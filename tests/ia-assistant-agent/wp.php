<?php
/** WordPress mínimo para tests/ia-assistant-agent.php (el mismo contrato que tests/ia-assistant.php, más lo que leen las Abilities de lectura del sitio). */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DIGITALISIMO_CHATBOT_URL', 'https://sitio.test/wp-content/plugins/digitalisimo.chatbot/' );
define( 'DIGITALISIMO_CHATBOT_VERSION', '9.9.9' );
$checks = 0;
// DIGI_SEGUIR=1 (sólo a mano, para ver todo lo que falla con otro código): anota la falla y sigue.
function check( $condition, $message ) { global $checks; if ( ! $condition ) { echo "FALLA: $message\n"; if ( ! getenv( 'DIGI_SEGUIR' ) ) exit( 1 ); $GLOBALS['fallas'] = ( $GLOBALS['fallas'] ?? 0 ) + 1; return; } $checks++; }

const KEY_OPENAI = 'sk-test-SECRETKEY0123456789abcdef';
const KEY_ANTHROPIC = 'sk-ant-test-OTRACLAVE0123456789abcdef';

/* ---------------- WordPress mínimo ---------------- */
class WP_Error {
	public $code; public $message; public $data;
	public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
class WP_REST_Response { public $data; public function __construct( $data ) { $this->data = $data; } public function get_data() { return $this->data; } }
class WP_REST_Request implements ArrayAccess {
	private $params; private $headers;
	public function __construct( array $params = array(), array $headers = array() ) { $this->params = $params; foreach ( $headers as $k => $v ) $this->headers[ strtolower( str_replace( '-', '_', $k ) ) ] = $v; }
	public function get_param( $key ) { return $this->params[ $key ] ?? null; }
	public function get_header( $key ) { return $this->headers[ strtolower( str_replace( '-', '_', $key ) ) ] ?? null; }
	#[\ReturnTypeWillChange]
	public function offsetExists( $key ) { return isset( $this->params[ $key ] ); }
	#[\ReturnTypeWillChange]
	public function offsetGet( $key ) { return $this->params[ $key ] ?? null; }
	#[\ReturnTypeWillChange]
	public function offsetSet( $key, $value ) { $this->params[ $key ] = $value; }
	#[\ReturnTypeWillChange]
	public function offsetUnset( $key ) { unset( $this->params[ $key ] ); }
}
#[\AllowDynamicProperties]
class WP_Post { public function __construct( array $d ) { foreach ( $d as $k => $v ) $this->$k = $v; } }
class WP_Ability {
	public $name; public $args;
	public function __construct( $name, $args ) { $this->name = $name; $this->args = $args; }
	public function get_name() { return $this->name; }
	public function get_label() { return $this->args['label'] ?? ''; }
	public function get_description() { return $this->args['description'] ?? ''; }
	public function get_category() { return $this->args['category'] ?? ''; }
	public function get_input_schema() { return $this->args['input_schema'] ?? array(); }
	public function get_meta() { return $this->args['meta'] ?? array(); }
	public function check_permissions( $input = null ) { return call_user_func( $this->args['permission_callback'], $input ); }
	public function execute( $input = null ) {
		if ( true !== $this->check_permissions( $input ) ) return new WP_Error( 'ability_invalid_permissions', 'Sin permiso.' );
		$GLOBALS['executed'][] = array( $this->name, $input );
		return call_user_func( $this->args['execute_callback'], $input );
	}
}
$GLOBALS['hooks'] = array(); $GLOBALS['options'] = array(); $GLOBALS['posts'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['abilities'] = array();
$GLOBALS['executed'] = array(); $GLOBALS['clock'] = 0; $GLOBALS['next_id'] = 100; $GLOBALS['user'] = 1; $GLOBALS['enqueued'] = array(); $GLOBALS['menus'] = array(); $GLOBALS['routes'] = array();
$GLOBALS['users'] = array( 1 => array( 'name' => 'Ana Admin', 'caps' => array( 'edit_posts', 'manage_options', 'read' ) ), 2 => array( 'name' => 'Eli Editora', 'caps' => array( 'edit_posts', 'read' ) ), 3 => array( 'name' => 'Sus Suscrita', 'caps' => array( 'read' ) ) );
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function remove_all_filters( $hook ) { unset( $GLOBALS['hooks'][ $hook ] ); }
function apply_filters( $hook, $value, ...$args ) { foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $cb ) $value = call_user_func_array( $cb, array_merge( array( $value ), $args ) ); return $value; }
function do_action( $hook, ...$args ) { foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $cb ) call_user_func_array( $cb, $args ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function is_multisite() { return false; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['options'] ) ? $GLOBALS['options'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['options'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function get_site_option( $k, $d = false ) { return $d; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $t ) ) ); }
function sanitize_textarea_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function wp_strip_all_tags( $t ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $t ) ) ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_slash( $v ) { return is_string( $v ) ? addslashes( $v ) : $v; }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function wp_rand() { return mt_rand(); }
function wp_generate_password( $n = 12 ) { return substr( bin2hex( random_bytes( $n ) ), 0, $n ); }
// Copia de rest_sanitize_boolean() del núcleo: toda cadena que no sea 'false' ni '0' vale true (también 'no' u 'off').
function rest_sanitize_boolean( $value ) { if ( is_string( $value ) ) { $value = strtolower( $value ); if ( in_array( $value, array( 'false', '0' ), true ) ) $value = false; } return (bool) $value; }
function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function is_admin() { return ! empty( $GLOBALS['is_admin'] ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function rest_authorization_required_code() { return is_user_logged_in() ? 403 : 401; }
function rest_ensure_response( $d ) { return $d instanceof WP_REST_Response ? $d : new WP_REST_Response( $d ); }
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $ns . $route ] = $args; }
function register_post_type( $type, $args ) { $GLOBALS['post_types'][ $type ] = $args; }
function current_time( $type, $gmt = false ) { return gmdate( 'Y-m-d H:i:s', time() + ( $GLOBALS['clock']++ ) ); }
function is_user_logged_in() { return $GLOBALS['user'] > 0; }
function get_current_user_id() { return $GLOBALS['user']; }
function current_user_can( $cap, ...$args ) { $map = array( 'edit_post' => 'edit_posts', 'read_post' => 'read', 'delete_post' => 'edit_posts' ); $cap = $map[ $cap ] ?? $cap; return $GLOBALS['user'] > 0 && in_array( $cap, $GLOBALS['users'][ $GLOBALS['user'] ]['caps'], true ); }
function wp_get_current_user() { return (object) array( 'ID' => $GLOBALS['user'], 'display_name' => $GLOBALS['users'][ $GLOBALS['user'] ]['name'] ?? '' ); }
function get_userdata( $id ) { return isset( $GLOBALS['users'][ $id ] ) ? (object) array( 'ID' => $id, 'display_name' => $GLOBALS['users'][ $id ]['name'] ) : false; }
function wp_create_nonce( $a ) { return 'n' . md5( $a . '|' . $GLOBALS['user'] ); }
function wp_verify_nonce( $n, $a ) { return hash_equals( 'n' . md5( $a . '|' . $GLOBALS['user'] ), (string) $n ) ? 1 : false; }
function admin_url( $p = '' ) { return 'https://sitio.test/wp-admin/' . $p; }
function rest_url( $p = '' ) { return 'https://sitio.test/wp-json/' . $p; }
function home_url( $p = '' ) { return 'https://sitio.test' . $p; }
function esc_url_raw( $u ) { return $u; }
function add_query_arg( $args, $url ) { $q = array(); foreach ( $args as $k => $v ) $q[] = $k . '=' . $v; return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $q ); }
function get_bloginfo( $k ) { return $GLOBALS['blogname'] ?? 'Sitio de prueba'; }
function get_site_icon_url() { return ''; }
function wp_die( $m ) { throw new RuntimeException( 'wp_die' ); }
function esc_html__( $t ) { return $t; }
function wp_enqueue_style( $h ) { $GLOBALS['enqueued'][] = $h; }
function wp_register_script( $h ) { $GLOBALS['registered'][] = $h; }
function wp_enqueue_script( $h, $src = '', $deps = array() ) { $GLOBALS['enqueued'][] = $h; $GLOBALS['deps'][ $h ] = $deps; }
function wp_add_inline_script( $h, $code ) { $GLOBALS['inline'][ $h ] = $code; }
function add_menu_page( $title, $menu, $cap, $slug, $cb, $icon, $pos ) { $GLOBALS['menus'][] = compact( 'title', 'menu', 'cap', 'slug', 'icon', 'pos' ); return 'toplevel_page_' . $slug; }
function wp_insert_post( $d, $err = false ) {
	$id = $GLOBALS['next_id']++;
	$GLOBALS['posts'][ $id ] = new WP_Post( array_merge( $d, array( 'ID' => $id, 'post_modified_gmt' => current_time( 'mysql', true ) ) ) );
	return $id;
}
function get_post( $id ) { if ( $id instanceof WP_Post ) return $id; return $GLOBALS['posts'][ (int) $id ] ?? null; }
function wp_cache_delete( $key, $group = '' ) { $GLOBALS['cache_deleted'][] = array( $key, $group ); return true; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function get_post_meta( $id, $k, $s = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = wp_unslash( $v ); return true; }
function wp_delete_post( $id, $force = false ) { $p = $GLOBALS['posts'][ $id ] ?? null; unset( $GLOBALS['posts'][ $id ], $GLOBALS['meta'][ $id ] ); return $p; }
function clean_post_cache( $id ) {}
function get_posts( $a ) {
	$types    = isset( $a['post_type'] ) ? (array) $a['post_type'] : array( 'post' );
	$statuses = isset( $a['post_status'] ) ? (array) $a['post_status'] : null;
	$out      = array_values( array_filter( $GLOBALS['posts'], function ( $p ) use ( $a, $types, $statuses ) { return ( in_array( 'any', $types, true ) ? 'digitalisimo_ai_chat' !== $p->post_type : in_array( $p->post_type, $types, true ) ) && ( ! isset( $a['author'] ) || (int) $p->post_author === (int) $a['author'] ) && ( null === $statuses || in_array( 'any', $statuses, true ) || in_array( $p->post_status, $statuses, true ) ) && ( ! isset( $a['name'] ) || ( $p->post_name ?? '' ) === $a['name'] ); } ) );
	usort( $out, function ( $x, $y ) { return strcmp( $y->post_modified_gmt, $x->post_modified_gmt ) ?: $y->ID - $x->ID; } );
	if ( ( $a['posts_per_page'] ?? -1 ) > 0 ) $out = array_slice( $out, 0, $a['posts_per_page'] );
	return 'ids' === ( $a['fields'] ?? '' ) ? array_map( function ( $p ) { return $p->ID; }, $out ) : $out;
}
function get_the_title( $p ) { $p = get_post( $p ); return $p ? (string) ( $p->post_title ?? '' ) : ''; }
function get_permalink( $p ) { $p = get_post( $p ); return $p ? 'https://sitio.test/' . ( $p->post_name ?? ( '?p=' . $p->ID ) ) . '/' : ''; }
function url_to_postid( $url ) { foreach ( $GLOBALS['posts'] as $p ) if ( isset( $p->post_name ) && rtrim( $url, '/' ) === 'https://sitio.test/' . $p->post_name ) return $p->ID; return 0; }
function sanitize_title( $t ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( (string) $t ) ) ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function wp_date( $f, $ts = null ) { return gmdate( $f, null === $ts ? time() : $ts ); }
function wp_timezone_string() { return 'America/Mexico_City'; }
function remove_accents( $t ) { return strtr( (string) $t, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u' ) ); }
function get_post_type_object( $t ) { return in_array( $t, array( 'post', 'page', 'product', 'attachment', 'elementor_library', 'shop_coupon', 'feedback', 'flamingo_inbound' ), true ) ? (object) array( 'name' => $t ) : null; }
function is_post_type_viewable( $o ) { return is_object( $o ) && in_array( $o->name, array( 'post', 'page', 'product', 'attachment' ), true ); }
function wp_has_ability( $n ) { return isset( $GLOBALS['abilities'][ $n ] ); }
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function checked( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' checked="checked"' : ''; }
function wp_get_nav_menus() { return $GLOBALS['menus_nav'] ?? array(); }
function wp_get_nav_menu_items( $m ) { return $GLOBALS['menu_items'][ is_object( $m ) ? $m->term_id : $m ] ?? array(); }
// La tabla de opciones sólo para los candados: INSERT IGNORE (1 fila o 0), SELECT del valor y DELETE con su valor exacto.
class Fake_DB {
	public $posts = 'wp_posts'; public $options = 'wp_options'; public $rows = array();
	public function update( $t, $d, $w ) {
		if ( 'wp_options' === $t ) { $n = $w['option_name']; if ( ! isset( $this->rows[ $n ] ) || $this->rows[ $n ] !== $w['option_value'] ) return 0; $this->rows[ $n ] = $d['option_value']; return 1; }
		foreach ( $d as $k => $v ) $GLOBALS['posts'][ $w['ID'] ]->$k = $v; return 1;
	}
	public function prepare( $query, ...$args ) { return array( $query, $args ); }
	public function query( $q ) { if ( is_array( $q ) && 0 === strpos( $q[0], 'INSERT IGNORE INTO wp_options' ) ) { if ( isset( $this->rows[ $q[1][0] ] ) ) return 0; $this->rows[ $q[1][0] ] = $q[1][1]; return 1; } return false; }
	public function get_var( $q ) { return $this->rows[ $q[1][0] ] ?? null; }
	public function delete( $t, $w ) { $n = $w['option_name']; if ( 'wp_options' === $t && isset( $this->rows[ $n ] ) && $this->rows[ $n ] === $w['option_value'] ) { unset( $this->rows[ $n ] ); return 1; } return 0; }
}
$GLOBALS['wpdb'] = new Fake_DB();
function wp_register_ability( $n, $a ) { $GLOBALS['abilities'][ $n ] = new WP_Ability( $n, $a ); return $GLOBALS['abilities'][ $n ]; }
function wp_register_ability_category( $n, $a ) { $GLOBALS['ability_categories'][ $n ] = $a; }
function wp_get_abilities() { return array_values( $GLOBALS['abilities'] ); }
function wp_get_ability( $n ) { return $GLOBALS['abilities'][ $n ] ?? null; }
$GLOBALS['http'] = array();
function wp_remote_post( $url, $args ) { $GLOBALS['http'][] = array( 'url' => $url, 'args' => $args ); return ! empty( $GLOBALS['http_replies'] ) ? array_shift( $GLOBALS['http_replies'] ) : $GLOBALS['http_reply']; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
