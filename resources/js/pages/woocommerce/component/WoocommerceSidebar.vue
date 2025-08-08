<template>
    <div class="card settings-card no-print">
        <div class="card-body">
            <ul class="nav flex-column nav-pills m-1">
                <li class="nav-item">
                    <router-link :to="{ name: 'woocommerce.settings' }" class="nav-link thumb">
                        {{ $t("woocommerce.instructions") }}
                    </router-link>
                </li>
                <li class="nav-item">
                    <router-link :to="{ name: 'woocommerce.apiSettings' }" class="nav-link thumb">
                        {{ $t("woocommerce.api_settings") }}
                    </router-link>
                </li>
                <li class="nav-item">
                    <router-link :to="{ name: 'woocommerce.productSyncSettings' }" class="nav-link thumb">
                        {{ $t("woocommerce.product_sync_settings") }}
                    </router-link>
                </li>
                <li class="nav-item">
                    <router-link :to="{ name: 'woocommerce.orderSyncSettings' }" class="nav-link thumb">
                        {{ $t("woocommerce.order_sync_settings") }}
                    </router-link>
                </li>
            </ul>
        </div>
    </div>
</template>
  
  
<script>
export default {
    name: "WoocommerceSidebar",
};
</script>
  