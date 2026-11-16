#!/bin/sh
set -eu

compose='docker compose -f compose.yaml -f compose.production.yaml'
$compose ps
$compose --profile tools run --rm cli wp core is-installed
printf 'HOME='
$compose --profile tools run --rm cli wp option get home
printf 'PRODUCTS='
$compose --profile tools run --rm cli wp post list --post_type=product --post_status=publish --format=count
$compose --profile tools run --rm cli wp redis status
$compose --profile tools run --rm cli wp eval '$gateway = get_option("woocommerce_razorpay_settings", []); echo "RAZORPAY=" . (($gateway["enabled"] ?? "no") === "yes" ? "test-enabled" : "disabled") . PHP_EOL; $analytics = get_option("woocommerce_google_analytics_settings", []); echo "GA4=" . (!empty($analytics["ga_id"]) ? "configured" : "disabled") . PHP_EOL;'
printf 'NGINX='
curl -sS -o /dev/null -w '%{http_code}\n' -H 'Host: againbuy.in' -H 'X-Forwarded-Proto: https' http://127.0.0.1/
printf 'WORDPRESS='
curl -sS -o /dev/null -w '%{http_code}\n' -H 'Host: againbuy.in' -H 'X-Forwarded-Proto: https' http://127.0.0.1:8080/
