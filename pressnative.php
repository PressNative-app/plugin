<?php
/**
 * Plugin Name: PressNative
 * Plugin URI:  https://pressnative.app
 * Description: Turn your WordPress site into a native mobile app with WooCommerce support. Serves layout, content, products, and branding via REST API to the PressNative Android and iOS apps.
 * Version:     1.1.0
 * Author:      PressNative
 * Author URI:  https://pressnative.app
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pressnative
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'PRESSNATIVE_VERSION', '1.1.0' );
define( 'PRESSNATIVE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRESSNATIVE_PLUGIN_FILE', __FILE__ );

require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-options.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-themes.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-layout-options.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-layout.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-dom-parser.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-aot-compiler.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-shortcodes.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-search-api.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-devices.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-analytics.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-admin.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-preview.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-registry-notify.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-qr.php';
require_once PRESSNATIVE_PLUGIN_DIR . 'includes/class-pressnative-woocommerce.php';

/**
 * Load plugin text domain for translations.
 */
add_action( 'init', function () {
	load_plugin_textdomain( 'pressnative', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/**
 * Activation: create devices table and verify Registry schema.
 */
register_activation_hook( __FILE__, function () {
	PressNative_Devices::create_table();
	PressNative_Admin::verify_registry_schema();
} );

/**
 * Deactivation: currently a no-op. Data is preserved so reactivation is seamless.
 * Full cleanup (options + database table) happens in uninstall.php.
 */
register_deactivation_hook( __FILE__, function () {
	// Intentionally empty: data is preserved for reactivation.
	// See uninstall.php for full cleanup on plugin deletion.
} );

/**
 * REST API: layout/home and register-device.
 */
add_action( 'rest_api_init', function () {
	$layout = new PressNative_Layout();

	register_rest_route(
		'pressnative/v1',
		'/layout/home',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
				$data     = $layout->get_home_layout();
				$response = rest_ensure_response( $data );
				$response->header( 'X-PressNative-Version', PRESSNATIVE_VERSION );
				$response->header( 'Last-Updated', gmdate( 'c' ) );
				if ( ! is_wp_error( $response ) && $response->get_status() === 200 ) {
					$device_id = $request->get_param( 'device_id' );
					PressNative_Analytics::forward_event_to_registry( 'home', 'home', get_bloginfo( 'name' ), null, $device_id );
				}
				return $response;
			},
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'pressnative/v1',
		'/layout/post/(?P<id>[\d]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
				$post_id = (int) $request['id'];

				// AOT cache: read pre-compiled SDUI blocks from post meta.
				// Falls back to on-demand DOM parsing when the cache is empty.
				$cached_blocks = PressNative_AOT_Compiler::get_cached_blocks( $post_id );

				$data = $layout->get_post_layout( $post_id, $cached_blocks );
				if ( ! $data ) {
					return new WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
				}
				$response = rest_ensure_response( $data );
				if ( ! is_wp_error( $response ) && $response->get_status() === 200 ) {
					$title     = isset( $data['screen']['title'] ) ? $data['screen']['title'] : get_the_title( $post_id );
					$device_id = $request->get_param( 'device_id' );
					PressNative_Analytics::forward_event_to_registry( 'post', (string) $post_id, $title, null, $device_id );
				}
				return $response;
			},
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'pressnative/v1',
		'/layout/page/(?P<slug>[a-zA-Z0-9\-_]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
				$slug = $request['slug'];
				$data = $layout->get_page_layout( $slug );
				if ( ! $data ) {
					return new WP_Error( 'not_found', 'Page not found', array( 'status' => 404 ) );
				}
				$response = rest_ensure_response( $data );
				if ( ! is_wp_error( $response ) && $response->get_status() === 200 ) {
					$title     = isset( $data['screen']['title'] ) ? $data['screen']['title'] : '';
					$device_id = $request->get_param( 'device_id' );
					PressNative_Analytics::forward_event_to_registry( 'page', $slug, $title, null, $device_id );
				}
				return $response;
			},
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'pressnative/v1',
		'/layout/category/(?P<id>[\d]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
				$data = $layout->get_category_layout( (int) $request['id'] );
				if ( ! $data ) {
					return new WP_Error( 'not_found', 'Category not found', array( 'status' => 404 ) );
				}
				$response = rest_ensure_response( $data );
				if ( ! is_wp_error( $response ) && $response->get_status() === 200 ) {
					$title     = isset( $data['screen']['title'] ) ? $data['screen']['title'] : '';
					$device_id = $request->get_param( 'device_id' );
					PressNative_Analytics::forward_event_to_registry( 'category', (string) $request['id'], $title, null, $device_id );
				}
				return $response;
			},
			'permission_callback' => '__return_true',
		)
	);

	// WooCommerce: shop, product, product-category layouts.
	if ( PressNative_WooCommerce::is_active() ) {
		register_rest_route(
			'pressnative/v1',
			'/layout/shop',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
					$data = $layout->get_shop_layout();
					if ( ! $data ) {
						return new WP_Error( 'not_found', 'Shop not available', array( 'status' => 404 ) );
					}
					$response = rest_ensure_response( $data );
					if ( ! is_wp_error( $response ) && $response->get_status() === 200 ) {
						$device_id = $request->get_param( 'device_id' );
						PressNative_Analytics::forward_event_to_registry( 'shop', 'shop', isset( $data['screen']['title'] ) ? $data['screen']['title'] : 'Shop', null, $device_id );
					}
					return $response;
				},
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'pressnative/v1',
			'/layout/product/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
					$data = $layout->get_product_layout( (int) $request['id'] );
					if ( ! $data ) {
						return new WP_Error( 'not_found', 'Product not found', array( 'status' => 404 ) );
					}
					$response = rest_ensure_response( $data );
					if ( ! is_wp_error( $response ) && $response->get_status() === 200 ) {
						$title     = isset( $data['screen']['title'] ) ? $data['screen']['title'] : '';
						$device_id = $request->get_param( 'device_id' );
						PressNative_Analytics::forward_event_to_registry( 'product', (string) $request['id'], $title, null, $device_id );
					}
					return $response;
				},
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'pressnative/v1',
			'/layout/product-category/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
					$data = $layout->get_product_category_layout( (int) $request['id'] );
					if ( ! $data ) {
						return new WP_Error( 'not_found', 'Product category not found', array( 'status' => 404 ) );
					}
					$response = rest_ensure_response( $data );
					if ( ! is_wp_error( $response ) && $response->get_status() === 200 ) {
						$title     = isset( $data['screen']['title'] ) ? $data['screen']['title'] : '';
						$device_id = $request->get_param( 'device_id' );
						PressNative_Analytics::forward_event_to_registry( 'product_category', (string) $request['id'], $title, null, $device_id );
					}
					return $response;
				},
				'permission_callback' => '__return_true',
			)
		);
	}

	// Branding sync endpoint: Registry pushes branding updates to WordPress.
	register_rest_route(
		'pressnative/v1',
		'/branding',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => function ( WP_REST_Request $request ) {
				$params = $request->get_json_params();
				if ( empty( $params ) || ! is_array( $params ) ) {
					return new WP_Error( 'invalid_body', 'JSON body required', array( 'status' => 400 ) );
				}

			$map = array(
				'app_name'         => PressNative_Options::OPTION_APP_NAME,
				'primary_color'    => PressNative_Options::OPTION_PRIMARY_COLOR,
				'accent_color'     => PressNative_Options::OPTION_ACCENT_COLOR,
				'background_color' => PressNative_Options::OPTION_BACKGROUND_COLOR,
				'text_color'       => PressNative_Options::OPTION_TEXT_COLOR,
				'border_color'     => PressNative_Options::OPTION_BORDER_COLOR,
				'font_family'      => PressNative_Options::OPTION_FONT_FAMILY,
				'base_font_size'   => PressNative_Options::OPTION_BASE_FONT_SIZE,
			);
			$updated = array();

			// Switch to Custom theme when Registry pushes explicit colors,
			// so theme presets don't override the synced values.
			if ( isset( $params['theme_id'] ) ) {
				$theme_id = sanitize_text_field( $params['theme_id'] );
				$themes   = PressNative_Themes::get_themes();
				if ( isset( $themes[ $theme_id ] ) ) {
					update_option( PressNative_Themes::OPTION_THEME_ID, $theme_id );
					$updated[] = 'theme_id';
				}
			}

			foreach ( $map as $key => $option_name ) {
				if ( isset( $params[ $key ] ) ) {
					$value = $params[ $key ];
					if ( in_array( $key, array( 'primary_color', 'accent_color', 'background_color', 'text_color', 'border_color' ), true ) ) {
						$value = PressNative_Options::sanitize_hex( $value );
					}
					update_option( $option_name, $value );
					$updated[] = $key;
				}
			}

				// Handle logo_url: download to media library if it's external.
				if ( isset( $params['logo_url'] ) && ! empty( $params['logo_url'] ) ) {
					$logo_url  = esc_url_raw( $params['logo_url'] );
					$current   = (int) get_option( PressNative_Options::OPTION_LOGO_ATTACHMENT, 0 );
					$current_u = $current > 0 ? wp_get_attachment_image_url( $current, 'full' ) : '';
					if ( $logo_url !== $current_u ) {
						require_once ABSPATH . 'wp-admin/includes/media.php';
						require_once ABSPATH . 'wp-admin/includes/file.php';
						require_once ABSPATH . 'wp-admin/includes/image.php';
						$attachment_id = media_sideload_image( $logo_url, 0, 'PressNative Logo', 'id' );
						if ( ! is_wp_error( $attachment_id ) ) {
							update_option( PressNative_Options::OPTION_LOGO_ATTACHMENT, $attachment_id );
							$updated[] = 'logo_url';
						}
					}
				}

				return rest_ensure_response( array(
					'ok'      => true,
					'updated' => $updated,
				) );
			},
			'permission_callback' => function ( WP_REST_Request $request ) {
				// Accept if request comes from Registry (shared key) or user is admin.
				$registry_key = $request->get_header( 'X-PressNative-Registry-Key' );
				$admin_key    = defined( 'PRESSNATIVE_ADMIN_KEY' )
					? PRESSNATIVE_ADMIN_KEY
					: getenv( 'ADMIN_API_KEY' );
				if ( $admin_key && $registry_key === $admin_key ) {
					return true;
				}
				return current_user_can( 'manage_options' );
			},
		)
	);

	// Site info endpoint: Registry fetches admin email and site metadata for the admin dashboard.
	register_rest_route(
		'pressnative/v1',
		'/site-info',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function () {
				$app_name = (string) get_option( PressNative_Options::OPTION_APP_NAME, get_bloginfo( 'name' ) );
				return rest_ensure_response(
					array(
						'admin_email' => (string) get_option( 'admin_email', '' ),
						'site_name'   => $app_name,
						'blog_name'   => get_bloginfo( 'name' ),
						'language'    => get_bloginfo( 'language' ),
						'timezone'    => wp_timezone_string(),
					)
				);
			},
			'permission_callback' => function ( WP_REST_Request $request ) {
				$registry_key = $request->get_header( 'X-PressNative-Registry-Key' );
				$admin_key    = defined( 'PRESSNATIVE_ADMIN_KEY' )
					? PRESSNATIVE_ADMIN_KEY
					: getenv( 'ADMIN_API_KEY' );
				if ( $admin_key && $registry_key === $admin_key ) {
					return true;
				}
				return current_user_can( 'manage_options' );
			},
		)
	);

	// Site verification endpoint: Registry calls this to confirm wp-admin access.
	// The plugin generates a nonce (see PressNative_Admin::trigger_site_verification),
	// sends it to the Registry, and the Registry calls back here to confirm it.
	register_rest_route(
		'pressnative/v1',
		'/verify-ownership',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => function ( WP_REST_Request $request ) {
				$params = $request->get_json_params();
				$nonce  = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';

				if ( empty( $nonce ) ) {
					return new WP_Error( 'missing_nonce', 'nonce is required', array( 'status' => 400 ) );
				}

				// Verify against the stored verification nonce
				$stored_nonce = get_transient( 'pressnative_verify_nonce' );
				if ( empty( $stored_nonce ) || ! hash_equals( $stored_nonce, $nonce ) ) {
					return new WP_Error( 'invalid_nonce', 'Verification nonce is invalid or expired', array( 'status' => 403 ) );
				}

				// Nonce is valid — delete it (single use) and confirm
				delete_transient( 'pressnative_verify_nonce' );

				return rest_ensure_response( array(
					'verified'   => true,
					'site_url'   => site_url(),
					'admin_email' => get_option( 'admin_email', '' ),
				) );
			},
			'permission_callback' => '__return_true',
		)
	);

	// Preview endpoint: returns home layout with optional overrides (no save).
	register_rest_route(
		'pressnative/v1',
		'/preview',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
				$overrides = $request->get_json_params();
				if ( ! is_array( $overrides ) ) {
					$overrides = array();
				}
				$callbacks = PressNative_Preview::apply_overrides( $overrides );
				try {
					$data = $layout->get_home_layout();
					return rest_ensure_response( $data );
				} finally {
					PressNative_Preview::remove_overrides( $callbacks );
				}
			},
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	// Documentation endpoint: returns documentation layout for the mobile apps.
	register_rest_route(
		'pressnative/v1',
		'/layout/documentation',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => function ( WP_REST_Request $request ) use ( $layout ) {
				$data = $layout->get_documentation_layout();
				$response = rest_ensure_response( $data );
				$response->header( 'X-PressNative-Version', PRESSNATIVE_VERSION );
				$response->header( 'Last-Updated', gmdate( 'c' ) );
				if ( ! is_wp_error( $response ) && $response->get_status() === 200 ) {
					$device_id = $request->get_param( 'device_id' );
					PressNative_Analytics::forward_event_to_registry( 'documentation', 'documentation', 'PressNative Documentation', null, $device_id );
				}
				return $response;
			},
			'permission_callback' => '__return_true',
		)
	);

	// WooCommerce: native add-to-cart and cart count (Store API proxy).
	if ( PressNative_WooCommerce::is_active() ) {
		register_rest_route(
			'pressnative/v1',
			'/cart/add',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$product_id   = (int) $request->get_param( 'product_id' );
					$variation_id = $request->get_param( 'variation_id' );
					$quantity     = (int) $request->get_param( 'quantity' );
					if ( $quantity < 1 ) {
						$quantity = 1;
					}
					if ( $product_id < 1 ) {
						return new WP_Error( 'invalid_product', 'product_id required', array( 'status' => 400 ) );
					}
					if ( ! function_exists( 'WC' ) ) {
						return new WP_Error( 'woocommerce_unavailable', 'WooCommerce not available', array( 'status' => 503 ) );
					}
					if ( ! WC()->cart ) {
						wc_load_cart();
						// For WooCommerce 9.0+, also load cart from session
						if ( method_exists( WC()->cart, 'get_cart_from_session' ) ) {
							WC()->cart->get_cart_from_session();
						}
					}
					if ( ! WC()->cart ) {
						return new WP_Error( 'woocommerce_unavailable', 'Cart not available', array( 'status' => 503 ) );
					}
					$variation_id = is_numeric( $variation_id ) ? (int) $variation_id : 0;
					$added        = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
					if ( $added === false ) {
						return new WP_Error( 'add_failed', 'Could not add to cart', array( 'status' => 400 ) );
					}
					$count = WC()->cart->get_cart_contents_count();
					return rest_ensure_response( array(
						'ok'          => true,
						'cart_count'  => $count,
					) );
				},
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'variation_id' => array(
						'required' => false,
						'type'     => 'integer',
					),
					'quantity'     => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 1,
					),
				),
			)
		);
		register_rest_route(
			'pressnative/v1',
			'/cart/remove',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function ( $request ) {
					$product_id = (int) $request->get_param( 'product_id' );
					if ( $product_id <= 0 ) {
						return new WP_Error( 'invalid_product', 'Invalid product ID', array( 'status' => 400 ) );
					}
					if ( ! function_exists( 'WC' ) ) {
						return new WP_Error( 'woocommerce_unavailable', 'WooCommerce not available', array( 'status' => 503 ) );
					}
					if ( ! WC()->cart ) {
						wc_load_cart();
						if ( method_exists( WC()->cart, 'get_cart_from_session' ) ) {
							WC()->cart->get_cart_from_session();
						}
					}
					if ( ! WC()->cart ) {
						return new WP_Error( 'woocommerce_unavailable', 'Cart not available', array( 'status' => 503 ) );
					}
					
					// Find and remove the cart item
					$removed = false;
					foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
						if ( (int) $cart_item['product_id'] === $product_id ) {
							WC()->cart->remove_cart_item( $cart_item_key );
							$removed = true;
							break; // Remove only the first match
						}
					}
					
					if ( ! $removed ) {
						return new WP_Error( 'item_not_found', 'Item not found in cart', array( 'status' => 404 ) );
					}
					
					$count = WC()->cart->get_cart_contents_count();
					return rest_ensure_response( array(
						'ok'          => true,
						'cart_count'  => $count,
					) );
				},
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			'pressnative/v1',
			'/cart/update',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function ( $request ) {
					$product_id = (int) $request->get_param( 'product_id' );
					$quantity   = (int) $request->get_param( 'quantity' );
					if ( $product_id <= 0 ) {
						return new WP_Error( 'invalid_product', 'Invalid product ID', array( 'status' => 400 ) );
					}
					if ( $quantity < 0 ) {
						return new WP_Error( 'invalid_quantity', 'Quantity must be 0 or greater', array( 'status' => 400 ) );
					}
					if ( ! function_exists( 'WC' ) ) {
						return new WP_Error( 'woocommerce_unavailable', 'WooCommerce not available', array( 'status' => 503 ) );
					}
					if ( ! WC()->cart ) {
						wc_load_cart();
						if ( method_exists( WC()->cart, 'get_cart_from_session' ) ) {
							WC()->cart->get_cart_from_session();
						}
					}
					if ( ! WC()->cart ) {
						return new WP_Error( 'woocommerce_unavailable', 'Cart not available', array( 'status' => 503 ) );
					}
					
					// Find and update the cart item
					$updated = false;
					foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
						if ( (int) $cart_item['product_id'] === $product_id ) {
							if ( $quantity === 0 ) {
								// Remove item if quantity is 0
								WC()->cart->remove_cart_item( $cart_item_key );
							} else {
								// Update quantity
								WC()->cart->set_quantity( $cart_item_key, $quantity );
							}
							$updated = true;
							break; // Update only the first match
						}
					}
					
					if ( ! $updated ) {
						return new WP_Error( 'item_not_found', 'Item not found in cart', array( 'status' => 404 ) );
					}
					
					$count = WC()->cart->get_cart_contents_count();
					return rest_ensure_response( array(
						'ok'          => true,
						'cart_count'  => $count,
					) );
				},
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'quantity'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			'pressnative/v1',
			'/cart/count',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					if ( ! function_exists( 'WC' ) ) {
						return rest_ensure_response( array( 'cart_count' => 0 ) );
					}
					if ( ! WC()->cart ) {
						wc_load_cart();
						// For WooCommerce 9.0+, also load cart from session
						if ( method_exists( WC()->cart, 'get_cart_from_session' ) ) {
							WC()->cart->get_cart_from_session();
						}
					}
					if ( ! WC()->cart ) {
						return rest_ensure_response( array( 'cart_count' => 0 ) );
					}
					$cart_items = array();
					foreach ( WC()->cart->get_cart() as $key => $item ) {
						$product_details = PressNative_WooCommerce::get_product( (int) $item['product_id'] );
						$cart_items[] = array(
							'product_id' => (int) $item['product_id'],
							'quantity'   => (int) $item['quantity'],
							'name'       => $product_details['title'] ?? '',
							'price'      => $product_details['price'] ?? '',
							'image'      => $product_details['image_url'] ?? '',
						);
					}
					return rest_ensure_response( array(
						'cart_count' => WC()->cart->get_cart_contents_count(),
						'cart_items' => $cart_items,
					) );
				},
				'permission_callback' => '__return_true',
			)
		);

	}

	PressNative_Devices::register_rest_route();
	PressNative_Analytics::register_rest_routes();
	PressNative_Search_Api::register_routes();
} );

