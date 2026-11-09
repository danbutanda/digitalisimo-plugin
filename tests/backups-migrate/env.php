<?php
/**
 * Dos instalaciones de WordPress en un solo proceso, para probar la migración entre servidores y dominios con el motor
 * real (trabajos, ZIP, manifiesto, firma, DGBK, guardián SQL, restauración con copia previa y reubicación de E1):
 *
 * - «origen» (https://origen.test, prefijo `wp_`) y «destino» (https://destino.test, prefijo `dst_`), cada una con su
 *   carpeta real, sus opciones, su base de datos simulada (wp_options y posts, cuyas filas salen de las opciones y del
 *   contenido simulados, así el SQL exportado contiene de verdad lo que el sitio guarda) y su almacén privado;
 * - ABSPATH es un enlace simbólico que mig_use() apunta a una u otra (como un despliegue `current → releases/N`), así que
 *   realpath(ABSPATH), WP_CONTENT_DIR y el almacén cambian con el sitio;
 * - el transporte HTTP del destino (Digitalisimo_Backups_Migrate::$transport) despacha la petición a las rutas REST
 *   registradas, como WP_REST_Server, en el sitio del host de la URL, con cabeceras, cuerpo y cadena de consulta, y
 *   permite inyectar fallos de red, cortes y alteraciones en el camino.
 *
 * Sin libsodium nativo usa el sodium_compat de tests/lib. Cada prueba incluye este archivo una sola vez.
 */
if ( ! extension_loaded( 'sodium' ) || getenv( 'DIGI_FORCE_SODIUM_COMPAT' ) ) require_once __DIR__ . '/../lib/sodium_compat/autoload.php';

