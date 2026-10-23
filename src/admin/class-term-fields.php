<?php
/**
 * Colour, image and label fields on attribute terms.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Admin;

use SwatchCraft\Data\Swatch_Data;
use SwatchCraft\Front\Assets;
use WP_Term;

/**
 * Adds the swatch fields to the add/edit term forms of every product attribute, and a preview
 * column to the term list.
 */
final class Term_Fields {

	const NONCE = 'swatchcraft_term';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_init', array( self::class, 'attach' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Hook into each attribute taxonomy.
	 */
	public static function attach() {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
			add_action( "{$taxonomy}_add_form_fields", array( self::class, 'add_fields' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( self::class, 'edit_fields' ), 10, 2 );
			add_action( "created_{$taxonomy}", array( self::class, 'save' ) );
			add_action( "edited_{$taxonomy}", array( self::class, 'save' ) );
			add_filter( "manage_edit-{$taxonomy}_columns", array( self::class, 'columns' ) );
			add_filter( "manage_{$taxonomy}_custom_column", array( self::class, 'column' ), 10, 3 );
		}
	}

	/**
	 * Scripts for the colour and media pickers on attribute term screens.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) || ! $screen || 0 !== strpos( (string) $screen->taxonomy, 'pa_' ) ) {
			return;
		}
		Assets::enqueue_admin();
	}

	/**
	 * Type of the attribute on the current screen.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	private static function type( $taxonomy ) {
		return Swatch_Data::attribute( $taxonomy )['type'];
	}

	/**
	 * The fields.
	 *
	 * @param array  $values Saved values.
	 * @param string $type   Attribute type.
	 * @param bool   $table  Table layout (edit form).
	 */
	private static function fields( array $values, $type, $table ) {
		$fields = array();
		if ( in_array( $type, array( 'color', '' ), true ) ) {
			$fields[] = array(
				'label' => __( 'Colour', 'wp-smart-variations' ),
				'html'  => sprintf( '<input type="text" class="swatchcraft-color" name="swatchcraft[color]" value="%s" />', esc_attr( $values['color'] ) ),
			);
			$fields[] = array(
				'label' => __( 'Second colour', 'wp-smart-variations' ),
				'html'  => sprintf( '<input type="text" class="swatchcraft-color" name="swatchcraft[color_2]" value="%s" />', esc_attr( $values['color_2'] ) ),
				'help'  => __( 'Optional. The swatch is split diagonally between the two colours.', 'wp-smart-variations' ),
			);
		}
		if ( in_array( $type, array( 'image', '' ), true ) ) {
			$preview  = $values['image'] ? wp_get_attachment_image( $values['image'], 'thumbnail', false, array( 'class' => 'swatchcraft-media__preview' ) ) : '';
			$fields[] = array(
				'label' => __( 'Image', 'wp-smart-variations' ),
				'html'  => sprintf(
					'<div class="swatchcraft-media" data-swatchcraft-media><input type="hidden" name="swatchcraft[image]" value="%1$d" />%2$s<button type="button" class="button" data-action="choose">%3$s</button> <button type="button" class="button-link button-link-delete" data-action="remove"%4$s>%5$s</button></div>',
					(int) $values['image'],
					$preview,
					esc_html__( 'Choose image', 'wp-smart-variations' ),
					$values['image'] ? '' : ' hidden',
					esc_html__( 'Remove', 'wp-smart-variations' )
				),
			);
		}
		$fields[] = array(
			'label' => __( 'Swatch label', 'wp-smart-variations' ),
			'html'  => sprintf( '<input type="text" name="swatchcraft[label]" value="%s" maxlength="40" />', esc_attr( $values['label'] ) ),
			'help'  => __( 'Optional short text for buttons, e.g. "XL" for "Extra large".', 'wp-smart-variations' ),
		);

		foreach ( $fields as $field ) {
			$help = isset( $field['help'] ) ? '<p class="description">' . esc_html( $field['help'] ) . '</p>' : '';
			if ( $table ) {
				printf( '<tr class="form-field"><th scope="row">%s</th><td>%s%s</td></tr>', esc_html( $field['label'] ), $field['html'], $help ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			} else {
				printf( '<div class="form-field"><label>%s</label>%s%s</div>', esc_html( $field['label'] ), $field['html'], $help ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			}
		}
	}

	/**
	 * Add-term form.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public static function add_fields( $taxonomy ) {
		wp_nonce_field( self::NONCE, '_swatchcraft_term_nonce' );
		self::fields( Swatch_Data::clean_values( array() ), self::type( $taxonomy ), false );
	}

	/**
	 * Edit-term form.
	 *
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 */
	public static function edit_fields( $term, $taxonomy ) {
		wp_nonce_field( self::NONCE, '_swatchcraft_term_nonce' );
		self::fields( Swatch_Data::term( $term->term_id ), self::type( $taxonomy ), true );
	}

	/**
	 * Save.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save( $term_id ) {
		if ( ! isset( $_POST['_swatchcraft_term_nonce'], $_POST['swatchcraft'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_swatchcraft_term_nonce'] ) ), self::NONCE ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		$values = map_deep( wp_unslash( (array) $_POST['swatchcraft'] ), 'sanitize_text_field' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by map_deep and clean_values().
		Swatch_Data::save_term( $term_id, $values );
	}

	/**
	 * Preview column.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$out['swatchcraft'] = '<span class="screen-reader-text">' . esc_html__( 'Swatch', 'wp-smart-variations' ) . '</span>';
			}
			$out[ $key ] = $label;
		}
		return $out;
	}

	/**
	 * Preview column content.
	 *
	 * @param string $content Content.
	 * @param string $column  Column.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public static function column( $content, $column, $term_id ) {
		if ( 'swatchcraft' !== $column ) {
			return $content;
		}
		$values = Swatch_Data::term( $term_id );
		if ( $values['image'] ) {
			return wp_get_attachment_image( $values['image'], array( 32, 32 ), false, array( 'class' => 'swatchcraft-preview' ) );
		}
		if ( $values['color'] ) {
			$second = $values['color_2'] ? $values['color_2'] : $values['color'];
			return sprintf( '<span class="swatchcraft-preview" style="background:linear-gradient(135deg,%1$s 50%%,%2$s 50%%)"></span>', esc_attr( $values['color'] ), esc_attr( $second ) );
		}
		return $content;
	}
}
