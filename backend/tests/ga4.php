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
