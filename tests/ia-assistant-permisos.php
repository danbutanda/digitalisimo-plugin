<?php
/**
 * IA Tools · el Asistente y los interruptores «sólo para el MCP externo» (caso plomerito 4 del 2026-11-26, IA Tools
 * 1.0.74): un administrador pidió «revisa el sitio web y optimízalo en SEO»; al guardar, el Asistente dijo «No pude
 * guardarlo: WordPress dice que tu usuario no tiene permiso… alguien con cuenta de administrador puede darte ese
 * permiso». Lo apagado era «Permitir que cambien datos SEO» (`seo_abilities_write`, 0 por omisión), un opt-in pensado
 * para clientes MCP externos. Sin WordPress, con el modelo simulado y las abilities REALES de escritura de SEO:
 *   P1  con `seo_abilities_write` apagado, el Asistente propone el cambio con su tarjeta, lo guarda al confirmar
 *       (botón o «sí») y queda en el historial del contenido (via assistant) y en la bitácora; deshacer también;
 *   P2  (desde el 2026-11-27, acceso total sin interruptores) un cliente MCP o REST externo con la capacidad también
 *       escribe aunque el sitio tuviera el interruptor viejo apagado (via ability); antes quedaba bloqueado;
 *   P3  sin la capacidad sigue bloqueado, sin tarjeta, y el mensaje dice que falta el permiso del usuario; a un
 *       administrador nunca se le dice que le falta un permiso cuando la herramienta no dio el motivo;
 *   P4  un ajuste apagado que el Asistente no salta (de una herramienta cualquiera; la tienda ya no tiene modos) se
 *       dice como ajuste, con su nombre y dónde se cambia, sin tarjeta;
 *   P5  el contexto no se puede falsificar: sin el nonce del Asistente, con una contraseña de aplicación, para otra
 *       ability, para otro usuario o con parámetros en la entrada, no se abre; y se cierra al terminar (con una ability
 *       de prueba que sólo deja pasar dentro del contexto: SEO ya no lo necesita).
 * Falla con el código de main v516 (IA Tools 1.0.74). Uso: php tests/ia-assistant-permisos.php
 */
require __DIR__ . '/ia-assistant-agent/wp.php';
require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
foreach ( array( 'model', 'tools', 'history', 'guard', 'ability', 'uploads', '' ) as $part ) {
	require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant' . ( $part ? '-' . $part : '' ) . '.php';
}
// Lo mínimo de DIGITALÍSIMO SEO alrededor de sus abilities de escritura (las reales).
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $k, $d = null ) { return array_key_exists( $k, $GLOBALS['seo'] ) ? $GLOBALS['seo'][ $k ] : $d; } }
class Digitalisimo_Integrations_Editorial { public static function content_types() { return array( 'post', 'page' ); } }
class Digitalisimo_Integrations_SEO_Abilities {
	const CATEGORY = 'digitalisimo-seo';
	// La misma regla que la real: editar ese contenido.
	public static function can_edit( $input = array() ) { $id = absint( is_array( $input ) ? ( $input['post_id'] ?? 0 ) : 0 ); return $id && current_user_can( 'edit_post', $id ); }
	public static function get_post_seo( $input ) { return array( 'title' => get_post_meta( $input['post_id'], 'digitalisimo_seo_title', true ) ); }
}
require dirname( __DIR__ ) . '/digitalisimo-seo/includes/class-settings.php';
require dirname( __DIR__ ) . '/digitalisimo-seo/includes/class-seo-abilities-write.php';
if ( ! function_exists( '__return_false' ) ) { function __return_false() { return false; } }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['meta'][ $id ][ $k ] ); return true; }
// Con una contraseña de aplicación, WordPress devuelve su UUID; con la sesión del navegador, null.
function rest_get_authenticated_app_password() { return $GLOBALS['app_password'] ?? null; }