$GLOBALS['mig_root'] = sys_get_temp_dir() . '/digitalisimo-migrate-' . bin2hex( random_bytes( 4 ) );
mkdir( $GLOBALS['mig_root'], 0700, true );
$GLOBALS['mig_root'] = realpath( $GLOBALS['mig_root'] );
define( 'ABSPATH', $GLOBALS['mig_root'] . '/site/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
define( 'DB_NAME', 'wordpress_test' );
define( 'DIGITALISIMO_BACKUPS_VERSION', '9.9.9-migrate' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'AUTH_KEY', 'clave-de-prueba-auth-0123456789abcdef0123456789abcdef' );
define( 'SECURE_AUTH_KEY', 'clave-de-prueba-secure-0123456789abcdef0123456789abcd' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

/** Base de datos simulada de un sitio: `<prefijo>options` sale de las opciones del sitio en curso y `<prefijo>posts` es suya. */
class Mig_DB {
	public $base_prefix;
	public $prefix;
	public $last_error = '';
	public $queries = array();
	public $posts = array();
	/** Simula que el proceso muere en esta sentencia (exit no ejecuta ningún finally). */
	public $fail_on = '';
	public function __construct( $prefix, array $posts ) { $this->base_prefix = $prefix; $this->prefix = $prefix; $this->posts = $posts; }
	public function tables() { return array( $this->base_prefix . 'options', $this->base_prefix . 'posts' ); }
	public function get_col( $query ) {
		if ( 0 === strpos( $query, 'SHOW TABLES' ) ) return $this->tables();
		return array();
	}
	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) $query = preg_replace( '/%[sd]/', is_int( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'", $query, 1 );
		return $query;
	}
	private function table_of( $query ) { preg_match( '/`([^`]+)`/', $query, $m ); return $m[1] ?? ''; }
	public function get_row( $query, $format = null ) {
		$this->last_error = '';
		if ( false !== strpos( $query, 'SHOW TABLE STATUS' ) ) return array( 'Engine' => 'InnoDB' );
		$table = $this->table_of( $query );
		if ( $this->base_prefix . 'options' === $table ) return array( $table, "CREATE TABLE `" . $table . "` (\n  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `option_name` varchar(191) NOT NULL DEFAULT '',\n  `option_value` longtext NOT NULL,\n  PRIMARY KEY (`option_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
		return array( $table, "CREATE TABLE `" . $table . "` (\n  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `post_content` longtext,\n  `guid` varchar(255) NOT NULL DEFAULT '',\n  PRIMARY KEY (`ID`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
	}
	public function option_rows() {
		$rows = array();
		$id = 0;
		foreach ( $GLOBALS['bk_options'] as $name => $value ) $rows[] = array( 'option_id' => (string) ++$id, 'option_name' => (string) $name, 'option_value' => is_scalar( $value ) ? (string) $value : serialize( $value ) );
		return $rows;
	}
	public function get_results( $query, $format = null ) {
		$this->last_error = '';
		if ( false !== strpos( $query, 'SHOW INDEX' ) ) return array( array( 'Key_name' => 'PRIMARY', 'Column_name' => false !== strpos( $query, 'options' ) ? 'option_id' : 'ID', 'Non_unique' => 0, 'Seq_in_index' => 1 ) );
		if ( false !== strpos( $query, 'SHOW COLUMNS' ) ) {
			if ( false !== strpos( $query, 'options' ) ) return array( array( 'Field' => 'option_id', 'Type' => 'bigint(20)', 'Key' => 'PRI' ), array( 'Field' => 'option_name', 'Type' => 'varchar(191)', 'Key' => '' ), array( 'Field' => 'option_value', 'Type' => 'longtext', 'Key' => '' ) );
			return array( array( 'Field' => 'ID', 'Type' => 'bigint(20)', 'Key' => 'PRI' ), array( 'Field' => 'post_content', 'Type' => 'longtext', 'Key' => '' ), array( 'Field' => 'guid', 'Type' => 'varchar(255)', 'Key' => '' ) );
		}
		if ( preg_match( '/LIMIT [1-9]\d*, /', $query ) ) return array();
		$table = $this->table_of( $query );
		if ( $this->base_prefix . 'options' === $table ) return $this->option_rows();
		if ( $this->base_prefix . 'posts' === $table ) return array_values( $this->posts );
		return array();
	}
	public function get_var( $query ) { return 0; }
	public function db_version() { return '8.0'; }
	public function suppress_errors( $suppress = true ) { return false; }
	public function get_blog_prefix( $blog_id = null ) { return $this->base_prefix; }
	public function query( $sql ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		if ( '' !== $this->fail_on && false !== strpos( $sql, $this->fail_on ) ) { $this->last_error = 'Fallo simulado'; return false; }
		$options = $this->base_prefix . 'options';
		$posts = $this->base_prefix . 'posts';
		if ( 0 === strpos( $sql, 'DROP TABLE IF EXISTS `' . $options . '`' ) ) { $GLOBALS['bk_options'] = array(); return 1; }
		if ( 0 === strpos( $sql, 'DROP TABLE IF EXISTS `' . $posts . '`' ) ) { $this->posts = array(); return 1; }
		if ( preg_match( '/^INSERT INTO `([^`]+)` \(([^)]*)\) VALUES \((.*)\);?\s*$/s', $sql, $m ) ) {
			if ( ! in_array( $m[1], array( $options, $posts ), true ) ) { $this->last_error = 'Tabla desconocida ' . $m[1]; return false; }
			$columns = array_map( function( $c ) { return trim( $c, '` ' ); }, explode( ',', $m[2] ) );
			preg_match_all( "/'((?:[^'\\\\]|\\\\.)*)'|NULL/s", $m[3], $values );
			$row = array();
			foreach ( $columns as $i => $column ) $row[ $column ] = 'NULL' === ( $values[0][ $i ] ?? 'NULL' ) ? null : mig_sql_unescape( $values[1][ $i ] );
			if ( $options === $m[1] ) {
				$value = (string) $row['option_value'];
				$unserialized = @unserialize( $value );
				$GLOBALS['bk_options'][ (string) $row['option_name'] ] = false === $unserialized && 'b:0;' !== $value ? $value : $unserialized;
			} else {
				$this->posts[ (int) $row['ID'] ] = $row;
			}
			return 1;
		}
		if ( preg_match( "/^UPDATE `" . preg_quote( $options, '/' ) . "` SET option_value = '((?:[^'\\\\]|\\\\.)*)' WHERE option_name = '((?:[^'\\\\]|\\\\.)*)'$/s", $sql, $m ) ) {
			$GLOBALS['bk_options'][ stripslashes( $m[2] ) ] = stripslashes( $m[1] );
			return 1;
		}
		return 0 === strpos( $sql, 'DELETE' ) ? 0 : 1;
	}
}
function mig_sql_unescape( $value ) { return strtr( $value, array( '\\\\' => '\\', '\\0' => "\0", '\\n' => "\n", '\\r' => "\r", '\\Z' => "\x1a", "\\'" => "'", '\\"' => '"' ) ); }

/** Los dos sitios: carpeta real, opciones, red y base de datos. */
$GLOBALS['mig'] = array( 'current' => '', 'sites' => array(), 'requests' => array(), 'faults' => array() );
function mig_site( $name, $host, $prefix, array $extra_files = array() ) {
	$real = $GLOBALS['mig_root'] . '/' . $name;
	foreach ( array( 'wp-admin', 'wp-includes', 'wp-content/plugins', 'wp-content/themes/tema', 'wp-content/uploads/2026/11' ) as $dir ) @mkdir( $real . '/' . $dir, 0700, true );
	$files = array(
		'wp-config.php' => "<?php\ndefine( 'DB_NAME', 'wordpress_test' );\ndefine( 'AUTH_KEY', '" . AUTH_KEY . "' );\ndefine( 'SECURE_AUTH_KEY', '" . SECURE_AUTH_KEY . "' );\n\$table_prefix = '" . $prefix . "';\n",
		'wp-load.php' => "<?php // load\n", 'wp-settings.php' => "<?php // settings\n", 'index.php' => "<?php // index\n",
		'wp-admin/index.php' => "<?php // admin\n", 'wp-includes/version.php' => "<?php \$wp_version = '6.8';\n",
		'wp-content/plugins/example.php' => "<?php /* Plugin Name: Ejemplo */\n",
		'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
	) + $extra_files;
	foreach ( $files as $file => $content ) {
		if ( ! is_dir( dirname( $real . '/' . $file ) ) ) mkdir( dirname( $real . '/' . $file ), 0700, true );
		file_put_contents( $real . '/' . $file, $content );
	}
	$GLOBALS['mig']['sites'][ $name ] = array( 'real' => realpath( $real ), 'host' => $host, 'options' => array( 'home' => 'https://' . $host, 'siteurl' => 'https://' . $host, 'blogname' => ucfirst( $name ) ), 'site_options' => array(), 'multisite' => false, 'subdomain' => false, 'db' => new Mig_DB( $prefix, array() ), 'caps' => array( 'manage_options' => true, 'manage_network_options' => true ) );
}
/** Cambia de sitio: guarda el estado del actual y apunta ABSPATH, la base de datos, las opciones y el almacén al otro. */
function mig_use( $name ) {
	$mig =& $GLOBALS['mig'];
	if ( $mig['current'] === $name ) return;
	if ( '' !== $mig['current'] ) {
		$mig['sites'][ $mig['current'] ]['options'] = $GLOBALS['bk_options'];
		$mig['sites'][ $mig['current'] ]['site_options'] = $GLOBALS['bk_site_options'];
	}
	$site = $mig['sites'][ $name ];
	$GLOBALS['bk_options'] = $site['options'];
	$GLOBALS['bk_site_options'] = $site['site_options'];
	$GLOBALS['wpdb'] = $site['db'];
	$GLOBALS['bk_multisite'] = $site['multisite'];
	$GLOBALS['bk_caps'] = $site['caps'];
	$link = rtrim( ABSPATH, '/' );
	if ( is_link( $link ) ) unlink( $link );
	symlink( $site['real'], $link );
	clearstatcache( true );
	$_SERVER['DOCUMENT_ROOT'] = $site['real'];
	$mig['current'] = $name;
	if ( class_exists( 'Digitalisimo_Backups', false ) && method_exists( 'Digitalisimo_Backups', 'forget_storage_directory' ) ) Digitalisimo_Backups::forget_storage_directory();
}
function mig_current() { return $GLOBALS['mig']['current']; }
/** Ejecuta $callback en el sitio $name y vuelve al de antes. */
function mig_in( $name, callable $callback ) {
	$before = mig_current();
	mig_use( $name );
	try { return $callback(); } finally { mig_use( $before ); }
}

$GLOBALS['bk_options'] = array();
$GLOBALS['bk_site_options'] = array();
$GLOBALS['bk_multisite'] = false;
$GLOBALS['bk_caps'] = array();
$GLOBALS['bk_filters'] = array();
$GLOBALS['mig_routes'] = array();

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class Mig_Redirect extends Error { public $url; public function __construct( $url ) { parent::__construct( 'redirect' ); $this->url = $url; } }
class Mig_Die extends Error {}
class Mig_Exit extends Error {}

function wp_mkdir_p( $path ) { return is_dir( $path ) || @mkdir( $path, 0700, true ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', (string) $path ); }
function trailingslashit( $path ) { return rtrim( (string) $path, '/\\' ) . '/'; }
function untrailingslashit( $path ) { return rtrim( (string) $path, '/\\' ); }
function home_url( $path = '' ) { return rtrim( (string) get_option( 'home' ), '/' ) . '/' . ltrim( $path, '/' ); }
function site_url( $path = '' ) { return rtrim( (string) get_option( 'siteurl' ), '/' ) . '/' . ltrim( $path, '/' ); }
/** Sin enlaces permanentes, como el ensayo de Playground: la API vive en `?rest_route=`. */
function rest_url( $path = '' ) { return rtrim( (string) get_option( 'home' ), '/' ) . '/index.php?rest_route=/' . ltrim( $path, '/' ); }
function get_rest_url( $blog_id = null, $path = '' ) { return rest_url( $path ); }
function is_ssl() { return true; }
function get_bloginfo( $key ) { return '6.8'; }
function is_multisite() { return (bool) $GLOBALS['bk_multisite']; }
function is_main_site() { return true; }
function is_main_network() { return true; }
function is_network_admin() { return false; }
function is_subdomain_install() { return ! empty( $GLOBALS['mig']['sites'][ mig_current() ]['subdomain'] ); }
function get_main_site_id() { return 1; }
function get_current_blog_id() { return 1; }
function get_current_network_id() { return 1; }
function get_current_user_id() { return 1; }
function get_site( $id ) { return (object) array( 'blog_id' => $id, 'domain' => $GLOBALS['mig']['sites'][ mig_current() ]['host'], 'path' => '/' ); }
function get_network() { return (object) array( 'domain' => $GLOBALS['mig']['sites'][ mig_current() ]['host'], 'path' => '/' ); }
function switch_to_blog( $id ) { return true; }
function restore_current_blog() { return true; }
function wp_generate_uuid4() { $b = random_bytes( 16 ); $b[6] = chr( ord( $b[6] ) & 0x0f | 0x40 ); $b[8] = chr( ord( $b[8] ) & 0x3f | 0x80 ); return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $b ), 4 ) ); }
function wp_generate_password( $length = 12 ) { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['bk_options'] ) ? $GLOBALS['bk_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['bk_options'][ $key ] = $value; return true; }
function add_option( $key, $value, $deprecated = '', $autoload = null ) { if ( array_key_exists( $key, $GLOBALS['bk_options'] ) ) return false; $GLOBALS['bk_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['bk_options'][ $key ] ); return true; }
function get_site_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['bk_site_options'] ) ? $GLOBALS['bk_site_options'][ $key ] : $default; }
function update_site_option( $key, $value ) { $GLOBALS['bk_site_options'][ $key ] = $value; return true; }
function wp_cache_flush() { return true; }
function current_time( $format ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_basename( $path ) { return basename( (string) $path ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( (string) $value ) ), '-' ); }
function sanitize_file_name( $value ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', str_replace( array( '/', '\\' ), '', (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function checked( $a, $b = true, $echo = true ) { $r = $a == $b ? ' checked="checked"' : ''; if ( $echo ) echo $r; return $r; }
function disabled( $a, $b = true, $echo = true ) { $r = $a == $b ? ' disabled="disabled"' : ''; if ( $echo ) echo $r; return $r; }
function selected( $a, $b = true, $echo = true ) { $r = $a == $b ? ' selected="selected"' : ''; if ( $echo ) echo $r; return $r; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function submit_button( $text ) { echo '<button class="button button-primary">' . esc_html( $text ) . '</button>'; }
function admin_url( $path = '' ) { return home_url( 'wp-admin/' . ltrim( $path, '/' ) ); }
function network_admin_url( $path = '' ) { return home_url( 'wp-admin/network/' . ltrim( $path, '/' ) ); }
function add_query_arg( ...$a ) { if ( is_array( $a[0] ) ) { $args = $a[0]; $url = $a[1]; } else { $args = array( $a[0] => $a[1] ); $url = $a[2]; } return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_safe_redirect( $url ) { throw new Mig_Redirect( $url ); }
function wp_die( $message = '' ) { throw new Mig_Die( (string) $message ); }
function check_admin_referer( $action ) { if ( ( $_POST['_wpnonce'] ?? '' ) !== 'nonce-' . $action ) throw new Mig_Die( 'nonce inválido' ); return 1; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['bk_caps'][ $cap ] ); }
function wp_get_referer() { return ''; }
function size_format( $bytes ) { return number_format( $bytes / 1048576, 1 ) . ' MB'; }
function add_action() { return true; }
function add_filter() { return true; }
function nocache_headers() { return true; }
function apply_filters( $hook, $value, ...$args ) { return array_key_exists( $hook, $GLOBALS['bk_filters'] ) ? $GLOBALS['bk_filters'][ $hook ] : $value; }
function wp_upload_dir( $time = null, $create = true ) { return array( 'basedir' => WP_CONTENT_DIR . '/uploads', 'baseurl' => home_url( 'wp-content/uploads' ) ); }
function get_theme_root() { return WP_CONTENT_DIR . '/themes'; }
function wp_clear_scheduled_hook() { return true; }
function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $response, $header ) { return is_array( $response ) ? (string) ( $response['headers'][ strtolower( $header ) ] ?? '' ) : ''; }
function wp_remote_request( $url, $args = array() ) { throw new RuntimeException( 'FALLA: una petición HTTP real salió de la prueba: ' . $url ); }

/** REST como WP_REST_Server: rutas, valores por defecto, obligatorios y permission_callback antes del callback. */
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['mig_routes'][ $namespace . $route ] = $args; return true; }
class WP_REST_Response {
	public $data;
	public $status = 200;
	public $headers = array();
	public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
	public function set_status( $status ) { $this->status = (int) $status; }
	public function get_status() { return $this->status; }
	public function get_data() { return $this->data; }
	public function header( $name, $value ) { $this->headers[ $name ] = $value; }
	public function get_headers() { return $this->headers; }
}
function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value ); }
class WP_REST_Request implements ArrayAccess {
	private $params;
	private $query;
	private $body;
	private $headers;
	private $route;
	public function __construct( array $params = array(), array $query = array(), array $body = array(), array $headers = array(), $route = '' ) { $this->params = $params; $this->query = $query; $this->body = $body; $this->headers = array_change_key_case( $headers, CASE_LOWER ); $this->route = $route; }
	public function get_query_params() { return $this->query; }
	public function get_json_params() { return $this->body; }
	public function get_body_params() { return array(); }
	public function get_header( $name ) { return $this->headers[ strtolower( str_replace( '-', '_', $name ) ) ] ?? ( $this->headers[ strtolower( $name ) ] ?? null ); }
	public function get_route() { return $this->route; }
	#[\ReturnTypeWillChange]
	public function offsetExists( $key ) { return isset( $this->params[ $key ] ); }
	#[\ReturnTypeWillChange]
	public function offsetGet( $key ) { return $this->params[ $key ] ?? null; }
	#[\ReturnTypeWillChange]
	public function offsetSet( $key, $value ) { $this->params[ $key ] = $value; }
	#[\ReturnTypeWillChange]
	public function offsetUnset( $key ) { unset( $this->params[ $key ] ); }
}
/**
 * Despacha una ruta en el sitio en curso. Devuelve array( estado, datos, cabeceras ); los bytes de un fragmento del
 * paquete llegan por Digitalisimo_Backups_Rest::serve_package(), como en WordPress.
 */
function mig_rest( $method, $path, array $body = array(), array $headers = array(), array $query = array(), $logged_in = true ) {
	$route_path = '/' . ltrim( $path, '/' );
	$path = ltrim( $path, '/' );
	$params = array_merge( $query, $body );
	foreach ( $GLOBALS['mig_routes'] as $pattern => $handlers ) {
		if ( ! preg_match( '@^' . $pattern . '$@', $path, $matches ) ) continue;
		foreach ( $handlers as $handler ) {
			if ( ! in_array( $method, array_map( 'trim', explode( ',', $handler['methods'] ) ), true ) ) continue;
			foreach ( (array) ( $handler['args'] ?? array() ) as $name => $arg ) {
				if ( ! array_key_exists( $name, $params ) && array_key_exists( 'default', $arg ) ) $params[ $name ] = $arg['default'];
				if ( ! array_key_exists( $name, $params ) && ! empty( $arg['required'] ) ) return array( 400, array( 'code' => 'rest_missing_callback_param' ), array() );
			}
			foreach ( $matches as $key => $value ) if ( is_string( $key ) ) $params[ $key ] = $value;
			if ( empty( $handler['permission_callback'] ) ) throw new RuntimeException( 'FALLA: la ruta ' . $pattern . ' no declara permission_callback.' );
			$request = new WP_REST_Request( $params, $query, $body, $headers, $route_path );
			$allowed = call_user_func( $handler['permission_callback'], $request );
			if ( is_wp_error( $allowed ) ) return array( (int) ( $allowed->get_error_data()['status'] ?? 403 ), array( 'code' => $allowed->get_error_code(), 'message' => $allowed->get_error_message(), 'data' => $allowed->get_error_data() ), array() );
			if ( true !== $allowed ) return array( $logged_in ? 403 : 401, array( 'code' => 'rest_forbidden' ), array() );
			$result = call_user_func( $handler['callback'], $request );
			if ( is_wp_error( $result ) ) return array( (int) ( $result->get_error_data()['status'] ?? 500 ), array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $result->get_error_data() ), array() );
			ob_start();
			$served = Digitalisimo_Backups_Rest::serve_package( false, $result, $request, null );
			$raw = ob_get_clean();
			return array( $result->get_status(), $served ? $raw : $result->get_data(), $result->get_headers() );
		}
		return array( 404, array( 'code' => 'rest_no_route' ), array() );
	}
	return array( 404, array( 'code' => 'rest_no_route' ), array() );
}
/** Ruta REST de una URL de la API (`?rest_route=/…` o `/wp-json/…`) y el resto de la cadena de consulta. */
function mig_route_of( $url ) {
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $args );
	if ( isset( $args['rest_route'] ) ) { $route = (string) $args['rest_route']; unset( $args['rest_route'] ); return array( '/' . ltrim( $route, '/' ), $args ); }
	$path = (string) parse_url( $url, PHP_URL_PATH );
	return array( '/' . ltrim( substr( $path, (int) strpos( $path, '/wp-json/' ) + 8 ), '/' ), $args );
}
/**
 * Transporte HTTP del destino: la petición llega al sitio del host de la URL, con el paso del motor de trabajos en
 * pausa (cada sitio tiene su propio proceso en la vida real). $GLOBALS['mig']['faults'] son callables
 * ( $method, $route, $args, $response ) que pueden devolver un WP_Error, otra respuesta o null (sin cambios).
 */
