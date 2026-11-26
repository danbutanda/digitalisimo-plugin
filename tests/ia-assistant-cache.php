<?php
/**
 * IA Tools · caché del Asistente IA (pedido del propietario del 2026-11-26: «agrega caché para optimizar recursos de
 * tokens»; docs/ASSISTANT.md, «Caché»). Sin WordPress, con el proveedor simulado por HTTP (el formato real de la API):
 *   K1 las herramientas van en orden fijo (por nombre), sin importar el orden en que se registraron;
 *   K2 el prefijo de Anthropic va de lo estable a lo que cambia: herramientas, parte fija (con punto de caché),
 *      aprendizaje del sitio (con punto de caché) y al final la parte variable (persona y fecha, sin punto); más la
 *      caché automática de la conversación: 3 puntos, dentro de los 4 que admite la API; la parte fija no trae ni la
 *      persona ni la fecha, así que es la misma para todos y todos los días;
 *   K3 con otro proveedor (OpenAI, Google) o una URL compatible propia, no se manda ningún `cache_control`;
 *   K4 la conversación se reutiliza paso a paso y turno a turno: cada petición empieza byte a byte con la anterior
 *      (también los bloques thinking de turnos pasados, que antes se quitaban y rompían la caché);
 *   K5 se mide: los tokens de cada llamada (entrada, leídos y escritos en la caché, salida) quedan en la bitácora del
 *      Asistente, de los tres formatos de proveedor;
 *   K6 caché del sitio: el contacto del sitio leído dos veces en pocos minutos se calcula una vez; los productos
 *      (precios y existencias) siempre en vivo; otra persona o un cambio en el sitio vuelven a calcularlo.
 * Falla con el código anterior. Uso: php tests/ia-assistant-cache.php
 */
require __DIR__ . '/ia-assistant-agent/wp.php';
function user_can( $user, $cap ) { return isset( $GLOBALS['users'][ (int) $user ] ) && in_array( $cap, $GLOBALS['users'][ (int) $user ]['caps'], true ); }
function delete_option( $k ) { unset( $GLOBALS['options'][ $k ] ); return true; }
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
foreach ( array( 'model', 'tools', 'history', 'guard', 'ability', '' ) as $part ) {
	require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant' . ( $part ? '-' . $part : '' ) . '.php';
}
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant-learning.php';
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant-cache.php';
$M = 'Digitalisimo_AI_Assistant_Model';

