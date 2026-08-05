import { __ } from '@wordpress/i18n';

/**
 * Block variations for Production Credits.
 *
 * Default (no variation): all credits for the production.
 * team:    credits with role_group != actor and != producer (creative_team, plus any legacy values)
 * cast:    credits with role_group = actor
 * partner: credits with role_group = producer
 */
const variations = [
  {
    name: 'production-team',
    title: __('Production Team', 'theatrum-blocks'),
    description: __('Display the production team: directors, designers, choreographers, and crew.', 'theatrum-blocks'),
    icon: 'groups',
    attributes: { roleGroup: 'team' },
    scope: ['inserter', 'transform'],
  },
  {
    name: 'production-cast',
    title: __('Production Cast', 'theatrum-blocks'),
    description: __('Display the cast members (actors).', 'theatrum-blocks'),
    icon: 'admin-users',
    attributes: { roleGroup: 'cast' },
    scope: ['inserter', 'transform'],
  },
  {
    name: 'production-partners',
    title: __('Production Partners', 'theatrum-blocks'),
    description: __('Display producers and partner credits.', 'theatrum-blocks'),
    icon: 'businessman',
    attributes: { roleGroup: 'partner' },
    scope: ['inserter', 'transform'],
  },
];

export default variations;
