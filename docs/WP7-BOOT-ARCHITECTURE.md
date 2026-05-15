# WordPress 7 "Boot" Architecture

> Living notes. Everything here was reverse-engineered from core's
> `wp-includes/js/dist/script-modules/` and from the WordPress/ai plugin
> (`/tmp/chatbot-ref/ai/`). This is the canvas/stage system that
> Settings → Connectors, the AI plugin's settings, Font Library, and
> similar "full-page React app" admin screens are built on.

## 30-second summary

Admin pages that want the new full-width, rounded-card, view-transitioned
React-app look don't hand-roll their layout. They hand an entry component
to **`@wordpress/boot`**, a script-module shipped in core. Boot builds
the entire DOM hierarchy (`.boot-layout-container` → dark `.boot-layout`
→ margined `.boot-layout__surfaces` → white rounded `.boot-layout__stage`
→ `.admin-ui-page`) and injects all the WPDS design-token CSS. The page
just mounts a React tree that returns a `<Page>` from
**`@wordpress/admin-ui`**.

## The file you're looking for

`wp-includes/js/dist/script-modules/boot/index.js` (served at
`/wp-includes/js/dist/script-modules/boot/index.min.js`).

Shape:

```js
export { init, initSinglePage, store };
```

`initSinglePage({ mountId, routes })` is the call the AI plugin and
Connectors use. It:

1. Walks `routes[]` and registers each into boot's internal store.
2. Finds `document.getElementById(mountId)`.
3. `createRoot(root).render(<StrictMode><App rootComponent={RootSinglePage} /></StrictMode>)`.
4. The internal `App` renders the full `.boot-layout` hierarchy and
   resolves the active route's `content_module` as the current stage.

## Route shape

```php
[
  'path'           => '/',
  'content_module' => 'my-plugin/settings',      // REQUIRED for visible pages
  'route_module'   => 'my-plugin/settings/route' // OPTIONAL; lifecycle hooks
]
```

- `content_module` is a **script-module ID**. That module must `export`
  `stage`, a React component. Boot dynamically imports the module when
  the route is active and renders `<stage />`.
- `route_module` (optional) is another script-module that exports route
  lifecycle hooks (onEnter, onLeave, etc.).

## CSS, automatic

Boot's module, on load, injects **two `<style>` tags** into `<head>`:

1. The **WPDS design tokens** (`--wpds-border-radius-lg: 8px`,
   `--wpds-color-*`, `--wpds-dimension-*`, `--wpds-elevation-*`,
   `--wpds-font-*`).
2. The **`.admin-ui-page*`, `.boot-layout*`, view-transition** rules.

This means you get all the WordPress-admin visual tokens + the rounded
white-card-on-dark-canvas layout **for free** by simply loading the boot
module. No manual enqueue of `wp-admin-ui` stylesheet needed.

## The DOM hierarchy boot produces

```
#<mountId>.boot-layout-container
  └─ div.boot-layout.boot-layout--single-page           ← position:absolute, dark bg, fills the content area
      └─ div.boot-layout__surfaces                       ← flex container, 8px right/bottom margin (the gap)
          └─ <CSS-module-scoped wrapper>                 ← hashed class name
              └─ div.boot-layout__stage                  ← white, border-radius: 8px, overflow: hidden ← THE CARD
                  └─ div.admin-ui-navigable-region.admin-ui-page
                      ├─ header.admin-ui-page__header    ← sticky header with title / subtitle / actions
                      └─ div.admin-ui-page__content      ← scrollable body (use `.has-padding` to get 16px/24px)
                          └─ <your Page's children />
```

That 8-px radius you kept seeing is literally `.boot-layout__stage {
border-radius: 8px }` + the 8 px margin on `.boot-layout__surfaces` that
lets the dark `.boot-layout` show through on the right and bottom.

## Import maps

Connectors + the AI plugin both expose an HTML `<script type="importmap">`
tag on the page:

```json
{
  "imports": {
    "@wordpress/boot":  "/wp-includes/js/dist/script-modules/boot/index.min.js?ver=...",
    "@wordpress/route": "/wp-includes/js/dist/script-modules/route/index.min.js?ver=...",
    "wp/routes/connectors-home/route":   "/wp-includes/build/routes/connectors-home/route.min.js?ver=...",
    "wp/routes/connectors-home/content": "/wp-includes/build/routes/connectors-home/content.min.js?ver=..."
  }
}
```

