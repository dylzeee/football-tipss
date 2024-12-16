<?php

class Football_Tips_Profile_Subscription
{
    public static function init()
    {
        add_action('bp_after_member_header', [__CLASS__, 'add_subscription_button']);
    }

    public static function add_subscription_button()
    {
        if (bp_is_settings_component() || bp_current_action() == 'tipping-settings') {
            return;
        } else {
            return;
        }

        $user_id = bp_displayed_user_id();
        $product_id = get_user_meta($user_id, 'tips_subscription_product_id', true);

        if (!$product_id) {
            return;
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return;
        }

        $subscription_url = add_query_arg('add-to-cart', $product_id, wc_get_cart_url());
        echo '<a href="' . esc_url($subscription_url) . '" class="button subscribe-button">Subscribe to Tips</a>';
    }
}

Football_Tips_Profile_Subscription::init();
