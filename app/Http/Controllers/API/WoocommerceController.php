<?php

namespace App\Http\Controllers\API;

use Carbon;
use App\Models\Client;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\VatRate;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\InvoiceReturn;
use App\Models\InvoicePayment;
use App\Models\InvoiceProduct;
use App\Models\ProductCategory;
use App\Models\AccountTransaction;
use App\Models\ProductSubCategory;
use App\Models\WoocommerceSyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\InvoiceReturnProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\WoocommerceSyncSetting;
use App\Http\Resources\AccountResource;
use App\Models\WooCommerceSyncSettings;
use Jackiedo\DotenvEditor\Facades\DotenvEditor;
use App\Http\Resources\WoocommerceSyncLogResource;
use App\Http\Requests\WoocommerceCredentialRequest;
use Automattic\WooCommerce\Client as  WooCommerceClient;

class WoocommerceController extends Controller
{
    // get woocommerce credentials from .evv file
    public function getWoocommerceCredentials()
    {
        $editor = DotenvEditor::load();
        $data = [
            'WooCommerce_App_URL' => $editor->getKey('WOOCOMMERCE_APP_URL'),
            'WooCommerce_Consumer_Key' => $editor->getKey('WOOCOMMERCE_CONSUMER_KEY'),
            'WooCommerce_Consumer_Secret' =>  $editor->getKey('WOOCOMMERCE_CONSUMER_SECRET'),
        ];
        return $data;
    }

    // update woocommerce credentials from .env file
    public function updateWoocommerceCredentials(WoocommerceCredentialRequest $request)
    {
        $editor = DotenvEditor::load();
        $editor->setKey('WOOCOMMERCE_APP_URL', $request->WooCommerce_App_URL);
        $editor->setKey('WOOCOMMERCE_CONSUMER_KEY', $request->WooCommerce_Consumer_Key);
        $editor->setKey('WOOCOMMERCE_CONSUMER_SECRET', $request->WooCommerce_Consumer_Secret);
        $editor->save();

        return 'Credentials updated successfully!';
    }

    // get woocommerce credentials
    private function getWooCommerceClient()
    {
        return new WooCommerceClient(
            env('WOOCOMMERCE_APP_URL'),
            env('WOOCOMMERCE_CONSUMER_KEY'),
            env('WOOCOMMERCE_CONSUMER_SECRET'),
            [
                'wp_api' => true,
                'version' => 'wc/v3',
            ]
        );
    }

