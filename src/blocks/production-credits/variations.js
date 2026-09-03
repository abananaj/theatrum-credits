import { __ } from '@wordpress/i18n';

/**
 * Block variations for Production Credits — team = role_group not in [actor, producer] (exclusion-based; catches creative_team + any legacy values), cast = actor, partner = producer.
 */
const variations = [
	{
		name: 'production-team',
		title: __('Production Team', 'theatrum-credits'),
		description: __(
			'Display the production team: directors, designers, choreographers, and crew.',
			'theatrum-credits'
		),
		icon: 'groups',
		attributes: { roleGroup: 'team' },
		scope: ['inserter', 'transform'],
	},
	{
		name: 'production-cast',
		title: __('Production Cast', 'theatrum-credits'),
		description: __(
			'Display the cast members (actors).',
			'theatrum-credits'
		),
		icon: 'admin-users',
		attributes: { roleGroup: 'cast' },
		scope: ['inserter', 'transform'],
	},
	{
		name: 'production-partners',
		title: __('Production Partners', 'theatrum-credits'),
		description: __(
			'Display producers and partner credits.',
			'theatrum-credits'
		),
		icon: 'businessman',
		attributes: { roleGroup: 'partner' },
		scope: ['inserter', 'transform'],
	},
];

export default variations;
