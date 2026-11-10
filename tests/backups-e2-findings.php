<?php
/**
 * Hallazgos del ensayo real E2 (otro servidor y otro dominio, en el hosting del propietario) que no dependen de la
 * memoria (esos están en backups-memory-64m.php). Con el motor real de trabajos y de restauración:
 *   (h10) restaurar el respaldo de otro servidor deja el registro del almacén privado de ESTE servidor
 *         (`digitalisimo_backups_private_storage`), no el que trajo el SQL (la carpeta de allá con la raíz de aquí): la
 *         copia previa sigue visible y restaurable; en una red, el de sitemeta (h10-red); un registro de otro servidor
 *         cuya carpeta no existe aquí no deja Backups inservible (h10-ajeno); y si el proceso muere justo después del
 *         cambio de tablas, la operación siguiente lo repone desde el diario (h10-diario, al final);
 *   (h11) una ruta del servidor dentro de una URL `file://` que es el valor entero (el directorio aprobado de descargas
 *         de WooCommerce) se reubica, sin tocar un `file://` citado en un texto ni otra carpeta que empiece igual;
 *   (h12) la huella SFTP que no coincide dice qué clave presentó el servidor (tipo negociado y su SHA-1) y cómo
 *         comprobarla; se aceptan las líneas de `ssh-keygen -l -E sha1` (SHA1:…) y varias huellas, y vale la de la
 *         clave que presente; nada se autentica antes de comprobarla;
 *   (h13) «Probar conexión» sin JavaScript (el formulario envía action=…save) prueba y no guarda;
 *   (h14) los `.part-*` que dejaron intentos anteriores muertos sin limpiar se retiran al terminar bien (SFTP y WebDAV) o
 *         al abandonar la subida;
 *   (h15) «Eliminar» de «Archivos del almacén sin registro» acepta un paquete `extracted-site-*` con las mismas
 *         validaciones (no uno registrado, ni otro nombre);
 *   (h17) un ZIP antiguo del almacén en 0644 queda en 0600 con la operación siguiente;
 *   (h18, fase B) si la restauración la termina otro proceso (WP-Cron), el tick de la pestaña que la seguía con su
 *         testigo devuelve el estado final de esa restauración en vez de un 403 que la pantalla reintentaba sin fin.
 * Cada caso escribe «FALLA (x)» si falla; la prueba falla si alguno lo hizo. Debe fallar con 1.0.64 (d3ac3fa8).
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/destination-fakes.php';
$B = 'Digitalisimo_Backups';

/* ------------------------------------------------------------------ Lo que el entorno común no trae */
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=nonce-' . $action; }
function wp_verify_nonce( $nonce, $action ) { return 'nonce-' . $action === $nonce ? 1 : false; }
function get_current_user_id() { return 1; }
define( 'SSH2_FINGERPRINT_SHA1', 1 );
define( 'SSH2_FINGERPRINT_HEX', 2 );
$GLOBALS['bk_sftp_root'] = sys_get_temp_dir() . '/digitalisimo-e2-sftp-' . bin2hex( random_bytes( 4 ) );
mkdir( $GLOBALS['bk_sftp_root'] . '/backups', 0700, true );
// El servidor SFTP presenta su clave ECDSA (lo que negocia libssh2 en EL9), aunque también tenga ED25519 y RSA.
$GLOBALS['bk_hostkeys'] = array( 'ED25519' => strtoupper( sha1( 'clave ED25519 del servidor de respaldos' ) ), 'ECDSA' => strtoupper( sha1( 'clave ECDSA del servidor de respaldos' ) ), 'RSA' => strtoupper( sha1( 'clave RSA del servidor de respaldos' ) ) );
$GLOBALS['bk_sftp_auth'] = 0;
function ssh2_connect( $host, $port ) { return fopen( 'php://memory', 'r+' ); }
function ssh2_methods_negotiated( $connection ) { return array( 'kex' => 'curve25519-sha256', 'hostkey' => 'ecdsa-sha2-nistp256' ); }
function ssh2_fingerprint( $connection, $flags ) { return $GLOBALS['bk_hostkeys']['ECDSA']; }
function ssh2_auth_password( $connection, $user, $password ) { ++$GLOBALS['bk_sftp_auth']; return true; }
function ssh2_sftp( $connection ) { return fopen( 'php://memory', 'r+' ); }
function ssh2_sftp_rename( $sftp, $from, $to ) { return rename( $GLOBALS['bk_sftp_root'] . $from, $GLOBALS['bk_sftp_root'] . $to ); }
function ssh2_sftp_unlink( $sftp, $path ) { return @unlink( $GLOBALS['bk_sftp_root'] . $path ); }
function ssh2_sftp_stat( $sftp, $path ) { $stat = @stat( $GLOBALS['bk_sftp_root'] . $path ); return is_array( $stat ) ? array( 'size' => $stat['size'] ) : false; }
class Bk_E2_Sftp_Stream {
	public $context;
	private $handle;
	public function stream_open( $path, $mode, $options, &$opened ) { $this->handle = fopen( $GLOBALS['bk_sftp_root'] . parse_url( $path, PHP_URL_PATH ), $mode ); return (bool) $this->handle; }
	public function stream_read( $count ) { return fread( $this->handle, $count ); }
	public function stream_write( $data ) { return fwrite( $this->handle, $data ); }
	public function stream_eof() { return feof( $this->handle ); }
	public function stream_flush() { return fflush( $this->handle ); }
	public function stream_stat() { return fstat( $this->handle ); }
	public function stream_close() { fclose( $this->handle ); }
	public function stream_set_option( $option, $arg1, $arg2 ) { return false; }
	public function url_stat( $path, $flags ) { return @stat( $GLOBALS['bk_sftp_root'] . parse_url( $path, PHP_URL_PATH ) ); }
}
if ( in_array( 'ssh2.sftp', stream_get_wrappers(), true ) ) throw new RuntimeException( 'La prueba requiere un runtime sin la extensión SSH2 real.' );
stream_wrapper_register( 'ssh2.sftp', 'Bk_E2_Sftp_Stream' );

