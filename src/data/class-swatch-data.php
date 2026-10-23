<?php
/**
 * What each attribute value looks like as a swatch.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Data;

use SwatchCraft\Settings;
use WC_Product;

/**
 * Resolves the swatch type of an attribute and the colour, image and label of each value.
 *
 * Sources, most specific first:
 * 1. the product's own overrides (product meta `_swatchcraft`), which is the only source for
 *    custom (local) product attributes;
 * 2. for global attributes, the type chosen on the attribute screen (option `swatchcraft_attributes`)
 *    and the colour, image and label saved on each term (term meta `swatchcraft_*`);
 * 3. WooCommerce's own colour/image attribute type (`wc-visual`, term meta `color` and `image`);
 * 4. the "auto" setting, which shows untyped attributes as buttons.
 */
final class Swatch_Data {

	/** Swatch types. "dropdown" keeps WooCommerce's select. */
	const TYPES = array( 'color', 'image', 'button', 'radio', 'dropdown' );

	const ATTRIBUTES_OPTION = 'swatchcraft_attributes';

	const PRODUCT_META = '_swatchcraft';

	const TERM_META = array(
		'color'   => 'swatchcraft_color',
		'color_2' => 'swatchcraft_color_2',
		'image'   => 'swatchcraft_image',
		'label'   => 'swatchcraft_label',
	);

	/**
	 * Swatch type names, for admin screens.
	 *
	 * @return string[]
	 */
	public static function type_labels() {
		return array(
			''         => __( 'Default (from settings)', 'wp-smart-variations' ),
			'color'    => __( 'Colour', 'wp-smart-variations' ),
			'image'    => __( 'Image', 'wp-smart-variations' ),
			'button'   => __( 'Button', 'wp-smart-variations' ),
			'radio'    => __( 'Radio', 'wp-smart-variations' ),
			'dropdown' => __( 'Dropdown', 'wp-smart-variations' ),
		);
	}

	/**
	 * Key used for an attribute in product overrides ("pa_color", or "size" for a custom attribute).
	 *
	 * @param string $attribute Attribute name as WooCommerce passes it.
	 * @return string
	 */
	public static function key( $attribute ) {
		return 0 === strpos( (string) $attribute, 'pa_' ) ? (string) $attribute : sanitize_title( $attribute );
	}

	/**
	 * A valid hex colour or ''.
	 *
	 * @param mixed $color Colour.
	 * @return string
	 */
	public static function color( $color ) {
		$color = is_string( $color ) ? sanitize_hex_color( trim( $color ) ) : '';
		return $color ? strtolower( $color ) : '';
	}

	// --------------------------------------------------------------------------------------------
	// Global attribute settings
	// --------------------------------------------------------------------------------------------

