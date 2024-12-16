<?php

/**
 * Admin settings class for Football Tips plugin.
 */
class Football_Tips_Admin
{

    /**
     * Initialize hooks for admin functionality.
     */
    public static function init()
    {
        // Add admin menus and submenus.
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        // Handle form submission for saving tip results.
        add_action('admin_post_save_tip_results', [__CLASS__, 'handle_save_results']);
        // Register settings for the Settings page.
        add_action('admin_init', [__CLASS__, 'register_settings']);

        add_filter('bp_notifications_get_registered_components', [__CLASS__, 'register_notification_component']);
        add_filter('bp_notifications_get_notifications_for_user', [__CLASS__, 'format_notification'], 10, 5);
    }

    /**
     * Register the notification component for BuddyBoss.
     *
     * @param array $components Registered components.
     * @return array Updated components list.
     */
    public static function register_notification_component($components)
    {
        if (!in_array('football_tips', $components, true)) {
            $components[] = 'football_tips';
        }
        return $components;
    }



    /**
     * Add top-level menu and submenus for Football Tips.
     */
    public static function add_admin_menu()
    {
        // Add top-level menu for "Football Tips."
        add_menu_page(
            __('Football Tips', 'football-tips'), // Page title.
            __('Football Tips', 'football-tips'), // Menu title.
            'manage_options',                    // Capability.
            'football-tips',                     // Menu slug for top-level menu.
            [__CLASS__, 'render_settings_page'], // Default callback (Settings page).
            'dashicons-chart-line',              // Icon URL.
            25                                   // Position.
        );

        // Add "Settings" submenu.
        add_submenu_page(
            'football-tips',                      // Parent slug.
            __('Settings', 'football-tips'),      // Page title.
            __('Settings', 'football-tips'),      // Menu title.
            'manage_options',                     // Capability.
            'football-tips-settings',             // Menu slug.
            [__CLASS__, 'render_settings_page']   // Callback function.
        );

        // Add "Result Tips" submenu.
        add_submenu_page(
            'football-tips',                      // Parent slug.
            __('Result Tips', 'football-tips'),   // Page title.
            __('Result Tips', 'football-tips'),   // Menu title.
            'manage_options',                     // Capability.
            'football-tips-result-tips',          // Menu slug.
            [__CLASS__, 'render_result_tips_page'] // Callback function.
        );
    }

