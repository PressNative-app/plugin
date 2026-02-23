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

// Plugin options to remove.
$pressnative_options = array(
	// Registry settings.
	'pressnative_registry_url',
	'pressnative_api_key',
	'pressnative_schema_verified',

	// Branding (App Settings).
	'pressnative_app_name',
	'pressnative_primary_color',
	'pressnative_accent_color',
	'pressnative_logo_attachment_id',
	'pressnative_background_color',
	'pressnative_text_color',
	'pressnative_border_color',
	'pressnative_font_family',
	'pressnative_base_font_size',
	// Layout settings.
	'pressnative_hero_category_slug',
	'pressnative_hero_max_items',
	'pressnative_post_grid_columns',
	'pressnative_post_grid_per_page',
	'pressnative_enabled_categories',
	'pressnative_enabled_components',

	// Legacy option (from pressnative-app plugin).
	'pressnative_app_enabled_categories',
);

foreach ( $pressnative_options as $pressnative_option ) {
	delete_option( $pressnative_option );
}

// Drop the devices table.
global $wpdb;
$pressnative_devices_table = $wpdb->prefix . 'pressnative_devices';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- uninstall: drop table (name from prefix + constant)
$wpdb->query( "DROP TABLE IF EXISTS {$pressnative_devices_table}" );
