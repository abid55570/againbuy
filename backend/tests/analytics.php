<?php
if (!class_exists('AgainBuy_Analytics')) { throw new RuntimeException('Sales analytics is missing'); }
$product = new WC_Product_Simple();
$product->set_name('Analytics test product');
$product->set_status('publish');
$product->set_regular_price('1499');
$product->save();
$order = wc_create_order();
$failed = wc_create_order();
try {
    $order->add_product($product, 2);
    $order->set_currency('INR');
    $order->set_payment_method('razorpay');
    $order->set_billing_country('IN');
    $order->calculate_totals();
    $order->set_date_paid(time());
    $order->set_status('processing');
    $order->save();
    $failed->add_product($product, 1);
    $failed->set_currency('INR');
    $failed->set_payment_method('razorpay');
    $failed->set_status('failed');
    $failed->calculate_totals();
    $failed->save();
    $summary = AgainBuy_Analytics::summary(gmdate('Y-m-d', strtotime('-1 day')), gmdate('Y-m-d', strtotime('+1 day')));
    if (($summary['products'][$product->get_id()]['quantity'] ?? 0) < 2 || ($summary['payments']['razorpay'] ?? 0) < 1) {
        throw new RuntimeException('Product/payment analytics missed a paid order');
    }
    if (($summary['payment_revenue']['razorpay']['INR'] ?? 0) < 2998) {
        throw new RuntimeException('Payment method revenue is missing');
    }
    if (($summary['payment_statuses']['razorpay']['failed'] ?? 0) < 1) {
        throw new RuntimeException('Failed payment/order attempt is absent from analytics');
    }
    $csv = AgainBuy_Analytics::csv($summary);
    if (!str_contains($csv, 'razorpay') || !str_contains($csv, 'Analytics test product')) {
        throw new RuntimeException('CSV export omitted payment or product data');
    }
    $dangerous = $summary;
    $dangerous['products'][$product->get_id()]['name'] = '=2+2';
    if (!str_contains(AgainBuy_Analytics::csv($dangerous), "'=2+2")) {
        throw new RuntimeException('CSV formula injection was not neutralized');
    }
    wp_set_current_user(get_user_by('login', 'admin')->ID);
    $_GET['from'] = gmdate('Y-m-d', strtotime('-1 day'));
    $_GET['to'] = gmdate('Y-m-d', strtotime('+1 day'));
    ob_start();
    AgainBuy_Analytics::page();
    $page = ob_get_clean();
    if (!str_contains($page, 'Product revenue') || !str_contains($page, 'Payment method net revenue')) {
        throw new RuntimeException('Admin sales report did not render');
    }
    echo "Sales analytics checks passed.\n";
} finally {
    wp_set_current_user(0);
    unset($_GET['from'], $_GET['to']);
    $order->delete(true);
    $failed->delete(true);
    $product->delete(true);
}
