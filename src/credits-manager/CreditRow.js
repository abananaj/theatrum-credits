import { useState } from '@wordpress/element';
import { Button, TextControl, ComboboxControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { decodeEntities } from '@wordpress/html-entities';

export default function CreditRow({ row, onChange, onDelete, onMoveUp, onMoveDown, canMoveUp, canMoveDown }) {
  const [artistOptions, setArtistOptions] = useState(
    row.artist_id
      ? [{ value: row.artist_id, label: decodeEntities(row.artist_title) || String(row.artist_id) }]
      : []
  );

  function searchArtists(filterValue) {
    if (filterValue.length < 2) return;
    const subtype = row.role_group === 'producer' ? 'supporter' : 'artist';
    apiFetch({
      path: `/wp/v2/search?search=${encodeURIComponent(filterValue)}&type=post&subtype=${subtype}&per_page=20&_fields=id,title`,
    })
      .then((results) => {
        setArtistOptions(results.map((r) => ({ value: r.id, label: decodeEntities(r.title) })));
      })
      .catch(() => {});
  }

  return (
    <div className="credit-row-wrapper" data-local-id={row._localId}>
      <div className="credit-row">
        <div className="credit-row-body">
          <div className="credit-row-fields">
            <ComboboxControl
              label={row.role_group === 'producer' ? 'Supporter' : 'Artist'}
              value={row.artist_id || null}
              onChange={(value) => {
                const option = artistOptions.find((o) => o.value === value);
                onChange({ artist_id: value, artist_title: option?.label || '' });
              }}
              options={artistOptions}
              onFilterValueChange={searchArtists}
              allowReset={false}
            />
            <TextControl
              label="Role"
              value={row.role}
              onChange={(value) => onChange({ role: value })}
              placeholder="e.g. Hamlet"
            />
          </div>
        </div>
        <span className="credit-row-drag-handle" title="Drag to reorder" aria-hidden="true">
          <svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor">
            <circle cx="2" cy="2" r="1.5" />
            <circle cx="8" cy="2" r="1.5" />
            <circle cx="2" cy="8" r="1.5" />
            <circle cx="8" cy="8" r="1.5" />
            <circle cx="2" cy="14" r="1.5" />
            <circle cx="8" cy="14" r="1.5" />
          </svg>
        </span>
      </div>
      <span className="credit-row-move">
        <Button icon="arrow-up-alt2" label="Move up" showTooltip size="small" onClick={onMoveUp} disabled={!canMoveUp} />
        <Button icon="arrow-down-alt2" label="Move down" showTooltip size="small" onClick={onMoveDown} disabled={!canMoveDown} />
      </span>
      <Button
        className="credit-row-delete"
        icon="trash"
        label="Remove credit"
        onClick={onDelete}
        isDestructive
        size="small"
      />
    </div>
  );
}
