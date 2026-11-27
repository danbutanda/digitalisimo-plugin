<?php
/**
 * IA Tools · el modelo del Asistente es fijo (pedido del dueño del 2026-11-27: «en WordPress asistente no permitas que
 * el usuario cambie el modelo todavía porque ese lo pagaré yo, debemos darle el asistente ya optimizado»), sin
 * WordPress y con el modelo simulado (`digitalisimo_ai_assistant_pre_model`):
 *   F1  de fábrica, el Asistente usa Digitalisimo_AI_Assistant_Model::MODEL con su esfuerzo (EFFORT);
 *   F2  lo guardado antes (la opción `assistant_models`, un perfil «assistant», el perfil de contenido) se ignora;
 *   F3  la IA que pida el navegador o un cliente de la REST (`provider`) se ignora, también al confirmar y al seguir;
 *   F4  en IA Tools (sitio y red) no hay selector ni campo: una línea informativa sin jerga; guardar borra lo de antes y
 *       no acepta un modelo nuevo;
 *   F5  la pantalla del Asistente no tiene selector de IA ni manda `provider`, y su configuración no dice el modelo;
 *   F6  el MCP no ofrece nada para cambiarlo (la ability «Abrir el Asistente IA» sólo abre la pantalla);
 *   F7  DIGITALÍSIMO lo cambia con el filtro `digitalisimo_ai_assistant_model` o con las constantes de wp-config
 *       DIGITALISIMO_AI_ASSISTANT_MODEL, …_PROVIDER y …_EFFORT (lo no válido se ignora);
 *   F8  los demás perfiles (chatbot, contenido…) siguen con su modelo.
 * Con el código anterior (selector «Modelo del Asistente IA» y selector de IA en el chat) falla en F2.
 * Uso: php tests/ia-assistant-modelo-fijo.php
 */
require __DIR__ . '/ia-assistant-agent/wp.php';
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
foreach ( array( 'model', 'tools', 'history', 'guard', 'ability', 'uploads', '' ) as $part ) require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant' . ( $part ? '-' . $part : '' ) . '.php';

$M = 'Digitalisimo_AI_Assistant_Model';
$GLOBALS['options']['digitalisimo_ai_options'] = array(
	'providers' => array(
		'anthropic' => array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => '' ),
		'openai'    => array( 'enabled' => 1, 'key' => KEY_OPENAI, 'url' => '' ),
	),
	'profiles'  => array( 'content' => array( 'provider' => 'auto', 'model' => '', 'fallback' => '' ) ),
);
add_filter( 'digitalisimo_ai_assistant_limits', function () { return array( 'per_minute' => 0, 'per_day' => 0, 'concurrent' => 1 ); } );
$GLOBALS['model_calls'] = array();
add_filter( 'digitalisimo_ai_assistant_pre_model', function ( $pre, $request ) { $GLOBALS['model_calls'][] = $request; return 'Listo.'; } );
$req = function ( array $params = array() ) { return new WP_REST_Request( $params, array( 'X-Digitalisimo-Assistant' => wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) ) ); };

