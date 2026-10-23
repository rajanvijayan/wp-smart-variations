/**
 * Product card swatches: show the variation's image on the card and point the card's links at
 * the product with that value preselected.
 */

const CARD = 'li.product, .wc-block-product, .wp-block-post, .product';

function cardImage( card ) {
	return card.querySelector(
		'img.attachment-woocommerce_thumbnail, .wc-block-components-product-image img, img'
	);
}

function setLinks( wrapper, card, value ) {
	const base = wrapper.dataset.link;
	card.querySelectorAll( 'a[href]' ).forEach( ( link ) => {
		if (
			link.closest( '[data-swatchcraft-loop]' ) &&
			! link.classList.contains( 'swatchcraft__more' )
		) {
			return;
		}
		const original =
			link.dataset.swatchcraftHref || link.getAttribute( 'href' );
		if ( ! original.startsWith( base ) ) {
			return;
		}
		link.dataset.swatchcraftHref = original;
		const url = new URL( original, window.location.href );
		if ( value ) {
			url.searchParams.set( wrapper.dataset.key, value );
		} else {
			url.searchParams.delete( wrapper.dataset.key );
		}
		link.setAttribute( 'href', url.toString() );
	} );
}

function setImage( card, src ) {
	const img = cardImage( card );
	if ( ! img ) {
		return;
	}
	if ( img.dataset.swatchcraftSrc === undefined ) {
		img.dataset.swatchcraftSrc = img.getAttribute( 'src' ) || '';
		img.dataset.swatchcraftSrcset = img.getAttribute( 'srcset' ) || '';
	}
	if ( src ) {
		img.setAttribute( 'src', src );
		img.removeAttribute( 'srcset' );
	} else {
		img.setAttribute( 'src', img.dataset.swatchcraftSrc );
		if ( img.dataset.swatchcraftSrcset ) {
			img.setAttribute( 'srcset', img.dataset.swatchcraftSrcset );
		}
	}
}

export default function initLoop() {
	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest(
			'[data-swatchcraft-loop] .swatchcraft__option'
		);
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		const wrapper = button.closest( '[data-swatchcraft-loop]' );
		const card = wrapper.closest( CARD ) || wrapper.parentElement;
		const on = button.getAttribute( 'aria-pressed' ) !== 'true';
		wrapper
			.querySelectorAll( '.swatchcraft__option' )
			.forEach( ( other ) => {
				other.setAttribute( 'aria-pressed', 'false' );
				other.classList.remove( 'is-selected' );
			} );
		button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		button.classList.toggle( 'is-selected', on );
		setImage( card, on ? button.dataset.image : '' );
		setLinks( wrapper, card, on ? button.dataset.value : '' );
	} );
}
