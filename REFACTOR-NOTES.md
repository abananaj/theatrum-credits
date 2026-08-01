# ct_credits Refactor — Session Notes

## Goal

Replace the `credit` custom post type (data stored in `ct_posts` + `ct_postmeta`) with a
custom SQL table `ct_credits` for better query performance and simpler data management.

---

## What Was Built

### Table schema (`ct_credits`)

| Column              | Type                     | Notes                                                                 |
| ------------------- | ------------------------ | --------------------------------------------------------------------- |
| `credit_ID`         | bigint PK auto-increment |                                                                       |
| `credit_title`      | varchar(255)             | "{Production} / {Artist}", auto-generated                             |
| `credit_name`       | varchar(255)             | sanitized slug                                                        |
| `credit_artist`     | bigint                   | post ID of linked artist                                              |
| `credit_production` | bigint                   | post ID of linked production                                          |
| `credit_role`       | varchar(255)             | freetext role entered in repeater                                     |
| `credit_role_group` | varchar(100)             | playwright, actor, director, choreographer, designer, producer, other |
| `credit_date`       | varchar(20)              | production opening date, used to derive year for display              |

### Files created/modified

- `inc/table.php` — `dbDelta()` table creation, version-gated
- `theatrum-credits.php` — added `THEATRUM_CREDITS_TABLE` constant, requires new includes
- `models/credits.php` — full rewrite: all query functions use `$wpdb` against `ct_credits`, sync writes to table + `credit_ids` postmeta on production
- `inc/acf-fields.php` — removed `credit_id` sub_field from repeater
- `inc/rest-endpoints.php` — both callbacks rewritten to query `ct_credits`
- `src/blocks/production-credits/render.php` — calls `get_production_credits()`
- `src/blocks/artist-credits/render.php` — calls `get_artist_productions()`, year from `credit_date`
- `inc/migration.php` — one-time migration script (credit posts → ct_credits)
- `inc/populate-repeaters.php` — backfill repeater from ct_credits data
- `inc/bulk-sync.php` — (not written yet, identified as needed)

### Ordering approach

Production posts store an ordered JSON array of credit IDs in `credit_ids` postmeta.
Display order is determined by this array. The `get_production_credits()` function
queries `WHERE credit_ID IN (...)` using this array, then sorts PHP-side.

---

## What Went Wrong

### 1. Migration only read from credit posts

The migration script iterated `post_type = credit` posts and inserted them into `ct_credits`.
**Problem:** Most productions store their credits ONLY in the ACF repeater field
(`production_credits_repeater` on the production post). Credit posts were only created
for some productions via the old sync hook. The majority of credit data lives in the
repeater and was not migrated.

**Fix needed:** The migration (or a bulk-sync) should read from the repeater, not from
credit posts. Run `sync_repeater_to_credits()` across all productions with non-empty
repeater data.

### 2. Producer credits link to `support` post type, not `artist`

The `credit_role_group = 'producer'` group links to the `support` custom post type
(sponsors/partners), not to `artist` posts. The current schema assumes all credits
link to `artist` via `credit_artist`.

**Fix needed:** The table needs to handle multiple entity types. Options:

- Add a `credit_entity_type` column (`artist`, `support`, etc.) alongside `credit_artist`
  renamed to `credit_entity`
- Or keep separate columns: `credit_artist` and `credit_support`, with one always null
- The block variations (cast, team, partner) and all query functions need updating

### 3. Repeater data was empty for many old productions

Old productions (pre-sync era) had credit posts created through another mechanism.
Their `production_credits_repeater` postmeta is empty — the repeater was added later.
These credits DO exist as credit posts but were not entered via the repeater.

**Fix needed:** For old productions, the populate-repeaters script should backfill the
repeater from ct_credits after migration. Then everything is consistent.

---

## Next Attempt — Key Changes

Before writing any code:

1. **Audit all credit role groups** — confirm which role groups link to `artist` vs `support`
   vs any other post type. Decide on the schema for `credit_entity_type`.

2. **Decide on migration source** — repeater first, credit posts as fallback for old
   productions without repeater data.

3. **Decide on ordering** — the `credit_ids` array approach works, but consider whether
   the artist block also needs a per-artist `credit_ids` array, or whether it just queries
   `WHERE credit_entity = %d` ordered by `credit_date DESC`.

4. **Test one production end-to-end** before running any bulk migration.

