<?php
/**
 * Checks pressnative.app for plugin updates and surfaces them in WordPress.
 *
 * Update-time failures must abort before WordPress deletes the installed
 * plugin. A bad zip must never take the rest of the site down.
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
	const MAIN_FILE     = 'pressnative.php';

	/**
	 * Registers WordPress update hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'after_upgrade' ), 10, 2 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'pre_download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_directory' ), 10, 4 );
		add_filter( 'upgrader_install_package_result', array( __CLASS__, 'verify_install_result' ), 10, 2 );
		add_filter( 'http_request_args', array( __CLASS__, 'package_request_args' ), 10, 2 );
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
		if ( empty( $args->slug ) ) {
			return $result;
		}
		$installed_slug = dirname( plugin_basename( PRESSNATIVE_PLUGIN_FILE ) );
		if ( self::SLUG !== $args->slug && $installed_slug !== $args->slug ) {
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
	 * Downloads our package ourselves so we can reject HTML/404 bodies and bad checksums
	 * before WordPress unzips anything.
	 *
	 * @param bool|WP_Error         $reply      Short-circuit value.
	 * @param string                $package    Package URL.
	 * @param WP_Upgrader           $upgrader   Upgrader.
	 * @param array<string, mixed>  $hook_extra Hook extra.
	 * @return bool|string|WP_Error False to use core download, path on success, WP_Error on failure.
	 */
	public static function pre_download( $reply, $package, $upgrader, $hook_extra = array() ) {
		unset( $upgrader );
		if ( false !== $reply ) {
			return $reply;
		}
		$cached     = get_site_transient( self::TRANSIENT_KEY );
		$is_our_url = is_array( $cached ) && ! empty( $cached['download_url'] ) && $package === $cached['download_url'];
		if ( ! self::is_our_upgrade( $hook_extra ) && ! $is_our_url ) {
			return $reply;
		}
		if ( ! is_string( $package ) || ! self::is_allowed_download_host( $package ) ) {
			return new WP_Error(
				'pressnative_download_host',
				__( 'PressNative refused this update because the download is not from GitHub. Your existing plugin was left unchanged.', 'pressnative-apps' )
			);
		}

		$remote = self::get_remote_info();
		$response = wp_remote_get(
			$package,
			array(
				'timeout'     => 60,
				'redirection' => 5,
				'sslverify'   => true,
				'headers'     => array(
					'Accept'     => 'application/octet-stream',
					'User-Agent' => 'PressNative-Apps/' . PRESSNATIVE_VERSION . '; ' . home_url(),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'pressnative_download_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'PressNative could not download the update (%s). Your existing plugin was left unchanged.', 'pressnative-apps' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'pressnative_download_http',
				sprintf(
					/* translators: %d: HTTP status */
					__( 'PressNative update download failed (HTTP %d). Your existing plugin was left unchanged.', 'pressnative-apps' ),
					$code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$len  = strlen( $body );
		if ( $len < 1024 || $len > 20 * 1024 * 1024 || 0 !== strpos( $body, 'PK' ) ) {
			return new WP_Error(
				'pressnative_download_not_zip',
				__( 'PressNative update was not a valid zip file (often a GitHub HTML error page). Your existing plugin was left unchanged.', 'pressnative-apps' )
			);
		}

		if ( is_array( $remote ) && ! empty( $remote['sha256'] ) && is_string( $remote['sha256'] ) ) {
			$actual = hash( 'sha256', $body );
			if ( ! hash_equals( strtolower( $remote['sha256'] ), strtolower( $actual ) ) ) {
				return new WP_Error(
					'pressnative_download_checksum',
					__( 'PressNative update failed the integrity check. Your existing plugin was left unchanged.', 'pressnative-apps' )
				);
			}
		}

		$tmp = wp_tempnam( 'pressnative-apps.zip' );
		if ( ! is_string( $tmp ) || '' === $tmp ) {
			return new WP_Error(
				'pressnative_download_tmp',
				__( 'PressNative could not create a temporary file for the update. Your existing plugin was left unchanged.', 'pressnative-apps' )
			);
		}
		if ( false === file_put_contents( $tmp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temp file before unzip
			return new WP_Error(
				'pressnative_download_write',
				__( 'PressNative could not save the update package. Your existing plugin was left unchanged.', 'pressnative-apps' )
			);
		}

		return $tmp;
	}

	/**
	 * Lengthen the HTTP timeout when WordPress core downloads our package.
	 *
	 * @param array  $args Request args.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public static function package_request_args( $args, $url ) {
		if ( ! is_array( $args ) || ! is_string( $url ) ) {
			return $args;
		}
		$remote = get_site_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $remote ) || empty( $remote['download_url'] ) || $url !== $remote['download_url'] ) {
			return $args;
		}
		$args['timeout'] = max( isset( $args['timeout'] ) ? (int) $args['timeout'] : 0, 60 );
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}
		$args['headers']['Accept'] = 'application/octet-stream';
		return $args;
	}

	/**
	 * Forces the extracted folder to match the installed plugin directory and
	 * aborts before destination-clear if pressnative.php is missing.
	 *
	 * @param string|WP_Error $source        Extracted source path.
	 * @param string          $remote_source Working directory.
	 * @param WP_Upgrader     $upgrader      Upgrader.
	 * @param array           $hook_extra    Hook extra.
	 * @return string|WP_Error
	 */
	public static function fix_source_directory( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		unset( $upgrader, $remote_source );
		if ( is_wp_error( $source ) || ! self::is_our_upgrade( $hook_extra ) ) {
			return $source;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return new WP_Error(
				'pressnative_update_fs',
				__( 'PressNative could not access the filesystem for this update. Your existing plugin was left unchanged.', 'pressnative-apps' )
			);
		}

		$source      = self::find_plugin_root( $source, $wp_filesystem );
		$plugin_file = plugin_basename( PRESSNATIVE_PLUGIN_FILE );
		$wanted_dir  = dirname( $plugin_file );
		if ( '.' === $wanted_dir || '' === $wanted_dir ) {
			$wanted_dir = self::SLUG;
		}

		$source_basename = basename( untrailingslashit( $source ) );
		if ( $source_basename === $wanted_dir ) {
			return $source;
		}

		$corrected = trailingslashit( dirname( untrailingslashit( $source ) ) ) . $wanted_dir;
		if ( $wp_filesystem->exists( $corrected ) ) {
			$wp_filesystem->delete( $corrected, true );
		}
		if ( ! $wp_filesystem->move( $source, $corrected ) ) {
			return new WP_Error(
				'pressnative_update_rename_failed',
				__( 'PressNative could not prepare the update package. Your existing plugin was left unchanged.', 'pressnative-apps' )
			);
		}

		return trailingslashit( $corrected );
	}

	/**
	 * After copy, confirm the main plugin file exists so WP 6.3+ can roll back.
	 *
	 * @param array|WP_Error $result     Install result.
	 * @param array          $hook_extra Hook extra.
	 * @return array|WP_Error
	 */
	public static function verify_install_result( $result, $hook_extra ) {
		if ( is_wp_error( $result ) || ! self::is_our_upgrade( $hook_extra ) ) {
			return $result;
		}

		$main = WP_PLUGIN_DIR . '/' . plugin_basename( PRESSNATIVE_PLUGIN_FILE );
		if ( ! is_readable( $main ) ) {
			return new WP_Error(
				'pressnative_install_missing_main',
				__( 'PressNative update did not install pressnative.php. WordPress will restore the previous version if possible.', 'pressnative-apps' )
			);
		}

		$src = file_get_contents( $main );
		if ( ! is_string( $src ) || false === strpos( $src, 'Plugin Name: PressNative Apps' ) ) {
			return new WP_Error(
				'pressnative_install_invalid_main',
				__( 'PressNative update did not look like PressNative Apps. WordPress will restore the previous version if possible.', 'pressnative-apps' )
			);
		}

		return $result;
	}

	/**
	 * Whether this upgrader run is for PressNative Apps.
	 *
	 * @param array $hook_extra Hook extra.
	 * @return bool
	 */
	private static function is_our_upgrade( $hook_extra ) {
		if ( ! is_array( $hook_extra ) || empty( $hook_extra['plugin'] ) ) {
			return false;
		}
		if ( ! defined( 'PRESSNATIVE_PLUGIN_FILE' ) ) {
			return false;
		}
		return plugin_basename( PRESSNATIVE_PLUGIN_FILE ) === $hook_extra['plugin'];
	}

	/**
	 * Finds the directory that actually contains pressnative.php.
	 *
	 * @param string             $source        Current source.
	 * @param WP_Filesystem_Base $wp_filesystem Filesystem.
	 * @return string|WP_Error
	 */
	private static function find_plugin_root( $source, $wp_filesystem ) {
		$source = trailingslashit( $source );
		if ( $wp_filesystem->exists( $source . self::MAIN_FILE ) ) {
			return $source;
		}

		$nested = $source . self::SLUG . '/' . self::MAIN_FILE;
		if ( $wp_filesystem->exists( $nested ) ) {
			return trailingslashit( $source . self::SLUG );
		}

		$dirlist = $wp_filesystem->dirlist( $source );
		if ( is_array( $dirlist ) ) {
			foreach ( array_keys( $dirlist ) as $entry ) {
				if ( '.' === $entry || '..' === $entry || '__MACOSX' === $entry ) {
					continue;
				}
				$candidate = $source . $entry . '/' . self::MAIN_FILE;
				if ( $wp_filesystem->exists( $candidate ) ) {
					return trailingslashit( $source . $entry );
				}
			}
		}

		return new WP_Error(
			'pressnative_invalid_package',
			__( 'The PressNative update package is missing pressnative.php. Your existing plugin was left unchanged.', 'pressnative-apps' )
		);
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
				'timeout'   => 8,
				'sslverify' => true,
				'headers'   => array(
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
		if ( isset( $remote['sha256'] ) && ! is_string( $remote['sha256'] ) ) {
			return false;
		}
		if ( ! empty( $remote['sha256'] ) && ! preg_match( '/^[a-f0-9]{64}$/i', $remote['sha256'] ) ) {
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
