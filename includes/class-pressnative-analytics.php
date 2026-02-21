<?php
/**
 * Analytics: forward events to the Registry; dashboard proxies to Registry API.
 * No local storage. API key is issued after subscription and entered in plugin settings.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Analytics
 */
class PressNative_Analytics {

	const EVENT_HOME            = 'home';
	const EVENT_POST            = 'post';
	const EVENT_PAGE            = 'page';
	const EVENT_CATEGORY        = 'category';
	const EVENT_SEARCH          = 'search';
	const EVENT_SHOP            = 'shop';
	const EVENT_PRODUCT         = 'product';
	const EVENT_PRODUCT_CATEGORY = 'product-category';

	const DEVICE_IOS     = 'ios';
	const DEVICE_ANDROID = 'android';
	const DEVICE_UNKNOWN = 'unknown';

	/**
	 * Detects device type from User-Agent string.
	 *
	 * Android emulators and OkHttp (common in Android apps) often omit "android"
	 * from the User-Agent. We detect dalvik, okhttp, and kotlin as Android.
	 *
	 * @param string|null $user_agent User-Agent header.
	 * @return string One of ios, android, unknown.
	 */
	public static function get_device_type_from_user_agent( $user_agent ) {
		if ( empty( $user_agent ) || ! is_string( $user_agent ) ) {
			return self::DEVICE_UNKNOWN;
		}
		$ua = strtolower( $user_agent );
		if ( strpos( $ua, 'iphone' ) !== false || strpos( $ua, 'ipad' ) !== false ) {
			return self::DEVICE_IOS;
		}
		if ( strpos( $ua, 'android' ) !== false ) {
			return self::DEVICE_ANDROID;
		}
		// Android emulator / OkHttp: often sends "okhttp/4.x", "dalvik", or "kotlin" without "android".
		if ( strpos( $ua, 'okhttp' ) !== false || strpos( $ua, 'dalvik' ) !== false || strpos( $ua, 'kotlin' ) !== false ) {
			return self::DEVICE_ANDROID;
		}
		return self::DEVICE_UNKNOWN;
	}

