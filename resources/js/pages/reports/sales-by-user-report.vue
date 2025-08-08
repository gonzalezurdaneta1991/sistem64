<template>
  <div>
    <!-- breadcrumbs Start -->
    <breadcrumbs :items="breadcrumbs" :current="breadcrumbsCurrent" />
    <!-- breadcrumbs end -->

    <div class="row no-print">
      <div class="col-lg-12">
        <div class="card">
          <!-- form start -->
          <form role="form">
            <div class="card-body">
              <div class="row">
                <div v-if="users" class="form-group col-md-12">
                  <label for="user">{{ $t("common.user") }}
                    <span class="required">*</span></label>
                  <v-select v-model="form.user" :options="users" label="label"
                    :class="{ 'is-invalid': form.errors.has('user') }" name="user"
                    :placeholder="$t('common.select_user')" />
                  <has-error :form="form" field="user" />
                </div>
              </div>
              <div class="col-12">
                <template :class="w - 100">
                  <date-range-picker :from="form.fromDate" :to="form.toDate" :panel="$route.query.panel"
                    @update="update" />
                </template>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="row no-print mb-2">
      <div class="w-100 text-right float-right">
        <div class="btn-group" v-if="items && items.length > 0">
          <a :href="exportUrl" v-tooltip="$t('common.export_excel')" class="btn btn-info">
            <i class="fa fa-arrow-circle-down"></i>
          </a>
          <a @click="printWindow()" href="#" class="btn btn-secondary">
            <i class="fas fa-print"></i> {{ $t("common.print") }}
          </a>
          <router-link :to="{ name: 'home' }" class="btn btn-dark float-right">
            <i class="fas fa-long-arrow-alt-left" /> {{ $t("common.back") }}
          </router-link>
        </div>
      </div>
    </div>

    <div v-if="items && items.length > 0" class="row">
      <div class="invoice p-3 mb-3 w-100" id="content-to-pdf">
        <div class="row mt-5 position-relative">
          <table-loading v-show="loading" />
          <div class="table-responsive table-custom">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>{{ $t("common.s_no") }}</th>
                  <th>{{ $t("common.sales_by_name") }}</th>
                  <th>{{ $t("common.invoice_no") }}</th>
                  <th>{{ $t("common.client") }}</th>
                  <th>{{ $t("common.net_total") }}</th>
                  <th>{{ $t("common.invoice_date") }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(data, i) in items" :key="i">
                  <td>{{ ++i }}</td>
                  <td>{{ data.salesBy }}</td>
                  <td>
                    <router-link v-if="$can('invoice-view')" :to="{
                      name: 'invoices.show',
                      params: { slug: data.slug },
                    }">
                      {{ data.invoiceLabel }}
                    </router-link>
                  </td>
                  <td>{{ data.client }}</td>
                  <td>{{ data.invoiceTotal | withCurrency }}</td>
                  <td>{{ data.invoiceDate | moment("Do MMM, YYYY") }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="items && items.length <= 0" class="row">
      <div class="col-lg-12 col-xl-10 offset-xl-1">
        <EmptyTable />
      </div>
    </div>
  </div>
</template>

<script>
import Form from "vform";
import axios from "axios";
import { mapGetters } from "vuex";
import "vue-mj-daterangepicker/dist/vue-mj-daterangepicker.css";

export default {
  middleware: ["auth", "check-permissions"],
  metaInfo() {
    return { title: this.$t("reports.sales_by_user_report") };
  },
  data: () => ({
    breadcrumbsCurrent: "reports.sales_by_user_report",
    breadcrumbs: [
      {
        name: "employees.list.index.breadcrumbs_first",
        url: "home",
      },
      {
        name: "reports.sales_by_user_report",
        url: "",
      },
    ],
    form: new Form({
      fromDate: String(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)),
      toDate: String(new Date()),
      user: "",
    }),
    users: '',
    items: '',
    loading: false,
    date: new Date(),
  }),

  computed: {
    ...mapGetters("operations", ["appInfo"]),
    exportUrl() {
      // Create a dynamic export URL with query parameters
      return `/sales-by-user-report/export/excel?start_date=${this.form.fromDate}&end_date=${this.form.toDate}&term=${this.form.user['id']}`;
    },
  },

  created() {
    this.getUser();
  },
  methods: {
    // get users
    async getUser() {
      const { data } = await axios.get(
        window.location.origin + '/api/all-user'
      )
      const usersFromApi = data.data;
      const allUsers = { id: 0, name: "All Users", label: "All Users" };
      this.users = [allUsers, ...usersFromApi];
    },

    // get filtered data
    async update(values) {
      this.loading = true;
      this.form.fromDate = values.from;
      this.form.toDate = values.to;
      await this.form
        .post(window.location.origin + "/api/reports/sales-by-user-report")
        .then((response) => {
          this.items = response.data.data;
          this.loading = false;
        })
        .catch(() => {
          toast.fire({ type: "error", title: this.$t("common.delete_failed") });
        });
    },

    // print
    printWindow() {
      window.print();
    },
  },
};
</script>

