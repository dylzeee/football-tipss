<?php

class Football_Tips_Profile
{
    public static function init()
    {

        // add_shortcode('football_tips_leaderboard', [__CLASS__, 'render_leaderboard']);
        add_shortcode('football_tips_user_tips', [__CLASS__, 'display_user_tips']);

        //add_action('bp_setup_nav', [__CLASS__, 'add_bb_navigation']);
        add_action('bp_setup_nav', [__CLASS__, 'add_bb_navigation']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);

        add_action('wp_ajax_fetch_user_tips', [__CLASS__, 'fetch_user_tips']);
        add_action('wp_ajax_nopriv_fetch_user_tips', [__CLASS__, 'fetch_user_tips']);
    }

    /**
     * Enqueue styles for the frontend form.
     */
    public static function enqueue_styles()
    {
        // if (!bp_is_settings_component() || bp_current_action() !== 'tipping-settings') {
        //     return;
        // }

        wp_enqueue_style(
            'football-tips-frontend-features',
            plugins_url('../assets/css/frontend-features.css', __FILE__),
            [],
            '1.0.0',
            'all'
        );

        wp_enqueue_script(
            'football-tips-profile',
            plugins_url('../assets/js/profile.js', __FILE__),
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('football-tips-profile', 'FootballTipsProfileAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    public static function fetch_user_tips()
    {
        $profile_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;

        if (!$profile_user_id) {
            wp_send_json_error(['message' => 'Invalid user ID']);
        }

        ob_start();
        self::render_tips_table($profile_user_id, $page); // Pass the current page
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }



    /**
     * Add custom tab to BuddyPress profile.
     */
    public static function add_bb_navigation()
    {

        error_log("what in the plugs");
        // Add "Football Tips" tab to the profile navigation
        bp_core_new_nav_item([
            'name'            => __('Member Tips', 'football-tips'),
            'slug'            => 'member-tips',
            'position'        => 30,
            'parent_url'      => trailingslashit(bp_loggedin_user_domain()) . 'profile/',
            'parent_slug'     => 'profile',
            'screen_function' => [__CLASS__, 'member_tips_tab_content'],
            'default_subnav_slug' => 'member-tips',
        ]);
    }

    /**
     * The content for the "members Tips" tab in BuddyPress profile.
     */
    public static function member_tips_tab_content()
    {
        add_action('bp_template_content', [__CLASS__, 'display_tips_table']); // Display the tips table here // Assuming display_user_tips is your function to display the table
        bp_core_load_template('members/single/plugins');
    }

    public static function display_tips_table()
    {
        $profile_user_id = bp_displayed_user_id();
        $current_user_id = get_current_user_id();

        if (!$profile_user_id) {
            echo '<p>Error: Unable to determine profile user.</p>';
            error_log("Error: Unable to determine profile user.");
            return;
        }

        // If the user is viewing their own profile, always show tips
        if ($profile_user_id === $current_user_id) {
            error_log("Viewing own profile: $profile_user_id");
            echo '<div id="profile-tips-container" data-user-id="' . esc_attr($profile_user_id) . '">';
            echo '<div class="tips-spinner" style="display: none;">Loading...</div>'; // Spinner markup
            self::render_tips_table($profile_user_id); // Initial render for first page
            echo '</div>';
            return;
        }

        // Fetch tips visibility
        $visibility = get_user_meta($profile_user_id, 'tips_visibility', true);
        error_log("Tips visibility for user $profile_user_id: $visibility");

        // Check if the user's tips are private
        if ($visibility === 'private') {
            $subscription_product_id = get_user_meta($profile_user_id, 'tips_subscription_product_id', true);
            error_log("Subscription product ID for user $profile_user_id: $subscription_product_id");

            if (!$subscription_product_id) {
                echo '<p>This user’s tips are private and not currently available for subscription.</p>';
                return;
            }

            $product = wc_get_product($subscription_product_id);

            if ($product && $product->is_type('subscription')) {
                // Check if the current user is subscribed to this product
                $subscriptions = wcs_get_users_subscriptions($current_user_id);
                error_log("Subscriptions for user $current_user_id: " . print_r($subscriptions, true));

                $is_subscribed = false;
                foreach ($subscriptions as $subscription) {
                    if ($subscription->has_status(['active', 'on-hold'])) {
                        // Check if the subscription includes the product
                        foreach ($subscription->get_items() as $item) {
                            if ((int) $item->get_product_id() === (int) $subscription_product_id) {
                                $is_subscribed = true;
                                break 2; // Exit both loops
                            }
                        }
                    }
                }

                error_log("Is subscribed: " . ($is_subscribed ? "Yes" : "No"));

                if ($is_subscribed) {
                    // Render the tips table
                    echo '<div id="profile-tips-container" data-user-id="' . esc_attr($profile_user_id) . '">';
                    echo '<div class="tips-spinner" style="display: none;">Loading...</div>'; // Spinner markup
                    self::render_tips_table($profile_user_id); // Initial render for first page
                    echo '</div>';
                    return;
                }

                // Display subscription message if not subscribed
                echo '<div class="private-tips-message">';
                echo '<p>This user’s tips are private and available for $' . esc_html($product->get_price()) . '/month.</p>';
                echo '<a href="/cart?add-to-cart=' . esc_html($product->get_id()) . '&quantity=1" class="button subscribe-now">Subscribe Now</a>';
                echo '</div>';
            } else {
                error_log("No valid subscription product found for user $profile_user_id.");
                echo '<p>This user’s tips are private and not currently available for subscription.</p>';
            }

            return;
        }

        // Display public tips for other users
        error_log("Displaying public tips for user $profile_user_id.");
        echo '<div id="profile-tips-container" data-user-id="' . esc_attr($profile_user_id) . '">';
        echo '<div class="tips-spinner" style="display: none;">Loading...</div>'; // Spinner markup
        self::render_tips_table($profile_user_id); // Initial render for first page
        echo '</div>';
    }



    private static function render_tips_table($user_id, $page = 1)
    {
        global $wpdb;

        // Pagination parameters
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        // Query to fetch unresulted tips for the user
        $query = $wpdb->prepare("
        SELECT 
            t.id, 
            t.created_at, 
            t.sport_key, 
            t.market_key, 
            t.stake, 
            t.odds, 
            t.event_name, 
            t.selection
        FROM {$wpdb->prefix}football_tips t
        LEFT JOIN {$wpdb->prefix}football_tips_results r ON t.id = r.tip_id
        WHERE t.user_id = %d AND r.result IS NULL
        ORDER BY t.created_at DESC
        LIMIT %d OFFSET %d
    ", $user_id, $per_page, $offset);

        $tips = $wpdb->get_results($query);

        // Query to fetch total count of unresulted tips
        $total_tips = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}football_tips t
        LEFT JOIN {$wpdb->prefix}football_tips_results r ON t.id = r.tip_id
        WHERE t.user_id = %d AND r.result IS NULL
    ", $user_id));

        // Render table
        echo '<table class="tips-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Date</th>';
        echo '<th>Sport</th>';
        echo '<th>Event</th>';
        echo '<th>Market</th>';
        echo '<th>Selection</th>';
        echo '<th>Odds</th>';
        echo '<th>Stake</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        if ($tips) {
            foreach ($tips as $tip) {
                // Extract selection name (remove the odds part)
                $selection = explode(' - ', $tip->selection)[0];

                echo '<tr>';
                echo '<td>' . esc_html(date('Y-m-d', strtotime($tip->created_at))) . '</td>';
                echo '<td>' . esc_html(self::get_sport_label($tip->sport_key)) . '</td>';
                echo '<td>' . esc_html($tip->event_name) . '</td>'; // Event column
                echo '<td>' . esc_html(self::get_market_label($tip->market_key)) . '</td>';
                echo '<td>' . esc_html($selection) . '</td>'; // Selection column (without odds)
                echo '<td>' . esc_html(number_format($tip->odds, 2)) . '</td>';
                echo '<td>' . esc_html(number_format($tip->stake, 2)) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">No unresulted tips available.</td></tr>';
        }
        echo '</tbody>';
        echo '</table>';

        // Render pagination
        self::render_pagination($total_tips, $page, $per_page, 'unresulted');
    }








    private static function render_pagination($total_tips, $current_page, $per_page, $type)
    {
        $total_pages = ceil($total_tips / $per_page);

        if ($total_pages <= 1) {
            return; // No pagination needed
        }

        echo '<div class="pagination">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $active_class = ($i === $current_page) ? 'active' : '';
            echo '<button class="pagination-button ' . esc_attr($active_class) . '" data-page="' . esc_attr($i) . '" data-type="' . esc_attr($type) . '">' . esc_html($i) . '</button>';
        }
        echo '</div>';
    }




    private static function get_sport_label($sport_key)
    {
        $sports = [
            'soccer_epl' => 'English Premier League (EPL)',
            'soccer_efl_champ' => 'EFL Championship',
            'soccer_england_league1' => 'England League 1',
            'soccer_england_league2' => 'England League 2',
            'soccer_spain_la_liga' => 'Spain La Liga',
            'soccer_france_ligue_one' => 'Ligue 1 (France)',
            'soccer_germany_bundesliga' => 'Bundesliga (Germany)',
            'soccer_italy_serie_a' => 'Serie A (Italy)',
            'soccer_australia_aleague' => 'Australian A-League',
            'soccer_spl' => 'Scottish Premier League',
            'soccer_uefa_champs_league' => 'UEFA Champions League',
            'soccer_uefa_europa_league' => 'UEFA Europa League',
            'soccer_fa_cup' => 'FA Cup',
            'soccer_england_efl_cup' => 'EFL Cup',
        ];

        return $sports[$sport_key] ?? ucfirst(str_replace('_', ' ', $sport_key));
    }

    private static function get_market_label($market_key)
    {
        $markets = [
            'h2h' => 'Match Result',
            'draw_no_bet' => 'Draw No Bet',
            'double_chance' => 'Double Chance',
            'btts' => 'Both Teams to Score',
            'alternate_spreads' => 'Asian Handicap',
            'totals' => 'Total Goals Over/Under',
            'team_totals' => 'Team Total Goals',
            'alternate_totals' => 'Alternate Totals',
            'totals_h1' => '1st Half Total Goals',
            'totals_h2' => '2nd Half Total Goals',
            'player_goal_scorer_anytime' => 'Anytime Goal Scorer',
        ];

        return $markets[$market_key] ?? ucfirst(str_replace('_', ' ', $market_key));
    }
}

Football_Tips_Profile::init();