$GLOBALS['seo'] = array( 'seo_abilities_write' => 0 );
$GLOBALS['options']['digitalisimo_ai_options'] = array( 'providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => '' ) ) );
add_filter( 'digitalisimo_ai_assistant_limits', function () { return array( 'per_minute' => 0, 'per_day' => 0, 'concurrent' => 1 ); } );
Digitalisimo_Integrations_SEO_Abilities_Write::register();
$GLOBALS['script'] = array();
add_filter( 'digitalisimo_ai_assistant_pre_model', function ( $pre, $request ) { $GLOBALS['model_calls'][] = $request; return $GLOBALS['script'] ? array_shift( $GLOBALS['script'] ) : 'Listo.'; } );

$ctx    = 'Digitalisimo_AI_Assistant_Context';
// Con el código anterior (sin contexto) la prueba sigue hasta la comprobación del caso, que falla.
$desk   = class_exists( $ctx ) ? new ReflectionProperty( $ctx, 'desk' ) : null;
if ( $desk ) $desk->setAccessible( true );
// Cada petición de PHP empieza sin el permiso del Asistente.
$new_request = function () use ( $desk ) { if ( $desk ) $desk->setValue( null, 0 ); };
$req    = function ( array $params = array(), $nonce = null ) { return new WP_REST_Request( $params, array( 'X-Digitalisimo-Assistant' => null === $nonce ? wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) : $nonce ) ); };
// Como el servidor REST: primero el permiso de la ruta y, sólo si pasa, su callback.
$route  = function ( $callback, WP_REST_Request $request ) use ( $new_request ) {
	$new_request();
	$allowed = Digitalisimo_AI_Assistant::permission( $request );
	return true === $allowed ? call_user_func( array( 'Digitalisimo_AI_Assistant', $callback ), $request ) : $allowed;
};
$events = function ( $response ) { check( $response instanceof WP_REST_Response, 'La ruta responde con eventos: ' . ( is_wp_error( $response ) ? $response->get_error_message() : gettype( $response ) ) ); return $response->data['events']; };
$of     = function ( $events, $type ) { return array_values( array_filter( $events, function ( $e ) use ( $type ) { return $e['type'] === $type; } ) ); };
$say    = function ( array $calls, $text = 'Voy.' ) { $GLOBALS['script'] = array( array( 'text' => $text, 'calls' => $calls ), 'Listo.' ); };
$n      = 0;
$call   = function ( $tool, array $input ) use ( &$n ) { return array( 'id' => 'toolu_' . ( ++$n ), 'name' => $tool, 'input' => $input ); };
$sent_result = function () { $last = end( $GLOBALS['model_calls'] )['messages']; foreach ( array_reverse( $last ) as $m ) if ( 'tool' === $m['role'] ) return json_decode( $m['content'], true ); return null; };

$home = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Inicio', 'post_name' => 'inicio' ) );
update_post_meta( $home, 'digitalisimo_seo_description', 'Descripción vieja' );
$seo_input = array( 'post_id' => $home, 'title' => 'Plomero en Torreón 24 h %sep% %sitename%', 'description' => 'Plomería urgente en Torreón: fugas, calentadores y drenaje, con garantía.' );

