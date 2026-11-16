$ErrorActionPreference = 'Stop'
$base = 'http://localhost:8080'
$fixture = '/tests/backend/checkout-fixture.php'
docker compose --progress quiet --profile tools run --rm -e AB_FIXTURE_ACTION=delete cli wp eval-file $fixture | Out-Null
$created = docker compose --progress quiet --profile tools run --rm cli wp eval-file $fixture
$id = [int]([regex]::Match(($created -join "`n"), 'Checkout fixture product ID: (\d+)').Groups[1].Value)
if (-not $id) { throw 'Checkout fixture ID missing.' }
try {
    for ($round = 1; $round -le 2; $round++) {
        $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
        $null = Invoke-WebRequest "$base/?againbuy_test_country=IN" -WebSession $session -UseBasicParsing
        $null = Invoke-WebRequest "$base/?add-to-cart=$id" -WebSession $session -UseBasicParsing
        $cart = Invoke-WebRequest "$base/wp-json/wc/store/v1/cart" -WebSession $session -UseBasicParsing
        $tokenValue = $cart.Headers['Cart-Token']
        $nonceValue = $cart.Headers['Nonce']
        if ($tokenValue -is [array]) { $tokenValue = $tokenValue[0] }
        if ($nonceValue -is [array]) { $nonceValue = $nonceValue[0] }
        $headers = @{'Cart-Token'=[string]$tokenValue;'Nonce'=[string]$nonceValue}
        $body = @{billing_address=@{first_name='Test';last_name='Buyer';address_1='Local test';city='Mumbai';state='MH';postcode='400001';country='IN';email='againbuy-checkout-test@example.com';phone='9999999999'};payment_method='bacs';payment_data=@()} | ConvertTo-Json -Depth 8
        try {
            $first = Invoke-WebRequest "$base/wp-json/wc/store/v1/checkout" -Method Post -Body $body -ContentType 'application/json' -Headers $headers -WebSession $session -UseBasicParsing
            $firstStatus = [int]$first.StatusCode
        } catch {
            $firstStatus = [int]$_.Exception.Response.StatusCode
            $retry = [int]$_.Exception.Response.Headers['RateLimit-Retry-After']
        }
        if ($firstStatus -eq 429 -and $round -eq 1) {
            Start-Sleep -Seconds ([Math]::Max(1, [Math]::Min(65, $retry + 1)))
            continue
        }
        if ($firstStatus -ne 200) { throw "First checkout was rejected: $firstStatus" }
        try {
            $second = Invoke-WebRequest "$base/wp-json/wc/store/v1/checkout" -Method Post -Body $body -ContentType 'application/json' -Headers $headers -WebSession $session -UseBasicParsing
            $secondStatus = [int]$second.StatusCode
        } catch {
            $secondStatus = [int]$_.Exception.Response.StatusCode
        }
        if ($secondStatus -eq 200) { throw 'Repeated checkout created another order.' }
        $count = docker compose --progress quiet --profile tools run --rm cli wp eval-file /tests/backend/checkout-count.php
        if (($count -join "`n") -notmatch 'Placed checkout test orders: 1') { throw ($count -join "`n") }
        Write-Output "Duplicate checkout check passed: first=$firstStatus second=$secondStatus placed-orders=1."
        break
    }
} finally {
    docker compose --progress quiet --profile tools run --rm -e AB_FIXTURE_ACTION=delete cli wp eval-file $fixture | Out-Null
}
