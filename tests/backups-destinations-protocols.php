<?php
/**
 * Protocolos de los destinos, sin red:
 *   (a) SigV4: las firmas coinciden con las de botocore (S3SigV4Auth, la implementación oficial de AWS para Python) en
 *       peticiones de estilo virtual y de ruta, con consulta, claves con espacios y no ASCII, cabeceras x-amz-* y regiones
 *       de AWS, Backblaze, R2 y MinIO (vectores generados el 2026-11-09 con la hora fija 2026-11-09T12:34:56Z);
 *   (b) SFTP con clave privada (en memoria si la extensión lo permite, si no en archivos 0600 que se borran) y con la
 *       huella SHA-256 de OpenSSH (y la SHA-1 de siempre); sin la extensión con SHA-256, mensaje claro;
 *   (c) los mensajes de error nunca llevan una cabecera de autorización, una firma, un token ni la URL con su consulta.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'SSH2_FINGERPRINT_MD5', 0 );
define( 'SSH2_FINGERPRINT_SHA1', 1 );
define( 'SSH2_FINGERPRINT_HEX', 2 );
define( 'SSH2_FINGERPRINT_RAW', 4 );
define( 'SSH2_FINGERPRINT_SHA256', 8 );
$GLOBALS['bk_fail'] = 0;
$root = sys_get_temp_dir() . '/digitalisimo-protocols-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/remote/backups', 0700, true );
mkdir( $root . '/private', 0700, true );
$GLOBALS['sftp_root'] = $root . '/remote';
$GLOBALS['sftp_hostkey'] = random_bytes( 51 );
$GLOBALS['sftp_auth'] = array();
$GLOBALS['sftp_pubkey_files'] = array();

function wp_basename( $path ) { return basename( $path ); }
function ssh2_connect( $host, $port ) { return fopen( 'php://memory', 'r+' ); }
function ssh2_fingerprint( $connection, $flags ) {
	if ( $flags & SSH2_FINGERPRINT_SHA256 ) return hash( 'sha256', $GLOBALS['sftp_hostkey'], true );
	return strtoupper( sha1( $GLOBALS['sftp_hostkey'] ) );
}
function ssh2_auth_password( $connection, $user, $password ) { $GLOBALS['sftp_auth'][] = array( 'password', $user, $password ); return 'Clave-SFTP' === $password; }
function ssh2_auth_pubkey_file( $connection, $user, $public, $private, $passphrase ) {
	$GLOBALS['sftp_pubkey_files'][] = array( $public, $private, substr( sprintf( '%o', fileperms( $private ) ), -3 ), file_get_contents( $private ) );
	$GLOBALS['sftp_auth'][] = array( 'pubkey_file', $user, trim( file_get_contents( $public ) ), $passphrase );
	return false !== strpos( file_get_contents( $private ), 'CLAVE-PRIVADA-DE-PRUEBA' ) && 'frase' === $passphrase;
}
function ssh2_sftp( $connection ) { return fopen( 'php://memory', 'r+' ); }
function ssh2_sftp_rename( $sftp, $from, $to ) { return rename( $GLOBALS['sftp_root'] . $from, $GLOBALS['sftp_root'] . $to ); }
function ssh2_sftp_unlink( $sftp, $path ) { return @unlink( $GLOBALS['sftp_root'] . $path ); }
function ssh2_sftp_stat( $sftp, $path ) { return @stat( $GLOBALS['sftp_root'] . $path ); }
final class Bk_Sftp_Stream {
	public $context;
	private $handle;
	private $dir;
	public function stream_open( $path, $mode, $options, &$opened ) { $this->handle = @fopen( $GLOBALS['sftp_root'] . parse_url( $path, PHP_URL_PATH ), $mode ); return (bool) $this->handle; }
	public function stream_read( $count ) { return fread( $this->handle, $count ); }
	public function stream_write( $data ) { return fwrite( $this->handle, $data ); }
	public function stream_eof() { return feof( $this->handle ); }
	public function stream_flush() { return fflush( $this->handle ); }
	public function stream_stat() { return fstat( $this->handle ); }
	public function stream_close() { fclose( $this->handle ); }
	public function url_stat( $path, $flags ) { return @stat( $GLOBALS['sftp_root'] . parse_url( $path, PHP_URL_PATH ) ); }
	public function dir_opendir( $path, $options ) { $this->dir = @opendir( $GLOBALS['sftp_root'] . parse_url( $path, PHP_URL_PATH ) ); return (bool) $this->dir; }
	public function dir_readdir() { return readdir( $this->dir ); }
	public function dir_closedir() { closedir( $this->dir ); return true; }
}
stream_wrapper_register( 'ssh2.sftp', 'Bk_Sftp_Stream' );
require __DIR__ . '/../digitalisimo-backups/includes/class-backups-job-store.php';
foreach ( array( 'http', 'destination', 'destinations', 'destination-sftp', 'destination-s3' ) as $part ) require __DIR__ . '/../digitalisimo-backups/includes/destinations/class-' . $part . '.php';

$case = function( $label, callable $test ) {
	try { $test(); echo "ok ($label)\n"; }
	catch ( Throwable $e ) { $GLOBALS['bk_fail']++; echo 'FALLA (' . $label . '): ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n"; }
};
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };

// (a) Vectores de botocore.
$case( 'a', function() use ( $check ) {
	$secret = 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';
	$time = gmmktime( 12, 34, 56, 11, 9, 2026 );
	$vectors = array(
		array( 'GET', 'mi-bucket.s3.eu-west-1.amazonaws.com', '/carpeta/digitalisimo-backup.zip', array(), array(), '', 'eu-west-1', 'host;x-amz-content-sha256;x-amz-date', 'c2854e95dd2b55fef76147dd2a9db605c49f4d1e8761007ad4706599284b55e9' ),
		array( 'PUT', 'minio.ejemplo.test:9443', '/mi-bucket/' . Digitalisimo_Backups_Destination_S3_Base::uri_encode( 'digitalisimo backups/archivo con espacios.zip', false ), array( 'partNumber' => '3', 'uploadId' => 'VXBsb2FkIElE+z' ), array(), 'parte de prueba', 'us-east-1', 'host;x-amz-content-sha256;x-amz-date', 'c77c74bc87f1f3b4d074fb0dfd7230f52f77eab6e184b7ed41f0d62b371d9572' ),
		array( 'POST', 's3.us-west-004.backblazeb2.com', '/b2-bucket/' . Digitalisimo_Backups_Destination_S3_Base::uri_encode( 'x/ñandú.zip.part-abc', false ), array( 'uploads' => '' ), array( 'content-type' => 'application/octet-stream', 'x-amz-storage-class' => 'STANDARD' ), '', 'us-west-004', 'content-type;host;x-amz-content-sha256;x-amz-date;x-amz-storage-class', '7a967298c1203878afc78e87beecc715fc0a1728fc104cb21b7064b6ac9d9458' ),
		array( 'GET', 'cuenta.r2.cloudflarestorage.com', '/r2-bucket', array( 'list-type' => '2', 'prefix' => 'digitalisimo-backups/', 'delimiter' => '/', 'max-keys' => '1000', 'continuation-token' => '1/abC=' ), array(), '', 'auto', 'host;x-amz-content-sha256;x-amz-date', '60166d2e6b407426a432ad0dbfcc591a4c157125cd0d8b238d05244ef28e222e' ),
		array( 'PUT', 'nyc3.digitaloceanspaces.com', '/spaces-bucket/b/final.zip', array(), array( 'x-amz-copy-source' => '/spaces-bucket/b/final.zip.part-0a1b' ), '', 'us-east-1', 'host;x-amz-content-sha256;x-amz-copy-source;x-amz-date', '91934911ef68fe7fc6617f19304920bd99124a6f473b6a7a88e881e33b3db926' ),
	);
	foreach ( $vectors as $i => list( $method, $host, $uri, $query, $headers, $body, $region, $signed, $signature ) ) {
		$out = Digitalisimo_Backups_Destination_S3_Base::sign( $method, $host, $uri, $query, $headers, hash( 'sha256', $body ), 'AKIDEXAMPLE', $secret, $region, $time );
		$expected = 'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20261109/' . $region . '/s3/aws4_request, SignedHeaders=' . $signed . ', Signature=' . $signature;
		$check( $expected === $out['headers']['authorization'], 'Vector ' . $i . ' distinto de botocore: ' . $out['headers']['authorization'] . "\n" . $out['canonical'] );
		$check( '20261109T123456Z' === $out['headers']['x-amz-date'] && hash( 'sha256', $body ) === $out['headers']['x-amz-content-sha256'], 'Vector ' . $i . ': fecha o carga firmada.' );
	}
	$check( 'a%20b/%C3%B1~-_.' === Digitalisimo_Backups_Destination_S3_Base::uri_encode( 'a b/ñ~-_.', false ) && 'a%20b%2F' === Digitalisimo_Backups_Destination_S3_Base::uri_encode( 'a b/' ), 'UriEncode no sigue las reglas de AWS.' );
} );

// (b) SFTP con clave privada y huella SHA-256.
$sha256 = 'SHA256:' . rtrim( base64_encode( hash( 'sha256', $GLOBALS['sftp_hostkey'], true ) ), '=' );
$sha1 = strtoupper( sha1( $GLOBALS['sftp_hostkey'] ) );
$file = $root . '/digitalisimo-backup-x.zip';
file_put_contents( $file, random_bytes( 70000 ) );
$base = array( 'sftp_host' => 'sftp.test', 'sftp_port' => '22', 'sftp_user' => 'respaldos', 'sftp_path' => '/backups' );
$context = array( 'temp_dir' => $root . '/private', 'connection' => 'sftp' );
$case( 'b', function() use ( $check, $base, $context, $file, $sha256, $sha1, $root ) {
	$key = array( 'sftp_auth' => 'key', 'sftp_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nCLAVE-PRIVADA-DE-PRUEBA\n-----END OPENSSH PRIVATE KEY-----", 'sftp_key_passphrase' => 'frase', 'sftp_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPrueba respaldos@sitio', 'sftp_fingerprint' => $sha256 );
	$check( Digitalisimo_Backups_Destination_Sftp::ready( $base + $key ) && ! Digitalisimo_Backups_Destination_Sftp::ready( array( 'sftp_public_key' => '' ) + $base + $key ), 'ready() no exige la clave pública con la privada.' );
	$destination = new Digitalisimo_Backups_Destination_Sftp( $base + $key, $context );
	$receipt = $destination->put( $file );
	$check( '/backups/' . basename( $file ) === $receipt['path'] && hash_file( 'sha256', $file ) === hash_file( 'sha256', $GLOBALS['sftp_root'] . $receipt['path'] ), 'SFTP con clave no publicó la copia verificada.' );
	$auth = $GLOBALS['sftp_pubkey_files'][0];
	$check( '600' === $auth[2] && ! file_exists( $auth[0] ) && ! file_exists( $auth[1] ) && 0 === strpos( basename( $auth[1] ), '.sftp-key-' ), 'La clave privada no se escribió 0600 en la carpeta privada o no se borró: ' . json_encode( array( $auth[2], file_exists( $auth[1] ) ) ) );
	$check( array( 'pubkey_file', 'respaldos', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPrueba respaldos@sitio', 'frase' ) === $GLOBALS['sftp_auth'][0], 'La autenticación por clave no usó la pública, la privada y su frase.' );
	$check( ! glob( $root . '/private/.sftp-*' ), 'Quedaron archivos de clave en la carpeta privada.' );
	// size, get, list y delete del contrato.
	$check( 70000 === $destination->size( $receipt ), 'size() SFTP.' );
	$destination->get( $receipt, $root . '/private/back' );
	$check( hash_file( 'sha256', $file ) === hash_file( 'sha256', $root . '/private/back' ), 'get() SFTP.' );
	$names = array_column( $destination->list(), 'name' );
	$check( array( basename( $file ) ) === $names, 'list() SFTP: ' . json_encode( $names ) );
	$destination->delete( $receipt );
	$destination->delete( $receipt );
	$check( false === $destination->size( $receipt ), 'delete() SFTP.' );
	unlink( $root . '/private/back' );
	// Huella SHA-256 equivocada: se rechaza antes de autenticarse.
	$GLOBALS['sftp_auth'] = array();
	try { ( new Digitalisimo_Backups_Destination_Sftp( array( 'sftp_fingerprint' => 'SHA256:' . str_repeat( 'A', 43 ) ) + $key + $base, $context ) )->put( $file ); throw new LogicException( 'Aceptó otra huella SHA-256.' ); }
	catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( 'La huella de la clave SSH del servidor SFTP no coincide con la configurada.' === $e->getMessage() && ! $GLOBALS['sftp_auth'], 'Con otra huella SHA-256 se intentó autenticar.' ); }
	// La SHA-1 de siempre sigue sirviendo, con contraseña.
	$receipt = ( new Digitalisimo_Backups_Destination_Sftp( $base + array( 'sftp_password' => 'Clave-SFTP', 'sftp_fingerprint' => $sha1 ), $context ) )->put( $file );
	$check( is_file( $GLOBALS['sftp_root'] . $receipt['path'] ), 'La huella SHA-1 guardada dejó de funcionar.' );
	unlink( $GLOBALS['sftp_root'] . $receipt['path'] );
	// Validación al guardar.
	$saved = Digitalisimo_Backups_Destination_Sftp::sanitize( array( 'sftp_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\r\nCLAVE-PRIVADA-DE-PRUEBA\r\n-----END OPENSSH PRIVATE KEY-----" ) + $base + $key, array() );
	$check( $sha256 === $saved['sftp_fingerprint'] && false === strpos( $saved['sftp_private_key'], "\r" ), 'La huella SHA-256 o la clave no se normalizaron.' );
	try { Digitalisimo_Backups_Destination_Sftp::sanitize( array( 'sftp_private_key' => 'no es una clave' ) + $base + $key, array() ); throw new LogicException( 'Aceptó una clave privada incompleta.' ); } catch ( Exception $e ) { $check( false !== strpos( $e->getMessage(), 'BEGIN' ), 'Mensaje de clave privada incompleta: ' . $e->getMessage() ); }
} );

// (c) Mensajes sin secretos.
$case( 'c', function() use ( $check ) {
	$message = Digitalisimo_Backups_Http::redact( 'cURL error 22: https://api.pcloud.com/uploadfile?access_token=TOKEN-SECRETO&x=1 Authorization: Bearer sl.SECRETO X-Amz-Signature=abcdef AWS4-HMAC-SHA256 Credential=AKID/2026 refresh_token=RT-SECRETO code_verifier=VERIF', 'https://api.pcloud.com/uploadfile?access_token=TOKEN-SECRETO&x=1' );
	foreach ( array( 'TOKEN-SECRETO', 'sl.SECRETO', 'abcdef', 'AKID/2026', 'RT-SECRETO', 'VERIF', 'uploadfile' ) as $secret ) $check( false === strpos( $message, $secret ), 'redact() deja «' . $secret . '»: ' . $message );
	$check( false !== strpos( $message, 'api.pcloud.com' ), 'redact() quitó también el servidor.' );
	$seen = null;
	Digitalisimo_Backups_Http::$transport = function( $request ) use ( &$seen ) { $seen = $request; throw new Exception( 'cURL error 7: Failed to connect' ); };
	try { Digitalisimo_Backups_Http::request( 'GET', 'ftp://servidor/' ); throw new LogicException( 'Aceptó una URL que no es http(s).' ); } catch ( Exception $e ) { $check( null === $seen && false !== strpos( $e->getMessage(), 'http(s)' ), 'Una URL ftp:// llegó al transporte.' ); }
	Digitalisimo_Backups_Http::$transport = null;
} );

foreach ( array( $root . '/remote/backups', $root . '/remote', $root . '/private' ) as $dir ) { foreach ( array_diff( (array) scandir( $dir ), array( '.', '..' ) ) as $name ) @unlink( $dir . '/' . $name ); @rmdir( $dir ); }
@unlink( $file );
@rmdir( $root );
if ( $GLOBALS['bk_fail'] ) { echo 'FALLAN ' . $GLOBALS['bk_fail'] . " casos de protocolos.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: firmas SigV4 idénticas a botocore, SFTP con clave privada y huella SHA-256, y mensajes sin secretos.\n";
