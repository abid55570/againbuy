<?php
$key = getenv('MAXMIND_LICENSE_KEY') ?: '';
if (!$key) { return; }
$settings = get_option('woocommerce_maxmind_geolocation_settings', []);
if (!is_array($settings)) { $settings = []; }
$settings['license_key'] = $key;
update_option('woocommerce_maxmind_geolocation_settings', $settings);
update_option('woocommerce_default_customer_address', 'geolocation');
do_action('woocommerce_geoip_updater');
echo "WooCommerce geolocation configured.\n";
