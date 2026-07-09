import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const TEAM_EXCLUDE_GROUPS = ['actor', 'producer'];

const VARIATION_OPTIONS = [
  { label: __('All Credits', 'theatrum-blocks'), value: 'all' },
  { label: __('Production Team', 'theatrum-blocks'), value: 'team' },
  { label: __('Production Cast', 'theatrum-blocks'), value: 'cast' },
  { label: __('Production Partners', 'theatrum-blocks'), value: 'partner' },
];

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

function filterByRoleGroup(credits, roleGroup) {
  if (roleGroup === 'all' || !roleGroup) return credits;
  if (roleGroup === 'team') return credits.filter((c) => !TEAM_EXCLUDE_GROUPS.includes(c.role_group));
  if (roleGroup === 'cast') return credits.filter((c) => c.role_group === 'actor');
  if (roleGroup === 'partner') return credits.filter((c) => c.role_group === 'producer');
  return credits;
}

export default function Edit({ attributes, setAttributes }) {
  const { roleGroup = 'all', justifyContent = 'flex-start', alignItems = 'flex-start', itemWidth = '160px' } = attributes;
  const blockProps = useBlockProps();
  const [credits, setCredits] = useState([]);
  const [isLoading, setIsLoading] = useState(false);

  const postId = useSelect((select) => select('core/editor').getCurrentPostId());

  useEffect(() => {
    if (!postId) {
      setCredits([]);
      return;
    }

    setIsLoading(true);

    apiFetch({ path: `/chance/v1/production-credits/${postId}` })
      .then((data) => {
        setCredits(filterByRoleGroup(data.credits || [], roleGroup));
        setIsLoading(false);
      })
      .catch((error) => {
        console.error('Error fetching production credits:', error);
        setCredits([]);
        setIsLoading(false);
      });
  }, [postId, roleGroup]);

  const decodeHtmlEntities = (text) => {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
  };

  const listItems = credits.map((credit) => {
    const artistTitle = decodeHtmlEntities(credit.artist_title);
    const role = credit.role ? decodeHtmlEntities(credit.role) : '';

    return (
      <li key={credit.id} className="credit">
        <div className="credit-link">
          {credit.artist_thumbnail && (
            <img src={credit.artist_thumbnail} alt={artistTitle} className="artist-headshot" />
          )}
          <p className="artist">{artistTitle}</p>
          {role && (
            <p className="role">{role}</p>
          )}
        </div>
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
        <div style={{ padding: '16px' }}>
          <SelectControl
            label={__('Display', 'theatrum-blocks')}
            value={roleGroup}
            options={VARIATION_OPTIONS}
            onChange={(value) => setAttributes({ roleGroup: value })}
          />
        </div>
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
            help={__('Any CSS length, e.g. 160px, 10rem, 20%', 'theatrum-blocks')}
            value={itemWidth}
            onChange={(value) => setAttributes({ itemWidth: value })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        {isLoading ? (
          <Spinner />
        ) : (
          <ul className="production-credits-ul" style={flexStyle}>
            {listItems.length > 0 ? listItems : <li>No credits found</li>}
          </ul>
        )}
      </div>
    </>
  );
}

