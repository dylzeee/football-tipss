<?php

/**
 * Plugin Name: Football Tips Manager
 * Plugin URI:  https://yourwebsite.com
 * Description: A plugin for users to submit, track, and compete with football tips.
 * Version:     1.0.0
 * Author:      Dylan
 * License:     GPL-2.0+
 * Text Domain: football-tips
 */

defined('ABSPATH') || exit;

// Include required files.
require_once plugin_dir_path(__FILE__) . 'includes/class-activator.php';
//require_once plugin_dir_path( __FILE__ ) . 'includes/class-deactivator.php';
//require_once plugin_dir_path(__FILE__) . 'admin/admin-menu.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-tips-manager.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-football-tips-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-frontend.php';
//require_once plugin_dir_path(__FILE__) . 'includes/class-frontend-features.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-profile.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-profile-results.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-access.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-visibility.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-subscriptions.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-profile-subscription.php';
//require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-transactions.php';
//require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-earnings.php';
//require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-payouts.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-my-account.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-withdraw.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-football-tips-admin-payouts.php';



// Activation and deactivation hooks.
register_activation_hook(__FILE__, ['Football_Tips_Activator', 'activate']);
// Clear the cron job on plugin deactivation.
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('football_tips_update_leaderboard');
});


// Initialize the plugin.
add_action('plugins_loaded', ['Football_Tips_Manager', 'init']);

//add_action('bp_init', ['Frontend_Features', 'init']);

/**
 * Blocks initiation
 */
// function football_tips_register_blocks()
// {
//     register_block_type_from_metadata(__DIR__ . '/build');
// }
// add_action('init', 'football_tips_register_blocks');
