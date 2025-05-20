<?php
namespace SportsNewsFetcher;

class Database {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sports_news';
    }

    public function init() {
        add_action('sports_news_fetcher_cron_event', [$this, 'fetch_data']);
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title TEXT NOT NULL,
            content LONGTEXT NOT NULL,
            meta_title varchar(255) NULL,
            meta_description TEXT NULL,
            media_url TEXT NULL,
            categories_data LONGTEXT NULL,
            tags_data LONGTEXT NULL,
            added_to_post_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        $this->check_and_add_columns();

        $this->update_meta_description_column_type();

        $this->add_post_id_column();

        $this->create_training_prompts_table();
    }

    private function check_and_add_columns() {
        global $wpdb;
        $columns = ['media_url', 'categories_data', 'tags_data'];

        foreach ($columns as $column) {
            $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = '{$this->table_name}' AND column_name = '{$column}'");

            if (empty($row)) {
                $type = $column === 'media_url' ? 'TEXT' : 'LONGTEXT';
                $wpdb->query("ALTER TABLE {$this->table_name} ADD {$column} {$type} NULL");
            }
        }
    }

    private function update_meta_description_column_type() {
        global $wpdb;

        $column_info = $wpdb->get_row(
            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = '{$this->table_name}'
            AND COLUMN_NAME = 'meta_description'"
        );

        if ($column_info && strtolower($column_info->DATA_TYPE) === 'varchar') {
            $wpdb->query("ALTER TABLE {$this->table_name} MODIFY meta_description TEXT NULL");
        }
    }

    private function add_post_id_column() {
        global $wpdb;
        $column_info = $wpdb->get_row(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = '{$this->table_name}'
            AND COLUMN_NAME = 'post_id'"
        );

        if (empty($column_info)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD post_id BIGINT(20) UNSIGNED NULL");
        }
    }

    private function create_training_prompts_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sports_news_training_prompts';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            prompt TEXT NOT NULL,
            type VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        dbDelta($sql);

        $insert_prompts = [
            [
                'prompt' => 'Generate a post title for the following content:',
                'type' => 'post_tags_training'
            ],
            [
                'prompt' => 'Generate a post meta title and description for the following content:',
                'type' => 'post_meta_title_and_description_training'
            ],
        ];

        foreach ($insert_prompts as $prompt) {
            if ($wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE type = '{$prompt['type']}'") == 0) {
                $wpdb->insert($table_name, $prompt);
            }
        }
    }

}