    /**
     * Render the Settings page.
     */
    public static function render_settings_page()
    {
?>
        <div class="wrap">
            <h1><?php esc_html_e('Football Tips Settings', 'football-tips'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('football_tips_settings');
                do_settings_sections('football-tips-settings');
                submit_button();
                ?>
            </form>
        </div>
    <?php
    }

    /**
     * Register settings for the Settings page.
     */
    public static function register_settings()
    {
        register_setting('football_tips_settings', 'football_tips_api_key');
        register_setting('football_tips_settings', 'football_tips_enabled_sports');

        add_settings_section(
            'football_tips_api_key_section',
            __('API Configuration', 'football-tips'),
            null,
            'football-tips-settings'
        );

        add_settings_field(
            'football_tips_api_key',
            __('API Key', 'football-tips'),
            [__CLASS__, 'render_api_key_field'],
            'football-tips-settings',
            'football_tips_api_key_section'
        );

        add_settings_section(
            'football_tips_sports_section',
            __('Manage Sports', 'football-tips'),
            null,
            'football-tips-settings'
        );

        add_settings_field(
            'football_tips_sports',
            __('Enabled Sports', 'football-tips'),
            [__CLASS__, 'render_sports_selection_field'],
            'football-tips-settings',
            'football_tips_sports_section'
        );
    }

    /**
     * Render the API key input field.
     */
    public static function render_api_key_field()
    {
        $api_key = get_option('football_tips_api_key', '');
        echo '<input type="text" name="football_tips_api_key" value="' . esc_attr($api_key) . '" style="width: 100%;">';
    }



    /**
     * Render the Result Tips page.
     */
    public static function render_result_tips_page()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'football_tips';

        $pending_tips = $wpdb->get_results("SELECT * FROM $table_name WHERE resulted = 0", ARRAY_A);

    ?>
        <div class="wrap">
            <h1><?php esc_html_e('Result Tips', 'football-tips'); ?></h1>
            <?php if (empty($pending_tips)) : ?>
                <p><?php esc_html_e('No pending tips to result.', 'football-tips'); ?></p>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('User', 'football-tips'); ?></th>
                                <th><?php esc_html_e('Event', 'football-tips'); ?></th>
                                <th><?php esc_html_e('Market', 'football-tips'); ?></th>
                                <th><?php esc_html_e('Selection', 'football-tips'); ?></th>
                                <th><?php esc_html_e('Stake', 'football-tips'); ?></th>
                                <th><?php esc_html_e('Odds', 'football-tips'); ?></th>
                                <th><?php esc_html_e('Result', 'football-tips'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_tips as $tip) : ?>
                                <tr>
                                    <td><?php echo esc_html(bp_core_get_user_displayname($tip['user_id'])); ?></td>
                                    <td><?php echo esc_html($tip['event_name']); ?></td>
                                    <td><?php echo esc_html($tip['market_key']); ?></td>
                                    <td><?php echo esc_html($tip['selection']); ?></td>
                                    <td><?php echo esc_html($tip['stake']); ?></td>
                                    <td><?php echo esc_html($tip['odds']); ?></td>
                                    <td>
                                        <select name="results[<?php echo esc_attr($tip['id']); ?>]">
                                            <option value=""><?php esc_html_e('Select Result', 'football-tips'); ?></option>
                                            <option value="win"><?php esc_html_e('Win', 'football-tips'); ?></option>
                                            <option value="loss"><?php esc_html_e('Loss', 'football-tips'); ?></option>
                                            <option value="push"><?php esc_html_e('Push', 'football-tips'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <input type="hidden" name="action" value="save_tip_results">
                    <?php wp_nonce_field('save_tip_results_action'); ?>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Results', 'football-tips'); ?></button>
                </form>
            <?php endif; ?>
        </div>
<?php
    }


    /**
     * Handle saving tip results.
     */
    public static function handle_save_results()
    {
        // Verify nonce for security.
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'save_tip_results_action')) {
            wp_die(__('Invalid nonce specified.', 'football-tips'));
        }

