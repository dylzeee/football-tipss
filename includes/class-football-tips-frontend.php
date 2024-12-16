<?php

/**
 * Frontend functionality for Football Tips plugin.
 */
class Football_Tips_Frontend
{

    /**
     * Initialize hooks for frontend functionality.
     */
    public static function init()
    {
        // Enqueue scripts and styles.
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // Register AJAX actions for fetching events and odds.
        add_action('wp_ajax_fetch_events', [__CLASS__, 'fetch_events']);
        add_action('wp_ajax_nopriv_fetch_events', [__CLASS__, 'fetch_events']);

        add_action('wp_ajax_fetch_odds', [__CLASS__, 'fetch_odds']);         // For logged-in users.
        add_action('wp_ajax_nopriv_fetch_odds', [__CLASS__, 'fetch_odds']); // For non-logged-in users.

        add_action('wp_ajax_submit_tip', [__CLASS__, 'submit_tip']);         // For logged-in users.
        add_action('wp_ajax_nopriv_submit_tip', [__CLASS__, 'submit_tip']); // For non-logged-in users.

        // Register the shortcode.
        add_shortcode('football_tips_form', [__CLASS__, 'render_tips_form']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);

        add_action('wp_ajax_get_user_points', [__CLASS__, 'get_user_points']);
    }

    /**
     * Enqueue styles for the frontend form.
     */
    public static function enqueue_styles()
    {
        wp_enqueue_style(
            'football-tips-frontend',
            plugins_url('../assets/css/frontend.css', __FILE__),
            [],
            '1.0.0',
            'all'
        );
    }


