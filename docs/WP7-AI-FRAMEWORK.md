# WordPress 7 AI Framework, working notes

A condensed, plugin-author-facing reference to the APIs this plugin is built on. Verified against **WordPress 7.1** (released 2026-08-19).

This file is not a substitute for core docs, it's a distilled crib sheet for anyone maintaining the Chagency plugin.

---

## The three APIs

### 1. AI Client, `wp_ai_client_prompt()`

`wp-includes/ai-client.php`. Entry point:

```php
$builder = wp_ai_client_prompt( 'What's a block theme?' );
```

Returns a `WP_AI_Client_Prompt_Builder`. It wraps the bundled PHP AI Client SDK and adds:

* **snake_case method names** (`using_system_instruction()` → `usingSystemInstruction()` on the SDK builder).
* **`WP_Error` instead of exceptions.** Non-generator methods absorb errors and become no-ops until the next generator call, which returns the stored `WP_Error`.
* **Integration with the Abilities API** via `using_abilities( ...$abilityOrName )`.

Most common methods:

| Method | Purpose |
|---|---|
| `using_system_instruction( $s )` | set the system prompt |
| `using_temperature( $t )` | 0.0 deterministic → 2.0 chaotic |
| `using_max_tokens( $n )` | cap the reply length |
| `using_model_preference( ...$pairs )` | `['provider','model']` tuples, tried in order |
| `using_provider( $id )` | pin to a specific Connector ID |
| `with_history( ...$messages )` | pass prior turns as `Message` DTOs |
| `using_abilities( ...$abilities )` | expose abilities as tool calls |
| `is_supported_for_text_generation()` | cheap reachability check |
| `generate_text()` | → `string\|WP_Error` |
| `generate_text_result()` | → full `GenerativeAiResult\|WP_Error` (includes token counts, finish reason) |

**Messages & history.** Current turn goes into the constructor. Prior turns go through `with_history()`:

```php
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;

$history = [
    new Message( MessageRoleEnum::user(),  [ new MessagePart( 'Hi there' ) ] ),
    new Message( MessageRoleEnum::model(), [ new MessagePart( 'Hello! How can I help?' ) ] ),
];

$reply = wp_ai_client_prompt( 'Tell me a joke.' )
    ->with_history( ...$history )
    ->using_temperature( 0.7 )
    ->generate_text();
```

Note the role enum is `model()`, **not** `assistant()`. The REST layer in `includes/rest.php` translates the external `"assistant"` wire format to `MessageRoleEnum::model()`.

**Errors.** A `WP_Error` from `generate_text()` carries a `status` code in its data (e.g. 400 for `prompt_invalid_argument`, 503 for `prompt_network_error`). Safe to return directly from a REST callback.

### 2. Connectors, `wp_get_connectors()`

`wp-includes/connectors.php`. Returns every registered provider, keyed by ID:

```php
wp_get_connectors()[ 'anthropic' ] === [
    'name'           => 'Anthropic',
    'description'    => 'Text generation with Claude.',
    'logo_url'       => '…',
    'type'           => 'ai_provider',
    'authentication' => [
        'method'          => 'api_key',
        'credentials_url' => 'https://platform.claude.com/settings/keys',
        'setting_name'    => 'connectors_ai_anthropic_api_key',
    ],
    'plugin' => [ 'slug' => 'ai-provider-for-anthropic' ],
];
```

**A connector being registered does not mean it is configured.** Read `get_option( $auth['setting_name'] )` to check (the helper `aichat_has_credentials()` does this). For `method: 'none'` providers, assume configured.

Keys stored in `get_option()` are validated on write by core (`_wp_connectors_rest_settings_dispatch`); a bad key is wiped. On read, core masks keys in REST responses.

### 3. Abilities, `wp_register_ability()`

`wp-includes/abilities-api.php`. Register on `wp_abilities_api_init`; register categories on `wp_abilities_api_categories_init`:

```php
add_action( 'wp_abilities_api_init', function () {
    wp_register_ability( 'chagency/send-message', [
        'label'               => 'Send a message',
        'description'         => '…',
        'category'            => 'chagency',
        'input_schema'        => [ /* JSON Schema */ ],
        'output_schema'       => [ /* JSON Schema */ ],
        'execute_callback'    => 'aichat_ability_send_message',
        'permission_callback' => fn() => current_user_can( 'manage_options' ),
        'meta'                => [ 'public' => true, 'show_in_rest' => true ],
    ] );
} );
```

Invoke anywhere: `wp_get_ability( 'chagency/send-message' )->execute( [ 'message' => 'Hi' ] )`.

Since 7.1, `meta.public` is the high-level flag: it means "this ability is meant for clients" (REST, MCP, AI agents) and seeds every per-channel flag, `show_in_rest` included. Set a channel flag explicitly to override it. `public` does not exist in 7.0, so set both while 7.0 is supported.

7.1 also added a filter at every step of `WP_Ability::execute()`: `wp_ability_invoked` (action, fires first, always), `wp_pre_execute_ability` (short-circuit), `wp_ability_normalize_input`, `wp_ability_validate_input`, `wp_ability_permission_result`, `wp_ability_execute_result` and `wp_ability_validate_output`. Together they are how a plugin logs, rate-limits or overrides abilities it does not own. `wp_get_abilities()` also takes `$args` now (`category`, `namespace`, `meta`, `item_include_callback`, `result_callback`).

Core registers three abilities of its own, all read-only and gated on `manage_options`: `core/get-site-info`, `core/get-user-info`, `core/get-environment-info`.

---

### 4. Tool calling, abilities as functions

This is the bridge that turns a chatbot into an agent, and core owns all of it.

