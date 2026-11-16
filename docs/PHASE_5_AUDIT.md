# Phase 5 test record

Date: 2026-11-01. Scope: local Docker WordPress/WooCommerce build and headless Chrome on Windows.

## Results

- `docker/run-checks.ps1` passed end to end. It covers dual prices, variations, roles, audit records, sales reports, WooCommerce theme, repeated cart adds, invalid quantities, currency switching, missing USD price, duplicate checkout (HTTP 200 followed by 409 with one order), checkout rate limiting (HTTP 429), and local SMTP.
- `docker/test-mobile-browser.ps1` passed in Chrome at 320, 390, 768, and 1280 px. It checks horizontal overflow, header search, product title and INR price, add to cart, cart navigation, mobile checkout fields, and product search.
- Browser test fixtures were removed: zero products and zero orders remained after the suite. Python syntax compilation passed. `.env` files remain ignored by Git.

## Test correction

The browser harness initially treated WordPress's redirect from a unique search result to the product page as a failed search. The assertion now accepts either the search results page or the matching product page. Navigation checks wait for the new document rather than reading the previous page.

The fixture is identified by shared WordPress options. Run the shopper and browser scripts serially, as `docker/run-checks.ps1` does. Browser fixture cleanup runs even after setup or assertion failure, and a failed cleanup fails the script.

## Open release gates

Razorpay payment success/failure, callbacks, refunds, and replay tests require test credentials. Neviri media tests require its endpoint and credentials. Real shipping/tax rules and product content require business inputs. Accessibility, performance, backup/restore, and production security audits are Phase 6 work. The local browser check requires Chrome and Python with `docker/browser-requirements.txt`; it has not run on iOS Safari or Android Chrome devices.
