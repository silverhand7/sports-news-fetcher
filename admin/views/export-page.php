<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Export Posts for AI Training</h1>
    <hr class="wp-header-end">
    <p>You can export posts to a CSV file to check the data.</p>
    <p>You can export posts to a JSONL file to train the AI model.</p>

    <form method="post" action="">
        <h2>Export Posts for Post Tags Training</h2>
        <input type="hidden" name="action" value="export_posts_for_post_tags_training">
        <select name="post_type">
            <option value="post">CSV</option>
            <option value="page">JSONL</option>
        </select>
        <input type="submit" value="Export" class="button button-primary">
    </form>
    <hr style="margin: 20px 0;">
    <form method="post" action="">
        <h2>Export Posts for Post Meta Title and Meta Description Training</h2>
        <input type="hidden" name="action" value="export_posts_for_post_meta_title_and_description_training">
        <select name="post_type">
            <option value="post">CSV</option>
            <option value="page">JSONL</option>
        </select>
        <input type="submit" value="Export" class="button button-primary">
    </form>
</div>
