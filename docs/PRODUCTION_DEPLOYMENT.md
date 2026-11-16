# Production deployment status

Deployment date: 2026-11-02 (Asia/Calcutta)

## Live environment

- URL: `https://againbuy.in`
- VM: Ubuntu 24.04 LTS, 2 vCPU, 4 GB RAM, 25 GB disk
- Public IP: `173.231.193.174`
- Edge: host Nginx on ports 80 and 443
- TLS: Let's Encrypt with an active systemd renewal timer; renewal simulation passed
- Runtime: Docker Compose with WordPress/PHP 8.3, MariaDB 11.4 and Redis 7.4
- Persistence: Docker volumes for WordPress, MariaDB and Redis
- Exposure: WordPress binds to `127.0.0.1:8080`; MariaDB and Redis have no host port
- Cache: Redis Object Cache 2.8.0 reports connected, valid drop-in and PONG
- Catalog: 11 published owned products
- Integrations: GA4 configured; Razorpay enabled in Test Mode
- Security headers: HSTS, nosniff, SAMEORIGIN, referrer policy and permissions policy

## Verification completed

- Nginx and WordPress return HTTP 200 through HTTPS.
- Home, shop, cart, account, login and a real product page load publicly.
- Customer signup is available on My Account; product reviews require a verified purchase.
- The cart count updates after AJAX additions and an accessible confirmation toast appears.
- Analytics-only staff use `/wp-admin/admin.php?page=againbuy-sales` with the restricted `againbuy_analyst` role.
- India delivery is ₹120. International shipping remains unavailable until `INTERNATIONAL_DELIVERY_CHARGE_USD` is configured.
- GA4 tag, favicon, product links and add-to-cart controls render.
- Live Chrome checks pass at 320, 390, 768 and 1280 pixels with no horizontal overflow or broken visible images.
- The shop shows 11 products; product detail includes price, cart form, multiple photos and related items.
- Docker services are healthy and configured to restart unless stopped.
- Only SSH, HTTP and HTTPS listen publicly; WordPress listens on loopback.

## Required before live orders

- Replace the previously shared Razorpay test credentials, configure the new test keys and webhook, and complete success, failure, retry and refund tests.
- Configure real shipping rates, taxes, stock, store address and policy pages in WooCommerce.
- Configure production SMTP and verify delivery to a real inbox.
- Provide Neviri object-storage bucket and credentials if media offload is required; current media is stored in the persistent WordPress volume.
- Add MaxMind credentials for reliable IP-country detection and verify INR/USD behavior from India and another country.
- Configure an off-VM backup, then test database and media restoration.
- Add a real administrator email. The current placeholder was not used for Let's Encrypt registration, so certificate expiry emails are unavailable; automatic renewal is active and tested.

## Operations

Run the read-only VM smoke test:

```bash
cd /opt/againbuy
sudo sh docker/production-smoke.sh
```

Inspect services and logs:

```bash
sudo docker compose -f compose.yaml -f compose.production.yaml ps
sudo docker compose -f compose.yaml -f compose.production.yaml logs --tail=100 wordpress db redis
```

The public browser smoke test runs from a workstation with Chrome and Python dependencies:

```powershell
$env:AGAINBUY_TEST_URL='https://againbuy.in'
python docker/test-production-browser.py
```
