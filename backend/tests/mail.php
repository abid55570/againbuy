<?php
add_action('wp_mail_failed', static function ($error) { echo 'Mailer error: ' . $error->get_error_message() . "\n"; });
if (!wp_mail('againbuy-local-test@example.com', 'AgainBuy local mail test', 'SMTP delivery check')) {
    throw new RuntimeException('Local SMTP delivery failed');
}
echo "Local SMTP send accepted.\n";
