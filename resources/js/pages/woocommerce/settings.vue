<template>
    <div>
        <!-- breadcrumbs Start -->
        <breadcrumbs :items="breadcrumbs" :current="breadcrumbsCurrent" />
        <!-- breadcrumbs end -->

        <div class="row">
            <div class="col-3">
                <div class="nav flex-column nav-pills settings-page-nav-wrapper" id="v-pills-tab" role="tablist"
                    aria-orientation="vertical">
                    <button class="nav-link active border-0 " id="v-pills-home-tab" data-toggle="pill"
                        data-target="#instruction" type="button" role="tab" aria-controls="v-pills-home"
                        aria-selected="true">{{
                            $t("woocommerce.instructions") }}</button>
                    <button class="nav-link border-0 " id="v-pills-profile-tab" data-toggle="pill"
                        data-target="#apiSettings" type="button" role="tab" aria-controls="v-pills-profile"
                        aria-selected="false"> {{
                            $t("woocommerce.api_settings") }}</button>
                    <button class="nav-link border-0" id="v-pills-messages-tab" data-toggle="pill"
                        data-target="#productSyncSettings" type="button" role="tab" aria-controls="v-pills-messages"
                        aria-selected="false"> {{
                            $t("woocommerce.product_sync_settings") }}</button>
                    <button class="nav-link border-0" id="v-pills-settings-tab" data-toggle="pill"
                        data-target="#orderSyncSettings" type="button" role="tab" aria-controls="v-pills-settings"
                        aria-selected="false"> {{
                            $t("woocommerce.order_sync_settings") }}</button>
                    <button class="nav-link border-0" id="v-pills-settings-tab" data-toggle="pill"
                        data-target="#webhookSettings" type="button" role="tab" aria-controls="v-pills-settings"
                        aria-selected="false"> Webhook Settings</button>
                </div>
            </div>

            <div class="col-9">
                <div class="tab-content" id="v-pills-tabContent">
                    <div class="tab-pane fade show active" id="instruction" role="tabpanel"
                        aria-labelledby="v-pills-home-tab">
                        <div class="card">
                            <div class="card-body">
                                <div class="tab-pane fade show active" id="list-instruction" role="tabpanel"
                                    aria-labelledby="list-instruction-list">
                                    <ul>
                                        <li>While synchronizing, refrain from refreshing the page or leaving it.</li>
                                        <li>To obtain WooCommerce API details, navigate to WooCommerce -&gt; Settings
                                            -&gt; Advance
                                            -&gt; REST API. Provide a description, select a user, and grant Read/Write
                                            Permission.
                                        </li>
                                        <li>In the WordPress permalink option, select Post Name as the permalinks
                                            option.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="apiSettings" role="tabpanel" aria-labelledby="v-pills-profile-tab">
                        <div class="card">
                            <div class="card-body">
                                <form role="form" @submit.prevent="updateWoocommerceCredentials"
                                    @keydown="form.onKeydown($event)">
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label for="woocomerce_app_url"><b>WooCommerce App URL</b></label>
                                            <input type="text" v-model="form.WooCommerce_App_URL" class="form-control"
                                                :class="{ 'is-invalid': form.errors.has('WooCommerce_App_URL') }"
                                                id="woocomerce_app_url" placeholder="WooCommerce App URL">
                                            <has-error :form="form" field="WooCommerce_App_URL" />
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="woocomerce_consumer_key"><b>WooCommerce Consumer Key</b></label>
                                            <input type="text" v-model="form.WooCommerce_Consumer_Key" class="form-control"
                                                :class="{ 'is-invalid': form.errors.has('WooCommerce_Consumer_Key') }"
                                                id="woocomerce_consumer_key" placeholder="WooCommerce Consumer Key:">
                                            <has-error :form="form" field="WooCommerce_Consumer_Key" />
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="woocomerce_consumer_secret"><b>WooCommerce Consumer
                                                    Secret</b></label>
                                            <input type="text" v-model="form.WooCommerce_Consumer_Secret"
                                                class="form-control"
                                                :class="{ 'is-invalid': form.errors.has('WooCommerce_Consumer_Secret') }"
                                                id="woocomerce_consumer_secret" placeholder="WooCommerce Consumer Secret">
                                            <has-error :form="form" field="WooCommerce_Consumer_Secret" />
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="productSyncSettings" role="tabpanel"
                        aria-labelledby="v-pills-messages-tab">
                        <div class="pos-tab-content active">
                            <div class="card">
                                <form role="form" @submit.prevent="updateWoocommerceProductSyncSettings"
                                    @keydown="form.onKeydown($event)">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="default_tax_class">Default Tax Class:</label> <i
                                                        class="fa fa-info-circle text-info hover-q no-print "
                                                        aria-hidden="true" data-container="body" data-toggle="popover"
                                                        data-placement="auto bottom"
                                                        data-content="Woocommerce Tax Class which will be set as default while synchronizing products. (Default: standard)"
                                                        data-html="true" data-trigger="hover" data-original-title=""
                                                        title=""></i>
                                                    <input class="form-control" name="default_tax_class" type="text"
                                                        value="" id="default_tax_class">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="product_tax_type">Sync Product Price:</label>
                                                    <select class="form-control" id="product_tax_type"
                                                        name="product_tax_type">
                                                        <option value="inc" selected="selected">Including Tax</option>
                                                        <option value="exc">Excluding Tax</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="default_selling_price_group">Default Selling Price
                                                        Group:</label>
                                                    <select class="form-control" name="default_selling_price_group"
                                                        id="default_selling_price_group">
                                                        <option value="default">Default</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="sync_description_as">Sync product description as:</label>
                                                    <select class="form-control select2 select2-hidden-accessible"
                                                        v-model="form.woocommerce_product_sync_desc" style="width: 100%;"
                                                        id="sync_description_as" name="sync_description_as" tabindex="-1"
                                                        aria-hidden="true">
                                                        <option value="short">Short description</option>
                                                        <option value="long" selected="selected">Long description</option>
                                                        <option value="both">Both</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="#">Product fields to be synced with woocommerce while
                                                        creating
                                                        products:</label><br>
                                                    <div class="form-check">
                                                        <input type="checkbox" disabled checked class="form-check-input"
                                                            id="productName">
                                                        <label class="form-check-label" for="productName"> Product
                                                            Name</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" disabled checked class="form-check-input"
                                                            id="price">
                                                        <label class="form-check-label" for="price">Price</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" disabled checked class="form-check-input"
                                                            id="category">
                                                        <label class="form-check-label" for="category">Category</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                            v-model="form.woocommerce_product_sync_create_quantity"
                                                            id="quantity">
                                                        <label class="form-check-label" for="quantity">Quantity</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                            v-model="form.woocommerce_product_sync_create_image" id="image">
                                                        <label class="form-check-label" for="image">Image</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                            v-model="form.woocommerce_product_sync_create_description"
                                                            id="description">
                                                        <label class="form-check-label"
                                                            for="description">Description</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="#">Product fields to be synced with woocommerce while
                                                        updating
                                                        products:</label><br>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                            v-model="form.woocommerce_product_sync_update_name"
                                                            id="productNameForUpdate">
                                                        <label class="form-check-label" for="productNameForUpdate"> Product
                                                            Name</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                            v-model="form.woocommerce_product_sync_update_price"
                                                            id="priceForUpdate">
                                                        <label class="form-check-label" for="priceForUpdate">Price</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                            v-model="form.woocommerce_product_sync_update_category"
                                                            id="categoryForUpdate">
                                                        <label class="form-check-label"
                                                            for="categoryForUpdate">Category</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                            v-model="form.woocommerce_product_sync_update_quantity"
                                                            id="quantityForUpdate">
                                                        <label class="form-check-label"
                                                            for="quantityForUpdate">Quantity</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                            v-model="form.woocommerce_product_sync_update_image"
                                                            id="imageForUpdate">
                                                        <label class="form-check-label" for="imageForUpdate">Image</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input"
                                                            v-model="form.woocommerce_product_sync_update_description"
                                                            id="descriptionForUpdate">
                                                        <label class="form-check-label"
                                                            for="descriptionForUpdate">Description</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Submit</button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="orderSyncSettings" role="tabpanel"
                        aria-labelledby="v-pills-settings-tab">
                        <div class="pos-tab-content active">
                            <div class="card">
                                <div class="card-body">
                                    Nothing yet
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="webhookSettings" role="tabpanel" aria-labelledby="v-pills-settings-tab">
                        <div class="pos-tab-content active">
                            <div class="card">
                                <div class="card-body">
                                    <form role="form" @submit.prevent="updateWoocommerceWHSettings"
                                        @keydown="form.onKeydown($event)">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Order Created</h5>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="woocommerce_wh_oc_secret">Webhook Secret:</label>
                                                    <input class="form-control" v-model="form.woocommerce_wh_oc_secret"
                                                        placeholder="Webhook Secret" name="woocommerce_wh_oc_secret"
                                                        type="text" id="woocommerce_wh_oc_secret">
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="form-group">
                                                    <strong>Webhook Delivery URL:</strong>
                                                    <p>http://acculance.test/webhook/order-created/1</p>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <h5>Order Updated</h5>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="woocommerce_wh_ou_secret">Webhook Secret:</label>
                                                    <input class="form-control" v-model="form.woocommerce_wh_ou_secret"
                                                        placeholder="Webhook Secret" name="woocommerce_wh_ou_secret"
                                                        type="text" id="woocommerce_wh_ou_secret">
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="form-group">
                                                    <strong>Webhook Delivery URL:</strong>
                                                    <p>http://acculance.test/webhook/order-updated/1</p>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <h5>Order Deleted</h5>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="woocommerce_wh_od_secret">Webhook Secret:</label>
                                                    <input class="form-control" v-model="form.woocommerce_wh_od_secret"
                                                        placeholder="Webhook Secret" name="woocommerce_wh_od_secret"
                                                        type="text" id="woocommerce_wh_od_secret">
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="form-group">
                                                    <strong>Webhook Delivery URL:</strong>
                                                    <p>http://acculance.test/webhook/order-deleted/1</p>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <h5>Order Restored</h5>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="woocommerce_wh_or_secret">Webhook Secret:</label>
                                                    <input class="form-control" v-model="form.woocommerce_wh_or_secret"
                                                        placeholder="Webhook Secret" name="woocommerce_wh_or_secret"
                                                        type="text" id="woocommerce_wh_or_secret">
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="form-group">
                                                    <strong>Webhook Delivery URL:</strong>
                                                    <p>http://acculance.test/webhook/order-restored/1</p>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Submit</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
  