$GLOBALS['options']['digitalisimo_ai_options'] = array( 'providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => '' ), 'openai' => array( 'enabled' => 1, 'key' => KEY_OPENAI, 'url' => '' ) ) );
add_filter( 'digitalisimo_ai_assistant_limits', function () { return array( 'per_minute' => 0, 'per_day' => 0, 'concurrent' => 1 ); } );
add_filter( 'digitalisimo_ai_assistant_trusted_prefixes', function ( $p ) { $p[] = 'demo/'; return $p; } );
Digitalisimo_AI_Assistant::init();
Digitalisimo_AI_Assistant_Learning::init();
Digitalisimo_AI_Assistant_Cache::init();
$meta = array( 'annotations' => array( 'readonly' => true, 'destructive' => false ), 'mcp' => array( 'public' => true, 'type' => 'tool' ) );
$reg  = function ( $name, $fn = null ) use ( $meta ) {
	wp_register_ability( $name, array( 'label' => $name, 'category' => strtok( $name, '/' ), 'input_schema' => array( 'type' => 'object', 'properties' => array( 'action' => array( 'type' => 'string', 'enum' => array( 'list', 'get' ) ) ) ), 'permission_callback' => function () { return current_user_can( 'edit_posts' ); }, 'execute_callback' => $fn ?: function () use ( $name ) { $GLOBALS['ran'][] = $name; return array( 'ok' => true, 'de' => $name ); }, 'meta' => $meta ) );
};

/* ---------------- K1: orden fijo de las herramientas ---------------- */
foreach ( array( 'demo/zeta', 'demo/alfa', 'demo/mu' ) as $name ) $reg( $name );
$one = Digitalisimo_AI_Assistant_Tools::definitions( Digitalisimo_AI_Assistant_Tools::catalog() );
$GLOBALS['abilities'] = array();
foreach ( array( 'demo/mu', 'demo/zeta', 'demo/alfa' ) as $name ) $reg( $name );
$two = Digitalisimo_AI_Assistant_Tools::definitions( Digitalisimo_AI_Assistant_Tools::catalog() );
check( array( 'demo-alfa', 'demo-mu', 'demo-zeta' ) === array_column( $one, 'name' ), 'K1: las herramientas van ordenadas por nombre · ' . implode( ',', array_column( $one, 'name' ) ) );
check( wp_json_encode( $one ) === wp_json_encode( $two ), 'K1: registradas en otro orden, el mismo prefijo byte a byte' );

/* ---------------- K2: el prefijo de Anthropic ---------------- */
$GLOBALS['options'][ Digitalisimo_AI_Assistant_Learning::OPTION ] = array( 'trained_at' => time(), 'items' => array( array( 'id' => 'a1', 'section' => 'negocio', 'text' => 'Ferretería en Saltillo.', 'source' => 'site', 'at' => time() ) ) );
$parts = Digitalisimo_AI_Assistant::system_parts();
check( false === strpos( $parts['fixed'], 'Ana Admin' ) && false === strpos( $parts['fixed'], 'Hoy es' ) && false === strpos( $parts['learning'], 'Hoy es' ), 'K2: la parte fija (y el aprendizaje) no traen la persona ni la fecha' );
check( false !== strpos( $parts['variable'], 'Ana Admin' ) && false !== strpos( $parts['variable'], 'Hoy es' ), 'K2: la persona y la fecha van en la parte variable' );
check( false !== strpos( $parts['learning'], 'Ferretería en Saltillo.' ) && false === strpos( $parts['fixed'], 'Ferretería en Saltillo.' ), 'K2: el aprendizaje en su propia parte' );
$GLOBALS['user'] = 2;
check( Digitalisimo_AI_Assistant::system_parts()['fixed'] === $parts['fixed'], 'K2: la parte fija es la misma para otra persona del sitio' );
$GLOBALS['user'] = 1;
$target = $M::target( 'anthropic' );
$b      = $M::build( $target, $parts, array( array( 'role' => 'user', 'text' => 'hola' ) ), $one );
$sys    = $b['body']['system'];
check( is_array( $sys ) && 3 === count( $sys ) && $parts['fixed'] === $sys[0]['text'] && $parts['learning'] === $sys[1]['text'] && $parts['variable'] === $sys[2]['text'], 'K2: tres bloques en orden: fijo, aprendizaje, variable' );
check( isset( $sys[0]['cache_control'], $sys[1]['cache_control'] ) && ! isset( $sys[2]['cache_control'] ), 'K2: punto de caché tras la parte fija y tras el aprendizaje; la parte variable, después del último' );
check( array( 'type' => 'ephemeral' ) === $b['body']['cache_control'], 'K2: y la caché automática para la conversación' );
$marks = substr_count( wp_json_encode( $b['body'] ), '"cache_control"' );
check( 3 === $marks, "K2: 3 puntos de caché, dentro de los 4 que admite la API ($marks)" );
check( array( 'demo-alfa', 'demo-mu', 'demo-zeta' ) === array_column( $b['body']['tools'], 'name' ), 'K2: las herramientas primero, en su orden fijo' );
$plain = $M::build( $target, 'SYS', array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( 'SYS' === $plain['body']['system'], 'K2: unas instrucciones de un solo texto siguen igual' );

/* ---------------- K3: sin caché donde no aplica ---------------- */
$o = $M::build( $M::target( 'openai' ), $parts, array( array( 'role' => 'user', 'text' => 'hola' ) ), $one );
check( false === strpos( wp_json_encode( $o['body'] ), 'cache_control' ) && 'system' === $o['body']['messages'][0]['role'] && false !== strpos( $o['body']['messages'][0]['content'], 'Ferretería en Saltillo.' ), 'K3: OpenAI no recibe cache_control (su caché es automática) y sí todas las instrucciones' );
$g = $M::build( array( 'provider' => 'google', 'model' => 'gemini-2.5-pro', 'key' => 'k', 'url' => '' ), $parts, array( array( 'role' => 'user', 'text' => 'hola' ) ), $one );
check( false === strpos( wp_json_encode( $g['body'] ), 'cache_control' ) && is_string( $g['body']['systemInstruction']['parts'][0]['text'] ), 'K3: Google tampoco' );
$custom = $M::build( array_merge( $target, array( 'url' => 'https://proxy.test/v1/messages' ) ), $parts, array( array( 'role' => 'user', 'text' => 'hola' ) ), $one );
check( false === strpos( wp_json_encode( $custom['body'] ), 'cache_control' ) && is_string( $custom['body']['system'] ), 'K3: ni una URL compatible propia' );
$nc = $M::build( array_merge( $target, array( 'no_cache' => true ) ), $parts, array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( false === strpos( wp_json_encode( $nc['body'] ), 'cache_control' ), 'K3: ni una llamada suelta del aprendizaje (la escritura sería un recargo)' );

/* ---------------- K4: la conversación se reutiliza ---------------- */
$reply = function ( array $content, $stop, array $usage ) {
	return array( 'code' => 200, 'body' => wp_json_encode( array( 'content' => $content, 'stop_reason' => $stop, 'usage' => $usage ) ) );
};
$think = function ( $n ) { return array( 'type' => 'thinking', 'thinking' => 'Reviso tu sitio ' . $n . '.', 'signature' => 'sig' . $n ); };
$GLOBALS['http']         = array();
$GLOBALS['http_replies'] = array(
	$reply( array( $think( 1 ), array( 'type' => 'tool_use', 'id' => 'toolu_a1', 'name' => 'demo-alfa', 'input' => array( 'action' => 'list' ) ) ), 'tool_use', array( 'input_tokens' => 40, 'cache_creation_input_tokens' => 9000, 'cache_read_input_tokens' => 0, 'output_tokens' => 50 ) ),
	$reply( array( $think( 2 ), array( 'type' => 'text', 'text' => 'Listo.' ) ), 'end_turn', array( 'input_tokens' => 30, 'cache_creation_input_tokens' => 200, 'cache_read_input_tokens' => 9000, 'output_tokens' => 20 ) ),
	$reply( array( $think( 3 ), array( 'type' => 'text', 'text' => 'Claro.' ) ), 'end_turn', array( 'input_tokens' => 25, 'cache_creation_input_tokens' => 150, 'cache_read_input_tokens' => 9200, 'output_tokens' => 10 ) ),
);
$req = function ( array $params ) { return new WP_REST_Request( $params, array( 'X-Digitalisimo-Assistant' => wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) ) ); };
$r1  = Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'revisa mi sitio', 'provider' => 'anthropic' ) ) );
$cid = $r1->data['conversation']['id'];
Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'gracias, ahora otra cosa', 'conversation' => $cid, 'provider' => 'anthropic' ) ) );
check( 3 === count( $GLOBALS['http'] ), 'K4: tres peticiones (dos pasos del primer turno y una del segundo) · ' . count( $GLOBALS['http'] ) );
$bodies = array_map( function ( $h ) { return json_decode( $h['args']['body'], true ); }, $GLOBALS['http'] );
$strip  = function ( $v ) use ( &$strip ) { if ( ! is_array( $v ) ) return $v; unset( $v['cache_control'] ); foreach ( $v as $k => $x ) $v[ $k ] = $strip( $x ); return $v; };
$prefix = function ( array $a, array $b ) use ( $strip ) { return wp_json_encode( $strip( $a ) ) === wp_json_encode( $strip( array_slice( $b, 0, count( $a ) ) ) ); };
check( wp_json_encode( $bodies[0]['tools'] ) === wp_json_encode( $bodies[2]['tools'] ) && wp_json_encode( $bodies[0]['system'] ) === wp_json_encode( $bodies[2]['system'] ), 'K4: herramientas e instrucciones idénticas en todas las peticiones' );
check( $prefix( $bodies[0]['messages'], $bodies[1]['messages'] ), 'K4: dentro de un turno, cada paso empieza con la petición anterior' );
check( $prefix( $bodies[1]['messages'], $bodies[2]['messages'] ), 'K4: en el turno siguiente también (los bloques thinking del turno pasado siguen igual) · ' . substr( wp_json_encode( $bodies[2]['messages'][1] ), 0, 300 ) );
check( 'thinking' === $bodies[2]['messages'][1]['content'][0]['type'] && 'sig1' === $bodies[2]['messages'][1]['content'][0]['signature'], 'K4: el bloque thinking del turno anterior va tal cual' );
check( isset( $bodies[2]['cache_control'] ) && is_array( $bodies[2]['system'] ), 'K4: cada petición lleva sus puntos de caché' );
add_filter( 'digitalisimo_ai_assistant_replay_thinking', '__return_false_cache' );
function __return_false_cache() { return false; }
$old = Digitalisimo_AI_Assistant_History::for_model( Digitalisimo_AI_Assistant_History::get( $cid, 1 ), Digitalisimo_AI_Assistant_Tools::catalog(), $target );
check( ! isset( $old[1]['thinking'] ), 'K4: con el filtro en false, sólo los del turno en curso (como antes)' );
remove_all_filters( 'digitalisimo_ai_assistant_replay_thinking' );

