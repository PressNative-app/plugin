<?php
/**
 * PressNative WP Admin: Registry URL and settings.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Admin
 */
class PressNative_Admin {

	const OPTION_REGISTRY_URL    = 'pressnative_registry_url';
	const OPTION_API_KEY         = 'pressnative_api_key';
	const DEFAULT_REGISTRY_URL   = 'https://pressnative.app';
	const OPTION_SCHEMA_VERIFIED = 'pressnative_schema_verified';

	/**
	 * Hooks into admin.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 10 );
		add_action( 'admin_menu', array( __CLASS__, 'reorder_menu_by_subscription' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_stripe_portal_redirect' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_app_settings_assets' ) );
		add_action( 'admin_post_pressnative_send_push', array( __CLASS__, 'handle_send_push' ) );
		add_action( 'update_option_' . self::OPTION_API_KEY, array( __CLASS__, 'trigger_site_verification' ), 10, 2 );
		add_action( 'add_option_' . self::OPTION_API_KEY, array( __CLASS__, 'trigger_site_verification_on_add' ), 10, 2 );
		add_action( 'update_option_' . self::OPTION_API_KEY, array( __CLASS__, 'clear_subscription_cache' ), 10 );
		add_action( 'admin_post_pressnative_verify_site', array( __CLASS__, 'handle_verify_site' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_woocommerce_seed_notice' ) );
	}

	/**
	 * Clears the subscription status cache (e.g. after API key change).
	 *
	 * @return void
	 */
	public static function clear_subscription_cache() {
		delete_transient( self::SUBSCRIPTION_CACHE_KEY . '_' . md5( get_option( self::OPTION_API_KEY, '' ) ) );
	}

	/**
	 * Adds PressNative top-level menu and settings pages.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'PressNative', 'pressnative' ),
			__( 'PressNative', 'pressnative' ),
			'manage_options',
			'pressnative',
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-smartphone',
			30
		);
		add_submenu_page(
			'pressnative',
			__( 'App Settings', 'pressnative' ),
			__( 'App Settings', 'pressnative' ),
			'manage_options',
			'pressnative-app-settings',
			array( __CLASS__, 'render_app_settings_page' )
		);
		add_submenu_page(
			'pressnative',
			__( 'Layout Settings', 'pressnative' ),
			__( 'Layout Settings', 'pressnative' ),
			'manage_options',
			'pressnative-layout-settings',
			array( __CLASS__, 'render_layout_settings_page' )
		);
		add_submenu_page(
			'pressnative',
			__( 'Dashboard', 'pressnative' ),
			__( 'Dashboard', 'pressnative' ),
			'manage_options',
			'pressnative-analytics',
			array( __CLASS__, 'render_analytics_page' )
		);
		add_submenu_page(
			'pressnative',
			__( 'Push Notifications', 'pressnative' ),
			__( 'Push Notifications', 'pressnative' ),
			'manage_options',
			'pressnative-push',
			array( __CLASS__, 'render_push_page' )
		);
		add_submenu_page(
			'pressnative',
			__( 'Get Started', 'pressnative' ),
			__( 'Get Started', 'pressnative' ),
			'manage_options',
			'pressnative-growth',
			array( __CLASS__, 'render_growth_page' )
		);
	}

	/**
	 * Reorders PressNative submenu: Dashboard first when subscribed, Get Started first when not.
	 *
	 * @return void
	 */
	public static function reorder_menu_by_subscription() {
		global $submenu;
		if ( ! isset( $submenu['pressnative'] ) || ! is_array( $submenu['pressnative'] ) ) {
			return;
		}
		$sub_status = self::fetch_subscription_status();
		$has_active = $sub_status && in_array( $sub_status['billing_status'] ?? '', array( 'active', 'trial' ), true );

		$items = $submenu['pressnative'];
		$by_slug = array();
		foreach ( $items as $item ) {
			$slug = $item[2];
			$by_slug[ $slug ] = $item;
		}

		$order_subscribed   = array( 'pressnative-analytics', 'pressnative', 'pressnative-app-settings', 'pressnative-layout-settings', 'pressnative-push', 'pressnative-growth' );
		$order_unsubscribed = array( 'pressnative-growth', 'pressnative', 'pressnative-app-settings', 'pressnative-layout-settings', 'pressnative-push', 'pressnative-analytics' );
		$order              = $has_active ? $order_subscribed : $order_unsubscribed;

		$reordered = array();
		foreach ( $order as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$reordered[] = $by_slug[ $slug ];
			}
		}
		foreach ( $by_slug as $slug => $item ) {
			if ( ! in_array( $slug, $order, true ) ) {
				$reordered[] = $item;
			}
		}

		$submenu['pressnative'] = $reordered;

