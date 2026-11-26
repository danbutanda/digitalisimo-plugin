<?php
/**
 * Pasos de ia-usage.sh (registro de uso de IA en una red real de Playground). Un proceso PHP por paso; cada paso anota
 * lo observado en result.json. Lo que devolvió el proveedor (sólo su bloque `usage`, nunca el texto) y lo registrado
 * quedan en usage.json para comparar. La clave sólo vive en las opciones del sitio mientras dura la prueba.
 */
$digi_step = (string) getenv( 'DIGI_STEP' );
$digi_blog = (int) getenv( 'DIGI_BLOG' );
$digi_dir  = '/wordpress/wp-content/probe';
$_SERVER['HTTP_HOST']   = 'playground.test';
$_SERVER['REQUEST_URI'] = ( 2 === $digi_blog ? '/sitio2/' : '/' ) . ( 'revisar' === $digi_step ? 'wp-admin/admin.php' : '' );
if ( 'revisar' === $digi_step ) {
	define( 'WP_ADMIN', true );
}
register_shutdown_function( static function () use ( $digi_step, $digi_dir ) { $e = error_get_last(); if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( $digi_dir . '/fatal.log', "$digi_step {$e['message']} en {$e['file']}:{$e['line']}\n", FILE_APPEND ); } );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$digi_mode   = trim( (string) @file_get_contents( $digi_dir . '/mode' ) );
$result_file = $digi_dir . '/result.json';
$result      = is_readable( $result_file ) ? json_decode( file_get_contents( $result_file ), true ) : array();
$note        = function ( $ok, $what ) use ( &$result, $digi_step ) { $result[] = array( 'step' => $digi_step, 'ok' => (bool) $ok, 'what' => $what ); };
$save        = function () use ( &$result, $result_file ) { file_put_contents( $result_file, json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); };
register_shutdown_function( function () use ( $save ) { $save(); } );
const DIGI_PROMPT = 'Responde solamente: hola mundo de prueba.';
const DIGI_VISITOR_IP = '198.51.100.9';

// Precios publicados de claude-haiku-5-5 (platform.claude.com/docs/en/about-claude/pricing, 2026-11-26), escritos aquí a
// mano para comprobar el cálculo del plugin con una cuenta independiente (prompts de menos de 100 000 tokens).
function digi_expected_cost( array $u ) {
	$write = (int) ( $u['cache_creation_input_tokens'] ?? 0 );
	$w1h   = (int) ( $u['cache_creation']['ephemeral_1h_input_tokens'] ?? 0 );
	return ( (int) $u['input_tokens'] * 0.10 + (int) $u['output_tokens'] * 0.50 + (int) ( $u['cache_read_input_tokens'] ?? 0 ) * 0.01 + ( $write - $w1h ) * 0.125 + $w1h * 0.20 ) / 1000000;
}

// Lo que devuelve el proveedor (sólo el bloque de uso), después del registro del plugin (prioridad 10).
$GLOBALS['digi_raw'] = array();
add_action( 'http_api_debug', function ( $response, $context, $class, $args, $url ) {
	if ( false === strpos( (string) $url, 'api.anthropic.com' ) ) return;
	$body = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
	$GLOBALS['digi_raw'][] = array( 'status' => is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response ), 'model' => (string) ( $body['model'] ?? '' ), 'usage' => $body['usage'] ?? null, 'error' => is_array( $body['error'] ?? null ) ? substr( (string) ( $body['error']['message'] ?? '' ), 0, 200 ) : '' );
}, 99, 5 );

