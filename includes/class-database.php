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

    public function update_meta_description_column_type() {
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
}
