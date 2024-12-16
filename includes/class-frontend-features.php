<?php

/**
 * Frontend functionality for Football Tips plugin.
 */
class Frontend_Features
{

    /**
     * Initialize hooks for frontend functionality.
     */
    public static function init()
    {

        // add_shortcode('football_tips_leaderboard', [__CLASS__, 'render_leaderboard']);
        add_shortcode('football_tips_user_tips', [__CLASS__, 'display_user_tips']);

        //add_action('bp_setup_nav', [__CLASS__, 'add_bb_navigation']);
        add_action('bp_setup_nav', [__CLASS__, 'add_bb_navigation']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);

        add_action('wp_ajax_fetch_leaderboard', [__CLASS__, 'fetch_leaderboard']);
        add_action('wp_ajax_nopriv_fetch_leaderboard', [__CLASS__, 'fetch_leaderboard']);
    }

    /**
     * Add custom tab to BuddyPress profile.
     */
    public static function add_bb_navigation()
    {
        // Add "Football Tips" tab to the profile navigation
        bp_core_new_nav_item([
            'name'            => __('Members Tips', 'football-tips'),
            'slug'            => 'members-tips',
            'position'        => 30,
            'parent_url'      => trailingslashit(bp_loggedin_user_domain()) . 'profile/',
            'parent_slug'     => 'profile',
            'screen_function' => [__CLASS__, 'football_tips_tab_content'],
            'default_subnav_slug' => 'football-tips',
        ]);
    }

    /**
     * The content for the "Football Tips" tab in BuddyPress profile.
     */
    public static function football_tips_tab_content()
    {
        error_log("visirreasfdfcadmdl");
        add_action('bp_template_content', [__CLASS__, 'display_user_tips']); // Display the tips table here // Assuming display_user_tips is your function to display the table
        bp_core_load_template('members/single/plugins');
    }

