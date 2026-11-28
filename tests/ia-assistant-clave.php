<?php
/**
 * IA Tools · la clave del Asistente (revisión de ia/asistente-optimizado, 2026-11-27): DIGITALÍSIMO paga la API del
 * Asistente, así que su clave no debe poder salir del sitio ni gastarse en otra cosa. Sin WordPress, con el HTTP de la
 * prueba (wp_remote_post simulado):
 *   K1  el Asistente habla con Anthropic siempre en su URL oficial, aunque en IA Tools → Proveedores alguien ponga otra
 *       (la clave nunca viaja a un servidor que cambie el administrador);
 *   K2  con DIGITALISIMO_AI_ASSISTANT_ANTHROPIC_KEY en wp-config, el Asistente usa esa clave (aunque Proveedores no tenga
 *       Anthropic o tenga otra), siempre en la URL oficial;
 *   K3  esa clave no sale en la pantalla, la REST, la bitácora ni los textos (se borra como las de Proveedores) y «Uso de
 *       IA» la cuenta como Anthropic;
 *   K4  los demás perfiles (chatbot, contenido…) no la usan;
 *   K5  sin la IA de DIGITALÍSIMO, el aviso dice qué falta y cómo resolverlo, sin jerga.
 * Uso: php tests/ia-assistant-clave.php
 */
require __DIR__ . '/ia-assistant-agent/wp.php';
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
foreach ( array( 'model', 'tools', 'history', 'guard', 'ability', 'uploads', '' ) as $part ) require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant' . ( $part ? '-' . $part : '' ) . '.php';
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-usage.php';
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant-learning.php';

const OWN_KEY = 'sk-ant-digitalisimo-PROPIA0123456789abcdef';
$M = 'Digitalisimo_AI_Assistant_Model';
$GLOBALS['options']['digitalisimo_ai_options'] = array(
	'providers' => array(
		'anthropic' => array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => 'https://captura.example/v1/messages' ),
		'openai'    => array( 'enabled' => 1, 'key' => KEY_OPENAI, 'url' => '' ),
	),
	'profiles'  => array( 'chatbot' => array( 'provider' => 'anthropic', 'model' => 'claude-opus-5-5', 'fallback' => '' ) ),
);
// Como lo haría un fragmento de PHP del sitio: quitar los topes.
add_filter( 'digitalisimo_ai_assistant_limits', function () { return array( 'per_minute' => 0, 'per_day' => 0, 'concurrent' => 1 ); } );
add_filter( 'digitalisimo_ai_learning_max_trainings', function () { return 99; } );
$reply = function ( $text ) { return array( 'code' => 200, 'body' => json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => $text ) ), 'stop_reason' => 'end_turn', 'usage' => array( 'input_tokens' => 10, 'output_tokens' => 5 ) ) ) ); };
$req   = function ( array $params = array() ) { return new WP_REST_Request( $params, array( 'X-Digitalisimo-Assistant' => wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) ) ); };

