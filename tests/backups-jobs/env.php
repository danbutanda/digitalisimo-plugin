<?php
/**
 * Entorno de prueba de la cola de respaldos: un WordPress sintético en disco, un almacén privado fuera de él,
 * opciones persistidas en un archivo (para que dos procesos compartan historial) y un `$wpdb` que registra cada
 * consulta. Lo usan tests/backups-job-*.php y el trabajador de tests/backups-jobs/worker.php.
 *
 * Antes de incluirlo se puede fijar $GLOBALS['bk_root'] (raíz existente) y $GLOBALS['bk_multisite'].
 */
if ( empty( $GLOBALS['bk_root'] ) ) $GLOBALS['bk_root'] = sys_get_temp_dir() . '/digitalisimo-jobs-' . bin2hex( random_bytes( 4 ) );
$bk_root = $GLOBALS['bk_root'];
if ( ! is_dir( $bk_root . '/public/wp-content/plugins' ) ) mkdir( $bk_root . '/public/wp-content/plugins', 0700, true );
define( 'ABSPATH', $bk_root . '/public/' );
define( 'WP_CONTENT_DIR', $bk_root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $bk_root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $bk_root . '/private' );
define( 'DIGITALISIMO_BACKUPS_VERSION', '9.9.9-test' );
define( 'DB_NAME', 'wordpress_test' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
$_SERVER['DOCUMENT_ROOT'] = $bk_root . '/public';
$GLOBALS['bk_multisite'] = $GLOBALS['bk_multisite'] ?? false;
$GLOBALS['bk_can'] = true;
$GLOBALS['bk_cron'] = array();
$GLOBALS['bk_spawned'] = 0;
$GLOBALS['bk_filters'] = array();
$GLOBALS['bk_option_log'] = array();
$GLOBALS['bk_remote'] = null;
$GLOBALS['bk_remote_log'] = array();
$GLOBALS['bk_blog_id'] = 1;
$GLOBALS['bk_blog_stack'] = array();

/* ---- WordPress sintético ---- */
function bk_tree() {
	return array(
		'wp-config.php' => "<?php define( 'DB_NAME', 'wordpress_test' );\n",
		'wp-load.php' => "<?php // load\n",
		'wp-settings.php' => "<?php // settings\n",
		'index.php' => "<?php // index\n",
		'wp-admin/index.php' => "<?php // admin\n",
		'wp-includes/version.php' => "<?php \$wp_version = '6.8';\n",
		'wp-content/plugins/example/example.php' => "<?php /* Plugin Name: Ejemplo */\n",
		'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
		'wp-content/uploads/2026/11/foto.jpg' => str_repeat( "\xff\xd8\xff\xe0JFIF", 4096 ),
		'wp-content/uploads/2026/11/notas ñ.txt' => "UTF-8 en el nombre\n",
	);
}
foreach ( bk_tree() as $name => $content ) {
	$path = $bk_root . '/public/' . $name;
	if ( ! is_dir( dirname( $path ) ) ) mkdir( dirname( $path ), 0700, true );
	if ( ! is_file( $path ) ) file_put_contents( $path, $content );
}

/* ---- Opciones persistidas, con la caché de cada petición de WordPress ---- */
// get_option() guarda lo leído en la caché de la petición (también lo que no existe, como `notoptions`), y otro proceso
// escribe en la base de datos sin tocar esa caché: bk_external_update_option() simula ese otro proceso.
$GLOBALS['bk_option_cache'] = array();
function bk_options_path() { return $GLOBALS['bk_root'] . '/options.json'; }
function bk_options_load() { $raw = @file_get_contents( bk_options_path() ); $data = $raw ? json_decode( $raw, true ) : array(); return is_array( $data ) ? $data : array(); }
function bk_options_save( $options ) { file_put_contents( bk_options_path(), json_encode( $options ) ); }
function bk_db_access( $what, $key ) {
	$GLOBALS['bk_option_log'][] = array( $what, $key, ! empty( $GLOBALS['wpdb']->in_snapshot ) );
	if ( ! empty( $GLOBALS['wpdb']->in_snapshot ) ) $GLOBALS['wpdb']->snapshot_violations[] = $what . ':' . $key;
}
function get_option( $key, $default = false ) {
	bk_db_access( 'get', $key );
	if ( ! array_key_exists( $key, $GLOBALS['bk_option_cache'] ) ) {
		$options = bk_options_load();
		$GLOBALS['bk_option_cache'][ $key ] = array_key_exists( $key, $options ) ? array( $options[ $key ] ) : null;
	}
	return null === $GLOBALS['bk_option_cache'][ $key ] ? $default : $GLOBALS['bk_option_cache'][ $key ][0];
}
function update_option( $key, $value, $autoload = null ) { bk_db_access( 'update', $key ); $options = bk_options_load(); $options[ $key ] = $value; bk_options_save( $options ); $GLOBALS['bk_option_cache'][ $key ] = array( $value ); return true; }
function add_option( $key, $value = '', $deprecated = '', $autoload = 'yes' ) { $options = bk_options_load(); if ( array_key_exists( $key, $options ) ) return false; return update_option( $key, $value ); }
function delete_option( $key ) { bk_db_access( 'delete', $key ); $options = bk_options_load(); unset( $options[ $key ] ); bk_options_save( $options ); $GLOBALS['bk_option_cache'][ $key ] = null; return true; }
function bk_external_update_option( $key, $value ) { $options = bk_options_load(); $options[ $key ] = $value; bk_options_save( $options ); }
function wp_cache_get( $key, $group = '' ) {
	if ( 'options' !== $group ) return false;
	if ( 'notoptions' === $key ) return array_fill_keys( array_keys( array_filter( $GLOBALS['bk_option_cache'], 'is_null' ) ), true );
	return isset( $GLOBALS['bk_option_cache'][ $key ] ) ? $GLOBALS['bk_option_cache'][ $key ][0] : false;
}
function wp_cache_set( $key, $value, $group = '' ) {
	if ( 'options' === $group && 'notoptions' === $key ) foreach ( array_keys( array_filter( $GLOBALS['bk_option_cache'], 'is_null' ) ) as $name ) if ( ! isset( $value[ $name ] ) ) unset( $GLOBALS['bk_option_cache'][ $name ] );
	return true;
}
function wp_cache_delete( $key, $group = '' ) { if ( 'options' === $group ) unset( $GLOBALS['bk_option_cache'][ $key ] ); return true; }
function wp_load_alloptions() { return array(); }
function get_site_option( $key, $default = false ) { return get_option( $key, $default ); }
function update_site_option( $key, $value ) { return update_option( $key, $value ); }

/* ---- Funciones de WordPress usadas por Backups ---- */
function wp_parse_args( $args, $defaults = array() ) { return array_merge( (array) $defaults, (array) $args ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function current_time( $format ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_basename( $path ) { return basename( $path ); }
function sanitize_title( $value ) { return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( (string) $value ) ), '-' ); }
function sanitize_file_name( $value ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url() { return 'https://ejemplo.test'; }
function site_url() { return 'https://ejemplo.test'; }
function get_bloginfo( $key ) { return '6.8'; }
function is_multisite() { return (bool) $GLOBALS['bk_multisite']; }
/** Sitio en curso: 1 es el principal; una prueba fija $GLOBALS['bk_blog_id'] para simular un subsitio de la red. */
function get_current_blog_id() { return (int) ( $GLOBALS['bk_blog_id'] ?? 1 ); }
function get_main_site_id() { return 1; }
function is_main_site() { return 1 === get_current_blog_id(); }
function is_network_admin() { return false; }
function switch_to_blog( $id ) { $GLOBALS['bk_blog_stack'][] = get_current_blog_id(); $GLOBALS['bk_blog_id'] = (int) $id; return true; }
function restore_current_blog() { $GLOBALS['bk_blog_id'] = array_pop( $GLOBALS['bk_blog_stack'] ) ?? 1; return true; }
function wp_delete_file( $file ) { return @unlink( $file ); }
function wp_mkdir_p( $path ) { return is_dir( $path ) || @mkdir( $path, 0700, true ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function wp_generate_uuid4() { return Digitalisimo_Backups_Job_Store::new_id(); }
function wp_generate_password( $length = 12 ) { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
function get_current_user_id() { return 17; }
function current_user_can( $capability ) { return $GLOBALS['bk_can'] && ( is_multisite() ? 'manage_network_options' === $capability : 'manage_options' === $capability ); }
function check_ajax_referer( $action, $name ) { if ( 'ok' !== ( $_POST[ $name ] ?? '' ) || ( $GLOBALS['bk_nonce_action'] ?? $action ) !== $action ) throw new RuntimeException( 'NONCE:' . $action ); return 1; }
function check_admin_referer( $action ) { if ( 'ok' !== ( $_POST['_wpnonce'] ?? '' ) ) throw new RuntimeException( 'NONCE:' . $action ); return 1; }
function wp_send_json_success( $data = null ) { throw new RuntimeException( 'OK:' . json_encode( $data ) ); }
function wp_send_json_error( $data = null, $status = 200 ) { throw new RuntimeException( 'ERROR:' . $status . ':' . json_encode( $data ) ); }
function wp_die( $message = '' ) { throw new RuntimeException( 'DIE:' . $message ); }
function wp_safe_redirect( $url ) { throw new RuntimeException( 'REDIRECT:' . $url ); }
function wp_get_referer() { return ''; }
function add_query_arg( $args, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function admin_url( $path = '' ) { return 'https://ejemplo.test/wp-admin/' . $path; }
function network_admin_url( $path = '' ) { return 'https://ejemplo.test/wp-admin/network/' . $path; }
function size_format( $bytes ) { return $bytes . ' B'; }
function add_action() { return true; }
function current_filter() { return $GLOBALS['bk_current_filter'] ?? ''; }
function apply_filters( $hook, $value, ...$args ) { return isset( $GLOBALS['bk_filters'][ $hook ] ) ? call_user_func( $GLOBALS['bk_filters'][ $hook ], $value, ...$args ) : $value; }
function wp_next_scheduled( $hook, $args = array() ) { $times = array_keys( array_filter( $GLOBALS['bk_cron'], function( $event ) use ( $hook ) { return $event === $hook; } ) ); return $times ? min( array_map( 'intval', $times ) ) : false; }
function wp_schedule_single_event( $timestamp, $hook, $args = array() ) { $GLOBALS['bk_cron'][ (string) $timestamp ] = $hook; return true; }
function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) { $GLOBALS['bk_cron'][ (string) $timestamp ] = $hook; return true; }
function wp_unschedule_event( $timestamp, $hook, $args = array() ) { unset( $GLOBALS['bk_cron'][ (string) $timestamp ] ); return true; }
function wp_clear_scheduled_hook( $hook, $args = array() ) { $GLOBALS['bk_cron'] = array_filter( $GLOBALS['bk_cron'], function( $event ) use ( $hook ) { return $event !== $hook; } ); return 0; }
function spawn_cron() { $GLOBALS['bk_spawned']++; return true; }
function wp_cache_flush() { $GLOBALS['bk_option_cache'] = array(); return true; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
class WP_Error {
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }

/* ---- Peticiones remotas simuladas (Google Drive) ---- */
function bk_remote( $method, $url, $args ) {
	$GLOBALS['bk_remote_log'][] = array( $method, $url );
	if ( is_callable( $GLOBALS['bk_remote'] ) ) return call_user_func( $GLOBALS['bk_remote'], $method, $url, $args );
	return new WP_Error( 'sin_red', 'Sin red en la prueba.' );
}
function wp_remote_post( $url, $args = array() ) { return bk_remote( 'POST', $url, $args ); }
function wp_remote_get( $url, $args = array() ) { return bk_remote( 'GET', $url, $args ); }
function wp_remote_request( $url, $args = array() ) { return bk_remote( $args['method'] ?? 'GET', $url, $args ); }
function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $response, $header ) { return is_array( $response ) ? (string) ( $response['headers'][ $header ] ?? '' ) : ''; }
/** Google Drive que acepta la subida y devuelve la copia íntegra al verificar. */
function bk_google_ok( $hook = null ) {
	return function( $method, $url, $args ) use ( $hook ) {
		if ( $hook ) call_user_func( $hook, $method, $url, $args );
		if ( 'POST' === $method && false !== strpos( $url, 'oauth2' ) ) return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'access_token' => 'token-de-prueba' ) ) );
		if ( 'POST' === $method ) return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'location' => 'https://upload.test/session/1' ), 'body' => '' );
		if ( 'PUT' === $method ) { $GLOBALS['bk_uploaded'] = ( $GLOBALS['bk_uploaded'] ?? '' ) . $args['body']; return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'id' => 'archivo-remoto' ) ) ); }
		if ( 'GET' === $method ) { file_put_contents( $args['filename'], $GLOBALS['bk_uploaded'] ?? '' ); return array( 'response' => array( 'code' => 200 ) ); }
		return array( 'response' => array( 'code' => 204 ), 'body' => '' );
	};
}

