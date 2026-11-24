<?php
/**
 * IA Tools · el Asistente IA «primero hace y muestra» (caso plomerito 2 del 2026-11-24, ya con 1.0.72): «es muy
 * preguntón, arroja información técnica que el usuario nunca va a entender y toca cosas de más: sólo le pedí una landing
 * y metió muchas cosas como editar producto»; «el usuario promedio quiere primero ver y luego cambiar las cosas». Sin
 * WordPress y con el modelo simulado (`digitalisimo_ai_assistant_pre_model`):
 *   M1  las instrucciones: crear ya el borrador completo, como mucho una pregunta, sólo lo pedido (lo demás se ofrece en
 *       una frase), nunca repetir lo de resultado desconocido, lenguaje de dueño de negocio y enlaces con texto;
 *   M2  la hora de la tienda: con el sitio en UTC y la tienda en México, «fin de mes» es la medianoche de México, sin
 *       «UTC» y con ese cierre también en la hora del sitio;
 *   M3  en el turno en que se preparó un borrador, un cambio en vivo (el precio del producto) no se propone: queda «no se
 *       hizo», sin tarjeta, y el modelo sabe que debe ofrecerlo; en el turno siguiente, si la persona lo pide, sí;
 *   M4  lo que quedó con resultado desconocido no se repite en el mismo turno;
 *   M5  un error que dice que no se hizo nada (4xx) no es «resultado desconocido»;
 *   M6  rehacer un borrador propio (create-page con post_id) no pide tarjeta, también desde otra conversación; uno que
 *       no se puede rehacer se crea nuevo, sin tocar el otro y con la nota de no mencionarlo;
 *   M7  el filtro del kit `digitalisimo_sales_regenerable` acepta los borradores que el Asistente creó para esa persona;
 *   M8  read-page-text no lee el pie de una imagen que no se ve («Foto de la persona detrás de la marca»);
 *   M9  la pantalla recibe aparte el trabajo (notas de avance y texto que acompaña a una llamada) y la respuesta.
 * Falla con el código de 1.0.72 (2d851937). Uso: php tests/ia-assistant-mostrar.php
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
Digitalisimo_AI_Assistant::init();

/* ---------------- Las herramientas del caso, con la forma de las reales ---------------- */
$can_edit = function () { return current_user_can( 'edit_posts' ); };
$ann      = function ( $readonly, $destructive ) { return array( 'annotations' => array( 'readonly' => $readonly, 'destructive' => $destructive ), 'mcp' => array( 'public' => true, 'type' => 'tool' ) ); };
$GLOBALS['sale']     = '';
$GLOBALS['kit_runs'] = array();
$GLOBALS['kit_fail'] = array();
// El kit de ventas: crea el borrador (o lo regenera con post_id); puede fallar a propósito (kit_fail).
wp_register_ability(
	'digitalisimo-sales/create-page',
	array(
		'label'               => 'Kit de ventas: crear una página en borrador',
		'category'            => 'digitalisimo-sales',
		'input_schema'        => array( 'type' => 'object', 'properties' => array( 'product_id' => array( 'type' => 'integer' ), 'offer' => array( 'type' => 'object' ), 'title' => array( 'type' => 'string' ), 'post_id' => array( 'type' => 'integer' ) ) ),
		'permission_callback' => $can_edit,
		'execute_callback'    => function ( $in ) {
			$GLOBALS['kit_runs'][] = $in;
			if ( $GLOBALS['kit_fail'] ) {
				return array_shift( $GLOBALS['kit_fail'] );
			}
			if ( isset( $in['post_id'] ) ) {
				$GLOBALS['posts'][ (int) $in['post_id'] ]->post_title = $in['title'] ?? 'Rehecha';
				return array( 'ok' => true, 'id' => (int) $in['post_id'], 'status' => 'draft', 'regenerated' => true, 'preview_url' => 'https://sitio.test/?page_id=' . (int) $in['post_id'] . '&preview=true', 'edit_url' => 'https://sitio.test/wp-admin/post.php?post=' . (int) $in['post_id'] . '&action=elementor' );
			}
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $in['title'] ?? 'Black Friday', 'post_author' => get_current_user_id() ) );
			return array( 'ok' => true, 'id' => $id, 'status' => 'draft', 'regenerated' => false, 'preview_url' => 'https://sitio.test/?page_id=' . $id . '&preview=true', 'edit_url' => 'https://sitio.test/wp-admin/post.php?post=' . $id . '&action=elementor', 'review' => array( 'La tienda cobra $10,000: pon el precio de oferta en el producto para cobrar lo mismo que anuncia la landing.' ) );
		},
		'meta'                => $ann( false, false ),
	)
);
// La tienda de DIGITALÍSIMO: leer y actualizar productos (toda escritura anotada como destructiva, como la real).
wp_register_ability(
	'digitalisimo-woo/products',
	array(
		'label'               => 'Tienda: productos',
		'category'            => 'digitalisimo-woo',
		'input_schema'        => array( 'type' => 'object', 'properties' => array( 'action' => array( 'type' => 'string', 'enum' => array( 'list', 'get', 'create', 'update', 'delete', 'batch' ) ), 'product_id' => array( 'type' => 'integer' ), 'data' => array( 'type' => 'object' ), 'query' => array( 'type' => 'object' ) ) ),
		'permission_callback' => $can_edit,
		'execute_callback'    => function ( $in ) {
			if ( 'update' === ( $in['action'] ?? '' ) ) {
				$GLOBALS['sale'] = (string) ( $in['data']['sale_price'] ?? '' );
				return array( 'id' => 252, 'sale_price' => $GLOBALS['sale'] );
			}
			return array( array( 'id' => 252, 'name' => 'Purificador Alcalino AguaClara', 'regular_price' => '10000', 'sale_price' => $GLOBALS['sale'] ) );
		},
		'meta'                => $ann( false, true ),
	)
);
wp_register_ability( 'elementor/build-composition', array( 'label' => 'Construir composición', 'category' => 'elementor', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'xml' => array( 'type' => 'string' ) ) ), 'permission_callback' => $can_edit, 'execute_callback' => function ( $in ) { $GLOBALS['built'][] = $in['post_id'] ?? 0; return array( 'post_id' => $in['post_id'] ?? 0 ); }, 'meta' => $ann( false, true ) ) );

