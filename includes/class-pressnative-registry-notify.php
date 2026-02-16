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
		PressNative_Options::OPTION_APP_NAME,
		PressNative_Options::OPTION_PRIMARY_COLOR,
		PressNative_Options::OPTION_ACCENT_COLOR,
		PressNative_Options::OPTION_LOGO_ATTACHMENT,
		PressNative_Options::OPTION_BACKGROUND_COLOR,
		PressNative_Options::OPTION_TEXT_COLOR,
		PressNative_Options::OPTION_FONT_FAMILY,
		PressNative_Options::OPTION_BASE_FONT_SIZE,
		PressNative_Themes::OPTION_THEME_ID,
		PressNative_Layout_Options::OPTION_HERO_CATEGORY_SLUG,
		PressNative_Layout_Options::OPTION_HERO_MAX_ITEMS,
		PressNative_Layout_Options::OPTION_POST_GRID_COLUMNS,
		PressNative_Layout_Options::OPTION_POST_GRID_PER_PAGE,
		PressNative_Layout_Options::OPTION_ENABLED_CATEGORIES,
		PressNative_Layout_Options::OPTION_ENABLED_COMPONENTS,
	);

	/**
	 * Bootstraps the notify hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'updated_option', array( __CLASS__, 'maybe_notify_registry' ), 10, 3 );
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

		$registry_url = PressNative_Admin::get_registry_url();
		$url          = rtrim( $registry_url, '/' ) . '/api/v1/notify/config-changed';
		$site_url     = home_url( '/' );

		wp_remote_post(
			$url,
			array(
				'timeout'    => 5,
				'blocking'   => false,
				'sslverify'  => true,
				'headers'    => array(
					'Content-Type' => 'application/json',
				),
				'body'       => wp_json_encode( array( 'site_url' => $site_url ) ),
			)
		);
	}
}
