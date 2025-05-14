<?php
/**
 * Plugin Name: Sports News Fetcher
 * Description: Fetches data from an external API and saves it into wp_posts daily.
 * Version: 1.5.0
 * Author: Refo
 */
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Activation hook to schedule the cron event.
function sports_news_fetcher_activation()
{
    global $wpdb;
    if (! wp_next_scheduled('sports_news_fetcher_cron_event')) {
        wp_schedule_event(time(), 'daily', 'sports_news_fetcher_cron_event');
    }

    $table_name = $wpdb->prefix.'sports_news';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title TEXT NOT NULL,
        content LONGTEXT NOT NULL,
        meta_title varchar(255) NULL,
        meta_description varchar(255) NULL,
        media_url TEXT NULL,
        categories_data LONGTEXT NULL,
        tags_data LONGTEXT NULL,
        added_to_post_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Check if media_url column exists
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = '{$wpdb->prefix}sports_news' AND column_name = 'media_url'");

    if (empty($row)) {
        $wpdb->query("ALTER TABLE $table_name ADD media_url TEXT NULL");
    }

    // Check if categories_data column exists
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = '{$wpdb->prefix}sports_news' AND column_name = 'categories_data'");

    if (empty($row)) {
        $wpdb->query("ALTER TABLE $table_name ADD categories_data LONGTEXT NULL");
    }

    // Check if tags_data column exists
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = '{$wpdb->prefix}sports_news' AND column_name = 'tags_data'");

    if (empty($row)) {
        $wpdb->query("ALTER TABLE $table_name ADD tags_data LONGTEXT NULL");
    }
}
register_activation_hook(__FILE__, 'sports_news_fetcher_activation');

// Deactivation hook to clear the scheduled event.
function sports_news_fetcher_deactivation()
{
    wp_clear_scheduled_hook('sports_news_fetcher_cron_event');
}
register_deactivation_hook(__FILE__, 'sports_news_fetcher_deactivation');

