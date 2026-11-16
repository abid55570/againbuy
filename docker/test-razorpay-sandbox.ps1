$ErrorActionPreference = 'Stop'
$fixture = '/tests/backend/checkout-fixture.php'
try {
    $created = docker compose --progress quiet --profile tools run --rm cli wp eval-file $fixture
    if ($LASTEXITCODE -ne 0) { throw 'Razorpay fixture creation failed.' }
    $fixtureId = [int]([regex]::Match(($created -join "`n"), 'Checkout fixture product ID: (\d+)').Groups[1].Value)
    if (-not $fixtureId) { throw 'Razorpay fixture ID missing.' }
    python "$PSScriptRoot/test-razorpay-sandbox.py" $fixtureId
    if ($LASTEXITCODE -ne 0) { throw 'Razorpay sandbox checkout failed.' }
    docker compose --progress quiet --profile tools run --rm cli wp eval-file /tests/backend/razorpay-pending.php
    if ($LASTEXITCODE -ne 0) { throw 'Razorpay order state check failed.' }
} finally {
    docker compose --progress quiet --profile tools run --rm -e AB_FIXTURE_ACTION=delete cli wp eval-file $fixture | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Razorpay fixture cleanup failed.' }
}
