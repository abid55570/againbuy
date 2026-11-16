<?php
if (isset(rest_get_server()->get_routes()['/againbuy/v1/reverse-geocode'])) {
    throw new RuntimeException('Location route is active while the feature is disabled');
}
putenv('LOCATION_AUTOFILL_ENABLED=true');
putenv('GOOGLE_GEOCODING_API_KEY=test');
require_once WP_PLUGIN_DIR . '/againbuy-commerce-core/location.php';
AgainBuy_Location::boot();
$request = new WP_REST_Request('POST', '/againbuy/v1/reverse-geocode');
$request->set_header('Content-Type', 'application/json');
$request->set_body(wp_json_encode(['latitude' => 37.42255, 'longitude' => -122.08463]));
$denied = rest_do_request($request);
if ($denied->get_status() !== 401) { throw new RuntimeException('Location route requires a nonce'); }

$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce('wp_rest');
$invalid = new WP_REST_Request('POST', '/againbuy/v1/reverse-geocode');
$invalid->set_header('Content-Type', 'application/json');
$invalid->set_body(wp_json_encode(['latitude' => 999, 'longitude' => 0]));
if (rest_do_request($invalid)->get_status() !== 400) { throw new RuntimeException('Invalid coordinates were accepted'); }

$mock = static function ($pre, $args, $url) {
    if (!str_starts_with($url, 'https://geocode.googleapis.com/v4/geocode/location')) { return $pre; }
    return ['response' => ['code' => 200, 'message' => 'OK'], 'headers' => [], 'body' => wp_json_encode(['results' => [[
        'types' => ['street_address'],
        'addressComponents' => [
            ['types' => ['street_number'], 'longText' => '1600', 'shortText' => '1600'],
            ['types' => ['route'], 'longText' => 'Amphitheatre Parkway', 'shortText' => 'Amphitheatre Pkwy'],
            ['types' => ['locality'], 'longText' => 'Mountain View', 'shortText' => 'Mountain View'],
            ['types' => ['administrative_area_level_1'], 'longText' => 'California', 'shortText' => 'CA'],
            ['types' => ['country'], 'longText' => 'United States', 'shortText' => 'US'],
            ['types' => ['postal_code'], 'longText' => '94043', 'shortText' => '94043'],
        ],
    ]]])];
};
add_filter('pre_http_request', $mock, 10, 3);
try {
    $response = rest_do_request($request);
    $address = $response->get_data();
    if ($response->get_status() !== 200 || $address['country'] !== 'US' || $address['state'] !== 'CA' || $address['city'] !== 'Mountain View' || $address['address_1'] !== '1600 Amphitheatre Parkway' || $address['approximate']) {
        throw new RuntimeException('Location fields were not mapped correctly');
    }
    for ($attempt = 0; $attempt < 4; $attempt++) { rest_do_request($request); }
    if (rest_do_request($request)->get_status() !== 429) { throw new RuntimeException('Location lookup limit did not apply'); }
    echo "Checkout location endpoint checks passed.\n";
} finally {
    remove_filter('pre_http_request', $mock, 10);
    delete_transient('againbuy_location_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
    putenv('GOOGLE_GEOCODING_API_KEY');
    putenv('LOCATION_AUTOFILL_ENABLED');
    unset($_SERVER['HTTP_X_WP_NONCE']);
}
