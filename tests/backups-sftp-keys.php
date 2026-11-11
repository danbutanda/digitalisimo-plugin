<?php
/**
 * Clave privada SFTP con la extensión ssh2 presente (simulada aquí por funciones de usuario, sin red), revisión H6–H8:
 *   (h6) con ssh2 sin autenticación en memoria (sólo ssh2_auth_pubkey_file), una clave guardada (que queda sin frase en el
 *        almacén cifrado) nunca se escribe en el disco: el motor automático usa el cliente incluido y el motor «ssh2»
 *        forzado se niega sin escribir nada; una clave que sigue cifrada con su frase (guardada antes de esta versión) sí
 *        va al archivo temporal, pero cifrada, con la frase aparte, en 0600 y borrada al momento; con ssh2_auth_pubkey
 *        (en memoria) se usa la extensión y no se escribe nada;
 *   (h7) una frase equivocada se rechaza al guardar aunque se pegue también la clave pública, sin cambiar nada;
 *   (h8) un .ppk de PuTTY se convierte al guardar (también con su clave pública y sin frase) y nunca llega a libssh2.
 * Las claves las genera phpseclib (la copia incluida): no hace falta ssh-keygen.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'SSH2_FINGERPRINT_MD5', 0 );
define( 'SSH2_FINGERPRINT_SHA1', 1 );
define( 'SSH2_FINGERPRINT_HEX', 0 );
define( 'SSH2_FINGERPRINT_RAW', 2 );
$GLOBALS['bk_fail'] = 0;
$GLOBALS['host_sha1'] = str_repeat( 'AB', 20 );
$GLOBALS['seen'] = array();
$GLOBALS['memory'] = array();
function wp_basename( $path ) { return basename( $path ); }
function ssh2_connect( $host, $port ) { return fopen( 'php://memory', 'r' ); }
function ssh2_fingerprint( $c, $flags ) { return $GLOBALS['host_sha1']; }
function ssh2_methods_negotiated( $c ) { return array( 'hostkey' => 'ssh-rsa' ); }
function ssh2_auth_password( $c, $u, $p ) { return false; }
function ssh2_auth_pubkey_file( $c, $user, $pub, $priv, $passphrase ) {
	$GLOBALS['seen'][] = array( 'priv' => (string) file_get_contents( $priv ), 'pass' => $passphrase, 'mode' => substr( sprintf( '%o', fileperms( $priv ) ), -4 ), 'path' => $priv );
	return true;
}
$tmp = sys_get_temp_dir() . '/digitalisimo-sftp-keys-' . bin2hex( random_bytes( 4 ) );
mkdir( $tmp . '/private', 0700, true );
require __DIR__ . '/../digitalisimo-backups/includes/class-backups-job-store.php';
foreach ( array( 'http', 'destination', 'destinations', 'destination-sftp' ) as $part ) require __DIR__ . '/../digitalisimo-backups/includes/destinations/class-' . $part . '.php';
if ( is_file( __DIR__ . '/../digitalisimo-backups/includes/destinations/class-sftp-client.php' ) ) require_once __DIR__ . '/../digitalisimo-backups/includes/destinations/class-sftp-client.php';

$case = function( $label, callable $test ) {
	try { $test(); echo "ok ($label)\n"; }
	catch ( Throwable $e ) { $GLOBALS['bk_fail']++; echo 'FALLA (' . $label . '): ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n"; }
};
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
$files = function() use ( $tmp ) { return glob( $tmp . '/private/.sftp-*' ) ?: array(); };

// Claves de prueba: RSA PEM cifrada con frase y su pública (como las de ssh-keygen -m PEM), y un .ppk sin frase.
Digitalisimo_Backups_Sftp_Client::load();
$rsa = 'DigitalisimoBackups\\Vendor\\phpseclib3\\Crypt\\RSA';
$key = $rsa::createKey( 2048 );
$private = trim( str_replace( "\r\n", "\n", $key->withPassword( 'frase-secreta' )->toString( 'PKCS1' ) ) );
$public = trim( $key->getPublicKey()->toString( 'OpenSSH', array( 'comment' => 'respaldos' ) ) );
$ppk = trim( str_replace( "\r\n", "\n", $key->toString( 'PuTTY' ) ) );
$base = array( 'sftp_host' => 'sftp.ejemplo.test', 'sftp_port' => '22', 'sftp_user' => 'u', 'sftp_path' => '/respaldos', 'sftp_fingerprint' => $GLOBALS['host_sha1'], 'sftp_auth' => 'key', 'sftp_engine' => 'auto' );
$context = array( 'temp_dir' => $tmp . '/private', 'connection' => 'sftp' );

$case( 'h6', function() use ( $check, $files, $base, $context, $private, $public ) {
	$check( false !== strpos( $private, 'Proc-Type: 4,ENCRYPTED' ) && Digitalisimo_Backups_Destination_Sftp::key_encrypted( $private ), 'La clave de prueba no va cifrada.' );
	$saved = Digitalisimo_Backups_Destination_Sftp::sanitize( $base + array( 'sftp_private_key' => $private, 'sftp_key_passphrase' => 'frase-secreta', 'sftp_public_key' => $public ), array() );
	$check( '' === $saved['sftp_key_passphrase'] && ! Digitalisimo_Backups_Destination_Sftp::key_encrypted( $saved['sftp_private_key'] ), 'La clave no quedó abierta al guardar.' );
	// Automático: la extensión sólo autentica desde un archivo, y la clave ya no lleva frase → cliente incluido.
	$auto = new Digitalisimo_Backups_Destination_Sftp( $saved, $context );
	$check( 'phpseclib' === $auto->engine(), 'Con ssh2 sin autenticación en memoria, el motor automático eligió la extensión: ' . $auto->engine() );
	// «ssh2» forzado: se niega sin escribir nada en el disco.
	$GLOBALS['seen'] = array();
	try { ( new Digitalisimo_Backups_Destination_Sftp( array( 'sftp_engine' => 'ssh2' ) + $saved, $context ) )->connection(); throw new LogicException( 'Autenticó con la clave sin cifrar en un archivo.' ); }
	catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'no escribirla sin cifrar' ), 'Mensaje: ' . $e->getMessage() ); }
	$check( ! $GLOBALS['seen'] && ! $files(), 'Se escribió la clave en el disco: ' . json_encode( array_column( $GLOBALS['seen'], 'path' ) ) );
	// Una clave que sigue cifrada con su frase (guardada antes de esta versión): al archivo sólo va el texto cifrado.
	$legacy = new Digitalisimo_Backups_Destination_Sftp( $base + array( 'sftp_private_key' => $private, 'sftp_key_passphrase' => 'frase-secreta', 'sftp_public_key' => $public ), $context );
	$check( 'ssh2' === $legacy->engine(), 'Una clave cifrada con su frase no usó la extensión.' );
	$legacy->connection();
	$seen = end( $GLOBALS['seen'] );
	$check( $seen && false !== strpos( $seen['priv'], 'Proc-Type: 4,ENCRYPTED' ) && 'frase-secreta' === $seen['pass'] && '0600' === $seen['mode'] && ! file_exists( $seen['path'] ) && ! $files(), 'El archivo temporal no llevaba la clave cifrada o no se borró: ' . json_encode( array( substr( (string) ( $seen['priv'] ?? '' ), 0, 60 ), $seen['mode'] ?? '' ) ) );
} );

$case( 'h7', function() use ( $check, $base, $private, $public ) {
	try { Digitalisimo_Backups_Destination_Sftp::sanitize( $base + array( 'sftp_private_key' => $private, 'sftp_key_passphrase' => 'frase-equivocada', 'sftp_public_key' => $public ), array() ); throw new LogicException( 'Guardó con la frase equivocada y la clave pública.' ); }
	catch ( Exception $e ) { $check( false !== strpos( $e->getMessage(), 'no se pudo abrir' ) && false !== strpos( $e->getMessage(), 'No se cambió nada' ) && false === strpos( $e->getMessage(), 'PRIVATE' ), 'Mensaje: ' . $e->getMessage() ); }
} );

$case( 'h8', function() use ( $check, $base, $context, $ppk, $public ) {
	$check( 0 === strpos( $ppk, 'PuTTY-User-Key-File-' ), 'La clave de prueba no es un .ppk.' );
	$saved = Digitalisimo_Backups_Destination_Sftp::sanitize( $base + array( 'sftp_private_key' => $ppk, 'sftp_key_passphrase' => '', 'sftp_public_key' => $public ), array() );
	$check( false === strpos( $saved['sftp_private_key'], 'PuTTY' ) && 0 === strpos( $saved['sftp_private_key'], '-----BEGIN ' ), 'El .ppk no se convirtió al guardar: ' . strtok( $saved['sftp_private_key'], "\n" ) );
	// Un .ppk guardado tal cual por una versión anterior: nunca va a libssh2.
	$GLOBALS['seen'] = array();
	$old = new Digitalisimo_Backups_Destination_Sftp( $base + array( 'sftp_private_key' => $ppk, 'sftp_key_passphrase' => 'x', 'sftp_public_key' => $public ), $context );
	$check( 'phpseclib' === $old->engine(), 'Un .ppk eligió la extensión ssh2.' );
	try { ( new Digitalisimo_Backups_Destination_Sftp( array( 'sftp_engine' => 'ssh2' ) + $base + array( 'sftp_private_key' => $ppk, 'sftp_key_passphrase' => 'x', 'sftp_public_key' => $public ), $context ) )->connection(); throw new LogicException( 'Pasó el .ppk a libssh2.' ); }
	catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), '.ppk' ), 'Mensaje: ' . $e->getMessage() ); }
	$check( ! $GLOBALS['seen'], 'libssh2 recibió el .ppk.' );
} );

// Con ssh2_auth_pubkey (en memoria) se usa la extensión y no se escribe nada.
// Declarada con un if: así sólo existe desde aquí (una función sin condición existiría desde el principio del archivo).
if ( ! function_exists( 'ssh2_auth_pubkey' ) ) { function ssh2_auth_pubkey( $c, $user, $pub, $priv, $passphrase ) { $GLOBALS['memory'][] = array( $user, $pub, substr( $priv, 0, 31 ), $passphrase ); return true; } }
$case( 'memoria', function() use ( $check, $files, $base, $context, $private, $public ) {
	$saved = Digitalisimo_Backups_Destination_Sftp::sanitize( $base + array( 'sftp_private_key' => $private, 'sftp_key_passphrase' => 'frase-secreta', 'sftp_public_key' => $public ), array() );
	$destination = new Digitalisimo_Backups_Destination_Sftp( $saved, $context );
	$check( 'ssh2' === $destination->engine(), 'Con autenticación en memoria no se usó la extensión.' );
	$GLOBALS['seen'] = array();
	$destination->connection();
	$check( 1 === count( $GLOBALS['memory'] ) && ! $GLOBALS['seen'] && ! $files(), 'La autenticación en memoria escribió algo en el disco.' );
} );

exec( 'rm -rf ' . escapeshellarg( $tmp ) );
if ( $GLOBALS['bk_fail'] ) { echo 'FALLAN ' . $GLOBALS['bk_fail'] . " casos de claves SFTP con la extensión ssh2.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: la clave SFTP nunca va sin cifrar al disco, la frase equivocada se rechaza y un .ppk se convierte.\n";
