<?php
/**
 * Preparación del ensayo E2FIX (paso runPHP del blueprint de backups-e2fix.sh). Activa Backups, registra su almacén
 * privado como lo haría el servidor (sin la constante DIGITALISIMO_BACKUPS_STORAGE_DIR: el origen en /origen-store, el
 * destino en /digi-store), corta el loopback de WP-Cron, escribe el mu-plugin del ensayo (el mismo en los dos sitios:
 * la restauración trae el del origen) y crea una contraseña de aplicación. En el destino registra en el historial el
 * respaldo grande que el arnés dejó en el almacén. Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 *
 * El mu-plugin pone en cada petición los límites del hosting del propietario: max_execution_time=30, memory_limit=64M
 * (también para admin y WP-Cron) y el búfer de salida de PHP activo y sin límite desde el principio, como
 * `output_buffering=On` en el PHP de Webuzo (con 4096 se vacía solo cada 4 KiB y readfile() no se nota). php-wasm no tiene la extensión ssh2: la simula con sus funciones y un envoltorio de flujos «ssh2.sftp» hacia
 * /digi-sftp, que recibe cada escritura entera, como libssh2. La sonda (`?digi_e2=…&token=…`) da el registro del almacén
 * y copia un respaldo del almacén a ese SFTP con Digitalisimo_Backups::destination( 'sftp' )->put(), en una petición web.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	$mode = trim( (string) @file_get_contents( $dir . '/mode.txt' ) );
	$store = 'origin' === $mode ? '/origen-store' : '/digi-store';
	// El registro del almacén, como lo deja Backups la primera vez en ese servidor (aquí, sin DOCUMENT_ROOT, lo fija el ensayo).
	update_option( 'digitalisimo_backups_private_storage', array( 'wordpress_root' => wp_normalize_path( (string) realpath( ABSPATH ) ), 'directory' => $store ), false );
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	if ( realpath( $store ) !== Digitalisimo_Backups::storage_directory() ) throw new RuntimeException( 'El almacén no es ' . $store . ': ' . Digitalisimo_Backups::storage_directory() );
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	$mu = <<<'PHP'
<?php
// Ensayo E2FIX de DIGITALÍSIMO Backups: límites del hosting del propietario, SFTP simulado y sonda del ensayo.
@ini_set( 'max_execution_time', '30' );
@ini_set( 'memory_limit', '64M' );
foreach ( array( 'admin_memory_limit', 'cron_memory_limit', 'image_memory_limit' ) as $digi_e2_filter ) add_filter( $digi_e2_filter, static function () { return '64M'; } );
// output_buffering=On del PHP de Webuzo: el búfer de salida de PHP activo y sin límite desde el principio de cada petición.
ob_start();
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	if ( false === strpos( (string) $url, 'wp-cron.php' ) ) return $pre;
	delete_transient( 'doing_cron' );
	return new WP_Error( 'digi_e2_no_cron', 'Sin loopback de WP-Cron en el ensayo.' );
}, 10, 3 );
if ( ! function_exists( 'ssh2_connect' ) ) {
	define( 'SSH2_FINGERPRINT_SHA1', 1 );
	define( 'SSH2_FINGERPRINT_HEX', 2 );
	define( 'SSH2_FINGERPRINT_RAW', 4 );
	function ssh2_connect( $host, $port ) { return fopen( 'php://memory', 'r+' ); }
	function ssh2_methods_negotiated( $connection ) { return array( 'hostkey' => 'ecdsa-sha2-nistp256' ); }
	function ssh2_fingerprint( $connection, $flags ) { return strtoupper( sha1( 'clave ECDSA del SFTP del ensayo E2FIX' ) ); }
	function ssh2_auth_password( $connection, $user, $password ) { return 'clave-sftp-e2fix' === $password; }
	function ssh2_sftp( $connection ) { return fopen( 'php://memory', 'r+' ); }
	function ssh2_sftp_rename( $sftp, $from, $to ) { return rename( '/digi-sftp' . $from, '/digi-sftp' . $to ); }
	function ssh2_sftp_unlink( $sftp, $path ) { return @unlink( '/digi-sftp' . $path ); }
	function ssh2_sftp_stat( $sftp, $path ) { $stat = @stat( '/digi-sftp' . $path ); return is_array( $stat ) ? array( 'size' => $stat['size'] ) : false; }
	final class Digi_E2fix_Sftp_Stream {
		public static $largest = 0;
		public $context;
		private $handle;
		public function stream_open( $path, $mode, $options, &$opened ) { $this->handle = fopen( '/digi-sftp' . parse_url( $path, PHP_URL_PATH ), $mode ); return (bool) $this->handle; }
		public function stream_read( $count ) { return fread( $this->handle, $count ); }
		public function stream_write( $data ) { self::$largest = max( self::$largest, strlen( $data ) ); return fwrite( $this->handle, $data ); }
		public function stream_eof() { return feof( $this->handle ); }
		public function stream_flush() { return fflush( $this->handle ); }
		public function stream_stat() { return fstat( $this->handle ); }
		public function stream_close() { fclose( $this->handle ); }
		public function stream_set_option( $option, $arg1, $arg2 ) { return false; }
		public function url_stat( $path, $flags ) { return @stat( '/digi-sftp' . parse_url( $path, PHP_URL_PATH ) ); }
	}
	if ( ! in_array( 'ssh2.sftp', stream_get_wrappers(), true ) ) stream_wrapper_register( 'ssh2.sftp', 'Digi_E2fix_Sftp_Stream' );
}
add_action( 'init', static function () {
	if ( empty( $_GET['digi_e2'] ) || ! hash_equals( trim( (string) @file_get_contents( '/digi-probe/token.txt' ) ), (string) ( $_GET['token'] ?? '' ) ) ) return;
	$started = microtime( true );
	try {
		$out = array( 'record' => get_option( 'digitalisimo_backups_private_storage' ), 'directory' => Digitalisimo_Backups::storage_directory() );
		if ( 'sftp' === $_GET['digi_e2'] ) {
			$file = Digitalisimo_Backups::storage_directory() . '/' . basename( (string) ( $_GET['file'] ?? '' ) );
			$settings = array( 'sftp_host' => 'sftp.e2fix.test', 'sftp_port' => '22', 'sftp_user' => 'respaldos', 'sftp_password' => 'clave-sftp-e2fix', 'sftp_path' => '/respaldos', 'sftp_fingerprint' => strtoupper( sha1( 'clave ECDSA del SFTP del ensayo E2FIX' ) ) );
			$out['receipt'] = Digitalisimo_Backups::destination( 'sftp', $settings )->put( $file );
			$out['largest_write'] = Digi_E2fix_Sftp_Stream::$largest;
		}
		$out['ok'] = true;
	} catch ( Throwable $e ) {
		$out = array( 'ok' => false, 'error' => $e->getMessage() );
	}
	$out += array( 'peak_mb' => round( memory_get_peak_usage() / 1048576, 1 ), 'memory_limit' => ini_get( 'memory_limit' ), 'seconds' => round( microtime( true ) - $started, 2 ) );
	while ( ob_get_level() > 0 && ob_end_clean() ) continue;
	header( 'Content-Type: application/json' );
	echo wp_json_encode( $out );
	exit;
}, 1 );
// Memoria de pico de cada petición, para el informe (la descarga no vuelve a PHP después de enviar).
register_shutdown_function( static function () {
	$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	if ( '' === $uri ) return;
	@file_put_contents( '/digi-probe/requests.jsonl', json_encode( array( 'uri' => preg_replace( '/_wpnonce=[^&]+/', '_wpnonce=…', $uri ), 'peak_mb' => round( memory_get_peak_usage() / 1048576, 1 ), 'memory_limit' => ini_get( 'memory_limit' ), 'seconds' => round( microtime( true ) - (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) ), 2 ), 'error' => error_get_last()['message'] ?? '' ) ) . "\n", FILE_APPEND );
} );
PHP;
	if ( false === file_put_contents( WPMU_PLUGIN_DIR . '/digi-e2fix-lab.php', $mu ) ) throw new RuntimeException( 'No se escribió el mu-plugin del ensayo.' );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-e2fix-' . $mode ) );
	if ( is_wp_error( $created ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $created->get_error_message() );
	$setup = array( 'mode' => $mode, 'password' => $created[0], 'store' => $store, 'home' => home_url(), 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION );
	if ( 'dest' === $mode ) {
		// El respaldo grande (más que memory_limit) que el arnés dejó en el almacén, registrado como uno propio.
		$big = json_decode( (string) file_get_contents( $dir . '/big.json' ), true );
		$path = $store . '/' . $big['file'];
		if ( ! is_file( $path ) || (int) $big['size'] !== filesize( $path ) ) throw new RuntimeException( 'Falta el respaldo grande en el almacén del destino.' );
		update_option( Digitalisimo_Backups::HISTORY, array( array( 'date' => current_time( 'mysql' ), 'file' => $big['file'], 'size' => (int) $big['size'], 'sha256' => (string) $big['sha256'], 'destination' => 'local', 'scope' => 'installation', 'site_id' => 1, 'policy' => 'manual', 'backup_type' => 'full', 'remote' => array(), 'locked' => true, 'origin' => 'created' ) ), false );
		$setup['big'] = $big;
	}
	file_put_contents( $dir . '/setup.json', wp_json_encode( $setup ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