<script>
import WoocommerceSidebar from './component/WoocommerceSidebar';
import Form from "vform";
import axios from "axios";

export default {
    middleware: ["auth", "check-permissions"],
    components: {
        WoocommerceSidebar,
    },
    metaInfo() {
        return { title: this.$t("woocommerce.woocommerce_settings") };
    },
    data: () => ({
        breadcrumbsCurrent: "woocommerce.woocommerce_settings",
        breadcrumbs: [
            {
                name: "setup.general_settings.index.breadcrumbs_first",
                url: "home",
            },
            {
                name: "woocommerce.woocommerce_settings",
                url: "",
            },
        ],
        form: new Form({
            WooCommerce_App_URL: "",
            WooCommerce_Consumer_Key: "",
            WooCommerce_Consumer_Secret: "",
            woocommerce_wh_oc_secret: "",
            woocommerce_wh_ou_secret: "",
            woocommerce_wh_od_secret: "",
            woocommerce_wh_or_secret: "",
            woocommerce_product_sync_desc: "",
            woocommerce_product_sync_create_quantity: "",
            woocommerce_product_sync_create_image: "",
            woocommerce_product_sync_create_description: "",
            woocommerce_product_sync_update_name: "",
            woocommerce_product_sync_update_price: "",
            woocommerce_product_sync_update_category: "",
            woocommerce_product_sync_update_quantity: "",
            woocommerce_product_sync_update_image: "",
            woocommerce_product_sync_update_description: "",
        }),
    }),
    created() {
        this.getWoocommerceCredentials();
        this.getWoocommerceWHInfo();
        this.getWoocommerceProductSyncSettingsInfo();
    },
    methods: {
        // get woocommerce credentials
        async getWoocommerceCredentials() {
            const { data } = await axios.get(
                window.location.origin + "/api/woocommerce/credentials"
            );
            this.form.WooCommerce_App_URL = data.WooCommerce_App_URL.value;
            this.form.WooCommerce_Consumer_Key = data.WooCommerce_Consumer_Key.value;
            this.form.WooCommerce_Consumer_Secret = data.WooCommerce_Consumer_Secret.value;
        },

        // get woocommerce webhook info
        async getWoocommerceWHInfo() {
            const { data } = await axios.get(
                window.location.origin + "/api/woocommerce-wh-settings-info"
            );
            this.form.woocommerce_wh_oc_secret = data.woocommerce_wh_oc_secret.replace(/\"/g, '');
            this.form.woocommerce_wh_ou_secret = data.woocommerce_wh_ou_secret.replace(/\"/g, '');
            this.form.woocommerce_wh_od_secret = data.woocommerce_wh_od_secret.replace(/\"/g, '');
            this.form.woocommerce_wh_or_secret = data.woocommerce_wh_or_secret.replace(/\"/g, '');
        },

        // get woocommerce product sync settings info
        async getWoocommerceProductSyncSettingsInfo() {
            const { data } = await axios.get(
                window.location.origin + "/api/woocommerce-product-sync-settings-info"
            );
            this.form.woocommerce_product_sync_desc = data.woocommerce_product_sync_desc.replace(/\"/g, '');

            this.form.woocommerce_product_sync_create_quantity = data.woocommerce_product_sync_create_quantity;
            this.form.woocommerce_product_sync_create_image = data.woocommerce_product_sync_create_image;
            this.form.woocommerce_product_sync_create_description = data.woocommerce_product_sync_create_description;

            this.form.woocommerce_product_sync_update_name = data.woocommerce_product_sync_update_name;
            this.form.woocommerce_product_sync_update_price = data.woocommerce_product_sync_update_price;
            this.form.woocommerce_product_sync_update_category = data.woocommerce_product_sync_update_category;
            this.form.woocommerce_product_sync_update_quantity = data.woocommerce_product_sync_update_quantity;
            this.form.woocommerce_product_sync_update_image = data.woocommerce_product_sync_update_image;
            this.form.woocommerce_product_sync_update_description = data.woocommerce_product_sync_update_description;
        },
        // update woocommerce credentials
        async updateWoocommerceCredentials() {
            await this.form.post(window.location.origin + "/api/update/woocommerce-credentials")
                .then(() => {
                    toast.fire({
                        type: "success",
                        title: this.$t("setup.general_settings.index.success_message"),
                    });
                })
                .catch(() => {
                    toast.fire({
                        type: "error",
                        title: this.$t("common.error_msg"),
                    });
                });
        },

        // update woocommerce webhook settings
        async updateWoocommerceWHSettings() {
            await this.form.post(window.location.origin + "/api/update-woocommerce-wh-settings")
                .then(() => {
                    toast.fire({
                        type: "success",
                        title: this.$t("setup.general_settings.index.success_message"),
                    });
                })
                .catch(() => {
                    toast.fire({
                        type: "error",
                        title: this.$t("common.error_msg"),
                    });
                });
        },

        // update woocommerce product sync settings
        async updateWoocommerceProductSyncSettings() {
            await this.form.post(window.location.origin + "/api/update-woocommerce-product-sync-settings")
                .then(() => {
                    toast.fire({
                        type: "success",
                        title: this.$t("setup.general_settings.index.success_message"),
                    });
                })
                .catch(() => {
                    toast.fire({
                        type: "error",
                        title: this.$t("common.error_msg"),
                    });
                });
        },
    }
};
</script>
  