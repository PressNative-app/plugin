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
	 * Initialize WooCommerce integration hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_app_checkout' ), 1 );
	}

	/**
	 * Handle checkout from the native app.
	 *
	 * The app opens: https://site.com/?pressnative_checkout=PID:QTY,PID:QTY
	 * This hook parses the items, adds them to a fresh WC cart, and redirects
	 * to the real checkout page. No sessions or tokens required.
	 */
	public static function handle_app_checkout() {
		if ( ! isset( $_GET['pressnative_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! self::is_active() ) {
			return;
		}

		$raw = sanitize_text_field( wp_unslash( $_GET['pressnative_checkout'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		wc_load_cart();
		WC()->cart->empty_cart();

		if ( ! empty( $raw ) ) {
			$pairs = explode( ',', $raw );
			foreach ( $pairs as $pair ) {
				$parts      = explode( ':', $pair );
				$product_id = isset( $parts[0] ) ? absint( $parts[0] ) : 0;
				$quantity   = isset( $parts[1] ) ? absint( $parts[1] ) : 1;
				if ( $product_id > 0 && $quantity > 0 ) {
					WC()->cart->add_to_cart( $product_id, $quantity );
				}
			}
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Register WooCommerce REST API routes.
	 */
	public static function register_rest_routes() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Seed demo data endpoint
		register_rest_route(
			'pressnative/v1',
			'/woocommerce/seed-demo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'seed_demo_data' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
			)
		);
	}

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
		return $page_id > 0 ? (string) get_permalink( $page_id ) : trailingslashit( home_url( 'cart' ) );
	}

	/**
	 * Get checkout URL for the site.
	 * The app builds the actual checkout URL with ?pressnative_checkout=ID:QTY,ID:QTY to transfer cart and redirect here.
	 *
	 * @return string
	 */
	public static function get_checkout_url() {
		if ( ! self::is_active() ) {
			return '';
		}
		$page_id = wc_get_page_id( 'checkout' );
		return $page_id > 0 ? (string) get_permalink( $page_id ) : trailingslashit( home_url( 'checkout' ) );
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
			'product_display_preferences' => self::get_product_display_preferences(),
		);
	}

	/**
	 * Get product display preferences for different contexts.
	 *
	 * @return array
	 */
	public static function get_product_display_preferences() {
		$in_post_style = get_option( 'pressnative_product_in_post_style', 'compact_row' );
		$grid_style = get_option( 'pressnative_product_grid_style', 'card' );
		
		return array(
			'in_post_style' => $in_post_style,
			'grid_style'    => $grid_style,
			'available_styles' => array(
				'compact_row' => array(
					'name'        => __( 'Compact Row', 'pressnative' ),
					'description' => __( 'Image on left, details on right - perfect for in-post products', 'pressnative' ),
				),
				'card' => array(
					'name'        => __( 'Card', 'pressnative' ),
					'description' => __( 'Full-width card with large image - great for product grids', 'pressnative' ),
				),
				'mini_card' => array(
					'name'        => __( 'Mini Card', 'pressnative' ),
					'description' => __( 'Smaller card with centered image - subtle in-post display', 'pressnative' ),
				),
			),
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
		$price = self::strip_price_html( $product->get_price_html() );
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
		$id    = $product->get_id();
		$image = '';
		if ( $product->get_image_id() ) {
			$image = wp_get_attachment_image_url( $product->get_image_id(), 'large' );
		}
		if ( empty( $image ) ) {
			$image = wc_placeholder_img_src( 'large' );
		}
		$image = $image ? (string) $image : '';

		// Gallery: featured first, then gallery IDs, deduplicated URLs.
		$image_ids = array();
		$feat_id   = $product->get_image_id();
		if ( $feat_id ) {
			$image_ids[] = $feat_id;
		}
		if ( method_exists( $product, 'get_gallery_image_ids' ) ) {
			$gallery_ids = $product->get_gallery_image_ids();
			if ( is_array( $gallery_ids ) ) {
				foreach ( $gallery_ids as $aid ) {
					if ( $aid && ! in_array( $aid, $image_ids, true ) ) {
						$image_ids[] = $aid;
					}
				}
			}
		}
		$images = array();
		$seen   = array();
		foreach ( $image_ids as $aid ) {
			$url = wp_get_attachment_image_url( (int) $aid, 'large' );
			if ( $url && ! in_array( $url, $seen, true ) ) {
				$images[] = (string) $url;
				$seen[]   = $url;
			}
		}
		if ( empty( $images ) && $image ) {
			$images[] = $image;
		}
		if ( empty( $images ) && function_exists( 'wc_placeholder_img_src' ) ) {
			$images[] = (string) wc_placeholder_img_src( 'large' );
		}

		$desc = $product->get_short_description();
		if ( empty( $desc ) ) {
			$desc = $product->get_description();
		}
		$variations = array();
		if ( $product->is_type( 'variable' ) && method_exists( $product, 'get_available_variations' ) ) {
			$variations = $product->get_available_variations();
		}
		// Collect category names.
		$category_names = array();
		$terms = get_the_terms( $id, 'product_cat' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$category_names[] = $term->name;
			}
		}

		return array(
			'product_id'         => (string) $id,
			'title'               => $product->get_name(),
			'price'               => self::strip_price_html( $product->get_price_html() ),
			'price_raw'           => $product->get_price(),
			'description'        => $desc,
			'image_url'           => $image,
			'images'              => $images,
			'categories'          => $category_names,
			'add_to_cart_action'   => array(
				'type'    => 'add_to_cart',
				'payload' => array( 'product_id' => (string) $id ),
			),
			'variations'          => $variations,
		);
	}

	/**
	 * Convert WooCommerce price HTML to plain text (e.g. "$44.00").
	 *
	 * @param string $html Price HTML from get_price_html().
	 * @return string Plain text price.
	 */
	private static function strip_price_html( $html ) {
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		return trim( $text );
	}

	/**
	 * Seed WooCommerce with demo data via REST API.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function seed_demo_data( $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'woocommerce_not_active', 'WooCommerce must be active', array( 'status' => 400 ) );
		}

		$results = array(
			'categories_created' => 0,
			'products_created'   => 0,
			'posts_created'      => 0,
			'errors'             => array(),
		);

		// Product categories
		$product_categories = array(
			array( 'name' => 'Electronics',       'slug' => 'electronics',       'description' => 'Smartphones, laptops, accessories' ),
			array( 'name' => 'Fashion',           'slug' => 'fashion',            'description' => 'Clothing, shoes, jewelry' ),
			array( 'name' => 'Home & Garden',     'slug' => 'home-garden',       'description' => 'Furniture, decor, tools' ),
			array( 'name' => 'Sports & Outdoors', 'slug' => 'sports-outdoors',  'description' => 'Fitness, camping, sports gear' ),
			array( 'name' => 'Books & Media',     'slug' => 'books-media',      'description' => 'Books, games, movies' ),
		);

		// Demo products
		$demo_products = array(
			// Electronics
			array( 'name' => 'iPhone 15 Pro',           'price' => 999,  'category' => 'electronics', 'featured' => true ),
			array( 'name' => 'MacBook Air M3',           'price' => 1299, 'category' => 'electronics', 'featured' => true ),
			array( 'name' => 'AirPods Pro',             'price' => 249,  'category' => 'electronics' ),
			array( 'name' => 'iPad Pro 12.9"',          'price' => 1099, 'category' => 'electronics' ),
			array( 'name' => 'Apple Watch Ultra',       'price' => 799,  'category' => 'electronics' ),
			array( 'name' => 'Wireless Keyboard',       'price' => 99,   'category' => 'electronics' ),
			array( 'name' => 'USB-C Hub 7-in-1',        'price' => 59,   'category' => 'electronics' ),
			// Fashion
			array( 'name' => 'Premium Wool Sweater',    'price' => 89,   'category' => 'fashion', 'featured' => true ),
			array( 'name' => 'Designer Jeans',          'price' => 129,  'category' => 'fashion' ),
			array( 'name' => 'Leather Boots',           'price' => 199,  'category' => 'fashion' ),
			array( 'name' => 'Silk Scarf',              'price' => 45,   'category' => 'fashion' ),
			array( 'name' => 'Classic Blazer',          'price' => 179,  'category' => 'fashion' ),
			array( 'name' => 'Cotton T-Shirt Pack',     'price' => 39,   'category' => 'fashion' ),
			array( 'name' => 'Running Jacket',          'price' => 119,  'category' => 'fashion' ),
			// Home & Garden
			array( 'name' => 'Smart Coffee Maker',      'price' => 179,  'category' => 'home-garden', 'featured' => true ),
			array( 'name' => 'Ergonomic Office Chair',  'price' => 299,  'category' => 'home-garden' ),
			array( 'name' => 'Indoor Plant Collection', 'price' => 49,   'category' => 'home-garden' ),
			array( 'name' => 'Desk Lamp LED',           'price' => 69,   'category' => 'home-garden' ),
			array( 'name' => 'Throw Pillow Set',        'price' => 34,   'category' => 'home-garden' ),
			array( 'name' => 'Garden Tool Set',         'price' => 79,   'category' => 'home-garden' ),
			array( 'name' => 'Bluetooth Speaker',       'price' => 89,   'category' => 'home-garden' ),
			// Sports & Outdoors
			array( 'name' => 'Yoga Mat Pro',            'price' => 79,   'category' => 'sports-outdoors' ),
			array( 'name' => 'Running Shoes',           'price' => 159,  'category' => 'sports-outdoors' ),
			array( 'name' => 'Camping Tent 4-Person',   'price' => 249,  'category' => 'sports-outdoors' ),
			array( 'name' => 'Water Bottle 32oz',       'price' => 29,   'category' => 'sports-outdoors' ),
			array( 'name' => 'Dumbbell Set',            'price' => 129,  'category' => 'sports-outdoors' ),
			array( 'name' => 'Cycling Helmet',          'price' => 59,   'category' => 'sports-outdoors' ),
			array( 'name' => 'Resistance Bands',        'price' => 24,   'category' => 'sports-outdoors' ),
			// Books & Media
			array( 'name' => 'Bestseller Novel',        'price' => 16,   'category' => 'books-media' ),
			array( 'name' => 'Programming Guide',       'price' => 44,   'category' => 'books-media' ),
			array( 'name' => 'Board Game Classic',      'price' => 35,   'category' => 'books-media' ),
			array( 'name' => 'Wireless Earbuds',        'price' => 79,   'category' => 'books-media' ),
			array( 'name' => 'Streaming Stick',         'price' => 49,   'category' => 'books-media' ),
		);

		// Shoppable posts
		$shoppable_posts = array(
			array(
				'title'   => 'Best Tech Gadgets 2026',
				'excerpt' => 'Our top picks for smartphones, laptops, and accessories this year.',
				'slugs'   => array( 'iphone-15-pro', 'macbook-air-m3', 'airpods-pro' ),
			),
			array(
				'title'   => 'Spring Fashion Trends',
				'excerpt' => 'Refresh your wardrobe with these seasonal essentials.',
				'slugs'   => array( 'premium-wool-sweater', 'designer-jeans', 'silk-scarf' ),
			),
			array(
				'title'   => 'Home Office Setup Guide',
				'excerpt' => 'Create a productive workspace with these must-have items.',
				'slugs'   => array( 'smart-coffee-maker', 'ergonomic-office-chair', 'indoor-plant-collection' ),
			),
			array(
				'title'   => 'Fitness Journey Essentials',
				'excerpt' => 'Gear that keeps you motivated and comfortable.',
				'slugs'   => array( 'yoga-mat-pro', 'running-shoes', 'water-bottle-32oz' ),
			),
		);

		$term_ids_by_slug    = array();
		$product_ids_by_slug = array();
		$default_description = 'Quality product with great reviews. Perfect for everyday use.';

		// Create categories
		foreach ( $product_categories as $cat ) {
			$existing = get_term_by( 'slug', $cat['slug'], 'product_cat' );
			if ( $existing ) {
				$term_ids_by_slug[ $cat['slug'] ] = (int) $existing->term_id;
				continue;
			}

			$term_result = wp_insert_term( $cat['name'], 'product_cat', array(
				'slug'        => $cat['slug'],
				'description' => $cat['description'],
			) );

			if ( is_wp_error( $term_result ) ) {
				$results['errors'][] = 'Failed to create category: ' . $cat['name'] . ' - ' . $term_result->get_error_message();
				continue;
			}

			$term_ids_by_slug[ $cat['slug'] ] = (int) $term_result['term_id'];
			$results['categories_created']++;
		}

		// Create products
		foreach ( $demo_products as $product_data ) {
			$slug = sanitize_title( $product_data['name'] );

			// Check if product already exists
			$existing_product = get_page_by_path( $slug, OBJECT, 'product' );
			if ( $existing_product ) {
				$product_ids_by_slug[ $slug ] = (int) $existing_product->ID;
				continue;
			}

			$post_id = wp_insert_post( array(
				'post_title'   => $product_data['name'],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'product',
				'post_content' => $default_description,
			) );

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				$results['errors'][] = 'Failed to create product: ' . $product_data['name'];
				continue;
			}

			$product = wc_get_product( $post_id );
			if ( ! $product ) {
				$results['errors'][] = 'Failed to get product object: ' . $product_data['name'];
				continue;
			}

			// Set product properties
			$product->set_regular_price( (string) $product_data['price'] );
			$product->set_price( (string) $product_data['price'] );
			$product->set_stock_status( 'instock' );
			$product->set_catalog_visibility( 'visible' );

			if ( ! empty( $product_data['featured'] ) ) {
				$product->set_featured( true );
			}

			$product->save();

			// Assign to category
			if ( isset( $term_ids_by_slug[ $product_data['category'] ] ) ) {
				wp_set_object_terms( $post_id, array( $term_ids_by_slug[ $product_data['category'] ] ), 'product_cat' );
			}

			$product_ids_by_slug[ $slug ] = (int) $post_id;
			$results['products_created']++;
		}

		// Create shoppable posts
		foreach ( $shoppable_posts as $post_data ) {
			// Check if post already exists
			$existing_post = get_page_by_title( $post_data['title'], OBJECT, 'post' );
			if ( $existing_post ) {
				continue;
			}

			$shortcodes = array();
			foreach ( $post_data['slugs'] as $pslug ) {
				if ( isset( $product_ids_by_slug[ $pslug ] ) ) {
					$shortcodes[] = '[product_page id="' . $product_ids_by_slug[ $pslug ] . '"]';
				}
			}

			$content = '<p>' . esc_html( $post_data['excerpt'] ) . '</p>' . "\n\n"
				. implode( "\n\n", $shortcodes ) . "\n\n"
				. '<p>Shop these picks and more in our store.</p>';

			$post_id = wp_insert_post( array(
				'post_title'   => $post_data['title'],
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_content' => $content,
				'post_excerpt' => $post_data['excerpt'],
			) );

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				$results['posts_created']++;
			} else {
				$results['errors'][] = 'Failed to create post: ' . $post_data['title'];
			}
		}

		return rest_ensure_response( $results );
	}

}
