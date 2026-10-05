<?php
/** Prueba del transporte SFTP sin conectar con un servidor externo. */
define( 'ABSPATH', __DIR__ );
$root = sys_get_temp_dir() . '/digitalisimo-sftp-test-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/backups', 0700, true );
$GLOBALS['sftp_test_root'] = $root;
$GLOBALS['sftp_test_corrupt_reads'] = false;
$GLOBALS['sftp_test_fingerprint'] = str_repeat( 'A', 40 );
$GLOBALS['sftp_test_auth_attempts'] = 0;
define( 'SSH2_FINGERPRINT_SHA1', 1 );
define( 'SSH2_FINGERPRINT_HEX', 2 );

function wp_basename( $path ) { return basename( $path ); }
function ssh2_connect( $host, $port ) { return fopen( 'php://memory', 'r+' ); }
function ssh2_fingerprint( $connection, $flags ) { return $GLOBALS['sftp_test_fingerprint']; }
function ssh2_auth_password( $connection, $user, $password ) { ++$GLOBALS['sftp_test_auth_attempts']; return true; }
function ssh2_sftp( $connection ) { return fopen( 'php://memory', 'r+' ); }
function ssh2_sftp_rename( $sftp, $from, $to ) { return rename( $GLOBALS['sftp_test_root'] . $from, $GLOBALS['sftp_test_root'] . $to ); }
function ssh2_sftp_unlink( $sftp, $path ) { return unlink( $GLOBALS['sftp_test_root'] . $path ); }

class Digitalisimo_Test_Sftp_Stream {
	public $context;
	private $handle;
	private $corrupt = false;
	private $first_read = true;
	public function stream_open( $path, $mode, $options, &$opened_path ) {
		$file = $GLOBALS['sftp_test_root'] . parse_url( $path, PHP_URL_PATH );
		$this->corrupt = 'r' === substr( $mode, 0, 1 ) && $GLOBALS['sftp_test_corrupt_reads'];
		$this->handle = fopen( $file, $mode );
		return (bool) $this->handle;
	}
	public function stream_read( $count ) {
		$data = fread( $this->handle, $count );
		if ( $this->corrupt && $this->first_read && '' !== $data ) $data[0] = 'X';
		$this->first_read = false;
		return $data;
	}
	public function stream_write( $data ) { return fwrite( $this->handle, $data ); }
	public function stream_eof() { return feof( $this->handle ); }
	public function stream_tell() { return ftell( $this->handle ); }
	public function stream_seek( $offset, $whence = SEEK_SET ) { return 0 === fseek( $this->handle, $offset, $whence ); }
	public function stream_flush() { return fflush( $this->handle ); }
	public function stream_stat() { return fstat( $this->handle ); }
	public function stream_close() { fclose( $this->handle ); }
}
if ( in_array( 'ssh2.sftp', stream_get_wrappers(), true ) ) throw new RuntimeException( 'La prueba requiere un runtime sin la extensión SSH2 real.' );
stream_wrapper_register( 'ssh2.sftp', 'Digitalisimo_Test_Sftp_Stream' );
require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';

$file = $root . '/original.zip';
file_put_contents( $file, 'ZIP de prueba para verificar que la copia no cambia.' );
$settings = array( 'sftp_host' => 'example.test', 'sftp_port' => 22, 'sftp_user' => 'test', 'sftp_password' => 'secret', 'sftp_path' => '/backups', 'sftp_fingerprint' => str_repeat( 'A', 40 ) );
$method = new ReflectionMethod( 'Digitalisimo_Backups', 'sftp' );
if ( PHP_VERSION_ID < 80100 ) $method->setAccessible( true );
$result = $method->invoke( null, $file, $settings );
if ( 'sftp' !== $result['provider'] || $result['sha256'] !== hash_file( 'sha256', $file ) || file_get_contents( $root . $result['path'] ) !== file_get_contents( $file ) ) throw new RuntimeException( 'SFTP debe publicar sólo una copia verificada.' );

$attempts = $GLOBALS['sftp_test_auth_attempts'];
$wrong = $settings;
$wrong['sftp_fingerprint'] = str_repeat( 'B', 40 );
try {
	$method->invoke( null, $file, $wrong );
	throw new RuntimeException( 'Una huella SSH diferente no fue rechazada.' );
} catch ( ReflectionException $e ) {
	throw $e;
} catch ( Exception $e ) {
	if ( 'La huella de la clave SSH del servidor SFTP no coincide con la configurada.' !== $e->getMessage() ) throw $e;
}
if ( $GLOBALS['sftp_test_auth_attempts'] !== $attempts ) throw new RuntimeException( 'El plugin intentó autenticarse antes de verificar la clave SSH.' );

$missing = $settings;
$missing['sftp_fingerprint'] = '';
try {
	$method->invoke( null, $file, $missing );
	throw new RuntimeException( 'SFTP aceptó una conexión sin huella de host.' );
} catch ( ReflectionException $e ) {
	throw $e;
} catch ( Exception $e ) {
	if ( 'Configura y verifica la huella SHA-1 de la clave SSH del servidor antes de usar SFTP.' !== $e->getMessage() ) throw $e;
}
if ( $GLOBALS['sftp_test_auth_attempts'] !== $attempts ) throw new RuntimeException( 'El plugin intentó autenticarse sin huella de host.' );

unlink( $root . $result['path'] );
$GLOBALS['sftp_test_corrupt_reads'] = true;
try {
	$method->invoke( null, $file, $settings );
	throw new RuntimeException( 'La copia alterada no fue rechazada.' );
} catch ( ReflectionException $e ) {
	throw $e;
} catch ( Exception $e ) {
	if ( 'La copia SFTP no coincide con el ZIP local.' !== $e->getMessage() ) throw $e;
}
if ( glob( $root . '/backups/*' ) ) throw new RuntimeException( 'Una transferencia fallida dejó un archivo visible o temporal.' );
unlink( $file );
rmdir( $root . '/backups' );
rmdir( $root );
echo "DIGITALÍSIMO Backups: SFTP verifica y publica sólo copias íntegras.\n";
