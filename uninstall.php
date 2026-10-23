<?php
/**
 * Uninstall: remove SwatchCraft's options and meta.
 *
 * @package SwatchCraft
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'swatchcraft_settings' );
delete_option( 'swatchcraft_attributes' );
delete_metadata( 'post', 0, '_swatchcraft', '', true );
delete_metadata( 'post', 0, '_swatchcraft_gallery', '', true );
foreach ( array( 'swatchcraft_color', 'swatchcraft_color_2', 'swatchcraft_image', 'swatchcraft_label' ) as $swatchcraft_meta ) {
	delete_metadata( 'term', 0, $swatchcraft_meta, '', true );
}
