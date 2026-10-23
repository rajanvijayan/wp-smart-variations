/**
 * Live preview of the swatches with the current settings, using the front-end markup and styles.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const COLOURS = [
	{
		value: 'green',
		name: __( 'Green', 'wp-smart-variations' ),
		color: '#3f8a57',
	},
	{
		value: 'navy',
		name: __( 'Navy and white', 'wp-smart-variations' ),
		color: '#1f2a56',
		color2: '#f4f4f4',
	},
	{
		value: 'red',
		name: __( 'Red', 'wp-smart-variations' ),
		color: '#d64040',
	},
	{
		value: 'sand',
		name: __( 'Sand', 'wp-smart-variations' ),
		color: '#dcc79e',
	},
];

const SIZES = [ 'S', 'M', 'L', 'XL' ];

// In the preview, XL is not made in red and L is out of stock in navy.
function state( colour, size ) {
	if ( colour === 'red' && size === 'XL' ) {
		return 'unavailable';
	}
	if ( colour === 'navy' && size === 'L' ) {
		return 'out-of-stock';
	}
	return '';
}

function Group( { settings, type, label, options, selected, onChoose } ) {
	const classes = [
		'swatchcraft',
		`swatchcraft--${ type }`,
		`swatchcraft--${ settings.shape }`,
		`swatchcraft--${ settings.size }`,
		'swatchcraft--single',
		settings.tooltips ? '' : 'swatchcraft--no-tooltips',
	].join( ' ' );
	return (
		<div className={ classes } role="radiogroup" aria-label={ label }>
			{ options.map( ( option ) => {
				const status = option.status;
				const hidden =
					( status === 'unavailable' &&
						settings.unavailable === 'hide' ) ||
					( status === 'out-of-stock' &&
						settings.out_of_stock === 'hide' );
				const crossed =
					status === 'unavailable' ||
					( status === 'out-of-stock' &&
						settings.out_of_stock !== 'clickable' );
				let tooltip = option.name;
				if ( status === 'unavailable' ) {
					tooltip += ` · ${ __(
						'Not available with your other choices',
						'wp-smart-variations'
					) }`;
				} else if ( status === 'out-of-stock' ) {
					tooltip += ` · ${ __(
						'Out of stock',
						'wp-smart-variations'
					) }`;
				} else if ( option.extra ) {
					tooltip += ` · ${ option.extra }`;
				}
				const isSelected = selected === option.value;
				return (
					<button
						key={ option.value }
						type="button"
						role="radio"
						aria-checked={ isSelected }
						hidden={ hidden && ! isSelected }
						className={ [
							'swatchcraft__option',
							isSelected ? 'is-selected' : '',
							crossed && status === 'unavailable'
								? 'is-unavailable'
								: '',
							crossed && status === 'out-of-stock'
								? 'is-out-of-stock'
								: '',
						].join( ' ' ) }
						data-tooltip={ tooltip }
						style={
							option.color
								? {
										'--swatchcraft-color': option.color,
										'--swatchcraft-color-2':
											option.color2 || option.color,
								  }
								: undefined
						}
						onClick={ () =>
							onChoose( isSelected ? '' : option.value )
						}
					>
						{ type === 'color' ? (
							<>
								<span
									className="swatchcraft__swatch"
									aria-hidden="true"
								/>
								<span className="screen-reader-text">
									{ option.name }
								</span>
							</>
						) : (
							<span className="swatchcraft__label">
								{ option.name }
							</span>
						) }
					</button>
				);
			} ) }
		</div>
	);
}

export default function Preview( { settings } ) {
	const [ colour, setColour ] = useState( 'navy' );
	const [ size, setSize ] = useState( '' );
	const colourName = COLOURS.find( ( c ) => c.value === colour )?.name;
	const sizeType = settings.auto_buttons ? 'button' : 'dropdown';

	return (
		<div className="swatchcraft-settings__preview" aria-live="polite">
			<table className="variations">
				<tbody>
					<tr>
						<th scope="row">
							{ __( 'Colour', 'wp-smart-variations' ) }
							{ settings.show_selected && colourName && (
								<span className="swatchcraft-selected">
									: { colourName }
								</span>
							) }
						</th>
						<td>
							<Group
								settings={ settings }
								type="color"
								label={ __( 'Colour', 'wp-smart-variations' ) }
								options={ COLOURS.map( ( c ) => ( {
									...c,
									status: size ? state( c.value, size ) : '',
								} ) ) }
								selected={ colour }
								onChoose={ setColour }
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							{ __( 'Size', 'wp-smart-variations' ) }
							{ settings.show_selected && size && (
								<span className="swatchcraft-selected">
									: { size }
								</span>
							) }
						</th>
						<td>
							{ sizeType === 'dropdown' ? (
								<select
									value={ size }
									onChange={ ( event ) =>
										setSize( event.target.value )
									}
								>
									<option value="">
										{ __(
											'Choose an option',
											'wp-smart-variations'
										) }
									</option>
									{ SIZES.map( ( s ) => (
										<option key={ s } value={ s }>
											{ s }
										</option>
									) ) }
								</select>
							) : (
								<Group
									settings={ settings }
									type="button"
									label={ __(
										'Size',
										'wp-smart-variations'
									) }
									options={ SIZES.map( ( s ) => ( {
										value: s,
										name: s,
										status: colour
											? state( colour, s )
											: '',
										extra: settings.tooltip_price
											? '$28.00'
											: '',
									} ) ) }
									selected={ size }
									onChoose={ setSize }
								/>
							) }
							{ settings.clear_link && ( colour || size ) && (
								<button
									type="button"
									className="button-link swatchcraft-settings__clear"
									onClick={ () => {
										setColour( '' );
										setSize( '' );
									} }
								>
									{ __( 'Clear', 'wp-smart-variations' ) }
								</button>
							) }
						</td>
					</tr>
				</tbody>
			</table>
			<p className="description">
				{ __(
					'Try it: red is not made in XL, and navy is out of stock in L.',
					'wp-smart-variations'
				) }
			</p>
		</div>
	);
}