/** La base de datos en memoria con wp_sitemeta (en una red), que refleja las opciones de red simuladas. */
class Bk_E2_DB extends Bk_Test_DB {
	public function tables() { $tables = parent::tables(); if ( $GLOBALS['bk_multisite'] ) $tables[] = 'wp_sitemeta'; return $tables; }
	public function get_row( $query, $format ) {
		if ( false !== strpos( $query, 'SHOW CREATE TABLE `wp_sitemeta`' ) ) return array( 'wp_sitemeta', "CREATE TABLE `wp_sitemeta` (\n  `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `site_id` bigint(20) unsigned NOT NULL DEFAULT '0',\n  `meta_key` varchar(255) DEFAULT NULL,\n  `meta_value` longtext,\n  PRIMARY KEY (`meta_id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
		return parent::get_row( $query, $format );
	}
	public function get_results( $query, $format ) {
		if ( false !== strpos( $query, 'SHOW INDEX FROM `wp_sitemeta`' ) ) return array( array( 'Key_name' => 'PRIMARY', 'Column_name' => 'meta_id', 'Non_unique' => 0, 'Seq_in_index' => 1 ) );
		if ( false === strpos( $query, '`wp_sitemeta`' ) || false !== strpos( $query, 'SHOW INDEX' ) ) return parent::get_results( $query, $format );
		$rows = array();
		$id = 0;
		foreach ( $GLOBALS['bk_site_options'] as $name => $value ) $rows[] = array( 'meta_id' => (string) ++$id, 'site_id' => '1', 'meta_key' => $name, 'meta_value' => is_scalar( $value ) ? (string) $value : serialize( $value ) );
		return bk_page( $rows, $query, 'meta_id' );
	}
	public function query( $sql ) {
		if ( 0 === strpos( $sql, 'DROP TABLE IF EXISTS `wp_sitemeta`' ) ) { $this->queries[] = $sql; $GLOBALS['bk_site_options'] = array(); return 1; }
		if ( 0 === strpos( $sql, 'INSERT INTO `wp_sitemeta`' ) && preg_match_all( "/'((?:[^'\\\\]|\\\\.)*)'|NULL/s", substr( $sql, strpos( $sql, 'VALUES' ) ), $m ) && count( $m[1] ) >= 4 ) {
			$this->queries[] = $sql;
			$value = bk_sql_unescape( $m[1][3] );
			$unserialized = @unserialize( $value );
			$GLOBALS['bk_site_options'][ bk_sql_unescape( $m[1][2] ) ] = false === $unserialized && 'b:0;' !== $value ? $value : $unserialized;
			return 1;
		}
		return parent::query( $sql );
	}
}
$GLOBALS['wpdb'] = new Bk_E2_DB();

$failures = array();
$done = array();
$case = function( $label, callable $body ) use ( &$failures, &$done ) {
	try {
		$body();
		$done[] = $label;
		echo 'ok (' . $label . ")\n";
	} catch ( Throwable $e ) {
		$failures[] = $label;
		echo 'FALLA (' . $label . '): ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
	}
};
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
$sql_of = function( $path ) { $zip = new ZipArchive(); $zip->open( $path ); $sql = $zip->getFromName( 'database.sql' ); $zip->close(); return (string) $sql; };
$restore = function( $path ) use ( $B ) {
	$report = Digitalisimo_Backups_Security::open( $path, bk_call( $B, 'history_item', basename( $path ) ), '' );
	try { bk_call( $B, 'restore_digitalisimo_zip', $report['path'], $report ); } finally { Digitalisimo_Backups_Security::close( $report ); }
};
$sftp_settings = array( 'sftp_host' => 'sftp.example.test', 'sftp_port' => '22', 'sftp_user' => 'respaldos', 'sftp_password' => 'Clave-SFTP-E2', 'sftp_path' => '/backups' );
$keygen = function( $type ) { return '256 SHA1:' . rtrim( base64_encode( hex2bin( $GLOBALS['bk_hostkeys'][ $type ] ) ), '=' ) . ' sftp.example.test (' . $type . ')'; };

// (h10-ajeno) Lo primero que abre el almacén: un registro de otro servidor (la raíz de aquí, que dejó la reubicación, y la
// carpeta de allá, que aquí no existe ni se puede crear). Backups usa la carpeta de esta instalación y no queda inservible.
$blocker = sys_get_temp_dir() . '/digitalisimo-e2-otro-servidor-' . bin2hex( random_bytes( 4 ) );
file_put_contents( $blocker, 'un archivo donde el origen tenía una carpeta' );
$root_here = wp_normalize_path( (string) realpath( ABSPATH ) );
update_option( $B::STORAGE, array( 'wordpress_root' => $root_here, 'directory' => $blocker . '/.digitalisimo-backups-0123456789abcdef' ) );
$case( 'h10-ajeno', function() use ( $B, $check, $root_here ) {
	$storage = bk_storage();
	$expected = dirname( (string) realpath( $_SERVER['DOCUMENT_ROOT'] ) ) . '/.digitalisimo-backups-' . substr( hash( 'sha256', $root_here ), 0, 16 );
	$check( realpath( $expected ) === $storage && is_dir( $storage ), 'Con el registro de otro servidor, el almacén no es la carpeta de esta instalación: ' . $storage );
	$check( array( 'wordpress_root' => $root_here, 'directory' => $storage ) === get_option( $B::STORAGE ), 'El registro del almacén no volvió a ser el de este servidor: ' . json_encode( get_option( $B::STORAGE ) ) );
} );
@unlink( $blocker );
// Sin almacén no se podría seguir (1.0.64 lanza «No se pudo crear la carpeta privada…»): los demás casos, con el de aquí.
if ( in_array( 'h10-ajeno', $failures, true ) ) delete_option( $B::STORAGE );

// (h10) Restaurar el respaldo de otro servidor conserva el registro del almacén de éste; la copia previa sigue visible.
$case( 'h10', function() use ( $B, $check, $sql_of ) {
	$storage = bk_storage();
	$here = get_option( $B::STORAGE );
	$check( is_array( $here ) && $storage === $here['directory'], 'El almacén no está registrado: ' . json_encode( $here ) );
	// Lo que trae el SQL de otro servidor después de reubicarlo: la raíz de aquí con la carpeta del origen.
	$foreign = array( 'wordpress_root' => $here['wordpress_root'], 'directory' => '/home/origen-e2/.digitalisimo-backups-0123456789abcdef' );
	update_option( $B::STORAGE, $foreign );
	$backup = bk_backup( true );
	update_option( $B::STORAGE, $here );
	$check( false !== strpos( $sql_of( $backup ), '/home/origen-e2/.digitalisimo-backups-' ), 'El SQL del respaldo no lleva el registro del otro servidor.' );
	$result = $B::service_restore( basename( $backup ), array( 'confirm' => 'RESTAURAR' ) );
	$check( ! is_wp_error( $result ) && true === $result['restored'], 'La restauración falló: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	$check( $here === get_option( $B::STORAGE ), 'Tras restaurar, el almacén privado es el del otro servidor: ' . json_encode( get_option( $B::STORAGE ) ) );
	// La copia previa: en el historial, con su ZIP en el almacén de aquí, restaurable.
	$safety = null;
	foreach ( (array) get_option( $B::HISTORY, array() ) as $row ) if ( 'pre-restore' === ( $row['policy'] ?? '' ) ) { $safety = $row; break; }
	$check( is_array( $safety ) && is_file( $storage . '/' . $safety['file'] ), 'La copia previa no está en el historial o su ZIP no está en el almacén de este servidor.' );
	$listed = $B::service_backups();
	$row = null;
	foreach ( is_array( $listed ) ? $listed : array() as $item ) if ( ( $item['file'] ?? '' ) === $safety['file'] ) $row = $item;
	$check( is_array( $row ) && ! empty( $row['restorable'] ), 'La copia previa no sale restaurable: ' . json_encode( $row ) );
	$status = $B::service_status();
	$check( ! is_wp_error( $status ) && ! preg_match( '/carpeta privada|almacén/i', implode( ' ', (array) $status['problems'] ) ), 'Backups no encuentra su almacén tras restaurar: ' . json_encode( $status['problems'] ?? $status ) );
	@unlink( $backup );
} );

// (h10-red) En una red, el registro vive en sitemeta y se conserva igual.
$case( 'h10-red', function() use ( $B, $check, $sql_of, $restore ) {
	$GLOBALS['bk_multisite'] = true;
	$options = $GLOBALS['bk_options'];
	try {
		$here = array( 'wordpress_root' => wp_normalize_path( (string) realpath( ABSPATH ) ), 'directory' => bk_storage() );
		$GLOBALS['bk_site_options'][ $B::STORAGE ] = array( 'wordpress_root' => $here['wordpress_root'], 'directory' => '/home/origen-red/.digitalisimo-backups-fedcba9876543210' );
		$backup = bk_backup( true );
		$GLOBALS['bk_site_options'][ $B::STORAGE ] = $here;
		$check( (bool) preg_match( "/^INSERT INTO `wp_sitemeta` .*'digitalisimo_backups_private_storage'.*origen-red/m", $sql_of( $backup ) ), 'El SQL de la red no lleva el registro del otro servidor en sitemeta.' );
		$restore( $backup );
		$check( $here === ( $GLOBALS['bk_site_options'][ $B::STORAGE ] ?? null ), 'En la red, tras restaurar, el almacén privado es el del otro servidor: ' . json_encode( $GLOBALS['bk_site_options'][ $B::STORAGE ] ?? null ) );
		$check( ! isset( $GLOBALS['bk_options'][ $B::STORAGE ] ) || $options[ $B::STORAGE ] === $GLOBALS['bk_options'][ $B::STORAGE ], 'En la red el registro se escribió en las opciones del sitio.' );
		@unlink( $backup );
	} finally {
		$GLOBALS['bk_multisite'] = false;
	}
} );

// (h18) La restauración de h10 ya terminó (como si la hubiera terminado WP-Cron): la pestaña que la seguía con su testigo
// recibe el estado final, no un 403 que reintentaría sin fin; con otro testigo, 403 como siempre, y nada de otros trabajos.
class Bk_E2_Json extends Error { public $success; public $payload; public $status; public function __construct( $success, $payload, $status ) { parent::__construct( 'json' ); $this->success = $success; $this->payload = $payload; $this->status = $status; } }
function wp_send_json_success( $data = null, $status = null ) { throw new Bk_E2_Json( true, $data, $status ); }
function wp_send_json_error( $data = null, $status = null ) { throw new Bk_E2_Json( false, $data, $status ); }
$case( 'h18', function() use ( $check ) {
	$restore = null;
	foreach ( Digitalisimo_Backups_Job_Store::all() as $job ) if ( Digitalisimo_Backups_Jobs::is_restore( $job ) && Digitalisimo_Backups_Job_Store::is_terminal( $job ) && ( ! $restore || (int) $job['finished_at'] >= (int) $restore['finished_at'] ) ) $restore = $job;
	$check( is_array( $restore ) && '' !== (string) ( $restore['spec']['token'] ?? '' ) && null === Digitalisimo_Backups_Jobs::restore_active(), 'No quedó la restauración terminada de h10 con su testigo.' );
	$tick = function( $token ) {
		$_COOKIE[ Digitalisimo_Backups_Restore_Maintenance::COOKIE ] = $token;
		try { Digitalisimo_Backups_Jobs::ajax_restore_tick(); return null; } catch ( Bk_E2_Json $json ) { return $json; } finally { unset( $_COOKIE[ Digitalisimo_Backups_Restore_Maintenance::COOKIE ] ); }
	};
	$answer = $tick( (string) $restore['spec']['token'] );
	$check( $answer && $answer->success && 'finished' === ( $answer->payload['result'] ?? '' ), 'Con la restauración terminada por otro proceso, el tick de su testigo no da el estado final: ' . json_encode( $answer ? array( $answer->success, $answer->status, $answer->payload ) : null ) );
	$jobs = (array) ( $answer->payload['jobs'] ?? array() );
	$check( 1 === count( $jobs ) && $restore['id'] === $jobs[0]['id'] && ! empty( $jobs[0]['terminal'] ) && $restore['status'] === $jobs[0]['status'], 'El estado final no es el de esa restauración (o lleva otros trabajos): ' . json_encode( $jobs ) );
	$other = $tick( 'otro-testigo-que-no-es' );
	$check( $other && ! $other->success && 403 === $other->status, 'Con otro testigo no respondió 403.' );
} );

// (h11) Una ruta dentro de una URL file:// que es el valor entero se reubica; en un texto, no.
$case( 'h11', function() use ( $check ) {
	$r = new Digitalisimo_Backups_Relocate();
	$r->add_path( '/home/origen/public_html', '/home/destino/public_html' );
	$approved = 'file:///home/origen/public_html/wp-content/uploads/woocommerce_uploads/';
	$check( 'file:///home/destino/public_html/wp-content/uploads/woocommerce_uploads/' === $r->rewrite( $approved, 'content' ), 'El directorio aprobado de descargas de WooCommerce no se reubicó: ' . $r->rewrite( $approved, 'content' ) );
	$check( 'FILE:///home/destino/public_html/wp-content/uploads/x.zip' === $r->rewrite( 'FILE:///home/origen/public_html/wp-content/uploads/x.zip', 'content' ), 'Un file:// en mayúsculas no se reubicó.' );
	$check( 'file:\\/\\/\\/home\\/destino\\/public_html\\/wp-content' === $r->rewrite( 'file:\\/\\/\\/home\\/origen\\/public_html\\/wp-content', 'setting' ), 'Un file:// con barras de JSON no se reubicó.' );
	$serialized = serialize( array( 'url' => $approved, 'enabled' => 1 ) );
	$check( serialize( array( 'url' => 'file:///home/destino/public_html/wp-content/uploads/woocommerce_uploads/', 'enabled' => 1 ) ) === $r->rewrite( $serialized, 'content' ), 'Un file:// dentro de un serializado no se reubicó.' );
	// Lo que no es el valor entero, otra carpeta que empieza igual u otro esquema no cambian.
	foreach ( array( 'Descarga desde file:///home/origen/public_html/wp-content/a.zip en tu equipo.', 'file:///home/origen/public_html2/wp-content/a.zip', 'file:///home/origen/public_html/a.zip y otro', 'https://ejemplo.test/file:///home/origen/public_html', 'smb:///home/origen/public_html/x' ) as $kept ) $check( $kept === $r->rewrite( $kept, 'content' ), 'Se cambió lo que no debía: ' . $kept . ' → ' . $r->rewrite( $kept, 'content' ) );
} );

// (h12) La huella que no coincide dice qué clave presentó el servidor; varias huellas y las líneas de ssh-keygen sirven.
$case( 'h12', function() use ( $check, $sftp_settings, $keygen ) {
	$S = 'Digitalisimo_Backups_Destination_Sftp';
	$file = bk_storage() . '/digitalisimo-e2-sftp.zip';
	file_put_contents( $file, 'ZIP de prueba de la huella SFTP' );
	$context = array( 'temp_dir' => bk_storage(), 'connection' => 'sftp' );
	try {
		// La huella de ED25519 (la que indicaba la ayuda) con un servidor que presenta ECDSA.
		$GLOBALS['bk_sftp_auth'] = 0;
		try { ( new $S( $sftp_settings + array( 'sftp_fingerprint' => $GLOBALS['bk_hostkeys']['ED25519'] ), $context ) )->put( $file ); $message = 'aceptada'; }
		catch ( Digitalisimo_Backups_Permanent_Error $e ) { $message = $e->getMessage(); }
		$sha1 = 'SHA1:' . rtrim( base64_encode( hex2bin( $GLOBALS['bk_hostkeys']['ECDSA'] ) ), '=' );
		$colons = implode( ':', str_split( strtolower( $GLOBALS['bk_hostkeys']['ECDSA'] ), 2 ) );
		$check( 0 === strpos( $message, 'La huella de la clave SSH del servidor SFTP no coincide con la configurada.' ), 'Una huella que no coincide no se rechazó: ' . $message );
		foreach ( array( 'ECDSA (nistp256)', $sha1, $colons, 'ssh-keyscan -t ecdsa -p 22 sftp.example.test | ssh-keygen -l -E sha1 -f -', 'canal independiente' ) as $needle ) $check( false !== strpos( $message, $needle ), 'El error de la huella no dice «' . $needle . '»: ' . $message );
		$check( 0 === $GLOBALS['bk_sftp_auth'], 'Se intentó autenticar con una huella que no coincide.' );
		// Las tres líneas de ssh-keygen -l -E sha1 pegadas en el campo (un campo de una línea las junta).
		$pasted = $keygen( 'ED25519' ) . $keygen( 'ECDSA' ) . $keygen( 'RSA' );
		$saved = $S::sanitize( array( 'sftp_fingerprint' => $pasted ) + $sftp_settings, array() );
		$check( implode( ' ', array( $GLOBALS['bk_hostkeys']['ED25519'], $GLOBALS['bk_hostkeys']['ECDSA'], $GLOBALS['bk_hostkeys']['RSA'] ) ) === $saved['sftp_fingerprint'], 'Las líneas de ssh-keygen no se guardaron como tres huellas: ' . $saved['sftp_fingerprint'] );
		$receipt = ( new $S( array_merge( $sftp_settings, $saved, array( 'sftp_password' => 'Clave-SFTP-E2' ) ), $context ) )->put( $file );
		$check( is_file( $GLOBALS['bk_sftp_root'] . $receipt['path'] ), 'Con la huella de la clave que presenta el servidor entre varias, SFTP no publicó la copia.' );
		@unlink( $GLOBALS['bk_sftp_root'] . $receipt['path'] );
		// Separadas por saltos de línea, comas y con dos puntos; una sola SHA1:… también.
		$check( array( $GLOBALS['bk_hostkeys']['ECDSA'], $GLOBALS['bk_hostkeys']['RSA'] ) === $S::fingerprints( $keygen( 'ECDSA' ) . "\n" . implode( ':', str_split( strtolower( $GLOBALS['bk_hostkeys']['RSA'] ), 2 ) ) . ',' ), 'Varias huellas por línea no se leen.' );
		$check( $GLOBALS['bk_hostkeys']['ECDSA'] === $S::normalize_fingerprint( $sha1 ), 'Una huella SHA1:… de ssh-keygen no se normaliza.' );
		// Una huella a medias, un algoritmo sin huella o MD5 se rechazan al guardar (no se ignoran en silencio).
		foreach ( array( 'SHA1:abc ' . $GLOBALS['bk_hostkeys']['ECDSA'], $GLOBALS['bk_hostkeys']['ECDSA'] . ' ' . substr( $GLOBALS['bk_hostkeys']['RSA'], 0, 39 ), '256 MD5:aa:bb:cc:dd:ee:ff:00:11:22:33:44:55:66:77:88:99 sftp (ECDSA)', 'SHA1:' ) as $bad ) {
			try { $S::sanitize( array( 'sftp_fingerprint' => $bad ) + $sftp_settings, array() ); $result = 'aceptada'; } catch ( Exception $e ) { $result = $e->getMessage(); }
			$check( false !== strpos( $result, 'La huella SFTP debe ser' ), 'Se aceptó una huella no válida: ' . $bad );
		}
	} finally {
		@unlink( $file );
	}
} );

// (h13) «Probar conexión» sin JavaScript: el formulario lleva action=…save, pero se pulsó «Probar conexión».
$case( 'h13', function() use ( $B, $check ) {
	$webdav = new Bk_Fake_Webdav( 'https://nube.ejemplo.test/dav/', 'ana', 'Clave-WebDav-E2' );
	Digitalisimo_Backups_Http::$transport = $webdav;
	$before = get_option( $B::OPTION );
	$connections = Digitalisimo_Backups_Destinations::connections();
	$fields = array( 'destination' => 'local', 'webdav_url' => 'https://nube.ejemplo.test/dav/', 'webdav_user' => 'ana', 'webdav_password' => 'Clave-WebDav-E2', 'webdav_path' => '/respaldos' );
	try {
		list( $ok, $message ) = bk_post( array( $B, 'save' ), array( 'action' => $B::ACTION_SAVE, '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups_test' => 'webdav', 'digitalisimo_backups' => $fields ) );
		$check( $ok && 0 === strpos( $message, 'Conexión con WebDAV correcta' ), 'Sin JavaScript, «Probar conexión» no probó: ' . $message );
		$check( $before === get_option( $B::OPTION ), '«Probar conexión» guardó los ajustes.' );
		$check( ! array_filter( array_keys( $webdav->files ), function( $name ) { return false !== strpos( $name, 'prueba' ); } ), 'La prueba dejó su archivo en el destino.' );
		// En el formulario de otra conexión (save_connection), lo mismo: prueba y no la crea.
		list( $ok, $message ) = bk_post( array( $B, 'save_connection' ), array( 'action' => $B::ACTION_SAVE_CONNECTION, '_wpnonce' => 'nonce-' . $B::ACTION_SAVE_CONNECTION, 'digitalisimo_backups_test' => '', 'connection_type' => 'webdav', 'connection_name' => 'Otra nube', 'digitalisimo_backups_connection' => array_diff_key( $fields, array( 'destination' => 1 ) ) ) );
		$check( $ok && 0 === strpos( $message, 'Conexión con WebDAV correcta' ) && $connections === Digitalisimo_Backups_Destinations::connections(), '«Probar conexión» de otra conexión no probó o la creó: ' . $message );
		// Sin el nonce de la pantalla no prueba ni guarda.
		$requests = count( $webdav->log );
		try { bk_post( array( $B, 'save' ), array( 'action' => $B::ACTION_SAVE, '_wpnonce' => 'otro', 'digitalisimo_backups_test' => 'webdav', 'digitalisimo_backups' => $fields ) ); $denied = false; } catch ( Bk_Die $e ) { $denied = true; }
		$check( $denied && $requests === count( $webdav->log ) && $before === get_option( $B::OPTION ), 'Sin nonce, «Probar conexión» se conectó o guardó.' );
		// Guardar (el botón de guardar no lleva digitalisimo_backups_test) sigue guardando.
		list( $ok, $message ) = bk_post( array( $B, 'save' ), array( 'action' => $B::ACTION_SAVE, '_wpnonce' => 'nonce-' . $B::ACTION_SAVE, 'digitalisimo_backups' => $fields ) );
		$check( $ok && false !== strpos( $message, 'guardados' ) && 'https://nube.ejemplo.test/dav/' === ( get_option( $B::OPTION )['webdav_url'] ?? '' ), '«Guardar conexiones» dejó de guardar: ' . $message );
	} finally {
		Digitalisimo_Backups_Http::$transport = null;
	}
} );

// (h14) Los temporales de intentos anteriores que murieron sin limpiar se retiran al terminar bien o al abandonar.
$case( 'h14', function() use ( $check, $sftp_settings ) {
	$S = 'Digitalisimo_Backups_Destination_Sftp';
	$remote = $GLOBALS['bk_sftp_root'] . '/backups';
	$file = bk_storage() . '/digitalisimo-e2-reintento.zip';
	file_put_contents( $file, str_repeat( 'Respaldo que se reintenta. ', 4000 ) );
	$source = array( 'size' => filesize( $file ), 'sha256' => hash_file( 'sha256', $file ) );
	$state_path = bk_storage() . '/transfer-e2.json';
	$state = new Digitalisimo_Backups_Transfer_State( $state_path );
	$settings = $sftp_settings + array( 'sftp_fingerprint' => $GLOBALS['bk_hostkeys']['ECDSA'] );
	$destination = function() use ( $S, $settings, $state ) { return new $S( $settings, array( 'temp_dir' => bk_storage(), 'connection' => 'sftp', 'state' => $state ) ); };
	$parts = function() use ( $remote ) { return glob( $remote . '/*.part-*' ) ?: array(); };
	try {
		// Primer intento muerto (SIGKILL del hosting): su temporal de 0 bytes y su estado, sin nada más.
		$first = '/backups/digitalisimo-e2-reintento.zip.part-aaaaaaaaaaaa';
		touch( $GLOBALS['bk_sftp_root'] . $first );
		$state->set( array( 'connection' => 'sftp', 'size' => $source['size'], 'sha256' => $source['sha256'], 'temp' => array( 'provider' => 'sftp', 'path' => $first ), 'type' => 'sftp' ) );
		// Segundo intento muerto después de subir entero, antes de publicar.
		$upload = new ReflectionMethod( $S, 'upload' );
		if ( PHP_VERSION_ID < 80100 ) $upload->setAccessible( true );
		$upload->invoke( $destination(), $file, basename( $file ), $source );
		$check( 2 === count( $parts() ), 'La simulación no dejó los dos temporales: ' . json_encode( $parts() ) );
		// El tercero termina bien: publica y retira los dos.
		$receipt = $destination()->put( $file );
		$check( is_file( $GLOBALS['bk_sftp_root'] . $receipt['path'] ) && ! $parts(), 'Tras una transferencia correcta quedaron temporales de intentos anteriores: ' . json_encode( array_map( 'basename', $parts() ) ) );
		$check( ! is_file( $state_path ), 'El estado de la transferencia no se retiró.' );
		@unlink( $GLOBALS['bk_sftp_root'] . $receipt['path'] );
		// Abandonar (el trabajo falla o se cancela) también retira los de intentos anteriores.
		touch( $GLOBALS['bk_sftp_root'] . $first );
		$state->set( array( 'connection' => 'sftp', 'size' => $source['size'], 'sha256' => $source['sha256'], 'temp' => array( 'provider' => 'sftp', 'path' => $first ), 'type' => 'sftp' ) );
		$upload->invoke( $destination(), $file, basename( $file ), $source );
		$destination()->abandon();
		$check( ! $parts() && ! is_file( $state_path ), 'Abandonar no retiró los temporales: ' . json_encode( array_map( 'basename', $parts() ) ) );
		// WebDAV: el mismo contrato.
		$webdav = new Bk_Fake_Webdav( 'https://nube.ejemplo.test/dav/', 'ana', 'Clave-WebDav-E2' );
		$webdav->collections['respaldos'] = true;
		$webdav->files['respaldos/digitalisimo-e2-reintento.zip.part-bbbbbbbbbbbb'] = '';
		$state->set( array( 'connection' => 'webdav', 'size' => $source['size'], 'sha256' => $source['sha256'], 'temp' => array( 'provider' => 'webdav', 'path' => 'respaldos/digitalisimo-e2-reintento.zip.part-bbbbbbbbbbbb' ), 'type' => 'webdav' ) );
		Digitalisimo_Backups_Http::$transport = $webdav;
		try { ( new Digitalisimo_Backups_Destination_Webdav( array( 'webdav_url' => 'https://nube.ejemplo.test/dav/', 'webdav_user' => 'ana', 'webdav_password' => 'Clave-WebDav-E2', 'webdav_path' => '/respaldos' ), array( 'temp_dir' => bk_storage(), 'connection' => 'webdav', 'state' => $state ) ) )->put( $file ); }
		finally { Digitalisimo_Backups_Http::$transport = null; }
		$check( array( 'respaldos/digitalisimo-e2-reintento.zip' ) === array_keys( $webdav->files ), 'WebDAV conservó el temporal de un intento anterior: ' . json_encode( array_keys( $webdav->files ) ) );
	} finally {
		@unlink( $file );
		@unlink( $state_path );
		foreach ( $parts() as $part ) @unlink( $part );
	}
} );

// (h15) «Eliminar» un paquete de un sitio extraído copiado al almacén a mano, con las mismas validaciones.
$case( 'h15', function() use ( $B, $check ) {
	$storage = bk_storage();
	$name = Digitalisimo_Backups_Site_Extract::FILE_PREFIX . 'uno-20261109-180000-1a2b3c4d.zip';
	file_put_contents( $storage . '/' . $name, 'paquete extraído copiado por SSH' );
	$post = function( $file ) use ( $B ) { return bk_post( array( $B, 'delete_loose' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_DELETE_LOOSE, 'file' => $file ) ); };
	list( $ok, $message ) = $post( $name );
	$check( $ok && ! file_exists( $storage . '/' . $name ), '«Eliminar» no borró el paquete extraído suelto: ' . $message );
	// Uno registrado en el historial no se borra desde aquí; otro nombre tampoco.
	$registered = Digitalisimo_Backups_Site_Extract::FILE_PREFIX . 'dos-20261109-180000-5e6f7a8b.zip';
	file_put_contents( $storage . '/' . $registered, 'paquete registrado' );
	$history = (array) get_option( $B::HISTORY, array() );
	update_option( $B::HISTORY, array_merge( array( array( 'file' => $registered, 'date' => '2026-11-09 18:00:00', 'destination' => 'local', 'scope' => Digitalisimo_Backups_Site_Extract::SCOPE ) ), $history ) );
	list( $ok, $message ) = $post( $registered );
	$check( ! $ok && file_exists( $storage . '/' . $registered ) && false !== strpos( $message, 'historial' ), 'Se borró un paquete extraído registrado: ' . $message );
	update_option( $B::HISTORY, $history );
	@unlink( $storage . '/' . $registered );
	foreach ( array( Digitalisimo_Backups_Site_Extract::FILE_PREFIX . 'uno.php', 'extracted-site.zip', '.htaccess', 'otro-extracted-site-uno.zip' ) as $bad ) {
		$existed = file_exists( $storage . '/' . $bad );
		if ( ! $existed ) file_put_contents( $storage . '/' . $bad, 'x' );
		list( $ok, $message ) = $post( $bad );
		$check( ! $ok && file_exists( $storage . '/' . $bad ), 'Se aceptó borrar «' . $bad . '»: ' . $message );
		if ( ! $existed ) @unlink( $storage . '/' . $bad );
	}
} );

// (h17) Un ZIP de una versión anterior en 0644 queda en 0600 con la operación siguiente.
$case( 'h17', function() use ( $B, $check ) {
	if ( DIRECTORY_SEPARATOR !== '/' ) return;
	$old = bk_storage() . '/digitalisimo-backup-antiguo-20250101-000000-00000001.zip';
	file_put_contents( $old, 'respaldo de 1.0.60' );
	chmod( $old, 0644 );
	$lock = bk_call( $B, 'acquire_operation_lock' );
	bk_call( $B, 'release_operation_lock', $lock );
	clearstatcache( true, $old );
	$check( 0600 === ( fileperms( $old ) & 0777 ), 'Tras operar, el ZIP antiguo sigue en ' . sprintf( '%o', fileperms( $old ) & 0777 ) . '.' );
	@unlink( $old );
} );

// (h10-diario) El proceso muere justo después del cambio de tablas (antes de reponer nada): la operación siguiente repone
// el registro del almacén de este servidor desde el diario. exit() no ejecuta ningún finally; sigue en el cierre.
$interrupted = null;
$here_at_death = get_option( $B::STORAGE );
$case( 'h10-diario (preparación)', function() use ( $B, $check, &$interrupted, $here_at_death ) {
	update_option( $B::STORAGE, array( 'wordpress_root' => $here_at_death['wordpress_root'], 'directory' => '/home/origen-diario/.digitalisimo-backups-00112233445566778' ) );
	$interrupted = bk_backup( true );
	update_option( $B::STORAGE, $here_at_death );
} );
register_shutdown_function( function() use ( $B, $check, $case, &$failures, &$done, &$interrupted, $here_at_death ) {
	if ( null !== $interrupted ) {
		bk_close_dead_locks();
		$case( 'h10-diario', function() use ( $B, $check, $here_at_death ) {
			$journal = bk_storage() . '/' . $B::RESTORE_JOURNAL;
			$check( is_file( $journal ) && false !== strpos( json_encode( get_option( $B::STORAGE ) ), 'origen-diario' ), 'La simulación no dejó el SQL cambiado con el registro del otro servidor.' );
			$lock = bk_call( $B, 'acquire_operation_lock' );
			bk_call( $B, 'release_operation_lock', $lock );
			$check( ! file_exists( $journal ) && $here_at_death === get_option( $B::STORAGE ), 'La operación siguiente no repuso el registro del almacén de este servidor: ' . json_encode( get_option( $B::STORAGE ) ) );
		} );
	}
	bk_cleanup();
	bk_remove_tree( $GLOBALS['bk_sftp_root'] );
	if ( $failures ) throw new RuntimeException( 'Hallazgos de E2: fallaron ' . implode( ', ', array_unique( $failures ) ) . '.' );
	echo 'DIGITALÍSIMO Backups: hallazgos del ensayo E2 corregidos (' . implode( ', ', array_unique( $done ) ) . ").\n";
} );
if ( null !== $interrupted ) {
	$GLOBALS['wpdb']->exit_after = 'RENAME TABLE ';
	$restore( $interrupted );
	$failures[] = 'h10-diario';
	echo "FALLA (h10-diario): la restauración no se interrumpió.\n";
}
