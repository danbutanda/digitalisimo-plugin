<?php
/**
 * WordPress mínimo para «Extraer como WordPress individual» (tests/backups-site-extract.php): una red con varias tablas
 * reales en memoria (opciones, entradas, usuarios, metas, sitemeta) y un intérprete de las consultas que usan el motor
 * y la extracción. Las opciones se leen y escriben en esas tablas, como en WordPress: importar el SQL de un respaldo
 * las reemplaza. El motor corre de verdad: cola, ZIP, manifiesto, SHA-256, verificación y restauración.
 *
 * Antes de incluirlo, la prueba define ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR y DIGITALISIMO_BACKUPS_STORAGE_DIR.
 */
if ( ! extension_loaded( 'sodium' ) || getenv( 'DIGI_FORCE_SODIUM_COMPAT' ) ) require_once __DIR__ . '/../lib/sodium_compat/autoload.php';
if ( ! defined( 'AUTH_KEY' ) ) define( 'AUTH_KEY', 'extraer-auth-0123456789abcdef0123456789abcdef0123' );
if ( ! defined( 'SECURE_AUTH_KEY' ) ) define( 'SECURE_AUTH_KEY', 'extraer-secure-0123456789abcdef0123456789abcdef' );
if ( ! defined( 'DB_NAME' ) ) define( 'DB_NAME', 'wordpress_test' );
if ( ! defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ) define( 'DIGITALISIMO_BACKUPS_VERSION', '9.9.9-test' );
if ( ! defined( 'DIGITALISIMO_BACKUPS_FILE' ) ) define( 'DIGITALISIMO_BACKUPS_FILE', WP_PLUGIN_DIR . '/digitalisimo-backups/digitalisimo-backups.php' );
if ( ! defined( 'ARRAY_A' ) ) define( 'ARRAY_A', 'ARRAY_A' );
if ( ! defined( 'ARRAY_N' ) ) define( 'ARRAY_N', 'ARRAY_N' );
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['digi'] = array(
	'multisite' => true,
	'blog' => 1,
	'stack' => array(),
	'sites' => array(),
	'super_admin' => true,
	'caps' => array( 'manage_network_options' => true ),
	'actions' => array(),
	'filters' => array(),
	'routes' => array(),
	'scheduled' => false,
);

function digi_assert( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( 'FALLA: ' . $message );
}
function digi_rmtree( $path ) {
	if ( is_link( $path ) || is_file( $path ) ) { @unlink( $path ); return; }
	if ( ! is_dir( $path ) ) return;
	foreach ( scandir( $path ) as $name ) if ( '.' !== $name && '..' !== $name ) digi_rmtree( $path . '/' . $name );
	@rmdir( $path );
}
function digi_write( $path, $content ) {
	if ( ! is_dir( dirname( $path ) ) ) mkdir( dirname( $path ), 0700, true );
	file_put_contents( $path, $content );
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
class Digi_Redirect extends Exception {
	public $url;
	public $query = array();
	public function __construct( $url ) { parent::__construct( 'Redirección a ' . $url ); $this->url = $url; parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $this->query ); }
}
class Digi_Die extends Exception {}

/**
 * Base de datos en memoria: tablas con su CREATE, su clave primaria y sus filas. Entiende las sentencias que escribe el
 * exportador (DROP, CREATE, INSERT) y las consultas de lectura del motor y de la extracción.
 */
class Digi_Extract_DB {
	public $base_prefix = 'wp_';
	public $prefix = 'wp_';
	public $last_error = '';
	public $queries = array();
	public $tables = array();

