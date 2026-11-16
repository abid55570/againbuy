$ErrorActionPreference = 'Stop'
$fixture = '/tests/backend/fixture.php'
try {
    $created = docker compose --progress quiet --profile tools run --rm cli wp eval-file $fixture
    if ($LASTEXITCODE -ne 0) { throw ($created -join "`n") }
    $id = [int]([regex]::Match(($created -join "`n"), 'Fixture product ID: (\d+)').Groups[1].Value)
    if (-not $id) { throw 'Browser fixture ID missing.' }
    python "$PSScriptRoot/test-mobile-browser.py" $id
    if ($LASTEXITCODE -ne 0) { throw 'Mobile browser test failed.' }
} finally {
    docker compose --progress quiet --profile tools run --rm -e AB_FIXTURE_ACTION=delete cli wp eval-file $fixture | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Browser fixture cleanup failed.' }
}
