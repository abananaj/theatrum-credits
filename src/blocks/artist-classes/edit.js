import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, context }) {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<ServerSideRender
				block="theatrum/artist-classes"
				attributes={attributes}
				urlQueryArgs={
					context.postId ? { post_id: context.postId } : {}
				}
				EmptyResponsePlaceholder={() => (
					<p>
						{__(
							'Classes taught by this artist appear here.',
							'theatrum-credits'
						)}
					</p>
				)}
			/>
		</div>
	);
}
