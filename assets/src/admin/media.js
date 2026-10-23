/**
 * Image pickers: a [data-swatchcraft-media] wrapper holding a hidden input (attachment ID), an
 * optional preview image, and "choose" / "remove" buttons. Multiple mode keeps a comma-separated
 * list of IDs (variation galleries).
 */
import { __ } from '@wordpress/i18n';

function preview( wrapper, attachments ) {
	wrapper.querySelectorAll( '.swatchcraft-media__preview' ).forEach( ( img ) => img.remove() );
	const choose = wrapper.querySelector( '[data-action="choose"]' );
	attachments.forEach( ( attachment ) => {
		const sizes = attachment.sizes || {};
		const img = document.createElement( 'img' );
		img.className = 'swatchcraft-media__preview';
		img.alt = '';
		img.src = ( sizes.thumbnail || sizes.full || attachment ).url;
		wrapper.insertBefore( img, choose );
	} );
}

function open( wrapper ) {
	const multiple = wrapper.dataset.multiple === '1';
	const input = wrapper.querySelector( 'input[type="hidden"]' );
	const frame = window.wp.media( {
		title: multiple
			? __( 'Variation images', 'wp-smart-variations' )
			: __( 'Swatch image', 'wp-smart-variations' ),
		library: { type: 'image' },
		multiple: multiple ? 'add' : false,
		button: { text: __( 'Use these images', 'wp-smart-variations' ) },
	} );
	frame.on( 'open', () => {
		const selection = frame.state().get( 'selection' );
		input.value
			.split( ',' )
			.filter( Boolean )
			.forEach( ( id ) => {
				const attachment = window.wp.media.attachment( id );
				attachment.fetch();
				selection.add( attachment );
			} );
	} );
	frame.on( 'select', () => {
		const chosen = frame.state().get( 'selection' ).toJSON();
		input.value = chosen.map( ( a ) => a.id ).join( ',' );
		preview( wrapper, chosen );
		wrapper.querySelector( '[data-action="remove"]' ).hidden = ! chosen.length;
		// WooCommerce's variation panels only save when they are marked as changed.
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		const variation = wrapper.closest( '.woocommerce_variation' );
		if ( variation ) {
			variation.classList.add( 'variation-needs-update' );
			window.jQuery( 'button.cancel-variation-changes, button.save-variation-changes' ).prop( 'disabled', false );
		}
	} );
	frame.open();
}

export default function initMedia() {
	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-swatchcraft-media] [data-action]' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		const wrapper = button.closest( '[data-swatchcraft-media]' );
		if ( button.dataset.action === 'choose' ) {
			open( wrapper );
			return;
		}
		wrapper.querySelector( 'input[type="hidden"]' ).value = '';
		preview( wrapper, [] );
		button.hidden = true;
		wrapper.querySelector( 'input[type="hidden"]' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
	} );
}
