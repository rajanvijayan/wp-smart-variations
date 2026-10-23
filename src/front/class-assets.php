<?php
/**
 * Scripts and styles.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Front;

use SwatchCraft\Settings;

/**
 * Registers the built scripts (build/*.js, made by wp-scripts) and enqueues them only where they
 * are used.
 */
final class Assets {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( self::class, 'register_assets' ) );
	}

	/**
	 * Data written by wp-scripts next to each build.
	 *
	 * @param string $name Entry.
	 * @return array { dependencies, version }
	 */
	public static function asset( $name ) {
		$file = SWATCHCRAFT_DIR . 'build/' . $name . '.asset.php';
		$data = file_exists( $file ) ? require $file : array();
		return array(
			'dependencies' => isset( $data['dependencies'] ) ? $data['dependencies'] : array(),
			'version'      => isset( $data['version'] ) ? $data['version'] : SWATCHCRAFT_VERSION,
		);
	}

	/**
	 * Register everything.
	 */
	public static function register_assets() {
		$front = self::asset( 'frontend' );
		// WooCommerce's variation form talks through jQuery events, so the script listens to them.
		wp_register_script( 'swatchcraft', SWATCHCRAFT_URL . 'build/frontend.js', array_merge( $front['dependencies'], array( 'jquery' ) ), $front['version'], array( 'strategy' => 'defer' ) );
		wp_set_script_translations( 'swatchcraft', 'wp-smart-variations' );
		wp_register_style( 'swatchcraft', SWATCHCRAFT_URL . 'build/frontend.css', array(), $front['version'] );

		$admin = self::asset( 'admin' );
		wp_register_script( 'swatchcraft-admin', SWATCHCRAFT_URL . 'build/admin.js', array_merge( $admin['dependencies'], array( 'jquery', 'wp-color-picker' ) ), $admin['version'], true );
		wp_set_script_translations( 'swatchcraft-admin', 'wp-smart-variations' );
		wp_register_style( 'swatchcraft-admin', SWATCHCRAFT_URL . 'build/admin.css', array( 'wp-color-picker' ), $admin['version'] );
	}

	/**
	 * Front-end script and style, with the settings the script needs.
	 */
	public static function enqueue() {
		wp_enqueue_style( 'swatchcraft' );
		if ( wp_script_is( 'swatchcraft', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script( 'swatchcraft' );
		$settings = Settings::get();
		$config   = array(
			'unavailable'  => $settings['unavailable'],
			'outOfStock'   => $settings['out_of_stock'],
			'showSelected' => $settings['show_selected'],
			'gallery'      => $settings['gallery'],
			'restUrl'      => esc_url_raw( rest_url( 'swatchcraft/v1/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'cartUrl'      => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
		);
		wp_add_inline_script( 'swatchcraft', 'window.swatchcraftConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Admin script and style, with the media library.
	 */
	public static function enqueue_admin() {
		wp_enqueue_media();
		wp_enqueue_script( 'swatchcraft-admin' );
		wp_enqueue_style( 'swatchcraft-admin' );
	}
}
