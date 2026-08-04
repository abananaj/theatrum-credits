# Theatrum Credits

Production credits (cast & crew) management for the Chance Theater website — a custom
`ct_credits` SQL table plus a React meta-box editor, replacing an earlier ACF-repeater +
junction-post-type design entirely (see "History" below).

## Architecture

Credits are rows in a custom table, not posts and not postmeta. Editors manage them through
a React "Credits Manager" meta box on the Production edit screen, backed by REST endpoints —
there is no ACF repeater field involved in day-to-day editing anymore.

```
theatrum-credits/
├── theatrum-credits.php        # Loader: table bootstrap, models, credit CPT (legacy),
│                                # ACF fields (legacy), REST endpoints, admin list,
│                                # block registration, Credits Manager meta box + enqueue
├── inc/
│   ├── table.php                # Creates/upgrades the ct_credits table (dbDelta, version-gated)
│   ├── rest-endpoints.php       # /theatrum/v1/production-credits, /artist-credits, /credit/{id}
│   ├── admin-list.php           # WP_List_Table admin view over ct_credits
│   ├── credit.php               # Registers the legacy `credit` CPT (staging-only, see below)
│   └── acf-fields.php           # Legacy ACF field group (kept for the save_post_credit
│                                 # backwards-compat hook only — not used for new editing)
├── models/
│   └── credits.php              # All query functions; run directly against ct_credits
├── src/
│   ├── credits-manager/         # React meta box: CreditsManager.js, CreditRow.js
│   └── blocks/
│       ├── artist-credits/      # Frontend display block: theatrum/artist-credits
│       └── production-credits/  # Frontend display block: theatrum/production-credits
├── build/                       # Compiled output (blocks via wp-scripts, credits-manager via webpack)
├── webpack.config.js            # wp-scripts default build config, for src/blocks
├── webpack.credits-manager.js   # Separate webpack config for the meta box React app
├── PLAN.md                      # 10-phase refactor plan (ACF repeater → ct_credits table)
└── REFACTOR-NOTES.md            # Notes from the first (incomplete) migration attempt
```

## Database

`ct_credits` (created/upgraded by `inc/table.php`, version-gated via the
`theatrum_credits_db_version` option):

| Column | Type | Notes |
|---|---|---|
| `credit_ID` | bigint, PK, auto-increment | |
| `credit_title` | varchar(255) | `"{Production} / {Artist}"`, auto-generated |
| `credit_name` | varchar(255) | `sanitize_title($credit_title)` |
| `credit_artist` | bigint | **Raw artist post ID** — see caveat below |
| `credit_production` | bigint | Raw production post ID |
| `credit_role` | varchar(255) | Free text, editor-entered |
| `credit_role_group` | varchar(100) | One of `THEATRUM_CREDITS_VALID_ROLE_GROUPS` (see below) |
| `credit_date` | varchar(20) | Copied from the production's `opening` ACF field at write time |
| `credit_order` | int | Per-production display order |
| `credit_created` / `credit_modified` | datetime | |

**`credit_artist` is producer-role-only a known gap:** it's typed as an artist post ID, but
producer/partner credits conceptually link to the `support` CPT, not `artist`. There is no
`credit_entity_type` (or equivalent) column to disambiguate — this was flagged in
`REFACTOR-NOTES.md` during the first migration attempt and was never resolved before the
table shipped. See wp_root's documentation-cleanup follow-up list for this as a live issue.

## Valid role groups

Defined once, in `inc/rest-endpoints.php`'s `THEATRUM_CREDITS_VALID_ROLE_GROUPS`:
`playwright`, `actor`, `director`, `choreographer`, `designer`, `producer`, `other`.
Any REST write with a role group outside this list is rejected. (`stage_management` is
**not** a valid role group, despite appearing in one code comment — see Blocks below.)

## Query Functions (`models/credits.php`)

