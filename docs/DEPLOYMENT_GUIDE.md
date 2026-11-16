# AgainBuy deployment guide

This guide covers the current WooCommerce application, not the earlier Muzzo traffic-test site. The `backend/` plugin and `frontend/` theme are mounted into **one WordPress/PHP web process**. MariaDB stores products, users, carts and orders. Product media can be offloaded to Neviri S3-compatible storage. Nginx can terminate HTTPS in front of WordPress.

## 1. Choose a deployment path

| Path | Current repository status | What runs the app |
| --- | --- | --- |
| Neviri App Deployment | **Not a direct ZIP upload yet.** This repository has a multi-service `compose.yaml`, bind-mounted plugin/theme, an installer and persistent WordPress/database volumes. A single app deployment needs a prepared WordPress image or build, a persistent database, persistent `wp-content`, object storage, and a one-time install step. Confirm Neviri's ZIP/Docker build and persistent-volume options before packaging. | Neviri's PHP/container runtime |
| Neviri or other Ubuntu VM | The closest path to the tested local setup. Follow sections 2–9. | Docker Compose on the VM, Nginx on the host |

**PM2 is not used for this application.** PM2 supervises Node processes; this code is WordPress/PHP. Docker restart policies supervise its containers. Installing Node and PM2 would not start the storefront. The repository also has no separately built JavaScript frontend to deploy: `frontend/theme/againbuy` is a WordPress theme.

Neviri advertises Git-connected app builds, environment variables and automatic TLS, but that does not establish that a raw ZIP containing this Compose project will run all its services or keep its data. Ask Neviri support whether the chosen plan supports custom Docker images, persistent volumes, private MySQL networking, scheduled jobs and one-time WP-CLI commands. If it does, package the plugin/theme into an image, connect a managed MySQL database, attach durable `wp-content` storage, add the environment settings, and run the installer once. This packaging is **not yet implemented or tested** in this repository. Do not upload the current project ZIP as if it were a ready single-service app.

## 2. Before touching the VM

Use a fresh **Ubuntu 24.04 LTS** VM with a public IPv4 address, an SSH key, enough disk for the MariaDB and WordPress volumes, and a domain you control. Examples below use `againbuy.in`, `ubuntu` and `/opt/againbuy`; replace them for your VM. Keep the Neviri dashboard open for firewall and DNS changes.

Have these ready: private GitHub repository containing this code, Neviri object-storage endpoint/bucket/access keys/public media host, production SMTP service, a separate AgainBuy GA4 measurement ID, MaxMind license key for IP-based country detection, and Razorpay **test** keys plus a webhook secret. Actual inventory, shipping rates, taxes, merchant eligibility and payment tests are still launch gates. Do not switch to live payments merely because the site opens.

On your Windows computer, open PowerShell:

```powershell
ssh-keygen -t ed25519 -f "$HOME\.ssh\againbuy_vm" -C "againbuy-vm"
Get-Content "$HOME\.ssh\againbuy_vm.pub"
```

Add the printed **public** key to the VM in Neviri. Never upload or paste the private key. Allow inbound TCP 22 from your IP and TCP 80/443 from the internet in Neviri's VM firewall. Connect:

```powershell
ssh -i "$HOME\.ssh\againbuy_vm" ubuntu@VM_PUBLIC_IP
```

If the VM's default SSH user differs, use the username shown in Neviri. The remaining commands run **inside the VM** unless marked Windows.

## 3. Connect the VM to GitHub

Create an empty **private** GitHub repository named `againbuy`. On your Windows computer, in PowerShell, inspect the files before the first push:

```powershell
Set-Location B:\Workspace\againbuy
git status --short
git ls-files .env .env.storage-test
git remote -v
```

The `git ls-files` command must print nothing for those secret files. If it lists either, remove it from Git tracking before pushing. Do not commit database dumps or private keys. If the source is already committed and the remote is missing, connect and push it:

```powershell
git remote add origin https://github.com/GITHUB_OWNER/againbuy.git
git push -u origin HEAD:main
```

If `origin` already exists, inspect its URL and use it instead of adding another. GitHub may prompt for browser sign-in. On the VM, create a separate read-only deploy key:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/againbuy_github -C againbuy-vm-deploy
cat ~/.ssh/againbuy_github.pub
```

Add that public key in GitHub repository **Settings → Deploy keys**, with write access **off**. Then:

```bash
cat >> ~/.ssh/config <<'EOF'
Host github-againbuy
  HostName github.com
  User git
  IdentityFile ~/.ssh/againbuy_github
  IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config
