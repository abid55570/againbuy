# Muzzo catalog import

The local AgainBuy catalog contains 11 products marked as Muzzo-owned in the earlier Muzzo project. Names and INR prices came from that project and were checked against `muzzo.store` on 2026-11-01. The bottle price is ₹1,499 as supplied by the owner. The other 89 listings in the earlier interest-test site were market research picks and were not imported as stock.

The 38 distinct photos in `backend/catalog/media/` came from the owner's 17-photo bottle archive and the Muzzo product image URLs recorded in the earlier project. One bottle file duplicated another, so its gallery has 16 distinct views. Every imported product has a cover image; products with additional source photos have galleries. Category cards use those same product images.

`backend/catalog/muzzo-products.json` is the import manifest. `docker/import-muzzo.php` imports products and media through WP-CLI. `docker/install.sh` runs it on initial setup. Re-running it skips products already imported, preserving subsequent admin edits.

INR prices came from the owner and the earlier catalog. Initial USD prices were calculated on 2026-11-01 by dividing each INR price by the 2026-10-30 USD/INR reference rate of 95.2492 and rounding to cents. Source: https://www.msei.in/markets/currency/historical-data/rbireferenceratearchives . These are editable starting values, without shipping, tax, or margin adjustment. The importer fills a missing USD price but preserves subsequent admin edits. Stock quantities, shipping charges and tax rules were not supplied. A product without a USD price cannot be purchased in USD. The administrator should review all product details, stock, shipping, tax, and country prices before enabling payment.

Local checks passed: 11 products, 38 attached gallery images matching the manifest, no duplicate products on re-import, 16 homepage image URLs returning HTTP 200, responsive browser checks at 320/390/768/1280 px, and the full integration suite.