All global scope, run directly against `ct_credits`, return arrays of `stdClass` row objects
(not `WP_Query`):

```php
get_production_credits($production_id, $args)       // $args: role_group, count_only
get_artist_productions($artist_id, $args)            // $args: role_group; ordered by credit_date DESC
get_artist_productions_with_dates($artist_id)        // formatted array, not raw rows
get_credits_by_group($production_id)                 // keyed by role_group
count_artist_productions($artist_id)                 // distinct productions
count_production_credits($production_id, $role_group)
```

## REST API (`inc/rest-endpoints.php`)

All under `theatrum/v1`:

| Route | Methods | Auth |
|---|---|---|
| `/production-credits/{post_id}` | GET, POST | GET public (already-public data only); POST requires `edit_posts` + `edit_post` on the production |
| `/production-credits/{post_id}/reorder` | POST | `edit_posts` + `edit_post` on the production |
| `/credit/{credit_id}` | PUT, DELETE | `edit_posts` + `edit_post` on the credit's production |
| `/artist-credits/{post_id}` | GET | Public (already-public data only) |

The Credits Manager meta box (`src/credits-manager/`) is the only consumer of the write
endpoints; the GET endpoints also back the two frontend blocks' editor previews.

## Blocks

Two server-rendered display blocks, registered as `theatrum/artist-credits` and
`theatrum/production-credits` (not `theatrum-credits/*` — the block namespace matches the
site-wide `theatrum/` convention, not the plugin slug):

- **`theatrum/artist-credits`** — used on artist pages; lists productions an artist worked on.
- **`theatrum/production-credits`** — used on production pages; one block, 4 display modes via
  the `roleGroup` attribute (`all` default, plus 3 registered variations): **Production Team**
  (`team` — every role group *except* `actor` and `producer`), **Production Cast** (`cast` →
  `actor` only), **Production Partners** (`partner` → `producer` only). See each block's own
  README for attributes and markup.

## Data Flow

1. Editor opens a Production post → the **Credits Manager** meta box (React, REST-backed)
   loads existing rows via `GET /production-credits/{id}`.
2. Adding/editing/removing/reordering a credit calls the matching REST endpoint directly —
   each write lands in `ct_credits` immediately, no save-post hook involved.
3. Frontend blocks query `ct_credits` via the model functions above at render time.

### Legacy backwards-compat path

`models/credits.php` still registers a `save_post_credit` hook that syncs a `credit` CPT post
(if one is edited directly) into `ct_credits`, keyed by a `_ct_credit_id` postmeta pointer.
The `credit` CPT (`inc/credit.php`) and its ACF field group (`inc/acf-fields.php`) exist
**only** to support this backwards-compat path — new credits should be created through the
Credits Manager meta box, not by creating `credit` posts directly.

## Build & Development

```bash
npm run build            # build:blocks (wp-scripts) + build:manager (webpack)
npm run build:blocks      # src/blocks/* → build/blocks/*
npm run build:manager     # src/credits-manager/* → build/credits-manager/* (separate webpack config)
npm run start:blocks      # watch mode, blocks only
npm run start:manager     # watch mode, credits manager only
```

## Dependencies

- WordPress 5.0+
- ACF Pro — only for the legacy `save_post_credit` backwards-compat path (see above); not
  required for normal Credits Manager editing
- Custom post types `artist` and `production` must exist before activation

## History

This plugin originally stored credits as an ACF repeater field (`production_credits_repeater`)
on production posts, auto-synced into a `credit` junction post type on save. `PLAN.md`
documents the 10-phase migration to the current `ct_credits` custom-table design (better
query performance, simpler data management, no per-repeater-row post overhead).
`REFACTOR-NOTES.md` documents a first migration attempt that undercounted data (it only read
existing `credit` posts, missing rows that lived solely in the ACF repeater) and flagged the
producer/`support`-entity-type gap described above, which the shipped schema still doesn't
address.
