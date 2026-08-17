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

	const EVENT_HOME              = 'home';
	const EVENT_POST              = 'post';
	const EVENT_PAGE              = 'page';
	const EVENT_CATEGORY          = 'category';
	const EVENT_SEARCH            = 'search';
	const EVENT_SHOP              = 'shop';
	const EVENT_PRODUCT           = 'product';
	const EVENT_PRODUCT_CATEGORY  = 'product_category';
	const EVENT_DOCUMENTATION     = 'documentation';
	const EVENT_POST_ENGAGEMENT   = 'post_engagement';
	const EVENT_ADD_TO_CART       = 'add_to_cart';
	const EVENT_PURCHASE_COMPLETE = 'purchase_complete';
	const EVENT_SPONSOR_IMPRESSION = 'sponsor_impression';
	const EVENT_SPONSOR_CLICK      = 'sponsor_click';
	const EVENT_AD_REQUEST         = 'ad_request';
	const EVENT_AD_LOAD            = 'ad_load';
	const EVENT_AD_IMPRESSION      = 'ad_impression';
	const EVENT_AD_CLICK           = 'ad_click';
	const EVENT_AD_NO_FILL         = 'ad_no_fill';

	const DEVICE_IOS     = 'ios';
	const DEVICE_ANDROID = 'android';
	const DEVICE_UNKNOWN = 'unknown';

	/**
	 * Contract-aligned content/commerce/engagement/monetization event types accepted by /track and forwarder.
	 *
	 * @return string[]
	 */
	public static function get_valid_event_types() {
		return array(
			self::EVENT_HOME,
			self::EVENT_POST,
			self::EVENT_PAGE,
			self::EVENT_CATEGORY,
			self::EVENT_SEARCH,
			self::EVENT_SHOP,
			self::EVENT_PRODUCT,
			self::EVENT_PRODUCT_CATEGORY,
			self::EVENT_DOCUMENTATION,
			self::EVENT_POST_ENGAGEMENT,
			self::EVENT_ADD_TO_CART,
			self::EVENT_PURCHASE_COMPLETE,
			self::EVENT_SPONSOR_IMPRESSION,
			self::EVENT_SPONSOR_CLICK,
			self::EVENT_AD_REQUEST,
			self::EVENT_AD_LOAD,
			self::EVENT_AD_IMPRESSION,
			self::EVENT_AD_CLICK,
			self::EVENT_AD_NO_FILL,
		);
	}

	/**
	 * Normalize legacy hyphenated product-category to snake_case.
	 *
	 * @param string $event_type Raw event type.
	 * @return string
	 */
	public static function normalize_event_type( $event_type ) {
		$event_type = is_string( $event_type ) ? sanitize_text_field( $event_type ) : '';
		if ( $event_type === 'product-category' ) {
			return self::EVENT_PRODUCT_CATEGORY;
		}
		return $event_type;
	}

	/**
	 * Sanitize optional metadata object for Registry.
	 *
	 * @param mixed $metadata Raw metadata.
	 * @return array
	 */
	public static function sanitize_metadata( $metadata ) {
		if ( ! is_array( $metadata ) ) {
			return array();
		}
		$out = array();
		if ( isset( $metadata['scroll_depth_pct'] ) && is_numeric( $metadata['scroll_depth_pct'] ) ) {
			$out['scroll_depth_pct'] = max( 0, min( 100, (int) round( (float) $metadata['scroll_depth_pct'] ) ) );
		}
		if ( isset( $metadata['dwell_seconds'] ) && is_numeric( $metadata['dwell_seconds'] ) ) {
			$out['dwell_seconds'] = max( 0, (int) round( (float) $metadata['dwell_seconds'] ) );
		}
		if ( array_key_exists( 'read_complete', $metadata ) ) {
			$out['read_complete'] = (bool) $metadata['read_complete'];
		}
		if ( isset( $metadata['quantity'] ) && is_numeric( $metadata['quantity'] ) ) {
			$out['quantity'] = max( 0, (int) round( (float) $metadata['quantity'] ) );
		}
		if ( isset( $metadata['value_cents'] ) && is_numeric( $metadata['value_cents'] ) ) {
			$out['value_cents'] = max( 0, (int) round( (float) $metadata['value_cents'] ) );
		}
		if ( isset( $metadata['item_count'] ) && is_numeric( $metadata['item_count'] ) ) {
			$out['item_count'] = max( 0, (int) round( (float) $metadata['item_count'] ) );
		}
		if ( ! empty( $metadata['push_campaign_id'] ) && is_string( $metadata['push_campaign_id'] ) ) {
			$out['push_campaign_id'] = substr( sanitize_text_field( $metadata['push_campaign_id'] ), 0, 255 );
		}
		if ( ! empty( $metadata['placement_id'] ) && is_string( $metadata['placement_id'] ) ) {
			$out['placement_id'] = substr( sanitize_text_field( $metadata['placement_id'] ), 0, 255 );
		}
		if ( ! empty( $metadata['provider'] ) && is_string( $metadata['provider'] ) ) {
			$out['provider'] = substr( sanitize_text_field( $metadata['provider'] ), 0, 64 );
		}
		if ( ! empty( $metadata['ad_format'] ) && is_string( $metadata['ad_format'] ) ) {
			$out['ad_format'] = substr( sanitize_text_field( $metadata['ad_format'] ), 0, 64 );
		}
		if ( ! empty( $metadata['sponsor_name'] ) && is_string( $metadata['sponsor_name'] ) ) {
			$out['sponsor_name'] = substr( sanitize_text_field( $metadata['sponsor_name'] ), 0, 255 );
		}
		return $out;
	}

	/**
	 * Detects device type from User-Agent string.
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
		if ( strpos( $ua, 'okhttp' ) !== false || strpos( $ua, 'dalvik' ) !== false || strpos( $ua, 'kotlin' ) !== false ) {
			return self::DEVICE_ANDROID;
		}
		return self::DEVICE_UNKNOWN;
	}

	/**
	 * Forwards an analytics event to the Registry. No local storage.
	 *
	 * @param string      $event_type     Event type from contract.
	 * @param string      $resource_id    Resource id.
	 * @param string|null $resource_title Optional display title.
	 * @param string|null $device_type    Optional; if null, derived from User-Agent.
	 * @param string|null $device_id      Optional device id.
	 * @param array|null  $metadata       Optional event metadata.
	 * @return bool
	 */
	public static function forward_event_to_registry( $event_type, $resource_id = '', $resource_title = null, $device_type = null, $device_id = null, $metadata = null ) {
		$event_type  = self::normalize_event_type( $event_type );
		$valid_types = self::get_valid_event_types();
		if ( ! in_array( $event_type, $valid_types, true ) ) {
			return false;
		}

		$api_key      = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		$registry_url = get_option( PressNative_Admin::OPTION_REGISTRY_URL, PressNative_Admin::DEFAULT_REGISTRY_URL );
		if ( ! $api_key || ! $registry_url ) {
			return true;
		}

		if ( $device_type === null ) {
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
				: '';
			$device_type = self::get_device_type_from_user_agent( $user_agent );
		}
		$device_type = in_array( $device_type, array( self::DEVICE_IOS, self::DEVICE_ANDROID ), true )
			? $device_type
			: self::DEVICE_UNKNOWN;

		$resource_id    = is_string( $resource_id ) ? substr( sanitize_text_field( $resource_id ), 0, 255 ) : '';
		$resource_title = $resource_title !== null ? substr( sanitize_text_field( $resource_title ), 0, 255 ) : null;
		$device_id      = is_string( $device_id ) && strlen( trim( $device_id ) ) > 0 ? substr( sanitize_text_field( trim( $device_id ) ), 0, 255 ) : null;
		$meta           = self::sanitize_metadata( $metadata );

		$url  = rtrim( $registry_url, '/' ) . '/api/v1/analytics/event';
		$body = array(
			'event_type'     => $event_type,
			'resource_id'    => $resource_id,
			'resource_title' => $resource_title,
			'device_type'    => $device_type,
			'metadata'       => $meta,
		);
		if ( $device_id !== null ) {
			$body['device_id'] = $device_id;
		}

		wp_remote_post(
			$url,
			array(
				'timeout'  => 2,
				'blocking' => false,
				'headers'  => array(
					'Content-Type'          => 'application/json',
					'X-PressNative-API-Key' => $api_key,
				),
				'body'     => wp_json_encode( $body ),
			)
		);

		return true;
	}

	/**
	 * Fetches JSON from the Registry analytics API (for dashboard proxy).
	 *
	 * @param string $path Path and query.
	 * @return array|null
	 */
	private static function fetch_from_registry( $path ) {
		$api_key      = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		$registry_url = get_option( PressNative_Admin::OPTION_REGISTRY_URL, PressNative_Admin::DEFAULT_REGISTRY_URL );
		if ( ! $api_key || ! $registry_url ) {
			return null;
		}
		$url      = rtrim( $registry_url, '/' ) . $path;
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
	 * Handles POST /track: forwards the view event to the Registry.
	 *
	 * @param WP_REST_Request $request Request with JSON body.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_track( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$event_type     = isset( $params['event_type'] ) ? self::normalize_event_type( $params['event_type'] ) : '';
		$resource_id    = isset( $params['resource_id'] ) ? sanitize_text_field( $params['resource_id'] ) : '';
		$resource_title = isset( $params['resource_title'] ) ? sanitize_text_field( $params['resource_title'] ) : null;
		$device_type    = isset( $params['device_type'] ) ? sanitize_text_field( $params['device_type'] ) : null;
		$device_id      = isset( $params['device_id'] ) ? sanitize_text_field( $params['device_id'] ) : null;
		$metadata       = isset( $params['metadata'] ) ? $params['metadata'] : null;

		$valid_types = self::get_valid_event_types();
		if ( ! in_array( $event_type, $valid_types, true ) ) {
			return new WP_Error( 'invalid_event_type', __( 'Invalid event_type.', 'pressnative-apps' ), array( 'status' => 400 ) );
		}

		$ok = self::forward_event_to_registry( $event_type, $resource_id, $resource_title, $device_type, $device_id, $metadata );
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

		$valid_types = self::get_valid_event_types();
		// Also accept legacy hyphenated product-category in REST enum.
		$track_enum = array_values( array_unique( array_merge( $valid_types, array( 'product-category' ) ) ) );

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
						'enum'              => $track_enum,
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
					'metadata'       => array( 'required' => false, 'type' => 'object', 'default' => null ),
				),
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/analytics/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$days = (int) $request->get_param( 'days' );
					$days = $days >= 1 && $days <= 365 ? $days : 30;
					$data = self::fetch_from_registry( '/api/v1/analytics/summary?days=' . $days );
					return rest_ensure_response(
						$data !== null
							? $data
							: array(
								'total'         => 0,
								'by_type'       => array(
									'home'             => 0,
									'post'             => 0,
									'page'             => 0,
									'category'         => 0,
									'search'           => 0,
									'shop'             => 0,
									'product'          => 0,
									'product_category' => 0,
								),
								'favorites'     => 0,
								'push_received' => 0,
								'push_clicked'  => 0,
							)
					);
				},
				'permission_callback' => $permission,
				'args'                => array( 'days' => array( 'default' => 30, 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ) ),
			)
		);

		$proxy_routes = array(
			'/analytics/top-posts'        => array(),
			'/analytics/top-pages'        => array(),
			'/analytics/top-categories'   => array(),
			'/analytics/top-searches'     => array(),
			'/analytics/views-over-time'  => array( 'group_by' => true ),
			'/analytics/device-breakdown' => array( 'default' => array( 'ios' => 0, 'android' => 0, 'unknown' => 0 ) ),
			'/analytics/engagement'       => array( 'default' => array( 'events' => 0, 'top_posts' => array() ) ),
			'/analytics/commerce'         => array( 'default' => array( 'product_views' => 0, 'purchases' => 0, 'top_products' => array() ) ),
			'/analytics/funnel'           => array( 'default' => array( 'push_received' => 0, 'push_clicked' => 0 ) ),
		);

		foreach ( $proxy_routes as $route => $opts ) {
			register_rest_route(
				'pressnative/v1',
				$route,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function ( WP_REST_Request $request ) use ( $route, $opts ) {
						$days = (int) $request->get_param( 'days' );
						$days = $days >= 1 && $days <= 365 ? $days : 30;
						$path = '/api/v1' . $route . '?days=' . $days;
						if ( ! empty( $opts['group_by'] ) ) {
							$group_by = $request->get_param( 'group_by' ) === 'week' ? 'week' : 'day';
							$path    .= '&group_by=' . $group_by;
						}
						if ( in_array( $route, array( '/analytics/top-posts', '/analytics/top-pages', '/analytics/top-categories', '/analytics/top-searches' ), true ) ) {
							$limit = (int) $request->get_param( 'limit' );
							$limit = $limit >= 1 && $limit <= 100 ? $limit : 10;
							$path .= '&limit=' . $limit;
						}
						$data    = self::fetch_from_registry( $path );
						$default = isset( $opts['default'] ) ? $opts['default'] : array();
						return rest_ensure_response( is_array( $data ) ? $data : $default );
					},
					'permission_callback' => $permission,
					'args'                => array(
						'days'     => array( 'default' => 30, 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ),
						'limit'    => array( 'default' => 10, 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
						'group_by' => array( 'default' => 'day', 'enum' => array( 'day', 'week' ) ),
					),
				)
			);
		}
	}
}
