# CLAUDE.md — Chagency

Working notes for future Claude sessions. Keep this terse and current.

## What this plugin is

A deliberately minimal WordPress 7 chat-meets-agent plugin built on the new AI framework (`wp_ai_client_prompt()` + Connectors + Abilities). Today it's a chatbot. As the Abilities API matures, it becomes an agent that can perform actions through conversation. The name **Chagency** (chat + agency) is meant to cover that arc without a future rename.

Author is **Jordy Meow** (handle *TigrouMeow*, site *jordymeow.com*). This project is *not* a Meow Apps product — don't add meowapps.com links or branding.

## Naming (final, post wp.org review feedback)

History: "Chatbot" → taken. "AI Chat" → taken. "AI Chatkit" → submitted but flagged by wp.org review (28 Apr 2026): "Chatkit" matches Pusher's defunct trademark, and `AI ...` patterns are flagged as starting with a generic identifier. Resolved 2026-04-21 by renaming to **Chagency** (coined word, brand-first, satisfies the rules cleanly).

* Public plugin name: **Chagency**
* wp.org slug / Text Domain / zip folder / main PHP file: `chagency` (+ `chagency.php`)
* PHP namespace: `Chagency\`
* PHP constants: `CHAGENCY_PLUGIN_FILE`, `CHAGENCY_VERSION`, `CHAGENCY_PLUGIN_DIR`, `CHAGENCY_PLUGIN_URL`, `CHAGENCY_ABILITY_CATEGORY`
* REST namespace: `chagency/v1`
* Ability name: `chagency/send-message`
* Option key: `chagency_settings` · option group: `chagency`
* JS globals: `window.chagencyConfig`, `window.chagencySettingsConfig`
* JS event: `chagency:settings-changed`
* Script handle prefix: `chagency_`, prereq handle `chagency-settings-prerequisites`
* CSS class prefix: `.chagency-*`
* DOM IDs: `#chagency-widget-root`, `#chagency-settings-root`

Nothing should say `Chatbot`, `AI Chat`, `AI Chatkit`, `ai-chat*`, `aichat*`, or `AiChatkit\` anywhere. Full migration was done with:

```
perl -pe 's/ai-chatkit/chagency/g; s/aichatkit/chagency/g; s/\bAiChatkit\b/Chagency/g; s/\bAICHATKIT_/CHAGENCY_/g'
perl -pe 's/AI Chatkit/Chagency/g; s/AI Chat/Chagency/g'
```

User-facing labels (menu title, launcher text, page title) all say **Chagency**.

## Non-negotiable design rules

1. **Mirror [WordPress/ai](https://github.com/WordPress/ai) patterns.** Plugin-file namespace declaration, `Main` singleton with `__clone`/`__wakeup` guards, `Requirements` callback-driven checks, `Asset_Loader` utility, `AI_Service` singleton around `wp_ai_client_prompt()`, `Settings_Registration` split from `Settings_Page`.
2. **Zero third-party plugin dependencies.** Chagency only uses what WP 7 ships in core (AI Client + Connectors + Abilities + Gutenberg toolkit). No bundled vendor SDKs, no telemetry, no phone-home. AI provider plugins (Anthropic / Google / OpenAI Connectors) are not Chagency dependencies — they're WP 7's plugin architecture for providers.
3. **All AI calls go through `AI_Service::get_instance()->create_chat_prompt()`.** Never call `wp_ai_client_prompt()` directly from REST/Abilities — the service owns defaults.
4. **All provider discovery goes through `wp_get_connectors()`.** Never read `get_option` for API keys; core handles that.
5. **Front-end is `@wordpress/components` + `@wordpress/element`, compiled with `@wordpress/scripts`.** Don't swap bundlers. Don't import UI libraries outside Gutenberg.
6. **Admin-only (for now).** REST routes, abilities, settings page, widget all require `manage_options`. Front-end exposure is on the roadmap once agent capabilities are mature enough to expose safely.
7. **No temperature / max-tokens / top-p knobs.** Modern models ignore them. Don't re-add.
8. **Commit `build/`.** wp.org installs must work without `pnpm install`. Source lives in `src/`.
9. **UX is one settings page + a floating widget.** No Tools menu, no full-screen chat page.
10. **No raw `<style>` or `<script>` tags in PHP output.** Always `wp_add_inline_style` / `wp_add_inline_script`. Plugin Check flags inline tags.

## File layout (authoritative)

```
chagency.php                     ← namespace Chagency; constants(); Main::get_instance()
LICENSE                          ← GPL-2.0 full text
.distignore                      ← exclusion list for plugin zip
package.json                     ← wpPlugin.name: "chagency", handlePrefix: "chagency", files allowlist
includes/
  autoload.php                   ← PSR-4 for Chagency\
  helpers.php                    ← OPTION_KEY const + namespaced helpers (required in Main::load)
  Main.php                       ← singleton bootstrap
  Requirements.php               ← PHP/WP/framework/build checks
  Asset_Loader.php               ← enqueue_{script,style,localize_script,set_script_translations}
  Services/AI_Service.php        ← singleton around wp_ai_client_prompt
  Admin/Widget_Loader.php        ← enqueues floating widget on every admin page
  Settings/Settings_Page.php     ← Settings → Chagency mount (boot script-module pattern)
  Settings/Settings_Registration.php ← register_setting + show_in_rest schema
  REST/Routes.php                ← /chat, /test, /connectors, /settings
  Abilities/Registrar.php        ← chagency/send-message