/**
 * Minimal template for in-app WebView navigations.
 *
 * When a same-origin link is followed inside the app's WebView, the request
 * includes ?pressnative=1. This hook intercepts such requests and renders a
 * minimal HTML page containing only the post/page content with all enqueued
 * scripts and styles — no theme header, footer, sidebars or navigation.
 */
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['pressnative'] ) || $_GET['pressnative'] !== '1' ) {
		return;
	}

	// Let WordPress determine the queried object normally.
	$post = get_queried_object();
	if ( ! ( $post instanceof WP_Post ) ) {
		// Fallback: try the global $post.
		global $post;
	}
	if ( ! $post || ! ( $post instanceof WP_Post ) ) {
		status_header( 404 );
		echo '<!DOCTYPE html><html><body><p>Not found</p></body></html>';
		exit;
	}

	// Set up post data so shortcodes and the_content work.
	setup_postdata( $post );
	$content = apply_filters( 'the_content', $post->post_content );

	// Capture the full output so we can relativize absolute site URLs.
	// This ensures script/style URLs work in the mobile WebView which may
	// access the site via a different host (e.g. 10.0.2.2 on Android emulator).
	ob_start();
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: sans-serif; font-size: 16px; line-height: 1.5; margin: 0; padding: 16px; }
img { max-width: 100%; height: auto; }
table { width: 100%; border-collapse: collapse; }
</style>
<?php wp_head(); ?>
</head>
<body>
<?php echo $content; ?>
<?php wp_footer(); ?>
</body>
</html><?php
	$output   = ob_get_clean();
	$site_url = untrailingslashit( site_url() );
	echo str_replace( $site_url, '', $output );
	exit;
} );

