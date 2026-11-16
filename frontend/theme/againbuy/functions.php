<?php
if (!defined('ABSPATH')) { exit; }

add_action('after_setup_theme', static function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce', ['thumbnail_image_width' => 500, 'single_image_width' => 700]);
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    register_nav_menus(['primary' => __('Primary navigation', 'againbuy')]);
});

add_action('wp_enqueue_scripts', static function () {
    wp_enqueue_style('againbuy-style', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
    wp_enqueue_script('againbuy-card-gallery', get_theme_file_uri('card-gallery.js'), [], wp_get_theme()->get('Version'), true);
    wp_enqueue_script('wc-cart-fragments');
    wp_enqueue_script('againbuy-cart-feedback', get_theme_file_uri('cart-feedback.js'), ['jquery', 'wc-cart-fragments'], wp_get_theme()->get('Version'), true);
    if (is_checkout() && !is_order_received_page() && getenv('LOCATION_AUTOFILL_ENABLED') === 'true' && getenv('GOOGLE_GEOCODING_API_KEY')) {
        wp_enqueue_style('againbuy-checkout-location', get_theme_file_uri('checkout-location.css'), [], wp_get_theme()->get('Version'));
        wp_enqueue_script('againbuy-checkout-location', get_theme_file_uri('checkout-location.js'), [], wp_get_theme()->get('Version'), true);
        wp_add_inline_script('againbuy-checkout-location', 'window.againbuyLocation=' . wp_json_encode([
            'url' => rest_url('againbuy/v1/reverse-geocode'),
            'nonce' => wp_create_nonce('wp_rest'),
            'available' => (bool) getenv('GOOGLE_GEOCODING_API_KEY'),
        ]) . ';', 'before');
    }
});

function againbuy_cart_count(): int {
    return function_exists('WC') && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
}

add_filter('woocommerce_add_to_cart_fragments', static function (array $fragments): array {
    $count = againbuy_cart_count();
    $fragments['.header-cart-count'] = '<span class="header-cart-count" aria-live="polite">' . esc_html((string) $count) . '</span>';
    $fragments['.mobile-cart-label'] = '<span class="mobile-cart-label">Cart' . ($count ? ' (' . esc_html((string) $count) . ')' : '') . '</span>';
    return $fragments;
});

add_filter('loop_shop_per_page', static fn() => 16);
function againbuy_registration_view(): bool {
    return get_option('woocommerce_enable_myaccount_registration') === 'yes'
        && (isset($_POST['register']) || (isset($_GET['account']) && $_GET['account'] === 'create'));
}
add_filter('body_class', static function (array $classes): array {
    if (is_account_page() && !is_user_logged_in() && !is_wc_endpoint_url()) {
        $classes[] = 'againbuy-auth';
        $classes[] = againbuy_registration_view() ? 'auth-create' : 'auth-login';
    }
    return $classes;
});
add_action('woocommerce_before_customer_login_form', static function (): void {
    $create = againbuy_registration_view();
    echo '<div class="auth-heading"><h1>' . ($create ? 'Create your account' : 'Welcome back') . '</h1><p>' . ($create ? 'Keep your orders and delivery details in one place.' : 'Sign in to view your orders and account details.') . '</p></div>';
    if (get_option('woocommerce_enable_myaccount_registration') === 'yes') {
        $account = wc_get_page_permalink('myaccount');
        echo '<nav class="auth-switch" aria-label="Account access"><a href="' . esc_url($account) . '"' . (!$create ? ' aria-current="page"' : '') . '>Sign in</a><a href="' . esc_url(add_query_arg('account', 'create', $account)) . '"' . ($create ? ' aria-current="page"' : '') . '>Create account</a></nav>';
    }
    if (shortcode_exists('nextend_social_login')) {
        echo '<div class="auth-social">' . do_shortcode('[nextend_social_login provider="google"]') . '</div>';
    }
});
add_filter('woocommerce_catalog_orderby', static function (array $options): array {
    return array_intersect_key($options, array_flip(['menu_order', 'date', 'price', 'price-desc']));
});
function againbuy_category_links(string $class): void {
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'exclude' => [get_option('default_product_cat')]]);
    if (is_wp_error($categories) || !$categories) { return; }
    echo '<nav class="' . esc_attr($class) . '" aria-label="Product categories">';
    foreach ($categories as $category) {
        echo '<a href="' . esc_url(get_term_link($category)) . '"' . (is_product_category($category->slug) ? ' aria-current="page"' : '') . '>' . esc_html($category->name) . '</a>';
    }
    echo '</nav>';
}
add_action('woocommerce_before_shop_loop', static function (): void {
    if (is_shop() || is_product_category()) { againbuy_category_links('shop-category-links'); }
}, 15);
add_action('woocommerce_single_product_summary', static function (): void {
    global $product;
    if (!$product instanceof WC_Product || $product->get_short_description()) { return; }
    $html = $product->get_description();
    preg_match('/<p[^>]*>(.*?)<\/p>/s', $html, $paragraph);
    $description = wp_strip_all_tags($paragraph[1] ?? $html);
    if ($description) { echo '<p class="againbuy-product-intro">' . esc_html(wp_trim_words($description, 28)) . '</p>'; }
}, 21);
add_filter('woocommerce_related_products', static function (array $ids, int $product_id): array {
    if ($ids) { return $ids; }
    return wc_get_products(['status' => 'publish', 'limit' => 4, 'exclude' => [$product_id], 'return' => 'ids', 'orderby' => 'date', 'order' => 'DESC']);
}, 10, 2);
remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
add_action('woocommerce_before_shop_loop_item_title', static function (): void {
    global $product;
    if (!$product instanceof WC_Product) { return; }
    $ids = array_values(array_unique(array_filter(array_merge([$product->get_image_id()], $product->get_gallery_image_ids()))));
    if (!$ids) { echo wc_placeholder_img('woocommerce_thumbnail'); return; }
    echo '<span class="againbuy-card-gallery">';
    foreach ($ids as $index => $id) {
        $attrs = ['alt' => $product->get_name()];
        if ($index) { $attrs['hidden'] = 'hidden'; }
        echo wp_get_attachment_image($id, 'woocommerce_thumbnail', false, $attrs);
    }
    echo '</span>';
}, 10);
add_filter('woocommerce_single_product_carousel_options', static function ($options) {
    $options['slideshow'] = true;
    $options['slideshowSpeed'] = 2000;
    $options['pauseOnHover'] = true;
    $options['pauseOnAction'] = true;
    $options['pausePlay'] = true;
    return $options;
});
add_filter('woocommerce_breadcrumb_defaults', static function ($defaults) {
    $defaults['delimiter'] = '<span aria-hidden="true"> / </span>';
    return $defaults;
});
