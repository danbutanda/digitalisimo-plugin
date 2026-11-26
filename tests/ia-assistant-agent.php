<?php
/**
 * IA Tools · el Asistente IA como un buen agente (caso plomerito del 2026-11-23: «Está muy tonto el asistente IA, no
 * hace las cosas como aquí»), sin WordPress y con el modelo simulado (`digitalisimo_ai_assistant_pre_model`):
 *   K  la conversación del caso: tras un turno con 19 resultados grandes, la petición siguiente al modelo AÚN lleva lo
 *      que dijo la persona («purificador», «7500», «fin de mes»); sólo cerca del límite real del modelo se compactan
 *      los resultados viejos y, después, los turnos más viejos pasan a un resumen que conserva lo dicho;
 *   Y  «sí hazlo» con una acción pendiente la ejecuta (la guardada, con su permiso y su bitácora); «sí, pero…» no; lo
 *      destructivo sigue pidiendo el botón y la casilla;
 *   D  crear un borrador nuevo y editar un borrador de esta conversación no piden confirmación; publicar, editar otra
 *      página o cambiar el estado sí;
 *   T  límites del turno generosos y continuación transparente (evento `continue` y `assistant/continue`);
 *   R  digitalisimo-ia/read-page-text lee el texto de los widgets de Elementor (clásicos y atómicos) y
 *      digitalisimo-ia/site-contact encuentra el WhatsApp de un enlace wa.me del pie y las páginas de políticas reales;
 *   G  el modelo del Asistente sale de su selector (Anthropic: claude-opus-5-5 por defecto) sin tocar DEFAULT_MODELS;
 *   P  las instrucciones del sistema: fecha del sitio, cierre de mes, usar los datos del sitio, no volver a preguntar.
 * Uso: php tests/ia-assistant-agent.php
 */
require __DIR__ . '/ia-assistant-agent/wp.php';
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
foreach ( array( 'model', 'tools', 'history', 'guard', 'ability', '' ) as $part ) require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant' . ( $part ? '-' . $part : '' ) . '.php';
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-site-reader.php';

$GLOBALS['options']['digitalisimo_ai_options'] = array(
	'providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => '' ) ),
	'profiles'  => array( 'content' => array( 'provider' => 'auto', 'model' => '', 'fallback' => '' ) ),
);
add_filter( 'digitalisimo_ai_assistant_trusted_prefixes', function ( $p ) { $p[] = 'demo/'; return $p; } );
add_filter( 'digitalisimo_ai_assistant_limits', function () { return array( 'per_minute' => 0, 'per_day' => 0, 'concurrent' => 1 ); } );

