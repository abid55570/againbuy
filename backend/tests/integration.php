<?php
// Run with: docker compose --profile tools run --rm cli wp eval-file /tests/backend/integration.php
if (!class_exists('AgainBuy_Commerce_Core')) {
    throw new RuntimeException('AgainBuy plugin is inactive');
}
if (!get_role('againbuy_catalog_manager') || !get_role('againbuy_fulfilment_manager') || !get_role('againbuy_analyst')) {
    throw new RuntimeException('Staff roles are missing');
}
$analyst = get_role('againbuy_analyst');
if (!$analyst->has_cap('view_woocommerce_reports') || $analyst->has_cap('manage_woocommerce') || $analyst->has_cap('edit_products')) {
    throw new RuntimeException('Analytics role boundaries are wrong');
}
$analyst_user = get_users(['role' => 'againbuy_analyst', 'number' => 1])[0] ?? null;
if ($analyst_user) {
    wp_set_current_user($analyst_user->ID);
    if (AgainBuy_Commerce_Core::allow_report_admin(true)) { throw new RuntimeException('Analyst is blocked from the reports admin page'); }
    wp_set_current_user(0);
}
if (get_option('woocommerce_enable_myaccount_registration') !== 'yes') {
    throw new RuntimeException('Customer account registration is disabled');
}
if (get_option('woocommerce_enable_reviews') !== 'yes' || get_option('woocommerce_review_rating_verification_required') !== 'yes') {
    throw new RuntimeException('Verified-purchase review settings are wrong');
}
$catalog = get_role('againbuy_catalog_manager');
$fulfilment = get_role('againbuy_fulfilment_manager');
if (!$catalog->has_cap('edit_products') || $catalog->has_cap('edit_shop_orders') || $catalog->has_cap('manage_woocommerce') || !$fulfilment->has_cap('edit_shop_orders') || $fulfilment->has_cap('edit_products')) {
    throw new RuntimeException('Staff role boundaries are wrong');
}
$shop_manager = get_role('shop_manager');
if ($shop_manager->has_cap('manage_woocommerce') || !$shop_manager->has_cap('edit_shop_orders') || !$shop_manager->has_cap('edit_products') || !$shop_manager->has_cap('view_woocommerce_reports')) {
    throw new RuntimeException('Shop Manager capability boundaries are wrong');
}
if (!has_action('woocommerce_product_options_related', ['AgainBuy_Commerce_Core', 'related_field'])) {
    throw new RuntimeException('Related-product admin control is missing');
}
global $wpdb;
$audit_table = $wpdb->prefix . 'againbuy_audit';
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $audit_table)) !== $audit_table) {
    throw new RuntimeException('Durable audit table is missing');
}

$product = new WC_Product_Simple();
$product->set_name('Phase test bottle');
$product->set_status('publish');
$product->set_regular_price('1499');
$product->save();
try {
    $admin = get_user_by('login', 'admin');
    if (!$admin) { throw new RuntimeException('Local test admin is missing'); }
    wp_set_current_user($admin->ID);
    $product->update_meta_data('_againbuy_usd_price', '29.00');
    $product->save();
    $logged = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$audit_table} WHERE product_id = %d AND field = %s", $product->get_id(), '_againbuy_usd_price'));
    if (!$logged) { throw new RuntimeException('Price edit was not audited'); }
    if (AgainBuy_Commerce_Core::usd_price($product) !== 29.0) {
        throw new RuntimeException('USD price mismatch');
    }
    $_COOKIE['againbuy_test_country'] = 'US';
    if ((float) $product->get_price() !== 29.0 || get_woocommerce_currency() !== 'USD') {
        throw new RuntimeException('Displayed USD price or currency mismatch');
    }
    $product->update_meta_data('_againbuy_usd_sale_price', '25.00');
    $product->save();
    if ((float) $product->get_price() !== 25.0) {
        throw new RuntimeException('USD sale price must replace regular price');
    }
    $product->delete_meta_data('_againbuy_usd_sale_price');
    $product->save();
    $product->delete_meta_data('_againbuy_usd_price');
    $product->save();
    if (AgainBuy_Commerce_Core::usd_price($product) !== null) {
        throw new RuntimeException('Missing USD price must be unavailable');
    }
    if ($product->is_purchasable()) {
        throw new RuntimeException('Product without USD price must not be purchasable in USD');
    }
    if (!str_contains($product->get_price_html(), 'INR price') || !str_contains($product->get_price_html(), 'USD price unavailable')) {
        throw new RuntimeException('Missing USD price must show the known INR price and availability');
    }
    $related = new WC_Product_Simple();
    $related->set_name('Related test item');
    $related->set_status('publish');
    $related->set_regular_price('100');
    $related->save();
    try {
        $product->update_meta_data('_againbuy_related_ids', [$related->get_id()]);
        $product->save();
        if (AgainBuy_Commerce_Core::related_ids([], $product->get_id(), []) !== [$related->get_id()]) {
            throw new RuntimeException('Pinned related product is missing');
        }
        $related->set_stock_status('outofstock');
        $related->save();
        if (AgainBuy_Commerce_Core::related_ids([], $product->get_id(), []) !== []) {
            throw new RuntimeException('Out-of-stock related product was shown');
        }
    } finally {
        wp_delete_post($related->get_id(), true);
    }
    echo "Phase 2 integration checks passed.\n";
} finally {
    wp_delete_post($product->get_id(), true);
    wp_set_current_user(0);
    unset($_COOKIE['againbuy_test_country']);
}
