<?php

/**
 * Block functionality for Football Tips plugin.
 */
class Football_Tips_Blocks
{

    /**
     * Initialize hooks for frontend functionality.
     */
    public static function init()
    {
        /**
         * Register REST API route for user statistics.
         */
        add_action('rest_api_init', [__CLASS__, 'init_api']);

        add_action('init', [__CLASS__, 'football_tips_register_blocks']);
    }

    public static function init_api()
    {
        error_log("API Init");
        add_action('rest_api_init', function () {
            error_log("REST API Route Registered");
        });

        register_rest_route('football-tits/v1', '/statistics', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'football_tips_get_statistics'],
            'permission_callback' => '__return_true',
        ]);
        // error_log(print_r(rest_get_server()->get_routes(), true));
    }
    /**
     * Blocks initiation
     */
    public static function football_tips_register_blocks()
    {
        error_log('Registering block: football-tips/statistics-block');

        register_block_type_from_metadata(__DIR__ . '/../build');
    }


    /**
     * Fetch user statistics based on filters.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function football_tips_get_statistics(WP_REST_Request $request)
    {
        error_log(("GOT SDk"));
        global $wpdb;

        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        $date_filter = sanitize_text_field($params['dateFilter'] ?? 'all');
        $sport_filter = sanitize_text_field($params['sportFilter'] ?? 'all');
        $market_filter = sanitize_text_field($params['marketFilter'] ?? 'all');

        // Build query filters.
        $where = ["t.user_id = %d"];
        $query_params = [$user_id];

        if ($date_filter !== 'all') {
            $date_range = self::get_date_range($date_filter);
            $where[] = "t.created_at BETWEEN %s AND %s";
            $query_params[] = $date_range['start'];
            $query_params[] = $date_range['end'];
        }

        if ($sport_filter !== 'all') {
            $where[] = "t.sport_key = %s";
            $query_params[] = $sport_filter;
        }

        if ($market_filter !== 'all') {
            $where[] = "t.market_key = %s";
            $query_params[] = $market_filter;
        }

        $where_clause = implode(' AND ', $where);

        // Fetch win/loss/push distribution.
        $results_query = "
        SELECT 
            SUM(CASE WHEN r.result = 'win' THEN 1 ELSE 0 END) AS wins,
            SUM(CASE WHEN r.result = 'loss' THEN 1 ELSE 0 END) AS losses,
            SUM(CASE WHEN r.result = 'push' THEN 1 ELSE 0 END) AS pushes
        FROM {$wpdb->prefix}football_tips t
        LEFT JOIN {$wpdb->prefix}football_tips_results r ON t.id = r.tip_id
        WHERE {$where_clause}
    ";
        $results_data = $wpdb->get_row($wpdb->prepare($results_query, $query_params), ARRAY_A);

        // Fetch profit over time (grouped by week or month based on the filter).
        $time_group = $date_filter === 'year' ? 'MONTH(t.created_at)' : 'WEEK(t.created_at)';
        $time_group = $date_filter === 'year' ? 'MONTH(t.created_at)' : 'WEEK(t.created_at)';
        $profit_query = "
        SELECT 
            {$time_group} AS time_group,
            SUM(r.profit_loss) AS profit
        FROM {$wpdb->prefix}football_tips t
        LEFT JOIN {$wpdb->prefix}football_tips_results r ON t.id = r.tip_id
        WHERE {$where_clause}
        GROUP BY time_group
        ORDER BY time_group ASC
        ";

        $profit_data = $wpdb->get_results($wpdb->prepare($profit_query, $query_params), ARRAY_A);

        // Format profit over time.
        $profit_over_time = [
            'dates' => array_column($profit_data, 'time_group'),
            'values' => array_map(function ($row) {
                return (float) $row['profit'];
            }, $profit_data),
        ];
        error_log(print_r($profit_over_time, true));

        // Fetch market breakdown.
        $market_query = "
        SELECT 
            t.market_key AS market,
            COUNT(*) AS total
        FROM {$wpdb->prefix}football_tips t
        LEFT JOIN {$wpdb->prefix}football_tips_results r ON t.id = r.tip_id
        WHERE {$where_clause}
        GROUP BY t.market_key
    ";
        $market_data = $wpdb->get_results($wpdb->prepare($market_query, $query_params), ARRAY_A);

        // Format market breakdown.
        $market_breakdown = [
            'labels' => array_column($market_data, 'market'),
            'values' => array_column($market_data, 'total'),
        ];

        // Return response.
        return new WP_REST_Response([
            'wins' => (int) $results_data['wins'],
            'losses' => (int) $results_data['losses'],
            'pushes' => (int) $results_data['pushes'],
            'profit_over_time' => $profit_over_time,
            'market_breakdown' => $market_breakdown,
        ], 200);
    }


    /**
     * Get the date range for filtering.
     *
     * @param string $date_filter
     * @return array
     */
    public static function get_date_range($date_filter)
    {
        switch ($date_filter) {
            case 'week':
                return [
                    'start' => date('Y-m-d 00:00:00', strtotime('monday this week')),
                    'end' => date('Y-m-d 23:59:59', strtotime('sunday this week')),
                ];
            case 'month':
                return [
                    'start' => date('Y-m-01 00:00:00'),
                    'end' => date('Y-m-t 23:59:59'),
                ];
            case 'year':
                return [
                    'start' => date('Y-01-01 00:00:00'),
                    'end' => date('Y-12-31 23:59:59'),
                ];
            default:
                return ['start' => '1970-01-01 00:00:00', 'end' => current_time('mysql')];
        }
    }
}

Football_Tips_Blocks::init();
