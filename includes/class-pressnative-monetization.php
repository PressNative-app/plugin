<?php
/**
 * Monetization: AdMob config, placement policy, and AdPlacement payload builders.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Monetization
 */
class PressNative_Monetization {

	const OPTION_ENABLED           = 'pressnative_ads_enabled';
	const OPTION_CONSENT_REQUIRED  = 'pressnative_ads_consent_required';
	const OPTION_TEST_MODE         = 'pressnative_ads_test_mode';
	const OPTION_ADMOB_APP_ID_IOS  = 'pressnative_admob_app_id_ios';
	const OPTION_ADMOB_APP_ID_ANDROID = 'pressnative_admob_app_id_android';
	const OPTION_UNIT_ID_IOS       = 'pressnative_admob_unit_id_ios';
	const OPTION_UNIT_ID_ANDROID   = 'pressnative_admob_unit_id_android';

	/** Google sample app / unit IDs for test mode. */
	const TEST_APP_ID_IOS     = 'ca-app-pub-3940256099942544~1458002511';
	const TEST_APP_ID_ANDROID = 'ca-app-pub-3940256099942544~3347511713';
	const TEST_UNIT_ID_IOS    = 'ca-app-pub-3940256099942544/2934735716';
	const TEST_UNIT_ID_ANDROID = 'ca-app-pub-3940256099942544/6300978111';