/* ---------------- F1: de fábrica ---------------- */
check( defined( $M . '::MODEL' ) && defined( $M . '::PROVIDER' ) && method_exists( $M, 'fixed' ), 'F1: el modelo fijo vive en el código (MODEL, PROVIDER y fixed())' );
// Con el código anterior (DIGI_SEGUIR=1) se sigue con su modelo de fábrica para ver qué más falla.
$MODEL = defined( $M . '::MODEL' ) ? $M::MODEL : 'claude-opus-5-5';
$fixed = method_exists( $M, 'fixed' ) ? $M::fixed() : array();
check( array( 'provider' => 'anthropic', 'model' => $MODEL, 'effort' => $M::EFFORT ) === $fixed, 'F1: de fábrica, Anthropic con MODEL y EFFORT: ' . json_encode( $fixed ) );
$t = $M::target( 'auto' );
check( ! is_wp_error( $t ) && 'anthropic' === $t['provider'] && $MODEL === $t['model'] && KEY_ANTHROPIC === $t['key'], 'F1: target() da la IA fija con su clave (sólo en el servidor)' );
check( 1 === count( $M::targets() ) && isset( $M::targets()['anthropic'] ), 'F1: hay una sola IA para el Asistente aunque OpenAI también tenga clave' );
$b = $M::build( $t, 'S', array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( $MODEL === $b['body']['model'], 'F1: la petición lleva el modelo fijo' );
if ( preg_match( $M::UPDATES_MODELS, $MODEL ) ) {
	check( array( 'effort' => $M::EFFORT ) === $b['body']['output_config'], 'F1: y su esfuerzo fijo' );
}

/* ---------------- F2: lo guardado antes se ignora ---------------- */
$other = 'claude-opus-5-5' === $MODEL ? 'claude-haiku-5-5' : 'claude-opus-5-5';
$GLOBALS['options']['digitalisimo_ai_options']['assistant_models'] = array( 'anthropic' => $other, 'openai' => 'gpt-4.1' );
check( $MODEL === $M::target( 'auto' )['model'], 'F2: un modelo guardado en «Modelo del Asistente IA» (opción assistant_models) se ignora' );
$GLOBALS['options']['digitalisimo_ai_options']['assistant_models'] = array( 'anthropic' => 'profile' );
$GLOBALS['options']['digitalisimo_ai_options']['profiles']['content'] = array( 'provider' => 'openai', 'model' => 'gpt-perfil', 'fallback' => '' );
$GLOBALS['options']['digitalisimo_ai_options']['profiles']['assistant'] = array( 'provider' => 'anthropic', 'model' => 'claude-perfil', 'fallback' => '' );
$t = $M::target( 'auto' );
check( 'anthropic' === $t['provider'] && $MODEL === $t['model'], 'F2: ni «Igual que el perfil de uso», ni un perfil «assistant», ni el de contenido cambian el Asistente: ' . $t['provider'] . ' ' . $t['model'] );
unset( $GLOBALS['options']['digitalisimo_ai_options']['profiles']['assistant'], $GLOBALS['options']['digitalisimo_ai_options']['assistant_models'] );

/* ---------------- F3: la IA que pida el navegador se ignora ---------------- */
foreach ( array( 'openai', 'google', 'inventada', 'auto', '' ) as $choice ) {
	$t = $M::target( $choice );
	check( ! is_wp_error( $t ) && 'anthropic' === $t['provider'] && $MODEL === $t['model'], "F3: target( '$choice' ) da siempre la IA fija" );
}
$GLOBALS['model_calls'] = array();
$r = Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'hola', 'provider' => 'openai', 'model' => 'gpt-4.1', 'effort' => 'max' ) ) );
$call = end( $GLOBALS['model_calls'] );
check( $r instanceof WP_REST_Response && $call && 'anthropic' === $call['provider'] && $MODEL === $call['model'], 'F3: un mensaje que pide OpenAI (o un modelo, o un esfuerzo) va igual a la IA fija: ' . json_encode( $call ? array( $call['provider'], $call['model'] ) : null ) );
$cid = (int) $r->data['conversation']['id'];
$conv = Digitalisimo_AI_Assistant_History::get( $cid, get_current_user_id() );
check( 'auto' === $conv['provider'], 'F3: la conversación no guarda la IA pedida' );
$conv['provider'] = 'openai'; // Una conversación guardada antes con otra IA.
Digitalisimo_AI_Assistant_History::save( $conv );
Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'otra cosa', 'conversation' => $cid ) ) );
$call = end( $GLOBALS['model_calls'] );
check( 'anthropic' === $call['provider'] && $MODEL === $call['model'], 'F3: una conversación guardada con otra IA sigue con la fija' );
$GLOBALS['options']['digitalisimo_ai_options']['providers']['anthropic']['enabled'] = 0;
$e = $M::target( 'openai' );
check( is_wp_error( $e ) && 409 === ( $e->get_error_data()['status'] ?? 0 ) && false === strpos( $e->get_error_message(), 'OpenAI' ), 'F3: sin la clave de la IA fija, el Asistente no se pasa a otra IA (aunque OpenAI tenga clave)' );
check( array() === Digitalisimo_AI_Assistant::config()['providers'], 'F3: y la pantalla dice que aún no está activo' );
$GLOBALS['options']['digitalisimo_ai_options']['providers']['anthropic']['enabled'] = 1;

