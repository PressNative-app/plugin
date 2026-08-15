<?php
/**
 * Vertical launch kits: publisher, shop, local business presets.
 *
 * Applies branding + layout component sets without forcing a second CMS.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Launch_Kits
 */
class PressNative_Launch_Kits {

	const OPTION_ACTIVE_KIT = 'pressnative_active_launch_kit';

	/**
	 * Kit definitions. Colors + enabled components + hub categories.
	 *
	 * @return array<string, array>
	 */
	public static function get_kits() {
		$kits = array(
			'publisher' => array(
				'label'       => __( 'Publisher', 'pressnative-apps' ),
				'description' => __( 'News & blogs: hero + topic rails + section push friendly layout.', 'pressnative-apps' ),
				'theme_id'    => 'editorial',
				'hub_tags'    => array( 'News', 'Blog' ),
				'primary'     => '#1A73E8',
				'accent'      => '#E53935',
				'background'  => '#FFFFFF',
				'text'        => '#111111',
				'tile_bg'     => '#F6F7F9',
				'tile_text'   => '#111111',
				'border'      => '#E5E7EB',
				'components'  => array( 'nav-menu', 'hero-carousel', 'post-grid', 'category-list', 'block-sponsor' ),
				'hero_max'    => 5,
				'grid_cols'   => 2,
				'grid_per'    => 12,
			),
			'shop'      => array(
				'label'       => __( 'Shop', 'pressnative-apps' ),
				'description' => __( 'WooCommerce: product carousels, grids, and promo-ready chrome.', 'pressnative-apps' ),
				'theme_id'    => 'citrus',
				'hub_tags'    => array( 'Shopping' ),
				'primary'     => '#0F766E',
				'accent'      => '#F59E0B',
				'background'  => '#FFFFFF',
				'text'        => '#111111',
				'tile_bg'     => '#F0FDFA',
				'tile_text'   => '#134E4A',
				'border'      => '#CCFBF1',
				'components'  => array( 'nav-menu', 'hero-carousel', 'product-carousel', 'product-grid', 'product-category-list', 'post-grid' ),
				'hero_max'    => 3,
				'grid_cols'   => 2,
				'grid_per'    => 12,
			),
			'local'     => array(
				'label'       => __( 'Local business', 'pressnative-apps' ),
				'description' => __( 'Simple branded presence: pages, offers, and announcement push.', 'pressnative-apps' ),
				'theme_id'    => 'ocean',
				'hub_tags'    => array( 'Local' ),
				'primary'     => '#0369A1',
				'accent'      => '#34C759',
				'background'  => '#FFFFFF',
				'text'        => '#0F172A',
				'tile_bg'     => '#F0F9FF',
				'tile_text'   => '#0C4A6E',
				'border'      => '#BAE6FD',
				'components'  => array( 'nav-menu', 'hero-carousel', 'page-list', 'post-grid', 'block-sponsor' ),
				'hero_max'    => 3,
				'grid_cols'   => 1,
				'grid_per'    => 8,
			),
			'midnight'  => array(
				'label'       => __( 'Midnight (theme only)', 'pressnative-apps' ),
				'description' => __( 'Dark editorial look. Keeps current layout components.', 'pressnative-apps' ),
				'theme_id'    => 'midnight',
				'hub_tags'    => array(),
				'primary'     => '#8B5CF6',
				'accent'      => '#22D3EE',
				'background'  => '#0F172A',
				'text'        => '#F8FAFC',
				'tile_bg'     => '#1E293B',
				'tile_text'   => '#F1F5F9',
				'border'      => '#334155',
				'components'  => null,
				'hero_max'    => null,
				'grid_cols'   => null,
				'grid_per'    => null,
			),
		);

		/**
		 * Filter launch kits.
		 *
		 * @param array $kits Kit definitions.
		 */
		return apply_filters( 'pressnative_launch_kits', $kits );
	}

	/**
	 * Apply a kit by id.
	 *
	 * @param string $kit_id Kit key.
	 * @return true|WP_Error
	 */
	public static function apply_kit( $kit_id ) {
		$kits = self::get_kits();
		if ( ! isset( $kits[ $kit_id ] ) ) {
			return new WP_Error( 'invalid_kit', __( 'Unknown launch kit.', 'pressnative-apps' ) );
		}
		$kit = $kits[ $kit_id ];

		update_option( PressNative_Options::OPTION_PRIMARY_COLOR, $kit['primary'] );
		update_option( PressNative_Options::OPTION_ACCENT_COLOR, $kit['accent'] );
		update_option( PressNative_Options::OPTION_BACKGROUND_COLOR, $kit['background'] );
		update_option( PressNative_Options::OPTION_TEXT_COLOR, $kit['text'] );
		update_option( PressNative_Options::OPTION_TILE_BACKGROUND_COLOR, $kit['tile_bg'] );
		update_option( PressNative_Options::OPTION_TILE_TEXT_COLOR, $kit['tile_text'] );
		update_option( PressNative_Options::OPTION_BORDER_COLOR, $kit['border'] );
		update_option( PressNative_Options::OPTION_THEME_ID, $kit['theme_id'] );
		update_option( self::OPTION_ACTIVE_KIT, $kit_id );

		if ( ! empty( $kit['hub_tags'] ) ) {
			update_option( PressNative_Options::OPTION_APP_CATEGORIES, $kit['hub_tags'] );
		}

		if ( is_array( $kit['components'] ) ) {
			$enabled = $kit['components'];
			if ( 'shop' === $kit_id && ! class_exists( 'WooCommerce' ) ) {
				$enabled = array_values(
					array_filter(
						$enabled,
						static function ( $id ) {
							return ! in_array( $id, PressNative_Layout_Options::WOOCOMMERCE_COMPONENT_IDS, true );
						}
					)
				);
				if ( ! in_array( 'post-grid', $enabled, true ) ) {
					$enabled[] = 'post-grid';
				}
			}
			update_option( PressNative_Layout_Options::OPTION_ENABLED_COMPONENTS, $enabled );
		}

		if ( null !== $kit['hero_max'] ) {
			update_option( PressNative_Layout_Options::OPTION_HERO_MAX_ITEMS, (int) $kit['hero_max'] );
		}
		if ( null !== $kit['grid_cols'] ) {
			update_option( PressNative_Layout_Options::OPTION_POST_GRID_COLUMNS, (int) $kit['grid_cols'] );
		}
		if ( null !== $kit['grid_per'] ) {
			update_option( PressNative_Layout_Options::OPTION_POST_GRID_PER_PAGE, (int) $kit['grid_per'] );
		}

		PressNative_Options::increment_settings_version();

		return true;
	}
}
