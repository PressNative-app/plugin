<?php
/**
 * Notifies the PressNative Registry when branding, layout or Hub options are saved and when
 * content is first published. Option changes are batched into one request per page load.
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
		PressNative_Options::OPTION_HUB_OPTED_IN,
		PressNative_Options::OPTION_STORE_SHORT_DESCRIPTION,
		PressNative_Options::OPTION_STORE_FULL_DESCRIPTION,
		PressNative_Options::OPTION_STORE_KEYWORDS,
		PressNative_Options::OPTION_STORE_CATEGORY,
		PressNative_Options::OPTION_STORE_PRIVACY_POLICY_URL,
		PressNative_Options::OPTION_STORE_SUPPORT_URL,
		PressNative_Options::OPTION_STORE_MARKETING_URL,
		PressNative_Options::OPTION_STORE_COPYRIGHT,
		PressNative_Options::OPTION_STORE_ICON_ATTACHMENT_ID,
		PressNative_Options::OPTION_STORE_CONTENT_RATING,
		PressNative_Options::OPTION_APP_LINK_SETTINGS,
		'pressnative_active_launch_kit',
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
	 * Tracked options that change only the Hub listing or store metadata, not what the
	 * app renders. Saving only these must not bump the apps' "pending updates" badge.
	 */
	const LISTING_ONLY_OPTIONS = array(
		PressNative_Options::OPTION_APP_CATEGORIES,
		PressNative_Options::OPTION_HUB_OPTED_IN,
		PressNative_Options::OPTION_STORE_SHORT_DESCRIPTION,
		PressNative_Options::OPTION_STORE_FULL_DESCRIPTION,
		PressNative_Options::OPTION_STORE_KEYWORDS,
		PressNative_Options::OPTION_STORE_CATEGORY,
		PressNative_Options::OPTION_STORE_PRIVACY_POLICY_URL,
		PressNative_Options::OPTION_STORE_SUPPORT_URL,
		PressNative_Options::OPTION_STORE_MARKETING_URL,
		PressNative_Options::OPTION_STORE_COPYRIGHT,
		PressNative_Options::OPTION_STORE_ICON_ATTACHMENT_ID,
		PressNative_Options::OPTION_STORE_CONTENT_RATING,
		PressNative_Options::OPTION_APP_LINK_SETTINGS,
		PressNative_Options::OPTION_NOTIFICATION_PREFERENCES,
	);

	/** WooCommerce display options whose change also sends shop_config. */
	const WC_OPTIONS = array( 'pressnative_product_in_post_style', 'pressnative_product_grid_style' );

	/**
	 * Tracked options changed during this request, flushed once on shutdown.
	 *
	 * @var array<string, true>
	 */
	private static $pending = array();

	/**
	 * Bootstraps the notify hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'updated_option', array( __CLASS__, 'on_updated_option' ), 10, 3 );
		// First save of an option with a registered default goes through add_option(), which never fires updated_option.
		add_action( 'added_option', array( __CLASS__, 'on_added_option' ), 10, 2 );
		// Runs after save_post (and the block editor's separate metabox save), so the
		// editorial metabox values are already stored when we read them.
		add_action( 'wp_after_insert_post', array( __CLASS__, 'maybe_notify_content_changed' ), 10, 4 );
	}

	/**
	 * Queues a tracked option change.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value     New value.
	 * @return void
	 */
	public static function on_updated_option( $option, $old_value, $value ) {
		if ( ! in_array( $option, self::TRACKED_OPTIONS, true ) ) {
			return;
		}
		if ( in_array( $option, self::WC_OPTIONS, true ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			set_transient(
				'pressnative_cache_invalidated',
				array(
					'option'    => $option,
					'old_value' => $old_value,
					'new_value' => $value,
					'timestamp' => time(),
				),
				30
			);
		}
		self::queue( $option );
	}

	/**
	 * Queues a tracked option saved for the first time.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return void
	 */
	public static function on_added_option( $option, $value ) {
		unset( $value );
		if ( in_array( $option, self::TRACKED_OPTIONS, true ) ) {
			self::queue( $option );
		}
	}

	/**
	 * Adds an option to the pending set and schedules one flush for the request.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	private static function queue( $option ) {
		if ( empty( self::$pending ) ) {
			add_action( 'shutdown', array( __CLASS__, 'flush' ) );
		}
		self::$pending[ $option ] = true;
	}

	/**
	 * Sends a single config-changed notification for everything saved this request.
	 *
	 * @return void
	 */
	public static function flush() {
		if ( empty( self::$pending ) ) {
			return;
		}
		$changed       = array_keys( self::$pending );
		self::$pending = array();

		$content_affecting = (bool) array_diff( $changed, self::LISTING_ONLY_OPTIONS );
		if ( $content_affecting ) {
			PressNative_Options::increment_settings_version();
		}

		// Only contact the Registry after the site owner has connected (API key).
		$api_key = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		if ( empty( $api_key ) ) {
			return;
		}

		$extra = array( 'content_affecting' => $content_affecting );
		if ( array_intersect( $changed, self::WC_OPTIONS ) && class_exists( 'PressNative_WooCommerce' ) && PressNative_WooCommerce::is_active() ) {
			$extra['shop_config'] = PressNative_WooCommerce::get_shop_config();
		}
		self::send_config_changed( $api_key, $extra );
	}

	/**
	 * Full configuration sync, sent right after connecting.
	 *
	 * @param string $api_key Site API key.
	 * @return void
	 */
	public static function sync_now( $api_key ) {
		// This full sync supersedes anything queued earlier in the request.
		self::$pending = array();
		self::send_config_changed( $api_key, array(), true );
	}

	/**
	 * Notifies the Registry when a post is first published, so it can bump content_version
	 * and push to users who follow this site. Updates to already-published posts notify
	 * only when the editor ticked "Send a notification again".
	 *
	 * @param int          $post_id     Post ID.
	 * @param WP_Post      $post        Post object.
	 * @param bool         $update      Whether this is an update.
	 * @param WP_Post|null $post_before Post before the update, null for new posts.
	 * @return void
	 */
	public static function maybe_notify_content_changed( $post_id, $post, $update, $post_before ) {
		unset( $post_id, $update );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! in_array( $post->post_type, array( 'post', 'page', 'product' ), true ) ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$was_published = $post_before instanceof WP_Post && 'publish' === $post_before->post_status;
		if ( $was_published && ! ( class_exists( 'PressNative_Editorial_Push' ) && PressNative_Editorial_Push::consume_resend_flag( $post ) ) ) {
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
			'site_url'          => $site_url,
			'post_id'           => $post->ID,
			'post_type'         => $post->post_type,
			'slug'              => $post->post_name,
			'title'             => get_the_title( $post ),
			'excerpt'           => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( $post->post_content, 30 ),
			'link'              => get_permalink( $post ),
			'thumbnail_url'     => $thumbnail_url,
			'notification_type' => $notification_type,
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

	/**
	 * Notify the Registry when config changes (e.g. after launch kit apply).
	 *
	 * @return void
	 */
	public static function notify_if_configured() {
		PressNative_Options::increment_settings_version();
		$api_key = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		if ( empty( $api_key ) ) {
			return;
		}
		self::send_config_changed( $api_key );
	}

	/**
	 * Notify registry that association files verified on this site.
	 *
	 * @param string $bundle_id Optional bundle / package id from App Links settings.
	 * @return void
	 */
	public static function notify_assoc_verified( $bundle_id = '' ) {
		$api_key = get_option( PressNative_Admin::OPTION_API_KEY, '' );
		if ( empty( $api_key ) ) {
			return;
		}
		$extra = array( 'assoc_files_verified' => true );
		if ( is_string( $bundle_id ) && $bundle_id !== '' ) {
			$extra['bundle_id'] = $bundle_id;
		}
		self::send_config_changed( $api_key, $extra, true );
	}

	/**
	 * Send config-changed webhook to the Registry.
	 *
	 * @param string $api_key      Site API key.
	 * @param array  $extra_fields Optional extra body fields.
	 * @param bool   $blocking     Whether to wait for the registry response.
	 * @return void
	 */
	private static function send_config_changed( $api_key, $extra_fields = array(), $blocking = false ) {
		$registry_url = PressNative_Admin::get_registry_url();
		$url          = rtrim( $registry_url, '/' ) . '/api/v1/notify/config-changed';
		$site_url     = home_url( '/' );
		$tags         = PressNative_Options::HUB_DIRECTORY_ENABLED ? PressNative_Options::get_app_categories() : array();
		$branding     = PressNative_Options::get_branding();

		$post_counts = wp_count_posts( 'post' );

		$body = array_merge(
			array(
				'site_url'             => $site_url,
				'branding'             => $branding,
				// Hub lists a site once it has a few published posts.
				'published_post_count' => isset( $post_counts->publish ) ? (int) $post_counts->publish : 0,
			),
			$extra_fields
		);

		if ( PressNative_Options::HUB_DIRECTORY_ENABLED ) {
			if ( ! isset( $body['tags'] ) ) {
				$body['tags'] = $tags;
			}
			if ( ! isset( $body['hub_opted_in'] ) ) {
				$body['hub_opted_in'] = PressNative_Options::is_hub_opted_in();
			}
		}
		if ( ! isset( $body['store_listing'] ) ) {
			$body['store_listing'] = PressNative_Options::get_store_listing();
		}

		wp_remote_post(
			$url,
			array(
				'timeout'   => $blocking ? 10 : 5,
				'blocking'  => (bool) $blocking,
				'sslverify' => true,
				'headers'   => array(
					'Content-Type'          => 'application/json',
					'X-PressNative-API-Key' => $api_key,
				),
				'body'      => wp_json_encode( $body ),
			)
		);
	}
}
