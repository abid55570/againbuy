<?php
$orders = wc_get_orders(['billing_email' => 'againbuy-checkout-test@example.com', 'limit' => -1]);
if (count($orders) !== 1) { throw new RuntimeException('Expected one Razorpay test order'); }
$order = $orders[0];
if ($order->get_payment_method() !== 'razorpay' || $order->is_paid() || $order->get_status() !== 'pending') {
    throw new RuntimeException('Razorpay test order has an unsafe payment state');
}
echo "Razorpay order remains pending until payment confirmation.\n";
