<?php
/**
 * Swatches on product cards.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Front;

use SwatchCraft\Data\Swatch_Data;
use SwatchCraft\Settings;
use WC_Product_Variable;
use WP_Block;

/**
 * One attribute's swatches on the cards of variable products (shop, categories, related products,
 * Product Collection blocks). Choosing one shows that variation's image on the card and points the
 * card's links at the product with the value preselected.
 */
final class Loop_Swatches {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'wp', array( self::class, 'positions' ) );
		add_filter( 'render_block_woocommerce/product-button', array( self::class, 'button_block' ), 10, 3 );
	}

	/**
	 * Classic template hooks for the chosen position.
	 */
	public static function positions() {
		if ( ! Settings::get( 'loop' ) ) {
			return;
		}
		// After the card link closes (priority 5), so the swatches are not inside a link.
		$priority = 'after_cart' === Settings::get( 'loop_position' ) ? 11 : 9;
		add_action( 'woocommerce_after_shop_loop_item', array( self::class, 'output' ), $priority );
	}

	/**
	 * Classic hook output. In block templates WooCommerce also runs the classic loop hooks (from
	 * a render_block filter, after the whole card); the button block filter places them there.
	 */
	public static function output() {
		if ( doing_filter( 'render_block' ) ) {
			return;
		}
		echo self::html( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Swatches::render().
	}

	/**
	 * Around the add-to-cart button block.
	 *
	 * @param string   $html     Block HTML.
	 * @param array    $block    Block.
	 * @param WP_Block $instance Block instance.
	 * @return string
	 */
	public static function button_block( $html, $block, $instance = null ) {
		if ( ! self::in_loop_block( $instance ) ) {
			return $html;
		}
		$swatches = self::html( self::block_product_id( $instance ) );
		return 'after_cart' === Settings::get( 'loop_position' ) ? $html . $swatches : $swatches . $html;
	}

	/**
	 * Whether a block renders inside a product card: it gets its post from a query loop (Product
	 * Collection), unlike the button of the product on a single product page.
	 *
	 * @param WP_Block|null $instance Block instance.
	 * @return bool
	 */
	private static function in_loop_block( $instance ) {
		if ( ! Settings::get( 'loop' ) || ! $instance instanceof WP_Block || empty( $instance->context['postId'] ) ) {
			return false;
		}
		return isset( $instance->context['queryId'] ) || isset( $instance->context['query'] );
	}

	/**
	 * Product ID of a block.
	 *
	 * @param WP_Block $instance Block instance.
	 * @return int
	 */
	private static function block_product_id( $instance ) {
		return (int) $instance->context['postId'];
	}

	/**
	 * Attribute shown on a product's card, or '' for none.
	 *
	 * @param WC_Product_Variable $product Product.
	 * @return string
	 */
	public static function attribute( WC_Product_Variable $product ) {
		$overrides = Swatch_Data::product( $product->get_id() );
		if ( false === $overrides['loop'] ) {
			return '';
		}
		$attributes = array_keys( $product->get_variation_attributes() );
		$preferred  = Settings::get( 'loop_attribute' );
		if ( '' !== $preferred ) {
			foreach ( $attributes as $attribute ) {
				if ( Swatch_Data::key( $attribute ) === $preferred || 'pa_' . $preferred === $attribute ) {
					return $attribute;
				}
			}
		}
		foreach ( $attributes as $attribute ) {
			if ( taxonomy_exists( $attribute ) && Swatch_Data::attribute( $attribute )['loop'] ) {
				return $attribute;
			}
		}
		foreach ( $attributes as $attribute ) {
			if ( in_array( Swatch_Data::type( $product, $attribute ), array( 'color', 'image' ), true ) ) {
				return $attribute;
			}
		}
		return '';
	}

	/**
	 * Card markup for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function html( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product_Variable || ! $product->is_purchasable() ) {
			return '';
		}
		$attribute = self::attribute( $product );
		if ( '' === $attribute ) {
			return '';
		}
		$values = $product->get_variation_attributes()[ $attribute ];
		$type   = Swatch_Data::type( $product, $attribute );
		if ( 'dropdown' === $type || 'radio' === $type ) {
			$type = 'button';
		}
		$options = Swatch_Data::options( $product, $attribute, array_map( 'strval', $values ) );
		$images  = self::variation_images( $product, $attribute );
		$link    = get_permalink( $product->get_id() );
		$key     = 'attribute_' . sanitize_title( $attribute );
		foreach ( $options as &$option ) {
			if ( isset( $images[ $option['value'] ] ) ) {
				$option['image_full'] = $images[ $option['value'] ];
			}
		}
		unset( $option );

		$limit = (int) Settings::get( 'loop_limit' );
		$more  = count( $options ) - $limit;
		$html  = Swatches::render( $product, $attribute, $type, array_slice( $options, 0, $limit ), '', 'loop' );
		if ( $more > 0 ) {
			/* translators: %d: number of other values. */
			$html = str_replace( '</div>', '<a class="swatchcraft__more" href="' . esc_url( $link ) . '">' . esc_html( sprintf( __( '+%d more', 'wp-smart-variations' ), $more ) ) . '</a></div>', $html );
		}
		Assets::enqueue();
		return sprintf(
			'<div class="swatchcraft-loop" data-swatchcraft-loop data-link="%1$s" data-key="%2$s">%3$s</div>',
			esc_url( $link ),
			esc_attr( $key ),
			$html
		);
	}

	/**
	 * Image of the first variation with each value (for the card image swap).
	 *
	 * @param WC_Product_Variable $product   Product.
	 * @param string              $attribute Attribute.
	 * @return string[] value => image URL.
	 */
	private static function variation_images( WC_Product_Variable $product, $attribute ) {
		if ( count( $product->get_children() ) > Settings::get( 'ajax_threshold' ) ) {
			return array();
		}
		$key = sanitize_title( $attribute );
		$out = array();
		foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
			$attributes = $variation->get_attributes();
			$value      = isset( $attributes[ $key ] ) ? (string) $attributes[ $key ] : '';
			$image_id   = $variation->get_image_id( 'edit' );
			if ( '' === $value || isset( $out[ $value ] ) || ! $image_id ) {
				continue;
			}
			$url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
			if ( $url ) {
				$out[ $value ] = $url;
			}
		}
		return $out;
	}
}
