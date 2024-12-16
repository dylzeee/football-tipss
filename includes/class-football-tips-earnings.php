<?php

class Football_Tips_Earnings
{
    public static function init()
    {
        // Hook into WooCommerce Subscription events
        add_action('woocommerce_subscription_payment_complete', [__CLASS__, 'log_subscription_earnings'], 10, 1);
    }

    /**
     * Log subscription earnings into the database.
     *
     * @param WC_Subscription $subscription The WooCommerce subscription object.
     */
    public static function log_subscription_earnings($subscription)
    {
        global $wpdb;

        // Ensure this is a valid subscription object
        if (!$subscription instanceof WC_Subscription) {
            error_log('Invalid subscription object passed to log_subscription_earnings.');
            return;
        }

        $subscription_id = $subscription->get_id();
        $items = $subscription->get_items(); // Get all items in the subscription

        foreach ($items as $item) {
            $product_id = $item->get_product_id(); // Retrieve the product ID
            $tipster_id = get_post_meta($product_id, '_tipster_id', true); // Fetch the tipster ID

            if (!$tipster_id) {
                error_log("No tipster linked to product ID: {$product_id}");
                continue;
            }

            // Calculate earnings and commission
            $amount = $subscription->get_total();
            $commission_rate = 0.25; // 25% commission
            $commission = $amount * $commission_rate;
            $net_earnings = $amount - $commission;

            // Check if earnings are already logged for this subscription
            $existing_entry = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}tipster_earnings 
            WHERE subscription_id = %d AND type = 'earning'
        ", $subscription_id));

            if ($existing_entry) {
                error_log("Earnings already logged for subscription ID: {$subscription_id}");
                return; // Skip duplicate logging
            }

            // Log earnings in the database
            $wpdb->insert(
                "{$wpdb->prefix}tipster_earnings",
                [
                    'tipster_id'     => $tipster_id,
                    'subscription_id' => $subscription_id,
                    'amount'          => $amount,
                    'commission'      => $commission,
                    'net_earnings'    => $net_earnings,
                    'type'            => 'earning',
                    'status'          => 'completed',
                    'created_at'      => current_time('mysql'),
                ],
                ['%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s']
            );

            error_log("Earnings logged for subscription ID: {$subscription_id}, Tipster ID: {$tipster_id}");
        }
    }

    public static function get_current_balance($user_id)
    {
        global $wpdb;

        $earnings_table = $wpdb->prefix . 'tipster_earnings';

        // Query to sum net earnings for all pending payouts
        $balance = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(net_earnings)
        FROM $earnings_table
        WHERE tipster_id = %d AND status = 'pending'
    ", $user_id));

        return $balance ? floatval($balance) : 0.00;
    }
}

Football_Tips_Earnings::init();
