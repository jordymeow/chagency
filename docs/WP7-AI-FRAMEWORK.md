# WordPress 7 AI Framework, working notes

A condensed, plugin-author-facing reference to the APIs this plugin is built on. Verified against **WordPress 7.0-RC2**.

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
        'meta'                => [ 'show_in_rest' => true ],
    ] );
} );
```

Invoke anywhere: `wp_get_ability( 'chagency/send-message' )->execute( [ 'message' => 'Hi' ] )`.

Abilities are also exposed over REST when `meta.show_in_rest` is true, and can be declared as tool calls on a prompt via `$builder->using_abilities( 'chagency/send-message' )`.

---

## End-to-end flow (what the Chagency plugin does)

1. User types a message in `/wp-admin/tools.php?page=chagency`.
2. Browser POSTs `{ messages: [...] }` to `/wp-json/chagency/v1/chat` with the REST nonce.
3. `aichat_rest_chat()` splits history / current message, loads settings, builds the prompt with `wp_ai_client_prompt()`.
4. It calls `is_supported_for_text_generation()` → returns 503 if no provider is ready.
5. `generate_text()` → reply string or `WP_Error`.
6. Browser receives `{ reply }` and paints it as an assistant bubble.

`/chagency/v1/test` is the same flow with a canary prompt pinned to one provider, used by the "Test" buttons on **Settings → the Chagency plugin**.

---

## Gotchas discovered while building

* `using_provider( $id )` requires the provider to be **registered in the AI Client registry**, not just in the Connectors registry. In practice the provider-plugin (e.g. `ai-provider-for-anthropic`) does both. Always guard a test call with `is_supported_for_text_generation()` first.
* Every fluent method on the builder swallows exceptions and flags the builder as errored. Chaining many fluent calls is safe, the first generator call will surface the error. But don't assume `is_supported_for_text_generation()` is free of side effects: if a prior fluent call errored, it returns `false`.
* `with_history( ...$msgs )` uses **spread**, not an array. `with_history( $array )` silently does the wrong thing.
* Ability names must be lowercase, alphanumeric + hyphen + slash. `AI-Chat/SendMessage` gets rejected with `_doing_it_wrong`.
* `register_setting()` with `type: 'object'` + `show_in_rest: false` is fine when you don't want the option exposed over REST. If you flip `show_in_rest` to `true`, define a full `schema` or core will reject updates.

---

## References in core

* `wp-includes/ai-client.php`, `wp_ai_client_prompt()`, `wp_supports_ai()`
* `wp-includes/ai-client/class-wp-ai-client-prompt-builder.php`, fluent builder (all the `@method` annotations list the SDK surface)
* `wp-includes/connectors.php`, `wp_get_connectors()`, defaults, key masking
* `wp-includes/class-wp-connector-registry.php`, registry internals
* `wp-includes/abilities-api.php`, `wp_register_ability()`, category registration, REST exposure
* `wp-includes/php-ai-client/src/`, the bundled SDK (`Messages`, `Providers`, `Builders`, `Tools`)
