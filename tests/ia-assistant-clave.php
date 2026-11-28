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

const OWN_KEY = 'sk-ant-digitalisimo-PROPIA0123456789abcdef';
$M = 'Digitalisimo_AI_Assistant_Model';
$GLOBALS['options']['digitalisimo_ai_options'] = array(
	'providers' => array(
		'anthropic' => array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => 'https://captura.example/v1/messages' ),
		'openai'    => array( 'enabled' => 1, 'key' => KEY_OPENAI, 'url' => '' ),
	),
	'profiles'  => array( 'chatbot' => array( 'provider' => 'anthropic', 'model' => 'claude-opus-5-5', 'fallback' => '' ) ),
);
add_filter( 'digitalisimo_ai_assistant_limits', function () { return array( 'per_minute' => 0, 'per_day' => 0, 'concurrent' => 1 ); } );
$reply = function ( $text ) { return array( 'code' => 200, 'body' => json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => $text ) ), 'stop_reason' => 'end_turn', 'usage' => array( 'input_tokens' => 10, 'output_tokens' => 5 ) ) ) ); };
$req   = function ( array $params = array() ) { return new WP_REST_Request( $params, array( 'X-Digitalisimo-Assistant' => wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) ) ); };

/* ---------------- K1: siempre la URL oficial ---------------- */
$t = $M::target( 'auto' );
check( '' === $t['url'] && KEY_ANTHROPIC === $t['key'], 'K1: aunque Proveedores tenga otra URL para Anthropic, el Asistente no la usa' );
$GLOBALS['http'] = array(); $GLOBALS['http_replies'] = array( $reply( 'Hola.' ) );
Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'hola' ) ) );
$sent = end( $GLOBALS['http'] );
check( $sent && 'https://api.anthropic.com/v1/messages' === $sent['url'] && false === strpos( wp_json_encode( $GLOBALS['http'] ), 'captura.example' ), 'K1: la petición va a api.anthropic.com, nunca a la URL guardada: ' . ( $sent['url'] ?? '' ) );

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
check( 'https://api.anthropic.com/v1/messages' === $sent['url'] && OWN_KEY === $sent['args']['headers']['x-api-key'], 'K2: la petición lleva la clave de wp-config a la URL oficial' );

/* ---------------- K3: nunca a la vista ---------------- */
$visible = wp_json_encode( array( Digitalisimo_AI_Assistant::config(), $r->data, Digitalisimo_AI_Assistant::rest_conversation( new WP_REST_Request( array( 'id' => $r->data['conversation']['id'] ) ) )->data, get_option( Digitalisimo_AI_Assistant_Tools::LOG ), get_option( Digitalisimo_AI_Assistant_Tools::USAGE_LOG ), $GLOBALS['meta'] ) );
check( false === strpos( $visible, OWN_KEY ) && false !== strpos( $visible, '[clave oculta]' ), 'K3: la clave de wp-config no sale en la pantalla, la REST, la conversación guardada ni la bitácora (se borra de los textos)' );
check( '[clave oculta]' === Digitalisimo_AI_Assistant_Tools::scrub( OWN_KEY ), 'K3: scrub() la borra como a las de Proveedores' );
check( 'anthropic' === Digitalisimo_AI_Usage::provider_for( 'https://api.anthropic.com/v1/messages', array( 'headers' => array( 'x-api-key' => OWN_KEY ) ) ), 'K3: «Uso de IA» cuenta lo que gasta como Anthropic' );

/* ---------------- K4: los demás perfiles no la usan ---------------- */
$GLOBALS['options']['digitalisimo_ai_options']['providers']['anthropic'] = array( 'enabled' => 0, 'key' => '', 'url' => '' );
$chain = $order->invoke( null, Digitalisimo_AI::get()['profiles']['chatbot'], Digitalisimo_AI::get() );
check( ! isset( Digitalisimo_AI::usable_providers()['anthropic'] ) && false === strpos( wp_json_encode( Digitalisimo_AI::get() ), OWN_KEY ), 'K4: la clave de wp-config no es un proveedor de IA Tools: los demás perfiles no la ven ni la usan' );
check( ! is_wp_error( $M::target( 'auto' ) ), 'K4: pero el Asistente sí' );

/* ---------------- K5: el aviso sin la IA de DIGITALÍSIMO ---------------- */
check( false !== strpos( $M::MISSING, 'IA de DIGITALÍSIMO' ) && false !== strpos( $M::MISSING, 'Proveedores' ) && false !== strpos( $M::MISSING, 'Claude' ) && ! preg_match( '/API|endpoint|wp-config|constante/i', $M::MISSING ), 'K5: el aviso dice qué falta (la IA de DIGITALÍSIMO), dónde se pone la clave y a quién pedirla, sin jerga' );
$ui = file_get_contents( dirname( __DIR__ ) . '/digitalisimo-ia-tools/assets/assistant/assistant.js' );
check( false !== strpos( $ui, 'necesita la IA de DIGITALÍSIMO' ) && false !== strpos( $ui, 'Abrir Proveedores' ) && false === strpos( $ui, 'aún no está activo en este sitio' ), 'K5: la pantalla dice lo mismo y lleva a Proveedores a quien administra' );

echo "Asistente IA, clave de DIGITALÍSIMO: $checks comprobaciones correctas.\n";
