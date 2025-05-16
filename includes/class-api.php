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
                $this->process_data($data, $start_date, $end_date, $current_page);
            }

            $current_page++;
        } while ($current_page <= $last_page);
    }

    private function process_data($data, $start_date, $end_date, $current_page) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sports_news';

        // Create a log file to track the data
        $log_file = SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'logs/api_data_' . $start_date . '_' . $end_date . '_' . $current_page . '.log';

        // Make sure the logs directory exists
        if (!file_exists(SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'logs')) {
            mkdir(SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'logs', 0755, true);
        }

        // Clear the log file before writing new content
        file_put_contents($log_file, "");

        // Log the entire data array for debugging
        file_put_contents($log_file, "=== FULL DATA ARRAY ===\n" . print_r($data, true) . "\n\n", FILE_APPEND);

        // Create a summary section
        file_put_contents($log_file, "=== PROCESSING SUMMARY ===\n", FILE_APPEND);
        file_put_contents($log_file, "Total items in API response: " . count($data) . "\n\n", FILE_APPEND);

        $processed_items = [];
        $skipped_items = [];
        $failed_items = [];

        foreach ($data as $item) {
            $title = sanitize_text_field($item['title']);

            // Log each individual item
            file_put_contents($log_file, "=== PROCESSING ITEM ===\n" . print_r($item, true) . "\n\n", FILE_APPEND);

            $existing_ai_post = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE title = %s",
                $title
            ));

            if (!$existing_ai_post) {
                // Log that we're inserting a new item
                file_put_contents($log_file, "Inserting new item with title: " . $title . "\n\n", FILE_APPEND);

                // Simplify categories data to only include necessary fields
                $simplified_categories = [];
                if (!empty($item['wp_categories_internal_links'])) {
                    foreach ($item['wp_categories_internal_links'] as $category) {
                        $simplified_categories[] = [
                            'category_url' => $category['category_url'] ?? null,
                            'tag_url' => $category['tag_url'] ?? null,
                            'name' => $category['name'] ?? null,
                            'slug' => $category['slug'] ?? null,
                            'description' => $category['description'] ?? null
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
                            'slug' => $tag['slug'] ?? null,
                            'description' => $tag['description'] ?? null
                        ];
                    }
                }

                // Prepare the data for insertion
                $insert_data = [
                    'title' => $title,
                    'content' => $item['content'],
                    'meta_title' => sanitize_text_field($item['meta_title']),
                    'meta_description' => substr(sanitize_text_field($item['meta_description']), 0, 255),
                    'media_url' => !empty($item['source_media_url']) ? esc_url_raw($item['source_media_url']) : null,
                    'categories_data' => !empty($simplified_categories) ? json_encode($simplified_categories) : null,
                    'tags_data' => !empty($simplified_tags) ? json_encode($simplified_tags) : null,
                ];

                // Log the data being inserted
                file_put_contents($log_file, "Attempting to insert data:\n" . print_r($insert_data, true) . "\n\n", FILE_APPEND);

                // Check if the table exists
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
                if (!$table_exists) {
                    file_put_contents($log_file, "ERROR: Table {$table_name} does not exist!\n\n", FILE_APPEND);
                    $failed_items[] = [
                        'title' => $title,
                        'reason' => 'Table does not exist'
                    ];
                    continue;
                }

                // Attempt the insert
                $result = $wpdb->insert(
                    $table_name,
                    $insert_data,
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );

                if ($result === false) {
                    // Log the database error
                    file_put_contents($log_file, "Database insert failed. Error: " . $wpdb->last_error . "\n\n", FILE_APPEND);
                    $failed_items[] = [
                        'title' => $title,
                        'reason' => $wpdb->last_error
                    ];
                } else {
                    // Log the database insert result
                    file_put_contents($log_file, "Database insert completed. Last insert ID: " . $wpdb->insert_id . "\n\n", FILE_APPEND);
                    $processed_items[] = $title;
                }
            } else {
                // Log that we're skipping an existing item
                file_put_contents($log_file, "Skipping existing item with title: " . $title . "\n\n", FILE_APPEND);
                $skipped_items[] = $title;
            }
        }

        // Log the final summary
        file_put_contents($log_file, "\n=== FINAL SUMMARY ===\n", FILE_APPEND);
        file_put_contents($log_file, "Total items processed: " . count($processed_items) . "\n", FILE_APPEND);
        file_put_contents($log_file, "Total items skipped: " . count($skipped_items) . "\n", FILE_APPEND);
        file_put_contents($log_file, "Total items failed: " . count($failed_items) . "\n\n", FILE_APPEND);

        if (!empty($processed_items)) {
            file_put_contents($log_file, "Successfully processed items:\n" . implode("\n", $processed_items) . "\n\n", FILE_APPEND);
        }

        if (!empty($skipped_items)) {
            file_put_contents($log_file, "Skipped items (already exist):\n" . implode("\n", $skipped_items) . "\n\n", FILE_APPEND);
        }

        if (!empty($failed_items)) {
            file_put_contents($log_file, "Failed items:\n", FILE_APPEND);
            foreach ($failed_items as $failed) {
                file_put_contents($log_file, "- {$failed['title']} (Reason: {$failed['reason']})\n", FILE_APPEND);
            }
        }

        // Log completion
        file_put_contents($log_file, "\n=== PROCESSING COMPLETED ===\n", FILE_APPEND);
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
