import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { Button, Notice, Spinner, TabPanel } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import CreditRow from './CreditRow';

let _localIdCounter = 0;
const newLocalId = () => `new-${++_localIdCounter}`;

const TABS = [
	{ name: 'cast', title: 'Cast' },
	{ name: 'creative', title: 'Creative Team' },
	{ name: 'producers', title: 'Producers' },
];

const DEFAULT_ROLE_GROUP_BY_TAB = {
	cast: 'actor',
	producers: 'producer',
	creative: 'creative_team',
};

function rowMatchesTab(row, tabName) {
	if (tabName === 'cast') {
		return row.role_group === 'actor';
	}
	if (tabName === 'producers') {
		return row.role_group === 'producer';
	}
	// Catches legacy per-role groups (playwright, director, etc.) and current 'creative_team' — anything that isn't Cast or Producers.
	return row.role_group !== 'actor' && row.role_group !== 'producer';
}

// Role Group is implied by tab, not user-edited; normalizes legacy per-role values (not in THEATRUM_CREDITS_VALID_ROLE_GROUPS) to 'creative_team' so old rows pass validation.
function canonicalRoleGroupForRow(row) {
	if (row.role_group === 'actor' || row.role_group === 'producer') {
		return row.role_group;
	}
	return 'creative_team';
}

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
	const postId = useSelect((select) =>
		select('core/editor').getCurrentPostId()
	);
	const [rows, setRows] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [isSaving, setIsSaving] = useState(false);
	const [notice, setNotice] = useState(null);
	const [activeTab, setActiveTab] = useState('cast');
	const listRef = useRef(null);
	const pendingOrderRef = useRef(null);
	const [announcement, setAnnouncement] = useState('');
	// Which row (by local id) should receive focus after the next render, and which control inside it.
	const focusRequestRef = useRef(null);

	const loadCredits = useCallback(() => {
		if (!postId) {
			return;
		}
		setIsLoading(true);
		apiFetch({ path: `/theatrum/v1/production-credits/${postId}` })
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
			prev.map((r) =>
				r._localId === localId ? { ...r, ...updates, isDirty: true } : r
			)
		);
	}, []);

	const addRow = useCallback(() => {
		const maxOrder = rows.reduce((max, r) => Math.max(max, r.order), -1);
		const localId = newLocalId();
		focusRequestRef.current = { localId };
		setRows((prev) => [
			...prev,
			{
				_localId: localId,
				id: null,
				artist_id: 0,
				artist_title: '',
				role: '',
				role_group: DEFAULT_ROLE_GROUP_BY_TAB[activeTab],
				order: maxOrder + 1,
				isNew: true,
				isDirty: false,
				_deleted: false,
			},
		]);
	}, [rows, activeTab]);

	const removeRow = useCallback((localId) => {
		setRows((prev) => {
			const row = prev.find((r) => r._localId === localId);
			if (!row) {
				return prev;
			}
			// Focus the next visible row's first field, or Add Credit if none is left.
			const visible = prev.filter(
				(r) =>
					!r._deleted &&
					rowMatchesTab(
						r,
						row.role_group === 'actor'
							? 'cast'
							: row.role_group === 'producer'
								? 'producers'
								: 'creative'
					)
			);
			const idx = visible.findIndex((r) => r._localId === localId);
			const next = visible[idx + 1] || visible[idx - 1];
			focusRequestRef.current = next
				? { localId: next._localId }
				: { addButton: true };
			if (row.isNew) {
				return prev.filter((r) => r._localId !== localId);
			}
			return prev.map((r) =>
				r._localId === localId ? { ...r, _deleted: true } : r
			);
		});
	}, []);

	// credit_order is one shared list across all tabs; dragging within a tab only reorders that tab's rows relative to each other.
	// tabLocalIds is the pre-drag order for the active tab, used to find where the dragged row's new neighbor sits in the full list so other tabs stay untouched.
	const moveRowToIndex = useCallback((localId, toIndex, tabLocalIds) => {
		setRows((prev) => {
			const deleted = prev.filter((r) => r._deleted);
			const rest = prev.filter((r) => !r._deleted);

			const fromIndex = rest.findIndex((r) => r._localId === localId);
			if (fromIndex === -1) {
				return prev;
			}

			const [dragged] = rest.splice(fromIndex, 1);

			const otherTabIds = tabLocalIds.filter((id) => id !== localId);
			const beforeLocalId = otherTabIds[toIndex];

			let insertAt;
			if (beforeLocalId != null) {
				insertAt = rest.findIndex((r) => r._localId === beforeLocalId);
				if (insertAt === -1) {
					insertAt = rest.length;
				}
			} else {
				const otherTabIdSet = new Set(otherTabIds);
				let lastTabIndex = -1;
				rest.forEach((r, i) => {
					if (otherTabIdSet.has(r._localId)) {
						lastTabIndex = i;
					}
				});
				insertAt = lastTabIndex === -1 ? rest.length : lastTabIndex + 1;
			}

			rest.splice(insertAt, 0, dragged);
			return [...rest, ...deleted];
		});
	}, []);

	const saveCredits = useCallback(async () => {
		if (!postId) {
			return;
		}

		const incomplete = rows.filter(
			(r) => r.isNew && !r._deleted && !r.artist_id
		);
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
			const toCreate = rows.filter(
				(r) => r.isNew && !r._deleted && r.artist_id
			);
			const toUpdate = rows.filter(
				(r) => !r.isNew && !r._deleted && r.isDirty
			);
			const keepRows = rows.filter((r) => !r._deleted);

			await Promise.all(
				toDelete.map((r) =>
					apiFetch({
						path: `/theatrum/v1/credit/${r.id}`,
						method: 'DELETE',
					})
				)
			);

			const createdIds = {};
			await Promise.all(
				toCreate.map(async (r) => {
					const result = await apiFetch({
						path: `/theatrum/v1/production-credits/${postId}`,
						method: 'POST',
						data: {
							artist: r.artist_id,
							role_group: canonicalRoleGroupForRow(r),
							role: r.role,
						},
					});
					createdIds[r._localId] = result.id;
				})
			);

			await Promise.all(
				toUpdate.map((r) =>
					apiFetch({
						path: `/theatrum/v1/credit/${r.id}`,
						method: 'PUT',
						data: {
							artist: r.artist_id,
							role_group: canonicalRoleGroupForRow(r),
							role: r.role,
						},
					})
				)
			);

			const orderedIds = keepRows
				.map((r) => (r.isNew ? createdIds[r._localId] : r.id))
				.filter(Boolean);

			if (orderedIds.length > 0) {
				await apiFetch({
					path: `/theatrum/v1/production-credits/${postId}/reorder`,
					method: 'POST',
					data: { order: orderedIds },
				});
			}

			loadCredits();
			setNotice({ type: 'success', message: 'Credits saved.' });
		} catch {
			setNotice({
				type: 'error',
				message: 'Error saving credits. Please try again.',
			});
		} finally {
			setIsSaving(false);
		}
	}, [postId, rows, loadCredits]);

	const visibleRows = rows.filter(
		(r) => !r._deleted && rowMatchesTab(r, activeTab)
	);
	const rowsKey = visibleRows.map((r) => r._localId).join(',');

	// Keyboard reorder: same shared-list semantics as drag, one step within the active tab.
	const moveRowBy = useCallback(
		(localId, delta) => {
			const tabLocalIds = visibleRows.map((r) => r._localId);
			const from = tabLocalIds.indexOf(localId);
			const to = from + delta;
			if (from === -1 || to < 0 || to >= tabLocalIds.length) {
				return;
			}
			moveRowToIndex(localId, to, tabLocalIds);
			setAnnouncement(
				`Moved to position ${to + 1} of ${tabLocalIds.length}`
			);
			focusRequestRef.current = {
				localId,
				control: delta < 0 ? 'up' : 'down',
			};
		},
		[visibleRows, moveRowToIndex]
	);

	// Apply any pending focus request once the rows have rendered.
	useEffect(() => {
		const req = focusRequestRef.current;
		if (!req || !listRef.current) {
			return;
		}
		focusRequestRef.current = null;
		if (req.addButton) {
			listRef.current.parentElement
				?.querySelector('.credits-actions button')
				?.focus();
			return;
		}
		const wrapper = listRef.current.querySelector(
			`[data-local-id="${req.localId}"]`
		);
		if (!wrapper) {
			return;
		}
		const target =
			req.control === 'up'
				? wrapper.querySelector('.credit-row-move button:first-child')
				: req.control === 'down'
					? wrapper.querySelector(
							'.credit-row-move button:last-child'
						)
					: wrapper.querySelector('input');
		(target?.disabled ? wrapper.querySelector('input') : target)?.focus();
	}, [rowsKey]);

	// jQuery UI Sortable (same lib as ACF's repeater). `update` captures the drop target; `stop` cancels jQuery's own DOM move so React's key-based re-render is what actually reorders rows, avoiding the two fighting over the DOM.
	useEffect(() => {
		const $ = window.jQuery;
		if (!$ || !$.fn.sortable || !listRef.current) {
			return undefined;
		}

		const $list = $(listRef.current);
		const tabLocalIds = visibleRows.map((r) => r._localId);

		$list.sortable({
			handle: '.credit-row-drag-handle',
			items: '> .credit-row-wrapper',
			axis: 'y',
			tolerance: 'pointer',
			placeholder: 'credit-row-placeholder',
			forcePlaceholderSize: true,
			update(event, ui) {
				const localId = ui.item.data('local-id');
				const newIndex = ui.item.index();
				pendingOrderRef.current =
					localId != null
						? { localId: String(localId), newIndex }
						: null;
			},
			stop() {
				$list.sortable('cancel');
				if (pendingOrderRef.current) {
					moveRowToIndex(
						pendingOrderRef.current.localId,
						pendingOrderRef.current.newIndex,
						tabLocalIds
					);
					pendingOrderRef.current = null;
				}
			},
		});

		return () => {
			if ($list.hasClass('ui-sortable')) {
				$list.sortable('destroy');
			}
		};
	}, [activeTab, rowsKey, moveRowToIndex]);

	if (!postId) {
		return (
			<p style={{ color: '#757575', fontStyle: 'italic' }}>
				Save the post first to manage credits.
			</p>
		);
	}

	if (isLoading) {
		return <Spinner />;
	}

	return (
		<div className="theatrum-credits-manager">
			<div
				className="credits-announcer"
				aria-live="polite"
				aria-atomic="true"
			>
				{announcement}
			</div>
			{notice && (
				<Notice
					status={notice.type}
					isDismissible
					onRemove={() => setNotice(null)}
				>
					{notice.message}
				</Notice>
			)}

			<TabPanel
				className="credits-tabs"
				tabs={TABS}
				onSelect={setActiveTab}
			>
				{() => (
					<>
						{visibleRows.length === 0 && (
							<p
								style={{
									color: '#757575',
									fontStyle: 'italic',
									margin: '8px 0',
								}}
							>
								No credits yet.
							</p>
						)}

						<div className="credits-list" ref={listRef}>
							{visibleRows.map((row, index) => (
								<CreditRow
									key={row._localId}
									row={row}
									onChange={(updates) =>
										updateRow(row._localId, updates)
									}
									onDelete={() => removeRow(row._localId)}
									onMoveUp={() => moveRowBy(row._localId, -1)}
									onMoveDown={() =>
										moveRowBy(row._localId, 1)
									}
									canMoveUp={index > 0}
									canMoveDown={index < visibleRows.length - 1}
								/>
							))}
						</div>

						<div className="credits-actions">
							<Button
								variant="secondary"
								onClick={addRow}
								icon="plus"
							>
								Add Credit
							</Button>
						</div>
					</>
				)}
			</TabPanel>

			<div className="credits-save-actions">
				<Button
					variant="primary"
					onClick={saveCredits}
					isBusy={isSaving}
					disabled={isSaving}
				>
					Save Credits
				</Button>
			</div>
		</div>
	);
}
