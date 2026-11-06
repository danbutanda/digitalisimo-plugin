<?php
/**
 * WordPress mínimo para las pruebas de cobertura de Backups: casos de uso compartidos, REST, WP-CLI y rutas externas.
 *
 * Cada prueba define antes ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR y DIGITALISIMO_BACKUPS_STORAGE_DIR (cada proceso sólo
 * puede definirlas una vez) y después incluye este archivo. El motor corre de verdad: ZIP, manifiesto, SHA-256,
 * bloqueo del almacén, historial y restauración; sólo la base de datos es una tabla en memoria cuyo SQL exportado se
 * vuelve a importar.
 */
if ( ! defined( 'DB_NAME' ) ) define( 'DB_NAME', 'wordpress_test' );
if ( ! defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ) define( 'DIGITALISIMO_BACKUPS_VERSION', '1.0.58' );
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
if ( ! defined( 'ARRAY_N' ) ) define( 'ARRAY_N', 'ARRAY_N' );
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) define( 'MINUTE_IN_SECONDS', 60 );

/** Estado de la «instalación»: red o sitio individual, sitio en curso, permisos del usuario y opciones. */
$GLOBALS['digi'] = array(
	'multisite' => false,
	'main' => true,
	'main_network' => true,
	'blog' => 1,
	'network_admin' => false,
	'caps' => array( 'manage_options' => true ),
	'options' => array(),
	'site_options' => array(),
	'actions' => array(),
	'routes' => array(),
	'scheduled' => false,
	'uploads' => null,
);

