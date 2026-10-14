<?php
/**
 * PressNative Uninstall
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all plugin options and drops the custom database table.
 *
 * @package PressNative
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Every plugin option and transient uses the pressnative_ prefix.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'pressnative_' ) . '%',
		$wpdb->esc_like( '_transient_pressnative_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_pressnative_' ) . '%',
		$wpdb->esc_like( '_site_transient_pressnative_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_pressnative_' ) . '%'
	)
);
// Legacy option (from pressnative-app plugin).
delete_option( 'pressnative_app_enabled_categories' );

// Per-post plugin data (AOT layout cache, editorial push settings). Posts themselves are kept.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_pressnative_' ) . '%'
	)
);

wp_unschedule_hook( 'pressnative_aot_batch' );
wp_unschedule_hook( 'pressnative_send_cart_recovery_push' );

// Drop the devices table.
$pressnative_devices_table = $wpdb->prefix . 'pressnative_devices';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- uninstall: drop table (name from prefix + constant)
$wpdb->query( "DROP TABLE IF EXISTS {$pressnative_devices_table}" );
