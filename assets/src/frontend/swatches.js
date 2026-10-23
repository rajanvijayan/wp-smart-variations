/**
 * Product page swatches. WooCommerce's variation form stays in charge: a swatch sets the hidden
 * select and triggers "change"; after WooCommerce updates the selects
 * ("woocommerce_update_variation_values") the swatches mirror them.
 */
import { __ } from '@wordpress/i18n';

import config from './config';

const $ = window.jQuery;

function selectFor( form, group ) {
	return form.querySelector(
		`select[data-attribute_name="${ group.dataset.attribute }"]`
	);
}

function variationsOf( form ) {
	if ( form._swatchcraftVariations !== undefined ) {
		return form._swatchcraftVariations;
	}
	let data = false;
	try {
		data = JSON.parse( form.dataset.product_variations || 'false' );
	} catch ( e ) {
		data = false;
	}
	// "false" means WooCommerce looks variations up with AJAX (many variations).
	form._swatchcraftVariations = Array.isArray( data ) ? data : false;
	return form._swatchcraftVariations;
}

function currentSelection( form ) {
	const out = {};
	form.querySelectorAll( 'select[data-attribute_name]' ).forEach(
		( select ) => {
			out[ select.dataset.attribute_name ] = select.value;
		}
	);
	return out;
}

function matches( variation, wanted ) {
	return Object.entries( wanted ).every( ( [ name, value ] ) => {
		if ( ! value ) {
			return true;
		}
		const own = variation.attributes[ name ];
		// An empty variation value means "any".
		return own === undefined || own === '' || own === value;
	} );
}

/**
 * Whether every variation that would match, if this value were chosen with the other current
 * choices, is out of stock.
 *
 * @param {HTMLFormElement} form      Form.
 * @param {string}          attribute attribute_* name.
 * @param {string}          value     Value.
 * @return {boolean} Out of stock.
 */
export function isOutOfStock( form, attribute, value ) {
	const variations = variationsOf( form );
	if ( ! variations ) {
		return false;
	}
	const wanted = { ...currentSelection( form ), [ attribute ]: value };
	const candidates = variations.filter( ( v ) => matches( v, wanted ) );
	return (
		candidates.length > 0 && candidates.every( ( v ) => ! v.is_in_stock )
	);
}

function updateLabel( form, group, select ) {
	if ( ! config.showSelected || ! select.id ) {
		return;
	}
	const label = form.querySelector(
		`label[for="${ CSS.escape( select.id ) }"]`
	);
	if ( ! label ) {
		return;
	}
	let span = label.querySelector( '.swatchcraft-selected' );
	if ( ! span ) {
		span = document.createElement( 'span' );
		span.className = 'swatchcraft-selected';
		label.appendChild( span );
	}
	const chosen = group.querySelector( '.swatchcraft__option.is-selected' );
	span.textContent = chosen ? `: ${ chosen.dataset.name }` : '';
}

