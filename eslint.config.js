/**
 * Project ESLint config (flat, ESLint 10).
 *
 * Extends the @wordpress/scripts default with three project-specific tweaks:
 *
 *  1. `@wordpress/*` packages are runtime externals provided by WordPress, not
 *     bundled — `import/no-unresolved` would otherwise flag every import.
 *  2. Server-side option keys are snake_case by WP convention (`admin_enabled`,
 *     `chat_title`, etc.), so we relax `camelcase` for property names only.
 *  3. We use `catch ( _ )` as a "don't care about the error" convention; allow
 *     `_` (alone or as a prefix) as an explicitly ignored binding.
 */
const baseConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...baseConfig,
	{
		settings: {
			'import/core-modules': [],
			'import/ignore': [ '^@wordpress/' ],
		},
		rules: {
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
			camelcase: [
				'error',
				{ properties: 'never', ignoreDestructuring: true },
			],
			'no-unused-vars': [
				'error',
				{
					argsIgnorePattern: '^_',
					varsIgnorePattern: '^_',
					caughtErrors: 'all',
					caughtErrorsIgnorePattern: '^_',
				},
			],
		},
	},
];
