$required = 'DB_NAME','DB_USER','DB_PASSWORD','DB_ROOT_PASSWORD','WP_ADMIN_USER','WP_ADMIN_PASSWORD','WP_ADMIN_EMAIL','WORDPRESS_AUTH_KEY','WORDPRESS_SECURE_AUTH_KEY','WORDPRESS_LOGGED_IN_KEY','WORDPRESS_NONCE_KEY','WORDPRESS_AUTH_SALT','WORDPRESS_SECURE_AUTH_SALT','WORDPRESS_LOGGED_IN_SALT','WORDPRESS_NONCE_SALT'
if (-not (Test-Path -LiteralPath '.env')) { throw 'Copy .env.example to .env and set local passwords first.' }
$values = @{}
Get-Content -LiteralPath '.env' | ForEach-Object {
    if ($_ -match '^([A-Z0-9_]+)=(.*)$') { $values[$matches[1]] = $matches[2] }
}
foreach ($name in $required) {
    if (-not $values[$name] -or $values[$name] -eq 'change_me') { throw "Set $name in .env before starting." }
}
Write-Output 'Required local settings are present.'