	/**
	 * Boot hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 12 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Add Monetization submenu under PressNative.
	 */
	public static function add_menu() {
		add_submenu_page(
			'pressnative',
			__( 'Monetization', 'pressnative-apps' ),
			__( 'Monetization', 'pressnative-apps' ),
			'manage_options',
			'pressnative-monetization',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register monetization options.
	 */
	public static function register_settings() {
		$group = 'pressnative_monetization_settings';
		register_setting(
			$group,
			self::OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => function ( $v ) {
					return (bool) $v;
				},
				'default'           => false,
			)
		);
		register_setting(
			$group,
			self::OPTION_CONSENT_REQUIRED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => function ( $v ) {
					return (bool) $v;
				},
				'default'           => true,
			)
		);
		register_setting(
			$group,
			self::OPTION_TEST_MODE,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => function ( $v ) {
					return (bool) $v;
				},
				'default'           => true,
			)
		);
		register_setting(
			$group,
			self::OPTION_ADMOB_APP_ID_IOS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_admob_id' ),
				'default'           => '',
			)
		);
		register_setting(
			$group,
			self::OPTION_ADMOB_APP_ID_ANDROID,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_admob_id' ),
				'default'           => '',
			)
		);
		register_setting(
			$group,
			self::OPTION_UNIT_ID_IOS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_admob_id' ),
				'default'           => '',
			)
		);
		register_setting(
			$group,
			self::OPTION_UNIT_ID_ANDROID,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_admob_id' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Sanitize AdMob app or unit ID (ca-app-pub-... form).
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	public static function sanitize_admob_id( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^ca-app-pub-\d+[\/~]\d+$/', $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Whether programmatic ads are enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Whether clients should use Google test IDs.
	 *
	 * @return bool
	 */
	public static function is_test_mode() {
		return (bool) get_option( self::OPTION_TEST_MODE, true );
	}

	/**
	 * Build top-level monetization config for layout responses.
	 *
	 * @return array
	 */
	public static function get_config() {
		$test = self::is_test_mode();
		$app_ios = $test ? self::TEST_APP_ID_IOS : (string) get_option( self::OPTION_ADMOB_APP_ID_IOS, '' );
		$app_android = $test ? self::TEST_APP_ID_ANDROID : (string) get_option( self::OPTION_ADMOB_APP_ID_ANDROID, '' );
		$interval = class_exists( 'PressNative_Layout_Options' )
			? PressNative_Layout_Options::get_sponsor_article_interval()
			: 5;
		$enabled_components = class_exists( 'PressNative_Layout_Options' )
			? PressNative_Layout_Options::get_enabled_components()
			: array();
		$sponsor_home = in_array( 'block-sponsor', $enabled_components, true );
		$ad_home      = in_array( 'ad-placement', $enabled_components, true );

		return array(
			'enabled'          => self::is_enabled(),
			'consent_required' => (bool) get_option( self::OPTION_CONSENT_REQUIRED, true ),
			'test_mode'        => $test,
			'providers'        => array(
				'admob' => array(
					'app_id_ios'     => $app_ios,
					'app_id_android' => $app_android,
				),
			),
			'policy'           => array(
				'sponsor_home_enabled'     => $sponsor_home,
				'sponsor_article_interval' => $interval,
				'ad_home_enabled'          => $ad_home && self::is_enabled(),
			),
		);
	}

	/**
	 * Resolve banner unit IDs for the current mode.
	 *
	 * @return array{ios:string,android:string}
	 */
	public static function get_banner_unit_ids() {
		if ( self::is_test_mode() ) {
			return array(
				'ios'     => self::TEST_UNIT_ID_IOS,
				'android' => self::TEST_UNIT_ID_ANDROID,
			);
		}
		return array(
			'ios'     => (string) get_option( self::OPTION_UNIT_ID_IOS, '' ),
			'android' => (string) get_option( self::OPTION_UNIT_ID_ANDROID, '' ),
		);
	}

	/**
	 * Build an AdPlacement component for the home layout, or null when disabled / incomplete.
	 *
	 * @param array $styles Component styles from layout builder.
	 * @return array|null
	 */
	public static function build_ad_placement( array $styles ) {
		if ( ! self::is_enabled() ) {
			return null;
		}
		$units = self::get_banner_unit_ids();
		if ( '' === $units['ios'] && '' === $units['android'] ) {
			return null;
		}
		$styles['padding']['vertical'] = isset( $styles['padding']['vertical'] ) ? $styles['padding']['vertical'] : 8;
		return array(
			'id'      => 'ad-home-banner-1',
			'type'    => 'AdPlacement',
			'styles'  => $styles,
			'content' => array(
				'placement_id' => 'home_feed_banner',
				'provider'     => 'admob',
				'format'       => 'banner',
				'size'         => 'adaptive',
				'unit_ids'     => $units,
			),
		);
	}

	/**
	 * Render Monetization settings page.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$enabled          = self::is_enabled();
		$consent_required = (bool) get_option( self::OPTION_CONSENT_REQUIRED, true );
		$test_mode        = self::is_test_mode();
		$app_ios          = (string) get_option( self::OPTION_ADMOB_APP_ID_IOS, '' );
		$app_android      = (string) get_option( self::OPTION_ADMOB_APP_ID_ANDROID, '' );
		$unit_ios         = (string) get_option( self::OPTION_UNIT_ID_IOS, '' );
		$unit_android     = (string) get_option( self::OPTION_UNIT_ID_ANDROID, '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Monetization', 'pressnative-apps' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Configure AdMob for programmatic banner ads on the home screen. Direct sponsorships are managed under Sponsors and Layout Settings (Block Sponsor).', 'pressnative-apps' ); ?>
			</p>
			<form method="post" action="options.php" class="pressnative-settings-form">
				<?php settings_fields( 'pressnative_monetization_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Ads', 'pressnative-apps' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>" value="0" />
							<label>
								<input type="checkbox"
									   name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>"
									   value="1"
									   <?php checked( $enabled ); ?> />
								<?php esc_html_e( 'Serve AdPlacement components when enabled in Layout Settings', 'pressnative-apps' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test Mode', 'pressnative-apps' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_TEST_MODE ); ?>" value="0" />
							<label>
								<input type="checkbox"
									   name="<?php echo esc_attr( self::OPTION_TEST_MODE ); ?>"
									   value="1"
									   <?php checked( $test_mode ); ?> />
								<?php esc_html_e( 'Use Google sample app and unit IDs (recommended while setting up)', 'pressnative-apps' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require Consent', 'pressnative-apps' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_CONSENT_REQUIRED ); ?>" value="0" />
							<label>
								<input type="checkbox"
									   name="<?php echo esc_attr( self::OPTION_CONSENT_REQUIRED ); ?>"
									   value="1"
									   <?php checked( $consent_required ); ?> />
								<?php esc_html_e( 'Gather UMP / ATT consent before initializing the Mobile Ads SDK', 'pressnative-apps' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_admob_app_id_ios"><?php esc_html_e( 'AdMob App ID (iOS)', 'pressnative-apps' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_admob_app_id_ios"
								   name="<?php echo esc_attr( self::OPTION_ADMOB_APP_ID_IOS ); ?>"
								   value="<?php echo esc_attr( $app_ios ); ?>"
								   class="regular-text"
								   placeholder="ca-app-pub-xxxxxxxxxxxxxxxx~yyyyyyyyyy" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_admob_app_id_android"><?php esc_html_e( 'AdMob App ID (Android)', 'pressnative-apps' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_admob_app_id_android"
								   name="<?php echo esc_attr( self::OPTION_ADMOB_APP_ID_ANDROID ); ?>"
								   value="<?php echo esc_attr( $app_android ); ?>"
								   class="regular-text"
								   placeholder="ca-app-pub-xxxxxxxxxxxxxxxx~yyyyyyyyyy" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_admob_unit_id_ios"><?php esc_html_e( 'Banner Unit ID (iOS)', 'pressnative-apps' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_admob_unit_id_ios"
								   name="<?php echo esc_attr( self::OPTION_UNIT_ID_IOS ); ?>"
								   value="<?php echo esc_attr( $unit_ios ); ?>"
								   class="regular-text"
								   placeholder="ca-app-pub-xxxxxxxxxxxxxxxx/yyyyyyyyyy" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="pressnative_admob_unit_id_android"><?php esc_html_e( 'Banner Unit ID (Android)', 'pressnative-apps' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="pressnative_admob_unit_id_android"
								   name="<?php echo esc_attr( self::OPTION_UNIT_ID_ANDROID ); ?>"
								   value="<?php echo esc_attr( $unit_android ); ?>"
								   class="regular-text"
								   placeholder="ca-app-pub-xxxxxxxxxxxxxxxx/yyyyyyyyyy" />
							<p class="description">
								<?php esc_html_e( 'Enable the Ad Placement component under Layout Settings to show the home banner. Direct sponsors remain under Sponsors.', 'pressnative-apps' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

PressNative_Monetization::init();
