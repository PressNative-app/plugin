<?php
/**
 * WooCommerce abandoned-cart and back-in-stock push hooks.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Cart_Recovery
 */
class PressNative_Cart_Recovery {

	const OPTION_ENABLED               = 'pressnative_cart_recovery_enabled';
	const OPTION_DELAY_MINUTES         = 'pressnative_cart_recovery_delay_minutes';
	const OPTION_BACK_IN_STOCK_ENABLED = 'pressnative_back_in_stock_enabled';
	const TRANSIENT_PREFIX             = 'pressnative_cart_recovery_';
	const DEVICE_PREFIX                = 'pressnative_cart_device_';
	const CRON_HOOK                    = 'pressnative_send_cart_recovery_push';

	/**
	 * Bootstrap.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'schedule_recovery' ), 20 );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'maybe_clear_if_empty' ), 20 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'clear_on_order' ), 10, 1 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_scheduled_recovery' ), 10, 1 );
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'maybe_back_in_stock' ), 10, 3 );
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'maybe_back_in_stock' ), 10, 3 );
	}

	/**
	 * Whether cart recovery push is enabled (default on when abandoned_cart pref is on).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$flag = get_option( self::OPTION_ENABLED, '1' );
		if ( '0' === (string) $flag ) {
			return false;
		}
		$prefs = PressNative_Options::get_notification_preferences();
		return ! empty( $prefs['enabled'] ) && ! empty( $prefs['types']['abandoned_cart']['enabled'] );
	}

	/**
	 * Whether back-in-stock push is enabled.
	 *
	 * @return bool
	 */
	public static function is_back_in_stock_enabled() {
		$flag = get_option( self::OPTION_BACK_IN_STOCK_ENABLED, '1' );
		if ( '0' === (string) $flag ) {
			return false;
		}
		$prefs = PressNative_Options::get_notification_preferences();
		return ! empty( $prefs['enabled'] ) && ! empty( $prefs['types']['product_updates']['enabled'] );
	}

	/**
	 * Delay in minutes before recovery push (default 60).
	 *
	 * @return int
	 */
	public static function get_delay_minutes() {
		$m = (int) get_option( self::OPTION_DELAY_MINUTES, 60 );
		return max( 15, min( 24 * 60, $m ) );
	}

	/**
	 * Bind the current WooCommerce session to a native device_id.
	 *
	 * @param string $device_id Client device identifier.
	 * @return void
	 */
	public static function bind_device( $device_id ) {
		if ( ! is_string( $device_id ) ) {
			return;
		}
		$device_id = substr( sanitize_text_field( $device_id ), 0, 255 );
		if ( '' === $device_id ) {
			return;
		}
		$key = self::session_key();
		if ( ! $key ) {
			return;
		}
		set_transient( self::DEVICE_PREFIX . $key, $device_id, DAY_IN_SECONDS );
	}

	/**
	 * Device bound to the current WC session, if any.
	 *
	 * @return string|null
	 */
	public static function get_bound_device() {
		$key = self::session_key();
		if ( ! $key ) {
			return null;
		}
		$id = get_transient( self::DEVICE_PREFIX . $key );
		return ( is_string( $id ) && '' !== $id ) ? $id : null;
	}

