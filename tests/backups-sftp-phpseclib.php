<?php
/**
 * SFTP sin la extensión ssh2 (I5): el cliente incluido (phpseclib 3 con prefijo propio) contra un sshd real de OpenSSH
 * que la prueba arranca sin privilegios en 127.0.0.1, con claves de servidor ED25519, ECDSA y RSA.
 *   (a) motor automático sin ssh2 → cliente incluido; clave privada ED25519 de OpenSSH cifrada con frase y huella
 *       SHA-256: put() sube, verifica y publica; size, get, list, delete y «Probar conexión»; ningún `.part-*` queda;
 *   (b) la huella fijada es la de la clave RSA (SHA-1 hexadecimal, como las de 1.0.61): la negociada (ED25519) no
 *       coincide y el cliente prueba los demás tipos hasta la RSA;
 *   (c) huella equivocada: error permanente antes de autenticarse, con las claves que presentó el servidor (SHA-256);
 *   (d) frase equivocada o sin frase: error permanente que no muestra la clave; clave RSA PEM sin frase sí entra;
 *   (e) al guardar, la clave con frase se abre una vez y queda sin frase en el almacén (cada conexión se ahorra los
 *       segundos de bcrypt_pbkdf en PHP); sin clave pública, se calcula de la privada (y la frase equivocada se rechaza);
 *   (f) memoria acotada: con memory_limit=64M sube y descarga un archivo de 72 MiB, aunque otro plugin haya definido
 *       NET_SSH2_LOGGING (registro de cada paquete en memoria) y cargado su propio phpseclib3 sin prefijo;
 *   (g) cancelar a mitad de la subida no deja el temporal en el servidor;
 *   (h) el motor «ssh2» forzado sin la extensión da un mensaje claro.
 * Sin sshd/ssh-keygen sólo corren los casos sin servidor, salvo en la CI (CI=true, el workflow lo instala) o con
 * BK_REQUIRE_SSHD=1.
 */
ini_set( 'memory_limit', '64M' );
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['bk_fail'] = 0;
$root = sys_get_temp_dir() . '/digitalisimo-sftp-lib-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/remote/backups', 0700, true );
mkdir( $root . '/private', 0700, true );
mkdir( $root . '/keys', 0700, true );

// Otro plugin con su propio phpseclib3 (sin prefijo) y el registro de paquetes activado.
file_put_contents( $root . '/otro-phpseclib.php', "<?php\nnamespace phpseclib3\\Net;\nclass SFTP { public function __construct() { throw new \\RuntimeException( 'Se usó el phpseclib de otro plugin.' ); } }\nclass SSH2 extends SFTP {}\n" );
require $root . '/otro-phpseclib.php';
define( 'NET_SSH2_LOGGING', 2 );
define( 'NET_SFTP_LOGGING', 2 );

function wp_basename( $path ) { return basename( $path ); }
require __DIR__ . '/../digitalisimo-backups/includes/class-backups-job-store.php';
foreach ( array( 'http', 'destination', 'destinations', 'destination-sftp' ) as $part ) require __DIR__ . '/../digitalisimo-backups/includes/destinations/class-' . $part . '.php';
// El destino carga solo su cliente incluido; aquí también, si existe, para comprobarlo directamente (en 1.0.64 no existe).
if ( is_file( __DIR__ . '/../digitalisimo-backups/includes/destinations/class-sftp-client.php' ) ) require_once __DIR__ . '/../digitalisimo-backups/includes/destinations/class-sftp-client.php';

$case = function( $label, callable $test ) {
	try { $test(); echo "ok ($label)\n"; }
	catch ( Throwable $e ) { $GLOBALS['bk_fail']++; echo 'FALLA (' . $label . '): ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n"; }
};
$check = function( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); };
$run = function( $command ) { exec( $command . ' 2>&1', $out, $code ); if ( 0 !== $code ) throw new RuntimeException( $command . ': ' . implode( ' ', $out ) ); return implode( "\n", $out ); };
$fingerprint = function( $pub, $hash ) use ( $run ) { return explode( ' ', trim( $run( 'ssh-keygen -l -E ' . $hash . ' -f ' . escapeshellarg( $pub ) ) ) )[1]; };

