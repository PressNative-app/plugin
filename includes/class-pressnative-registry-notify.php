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
		PressNative_Options::OPTION_APP_CATEGORIES,
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

		$registry_url = PressNative_Admin::get_registry_url();
		$url          = rtrim( $registry_url, '/' ) . '/api/v1/notify/config-changed';
		$site_url     = home_url( '/' );
		$tags         = PressNative_Options::get_app_categories();
		$branding     = PressNative_Options::get_branding();

		$body = array(
			'site_url' => $site_url,
			'tags'     => $tags,
			'branding' => $branding,
		);

		wp_remote_post(
			$url,
			array(
				'timeout'    => 5,
				'blocking'   => false,
				'sslverify'  => true,
				'headers'    => array(
					'Content-Type' => 'application/json',
				),
				'body'       => wp_json_encode( $body ),
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
		if ( $new_status !== 'publish' || ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
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

		$body = array(
			'site_url'        => $site_url,
			'post_id'         => $post->ID,
			'post_type'       => $post->post_type,
			'slug'            => $post->post_name,
			'title'           => get_the_title( $post ),
			'excerpt'         => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( $post->post_content, 30 ),
			'link'            => get_permalink( $post ),
			'thumbnail_url'   => $thumbnail_url,
		);

		wp_remote_post(
			$url,
			array(
				'timeout'    => 5,
				'blocking'   => false,
				'sslverify'  => true,
				'headers'    => array(
					'Content-Type' => 'application/json',
				),
				'body'       => wp_json_encode( $body ),
			)
		);
	}
}
