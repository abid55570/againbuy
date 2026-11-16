<?php
if (get_stylesheet() !== 'againbuy') {
    throw new RuntimeException('AgainBuy theme is inactive');
}
if (!current_theme_supports('woocommerce')) {
    throw new RuntimeException('WooCommerce theme support is missing');
}
if (!has_nav_menu('primary')) {
    echo "Primary menu has not been assigned; fallback navigation is expected.\n";
}
if (!has_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price') || !has_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart')) {
    throw new RuntimeException('Product cards are missing price or Add to cart');
}
if (!is_file(get_stylesheet_directory() . '/card-gallery.js')) { throw new RuntimeException('Product card gallery script is missing'); }
if (!is_file(get_stylesheet_directory() . '/cart-feedback.js')) { throw new RuntimeException('Cart feedback script is missing'); }
if (!has_filter('woocommerce_add_to_cart_fragments')) { throw new RuntimeException('Cart count fragments are missing'); }
if (!is_file(get_stylesheet_directory() . '/favicon.svg')) { throw new RuntimeException('AgainBuy favicon is missing'); }
$homepage = file_get_contents(get_stylesheet_directory() . '/front-page.php');
if (!str_contains($homepage, '$featured[0]->get_permalink()')) { throw new RuntimeException('Featured product image has no product link'); }
$gallery = apply_filters('woocommerce_single_product_carousel_options', ['slideshow' => false, 'slideshowSpeed' => 7000]);
if (($gallery['slideshow'] ?? null) !== true || ($gallery['slideshowSpeed'] ?? null) !== 2000) { throw new RuntimeException('Product gallery does not advance every two seconds'); }
echo "Phase 3 theme integration checks passed.\n";
