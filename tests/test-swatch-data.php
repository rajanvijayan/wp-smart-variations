<?php
/**
 * Swatch data resolution.
 *
 * @package SwatchCraft
 */

use SwatchCraft\Data\Swatch_Data;
use SwatchCraft\Settings;

/**
 * Swatch_Data.
 */
class Test_Swatch_Data extends SwatchCraft_TestCase {

	public function test_settings_are_sanitized() {
		$saved = Settings::save(
			array(
				'shape'          => 'triangle',
				'unavailable'    => 'hide',
				'loop_limit'     => 500,
				'ajax_threshold' => 2,
				'tooltips'       => 'false',
			)
		);
		$this->assertSame( 'rounded', $saved['shape'] );
		$this->assertSame( 'hide', $saved['unavailable'] );
		$this->assertSame( 30, $saved['loop_limit'] );
		$this->assertSame( 10, $saved['ajax_threshold'] );
		$this->assertFalse( $saved['tooltips'] );
	}

	public function test_term_values_are_cleaned_and_saved() {
		$color = $this->attribute( 'color', array( 'Navy' ), 'color' );
		$id    = $color['terms']['navy'];
		Swatch_Data::save_term(
			$id,
			array(
				'color'   => '#1F2A44',
				'color_2' => 'not a colour',
				'image'   => '12abc',
				'label'   => '<b>NV</b>',
			)
		);
		$this->assertSame(
			array(
				'color'   => '#1f2a44',
				'color_2' => '',
				'image'   => 12,
				'label'   => 'NV',
			),
			Swatch_Data::term( $id )
		);
		Swatch_Data::save_term( $id, array( 'color' => '' ) );
		$this->assertSame( '', get_term_meta( $id, 'swatchcraft_color', true ) );
	}

	public function test_woocommerce_visual_term_meta_is_a_fallback() {
		$color = $this->attribute( 'color', array( 'Red' ) );
		update_term_meta( $color['terms']['red'], 'color', '#ff0000' );
		$this->assertSame( '#ff0000', Swatch_Data::term( $color['terms']['red'] )['color'] );
	}

	public function test_type_resolution_order() {
		$color   = $this->attribute( 'color', array( 'Red', 'Blue' ), 'color' );
		$size    = $this->attribute( 'size', array( 'S', 'M' ) );
		$product = $this->variable_product(
			array(
				'color'    => $color,
				'size'     => $size,
				'Material' => array( 'Cotton', 'Linen' ),
			),
			array(
				array(
					'pa_color' => 'red',
					'pa_size'  => 's',
					'material' => 'Cotton',
				),
			)
		);

		$this->assertSame( 'color', Swatch_Data::type( $product, 'pa_color' ) );
		$this->assertSame( 'button', Swatch_Data::type( $product, 'pa_size' ), 'Untyped attributes become buttons.' );
		$this->assertSame( 'button', Swatch_Data::type( $product, 'Material' ) );

		Settings::save( array( 'auto_buttons' => false ) );
		$this->assertSame( 'dropdown', Swatch_Data::type( $product, 'pa_size' ) );

		Swatch_Data::save_product(
			$product->get_id(),
			array(
				'attributes' => array(
					'pa_color' => array( 'type' => 'image' ),
					'Material' => array( 'type' => 'radio' ),
				),
			)
		);
		$this->assertSame( 'image', Swatch_Data::type( $product, 'pa_color' ) );
		$this->assertSame( 'radio', Swatch_Data::type( $product, 'Material' ), 'Custom attributes are keyed by their sanitized name.' );
	}

	public function test_options_follow_values_and_merge_overrides() {
		$color   = $this->attribute( 'color', array( 'Red', 'Blue', 'Green' ), 'color' );
		$product = $this->variable_product(
			array(
				'color' => $color,
				'Size'  => array( 'Small', 'Large' ),
			),
			array(
				array(
					'pa_color' => 'red',
					'size'     => 'Small',
				),
			)
		);
		Swatch_Data::save_term(
			$color['terms']['red'],
			array(
				'color' => '#ff0000',
				'label' => 'R',
			)
		);
		Swatch_Data::save_term( $color['terms']['blue'], array( 'color' => '#0000ff' ) );
		Swatch_Data::save_product(
			$product->get_id(),
			array(
				'attributes' => array(
					'pa_color' => array( 'values' => array( 'blue' => array( 'color' => '#000080' ) ) ),
					'size'     => array( 'values' => array( 'Large' => array( 'label' => 'L' ) ) ),
				),
			)
		);

		$options = Swatch_Data::options( $product, 'pa_color', array( 'red', 'blue' ) );
		// WooCommerce's term order (here by name), only the values offered.
		$this->assertSame( array( 'blue', 'red' ), wp_list_pluck( $options, 'value' ) );
		$this->assertSame( array( 'Blue', 'R' ), wp_list_pluck( $options, 'label' ) );
		$this->assertSame( array( 'Blue', 'Red' ), wp_list_pluck( $options, 'name' ) );
		$this->assertSame( array( '#000080', '#ff0000' ), wp_list_pluck( $options, 'color' ) );

		$sizes = Swatch_Data::options( $product, 'Size', array( 'Small', 'Large' ) );
		$this->assertSame( array( 'Small', 'L' ), wp_list_pluck( $sizes, 'label' ) );
	}

	public function test_product_overrides_are_cleaned_and_empty_ones_removed() {
		$id = self::factory()->post->create( array( 'post_type' => 'product' ) );
		Swatch_Data::save_product(
			$id,
			array(
				'attributes' => array(
					'pa_color' => array(
						'type'   => 'sparkles',
						'values' => array( 'red' => array( 'color' => 'nope' ) ),
					),
				),
				'table'      => 'maybe',
			)
		);
		$this->assertSame( '', get_post_meta( $id, Swatch_Data::PRODUCT_META, true ) );
		Swatch_Data::save_product(
			$id,
			array(
				'loop'  => '0',
				'table' => 'show',
			)
		);
		$this->assertSame(
			array(
				'attributes' => array(),
				'loop'       => false,
				'table'      => 'show',
			),
			Swatch_Data::product( $id )
		);
	}

	public function test_attribute_settings_and_deletion() {
		$color = $this->attribute( 'color', array( 'Red' ) );
		Swatch_Data::save_attribute(
			$color['id'],
			array(
				'type' => 'image',
				'loop' => true,
			)
		);
		$this->assertSame(
			array(
				'type' => 'image',
				'loop' => true,
			),
			Swatch_Data::attribute( 'pa_color' )
		);
		Swatch_Data::save_attribute( $color['id'], array( 'type' => 'bogus' ) );
		$this->assertSame( '', Swatch_Data::attribute( 'pa_color' )['type'] );
		do_action( 'woocommerce_attribute_deleted', $color['id'], 'color', 'pa_color' );
		$this->assertArrayNotHasKey( $color['id'], Swatch_Data::attribute_settings() );
	}
}
