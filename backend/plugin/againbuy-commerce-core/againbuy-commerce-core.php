<?php
/**
 * Plugin Name: AgainBuy Commerce Core
 * Description: Staff roles, international product prices, location pricing and catalog audit for AgainBuy.
 * Version: 0.2.0
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/login.php';

final class AgainBuy_Commerce_Core {
    private const SCHEMA_VERSION = '4';
    public static function boot(): void {
        add_filter('woocommerce_ga_disable_tracking', [__CLASS__, 'exclude_staff_tracking']);
        add_action('template_redirect', [__CLASS__, 'test_country_request']);
        add_filter('woocommerce_currency', [__CLASS__, 'currency']);
        foreach (['price', 'regular_price', 'sale_price'] as $field) {
            add_filter("woocommerce_product_get_{$field}", [__CLASS__, 'product_price'], 20, 2);
            add_filter("woocommerce_product_variation_get_{$field}", [__CLASS__, 'product_price'], 20, 2);
            add_filter("woocommerce_variation_prices_{$field}", [__CLASS__, 'variation_price'], 20, 3);
        }
        add_filter('woocommerce_get_variation_prices_hash', static function ($hash) { $hash[] = self::selected_currency(); return $hash; });
        add_filter('woocommerce_is_purchasable', [__CLASS__, 'purchasable'], 20, 2);
        add_filter('woocommerce_get_price_html', [__CLASS__, 'missing_usd_price_html'], 20, 2);
        add_action('woocommerce_product_options_pricing', [__CLASS__, 'price_field']);
        add_action('woocommerce_product_options_related', [__CLASS__, 'related_field']);
        add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_price']);
        add_action('woocommerce_variation_options_pricing', [__CLASS__, 'variation_field'], 10, 3);
        add_action('woocommerce_save_product_variation', [__CLASS__, 'save_variation'], 10, 2);
        add_action('updated_post_meta', [__CLASS__, 'audit_meta'], 10, 4);
        add_action('added_post_meta', [__CLASS__, 'audit_meta'], 10, 4);
        add_action('deleted_post_meta', [__CLASS__, 'audit_meta'], 10, 4);
        add_action('post_updated', [__CLASS__, 'audit_post'], 10, 3);
        add_action('admin_menu', [__CLASS__, 'audit_menu']);
        add_filter('option_woocommerce_maxmind_geolocation_settings', [__CLASS__, 'maxmind_settings']);
        if (getenv('LOCATION_AUTOFILL_ENABLED') === 'true' && getenv('GOOGLE_GEOCODING_API_KEY')) {
            require_once __DIR__ . '/location.php';
            AgainBuy_Location::boot();
        }
        add_filter('woocommerce_output_related_products_args', [__CLASS__, 'related_args']);
        add_filter('woocommerce_related_products', [__CLASS__, 'related_ids'], 10, 3);
        add_filter('option_woocommerce_razorpay_settings', [__CLASS__, 'razorpay_settings']);
        add_filter('user_has_cap', [__CLASS__, 'fulfilment_order_cap'], 100, 4);
        add_filter('woocommerce_prevent_admin_access', [__CLASS__, 'allow_report_admin']);
        add_action('phpmailer_init', [__CLASS__, 'mailer']);
        add_filter('wp_mail_from', static function ($from) {
            $configured = getenv('MAIL_FROM_ADDRESS') ?: '';
            return is_email($configured) ? $configured : $from;
        });
        add_filter('wp_mail_from_name', static fn($name) => getenv('MAIL_FROM_NAME') ?: $name);
    }

    public static function exclude_staff_tracking(bool $disabled): bool {
        return $disabled || is_admin() || current_user_can('manage_options')
            || current_user_can('edit_products') || current_user_can('edit_shop_orders')
            || current_user_can('view_woocommerce_reports');
    }

    public static function allow_report_admin(bool $prevent): bool {
        return current_user_can('view_woocommerce_reports') ? false : $prevent;
    }

    public static function fulfilment_order_cap(array $allcaps, array $caps, array $args, WP_User $user): array {
        $cap = $args[0] ?? '';
        if (!in_array($cap, ['edit_shop_order', 'read_shop_order', 'delete_shop_order'], true) || !in_array('againbuy_fulfilment_manager', $user->roles, true)) { return $allcaps; }
        $order = isset($args[2]) ? wc_get_order((int) $args[2]) : false;
        if (!$order || !$order->is_paid()) { foreach ($caps as $required) { $allcaps[$required] = false; } }
        return $allcaps;
    }

    public static function activate(): void {
        if (!class_exists('WooCommerce')) { return; }
        global $wpdb;
        $table = $wpdb->prefix . 'againbuy_audit';
        $collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            occurred_at datetime NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            action varchar(24) NOT NULL,
            field varchar(80) NOT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY occurred_at (occurred_at)
        ) {$collate};");
        $catalog = ['read', 'upload_files', 'edit_product', 'read_product', 'delete_product', 'edit_products', 'edit_others_products', 'publish_products', 'read_private_products', 'delete_products', 'delete_private_products', 'delete_published_products', 'delete_others_products', 'edit_private_products', 'edit_published_products', 'manage_product_terms', 'edit_product_terms', 'delete_product_terms', 'assign_product_terms'];
        $fulfilment = ['read', 'edit_shop_order', 'read_shop_order', 'edit_shop_orders', 'edit_others_shop_orders', 'read_private_shop_orders', 'edit_private_shop_orders', 'edit_published_shop_orders'];
        foreach (['againbuy_catalog_manager' => $catalog, 'againbuy_fulfilment_manager' => $fulfilment] as $role => $caps) {
            $instance = get_role($role) ?: add_role($role, ucwords(str_replace('_', ' ', $role)), ['read' => true]);
            if ($instance) { foreach ($caps as $cap) { $instance->add_cap($cap); } }
        }
        $analyst = get_role('againbuy_analyst') ?: add_role('againbuy_analyst', 'AgainBuy Analyst', ['read' => true]);
        if ($analyst) { $analyst->add_cap('view_woocommerce_reports'); }
        $shop_manager = get_role('shop_manager');
        if ($shop_manager) {
            $shop_manager->remove_cap('manage_woocommerce');
            $shop_manager->add_cap('view_woocommerce_reports');
        }
        update_option('againbuy_core_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function selected_currency(): string {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/wc/store/'))) { return 'INR'; }
        if (getenv('APP_ENV') === 'local') {
            $test_country = sanitize_text_field(wp_unslash($_COOKIE['againbuy_test_country'] ?? ''));
            if (in_array($test_country, ['IN', 'US'], true)) { return $test_country === 'IN' ? 'INR' : 'USD'; }
        }
        if (function_exists('WC') && WC()->customer) {
            $country = WC()->customer->get_shipping_country() ?: WC()->customer->get_billing_country();
            if ($country) { return $country === 'IN' ? 'INR' : 'USD'; }
        }
        $geo = class_exists('WC_Geolocation') ? WC_Geolocation::geolocate_ip(self::visitor_ip(), false, false) : [];
        $country = $geo['country'] ?? '';
        if (!$country) { $country = getenv('DEFAULT_COUNTRY') ?: 'IN'; }
        return strtoupper($country) === 'IN' ? 'INR' : 'USD';
    }

    public static function currency(string $currency): string {
        return self::selected_currency();
    }

    public static function test_country_request(): void {
        if (getenv('APP_ENV') !== 'local' || !isset($_GET['againbuy_test_country'])) { return; }
        $country = sanitize_text_field(wp_unslash($_GET['againbuy_test_country']));
        if (!in_array($country, ['IN', 'US'], true)) { return; }
        setcookie('againbuy_test_country', $country, ['expires' => time() + HOUR_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE['againbuy_test_country'] = $country;
        if (function_exists('WC') && WC()->cart) { WC()->cart->calculate_totals(); }
        wp_safe_redirect(remove_query_arg('againbuy_test_country'));
        exit;
    }

    public static function maxmind_settings($settings): array {
        $settings = is_array($settings) ? $settings : [];
        if (getenv('MAXMIND_LICENSE_KEY')) { $settings['license_key'] = getenv('MAXMIND_LICENSE_KEY'); }
        return $settings;
    }

    public static function visitor_ip(): string {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!filter_var($remote, FILTER_VALIDATE_IP)) { return ''; }
        $trusted = array_filter(array_map('trim', explode(',', getenv('TRUSTED_PROXY_CIDRS') ?: '')));
        foreach ($trusted as $range) {
            [$network, $bits] = array_pad(explode('/', $range, 2), 2, '');
            $ip = inet_pton($remote); $base = @inet_pton($network);
            if (!$base || strlen($base) !== strlen($ip) || !ctype_digit($bits) || (int) $bits > strlen($ip) * 8) { continue; }
            $full = intdiv((int) $bits, 8); $partial = (int) $bits % 8;
            if (substr($ip, 0, $full) !== substr($base, 0, $full)) { continue; }
            if ($partial && ((ord($ip[$full]) ^ ord($base[$full])) & (0xff << (8 - $partial)))) { continue; }
            $forwarded = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? ''))[0]);
            return filter_var($forwarded, FILTER_VALIDATE_IP) ? $forwarded : $remote;
        }
        return $remote;
    }

    public static function usd_price(WC_Product $product, string $field = 'price'): ?float {
        $suffix = $field === 'sale_price' ? '_sale' : '';
        $raw = $product->get_meta('_againbuy_usd' . $suffix . '_price', true);
        if ($raw === '' || !is_numeric($raw) || (float) $raw < 0) { return null; }
        return (float) $raw;
    }

    public static function product_price($price, WC_Product $product) {
        if (self::selected_currency() !== 'USD') { return $price; }
        $filter = current_filter();
        $regular = self::usd_price($product);
        $sale = self::usd_price($product, 'sale_price');
        if (str_ends_with($filter, 'sale_price')) { return $sale === null ? '' : (string) $sale; }
        $usd = str_ends_with($filter, 'regular_price') ? $regular : ($sale !== null && $regular !== null && $sale < $regular ? $sale : $regular);
        return $usd === null ? '' : (string) $usd;
    }

    public static function purchasable(bool $purchasable, WC_Product $product): bool {
        if ($product->is_type('variable') && self::selected_currency() === 'USD') {
            foreach ($product->get_children() as $child_id) {
                $child = wc_get_product($child_id);
                if ($child && self::usd_price($child) !== null) { return $purchasable; }
            }
            return false;
        }
        return $purchasable && (self::selected_currency() !== 'USD' || self::usd_price($product) !== null);
    }

    public static function missing_usd_price_html(string $html, WC_Product $product): string {
        if (self::selected_currency() !== 'USD' || !$product->is_type('simple') || self::usd_price($product) !== null) { return $html; }
        $inr = $product->get_regular_price('edit');
        if ($inr === '' || !is_numeric($inr)) { return $html; }
        return esc_html__('INR price', 'againbuy') . ' ' . wc_price((float) $inr, ['currency' => 'INR']) . ' <small>' . esc_html__('USD price unavailable', 'againbuy') . '</small>';
    }

    public static function variation_price($price, WC_Product_Variation $variation, WC_Product_Variable $parent) {
        return self::product_price($price, $variation);
    }

    public static function price_field(): void {
        woocommerce_wp_text_input(['id' => '_againbuy_usd_price', 'label' => __('USD regular price ($)', 'againbuy'), 'data_type' => 'price', 'description' => __('Required to sell this product in USD.', 'againbuy'), 'desc_tip' => true]);
        woocommerce_wp_text_input(['id' => '_againbuy_usd_sale_price', 'label' => __('USD sale price ($)', 'againbuy'), 'data_type' => 'price']);
    }

    public static function related_field(): void {
        global $product_object;
        $ids = $product_object ? (array) $product_object->get_meta('_againbuy_related_ids', true) : [];
        woocommerce_wp_text_input(['id' => '_againbuy_related_ids_input', 'label' => __('Pinned related product IDs', 'againbuy'), 'value' => implode(',', array_map('absint', $ids)), 'description' => __('Comma-separated IDs; in-stock items appear before category suggestions.', 'againbuy'), 'desc_tip' => true]);
    }

    public static function save_price(WC_Product $product): void {
        foreach (['_againbuy_usd_price', '_againbuy_usd_sale_price'] as $key) {
            if (!isset($_POST[$key])) { continue; }
            $value = wc_format_decimal(wp_unslash($_POST[$key]));
            $product->update_meta_data($key, $value !== '' && (float) $value >= 0 ? $value : '');
        }
        if (isset($_POST['_againbuy_related_ids_input'])) {
            $ids = array_filter(array_unique(array_map('absint', explode(',', sanitize_text_field(wp_unslash($_POST['_againbuy_related_ids_input']))))));
            $product->update_meta_data('_againbuy_related_ids', array_values(array_diff($ids, [$product->get_id()])));
        }
    }

    public static function variation_field($loop, $variation_data, $variation): void {
        woocommerce_wp_text_input(['id' => "_againbuy_usd_price_{$loop}", 'name' => "_againbuy_usd_price[{$loop}]", 'value' => get_post_meta($variation->ID, '_againbuy_usd_price', true), 'label' => __('USD regular price ($)', 'againbuy'), 'data_type' => 'price', 'wrapper_class' => 'form-row form-row-first']);
        woocommerce_wp_text_input(['id' => "_againbuy_usd_sale_price_{$loop}", 'name' => "_againbuy_usd_sale_price[{$loop}]", 'value' => get_post_meta($variation->ID, '_againbuy_usd_sale_price', true), 'label' => __('USD sale price ($)', 'againbuy'), 'data_type' => 'price', 'wrapper_class' => 'form-row form-row-last']);
    }

    public static function save_variation(int $variation_id, int $index): void {
        foreach (['_againbuy_usd_price', '_againbuy_usd_sale_price'] as $key) {
            if (!isset($_POST[$key][$index])) { continue; }
            $value = wc_format_decimal(wp_unslash($_POST[$key][$index]));
            update_post_meta($variation_id, $key, $value !== '' && (float) $value >= 0 ? $value : '');
        }
    }

    private static function audit(string $action, int $post_id, string $detail): void {
        if (!get_current_user_id() || !in_array(get_post_type($post_id), ['product', 'product_variation'], true)) { return; }
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'againbuy_audit', [
            'occurred_at' => current_time('mysql', true), 'actor_id' => get_current_user_id(),
            'product_id' => $post_id, 'action' => $action, 'field' => $detail,
        ], ['%s', '%d', '%d', '%s', '%s']);
    }

    public static function audit_meta($meta_id, $post_id, $key, $value): void {
        if (in_array($key, ['_price', '_regular_price', '_sale_price', '_againbuy_usd_price', '_againbuy_usd_sale_price', '_stock', '_stock_status', '_thumbnail_id', '_product_image_gallery'], true)) {
            self::audit('meta', (int) $post_id, (string) $key);
        }
    }

    public static function audit_post(int $post_id, WP_Post $after, WP_Post $before): void {
        if ($after->post_title !== $before->post_title || $after->post_status !== $before->post_status || $after->post_content !== $before->post_content) {
            self::audit('post', $post_id, 'title/status/description');
        }
    }

    public static function audit_menu(): void {
        add_submenu_page('woocommerce', __('Catalog audit', 'againbuy'), __('Catalog audit', 'againbuy'), 'manage_woocommerce', 'againbuy-audit', [__CLASS__, 'audit_page']);
    }

    public static function audit_page(): void {
        if (!current_user_can('manage_woocommerce')) { wp_die(esc_html__('Not allowed.', 'againbuy')); }
        global $wpdb;
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        $entries = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}againbuy_audit ORDER BY id DESC LIMIT 50 OFFSET %d", ($page - 1) * 50));
        echo '<div class="wrap"><h1>Catalog audit</h1><table class="widefat striped"><thead><tr><th>UTC</th><th>User</th><th>Product ID</th><th>Change</th></tr></thead><tbody>';
        foreach ($entries as $entry) {
            echo '<tr><td>' . esc_html($entry->occurred_at) . '</td><td>' . esc_html(get_the_author_meta('display_name', $entry->actor_id)) . '</td><td>' . esc_html((string) $entry->product_id) . '</td><td>' . esc_html($entry->field) . '</td></tr>';
        }
        echo '</tbody></table>';
        if (count($entries) === 50) { echo '<p><a href="' . esc_url(add_query_arg('paged', $page + 1)) . '">Older changes →</a></p>'; }
        if ($page > 1) { echo '<p><a href="' . esc_url(add_query_arg('paged', $page - 1)) . '">← Newer changes</a></p>'; }
        echo '</div>';
    }

    public static function related_args(array $args): array {
        $args['posts_per_page'] = 4;
        return $args;
    }

    public static function related_ids(array $ids, int $product_id, array $args): array {
        $pinned = array_filter(array_map('absint', (array) get_post_meta($product_id, '_againbuy_related_ids', true)));
        $ordered = array_unique(array_merge($pinned, $ids));
        return array_values(array_filter($ordered, static function ($id) use ($product_id) {
            $product = wc_get_product($id);
            return $id !== $product_id && $product && $product->is_visible() && $product->is_in_stock();
        }));
    }

    public static function razorpay_settings($settings): array {
        $settings = is_array($settings) ? $settings : [];
        $mode = getenv('RAZORPAY_MODE') ?: 'test';
        $key = getenv('RAZORPAY_KEY_ID') ?: '';
        $secret = getenv('RAZORPAY_KEY_SECRET') ?: '';
        $webhook = getenv('RAZORPAY_WEBHOOK_SECRET') ?: '';
        $valid = $key && $secret && $webhook && in_array($mode, ['test', 'live'], true)
            && str_starts_with($key, 'rzp_' . $mode . '_')
            && ($mode !== 'live' || getenv('APP_ENV') === 'production');
        if (!$valid) { $settings['enabled'] = 'no'; return $settings; }
        $settings['key_id'] = $key;
        $settings['key_secret'] = $secret;
        $settings['webhook_secret'] = $webhook;
        return $settings;
    }

    public static function mailer($mailer): void {
        $host = getenv('SMTP_HOST');
        if (!$host) { return; }
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Port = (int) (getenv('SMTP_PORT') ?: 25);
        $user = getenv('SMTP_USERNAME') ?: '';
        $pass = getenv('SMTP_PASSWORD') ?: '';
        $mailer->SMTPAuth = $user !== '' && $pass !== '';
        if ($mailer->SMTPAuth) { $mailer->Username = $user; $mailer->Password = $pass; }
        $encryption = getenv('SMTP_ENCRYPTION') ?: 'none';
        if (in_array($encryption, ['tls', 'ssl'], true)) { $mailer->SMTPSecure = $encryption; }
        $from = getenv('MAIL_FROM_ADDRESS') ?: '';
        if (is_email($from)) { $mailer->setFrom($from, getenv('MAIL_FROM_NAME') ?: 'AgainBuy'); }
    }
}

register_activation_hook(__FILE__, ['AgainBuy_Commerce_Core', 'activate']);
add_action('plugins_loaded', static function () {
    if (class_exists('WooCommerce')) {
        if (get_option('againbuy_core_schema_version') !== '3') { AgainBuy_Commerce_Core::activate(); }
        AgainBuy_Commerce_Core::boot();
        AgainBuy_Analytics::boot();
    }
});
