<?php

class Football_Tips_Access
{
    public static function init()
    {
        add_filter('display_football_tip', [__CLASS__, 'check_tip_visibility'], 10, 2);
    }

    public static function check_tip_visibility($content, $user_id)
    {
        $visibility = get_user_meta($user_id, 'tips_visibility', true) ?: 'public';

        if ($visibility === 'private' && !self::is_subscribed_to_user($user_id)) {
            return '<p><em>This tip is private. Subscribe to view.</em></p>';
        }

        return $content;
    }

    private static function is_subscribed_to_user($user_id)
    {
        $current_user_id = get_current_user_id();

        if (!$current_user_id) {
            return false;
        }

        $product_id = get_user_meta($user_id, 'tips_subscription_product_id', true);

        if (!$product_id) {
            return false;
        }

        return wcs_user_has_subscription($current_user_id, $product_id, 'active');
    }
}

Football_Tips_Access::init();
