<?php
if (!defined('ABSPATH')) {
    exit;
}
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
</div>