/**
 * WP eCommerce: turn each variation dropdown into radio buttons that keep the dropdown in sync,
 * so WP eCommerce's own price and stock updates keep working.
 */
( function () {
	function enhance( select ) {
		var group = document.createElement( 'div' );
		group.className = 'swatchcraft-wpsc';
		group.setAttribute( 'role', 'radiogroup' );
		Array.prototype.forEach.call( select.options, function ( option ) {
			if ( option.value === '0' || option.value === '' ) {
				return;
			}
			var label = document.createElement( 'label' );
			var input = document.createElement( 'input' );
			input.type = 'radio';
			input.name = 'swatchcraft-' + select.name;
			input.value = option.value;
			input.checked = option.selected;
			input.addEventListener( 'change', function () {
				select.value = option.value;
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
			label.appendChild( input );
			label.appendChild( document.createTextNode( ' ' + option.text ) );
			group.appendChild( label );
		} );
		select.style.display = 'none';
		select.parentNode.insertBefore( group, select.nextSibling );
	}

	function start() {
		document.querySelectorAll( '.wpsc_variation_forms .wpsc_select_variation' ).forEach( enhance );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
