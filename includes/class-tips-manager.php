<?php

/**
 * Handles frontend functionalities for football tips.
 */
class Football_Tips_Manager
{

    /**
     * Initialize plugin functionalities.
     */
    public static function init()
    {


        add_action('bp_register_activity_actions', function () {
            bp_activity_set_action(
                'football_tips',
                'submitted_tip',
                __('Submitted a Tip', 'football-tips')
            );

            bp_activity_set_action(
                'football_tips',
                'tip_settled',
                __('Tip Settled', 'football-tips')
            );
        });

        // Add a custom schedule for every 30 minutes.
        add_filter('cron_schedules', function ($schedules) {
            $schedules['thirty_minutes'] = [
                'interval' => 1800, // 30 minutes.
                'display'  => __('Every 30 Minutes', 'football-tips'),
            ];
            return $schedules;
        });

        add_action('football_tips_update_leaderboard', function () {
            global $wpdb;
            $table_name = $wpdb->prefix . 'football_tips';

            // Timeframes and their respective queries.
            $timeframes = [
                'daily' => "WHERE resulted = 1 AND DATE(commence_time) = CURDATE()",
                'weekly' => "WHERE resulted = 1 AND commence_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                'monthly' => "WHERE resulted = 1 AND commence_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                'overall' => "WHERE resulted = 1",
            ];

            $leaderboard_data = [];

            foreach ($timeframes as $key => $where_clause) {
                $query = "
                    SELECT user_id, SUM(stake * (odds - 1)) AS profit, COUNT(*) AS tips_count
                    FROM $table_name
                    $where_clause
                    GROUP BY user_id
                    ORDER BY profit DESC
                    LIMIT 10
                ";
                $leaderboard_data[$key] = $wpdb->get_results($query, ARRAY_A);
            }

            // Store the results in an option or a custom table.
            update_option('football_tips_leaderboard_cache', $leaderboard_data, false);
        });
    }
}
