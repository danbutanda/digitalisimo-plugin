<?php
/**
 * IA Tools · Registro de uso de tokens (docs/IA_USAGE.md), sin WordPress: funciones mínimas con el mismo contrato y una
 * base SQLite en memoria detrás de un $wpdb de prueba (traduce lo propio de MySQL que usa el almacén: dbDelta,
 * ON DUPLICATE KEY UPDATE, SHOW TABLES LIKE). wp_remote_post() hace lo mismo que WP_Http::request(): primero
 * `pre_http_request` y, tras la respuesta, `http_api_debug`.
 *
 *   K  costo con caché (Anthropic 5 min y 1 h, OpenAI, DeepSeek, Gemini con razonamiento, tramo largo, imágenes) y
 *      «sin precio»;
 *   F  cada función queda registrada por el único punto (chatbot público, asistente editorial, contenido, SEO,
 *      automatizaciones, cambio de proveedor como reintento, Asistente IA con su reintento, aprendizaje, imágenes, base
 *      de conocimiento), y nada más (otra clave, otro dominio);
 *   R  el resumen diario, los desgloses y las llamadas más caras salen de las tablas;
 *   I  aislamiento entre sitios de una red (tablas por prefijo, la Ability y la pantalla de cada sitio, la red suma);
 *   P  permisos (pantallas, CSV, ajustes en una red, Ability con manage_options);
 *   B  presupuesto: 80 % (aviso descartable y correo programado), 100 % sólo avisar, 100 % pausar (no sale la llamada,
 *      mensaje claro en complete() y en el Asistente), mes nuevo;
 *   C  CSV (encabezados, valores, celdas sin fórmulas, BOM);
 *   S  nunca se guardan contenido, claves ni datos del visitante;
 *   A  ajustes con paridad sitio/red, precios corregidos y recálculo de lo «sin precio», retención.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'DIGITALISIMO_CHATBOT_URL', 'https://sitio.test/wp-content/plugins/digitalisimo.chatbot/' );
define( 'DIGITALISIMO_CHATBOT_VERSION', '9.9.9' );
require __DIR__ . '/lib/browser-form.php';
$checks = 0;
function check( $condition, $message ) { global $checks; if ( ! $condition ) { echo "FALLA: $message\n"; exit( 1 ); } $checks++; }
function near( $a, $b, $eps = 1e-9 ) { return null !== $a && abs( (float) $a - (float) $b ) < $eps; }

const KEY_OPENAI    = 'sk-test-OPENAI-0123456789abcdef';
const KEY_ANTHROPIC = 'sk-ant-test-CLAVE-0123456789abcdef';
const KEY_GOOGLE    = 'AIza-test-GOOGLE-0123456789';
const KEY_AJENA     = 'sk-ajena-de-otro-plugin-000';
const SECRET_PROMPT = 'PROMPT-SECRETO-del-visitante-no-guardar';
const SECRET_ANSWER = 'RESPUESTA-SECRETA-del-modelo-no-guardar';
const VISITOR_IP    = '203.0.113.77';

/* ---------------- WordPress mínimo ---------------- */
class WP_Error {
	public $code; public $message; public $data;
	public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
class WP_REST_Response { public $data; public function __construct( $d ) { $this->data = $d; } public function get_data() { return $this->data; } }
class WP_REST_Request implements ArrayAccess {
	private $p; public function __construct( array $p = array() ) { $this->p = $p; }
	public function get_param( $k ) { return $this->p[ $k ] ?? null; }
	#[\ReturnTypeWillChange]
	public function offsetExists( $k ) { return isset( $this->p[ $k ] ); }
	#[\ReturnTypeWillChange]
	public function offsetGet( $k ) { return $this->p[ $k ] ?? null; }
	#[\ReturnTypeWillChange]
	public function offsetSet( $k, $v ) { $this->p[ $k ] = $v; }
	#[\ReturnTypeWillChange]
	public function offsetUnset( $k ) { unset( $this->p[ $k ] ); }
}
#[\AllowDynamicProperties]
class WP_Post { public function __construct( array $d ) { foreach ( $d as $k => $v ) $this->$k = $v; } }
class Died extends Exception {}

$GLOBALS['hooks'] = array(); $GLOBALS['blog'] = 1; $GLOBALS['options'] = array( 1 => array(), 2 => array() ); $GLOBALS['site_options'] = array();
$GLOBALS['multisite'] = false; $GLOBALS['network_admin'] = false; $GLOBALS['user'] = 1; $GLOBALS['usermeta'] = array(); $GLOBALS['mail'] = array(); $GLOBALS['cron'] = array();
$GLOBALS['today'] = '2026-11-20'; $GLOBALS['transients'] = array(); $GLOBALS['http_log'] = array();
$GLOBALS['users'] = array(
	1 => array( 'name' => 'Ana Admin', 'caps' => array( 'manage_options', 'edit_posts', 'upload_files', 'read', 'manage_network_options' ), 'super' => true ),
	2 => array( 'name' => 'Eli Editora', 'caps' => array( 'edit_posts', 'upload_files', 'read' ), 'super' => false ),
	3 => array( 'name' => 'Sitio Admin', 'caps' => array( 'manage_options', 'edit_posts', 'read' ), 'super' => false ),
);
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooks'][ $h ][ $p ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooks'][ $h ][ $p ][] = $cb; }
function apply_filters( $h, $v, ...$a ) { $list = $GLOBALS['hooks'][ $h ] ?? array(); ksort( $list ); foreach ( $list as $cbs ) foreach ( $cbs as $cb ) $v = call_user_func_array( $cb, array_merge( array( $v ), $a ) ); return $v; }
function do_action( $h, ...$a ) { $list = $GLOBALS['hooks'][ $h ] ?? array(); ksort( $list ); foreach ( $list as $cbs ) foreach ( $cbs as $cb ) call_user_func_array( $cb, $a ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function is_multisite() { return $GLOBALS['multisite']; }
function is_network_admin() { return $GLOBALS['network_admin']; }
function get_current_blog_id() { return $GLOBALS['blog']; }
function get_current_user_id() { return $GLOBALS['user']; }
function is_user_logged_in() { return $GLOBALS['user'] > 0; }
function current_user_can( $cap ) { $u = $GLOBALS['users'][ $GLOBALS['user'] ] ?? null; return $u && in_array( $cap, $u['caps'], true ); }
function is_super_admin() { $u = $GLOBALS['users'][ $GLOBALS['user'] ] ?? null; return $u && ! empty( $u['super'] ); }
function get_userdata( $id ) { $u = $GLOBALS['users'][ $id ] ?? null; return $u ? (object) array( 'ID' => $id, 'display_name' => $u['name'] ) : false; }
function get_option( $k, $d = false ) { return $GLOBALS['options'][ $GLOBALS['blog'] ][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $GLOBALS['blog'] ][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['options'][ $GLOBALS['blog'] ][ $k ] ); return true; }
function get_blog_option( $b, $k, $d = false ) { return $GLOBALS['options'][ $b ][ $k ] ?? $d; }
function get_site_option( $k, $d = false ) { return $GLOBALS['site_options'][ $k ] ?? $d; }
function update_site_option( $k, $v ) { $GLOBALS['site_options'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['transients'][ $GLOBALS['blog'] ][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['transients'][ $GLOBALS['blog'] ][ $k ] = $v; return true; }
function get_user_meta( $u, $k, $s = false ) { return $GLOBALS['usermeta'][ $u ][ $k ] ?? ''; }
function update_user_meta( $u, $k, $v ) { $GLOBALS['usermeta'][ $u ][ $k ] = $v; return true; }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u, $c ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $t ) ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_email( $e ) { return trim( (string) $e ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function absint( $n ) { return abs( (int) $n ); }
function esc_url_raw( $u ) { return (string) $u; }
function esc_url( $u ) { return (string) $u; }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_js( $t ) { return addslashes( (string) $t ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function wp_unslash( $v ) { return $v; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_strip_all_tags( $t ) { return trim( strip_tags( (string) $t ) ); }
function wp_trim_words( $t, $n = 55, $m = '…' ) { $w = preg_split( '/\s+/', trim( (string) $t ) ); return implode( ' ', array_slice( $w, 0, $n ) ); }
function remove_accents( $t ) { return (string) $t; }
function wp_rand( $a = 0, $b = 0 ) { return $a; }
function home_url( $p = '' ) { return 'https://sitio' . $GLOBALS['blog'] . '.test' . $p; }
function admin_url( $p = '' ) { return 'https://sitio' . $GLOBALS['blog'] . '.test/wp-admin/' . $p; }
function network_admin_url( $p = '' ) { return 'https://red.test/wp-admin/network/' . $p; }
function get_admin_url( $b, $p = '' ) { return 'https://sitio' . $b . '.test/wp-admin/' . $p; }
function rest_url( $p = '' ) { return 'https://sitio.test/wp-json/' . $p; }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=n:' . $a; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="n:' . $a . '">'; }
function check_admin_referer( $a ) { $n = $_POST['_wpnonce'] ?? ( $_GET['_wpnonce'] ?? '' ); if ( 'n:' . $a !== $n ) throw new Died( 'nonce' ); return true; }
function submit_button( $text = 'Guardar', $type = 'primary', $name = 'submit' ) { echo '<input type="submit" name="' . $name . '" value="' . $text . '">'; }
function checked( $a, $b = true, $e = true ) { return $a == $b ? 'checked' : ''; }
function selected( $a, $b = true, $e = true ) { return $a == $b ? 'selected' : ''; }
function wp_die( $m = '', $c = 0 ) { throw new Died( is_string( $m ) ? $m : 'die' ); }
function wp_safe_redirect( $u ) { $GLOBALS['redirect'] = $u; }
function wp_get_referer() { return 'https://sitio1.test/wp-admin/index.php'; }
function nocache_headers() {}
function wp_mail( $to, $subject, $body ) { $GLOBALS['mail'][] = array( $to, $subject, $body ); return true; }
function wp_next_scheduled( $h ) { return $GLOBALS['cron'][ $GLOBALS['blog'] ][ $h ] ?? false; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['cron'][ $GLOBALS['blog'] ][ $h ] = $t; return true; }
function wp_schedule_event( $t, $r, $h ) { $GLOBALS['cron'][ $GLOBALS['blog'] ][ $h ] = $t; return true; }
function wp_date( $f ) { return $GLOBALS['today']; }
function current_time( $f ) { return 'mysql' === $f ? $GLOBALS['today'] . ' 12:00:00' : $GLOBALS['today']; }
function get_bloginfo( $k ) { return 'Sitio ' . $GLOBALS['blog']; }
function get_post( $id ) { return null; }
function get_posts( $a ) { return array( new WP_Post( array( 'ID' => 5, 'post_title' => 'Entrada pública', 'post_content' => 'Texto público.' ) ) ); }
function rest_ensure_response( $d ) { return new WP_REST_Response( $d ); }
function get_sites( $a = array() ) { return array( 1, 2 ); }
function get_blog_details( $id ) { return (object) array( 'blogname' => 'Sitio ' . $id, 'siteurl' => 'https://sitio' . $id . '.test' ); }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? (string) ( $r['body'] ?? '' ) : ''; }
function switch_blog( $id ) { global $wpdb; $GLOBALS['blog'] = $id; $wpdb->prefix = $wpdb->get_blog_prefix( $id ); Digitalisimo_AI_Usage_Prices::reset(); }

/* El proveedor simulado: responde según la URL y lo que se le programe. */
$GLOBALS['replies'] = array();
function reply( $match, $code, $body ) { $GLOBALS['replies'][] = array( $match, $code, $body ); }
/** Igual que WP_Http::request(): pre_http_request puede cortar; si no, la respuesta pasa por http_api_debug. */
function wp_remote_post( $url, $args = array() ) {
	$args = array_merge( array( 'method' => 'POST', 'headers' => array(), 'body' => '' ), $args );
	$pre  = apply_filters( 'pre_http_request', false, $args, $url );
	if ( false !== $pre ) return $pre;
	$GLOBALS['http_log'][] = $url;
	$response = new WP_Error( 'http_request_failed', 'sin respuesta programada' );
	foreach ( $GLOBALS['replies'] as $i => $r ) {
		if ( false !== strpos( $url, $r[0] ) ) { unset( $GLOBALS['replies'][ $i ] ); $response = null === $r[1] ? new WP_Error( 'http_request_failed', 'cURL error 28' ) : array( 'response' => array( 'code' => $r[1] ), 'body' => is_string( $r[2] ) ? $r[2] : json_encode( $r[2] ) ); break; }
	}
	do_action( 'http_api_debug', $response, 'response', 'WpOrg\Requests\Requests', $args, $url );
	return $response;
}

/* $wpdb de prueba sobre SQLite: sólo traduce lo que usa el almacén. */
class Fake_WPDB {
	public $prefix = 'wp_'; public $base_prefix = 'wp_'; public $pdo; public $queries = 0; public $log = array();
	public function __construct() { $this->pdo = new PDO( 'sqlite::memory:' ); $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION ); }
	public function get_blog_prefix( $id ) { return (int) $id > 1 ? 'wp_' . (int) $id . '_' : 'wp_'; }
	public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
	public function suppress_errors( $s = true ) { return false; }
	public function esc_like( $t ) { return addcslashes( (string) $t, '_%\\' ); }
	public function prepare( $q, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) $args = $args[0];
		$i = 0;
		return preg_replace_callback( '/%[sdfF]/', function ( $m ) use ( &$i, $args ) { $v = $args[ $i++ ]; if ( '%d' === $m[0] ) return (string) (int) $v; if ( '%s' !== $m[0] ) return sprintf( '%.6F', (float) $v ); return "'" . str_replace( "'", "''", (string) $v ) . "'"; }, $q );
	}
	private function sql( $q ) {
		$this->queries++; $this->log[] = $q;
		if ( preg_match( "/^SHOW TABLES LIKE (.+)$/", $q, $m ) ) return "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE {$m[1]} ESCAPE '\\'";
		$q = str_replace( 'ON DUPLICATE KEY UPDATE', 'ON CONFLICT(day, feature, provider, model, user_id) DO UPDATE SET', $q );
		return preg_replace( '/VALUES\((\w+)\)/', 'excluded.$1', $q );
	}
	public function create( $q ) {
		preg_match( '/CREATE TABLE (\S+) \((.*)\)[^)]*$/s', $q, $m );
		$cols = array(); $pk = '';
		foreach ( preg_split( '/,\n/', trim( $m[2] ) ) as $line ) {
			$line = trim( $line );
			if ( preg_match( '/^PRIMARY KEY\s+\((.+)\)/', $line, $p ) ) { $pk = $p[1]; continue; }
			if ( preg_match( '/^KEY /', $line ) ) continue;
			if ( false !== strpos( $line, 'AUTO_INCREMENT' ) ) { $cols[] = preg_replace( '/\s.*$/', '', $line ) . ' INTEGER PRIMARY KEY AUTOINCREMENT'; $pk = ''; continue; }
			$cols[] = str_replace( array( ' unsigned', 'decimal(16,8)', 'decimal(18,8)' ), array( '', 'REAL', 'REAL' ), $line );
		}
		if ( $pk && false === strpos( implode( ',', $cols ), 'AUTOINCREMENT' ) ) $cols[] = 'PRIMARY KEY (' . $pk . ')';
		$this->pdo->exec( 'CREATE TABLE IF NOT EXISTS ' . $m[1] . ' (' . implode( ', ', $cols ) . ')' );
	}
	public function query( $q ) { $s = $this->pdo->prepare( $this->sql( $q ) ); $s->execute(); return $s->rowCount(); }
	public function insert( $table, $data, $formats = array() ) {
		$cols = array_keys( $data ); $vals = array();
		foreach ( array_values( $data ) as $i => $v ) $vals[] = null === $v ? 'NULL' : ( '%d' === ( $formats[ $i ] ?? '%s' ) ? (string) (int) $v : "'" . str_replace( "'", "''", (string) $v ) . "'" );
		return $this->query( "INSERT INTO {$table} (" . implode( ', ', $cols ) . ') VALUES (' . implode( ', ', $vals ) . ')' );
	}
	public function get_results( $q, $o = null ) { $s = $this->pdo->query( $this->sql( $q ) ); return $s->fetchAll( PDO::FETCH_ASSOC ); }
	public function get_row( $q, $o = null ) { $r = $this->get_results( $q ); return $r[0] ?? null; }
	public function get_var( $q ) { $r = $this->get_results( $q ); return $r ? reset( $r[0] ) : null; }
	public function get_col( $q ) { return array_map( function ( $r ) { return reset( $r ); }, $this->get_results( $q ) ); }
	/** Todo lo guardado, en texto (para buscar secretos). */
	public function dump() { $out = ''; foreach ( $this->get_col( "SELECT name FROM sqlite_master WHERE type = 'table'" ) as $t ) $out .= $t . ':' . json_encode( $this->pdo->query( "SELECT * FROM {$t}" )->fetchAll( PDO::FETCH_ASSOC ) ); return $out; }
}
function dbDelta( $queries ) { global $wpdb; foreach ( (array) $queries as $q ) $wpdb->create( $q ); }
$GLOBALS['wpdb'] = new Fake_WPDB();
global $wpdb;

require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-knowledge.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-images.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant-model.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-usage-prices.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-usage-store.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-usage.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-usage-admin.php';
Digitalisimo_AI_Usage::init();

function ia_options( array $extra = array() ) {
	update_option( Digitalisimo_AI::OPTION, array_merge( array(
		'inherit_network' => 0, 'chat_enabled' => 1, 'knowledge_enabled' => 1, 'knowledge_embeddings_enabled' => 1, 'knowledge_semantic_rerank' => 1,
		'providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => '' ), 'openai' => array( 'enabled' => 1, 'key' => KEY_OPENAI, 'url' => '' ), 'google' => array( 'enabled' => 1, 'key' => KEY_GOOGLE, 'url' => '' ) ),
		'profiles' => array( 'chatbot' => array( 'provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'fallback' => '' ), 'content' => array( 'provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'fallback' => '' ), 'seo' => array( 'provider' => 'openai', 'model' => 'gpt-4.1', 'fallback' => '' ), 'ecommerce' => array( 'provider' => 'auto', 'model' => '', 'fallback' => '' ), 'automation' => array( 'provider' => 'google', 'model' => 'gemini-2.5-flash', 'fallback' => 'anthropic' ), 'images' => array( 'provider' => 'openai', 'model' => 'gpt-image-1', 'fallback' => '' ) ),
	), $extra ) );
}
function anthropic_reply( $in, $out, $read = 0, $write = 0, $model = 'claude-haiku-5-5' ) { return array( 'id' => 'msg_1', 'model' => $model, 'content' => array( array( 'type' => 'text', 'text' => SECRET_ANSWER ) ), 'stop_reason' => 'end_turn', 'usage' => array( 'input_tokens' => $in, 'output_tokens' => $out, 'cache_read_input_tokens' => $read, 'cache_creation_input_tokens' => $write ) ); }
function rows( $where = '1 = 1' ) { global $wpdb; return $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'digitalisimo_ai_usage WHERE ' . $where . ' ORDER BY id ASC' ); }
function last_row() { $r = rows(); return end( $r ); }

/* ================= K · costo con caché ================= */
$P = 'Digitalisimo_AI_Usage_Prices';
// Opus 5.5: $4 entrada, $20 salida, $0.20 lectura, $5 escritura 5 min, $8 escritura 1 h.
check( near( $P::cost( $P::find( 'claude-opus-5-5' ), array( 'input' => 1000, 'output' => 500, 'cache_read' => 100000, 'cache_write' => 20000 ) ), ( 1000 * 4 + 500 * 20 + 100000 * 0.20 + 20000 * 5 ) / 1e6 ), 'K: Opus 5.5 con lectura y escritura de caché' );
check( near( $P::cost( $P::find( 'claude-opus-5-5' ), array( 'input' => 1000, 'output' => 500, 'cache_read' => 100000, 'cache_write' => 20000, 'cache_write_1h' => 10000 ) ), ( 1000 * 4 + 500 * 20 + 100000 * 0.20 + 10000 * 5 + 10000 * 8 ) / 1e6 ), 'K: la parte escrita por 1 h se cobra a su precio' );
check( near( $P::cost( $P::find( 'claude-sonnet-5-5-20261101' ), array( 'input' => 1000000 ) ), 2.0 ), 'K: un ID con fecha usa el precio del modelo' );
// Haiku 5.5: más de 100 000 tokens de entrada (contando la caché) pasa al tramo largo.
check( near( $P::cost( $P::find( 'claude-haiku-5-5' ), array( 'input' => 50000, 'cache_read' => 60000, 'output' => 1000 ) ), ( 50000 * 0.50 + 60000 * 0.05 + 1000 * 2.50 ) / 1e6 ), 'K: Haiku 5.5 sobre 100 000 tokens usa el precio largo' );
check( near( $P::cost( $P::find( 'claude-haiku-5-5' ), array( 'input' => 50000, 'cache_read' => 40000, 'output' => 1000 ) ), ( 50000 * 0.10 + 40000 * 0.01 + 1000 * 0.50 ) / 1e6 ), 'K: Haiku 5.5 bajo 100 000 tokens usa el precio normal' );
check( null === $P::cost( $P::find( 'modelo-inventado-9' ), array( 'input' => 10 ) ), 'K: un modelo sin precio da null («sin precio»)' );
$U = 'Digitalisimo_AI_Usage';
// OpenAI: lo leído de la caché va dentro de prompt_tokens.
$p = $U::parse( 'openai', 'https://api.openai.com/v1/chat/completions', '{"model":"gpt-4.1","messages":[]}', json_encode( array( 'model' => 'gpt-4.1-2025-04-14', 'usage' => array( 'prompt_tokens' => 10000, 'completion_tokens' => 1000, 'prompt_tokens_details' => array( 'cached_tokens' => 8000 ) ) ) ) );
check( 'gpt-4.1' === $p['model'] && 2000 === $p['usage']['input'] && 8000 === $p['usage']['cache_read'] && 1000 === $p['usage']['output'], 'K: OpenAI separa la caché de prompt_tokens' );
check( near( $P::cost( $P::find( $p['model'] ), $p['usage'] ), ( 2000 * 2 + 8000 * 0.5 + 1000 * 8 ) / 1e6 ), 'K: costo de OpenAI con entrada en caché' );
// DeepSeek: prompt_cache_hit_tokens.
$p = $U::parse( 'deepseek', 'https://api.deepseek.com/v1/chat/completions', '{"model":"deepseek-flash"}', json_encode( array( 'usage' => array( 'prompt_tokens' => 5000, 'completion_tokens' => 100, 'prompt_cache_hit_tokens' => 4000, 'prompt_cache_miss_tokens' => 1000 ) ) ) );
check( 1000 === $p['usage']['input'] && 4000 === $p['usage']['cache_read'], 'K: DeepSeek separa sus aciertos de caché' );
// Gemini: el modelo va en la URL, la caché dentro de promptTokenCount y el razonamiento se cobra como salida.
$p = $U::parse( 'google', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', '{"contents":[]}', json_encode( array( 'usageMetadata' => array( 'promptTokenCount' => 3000, 'cachedContentTokenCount' => 1000, 'candidatesTokenCount' => 200, 'thoughtsTokenCount' => 300 ), 'modelVersion' => 'gemini-2.5-flash' ) ) );
check( 'gemini-2.5-flash' === $p['model'] && 2000 === $p['usage']['input'] && 1000 === $p['usage']['cache_read'] && 500 === $p['usage']['output'], 'K: Gemini cuenta el razonamiento como salida y separa la caché' );
// Anthropic: escritura de 1 h.
$p = $U::parse( 'anthropic', 'https://api.anthropic.com/v1/messages', '{"model":"claude-opus-5-5"}', json_encode( array( 'usage' => array( 'input_tokens' => 10, 'output_tokens' => 20, 'cache_read_input_tokens' => 30, 'cache_creation_input_tokens' => 40, 'cache_creation' => array( 'ephemeral_5m_input_tokens' => 15, 'ephemeral_1h_input_tokens' => 25 ) ) ) ) );
check( 10 === $p['usage']['input'] && 30 === $p['usage']['cache_read'] && 40 === $p['usage']['cache_write'] && 25 === $p['usage']['cache_write_1h'], 'K: Anthropic separa lectura, escritura y escritura de 1 h' );
// Imagen: tokens de salida de la imagen; sin tokens, por imagen. Respuesta enorme: sólo su bloque de uso.
$big = '{"created":1,"data":[{"b64_json":"' . str_repeat( 'A', 1100000 ) . '"}],"usage":{"input_tokens":50,"output_tokens":1568,"input_tokens_details":{"text_tokens":50,"image_tokens":0}}}';
$p = $U::parse( 'openai', 'https://api.openai.com/v1/images/generations', '{"model":"gpt-image-1","prompt":"x"}', $big );
check( 1 === $p['usage']['units'] && 1568 === $p['usage']['output'] && 50 === $p['usage']['input'], 'K: imagen de más de 1 MB: se lee sólo su uso' );
check( near( $P::cost( $P::find( 'gpt-image-1' ), $p['usage'] ), ( 50 * 5 + 1568 * 40 ) / 1e6 ), 'K: imagen cobrada por tokens' );
check( near( $P::cost( $P::find( 'gpt-image-1' ), array( 'units' => 2 ) ), 2 * 0.063 ), 'K: imagen sin tokens se cobra por imagen' );

/* ================= F · cada función por el único punto ================= */
ia_options();
$GLOBALS['user'] = 0; $_SERVER['REMOTE_ADDR'] = VISITOR_IP;
reply( 'api.anthropic.com', 200, anthropic_reply( 120, 40, 0, 0 ) );
$r = Digitalisimo_AI::chat( new WP_REST_Request( array( 'message' => SECRET_PROMPT ) ) );
check( $r instanceof WP_REST_Response, 'F: el chatbot responde' );
$row = last_row();
check( 'chatbot' === $row['feature'] && 0 === (int) $row['user_id'] && 'anthropic' === $row['provider'] && 'claude-haiku-5-5' === $row['model'], 'F: chatbot público registrado, como visitante' );
check( 120 === (int) $row['input_tokens'] && 40 === (int) $row['output_tokens'] && near( $row['cost_usd'], ( 120 * 0.10 + 40 * 0.50 ) / 1e6 ) && ! $row['is_error'] && ! $row['is_retry'], 'F: tokens y costo del chatbot' );
check( 'Visitante' === Digitalisimo_AI_Usage_Admin::user_label( 0, 'chatbot' ), 'F: el usuario 0 del chatbot se ve como «Visitante»' );

$GLOBALS['user'] = 2;
// El metabox busca antes en la base de conocimiento (embedding de la consulta): esa llamada es de la base de conocimiento.
reply( 'api.openai.com/v1/embeddings', 200, array( 'data' => array( array( 'embedding' => array( 0.3 ) ) ), 'usage' => array( 'prompt_tokens' => 7, 'total_tokens' => 7 ) ) );
reply( 'api.openai.com/v1/chat', 200, array( 'model' => 'gpt-4.1', 'choices' => array( array( 'message' => array( 'content' => SECRET_ANSWER ) ) ), 'usage' => array( 'prompt_tokens' => 900, 'completion_tokens' => 100, 'prompt_tokens_details' => array( 'cached_tokens' => 0 ) ) ) );
$r = Digitalisimo_AI::complete_api( new WP_REST_Request( array( 'profile' => 'seo', 'prompt' => SECRET_PROMPT ) ) );
check( $r instanceof WP_REST_Response, 'F: el metabox editorial responde' );
$row = last_row();
check( 'editorial' === $row['feature'] && 2 === (int) $row['user_id'] && 'gpt-4.1' === $row['model'], 'F: asistente editorial registrado con su usuario' );
$prev = rows(); $prev = $prev[ count( $prev ) - 2 ];
check( 'knowledge' === $prev['feature'] && 7 === (int) $prev['input_tokens'] && 2 === (int) $prev['user_id'], 'F: el embedding que pide el metabox queda en la base de conocimiento' );

reply( 'api.anthropic.com', 200, anthropic_reply( 2000, 1500, 0, 0, 'claude-sonnet-5-5' ) );
Digitalisimo_AI::complete( 'content', 'Borrador' );
check( 'content' === last_row()['feature'] && 'claude-sonnet-5-5' === last_row()['model'], 'F: creación de contenido (publisher, playbook) registrada' );
reply( 'api.openai.com', 200, array( 'choices' => array( array( 'message' => array( 'content' => 'Informacional' ) ) ), 'usage' => array( 'prompt_tokens' => 50, 'completion_tokens' => 2 ) ) );
Digitalisimo_AI::complete( 'seo', 'Intención' );
check( 'seo' === last_row()['feature'], 'F: la llamada de DIGITALÍSIMO SEO por complete() registrada como SEO' );

// Automatización con Gemini caído (sin conexión) y Anthropic de alternativa: dos filas, la segunda es reintento.
$before = count( rows() );
reply( 'generativelanguage.googleapis.com', null, '' );
reply( 'api.anthropic.com', 200, anthropic_reply( 300, 30 ) );
$r = Digitalisimo_AI::complete( 'automation', 'Resume' );
$new = array_slice( rows(), $before );
check( 2 === count( $new ) && 'automation' === $new[0]['feature'] && 'google' === $new[0]['provider'] && 'gemini-2.5-flash' === $new[0]['model'] && $new[0]['is_error'] && 0 === (int) $new[0]['status'] && ! $new[0]['is_retry'], 'F: la llamada fallida queda como error' );
check( 'anthropic' === $new[1]['provider'] && $new[1]['is_retry'] && ! $new[1]['is_error'], 'F: el cambio de proveedor tras el error queda como reintento' );
check( near( $new[0]['cost_usd'], 0 ), 'F: un error sin uso no cuesta' );

// Asistente IA: el modelo real con su ciclo (400 por una herramienta → se quita y se repite).
$GLOBALS['user'] = 1;
class Digitalisimo_AI_Assistant_Tools { public static function clip( $t, $n ) { return substr( (string) $t, 0, $n ); } public static function scrub( $t ) { return $t; } public static function log( $e ) {} }
$target = array( 'provider' => 'anthropic', 'model' => 'claude-opus-5-5', 'key' => KEY_ANTHROPIC, 'url' => '', 'name' => 'Anthropic', 'ai_name' => 'Claude', 'profile' => '' );
$tools  = array( array( 'name' => 'leer', 'description' => 'Lee', 'schema' => array( 'type' => 'object' ) ), array( 'name' => 'mala', 'description' => 'Mal', 'schema' => array( 'type' => 'object' ) ) );
$before = count( rows() );
reply( 'api.anthropic.com', 400, array( 'error' => array( 'message' => 'tools.1.input_schema: inválido' ) ) );
reply( 'api.anthropic.com', 200, anthropic_reply( 5000, 800, 40000, 3000, 'claude-opus-5-5' ) );
$r = Digitalisimo_AI_Assistant_Model::chat( $target, 'SYS', array( array( 'role' => 'user', 'text' => SECRET_PROMPT ) ), $tools );
$new = array_slice( rows(), $before );
check( ! is_wp_error( $r ) && 2 === count( $new ), 'F: el Asistente IA registra sus dos peticiones' );
check( 'assistant' === $new[0]['feature'] && $new[0]['is_error'] && 400 === (int) $new[0]['status'] && 'assistant' === $new[1]['feature'] && $new[1]['is_retry'] && 1 === (int) $new[1]['user_id'], 'F: Asistente IA: el rechazo es error y la repetición, reintento' );
check( near( $new[1]['cost_usd'], ( 5000 * 4 + 800 * 20 + 40000 * 0.20 + 3000 * 5 ) / 1e6 ) && 40000 === (int) $new[1]['cache_read_tokens'] && 3000 === (int) $new[1]['cache_write_tokens'], 'F: el Asistente IA guarda la caché leída y escrita y su costo' );
// El paso siguiente del Asistente ya no es reintento.
reply( 'api.anthropic.com', 200, anthropic_reply( 100, 10, 0, 0, 'claude-opus-5-5' ) );
Digitalisimo_AI_Assistant_Model::chat( $target, 'SYS', array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( ! last_row()['is_retry'], 'F: cada paso del Asistente empieza su propia cuenta de reintentos' );
// Aprendizaje del sitio (rama ia/asistente-aprendizaje): abre su contexto y usa el mismo modelo.
$mark = Digitalisimo_AI_Usage::begin( 'learning' );
reply( 'api.anthropic.com', 200, anthropic_reply( 700, 70, 0, 0, 'claude-opus-5-5' ) );
Digitalisimo_AI_Assistant_Model::chat( $target, 'SYS', array( array( 'role' => 'user', 'text' => 'aprende' ) ), array() );
Digitalisimo_AI_Usage::end( $mark );
check( 'learning' === last_row()['feature'], 'F: el aprendizaje del sitio queda con su propia función' );

// Imágenes: la función real (el proveedor rechaza: no hace falta la biblioteca de medios).
$before = count( rows() );
reply( 'api.openai.com/v1/images', 400, array( 'error' => array( 'message' => 'Contenido rechazado' ) ) );
$r = Digitalisimo_AI_Images::generate( new WP_REST_Request( array( 'prompt' => SECRET_PROMPT ) ) );
$new = array_slice( rows(), $before );
check( is_wp_error( $r ) && 1 === count( $new ) && 'images' === $new[0]['feature'] && 'gpt-image-1' === $new[0]['model'] && $new[0]['is_error'], 'F: generación de imágenes registrada' );
// Base de conocimiento: la búsqueda semántica pide un embedding.
reply( 'api.openai.com/v1/embeddings', 200, array( 'data' => array( array( 'embedding' => array( 0.1, 0.2 ) ) ), 'model' => 'text-embedding-3-small', 'usage' => array( 'prompt_tokens' => 12, 'total_tokens' => 12 ) ) );
Digitalisimo_AI_Knowledge::search( 'consulta de prueba' );
$row = last_row();
check( 'knowledge' === $row['feature'] && 'text-embedding-3-small' === $row['model'] && 12 === (int) $row['input_tokens'] && near( $row['cost_usd'], 12 * 0.02 / 1e6 ), 'F: embedding de la base de conocimiento registrado (costo de 8 decimales)' );
// Lo que no es de IA Tools no se registra: otra clave en el mismo proveedor, otro dominio.
$before = count( rows() );
reply( 'api.openai.com', 200, array( 'usage' => array( 'prompt_tokens' => 99, 'completion_tokens' => 1 ) ) );
wp_remote_post( 'https://api.openai.com/v1/chat/completions', array( 'headers' => array( 'Authorization' => 'Bearer ' . KEY_AJENA ), 'body' => '{"model":"gpt-4o-mini"}' ) );
reply( 'api.wordpress.org', 200, '{}' );
wp_remote_post( 'https://api.wordpress.org/plugins/update-check/1.1/', array( 'headers' => array( 'Authorization' => 'Bearer ' . KEY_OPENAI ) ) );
check( count( rows() ) === $before, 'F: llamadas con otra clave o a otro dominio no se registran' );
$q = $wpdb->queries; reply( 'downloads.wordpress.org', 200, '{}' ); wp_remote_post( 'https://downloads.wordpress.org/x.zip' );
check( $q === $wpdb->queries, 'F: una petición HTTP ajena no hace ninguna consulta a la base' );
// URL compatible propia (un proxy): cuenta si lleva la clave de IA Tools.
$o = get_option( Digitalisimo_AI::OPTION ); $o['providers']['openai']['url'] = 'https://proxy.ejemplo.test/v1/chat/completions'; update_option( Digitalisimo_AI::OPTION, $o );
reply( 'proxy.ejemplo.test', 200, array( 'choices' => array( array( 'message' => array( 'content' => 'ok' ) ) ), 'usage' => array( 'prompt_tokens' => 10, 'completion_tokens' => 5 ) ) );
Digitalisimo_AI::complete( 'seo', 'x' );
check( 'openai' === last_row()['provider'] && 'seo' === last_row()['feature'], 'F: la URL compatible configurada también se registra' );
ia_options();
// Más de 8 decimales: el costo se guarda exacto, sin redondear a 6.
check( '0.00000024' === Digitalisimo_AI_Usage_Store::money( 0.00000024 ), 'F: el costo viaja con 8 decimales' );
// Sin contexto, una llamada de IA Tools queda como «Otra función» (nunca se pierde).
reply( 'api.anthropic.com', 200, anthropic_reply( 1, 1 ) );
$ref = new ReflectionProperty( 'Digitalisimo_AI_Usage', 'ambient' ); if ( PHP_VERSION_ID < 80100 ) $ref->setAccessible( true ); $ref->setValue( null, null );
wp_remote_post( 'https://api.anthropic.com/v1/messages', array( 'headers' => array( 'x-api-key' => KEY_ANTHROPIC ), 'body' => '{"model":"claude-haiku-5-5"}' ) );
check( 'other' === last_row()['feature'], 'F: una llamada de IA Tools sin función conocida se registra como «Otra función»' );

/* ================= R · resumen, desgloses, más caras ================= */
$sum = Digitalisimo_AI_Usage::summary();
$detail_total = 0.0; foreach ( rows() as $r ) $detail_total += (float) $r['cost_usd'];
check( count( rows() ) === $sum['periods']['today']['calls'] && near( $sum['periods']['today']['cost_usd'], $detail_total, 1e-7 ), 'R: el resumen diario suma lo mismo que el detalle' );
$features = array_column( $sum['by_feature'], 'feature' );
foreach ( array( 'chatbot', 'editorial', 'content', 'seo', 'automation', 'assistant', 'learning', 'images', 'knowledge', 'other' ) as $f ) check( in_array( $f, $features, true ), "R: el desglose por función incluye $f" );
check( $sum['by_feature'][0]['cost_usd'] >= end( $sum['by_feature'] )['cost_usd'], 'R: desglose de mayor a menor costo' );
$top = Digitalisimo_AI_Usage_Store::top( $GLOBALS['today'], $GLOBALS['today'], 3 );
check( 3 === count( $top ) && 'assistant' === $top[0]['feature'], 'R: la llamada más cara es la del Asistente con Opus' );
$by_user = Digitalisimo_AI_Usage_Store::breakdown( 'user_id', $GLOBALS['today'], $GLOBALS['today'] );
check( in_array( 0, array_column( $by_user, 'user_id' ), true ) && in_array( 2, array_column( $by_user, 'user_id' ), true ), 'R: desglose por usuario (visitante y editora)' );
$GLOBALS['today'] = '2026-11-21';
reply( 'api.anthropic.com', 200, anthropic_reply( 10, 10 ) );
Digitalisimo_AI::complete( 'chatbot', 'x' );
$sum = Digitalisimo_AI_Usage::summary( '2026-11-01', '2026-11-21' );
check( 1 === $sum['periods']['today']['calls'] && $sum['periods']['week']['calls'] === $sum['periods']['range']['calls'] && 2 === count( Digitalisimo_AI_Usage_Store::breakdown( 'day', '2026-11-01', '2026-11-21' ) ), 'R: hoy, 7 días, rango y por día' );

/* ================= S · sin contenido, claves ni datos del visitante ================= */
$all = $wpdb->dump() . json_encode( $GLOBALS['options'] ) . json_encode( $GLOBALS['site_options'] );
$stored_opts = $GLOBALS['options'][1]; unset( $stored_opts[ Digitalisimo_AI::OPTION ] ); // Las claves viven en la opción de IA Tools, no en el registro.
$registro = $wpdb->dump() . json_encode( $stored_opts );
foreach ( array( SECRET_PROMPT, SECRET_ANSWER, KEY_OPENAI, KEY_ANTHROPIC, KEY_GOOGLE, VISITOR_IP, 'Entrada pública', 'Texto público' ) as $secret ) check( false === strpos( $registro, $secret ), 'S: el registro no guarda «' . $secret . '»' );

/* ================= C · CSV ================= */
$csv = Digitalisimo_AI_Usage_Admin::csv_rows( 'site', 'detail', '2026-11-01', '2026-11-21' );
check( 'fecha_utc' === $csv[0][0] && in_array( 'costo_usd', $csv[0], true ) && in_array( 'reintento', $csv[0], true ) && count( $csv ) === count( rows() ) + 1, 'C: el CSV de detalle trae encabezado y una fila por llamada' );
$text = Digitalisimo_AI_Usage_Admin::csv_text( $csv );
check( 0 === strpos( $text, "\xEF\xBB\xBF" ) && false !== strpos( $text, 'Chatbot público' ) && false !== strpos( $text, 'Visitante' ) && false === strpos( $text, SECRET_PROMPT ) && false === strpos( $text, KEY_ANTHROPIC ), 'C: CSV con BOM, nombres legibles y sin contenido ni claves' );
check( "'=HYPERLINK(1)" === Digitalisimo_AI_Usage_Admin::cell( '=HYPERLINK(1)' ) && '-0.5' === Digitalisimo_AI_Usage_Admin::cell( '-0.5' ), 'C: una celda que empieza con = no se vuelve fórmula; un número sí queda número' );
$daily = Digitalisimo_AI_Usage_Admin::csv_rows( 'site', 'daily', '2026-11-01', '2026-11-21' );
$sum_csv = 0.0; foreach ( array_slice( $daily, 1 ) as $r ) $sum_csv += (float) $r[15];
check( 'dia' === $daily[0][0] && near( $sum_csv, $sum['periods']['range']['cost_usd'], 1e-6 ), 'C: el CSV diario suma el total del rango' );
update_option( Digitalisimo_AI_Usage::SETTINGS, array( 'mxn_rate' => 18.5 ) );
$csv = Digitalisimo_AI_Usage_Admin::csv_rows( 'site', 'detail', '2026-11-21', '2026-11-21' );
check( near( (float) $csv[1][14], (float) $csv[1][13] * 18.5, 1e-4 ), 'C: el CSV trae el costo en MXN con el tipo de cambio' );
// La descarga exige nonce y permiso.
$_GET = $_REQUEST = array( 'scope' => 'site', 'type' => 'detail', '_wpnonce' => 'n:' . Digitalisimo_AI_Usage_Admin::CSV );
$GLOBALS['user'] = 2; $died = false; try { Digitalisimo_AI_Usage_Admin::csv(); } catch ( Died $e ) { $died = true; }
check( $died, 'P: una editora no descarga el CSV' );
$_GET = $_REQUEST = array( 'scope' => 'site', 'type' => 'detail' );
$GLOBALS['user'] = 1; $died = false; try { Digitalisimo_AI_Usage_Admin::csv(); } catch ( Died $e ) { $died = 'nonce' === $e->getMessage(); }
check( $died, 'P: sin nonce no hay CSV' );

/* ================= P · permisos ================= */
$GLOBALS['user'] = 2; ob_start(); Digitalisimo_AI_Usage_Admin::page(); check( '' === ob_get_clean(), 'P: la pantalla de uso exige manage_options' );
check( false === Digitalisimo_AI_Usage::ability_permission(), 'P: la Ability no es para una editora' );
$GLOBALS['user'] = 0; check( false === Digitalisimo_AI_Usage::ability_permission(), 'P: la Ability no es para visitantes' );
$GLOBALS['user'] = 1; check( true === Digitalisimo_AI_Usage::ability_permission(), 'P: la Ability es para quien tiene manage_options' );
$def = Digitalisimo_AI_Usage::ability_definition();
check( ! empty( $def['meta']['annotations']['readonly'] ) && empty( $def['meta']['annotations']['destructive'] ) && 'digitalisimo-ia' === $def['category'], 'P: la Ability es de sólo lectura' );
$out = Digitalisimo_AI_Usage::ability_execute( array( 'from' => '2026-11-01', 'to' => '2026-11-21' ) );
$json = json_encode( $out );
check( isset( $out['periods']['today'], $out['periods']['week'], $out['periods']['month'], $out['periods']['range'], $out['by_feature'], $out['by_model'], $out['budget'] ) && 'Chatbot público' === array_column( $out['by_feature'], 'label', 'feature' )['chatbot'], 'P: la Ability da totales por periodo, función y modelo' );
foreach ( array( KEY_ANTHROPIC, KEY_OPENAI, KEY_GOOGLE, SECRET_PROMPT, SECRET_ANSWER ) as $secret ) check( false === strpos( $json, $secret ), 'P: la Ability no devuelve claves ni contenido' );
check( in_array( 'digitalisimo-ia/usage', Digitalisimo_AI_Usage::server_tools( array() ), true ) || ! function_exists( 'wp_register_ability' ), 'P: la Ability se ofrece al MCP de Elements cuando existe la Abilities API' );
ob_start(); Digitalisimo_AI_Usage_Admin::page(); $html = ob_get_clean();
check( false !== strpos( $html, 'Uso de IA' ) && false !== strpos( $html, 'Por función' ) && false !== strpos( $html, 'Llamadas más caras' ) && false !== strpos( $html, 'MX$' ) && false !== strpos( $html, 'digitalisimo-usage-chart' ) && false === strpos( $html, KEY_ANTHROPIC ), 'P: la pantalla pinta totales, gráfica, desgloses y MXN, sin claves' );

/* ================= A · ajustes, precios y retención ================= */
// Un modelo sin precio se registra igual y la pantalla avisa; al agregar su precio se calcula.
reply( 'api.openai.com', 200, array( 'choices' => array( array( 'message' => array( 'content' => 'x' ) ) ), 'usage' => array( 'prompt_tokens' => 1000000, 'completion_tokens' => 0 ) ) );
$o = get_option( Digitalisimo_AI::OPTION ); $o['profiles']['seo']['model'] = 'gpt-9-nuevo'; update_option( Digitalisimo_AI::OPTION, $o );
Digitalisimo_AI::complete( 'seo', 'x' );
check( null === last_row()['cost_usd'] && 'gpt-9-nuevo' === last_row()['model'], 'A: el modelo sin precio se registra con costo «sin precio»' );
ob_start(); Digitalisimo_AI_Usage_Admin::page(); $html = ob_get_clean();
check( false !== strpos( $html, 'Hay llamadas sin precio' ) && false !== strpos( $html, 'gpt-9-nuevo' ), 'A: la pantalla avisa del modelo sin precio' );
$form = digi_browser_form( $html, array( 'usage_prices[' . count( Digitalisimo_AI_Usage_Prices::all() ) . '][model]' => 'gpt-9-nuevo', 'usage_prices[' . count( Digitalisimo_AI_Usage_Prices::all() ) . '][input]' => '3', 'usage_prices[' . count( Digitalisimo_AI_Usage_Prices::all() ) . '][provider]' => 'openai' ), Digitalisimo_AI_Usage_Admin::SAVE, 'field:usage[' );
$_POST = $form['post']; $_REQUEST = $_POST; $_GET = array();
ob_start(); Digitalisimo_AI_Usage_Admin::page(); $html = ob_get_clean(); $_POST = array();
check( false !== strpos( $html, 'Se calculó el costo de 1 llamadas' ) && near( last_row()['cost_usd'], 3.0 ), 'A: al agregar el precio, la llamada sin precio toma su costo' );
check( array( 'gpt-9-nuevo' ) === array_keys( get_option( Digitalisimo_AI_Usage_Prices::OPTION ) ), 'A: sólo se guarda lo que difiere de la tabla de fábrica' );
check( 0 === Digitalisimo_AI_Usage::summary( $GLOBALS['today'], $GLOBALS['today'] )['periods']['today']['unpriced'], 'A: el resumen diario ya no tiene llamadas sin precio' );
// Corregir un precio de fábrica cambia las llamadas nuevas, no las ya registradas.
$old = (float) rows( "model = 'claude-haiku-5-5'" )[0]['cost_usd'];
$i = array_search( 'claude-haiku-5-5', array_keys( Digitalisimo_AI_Usage_Prices::all() ), true );
$form = digi_browser_form( $html, array( "usage_prices[$i][input]" => '1' ), Digitalisimo_AI_Usage_Admin::SAVE, 'field:usage[' );
$_POST = $form['post']; ob_start(); Digitalisimo_AI_Usage_Admin::page(); ob_end_clean(); $_POST = array();
check( 1.0 === Digitalisimo_AI_Usage_Prices::find( 'claude-haiku-5-5' )['input'] && 'edited' === Digitalisimo_AI_Usage_Prices::find( 'claude-haiku-5-5' )['origin'] && near( (float) rows( "model = 'claude-haiku-5-5'" )[0]['cost_usd'], $old ), 'A: corregir un precio no reescribe la historia' );
// Retención: el detalle viejo se borra y el resumen diario queda.
global $wpdb;
$wpdb->query( "UPDATE {$wpdb->prefix}digitalisimo_ai_usage SET day = '2026-01-02' WHERE feature = 'chatbot' AND day = '2026-11-20'" );
$wpdb->query( "UPDATE {$wpdb->prefix}digitalisimo_ai_usage_daily SET day = '2026-01-02' WHERE feature = 'chatbot' AND day = '2026-11-20'" );
$removed = Digitalisimo_AI_Usage::purge();
check( $removed > 0 && ! rows( "day = '2026-01-02'" ) && Digitalisimo_AI_Usage_Store::totals( '2026-01-02', '2026-01-02' )['calls'] > 0, 'A: la retención borra el detalle viejo y conserva el resumen diario' );
check( 90 === Digitalisimo_AI_Usage::sanitize_settings( array( 'retention_days' => 'x' ), Digitalisimo_AI_Usage::defaults() )['retention_days'] && 7 === Digitalisimo_AI_Usage::sanitize_settings( array( 'retention_days' => 1 ), Digitalisimo_AI_Usage::defaults() )['retention_days'], 'A: retención de 90 días por omisión y mínimo de 7' );

/* ================= B · presupuesto ================= */
$GLOBALS['today'] = '2026-12-03'; $GLOBALS['mail'] = array(); $GLOBALS['cron'] = array();
check( false === Digitalisimo_AI_Usage::budget()['enabled'], 'B: el presupuesto viene apagado' );
$q = $wpdb->queries; Digitalisimo_AI_Usage::gate( false, array( 'headers' => array( 'x-api-key' => KEY_ANTHROPIC ) ), 'https://api.anthropic.com/v1/messages' );
check( $q === $wpdb->queries, 'B: sin presupuesto, el candado no consulta la base' );
update_option( Digitalisimo_AI_Usage::SETTINGS, array( 'mxn_rate' => 18.5, 'budget_enabled' => 1, 'budget_usd' => 0.01, 'budget_action' => 'notify', 'budget_email' => 'dueno@ejemplo.test' ) );
reply( 'api.anthropic.com', 200, anthropic_reply( 4000, 0, 0, 0, 'claude-sonnet-5-5' ) ); // Sonnet 5.5 a $2: 0.008 USD = 80 %.
Digitalisimo_AI::complete( 'content', 'x' );
$b = Digitalisimo_AI_Usage::budget();
check( 80 === $b['level'] && near( $b['spent'], 0.008 ) && ! empty( $GLOBALS['cron'][1][ Digitalisimo_AI_Usage::CRON_ALERT ] ) && ! $GLOBALS['mail'], 'B: al 80 % se programa el correo (no se manda dentro de la petición)' );
Digitalisimo_AI_Usage::send_alerts();
check( 1 === count( $GLOBALS['mail'] ) && 'dueno@ejemplo.test' === $GLOBALS['mail'][0][0] && false !== strpos( $GLOBALS['mail'][0][1], '80' ), 'B: el correo del 80 % llega al administrador' );
Digitalisimo_AI_Usage::send_alerts();
check( 1 === count( $GLOBALS['mail'] ), 'B: el aviso del 80 % se manda una sola vez al mes' );
ob_start(); Digitalisimo_AI_Usage_Admin::notice(); $n = ob_get_clean();
check( false !== strpos( $n, 'notice-warning' ) && false !== strpos( $n, 'Ocultar este aviso' ), 'B: aviso del 80 % en el escritorio, descartable' );
Digitalisimo_AI_Usage_Admin::dismiss_notice( 80 );
ob_start(); Digitalisimo_AI_Usage_Admin::notice(); check( '' === ob_get_clean(), 'B: el aviso descartado no vuelve en el mes' );
reply( 'api.anthropic.com', 200, anthropic_reply( 2000, 0, 0, 0, 'claude-sonnet-5-5' ) ); // 0.012 USD > 100 %.
$r = Digitalisimo_AI::complete( 'content', 'x' );
check( is_string( $r ) && 100 === Digitalisimo_AI_Usage::budget()['level'] && false === Digitalisimo_AI_Usage::paused(), 'B: al 100 % con «sólo avisar» las funciones siguen' );
Digitalisimo_AI_Usage::send_alerts();
check( 2 === count( $GLOBALS['mail'] ) && false !== strpos( $GLOBALS['mail'][1][1], '100' ), 'B: llega el correo del 100 %' );
ob_start(); Digitalisimo_AI_Usage_Admin::notice(); check( false !== strpos( ob_get_clean(), 'notice-error' ), 'B: el aviso del 100 % aparece aunque se ocultara el del 80 %' );
update_option( Digitalisimo_AI_Usage::SETTINGS, array_merge( get_option( Digitalisimo_AI_Usage::SETTINGS ), array( 'budget_action' => 'pause' ) ) );
$calls = count( $GLOBALS['http_log'] ); $before = count( rows() );
$r = Digitalisimo_AI::complete( 'content', 'x' );
check( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'en pausa' ) && false !== strpos( $r->get_error_message(), 'US$ 0.01' ), 'B: en pausa, complete() da un mensaje claro' );
check( count( $GLOBALS['http_log'] ) === $calls && count( rows() ) === $before, 'B: en pausa no sale ninguna llamada al proveedor ni se registra costo' );
$r = Digitalisimo_AI_Assistant_Model::chat( $target, 'SYS', array( array( 'role' => 'user', 'text' => 'hola' ) ), array() );
check( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'en pausa' ) && count( $GLOBALS['http_log'] ) === $calls, 'B: en pausa, el Asistente IA lo dice claro y no llama' );
$GLOBALS['user'] = 0; $r = Digitalisimo_AI::chat( new WP_REST_Request( array( 'message' => 'hola' ) ) ); $GLOBALS['user'] = 1;
check( is_wp_error( $r ) && 502 === $r->data['status'] && count( $GLOBALS['http_log'] ) === $calls, 'B: en pausa, el chatbot público no llama al proveedor' );
reply( 'api.openai.com', 200, '{}' );
wp_remote_post( 'https://api.openai.com/v1/chat/completions', array( 'headers' => array( 'Authorization' => 'Bearer ' . KEY_AJENA ) ) );
check( count( $GLOBALS['http_log'] ) === $calls + 1, 'B: la pausa no toca peticiones que no son de IA Tools' );
$GLOBALS['today'] = '2027-01-01';
reply( 'api.anthropic.com', 200, anthropic_reply( 10, 10 ) );
check( is_string( Digitalisimo_AI::complete( 'chatbot', 'x' ) ), 'B: el mes nuevo reanuda las funciones' );
update_option( Digitalisimo_AI_Usage::SETTINGS, array( 'mxn_rate' => 18.5 ) );

/* ================= I · aislamiento entre sitios de una red + paridad ================= */
$GLOBALS['multisite'] = true; $GLOBALS['today'] = '2027-01-05';
switch_blog( 2 ); ia_options();
reply( 'api.anthropic.com', 200, anthropic_reply( 1000000, 0, 0, 0, 'claude-sonnet-5-5' ) ); // 2 USD.
Digitalisimo_AI::complete( 'content', 'x' );
$site2 = Digitalisimo_AI_Usage::ability_execute( array( 'from' => '2027-01-05', 'to' => '2027-01-05' ) );
check( 1 === $site2['periods']['range']['calls'] && near( $site2['periods']['range']['cost_usd'], 2.0 ) && 2 === $site2['blog_id'], 'I: el sitio 2 sólo ve su llamada' );
check( 'wp_2_digitalisimo_ai_usage' === Digitalisimo_AI_Usage_Store::table() && 2 === (int) rows()[0]['blog_id'], 'I: el sitio 2 escribe en sus propias tablas' );
switch_blog( 1 );
reply( 'api.anthropic.com', 200, anthropic_reply( 500000, 0, 0, 0, 'claude-sonnet-5-5' ) ); // 1 USD.
Digitalisimo_AI::complete( 'content', 'x' );
$site1 = Digitalisimo_AI_Usage::ability_execute( array( 'from' => '2027-01-05', 'to' => '2027-01-05' ) );
check( 1 === $site1['periods']['range']['calls'] && near( $site1['periods']['range']['cost_usd'], 1.0 ), 'I: el sitio 1 no ve la llamada del sitio 2' );
$net = Digitalisimo_AI_Usage_Store::network_sites( array( 1, 2 ), Digitalisimo_AI_Usage::days() + array( 'from' => '2027-01-05', 'to' => '2027-01-05' ) );
check( near( $net[1]['range']['cost_usd'], 1.0 ) && near( $net[2]['range']['cost_usd'], 2.0 ), 'I: la red suma cada sitio por separado' );
$GLOBALS['network_admin'] = true; $_GET = array( 'from' => '2027-01-05', 'to' => '2027-01-05' );
ob_start(); Digitalisimo_AI_Usage_Admin::network_page(); $nhtml = ob_get_clean();
check( false !== strpos( $nhtml, 'US$ 3.00' ) && false !== strpos( $nhtml, 'Sitio 2' ) && false !== strpos( $nhtml, 'Toda la red por modelo' ), 'I: la red muestra el total de cada sitio y de toda la red' );
$csv = Digitalisimo_AI_Usage_Admin::csv_rows( 'network', 'sites', '2027-01-05', '2027-01-05' );
check( 3 === count( $csv ) && near( (float) $csv[1][6], 1.0 ) && near( (float) $csv[2][6], 2.0 ), 'I: CSV por sitio de la red' );
// Permisos de red: el administrador de un sitio no ve la red ni descarga su CSV.
$GLOBALS['user'] = 3; ob_start(); Digitalisimo_AI_Usage_Admin::network_page(); check( '' === ob_get_clean(), 'P: la pantalla de red es sólo del superadministrador' );
$_GET = $_REQUEST = array( 'scope' => 'network', 'type' => 'sites', '_wpnonce' => 'n:' . Digitalisimo_AI_Usage_Admin::CSV ); $died = false; try { Digitalisimo_AI_Usage_Admin::csv(); } catch ( Died $e ) { $died = true; }
check( $died, 'P: el CSV de la red es sólo del superadministrador' );
// Paridad: los mismos campos en la red y en el sitio (más «Heredar» sólo en el sitio).
$GLOBALS['user'] = 1; $_GET = array();
$names = function ( $html ) { preg_match_all( '/name="(usage\[[a-z_]+\])"/', $html, $m ); return array_values( array_unique( $m[1] ) ); };
$GLOBALS['network_admin'] = true; ob_start(); Digitalisimo_AI_Usage_Admin::network_page(); $nhtml = ob_get_clean();
$GLOBALS['network_admin'] = false; switch_blog( 2 ); ob_start(); Digitalisimo_AI_Usage_Admin::page(); $shtml = ob_get_clean();
check( array_values( array_diff( $names( $shtml ), array( 'usage[inherit_network]' ) ) ) === $names( $nhtml ) && in_array( 'usage[inherit_network]', $names( $shtml ), true ) && ! in_array( 'usage[inherit_network]', $names( $nhtml ), true ), 'A: paridad sitio/red de los campos; «Heredar» sólo en el sitio' );
// La red define el tope que heredan los sitios.
$GLOBALS['network_admin'] = true;
$form = digi_browser_form( $nhtml, array( 'usage[budget_enabled]' => '1', 'usage[budget_usd]' => '50', 'usage[budget_action]' => 'pause', 'usage[mxn_rate]' => '17.9' ), Digitalisimo_AI_Usage_Admin::SAVE, 'field:usage[' );
$_POST = $form['post']; ob_start(); Digitalisimo_AI_Usage_Admin::network_page(); ob_end_clean(); $_POST = array(); $GLOBALS['network_admin'] = false;
check( 50.0 === (float) get_site_option( Digitalisimo_AI_Usage::SETTINGS )['budget_usd'] && 'pause' === get_site_option( Digitalisimo_AI_Usage::SETTINGS )['budget_action'], 'A: la red guarda su tope' );
check( Digitalisimo_AI_Usage::inherits() && 50.0 === (float) Digitalisimo_AI_Usage::settings( 'budget_usd' ) && 17.9 === Digitalisimo_AI_Usage::mxn_rate(), 'A: un sitio nuevo hereda el tope y el tipo de cambio de la red' );
// El administrador del sitio (no súper) no puede cambiarlo.
$GLOBALS['user'] = 3; ob_start(); Digitalisimo_AI_Usage_Admin::page(); $shtml = ob_get_clean();
check( false !== strpos( $shtml, 'los define el superadministrador' ) && false === strpos( $shtml, 'name="' . Digitalisimo_AI_Usage_Admin::SAVE . '"' ), 'P: el administrador del sitio ve el tope sin poder cambiarlo' );
$_POST = array( Digitalisimo_AI_Usage_Admin::SAVE => '1', '_wpnonce' => 'n:' . Digitalisimo_AI_Usage_Admin::SAVE, 'usage' => array( 'inherit_network' => '0', 'budget_enabled' => '0' ) );
ob_start(); Digitalisimo_AI_Usage_Admin::page(); ob_end_clean(); $_POST = array();
check( Digitalisimo_AI_Usage::inherits() && 50.0 === (float) Digitalisimo_AI_Usage::settings( 'budget_usd' ), 'P: un envío del administrador del sitio no quita el tope de la red' );
// El superadministrador personaliza el sitio 2: deja de heredar, con lo que se veía como base.
$GLOBALS['user'] = 1; ob_start(); Digitalisimo_AI_Usage_Admin::page(); $shtml = ob_get_clean();
$form = digi_browser_form( $shtml, array( 'usage[inherit_network]' => '' ), Digitalisimo_AI_Usage_Admin::SAVE, 'field:usage[' );
$_POST = $form['post']; ob_start(); Digitalisimo_AI_Usage_Admin::page(); $shtml = ob_get_clean(); $_POST = array();
check( ! Digitalisimo_AI_Usage::inherits() && 50.0 === (float) Digitalisimo_AI_Usage::settings( 'budget_usd' ), 'A: al dejar de heredar se conserva lo que se veía' );
$form = digi_browser_form( $shtml, array( 'usage[budget_usd]' => '1.5' ), Digitalisimo_AI_Usage_Admin::SAVE, 'field:usage[' );
$_POST = $form['post']; ob_start(); Digitalisimo_AI_Usage_Admin::page(); ob_end_clean(); $_POST = array();
check( 1.5 === (float) Digitalisimo_AI_Usage::settings( 'budget_usd' ) && Digitalisimo_AI_Usage::paused(), 'A: el tope propio del sitio 2 (ya gastó 2 USD) lo pone en pausa' );
switch_blog( 1 );
check( ! Digitalisimo_AI_Usage::paused() && 50.0 === (float) Digitalisimo_AI_Usage::settings( 'budget_usd' ), 'I: la pausa del sitio 2 no afecta al sitio 1' );
reply( 'api.anthropic.com', 200, anthropic_reply( 10, 10 ) );
check( is_string( Digitalisimo_AI::complete( 'chatbot', 'x' ) ), 'I: el sitio 1 sigue usando la IA' );

echo "OK ia-usage: $checks comprobaciones\n";
