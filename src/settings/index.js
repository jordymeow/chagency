/**
 * Chagency Settings — script-module "stage".
 *
 * Boot (@wordpress/boot) imports this module dynamically when the
 * `chagency/settings/content` route is active, then renders the exported
 * `stage` React component inside the `.boot-layout__stage` surface.
 *
 * The `cfg` object lives on `window.chagencySettingsConfig`, seeded by
 * `Settings_Page::render()` via `wp_add_inline_script` on the
 * prerequisites classic handle.
 *
 * @package
 */

import Settings from '../Settings';

function StageRoot() {
	const cfg =
		( typeof window !== 'undefined' && window.chagencySettingsConfig ) ||
		{};
	return <Settings cfg={ cfg } />;
}

export const stage = StageRoot;
