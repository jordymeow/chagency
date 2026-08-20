# CLAUDE.md, Chagency

Working notes for future Claude sessions. Keep this terse and current.

## What this plugin is

A deliberately minimal WordPress 7 chat-meets-agent plugin built on the new AI framework (`wp_ai_client_prompt()` + Connectors + Abilities). Today it's a chatbot, usable both inside the WordPress admin and on the public site, controlled by two independent toggles. As the Abilities API matures, it becomes an agent that can perform actions through conversation. The name **Chagency** (chat + agency) is meant to cover that arc without a future rename.

Author is **Jordy Meow** (handle *TigrouMeow*, site *jordymeow.com*). This project is *not* a Meow Apps product, don't add meowapps.com links or branding.

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
2. **Zero third-party plugin dependencies.** Chagency only uses what WP 7 ships in core (AI Client + Connectors + Abilities + Gutenberg toolkit). No bundled vendor SDKs, no telemetry, no phone-home. AI provider plugins (Anthropic / Google / OpenAI Connectors) are not Chagency dependencies, they're WP 7's plugin architecture for providers.
3. **All AI calls go through `AI_Service`.** `generate_chat_reply()` for the chat (it owns the ability loop), `create_chat_prompt()` when a caller just needs a builder. Never call `wp_ai_client_prompt()` directly from REST/Abilities, the service owns defaults.
4. **All provider discovery goes through `wp_get_connectors()`.** Never read `get_option` for API keys; core handles that.
5. **Front-end is `@wordpress/components` + `@wordpress/element`, compiled with `@wordpress/scripts`.** Don't swap bundlers. Don't import UI libraries outside Gutenberg.
6. **Two surfaces, one widget.** `admin_enabled` (default true) puts the launcher in `wp-admin` for `manage_options` users. `frontend_enabled` (default false) puts it on the public site for every visitor. Settings + provider tests stay `manage_options`. `/chat` opens up to anyone when the front-end toggle is on (`chat_permission_check`).
7. **No temperature / max-tokens / top-p knobs.** Modern models ignore them. Don't re-add.
8. **Commit `build/`.** wp.org installs must work without `pnpm install`. Source lives in `src/`.
9. **UX is one settings page + a floating widget.** No Tools menu, no full-screen chat page.
10. **No raw `<style>` or `<script>` tags in PHP output.** Always `wp_add_inline_style` / `wp_add_inline_script`. Plugin Check flags inline tags.
11. **Errors are rewritten for humans.** `AI_Service::friendly_error()` maps core's structured codes (`prompt_network_error`, `prompt_client_error` + HTTP status, ...) onto sentences a site owner can act on, and keeps the provider's own wording in `data.detail`, which the widget only shows on the admin surface. Never surface a raw SDK exception in a chat bubble.
12. **Abilities are opt-in, admin-only, and visible.** Nothing is allowed by default, `agent_abilities()` returns empty for anyone who can't `manage_options` (so the public widget never gets tools), and every call is echoed under the reply. `AI_Service::MAX_ABILITY_ROUNDS` caps the loop.

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
  Services/AI_Service.php        ← singleton around wp_ai_client_prompt + the ability-calling loop
  Widget_Loader.php              ← mounts the floating widget on admin AND/OR frontend
  Settings/Settings_Page.php     ← Settings → Chagency mount (boot script-module pattern)
  Settings/Settings_Registration.php ← register_setting + show_in_rest schema
  REST/Routes.php                ← /chat, /test, /connectors, /abilities, /settings (+ MAX_HISTORY_TURNS)
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
tests/run.php                    ← `pnpm run test`, no PHPUnit, no DB (excluded from the zip)
languages/chagency.pot           ← stub .pot
docs/WP7-AI-FRAMEWORK.md · docs/WP7-BOOT-ARCHITECTURE.md · docs/CHANGELOG.md
```

## Canonical load order

1. `chagency.php` defines constants, requires `includes/autoload.php`, instantiates `Main`.
2. `Main::setup()` hooks `plugins_loaded` → `load()`.
3. `load()` runs `Requirements`, requires `helpers.php`, registers `plugin_action_links`, and hooks `init` (priority 15) → `initialize()`.
4. `initialize()` calls `Settings_Registration::init()`, `Settings_Page::init()`, `Widget_Loader::init()`, `Routes::init()`, `Abilities_Registrar::init()`.

Note: `load_plugin_textdomain` is intentionally NOT called, auto-loaded since WP 4.6 and Plugin Check flags it as discouraged.

## Boot architecture (Settings → Chagency page)

Our Settings page follows Automattic's "boot" pattern used by Settings → Connectors and the official AI plugin. Everything lives in [`docs/WP7-BOOT-ARCHITECTURE.md`](docs/WP7-BOOT-ARCHITECTURE.md). Short version:

- We ship a **script module** (`chagency/settings/content`) that exports `stage`, our `<Page>` React component.
- PHP registers a classic "prerequisites" script carrying the `wp-*` dependencies + an inline `import('@wordpress/boot').then(mod => mod.initSinglePage({ mountId, routes }))` call.
- Boot builds the DOM hierarchy itself (`.boot-layout-container` → dark `.boot-layout` → `.boot-layout__surfaces` → white rounded `.boot-layout__stage` → `.admin-ui-page`) and injects every WPDS CSS token. Rounded card, sticky header, view-transitions are all boot-provided.
- **Do not hand-roll** `.admin-ui-page*` markup or copy-paste WPDS variables into our CSS. Setting `--wpds-*` in our `:root` is a `plugin-wpds/no-setting-wpds-custom-properties` lint error.
- Admin-chrome reset CSS is enqueued via `wp_add_inline_style( 'wp-components', $css )` in `maybe_register_assets`, never as a raw `<style>` tag (PCP rule).
- The floating widget stays a **classic script** because it's not a boot-layout page; it just pokes a DOM island on every admin screen.

## Framework facts (verified against WP 7.1, released 2026-08-19)

* `wp_ai_client_prompt( $prompt )` returns `WP_AI_Client_Prompt_Builder`. Snake_case methods wrap the SDK `PromptBuilder`.
* Generator methods return `WP_Error`, the fluent chain absorbs errors and replays them on the next generator call.
* `$prompt` accepts a `list<Message>` and then *is* the whole conversation. `AI_Service::create_prompt()` uses that shape so the ability loop can replay tool calls and responses. `with_history()` is the other way of doing it: current turn in the constructor, prior turns in `with_history( ...$messages )`.
* **Tool calling is core's job, not ours.** `using_abilities( ...$names )` converts each `WP_Ability` into a `FunctionDeclaration`, and `WP_AI_Client_Ability_Function_Resolver` (`has_ability_calls()` / `execute_abilities()`) executes what the model asked for. `execute_abilities()` returns a `UserMessage` of `FunctionResponse` parts, ready to append. `WP_Ability::execute()` runs `check_permissions()` first, so ability permissions are enforced by core, for the current user.
* The resolver only executes abilities passed to its constructor, and only calls prefixed `wpab__`. Everything else comes back as an error `FunctionResponse`.
* Nothing streams and nothing generates embeddings in 7.1. The SDK only gained `is_supported_for_embedding_generation()`; the generation side and the streaming API announced in the 7.1 previews did not ship. Do not promise either.
* `wp_get_connectors()` returns every *registered* provider, not just configured ones. `\Chagency\connector_is_configured()` decides usability from the credential sources the connector declares (env var → constant → option), never from the AI Client registry, see the trap below.
* WP 7.1 added the `application_password` connector auth method + `wp_connectors_get_application_password_credentials()`.
* Abilities register on `wp_abilities_api_init`, categories on `wp_abilities_api_categories_init`. Names must be lowercase + namespaced (`chagency/send-message`).
* WP 7.1 ability meta: `public` is the high-level "expose to clients" flag and seeds `show_in_rest`. We set both, since `public` does not exist in 7.0.
* WP 7.1 added a full ability filter suite (`wp_ability_invoked`, `wp_pre_execute_ability`, `wp_ability_normalize_input`, `wp_ability_validate_input`, `wp_ability_permission_result`, `wp_ability_execute_result`, `wp_ability_validate_output`) and `wp_get_abilities( $args )` filtering. We use none of them yet; they are the natural hooks if we ever add logging or rate limiting.
* Core abilities in 7.1 are still just `core/get-site-info`, `core/get-user-info`, `core/get-environment-info`. The `core/read-*` names in the merge proposals never shipped.

## Common traps

* Role enum is `MessageRoleEnum::model()`, **not** `assistant()`. Wire format uses `"assistant"`; `AI_Service::to_messages()` translates.
* `GenerativeAiResult::toText()` **throws** when a candidate has no text part, which is exactly what a pure tool-call turn looks like. Read `toMessage()` and walk the parts (`AI_Service::message_to_text()`).
* `using_provider()` needs the provider registered in the AI Client registry (i.e. a provider plugin like `ai-provider-for-anthropic` active). Always guard with `is_supported_for_text_generation()`.
* `with_history( $array )` silently misbehaves, use spread.
* `register_setting` with `show_in_rest` must supply a full schema; `show_in_rest: true` alone is rejected when the value is an object.
* JS config lives at `window.chagencyConfig` (widget) / `window.chagencySettingsConfig` (settings stage). Don't hand-roll `wp_localize_script`; the `chagency_` handle prefix is enforced via `Asset_Loader`.
* **Unqualified function calls from sub-namespaces silently fall through to globals.** e.g. calling `get_settings()` from `Chagency\REST\` resolved to WP's deprecated global `get_settings()` until we renamed our helper to `get_plugin_settings()`. Always use `use function Chagency\foo;` and keep helper names distinctive.
* Plugin Check flags `get_settings()` regardless of namespace, that's why our helper is `get_plugin_settings()`.
* **`isProviderConfigured()` is not a lookup, it is a network probe.** `ListModels` availability does an HTTP GET on the provider's models endpoint (cached 24h, so a cold cache blocks the page) and `GenerateText` availability fires a real, billed generation with `max_tokens: 1` and no caching. Core only calls it on the Connectors screen. `has_credentials()` runs on every admin page load, so it must stay pure local reads. Do not "simplify" it to ask the registry.
* Plugin URI and Author URI MUST differ (or omit Plugin URI). We dropped `Plugin URI:` from the header.
* wp.org auto-derives the slug from the Plugin Name via `sanitize_title()`. Generic prefixes ("AI ...", "Simple ...", "Advanced ...") get rejected. Names ending in known SDK terms ("Chatkit", "AgentKit") get flagged for trademark even when defunct.

## Build & testing

```
pnpm install          # one-time
pnpm run build        # production bundle
pnpm run test         # PHP harness, see below
pnpm run start        # watch mode while editing src/
pnpm run lint:js      # eslint (flat config, see eslint.config.js)
pnpm run lint:css     # stylelint
pnpm run plugin-zip   # wp.org-ready zip; post-process with
                      # `zip -d chagency.zip 'chagency/README.md' 'chagency/package.json'`
                      # to strip files npm-packlist forces in.