ssh -T git@github-againbuy
sudo mkdir -p /opt/againbuy
sudo chown "$USER:$USER" /opt/againbuy
git clone git@github-againbuy:GITHUB_OWNER/againbuy.git /opt/againbuy
cd /opt/againbuy
```

Replace `GITHUB_OWNER`. GitHub's SSH greeting is enough to confirm authentication; it does not provide a shell. For later releases, use `git pull --ff-only` from this directory, review changes, back up data, then recreate affected containers. Do not run `docker compose down -v`: `-v` removes the database and WordPress volumes.

## 4. Install Docker, Nginx and Certbot

Follow Docker's current [Ubuntu installation instructions](https://docs.docker.com/engine/install/ubuntu/) for its official apt repository and Compose plugin. Verify:

```bash
docker --version
docker compose version
sudo systemctl enable --now docker
```

Use `sudo docker compose` below unless you deliberately add your user to the `docker` group; that group grants root-equivalent control. Install host Nginx and Certbot:

```bash
sudo apt update
sudo apt install -y nginx snapd git
sudo snap install core
sudo snap refresh core
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/local/bin/certbot
sudo systemctl enable --now nginx
```

If `certbot` is already on `PATH`, skip the symlink. Confirm Nginx opens on the VM's public IP before proceeding.

## 5. Configure environment and persistent services

```bash
cd /opt/againbuy
cp .env.example .env
chmod 600 .env
nano .env
```

Set `APP_ENV=production`, `APP_URL=https://againbuy.in`, `WORDPRESS_URL=https://againbuy.in`, `WORDPRESS_PORT=127.0.0.1:8080`, `WORDPRESS_DEBUG=false`, strong unique `DB_PASSWORD` and `DB_ROOT_PASSWORD`, all eight unique `WORDPRESS_*` salts, and unique `WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`, `WP_ADMIN_EMAIL`. Set `MAIL_FROM_ADDRESS` to an address approved by your SMTP provider. Set `RAZORPAY_MODE=test` until the payment release gate passes. Leave `LOCATION_AUTOFILL_ENABLED=false` and `GOOGLE_GEOCODING_API_KEY` blank until you choose to enable that feature. Set `DEFAULT_COUNTRY=IN` as a fallback and `MAXMIND_LICENSE_KEY` for real IP country detection.

For Neviri media storage, fill `S3_ENDPOINT`, `S3_PUBLIC_URL`, `S3_REGION`, `S3_BUCKET`, `S3_ACCESS_KEY`, `S3_SECRET_KEY` and `S3_PATH_STYLE` from the Neviri dashboard. `S3_PUBLIC_URL` is the public media host **without** the bucket suffix. Fill production `SMTP_*`, `GA4_MEASUREMENT_ID`, `RAZORPAY_*` and `TRUSTED_PROXY_CIDRS` only with values you have verified. The current Compose file uses `db` as WordPress's database host; `DB_HOST`/`DB_PORT` in `.env.example` are **not wired to Compose**. Moving to an external DB needs a Compose change first.

The repository includes `compose.production.yaml`, which makes the app, database and Redis restart after reboot:

```yaml
services:
  db:
    restart: unless-stopped
  wordpress:
    restart: unless-stopped
  redis:
    restart: unless-stopped
```

The application uses the internal Compose names `db:3306` and `redis:6379`. The installer activates Redis Object Cache and `wp redis status` must report `Connected` and `PONG`. Do not expose Redis port 6379 or MariaDB port 3306 to the internet. The Docker named volumes `db_data`, `wp_data` and `redis_data` remain on this VM; arrange real backups before accepting orders.

Check the configuration without printing secrets:

```bash
sudo docker compose -f compose.yaml -f compose.production.yaml config -q
sudo docker compose -f compose.yaml -f compose.production.yaml up -d db redis wordpress
sudo docker compose -f compose.yaml -f compose.production.yaml ps
```

Do not start `mailpit` for production mail; set a real `SMTP_HOST` in `.env`. The WordPress port must bind to `127.0.0.1`, never the public interface. Check `sudo ss -ltnp | grep 8080`.

## 6. DNS, Nginx and HTTPS

Set an `A` record for `againbuy.in` to the VM public IP. If you use `www`, add a `www` record and choose one canonical hostname. Wait until `dig +short againbuy.in` returns the VM IP (`sudo apt install -y dnsutils` if `dig` is missing). On the VM create the file with `sudo nano /etc/nginx/sites-available/againbuy` and paste:

```nginx
server {
    listen 80;
    server_name againbuy.in;
    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
sudo rm -f /etc/nginx/sites-enabled/default
sudo ln -s /etc/nginx/sites-available/againbuy /etc/nginx/sites-enabled/againbuy
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d againbuy.in
sudo certbot renew --dry-run
```

Certbot will add the HTTPS server and redirect. Verify `curl -I https://againbuy.in` and an admin login; check for mixed HTTP assets. The official WordPress Docker image recognizes the Nginx `X-Forwarded-Proto` header when its environment-based config is used. If another proxy/CDN is placed in front, set trusted proxy CIDRs accurately; do not trust arbitrary forwarded client-IP headers.

## 7. Install WordPress, backend and frontend

