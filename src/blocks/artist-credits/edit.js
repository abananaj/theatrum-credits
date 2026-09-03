/**
 * useBlockProps marks the block wrapper element with the needed props (e.g. class name).
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';

/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import {
	PanelBody,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const JUSTIFY_OPTIONS = [
	{ label: __('Left', 'theatrum-credits'), value: 'flex-start' },
	{ label: __('Center', 'theatrum-credits'), value: 'center' },
	{ label: __('Right', 'theatrum-credits'), value: 'flex-end' },
	{ label: __('Space between', 'theatrum-credits'), value: 'space-between' },
	{ label: __('Space around', 'theatrum-credits'), value: 'space-around' },
	{ label: __('Space evenly', 'theatrum-credits'), value: 'space-evenly' },
];

const ALIGN_OPTIONS = [
	{ label: __('Top', 'theatrum-credits'), value: 'flex-start' },
	{ label: __('Center', 'theatrum-credits'), value: 'center' },
	{ label: __('Bottom', 'theatrum-credits'), value: 'flex-end' },
	{ label: __('Stretch', 'theatrum-credits'), value: 'stretch' },
];

/**
 * Edit function: renders the block's structure in the editor.
 *
 * @param  root0
 * @param  root0.attributes
 * @param  root0.setAttributes
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const {
		justifyContent = 'flex-start',
		alignItems = 'flex-start',
		itemWidth = '240px',
	} = attributes;
	const blockProps = useBlockProps();
	const [credits, setCredits] = useState([]);
	const [isLoading, setIsLoading] = useState(false);

	const postId = useSelect((select) =>
		select('core/editor').getCurrentPostId()
	);

	useEffect(() => {
		if (!postId) {
			setCredits([]);
			return;
		}

		setIsLoading(true);

		apiFetch({ path: `/theatrum/v1/artist-credits/${postId}` })
			.then((data) => {
				setCredits(data.credits || []);
				setIsLoading(false);
			})
			.catch((error) => {
				console.error('Error fetching artist credits:', error);
				setCredits([]);
				setIsLoading(false);
			});
	}, [postId]);

	const decodeHtmlEntities = (text) => {
		const textarea = document.createElement('textarea');
		textarea.innerHTML = text;
		return textarea.value;
	};

	const listItems = credits.map((credit) => {
		const productionTitle = decodeHtmlEntities(credit.production_title);
		const role = credit.role ? decodeHtmlEntities(credit.role) : '';
		const date = credit.date ? decodeHtmlEntities(credit.date) : '';

		return (
			<li key={credit.id} className="credit">
				<span className="production">{productionTitle}</span>
				{role && (
					<>
						, <span className="role">{role}</span>
					</>
				)}
				{date && <span className="date">{date}</span>}
			</li>
		);
	});

	const flexStyle = {
		justifyContent,
		alignItems,
		'--credit-width': itemWidth,
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Layout', 'theatrum-credits')}>
					<SelectControl
						label={__('Justify content', 'theatrum-credits')}
						value={justifyContent}
						options={JUSTIFY_OPTIONS}
						onChange={(value) =>
							setAttributes({ justifyContent: value })
						}
					/>
					<SelectControl
						label={__('Align items', 'theatrum-credits')}
						value={alignItems}
						options={ALIGN_OPTIONS}
						onChange={(value) =>
							setAttributes({ alignItems: value })
						}
					/>
					<TextControl
						label={__('Item width', 'theatrum-credits')}
						help={__(
							'Any CSS length, e.g. 240px, 10rem, 20%',
							'theatrum-credits'
						)}
						value={itemWidth}
						onChange={(value) =>
							setAttributes({ itemWidth: value })
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				{isLoading ? (
					<Spinner />
				) : (
					<ul className="artist-credits-ul" style={flexStyle}>
						{listItems.length > 0 ? (
							listItems
						) : (
							<li>No credits found</li>
						)}
					</ul>
				)}
			</div>
		</>
	);
}
