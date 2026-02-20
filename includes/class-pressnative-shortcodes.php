<?php
/**
 * Shortcode registry and native mapping for PressNative SDUI.
 *
 * Plugins can register shortcodes that have native app mappings via the
 * pressnative_native_shortcodes filter. When content contains these shortcodes,
 * they are elevated to ShortcodeBlock components for native rendering.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Shortcodes
 */
class PressNative_Shortcodes {

	/**
	 * Returns shortcode tags that have native mappings.
	 *
	 * @return array
	 */
	public static function get_native_shortcodes() {
		$default = array( 'search_form', 'searchform', 'pressnative_download', 'app_download' );
		return apply_filters( 'pressnative_native_shortcodes', $default );
	}

	/**
	 * Maps a shortcode tag to a native_component hint.
	 *
	 * @param string $tag Shortcode tag.
	 * @return string|null Native component name or null.
	 */
	public static function get_native_component( $tag ) {
		$map = array(
			'search_form'         => 'SearchBar',
			'searchform'          => 'SearchBar',
			'pressnative_download' => 'AppDownload',
			'app_download'        => 'AppDownload',
		);
		$map = apply_filters( 'pressnative_shortcode_native_map', $map );
		return isset( $map[ $tag ] ) ? $map[ $tag ] : null;
	}

	/**
	 * Scans content for registered shortcodes and returns ShortcodeBlock data.
	 *
	 * @param string $content Raw post/page content.
	 * @return array List of shortcode block arrays for JSON.
	 */
	public static function extract_shortcode_blocks( $content ) {
		$blocks   = array();
		$native   = self::get_native_shortcodes();
		$regex    = get_shortcode_regex( $native );

		if ( ! preg_match_all( '/' . $regex . '/s', $content, $matches, PREG_SET_ORDER ) ) {
			return $blocks;
		}

		$index = 0;
		foreach ( $matches as $m ) {
			$tag     = $m[2];
			$attrs   = shortcode_parse_atts( $m[3] ?? '' );
			$attrs   = is_array( $attrs ) ? $attrs : array();
			$inner   = $m[5] ?? '';
			$full    = $m[0];
			$html    = do_shortcode( $full );
			$native_component = self::get_native_component( $tag );

			$blocks[] = array(
				'shortcode'        => $tag,
				'attrs'            => (object) $attrs,
				'html_fallback'    => $html,
				'native_component' => $native_component,
			);
			$index++;
		}

		return $blocks;
	}

	/**
	 * Removes registered shortcodes from content (replaced with empty string).
	 *
	 * @param string $content Raw content.
	 * @return string Content with shortcodes stripped.
	 */
	public static function strip_native_shortcodes( $content ) {
		$native = self::get_native_shortcodes();
		$regex  = get_shortcode_regex( $native );
		return preg_replace( '/' . $regex . '/s', '', $content );
	}
}
