<?php
/**
 * Notifies the PressNative Registry when branding or layout options are saved.
 * Registry invalidates its site branding cache so the next app request fetches fresh data.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Registry_Notify
 */
class PressNative_Registry_Notify {

	/** Option names that trigger a config-changed notification. */
	const TRACKED_OPTIONS = array(
		// Branding options
		PressNative_Options::OPTION_APP_NAME,
		PressNative_Options::OPTION_PRIMARY_COLOR,
		PressNative_Options::OPTION_ACCENT_COLOR,
		PressNative_Options::OPTION_LOGO_ATTACHMENT,
		PressNative_Options::OPTION_BACKGROUND_COLOR,
		PressNative_Options::OPTION_BACKGROUND_IMAGE,
		PressNative_Options::OPTION_TEXT_COLOR,
		PressNative_Options::OPTION_BORDER_COLOR,
		PressNative_Options::OPTION_TILE_BACKGROUND_COLOR,
		PressNative_Options::OPTION_TILE_BACKGROUND,
		PressNative_Options::OPTION_TILE_TEXT_COLOR,
		PressNative_Options::OPTION_FONT_FAMILY,
		PressNative_Options::OPTION_BASE_FONT_SIZE,
		PressNative_Options::OPTION_APP_CATEGORIES,
		PressNative_Options::OPTION_THEME_ID,
		PressNative_Launch_Kits::OPTION_ACTIVE_KIT,
		// Layout options
		PressNative_Layout_Options::OPTION_HERO_CATEGORY_SLUG,
		PressNative_Layout_Options::OPTION_HERO_MAX_ITEMS,
		PressNative_Layout_Options::OPTION_POST_GRID_COLUMNS,
		PressNative_Layout_Options::OPTION_POST_GRID_PER_PAGE,
		PressNative_Layout_Options::OPTION_ENABLED_CATEGORIES,
		PressNative_Layout_Options::OPTION_ENABLED_COMPONENTS,
		PressNative_Layout_Options::OPTION_PRODUCT_GRID_COLUMNS,
		PressNative_Layout_Options::OPTION_PRODUCT_GRID_PER_PAGE,
		PressNative_Layout_Options::OPTION_FEATURED_PRODUCT_CAT,
		// Notification preferences
		PressNative_Options::OPTION_NOTIFICATION_PREFERENCES,
		// WooCommerce product display settings
		'pressnative_product_in_post_style',
		'pressnative_product_grid_style',
	);

