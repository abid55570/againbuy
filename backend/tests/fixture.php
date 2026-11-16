<?php
$action = getenv('AB_FIXTURE_ACTION') ?: 'create';
$id = (int) get_option('againbuy_test_product_id', 0);
$no_usd_id = (int) get_option('againbuy_test_no_usd_id', 0);
if ($action === 'delete') {
    if ($id) { wp_delete_post($id, true); delete_option('againbuy_test_product_id'); }
    if ($no_usd_id) { wp_delete_post($no_usd_id, true); delete_option('againbuy_test_no_usd_id'); }
    echo "Fixture removed.\n";
    return;
}
if (!$id) {
    $product = new WC_Product_Simple();
    $product->set_name('AgainBuy test bottle');
    $product->set_status('publish');
    $product->set_catalog_visibility('hidden');
    $product->set_description('A reusable bottle for checking the product and cart flow.');
    $product->set_regular_price('1499');
    $product->set_manage_stock(true);
    $product->set_stock_quantity(10);
    $product->update_meta_data('_againbuy_usd_price', '29.00');
    $id = $product->save();
    update_option('againbuy_test_product_id', $id, false);
}
if (!$no_usd_id) {
    $product = new WC_Product_Simple();
    $product->set_name('AgainBuy INR-only test item');
    $product->set_status('publish');
    $product->set_catalog_visibility('hidden');
    $product->set_regular_price('500');
    $no_usd_id = $product->save();
    update_option('againbuy_test_no_usd_id', $no_usd_id, false);
}
echo "Fixture product ID: {$id}\n";
echo "INR-only fixture ID: {$no_usd_id}\n";