		if ( $has_active ) {
			$submenu['pressnative'][0][0] = __( 'Dashboard', 'pressnative' );
		} else {
			$submenu['pressnative'][0][0] = __( 'Get Started', 'pressnative' );
		}
	}

	/**
	 * Registers Registry URL setting.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'pressnative_settings',
			self::OPTION_REGISTRY_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_registry_url' ),
				'default'           => self::DEFAULT_REGISTRY_URL,
			)
		);
		register_setting(
			'pressnative_settings',
			self::OPTION_API_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// App Settings (branding)
		register_setting(
			'pressnative_app_settings',
			PressNative_Themes::OPTION_THEME_ID,
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $v ) {
					$themes = PressNative_Themes::get_themes();
					return isset( $themes[ $v ] ) ? $v : PressNative_Themes::THEME_CUSTOM;
				},
				'default'           => 'editorial',
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_APP_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => PressNative_Options::DEFAULT_APP_NAME,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_PRIMARY_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_primary_color' ),
				'default'           => PressNative_Options::DEFAULT_PRIMARY_COLOR,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_ACCENT_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_hex' ),
				'default'           => PressNative_Options::DEFAULT_ACCENT_COLOR,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_LOGO_ATTACHMENT,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_BACKGROUND_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_hex' ),
				'default'           => PressNative_Options::DEFAULT_BACKGROUND_COLOR,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_BACKGROUND_IMAGE,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_TILE_BACKGROUND_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_hex' ),
				'default'           => PressNative_Options::DEFAULT_TILE_BACKGROUND_COLOR,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_TILE_BACKGROUND,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_TEXT_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_text_color' ),
				'default'           => PressNative_Options::DEFAULT_TEXT_COLOR,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_TILE_TEXT_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_hex' ),
				'default'           => PressNative_Options::DEFAULT_TILE_TEXT_COLOR,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_FONT_FAMILY,
			array(
				'type'              => 'string',
				'sanitize_callback' => function ( $v ) {
					$allowed = array( 'sans-serif', 'serif', 'monospace' );
					return in_array( $v, $allowed, true ) ? $v : PressNative_Options::DEFAULT_FONT_FAMILY;
				},
				'default'           => PressNative_Options::DEFAULT_FONT_FAMILY,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_BASE_FONT_SIZE,
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					$v = absint( $v );
					return max( 12, min( 24, $v ) );
				},
				'default'           => PressNative_Options::DEFAULT_BASE_FONT_SIZE,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_APP_CATEGORIES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'PressNative_Options', 'sanitize_app_categories' ),
				'default'           => PressNative_Options::DEFAULT_APP_CATEGORIES,
			)
		);
		register_setting(
			'pressnative_app_settings',
			PressNative_Options::OPTION_ADMOB_BANNER_UNIT_ID,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => PressNative_Options::DEFAULT_ADMOB_BANNER_UNIT_ID,
			)
		);

		// Layout Settings
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_HERO_CATEGORY_SLUG,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => PressNative_Layout_Options::DEFAULT_HERO_CATEGORY_SLUG,
			)
		);
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_HERO_MAX_ITEMS,
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					$v = absint( $v );
					return max( 1, min( 10, $v ) );
				},
				'default'           => PressNative_Layout_Options::DEFAULT_HERO_MAX_ITEMS,
			)
		);
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_POST_GRID_COLUMNS,
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					$v = absint( $v );
					return max( 1, min( 4, $v ) );
				},
				'default'           => PressNative_Layout_Options::DEFAULT_POST_GRID_COLUMNS,
			)
		);
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_POST_GRID_PER_PAGE,
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					$v = absint( $v );
					return max( 1, min( 50, $v ) );
				},
				'default'           => PressNative_Layout_Options::DEFAULT_POST_GRID_PER_PAGE,
			)
		);
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_ENABLED_CATEGORIES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_category_ids' ),
				'default'           => array(),
			)
		);
		register_setting(
			'pressnative_layout_settings',
			PressNative_Layout_Options::OPTION_ENABLED_COMPONENTS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_component_ids' ),
				'default'           => PressNative_Layout_Options::COMPONENT_IDS,
			)
		);
	}

	/**
	 * Sanitizes category IDs array.
	 *
	 * @param mixed $value Raw value.
	 * @return int[]
	 */
	public static function sanitize_category_ids( $value ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return array();
		}
		$ids = array_map( 'absint', $value );
		$ids = array_filter( $ids );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Sanitizes component IDs (comma-separated string or array).
	 *
	 * @param mixed $value Raw value (string "id1,id2" or array).
	 * @return string[]
	 */
	public static function sanitize_component_ids( $value ) {
		$ids = array();
		if ( is_string( $value ) && '' !== trim( $value ) ) {
			$ids = array_map( 'trim', explode( ',', $value ) );
		} elseif ( is_array( $value ) ) {
			$ids = array_map( 'sanitize_text_field', $value );
		}
		$valid = array_values( array_intersect( array_filter( $ids ), PressNative_Layout_Options::COMPONENT_IDS ) );
		return empty( $valid ) ? PressNative_Layout_Options::COMPONENT_IDS : $valid;
	}

	/**
	 * Sanitizes Registry URL (ensure no trailing slash, valid URL).
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	public static function sanitize_registry_url( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return self::DEFAULT_REGISTRY_URL;
		}
		$value = rtrim( $value, '/' );
		if ( ! preg_match( '#^https?://#', $value ) ) {
			return 'http://' . $value;
		}
		return $value;
	}

	const SUBSCRIPTION_CACHE_KEY = 'pressnative_subscription_status';
	const SUBSCRIPTION_CACHE_TTL = 300;

	/**
	 * Fetches the subscription status from the Registry via Stripe API.
	 * Cached for 5 minutes to avoid Registry calls on every admin page load.
	 *
	 * @return array|null Subscription data or null on failure.
	 */
	public static function fetch_subscription_status() {
		$api_key      = get_option( self::OPTION_API_KEY, '' );
		$registry_url = self::get_registry_url();
		if ( empty( $api_key ) || empty( $registry_url ) ) {
			return null;
		}

		$cache_key = self::SUBSCRIPTION_CACHE_KEY . '_' . md5( $api_key );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url      = rtrim( $registry_url, '/' ) . '/api/stripe/subscription-status';
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'X-PressNative-API-Key' => $api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( is_array( $data ) ) {
			set_transient( $cache_key, $data, self::SUBSCRIPTION_CACHE_TTL );
			return $data;
		}
		return null;
	}

	/**
	 * Creates a Stripe Customer Portal session URL.
	 *
	 * @return string|null Portal URL or null on failure.
	 */
	public static function get_stripe_portal_url() {
		$api_key      = get_option( self::OPTION_API_KEY, '' );
		$registry_url = self::get_registry_url();
		if ( empty( $api_key ) || empty( $registry_url ) ) {
			return null;
		}

		$url      = rtrim( $registry_url, '/' ) . '/api/stripe/portal';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'         => 'application/json',
					'X-PressNative-API-Key' => $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'return_url' => admin_url( 'admin.php?page=pressnative' ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		return isset( $data['url'] ) ? $data['url'] : null;
	}

	/**
	 * Renders the PressNative settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$registry_url   = get_option( self::OPTION_REGISTRY_URL, self::DEFAULT_REGISTRY_URL );
		$api_key        = get_option( self::OPTION_API_KEY, '' );
		$verified       = get_option( self::OPTION_SCHEMA_VERIFIED, '' );
		$site_verified  = get_option( 'pressnative_site_verified', '' );
		$sub_status     = ! empty( $api_key ) ? self::fetch_subscription_status() : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PressNative', 'pressnative' ); ?></h1>

			<?php if ( $sub_status ) : ?>
			<div class="pressnative-subscription-card" style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo 'active' === $sub_status['billing_status'] ? '#00a32a' : ( 'trial' === $sub_status['billing_status'] ? '#dba617' : '#d63638' ); ?>;border-radius:4px;padding:16px 20px;margin-bottom:20px;max-width:700px;">
				<h2 style="margin:0 0 12px;font-size:1.1em;">
					<?php esc_html_e( 'Subscription Status', 'pressnative' ); ?>
				</h2>
				<table style="border-collapse:collapse;width:100%;">
					<tr>
						<td style="padding:6px 12px 6px 0;color:#50575e;width:140px;"><strong><?php esc_html_e( 'Plan', 'pressnative' ); ?></strong></td>
						<td style="padding:6px 0;">
							<span style="display:inline-block;padding:2px 10px;border-radius:4px;background:#f0f0f1;font-weight:600;text-transform:capitalize;">
								<?php echo esc_html( $sub_status['plan'] ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<td style="padding:6px 12px 6px 0;color:#50575e;"><strong><?php esc_html_e( 'Status', 'pressnative' ); ?></strong></td>
						<td style="padding:6px 0;">
							<?php
							$status_label = $sub_status['billing_status'];
							$status_color = '#00a32a';
							if ( 'trial' === $status_label ) {
								$status_color = '#dba617';
							} elseif ( 'past_due' === $status_label ) {
								$status_color = '#dba617';
							} elseif ( 'canceled' === $status_label ) {
								$status_color = '#d63638';
							}
							?>
							<span style="display:inline-block;padding:2px 10px;border-radius:4px;background:<?php echo esc_attr( $status_color ); ?>;color:#fff;font-weight:600;text-transform:capitalize;">
								<?php echo esc_html( str_replace( '_', ' ', $status_label ) ); ?>
							</span>
						</td>
					</tr>
					<?php if ( ! empty( $sub_status['stripe_subscription'] ) ) : ?>
						<?php $stripe_sub = $sub_status['stripe_subscription']; ?>
						<?php if ( ! empty( $stripe_sub['plan_name'] ) ) : ?>
						<tr>
							<td style="padding:6px 12px 6px 0;color:#50575e;"><strong><?php esc_html_e( 'Stripe Plan', 'pressnative' ); ?></strong></td>
							<td style="padding:6px 0;"><?php echo esc_html( $stripe_sub['plan_name'] ); ?>
								<?php if ( ! empty( $stripe_sub['plan_amount'] ) ) : ?>
									— <?php echo esc_html( '$' . number_format( $stripe_sub['plan_amount'] / 100, 2 ) . '/' . ( $stripe_sub['plan_interval'] ?? 'month' ) ); ?>
								<?php endif; ?>
							</td>
						</tr>
						<?php endif; ?>
						<?php if ( ! empty( $stripe_sub['current_period_end'] ) ) : ?>
						<tr>
							<td style="padding:6px 12px 6px 0;color:#50575e;"><strong><?php esc_html_e( 'Renews On', 'pressnative' ); ?></strong></td>
							<td style="padding:6px 0;">
								<?php echo esc_html( gmdate( 'F j, Y', $stripe_sub['current_period_end'] ) ); ?>
								<?php if ( ! empty( $stripe_sub['cancel_at_period_end'] ) ) : ?>
									<span style="color:#d63638;font-weight:600;margin-left:8px;"><?php esc_html_e( '(Cancels at period end)', 'pressnative' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endif; ?>
					<?php endif; ?>
				</table>
				<?php if ( $sub_status['has_stripe'] ) : ?>
					<p style="margin:12px 0 0;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pressnative&pressnative_portal=1' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Manage Subscription', 'pressnative' ); ?>
						</a>
						<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Opens the Stripe billing portal to update payment, change plan, or cancel.', 'pressnative' ); ?></span>
					</p>
				<?php endif; ?>
			</div>
		<?php elseif ( ! empty( $api_key ) ) : ?>
		<div class="notice notice-warning" style="max-width:700px;">
			<p><?php esc_html_e( 'Could not fetch subscription status. Ensure the Registry is running and your API key is valid.', 'pressnative' ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $api_key ) && ! empty( $site_verified ) ) : ?>
		<div class="notice notice-success" style="max-width:700px;">
			<p>
				<strong><?php esc_html_e( 'Site Verified', 'pressnative' ); ?></strong> —
				<?php echo esc_html( sprintf( __( 'WordPress admin access confirmed on %s.', 'pressnative' ), $site_verified ) ); ?>
			</p>
		</div>
		<?php elseif ( ! empty( $api_key ) && empty( $site_verified ) ) : ?>
		<div class="notice notice-warning" style="max-width:700px;">
			<p>
				<?php esc_html_e( 'Site verification pending. Click the button below to verify your WordPress admin access with the Registry.', 'pressnative' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pressnative_verify_site' ), 'pressnative_verify_site' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Verify Now', 'pressnative' ); ?>
				</a>
			</p>
			<?php
			$verify_error = get_transient( 'pressnative_verify_error' );
			if ( $verify_error ) :
				delete_transient( 'pressnative_verify_error' );
			?>
				<p style="color:#d63638;"><strong><?php esc_html_e( 'Last verification error:', 'pressnative' ); ?></strong> <?php echo esc_html( $verify_error ); ?></p>
			<?php endif; ?>
		</div>
		<?php endif; ?>

			<?php if ( ! empty( $_GET['pressnative_portal_error'] ) ) : ?>
			<div class="notice notice-error is-dismissible" style="max-width:700px;">
				<p><?php esc_html_e( 'Could not open the subscription management portal. Ensure your API key is valid and the Registry is running.', 'pressnative' ); ?></p>
			</div>
			<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'pressnative_settings' ); ?>
			<table class="form-table" role="presentation">
				<?php if ( self::is_localhost() ) : ?>
				<tr>
					<th scope="row">
						<label for="pressnative_registry_url"><?php esc_html_e( 'Registry URL', 'pressnative' ); ?></label>
					</th>
					<td>
						<input type="url"
							   id="pressnative_registry_url"
							   name="<?php echo esc_attr( self::OPTION_REGISTRY_URL ); ?>"
							   value="<?php echo esc_attr( $registry_url ); ?>"
							   class="regular-text"
							   placeholder="http://localhost:3000"/>
						<p class="description">
							<?php esc_html_e( 'Local development: Base URL of your local PressNative Registry.', 'pressnative' ); ?>
							<?php if ( $verified ) : ?>
								<br><strong><?php esc_html_e( 'Last verified:', 'pressnative' ); ?></strong> <?php echo esc_html( $verified ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
						<th scope="row">
							<label for="pressnative_api_key"><?php esc_html_e( 'API Key', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="password"
								   id="pressnative_api_key"
								   name="<?php echo esc_attr( self::OPTION_API_KEY ); ?>"
								   value="<?php echo esc_attr( $api_key ); ?>"
								   class="regular-text"
								   autocomplete="off"
								   placeholder="pn_..."/>
							<p class="description">
								<?php esc_html_e( 'Issued by PressNative after you start your subscription (sent via email). Required for analytics: events are stored on our servers and the dashboard queries them.', 'pressnative' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueues scripts and styles for App Settings page (color picker, media library).
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_app_settings_assets( $hook_suffix ) {
		$app_settings    = ( $hook_suffix === 'pressnative_page_pressnative-app-settings' );
		$layout_settings = ( $hook_suffix === 'pressnative_page_pressnative-layout-settings' );
		$analytics_page  = ( $hook_suffix === 'pressnative_page_pressnative-analytics' );
		$growth_page     = ( $hook_suffix === 'pressnative_page_pressnative-growth' );
		$push_page       = ( $hook_suffix === 'pressnative_page_pressnative-push' );
		if ( ! $app_settings && ! $layout_settings && ! $analytics_page && ! $growth_page && ! $push_page ) {
			return;
		}

		// Live preview assets (both pages).
		$preview_css = PRESSNATIVE_PLUGIN_DIR . 'assets/css/preview.css';
		$preview_js  = PRESSNATIVE_PLUGIN_DIR . 'assets/js/preview.js';
		if ( file_exists( $preview_css ) ) {
			wp_enqueue_style(
				'pressnative-preview',
				plugins_url( 'assets/css/preview.css', PRESSNATIVE_PLUGIN_DIR . 'pressnative.php' ),
				array(),
				filemtime( $preview_css )
			);
		}
		if ( file_exists( $preview_js ) ) {
			wp_enqueue_script(
				'pressnative-preview',
				plugins_url( 'assets/js/preview.js', PRESSNATIVE_PLUGIN_DIR . 'pressnative.php' ),
				array(),
				filemtime( $preview_js ),
				true
			);
			wp_localize_script(
				'pressnative-preview',
				'pressnativePreview',
				array(
					'restUrl' => rest_url(),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'woocommerceActive' => class_exists( 'PressNative_WooCommerce' ) && PressNative_WooCommerce::is_active(),
				)
			);
		}

		if ( $layout_settings ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_add_inline_script(
				'jquery-ui-sortable',
				self::get_component_order_script(),
				'after'
			);
		}
		if ( $analytics_page ) {
			wp_enqueue_script(
				'chartjs',
				plugins_url( 'assets/js/vendor/chart.umd.min.js', PRESSNATIVE_PLUGIN_DIR . 'pressnative.php' ),
				array(),
				'4.4.1',
				true
			);
			$analytics_js = PRESSNATIVE_PLUGIN_DIR . 'assets/js/analytics-dashboard.js';
			$analytics_css = PRESSNATIVE_PLUGIN_DIR . 'assets/css/analytics-dashboard.css';
			if ( file_exists( $analytics_js ) ) {
				wp_enqueue_script(
					'pressnative-analytics',
					plugins_url( 'assets/js/analytics-dashboard.js', PRESSNATIVE_PLUGIN_DIR . 'pressnative.php' ),
					array( 'chartjs' ),
					filemtime( $analytics_js ),
					true
				);
				wp_localize_script(
					'pressnative-analytics',
					'pressnativeAnalytics',
					array(
						'restUrl'       => rest_url( 'pressnative/v1' ),
						'nonce'         => wp_create_nonce( 'wp_rest' ),
						'editPostUrl'   => admin_url( 'post.php' ),
					)
				);
			}
			if ( file_exists( $analytics_css ) ) {
				wp_enqueue_style(
					'pressnative-analytics',
					plugins_url( 'assets/css/analytics-dashboard.css', PRESSNATIVE_PLUGIN_DIR . 'pressnative.php' ),
					array(),
					filemtime( $analytics_css )
				);
			}
		}
		if ( $app_settings ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_enqueue_media();
			wp_add_inline_script(
				'wp-color-picker',
				'jQuery(function($){$(".pressnative-color-picker").wpColorPicker();});'
			);
			wp_add_inline_script(
				'wp-color-picker',
				self::get_logo_upload_script() . "\n" . self::get_media_upload_script() . "\n" . self::get_theme_card_script() . "\n" . self::get_app_categories_script(),
				'after'
			);
		}
	}

	/**
	 * Inline script for component order sortable and hidden input sync.
	 *
	 * @return string
	 */
	private static function get_component_order_script() {
		return "
		jQuery(function($){
			var \$list = $('#pressnative-component-order');
			var \$hidden = $('#pressnative-component-order-value');
			function updateOrder() {
				var order = [];
				\$list.find('li').each(function(){
					var cb = $(this).find('input[type=checkbox]');
					if (cb.length && cb.prop('checked')) {
						order.push(cb.val());
					}
				});
				\$hidden.val(order.join(','));
				if (typeof window.pressnativePreviewRefresh === 'function') {
					window.pressnativePreviewRefresh();
				}
			}
			\$list.sortable({
				handle: '.pressnative-drag-handle',
				placeholder: 'pressnative-sortable-placeholder',
				items: '> li'
			});
			\$list.on('sortupdate', updateOrder);
			\$list.on('change', 'input[type=checkbox]', updateOrder);
		});
		";
	}

	/**
	 * Inline script for logo upload (WP Media Library).
	 *
	 * @return string
	 */
	private static function get_logo_upload_script() {
		return "
		jQuery(function($){
			var frame;
			$('#pressnative_logo_select').on('click', function(e){
				e.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({
					title: 'Select or upload logo',
					library: { type: 'image' },
					button: { text: 'Use this image' },
					multiple: false
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#pressnative_logo_attachment_id').val(att.id);
					$('#pressnative_logo_preview').html('<img src=\"'+att.url+'\" style=\"max-height:60px;\" />');
					if (typeof window.pressnativePreviewRefresh === 'function') { window.pressnativePreviewRefresh(); }
				});
				frame.open();
			});
			$('#pressnative_logo_remove').on('click', function(e){
				e.preventDefault();
				$('#pressnative_logo_attachment_id').val('');
				$('#pressnative_logo_preview').html('');
				if (typeof window.pressnativePreviewRefresh === 'function') { window.pressnativePreviewRefresh(); }
			});
		});
		";
	}

	/**
	 * Inline script for generic media upload buttons (background image, tile background).
	 *
	 * @return string
	 */
	private static function get_media_upload_script() {
		return "
		jQuery(function($){
			var frames = {};
			$('.pressnative-media-upload').on('click', function(e){
				e.preventDefault();
				var \$btn = $(this);
				var target = \$btn.attr('data-target');
				var preview = \$btn.attr('data-preview');
				var title = \$btn.attr('data-title') || 'Select or upload image';
				var buttonText = \$btn.attr('data-button') || 'Use this image';
				
				if (frames[target]) { 
					frames[target].open(); 
					return; 
				}
				
				frames[target] = wp.media({
					title: title,
					library: { type: 'image' },
					button: { text: buttonText },
					multiple: false
				});
				
				frames[target].on('select', function(){
					var att = frames[target].state().get('selection').first().toJSON();
					$(target).val(att.id);
					if (preview) {
						$(preview).attr('src', att.url).show();
						\$btn.siblings('.pressnative-media-remove').show();
					}
					if (typeof window.pressnativePreviewRefresh === 'function') { 
						window.pressnativePreviewRefresh(); 
					}
				});
				
				frames[target].open();
			});
			
			$('.pressnative-media-remove').on('click', function(e){
				e.preventDefault();
				var \$btn = $(this);
				var target = \$btn.attr('data-target');
				var preview = \$btn.attr('data-preview');
				
				if (target) $(target).val('');
				if (preview) $(preview).attr('src', '').hide();
				\$btn.hide();
				
				if (typeof window.pressnativePreviewRefresh === 'function') { 
					window.pressnativePreviewRefresh(); 
				}
			});
		});
		";
	}

	/**
	 * Inline script for theme card clicks (ensures radios work when label/input association fails).
	 *
	 * @return string
	 */
	private static function get_theme_card_script() {
		return "
		jQuery(function($){
			$('.pressnative-theme-card').on('click', function(e){
				var radio = $(this).find('input[type=radio]');
				if (radio.length) {
					radio.prop('checked', true);
					$('.pressnative-theme-card').css('border', '2px solid #ddd');
					$(this).css('border', '2px solid #2271b1');
				}
			});
		});
		";
	}

	/**
	 * Inline script for App Categories tag pills (pill display, add via Enter/comma, remove).
	 *
	 * @return string
	 */
	private static function get_app_categories_script() {
		return "
		jQuery(function($){
			var \$hidden = $('#pressnative_app_categories');
			var \$textInput = $('#pressnative_app_categories_input');
			var \$wrapper = $('.pressnative-tag-input-wrapper');
			var max = parseInt(\$hidden.attr('data-max') || 5, 10);

			function getTags() {
				var val = (\$hidden.val() || '').trim();
				return val ? val.split(',').map(function(t){ return t.trim(); }).filter(Boolean) : [];
			}

			function setTags(tags) {
				tags = tags.slice(0, max);
				\$hidden.val(tags.join(','));
				\$wrapper.find('.pressnative-tag-pill-display').remove();
				tags.forEach(function(tag) {
					var safeTag = $('<span/>').text(tag).html();
					var safeAttr = tag.replace(/\"/g, '&quot;');
					var pill = '<span class=\"pressnative-tag-pill-display\" style=\"display:inline-flex;align-items:center;gap:4px;background:#2271b1;color:#fff;border-radius:16px;padding:4px 8px 4px 12px;font-size:13px;cursor:default;\">' +
						safeTag +
						' <span class=\"pressnative-tag-remove\" data-tag=\"' + safeAttr + '\" role=\"button\" tabindex=\"0\" style=\"cursor:pointer;padding:2px 4px;line-height:1;opacity:0.85;font-size:15px;font-weight:bold;\" aria-label=\"Remove tag\">&times;</span>' +
						'</span>';
					$(pill).insertBefore(\$textInput);
				});
				updateSuggestedPills();
			}

			function updateSuggestedPills() {
				var tags = getTags();
				$('.pressnative-tag-pill').each(function() {
					var tag = String($(this).attr('data-tag') || '');
					var active = tags.indexOf(tag) >= 0;
					$(this).toggleClass('active', active).css({
						'background': active ? '#2271b1' : '',
						'color': active ? '#fff' : '',
						'border-color': active ? '#2271b1' : '#8c8f94'
					});
				});
			}

			$('.pressnative-tag-pill').on('click', function() {
				var tag = String($(this).attr('data-tag') || '');
				var tags = getTags();
				var idx = tags.indexOf(tag);
				if (idx >= 0) {
					tags.splice(idx, 1);
				} else if (tags.length < max) {
					tags.push(tag);
				}
				setTags(tags);
			});

			\$textInput.on('keydown', function(e) {
				if (e.key === 'Enter' || e.key === ',') {
					e.preventDefault();
					var val = ($(this).val() || '').trim();
					if (val) {
						var tags = getTags();
						if (tags.indexOf(val) < 0 && tags.length < max) {
							tags.push(val);
							setTags(tags);
						}
						$(this).val('');
					}
				} else if (e.key === 'Backspace' && !$(this).val()) {
					var tags = getTags();
					if (tags.length) {
						tags.pop();
						setTags(tags);
					}
				}
			});

			\$textInput.on('blur', function() {
				var val = ($(this).val() || '').trim();
				if (val) {
					var tags = getTags();
					if (tags.indexOf(val) < 0 && tags.length < max) {
						tags.push(val);
						setTags(tags);
					}
					$(this).val('');
				}
			});

			$(document).on('click', '.pressnative-tag-remove', function(e) {
				e.preventDefault();
				e.stopPropagation();
				var tag = String($(this).attr('data-tag') || '');
				if (!tag) return;
				var tags = getTags().filter(function(t) { return t !== tag; });
				setTags(tags);
			});

			setTags(getTags());
		});
		";
	}

	/**
	 * Renders the App Settings page (branding: app name, colors, logo).
	 *
	 * @return void
	 */
	public static function render_app_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$app_name        = get_option( PressNative_Options::OPTION_APP_NAME, PressNative_Options::DEFAULT_APP_NAME );
		$primary_color   = get_option( PressNative_Options::OPTION_PRIMARY_COLOR, PressNative_Options::DEFAULT_PRIMARY_COLOR );
		$accent_color    = get_option( PressNative_Options::OPTION_ACCENT_COLOR, PressNative_Options::DEFAULT_ACCENT_COLOR );
		$logo_attachment = (int) get_option( PressNative_Options::OPTION_LOGO_ATTACHMENT, 0 );
		$logo_preview    = '';
		if ( $logo_attachment > 0 ) {
			$url = wp_get_attachment_image_url( $logo_attachment, 'thumbnail' );
			if ( $url ) {
				$logo_preview = '<img src="' . esc_url( $url ) . '" style="max-height:60px;" />';
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'App Settings', 'pressnative' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Branding shown in the PressNative mobile app (toolbar title, logo, theme colors).', 'pressnative' ); ?></p>
			<div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;">
				<div style="flex:1;min-width:320px;">
			<form method="post" action="options.php" class="pressnative-settings-form">
				<?php settings_fields( 'pressnative_app_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="pressnative_theme_id"><?php esc_html_e( 'Theme', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$current_theme = PressNative_Themes::get_selected_theme_id();
							$themes       = PressNative_Themes::get_themes();
							?>
							<div class="pressnative-theme-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:12px;">
								<?php foreach ( $themes as $id => $t ) :
									$active = $current_theme === $id ? '2px solid #2271b1' : '2px solid #ddd';
									if ( $id === PressNative_Themes::THEME_CUSTOM ) {
										$bg = '#f6f7f7';
										$accent = '#999';
									} else {
										$bg = isset( $t['theme']['background_color'] ) ? esc_attr( $t['theme']['background_color'] ) : '#fff';
										$accent = isset( $t['theme']['accent_color'] ) ? esc_attr( $t['theme']['accent_color'] ) : '#34c759';
									}
									?>
									<label class="pressnative-theme-card" style="cursor:pointer;border:<?php echo $active; ?>;border-radius:8px;overflow:hidden;transition:border-color .15s;position:relative;display:block;">
										<input type="radio" name="<?php echo esc_attr( PressNative_Themes::OPTION_THEME_ID ); ?>" value="<?php echo esc_attr( $id ); ?>" <?php checked( $current_theme, $id ); ?> style="position:absolute;top:0;left:0;right:0;bottom:0;width:100%;height:100%;margin:0;opacity:0;cursor:pointer;">
										<div style="background:<?php echo $bg; ?>;padding:10px;min-height:70px;pointer-events:none;">
											<div style="width:20px;height:20px;border-radius:4px;background:<?php echo $accent; ?>;margin-bottom:6px;"></div>
											<span style="font-size:12px;font-weight:600;color:inherit;"><?php echo esc_html( $t['name'] ); ?></span>
										</div>
									</label>
								<?php endforeach; ?>
							</div>
							<p class="description">
								<?php
								$desc = isset( $themes[ $current_theme ]['description'] ) ? $themes[ $current_theme ]['description'] : '';
								echo $desc ? esc_html( $desc ) : esc_html__( 'Choose a preset theme or Custom to use your own colors below.', 'pressnative' );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_app_name"><?php esc_html_e( 'App Name', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_app_name"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_APP_NAME ); ?>"
								   value="<?php echo esc_attr( $app_name ); ?>"
								   class="regular-text"
								   placeholder="<?php echo esc_attr( PressNative_Options::DEFAULT_APP_NAME ); ?>"/>
							<p class="description"><?php esc_html_e( 'Title shown in the app toolbar.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_primary_color"><?php esc_html_e( 'Primary Color', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_primary_color"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_PRIMARY_COLOR ); ?>"
								   value="<?php echo esc_attr( $primary_color ); ?>"
								   class="pressnative-color-picker"/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_accent_color"><?php esc_html_e( 'Accent Color', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_accent_color"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_ACCENT_COLOR ); ?>"
								   value="<?php echo esc_attr( $accent_color ); ?>"
								   class="pressnative-color-picker"/>
							<p class="description"><?php esc_html_e( 'Buttons, links, highlights.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_background_color"><?php esc_html_e( 'App Background Color', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$bg_color = get_option( PressNative_Options::OPTION_BACKGROUND_COLOR, PressNative_Options::DEFAULT_BACKGROUND_COLOR );
							?>
							<input type="text"
								   id="pressnative_background_color"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_BACKGROUND_COLOR ); ?>"
								   value="<?php echo esc_attr( $bg_color ); ?>"
								   class="pressnative-color-picker"/>
							<p class="description"><?php esc_html_e( 'Main app background color. Used when no background image is set.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'App Background Image', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$bg_image_id  = (int) get_option( PressNative_Options::OPTION_BACKGROUND_IMAGE, 0 );
							$bg_image_url = $bg_image_id ? wp_get_attachment_image_url( $bg_image_id, 'thumbnail' ) : '';
							?>
							<input type="hidden"
								   id="pressnative_background_image"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_BACKGROUND_IMAGE ); ?>"
								   value="<?php echo esc_attr( $bg_image_id ); ?>"/>
							<div style="display:flex;align-items:center;gap:8px;">
								<?php if ( $bg_image_url ) : ?>
									<img id="pressnative-bg-image-preview"
										 src="<?php echo esc_url( $bg_image_url ); ?>"
										 style="max-width:80px;max-height:80px;border:1px solid #ccc;border-radius:4px;"/>
								<?php else : ?>
									<img id="pressnative-bg-image-preview"
										 src=""
										 style="max-width:80px;max-height:80px;border:1px solid #ccc;border-radius:4px;display:none;"/>
								<?php endif; ?>
								<button type="button"
										class="button pressnative-media-upload"
										data-target="#pressnative_background_image"
										data-preview="#pressnative-bg-image-preview"
										data-title="<?php esc_attr_e( 'Select app background image', 'pressnative' ); ?>"
										data-button="<?php esc_attr_e( 'Use image', 'pressnative' ); ?>">
									<?php esc_html_e( 'Select or upload image', 'pressnative' ); ?>
								</button>
								<button type="button"
										class="button pressnative-media-remove"
										data-target="#pressnative_background_image"
										data-preview="#pressnative-bg-image-preview"
										<?php echo $bg_image_id ? '' : 'style="display:none;"'; ?>>
									<?php esc_html_e( 'Remove', 'pressnative' ); ?>
								</button>
							</div>
							<p class="description"><?php esc_html_e( 'Optional background image for the app. Overrides background color when set.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_tile_background_color"><?php esc_html_e( 'Tile Background Color', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$tile_bg_color = get_option( PressNative_Options::OPTION_TILE_BACKGROUND_COLOR, PressNative_Options::DEFAULT_TILE_BACKGROUND_COLOR );
							?>
							<input type="text"
								   id="pressnative_tile_background_color"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_TILE_BACKGROUND_COLOR ); ?>"
								   value="<?php echo esc_attr( $tile_bg_color ); ?>"
								   class="pressnative-color-picker"/>
							<p class="description"><?php esc_html_e( 'Background color for content tiles/cards. Used when no tile background image is set.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Tile Background Image', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$tile_bg_id  = (int) get_option( PressNative_Options::OPTION_TILE_BACKGROUND, 0 );
							$tile_bg_url = $tile_bg_id ? wp_get_attachment_image_url( $tile_bg_id, 'thumbnail' ) : '';
							?>
							<input type="hidden"
								   id="pressnative_tile_background"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_TILE_BACKGROUND ); ?>"
								   value="<?php echo esc_attr( $tile_bg_id ); ?>"/>
							<div style="display:flex;align-items:center;gap:8px;">
								<?php if ( $tile_bg_url ) : ?>
									<img id="pressnative-tile-bg-preview"
										 src="<?php echo esc_url( $tile_bg_url ); ?>"
										 style="max-width:80px;max-height:80px;border:1px solid #ccc;border-radius:4px;"/>
								<?php else : ?>
									<img id="pressnative-tile-bg-preview"
										 src=""
										 style="max-width:80px;max-height:80px;border:1px solid #ccc;border-radius:4px;display:none;"/>
								<?php endif; ?>
								<button type="button"
										class="button pressnative-media-upload"
										data-target="#pressnative_tile_background"
										data-preview="#pressnative-tile-bg-preview"
										data-title="<?php esc_attr_e( 'Select tile background image', 'pressnative' ); ?>"
										data-button="<?php esc_attr_e( 'Use image', 'pressnative' ); ?>">
									<?php esc_html_e( 'Select or upload image', 'pressnative' ); ?>
								</button>
								<button type="button"
										class="button pressnative-media-remove"
										data-target="#pressnative_tile_background"
										data-preview="#pressnative-tile-bg-preview"
										<?php echo $tile_bg_id ? '' : 'style="display:none;"'; ?>>
									<?php esc_html_e( 'Remove', 'pressnative' ); ?>
								</button>
							</div>
							<p class="description"><?php esc_html_e( 'Optional background image for content tiles/cards. Overrides tile background color when set.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_tile_text_color"><?php esc_html_e( 'Tile Text Color', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$tile_text_color = get_option( PressNative_Options::OPTION_TILE_TEXT_COLOR, PressNative_Options::DEFAULT_TILE_TEXT_COLOR );
							?>
							<input type="text"
								   id="pressnative_tile_text_color"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_TILE_TEXT_COLOR ); ?>"
								   value="<?php echo esc_attr( $tile_text_color ); ?>"
								   class="pressnative-color-picker"/>
							<p class="description"><?php esc_html_e( 'Text color for content on tiles/cards.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_text_color"><?php esc_html_e( 'Text Color', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$text_color = get_option( PressNative_Options::OPTION_TEXT_COLOR, PressNative_Options::DEFAULT_TEXT_COLOR );
							?>
							<input type="text"
								   id="pressnative_text_color"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_TEXT_COLOR ); ?>"
								   value="<?php echo esc_attr( $text_color ); ?>"
								   class="pressnative-color-picker"/>
							<p class="description"><?php esc_html_e( 'Primary text color.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_font_family"><?php esc_html_e( 'Font Family', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$font_family = get_option( PressNative_Options::OPTION_FONT_FAMILY, PressNative_Options::DEFAULT_FONT_FAMILY );
							?>
							<select id="pressnative_font_family"
									name="<?php echo esc_attr( PressNative_Options::OPTION_FONT_FAMILY ); ?>">
								<option value="sans-serif" <?php selected( $font_family, 'sans-serif' ); ?>><?php esc_html_e( 'Sans-serif (default)', 'pressnative' ); ?></option>
								<option value="serif" <?php selected( $font_family, 'serif' ); ?>><?php esc_html_e( 'Serif', 'pressnative' ); ?></option>
								<option value="monospace" <?php selected( $font_family, 'monospace' ); ?>><?php esc_html_e( 'Monospace', 'pressnative' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Base font for app content.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_base_font_size"><?php esc_html_e( 'Base Font Size', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$base_font_size = (int) get_option( PressNative_Options::OPTION_BASE_FONT_SIZE, PressNative_Options::DEFAULT_BASE_FONT_SIZE );
							?>
							<input type="number"
								   id="pressnative_base_font_size"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_BASE_FONT_SIZE ); ?>"
								   value="<?php echo esc_attr( $base_font_size ); ?>"
								   min="12" max="24" step="1" class="small-text"/>
							<span><?php esc_html_e( 'px (12–24)', 'pressnative' ); ?></span>
							<p class="description"><?php esc_html_e( 'Base font size for app typography.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Logo', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="hidden" id="pressnative_logo_attachment_id" name="<?php echo esc_attr( PressNative_Options::OPTION_LOGO_ATTACHMENT ); ?>" value="<?php echo esc_attr( $logo_attachment ); ?>"/>
							<div id="pressnative_logo_preview"><?php echo wp_kses_post( $logo_preview ); ?></div>
							<button type="button" class="button" id="pressnative_logo_select"><?php esc_html_e( 'Select or upload logo', 'pressnative' ); ?></button>
							<button type="button" class="button" id="pressnative_logo_remove"><?php esc_html_e( 'Remove', 'pressnative' ); ?></button>
							<p class="description"><?php esc_html_e( 'Header logo for the app. Used when provided in the API.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_app_categories"><?php esc_html_e( 'App Categories', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$app_categories = PressNative_Options::get_app_categories();
							$default_tags   = array( 'TCG', 'News', 'Store', 'Community' );
							?>
							<div id="pressnative-app-categories" class="pressnative-tag-cloud" style="margin-bottom:10px;">
								<span class="pressnative-tag-label" style="display:block;margin-bottom:6px;font-weight:500;"><?php esc_html_e( 'Suggested:', 'pressnative' ); ?></span>
								<div class="pressnative-tag-pills" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
									<?php foreach ( $default_tags as $tag ) : ?>
										<button type="button" class="pressnative-tag-pill button" data-tag="<?php echo esc_attr( $tag ); ?>" style="border-radius:16px;padding:4px 12px;height:auto;line-height:1.4;"><?php echo esc_html( $tag ); ?></button>
									<?php endforeach; ?>
								</div>
								<div class="pressnative-tag-input-wrapper" style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;min-height:38px;padding:6px 10px;border:1px solid #8c8f94;border-radius:4px;background:#fff;">
									<?php foreach ( $app_categories as $tag ) : ?>
										<span class="pressnative-tag-pill-display" data-tag="<?php echo esc_attr( $tag ); ?>" style="display:inline-flex;align-items:center;gap:4px;background:#2271b1;color:#fff;border-radius:16px;padding:4px 8px 4px 12px;font-size:13px;">
											<?php echo esc_html( $tag ); ?>
											<button type="button" class="pressnative-tag-remove" data-tag="<?php echo esc_attr( $tag ); ?>" style="background:none;border:none;color:inherit;cursor:pointer;padding:0;line-height:1;opacity:0.8;" aria-label="<?php esc_attr_e( 'Remove tag', 'pressnative' ); ?>">&times;</button>
										</span>
									<?php endforeach; ?>
									<input type="text"
										   id="pressnative_app_categories_input"
										   class="pressnative-tag-input"
										   placeholder="<?php esc_attr_e( 'Type and press Enter or comma to add (max 5)', 'pressnative' ); ?>"
										   data-max="<?php echo (int) PressNative_Options::APP_CATEGORIES_MAX; ?>"
										   style="flex:1;min-width:120px;border:none;outline:none;font-size:14px;"/>
									<input type="hidden"
										   id="pressnative_app_categories"
										   name="<?php echo esc_attr( PressNative_Options::OPTION_APP_CATEGORIES ); ?>"
										   value="<?php echo esc_attr( implode( ',', $app_categories ) ); ?>"/>
								</div>
							</div>
							<p class="description">
								<?php
								printf(
									/* translators: %d: max number of tags */
									esc_html__( 'Tags for Hub discovery. Max %d tags. Syncs to pressnative.app when you save.', 'pressnative' ),
									PressNative_Options::APP_CATEGORIES_MAX
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2 style="margin-top:2em;"><?php esc_html_e( 'Monetization', 'pressnative' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure AdMob to display banner ads in your app. Enable the Ad Placement component in Layout Settings to show ads on the home screen.', 'pressnative' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="pressnative_admob_banner_unit_id"><?php esc_html_e( 'AdMob Banner Unit ID', 'pressnative' ); ?></label>
						</th>
						<td>
							<?php
							$admob_banner = get_option( PressNative_Options::OPTION_ADMOB_BANNER_UNIT_ID, PressNative_Options::DEFAULT_ADMOB_BANNER_UNIT_ID );
							?>
							<input type="text"
								   id="pressnative_admob_banner_unit_id"
								   name="<?php echo esc_attr( PressNative_Options::OPTION_ADMOB_BANNER_UNIT_ID ); ?>"
								   value="<?php echo esc_attr( $admob_banner ); ?>"
								   class="regular-text"
								   placeholder="ca-app-pub-XXXXXXXXXXXXXXXX/YYYYYYYYYY"/>
							<p class="description">
								<?php esc_html_e( 'Your AdMob banner ad unit ID (e.g. ca-app-pub-xxxxx/yyyyy). The App ID is configured in each native app at build time. Leave blank to disable ads.', 'pressnative' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
				</div>
				<div class="pressnative-preview-wrapper" style="flex:0 0 auto;">
					<div class="pressnative-device-switcher">
						<button type="button" class="pressnative-device-btn active" data-device="iphone" aria-pressed="true">iPhone</button>
						<button type="button" class="pressnative-device-btn" data-device="android" aria-pressed="false">Android</button>
					</div>
					<div class="pressnative-device-frame pressnative-device-iphone" id="pressnative-device-frame">
						<div class="pressnative-device-screen">
							<div class="pressnative-device-notch" aria-hidden="true"></div>
							<div class="pressnative-device-punch" aria-hidden="true"></div>
							<div id="pressnative-preview" class="pressnative-preview-frame" data-page="app-settings">
								<div class="pressnative-preview-viewport">
									<div class="pressnative-preview-toolbar"></div>
									<div class="pressnative-preview-content"></div>
								</div>
							</div>
							<div class="pressnative-device-home" aria-hidden="true"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Layout Settings page (hero, post grid, categories, components).
	 *
	 * @return void
	 */
	public static function render_layout_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$hero_slug    = get_option( PressNative_Layout_Options::OPTION_HERO_CATEGORY_SLUG, PressNative_Layout_Options::DEFAULT_HERO_CATEGORY_SLUG );
		$hero_max     = (int) get_option( PressNative_Layout_Options::OPTION_HERO_MAX_ITEMS, PressNative_Layout_Options::DEFAULT_HERO_MAX_ITEMS );
		$grid_cols    = (int) get_option( PressNative_Layout_Options::OPTION_POST_GRID_COLUMNS, PressNative_Layout_Options::DEFAULT_POST_GRID_COLUMNS );
		$grid_per     = (int) get_option( PressNative_Layout_Options::OPTION_POST_GRID_PER_PAGE, PressNative_Layout_Options::DEFAULT_POST_GRID_PER_PAGE );
		$enabled_cats = PressNative_Layout_Options::get_enabled_category_ids();
		$enabled_comp = get_option( PressNative_Layout_Options::OPTION_ENABLED_COMPONENTS, PressNative_Layout_Options::COMPONENT_IDS );
		if ( ! is_array( $enabled_comp ) ) {
			$enabled_comp = PressNative_Layout_Options::COMPONENT_IDS;
		}

		$categories = get_categories( array( 'hide_empty' => false, 'parent' => 0 ) );
		$component_labels = array(
			'hero-carousel'        => __( 'Hero Carousel', 'pressnative' ),
			'post-grid'            => __( 'Post Grid', 'pressnative' ),
			'category-list'        => __( 'Category List', 'pressnative' ),
			'page-list'            => __( 'Page List', 'pressnative' ),
			'product-grid'         => __( 'Product Grid (WooCommerce)', 'pressnative' ),
			'product-category-list' => __( 'Product Categories (WooCommerce)', 'pressnative' ),
			'product-carousel'     => __( 'Product Carousel (WooCommerce)', 'pressnative' ),
			'ad-slot-1'            => __( 'Ad Placement', 'pressnative' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Layout Settings', 'pressnative' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Configure home screen components and content. Changes appear in the mobile app.', 'pressnative' ); ?></p>
			<div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;">
				<div style="flex:1;min-width:320px;">
			<form method="post" action="options.php" class="pressnative-settings-form">
				<?php settings_fields( 'pressnative_layout_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="pressnative_hero_category_slug"><?php esc_html_e( 'Hero Carousel: Featured Category', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_hero_category_slug"
								   name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_HERO_CATEGORY_SLUG ); ?>"
								   value="<?php echo esc_attr( $hero_slug ); ?>"
								   class="regular-text"
								   placeholder="featured"/>
							<p class="description"><?php esc_html_e( 'Category slug for hero items. Create a category with this slug (e.g. "featured") and assign posts to it.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_hero_max_items"><?php esc_html_e( 'Hero Carousel: Max Items', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="number"
								   id="pressnative_hero_max_items"
								   name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_HERO_MAX_ITEMS ); ?>"
								   value="<?php echo esc_attr( $hero_max ); ?>"
								   min="1" max="10" step="1" class="small-text"/>
							<p class="description"><?php esc_html_e( 'Maximum slides in the hero carousel (1–10).', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_post_grid_columns"><?php esc_html_e( 'Post Grid: Columns', 'pressnative' ); ?></label>
						</th>
						<td>
							<select id="pressnative_post_grid_columns"
									name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_POST_GRID_COLUMNS ); ?>">
								<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
									<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $grid_cols, $i ); ?>><?php echo esc_html( $i ); ?></option>
								<?php endfor; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Number of columns in the post grid.', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_post_grid_per_page"><?php esc_html_e( 'Post Grid: Posts Per Page', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="number"
								   id="pressnative_post_grid_per_page"
								   name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_POST_GRID_PER_PAGE ); ?>"
								   value="<?php echo esc_attr( $grid_per ); ?>"
								   min="1" max="50" step="1" class="small-text"/>
							<p class="description"><?php esc_html_e( 'Number of posts shown in the grid (1–50).', 'pressnative' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Category List: Visible Categories', 'pressnative' ); ?></label>
						</th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_ENABLED_CATEGORIES ); ?>[]" value="0" />
							<?php if ( ! empty( $categories ) ) : ?>
								<fieldset>
									<?php foreach ( $categories as $cat ) : ?>
										<label style="display:block;margin-bottom:4px;">
											<input type="checkbox"
												   name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_ENABLED_CATEGORIES ); ?>[]"
												   value="<?php echo esc_attr( $cat->term_id ); ?>"
												   <?php checked( in_array( (int) $cat->term_id, $enabled_cats, true ) ); ?>/>
											<?php echo esc_html( $cat->name ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Select categories to show in the app. Leave all unchecked to show all top-level categories.', 'pressnative' ); ?></p>
							<?php else : ?>
								<p><?php esc_html_e( 'No categories found. Create categories in Posts → Categories.', 'pressnative' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Component Order', 'pressnative' ); ?></label>
						</th>
						<td>
							<style>.pressnative-sortable-placeholder{height:40px;background:#f0f0f1;border:2px dashed #c3c4c7;margin-bottom:6px;list-style:none;}</style>
							<p class="description"><?php esc_html_e( 'Drag to reorder. Uncheck to hide a component.', 'pressnative' ); ?></p>
							<input type="hidden" id="pressnative-component-order-value"
								   name="<?php echo esc_attr( PressNative_Layout_Options::OPTION_ENABLED_COMPONENTS ); ?>"
								   value="<?php echo esc_attr( implode( ',', $enabled_comp ) ); ?>" />
							<ul id="pressnative-component-order" style="list-style:none;margin:0;padding:0;">
								<?php foreach ( $enabled_comp as $cid ) : ?>
									<li style="margin-bottom:6px;padding:8px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;">
										<span class="pressnative-drag-handle" style="cursor:move;margin-right:8px;color:#787c82;">&#9776;</span>
										<label style="cursor:pointer;margin:0;">
											<input type="checkbox" value="<?php echo esc_attr( $cid ); ?>" checked="checked" />
											<?php echo esc_html( $component_labels[ $cid ] ?? $cid ); ?>
										</label>
									</li>
								<?php endforeach; ?>
								<?php foreach ( array_diff( PressNative_Layout_Options::COMPONENT_IDS, $enabled_comp ) as $cid ) : ?>
									<li style="margin-bottom:6px;padding:8px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;">
										<span class="pressnative-drag-handle" style="cursor:move;margin-right:8px;color:#787c82;">&#9776;</span>
										<label style="cursor:pointer;margin:0;">
											<input type="checkbox" value="<?php echo esc_attr( $cid ); ?>" />
											<?php echo esc_html( $component_labels[ $cid ] ?? $cid ); ?>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
				</div>
				<div class="pressnative-preview-wrapper" style="flex:0 0 auto;">
					<div class="pressnative-device-switcher">
						<button type="button" class="pressnative-device-btn active" data-device="iphone" aria-pressed="true">iPhone</button>
						<button type="button" class="pressnative-device-btn" data-device="android" aria-pressed="false">Android</button>
					</div>
					<div class="pressnative-device-frame pressnative-device-iphone" id="pressnative-device-frame">
						<div class="pressnative-device-screen">
							<div class="pressnative-device-notch" aria-hidden="true"></div>
							<div class="pressnative-device-punch" aria-hidden="true"></div>
							<div id="pressnative-preview" class="pressnative-preview-frame" data-page="layout-settings">
								<div class="pressnative-preview-viewport">
									<div class="pressnative-preview-toolbar"></div>
									<div class="pressnative-preview-content"></div>
								</div>
							</div>
							<div class="pressnative-device-home" aria-hidden="true"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handles ad-hoc push form submission. POSTs to Registry with API key.
	 *
	 * @return void
	 */
	public static function handle_send_push() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressnative' ) );
		}
		if ( ! isset( $_POST['pressnative_push_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pressnative_push_nonce'] ) ), 'pressnative_send_push' ) ) {
			wp_safe_redirect( add_query_arg( 'pressnative_push_error', 'nonce', admin_url( 'admin.php?page=pressnative-push' ) ) );
			exit;
		}
		$api_key     = get_option( self::OPTION_API_KEY, '' );
		$registry_url = self::get_registry_url();
		if ( ! $api_key || ! $registry_url ) {
			wp_safe_redirect( add_query_arg( 'pressnative_push_error', 'no_api_key', admin_url( 'admin.php?page=pressnative-push' ) ) );
			exit;
		}
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$body  = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		if ( ! $title || ! $body ) {
			wp_safe_redirect( add_query_arg( 'pressnative_push_error', 'missing_fields', admin_url( 'admin.php?page=pressnative-push' ) ) );
			exit;
		}
		$link        = isset( $_POST['link'] ) ? esc_url_raw( wp_unslash( $_POST['link'] ) ) : '';
		$image_url   = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		$device_type = isset( $_POST['device_type'] ) ? sanitize_text_field( wp_unslash( $_POST['device_type'] ) ) : 'all';
		if ( ! in_array( $device_type, array( 'all', 'ios', 'android' ), true ) ) {
			$device_type = 'all';
		}
		$engaged_since_days = isset( $_POST['engaged_since_days'] ) ? sanitize_text_field( wp_unslash( $_POST['engaged_since_days'] ) ) : '';
		if ( in_array( $engaged_since_days, array( '7', '30' ), true ) ) {
			$engaged_since_days = (int) $engaged_since_days;
		} else {
			$engaged_since_days = null;
		}
		$url       = rtrim( $registry_url, '/' ) . '/api/v1/push/send';
		$body_data = array(
			'title'             => $title,
			'body'              => $body,
			'link'              => $link,
			'image_url'         => $image_url,
			'device_type'       => $device_type,
			'engaged_since_days' => $engaged_since_days,
		);
		$response   = wp_remote_post(
			$url,
			array(
				'timeout'  => 15,
				'blocking' => true,
				'headers'  => array(
					'Content-Type'         => 'application/json',
					'X-PressNative-API-Key' => $api_key,
				),
				'body'     => wp_json_encode( $body_data ),
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_safe_redirect( add_query_arg( 'pressnative_push_error', 'request_failed', admin_url( 'admin.php?page=pressnative-push' ) ) );
			exit;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body_res = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body_res, true );
		if ( $code >= 200 && $code < 300 ) {
			$sent = isset( $data['sent'] ) ? (int) $data['sent'] : 0;
			wp_safe_redirect( add_query_arg( 'pressnative_push_sent', $sent, admin_url( 'admin.php?page=pressnative-push' ) ) );
			exit;
		}
		$err_msg = isset( $data['error'] ) ? $data['error'] : __( 'Unknown error', 'pressnative' );
		wp_safe_redirect( add_query_arg( array( 'pressnative_push_error' => 'registry', 'pressnative_push_message' => rawurlencode( $err_msg ) ), admin_url( 'admin.php?page=pressnative-push' ) ) );
		exit;
	}

	/**
	 * Renders the Push Notifications page (ad-hoc send).
	 *
	 * @return void
	 */
	public static function render_push_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$api_key      = get_option( self::OPTION_API_KEY, '' );
		$registry_url = self::get_registry_url();
		$has_config   = ! empty( $api_key ) && ! empty( $registry_url );
		$error        = isset( $_GET['pressnative_push_error'] ) ? sanitize_text_field( wp_unslash( $_GET['pressnative_push_error'] ) ) : '';
		$sent         = isset( $_GET['pressnative_push_sent'] ) ? (int) $_GET['pressnative_push_sent'] : 0;
		$err_msg      = isset( $_GET['pressnative_push_message'] ) ? sanitize_text_field( wp_unslash( $_GET['pressnative_push_message'] ) ) : '';
		$stats        = $has_config ? self::fetch_push_subscriber_stats() : null;
		$stats        = is_array( $stats ) ? $stats : array( 'total' => 0, 'ios' => 0, 'android' => 0, 'engaged_7d' => 0, 'engaged_30d' => 0 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Push Notifications', 'pressnative' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Send an ad-hoc push notification to all app users who have your site favorited and opted into push. Requires API key and Registry URL in Settings.', 'pressnative' ); ?></p>

			<?php if ( $has_config ) : ?>
				<div class="pressnative-push-stats" style="margin: 16px 0; padding: 12px 16px; background: #f0f0f1; border-left: 4px solid #2271b1; max-width: 600px;">
					<p style="margin: 0 0 8px; font-weight: 600;">
						<?php esc_html_e( 'Subscribers', 'pressnative' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pressnative-push' ) ); ?>" class="button button-small" style="margin-left: 8px; vertical-align: middle;"><?php esc_html_e( 'Refresh', 'pressnative' ); ?></a>
					</p>
					<p style="margin: 0; font-size: 24px; line-height: 1.2;">
						<?php
						printf(
							/* translators: %s: number of subscribers */
							esc_html__( '%s subscriber(s) will receive this notification', 'pressnative' ),
							esc_html( number_format_i18n( $stats['total'] ) )
						);
						?>
					</p>
					<?php if ( $stats['total'] > 0 ) : ?>
						<p style="margin: 8px 0 0; font-size: 13px; color: #50575e;">
							<?php
							printf(
								/* translators: 1: Android count, 2: iOS count */
								esc_html__( 'Android: %1$s · iOS: %2$s', 'pressnative' ),
								esc_html( number_format_i18n( $stats['android'] ) ),
								esc_html( number_format_i18n( $stats['ios'] ) )
							);
							?>
						</p>
						<?php if ( ( $stats['engaged_7d'] ?? 0 ) > 0 || ( $stats['engaged_30d'] ?? 0 ) > 0 ) : ?>
							<p style="margin: 4px 0 0; font-size: 12px; color: #646970;">
								<?php
								printf(
									/* translators: 1: engaged in 7 days count, 2: engaged in 30 days count */
									esc_html__( 'Engaged (viewed/clicked): %1$s in last 7 days · %2$s in last 30 days', 'pressnative' ),
									esc_html( number_format_i18n( $stats['engaged_7d'] ?? 0 ) ),
									esc_html( number_format_i18n( $stats['engaged_30d'] ?? 0 ) )
								);
								?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $sent > 0 ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( __( 'Push sent successfully to %d device(s).', 'pressnative' ), $sent ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible">
					<p>
						<?php
						switch ( $error ) {
							case 'nonce':
								esc_html_e( 'Security check failed. Please try again.', 'pressnative' );
								break;
							case 'no_api_key':
								esc_html_e( 'API Key and Registry URL must be configured in PressNative Settings.', 'pressnative' );
								break;
							case 'missing_fields':
								esc_html_e( 'Title and body are required.', 'pressnative' );
								break;
							case 'request_failed':
								esc_html_e( 'Failed to reach the Registry. Check your connection and Registry URL.', 'pressnative' );
								break;
							case 'registry':
								echo esc_html( $err_msg ?: __( 'Registry returned an error.', 'pressnative' ) );
								break;
							default:
								esc_html_e( 'An error occurred. Please try again.', 'pressnative' );
						}
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! $has_config ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Configure your API Key and Registry URL in PressNative Settings to send push notifications.', 'pressnative' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=pressnative' ) ); ?>"><?php esc_html_e( 'Go to Settings', 'pressnative' ); ?></a></p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pressnative-push-form" style="max-width: 600px; margin-top: 20px;">
					<input type="hidden" name="action" value="pressnative_send_push" />
					<?php wp_nonce_field( 'pressnative_send_push', 'pressnative_push_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="pressnative-push-device-type"><?php esc_html_e( 'Deliver to', 'pressnative' ); ?></label></th>
							<td>
								<fieldset id="pressnative-push-device-type" style="margin: 0; padding: 0; border: none;">
									<label style="margin-right: 16px;">
										<input type="radio" name="device_type" value="all" checked />
										<?php esc_html_e( 'All devices', 'pressnative' ); ?>
									</label>
									<label style="margin-right: 16px;">
										<input type="radio" name="device_type" value="android" />
										<?php esc_html_e( 'Android only', 'pressnative' ); ?>
									</label>
									<label>
										<input type="radio" name="device_type" value="ios" />
										<?php esc_html_e( 'iOS only', 'pressnative' ); ?>
									</label>
								</fieldset>
								<p class="description" style="margin-top: 4px;"><?php esc_html_e( 'Filter which devices receive this notification.', 'pressnative' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pressnative-push-engaged"><?php esc_html_e( 'Engagement filter', 'pressnative' ); ?></label></th>
							<td>
								<select id="pressnative-push-engaged" name="engaged_since_days" style="min-width: 200px;">
									<option value=""><?php esc_html_e( 'All subscribers', 'pressnative' ); ?></option>
									<option value="7"><?php esc_html_e( 'Viewed or clicked in last 7 days', 'pressnative' ); ?></option>
									<option value="30"><?php esc_html_e( 'Viewed or clicked in last 30 days', 'pressnative' ); ?></option>
								</select>
								<p class="description" style="margin-top: 4px;"><?php esc_html_e( 'Target only subscribers who have opened the app and viewed content recently.', 'pressnative' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="pressnative-push-title"><?php esc_html_e( 'Title', 'pressnative' ); ?></label></th>
							<td><input type="text" id="pressnative-push-title" name="title" class="regular-text" required maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Breaking News', 'pressnative' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="pressnative-push-body"><?php esc_html_e( 'Message', 'pressnative' ); ?></label></th>
							<td><textarea id="pressnative-push-body" name="body" rows="4" class="large-text" required maxlength="200" placeholder="<?php esc_attr_e( 'Your notification message...', 'pressnative' ); ?>"></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="pressnative-push-link"><?php esc_html_e( 'Link (optional)', 'pressnative' ); ?></label></th>
							<td><input type="url" id="pressnative-push-link" name="link" class="regular-text" placeholder="<?php esc_attr_e( 'https://...', 'pressnative' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="pressnative-push-image"><?php esc_html_e( 'Image URL (optional)', 'pressnative' ); ?></label></th>
							<td><input type="url" id="pressnative-push-image" name="image_url" class="regular-text" placeholder="<?php esc_attr_e( 'https://...', 'pressnative' ); ?>" /></td>
						</tr>
					</table>
					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Send Push Notification', 'pressnative' ); ?></button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	}

	/**
	 * Renders the Analytics dashboard page.
	 *
	 * @return void
	 */
	public static function render_analytics_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap pressnative-analytics-wrap">
			<h1><?php esc_html_e( 'Analytics', 'pressnative' ); ?></h1>
			<p class="description"><?php esc_html_e( 'App usage and content views from the PressNative mobile app. Views are counted when the app loads content from the server; when the app serves cached content it can send a track event so those views are included.', 'pressnative' ); ?></p>

			<div class="pressnative-analytics-toolbar">
				<label for="pressnative-analytics-days"><?php esc_html_e( 'Date range:', 'pressnative' ); ?></label>
				<select id="pressnative-analytics-days" class="pressnative-analytics-days">
					<option value="7"><?php esc_html_e( 'Last 7 days', 'pressnative' ); ?></option>
					<option value="30" selected><?php esc_html_e( 'Last 30 days', 'pressnative' ); ?></option>
					<option value="90"><?php esc_html_e( 'Last 90 days', 'pressnative' ); ?></option>
				</select>
			</div>

			<div class="pressnative-analytics-kpis" id="pressnative-analytics-kpis">
				<div class="pressnative-kpi-card pressnative-kpi-highlight"><span class="pressnative-kpi-value" data-kpi="favorites">—</span><span class="pressnative-kpi-label"><?php esc_html_e( 'Downloads', 'pressnative' ); ?></span></div>
				<div class="pressnative-kpi-card"><span class="pressnative-kpi-value" data-kpi="total">—</span><span class="pressnative-kpi-label"><?php esc_html_e( 'Total Views', 'pressnative' ); ?></span></div>
				<div class="pressnative-kpi-card"><span class="pressnative-kpi-value" data-kpi="post">—</span><span class="pressnative-kpi-label"><?php esc_html_e( 'Post Views', 'pressnative' ); ?></span></div>
				<div class="pressnative-kpi-card"><span class="pressnative-kpi-value" data-kpi="page">—</span><span class="pressnative-kpi-label"><?php esc_html_e( 'Page Views', 'pressnative' ); ?></span></div>
				<div class="pressnative-kpi-card"><span class="pressnative-kpi-value" data-kpi="category">—</span><span class="pressnative-kpi-label"><?php esc_html_e( 'Category Views', 'pressnative' ); ?></span></div>
			</div>
			<p class="pressnative-analytics-kpi-note"><?php esc_html_e( 'Downloads = users who have favorited your site in the app.', 'pressnative' ); ?></p>
			<div class="pressnative-analytics-push-kpis">
				<div class="pressnative-kpi-card"><span class="pressnative-kpi-value" data-kpi="push_received">—</span><span class="pressnative-kpi-label"><?php esc_html_e( 'Push Received', 'pressnative' ); ?></span></div>
				<div class="pressnative-kpi-card"><span class="pressnative-kpi-value" data-kpi="push_clicked">—</span><span class="pressnative-kpi-label"><?php esc_html_e( 'Push Clicks', 'pressnative' ); ?></span></div>
			</div>
			<p class="pressnative-analytics-kpi-note"><?php esc_html_e( 'Push Received = notifications delivered to devices. Push Clicks = users who tapped a notification.', 'pressnative' ); ?></p>

			<div class="pressnative-analytics-charts">
				<div class="pressnative-chart-container">
					<h3><?php esc_html_e( 'Views over time', 'pressnative' ); ?></h3>
					<canvas id="pressnative-chart-views-over-time" aria-label="<?php esc_attr_e( 'Views over time', 'pressnative' ); ?>"></canvas>
				</div>
				<div class="pressnative-chart-container">
					<h3><?php esc_html_e( 'Content type breakdown', 'pressnative' ); ?></h3>
					<canvas id="pressnative-chart-content-type" aria-label="<?php esc_attr_e( 'Content type breakdown', 'pressnative' ); ?>"></canvas>
				</div>
				<div class="pressnative-chart-container">
					<h3><?php esc_html_e( 'Device breakdown', 'pressnative' ); ?></h3>
					<p class="pressnative-chart-description"><?php esc_html_e( 'Views by device. "Other" may include emulators or requests without a standard User-Agent. Emulator traffic is now detected as Android.', 'pressnative' ); ?></p>
					<canvas id="pressnative-chart-device" aria-label="<?php esc_attr_e( 'Device breakdown', 'pressnative' ); ?>"></canvas>
				</div>
			</div>

			<div class="pressnative-analytics-tables">
				<div class="pressnative-table-container">
					<h3><?php esc_html_e( 'Most viewed posts', 'pressnative' ); ?></h3>
					<div id="pressnative-table-top-posts" class="pressnative-table-wrapper"></div>
				</div>
				<div class="pressnative-table-container">
					<h3><?php esc_html_e( 'Most viewed pages', 'pressnative' ); ?></h3>
					<div id="pressnative-table-top-pages" class="pressnative-table-wrapper"></div>
				</div>
				<div class="pressnative-table-container">
					<h3><?php esc_html_e( 'Top categories', 'pressnative' ); ?></h3>
					<div id="pressnative-table-top-categories" class="pressnative-table-wrapper"></div>
				</div>
				<div class="pressnative-table-container">
					<h3><?php esc_html_e( 'Top search queries', 'pressnative' ); ?></h3>
					<div id="pressnative-table-top-searches" class="pressnative-table-wrapper"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Fetches push subscriber stats (total, ios, android) from the Registry.
	 *
	 * @return array{total:int,ios:int,android:int}|null Stats or null on failure.
	 */
	public static function fetch_push_subscriber_stats() {
		$registry_url = self::get_registry_url();
		$api_key      = get_option( self::OPTION_API_KEY, '' );
		if ( empty( $registry_url ) || empty( $api_key ) ) {
			return null;
		}
		$url      = rtrim( $registry_url, '/' ) . '/api/v1/push/subscriber-stats';
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'               => 'application/json',
					'X-PressNative-API-Key' => $api_key,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		return array(
			'total'      => isset( $data['total'] ) ? (int) $data['total'] : 0,
			'ios'        => isset( $data['ios'] ) ? (int) $data['ios'] : 0,
			'android'    => isset( $data['android'] ) ? (int) $data['android'] : 0,
			'engaged_7d' => isset( $data['engaged_7d'] ) ? (int) $data['engaged_7d'] : 0,
			'engaged_30d' => isset( $data['engaged_30d'] ) ? (int) $data['engaged_30d'] : 0,
		);
	}

	/**
	 * Fetches pitch / growth data from the Registry.
	 *
	 * @return array|null Pitch data or null on failure.
	 */
	public static function fetch_pitch_data() {
		$registry_url = self::get_registry_url();
		if ( empty( $registry_url ) ) {
			return null;
		}

		$url      = rtrim( $registry_url, '/' ) . '/api/v1/pitch';
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Renders the Growth / Pitch page showing business metrics to site owners.
	 *
	 * @return void
	 */
	public static function render_growth_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pitch      = self::fetch_pitch_data();
		$sub_status = self::fetch_subscription_status();
		$is_pro     = $sub_status && in_array( $sub_status['plan'] ?? '', array( 'pro', 'native_pro', 'enterprise' ), true );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Get Started — Why Go Native?', 'pressnative' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Business metrics and insights that show the value of a native mobile app for your WordPress site.', 'pressnative' ); ?></p>

			<?php if ( ! $pitch ) : ?>
				<div class="notice notice-warning" style="max-width:700px;margin-top:20px;">
					<p><?php esc_html_e( 'Could not load pitch data from the PressNative Registry. Ensure the Registry is running.', 'pressnative' ); ?></p>
				</div>
			<?php else : ?>

			<style>
				.pressnative-growth-grid {
					display: grid;
					grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
					gap: 20px;
					margin-top: 24px;
					max-width: 900px;
				}
				.pressnative-growth-card {
					background: #fff;
					border: 1px solid #c3c4c7;
					border-radius: 8px;
					padding: 24px;
					position: relative;
					overflow: hidden;
				}
				.pressnative-growth-card::before {
					content: '';
					position: absolute;
					top: 0;
					left: 0;
					right: 0;
					height: 4px;
					background: linear-gradient(90deg, #6366f1, #06b6d4);
				}
				.pressnative-growth-card .tag {
					display: inline-block;
					padding: 2px 8px;
					border-radius: 4px;
					font-size: 11px;
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: 0.05em;
					color: #6366f1;
					background: rgba(99, 102, 241, 0.1);
					margin-bottom: 12px;
				}
				.pressnative-growth-card h3 {
					margin: 0 0 8px;
					font-size: 1em;
					line-height: 1.4;
				}
				.pressnative-growth-card .stat {
					font-size: 2.5em;
					font-weight: 800;
					background: linear-gradient(135deg, #6366f1, #06b6d4);
					-webkit-background-clip: text;
					-webkit-text-fill-color: transparent;
					background-clip: text;
					line-height: 1;
					margin-bottom: 4px;
				}
				.pressnative-growth-card .stat-label {
					font-size: 13px;
					color: #50575e;
				}
				.pressnative-growth-card p {
					color: #50575e;
					font-size: 13px;
					line-height: 1.5;
				}
				.pressnative-benefit-grid {
					display: grid;
					grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
					gap: 16px;
					margin-top: 24px;
					max-width: 900px;
				}
				.pressnative-benefit-card {
					background: #fff;
					border: 1px solid #c3c4c7;
					border-radius: 8px;
					padding: 20px;
				}
				.pressnative-benefit-card h4 {
					margin: 0 0 6px;
					font-size: 0.95em;
				}
				.pressnative-benefit-card p {
					margin: 0;
					color: #50575e;
					font-size: 13px;
				}
				.pressnative-benefit-icon {
					width: 36px;
					height: 36px;
					border-radius: 8px;
					background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(6,182,212,0.15));
					display: flex;
					align-items: center;
					justify-content: center;
					margin-bottom: 12px;
					font-size: 18px;
				}
				.pressnative-cta-banner {
					margin-top: 32px;
					max-width: 900px;
					background: linear-gradient(135deg, #6366f1, #06b6d4);
					border-radius: 8px;
					padding: 24px 28px;
					display: flex;
					align-items: center;
					justify-content: space-between;
					flex-wrap: wrap;
					gap: 16px;
				}
				.pressnative-cta-banner h3 {
					margin: 0;
					color: #fff;
					font-size: 1.1em;
				}
				.pressnative-cta-banner p {
					margin: 4px 0 0;
					color: rgba(255,255,255,0.85);
					font-size: 13px;
				}
				.pressnative-cta-banner .button {
					background: #fff;
					color: #6366f1;
					border: none;
					font-weight: 600;
					padding: 8px 20px;
					border-radius: 6px;
					text-decoration: none;
				}
				.pressnative-cta-banner .button:hover {
					opacity: 0.9;
				}
			</style>

			<?php if ( ! empty( $pitch['headline'] ) ) : ?>
				<h2 style="margin-top:28px;"><?php echo esc_html( $pitch['headline'] ); ?></h2>
			<?php endif; ?>

			<?php if ( ! empty( $pitch['slides'] ) && is_array( $pitch['slides'] ) ) : ?>
				<div class="pressnative-growth-grid">
					<?php foreach ( $pitch['slides'] as $slide ) : ?>
						<div class="pressnative-growth-card">
							<?php if ( ! empty( $slide['tag'] ) ) : ?>
								<span class="tag"><?php echo esc_html( $slide['tag'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $slide['stat'] ) ) : ?>
								<div class="stat"><?php echo esc_html( $slide['stat'] ); ?></div>
								<?php if ( ! empty( $slide['stat_label'] ) ) : ?>
									<div class="stat-label"><?php echo esc_html( $slide['stat_label'] ); ?></div>
								<?php endif; ?>
							<?php endif; ?>
							<h3><?php echo esc_html( $slide['headline'] ?? '' ); ?></h3>
							<p><?php echo esc_html( $slide['description'] ?? '' ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $pitch['benefits'] ) && is_array( $pitch['benefits'] ) ) : ?>
				<h2 style="margin-top:32px;"><?php esc_html_e( 'Native Advantages', 'pressnative' ); ?></h2>
				<div class="pressnative-benefit-grid">
					<?php
					$icon_map = array(
						'bolt'        => '&#9889;',
						'fingerprint' => '&#128274;',
						'wifi'        => '&#128246;',
						'palette'     => '&#127912;',
					);
					foreach ( $pitch['benefits'] as $benefit ) :
						$icon_char = isset( $icon_map[ $benefit['icon'] ?? '' ] ) ? $icon_map[ $benefit['icon'] ] : '&#9733;';
						?>
						<div class="pressnative-benefit-card">
							<div class="pressnative-benefit-icon"><?php echo $icon_char; ?></div>
							<h4><?php echo esc_html( $benefit['title'] ?? '' ); ?></h4>
							<p><?php echo esc_html( $benefit['description'] ?? '' ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $is_pro && ! empty( $pitch['cta'] ) ) : ?>
				<div class="pressnative-cta-banner">
					<div>
						<h3><?php echo esc_html( $pitch['cta']['label'] ?? __( 'Upgrade to Native Pro', 'pressnative' ) ); ?></h3>
						<p><?php echo esc_html( $pitch['cta']['description'] ?? '' ); ?></p>
					</div>
					<a href="<?php echo esc_url( $pitch['cta']['url'] ?? 'https://pressnative.app/#pricing' ); ?>" class="button" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Learn More', 'pressnative' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Returns the configured Registry URL.
	 * Production default is https://pressnative.app.
	 * On localhost, uses the user-configured value (which defaults to localhost:3000 for dev).
	 *
	 * @return string
	 */
	public static function get_registry_url() {
		if ( self::is_localhost() ) {
			$saved = get_option( self::OPTION_REGISTRY_URL, '' );
			return ! empty( $saved ) ? $saved : 'http://localhost:3000';
		}
		return self::DEFAULT_REGISTRY_URL;
	}

	/**
	 * Checks if the current WordPress site is running on localhost.
	 *
	 * @return bool
	 */
	public static function is_localhost() {
		$home   = home_url();
		$parsed = wp_parse_url( $home );
		$host   = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
		return in_array( $host, array( 'localhost', '127.0.0.1' ), true );
	}

	/**
	 * Handles the Stripe portal redirect when ?pressnative_portal=1 is present.
	 *
	 * @return void
	 */
	public static function handle_stripe_portal_redirect() {
		if ( empty( $_GET['pressnative_portal'] ) || '1' !== $_GET['pressnative_portal'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Only handle on our settings page.
		if ( empty( $_GET['page'] ) || 'pressnative' !== $_GET['page'] ) {
			return;
		}

		$portal_url = self::get_stripe_portal_url();
		if ( $portal_url ) {
			wp_redirect( $portal_url );
			exit;
		}
		// If portal URL failed, redirect back with an error.
		wp_safe_redirect( add_query_arg( 'pressnative_portal_error', '1', admin_url( 'admin.php?page=pressnative' ) ) );
		exit;
	}

	/**
	 * Pings the Registry to verify schema (GET /api/v1/schema). Saves verification timestamp on success.
	 *
	 * @return bool True if schema was verified, false otherwise.
	 */
	public static function verify_registry_schema() {
		$base = self::get_registry_url();
		$url  = rtrim( $base, '/' ) . '/api/v1/schema';

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'sslverify'  => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );
		if ( ! is_array( $json ) || ! isset( $json['branding'] ) || ! isset( $json['screen'] ) || ! isset( $json['components'] ) ) {
			return false;
		}

		update_option( self::OPTION_SCHEMA_VERIFIED, current_time( 'mysql' ) );
		return true;
	}

	/**
	 * Triggers site verification when the API key is saved/updated.
	 *
	 * @param string $old_value Previous API key.
	 * @param string $new_value New API key.
	 * @return bool True if verification succeeded.
	 */
	public static function trigger_site_verification( $old_value, $new_value ) {
		return self::attempt_site_verification( sanitize_text_field( $new_value ) );
	}

	/**
	 * Triggers site verification when the API key is first added (not just updated).
	 *
	 * @param string $option Option name.
	 * @param string $value  Option value.
	 * @return bool True if verification succeeded.
	 */
	public static function trigger_site_verification_on_add( $option, $value ) {
		return self::attempt_site_verification( sanitize_text_field( $value ) );
	}

	/**
	 * Admin action handler: manual "Verify Now" button.
	 *
	 * @return void
	 */
	public static function handle_verify_site() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressnative' ) );
		}
		check_admin_referer( 'pressnative_verify_site' );

		$api_key = get_option( self::OPTION_API_KEY, '' );
		$result  = self::attempt_site_verification( $api_key );

		if ( ! $result ) {
			$error = get_transient( 'pressnative_verify_error' );
			if ( empty( $error ) ) {
				set_transient( 'pressnative_verify_error', 'Unknown error during verification.', 60 );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=pressnative' ) );
		exit;
	}

	/**
	 * Attempts site verification with the Registry.
	 *
	 * Generates a one-time nonce, stores it as a transient, then calls the
	 * Registry's /api/verify-site endpoint. The Registry calls back to
	 * /wp-json/pressnative/v1/verify-ownership to confirm the nonce,
	 * proving this WordPress site has wp-admin access for the given API key.
	 *
	 * @param string $api_key The API key to verify with.
	 * @return bool True if verification succeeded.
	 */
	public static function attempt_site_verification( $api_key ) {
		if ( empty( $api_key ) ) {
			set_transient( 'pressnative_verify_error', 'No API key provided.', 5 * MINUTE_IN_SECONDS );
			return false;
		}

		$nonce = wp_generate_password( 48, false );
		set_transient( 'pressnative_verify_nonce', $nonce, 5 * MINUTE_IN_SECONDS );

		$registry_url = self::get_registry_url();
		$verify_url   = rtrim( $registry_url, '/' ) . '/api/verify-site';
		$site_url     = site_url();

		$response = wp_remote_post(
			$verify_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'          => 'application/json',
					'X-PressNative-API-Key' => $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'site_url' => $site_url,
						'nonce'    => $nonce,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			set_transient(
				'pressnative_verify_error',
				sprintf( 'Could not reach Registry at %s: %s', $registry_url, $response->get_error_message() ),
				5 * MINUTE_IN_SECONDS
			);
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 && ! empty( $data['ok'] ) ) {
			delete_transient( 'pressnative_verify_error' );
			update_option( 'pressnative_site_verified', current_time( 'mysql' ) );
			return true;
		}

		$error_detail = ! empty( $data['error'] ) ? $data['error'] : '';
		$hint         = ! empty( $data['hint'] ) ? ' ' . $data['hint'] : '';
		set_transient(
			'pressnative_verify_error',
			sprintf( 'Registry returned HTTP %d: %s%s', $code, $error_detail, $hint ),
			5 * MINUTE_IN_SECONDS
		);

		return false;
	}

	/**
	 * Show admin notice with WooCommerce seed demo button.
	 */
	public static function show_woocommerce_seed_notice() {
		// Only show on PressNative admin pages
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'pressnative' ) === false ) {
			return;
		}

		// Only show if WooCommerce is active and user can manage it
		if ( ! class_exists( 'WooCommerce' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Check if we already have products (don't show if store already has content)
		$product_count = wp_count_posts( 'product' );
		if ( $product_count && $product_count->publish > 5 ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible" id="pressnative-woocommerce-seed-notice">
			<p>
				<strong><?php esc_html_e( 'WooCommerce Demo Data', 'pressnative' ); ?></strong><br>
				<?php esc_html_e( 'Populate your WooCommerce store with demo products and shoppable blog posts to test the native app integration.', 'pressnative' ); ?>
			</p>
			<p>
				<button type="button" class="button button-primary" id="pressnative-seed-woocommerce" data-loading-text="<?php esc_attr_e( 'Creating demo data...', 'pressnative' ); ?>">
					<?php esc_html_e( 'Create Demo Data', 'pressnative' ); ?>
				</button>
				<button type="button" class="notice-dismiss" onclick="document.getElementById('pressnative-woocommerce-seed-notice').style.display='none';">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'pressnative' ); ?></span>
				</button>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('#pressnative-seed-woocommerce').on('click', function() {
				var $btn = $(this);
				var originalText = $btn.text();
				var loadingText = $btn.data('loading-text');
				
				$btn.prop('disabled', true).text(loadingText);
				
				$.ajax({
					url: '<?php echo esc_url( rest_url( 'pressnative/v1/woocommerce/seed-demo' ) ); ?>',
					method: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
					},
					success: function(response) {
						var message = 'Demo data created successfully!';
						if (response.categories_created || response.products_created || response.posts_created) {
							message += ' Created: ' + response.categories_created + ' categories, ' + 
								response.products_created + ' products, ' + response.posts_created + ' posts.';
						}
						if (response.errors && response.errors.length > 0) {
							message += ' Some errors occurred: ' + response.errors.join(', ');
						}
						alert(message);
						$('#pressnative-woocommerce-seed-notice').fadeOut();
					},
					error: function(xhr, status, error) {
						alert('Error creating demo data: ' + (xhr.responseJSON ? xhr.responseJSON.message : error));
						$btn.prop('disabled', false).text(originalText);
					}
				});
			});
		});
		</script>
		<?php
	}
}
