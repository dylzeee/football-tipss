<?php

class Football_Tips_Withdraw
{

    public static function init()
    {
        add_action('init', function () {
            add_rewrite_endpoint('withdraw', EP_PAGES);
        });

        add_filter('query_vars', function ($vars) {
            $vars[] = 'withdraw';
            return $vars;
        });

        add_filter('woocommerce_account_menu_items', function ($items) {
            $logout = $items['customer-logout'];
            unset($items['customer-logout']); // Temporarily remove logout

            $items['withdraw'] = __('Withdraw Funds', 'football-tips');
            $items['customer-logout'] = $logout; // Add logout back

            return $items;
        });

        add_action('woocommerce_account_withdraw_endpoint', function () {
            self::render_withdrawal_form();
        });

        add_action('init', function () {
            add_action('woo_wallet_after_debit', function ($user_id, $amount, $details, $type) {
                if ($type === 'payout') {
                    $admin_email = get_option('admin_email');
                    wp_mail($admin_email, 'New Withdrawal Request', "A user has requested a withdrawal of $amount.");
                }
            });
        });
    }

    public static function render_withdrawal_form()
    {
        $user_id = get_current_user_id();
        error_log("THE USER ID IS........." . $user_id);
        $balance = woo_wallet()->wallet->get_wallet_balance($user_id);
        error_log("THE USER BALANCE IS........." . $balance);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::process_withdrawal_request($user_id, $balance);
        }

?>
        <h3><?php _e('Request Withdrawal', 'football-tips'); ?></h3>
        <p>Current balance: <?php echo $balance; ?></p>
        <form method="POST">
            <label for="withdrawal-amount"><?php _e('Withdrawal Amount', 'football-tips'); ?></label>
            <input type="number" style="display:block; margin-bottom:20px;" id="withdrawal-amount" name="withdrawal_amount" min="1" max="<?php echo esc_attr($balance); ?>" required>
            <button type="submit" class="button"><?php _e('Request Withdrawal', 'football-tips'); ?></button>
        </form>
<?php
    }

    private static function process_withdrawal_request($user_id, $balance)
    {
        $amount = floatval($_POST['withdrawal_amount']);
        $notes = sanitize_text_field($_POST['withdrawal_notes']);

        if ($amount <= 0 || $amount > $balance) {
            wc_add_notice(__('Invalid withdrawal amount.', 'football-tips'), 'error');
            return;
        }

        // Store the request in the database
        global $wpdb;
        $table_name = $wpdb->prefix . 'tipster_payout_requests';
        $wpdb->insert($table_name, [
            'tipster_id'     => $user_id,
            'amount'         => $amount,
            'status'         => 'pending',
            'request_date'   => current_time('mysql'),
            'notes'          => $notes,
        ]);

        wc_add_notice(__('Withdrawal request submitted successfully.', 'football-tips'), 'success');
    }
}

Football_Tips_Withdraw::init();