/* ---- Base de datos sintética que registra cada consulta ---- */
class Digitalisimo_Jobs_Test_DB {
	public $base_prefix = 'wp_';
	public $prefix = 'wp_';
	public $last_error = '';
	public $queries = array();
	public $engine = 'InnoDB';
	public $in_snapshot = false;
	public $snapshot_violations = array();
	public $exports = 0;
	public $on_select = null;
	public $tables = array( 'wp_options' => 3, 'wp_posts' => 2 );
	public function get_col( $query ) { $this->queries[] = $query; if ( 'SHOW TABLES' === $query ) $this->exports++; return array_keys( $this->tables ); }
	public function prepare( $query, $value ) { return str_replace( '%s', "'" . $value . "'", $query ); }
	public function get_row( $query, $format = null ) {
		$this->queries[] = $query;
		$this->last_error = '';
		if ( false !== strpos( $query, 'SHOW TABLE STATUS' ) ) return array( 'Engine' => $this->engine );
		preg_match( '/`([^`]+)`/', $query, $m );
		return array( $m[1], 'CREATE TABLE `' . $m[1] . '` (`id` bigint, `v` longtext)' );
	}
	public function get_results( $query, $format = null ) {
		$this->queries[] = $query;
		$this->last_error = '';
		if ( false !== strpos( $query, 'SHOW INDEX' ) ) return array( array( 'Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Seq_in_index' => 1 ) );
		if ( 0 === strpos( $query, 'SELECT * FROM' ) ) {
			if ( is_callable( $this->on_select ) ) call_user_func( $this->on_select, $query );
			preg_match( '/`([^`]+)`/', $query, $m );
			if ( false !== strpos( $query, 'LIMIT 0,' ) ) {
				$rows = array();
				for ( $i = 1; $i <= ( $this->tables[ $m[1] ] ?? 0 ); $i++ ) $rows[] = array( 'id' => $i, 'v' => $m[1] . " fila $i con 'comillas' y ñ" );
				return $rows;
			}
			return array();
		}
		return array();
	}
	public function db_version() { return '8.0'; }
	public function query( $sql ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		if ( 0 === strpos( $sql, 'START TRANSACTION' ) || 0 === strpos( $sql, 'LOCK TABLES' ) ) $this->in_snapshot = true;
		if ( in_array( $sql, array( 'COMMIT', 'ROLLBACK', 'UNLOCK TABLES' ), true ) ) $this->in_snapshot = false;
		return 1;
	}
}
$GLOBALS['wpdb'] = new Digitalisimo_Jobs_Test_DB();

