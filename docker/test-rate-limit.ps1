$ErrorActionPreference = 'Stop'
$base = 'http://localhost:8080'
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$cart = Invoke-WebRequest "$base/wp-json/wc/store/v1/cart" -WebSession $session -UseBasicParsing
$token = $cart.Headers['Cart-Token']
if (-not $token) { throw 'Cart token missing.' }
$statuses = @()
foreach ($attempt in 1..4) {
    try {
        $response = Invoke-WebRequest "$base/wp-json/wc/store/v1/checkout" -Method Post -Body '{}' -ContentType 'application/json' -Headers @{'Cart-Token'=$token} -WebSession $session -UseBasicParsing
        $statuses += [int]$response.StatusCode
    } catch {
        $statuses += [int]$_.Exception.Response.StatusCode
    }
}
$firstLimit = [array]::IndexOf($statuses, 429)
if ($firstLimit -lt 0) { throw "No rate-limit response: $($statuses -join ',')" }
foreach ($index in 0..3) {
    $expected = if ($index -lt $firstLimit) { 400 } else { 429 }
    if ($statuses[$index] -ne $expected) { throw "Unexpected checkout rate-limit responses: $($statuses -join ',')" }
}
Write-Output "Checkout rate limit passed: $($statuses -join ',')."
