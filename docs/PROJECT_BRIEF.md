# AgainBuy project brief

The owner wants a production WooCommerce shop in `B:\Workspace\againbuy`, with backend and frontend folders, a phone-first storefront, product details and related products, cart, account, shipping and tax rules for India, the US and other countries, INR and USD prices controlled by admin, Razorpay payments, Neviri object storage, sales reports, GA4 event tracking, and complete testing and security review before public orders.

The current catalog imports 11 owner-associated Muzzo products and 38 genuine source photos. Research products from the earlier audience-test site are excluded. The bottle's INR price is ₹1,499. Initial USD prices are reference-rate conversions that admin can edit. The admin manages products, descriptions, photos, prices, stock, shipping and tax settings, orders and reports. The homepage displays the catalog, a linked featured bottle image, and phone bottom tabs for Home, Shop, Cart and Account.

Local Docker runs WordPress, WooCommerce, MariaDB, Redis and Mailpit. S3-compatible storage is intended for Neviri at deployment. Razorpay test credentials are configured locally and a sandbox checkout handoff is verified. Do not claim payment validation or production readiness until successful and failed gateway transactions, webhook replay, refunds, shipping/tax rules, storage access, and final security checks pass. `.env.example` lists external configuration; secrets belong in `.env`, not source code.

GA4 uses the existing Muzzo stream locally. Admin should create a separate AgainBuy stream for deployment and put its measurement ID in `.env`. A public domain must be verified with GA4 DebugView after deployment.
