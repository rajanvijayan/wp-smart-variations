<?php
/**
 * Front end: swatches, product cards, galleries, variations table, bulk cart.
 *
 * @package SwatchCraft
 */

use SwatchCraft\Admin\Product_Tab;
use SwatchCraft\Data\Swatch_Data;
use SwatchCraft\Front\Cart;
use SwatchCraft\Front\Gallery;
use SwatchCraft\Front\Loop_Swatches;
use SwatchCraft\Front\Swatches;
use SwatchCraft\Front\Table;
use SwatchCraft\Settings;

/**
 * Front end.
 */
class Test_Front extends SwatchCraft_TestCase {

	/**
	 * Colour attribute.
	 *
	 * @var array
	 */
	private $color;

	/**
	 * Product with colour (Blue, Red) and size (S, M), Blue/M out of stock, Red/M missing.
	 *
	 * @var WC_Product_Variable
	 */
	private $product;

	public function set_up() {
		parent::set_up();
		$this->color = $this->attribute( 'color', array( 'Red', 'Blue' ), 'color' );
		$size        = $this->attribute( 'size', array( 'S', 'M' ) );
		Swatch_Data::save_term( $this->color['terms']['red'], array( 'color' => '#ff0000' ) );
		Swatch_Data::save_term(
			$this->color['terms']['blue'],
			array(
				'color'   => '#0000ff',
				'color_2' => '#ffffff',
			)
		);
		$this->product = $this->variable_product(
			array(
				'color' => $this->color,
				'size'  => $size,
			),
			array(
				array(
					'pa_color' => 'red',
					'pa_size'  => 's',
					'_price'   => 10,
				),
				array(
					'pa_color' => 'blue',
					'pa_size'  => 's',
					'_price'   => 12,
				),
				array(
					'pa_color' => 'blue',
					'pa_size'  => 'm',
					'_price'   => 12,
					'_stock'   => 0,
				),
			)
		);
	}

	/**
	 * WooCommerce's dropdown for an attribute, after SwatchCraft's filter.
	 *
	 * @param string $attribute Attribute.
	 * @param array  $args      Extra arguments.
	 * @return string
	 */
	private function dropdown( $attribute, array $args = array() ) {
		ob_start();
		wc_dropdown_variation_attribute_options(
			array_merge(
				array(
					'attribute' => $attribute,
					'product'   => $this->product,
				),
				$args
			)
		);
		return ob_get_clean();
	}

	public function test_colour_swatches_follow_the_select() {
		$html = $this->dropdown( 'pa_color' );
		$this->assertStringContainsString( 'swatchcraft-select', $html, 'The select stays, hidden.' );
		$this->assertStringContainsString( 'data-attribute_name="attribute_pa_color"', $html );
		$this->assertStringContainsString( 'role="radiogroup"', $html );
		$this->assertStringContainsString( 'data-attribute="attribute_pa_color"', $html );
		$this->assertStringContainsString( '--swatchcraft-color:#0000ff;--swatchcraft-color-2:#ffffff', $html );
		$this->assertStringContainsString( '--swatchcraft-color:#ff0000;--swatchcraft-color-2:#ff0000', $html );
		$this->assertSame( 2, substr_count( $html, 'role="radio"' ) );
		$this->assertTrue( wp_script_is( 'swatchcraft', 'enqueued' ) );
	}

	public function test_selected_value_and_single_tab_stop() {
		$html = $this->dropdown( 'pa_color', array( 'selected' => 'red' ) );
		$this->assertMatchesRegularExpression( '/aria-checked="true" tabindex="0" class="swatchcraft__option is-selected" data-value="red"/', $html );
		$this->assertMatchesRegularExpression( '/aria-checked="false" tabindex="-1" class="swatchcraft__option" data-value="blue"/', $html );
	}

	public function test_buttons_for_untyped_and_dropdown_when_auto_is_off() {
		$this->assertStringContainsString( 'swatchcraft--button', $this->dropdown( 'pa_size' ) );
		Settings::save( array( 'auto_buttons' => false ) );
		$html = $this->dropdown( 'pa_size' );
		$this->assertStringNotContainsString( 'data-swatchcraft', $html );
		$this->assertStringNotContainsString( 'swatchcraft-select', $html );
	}

