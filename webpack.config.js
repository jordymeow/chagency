/**
 * Dual-output build.
 *
 * Output 1 — classic IIFE for the floating widget. Uses the standard
 *            `@wordpress/scripts` config (DependencyExtractionPlugin +
 *            `window.wp.*` externals).
 *
 * Output 2 — ES module for the Settings page's boot "stage". Imported
 *            dynamically by `@wordpress/boot` when the route is active.
 *            We strip DependencyExtractionPlugin because it blocks
 *            `@wordpress/*` imports inside module builds; the Gutenberg
 *            packages we need are still externalised to `window.wp.*`
 *            via manual externals so the bundle stays small. The
 *            classic wp-* scripts listed on the "prerequisites" handle
 *            put those globals in place before boot loads us.
 *
 * @package
 */

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// `@wordpress/admin-ui` is not in this list — it isn't a reliable
// classic script on every WP 7 build, so we bundle it into our module.
const WP_GLOBALS = [
	[ '@wordpress/element', 'window.wp.element' ],
	[ '@wordpress/components', 'window.wp.components' ],
	[ '@wordpress/i18n', 'window.wp.i18n' ],
	[ '@wordpress/data', 'window.wp.data' ],
	[ '@wordpress/notices', 'window.wp.notices' ],
	[ '@wordpress/api-fetch', 'window.wp.apiFetch' ],
	[ '@wordpress/primitives', 'window.wp.primitives' ],
	[ 'react', 'window.React' ],
	[ 'react-dom', 'window.ReactDOM' ],
	[ 'react/jsx-runtime', 'window.ReactJSXRuntime' ],
];

// -------- 1) classic widget build --------

const widgetConfig = {
	...defaultConfig,
	name: 'widget',
	entry: { index: path.resolve( __dirname, 'src/index.js' ) },
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
		filename: 'index.js',
	},
};

// -------- 2) ES-module Settings build --------

// Clone default config and strip the DependencyExtractionWebpackPlugin,
// which errors out on `@wordpress/*` imports inside a module build.
const settingsPlugins = ( defaultConfig.plugins || [] ).filter( ( plugin ) => {
	const name = plugin && plugin.constructor && plugin.constructor.name;
	return name !== 'DependencyExtractionWebpackPlugin';
} );

const settingsConfig = {
	...defaultConfig,
	name: 'settings',
	entry: { index: path.resolve( __dirname, 'src/settings/index.js' ) },
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build/settings' ),
		filename: 'index.js',
		library: { type: 'module' },
		module: true,
		chunkFormat: 'module',
		environment: { module: true, dynamicImport: true },
	},
	experiments: {
		...( defaultConfig.experiments || {} ),
		outputModule: true,
	},
	externalsType: 'var',
	externals: Object.fromEntries( WP_GLOBALS ),
	plugins: settingsPlugins,
	target: 'web',
};

module.exports = [ widgetConfig, settingsConfig ];
