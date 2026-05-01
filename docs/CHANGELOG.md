# Changelog

All notable changes to **Chagency** are recorded here. Keep entries short and action-oriented.

## 0.4.0 — 2026-04-20

Aligned with [WordPress/ai](https://github.com/WordPress/ai) conventions and readied for wordpress.org submission.

### Structure

- Plugin entry (`chagency.php`) declares `namespace Chagency;` + a `constants()` function, matching WordPress/ai's plugin-file pattern.
- Replaced the procedural `bootstrap.php` with a `Main` singleton (`__clone`/`__wakeup` guards, `get_instance()`), called from the plugin file.
- Added a `Requirements` class (callback-driven checks for PHP, WP, framework, and built assets) that posts a consolidated admin notice on failure.
- Added an `Asset_Loader` utility that reads `.asset.php`, prefixes handles with `aichat_`, and supports RTL stylesheets.
- Added an `AI_Service` singleton that wraps `wp_ai_client_prompt()` and owns the default model-preference list — REST and Abilities both route through it.
- Split `Settings_Registration` from `Settings_Page`: the option registers on `admin_init` + `rest_api_init` (with a full JSON schema and `show_in_rest`); the page is just the React mount.
- Procedural `helpers.php` (namespaced functions) replaces the former `Helpers` static class.

### UX

- Chat page now fills the full admin content area (no fixed-height box).
- Settings page is a full React app with `Card` sections, inline Notices, and per-provider Test buttons — no more classic `form-table`.
- Menu placement: **Tools → Chagency** is first in the submenu; **Settings → Chagency** sits directly under **Connectors**.

### wordpress.org prep

- Added a full GPL-2.0 `LICENSE` file.
- Added `.distignore` listing dev-only files excluded from the plugin zip.
- Added `wpPlugin` metadata to `package.json` (`handlePrefix: chagency`, `pages: [chagency]`, `plugin-zip` script).

## 0.3.0 — 2026-04-20

A full restructure to match the WordPress AI team's plugin conventions.

- Moved PHP under the `Chagency\` namespace with PSR-4 autoload.
- Added a `@wordpress/scripts` build pipeline (`package.json`, `src/`, `build/`).
- Rewrote the chat UI as a compiled React app using `@wordpress/element` + `@wordpress/components`.
- Removed **Temperature** and **Max tokens** settings — many current models ignore them or interpret them inconsistently.
- Credited *Jordy Meow* as author; removed Meow Apps branding.

## 0.2.0 — 2026-04-20

- Switched the admin UI to runtime `wp.element` + `@wordpress/components`.
- Enqueued the `wp-components` stylesheet for the WP 7 admin look.

## 0.1.0 — 2026-04-20

Initial release.

- Plugin scaffolding + version gate.
- **Tools → Chagency** admin page with a pure HTML/CSS/JS chat UI + empty state.
- **Settings → Chagency** page via the Settings API.
- Per-provider **Test** buttons driven by `/chagency/v1/test`.
- REST routes: `POST /chagency/v1/chat`, `POST /chagency/v1/test`, `GET /chagency/v1/connectors`.
- `chagency/send-message` ability via the Abilities API.
- Docs: `README.md`, `CLAUDE.md`, `docs/WP7-AI-FRAMEWORK.md`.
