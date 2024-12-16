<?php

class Football_Tips_Payouts
{
    public static function init()
    {
        add_action('init', [__CLASS__, 'register_my_account_endpoints']);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'add_payouts_menu_item']);
        add_action('woocommerce_account_payout-requests_endpoint', [__CLASS__, 'render_payout_requests']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);

        // Handle AJAX request for withdrawal
        add_action('wp_ajax_request_payout', [__CLASS__, 'handle_payout_request']);
        add_action('wp_ajax_nopriv_request_payout', '__return_false'); // Disable for unauthenticated users
    }

    /**
     * Create a 'Payout Requests' endpoint in WooCommerce My Account.
     */
    public static function register_my_account_endpoints()
    {
        add_rewrite_endpoint('payout-requests', EP_PAGES);
    }

    /**
     * Add 'Payout Requests' to WooCommerce My Account menu.
     */
    public static function add_payouts_menu_item($items)
    {
        $items['payout-requests'] = __('Payout Requests', 'football-tips');
        return $items;
    }

    /**
     * Enqueue styles and scripts for the payout functionality.
     */
    public static function enqueue_scripts()
    {
        if (!is_account_page()) {
            return;
        }

        wp_enqueue_script(
            'football-tips-payouts',
            plugins_url('../assets/js/payouts.js', __FILE__),
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('football-tips-payouts', 'FootballTipsPayoutsAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    /**
     * Render the 'Payout Requests' page in WooCommerce My Account.
     */
    public static function render_payout_requests()
    {
        echo '<div id="payout-requests-container">';
        echo '<h3>Payout Requests</h3>';
        echo '<div id="payout-form">';
        echo '<p>Current Balance: $<span id="current-balance">0.00</span></p>';
        echo '<label for="payout-amount">Request Amount:</label>';
        echo '<input type="number" id="payout-amount" min="0" step="0.01">';
        echo '<button id="submit-payout-request">Submit Request</button>';
        echo '</div>';
        echo '<h4>Your Previous Requests</h4>';
        echo '<table id="payout-history">';
        echo '<thead><tr><th>Request Date</th><th>Amount</th><th>Status</th><th>Resolution Date</th></tr></thead>';
        echo '<tbody><!-- AJAX Data --></tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Handle a tipster's payout request.
     */
    public static function handle_payout_request()
    {
        global $wpdb;

        // Get the current user
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'You must be logged in to request a payout.']);
        }

        // Validate and sanitize the requested amount
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid payout amount.']);
        }

        // Ensure the user has sufficient earnings
        $available_earnings = Football_Tips_Earnings::get_current_balance($user_id);
        if ($amount > $available_earnings) {
            wp_send_json_error(['message' => 'Insufficient balance for this request.']);
        }

        // Insert the payout request into the database
        $wpdb->insert("{$wpdb->prefix}tipster_payout_requests", [
            'tipster_id'    => $user_id,
            'amount'        => $amount,
            'status'        => 'pending',
            'request_date'  => current_time('mysql'),
        ]);

        wp_send_json_success(['message' => 'Your payout request has been submitted.']);
    }
}

Football_Tips_Payouts::init();
