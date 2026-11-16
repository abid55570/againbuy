#!/bin/sh
set -eu

attempt=0
until wp db check >/dev/null 2>&1; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 30 ]; then echo 'Database did not become ready.' >&2; exit 1; fi
  sleep 2
done

wp core is-installed >/dev/null 2>&1 || wp core install \
  --url="$APP_URL" \
  --title='AgainBuy' \
  --admin_user="$WP_ADMIN_USER" \
  --admin_password="$WP_ADMIN_PASSWORD" \
  --admin_email="$WP_ADMIN_EMAIL" \
  --skip-email

if ! wp plugin is-installed woocommerce; then
  wp plugin install woocommerce --activate
else
  wp plugin activate woocommerce
fi

wp option update woocommerce_currency INR
wp option update woocommerce_coming_soon no
wp option update woocommerce_enable_myaccount_registration yes
wp option update woocommerce_enable_reviews yes
wp option update woocommerce_review_rating_verification_required yes
wp option update blogdescription 'AgainBuy online store'
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard
wp plugin is-active woocommerce
if ! wp plugin is-installed advanced-media-offloader; then
  wp plugin install advanced-media-offloader --version=4.7.0
fi
if [ -n "${S3_ENDPOINT:-}" ] && [ -n "${S3_PUBLIC_URL:-}" ] && [ -n "${S3_BUCKET:-}" ] && [ -n "${S3_ACCESS_KEY:-}" ] && [ -n "${S3_SECRET_KEY:-}" ]; then
  wp eval-file /scripts/storage-setup.php
  wp plugin activate advanced-media-offloader
else
  wp plugin deactivate advanced-media-offloader >/dev/null 2>&1 || true
fi
if ! wp plugin is-active againbuy-commerce-core; then wp plugin activate againbuy-commerce-core; fi
wp eval 'AgainBuy_Commerce_Core::activate();'
if [ -n "${ANALYTICS_USER:-}" ] && [ -n "${ANALYTICS_PASSWORD:-}" ] && [ -n "${ANALYTICS_EMAIL:-}" ]; then
  if wp user get "$ANALYTICS_USER" >/dev/null 2>&1; then
    wp user update "$ANALYTICS_USER" --user_pass="$ANALYTICS_PASSWORD" --user_email="$ANALYTICS_EMAIL" --role=againbuy_analyst
  else
    wp user create "$ANALYTICS_USER" "$ANALYTICS_EMAIL" --user_pass="$ANALYTICS_PASSWORD" --role=againbuy_analyst
  fi
fi
if ! wp plugin is-installed nextend-facebook-connect; then wp plugin install nextend-facebook-connect --version=3.1.26; fi
if ! wp plugin is-installed redis-cache; then
  wp plugin install redis-cache --version=2.8.0 --activate
else
  wp plugin activate redis-cache
fi
if [ -n "${REDIS_HOST:-}" ]; then wp redis enable; fi
wp eval-file /scripts/geolocation-setup.php
if [ "$(wp theme list --status=active --field=name)" != 'againbuy' ]; then wp theme activate againbuy; fi
if ! wp plugin is-installed woocommerce-google-analytics-integration; then
  wp plugin install woocommerce-google-analytics-integration --version=2.4.4
fi
if [ -n "${GA4_MEASUREMENT_ID:-}" ]; then
  wp plugin activate woocommerce-google-analytics-integration
  wp eval-file /scripts/ga4-setup.php
else
  wp plugin deactivate woocommerce-google-analytics-integration >/dev/null 2>&1 || true
fi
if ! wp plugin is-installed woo-razorpay; then wp plugin install woo-razorpay --version=4.8.8; fi
if [ -n "${RAZORPAY_KEY_ID:-}" ] && [ -n "${RAZORPAY_KEY_SECRET:-}" ] && [ -n "${RAZORPAY_WEBHOOK_SECRET:-}" ]; then
  wp plugin activate woo-razorpay
else
  wp plugin deactivate woo-razorpay >/dev/null 2>&1 || true
fi
wp eval-file /scripts/phase4-setup.php
wp eval-file /scripts/import-muzzo.php
echo 'WordPress and WooCommerce installation complete.'