        // Check user permissions.
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'football-tips'));
        }

        if (empty($_POST['results'])) {
            wp_redirect(admin_url('admin.php?page=football-tips-result-tips&error=no-results'));
            exit;
        }

        global $wpdb;
        $tips_table = $wpdb->prefix . 'football_tips';
        $results_table = $wpdb->prefix . 'football_tips_results';

        foreach ($_POST['results'] as $tip_id => $result) {
            $result = sanitize_text_field($result);

            // Skip invalid results.
            if (!in_array($result, ['win', 'loss', 'push'], true)) {
                continue;
            }

            // Fetch the tip data for profit/loss calculation.
            $tip = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tips_table WHERE id = %d", $tip_id), ARRAY_A);

            if (!$tip) {
                error_log("Failed to retrieve tip with ID $tip_id.");
                continue;
            }

            $stake = (float) $tip['stake'];
            $odds = (float) $tip['odds'];
            $profit_loss = 0.00; // Default value.

            // Calculate profit or loss based on the result.
            if ($result === 'win') {
                $profit_loss = $stake * ($odds - 1); // Profit = Stake * (Odds - 1).
            } elseif ($result === 'loss') {
                $profit_loss = -$stake; // Loss = -Stake.
            }

            // Add a new row to the results table with profit/loss.
            $inserted = $wpdb->insert(
                $results_table,
                [
                    'tip_id'      => (int) $tip_id,
                    'result'      => $result,
                    'profit_loss' => $profit_loss,
                    'processed_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%f', '%s']
            );

            if ($inserted === false) {
                error_log('Failed to insert into results table: ' . $wpdb->last_error);
                continue; // Skip further processing for this tip.
            }

            // Update the resulted column in the main tips table.
            $updated = $wpdb->update(
                $tips_table,
                ['resulted' => 1],
                ['id' => (int) $tip_id],
                ['%d'],
                ['%d']
            );

            if ($updated === false) {
                error_log('Failed to update resulted column: ' . $wpdb->last_error);
                continue; // Skip further processing for this tip.
            }

            // Process the result for additional actions (e.g., GamiPress, BuddyBoss).
            self::process_tip_result($tip, $result, $tip_id);
        }

        wp_redirect(admin_url('admin.php?page=football-tips-result-tips&success=true'));
        exit;
    }


    private static function process_tip_result($tip, $result, $tip_id)
    {
        $user_id = (int) $tip['user_id'];
        $stake = (float) $tip['stake'];
        $odds = (float) $tip['odds'];
        $user_name = bp_core_get_user_displayname($user_id);
        $event_name = $tip['event_name'];

        if ($result != 'loss') {
            if ($result === 'win') {
                $profit = $stake * ($odds - 1);
                $total_return = $stake + $profit; // Total = Stake + Profit.    
            } elseif ($result === 'push') {
                $profit = 0.00;
                $total_return = $stake; // Only the stake is returned for a push.
            }

            // Award total return in GamiPress.
            if (function_exists('gamipress_award_points_to_user')) {
                gamipress_award_points_to_user($user_id, $total_return, 'betting-coins');
            }

            // Log BuddyPress activity for a win or push.
            if ($result === 'win') {
                $activity_message = sprintf(
                    __("%s's tip on %s was a WINNER and earned $%s profit.", 'football-tips'),
                    $user_name,
                    $event_name,
                    number_format($profit, 2)
                );
                $activity_type = 'win';
            }

            bp_activity_add([
                'user_id'    => $user_id,
                'action'     => $activity_message,
                'component'  => 'football_tips',
                'type'       => $activity_type,
            ]);
        }

        error_log("about to notify the user with user_id: $user_id, Event Name: $event_name, result: $result, Profit: $profit, Stake: $stake, and the Tip ID: $tip_id");
        // Notify the user for all results.
        self::notify_user($user_id, $event_name, $result, $profit, $stake, $tip_id);
    }

    /**
     * Notifies users of tip results
     */
    public static function notify_user($user_id, $event_name, $result, $profit = 0.00, $stake = 0.00, $tip_id)
    {
        $message = ''; // Construct the notification message.
        switch ($result) {
            case 'win':
                $message = sprintf(
                    __("Your tip on %s was a WIN! You earned $%s profit.", 'football-tips'),
                    $event_name,
                    number_format($profit, 2)
                );
                break;
            case 'loss':
                $message = sprintf(
                    __("Your tip on %s was a LOSS. You lost your stake of $%s.", 'football-tips'),
                    $event_name,
                    number_format($stake, 2)
                );
                break;
            case 'push':
                $message = sprintf(
                    __("Your tip on %s resulted in a PUSH. Your stake of $%s has been returned.", 'football-tips'),
                    $event_name,
                    number_format($stake, 2)
                );
                break;
        }

        $additional_data = [
            'event_name' => $event_name,
            'profit'     => number_format($profit, 2),
            'stake'      => number_format($stake, 2),
            'result'     => $result,
        ];

        // Add BuddyBoss notification.
        if (function_exists('bp_notifications_add_notification')) {
            $notification_id = bp_notifications_add_notification([
                'user_id'           => $user_id,
                'item_id'           => 0, // Temporarily set to 0; we'll update it.
                'secondary_item_id' => 0,
                'component_name'    => 'football_tips',
                'component_action'  => 'tip_result',
                'date_notified'     => bp_core_current_time(),
                'is_new'            => 1,
            ]);

            // Update the notification item_id to be the notification_id.
            if ($notification_id) {
                global $wpdb;

                $wpdb->update(
                    $wpdb->prefix . 'bp_notifications',
                    ['item_id' => $notification_id],
                    ['id' => $notification_id]
                );

                // Save additional metadata for the notification.
                bp_notifications_add_meta($notification_id, 'additional_data', wp_json_encode($additional_data));
            }
        }

        // Send email notification as fallback or alongside.
        $user = get_userdata($user_id);
        if ($user) {
            $subject = __('Your Tip Result', 'football-tips');
            wp_mail($user->user_email, $subject, $message);
        }
    }

    /**
     * Format the notification for display.
     *
     * @param string $action            The action of the notification.
     * @param int    $item_id           The item ID.
     * @param int    $secondary_item_id Secondary item ID.
     * @param int    $total_items       Total items for grouped notifications.
     * @param string $format            The format of the notification (e.g., 'string' or 'html').
     * @return string Formatted notification message.
     */
    public static function format_notification($action, $item_id, $secondary_item_id, $total_items, $format)
    {
        error_log("Formatting notification: action={$action}, item_id={$item_id}, secondary_item_id={$secondary_item_id}");

        if ($action !== 'tip_result' || !$item_id) {
            return __('Your football tip has been processed.', 'football-tips');
        }

        // Fetch meta for the notification ID
        $notification_meta = bp_notifications_get_meta($item_id, 'additional_data');
        if (!$notification_meta) {
            error_log("Meta retrieval failed or empty for notification ID: {$item_id}");
            return __('Your football tip has been processed.', 'football-tips');
        }

        $additional_data = json_decode($notification_meta, true);
        if (!$additional_data || !is_array($additional_data)) {
            error_log("No additional data or invalid JSON for notification ID: {$item_id}");
            return __('Your football tip has been processed.', 'football-tips');
        }

        $event_name = $additional_data['event_name'] ?? __('an event', 'football-tips');
        $profit_loss = $additional_data['profit'] ?? '0.00';
        $stake = $additional_data['stake'] ?? '0.00';
        $result = ucfirst($additional_data['result'] ?? '');

        switch ($result) {
            case 'Win':
                $message = sprintf(
                    __("Your tip on %s was a %s. You earned $%s profit with a stake of $%s.", 'football-tips'),
                    $event_name,
                    $result,
                    $profit_loss,
                    $stake
                );
                break;
            case 'Loss':
                $message = sprintf(
                    __("Unfortunately your tip on %s did not win you lost your stake of $%s.", 'football-tips'),
                    $event_name,
                    $result,
                    $profit_loss,
                    $stake
                );
            case 'Push':
                $message = sprintf(
                    __("Your tip on %s was a %s. Your stake of $%s has been refunded.", 'football-tips'),
                    $event_name,
                    $result,
                    $profit_loss,
                    $stake
                );
            default:
                $message = sprintf(
                    __("Your tip on %s was a %s. Your stake of $%s has been refunded.", 'football-tips'),
                    $event_name,
                    $result,
                    $profit_loss,
                    $stake
                );
                break;
        }


        if ('string' === $format) {
            return $message;
        }

        $link = trailingslashit(bp_loggedin_user_domain()) . 'tips/';
        return sprintf('<a href="%s">%s</a>', esc_url($link), esc_html($message));
    }

    /**
     * Fetch sports from API.
     */
    private static function fetch_sports_from_api()
    {
        error_log("Called it bro");
        //return;
        // Implementation remains the same.
        $cached_sports = get_transient('ftm_sports_list');
        if ($cached_sports) {
            return $cached_sports;
        }

        $api_key = get_option('football_tips_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('API Key is missing.', 'sports-betting-tips')]);
        }

        $response = wp_remote_get("https://api.the-odds-api.com/v4/sports?apiKey={$api_key}");

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('Failed to fetch sports.', 'sports-betting-tips')]);
        }

        $body = wp_remote_retrieve_body($response);
        $sports = json_decode($body, true);

        if (! is_array($sports)) {
            wp_send_json_error(['message' => __('Invalid API response.', 'sports-betting-tips')]);
        }

        // Cache the response for 24 hours.
        set_transient('ftm_sports_list', $sports, DAY_IN_SECONDS);

        // wp_send_json_success($sports);

        return $sports;
    }

    /**
     * Render the sports selection checkboxes.
     */
    public static function render_sports_selection_field()
    {
        $enabled_sports = get_option('football_tips_enabled_sports', []);
        if (!is_array($enabled_sports)) {
            $enabled_sports = [];
        }

        $sports = self::fetch_sports_from_api();
        error_log(print_r($sports, true));

        if (empty($sports)) {
            echo '<p>' . esc_html__('Failed to fetch sports from the API. Please ensure your API key is valid.', 'football-tips') . '</p>';
            return;
        }

        foreach ($sports as $sport) {
            $sport_key = esc_attr($sport['key']);
            $sport_name = esc_html($sport['title']);
            $checked = in_array($sport_key, $enabled_sports, true) ? 'checked' : '';

            echo '<label style="display:block;margin-bottom:5px;">';
            echo '<input type="checkbox" name="football_tips_enabled_sports[]" value="' . $sport_key . '" ' . $checked . '> ';
            echo $sport_name;
            echo '</label>';
        }
    }
}

// Initialize the class.
Football_Tips_Admin::init();

//add_action('admin_init', ['Football_Tips_Admin', 'register_fields']);
