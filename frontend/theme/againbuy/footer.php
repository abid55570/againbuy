</main>
<footer class="site-footer">
    <div class="wrap footer-grid">
        <div><a class="brand" href="<?php echo esc_url(home_url('/')); ?>">again<span>buy</span><i>.</i></a><p>Browse our current selection of bags, belts, wallets and the copper bottle set.</p></div>
        <div><h2>Explore</h2><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Shop all</a><a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">My account</a></div>
        <div><h2>Help</h2><a href="<?php echo esc_url(wc_get_page_permalink('cart')); ?>">Your cart</a><a href="<?php echo esc_url(wc_get_page_permalink('checkout')); ?>">Checkout</a></div>
    </div>
    <div class="wrap footer-bottom">© <?php echo esc_html(gmdate('Y')); ?> AgainBuy</div>
</footer>
<nav class="mobile-tabs" aria-label="Mobile navigation">
    <a href="<?php echo esc_url(home_url('/')); ?>" <?php if (is_front_page()) { echo 'aria-current="page"'; } ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10 12 3l9 7v10h-6v-6H9v6H3z"/></svg><span>Home</span></a>
    <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" <?php if (is_shop() || is_product() || is_product_taxonomy()) { echo 'aria-current="page"'; } ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h18v16H3zM3 10h18M9 10v10M15 10v10"/></svg><span>Shop</span></a>
    <a href="<?php echo esc_url(wc_get_cart_url()); ?>" <?php if (is_cart()) { echo 'aria-current="page"'; } ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 4h3l2 11h12l2-8H6M9 20h.01M18 20h.01"/></svg><span class="mobile-cart-label">Cart<?php $cart_count = againbuy_cart_count(); if ($cart_count) { echo ' (' . esc_html((string) $cart_count) . ')'; } ?></span></a>
    <a class="mobile-account<?php echo is_user_logged_in() ? ' is-signed-in' : ''; ?>" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" <?php if (is_account_page()) { echo 'aria-current="page"'; } ?>><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3-7 8-7s8 3 8 7"/></svg><span><?php echo is_user_logged_in() ? 'My account' : 'Sign in'; ?></span><?php if (is_user_logged_in()) : ?><span class="screen-reader-text">Signed in</span><?php endif; ?></a>
</nav>
<?php wp_footer(); ?>
</body></html>
