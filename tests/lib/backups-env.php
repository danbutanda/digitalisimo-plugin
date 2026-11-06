<?php
/**
 * Entorno común de las pruebas de seguridad de Backups: una instalación mínima en un temporal, funciones de WordPress
 * simuladas y una base de datos en memoria cuyas filas de wp_options salen de las opciones simuladas (así el SQL
 * exportado contiene de verdad lo que el sitio guarda). Sin libsodium nativo carga la copia de sodium_compat de
 * WordPress que vive en tests/lib/sodium_compat (ver SOURCE.txt).
 */
if ( ! extension_loaded( 'sodium' ) || getenv( 'DIGI_FORCE_SODIUM_COMPAT' ) ) require_once __DIR__ . '/sodium_compat/autoload.php';

$GLOBALS['bk_root'] = sys_get_temp_dir() . '/digitalisimo-bk-' . bin2hex( random_bytes( 4 ) );
foreach ( array( '/wp-admin', '/wp-includes', '/wp-content/plugins', '/wp-content/uploads' ) as $dir ) mkdir( $GLOBALS['bk_root'] . $dir, 0700, true );
defined( 'ABSPATH' ) || define( 'ABSPATH', $GLOBALS['bk_root'] . '/' );
define( 'WP_CONTENT_DIR', $GLOBALS['bk_root'] . '/wp-content' );
define( 'WP_PLUGIN_DIR', $GLOBALS['bk_root'] . '/wp-content/plugins' );
$_SERVER['DOCUMENT_ROOT'] = $GLOBALS['bk_root'];
define( 'DB_NAME', 'wordpress_test' );
defined( 'DIGITALISIMO_BACKUPS_VERSION' ) || define( 'DIGITALISIMO_BACKUPS_VERSION', '9.9.9' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'ARRAY_A', 'ARRAY_A' );
defined( 'AUTH_KEY' ) || define( 'AUTH_KEY', 'clave-de-prueba-auth-0123456789abcdef0123456789abcdef' );
defined( 'SECURE_AUTH_KEY' ) || define( 'SECURE_AUTH_KEY', 'clave-de-prueba-secure-0123456789abcdef0123456789abcd' );
foreach ( array( 'wp-config.php', 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php', 'wp-content/plugins/example.php' ) as $name ) file_put_contents( $GLOBALS['bk_root'] . '/' . $name, '<?php // ' . $name . "\n" );

/** Base de datos en memoria: wp_options refleja las opciones simuladas; wp_posts trae datos difíciles de escapar. */
class Bk_Test_DB {
	public $base_prefix = 'wp_';
	public $prefix = 'wp_';
	public $last_error = '';
	public $queries = array();
	public $fail_on = '';
	/** Simula que el proceso muere en esta sentencia: exit() no ejecuta ningún finally. */
	public $exit_on = '';
	public $posts = array();
	public $extra_tables = array();
	public function __construct() { $this->posts = array( array( 'ID' => '1', 'post_content' => "Texto con 'comillas', \"dobles\", ; punto y coma, \\ barra, salto\nde línea y \0 nulo" ), array( 'ID' => '2', 'post_content' => null ) ); }
	public function tables() { return array_merge( array( 'wp_options', 'wp_posts' ), array_keys( $this->extra_tables ) ); }
	public function get_col( $query ) { return $this->tables(); }
	public function prepare( $query, $value ) { return str_replace( '%s', "'" . $value . "'", $query ); }
	public function get_row( $query, $format ) {
		$this->last_error = '';
		if ( false !== strpos( $query, 'SHOW TABLE STATUS' ) ) return array( 'Engine' => 'InnoDB' );
		preg_match( '/`([^`]+)`/', $query, $m );
		$table = $m[1] ?? '';
		if ( 'wp_options' === $table ) return array( $table, "CREATE TABLE `wp_options` (\n  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `option_name` varchar(191) NOT NULL DEFAULT '',\n  `option_value` longtext NOT NULL,\n  PRIMARY KEY (`option_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci" );
		if ( isset( $this->extra_tables[ $table ] ) ) return array( $table, $this->extra_tables[ $table ] );
		return array( $table, "CREATE TABLE `" . $table . "` (\n  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `post_content` longtext,\n  PRIMARY KEY (`ID`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
	}
	public function option_rows() {
		$rows = array();
		$id = 0;
		foreach ( $GLOBALS['bk_options'] as $name => $value ) $rows[] = array( 'option_id' => (string) ++$id, 'option_name' => $name, 'option_value' => is_scalar( $value ) ? (string) $value : serialize( $value ) );
		return $rows;
	}
	public function get_results( $query, $format ) {
		$this->last_error = '';
		if ( false !== strpos( $query, 'SHOW INDEX' ) ) return array( array( 'Key_name' => 'PRIMARY', 'Column_name' => false !== strpos( $query, 'wp_options' ) ? 'option_id' : 'ID', 'Non_unique' => 0, 'Seq_in_index' => 1 ) );
		if ( false !== strpos( $query, 'LIMIT 500,' ) ) return array();
		if ( false !== strpos( $query, '`wp_options`' ) ) return $this->option_rows();
		if ( false !== strpos( $query, '`wp_posts`' ) ) return $this->posts;
		return array();
	}
	public function db_version() { return '8.0'; }
	public function query( $sql ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		if ( '' !== $this->fail_on && false !== strpos( $sql, $this->fail_on ) ) { $this->last_error = 'Fallo simulado'; return false; }
		if ( '' !== $this->exit_on && false !== strpos( $sql, $this->exit_on ) ) { $this->exit_on = ''; exit; }
		if ( 0 === strpos( $sql, 'DROP TABLE IF EXISTS `wp_options`' ) ) $GLOBALS['bk_options'] = array();
		if ( 0 === strpos( $sql, 'INSERT INTO `wp_options`' ) && preg_match_all( "/'((?:[^'\\\\]|\\\\.)*)'|NULL/s", substr( $sql, strpos( $sql, 'VALUES' ) ), $m ) && count( $m[1] ) >= 3 ) {
			$value = bk_sql_unescape( $m[1][2] );
			$unserialized = @unserialize( $value );
			$GLOBALS['bk_options'][ bk_sql_unescape( $m[1][1] ) ] = false === $unserialized && 'b:0;' !== $value ? $value : $unserialized;
		}
		return 1;
	}
	public function selects() { return array_values( array_filter( $this->queries, function( $q ) { return 0 === strpos( $q, 'DROP' ) || 0 === strpos( $q, 'INSERT' ) || 0 === strpos( $q, 'CREATE' ) || 0 === strpos( $q, 'SET NAMES' ); } ) ); }
}
function bk_sql_unescape( $value ) { return strtr( $value, array( '\\\\' => '\\', '\\0' => "\0", '\\n' => "\n", '\\r' => "\r", '\\Z' => "\x1a", "\\'" => "'" ) ); }
$GLOBALS['wpdb'] = new Bk_Test_DB();
$GLOBALS['bk_options'] = array( 'siteurl' => 'https://example.test', 'blogname' => 'Sitio de prueba' );
$GLOBALS['bk_site_options'] = array();
$GLOBALS['bk_multisite'] = false;
$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
$GLOBALS['bk_nonces'] = array();
$GLOBALS['bk_filters'] = array();
$GLOBALS['bk_post'] = array();

class Bk_Redirect extends Error { public $url; public function __construct( $url ) { parent::__construct( 'redirect' ); $this->url = $url; } }
class Bk_Die extends Error {}
/** $GLOBALS['bk_on_mkdir'] observa cada carpeta que se prepara (la extracción prepara una por archivo). */
function wp_mkdir_p( $path ) { if ( isset( $GLOBALS['bk_on_mkdir'] ) ) call_user_func( $GLOBALS['bk_on_mkdir'], $path ); return is_dir( $path ) || @mkdir( $path, 0700, true ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
function get_site( $id ) { return (object) array( 'blog_id' => $id, 'domain' => 'example.test', 'path' => '/' ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function untrailingslashit( $path ) { return rtrim( $path, '/\\' ); }
function home_url() { return 'https://example.test/'; }
function site_url() { return 'https://example.test/'; }
function get_bloginfo( $key ) { return '6.8'; }
function is_multisite() { return $GLOBALS['bk_multisite']; }
function is_main_site() { return true; }
function is_network_admin() { return false; }
function get_main_site_id() { return 1; }
function get_current_blog_id() { return 1; }
function wp_generate_uuid4() { $b = random_bytes( 16 ); $b[6] = chr( ord( $b[6] ) & 0x0f | 0x40 ); $b[8] = chr( ord( $b[8] ) & 0x3f | 0x80 ); return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $b ), 4 ) ); }
function wp_generate_password( $length = 12 ) { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_option( $key, $default = false ) { return $GLOBALS['bk_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['bk_options'][ $key ] = $value; return true; }
function add_option( $key, $value, $deprecated = '', $autoload = null ) { if ( isset( $GLOBALS['bk_options'][ $key ] ) ) return false; $GLOBALS['bk_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['bk_options'][ $key ] ); return true; }
function get_site_option( $key, $default = false ) { return $GLOBALS['bk_site_options'][ $key ] ?? $default; }
function update_site_option( $key, $value ) { $GLOBALS['bk_site_options'][ $key ] = $value; return true; }
function wp_cache_flush() { return true; }
function current_time( $format ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_basename( $path ) { return basename( $path ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $value ) ), '-' ); }
function sanitize_file_name( $value ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', str_replace( array( '/', '\\' ), '', (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function checked( $a, $b = true, $echo = true ) { $r = $a == $b ? ' checked="checked"' : ''; if ( $echo ) echo $r; return $r; }
function disabled( $a, $b = true, $echo = true ) { $r = $a == $b ? ' disabled="disabled"' : ''; if ( $echo ) echo $r; return $r; }
function selected( $a, $b = true, $echo = true ) { $r = $a == $b ? ' selected="selected"' : ''; if ( $echo ) echo $r; return $r; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function submit_button( $text ) { echo '<button class="button button-primary">' . esc_html( $text ) . '</button>'; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function network_admin_url( $path = '' ) { return 'https://example.test/wp-admin/network/' . ltrim( $path, '/' ); }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_safe_redirect( $url ) { throw new Bk_Redirect( $url ); }
function wp_die( $message = '' ) { throw new Bk_Die( (string) $message ); }
function check_admin_referer( $action ) { $GLOBALS['bk_nonces'][] = $action; if ( ( $_POST['_wpnonce'] ?? '' ) !== 'nonce-' . $action ) throw new Bk_Die( 'nonce inválido' ); return 1; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['bk_caps'][ $cap ] ); }
function wp_get_referer() { return ''; }
function size_format( $bytes ) { return number_format( $bytes / 1048576, 1 ) . ' MB'; }
function add_action() { return true; }
function apply_filters( $hook, $value, ...$args ) { return array_key_exists( $hook, $GLOBALS['bk_filters'] ) ? $GLOBALS['bk_filters'][ $hook ] : $value; }
function wp_upload_dir() { return array( 'basedir' => WP_CONTENT_DIR . '/uploads' ); }
function get_theme_root() { return WP_CONTENT_DIR . '/themes'; }
function wp_clear_scheduled_hook() { return true; }

require_once __DIR__ . '/../../digitalisimo-backups/includes/class-backups.php';

/** Llama a un método privado de una clase. */
function bk_call( $class, $method, ...$args ) {
	$reflection = new ReflectionMethod( $class, $method );
	if ( PHP_VERSION_ID < 80100 ) $reflection->setAccessible( true );
	return $reflection->invokeArgs( null, $args );
}
function bk_storage() { return bk_call( 'Digitalisimo_Backups', 'local_directory' ); }
/** Archivos bajo ABSPATH (para comprobar que un rechazo no escribió nada). */
function bk_tree( $root = null ) {
	$root = $root ?: rtrim( ABSPATH, '/' );
	$out = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $iterator as $entry ) $out[ substr( $entry->getPathname(), strlen( $root ) ) ] = $entry->isFile() ? hash_file( 'sha256', $entry->getPathname() ) : 'dir';
	ksort( $out );
	return $out;
}
/** Comprueba que $callback lanza una excepción que contiene $needle; devuelve el mensaje. */
function bk_expect_failure( $label, $callback, $needle = '' ) {
	try {
		$callback();
	} catch ( Bk_Redirect $redirect ) {
		parse_str( (string) parse_url( $redirect->url, PHP_URL_QUERY ), $query );
		$message = rawurldecode( $query['message'] ?? '' );
		if ( 'error' !== ( $query['backup'] ?? '' ) ) throw new RuntimeException( $label . ': se esperaba un error y la operación terminó bien: ' . $message );
		if ( '' !== $needle && false === strpos( $message, $needle ) ) throw new RuntimeException( $label . ': mensaje inesperado: ' . $message );
		return $message;
	} catch ( Exception $e ) {
		if ( '' !== $needle && false === strpos( $e->getMessage(), $needle ) ) throw new RuntimeException( $label . ': mensaje inesperado: ' . $e->getMessage(), 0, $e );
		if ( getenv( 'BK_VERBOSE' ) ) echo '  · ' . $label . ': ' . $e->getMessage() . "\n";
		return $e->getMessage();
	}
	throw new RuntimeException( $label . ': se aceptó lo que debía rechazarse.' );
}
/** Ejecuta un handler admin-post y devuelve el redirect como array( ok, mensaje, url ). */
function bk_post( $callback, $post ) {
	$_POST = $post;
	$_REQUEST = $post;
	try { $callback(); }
	catch ( Bk_Redirect $redirect ) {
		parse_str( (string) parse_url( $redirect->url, PHP_URL_QUERY ), $query );
		return array( 'ok' === ( $query['backup'] ?? '' ), rawurldecode( $query['message'] ?? '' ), $redirect->url );
	} finally { $_POST = array(); $_REQUEST = array(); }
	throw new RuntimeException( 'El handler no redirigió.' );
}
function bk_remove_tree( $path ) {
	if ( is_link( $path ) || is_file( $path ) ) { @unlink( $path ); return; }
	if ( ! is_dir( $path ) ) return;
	foreach ( scandir( $path ) as $name ) if ( '.' !== $name && '..' !== $name ) bk_remove_tree( $path . '/' . $name );
	@rmdir( $path );
}
function bk_cleanup() {
	$storage = null;
	try { $storage = bk_storage(); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	if ( $storage ) bk_remove_tree( $storage );
	bk_remove_tree( rtrim( ABSPATH, '/' ) );
}
/** ZIP construido a mano: permite nombres, atributos, métodos, banderas y tamaños que ZipArchive no escribe. */
function bk_raw_zip( $path, $entries ) {
	$local = '';
	$central = '';
	foreach ( $entries as $entry ) {
		$entry += array( 'method' => 0, 'flags' => 0, 'attr' => 0100644 << 16, 'opsys' => 3, 'declared' => null );
		$data = $entry['data'];
		$stored = 8 === $entry['method'] ? gzdeflate( $data ) : $data;
		$crc = crc32( $data );
		$size = null === $entry['declared'] ? strlen( $data ) : $entry['declared'];
		$offset = strlen( $local );
		$header = pack( 'vvvvvVVVvv', 20, $entry['flags'], $entry['method'], 0, 0x21, $crc, strlen( $stored ), $size, strlen( $entry['name'] ), 0 );
		$local .= "PK\x03\x04" . $header . $entry['name'] . $stored;
		$central .= "PK\x01\x02" . pack( 'vvvvvvVVVvvvvvVV', ( $entry['opsys'] << 8 ) | 20, 20, $entry['flags'], $entry['method'], 0, 0x21, $crc, strlen( $stored ), $size, strlen( $entry['name'] ), 0, 0, 0, 0, $entry['attr'], $offset ) . $entry['name'];
	}
	$end = "PK\x05\x06" . pack( 'vvvvVVv', 0, 0, count( $entries ), count( $entries ), strlen( $central ), strlen( $local ), 0 );
	file_put_contents( $path, $local . $central . $end );
	return $path;
}
