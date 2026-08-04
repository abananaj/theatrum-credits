# Production Credits Block (`theatrum/production-credits`)

One block, `roleGroup` attribute, 4 display modes (default + 3 registered block variations):

1. **Production Credits** (`roleGroup: 'all'`, default) — every credit for the production
2. **Production Team** (`roleGroup: 'team'`) — every `credit_role_group` **except** `actor`
   and `producer` (i.e. `playwright`, `director`, `choreographer`, `designer`, `other`)
3. **Production Cast** (`roleGroup: 'cast'`) — `credit_role_group = 'actor'` only
4. **Production Partners** (`roleGroup: 'partner'`) — `credit_role_group = 'producer'` only

`stage_management` is not a valid role group (see the plugin's own README for the full
`THEATRUM_CREDITS_VALID_ROLE_GROUPS` list) — "team" is everything left over after excluding
cast and partners, not an explicit allow-list.