/* ---------------- Abilities de prueba (las del caso, con la forma de las reales) ---------------- */
$can_edit = function () { return current_user_can( 'edit_posts' ); };
$public   = function ( $readonly, $destructive ) { return array( 'annotations' => array( 'readonly' => $readonly, 'destructive' => $destructive ), 'mcp' => array( 'public' => true, 'type' => 'tool' ) ); };
// Un resultado grande como los de get-page-structure o el catálogo (unos 12 KB cada uno).
wp_register_ability( 'demo/big-read', array( 'label' => 'Estructura de página', 'category' => 'demo', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'n' => array( 'type' => 'integer' ) ) ), 'permission_callback' => $can_edit, 'execute_callback' => function ( $in ) { return array( 'n' => $in['n'] ?? 0, 'estructura' => str_repeat( 'widget heading texto de muestra ', 600 ) ); }, 'meta' => $public( true, false ) ) );
wp_register_ability( 'demo/create-note', array( 'label' => 'Crear una nota', 'category' => 'demo', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'title' => array( 'type' => 'string' ) ) ), 'permission_callback' => $can_edit, 'execute_callback' => function ( $in ) { $GLOBALS['created_notes'][] = $in; return array( 'id' => 7 ); }, 'meta' => $public( false, false ) ) );
wp_register_ability( 'demo/delete-note', array( 'label' => 'Borrar la nota', 'category' => 'demo', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer' ) ) ), 'permission_callback' => $can_edit, 'execute_callback' => function ( $in ) { $GLOBALS['deleted_notes'][] = $in; return array( 'deleted' => true ); }, 'meta' => $public( false, true ) ) );
// Como las del MCP de Elements: create-page (escribe, nace en borrador), build-composition y update-page-settings
// (destructivas por post_id) y publish-document.
wp_register_ability( 'elementor/create-page', array( 'label' => 'Crear página', 'category' => 'elementor', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'title' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string' ) ) ), 'permission_callback' => $can_edit, 'execute_callback' => function ( $in ) { $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $in['title'] ?? 'Sin título', 'post_name' => 'borrador-' . count( $GLOBALS['posts'] ) ) ); $GLOBALS['ran'][] = array( 'create', $id ); return array( 'id' => $id, 'status' => 'draft' ); }, 'meta' => $public( false, false ) ) );
wp_register_ability( 'elementor/build-composition', array( 'label' => 'Construir composición', 'category' => 'elementor', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'xml' => array( 'type' => 'string' ), 'settings' => array( 'type' => 'object' ) ) ), 'permission_callback' => $can_edit, 'execute_callback' => function ( $in ) { $GLOBALS['ran'][] = array( 'build', $in['post_id'] ?? 0 ); return array( 'post_id' => $in['post_id'] ?? 0, 'ok' => true ); }, 'meta' => $public( false, true ) ) );
wp_register_ability( 'elementor/manage-elements', array( 'label' => 'Editar elementos', 'category' => 'elementor', 'input_schema' => array( 'type' => 'object', 'required' => array( 'post_id', 'operations' ), 'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'operations' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array( 'action' => array( 'type' => 'string', 'enum' => array( 'update', 'delete', 'move', 'duplicate' ) ), 'element_id' => array( 'type' => 'string' ), 'settings' => array( 'type' => 'object' ), 'style' => array( 'type' => 'string' ) ) ) ) ) ), 'permission_callback' => $can_edit, 'execute_callback' => function ( $in ) { $GLOBALS['ran'][] = array( 'manage', $in['post_id'] ?? 0 ); return array( 'status' => 'ok', 'post_id' => $in['post_id'] ?? 0 ); }, 'meta' => $public( false, true ) ) );
wp_register_ability( 'elementor/publish-document', array( 'label' => 'Publicar', 'category' => 'elementor', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ) ) ), 'permission_callback' => $can_edit, 'execute_callback' => function ( $in ) { $GLOBALS['ran'][] = array( 'publish', $in['post_id'] ?? 0 ); return array( 'status' => 'publish' ); }, 'meta' => $public( false, true ) ) );
Digitalisimo_AI_Assistant_Ability::register();

$req    = function ( array $params = array() ) { return new WP_REST_Request( $params, array( 'X-Digitalisimo-Assistant' => wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) ) ); };
$events = function ( $response ) { check( $response instanceof WP_REST_Response, 'La ruta responde con eventos: ' . ( is_wp_error( $response ) ? $response->get_error_message() : gettype( $response ) ) ); return $response->data['events']; };
$of     = function ( $events, $type ) { return array_values( array_filter( $events, function ( $e ) use ( $type ) { return $e['type'] === $type; } ) ); };
$call   = function ( $name, array $input = array(), $id = null ) { static $n = 0; return array( 'id' => $id ?: 'toolu_' . ( ++$n ), 'name' => $name, 'input' => $input ); };
$GLOBALS['script'] = array(); $GLOBALS['model_calls'] = array();
add_filter( 'digitalisimo_ai_assistant_pre_model', function ( $pre, $request ) { $GLOBALS['model_calls'][] = $request; return $GLOBALS['script'] ? array_shift( $GLOBALS['script'] ) : 'Listo.'; } );
$texts = function ( array $request ) { $out = array(); foreach ( $request['messages'] as $m ) { if ( 'user' === $m['role'] ) $out[] = $m['text']; } return implode( "\n", $out ); };
$paired = function ( array $messages ) { $open = array(); foreach ( $messages as $m ) { if ( 'assistant' === $m['role'] ) foreach ( $m['calls'] as $c ) $open[ $c['id'] ] = true; if ( 'tool' === $m['role'] ) { if ( ! isset( $open[ $m['call'] ] ) ) return false; unset( $open[ $m['call'] ] ); } } return array() === $open && 'user' === $messages[0]['role']; };

/* ---------------- G: el modelo del Asistente ---------------- */
$t = Digitalisimo_AI_Assistant_Model::target( 'auto' );
check( 'anthropic' === $t['provider'] && 'claude-opus-5-5' === $t['model'], 'G1: con Anthropic, el Asistente usa claude-opus-5-5 por defecto: ' . $t['model'] );
check( 'claude-sonnet-5-5' === Digitalisimo_AI::default_model( 'anthropic' ) && 'claude-sonnet-5-5' === Digitalisimo_AI::DEFAULT_MODELS['anthropic'], 'G1: las demás herramientas siguen con su modelo (DEFAULT_MODELS no cambia)' );
$GLOBALS['options']['digitalisimo_ai_options']['assistant_models'] = array( 'anthropic' => 'claude-sonnet-5-5' );
check( 'claude-sonnet-5-5' === Digitalisimo_AI_Assistant_Model::target( 'auto' )['model'], 'G2: si el dueño elige Sonnet en el selector, se respeta' );
$GLOBALS['options']['digitalisimo_ai_options']['profiles']['content'] = array( 'provider' => 'anthropic', 'model' => 'claude-perfil', 'fallback' => '' );
$GLOBALS['options']['digitalisimo_ai_options']['assistant_models'] = array( 'anthropic' => 'profile' );
check( 'claude-perfil' === Digitalisimo_AI_Assistant_Model::target( 'auto' )['model'], 'G3: «Igual que el perfil de uso» usa el modelo del perfil' );
$GLOBALS['options']['digitalisimo_ai_options']['assistant_models'] = array();
check( 'claude-opus-5-5' === Digitalisimo_AI_Assistant_Model::target( 'auto' )['model'], 'G3: sin elección, el recomendado aunque el perfil de contenido tenga otro modelo' );
$GLOBALS['options']['digitalisimo_ai_options']['profiles']['content'] = array( 'provider' => 'auto', 'model' => '', 'fallback' => '' );
ob_start(); Digitalisimo_AI::assistant_model_fields( Digitalisimo_AI::get() ); $html = ob_get_clean();
check( false !== strpos( $html, 'name="assistant_models[anthropic]"' ) && false !== strpos( $html, 'Recomendado: claude-opus-5-5' ) && false !== strpos( $html, 'cuesta más que Claude Sonnet' ), 'G4: el selector está en IA Tools, recomienda Opus y avisa que cuesta más que Sonnet' );
$_POST = array( 'assistant_models' => array( 'anthropic' => 'custom', 'openai' => 'profile', 'google' => '<script>' ), 'assistant_models_custom' => array( 'anthropic' => 'claude-opus-5-5-fast' ) );
$sanitize = new ReflectionMethod( 'Digitalisimo_AI', 'sanitize_values' );
$sanitize->setAccessible( true );
$saved = $sanitize->invoke( null, Digitalisimo_AI::get() );
$_POST = array();
check( array( 'anthropic' => 'claude-opus-5-5-fast', 'openai' => 'profile' ) === $saved['assistant_models'], 'G4: se guarda lo elegido (otro modelo escrito, perfil) y lo inválido vuelve al recomendado: ' . json_encode( $saved['assistant_models'] ) );
$b = Digitalisimo_AI_Assistant_Model::build( Digitalisimo_AI_Assistant_Model::target( 'auto' ), 'S', array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( 'claude-opus-5-5' === $b['body']['model'] && array( 'type' => 'ephemeral' ) === $b['body']['cache_control'] && $b['body']['max_tokens'] >= 16000, 'G5: la petición a Anthropic va con Opus, caché automática (la conversación completa se reenvía) y sitio para pensar' );
check( 'adaptive' === $b['body']['thinking']['type'] && 'updates' === $b['body']['thinking']['display'] && array( 'prefix_mismatch_behavior' => 'drop_block' ) === $b['body']['thinking']['block_binding'] && 'thinking-display-updates-2026-08-18,thinking-binding-controls-2026-08-01' === $b['headers']['anthropic-beta'], 'G6: con Opus 5.5 se piden las notas de avance, y los bloques reenviados que ya no cuadran se descartan en vez de rechazar la petición' );
check( array( 'effort' => 'high' ) === $b['body']['output_config'], 'G6: el Asistente con Opus 5.5 trabaja con esfuerzo alto (el de fábrica es medium)' );
$old = Digitalisimo_AI_Assistant_Model::build( array( 'provider' => 'anthropic', 'model' => 'claude-sonnet-4-5', 'key' => 'k' ), 'S', array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( ! isset( $old['body']['thinking'] ) && ! isset( $old['headers']['anthropic-beta'] ) && ! isset( $old['body']['output_config'] ), 'G6: a un modelo anterior no se le piden ni se fija el esfuerzo' );
$p = Digitalisimo_AI_Assistant_Model::parse( 'anthropic', array( 'stop_reason' => 'tool_use', 'content' => array( array( 'type' => 'thinking', 'thinking' => '', 'signature' => 'x' ), array( 'type' => 'thinking', 'thinking' => 'Creo la landing en borrador con el producto.', 'signature' => 'y' ), array( 'type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'demo-create-note', 'input' => array() ) ) ) );
check( '' === $p['text'] && 'Creo la landing en borrador con el producto.' === $p['notes'] && 2 === count( $p['thinking'] ) && 'y' === $p['thinking'][1]['signature'] && 1 === count( $p['calls'] ), 'G6: la nota de avance es para la persona (notes) y los bloques thinking se guardan tal cual, sin pasar a texto' );
// Reenvío: los bloques thinking del turno en curso van tal cual y primero; los de turnos anteriores, no.
$neutral = array(
	array( 'role' => 'user', 'text' => 'uno' ),
	array( 'role' => 'assistant', 'text' => 'Hecho.', 'calls' => array(), 'thinking' => array( array( 'type' => 'thinking', 'thinking' => '', 'signature' => 'VIEJA' ) ) ),
	array( 'role' => 'user', 'text' => 'dos' ),
	array( 'role' => 'assistant', 'text' => '', 'calls' => array( array( 'id' => 'toolu_9', 'name' => 'demo-create-note', 'input' => array(), 'sig' => '' ) ), 'thinking' => array( array( 'type' => 'thinking', 'thinking' => 'Nota', 'signature' => 'NUEVA' ), array( 'type' => 'redacted_thinking', 'data' => 'ZZZ' ) ) ),
	array( 'role' => 'tool', 'call' => 'toolu_9', 'name' => 'demo-create-note', 'content' => '{}' ),
);
$stored = array( 'messages' => array() );
foreach ( $neutral as $m ) { $m['role'] = $m['role']; if ( 'assistant' === $m['role'] ) foreach ( $m['calls'] as $i => $c ) $m['calls'][ $i ]['ability'] = 'demo/create-note'; if ( 'tool' === $m['role'] ) $m['ability'] = 'demo/create-note'; $stored['messages'][] = $m; }
$fm = Digitalisimo_AI_Assistant_History::for_model( $stored, Digitalisimo_AI_Assistant_Tools::catalog() );
check( ! isset( $fm[1]['thinking'] ) && 'NUEVA' === $fm[3]['thinking'][0]['signature'], 'G6: al modelo van sólo los bloques thinking del turno en curso' );
$body = Digitalisimo_AI_Assistant_Model::build( Digitalisimo_AI_Assistant_Model::target( 'auto' ), 'S', $fm, array() )['body'];
$last_assistant = $body['messages'][3];
check( 'assistant' === $last_assistant['role'] && 'thinking' === $last_assistant['content'][0]['type'] && 'NUEVA' === $last_assistant['content'][0]['signature'] && 'Nota' === $last_assistant['content'][0]['thinking'] && 'redacted_thinking' === $last_assistant['content'][1]['type'] && 'tool_use' === $last_assistant['content'][2]['type'] && false === strpos( json_encode( $body ), 'VIEJA' ), 'G6: Anthropic recibe esos bloques tal cual, antes del tool_use (y la nota nunca como texto)' );
check( '' === Digitalisimo_AI_Assistant_Model::parse( 'anthropic', array( 'content' => array( array( 'type' => 'thinking', 'thinking' => Digitalisimo_AI_Assistant_Model::UPDATES_CUT ) ) ) )['text'], 'G6: la marca de nota cortada no se muestra' );
remove_all_filters( 'digitalisimo_ai_assistant_pre_model' );
$GLOBALS['http'] = array();
$GLOBALS['http_replies'] = array( array( 'code' => 400, 'body' => '{"type":"error","error":{"type":"invalid_request_error","message":"thinking.display: Input should be \'summarized\' or \'omitted\'"}}' ), array( 'code' => 200, 'body' => json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => 'Hola' ) ), 'stop_reason' => 'end_turn' ) ) ) );
$r = Digitalisimo_AI_Assistant_Model::chat( Digitalisimo_AI_Assistant_Model::target( 'auto' ), 'S', array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( 'Hola' === $r['text'] && 2 === count( $GLOBALS['http'] ) && ! isset( json_decode( $GLOBALS['http'][1]['args']['body'], true )['thinking'] ) && ! isset( json_decode( $GLOBALS['http'][1]['args']['body'], true )['output_config'] ) && ! isset( $GLOBALS['http'][1]['args']['headers']['anthropic-beta'] ), 'G6: si la API no las acepta, se repite una vez sin ellas' );
$GLOBALS['http'] = array();
$GLOBALS['http_replies'] = array( array( 'code' => 400, 'body' => '{"type":"error","error":{"type":"invalid_request_error","message":"thinking.display: unknown"}}' ), array( 'code' => 400, 'body' => '{"type":"error","error":{"type":"invalid_request_error","message":"messages.1.content.0: Invalid `signature` in `thinking` block."}}' ), array( 'code' => 200, 'body' => json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => 'Sigo' ) ), 'stop_reason' => 'end_turn' ) ) ) );
$r = Digitalisimo_AI_Assistant_Model::chat( Digitalisimo_AI_Assistant_Model::target( 'auto' ), 'S', $fm, array() );
check( 'Sigo' === $r['text'] && 3 === count( $GLOBALS['http'] ) && false === strpos( $GLOBALS['http'][2]['args']['body'], '"thinking"' ), 'G6: si la API rechaza un bloque thinking reenviado, se repite una vez sin reenviarlos' );
$woo = array( 'name' => 'digitalisimo-woo/products', 'annotations' => array( 'readonly' => false, 'destructive' => true ), 'schema' => array( 'type' => 'object', 'properties' => array( 'action' => array( 'type' => 'string', 'enum' => array( 'list', 'get', 'create', 'update', 'delete', 'batch' ) ), 'resource' => array( 'type' => 'string' ) ) ) );
check( 'write' === Digitalisimo_AI_Assistant_Tools::kind( $woo, array( 'action' => 'update', 'product_id' => 13, 'data' => array( 'sale_price' => '7500' ) ) ) && 'write' === Digitalisimo_AI_Assistant_Tools::kind( $woo, array( 'action' => 'create' ) ), 'G8: poner un precio de oferta (update) en la tienda pide confirmación normal, sin casilla de destructiva (así un «sí» lo confirma)' );
check( 'destructive' === Digitalisimo_AI_Assistant_Tools::kind( $woo, array( 'action' => 'delete' ) ) && 'destructive' === Digitalisimo_AI_Assistant_Tools::kind( $woo, array( 'action' => 'batch' ) ) && 'destructive' === Digitalisimo_AI_Assistant_Tools::kind( array_merge( $woo, array( 'name' => 'digitalisimo-woo/orders' ) ), array( 'action' => 'create', 'resource' => 'refund' ) ) && 'destructive' === Digitalisimo_AI_Assistant_Tools::kind( array_merge( $woo, array( 'name' => 'otro/products' ) ), array( 'action' => 'update' ) ), 'G8: borrar, lotes, reembolsos y lo de plugins ajenos siguen siendo destructivos' );
foreach ( array( array( 'digitalisimo-woo/products', null, 'write' ), array( 'digitalisimo-woo/products', 'variation', 'write' ), array( 'digitalisimo-woo/catalog', 'category', 'write' ), array( 'digitalisimo-woo/catalog', 'attribute_term', 'write' ), array( 'digitalisimo-woo/catalog', 'review', 'destructive' ), array( 'digitalisimo-woo/orders', 'order', 'destructive' ), array( 'digitalisimo-woo/orders', 'note', 'destructive' ), array( 'digitalisimo-woo/orders', 'refund', 'destructive' ), array( 'digitalisimo-woo/orders', null, 'destructive' ), array( 'digitalisimo-woo/customers', 'customer', 'destructive' ), array( 'digitalisimo-woo/coupons', 'coupon', 'destructive' ), array( 'digitalisimo-woo/coupons', null, 'destructive' ), array( 'digitalisimo-woo/store-settings', 'setting', 'destructive' ), array( 'digitalisimo-woo/store-settings', 'shipping_zone', 'destructive' ), array( 'digitalisimo-woo/products', array( 'x' ), 'destructive' ) ) as $case ) {
	foreach ( array( 'create', 'update' ) as $act ) {
		$in = array( 'action' => $act );
		if ( null !== $case[1] ) $in['resource'] = $case[1];
		check( $case[2] === Digitalisimo_AI_Assistant_Tools::kind( array_merge( $woo, array( 'name' => $case[0] ) ), $in ), "G9: {$case[0]} " . json_encode( $in ) . " es {$case[2]}" );
	}
}
foreach ( array( array( array( 'sale_price' => '7500' ), 'write' ), array( array( 'status' => 'draft', 'name' => 'X' ), 'write' ), array( array( 'meta_data' => array( array( 'key' => '_x', 'value' => 1 ) ) ), 'destructive' ), array( array( 'downloads' => array( array( 'file' => 'https://x.test/a.zip' ) ) ), 'destructive' ), array( array( 'status' => 'publish' ), 'destructive' ), array( array( 'status' => 'private' ), 'destructive' ) ) as $case ) {
	check( $case[1] === Digitalisimo_AI_Assistant_Tools::kind( $woo, array( 'action' => 'update', 'product_id' => 13, 'data' => $case[0] ) ), 'G10: productos update con ' . json_encode( $case[0] ) . ' es ' . $case[1] );
}
check( 'destructive' === Digitalisimo_AI_Assistant_Tools::kind( array_merge( $woo, array( 'name' => 'digitalisimo-woo/customers' ) ), array( 'action' => 'update' ) ) && 'destructive' === Digitalisimo_AI_Assistant_Tools::kind( $woo, array( 'action' => 'update', 'resource' => 'customer' ) ) && 'destructive' === Digitalisimo_AI_Assistant_Tools::kind( array_merge( $woo, array( 'name' => 'elementor/manage-component' ) ), array( 'action' => 'update' ) ) && 'destructive' === Digitalisimo_AI_Assistant_Tools::kind( array_merge( $woo, array( 'name' => 'digitalisimo-sales/x' ) ), array( 'action' => 'update' ) ), 'G8: sólo la tienda de DIGITALÍSIMO baja a escritura; clientes, usuarios y cualquier otra herramienta (elementor/manage-component update) siguen destructivas' );
check( 'read' === Digitalisimo_AI_Assistant_Tools::kind( array( 'name' => 'woocommerce/products-query', 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'schema' => array() ), array() ), 'G7: leer productos con las Abilities del núcleo de WooCommerce no pide confirmación' );
add_filter( 'digitalisimo_ai_assistant_pre_model', function ( $pre, $request ) { $GLOBALS['model_calls'][] = $request; return $GLOBALS['script'] ? array_shift( $GLOBALS['script'] ) : 'Listo.'; } );

