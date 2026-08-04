# AGENTS.md

Claude Code agent workflows specific to **theatrum-credits**. For site-wide agent workflows,
see [wp_root AGENTS.md](../../../AGENTS.md). For architecture, see [CLAUDE.md](CLAUDE.md).

## Working in this plugin

No automated `CHANGELOG.md` — the root `/changelog` skill derives entries from `git log` here
(see `.claude/skills/changelog/SKILL.md`).

**Before touching credit data or queries, read README.md's Database section.** This plugin's
whole data model changed once already (ACF repeater → `ct_credits` table), and the migration
left one open gap: `credit_artist` has no entity-type discriminator, so a producer credit's
`credit_artist` may not actually point at an `artist` post. Don't write a new query assuming
otherwise without checking.

## Common Tasks

### Adding a new credit query function

Add it to `models/credits.php`, follow the existing pattern: `$wpdb->prepare()` against
`THEATRUM_CREDITS_TABLE`, return raw `stdClass` rows (formatting/shaping happens in the
caller, e.g. `get_artist_productions_with_dates()` wraps `get_artist_productions()`). Don't
reintroduce `WP_Query` or postmeta lookups — the whole point of the table migration was to
get off both.

### Changing what the Credits Manager can edit

The write path is REST-only: `inc/rest-endpoints.php` validates and writes, then
`src/credits-manager/CreditsManager.js`/`CreditRow.js` call it. Adding a new editable field
means updating the REST callback's accepted params (and `THEATRUM_CREDITS_VALID_ROLE_GROUPS`
if it's role-group-related), the `ct_credits` schema in `inc/table.php` if a new column is
needed (bump `theatrum_credits_db_version` to trigger `dbDelta`), and the React component.

### Changing block display logic

`src/blocks/production-credits/render.php`'s `roleGroup` → `credit_role_group` mapping is
exclusion-based for `team` (everything except `actor`/`producer`), not an allow-list — a new
role group added to `THEATRUM_CREDITS_VALID_ROLE_GROUPS` automatically falls into `team`
unless you add explicit handling. Check both `render.php` and `variations.js` if you're
adding a role group and expect a dedicated display mode instead.

### Reviewing changes

```bash
/code-review low
```

Since the plugin has already been through one incomplete migration (see
`REFACTOR-NOTES.md`), treat any change touching `credit_artist`/`credit_role_group` semantics
as higher-risk than average — check whether it interacts with the known entity-type gap
before merging.
