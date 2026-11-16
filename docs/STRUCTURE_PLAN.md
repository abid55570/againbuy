# Application layout

- `backend/plugin/againbuy-commerce-core/`: installable WordPress plugin. Only runtime PHP files live here.
- `backend/tests/`: backend tests and temporary fixtures, mounted into the CLI container only.
- `frontend/theme/againbuy/`: installable WooCommerce theme. Only runtime theme files live here.
- `frontend/tests/`: theme integration test, mounted into the CLI container only.
- `docker/`: local setup and browser/HTTP test runners.
- `docs/`: plans and audit records.

Update Compose bind mounts and every WP-CLI test path, then verify installation, PHP lint, browser flow, and the full suite. Existing database and WordPress volumes stay in place.