	/**
	 * Settings of all global attributes, keyed by attribute ID.
	 *
	 * @return array
	 */
	public static function attribute_settings() {
		$stored = get_option( self::ATTRIBUTES_OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Settings of one global attribute.
	 *
	 * @param string $taxonomy Taxonomy, e.g. pa_color.
	 * @return array { type: string, loop: bool }
	 */
	public static function attribute( $taxonomy ) {
		$id       = function_exists( 'wc_attribute_taxonomy_id_by_name' ) ? (int) wc_attribute_taxonomy_id_by_name( $taxonomy ) : 0;
		$settings = self::attribute_settings();
		$saved    = $id && isset( $settings[ $id ] ) && is_array( $settings[ $id ] ) ? $settings[ $id ] : array();
		$type     = isset( $saved['type'] ) && in_array( $saved['type'], self::TYPES, true ) ? $saved['type'] : '';
		if ( '' === $type && $id && self::is_wc_visual( $id ) ) {
			$type = 'color';
		}
		return array(
			'type' => $type,
			'loop' => ! empty( $saved['loop'] ),
		);
	}

	/**
	 * Save the settings of a global attribute.
	 *
	 * @param int   $id     Attribute ID.
	 * @param array $values { type, loop }.
	 */
	public static function save_attribute( $id, array $values ) {
		$settings        = self::attribute_settings();
		$type            = isset( $values['type'] ) && in_array( $values['type'], self::TYPES, true ) ? $values['type'] : '';
		$settings[ $id ] = array(
			'type' => $type,
			'loop' => ! empty( $values['loop'] ),
		);
		update_option( self::ATTRIBUTES_OPTION, $settings );
	}

	/**
	 * Forget a deleted attribute.
	 *
	 * @param int $id Attribute ID.
	 */
	public static function delete_attribute( $id ) {
		$settings = self::attribute_settings();
		unset( $settings[ (int) $id ] );
		update_option( self::ATTRIBUTES_OPTION, $settings );
	}

	/**
	 * Whether WooCommerce's own colour/image type is set on an attribute.
	 *
	 * @param int $id Attribute ID.
	 * @return bool
	 */
	private static function is_wc_visual( $id ) {
		$attribute = wc_get_attribute( $id );
		return $attribute && 'wc-visual' === $attribute->type;
	}

	// --------------------------------------------------------------------------------------------
	// Terms
	// --------------------------------------------------------------------------------------------

	/**
	 * Swatch values saved on a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array { color, color_2, image (attachment ID), label }
	 */
	public static function term( $term_id ) {
		$out = array();
		foreach ( self::TERM_META as $key => $meta ) {
			$out[ $key ] = get_term_meta( $term_id, $meta, true );
		}
		$out = self::clean_values( $out );
		// WooCommerce's colour/image attributes store plain "color" and "image" term meta.
		if ( '' === $out['color'] && ! $out['image'] ) {
			$out['color'] = self::color( get_term_meta( $term_id, 'color', true ) );
			$out['image'] = absint( get_term_meta( $term_id, 'image', true ) );
		}
		return $out;
	}

	/**
	 * Save swatch values on a term.
	 *
	 * @param int   $term_id Term ID.
	 * @param array $values  { color, color_2, image, label }.
	 */
	public static function save_term( $term_id, array $values ) {
		foreach ( self::clean_values( $values ) as $key => $value ) {
			if ( '' === $value || 0 === $value ) {
				delete_term_meta( $term_id, self::TERM_META[ $key ] );
			} else {
				update_term_meta( $term_id, self::TERM_META[ $key ], $value );
			}
		}
	}

	/**
	 * Clean swatch values.
	 *
	 * @param array $values Values.
	 * @return array { color, color_2, image, label }
	 */
	public static function clean_values( array $values ) {
		$label = isset( $values['label'] ) && is_scalar( $values['label'] ) ? sanitize_text_field( (string) $values['label'] ) : '';
		return array(
			'color'   => self::color( isset( $values['color'] ) ? $values['color'] : '' ),
			'color_2' => self::color( isset( $values['color_2'] ) ? $values['color_2'] : '' ),
			'image'   => isset( $values['image'] ) ? absint( $values['image'] ) : 0,
			'label'   => function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 40 ) : substr( $label, 0, 40 ),
		);
	}

	// --------------------------------------------------------------------------------------------
	// Products
	// --------------------------------------------------------------------------------------------

	/**
	 * A product's overrides.
	 *
	 * @param int $product_id Product ID.
	 * @return array { attributes: [ key => { type, values: [ value => {color, color_2, image, label} ] } ], loop: bool|null, table: string }
	 */
	public static function product( $product_id ) {
		$stored = get_post_meta( $product_id, self::PRODUCT_META, true );
		return self::clean_product( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Save a product's overrides.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $data       Overrides.
	 */
	public static function save_product( $product_id, array $data ) {
		$clean = self::clean_product( $data );
		if ( ! $clean['attributes'] && null === $clean['loop'] && '' === $clean['table'] ) {
			delete_post_meta( $product_id, self::PRODUCT_META );
			return;
		}
		update_post_meta( $product_id, self::PRODUCT_META, $clean );
	}

	/**
	 * Clean product overrides.
	 *
	 * @param array $data Overrides.
	 * @return array
	 */
	private static function clean_product( array $data ) {
		$attributes = array();
		foreach ( isset( $data['attributes'] ) && is_array( $data['attributes'] ) ? $data['attributes'] : array() as $key => $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}
			$type   = isset( $attribute['type'] ) && in_array( $attribute['type'], self::TYPES, true ) ? $attribute['type'] : '';
			$values = array();
			foreach ( isset( $attribute['values'] ) && is_array( $attribute['values'] ) ? $attribute['values'] : array() as $value => $swatch ) {
				$swatch = self::clean_values( is_array( $swatch ) ? $swatch : array() );
				if ( array_filter( $swatch ) ) {
					$values[ (string) $value ] = $swatch;
				}
			}
			if ( '' !== $type || $values ) {
				$attributes[ self::key( $key ) ] = array(
					'type'   => $type,
					'values' => $values,
				);
			}
		}
		$loop  = isset( $data['loop'] ) && '' !== $data['loop'] && null !== $data['loop'] ? (bool) rest_sanitize_boolean( $data['loop'] ) : null;
		$table = isset( $data['table'] ) && in_array( $data['table'], array( 'show', 'hide' ), true ) ? $data['table'] : '';
		return array(
			'attributes' => $attributes,
			'loop'       => $loop,
			'table'      => $table,
		);
	}

	// --------------------------------------------------------------------------------------------
	// Resolution
	// --------------------------------------------------------------------------------------------

	/**
	 * Swatch type of an attribute on a product.
	 *
	 * @param WC_Product $product   Product.
	 * @param string     $attribute Attribute name.
	 * @return string One of TYPES.
	 */
	public static function type( WC_Product $product, $attribute ) {
		$overrides = self::product( $product->get_id() );
		$key       = self::key( $attribute );
		$type      = isset( $overrides['attributes'][ $key ] ) ? $overrides['attributes'][ $key ]['type'] : '';
		if ( '' === $type && taxonomy_exists( $attribute ) ) {
			$type = self::attribute( $attribute )['type'];
		}
		if ( '' === $type ) {
			$type = Settings::get( 'auto_buttons' ) ? 'button' : 'dropdown';
		}
		/**
		 * Filters the swatch type of an attribute on a product.
		 *
		 * @param string     $type      color, image, button, radio or dropdown.
		 * @param WC_Product $product   Product.
		 * @param string     $attribute Attribute name.
		 */
		$type = apply_filters( 'swatchcraft_attribute_type', $type, $product, $attribute );
		return in_array( $type, self::TYPES, true ) ? $type : 'dropdown';
	}

	/**
	 * The swatches of an attribute, in the order WooCommerce shows the values.
	 *
	 * @param WC_Product $product   Variable product.
	 * @param string     $attribute Attribute name.
	 * @param string[]   $values    Values offered (term slugs or custom values).
	 * @return array[] { value, label, name, color, color_2, image, image_full }
	 */
	public static function options( WC_Product $product, $attribute, array $values ) {
		$overrides = self::product( $product->get_id() );
		$key       = self::key( $attribute );
		$custom    = isset( $overrides['attributes'][ $key ]['values'] ) ? $overrides['attributes'][ $key ]['values'] : array();
		$out       = array();

		if ( taxonomy_exists( $attribute ) ) {
			$terms = wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) );
			foreach ( $terms as $term ) {
				if ( ! in_array( $term->slug, $values, true ) ) {
					continue;
				}
				$swatch = self::term( $term->term_id );
				if ( isset( $custom[ $term->slug ] ) ) {
					$swatch = array_merge( $swatch, array_filter( $custom[ $term->slug ] ) );
				}
				$out[] = self::option( $term->slug, $term->name, $swatch );
			}
		} else {
			foreach ( $values as $value ) {
				$swatch = isset( $custom[ $value ] ) ? $custom[ $value ] : self::clean_values( array() );
				$out[]  = self::option( $value, $value, $swatch );
			}
		}

		/**
		 * Filters the swatches of an attribute.
		 *
		 * @param array[]    $out       Swatches.
		 * @param WC_Product $product   Product.
		 * @param string     $attribute Attribute name.
		 */
		return apply_filters( 'swatchcraft_attribute_options', $out, $product, $attribute );
	}

	/**
	 * One swatch.
	 *
	 * @param string $value  Value.
	 * @param string $name   Display name.
	 * @param array  $swatch Swatch values.
	 * @return array
	 */
	private static function option( $value, $name, array $swatch ) {
		$image = $swatch['image'] ? wp_get_attachment_image_url( $swatch['image'], 'woocommerce_gallery_thumbnail' ) : '';
		return array(
			'value'      => (string) $value,
			'name'       => wp_strip_all_tags( (string) $name ),
			'label'      => '' !== $swatch['label'] ? $swatch['label'] : wp_strip_all_tags( (string) $name ),
			'color'      => $swatch['color'],
			'color_2'    => $swatch['color_2'],
			'image'      => $image ? $image : '',
			'image_full' => $swatch['image'] ? (string) wp_get_attachment_image_url( $swatch['image'], 'woocommerce_thumbnail' ) : '',
		);
	}
}
