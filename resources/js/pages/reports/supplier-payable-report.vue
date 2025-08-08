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
                {{ $t("suppliers.report.breadcrumbs_current") }}
              </h3>
            </div>
            <div class="col-xl-8 col-8 float-right text-right">
              <div class="btn-group c-w-100">
                <a @click="refreshTable()" href="#" v-tooltip="'Refresh'" class="btn btn-success">
                  <i class="fas fa-sync"></i>
                </a>
                <a :href="exportUrl" v-tooltip="$t('common.export_excel')" class="btn btn-info">
                  <i class="fa fa-arrow-circle-down"></i>
                </a>
                <a href="/supplier-payable-report/pdf" v-tooltip="$t('common.export_pdf')" class="btn btn-secondary">
                  <i class="fas fa-file-export"></i>
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
            </div>
            <table-loading v-show="loading" />
            <div class="table-responsive table-custom mt-3" id="printMe">
              <table class="table">
                <thead>
                  <tr>
                    <th>{{ $t("common.s_no") }}</th>
                    <th>{{ $t("common.supplier_id") }}</th>
                    <th>{{ $t("common.name") }}</th>
                    <th>{{ $t("common.contact_number") }}</th>
                    <th>{{ $t("common.email") }}</th>
                    <th>{{ $t("common.company_name") }}</th>
                    <th>{{ $t("purchases.list.common.purchase_due") }}</th>
                    <th>{{ $t("payments.common.non_purchase_due") }}</th>
                    <th>{{ $t("common.total_due") }}</th>
                    <th>{{ $t("common.status") }}</th>
                  </tr>
                </thead>
                <tbody>

                  <tr v-show="items.length" v-for="(data, i) in items" :key="i">
                    <td>
                      <span v-if="pagination && pagination.current_page > 1">
                        {{
                          pagination.per_page * (pagination.current_page - 1) +
                          (i + 1)
                        }}
                      </span>
                      <span v-else>{{ i + 1 }}</span>
                    </td>
                    <td>{{ data.supplierID | withPrefix(supplierPrefix) }}</td>
                    <td>
                      <router-link v-if="$can('client-view')" :to="{
                        name: 'suppliers.show',
                        params: { slug: data.slug },
                      }">
                        {{ data.name }}
                      </router-link>
                      <span v-else>{{ data.name }}</span>
                    </td>
                    <td>{{ data.phoneNumber }}</td>
                    <td>{{ data.email }}</td>
                    <td>{{ data.companyName }}</td>
                    <td>{{ data.purchaseTotalDue | withCurrency }}</td>
                    <td>{{ data.nonPurchaseCurrentDue | withCurrency }}</td>
                    <td>{{ data.purchaseTotalDue + data.nonPurchaseCurrentDue | withCurrency }}</td>
                    <td>
                      <span v-if="data.status === 1" class="badge bg-success">{{
                        $t("common.active")
                      }}</span>
                      <span v-else class="badge bg-danger">{{
                        $t("common.in_active")
                      }}</span>
                    </td>
                  </tr>
                  <tr v-show="!loading && !items.length">
                    <td colspan="9">
                      <EmptyTable />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <!-- /.card-body -->
          <div class="card-footer">
            <div class="dtable-footer">
              <div class="form-group row display-per-page">
                <label>{{ $t("per_page") }} </label>
                <div>
                  <select @change="updatePerPager" v-model="perPage" class="form-control form-control-sm ml-1">
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
import { mapGetters } from "vuex";

export default {
  middleware: ["auth", "check-permissions"],
  metaInfo() {
    return { title: this.$t("suppliers.report.breadcrumbs_current") };
  },
  data: () => ({

    breadcrumbsCurrent: "suppliers.report.breadcrumbs_current",
    breadcrumbs: [
      {
        name: "suppliers.index.breadcrumbs_first",
        url: "home",
      },
      {
        name: "suppliers.report.breadcrumbs_current",
        url: "",
      },
    ],
    query: "",
    perPage: 10,
    supplierPrefix: "",
  }),
  // Map Getters
  computed: {
    ...mapGetters("operations", ["items", "loading", "pagination", "appInfo"]),
    exportUrl() {
      // Create a dynamic export URL with query parameters
      return `/supplier-payable-report/export/excel?term=${this.query}`;
    },
  },
  watch: {
    // watch search data
    query: function (newQ) {
      if (newQ === "") {
        this.getData();
      } else {
        this.searchData();
      }
    },
  },
  created() {
    this.getData();
    this.supplierPrefix = this.appInfo.supplierPrefix;
  },
  methods: {

    // refresh table
    refreshTable() {
      this.query = "";
      this.query === "" ? this.getData() : this.searchData();
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
        path: "/api/reports/supplier-due-report?page=",
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
        path: "/api/suppliers/search",
        term: this.query,
        currentPage: currentPage + "&perPage=" + this.perPage,
      });
    },

    // reload after search
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

