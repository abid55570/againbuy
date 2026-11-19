<?php
$original = get_current_user_id();
$post = $_POST;
try {
    $users = get_users(['number' => 2, 'orderby' => 'ID']);
    if (count($users) < 2) { throw new RuntimeException('Two accounts required for name isolation check'); }
    foreach ($users as $user) {
        wp_set_current_user($user->ID);
        $expected = $user->first_name ?: (get_user_meta($user->ID, 'billing_first_name', true) ?: $user->display_name);
        if (againbuy_customer_name() !== $expected) { throw new RuntimeException('Name does not match the active account'); }
    }
    wp_set_current_user(0);
    if (againbuy_customer_name() !== '') { throw new RuntimeException('Signed-out visitor inherited a name'); }
    $_POST = ['register' => 'Register'];
    if (!apply_filters('woocommerce_registration_errors', new WP_Error(), '', '')->has_errors()) { throw new RuntimeException('Missing signup name accepted'); }
    $_POST['first_name'] = 'Customer Name';
    if (apply_filters('woocommerce_registration_errors', new WP_Error(), '', '')->has_errors()) { throw new RuntimeException('Valid signup name rejected'); }
    $_POST = [];
    if (apply_filters('woocommerce_registration_errors', new WP_Error(), '', '')->has_errors()) { throw new RuntimeException('Checkout or Google account creation was blocked'); }
    echo "Current-user names, signed-out isolation and signup validation passed.\n";
} finally {
    $_POST = $post;
    wp_set_current_user($original);
}
