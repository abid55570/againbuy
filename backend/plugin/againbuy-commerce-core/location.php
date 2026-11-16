<?php
if (!defined('ABSPATH')) { exit; }

final class AgainBuy_Location {
    public static function boot(): void {
        if (did_action('rest_api_init')) { self::register_route(); }
        else { add_action('rest_api_init', [__CLASS__, 'register_route']); }
    }

    public static function register_route(): void {
        register_rest_route('againbuy/v1', '/reverse-geocode', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'reverse_geocode'],
            'permission_callback' => static fn() => wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest') !== false,
        ]);
    }

    public static function reverse_geocode(WP_REST_Request $request) {
        $body = $request->get_json_params();
        $lat = $body['latitude'] ?? null;
        $lng = $body['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return new WP_Error('invalid_location', 'Invalid location.', ['status' => 400]);
        }
        $key = getenv('GOOGLE_GEOCODING_API_KEY') ?: '';
        if (!$key) { return new WP_Error('location_unavailable', 'Address lookup is unavailable. Please enter your address.', ['status' => 503]); }
        $ip = AgainBuy_Commerce_Core::visitor_ip();
        $limit_key = 'againbuy_location_' . md5($ip);
        $attempts = (int) get_transient($limit_key);
        if ($attempts >= 5) { return new WP_Error('location_limit', 'Please try again later or enter your address.', ['status' => 429]); }
        set_transient($limit_key, $attempts + 1, HOUR_IN_SECONDS);

        $url = add_query_arg(['location.latitude' => round((float) $lat, 6), 'location.longitude' => round((float) $lng, 6)], 'https://geocode.googleapis.com/v4/geocode/location');
        $response = wp_remote_get($url, [
            'timeout' => 8,
            'redirection' => 0,
            'headers' => ['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => 'results.addressComponents,results.types'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('location_unavailable', 'Address lookup failed. Please enter your address.', ['status' => 502]);
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $results = $data['results'] ?? [];
        $result = null;
        foreach ($results as $candidate) {
            if (in_array('street_address', $candidate['types'] ?? [], true)) { $result = $candidate; break; }
        }
        $result ??= $results[0] ?? null;
        if (!$result) { return new WP_Error('location_unknown', 'No address was found. Please enter your address.', ['status' => 404]); }
        $parts = [];
        foreach ($result['addressComponents'] ?? [] as $component) {
            foreach ($component['types'] ?? [] as $type) { $parts[$type] = $component; }
        }
        $long = static fn($type) => sanitize_text_field($parts[$type]['longText'] ?? '');
        $short = static fn($type) => sanitize_text_field($parts[$type]['shortText'] ?? '');
        $street = trim($long('street_number') . ' ' . $long('route'));
        $city = $long('locality') ?: ($long('postal_town') ?: $long('administrative_area_level_2'));
        return rest_ensure_response([
            'country' => $short('country'),
            'state' => $short('administrative_area_level_1'),
            'city' => $city,
            'postcode' => $long('postal_code'),
            'address_1' => $street,
            'approximate' => !$long('street_number') || !$long('route'),
        ]);
    }
}
