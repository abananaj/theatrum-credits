# theatrum-credits — Production Credits

> First draft. Component deep-dive; project-level story lives in the [root case study](../../../CASE_STUDY.md).

A custom SQL table, a REST API, and a React editor UI — for the one piece of data
a theater site can't model as posts and meta.

---

## Goal

- Model the **artist ↔ production ↔ role** relationship properly. It's a three-way join with ordering, and postmeta can't express it.
- Let staff edit a full production's credits in one screen — add, reorder, remove — without leaving the post editor.
- Render the same data two ways: by production (the program) and by artist (the résumé).
- Carry thirteen years of existing credits across without loss.

**Why a `mu-plugin`:** it owns a database table other content points at. Deactivating it would
silently empty every production page. `mu-plugins` can't be deactivated.

---

## Timeline

38 commits, 2026-05-11 → 2026-08-31.

- **May** — Init; split out of `theatrum-blocks` where credits first lived.
- **Jun** — ACF meta fields; the migration process; render bugs in both blocks fixed; title styling. Marked done 06-18.
- **Jul** — Display converted from grid to flexbox; styling refinements; editor-only styles removed; timestamps added.
- **Aug** — Renamed `chance-credits` → `theatrum-credits`; producer added as post meta with block-editor display, then the key format fixed (role text slugified to `snake_case` instead of raw spaces and capitals) and the sync restricted to 4 canonical production-scoped roles; standards pass including `%i` identifier placeholders replacing raw SQL interpolation.

---

## Structure

**The table** — `inc/table.php`

```
ct_credits
  credit_ID · credit_title · credit_name
  credit_artist      → artist post ID
  credit_production  → production post ID
  credit_role · credit_role_group
  credit_date · credit_order
  credit_created · credit_modified
```

Indexed on `credit_artist`, `credit_production`, `credit_role_group`, and the composite
`(credit_production, credit_order)` that drives ordered display.

`credit_name` exists alongside `credit_artist` on purpose — a credit can name someone who
has no artist record, and it still has to render.

**The rest**

- `models/credits.php` — the data layer
- `inc/rest-endpoints.php` — 4 routes under `theatrum/v1`: list/create by production, reorder, update/delete a credit, list by artist
- `inc/admin-list.php` — a WP list table for browsing all credits
- `inc/credit.php` · `inc/acf-fields.php`
- `src/credits-manager/` — the React editor UI (`CreditsManager.js`, `CreditRow.js`)
- `src/blocks/` — `production-credits` (with variations) and `artist-credits`

---

## Highlights

**Custom table over postmeta**

- Credits are a join, not an attribute. As postmeta, "every credit for this artist across all productions" is an unindexed serialized-value scan.
- As a table with real foreign keys and indexes, it's a query.
- The tradeoff — no post revisions, no native WP admin, no free REST API — is why the REST layer and the list table exist.

**The React manager**

- Full-production credit editing in one panel: add a credit, pick an artist, set the role, drag to reorder.
- Reordering is its own endpoint, so a drag writes one request instead of one per row.

**Two views, one source**

- `production-credits` renders the program listing, grouped by role group and ordered.
- `artist-credits` renders the same rows as an artist's history.
- One table, no duplication, no sync problem.

**Producer meta sync**

- Producer credits also mirror into post meta so they're queryable alongside other production fields — restricted to 4 canonical production-scoped roles after the first pass proved too broad, with role text slugified rather than used raw.

---

## Results

> **TODO:**
> - Row count in `ct_credits` — how many credits carried over
> - Migration story: what the 2013 structure looked like and what was lost or recovered
> - Screenshot of the credits manager and a rendered program page
