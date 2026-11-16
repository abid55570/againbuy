$ErrorActionPreference = 'Stop'
$base = 'http://localhost:8080'
$category = Invoke-WebRequest "$base/product-category/bottle/?againbuy_test_country=US" -UseBasicParsing
if ($category.StatusCode -ne 200 -or $category.Content -notmatch 'woocommerce-Price-currencySymbol.{0,80}&#36;' -or $category.Content -notmatch 'againbuy-card-gallery' -or $category.Content -notmatch 'add_to_cart_button' -or $category.Content -notmatch 'googletagmanager.com/gtag/js') { throw 'Public bottle category is missing price, image, cart action or GA4 tag.' }
$switch = curl.exe -sS -D - -o NUL "$base/?againbuy_test_country=US"
if (($switch -join "`n") -notmatch 'HTTP/1\.1 302' -or ($switch -join "`n") -notmatch 'Location: /') { throw 'Local country test route did not redirect cleanly.' }
$created = docker compose --progress quiet --profile tools run --rm cli wp eval-file /tests/backend/fixture.php
if ($LASTEXITCODE -ne 0) { throw ($created -join "`n") }
$productId = [int]([regex]::Match(($created -join "`n"), 'Fixture product ID: (\d+)').Groups[1].Value)
$inrOnlyId = [int]([regex]::Match(($created -join "`n"), 'INR-only fixture ID: (\d+)').Groups[1].Value)
if (-not $productId -or -not $inrOnlyId) { throw 'Fixture IDs missing.' }
$storefrontResponse = Invoke-WebRequest "$base/" -UseBasicParsing
if ($storefrontResponse.Content -match 'AgainBuy test bottle|AgainBuy INR-only test item') { throw 'Test fixtures leaked into the public catalog.' }
try {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $null = Invoke-WebRequest "$base/?againbuy_test_country=IN" -WebSession $session -UseBasicParsing
    $product = Invoke-WebRequest "$base/?p=$productId&post_type=product" -WebSession $session -UseBasicParsing
    if ($product.StatusCode -ne 200 -or $product.Content -notmatch 'AgainBuy test bottle') { throw 'Product page failed.' }
    1..2 | ForEach-Object { $null = Invoke-WebRequest "$base/?add-to-cart=$productId" -WebSession $session -UseBasicParsing }
    $cart = Invoke-RestMethod "$base/wp-json/wc/store/v1/cart" -WebSession $session
    if ($cart.items[0].quantity -ne 2 -or $cart.totals.total_price -ne '299800') { throw 'Repeated add-to-cart failed.' }
    $null = Invoke-WebRequest "$base/?againbuy_test_country=US" -WebSession $session -UseBasicParsing
    $usdCart = Invoke-RestMethod "$base/wp-json/wc/store/v1/cart" -WebSession $session
    if ($usdCart.items[0].quantity -ne 2 -or $usdCart.totals.total_price -ne '5800' -or $usdCart.totals.currency_code -ne 'USD') { throw 'Currency switch failed.' }

    $invalid = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $null = Invoke-WebRequest "$base/?againbuy_test_country=IN" -WebSession $invalid -UseBasicParsing
    $null = Invoke-WebRequest "$base/?add-to-cart=$productId&quantity=-2" -WebSession $invalid -UseBasicParsing
    $badCart = Invoke-RestMethod "$base/wp-json/wc/store/v1/cart" -WebSession $invalid
    if ($badCart.items.Count -ne 0) { throw 'Negative quantity entered the cart.' }
    $null = Invoke-WebRequest "$base/?add-to-cart=$productId&quantity=999" -WebSession $invalid -UseBasicParsing
    $badCart = Invoke-RestMethod "$base/wp-json/wc/store/v1/cart" -WebSession $invalid
    if ($badCart.items.Count -ne 0) { throw 'Above-stock quantity entered the cart.' }
    $null = Invoke-WebRequest "$base/?add-to-cart=$inrOnlyId" -WebSession $invalid -UseBasicParsing
    $null = Invoke-WebRequest "$base/?againbuy_test_country=US" -WebSession $invalid -UseBasicParsing
    $badCart = Invoke-RestMethod "$base/wp-json/wc/store/v1/cart" -WebSession $invalid
    if ($badCart.items.Count -ne 0) { throw 'INR-only product survived USD switch.' }
    Write-Output 'Shopper flow checks passed: product, repeated cart, currency, invalid quantity, missing USD price.'
} finally {
    docker compose --progress quiet --profile tools run --rm -e AB_FIXTURE_ACTION=delete cli wp eval-file /tests/backend/fixture.php | Out-Null
}
