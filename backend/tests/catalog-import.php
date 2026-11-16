<?php
$manifest = json_decode(file_get_contents('/catalog/muzzo-products.json'), true, 512, JSON_THROW_ON_ERROR);
$products = wc_get_products(['limit' => -1, 'meta_key' => '_againbuy_source_id', 'return' => 'objects']);
if (count($products) !== count($manifest)) { throw new RuntimeException('Muzzo product count does not match the catalog manifest'); }
foreach ($manifest as $item) {
    $matches = array_values(array_filter($products, static fn($product) => $product->get_meta('_againbuy_source_id') === $item['source_id']));
    if (count($matches) !== 1) { throw new RuntimeException('Missing or duplicate product: ' . $item['source_id']); }
    $product = $matches[0];
    if (!isset($item['usd_price']) || (float) $product->get_meta('_againbuy_usd_price') <= 0) {
        throw new RuntimeException('USD price is unavailable: ' . $item['source_id']);
    }
    if ($product->get_status() !== 'publish' || (float) $product->get_regular_price('edit') !== (float) $item['inr_price'] || !$product->get_image_id()) {
        throw new RuntimeException('Product is incomplete: ' . $item['source_id']);
    }
    $images = array_merge([$product->get_image_id()], $product->get_gallery_image_ids());
    if (count($images) !== count($item['images'])) { throw new RuntimeException('Gallery image count differs: ' . $item['source_id']); }
    foreach ($images as $image_id) {
        if (!wp_attachment_is_image($image_id) || !is_file(get_attached_file($image_id))) {
            throw new RuntimeException('Image file is unavailable: ' . $item['source_id']);
        }
    }
    if ($item['source_id'] === 'copper-kit' && count($product->get_gallery_image_ids()) < 4) {
        throw new RuntimeException('Bottle gallery is incomplete');
    }
}
echo 'Muzzo catalog import checks passed: ' . count($products) . " products.\n";
