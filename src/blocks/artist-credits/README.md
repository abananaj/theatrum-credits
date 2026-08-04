# Artist Credits Block (`theatrum/artist-credits`)

Displays the productions an artist has worked on, on artist pages. Fetches via
`GET /theatrum/v1/artist-credits/{post_id}`, which queries the `ct_credits` custom table
(via `get_artist_productions()`) — not ACF fields.

## Key Features

- Displays every production credited to the current artist
- Renders as a formatted list of production titles and roles
- Reusable across different post types (reads the current post as the artist context)
- No configuration needed - works automatically with post context