/* ---------------- P1: el caso, con el interruptor apagado ---------------- */
$GLOBALS['user'] = 1; // Ana Admin.
check( true === Digitalisimo_AI_Assistant_Tools::allowed( Digitalisimo_AI_Assistant_Tools::catalog()['digitalisimo-seo/update-post-seo'], $seo_input ), 'P1: con el interruptor viejo apagado, un administrador puede (acceso total, 2026-11-27)' );
$say( array( $call( 'digitalisimo-seo-update-post-seo', $seo_input ) ), 'Te propongo este título y esta descripción para Google.' );
$ev   = $events( $route( 'rest_message', $req( array( 'message' => 'revisa el sitio web y optimízalo en SEO' ) ) ) );
$cid  = $ev[0]['id'];
$card = $of( $ev, 'confirm' );
check( 1 === count( $card ) && 'digitalisimo-seo/update-post-seo' === $card[0]['action']['ability'] && 'write' === $card[0]['action']['kind'], 'P1: el Asistente propone el cambio con su tarjeta (no «sin permiso»): ' . json_encode( $of( $ev, 'tool' ), JSON_UNESCAPED_UNICODE ) );
check( '' === get_post_meta( $home, 'digitalisimo_seo_title', true ) && 'Descripción vieja' === get_post_meta( $home, 'digitalisimo_seo_description', true ), 'P1: antes de confirmar no se guarda nada' );
check( null === Digitalisimo_AI_Assistant_Context::current(), 'P1: el contexto se cierra al terminar la comprobación' );
$ev = $events( $route( 'rest_confirm', $req( array( 'conversation' => $cid, 'action' => $card[0]['action']['id'], 'decision' => 'approve' ) ) ) );
$tool = $of( $ev, 'tool' );
check( 'ok' === ( $tool[0]['message']['status'] ?? '' ), 'P1: al confirmar, se guarda: ' . json_encode( $tool, JSON_UNESCAPED_UNICODE ) );
check( $seo_input['title'] === get_post_meta( $home, 'digitalisimo_seo_title', true ) && $seo_input['description'] === get_post_meta( $home, 'digitalisimo_seo_description', true ), 'P1: el título y la descripción quedaron guardados' );
$history = get_post_meta( $home, Digitalisimo_Integrations_SEO_Abilities_Write::HISTORY, true );
check( is_array( $history ) && 1 === count( $history ) && 'assistant' === $history[0]['via'] && 1 === $history[0]['user'] && array( 'title' => '', 'description' => 'Descripción vieja' ) === $history[0]['before'], 'P1: queda en el historial del contenido, hecho por el Asistente, con lo anterior para deshacer: ' . json_encode( $history ) );
$log = get_option( Digitalisimo_AI_Assistant_Tools::LOG );
check( 'digitalisimo-seo/update-post-seo' === end( $log )['ability'] && 'ok' === end( $log )['result'] && 'write' === end( $log )['kind'] && 1 === end( $log )['user'], 'P1: y en la bitácora del Asistente' );
check( null === Digitalisimo_AI_Assistant_Context::current(), 'P1: el contexto se cierra al terminar la acción' );
// Deshacer, también desde el Asistente y también con su tarjeta.
$say( array( $call( 'digitalisimo-seo-revert-post-seo', array( 'post_id' => $home ) ) ) );
$ev   = $events( $route( 'rest_message', $req( array( 'message' => 'mejor déjalo como estaba', 'conversation' => $cid ) ) ) );
$card = $of( $ev, 'confirm' );
check( 1 === count( $card ), 'P1: deshacer también pide confirmación' );
$GLOBALS['script'] = array( 'Listo, quedó como antes.' );
$ev = $events( $route( 'rest_confirm', $req( array( 'conversation' => $cid, 'action' => $card[0]['action']['id'], 'decision' => 'approve' ) ) ) );
check( 'ok' === ( $of( $ev, 'tool' )[0]['message']['status'] ?? '' ) && '' === get_post_meta( $home, 'digitalisimo_seo_title', true ) && 'Descripción vieja' === get_post_meta( $home, 'digitalisimo_seo_description', true ), 'P1: al confirmar, deshace el cambio (lo anterior vuelve)' );

/* ---------------- P2: un cliente MCP o REST externo también escribe (acceso total) ---------------- */
$new_request(); // Otra petición: /wp-json/elementor/mcp, con la contraseña de aplicación de un administrador.
$update = wp_get_ability( 'digitalisimo-seo/update-post-seo' );
check( true === $update->check_permissions( $seo_input ) && true === wp_get_ability( 'digitalisimo-seo/create-redirection' )->check_permissions( array( 'source' => '/a/' ) ), 'P2: su comprobación de permiso pasa con el interruptor viejo apagado (antes no)' );
$external = $update->execute( $seo_input );
$history  = get_post_meta( $home, Digitalisimo_Integrations_SEO_Abilities_Write::HISTORY, true );
check( is_array( $external ) && $seo_input['title'] === get_post_meta( $home, 'digitalisimo_seo_title', true ) && 'ability' === end( $history )['via'], 'P2: el cliente externo escribe (via ability), sin encender nada' );
delete_post_meta( $home, 'digitalisimo_seo_title' );

