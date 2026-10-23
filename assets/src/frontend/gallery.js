/**
 * Variation galleries: swap the product gallery for the chosen variation's images, using the
 * markup WooCommerce printed for them, and re-run WooCommerce's gallery script on it.
 */
import config from './config';

const $ = window.jQuery;

function galleryOf( form ) {
	const product = form.closest( '.product' );
	return ( product || document ).querySelector(
		'.woocommerce-product-gallery'
	);
}

function rebuild( gallery, slides ) {
	const fresh = gallery.cloneNode( false );
	fresh.removeAttribute( 'style' );
	fresh.style.opacity = '0';
	const wrapper = document.createElement( 'div' );
	wrapper.className = 'woocommerce-product-gallery__wrapper';
	wrapper.innerHTML = slides;
	fresh.appendChild( wrapper );
	fresh.classList.remove(
		'woocommerce-product-gallery--with-images',
		'woocommerce-product-gallery--without-images'
	);
	fresh.classList.add( 'woocommerce-product-gallery--with-images' );
	gallery.replaceWith( fresh );
	if ( $.fn.wc_product_gallery ) {
		$( fresh ).wc_product_gallery( window.wc_single_product_params || {} );
	} else {
		fresh.style.opacity = '1';
	}
	return fresh;
}

export default function initGallery() {
	if ( ! $ || ! config.gallery ) {
		return;
	}
	const originals = new WeakMap();

	$( document ).on(
		'found_variation',
		'.variations_form',
		function ( event, variation ) {
			const gallery = galleryOf( this );
			if ( ! gallery || ! variation.swatchcraft_gallery ) {
				return;
			}
			if ( ! originals.has( this ) ) {
				originals.set(
					this,
					gallery.querySelector(
						'.woocommerce-product-gallery__wrapper'
					)?.innerHTML || ''
				);
			}
			rebuild( gallery, variation.swatchcraft_gallery.join( '' ) );
			this.dataset.swatchcraftGallery = 'variation';
		}
	);

	$( document ).on(
		'reset_data found_variation',
		'.variations_form',
		function ( event, variation ) {
			const showingVariation =
				this.dataset.swatchcraftGallery === 'variation';
			const switchingToPlain =
				event.type === 'found_variation' &&
				variation &&
				! variation.swatchcraft_gallery;
			if (
				! showingVariation ||
				( event.type === 'found_variation' && ! switchingToPlain )
			) {
				return;
			}
			const gallery = galleryOf( this );
			if ( gallery && originals.has( this ) ) {
				rebuild( gallery, originals.get( this ) );
				this.dataset.swatchcraftGallery = '';
				// Let WooCommerce show the plain variation image on the restored gallery.
				if ( switchingToPlain ) {
					$( this ).wc_variations_image_update( variation );
				}
			}
		}
	);
}