/* ---------------- K: la conversación del caso ---------------- */
$GLOBALS['options']['wp_page_for_privacy_policy'] = 0;
$GLOBALS['script'] = array( 'Puedo crear landings, revisar productos, SEO…' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'En qué puedes ayudarme' ) ) ) );
$cid = $ev[0]['id'];
$GLOBALS['script'] = array( array( 'text' => 'Busco el producto.', 'calls' => array( $call( 'demo-big-read', array( 'n' => 1 ) ), $call( 'demo-big-read', array( 'n' => 2 ) ) ) ), 'No existe Impact5000. ¿Qué producto usamos?' );
$events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'créame una landing para blackfriday con la promoción del impact5000 a $1,000 MXN', 'conversation' => $cid ) ) ) );
$GLOBALS['script'] = array( 'Encontré el Purificador y Alcalinizador de Agua sobre tarja Negro ROTOPLAS (ID 252, $10,000). Uso la plantilla venta corta.' );
$events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'que la promo sea para el purificador alcalinizador de 10000 a 7500', 'conversation' => $cid ) ) ) );
// Turno 4: 19 consultas grandes en un paso, y después el modelo sigue.
$nineteen = array();
for ( $i = 0; $i < 19; $i++ ) $nineteen[] = $call( 'demo-big-read', array( 'n' => 100 + $i ) );
$GLOBALS['script'] = array( array( 'text' => 'Reviso el sitio.', 'calls' => $nineteen ), 'Listo para crear.' );
$before = count( $GLOBALS['model_calls'] );
$events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'créala, la oferta es de hoy a fin de mes, el número es el de este sitio web, ya todo está en políticas', 'conversation' => $cid ) ) ) );
$after19 = $GLOBALS['model_calls'][ $before + 1 ];
$tool_count = count( array_filter( $after19['messages'], function ( $m ) { return 'tool' === $m['role']; } ) );
check( 19 <= $tool_count && strlen( json_encode( $after19['messages'] ) ) > 200000, 'K1: tras las 19 consultas, el modelo recibe todos sus resultados (' . $tool_count . ')' );
$all = $texts( $after19 );
check( false !== strpos( $all, 'purificador' ) && false !== strpos( $all, '7500' ) && false !== strpos( $all, 'fin de mes' ) && false !== strpos( $all, 'impact5000' ), 'K1: y AÚN lo que dijo la persona: producto, precio y fecha (lo que el código viejo olvidaba)' );
check( $paired( $after19['messages'] ), 'K1: cada llamada con su resultado y empezando en un mensaje de la persona' );
$GLOBALS['script'] = array( 'Hecho.' );
$events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'créala primero luego agregamos eso', 'conversation' => $cid ) ) ) );
$next = end( $GLOBALS['model_calls'] );
$assistant_texts = implode( "\n", array_column( array_filter( $next['messages'], function ( $m ) { return 'assistant' === $m['role']; } ), 'text' ) );
check( false !== strpos( $texts( $next ), 'purificador' ) && false !== strpos( $texts( $next ), '7500' ) && false !== strpos( $assistant_texts, 'ID 252' ), 'K2: en el turno siguiente siguen el producto, el precio y lo que el asistente encontró (ID 252)' );
check( 'claude-opus-5-5' === $next['model'], 'K2: y la petición va al modelo del selector' );
// K3 Cerca del límite real: primero se compactan los resultados viejos (el turno en curso nunca).
add_filter( 'digitalisimo_ai_assistant_context_chars', function () { return 120000; } );
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'demo-big-read', array( 'n' => 900 ) ) ) ), 'Sigo.' );
$events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'revisa la estructura otra vez', 'conversation' => $cid ) ) ) );
$small = end( $GLOBALS['model_calls'] );
$json  = json_encode( $small['messages'], JSON_UNESCAPED_UNICODE );
$last  = end( $small['messages'] );
check( strlen( $json ) <= 130000 && false !== strpos( $json, '[resultado anterior recortado:' ) && 'tool' === $last['role'] && false === strpos( $last['content'], '[resultado anterior recortado' ), 'K3: cerca del límite, los resultados viejos se recortan con su marca y el del turno en curso va entero' );
check( false !== strpos( $texts( $small ), 'purificador' ) && false !== strpos( $texts( $small ), '7500' ) && false !== strpos( $texts( $small ), 'fin de mes' ) && $paired( $small['messages'] ), 'K3: todo lo que dijo la persona sigue y cada llamada con su resultado' );
$c2 = json_decode( Digitalisimo_AI_Assistant_History::compact( Digitalisimo_AI_Assistant_Tools::as_data( 'demo/big-read', array( 'status' => 'ok', 'data' => array( 'x' => str_repeat( 'á', 9000 ) ) ) ), 1500 ), true );
check( is_array( $c2 ) && Digitalisimo_AI_Assistant_Tools::DATA_NOTICE === $c2['aviso'] && 'demo/big-read' === $c2['herramienta'] && 0 === strpos( $c2['recortado'], '[resultado anterior recortado: ' ), 'K3: un resultado compactado sigue siendo JSON, marcado como dato y dice cuánto se recortó' );
// K4 Aun más justo: los turnos más viejos pasan a un resumen con todo lo que dijo la persona.
remove_all_filters( 'digitalisimo_ai_assistant_context_chars' );
add_filter( 'digitalisimo_ai_assistant_context_chars', function () { return 25000; } );
$GLOBALS['script'] = array( 'Vale.' );
$events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'y el título', 'conversation' => $cid ) ) ) );
$tiny = end( $GLOBALS['model_calls'] );
check( 0 === strpos( $tiny['messages'][0]['text'], '[Resumen de la parte más antigua' ) && false !== strpos( $texts( $tiny ), 'purificador alcalinizador de 10000 a 7500' ) && false !== strpos( $texts( $tiny ), 'de hoy a fin de mes' ) && $paired( $tiny['messages'] ) && strlen( json_encode( $tiny['messages'] ) ) < 60000, 'K4: con el contexto lleno, lo más viejo va como resumen que conserva lo dicho' );
remove_all_filters( 'digitalisimo_ai_assistant_context_chars' );
// K5 Lo guardado: pasado MAX_MESSAGES, lo más viejo pasa a un resumen (no se pierde lo que dijo la persona).
$conv = Digitalisimo_AI_Assistant_History::get( $cid, 1 );
for ( $i = 0; $i < Digitalisimo_AI_Assistant_History::MAX_MESSAGES; $i++ ) $conv['messages'][] = Digitalisimo_AI_Assistant_History::message( 'user', array( 'text' => 'relleno ' . $i ) );
Digitalisimo_AI_Assistant_History::save( $conv );
$conv = Digitalisimo_AI_Assistant_History::get( $cid, 1 );
check( 'summary' === $conv['messages'][0]['role'] && false !== strpos( $conv['messages'][0]['text'], 'purificador alcalinizador de 10000 a 7500' ) && count( $conv['messages'] ) <= Digitalisimo_AI_Assistant_History::MAX_MESSAGES + 1, 'K5: al recortar lo guardado, lo dicho pasa a un resumen' );
check( null === Digitalisimo_AI_Assistant_History::display( $conv['messages'][0] ) && false !== strpos( Digitalisimo_AI_Assistant_History::for_model( $conv, array() )[0]['text'], 'purificador' ), 'K5: el resumen no se muestra en pantalla pero el modelo lo recibe' );
check( Digitalisimo_AI_Assistant_History::context_chars( 'anthropic', 'claude-opus-5-5' ) >= 1500000 && Digitalisimo_AI_Assistant_History::context_chars( 'openai', 'gpt-4o-mini' ) < 400000 && Digitalisimo_AI_Assistant_History::context_chars( 'x', 'desconocido' ) === Digitalisimo_AI_Assistant_History::CONTEXT_DEFAULT, 'K6: el presupuesto es el del contexto real de cada modelo' );

