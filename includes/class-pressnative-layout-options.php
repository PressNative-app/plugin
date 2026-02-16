<?php
/**
 * PressNative Layout Settings: home screen component configuration.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Layout_Options
 *
 * Options for Hero Carousel, Post Grid, Category List, and component visibility.
 */
class PressNative_Layout_Options {

	const OPTION_HERO_CATEGORY_SLUG = 'pressnative_hero_category_slug';
	const OPTION_HERO_MAX_ITEMS     = 'pressnative_hero_max_items';
	const OPTION_POST_GRID_COLUMNS  = 'pressnative_post_grid_columns';
	const OPTION_POST_GRID_PER_PAGE = 'pressnative_post_grid_per_page';
	const OPTION_ENABLED_CATEGORIES = 'pressnative_enabled_categories';
	const OPTION_ENABLED_COMPONENTS = 'pressnative_enabled_components';

	const DEFAULT_HERO_CATEGORY_SLUG = 'featured';
	const DEFAULT_HERO_MAX_ITEMS     = 3;
	const DEFAULT_POST_GRID_COLUMNS  = 2;
	const DEFAULT_POST_GRID_PER_PAGE = 10;

	/** Component IDs in default order. */
	const COMPONENT_IDS = array( 'hero-carousel', 'post-grid', 'category-list', 'ad-slot-1' );

	/**
	 * Featured category slug for hero carousel.
	 *
	 * @return string
	 */
	public static function get_hero_category_slug() {
		return (string) get_option( self::OPTION_HERO_CATEGORY_SLUG, self::DEFAULT_HERO_CATEGORY_SLUG );
	}

	/**
	 * Max items in hero carousel.
	 *
	 * @return int
	 */
	public static function get_hero_max_items() {
		return (int) get_option( self::OPTION_HERO_MAX_ITEMS, self::DEFAULT_HERO_MAX_ITEMS );
	}

	/**
	 * Post grid columns (1–4).
	 *
	 * @return int
	 */
	public static function get_post_grid_columns() {
		$v = (int) get_option( self::OPTION_POST_GRID_COLUMNS, self::DEFAULT_POST_GRID_COLUMNS );
		return max( 1, min( 4, $v ) );
	}

	/**
	 * Posts per page in post grid.
	 *
	 * @return int
	 */
	public static function get_post_grid_per_page() {
		$v = (int) get_option( self::OPTION_POST_GRID_PER_PAGE, self::DEFAULT_POST_GRID_PER_PAGE );
		return max( 1, min( 50, $v ) );
	}

	/**
	 * Enabled category IDs for CategoryList. Empty = all top-level categories.
	 *
	 * @return int[]
	 */
	public static function get_enabled_category_ids() {
		$option = get_option( self::OPTION_ENABLED_CATEGORIES, null );
		if ( null === $option || false === $option || ! is_array( $option ) ) {
			return array();
		}
		return array_values( array_map( 'absint', array_filter( $option ) ) );
	}

	/**
	 * Enabled component IDs in display order.
	 *
	 * @return string[]
	 */
	public static function get_enabled_components() {
		$option = get_option( self::OPTION_ENABLED_COMPONENTS, null );
		if ( null === $option || false === $option || ! is_array( $option ) ) {
			return self::COMPONENT_IDS;
		}
		$valid = array_intersect( $option, self::COMPONENT_IDS );
		return empty( $valid ) ? self::COMPONENT_IDS : array_values( $valid );
	}
}