src/
  index.js                       ← classic entry; mounts <Widget /> on #chagency-widget-root
  Widget.js                      ← floating launcher + chat panel
  Settings.js                    ← <Settings /> React app for the boot stage
  Snackbars.js                   ← shared notice portal
  useConversation.js             ← localStorage-backed hook (per-user key)
  pageContext.js                 ← getPageContext() for {current_page}/{current_url}
  rest.js                        ← tiny apiRequest helper
  style.css                      ← widget + settings styles
  settings/index.js              ← ES-module entry; exports `stage` for @wordpress/boot
build/                           ← compiled (committed)
languages/chagency.pot           ← stub .pot
docs/WP7-AI-FRAMEWORK.md · docs/WP7-BOOT-ARCHITECTURE.md · docs/CHANGELOG.md
```

## Canonical load order

1. `chagency.php` defines constants, requires `includes/autoload.php`, instantiates `Main`.
2. `Main::setup()` hooks `plugins_loaded` → `load()`.
3. `load()` runs `Requirements`, requires `helpers.php`, registers `plugin_action_links`, and hooks `init` (priority 15) → `initialize()`.
4. `initialize()` calls `Settings_Registration::init()`, `Settings_Page::init()`, `Widget_Loader::init()`, `Routes::init()`, `Abilities_Registrar::init()`.

Note: `load_plugin_textdomain` is intentionally NOT called — auto-loaded since WP 4.6 and Plugin Check flags it as discouraged.

## Boot architecture (Settings → Chagency page)

Our Settings page follows Automattic's "boot" pattern used by Settings → Connectors and the official AI plugin. Everything lives in [`docs/WP7-BOOT-ARCHITECTURE.md`](docs/WP7-BOOT-ARCHITECTURE.md). Short version:

- We ship a **script module** (`chagency/settings/content`) that exports `stage` — our `<Page>` React component.
- PHP registers a classic "prerequisites" script carrying the `wp-*` dependencies + an inline `import('@wordpress/boot').then(mod => mod.initSinglePage({ mountId, routes }))` call.
- Boot builds the DOM hierarchy itself (`.boot-layout-container` → dark `.boot-layout` → `.boot-layout__surfaces` → white rounded `.boot-layout__stage` → `.admin-ui-page`) and injects every WPDS CSS token. Rounded card, sticky header, view-transitions are all boot-provided.
- **Do not hand-roll** `.admin-ui-page*` markup or copy-paste WPDS variables into our CSS. Setting `--wpds-*` in our `:root` is a `plugin-wpds/no-setting-wpds-custom-properties` lint error.
- Admin-chrome reset CSS is enqueued via `wp_add_inline_style( 'wp-components', $css )` in `maybe_register_assets` — never as a raw `<style>` tag (PCP rule).
- The floating widget stays a **classic script** because it's not a boot-layout page; it just pokes a DOM island on every admin screen.

## Framework facts (verified against WP 7.0-RC2)

* `wp_ai_client_prompt( $prompt )` returns `WP_AI_Client_Prompt_Builder`. Snake_case methods wrap the SDK `PromptBuilder`.
* Generator methods return `WP_Error` — the fluent chain absorbs errors and replays them on the next generator call.
* History is `with_history( ...$messages )` where each message is `new Message( MessageRoleEnum::user()|model(), [ new MessagePart( $text ) ] )`. **Current turn goes in the constructor**; prior turns into `with_history`. `AI_Service::create_chat_prompt()` handles the split.
* `wp_get_connectors()` returns every *registered* provider, not just configured ones. Use `\Chagency\has_credentials()` to gate UI.
* Abilities register on `wp_abilities_api_init`, categories on `wp_abilities_api_categories_init`. Names must be lowercase + namespaced (`chagency/send-message`).

## Common traps

* Role enum is `MessageRoleEnum::model()`, **not** `assistant()`. Wire format uses `"assistant"`; `AI_Service::create_chat_prompt()` translates.
* `using_provider()` needs the provider registered in the AI Client registry (i.e. a provider plugin like `ai-provider-for-anthropic` active). Always guard with `is_supported_for_text_generation()`.
* `with_history( $array )` silently misbehaves — use spread.
* `register_setting` with `show_in_rest` must supply a full schema; `show_in_rest: true` alone is rejected when the value is an object.
* JS config lives at `window.chagencyConfig` (widget) / `window.chagencySettingsConfig` (settings stage). Don't hand-roll `wp_localize_script`; the `chagency_` handle prefix is enforced via `Asset_Loader`.
* **Unqualified function calls from sub-namespaces silently fall through to globals.** e.g. calling `get_settings()` from `Chagency\REST\` resolved to WP's deprecated global `get_settings()` until we renamed our helper to `get_plugin_settings()`. Always use `use function Chagency\foo;` and keep helper names distinctive.
* Plugin Check flags `get_settings()` regardless of namespace — that's why our helper is `get_plugin_settings()`.
* Plugin URI and Author URI MUST differ (or omit Plugin URI). We dropped `Plugin URI:` from the header.
* wp.org auto-derives the slug from the Plugin Name via `sanitize_title()`. Generic prefixes ("AI ...", "Simple ...", "Advanced ...") get rejected. Names ending in known SDK terms ("Chatkit", "AgentKit") get flagged for trademark even when defunct.

## Build & testing

```
pnpm install          # one-time
pnpm run build        # production bundle
pnpm run start        # watch mode while editing src/
pnpm run lint:js      # eslint
pnpm run lint:css     # stylelint
pnpm run plugin-zip   # wp.org-ready zip; post-process with
                      # `zip -d chagency.zip 'chagency/README.md' 'chagency/package.json'`
                      # to strip files npm-packlist forces in.
