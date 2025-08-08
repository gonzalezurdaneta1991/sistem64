<template>
    <div class="mb-50">
        <!-- breadcrumbs Start -->
        <breadcrumbs :items="breadcrumbs" :current="breadcrumbsCurrent" />
        <!-- breadcrumbs end -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card custom-card w-100">
                    <div class="card-header setings-header">
                        <div class="col-xl-4 col-4">
                            <h3 class="card-title">
                                {{ $t("sidebar.sync_log") }}
                            </h3>
                        </div>
                        <div class="col-xl-8 col-8 float-right text-right">
                            <div class="btn-group c-w-100">
                                <a @click="refreshTable()" href="#" v-tooltip="'Refresh'" class="btn btn-success">
                                    <i class="fas fa-sync"></i>
                                </a>
                                <a @click="print" v-tooltip="$t('common.print_table')" class="btn btn-info">
                                    <i class="fas fa-print"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body position-relative">
                        <div class="row">
                            <div class="col-6 col-xl-4 mb-2">
                                <search v-model="query" @reset-pagination="resetPagination()" @reload="reload" />
                            </div>
                            <div class="col-6 col-xl-8 mb-2 text-right">
                                <date-range-picker ref="picker" opens="left" :locale-data="locale" :minDate="minDate"
                                    :maxDate="maxDate" :singleDatePicker="false" :showWeekNumbers="false"
                                    :showDropdowns="true" :autoApply="true" v-model="dateRange" @update="updateValues"
                                    :linkedCalendars="true" class="c-w-100">
                                    <template v-slot:input="picker" style="min-width: 350px">
                                        {{ picker.startDate | startDate }} -
                                        {{ picker.endDate | endDate }}
                                    </template>
                                </date-range-picker>
                            </div>
                        </div>
                        <table-loading v-show="loading" />
                        <div class="table-responsive table-custom mt-3" id="printMe">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <td width="60"></td>
                                        <th>{{ $t("common.date") }}</th>
                                        <th>{{ $t("woocommerce.sync_type") }}</th>
                                        <th>{{ $t("woocommerce.operation") }}</th>
                                        <th>{{ $t("woocommerce.records") }}</th>
                                        <th>{{ $t("woocommerce.synced_by") }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="items.length">
                                        <tr v-for="(data, i) in items" :key="i">
                                            <td v-show="data.details" @click="toggleDetails(i)" class="text-center">
                                                <span v-show="selectedRow === i"
                                                    class="toggle-icon fas fa-arrow-circle-down"></span>
                                                <span v-show="selectedRow !== i"
                                                    class="toggle-icon fas fa-arrow-circle-right"></span>
                                            </td>
                                            <td v-show="!data.details"></td>

                                            <td>{{ data.createdAt | moment("Do MMM, YYYY") }}</td>
                                            <td>{{ data.syncType }}</td>
                                            <td>{{ data.operationType }}</td>
                                            <td>{{ data.data }}</td>
                                            <td>{{ data.createdBy }}</td>
                                        </tr>
                                        <template v-if="selectedRow !== null">
                                            <td :colspan="6">
                                                <div class="card ml-5">
                                                    <div class="card-body bg-gray">
                                                        <template
                                                            v-for="(error, index) in parseErrorDetails(items[selectedRow].details)">
                                                            <div class="card-text" v-if="index > 0"></div>
                                                            <div class="card-text">{{ error }}</div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </td>
                                        </template>
                                    </template>
                                    <tr v-else>
                                        <td colspan="6">
                                            <EmptyTable />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="dtable-footer">
                            <div class="form-group row display-per-page">
                                <label>{{ $t("per_page") }} </label>
                                <div>
                                    <select @change="updatePerPager" v-model="perPage"
                                        class="form-control form-control-sm ml-1">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                            </div>
                            <!-- pagination-start -->
                            <pagination v-if="pagination && pagination.last_page > 1" :pagination="pagination" :offset="5"
                                class="justify-flex-end" @paginate="paginate" />
                            <!-- pagination-end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
  
<script>
import moment from "moment";
import { mapGetters } from "vuex";
import i18n from "~/plugins/i18n";
import DateRangePicker from "vue2-daterange-picker";

export default {
    middleware: ["auth", "check-permissions"],
    metaInfo() {
        return { title: this.$t("sidebar.sync_log") };
    },
    components: {
        DateRangePicker,
    },
    data: () => ({
        selectedRow: null,
        breadcrumbsCurrent: "sidebar.woocommerce",
        breadcrumbs: [
            {
                name: "payroll.index.breadcrumbs_first",
                url: "home",
            },
            {
                name: "sidebar.sync_log",
                url: "",
            },
        ],
        query: "",
        perPage: 10,
        minDate: moment(new Date("01-01-2021")).format("YYYY-MM-DD"),
        maxDate: moment().add(1, "days").format("YYYY-MM-DD"),
        dateRange: {
            startDate: "",
            endDate: "",
        },
        locale: {
            direction: "ltr",
            format: "YYYY-MM-DD",
            separator: " - ",
            applyLabel: "Apply",
            cancelLabel: "Cancel",
            weekLabel: "W",
            customRangeLabel: "Custom Range",
            daysOfWeek: moment.weekdaysMin(),
            monthNames: moment.monthsShort(),
            firstDay: 1,
        },
    }),
    filters: {
        startDate(val) {
            return val ? moment(val).format("YYYY-MM-DD") : i18n.t("common.from");
        },
        endDate(val) {
            return val ? moment(val).format("YYYY-MM-DD") : i18n.t("common.to");
        },
    },
    // Map Getters
    computed: {
        ...mapGetters("operations", ["items", "loading", "pagination", "appInfo"]),
    },
    watch: {
        // watch search data
        query: function (newQ, oldQ) {
            if (newQ === "") {
                if (this.dateRange.startDate && this.dateRange.endDate) {
                    this.searchData();
                } else {
                    this.getData();
                }
            } else {
                this.searchData();
            }
        },
    },
    created() {
        this.getData();
    },
    methods: {
        parseErrorDetails(details) {
            const errorDetails = JSON.parse(details);
            const formattedErrors = [];
            errorDetails.forEach(error => {
                let errorMessage = `ERROR: SKIPPED: Order #${error.has_error.order_number}`;
                if (error.has_error.products && error.has_error.products.length > 0) {
                    errorMessage += ` because product ${error.has_error.products.join(", ")} not found`;
                }
                formattedErrors.push(errorMessage);
            });
            return formattedErrors;
        },

        toggleDetails(index) {
            if (this.selectedRow === index) {
                this.selectedRow = null;
            } else {
                this.selectedRow = index;
            }
        },
        // filter data for selected date range
        async updateValues() {
            this.dateRange.startDate = moment(this.dateRange.startDate).format(
                "YYYY-MM-DD"
            );
            this.dateRange.endDate = moment(this.dateRange.endDate).format(
                "YYYY-MM-DD"
            );
            this.searchData();
        },
        // refresh table
        refreshTable() {
            this.query = "";
            this.dateRange.startDate = null;
            this.dateRange.endDate = null;

            this.query === "" ? this.getData() : this.searchData();

            setTimeout(
                function () {
                    this.dateRange.startDate = "";
                    this.dateRange.endDate = "";
                }.bind(this),
                500
            );
        },
        // update per page count
        updatePerPager() {
            this.pagination.current_page = 1;
            this.query === "" ? this.getData() : this.searchData();
        },
        // get data
        async getData() {
            this.$store.state.operations.loading = true;
            let currentPage = this.pagination ? this.pagination.current_page : 1;
            await this.$store.dispatch("operations/fetchData", {
                path: "/api/woocommerce-sync-logs?page=",
                currentPage: currentPage + "&perPage=" + this.perPage,
            });
        },

        // Pagination
        async paginate() {
            this.query === "" ? this.getData() : this.searchData();
        },

        // Reset pagination
        async resetPagination() {
            this.pagination.current_page = 1;
        },

        // search data
        async searchData() {
            this.$store.state.operations.loading = true;
            let currentPage = this.pagination ? this.pagination.current_page : 1;
            await this.$store.dispatch("operations/searchData", {
                path: "/api/woocommerce-sync-logs/search",
                term: this.query,
                currentPage: currentPage + "&perPage=" + this.perPage,
                startDate: this.dateRange.startDate,
                endDate: this.dateRange.endDate,
            });
        },

        // Reload after search
        async reload() {
            this.query = "";
            await this.searchData();
        },

        // print table
        async print() {
            await this.$htmlToPaper("printMe");
        },
    },
};
</script>
  