<?php
/**
 * Swatches on the product page.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Front;

use SwatchCraft\Data\Swatch_Data;
use SwatchCraft\Settings;
use WC_Product;
use WC_Product_Variable;

/**
 * Adds swatches after each variation select. The select stays in the form (hidden), so
 * WooCommerce's own script keeps finding variations, prices, stock and images, and every theme or
 * plugin hooked into the variation form keeps working; the front-end script keeps the two in sync.
 */
final class Swatches {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( self::class, 'filter' ), 20, 2 );
		add_filter( 'woocommerce_ajax_variation_threshold', array( self::class, 'threshold' ), 10, 2 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_filter( 'woocommerce_reset_variations_link', array( self::class, 'reset_link' ) );
	}

	/**
	 * WooCommerce's "Clear" link, unless switched off (clicking a chosen swatch clears it too).
	 *
	 * @param string $html Link HTML.
	 * @return string
	 */
	public static function reset_link( $html ) {
		return Settings::get( 'clear_link' ) ? $html : '';
	}

	/**
	 * Variation count above which WooCommerce looks variations up with AJAX.
	 *
	 * @param int $threshold Threshold.
	 * @return int
	 */
	public static function threshold( $threshold ) {
		unset( $threshold );
		return (int) Settings::get( 'ajax_threshold' );
	}

	/**
	 * Theme styling hooks.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		$classes[] = 'swatchcraft-shape-' . Settings::get( 'shape' );
		return $classes;
	}

	/**
	 * Swatches for one attribute, after WooCommerce's select.
	 *
	 * @param string $html Select HTML.
	 * @param array  $args Arguments of wc_dropdown_variation_attribute_options().
	 * @return string
	 */
	public static function filter( $html, $args ) {
		$product = isset( $args['product'] ) ? $args['product'] : null;
		if ( ! $product instanceof WC_Product || empty( $args['attribute'] ) ) {
			return $html;
		}
		$attribute = $args['attribute'];
		$type      = Swatch_Data::type( $product, $attribute );
		if ( 'dropdown' === $type ) {
			return $html;
		}
		$values = ! empty( $args['options'] ) ? $args['options'] : self::values( $product, $attribute );
		$values = array_map( 'strval', (array) $values );
		if ( ! $values ) {
			return $html;
		}

		$selected = self::selected( $args, $product, $attribute );
		$swatches = self::render(
			$product,
			$attribute,
			$type,
			Swatch_Data::options( $product, $attribute, $values ),
			$selected
		);
		Assets::enqueue();

		// Hide the select (WooCommerce still drives it) but keep its markup intact.
		$html = preg_replace( '/<select\b([^>]*?)\bclass="([^"]*)"/', '<select$1class="$2 swatchcraft-select"', $html, 1, $count );
		if ( ! $count ) {
			$html = preg_replace( '/<select\b/', '<select class="swatchcraft-select"', $html, 1 );
		}
		return $html . $swatches;
	}

	/**
	 * Values of an attribute on a variable product.
	 *
	 * @param WC_Product $product   Product.
	 * @param string     $attribute Attribute.
	 * @return string[]
	 */
	private static function values( WC_Product $product, $attribute ) {
		if ( ! $product instanceof WC_Product_Variable ) {
			return array();
		}
		$all = $product->get_variation_attributes();
		return isset( $all[ $attribute ] ) ? $all[ $attribute ] : array();
	}

	/**
	 * Value selected when the page loads (URL argument, else the product default), as WooCommerce
	 * works it out.
	 *
	 * @param array      $args      Arguments.
	 * @param WC_Product $product   Product.
	 * @param string     $attribute Attribute.
	 * @return string
	 */
	private static function selected( array $args, WC_Product $product, $attribute ) {
		if ( isset( $args['selected'] ) && false !== $args['selected'] ) {
			return (string) $args['selected'];
		}
		$key = 'attribute_' . sanitize_title( $attribute );
		// Read-only: the same query argument WooCommerce reads to preselect a variation.
		if ( isset( $_REQUEST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return wc_clean( wp_unslash( $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wc_clean.
		}
		return (string) $product->get_variation_default_attribute( $attribute );
	}

	/**
	 * Swatch group markup.
	 *
	 * @param WC_Product $product   Product.
	 * @param string     $attribute Attribute.
	 * @param string     $type      Swatch type.
	 * @param array[]    $options   Swatches.
	 * @param string     $selected  Selected value.
	 * @param string     $context   single or loop.
	 * @return string
	 */
	public static function render( WC_Product $product, $attribute, $type, array $options, $selected, $context = 'single' ) {
		$settings = Settings::get();
		$extra    = 'single' === $context ? self::option_details( $product, $attribute ) : array();
		$classes  = array(
			'swatchcraft',
			'swatchcraft--' . $type,
			'swatchcraft--' . $settings['shape'],
			'swatchcraft--' . $settings['size'],
			'swatchcraft--' . $context,
		);
		if ( ! $settings['tooltips'] ) {
			$classes[] = 'swatchcraft--no-tooltips';
		}

		$items = '';
		foreach ( $options as $option ) {
			$is_selected = '' !== $selected && ( $selected === $option['value'] || sanitize_title( $selected ) === $option['value'] );
			$tooltip     = $option['name'];
			if ( isset( $extra[ $option['value'] ] ) ) {
				$tooltip .= ' · ' . $extra[ $option['value'] ];
			}
			$items .= self::item( $type, $option, $is_selected, $tooltip, $context );
		}

		return sprintf(
			'<div class="%1$s" role="radiogroup" aria-label="%2$s" data-swatchcraft data-attribute="%3$s" data-type="%4$s">%5$s</div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( wc_attribute_label( $attribute, $product ) ),
			esc_attr( 'attribute_' . sanitize_title( $attribute ) ),
			esc_attr( $type ),
			$items
		);
	}

	/**
	 * One swatch. Buttons with radio semantics; the script adds arrow-key navigation.
	 *
	 * @param string $type     Type.
	 * @param array  $option   Swatch.
	 * @param bool   $selected Selected.
	 * @param string $tooltip  Tooltip.
	 * @param string $context  single or loop.
	 * @return string
	 */
	private static function item( $type, array $option, $selected, $tooltip, $context ) {
		$style  = '';
		$visual = '';
		if ( 'color' === $type ) {
			$first  = $option['color'] ? $option['color'] : '#cccccc';
			$second = $option['color_2'] ? $option['color_2'] : $first;
			$style  = sprintf( '--swatchcraft-color:%s;--swatchcraft-color-2:%s', $first, $second );
			// A colour attribute with images on some terms shows those images.
			$visual = $option['image'] ? sprintf( '<img src="%s" alt="" loading="lazy" />', esc_url( $option['image'] ) ) : '';
		} elseif ( 'image' === $type ) {
			$visual = $option['image']
				? sprintf( '<img src="%s" alt="" loading="lazy" />', esc_url( $option['image'] ) )
				: '<span class="swatchcraft__fallback">' . esc_html( $option['label'] ) . '</span>';
		}
		$visible = in_array( $type, array( 'color', 'image' ), true )
			? '<span class="swatchcraft__swatch" aria-hidden="true">' . $visual . '</span><span class="screen-reader-text">' . esc_html( $option['name'] ) . '</span>'
			: ( 'radio' === $type ? '<span class="swatchcraft__dot" aria-hidden="true"></span>' : '' ) . '<span class="swatchcraft__label">' . esc_html( $option['label'] ) . '</span>';

		$attributes = sprintf(
			'class="swatchcraft__option%1$s" data-value="%2$s" data-name="%3$s" data-tooltip="%4$s"%5$s%6$s',
			$selected ? ' is-selected' : '',
			esc_attr( $option['value'] ),
			esc_attr( $option['name'] ),
			esc_attr( $tooltip ),
			$style ? ' style="' . esc_attr( $style ) . '"' : '',
			$option['image_full'] ? ' data-image="' . esc_url( $option['image_full'] ) . '"' : ''
		);
		if ( 'loop' === $context ) {
			return '<button type="button" ' . $attributes . ' aria-pressed="' . ( $selected ? 'true' : 'false' ) . '">' . $visible . '</button>';
		}
		return '<button type="button" role="radio" aria-checked="' . ( $selected ? 'true' : 'false' ) . '" tabindex="' . ( $selected ? '0' : '-1' ) . '" ' . $attributes . '>' . $visible . '</button>';
	}

	/**
	 * Price and stock for tooltips, per value, when every variation with that value agrees (e.g.
	 * all "XL" variations cost the same). Skipped above the AJAX threshold.
	 *
	 * @param WC_Product $product   Product.
	 * @param string     $attribute Attribute.
	 * @return string[] value => text.
	 */
	public static function option_details( WC_Product $product, $attribute ) {
		$settings = Settings::get();
		if ( ( ! $settings['tooltip_price'] && ! $settings['tooltip_stock'] ) || ! $product instanceof WC_Product_Variable ) {
			return array();
		}
		if ( count( $product->get_children() ) > $settings['ajax_threshold'] ) {
			return array();
		}
		$key    = sanitize_title( $attribute );
		$groups = array();
		foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
			$raw   = $variation->get_attributes();
			$value = isset( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
			if ( '' === $value ) {
				continue; // "Any" value: matches everything, says nothing about one value.
			}
			$groups[ $value ][] = $variation;
		}
		$out = array();
		foreach ( $groups as $value => $variations ) {
			$parts = array();
			if ( $settings['tooltip_price'] ) {
				$prices = array_unique(
					array_map(
						static function ( $v ) {
							return (string) wc_get_price_to_display( $v );
						},
						$variations
					)
				);
				if ( 1 === count( $prices ) ) {
					$parts[] = wp_strip_all_tags( wc_price( (float) reset( $prices ) ) );
				}
			}
			if ( $settings['tooltip_stock'] ) {
				$in_stock = array_filter(
					$variations,
					static function ( $v ) {
						return $v->is_in_stock();
					}
				);
				if ( ! $in_stock ) {
					$parts[] = __( 'Out of stock', 'wp-smart-variations' );
				} elseif ( 1 === count( $variations ) && $variations[0]->managing_stock() ) {
					/* translators: %d: number in stock. */
					$parts[] = sprintf( __( '%d in stock', 'wp-smart-variations' ), $variations[0]->get_stock_quantity() );
				}
			}
			if ( $parts ) {
				$out[ (string) $value ] = implode( ' · ', $parts );
			}
		}
		return $out;
	}
}
