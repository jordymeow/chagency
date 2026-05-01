=== Chagency ===
Contributors: TigrouMeow
Tags: ai, chatbot, agent, assistant, abilities
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 0.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A chatbot today, an agent tomorrow — built natively on the WordPress 7 AI framework, with zero third-party plugin requirements.

== Description ==

**Chagency** — a portmanteau of *chat* and *agency* — is a deliberately small WordPress plugin that lets you have a conversation with your site through whichever AI provider you've configured. Today it's a chatbot. As the WordPress AI framework matures, it grows into a true agent — one you talk to in the admin (and eventually on the front-end too) to either *discuss* or *perform actions*.

The name covers that arc on purpose: chat now, agency later, no rename needed.

== 🪨 Built on WordPress, period ==

Chagency relies entirely on what WordPress 7 ships in core:

* `wp_ai_client_prompt()` — the AI Client API.
* `wp_get_connectors()` — the Connectors API.
* `wp_register_ability()` / `wp_get_ability()` — the Abilities API.
* `@wordpress/components`, `@wordpress/element`, `@wordpress/boot` — the Gutenberg toolkit.

That's it. **No bundled AI vendor SDKs, no third-party JavaScript libraries beyond the Gutenberg stack, no telemetry, no phone-home.** The plugin's surface area is small on purpose — fewer moving parts means less friction, fewer security holes, less to break when WordPress, browsers, or providers change.

You will need at least one AI provider plugin (e.g. *AI Provider for Anthropic*, *AI Provider for Google*, or *AI Provider for OpenAI*) to talk to a model. That's WordPress 7's architecture — providers are separate plugins that register a Connector, not a Chagency dependency.

== 🪄 What you get ==

* A floating chat panel pinned to the bottom-right of every admin page — one click, start typing.
* A single settings page under **Settings → Chagency** (right below **Settings → Connectors**) — enable toggle, greeting, system instruction, model preference. That's it.
* Live on/off: flipping the enable toggle shows or hides the launcher immediately, no page reload.
* Per-provider **Test** buttons that fire a canary prompt and report round-trip time.
* Conversation persistence in your browser's localStorage, scoped per user. Hit **Reset** to clear.
* Placeholder expansion (`{user_name}`, `{site_name}`, `{current_page}`, …) so the assistant always knows who and where it is.
* Exposed as an Ability — `chagency/send-message` — so other plugins, MCP clients, and AI agents can invoke Chagency directly.

== 🚀 Getting started ==

1. Install and activate **Chagency**.
2. Install and configure at least one AI provider plugin under **Settings → Connectors**.
3. Click the **Chagency** launcher in the bottom-right of any admin page and start talking.

Tune defaults under **Settings → Chagency**. Everything is reversible; nothing is precious.

== 🙋 Frequently Asked Questions ==

= Why "Chagency"? =

*Chat* + *agency* = Chagency. The plugin starts life as a minimal chatbot for the new WordPress 7 AI framework, but as the Abilities API matures it becomes an agent that can take actions on your behalf. The name covers both phases without a future rename.

= Does it need WordPress 7? =

Yes. It uses `wp_ai_client_prompt()`, `wp_get_connectors()`, and the Abilities API — all added in WordPress 7.0. On older versions it refuses to boot and tells you why.

= Which AI providers are supported? =

Whatever you configure under **Settings → Connectors**. Chagency is provider-agnostic — Anthropic, Google, OpenAI, or any third-party Connector are treated equally.

= Can visitors on the front end use it? =

Not yet. Today it's admin-only and requires `manage_options`. A front-end build is on the roadmap and will arrive once the agent capabilities are mature enough to expose safely.

= Does it store my conversations? =

No. Conversations live only in your own browser (localStorage, per-user). Hit **Reset** in the widget to clear them. Nothing is stored server-side.

= Can I use it as a building block in my own plugin? =

Yes. The `chagency/send-message` Ability is registered on `wp_abilities_api_init` and can be called with `wp_get_ability( 'chagency/send-message' )->execute( array( 'message' => '…' ) )`.

== Screenshots ==

1. The floating chat panel open on any admin page — native WP 7 Gutenberg look.
2. The settings screen under **Settings → Chagency**, with per-provider *Test* buttons.

== Source code ==

The unminified React/JavaScript source is shipped inside the plugin folder under `src/`, alongside the compiled output in `build/`. Public source repository: https://github.com/jordymeow/chagency

To rebuild from source:

`pnpm install && pnpm run build`

The build is `@wordpress/scripts` (webpack) with no custom plugins or transforms.

== Changelog ==

= 0.0.1 =
* Initial release.
