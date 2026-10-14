<?php
/**
 * Per-IP fixed-window rate limiting for public (unauthenticated) REST writes.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Rate_Limit
 */
class PressNative_Rate_Limit {

	/**
	 * Counts a request against a bucket and reports whether it is within the limit.
	 *
	 * @param string $bucket  Short bucket name, e.g. 'track'.
	 * @param int    $limit   Max requests per window.
	 * @param int    $window  Window length in seconds.
	 * @return bool True when the request may proceed.
	 */
	public static function allow( $bucket, $limit, $window ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $ip ) {
			return true;
		}
		$key   = 'pressnative_rl_' . $bucket . '_' . md5( $ip . '|' . (int) floor( time() / $window ) );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Standard 429 error for REST callbacks.
	 *
	 * @return WP_Error
	 */
	public static function error() {
		return new WP_Error( 'pressnative_rate_limited', __( 'Too many requests. Try again shortly.', 'pressnative-apps' ), array( 'status' => 429 ) );
	}
}