	public function get_blog_prefix( $blog_id = null ) {
		$blog_id = null === $blog_id ? get_current_blog_id() : (int) $blog_id;
		return ( ! is_multisite() || 1 === $blog_id ) ? $this->base_prefix : $this->base_prefix . $blog_id . '_';
	}
	public function esc_like( $text ) { return addcslashes( (string) $text, '_%\\' ); }
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) $args = $args[0];
		foreach ( $args as $arg ) $query = preg_replace( '/%[sd]/', is_int( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'", $query, 1 );
		return $query;
	}
	public function suppress_errors( $suppress = true ) { return false; }
	public function db_version() { return '8.0'; }
	/** Crea una tabla con columnas (la primera es la clave primaria). */
	public function create( $name, array $columns, array $rows = array() ) {
		$definition = 'CREATE TABLE `' . $name . '` (' . implode( ', ', array_map( function( $column ) { return '`' . $column . '` longtext'; }, $columns ) ) . ', PRIMARY KEY (`' . $columns[0] . '`))';
		$this->tables[ $name ] = array( 'create' => $definition, 'columns' => $columns, 'rows' => array() );
		foreach ( $rows as $row ) $this->insert( $name, $row );
	}
	public function insert( $name, array $row ) {
		$full = array();
		foreach ( $this->tables[ $name ]['columns'] as $index => $column ) $full[ $column ] = array_key_exists( $column, $row ) ? ( null === $row[ $column ] ? null : (string) $row[ $column ] ) : ( 0 === $index ? (string) ( $this->max( $name, $column ) + 1 ) : '' );
		$this->tables[ $name ]['rows'][] = $full;
	}
	private function max( $table, $column ) { $max = 0; foreach ( $this->tables[ $table ]['rows'] as $row ) $max = max( $max, (int) $row[ $column ] ); return $max; }
	public function rows( $table ) { return $this->tables[ $table ]['rows'] ?? array(); }

	/* ---- Lectura ---- */
	public function get_col( $query, $x = 0 ) {
		$this->last_error = '';
		if ( 'SHOW TABLES' === trim( $query ) ) return array_keys( $this->tables );
		if ( preg_match( "/^SHOW TABLES LIKE '((?:[^'\\\\]|\\\\.)*)'$/s", trim( $query ), $m ) ) { $pattern = self::like( stripslashes( $m[1] ) ); return array_values( array_filter( array_keys( $this->tables ), function( $name ) use ( $pattern ) { return preg_match( $pattern, $name ); } ) ); }
		$rows = $this->select( $query );
		return array_map( function( $row ) { return reset( $row ); }, $rows );
	}
	public function get_var( $query ) {
		$rows = $this->select( $query );
		return $rows ? reset( $rows[0] ) : null;
	}
	public function get_row( $query, $format = null ) {
		$this->last_error = '';
		if ( false !== strpos( $query, 'SHOW TABLE STATUS' ) ) return array( 'Engine' => 'InnoDB' );
		if ( preg_match( '/^SHOW CREATE TABLE `([^`]+)`$/', trim( $query ), $m ) ) return isset( $this->tables[ $m[1] ] ) ? array( $m[1], $this->tables[ $m[1] ]['create'] ) : null;
		$rows = $this->select( $query );
		return $rows ? $rows[0] : null;
	}
	public function get_results( $query, $format = null ) {
		$this->last_error = '';
		if ( preg_match( '/^SHOW INDEX FROM `([^`]+)`$/', trim( $query ), $m ) ) return array( array( 'Key_name' => 'PRIMARY', 'Column_name' => $this->tables[ $m[1] ]['columns'][0], 'Non_unique' => 0, 'Seq_in_index' => 1 ) );
		if ( preg_match( '/^SHOW COLUMNS FROM `([^`]+)`$/', trim( $query ), $m ) ) { if ( ! isset( $this->tables[ $m[1] ] ) ) return array(); $out = array(); foreach ( $this->tables[ $m[1] ]['columns'] as $index => $column ) $out[] = array( 'Field' => $column, 'Type' => 'longtext', 'Key' => 0 === $index ? 'PRI' : '' ); return $out; }
		return $this->select( $query );
	}
	private static function like( $pattern, $escape = '\\' ) {
		$out = '';
		for ( $i = 0; $i < strlen( $pattern ); $i++ ) {
			$c = $pattern[ $i ];
			if ( $escape === $c && $i + 1 < strlen( $pattern ) ) { $out .= preg_quote( $pattern[ ++$i ], '/' ); continue; }
			$out .= '%' === $c ? '.*' : ( '_' === $c ? '.' : preg_quote( $c, '/' ) );
		}
		return '/^' . $out . '$/s';
	}
	private static function literals( $text ) {
		preg_match_all( "/'((?:[^'\\\\]|\\\\.)*)'/s", $text, $m );
		return array_map( 'stripslashes', $m[1] );
	}
	/** SELECT [DISTINCT] columnas|*|MAX(c) FROM `t` [WHERE a AND b] [ORDER BY …] [LIMIT o, n]. */
	private function select( $query ) {
		$this->last_error = '';
		$this->queries[] = $query;
		if ( ! preg_match( '/^SELECT\s+(DISTINCT\s+)?(.+?)\s+FROM\s+`([^`]+)`(?:\s+WHERE\s+(.+?))?(?:\s+ORDER BY\s+(.+?))?(?:\s+LIMIT\s+(\d+)(?:\s*,\s*(\d+))?)?\s*$/s', trim( $query ), $m ) ) throw new RuntimeException( 'FALLA: consulta inesperada: ' . $query );
		$table = $m[3];
		if ( ! isset( $this->tables[ $table ] ) ) { $this->last_error = "Table '" . $table . "' doesn't exist"; return array(); }
		$rows = self::where( $this->tables[ $table ]['rows'], (string) ( $m[4] ?? '' ) );
		if ( ! empty( $m[5] ) ) { $column = trim( explode( ',', $m[5] )[0], '` ' ); usort( $rows, function( $a, $b ) use ( $column ) { return (int) $a[ $column ] <=> (int) $b[ $column ]; } ); }
		if ( isset( $m[6] ) && '' !== $m[6] ) $rows = isset( $m[7] ) && '' !== $m[7] ? array_slice( $rows, (int) $m[6], (int) $m[7] ) : array_slice( $rows, 0, (int) $m[6] );
		$columns = trim( $m[2] );
		if ( 'COUNT(*)' === strtoupper( $columns ) ) return array( array( 'COUNT(*)' => count( $rows ) ) );
		if ( preg_match( '/^MAX\((\w+)\)$/i', $columns, $c ) ) { $max = null; foreach ( $rows as $row ) $max = null === $max ? (int) $row[ $c[1] ] : max( $max, (int) $row[ $c[1] ] ); return array( array( 'MAX' => $max ) ); }
		if ( '*' !== $columns ) {
			$wanted = array_map( function( $column ) { return trim( $column, '` ' ); }, explode( ',', $columns ) );
			$rows = array_map( function( $row ) use ( $wanted ) { $out = array(); foreach ( $wanted as $column ) $out[ $column ] = $row[ $column ]; return $out; }, $rows );
		}
		if ( ! empty( $m[1] ) ) $rows = array_values( array_map( 'unserialize', array_unique( array_map( 'serialize', $rows ) ) ) );
		return $rows;
	}

	/** Filas que cumplen un WHERE de condiciones unidas por AND dentro de grupos unidos por OR. */
	private static function where( array $rows, $clause ) {
		$clause = trim( $clause );
		if ( '' === $clause ) return array_values( $rows );
		$groups = preg_split( '/\s+OR\s+/', $clause );
		return array_values( array_filter( $rows, function( $row ) use ( $groups ) {
			foreach ( $groups as $group ) {
				$all = true;
				foreach ( preg_split( '/\s+AND\s+/', trim( $group ) ) as $condition ) if ( ! self::matches( $row, trim( $condition ) ) ) { $all = false; break; }
				if ( $all ) return true;
			}
			return false;
		} ) );
	}
	private static function matches( array $row, $condition ) {
		if ( preg_match( "/^`?(\\w+)`?\\s+IN\\s*\\((.*)\\)$/s", $condition, $c ) ) return in_array( (string) $row[ $c[1] ], self::literals( $c[2] ), true );
		if ( preg_match( "/^`?(\\w+)`?\\s+LIKE\\s+'((?:[^'\\\\]|\\\\.)*)'(?:\\s+ESCAPE\\s+'(.)')?$/s", $condition, $c ) ) return (bool) preg_match( self::like( stripslashes( $c[2] ), $c[3] ?? '\\' ), (string) $row[ $c[1] ] );
		if ( preg_match( "/^`?(\\w+)`?\\s*=\\s*'((?:[^'\\\\]|\\\\.)*)'$/s", $condition, $c ) ) return (string) $row[ $c[1] ] === stripslashes( $c[2] );
		if ( preg_match( '/^`?(\w+)`?\s*(=|>)\s*(-?\d+)$/', $condition, $c ) ) return '=' === $c[2] ? (int) $row[ $c[1] ] === (int) $c[3] : (int) $row[ $c[1] ] > (int) $c[3];
		throw new RuntimeException( 'FALLA: condición inesperada: ' . $condition );
	}
	/* ---- Escritura ---- */
	public function query( $sql ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		$sql = trim( $sql );
		if ( preg_match( '/^(SET |START TRANSACTION|COMMIT|ROLLBACK|UNLOCK TABLES|LOCK TABLES)/i', $sql ) ) return 1;
		if ( preg_match( '/^DROP TABLE IF EXISTS `([^`]+)`$/', $sql, $m ) ) { unset( $this->tables[ $m[1] ] ); return 1; }
		if ( preg_match( "/^DELETE FROM `([^`]+)` WHERE (.+)$/s", $sql, $m ) ) {
			if ( ! isset( $this->tables[ $m[1] ] ) ) return 0;
			$gone = array_map( 'serialize', self::where( $this->tables[ $m[1] ]['rows'], $m[2] ) );
			$before = count( $this->tables[ $m[1] ]['rows'] );
			$this->tables[ $m[1] ]['rows'] = array_values( array_filter( $this->tables[ $m[1] ]['rows'], function( $row ) use ( $gone ) { return ! in_array( serialize( $row ), $gone, true ); } ) );
			return $before - count( $this->tables[ $m[1] ]['rows'] );
		}
		if ( preg_match( "/^UPDATE `([^`]+)` SET (.+?) WHERE (.+)$/s", $sql, $m ) ) {
			if ( ! isset( $this->tables[ $m[1] ] ) ) { $this->last_error = "Table '" . $m[1] . "' doesn't exist"; return false; }
			preg_match_all( "/`?(\\w+)`?\\s*=\\s*'((?:[^'\\\\]|\\\\.)*)'/s", $m[2], $sets, PREG_SET_ORDER );
			$hit = array_map( 'serialize', self::where( $this->tables[ $m[1] ]['rows'], $m[3] ) );
			$count = 0;
			foreach ( $this->tables[ $m[1] ]['rows'] as $index => $row ) {
				if ( ! in_array( serialize( $row ), $hit, true ) ) continue;
				foreach ( $sets as $set ) $this->tables[ $m[1] ]['rows'][ $index ][ $set[1] ] = stripslashes( $set[2] );
				$count++;
			}
			return $count;
		}
		if ( preg_match( '/^CREATE TABLE `([^`]+)` \((.*)\)$/s', $sql, $m ) ) {
			preg_match_all( '/`(\w+)` longtext/', $m[2], $c );
			preg_match( '/PRIMARY KEY \(`(\w+)`\)/', $m[2], $pk );
			$columns = $c[1];
			if ( $pk ) { $columns = array_values( array_diff( $columns, array( $pk[1] ) ) ); array_unshift( $columns, $pk[1] ); }
			$this->tables[ $m[1] ] = array( 'create' => $sql, 'columns' => $columns, 'rows' => array() );
			return 1;
		}
		if ( preg_match( '/^INSERT INTO `([^`]+)` \((.*?)\) VALUES \((.*)\)$/s', $sql, $m ) ) {
			if ( ! isset( $this->tables[ $m[1] ] ) ) { $this->last_error = "Table '" . $m[1] . "' doesn't exist"; return false; }
			$columns = array_map( function( $column ) { return trim( $column, '` ' ); }, explode( ',', $m[2] ) );
			$this->tables[ $m[1] ]['rows'][] = array_combine( $columns, self::values( $m[3] ) );
			return 1;
		}
		throw new RuntimeException( 'FALLA: sentencia inesperada: ' . substr( $sql, 0, 120 ) );
	}
	/** Valores del exportador: NULL o '…' con \\, \0, \n, \r, \Z y \'. */
	public static function values( $text ) {
		$out = array();
		$i = 0;
		$n = strlen( $text );
		while ( $i < $n ) {
			if ( ' ' === $text[ $i ] || ',' === $text[ $i ] ) { $i++; continue; }
			if ( 'NULL' === substr( $text, $i, 4 ) ) { $out[] = null; $i += 4; continue; }
			if ( "'" !== $text[ $i ] ) throw new RuntimeException( 'FALLA: valor SQL inesperado en ' . substr( $text, $i, 40 ) );
			$i++;
			$value = '';
			while ( $i < $n && "'" !== $text[ $i ] ) {
				if ( '\\' === $text[ $i ] ) { $next = $text[ ++$i ]; $value .= array( '0' => "\0", 'n' => "\n", 'r' => "\r", 'Z' => "\x1a" )[ $next ] ?? $next; $i++; continue; }
				$value .= $text[ $i++ ];
			}
			$i++;
			$out[] = $value;
		}
		return $out;
	}
	/* ---- Opciones (las usan get_option() y compañía) ---- */
	public function option_row( $table, $name ) { foreach ( $this->tables[ $table ]['rows'] ?? array() as $index => $row ) if ( $row['option_name'] === $name ) return $index; return null; }
}
$GLOBALS['wpdb'] = new Digi_Extract_DB();