	public function test_labels_are_escaped() {
		Swatch_Data::save_term( $this->color['terms']['red'], array( 'label' => '"><script>x</script>' ) );
		Swatch_Data::save_attribute( $this->color['id'], array( 'type' => 'button' ) );
		$html = $this->dropdown( 'pa_color' );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_clear_link_setting() {
		$link = '<a class="reset_variations" href="#">Clear</a>';
		$this->assertSame( $link, apply_filters( 'woocommerce_reset_variations_link', $link ) );
		Settings::save( array( 'clear_link' => false ) );
		$this->assertSame( '', apply_filters( 'woocommerce_reset_variations_link', $link ) );
	}

	public function test_tooltip_price_and_stock() {
		Settings::save(
			array(
				'tooltip_price' => true,
				'tooltip_stock' => true,
			)
		);
		$details = Swatches::option_details( $this->product, 'pa_size' );
		$this->assertArrayNotHasKey( 's', $details, 'S costs 10 or 12: no single price.' );
		$this->assertStringContainsString( 'Out of stock', $details['m'] );
		$this->assertStringContainsString( '12', Swatches::option_details( $this->product, 'pa_color' )['blue'] );
	}

	public function test_product_card_swatches() {
		$html = Loop_Swatches::html( $this->product->get_id() );
		$this->assertStringContainsString( 'data-swatchcraft-loop', $html );
		$this->assertStringContainsString( 'data-key="attribute_pa_color"', $html, 'The first colour attribute is used.' );
		$this->assertStringContainsString( 'aria-pressed="false"', $html );
		$this->assertStringNotContainsString( 'role="radio"', $html );

		Settings::save( array( 'loop_limit' => 1 ) );
		$this->assertStringContainsString( '+1 more', Loop_Swatches::html( $this->product->get_id() ) );

		Swatch_Data::save_product( $this->product->get_id(), array( 'loop' => '0' ) );
		$this->assertSame( '', Loop_Swatches::html( $this->product->get_id() ) );
	}

	public function test_product_card_uses_chosen_attribute() {
		Settings::save( array( 'loop_attribute' => 'size' ) );
		$this->assertStringContainsString( 'data-key="attribute_pa_size"', Loop_Swatches::html( $this->product->get_id() ) );
	}

	public function test_product_card_placement() {
		$block             = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(),
		);
		$instance          = new WP_Block( $block );
		$instance->context = array(
			'postId'  => $this->product->get_id(),
			'queryId' => 1,
		);
		$this->assertMatchesRegularExpression( '/^<div class="swatchcraft-loop".*<p><\/p>$/s', Loop_Swatches::button_block( '<p></p>', $block, $instance ) );
		Settings::save( array( 'loop_position' => 'after_cart' ) );
		$this->assertStringStartsWith( '<p></p><div class="swatchcraft-loop"', Loop_Swatches::button_block( '<p></p>', $block, $instance ) );

		unset( $instance->context['queryId'] );
		$this->assertSame( '<p></p>', Loop_Swatches::button_block( '<p></p>', $block, $instance ), 'Not on the single product page.' );

		// Classic hooks print the swatches, unless WooCommerce runs them inside a block template.
		$GLOBALS['post'] = get_post( $this->product->get_id() );
		ob_start();
		Loop_Swatches::output();
		$this->assertStringContainsString( 'data-swatchcraft-loop', ob_get_clean() );
		$output = '';
		$filter = static function ( $html ) use ( &$output ) {
			ob_start();
			Loop_Swatches::output();
			$output = ob_get_clean();
			return $html;
		};
		add_filter( 'render_block', $filter );
		apply_filters( 'render_block', '', $block, $instance );
		remove_filter( 'render_block', $filter );
		$this->assertSame( '', $output );
	}

	public function test_variation_gallery_data() {
		$images    = array(
			self::factory()->attachment->create_upload_object( __DIR__ . '/fixtures/blue.jpg' ),
			self::factory()->attachment->create_upload_object( __DIR__ . '/fixtures/red.jpg' ),
		);
		$children  = $this->product->get_children();
		$variation = wc_get_product( $children[0] );
		update_post_meta( $variation->get_id(), Gallery::META, $images );

		$data = Gallery::data( array(), $this->product, $variation );
		$this->assertCount( 2, $data['swatchcraft_gallery'] );
		$this->assertStringContainsString( 'woocommerce-product-gallery__image', $data['swatchcraft_gallery'][0] );

		Settings::save( array( 'gallery' => false ) );
		$this->assertArrayNotHasKey( 'swatchcraft_gallery', Gallery::data( array(), $this->product, $variation ) );
	}

