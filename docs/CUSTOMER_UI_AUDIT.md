# Customer interface review — 2026-11-17

The signed-in account uses a custom dashboard, linked order/address/account cards, desktop navigation and a horizontally scrolling phone menu. WooCommerce still owns account forms, validation, order access and password changes. Address cards, password fields, empty states and cart/checkout spacing use shared theme styles.

The production browser suite covers home, shop, category, product search, product details, empty and populated cart, checkout, sign-in, signup and password reset at 320, 390, 768 and 1280 px. Signed-in coverage uses the configured restricted analytics account and opens dashboard, orders, downloads, addresses, billing/shipping address forms and account details at those widths. Tests check visibility, horizontal overflow, dashboard links, sticky navigation, carousel rotation and cart feedback. They do not submit account changes or payments. Dashboard screenshots are written to ignored `artifacts/` for visual review.

These checks do not establish successful email delivery, Google OAuth completion, live payments, or the appearance of populated customer order details. Those need their respective configured services and appropriate test orders.