/* ---------------- F4: IA Tools, sitio y red ---------------- */
$fields = new ReflectionMethod( 'Digitalisimo_AI', 'tab_fields' );
$fields->setAccessible( true );
foreach ( array( false => 'sitio', true => 'red' ) as $network => $where ) {
	ob_start();
	$fields->invoke( null, 'profiles', Digitalisimo_AI::get(), (bool) $network );
	$html = ob_get_clean();
	$assistant = substr( $html, (int) strpos( $html, 'id="assistant-model"' ) );
	check( false !== strpos( $html, 'El Asistente usa la IA de DIGITALÍSIMO, ya optimizada' ), "F4 ($where): Perfiles de uso dice en una línea que el Asistente usa la IA de DIGITALÍSIMO" );
	check( false === strpos( $html, 'assistant_models' ) && false === strpos( $assistant, '<select' ) && false === strpos( $assistant, '<input' ), "F4 ($where): sin selector ni campo para el modelo del Asistente" );
	check( ! preg_match( '/claude|opus|sonnet|haiku|modelo m[aá]s capaz|token/i', $assistant ), "F4 ($where): sin jerga ni nombres de modelos en la línea del Asistente" );
}
$sanitize = new ReflectionMethod( 'Digitalisimo_AI', 'sanitize_values' );
$sanitize->setAccessible( true );
$_POST = array( 'assistant_models' => array( 'anthropic' => 'custom' ), 'assistant_models_custom' => array( 'anthropic' => 'claude-opus-5-5-fast' ), 'ai' => array( 'assistant_models' => 'x' ) );
$saved = $sanitize->invoke( null, array_merge( Digitalisimo_AI::get(), array( 'assistant_models' => array( 'anthropic' => 'claude-sonnet-5-5' ) ) ) );
$_POST = array();
check( ! array_key_exists( 'assistant_models', $saved ) && ! array_key_exists( 'assistant_models_custom', $saved ), 'F4: guardar IA Tools no acepta un modelo del Asistente y borra el que quedó de antes: ' . json_encode( array_intersect_key( $saved, array( 'assistant_models' => 1, 'assistant_models_custom' => 1 ) ) ) );

/* ---------------- F5: la pantalla del Asistente ---------------- */
$config = Digitalisimo_AI_Assistant::config();
$json   = json_encode( $config );
check( 1 === count( $config['providers'] ) && 'available' === $config['providers'][0]['status'], 'F5: la pantalla sabe que hay IA (una sola, sin nada que elegir)' );
check( ! preg_match( '/claude-|gpt-|gemini|opus|sonnet|haiku|anthropic|openai/i', $json ), 'F5: la configuración de la pantalla no dice el modelo ni el proveedor' );
$ui = file_get_contents( dirname( __DIR__ ) . '/digitalisimo-ia-tools/assets/assistant/assistant.js' );
check( false === strpos( $ui, 'AiSwitcher' ) && false === strpos( $ui, 'IA automática' ) && false === strpos( $ui, 'dga-ai' ), 'F5: la pantalla no tiene selector de IA' );
check( ! preg_match( '/model\.send\(\s*Object\.assign\(\s*\{[^}]*provider/', $ui ), 'F5: el mensaje no manda la IA elegida' );

