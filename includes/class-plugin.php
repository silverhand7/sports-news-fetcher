<?php
namespace SportsNewsFetcher;

class Plugin {
    private $admin;
    private $api;
    private $database;

    public function __construct() {
        $this->load_dependencies();
    }

    public function init() {
        // Register activation and deactivation hooks
        register_activation_hook(SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'sports-news-fetcher.php', [$this, 'activate']);
        register_deactivation_hook(SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'sports-news-fetcher.php', [$this, 'deactivate']);

        // Initialize components
        $this->admin->init();
        $this->api->init();
        $this->database->init();
    }

    private function load_dependencies() {
        $this->admin = new Admin();
        $this->api = new API();
        $this->database = new Database();
    }

    public function activate() {
        $this->database->create_tables();
        $this->schedule_cron_event();
    }

    public function deactivate() {
        $this->clear_cron_event();
    }

    private function schedule_cron_event() {
        if (!wp_next_scheduled('sports_news_fetcher_cron_event')) {
            wp_schedule_event(time(), 'daily', 'sports_news_fetcher_cron_event');
        }
    }

    private function clear_cron_event() {
        wp_clear_scheduled_hook('sports_news_fetcher_cron_event');
    }
}
