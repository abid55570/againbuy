<?php
$catalog = json_decode(file_get_contents('/catalog/muzzo-products.json'), true, 512, JSON_THROW_ON_ERROR);
if (count($catalog) !== 11) { throw new RuntimeException('Unexpected Muzzo catalog size'); }
foreach ($catalog as $item) {
    if (empty($item['source_id']) || empty($item['images']) || !is_numeric($item['inr_price']) || !is_numeric($item['usd_price']) || $item['usd_price'] <= 0) {
        throw new RuntimeException('Incomplete catalog record');
    }
    foreach ($item['images'] as $image) {
        if (!str_starts_with($image, 'media/') || !is_file('/catalog/' . $image)) {
            throw new RuntimeException('Missing catalog image: ' . $image);
        }
    }
}
require_once ABSPATH . 'wp-admin/includes/image.php';

foreach ($catalog as $item) {
    $existing = wc_get_products(['limit' => 1, 'meta_key' => '_againbuy_source_id', 'meta_value' => $item['source_id']]);
    if ($existing && $existing[0]->get_meta('_againbuy_usd_price') === '') {
        $existing[0]->update_meta_data('_againbuy_usd_price', $item['usd_price']);
        $existing[0]->save();
    }
    if ($existing && $existing[0]->get_image_id()) {
        echo 'Exists: ' . $item['source_id'] . "\n";
        continue;
    }
    $term = term_exists($item['category'], 'product_cat');
    if (!$term) { $term = wp_insert_term($item['category'], 'product_cat'); }
    if (is_wp_error($term)) { throw new RuntimeException($term->get_error_message()); }
    $term_id = (int) (is_array($term) ? $term['term_id'] : $term);
    $product = $existing ? $existing[0] : new WC_Product_Simple();
    if (!$existing) {
        $product->set_name($item['name']);
        $product->set_slug('muzzo-' . $item['source_id']);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_regular_price((string) $item['inr_price']);
        $product->set_category_ids([$term_id]);
        $product->set_description(wp_kses_post('<p>' . esc_html($item['description']) . '</p><ul>' . implode('', array_map(static fn($fact) => '<li>' . esc_html($fact) . '</li>', $item['facts'])) . '</ul>'));
        $product->update_meta_data('_againbuy_source_id', $item['source_id']);
        $product->update_meta_data('_againbuy_usd_price', $item['usd_price']);
        $product->save();
    }
    $attachment_ids = [];
    foreach ($item['images'] as $index => $image) {
        $path = '/catalog/' . $image;
        $upload = wp_upload_bits(basename($path), null, file_get_contents($path));
        if ($upload['error']) { throw new RuntimeException($upload['error']); }
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => wp_check_filetype($upload['file'])['type'],
            'post_title' => $item['name'],
            'post_status' => 'inherit',
            'guid' => $upload['url'],
        ], $upload['file'], $product->get_id(), true);
        if (is_wp_error($attachment_id)) { throw new RuntimeException($attachment_id->get_error_message()); }
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $item['name'] . ($index ? ' — view ' . ($index + 1) : ''));
        $attachment_ids[] = $attachment_id;
    }
    $product->set_image_id(array_shift($attachment_ids));
    $product->set_gallery_image_ids($attachment_ids);
    $product->save();
    if (!get_term_meta($term_id, 'thumbnail_id', true)) {
        update_term_meta($term_id, 'thumbnail_id', $product->get_image_id());
    }
    echo 'Imported: ' . $item['source_id'] . "\n";
}
