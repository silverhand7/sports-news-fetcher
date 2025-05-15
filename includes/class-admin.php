<?php
namespace SportsNewsFetcher;

class Admin {
    public function init() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function add_menu_page() {
        add_menu_page(
            'Sports News',
            'Sports News',
            'manage_options',
            'sports-news-fetcher',
            [$this, 'render_admin_page'],
            'dashicons-rss',
            30
        );
    }

    public function register_settings() {
        register_setting('sports_news_fetcher_settings_group', 'sports_news_fetcher_api_key');
        add_settings_section('sports_news_fetcher_section', 'API Settings', null, 'sports_news_fetcher_settings_page');
        add_settings_field('sports_news_fetcher_api_key', 'API Key', [$this, 'render_api_key_field'], 'sports_news_fetcher_settings_page', 'sports_news_fetcher_section');
    }

    public function enqueue_scripts($hook) {
        if ('toplevel_page_sports-news-fetcher' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'sports-news-fetcher-admin',
            SPORTS_NEWS_FETCHER_PLUGIN_URL . 'admin/css/admin.css',
            [],
            SPORTS_NEWS_FETCHER_VERSION
        );

        wp_enqueue_script(
            'sports-news-fetcher-admin',
            SPORTS_NEWS_FETCHER_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            SPORTS_NEWS_FETCHER_VERSION,
            true
        );

        wp_localize_script('sports-news-fetcher-admin', 'sportsNewsFetcher', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sports_news_fetcher_nonce')
        ]);
    }

    public function render_api_key_field() {
        $api_key = get_option('sports_news_fetcher_api_key', '');
        echo '<input type="text" name="sports_news_fetcher_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
    }

    public function render_admin_page() {
        require_once SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'admin/views/admin-page.php';
    }
}