function digi_assert( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( 'FALLA: ' . $message );
}
/** Carpeta temporal propia de la prueba, fuera de /tmp cuando TMPDIR apunta a otro sitio. */
function digi_temp_root( $name ) {
	$root = sys_get_temp_dir() . '/digitalisimo-' . $name . '-' . bin2hex( random_bytes( 4 ) );
	mkdir( $root, 0700, true );
	return realpath( $root );
}
/** Archivos que un respaldo completo exige, más contenido de ejemplo en wp-content. */
function digi_make_wordpress( $abspath, $content = null, $config = true ) {
	$content = $content ?: $abspath . '/wp-content';
	foreach ( array( 'wp-admin', 'wp-includes' ) as $dir ) @mkdir( $abspath . '/' . $dir, 0700, true );
	@mkdir( $content . '/plugins', 0700, true );
	@mkdir( $content . '/themes/ejemplo', 0700, true );
	foreach ( array( 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php' ) as $file ) file_put_contents( $abspath . '/' . $file, 'Contenido de ' . $file );
	if ( $config ) file_put_contents( $abspath . '/wp-config.php', "<?php // configuración de prueba\n" );
	file_put_contents( $content . '/plugins/example.php', "<?php // plugin de ejemplo\n" );
	file_put_contents( $content . '/themes/ejemplo/style.css', "/* Theme Name: Ejemplo */\n" );
}
/** Borra un árbol sin seguir enlaces simbólicos. */
function digi_rmtree( $path ) {
	if ( is_link( $path ) || is_file( $path ) ) { @unlink( $path ); return; }
	if ( ! is_dir( $path ) ) return;
	foreach ( scandir( $path ) as $name ) if ( '.' !== $name && '..' !== $name ) digi_rmtree( $path . '/' . $name );
	@rmdir( $path );
}
/** Archivos del almacén privado que son respaldos. */
function digi_stored( $dir ) {
	return array_map( 'basename', glob( rtrim( $dir, '/' ) . '/*.zip' ) ?: array() );
}

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

/** Redirección de admin-post: corta el flujo como el `exit` que sigue a wp_safe_redirect(). */
class Digi_Redirect extends Exception {
	public $url;
	public $query = array();
	public function __construct( $url ) { parent::__construct( 'Redirección a ' . $url ); $this->url = $url; parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $this->query ); }
}
class Digi_Die extends Exception {}

/** Tabla wp_options en memoria: el SQL que exporta el motor se vuelve a importar fila por fila. */
class Digi_Test_DB {
	public $base_prefix = 'wp_';
	public $prefix = 'wp_';
	public $last_error = '';
	public $queries = array();
	public $rows = array( array( 'option_id' => '1', 'option_name' => 'blogname', 'option_value' => 'Sitio de prueba' ) );
	public function get_col( $query ) { return array( 'wp_options' ); }
	public function prepare( $query, ...$args ) { foreach ( $args as $arg ) $query = preg_replace( '/%s/', "'" . addslashes( (string) $arg ) . "'", $query, 1 ); return $query; }
	public function get_row( $query, $format = null ) {
		$this->last_error = '';
		if ( false !== strpos( $query, 'SHOW TABLE STATUS' ) ) return array( 'Engine' => 'InnoDB' );
		return array( 'wp_options', 'CREATE TABLE `wp_options` (`option_id` bigint, `option_name` varchar(191), `option_value` longtext, PRIMARY KEY (`option_id`))' );
	}
	public function get_results( $query, $format = null ) {
		$this->last_error = '';
		if ( false !== strpos( $query, 'SHOW INDEX' ) ) return array( array( 'Key_name' => 'PRIMARY', 'Column_name' => 'option_id', 'Non_unique' => 0, 'Seq_in_index' => 1 ) );
		if ( preg_match( '/LIMIT (\d+), (\d+)/', $query, $limit ) ) return array_slice( $this->rows, (int) $limit[1], (int) $limit[2] );
		return $this->rows;
	}
	public function db_version() { return '8.0'; }
	/** `upload_path` de cada sitio de la red (id => valor), leído por Digitalisimo_Backups_Layout. */
	public $upload_paths = array();
	public function get_blog_prefix( $blog_id = null ) { return 1 === (int) $blog_id ? 'wp_' : 'wp_' . (int) $blog_id . '_'; }
	public function suppress_errors( $suppress = true ) { return false; }
	public function get_var( $query ) {
		if ( ! preg_match( "/^SELECT option_value FROM `wp_(?:(\\d+)_)?options` WHERE option_name = 'upload_path' LIMIT 1$/", $query, $match ) ) throw new RuntimeException( 'FALLA: consulta inesperada: ' . $query );
		return $this->upload_paths[ empty( $match[1] ) ? 1 : (int) $match[1] ] ?? null;
	}
	public function query( $sql ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		if ( 0 === strpos( $sql, 'DROP TABLE IF EXISTS `wp_options`' ) ) $this->rows = array();
		if ( preg_match( '/^INSERT INTO `wp_options` \((.*?)\) VALUES \((.*)\)$/s', $sql, $match ) ) {
			$columns = array_map( function( $column ) { return trim( $column, '`' ); }, explode( ',', $match[1] ) );
			$this->rows[] = array_combine( $columns, str_getcsv( $match[2], ',', "'", '\\' ) );
		}
		return 1;
	}
	public function value( $name ) { foreach ( $this->rows as $row ) if ( $row['option_name'] === $name ) return $row['option_value']; return null; }
	public function set( $name, $value ) { foreach ( $this->rows as &$row ) if ( $row['option_name'] === $name ) $row['option_value'] = $value; unset( $row ); }
}
$GLOBALS['wpdb'] = new Digi_Test_DB();

function is_multisite() { return $GLOBALS['digi']['multisite']; }
function is_main_site() { return ! $GLOBALS['digi']['multisite'] || $GLOBALS['digi']['main']; }
/** Con varias redes (WP Multi Network), cada una tiene su sitio principal y sus superadministradores. */
function is_main_network() { return ! $GLOBALS['digi']['multisite'] || $GLOBALS['digi']['main_network']; }
function get_main_site_id() { return 1; }
/** Sitios de la red: el principal (1) y un subsitio (2). */
function get_sites( $args = array() ) { return $GLOBALS['digi']['multisite'] ? array( 1, 2 ) : array(); }
function get_current_blog_id() { return $GLOBALS['digi']['multisite'] && ! $GLOBALS['digi']['main'] ? 2 : 1; }
function get_current_user_id() { return 7; }
function current_user_can( $capability ) { return ! empty( $GLOBALS['digi']['caps'][ $capability ] ); }
function is_network_admin() { return $GLOBALS['digi']['network_admin']; }
function wp_get_referer() { return ''; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['digi']['options'] ) ? $GLOBALS['digi']['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['digi']['options'][ $key ] = $value; return true; }
function add_option( $key, $value = '', $deprecated = '', $autoload = null ) { if ( ! array_key_exists( $key, $GLOBALS['digi']['options'] ) ) $GLOBALS['digi']['options'][ $key ] = $value; return true; }
function get_site_option( $key, $default = false ) { return $GLOBALS['digi']['site_options'][ $key ] ?? $default; }
function update_site_option( $key, $value ) { $GLOBALS['digi']['site_options'][ $key ] = $value; return true; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_file_name( $value ) { return trim( preg_replace( '/[^A-Za-z0-9._-]/', '', str_replace( array( '/', '\\' ), '', (string) $value ) ), '.-_' ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( (string) $value ) ), '-' ); }
function wp_unslash( $value ) { return $value; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', (string) $path ); }
function trailingslashit( $path ) { return rtrim( (string) $path, '/\\' ) . '/'; }
function wp_mkdir_p( $path ) { return is_dir( $path ) || @mkdir( $path, 0700, true ); }
function wp_basename( $path ) { return basename( (string) $path ); }
function home_url() { return 'https://example.test/'; }
function site_url() { return 'https://example.test/'; }
function get_bloginfo( $key ) { return '6.8'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_generate_uuid4() { return sprintf( '%08x-%04x-4%03x-8%03x-%012x', random_int( 0, 0xffffffff ), random_int( 0, 0xffff ), random_int( 0, 0xfff ), random_int( 0, 0xfff ), random_int( 0, 0xffffffffffff ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function current_time( $format ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_cache_flush() { return true; }
function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['digi']['scheduled']; }
function wp_clear_scheduled_hook( $hook, $args = array() ) { return 0; }
function wp_upload_dir( $time = null, $create = true ) { return array( 'basedir' => $GLOBALS['digi']['uploads'] ?? WP_CONTENT_DIR . '/uploads' ); }
function check_admin_referer( $action ) { if ( ( $_REQUEST['_wpnonce'] ?? '' ) !== 'nonce-' . $action ) throw new Digi_Die( 'Nonce inválido para ' . $action ); return 1; }
function wp_die( $message = '' ) { throw new Digi_Die( (string) $message ); }
function wp_safe_redirect( $url ) { throw new Digi_Redirect( $url ); }
function add_query_arg( $args, $url ) { $pairs = array(); foreach ( (array) $args as $key => $value ) $pairs[] = $key . '=' . $value; return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $pairs ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return 'https://example.test/wp-admin/network/' . $path; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['digi']['actions'][ $hook ][] = $callback; return true; }

/** REST: las rutas se registran y se despachan como lo hace WordPress después de autenticar. */
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['digi']['routes'][ $namespace . $route ] = $args; return true; }
class WP_REST_Response {
	public $data;
	public $status = 200;
	public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
	public function set_status( $status ) { $this->status = (int) $status; }
	public function get_status() { return $this->status; }
	public function get_data() { return $this->data; }
}
function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value ); }
class WP_REST_Request implements ArrayAccess {
	private $params;
	public function __construct( array $params = array() ) { $this->params = $params; }
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
 * Despacho de una ruta registrada: busca método y patrón, aplica los valores por defecto de sus argumentos, exige los
 * obligatorios y pregunta a permission_callback antes del callback, como WP_REST_Server. Sin permiso: 401 sin sesión,
 * 403 con sesión. Devuelve array( estado, datos ).
 */
function digi_rest( $method, $path, array $params = array(), $logged_in = true ) {
	foreach ( $GLOBALS['digi']['routes'] as $pattern => $handlers ) {
		if ( ! preg_match( '@^' . $pattern . '$@', $path, $matches ) ) continue;
		foreach ( $handlers as $handler ) {
			if ( ! in_array( $method, array_map( 'trim', explode( ',', $handler['methods'] ) ), true ) ) continue;
			foreach ( (array) ( $handler['args'] ?? array() ) as $name => $arg ) {
				if ( ! array_key_exists( $name, $params ) && array_key_exists( 'default', $arg ) ) $params[ $name ] = $arg['default'];
				if ( ! array_key_exists( $name, $params ) && ! empty( $arg['required'] ) ) return array( 400, array( 'code' => 'rest_missing_callback_param' ) );
			}
			foreach ( $matches as $key => $value ) if ( is_string( $key ) ) $params[ $key ] = $value;
			if ( empty( $handler['permission_callback'] ) ) throw new RuntimeException( 'FALLA: la ruta ' . $pattern . ' no declara permission_callback.' );
			if ( true !== call_user_func( $handler['permission_callback'], new WP_REST_Request( $params ) ) ) return array( $logged_in ? 403 : 401, array( 'code' => 'rest_forbidden' ) );
			$result = call_user_func( $handler['callback'], new WP_REST_Request( $params ) );
			if ( is_wp_error( $result ) ) return array( (int) ( $result->get_error_data()['status'] ?? 500 ), array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );
			return array( $result->get_status(), $result->get_data() );
		}
		return array( 404, array( 'code' => 'rest_no_route' ) );
	}
	return array( 404, array( 'code' => 'rest_no_route' ) );
}

require_once __DIR__ . '/../../digitalisimo-backups/includes/class-backups.php';
require_once __DIR__ . '/../../digitalisimo-backups/includes/class-backups-rest.php';
require_once __DIR__ . '/../../digitalisimo-backups/includes/class-backups-cli.php';

/** Bloqueo del almacén tomado por «otra petición». */
function digi_hold_lock() {
	$acquire = new ReflectionMethod( 'Digitalisimo_Backups', 'acquire_operation_lock' );
	if ( PHP_VERSION_ID < 80100 ) $acquire->setAccessible( true );
	return $acquire->invoke( null );
}
function digi_release_lock( $handle ) {
	flock( $handle, LOCK_UN );
	fclose( $handle );
}
/** admin-post de la pantalla: nonce válido, $_POST y la redirección que termina la petición. */
function digi_admin_post( $handler, $action, array $post ) {
	$_POST = $post;
	$_REQUEST = array_merge( $post, array( '_wpnonce' => 'nonce-' . $action ) );
	try {
		call_user_func( array( 'Digitalisimo_Backups', $handler ) );
	} catch ( Digi_Redirect $redirect ) {
		return $redirect->query;
	}
	throw new RuntimeException( 'FALLA: ' . $handler . ' terminó sin redirigir.' );
}
