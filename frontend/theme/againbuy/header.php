<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="<?php echo esc_url(get_stylesheet_directory_uri() . '/favicon.svg'); ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="header-main wrap">
        <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="AgainBuy home">again<span>buy</span><i>.</i></a>
        <form class="site-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <label class="screen-reader-text" for="site-search">Search products</label>
            <input id="site-search" type="search" name="s" placeholder="Search products" value="<?php echo esc_attr(get_search_query()); ?>">
            <input type="hidden" name="post_type" value="product">
            <button type="submit" aria-label="Search">Search</button>
        </form>
        <div class="header-actions">
            <span class="currency-status"><?php echo esc_html(get_woocommerce_currency() === 'INR' ? '₹ INR' : '$ USD'); ?></span>
            <?php $account_name = againbuy_customer_name(); ?>
            <a class="header-account<?php echo is_user_logged_in() ? ' is-signed-in' : ''; ?>" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"><?php if (is_user_logged_in()) : ?><span class="account-status">Signed in</span><span class="account-name"><?php echo esc_html($account_name); ?></span><?php else : ?><span>Sign in</span><?php endif; ?></a>
            <a class="cart-link" href="<?php echo esc_url(wc_get_cart_url()); ?>">Cart <span class="header-cart-count" aria-live="polite"><?php echo esc_html((string) againbuy_cart_count()); ?></span></a>
        </div>
    </div>
    <nav class="site-nav wrap" aria-label="Main navigation">
        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">All products</a>
        <?php againbuy_category_links('site-category-links'); ?>
        <?php
        wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'menu_class' => 'menu', 'fallback_cb' => false, 'depth' => 1]);
        ?>
    </nav>
</header>
<main id="main" class="site-main wrap">
