<?php
if (get_option('woocommerce_feature_rate_limit_checkout_enabled') !== 'yes') {
    throw new RuntimeException('Checkout rate limiting is disabled');
}
$zones = array_map(static fn($zone) => $zone['zone_name'], WC_Shipping_Zones::get_zones());
if (!in_array('India', $zones, true) || !in_array('United States', $zones, true)) {
    throw new RuntimeException('India and US shipping zones are missing');
}
foreach (['India' => 'IN', 'United States' => 'US'] as $name => $country) {
    $zone = array_values(array_filter(WC_Shipping_Zones::get_zones(), static fn($item) => $item['zone_name'] === $name))[0];
    $locations = (new WC_Shipping_Zone($zone['zone_id']))->get_zone_locations();
    if (count($locations) !== 1 || $locations[0]->type !== 'country' || $locations[0]->code !== $country) {
        throw new RuntimeException('Incorrect shipping region: ' . $name);
    }
}
$india_zone = array_values(array_filter(WC_Shipping_Zones::get_zones(), static fn($item) => $item['zone_name'] === 'India'))[0];
$india_methods = (new WC_Shipping_Zone($india_zone['zone_id']))->get_shipping_methods(true);
$india_rate = array_values(array_filter($india_methods, static fn($method) => $method->id === 'flat_rate' && $method->get_option('title') === 'India delivery'))[0] ?? null;
if (!$india_rate || $india_rate->enabled !== 'yes' || (float) $india_rate->get_option('cost') !== 120.0) {
    throw new RuntimeException('India delivery must be a ₹120 flat rate');
}
$india_match = WC_Shipping_Zones::get_zone_matching_package(['destination' => ['country' => 'IN', 'state' => '', 'postcode' => '110001']]);
if ($india_match->get_id() !== (int) $india_zone['zone_id']) { throw new RuntimeException('Indian checkout does not match the India delivery zone'); }
if ((getenv('INTERNATIONAL_DELIVERY_CHARGE_USD') ?: '') === '') {
    $us_zone = array_values(array_filter(WC_Shipping_Zones::get_zones(), static fn($item) => $item['zone_name'] === 'United States'))[0];
    foreach ([(new WC_Shipping_Zone($us_zone['zone_id'])), (new WC_Shipping_Zone(0))] as $zone) {
        foreach ($zone->get_shipping_methods(true) as $method) {
            if ($method->get_option('title') === 'International delivery' && $method->enabled === 'yes') {
                throw new RuntimeException('International delivery must stay unavailable without its environment charge');
            }
        }
    }
}
if (!file_exists(WP_PLUGIN_DIR . '/woo-razorpay/woo-razorpay.php')) {
    throw new RuntimeException('Official Razorpay plugin is not installed');
}
if (!getenv('RAZORPAY_KEY_ID') && in_array('woo-razorpay/woo-razorpay.php', get_option('active_plugins', []), true)) {
    throw new RuntimeException('Payment gateway must stay inactive without keys');
}
$configured = (bool) (getenv('RAZORPAY_KEY_ID') && getenv('RAZORPAY_KEY_SECRET') && getenv('RAZORPAY_WEBHOOK_SECRET'));
$razorpay_enabled = false;
foreach (WC()->payment_gateways()->payment_gateways() as $gateway) {
    if ($gateway->id === 'razorpay') { $razorpay_enabled = $gateway->enabled === 'yes'; }
    elseif ($gateway->enabled === 'yes') { throw new RuntimeException('Unexpected active payment method: ' . $gateway->id); }
}
if ($razorpay_enabled !== $configured) { throw new RuntimeException('Razorpay test gateway state does not match configuration'); }
putenv('RAZORPAY_KEY_ID=rzp_test_fixture');
putenv('RAZORPAY_KEY_SECRET=not-a-real-secret');
putenv('RAZORPAY_WEBHOOK_SECRET=not-a-real-webhook');
putenv('RAZORPAY_MODE=test');
$settings = apply_filters('option_woocommerce_razorpay_settings', ['enabled' => 'yes']);
if (($settings['key_id'] ?? '') !== 'rzp_test_fixture' || ($settings['webhook_secret'] ?? '') !== 'not-a-real-webhook') {
    throw new RuntimeException('Test payment settings did not read from runtime configuration');
}
putenv('RAZORPAY_KEY_ID=rzp_live_testfixture');
$settings = apply_filters('option_woocommerce_razorpay_settings', ['enabled' => 'yes']);
if (($settings['enabled'] ?? '') !== 'no') {
    throw new RuntimeException('Live payment key must fail closed in test mode');
}
putenv('RAZORPAY_MODE=live');
$settings = apply_filters('option_woocommerce_razorpay_settings', ['enabled' => 'yes']);
if (($settings['enabled'] ?? '') !== 'no') {
    throw new RuntimeException('Live mode must fail closed outside production');
}
putenv('RAZORPAY_KEY_ID');
putenv('RAZORPAY_KEY_SECRET');
putenv('RAZORPAY_WEBHOOK_SECRET');
putenv('RAZORPAY_MODE=test');
echo "Phase 4 configuration checks passed.\n";
