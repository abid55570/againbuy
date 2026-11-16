<?php
get_header();
$featured = wc_get_products(['limit' => 1, 'meta_key' => '_againbuy_source_id', 'meta_value' => 'copper-kit']);
$hero = $featured ? ($featured[0]->get_gallery_image_ids()[0] ?? $featured[0]->get_image_id()) : 0;
?>
<section class="hero" aria-labelledby="hero-title">
    <div class="hero-copy"><p class="eyebrow">Featured · Copper bottle set</p><h1 id="hero-title">Made to carry, made to keep.</h1><p>A hammered copper bottle with its own fabric pouch and cleaning brush. See every detail before you decide.</p><?php if ($featured) : ?><p class="hero-price"><?php echo wp_kses_post($featured[0]->get_price_html()); ?></p><?php endif; ?><div class="hero-actions"><a class="button button-primary" href="<?php echo esc_url($featured ? $featured[0]->get_permalink() : wc_get_page_permalink('shop')); ?>">Explore the bottle <span aria-hidden="true">↗</span></a><a class="hero-secondary" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Shop all products</a></div></div>
    <?php if ($hero) : ?><a class="hero-art" href="<?php echo esc_url($featured[0]->get_permalink()); ?>" aria-label="View <?php echo esc_attr($featured[0]->get_name()); ?>"><?php echo wp_get_attachment_image($hero, 'large', false, ['alt' => $featured[0]->get_name()]); ?></a><?php else : ?><div class="hero-art" aria-hidden="true"><span>AB</span><div class="hero-circle"></div></div><?php endif; ?>
</section>
<?php
$categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 6, 'exclude' => [get_option('default_product_cat')]]);
if (!is_wp_error($categories) && $categories) : ?>
<section class="section"><div class="section-heading"><div><p class="eyebrow">Browse</p><h2>Find what you came for</h2></div></div><div class="category-grid">
<?php foreach ($categories as $category) : $thumbnail = get_term_meta($category->term_id, 'thumbnail_id', true); ?>
<a class="category-card" href="<?php echo esc_url(get_term_link($category)); ?>"><?php echo $thumbnail ? wp_get_attachment_image($thumbnail, 'medium') : '<span class="category-placeholder" aria-hidden="true">' . esc_html(mb_substr($category->name, 0, 1)) . '</span>'; ?><strong><?php echo esc_html($category->name); ?></strong><span><?php echo esc_html((string) $category->count); ?> products</span></a>
<?php endforeach; ?></div></section>
<?php endif; ?>
<section class="section"><div class="section-heading"><div><p class="eyebrow">In the shop</p><h2>Browse the collection</h2></div><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">View all products <span aria-hidden="true">→</span></a></div>
<?php echo do_shortcode('[products limit="12" columns="4" orderby="date" order="DESC"]'); ?>
</section>
<?php get_footer(); ?>
