<?php
/**
 * Admin forms.
 *
 * @package SwatchCraft
 */

use SwatchCraft\Admin\Attribute_Fields;
use SwatchCraft\Admin\Term_Fields;
use SwatchCraft\Data\Swatch_Data;

/**
 * Attribute and term fields.
 */
class Test_Admin extends SwatchCraft_TestCase {

	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	public function test_attribute_type_saves_with_nonce_and_capability() {
		$color = $this->attribute( 'color', array( 'Red' ) );
		$_POST = array(
			'_swatchcraft_nonce' => wp_create_nonce( Attribute_Fields::NONCE ),
			'swatchcraft_type'   => 'color',
			'swatchcraft_loop'   => '1',
		);
		Attribute_Fields::save( $color['id'] );
		$this->assertSame( '', Swatch_Data::attribute( 'pa_color' )['type'], 'Visitors without the capability cannot save.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST['_swatchcraft_nonce'] = wp_create_nonce( Attribute_Fields::NONCE );
		Attribute_Fields::save( $color['id'] );
		$this->assertSame(
			array(
				'type' => 'color',
				'loop' => true,
			),
			Swatch_Data::attribute( 'pa_color' )
		);

		$_POST['_swatchcraft_nonce'] = 'wrong';
		$_POST['swatchcraft_type']   = 'image';
		Attribute_Fields::save( $color['id'] );
		$this->assertSame( 'color', Swatch_Data::attribute( 'pa_color' )['type'] );
	}

	public function test_term_fields_render_and_save() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$color = $this->attribute( 'color', array( 'Red' ), 'color' );
		$term  = get_term( $color['terms']['red'] );

		ob_start();
		Term_Fields::edit_fields( $term, 'pa_color' );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="swatchcraft[color]"', $html );
		$this->assertStringNotContainsString( 'name="swatchcraft[image]"', $html, 'Colour attributes do not ask for an image.' );

		$_POST = array(
			'_swatchcraft_term_nonce' => wp_create_nonce( Term_Fields::NONCE ),
			'swatchcraft'             => array(
				'color' => '#AA0000',
				'label' => 'Rd',
			),
		);
		Term_Fields::save( $term->term_id );
		$this->assertSame( '#aa0000', Swatch_Data::term( $term->term_id )['color'] );
		$this->assertStringContainsString( '#aa0000', Term_Fields::column( '', 'swatchcraft', $term->term_id ) );
	}
}