/* ---------------- K5: se mide ---------------- */
$u = get_option( Digitalisimo_AI_Assistant_Tools::USAGE_LOG );
check( 3 === count( $u ) && array( 'input' => 30, 'cache_read' => 9000, 'cache_write' => 200, 'output' => 20 ) === $u[1]['usage'] && 'model' === $u[1]['kind'] && $cid === $u[1]['conversation'], 'K5: cada llamada al modelo queda en la bitácora con sus tokens · ' . wp_json_encode( $u[1] ) );
check( false !== strpos( $u[2]['detail'], 'caché leída 9200 (98 %)' ), 'K5: con el porcentaje leído de la caché, en llano · ' . $u[2]['detail'] );
check( in_array( 'model', array_column( Digitalisimo_AI_Assistant_Tools::log_rows(), 'kind' ), true ) && in_array( 'read', array_column( Digitalisimo_AI_Assistant_Tools::log_rows(), 'kind' ), true ), 'K5: «Ver la bitácora» muestra las llamadas y las herramientas' );
check( array( 'input' => 70, 'cache_read' => 30, 'cache_write' => 0, 'output' => 9 ) === $M::usage( 'openai', array( 'usage' => array( 'prompt_tokens' => 100, 'completion_tokens' => 9, 'prompt_tokens_details' => array( 'cached_tokens' => 30 ) ) ) ), 'K5: OpenAI cuenta lo leído dentro de la entrada: se separa' );
check( array( 'input' => 60, 'cache_read' => 40, 'cache_write' => 0, 'output' => 7 ) === $M::usage( 'google', array( 'usageMetadata' => array( 'promptTokenCount' => 100, 'cachedContentTokenCount' => 40, 'candidatesTokenCount' => 7 ) ) ), 'K5: Google igual' );
check( array( 'input' => 1, 'cache_read' => 2, 'cache_write' => 3, 'output' => 4 ) === $M::usage( 'anthropic', array( 'usage' => array( 'input_tokens' => 1, 'cache_read_input_tokens' => 2, 'cache_creation_input_tokens' => 3, 'output_tokens' => 4 ) ) ), 'K5: Anthropic los separa' );