if ( function_exists( 'ssh2_connect' ) ) { echo "La prueba necesita un PHP sin la extensión ssh2.\n"; exit( 1 ); }

// (h) sin servidor: el motor «ssh2» forzado sin la extensión.
$case( 'h', function() use ( $check, $root ) {
	$destination = new Digitalisimo_Backups_Destination_Sftp( array( 'sftp_engine' => 'ssh2', 'sftp_host' => '127.0.0.1', 'sftp_user' => 'x', 'sftp_password' => 'y', 'sftp_fingerprint' => str_repeat( 'A', 40 ), 'sftp_path' => '/' ), array( 'temp_dir' => $root . '/private', 'connection' => 'sftp' ) );
	$check( 'ssh2' === $destination->engine(), 'El motor forzado no se respetó.' );
	try { $destination->list(); throw new LogicException( 'Usó ssh2 sin la extensión.' ); }
	catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'extensión ssh2' ), 'Mensaje sin la extensión: ' . $e->getMessage() ); }
	$auto = new Digitalisimo_Backups_Destination_Sftp( array( 'sftp_engine' => 'auto' ), array() );
	$check( 'phpseclib' === $auto->engine() && class_exists( 'Digitalisimo_Backups_Sftp_Client' ) && Digitalisimo_Backups_Sftp_Client::available(), 'Sin ssh2, el motor automático no eligió el cliente incluido.' );
	$check( ! class_exists( 'phpseclib3\\Net\\SSH2', false ) || ! is_subclass_of( 'DigitalisimoBackups\\Vendor\\phpseclib3\\Net\\SFTP', 'phpseclib3\\Net\\SSH2' ), 'El cliente incluido no está aislado del phpseclib de otro plugin.' );
} );