/* ---------------- Y: confirmar escribiendo ---------------- */
foreach ( array( 'sí hazlo', 'Sí, créala', 'dale', 'ok', 'confirmo', 'SÍ', 'sí por favor', 'de acuerdo', 'adelante', 'sí, hazlo ya', 'ok ya', 'sí!' ) as $yes ) check( Digitalisimo_AI_Assistant::affirmation( $yes ), "Y0: «{$yes}» confirma" );
foreach ( array( 'no', 'sí, pero cambia el precio', 'es una nueva landing, no modificas templates verdad', 'mejor no', 'créala primero luego agregamos eso', '¿qué vas a hacer?', 'espera', 'ok no', 'ok gracias', 'perfecto gracias', 'correcto', 'perfecto', 'listo', 'todo bien', 'gracias', 'Crea', 'publica', 'sí crea otra', '¿sí?', '¿ok?', 'ok?', 'sí?' ) as $no ) check( ! Digitalisimo_AI_Assistant::affirmation( $no ), "Y0: «{$no}» no confirma" );
$fresh = function ( array $calls, $message ) use ( $req, $events ) { $GLOBALS['script'] = array( array( 'text' => 'Voy.', 'calls' => $calls ) ); $ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => $message ) ) ) ); return $ev; };
// Y1 Una acción pendiente, escritura, sin nada leído: «sí hazlo» la ejecuta (la guardada, con su bitácora).
$GLOBALS['created_notes'] = array();
$ev = $fresh( array( $call( 'demo-create-note', array( 'title' => 'Nota del caso' ) ) ), 'crea la nota' );
$cy = $ev[0]['id'];
check( 1 === count( $of( $ev, 'confirm' ) ) && false === $of( $ev, 'confirm' )[0]['action']['after_read'] && array() === $GLOBALS['created_notes'], 'Y1: escribir algo que no es un borrador pide confirmación' );
$GLOBALS['script'] = array( 'Creada.' );
$calls_before = count( $GLOBALS['model_calls'] );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'sí hazlo', 'conversation' => $cy ) ) ) );
check( array( array( 'title' => 'Nota del caso' ) ) === $GLOBALS['created_notes'] && 'ok' === $of( $ev, 'tool' )[0]['message']['status'], 'Y1: «sí hazlo» ejecuta la acción pendiente (la guardada), una vez' );
check( $calls_before + 1 === count( $GLOBALS['model_calls'] ) && 'Creada.' === $of( $ev, 'message' )[0]['message']['text'], 'Y1: y el modelo sigue' );
$sent = end( $GLOBALS['model_calls'] )['messages'];
check( 'tool' === $sent[ count( $sent ) - 2 ]['role'] && 'user' === end( $sent )['role'] && 'sí hazlo' === end( $sent )['text'] && $paired( $sent ), 'Y1: el modelo ve el resultado y después el «sí hazlo»' );
$log = get_option( Digitalisimo_AI_Assistant_Tools::LOG );
check( 'demo/create-note' === end( $log )['ability'] && 'ok' === end( $log )['result'] && 'write' === end( $log )['kind'], 'Y1: queda en la bitácora como la escritura confirmada' );
check( array() === Digitalisimo_AI_Assistant_History::get( $cy, 1 )['pending'], 'Y1: no queda nada pendiente' );
// Y2 Con un «sí, pero…» se cancela, como antes.
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'demo-create-note', array( 'title' => 'Otra' ) ) ) ), 'Cambio el precio.' );
$events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'crea otra', 'conversation' => $cy ) ) ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'sí, pero cambia el precio', 'conversation' => $cy ) ) ) );
check( 1 === count( $GLOBALS['created_notes'] ) && 'cancelled' === $of( $ev, 'tool' )[0]['message']['status'], 'Y2: «sí, pero…» no ejecuta: lo pendiente se cancela y el modelo atiende el cambio' );
// Y3 Lo destructivo: «sí» no basta.
$GLOBALS['deleted_notes'] = array();
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'demo-delete-note', array( 'id' => 7 ) ) ) ) );
$events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'borra la nota', 'conversation' => $cy ) ) ) );
$calls_before = count( $GLOBALS['model_calls'] );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'sí', 'conversation' => $cy ) ) ) );
$c = Digitalisimo_AI_Assistant_History::get( $cy, 1 );
check( array() === $GLOBALS['deleted_notes'] && 1 === count( $c['pending'] ) && $calls_before === count( $GLOBALS['model_calls'] ) && false !== strpos( $of( $ev, 'message' )[0]['message']['text'], 'vuelta atrás' ), 'Y3: con una acción destructiva, «sí» no la ejecuta: sigue pendiente de botón y casilla' );
check( 'sí' !== end( $c['messages'] )['text'] || 'user' !== end( $c['messages'] )['role'], 'Y3: y el «sí» no queda suelto tras la llamada sin resultado' );
$events( Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $cy, 'action' => $c['pending'][0]['id'], 'decision' => 'deny' ) ) ) );
// Y4 Caducada: «sí» no la revive.
$ev = $fresh( array( $call( 'demo-create-note', array( 'title' => 'Vieja' ) ) ), 'crea la vieja' );
$cv = $ev[0]['id'];
$c = Digitalisimo_AI_Assistant_History::get( $cv, 1 );
$c['pending'][0]['created'] = time() - 2 * HOUR_IN_SECONDS;
Digitalisimo_AI_Assistant_History::save( $c );
$GLOBALS['script'] = array( 'Vale.' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'sí', 'conversation' => $cv ) ) ) );
check( 1 === count( $GLOBALS['created_notes'] ) && 'cancelled' === $of( $ev, 'tool' )[0]['message']['status'], 'Y4: una acción caducada no se ejecuta con «sí»' );
// Y5 Sin permiso: «sí» no salta el permiso de la ability.
$ev = $fresh( array( $call( 'demo-create-note', array( 'title' => 'Sin permiso' ) ) ), 'crea sin permiso' );
$cp = $ev[0]['id'];
$GLOBALS['abilities']['demo/create-note']->args['permission_callback'] = function () { return current_user_can( 'manage_options' ); };
$caps = $GLOBALS['users'][1]['caps'];
$GLOBALS['users'][1]['caps'] = array( 'edit_posts', 'read' );
$GLOBALS['script'] = array( 'Vale.' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'sí', 'conversation' => $cp ) ) ) );
check( 1 === count( $GLOBALS['created_notes'] ) && 'denied' === $of( $ev, 'tool' )[0]['message']['status'], 'Y5: «sí» pasa por el mismo permiso: sin él, no se ejecuta' );
$GLOBALS['abilities']['demo/create-note']->args['permission_callback'] = $can_edit;
$GLOBALS['users'][1]['caps'] = $caps;
// Y6 Dos acciones pendientes: un «sí» escrito no dice cuál; no se ejecuta ninguna y siguen las dos tarjetas.
$ev = $fresh( array( $call( 'demo-create-note', array( 'title' => 'Una' ) ), $call( 'demo-create-note', array( 'title' => 'Dos' ) ) ), 'crea dos notas' );
$c2 = $ev[0]['id'];
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'sí hazlo', 'conversation' => $c2 ) ) ) );
check( 1 === count( $GLOBALS['created_notes'] ) && 2 === count( Digitalisimo_AI_Assistant_History::get( $c2, 1 )['pending'] ) && false !== strpos( $of( $ev, 'message' )[0]['message']['text'], '2 cambios esperando' ), 'Y6: con dos acciones pendientes, «sí hazlo» no ejecuta ninguna (botón)' );
// Y7 Una acción propuesta tras leer contenido del sitio: «sí» no basta (la persona revisa la tarjeta); el botón sí.
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'demo-big-read', array( 'n' => 1 ) ) ) ), array( 'text' => 'Propongo la nota.', 'calls' => array( $call( 'demo-create-note', array( 'title' => 'Tras leer' ) ) ) ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'lee y crea' ) ) ) );
$c3 = $ev[0]['id'];
check( true === $of( $ev, 'confirm' )[0]['action']['after_read'], 'Y7: la tarjeta avisa que llega tras leer' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'sí hazlo', 'conversation' => $c3 ) ) ) );
$c = Digitalisimo_AI_Assistant_History::get( $c3, 1 );
check( 1 === count( $GLOBALS['created_notes'] ) && 1 === count( $c['pending'] ) && false !== strpos( $of( $ev, 'message' )[0]['message']['text'], 'Revisa sus datos en la tarjeta' ), 'Y7: tras leer contenido del sitio, «sí hazlo» no ejecuta: la acción sigue en su tarjeta' );
$GLOBALS['script'] = array( 'Creada.' );
$events( Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $c3, 'action' => $c['pending'][0]['id'], 'decision' => 'approve' ) ) ) );
check( 2 === count( $GLOBALS['created_notes'] ), 'Y7: con el botón, sí' );
// Y8 Un verbo que no corresponde a la acción pendiente no la confirma: «publícala» con una creación pendiente.
$ev = $fresh( array( $call( 'demo-create-note', array( 'title' => 'Verbo' ) ) ), 'crea otra nota' );
$c4 = $ev[0]['id'];
$GLOBALS['script'] = array( 'Para publicar necesito otra acción.' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'publícala', 'conversation' => $c4 ) ) ) );
check( 2 === count( $GLOBALS['created_notes'] ) && 'cancelled' === $of( $ev, 'tool' )[0]['message']['status'], 'Y8: «publícala» no confirma una creación pendiente: es otra instrucción' );
$ev = $fresh( array( $call( 'demo-create-note', array( 'title' => 'Créala' ) ) ), 'crea la última' );
$GLOBALS['script'] = array( 'Hecha.' );
$events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'sí, créala', 'conversation' => $ev[0]['id'] ) ) ) );
check( 3 === count( $GLOBALS['created_notes'] ), 'Y8: «sí, créala» sí confirma una creación pendiente' );