$req    = function ( array $params = array() ) { return new WP_REST_Request( $params, array( 'X-Digitalisimo-Assistant' => wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) ) ); };
$events = function ( $response ) { check( $response instanceof WP_REST_Response, 'La ruta responde con eventos: ' . ( is_wp_error( $response ) ? $response->get_error_message() : gettype( $response ) ) ); return $response->data['events']; };
$of     = function ( $events, $type ) { return array_values( array_filter( $events, function ( $e ) use ( $type ) { return $e['type'] === $type; } ) ); };
$call   = function ( $name, array $input = array() ) { static $n = 0; return array( 'id' => 'toolu_m' . ( ++$n ), 'name' => $name, 'input' => $input ); };
$sent   = function ( $tool_id = null ) { foreach ( array_reverse( $GLOBALS['model_calls'] ) as $request ) { foreach ( array_reverse( $request['messages'] ) as $m ) { if ( 'tool' === $m['role'] && ( null === $tool_id || $m['call'] === $tool_id ) ) return json_decode( $m['content'], true ); } } return null; };
$GLOBALS['script'] = array(); $GLOBALS['model_calls'] = array();
add_filter( 'digitalisimo_ai_assistant_pre_model', function ( $pre, $request ) { $GLOBALS['model_calls'][] = $request; return $GLOBALS['script'] ? array_shift( $GLOBALS['script'] ) : 'Listo.'; } );
$offer = array( 'product_id' => 252, 'offer' => array( 'price' => '7500', 'ends' => '2026-11-30 23:59', 'label' => 'Black Friday' ) );

/* ---------------- M1: las instrucciones ---------------- */
$sys = Digitalisimo_AI_Assistant::system_prompt();
foreach ( array(
	'Primero haces y muestras',
	'créalo YA en borrador, completo, en este mismo turno',
	'no pidas permiso ni confirmación para crearlo',
	'llama enseguida a digitalisimo-sales/create-page con product_id y offer',
	'no los consultes antes',
	'usa el más parecido de la tienda',
	'Como mucho una pregunta corta. Nunca pidas listas de datos.',
	'Haz únicamente lo que la persona pidió',
	'«¿Quieres que ponga el precio de oferta en la tienda?»',
	'No llames a la herramienta hasta que la persona lo pida',
	'nunca lo repitas: primero consulta el estado real',
	'no menciones el anterior',
	'Nunca escribas IDs',
	'[Ver la landing](URL)',
	'Nunca pegues una URL suelta',
	'en 4 o 5 líneas cortas',
	'no expliques zonas horarias',
	'sólo lo propones cuando la persona lo pidió',
) as $piece ) {
	check( false !== strpos( $sys, $piece ), "M1: las instrucciones dicen «{$piece}»" );
}
check( false === strpos( $sys, 'todo junto y una sola vez' ) && false === strpos( $sys, 'no preguntes en texto si lo haces' ), 'M1: ya no invitan a pedir listas de datos ni a lanzar tarjetas por iniciativa propia' );

