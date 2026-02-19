<?php
/**
 * Test script for notification preferences functionality.
 * Run this from WordPress admin or wp-cli to verify the implementation.
 */

// Ensure this is run in WordPress context
if (!defined('ABSPATH')) {
    die('This script must be run in WordPress context.');
}

// Include the necessary classes
require_once plugin_dir_path(__FILE__) . 'includes/class-pressnative-options.php';

echo "<h2>Testing PressNative Notification Preferences</h2>\n";

// Test 1: Get default preferences
echo "<h3>Test 1: Default Notification Preferences</h3>\n";
$default_preferences = PressNative_Options::get_notification_preferences();
echo "<pre>" . print_r($default_preferences, true) . "</pre>\n";

// Test 2: Test sanitization
echo "<h3>Test 2: Sanitization Test</h3>\n";
$test_input = array(
    'enabled' => '1',
    'types' => array(
        'new_posts' => array('enabled' => '1'),
        'new_pages' => array('enabled' => '0'),
        'invalid_type' => array('enabled' => '1'), // Should be ignored
    ),
    'categories' => array(
        'all_categories' => '0',
        'selected_categories' => array('1', '2', '3'),
    ),
    'quiet_hours' => array(
        'enabled' => '1',
        'start_time' => '22:00',
        'end_time' => '08:00',
        'timezone' => 'auto',
    ),
);

$sanitized = PressNative_Options::sanitize_notification_preferences($test_input);
echo "<strong>Input:</strong><pre>" . print_r($test_input, true) . "</pre>\n";
echo "<strong>Sanitized:</strong><pre>" . print_r($sanitized, true) . "</pre>\n";

// Test 3: Save and retrieve preferences
echo "<h3>Test 3: Save and Retrieve Test</h3>\n";
$test_preferences = array(
    'enabled' => true,
    'types' => array(
        'new_posts' => array('enabled' => true, 'title' => 'New Posts', 'description' => 'Get notified when new blog posts are published'),
        'new_pages' => array('enabled' => false, 'title' => 'New Pages', 'description' => 'Get notified when new pages are created'),
        'new_products' => array('enabled' => true, 'title' => 'New Products', 'description' => 'Get notified when new products are added to the store'),
        'product_updates' => array('enabled' => false, 'title' => 'Product Updates', 'description' => 'Get notified when existing products are updated'),
        'sales_promotions' => array('enabled' => false, 'title' => 'Sales & Promotions', 'description' => 'Get notified about special offers and discounts'),
        'order_updates' => array('enabled' => true, 'title' => 'Order Updates', 'description' => 'Get notified about your order status changes'),
    ),
    'categories' => array(
        'all_categories' => false,
        'selected_categories' => array(1, 2),
    ),
    'quiet_hours' => array(
        'enabled' => true,
        'start_time' => '23:00',
        'end_time' => '07:00',
        'timezone' => 'auto',
    ),
);

// Save the test preferences
update_option(PressNative_Options::OPTION_NOTIFICATION_PREFERENCES, $test_preferences);
echo "<strong>Saved preferences</strong><br>\n";

// Retrieve and display
$retrieved_preferences = PressNative_Options::get_notification_preferences();
echo "<strong>Retrieved preferences:</strong><pre>" . print_r($retrieved_preferences, true) . "</pre>\n";

// Test 4: Test notification type determination
echo "<h3>Test 4: Notification Type Detection</h3>\n";

// Create a test post
$test_post = new WP_Post((object) array(
    'ID' => 999,
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_title' => 'Test Post',
    'post_content' => 'This is a test post.',
));

echo "<strong>Test post created (simulated)</strong><br>\n";
echo "Post ID: {$test_post->ID}<br>\n";
echo "Post Type: {$test_post->post_type}<br>\n";

// Test 5: Verify contract inclusion
echo "<h3>Test 5: Contract Integration Test</h3>\n";
$branding = PressNative_Options::get_branding();
if (isset($branding['notification_preferences'])) {
    echo "<strong>✓ Notification preferences found in branding response</strong><br>\n";
    echo "<pre>" . print_r($branding['notification_preferences'], true) . "</pre>\n";
} else {
    echo "<strong>✗ Notification preferences NOT found in branding response</strong><br>\n";
}

// Clean up
delete_option(PressNative_Options::OPTION_NOTIFICATION_PREFERENCES);
echo "<br><strong>Test completed. Preferences cleaned up.</strong>\n";
?>