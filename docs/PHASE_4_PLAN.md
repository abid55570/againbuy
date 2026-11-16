# Phase 4: checkout and operations

1. Keep WooCommerce responsible for shipping zones, tax rules, orders, stock, and emails. Create India and US zones without guessing business rates; no matching rate means no shippable checkout.
2. Install the official Razorpay WooCommerce gateway at a pinned, patched version. Keep it disabled until the owner supplies test credentials. Live mode must require an explicit deployment change.
3. Apply a checkout request limit and reject duplicate order submissions while a request is in progress. Verify order totals are server-calculated.
4. Use WooCommerce Analytics for sales, products and revenue; add an AgainBuy payment-status summary only after Razorpay test transactions are available.
5. Test shopper mistakes: repeated add-to-cart, invalid quantity, currency changes, duplicate checkout submission, failed payment, replayed callback and stale session.

Tax rates, delivery charges, international merchant activation and Razorpay credentials are business inputs. No live charge will be attempted with missing values.