```php
$builder = wp_ai_client_prompt( $messages )   // list<Message> = the whole conversation
    ->using_system_instruction( $system )
    ->using_abilities( 'core/get-site-info' ); // → FunctionDeclaration per ability

$resolver = new WP_AI_Client_Ability_Function_Resolver( 'core/get-site-info' );

$result  = $builder->generate_text_result();   // GenerativeAiResult|WP_Error
$message = $result->toMessage();               // may hold text AND function calls

if ( $resolver->has_ability_calls( $message ) ) {
    $responses  = $resolver->execute_abilities( $message ); // UserMessage of FunctionResponses
    $messages[] = $message;
    $messages[] = $responses;
    // …loop: rebuild the prompt with the longer message list and generate again.
}
```

What core guarantees:

* Only abilities passed to the resolver constructor can run. A call to anything else comes back as an error `FunctionResponse`, never as an execution.
* `execute_abilities()` goes through `WP_Ability::execute()`, which runs `check_permissions()` for the **current user** first. An ability the user may not run returns `ability_invalid_permissions` to the model, which then explains it.
* Ability names map to function names as `core/get-site-info` ⇄ `wpab__core__get-site-info` (`ability_name_to_function_name()` / `function_name_to_ability_name()`).
* In 7.1 input schemas are passed through `wp_prepare_json_schema_for_client()` (new `wp-includes/json-schema.php`), which strips keywords a model's function-calling dialect cannot parse.

What core does **not** do: the loop itself, a round limit, or any transcript. That is the plugin's job, see `AI_Service::generate_chat_reply()`.

**Not in 7.1, despite the previews:** generation streaming and embedding generation. The SDK only exposes `is_supported_for_embedding_generation()`; there is no `generate_embedding*()` and no streaming entry point anywhere in `wp-includes/php-ai-client`.

---

## End-to-end flow (what the Chagency plugin does)

1. User types a message in the floating widget.
2. Browser POSTs `{ messages: [...], page_context: {…} }` to `/wp-json/chagency/v1/chat` with the REST nonce.
3. `Routes::handle_chat()` normalises the turns, expands system-instruction placeholders, and asks `agent_abilities()` which abilities this caller may use (empty unless the feature is on and the user can `manage_options`).
4. `AI_Service::generate_chat_reply()` builds the prompt, checks `is_supported_for_text_generation()` (503 if no provider is ready) and generates.
5. While the model asks for abilities, the resolver runs them and the loop generates again, up to `MAX_ABILITY_ROUNDS`.
6. Browser receives `{ reply, steps }` and paints the bubble plus one chip per ability call.

`/chagency/v1/test` is the same flow with a canary prompt pinned to one provider, used by the "Test" buttons on **Settings → the Chagency plugin**.

---

## Gotchas discovered while building

* `using_provider( $id )` requires the provider to be **registered in the AI Client registry**, not just in the Connectors registry. In practice the provider-plugin (e.g. `ai-provider-for-anthropic`) does both. Always guard a test call with `is_supported_for_text_generation()` first.
* Every fluent method on the builder swallows exceptions and flags the builder as errored. Chaining many fluent calls is safe, the first generator call will surface the error. But don't assume `is_supported_for_text_generation()` is free of side effects: if a prior fluent call errored, it returns `false`.
* `with_history( ...$msgs )` uses **spread**, not an array. `with_history( $array )` silently does the wrong thing.
* Ability names must be lowercase, alphanumeric + hyphen + slash. `AI-Chat/SendMessage` gets rejected with `_doing_it_wrong`.
* `register_setting()` with `type: 'object'` + `show_in_rest: false` is fine when you don't want the option exposed over REST. If you flip `show_in_rest` to `true`, define a full `schema` or core will reject updates.
* `GenerativeAiResult::toText()` throws when the candidate carries no text part. A turn that is pure function calls is exactly that case, so read `toMessage()` and walk the parts instead.
* `wp_ai_client_prompt()` treats its argument as the full conversation **only** when it is a non-empty list where every item is a `Message`. One stray array or string and the whole thing is parsed as a single user message instead.
* `ProviderRegistry::isProviderConfigured()` reads like a cheap lookup and is not. It calls `$provider::availability()->isConfigured()`, which is either an HTTP GET on the models endpoint (cached a day) or an actual billed `generateTextResult()` call with `max_tokens: 1` (not cached). It is fine on a settings screen, ruinous on anything that runs per page load. Work out credentials from the connector's declared `env_var_name` / `constant_name` / `setting_name` instead.
* 7.1 added the `application_password` connector auth method. Its credentials live as a `username:password` string (env/constant) or an array (option), and `wp_connectors_get_application_password_credentials()` resolves all three sources.

---

## References in core

* `wp-includes/ai-client.php`, `wp_ai_client_prompt()`, `wp_supports_ai()`
* `wp-includes/ai-client/class-wp-ai-client-prompt-builder.php`, fluent builder (all the `@method` annotations list the SDK surface)
* `wp-includes/connectors.php`, `wp_get_connectors()`, defaults, key masking
* `wp-includes/class-wp-connector-registry.php`, registry internals
* `wp-includes/abilities-api.php`, `wp_register_ability()`, `wp_get_abilities( $args )`, category registration, client exposure
* `wp-includes/abilities-api/class-wp-ability.php`, the execute pipeline and its 7.1 filters
* `wp-includes/abilities.php`, core's own three abilities, a good template for schema style
* `wp-includes/ai-client/class-wp-ai-client-ability-function-resolver.php`, ability tool calling
* `wp-includes/json-schema.php`, `wp_prepare_json_schema_for_client()` (7.1)
* `wp-includes/php-ai-client/src/`, the bundled SDK (`Messages`, `Providers`, `Builders`, `Tools`)
