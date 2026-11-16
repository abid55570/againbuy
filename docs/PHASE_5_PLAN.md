# Phase 5: automated browser and regression tests

Goal: make the local storefront repeatably testable at common mobile widths and verify real shopper navigation, product display, cart actions, and checkout safeguards.

1. Add a disposable browser test fixture. Verify creation and cleanup leave no product or order records.
2. Drive local Chrome at 320, 390, 768, and 1280 px. Assert no horizontal overflow, usable header/search/navigation, product name and price, and working add-to-cart and cart navigation. Capture failures with clear assertions.
3. Add the browser check to the local test runner. Keep the existing Store API duplicate-submit, rate-limit, currency, role, reporting, SMTP, and storage checks.
4. Run the full suite and audit failures. Record browser/runtime prerequisites and remaining Razorpay/Neviri gates.

Ruling: use Chrome DevTools Protocol through the Python `websockets` package already present on this workstation. This avoids adding browser tooling to the production containers. The test runner will state its prerequisites clearly; CI must install the listed dev dependency and Chrome.
