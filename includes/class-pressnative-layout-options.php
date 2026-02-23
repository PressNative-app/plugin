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

	/** WooCommerce layout options. */
	const OPTION_PRODUCT_GRID_COLUMNS  = 'pressnative_product_grid_columns';
	const OPTION_PRODUCT_GRID_PER_PAGE = 'pressnative_product_grid_per_page';
	const OPTION_FEATURED_PRODUCT_CAT  = 'pressnative_featured_product_cat';

	const DEFAULT_HERO_CATEGORY_SLUG = 'featured';
	const DEFAULT_HERO_MAX_ITEMS     = 3;
	const DEFAULT_POST_GRID_COLUMNS  = 2;
	const DEFAULT_POST_GRID_PER_PAGE = 10;
	const DEFAULT_PRODUCT_GRID_COLUMNS  = 2;
	const DEFAULT_PRODUCT_GRID_PER_PAGE = 12;

	/** Base component IDs (always available). */
	const BASE_COMPONENT_IDS = array( 'hero-carousel', 'post-grid', 'category-list', 'page-list' );

	/** WooCommerce component IDs (only used when WooCommerce is active). */
	const WOOCOMMERCE_COMPONENT_IDS = array( 'product-grid', 'product-category-list', 'product-carousel' );

	/** All component IDs in default order. */
	const COMPONENT_IDS = array( 'hero-carousel', 'post-grid', 'category-list', 'page-list', 'product-grid', 'product-category-list', 'product-carousel' );

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
		$woo_active = class_exists( 'PressNative_WooCommerce' ) && PressNative_WooCommerce::is_active();
		$all_ids    = $woo_active ? self::COMPONENT_IDS : self::BASE_COMPONENT_IDS;

		$option = get_option( self::OPTION_ENABLED_COMPONENTS, null );
		if ( null === $option || false === $option || ! is_array( $option ) ) {
			return $all_ids;
		}
		$valid = array_intersect( $option, $all_ids );
		return empty( $valid ) ? $all_ids : array_values( $valid );
	}

	/**
	 * Product grid columns (1–4).
	 *
	 * @return int
	 */
	public static function get_product_grid_columns() {
		$v = (int) get_option( self::OPTION_PRODUCT_GRID_COLUMNS, self::DEFAULT_PRODUCT_GRID_COLUMNS );
		return max( 1, min( 4, $v ) );
	}

	/**
	 * Products per page in product grid.
	 *
	 * @return int
	 */
	public static function get_product_grid_per_page() {
		$v = (int) get_option( self::OPTION_PRODUCT_GRID_PER_PAGE, self::DEFAULT_PRODUCT_GRID_PER_PAGE );
		return max( 1, min( 50, $v ) );
	}

	/**
	 * Featured product category slug for ProductCarousel.
	 *
	 * @return string
	 */
	public static function get_featured_product_cat() {
		return (string) get_option( self::OPTION_FEATURED_PRODUCT_CAT, '' );
	}
}