/* ---------------- P3: sin la capacidad sigue bloqueado y lo dice ---------------- */
$GLOBALS['user'] = 2; // Eli Editora: edit_posts, sin manage_options.
$GLOBALS['executed'] = array();
$say( array( $call( 'digitalisimo-seo-create-redirection', array( 'source' => '/plomeria/', 'target' => '/' ) ) ) );
$ev   = $events( $route( 'rest_message', $req( array( 'message' => 'redirige /plomeria/ a la portada' ) ) ) );
$tool = $of( $ev, 'tool' );
check( ! $of( $ev, 'confirm' ) && array() === $GLOBALS['executed'] && 'denied' === $tool[0]['message']['status'], 'P3: sin administrar el sitio, ni tarjeta ni ejecución' );
check( Digitalisimo_AI_Assistant_Tools::CAPABILITY_NOTE === $tool[0]['message']['detail'] && false !== strpos( $tool[0]['message']['detail'], 'Tu usuario de WordPress no tiene el permiso' ), 'P3: el mensaje dice que a su usuario le falta el permiso' );
$data = $sent_result();
check( 'permiso' === ( $data['motivo'] ?? '' ) && 'denied' === $data['estado'], 'P3: el modelo recibe el motivo «permiso»: ' . json_encode( $data, JSON_UNESCAPED_UNICODE ) );
// Una ability de otro plugin que se niega sin motivo a un ADMINISTRADOR: no se le dice que le falta un permiso.
$GLOBALS['user'] = 1;
wp_register_ability( 'otro/guardar', array( 'label' => 'Guardar en otro plugin', 'category' => 'otro', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'x' => array( 'type' => 'string' ) ) ), 'permission_callback' => '__return_false', 'execute_callback' => function () { $GLOBALS['otro'] = true; return array(); }, 'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false ), 'mcp' => array( 'public' => true, 'type' => 'tool' ) ) ) );
$say( array( $call( 'otro-guardar', array( 'x' => 'y' ) ) ) );
$tool = $of( $events( $route( 'rest_message', $req( array( 'message' => 'guárdalo' ) ) ) ), 'tool' );
check( 'denied' === $tool[0]['message']['status'] && Digitalisimo_AI_Assistant_Tools::UNKNOWN_DENIAL === $tool[0]['message']['detail'] && false === strpos( $tool[0]['message']['detail'], 'no tiene el permiso' ) && empty( $GLOBALS['otro'] ), 'P3: a un administrador, «no dijo por qué», nunca «tu usuario no tiene permiso»' );
check( 'desconocido' === ( $sent_result()['motivo'] ?? '' ), 'P3: con el motivo «desconocido» para el modelo' );

