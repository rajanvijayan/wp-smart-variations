<?php
/**
 * "Swatches" tab in the product data box.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Admin;

use SwatchCraft\Data\Swatch_Data;
use WC_Product;
use WC_Product_Attribute;

/**
 * Per-product settings: swatch type of each variation attribute, colours/images/labels of their
 * values (the only way to style custom product attributes), product card swatches and the
 * variations table.
 */
final class Product_Tab {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_filter( 'woocommerce_product_data_tabs', array( self::class, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( self::class, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
	}

	/**
	 * The tab, shown for variable products only.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function tab( $tabs ) {
		$tabs['swatchcraft'] = array(
			'label'    => __( 'Swatches', 'wp-smart-variations' ),
			'target'   => 'swatchcraft_product_data',
			'class'    => array( 'show_if_variable' ),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * Variation attributes of a product.
	 *
	 * @param WC_Product $product Product.
	 * @return WC_Product_Attribute[]
	 */
	private static function attributes( WC_Product $product ) {
		return array_filter(
			$product->get_attributes(),
			static function ( $attribute ) {
				return $attribute instanceof WC_Product_Attribute && $attribute->get_variation();
			}
		);
	}

	/**
	 * Values of an attribute: [ value => name ].
	 *
	 * @param WC_Product_Attribute $attribute Attribute.
	 * @return string[]
	 */
	private static function values( WC_Product_Attribute $attribute ) {
		if ( ! $attribute->is_taxonomy() ) {
			return array_combine( $attribute->get_options(), $attribute->get_options() );
		}
		$out = array();
		foreach ( $attribute->get_terms() ? $attribute->get_terms() : array() as $term ) {
			$out[ $term->slug ] = $term->name;
		}
		return $out;
	}

	/**
	 * The panel.
	 */
	public static function panel() {
		global $product_object;
		$product = $product_object instanceof WC_Product ? $product_object : null;
		$saved   = $product ? Swatch_Data::product( $product->get_id() ) : Swatch_Data::product( 0 );
		echo '<div id="swatchcraft_product_data" class="panel woocommerce_options_panel hidden">';

		woocommerce_wp_select(
			array(
				'id'      => 'swatchcraft_loop',
				'label'   => __( 'Product cards', 'wp-smart-variations' ),
				'value'   => null === $saved['loop'] ? '' : ( $saved['loop'] ? '1' : '0' ),
				'options' => array(
					''  => __( 'Default (from settings)', 'wp-smart-variations' ),
					'1' => __( 'Show swatches', 'wp-smart-variations' ),
					'0' => __( 'Hide swatches', 'wp-smart-variations' ),
				),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => 'swatchcraft_table',
				'label'   => __( 'Variations table', 'wp-smart-variations' ),
				'value'   => $saved['table'],
				'options' => array(
					''     => __( 'Default (from settings)', 'wp-smart-variations' ),
					'show' => __( 'Show', 'wp-smart-variations' ),
					'hide' => __( 'Hide', 'wp-smart-variations' ),
				),
			)
		);

		$attributes = $product ? self::attributes( $product ) : array();
		if ( ! $attributes ) {
			echo '<p class="swatchcraft-tab-note">' . esc_html__( 'Add attributes "Used for variations" and save the product to choose their swatches here.', 'wp-smart-variations' ) . '</p></div>';
			return;
		}

		foreach ( $attributes as $attribute ) {
			$key      = Swatch_Data::key( $attribute->get_name() );
			$current  = isset( $saved['attributes'][ $key ] ) ? $saved['attributes'][ $key ] : array(
				'type'   => '',
				'values' => array(),
			);
			$global   = $attribute->is_taxonomy() ? Swatch_Data::attribute( $attribute->get_name() )['type'] : '';
			$base     = 'swatchcraft[' . $key . ']';
			$type_ids = 'swatchcraft_type_' . $key;
			echo '<div class="options_group swatchcraft-attribute">';
			printf( '<h4 class="swatchcraft-attribute__title">%s</h4>', esc_html( wc_attribute_label( $attribute->get_name(), $product ) ) );
			$labels     = Swatch_Data::type_labels();
			$labels[''] = $global
				/* translators: %s: swatch type set on the attribute. */
				? sprintf( __( 'From the attribute (%s)', 'wp-smart-variations' ), $labels[ $global ] )
				: $labels[''];
			woocommerce_wp_select(
				array(
					'id'      => $type_ids,
					'name'    => $base . '[type]',
					'label'   => __( 'Swatch type', 'wp-smart-variations' ),
					'value'   => $current['type'],
					'options' => $labels,
				)
			);

			echo '<table class="widefat striped swatchcraft-values"><thead><tr><th>' . esc_html__( 'Value', 'wp-smart-variations' ) . '</th><th>' . esc_html__( 'Colour', 'wp-smart-variations' ) . '</th><th>' . esc_html__( 'Second colour', 'wp-smart-variations' ) . '</th><th>' . esc_html__( 'Image', 'wp-smart-variations' ) . '</th><th>' . esc_html__( 'Label', 'wp-smart-variations' ) . '</th></tr></thead><tbody>';
			foreach ( self::values( $attribute ) as $value => $name ) {
				$own   = isset( $current['values'][ $value ] ) ? $current['values'][ $value ] : Swatch_Data::clean_values( array() );
				$field = $base . '[values][' . $value . ']';
				$image = $own['image'] ? wp_get_attachment_image( $own['image'], 'thumbnail', false, array( 'class' => 'swatchcraft-media__preview' ) ) : '';
				printf(
					'<tr><td>%1$s</td><td><input type="text" class="swatchcraft-color" name="%2$s[color]" value="%3$s" /></td><td><input type="text" class="swatchcraft-color" name="%2$s[color_2]" value="%4$s" /></td><td><div class="swatchcraft-media" data-swatchcraft-media><input type="hidden" name="%2$s[image]" value="%5$d" />%6$s<button type="button" class="button button-small" data-action="choose">%7$s</button><button type="button" class="button-link button-link-delete" data-action="remove"%8$s>%9$s</button></div></td><td><input type="text" name="%2$s[label]" value="%10$s" maxlength="40" class="short" /></td></tr>',
					esc_html( $name ),
					esc_attr( $field ),
					esc_attr( $own['color'] ),
					esc_attr( $own['color_2'] ),
					(int) $own['image'],
					$image, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image().
					esc_html__( 'Choose', 'wp-smart-variations' ),
					$own['image'] ? '' : ' hidden',
					esc_html__( 'Remove', 'wp-smart-variations' ),
					esc_attr( $own['label'] )
				);
			}
			echo '</tbody></table>';
			if ( $attribute->is_taxonomy() ) {
				echo '<p class="description swatchcraft-tab-note">' . esc_html__( 'Leave values empty to use the colours and images set on the attribute terms.', 'wp-smart-variations' ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Save (WooCommerce checks the nonce and capability before this runs).
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public static function save( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce (woocommerce_meta_nonce).
		if ( ! isset( $_POST['swatchcraft_loop'] ) && ! isset( $_POST['swatchcraft_table'] ) ) {
			return;
		}
		$attributes = isset( $_POST['swatchcraft'] ) && is_array( $_POST['swatchcraft'] ) ? map_deep( wp_unslash( $_POST['swatchcraft'] ), 'sanitize_text_field' ) : array();
		$loop       = isset( $_POST['swatchcraft_loop'] ) ? sanitize_key( wp_unslash( $_POST['swatchcraft_loop'] ) ) : '';
		$table      = isset( $_POST['swatchcraft_table'] ) ? sanitize_key( wp_unslash( $_POST['swatchcraft_table'] ) ) : '';
		// phpcs:enable
		Swatch_Data::save_product(
			$product->get_id(),
			array(
				'attributes' => $attributes,
				'loop'       => '' === $loop ? null : $loop,
				'table'      => $table,
			)
		);
	}
}
