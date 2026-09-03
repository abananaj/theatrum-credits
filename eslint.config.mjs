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

// The package's "exports" map only exposes the root and ./eslintrc, so importing
// '@wordpress/eslint-plugin/configs/recommended.js' throws ERR_PACKAGE_PATH_NOT_EXPORTED and
// ESLint aborts before linting a single file — silently the case in all five submodules until
// 2026-09-02. Read the flat config off the package root instead.
import wpPlugin from '@wordpress/eslint-plugin';

const wpConfig = wpPlugin.configs.recommended;

export default [
	...wpConfig,
	{
		// *.min.js: vendored minified libraries (public/gsap.min.js alone was 2,136 of the theme's 3,232
		// findings). eslint.config.mjs: rendered by `audit.sh sync` from a template, so its own findings
		// can never be fixed in place.
		ignores: [
			'**/vendor/**',
			'**/node_modules/**',
			'**/build/**',
			'**/dist/**',
			'**/.build/**',
			'**/animista/**',
			'**/*.min.js',
			'eslint.config.mjs',
		],
	},
	{
		rules: {
			'@wordpress/i18n-text-domain': [ 'error', { allowedTextDomain: 'theatrum-credits' } ],
		},
	},
];
