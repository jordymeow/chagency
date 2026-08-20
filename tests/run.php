<?php
/**
 * Chagency test harness.
 *
 * There is no PHPUnit here on purpose: the plugin is small, and the parts most
 * likely to break silently are the ones that talk to WordPress 7's AI Client
 * DTOs. So this boots the *real* bundled SDK out of a WordPress install, stubs
 * the handful of WordPress functions the plugin touches, and asserts against
 * actual objects rather than mocks.
 *
 * Usage:
 *
 *     php tests/run.php                       # uses the default WordPress path
 *     WP_PATH=/path/to/wordpress php tests/run.php
 *
 * The WordPress install only needs to be on disk. No database, no web server.
 *
 * @package Chagency
 */

namespace {
	$wp_path = getenv( 'WP_PATH' );
	if ( ! $wp_path ) {
		$wp_path = getenv( 'HOME' ) . '/sites/seven/app/public';
	}
	$wp = rtrim( $wp_path, '/' ) . '/wp-includes';

	if ( ! file_exists( $wp . '/php-ai-client/autoload.php' ) ) {
		fwrite( STDERR, "No WordPress 7 install at {$wp_path}.\nSet WP_PATH to one that has wp-includes/php-ai-client.\n" );
		exit( 2 );
	}

	$plugin = dirname( __DIR__ );

	define( 'ABSPATH', rtrim( $wp_path, '/' ) . '/' );
	require_once $wp . '/php-ai-client/autoload.php';

	// --- WordPress stubs ---------------------------------------------------
	function __( $text, $domain = null ) { return $text; }
	function esc_html( $text ) { return $text; }
	function _doing_it_wrong( ...$args ) {}
	function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
	function sanitize_textarea_field( $text ) { return trim( strip_tags( (string) $text ) ); }
	function get_option( $name, $default_value = '' ) { return $GLOBALS['options'][ $name ] ?? $default_value; }
	function get_bloginfo( $key ) { return 'Test Site'; }
	function home_url() { return 'https://example.test'; }
	function wp_get_current_user() { return null; }
	function get_current_user_id() { return 0; }
	function current_user_can( $capability ) { return $GLOBALS['can'] ?? false; }
	function wp_get_connectors() { return $GLOBALS['connectors'] ?? array(); }
	function wp_get_abilities() { return $GLOBALS['abilities'] ?? array(); }
	function wp_get_ability( string $name ) { return $GLOBALS['abilities'][ $name ] ?? null; }
	function wp_has_ability( string $name ) { return isset( $GLOBALS['abilities'][ $name ] ); }
	function wp_get_ability_categories() { return $GLOBALS['ability_categories'] ?? array(); }

