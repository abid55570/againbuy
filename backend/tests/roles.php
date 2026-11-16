<?php
$catalog_id = wp_insert_user(['user_login' => 'ab_catalog_test_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(20), 'role' => 'againbuy_catalog_manager']);
$fulfilment_id = wp_insert_user(['user_login' => 'ab_fulfilment_test_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(20), 'role' => 'againbuy_fulfilment_manager']);
$product = new WC_Product_Simple();
$product->set_name('Role test item');
$product->set_status('publish');
$product->set_regular_price('100');
$product->save();
$order = wc_create_order();
$order->set_date_paid(time());
$order->set_status('processing');
$order->save();
$unpaid = wc_create_order();
try {
    $catalog = get_user_by('id', $catalog_id);
    $fulfilment = get_user_by('id', $fulfilment_id);
    echo 'Catalog product=' . (int) user_can($catalog, 'edit_post', $product->get_id()) . ' order=' . (int) user_can($catalog, 'edit_shop_order', $order->get_id()) . ' settings=' . (int) user_can($catalog, 'manage_woocommerce') . "\n";
    echo 'Fulfilment order=' . (int) user_can($fulfilment, 'edit_shop_order', $order->get_id()) . ' product=' . (int) user_can($fulfilment, 'edit_post', $product->get_id()) . ' settings=' . (int) user_can($fulfilment, 'manage_woocommerce') . "\n";
    if (!user_can($catalog, 'edit_post', $product->get_id()) || user_can($catalog, 'edit_shop_order', $order->get_id()) || user_can($catalog, 'manage_woocommerce')) {
        throw new RuntimeException('Catalog Manager access failed');
    }
    if (!user_can($fulfilment, 'edit_shop_order', $order->get_id()) || user_can($fulfilment, 'edit_post', $product->get_id()) || user_can($fulfilment, 'manage_woocommerce')) {
        throw new RuntimeException('Fulfilment Manager access failed');
    }
    if (user_can($fulfilment, 'edit_shop_order', $unpaid->get_id())) {
        throw new RuntimeException('Fulfilment Manager can edit an unpaid order');
    }
    echo "Staff role access checks passed.\n";
} finally {
    $order->delete(true);
    $unpaid->delete(true);
    $product->delete(true);
    wp_delete_user($catalog_id);
    wp_delete_user($fulfilment_id);
}
