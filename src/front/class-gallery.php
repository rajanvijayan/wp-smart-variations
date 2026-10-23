<?php
/**
 * Variation image galleries.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Front;

use SwatchCraft\Settings;
use WC_Product;
use WC_Product_Variation;
use WP_Post;

/**
 * Extra images per variation. In the admin they are picked in each variation panel; on the
 * product page the script swaps the whole product gallery for the chosen variation's images,
 * built with WooCommerce's own gallery markup (wc_get_gallery_image_html), and restores it when
 * the choice is cleared.
 */
final class Gallery {

	const META = '_swatchcraft_gallery';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'woocommerce_product_after_variable_attributes', array( self::class, 'field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( self::class, 'save' ), 10, 2 );
		add_filter( 'woocommerce_available_variation', array( self::class, 'data' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Admin script on the product editor.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && 'product' === $screen->post_type ) {
			Assets::enqueue_admin();
		}
	}

	/**
	 * Image IDs of a variation's gallery.
	 *
	 * @param int $variation_id Variation ID.
	 * @return int[]
	 */
	public static function ids( $variation_id ) {
		$stored = get_post_meta( $variation_id, self::META, true );
		$ids    = is_array( $stored ) ? $stored : explode( ',', (string) $stored );
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Field in a variation panel.
	 *
	 * @param int     $loop      Variation index.
	 * @param array   $data      Variation data.
	 * @param WP_Post $variation Variation post.
	 */
	public static function field( $loop, $data, $variation ) {
		$ids     = self::ids( $variation->ID );
		$preview = '';
		foreach ( $ids as $id ) {
			$preview .= wp_get_attachment_image( $id, 'thumbnail', false, array( 'class' => 'swatchcraft-media__preview' ) );
		}
		printf(
			'<div class="form-row form-row-full swatchcraft-gallery-field"><label>%1$s</label><div class="swatchcraft-media" data-swatchcraft-media data-multiple="1"><input type="hidden" name="swatchcraft_gallery[%2$d]" value="%3$s" />%4$s<button type="button" class="button" data-action="choose">%5$s</button> <button type="button" class="button-link button-link-delete" data-action="remove"%6$s>%7$s</button></div><p class="description">%8$s</p></div>',
			esc_html__( 'Variation gallery', 'wp-smart-variations' ),
			(int) $loop,
			esc_attr( implode( ',', $ids ) ),
			$preview, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image().
			esc_html__( 'Choose images', 'wp-smart-variations' ),
			$ids ? '' : ' hidden',
			esc_html__( 'Remove all', 'wp-smart-variations' ),
			esc_html__( 'Shown in the product gallery, after the variation image, when this variation is chosen.', 'wp-smart-variations' )
		);
		unset( $data );
	}

	/**
	 * Save from a variation panel (WooCommerce checks the nonce and capability before this runs).
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation index.
	 */
	public static function save( $variation_id, $loop ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce (save-variations).
		if ( ! isset( $_POST['swatchcraft_gallery'][ $loop ] ) || ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}
		$raw = sanitize_text_field( wp_unslash( $_POST['swatchcraft_gallery'][ $loop ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) ) );
		$ids = array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return wp_attachment_is_image( $id );
				}
			)
		);
		if ( $ids ) {
			update_post_meta( $variation_id, self::META, $ids );
		} else {
			delete_post_meta( $variation_id, self::META );
		}
	}

	/**
	 * Gallery markup in the variation data WooCommerce gives its script.
	 *
	 * @param array                $data      Variation data.
	 * @param WC_Product           $product   Parent product.
	 * @param WC_Product_Variation $variation Variation.
	 * @return array
	 */
	public static function data( $data, $product, $variation ) {
		if ( ! Settings::get( 'gallery' ) || ! function_exists( 'wc_get_gallery_image_html' ) ) {
			return $data;
		}
		$ids = self::ids( $variation->get_id() );
		if ( ! $ids ) {
			return $data;
		}
		$main = $variation->get_image_id() ? (int) $variation->get_image_id() : (int) $product->get_image_id();
		$all  = array_values( array_unique( array_merge( $main ? array( $main ) : array(), $ids ) ) );
		$html = array();
		foreach ( $all as $index => $id ) {
			$html[] = wc_get_gallery_image_html( $id, 0 === $index );
		}
		$data['swatchcraft_gallery'] = $html;
		return $data;
	}
}
