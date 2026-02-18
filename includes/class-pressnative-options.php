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

	const OPTION_APP_NAME          = 'pressnative_app_name';
	const OPTION_PRIMARY_COLOR     = 'pressnative_primary_color';
	const OPTION_ACCENT_COLOR      = 'pressnative_accent_color';
	const OPTION_LOGO_ATTACHMENT   = 'pressnative_logo_attachment_id';
	const OPTION_BACKGROUND_COLOR  = 'pressnative_background_color';
	const OPTION_TEXT_COLOR        = 'pressnative_text_color';
	const OPTION_FONT_FAMILY       = 'pressnative_font_family';
	const OPTION_BASE_FONT_SIZE    = 'pressnative_base_font_size';
	const OPTION_APP_CATEGORIES    = 'pressnative_app_categories';

	// AdMob monetization settings.
	const OPTION_ADMOB_BANNER_UNIT_ID = 'pressnative_admob_banner_unit_id';

	const DEFAULT_APP_NAME         = 'PressNative';
	const DEFAULT_APP_CATEGORIES   = array();
	const APP_CATEGORIES_MAX       = 5;
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
			'app_name'   => (string) get_option( self::OPTION_APP_NAME, self::DEFAULT_APP_NAME ),
			'logo_url'   => $logo_url,
			'app_categories' => self::get_app_categories(),
			'theme'      => array(
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
	 * Returns app categories (tags) for Hub discovery. Max 5.
	 *
	 * @return string[]
	 */
	public static function get_app_categories() {
		$cats = get_option( self::OPTION_APP_CATEGORIES, self::DEFAULT_APP_CATEGORIES );
		if ( ! is_array( $cats ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', $cats ), 'strlen' ) );
	}

	/**
	 * Sanitizes app categories: max 5 tags, trimmed non-empty strings.
	 * Accepts array (from checkboxes) or string (comma-separated from text input).
	 *
	 * @param mixed $value Raw value (array or string).
	 * @return string[]
	 */
	public static function sanitize_app_categories( $value ) {
		$tags = array();
		if ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				$t = is_string( $v ) ? trim( $v ) : '';
				if ( $t !== '' && ! in_array( $t, $tags, true ) ) {
					$tags[] = $t;
					if ( count( $tags ) >= self::APP_CATEGORIES_MAX ) {
						break;
					}
				}
			}
		} elseif ( is_string( $value ) && $value !== '' ) {
			$parts = array_map( 'trim', explode( ',', $value ) );
			foreach ( $parts as $t ) {
				if ( $t !== '' && ! in_array( $t, $tags, true ) ) {
					$tags[] = $t;
					if ( count( $tags ) >= self::APP_CATEGORIES_MAX ) {
						break;
					}
				}
			}
		}
		return $tags;
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

	/**
	 * WCAG 2.1 AA minimum contrast ratio for normal text.
	 */
	const MIN_CONTRAST_RATIO_AA = 4.5;

	/**
	 * Relative luminance of sRGB color (0–1). Used for contrast ratio.
	 *
	 * @param int $r Red 0–255.
	 * @param int $g Green 0–255.
	 * @param int $b Blue 0–255.
	 * @return float
	 */
	public static function relative_luminance( $r, $g, $b ) {
		$rs = $r / 255.0;
		$gs = $g / 255.0;
		$bs = $b / 255.0;
		$rl = function ( $c ) {
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * $rl( $rs ) + 0.7152 * $rl( $gs ) + 0.0722 * $rl( $bs );
	}

	/**
	 * Contrast ratio between two luminance values (1–21).
	 *
	 * @param float $l1 Luminance 1.
	 * @param float $l2 Luminance 2.
	 * @return float
	 */
	public static function contrast_ratio( $l1, $l2 ) {
		$light = max( $l1, $l2 );
		$dark  = min( $l1, $l2 );
		return ( $light + 0.05 ) / ( $dark + 0.05 );
	}

	/**
	 * Parse hex to RGB (0–255). Supports #RGB and #RRGGBB.
	 *
	 * @param string $hex Hex color.
	 * @return array{0: int, 1: int, 2: int}|null
	 */
	public static function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) {
			return null;
		}
		return array(
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * RGB (0–255) to hex #RRGGBB.
	 *
	 * @param int $r Red.
	 * @param int $g Green.
	 * @param int $b Blue.
	 * @return string
	 */
	public static function rgb_to_hex( $r, $g, $b ) {
		$r = max( 0, min( 255, (int) $r ) );
		$g = max( 0, min( 255, (int) $g ) );
		$b = max( 0, min( 255, (int) $b ) );
		return '#' . sprintf( '%02X%02X%02X', $r, $g, $b );
	}

	/**
	 * Adjust a color toward white or black until contrast with background meets WCAG AA.
	 *
	 * @param string $foreground_hex Foreground (e.g. primary or text) hex.
	 * @param string $background_hex Background hex.
	 * @param float  $min_ratio      Minimum contrast ratio (default 4.5).
	 * @return string Adjusted foreground hex.
	 */
	public static function ensure_contrast( $foreground_hex, $background_hex, $min_ratio = self::MIN_CONTRAST_RATIO_AA ) {
		$fg = self::hex_to_rgb( self::sanitize_hex( $foreground_hex ) );
		$bg = self::hex_to_rgb( self::sanitize_hex( $background_hex ) );
		if ( $fg === null || $bg === null ) {
			return self::sanitize_hex( $foreground_hex );
		}
		$l_fg = self::relative_luminance( $fg[0], $fg[1], $fg[2] );
		$l_bg = self::relative_luminance( $bg[0], $bg[1], $bg[2] );
		if ( self::contrast_ratio( $l_fg, $l_bg ) >= $min_ratio ) {
			return self::sanitize_hex( $foreground_hex );
		}
		// Move foreground toward higher contrast: if background is light, darken fg; if dark, lighten fg.
		$step = 12;
		$r    = $fg[0];
		$g    = $fg[1];
		$b    = $fg[2];
		if ( $l_bg > 0.5 ) {
			// Background is light: darken foreground.
			for ( $i = 0; $i < 20; $i++ ) {
				$r = max( 0, $r - $step );
				$g = max( 0, $g - $step );
				$b = max( 0, $b - $step );
				$l_new = self::relative_luminance( $r, $g, $b );
				if ( self::contrast_ratio( $l_new, $l_bg ) >= $min_ratio ) {
					return self::rgb_to_hex( $r, $g, $b );
				}
			}
		} else {
			// Background is dark: lighten foreground.
			for ( $i = 0; $i < 20; $i++ ) {
				$r = min( 255, $r + $step );
				$g = min( 255, $g + $step );
				$b = min( 255, $b + $step );
				$l_new = self::relative_luminance( $r, $g, $b );
				if ( self::contrast_ratio( $l_new, $l_bg ) >= $min_ratio ) {
					return self::rgb_to_hex( $r, $g, $b );
				}
			}
		}
		return self::rgb_to_hex( $r, $g, $b );
	}

	/**
	 * Sanitize primary color and ensure WCAG AA contrast against current background.
	 *
	 * @param string $value Raw primary color.
	 * @return string
	 */
	public static function sanitize_primary_color( $value ) {
		$hex = self::sanitize_hex( $value );
		$bg  = get_option( self::OPTION_BACKGROUND_COLOR, self::DEFAULT_BACKGROUND_COLOR );
		return self::ensure_contrast( $hex, $bg );
	}

	/**
	 * Sanitize text color and ensure WCAG AA contrast against current background.
	 *
	 * @param string $value Raw text color.
	 * @return string
	 */
	public static function sanitize_text_color( $value ) {
		$hex = self::sanitize_hex( $value );
		$bg  = get_option( self::OPTION_BACKGROUND_COLOR, self::DEFAULT_BACKGROUND_COLOR );
		return self::ensure_contrast( $hex, $bg );
	}
}
