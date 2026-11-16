# Backend

`plugin/againbuy-commerce-core/` contains the installable WordPress/WooCommerce plugin. `tests/` contains CLI integration tests and temporary fixtures; it is not served by WordPress.

Planned responsibilities:

- INR and USD product and variation prices.
- Country/currency selection and server-side total validation.
- Product, inventory, delivery, tax and recommendation metadata.
- Razorpay order lifecycle, callback verification and idempotent webhooks.
- Neviri S3-compatible object-storage configuration.
- Admin roles, capability enforcement and change audit log.
- Sales dashboards for revenue, products, payments, currencies and countries.
- Structured logs, scheduled tasks and protected integration endpoints.

Implemented: INR/USD pricing, restricted staff roles, durable product audit, related-product selection, local/production SMTP configuration, Razorpay environment guard, and sales reporting. Razorpay checkout and Neviri connectivity require service credentials for end-to-end verification.
