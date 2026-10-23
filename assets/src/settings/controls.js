/**
 * Form controls with the current WordPress sizing defaults.
 */
import {
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

export function NumberInput( { value, onChange, ...props } ) {
	return (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			type="number"
			{ ...props }
			value={ String( value ?? '' ) }
			onChange={ ( next ) => onChange( parseInt( next, 10 ) || 0 ) }
		/>
	);
}

export function Select( props ) {
	return (
		<SelectControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			{ ...props }
		/>
	);
}

export function Toggle( { checked, ...props } ) {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			{ ...props }
			checked={ !! checked }
		/>
	);
}