The repository installer installs WordPress/WooCommerce and required plugins, activates `backend/plugin/againbuy-commerce-core` and `frontend/theme/againbuy`, configures GA4/geolocation/payment settings, and imports the 11 Muzzo-owned products and their images. Run it **once after the database and HTTPS URL are ready**:

```bash
cd /opt/againbuy
sudo docker compose -f compose.yaml -f compose.production.yaml --profile tools run --rm cli sh /scripts/install.sh
sudo docker compose -f compose.yaml -f compose.production.yaml --profile tools run --rm cli wp core is-installed
sudo docker compose -f compose.yaml -f compose.production.yaml --profile tools run --rm cli wp option get home
```

The final URL should be `https://againbuy.in`. If it is not, stop and correct the WordPress `home`/`siteurl` settings before browsing further. The importer skips existing products on repeat runs, but the installer also updates WooCommerce settings; review those effects before rerunning it on a live store. Admin login: `https://againbuy.in/wp-admin/` with the credentials **you put in `.env` at first install**. Later changing `WP_ADMIN_PASSWORD` in `.env` does not reset the WordPress user's password; use WP-CLI or the admin account screen to rotate it.

## 8. External services and order readiness

1. In admin, set the real store address, stock, product descriptions, INR and USD prices, India/US/other-country shipping zones, charges, taxes and policy pages. Verify a cart for each destination. Unknown shipping or taxes must not silently become free.
2. Upload a new product image and confirm its public URL uses Neviri storage; test retrieval and deletion. Check that the media host is reachable over HTTPS. Keep a backup of original uploads until offloading is verified.
3. Send a real order email through SMTP to an inbox you control. Mailpit is only for local testing.
4. In Razorpay **Test Mode**, copy the webhook URL displayed by the installed WooCommerce gateway into Razorpay's dashboard and use the same webhook secret as `.env`. Test success, failure, duplicate click, delayed callback, webhook retry, refund and INR/USD amounts. The exact webhook URL comes from the gateway settings, not from a guessed path.
5. Create a separate AgainBuy GA4 web stream. Put its `G-...` ID in `.env`, rerun the relevant installer step if necessary, and verify `view_item`, `add_to_cart`, `begin_checkout` and `purchase` in GA4 DebugView on the public domain. Do not use the old Muzzo test stream for production reporting.
6. For later GPS address autofill, add a server-side Google Geocoding key and set `LOCATION_AUTOFILL_ENABLED=true`, recreate WordPress, and test it on HTTPS. Until then the feature remains absent from checkout.

Environment changes require recreating containers, not just `docker compose restart`:

```bash
sudo docker compose -f compose.yaml -f compose.production.yaml up -d --force-recreate wordpress
```

## 9. Updates, backups and checks

Before each release, back up MariaDB **and** the `wp_data` volume plus any not-yet-offloaded media. Keep copies outside the VM, encrypt them, and rehearse a restore on a spare VM. Do not rely on a Git clone or Redis AOF as an order backup. Database backup example (run inside `/opt/againbuy`; creates a root-readable file containing customer data):

```bash
umask 077
sudo docker compose -f compose.yaml -f compose.production.yaml exec -T db sh -c 'exec mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" --single-transaction "$MARIADB_DATABASE"' > "againbuy-db-$(date +%F).sql"
```

For a release:

```bash
cd /opt/againbuy
git pull --ff-only
sudo docker compose -f compose.yaml -f compose.production.yaml config -q
sudo docker compose -f compose.yaml -f compose.production.yaml up -d db redis wordpress
sudo docker compose -f compose.yaml -f compose.production.yaml ps
curl -I https://againbuy.in
```

Confirm home, shop, product, cart, checkout and admin on a phone and desktop. Test a complete Razorpay transaction only in Test Mode until the launch gates are signed off. The Windows `docker/run-checks.ps1` suite creates temporary products/orders, so run it on a **staging clone**, never against the live database. Monitor Nginx and container logs without posting secrets:

```bash
sudo journalctl -u nginx -n 50 --no-pager
sudo docker compose -f compose.yaml -f compose.production.yaml logs --tail=100 wordpress db
```

### Sources checked

- [Neviri App Deployment overview](https://neviri.com/ai) and [Neviri products](https://neviri.com/products) — public capabilities; exact ZIP/volume behavior still needs plan-specific confirmation.
- [Docker Engine on Ubuntu](https://docs.docker.com/engine/install/ubuntu/), [Compose service restarts](https://docs.docker.com/reference/compose-file/services/), and [Compose environment-change behavior](https://docs.docker.com/reference/cli/docker/compose/restart/).
- [GitHub SSH keys](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/generating-a-new-ssh-key-and-adding-it-to-the-ssh-agent), [Certbot Nginx instructions](https://certbot.eff.org/instructions?ws=nginx&os=ubuntufocal), [WordPress proxy HTTPS handling](https://developer.wordpress.org/advanced-administration/security/https/), and [Razorpay WooCommerce plugin](https://razorpay.com/app-store/woocommerce/).