function digi_options_table() { global $wpdb; return $wpdb->get_blog_prefix() . 'options'; }
function is_serialized( $data ) { return is_string( $data ) && ( 'N;' === $data || (bool) preg_match( '/^(?:a:\d+:\{|O:\d+:"|s:\d+:"|i:-?\d+;|d:-?[\d.E+-]+;|b:[01];)/', $data ) ); }
function maybe_unserialize( $value ) { return is_serialized( $value ) ? @unserialize( $value, array( 'allowed_classes' => false ) ) : $value; }
function maybe_serialize( $value ) { return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value; }
function get_option( $key, $default = false ) {
	global $wpdb;
	$table = digi_options_table();
	$index = $wpdb->option_row( $table, $key );
	return null === $index ? $default : maybe_unserialize( $wpdb->tables[ $table ]['rows'][ $index ]['option_value'] );
}
function update_option( $key, $value, $autoload = null ) {
	global $wpdb;
	$table = digi_options_table();
	if ( ! isset( $wpdb->tables[ $table ] ) ) return false;
	$index = $wpdb->option_row( $table, $key );
	if ( null === $index ) $wpdb->insert( $table, array( 'option_name' => $key, 'option_value' => (string) maybe_serialize( $value ), 'autoload' => false === $autoload ? 'no' : 'yes' ) );
	else $wpdb->tables[ $table ]['rows'][ $index ]['option_value'] = (string) maybe_serialize( $value );
	return true;
}
function add_option( $key, $value = '', $deprecated = '', $autoload = null ) { return false === get_option( $key, false ) ? update_option( $key, $value, $autoload ) : false; }
function delete_option( $key ) {
	global $wpdb;
	$table = digi_options_table();
	$index = $wpdb->option_row( $table, $key );
	if ( null !== $index ) { unset( $wpdb->tables[ $table ]['rows'][ $index ] ); $wpdb->tables[ $table ]['rows'] = array_values( $wpdb->tables[ $table ]['rows'] ); }
	return true;
}
function get_site_option( $key, $default = false ) {
	global $wpdb;
	if ( ! is_multisite() ) return get_option( $key, $default );
	foreach ( $wpdb->rows( $wpdb->base_prefix . 'sitemeta' ) as $row ) if ( $row['meta_key'] === $key && 1 === (int) $row['site_id'] ) return maybe_unserialize( $row['meta_value'] );
	return $default;
}
function update_site_option( $key, $value ) {
	global $wpdb;
	if ( ! is_multisite() ) return update_option( $key, $value );
	$table = $wpdb->base_prefix . 'sitemeta';
	foreach ( $wpdb->tables[ $table ]['rows'] as $index => $row ) if ( $row['meta_key'] === $key ) { $wpdb->tables[ $table ]['rows'][ $index ]['meta_value'] = (string) maybe_serialize( $value ); return true; }
	$wpdb->insert( $table, array( 'site_id' => '1', 'meta_key' => $key, 'meta_value' => (string) maybe_serialize( $value ) ) );
	return true;
}
function get_blog_option( $id, $key, $default = false ) { switch_to_blog( $id ); try { return get_option( $key, $default ); } finally { restore_current_blog(); } }

