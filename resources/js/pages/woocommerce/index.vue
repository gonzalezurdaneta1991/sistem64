<template>
    <div>
        <!-- breadcrumbs Start -->
        <breadcrumbs :items="breadcrumbs" :current="breadcrumbsCurrent" />
        <!-- breadcrumbs end -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Account for transections</h3>
                    </div>
                    <div class="card-body">
                        <form role="form" @submit.prevent="updateWoocommerceOrderSettings">
                            <div class="row">
                                <div class="col-md-12">
                                    <table class="table">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    Account
                                                </td>
                                                <td>
                                                    <div v-if="accounts">
                                                        <v-select v-model="form.account" :options="accounts" label="label"
                                                            :class="{ 'is-invalid': form.errors.has('account') }"
                                                            name="account"
                                                            :placeholder="$t('common.account_placeholder')" />
                                                        <has-error :form="form" field="account" />
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Tax Rates Mapping</h3>
                    </div>
                    <div class="card-body">
                        <form id="mapping-tax" @submit.prevent="submitMapping">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>POS Tax Rate</th>
                                        <th>Equivalent WooCommerce Tax Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(vatRate, index) in allVatRates" :key="vatRate.id">
                                        <td>{{ vatRate.code }}</td>
                                        <td>
                                            <v-select v-model="selectedVatRates[vatRate.id]"
                                                :options="allWoocommerceVatRates" label="name" name="vatRate"
                                                :placeholder="$t('setup.general_settings.index.default_elements.select_a_vat_rate')" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="submit" :class="{ 'btn-loading': taxMapLoading }"
                                class="btn btn-primary form-control">Submit</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4> Sync Product Categories</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6"><button id="sync-categories" type="button" @click="syncProductCategories"
                                    :class="{ 'btn-loading': loading }" class="btn btn-primary w-100"><i
                                        class="fa fa-handshake-o"></i> Sync Categories</button>
                            </div>
                            <div class="col-6"> <button id="reset-categories" type="button"
                                    @click="resetSyncProductCategories" :class="{ 'btn-loading': resetCategoryLoading }"
                                    class="btn btn-warning w-100"><i class="fa fa-undo"></i> Reset Synced
                                    Category</button></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Sync Products</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6"><button id="sync-products" type="button"
                                    :class="{ 'btn-loading': productLoading }" @click="syncProduct"
                                    class="btn btn-primary w-100"><i class="fa fa-handshake-o"></i>
                                    Sync Products</button>
                            </div>
                            <div class="col-6"> <button id="reset-products" type="button" @click="resetSyncedProducts"
                                    :class="{ 'btn-loading': resetLoading }" class="btn btn-warning w-100"><i
                                        class="fa fa-undo"></i> Reset Synced
                                    Product</button></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4>Sync Orders</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-12"><button id="sync-orders" type="button"
                                    :class="{ 'btn-loading': orderLoading }" @click="syncOrder"
                                    class="btn btn-primary w-100"><i class="fa fa-handshake-o"></i> Sync</button></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
  
<script>
import Form from "vform";
import { mapGetters } from "vuex";
import { loadMessages } from "~/plugins/i18n";
import axios from "axios";

