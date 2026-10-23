<?php
/**
 * REST endpoint for the settings page.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Rest;

use SwatchCraft\Settings;
use WP_REST_Request;
use WP_REST_Server;

/**
 * GET/POST /swatchcraft/v1/settings, for shop managers.
 */
final class Settings_Controller {

	/**
	 * Register the route.
	 */
	public static function register_routes() {
		register_rest_route(
			'swatchcraft/v1',
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static function () {
						return Settings::get();
					},
					'permission_callback' => array( self::class, 'permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'save' ),
					'permission_callback' => array( self::class, 'permission' ),
				),
			)
		);
	}

	/**
	 * Shop managers only.
	 *
	 * @return bool
	 */
	public static function permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Save any subset of the settings (each value is sanitized against the defaults).
	 *
	 * @param WP_REST_Request $request Request with a JSON body.
	 * @return array Saved settings.
	 */
	public static function save( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		return Settings::save( is_array( $data ) ? $data : array() );
	}
}
