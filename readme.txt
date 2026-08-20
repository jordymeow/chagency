=== Chagency ===
Contributors: TigrouMeow
Tags: ai, chatbot, agent, connectors, abilities
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 0.0.5
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A chatbot built natively on the WordPress AI Client, and an agent through the Abilities API. No bundled SDKs, no telemetry.

== Description ==

**Chagency** is built directly on the AI Client that shipped in WordPress 7.0: `wp_ai_client_prompt()`, Connectors, and the Abilities API. Every prompt flows through WordPress core, every provider is a Connector you already configured under **Settings → Connectors**, and the assistant can call the abilities your site registers.

That last part is what makes it an agent rather than a chat box. Tick the abilities you want it to reach, and the model can use them mid-conversation to answer you: WordPress converts each one into a tool the model understands, runs it, and checks that ability's own permissions first. Ask "what's my site running on?" and it goes and looks, instead of guessing.

It runs in the WordPress admin and, when you turn it on, on every page of your public site. The name (chat + ai + ency) is meant as a small bridge between the new AI primitives shipped with WordPress and the broader world of chat and agents.

== 🪨 Built on WordPress, period ==

Chagency relies entirely on what WordPress 7 ships in core:

* `wp_ai_client_prompt()`, the AI Client API.
* `using_abilities()` and `WP_AI_Client_Ability_Function_Resolver`, core's own tool-calling bridge.
* `wp_get_connectors()`, the Connectors API.
* `wp_register_ability()` / `wp_get_ability()` / `wp_get_abilities()`, the Abilities API.
* `@wordpress/components`, `@wordpress/element`, `@wordpress/boot`, the Gutenberg toolkit.

That is the entire dependency surface. **No bundled AI vendor SDKs, no third-party JavaScript libraries beyond the Gutenberg stack, no telemetry, no phone-home.** The plugin stays small on purpose: fewer moving parts means less friction, fewer security holes, less to break when WordPress, browsers, or providers change.

You will need at least one AI provider plugin (e.g. *AI Provider for Anthropic*, *AI Provider for Google*, *AI Provider for OpenAI*) to talk to a model. That is WordPress 7's architecture: providers are separate plugins that register a Connector. They are not a Chagency dependency.

💡 **Tip:** For the smoothest setup, install [AI Engine](https://wordpress.org/plugins/ai-engine/). It registers every AI provider it manages as a Connector, so a single configuration covers all your WordPress 7 AI plugins (including Chagency). Every request then flows through one clean, unified system.

== 🪄 What you get ==

* A floating chat panel pinned to the bottom-right of every page (admin and / or public site, controlled by two independent toggles).
* A single settings page under **Settings → Chagency**, with the bare minimum: where to show the chatbot, the chat title, the greeting, the system instruction, the model preference, and which abilities the assistant may use.
* Abilities, opt-in one by one. Every ability registered on your site (by core, by Chagency, by any other plugin) can be handed to the assistant, and each call is shown under the reply so you can see what it actually did.
* Live updates: flipping a toggle in Settings shows or hides the launcher immediately on the same page, no reload needed.
* Per-provider **Test** buttons that fire a canary prompt and report round-trip time.
* Conversation persistence in your browser's localStorage, scoped per user.
* Failures explained in plain words (rejected key, rate limit, provider down, conversation too long) with a *Try again* button, so a hiccup never costs you what you typed.
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

Yes. It uses `wp_ai_client_prompt()`, `wp_get_connectors()`, and the Abilities API, all added in WordPress 7.0. On older versions it refuses to boot and tells you why. WordPress 7.1 is recommended.

= How do abilities work, and is it safe? =

Open **Settings → Chagency**, turn on *Let the assistant use abilities*, then tick the ones you want to allow. Nothing is allowed by default.

Three things keep it tight. Only the abilities you ticked are ever offered to the model. WordPress runs each ability itself and checks that ability's own `permission_callback` for the person chatting, so the assistant can never do more than that user could. And abilities are admin-only: the public widget never gets them, even when you turn the public site on.

Each reply lists the abilities it used, so nothing happens off-screen.

= Which abilities can it use? =

Whatever is registered on your site. WordPress ships `core/get-site-info`, `core/get-user-info` and `core/get-environment-info`, and any plugin can register more. They all show up in the list.

= Which AI providers are supported? =

Whatever you configure under **Settings → Connectors**. Chagency is provider-agnostic. Anthropic, Google, OpenAI, or any third-party Connector are treated equally.

= Can visitors on the front end use it? =

Yes. Open **Settings → Chagency** and turn on *Show on the public site*. The chat then appears for every visitor, signed in or not.

= Does it store my conversations? =

No. Conversations live only in your own browser (localStorage, per user). Hit *Reset* in the panel to clear them. Nothing is stored server-side.

= Can I use it as a building block in my own plugin? =

Yes. The `chagency/send-message` Ability is registered on `wp_abilities_api_init` and can be called with `wp_get_ability( 'chagency/send-message' )->execute( array( 'message' => '...' ) )`.

== Screenshots ==

1. The floating chat panel answering a question on a WordPress admin screen, with the native WP 7 look.
2. The single settings screen under **Settings → Chagency**: where to show the chatbot, its title, greeting, system instruction, and model.
3. Flip one toggle and the same chat panel runs on the public side of your site, for every visitor.

== Source code ==

The unminified React / JavaScript source is shipped inside the plugin folder under `src/`, alongside the compiled output in `build/`. Public source repository: https://github.com/jordymeow/chagency

To rebuild from source:

`pnpm install && pnpm run build`

The build is `@wordpress/scripts` (webpack) with no custom plugins or transforms.

== Changelog ==

= 0.0.5 (2026/08/20) =
* Add: Abilities support — the assistant can use the ones you allow and shows which it called.
* Add: Try again button on failed messages, with provider errors explained in plain language.
* Update: Aligned with WordPress 7.1, including the new public ability meta and application-password connectors.
* Update: Limit how much of a long conversation is replayed to the model.
* Add: Test harness that checks the AI plumbing without a database.
* 🌴 Keep us motivated with [a little review here](https://wordpress.org/support/plugin/chagency/reviews/). Thank you!

= 0.0.4 (2026/06/03) =
* Fix: Chat launcher now appears when the AI provider key is set via an environment variable or constant, not only when saved through the settings page.

= 0.0.3 (2026/05/22) =
* Update: Rebuilt plugin against the WP 7 AI plugin toolchain.
* Update: Sharpened wordpress.org positioning.

= 0.0.2 (2026/05/15) =
* Add: option to show the chat widget on the public site for all visitors
* Add: configurable chat title in the Settings page
* Add: chat panel stays open when navigating between admin pages
* Update: launcher, chat panel, and Settings page styled to match the WP 7 AI plugin
* 🌴 Keep us motivated with [a little review here](https://wordpress.org/support/plugin/chagency/reviews/). Thank you!

= 0.0.1 =
* Initial release.