/**
 * Admin: PressNative menu and Registry URL setting.
 */
PressNative_Admin::init();

/**
 * QR code shortcode for app deep links.
 */
PressNative_QR::init();

/**
 * AOT compiler: pre-build SDUI JSON on every post/page/product save.
 */
PressNative_AOT_Compiler::init();

/**
 * Notify Registry when branding/layout options are saved (invalidates site branding cache).
 */
PressNative_Registry_Notify::init();

/**
 * Handle app-initiated checkout with cart transfer.
 */
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['pressnative_checkout'] ) ) {
		return;
	}
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
		return;
	}
	
	$raw = sanitize_text_field( wp_unslash( $_GET['pressnative_checkout'] ) );
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
}, 1 );

/**
 * Handle checkout completion and return to app.
 */
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	if ( ! $order_id ) {
		return;
	}
	
	// Check if this checkout was initiated from the app
	$referer = wp_get_referer();
	$is_app_checkout = isset( $_GET['pressnative_checkout'] ) || 
	                   ( $referer && strpos( $referer, 'pressnative_checkout=' ) !== false );
	
	if ( ! $is_app_checkout ) {
		return;
	}
	
	// Add JavaScript to redirect to app after a short delay
	?>
	<script type="text/javascript">
		// Add a "Return to App" button
		setTimeout(function() {
			var returnButton = document.createElement('button');
			returnButton.innerHTML = 'Return to App';
			returnButton.style.cssText = 'background: #0073aa; color: white; padding: 12px 24px; border: none; border-radius: 4px; font-size: 16px; margin: 20px 0; cursor: pointer;';
			returnButton.onclick = function() {
				window.location.href = 'app.pressnative.hub://checkout-complete?order_id=<?php echo esc_js( $order_id ); ?>';
			};
			
			// Find a good place to insert the button
			var orderDetails = document.querySelector('.woocommerce-order');
			if (orderDetails) {
				orderDetails.appendChild(returnButton);
			} else {
				document.body.appendChild(returnButton);
			}
		}, 1000);
		
		// Auto-redirect after 10 seconds
		setTimeout(function() {
			window.location.href = 'app.pressnative.hub://checkout-complete?order_id=<?php echo esc_js( $order_id ); ?>';
		}, 10000);
	</script>
	<?php
}, 10, 1 );

