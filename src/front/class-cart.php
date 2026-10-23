<?php
/**
 * Adding several variations to the cart at once.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Front;

use WC_Product_Variable;
use WC_Product_Variation;

/**
 * Shared by the variations table form (works without JavaScript) and the REST endpoint the
 * table's script uses.
 */
final class Cart {

	/**
	 * Add variations of a product to the cart.
	 *
	 * @param int   $product_id Variable product ID.
	 * @param array $quantities Variation ID => quantity.
	 * @return array { added: int (products added), errors: string[] }
	 */
	public static function add( $product_id, array $quantities ) {
		$out     = array(
			'added'  => 0,
			'errors' => array(),
		);
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product_Variable || ! function_exists( 'WC' ) || ! WC()->cart ) {
			$out['errors'][] = __( 'This product cannot be added to the cart.', 'wp-smart-variations' );
			return $out;
		}
		$children = array_map( 'intval', $product->get_children() );
		foreach ( $quantities as $variation_id => $quantity ) {
			$variation_id = absint( $variation_id );
			$quantity     = max( 0, min( 9999, (int) $quantity ) );
			if ( ! $quantity ) {
				continue;
			}
			$variation = in_array( $variation_id, $children, true ) ? wc_get_product( $variation_id ) : null;
			if ( ! $variation instanceof WC_Product_Variation || ! $variation->is_purchasable() || ! $variation->variation_is_visible() ) {
				$out['errors'][] = __( 'One of the chosen variations is not available.', 'wp-smart-variations' );
				continue;
			}
			$attributes = self::attributes( $variation );
			if ( null === $attributes ) {
				/* translators: %s: variation name. */
				$out['errors'][] = sprintf( __( 'Choose the options of "%s" on the product page.', 'wp-smart-variations' ), $variation->get_name() );
				continue;
			}
			// WooCommerce validates stock and limits, and adds its own notices on failure.
			if ( WC()->cart->add_to_cart( $product->get_id(), $quantity, $variation_id, $attributes ) ) {
				++$out['added'];
			}
		}
		return $out;
	}

	/**
	 * A variation's attributes for the cart, or null when one of them is "any" (the shopper has to
	 * choose it on the product page).
	 *
	 * @param WC_Product_Variation $variation Variation.
	 * @return array|null
	 */
	public static function attributes( WC_Product_Variation $variation ) {
		$out = array();
		foreach ( $variation->get_variation_attributes() as $name => $value ) {
			if ( '' === $value ) {
				return null;
			}
			$out[ $name ] = $value;
		}
		return $out;
	}
}
