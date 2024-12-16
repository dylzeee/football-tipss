<?php

class Football_Tips_Profile_Results
{
    public static function init()
    {

        // add_shortcode('football_tips_leaderboard', [__CLASS__, 'render_leaderboard']);
        add_shortcode('football_tips_user_tips', [__CLASS__, 'display_user_tips']);

        //add_action('bp_setup_nav', [__CLASS__, 'add_bb_navigation']);
        add_action('bp_setup_nav', [__CLASS__, 'add_bb_navigation']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);

        add_action('wp_ajax_fetch_user_results', [__CLASS__, 'fetch_user_results']);
        add_action('wp_ajax_nopriv_fetch_user_results', [__CLASS__, 'fetch_user_results']);
    }

    /**
     * Enqueue styles for the frontend form.
     */
    public static function enqueue_styles()
    {
        // if (!bp_is_settings_component() || bp_current_action() !== 'tipping-settings') {
        //     return;
        // }

        wp_enqueue_script(
            'football-tips-profile-results',
            plugins_url('../assets/js/results.js', __FILE__),
            ['jquery'],
            '1.0',
            true
        );
        wp_localize_script('football-tips-profile-results', 'FootballTipsResultsAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    public static function fetch_user_results()
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

        // Add "Football Tips" tab to the profile navigation
        bp_core_new_nav_item([
            'name'            => __('Tip Results', 'football-tips'),
            'slug'            => 'tip-results',
            'position'        => 30,
            'parent_url'      => trailingslashit(bp_loggedin_user_domain()) . 'profile/',
            'parent_slug'     => 'profile',
            'screen_function' => [__CLASS__, 'member_tips_tab_content'],
            'default_subnav_slug' => 'tip-results',
        ]);
    }

    /**
     * The content for the "members Tips" tab in BuddyPress profile.
     */
    public static function member_tips_tab_content()
    {
        add_action('bp_template_content', [__CLASS__, 'display_results_table']); // Display the results table here // Assuming display_user_results is your function to display the table
        bp_core_load_template('members/single/plugins');
    }

    public static function display_results_table()
    {
        $profile_user_id = bp_displayed_user_id();
        $current_user_id = get_current_user_id();

        if (!$profile_user_id) {
            echo '<p>Error: Unable to determine profile user.</p>';
            return;
        }


        echo '<div id="profile-results-container" data-user-id="' . esc_attr($profile_user_id) . '">';
        echo '<div class="results-spinner" style="display: none;">Loading...</div>'; // Spinner markup
        self::render_tips_table($profile_user_id); // Initial render for first page
        echo '</div>';
        return;
    }


    private static function render_tips_table($user_id, $page = 1)
    {
        global $wpdb;

        // Pagination parameters
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        // Query to fetch resulted tips
        $query = $wpdb->prepare("
        SELECT 
            t.id, 
            t.created_at, 
            t.sport_key, 
            t.market_key, 
            t.event_name, 
            t.selection, 
            r.result, 
            r.profit_loss
        FROM {$wpdb->prefix}football_tips t
        LEFT JOIN {$wpdb->prefix}football_tips_results r ON t.id = r.tip_id
        WHERE t.user_id = %d AND r.result IS NOT NULL
        ORDER BY t.created_at DESC
        LIMIT %d OFFSET %d
    ", $user_id, $per_page, $offset);

        $tips = $wpdb->get_results($query);

        // Query to fetch total count of resulted tips
        $total_tips = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}football_tips t
        LEFT JOIN {$wpdb->prefix}football_tips_results r ON t.id = r.tip_id
        WHERE t.user_id = %d AND r.result IS NOT NULL
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
        echo '<th>Result</th>';
        echo '<th>Profit/Loss</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        if ($tips) {
            foreach ($tips as $tip) {
                // Determine the class for the result column
                $result_class = '';
                switch ($tip->result) {
                    case 'win':
                        $result_class = 'result-win'; // Green
                        break;
                    case 'loss':
                        $result_class = 'result-loss'; // Red
                        break;
                    case 'push':
                        $result_class = 'result-push'; // Neutral
                        break;
                }

                // Extract selection name (remove the odds part)
                $selection = explode(' - ', $tip->selection)[0];

                echo '<tr class="' . esc_attr($result_class) . '">';
                echo '<td>' . esc_html(date('Y-m-d', strtotime($tip->created_at))) . '</td>';
                echo '<td>' . esc_html(self::get_sport_label($tip->sport_key)) . '</td>';
                echo '<td>' . esc_html($tip->event_name) . '</td>'; // Event column
                echo '<td>' . esc_html(self::get_market_label($tip->market_key)) . '</td>';
                echo '<td>' . esc_html($selection) . '</td>'; // Selection column (without odds)
                echo '<td>' . esc_html(ucfirst($tip->result)) . '</td>';
                echo '<td>' . esc_html(number_format($tip->profit_loss, 2)) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7">This user has not posted any resulted tips yet.</td></tr>';
        }
        echo '</tbody>';
        echo '</table>';

        // Render pagination
        self::render_pagination($total_tips, $page, $per_page, 'resulted');
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
            'soccer_epl' => 'English Premier League',
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

Football_Tips_Profile_Results::init();
