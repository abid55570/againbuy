<?php
update_option('woocommerce_feature_rate_limit_checkout_enabled', 'yes');
update_option('woocommerce_default_country', 'IN');
update_option('woocommerce_enable_guest_checkout', 'no');
update_option('woocommerce_enable_signup_and_login_from_checkout', 'yes');
update_option('woocommerce_enable_checkout_login_reminder', 'yes');
foreach (['India' => 'IN', 'United States' => 'US'] as $name => $country) {
    $existing_id = 0;
    foreach (WC_Shipping_Zones::get_zones() as $zone) {
        if ($zone['zone_name'] === $name) { $existing_id = (int) $zone['zone_id']; break; }
    }
    $zone = $existing_id ? new WC_Shipping_Zone($existing_id) : new WC_Shipping_Zone();
    if (!$existing_id) { $zone->set_zone_name($name); $zone->save(); }
    if (!$zone->get_zone_locations()) { $zone->add_location($country, 'country'); $zone->save(); }
}

function againbuy_shipping_rate(WC_Shipping_Zone $zone, string $title, string $cost): void {
    $method = null;
    foreach ($zone->get_shipping_methods(true) as $candidate) {
        if ($candidate->id === 'flat_rate' && $candidate->get_option('title') === $title) { $method = $candidate; break; }
    }
    if ($cost === '' || !is_numeric($cost) || (float) $cost < 0) {
        if ($method) { update_option('woocommerce_flat_rate_' . $method->get_instance_id() . '_settings', ['title' => $title, 'tax_status' => 'none', 'cost' => '', 'enabled' => 'no']); }
        return;
    }
    $instance_id = $method ? $method->get_instance_id() : $zone->add_shipping_method('flat_rate');
    update_option('woocommerce_flat_rate_' . $instance_id . '_settings', ['title' => $title, 'tax_status' => 'none', 'cost' => wc_format_decimal($cost), 'enabled' => 'yes']);
}

$zones = WC_Shipping_Zones::get_zones();
$india = array_values(array_filter($zones, static fn($item) => $item['zone_name'] === 'India'))[0];
$us = array_values(array_filter($zones, static fn($item) => $item['zone_name'] === 'United States'))[0];
againbuy_shipping_rate(new WC_Shipping_Zone($india['zone_id']), 'India delivery', getenv('INDIA_DELIVERY_CHARGE_INR') ?: '120');
$international = getenv('INTERNATIONAL_DELIVERY_CHARGE_USD') ?: '';
againbuy_shipping_rate(new WC_Shipping_Zone($us['zone_id']), 'International delivery', $international);
againbuy_shipping_rate(new WC_Shipping_Zone(0), 'International delivery', $international);
if (getenv('RAZORPAY_KEY_ID') && getenv('RAZORPAY_KEY_SECRET') && getenv('RAZORPAY_WEBHOOK_SECRET')) {
    update_option('woocommerce_razorpay_settings', [
        'enabled' => 'yes', 'title' => 'Secure payment',
        'description' => 'Pay securely with Razorpay.', 'payment_action' => 'capture',
    ]);
}
echo "Checkout safeguards, shipping zones and delivery rates configured.\n";
