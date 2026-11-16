# Update the existing VM

From Windows PowerShell:

```powershell
ssh -i "C:\Users\abida\Downloads\muzzo-vm-key.pem" ubuntu@173.231.193.174
```

From WSL use `/mnt/c/Users/abida/Downloads/muzzo-vm-key.pem` instead of the Windows path.

One-time setup on the VM:

```bash
sudo apt-get update
sudo apt-get install -y git rsync
sudo git clone --branch main https://github.com/abid55570/againbuy.git /opt/againbuy-source
sudo bash /opt/againbuy-source/docker/deploy-vm.sh
```

Every later release uses one command:

```bash
sudo bash /opt/againbuy-source/docker/deploy-vm.sh
```

The script pulls main, backs up the database, current code and environment, updates the runtime, applies shipping configuration, clears cache and checks HTTPS. It preserves production `.env`, media, database volumes and Google login settings. It does not run the initial installer or reset account passwords. If the repository becomes private, configure read-only GitHub deploy-key authentication for the user executing the script first; never embed a GitHub token in its URL.

Backups contain personal data and secrets; they are stored with restricted permissions under `/opt/againbuy/backups`. Copy backups off the VM and manage retention. A failed deployment exits immediately and prints the failure; rollback is manual using its code/database backup. Never run `docker compose down -v`.

## Environment changes

```bash
sudo nano /opt/againbuy/.env
sudo bash /opt/againbuy-source/docker/deploy-vm.sh
```

Use `INDIA_DELIVERY_CHARGE_INR=120`. Leave `INTERNATIONAL_DELIVERY_CHARGE_USD` empty until the international price is approved. Google OAuth credentials are entered in WordPress Nextend settings, rather than this environment file.

## ZIP and Neviri App Deployment

The source ZIP excludes credentials, the database and the installed WordPress volume. It contains Compose, a custom theme/plugin and an initial installer; it is not a standalone Node application. A ZIP upload alone does not recreate the live store or its recently added products. Back up/migrate the WordPress database and media when moving hosts.

The current tested target is the VM. Neviri App Deployment compatibility requires confirmation of PHP/WordPress container support, persistent WordPress filesystem, external MySQL/MariaDB, Redis access, scheduled tasks and an installation/migration command. Docker Compose support has not been verified. Do not use `npm start` or PM2 for this PHP application. Do not switch the production domain until the target has passed checkout, login, media, shipping and payment checks.
