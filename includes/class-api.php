<?php
namespace SportsNewsFetcher;

class API {
    private $api_key;
    private $api_url_base;

    public function __construct() {
        $this->api_key = get_option('sports_news_fetcher_api_key', '');
        $this->api_url_base = $_SERVER['HTTP_HOST'] === 'fabrizio-news.test'
            ? 'http://ai-sports-news.test/api/v1/posts'
            : 'https://ai-articles-db8717205dd6.herokuapp.com/api/v1/posts';
    }

    public function init() {
        add_action('sports_news_fetcher_cron_event', [$this, 'fetch_data']);
        add_action('admin_init', [$this, 'handle_manual_fetch']);
    }

    public function fetch_data($start_date = '', $end_date = '') {
        if (empty($this->api_key)) {
            return;
        }

        $current_page = 1;
        $last_page = 1;

        if (empty($start_date)) {
            $start_date = date('Y-m-d');
        }

        if (empty($end_date)) {
            $end_date = date('Y-m-d');
        }

        do {
            $query_params = [
                'page' => $current_page,
                'start_date' => $start_date,
                'end_date' => $end_date
            ];

            $api_url = add_query_arg($query_params, $this->api_url_base);
            $response = wp_remote_get($api_url, [
                'headers' => [
                    'Authorization' => 'Basic ' . $this->api_key,
                ],
            ]);

            if (is_wp_error($response)) {
                return;
            }

            $body = wp_remote_retrieve_body($response);
            $json_data = json_decode($body, true);
            $data = $json_data['data'] ?? [];

            if ($current_page === 1) {
                $last_page = $json_data['meta']['last_page'] ?? 1;
            }

            if (!empty($data)) {
                $this->process_data($data);
            }

            $current_page++;
        } while ($current_page <= $last_page);
    }

    private function process_data($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sports_news';

        foreach ($data as $item) {
            $existing_ai_post = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE title = %s",
                sanitize_text_field($item['title'])
            ));

            if (!$existing_ai_post) {
                // Simplify categories data to only include necessary fields
                $simplified_categories = [];
                if (!empty($item['wp_categories_internal_links'])) {
                    foreach ($item['wp_categories_internal_links'] as $category) {
                        $simplified_categories[] = [
                            'category_url' => $category['category_url'] ?? null,
                            'tag_url' => $category['tag_url'] ?? null,
                            'name' => $category['name'] ?? null,
                            'slug' => $category['slug'] ?? null
                        ];
                    }
                }

                // Simplify tags data to only include necessary fields
                $simplified_tags = [];
                if (!empty($item['wp_tags_internal_links'])) {
                    foreach ($item['wp_tags_internal_links'] as $tag) {
                        $simplified_tags[] = [
                            'category_url' => $tag['category_url'] ?? null,
                            'tag_url' => $tag['tag_url'] ?? null,
                            'name' => $tag['name'] ?? null,
                            'slug' => $tag['slug'] ?? null
                        ];
                    }
                }

                $wpdb->insert(
                    $table_name,
                    [
                        'title' => sanitize_text_field($item['title']),
                        'content' => $item['content'],
                        'meta_title' => sanitize_text_field($item['meta_title']),
                        'meta_description' => sanitize_text_field($item['meta_description']),
                        'media_url' => !empty($item['source_media_url']) ? esc_url_raw($item['source_media_url']) : null,
                        'categories_data' => !empty($simplified_categories) ? json_encode($simplified_categories) : null,
                        'tags_data' => !empty($simplified_tags) ? json_encode($simplified_tags) : null,
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );
            }
        }
    }

    public function handle_manual_fetch() {
        if (isset($_POST['sports_news_fetcher_manual_fetch']) && current_user_can('manage_options')) {
            $start_date = isset($_POST['sports_news_start_date']) ? sanitize_text_field($_POST['sports_news_start_date']) : '';
            $end_date = isset($_POST['sports_news_end_date']) ? sanitize_text_field($_POST['sports_news_end_date']) : '';

            $this->fetch_data($start_date, $end_date);

            add_action('admin_notices', function () {
                echo '<div class="notice notice-success"><p>Data fetched successfully!</p></div>';
            });
        }
    }
}