/* ---------------- P4: un ajuste que el Asistente no salta ---------------- */
$read_only = 'Este sitio tiene «Demo de pedidos» apagado: delete en «order» no se hizo. Un administrador lo enciende en Ajustes → Demo.';
wp_register_ability( 'digitalisimo-woo/orders', array( 'label' => 'Tienda: pedidos', 'category' => 'digitalisimo-woo', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'action' => array( 'type' => 'string', 'enum' => array( 'list', 'delete' ) ), 'id' => array( 'type' => 'integer' ) ) ), 'permission_callback' => function ( $in ) use ( $read_only ) {
	// Una herramienta con un ajuste propio que el Asistente no salta (la tienda ya no tiene modos desde el 2026-11-27).
	return Digitalisimo_AI_Assistant_Context::allows( 'digitalisimo-woo/orders' ) && 'delete' === ( $in['action'] ?? '' ) ? new WP_Error( 'digitalisimo_demo_off', $read_only, array( 'status' => 403, 'digitalisimo_setting' => 'demo' ) ) : true;
}, 'execute_callback' => function () { $GLOBALS['woo_ran'] = true; return array(); }, 'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => true ), 'mcp' => array( 'public' => true, 'type' => 'tool' ) ) ) );
$say( array( $call( 'digitalisimo-woo-orders', array( 'action' => 'delete', 'id' => 9 ) ) ) );
$ev   = $events( $route( 'rest_message', $req( array( 'message' => 'borra el pedido 9' ) ) ) );
$tool = $of( $ev, 'tool' );
check( ! $of( $ev, 'confirm' ) && empty( $GLOBALS['woo_ran'] ) && 'blocked' === $tool[0]['message']['status'], 'P4: un ajuste que no se salta: sin tarjeta, sin ejecutar, como «apagado en ajustes»' );
check( false !== strpos( $tool[0]['message']['detail'], '«Demo de pedidos» apagado' ) && false !== strpos( $tool[0]['message']['detail'], 'Ajustes → Demo' ) && false !== strpos( $tool[0]['message']['detail'], 'No se aplicó nada' ) && false === strpos( $tool[0]['message']['detail'], 'permiso' ), 'P4: dice cuál ajuste y dónde se cambia, no «sin permiso»: ' . $tool[0]['message']['detail'] );
check( 'ajuste' === ( $sent_result()['motivo'] ?? '' ), 'P4: el modelo recibe el motivo «ajuste»' );
// Un error propio marcado como ajuste (digitalisimo_setting) cuenta igual; un error cualquiera, como error.
check( 'setting' === Digitalisimo_AI_Assistant_Tools::denial( new WP_Error( 'x_off', 'Apagado en X.', array( 'status' => 403, 'digitalisimo_setting' => 'x' ) ) )['reason'] && 'error' === Digitalisimo_AI_Assistant_Tools::denial( new WP_Error( 'ability_invalid_input', 'Falta post_id.' ) )['status'], 'P4: tres casos: capacidad, ajuste o error' );
$sys = Digitalisimo_AI_Assistant::system_prompt();
check( false !== strpos( $sys, '«ajuste», qué ajuste está apagado y dónde se cambia' ) && false !== strpos( $sys, 'Nunca digas que alguien más tiene que darle permiso' ), 'P4: las instrucciones piden la causa real' );