/* ---------------- M2: la hora de la tienda ---------------- */
$GLOBALS['tz']                                     = 'UTC';
$GLOBALS['options']['woocommerce_default_country'] = 'MX:COA';
$line = Digitalisimo_AI_Assistant::date_line();
$mx   = new DateTimeImmutable( 'now', new DateTimeZone( 'America/Mexico_City' ) );
$end  = new DateTimeImmutable( $mx->format( 'Y-m-t' ) . ' 23:59', new DateTimeZone( 'America/Mexico_City' ) );
check( 'America/Mexico_City' === Digitalisimo_AI_Assistant::store_timezone()->getName(), 'M2: con el sitio en UTC, la hora de la tienda es la de su país (México: centro)' );
check( false !== strpos( $line, '«' . $end->format( 'Y-m-d' ) . ' 23:59»' ) && false !== strpos( $line, '«' . $end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i' ) . '»' ), 'M2: el fin de mes a las 23:59 de la tienda y ese mismo cierre en la hora del sitio: ' . $line );
check( false === strpos( $line, 'UTC' ) && false !== strpos( $line, 'no la menciones' ), 'M2: la línea no dice «UTC» y pide no mencionar la zona: ' . $line );
$GLOBALS['tz'] = 'America/Tijuana';
check( 'America/Tijuana' === Digitalisimo_AI_Assistant::store_timezone()->getName() && false === strpos( Digitalisimo_AI_Assistant::date_line(), 'hora del sitio: ahí' ), 'M2: con una ciudad elegida en el sitio, manda la del sitio (y no hace falta convertir)' );
$GLOBALS['tz'] = 'America/Mexico_City';
unset( $GLOBALS['options']['woocommerce_default_country'] );

/* ---------------- M3: sólo lo que se pidió ---------------- */
// El caso: crea la landing y, en el mismo turno, quiere poner el precio de oferta en el producto (dos veces).
$GLOBALS['script'] = array(
	array( 'calls' => array( $call( 'digitalisimo-woo-products', array( 'action' => 'list', 'query' => array( 'search' => 'purificador' ) ) ) ) ),
	array( 'calls' => array( $call( 'digitalisimo-sales-create-page', $offer ) ) ),
	array( 'text' => 'Para cobrar lo mismo, pongo el precio de oferta.', 'calls' => array( $call( 'digitalisimo-woo-products', array( 'action' => 'update', 'product_id' => 252, 'data' => array( 'sale_price' => '7500' ) ) ) ) ),
	array( 'calls' => array( $call( 'digitalisimo-woo-products', array( 'action' => 'update', 'product_id' => 252, 'data' => array( 'sale_price' => '7500' ) ) ) ) ),
	'Listo: [Ver la landing](https://sitio.test/?page_id=100&preview=true). La tienda cobra $10,000: ¿quieres que ponga el precio de oferta en la tienda?',
);
$ev    = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'créame una landing de Black Friday del purificador de 10,000 a 7,500 hasta fin de mes con el número del sitio' ) ) ) );
$cm    = $ev[0]['id'];
$tools = $of( $ev, 'tool' );
check( ! $of( $ev, 'confirm' ) && '' === $GLOBALS['sale'] && count( $tools ) >= 4, 'M3: en el turno de la landing no aparece ninguna tarjeta para cambiar el producto y el precio sigue igual: ' . json_encode( $ev, JSON_UNESCAPED_UNICODE ) );
check( 'draft' === $tools[1]['message']['kind'] && 'ok' === $tools[1]['message']['status'] && 'skipped' === $tools[2]['message']['status'] && 'skipped' === $tools[3]['message']['status'], 'M3: la landing se crea sin tarjeta y los dos intentos de cambiar el precio quedan «no se hizo»: ' . json_encode( array_map( function ( $t ) { return $t['message']['kind'] . ':' . $t['message']['status']; }, $tools ) ) );
$why = $sent( 'toolu_m3' );
check( is_array( $why ) && 'skipped' === $why['estado'] && false !== strpos( $why['error'], 'pregúntale si lo quiere' ), 'M3: el modelo recibe por qué y que debe ofrecerlo en una frase' );
$row = end( $GLOBALS['options'][ Digitalisimo_AI_Assistant_Tools::LOG ] );
check( 'skipped' === $row['result'] && 'digitalisimo-woo/products' === $row['ability'], 'M3: queda en la bitácora como «skipped»' );
// Turno siguiente: la persona lo pide; ahora sí se propone (tarjeta) y nada cambia sin confirmar.
$GLOBALS['script'] = array( array( 'text' => 'Pongo $7,500 en la tienda.', 'calls' => array( $call( 'digitalisimo-woo-products', array( 'action' => 'update', 'product_id' => 252, 'data' => array( 'sale_price' => '7500' ) ) ) ) ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'sí, pon el precio de oferta en la tienda', 'conversation' => $cm ) ) ) );
check( 1 === count( $of( $ev, 'confirm' ) ) && '' === $GLOBALS['sale'], 'M3: cuando la persona lo pide, el cambio se propone con su tarjeta (y no se aplica sin confirmar)' );
$events( Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $cm, 'action' => $of( $ev, 'confirm' )[0]['action']['id'], 'decision' => 'deny' ) ) ) );
// El filtro lo apaga (para quien prefiere el comportamiento anterior).
add_filter( 'digitalisimo_ai_assistant_hold_after_draft', function () { return false; } );
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'digitalisimo-sales-create-page', $offer ), $call( 'digitalisimo-woo-products', array( 'action' => 'update', 'product_id' => 252, 'data' => array( 'sale_price' => '7500' ) ) ) ) ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'otra landing y el precio' ) ) ) );
check( 1 === count( $of( $ev, 'confirm' ) ), 'M3: con el filtro digitalisimo_ai_assistant_hold_after_draft en false, la tarjeta vuelve' );
remove_all_filters( 'digitalisimo_ai_assistant_hold_after_draft' );