/* ---------------- D: borradores sin confirmar ---------------- */
$GLOBALS['ran'] = array();
$GLOBALS['script'] = array( array( 'text' => 'Creo la landing en borrador.', 'calls' => array( $call( 'elementor-create-page', array( 'title' => 'Oferta Black Friday' ) ) ) ), 'Creada.' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'créala' ) ) ) );
$cd = $ev[0]['id'];
$created = $GLOBALS['ran'][0][1] ?? 0;
check( array( array( 'create', $created ) ) === $GLOBALS['ran'] && ! $of( $ev, 'confirm' ) && 'draft' === $of( $ev, 'tool' )[0]['message']['kind'] && 'ok' === $of( $ev, 'tool' )[0]['message']['status'], 'D1: crear una página nueva en borrador no pide confirmación' );
check( array( $created ) === Digitalisimo_AI_Assistant_History::get( $cd, 1 )['drafts'], 'D1: el borrador queda anotado en la conversación' );
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'elementor-build-composition', array( 'post_id' => $created, 'xml' => '<e-flexbox/>' ) ) ) ), 'Armada.' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'llénala', 'conversation' => $cd ) ) ) );
check( array( 'build', $created ) === end( $GLOBALS['ran'] ) && ! $of( $ev, 'confirm' ), 'D2: editar el borrador que creó esta conversación tampoco' );
$other = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Borrador ajeno' ) );
$published = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Inicio' ) );
foreach ( array( array( 'elementor-build-composition', array( 'post_id' => $other ), 'D3: un borrador que no creó esta conversación' ), array( 'elementor-build-composition', array( 'post_id' => $published ), 'D3: una página publicada' ), array( 'elementor-build-composition', array( 'post_id' => $created, 'settings' => array( 'post_status' => 'publish' ) ), 'D3: el borrador propio, pero cambiando su estado' ), array( 'elementor-publish-document', array( 'post_id' => $created ), 'D3: publicar el borrador' ) ) as $case ) {
	$n = count( $GLOBALS['ran'] );
	$GLOBALS['script'] = array( array( 'calls' => array( $call( $case[0], $case[1] ) ) ) );
	$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'haz el cambio', 'conversation' => $cd ) ) ) );
	check( 1 === count( $of( $ev, 'confirm' ) ) && $n === count( $GLOBALS['ran'] ), $case[2] . ' sigue pidiendo confirmación' );
	$events( Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $cd, 'action' => $of( $ev, 'confirm' )[0]['action']['id'], 'decision' => 'deny' ) ) ) );
}
// D3b «Crear» sobre una página publicada (revisión del caso plomerito 2): no se crea nada a escondidas ni se toca.
$n = count( $GLOBALS['ran'] );
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'elementor-create-page', array( 'title' => 'X', 'status' => 'publish', 'post_id' => $published ) ) ) ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'haz el cambio', 'conversation' => $cd ) ) ) );
$sent = json_decode( end( end( $GLOBALS['model_calls'] )['messages'] )['content'], true );
check( ! $of( $ev, 'confirm' ) && $n === count( $GLOBALS['ran'] ) && 'publish' === get_post( $published )->post_status && 'skipped' === $sent['estado'] && false !== strpos( $sent['error'], 'está publicada' ), 'D3b: «crear» sobre una página publicada no crea nada ni la toca, y el modelo sabe qué ofrecer' );
// D6 Tras leer contenido del sitio, editar el borrador con código (script, on…=) teniendo unfiltered_html pide tarjeta.
$GLOBALS['users'][1]['caps'][] = 'unfiltered_html';
$n = count( $GLOBALS['ran'] );
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'elementor-build-composition', array( 'post_id' => $created, 'xml' => '<e-html><script>fetch("//x.test")</script></e-html>' ) ) ) ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'pon el código', 'conversation' => $cd ) ) ) );
check( 1 === count( $of( $ev, 'confirm' ) ) && $n === count( $GLOBALS['ran'] ), 'D6: con lo leído en la conversación, código en la edición del borrador pasa por la tarjeta' );
$events( Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $cd, 'action' => $of( $ev, 'confirm' )[0]['action']['id'], 'decision' => 'deny' ) ) ) );
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'elementor-build-composition', array( 'post_id' => $created, 'settings' => array( 'title' => 'Oferta de fin de mes', 'html' => '<p>Hasta el <strong>30 de noviembre</strong></p>' ) ) ) ) ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'pon el título', 'conversation' => $cd ) ) ) );
check( ! $of( $ev, 'confirm' ) && $n + 1 === count( $GLOBALS['ran'] ), 'D6: con texto y HTML que wp_kses_post() deja igual, sigue directo' );
// D7 El criterio no reconoce código: compara con wp_kses_post(). Las evasiones de un filtro de patrones van a la tarjeta.
$evasions = array( '<a href="java&#115;cript:alert(1)">x</a>', "<a href=\"java\tscript:alert(1)\">x</a>", '<a href="javascript&colon;alert(1)">x</a>', '<meta http-equiv="refresh" content="0;url=//x.test">', '<base href="//x.test/">', '<style>@import url(//x.test/a.css)</style>' );
foreach ( $evasions as $evasion ) {
	check( Digitalisimo_AI_Assistant_Tools::needs_review( array( 'post_id' => 1, 'settings' => array( 'html' => $evasion ) ) ), 'D7: va a la tarjeta: ' . $evasion );
	$n = count( $GLOBALS['ran'] );
	$GLOBALS['script'] = array( array( 'calls' => array( $call( 'elementor-build-composition', array( 'post_id' => $created, 'settings' => array( 'html' => $evasion ) ) ) ) ) );
	$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'cámbialo', 'conversation' => $cd ) ) ) );
	check( 1 === count( $of( $ev, 'confirm' ) ) && $n === count( $GLOBALS['ran'] ), 'D7: y el Asistente no la ejecuta sin tarjeta: ' . $evasion );
	$events( Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $cd, 'action' => $of( $ev, 'confirm' )[0]['action']['id'], 'decision' => 'deny' ) ) ) );
}
// D8 Con los esquemas REALES: un borrador normal tras una lectura NO pide tarjeta (el XML de Elementor sólo trae
// etiquetas e-* con configuration-id; kses reescribe & y <br/> sin cambiar nada); un java&#115;cript: sí.
$composition = array(
	'post_id'        => $created,
	'xml_structure'  => '<e-flexbox configuration-id="hero"><e-heading configuration-id="title"></e-heading><e-paragraph configuration-id="p1"></e-paragraph><e-button configuration-id="btn"></e-button></e-flexbox>',
	'element_config' => array( 'title' => array( 'title' => 'Oferta & Black Friday' ), 'p1' => array( 'paragraph' => 'Antes <s>$10,000</s><br/>hoy <strong>$7,500</strong>' ), 'btn' => array( 'text' => '¡Lo quiero!', 'link' => array( 'destination' => 'https://wa.me/5218711234567?text=Hola%20quiero%20el%20purificador' ) ) ),
	'style'          => array( 'hero' => 'padding:40px 20px; &:hover{color:#fff} @media(--mobile){padding:20px}' ),
);
$operations = array( 'post_id' => $created, 'operations' => array( array( 'action' => 'update', 'element_id' => 'abc123', 'settings' => array( 'title' => "Precio: 'especial' & más", 'link' => array( 'destination' => 'tel:+528711234567' ) ), 'style' => 'color:red' ) ) );
$GLOBALS['users'][1]['caps'][] = 'unfiltered_html';
foreach ( array( array( 'elementor-build-composition', $composition, 'build-composition' ), array( 'elementor-manage-elements', $operations, 'manage-elements' ) ) as $case ) {
	check( ! Digitalisimo_AI_Assistant_Tools::needs_review( $case[1] ), 'D8: ' . $case[2] . ' normal no necesita revisión' );
	$n = count( $GLOBALS['ran'] );
	$GLOBALS['script'] = array( array( 'calls' => array( $call( $case[0], $case[1] ) ) ) );
	$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'arma la portada', 'conversation' => $cd ) ) ) );
	check( ! $of( $ev, 'confirm' ) && $n + 1 === count( $GLOBALS['ran'] ), 'D8: tras una lectura, ' . $case[2] . ' con el esquema real sobre el borrador propio corre sin tarjeta' );
}
$bad = array(
	'enlace java&#115;cript: en element_config' => array_replace_recursive( $composition, array( 'element_config' => array( 'btn' => array( 'link' => array( 'destination' => 'java&#115;cript:alert(1)' ) ) ) ) ),
	'<a href="java&#115;cript:"> en un párrafo' => array_replace_recursive( $composition, array( 'element_config' => array( 'p1' => array( 'paragraph' => 'Hola <a href="java&#115;cript:alert(1)">aquí</a>' ) ) ) ),
	'@import en el estilo'                      => array_replace_recursive( $composition, array( 'style' => array( 'hero' => '@import url(//x.test/a.css);' ) ) ),
	'XML con un atributo onload'                => array_replace( $composition, array( 'xml_structure' => '<e-flexbox configuration-id="hero" onload="alert(1)"></e-flexbox>' ) ),
	'javascript&colon; en manage-elements'       => array( 'post_id' => $created, 'operations' => array( array( 'action' => 'update', 'element_id' => 'abc123', 'settings' => array( 'link' => array( 'destination' => "java\tscript&colon;alert(1)" ) ) ) ) ),
);
foreach ( $bad as $what => $input ) {
	check( Digitalisimo_AI_Assistant_Tools::needs_review( $input ), 'D8: va a la tarjeta: ' . $what );
	$n = count( $GLOBALS['ran'] );
	$GLOBALS['script'] = array( array( 'calls' => array( $call( isset( $input['operations'] ) ? 'elementor-manage-elements' : 'elementor-build-composition', $input ) ) ) );
	$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'arma la portada', 'conversation' => $cd ) ) ) );
	check( 1 === count( $of( $ev, 'confirm' ) ) && $n === count( $GLOBALS['ran'] ), 'D8: y el Asistente no lo ejecuta sin tarjeta: ' . $what );
	$events( Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $cd, 'action' => $of( $ev, 'confirm' )[0]['action']['id'], 'decision' => 'deny' ) ) ) );
}
array_pop( $GLOBALS['users'][1]['caps'] );
// D9 La tarjeta marca «Código» con el mismo criterio y ve los importes que llegan como texto.
list( , , $risks ) = Digitalisimo_AI_Assistant_Tools::card( $bad['enlace java&#115;cript: en element_config'] );
check( false !== strpos( implode( ' | ', $risks ), 'Código o eventos' ), 'D9: la tarjeta avisa de código en un enlace java&#115;cript: ' . implode( ' | ', $risks ) );
list( , , $risks ) = Digitalisimo_AI_Assistant_Tools::card( $composition );
check( false === strpos( implode( ' | ', $risks ), 'Código o eventos' ), 'D9: y no en una composición normal: ' . implode( ' | ', $risks ) );
list( , , $risks ) = Digitalisimo_AI_Assistant_Tools::card( array( 'action' => 'update', 'product_id' => 13, 'data' => array( 'sale_price' => '7500', 'regular_price' => '10,000.00' ) ) );
check( false !== strpos( implode( ' | ', $risks ), 'sale_price = 7500' ) && false !== strpos( implode( ' | ', $risks ), 'regular_price = 10,000.00' ), 'D9: los importes que llegan como texto ("7500") salen en el resumen: ' . implode( ' | ', $risks ) );
$deep = 'texto'; for ( $i = 0; $i < 40; $i++ ) $deep = array( 'a' => $deep );
check( Digitalisimo_AI_Assistant_Tools::needs_review( array( 'post_id' => 1, 'settings' => $deep ) ), 'D7: una entrada más honda que el límite que se revisa va a la tarjeta' );
check( ! Digitalisimo_AI_Assistant_Tools::needs_review( array( 'post_id' => 1, 'title' => 'Oferta', 'n' => 3, 'ok' => true ) ), 'D7: texto y números normales no' );
array_pop( $GLOBALS['users'][1]['caps'] );
$GLOBALS['posts'][ $created ]->post_status = 'publish';
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'elementor-build-composition', array( 'post_id' => $created ) ) ) ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'otro cambio', 'conversation' => $cd ) ) ) );
check( 1 === count( $of( $ev, 'confirm' ) ), 'D4: si el borrador ya se publicó, editarlo vuelve a pedir confirmación' );
check( '' === Digitalisimo_AI_Assistant_Tools::draft_mode( Digitalisimo_AI_Assistant_Tools::catalog()['demo/create-note'], array( 'title' => 'x' ), array() ), 'D5: sólo las herramientas de la lista explícita (no cualquier escritura)' );

