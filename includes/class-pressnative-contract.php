<?php
/**
 * Generated from www/contract.schema.json by scripts/generate-contract-dtos.mjs. Do not edit.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

class PressNative_Contract {

	const VERSION = '1.0.0';

	/**
	 * Known top-level component type strings from the layout contract.
	 *
	 * @return string[]
	 */
	public static function component_types() {
		return array(
			'HeroCarousel',
			'PostGrid',
			'CategoryList',
			'PageList',
			'BlockSponsor',
			'NavMenu',
			'PostDetail',
			'ShortcodeBlock',
			'PostContentBlock',
			'ProductGrid',
			'ProductDetail',
			'ProductCategoryList',
			'ProductCarousel',
			'ProductCardCompact',
			'CartPromoBanner',
			'Documentation',
		);
	}

	/**
	 * Stamp contract_version onto a layout envelope.
	 *
	 * @param array $layout Layout array.
	 * @return array
	 */
	public static function apply( $layout ) {
		if ( ! is_array( $layout ) ) {
			return $layout;
		}
		$layout['contract_version'] = self::VERSION;
		return $layout;
	}
}