	class WP_Error {
		public $code; public $message; public $data;
		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code = $code; $this->message = $message; $this->data = $data;
		}
		public function get_error_message() { return $this->message; }
		public function get_error_code() { return $this->code; }
		public function get_error_data() { return $this->data; }
	}
	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

	/** Stands in for core's WP_Ability. The resolver only needs these three. */
	class WP_Ability {
		private string $name; private array $meta; private $result;
		public function __construct( string $name, $result, array $meta = array() ) {
			$this->name = $name; $this->result = $result; $this->meta = $meta;
		}
		public function get_name(): string { return $this->name; }
		public function get_label(): string { return ucfirst( str_replace( array( '/', '-' ), ' ', $this->name ) ); }
		public function get_description(): string { return 'Description of ' . $this->name; }
		public function get_category(): string { return explode( '/', $this->name )[0]; }
		public function get_meta_item( string $key, $default_value = null ) { return $this->meta[ $key ] ?? $default_value; }
		public function execute( $input = null ) { return $this->result; }
	}

	class Ability_Category_Stub {
		private string $label;
		public function __construct( string $label ) { $this->label = $label; }
		public function get_label(): string { return $this->label; }
	}

	$GLOBALS['options']            = array();
	$GLOBALS['connectors']         = array();
	$GLOBALS['abilities']          = array();
	$GLOBALS['ability_categories'] = array();
	$GLOBALS['can']                = false;

	require_once $wp . '/ai-client/class-wp-ai-client-ability-function-resolver.php';
	require_once $plugin . '/includes/helpers.php';
	require_once $plugin . '/includes/Services/AI_Service.php';

	// --- tiny assertion helpers -------------------------------------------
	$ok = 0; $fail = 0;
	function check( string $label, $actual, $expected ) {
		global $ok, $fail;
		$pass = $actual === $expected;
		$pass ? $ok++ : $fail++;
		printf( "%s %s\n", $pass ? 'ok  ' : 'FAIL', $label );
		if ( ! $pass ) {
			echo '     expected: ' . var_export( $expected, true ) . "\n";
			echo '     actual:   ' . var_export( $actual, true ) . "\n";
		}
	}
	function section( string $title ) { echo "\n# {$title}\n"; }

	$service = new ReflectionClass( \Chagency\Services\AI_Service::class );
	$call    = static function ( string $method, ...$args ) use ( $service ) {
		$m = $service->getMethod( $method );
		$m->setAccessible( true );
		return $m->invoke( null, ...$args );
	};

	// ======================================================================
	section( 'Ability tool calling, against the real AI Client DTOs' );

	$GLOBALS['abilities']['core/get-site-info'] = new WP_Ability( 'core/get-site-info', array( 'name' => 'Test Site' ), array( 'annotations' => array( 'readonly' => true ) ) );
	$GLOBALS['abilities']['core/get-user-info'] = new WP_Ability( 'core/get-user-info', new WP_Error( 'ability_invalid_permissions', 'not allowed' ) );
	$GLOBALS['ability_categories']['core']      = new Ability_Category_Stub( 'Core' );

	$resolver = new WP_AI_Client_Ability_Function_Resolver( 'core/get-site-info', 'core/get-user-info' );

	$model_message = new \WordPress\AiClient\Messages\DTO\ModelMessage( array(
		new \WordPress\AiClient\Messages\DTO\MessagePart( 'Let me look that up.' ),
		new \WordPress\AiClient\Messages\DTO\MessagePart( new \WordPress\AiClient\Tools\DTO\FunctionCall( 'c1', 'wpab__core__get-site-info', array( 'fields' => array( 'name' ) ) ) ),
		new \WordPress\AiClient\Messages\DTO\MessagePart( new \WordPress\AiClient\Tools\DTO\FunctionCall( 'c2', 'wpab__core__get-user-info', array() ) ),
	) );

	check( 'a message with calls is detected', $resolver->has_ability_calls( $model_message ), true );

	$responses = $resolver->execute_abilities( $model_message );
	check( 'one response per call', count( $responses->getParts() ), 2 );
	check(
		'steps report which ability ran and whether it worked',
		$call( 'describe_calls', $model_message, $responses ),
		array(
			array( 'ability' => 'core/get-site-info', 'ok' => true ),
			array( 'ability' => 'core/get-user-info', 'ok' => false ),
		)
	);

	check( 'text is read from a mixed message', $call( 'message_to_text', $model_message ), 'Let me look that up.' );

	$calls_only = new \WordPress\AiClient\Messages\DTO\ModelMessage( array(
		new \WordPress\AiClient\Messages\DTO\MessagePart( new \WordPress\AiClient\Tools\DTO\FunctionCall( 'c3', 'wpab__core__get-site-info', array() ) ),
	) );
	// toText() would throw here, which is the whole reason message_to_text exists.
	check( 'a calls-only message yields no text', $call( 'message_to_text', $calls_only ), '' );

	$plain = new \WordPress\AiClient\Messages\DTO\ModelMessage( array( new \WordPress\AiClient\Messages\DTO\MessagePart( 'Final answer.' ) ) );
	check( 'a plain message ends the loop', $resolver->has_ability_calls( $plain ), false );

	$messages = $call( 'to_messages', array(
		array( 'role' => 'user', 'content' => 'hi' ),
		array( 'role' => 'assistant', 'content' => 'hello' ),
		array( 'role' => 'user', 'content' => '' ),
	) );
	check( 'empty turns are dropped', count( $messages ), 2 );
	check(
		'assistant maps to the model role',
		array_map( static fn( $m ) => $m->getRole()->isUser() ? 'user' : 'model', $messages ),
		array( 'user', 'model' )
	);

	// ======================================================================
	section( 'Provider errors are rewritten for humans' );

	$network = $call( 'friendly_error', new WP_Error( 'prompt_network_error', 'cURL error 28', array( 'status' => 503 ) ) );
	check( 'network failure', $network->get_error_message(), 'Could not reach the AI provider. Check this site can make outgoing requests, then try again.' );
	check( 'the provider wording is kept as detail', $network->get_error_data()['detail'], 'cURL error 28' );
	check( 'the HTTP status survives', $network->get_error_data()['status'], 503 );

	check( 'rejected key', $call( 'friendly_error', new WP_Error( 'prompt_client_error', '401', array( 'status' => 401 ) ) )->get_error_message(), 'The AI provider rejected the API key. Check it under Settings → Connectors.' );
	check( 'rate limit', $call( 'friendly_error', new WP_Error( 'prompt_client_error', '429', array( 'status' => 429 ) ) )->get_error_message(), 'The AI provider is rate limiting this site. Wait a moment and try again.' );
	check( 'unknown model', $call( 'friendly_error', new WP_Error( 'prompt_client_error', '404', array( 'status' => 404 ) ) )->get_error_message(), 'The AI provider does not offer that model. Pick another one under Settings → Chagency.' );
	check( 'context overflow', $call( 'friendly_error', new WP_Error( 'prompt_token_limit_reached', 'too long', array( 'status' => 400 ) ) )->get_error_message(), 'This conversation is too long for the model. Hit Reset in the chat panel to start a fresh one.' );
	check( 'upstream failure without data', $call( 'friendly_error', new WP_Error( 'prompt_upstream_server_error', 'boom' ) )->get_error_message(), 'The AI provider is having trouble on their side. Try again in a moment.' );

	$already_human = new WP_Error( 'chagency_unsupported', 'No configured AI provider can generate text right now.', array( 'status' => 503 ) );
	check( 'our own errors are left alone', $call( 'friendly_error', $already_human ), $already_human );

	// ======================================================================
	section( 'Settings and ability gating' );

	check( 'well-formed names are kept', \Chagency\sanitize_ability_names( array( 'core/get-site-info', 'chagency/send-message' ) ), array( 'core/get-site-info', 'chagency/send-message' ) );
	check( 'anything else is dropped', \Chagency\sanitize_ability_names( array( 'Core/Get', '../../etc/passwd', '', 42, 'no-slash', 'a/b/c' ) ), array() );
	check( 'duplicates collapse', \Chagency\sanitize_ability_names( array( 'core/x', 'core/x' ) ), array( 'core/x' ) );
	check( 'a non-array is not a list of names', \Chagency\sanitize_ability_names( 'core/x' ), array() );

	$saved = \Chagency\sanitize_settings( array(
		'admin_enabled'     => true,
		'abilities_enabled' => '1',
		'abilities'         => array( 'core/get-site-info', 'BAD' ),
	) );
	check( 'saving keeps only real ability names', $saved['abilities'], array( 'core/get-site-info' ) );
	$GLOBALS['options']['chagency_settings'] = $saved;
	check( 'settings read back intact', \Chagency\get_plugin_settings()['abilities'], array( 'core/get-site-info' ) );

	$GLOBALS['can'] = false;
	check( 'a non-admin never gets abilities', \Chagency\agent_abilities(), array() );
	$GLOBALS['can'] = true;
	check( 'an admin gets what was ticked', \Chagency\agent_abilities(), array( 'core/get-site-info' ) );

	$GLOBALS['options']['chagency_settings']['abilities'] = array( 'gone/away' );
	check( 'an ability that is no longer registered is dropped', \Chagency\agent_abilities(), array() );
	$GLOBALS['options']['chagency_settings']['abilities']          = array( 'core/get-site-info' );
	$GLOBALS['options']['chagency_settings']['abilities_enabled']  = false;
	check( 'the master switch wins', \Chagency\agent_abilities(), array() );
	$GLOBALS['options']['chagency_settings']['abilities_enabled'] = true;

	$listed = \Chagency\list_abilities();
	check( 'every registered ability is offered', count( $listed ), 2 );
	check( 'the category label is resolved', $listed[0]['categoryLabel'], 'Core' );
	check( 'read-only annotations surface', $listed[0]['readonly'], true );

	// ======================================================================
	section( 'Connector detection' );

	$GLOBALS['connectors'] = array(
		'anthropic' => array(
			'name' => 'Anthropic', 'type' => 'ai_provider',
			'authentication' => array( 'method' => 'api_key', 'setting_name' => 'connectors_ai_provider_anthropic_api_key', 'env_var_name' => 'CHAGENCY_TEST_KEY' ),
		),
		'remote' => array(
			'name' => 'Remote', 'type' => 'ai_provider',
			'authentication' => array( 'method' => 'application_password', 'setting_name' => 'connectors_ai_provider_remote_application_password' ),
		),
	);
	check( 'no keys anywhere means no launcher', \Chagency\has_credentials(), false );

	$GLOBALS['options']['connectors_ai_provider_anthropic_api_key'] = 'sk-test';
	check( 'a saved key is found', \Chagency\has_credentials(), true );

	$GLOBALS['options']['connectors_ai_provider_anthropic_api_key'] = '';
	putenv( 'CHAGENCY_TEST_KEY=sk-from-env' );
	check( 'a key from the environment is found too', \Chagency\has_credentials(), true );
	putenv( 'CHAGENCY_TEST_KEY' );

	$GLOBALS['options']['connectors_ai_provider_anthropic_api_key'] = 'sk-test';
	$status = \Chagency\list_connectors_status();
	check( 'both providers are listed', count( $status ), 2 );
	check( 'sorted by name', $status[0]['name'], 'Anthropic' );
	check( 'configured state is reported', $status[0]['isConfigured'], true );
	check( 'the 7.1 auth method surfaces', $status[1]['method'], 'application_password' );

	// Connector checks must stay local reads. Asking the AI Client registry
	// would probe the provider over the network on every page load, so an
	// auth method we cannot read is assumed usable rather than probed.
	$GLOBALS['connectors'] = array(
		'future' => array(
			'name' => 'Future', 'type' => 'ai_provider',
			'authentication' => array( 'method' => 'oauth_something' ),
		),
	);
	check( 'an unknown auth method is assumed configured', \Chagency\has_credentials(), true );

	$GLOBALS['connectors'] = array(
		'local' => array(
			'name' => 'Local', 'type' => 'ai_provider',
			'authentication' => array( 'method' => 'none' ),
		),
	);
	check( 'a provider needing no credentials is configured', \Chagency\has_credentials(), true );

	printf( "\n%d passed, %d failed\n", $ok, $fail );
	exit( $fail > 0 ? 1 : 0 );
}
