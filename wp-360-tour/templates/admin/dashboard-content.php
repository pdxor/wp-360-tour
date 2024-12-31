<div class="tour-dashboard-wrap">
    <h1>360° Tour Dashboard</h1>

    <!-- Create New Tour Form -->
    <div class="postbox">
        <h2 class="hndle"><span>Create New Tour</span></h2>
        <div class="inside">
            <form method="post" enctype="multipart/form-data" id="create-tour-form">
                <?php wp_nonce_field('create_tour_nonce', 'tour_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="tour-title">Tour Title</label></th>
                        <td>
                            <input type="text" id="tour-title" name="tour_title" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tour-image">Panorama Image</label></th>
                        <td>
                            <input type="file" id="tour-image" name="tour_image" accept="image/*" required>
                            <p class="description">Upload a 360° panorama image (recommended size: 4096x2048)</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="Create Tour">
                </p>
            </form>
        </div>
    </div>

    <!-- Tours List -->
    <div class="postbox">
        <h2 class="hndle"><span>Your Tours</span></h2>
        <div class="inside">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Hotspots</th>
                        <th>Preview</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $tours = get_posts([
                        'post_type' => 'tour',
                        'posts_per_page' => -1,
                    ]);

                    if (empty($tours)) {
                        echo '<tr><td colspan="4">No tours found. Create your first tour above!</td></tr>';
                    } else {
                        foreach ($tours as $tour) {
                            // Count hotspots for this specific tour
                            $hotspot_count = get_posts([
                                'post_type' => 'hotspot',
                                'posts_per_page' => -1,
                                'meta_query' => [
                                    [
                                        'key' => 'parent_tour_id',
                                        'value' => $tour->ID,
                                        'compare' => '='
                                    ]
                                ]
                            ]);
                            $hotspot_count = count($hotspot_count);
                            $preview_url = get_permalink($tour->ID);
                            ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($tour->post_title); ?>
                                    <?php if (has_post_thumbnail($tour->ID)): ?>
                                        <div class="tour-thumbnail">
                                            <?php echo get_the_post_thumbnail($tour->ID, [100, 50]); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $hotspot_count; ?> hotspots</td>
                                <td>
                                    <a href="<?php echo esc_url($preview_url); ?>" target="_blank" class="button">Preview</a>
                                </td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($tour->ID); ?>" class="button">Edit</a>
                                    <a href="#" class="button delete-tour" data-id="<?php echo $tour->ID; ?>">Delete</a>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Hotspots List -->
    <div class="postbox">
        <h2 class="hndle"><span>All Hotspots</span></h2>
        <div class="inside">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Tour</th>
                        <th>Content</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $hotspots = get_posts([
                        'post_type' => 'hotspot',
                        'posts_per_page' => -1,
                    ]);

                    if (empty($hotspots)) {
                        echo '<tr><td colspan="4">No hotspots found. Create a tour and add hotspots to it!</td></tr>';
                    } else {
                        foreach ($hotspots as $hotspot) {
                            $tour_id = get_post_meta($hotspot->ID, 'parent_tour_id', true);
                            $tour = get_post($tour_id);
                            ?>
                            <tr>
                                <td><?php echo esc_html($hotspot->post_title); ?></td>
                                <td><?php echo $tour ? esc_html($tour->post_title) : 'N/A'; ?></td>
                                <td><?php echo wp_trim_words($hotspot->post_content, 10); ?></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($hotspot->ID); ?>" class="button">Edit</a>
                                    <a href="#" class="button delete-hotspot" data-id="<?php echo $hotspot->ID; ?>">Delete</a>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.tour-dashboard-wrap {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}
.tour-dashboard-wrap h1 {
    margin-bottom: 20px;
}
.tour-thumbnail {
    margin-top: 5px;
}
.tour-thumbnail img {
    max-width: 100px;
    height: auto;
    border-radius: 3px;
}
.button {
    margin: 0 5px 5px 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle tour creation
    $('#create-tour-form').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('action', 'create_tour');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error creating tour');
                }
            }
        });
    });

    // Handle tour deletion
    $('.delete-tour').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this tour? This will also delete all associated hotspots.')) return;

        var tourId = $(this).data('id');
        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'delete_tour',
            tour_id: tourId,
            nonce: '<?php echo wp_create_nonce("delete_tour_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Error deleting tour');
            }
        });
    });

    // Handle hotspot deletion
    $('.delete-hotspot').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this hotspot?')) return;

        var hotspotId = $(this).data('id');
        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'delete_hotspot',
            hotspot_id: hotspotId,
            nonce: '<?php echo wp_create_nonce("delete_hotspot_nonce"); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Error deleting hotspot');
            }
        });
    });
});
</script> 