// Proveedor simulado (MODE=simulado): mismo formato que la API de Anthropic, y la respuesta pasa por http_api_debug como
// en WP_Http::request(). Va al final de pre_http_request: si el candado del presupuesto ya cortó, se respeta.
if ( 'simulado' === $digi_mode ) {
	add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
		if ( false !== $pre || false === strpos( (string) $url, 'api.anthropic.com' ) ) return $pre;
		$body  = (string) ( $args['body'] ?? '' );
		$n     = max( 10, intdiv( strlen( $body ), 4 ) );
		$usage = array( 'input_tokens' => $n, 'output_tokens' => 7, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0 );
		if ( false !== strpos( $body, 'cache_control' ) && $n > 4000 ) {
			$seen = (int) get_option( 'digi_sim_cache', 0 );
			$usage[ $seen ? 'cache_read_input_tokens' : 'cache_creation_input_tokens' ] = $n - 20;
			$usage['input_tokens'] = 20;
			update_option( 'digi_sim_cache', 1 );
		}
		preg_match( '/"model":"([^"]+)"/', $body, $m );
		$response = array( 'headers' => array(), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null, 'body' => wp_json_encode( array( 'id' => 'msg_sim', 'type' => 'message', 'role' => 'assistant', 'model' => $m[1] ?? '', 'content' => array( array( 'type' => 'text', 'text' => 'hola mundo de prueba' ) ), 'stop_reason' => 'end_turn', 'usage' => $usage ) ) );
		do_action( 'http_api_debug', $response, 'response', 'WpOrg\Requests\Requests', $args, $url );
		return $response;
	}, PHP_INT_MAX, 3 );
}

/** Filas registradas en el sitio en curso. */
function digi_rows() { global $wpdb; return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'digitalisimo_ai_usage ORDER BY id ASC', ARRAY_A ); }

/** Compara cada fila nueva con lo que devolvió el proveedor y con el costo a mano. */
function digi_compare( array $rows, array $raw, $note ) {
	$note( count( $rows ) === count( $raw ), sprintf( 'una fila por cada petición al proveedor (%d filas, %d peticiones)', count( $rows ), count( $raw ) ) );
	foreach ( $rows as $i => $row ) {
		$u = $raw[ $i ]['usage'] ?? null;
		if ( ! is_array( $u ) ) {
			$note( (int) $row['is_error'] && 0.0 === (float) $row['cost_usd'], sprintf( 'petición %d sin uso (HTTP %d %s): queda como error sin costo', $i + 1, $raw[ $i ]['status'] ?? 0, $raw[ $i ]['error'] ?? '' ) );
			continue;
		}
		$same = (int) $row['input_tokens'] === (int) $u['input_tokens'] && (int) $row['output_tokens'] === (int) $u['output_tokens'] && (int) $row['cache_read_tokens'] === (int) ( $u['cache_read_input_tokens'] ?? 0 ) && (int) $row['cache_write_tokens'] === (int) ( $u['cache_creation_input_tokens'] ?? 0 );
		$note( $same, sprintf( 'petición %d (%s): tokens registrados = los del proveedor (entrada %d, salida %d, caché leída %d, escrita %d)', $i + 1, $row['feature'], $u['input_tokens'], $u['output_tokens'], $u['cache_read_input_tokens'] ?? 0, $u['cache_creation_input_tokens'] ?? 0 ) );
		$expected = digi_expected_cost( $u );
		$note( abs( (float) $row['cost_usd'] - $expected ) < 1e-8, sprintf( 'petición %d: costo registrado US$ %.8F = calculado a mano US$ %.8F', $i + 1, (float) $row['cost_usd'], $expected ) );
	}
}

/** Todo lo que el registro guardó (tablas de los dos sitios y sus opciones de uso), en texto. */
function digi_dump() {
	global $wpdb;
	$out = '';
	foreach ( array( 'wp_', 'wp_2_' ) as $prefix ) {
		foreach ( array( 'digitalisimo_ai_usage', 'digitalisimo_ai_usage_daily' ) as $t ) {
			$out .= wp_json_encode( $wpdb->get_results( "SELECT * FROM {$prefix}{$t}", ARRAY_A ) );
		}
	}
	foreach ( array( 1, 2 ) as $blog ) {
		foreach ( array( 'digitalisimo_ai_usage_settings', 'digitalisimo_ai_usage_prices', 'digitalisimo_ai_usage_alerts', 'digitalisimo_ai_usage_db' ) as $o ) {
			$out .= wp_json_encode( get_blog_option( $blog, $o ) );
		}
	}
	return $out;
}