/* ---------------- F6: el MCP ---------------- */
$definition = Digitalisimo_AI_Assistant_Ability::definition();
check( array( 'new_conversation', 'message' ) === array_keys( $definition['input_schema']['properties'] ) && false === $definition['input_schema']['additionalProperties'], 'F6: «Abrir el Asistente IA» no acepta proveedor, modelo ni esfuerzo' );

/* ---------------- F8: los demás perfiles no cambian ---------------- */
check( 'claude-sonnet-5-5' === Digitalisimo_AI::DEFAULT_MODELS['anthropic'] && 'gpt-4o-mini' === Digitalisimo_AI::DEFAULT_MODELS['openai'], 'F8: los demás perfiles siguen con su modelo recomendado (DEFAULT_MODELS)' );
$GLOBALS['options']['digitalisimo_ai_options']['profiles']['chatbot'] = array( 'provider' => 'openai', 'model' => 'gpt-chat', 'fallback' => '' );
$order = new ReflectionMethod( 'Digitalisimo_AI', 'provider_order' );
$order->setAccessible( true );
$chain = $order->invoke( null, Digitalisimo_AI::get()['profiles']['chatbot'], Digitalisimo_AI::get() );
check( 'openai' === array_key_first( $chain ) && 'gpt-chat' === reset( $chain ), 'F8: el chatbot sigue con su perfil' );

/* ---------------- F7: DIGITALÍSIMO lo cambia en el código ---------------- */
if ( ! method_exists( $M, 'fixed' ) ) {
	check( false, 'F7: no hay fixed(): DIGITALÍSIMO no puede fijar el modelo con su filtro ni con wp-config' );
	exit( 1 );
}
add_filter( 'digitalisimo_ai_assistant_model', function ( $fixed ) { return array( 'model' => 'claude-sonnet-5-5', 'effort' => 'medium' ) + $fixed; } );
$t = $M::target( 'auto' );
check( 'anthropic' === $t['provider'] && 'claude-sonnet-5-5' === $t['model'] && 'medium' === $t['turn_effort'], 'F7: el filtro digitalisimo_ai_assistant_model cambia modelo y esfuerzo' );
$b = $M::build( $t, 'S', array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( 'claude-sonnet-5-5' === $b['body']['model'] && array( 'effort' => 'medium' ) === $b['body']['output_config'], 'F7: y la petición los lleva' );
remove_all_filters( 'digitalisimo_ai_assistant_model' );
add_filter( 'digitalisimo_ai_assistant_model', function ( $fixed ) { return array( 'model' => 'claude haiku; drop', 'provider' => 'inventado', 'effort' => 'enorme' ); } );
check( array( 'provider' => 'anthropic', 'model' => $MODEL, 'effort' => $M::EFFORT ) === $M::fixed(), 'F7: lo que el filtro da y no es válido se ignora' );
remove_all_filters( 'digitalisimo_ai_assistant_model' );
define( 'DIGITALISIMO_AI_ASSISTANT_MODEL', 'gpt-4.1' );
define( 'DIGITALISIMO_AI_ASSISTANT_EFFORT', 'low' );
$fixed = $M::fixed();
check( array( 'provider' => 'openai', 'model' => 'gpt-4.1', 'effort' => 'low' ) === $fixed, 'F7: con DIGITALISIMO_AI_ASSISTANT_MODEL (y su esfuerzo) en wp-config, el proveedor sale del modelo: ' . json_encode( $fixed ) );
$t = $M::target( 'auto' );
check( 'openai' === $t['provider'] && KEY_OPENAI === $t['key'], 'F7: y usa la clave de ese proveedor' );
define( 'DIGITALISIMO_AI_ASSISTANT_PROVIDER', 'deepseek' );
check( 'deepseek' === $M::fixed()['provider'], 'F7: DIGITALISIMO_AI_ASSISTANT_PROVIDER fija el proveedor' );
check( is_wp_error( $M::target( 'auto' ) ), 'F7: un proveedor fijo sin clave deja el Asistente sin IA, sin pasarse a otra' );

echo "OK modelo fijo del Asistente: $checks comprobaciones\n";
