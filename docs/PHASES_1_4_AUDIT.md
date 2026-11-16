# AgainBuy audit through Phase 4

Date: 2026-11-01. Scope: local Docker build, custom WooCommerce plugin and theme, checkout safeguards, reporting, email, and S3-compatible media offload. This is a local readiness audit, not approval for public orders.

## Passed

- `docker/run-checks.ps1`: product and variation pricing in INR/USD; missing USD price blocks purchase; catalog and fulfilment role access; product audit; sales report and CSV; theme; cart quantity and currency changes; invalid and over-stock quantities; duplicate checkout (HTTP 200 then 409, one order); checkout rate limit (HTTP 429); Mailpit SMTP.
- `docker/test-storage.ps1`: WordPress upload offloaded to S3 mock, rewritten media URL returned HTTP 200 with expected bytes; test restored normal local configuration.
- `docker compose config --quiet` and PHP lint for every custom plugin/theme PHP file passed.
- `.env` and `.env.storage-test` are Git-ignored. Source scan found no real Razorpay key or embedded service password. Admin credentials are configured through environment variables.
- Manual responsive check at 320, 390, 768, and 1280 px found no horizontal overflow and primary touch targets of at least 48 px.

## Findings fixed during this phase

- Fulfilment Manager could edit an unpaid order. The role capability check now rejects direct read/edit/delete requests for unpaid orders. The regression test creates paid and unpaid orders and checks both.
- A second rapid checkout submission created no second order in the local Store API test.

## Required before public orders

- Add verified products, owned images, INR and USD prices, stock, delivery charges, and applicable tax rules. The catalog contains no invented products.
- Supply Razorpay test credentials and run actual successful, failed, retried, webhook replay, refund, and INR/USD payment tests. The official gateway is installed but disabled without credentials. Live mode is guarded by `APP_ENV=production`.
- Supply Neviri S3 credentials and public media host, then test upload, retrieval, deletion, and access controls against Neviri. The S3 mock result does not establish Neviri connectivity.
- Confirm shipping countries, serviceability, rates, tax treatment, returns policy, transactional email provider, and the merchant's ability to accept international payments. India and US shipping zones exist with no guessed rates.
- Complete a production security, accessibility, performance, backup/restore, and browser/device audit. Review whether fulfilment staff should see unpaid orders in the list; direct order operations are denied, but list visibility has not been restricted.

No live payment or public deployment was attempted.
