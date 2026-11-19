<?php
$id = getenv('GA4_MEASUREMENT_ID') ?: '';
$settings = get_option('woocommerce_google_analytics_settings', []);
if (!is_plugin_active('woocommerce-google-analytics-integration/woocommerce-google-analytics-integration.php') || ($settings['ga_id'] ?? '') !== $id) {
    throw new RuntimeException('GA4 tracking is not configured');
}
foreach (['ga_ecommerce_tracking_enabled', 'ga_event_tracking_enabled', 'ga_enhanced_remove_from_cart_enabled', 'ga_enhanced_product_impression_enabled', 'ga_enhanced_product_click_enabled', 'ga_enhanced_product_detail_view_enabled', 'ga_enhanced_checkout_process_enabled'] as $key) {
    if (($settings[$key] ?? '') !== 'yes') { throw new RuntimeException('GA4 event disabled: ' . $key); }
}
echo "GA4 event settings passed.\n";
$original_user = get_current_user_id();
try {
    wp_set_current_user(0);
    if (apply_filters('woocommerce_ga_disable_tracking', false, 'pageview')) { throw new RuntimeException('Visitor tracking was disabled'); }
    if (!apply_filters('woocommerce_ga_disable_tracking', true, 'pageview')) { throw new RuntimeException('Existing tracking opt-out was overridden'); }
    foreach (['administrator', 'shop_manager', 'againbuy_catalog_manager', 'againbuy_fulfilment_manager', 'againbuy_analyst', 'customer'] as $role) {
        $user = new WP_User(0);
        $user->allcaps = (get_role($role))->capabilities;
        $GLOBALS['current_user'] = $user;
        $excluded = apply_filters('woocommerce_ga_disable_tracking', false, 'pageview');
        if ($excluded !== ($role !== 'customer')) { throw new RuntimeException('Incorrect GA4 exclusion for ' . $role); }
    }
} finally {
    unset($GLOBALS['current_user']);
    wp_set_current_user($original_user);
}
echo "GA4 visitor/customer tracking and staff exclusions passed.\n";
