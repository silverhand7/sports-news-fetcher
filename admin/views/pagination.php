<?php
if (!defined('ABSPATH')) {
    exit;
}

$pagination_args = [
    'base' => add_query_arg('paged', '%#%'),
    'format' => '',
    'prev_text' => __('&laquo;'),
    'next_text' => __('&raquo;'),
    'total' => $total_pages,
    'current' => $paged,
];

// Preserve search term in pagination
if (!empty($search_term)) {
    $pagination_args['add_args'] = ['sports_news_search' => $search_term];
}
?>

<div class="tablenav bottom">
    <div class="tablenav-pages">
        <?php echo paginate_links($pagination_args); ?>
    </div>
</div>
