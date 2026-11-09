<?php
/**
 * Destinos remotos de DIGITALÍSIMO Backups en un WordPress real (Playground, SQLite, php-wasm) contra servidores reales
 * del servidor de desarrollo: S3 compatible (MinIO), FTPS explícito (pyftpdlib) y WebDAV sobre HTTPS (wsgidav), con TLS
 * verificado por una CA de prueba pegada en la conexión. Escribe cada resultado en result.txt; una línea «FALLA»
 * detiene el arnés. Todo pasa por los handlers admin-post reales con nonce y capacidad.
 *
 * Para cada destino:
 *   D1  la conexión se guarda por el formulario real (la de WebDAV como conexión adicional con nombre) y ningún secreto
 *       queda en claro en la base de datos;
 *   D2  «Probar conexión» (admin-post) sube, verifica, descarga, lista y borra un archivo pequeño;
 *   D3  «Crear respaldo» hacia el destino encola el trabajo; los ticks lo llevan a «success» sólo tras subir a un
 *       temporal, verificar la copia remota y publicarla; el historial guarda el recibo sin secretos;
 *   D4  se cambia el sitio, se borra la copia local, «Traer al almacén» la descarga y la verifica por SHA-256, la
 *       descarga al navegador da el mismo archivo y la restauración por admin-post devuelve el sitio al respaldo;
 *   D5  borrar el respaldo del historial lo borra del destino.
 * En una red (modo «multisite»), D1-D5 con MinIO desde la administración de red (el almacén en sitemeta).
 */
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', '/var/digitalisimo-backups-probe' );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
$dir = '/digitalisimo-probe';
$mode = trim( (string) @file_get_contents( $dir . '/mode' ) ) ?: 'single';
$config = json_decode( (string) file_get_contents( $dir . '/config.json' ), true );
$result = $dir . '/result.txt';
$log = function( $line ) use ( $result ) { file_put_contents( $result, $line . "\n", FILE_APPEND ); };
$call = function( $method, ...$args ) { $r = new ReflectionMethod( 'Digitalisimo_Backups', $method ); if ( PHP_VERSION_ID < 80100 ) $r->setAccessible( true ); return $r->invokeArgs( null, $args ); };
$check = function( $ok, $what ) use ( $log ) { if ( ! $ok ) throw new RuntimeException( $what ); $log( 'ok · ' . $what ); };
class Probe_Redirect extends Error { public $url; public function __construct( $url ) { parent::__construct( 'redirect' ); $this->url = $url; } }
class Probe_Die extends Error {}
add_filter( 'wp_redirect', function( $location ) { throw new Probe_Redirect( $location ); }, 1 );
add_filter( 'wp_die_handler', function() { return function( $message ) { throw new Probe_Die( is_string( $message ) ? $message : 'wp_die' ); }; } );
$post = function( $callback, $action, $fields, $nonce = null ) {
	$_POST = wp_slash( array_merge( array( '_wpnonce' => $nonce ?? wp_create_nonce( $action ), 'action' => $action ), $fields ) );
	$_REQUEST = $_POST;
	try {
		call_user_func( $callback );
	} catch ( Probe_Redirect $redirect ) {
		$query = array();
		parse_str( (string) wp_parse_url( $redirect->url, PHP_URL_QUERY ), $query );
		return array( 'ok' === ( $query['backup'] ?? '' ), rawurldecode( (string) ( $query['message'] ?? '' ) ), $redirect->url );
	} finally {
		$_POST = array();
		$_REQUEST = array();
	}
	throw new RuntimeException( 'El handler ' . $action . ' no redirigió.' );
};
add_filter( 'pre_http_request', function( $pre, $args, $url ) { return false !== strpos( (string) $url, 'wp-cron.php' ) ? new WP_Error( 'digitalisimo_probe_cron', 'WP-Cron lo simula la sonda.' ) : $pre; }, 10, 3 );
$timer = microtime( true );
$elapsed = function() use ( $timer ) { return round( microtime( true ) - $timer ) . ' s'; };
try {
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', is_multisite() );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( $activated->get_error_message() );
	if ( ! class_exists( 'Digitalisimo_Backups' ) ) require_once WP_PLUGIN_DIR . '/digitalisimo-backups/digitalisimo-backups.php';
	if ( ! has_action( 'admin_post_' . Digitalisimo_Backups::ACTION_SAVE_CONNECTION ) ) Digitalisimo_Backups::init();
	wp_set_current_user( 1 );
	$network = is_multisite();
	$context = $network ? array( 'digitalisimo_backups_network' => '1' ) : array();
	$curl = function_exists( 'curl_version' ) ? curl_version() : array();
	$log( 'Modo ' . $mode . ' · PHP ' . PHP_VERSION . ' · WordPress ' . get_bloginfo( 'version' ) . ( $network ? ' · Multisite' : '' ) . ' · cURL ' . ( $curl['version'] ?? 'no' ) . ' · cifrado de credenciales: ' . ( Digitalisimo_Backups_Credentials::can_encrypt() ? 'sí' : 'no' ) );
	$pem = (string) $config['ca'];
	$secrets = array( $config['s3']['secret'], $config['ftp']['password'], $config['webdav']['password'] );
	$leak = function( $text ) use ( $secrets ) { foreach ( $secrets as $secret ) if ( false !== strpos( (string) $text, $secret ) ) return true; return false; };
	$primary = array(
		'destination' => 'local', 'retention' => '10',
		's3compat_provider' => 'other', 's3compat_endpoint' => $config['s3']['endpoint'], 's3compat_signing_region' => $config['s3']['region'], 's3compat_bucket' => $config['s3']['bucket'], 's3compat_access_key' => $config['s3']['access'], 's3compat_secret_key' => $config['s3']['secret'], 's3compat_path' => 'playground/' . $mode, 's3compat_style' => 'path', 's3compat_ca' => $pem, 's3compat_name' => 'MinIO de prueba',
		'ftp_host' => $config['ftp']['host'], 'ftp_port' => $config['ftp']['port'], 'ftp_security' => 'explicit', 'ftp_user' => $config['ftp']['user'], 'ftp_password' => $config['ftp']['password'], 'ftp_path' => '/playground/' . $mode, 'ftp_passive' => 'passive', 'ftp_ca' => $pem, 'ftp_name' => 'FTPS de prueba',
	);
	// D1: conexiones principales por «Guardar conexiones» y WebDAV como conexión adicional con nombre.
	list( $ok, $message ) = $post( array( 'Digitalisimo_Backups', 'save' ), Digitalisimo_Backups::ACTION_SAVE, array( 'digitalisimo_backups' => $primary ) + $context );
	$check( $ok, 'D1 conexiones S3 compatible y FTPS guardadas: ' . $message );
	$destinations = array( 's3compat' => 'S3 compatible (MinIO)' );
	if ( 'single' === $mode ) {
		$destinations['ftp'] = 'FTPS explícito (pyftpdlib)';
		list( $ok, $message ) = $post( array( 'Digitalisimo_Backups', 'save_connection' ), Digitalisimo_Backups::ACTION_SAVE_CONNECTION, array( 'connection_type' => 'webdav', 'connection_name' => 'WebDAV de prueba', 'digitalisimo_backups_connection' => array( 'webdav_url' => $config['webdav']['url'], 'webdav_user' => $config['webdav']['user'], 'webdav_password' => $config['webdav']['password'], 'webdav_path' => '/playground', 'webdav_ca' => $pem ) ) + $context );
		$webdav = (string) key( Digitalisimo_Backups_Destinations::connections() );
		$check( $ok && Digitalisimo_Backups_Destinations::is_connection_id( $webdav ), 'D1 WebDAV guardado como conexión adicional «WebDAV de prueba»: ' . $message );
		$destinations[ $webdav ] = 'WebDAV sobre HTTPS (wsgidav, conexión adicional)';
	}
	global $wpdb;
	$table = $network ? $wpdb->sitemeta : $wpdb->options;
	$dump = implode( "\n", (array) $wpdb->get_col( $network ? "SELECT meta_value FROM {$wpdb->sitemeta}" : "SELECT option_value FROM {$wpdb->options}" ) );
	$check( ! $leak( $dump ) && ! $leak( serialize( get_option( Digitalisimo_Backups::OPTION ) ) ) && ! $leak( serialize( get_option( Digitalisimo_Backups_Destinations::CONNECTIONS ) ) ), 'D1 ninguna contraseña ni Secret key en claro en ' . $table );
	$ready = $call( 'manual_destinations', $call( 'settings' ) );
	foreach ( array_keys( $destinations ) as $id ) $check( isset( $ready[ $id ] ), 'D1 «' . $ready[ $id ] . '» aparece como destino listo en Backups' );

	foreach ( $destinations as $id => $label ) {
		$started = microtime( true );
		// D2: «Probar conexión» por admin-post (sin JavaScript), con lo guardado.
		list( $ok, $message ) = $post( array( 'Digitalisimo_Backups', 'test_destination' ), Digitalisimo_Backups::ACTION_TEST_DESTINATION, array( 'digitalisimo_backups_test' => $id ) + $context, wp_create_nonce( Digitalisimo_Backups::ACTION_SAVE ) );
		$check( $ok && ! $leak( $message ), 'D2 ' . $label . ' · Probar conexión: ' . $message );
		// D3: «Crear respaldo» hacia el destino y ticks hasta terminar.
		update_option( 'digitalisimo_destinos_marca', 'antes-' . $id );
		$known = array_column( Digitalisimo_Backups_Jobs::all(), 'id' );
		list( $ok, $message ) = $post( array( 'Digitalisimo_Backups', 'run' ), Digitalisimo_Backups::ACTION_RUN, array( 'digitalisimo_backup_scope' => 'installation', 'digitalisimo_backup_destination' => $id ) + $context );
		$check( $ok, 'D3 ' . $label . ' · respaldo encolado: ' . $message );
		$job = null;
		foreach ( Digitalisimo_Backups_Jobs::all() as $candidate ) if ( ! in_array( $candidate['id'], $known, true ) ) $job = $candidate;
		$statuses = array();
		for ( $i = 0; $i < 300 && $job && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); $i++ ) {
			if ( (int) $job['retry_at'] > time() ) throw new RuntimeException( 'D3 el trabajo espera un reintento: ' . ( $job['error']['message'] ?? '' ) );
			Digitalisimo_Backups_Jobs::tick();
			$job = Digitalisimo_Backups_Jobs::get( $job['id'] );
			$statuses[ $job['status'] ] = true;
		}
		$check( 'success' === ( $job['status'] ?? '' ) && isset( $statuses['transferring'] ), 'D3 ' . $label . ' · trabajo «success» después de «transferring»: ' . ( $job['error']['message'] ?? '' ) );
		$file = basename( $job['spec']['file'] );
		$row = $call( 'history_item', $file );
		$local = rtrim( Digitalisimo_Backups::storage_directory(), '/' ) . '/' . $file;
		$sha = hash_file( 'sha256', $local );
		$check( 'ok' === ( $row['transfer'] ?? '' ) && $id === $row['destination'] && $sha === ( $row['remote']['sha256'] ?? '' ) && ! $leak( json_encode( $row ) ), 'D3 ' . $label . ' · historial con el recibo verificado (' . size_format( filesize( $local ) ) . ', ' . json_encode( array_diff_key( $row['remote'], array( 'sha256' => 1 ) ) ) . ')' );
		$listed = array_column( Digitalisimo_Backups::destination( $id )->list(), 'name' );
		$check( in_array( $file, $listed, true ) && ! preg_grep( '/\.part-/', $listed ), 'D3 ' . $label . ' · el destino lo lista con su nombre y sin temporales' );
		// D4: cambio, copia local borrada, «Traer al almacén», descarga y restauración.
		update_option( 'digitalisimo_destinos_marca', 'despues-' . $id );
		unlink( $local );
		list( $ok, $message ) = $post( array( 'Digitalisimo_Backups', 'fetch_request' ), Digitalisimo_Backups::ACTION_FETCH, array( 'file' => $file ) + $context );
		$check( $ok && is_file( $local ) && $sha === hash_file( 'sha256', $local ), 'D4 ' . $label . ' · «Traer al almacén»: ' . $message );
		set_error_handler( function( $number, $text ) { return false !== strpos( $text, 'Cannot modify header information' ); } );
		ob_start();
		try { $call( 'download_remote', $row ); } finally { $download = ob_get_clean(); restore_error_handler(); }
		$check( $sha === hash( 'sha256', $download ), 'D4 ' . $label . ' · la descarga al navegador es el respaldo (SHA-256)' );
		unset( $download );
		list( $ok, $message ) = $post( array( 'Digitalisimo_Backups', 'restore_request' ), Digitalisimo_Backups::ACTION_RESTORE, array( 'backup_file' => $file, 'confirmation' => 'RESTAURAR' ) + $context );
		wp_cache_flush();
		$check( $ok && 'antes-' . $id === get_option( 'digitalisimo_destinos_marca' ), 'D4 ' . $label . ' · restaurado el respaldo traído del destino: ' . $message );
		wp_set_current_user( 1 );
		// D5: borrar del historial lo borra del destino.
		list( $ok, $message ) = $post( array( 'Digitalisimo_Backups', 'delete_backup' ), Digitalisimo_Backups::ACTION_DELETE, array( 'file' => $file ) + $context );
		$check( $ok && false === Digitalisimo_Backups::destination( $id )->size( (array) $row['remote'] ), 'D5 ' . $label . ' · borrado del historial y del destino · ' . round( microtime( true ) - $started ) . ' s' );
	}
	$log( 'TERMINADO · ' . $elapsed() );
} catch ( Throwable $e ) {
	$log( 'FALLA · ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
}
