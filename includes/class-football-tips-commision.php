<?php

class Football_Tips_Commission

{

    public static function init()
    {
        add_action('woocommerce_subscription_payment_complete', [__CLASS__, 'apply_commission']);
    }

    public static function apply_commission($subscription)
    {
        $product_id = $subscription->get_items()[0]->get_product_id();
        $tipster_id = self::get_user_by_subscription_product($product_id);

        if (!$tipster_id) {
            return;
        }

        $order_total = $subscription->get_total();
        $commission = $order_total * 0.25; // 25% commission.

        // Transfer commission to site owner.
        self::record_commission($commission, $tipster_id);
    }

    private static function get_user_by_subscription_product($product_id)
    {
        global $wpdb;
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'tips_subscription_product_id' AND meta_value = %d",
            $product_id
        ));
        return $user_id;
    }

    private static function record_commission($commission, $tipster_id)
    {
        // Logic to track commission (e.g., store in database or send payout).
    }
}

Football_Tips_Commission::init();
