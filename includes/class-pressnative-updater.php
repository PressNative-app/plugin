<?php
/**
 * Checks pressnative.app for plugin updates and surfaces them in WordPress.
 *
 * @package PressNative
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PressNative_Updater
 */
class PressNative_Updater {

	const TRANSIENT_KEY = 'pressnative_plugin_update_info';
	const SLUG          = 'pressnative-apps';

	/**
	 * Registers WordPress update hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_upgrade' ), 10, 2 );
	}

	/**
	 * Injects an update into the WordPress plugin update transient when a newer release exists.
	 *
	 * @param object|false $transient Update transient.
	 * @return object|false
	 */
	public static function check_for_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = self::get_remote_info();
		if ( ! $remote ) {
			return $transient;
		}

		$plugin_file = plugin_basename( PRESSNATIVE_PLUGIN_FILE );
		$item        = self::to_update_object( $remote, $plugin_file );

		if ( version_compare( PRESSNATIVE_VERSION, $remote['version'], '<' ) ) {
			$transient->response[ $plugin_file ] = $item;
			unset( $transient->no_update[ $plugin_file ] );
		} else {
			$transient->no_update[ $plugin_file ] = $item;
			unset( $transient->response[ $plugin_file ] );
		}

		return $transient;
	}

	/**
	 * Supplies plugin details for the WordPress update modal.
	 *
	 * @param mixed  $result Default result.
	 * @param string $action API action.
	 * @param object $args   Request args.
	 * @return mixed
	 */
	public static function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$remote = self::get_remote_info();
		if ( ! $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => isset( $remote['name'] ) ? $remote['name'] : 'PressNative Apps',
			'slug'          => self::SLUG,
			'version'       => $remote['version'],
			'author'        => '<a href="https://pressnative.app">PressNative</a>',
			'homepage'      => isset( $remote['homepage'] ) ? $remote['homepage'] : 'https://pressnative.app/docs',
			'requires'      => isset( $remote['requires'] ) ? $remote['requires'] : '6.0',
			'requires_php'  => isset( $remote['requires_php'] ) ? $remote['requires_php'] : '7.4',
			'tested'        => isset( $remote['tested'] ) ? $remote['tested'] : '',
			'download_link' => $remote['download_url'],
			'last_updated'  => isset( $remote['last_updated'] ) ? $remote['last_updated'] : '',
			'sections'      => array(
				'description' => __( 'Native iOS and Android apps from WordPress. This plugin is a SaaS connector for PressNative Cloud.', 'pressnative-apps' ),
				'changelog'   => isset( $remote['sections']['changelog'] ) ? $remote['sections']['changelog'] : '',
			),
		);
	}

	/**
	 * Clears cached update metadata after this plugin is updated.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Upgrade options.
	 * @return void
	 */
	public static function after_upgrade( $upgrader, $options ) {
		unset( $upgrader );
		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}
		$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : array();
		if ( in_array( plugin_basename( PRESSNATIVE_PLUGIN_FILE ), $plugins, true ) ) {
			delete_site_transient( self::TRANSIENT_KEY );
		}
	}

	/**
	 * Fetches and caches remote update metadata from the Registry.
	 *
	 * @return array|null
	 */
	private static function get_remote_info() {
		$cached = get_site_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) && self::is_valid_remote( $cached ) ) {
			return $cached;
		}

		$registry_url = PressNative_Admin::get_registry_url();
		if ( empty( $registry_url ) ) {
			return null;
		}

		$url      = rtrim( $registry_url, '/' ) . '/api/v1/plugin/update';
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 8,
				'sslverify'  => true,
				'headers'    => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'PressNative-Apps/' . PRESSNATIVE_VERSION . '; ' . home_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! self::is_valid_remote( $body ) ) {
			return null;
		}

		set_site_transient( self::TRANSIENT_KEY, $body, 12 * HOUR_IN_SECONDS );
		return $body;
	}

	/**
	 * Validates remote update JSON and download host.
	 *
	 * @param mixed $remote Decoded payload.
	 * @return bool
	 */
	private static function is_valid_remote( $remote ) {
		if ( ! is_array( $remote ) ) {
			return false;
		}
		if ( empty( $remote['version'] ) || empty( $remote['download_url'] ) ) {
			return false;
		}
		if ( ! is_string( $remote['version'] ) || ! is_string( $remote['download_url'] ) ) {
			return false;
		}
		return self::is_allowed_download_host( $remote['download_url'] );
	}

	/**
	 * Restricts package URLs to GitHub download hosts.
	 *
	 * @param string $url Download URL.
	 * @return bool
	 */
	private static function is_allowed_download_host( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}
		$host = strtolower( $host );
		$allowed = array(
			'github.com',
			'www.github.com',
			'objects.githubusercontent.com',
			'release-assets.githubusercontent.com',
		);
		if ( in_array( $host, $allowed, true ) ) {
			return true;
		}
		return (bool) preg_match( '/\.githubusercontent\.com$/', $host );
	}

	/**
	 * Builds the object WordPress expects in the update transient.
	 *
	 * @param array  $remote      Remote metadata.
	 * @param string $plugin_file Plugin basename.
	 * @return object
	 */
	private static function to_update_object( $remote, $plugin_file ) {
		return (object) array(
			'slug'         => self::SLUG,
			'plugin'       => $plugin_file,
			'new_version'  => $remote['version'],
			'url'          => isset( $remote['homepage'] ) ? $remote['homepage'] : 'https://pressnative.app/docs',
			'package'      => $remote['download_url'],
			'tested'       => isset( $remote['tested'] ) ? $remote['tested'] : '',
			'requires'     => isset( $remote['requires'] ) ? $remote['requires'] : '6.0',
			'requires_php' => isset( $remote['requires_php'] ) ? $remote['requires_php'] : '7.4',
		);
	}
}
