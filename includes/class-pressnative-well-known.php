<?php
/**
 * Serves .well-known app association files and deep-link utilities.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Well_Known
 */
class PressNative_Well_Known {

	const QUERY_VAR = 'pressnative_well_known';

	/**
	 * Bootstraps rewrite rules and template redirect.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ) );
	}

	/**
	 * Register rewrite rules for association files.
	 *
	 * @return void
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule(
			'^\.well-known/apple-app-site-association$',
			'index.php?' . self::QUERY_VAR . '=aasa',
			'top'
		);
		add_rewrite_rule(
			'^\.well-known/assetlinks\.json$',
			'index.php?' . self::QUERY_VAR . '=assetlinks',
			'top'
		);
	}

	/**
	 * Flush rewrite rules (call on activation).
	 *
	 * @return void
	 */
	public static function flush_rewrite_rules() {
		self::add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Register custom query var.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Serve association JSON when rewrite rule matches.
	 *
	 * @return void
	 */
	public static function maybe_serve() {
		$type = get_query_var( self::QUERY_VAR );
		if ( $type === 'aasa' ) {
			self::send_json_response( self::get_aasa(), 'application/json' );
		}
		if ( $type === 'assetlinks' ) {
			self::send_json_response( self::get_assetlinks(), 'application/json' );
		}
	}

	/**
	 * Build Apple App Site Association document.
	 *
	 * @return array
	 */
	public static function get_aasa() {
		$settings = PressNative_Options::get_app_link_settings();
		$team_id  = $settings['team_id'];
		$bundle   = $settings['bundle_id'];

		if ( $team_id === '' || $bundle === '' ) {
			return array(
				'applinks' => array(
					'apps'    => array(),
					'details' => array(),
				),
			);
		}

		return array(
			'applinks' => array(
				'apps'    => array(),
				'details' => array(
					array(
						'appIDs'     => array( $team_id . '.' . $bundle ),
						'components' => array(
							array( '/' => '/*' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Build Android assetlinks.json document.
	 *
	 * @return array
	 */
	public static function get_assetlinks() {
		$settings = PressNative_Options::get_app_link_settings();
		$bundle   = $settings['bundle_id'];
		$sha256   = $settings['sha256_fingerprint'];

		if ( $bundle === '' || $sha256 === '' ) {
			return array();
		}

		return array(
			array(
				'relation' => array( 'delegate_permission/common.handle_all_urls' ),
				'target'   => array(
					'namespace'                => 'android_app',
					'package_name'             => $bundle,
					'sha256_cert_fingerprints' => array( $sha256 ),
				),
			),
		);
	}

	/**
	 * Fetch and validate own well-known association files.
	 *
	 * @return array
	 */
	public static function verify_app_links() {
		$site_url = home_url( '/' );
		$aasa_url = home_url( '/.well-known/apple-app-site-association' );
		$al_url   = home_url( '/.well-known/assetlinks.json' );
		$settings = PressNative_Options::get_app_link_settings();
		$results  = array(
			'aasa'        => array( 'url' => $aasa_url, 'ok' => false, 'errors' => array() ),
			'assetlinks'  => array( 'url' => $al_url, 'ok' => false, 'errors' => array() ),
		);

		$aasa_response = wp_remote_get(
			$aasa_url,
			array(
				'timeout'   => 10,
				'sslverify' => true,
				'headers'   => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $aasa_response ) ) {
			$results['aasa']['errors'][] = $aasa_response->get_error_message();
		} else {
			$code = (int) wp_remote_retrieve_response_code( $aasa_response );
			$body = wp_remote_retrieve_body( $aasa_response );
			if ( $code !== 200 ) {
				$results['aasa']['errors'][] = sprintf( 'HTTP %d', $code );
			} else {
				$data = json_decode( $body, true );
				if ( ! is_array( $data ) ) {
					$results['aasa']['errors'][] = 'Invalid JSON';
				} else {
					$expected_id = $settings['team_id'] . '.' . $settings['bundle_id'];
					$found       = false;
					$details     = $data['applinks']['details'] ?? array();
					if ( is_array( $details ) ) {
						foreach ( $details as $detail ) {
							$app_ids = $detail['appIDs'] ?? array();
							if ( is_array( $app_ids ) && in_array( $expected_id, $app_ids, true ) ) {
								$found = true;
								break;
							}
						}
					}
					if ( $settings['team_id'] === '' || $settings['bundle_id'] === '' ) {
						$results['aasa']['errors'][] = 'team_id and bundle_id must be configured in App Links settings';
					} elseif ( ! $found ) {
						$results['aasa']['errors'][] = 'appID ' . $expected_id . ' not found in AASA';
					} else {
						$results['aasa']['ok'] = true;
					}
				}
			}
		}

		$al_response = wp_remote_get(
			$al_url,
			array(
				'timeout'   => 10,
				'sslverify' => true,
				'headers'   => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $al_response ) ) {
			$results['assetlinks']['errors'][] = $al_response->get_error_message();
		} else {
			$code = (int) wp_remote_retrieve_response_code( $al_response );
			$body = wp_remote_retrieve_body( $al_response );
			if ( $code !== 200 ) {
				$results['assetlinks']['errors'][] = sprintf( 'HTTP %d', $code );
			} else {
				$data = json_decode( $body, true );
				if ( ! is_array( $data ) ) {
					$results['assetlinks']['errors'][] = 'Invalid JSON';
				} else {
					$found = false;
					foreach ( $data as $entry ) {
						$target = $entry['target'] ?? array();
						if (
							( $target['package_name'] ?? '' ) === $settings['bundle_id']
							&& is_array( $target['sha256_cert_fingerprints'] ?? null )
							&& in_array( $settings['sha256_fingerprint'], $target['sha256_cert_fingerprints'], true )
						) {
							$found = true;
							break;
						}
					}
					if ( $settings['bundle_id'] === '' || $settings['sha256_fingerprint'] === '' ) {
						$results['assetlinks']['errors'][] = 'bundle_id and sha256_fingerprint must be configured in App Links settings';
					} elseif ( ! $found ) {
						$results['assetlinks']['errors'][] = 'package_name / sha256 fingerprint not found in assetlinks.json';
					} else {
						$results['assetlinks']['ok'] = true;
					}
				}
			}
		}

		return array(
			'site_url' => $site_url,
			'ok'       => $results['aasa']['ok'] && $results['assetlinks']['ok'],
			'results'  => $results,
		);
	}

	/**
	 * Resolve a site URL to a WordPress content type and ID.
	 *
	 * @param string $url URL to resolve.
	 * @return array|null
	 */
	public static function resolve_url( $url ) {
		$url = esc_url_raw( trim( $url ) );
		if ( $url === '' ) {
			return null;
		}

		$home = untrailingslashit( home_url() );
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			$path = '';
		}

		// Must be same site (or relative path).
		if ( strpos( $url, 'http' ) === 0 ) {
			$url_host = wp_parse_url( $url, PHP_URL_HOST );
			$home_host = wp_parse_url( $home, PHP_URL_HOST );
			if ( $url_host && $home_host && strtolower( $url_host ) !== strtolower( $home_host ) ) {
				return null;
			}
		}

		$post_id = url_to_postid( $url );
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post && $post->post_status === 'publish' ) {
				$type = $post->post_type;
				if ( in_array( $type, array( 'post', 'page', 'product' ), true ) ) {
					return array(
						'type' => $type,
						'id'   => $post_id,
						'slug' => $post->post_name,
						'url'  => get_permalink( $post ),
					);
				}
			}
		}

		// Shop archive.
		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_id' ) ) {
			$shop_id = wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$shop_url = untrailingslashit( get_permalink( $shop_id ) );
				if ( untrailingslashit( $url ) === $shop_url || rtrim( $path, '/' ) === rtrim( (string) wp_parse_url( $shop_url, PHP_URL_PATH ), '/' ) ) {
					return array(
						'type' => 'shop',
						'id'   => $shop_id,
						'slug' => 'shop',
						'url'  => $shop_url,
					);
				}
			}
		}

		return null;
	}

	/**
	 * Register REST routes for store listing, verify, and resolve.
	 *
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'pressnative/v1',
			'/store-listing',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					return rest_ensure_response( PressNative_Options::get_store_listing() );
				},
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/verify-app-links',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function () {
					$result = self::verify_app_links();
					if ( ! empty( $result['ok'] ) ) {
						$settings = PressNative_Options::get_app_link_settings();
						PressNative_Registry_Notify::notify_assoc_verified( $settings['bundle_id'] ?? '' );
					}
					return rest_ensure_response( $result );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/resolve-url',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$params = $request->get_json_params();
					$url    = isset( $params['url'] ) ? (string) $params['url'] : '';
					if ( $url === '' ) {
						return new WP_Error( 'missing_url', 'url is required', array( 'status' => 400 ) );
					}
					$resolved = self::resolve_url( $url );
					if ( $resolved === null ) {
						return new WP_Error( 'not_found', 'URL could not be resolved', array( 'status' => 404 ) );
					}
					return rest_ensure_response( $resolved );
				},
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/app-links/aasa',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					return rest_ensure_response( self::get_aasa() );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'pressnative/v1',
			'/app-links/assetlinks',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					return rest_ensure_response( self::get_assetlinks() );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Output JSON with correct headers and exit.
	 *
	 * @param mixed  $data        Data to encode.
	 * @param string $content_type Content-Type header.
	 * @return void
	 */
	private static function send_json_response( $data, $content_type ) {
		status_header( 200 );
		header( 'Content-Type: ' . $content_type );
		header( 'Cache-Control: public, max-age=3600' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response
		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		exit;
	}
}