function mig_transport( $method, $url, $args ) {
	$host = (string) parse_url( $url, PHP_URL_HOST );
	$target = '';
	foreach ( $GLOBALS['mig']['sites'] as $name => $site ) if ( $site['host'] === $host ) $target = $name;
	list( $route, $query ) = mig_route_of( $url );
	$GLOBALS['mig']['requests'][] = array( 'method' => $method, 'url' => $url, 'route' => $route, 'args' => $args );
	foreach ( $GLOBALS['mig']['faults'] as $i => $fault ) {
		$early = call_user_func( $fault, $method, $route, $args, null );
		if ( null !== $early ) return $early;
	}
	if ( '' === $target ) return new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host: ' . $host );
	$paused = Digitalisimo_Backups_Jobs::suspend();
	$headers = (array) ( $args['headers'] ?? array() );
	$body = isset( $args['body'] ) ? (array) json_decode( (string) $args['body'], true ) : array();
	try {
		$reply = mig_in( $target, function() use ( $method, $route, $body, $headers, $query ) {
			$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
			return mig_rest( $method, $route, $body, $headers, $query, false );
		} );
	} finally {
		Digitalisimo_Backups_Jobs::resume( $paused );
		Digitalisimo_Backups::forget_storage_directory();
	}
	$out = array( 'response' => array( 'code' => $reply[0] ), 'body' => is_string( $reply[1] ) ? $reply[1] : json_encode( $reply[1] ), 'headers' => array_change_key_case( (array) $reply[2], CASE_LOWER ) );
	foreach ( $GLOBALS['mig']['faults'] as $fault ) {
		$changed = call_user_func( $fault, $method, $route, $args, $out );
		if ( null !== $changed ) return $changed;
	}
	return $out;
}

