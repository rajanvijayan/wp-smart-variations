<?php
/**
 * Swatch settings on the Products → Attributes screen.
 *
 * @package SwatchCraft
 */

namespace SwatchCraft\Admin;

use SwatchCraft\Data\Swatch_Data;

/**
 * Adds "Swatch type" and "Show on product cards" to the add and edit attribute forms. The type
 * is stored by SwatchCraft, so WooCommerce's own attribute type (and its term selector on the
 * product screen) is left alone.
 */
final class Attribute_Fields {

	const NONCE = 'swatchcraft_attribute';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'woocommerce_after_add_attribute_fields', array( self::class, 'add_fields' ) );
		add_action( 'woocommerce_after_edit_attribute_fields', array( self::class, 'edit_fields' ) );
		add_action( 'woocommerce_attribute_added', array( self::class, 'save' ), 10, 1 );
		add_action( 'woocommerce_attribute_updated', array( self::class, 'save' ), 10, 1 );
		add_action( 'woocommerce_attribute_deleted', array( Swatch_Data::class, 'delete_attribute' ), 10, 1 );
	}

	/**
	 * The type select.
	 *
	 * @param string $current Current type.
	 * @return string
	 */
	private static function select( $current ) {
		$html = '<select name="swatchcraft_type" id="swatchcraft_type">';
		foreach ( Swatch_Data::type_labels() as $value => $label ) {
			$html .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		return $html . '</select>';
	}

	/**
	 * Fields on the "Add new attribute" form.
	 */
	public static function add_fields() {
		wp_nonce_field( self::NONCE, '_swatchcraft_nonce' );
		?>
		<div class="form-field">
			<label for="swatchcraft_type"><?php esc_html_e( 'Swatch type', 'wp-smart-variations' ); ?></label>
			<?php echo self::select( '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in select(). ?>
			<p class="description"><?php esc_html_e( 'How the values of this attribute are shown on product pages. Set colours and images on each term.', 'wp-smart-variations' ); ?></p>
		</div>
		<div class="form-field">
			<label for="swatchcraft_loop"><input type="checkbox" name="swatchcraft_loop" id="swatchcraft_loop" value="1" /> <?php esc_html_e( 'Show swatches on product cards', 'wp-smart-variations' ); ?></label>
		</div>
		<?php
	}

	/**
	 * Fields on the "Edit attribute" form.
	 */
	public static function edit_fields() {
		$id       = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only; WooCommerce checks the form.
		$settings = Swatch_Data::attribute_settings();
		$current  = isset( $settings[ $id ] ) ? $settings[ $id ] : array();
		wp_nonce_field( self::NONCE, '_swatchcraft_nonce' );
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="swatchcraft_type"><?php esc_html_e( 'Swatch type', 'wp-smart-variations' ); ?></label></th>
			<td>
				<?php echo self::select( isset( $current['type'] ) ? $current['type'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in select(). ?>
				<p class="description"><?php esc_html_e( 'How the values of this attribute are shown on product pages. Set colours and images on each term.', 'wp-smart-variations' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><?php esc_html_e( 'Product cards', 'wp-smart-variations' ); ?></th>
			<td><label><input type="checkbox" name="swatchcraft_loop" value="1" <?php checked( ! empty( $current['loop'] ) ); ?> /> <?php esc_html_e( 'Show swatches on product cards', 'wp-smart-variations' ); ?></label></td>
		</tr>
		<?php
	}

	/**
	 * Save after WooCommerce saved the attribute.
	 *
	 * @param int $id Attribute ID.
	 */
	public static function save( $id ) {
		if ( ! isset( $_POST['_swatchcraft_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_swatchcraft_nonce'] ) ), self::NONCE ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		Swatch_Data::save_attribute(
			(int) $id,
			array(
				'type' => isset( $_POST['swatchcraft_type'] ) ? sanitize_key( wp_unslash( $_POST['swatchcraft_type'] ) ) : '',
				'loop' => ! empty( $_POST['swatchcraft_loop'] ),
			)
		);
	}
}
