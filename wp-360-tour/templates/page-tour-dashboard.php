<?php
/**
 * Template Name: 360° Tour Dashboard
 */

// Ensure only administrators can access this page
if (!current_user_can('manage_tours')) {
    wp_redirect(home_url());
    exit;
}

get_header();
?>

<div class="container">
    <?php
    // Include the dashboard content
    include(WP_360_TOUR_PLUGIN_DIR . 'templates/admin/dashboard-content.php');
    ?>
</div>

<?php get_footer(); ?> 