$sshd = is_executable( '/usr/sbin/sshd' ) ? '/usr/sbin/sshd' : '';
$keygen = trim( (string) shell_exec( 'command -v ssh-keygen' ) );
if ( '' === $sshd || '' === $keygen ) {
	// En la CI (GitHub pone CI=true) el workflow instala sshd: sin él, la prueba falla en vez de omitirse.
	if ( getenv( 'CI' ) || getenv( 'BK_REQUIRE_SSHD' ) ) { echo "FALLA: sin sshd o ssh-keygen.\n"; exit( 1 ); }
	echo "OMITIDOS (a)-(g): este equipo no tiene sshd o ssh-keygen.\n";
} else {
	// Servidor: tres claves de host, usuario actual con clave pública, sólo SFTP interno, sin PAM ni contraseñas.
	$k = $root . '/keys';
	foreach ( array( 'ed25519', 'ecdsa', 'rsa' ) as $type ) $run( 'ssh-keygen -q -t ' . $type . ' -N "" -f ' . escapeshellarg( $k . '/host_' . $type ) );
	$run( 'ssh-keygen -q -t ed25519 -N ' . escapeshellarg( 'frase de la clave' ) . ' -C respaldos -f ' . escapeshellarg( $k . '/user_ed25519' ) );
	$run( 'ssh-keygen -q -t rsa -b 2048 -m PEM -N "" -C pem -f ' . escapeshellarg( $k . '/user_rsa' ) );
	file_put_contents( $k . '/authorized_keys', file_get_contents( $k . '/user_ed25519.pub' ) . file_get_contents( $k . '/user_rsa.pub' ) );
	chmod( $k . '/authorized_keys', 0600 );
	$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
	$port = (int) substr( strrchr( stream_socket_get_name( $socket, false ), ':' ), 1 );
	fclose( $socket );
	$config = "Port $port\nListenAddress 127.0.0.1\nHostKey $k/host_ed25519\nHostKey $k/host_ecdsa\nHostKey $k/host_rsa\nPidFile $k/sshd.pid\nAuthorizedKeysFile $k/authorized_keys\nPasswordAuthentication no\nKbdInteractiveAuthentication no\nUsePAM no\nStrictModes no\nSubsystem sftp internal-sftp\nLogLevel ERROR\n";
	file_put_contents( $k . '/sshd_config', $config );
	$server = proc_open( array( $sshd, '-D', '-e', '-f', $k . '/sshd_config' ), array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'file', $k . '/sshd.log', 'a' ), 2 => array( 'file', $k . '/sshd.log', 'a' ) ), $pipes );
	// Si la prueba muere a medias, sshd no queda vivo (heredaría los descriptores del proceso, también cerrojos).
	register_shutdown_function( function() use ( $server ) { if ( is_resource( $server ) ) { @proc_terminate( $server ); @proc_close( $server ); } } );
	for ( $i = 0; $i < 100 && ! @fsockopen( '127.0.0.1', $port, $errno, $errstr, 0.2 ); $i++ ) usleep( 100000 );
	$user = function_exists( 'posix_getpwuid' ) ? posix_getpwuid( posix_geteuid() )['name'] : get_current_user();
	$sha256 = array(); $sha1 = array();
	foreach ( array( 'ed25519', 'ecdsa', 'rsa' ) as $type ) {
		$sha256[ $type ] = $fingerprint( $k . '/host_' . $type . '.pub', 'sha256' );
		// SHA-1 hexadecimal de la clave (el formato que guardaba la extensión ssh2 hasta 1.0.61).
		$sha1[ $type ] = strtoupper( sha1( base64_decode( explode( ' ', trim( file_get_contents( $k . '/host_' . $type . '.pub' ) ) )[1] ) ) );
	}
	$base = array( 'sftp_host' => '127.0.0.1', 'sftp_port' => (string) $port, 'sftp_user' => $user, 'sftp_path' => $root . '/remote/backups', 'sftp_engine' => 'auto' );
	$typed = array( 'sftp_auth' => 'key', 'sftp_private_key' => trim( file_get_contents( $k . '/user_ed25519' ) ), 'sftp_key_passphrase' => 'frase de la clave', 'sftp_public_key' => '' );
	// Lo que se guarda al pulsar «Guardar» (la clave ya sin frase, la pública calculada): lo que usan los trabajos.
	$started = microtime( true );
	$key = array_intersect_key( Digitalisimo_Backups_Destination_Sftp::sanitize( array( 'sftp_fingerprint' => $sha256['ed25519'] ) + $base + $typed, array() ), $typed );
	echo '  abrir la clave con frase al guardar: ' . round( microtime( true ) - $started, 1 ) . " s\n";
	$context = array( 'temp_dir' => $root . '/private', 'connection' => 'sftp' );
	$file = $root . '/digitalisimo-backup-prueba.zip';
	$h = fopen( $file, 'wb' ); for ( $i = 0; $i < 3; $i++ ) fwrite( $h, random_bytes( 1048576 ) ); fwrite( $h, random_bytes( 12345 ) ); fclose( $h );
	$parts = function() use ( $root ) { return glob( $root . '/remote/backups/*.part-*' ) ?: array(); };

	$case( 'a', function() use ( $check, $base, $key, $context, $file, $sha256, $root, $parts ) {
		$destination = new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $context );
		$check( 'phpseclib' === $destination->engine(), 'El motor automático sin ssh2 no es el cliente incluido.' );
		$receipt = $destination->put( $file );
		$path = $root . '/remote/backups/' . basename( $file );
		$check( $path === $receipt['path'] && is_file( $path ) && hash_file( 'sha256', $file ) === hash_file( 'sha256', $path ) && hash_file( 'sha256', $file ) === $receipt['sha256'], 'put() no publicó la copia verificada.' );
		$check( ! $parts(), 'Quedó un temporal .part-* en el servidor.' );
		$check( filesize( $file ) === $destination->size( $receipt ), 'size().' );
		$destination->get( $receipt, $root . '/private/back' );
		$check( hash_file( 'sha256', $file ) === hash_file( 'sha256', $root . '/private/back' ), 'get().' );
		@unlink( $root . '/private/back' );
		$check( array( basename( $file ) ) === array_column( $destination->list(), 'name' ), 'list(): ' . json_encode( array_column( $destination->list(), 'name' ) ) );
		$destination->delete( $receipt );
		$destination->delete( $receipt );
		$check( false === $destination->size( $receipt ) && ! is_file( $path ), 'delete().' );
		$steps = $destination->test();
		$check( 4 === count( $steps ) && ! glob( $root . '/remote/backups/*' ), '«Probar conexión» no completó sus pasos o dejó archivos: ' . json_encode( $steps ) );
	} );

	$case( 'b', function() use ( $check, $base, $key, $context, $file, $sha1, $root ) {
		// La SHA-1 hexadecimal de la clave RSA (como la guardaba la extensión ssh2): ED25519 se negocia primero y no vale.
		$destination = new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha1['rsa'] ), $context );
		$receipt = $destination->put( $file );
		$check( is_file( $receipt['path'] ), 'Con la huella de la clave RSA no se conectó probando los demás tipos.' );
		$destination->delete( $receipt );
	} );

	$case( 'c', function() use ( $check, $base, $typed, $context, $file, $sha256, $root ) {
		$wrong = array( 'sftp_key_passphrase' => 'otra frase' ) + $typed;
		try { ( new Digitalisimo_Backups_Destination_Sftp( $base + $wrong + array( 'sftp_fingerprint' => 'SHA256:' . str_repeat( 'A', 43 ) ), $context ) )->put( $file ); throw new LogicException( 'Aceptó otra huella.' ); }
		catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			$message = $e->getMessage();
			// La frase equivocada daría «no se pudo abrir»: el mensaje de la huella prueba que no se llegó a autenticar.
			$check( 0 === strpos( $message, 'La huella de la clave SSH del servidor SFTP no coincide con la configurada.' ), 'No se rechazó por la huella antes de autenticarse: ' . $message );
			foreach ( $sha256 as $type => $value ) $check( false !== strpos( $message, $value ), 'El mensaje no dice la huella ' . $type . ' que presentó el servidor: ' . $message );
		}
		$check( ! glob( $root . '/remote/backups/*' ), 'Con otra huella se escribió en el servidor.' );
	} );

	$case( 'd', function() use ( $check, $base, $typed, $context, $file, $sha256, $k ) {
		// Una clave guardada con su frase antes de esta versión se abre al conectar: con la frase correcta entra.
		$check( is_array( ( new Digitalisimo_Backups_Destination_Sftp( $base + $typed + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $context ) )->list() ), 'La clave cifrada con su frase no entró.' );
		foreach ( array( 'otra frase', '' ) as $passphrase ) {
			try { ( new Digitalisimo_Backups_Destination_Sftp( $base + array( 'sftp_key_passphrase' => $passphrase ) + $typed + array( 'sftp_fingerprint' => $sha256['ecdsa'] . ' ' . $sha256['ed25519'] ), $context ) )->list(); throw new LogicException( 'Entró con la frase «' . $passphrase . '».' ); }
			catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'La clave privada SFTP no se pudo abrir' ) && false === strpos( $e->getMessage(), 'PRIVATE' ), 'Mensaje de la frase equivocada: ' . $e->getMessage() ); }
		}
		$pem = array( 'sftp_auth' => 'key', 'sftp_private_key' => trim( file_get_contents( $k . '/user_rsa' ) ), 'sftp_key_passphrase' => '' );
		$check( is_array( ( new Digitalisimo_Backups_Destination_Sftp( $base + $pem + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $context ) )->list() ), 'La clave RSA PEM sin frase no entró.' );
		// Contraseña: este sshd no la admite, así que es un error permanente de autenticación (no un reintento).
		try { ( new Digitalisimo_Backups_Destination_Sftp( $base + array( 'sftp_auth' => 'password', 'sftp_password' => 'no-vale' ) + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $context ) )->list(); throw new LogicException( 'Entró con contraseña.' ); }
		catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( 'No se pudo autenticar en SFTP.' === $e->getMessage(), 'Mensaje de autenticación: ' . $e->getMessage() ); }
	} );

	$case( 'e', function() use ( $check, $base, $key, $typed, $sha256, $k ) {
		$input = array( 'sftp_fingerprint' => $sha256['ed25519'] ) + $base + $typed;
		$saved = $key;
		$check( '' === $saved['sftp_key_passphrase'] && false !== strpos( $saved['sftp_private_key'], 'BEGIN OPENSSH PRIVATE KEY' ) && $saved['sftp_private_key'] !== $typed['sftp_private_key'], 'La clave con frase no quedó abierta al guardar.' );
		$expected = implode( ' ', array_slice( explode( ' ', trim( file_get_contents( $k . '/user_ed25519.pub' ) ) ), 0, 2 ) );
		$check( 0 === strpos( $saved['sftp_public_key'], $expected . ' ' ), 'No se calculó la clave pública de la privada: ' . $saved['sftp_public_key'] );
		$check( Digitalisimo_Backups_Destination_Sftp::ready( $base + $saved + array( 'sftp_fingerprint' => $sha256['ed25519'] ) ), 'Con la clave pública calculada la conexión no queda lista.' );
		try { Digitalisimo_Backups_Destination_Sftp::sanitize( array( 'sftp_key_passphrase' => 'otra' ) + $input, array() ); throw new LogicException( 'Guardó con la frase equivocada.' ); }
		catch ( Exception $e ) { $check( false !== strpos( $e->getMessage(), 'no se pudo abrir' ), 'Mensaje al guardar con la frase equivocada: ' . $e->getMessage() ); }
	} );

	$case( 'f', function() use ( $check, $base, $key, $context, $sha256, $root ) {
		$big = $root . '/digitalisimo-backup-grande.zip';
		$h = fopen( $big, 'wb' ); for ( $i = 0; $i < 72; $i++ ) fwrite( $h, random_bytes( 1048576 ) ); fclose( $h );
		$before = memory_get_usage();
		$destination = new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $context );
		$receipt = $destination->put( $big );
		$destination->get( $receipt, $root . '/private/grande' );
		$check( hash_file( 'sha256', $big ) === hash_file( 'sha256', $root . '/private/grande' ), 'La copia grande no coincide.' );
		$peak = memory_get_peak_usage();
		echo '  memoria pico ' . round( $peak / 1048576, 1 ) . " MiB (límite 64M)\n";
		$check( $peak < 40 * 1048576, 'La memoria creció con el archivo: ' . $peak );
		$destination->delete( $receipt );
		@unlink( $big ); @unlink( $root . '/private/grande' );
	} );

	$case( 'g', function() use ( $check, $base, $key, $context, $file, $sha256, $root, $parts ) {
		$cancel = $context;
		$cancel['progress'] = function( $percent, $message ) { if ( $percent >= 92 ) throw new Digitalisimo_Backups_Job_Cancelled( 'cancelado' ); };
		try { ( new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $cancel ) )->put( $file ); throw new LogicException( 'No se canceló.' ); }
		catch ( Digitalisimo_Backups_Job_Cancelled $e ) { $check( true, '' ); }
		$check( ! $parts() && ! glob( $root . '/remote/backups/*' ), 'Cancelar a mitad dejó archivos en el servidor: ' . json_encode( glob( $root . '/remote/backups/*' ) ) );
	} );

	proc_terminate( $server );
	proc_close( $server );
	if ( $GLOBALS['bk_fail'] && is_file( $k . '/sshd.log' ) ) echo "sshd: " . trim( (string) file_get_contents( $k . '/sshd.log' ) ) . "\n";
}

exec( 'rm -rf ' . escapeshellarg( $root ) );
if ( $GLOBALS['bk_fail'] ) { echo 'FALLAN ' . $GLOBALS['bk_fail'] . " casos de SFTP sin la extensión ssh2.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: SFTP sin la extensión ssh2 (cliente incluido con prefijo propio), clave privada cifrada y huella SHA-256.\n";
