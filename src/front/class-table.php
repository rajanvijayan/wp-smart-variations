<?php
/**
 * Variations table with bulk add to cart.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Front;

use SwatchCraft\Data\Swatch_Data;
use SwatchCraft\Settings;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Block;

/**
 * A table of every variation with image, options, price, stock and a quantity, and one button to
 * add them all. It is a real form posted to the product page, so it works without JavaScript;
 * the script sends it to the REST API instead and refreshes the mini cart.
 */
final class Table {

	const ACTION = 'swatchcraft_bulk_add';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( self::class, 'register_block' ) );
		add_shortcode( 'swatchcraft_table', array( self::class, 'shortcode' ) );
		add_action( 'wp', array( self::class, 'positions' ) );
		// The classic add-to-cart block fires the template hooks above; the newer one does not.
		add_filter( 'render_block_woocommerce/add-to-cart-with-options', array( self::class, 'cart_block' ), 20, 2 );
		add_action( 'wp_loaded', array( self::class, 'handle_post' ), 25 );
	}

	/**
	 * The "Variations Table" block.
	 */
	public static function register_block() {
		$asset = Assets::asset( 'blocks' );
		wp_register_script( 'swatchcraft-blocks', SWATCHCRAFT_URL . 'build/blocks.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'swatchcraft-blocks', 'wp-smart-variations' );
		register_block_type( SWATCHCRAFT_DIR . 'blocks/variations-table', array( 'render_callback' => array( self::class, 'block' ) ) );
	}

	/**
	 * Where the table goes on this product page: '' (nowhere), after_cart or replace_cart.
	 *
	 * @param WC_Product_Variable $product Product.
	 * @return string
	 */
	public static function mode( WC_Product_Variable $product ) {
		$override = Swatch_Data::product( $product->get_id() )['table'];
		$global   = Settings::get( 'table' );
		if ( 'hide' === $override ) {
			return '';
		}
		if ( 'show' === $override ) {
			return 'off' === $global ? 'after_cart' : $global;
		}
		return 'off' === $global ? '' : $global;
	}

	/**
	 * Classic templates: after the variation form, or instead of it.
	 */
	public static function positions() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product instanceof WC_Product_Variable ) {
			return;
		}
		$mode = self::mode( $product );
		if ( 'replace_cart' === $mode ) {
			remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
			add_action( 'woocommerce_variable_add_to_cart', array( self::class, 'output' ), 30 );
		} elseif ( 'after_cart' === $mode ) {
			add_action( 'woocommerce_after_add_to_cart_form', array( self::class, 'output' ) );
		}
	}

	/**
	 * Classic hook output.
	 */
	public static function output() {
		echo self::html( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html().
	}

	/**
	 * WooCommerce's "Add to Cart with Options" block, which does not run the classic hooks.
	 *
	 * @param string $html  Block HTML.
	 * @param array  $block Block.
	 * @return string
	 */
	public static function cart_block( $html, $block ) {
		unset( $block );
		$product = is_singular( 'product' ) ? wc_get_product( get_queried_object_id() ) : null;
		if ( ! $product instanceof WC_Product_Variable ) {
			return $html;
		}
		$mode = self::mode( $product );
		if ( 'replace_cart' === $mode ) {
			return self::html( $product->get_id() );
		}
		return 'after_cart' === $mode ? $html . self::html( $product->get_id() ) : $html;
	}

	/**
	 * Block render.
	 *
	 * @param array    $attributes Attributes.
	 * @param string   $content    Content.
	 * @param WP_Block $block      Block.
	 * @return string
	 */
	public static function block( $attributes, $content = '', $block = null ) {
		$id = ! empty( $attributes['productId'] ) ? (int) $attributes['productId'] : 0;
		if ( ! $id && $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
			$id = (int) $block->context['postId'];
		}
		$id   = $id ? $id : get_the_ID();
		$html = self::html( $id );
		if ( '' === $html ) {
			return '';
		}
		$wrapper = class_exists( 'WP_Block_Supports' ) && ! empty( \WP_Block_Supports::$block_to_render ) ? get_block_wrapper_attributes() : 'class="wp-block-swatchcraft-variations-table"';
		return '<div ' . $wrapper . '>' . $html . '</div>';
	}

	/**
	 * [swatchcraft_table id=""]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'swatchcraft_table' );
		return self::html( $atts['id'] ? absint( $atts['id'] ) : get_the_ID() );
	}

	/**
	 * Table markup.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function html( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product_Variable ) {
			return '';
		}
		$rows = '';
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( $variation instanceof WC_Product_Variation && $variation->variation_is_visible() ) {
				$rows .= self::row( $variation );
			}
		}
		if ( '' === $rows ) {
			return '';
		}
		Assets::enqueue();
		$head = sprintf(
			'<thead><tr><th scope="col"><span class="screen-reader-text">%1$s</span></th><th scope="col">%2$s</th><th scope="col">%3$s</th><th scope="col">%4$s</th><th scope="col">%5$s</th></tr></thead>',
			esc_html__( 'Image', 'wp-smart-variations' ),
			esc_html__( 'Option', 'wp-smart-variations' ),
			esc_html__( 'Price', 'wp-smart-variations' ),
			esc_html__( 'Availability', 'wp-smart-variations' ),
			esc_html__( 'Quantity', 'wp-smart-variations' )
		);
		return sprintf(
			'<form class="swatchcraft-table" method="post" action="%1$s" data-swatchcraft-table data-product="%2$d"><input type="hidden" name="%3$s" value="%2$d" />%4$s<div class="swatchcraft-table__scroll"><table class="shop_table">%5$s<tbody>%6$s</tbody></table></div><p class="swatchcraft-table__footer"><span class="swatchcraft-table__message" role="status" aria-live="polite"></span><button type="submit" class="button alt single_add_to_cart_button%7$s">%8$s</button></p></form>',
			esc_url( get_permalink( $product->get_id() ) ),
			(int) $product->get_id(),
			esc_attr( self::ACTION ),
			wp_nonce_field( self::ACTION, '_swatchcraft_nonce', true, false ),
			$head,
			$rows,
			function_exists( 'wc_wp_theme_get_element_class_name' ) && wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ) : '',
			esc_html__( 'Add selected to cart', 'wp-smart-variations' )
		);
	}

	/**
	 * One row.
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @return string
	 */
	private static function row( WC_Product_Variation $variation ) {
		$image    = $variation->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '' ) );
		$name     = wc_get_formatted_variation( $variation, true, false, false );
		$can_buy  = $variation->is_purchasable() && $variation->is_in_stock() && null !== Cart::attributes( $variation );
		$max      = $variation->get_max_purchase_quantity();
		$quantity = $can_buy
			? sprintf(
				'<label class="screen-reader-text" for="swatchcraft-qty-%1$d">%2$s</label><input type="number" id="swatchcraft-qty-%1$d" class="input-text qty text" name="swatchcraft_qty[%1$d]" value="0" min="0" step="1"%3$s inputmode="numeric" />',
				(int) $variation->get_id(),
				/* translators: %s: variation options. */
				esc_html( sprintf( __( 'Quantity of %s', 'wp-smart-variations' ), wp_strip_all_tags( $name ) ) ),
				$max > 0 ? ' max="' . (int) $max . '"' : ''
			)
			: ( null === Cart::attributes( $variation ) && $variation->is_in_stock()
				? '<a href="' . esc_url( $variation->get_permalink() ) . '">' . esc_html__( 'Choose options', 'wp-smart-variations' ) . '</a>'
				: '&mdash;' );
		$stock = wc_get_stock_html( $variation );
		return sprintf(
			'<tr data-variation="%1$d"><td class="swatchcraft-table__image">%2$s</td><td class="swatchcraft-table__name">%3$s</td><td class="swatchcraft-table__price">%4$s</td><td class="swatchcraft-table__stock">%5$s</td><td class="swatchcraft-table__qty">%6$s</td></tr>',
			(int) $variation->get_id(),
			$image,
			esc_html( wp_strip_all_tags( $name ) ),
			wp_kses_post( $variation->get_price_html() ),
			$stock ? wp_kses_post( $stock ) : '<span class="stock in-stock">' . esc_html__( 'In stock', 'wp-smart-variations' ) . '</span>',
			$quantity
		);
	}

	/**
	 * The form posted without JavaScript.
	 */
	public static function handle_post() {
		if ( ! isset( $_POST[ self::ACTION ] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( ! isset( $_POST['_swatchcraft_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_swatchcraft_nonce'] ) ), self::ACTION ) ) {
			wc_add_notice( __( 'Your session has expired. Please try again.', 'wp-smart-variations' ), 'error' );
			return;
		}
		$product_id = absint( $_POST[ self::ACTION ] );
		$quantities = isset( $_POST['swatchcraft_qty'] ) && is_array( $_POST['swatchcraft_qty'] ) ? array_map( 'absint', wp_unslash( $_POST['swatchcraft_qty'] ) ) : array();
		$result     = Cart::add( $product_id, $quantities );
		foreach ( $result['errors'] as $error ) {
			wc_add_notice( $error, 'error' );
		}
		if ( $result['added'] ) {
			/* translators: %d: number of products. */
			wc_add_notice( sprintf( _n( '%d product was added to your cart.', '%d products were added to your cart.', $result['added'], 'wp-smart-variations' ), $result['added'] ) );
		} elseif ( ! $result['errors'] && ! wc_notice_count( 'error' ) ) {
			wc_add_notice( __( 'Enter a quantity for at least one option.', 'wp-smart-variations' ), 'notice' );
		}
	}
}
