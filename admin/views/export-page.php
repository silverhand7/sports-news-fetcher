<?php
if (!defined('ABSPATH')) {
    exit;
}
global $wpdb;
$table_name = $wpdb->prefix . 'sports_news_training_prompts';
$post_tags_training_prompt = $wpdb->get_var("SELECT prompt FROM $table_name WHERE type = 'post_tags_training'");
?>

<div class="wrap">
    <h1>Export Posts for AI Training</h1>

    <p>You can export posts to a CSV file to check the data.</p>
    <p>You can export posts to a JSONL file to train the AI model.</p>

    <form method="post" action="<?php echo admin_url('admin.php'); ?>" class="export-form">
        <input type="hidden" name="page" value="sports-news-fetcher-export">
        <input type="hidden" name="export_posts_for_post_tags_training" value="1">

        <div class="form-section">
            <h2>Export Posts for Post Tags Training</h2>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Training Prompt</th>
                    <td>
                        <textarea
                            name="post_tags_training_prompt"
                            id="post_tags_training_prompt"
                            rows="10"
                            class="large-text code"
                        ><?php echo esc_textarea($post_tags_training_prompt); ?></textarea>
                    </td>
                </tr>
                <tr valign="top"></tr>
                    <th scope="row">Export Format</th>
                    <td>
                        <select name="export_format" id="export_format" class="regular-text">
                            <option value="csv">CSV</option>
                            <option value="jsonl">JSONL</option>
                        </select>
                    </td>
                </tr>
            </table>

            <div class="form-actions">
                <?php submit_button('Export and save prompt', 'primary', 'handle_actions'); ?>
            </div>
        </div>
    </form>

    <hr style="margin: 20px 0;">

    <!-- <form method="post" action="">
        <h2>Export Posts for Post Meta Title and Meta Description Training</h2>
        <input type="hidden" name="export_posts_for_post_meta_title_and_description_training" value="1">
        <select name="post_type">
            <option value="post">CSV</option>
            <option value="page">JSONL</option>
        </select>
        <input type="submit" value="Export" class="button button-primary">
    </form> -->
</div>
