<?php
/**
 * PressNative App Settings: branding options stored in wp_options.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Options
 *
 * Saves and retrieves app branding (app name, logo, theme colors) via the WordPress Options API.
 */
class PressNative_Options {

	const OPTION_APP_NAME         = 'pressnative_app_name';
	const OPTION_PRIMARY_COLOR    = 'pressnative_primary_color';
	const OPTION_ACCENT_COLOR     = 'pressnative_accent_color';
	const OPTION_LOGO_ATTACHMENT  = 'pressnative_logo_attachment_id';
	const OPTION_BACKGROUND_COLOR = 'pressnative_background_color';
	const OPTION_TEXT_COLOR       = 'pressnative_text_color';
	const OPTION_FONT_FAMILY      = 'pressnative_font_family';
	const OPTION_BASE_FONT_SIZE   = 'pressnative_base_font_size';

	// AdMob monetization settings.
	const OPTION_ADMOB_BANNER_UNIT_ID = 'pressnative_admob_banner_unit_id';

	const DEFAULT_APP_NAME         = 'PressNative';
	const DEFAULT_PRIMARY_COLOR    = '#1A73E8';
	const DEFAULT_ACCENT_COLOR     = '#34C759';
	const DEFAULT_BACKGROUND_COLOR = '#FFFFFF';
	const DEFAULT_TEXT_COLOR       = '#111111';
	const DEFAULT_FONT_FAMILY      = 'sans-serif';
	const DEFAULT_BASE_FONT_SIZE   = 16;
	const DEFAULT_ADMOB_BANNER_UNIT_ID = '';

	/**
	 * Returns the full branding object for the REST API (contract structure).
	 *
	 * @return array
	 */
	public static function get_branding() {
		$logo_url = '';
		$attachment_id = (int) get_option( self::OPTION_LOGO_ATTACHMENT, 0 );
		if ( $attachment_id > 0 ) {
			$logo_url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( ! is_string( $logo_url ) ) {
				$logo_url = '';
			}
		}

		$branding = array(
			'app_name'  => (string) get_option( self::OPTION_APP_NAME, self::DEFAULT_APP_NAME ),
			'logo_url'  => $logo_url,
			'theme'     => array(
				'primary_color'    => self::sanitize_hex( get_option( self::OPTION_PRIMARY_COLOR, self::DEFAULT_PRIMARY_COLOR ) ),
				'accent_color'     => self::sanitize_hex( get_option( self::OPTION_ACCENT_COLOR, self::DEFAULT_ACCENT_COLOR ) ),
				'background_color' => self::sanitize_hex( get_option( self::OPTION_BACKGROUND_COLOR, self::DEFAULT_BACKGROUND_COLOR ) ),
				'text_color'       => self::sanitize_hex( get_option( self::OPTION_TEXT_COLOR, self::DEFAULT_TEXT_COLOR ) ),
			),
			'typography' => array(
				'font_family'     => (string) get_option( self::OPTION_FONT_FAMILY, self::DEFAULT_FONT_FAMILY ),
				'base_font_size'  => (int) get_option( self::OPTION_BASE_FONT_SIZE, self::DEFAULT_BASE_FONT_SIZE ),
			),
		);
		return PressNative_Themes::apply_theme_to_branding( $branding );
	}

	/**
	 * Sanitize a hex color string.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_hex( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value ) ) {
			return $value;
		}
		return self::DEFAULT_PRIMARY_COLOR;
	}
}