export default {
    middleware: ["auth", "check-permissions"],
    metaInfo() {
        return { title: this.$t("sidebar.woocommerce") };
    },
    data: () => ({
        breadcrumbsCurrent: "sidebar.woocommerce",
        breadcrumbs: [
            {
                name: "setup.general_settings.index.breadcrumbs_first",
                url: "home",
            },
            {
                name: "sidebar.woocommerce",
                url: "",
            },
        ],
        allVatRates: [],
        allWoocommerceVatRates: [],
        selectedVatRates: {},
        form: new Form({
            account: null,
        }),
        loading: false,
        productLoading: false,
        orderLoading: false,
        resetLoading: false,
        taxMapLoading: false,
        resetCategoryLoading: false,
        isDemoMode: window.config.isDemoMode,
        accounts: "",
    }),
    computed: mapGetters({
        appInfo: "operations/appInfo",
        items: "operations/items",
    }),

    created() {
        this.getVatRates();
        this.getSelectedWoocommerceAccount();
        this.getAccounts();
        this.fetchWoocommerceVatRates();
    },

    methods: {

        // get selected account
        async getSelectedWoocommerceAccount() {
            const { data } = await axios.get(
                window.location.origin +
                '/api/woocommerce-order-setting-account'
            )
            this.form.account = data.data
        },

        // get accounts
        async getAccounts() {
            const { data } = await axios.get(
                window.location.origin + "/api/all-accounts"
            );
            this.accounts = data.data;
        },

        // update woocommerce order settings
        async updateWoocommerceOrderSettings() {
            await this.form.post(window.location.origin + "/api/update/woocommerce-order-settings")
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

        // sync products
        syncProduct() {
            this.productLoading = true;
            axios.get('api/sync-woocommerce-products').then((response) => {
                this.productLoading = false;
                toast.fire({
                    type: "success",
                    title: this.$t("woocommerce.products_sync_successfully"),
                });
            }).catch(() => {
                this.productLoading = false;
                toast.fire({
                    type: "error",
                    title: this.$t("common.error_msg"),
                });
            });
        },

        // reset synced products
        resetSyncedProducts() {
            this.resetLoading = true;
            axios.post('api/reset-sync-woocommerce-products').then((response) => {
                this.resetLoading = false;
                toast.fire({
                    type: "success",
                    title: this.$t("woocommerce.products_reset_successfully"),
                });
            }).catch(() => {
                this.resetLoading = false;
                toast.fire({
                    type: "error",
                    title: this.$t("common.error_msg"),
                });
            });
        },

        // sync order
        syncOrder() {
            this.orderLoading = true;
            axios.get('api/sync-woocommerce-orders').then((response) => {
                this.orderLoading = false;
                toast.fire({
                    type: "success",
                    title: this.$t("woocommerce.order_sync_successfully"),
                });
            }).catch(() => {
                this.orderLoading = false;
                toast.fire({
                    type: "error",
                    title: this.$t("common.error_msg"),
                });
            });
        },

        // sync product categories
        syncProductCategories() {
            this.loading = true;
            axios.get('api/sync-woocommerce-product-categories').then((response) => {
                this.loading = false;
                toast.fire({
                    type: "success",
                    title: this.$t("woocommerce.categories_sync_successfully"),
                });
            }).catch(() => {
                this.loading = false;
                toast.fire({
                    type: "error",
                    title: this.$t("common.error_msg"),
                });
            });
        },

        // reset product categories
        resetSyncProductCategories() {
            this.resetCategoryLoading = true;
            axios.post('api/reset-woocommerce-product-categories').then((response) => {
                this.resetCategoryLoading = false;
                toast.fire({
                    type: "success",
                    title: this.$t("woocommerce.categories_reset_successfully"),
                });
            }).catch(() => {
                this.resetCategoryLoading = false;
                toast.fire({
                    type: "error",
                    title: this.$t("common.error_msg"),
                });
            });
        },

        // get all vat rates
        getVatRates() {
            axios.get("/api/all-vat-rates").then((response) => {
                this.allVatRates = response.data.data;
            });
        },

        // get all woocommerce vat rates
        async fetchWoocommerceVatRates() {
            try {
                const response = await axios.get("api/woocommerce-all-vat-rates");
                const data = response.data;
                this.allWoocommerceVatRates = data;
            } catch (error) {
                console.error("Error fetching WooCommerce VAT rates:", error);
            }
        },

        submitMapping() {
            this.taxMapLoading = true;
            axios.post('api/woocommerce-map-vat-rates', this.selectedVatRates)
                .then(response => {
                    this.taxMapLoading = false;
                    toast.fire({
                    type: "success",
                    title: this.$t("woocommerce.tax_mapped_successfully"),
                });
                })
                .catch(error => {
                    this.taxMapLoading = false;
                    console.error(error);
                });
        }

    },
};
</script>
  