// Function to fetch and insert data.
function sports_news_fetcher_fetch_data($start_date = '', $end_date = '')
{
    global $wpdb;
    $api_key = get_option('sports_news_fetcher_api_key', '');
    if (empty($api_key)) {
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

    // Build API URL with date parameters if provided
    $api_url_base = $_SERVER['HTTP_HOST'] === 'fabrizio-news.test'
        ? 'http://ai-sports-news.test/api/v1/posts'
        : 'https://ai-articles-db8717205dd6.herokuapp.com/api/v1/posts';

    $query_params = [];

    $query_params['start_date'] = $start_date;

    $query_params['end_date'] = $end_date;

    do {
        $query_params['page'] = $current_page;
        $api_url = add_query_arg($query_params, $api_url_base);

        $response = wp_remote_get($api_url, [
            'headers' => [
                'Authorization' => 'Basic '.$api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $json_data = json_decode($body, true);
        $data = $json_data['data'] ?? [];

        // Get the last page number from meta data
        if ($current_page === 1) {
            $last_page = $json_data['meta']['last_page'] ?? 1;
        }

        if (! empty($data)) {
            foreach ($data as $item) {
                // Check if title exists in sports_news table
                $existing_ai_post = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sports_news WHERE title = %s",
                    sanitize_text_field($item['title'])
                ));

                if (! $existing_ai_post) {
                    // Insert new record
                    $wpdb->insert(
                        $wpdb->prefix.'sports_news',
                        [
                            'title' => sanitize_text_field($item['title']),
                            'content' => $item['content'],
                            'meta_title' => sanitize_text_field($item['meta_title']),
                            'meta_description' => sanitize_text_field($item['meta_description']),
                            'media_url' => ! empty($item['source_media_url']) ? esc_url_raw($item['source_media_url']) : null,
                            'categories_data' => ! empty($item['wp_categories_internal_links']) ? json_encode($item['wp_categories_internal_links']) : null,
                            'tags_data' => ! empty($item['wp_tags_internal_links']) ? json_encode($item['wp_tags_internal_links']) : null,
                        ],
                        ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
                    );
                }
            }
        }

        $current_page++;
    } while ($current_page <= $last_page);
}
add_action('sports_news_fetcher_cron_event', 'sports_news_fetcher_fetch_data');

// Add menu to the sidebar
function sports_news_fetcher_menu()
{
    add_menu_page(
        'Sports News',
        'Sports News',
        'manage_options',
        'sports-news-fetcher',
        'sports_news_fetcher_settings_page',
        'dashicons-rss',
        30
    );
}
add_action('admin_menu', 'sports_news_fetcher_menu');

// Plugin settings page
function sports_news_fetcher_settings_page()
{ ?>
    <div class="wrap">
        <?php
            global $wpdb;
            $table_name = $wpdb->prefix.'sports_news';
            $per_page = 10;
            $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
            $offset = ($paged - 1) * $per_page;

            // Search functionality
            $search_term = isset($_GET['sports_news_search']) ? sanitize_text_field($_GET['sports_news_search']) : '';
            $where_clause = '';

            if (! empty($search_term)) {
                $where_clause = $wpdb->prepare(
                    'WHERE title LIKE %s OR meta_title LIKE %s OR meta_description LIKE %s OR content LIKE %s',
                    '%'.$wpdb->esc_like($search_term).'%',
                    '%'.$wpdb->esc_like($search_term).'%',
                    '%'.$wpdb->esc_like($search_term).'%',
                    '%'.$wpdb->esc_like($search_term).'%'
                );
            }

            $entries = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ));

            $total_entries = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause");
            $total_pages = ceil($total_entries / $per_page);
        ?>
        <h1>Sports News Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('sports_news_fetcher_settings_group');
                do_settings_sections('sports_news_fetcher_settings_page');
                submit_button();
            ?>
        </form>

        <form method="post" action="">
            <?php
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d');
            ?>
            <input type="hidden" name="sports_news_fetcher_manual_fetch" value="1">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Start Date</th>
                    <td>
                        <input type="date" name="sports_news_start_date" class="regular-text" value="<?php echo esc_attr($start_date); ?>">
                        <p class="description">Fetch news from this date</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">End Date</th>
                    <td>
                        <input type="date" name="sports_news_end_date" class="regular-text" value="<?php echo esc_attr($end_date); ?>">
                        <p class="description">Fetch news until this date</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Fetch Data Now', 'primary', 'submit_manual_fetch'); ?>
        </form>

        <h2>Fetched Sports News</h2>
        <hr class="wp-header-end">

        <!-- Search form -->
        <form method="get" action="">
            <input type="hidden" name="page" value="sports-news-fetcher">
            <p class="search-box">
                <label class="screen-reader-text" for="sports_news_search">Search Sports News:</label>
                <input type="search" id="sports_news_search" name="sports_news_search" value="<?php echo esc_attr($search_term); ?>" placeholder="Search by title, content, or meta data...">
                <?php submit_button('Search', 'button', 'submit_search', false); ?>
                <?php if (! empty($search_term)) { ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sports-news-fetcher')); ?>" class="button">Clear</a>
                <?php } ?>
            </p>
        </form>

        <!-- Bulk import form -->
        <form method="post" action="" id="bulk-action-form">
            <?php
                // Add the bulk actions dropdown
                $actions = [
                    '' => 'Bulk Actions',
                    'import' => 'Import Selected',
                    'delete' => 'Delete Selected',
                ];
            ?>

            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                    <select name="bulk_action" id="bulk-action-selector-top">
                        <?php
                            foreach ($actions as $value => $label) {
                                echo '<option value="'.esc_attr($value).'">'.esc_html($label).'</option>';
                            }
                        ?>
                    </select>

                    <?php submit_button('Apply', 'action', 'do_bulk_action', false); ?>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped posts" style="overflow: hidden;">
                <thead>
                    <tr>
                        <th class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all"></th>
                        <th class="manage-column column-title">Title</th>
                        <th class="manage-column column-meta-title">Meta Title</th>
                        <th class="manage-column column-meta-description">Meta Description</th>
                        <th class="manage-column column-added-to-post-at">Added to Post at</th>
                        <th class="manage-column column-media-url">Image</th>
                        <th class="manage-column column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (! empty($entries)) { ?>
                        <?php foreach ($entries as $entry) { ?>
                            <tr>
                                <td class="check-column" style="padding: 10px;">
                                    <?php if (empty($entry->added_to_post_at)) { ?>
                                        <input type="checkbox" name="entry_ids[]" class="check-item" value="<?php echo esc_attr($entry->id); ?>">
                                    <?php } ?>
                                </td>
                                <td class="title column-title"> <?php echo esc_html($entry->title); ?> </td>
                                <td class="meta-title column-meta-title"> <?php echo esc_html($entry->meta_title); ?> </td>
                                <td class="meta-description column-meta-description"> <?php echo esc_html($entry->meta_description); ?> </td>
                                <td class="added-to-post-at column-added-to-post-at"> <?php echo esc_html($entry->added_to_post_at); ?> </td>
                                <td class="media-url column-media-url">
                                    <?php if (! empty($entry->media_url)) { ?>
                                        <img src="<?php echo esc_url($entry->media_url); ?>" alt="<?php echo esc_attr($entry->title); ?>" style="max-width: 100px; height: auto;">
                                    <?php } ?>
                                </td>
                                <td class="actions column-actions" style="display: flex; gap: 7px; flex-direction: column;">
                                    <form method="post">
                                        <input type="hidden" name="import_entry" value="<?php echo esc_attr($entry->id); ?>">
                                        <?php submit_button('Import', 'primary', 'submit_import', false, ['style' => 'width: 100%;']); ?>
                                    </form>

                                    <a href="?page=sports-news-fetcher&preview_entry=<?php echo esc_attr($entry->id); ?>" class="button button-secondary" style="width: 100%; text-align: center; display: inline-block; text-decoration: none;">Preview</a>

                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this entry?');">
                                        <input type="hidden" name="delete_entry" value="<?php echo esc_attr($entry->id); ?>">
                                        <?php submit_button('Delete', 'delete', 'submit_delete', false, ['style' => 'width: 100%; background-color: #dc3545; color: white; border-color: #dc3545;']); ?>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="7">No records found.<?php echo ! empty($search_term) ? ' Try a different search term.' : ''; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-bottom"></th>
                        <th class="manage-column column-title">Title</th>
                        <th class="manage-column column-meta-title">Meta Title</th>
                        <th class="manage-column column-meta-description">Meta Description</th>
                        <th class="manage-column column-added-to-post-at">Added to Post at</th>
                        <th class="manage-column column-media-url">Image</th>
                        <th class="manage-column column-actions">Actions</th>
                    </tr>
                </tfoot>
            </table>
        </form>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                    $pagination_args = [
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo; Previous'),
                        'next_text' => __('Next &raquo;'),
                        'total' => $total_pages,
                        'current' => $paged,
                    ];

                    // Preserve search term in pagination
                    if (! empty($search_term)) {
                        $pagination_args['add_args'] = ['sports_news_search' => $search_term];
                    }

                    echo paginate_links($pagination_args);
                ?>
            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // Select all checkboxes
            $('#cb-select-all, #cb-select-all-bottom').on('click', function() {
                $('.check-item').prop('checked', $(this).prop('checked'));
            });

            // If all checkboxes are selected, check the "select all" checkbox
            $('.check-item').on('click', function() {
                if ($('.check-item:checked').length === $('.check-item').length) {
                    $('#cb-select-all, #cb-select-all-bottom').prop('checked', true);
                } else {
                    $('#cb-select-all, #cb-select-all-bottom').prop('checked', false);
                }
            });

            // Submit form confirmation for bulk actions
            $('#bulk-action-form').on('submit', function(e) {
                var action = $('#bulk-action-selector-top').val();

                if (action === 'delete') {
                    var checked = $('.check-item:checked').length;
                    if (checked === 0) {
                        alert('Please select at least one item to delete.');
                        e.preventDefault();
                        return false;
                    }

                    if (!confirm('Are you sure you want to delete ' + checked + ' selected items?')) {
                        e.preventDefault();
                        return false;
                    }
                } else if (action === 'import') {
                    var checked = $('.check-item:checked').length;
                    if (checked === 0) {
                        alert('Please select at least one item to import.');
                        e.preventDefault();
                        return false;
                    }
                } else if (action === '') {
                    alert('Please select an action.');
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
<?php

}


// Handle delete and import actions
function sports_news_fetcher_handle_actions()
{
    global $wpdb;
    $table_name = $wpdb->prefix.'sports_news';

    // Handle single delete action
    if (isset($_POST['delete_entry'])) {
        $wpdb->delete($table_name, ['id' => intval($_POST['delete_entry'])]);
        add_action('admin_notices', function () {
            echo '<div class="notice notice-success"><p>Entry deleted successfully!</p></div>';
        });
    }

    // Handle single import action
    if (isset($_POST['import_entry'])) {
        sports_news_fetcher_import_entry(intval($_POST['import_entry']));
        add_action('admin_notices', function () {
            echo '<div class="notice notice-success"><p>Entry imported successfully!</p></div>';
        });
    }

    // Handle bulk actions
    if (isset($_POST['do_bulk_action']) && isset($_POST['bulk_action']) && isset($_POST['entry_ids']) && is_array($_POST['entry_ids'])) {
        $action = sanitize_text_field($_POST['bulk_action']);
        $entry_ids = array_map('intval', $_POST['entry_ids']);

        if ($action === 'delete' && ! empty($entry_ids)) {
            $count = 0;
            foreach ($entry_ids as $id) {
                $result = $wpdb->delete($table_name, ['id' => $id]);
                if ($result) {
                    $count++;
                }
            }

            add_action('admin_notices', function () use ($count) {
                echo '<div class="notice notice-success"><p>'.$count.' entries deleted successfully!</p></div>';
            });
        } elseif ($action === 'import' && ! empty($entry_ids)) {
            $count = 0;
            foreach ($entry_ids as $id) {
                $result = sports_news_fetcher_import_entry($id);
                if ($result) {
                    $count++;
                }
            }

            add_action('admin_notices', function () use ($count) {
                echo '<div class="notice notice-success"><p>'.$count.' entries imported successfully!</p></div>';
            });
        }
    }
    // Store preview content in a variable instead of echoing directly
    $preview_content = '';
    if (isset($_GET['preview_entry'])) {
        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['preview_entry'])));
        if ($entry) {
            $preview_content .= '<div class="notice notice-info">
                <p><strong>Meta title:</strong> '.esc_html($entry->meta_title).'</p>
                <p><strong>Meta description:</strong> '.esc_html($entry->meta_description).'</p>
                <p><strong>Categories:</strong> ';

                if (!empty($entry->categories_data)) {
                    $categories = json_decode($entry->categories_data, true);
                    $category_names = array_map(function($category) {
                        return '<a href="/category/'.$category['slug'].'">'.$category['name'].'</a>';
                    }, $categories);
                    $preview_content .= implode(', ', $category_names);
                } else {
                    $preview_content .= 'None';
                }

            $preview_content .= '</p>';

            if (!empty($entry->tags_data)) {
                $preview_content .= '<p><strong>Tags:</strong> ';
                $tags = json_decode($entry->tags_data, true);
                $tag_names = array_map(function($tag) {
                    return '<a href="/tag/'.$tag['slug'].'">'.$tag['name'].'</a>';
                }, $tags);
                $preview_content .= implode(', ', $tag_names);
                $preview_content .= '</p>';
            }
            $preview_content .= '<h1>'.$entry->title.'</h1><p>'.wp_kses_post($entry->content).'</p>';
            $preview_content .= '</div>';

            // Add the preview content to be displayed later
            add_action('admin_notices', function() use ($preview_content) {
                echo $preview_content;
            });
        }
    }
}
add_action('admin_init', 'sports_news_fetcher_handle_actions');

// Helper function to import a single entry
function sports_news_fetcher_import_entry($id)
{
    global $wpdb;
    $table_name = $wpdb->prefix.'sports_news';

    $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    if (! $entry) {
        return false;
    }

    // Create post
    $post_id = wp_insert_post([
        'post_title' => $entry->title,
        'post_content' => $entry->content,
        'post_status' => 'draft',
        'post_type' => 'post',
    ]);

    if (! $post_id || is_wp_error($post_id)) {
        return false;
    }

    // Handle categories
    if (! empty($entry->categories_data)) {
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

                if (! is_wp_error($new_category)) {
                    $category_ids[] = $new_category['term_id'];
                }
            }
        }

        // Set post categories
        if (! empty($category_ids)) {
            wp_set_post_categories($post_id, $category_ids);
        }
    }

    // Handle tags
    if (! empty($entry->tags_data)) {
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

                if (! is_wp_error($new_tag)) {
                    $tag_ids[] = $new_tag['term_id'];
                }
            }
        }

        // Set post tags
        if (! empty($tag_ids)) {
            wp_set_object_terms($post_id, $tag_ids, 'post_tag');
        }
    }

    // Update added_to_post_at timestamp
    $wpdb->update(
        $table_name,
        ['added_to_post_at' => current_time('mysql')],
        ['id' => $entry->id],
        ['%s'],
        ['%d']
    );

    // Add meta_description to Yoast SEO meta description
    if (! empty($entry->meta_description)) {
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $entry->meta_description);
    }

    // Handle featured image
    if (! empty($entry->media_url)) {
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/image.php';

        // Download and attach the image
        $image_url = $entry->media_url;
        $upload = media_sideload_image($image_url, $post_id, '', 'id');

        if (! is_wp_error($upload)) {
            // Set as featured image
            set_post_thumbnail($post_id, $upload);
        }
    }

    return true;
}

