<?php
/**
 * Plugin Name: WP 360° Tour
 * Description: Create interactive 360° panorama tours with hotspots
 * Version: 1.0
 * Author: Kahlil Calavas
 * Author URI: https://kahlilcalavas.com
 * Text Domain: wp-360-tour
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

// Define plugin constants
define('WP_360_TOUR_VERSION', '1.0');
define('WP_360_TOUR_MIN_WP_VERSION', '5.0');
define('WP_360_TOUR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_360_TOUR_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

class WP_360_Tour {
    public function __construct() {
        // Register post types and taxonomies
        add_action('init', [$this, 'register_post_types']);
        add_action('after_setup_theme', [$this, 'add_image_support']);
        
        // Frontend scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 100);
        add_action('wp_enqueue_scripts', [$this, 'get_tour_hotspots'], 101);
        add_action('wp_head', [$this, 'add_viewport_meta']);
        
        // AJAX handlers
        add_action('wp_ajax_save_hotspot', [$this, 'save_hotspot_ajax']);
        add_action('wp_ajax_get_hotspot_data', [$this, 'get_hotspot_data_ajax']);
        add_action('wp_ajax_nopriv_get_hotspot_data', [$this, 'get_hotspot_data_ajax']);
        add_action('wp_ajax_create_tour', [$this, 'handle_tour_creation']);
        add_action('wp_ajax_delete_tour', [$this, 'handle_tour_deletion']);
        add_action('wp_ajax_delete_hotspot', [$this, 'handle_hotspot_deletion']);
        
        // Templates
        add_filter('single_template', [$this, 'load_tour_template']);
        add_filter('body_class', [$this, 'add_body_classes']);
        
        // Admin bar
        add_action('wp', [$this, 'remove_admin_bar']);

        if (is_admin()) {
            add_action('admin_init', [$this, 'add_capabilities']);
            add_action('admin_menu', [$this, 'add_admin_menu']);
        }

        // Add page template
        add_filter('theme_page_templates', [$this, 'add_tour_dashboard_template']);
        add_filter('template_include', [$this, 'load_tour_dashboard_template']);
    }

    public function register_post_types() {
        // Tour post type
        register_post_type('tour', [
            'labels' => [
                'name' => 'Tours',
                'singular_name' => 'Tour',
                'menu_name' => 'Tours',
                'add_new' => 'Add New Tour',
                'add_new_item' => 'Add New Tour',
                'edit_item' => 'Edit Tour',
                'new_item' => 'New Tour',
                'view_item' => 'View Tour',
                'search_items' => 'Search Tours',
                'not_found' => 'No tours found',
                'not_found_in_trash' => 'No tours found in Trash'
            ],
            'public' => true,
            'has_archive' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'tour'],
            'capability_type' => 'post',
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-location',
            'supports' => [
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'custom-fields'
            ]
        ]);

        // Hotspot post type
        register_post_type('hotspot', [
            'labels' => [
                'name' => 'Hotspots',
                'singular_name' => 'Hotspot',
                'menu_name' => 'Hotspots',
                'add_new' => 'Add New Hotspot',
                'add_new_item' => 'Add New Hotspot',
                'edit_item' => 'Edit Hotspot',
                'new_item' => 'New Hotspot',
                'view_item' => 'View Hotspot',
                'search_items' => 'Search Hotspots',
                'not_found' => 'No hotspots found',
                'not_found_in_trash' => 'No hotspots found in Trash'
            ],
            'public' => true,
            'has_archive' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'hotspot'],
            'capability_type' => 'post',
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-location-alt',
            'supports' => [
                'title',
                'editor',
                'thumbnail'
            ]
        ]);
    }

    public function add_image_support() {
        add_theme_support('post-thumbnails');
        add_post_type_support('tour', 'thumbnail');
        add_image_size('panorama', 4096, 2048, true);

        add_filter('wp_handle_upload_prefilter', function($file) {
            if ($file['type'] === 'image/jpeg' || $file['type'] === 'image/png') {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $image = wp_get_image_editor($file['tmp_name']);
                if (!is_wp_error($image)) {
                    $image->resize(4096, 2048, true);
                    $image->save($file['tmp_name']);
                }
            }
            return $file;
        });
    }

    public function enqueue_scripts() {
        if (!is_singular('tour')) {
            return;
        }

        wp_dequeue_script('aframe');
        wp_deregister_script('aframe');
        wp_dequeue_script('three');
        wp_deregister_script('three');

        wp_enqueue_script('three-js', 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js', [], null, true);
        wp_enqueue_style('wp-360-tour', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0');
        wp_enqueue_script('wp-360-tour', plugin_dir_url(__FILE__) . 'assets/js/tour.js?2', ['three-js', 'jquery'], '1.0', true);
        
        wp_localize_script('wp-360-tour', 'wpAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('save_hotspot_nonce')
        ]);
    }

    public function add_viewport_meta() {
        if (is_singular('tour')) {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">';
        }
    }

    public function save_hotspot_ajax() {
        // Add error logging
        error_log('Received hotspot save request: ' . print_r($_POST, true));
        error_log('Files: ' . print_r($_FILES, true));

        if (!check_ajax_referer('save_hotspot_nonce', 'nonce', false)) {
            error_log('Nonce verification failed');
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        if (!current_user_can('manage_hotspots')) {
            error_log('User lacks required capabilities');
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        $tour_id = intval($_POST['tour_id']);
        $position_x = floatval($_POST['position_x']);
        $position_y = floatval($_POST['position_y']);
        $position_z = floatval($_POST['position_z']);
        $title = sanitize_text_field($_POST['title']);
        $content = wp_kses_post($_POST['content']);

        if (empty($title)) {
            wp_send_json_error(['message' => 'Title is required']);
            return;
        }

        $hotspot_data = [
            'post_type' => 'hotspot',
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish'
        ];

        $hotspot_id = wp_insert_post($hotspot_data);

        if (is_wp_error($hotspot_id)) {
            error_log('Error creating hotspot: ' . $hotspot_id->get_error_message());
            wp_send_json_error(['message' => $hotspot_id->get_error_message()]);
            return;
        }

        update_post_meta($hotspot_id, 'position_x', $position_x);
        update_post_meta($hotspot_id, 'position_y', $position_y);
        update_post_meta($hotspot_id, 'position_z', $position_z);
        update_post_meta($hotspot_id, 'parent_tour_id', $tour_id);

        if (!empty($_FILES['featured_image'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('featured_image', $hotspot_id);
            if (is_wp_error($attachment_id)) {
                error_log('Error uploading image: ' . $attachment_id->get_error_message());
            } else {
                set_post_thumbnail($hotspot_id, $attachment_id);
            }
        }

        wp_send_json_success([
            'message' => 'Hotspot saved successfully',
            'hotspot_id' => $hotspot_id
        ]);
    }

    public function get_hotspot_data_ajax() {
        error_log('Received get hotspot request: ' . print_r($_POST, true));

        $hotspot_id = intval($_POST['hotspot_id']);
        if (!$hotspot_id) {
            wp_send_json_error(['message' => 'Invalid hotspot ID']);
            return;
        }

        $hotspot = get_post($hotspot_id);
        if (!$hotspot || $hotspot->post_type !== 'hotspot') {
            wp_send_json_error(['message' => 'Hotspot not found']);
            return;
        }

        $response = [
            'title' => $hotspot->post_title,
            'content' => apply_filters('the_content', $hotspot->post_content),
            'featured_image' => get_the_post_thumbnail_url($hotspot_id, 'full')
        ];

        error_log('Sending hotspot response: ' . print_r($response, true));
        wp_send_json_success($response);
    }

    public function load_tour_template($template) {
        if (is_singular('tour')) {
            $plugin_template = plugin_dir_path(__FILE__) . 'templates/single-tour.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function add_body_classes($classes) {
        if (is_singular('tour')) {
            $classes[] = 'panorama-viewer';
            $classes[] = 'full-screen-view';
        }
        return $classes;
    }

    public function remove_admin_bar() {
        if (is_singular('tour')) {
            add_filter('show_admin_bar', '__return_false');
        }
    }

    public function add_capabilities() {
        $admin = get_role('administrator');
        $admin->add_cap('manage_tours');
        $admin->add_cap('manage_hotspots');
    }

    public function get_tour_hotspots() {
        global $post;
        if (!is_singular('tour')) return;

        $args = array(
            'post_type' => 'hotspot',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'parent_tour_id',
                    'value' => $post->ID,
                    'compare' => '='
                )
            )
        );

        $hotspots = get_posts($args);
        $hotspot_data = array();

        foreach ($hotspots as $hotspot) {
            $hotspot_data[] = array(
                'id' => $hotspot->ID,
                'title' => $hotspot->post_title,
                'position' => array(
                    'x' => floatval(get_post_meta($hotspot->ID, 'position_x', true)),
                    'y' => floatval(get_post_meta($hotspot->ID, 'position_y', true)),
                    'z' => floatval(get_post_meta($hotspot->ID, 'position_z', true))
                )
            );
        }

        wp_localize_script('wp-360-tour', 'tourData', array(
            'hotspots' => $hotspot_data,
            'panoramaUrl' => get_the_post_thumbnail_url($post->ID, 'panorama')
        ));
    }

    public function add_admin_menu() {
        add_menu_page(
            '360° Tour Dashboard',
            '360° Tours',
            'manage_tours',
            'wp-360-tour',
            [$this, 'render_dashboard'],
            'dashicons-format-gallery',
            20
        );
    }

    public function render_dashboard() {
        include(WP_360_TOUR_PLUGIN_DIR . 'templates/admin/dashboard.php');
    }

    public function handle_tour_creation() {
        check_ajax_referer('create_tour_nonce', 'tour_nonce');
        
        if (!current_user_can('manage_tours')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        $title = sanitize_text_field($_POST['tour_title']);
        
        if (empty($title)) {
            wp_send_json_error(['message' => 'Title is required']);
            return;
        }

        $tour_data = [
            'post_type' => 'tour',
            'post_title' => $title,
            'post_status' => 'publish'
        ];

        $tour_id = wp_insert_post($tour_data);

        if (is_wp_error($tour_id)) {
            wp_send_json_error(['message' => $tour_id->get_error_message()]);
            return;
        }

        if (!empty($_FILES['tour_image'])) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('tour_image', $tour_id);
            if (!is_wp_error($attachment_id)) {
                set_post_thumbnail($tour_id, $attachment_id);
            }
        }

        wp_send_json_success(['message' => 'Tour created successfully']);
    }

    public function handle_tour_deletion() {
        check_ajax_referer('delete_tour_nonce', 'nonce');
        
        if (!current_user_can('manage_tours')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }

        $tour_id = intval($_POST['tour_id']);
        $result = wp_delete_post($tour_id, true);

        if ($result) {
            wp_send_json_success(['message' => 'Tour deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Error deleting tour']);
        }
    }

    public function add_tour_dashboard_template($templates) {
        $templates['page-tour-dashboard.php'] = '360° Tour Dashboard';
        return $templates;
    }

    public function load_tour_dashboard_template($template) {
        if (is_page_template('page-tour-dashboard.php')) {
            $template = WP_360_TOUR_PLUGIN_DIR . 'templates/page-tour-dashboard.php';
        }
        return $template;
    }
}

// Initialize the plugin
function wp_360_tour_init() {
    new WP_360_Tour();
}
add_action('plugins_loaded', 'wp_360_tour_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Check WordPress version
    if (version_compare($GLOBALS['wp_version'], WP_360_TOUR_MIN_WP_VERSION, '<')) {
        wp_die('This plugin requires WordPress version ' . WP_360_TOUR_MIN_WP_VERSION . ' or higher.');
    }
    
    // Trigger post type registration
    $tour = new WP_360_Tour();
    $tour->register_post_types();
    
    // Clear permalinks
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clear permalinks
    flush_rewrite_rules();
});