/* ---------------- M4: no repetir lo de resultado desconocido ---------------- */
$GLOBALS['kit_fail'] = array( new WP_Error( 'digitalisimo_sales_save', 'El servidor tardó demasiado.', array( 'status' => 500 ) ) );
$GLOBALS['kit_runs'] = array();
$GLOBALS['script']   = array( array( 'calls' => array( $call( 'digitalisimo-sales-create-page', $offer ) ) ), array( 'calls' => array( $call( 'digitalisimo-sales-create-page', $offer ) ) ), 'Reviso.' );
$ev    = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'créala' ) ) ) );
$tools = $of( $ev, 'tool' );
check( 1 === count( $GLOBALS['kit_runs'] ) && 'unknown' === $tools[0]['message']['status'] && 'skipped' === $tools[1]['message']['status'], 'M4: lo que quedó con resultado desconocido no se repite en el mismo turno' );
check( false !== strpos( (string) ( $sent()['error'] ?? '' ), 'Primero lee el estado real' ), 'M4: el modelo recibe que primero lea el estado real' );

/* ---------------- M5: un error que dice que no se hizo nada ---------------- */
$GLOBALS['kit_fail'] = array( new WP_Error( 'digitalisimo_sales_offer', 'offer.price: sólo la cifra.', array( 'status' => 400 ) ) );
$GLOBALS['script']   = array( array( 'calls' => array( $call( 'digitalisimo-sales-create-page', $offer ) ) ), 'Corrijo.' );
$ev   = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'créala otra vez' ) ) ) );
$tool = $of( $ev, 'tool' )[0]['message'];
check( 'error' === $tool['status'] && false !== strpos( $tool['detail'], 'No se aplicó nada' ) && empty( $sent()['puede_haberse_aplicado'] ), 'M5: un 400 del kit es un error («no se aplicó nada»), nunca «resultado desconocido»' );
foreach ( array( array( 'x', 409, false ), array( 'x', 429, false ), array( 'x', 500, false ), array( 'x', 404, false ), array( 'x', 400, false ), array( 'ability_invalid_output', 0, false ), array( 'ability_invalid_output', 400, false ), array( 'digitalisimo_sales_save', 500, false ), array( 'digitalisimo_sales_post_id', 409, false ), array( 'digitalisimo_sales_post_id', 404, true ), array( 'ability_invalid_input', 0, true ), array( 'ability_invalid_permissions', 0, true ), array( 'rest_invalid_param', 400, true ) ) as $c ) {
	check( $c[2] === Digitalisimo_AI_Assistant_Tools::not_applied( new WP_Error( $c[0], 'm', $c[1] ? array( 'status' => $c[1] ) : '' ) ), "M5: {$c[0]} {$c[1]} " . ( $c[2] ? 'no se aplicó' : 'pudo aplicarse' ) );
}