/* ---------------- K6: caché del sitio ---------------- */
$GLOBALS['ran'] = array();
$reg( 'digitalisimo-ia/site-contact' );
$reg( 'digitalisimo-woo/products' );
$catalog = Digitalisimo_AI_Assistant_Tools::catalog();
$run     = function ( $name, $input = array( 'action' => 'list' ) ) use ( $catalog ) { return Digitalisimo_AI_Assistant_Cache::run( $catalog[ $name ], $input ); };
$a = $run( 'digitalisimo-ia/site-contact' );
$b = $run( 'digitalisimo-ia/site-contact' );
check( 1 === count( array_keys( $GLOBALS['ran'], 'digitalisimo-ia/site-contact', true ) ) && $a['data'] === $b['data'] && ! empty( $b['cached'] ), 'K6: el contacto del sitio, leído dos veces, se calcula una vez' );
$run( 'digitalisimo-woo/products' );
$run( 'digitalisimo-woo/products' );
check( 2 === count( array_keys( $GLOBALS['ran'], 'digitalisimo-woo/products', true ) ), 'K6: los productos (precios y existencias) siempre en vivo' );
$GLOBALS['user'] = 2;
$run( 'digitalisimo-ia/site-contact' );
check( 2 === count( array_keys( $GLOBALS['ran'], 'digitalisimo-ia/site-contact', true ) ), 'K6: otra persona no recibe lo guardado para otra (sus permisos pueden ser otros)' );
$GLOBALS['user'] = 1;
$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Contacto' ) );
do_action( 'save_post', $pid, get_post( $pid ), true );
$run( 'digitalisimo-ia/site-contact' );
check( 3 === count( array_keys( $GLOBALS['ran'], 'digitalisimo-ia/site-contact', true ) ), 'K6: un cambio en el sitio la invalida' );
$run( 'digitalisimo-ia/site-contact' );
do_action( 'updated_option', '_transient_dgac_x' );
do_action( 'updated_option', 'digitalisimo_ai_assistant_cache_gen' );
$run( 'digitalisimo-ia/site-contact' );
check( 3 === count( array_keys( $GLOBALS['ran'], 'digitalisimo-ia/site-contact', true ) ), 'K6: guardar un transitorio (o la propia caché) no la invalida' );
do_action( 'updated_option', 'woocommerce_currency' );
$run( 'digitalisimo-ia/site-contact' );
check( 4 === count( array_keys( $GLOBALS['ran'], 'digitalisimo-ia/site-contact', true ) ), 'K6: un ajuste de la tienda sí' );
$conv = Digitalisimo_AI_Assistant_History::create( 1, 'x' );
$hist = wp_insert_post( array( 'post_type' => 'digitalisimo_ai_chat', 'post_status' => 'private', 'post_title' => 'x' ) );
do_action( 'save_post', $hist, get_post( $hist ), true );
$run( 'digitalisimo-ia/site-contact' );
check( 4 === count( array_keys( $GLOBALS['ran'], 'digitalisimo-ia/site-contact', true ) ), 'K6: guardar una conversación del Asistente no la invalida' );