    //sync woocommerce products
    public function syncWoocommerceProducts()
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 0);

        $user_id = Auth::user()->id;
        $offset = 0;
        $limit = 100;
        return  $all_products = $this->syncProducts($user_id, $limit, $offset);
    }

    // reset synced products
    public function resetSyncWoocommerceProducts()
    {
        Product::query()->update(['woocommerce_product_id' => null, 'woocommerce_media_id' => null]);

        $user_id = Auth::user()->id;
        $this->createSyncLog($user_id, 'products', 'reset', null);
    }

    public function syncProducts($user_id, $limit = 100, $page = 0)
    {
        $productSyncSettingsInfo = $this->getWoocommerceProductSyncSettingsInfo();
        $last_synced =  $this->getLastSync('all_products', false);
        $created_data = [];
        $updated_data = [];
        $offset = $page * $limit;

        $query = Product::with(['proSubCategory'])->where('status', true);
        if ($limit > 0) {
            $query->limit($limit)
                ->offset($offset);
        }
        $all_products = $query->get();
        $product_data = [];
        $new_products = [];
        $updated_products = [];

        foreach ($all_products as $product) {
            //Set common data
            $array = [
                'type' => 'simple',
            ];
            $price = $product->sellingPrice();
            $qty_available = $product->inventory_count;

            $product_cat = [];
            if (!empty($product->proSubCategory)) {
                $product_cat[] = ['id' => $product->proSubCategory?->woocommerce_category_id];
            }


            if (empty($product->woocommerce_product_id)) {
                if (!empty($product_cat)) {
                    $array['categories'] = $product_cat;
                }

                if ($productSyncSettingsInfo['woocommerce_product_sync_create_description'] == "true") {
                    if (!empty($product->note)) {
                        $array['description'] = $product->note;
                    }
                }


                //If media id is set use media id else use image src
                if ($productSyncSettingsInfo['woocommerce_product_sync_create_image'] == "true") {
                    if (!empty($product->image_path)) { // need to dynamic
                        $array['images'] = !empty($product->woocommerce_media_id) ? [['id' => $product->woocommerce_media_id]] : [['src' => "https://codeshaper.net/img/front-end/team/Shuvo.png"]];
                    }
                }


                if ($productSyncSettingsInfo['woocommerce_product_sync_create_quantity'] == "true") {
                    $array['manage_stock'] = true;
                    $array['stock_quantity'] = $qty_available > 0 ? $qty_available : 0;
                }

                $array['price'] = $price;
                $array['regular_price'] = $price;
                $array['name'] = $product->name;

                $product_data['create'][] = $array;
                $new_products[] = $product;
                $created_data[] = $product->code;
            } else {
                $array['id'] = $product->woocommerce_product_id;

                if ($productSyncSettingsInfo['woocommerce_product_sync_update_category'] == "true") {
                    $array['categories'] = $product_cat;
                }
                if ($productSyncSettingsInfo['woocommerce_product_sync_update_description'] == "true") {
                    if (!empty($product->note)) {
                        $array['description'] = $product->note;
                    }
                }
                //If media id is set use media id else use image src
                if ($productSyncSettingsInfo['woocommerce_product_sync_update_image'] == "true") {
                    if (!empty($product->image_path)) {
                        $array['images'] = [['src' => "https://codeshaper.net/img/front-end/team/Alok.png"]];  // need to dynamic
                    }
                }
                if ($productSyncSettingsInfo['woocommerce_product_sync_update_quantity'] == "true") {
                    $array['manage_stock'] = true;
                    $array['stock_quantity'] = $qty_available > 0 ? $qty_available : 0;
                }
                if ($productSyncSettingsInfo['woocommerce_product_sync_update_price'] == "true") {
                    $array['price'] = $price;
                    $array['regular_price'] = $price;
                }
                if ($productSyncSettingsInfo['woocommerce_product_sync_update_name'] == "true") {
                    $array['name'] = $product->name;
                }

                $product_data['update'][] = $array;
                $updated_products[] = $product;
                $updated_data[] = $product->code;
            }
        }

        $create_response = [];
        $update_response = [];

        if (!empty($product_data['create'])) {
            $create_response = $this->syncProd($product_data['create'], 'create', $new_products);
        }
        if (!empty($product_data['update'])) {
            $update_response = $this->syncProd($product_data['update'], 'update', $updated_products);
        }
        $new_woocommerce_product_ids = array_merge($create_response, $update_response);

        //Create log
        if (!empty($created_data)) {
            $this->createSyncLog($user_id, 'all_products', 'created', $created_data);
        }
        if (!empty($updated_data)) {
            $this->createSyncLog($user_id, 'all_products', 'updated', $updated_data);
        }

        if (empty($created_data) && empty($updated_data)) {
            $this->createSyncLog($user_id, 'all_products');
        }

        return $all_products;
    }

    public function syncProd($data, $type, $new_products)
    {
        $woocommerce = $this->getWooCommerceClient();

        $new_woocommerce_product_ids = [];
        $count = 0;

        foreach (array_chunk($data, 99) as $chunked_array) {
            $sync_data = [];
            $sync_data[$type] = $chunked_array;
            $response = $woocommerce->post('products/batch', $sync_data);

            if (!empty($response->create)) {
                foreach ($response->create as $key => $value) {
                    $new_product = $new_products[$count];
                    if ($value->id != 0) {
                        $new_product->woocommerce_product_id = $value->id;
                        //Sync woocommerce media id
                        $new_product->woocommerce_media_id = !empty($value->images[0]->id) ? $value->images[0]->id : null;
                    } else {
                        if (!empty($value->error->data->resource_id)) {
                            $new_product->woocommerce_product_id = $value->error->data->resource_id;
                        }
                    }
                    $new_product->save();
                    $new_woocommerce_product_ids[] = $new_product->woocommerce_product_id;
                    $count++;
                }
            }

            if (!empty($response->update)) {
                foreach ($response->update as $key => $value) {
                    $updated_product = $new_products[$count];
                    if ($value->id != 0) {
                        //Sync woocommerce media id
                        $updated_product->woocommerce_media_id = !empty($value->images[0]->id) ? $value->images[0]->id : null;
                        $updated_product->save();
                    }
                    $new_woocommerce_product_ids[] = $updated_product->woocommerce_product_id;
                    $count++;
                }
            }
        }
        return $new_woocommerce_product_ids;
    }



    public function syncWoocommerceProductCategories()
    {
        $user_id = Auth::user()->id;
        $this->syncProductCategories($user_id);
    }

    public function resetWoocommerceProductCategories()
    {
        ProductCategory::query()->update(['woocommerce_category_id' => null]);
        ProductSubCategory::query()->update(['woocommerce_category_id' => null]);

        $user_id = Auth::user()->id;
        $this->createSyncLog($user_id, 'categories', 'reset', null);
    }

    public function syncProductCategories($user_id)
    {
        $last_synced = $this->getLastSync('categories', false);

        //Update parent categories
        $query = ProductCategory::where('status', true);
        //Limit query to last sync
        if (!empty($last_synced)) {
            $query->where('updated_at', '>', $last_synced);
        }
        $categories = $query->get();

        $category_data = [];
        $new_categories = [];
        $created_data = [];
        $updated_data = [];

        foreach ($categories as $category) {
            if (empty($category->woocommerce_category_id)) {
                $category_data['create'][] = [
                    'name' => $category->name
                ];
                $new_categories[] = $category;
                $created_data[] = $category->name;
            } else {
                $category_data['update'][] = [
                    'id' => $category->woocommerce_category_id,
                    'name' => $category->name
                ];
                $updated_data[] = $category->name;
            }
        }

        if (!empty($category_data['create'])) {
            $this->syncCategories($category_data['create'], 'create', $new_categories);
        }
        if (!empty($category_data['update'])) {
            $this->syncCategories($category_data['update'], 'update', $new_categories);
        }

        //Sync sub categories
        $query2 = ProductSubCategory::where('status', true);
        //Limit query to last sync
        if (!empty($last_synced)) {
            $query2->where('updated_at', '>', $last_synced)->orWhereNull('woocommerce_category_id');
        }

        $child_categories = $query2->get();
        $cat_id_woocommerce_id = ProductCategory::where('status', true)
            ->pluck('woocommerce_category_id', 'id')
            ->toArray();

        $category_data = [];
        $new_categories = [];
        foreach ($child_categories as $category) {

            if (empty($cat_id_woocommerce_id[$category->cat_id])) {
                continue;
            }

            if (empty($category->woocommerce_category_id)) {
                $category_data['create'][] = [
                    'name' => $category->name,
                    'parent' => $cat_id_woocommerce_id[$category->cat_id]
                ];
                $new_categories[] = $category;
                $created_data[] = $category->name;
            } else {
                $category_data['update'][] = [
                    'id' => $category->woocommerce_category_id,
                    'name' => $category->name,
                    'parent' => $cat_id_woocommerce_id[$category->cat_id]
                ];
                $updated_data[] = $category->name;
            }
        }

        if (!empty($category_data['create'])) {
            $this->syncCategories($category_data['create'], 'create', $new_categories);
        }
        if (!empty($category_data['update'])) {
            $this->syncCategories($category_data['update'], 'update', $new_categories);
        }

        //Create log
        if (!empty($created_data)) {
            $this->createSyncLog($user_id, 'categories', 'created', $created_data);
        }
        if (!empty($updated_data)) {
            $this->createSyncLog($user_id, 'categories', 'updated', $updated_data);
        }
        if (empty($created_data) && empty($updated_data)) {
            $this->createSyncLog($user_id, 'categories');
        }
    }

    public function getLastSync($type, $for_humans = true)
    {
        $last_sync = WoocommerceSyncLog::where('sync_type', $type)
            ->max('created_at');

        //If last reset present make last sync to null
        $last_reset = WoocommerceSyncLog::where('sync_type', $type)
            ->where('operation_type', 'reset')
            ->max('created_at');
        if (!empty($last_reset) && !empty($last_sync) && $last_reset >= $last_sync) {
            $last_sync = null;
        }

        if (!empty($last_sync) && $for_humans) {
            $last_sync = Carbon::createFromFormat('Y-m-d H:i:s', $last_sync)->diffForHumans();
        }
        return $last_sync;
    }

    public function createSyncLog($user_id, $type, $operation = null, $data = [], $errors = null)
    {
        WoocommerceSyncLog::create([
            'sync_type' => $type,
            'created_by' => $user_id,
            'operation_type' => $operation,
            'data' => !empty($data) ? json_encode($data) : null,
            'details' => !empty($errors) ? json_encode($errors) : null
        ]);
    }

    public function syncCategories($data, $type, $new_categories = [])
    {
        //woocommerce api client object
        $woocommerce = $this->getWooCommerceClient();
        $count = 0;
        foreach (array_chunk($data, 99) as $chunked_array) {
            $sync_data = [];
            $sync_data[$type] = $chunked_array;

            //Batch update categories
            $response = $woocommerce->post('products/categories/batch', $sync_data);

            //update woocommerce_category_id
            if (!empty($response->create)) {
                foreach ($response->create as $key => $value) {
                    $new_category = $new_categories[$count];
                    if ($value->id != 0) {
                        $new_category->woocommerce_category_id = $value->id;
                    } else {
                        if (!empty($value->error->data->resource_id)) {
                            $new_category->woocommerce_category_id = $value->error->data->resource_id;
                        }
                    }
                    $new_category->save();
                    $count++;
                }
            }
        }
    }

    public function syncWoocommerceOrders()
    {
        try {

            $user_id = Auth::user()->id;

            $this->syncOrders($user_id);

            $output = [
                'success' => 1,
                'msg' => "Orders synced_successfully"
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            $output = [
                'success' => 0,
                'msg' => $e->getMessage(),
            ];
        }
        return $output;
    }

    public function syncOrders($user_id)
    {

        $last_synced = $this->getLastSync('orders', false);
        $orders = $this->getAllResponse('orders');

        $woocommerce_sells = Invoice::whereNotNull('woocommerce_order_id')->get();

        $created_data = [];
        $updated_data = [];
        $create_error_data = [];
        $update_error_data = [];

        foreach ($orders as $order) {
            //Search if order already exists
            $sell = $woocommerce_sells->filter(function ($item) use ($order) {
                return $item->woocommerce_order_id == $order->id;
            })->first();

            $order_number = $order->number;

            if (empty($sell)) {
                $created = $this->createNewSaleFromOrder($user_id, $order);
                $created_data[] = $order_number;

                if ($created !== true) {
                    $create_error_data[] = $created;
                }
            } else {
                $updated = $this->updateSaleFromOrder($user_id, $order, $sell);
                $updated_data[] = $order_number;

                if ($updated !== true) {
                    $update_error_data[] = $updated;
                }
            }
        }

        //Create log
        if (!empty($created_data)) {
            $this->createSyncLog($user_id, 'orders', 'created', $created_data, $create_error_data);
        }
        if (!empty($updated_data)) {
            $this->createSyncLog($user_id, 'orders', 'updated', $updated_data, $update_error_data);
        }

        if (empty($created_data) && empty($updated_data)) {
            $error_data = $create_error_data + $update_error_data;
            $this->createSyncLog($user_id, 'orders', null, [], $error_data);
        }
    }

    public function getAllResponse($endpoint, $params = [])
    {
        //woocommerce api client object
        $woocommerce = $this->getWooCommerceClient();

        $page = 1;
        $list = [];
        $all_list = [];
        $params['per_page'] = 100;

        do {
            $params['page'] = $page;
            try {
                $list = $woocommerce->get($endpoint, $params);
            } catch (\Exception $e) {
                return [];
            }
            $all_list = array_merge($all_list, $list);
            $page++;
        } while (count($list) > 0);

        return $all_list;
    }

    public function createNewSaleFromOrder($user_id, $order)
    {
        $lineItems = $order->line_items;
        $missingProducts = $this->checkMissingProducts($lineItems);

        if (!empty($missingProducts)) {
            return [
                'has_error' => [
                    'error_type' => 'order_product_not_found',
                    'order_number' => $order->number,
                    'products' => $missingProducts
                ]
            ];
        } else {
            $customer = $this->getOrCreateCustomer($order);

            $invoice = $this->createInvoice($user_id, $order, $customer);

            $this->storeInvoiceProducts($order, $invoice);

            $this->handleTransaction($user_id, $order, $invoice);

            return true;
        }
    }

    public function updateSaleFromOrder($user_id, $order, $sell)
    {
        $lineItems = $order->line_items;
        $missingProducts = $this->checkMissingProducts($lineItems);

        if (!empty($missingProducts)) {
            return [
                'has_error' => [
                    'error_type' => 'order_product_not_found',
                    'order_number' => $order->number,
                    'products' => $missingProducts
                ]
            ];
        } else {
            $previousOrder = Invoice::where('woocommerce_order_id', $order->id)->first();

            if ($previousOrder->woocommerce_order_status == "completed" && $order->status == "refunded") {

                $account = $this->woocommerceOrderSettingAccount()->toArray(null);
                $currentDateTime = now()->format('Y-m-d H:i:s');
                $firstRefundReason = $order->refunds[0]->reason;

                // generate code
                $code = 1;
                $lastReturn = InvoiceReturn::latest()->first();
                if ($lastReturn) {
                    $code = $lastReturn->return_no + 1;
                }

                $reason = '[' . config('config.invoiceReturnPrefix') . '-' . $code . '] Invoice Return payable sent from [' . $account['accountNumber'] . ']';
                // create transaction
                $transaction = AccountTransaction::create([
                    'account_id' => $account['id'],
                    'amount' => $order->total,
                    'reason' => $reason,
                    'type' => 0,
                    'transaction_date' => $currentDateTime,
                    'cheque_no' => "",
                    'receipt_no' => "",
                    'created_by' => $user_id,
                    'status' => true,
                ]);

                // store invoice return
                $invoiceReturn = InvoiceReturn::create([
                    'reason' => $firstRefundReason ?? null,
                    'return_no' => $code,
                    'invoice_id' => $previousOrder->id,
                    'total_return' => $order->total,
                    'date' => $currentDateTime,
                    'note' => "",
                    'transaction_id' => $transaction->id,
                    'created_by' => $user_id,
                    'status' => true,
                ]);

                // store invoice products
                foreach ($order->line_items as $selectedProduct) {
                    // update product inventory
                    $product = Product::where('woocommerce_product_id', $selectedProduct->product_id)->first();
                    $product->update([
                        'inventory_count' => $product->inventory_count + $selectedProduct->quantity,
                    ]);

                    // store return product
                    if ($selectedProduct->quantity > 0) {
                        InvoiceReturnProduct::create([
                            'return_id' => $invoiceReturn->id,
                            'product_id' => $product->id,
                            'sale_price' => $product->price, // update [line items->price]
                            'purchase_price' => $product->price, // update [acculance product price]
                            'quantity' => $selectedProduct->quantity,
                        ]);
                    }
                }

                $previousOrder->update([
                    'woocommerce_order_status' => 'refunded'
                ]);
            } else {
                $customer = $this->updateCustomer($order);
                $invoice = $this->updateInvoice($user_id, $order, $customer);

                $this->updateInvoiceProducts($order, $invoice);
                $this->updateTransaction($user_id, $order, $invoice, $previousOrder);

                return true;
            }
        }
    }

    private function checkMissingProducts($lineItems)
    {
        $missingProducts = [];

        foreach ($lineItems as $item) {
            $productId = $item->product_id;
            $productExists = Product::where('woocommerce_product_id', $productId)->exists();

            if (!$productExists) {
                $missingProducts[] = $item->name;
            }
        }

        return $missingProducts;
    }

    private function getOrCreateCustomer($order)
    {
        $woocommerce_customer_id = $order->customer_id;
        $clientCode = $this->generateClientCode();

        if (empty($woocommerce_customer_id)) {
            $billingDetails = $order->billing;
            $customerData = [
                'name' => $billingDetails->first_name . ' ' . $billingDetails->last_name,
                'client_id' => $clientCode,
                'email' => $billingDetails->email ?? null,
                'phone' => $billingDetails->phone,
                'address' => $billingDetails->address_1 ?? null,
                'status' => true,
            ];
        } else {
            $customerData = $this->getCustomerDataFromWooCommerce($woocommerce_customer_id, $clientCode);
        }

        return Client::firstOrCreate(['email' => $customerData['email']], $customerData);
    }

    private function generateClientCode()
    {
        $clientCode = 1;
        $lastClient = Client::latest()->first();

        if ($lastClient) {
            $clientCode = $lastClient->client_id + 1;
        }

        return $clientCode;
    }

    private function getCustomerDataFromWooCommerce($woocommerce_customer_id, $clientCode)
    {
        $woocommerce = $this->getWooCommerceClient();
        $order_customer = $woocommerce->get('customers/' . $woocommerce_customer_id);

        return [
            'name' => $order_customer->first_name . ' ' . $order_customer->last_name,
            'client_id' => $clientCode,
            'email' => $order_customer->email,
            'phone' => $order_customer->billing->phone,
            'address' => $order_customer->billing->city . ' ' . $order_customer->billing->state . ' ' . $order_customer->billing->country,
            'status' => true,
        ];
    }

    private function updateCustomer($order)
    {
        if (!empty($order->billing->email)) {
            $email = $order->billing->email;
            Client::where('email', $email)->update([
                'name' => $order->billing->first_name . ' ' . $order->billing->last_name,
                'phone' => $order->billing->phone,
                'address' => $order->billing->city . ' ' . $order->billing->state . ' ' . $order->billing->country,
            ]);

            return Client::where('email', $email)->first();
        }

        return null;
    }

    private function updateInvoice($user_id, $order, $customer)
    {
        $totalSubtotal = $this->calculateTotalSubtotal($order->line_items);
        $discountDetails = $this->extractDiscountDetails($order->coupon_lines);
        $deliveryPlace = $this->extractDeliveryPlace($order->shipping);

        $invoice = Invoice::where('woocommerce_order_id', $order->id)->first();
        $invoice->update([
            'client_id' => $customer->id,
            'transport' => $order->shipping_total,
            'discount_type' => $discountDetails['type'],
            'discount' => $discountDetails['value'],
            'sub_total' => $totalSubtotal,
            'delivery_place' => $deliveryPlace,
            'tax_id' => 1, // need to be dynamic
            'invoice_date' => $order->date_created,
            'status' => true,
            'woocommerce_order_id' => $order->id,
            'woocommerce_order_status' => $order->status,
            'is_paid' => $order->status == "completed" ? true : false,
            'created_by' => $user_id,
        ]);

        return $invoice;
    }

    private function createInvoice($user_id, $order, $customer)
    {
        $totalSubtotal = $this->calculateTotalSubtotal($order->line_items);
        $discountDetails = $this->extractDiscountDetails($order->coupon_lines);
        $invoiceCode = $this->generateInvoiceCode();
        $deliveryPlace = $this->extractDeliveryPlace($order->shipping);

        return Invoice::create([
            'invoice_no' => $invoiceCode,
            'reference' => "",
            'slug' => uniqid(),
            'client_id' => $customer->id,
            'transport' => $order->shipping_total,
            'discount_type' => $discountDetails['type'],
            'discount' => $discountDetails['value'],
            'sub_total' => $totalSubtotal,
            'po_reference' => null,
            'payment_terms' => null,
            'delivery_place' => $deliveryPlace,
            'tax_id' => 1, // need to be dynamic
            'invoice_date' => $order->date_created,
            'status' => true,
            'woocommerce_order_id' => $order->id,
            'woocommerce_order_status' => $order->status,
            'is_paid' => $order->status == "completed" ? true : false,
            'created_by' => $user_id,
        ]);
    }

    private function calculateTotalSubtotal($lineItems)
    {
        $productIds = Product::pluck('woocommerce_product_id')->toArray();
        $totalSubtotal = 0;

        foreach ($lineItems as $item) {
            if (in_array($item->product_id, $productIds)) {
                $totalSubtotal += floatval($item->subtotal);
            }
        }

        return $totalSubtotal;
    }

    private function extractDiscountDetails($couponLines)
    {
        $discountValue = null;
        $discountType = null;

        if (!empty($couponLines[0])) {
            $couponLine = $couponLines[0];

            $discountValue = $couponLine->discount ?? null;

            foreach ($couponLine->meta_data as $meta) {
                if ($meta->key === 'coupon_data') {
                    $discountType = $meta->value->discount_type;
                    break;
                }
            }
        }

        return ['value' => $discountValue, 'type' => $discountType == "percent" ? 1 : 0];
    }

    private function generateInvoiceCode()
    {
        $invoiceCode = 1;
        $lastInvoice = Invoice::latest()->first();
        if ($lastInvoice) {
            $invoiceCode = $lastInvoice->invoice_no + 1;
        }
        return $invoiceCode;
    }

    private function extractDeliveryPlace($shipping)
    {
        return $shipping->address_1 . ', ' .
            $shipping->city . ', ' .
            $shipping->state;
    }

    private function storeInvoiceProducts($order, $invoice)
    {
        foreach ($order->line_items as $selectedProduct) {
            $product = Product::where('woocommerce_product_id', $selectedProduct->product_id)->first();
            if (!empty($product)) {
                $product->decrement('inventory_count', $selectedProduct->quantity);

                InvoiceProduct::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'quantity' => $selectedProduct->quantity,
                    'purchase_price' => $product->purchase_price,
                    'sale_price' => $selectedProduct->price,
                    'unit_cost' => $selectedProduct->price, // need to discuss it
                    'tax_amount' => $selectedProduct->total_tax, // need to discuss it
                ]);
            }
        }
    }

    private function updateInvoiceProducts($order, $invoice)
    {
        InvoiceProduct::where('invoice_id', $invoice->id)->delete();

        foreach ($order->line_items as $selectedProduct) {
            $product = Product::where('woocommerce_product_id', $selectedProduct->product_id)->first();
            if (!empty($product)) {

                InvoiceProduct::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'quantity' => $selectedProduct->quantity,
                    'purchase_price' => $product->purchase_price,
                    'sale_price' => $selectedProduct->price,
                    'unit_cost' => $selectedProduct->price, // need to discuss it
                    'tax_amount' => $selectedProduct->total_tax, // need to discuss it
                ]);
            }
        }
    }


    private function handleTransaction($user_id, $order, $invoice)
    {
        $account = $this->woocommerceOrderSettingAccount()->toArray(null);

        if ($order->status == "completed") {
            $reason = '[' . config('config.invoicePrefix') . '-' . $invoice->invoice_no . '] Invoice Payment added to [' .  $account['accountNumber'] . ']';

            $transaction = AccountTransaction::create([
                'account_id' =>  $account['id'],
                'amount' => $order->total,
                'reason' => $reason,
                'type' => 1,
                'transaction_date' => $order->date_paid,
                'cheque_no' => "",
                'receipt_no' => "",
                'created_by' => $user_id,
                'status' => true,
            ]);

            InvoicePayment::create([
                'slug' => uniqid(),
                'invoice_id' => $invoice->id,
                'transaction_id' => $transaction->id,
                'amount' => $order->total,
                'date' => $order->date_paid,
                'note' => $order->customer_note,
                'created_by' => $user_id,
                'status' => true,
            ]);
        }
    }

    private function updateTransaction($user_id, $order, $invoice, $previousOrder)
    {
        $account = $this->woocommerceOrderSettingAccount()->toArray(null);

        if ($order->status == "completed" && $previousOrder->woocommerce_order_status != "completed") {
            $reason = '[' . config('config.invoicePrefix') . '-' . $invoice->invoice_no . '] Invoice Payment added to [' .  $account['accountNumber'] . ']';

            $transaction = AccountTransaction::create([
                'account_id' => $account['id'],
                'amount' => $order->total,
                'reason' => $reason,
                'type' => 1,
                'transaction_date' => $order->date_paid,
                'cheque_no' => "",
                'receipt_no' => "",
                'created_by' => $user_id,
                'status' => true,
            ]);

            InvoicePayment::create([
                'slug' => uniqid(),
                'invoice_id' => $invoice->id,
                'transaction_id' => $transaction->id,
                'amount' => $order->total,
                'date' => $order->date_paid,
                'note' => $order->customer_note,
                'created_by' => $user_id,
                'status' => true,
            ]);
        }
    }


    public function woocommerceSyncLogs(Request $request)
    {
        return WoocommerceSyncLogResource::collection(WoocommerceSyncLog::orderBy('id', 'desc')->paginate($request->perPage));
    }

    public function woocommerceSyncLogSearch(Request $request)
    {
        $term = $request->term;
        $query = WoocommerceSyncLog::with('user')->orderBy('id', 'desc');

        if ($request->startDate && $request->endDate) {
            $query = $query->whereBetween('created_at', [$request->startDate, $request->endDate]);
        }

        $query = $query->where(function ($query) use ($term) {
            $query->where('sync_type', 'LIKE', '%' . $term . '%')
                ->orWhere('operation_type', 'LIKE', '%' . $term . '%')
                ->orWhereHas('user', function ($newQuery) use ($term) {
                    $newQuery->where('name', 'LIKE', '%' . $term . '%');
                });
        });

        return WoocommerceSyncLogResource::collection($query->paginate($request->perPage));
    }


    public function updateWoocommerceOrderSettings(Request $request)
    {
        $accountId = $request->account['id'];
        WoocommerceSyncSetting::updateOrCreate(['name' => 'account'], ['value' => json_encode($accountId)]);
    }

    public function woocommerceOrderSettingAccount()
    {
        $accountId = WoocommerceSyncSetting::where('name', 'account')->first()->value ?? 1; // if not set, return default account
        $account = Account::where('id', $accountId)->first();
        return new AccountResource($account);
    }

    // woocommerce vat rates
    public function woocommerceAllVatRates()
    {
        $taxes = $this->getAllResponse('taxes');
        return response()->json($taxes);
    }


    // woocommerce map vat rates
    public function woocommerceMapVatRates(Request $request)
    {
        $requestData = $request->all();

        foreach ($requestData as $vatRateId => $woocommerceTaxRateId) {
            VatRate::where('id', $vatRateId)->update(['woocommerce_tax_rate_id' => $woocommerceTaxRateId['id']]);
        }
    }

    // update woocommerce webhook settings
    public function updateWoocommerceWHSettings(Request $request)
    {
        $settings = [
            'woocommerce_wh_oc_secret' => $request->woocommerce_wh_oc_secret,
            'woocommerce_wh_ou_secret' => $request->woocommerce_wh_ou_secret,
            'woocommerce_wh_od_secret' => $request->woocommerce_wh_od_secret,
            'woocommerce_wh_or_secret' => $request->woocommerce_wh_or_secret,
        ];

        foreach ($settings as $name => $value) {
            $value = $value !== null ? json_encode($value) : null;
            WoocommerceSyncSetting::updateOrCreate(['name' => $name], ['value' => $value]);
        }
    }

    // get woocommerce webhook settings info
    public function getWoocommerceWHSettingsInfo()
    {
        $data = [
            'woocommerce_wh_oc_secret' => WoocommerceSyncSetting::where('name', 'woocommerce_wh_oc_secret')->first()->value ?? '',
            'woocommerce_wh_ou_secret' => WoocommerceSyncSetting::where('name', 'woocommerce_wh_ou_secret')->first()->value ?? '',
            'woocommerce_wh_od_secret' => WoocommerceSyncSetting::where('name', 'woocommerce_wh_od_secret')->first()->value ?? '',
            'woocommerce_wh_or_secret' => WoocommerceSyncSetting::where('name', 'woocommerce_wh_or_secret')->first()->value ?? '',
        ];
        return $data;
    }

    // update woocommerce product syns settings
    public function woocommerceproductSyncSettings(Request $request)
    {
        // return $request->all();
        WoocommerceSyncSetting::updateOrCreate(['name' => 'woocommerce_product_sync_desc'], ['value' => json_encode($request->woocommerce_product_sync_desc)]);

        $checkboxFields = [
            'woocommerce_product_sync_create_quantity',
            'woocommerce_product_sync_create_image',
            'woocommerce_product_sync_create_description',
            'woocommerce_product_sync_update_name',
            'woocommerce_product_sync_update_price',
            'woocommerce_product_sync_update_category',
            'woocommerce_product_sync_update_quantity',
            'woocommerce_product_sync_update_image',
            'woocommerce_product_sync_update_description'
        ];

        foreach ($checkboxFields as $fieldName) {
            $value = $request->{$fieldName} ? json_encode(true) : null;
            WoocommerceSyncSetting::updateOrCreate(
                ['name' => $fieldName],
                ['value' => $value]
            );
        }
    }

    // get woocommerce product sync settings info
    public function getWoocommerceProductSyncSettingsInfo()
    {
        $data = [
            'woocommerce_product_sync_desc' => WoocommerceSyncSetting::where('name', 'woocommerce_product_sync_desc')->first()->value ?? '',
            'woocommerce_product_sync_create_quantity' => WoocommerceSyncSetting::where('name', 'woocommerce_product_sync_create_quantity')->first()->value ?? '',
            'woocommerce_product_sync_create_image' => WoocommerceSyncSetting::where('name', 'woocommerce_product_sync_create_image')->first()->value ?? '',
            'woocommerce_product_sync_create_description' => WoocommerceSyncSetting::where('name', 'woocommerce_product_sync_create_description')->first()->value ?? '',
            'woocommerce_product_sync_update_name' => WoocommerceSyncSetting::where('name', 'woocommerce_product_sync_update_name')->first()->value ?? '',
            'woocommerce_product_sync_update_price' => WoocommerceSyncSetting::where('name', 'woocommerce_product_sync_update_price')->first()->value ?? '',
            'woocommerce_product_sync_update_category' => WoocommerceSyncSetting::where('name', 'woocommerce_product_sync_update_category')->first()->value ?? '',
            'woocommerce_product_sync_update_quantity' => WoocommerceSyncSetting::where('name', 'woocommerce_product_sync_update_quantity')->first()->value ?? '',
            'woocommerce_product_sync_update_image' => WoocommerceSyncSetting::where('name', 'woocommerce_product_sync_update_image')->first()->value ?? '',
            'woocommerce_product_sync_update_description' => WoocommerceSyncSetting::where('name', 'woocommerce_product_sync_update_description')->first()->value ?? '',
        ];
        return $data;
    }

    // webhook order created
    public function woocommerceWHorderCreated(Request $request)
    {

        Log::info("hi");
        // $payload = $request->getContent();
        // $user_id = Auth::user()->id;
        // $order = json_decode($payload);

        // $created = $this->createNewSaleFromOrder($user_id, $order);
        // $created_data[] = $order->number;
        // if ($created !== true) {
        //     $create_error_data[] = $created;
        // }

        // //Create log
        // if (!empty($created_data)) {
        //     $this->createSyncLog($user_id, 'orders', 'created', $created_data, $create_error_data);
        // }
    }
}
