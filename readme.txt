=== Chagency ===
Contributors: TigrouMeow
Tags: ai, chatbot, agent, connectors, abilities
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 0.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The first chatbot built natively on the WordPress 7 AI Client. No bundled SDKs, no third-party JavaScript, no telemetry.

== Description ==

**Chagency** is the first chatbot to ship directly on top of the new AI Client introduced in WordPress 7.0 — `wp_ai_client_prompt()`, Connectors, and the Abilities API. Every prompt flows through WordPress core, every provider is a Connector you already configured under **Settings → Connectors**, and every action it exposes is a registered Ability that other plugins (and other AI agents) can call.

There is no other plugin on wordpress.org that builds on `wp_ai_client_prompt()` today. Chagency is the reference case: a small, native chatbot that proves the framework works, ships zero vendor SDKs, and grows alongside the Abilities API into a real agent.

It runs in the WordPress admin and, when you turn it on, on every page of your public site. The name (chat + ai + ency) is meant as a small bridge between the new AI primitives shipped with WordPress and the broader world of chat and agents. It starts as a chatbot today and turns into an agent tomorrow, without a rename.

== 🪨 Built on WordPress, period ==

Chagency relies entirely on what WordPress 7 ships in core:

* `wp_ai_client_prompt()`, the AI Client API.
* `wp_get_connectors()`, the Connectors API.
* `wp_register_ability()` / `wp_get_ability()`, the Abilities API.
* `@wordpress/components`, `@wordpress/element`, `@wordpress/boot`, the Gutenberg toolkit.

That is the entire dependency surface. **No bundled AI vendor SDKs, no third-party JavaScript libraries beyond the Gutenberg stack, no telemetry, no phone-home.** The plugin stays small on purpose: fewer moving parts means less friction, fewer security holes, less to break when WordPress, browsers, or providers change.

You will need at least one AI provider plugin (e.g. *AI Provider for Anthropic*, *AI Provider for Google*, *AI Provider for OpenAI*) to talk to a model. That is WordPress 7's architecture: providers are separate plugins that register a Connector. They are not a Chagency dependency.

💡 **Tip:** For the smoothest setup, install [AI Engine](https://wordpress.org/plugins/ai-engine/). It registers every AI provider it manages as a Connector, so a single configuration covers all your WordPress 7 AI plugins (including Chagency). Every request then flows through one clean, unified system.

== 🪄 What you get ==

* A floating chat panel pinned to the bottom-right of every page (admin and / or public site, controlled by two independent toggles).
* A single settings page under **Settings → Chagency**, with the bare minimum: where to show the chatbot, the chat title, the greeting, the system instruction, and the model preference.
* Live updates: flipping a toggle in Settings shows or hides the launcher immediately on the same page, no reload needed.
* Per-provider **Test** buttons that fire a canary prompt and report round-trip time.
* Conversation persistence in your browser's localStorage, scoped per user.
* Placeholder expansion (`{user_name}`, `{site_name}`, `{current_page}`, `{current_url}`, `{user_role}`, `{site_url}`) so the assistant always knows who and where it is.
* Exposed as an Ability (`chagency/send-message`) so other plugins, MCP clients, and AI agents can invoke Chagency directly.

== 🚀 Getting started ==

1. Install and activate **Chagency**.
2. Install and configure at least one AI provider plugin under **Settings → Connectors** (we recommend [AI Engine](https://wordpress.org/plugins/ai-engine/), which exposes all its providers as Connectors at once).
3. Click the chat launcher in the bottom-right of any admin page and start talking.
4. Want it on the public site too? Open **Settings → Chagency** and flip the second toggle.

== 🙋 Frequently Asked Questions ==

= Why "Chagency"? =

It's a portmanteau of *chat*, *AI*, and a bit of *agent*. Not "agency" in the sense of a marketing shop. The plugin starts as a minimal chatbot for the new WordPress 7 AI framework and grows alongside the Abilities API into a true agent.

= Does it need WordPress 7? =

Yes. It uses `wp_ai_client_prompt()`, `wp_get_connectors()`, and the Abilities API, all added in WordPress 7.0. On older versions it refuses to boot and tells you why.

= Which AI providers are supported? =

Whatever you configure under **Settings → Connectors**. Chagency is provider-agnostic. Anthropic, Google, OpenAI, or any third-party Connector are treated equally.

= Can visitors on the front end use it? =

Yes. Open **Settings → Chagency** and turn on *Show on the public site*. The chat then appears for every visitor, signed in or not.

= Does it store my conversations? =

No. Conversations live only in your own browser (localStorage, per user). Hit *Reset* in the panel to clear them. Nothing is stored server-side.

= Can I use it as a building block in my own plugin? =

Yes. The `chagency/send-message` Ability is registered on `wp_abilities_api_init` and can be called with `wp_get_ability( 'chagency/send-message' )->execute( array( 'message' => '...' ) )`.

== Screenshots ==

1. The floating chat panel open on an admin page, native WP 7 Gutenberg look.
2. The settings screen under **Settings → Chagency**, with per-provider *Test* buttons.

== Source code ==

The unminified React / JavaScript source is shipped inside the plugin folder under `src/`, alongside the compiled output in `build/`. Public source repository: https://github.com/jordymeow/chagency

To rebuild from source:

`pnpm install && pnpm run build`

The build is `@wordpress/scripts` (webpack) with no custom plugins or transforms.

== Changelog ==

= 0.0.2 (2026/05/15) =
* Add: option to show the chat widget on the public site for all visitors
* Add: configurable chat title in the Settings page
* Add: chat panel stays open when navigating between admin pages
* Update: launcher, chat panel, and Settings page styled to match the WP 7 AI plugin
* 🌴 Keep us motivated with [a little review here](https://wordpress.org/support/plugin/chagency/reviews/). Thank you!

= 0.0.1 =
* Initial release.
