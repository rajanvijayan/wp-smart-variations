<?php
/**
 * Plugin bootstrap.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft;

/**
 * Wires the plugin into WordPress.
 */
final class Plugin {

	/**
	 * Whether boot() ran.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Register hooks.
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		Legacy\Wpsc::register();
		Front\Assets::register();
		add_action( 'before_woocommerce_init', array( self::class, 'compatibility' ) );
		add_action( 'plugins_loaded', array( self::class, 'woocommerce' ), 20 );
		add_filter( 'plugin_action_links_' . plugin_basename( SWATCHCRAFT_FILE ), array( self::class, 'action_links' ) );
	}

	/**
	 * Everything that needs WooCommerce.
	 */
	public static function woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			if ( ! Legacy\Wpsc::active() ) {
				add_action( 'admin_notices', array( self::class, 'missing_woocommerce' ) );
			}
			return;
		}
		Admin\Attribute_Fields::register();
		Admin\Term_Fields::register();
		Admin\Product_Tab::register();
		Admin\Settings_Page::register();
		Front\Swatches::register();
		Front\Loop_Swatches::register();
		Front\Gallery::register();
		Front\Table::register();
		add_action( 'rest_api_init', array( Rest\Cart_Controller::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( Rest\Settings_Controller::class, 'register_routes' ) );
		/**
		 * Fires when SwatchCraft has loaded with WooCommerce (for extensions).
		 */
		do_action( 'swatchcraft_loaded' );
	}

	/**
	 * Declare support for order storage tables and the cart and checkout blocks.
	 */
	public static function compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SWATCHCRAFT_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SWATCHCRAFT_FILE, true );
		}
	}

	/**
	 * Notice when WooCommerce is not active.
	 */
	public static function missing_woocommerce() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'SwatchCraft needs WooCommerce. Install and activate WooCommerce to use variation swatches.', 'wp-smart-variations' ) . '</p></div>';
	}

	/**
	 * Activation: nothing to migrate from 1.0 (it stored no data); keep the default settings.
	 */
	public static function activate() {
		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		if ( class_exists( 'WooCommerce' ) ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=swatchcraft' ) ) . '">' . esc_html__( 'Settings', 'wp-smart-variations' ) . '</a>' );
		}
		return $links;
	}
}