/**
 * Add return to app link on cart page when accessed from app.
 */
add_action( 'woocommerce_after_cart', function () {
	$referer = wp_get_referer();
	$is_app_context = isset( $_GET['pressnative_checkout'] ) || 
	                  ( $referer && strpos( $referer, 'pressnative_checkout=' ) !== false );
	
	if ( ! $is_app_context ) {
		return;
	}
	
	?>
	<div style="text-align: center; margin: 20px 0;">
		<a href="app.pressnative.hub://home" 
		   style="background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
			Return to App
		</a>
	</div>
	<?php
} );

/**
 * Initialize WooCommerce integration.
 */
PressNative_WooCommerce::init();

/**
 * Register app download shortcodes.
 */
add_action( 'init', function () {
	add_shortcode( 'pressnative_download', function ( $atts ) {
		$atts = shortcode_atts( array(
			'style'     => 'both',     // 'ios', 'android', 'both'
			'size'      => 'medium',   // 'small', 'medium', 'large'
			'text'      => '',         // Custom text override
			'ios_url'   => '',         // Custom iOS URL
			'android_url' => '',       // Custom Android URL
		), $atts, 'pressnative_download' );

		$app_name = get_option( PressNative_Options::OPTION_APP_NAME, get_bloginfo( 'name' ) );
		$default_text = $atts['text'] ?: "Download {$app_name}";
		$site_url = untrailingslashit( home_url() );
		
		// Generate fallback HTML for web browsers with deferred deep linking
		$html = '<div class="pressnative-download-buttons" style="text-align: center; margin: 20px 0;">';
		
		if ( $atts['style'] === 'ios' || $atts['style'] === 'both' ) {
			$ios_base_url = $atts['ios_url'] ?: 'https://apps.apple.com/app/pressnative/id0000000000';
			$ios_url = $ios_base_url . '?pt=pressnative&ct=' . urlencode( $site_url );
			$html .= '<a href="' . esc_url( $ios_url ) . '" class="pressnative-ios-download" style="display: inline-block; margin: 5px; padding: 12px 24px; background: #000; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;">📱 Download for iOS</a>';
		}
		
		if ( $atts['style'] === 'android' || $atts['style'] === 'both' ) {
			$android_base_url = $atts['android_url'] ?: 'https://play.google.com/store/apps/details?id=app.pressnative.hub';
			$android_url = $android_base_url . '&referrer=utm_source%3Dpressnative%26utm_content%3D' . urlencode( $site_url );
			$html .= '<a href="' . esc_url( $android_url ) . '" class="pressnative-android-download" style="display: inline-block; margin: 5px; padding: 12px 24px; background: #34A853; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;">🤖 Download for Android</a>';
		}
		
		$html .= '</div>';
		
		return $html;
	} );

	// Alias shortcode for convenience
	add_shortcode( 'app_download', function ( $atts ) {
		return do_shortcode( '[pressnative_download ' . implode( ' ', array_map( function ( $k, $v ) {
			return $k . '="' . esc_attr( $v ) . '"';
		}, array_keys( $atts ), $atts ) ) . ']' );
	} );

	// Debug shortcode for testing deep links
	add_shortcode( 'pressnative_debug_links', function ( $atts ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return ''; // Only show to admins
		}
		
		$site_url = untrailingslashit( home_url() );
		$deep_link_url = 'https://pressnative.app/open?site=' . rawurlencode( $site_url );
		$custom_scheme_url = 'app.pressnative.hub://site?url=' . rawurlencode( $site_url );
		
		$html = '<div style="background: #f0f0f0; padding: 20px; border-radius: 8px; margin: 20px 0;">';
		$html .= '<h3>🔧 PressNative Debug Links (Admin Only)</h3>';
		$html .= '<p><strong>Deep Link (HTTPS):</strong><br><a href="' . esc_url( $deep_link_url ) . '" target="_blank">' . esc_html( $deep_link_url ) . '</a></p>';
		$html .= '<p><strong>Custom Scheme:</strong><br><a href="' . esc_url( $custom_scheme_url ) . '" target="_blank">' . esc_html( $custom_scheme_url ) . '</a></p>';
		$html .= '<p><em>Click these links on your mobile device to test deep linking.</em></p>';
		$html .= '</div>';
		
		return $html;
	} );
} );
