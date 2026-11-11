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
	if ( 0 !== strpos( $e->getMessage(), 'La huella de la clave SSH del servidor SFTP no coincide con la configurada.' ) ) throw $e;
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
$GLOBALS['sftp_test_corrupt_reads'] = false;

// H20 del ensayo E3 (con la extensión ssh2): la subida va por tramos hasta el plazo de cada uno, con el estado en el
// área del trabajo; una muerte a mitad de un tramo no repite lo que el servidor ya tiene.
if ( ! function_exists( 'ssh2_sftp_stat' ) ) { function ssh2_sftp_stat( $sftp, $path ) { clearstatcache(); $stat = @stat( $GLOBALS['sftp_test_root'] . $path ); return is_array( $stat ) ? array( 'size' => $stat['size'] ) : false; } }
$big = $root . '/digitalisimo-backup-tramos.zip';
$h = fopen( $big, 'wb' ); for ( $i = 0; $i < 6; $i++ ) fwrite( $h, random_bytes( 1048576 ) ); fwrite( $h, random_bytes( 4321 ) ); fclose( $h );
$state_path = $root . '/transfer.json';
$positions = array();
$die = 2;
$context = array( 'temp_dir' => $root, 'connection' => 'sftp', 'state' => new Digitalisimo_Backups_Transfer_State( $state_path ), 'progress' => function( $percent, $message ) use ( &$die ) { if ( 0 === --$die ) throw new RuntimeException( 'proceso muerto' ); } );
$receipt = null;
$slices = 0;
$resumed_at = null;
for ( $i = 0; $i < 60 && ! $receipt; $i++ ) {
	$before = glob( $root . '/backups/*.part-*' );
	$before_size = $before ? filesize( $before[0] ) : 0;
	$destination = new Digitalisimo_Backups_Destination_Sftp( $settings, $context );
	$destination->set_deadline( microtime( true ) - 1 ); // Plazo vencido: cada tramo avanza el mínimo (un bloque).
	try {
		$receipt = $destination->put( $big );
	} catch ( Digitalisimo_Backups_Transfer_Pending $e ) {
		$slices++;
	} catch ( RuntimeException $e ) {
		if ( 'proceso muerto' !== $e->getMessage() ) throw $e;
		clearstatcache();
		$after = glob( $root . '/backups/*.part-*' );
		if ( ! $after || filesize( $after[0] ) <= $before_size ) throw new RuntimeException( 'La muerte simulada no dejó nada nuevo en el servidor.' );
		$resumed_at = filesize( $after[0] ); // Lo que el servidor ya tiene: el tramo siguiente tiene que seguir desde aquí.
		continue;
	}
	if ( null !== $resumed_at && ! isset( $first_after ) ) {
		clearstatcache();
		$now = glob( $root . '/backups/*.part-*' );
		$state = json_decode( (string) @file_get_contents( $state_path ), true );
		$first_after = (int) ( $state['offset'] ?? 0 );
		if ( $first_after <= $resumed_at ) throw new RuntimeException( 'Tras la muerte no siguió desde lo que el servidor ya tenía: ' . $first_after . ' ≤ ' . $resumed_at );
		if ( (int) ( $state['sent'] ?? 0 ) > filesize( $big ) ) throw new RuntimeException( 'Se volvió a subir lo que ya estaba en el servidor.' );
	}
}
clearstatcache();
if ( ! $receipt || $slices < 4 || null === $resumed_at || ! is_file( $root . $receipt['path'] ) || hash_file( 'sha256', $big ) !== hash_file( 'sha256', $root . $receipt['path'] ) || hash_file( 'sha256', $big ) !== $receipt['sha256'] ) throw new RuntimeException( 'La subida por tramos no publicó la copia verificada: ' . json_encode( array( $slices, $resumed_at, $receipt ) ) );
if ( glob( $root . '/backups/*.part-*' ) || is_file( $state_path ) ) throw new RuntimeException( 'Tras publicar quedó el temporal o el estado de la subida.' );
unlink( $root . $receipt['path'] );
unlink( $big );
unlink( $file );
rmdir( $root . '/backups' );
rmdir( $root );
echo "DIGITALÍSIMO Backups: SFTP verifica y publica sólo copias íntegras.\n";
