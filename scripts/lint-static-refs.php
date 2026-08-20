<?php
/**
 * Static reference linter.
 *
 * `php -l` only proves a file parses. It cannot see that
 * `self::OPTION_CONSENT_REQUIRED` was never declared — that only explodes at
 * runtime, and in WordPress a runtime Error during admin_init takes wp-admin
 * down for the whole site.
 *
 * This loads every plugin class and verifies each `Class::CONSTANT`,
 * `Class::method()` and `Class::$prop` reference actually resolves.
 *
 * Usage: php scripts/lint-static-refs.php [plugin-root]
 * Exits non-zero when a reference is unresolvable.
 *
 * @package PressNative
 */

$root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__ );

if ( ! is_file( $root . '/pressnative.php' ) ) {
	fwrite( STDERR, "lint-static-refs: no pressnative.php in {$root}\n" );
	exit( 1 );
}

define( 'ABSPATH', $root . '/' );
define( 'PRESSNATIVE_PLUGIN_DIR', $root . '/' );
define( 'PRESSNATIVE_PLUGIN_FILE', $root . '/pressnative.php' );
define( 'PRESSNATIVE_VERSION', '0.0.0' );
define( 'WP_PLUGIN_DIR', dirname( $root ) );

// Class files may self-register hooks at include time, so stub the WordPress
// surface they touch. Anything unstubbed surfaces as a clear fatal below.
$stubs = array(
	'add_action', 'add_filter', 'remove_action', 'remove_filter', 'add_shortcode',
	'register_setting', 'do_action', 'apply_filters', '__', 'esc_html__', 'esc_attr__',
	'plugin_basename', 'plugin_dir_path', 'plugin_dir_url', 'register_activation_hook',
	'register_deactivation_hook', 'wp_parse_url', 'trailingslashit', 'untrailingslashit',
);
foreach ( $stubs as $fn ) {
	if ( function_exists( $fn ) ) {
		continue;
	}
	eval( 'function ' . $fn . '() { return null; }' );
}

$files = array();
foreach ( array( '/includes', '/lib' ) as $dir ) {
	if ( ! is_dir( $root . $dir ) ) {
		continue;
	}
	foreach ( glob( $root . $dir . '/*.php' ) as $file ) {
		if ( basename( $file ) === 'index.php' ) {
			continue;
		}
		$files[] = $file;
	}
}
sort( $files );

// Include twice: the first pass tolerates load order, the second resolves
// classes whose parents/dependencies only existed after the first pass.
foreach ( array( 1, 2 ) as $pass ) {
	foreach ( $files as $file ) {
		require_once $file;
	}
}

$known = array();
foreach ( get_declared_classes() as $class ) {
	$rc = new ReflectionClass( $class );
	$file = $rc->getFileName();
	if ( $file && 0 === strpos( $file, $root . '/' ) ) {
		$known[ $class ] = $rc;
	}
}

if ( empty( $known ) ) {
	fwrite( STDERR, "lint-static-refs: no plugin classes loaded\n" );
	exit( 1 );
}

$lc_known = array();
foreach ( $known as $name => $rc ) {
	$lc_known[ strtolower( $name ) ] = $name;
}

$scan = $files;
$scan[] = $root . '/pressnative.php';
$scan[] = $root . '/uninstall.php';

$errors = array();

foreach ( $scan as $file ) {
	if ( ! is_file( $file ) ) {
		continue;
	}
	$tokens  = token_get_all( file_get_contents( $file ) );
	$count   = count( $tokens );
	$current = '';
	$depth   = 0;

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( is_array( $token ) && T_CLASS === $token[0] ) {
			for ( $j = $i + 1; $j < $count; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
					$current = $tokens[ $j ][1];
					break;
				}
			}
			continue;
		}

		if ( ! is_array( $token ) || T_DOUBLE_COLON !== $token[0] ) {
			continue;
		}

		// Left of `::` is the class (or self/static/parent).
		$left = null;
		for ( $j = $i - 1; $j >= 0; $j-- ) {
			if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$left = $tokens[ $j ];
			break;
		}
		if ( ! is_array( $left ) ) {
			continue;
		}
		$lname = $left[1];
		if ( ! in_array( $left[0], array( T_STRING, T_STATIC, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED ), true ) ) {
			continue;
		}

		if ( in_array( strtolower( $lname ), array( 'self', 'static', '__class__' ), true ) ) {
			$class = $current;
		} else {
			$class = ltrim( $lname, '\\' );
		}
		if ( '' === $class || ! isset( $lc_known[ strtolower( $class ) ] ) ) {
			continue; // Not one of ours (WordPress core, PHP, third party).
		}
		$rc = $known[ $lc_known[ strtolower( $class ) ] ];

		// Right of `::` is the member.
		$right = null;
		for ( $j = $i + 1; $j < $count; $j++ ) {
			if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$right     = $tokens[ $j ];
			$right_pos = $j;
			break;
		}
		if ( null === $right ) {
			continue;
		}

		$line = is_array( $right ) ? $right[2] : $left[2];
		$rel  = ltrim( str_replace( $root, '', $file ), '/' );

		if ( is_array( $right ) && T_CLASS === $right[0] ) {
			continue; // ::class is always fine.
		}

		if ( is_array( $right ) && T_VARIABLE === $right[0] ) {
			$prop = ltrim( $right[1], '$' );
			if ( ! $rc->hasProperty( $prop ) ) {
				$errors[] = "{$rel}:{$line}  {$class}::\${$prop} is not declared";
			}
			continue;
		}

		if ( ! is_array( $right ) || T_STRING !== $right[0] ) {
			continue; // Dynamic access such as ::{$name}.
		}
		$member = $right[1];

		// Method call when followed by `(`.
		$next = null;
		for ( $j = $right_pos + 1; $j < $count; $j++ ) {
			if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$next = $tokens[ $j ];
			break;
		}

		if ( '(' === $next ) {
			if ( ! $rc->hasMethod( $member ) && ! $rc->hasMethod( '__callStatic' ) ) {
				$errors[] = "{$rel}:{$line}  {$class}::{$member}() is not defined";
			}
			continue;
		}

		if ( ! $rc->hasConstant( $member ) ) {
			$errors[] = "{$rel}:{$line}  {$class}::{$member} is not defined";
		}
	}
}

$errors = array_values( array_unique( $errors ) );

if ( $errors ) {
	fwrite( STDERR, "\nUnresolved static references (these are runtime fatals in WordPress):\n" );
	foreach ( $errors as $error ) {
		fwrite( STDERR, "  {$error}\n" );
	}
	fwrite( STDERR, "\n" . count( $errors ) . " problem(s) found.\n" );
	exit( 1 );
}

printf( "Static references OK (%d classes, %d files).\n", count( $known ), count( $scan ) );
