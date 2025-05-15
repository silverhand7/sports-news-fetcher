<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'sports_news';
$per_page = 10;
$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$offset = ($paged - 1) * $per_page;

// Search functionality
$search_term = isset($_GET['sports_news_search']) ? sanitize_text_field($_GET['sports_news_search']) : '';
$where_clause = '';

if (!empty($search_term)) {
    $where_clause = $wpdb->prepare(
        'WHERE title LIKE %s OR meta_title LIKE %s OR meta_description LIKE %s OR content LIKE %s',
        '%' . $wpdb->esc_like($search_term) . '%',
        '%' . $wpdb->esc_like($search_term) . '%',
        '%' . $wpdb->esc_like($search_term) . '%',
        '%' . $wpdb->esc_like($search_term) . '%'
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

<div class="wrap">
    <h1>Sports News Settings</h1>

    <!-- Settings Form -->
    <form method="post" action="options.php">
        <?php
        settings_fields('sports_news_fetcher_settings_group');
        do_settings_sections('sports_news_fetcher_settings_page');
        submit_button();
        ?>
    </form>

    <!-- Manual Fetch Form -->
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

    <!-- News List -->
    <h2>Fetched Sports News</h2>
    <hr class="wp-header-end">

    <!-- Search Form -->
    <form method="get" action="">
        <input type="hidden" name="page" value="sports-news-fetcher">
        <p class="search-box">
            <label class="screen-reader-text" for="sports_news_search">Search Sports News:</label>
            <input type="search" id="sports_news_search" name="sports_news_search" value="<?php echo esc_attr($search_term); ?>" placeholder="Search by title, content, or meta data...">
            <?php submit_button('Search', 'button', 'submit_search', false); ?>
            <?php if (!empty($search_term)) { ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sports-news-fetcher')); ?>" class="button">Clear</a>
            <?php } ?>
        </p>
    </form>

    <!-- Bulk Actions Form -->
    <?php require_once SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'admin/views/bulk-actions.php'; ?>

    <!-- Pagination -->
    <?php require_once SPORTS_NEWS_FETCHER_PLUGIN_DIR . 'admin/views/pagination.php'; ?>
</div>
