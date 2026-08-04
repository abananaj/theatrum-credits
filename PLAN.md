# ct_credits Refactor — Implementation Plan

## Architecture Summary

- `ct_credits` custom SQL table replaces credit post type as the data store
- `credit_order` column handles display order within a production (no postmeta on productions)
- Custom React meta box on the production edit page replaces the ACF repeater
- Full CRUD REST API connects the meta box to ct_credits
- Existing credit posts are migrated to ct_credits via a one-time script
- Old credit posts remain in ct_posts untouched (soft migration — no deletes until verified)

---

## Table Schema

```sql
CREATE TABLE ct_credits (
  credit_ID        bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  credit_title     varchar(255)        NOT NULL DEFAULT '',
  credit_name      varchar(255)        NOT NULL DEFAULT '',
  credit_artist    bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  credit_production bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  credit_role      varchar(255)        NOT NULL DEFAULT '',
  credit_role_group varchar(100)       NOT NULL DEFAULT '',
  credit_date      varchar(20)         NOT NULL DEFAULT '',
  credit_order     int(11) UNSIGNED    NOT NULL DEFAULT 0,
  PRIMARY KEY  (credit_ID),
  KEY credit_artist     (credit_artist),
  KEY credit_production (credit_production),
  KEY credit_role_group (credit_role_group),
  KEY credit_order      (credit_production, credit_order)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Notes:**
- `credit_artist` stores artist post ID for all role groups except `producer`, which stores a support post ID
- `credit_date` stores the production's opening date value — year is derived from this at render time
- `credit_order` is scoped per production — starts at 0, increments per row

---

## Phase 1 — Database Setup

**Files:** `inc/table.php`, `theatrum-credits.php`

1. Create `inc/table.php`:
   - Define `theatrum_credits_create_table()` using `dbDelta()`
   - Version-gate with `get_option('theatrum_credits_db_version')`
   - Hook `theatrum_credits_maybe_create_table()` on `init`

2. Update `theatrum-credits.php`:
   - Add `global $wpdb; define('THEATRUM_CREDITS_TABLE', $wpdb->prefix . 'credits');`
   - Require `inc/table.php`

3. **Run:** Load any WordPress page to trigger table creation. Verify table exists.

---

## Phase 2 — Migration Script

**File:** `inc/migration.php` (run once via WP-CLI, then delete)

Goal: populate ct_credits from existing credit posts. Does NOT delete credit posts.

```
wp eval-file wp-content/mu-plugins/theatrum-credits/inc/migration.php
```

Script logic:
1. Get all `post_type=credit` posts, ordered by `menu_order ASC, post_title ASC`
2. For each credit post:
   - Read postmeta: `production`, `artist`, `role_group`, `role`
   - Skip if `production` or `artist` is missing (log skipped IDs)
   - Get `credit_date` from `get_field('opening', $production_id)`
   - Assign `credit_order` sequentially within each production (track per-production counter)
   - INSERT into ct_credits
   - Write the new `credit_ID` back to the credit post as `_ct_credit_id` postmeta — required for the Phase 8 backwards compat hook to find the correct row to upsert
3. Log: migrated count, skipped count, skipped IDs
4. Include a `$dry_run = true` flag

**After running:** verify row count matches expected, spot-check a few productions.

---

## Phase 3 — Model Layer

**File:** `models/credits.php` — full rewrite

Remove:
- `sync_repeater_to_credits()` and its `acf/save_post` hook

Rewrite all query functions to use `$wpdb` against ct_credits:

| Function | Query |
|---|---|
| `get_production_credits($production_id, $args)` | `WHERE credit_production = %d ORDER BY credit_order ASC` |
| `get_artist_productions($artist_id, $args)` | `WHERE credit_artist = %d ORDER BY credit_date DESC` |
| `get_credits_by_group($production_id)` | calls `get_production_credits()`, groups by `credit_role_group` |
| `get_artist_productions_with_dates($artist_id)` | calls `get_artist_productions()` |
| `count_artist_productions($artist_id)` | `COUNT(DISTINCT credit_production)` |
| `count_production_credits($production_id, $role_group)` | `COUNT(*)` with optional role_group filter |

All functions return plain arrays of row objects (not WP_Query).

---

## Phase 4 — REST API

**File:** `inc/rest-endpoints.php` — expand existing file

Namespace: `theatrum/v1`

| Method | Route | Purpose |
|---|---|---|
| GET | `/production-credits/{post_id}` | List credits for a production (editor preview) |
| POST | `/production-credits/{post_id}` | Create a new credit |
| POST | `/production-credits/{post_id}/reorder` | Update credit_order for all credits in a production |
| GET | `/artist-credits/{post_id}` | List credits for an artist (editor preview) |
| PUT | `/credit/{credit_id}` | Update a single credit |
| DELETE | `/credit/{credit_id}` | Delete a single credit |

Note: single-credit operations use `/credit/{credit_id}` to avoid URL ambiguity with the production-scoped routes that also accept a numeric ID.

All write endpoints: permission callback = `edit_posts`.

**Input validation (all write endpoints):**
- `role_group` must be one of: `playwright`, `actor`, `director`, `choreographer`, `designer`, `producer`, `other` — reject with 400 otherwise
- `artist` must be a positive integer — existence check via `get_post()`
- All `$wpdb` queries must use `$wpdb->prepare()` with `%d`/`%s` placeholders — no raw interpolation
- PUT/DELETE on `/credit/{credit_id}`: verify the credit's `credit_production` belongs to a post the current user can edit (`current_user_can('edit_post', $production_id)`)
- POST `/reorder`: verify every ID in the `order` array has `credit_production = $post_id` before updating — prevents reordering another production's credits

POST body for create/update:
```json
{
  "artist": 123,
  "role_group": "actor",
  "role": "Hamlet"
}
```
Server derives `credit_title`, `credit_name`, `credit_date` automatically.

Reorder body:
```json
{ "order": [42, 17, 8, 103] }
```
Server updates `credit_order` for each ID in the array sequentially.

---

## Phase 5 — Frontend Blocks

**Files:** `src/blocks/production-credits/render.php`, `src/blocks/artist-credits/render.php`

Update both to query ct_credits directly via `$wpdb` (or via model functions).

**production-credits/render.php:**
- Call `get_production_credits($post_id, ['mode' => $role_group])`
- `mode` values: `all`, `team` (NOT IN actor/producer), `cast` (actor), `partner` (producer)
- Output `<ul class="production-credits-ul">` with artist headshot, name, role

**artist-credits/render.php:**
- Call `get_artist_productions($post_id)`
- Year derived from `credit_date` — but first check what format the ACF `opening` field actually returns (timestamp, `Y-m-d`, `Ymd`, etc.) before writing the parse logic, since `strtotime()` and `ORDER BY` behave differently per format
- Remove `get_season_year()` helper — no longer needed
- Output `<ul class="artist-credits-ul">` with production title, role, year

After editing: copy both render.php files to `build/blocks/`.

---

## Phase 6 — Credits Manager UI (React Meta Box)

**New files:**
- `src/credits-manager/index.js` — entry point
- `src/credits-manager/CreditsManager.js` — main component
- `src/credits-manager/CreditRow.js` — single row component

### Registration

In `theatrum-credits.php`, enqueue the compiled script on production edit pages only:
```php
add_action('enqueue_block_editor_assets', function () {
  $screen = get_current_screen();
  if (! $screen || $screen->post_type !== 'production') return;
  wp_enqueue_script('theatrum-credits-manager', ...);
  // Pass REST nonce and base URL so the React component can make authenticated requests
  wp_localize_script('theatrum-credits-manager', 'theatrumCredits', [
    'restUrl' => rest_url('theatrum/v1/'),
    'nonce'   => wp_create_nonce('wp_rest'),
    'postId'  => get_the_ID(),
  ]);
});
```

Note: `get_current_screen()->post_type` is used instead of `get_post_type()` because `get_post_type()` returns `false` for unsaved (new) posts, which would prevent the meta box from loading when creating a new production.

### UI Structure

Implemented as a `PluginDocumentSettingPanel` (renders in the block editor right sidebar):

```
[ Production Credits ]
┌─────────────────────────────────────────────────────────┐
│  ≡  Artist              Role Group    Role              │
│  ≡  [John Smith    ▾]  [Actor     ▾] [Hamlet        ]  │
│  ≡  [Jane Doe      ▾]  [Director  ▾] [              ]  │
│                                              [✕]        │
│  + Add Credit                        [Save Credits]     │
└─────────────────────────────────────────────────────────┘
```

### Behaviour

- **On mount:** `GET /theatrum/v1/production-credits/{postId}` → populate rows
- **Add row:** append empty row to local state
- **Delete row:** `DELETE /theatrum/v1/production-credits/{credit_id}` (if saved) or remove from state (if unsaved)
- **Reorder:** drag handle or ↑↓ arrows update local order
- **Save Credits button:**
  1. For new rows: `POST` each one
  2. For modified rows: `PUT` each one
  3. `POST /reorder` with full ordered ID array
- **Artist field:** uses `@wordpress/components` `ComboboxControl` querying the WP REST API for artist (and support, for producer role_group) posts
- **Role group:** `SelectControl` with the same options as the old ACF field
- **Role:** `TextControl`

### Build

Add `credits-manager` as a new entry point in `package.json` / webpack config alongside the existing block entries.

---

## Phase 7 — ACF Cleanup

**File:** `inc/acf-fields.php`

- Remove (or set `'active' => false`) the `group_production_cast_crew` field group
- The repeater is no longer needed — the React meta box replaces it
- ACF fields on the credit post type itself (production, artist, role_group, role) can remain for reference but are no longer the write path

---

## Phase 8 — Backwards Compatibility Hook

**File:** `models/credits.php`

Add a lightweight hook on `save_post` for the `credit` post type. If someone edits an old credit post directly, sync it to ct_credits:

```php
add_action('save_post_credit', function ($post_id) {
  // Read postmeta, upsert into ct_credits
  // WHERE credit_ID matches a stored _ct_credit_id postmeta on the post
  // or INSERT and write the new ID back
}, 20);
```

This ensures old-style credit management still works during transition.

---

## Phase 9 — Testing Checklist

Before touching bulk data:

- [ ] Create one credit via the meta box → appears in ct_credits → appears in frontend block
- [ ] Edit that credit → ct_credits row updates
- [ ] Delete that credit → ct_credits row removed
- [ ] Reorder credits → `credit_order` updates → frontend reflects new order
- [ ] Production-credits block: all / cast / team / partner variations
- [ ] Artist-credits block: shows correct productions with year
- [ ] Old migrated credits display correctly on frontend
- [ ] Editing an old credit post directly still syncs to ct_credits (Phase 8 hook)

---

## Phase 10 — Cleanup

After testing and confirming everything works:

1. Delete `inc/migration.php`
2. Delete `inc/populate-repeaters.php`
3. Decide whether to trash old credit posts (separate decision — no rush)
4. Update `README.md` with new architecture
5. Update `REFACTOR-NOTES.md` to mark complete

---

## Order of Implementation

```
Phase 1  → Phase 2 (run migration) → Phase 3 → Phase 4
→ Phase 5 → verify frontend works
→ Phase 6 → verify editor UI works
→ Phase 7 → Phase 8 → Phase 9 → Phase 10
```

Do not proceed past Phase 5 without confirming the frontend blocks
display correctly from ct_credits data.

**Warning:** After Phase 3 removes the `sync_repeater_to_credits` hook, there is no write path for credits until the React meta box is live in Phase 6. Do not run Phase 3 on the production database without completing through Phase 6 in the same session.