$ErrorActionPreference = 'Stop'
$source = Get-Content -LiteralPath '.env'
$values = @{S3_ENDPOINT='http://s3mock:9090';S3_PUBLIC_URL='http://localhost:9090';S3_REGION='us-east-1';S3_BUCKET='againbuy';S3_ACCESS_KEY='test';S3_SECRET_KEY='test';S3_PATH_STYLE='true'}
$testEnv = $source | ForEach-Object {
    if ($_ -match '^([A-Z0-9_]+)=') {
        $name = $matches[1]
        if ($values.ContainsKey($name)) { "$name=$($values[$name])" } else { $_ }
    } else { $_ }
}
Set-Content -LiteralPath '.env.storage-test' -Value $testEnv
$attachment = 0
try {
    docker compose --env-file .env.storage-test --progress quiet --profile storage-test up -d s3mock wordpress
    if ($LASTEXITCODE -ne 0) { throw 'S3 mock did not start.' }
    docker compose --env-file .env.storage-test --progress quiet --profile tools run --rm cli wp eval-file /scripts/storage-setup.php | Out-Null
    docker compose --env-file .env.storage-test --progress quiet --profile tools run --rm cli wp plugin activate advanced-media-offloader | Out-Null
    $result = docker compose --env-file .env.storage-test --progress quiet --profile tools run --rm cli wp media import /scripts/pixel.png --title='Storage integration test' --porcelain
    $attachment = [int]($result | Select-Object -Last 1)
    if (-not $attachment) { throw 'Media import returned no ID.' }
    $url = docker compose --env-file .env.storage-test --progress quiet --profile tools run --rm cli wp eval "echo wp_get_attachment_url($attachment);"
    $url = ($url | Select-Object -Last 1).Trim()
    if (-not $url.StartsWith('http://localhost:9090/againbuy/')) { throw "Wrong media URL: $url" }
    $response = Invoke-WebRequest $url -UseBasicParsing
    if ($response.StatusCode -ne 200 -or $response.RawContentLength -ne 68) { throw 'Offloaded media could not be fetched.' }
    Write-Output 'S3-compatible media offload passed: WordPress upload, URL rewrite, and HTTP 200 fetch.'
} finally {
    if ($attachment) { docker compose --env-file .env.storage-test --progress quiet --profile tools run --rm cli wp post delete $attachment --force | Out-Null }
    docker compose --progress quiet up -d wordpress | Out-Null
    docker compose --progress quiet --profile tools run --rm cli wp plugin deactivate advanced-media-offloader | Out-Null
    docker compose --progress quiet --profile storage-test stop s3mock | Out-Null
}
