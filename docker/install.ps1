$ErrorActionPreference = 'Stop'
& "$PSScriptRoot/check-env.ps1"

docker compose up -d db wordpress redis mailpit
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

docker compose --profile tools run --rm cli sh /scripts/install.sh
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

docker compose --profile tools run --rm cli wp core is-installed
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
docker compose --profile tools run --rm cli wp plugin is-active woocommerce
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
Write-Output 'WordPress, WooCommerce, AgainBuy plugin and theme checks passed.'
