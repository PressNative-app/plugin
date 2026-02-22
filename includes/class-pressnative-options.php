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

	const OPTION_APP_NAME              = 'pressnative_app_name';
	const OPTION_PRIMARY_COLOR         = 'pressnative_primary_color';
	const OPTION_ACCENT_COLOR          = 'pressnative_accent_color';
	const OPTION_LOGO_ATTACHMENT       = 'pressnative_logo_attachment_id';
	const OPTION_BACKGROUND_COLOR      = 'pressnative_background_color';
	const OPTION_BACKGROUND_IMAGE      = 'pressnative_background_image_attachment_id';
	const OPTION_TEXT_COLOR            = 'pressnative_text_color';
	const OPTION_BORDER_COLOR          = 'pressnative_border_color';
	const OPTION_TILE_BACKGROUND_COLOR = 'pressnative_tile_background_color';
	const OPTION_TILE_BACKGROUND       = 'pressnative_tile_background_attachment_id';
	const OPTION_TILE_TEXT_COLOR       = 'pressnative_tile_text_color';
	const OPTION_FONT_FAMILY           = 'pressnative_font_family';
	const OPTION_BASE_FONT_SIZE        = 'pressnative_base_font_size';
	const OPTION_APP_CATEGORIES        = 'pressnative_app_categories';

	// AdMob monetization settings.
	const OPTION_ADMOB_BANNER_UNIT_ID = 'pressnative_admob_banner_unit_id';

	// Push notification preferences.
	const OPTION_NOTIFICATION_PREFERENCES = 'pressnative_notification_preferences';

	// Settings version tracking for cache invalidation.
	const OPTION_SETTINGS_VERSION = 'pressnative_settings_version';

	const DEFAULT_APP_NAME              = 'PressNative';
	const DEFAULT_APP_CATEGORIES        = array();
	const APP_CATEGORIES_MAX            = 5;
	const DEFAULT_PRIMARY_COLOR         = '#1A73E8';
	const DEFAULT_ACCENT_COLOR          = '#34C759';
	const DEFAULT_BACKGROUND_COLOR      = '#FFFFFF';
	const DEFAULT_TEXT_COLOR            = '#111111';
	const DEFAULT_BORDER_COLOR          = '#E5E7EB';
	const DEFAULT_TILE_BACKGROUND_COLOR = '#F6F7F9';
	const DEFAULT_TILE_TEXT_COLOR       = '#111111';
	const DEFAULT_FONT_FAMILY           = 'sans-serif';
	const DEFAULT_BASE_FONT_SIZE        = 16;
	const DEFAULT_ADMOB_BANNER_UNIT_ID  = '';

	const DEFAULT_NOTIFICATION_PREFERENCES = array(
		'enabled' => true,
		'types'   => array(
			'new_posts'        => array( 'enabled' => true, 'title' => 'New Posts', 'description' => 'Get notified when new blog posts are published' ),
			'new_pages'        => array( 'enabled' => true, 'title' => 'New Pages', 'description' => 'Get notified when new pages are created' ),
			'new_products'     => array( 'enabled' => true, 'title' => 'New Products', 'description' => 'Get notified when new products are added to the store' ),
			'product_updates'  => array( 'enabled' => false, 'title' => 'Product Updates', 'description' => 'Get notified when existing products are updated' ),
			'sales_promotions' => array( 'enabled' => false, 'title' => 'Sales & Promotions', 'description' => 'Get notified about special offers and discounts' ),
			'order_updates'    => array( 'enabled' => true, 'title' => 'Order Updates', 'description' => 'Get notified about your order status changes' ),
		),
		'categories' => array(
			'all_categories'      => true,
			'selected_categories' => array(),
		),
		'quiet_hours' => array(
			'enabled'    => false,
			'start_time' => '22:00',
			'end_time'   => '08:00',
			'timezone'   => 'auto',
		),
	);

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

		$background_image_url = '';
		$background_image_id  = (int) get_option( self::OPTION_BACKGROUND_IMAGE, 0 );
		if ( $background_image_id > 0 ) {
			$background_image_url = wp_get_attachment_image_url( $background_image_id, 'full' );
			if ( ! is_string( $background_image_url ) ) {
				$background_image_url = '';
			}
		}

		$tile_bg_url = '';
		$tile_bg_id  = (int) get_option( self::OPTION_TILE_BACKGROUND, 0 );
		if ( $tile_bg_id > 0 ) {
			$tile_bg_url = wp_get_attachment_image_url( $tile_bg_id, 'full' );
			if ( ! is_string( $tile_bg_url ) ) {
				$tile_bg_url = '';
			}
		}

		$branding = array(
			'app_name'   => (string) get_option( self::OPTION_APP_NAME, get_bloginfo( 'name' ) ),
			'logo_url'   => $logo_url,
			'app_categories' => self::get_app_categories(),
			'theme'      => array(
				'primary_color'         => self::sanitize_hex( get_option( self::OPTION_PRIMARY_COLOR, self::DEFAULT_PRIMARY_COLOR ) ),
				'accent_color'          => self::sanitize_hex( get_option( self::OPTION_ACCENT_COLOR, self::DEFAULT_ACCENT_COLOR ) ),
				'background_color'      => self::sanitize_hex( get_option( self::OPTION_BACKGROUND_COLOR, self::DEFAULT_BACKGROUND_COLOR ) ),
				'background_image_url'  => $background_image_url,
				'text_color'            => self::sanitize_hex( get_option( self::OPTION_TEXT_COLOR, self::DEFAULT_TEXT_COLOR ) ),
				'border_color'          => self::sanitize_hex( get_option( self::OPTION_BORDER_COLOR, self::DEFAULT_BORDER_COLOR ) ),
				'tile_background_color' => self::sanitize_hex( get_option( self::OPTION_TILE_BACKGROUND_COLOR, self::DEFAULT_TILE_BACKGROUND_COLOR ) ),
				'tile_background_url'   => $tile_bg_url,
				'tile_text_color'       => self::sanitize_hex( get_option( self::OPTION_TILE_TEXT_COLOR, self::DEFAULT_TILE_TEXT_COLOR ) ),
			),
			'typography' => array(
				'font_family'     => (string) get_option( self::OPTION_FONT_FAMILY, self::DEFAULT_FONT_FAMILY ),
				'base_font_size'  => (int) get_option( self::OPTION_BASE_FONT_SIZE, self::DEFAULT_BASE_FONT_SIZE ),
			),
			'notification_preferences' => self::get_notification_preferences(),
			'settings_version' => self::get_settings_version(),
		);
		return $branding;
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

	/**
	 * Get notification preferences.
	 *
	 * @return array
	 */
	public static function get_notification_preferences() {
		$preferences = get_option( self::OPTION_NOTIFICATION_PREFERENCES, self::DEFAULT_NOTIFICATION_PREFERENCES );
		
		// Ensure all default keys exist in case of partial data
		return wp_parse_args( $preferences, self::DEFAULT_NOTIFICATION_PREFERENCES );
	}

	/**
	 * Sanitize notification preferences.
	 *
	 * @param array $preferences Raw preferences data.
	 * @return array
	 */
	public static function sanitize_notification_preferences( $preferences ) {
		if ( ! is_array( $preferences ) ) {
			return self::DEFAULT_NOTIFICATION_PREFERENCES;
		}

		$sanitized = array();

		// Sanitize main enabled flag
		$sanitized['enabled'] = ! empty( $preferences['enabled'] );

		// Sanitize notification types
		$sanitized['types'] = array();
		if ( isset( $preferences['types'] ) && is_array( $preferences['types'] ) ) {
			foreach ( self::DEFAULT_NOTIFICATION_PREFERENCES['types'] as $type => $default_config ) {
				$sanitized['types'][ $type ] = array(
					'enabled'     => isset( $preferences['types'][ $type ]['enabled'] ) ? (bool) $preferences['types'][ $type ]['enabled'] : $default_config['enabled'],
					'title'       => $default_config['title'],
					'description' => $default_config['description'],
				);
			}
		} else {
			$sanitized['types'] = self::DEFAULT_NOTIFICATION_PREFERENCES['types'];
		}

		// Sanitize categories
		$sanitized['categories'] = array();
		if ( isset( $preferences['categories'] ) && is_array( $preferences['categories'] ) ) {
			$sanitized['categories']['all_categories'] = ! empty( $preferences['categories']['all_categories'] );
			$sanitized['categories']['selected_categories'] = array();
			
			if ( isset( $preferences['categories']['selected_categories'] ) && is_array( $preferences['categories']['selected_categories'] ) ) {
				foreach ( $preferences['categories']['selected_categories'] as $cat_id ) {
					if ( is_numeric( $cat_id ) ) {
						$sanitized['categories']['selected_categories'][] = (int) $cat_id;
					}
				}
			}
		} else {
			$sanitized['categories'] = self::DEFAULT_NOTIFICATION_PREFERENCES['categories'];
		}

		// Sanitize quiet hours
		$sanitized['quiet_hours'] = array();
		if ( isset( $preferences['quiet_hours'] ) && is_array( $preferences['quiet_hours'] ) ) {
			$sanitized['quiet_hours']['enabled'] = ! empty( $preferences['quiet_hours']['enabled'] );
			
			// Validate time format (HH:MM)
			$start_time = isset( $preferences['quiet_hours']['start_time'] ) ? sanitize_text_field( $preferences['quiet_hours']['start_time'] ) : '22:00';
			$end_time = isset( $preferences['quiet_hours']['end_time'] ) ? sanitize_text_field( $preferences['quiet_hours']['end_time'] ) : '08:00';
			
			$sanitized['quiet_hours']['start_time'] = preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time ) ? $start_time : '22:00';
			$sanitized['quiet_hours']['end_time'] = preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time ) ? $end_time : '08:00';
			
			$timezone = isset( $preferences['quiet_hours']['timezone'] ) ? sanitize_text_field( $preferences['quiet_hours']['timezone'] ) : 'auto';
			$sanitized['quiet_hours']['timezone'] = in_array( $timezone, array( 'auto', 'UTC' ), true ) ? $timezone : 'auto';
		} else {
			$sanitized['quiet_hours'] = self::DEFAULT_NOTIFICATION_PREFERENCES['quiet_hours'];
		}

		return $sanitized;
	}

	/**
	 * Get the current settings version (timestamp of last settings update).
	 *
	 * @return int
	 */
	public static function get_settings_version() {
		return (int) get_option( self::OPTION_SETTINGS_VERSION, 0 );
	}

	/**
	 * Increment the settings version (called when any tracked setting is updated).
	 *
	 * @return int New version number.
	 */
	public static function increment_settings_version() {
		$version = time();
		update_option( self::OPTION_SETTINGS_VERSION, $version );
		return $version;
	}
}
