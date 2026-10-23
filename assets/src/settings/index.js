/**
 * SwatchCraft settings page (WooCommerce → SwatchCraft).
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	ExternalLink,
	Notice,
	Spinner,
	TabPanel,
} from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { NumberInput, Select, Toggle } from './controls';
import Preview from './preview';
import './index.scss';

const config = window.swatchcraftSettings || { attributes: [], types: {} };
const PATH = '/swatchcraft/v1/settings';

const TABS = [
	{ name: 'swatches', title: __( 'Swatches', 'wp-smart-variations' ) },
	{
		name: 'availability',
		title: __( 'Availability', 'wp-smart-variations' ),
	},
	{ name: 'cards', title: __( 'Product cards', 'wp-smart-variations' ) },
	{ name: 'extras', title: __( 'Gallery and table', 'wp-smart-variations' ) },
	{ name: 'attributes', title: __( 'Attributes', 'wp-smart-variations' ) },
];

function initialTab() {
	const hash = window.location.hash.replace( '#', '' );
	return TABS.some( ( tab ) => tab.name === hash ) ? hash : 'swatches';
}

function Section( { title, description, wide, children } ) {
	return (
		<section className="swatchcraft-settings__section">
			{ title && <h2>{ title }</h2> }
			{ description && <p className="description">{ description }</p> }
			{ wide ? (
				children
			) : (
				<div className="swatchcraft-settings__stack">{ children }</div>
			) }
		</section>
	);
}

function Swatches( { value, set } ) {
	return (
		<div className="swatchcraft-settings__columns">
			<div>
				<Section title={ __( 'Look', 'wp-smart-variations' ) }>
					<Select
						label={ __( 'Shape', 'wp-smart-variations' ) }
						value={ value.shape }
						options={ [
							{
								value: 'rounded',
								label: __( 'Rounded', 'wp-smart-variations' ),
							},
							{
								value: 'round',
								label: __( 'Round', 'wp-smart-variations' ),
							},
							{
								value: 'square',
								label: __( 'Square', 'wp-smart-variations' ),
							},
						] }
						onChange={ ( shape ) => set( { shape } ) }
					/>
					<Select
						label={ __( 'Size', 'wp-smart-variations' ) }
						value={ value.size }
						options={ [
							{
								value: 'small',
								label: __( 'Small', 'wp-smart-variations' ),
							},
							{
								value: 'medium',
								label: __( 'Medium', 'wp-smart-variations' ),
							},
							{
								value: 'large',
								label: __( 'Large', 'wp-smart-variations' ),
							},
						] }
						onChange={ ( size ) => set( { size } ) }
					/>
					<Toggle
						label={ __(
							'Buttons for attributes without a swatch type',
							'wp-smart-variations'
						) }
						help={ __(
							'Off: those attributes keep WooCommerce’s dropdown. Set a type per attribute under Products → Attributes.',
							'wp-smart-variations'
						) }
						checked={ value.auto_buttons }
						onChange={ ( auto ) => set( { auto_buttons: auto } ) }
					/>
					<Toggle
						label={ __(
							'Show the chosen value next to the attribute name',
							'wp-smart-variations'
						) }
						checked={ value.show_selected }
						onChange={ ( show ) => set( { show_selected: show } ) }
					/>
					<Toggle
						label={ __(
							'Show the “Clear” link',
							'wp-smart-variations'
						) }
						help={ __(
							'Clicking a chosen swatch again also clears it.',
							'wp-smart-variations'
						) }
						checked={ value.clear_link }
						onChange={ ( show ) => set( { clear_link: show } ) }
					/>
				</Section>
				<Section title={ __( 'Tooltips', 'wp-smart-variations' ) }>
					<Toggle
						label={ __(
							'Show the value name on hover and focus',
							'wp-smart-variations'
						) }
						checked={ value.tooltips }
						onChange={ ( on ) => set( { tooltips: on } ) }
					/>
					<Toggle
						label={ __( 'Add the price', 'wp-smart-variations' ) }
						help={ __(
							'When every variation with that value costs the same.',
							'wp-smart-variations'
						) }
						checked={ value.tooltip_price }
						disabled={ ! value.tooltips }
						onChange={ ( on ) => set( { tooltip_price: on } ) }
					/>
					<Toggle
						label={ __( 'Add the stock', 'wp-smart-variations' ) }
						help={ __(
							'“Out of stock”, or the number left when stock is managed.',
							'wp-smart-variations'
						) }
						checked={ value.tooltip_stock }
						disabled={ ! value.tooltips }
						onChange={ ( on ) => set( { tooltip_stock: on } ) }
					/>
				</Section>
			</div>
			<Section title={ __( 'Preview', 'wp-smart-variations' ) }>
				<Preview settings={ value } />
			</Section>
		</div>
	);
}

function Availability( { value, set } ) {
	return (
		<div className="swatchcraft-settings__columns">
			<Section
				title={ __(
					'Unavailable and out of stock',
					'wp-smart-variations'
				) }
				description={ __(
					'A value is unavailable when no variation has it together with the shopper’s other choices.',
					'wp-smart-variations'
				) }
			>
				<Select
					label={ __( 'Unavailable values', 'wp-smart-variations' ) }
					value={ value.unavailable }
					options={ [
						{
							value: 'cross',
							label: __(
								'Cross out (a click starts the choice again)',
								'wp-smart-variations'
							),
						},
						{
							value: 'hide',
							label: __( 'Hide', 'wp-smart-variations' ),
						},
					] }
					onChange={ ( unavailable ) => set( { unavailable } ) }
				/>
				<Select
					label={ __( 'Out-of-stock values', 'wp-smart-variations' ) }
					value={ value.out_of_stock }
					options={ [
						{
							value: 'cross',
							label: __(
								'Cross out and block',
								'wp-smart-variations'
							),
						},
						{
							value: 'clickable',
							label: __(
								'Clickable (for backorders or stock notices)',
								'wp-smart-variations'
							),
						},
						{
							value: 'hide',
							label: __( 'Hide', 'wp-smart-variations' ),
						},
					] }
					onChange={ ( outOfStock ) =>
						set( { out_of_stock: outOfStock } )
					}
				/>
				<NumberInput
					label={ __( 'Large products', 'wp-smart-variations' ) }
					help={ __(
						'Products with more variations than this load them one at a time from the server: out-of-stock values are then only known once chosen. 10–500.',
						'wp-smart-variations'
					) }
					min={ 10 }
					max={ 500 }
					value={ value.ajax_threshold }
					onChange={ ( threshold ) =>
						set( { ajax_threshold: threshold } )
					}
				/>
			</Section>
			<Section title={ __( 'Preview', 'wp-smart-variations' ) }>
				<Preview settings={ value } />
			</Section>
		</div>
	);
}

function Cards( { value, set } ) {
	const attributes = [
		{
			value: '',
			label: __(
				'Automatic (first colour or image attribute)',
				'wp-smart-variations'
			),
		},
		...config.attributes.map( ( attribute ) => ( {
			value: attribute.slug,
			label: attribute.label,
		} ) ),
	];
	return (
		<Section
			title={ __( 'Swatches on product cards', 'wp-smart-variations' ) }
			description={ __(
				'On shop, category and related-product cards (classic templates and the Product Collection block). Choosing a swatch shows that variation’s image and links to the product with the value chosen.',
				'wp-smart-variations'
			) }
		>
			<Toggle
				label={ __(
					'Show swatches on product cards',
					'wp-smart-variations'
				) }
				checked={ value.loop }
				onChange={ ( loop ) => set( { loop } ) }
			/>
			<Select
				label={ __( 'Attribute', 'wp-smart-variations' ) }
				help={ __(
					'Attributes with “Show swatches on product cards” ticked come first. A product can switch its cards off in its Swatches tab.',
					'wp-smart-variations'
				) }
				value={ value.loop_attribute }
				options={ attributes }
				disabled={ ! value.loop }
				onChange={ ( attribute ) =>
					set( { loop_attribute: attribute } )
				}
			/>
			<NumberInput
				label={ __( 'Swatches shown', 'wp-smart-variations' ) }
				help={ __(
					'Others are summed up as “+3 more”. 1–30.',
					'wp-smart-variations'
				) }
				min={ 1 }
				max={ 30 }
				value={ value.loop_limit }
				disabled={ ! value.loop }
				onChange={ ( limit ) => set( { loop_limit: limit } ) }
			/>
			<Select
				label={ __( 'Position', 'wp-smart-variations' ) }
				value={ value.loop_position }
				options={ [
					{
						value: 'before_cart',
						label: __( 'Above the button', 'wp-smart-variations' ),
					},
					{
						value: 'after_cart',
						label: __( 'Below the button', 'wp-smart-variations' ),
					},
				] }
				disabled={ ! value.loop }
				onChange={ ( position ) => set( { loop_position: position } ) }
			/>
		</Section>
	);
}

function Extras( { value, set } ) {
	return (
		<>
			<Section
				title={ __( 'Variation galleries', 'wp-smart-variations' ) }
				description={ __(
					'Add more images to each variation (Product data → Variations). Choosing the variation shows them in the product gallery.',
					'wp-smart-variations'
				) }
			>
				<Toggle
					label={ __(
						'Show variation galleries',
						'wp-smart-variations'
					) }
					checked={ value.gallery }
					onChange={ ( gallery ) => set( { gallery } ) }
				/>
			</Section>
			<Section
				title={ __( 'Variations table', 'wp-smart-variations' ) }
				description={ __(
					'Every variation with its image, price, stock and a quantity, added to the cart in one go. Products can show or hide it in their Swatches tab; the “Variations Table” block and the [swatchcraft_table id="123"] shortcode place it anywhere.',
					'wp-smart-variations'
				) }
			>
				<Select
					label={ __( 'On product pages', 'wp-smart-variations' ) }
					value={ value.table }
					options={ [
						{
							value: 'off',
							label: __(
								'Only on products that turn it on',
								'wp-smart-variations'
							),
						},
						{
							value: 'after_cart',
							label: __(
								'Below the add-to-cart form',
								'wp-smart-variations'
							),
						},
						{
							value: 'replace_cart',
							label: __(
								'Instead of the add-to-cart form',
								'wp-smart-variations'
							),
						},
					] }
					onChange={ ( table ) => set( { table } ) }
				/>
			</Section>
		</>
	);
}

function Attributes() {
	return (
		<Section
			wide
			title={ __( 'Attributes', 'wp-smart-variations' ) }
			description={ __(
				'Choose the swatch type on each attribute, and colours or images on its terms. Custom attributes of a single product are set in that product’s Swatches tab.',
				'wp-smart-variations'
			) }
		>
			{ config.attributes.length ? (
				<table className="widefat striped swatchcraft-settings__attributes">
					<thead>
						<tr>
							<th>
								{ __( 'Attribute', 'wp-smart-variations' ) }
							</th>
							<th>
								{ __( 'Swatch type', 'wp-smart-variations' ) }
							</th>
							<th>
								{ __( 'Product cards', 'wp-smart-variations' ) }
							</th>
							<th>
								<span className="screen-reader-text">
									{ __( 'Actions', 'wp-smart-variations' ) }
								</span>
							</th>
						</tr>
					</thead>
					<tbody>
						{ config.attributes.map( ( attribute ) => (
							<tr key={ attribute.slug }>
								<td>
									<strong>{ attribute.label }</strong>
								</td>
								<td>
									{ attribute.type
										? config.types[ attribute.type ] ||
										  attribute.type
										: __(
												'Automatic (button or dropdown)',
												'wp-smart-variations'
										  ) }
								</td>
								<td>
									{ attribute.loop
										? __( 'Yes', 'wp-smart-variations' )
										: '—' }
								</td>
								<td className="swatchcraft-settings__actions">
									<a href={ attribute.editUrl }>
										{ __(
											'Edit type',
											'wp-smart-variations'
										) }
									</a>
									<a href={ attribute.termsUrl }>
										{ sprintf(
											/* translators: %s: attribute name. */
											__(
												'Colours and images of %s',
												'wp-smart-variations'
											),
											attribute.label
										) }
									</a>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) : (
				<p>
					{ __(
						'Your store has no global attributes yet.',
						'wp-smart-variations'
					) }{ ' ' }
					<a href={ config.newUrl }>
						{ __( 'Add one', 'wp-smart-variations' ) }
					</a>
				</p>
			) }
		</Section>
	);
}