	/**
	 * Forwards an analytics event to the Registry. No local storage.
	 *
	 * @param string      $event_type    One of home, post, page, category, search.
	 * @param string      $resource_id   Post ID, page slug, category ID, or search query.
	 * @param string|null $resource_title Optional display title.
	 * @param string|null $device_type   Optional; if null, derived from User-Agent.
	 * @param string|null $device_id     Optional; links event to push subscriber for engagement filtering.
	 * @return bool True if the Registry accepted the event (or no key configured and we skipped), false on failure.
	 */
	public static function forward_event_to_registry( $event_type, $resource_id = '', $resource_title = null, $device_type = null, $device_id = null ) {
		$valid_types = array( self::EVENT_HOME, self::EVENT_POST, self::EVENT_PAGE, self::EVENT_CATEGORY, self::EVENT_SEARCH, self::EVENT_SHOP, self::EVENT_PRODUCT, self::EVENT_PRODUCT_CATEGORY );
		if ( ! in_array( $event_type, $valid_types, true ) ) {
			return false;
		}

		$api_key = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		$registry_url = get_option( PressNative_Admin::OPTION_REGISTRY_URL, PressNative_Admin::DEFAULT_REGISTRY_URL );
		if ( ! $api_key || ! $registry_url ) {
			return true; // Skip without failing; key may be added later.
		}

		if ( $device_type === null ) {
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
			$device_type = self::get_device_type_from_user_agent( $user_agent );
		}
		$device_type = in_array( $device_type, array( self::DEVICE_IOS, self::DEVICE_ANDROID ), true )
			? $device_type
			: self::DEVICE_UNKNOWN;

		$resource_id    = is_string( $resource_id ) ? substr( sanitize_text_field( $resource_id ), 0, 255 ) : '';
		$resource_title = $resource_title !== null ? substr( sanitize_text_field( $resource_title ), 0, 255 ) : null;
		$device_id      = is_string( $device_id ) && strlen( trim( $device_id ) ) > 0 ? substr( sanitize_text_field( trim( $device_id ) ), 0, 255 ) : null;

		$url = rtrim( $registry_url, '/' ) . '/api/v1/analytics/event';
		$body = array(
			'event_type'     => $event_type,
			'resource_id'    => $resource_id,
			'resource_title' => $resource_title,
			'device_type'    => $device_type,
		);
		if ( $device_id !== null ) {
			$body['device_id'] = $device_id;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'  => 2,
				'blocking' => true,
				'headers'  => array(
					'Content-Type'         => 'application/json',
					'X-PressNative-API-Key' => $api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	/**
	 * Fetches JSON from the Registry analytics API (for dashboard proxy).
	 *
	 * @param string $path   Path and query, e.g. '/api/v1/analytics/summary?days=30'.
	 * @return array|null Decoded JSON or null on failure/missing key.
	 */
	private static function fetch_from_registry( $path ) {
		$api_key = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		$registry_url = get_option( PressNative_Admin::OPTION_REGISTRY_URL, PressNative_Admin::DEFAULT_REGISTRY_URL );
		if ( ! $api_key || ! $registry_url ) {
			return null;
		}
		$url = rtrim( $registry_url, '/' ) . $path;
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
				'headers' => array( 'X-PressNative-API-Key' => $api_key ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Handles POST /track: forwards the view event to the Registry (app sent this for cache-hit views).
	 *
	 * @param WP_REST_Request $request Request with JSON body.
	 * @return WP_REST_Response
	 */
	public static function handle_track( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$event_type     = isset( $params['event_type'] ) ? sanitize_text_field( $params['event_type'] ) : '';
		$resource_id   = isset( $params['resource_id'] ) ? sanitize_text_field( $params['resource_id'] ) : '';
		$resource_title = isset( $params['resource_title'] ) ? sanitize_text_field( $params['resource_title'] ) : null;
		$device_type   = isset( $params['device_type'] ) ? sanitize_text_field( $params['device_type'] ) : null;
		$device_id     = isset( $params['device_id'] ) ? sanitize_text_field( $params['device_id'] ) : null;

		$valid_types = array( self::EVENT_HOME, self::EVENT_POST, self::EVENT_PAGE, self::EVENT_CATEGORY, self::EVENT_SEARCH, self::EVENT_SHOP, self::EVENT_PRODUCT, self::EVENT_PRODUCT_CATEGORY );
		if ( ! in_array( $event_type, $valid_types, true ) ) {
			return new WP_Error( 'invalid_event_type', __( 'Invalid event_type.', 'pressnative' ), array( 'status' => 400 ) );
		}

		$ok = self::forward_event_to_registry( $event_type, $resource_id, $resource_title, $device_type, $device_id );
		return rest_ensure_response( array( 'ok' => $ok ) );
	}

	/**
	 * Registers REST routes: track (forward to Registry) and dashboard (proxy to Registry).
	 *
	 * @return void
	 */
	public static function register_rest_routes() {
		$permission = function () {
			return current_user_can( 'manage_options' );
		};

		// Public: app calls this when displaying content from cache; we forward to Registry.
		register_rest_route(
			'pressnative/v1',
			'/track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_track' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'event_type'     => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( self::EVENT_HOME, self::EVENT_POST, self::EVENT_PAGE, self::EVENT_CATEGORY, self::EVENT_SEARCH, self::EVENT_SHOP, self::EVENT_PRODUCT, self::EVENT_PRODUCT_CATEGORY ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'resource_id'    => array( 'required' => false, 'type' => 'string', 'default' => '' ),
					'resource_title' => array( 'required' => false, 'type' => 'string', 'default' => null ),
					'device_type'    => array(
						'required' => false,
						'type'     => 'string',
						'enum'     => array( self::DEVICE_IOS, self::DEVICE_ANDROID, self::DEVICE_UNKNOWN ),
						'default'  => null,
					),
					'device_id'      => array( 'required' => false, 'type' => 'string', 'default' => null ),
				),
			)
		);

		// Dashboard: proxy to Registry (same response shape so existing JS works).
		register_rest_route(
			'pressnative/v1',
			'/analytics/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$days = (int) $request->get_param( 'days' );
					$days = $days >= 1 && $days <= 365 ? $days : 30;
					$data = self::fetch_from_registry( '/api/v1/analytics/summary?days=' . $days );
					return rest_ensure_response( $data !== null ? $data : array( 'total' => 0, 'by_type' => array( 'home' => 0, 'post' => 0, 'page' => 0, 'category' => 0, 'search' => 0 ), 'favorites' => 0, 'push_received' => 0, 'push_clicked' => 0 ) );
				},
				'permission_callback' => $permission,
				'args'               => array( 'days' => array( 'default' => 30, 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ) ),
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/analytics/top-posts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$days  = (int) $request->get_param( 'days' );
					$days  = $days >= 1 && $days <= 365 ? $days : 30;
					$limit = (int) $request->get_param( 'limit' );
					$limit = $limit >= 1 && $limit <= 100 ? $limit : 10;
					$data = self::fetch_from_registry( '/api/v1/analytics/top-posts?days=' . $days . '&limit=' . $limit );
					return rest_ensure_response( is_array( $data ) ? $data : array() );
				},
				'permission_callback' => $permission,
				'args'               => array(
					'days'  => array( 'default' => 30, 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ),
					'limit' => array( 'default' => 10, 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
				),
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/analytics/top-pages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$days  = (int) $request->get_param( 'days' );
					$days  = $days >= 1 && $days <= 365 ? $days : 30;
					$limit = (int) $request->get_param( 'limit' );
					$limit = $limit >= 1 && $limit <= 100 ? $limit : 10;
					$data = self::fetch_from_registry( '/api/v1/analytics/top-pages?days=' . $days . '&limit=' . $limit );
					return rest_ensure_response( is_array( $data ) ? $data : array() );
				},
				'permission_callback' => $permission,
				'args'               => array(
					'days'  => array( 'default' => 30, 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ),
					'limit' => array( 'default' => 10, 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
				),
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/analytics/top-categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$days  = (int) $request->get_param( 'days' );
					$days  = $days >= 1 && $days <= 365 ? $days : 30;
					$limit = (int) $request->get_param( 'limit' );
					$limit = $limit >= 1 && $limit <= 100 ? $limit : 10;
					$data = self::fetch_from_registry( '/api/v1/analytics/top-categories?days=' . $days . '&limit=' . $limit );
					return rest_ensure_response( is_array( $data ) ? $data : array() );
				},
				'permission_callback' => $permission,
				'args'               => array(
					'days'  => array( 'default' => 30, 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ),
					'limit' => array( 'default' => 10, 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
				),
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/analytics/views-over-time',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$days     = (int) $request->get_param( 'days' );
					$days     = $days >= 1 && $days <= 365 ? $days : 30;
					$group_by = $request->get_param( 'group_by' ) === 'week' ? 'week' : 'day';
					$data = self::fetch_from_registry( '/api/v1/analytics/views-over-time?days=' . $days . '&group_by=' . $group_by );
					return rest_ensure_response( is_array( $data ) ? $data : array() );
				},
				'permission_callback' => $permission,
				'args'               => array(
					'days'     => array( 'default' => 30, 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ),
					'group_by' => array( 'default' => 'day', 'enum' => array( 'day', 'week' ) ),
				),
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/analytics/device-breakdown',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$days = (int) $request->get_param( 'days' );
					$days = $days >= 1 && $days <= 365 ? $days : 30;
					$data = self::fetch_from_registry( '/api/v1/analytics/device-breakdown?days=' . $days );
					return rest_ensure_response( is_array( $data ) ? $data : array( 'ios' => 0, 'android' => 0, 'unknown' => 0 ) );
				},
				'permission_callback' => $permission,
				'args'               => array( 'days' => array( 'default' => 30, 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ) ),
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/analytics/top-searches',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$days  = (int) $request->get_param( 'days' );
					$days  = $days >= 1 && $days <= 365 ? $days : 30;
					$limit = (int) $request->get_param( 'limit' );
					$limit = $limit >= 1 && $limit <= 100 ? $limit : 10;
					$data = self::fetch_from_registry( '/api/v1/analytics/top-searches?days=' . $days . '&limit=' . $limit );
					return rest_ensure_response( is_array( $data ) ? $data : array() );
				},
				'permission_callback' => $permission,
				'args'               => array(
					'days'  => array( 'default' => 30, 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ),
					'limit' => array( 'default' => 10, 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
				),
			)
		);
	}
}
