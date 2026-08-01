/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';

/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { PanelBody, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const JUSTIFY_OPTIONS = [
	{ label: __('Left', 'theatrum-blocks'), value: 'flex-start' },
	{ label: __('Center', 'theatrum-blocks'), value: 'center' },
	{ label: __('Right', 'theatrum-blocks'), value: 'flex-end' },
	{ label: __('Space between', 'theatrum-blocks'), value: 'space-between' },
	{ label: __('Space around', 'theatrum-blocks'), value: 'space-around' },
	{ label: __('Space evenly', 'theatrum-blocks'), value: 'space-evenly' },
];

const ALIGN_OPTIONS = [
	{ label: __('Top', 'theatrum-blocks'), value: 'flex-start' },
	{ label: __('Center', 'theatrum-blocks'), value: 'center' },
	{ label: __('Bottom', 'theatrum-blocks'), value: 'flex-end' },
	{ label: __('Stretch', 'theatrum-blocks'), value: 'stretch' },
];

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const { justifyContent = 'flex-start', alignItems = 'flex-start', itemWidth = '240px' } = attributes;
	const blockProps = useBlockProps();
	const [credits, setCredits] = useState([]);
	const [isLoading, setIsLoading] = useState(false);

	// Get current post ID
	const postId = useSelect((select) => select('core/editor').getCurrentPostId());

	// Fetch credits when post ID changes
	useEffect(() => {
		if (!postId) {
			setCredits([]);
			return;
		}

		setIsLoading(true);

		// Fetch credits using REST endpoint
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

	// Helper function to decode HTML entities
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
				<PanelBody title={__('Layout', 'theatrum-blocks')}>
					<SelectControl
						label={__('Justify content', 'theatrum-blocks')}
						value={justifyContent}
						options={JUSTIFY_OPTIONS}
						onChange={(value) => setAttributes({ justifyContent: value })}
					/>
					<SelectControl
						label={__('Align items', 'theatrum-blocks')}
						value={alignItems}
						options={ALIGN_OPTIONS}
						onChange={(value) => setAttributes({ alignItems: value })}
					/>
					<TextControl
						label={__('Item width', 'theatrum-blocks')}
						help={__('Any CSS length, e.g. 240px, 10rem, 20%', 'theatrum-blocks')}
						value={itemWidth}
						onChange={(value) => setAttributes({ itemWidth: value })}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				{isLoading ? (
					<Spinner />
				) : (
					<ul className="artist-credits-ul" style={flexStyle}>
						{listItems.length > 0 ? listItems : <li>No credits found</li>}
					</ul>
				)}
			</div>
		</>
	);
}
