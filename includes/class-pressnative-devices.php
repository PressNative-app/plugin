<?php
/**
 * FCM device registration and wp_pressnative_devices table.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Devices
 */
class PressNative_Devices {

	const TABLE_NAME = 'pressnative_devices';

	/**
	 * Registers REST route for device registration.
	 *
	 * @return void
	 */
	public static function register_rest_route() {
		register_rest_route(
			'pressnative/v1',
			'/register-device',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_register_device' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'fcm_token'   => array(
						'required'          => true,
						'type'               => 'string',
						'sanitize_callback'  => 'sanitize_text_field',
						'validate_callback'  => function ( $value ) {
							return is_string( $value ) && strlen( $value ) > 0;
						},
					),
					'device_type' => array(
						'required'          => true,
						'type'               => 'string',
						'enum'               => array( 'ios', 'android' ),
						'sanitize_callback'  => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Handles POST /register-device: saves or updates device in wp_pressnative_devices.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_register_device( WP_REST_Request $request ) {
		global $wpdb;

		$table = self::get_table_name(); // $wpdb->prefix + constant, safe for use in query.
		$token = $request->get_param( 'fcm_token' );
		$type  = $request->get_param( 'device_type' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from get_table_name() (prefix + constant)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE fcm_token = %s LIMIT 1",
				$token
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- single row update by primary key
			$updated = $wpdb->update(
				$table,
				array(
					'device_type' => $type,
					'updated_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $existing['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				return new WP_Error(
					'pressnative_update_failed',
					__( 'Failed to update device.', 'pressnative-apps' ),
					array( 'status' => 500 )
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Device updated.', 'pressnative-apps' ),
				),
				200
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- single row insert
		$inserted = $wpdb->insert(
			$table,
			array(
				'fcm_token'   => $token,
				'device_type' => $type,
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error(
				'pressnative_insert_failed',
				__( 'Failed to register device.', 'pressnative-apps' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message'  => __( 'Device registered.', 'pressnative-apps' ),
			),
			201
		);
	}

	/**
	 * Returns the full table name including prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Creates the wp_pressnative_devices table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table      = self::get_table_name();
		$charset    = $wpdb->get_charset_collate();
		$sql        = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fcm_token varchar(255) NOT NULL,
			device_type varchar(20) NOT NULL DEFAULT 'android',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY fcm_token (fcm_token)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