WordPress produces this automatically from the `wp_register_script_module`
calls, one entry per registered module. Dynamic `import('@wordpress/boot')`
resolves via this map.

Modules whose content is compiled with `@wordpress/build` (esbuild) use
the `package-external:@wordpress/name` convention for any `@wordpress/*`
package that **isn't** also registered as a module, they get inlined as
`window.wp.name` references, relying on a matching classic script handle
being enqueued in `dependencies`.

## Script modules vs classic scripts

WordPress 6.5+ ships two parallel script systems:

| Classic script                      | Script module                    |
|-------------------------------------|----------------------------------|
| `wp_register_script`                | `wp_register_script_module`      |
| `wp_enqueue_script`                 | `wp_enqueue_script_module`       |
| `script_dependencies` array of      | `module_dependencies` array of   |
| handle strings                      | `{ id, import: 'static'\|'dynamic' }` |
| Exposes `window.wp.name` global     | Exposed via `<script type="importmap">` |
| Rendered as `<script src="…">`      | Rendered as `<script type="module" src="…">` |

A module's `*.asset.php` can carry **both**:

```php
<?php return array(
  'dependencies'        => array( 'wp-components', 'wp-element', … ),     // classic
  'module_dependencies' => array(
    array( 'id' => '@wordpress/boot',  'import' => 'static' ),
    array( 'id' => '@wordpress/a11y',  'import' => 'dynamic' ),
  ),
  'version' => '…',
);
```

The classic list is there so any `window.wp.*` references inside the
module bundle resolve to real globals.

## How a page wires itself up

The pattern (distilled from `options-connectors.php` and the AI plugin):

```php
// 1. Register the content module (the one exporting `stage`).
wp_register_script_module(
    'my-plugin/settings/content',
    plugins_url( 'build/settings/content.js', __FILE__ ),
    array(
        array( 'id' => '@wordpress/boot',     'import' => 'static' ),
        array( 'id' => '@wordpress/element',  'import' => 'static' ),
        // …or whatever is really used
    ),
    '1.0'
);

// 2. Register a "prerequisites" *classic* script whose only job is to
//    carry the wp-* dependencies and the inline boot-init call.
wp_register_script(
    'my-plugin-settings-prerequisites',
    false,                              // no URL, inline only
    array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-data', … ),
    '1.0',
    true
);
wp_add_inline_script(
    'my-plugin-settings-prerequisites',
    sprintf(
        'import("@wordpress/boot").then(mod => mod.initSinglePage({ mountId: %s, routes: %s }));',
        wp_json_encode( 'my-plugin-root' ),
        wp_json_encode( array(
            array(
                'path'           => '/',
                'content_module' => 'my-plugin/settings/content',
            ),
        ) )
    )
);

// 3. Enqueue both on render.
wp_enqueue_script( 'my-plugin-settings-prerequisites' );
wp_enqueue_script_module( 'my-plugin/settings/content' );

// 4. Print the mount div.
echo '<div id="my-plugin-root" class="boot-layout-container"></div>';
```

The inline script runs after the classic prerequisites load, performs a
**dynamic** `import('@wordpress/boot')`, which is resolved via the
importmap, then calls `initSinglePage`. Boot's rendering pulls
`content_module` via its own dynamic import and mounts the stage.

## Chrome reset (what core's page.php also prints)

```html
<style>
  #wpwrap       { background: var( --wpds-color-fg-content-neutral, #1e1e1e ); overflow-y: auto; }
  body          { background: #fff; }
  #wpcontent    { padding-inline-start: 0; }
  #wpbody-content { padding-bottom: 0; }
  #wpbody-content > div:not(.boot-layout-container):not(#screen-meta) { display: none; }
  #wpfooter     { display: none; }
  .a11y-speak-region { inset-inline-start: -1px; top: -1px; }
  @media ( min-width: 782px ) { #wpwrap { overflow-y: initial; } }
</style>
```

Everything else is `position:absolute` inside `.boot-layout`, so the old
`.wrap` chrome is deliberately hidden.

## Stage component, minimum viable

```js
// src/settings/content.js, becomes wp_register_script_module( 'my-plugin/settings/content', … )
import { Page } from '@wordpress/admin-ui';
import { __ }   from '@wordpress/i18n';

function Settings() {
  return (
    <Page title={ __( 'Chagency', 'my-plugin' ) }>
      …
    </Page>
  );
}

export const stage = Settings;   // <-- boot calls this
```

## Gotchas