	/**
	 * Schedule a single recovery event after add-to-cart.
	 *
	 * @return void
	 */
	public static function schedule_recovery() {
		if ( ! self::is_enabled() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( WC()->cart->is_empty() ) {
			return;
		}

		$key = self::session_key();
		if ( ! $key ) {
			return;
		}

		$payload = array(
			'cart_url'   => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ),
			'item_count' => WC()->cart->get_cart_contents_count(),
			'scheduled'  => time(),
			'device_id'  => self::get_bound_device(),
		);
		set_transient( self::TRANSIENT_PREFIX . $key, $payload, DAY_IN_SECONDS );

		$timestamp = time() + ( self::get_delay_minutes() * MINUTE_IN_SECONDS );
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $key ) );
		wp_schedule_single_event( $timestamp, self::CRON_HOOK, array( $key ) );
	}

	/**
	 * Clear schedule when cart empties.
	 *
	 * @return void
	 */
	public static function maybe_clear_if_empty() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( ! WC()->cart->is_empty() ) {
			return;
		}
		$key = self::session_key();
		if ( ! $key ) {
			return;
		}
		delete_transient( self::TRANSIENT_PREFIX . $key );
		delete_transient( self::DEVICE_PREFIX . $key );
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $key ) );
	}

	/**
	 * Clear on successful order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function clear_on_order( $order_id ) {
		unset( $order_id );
		$key = self::session_key();
		if ( ! $key ) {
			return;
		}
		delete_transient( self::TRANSIENT_PREFIX . $key );
		delete_transient( self::DEVICE_PREFIX . $key );
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $key ) );
	}

	/**
	 * Cron callback: send recovery push via registry.
	 *
	 * @param string $key Session key.
	 * @return void
	 */
	public static function send_scheduled_recovery( $key ) {
		if ( ! self::is_enabled() ) {
			return;
		}
		$payload = get_transient( self::TRANSIENT_PREFIX . $key );
		delete_transient( self::TRANSIENT_PREFIX . $key );
		if ( ! is_array( $payload ) ) {
			return;
		}

		$api_key = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		if ( empty( $api_key ) ) {
			return;
		}

		$device_id = isset( $payload['device_id'] ) ? (string) $payload['device_id'] : '';
		if ( '' === $device_id ) {
			$device_id = (string) get_transient( self::DEVICE_PREFIX . $key );
		}
		if ( '' === $device_id ) {
			return;
		}

		$count = isset( $payload['item_count'] ) ? (int) $payload['item_count'] : 0;
		$cart  = isset( $payload['cart_url'] ) ? $payload['cart_url'] : home_url( '/' );
		$body  = array(
			'site_url'           => home_url( '/' ),
			'notification_type'  => 'abandoned_cart',
			'title'              => __( 'You left items in your cart', 'pressnative-apps' ),
			'excerpt'            => $count > 0
				? sprintf(
					/* translators: %d: number of cart items */
					_n( '%d item is waiting for you.', '%d items are waiting for you.', $count, 'pressnative-apps' ),
					$count
				)
				: __( 'Your cart is waiting. Tap to finish checkout.', 'pressnative-apps' ),
			'link'               => $cart,
			'post_id'            => 0,
			'post_type'          => 'product',
			'deep_link'          => 'open_cart',
			'device_id'          => $device_id,
		);

		$registry_url = PressNative_Admin::get_registry_url();
		$url          = rtrim( $registry_url, '/' ) . '/api/v1/notify/commerce';

		wp_remote_post(
			$url,
			array(
				'timeout'   => 5,
				'blocking'  => false,
				'sslverify' => true,
				'headers'   => array(
					'Content-Type'          => 'application/json',
					'X-PressNative-API-Key' => $api_key,
				),
				'body'      => wp_json_encode( $body ),
			)
		);
	}

	/**
	 * Back-in-stock push when status flips to instock.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $stock_status New status.
	 * @param mixed  $product Product object (unused).
	 * @return void
	 */
	public static function maybe_back_in_stock( $product_id, $stock_status, $product = null ) {
		unset( $product );
		if ( 'instock' !== $stock_status ) {
			return;
		}
		if ( ! self::is_back_in_stock_enabled() ) {
			return;
		}
		$api_key = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		if ( empty( $api_key ) ) {
			return;
		}

		$post = get_post( $product_id );
		if ( ! $post ) {
			return;
		}

		$body = array(
			'site_url'          => home_url( '/' ),
			'notification_type' => 'product_updates',
			'post_id'           => $product_id,
			'post_type'         => 'product',
			'title'             => get_the_title( $post ),
			'excerpt'           => __( 'Back in stock — tap to view.', 'pressnative-apps' ),
			'link'              => get_permalink( $post ),
			'stock_status'      => 'instock',
			'deep_link'         => 'open_product',
		);

		$registry_url = PressNative_Admin::get_registry_url();
		$url          = rtrim( $registry_url, '/' ) . '/api/v1/notify/commerce';

		wp_remote_post(
			$url,
			array(
				'timeout'   => 5,
				'blocking'  => false,
				'sslverify' => true,
				'headers'   => array(
					'Content-Type'          => 'application/json',
					'X-PressNative-API-Key' => $api_key,
				),
				'body'      => wp_json_encode( $body ),
			)
		);
	}

	/**
	 * Stable session key for cart recovery.
	 *
	 * @return string|null
	 */
	private static function session_key() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return null;
		}
		$id = WC()->session->get_customer_id();
		if ( ! $id ) {
			return null;
		}
		return substr( md5( (string) $id ), 0, 16 );
	}
}
