# Chagency — a tiny, native chatbot for WordPress 7

> A minimal WordPress plugin that puts a chatbot in your admin, built directly on top of the new WordPress 7 AI framework. No provider lock-in, no bloat, no marketing copy. Just a clean chat interface and the smallest amount of code needed to prove the framework works end-to-end.

Slug: `chagency` • Author: [Jordy Meow](https://jordymeow.com/)

> **For contributors:** the Settings page is wired through the
> `@wordpress/boot` script-module system — same framework Settings →
> Connectors and the WordPress/ai plugin use. See
> [`docs/WP7-BOOT-ARCHITECTURE.md`](docs/WP7-BOOT-ARCHITECTURE.md) for a
> living deep-dive, and [`CLAUDE.md`](CLAUDE.md) for the house rules.

---

## Why this project exists

WordPress 7 shipped three new APIs in `wp-includes/`:

- **`wp_ai_client_prompt()`** — a fluent prompt builder wrapping a bundled PSR SDK.
- **Connectors API** — a unified credentials registry shared across plugins.
- **Abilities API** — JSON-schema-typed tools that double as AI function calls, MCP tools, and REST endpoints.

Together they make WordPress a first-class AI platform. **Chagency is the smallest possible reference implementation built on top of them.** It isn't a competitor to AI Engine or the official AI plugin — it's a deliberate companion project that lets us:

1. Exercise the new APIs directly with minimal abstraction between you and the framework.
2. Validate how Connectors, Abilities, and the AI Client behave end-to-end on a real site.
3. Ship something useful: a focused, private admin assistant.
4. Stay portable and future-proof — when the AI surface evolves, a tiny plugin adapts quickly.

## Design principles

1. **Native to WP 7.** Every AI call goes through `wp_ai_client_prompt()`; every provider discovery goes through `wp_get_connectors()`.
2. **Built the WordPress AI team way.** PSR-4 namespaces (`Chagency\…`), `declare(strict_types=1)`, class-based organization mirroring the AI plugin. React front-end compiled with `@wordpress/scripts`, styled with `@wordpress/components`.
3. **As simple as possible, then a little simpler.** One chat page. One settings page. One Ability. No feature creep.
4. **Admin-only.** `manage_options` everywhere — REST routes, abilities, both pages.
5. **Provider-agnostic.** Anthropic, Google, OpenAI, anything third-party — all equal, all routed through Connectors.

## What the plugin does

### Out of the box

- **Tools → Chagency** opens a full chat interface using Gutenberg components, so it feels like the rest of the WP 7 admin.
- The system prompt defaults to *"You are a helpful WordPress assistant. Answer questions about WordPress, the user's site, and how to use it. Be concise and accurate."*
- With no Connector configured, a friendly empty state points the user to **Settings → Connectors**.

### Settings → Chagency

Three fields, on one screen:

- **System instruction** — the prompt that defines the assistant. Textarea.
- **Greeting message** — the first message shown in every fresh conversation.
- **Model preference** — `Automatic` by default, pulled from `wp_get_connectors()`; can be pinned to a specific provider.

Below the form, every registered provider gets a **Test** button that sends a one-word canary prompt. Useful for debugging your own Connector plugin or confirming an API key still works.

> Temperature and max-tokens used to be here. They were dropped in v0.3.0 because several modern models ignore them or treat them inconsistently; shipping the knobs would just mislead users.

### Exposed as an Ability

`chagency/send-message` is registered on `wp_abilities_api_init`. Other plugins, MCP clients, and AI agents can call it directly:

```php
$reply = wp_get_ability( 'chagency/send-message' )
    ->execute( array( 'message' => 'Hi!' ) );
// => array( 'reply' => '…' )
```

## File layout

```
chagency/
├── chagency.php                       ← plugin header + Main::get_instance()
├── LICENSE                           ← GPL-2.0 full text
├── readme.txt                        ← wordpress.org readme
├── README.md                         ← this file
├── CLAUDE.md                         ← notes for future agent sessions
├── package.json                      ← @wordpress/scripts + wpPlugin meta
├── .distignore                       ← wp.org zip exclusions
├── .gitignore
├── docs/
│   ├── WP7-AI-FRAMEWORK.md           ← framework reference
│   └── CHANGELOG.md                  ← per-version notes
├── includes/
│   ├── autoload.php                  ← PSR-4 autoloader for Chagency\
│   ├── helpers.php                   ← namespaced helper functions + OPTION_KEY
│   ├── Main.php                      ← singleton bootstrap
│   ├── Requirements.php              ← PHP / WP / framework / build checks
│   ├── Asset_Loader.php              ← enqueue utility (reads *.asset.php)
│   ├── Services/
│   │   └── AI_Service.php            ← singleton around wp_ai_client_prompt()
│   ├── Admin/
│   │   └── Chatbot_Page.php          ← Tools → Chagency (position 1, React mount)
│   ├── Settings/
│   │   ├── Settings_Page.php         ← Settings → Chagency (position 13, React mount)
│   │   └── Settings_Registration.php ← register_setting() with show_in_rest schema
│   ├── REST/
│   │   └── Routes.php                ← /chat, /test, /connectors, /settings
│   └── Abilities/
│       └── Registrar.php             ← chagency/send-message
├── src/
│   ├── index.js                      ← React entrypoint, dispatches on cfg.page
│   ├── Chat.js                       ← fullscreen chat app
│   ├── Settings.js                   ← settings app (Card / VStack / TextareaControl / Button)
│   ├── EmptyState.js                 ← no-provider empty state
│   ├── rest.js                       ← tiny REST helper
│   └── style.css                     ← small amount of custom CSS
├── build/                            ← compiled by `pnpm build` (committed)
│   ├── index.js
│   ├── index.asset.php
│   └── style-index.css (+ rtl)
└── languages/
    └── chagency.pot
```

## Developer workflow

```bash
# one-time setup
pnpm install

# build for release
pnpm run build

# live-rebuild while editing JS/CSS
pnpm run start

# lint
pnpm run lint:js
pnpm run lint:css
```

`build/` is the only artifact PHP loads — it is committed to the repo so the plugin runs out of the box on wordpress.org installs without requiring users to run a build step.

### Testing the framework

This plugin is a good place to verify how the WP 7 AI APIs behave:

1. With **no Connectors** — the empty state renders with a link to Settings → Connectors.
2. With **one Connector** — prompts route through it; the Test button succeeds.
3. With **multiple Connectors** — the model-preference dropdown lists every configured provider; Automatic mode uses the default preference order.
4. Alongside **AI Engine** or the official **AI plugin** — all three coexist because they all speak the same framework.

### Conventions

- **PHP** — PSR-12 loose-ish, 2-space indentation, `declare(strict_types=1)`, classes under `Chagency\…`, `aichat_` prefix only for the stored option key.
- **JavaScript** — ES modules, JSX, compiled by `@wordpress/scripts`. No runtime dependencies outside Gutenberg globals.
- **Text domain** — `chagency` (matches the plugin slug).

## Requirements

- WordPress **7.0+** (uses `wp_ai_client_prompt()`, `wp_get_connectors()`, Abilities API).
- PHP **7.4+**.
- At least one Connector installed and configured — the chatbot tells you if there is none.

### 💡 Recommended companion: AI Engine

For the smoothest setup, install [**AI Engine**](https://wordpress.org/plugins/ai-engine/). AI Engine registers every AI provider it manages (OpenAI, Anthropic, Google, Mistral, Anthropic-compatible endpoints, local models, …) as a WordPress 7 Connector — so a single configuration covers Chagency *and* every other plugin built on `wp_get_connectors()`. Every request then flows through one clean, unified provider system instead of duplicate API keys scattered across one Connector plugin per vendor.

Chagency itself does not depend on AI Engine in any way — it works with the official `AI Provider for Anthropic` / `AI Provider for OpenAI` / etc. plugins just as well. AI Engine is just the most ergonomic option if you already manage AI on the site.

## Status

**v0.0.1 — initial release, pending wordpress.org review.** See [`docs/CHANGELOG.md`](docs/CHANGELOG.md) for the per-version history, [`docs/WP7-AI-FRAMEWORK.md`](docs/WP7-AI-FRAMEWORK.md) for the framework crib sheet, and [`CLAUDE.md`](CLAUDE.md) for the rules that keep this plugin from growing heavy.

Build small. Keep it honest. Delete first.