    /**
     * Enqueue necessary assets.
     */
    public static function enqueue_assets()
    {
        wp_enqueue_script(
            'football-tips-frontend',
            plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('football-tips-frontend', 'FootballTipsAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    /**
     * Render the football tips form.
     */
    public static function render_tips_form()
    {
        // Fetch enabled sports.
        $enabled_sports = get_option('football_tips_enabled_sports', []);
        if (empty($enabled_sports)) {
            return '<p class="no-leagues-message">' . esc_html__('No leagues available at the moment.', 'football-tips') . '</p>';
        }

        ob_start();
?>
        <div id="football-tips-form-wrapper" class="football-tips-form-wrapper">
            <form id="football-tips-form" class="football-tips-form">
                <div class="form-group">
                    <label for="sport-select" class="form-label"><?php esc_html_e('Select League:', 'football-tips'); ?></label>
                    <select id="sport-select" name="sport" class="form-select">
                        <option value=""><?php esc_html_e('Choose a league', 'football-tips'); ?></option>
                        <?php foreach ($enabled_sports as $sport_key) : ?>
                            <option value="<?php echo esc_attr($sport_key); ?>">
                                <?php echo esc_html(self::format_league_name($sport_key)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="events-wrapper" class="dynamic-field-wrapper">
                    <!-- Event dropdown will be dynamically populated here. -->
                </div>

                <div id="markets-wrapper" class="dynamic-field-wrapper">
                    <!-- Market dropdown will be dynamically populated here. -->
                </div>

                <div id="odds-wrapper" class="dynamic-field-wrapper">
                    <!-- Odds will be dynamically populated here. -->
                </div>

                <div id="stake-wrapper" class="dynamic-field-wrapper" style="display: none;">
                    <label for="stake-input" class="form-label"><?php esc_html_e('Enter Stake (in units):', 'football-tips'); ?></label>
                    <input id="stake-input" type="number" class="form-input" min="1" step="1" placeholder="<?php esc_attr_e('Enter your stake', 'football-tips'); ?>" />
                </div>

                <div id="submit-wrapper" class="submit-field-wrapper" style="margin-top: 20px;">
                    <!-- Submit button will be dynamically added here. -->
                </div>

                <div id="returns-wrapper" class="returns-wrapper" style="display: none; margin-top: 20px;">
                    <!-- Returns/losses will be displayed here. -->
                </div>
            </form>
        </div>
<?php
        return ob_get_clean();
    }




    private static function format_league_name($raw_key)
    {
        // Define known mappings for specific leagues.
        $league_mapping = [
            'soccer_efl_champ' => 'EFL Championship',
            'soccer_efl_cup' => 'EFL Cup',
            'soccer_england_league1' => 'England League 1',
            'soccer_england_league2' => 'England League 2',
            'soccer_epl' => 'English Premier League (EPL)',
            'soccer_fa_cup' => 'FA Cup',
            'soccer_france_ligue_one' => 'Ligue 1 (France)',
            'soccer_germany_bundesliga' => 'Bundesliga (Germany)',
            'soccer_italy_serie_a' => 'Serie A (Italy)',
            'soccer_uefa_champs_league' => 'UEFA Champions League',
            'soccer_uefa_europa_league' => 'UEFA Europa League',
            'soccer_spl' => 'Scottish Premier League',
        ];

        // If a mapping exists, use it.
        if (array_key_exists(strtolower($raw_key), $league_mapping)) {
            return $league_mapping[strtolower($raw_key)];
        }

        // Fallback: Remove "soccer_" prefix and format dynamically.
        $formatted_name = str_replace('soccer_', '', strtolower($raw_key));
        $formatted_name = str_replace('_', ' ', $formatted_name); // Replace underscores with spaces.
        $formatted_name = ucwords($formatted_name); // Capitalize each word.

        return $formatted_name;
    }




    /**
     * Handle AJAX request to fetch events for a selected sport.
     */
    public static function fetch_events()
    {
        if (empty($_POST['sport'])) {
            wp_send_json_error(['message' => __('No sport selected.', 'football-tips')]);
        }

        $sport = sanitize_text_field($_POST['sport']);

        // Remove live and commenced events from the request
        $commenceTimeFrom = gmdate('Y-m-d\TH:i:s\Z');

        // Fetch events from The Odds API.
        $api_key = get_option('football_tips_api_key', '');

        $api_url = "https://api.the-odds-api.com/v4/sports/$sport/events/?apiKey=$api_key&commenceTimeFrom=$commenceTimeFrom";

        $response = wp_remote_get($api_url);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log(print_r($response, true));
            //error_log($response);
            wp_send_json_error(['message' => __('Failed to fetch events.', 'football-tips')]);
        }

        $events = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($events)) {
            wp_send_json_error(['message' => __('No events available for this sport.', 'football-tips')]);
        }

        error_log("INCOMING EVENTS BROO!");
        error_log("INCOMING EVENTS BROO!");
        error_log(print_r($events, true));

        wp_send_json_success(['events' => $events]);
    }

    public static function fetch_event_details()
    {
        // Validate input.
        if (empty($_POST['event_id']) || empty($_POST['sport_key'])) {
            wp_send_json_error(['message' => __('Invalid event or sport.', 'football-tips')]);
        }

        $event_id = sanitize_text_field($_POST['event_id']);
        $sport_key = sanitize_text_field($_POST['sport_key']);

        // Fetch event details from The Odds API.
        $api_key = get_option('football_tips_api_key', '');
        $api_url = "https://api.the-odds-api.com/v4/sports/{$sport_key}/events/{$event_id}?apiKey={$api_key}";

        $response = wp_remote_get($api_url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            wp_send_json_error(['message' => __('Failed to fetch event details.', 'football-tips')]);
        }

        $event_details = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($event_details) || empty($event_details['markets'])) {
            wp_send_json_error(['message' => __('No markets available for this event.', 'football-tips')]);
        }

        // Prepare markets for dropdown.
        $markets = array_map(function ($market) {
            return [
                'key'  => $market['key'],         // Unique market key.
                'name' => $market['name'],        // Market name.
            ];
        }, $event_details['markets']);

        wp_send_json_success(['markets' => $markets]);
    }


    public static function fetch_odds()
    {
        if (empty($_POST['event_id']) || empty($_POST['sport_key']) || empty($_POST['market_key'])) {
            wp_send_json_error(['message' => __('Invalid event, sport, or market.', 'football-tips')]);
        }

        $regions = 'us';
        $event_id = sanitize_text_field($_POST['event_id']);
        $sport_key = sanitize_text_field($_POST['sport_key']);
        $market_key = sanitize_text_field($_POST['market_key']);

        if ($sport_key === 'soccer_australia_aleague') {
            $regions = 'au,us';
        }

        $api_key = get_option('football_tips_api_key', '');
        $api_url = "https://api.the-odds-api.com/v4/sports/{$sport_key}/events/{$event_id}/odds?apiKey={$api_key}&regions={$regions}&markets={$market_key}";

        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('Failed to fetch odds.', 'football-tips')]);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            wp_send_json_error(['message' => __('Failed to fetch odds.', 'football-tips')]);
        }

        $odds_data = json_decode($response_body, true);

        if (empty($odds_data['bookmakers'])) {
            wp_send_json_error(['message' => __('No odds available for this market.', 'football-tips')]);
        }

        // Extract event details.
        $event_details = [
            'sport_title'   => $odds_data['sport_title'],
            'commence_time' => $odds_data['commence_time'],
            'home_team'     => $odds_data['home_team'],
            'away_team'     => $odds_data['away_team'],
        ];

        // Use only the first bookmaker's odds.
        $first_bookmaker = $odds_data['bookmakers'][0] ?? null;
        if (empty($first_bookmaker['markets'])) {
            wp_send_json_error(['message' => __('No markets available for the first bookmaker.', 'football-tips')]);
        }

        $odds = [];
        foreach ($first_bookmaker['markets'] as $market) {
            if ($market['key'] === $market_key) {
                foreach ($market['outcomes'] as $outcome) {
                    // Format outcome for team totals.
                    if ($market_key === 'team_totals' || $market_key === 'player_goal_scorer_anytime') {
                        $odds[] = [
                            'name'        => $outcome['description']['name'] ?? $outcome['name'], // Use description.name if available.
                            'price'       => $outcome['price'],
                            'point'       => $outcome['point'] ?? null,
                            'description' => $outcome['description'] ?? null, // Include full description for flexibility.
                        ];
                    } else {
                        // Default format for other markets.
                        $odds[] = [
                            'name'  => $outcome['name'],
                            'price' => $outcome['price'],
                            'point' => $outcome['point'] ?? null,
                        ];
                    }
                }
            }
        }

        if (empty($odds)) {
            wp_send_json_error(['message' => __('No odds available for the selected market.', 'football-tips')]);
        }

        wp_send_json_success([
            'event_details' => $event_details,
            'market_name'   => $market_key,
            'odds'          => $odds,
        ]);
    }



    public static function submit_tip()
    {
        // Check for required fields.
        $required_fields = ['sport_key', 'event_id', 'market_key', 'odds', 'stake'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(['message' => __('Missing required fields.', 'football-tips')]);
            }
        }

        if (!function_exists('gamipress_get_user_points')) {
            wp_die(__('GamiPress is required for this functionality. Please install and activate GamiPress.', 'football-tips'));
        }

        // Sanitize and assign fields.
        $user_id = get_current_user_id();
        $stake   = intval($_POST['stake']);

        if (!$user_id) {
            wp_send_json_error(['message' => __('You must be logged in to submit a tip.', 'football-tips')]);
        }

        // Check user's GamiPress points balance.
        $user_points = gamipress_get_user_points($user_id, 'betting-coins'); // Replace 'points' with your points type slug.

        if ($user_points < $stake) {
            wp_send_json_error(['message' => __('You do not have enough points to place this stake.', 'football-tips')]);
        }

        // Deduct points from user balance.
        // Deduct points from user balance.
        // Deduct points from user balance.
        gamipress_deduct_points_to_user($user_id, $stake, 'betting-coins', [
            'log' => true,
            'description' => __('Stake placed on a tip.', 'football-tips'),
        ]);

        // Sanitize input fields.
        $sport_key   = sanitize_text_field($_POST['sport_key']);
        $event_id    = sanitize_text_field($_POST['event_id']);
        $market_key  = sanitize_text_field($_POST['market_key']);
        $odds        = floatval($_POST['odds']);
        $stake       = intval($_POST['stake']);

        // Optional fields (fetch from POST or fallback).
        $sport_title  = sanitize_text_field($_POST['sport_title'] ?? '');
        $commence_time = sanitize_text_field($_POST['commence_time'] ?? '');
        $event_name    = sanitize_text_field($_POST['event_name'] ?? '');
        $away_team    = sanitize_text_field($_POST['away_team'] ?? '');
        $market_name  = sanitize_text_field($_POST['market_name'] ?? ''); // Displayed market name.
        $selection    = sanitize_text_field($_POST['selection'] ?? ''); // Displayed selection.

        error_log("Sport Title: $sport_title");


        // Get the current user ID.
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('You must be logged in to submit a tip.', 'football-tips')]);
        }

        // Prepare the data for insertion.
        global $wpdb;
        $table_name = $wpdb->prefix . 'football_tips'; // Update this if your table name differs.
        $data = [
            'user_id'       => $user_id,
            'sport_key'     => $sport_key,
            'sport_title'   => $sport_title,
            'event_id'      => $event_id,
            'commence_time' => $commence_time,
            'event_name'     => $event_name,
            'market_key'    => $market_key,
            'market_name'   => $market_name,
            'selection'     => $selection,
            'odds'          => $odds,
            'stake'         => $stake,
            'created_at'    => current_time('mysql', 1), // Store UTC time for consistency.
        ];

        // Insert data into the database.
        $inserted = $wpdb->insert($table_name, $data);

        if ($inserted === false) {
            wp_send_json_error(['message' => __('Failed to submit tip. Please try again.', 'football-tips')]);
        }

        // Add activity to BuddyBoss feed after successful submission.
        if ($inserted) {
            $user_id = get_current_user_id();
            $tips_visibility = get_user_meta($user_id, 'tips_visibility', true);

            if ($tips_visibility == 'public') {
                $activity_content = sprintf(
                    __('%s placed a tip: %s (%s) with a stake of %d coins.', 'football-tips'),
                    bp_core_get_userlink($user_id), // Get the user's BuddyBoss profile link.
                    sanitize_text_field($_POST['event_name']),
                    sanitize_text_field($_POST['selection']),
                    intval($_POST['stake'])
                );
                $action_content = sprintf(
                    __('%s placed a tip.', 'football-tips'),
                    bp_core_get_userlink($user_id)
                );

                bp_activity_add([
                    'user_id'   => $user_id,
                    'action'    => $action_content,
                    'content'   => $activity_content,
                    'component' => 'football_tips', // Create a custom component name for your plugin.
                    'type'      => 'submitted_tip',
                ]);
            }
        }

        // Return success response.
        wp_send_json_success(['message' => __('Tip submitted successfully!', 'football-tips')]);
    }

    public static function get_user_points()
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('You must be logged in to view your points.', 'football-tips')]);
        }

        $points = gamipress_get_user_points($user_id, 'betting-coins'); // Replace 'points' with your points type slug.
        wp_send_json_success(['points' => $points]);
    }
}

Football_Tips_Frontend::init();