require_once __DIR__ . '/../../digitalisimo-backups/includes/class-backups.php';

/* ---- Utilidades de las pruebas ---- */
function bk_fail( $message ) { throw new RuntimeException( 'FALLA: ' . $message ); }
function bk_assert( $condition, $message ) { if ( ! $condition ) bk_fail( $message ); }
function bk_storage() { return DIGITALISIMO_BACKUPS_STORAGE_DIR; }
function bk_job( $id ) { return Digitalisimo_Backups_Jobs::get( $id ); }
function bk_history() { return (array) get_option( Digitalisimo_Backups::HISTORY, array() ); }
/** Espera nula entre reintentos, para no depender del reloj. */
function bk_no_backoff() { $GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = function() { return 0; }; }
/** El reintento queda listo ya, sin esperar su hora. */
function bk_ready( $id ) {
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id ) { $job = Digitalisimo_Backups_Job_Store::read( $id ); $job['retry_at'] = 0; Digitalisimo_Backups_Job_Store::write( $job ); } );
}
function bk_manual( $destination = 'local', $policy = 'manual' ) {
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => $policy, 'destination' => $destination, 'retain' => 5, 'retention' => 'global', 'via' => 'test' ) ) );
	return $queued['job'];
}
/** Ticks de un paso hasta un estado terminal (o el límite). Devuelve los estados observados. */
function bk_run_until_done( $id, $limit = 40 ) {
	$seen = array();
	for ( $i = 0; $i < $limit; $i++ ) {
		$job = bk_job( $id );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return $seen;
		Digitalisimo_Backups_Jobs::tick( 0 );
		$job = bk_job( $id );
		$seen[] = $job['status'] . ':' . $job['phase'];
		bk_assert_no_unverified_final( $job );
	}
	bk_fail( 'El trabajo no terminó en ' . $limit . ' ticks: ' . implode( ', ', $seen ) );
}
/** Ningún nombre de respaldo existe en el almacén antes de que el ZIP pase la verificación. */
function bk_assert_no_unverified_final( $job ) {
	if ( ! in_array( $job['phase'], array( 'preflight', 'database', 'pack', 'verify' ), true ) || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return;
	bk_assert( ! is_file( bk_storage() . '/' . $job['spec']['file'] ), 'El nombre final existe antes de verificar el ZIP (' . $job['phase'] . ').' );
}
/** Simula un proceso muerto a mitad de un paso: arrendamiento de este servidor puesto y sin cerrojo. */
function bk_kill_in( $id, $phase, array $partials = array() ) {
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id, $phase, $partials ) {
		$job = Digitalisimo_Backups_Job_Store::read( $id );
		$job['phase'] = $phase;
		$job['status'] = Digitalisimo_Backups_Jobs::status_for( $phase, $job['spec'] );
		$job['retry_at'] = 0;
		$job['lease'] = array( 'owner' => str_repeat( 'd', 32 ), 'host' => Digitalisimo_Backups_Jobs::host(), 'pid' => 999999, 'since' => time() - 30, 'heartbeat_at' => time() - 20 );
		Digitalisimo_Backups_Job_Store::write( $job );
		$staging = Digitalisimo_Backups_Job_Store::staging( $id );
		foreach ( $partials as $name => $content ) file_put_contents( $staging . '/' . $name, $content );
	} );
}
function bk_zip_entries( $path ) {
	$zip = new ZipArchive();
	bk_assert( true === $zip->open( $path, ZipArchive::CHECKCONS ), 'El ZIP no supera CHECKCONS: ' . basename( $path ) );
	$manifest = json_decode( $zip->getFromName( 'manifest.json' ), true );
	$zip->close();
	return $manifest;
}
function bk_cleanup() {
	Digitalisimo_Backups_Job_Store::remove_tree( $GLOBALS['bk_root'] );
}
