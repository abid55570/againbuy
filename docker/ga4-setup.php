<?php
$id = getenv('GA4_MEASUREMENT_ID') ?: '';
if (!preg_match('/^G-[A-Z0-9]+$/', $id)) { throw new RuntimeException('Invalid GA4 measurement ID'); }
$settings = get_option('woocommerce_google_analytics_settings', []);
$settings['ga_id'] = $id;
foreach (['ga_ecommerce_tracking_enabled', 'ga_event_tracking_enabled', 'ga_enhanced_remove_from_cart_enabled', 'ga_enhanced_product_impression_enabled', 'ga_enhanced_product_click_enabled', 'ga_enhanced_product_detail_view_enabled', 'ga_enhanced_checkout_process_enabled'] as $key) {
    $settings[$key] = 'yes';
}
update_option('woocommerce_google_analytics_settings', $settings);
echo "GA4 ecommerce tracking configured.\n";
