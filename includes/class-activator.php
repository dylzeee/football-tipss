<?php

/**
 * Handles plugin activation tasks.
 */
class Football_Tips_Activator
{

    /**
     * Activate the plugin: Create database tables and initialize options.
     */
    public static function activate()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create football tips table.
        $table_tips = $wpdb->prefix . 'football_tips';
        $sql_tips = "CREATE TABLE $table_tips (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            sport_key VARCHAR(255) NOT NULL,
            sport_title VARCHAR(255) NOT NULL,
            event_id VARCHAR(255) NOT NULL,
            commence_time DATETIME NOT NULL,
            event_name VARCHAR(255) NOT NULL,
            market_key VARCHAR(255) NOT NULL,
            market_name VARCHAR(255) NOT NULL,
            selection VARCHAR(255) NOT NULL,
            odds DECIMAL(10, 2) NOT NULL,
            stake INT UNSIGNED NOT NULL,
            resulted TINYINT(1) NOT NULL DEFAULT 0, -- 0 = Not Resulted, 1 = Resulted
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Create performance tracking table.
        $table_performance = $wpdb->prefix . 'football_performance';
        $sql_performance = "CREATE TABLE $table_performance (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            total_tips INT(11) NOT NULL DEFAULT 0,
            total_profit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_loss DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            roi DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Results table.
        $results_table = $wpdb->prefix . 'football_tips_results';
        $results_sql = "CREATE TABLE $results_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        tip_id BIGINT UNSIGNED NOT NULL,
        result ENUM('win', 'loss', 'push') NOT NULL,
        profit_loss DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        processed_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (tip_id) REFERENCES $table_tips(id) ON DELETE CASCADE
    ) $charset_collate;";

        $earnings_table = $wpdb->prefix . 'tipster_earnings';
        $earnings_sql = "CREATE TABLE $earnings_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tipster_id BIGINT UNSIGNED NOT NULL,
        subscription_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        commission DECIMAL(10, 2) NOT NULL,
        net_earnings DECIMAL(10, 2) NOT NULL,
        type ENUM('earning', 'payout') DEFAULT 'earning',
        status ENUM('pending', 'paid') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

        $payouts_table = $wpdb->prefix . 'tipster_payout_requests';
        $payouts_sql = "CREATE TABLE $payouts_table (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipster_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolution_date DATETIME NULL,
    resolved_by BIGINT UNSIGNED NULL,
    notes TEXT NULL
) $charset_collate;";

        // Run queries.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_tips);
        dbDelta($sql_performance);
        dbDelta($results_sql);
        dbDelta($earnings_sql);
        dbDelta($payouts_sql);

        // Set up plugin cron jobs
        if (!wp_next_scheduled('football_tips_update_leaderboard')) {
            wp_schedule_event(time(), 'thirty_minutes', 'football_tips_update_leaderboard');
        }
    }
}
