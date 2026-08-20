<?php
/**
 * PressNative QR code shortcode for deep links to the app.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_QR
 */
class PressNative_QR {

	const REGISTRY_BASE = 'https://pressnative.app';
	const SHORTCODE_TAG = 'pressnative_qr';

	/**
	 * Initialize the shortcode.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE_TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Get the deep link base URL (filterable).
	 *
	 * @return string
	 */
	public static function get_deep_link_base() {
		return apply_filters( 'pressnative_qr_deep_link_base', self::REGISTRY_BASE );
	}

	/**
	 * Build the deep link URL for the current site (signed preview when API key is configured).
	 *
	 * @return string
	 */
	public static function get_deep_link_url() {
		$site_url = untrailingslashit( home_url() );
		$signed   = self::fetch_signed_preview_url( $site_url );
		if ( $signed ) {
			return $signed;
		}

		$base = rtrim( self::get_deep_link_base(), '/' );
		return $base . '/open?site=' . rawurlencode( $site_url );
	}

	/**
	 * Request a signed sales preview URL from the registry.
	 *
	 * @param string $site_url Site home URL.
	 * @return string|null Preview URL or null on failure.
	 */
	private static function fetch_signed_preview_url( $site_url ) {
		$auth_key = get_option( 'pressnative_auth_key', '' );
		if ( ! is_string( $auth_key ) || '' === trim( $auth_key ) ) {
			return null;
		}

		$registry = PressNative_Admin::get_registry_url();
		$url      = rtrim( $registry, '/' ) . '/api/v1/preview-link';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Content-Type'          => 'application/json',
					'X-PressNative-Api-Key' => trim( $auth_key ),
				),
				'body'    => wp_json_encode(
					array(
						'site_url' => $site_url,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['url'] ) || ! is_string( $data['url'] ) ) {
			return null;
		}

		return $data['url'];
	}

	/**
	 * Render the shortcode output.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Enclosed content (ignored).
	 * @return string
	 */
	public static function render( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'size'  => 200,
				'label' => __( 'Open in App', 'pressnative-apps' ),
			),
			$atts,
			self::SHORTCODE_TAG
		);

		$size  = max( 50, min( 800, absint( $atts['size'] ) ) );
		$label = sanitize_text_field( $atts['label'] );

		$encoder = PRESSNATIVE_PLUGIN_DIR . 'lib/QrEncoder.php';
		if ( ! class_exists( 'QrEncoder', false ) ) {
			if ( ! is_readable( $encoder ) ) {
				return '';
			}
			require_once $encoder;
		}

		$deep_link = self::get_deep_link_url();
		$svg       = QrEncoder::to_svg( $deep_link, 'qrm' );

		// Add width/height so SVG scales to desired size.
		$svg = preg_replace( '/<svg /', sprintf( '<svg width="%d" height="%d" ', $size, $size ), $svg, 1 );

		$html = '<div class="pressnative-qr" style="text-align:center;">';
		$html .= '<a href="' . esc_url( $deep_link ) . '" target="_blank" rel="noopener">';
		$html .= $svg;
		$html .= '</a>';
		if ( '' !== $label ) {
			$html .= '<p class="pressnative-qr-label" style="margin:0.5em 0 0;font-size:0.9em;">' . esc_html( $label ) . '</p>';
			$html .= '<p style="margin:0.25em 0 0;font-size:0.8em;color:#666;">Tap QR code or <a href="' . esc_url( $deep_link ) . '" target="_blank" rel="noopener">click here</a> to open in app</p>';
		}
		$html .= '</div>';

		return $html;
	}
}
