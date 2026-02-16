<?php
/**
 * Plugin Name: PressNative
 * Plugin URI: https://pressnative.app
 * Description: Data provider for the PressNative mobile app. Serves layout and content via REST API.
 * Version: 1.0.0
 * Author: PressNative
 * License: GPL v2 or later
 * Text Domain: pressnative
 */

defined( 'ABSPATH' ) || exit;

define( 'PRESSNATIVE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-options.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-themes.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-layout-options.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-layout.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-shortcodes.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-search-api.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-devices.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-admin.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-registry-notify.php';

/**
 * Activation: create devices table and verify Registry schema.
 */
register_activation_hook( __FILE__, function () {
	PressNative_Devices::create_table();
	PressNative_Admin::verify_registry_schema();
} );

/**
 * REST API: layout/home and register-device.
 */
add_action( 'rest_api_init', function () {
	$layout = new PressNative_Layout();

	register_rest_route(
		'pressnative/v1',
		'/layout/home',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
				$data     = $layout->get_home_layout();
				$response = rest_ensure_response( $data );
				$response->header( 'X-PressNative-Version', '1.0.0' );
				$response->header( 'Last-Updated', gmdate( 'c' ) );
				return $response;
			},
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'pressnative/v1',
		'/layout/post/(?P<id>[\d]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
				$data = $layout->get_post_layout( (int) $request['id'] );
				if ( ! $data ) {
					return new WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
				}
				return rest_ensure_response( $data );
			},
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'pressnative/v1',
		'/layout/page/(?P<slug>[a-zA-Z0-9\-_]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
				$data = $layout->get_page_layout( $request['slug'] );
				if ( ! $data ) {
					return new WP_Error( 'not_found', 'Page not found', array( 'status' => 404 ) );
				}
				return rest_ensure_response( $data );
			},
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'pressnative/v1',
		'/layout/category/(?P<id>[\d]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
				$data = $layout->get_category_layout( (int) $request['id'] );
				if ( ! $data ) {
					return new WP_Error( 'not_found', 'Category not found', array( 'status' => 404 ) );
				}
				return rest_ensure_response( $data );
			},
			'permission_callback' => '__return_true',
		)
	);

	PressNative_Devices::register_rest_route();
	PressNative_Search_Api::register_routes();
} );

/**
 * Admin: PressNative menu and Registry URL setting.
 */
PressNative_Admin::init();

/**
 * Notify Registry when branding/layout options are saved (invalidates site branding cache).
 */
PressNative_Registry_Notify::init();