export function sync( form ) {
	form.querySelectorAll( '[data-swatchcraft]' ).forEach( ( group ) => {
		const select = selectFor( form, group );
		if ( ! select ) {
			return;
		}
		const present = new Map();
		Array.from( select.options ).forEach( ( option ) => {
			if ( option.value ) {
				present.set( option.value, option );
			}
		} );
		group
			.querySelectorAll( '.swatchcraft__option' )
			.forEach( ( button ) => {
				const value = button.dataset.value;
				const option = present.get( value );
				const unavailable = ! option || option.disabled;
				const outOfStock =
					! unavailable &&
					isOutOfStock( form, group.dataset.attribute, value );
				const selected = select.value === value;

				button.classList.toggle( 'is-unavailable', unavailable );
				button.classList.toggle( 'is-out-of-stock', outOfStock );
				button.classList.toggle( 'is-selected', selected );
				button.hidden =
					( unavailable &&
						config.unavailable === 'hide' &&
						! selected ) ||
					( outOfStock &&
						config.outOfStock === 'hide' &&
						! selected );
				const blocked = outOfStock && config.outOfStock === 'cross';
				button.setAttribute(
					'aria-checked',
					selected ? 'true' : 'false'
				);
				// An unavailable swatch still works (it starts the choice again), so only a
				// blocked out-of-stock one is disabled.
				button.setAttribute(
					'aria-disabled',
					blocked ? 'true' : 'false'
				);
				button.tabIndex = -1;
				let tooltip = button.dataset.baseTooltip;
				if ( tooltip === undefined ) {
					tooltip = button.dataset.tooltip || '';
					button.dataset.baseTooltip = tooltip;
				}
				if ( unavailable ) {
					tooltip += ` · ${ __(
						'Not available with your other choices',
						'wp-smart-variations'
					) }`;
				} else if ( outOfStock ) {
					tooltip += ` · ${ __(
						'Out of stock',
						'wp-smart-variations'
					) }`;
				}
				button.dataset.tooltip = tooltip;
			} );
		// One tab stop per group: the chosen swatch, else the first visible one.
		const buttons = Array.from(
			group.querySelectorAll( '.swatchcraft__option' )
		);
		const stop =
			buttons.find( ( b ) => b.classList.contains( 'is-selected' ) ) ||
			buttons.find( ( b ) => ! b.hidden );
		if ( stop ) {
			stop.tabIndex = 0;
		}
		updateLabel( form, group, select );
	} );
}

function choose( form, button ) {
	const group = button.closest( '[data-swatchcraft]' );
	const select = selectFor( form, group );
	if ( ! select ) {
		return;
	}
	const value = button.dataset.value;
	if (
		button.classList.contains( 'is-out-of-stock' ) &&
		config.outOfStock === 'cross'
	) {
		return;
	}
	if ( select.value === value ) {
		// Clicking the chosen swatch again clears it.
		$( select ).val( '' ).trigger( 'change' );
		return;
	}
	if ( button.classList.contains( 'is-unavailable' ) ) {
		// Not possible with the other choices: start again from this value.
		form.querySelectorAll( 'select[data-attribute_name]' ).forEach(
			( other ) => {
				if ( other !== select ) {
					$( other ).val( '' );
				}
			}
		);
		$( select ).trigger( 'change' );
	}
	$( select ).val( value ).trigger( 'change' );
}

function onKey( event ) {
	const button = event.target.closest(
		'[data-swatchcraft] .swatchcraft__option'
	);
	const keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
	if (
		! button ||
		! keys[ event.key ] ||
		! button.closest( '.variations_form' )
	) {
		return;
	}
	event.preventDefault();
	const visible = Array.from(
		button
			.closest( '[data-swatchcraft]' )
			.querySelectorAll( '.swatchcraft__option' )
	).filter( ( b ) => ! b.hidden );
	const index = visible.indexOf( button );
	const next =
		visible[
			( index + keys[ event.key ] + visible.length ) % visible.length
		];
	next.focus();
	// Moving through the group never clears the other choices.
	if (
		next.getAttribute( 'aria-disabled' ) !== 'true' &&
		! next.classList.contains( 'is-unavailable' )
	) {
		next.click();
	}
}

export default function initSwatches() {
	if ( ! $ ) {
		return;
	}
	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest(
			'.variations_form [data-swatchcraft] .swatchcraft__option'
		);
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		choose( button.closest( '.variations_form' ), button );
	} );
	document.addEventListener( 'keydown', onKey );
	// Above the AJAX threshold WooCommerce skips woocommerce_update_variation_values, but it
	// always fires woocommerce_variation_has_changed.
	$( document ).on(
		'woocommerce_update_variation_values woocommerce_variation_has_changed reset_data wc_variation_form',
		'.variations_form',
		function () {
			sync( this );
		}
	);
	// Some themes reset with WooCommerce's "Clear" link before the form is initialised.
	$( document ).on(
		'click',
		'.variations_form .reset_variations',
		function () {
			window.setTimeout(
				() => sync( this.closest( '.variations_form' ) ),
				0
			);
		}
	);
	document.querySelectorAll( '.variations_form' ).forEach( sync );
}