	public function test_variation_gallery_save_keeps_only_images() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$image     = self::factory()->attachment->create_upload_object( __DIR__ . '/fixtures/blue.jpg' );
		$not_image = self::factory()->post->create();
		$variation = $this->product->get_children()[0];
		$_POST     = array( 'swatchcraft_gallery' => array( 3 => "{$image},{$not_image},{$image},abc" ) );
		Gallery::save( $variation, 3 );
		$this->assertSame( array( $image ), Gallery::ids( $variation ) );
		$_POST = array( 'swatchcraft_gallery' => array( 3 => '' ) );
		Gallery::save( $variation, 3 );
		$this->assertSame( array(), Gallery::ids( $variation ) );
		$_POST = array();
	}

	public function test_variations_table() {
		$this->assertSame( '', Table::mode( $this->product ) );
		Swatch_Data::save_product( $this->product->get_id(), array( 'table' => 'show' ) );
		$this->assertSame( 'after_cart', Table::mode( $this->product ) );
		Settings::save( array( 'table' => 'replace_cart' ) );
		$this->assertSame( 'replace_cart', Table::mode( $this->product ) );

		$html = Table::html( $this->product->get_id() );
		$this->assertSame( 3, substr_count( $html, '<tr data-variation=' ) );
		$this->assertSame( 2, substr_count( $html, 'name="swatchcraft_qty[' ), 'The out-of-stock variation has no quantity.' );
		$this->assertStringContainsString( '_swatchcraft_nonce', $html );
		$this->assertStringContainsString( 'swatchcraft-table', do_shortcode( '[swatchcraft_table id="' . $this->product->get_id() . '"]' ) );
		$this->assertSame( '', Table::html( self::factory()->post->create() ) );
	}

	public function test_bulk_add_to_cart() {
		WC()->frontend_includes();
		WC()->session = new WC_Session_Handler();
		WC()->session->init();
		WC()->cart = new WC_Cart();
		$children  = $this->product->get_children();
		$other     = $this->variable_product( array( 'Fit' => array( 'Slim' ) ), array( array( 'fit' => 'Slim' ) ) );

		$result = Cart::add(
			$this->product->get_id(),
			array(
				$children[0]              => 2,
				$children[1]              => 0,
				$other->get_children()[0] => 1,
				$children[2]              => 1,
			)
		);
		$this->assertSame( 1, $result['added'] );
		$this->assertNotEmpty( $result['errors'], 'Variations of another product are refused.' );
		$this->assertSame( 2, WC()->cart->get_cart_contents_count() );
		WC()->cart->empty_cart();
		wc_clear_notices();
	}

	public function test_cart_endpoint_needs_the_rest_nonce() {
		do_action( 'rest_api_init' );
		$request = new WP_REST_Request( 'POST', '/swatchcraft/v1/cart' );
		$request->set_body_params(
			array(
				'product_id' => $this->product->get_id(),
				'items'      => array( $this->product->get_children()[0] => 1 ),
			)
		);
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
	}

	public function test_product_tab_saves_overrides() {
		$_POST = array(
			'swatchcraft_loop'  => '0',
			'swatchcraft_table' => 'show',
			'swatchcraft'       => array(
				'pa_color' => array(
					'type'   => 'image',
					'values' => array( 'red' => array( 'color' => '#AA0000' ) ),
				),
			),
		);
		Product_Tab::save( $this->product );
		$saved = Swatch_Data::product( $this->product->get_id() );
		$this->assertFalse( $saved['loop'] );
		$this->assertSame( 'show', $saved['table'] );
		$this->assertSame( 'image', $saved['attributes']['pa_color']['type'] );
		$this->assertSame( '#aa0000', $saved['attributes']['pa_color']['values']['red']['color'] );
		$_POST = array();
	}

	public function test_variations_table_block_is_registered() {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'swatchcraft/variations-table' ) );
		$this->assertStringContainsString(
			'swatchcraft-table',
			do_blocks( '<!-- wp:swatchcraft/variations-table {"productId":' . $this->product->get_id() . '} /-->' )
		);
	}
}