/* ---------------- K1: siempre la URL oficial ---------------- */
$t = $M::target( 'auto' );
check( '' === $t['url'] && KEY_ANTHROPIC === $t['key'], 'K1: aunque Proveedores tenga otra URL para Anthropic, el Asistente no la usa' );
$GLOBALS['http'] = array(); $GLOBALS['http_replies'] = array( $reply( 'Hola.' ) );
Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'hola' ) ) );
$sent = end( $GLOBALS['http'] );
check( $sent && 'https://api.anthropic.com/v1/messages' === $sent['url'] && false === strpos( wp_json_encode( $GLOBALS['http'] ), 'captura.example' ), 'K1: la petición va a api.anthropic.com, nunca a la URL guardada: ' . ( $sent['url'] ?? '' ) );
check( 0 === ( $sent['args']['redirection'] ?? null ), 'K1: y sin seguir redirecciones (una 30x no se lleva la clave a otro lado)' );
// Sin la constante, un 401 manda a revisar la clave en IA Tools (es la de Proveedores).
$GLOBALS['http_replies'] = array( array( 'code' => 401, 'body' => '{"error":{"message":"invalid x-api-key"}}' ) );
$e = $M::chat( $M::target( 'auto' ), 'S', array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( is_wp_error( $e ) && false !== strpos( $e->get_error_message(), 'revisa la clave en IA Tools' ), 'K1: sin la constante, un 401 manda a revisar la clave en IA Tools' );
check( 1 === Digitalisimo_AI_Assistant_Guard::limits()['concurrent'] && 0 === Digitalisimo_AI_Assistant_Guard::limits()['per_day'] && 99 === Digitalisimo_AI_Assistant_Learning::max_trainings(), 'K6: sin la constante, los topes son los de IA Tools y sus filtros (como antes)' );

/* ---------------- K4 (antes de la constante): los demás perfiles siguen con lo suyo ---------------- */
$order = new ReflectionMethod( 'Digitalisimo_AI', 'provider_order' );
$order->setAccessible( true );

/* ---------------- K2: la constante de wp-config ---------------- */
define( 'DIGITALISIMO_AI_ASSISTANT_ANTHROPIC_KEY', OWN_KEY );
$GLOBALS['options']['digitalisimo_ai_options']['providers']['anthropic'] = array( 'enabled' => 0, 'key' => '', 'url' => 'https://captura.example/v1/messages' );
$t = $M::target( 'auto' );
check( ! is_wp_error( $t ) && 'anthropic' === $t['provider'] && OWN_KEY === $t['key'] && '' === $t['url'], 'K2: con la constante, el Asistente funciona aunque Proveedores no tenga Anthropic, con esa clave y la URL oficial' );
$GLOBALS['options']['digitalisimo_ai_options']['providers']['anthropic'] = array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => 'https://captura.example/v1/messages' );
check( OWN_KEY === $M::target( 'auto' )['key'], 'K2: y la prefiere a la clave de Proveedores' );
$GLOBALS['http'] = array(); $GLOBALS['http_replies'] = array( $reply( 'Tu clave es ' . OWN_KEY . ', no la compartas.' ) );
$r    = Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'hola otra vez' ) ) );
$sent = end( $GLOBALS['http'] );
check( 'https://api.anthropic.com/v1/messages' === $sent['url'] && OWN_KEY === $sent['args']['headers']['x-api-key'] && 0 === $sent['args']['redirection'], 'K2: la petición lleva la clave de wp-config a la URL oficial, sin redirecciones' );
$GLOBALS['http_replies'] = array( array( 'code' => 401, 'body' => '{"error":{"message":"invalid x-api-key"}}' ) );
$e = $M::chat( $M::target( 'auto' ), 'S', array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( is_wp_error( $e ) && false !== strpos( $e->get_error_message(), 'avisa a DIGITALÍSIMO' ) && false === strpos( $e->get_error_message(), 'IA Tools' ), 'K2: con la clave de DIGITALÍSIMO, un 401 dice que se avise a DIGITALÍSIMO (quien administra no la puede revisar): ' . ( is_wp_error( $e ) ? $e->get_error_message() : '' ) );

/* ---------------- K6: topes de gasto con la clave de DIGITALÍSIMO ---------------- */
$GLOBALS['options']['digitalisimo_ai_options']['assistant_daily_calls'] = 0;
$GLOBALS['options']['digitalisimo_ai_options']['assistant_concurrency'] = 5;
$limits = Digitalisimo_AI_Assistant_Guard::limits();
check( $M::OWN_DAILY === $limits['per_day'] && $M::OWN_DAILY > 0 && $M::OWN_CONCURRENCY === $limits['concurrent'], 'K6: con la clave de DIGITALÍSIMO, las llamadas por día y las peticiones a la vez son las de fábrica (' . $M::OWN_DAILY . ' y ' . $M::OWN_CONCURRENCY . '), aunque IA Tools o un filtro digan 0 (sin tope): ' . json_encode( $limits ) );
check( Digitalisimo_AI_Assistant_Learning::MAX_TRAININGS === Digitalisimo_AI_Assistant_Learning::max_trainings() && Digitalisimo_AI_Assistant_Learning::MAX_REVIEWS >= Digitalisimo_AI_Assistant_Learning::max_reviews(), 'K6: el Aprendizaje no pasa de sus topes de fábrica aunque un filtro los suba' );
$fields = new ReflectionMethod( 'Digitalisimo_AI', 'assistant_fields' );
ob_start();
$fields->invoke( null, Digitalisimo_AI::get() );
$html = ob_get_clean();
check( false === strpos( $html, 'name="ai[assistant_daily_calls]"' ) && false === strpos( $html, 'name="ai[assistant_concurrency]"' ) && 2 === substr_count( $html, 'disabled readonly' ) && false !== strpos( $html, 'value="' . $M::OWN_DAILY . '"' ), 'K6: en IA Tools esos dos campos sólo se ven (sin nombre: no se envían ni se pueden poner en 0)' );
define( 'DIGITALISIMO_AI_ASSISTANT_DAILY_CALLS', 0 );
define( 'DIGITALISIMO_AI_ASSISTANT_CONCURRENCY', 50 );
$limits = Digitalisimo_AI_Assistant_Guard::limits();
check( $M::OWN_DAILY === $limits['per_day'] && 10 === $limits['concurrent'], 'K6: las constantes de wp-config tampoco quitan el tope (0 vale el de fábrica; peticiones a la vez, hasta 10): ' . json_encode( $limits ) );

/* ---------------- K3: nunca a la vista ---------------- */
$visible = wp_json_encode( array( Digitalisimo_AI_Assistant::config(), $r->data, Digitalisimo_AI_Assistant::rest_conversation( new WP_REST_Request( array( 'id' => $r->data['conversation']['id'] ) ) )->data, get_option( Digitalisimo_AI_Assistant_Tools::LOG ), get_option( Digitalisimo_AI_Assistant_Tools::USAGE_LOG ), $GLOBALS['meta'] ) );
check( false === strpos( $visible, OWN_KEY ) && false !== strpos( $visible, '[clave oculta]' ), 'K3: la clave de wp-config no sale en la pantalla, la REST, la conversación guardada ni la bitácora (se borra de los textos)' );
check( '[clave oculta]' === Digitalisimo_AI_Assistant_Tools::scrub( OWN_KEY ), 'K3: scrub() la borra como a las de Proveedores' );
check( 'anthropic' === Digitalisimo_AI_Usage::provider_for( 'https://api.anthropic.com/v1/messages', array( 'headers' => array( 'x-api-key' => OWN_KEY ) ) ), 'K3: «Uso de IA» cuenta lo que gasta como Anthropic' );

/* ---------------- K4: los demás perfiles no la usan ---------------- */
$GLOBALS['options']['digitalisimo_ai_options']['providers']['anthropic'] = array( 'enabled' => 0, 'key' => '', 'url' => '' );
$chain = $order->invoke( null, Digitalisimo_AI::get()['profiles']['chatbot'], Digitalisimo_AI::get() );
check( 'anthropic' === array_key_first( $chain ) && '' === (string) ( Digitalisimo_AI::get( 'providers' )['anthropic']['key'] ?? '' ) && ! isset( Digitalisimo_AI::usable_providers()['anthropic'] ), 'K4: el chatbot (perfil en Anthropic) sólo tiene la clave de Proveedores, vacía: nunca la de wp-config · ' . json_encode( $chain ) );
check( false === strpos( wp_json_encode( Digitalisimo_AI::get() ), OWN_KEY ), 'K4: la clave de wp-config no está en las opciones de IA Tools' );
check( ! is_wp_error( $M::target( 'auto' ) ), 'K4: pero el Asistente sí' );

/* ---------------- K5: el aviso sin la IA de DIGITALÍSIMO ---------------- */
check( false !== strpos( $M::MISSING, 'IA de DIGITALÍSIMO' ) && false !== strpos( $M::MISSING, 'Pídele a DIGITALÍSIMO' ) && ! preg_match( '/Proveedores|clave|API|endpoint|wp-config|constante/i', $M::MISSING ), 'K5: el aviso dice qué falta (la IA de DIGITALÍSIMO) y a quién pedirla, sin mandar a escribir la clave ni jerga' );
$ui = file_get_contents( dirname( __DIR__ ) . '/digitalisimo-ia-tools/assets/assistant/assistant.js' );
check( false !== strpos( $ui, 'necesita la IA de DIGITALÍSIMO' ) && false !== strpos( $ui, 'Pídele a DIGITALÍSIMO que la conecte' ) && false === strpos( $ui, 'Abrir Proveedores' ) && false === strpos( $ui, 'escribe en Proveedores' ), 'K5: la pantalla dice lo mismo, sin mandar a Proveedores' );

echo "Asistente IA, clave de DIGITALÍSIMO: $checks comprobaciones correctas.\n";
