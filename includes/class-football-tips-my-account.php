<?php

class Football_Tips_My_Account
{
    const COMMISSION_PERCENTAGE = 25; // 25% commission

    public static function init()
    {
        // Add WooCommerce My Account Endpoints
        add_action('init', [__CLASS__, 'add_endpoints']);
        add_filter('query_vars', [__CLASS__, 'add_query_vars'], 0);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'add_menu_items']);
        add_action('woocommerce_account_my-earnings_endpoint', [__CLASS__, 'render_my_earnings']);
        add_action('woocommerce_account_sales-transactions_endpoint', [__CLASS__, 'render_sales_transactions']);

        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_fetch_transactions', [__CLASS__, 'fetch_transactions']);
        add_action('wp_ajax_fetch_earnings_data', [__CLASS__, 'fetch_earnings_data']);
    }

    public static function enqueue_scripts()
    {
        if (!is_account_page()) {
            return;
        }

        wp_enqueue_style(
            'football-tips-my-account',
            plugins_url('../assets/css/my-account.css', __FILE__),
            [],
            '1.0'
        );

        wp_enqueue_script(
            'football-tips-my-account',
            plugins_url('../assets/js/my-account.js', __FILE__),
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('football-tips-my-account', 'FootballTipsMyAccountAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }


    public static function add_endpoints()
    {
        add_rewrite_endpoint('sales-transactions', EP_PAGES);
    }

    public static function add_query_vars($vars)
    {
        $vars[] = 'sales-transactions';
        return $vars;
    }

    public static function add_menu_items($items)
    {
        $logout = $items['customer-logout'];
        unset($items['customer-logout']); // Remove logout temporarily

        $items['sales-transactions'] = __('Sales Transactions', 'football-tips');

        $items['customer-logout'] = $logout; // Add logout back at the end
        return $items;
    }

    public static function fetch_earnings_data()
    {
        $current_user_id = get_current_user_id();

        // Fetch the subscription product ID for the current user
        $subscription_product_id = get_user_meta($current_user_id, 'tips_subscription_product_id', true);

        if (!$subscription_product_id) {
            wp_send_json_success([
                'total_earnings' => 0,
                'current_month_earnings' => 0,
                'total_subscribers' => 0,
            ]);
            return;
        }

        // Fetch all subscriptions for the product
        $subscriptions = wcs_get_subscriptions_for_product($subscription_product_id);

        if (!$subscriptions || !is_array($subscriptions)) {
            wp_send_json_success([
                'total_earnings' => 0,
                'current_month_earnings' => 0,
                'total_subscribers' => 0,
            ]);
            return;
        }

        $total_earnings = 0;
        $current_month_earnings = 0;
        $total_subscribers = 0;
        $current_month = date('Y-m');

        foreach ($subscriptions as $subscription_id_or_object) {
            // If it's a string, try to convert it into a subscription object
            if (is_string($subscription_id_or_object) || is_int($subscription_id_or_object)) {
                $subscription = wcs_get_subscription($subscription_id_or_object);
            } else {
                $subscription = $subscription_id_or_object; // Already an object
            }

            if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
                continue; // Skip invalid or non-subscription entries
            }

            $status = $subscription->get_status();
            $date_created = $subscription->get_date_created();

            // Only count active/on-hold subscriptions
            if (in_array($status, ['active', 'on-hold'])) {
                $total_earnings += (float) $subscription->get_total();
                $total_subscribers++;

                // Check if subscription was created in the current month
                if ($date_created && $date_created->format('Y-m') === $current_month) {
                    $current_month_earnings += (float) $subscription->get_total();
                }
            }
        }

        wp_send_json_success([
            'total_earnings' => $total_earnings,
            'current_month_earnings' => $current_month_earnings,
            'total_subscribers' => $total_subscribers,
        ]);
    }



    public static function fetch_tipster_sales($tipster_id, $limit = 10, $offset = 0)
    {
        // Fetch the subscription product ID
        $subscription_product_id = get_user_meta($tipster_id, 'tips_subscription_product_id', true);

        if (!$subscription_product_id) {
            return []; // No subscription product found
        }

        // Get all subscriptions associated with this product
        $subscriptions = wcs_get_subscriptions_for_product($subscription_product_id, [
            'limit'  => $limit,
            'offset' => $offset,
        ]);

        $sales_data = [];

        foreach ($subscriptions as $subscription) {
            $customer_id = $subscription->get_user_id();
            $customer = get_userdata($customer_id);
            $total = $subscription->get_total();

            // Calculate commission and net earnings
            $commission = $total * (self::COMMISSION_PERCENTAGE / 100);
            $net_earnings = $total - $commission;

            $sales_data[] = [
                'subscription_id' => $subscription->get_id(),
                'status'          => $subscription->get_status(),
                'customer_name'   => $customer ? $customer->display_name : 'Unknown',
                'total'           => $total,
                'commission'      => $commission,
                'net_earnings'    => $net_earnings,
                'date'            => $subscription->get_date_created()->date('Y-m-d'),
            ];
        }

        return $sales_data;
    }


    public static function render_my_earnings()
    {
        echo '<div id="earnings-dashboard">';
        echo '<div id="earnings-summary">';
        echo '<h3>My Earnings</h3>';
        echo '<p>Total Earnings: $<span id="total-earnings">0.00</span></p>';
        echo '<p>Current Month Earnings: $<span id="current-month-earnings">0.00</span></p>';
        echo '<p>Total Subscribers: <span id="subscriber-count">0</span></p>';
        echo '</div>';

        echo '<div id="earnings-chart-container">';
        echo '<canvas id="earnings-chart"></canvas>';
        echo '</div>';
        echo '</div>';
    }

    public static function render_sales_transactions()
    {
        $current_user_id = get_current_user_id();

        // Fetch sales data for the tipster
        $sales_data = self::fetch_tipster_sales($current_user_id);

        if (empty($sales_data)) {
            echo '<p>No sales transactions available.</p>';
            return;
        }

        echo '<table class="sales-table">';
        echo '<thead><tr><th>Date</th><th>ID</th><th>Customer</th><th>Status</th><th>Total</th><th>Commission</th><th>Net Earnings</th></tr></thead>';
        echo '<tbody>';

        foreach ($sales_data as $sale) {
            echo '<tr>';
            echo '<td>' . esc_html(date('d-m-Y', strtotime($sale['date']))) . '</td>';
            echo '<td>' . esc_html($sale['subscription_id']) . '</td>';
            echo '<td>' . esc_html($sale['customer_name']) . '</td>';
            echo '<td>' . esc_html(ucfirst($sale['status'])) . '</td>';
            echo '<td>' . wc_price($sale['total']) . '</td>';
            echo '<td>' . wc_price($sale['commission']) . '</td>';
            echo '<td>' . wc_price($sale['net_earnings']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
Football_Tips_My_Account::init();