/* ---------------- K7: qué invalida la caché del sitio (revisión de b7762173) ---------------- */
$gen   = Digitalisimo_AI_Assistant_Cache::generation();
$order = wp_insert_post( array( 'post_type' => 'shop_order', 'post_status' => 'wc-processing', 'post_title' => 'Pedido' ) );
do_action( 'save_post', $order, get_post( $order ), true );
$form = wp_insert_post( array( 'post_type' => 'feedback', 'post_status' => 'publish', 'post_title' => 'Formulario' ) );
do_action( 'save_post', $form, get_post( $form ), true );
do_action( 'updated_option', 'woocommerce_task_list_tracked_completed_tasks' );
check( $gen === Digitalisimo_AI_Assistant_Cache::generation(), 'K7: un pedido, un formulario recibido o un ajuste interno de WooCommerce no vacían la caché' );
do_action( 'edited_term', $hist, 5, 'product_cat' );
check( $gen !== Digitalisimo_AI_Assistant_Cache::generation(), 'K7: editar un término sí, aunque su número coincida con el de una conversación' );
$gen = Digitalisimo_AI_Assistant_Cache::generation();
do_action( 'updated_option', 'woocommerce_bacs_settings' );
check( $gen !== Digitalisimo_AI_Assistant_Cache::generation(), 'K7: los ajustes de pagos de WooCommerce sí' );
echo "Caché del Asistente IA: $checks comprobaciones correctas.\n";
