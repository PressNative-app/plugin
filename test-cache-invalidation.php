<?php
/**
 * Test script to verify cache invalidation is working for WooCommerce settings.
 * 
 * Run this from WordPress admin or via WP-CLI:
 * wp eval-file wp-content/plugins/pressnative-app/test-cache-invalidation.php
 */

// Ensure WordPress is loaded
if (!defined('ABSPATH')) {
    die('This script must be run from within WordPress');
}

echo "Testing PressNative Cache Invalidation System\n";
echo "============================================\n\n";

// Test 1: Check if the registry notify class is initialized
echo "1. Checking if PressNative_Registry_Notify is initialized...\n";
if (class_exists('PressNative_Registry_Notify')) {
    echo "   ✅ PressNative_Registry_Notify class exists\n";
} else {
    echo "   ❌ PressNative_Registry_Notify class not found\n";
    return;
}

// Test 2: Check if the WooCommerce settings are in tracked options
echo "\n2. Checking if WooCommerce settings are tracked...\n";
$tracked_options = PressNative_Registry_Notify::TRACKED_OPTIONS;
$wc_options = ['pressnative_product_in_post_style', 'pressnative_product_grid_style'];

foreach ($wc_options as $option) {
    if (in_array($option, $tracked_options)) {
        echo "   ✅ {$option} is tracked\n";
    } else {
        echo "   ❌ {$option} is NOT tracked\n";
    }
}

// Test 3: Check current settings values
echo "\n3. Current WooCommerce settings values...\n";
foreach ($wc_options as $option) {
    $value = get_option($option, 'NOT SET');
    echo "   {$option}: {$value}\n";
}

// Test 4: Check current settings version
echo "\n4. Current settings version...\n";
$current_version = PressNative_Options::get_settings_version();
echo "   Settings version: {$current_version}\n";

// Test 5: Simulate a settings update
echo "\n5. Testing settings update simulation...\n";
$test_option = 'pressnative_product_in_post_style';
$old_value = get_option($test_option, 'compact_row');
$new_value = ($old_value === 'compact_row') ? 'card' : 'compact_row';

echo "   Simulating update of {$test_option}: {$old_value} -> {$new_value}\n";

// Temporarily enable error logging to capture our debug messages
$original_log_errors = ini_get('log_errors');
ini_set('log_errors', 1);

// Update the option (this should trigger the cache invalidation)
update_option($test_option, $new_value);

// Check if settings version was incremented
$new_version = PressNative_Options::get_settings_version();
if ($new_version > $current_version) {
    echo "   ✅ Settings version incremented: {$current_version} -> {$new_version}\n";
} else {
    echo "   ❌ Settings version was NOT incremented\n";
}

// Revert the test change
update_option($test_option, $old_value);

// Restore original error logging setting
ini_set('log_errors', $original_log_errors);

echo "\n6. Test completed!\n";
echo "   Check your WordPress error log for debug messages starting with 'PressNative:'\n";
echo "   If you see those messages, cache invalidation is working correctly.\n\n";

// Test 6: Show the registry URL being used
echo "7. Registry configuration...\n";
if (class_exists('PressNative_Admin')) {
    $registry_url = PressNative_Admin::get_registry_url();
    echo "   Registry URL: {$registry_url}\n";
    $api_key = get_option(PressNative_Admin::OPTION_API_KEY, '');
    echo "   API Key configured: " . (empty($api_key) ? 'NO' : 'YES') . "\n";
} else {
    echo "   ❌ PressNative_Admin class not found\n";
}

echo "\nDone!\n";