function App() {
	const [ settings, setSettings ] = useState( null );
	const [ saved, setSaved ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: PATH } )
			.then( ( data ) => {
				setSettings( data );
				setSaved( JSON.stringify( data ) );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error.message } )
			);
	}, [] );

	const dirty = settings && JSON.stringify( settings ) !== saved;

	useEffect( () => {
		const warn = ( event ) => {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	if ( ! settings ) {
		return notice ? (
			<Notice status="error" isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const props = {
		value: settings,
		set: ( changes ) =>
			setSettings( ( current ) => ( { ...current, ...changes } ) ),
	};

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: PATH, method: 'POST', data: settings } )
			.then( ( data ) => {
				setSettings( data );
				setSaved( JSON.stringify( data ) );
				setNotice( {
					status: 'success',
					text: __( 'Settings saved.', 'wp-smart-variations' ),
				} );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error.message } )
			)
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="swatchcraft-settings">
			<div className="swatchcraft-settings__header">
				<h1>{ __( 'SwatchCraft', 'wp-smart-variations' ) }</h1>
				<span className="swatchcraft-settings__version">
					{ config.version }
				</span>
				<ExternalLink href="https://wordpress.org/support/plugin/wp-smart-variations/">
					{ __( 'Support', 'wp-smart-variations' ) }
				</ExternalLink>
			</div>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			<TabPanel
				className="swatchcraft-settings__tabs"
				tabs={ TABS }
				initialTabName={ initialTab() }
				onSelect={ ( name ) =>
					window.history.replaceState( null, '', '#' + name )
				}
			>
				{ ( tab ) => (
					<div className="swatchcraft-settings__panel">
						{ tab.name === 'swatches' && <Swatches { ...props } /> }
						{ tab.name === 'availability' && (
							<Availability { ...props } />
						) }
						{ tab.name === 'cards' && <Cards { ...props } /> }
						{ tab.name === 'extras' && <Extras { ...props } /> }
						{ tab.name === 'attributes' && <Attributes /> }
					</div>
				) }
			</TabPanel>
			<div className="swatchcraft-settings__footer">
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving || ! dirty }
					onClick={ save }
				>
					{ __( 'Save settings', 'wp-smart-variations' ) }
				</Button>
				{ dirty && (
					<span className="swatchcraft-settings__unsaved">
						{ __( 'Unsaved changes', 'wp-smart-variations' ) }
					</span>
				) }
			</div>
		</div>
	);
}

const root = document.getElementById( 'swatchcraft-settings' );
if ( root ) {
	// The front-end styles show swatches only once scripts run.
	document.documentElement.classList.add( 'swatchcraft-js' );
	createRoot( root ).render( <App /> );
}
