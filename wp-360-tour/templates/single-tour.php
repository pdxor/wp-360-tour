/* /templates/single-tour.php */
<!DOCTYPE html>
<html>
<head>
    <title><?php wp_title(); ?></title>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="panorama-url" content="<?php echo esc_url($panorama_image_url); ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <?php while (have_posts()) : the_post(); ?>
        <div id="panoramaContainer"></div>
        <?php 
        $panorama_image_url = get_the_post_thumbnail_url(get_the_ID(), 'full');
        if (!$panorama_image_url) {
            $panorama_image_url = plugin_dir_url(__FILE__) . '../assets/img/default-panorama.jpg';
        }
        ?>

        <div id="autoRotateControl">
            <button id="autoRotateButton" title="Toggle Auto Rotate">
                <img src="<?php echo WP_360_TOUR_PLUGIN_URL . 'assets/img/autorotate.png'; ?>" alt="Auto Rotate">
            </button>
        </div>

        <div id="controlPanel" <?php if (!current_user_can('manage_hotspots')) : ?>style="display: none;"<?php endif; ?>>
            <label>
                <input type="checkbox" id="enableHotspotCreation">
                <span>Enable Hotspot Creation</span>
            </label>
        </div>

        <div id="hotspotModal">
            <button type="button" class="modal-close" onclick="closeHotspotModal()">×</button>
            <div id="modalError"></div>
            <h2 id="hotspotTitle"></h2>
            <img id="hotspotImage" alt="Hotspot Image">
            <div id="hotspotText"></div>
        </div>

        <div id="hotspotForm" style="display: none;">
            <div class="form-overlay"></div>
            <form id="saveHotspotForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_hotspot">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('save_hotspot_nonce'); ?>">
                <input type="hidden" name="position_x" id="positionX">
                <input type="hidden" name="position_y" id="positionY">
                <input type="hidden" name="position_z" id="positionZ">
                <input type="hidden" name="tour_id" value="<?php echo get_the_ID(); ?>">
                
                <div class="input-wrapper">
                    <input 
                        type="text" 
                        id="hotspotTitle" 
                        name="title" 
                        placeholder="Hotspot Title" 
                        required 
                        autocomplete="off" 
                        autocorrect="off" 
                        autocapitalize="off"
                    >
                </div>
                
                <div class="input-wrapper">
                    <textarea 
                        id="hotspotContent" 
                        name="content" 
                        placeholder="Hotspot Description" 
                        rows="4"
                    ></textarea>
                </div>
                
                <label class="choose-file-btn">
                    Choose Image
                    <input type="file" id="hotspotImage" name="featured_image" accept="image/*" style="display: none;">
                </label>
                <div class="selected-file"></div>
                
                <button type="submit">Save Hotspot</button>
                <button type="button" class="cancel-button">Cancel</button>
            </form>
        </div>

        <div class="loading-overlay">
            <img src="<?php echo home_url('/wp-content/uploads/2024/12/animate.gif'); ?>" alt="Loading..." class="custom-loader">
        </div>
    <?php endwhile; ?>

    <?php wp_footer(); ?>
    <script>
        // Initialize when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            init();
            
            // Add file selection handler
            document.querySelector('input[type="file"]').addEventListener('change', function(e) {
                const fileName = e.target.files[0]?.name || 'No file selected';
                document.querySelector('.selected-file').textContent = fileName;
            });
        });
    </script>
</body>
</html>