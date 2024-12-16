<?php

class Football_Tips_Transactions
{
    public static function init()
    {
        add_action('bp_setup_nav', [__CLASS__, 'add_transaction_tab']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_fetch_transactions', [__CLASS__, 'fetch_transactions']);
    }

    /**
     * Add "Transaction History" tab to BuddyPress profile.
     */
    public static function add_transaction_tab()
    {
        bp_core_new_nav_item([
            'name'            => __('Transaction History', 'football-tips'),
            'slug'            => 'transaction-history',
            'position'        => 40,
            'parent_url'      => trailingslashit(bp_loggedin_user_domain()) . 'profile/',
            'parent_slug'     => 'profile',
            'screen_function' => [__CLASS__, 'render_transaction_tab'],
            'default_subnav_slug' => 'transaction-history',
        ]);
    }

    /**
     * Render the transaction history tab content.
     */
    public static function render_transaction_tab()
    {
        add_action('bp_template_content', [__CLASS__, 'display_transaction_table']);
        bp_core_load_template('members/single/plugins');
    }

    /**
     * Enqueue required CSS and JS.
     */
    public static function enqueue_assets()
    {
        wp_enqueue_style(
            'football-tips-transactions-style',
            plugins_url('../assets/css/transactions.css', __FILE__),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'football-tips-transactions-script',
            plugins_url('../assets/js/transactions.js', __FILE__),
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('football-tips-transactions-script', 'FootballTipsTransactionsAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    /**
     * Fetch transactions via AJAX.
     */
    public static function fetch_transactions()
    {
        $user_id = get_current_user_id();
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        global $wpdb;
        $query = $wpdb->prepare("
            SELECT o.ID AS order_id, 
                   o.post_date AS order_date,
                   im.meta_value AS product_id,
                   oi.order_item_name AS product_name,
                   om.meta_value AS amount
            FROM {$wpdb->prefix}posts o
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta om ON oi.order_item_id = om.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON oi.order_item_id = im.order_item_id
            WHERE o.post_type = 'shop_order'
              AND om.meta_key = '_line_total'
              AND im.meta_key = '_product_id'
              AND im.meta_value = %d
              AND o.post_status = 'wc-completed'
            LIMIT %d OFFSET %d
        ", get_user_meta($user_id, 'tips_subscription_product_id', true), $per_page, $offset);

        $transactions = $wpdb->get_results($query);

        if ($transactions) {
            $html = '';
            foreach ($transactions as $transaction) {
                $html .= '<tr>';
                $html .= '<td>' . esc_html($transaction->order_id) . '</td>';
                $html .= '<td>' . esc_html(date('Y-m-d', strtotime($transaction->order_date))) . '</td>';
                $html .= '<td>' . esc_html($transaction->product_name) . '</td>';
                $html .= '<td>$' . esc_html(number_format($transaction->amount * 0.75, 2)) . '</td>'; // 75% after commission
                $html .= '</tr>';
            }

            wp_send_json_success([
                'html' => $html,
                'has_more' => count($transactions) === $per_page,
            ]);
        } else {
            wp_send_json_error(['message' => 'No transactions found.']);
        }
    }

    /**
     * Display the transaction table in the tab.
     */
    public static function display_transaction_table()
    {
        echo '<div id="transactions-container">';
        echo '<table id="transaction-history-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Order ID</th>';
        echo '<th>Date</th>';
        echo '<th>Subscription</th>';
        echo '<th>Amount Earned</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody></tbody>';
        echo '</table>';
        echo '<button id="load-more-transactions" style="display: none;">Load More</button>';
        echo '<div id="transactions-spinner" style="display: none;">Loading...</div>';
        echo '</div>';
    }
}

Football_Tips_Transactions::init();
