<?php
if (!defined('ABSPATH')) { exit; }

final class AgainBuy_Analytics {
    public static function boot(): void {
        add_action('admin_menu', static function () {
            add_menu_page('AgainBuy analytics', 'Analytics', 'view_woocommerce_reports', 'againbuy-sales', [__CLASS__, 'page'], 'dashicons-chart-area', 3);
        });
        add_action('admin_post_againbuy_sales_csv', [__CLASS__, 'download']);
    }

    public static function summary(string $from, string $to): array {
        $result = ['orders' => 0, 'currencies' => [], 'products' => [], 'payments' => [], 'payment_revenue' => [], 'payment_statuses' => [], 'countries' => [], 'days' => []];
        for ($page = 1; ; $page++) {
            $batch = wc_get_orders(['limit' => 100, 'page' => $page, 'paginate' => true, 'date_paid' => $from . '...' . $to, 'orderby' => 'date', 'order' => 'ASC']);
            foreach ($batch->orders as $order) {
                if (!$order->get_date_paid()) { continue; }
                $currency = $order->get_currency();
                $result['orders']++;
                $result['currencies'][$currency]['orders'] = ($result['currencies'][$currency]['orders'] ?? 0) + 1;
                foreach (['total' => $order->get_total(), 'refunded' => $order->get_total_refunded(), 'tax' => $order->get_total_tax(), 'shipping' => $order->get_shipping_total(), 'discount' => $order->get_discount_total()] as $key => $amount) {
                    $result['currencies'][$currency][$key] = ($result['currencies'][$currency][$key] ?? 0) + (float) $amount;
                }
                $payment = $order->get_payment_method() ?: 'unknown';
                $result['payments'][$payment] = ($result['payments'][$payment] ?? 0) + 1;
                $result['payment_revenue'][$payment][$currency] = ($result['payment_revenue'][$payment][$currency] ?? 0) + (float) $order->get_total() - (float) $order->get_total_refunded();
                $country = $order->get_billing_country() ?: 'unknown';
                $result['countries'][$country] = ($result['countries'][$country] ?? 0) + 1;
                $day = $order->get_date_paid()->date('Y-m-d');
                $result['days'][$day][$currency] = ($result['days'][$day][$currency] ?? 0) + (float) $order->get_total() - (float) $order->get_total_refunded();
                foreach ($order->get_items('line_item') as $item) {
                    $id = $item->get_product_id();
                    $result['products'][$id]['name'] = $item->get_name();
                    $result['products'][$id]['quantity'] = ($result['products'][$id]['quantity'] ?? 0) + $item->get_quantity();
                    $result['products'][$id]['revenue'][$currency] = ($result['products'][$id]['revenue'][$currency] ?? 0) + (float) $item->get_total();
                }
            }
            if ($page >= $batch->max_num_pages) { break; }
        }
        for ($page = 1; ; $page++) {
            $batch = wc_get_orders(['limit' => 100, 'page' => $page, 'paginate' => true, 'date_created' => $from . '...' . $to]);
            foreach ($batch->orders as $order) {
                if ($order->get_status() === 'checkout-draft') { continue; }
                $method = $order->get_payment_method() ?: 'unknown';
                $status = $order->get_status();
                $result['payment_statuses'][$method][$status] = ($result['payment_statuses'][$method][$status] ?? 0) + 1;
            }
            if ($page >= $batch->max_num_pages) { break; }
        }
        uasort($result['products'], static fn($a, $b) => $b['quantity'] <=> $a['quantity']);
        ksort($result['days']);
        return $result;
    }

