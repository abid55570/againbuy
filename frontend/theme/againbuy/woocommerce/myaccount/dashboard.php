<?php
defined('ABSPATH') || exit;
$customer = wp_get_current_user();
?>
<div class="account-welcome"><p class="eyebrow">Your account</p><h2>Welcome, <?php echo esc_html(againbuy_customer_name()); ?></h2><p>Find your orders, update delivery addresses and manage your account.</p></div>
<div class="account-shortcuts">
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>"><span class="account-shortcut-icon" aria-hidden="true">↗</span><h3>Your orders</h3><p>View order details and payment status.</p><span>View orders →</span></a>
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>"><span class="account-shortcut-icon" aria-hidden="true">⌂</span><h3>Delivery addresses</h3><p>Manage your billing and shipping addresses.</p><span>Manage addresses →</span></a>
    <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>"><span class="account-shortcut-icon" aria-hidden="true">⚙</span><h3>Account details</h3><p>Update your name, email and password.</p><span>Edit details →</span></a>
</div>
<p class="account-shop-link"><a class="button" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Continue shopping</a></p>
<?php do_action('woocommerce_account_dashboard'); ?>