/* ---------------- T: límites y continuación ---------------- */
check( Digitalisimo_AI_Assistant::MAX_STEPS >= 24 && Digitalisimo_AI_Assistant::MAX_CALLS >= 60, 'T1: pasos y llamadas por turno generosos (' . Digitalisimo_AI_Assistant::MAX_STEPS . '/' . Digitalisimo_AI_Assistant::MAX_CALLS . ')' );
$GLOBALS['script'] = array();
for ( $i = 0; $i < 20; $i++ ) $GLOBALS['script'][] = array( 'calls' => array( $call( 'demo-big-read', array( 'n' => $i ) ), $call( 'demo-big-read', array( 'n' => 50 + $i ) ) ) );
$GLOBALS['script'][] = 'Landing terminada.';
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'arma la landing completa' ) ) ) );
$said = $of( $ev, 'message' );
check( 40 === count( $of( $ev, 'tool' ) ) && 'Landing terminada.' === end( $said )['message']['text'], 'T1: un turno de 21 pasos y 40 consultas termina (el código viejo paraba en 8 pasos)' );
add_filter( 'digitalisimo_ai_assistant_turn_budget', function () { return 0; } );
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'demo-big-read', array( 'n' => 1 ) ) ) ), array( 'calls' => array( $call( 'demo-big-read', array( 'n' => 2 ) ) ) ), 'Terminé.' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'trabajo largo' ) ) ) );
$ct = $ev[0]['id'];
check( 1 === count( $of( $ev, 'continue' ) ) && ! array_filter( $of( $ev, 'message' ), function ( $e ) { return false !== strpos( $e['message']['text'], 'escribe para seguir' ); } ), 'T2: sin tiempo, el turno se pausa con «continue» (no pide a la persona que escriba)' );
check( is_array( Digitalisimo_AI_Assistant_History::get( $ct, 1 )['resume'] ), 'T2: y deja guardado dónde iba' );
remove_all_filters( 'digitalisimo_ai_assistant_turn_budget' );
$ev = $events( Digitalisimo_AI_Assistant::rest_continue( $req( array( 'conversation' => $ct ) ) ) );
$said = $of( $ev, 'message' );
check( 'Terminé.' === end( $said )['message']['text'] && ! $of( $ev, 'continue' ) && null === Digitalisimo_AI_Assistant_History::get( $ct, 1 )['resume'], 'T2: assistant/continue sigue el mismo turno hasta terminar' );
$e = Digitalisimo_AI_Assistant::rest_continue( $req( array( 'conversation' => $ct ) ) );
check( is_wp_error( $e ) && 409 === $e->data['status'], 'T2: sin turno pausado no hay nada que continuar (409)' );
$GLOBALS['user'] = 2;
$e = Digitalisimo_AI_Assistant::rest_continue( $req( array( 'conversation' => $ct ) ) );
check( is_wp_error( $e ) && 404 === $e->data['status'], 'T2: nadie continúa la conversación de otra persona' );
$GLOBALS['user'] = 1;
Digitalisimo_AI_Assistant::routes();
check( isset( $GLOBALS['routes']['digitalisimo-ai/v1/assistant/continue'] ) && is_array( $GLOBALS['routes']['digitalisimo-ai/v1/assistant/continue']['permission_callback'] ), 'T2: la ruta de continuación tiene el permiso del Asistente' );
$ui = file_get_contents( dirname( __DIR__ ) . '/digitalisimo-ia-tools/assets/assistant/assistant.js' );
$ad = file_get_contents( dirname( __DIR__ ) . '/digitalisimo-ia-tools/assets/assistant/assistant-adapters.js' );
check( false !== strpos( $ui, "'continue' === event.type" ) && false !== strpos( $ui, 'adapters.model.resume(' ) && false !== strpos( $ad, "stream( 'continue', payload, onEvent )" ), 'T2: la pantalla sigue sola con resume() por la ruta continue' );
check( false !== strpos( $ui, 'datos de clientes y pedidos) se envía a la IA que te responde' ) && false !== strpos( file_get_contents( dirname( __DIR__ ) . '/docs/ASSISTANT.md' ), '**Privacidad.**' ), 'T3: la pantalla y la documentación dicen que consultar clientes y pedidos envía sus datos al proveedor de IA' );

