import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import CreditRow from './CreditRow';

let _localIdCounter = 0;
const newLocalId = () => `new-${++_localIdCounter}`;

function rowFromApi(credit) {
  return {
    _localId: String(credit.id),
    id: credit.id,
    artist_id: credit.artist_id,
    artist_title: credit.artist_title,
    role: credit.role,
    role_group: credit.role_group,
    order: credit.order,
    isNew: false,
    isDirty: false,
    _deleted: false,
  };
}

export default function CreditsManager() {
  const postId = useSelect((select) => select('core/editor').getCurrentPostId());
  const [rows, setRows] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [notice, setNotice] = useState(null);

  const loadCredits = useCallback(() => {
    if (!postId) return;
    setIsLoading(true);
    apiFetch({ path: `/chance/v1/production-credits/${postId}` })
      .then((data) => {
        setRows((data.credits || []).map(rowFromApi));
        setIsLoading(false);
      })
      .catch(() => setIsLoading(false));
  }, [postId]);

  useEffect(() => {
    loadCredits();
  }, [loadCredits]);

  const updateRow = useCallback((localId, updates) => {
    setRows((prev) =>
      prev.map((r) => (r._localId === localId ? { ...r, ...updates, isDirty: true } : r))
    );
  }, []);

  const addRow = useCallback(() => {
    const maxOrder = rows.reduce((max, r) => Math.max(max, r.order), -1);
    setRows((prev) => [
      ...prev,
      {
        _localId: newLocalId(),
        id: null,
        artist_id: 0,
        artist_title: '',
        role: '',
        role_group: 'actor',
        order: maxOrder + 1,
        isNew: true,
        isDirty: false,
        _deleted: false,
      },
    ]);
  }, [rows]);

  const removeRow = useCallback((localId) => {
    setRows((prev) => {
      const row = prev.find((r) => r._localId === localId);
      if (!row) return prev;
      if (row.isNew) return prev.filter((r) => r._localId !== localId);
      return prev.map((r) => (r._localId === localId ? { ...r, _deleted: true } : r));
    });
  }, []);

  const moveRow = useCallback((localId, direction) => {
    setRows((prev) => {
      const visible = prev.filter((r) => !r._deleted);
      const idx = visible.findIndex((r) => r._localId === localId);
      const newIdx = idx + direction;
      if (newIdx < 0 || newIdx >= visible.length) return prev;
      const reordered = [...visible];
      [reordered[idx], reordered[newIdx]] = [reordered[newIdx], reordered[idx]];
      const deleted = prev.filter((r) => r._deleted);
      return [...reordered, ...deleted];
    });
  }, []);

  const saveCredits = useCallback(async () => {
    if (!postId) return;

    const incomplete = rows.filter((r) => r.isNew && !r._deleted && !r.artist_id);
    if (incomplete.length > 0) {
      setNotice({
        type: 'error',
        message:
          incomplete.length === 1
            ? 'Select an artist for the new credit row before saving.'
            : `Select an artist for all ${incomplete.length} new credit rows before saving.`,
      });
      return;
    }

    setIsSaving(true);
    setNotice(null);

    try {
      const toDelete = rows.filter((r) => r._deleted && !r.isNew);
      const toCreate = rows.filter((r) => r.isNew && !r._deleted && r.artist_id);
      const toUpdate = rows.filter((r) => !r.isNew && !r._deleted && r.isDirty);
      const keepRows = rows.filter((r) => !r._deleted);

      await Promise.all(
        toDelete.map((r) =>
          apiFetch({ path: `/chance/v1/credit/${r.id}`, method: 'DELETE' })
        )
      );

      const createdIds = {};
      await Promise.all(
        toCreate.map(async (r) => {
          const result = await apiFetch({
            path: `/chance/v1/production-credits/${postId}`,
            method: 'POST',
            data: { artist: r.artist_id, role_group: r.role_group, role: r.role },
          });
          createdIds[r._localId] = result.id;
        })
      );

      await Promise.all(
        toUpdate.map((r) =>
          apiFetch({
            path: `/chance/v1/credit/${r.id}`,
            method: 'PUT',
            data: { artist: r.artist_id, role_group: r.role_group, role: r.role },
          })
        )
      );

      const orderedIds = keepRows
        .map((r) => (r.isNew ? createdIds[r._localId] : r.id))
        .filter(Boolean);

      if (orderedIds.length > 0) {
        await apiFetch({
          path: `/chance/v1/production-credits/${postId}/reorder`,
          method: 'POST',
          data: { order: orderedIds },
        });
      }

      loadCredits();
      setNotice({ type: 'success', message: 'Credits saved.' });
    } catch {
      setNotice({ type: 'error', message: 'Error saving credits. Please try again.' });
    } finally {
      setIsSaving(false);
    }
  }, [postId, rows, loadCredits]);

  if (!postId) {
    return <p style={{ color: '#757575', fontStyle: 'italic' }}>Save the post first to manage credits.</p>;
  }

  if (isLoading) {
    return <Spinner />;
  }

  const visibleRows = rows.filter((r) => !r._deleted);

  return (
    <div className="chance-credits-manager">
      {notice && (
        <Notice status={notice.type} isDismissible onRemove={() => setNotice(null)}>
          {notice.message}
        </Notice>
      )}

      {visibleRows.length === 0 && (
        <p style={{ color: '#757575', fontStyle: 'italic', margin: '0 0 8px' }}>No credits yet.</p>
      )}

      <div className="credits-list">
        {visibleRows.map((row, idx) => (
          <CreditRow
            key={row._localId}
            row={row}
            isFirst={idx === 0}
            isLast={idx === visibleRows.length - 1}
            onChange={(updates) => updateRow(row._localId, updates)}
            onDelete={() => removeRow(row._localId)}
            onMoveUp={() => moveRow(row._localId, -1)}
            onMoveDown={() => moveRow(row._localId, 1)}
          />
        ))}
      </div>

      <div className="credits-actions">
        <Button variant="secondary" onClick={addRow} icon="plus">
          Add Credit
        </Button>
        <Button variant="primary" onClick={saveCredits} isBusy={isSaving} disabled={isSaving}>
          Save Credits
        </Button>
      </div>
    </div>
  );
}
