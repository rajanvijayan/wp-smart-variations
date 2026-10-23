<?php
/**
 * PHPUnit bootstrap. Set WP_TESTS_DIR to the WordPress test library (bin/install-wp-tests.sh) and
 * WC_DIR to an unpacked WooCommerce plugin.
 *
 * @package SwatchCraft
 */

$swatchcraft_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $swatchcraft_tests_dir ) {
	$swatchcraft_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}
$swatchcraft_wc_dir = getenv( 'WC_DIR' );
if ( ! $swatchcraft_wc_dir ) {
	$swatchcraft_wc_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/woocommerce';
}
foreach ( array( $swatchcraft_tests_dir . '/includes/functions.php', $swatchcraft_wc_dir . '/woocommerce.php' ) as $swatchcraft_required ) {
	if ( ! file_exists( $swatchcraft_required ) ) {
		echo "Could not find $swatchcraft_required\n"; // phpcs:ignore
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $swatchcraft_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $swatchcraft_wc_dir ) {
		require $swatchcraft_wc_dir . '/woocommerce.php';
		require dirname( __DIR__ ) . '/install.php';
	}
);

// Install WooCommerce's tables and roles once, as WooCommerce's own test suite does.
tests_add_filter(
	'setup_theme',
	static function () {
		WC_Install::install();
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();
	}
);

require $swatchcraft_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-testcase.php';
