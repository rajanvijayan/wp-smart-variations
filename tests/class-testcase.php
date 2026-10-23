<?php
/**
 * Base test case with WooCommerce product helpers.
 *
 * @package SwatchCraft
 */

use SwatchCraft\Data\Swatch_Data;
use SwatchCraft\Settings;

/**
 * Default settings for every test, and helpers to build attributes and variable products.
 */
abstract class SwatchCraft_TestCase extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION );
		delete_option( Swatch_Data::ATTRIBUTES_OPTION );
		Settings::flush();
	}

	public function tear_down() {
		Settings::flush();
		parent::tear_down();
	}

	/**
	 * A global attribute with terms.
	 *
	 * @param string   $name  Attribute name (e.g. "color").
	 * @param string[] $terms Term names.
	 * @param string   $type  Swatch type.
	 * @return array { id, taxonomy, terms: slug => term_id }
	 */
	protected function attribute( $name, array $terms, $type = '' ) {
		$taxonomy = wc_attribute_taxonomy_name( $name );
		$id       = wc_attribute_taxonomy_id_by_name( $taxonomy );
		if ( ! $id ) {
			// The taxonomy of an earlier test survives the database rollback; WooCommerce would see a taken slug.
			unregister_taxonomy( $taxonomy );
			delete_transient( 'wc_attribute_taxonomies' );
			$id = wc_create_attribute(
				array(
					'name' => ucfirst( $name ),
					'slug' => $name,
				)
			);
			$this->assertIsInt( $id );
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false ) );
		}
		$out = array();
		foreach ( $terms as $term ) {
			$existing = term_exists( $term, $taxonomy );
			$result   = $existing ? $existing : wp_insert_term( $term, $taxonomy );
			$slug     = get_term( (int) $result['term_id'], $taxonomy )->slug;
			$out[ $slug ] = (int) $result['term_id'];
		}
		if ( '' !== $type ) {
			Swatch_Data::save_attribute( $id, array( 'type' => $type ) );
		}
		return array(
			'id'       => $id,
			'taxonomy' => $taxonomy,
			'terms'    => $out,
		);
	}

	/**
	 * A variable product.
	 *
	 * @param array $attributes  Name => values. Global attributes as attribute() results, custom ones as [ 'Size' => ['S','M'] ].
	 * @param array $variations  List of [ attribute key => value, '_price' => 10, '_stock' => null|int ].
	 * @return WC_Product_Variable
	 */
	protected function variable_product( array $attributes, array $variations ) {
		$product = new WC_Product_Variable();
		$product->set_name( 'Test shirt' );
		$list = array();
		foreach ( $attributes as $key => $attribute ) {
			$object = new WC_Product_Attribute();
			if ( is_array( $attribute ) && isset( $attribute['taxonomy'] ) ) {
				$object->set_id( $attribute['id'] );
				$object->set_name( $attribute['taxonomy'] );
				$object->set_options( array_values( $attribute['terms'] ) );
			} else {
				$object->set_name( $key );
				$object->set_options( $attribute );
			}
			$object->set_visible( true );
			$object->set_variation( true );
			$list[] = $object;
		}
		$product->set_attributes( $list );
		$product->save();

		foreach ( $variations as $data ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product->get_id() );
			$price = isset( $data['_price'] ) ? $data['_price'] : 10;
			$stock = array_key_exists( '_stock', $data ) ? $data['_stock'] : null;
			unset( $data['_price'], $data['_stock'] );
			$variation->set_attributes( $data );
			$variation->set_regular_price( (string) $price );
			if ( null !== $stock ) {
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( $stock );
			}
			$variation->save();
		}
		WC_Product_Variable::sync( $product->get_id() );
		return wc_get_product( $product->get_id() );
	}
}
