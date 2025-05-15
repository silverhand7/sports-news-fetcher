<?php
/**
 * Plugin Name: Sports News Fetcher
 * Description: Fetch and import sports news from an AI Sports News API into WordPress.
 * Version: 1.7.0
 * Author: Refo J.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SPORTS_NEWS_FETCHER_VERSION', '1.7.0');
define('SPORTS_NEWS_FETCHER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPORTS_NEWS_FETCHER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'SportsNewsFetcher\\';
    $base_dir = SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . str_replace('\\', '/', strtolower($relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function sports_news_fetcher_init() {
    $plugin = new SportsNewsFetcher\Plugin();
    $plugin->init();
}
add_action('plugins_loaded', 'sports_news_fetcher_init');
