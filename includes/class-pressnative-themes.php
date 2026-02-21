<?php
/**
 * Theme Library: predefined themes for the PressNative app.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Themes
 *
 * Award-winning theme presets. Users pick a theme or customize manually.
 */
class PressNative_Themes {

	const OPTION_THEME_ID = 'pressnative_theme_id';

	const THEME_CUSTOM = 'custom';

	/**
	 * All available themes.
	 *
	 * @return array<string, array{ name: string, description: string, theme: array, typography: array }>
	 */
	public static function get_themes() {
		return array(
			'editorial'   => array(
				'name'        => __( 'Editorial', 'pressnative' ),
				'description' => __( 'Classic newsmagazine: serif headlines, refined grays.', 'pressnative' ),
				'theme'       => array(
					'primary_color'    => '#1a1a1a',
					'accent_color'     => '#c41e3a',
					'background_color' => '#fafafa',
					'text_color'       => '#1a1a1a',
					'border_color'     => '#e5e7eb',
				),
				'typography'  => array(
					'font_family'    => 'serif',
					'base_font_size' => 17,
				),
			),
			'midnight'    => array(
				'name'        => __( 'Midnight', 'pressnative' ),
				'description' => __( 'Dark mode: easy on the eyes, premium feel.', 'pressnative' ),
				'theme'       => array(
					'primary_color'    => '#ffffff',
					'accent_color'     => '#4ade80',
					'background_color' => '#0f172a',
					'text_color'       => '#e2e8f0',
					'border_color'     => '#1f2937',
				),
				'typography'  => array(
					'font_family'    => 'sans-serif',
					'base_font_size' => 16,
				),
			),
			'citrus'      => array(
				'name'        => __( 'Citrus', 'pressnative' ),
				'description' => __( 'Bold and energetic: vibrant greens and warm backgrounds.', 'pressnative' ),
				'theme'       => array(
					'primary_color'    => '#166534',
					'accent_color'     => '#22c55e',
					'background_color' => '#fffbeb',
					'text_color'       => '#1c1917',
					'border_color'     => '#e7e5e4',
				),
				'typography'  => array(
					'font_family'    => 'sans-serif',
					'base_font_size' => 16,
				),
			),
			'ocean'       => array(
				'name'        => __( 'Ocean', 'pressnative' ),
				'description' => __( 'Calm and professional: blues and soft grays.', 'pressnative' ),
				'theme'       => array(
					'primary_color'    => '#0c4a6e',
					'accent_color'     => '#0ea5e9',
					'background_color' => '#ffffff',
					'text_color'       => '#334155',
					'border_color'     => '#e2e8f0',
				),
				'typography'  => array(
					'font_family'    => 'sans-serif',
					'base_font_size' => 16,
				),
			),
			'minimal'     => array(
				'name'        => __( 'Minimal', 'pressnative' ),
				'description' => __( 'Clean and spacious: black, white, subtle accent.', 'pressnative' ),
				'theme'       => array(
					'primary_color'    => '#000000',
					'accent_color'     => '#737373',
					'background_color' => '#ffffff',
					'text_color'       => '#171717',
					'border_color'     => '#e5e7eb',
				),
				'typography'  => array(
					'font_family'    => 'sans-serif',
					'base_font_size' => 16,
				),
			),
			'custom'      => array(
				'name'        => __( 'Custom', 'pressnative' ),
				'description' => __( 'Use your own colors and typography below.', 'pressnative' ),
				'theme'       => array(),
				'typography'  => array(),
			),
		);
	}

	/**
	 * Get a single theme by ID.
	 *
	 * @param string $theme_id Theme ID.
	 * @return array|null Theme data or null.
	 */
	public static function get_theme( $theme_id ) {
		$themes = self::get_themes();
		return isset( $themes[ $theme_id ] ) ? $themes[ $theme_id ] : null;
	}

	/**
	 * Get the currently selected theme ID.
	 *
	 * @return string
	 */
	public static function get_selected_theme_id() {
		$id = (string) get_option( self::OPTION_THEME_ID, 'editorial' );
		$themes = self::get_themes();
		return isset( $themes[ $id ] ) ? $id : 'editorial';
	}

	/**
	 * Apply theme values to branding. When a preset is selected, merge its values
	 * with saved options (saved options override for custom; preset fills for others).
	 *
	 * @param array $branding Current branding from options.
	 * @return array Branding with theme applied.
	 */
	public static function apply_theme_to_branding( $branding ) {
		$theme_id = self::get_selected_theme_id();
		if ( $theme_id === self::THEME_CUSTOM ) {
			return $branding;
		}

		$preset = self::get_theme( $theme_id );
		if ( ! $preset || empty( $preset['theme'] ) ) {
			return $branding;
		}

		$branding['theme_id'] = $theme_id;
		$branding['theme']    = array_merge( $branding['theme'], $preset['theme'] );
		if ( ! empty( $preset['typography'] ) ) {
			$branding['typography'] = array_merge( $branding['typography'], $preset['typography'] );
		}
		return $branding;
	}
}
