#!/usr/bin/env bash
set -euo pipefail
umask 077

[[ $EUID -eq 0 ]] || { echo 'Run with sudo bash docker/deploy-vm.sh'; exit 1; }
runtime=/opt/againbuy
source_dir=/opt/againbuy-source
repo=https://github.com/abid55570/againbuy.git
[[ -f "$runtime/.env" ]] || { echo 'Existing /opt/againbuy/.env is required.'; exit 1; }
command -v rsync >/dev/null || { echo 'Install rsync: sudo apt-get install -y rsync'; exit 1; }
exec 9>/var/lock/againbuy-deploy.lock
flock -n 9 || { echo 'Another deployment is running.'; exit 1; }
if [[ ! -d "$source_dir/.git" ]]; then
  git clone --branch main "$repo" "$source_dir"
fi
[[ -z "$(git -C "$source_dir" status --porcelain)" ]] || { echo 'Source checkout has local changes; resolve them first.'; exit 1; }
git -C "$source_dir" pull --ff-only origin main
cd "$runtime"
compose=(docker compose -f compose.yaml -f compose.production.yaml)
backup="$runtime/backups/$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$backup"
"${compose[@]}" --profile tools run --rm -T cli wp db export - --quiet | gzip > "$backup/database.sql.gz"
gzip -t "$backup/database.sql.gz"
tar -czf "$backup/code.tgz" backend/plugin frontend/theme docker compose.yaml compose.production.yaml
cp .env "$backup/runtime.env"
rsync -a --chmod=D755,F644 --exclude=.git --exclude=.env --include=.env.example --exclude='.env.*' --exclude=backups --exclude=artifacts "$source_dir/" "$runtime/"
"${compose[@]}" config --quiet
"${compose[@]}" up -d db redis wordpress
"${compose[@]}" --profile tools run --rm -T cli wp eval 'AgainBuy_Commerce_Core::activate();'
"${compose[@]}" --profile tools run --rm -T cli wp eval-file /scripts/phase4-setup.php
"${compose[@]}" --profile tools run --rm -T cli wp cache flush
curl --fail --silent --show-error --retry 6 --retry-delay 3 --retry-connrefused -o /dev/null https://againbuy.in/
echo "Deployed $(git -C "$source_dir" rev-parse --short HEAD)"
echo "Backup: $backup"
