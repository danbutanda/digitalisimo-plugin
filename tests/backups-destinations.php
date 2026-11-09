<?php
/**
 * Contrato común de los destinos remotos (put/get/size/delete/list/verify/test) en TODOS los destinos, contra
 * servidores simulados fieles a la documentación de cada API (tests/lib/destination-fakes.php):
 *   (a) put sube a un nombre temporal, verifica y sólo entonces publica; no deja temporales; el recibo lleva tipo,
 *       conexión, SHA-256 y tamaño, nunca un secreto;
 *   (b) get descarga en streaming a un archivo idéntico; size da el tamaño o false; list encuentra el respaldo; delete
 *       lo borra y repetirlo no es un error;
 *   (c) una copia que no supera la verificación no se publica y su temporal se retira;
 *   (d) test() sube, verifica, descarga, lista y borra sin dejar nada.
 * Y lo propio de cada uno: S3 multipart reanudable (sólo las partes pendientes), 200 con <Error>, NoSuchUpload,
 * abandono con AbortMultipartUpload, endpoints y regiones de cada proveedor compatible y errores de credenciales
 * permanentes; Dropbox con incorrect_offset y sesión perdida; pCloud por región (SHA-256 en Europa, SHA-1 en EE. UU.);
 * WebDAV con Overwrite: F; FTP/FTPS (orden AUTH TLS → USER → PBSZ/PROT, TLS en datos, implícito, pasivo siempre al
 * servidor de control, reanudación con APPE); Google Drive y OneDrive con get en streaming a disco.
 * Cada caso escribe «FALLA (x)» si falla.
 */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['bk_fail'] = 0;
$root = sys_get_temp_dir() . '/digitalisimo-destinations-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/private', 0700, true );
$GLOBALS['bk_root'] = $root;

