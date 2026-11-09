<?php
/**
 * Destinos contra servidores reales (sin simulaciones): el contrato completo (put a un temporal, verificación,
 * publicación, size, get, list, delete y test) con TLS verificado contra una CA propia, y que sin esa CA la conexión se
 * rechaza. Se omite si no se configuran servidores (la CI no los tiene); en el servidor de desarrollo se corre contra
 * MinIO (S3 compatible), pyftpdlib (FTPS explícito) y wsgidav (WebDAV sobre HTTPS):
 *
 *   DIGI_LIVE_CA=/ruta/ca.pem
 *   DIGI_LIVE_S3='https://127.0.0.1:19000|bucket|access|secret|us-east-1'
 *   DIGI_LIVE_FTPS='127.0.0.1|12121|explicit|usuario|clave'
 *   DIGI_LIVE_WEBDAV='https://127.0.0.1:18443/dav/|usuario|clave'
 *
 * El archivo de prueba pasa de 8 MiB para que S3 use la subida por partes.
 */
define( 'ABSPATH', __DIR__ . '/' );
$ca = (string) getenv( 'DIGI_LIVE_CA' );
$configured = array_filter( array( 's3compat' => getenv( 'DIGI_LIVE_S3' ), 'ftp' => getenv( 'DIGI_LIVE_FTPS' ), 'webdav' => getenv( 'DIGI_LIVE_WEBDAV' ) ) );
if ( ! $configured || '' === $ca || ! is_readable( $ca ) ) { echo "DIGITALÍSIMO Backups: prueba con servidores reales OMITIDA (define DIGI_LIVE_CA y DIGI_LIVE_S3, DIGI_LIVE_FTPS o DIGI_LIVE_WEBDAV).\n"; exit( 0 ); }
function wp_basename( $path ) { return basename( $path ); }
require __DIR__ . '/../digitalisimo-backups/includes/class-backups-job-store.php';
foreach ( array( 'http', 'destination', 'destinations', 'destination-ftp', 'destination-webdav', 'destination-s3' ) as $part ) require __DIR__ . '/../digitalisimo-backups/includes/destinations/class-' . $part . '.php';
$pem = trim( (string) file_get_contents( $ca ) );
$root = sys_get_temp_dir() . '/digitalisimo-live-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/private', 0700, true );
$file = $root . '/digitalisimo-backup-en-vivo-' . gmdate( 'Ymd-His' ) . '.zip';
$handle = fopen( $file, 'wb' );
for ( $i = 0; $i < 9; $i++ ) fwrite( $handle, random_bytes( 1048576 ) );
fwrite( $handle, 'fin' );
fclose( $handle );
$fail = 0;
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
$settings = array();
if ( isset( $configured['s3compat'] ) ) {
	list( $endpoint, $bucket, $access, $secret, $region ) = explode( '|', $configured['s3compat'] );
	$settings['s3compat'] = array( 'Digitalisimo_Backups_Destination_S3_Compatible', array( 's3compat_provider' => 'other', 's3compat_endpoint' => $endpoint, 's3compat_signing_region' => $region, 's3compat_bucket' => $bucket, 's3compat_access_key' => $access, 's3compat_secret_key' => $secret, 's3compat_path' => 'en-vivo/prueba', 's3compat_style' => 'path', 's3compat_ca' => $pem ), 's3compat_ca' );
}
if ( isset( $configured['ftp'] ) ) {
	list( $host, $port, $mode, $user, $password ) = explode( '|', $configured['ftp'] );
	$settings['ftp'] = array( 'Digitalisimo_Backups_Destination_Ftp', array( 'ftp_host' => $host, 'ftp_port' => $port, 'ftp_security' => $mode, 'ftp_user' => $user, 'ftp_password' => $password, 'ftp_path' => '/en-vivo/prueba', 'ftp_passive' => 'passive', 'ftp_ca' => $pem ), 'ftp_ca' );
}
if ( isset( $configured['webdav'] ) ) {
	list( $url, $user, $password ) = explode( '|', $configured['webdav'] );
	$settings['webdav'] = array( 'Digitalisimo_Backups_Destination_Webdav', array( 'webdav_url' => $url, 'webdav_user' => $user, 'webdav_password' => $password, 'webdav_path' => '/en-vivo/prueba', 'webdav_ca' => $pem ), 'webdav_ca' );
}
foreach ( $settings as $type => list( $class, $values, $ca_field ) ) {
	try {
		$started = microtime( true );
		$context = array( 'temp_dir' => $root . '/private', 'connection' => $type, 'state' => new Digitalisimo_Backups_Transfer_State( $root . '/private/state-' . $type . '.json' ) );
		$destination = new $class( $values, $context );
		$receipt = $destination->put( $file );
		$check( filesize( $file ) === $destination->size( $receipt ), 'size() no coincide.' );
		$back = $root . '/private/back';
		$destination->get( $receipt, $back );
		$check( hash_file( 'sha256', $back ) === hash_file( 'sha256', $file ), 'get() no devolvió el mismo archivo.' );
		unlink( $back );
		$names = array_column( $destination->list(), 'name' );
		$check( in_array( basename( $file ), $names, true ) && ! preg_grep( '/\.part-/', $names ), 'list() no encuentra el respaldo o ve temporales: ' . json_encode( $names ) );
		$destination->delete( $receipt );
		$destination->delete( $receipt );
		$check( false === $destination->size( $receipt ), 'delete() no borró el respaldo.' );
		$steps = $destination->test();
		$check( count( $steps ) >= 4, 'test() incompleto.' );
		// Sin la CA propia, TLS no se acepta (el certificado no lo firma una autoridad pública).
		$values[ $ca_field ] = '';
		try {
			( new $class( $values, array( 'temp_dir' => $root . '/private', 'connection' => $type . '-sin-ca' ) ) )->size( $receipt );
			throw new LogicException( 'Se aceptó un certificado que no verifica.' );
		} catch ( LogicException $e ) { throw $e; } catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		printf( "ok (%s en vivo: %.1f s, %s)\n", $type, microtime( true ) - $started, implode( ' ', $steps ) );
	} catch ( Throwable $e ) {
		$fail++;
		echo 'FALLA (' . $type . ' en vivo): ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
	}
}
foreach ( array_diff( scandir( $root . '/private' ), array( '.', '..' ) ) as $name ) @unlink( $root . '/private/' . $name );
@rmdir( $root . '/private' );
@unlink( $file );
@rmdir( $root );
if ( $fail ) { echo "FALLAN $fail destinos en vivo.\n"; exit( 1 ); }
echo 'DIGITALÍSIMO Backups: contrato de destinos correcto contra servidores reales (' . implode( ', ', array_keys( $settings ) ) . ") con TLS verificado.\n";