```

Local dev site: `http://seven.nekod.net/wp-admin/`. Plugin folder is symlinked from `~/plugins/ai-chat` (local dev folder name still says ai-chat) → `wp-content/plugins/ai-chat/`. The plugin source has been fully renamed to Chagency; only the local folder name is legacy and harmless.

Smoke path: activate → Settings → Connectors → add a key → Settings → Chagency → toggle "Enable", hit Save → click the launcher in the bottom-right → send a message → reply arrives.

## wp.org status (0.0.1)

- 2026-04-20: submitted as "AI Chatkit". Pre-review queue.
- 2026-04-21 (review email): flagged for "Chatkit" trademark concern + `AI ...` generic prefix + inline `<style>` + Plugin URI 404 + 4-char prefix rule on globals.
- 2026-04-21: renamed to **Chagency**, fixed inline `<style>` (now `wp_add_inline_style`), removed `Plugin URI:` line. Re-uploaded.

**Stable tag stays `0.0.1` until SVN access is granted** (the tag reflects what's on SVN, not local dev).

## When to update docs

* Public method signature changes → update file layout above + `docs/CHANGELOG.md`.
* Framework quirk → add a line to `docs/WP7-AI-FRAMEWORK.md` under "Gotchas".
* Boot/stage behaviour → `docs/WP7-BOOT-ARCHITECTURE.md`.
* Non-obvious constraint → add a bullet to "Common traps" above.
* Naming / wp.org submission learnings → "Naming (final…)" section.