// Handle manual data fetch
function sports_news_fetcher_manual_fetch()
{
    if (isset($_POST['sports_news_fetcher_manual_fetch']) && current_user_can('manage_options')) {
        $start_date = isset($_POST['sports_news_start_date']) ? sanitize_text_field($_POST['sports_news_start_date']) : '';
        $end_date = isset($_POST['sports_news_end_date']) ? sanitize_text_field($_POST['sports_news_end_date']) : '';

        sports_news_fetcher_fetch_data($start_date, $end_date);

        add_action('admin_notices', function () {
            echo '<div class="notice notice-success"><p>Data fetched successfully!</p></div>';
        });
    }
}
add_action('admin_init', 'sports_news_fetcher_manual_fetch');

// Register settings
function sports_news_fetcher_register_settings()
{
    register_setting('sports_news_fetcher_settings_group', 'sports_news_fetcher_api_key');
    add_settings_section('sports_news_fetcher_section', 'API Settings', null, 'sports_news_fetcher_settings_page');
    add_settings_field('sports_news_fetcher_api_key', 'API Key', 'sports_news_fetcher_api_key_callback', 'sports_news_fetcher_settings_page', 'sports_news_fetcher_section');
}
add_action('admin_init', 'sports_news_fetcher_register_settings');

// API Key input field callback
function sports_news_fetcher_api_key_callback()
{
    $api_key = get_option('sports_news_fetcher_api_key', '');
    echo '<input type="text" name="sports_news_fetcher_api_key" value="'.esc_attr($api_key).'" class="regular-text">';
}