	/**
	 * Bootstraps the notify hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'updated_option', array( __CLASS__, 'maybe_notify_registry' ), 10, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'maybe_notify_content_changed' ), 10, 3 );
	}

	/**
	 * When a tracked option is updated, notify the Registry to invalidate its cache.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value     New value.
	 * @return void
	 */
	public static function maybe_notify_registry( $option, $old_value, $value ) {
		if ( ! in_array( $option, self::TRACKED_OPTIONS, true ) ) {
			return;
		}

		// Always bump local settings version for cache invalidation.
		$settings_version = PressNative_Options::increment_settings_version();

		// Debug logging for WooCommerce settings
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only when debugging
		if ( in_array( $option, array( 'pressnative_product_in_post_style', 'pressnative_product_grid_style' ), true ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug only when WP_DEBUG
			error_log( "PressNative: WooCommerce setting updated - {$option}: {$old_value} -> {$value}" );
			set_transient( 'pressnative_cache_invalidated', array(
				'option'    => $option,
				'old_value' => $old_value,
				'new_value' => $value,
				'timestamp' => time(),
			), 30 );
		}

		// Only contact the Registry after the site owner has connected (API key).
		$api_key = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		if ( empty( $api_key ) ) {
			return;
		}

		$registry_url = PressNative_Admin::get_registry_url();
		$url          = rtrim( $registry_url, '/' ) . '/api/v1/notify/config-changed';
		$site_url     = home_url( '/' );
		$tags         = PressNative_Options::HUB_DIRECTORY_ENABLED ? PressNative_Options::get_app_categories() : array();
		$branding     = PressNative_Options::get_branding();

		$body = array(
			'site_url' => $site_url,
			'branding' => $branding,
		);
		if ( PressNative_Options::HUB_DIRECTORY_ENABLED ) {
			$body['tags'] = $tags;
		}

		// Include shop_config if WooCommerce is active and the updated option affects it
		if ( class_exists( 'PressNative_WooCommerce' ) && PressNative_WooCommerce::is_active() ) {
			$wc_related_options = array(
				'pressnative_product_in_post_style',
				'pressnative_product_grid_style',
			);
			if ( in_array( $option, $wc_related_options, true ) ) {
				$body['shop_config'] = PressNative_WooCommerce::get_shop_config();
			}
		}

		if ( in_array( $option, array( 'pressnative_product_in_post_style', 'pressnative_product_grid_style' ), true ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug only when WP_DEBUG
			error_log( "PressNative: Sending cache invalidation notification for {$option} to {$url}" );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug only when WP_DEBUG
			error_log( "PressNative: Settings version incremented to {$settings_version}" );
		}

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
	 * When a post is published, notify the Registry so it can increment content_version
	 * and send FCM push to users who have this site favorited and opted into push.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public static function maybe_notify_content_changed( $new_status, $old_status, $post ) {
		if ( $new_status !== 'publish' || ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'page', 'product' ), true ) ) {
			return;
		}

		// Don't send push notifications for WooCommerce system pages
		if ( self::is_woocommerce_system_page( $post ) ) {
			return;
		}

		// Editorial metabox + site notification preferences.
		if ( class_exists( 'PressNative_Editorial_Push' ) && ! PressNative_Editorial_Push::should_notify( $post ) ) {
			return;
		}

		// Only contact the Registry after the site owner has connected (API key).
		$api_key = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		if ( empty( $api_key ) ) {
			return;
		}

		$registry_url = PressNative_Admin::get_registry_url();
		$url          = rtrim( $registry_url, '/' ) . '/api/v1/notify/content-changed';
		$site_url     = home_url( '/' );

		$thumbnail_url = null;
		$thumb_id      = get_post_thumbnail_id( $post );
		if ( $thumb_id ) {
			$thumb_data = wp_get_attachment_image_src( $thumb_id, 'medium' );
			if ( ! empty( $thumb_data[0] ) ) {
				$thumbnail_url = $thumb_data[0];
			}
		}

		$notification_type = 'new_posts';
		if ( 'page' === $post->post_type ) {
			$notification_type = 'new_pages';
		} elseif ( 'product' === $post->post_type ) {
			$notification_type = 'new_products';
		}

		$categories = null;
		if ( class_exists( 'PressNative_Editorial_Push' ) ) {
			$categories = PressNative_Editorial_Push::get_audience_categories( $post );
		}

		$body = array(
			'site_url'           => $site_url,
			'post_id'            => $post->ID,
			'post_type'          => $post->post_type,
			'slug'               => $post->post_name,
			'title'              => get_the_title( $post ),
			'excerpt'            => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( $post->post_content, 30 ),
			'link'               => get_permalink( $post ),
			'thumbnail_url'      => $thumbnail_url,
			'notification_type'  => $notification_type,
		);
		if ( is_array( $categories ) ) {
			$body['categories'] = $categories;
		}

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
	 * Check if a post is a WooCommerce system page that shouldn't trigger push notifications.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool
	 */
	private static function is_woocommerce_system_page( $post ) {
		// Skip if WooCommerce isn't active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		// Get WooCommerce page IDs
		$wc_pages = array(
			'shop'      => wc_get_page_id( 'shop' ),
			'cart'      => wc_get_page_id( 'cart' ),
			'checkout'  => wc_get_page_id( 'checkout' ),
			'myaccount' => wc_get_page_id( 'myaccount' ),
		);

		// Check if this post is any of the WooCommerce system pages
		foreach ( $wc_pages as $page_id ) {
			if ( $page_id > 0 && $post->ID === $page_id ) {
				return true;
			}
		}

		// Also check for common WooCommerce page slugs in case IDs don't match
		$wc_slugs = array( 'shop', 'cart', 'checkout', 'my-account', 'woocommerce' );
		if ( in_array( $post->post_name, $wc_slugs, true ) ) {
			return true;
		}

		return false;
	}
}
