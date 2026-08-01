# Theatrum Credits

Custom credit management system for Chance Theater production website.

## Features

- **Production Credits Management**: Manage cast and crew through an ACF repeater field on production pages
- **Artist Production History**: Display all productions an artist has worked on
- **Automatic Sync**: Credits automatically created/updated when you save the repeater
- **Custom Blocks**: Two display blocks for showing credits on frontend

## Installation

1. Activate the plugin through WordPress admin
2. Ensure ACF Pro is installed (required for Repeater field type)
3. Go to a Production page to see the "Cast & Crew" field

## Custom Post Types

- **production**: Productions/shows
- **artist**: Artist profiles
- **credit**: Junction post type (created automatically via auto-sync)

## ACF Integration

### Field Group: Cast & Crew

Applied to: `production` posts

**Repeater Field: `production_credits_repeater`**

- `artist` (Post Object) - Link to artist
- `role_group` (Select) - playwright, actor, director, choreographer, designer, producer, other
- `role` (Text) - Specific role (e.g., "Hamlet", "Stage Manager")

When you save, a `credit` post is automatically created with these meta fields:

- `production` (post ID)
- `artist` (post ID)
- `role_group` (string)
- `role` (string)

## Query Functions

All functions are global scope (no namespaces).

### Production Credits

```php
get_production_credits($production_id, $args)
get_production_credits($production_id, array('role_group' => 'actor'))
```

### Artist Productions

```php
get_artist_productions($artist_id, $args)
get_artist_productions_with_dates($artist_id) // Sorted by opening date
count_artist_productions($artist_id)
```

### Organized Display

```php
$credits = get_credits_by_group($production_id);
// Returns: ['actor' => [...], 'designer' => [...], etc]
```

**Query Arguments:**

- `role_group` (string) - Filter by role_group
- `orderby` (string) - Default: 'menu_order title'
- `order` (string) - ASC or DESC
- `per_page` (int) - Default: 200
- `count_only` (bool) - Return count only
- `fields` (string) - 'ids' for ID-only results

## Blocks

Two custom Gutenberg blocks available:

### Production Credits Block

- **Name**: `theatrum-credits/production-credits`
- **Used on**: Production pages
- **Displays**: Cast and crew organized by role_group
- **Context**: Uses `postId` and `postType` from block context
- **Attribute**: `groupBy` (filter by specific role_group)

### Artist Productions Block

- **Name**: `theatrum-credits/artist-productions`
- **Used on**: Artist pages
- **Displays**: All productions artist has worked on
- **Context**: Uses `postId` and `postType` from block context
- **Attribute**: `sortBy` (date, date-asc, title)

## File Structure

```
theatrum-credits/
├── theatrum-credits.php              # Main plugin file
├── models/
│   └── credits.php                 # All query functions & auto-sync
├── inc/
│   ├── acf-fields.php              # ACF field group registration
│   └── blocks.php                  # Block registration
└── blocks/
    ├── ProductionCredits/
    │   ├── block.json              # Block config
    │   ├── render.php              # Server-side render
    │   └── index.js                # Block type registration
    └── ArtistProductions/
        ├── block.json
        ├── render.php
        └── index.js
```

## Data Flow

1. **Edit Production**: Go to Production page → Edit "Cast & Crew" repeater
2. **Save Production**: ACF save hook fires `sync_repeater_to_credits()`
3. **Auto-create Credits**: `credit` posts created/updated with repeater data
4. **Display on Frontend**: Use blocks or query functions to display

## Future Extensions

This plugin is designed to be extensible. Additional blocks can be added by:

1. Creating new folder in `blocks/`
2. Adding `block.json`, `render.php`, `index.js`
3. Registering in `inc/blocks.php`

Possible additions:

- Crew by Department filter block
- Timeline of artist productions
- Credits search/filter
- Graphical cast view

## Dependencies

- WordPress 5.0+
- ACF Pro (for Repeater field type)
- Custom post types: artist, production (must exist before plugin activation)

## Notes

- All PHP functions use global scope (no namespaces)
- Blocks are server-side rendered (dynamic blocks)
- Auto-sync prevents orphaned credits when repeater rows are deleted
- Credits are deleted permanently if removed from repeater

# CUSTOM SQL TABLE REFACTORING

Move this credit data to a custom table called `ct_credits`. All existing meta fields should be stored as columns instead. For example, columns could be:

- `credit_ID` (primary key, auto-increment)
- `credit_title` (Artist Name/Production Name, auto-generated from the title of the artist and production, with a "/" separator, e.g. "Hamlet/John Doe")
- `credit_name` (sanitized title, may not be necessary because credits are not shown on the front end as individual posts, but it may be useful for debugging and data management)
- `credit_artist` (`artist` or `supporter` post_ID, from the post_ID of the selected artist OR supporter in the repeater)
- `credit_production` (production ID, auto add production ID from which the credit is created)
- `credit_role` (string, input by user in repeater)
- `credit_role_alt` (string, input by user in repeater, optional)
- `credit_role_group` (string, input by user in repeater)
- `credit_opening` (date, equal to production opening meta value)
- `credit_order` (number, may not be necessary if production can have a field that is an array of credit_IDs that dictates the order in which they appear. The user should be able to easily change this order using the repeater field. Order is not needed for querying from artist block, those should be ordered by production opening date instead)

This will improve query performance and simplify data management. The plugin should be refactored to use this custom table instead of post meta for credits. The auto-sync function will need to be updated to insert/update/delete rows in the `ct_credits` table instead of creating `credit` posts. Query functions will also need to be rewritten to query the custom table directly. I would also like to remove the individual credit ID values that are saved to the production. Instead, there should be one field on production posts that will be an array of credit ID's. This will allow for easier querying later. The credit ID can be generated as an auto-incrementing primary key in the custom table.

