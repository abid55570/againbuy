<?php
$original_customer = WC()->customer;
$original_remote = $_SERVER['REMOTE_ADDR'] ?? null;
$original_forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
$original_proxy = getenv('TRUSTED_PROXY_CIDRS');
$original_app = getenv('APP_ENV');
$customer = new WC_Customer();
$customer->set_shipping_country('');
$customer->set_billing_country('');
WC()->customer = $customer;
$seen_ip = '';
$lookup = static function ($country, $ip) use (&$seen_ip) { $seen_ip = $ip; return $ip === '198.51.100.25' ? 'US' : 'IN'; };
add_filter('woocommerce_geolocate_ip', $lookup, 10, 2);
try {
    putenv('APP_ENV=production');
    putenv('TRUSTED_PROXY_CIDRS');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.20';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.25';
    $_COOKIE['againbuy_test_country'] = 'US';
    if (AgainBuy_Commerce_Core::selected_currency() !== 'INR' || $seen_ip !== '203.0.113.20') {
        throw new RuntimeException('Untrusted proxy or test cookie changed production currency');
    }
    putenv('TRUSTED_PROXY_CIDRS=203.0.113.0/24');
    if (AgainBuy_Commerce_Core::selected_currency() !== 'USD' || $seen_ip !== '198.51.100.25') {
        throw new RuntimeException('Trusted proxy visitor country was not used');
    }
    $customer->set_shipping_country('IN');
    if (AgainBuy_Commerce_Core::selected_currency() !== 'INR') {
        throw new RuntimeException('Selected delivery country did not determine currency');
    }
    echo "Country pricing and proxy trust checks passed.\n";
} finally {
    remove_filter('woocommerce_geolocate_ip', $lookup, 10);
    WC()->customer = $original_customer;
    if ($original_remote === null) { unset($_SERVER['REMOTE_ADDR']); } else { $_SERVER['REMOTE_ADDR'] = $original_remote; }
    if ($original_forwarded === null) { unset($_SERVER['HTTP_X_FORWARDED_FOR']); } else { $_SERVER['HTTP_X_FORWARDED_FOR'] = $original_forwarded; }
    putenv($original_proxy === false ? 'TRUSTED_PROXY_CIDRS' : 'TRUSTED_PROXY_CIDRS=' . $original_proxy);
    putenv($original_app === false ? 'APP_ENV' : 'APP_ENV=' . $original_app);
    unset($_COOKIE['againbuy_test_country']);
}