/* ---------------- P5: el contexto no se puede falsificar ---------------- */
$GLOBALS['user'] = 1;
// Una ability que sólo deja pasar dentro del contexto del Asistente (como hacía SEO con su interruptor apagado).
wp_register_ability( 'digitalisimo-seo/solo-asistente', array( 'label' => 'Sólo desde el Asistente', 'category' => 'digitalisimo-seo', 'input_schema' => array( 'type' => 'object', 'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'title' => array( 'type' => 'string' ), 'description' => array( 'type' => 'string' ) ), 'additionalProperties' => true ), 'permission_callback' => function () { return Digitalisimo_AI_Assistant_Context::allows( 'digitalisimo-seo/solo-asistente' ) && current_user_can( 'manage_options' ); }, 'execute_callback' => function ( $in ) { update_post_meta( $in['post_id'], 'digitalisimo_seo_title', $in['title'] ); return array( 'ok' => true ); }, 'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false ), 'mcp' => array( 'public' => true, 'type' => 'tool' ) ) ) );
$update = wp_get_ability( 'digitalisimo-seo/solo-asistente' );
// Sin el nonce del Asistente (una cabecera inventada), la ruta no pasa ni se arma el contexto.
$new_request();
$bad = Digitalisimo_AI_Assistant::permission( $req( array( 'message' => 'x' ), 'nonce-falso' ) );
check( is_wp_error( $bad ) && is_wp_error( Digitalisimo_AI_Assistant_Context::with( 'digitalisimo-seo/solo-asistente', 'write', function () use ( $update, $seo_input ) { return $update->execute( $seo_input ); } ) ), 'P5: con un nonce falso no se abre' );
// Con una contraseña de aplicación (un cliente REST o MCP), aunque traiga un nonce válido, tampoco.
$GLOBALS['app_password'] = 'uuid-de-la-contraseña';
$say( array( $call( 'digitalisimo-seo-solo-asistente', $seo_input ) ) );
$ev = $events( $route( 'rest_message', $req( array( 'message' => 'optimiza el SEO' ) ) ) );
check( ! $of( $ev, 'confirm' ) && 'denied' === $of( $ev, 'tool' )[0]['message']['status'] && '' === get_post_meta( $home, 'digitalisimo_seo_title', true ), 'P5: con una contraseña de aplicación el Asistente no abre el contexto' );
unset( $GLOBALS['app_password'] );
// Parámetros en la entrada o en la petición no lo encienden.
$new_request();
check( is_wp_error( $update->execute( $seo_input + array( 'assistant' => true, 'digitalisimo_assistant_context' => 1, 'via' => 'assistant' ) ) ) && '' === get_post_meta( $home, 'digitalisimo_seo_title', true ), 'P5: un parámetro en la entrada no lo abre' );
// Con el permiso del Asistente en esta petición: sólo para ESA ability y ESE usuario, y se cierra al terminar.
Digitalisimo_AI_Assistant::permission( $req() );
$inside = Digitalisimo_AI_Assistant_Context::with( 'mcp-adapter/execute-ability', 'write', function () use ( $update, $seo_input ) { return $update->check_permissions( $seo_input ); } );
check( true !== $inside, 'P5: abierto para otra ability (p. ej. mcp-adapter/execute-ability), no deja pasar la de SEO' );
$inside = Digitalisimo_AI_Assistant_Context::with( 'digitalisimo-seo/solo-asistente', 'write', function () use ( $update, $seo_input ) { $GLOBALS['user'] = 2; $r = Digitalisimo_AI_Assistant_Context::current(); $GLOBALS['user'] = 1; return $r; } );
check( null === $inside, 'P5: abierto para un usuario, no vale para otro' );
$GLOBALS['user'] = 2;
check( null === Digitalisimo_AI_Assistant_Context::with( 'digitalisimo-seo/solo-asistente', 'write', function () { return Digitalisimo_AI_Assistant_Context::current(); } ), 'P5: armado por un usuario, otro no lo abre' );
$GLOBALS['user'] = 1;
$caps = $GLOBALS['users'][1]['caps'];
$GLOBALS['users'][1]['caps'] = array( 'read' );
check( null === Digitalisimo_AI_Assistant_Context::with( 'digitalisimo-seo/solo-asistente', 'write', function () { return Digitalisimo_AI_Assistant_Context::current(); } ), 'P5: sin la capacidad del Asistente, no se abre' );
$GLOBALS['users'][1]['caps'] = $caps;
check( true === Digitalisimo_AI_Assistant_Context::with( 'digitalisimo-seo/solo-asistente', 'write', function () use ( $update, $seo_input ) { return $update->check_permissions( $seo_input ); } ) && null === Digitalisimo_AI_Assistant_Context::current(), 'P5: en la petición del Asistente, para esa ability, pasa; y al terminar se cierra' );

