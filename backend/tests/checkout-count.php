<?php
$orders = wc_get_orders(['billing_email' => 'againbuy-checkout-test@example.com', 'limit' => -1]);
$placed = array_filter($orders, static fn($order) => $order->get_status() !== 'checkout-draft');
echo 'Placed checkout test orders: ' . count($placed) . "\n";
