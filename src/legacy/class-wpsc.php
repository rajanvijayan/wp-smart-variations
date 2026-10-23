<?php
/**
 * WP eCommerce compatibility (the 1.0 behaviour).
 *
 * @package SwatchCraft\Legacy
 */

namespace SwatchCraft\Legacy;

/**
 * Version 1.0 turned WP eCommerce variation dropdowns into radio buttons. Sites that still run
 * WP eCommerce keep that behaviour, now from an enqueued script instead of inline code.
 */
final class Wpsc {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Whether WP eCommerce is running.
	 *
	 * @return bool
	 */
	public static function active() {
		return class_exists( 'WP_eCommerce' ) || post_type_exists( 'wpsc-product' );
	}

	/**
	 * Enqueue on WP eCommerce sites.
	 */
	public static function enqueue() {
		if ( ! self::active() ) {
			return;
		}
		wp_enqueue_script( 'swatchcraft-wpsc', SWATCHCRAFT_URL . 'assets/legacy/wpsc.js', array(), SWATCHCRAFT_VERSION, array( 'strategy' => 'defer' ) );
		wp_enqueue_style( 'swatchcraft-wpsc', SWATCHCRAFT_URL . 'assets/legacy/wpsc.css', array(), SWATCHCRAFT_VERSION );
	}
}
