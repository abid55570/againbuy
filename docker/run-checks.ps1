$ErrorActionPreference = 'Stop'
docker compose --progress quiet --profile tools run --rm cli wp eval-file /tests/backend/catalog-import.php
if ($LASTEXITCODE -ne 0) { throw 'Catalog import test failed.' }
foreach ($file in 'integration.php','roles.php','variations.php','analytics.php','phase4.php') {
    docker compose --progress quiet --profile tools run --rm cli wp eval-file "/tests/backend/$file"
    if ($LASTEXITCODE -ne 0) { throw "$file failed." }
}
docker compose --progress quiet --profile tools run --rm cli wp eval-file /tests/backend/currency-location.php
if ($LASTEXITCODE -ne 0) { throw 'Country pricing test failed.' }
docker compose --progress quiet --profile tools run --rm cli wp eval-file /tests/backend/ga4.php
if ($LASTEXITCODE -ne 0) { throw 'GA4 settings test failed.' }
docker compose --progress quiet --profile tools run --rm cli wp eval-file /tests/backend/location.php
if ($LASTEXITCODE -ne 0) { throw 'Location endpoint test failed.' }
docker compose --progress quiet --profile tools run --rm cli wp eval-file /tests/frontend/integration.php
if ($LASTEXITCODE -ne 0) { throw 'Theme test failed.' }
& "$PSScriptRoot/test-shopper.ps1"
if ($LASTEXITCODE -ne 0) { throw 'Shopper test failed.' }
& "$PSScriptRoot/test-mobile-browser.ps1"
if ($LASTEXITCODE -ne 0) { throw 'Mobile browser test failed.' }
& "$PSScriptRoot/test-checkout-double-submit.ps1"
if ($LASTEXITCODE -ne 0) { throw 'Duplicate checkout test failed.' }
& "$PSScriptRoot/test-rate-limit.ps1"
if ($LASTEXITCODE -ne 0) { throw 'Rate-limit test failed.' }
docker compose --progress quiet --profile tools run --rm cli wp eval-file /tests/backend/mail.php
if ($LASTEXITCODE -ne 0) { throw 'Mail test failed.' }
Write-Output 'All local integration checks passed.'
