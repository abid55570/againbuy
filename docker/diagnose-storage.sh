#!/bin/sh
set -eu

compose='docker compose -f compose.yaml -f compose.production.yaml'
sudo $compose --profile tools run --rm cli wp eval '
foreach (["ADVMO_MINIO_KEY", "ADVMO_MINIO_SECRET", "ADVMO_MINIO_BUCKET", "ADVMO_MINIO_DOMAIN", "ADVMO_MINIO_ENDPOINT", "ADVMO_MINIO_REGION", "ADVMO_MINIO_PATH_STYLE_ENDPOINT"] as $name) {
    echo $name . "=" . (defined($name) ? "defined" : "missing") . PHP_EOL;
}
$settings = get_option("advmo_settings", []);
echo "SETTING_KEYS=" . implode(",", array_keys(is_array($settings) ? $settings : [])) . PHP_EOL;
'
echo PLUGIN_REFERENCES
sudo docker exec againbuy-wordpress-1 sh -lc 'grep -R "ADVMO_MINIO_" -n /var/www/html/wp-content/plugins/advanced-media-offloader | head -30'

getv() { sed -n "s/^$1=//p" .env; }
access=$(getv S3_ACCESS_KEY)
secret=$(getv S3_SECRET_KEY)
endpoint=$(getv S3_ENDPOINT)
region=$(getv S3_REGION)
bucket=$(getv S3_BUCKET)
count=$(sudo docker run --rm -e AWS_ACCESS_KEY_ID="$access" -e AWS_SECRET_ACCESS_KEY="$secret" -e AWS_DEFAULT_REGION="$region" amazon/aws-cli --endpoint-url "$endpoint" s3api list-objects-v2 --bucket "$bucket" --query 'length(Contents)' --output text)
echo "BUCKET_OBJECTS=$count"
attachment_id=$(sudo $compose --profile tools run --rm cli wp post list --post_type=attachment --orderby=ID --order=DESC --posts_per_page=1 --field=ID | tail -n1)
attachment_url=$(sudo $compose --profile tools run --rm cli wp eval "echo wp_get_attachment_url($attachment_id);" | tail -n1)
echo "ATTACHMENT_URL=$attachment_url"
echo "ATTACHMENT_HTTP=$(curl -sS -o /dev/null -w '%{http_code}' "$attachment_url")"
