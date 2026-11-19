<?php
if (!defined('ABSPATH')) { exit; }

add_action('init', static function (): void {
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (rtrim((string) $path, '/') !== '/login') { return; }
    global $user_login, $error, $errors, $action, $interim_login, $redirect_to, $rememberme, $reauth;
    $user_login = '';
    $error = '';
    $GLOBALS['pagenow'] = 'wp-login.php';
    require ABSPATH . 'wp-login.php';
    exit;
}, 0);

add_filter('login_url', static fn($url, $redirect, $reauth) => add_query_arg(array_filter(['redirect_to' => $redirect, 'reauth' => $reauth ? '1' : '']), home_url('/login')), 10, 3);
foreach (['lostpassword_url', 'logout_url'] as $hook) {
    add_filter($hook, static fn($url) => str_replace('/wp-login.php', '/login', $url));
}
add_filter('register_url', static fn() => home_url('/my-account/?account=create'));
add_filter('retrieve_password_message', static fn($message) => str_replace('/wp-login.php', '/login', $message));
add_action('login_init', static function (): void {
    if (isset($_REQUEST['loginSocial'])) { return; }
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!str_ends_with((string) $path, '/wp-login.php')) { return; }
    $query = wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    wp_safe_redirect(home_url('/login') . ($query ? '?' . $query : ''), ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 307 : 302);
    exit;
});
add_filter('nsl_is_register_allowed', static function ($allowed, $provider) {
    return $provider->getId() === 'google' && get_option('woocommerce_enable_myaccount_registration') === 'yes' ? true : $allowed;
}, 20, 2);
add_filter('login_headerurl', static fn() => home_url('/'));
add_filter('login_headertext', static fn() => 'AgainBuy');
add_filter('login_title', static fn() => 'Sign in | AgainBuy');
add_action('login_enqueue_scripts', static function (): void {
    wp_enqueue_style('againbuy-login', plugins_url('login.css', __FILE__), [], (string) filemtime(__DIR__ . '/login.css'));
});
add_action('login_head', static function (): void {
    echo '<link rel="icon" type="image/svg+xml" href="' . esc_url(get_stylesheet_directory_uri() . '/favicon.svg') . '">';
});
add_action('admin_bar_menu', static function ($bar): void { $bar->remove_node('wp-logo'); }, 999);
add_filter('admin_footer_text', static fn() => 'AgainBuy');
add_filter('admin_title', static fn($title) => str_replace('WordPress', 'AgainBuy', $title));
