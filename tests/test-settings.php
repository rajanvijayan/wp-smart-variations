<?php
/**
 * Settings page and its REST route.
 *
 * @package SwatchCraft
 */

use SwatchCraft\Admin\Settings_Page;
use SwatchCraft\Data\Swatch_Data;
use SwatchCraft\Settings;

/**
 * Settings.
 */
class Test_Settings extends SwatchCraft_TestCase {

	public function set_up() {
		parent::set_up();
		do_action( 'rest_api_init' );
	}

	/**
	 * Run a request to /swatchcraft/v1/settings.
	 *
	 * @param string $method GET or POST.
	 * @param array  $body   JSON body.
	 * @return WP_REST_Response
	 */
	private function request( $method, array $body = array() ) {
		$request = new WP_REST_Request( $method, '/swatchcraft/v1/settings' );
		if ( $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_get_server()->dispatch( $request );
	}

	public function test_only_shop_managers() {
		$this->assertSame( 401, $this->request( 'GET' )->get_status() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$this->assertSame( 403, $this->request( 'GET' )->get_status() );
		$this->assertSame( 403, $this->request( 'POST', array( 'shape' => 'square' ) )->get_status() );
		$this->assertSame( 'rounded', Settings::get( 'shape' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$response = $this->request( 'GET' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Settings::defaults(), $response->get_data() );
	}

	public function test_save_sanitizes_and_keeps_other_values() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		Settings::save( array( 'tooltips' => false ) );
		$data = $this->request(
			'POST',
			array(
				'shape'          => 'square',
				'size'           => 'huge',
				'ajax_threshold' => 5000,
				'loop_attribute' => '<b>Colour</b>',
				'gallery'        => 'false',
				'unknown'        => 'x',
			)
		)->get_data();
		$this->assertSame( 'square', $data['shape'] );
		$this->assertSame( 'medium', $data['size'] );
		$this->assertSame( 500, $data['ajax_threshold'] );
		$this->assertSame( 'colour', $data['loop_attribute'] );
		$this->assertFalse( $data['gallery'] );
		$this->assertFalse( $data['tooltips'], 'Values not sent are kept.' );
		$this->assertArrayNotHasKey( 'unknown', $data );
		$this->assertSame( $data, Settings::get() );
	}

	public function test_page_config_lists_attributes() {
		$color = $this->attribute( 'color', array( 'Red' ), 'color' );
		Swatch_Data::save_attribute(
			$color['id'],
			array(
				'type' => 'color',
				'loop' => true,
			)
		);
		$config = Settings_Page::config();
		$this->assertSame( SWATCHCRAFT_VERSION, $config['version'] );
		$this->assertArrayHasKey( 'image', $config['types'] );
		$found = wp_list_filter( $config['attributes'], array( 'slug' => 'color' ) );
		$this->assertCount( 1, $found );
		$attribute = reset( $found );
		$this->assertSame( 'color', $attribute['type'] );
		$this->assertTrue( $attribute['loop'] );
		$this->assertStringContainsString( 'taxonomy=pa_color', $attribute['termsUrl'] );
	}

	public function test_page_is_registered_for_shop_managers() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		set_current_screen( 'dashboard' );
		add_menu_page( 'WooCommerce', 'WooCommerce', 'edit_others_shop_orders', 'woocommerce' );
		Settings_Page::menu();
		$this->assertNotEmpty( menu_page_url( Settings_Page::SLUG, false ) );
		set_current_screen( 'front' );
	}
}