    public static function page(): void {
        if (!current_user_can('view_woocommerce_reports')) { wp_die(esc_html__('Not allowed.', 'againbuy')); }
        $from = sanitize_text_field(wp_unslash($_GET['from'] ?? gmdate('Y-m-d', strtotime('-30 days'))));
        $to = sanitize_text_field(wp_unslash($_GET['to'] ?? gmdate('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
            echo '<div class="wrap"><h1>AgainBuy sales</h1><p>Choose a valid date range.</p></div>';
            return;
        }
        $data = self::summary($from, $to);
        $download = wp_nonce_url(add_query_arg(['action' => 'againbuy_sales_csv', 'from' => $from, 'to' => $to], admin_url('admin-post.php')), 'againbuy_sales_csv');
        echo '<div class="wrap"><h1>AgainBuy sales</h1><form method="get"><input type="hidden" name="page" value="againbuy-sales"><label>From <input type="date" name="from" value="' . esc_attr($from) . '"></label> <label>To <input type="date" name="to" value="' . esc_attr($to) . '"></label> <button class="button button-primary">Apply</button></form><p><a class="button" href="' . esc_url($download) . '">Download CSV</a></p>';
        echo '<h2>Paid orders: ' . esc_html((string) $data['orders']) . '</h2>';
        foreach ($data['currencies'] as $currency => $row) {
            echo '<h3>' . esc_html($currency) . '</h3><p>Gross ' . esc_html(wp_strip_all_tags(wc_price($row['total'], ['currency' => $currency]))) . ' · Net ' . esc_html(wp_strip_all_tags(wc_price($row['total'] - $row['refunded'], ['currency' => $currency]))) . ' · Refunds ' . esc_html(wp_strip_all_tags(wc_price($row['refunded'], ['currency' => $currency]))) . ' · Tax ' . esc_html(wp_strip_all_tags(wc_price($row['tax'], ['currency' => $currency]))) . ' · Shipping ' . esc_html(wp_strip_all_tags(wc_price($row['shipping'], ['currency' => $currency]))) . ' · Discounts ' . esc_html(wp_strip_all_tags(wc_price($row['discount'], ['currency' => $currency]))) . '</p>';
        }
        self::bars('Product units', array_map(static fn($row) => $row['quantity'], $data['products']), array_map(static fn($row) => $row['name'], $data['products']));
        echo '<h2>Product revenue</h2><table class="widefat striped"><thead><tr><th>Product</th><th>Units</th><th>INR</th><th>USD</th></tr></thead><tbody>';
        foreach ($data['products'] as $row) {
            echo '<tr><td>' . esc_html($row['name']) . '</td><td>' . esc_html((string) $row['quantity']) . '</td><td>' . esc_html((string) ($row['revenue']['INR'] ?? 0)) . '</td><td>' . esc_html((string) ($row['revenue']['USD'] ?? 0)) . '</td></tr>';
        }
        echo '</tbody></table>';
        self::bars('Paid payment methods', $data['payments']);
        $statuses = [];
        foreach ($data['payment_statuses'] as $method => $counts) {
            foreach ($counts as $status => $count) { $statuses[$method . ' · ' . $status] = $count; }
        }
        self::bars('Payment/order statuses', $statuses);
        echo '<h2>Payment method net revenue</h2><table class="widefat striped"><thead><tr><th>Method</th><th>INR</th><th>USD</th></tr></thead><tbody>';
        foreach ($data['payment_revenue'] as $method => $row) {
            echo '<tr><td>' . esc_html($method) . '</td><td>' . esc_html((string) ($row['INR'] ?? 0)) . '</td><td>' . esc_html((string) ($row['USD'] ?? 0)) . '</td></tr>';
        }
        echo '</tbody></table>';
        self::bars('Customer countries', $data['countries']);
        foreach (['INR', 'USD'] as $currency) {
            $daily = [];
            foreach ($data['days'] as $day => $amounts) { $daily[$day] = $amounts[$currency] ?? 0; }
            self::bars($currency . ' daily net revenue', $daily);
        }
        echo '</div>';
    }

    public static function csv(array $data): string {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['Section', 'Item', 'Currency', 'Amount', 'Count']);
        foreach ($data['currencies'] as $currency => $row) {
            foreach (['total', 'refunded', 'tax', 'shipping', 'discount'] as $field) {
                fputcsv($stream, ['Sales', $field, $currency, $row[$field], $row['orders']]);
            }
        }
        foreach ($data['products'] as $row) {
            $name = preg_match('/^[=+\-@]/', $row['name']) ? "'" . $row['name'] : $row['name'];
            foreach ($row['revenue'] as $currency => $amount) {
                fputcsv($stream, ['Product', $name, $currency, $amount, $row['quantity']]);
            }
        }
        foreach ($data['payment_revenue'] as $method => $amounts) {
            foreach ($amounts as $currency => $amount) {
                fputcsv($stream, ['Payment method', $method, $currency, $amount, $data['payments'][$method]]);
            }
        }
        foreach ($data['payment_statuses'] as $method => $statuses) {
            foreach ($statuses as $status => $count) {
                fputcsv($stream, ['Payment status', $method . ': ' . $status, '', '', $count]);
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }

    public static function download(): void {
        if (!current_user_can('view_woocommerce_reports')) { wp_die(esc_html__('Not allowed.', 'againbuy')); }
        check_admin_referer('againbuy_sales_csv');
        $from = sanitize_text_field(wp_unslash($_GET['from'] ?? ''));
        $to = sanitize_text_field(wp_unslash($_GET['to'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) { wp_die(esc_html__('Invalid date range.', 'againbuy')); }
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="againbuy-sales-' . $from . '-to-' . $to . '.csv"');
        echo self::csv(self::summary($from, $to));
        exit;
    }

    private static function bars(string $title, array $values, array $labels = []): void {
        echo '<h2>' . esc_html($title) . '</h2>';
        if (!$values) { echo '<p>No data in this period.</p>'; return; }
        $maximum = max(1, max($values));
        echo '<div style="max-width:850px">';
        foreach ($values as $key => $value) {
            $width = max(1, (int) round(100 * $value / $maximum));
            echo '<div style="margin:10px 0"><strong>' . esc_html($labels[$key] ?? (string) $key) . '</strong> · ' . esc_html((string) round($value, 2)) . '<div style="background:#e8eee9;height:18px;border-radius:4px"><div style="background:#255241;height:18px;border-radius:4px;width:' . esc_attr((string) $width) . '%"></div></div></div>';
        }
        echo '</div>';
    }
}
