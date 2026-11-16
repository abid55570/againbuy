# AgainBuy implementation plan

## Scope

A production WooCommerce store with a custom mobile-first frontend, durable database, Neviri object storage, full administrator controls, Razorpay payments, international currency behavior, sales reporting and a final security audit.

WooCommerce will remain the source of truth for products, customers, stock, carts, taxes, shipping, orders, refunds and payment state.

## Architecture

- `frontend/theme/againbuy/`: custom responsive WooCommerce theme; `frontend/tests/` contains its CLI tests.
- `backend/plugin/againbuy-commerce-core/`: custom plugin for AgainBuy-specific commerce rules and reporting; `backend/tests/` contains its CLI tests.
- WordPress/WooCommerce: catalog, cart, checkout, accounts and order lifecycle.
- MariaDB: transactional store data.
- Neviri S3-compatible Object Storage: product and content media.
- Redis: object caching on local Docker and initially on the backend VM.
- Mailpit: free local email testing only; production uses configured SMTP.
- Razorpay: test mode during development, live keys supplied only at deployment.

## Currency

The administrator enters authoritative INR and USD prices for every product and variation. India defaults to INR. The United States and other countries default to USD. A visible currency selector lets customers correct detection.

The backend calculates product totals, coupons, delivery, tax and Razorpay amounts. Missing USD pricing prevents USD purchase instead of applying an unapproved exchange rate. Each order permanently stores its currency and exact amounts.

## International shipping and tax

The store will support independently configurable WooCommerce zones for:

1. India — domestic delivery methods and Indian tax classes.
2. United States — US delivery methods and approved state/country tax handling.
3. Selected countries or regions — region-specific delivery and tax rules.
4. Rest of world — explicit shipping price or unavailable status.

Taxes will be calculated from the configured billing/shipping address rule. Actual GST, sales-tax, VAT, customs and registration obligations must be supplied or approved by the business's accountant before launch.

## Admin rights

- **Administrator:** users, roles, plugins, payment credentials, tax, shipping, backups and all WooCommerce data.
- **Shop Manager:** products, stock, coupons, customers, orders, refunds and reports without access to infrastructure secrets.
- **Catalog Manager:** products, categories, descriptions, images, INR/USD prices and stock only.
- **Fulfilment Manager:** paid orders, delivery details, tracking updates and operational notes only.

Material product, price, order and settings changes will be attributed to an administrator in an audit log.

## Product administration

Authorized staff can manage product name, descriptions, status, categories, tags, variants, SKU, stock, INR/USD regular and sale prices, gallery, alt text, weight, dimensions, shipping class, tax class, delivery window and manually pinned related products.

## Sales and operational analytics

The admin panel will include WooCommerce's standard analytics plus an AgainBuy dashboard with graphical date-range reporting for:

- Gross sales, net revenue, tax, shipping, discounts, refunds and average order value.
- Orders by status and conversion funnel where measurable.
- Product, category and variation revenue/quantity.
- INR versus USD revenue and orders.
- India, US and other-country sales.
- Razorpay payment success, failure, pending, refund and payment-method breakdown.
- Stock, low-stock, out-of-stock and top/slow-moving products.
- CSV export using the current report filters.

Reports will respect admin capabilities and will not expose payment secrets or full sensitive customer/payment data.

## Customer flow

1. Detect country and select INR or USD; allow manual override.
2. Browse/search/filter products.
3. View product gallery, variants, stock, delivery/tax guidance and recommendations.
4. Add to the server-owned WooCommerce cart.
5. Enter billing and delivery addresses.
6. Calculate matching shipping methods and tax.
7. Review the server-calculated order.
8. Create the WooCommerce and Razorpay orders.
9. Verify browser return and Razorpay webhook signatures on the server.
10. Mark paid only after verified payment; reduce stock and send emails through WooCommerce.

## Recommendations

Product suggestions use administrator-pinned items first, followed by in-stock products from the same detailed category/attributes, parent category products, and popular/recent fallback products. Hidden, current and out-of-stock products are excluded.

## Build phases

1. Docker/VM foundation and repeatable WordPress/WooCommerce installation.
2. Backend plugin, roles, dual pricing, audit history and object storage.
3. Mobile-first frontend and product recommendations.
4. Shipping, tax, Razorpay, emails and admin analytics.
5. Automated unit, integration and mobile browser tests.
6. Accessibility, performance, backup/restore and security audit.
7. Deployment runbook, migration, monitoring and rollback readiness.

## Final audit

The release gate includes dependency and container scans, secret scanning, capability tests, CSRF/nonces, XSS escaping, SQL parameterization, upload validation, price/tax/shipping/payment tampering, Razorpay signature/replay/idempotency checks, login and checkout rate limiting, secure cookies/headers/TLS, backup restoration and OWASP ZAP review.

The final handoff will contain test evidence, an audit report, fixed findings and a documented residual-risk list. Live settlement cannot be certified until Razorpay test/live access and merchant activation are provided.

## Status

Phases 1–3 have local implementations and integration checks. Phase 4 has shipping-zone scaffolding, WooCommerce checkout rate limiting, local SMTP, sales reporting and a guarded Razorpay plugin. Phase 5 local integration and Chrome mobile browser checks pass. Live and test Razorpay transactions, real shipping/tax rules, Neviri connection, and the Phase 6–7 release gates remain open. Public orders are disabled until those gates pass.
