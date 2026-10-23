<?php
/**
 * WooCommerce → SwatchCraft settings page.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Admin;

use SwatchCraft\Data\Swatch_Data;
use SwatchCraft\Front\Assets;

/**
 * Adds the page and loads the settings app (assets/src/settings), which reads and saves through
 * the swatchcraft/v1/settings REST route. The front-end stylesheet is loaded too, for the preview.
 */
final class Settings_Page {

	const SLUG = 'swatchcraft';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( self::class, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Submenu under WooCommerce.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'SwatchCraft settings', 'wp-smart-variations' ),
			__( 'SwatchCraft', 'wp-smart-variations' ),
			'manage_woocommerce',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Page markup: the app mounts here.
	 */
	public static function render() {
		echo '<div class="wrap"><div id="swatchcraft-settings"><p>' . esc_html__( 'Loading…', 'wp-smart-variations' ) . '</p></div></div>';
	}

	/**
	 * Settings app scripts, on this page only.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue( $hook ) {
		if ( 'woocommerce_page_' . self::SLUG !== $hook ) {
			return;
		}
		$asset = Assets::asset( 'settings' );
		wp_enqueue_script( 'swatchcraft-settings', SWATCHCRAFT_URL . 'build/settings.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'swatchcraft-settings', SWATCHCRAFT_URL . 'build/settings.css', array( 'wp-components', 'swatchcraft' ), $asset['version'] );
		wp_set_script_translations( 'swatchcraft-settings', 'wp-smart-variations' );
		wp_add_inline_script(
			'swatchcraft-settings',
			'window.swatchcraftSettings = ' . wp_json_encode( self::config() ) . ';',
			'before'
		);
	}

	/**
	 * Data for the app: version, swatch types and the store's attributes.
	 *
	 * @return array
	 */
	public static function config() {
		$attributes = array();
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$taxonomy     = wc_attribute_taxonomy_name( $attribute->attribute_name );
			$settings     = Swatch_Data::attribute( $taxonomy );
			$attributes[] = array(
				'slug'     => $attribute->attribute_name,
				'label'    => $attribute->attribute_label ? $attribute->attribute_label : $attribute->attribute_name,
				'type'     => $settings['type'],
				'loop'     => $settings['loop'],
				'editUrl'  => admin_url( 'edit.php?post_type=product&page=product_attributes&edit=' . (int) $attribute->attribute_id ),
				'termsUrl' => admin_url( 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=product' ),
			);
		}
		return array(
			'version'    => SWATCHCRAFT_VERSION,
			'types'      => Swatch_Data::type_labels(),
			'attributes' => $attributes,
			'newUrl'     => admin_url( 'edit.php?post_type=product&page=product_attributes' ),
		);
	}
}