// Lo mínimo de WordPress que usan Google Drive y OneDrive (API HTTP simulada) y los mensajes.
function wp_basename( $path ) { return basename( $path ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function is_wp_error( $value ) { return false; }
function wp_remote_retrieve_response_code( $response ) { return (int) ( $response['response']['code'] ?? 0 ); }
function wp_remote_retrieve_body( $response ) { return (string) ( $response['body'] ?? '' ); }
function wp_remote_retrieve_header( $response, $name ) { return (string) ( $response['headers'][ strtolower( $name ) ] ?? '' ); }
function wp_remote_post( $url, $args = array() ) { return Bk_Fake_Drives::handle( 'POST', $url, $args ); }
function wp_remote_get( $url, $args = array() ) { return Bk_Fake_Drives::handle( 'GET', $url, $args ); }
function wp_remote_request( $url, $args = array() ) { return Bk_Fake_Drives::handle( $args['method'] ?? 'GET', $url, $args ); }

require __DIR__ . '/../digitalisimo-backups/includes/class-backups-job-store.php';
require __DIR__ . '/../digitalisimo-backups/includes/security/class-credentials.php';
foreach ( array( 'http', 'destination', 'destinations', 'destination-sftp', 'destination-ftp', 'destination-webdav', 'destination-cloud-drive', 'destination-dropbox', 'destination-s3', 'destination-pcloud' ) as $part ) require __DIR__ . '/../digitalisimo-backups/includes/destinations/class-' . $part . '.php';
require __DIR__ . '/lib/destination-fakes.php';

/** Google Drive y OneDrive simulados por la API HTTP de WordPress (subida reanudable por Content-Range). */
final class Bk_Fake_Drives {
	public static $files = array();
	public static $sessions = array();
	public static $corrupt = false;
	public static $log = array();
	private static function reply( $code, $body = '', array $headers = array() ) { return array( 'response' => array( 'code' => $code ), 'body' => is_string( $body ) ? $body : json_encode( $body ), 'headers' => $headers ); }
	public static function handle( $method, $url, $args ) {
		self::$log[] = array( $method, preg_replace( '/\?.*/', '', $url ), ! empty( $args['stream'] ) );
		if ( in_array( $url, array( 'https://oauth2.googleapis.com/token', 'https://login.microsoftonline.com/common/oauth2/v2.0/token' ), true ) ) return self::reply( 200, array( 'access_token' => 'acceso-drive' ) );
		if ( 'Bearer acceso-drive' !== ( $args['headers']['Authorization'] ?? 'Bearer acceso-drive' ) ) return self::reply( 401 );
		if ( 0 === strpos( $url, 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable' ) ) {
			$meta = json_decode( $args['body'], true );
			$id = 'g' . bin2hex( random_bytes( 4 ) );
			self::$sessions[ 'https://upload.fake/' . $id ] = array( 'id' => $id, 'name' => $meta['name'], 'data' => '' );
			return self::reply( 200, '', array( 'location' => 'https://upload.fake/' . $id ) );
		}
		if ( preg_match( '#^https://graph\.microsoft\.com/v1\.0/me/drive/root:/(.*):/createUploadSession$#', $url, $match ) ) {
			$id = 'o' . bin2hex( random_bytes( 4 ) );
			self::$sessions[ 'https://upload.fake/' . $id ] = array( 'id' => $id, 'name' => rawurldecode( basename( $match[1] ) ), 'folder' => rawurldecode( dirname( $match[1] ) ), 'data' => '' );
			return self::reply( 200, array( 'uploadUrl' => 'https://upload.fake/' . $id ) );
		}
		if ( isset( self::$sessions[ $url ] ) ) {
			if ( 'DELETE' === $method ) { unset( self::$sessions[ $url ] ); return self::reply( 204 ); }
			preg_match( '#^bytes (\d+)-(\d+)/(\d+)$#', $args['headers']['Content-Range'], $range );
			if ( (int) $range[1] !== strlen( self::$sessions[ $url ]['data'] ) ) return self::reply( 416 );
			self::$sessions[ $url ]['data'] .= $args['body'];
			if ( strlen( self::$sessions[ $url ]['data'] ) < (int) $range[3] ) return self::reply( 308 );
			$session = self::$sessions[ $url ];
			unset( self::$sessions[ $url ] );
			self::$files[ $session['id'] ] = array( 'name' => $session['name'], 'folder' => $session['folder'] ?? '', 'data' => $session['data'] );
			return self::reply( 201, array( 'id' => $session['id'] ) );
		}
		if ( preg_match( '#^https://(www\.googleapis\.com/drive/v3/files|graph\.microsoft\.com/v1\.0/me/drive/items)/([^/?]+)(/content|\?alt=media)?#', $url, $match ) && ( 'https://www.googleapis.com/drive/v3/files?' !== substr( $url, 0, 43 ) ) ) {
			$id = rawurldecode( $match[2] );
			if ( ! isset( self::$files[ $id ] ) ) return self::reply( 404 );
			if ( 'DELETE' === $method ) { unset( self::$files[ $id ] ); return self::reply( 204 ); }
			if ( ! empty( $match[3] ) ) {
				$data = self::$files[ $id ]['data'];
				if ( self::$corrupt ) $data[0] = chr( ord( $data[0] ) ^ 1 );
				if ( empty( $args['stream'] ) || empty( $args['filename'] ) ) return self::reply( 500, 'Se esperaba una descarga en streaming a disco.' );
				file_put_contents( $args['filename'], $data );
				return self::reply( 200 );
			}
			// Como Graph: parentReference.path codificado y precedido de la unidad («/drive/root:/Carpeta»).
			return self::reply( 200, array( 'id' => $id, 'name' => self::$files[ $id ]['name'], 'size' => (string) strlen( self::$files[ $id ]['data'] ), 'parentReference' => array( 'path' => '/drive/root:' . ( '' !== ( self::$files[ $id ]['folder'] ?? '' ) && '.' !== self::$files[ $id ]['folder'] ? '/' . str_replace( '%2F', '/', rawurlencode( self::$files[ $id ]['folder'] ) ) : '' ) ) ) );
		}
		if ( 0 === strpos( $url, 'https://www.googleapis.com/drive/v3/files?' ) || false !== strpos( $url, '/children' ) ) {
			$items = array();
			foreach ( self::$files as $id => $file ) $items[] = array( 'id' => $id, 'name' => $file['name'], 'size' => strlen( $file['data'] ), 'file' => array() );
			return self::reply( 200, false !== strpos( $url, 'googleapis' ) ? array( 'files' => $items ) : array( 'value' => $items ) );
		}
		return self::reply( 400, 'ruta desconocida ' . $url );
	}
}

$case = function( $label, callable $test ) {
	try { $test(); echo "ok ($label)\n"; }
	catch ( Throwable $e ) { $GLOBALS['bk_fail']++; echo 'FALLA (' . $label . '): ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n"; }
};
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
$expect_failure = function( callable $callback, $needle = '', $class = 'Exception' ) {
	try { $callback(); } catch ( Throwable $e ) {
		if ( ! $e instanceof $class ) throw new RuntimeException( 'Se esperaba ' . $class . ' y llegó ' . get_class( $e ) . ': ' . $e->getMessage() );
		if ( '' !== $needle && false === strpos( $e->getMessage(), $needle ) ) throw new RuntimeException( 'Mensaje inesperado: ' . $e->getMessage() );
		return $e;
	}
	throw new RuntimeException( 'La operación debía fallar y no falló.' );
};
$file_of = function( $size, $name = 'digitalisimo-backup-sitio-20261109-120000-abcd1234.zip' ) use ( $root ) {
	$path = $root . '/' . $name;
	$handle = fopen( $path, 'wb' );
	for ( $left = $size; $left > 0; $left -= 1048576 ) fwrite( $handle, random_bytes( (int) min( 1048576, $left ) ) );
	fclose( $handle );
	return $path;
};
$context = function( $connection ) use ( $root ) { return array( 'temp_dir' => $root . '/private', 'connection' => $connection, 'state' => new Digitalisimo_Backups_Transfer_State( $root . '/private/state-' . $connection . '.json' ) ); };
/** Las secretas que nunca deben aparecer en un recibo, un listado o un mensaje. */
$secrets = array( 'S3-secreta-123/abc+def', 'Compat-secreta-456', 'refresh-dropbox-0123', 'pcloud-token-1', 'WebDav-Clave-9', 'Ftp-Clave-1', 'acceso-drive' );
$no_secret = function( $value, $what ) use ( $secrets, $check ) { foreach ( $secrets as $secret ) $check( false === strpos( json_encode( $value ), $secret ), $what . ' lleva un secreto.' ); };

// Un transporte HTTP por servidor: S3 (AWS y compatibles), Dropbox, pCloud y WebDAV.
$s3 = new Bk_Fake_S3( 'mi-bucket', 'S3-secreta-123/abc+def', 'eu-west-1' );
$compat = new Bk_Fake_S3( 'compat-bucket', 'Compat-secreta-456', 'us-east-1' );
$dropbox = new Bk_Fake_Dropbox();
$pcloud = new Bk_Fake_Pcloud();
$webdav = new Bk_Fake_Webdav( 'https://nube.ejemplo.test/remote.php/dav/files/ana/', 'ana', 'WebDav-Clave-9' );
$routes = array();
Digitalisimo_Backups_Http::$transport = function( array $request ) use ( &$routes, $s3, $compat, $dropbox, $pcloud, $webdav ) {
	$host = parse_url( $request['url'], PHP_URL_HOST );
	$routes[] = $host;
	if ( preg_match( '/amazonaws\.com$/', $host ) ) return $s3( $request );
	if ( preg_match( '/(dropboxapi|dropbox)\.com$/', $host ) ) return $dropbox( $request );
	if ( preg_match( '/pcloud\.com$/', $host ) ) return $pcloud( $request );
	if ( 'nube.ejemplo.test' === $host ) return $webdav( $request );
	return $compat( $request );
};
Bk_Fake_Ftp::install();

$destinations = array(
	's3' => array( 'Digitalisimo_Backups_Destination_S3', array( 's3_region' => 'eu-west-1', 's3_bucket' => 'mi-bucket', 's3_access_key' => 'AKIDPRUEBA', 's3_secret_key' => 'S3-secreta-123/abc+def', 's3_path' => 'digitalisimo-backups', 's3_style' => 'auto', 's3_storage_class' => 'STANDARD_IA' ), function() use ( $s3 ) { return array_keys( $s3->objects ); }, function( $on ) use ( $s3 ) { $s3->faults['corrupt_get'] = $on; } ),
	's3compat' => array( 'Digitalisimo_Backups_Destination_S3_Compatible', array( 's3compat_provider' => 'other', 's3compat_endpoint' => 'https://minio.ejemplo.test:9443', 's3compat_signing_region' => 'us-east-1', 's3compat_bucket' => 'compat-bucket', 's3compat_access_key' => 'minioadmin', 's3compat_secret_key' => 'Compat-secreta-456', 's3compat_path' => 'respaldos/sitio', 's3compat_style' => 'auto' ), function() use ( $compat ) { return array_keys( $compat->objects ); }, function( $on ) use ( $compat ) { $compat->faults['corrupt_get'] = $on; } ),
	'dropbox' => array( 'Digitalisimo_Backups_Destination_Dropbox', array( 'dropbox_app_key' => 'appkey1234', 'dropbox_refresh_token' => 'refresh-dropbox-0123', 'dropbox_folder' => '/Respaldos ñandú' ), function() use ( $dropbox ) { return array_map( function( $file ) { return $file['path']; }, array_values( $dropbox->files ) ); }, function( $on ) use ( $dropbox ) { $dropbox->faults['corrupt_store'] = $on; } ),
	'pcloud' => array( 'Digitalisimo_Backups_Destination_Pcloud', array( 'pcloud_access_token' => 'pcloud-token-1', 'pcloud_host' => 'eapi.pcloud.com', 'pcloud_folder' => '/Digitalisimo Backups' ), function() use ( $pcloud ) { return array_map( function( $file ) { return $file['name']; }, array_values( $pcloud->files ) ); }, function( $on ) use ( $pcloud ) { $pcloud->faults['corrupt_store'] = $on; } ),
	'webdav' => array( 'Digitalisimo_Backups_Destination_Webdav', array( 'webdav_url' => 'https://nube.ejemplo.test/remote.php/dav/files/ana/', 'webdav_user' => 'ana', 'webdav_password' => 'WebDav-Clave-9', 'webdav_path' => '/respaldos/sitio' ), function() use ( $webdav ) { return array_keys( $webdav->files ); }, function( $on ) use ( $webdav ) { $webdav->faults['corrupt_get'] = $on; } ),
	'ftp' => array( 'Digitalisimo_Backups_Destination_Ftp', array( 'ftp_host' => 'ftp.ejemplo.test', 'ftp_port' => '21', 'ftp_security' => 'explicit', 'ftp_user' => 'respaldos', 'ftp_password' => 'Ftp-Clave-1', 'ftp_path' => '/respaldos', 'ftp_passive' => 'passive' ), function() { return array_keys( Bk_Fake_Ftp::$files ); }, function( $on ) { Bk_Fake_Ftp::$faults['corrupt_get'] = $on; } ),
	'google' => array( 'Digitalisimo_Backups_Destination_Google', array( 'google_client_id' => 'cid', 'google_client_secret' => 'csec', 'google_refresh_token' => 'rt', 'google_folder_id' => '' ), function() { return array_map( function( $file ) { return $file['name']; }, array_values( Bk_Fake_Drives::$files ) ); }, function( $on ) { Bk_Fake_Drives::$corrupt = $on; } ),
	'onedrive' => array( 'Digitalisimo_Backups_Destination_Onedrive', array( 'onedrive_client_id' => 'cid', 'onedrive_client_secret' => 'csec', 'onedrive_refresh_token' => 'rt', 'onedrive_folder' => 'Digitalisimo Backups' ), function() { return array_map( function( $file ) { return $file['name']; }, array_values( Bk_Fake_Drives::$files ) ); }, function( $on ) { Bk_Fake_Drives::$corrupt = $on; } ),
);

foreach ( $destinations as $type => list( $class, $settings, $names, $corrupt ) ) {
	$case( $type . ' (a-d) contrato', function() use ( $type, $class, $settings, $names, $corrupt, $context, $file_of, $check, $expect_failure, $no_secret, $root ) {
		$check( $class::ready( $settings ), 'La configuración de prueba no está lista.' );
		$destination = new $class( $settings, $context( $type ) );
		// Con multipart en S3 y varias partes en Dropbox (fragmentos de 8 MiB): 17 MiB y 3 bytes.
		$file = $file_of( in_array( $type, array( 's3', 's3compat', 'dropbox' ), true ) ? 17825795 : 300001 );
		$receipt = $destination->put( $file );
		$no_secret( $receipt, 'El recibo' );
		$check( $type === $receipt['provider'] && $type === $receipt['connection'] && hash_file( 'sha256', $file ) === $receipt['sha256'] && filesize( $file ) === (int) $receipt['size'], 'El recibo no tiene tipo, conexión, SHA-256 y tamaño: ' . json_encode( $receipt ) );
		$stored = $names();
		$check( 1 === count( array_filter( $stored, function( $name ) use ( $file ) { return basename( $file ) === basename( $name ); } ) ), 'El respaldo no quedó publicado con su nombre: ' . json_encode( $stored ) );
		$check( ! array_filter( $stored, function( $name ) { return false !== strpos( $name, '.part-' ); } ), 'Quedó un temporal en el destino: ' . json_encode( $stored ) );
		$check( filesize( $file ) === $destination->size( $receipt ), 'size() no da el tamaño del respaldo.' );
		$back = $root . '/private/descarga';
		$destination->get( $receipt, $back );
		$check( hash_file( 'sha256', $back ) === hash_file( 'sha256', $file ), 'get() no descargó una copia idéntica.' );
		unlink( $back );
		$listed = array_values( array_filter( $destination->list(), function( $item ) use ( $file ) { return basename( $file ) === $item['name']; } ) );
		$check( 1 === count( $listed ) && filesize( $file ) === (int) $listed[0]['size'], 'list() no encuentra el respaldo con su tamaño.' );
		$no_secret( $destination->list(), 'El listado' );
		$copy = $root . '/private/descarga-listada';
		$destination->get( $listed[0]['remote'], $copy );
		$check( hash_file( 'sha256', $copy ) === hash_file( 'sha256', $file ), 'El recibo de list() no sirve para get().' );
		unlink( $copy );
		$destination->delete( $listed[0]['remote'] );
		$check( false === $destination->size( $receipt ) && ! $names(), 'delete() no borró el respaldo.' );
		$destination->delete( $receipt );
		// (c) Una copia que no coincide no se publica y su temporal se retira.
		$corrupt( true );
		try {
			$expect_failure( function() use ( $destination, $file ) { $destination->put( $file ); }, 'no coincide' );
		} finally { $corrupt( false ); }
		$check( ! $names(), 'Una copia sin verificar quedó en el destino: ' . json_encode( $names() ) );
		// (d) Probar conexión.
		$steps = $destination->test();
		$check( count( $steps ) >= 4 && ! $names(), 'test() dejó archivos o no hizo todos los pasos: ' . json_encode( $names() ) );
		unlink( $file );
	} );
}

// ---------------------------------------------------------------------------------------------------------- S3

$case( 's3 reanudación multipart', function() use ( $s3, $context, $file_of, $check, $expect_failure, $destinations, $root ) {
	$settings = $destinations['s3'][1];
	$file = $file_of( 4 * 8388608 + 5 );
	$s3->faults['fail_part'] = 3;
	$first = new Digitalisimo_Backups_Destination_S3( $settings, $context( 's3' ) );
	$expect_failure( function() use ( $first, $file ) { $first->put( $file ); }, 'corte simulado' );
	$state = json_decode( file_get_contents( $root . '/private/state-s3.json' ), true );
	$check( 2 === count( $state['parts'] ) && '' !== (string) $state['upload_id'] && false !== strpos( $state['key'], '.part-' ), 'El estado no guardó las dos partes confirmadas: ' . json_encode( $state ) );
	$check( false === strpos( json_encode( $state ), 'S3-secreta' ) && false === strpos( json_encode( $state ), 'AKIDPRUEBA' ), 'El estado reanudable guarda una credencial.' );
	$s3->log = array();
	$second = new Digitalisimo_Backups_Destination_S3( $settings, $context( 's3' ) );
	$receipt = $second->put( $file );
	$parts = array_values( array_filter( $s3->log, function( $entry ) { return 'PUT' === $entry['method'] && isset( $entry['query']['partNumber'] ) && ! isset( $entry['headers']['x-amz-copy-source'] ); } ) );
	$check( array( '3', '4', '5' ) === array_map( function( $entry ) { return $entry['query']['partNumber']; }, $parts ), 'El reintento no subió sólo las partes 3 a 5: ' . json_encode( array_column( array_column( $parts, 'query' ), 'partNumber' ) ) );
	$check( ! array_filter( $s3->log, function( $entry ) { return 'POST' === $entry['method'] && array_key_exists( 'uploads', $entry['query'] ); } ), 'El reintento empezó otra subida por partes.' );
	$check( ! is_file( $root . '/private/state-s3.json' ) && hash_file( 'sha256', $file ) === hash( 'sha256', $s3->objects[ $receipt['key'] ] ), 'Tras publicar quedó el estado o el objeto no es idéntico.' );
	$check( ! $s3->uploads, 'Quedó una subida por partes abierta.' );
	$second->delete( $receipt );
	unlink( $file );
} );
$case( 's3 NoSuchUpload empieza de nuevo', function() use ( $s3, $context, $file_of, $check, $destinations, $root ) {
	$file = $file_of( 2 * 8388608 + 1 );
	$s3->faults['fail_part'] = 2;
	$first = new Digitalisimo_Backups_Destination_S3( $destinations['s3'][1], $context( 's3' ) );
	try { $first->put( $file ); } catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$s3->faults['forget_upload'] = true;
	$receipt = ( new Digitalisimo_Backups_Destination_S3( $destinations['s3'][1], $context( 's3' ) ) )->put( $file );
	$check( hash_file( 'sha256', $file ) === hash( 'sha256', $s3->objects[ $receipt['key'] ] ), 'Tras NoSuchUpload no se publicó el respaldo completo.' );
	( new Digitalisimo_Backups_Destination_S3( $destinations['s3'][1], $context( 's3' ) ) )->delete( $receipt );
	unlink( $file );
} );
$case( 's3 200 con <Error> no es éxito', function() use ( $s3, $context, $file_of, $check, $expect_failure, $destinations ) {
	$file = $file_of( 8388608 + 10 );
	$s3->faults['complete_error'] = true;
	$destination = new Digitalisimo_Backups_Destination_S3( $destinations['s3'][1], $context( 's3-complete' ) );
	$expect_failure( function() use ( $destination, $file ) { $destination->put( $file ); }, 'InternalError' );
	$check( ! array_filter( array_keys( $s3->objects ), function( $key ) { return false === strpos( $key, '.part-' ); } ), 'Se publicó un respaldo tras un CompleteMultipartUpload fallido.' );
	$s3->faults['copy_error'] = true;
	$small = $file_of( 1000, 'pequeno.zip' );
	$expect_failure( function() use ( $destination, $small ) { $destination->put( $small ); }, 'publicación' );
	$check( ! array_filter( array_keys( $s3->objects ), function( $key ) { return 'digitalisimo-backups/pequeno.zip' === $key; } ), 'Un CopyObject 200 con <Error> publicó el respaldo.' );
	$destination->abandon();
	$s3->objects = array();
	$s3->uploads = array();
	unlink( $file );
	unlink( $small );
} );
$case( 's3 abandon aborta la subida por partes', function() use ( $s3, $context, $file_of, $check, $destinations ) {
	$file = $file_of( 3 * 8388608 );
	$s3->faults['fail_part'] = 2;
	try { ( new Digitalisimo_Backups_Destination_S3( $destinations['s3'][1], $context( 's3-abandon' ) ) )->put( $file ); } catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$check( 1 === count( $s3->uploads ), 'No quedó la subida por partes interrumpida que abandonar.' );
	( new Digitalisimo_Backups_Destination_S3( $destinations['s3'][1], $context( 's3-abandon' ) ) )->abandon();
	$check( ! $s3->uploads && ! array_filter( array_keys( $s3->objects ) ), 'abandon() no abortó la subida por partes.' );
	unlink( $file );
} );
$case( 's3 credenciales y región: errores permanentes sin secretos', function() use ( $context, $file_of, $check, $expect_failure, $destinations, $secrets ) {
	$file = $file_of( 2000, 'cred.zip' );
	$bad = array( 's3_secret_key' => 'otra-secreta' ) + $destinations['s3'][1];
	$e = $expect_failure( function() use ( $bad, $context, $file ) { ( new Digitalisimo_Backups_Destination_S3( $bad, $context( 's3-bad' ) ) )->put( $file ); }, 'SignatureDoesNotMatch', 'Digitalisimo_Backups_Permanent_Error' );
	foreach ( $secrets as $secret ) $check( false === strpos( $e->getMessage(), $secret ), 'El mensaje lleva un secreto.' );
	$check( false === strpos( $e->getMessage(), 'AWS4-HMAC' ) && false === strpos( $e->getMessage(), 'Signature=' ), 'El mensaje lleva la cabecera de autorización.' );
	$region = array( 's3_region' => 'us-east-2' ) + $destinations['s3'][1];
	$expect_failure( function() use ( $region, $context, $file ) { ( new Digitalisimo_Backups_Destination_S3( $region, $context( 's3-region' ) ) )->put( $file ); }, 'región', 'Digitalisimo_Backups_Permanent_Error' );
	unlink( $file );
} );
$case( 's3 cada petición con SigV4, payload firmado y clase de almacenamiento', function() use ( $s3, $routes, $check ) {
	foreach ( $s3->log as $entry ) $check( 0 === strpos( $entry['headers']['authorization'], 'AWS4-HMAC-SHA256 ' ) && 'UNSIGNED-PAYLOAD' !== $entry['headers']['x-amz-content-sha256'], 'Una petición a S3 no va firmada con SigV4 y payload firmado.' );
	$put = array_filter( $s3->log, function( $entry ) { return 'PUT' === $entry['method'] && isset( $entry['headers']['x-amz-copy-source'] ); } );
	$check( $put && ! array_filter( $put, function( $entry ) { return 'STANDARD_IA' !== ( $entry['headers']['x-amz-storage-class'] ?? '' ); } ), 'La publicación no conserva la clase de almacenamiento elegida.' );
	$check( ! array_filter( $s3->log, function( $entry ) { return ! preg_match( '/^mi-bucket\.s3\.(eu-west-1|us-east-2)\.amazonaws\.com$/', $entry['host'] ); } ), 'Amazon S3 no usó el estilo virtual del endpoint regional.' );
} );
$case( 's3 compatibles: endpoint, región de firma y estilo de cada proveedor', function() use ( $check, $context ) {
	$base = array( 's3compat_bucket' => 'b-1', 's3compat_access_key' => 'k', 's3compat_secret_key' => 's', 's3compat_path' => '' );
	$expected = array(
		'digitalocean' => array( array( 's3compat_do_region' => 'fra1' ), 'b-1.fra1.digitaloceanspaces.com', 'us-east-1' ),
		'wasabi' => array( array( 's3compat_wasabi_region' => 'eu-central-2' ), 'b-1.s3.eu-central-2.wasabisys.com', 'eu-central-2' ),
		'r2' => array( array( 's3compat_account' => str_repeat( 'ab', 16 ), 's3compat_jurisdiction' => 'eu' ), str_repeat( 'ab', 16 ) . '.eu.r2.cloudflarestorage.com', 'auto' ),
		'backblaze' => array( array( 's3compat_endpoint' => 's3.us-west-004.backblazeb2.com' ), 's3.us-west-004.backblazeb2.com', 'us-west-004' ),
		'linode' => array( array( 's3compat_endpoint' => 'us-ord-10.linodeobjects.com' ), 'us-ord-10.linodeobjects.com', 'us-ord-10' ),
		'vultr' => array( array( 's3compat_endpoint' => 'ewr1.vultrobjects.com' ), 'ewr1.vultrobjects.com', 'ewr1' ),
		'other' => array( array( 's3compat_endpoint' => 'https://minio.local:9000', 's3compat_signing_region' => 'eu-1' ), 'minio.local:9000', 'eu-1' ),
	);
	foreach ( $expected as $provider => list( $extra, $host, $region ) ) {
		$seen = array();
		Digitalisimo_Backups_Http::$transport = function( $request ) use ( &$seen ) { $seen[] = $request; return array( 'code' => 404, 'headers' => array(), 'body' => '' ); };
		$destination = new Digitalisimo_Backups_Destination_S3_Compatible( array( 's3compat_provider' => $provider, 's3compat_style' => 'auto' ) + $extra + $base, $context( 'c-' . $provider ) );
		$check( false === $destination->size( array( 'key' => 'x.zip' ) ), 'HEAD de un objeto inexistente no dio false.' );
		$url = parse_url( $seen[0]['url'] );
		$check( $host === $url['host'] . ( isset( $url['port'] ) ? ':' . $url['port'] : '' ) && 'https' === $url['scheme'], $provider . ': endpoint ' . $seen[0]['url'] );
		$check( false !== strpos( $seen[0]['headers']['authorization'], '/' . $region . '/s3/aws4_request' ), $provider . ': región de firma ' . $seen[0]['headers']['authorization'] );
	}
	$check( 'us-west-004' === Digitalisimo_Backups_Destination_S3_Compatible::provider_region( 'backblaze', 's3.us-west-004.backblazeb2.com' ) && '' === Digitalisimo_Backups_Destination_S3_Compatible::provider_region( 'backblaze', 'evil.com' ), 'Endpoint de Backblaze mal validado.' );
} );

// ---------------------------------------------------------------------------------------------------------- Dropbox
Digitalisimo_Backups_Http::$transport = function( array $request ) use ( $s3, $compat, $dropbox, $pcloud, $webdav ) {
	$host = parse_url( $request['url'], PHP_URL_HOST );
	if ( preg_match( '/amazonaws\.com$/', $host ) ) return $s3( $request );
	if ( preg_match( '/(dropboxapi|dropbox)\.com$/', $host ) ) return $dropbox( $request );
	if ( preg_match( '/pcloud\.com$/', $host ) ) return $pcloud( $request );
	if ( 'nube.ejemplo.test' === $host ) return $webdav( $request );
	return $compat( $request );
};
$case( 'dropbox respuesta perdida: incorrect_offset y sigue', function() use ( $dropbox, $context, $file_of, $check, $expect_failure, $destinations, $root ) {
	$file = $file_of( 3 * 8388608 + 7 );
	$dropbox->appends = 0;
	$dropbox->faults['drop_reply'] = 2;
	$first = new Digitalisimo_Backups_Destination_Dropbox( $destinations['dropbox'][1], $context( 'dropbox' ) );
	$expect_failure( function() use ( $first, $file ) { $first->put( $file ); }, 'Empty reply' );
	$state = json_decode( file_get_contents( $root . '/private/state-dropbox.json' ), true );
	$check( 8388608 === (int) $state['offset'] && '' !== $state['session'], 'El estado no guardó el desplazamiento confirmado: ' . json_encode( $state ) );
	$dropbox->log = array();
	$receipt = ( new Digitalisimo_Backups_Destination_Dropbox( $destinations['dropbox'][1], $context( 'dropbox' ) ) )->put( $file );
	$appends = array_values( array_filter( $dropbox->log, function( $entry ) { return false !== strpos( $entry['url'], 'append_v2' ); } ) );
	$check( 3 === count( $appends ) && false !== strpos( $appends[0]['arg'], '"offset":8388608' ) && false !== strpos( $appends[1]['arg'], '"offset":16777216' ), 'No siguió desde correct_offset sin reenviar lo confirmado: ' . json_encode( array_column( $appends, 'arg' ) ) );
	$check( ! array_filter( $dropbox->log, function( $entry ) { return false !== strpos( $entry['url'], 'upload_session/start' ); } ), 'Empezó otra sesión aunque la anterior seguía viva.' );
	$check( hash_file( 'sha256', $file ) === hash( 'sha256', $dropbox->files[ strtolower( $receipt['path'] ) ]['data'] ), 'El archivo publicado no es idéntico.' );
	( new Digitalisimo_Backups_Destination_Dropbox( $destinations['dropbox'][1], $context( 'dropbox' ) ) )->delete( $receipt );
	unlink( $file );
} );
$case( 'dropbox sesión caducada: empieza de nuevo', function() use ( $dropbox, $context, $file_of, $check, $destinations ) {
	$file = $file_of( 2 * 8388608 + 3 );
	$dropbox->appends = 0;
	$dropbox->log = array();
	$dropbox->faults = array( 'lose_session' => 2 );
	$receipt = ( new Digitalisimo_Backups_Destination_Dropbox( $destinations['dropbox'][1], $context( 'dropbox-lost' ) ) )->put( $file );
	$check( hash_file( 'sha256', $file ) === hash( 'sha256', $dropbox->files[ strtolower( $receipt['path'] ) ]['data'] ) && 2 === count( array_filter( $dropbox->log, function( $entry ) { return false !== strpos( $entry['url'], 'upload_session/start' ); } ) ), 'Tras perder la sesión no se empezó otra ni se publicó el respaldo completo.' );
	( new Digitalisimo_Backups_Destination_Dropbox( $destinations['dropbox'][1], $context( 'dropbox-lost' ) ) )->delete( $receipt );
	$dropbox->faults = array();
	unlink( $file );
} );
$case( 'dropbox PKCE (RFC 7636) y content_hash (vectores de Python)', function() use ( $check, $dropbox ) {
	$check( 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM' === Digitalisimo_Backups_Destination_Dropbox::code_challenge( 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk' ), 'code_challenge S256 distinto del vector del RFC 7636.' );
	$verifier = Digitalisimo_Backups_Destination_Dropbox::code_verifier();
	$check( preg_match( '/^[A-Za-z0-9._~-]{43,128}$/', $verifier ), 'code_verifier fuera de las reglas de Dropbox.' );
	// Vectores calculados con hashlib (bloques de 4 MiB, SHA-256 de los SHA-256 binarios).
	$dir = $GLOBALS['bk_root'];
	file_put_contents( $dir . '/vacio', '' );
	file_put_contents( $dir . '/abc', str_repeat( 'abc', 3000000 ) );
	$check( 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855' === Digitalisimo_Backups_Destination_Dropbox::content_hash( $dir . '/vacio' ), 'content_hash del archivo vacío.' );
	$check( '2ba409c894272c1313e4cbd09431d060901365dc8b109ba2adc449b71e4d3a71' === Digitalisimo_Backups_Destination_Dropbox::content_hash( $dir . '/abc' ), 'content_hash de 9 000 000 bytes distinto del calculado con Python.' );
	unlink( $dir . '/vacio' );
	unlink( $dir . '/abc' );
	// Un nombre no ASCII viaja escapado (\uXXXX) en Dropbox-API-Arg (el simulado rechaza bytes no ASCII).
	$check( ! array_filter( $dropbox->log, function( $entry ) { return preg_match( '/[\x80-\xff]/', $entry['arg'] ); } ), 'Dropbox-API-Arg con caracteres no ASCII sin escapar.' );
	// El refresh token se usa sin App secret (PKCE) y el token de acceso nunca se guarda en el estado.
	$check( ! array_filter( $dropbox->log, function( $entry ) { return '' !== $entry['auth'] && 'Bearer sl.acceso' !== $entry['auth']; } ), 'Una llamada a Dropbox no usó el token de acceso renovado.' );
} );

// ---------------------------------------------------------------------------------------------------------- pCloud
$case( 'pcloud EE. UU.: SHA-1 del proveedor y región', function() use ( $pcloud, $context, $file_of, $check, $expect_failure ) {
	$pcloud->europe = false;
	$file = $file_of( 5000, 'us.zip' );
	$us = new Digitalisimo_Backups_Destination_Pcloud( array( 'pcloud_access_token' => 'pcloud-token-1', 'pcloud_host' => 'api.pcloud.com', 'pcloud_folder' => '/R' ), $context( 'pcloud-us' ) );
	$receipt = $us->put( $file );
	$check( 'us.zip' === $pcloud->files[ $receipt['id'] ]['name'], 'pCloud EE. UU. no publicó el respaldo.' );
	$us->delete( $receipt );
	$wrong = new Digitalisimo_Backups_Destination_Pcloud( array( 'pcloud_access_token' => 'pcloud-token-1', 'pcloud_host' => 'eapi.pcloud.com', 'pcloud_folder' => '/R' ), $context( 'pcloud-wrong' ) );
	$expect_failure( function() use ( $wrong, $file ) { $wrong->put( $file ); }, 'Conecta pCloud de nuevo', 'Digitalisimo_Backups_Permanent_Error' );
	$pcloud->europe = true;
	$check( 'eapi.pcloud.com' === Digitalisimo_Backups_Destination_Pcloud::api_host( 'eapi.pcloud.com' ) && 'api.pcloud.com' === Digitalisimo_Backups_Destination_Pcloud::api_host( 'evil.example.com', '1' ) && 'eapi.pcloud.com' === Digitalisimo_Backups_Destination_Pcloud::api_host( 'evil.example.com', '2' ), 'El servidor de pCloud no se limita a los dos oficiales.' );
	$check( ! array_filter( $pcloud->log, function( $entry ) { return isset( $entry['params']['access_token'] ); } ), 'El registro simulado guardó el token.' );
	unlink( $file );
} );

// ---------------------------------------------------------------------------------------------------------- WebDAV
$case( 'webdav MOVE con Overwrite: F y PROPFIND', function() use ( $webdav, $context, $file_of, $check, $expect_failure, $destinations ) {
	$destination = new Digitalisimo_Backups_Destination_Webdav( $destinations['webdav'][1], $context( 'webdav' ) );
	$file = $file_of( 1000, 'repetido.zip' );
	$webdav->files['respaldos/sitio/repetido.zip'] = 'otro contenido';
	$expect_failure( function() use ( $destination, $file ) { $destination->put( $file ); }, 'no se sobrescribe' );
	$check( 'otro contenido' === $webdav->files['respaldos/sitio/repetido.zip'] && 1 === count( $webdav->files ), 'Un MOVE sobrescribió un archivo existente o dejó el temporal.' );
	$moves = array_filter( $webdav->log, function( $entry ) { return 'MOVE' === $entry['method']; } );
	$check( $moves && ! array_filter( $moves, function( $entry ) { return 'F' !== ( $entry['headers']['Overwrite'] ?? '' ) || 0 !== strpos( $entry['headers']['Destination'], 'https://' ); } ), 'MOVE sin Overwrite: F o con Destination relativo.' );
	$check( 14 === $destination->size( array( 'path' => 'respaldos/sitio/repetido.zip' ) ) && false === $destination->size( array( 'path' => 'respaldos/sitio/no.zip' ) ), 'PROPFIND no da el tamaño.' );
	$bad = new Digitalisimo_Backups_Destination_Webdav( array( 'webdav_password' => 'mala' ) + $destinations['webdav'][1], $context( 'webdav-bad' ) );
	$expect_failure( function() use ( $bad, $file ) { $bad->put( $file ); }, 'rechazó el usuario', 'Digitalisimo_Backups_Permanent_Error' );
	$http = new Digitalisimo_Backups_Destination_Webdav( array( 'webdav_url' => 'http://nube.ejemplo.test/' ) + $destinations['webdav'][1], $context( 'webdav-http' ) );
	$expect_failure( function() use ( $http, $file ) { $http->put( $file ); }, 'http://', 'Digitalisimo_Backups_Permanent_Error' );
	$webdav->files = array();
	unlink( $file );
} );

// ---------------------------------------------------------------------------------------------------------- FTP
$case( 'ftp explícito: AUTH TLS antes de USER, PROT P, TLS en datos y pasivo al servidor de control', function() use ( $context, $file_of, $check, $destinations ) {
	Bk_Fake_Ftp::reset( 'explicit' );
	$file = $file_of( 4000, 'orden.zip' );
	$destination = new Digitalisimo_Backups_Destination_Ftp( $destinations['ftp'][1], $context( 'ftp-orden' ) );
	$receipt = $destination->put( $file );
	$log = Bk_Fake_Ftp::$log;
	$position = function( $prefix ) use ( $log ) { foreach ( $log as $i => $line ) if ( 0 === strpos( $line, $prefix ) ) return $i; return -1; };
	$check( 0 === $position( 'AUTH TLS' ) && $position( 'USER ' ) > 0 && $position( 'PBSZ 0' ) > $position( 'PASS ' ) && $position( 'PROT P' ) > $position( 'PBSZ 0' ) && $position( 'STOR ' ) > $position( 'PROT P' ), 'Orden FTPS incorrecto: ' . implode( ' | ', $log ) );
	$check( in_array( 'PASS ***', $log, true ) && false === strpos( implode( "\n", $destination->client_log() ), 'Ftp-Clave-1' ), 'El registro de comandos lleva la contraseña.' );
	$stor = $position( 'STOR ' );
	$check( $position( 'RNFR ' ) > $position( 'RETR ' ) && $position( 'RETR ' ) > $stor && false !== strpos( $log[ $stor ], '.part-' ), 'No sube a un temporal, lo verifica descargándolo y después lo renombra.' );
	$check( 'control@ftp.ejemplo.test' === Bk_Fake_Ftp::$tls[0] && count( array_filter( Bk_Fake_Ftp::$tls, function( $entry ) { return 0 === strpos( $entry, 'data@' ); } ) ) >= 2, 'TLS no se activó en el canal de control y en los de datos: ' . implode( ', ', Bk_Fake_Ftp::$tls ) );
	$check( ! array_filter( Bk_Fake_Ftp::$connected, function( $target ) { return 0 !== strpos( $target, 'ftp.ejemplo.test:' ); } ), 'El canal de datos se conectó a otro equipo que el de control: ' . implode( ', ', Bk_Fake_Ftp::$connected ) );
	Bk_Fake_Ftp::$faults['no_epsv'] = true;
	$destination->delete( $receipt );
	$again = new Digitalisimo_Backups_Destination_Ftp( $destinations['ftp'][1], $context( 'ftp-pasv' ) );
	$receipt = $again->put( $file );
	$check( in_array( 'PASV', Bk_Fake_Ftp::$log, true ) && ! array_filter( Bk_Fake_Ftp::$connected, function( $target ) { return 0 === strpos( $target, '10.0.0.7' ); } ), 'Con PASV se conectó a la IP privada de la respuesta.' );
	$again->delete( $receipt );
	unlink( $file );
} );
$case( 'ftp implícito y sin cifrar', function() use ( $context, $file_of, $check, $destinations ) {
	$file = $file_of( 3000, 'modos.zip' );
	Bk_Fake_Ftp::reset( 'implicit' );
	$implicit = new Digitalisimo_Backups_Destination_Ftp( array( 'ftp_security' => 'implicit', 'ftp_port' => '990' ) + $destinations['ftp'][1], $context( 'ftp-implicit' ) );
	$implicit->delete( $implicit->put( $file ) );
	$check( ! in_array( 'AUTH TLS', Bk_Fake_Ftp::$log, true ) && 'control@ftp.ejemplo.test' === Bk_Fake_Ftp::$tls[0] && in_array( 'ftp.ejemplo.test:990', Bk_Fake_Ftp::$connected, true ), 'FTPS implícito no cifró desde el inicio en el puerto 990.' );
	Bk_Fake_Ftp::reset( 'none' );
	$plain = new Digitalisimo_Backups_Destination_Ftp( array( 'ftp_security' => 'none' ) + $destinations['ftp'][1], $context( 'ftp-plain' ) );
	$plain->delete( $plain->put( $file ) );
	$check( ! Bk_Fake_Ftp::$tls && ! in_array( 'AUTH TLS', Bk_Fake_Ftp::$log, true ), 'FTP sin cifrar negoció TLS.' );
	Bk_Fake_Ftp::reset( 'explicit' );
	unlink( $file );
} );
$case( 'ftp corte a mitad: reanuda con APPE', function() use ( $context, $file_of, $check, $expect_failure, $destinations ) {
	Bk_Fake_Ftp::reset( 'explicit' );
	$file = $file_of( 3 * 1048576 + 17, 'corte.zip' );
	Bk_Fake_Ftp::$faults['cut_after'] = 1048576 + 100;
	$first = new Digitalisimo_Backups_Destination_Ftp( $destinations['ftp'][1], $context( 'ftp-corte' ) );
	$expect_failure( function() use ( $first, $file ) { $first->put( $file ); }, 'Se cortó' );
	Bk_Fake_Ftp::$log = array();
	$receipt = ( new Digitalisimo_Backups_Destination_Ftp( $destinations['ftp'][1], $context( 'ftp-corte' ) ) )->put( $file );
	$check( (bool) preg_grep( '/^APPE .*\.part-/', Bk_Fake_Ftp::$log ) && ! preg_grep( '/^STOR /', Bk_Fake_Ftp::$log ), 'El reintento no continuó con APPE: ' . implode( ' | ', Bk_Fake_Ftp::$log ) );
	$check( hash_file( 'sha256', $file ) === hash( 'sha256', Bk_Fake_Ftp::$files['/respaldos/corte.zip'] ), 'El archivo reanudado no es idéntico.' );
	unlink( $file );
	Bk_Fake_Ftp::reset( 'explicit' );
} );
$case( 'ftp contraseña incorrecta: error permanente', function() use ( $context, $file_of, $expect_failure, $destinations ) {
	$file = $file_of( 100, 'pw.zip' );
	$expect_failure( function() use ( $context, $file, $destinations ) { ( new Digitalisimo_Backups_Destination_Ftp( array( 'ftp_password' => 'mala' ) + $destinations['ftp'][1], $context( 'ftp-pw' ) ) )->put( $file ); }, 'rechazó el usuario', 'Digitalisimo_Backups_Permanent_Error' );
	unlink( $file );
} );

// ---------------------------------------------------------------------------------------------------------- Google y OneDrive
$case( 'google y onedrive: get en streaming a disco', function() use ( $check ) {
	$streamed = array_filter( Bk_Fake_Drives::$log, function( $entry ) { return 'GET' === $entry[0] && ( false !== strpos( $entry[1], '/content' ) || false !== strpos( $entry[1], 'drive/v3/files/' ) && $entry[2] ); } );
	$check( $streamed && ! array_filter( $streamed, function( $entry ) { return ! $entry[2]; } ), 'Una descarga de Drive u OneDrive no fue en streaming a disco.' );
} );

// ---------------------------------------------------------------------------------------------------------- Validación de la configuración
$case( 'validación guiada de cada tipo', function() use ( $check, $expect_failure ) {
	$expect_failure( function() { Digitalisimo_Backups_Destination_Webdav::sanitize( array( 'webdav_url' => 'http://nube.test/dav/' ), array() ); }, 'http://' );
	$out = Digitalisimo_Backups_Destination_Webdav::sanitize( array( 'webdav_url' => 'http://nube.test/dav', 'webdav_allow_http' => '1' ), array() );
	$check( 'http://nube.test/dav/' === $out['webdav_url'], 'La URL WebDAV no se normalizó.' );
	$expect_failure( function() { Digitalisimo_Backups_Destination_Webdav::sanitize( array( 'webdav_url' => 'https://user:pw@nube.test/' ), array() ); }, 'sin usuario' );
	$expect_failure( function() { Digitalisimo_Backups_Destination_Ftp::sanitize( array( 'ftp_host' => 'ftp://servidor/' ), array() ); }, 'nombre de host' );
	$check( '990' === Digitalisimo_Backups_Destination_Ftp::sanitize( array( 'ftp_host' => 'f.test', 'ftp_security' => 'implicit', 'ftp_port' => '' ), array() )['ftp_port'], 'FTPS implícito sin puerto no usa el 990.' );
	$expect_failure( function() { Digitalisimo_Backups_Destination_Ftp::sanitize( array( 'ftp_ca' => 'no es un PEM' ), array() ); }, 'PEM' );
	$expect_failure( function() { Digitalisimo_Backups_Destination_S3_Compatible::sanitize( array( 's3compat_provider' => 'other', 's3compat_endpoint' => 'http://minio:9000' ), array() ); }, 'http://' );
	$expect_failure( function() { Digitalisimo_Backups_Destination_S3_Compatible::sanitize( array( 's3compat_provider' => 'backblaze', 's3compat_endpoint' => 's3.evil.com' ), array() ); }, 'backblazeb2.com' );
	$expect_failure( function() { Digitalisimo_Backups_Destination_S3::sanitize( array( 's3_bucket' => 'Bucket_Malo!' ), array() ); }, 'bucket' );
	$check( 'us-east-1' === Digitalisimo_Backups_Destination_S3::sanitize( array( 's3_region' => 'mars-1' ), array() )['s3_region'], 'Una región que no está en la lista se aceptó.' );
	$kept = Digitalisimo_Backups_Destination_S3::sanitize( array( 's3_secret_key' => '' ), array( 's3_secret_key' => 'guardada' ) );
	$check( 'guardada' === $kept['s3_secret_key'], 'Un secreto vacío no conservó el guardado.' );
	$expect_failure( function() { Digitalisimo_Backups_Destination_Dropbox::sanitize( array( 'dropbox_folder' => '/a/../b' ), array() ); }, '..' );
	$check( 'SHA256:' . rtrim( base64_encode( str_repeat( "\x01", 32 ) ), '=' ) === Digitalisimo_Backups_Destination_Sftp::normalize_fingerprint( str_repeat( '01', 32 ) ) && str_repeat( 'AB', 20 ) === Digitalisimo_Backups_Destination_Sftp::normalize_fingerprint( implode( ':', str_split( str_repeat( 'ab', 20 ), 2 ) ) ) && '' === Digitalisimo_Backups_Destination_Sftp::normalize_fingerprint( 'SHA256:corta' ), 'Huellas SFTP mal normalizadas.' );
	foreach ( Digitalisimo_Backups_Destinations::CLASSES as $type => $class ) foreach ( $class::secrets() as $field ) $check( in_array( $field, Digitalisimo_Backups_Credentials::FIELDS, true ), 'El secreto ' . $field . ' de ' . $type . ' no está en el almacén cifrado.' );
} );

// ---------------------------------------------------------------------------------------------------------- Revisión de 1.0.62
$case( 'revisión R1: bucket, estilo y endpoint se validan al usarse y atan la credencial', function() use ( &$routes, $s3, $context, $file_of, $check, $expect_failure, $destinations ) {
	$file = $file_of( 2000, 'r1.zip' );
	foreach ( array( 's3' => 's3_', 's3compat' => 's3compat_' ) as $type => $p ) {
		list( $class, $settings ) = $destinations[ $type ];
		// Lo que una escritura directa en la base pondría para desviar el respaldo a otro host.
		foreach ( array( array( $p . 'bucket' => 'atacante.example/', $p . 'style' => 'virtual' ), array( $p . 'bucket' => '2130706433/', $p . 'style' => 'auto' ), array( $p . 'bucket' => '169.254.169.254' ), array( $p . 'bucket' => 'x@atacante.example' ), array( $p . 'style' => 'otro' ) ) as $change ) {
			$routes = array();
			$destination = new $class( $change + $settings, $context( $type . '-r1' ) );
			$expect_failure( function() use ( $destination, $file ) { $destination->put( $file ); }, '', 'Digitalisimo_Backups_Permanent_Error' );
			$check( ! $routes, $type . ' llamó a ' . json_encode( $routes ) . ' con ' . json_encode( $change ) );
		}
	}
	$routes = array();
	$expect_failure( function() use ( $destinations, $context, $file ) { ( new Digitalisimo_Backups_Destination_S3_Compatible( array( 's3compat_endpoint' => 'https://minio.ejemplo.test:9443/ruta?x=1' ) + $destinations['s3compat'][1], $context( 'r1-endpoint' ) ) )->put( $file ); }, 'endpoint', 'Digitalisimo_Backups_Permanent_Error' );
	$check( ! $routes, 'Un endpoint con ruta guardado en la base se usó.' );
	// Un recibo del historial sólo vale en el bucket y la carpeta de esta conexión.
	$s3->objects['otra/ajeno.zip'] = 'no tocar';
	$s3_destination = new Digitalisimo_Backups_Destination_S3( $destinations['s3'][1], $context( 's3-r1' ) );
	foreach ( array( array( 'key' => 'otra/ajeno.zip' ), array( 'key' => 'digitalisimo-backups/../otra/ajeno.zip' ), array( 'key' => 'digitalisimo-backups/x.zip', 'bucket' => 'otro-bucket' ), array( 'key' => "digitalisimo-backups/x\r\n.zip" ) ) as $remote ) {
		$routes = array();
		$expect_failure( function() use ( $s3_destination, $remote ) { $s3_destination->delete( array( 'provider' => 's3' ) + $remote ); }, 'no se toca', 'Digitalisimo_Backups_Permanent_Error' );
		$check( ! $routes, 'Se llamó a S3 con un recibo ajeno: ' . json_encode( $remote ) );
	}
	$check( isset( $s3->objects['otra/ajeno.zip'] ), 'Se borró un objeto fuera de la carpeta.' );
	unset( $s3->objects['otra/ajeno.zip'] );
	// Todo dato que decide adónde van los bytes entra en el dato adicional (AAD) de su secreto, también en una conexión adicional.
	$C = 'Digitalisimo_Backups_Credentials';
	$bound = array(
		's3_secret_key' => array( 's3_access_key', 's3_region', 's3_bucket', 's3_style', 's3_path' ),
		's3compat_secret_key' => array( 's3compat_access_key', 's3compat_provider', 's3compat_endpoint', 's3compat_signing_region', 's3compat_do_region', 's3compat_wasabi_region', 's3compat_account', 's3compat_jurisdiction', 's3compat_insecure', 's3compat_allow_http', 's3compat_ca', 's3compat_bucket', 's3compat_style', 's3compat_path' ),
		'ftp_password' => array( 'ftp_host', 'ftp_port', 'ftp_user', 'ftp_security', 'ftp_insecure', 'ftp_ca', 'ftp_path', 'ftp_passive' ),
		'webdav_password' => array( 'webdav_url', 'webdav_user', 'webdav_insecure', 'webdav_allow_http', 'webdav_ca', 'webdav_path' ),
		'dropbox_refresh_token' => array( 'dropbox_app_key', 'dropbox_folder' ),
		'pcloud_access_token' => array( 'pcloud_client_id', 'pcloud_host', 'pcloud_folder' ),
		'sftp_password' => array( 'sftp_host', 'sftp_port', 'sftp_user', 'sftp_fingerprint' ),
	);
	foreach ( $bound as $secret => $fields ) foreach ( $fields as $field ) {
		$check( $C::context( $secret, array( $field => '21' ) ) !== $C::context( $secret, array( $field => '22' ) ), $secret . ' no está atado a ' . $field . '.' );
		$type = strtok( $secret, '_' );
		$check( $C::context( $type . '-0a1b2c3d/' . $secret, array( $type . '-0a1b2c3d/' . $field => '21' ) ) !== $C::context( $type . '-0a1b2c3d/' . $secret, array( $type . '-0a1b2c3d/' . $field => '22' ) ), 'El ' . $secret . ' de una conexión adicional no está atado a ' . $field . '.' );
	}
	unlink( $file );
} );
$case( 'revisión R2: CR/LF y NUL nunca llegan a FTP; el historial sólo toca la carpeta de su conexión', function() use ( &$routes, $webdav, $dropbox, $pcloud, $context, $file_of, $check, $expect_failure, $destinations ) {
	Bk_Fake_Ftp::reset( 'explicit' );
	Bk_Fake_Ftp::$files['/respaldos/otro.zip'] = 'no tocar';
	Bk_Fake_Ftp::$files['/otra/ajeno.zip'] = 'no tocar';
	$ftp = new Digitalisimo_Backups_Destination_Ftp( $destinations['ftp'][1], $context( 'ftp-r2' ) );
	foreach ( array( "/respaldos/x.zip\r\nDELE /respaldos/otro.zip", "/respaldos/x.zip\nDELE /respaldos/otro.zip", "/respaldos/x\0.zip", '/otra/ajeno.zip', '/respaldos/../otra/ajeno.zip', '/respaldos-otra/ajeno.zip', 'respaldos/x.zip' ) as $path ) {
		$expect_failure( function() use ( $ftp, $path ) { $ftp->delete( array( 'provider' => 'ftp', 'path' => $path ) ); }, 'no se toca', 'Digitalisimo_Backups_Permanent_Error' );
	}
	$file = $file_of( 500, 'r2.zip' );
	$expect_failure( function() use ( $ftp, $file ) { $ftp->put( $file, "r2.zip\r\nDELE /respaldos/otro.zip" ); } );
	// El cliente rechaza por sí mismo cualquier comando con CR, LF o NUL (defensa en profundidad).
	$client = new ReflectionMethod( 'Digitalisimo_Backups_Destination_Ftp', 'client' );
	if ( PHP_VERSION_ID < 80100 ) $client->setAccessible( true );
	$expect_failure( function() use ( $client, $ftp ) { $client->invoke( $ftp )->delete( "/respaldos/x.zip\r\nDELE /respaldos/otro.zip" ); }, '', 'Digitalisimo_Backups_Permanent_Error' );
	$check( isset( Bk_Fake_Ftp::$files['/respaldos/otro.zip'], Bk_Fake_Ftp::$files['/otra/ajeno.zip'] ) && ! preg_grep( '/^DELE /', Bk_Fake_Ftp::$log ) && ! preg_grep( '/[\r\n]/', Bk_Fake_Ftp::$log ), 'Un salto de línea inyectó un comando FTP: ' . implode( ' | ', Bk_Fake_Ftp::$log ) );
	Bk_Fake_Ftp::reset( 'explicit' );
	// WebDAV: rutas fuera de la carpeta, con «..» o con caracteres de control.
	$webdav->files['otra/ajeno.zip'] = 'no tocar';
	$dav = new Digitalisimo_Backups_Destination_Webdav( $destinations['webdav'][1], $context( 'webdav-r2' ) );
	foreach ( array( 'otra/ajeno.zip', 'respaldos/sitio/../../otra/ajeno.zip', 'respaldos/sitio-otra/ajeno.zip', "respaldos/sitio/x\r\n.zip" ) as $path ) {
		$routes = array();
		$expect_failure( function() use ( $dav, $path ) { $dav->delete( array( 'provider' => 'webdav', 'path' => $path ) ); }, 'no se toca', 'Digitalisimo_Backups_Permanent_Error' );
		$check( ! $routes, 'WebDAV llamó al servidor con una ruta ajena: ' . json_encode( $path ) );
	}
	$check( isset( $webdav->files['otra/ajeno.zip'] ), 'WebDAV borró un archivo fuera de la carpeta.' );
	unset( $webdav->files['otra/ajeno.zip'] );
	// Dropbox opera siempre por la ruta confinada, nunca por un id suelto (que podría ser cualquier archivo de la cuenta).
	$box = new Digitalisimo_Backups_Destination_Dropbox( $destinations['dropbox'][1], $context( 'dropbox-r2' ) );
	foreach ( array( array( 'path' => '/Otra/ajeno.zip' ), array( 'id' => 'id:ajeno123' ), array( 'path' => '/Respaldos ñandú/../Otra/x.zip' ), array( 'path' => '/Otra/x.zip', 'id' => 'id:ajeno123' ) ) as $remote ) {
		$routes = array();
		$expect_failure( function() use ( $box, $remote ) { $box->delete( array( 'provider' => 'dropbox' ) + $remote ); }, '', 'Digitalisimo_Backups_Permanent_Error' );
		$check( ! $routes, 'Dropbox llamó a la API con un recibo ajeno: ' . json_encode( $remote ) );
	}
	// pCloud y OneDrive (el token ve toda la cuenta): un id de otra carpeta no se toca.
	$pcloud->files[990] = array( 'name' => 'ajeno.zip', 'folder' => 0, 'data' => 'no tocar' );
	$cloud = new Digitalisimo_Backups_Destination_Pcloud( $destinations['pcloud'][1], $context( 'pcloud-r2' ) );
	$expect_failure( function() use ( $cloud ) { $cloud->delete( array( 'provider' => 'pcloud', 'id' => 990 ) ); }, 'no se toca', 'Digitalisimo_Backups_Permanent_Error' );
	$expect_failure( function() use ( $cloud ) { $cloud->get( array( 'provider' => 'pcloud', 'id' => 990 ), $GLOBALS['bk_root'] . '/private/r2-pcloud' ); }, 'no se toca', 'Digitalisimo_Backups_Permanent_Error' );
	$check( isset( $pcloud->files[990] ) && ! is_file( $GLOBALS['bk_root'] . '/private/r2-pcloud' ), 'pCloud tocó un archivo fuera de la carpeta.' );
	unset( $pcloud->files[990] );
	Bk_Fake_Drives::$files['ajeno'] = array( 'name' => 'ajeno.zip', 'folder' => 'Otra carpeta', 'data' => 'no tocar' );
	$drive = new Digitalisimo_Backups_Destination_Onedrive( $destinations['onedrive'][1], $context( 'onedrive-r2' ) );
	$expect_failure( function() use ( $drive ) { $drive->delete( array( 'provider' => 'onedrive', 'id' => 'ajeno' ) ); }, 'no se toca', 'Digitalisimo_Backups_Permanent_Error' );
	$check( isset( Bk_Fake_Drives::$files['ajeno'] ), 'OneDrive borró un archivo fuera de la carpeta.' );
	Bk_Fake_Drives::$files['ajeno']['folder'] = 'digitalisimo backups';
	$drive->delete( array( 'provider' => 'onedrive', 'id' => 'ajeno' ) );
	$check( ! isset( Bk_Fake_Drives::$files['ajeno'] ), 'OneDrive no borró una copia de su carpeta (sin distinguir mayúsculas).' );
	// SFTP: lo mismo, con la ruta absoluta de su carpeta.
	$sftp = new Digitalisimo_Backups_Destination_Sftp( array( 'sftp_host' => 'sftp.ejemplo.test', 'sftp_user' => 'u', 'sftp_password' => 'p', 'sftp_fingerprint' => str_repeat( 'A', 40 ), 'sftp_path' => '/srv/respaldos' ), $context( 'sftp-r2' ) );
	$own = new ReflectionMethod( 'Digitalisimo_Backups_Destination_Sftp', 'own' );
	if ( PHP_VERSION_ID < 80100 ) $own->setAccessible( true );
	$check( '/srv/respaldos/x.zip' === $own->invoke( $sftp, array( 'path' => '/srv/respaldos/x.zip' ) ), 'SFTP rechazó una copia de su carpeta.' );
	foreach ( array( '/etc/passwd', '/srv/respaldos/../../etc/passwd', '/srv/respaldos-otra/x.zip', "/srv/respaldos/x\n.zip" ) as $path ) $expect_failure( function() use ( $own, $sftp, $path ) { $own->invoke( $sftp, array( 'path' => $path ) ); }, 'no se toca', 'Digitalisimo_Backups_Permanent_Error' );
	unlink( $file );
} );
$case( 'revisión R6: direcciones internas y metadatos de la nube', function() use ( &$routes, $check, $expect_failure ) {
	$H = 'Digitalisimo_Backups_Http';
	$H::$resolver = function( $name ) { $map = array( 'nas.lan.test' => array( '192.168.1.20' ), 'publico.test' => array( '93.184.215.14' ), 'mixto.test' => array( '93.184.215.14', '169.254.169.254' ) ); return $map[ $name ] ?? false; };
	try {
		$expected = array( '169.254.169.254' => 'metadata', 'metadata.google.internal' => 'metadata', 'METADATA.GOOGLE.INTERNAL.' => 'metadata', 'fd00:ec2::254' => 'metadata', '[fd00:ec2::254]' => 'metadata', '100.100.100.200' => 'metadata', 'mixto.test' => 'metadata', '127.0.0.1' => 'loopback', '127.8.9.10' => 'loopback', 'localhost' => 'loopback', 'api.localhost' => 'loopback', '::1' => 'loopback', '[::1]' => 'loopback', '2130706433' => 'loopback', '0x7f000001' => 'loopback', '::ffff:127.0.0.1' => 'loopback', '0.0.0.0' => 'loopback', '10.0.0.5' => 'private', '172.16.0.1' => 'private', '172.31.255.255' => 'private', '192.168.1.1' => 'private', '100.64.0.1' => 'private', 'fd12:3456::1' => 'private', 'nas.lan.test' => 'private', '169.254.10.1' => 'link-local', 'fe80::1' => 'link-local', '172.32.0.1' => '', '100.128.0.1' => '', '8.8.8.8' => '', 'publico.test' => '', 'no-resuelve.test' => '', '2001:db8::1' => '' );
		foreach ( $expected as $host => $kind ) $check( $kind === $H::internal( $host ), $host . ' se clasificó como «' . $H::internal( $host ) . '» y no como «' . $kind . '».' );
		$check( '' === $H::internal( 'nas.lan.test', false ), 'Sin resolver, un nombre se clasificó.' );
		// Al servicio de metadatos no se envía nada, ni siquiera desde un dato guardado.
		foreach ( array( 'http://169.254.169.254/latest/meta-data/', 'https://[fd00:ec2::254]/', 'http://metadata.google.internal/computeMetadata/v1/' ) as $url ) {
			$routes = array();
			$expect_failure( function() use ( $url ) { Digitalisimo_Backups_Http::request( 'GET', $url ); }, 'metadatos', 'Digitalisimo_Backups_Permanent_Error' );
			$check( ! $routes, 'Se envió una petición al servicio de metadatos: ' . $url );
		}
	} finally { $H::$resolver = null; }
	// Cada destino declara los servidores que elige quien lo configura.
	$check( array( 'nube.test' ) === Digitalisimo_Backups_Destination_Webdav::hosts( array( 'webdav_url' => 'https://nube.test/dav/' ) ) && array( 'f.test' ) === Digitalisimo_Backups_Destination_Ftp::hosts( array( 'ftp_host' => 'f.test' ) ) && array( 's.test' ) === Digitalisimo_Backups_Destination_Sftp::hosts( array( 'sftp_host' => 's.test' ) ), 'WebDAV, FTP o SFTP no declaran su servidor.' );
	$check( array( '10.0.0.9' ) === Digitalisimo_Backups_Destination_S3_Compatible::hosts( array( 's3compat_endpoint' => 'https://10.0.0.9:9000' ) ) && array( 's3.us-west-004.backblazeb2.com' ) === Digitalisimo_Backups_Destination_S3_Compatible::hosts( array( 's3compat_endpoint' => 's3.us-west-004.backblazeb2.com' ) ) && array() === Digitalisimo_Backups_Destination_S3::hosts( array() ) && array() === Digitalisimo_Backups_Destination_Dropbox::hosts( array() ), 'Los servidores de S3 compatible o de un servicio con nombre no se declaran bien.' );
} );
$case( 'revisión I6: FTP activo compara la IP del par con la del servidor de control', function() use ( $check ) {
	$address = new ReflectionMethod( 'Digitalisimo_Backups_Ftp_Client', 'address' );
	if ( PHP_VERSION_ID < 80100 ) $address->setAccessible( true );
	$check( '127.0.0.1' === $address->invoke( null, '[::ffff:127.0.0.1]:50123' ) && '127.0.0.1' === $address->invoke( null, '127.0.0.1:21' ) && '2001:db8::1' === $address->invoke( null, '[2001:db8::1]:21' ) && '10.0.0.9' !== $address->invoke( null, '10.0.0.8:21' ), 'La IP del par FTP no se normaliza.' );
} );

foreach ( array_diff( scandir( $root . '/private' ), array( '.', '..' ) ) as $name ) @unlink( $root . '/private/' . $name );
foreach ( glob( $root . '/*' ) ?: array() as $path ) is_dir( $path ) ? @rmdir( $path ) : @unlink( $path );
@rmdir( $root );
if ( $GLOBALS['bk_fail'] ) { echo 'FALLAN ' . $GLOBALS['bk_fail'] . " casos de los destinos.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: contrato de destinos (put/get/size/delete/list/verify/test) correcto en S3, S3 compatibles, Dropbox, pCloud, WebDAV, FTP/FTPS, Google Drive y OneDrive.\n";
