<?php

class Football_Tips_Visibility
{

    public static function init()
    {
        // Add "Tipping Settings" to BuddyBoss Settings tab.
        add_action('bp_settings_setup_nav', [__CLASS__, 'add_tipping_settings_tab']);
        add_action('bp_template_content', [__CLASS__, 'display_tipping_settings_form'], 10, 2);

        // Save settings on form submission.
        add_action('bp_screens', [__CLASS__, 'save_tipping_settings']);
    }

    /**
     * Add Tipping Settings Tab to BuddyBoss Profile Settings.
     */
    public static function add_tipping_settings_tab()
    {
        bp_core_new_subnav_item([
            'name'            => __('Tipping Settings', 'football-tips'),
            'slug'            => 'tipping-settings',
            'parent_url'      => bp_loggedin_user_domain() . 'settings/',
            'parent_slug'     => 'settings',
            'screen_function' => [__CLASS__, 'load_tipping_settings_template'],
            'position'        => 40,
            'user_has_access' => bp_is_my_profile(), // Only for the logged-in user.
        ]);
    }

    /**
     * Load Tipping Settings Form Template.
     */
    public static function load_tipping_settings_template()
    {
        add_action('bp_template_content', [__CLASS__, 'display_tipping_settings_form']);
        bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
    }

    /**
     * Display Tipping Settings Form.
     */
    /**
     * Display the tipping settings form.
     */
    public static function display_tipping_settings_form()
    {
        if (!bp_is_settings_component() || bp_current_action() !== 'tipping-settings') {
            return;
        }

        // Get current user ID and tipping settings
        $user_id = get_current_user_id();
        $tips_visibility = get_user_meta($user_id, 'tips_visibility', true) ?: 'public';
        $subscription_price = get_user_meta($user_id, 'tips_subscription_price', true) ?: '10.00'; // Default price

?>
        <form action="" method="post" id="tipping-settings-form">
            <?php wp_nonce_field('save_tipping_settings', 'tipping_settings_nonce'); ?>

            <h3><?php esc_html_e('Tipping Settings', 'football-tips'); ?></h3>

            <label for="tips-visibility"><?php esc_html_e('Tips Visibility', 'football-tips'); ?></label>
            <select name="tips_visibility" id="tips-visibility">
                <option value="public" <?php selected($tips_visibility, 'public'); ?>><?php esc_html_e('Public', 'football-tips'); ?></option>
                <option value="private" <?php selected($tips_visibility, 'private'); ?>><?php esc_html_e('Private (Subscribers Only)', 'football-tips'); ?></option>
            </select>

            <label for="subscription-price"><?php esc_html_e('Subscription Price ($)', 'football-tips'); ?></label>
            <input type="number" step="0.01" min="1" name="subscription_price" id="subscription-price" value="<?php echo esc_attr($subscription_price); ?>">

            <button type="submit"><?php esc_html_e('Save Settings', 'football-tips'); ?></button>
        </form>
<?php
    }


    /**
     * Save Tipping Settings on Form Submission.
     */
    /**
     * Save the tipping settings.
     */
    public static function save_tipping_settings()
    {
        error_log("Saving was called!");
        if (!isset($_POST['tipping_settings_nonce']) || !wp_verify_nonce($_POST['tipping_settings_nonce'], 'save_tipping_settings')) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Save tips visibility
        $tips_visibility = sanitize_text_field($_POST['tips_visibility']);
        update_user_meta($user_id, 'tips_visibility', $tips_visibility);

        // Save subscription price
        $subscription_price = floatval($_POST['subscription_price']);
        if ($subscription_price < 1) {
            $subscription_price = 1; // Minimum price
        }
        update_user_meta($user_id, 'tips_subscription_price', $subscription_price);

        // Update WooCommerce product
        self::update_subscription_product($user_id, $subscription_price);
        // Call the subscription creation logic directly
        Football_Tips_Subscriptions::create_subscription_product($user_id);

        // Redirect to avoid re-submission
        wp_safe_redirect(bp_displayed_user_domain() . 'settings/tipping-settings/?updated=true');
        exit;
    }

    /**
     * Update the subscription product price.
     */
    private static function update_subscription_product($user_id, $subscription_price)
    {
        // Fetch the subscription product ID for the user
        $product_id = get_user_meta($user_id, 'tips_subscription_product_id', true);
        if (!$product_id) {
            return;
        }
        error_log("uodate sub still goingg!");
        // Update the product price
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('subscription')) {
            $product->set_regular_price($subscription_price);
            $product->save();
        }
    }
}

Football_Tips_Visibility::init();
