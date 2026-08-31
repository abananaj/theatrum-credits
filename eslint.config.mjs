// Canonical ESLint config for all five Chance Theater submodules.
// DO NOT EDIT THE RENDERED eslint.config.mjs IN A SUBMODULE — edit this file
// and run `.build/sh/audit.sh sync`.
//
// DEVIATION from the WordPress JS handbook (approved 2026-08-28, recorded in
// .audit/config/DEVIATIONS.md): the handbook names JSHint + .jshintrc as
// authoritative, not ESLint. JSHint cannot parse modern ESM/TSX/JSX and is
// effectively abandoned; @wordpress/eslint-plugin is what Gutenberg itself
// uses, so it's adopted here as a documented substitution, not "the standard".
// Treat the handbook's jQuery-era deltas (semicolon-light, double quotes in
// places) as informational, not failures — ANIMATION_REVIEW.md's framing,
// adopted repo-wide per GLOBAL_REVIEW.md §1c/Part 5.
//
// Token substituted by sync: TEXTDOMAIN (not double-braced here on purpose —
// str_replace would eat a literal placeholder in this comment too)

import wpConfig from '@wordpress/eslint-plugin/configs/recommended.js';

export default [
	...wpConfig,
	{
		ignores: [ '**/vendor/**', '**/node_modules/**', '**/build/**', '**/dist/**', '**/.build/**' ],
	},
	{
		rules: {
			'@wordpress/i18n-text-domain': [ 'error', { allowedTextDomain: 'theatrum-credits' } ],
		},
	},
];