/* ---------------- P6: lo que la revisión encontró ---------------- */
$GLOBALS['user'] = 1;
// P6.1 Una redirección con patrón o hacia otro dominio es destructiva: casilla explícita y nunca un «sí» escrito.
$redir = Digitalisimo_AI_Assistant_Tools::catalog()['digitalisimo-seo/create-redirection'];
foreach ( array(
	array( array( 'source' => '/vieja/', 'target' => '/nueva/' ), 'write' ),
	array( array( 'source' => '/vieja/', 'target' => 'https://sitio.test/nueva/' ), 'write' ),
	array( array( 'source' => '/vieja/', 'code' => 410 ), 'write' ),
	array( array( 'source' => '.*', 'target' => 'https://otro.test/', 'comparison' => 'regex' ), 'destructive' ),
	array( array( 'source' => '/blog', 'target' => '/', 'comparison' => 'start' ), 'destructive' ),
	array( array( 'source' => 'x', 'target' => '/', 'comparison' => 'contains' ), 'destructive' ),
	array( array( 'source' => '.html', 'target' => '/', 'comparison' => 'end' ), 'destructive' ),
	array( array( 'source' => '/vieja/', 'target' => 'https://otro.test/' ), 'destructive' ),
	array( array( 'source' => '/vieja/', 'target' => '//otro.test/x' ), 'destructive' ),
	array( array( 'source' => '/vieja/', 'target' => 'otro.test/x' ), 'destructive' ),
) as $case ) {
	check( $case[1] === Digitalisimo_AI_Assistant_Tools::kind( $redir, $case[0] ), 'P6.1: redirección ' . json_encode( $case[0] ) . ' es ' . $case[1] );
}
$GLOBALS['executed'] = array();
$say( array( $call( 'digitalisimo-seo-create-redirection', array( 'source' => '.*', 'target' => 'https://otro.test/', 'comparison' => 'regex' ) ) ) );
$ev   = $events( $route( 'rest_message', $req( array( 'message' => 'redirige todo' ) ) ) );
$c6   = $ev[0]['id'];
$card = $of( $ev, 'confirm' );
check( 1 === count( $card ) && 'destructive' === $card[0]['action']['kind'], 'P6.1: la redirección de todo el sitio a otro dominio pide la casilla de destructiva' );
$ev = $events( $route( 'rest_message', $req( array( 'message' => 'sí', 'conversation' => $c6 ) ) ) );
check( array() === $GLOBALS['executed'] && 1 === count( Digitalisimo_AI_Assistant_History::get( $c6, 1 )['pending'] ), 'P6.1: un «sí» escrito no la ejecuta' );
$no = $route( 'rest_confirm', $req( array( 'conversation' => $c6, 'action' => $card[0]['action']['id'], 'decision' => 'approve' ) ) );
check( is_wp_error( $no ) && 'digitalisimo_assistant_explicit' === $no->get_error_code() && array() === $GLOBALS['executed'], 'P6.1: ni el botón sin la casilla' );

// P6.2 La API v3 de WooCommerce publica lo que se crea sin status (WooCommerce 11.2.1:
// Version3/class-wc-rest-products-controller.php, 'status' => array( … 'default' => ProductStatus::PUBLISH )).
$woo = array( 'name' => 'digitalisimo-woo/products', 'annotations' => array( 'readonly' => false, 'destructive' => true ), 'schema' => array( 'type' => 'object', 'properties' => array( 'action' => array( 'type' => 'string', 'enum' => array( 'list', 'get', 'create', 'update', 'delete', 'batch' ) ), 'resource' => array( 'type' => 'string' ), 'product_id' => array( 'type' => 'integer' ), 'data' => array( 'type' => 'object' ) ) ) );
$in  = Digitalisimo_AI_Assistant_Tools::prepare_input( $woo, array( 'action' => 'create', 'data' => array( 'name' => 'Bomba' ) ) );
check( 'draft' === $in['data']['status'] && 'write' === Digitalisimo_AI_Assistant_Tools::kind( $woo, $in ), 'P6.2: crear un producto sin estado nace en borrador (y sigue siendo un cambio sencillo)' );
$in = Digitalisimo_AI_Assistant_Tools::prepare_input( $woo, array( 'action' => 'create', 'resource' => 'variation', 'product_id' => 3, 'data' => array( 'regular_price' => '10' ) ) );
check( 'draft' === $in['data']['status'], 'P6.2: también una variación' );
$in = Digitalisimo_AI_Assistant_Tools::prepare_input( $woo, array( 'action' => 'batch', 'data' => array( 'create' => array( array( 'name' => 'A' ), array( 'name' => 'B', 'status' => 'publish' ) ) ) ) );
check( 'draft' === $in['data']['create'][0]['status'] && 'draft' === $in['data']['create'][1]['status'], 'P6.2: y cada producto de un lote' );
$in = Digitalisimo_AI_Assistant_Tools::prepare_input( array_merge( $woo, array( 'name' => 'digitalisimo-woo/catalog' ) ), array( 'action' => 'create', 'resource' => 'category', 'data' => array( 'name' => 'Bombas' ) ) );
check( ! isset( $in['data']['status'] ), 'P6.2: una categoría no tiene estado: no se le pone' );
foreach ( array( 'draft', 'publish', 'pending', 'private' ) as $st ) {
	check( 'destructive' === Digitalisimo_AI_Assistant_Tools::kind( $woo, array( 'action' => 'update', 'product_id' => 13, 'data' => array( 'status' => $st ) ) ), "P6.2: cambiar el estado de un producto existente a {$st} es destructivo" );
}

