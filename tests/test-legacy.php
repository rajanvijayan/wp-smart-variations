<?php
/**
 * WP eCommerce compatibility.
 *
 * @package SwatchCraft
 */

use SwatchCraft\Legacy\Wpsc;

/**
 * Wpsc.
 */
class Test_Legacy extends SwatchCraft_TestCase {

	public function test_script_only_on_wp_ecommerce_sites() {
		Wpsc::enqueue();
		$this->assertFalse( wp_script_is( 'swatchcraft-wpsc', 'enqueued' ) );
		register_post_type( 'wpsc-product' );
		Wpsc::enqueue();
		$this->assertTrue( wp_script_is( 'swatchcraft-wpsc', 'enqueued' ) );
		unregister_post_type( 'wpsc-product' );
	}
}
