# Phases 2 and 3 implementation

## Phase 2: commerce backend

1. Add a WooCommerce-dependent plugin with separate catalog and fulfilment roles.
2. Add per-product USD prices. INR remains WooCommerce's authoritative base price. A missing USD price blocks USD purchasing.
3. Select currency from customer country with a manual selector. Apply the same currency to displayed prices, cart calculations, and the eventual order.
4. Record product changes in an admin-only audit log without storing payment credentials or customer details.
5. Connect WordPress media to Neviri object storage only when credentials are supplied; keep local media for development.

## Phase 3: mobile storefront

1. Add a responsive WooCommerce theme for home, catalog, product, cart, checkout, and account routes.
2. Use WooCommerce templates and cart forms so stock and totals stay server-owned.
3. Show pinned related products first, then in-stock products from the same category.
4. Check touch targets, small-screen layout, gallery, cart, and failure states in a browser.

## Verification gates

- Plugin and theme PHP syntax checks.
- Real WordPress tests for roles, price switching, missing USD price, and recommendations.
- HTTP and mobile browser checks of catalog, product, cart, and account navigation.
- Human-error checks: repeated Add to cart taps, invalid quantity, currency switch with a cart, and stale product URL.

Razorpay, shipping/tax rules, checkout rate limits, and double-payment behavior are Phase 4. No payment is accepted in this phase.
