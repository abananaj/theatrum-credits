# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**[← Back to wp_root](../../../CLAUDE.md)** | [AGENTS.md](AGENTS.md) | [README.md](README.md)

## Project Overview

Theatrum Credits manages production credits (cast & crew) for Chance Theater. Credits are
rows in a custom `ct_credits` SQL table, edited through a React "Credits Manager" meta box
on the Production edit screen (REST-backed), and displayed on the frontend via two blocks.

**This plugin's architecture changed completely partway through its life** — it started as
an ACF repeater field synced into a `credit` junction post type, then migrated to the current
custom-table design. If you're reading old context (an old commit, a stale doc, a cached
memory), verify against **[README.md](README.md)** before trusting it — that file was
rewritten from scratch against the current code as of this pass. See README's "History"
section for the migration story and `PLAN.md`/`REFACTOR-NOTES.md` for the detailed record.

## Build & Development Commands

```bash
npm run build            # build:blocks (wp-scripts) + build:manager (webpack) — two separate pipelines
npm run build:blocks      # src/blocks/* → build/blocks/*
npm run build:manager     # src/credits-manager/* → build/credits-manager/*
npm run start:blocks      # watch mode, blocks only
npm run start:manager     # watch mode, credits manager only
```

Two build tools because the meta box (`credits-manager`) isn't a block — it's a plain React
app mounted into a `add_meta_box()` callback, built with its own webpack config
(`webpack.credits-manager.js`) rather than `wp-scripts`.

## Architecture

```
theatrum-credits.php   # loader: table bootstrap, models, legacy credit CPT, REST, blocks, meta box
inc/table.php            # ct_credits schema (dbDelta, version-gated via theatrum_credits_db_version option)
inc/rest-endpoints.php   # /theatrum/v1/production-credits, /artist-credits, /credit/{id} — the only write path
models/credits.php       # all query functions; run directly against ct_credits, return stdClass rows
src/credits-manager/     # React meta box (CreditsManager.js, CreditRow.js) — the only editing UI
src/blocks/              # theatrum/artist-credits, theatrum/production-credits (display only)
```

**Editing flow**: Credits Manager meta box → REST endpoint → direct `$wpdb` write to
`ct_credits`. There is no save-post hook in this path — `models/credits.php`'s
`save_post_credit` hook exists only for backwards compatibility with directly-edited legacy
`credit` posts, not as part of normal editing.

## Known Schema Gap

`credit_artist` is a bare post ID column with no type discriminator. Producer/partner credits
conceptually link to the `support` CPT, not `artist`, but the schema has no
`credit_entity_type` (or similar) column to distinguish them — flagged during the original
migration in `REFACTOR-NOTES.md` and never resolved. Don't assume `credit_artist` always
points at an `artist` post when writing new queries against this table.

## Related Documentation

- **wp_root docs:** `../../../CLAUDE.md` and `AGENTS.md`
- **Deployment:** `../../../.deploy/DEV_DEPLOY.md`
- **Prod content import** (this table gets populated from legacy prod data too): `../../../.build/PROD_IMPORT_RUNBOOK.md` Stage 8, which extracts `ct-production-role` posts into `ct_credits`