require_once __DIR__ . '/../../digitalisimo-backups/includes/class-backups.php';
require_once __DIR__ . '/../../digitalisimo-backups/includes/class-backups-rest.php';
if ( class_exists( 'Digitalisimo_Backups_Rest' ) ) Digitalisimo_Backups_Rest::routes();
if ( class_exists( 'Digitalisimo_Backups_Migrate' ) ) {
	Digitalisimo_Backups_Migrate::$transport = 'mig_transport';
	Digitalisimo_Backups_Migrate::$pause = function() {};
	Digitalisimo_Backups_Migrate::$end = function() { throw new Mig_Exit( 'fin' ); };
}

function mig_assert( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( 'FALLA: ' . $message ); }
/** Llama a un método privado. */
function mig_call( $class, $method, ...$args ) {
	$reflection = new ReflectionMethod( $class, $method );
	if ( PHP_VERSION_ID < 80100 ) $reflection->setAccessible( true );
	return $reflection->invokeArgs( null, $args );
}
function mig_storage() { return Digitalisimo_Backups::storage_directory(); }
/** admin-post de la pantalla con nonce válido: devuelve array( ok, mensaje ) de la redirección, o la salida si no redirige. */
function mig_post( $callback, array $post ) {
	$_POST = $post + array( '_wpnonce' => '' );
	$_REQUEST = $_POST;
	ob_start();
	try {
		call_user_func( $callback );
	} catch ( Mig_Redirect $redirect ) {
		ob_end_clean();
		parse_str( (string) parse_url( $redirect->url, PHP_URL_QUERY ), $query );
		return array( 'ok' === ( $query['backup'] ?? '' ), rawurldecode( $query['message'] ?? '' ), $redirect->url );
	} catch ( Mig_Exit $exit ) {
		return array( null, ob_get_clean(), '' );
	} finally {
		$_POST = array();
		$_REQUEST = array();
		if ( ob_get_level() > 0 && ! isset( $exit ) && ! isset( $redirect ) ) ob_end_clean();
	}
	throw new RuntimeException( 'FALLA: el handler no redirigió.' );
}
/** Hace avanzar la cola del sitio en curso hasta que el trabajo termina (o $max ticks). */
function mig_drive( $id, $max = 60 ) {
	for ( $i = 0; $i < $max; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return $job;
		if ( (int) $job['retry_at'] > time() ) {
			$job['retry_at'] = 0;
			Digitalisimo_Backups_Job_Store::write( $job );
		}
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	return Digitalisimo_Backups_Jobs::get( $id );
}
function mig_tree( $root ) {
	$out = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $iterator as $entry ) $out[ substr( $entry->getPathname(), strlen( $root ) ) ] = $entry->isFile() ? hash_file( 'sha256', $entry->getPathname() ) : 'dir';
	ksort( $out );
	return $out;
}
function mig_remove_tree( $path ) {
	if ( is_link( $path ) || is_file( $path ) ) { @unlink( $path ); return; }
	if ( ! is_dir( $path ) ) return;
	foreach ( scandir( $path ) as $name ) if ( '.' !== $name && '..' !== $name ) mig_remove_tree( $path . '/' . $name );
	@rmdir( $path );
}
