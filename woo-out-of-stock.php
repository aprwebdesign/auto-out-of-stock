<?php
/*
Plugin Name: Automatically Out of Stock After Date
Description: Adds a date field and automatic check to set products as out of stock after a specified date in WooCommerce.
Version: 1.0
Author: APR Webdesign
*/

// Add a metabox to the product editing screen
add_action('add_meta_boxes', 'custom_stock_management_add_metabox');
function custom_stock_management_add_metabox() {
    add_meta_box(
        'custom_stock_management_metabox',
        'Automatically Set as Out of Stock?',
        'custom_stock_management_render_metabox',
        'product',
        'side',
        'high'
    );
}

// Render the content of the metabox
function custom_stock_management_render_metabox($post) {
    // Retrieve current values
    $selected_date = get_post_meta($post->ID, '_custom_stock_management_date', true);
    $auto_out_of_stock = get_post_meta($post->ID, '_custom_stock_management_auto_out_of_stock', true);

    // Add nonce for security
    wp_nonce_field('custom_stock_management_save', 'custom_stock_management_nonce');

    // Explanation
    echo '<p>Select the date on which you want this product to be automatically set as "out of stock." The product will be automatically updated <b>on</b> this date.</p><p><b>Don\'t forget to check the checkbox</b> or else this option won\'t work.</p>';

    // Display the date field
    echo '<label for="custom_stock_management_date">Choose a date:</label>';
    echo '<input type="date" id="custom_stock_management_date" name="custom_stock_management_date" value="' . esc_attr($selected_date) . '" /><br />';

    // Display the checkbox for automatically setting as out of stock
    echo '<label for="custom_stock_management_auto_out_of_stock"><input type="checkbox" id="custom_stock_management_auto_out_of_stock" name="custom_stock_management_auto_out_of_stock" value="1" ' . checked(1, $auto_out_of_stock, false) . ' /> Automatically set as out of stock</label>';
}

// Save the metabox data when the product is saved
add_action('save_post', 'custom_stock_management_save_metabox');
function custom_stock_management_save_metabox($post_id) {
    // Check nonce
    if (!isset($_POST['custom_stock_management_nonce']) || !wp_verify_nonce($_POST['custom_stock_management_nonce'], 'custom_stock_management_save')) {
        return $post_id;
    }

    // Check if it's an automatic out of stock checkbox
    $auto_out_of_stock = isset($_POST['custom_stock_management_auto_out_of_stock']) ? 1 : 0;
    update_post_meta($post_id, '_custom_stock_management_auto_out_of_stock', $auto_out_of_stock);

    // Check if a date has been entered
    $selected_date = isset($_POST['custom_stock_management_date']) ? sanitize_text_field($_POST['custom_stock_management_date']) : '';
    update_post_meta($post_id, '_custom_stock_management_date', $selected_date);
}

// Schedule daily check via wp-cron
if (!wp_next_scheduled('custom_stock_management_daily_check')) {
    wp_schedule_event(time(), 'daily', 'custom_stock_management_daily_check');
}
add_action('custom_stock_management_daily_check', 'custom_stock_management_check_stock_status');

// Check stock status based on date and checkbox
function custom_stock_management_check_stock_status() {
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
    );

    $products = new WP_Query($args);

    if ($products->have_posts()) {
        while ($products->have_posts()) {
            $products->the_post();

            $selected_date = get_post_meta(get_the_ID(), '_custom_stock_management_date', true);
            $auto_out_of_stock = get_post_meta(get_the_ID(), '_custom_stock_management_auto_out_of_stock', true);

            // Check if the date is set and the checkbox is checked
            if (!empty($selected_date) && $auto_out_of_stock) {
                $current_date = date('Y-m-d');
                // Set product as out of stock if the date has passed
                if ($current_date >= $selected_date) {
                    wc_update_product_stock_status(get_the_ID(), 'outofstock');
                }
            }
        }
        wp_reset_postdata();
    }
}
?>
