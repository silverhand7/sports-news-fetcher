<?php

namespace SportsNewsFetcher;

class Admin {
    public function init() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_notices', [$this, 'handle_preview']);
        add_action('admin_init', [$this, 'handle_actions']);
    }

    public function add_menu_page() {
        add_menu_page(
            'Sports News',
            'Sports News',
            'manage_options',
            'sports-news-fetcher',
            [$this, 'render_admin_page'],
            file_get_contents(SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'admin/icon/ball-base64.txt'),
            30
        );

        add_submenu_page(
            'sports-news-fetcher',
            'Settings',
            'Settings',
            'manage_options',
            'sports-news-fetcher-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'sports-news-fetcher',
            'Export',
            'Export',
            'manage_options',
            'sports-news-fetcher-export',
            [$this, 'render_export_page']
        );
    }

    public function register_settings() {
        register_setting('sports_news_fetcher_settings_group', 'sports_news_fetcher_api_key');
        add_settings_section('sports_news_fetcher_section', 'API Settings', [$this, 'render_section_description'], 'sports_news_fetcher_settings_page');
        add_settings_field('sports_news_fetcher_api_key', 'API Key', [$this, 'render_api_key_field'], 'sports_news_fetcher_settings_page', 'sports_news_fetcher_section');
    }

    public function render_section_description() {
        echo '<p>Enter your API key to connect to the sports news service.</p>';
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

    public function render_settings_page() {
        require_once SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_export_page() {
        require_once SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'admin/views/export-page.php';
    }

    public function handle_preview() {
        if (isset($_GET['preview_entry'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'sports_news';
            $entry = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                intval($_GET['preview_entry'])
            ));

            if ($entry) {
                echo '<div class="notice notice-info">';
                echo '<p><strong>Meta title:</strong> ' . esc_html($entry->meta_title) . '</p>';
                echo '<p><strong>Meta description:</strong> ' . esc_html($entry->meta_description) . '</p>';
                echo '<p><strong>Categories:</strong> ';

                if (!empty($entry->categories_data)) {
                    $categories = json_decode($entry->categories_data, true);
                    $category_names = array_map(function($category) {
                        return '<a href="/category/' . $category['slug'] . '">' . $category['name'] . '</a>';
                    }, $categories);
                    echo implode(', ', $category_names);
                } else {
                    echo 'None';
                }

                echo '</p>';

                if (!empty($entry->tags_data)) {
                    echo '<p><strong>Tags:</strong> ';
                    $tags = json_decode($entry->tags_data, true);
                    $tag_names = array_map(function($tag) {
                        return '<a href="/tag/' . $tag['slug'] . '">' . $tag['name'] . '</a>';
                    }, $tags);
                    echo implode(', ', $tag_names);
                    echo '</p>';
                }

                echo '<h1>' . esc_html($entry->title) . '</h1>';
                echo '<div class="entry-content">' . wp_kses_post($entry->content) . '</div>';
                echo '</div>';
            }
        }
    }

    public function handle_actions() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sports_news';

        // Handle single delete action
        if (isset($_POST['delete_entry'])) {
            $wpdb->delete($table_name, ['id' => intval($_POST['delete_entry'])]);
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success"><p>Entry deleted successfully!</p></div>';
            });
        }

        // Handle single import action
        if (isset($_POST['import_entry'])) {
            $this->import_entry(intval($_POST['import_entry']));
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success"><p>Entry imported successfully!</p></div>';
            });
        }

        // Handle bulk actions
        if (isset($_POST['do_bulk_action']) && isset($_POST['bulk_action']) && isset($_POST['entry_ids']) && is_array($_POST['entry_ids'])) {
            $action = sanitize_text_field($_POST['bulk_action']);
            $entry_ids = array_map('intval', $_POST['entry_ids']);

            if ($action === 'delete' && !empty($entry_ids)) {
                $count = 0;
                foreach ($entry_ids as $id) {
                    $result = $wpdb->delete($table_name, ['id' => $id]);
                    if ($result) {
                        $count++;
                    }
                }

                add_action('admin_notices', function () use ($count) {
                    echo '<div class="notice notice-success"><p>' . $count . ' entries deleted successfully!</p></div>';
                });
            } elseif ($action === 'import' && !empty($entry_ids)) {
                $count = 0;
                foreach ($entry_ids as $id) {
                    $result = $this->import_entry($id);
                    if ($result) {
                        $count++;
                    }
                }

                add_action('admin_notices', function () use ($count) {
                    echo '<div class="notice notice-success"><p>' . $count . ' entries imported successfully!</p></div>';
                });
            }
        }

        if (isset($_POST['export_posts_for_post_tags_training'])) {
            $this->export_posts_for_post_tags_training(
                sanitize_text_field($_POST['post_tags_training_prompt']),
                intval(sanitize_text_field($_POST['limit'])),
                sanitize_text_field($_POST['export_format'])
            );
            wp_redirect(admin_url('admin.php?page=sports-news-fetcher-export'));
            exit;
        }
    }

    private function import_entry($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sports_news';

        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        if (!$entry) {
            return false;
        }

        // Create post
        $post_id = wp_insert_post([
            'post_title' => $entry->title,
            'post_content' => $entry->content,
            'post_status' => 'draft',
            'post_type' => 'post',
        ]);

        if (!$post_id || is_wp_error($post_id)) {
            return false;
        }

        // Handle categories
        if (!empty($entry->categories_data)) {
            $categories_data = json_decode($entry->categories_data, true);
            $category_ids = [];

            foreach ($categories_data as $category_data) {
                // Check if category exists by slug
                $existing_category = get_category_by_slug($category_data['slug']);

                if ($existing_category) {
                    $category_ids[] = $existing_category->term_id;
                } else {
                    // Create new category
                    $new_category = wp_insert_term(
                        $category_data['name'],
                        'category',
                        [
                            'slug' => $category_data['slug'],
                            'description' => $category_data['description'],
                        ]
                    );

                    if (!is_wp_error($new_category)) {
                        $category_ids[] = $new_category['term_id'];
                    }
                }
            }

            // Set post categories
            if (!empty($category_ids)) {
                wp_set_post_categories($post_id, $category_ids);
            }
        }

        // Handle tags
        if (!empty($entry->tags_data)) {
            $tags_data = json_decode($entry->tags_data, true);
            $tag_ids = [];

            foreach ($tags_data as $tag_data) {
                // Check if tag exists by slug
                $existing_tag = get_term_by('slug', $tag_data['slug'], 'post_tag');

                if ($existing_tag) {
                    $tag_ids[] = $existing_tag->term_id;
                } else {
                    // Create new tag
                    $new_tag = wp_insert_term(
                        $tag_data['name'],
                        'post_tag',
                        [
                            'slug' => $tag_data['slug'],
                            'description' => $tag_data['description'] ?? '',
                        ]
                    );

                    if (!is_wp_error($new_tag)) {
                        $tag_ids[] = $new_tag['term_id'];
                    }
                }
            }

            // Set post tags
            if (!empty($tag_ids)) {
                wp_set_object_terms($post_id, $tag_ids, 'post_tag');
            }
        }

        // Update added_to_post_at timestamp
        $wpdb->update(
            $table_name,
            ['added_to_post_at' => current_time('mysql'), 'post_id' => $post_id],
            ['id' => $entry->id],
            ['%s', '%d'],
            ['%d']
        );
        // Add meta_description to Yoast SEO meta description
        if (!empty($entry->meta_description)) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $entry->meta_description);
        }

        // Add meta_title to Yoast SEO title
        if (!empty($entry->meta_title)) {
            update_post_meta($post_id, '_yoast_wpseo_title', $entry->meta_title);
        }

        // Handle featured image
        if (!empty($entry->media_url)) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            // Download and attach the image
            $image_url = $entry->media_url;
            $upload = media_sideload_image($image_url, $post_id, '', 'id');

            if (!is_wp_error($upload)) {
                // Set as featured image
                set_post_thumbnail($post_id, $upload);
            }
        }

        return true;
    }

    private function export_posts_for_post_tags_training(
        $post_tags_training_prompt,
        $limit,
        $export_format,
    ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sports_news_training_prompts';
        $wpdb->update(
            $table_name,
            ['prompt' => $post_tags_training_prompt],
            ['type' => 'post_tags_training'],
            ['%s'],
            ['%s']
        );

        $posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => $limit,
        ]);

        $data = [];

        foreach ($posts as $post) {
            $tags = get_the_tags($post->ID);
            $tag_names = [];
            if ($tags) {
                foreach ($tags as $tag) {
                    $tag_names[] = $tag->name;
                }
            }

            $data[] = [
                'system_prompt' => $post_tags_training_prompt,
                'user_prompt' => $post->post_content,
                'assistant_response' => '[' . implode(', ', $tag_names) . ']',
            ];
        }

        if ($export_format === 'csv') {
            $this->export_csv($data);
        }
    }

    private function export_csv(array $data)
    {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="posts-export-' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for proper Excel encoding
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write headers
        fputcsv($output, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

}