// La salida inválida se detecta DESPUÉS de ejecutar: la escritura ya pudo ocurrir. Queda como desconocido, en llano.
wp_register_ability( 'demo/salida-rara', array( 'label' => 'Guardar algo', 'category' => 'demo', 'input_schema' => array( 'type' => 'object' ), 'permission_callback' => $can_edit, 'execute_callback' => function () { $GLOBALS['escrito'] = true; return new WP_Error( 'ability_invalid_output', 'La salida no cumple el esquema.' ); }, 'meta' => $ann( false, false ) ) );
$GLOBALS['script'] = array( array( 'calls' => array( $call( 'demo-salida-rara', array() ) ) ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'guárdalo' ) ) ) );
$ev = $events( Digitalisimo_AI_Assistant::rest_confirm( $req( array( 'conversation' => $ev[0]['id'], 'action' => $of( $ev, 'confirm' )[0]['action']['id'], 'decision' => 'approve' ) ) ) );
$tool = $of( $ev, 'tool' )[0]['message'];
check( ! empty( $GLOBALS['escrito'] ) && 'unknown' === $tool['status'] && 0 === strpos( $tool['detail'], 'No pude confirmar si se hizo; revisa antes de repetir.' ), 'M5: ability_invalid_output (la escritura ya ocurrió) queda como desconocido, dicho en llano: ' . $tool['detail'] );

/* ---------------- M6: rehacer el borrador propio ---------------- */
$GLOBALS['kit_fail'] = array();
$GLOBALS['script']   = array( array( 'calls' => array( $call( 'digitalisimo-sales-create-page', $offer ) ) ), 'Hecha.' );
$ev    = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'créame la landing' ) ) ) );
$c6    = $ev[0]['id'];
$draft = (int) $sent()['datos']['id'];
check( $draft > 0 && 1 === (int) get_post_meta( $draft, Digitalisimo_AI_Assistant_Tools::DRAFT_META, true ), 'M6: el borrador que creó el Asistente queda marcado con la persona' );
$GLOBALS['kit_runs'] = array();
$GLOBALS['script']   = array( array( 'calls' => array( $call( 'digitalisimo-sales-create-page', $offer + array( 'post_id' => $draft, 'title' => 'Black Friday: agua pura' ) ) ) ), 'Rehecha.' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'rehazla con otro título', 'conversation' => $c6 ) ) ) );
check( ! $of( $ev, 'confirm' ) && 'draft' === $of( $ev, 'tool' )[0]['message']['kind'] && $draft === (int) ( $GLOBALS['kit_runs'][0]['post_id'] ?? 0 ), 'M6: rehacer el borrador de esta conversación (post_id) no pide tarjeta y se hace sobre el mismo' );
// Desde otra conversación de la misma persona, también.
$GLOBALS['kit_runs'] = array();
$GLOBALS['script']   = array( array( 'calls' => array( $call( 'digitalisimo-sales-create-page', $offer + array( 'post_id' => $draft ) ) ) ), array( 'calls' => array( $call( 'elementor-build-composition', array( 'post_id' => $draft, 'xml' => '<e-flexbox/>' ) ) ) ), 'Rehecha.' );
$ev = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'rehaz mi landing de ayer' ) ) ) );
check( ! $of( $ev, 'confirm' ) && $draft === (int) ( $GLOBALS['kit_runs'][0]['post_id'] ?? 0 ) && array( $draft ) === ( $GLOBALS['built'] ?? array() ), 'M6: un borrador que el Asistente creó para esta persona se rehace y se edita sin tarjeta desde otra conversación: ' . json_encode( array( $draft, $GLOBALS['kit_runs'], $GLOBALS['built'] ?? null, array_map( function ( $e ) { return $e['type'] . ':' . ( $e['message']['kind'] ?? '' ) . ':' . ( $e['message']['status'] ?? '' ) . ':' . ( $e['message']['detail'] ?? ( $e['message']['text'] ?? '' ) ); }, $ev ) ), JSON_UNESCAPED_UNICODE ) );
// Uno que no se puede rehacer (de otra persona, publicado o creado antes de la marca): se crea uno nuevo, sin tocarlo.
$ajeno     = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Oferta de fin de mes', 'post_author' => 2 ) );
$publicada = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Inicio' ) );
foreach ( array( $ajeno => 'un borrador viejo sin marca', $publicada => 'una página publicada' ) as $id => $what ) {
	$GLOBALS['kit_runs'] = array();
	$GLOBALS['script']   = array( array( 'calls' => array( $call( 'digitalisimo-sales-create-page', $offer + array( 'post_id' => $id ) ) ) ), 'Hecha.' );
	$ev   = $events( Digitalisimo_AI_Assistant::rest_message( $req( array( 'message' => 'rehaz la landing' ) ) ) );
	$data = $sent();
	check( ! $of( $ev, 'confirm' ) && 1 === count( $GLOBALS['kit_runs'] ) && ! isset( $GLOBALS['kit_runs'][0]['post_id'] ) && 'ok' === $data['estado'] && $id !== (int) $data['datos']['id'] && false !== strpos( (string) ( $data['nota'] ?? '' ), 'No menciones el anterior' ), "M6: con $what se crea un borrador nuevo (sin tarjeta) y el modelo sabe que no debe mencionar el anterior" );
}
check( 'Oferta de fin de mes' === get_post( $ajeno )->post_title && 'publish' === get_post( $publicada )->post_status, 'M6: el viejo y la publicada quedaron como estaban' );

