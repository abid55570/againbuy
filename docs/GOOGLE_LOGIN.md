# Google login

AgainBuy uses Nextend Social Login and Register, pinned by `docker/install.sh`. It remains inactive until Google OAuth credentials are available, so the storefront never shows a broken login button.

1. In Google Cloud, create an OAuth 2.0 **Web application** client for AgainBuy and configure the consent screen.
2. In WordPress admin, activate **Nextend Social Login and Register**.
3. Open **Settings → Nextend Social Login → Google → Getting Started**. Copy the exact redirect URI shown there into the Google OAuth client's authorized redirect URIs.
4. Enter the client ID and client secret in the plugin's Google provider settings, verify the provider, then enable it.
5. In a private browser, test a new Google account, an existing customer with the same email, logout, login again, checkout, and account privacy behavior.

Google requires the configured redirect URI to exactly match the request. Do not put OAuth secrets in source files, `.env.example`, documentation, Git commits or chat. Email/password registration works independently while Google login is disabled.