$key = (string) get_site_option( 'digi_probe_fingerprint', '' );
switch ( $digi_step ) {
	case 'setup':
		$id = wpmu_create_blog( 'playground.test', '/sitio2/', 'Sitio dos', 1 );
		activate_plugin( 'digitalisimo.chatbot/digitalisimo-chatbot.php', '', true );
		$k = 'real' === $digi_mode ? trim( (string) @file_get_contents( $digi_dir . '/k' ) ) : 'sk-ant-simulada-0123456789';
		@unlink( $digi_dir . '/k' );
		$ia = array( 'inherit_network' => 0, 'chat_enabled' => 1, 'context_posts' => 1, 'providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => $k, 'url' => '' ) ), 'assistant_models' => array( 'anthropic' => 'claude-haiku-5-5' ) );
		foreach ( array( 'chatbot', 'content', 'seo', 'ecommerce', 'automation' ) as $p ) $ia['profiles'][ $p ] = array( 'provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'fallback' => '' );
		$ia['profiles']['images'] = array( 'provider' => 'openai', 'model' => 'gpt-image-1', 'fallback' => '' );
		update_option( 'digitalisimo_ai_options', $ia, false );
		switch_to_blog( 2 ); update_option( 'digitalisimo_ai_options', $ia, false ); restore_current_blog();
		// Sólo una huella para buscar la clave en lo guardado; la clave no se escribe en ningún otro lado.
		update_site_option( 'digi_probe_fingerprint', $k );
		$editor = wp_insert_user( array( 'user_login' => 'editora', 'user_pass' => wp_generate_password(), 'role' => 'editor', 'user_email' => 'editora@playground.test' ) );
		update_site_option( 'digi_probe_editor', (int) $editor );
		$note( 2 === (int) $id && is_plugin_active_for_network( 'digitalisimo.chatbot/digitalisimo-chatbot.php' ) && '' !== $k, "red con el sitio 2 e IA Tools activo en la red (modo $digi_mode)" );
		$note( ! file_exists( $digi_dir . '/k' ), 'la clave ya no está en la carpeta de la sonda' );
		break;

	case 'llamadas':
		wp_set_current_user( 1 );
		$a = Digitalisimo_AI::complete( 'content', DIGI_PROMPT );
		$note( is_string( $a ), 'Digitalisimo_AI::complete() responde: ' . ( is_wp_error( $a ) ? $a->get_error_message() : 'texto de ' . strlen( $a ) . ' bytes' ) );
		$target = Digitalisimo_AI_Assistant_Model::target( 'anthropic' );
		$note( ! is_wp_error( $target ) && 'claude-haiku-5-5' === $target['model'], 'el Asistente IA usa claude-haiku-5-5 en esta prueba' );
		// Unas 9 000 palabras fijas en las instrucciones: la segunda llamada idéntica debe leerlas de la caché.
		$system = str_repeat( 'Instrucción fija de prueba para la caché del registro de uso de IA Tools. ', 1000 );
		$msgs   = array( array( 'role' => 'user', 'text' => 'Di solamente: listo.' ) );
		$r1 = Digitalisimo_AI_Assistant_Model::chat( $target, $system, $msgs, array() );
		$r2 = Digitalisimo_AI_Assistant_Model::chat( $target, $system, $msgs, array() );
		$note( ! is_wp_error( $r1 ) && ! is_wp_error( $r2 ), 'el Asistente IA responde dos veces: ' . ( is_wp_error( $r1 ) ? $r1->get_error_message() : 'ok' ) . ' / ' . ( is_wp_error( $r2 ) ? $r2->get_error_message() : 'ok' ) );
		$rows = digi_rows();
		digi_compare( $rows, $GLOBALS['digi_raw'], $note );
		$features = array_column( $rows, 'feature' );
		$note( 'content' === $features[0] && in_array( 'assistant', $features, true ) && ! array_diff( array_column( $rows, 'blog_id' ), array( '1', 1 ) ) && ! array_diff( array_column( $rows, 'user_id' ), array( '1', 1 ) ), 'funciones, sitio 1 y usuario 1 en cada fila: ' . implode( ', ', $features ) );
		$write = array_sum( array_column( $rows, 'cache_write_tokens' ) );
		$read  = array_sum( array_column( $rows, 'cache_read_tokens' ) );
		$note( 'simulado' !== $digi_mode || ( $write > 0 && $read > 0 ), "caché observada: $write tokens escritos y $read leídos" );
		file_put_contents( $digi_dir . '/usage.json', wp_json_encode( array( 'sitio1' => array( 'proveedor' => $GLOBALS['digi_raw'], 'registrado' => $rows ) ), JSON_PRETTY_PRINT ) );
		break;

	case 'visitante':
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = DIGI_VISITOR_IP;
		$req = new WP_REST_Request( 'POST', '/digitalisimo-ai/v1/chat' );
		$req->set_param( 'message', '¿Qué es este sitio? Responde en cinco palabras.' );
		$r = Digitalisimo_AI::chat( $req );
		$note( ! is_wp_error( $r ), 'el chatbot público del sitio 2 responde a un visitante' . ( is_wp_error( $r ) ? ': ' . $r->get_error_message() : '' ) );
		$rows = digi_rows();
		digi_compare( $rows, $GLOBALS['digi_raw'], $note );
		$last = end( $rows );
		$note( $last && 'chatbot' === $last['feature'] && 0 === (int) $last['user_id'] && 2 === (int) $last['blog_id'], 'la fila del sitio 2: chatbot, visitante, blog_id 2' );
		$usage = json_decode( (string) @file_get_contents( $digi_dir . '/usage.json' ), true ) ?: array();
		$usage['sitio2'] = array( 'proveedor' => $GLOBALS['digi_raw'], 'registrado' => $rows );
		file_put_contents( $digi_dir . '/usage.json', wp_json_encode( $usage, JSON_PRETTY_PRINT ) );
		break;

	case 'revisar':
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		wp_set_current_user( 1 );
		global $wpdb;
		$today = Digitalisimo_AI_Usage::today();
		$s1    = Digitalisimo_AI_Usage::summary( $today, $today );
		$n1    = count( digi_rows() );
		switch_to_blog( 2 ); $s2 = Digitalisimo_AI_Usage::summary( $today, $today ); $n2 = count( digi_rows() ); restore_current_blog();
		$note( $s1['periods']['range']['calls'] === $n1 && $s2['periods']['range']['calls'] === $n2 && 1 === $n2, "cada sitio ve sólo lo suyo (sitio 1: $n1 llamadas, sitio 2: $n2)" );
		$net = Digitalisimo_AI_Usage_Store::network_sites( array( 1, 2 ), Digitalisimo_AI_Usage::days() + array( 'from' => $today, 'to' => $today ) );
		$note( isset( $net[1], $net[2] ) && abs( $net[1]['range']['cost_usd'] - $s1['periods']['range']['cost_usd'] ) < 1e-8 && abs( $net[2]['range']['cost_usd'] - $s2['periods']['range']['cost_usd'] ) < 1e-8, sprintf( 'la red suma cada sitio por separado (US$ %.8F y US$ %.8F)', $net[1]['range']['cost_usd'] ?? -1, $net[2]['range']['cost_usd'] ?? -1 ) );
		$tables = $wpdb->get_col( "SHOW TABLES LIKE '%digitalisimo_ai_usage%'" );
		$note( 4 === count( $tables ), 'tablas propias por sitio con dbDelta: ' . implode( ', ', $tables ) );
		if ( function_exists( 'wp_get_ability' ) ) {
			$ability = wp_get_ability( 'digitalisimo-ia/usage' );
			$out     = $ability ? $ability->execute( array() ) : null;
			$note( is_array( $out ) && isset( $out['periods']['month'], $out['by_feature'], $out['by_model'] ) && false === strpos( wp_json_encode( $out ), $key ), 'la Ability digitalisimo-ia/usage responde al administrador, sin la clave' );
			wp_set_current_user( (int) get_site_option( 'digi_probe_editor' ) );
			$denied = $ability ? $ability->execute( array() ) : null;
			$note( is_wp_error( $denied ), 'la Ability no responde a una editora' );
			wp_set_current_user( 1 );
		} else {
			$note( true, 'esta versión de WordPress no trae la Abilities API: la Ability no se registra' );
		}
		$csv = Digitalisimo_AI_Usage_Admin::csv_text( Digitalisimo_AI_Usage_Admin::csv_rows( 'site', 'detail', $today, $today ) );
		$note( substr_count( $csv, "\n" ) === $n1 + 1 && false === strpos( $csv, DIGI_PROMPT ) && false === strpos( $csv, $key ), 'CSV de detalle con una línea por llamada, sin texto ni clave' );
		$_GET = array( 'page' => 'digitalisimo-ai-usage' );
		ob_start(); Digitalisimo_AI_Usage_Admin::page(); $html = ob_get_clean();
		$note( false !== strpos( $html, 'Llamadas más caras' ) && false !== strpos( $html, 'Asistente IA' ) && false === strpos( $html, $key ), 'la pantalla «Uso de IA» se pinta con los datos y sin la clave' );
		$dump = digi_dump();
		foreach ( array( 'la clave' => $key, 'el prompt' => DIGI_PROMPT, 'la pregunta del visitante' => 'Qué es este sitio', 'la IP del visitante' => DIGI_VISITOR_IP, 'la respuesta' => 'hola mundo de prueba' ) as $what => $secret ) {
			$note( '' !== $secret && false === stripos( $dump, $secret ), "el registro no guarda $what" );
		}
		break;

	case 'pausa':
		wp_set_current_user( 1 );
		$before = count( digi_rows() );
		// Un tope menor que lo ya gastado (se escribe directo: la pantalla pide al menos un centavo).
		update_option( Digitalisimo_AI_Usage::SETTINGS, array( 'inherit_network' => 0, 'budget_enabled' => 1, 'budget_usd' => 0.0000001, 'budget_action' => 'pause' ), false );
		$r = Digitalisimo_AI::complete( 'chatbot', 'hola' );
		$note( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'en pausa' ), 'con el tope alcanzado y «pausar», complete() da el mensaje: ' . ( is_wp_error( $r ) ? $r->get_error_message() : 'respondió' ) );
		$note( ! $GLOBALS['digi_raw'] && count( digi_rows() ) === $before, 'en pausa no sale ninguna petición al proveedor' );
		switch_to_blog( 1 ); $paused1 = Digitalisimo_AI_Usage::paused(); restore_current_blog();
		$note( ! $paused1, 'la pausa del sitio 2 no pausa al sitio 1' );
		delete_option( Digitalisimo_AI_Usage::SETTINGS );
		break;

	case 'limpiar':
		foreach ( array( 1, 2 ) as $blog ) {
			switch_to_blog( $blog );
			$o = get_option( 'digitalisimo_ai_options' );
			$o['providers']['anthropic']['key'] = '';
			update_option( 'digitalisimo_ai_options', $o, false );
			restore_current_blog();
		}
		delete_site_option( 'digi_probe_fingerprint' );
		$note( '' === ( get_blog_option( 1, 'digitalisimo_ai_options' )['providers']['anthropic']['key'] ?? 'x' ) && '' === ( get_blog_option( 2, 'digitalisimo_ai_options' )['providers']['anthropic']['key'] ?? 'x' ), 'la clave se quitó de los dos sitios' );
		break;
}
