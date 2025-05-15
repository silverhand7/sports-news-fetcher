<?php
if (!defined('ABSPATH')) {
    exit;
}

$actions = [
    '' => 'Bulk Actions',
    'import' => 'Import Selected',
    'delete' => 'Delete Selected',
];
?>

<form method="post" action="" id="bulk-action-form">
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
            <select name="bulk_action" id="bulk-action-selector-top">
                <?php
                foreach ($actions as $value => $label) {
                    echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
                }
                ?>
            </select>
            <?php submit_button('Apply', 'action', 'do_bulk_action', false); ?>
        </div>
    </div>

    <table class="wp-list-table widefat striped posts" style="overflow: hidden;">
        <thead>
            <tr>
                <th class="manage-column column-cb" style="width: 25px; padding: 2px;">
                    <input type="checkbox" id="cb-select-all">
                </th>
                <th class="manage-column column-title">Title</th>
                <th class="manage-column column-meta-title">Meta Title</th>
                <th class="manage-column column-meta-description">Meta Description</th>
                <th class="manage-column column-added-to-post-at" style="width: 80px;">Has Post</th>
                <th class="manage-column column-media-url">Image</th>
                <th class="manage-column column-actions" style="width: 100px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($entries)) { ?>
                <?php foreach ($entries as $entry) { ?>
                    <tr>
                        <td class="check-column" style="padding: 10px;">
                            <?php if (empty($entry->added_to_post_at)) { ?>
                                <input type="checkbox" name="entry_ids[]" class="check-item" value="<?php echo esc_attr($entry->id); ?>">
                            <?php } ?>
                        </td>
                        <td class="title column-title"><?php echo esc_html($entry->title); ?></td>
                        <td class="meta-title column-meta-title"><?php echo esc_html($entry->meta_title); ?></td>
                        <td class="meta-description column-meta-description"><?php echo esc_html($entry->meta_description); ?></td>
                        <td class="added-to-post-at column-added-to-post-at" style="padding-left: 27px">
                            <?php if (!empty($entry->added_to_post_at)) { ?>
                                <span class="dashicons dashicons-yes" style="color: green;" title="Added to post on <?php echo esc_attr($entry->added_to_post_at); ?>"></span>
                            <?php } else { ?>
                                <span class="dashicons dashicons-no" style="color: red;" title="Not added to post yet"></span>
                            <?php } ?>
                        </td>
                        <td class="media-url column-media-url">
                            <?php if (!empty($entry->media_url)) { ?>
                                <img src="<?php echo esc_url($entry->media_url); ?>" alt="<?php echo esc_attr($entry->title); ?>" style="max-width: 100px; height: auto;">
                            <?php } ?>
                        </td>
                        <td class="actions column-actions" style="display: flex; gap: 7px; flex-direction: column; width: 100px;">
                            <button type="button" class="button button-primary" onclick="importEntry(<?php echo esc_attr($entry->id); ?>, '<?php echo esc_js($entry->title); ?>')">Import</button>
                            <a href="?page=sports-news-fetcher&preview_entry=<?php echo esc_attr($entry->id); ?>" class="button" style="text-align: center;">Preview</a>
                            <button type="button" class="button delete" onclick="deleteEntry(<?php echo esc_attr($entry->id); ?>, '<?php echo esc_js($entry->title); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="7">No records found.<?php echo !empty($search_term) ? ' Try a different search term.' : ''; ?></td>
                </tr>
            <?php } ?>
        </tbody>
        <tfoot>
            <tr>
                <th class="manage-column column-cb" style="width: 25px; padding: 2px;"><input type="checkbox" id="cb-select-all-bottom"></th>
                <th class="manage-column column-title">Title</th>
                <th class="manage-column column-meta-title">Meta Title</th>
                <th class="manage-column column-meta-description">Meta Description</th>
                <th class="manage-column column-added-to-post-at">Status</th>
                <th class="manage-column column-media-url">Image</th>
                <th class="manage-column column-actions">Actions</th>
            </tr>
        </tfoot>
    </table>
</form>