// P6.5/6 Lo que se niega AL EJECUTAR: el motivo real y «no se aplicó» sólo si es seguro.
$GLOBALS['flip'] = null;
wp_register_ability( 'demo/ajuste', array( 'label' => 'Demo', 'category' => 'demo', 'input_schema' => array( 'type' => 'object', 'properties' => array() ), 'permission_callback' => function () { return null === $GLOBALS['flip'] ? true : $GLOBALS['flip']; }, 'execute_callback' => function () { return isset( $GLOBALS['exec_error'] ) ? $GLOBALS['exec_error'] : array(); }, 'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false ), 'mcp' => array( 'public' => true, 'type' => 'tool' ) ) ) );
$demo = Digitalisimo_AI_Assistant_Tools::catalog()['demo/ajuste'];
// El núcleo cambia la negativa por ability_invalid_permissions sin motivo: se vuelve a preguntar.
$GLOBALS['flip'] = new WP_Error( 'x_apagado', 'Está apagado «X» (Ajustes → X).', array( 'status' => 403, 'digitalisimo_setting' => 'x' ) );
$r = Digitalisimo_AI_Assistant_Tools::run( $demo, array(), 'write' );
check( 'blocked' === $r['status'] && 'setting' === $r['reason'] && false !== strpos( $r['error'], '«X»' ) && false === strpos( $r['error'], 'permiso' ), 'P6.6: un ajuste que llega como ability_invalid_permissions se dice como ajuste: ' . json_encode( $r, JSON_UNESCAPED_UNICODE ) );
$GLOBALS['flip'] = false;
$r = Digitalisimo_AI_Assistant_Tools::run( $demo, array(), 'write' );
check( 'unknown' === $r['reason'] && false === strpos( $r['error'], 'no tiene el permiso' ), 'P6.6: a un administrador, una negativa sin motivo no es «te falta un permiso»' );
$GLOBALS['user'] = 2;
$r = Digitalisimo_AI_Assistant_Tools::run( $demo, array(), 'write' );
check( 'capability' === $r['reason'] && Digitalisimo_AI_Assistant_Tools::CAPABILITY_NOTE === $r['error'], 'P6.6: a quien no administra, que le falta el permiso' );
$GLOBALS['user'] = 1;
$GLOBALS['flip'] = null;
// Un ajuste que la herramienta dice DESPUÉS de empezar (no es de los que se comprueban antes): no se sabe si se aplicó.
$GLOBALS['exec_error'] = new WP_Error( 'x_tarde', 'Se apagó «X» a medias.', array( 'status' => 500, 'digitalisimo_setting' => 'x' ) );
$r = Digitalisimo_AI_Assistant_Tools::run( $demo, array(), 'write' );
check( empty( $r['not_applied'] ) && false === strpos( $r['error'], 'No se aplicó nada' ) && false !== strpos( $r['error'], 'No pude confirmar si se hizo' ), 'P6.5: «No se aplicó nada» sólo si es seguro: ' . json_encode( $r, JSON_UNESCAPED_UNICODE ) );
$GLOBALS['exec_error'] = new WP_Error( 'digitalisimo_woo_mcp_read_only', 'Sólo lectura.', array( 'status' => 403 ) );
$r = Digitalisimo_AI_Assistant_Tools::run( $demo, array(), 'write' );
check( ! empty( $r['not_applied'] ) && false !== strpos( $r['error'], 'No se aplicó nada' ), 'P6.5: y sí lo dice cuando el error es de antes de ejecutar' );
unset( $GLOBALS['exec_error'] );

echo "Asistente y permisos internos: {$checks} comprobaciones.\n";
