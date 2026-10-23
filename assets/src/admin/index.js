/**
 * SwatchCraft admin: colour and image pickers on attribute terms, the product Swatches tab and
 * variation galleries.
 */
import initMedia from './media';

import './index.scss';

function initColors( root = document ) {
	if ( ! window.jQuery || ! window.jQuery.fn.wpColorPicker ) {
		return;
	}
	window
		.jQuery( root )
		.find( '.swatchcraft-color' )
		.not( '.wp-color-picker' )
		.wpColorPicker();
}

function start() {
	initMedia();
	initColors();
	// The add-term form is submitted with AJAX; reset the pickers after a term is added.
	window.jQuery( document ).ajaxComplete( ( event, xhr, settings ) => {
		if (
			settings.data &&
			String( settings.data ).includes( 'action=add-tag' )
		) {
			document
				.querySelectorAll(
					'#addtag [data-swatchcraft-media] .swatchcraft-media__preview'
				)
				.forEach( ( img ) => img.remove() );
			initColors();
		}
	} );
	// Variation panels and the Swatches tab are loaded on demand.
	window
		.jQuery( document )
		.on( 'woocommerce_variations_loaded swatchcraft_tab_loaded', () =>
			initColors()
		);
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