```

Toolchain (as of 2026-05-22):

- `@wordpress/scripts` 32.2.x (ESLint 10 flat config; project overrides live in `eslint.config.js`)
- `@wordpress/admin-ui` 2.1.x (used for the `<Page>` wrapper inside the boot stage)
- `@wordpress/browserslist-config` 6.46.x
- `pnpm.overrides` pin `fast-uri ^3.1.2` + `uuid ^11.1.1` to clear two HIGH and one moderate transitive advisories that surface through `@wordpress/admin-ui > ... > stylelint > table > ajv > fast-uri`.

Local dev site: `http://seven.nekod.net/wp-admin/` (Local app, site "Seven"). It is symlinked twice into `wp-content/plugins/`, as `chagency` and as the legacy `ai-chat`; only activate one. As of 2026-08-20 the site still runs 7.0.1-RC1, update it to 7.1 before testing anything in this doc.

Smoke path: activate → Settings → Connectors → add a key → Settings → Chagency → toggle "Enable", hit Save → click the launcher in the bottom-right → send a message → reply arrives.

Abilities smoke path: Settings → Chagency → turn on *Let the assistant use abilities*, tick `core/get-site-info`, Save → ask the chat "what WordPress version am I running?" → the reply carries a `core/get-site-info` chip.

`pnpm run test` runs `tests/run.php`: no PHPUnit, no database, no web server. It boots the *real* bundled AI Client SDK out of a WordPress install, stubs the ~15 WordPress functions the plugin touches, and asserts against actual DTOs. `WP_PATH=/path/to/wordpress pnpm run test` points it elsewhere, which is how both 7.0 and 7.1 get checked before a release. Extend it whenever `AI_Service` or `helpers.php` grows a branch. It is excluded from the zip by `.distignore` and by the `files` allowlist.

