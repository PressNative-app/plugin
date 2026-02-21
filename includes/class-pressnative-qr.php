<?php
/**
 * PressNative QR code shortcode for deep links to the app.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

require_once PRESSNATIVE_PLUGIN_DIR . 'lib/QrEncoder.php';

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
	 * Build the deep link URL for the current site.
	 *
	 * @return string
	 */
	public static function get_deep_link_url() {
		$site_url = untrailingslashit( home_url() );
		$base     = rtrim( self::get_deep_link_base(), '/' );
		return $base . '/open?site=' . rawurlencode( $site_url );
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
				'label' => __( 'Open in App', 'pressnative' ),
			),
			$atts,
			self::SHORTCODE_TAG
		);

		$size  = max( 50, min( 800, absint( $atts['size'] ) ) );
		$label = sanitize_text_field( $atts['label'] );

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
