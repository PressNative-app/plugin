<?php
/**
 * WooCommerce integration for PressNative: products, categories, cart nonce, session bridge.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_WooCommerce
 *
 * Provides WooCommerce data and Store API nonce for native cart operations.
 * All methods guard with class_exists( 'WooCommerce' ).
 */
class PressNative_WooCommerce {

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Generate nonce for WooCommerce Store API (cart add, checkout).
	 * Native app sends this as Nonce header when calling Store API or PressNative cart proxy.
	 *
	 * @return string Nonce string, or empty if WooCommerce inactive.
	 */
	public static function generate_cart_session_nonce() {
		if ( ! self::is_active() ) {
			return '';
		}
		return wp_create_nonce( 'wc_store_api' );
	}

	/**
	 * Get a session/cart token for cookie bridge (checkout WebView).
	 * Uses current customer session so checkout can pre-fill user data.
	 *
	 * @return string Token or empty.
	 */
	public static function get_session_token() {
		if ( ! self::is_active() ) {
			return '';
		}
		if ( function_exists( 'WC' ) && isset( WC()->session ) && is_object( WC()->session ) ) {
			$session = WC()->session;
			if ( method_exists( $session, 'get_customer_id' ) ) {
				return (string) $session->get_customer_id();
			}
			if ( isset( $session->_customer_id ) ) {
				return (string) $session->_customer_id;
			}
		}
		return '';
	}

	/**
	 * Get cart URL for the site.
	 *
	 * @return string
	 */
	public static function get_cart_url() {
		if ( ! self::is_active() ) {
			return '';
		}
		$page_id = wc_get_page_id( 'cart' );
		if ( $page_id > 0 ) {
			return (string) get_permalink( $page_id );
		}
		return trailingslashit( home_url( 'cart' ) );
	}

	/**
	 * Get checkout URL for the site.
	 *
	 * @return string
	 */
	public static function get_checkout_url() {
		if ( ! self::is_active() ) {
			return '';
		}
		$page_id = wc_get_page_id( 'checkout' );
		if ( $page_id > 0 ) {
			return (string) get_permalink( $page_id );
		}
		return trailingslashit( home_url( 'checkout' ) );
	}

	/**
	 * Get shop_config for layout responses (cart_url, checkout_url, nonce, session, currency).
	 *
	 * @return array
	 */
	public static function get_shop_config() {
		if ( ! self::is_active() ) {
			return array();
		}
		$currency = get_woocommerce_currency();
		$symbol  = get_woocommerce_currency_symbol();
		return array(
			'cart_url'          => self::get_cart_url(),
			'checkout_url'      => self::get_checkout_url(),
			'store_api_nonce'   => self::generate_cart_session_nonce(),
			'session_token'     => self::get_session_token(),
			'currency'          => $currency,
			'currency_symbol'   => $symbol,
		);
	}

	/**
	 * Get products for layout (grid, carousel).
	 *
	 * @param array $args Same as wc_get_products (limit, category, featured, etc.).
	 * @return array List of product data for contract (id, title, price, image_url, etc.).
	 */
	public static function get_products( $args = array() ) {
		if ( ! self::is_active() || ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$defaults = array(
			'status'  => 'publish',
			'limit'   => 12,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'ids',
		);
		$ids = wc_get_products( array_merge( $defaults, $args ) );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$out = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}
			$out[] = self::product_to_contract_item( $product );
		}
		return $out;
	}

	/**
	 * Convert WC_Product to contract-style product item.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	public static function product_to_contract_item( $product ) {
		$id    = $product->get_id();
		$title = $product->get_name();
		$price = $product->get_price_html();
		$image = '';
		if ( $product->get_image_id() ) {
			$image = wp_get_attachment_image_url( $product->get_image_id(), 'medium' );
		}
		if ( empty( $image ) ) {
			$image = wc_placeholder_img_src( 'medium' );
		}
		$image = $image ? (string) $image : '';
		return array(
			'product_id'         => (string) $id,
			'title'              => $title,
			'price'              => $price,
			'price_raw'          => $product->get_price(),
			'image_url'          => $image,
			'action'             => array(
				'type'    => 'open_product',
				'payload' => array( 'product_id' => (string) $id ),
			),
			'add_to_cart_action' => array(
				'type'    => 'add_to_cart',
				'payload' => array( 'product_id' => (string) $id ),
			),
		);
	}

	/**
	 * Get product categories for ProductCategoryList.
	 *
	 * @param array $args get_terms args (hide_empty, parent, include, etc.).
	 * @return array List of category items for contract.
	 */
	public static function get_product_categories( $args = array() ) {
		if ( ! self::is_active() ) {
			return array();
		}
		$defaults = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'parent'     => 0,
		);
		$terms = get_terms( array_merge( $defaults, $args ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $term ) {
			$icon = '';
			$meta = get_term_meta( $term->term_id, 'thumbnail_id', true );
			if ( $meta ) {
				$url = wp_get_attachment_image_url( (int) $meta, 'medium' );
				if ( $url ) {
					$icon = $url;
				}
			}
			if ( empty( $icon ) ) {
				$icon = get_site_icon_url( 512 ) ?: '';
			}
			$out[] = array(
				'product_category_id' => (string) $term->term_id,
				'name'                => $term->name,
				'icon_url'            => $icon,
				'action'              => array(
					'type'    => 'open_product_category',
					'payload' => array( 'product_category_id' => (string) $term->term_id ),
				),
			);
		}
		return $out;
	}

	/**
	 * Get single product for ProductDetail layout.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null Contract product detail or null if not found.
	 */
	public static function get_product( $product_id ) {
		if ( ! self::is_active() ) {
			return null;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_visible() ) {
			return null;
		}
		$id     = $product->get_id();
		$image  = '';
		if ( $product->get_image_id() ) {
			$image = wp_get_attachment_image_url( $product->get_image_id(), 'large' );
		}
		if ( empty( $image ) ) {
			$image = wc_placeholder_img_src( 'large' );
		}
		$image = $image ? (string) $image : '';
		$desc  = $product->get_short_description();
		if ( empty( $desc ) ) {
			$desc = $product->get_description();
		}
		$variations = array();
		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_available_variations' ) ) {
			$variations = $product->get_available_variations();
		}
		return array(
			'product_id'         => (string) $id,
			'title'               => $product->get_name(),
			'price'               => $product->get_price_html(),
			'price_raw'           => $product->get_price(),
			'description'        => $desc,
			'image_url'           => $image,
			'add_to_cart_action'   => array(
				'type'    => 'add_to_cart',
				'payload' => array( 'product_id' => (string) $id ),
			),
			'variations'          => $variations,
		);
	}

}
