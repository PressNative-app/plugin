<?php
/**
 * Boots a real WordPress admin request with the plugin active and fails on any
 * PHP fatal.
 *
 * Syntax checks cannot catch an undefined class constant referenced from an
 * `admin_init` callback — that is a runtime Error, and in WordPress it takes
 * wp-admin down for every user on the site. This harness exercises the hooks
 * a real page load fires.
 *
 * Usage: php smoke-harness.php /path/to/wordpress
 *
 * @package PressNative
 */

$wp_root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
if ( '' === $wp_root || ! is_file( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "smoke-harness: pass the WordPress root (no wp-load.php found)\n" );
	exit( 1 );
}

define( 'WP_ADMIN', true );
define( 'WP_NETWORK_ADMIN', false );
define( 'WP_USER_ADMIN', false );

$_SERVER['REQUEST_URI']    = '/wp-admin/admin.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = 'localhost';
$_GET['page']              = 'pressnative';

register_shutdown_function(
	function () {
		$last = error_get_last();
		if ( $last && in_array( $last['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			fwrite( STDERR, "\nFATAL: {$last['message']} in {$last['file']}:{$last['line']}\n" );
			exit( 1 );
		}
	}
);

require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$failures = array();

function pressnative_smoke_step( $label, callable $fn ) {
	global $failures;
	try {
		ob_start();
		$fn();
		ob_end_clean();
		echo "  ok    {$label}\n";
	} catch ( Throwable $e ) {
		ob_end_clean();
		$failures[] = "{$label}: " . get_class( $e ) . ' ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
		echo "  FAIL  {$label}\n";
	}
}

if ( ! is_plugin_active( 'pressnative-apps/pressnative.php' ) ) {
	fwrite( STDERR, "smoke-harness: pressnative-apps is not active\n" );
	exit( 1 );
}
if ( ! defined( 'PRESSNATIVE_VERSION' ) ) {
	fwrite( STDERR, "smoke-harness: plugin did not bootstrap (PRESSNATIVE_VERSION undefined)\n" );
	exit( 1 );
}

echo 'WordPress ' . get_bloginfo( 'version' ) . ' / PHP ' . PHP_VERSION . ' / plugin ' . PRESSNATIVE_VERSION . "\n";

wp_set_current_user( 1 );

pressnative_smoke_step( 'admin_init', function () {
	do_action( 'admin_init' );
} );

pressnative_smoke_step( 'admin_menu', function () {
	do_action( '_admin_menu' );
	do_action( 'admin_menu' );
} );

pressnative_smoke_step( 'admin_notices', function () {
	do_action( 'admin_notices' );
} );

pressnative_smoke_step( 'admin_enqueue_scripts', function () {
	do_action( 'admin_enqueue_scripts', 'toplevel_page_pressnative' );
} );

pressnative_smoke_step( 'plugin update check', function () {
	$transient          = new stdClass();
	$transient->checked = array( 'pressnative-apps/pressnative.php' => PRESSNATIVE_VERSION );
	apply_filters( 'pre_set_site_transient_update_plugins', $transient );
} );

pressnative_smoke_step( 'rest_api_init', function () {
	rest_get_server();
} );

// Render every PressNative admin screen: a fatal inside a page callback is
// exactly what "wp-admin is broken" looks like.
global $menu, $submenu;
$screens = array();
foreach ( (array) $menu as $item ) {
	if ( ! empty( $item[2] ) && 0 === strpos( (string) $item[2], 'pressnative' ) ) {
		$screens[ $item[2] ] = $item[2];
	}
}
foreach ( (array) $submenu as $parent => $items ) {
	if ( 0 !== strpos( (string) $parent, 'pressnative' ) ) {
		continue;
	}
	foreach ( $items as $item ) {
		if ( ! empty( $item[2] ) ) {
			$screens[ $item[2] ] = $item[2];
		}
	}
}

if ( empty( $screens ) ) {
	$failures[] = 'admin menu: no PressNative screens were registered';
	echo "  FAIL  admin menu registration\n";
} else {
	echo '  ok    admin menu registration (' . count( $screens ) . " screens)\n";
}

global $wp_filter;
foreach ( $screens as $slug ) {
	$hook = 'toplevel_page_' . $slug;
	$page = isset( $wp_filter[ $hook ] ) ? $hook : 'pressnative_page_' . $slug;
	pressnative_smoke_step( "render {$slug}", function () use ( $slug ) {
		$_GET['page'] = $slug;
		$callback     = pressnative_smoke_find_callback( $slug );
		if ( $callback ) {
			call_user_func( $callback );
		}
	} );
}

/**
 * Locates the render callback WordPress stored for an admin page slug.
 *
 * @param string $slug Menu slug.
 * @return callable|null
 */
function pressnative_smoke_find_callback( $slug ) {
	global $menu, $submenu, $wp_filter;
	foreach ( array( 'toplevel_page_' . $slug, 'pressnative_page_' . $slug, 'admin_page_' . $slug ) as $hook ) {
		if ( empty( $wp_filter[ $hook ] ) ) {
			continue;
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $cb ) {
				if ( is_callable( $cb['function'] ) ) {
					return $cb['function'];
				}
			}
		}
	}
	return null;
}

// REST routes the mobile apps depend on must exist.
$routes   = rest_get_server()->get_routes();
$expected = array( '/pressnative/v1/layout/home', '/pressnative/v1/branding' );
foreach ( $expected as $route ) {
	if ( isset( $routes[ $route ] ) ) {
		echo "  ok    route {$route}\n";
	} else {
		$failures[] = "missing REST route {$route}";
		echo "  FAIL  route {$route}\n";
	}
}

// A REST layout response is what the apps actually consume.
pressnative_smoke_step( 'GET /pressnative/v1/layout/home', function () {
	$response = rest_do_request( new WP_REST_Request( 'GET', '/pressnative/v1/layout/home' ) );
	if ( $response->is_error() ) {
		$error = $response->as_error();
		throw new RuntimeException( 'REST error: ' . $error->get_error_message() );
	}
} );

if ( $failures ) {
	fwrite( STDERR, "\nWordPress smoke test failed:\n" );
	foreach ( $failures as $failure ) {
		fwrite( STDERR, "  - {$failure}\n" );
	}
	exit( 1 );
}

echo "\nWordPress smoke test passed.\n";
