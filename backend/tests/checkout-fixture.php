<?php
$action = getenv('AB_FIXTURE_ACTION') ?: 'create';
$id = (int) get_option('againbuy_checkout_test_product_id', 0);
if ($action === 'delete') {
    if ($id) { wp_delete_post($id, true); delete_option('againbuy_checkout_test_product_id'); }
    foreach (wc_get_orders(['billing_email' => 'againbuy-checkout-test@example.com', 'limit' => -1]) as $order) { $order->delete(true); }
    $previous = get_option('againbuy_checkout_test_bacs_previous', null);
    if ($previous === null) { delete_option('woocommerce_bacs_settings'); }
    else { update_option('woocommerce_bacs_settings', $previous); }
    delete_option('againbuy_checkout_test_bacs_previous');
    echo "Checkout fixture removed.\n";
    return;
}
if (!$id) {
    $product = new WC_Product_Simple();
    $product->set_name('Checkout flow test item');
    $product->set_status('publish');
    $product->set_catalog_visibility('hidden');
    $product->set_virtual(true);
    $product->set_regular_price('100');
    $product->update_meta_data('_againbuy_usd_price', '2.00');
    $id = $product->save();
    update_option('againbuy_checkout_test_product_id', $id, false);
}
if (get_option('againbuy_checkout_test_bacs_previous', null) === null) {
    update_option('againbuy_checkout_test_bacs_previous', get_option('woocommerce_bacs_settings', null), false);
}
update_option('woocommerce_bacs_settings', ['enabled' => 'yes', 'title' => 'Local test bank transfer']);
echo "Checkout fixture product ID: {$id}\n";
