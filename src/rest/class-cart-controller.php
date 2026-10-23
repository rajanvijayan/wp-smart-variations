<?php
/**
 * REST endpoint for the variations table.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Rest;

use SwatchCraft\Front\Cart;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * POST /swatchcraft/v1/cart { product_id, items: { variation_id: quantity } }.
 *
 * Open to visitors (anyone can fill a cart), protected from cross-site requests by the REST nonce
 * the page prints.
 */
final class Cart_Controller {

	/**
	 * Register the route.
	 */
	public static function register_routes() {
		register_rest_route(
			'swatchcraft/v1',
			'/cart',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'add' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'product_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'items'      => array(
						'type'                 => 'object',
						'required'             => true,
						'additionalProperties' => array(
							'type'    => 'integer',
							'minimum' => 0,
							'maximum' => 9999,
						),
						'maxProperties'        => 200,
					),
				),
			)
		);
	}

	/**
	 * Check the REST nonce (the cookie-authenticated request of a logged-out shopper is otherwise
	 * not verified by WordPress).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function permission( WP_REST_Request $request ) {
		if ( ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new WP_Error( 'swatchcraft_nonce', __( 'Your session has expired. Reload the page and try again.', 'wp-smart-variations' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Add the items.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function add( WP_REST_Request $request ) {
		// The REST API does not load the session and cart on its own.
		if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || ! WC()->cart ) ) {
			wc_load_cart();
		}
		$result = Cart::add( (int) $request['product_id'], (array) $request['items'] );
		if ( function_exists( 'wc_get_notices' ) ) {
			foreach ( wc_get_notices( 'error' ) as $notice ) {
				$result['errors'][] = wp_strip_all_tags( is_array( $notice ) ? $notice['notice'] : $notice );
			}
			wc_clear_notices();
		}
		$result['errors']   = array_values( array_unique( $result['errors'] ) );
		$result['count']    = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		$result['cart_url'] = wc_get_cart_url();
		return rest_ensure_response( $result );
	}
}
