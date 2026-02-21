<?php
/**
 * PressNative Preview: temporary option overrides for live admin preview.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Preview
 *
 * Applies and removes pre_option_* filters so get_home_layout() can be called
 * with unsaved form values for the wp-admin live preview.
 */
class PressNative_Preview {

	/** Map override keys (from JS) to WordPress option names. */
	const OPTION_MAP = array(
		'theme_id'             => 'pressnative_theme_id',
		'app_name'             => 'pressnative_app_name',
		'primary_color'        => 'pressnative_primary_color',
		'accent_color'         => 'pressnative_accent_color',
		'background_color'     => 'pressnative_background_color',
		'background_image_attachment' => 'pressnative_background_image_attachment_id',
		'text_color'           => 'pressnative_text_color',
		'border_color'         => 'pressnative_border_color',
		'tile_background_color' => 'pressnative_tile_background_color',
		'tile_background_attachment' => 'pressnative_tile_background_attachment_id',
		'tile_text_color'      => 'pressnative_tile_text_color',
		'font_family'          => 'pressnative_font_family',
		'base_font_size'       => 'pressnative_base_font_size',
		'logo_attachment'      => 'pressnative_logo_attachment_id',
		'hero_category_slug'   => 'pressnative_hero_category_slug',
		'hero_max_items'       => 'pressnative_hero_max_items',
		'post_grid_columns'    => 'pressnative_post_grid_columns',
		'post_grid_per_page'   => 'pressnative_post_grid_per_page',
		'enabled_categories'   => 'pressnative_enabled_categories',
		'enabled_components'   => 'pressnative_enabled_components',
		'product_in_post_style' => 'pressnative_product_in_post_style',
		'product_grid_style'    => 'pressnative_product_grid_style',
	);

	/**
	 * Applied filter callbacks (option_name => callback) for removal.
	 *
	 * @var array<string, callable>
	 */
	private static $applied = array();

	/**
	 * Apply temporary option overrides via pre_option_* filters.
	 *
	 * @param array<string, mixed> $overrides Key-value overrides (use keys from OPTION_MAP).
	 * @return array<string, callable> Map of option_name => callback, for remove_overrides().
	 */
	public static function apply_overrides( array $overrides ) {
		$callbacks = array();
		foreach ( $overrides as $key => $value ) {
			$option_name = isset( self::OPTION_MAP[ $key ] ) ? self::OPTION_MAP[ $key ] : $key;
			$value       = self::normalize_value( $option_name, $value );
			if ( $value === null ) {
				continue;
			}
			$callback = function () use ( $value ) {
				return $value;
			};
			add_filter( 'pre_option_' . $option_name, $callback, 10, 3 );
			$callbacks[ $option_name ] = $callback;
			self::$applied[ $option_name ] = $callback;
		}
		return $callbacks;
	}

	/**
	 * Remove previously applied override filters.
	 *
	 * @param array<string, callable> $callbacks Return value from apply_overrides().
	 * @return void
	 */
	public static function remove_overrides( array $callbacks ) {
		foreach ( $callbacks as $option_name => $callback ) {
			remove_filter( 'pre_option_' . $option_name, $callback, 10 );
			unset( self::$applied[ $option_name ] );
		}
	}

	/**
	 * Normalize and validate a single override value for the given option.
	 *
	 * @param string $option_name WordPress option name.
	 * @param mixed  $value       Raw value from request.
	 * @return mixed Normalized value, or null to skip this override.
	 */
	private static function normalize_value( $option_name, $value ) {
		if ( $value === null || $value === '' ) {
			return null;
		}

		switch ( $option_name ) {
			case 'pressnative_theme_id':
				$themes = PressNative_Themes::get_themes();
				return isset( $themes[ (string) $value ] ) ? (string) $value : null;
			case 'pressnative_primary_color':
			case 'pressnative_accent_color':
			case 'pressnative_background_color':
			case 'pressnative_text_color':
			case 'pressnative_border_color':
			case 'pressnative_tile_background_color':
			case 'pressnative_tile_text_color':
				return PressNative_Options::sanitize_hex( (string) $value );
			case 'pressnative_font_family':
				$allowed = array( 'sans-serif', 'serif', 'monospace' );
				return in_array( (string) $value, $allowed, true ) ? (string) $value : null;
			case 'pressnative_base_font_size':
				$v = absint( $value );
				return max( 12, min( 24, $v ) );
			case 'pressnative_hero_max_items':
				$v = absint( $value );
				return max( 1, min( 10, $v ) );
			case 'pressnative_post_grid_columns':
				$v = absint( $value );
				return max( 1, min( 4, $v ) );
			case 'pressnative_post_grid_per_page':
				$v = absint( $value );
				return max( 1, min( 50, $v ) );
			case 'pressnative_logo_attachment_id':
			case 'pressnative_background_image_attachment_id':
			case 'pressnative_tile_background_attachment_id':
				return absint( $value );
			case 'pressnative_enabled_categories':
				if ( ! is_array( $value ) ) {
					return array_values( array_filter( array_map( 'absint', (array) $value ) ) );
				}
				return array_values( array_map( 'absint', array_filter( $value ) ) );
			case 'pressnative_enabled_components':
				if ( is_array( $value ) ) {
					$valid = array_values( array_intersect( array_filter( $value ), PressNative_Layout_Options::COMPONENT_IDS ) );
					return empty( $valid ) ? PressNative_Layout_Options::COMPONENT_IDS : $valid;
				}
				$ids = array_map( 'trim', explode( ',', (string) $value ) );
				$valid = array_values( array_intersect( array_filter( $ids ), PressNative_Layout_Options::COMPONENT_IDS ) );
				return empty( $valid ) ? PressNative_Layout_Options::COMPONENT_IDS : $valid;
			case 'pressnative_product_in_post_style':
			case 'pressnative_product_grid_style':
				$allowed = array( 'compact_row', 'mini_card', 'card' );
				$v = (string) $value;
				return in_array( $v, $allowed, true ) ? $v : null;
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
