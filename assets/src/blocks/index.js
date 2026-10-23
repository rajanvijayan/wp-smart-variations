/**
 * Editor side of the Variations Table block: a server-side preview and a product ID setting.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from '../../../blocks/variations-table/block.json';

function Edit( { attributes, setAttributes, context } ) {
	const props = useBlockProps();
	const productId =
		attributes.productId ||
		( context.postType === 'product' ? context.postId : 0 );
	return (
		<div { ...props }>
			<InspectorControls>
				<PanelBody title={ __( 'Product', 'wp-smart-variations' ) }>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="number"
						min={ 0 }
						label={ __( 'Product ID', 'wp-smart-variations' ) }
						help={ __(
							'Leave at 0 to use the current product.',
							'wp-smart-variations'
						) }
						value={ attributes.productId || '' }
						onChange={ ( value ) =>
							setAttributes( {
								productId: parseInt( value, 10 ) || 0,
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			{ productId ? (
				<ServerSideRender
					block={ metadata.name }
					attributes={ { productId } }
				/>
			) : (
				<Placeholder
					icon="editor-table"
					label={ __( 'Variations Table', 'wp-smart-variations' ) }
					instructions={ __(
						'Shows the variations of the current product. Outside a product, enter a product ID in the block settings.',
						'wp-smart-variations'
					) }
				/>
			) }
		</div>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
