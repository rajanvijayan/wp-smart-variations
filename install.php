<?php
/**
 * Plugin Name:          SwatchCraft – Variation Swatches for WooCommerce
 * Plugin URI:           https://wordpress.org/plugins/wp-smart-variations/
 * Description:          Colour, image and button swatches for WooCommerce variations, on product pages and product cards, with variation image galleries and a variations table.
 * Version:              3.0.0-dev
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Rajan V
 * Author URI:           https://profiles.wordpress.org/rajanit2000/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          wp-smart-variations
 * WC requires at least: 9.0
 * WC tested up to:      11.2
 *
 * @package SwatchCraft
 */

defined( 'ABSPATH' ) || exit;

define( 'SWATCHCRAFT_VERSION', '3.0.0-dev' );
define( 'SWATCHCRAFT_FILE', __FILE__ );
define( 'SWATCHCRAFT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWATCHCRAFT_URL', plugin_dir_url( __FILE__ ) );

if ( ! file_exists( SWATCHCRAFT_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'SwatchCraft is missing its vendor folder. Run composer install, or install the plugin from WordPress.org.', 'wp-smart-variations' ) . '</p></div>';
		}
	);
	return;
}

require SWATCHCRAFT_DIR . 'vendor/autoload.php';

register_activation_hook( __FILE__, array( 'SwatchCraft\Plugin', 'activate' ) );

SwatchCraft\Plugin::boot();