/* ---------------- R: leer el sitio ---------------- */
$catalog = Digitalisimo_AI_Assistant_Tools::catalog();
check( isset( $catalog['digitalisimo-ia/read-page-text'], $catalog['digitalisimo-ia/site-contact'] ) && 'read' === Digitalisimo_AI_Assistant_Tools::kind( $catalog['digitalisimo-ia/read-page-text'], array() ) && 'read' === Digitalisimo_AI_Assistant_Tools::kind( $catalog['digitalisimo-ia/site-contact'], array() ), 'R0: las dos están en el catálogo del Asistente y son de sólo lectura (corren sin confirmar)' );
check( in_array( 'digitalisimo-ia/read-page-text', apply_filters( 'elementor/mcp/server/tools', array() ), true ) || ! isset( $GLOBALS['hooks']['elementor/mcp/server/tools'] ), 'R0: y el servidor MCP de Elements las ofrece' );
check( in_array( 'digitalisimo-ia/site-contact', Digitalisimo_AI_Assistant_Ability::server_tools( array() ), true ) && in_array( 'digitalisimo-ia/read-page-text', Digitalisimo_AI_Assistant_Ability::server_tools( array() ), true ), 'R0: server_tools() las suma al servidor MCP de Elements' );
$elementor = array(
	array( 'elType' => 'container', 'settings' => array( 'content_width' => 'boxed', 'background_color' => '#ffffff', 'background_color_b' => '#0B2A45', '_title' => '1 · La portada: logo, promesa y botón' ), 'elements' => array(
		array( 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Purificador y Alcalinizador ROTOPLAS', 'header_size' => 'h1', 'title_color' => '#000' ) ),
		array( 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Agua <strong>alcalina</strong> sin instalación complicada.</p><p>Precio de oferta: $7,500</p>' ) ),
		array( 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Pedir por WhatsApp', 'link' => array( 'url' => 'https://wa.me/5218711234567', 'is_external' => 'on' ), 'size' => 'lg' ) ),
		array( 'elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => array( 'icon_list' => array( array( '_id' => 'a1', 'text' => 'Garantía de 1 año', 'selected_icon' => array( 'value' => 'fas fa-check' ) ) ) ) ),
		array( 'elType' => 'e-heading', 'settings' => array( 'title' => array( '$$type' => 'string', 'value' => 'Título atómico v4' ), 'link' => array( '$$type' => 'link', 'value' => array( 'destination' => array( '$$type' => 'url', 'value' => 'https://sitio.test/aviso-de-privacidad/' ) ) ) ) ),
	) ),
);
$landing = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Landing', 'post_name' => 'landing', 'post_content' => '' ) );
update_post_meta( $landing, '_elementor_data', wp_slash( json_encode( $elementor ) ) );
$r = $GLOBALS['abilities']['digitalisimo-ia/read-page-text']->execute( array( 'id' => $landing ) );
check( is_array( $r ) && 'elementor' === $r['source'] && false !== strpos( $r['text'], 'Purificador y Alcalinizador ROTOPLAS' ) && false !== strpos( $r['text'], 'Agua alcalina sin instalación complicada.' ) && false !== strpos( $r['text'], 'Pedir por WhatsApp' ) && false !== strpos( $r['text'], 'Garantía de 1 año' ) && false !== strpos( $r['text'], 'Título atómico v4' ), 'R1: read-page-text devuelve el texto de los widgets de Elementor (clásicos y atómicos): ' . json_encode( $r['text'] ?? $r, JSON_UNESCAPED_UNICODE ) );
check( false === strpos( $r['text'], '<' ) && false === strpos( $r['text'], '#ffffff' ) && false === strpos( $r['text'], '#0B2A45' ) && false === strpos( $r['text'], 'La portada' ) && false === strpos( $r['text'], 'fas fa-check' ) && false === strpos( $r['text'], 'boxed' ), 'R1: sin HTML, valores de diseño ni las etiquetas internas del navegador de Elementor (_title)' );
check( in_array( 'https://wa.me/5218711234567', array_column( $r['links'], 'url' ), true ) && in_array( 'https://sitio.test/aviso-de-privacidad/', array_column( $r['links'], 'url' ), true ) && '5218711234567' === $r['whatsapp'][0]['number'], 'R1: con sus enlaces y el WhatsApp que lleva' );
$r = $GLOBALS['abilities']['digitalisimo-ia/read-page-text']->execute( array( 'url' => 'https://sitio.test/landing/' ) );
check( is_array( $r ) && $landing === $r['id'], 'R1: también por la URL del sitio' );
check( is_wp_error( $GLOBALS['abilities']['digitalisimo-ia/read-page-text']->execute( array( 'url' => 'https://otro.test/landing/' ) ) ), 'R1: una URL de otro sitio no' );
$blocks = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada', 'post_content' => '<h2>Beneficios</h2><p>Llámanos al <a href="tel:+52 871 123 4567">871 123 4567</a></p>' ) );
$r = $GLOBALS['abilities']['digitalisimo-ia/read-page-text']->execute( array( 'id' => $blocks ) );
check( 'content' === $r['source'] && "Beneficios\nLlámanos al 871 123 4567" === $r['text'] && '528711234567' === $r['phones'][0]['number'], 'R2: sin Elementor, el texto del contenido, con su teléfono' );
$coupon   = wp_insert_post( array( 'post_type' => 'shop_coupon', 'post_status' => 'publish', 'post_title' => 'DESCUENTO90' ) );
$feedback = wp_insert_post( array( 'post_type' => 'feedback', 'post_status' => 'publish', 'post_title' => 'Mensaje de contacto' ) );
check( is_wp_error( Digitalisimo_AI_Site_Reader::read_text( array( 'id' => $coupon ) ) ) && is_wp_error( Digitalisimo_AI_Site_Reader::read_text( array( 'id' => $feedback ) ) ), 'R3: ni cupones ni formularios recibidos (tipos que no se ven en el sitio), aunque estén publicados' );
$GLOBALS['posts'][ $footer_early = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Plantilla' ) ) ]->post_status = 'publish';
check( ! is_wp_error( Digitalisimo_AI_Site_Reader::read_text( array( 'id' => $footer_early ) ) ), 'R3: las plantillas de Elementor (cabecera, pie) sí' );
$private = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Privada' ) );
$GLOBALS['user'] = 3;
check( is_wp_error( $GLOBALS['abilities']['digitalisimo-ia/read-page-text']->execute( array( 'id' => $landing ) ) ), 'R3: sin edit_posts no se lee nada' );
$GLOBALS['user'] = 1;
check( is_wp_error( Digitalisimo_AI_Site_Reader::read_text( array( 'id' => $cid ) ) ), 'R3: nunca las conversaciones del Asistente' );
// site-contact: el WhatsApp en un enlace wa.me del pie (plantilla de Elementor) y las políticas reales.
$footer = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Pie' ) );
update_post_meta( $footer, '_elementor_template_type', 'footer' );
update_post_meta( $footer, '_elementor_data', wp_slash( json_encode( array( array( 'elType' => 'container', 'elements' => array( array( 'elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => array( 'icon_list' => array( array( '_id' => 'w', 'text' => 'Escríbenos', 'link' => array( 'url' => 'https://wa.me/5218719998877?text=Hola' ) ), array( '_id' => 'm', 'text' => 'ventas@plomerito.test', 'link' => array( 'url' => 'mailto:ventas@plomerito.test' ) ) ) ) ) ) ) ) ) ) );
$privacy = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Aviso de privacidad', 'post_name' => 'aviso-de-privacidad' ) );
$terms   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Términos y condiciones', 'post_name' => 'terminos-y-condiciones' ) );
$refund  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Política de devoluciones', 'post_name' => 'devoluciones' ) );
wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Política de reembolso (borrador)', 'post_name' => 'politica-de-reembolso' ) );
$GLOBALS['options']['wp_page_for_privacy_policy'] = $privacy;
$GLOBALS['options']['woocommerce_store_address']  = 'Av. Juárez 100';
$GLOBALS['options']['woocommerce_store_city']     = 'Torreón';
$c = $GLOBALS['abilities']['digitalisimo-ia/site-contact']->execute( array() );
check( is_array( $c ) && '5218719998877' === $c['whatsapp'][0]['number'] && 'https://wa.me/5218719998877' === $c['principal']['url'] && false !== strpos( $c['whatsapp'][0]['source'], 'pie' ), 'R4: site-contact encuentra el WhatsApp del enlace wa.me del pie: ' . json_encode( $c['whatsapp'] ?? $c, JSON_UNESCAPED_UNICODE ) );
check( in_array( 'ventas@plomerito.test', array_column( $c['emails'], 'email' ), true ) && false !== strpos( $c['address'], 'Torreón' ), 'R4: y el correo y la dirección' );
check( $privacy === $c['pages']['privacidad']['id'] && $terms === $c['pages']['terminos']['id'] && $refund === $c['pages']['reembolso']['id'] && 'https://sitio.test/devoluciones/' === $c['pages']['reembolso']['url'], 'R4: las páginas de políticas son las reales y publicadas (nunca un borrador ni una URL inventada): ' . json_encode( $c['pages'] ?? null, JSON_UNESCAPED_UNICODE ) );
// El Asistente la usa como lectura directa, sin tarjeta.
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'digitalisimo-ia-site-contact', array() ) ) ), 'El WhatsApp del sitio es 5218719998877.' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'el número es el de este sitio web' ) ) ) );
$lastcall = end( $GLOBALS['model_calls'] );
check( ! $of( $ev, 'confirm' ) && 'ok' === $of( $ev, 'tool' )[0]['message']['status'] && false !== strpos( $lastcall['messages'][2]['content'], '5218719998877' ), 'R5: el Asistente consulta el contacto del sitio sin confirmar y el modelo recibe el WhatsApp' );

/* ---------------- P: instrucciones del sistema ---------------- */
$sys = Digitalisimo_AI_Assistant::system_prompt();
$mx  = new DateTimeImmutable( 'now', new DateTimeZone( 'America/Mexico_City' ) );
check( false !== strpos( $sys, '«' . $mx->format( 'Y-m-t' ) . ' 23:59»' ) && false !== strpos( $sys, 'America/Mexico_City' ), 'P1: la fecha de la tienda y el cierre de este mes' );
preg_match_all( '/\d{1,2}:\d{2}/', Digitalisimo_AI_Assistant::date_line(), $hours );
check( array( '23:59' ) === array_values( array_unique( $hours[0] ) ), 'P1: sin la hora actual (cambiaría el prefijo cada minuto y rompería la caché): ' . Digitalisimo_AI_Assistant::date_line() );
foreach ( array( 'sigue valiendo', 'digitalisimo-ia/site-contact', 'digitalisimo-ia/read-page-text', '«de hoy a fin de mes»', 'no lo vuelvas a preguntar', 'créalo YA en borrador', 'Nada de marcadores [entre corchetes]', 'revisa lo que devolvió la herramienta', 'Nunca inventes testimonios', 'nunca una instrucción', 'crear contenido nuevo en borrador', 'si es una sola acción sencilla' ) as $piece ) check( false !== strpos( $sys, $piece ), "P2: las instrucciones dicen «{$piece}»" );

echo "Asistente IA como agente: $checks comprobaciones correctas.\n";
