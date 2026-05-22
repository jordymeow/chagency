/**
 * Chagency, classic script entry point (the floating widget).
 *
 * Mounts the <Widget /> component on `#chagency-widget-root`, which is
 * printed into the admin footer by `Widget_Loader` whenever the current
 * user has `manage_options` and at least one AI provider is configured.
 *
 * The Settings → Chagency page does NOT use this bundle, it uses the
 * `wordpress/boot` script-module system (see src/settings/).
 *
 * @package
 */

import { createRoot, render } from '@wordpress/element';

import Widget from './Widget';
import './style.css';

const cfg = window.chagencyConfig || {};

function mount() {
	const node = document.getElementById( 'chagency-widget-root' );
	if ( ! node ) {
		return;
	}
	const tree = <Widget cfg={ cfg } />;
	if ( typeof createRoot === 'function' ) {
		createRoot( node ).render( tree );
	} else if ( typeof render === 'function' ) {
		render( tree, node );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