/* ---- Red ---- */
function is_multisite() { return $GLOBALS['digi']['multisite']; }
function get_current_blog_id() { return $GLOBALS['digi']['multisite'] ? (int) $GLOBALS['digi']['blog'] : 1; }
function is_main_site( $site_id = null ) { return ! is_multisite() || 1 === (int) ( $site_id ?? get_current_blog_id() ); }
function is_main_network() { return true; }
function get_main_site_id( $network = null ) { return 1; }
function get_main_network_id() { return 1; }
function switch_to_blog( $id ) { $GLOBALS['digi']['stack'][] = $GLOBALS['digi']['blog']; $GLOBALS['digi']['blog'] = (int) $id; $GLOBALS['wpdb']->prefix = $GLOBALS['wpdb']->get_blog_prefix( (int) $id ); return true; }
function restore_current_blog() { $GLOBALS['digi']['blog'] = array_pop( $GLOBALS['digi']['stack'] ) ?? 1; $GLOBALS['wpdb']->prefix = $GLOBALS['wpdb']->get_blog_prefix(); return true; }
function get_site( $id = null ) {
	$site = $GLOBALS['digi']['sites'][ (int) $id ] ?? null;
	return $site && is_multisite() ? (object) ( $site + array( 'blog_id' => (int) $id, 'site_id' => 1, 'network_id' => 1 ) ) : null;
}
function get_sites( $args = array() ) {
	if ( ! is_multisite() ) return array();
	$ids = array_keys( $GLOBALS['digi']['sites'] );
	return 'ids' === ( $args['fields'] ?? '' ) ? $ids : array_map( 'get_site', $ids );
}
function get_home_url( $id = null, $path = '' ) { return untrailingslashit( (string) get_blog_option( $id ?? get_current_blog_id(), 'home' ) ) . $path; }
function is_super_admin() { return ! empty( $GLOBALS['digi']['super_admin'] ); }
function current_user_can( $capability ) { return ! empty( $GLOBALS['digi']['caps'][ $capability ] ); }
function is_network_admin() { return false; }
function get_current_user_id() { return 1; }
function home_url() { return untrailingslashit( (string) get_option( 'home' ) ); }
function site_url() { return untrailingslashit( (string) get_option( 'siteurl' ) ); }
/**
 * Medios como _wp_upload_dir(): el principal en uploads/, cada sitio en uploads/sites/<id>. La URL sale de
 * WP_CONTENT_URL, que es la del sitio que atiende la petición (el principal en estas pruebas), aunque se haya cambiado
 * de sitio con switch_to_blog(): así lo hace WordPress.
 */
