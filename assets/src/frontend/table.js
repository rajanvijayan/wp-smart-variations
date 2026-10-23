/**
 * Variations table: send the quantities to the REST API instead of reloading the page, then
 * refresh WooCommerce's mini cart.
 */
import { __, _n, sprintf } from '@wordpress/i18n';

import config from './config';

async function submit( form ) {
	const items = {};
	form.querySelectorAll( 'input[name^="swatchcraft_qty"]' ).forEach(
		( input ) => {
			const quantity = parseInt( input.value, 10 ) || 0;
			if ( quantity > 0 ) {
				items[ input.name.match( /\[(\d+)\]/ )[ 1 ] ] = quantity;
			}
		}
	);
	const message = form.querySelector( '.swatchcraft-table__message' );
	if ( ! Object.keys( items ).length ) {
		message.textContent = __(
			'Enter a quantity for at least one option.',
			'wp-smart-variations'
		);
		return;
	}
	const button = form.querySelector( 'button[type="submit"]' );
	button.disabled = true;
	form.classList.add( 'is-loading' );
	try {
		const response = await window.fetch( config.restUrl + 'cart', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( {
				product_id: parseInt( form.dataset.product, 10 ),
				items,
			} ),
		} );
		const data = await response.json();
		if ( ! response.ok ) {
			throw new Error( data.message || response.statusText );
		}
		const parts = [];
		if ( data.added ) {
			parts.push(
				sprintf(
					/* translators: %d: number of products. */
					_n(
						'%d product was added to your cart.',
						'%d products were added to your cart.',
						data.added,
						'wp-smart-variations'
					),
					data.added
				)
			);
		}
		parts.push( ...data.errors );
		message.textContent = parts.join( ' ' );
		if ( data.added ) {
			const link = document.createElement( 'a' );
			link.href = data.cart_url;
			link.textContent = __( 'View cart', 'wp-smart-variations' );
			message.append( ' ', link );
			form.querySelectorAll( 'input[name^="swatchcraft_qty"]' ).forEach(
				( input ) => {
					input.value = '0';
				}
			);
			window.jQuery?.( document.body ).trigger( 'wc_fragment_refresh' );
			document.body.dispatchEvent(
				new CustomEvent( 'wc-blocks_added_to_cart' )
			);
		}
	} catch ( error ) {
		// Fall back to posting the form.
		form.dataset.swatchcraftFallback = '1';
		form.submit();
	} finally {
		button.disabled = false;
		form.classList.remove( 'is-loading' );
	}
}

export default function initTable() {
	document.addEventListener( 'submit', ( event ) => {
		const form = event.target.closest( '[data-swatchcraft-table]' );
		if ( ! form || form.dataset.swatchcraftFallback || ! config.restUrl ) {
			return;
		}
		event.preventDefault();
		submit( form );
	} );
}
