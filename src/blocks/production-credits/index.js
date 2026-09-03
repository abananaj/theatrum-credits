import { registerBlockType } from '@wordpress/blocks';

import './style.scss';
import './editor.scss';

import Edit from './edit';
import metadata from './block.json';
import variations from './variations';

registerBlockType(metadata.name, {
	edit: Edit,
	save: () => null,
	variations,
});