- `@wordpress/admin-ui` isn't reliably in `window.wp.adminUi` on every
  WP 7 build (confirmed on 7.0-RC2). Import it as an ES module from
  within a script module, the importmap will resolve it via the
  registered handle, **not** via the global.
- `wp_register_script_module` silently drops a script if a declared
  dependency handle isn't registered. Double-check every `id` you
  declare in `module_dependencies` exists.
- The build tool matters. `@wordpress/scripts` v30 is classic-output by
  default. `--experimental-modules` or a custom `webpack.config.js` with
  `output.module: true` + `experiments.outputModule: true` are needed
  for ESM output. The AI plugin uses `@wordpress/build` (esbuild) which
  has ESM + `package-external` support out of the box.
- Boot's CSS rules live-inject on module load. If you also ship your
  own `.admin-ui-page*` fallback CSS, keep it defensive (lower
  specificity) so boot's wins once the module loads.
- The route `path` must start with `/` even for single-page apps. Boot
  matches against the URL path.

## Gotchas we hit while wiring this up

These two tripped us up; they're not in any docs we could find.

### 1. Enqueued modules are NOT in the import map

`wp_enqueue_script_module($id)` emits the module as `<script type="module"
src="...">` directly. WP's own code is explicit about this, inside
`WP_Script_Modules::get_import_map()`:

```php
// Note: the script modules in $this->queue are not included in the importmap
// because they get printed as scripts.
```

Result: if `@wordpress/boot` tries to `await import('your/module')`
internally, the specifier won't resolve, it's not in the map.

**Fix:** *don't* enqueue your module directly. Attach it as a
`module_dependencies` entry on a **classic** prerequisites script you
*do* enqueue. Those module deps go into the importmap without being
printed as standalone `<script type="module">` tags.

```php
wp_scripts()->add_data(
    'my-prereq-handle',
    'module_dependencies',
    array(
        array( 'id' => '@wordpress/boot',          'import' => 'static' ),
        array( 'id' => 'my-plugin/settings/content', 'import' => 'static' ),
    )
);
```

### 2. `dynamic` import type races with the importmap

We originally used `'import' => 'dynamic'` for the content module,
mirroring the AI plugin's code. That produced a repeatable
`TypeError: Failed to resolve module specifier 'my-plugin/settings/content'`
even though the importmap contained the entry. The console could
resolve it just fine after page load, but boot's internal call at
initial render time couldn't.

**Fix:** declare the content module with `'import' => 'static'`. The
browser pre-loads it as part of the module graph, caches it, and boot's
later `import()` call hits the cache instead of re-resolving. The
"failed to resolve" error disappears.

This is surprising because the AI plugin ships with `dynamic` and works
,  probably their loader module's static deps are enough to prime the
cache before boot's `import()` runs. Using `static` directly is the
more robust pattern for simple single-page setups.

### 3. Boot needs the full classic `wp-*` dep list

`@wordpress/boot/index.js` reads from `window.wp.data`,
`window.wp.notices`, `window.wp.core-data`, `window.wp.commands`,
`window.wp.editor`, `window.wp.keyboard-shortcuts`, etc. at runtime.
Enqueuing the module via a dynamic `import()` call does **not**
automatically enqueue those classic scripts. We had to list the full
dependency set on our prerequisites classic script. The authoritative
list lives at
`/wp-includes/js/dist/script-modules/boot/index.min.asset.php`:

```php
array('react', 'react-dom', 'react-jsx-runtime', 'wp-commands',
  'wp-components', 'wp-compose', 'wp-core-data', 'wp-data', 'wp-editor',
  'wp-element', 'wp-html-entities', 'wp-i18n', 'wp-keyboard-shortcuts',
  'wp-keycodes', 'wp-notices', 'wp-primitives', 'wp-private-apis',
  'wp-theme', 'wp-url');
```

Symptom of a missing classic dep: `TypeError: Cannot read properties of
undefined (reading 'name')` somewhere inside `wp-data`'s `select()` , 
boot tried to read a store that was never registered.

## Unknowns / still to investigate

- How `@wordpress/admin-ui` is actually served, is it a registered
  script module on some builds? Only shipped inside `@wordpress/boot`'s
  bundle? Needs a fresh look at the Connectors importmap once we wire
  things up and can inspect in the browser.
- Where core deregisters boot's fallback WPDS tokens in case two
  versions load (AI plugin plus core both using boot).
- Whether custom routes under our own namespace (`chagency/…`) need to
  live inside `wp-includes/build/routes` or can live in our plugin
  directory.