/* ---------------- M7: el filtro del kit ---------------- */
check( true === apply_filters( 'digitalisimo_sales_regenerable', false, $draft ) && false === apply_filters( 'digitalisimo_sales_regenerable', false, $ajeno ), 'M7: el kit puede rehacer un borrador que el Asistente creó para esta persona, y no uno ajeno' );
$GLOBALS['user'] = 2;
check( false === apply_filters( 'digitalisimo_sales_regenerable', false, $draft ), 'M7: ni el de otra persona' );
$GLOBALS['user'] = 1;

/* ---------------- M8: lo que no se ve no se lee ---------------- */
$landing = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Landing' ) );
update_post_meta(
	$landing,
	'_elementor_data',
	wp_json_encode(
		array(
			array( 'id' => 'a', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( 'image' => array( 'url' => 'https://sitio.test/a.jpg', 'id' => 5 ), 'caption_source' => 'none', 'caption' => 'Foto de la persona detrás de la marca' ), 'elements' => array() ),
			array( 'id' => 'b', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( 'image' => array( 'url' => 'https://sitio.test/b.jpg', 'id' => 6 ), 'caption' => 'Otro pie que no se ve' ), 'elements' => array() ),
			array( 'id' => 'c', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( 'image' => array( 'url' => 'https://sitio.test/c.jpg', 'id' => 7 ), 'caption_source' => 'custom', 'caption' => 'Instalado en una cocina real' ), 'elements' => array() ),
		)
	)
);
$text = Digitalisimo_AI_Site_Reader::content( get_post( $landing ) )['text'];
check( false === strpos( $text, 'detrás de la marca' ) && false === strpos( $text, 'Otro pie' ) && false !== strpos( $text, 'Instalado en una cocina real' ), 'M8: el pie de una imagen sólo se lee si se ve (caption_source custom): ' . $text );

/* ---------------- M9: trabajo y respuesta, por separado ---------------- */
$work  = Digitalisimo_AI_Assistant_History::display( array( 'id' => 'x', 'role' => 'assistant', 'text' => '', 'notes' => 'Busco el producto y el contacto.', 'calls' => array( array( 'id' => 't', 'ability' => 'demo/x', 'input' => array() ) ) ) );
$final = Digitalisimo_AI_Assistant_History::display( array( 'id' => 'y', 'role' => 'assistant', 'text' => 'Lista tu landing.', 'notes' => 'Reviso lo creado.', 'calls' => array() ) );
check( true === $work['work'] && 'Busco el producto y el contacto.' === $work['text'], 'M9: la nota de avance de un paso con herramientas llega como trabajo (la pantalla la pliega)' );
check( false === $final['work'] && 'Lista tu landing.' === $final['text'] && 'Reviso lo creado.' === $final['notes'], 'M9: la respuesta llega sola, sin la nota de avance pegada' );
check( 'https://sitio.test/' === Digitalisimo_AI_Assistant::config()['site']['home'], 'M9: la pantalla sabe la dirección del sitio (sólo enlaza lo del propio sitio)' );

echo "Asistente IA, primero hacer y mostrar: $checks comprobaciones correctas.\n";
