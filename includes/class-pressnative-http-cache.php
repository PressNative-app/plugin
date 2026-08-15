<?php
/**
 * HTTP cache helpers for layout REST responses (ETag, Last-Modified, 304).
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Http_Cache
 */
class PressNative_Http_Cache {

	/**
	 * Apply ETag / Last-Modified / Cache-Control headers.
	 * Returns 304 with empty body when If-None-Match matches the computed ETag.
	 *
	 * @param WP_REST_Request  $request           Incoming request (for If-None-Match).
	 * @param WP_REST_Response $response          Response to mutate.
	 * @param array            $data              Payload used for the content hash.
	 * @param string|null      $last_modified_gmt Optional MySQL GMT datetime (e.g. post_modified_gmt).
	 * @return WP_REST_Response
	 */
	public static function apply( WP_REST_Request $request, WP_REST_Response $response, array $data, $last_modified_gmt = null ) {
		$settings_version = PressNative_Options::get_settings_version();
		$json             = wp_json_encode( $data );
		if ( ! is_string( $json ) ) {
			$json = '';
		}
		$etag = '"' . md5( $json . '|' . (string) $settings_version ) . '"';

		$timestamp = self::resolve_timestamp( $last_modified_gmt, $settings_version );
		$last_modified_http = gmdate( 'D, d M Y H:i:s', $timestamp ) . ' GMT';
		$last_updated_iso   = gmdate( 'c', $timestamp );

		$response->header( 'ETag', $etag );
		$response->header( 'Last-Modified', $last_modified_http );
		$response->header( 'Last-Updated', $last_updated_iso );
		$response->header( 'Cache-Control', 'private, max-age=60' );

		$if_none_match = $request->get_header( 'if-none-match' );
		if ( is_string( $if_none_match ) && self::etag_matches( $if_none_match, $etag ) ) {
			$response->set_status( 304 );
			$response->set_data( null );
		}

		return $response;
	}

	/**
	 * Resolve a Unix timestamp for Last-Modified / Last-Updated.
	 *
	 * @param string|null $last_modified_gmt MySQL GMT datetime or null.
	 * @param int         $settings_version  Settings version (often a unix timestamp).
	 * @return int
	 */
	private static function resolve_timestamp( $last_modified_gmt, $settings_version ) {
		if ( is_string( $last_modified_gmt ) && $last_modified_gmt !== '' && $last_modified_gmt !== '0000-00-00 00:00:00' ) {
			$ts = strtotime( $last_modified_gmt . ' UTC' );
			if ( $ts !== false ) {
				return (int) $ts;
			}
		}
		if ( $settings_version > 0 ) {
			return (int) $settings_version;
		}
		return time();
	}

	/**
	 * Compare If-None-Match header value to our ETag (handles weak validators and lists).
	 *
	 * @param string $if_none_match Raw header value.
	 * @param string $etag          Quoted ETag we set.
	 * @return bool
	 */
	private static function etag_matches( $if_none_match, $etag ) {
		$if_none_match = trim( $if_none_match );
		if ( $if_none_match === '*' ) {
			return true;
		}
		$candidates = array_map( 'trim', explode( ',', $if_none_match ) );
		$normalized = self::normalize_etag( $etag );
		foreach ( $candidates as $candidate ) {
			if ( self::normalize_etag( $candidate ) === $normalized ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Strip weak marker and surrounding quotes for comparison.
	 *
	 * @param string $etag ETag token.
	 * @return string
	 */
	private static function normalize_etag( $etag ) {
		$etag = trim( $etag );
		if ( stripos( $etag, 'W/' ) === 0 ) {
			$etag = trim( substr( $etag, 2 ) );
		}
		return trim( $etag, " \t\"" );
	}
}