    /**
     * Enqueue styles for the frontend form.
     */
    public static function enqueue_styles()
    {
        wp_enqueue_style(
            'football-tips-frontend-features',
            plugins_url('../assets/css/frontend-features.css', __FILE__),
            [],
            '1.0.0',
            'all'
        );

        wp_enqueue_script(
            'football-tips-leaderboard',
            plugins_url('../assets/js/leaderboard.js', __FILE__),
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('football-tips-leaderboard', 'FootballTipsAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    public static function add_bp_navigation()
    {

        bp_core_new_subnav_item([
            'name'            => __('Leaderboard', 'football-tips'),
            'slug'            => 'leaderboard',
            'parent_url'      => trailingslashit(bp_loggedin_user_domain()),
            'parent_slug'     => bp_get_profile_slug(),
            'screen_function' => function () {
                add_action('bp_template_content', function () {
                    echo do_shortcode('[football_tips_leaderboard]');
                });
            },
            'position'        => 30,
        ]);

        error_log('Parent slug: ' . bp_get_profile_slug());
    }


    public static function render_leaderboard()
    {
        ob_start();
?>
        <div id="leaderboard-container" class="leaderboard-container">
            <ul class="leaderboard-tabs">
                <li data-tab="daily" class="active"><?php esc_html_e('Daily', 'football-tips'); ?></li>
                <li data-tab="weekly"><?php esc_html_e('Weekly', 'football-tips'); ?></li>
                <li data-tab="monthly"><?php esc_html_e('Monthly', 'football-tips'); ?></li>
                <li data-tab="overall"><?php esc_html_e('Overall', 'football-tips'); ?></li>
            </ul>
            <div id="leaderboard-content" class="leaderboard-content">
                <p><?php esc_html_e('Loading leaderboard...', 'football-tips'); ?></p>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    public static function fetch_leaderboard()
    {
        if (empty($_POST['timeframe'])) {
            wp_send_json_error(['message' => __('Invalid timeframe.', 'football-tips')]);
        }

        $timeframe = sanitize_text_field($_POST['timeframe']);
        $leaderboard_data = get_option('football_tips_leaderboard_cache', []);

        if (empty($leaderboard_data[$timeframe])) {
            wp_send_json_error(['message' => __('No data available for this timeframe.', 'football-tips')]);
        }

        // Render cached leaderboard data.
        ob_start();
        echo '<ol>';
        foreach ($leaderboard_data[$timeframe] as $user) {
            $user_name = bp_core_get_user_displayname($user['user_id']);
            $profit = number_format($user['profit'], 2);
            $tips_count = $user['tips_count'];
            echo "<li><strong>{$user_name}</strong>: $${profit} profit ({$tips_count} tips)</li>";
        }
        echo '</ol>';

        wp_send_json_success(['html' => ob_get_clean()]);
    }

    /**
     * Display the user's tips in a table format with pagination
     *
     * @param array $atts
     * @return string
     */
    public static function display_user_tips()
    {
        global $wpdb;

        // Pagination settings
        $per_page = 10;  // Number of tips per page
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;  // Current page, default to 1
        $offset = ($page - 1) * $per_page;  // Calculate the offset for the query

        // Query to get the user's tips along with the results (win/loss/push), profit, and result date
        $query = "
    SELECT t.id, t.event_name, t.stake, t.odds, t.created_at, r.result, r.profit_loss, r.processed_at
    FROM {$wpdb->prefix}football_tips t
    LEFT JOIN {$wpdb->prefix}football_tips_results r ON t.id = r.tip_id
    WHERE t.user_id = %d
    ORDER BY t.created_at DESC
    LIMIT %d OFFSET %d
    ";

        // Get the current logged-in user ID
        $user_id = get_current_user_id();

        // Prepare and execute the query to get the user's tips and results
        $user_tips = $wpdb->get_results($wpdb->prepare($query, $user_id, $per_page, $offset));

        // Query to get the total number of tips for pagination
        $total_tips_query = "
    SELECT COUNT(*)
    FROM {$wpdb->prefix}football_tips t
    WHERE t.user_id = %d
    ";
        $total_tips = $wpdb->get_var($wpdb->prepare($total_tips_query, $user_id));

        // Start building the output
        $output = '';

        // Display the table of tips if there are any
        if (!empty($user_tips)) {
            $output .= '<table class="football-tips-table">';
            $output .= '<thead>
            <tr>
                <th>' . __('Date', 'football-tips') . '</th>
                <th>' . __('Event', 'football-tips') . '</th>
                <th>' . __('Stake', 'football-tips') . '</th>
                <th>' . __('Odds', 'football-tips') . '</th>
                <th>' . __('Result', 'football-tips') . '</th>
                <th>' . __('Profit/Loss', 'football-tips') . '</th>
            </tr>
          </thead>';
            $output .= '<tbody>';

            foreach ($user_tips as $tip) {
                $result_class = strtolower($tip->result); // Apply class based on result (win/loss/push)
                $profit_loss = isset($tip->profit_loss) ? '$' . number_format($tip->profit_loss, 2) : '-'; // Show profit if exists, otherwise '-'

                // Format the date for when the tip was placed (created_at)
                $date_placed = date('d/m/y', strtotime($tip->created_at));

                // If processed_at exists (when the tip was resulted), use that as the result date
                $result_date = $tip->processed_at ? date('d/m/y', strtotime($tip->processed_at)) : $date_placed;

                // Output each row in the table
                $output .= "<tr class='tip-result-$result_class'>
                <td>{$result_date}</td>
                <td>{$tip->event_name}</td>
                <td>\${$tip->stake}</td>
                <td>{$tip->odds}</td>
                <td class='tip-result'>" . ucfirst($tip->result) . "</td>
                <td class='tip-profit'>$profit_loss</td>
              </tr>";
            }

            $output .= '</tbody></table>';

            // Display pagination links
            if ($total_tips > $per_page) {
                $output .= paginate_links([
                    'total'        => ceil($total_tips / $per_page),
                    'current'      => $page,
                    'format'       => '?page=%#%',
                    'prev_text'    => __('Previous', 'football-tips'),
                    'next_text'    => __('Next', 'football-tips'),
                    'add_args'     => false // Ensure no additional args are added
                ]);
            }
        } else {
            // Message if no tips are found
            $output .= '<p>' . __('No tips found.', 'football-tips') . '</p>';
        }

        // Return the generated output to be displayed in the shortcode
        echo $output;  // Make sure to return the output
    }
}

//Frontend_Features::init();
Frontend_Features::init();
add_action('bp_init', function () {});