function wp_upload_dir( $time = null, $create = true ) {
	$blog = get_current_blog_id();
	$suffix = is_multisite() && 1 !== $blog ? '/sites/' . $blog : '';
	$siteurl = is_multisite() ? get_blog_option( 1, 'siteurl' ) : get_option( 'siteurl' );
	return array( 'basedir' => WP_CONTENT_DIR . '/uploads' . $suffix, 'baseurl' => untrailingslashit( (string) $siteurl ) . '/wp-content/uploads' . $suffix );
}
function get_theme_root( $stylesheet = '' ) { return WP_CONTENT_DIR . '/themes'; }
function plugin_basename( $file ) { return ltrim( substr( wp_normalize_path( $file ), strlen( wp_normalize_path( WP_PLUGIN_DIR ) ) ), '/' ); }

/* ---- Utilidades de WordPress ---- */
function untrailingslashit( $value ) { return rtrim( (string) $value, '/\\' ); }
function trailingslashit( $path ) { return rtrim( (string) $path, '/\\' ) . '/'; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_file_name( $value ) { return trim( preg_replace( '/[^A-Za-z0-9._-]/', '', str_replace( array( '/', '\\' ), '', (string) $value ) ), '.-_' ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( (string) $value ) ), '-' ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', (string) $path ); }
function wp_mkdir_p( $path ) { return is_dir( $path ) || @mkdir( $path, 0700, true ); }
function wp_basename( $path ) { return basename( (string) $path ); }
function get_bloginfo( $key ) { return '6.8'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_generate_uuid4() { return sprintf( '%08x-%04x-4%03x-8%03x-%012x', random_int( 0, 0xffffffff ), random_int( 0, 0xffff ), random_int( 0, 0xfff ), random_int( 0, 0xfff ), random_int( 0, 0xffffffffffff ) ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function current_time( $format ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_cache_flush() { return true; }
function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['digi']['scheduled']; }
function wp_clear_scheduled_hook( $hook, $args = array() ) { return 0; }
function wp_schedule_single_event( $timestamp, $hook, $args = array() ) { return true; }
function wp_unschedule_event( $timestamp, $hook, $args = array() ) { return true; }
function check_admin_referer( $action ) { if ( ( $_REQUEST['_wpnonce'] ?? '' ) !== 'nonce-' . $action ) throw new Digi_Die( 'Nonce inválido para ' . $action ); return 1; }
function wp_die( $message = '' ) { throw new Digi_Die( (string) $message ); }
function wp_safe_redirect( $url ) { throw new Digi_Redirect( $url ); }
function wp_get_referer() { return ''; }
function add_query_arg( $args, $url ) { $pairs = array(); foreach ( (array) $args as $key => $value ) $pairs[] = $key . '=' . $value; return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $pairs ); }
function admin_url( $path = '' ) { return 'https://red.test/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return 'https://red.test/wp-admin/network/' . $path; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['digi']['actions'][ $hook ][] = $callback; return true; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['digi']['filters'][ $hook ][] = $callback; return true; }
function apply_filters( $hook, $value, ...$args ) { foreach ( $GLOBALS['digi']['filters'][ $hook ] ?? array() as $callback ) $value = call_user_func( $callback, $value, ...$args ); return $value; }
function size_format( $bytes ) { return $bytes . ' B'; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' selected' : ''; }
function disabled( $a, $b, $echo = true ) { return $a === $b ? ' disabled' : ''; }

/* ---- REST ---- */
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
	public function get_query_params() { return array(); }
	public function get_json_params() { return $this->params; }
	public function get_body_params() { return array(); }
	#[\ReturnTypeWillChange]
	public function offsetExists( $key ) { return isset( $this->params[ $key ] ); }
	#[\ReturnTypeWillChange]
	public function offsetGet( $key ) { return $this->params[ $key ] ?? null; }
	#[\ReturnTypeWillChange]
	public function offsetSet( $key, $value ) { $this->params[ $key ] = $value; }
	#[\ReturnTypeWillChange]
	public function offsetUnset( $key ) { unset( $this->params[ $key ] ); }
}
/** Despacho de una ruta registrada como WP_REST_Server: método, patrón, valores por defecto y permiso. */
function digi_rest( $method, $path, array $params = array() ) {
	foreach ( $GLOBALS['digi']['routes'] as $pattern => $handlers ) {
		if ( ! preg_match( '@^' . $pattern . '$@', $path, $matches ) ) continue;
		foreach ( $handlers as $handler ) {
			if ( ! in_array( $method, array_map( 'trim', explode( ',', $handler['methods'] ) ), true ) ) continue;
			foreach ( (array) ( $handler['args'] ?? array() ) as $name => $arg ) if ( ! array_key_exists( $name, $params ) && array_key_exists( 'default', $arg ) ) $params[ $name ] = $arg['default'];
			foreach ( $matches as $key => $value ) if ( is_string( $key ) ) $params[ $key ] = $value;
			$request = new WP_REST_Request( $params );
			if ( true !== call_user_func( $handler['permission_callback'], $request ) ) return array( 403, array( 'code' => 'rest_forbidden' ) );
			$result = call_user_func( $handler['callback'], $request );
			if ( is_wp_error( $result ) ) return array( (int) ( $result->get_error_data()['status'] ?? 500 ), array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );
			return array( $result->get_status(), $result->get_data() );
		}
	}
	return array( 404, array( 'code' => 'rest_no_route' ) );
}

require_once __DIR__ . '/../../digitalisimo-backups/includes/class-backups.php';
require_once __DIR__ . '/../../digitalisimo-backups/includes/class-backups-rest.php';
Digitalisimo_Backups_Rest::routes();

/** Hace avanzar la cola hasta que el trabajo termina; devuelve su vista pública. */
function digi_drive( $id ) {
	for ( $i = 0; $i < 30; $i++ ) {
		$view = Digitalisimo_Backups::service_job( $id );
		digi_assert( ! is_wp_error( $view ), 'El trabajo ' . $id . ' no se pudo consultar: ' . ( is_wp_error( $view ) ? $view->get_error_message() : '' ) );
		if ( $view['terminal'] ) return $view;
		$tick = Digitalisimo_Backups::service_tick();
		digi_assert( ! is_wp_error( $tick ) && 'error' !== $tick['result'], 'La cola no avanzó: ' . json_encode( is_wp_error( $tick ) ? $tick->get_error_message() : $tick ) );
	}
	throw new RuntimeException( 'FALLA: el trabajo ' . $id . ' no terminó: ' . json_encode( Digitalisimo_Backups::service_job( $id ) ) );
}
function digi_admin_post( $callback, $action, array $post ) {
	$_POST = $post;
	$_REQUEST = array_merge( $post, array( '_wpnonce' => 'nonce-' . $action ) );
	try {
		call_user_func( $callback );
	} catch ( Digi_Redirect $redirect ) {
		return $redirect->query;
	}
	throw new RuntimeException( 'FALLA: el handler terminó sin redirigir.' );
}
