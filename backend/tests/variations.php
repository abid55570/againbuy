<?php
$parent = new WC_Product_Variable();
$parent->set_name('Variable test product');
$parent->set_status('publish');
$attribute = new WC_Product_Attribute();
$attribute->set_name('Color');
$attribute->set_options(['Red']);
$attribute->set_variation(true);
$parent->set_attributes([$attribute]);
$parent->save();
$variation = new WC_Product_Variation();
$variation->set_parent_id($parent->get_id());
$variation->set_attributes(['color' => 'Red']);
$variation->set_regular_price('100');
$variation->update_meta_data('_againbuy_usd_price', '5.00');
$variation->save();
$missing = new WC_Product_Variation();
$missing->set_parent_id($parent->get_id());
$missing->set_attributes(['color' => 'Blue']);
$missing->set_regular_price('80');
$missing->save();
try {
    $_COOKIE['againbuy_test_country'] = 'US';
    $parent = wc_get_product($parent->get_id());
    $prices = array_values($parent->get_variation_prices(true)['price']);
    if ($prices !== [5.0] && $prices !== ['5'] && $prices !== ['5.00']) {
        throw new RuntimeException('Variable product USD range is wrong: ' . wp_json_encode($prices));
    }
    if ($missing->is_purchasable()) { throw new RuntimeException('Variation without USD price is purchasable'); }
    echo "Variation currency checks passed.\n";
} finally {
    wp_delete_post($variation->get_id(), true);
    wp_delete_post($missing->get_id(), true);
    wp_delete_post($parent->get_id(), true);
    unset($_COOKIE['againbuy_test_country']);
}