## wp.org status (current: 0.0.4 live)

History:

- 2026-04-20: submitted as "AI Chatkit". Pre-review queue.
- 2026-04-21 (review email): flagged for "Chatkit" trademark concern + `AI ...` generic prefix + inline `<style>` + Plugin URI 404 + 4-char prefix rule on globals.
- 2026-04-21: renamed to **Chagency**, fixed inline `<style>` (now `wp_add_inline_style`), removed `Plugin URI:` line. Re-uploaded.
- 2026-05-05: 0.0.1 approved and published on wp.org.
- 2026-05-15: 0.0.2 shipped (public-site widget toggle, configurable chat title, panel persistence across admin nav, WP 7 AI plugin styling).
- 2026-06-03: 0.0.4 shipped (launcher visible when the provider key comes from an env var or constant).
- 2026-08-20: aligned with WP 7.1 (ability calling, `meta.public`, `application_password` connectors, registry-based connector status). Fewer than 10 installs, no reviews.
- SVN access granted; tags `0.0.1/` and `0.0.2/` both live at `plugins.svn.wordpress.org/chagency/tags/`. `.svn/` is the local working copy (untracked in git, intentionally).

Releases go through **Nekofy** (`nekofy /Users/meow/Documents/Coding/plugins/chagency`). It bumps `chagency.php` (`Version:` + `CHAGENCY_VERSION`), `Stable tag`, generates the changelog entries (readme.txt AND `docs/CHANGELOG.md`) from commit messages, and handles the SVN tag/push. NEVER hand-bump versions, hand-write changelog entries, or run `svn cp`/`svn ci` manually, see the `meow-plugins` skill §7b. Jordy runs Nekofy himself.

## When to update docs

* Public method signature changes → update file layout above. (`docs/CHANGELOG.md` is Nekofy-generated, leave it.)
* Framework quirk → add a line to `docs/WP7-AI-FRAMEWORK.md` under "Gotchas".
* Boot/stage behaviour → `docs/WP7-BOOT-ARCHITECTURE.md`.
* Non-obvious constraint → add a bullet to "Common traps" above.
* Naming / wp.org submission learnings → "Naming (final…)" section.
