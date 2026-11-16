# AgainBuy Commerce

Production WooCommerce ecommerce project for `againbuy.in`.

Production runs on a VM, not a serverless ZIP deployment. Nginx terminates HTTPS and proxies to a Docker Compose stack containing WordPress/WooCommerce, MariaDB and Redis. Product media is offloaded to Neviri S3. The repository contains the custom plugin, theme, deployment files and tests; database content and secrets are excluded.

## Structure

- `backend/plugin/againbuy-commerce-core/` — installable WooCommerce plugin; `backend/tests/` holds backend tests outside the served plugin.
- `backend/catalog/` — the 11 Muzzo products, their source images, and the repeatable import manifest.
- `frontend/theme/againbuy/` — installable mobile-first theme; `frontend/tests/` holds theme tests outside the served theme.
- `docker/` — local containers and backend-VM deployment configuration.
- `docs/` — architecture, implementation plan, testing and audit records.
- `.env.example` — all configuration keys; no real credentials.

## Local startup

1. Copy `.env.example` to `.env` and replace local database and admin passwords.
2. Run `powershell -File docker/install.ps1` from the project root.
3. Open `http://localhost:8080` and sign in at `/wp-admin`.

The installer may be run again safely. Without Razorpay test keys, payments remain disabled. Shipping rates and tax rules are set by the administrator in WooCommerce before checkout opens.

Country pricing uses the delivery country when the customer has selected one, otherwise WooCommerce IP geolocation. To activate IP detection, add a MaxMind GeoLite2 license key as `MAXMIND_LICENSE_KEY` in `.env`, then rerun the installer to download the country database. Set `TRUSTED_PROXY_CIDRS` to the Neviri ingress CIDR ranges so forwarded client IPs are accepted only from those proxies. Local development falls back to `DEFAULT_COUNTRY=IN` when IP lookup is unavailable. Customers do not see a currency switch.

Checkout address autofill is dormant by default: no location control, script, stylesheet, or API route is loaded. To activate it later, set `GOOGLE_GEOCODING_API_KEY` and `LOCATION_AUTOFILL_ENABLED=true` in `.env`, then redeploy. On HTTPS or localhost, a customer can choose **Use my current location**, grant browser permission, and review or edit the nearby address returned by Google. GPS cannot reliably identify an apartment or building number, so the customer must confirm those details. The key stays on the server; coordinates are sent to Google only after the customer clicks the button. See [WooCommerce MaxMind setup](https://woocommerce.com/document/maxmind-geolocation-integration/) and [Google reverse geocoding](https://developers.google.com/maps/documentation/geocoding/reverse-geocoding).

Customer registration is enabled on `/my-account/`. Product reviews are limited to logged-in customers who bought the product. The analytics-only account uses the `againbuy_analyst` role and opens `/wp-admin/admin.php?page=againbuy-sales`; it cannot edit products, orders or WooCommerce settings.

India delivery is a ₹120 flat rate from `INDIA_DELIVERY_CHARGE_INR`. International checkout has no shipping method until `INTERNATIONAL_DELIVERY_CHARGE_USD` is set and the installer is rerun; missing international pricing never becomes free shipping.

Google login uses Nextend Social Login. The installer downloads the pinned plugin but leaves it inactive until a Google OAuth client exists. Follow [docs/GOOGLE_LOGIN.md](docs/GOOGLE_LOGIN.md); OAuth secrets must never be committed.

The installer imports the 11 Muzzo-owned products and 38 distinct photos. To import them into an already installed local store, run `docker compose --profile tools run --rm cli wp eval-file /scripts/import-muzzo.php`. The 89 market research listings from the earlier test site are not stock. See [docs/MUZZO_CATALOG_IMPORT.md](docs/MUZZO_CATALOG_IMPORT.md).

## Checks

Run `powershell -File docker/run-checks.ps1` for the full local suite. It creates and removes temporary products and orders. The mobile browser check needs Chrome, Python 3, and `pip install -r docker/browser-requirements.txt`. Set `CHROME_PATH` if Chrome is installed elsewhere.

- `docker compose --profile tools run --rm cli wp eval-file /tests/backend/integration.php`
- `docker compose --profile tools run --rm cli wp eval-file /tests/backend/variations.php`
- `docker compose --profile tools run --rm cli wp eval-file /tests/backend/analytics.php`
- `docker compose --profile tools run --rm cli wp eval-file /tests/backend/phase4.php`
- `docker compose --profile tools run --rm cli wp eval-file /tests/backend/currency-location.php`
- `docker compose --profile tools run --rm cli wp eval-file /tests/backend/location.php`
- `powershell -File docker/test-shopper.ps1`
- `powershell -File docker/test-rate-limit.ps1`
- `powershell -File docker/test-mobile-browser.ps1`
- `powershell -File docker/test-checkout-double-submit.ps1`

Local mail is captured at `http://localhost:8025`. To use Neviri media storage, fill `S3_*` in `.env`: `S3_PUBLIC_URL` is the public host without the bucket path. The installer then activates the media-offload plugin. Local storage can be exercised with the optional `storage-test` Docker profile.

Run `powershell -File docker/test-storage.ps1` to test media offloading against the Docker S3 mock. It restores the normal local configuration afterward.

## Production deployment

The VM deployment uses `compose.yaml` with `compose.production.yaml`, host Nginx, and Let's Encrypt. Redis Object Cache is configured through `REDIS_*` and enabled by the installer. Run `sudo sh docker/production-smoke.sh` on the VM for read-only service checks. See [docs/PRODUCTION_DEPLOYMENT.md](docs/PRODUCTION_DEPLOYMENT.md).

## Current status

Phases 1–3 are implemented locally. Phase 4 has shipping zones, checkout rate limiting, email, sales reporting and guarded Razorpay setup. Phase 5 includes local mobile browser flows at 320, 390, 768 and 1280 px. The landing page shows the imported Muzzo catalog and a phone bottom navigation for Home, Shop, Cart and Account. Initial editable USD prices are reference-rate conversions. GA4 storefront events use the configured measurement ID. Razorpay test credentials passed an API check and a sandbox checkout handoff, but no payment was completed. Stock verification, shipping/tax rates, webhook delivery and Neviri connectivity still need owner-provided service configuration. This is not yet approved for public orders. See `docs/PROJECT_BRIEF.md` for the agreed scope and `docs/GA4.md` for event coverage.

See [docs/IMPLEMENTATION_PLAN.md](docs/IMPLEMENTATION_PLAN.md), [docs/PHASES_1_4_AUDIT.md](docs/PHASES_1_4_AUDIT.md), and [docs/PHASE_5_AUDIT.md](docs/PHASE_5_AUDIT.md).
