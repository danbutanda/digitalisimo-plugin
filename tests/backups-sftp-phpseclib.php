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
 *   (h) el motor «ssh2» forzado sin la extensión da un mensaje claro;
 *   (i) revisión H1, atacante en medio (tests/backups-sftp/mitm-proxy.php): deja pasar la primera conexión al servidor
 *       real volteando un bit del largo del primer paquete cifrado y manda las siguientes a un impostor con otra clave.
 *       Con un servidor que sólo ofrece aes256-ctr, el cliente no conecta (no negocia los cifrados que activan la
 *       reconexión automática de phpseclib); con aes256-ctr y aes128-ctr, negocia aes128-ctr, el bit roto corta la
 *       sesión y nunca reconecta. En los dos casos el impostor no recibe nada: ni contraseña ni clave ni respaldo;
 *   (j) si la clave del servidor de la sesión cambia después de conectar, la siguiente operación se corta sin hacer nada;
 *   (k) revisión P3: un servidor que sólo ofrece hmac-md5 no se acepta;
 *   (l) revisión P1: un borrado que falla por permisos (y stat tampoco ve el archivo) es un error, no «ya no existe»;
 *   (m) revisión P2: sin OpenSSL el cliente incluido no sube un respaldo grande y la pantalla lo avisa;
 *   (n) H20 del ensayo E3: la subida va por tramos (cada uno hasta su plazo) con el estado en el área del trabajo, cada
 *       tramo en una conexión nueva (otro tick); una muerte a mitad de un tramo no repite lo que el servidor ya tiene; la
 *       verificación también va por tramos y al final se publica la copia íntegra;
 *   (o) segunda revisión N1 [T4]: algo escribe en la zona ya verificada del temporal entre dos tramos: no se publica esa
 *       copia (la marca de la subida cambió y el SHA-256 final no sería el del ZIP); se sube de nuevo y se publica la buena;
 *   (p) N2 [T5]: el proceso muere justo después del renombre final (vuelve el estado de antes): la transferencia termina
 *       igual, con el archivo final que ya está (verificado entero), sin subir otra vez ni chocar con él;
 *   (r) revisión R1 (en p): el respaldo publicado lleva la fecha de ahora, no la marca de hace más de un año (una
 *       limpieza del servidor por antigüedad lo borraría); y un archivo final ajeno (otro tamaño, o el mismo tamaño y otro
 *       contenido) termina con un error permanente claro, sin tocarlo ni repetir la subida;
 *   (q) N3: si la copia de phpseclib no tiene las propiedades privadas de las que dependen las protecciones (un renombre),
 *       el cliente incluido no conecta: error claro y ninguna conexión.
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
	// (q) N3: una copia de phpseclib que renombró una de las propiedades privadas de las que dependen las protecciones.
	$saved_internals = Digitalisimo_Backups_Sftp_Client::$internals;
	Digitalisimo_Backups_Sftp_Client::$internals[] = 'bad_key_size_fix_renombrada';
	try {
		try { Digitalisimo_Backups_Sftp_Client::load(); throw new LogicException( 'Aceptó una copia de phpseclib sin sus propiedades.' ); }
		catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'no es la esperada' ) && false !== strpos( $e->getMessage(), 'bad_key_size_fix_renombrada' ), 'Mensaje: ' . $e->getMessage() ); }
		$probe = stream_socket_server( 'tcp://127.0.0.1:0' );
		$port = (int) substr( strrchr( stream_socket_get_name( $probe, false ), ':' ), 1 );
		try { ( new Digitalisimo_Backups_Destination_Sftp( array( 'sftp_engine' => 'phpseclib', 'sftp_host' => '127.0.0.1', 'sftp_port' => (string) $port, 'sftp_user' => 'x', 'sftp_password' => 'y', 'sftp_fingerprint' => str_repeat( 'A', 40 ), 'sftp_path' => '/' ), array( 'temp_dir' => $root . '/private', 'connection' => 'sftp' ) ) )->list(); throw new LogicException( 'Conectó con una copia de phpseclib sin sus propiedades.' ); }
		catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'no es la esperada' ), 'Mensaje: ' . $e->getMessage() ); }
		$check( false === @stream_socket_accept( $probe, 0.2 ), 'Abrió una conexión con una copia de phpseclib sin sus propiedades.' );
		fclose( $probe );
	} finally {
		Digitalisimo_Backups_Sftp_Client::$internals = $saved_internals;
	}
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

	// Arranca otro sshd sin privilegios con su clave de host y $extra en la configuración. Devuelve array( proceso, puerto ).
	$sshd_with = function( $name, $extra ) use ( $sshd, $k, $run ) {
		$run( 'ssh-keygen -q -t ed25519 -N "" -f ' . escapeshellarg( $k . '/' . $name . '_host' ) );
		$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
		$port = (int) substr( strrchr( stream_socket_get_name( $socket, false ), ':' ), 1 );
		fclose( $socket );
		file_put_contents( $k . '/' . $name . '.conf', "Port $port\nListenAddress 127.0.0.1\nHostKey $k/{$name}_host\nPidFile $k/$name.pid\nAuthorizedKeysFile $k/authorized_keys\nPasswordAuthentication yes\nKbdInteractiveAuthentication no\nUsePAM no\nStrictModes no\nSubsystem sftp internal-sftp\nLogLevel VERBOSE\n" . $extra );
		$proc = proc_open( array( $sshd, '-D', '-e', '-f', $k . '/' . $name . '.conf' ), array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'file', $k . '/' . $name . '.log', 'a' ), 2 => array( 'file', $k . '/' . $name . '.log', 'a' ) ), $pipes );
		register_shutdown_function( function() use ( $proc ) { if ( is_resource( $proc ) ) { @proc_terminate( $proc ); @proc_close( $proc ); } } );
		for ( $i = 0; $i < 100 && ! @fsockopen( '127.0.0.1', $port, $errno, $errstr, 0.2 ); $i++ ) usleep( 100000 );
		return array( $proc, $port );
	};
	$host_sha256 = function( $name ) use ( $fingerprint, $k ) { return $fingerprint( $k . '/' . $name . '_host.pub', 'sha256' ); };

	$case( 'i', function() use ( $check, $base, $key, $context, $file, $root, $k, $sshd_with, $host_sha256 ) {
		list( $impostor, $port_b ) = $sshd_with( 'impostor', '' );
		foreach ( array( 'solo256' => 'aes256-ctr', 'ctr' => 'aes256-ctr,aes128-ctr' ) as $label => $ciphers ) {
			list( $real, $port_a ) = $sshd_with( 'real_' . $label, "Ciphers $ciphers\n" );
			$fp_a = $host_sha256( 'real_' . $label );
			foreach ( array( 'clave' => $key, 'contraseña' => array( 'sftp_auth' => 'password', 'sftp_password' => 'Contraseña-SFTP-de-prueba' ) ) as $auth_label => $auth ) {
				// Un proxy nuevo por caso: su primera conexión siempre va al servidor real.
				file_put_contents( $k . '/impostor.log', '' );
				$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
				$port_p = (int) substr( strrchr( stream_socket_get_name( $socket, false ), ':' ), 1 );
				fclose( $socket );
				$log = $k . '/proxy_' . $label . '_' . md5( $auth_label ) . '.log';
				$proxy = proc_open( array( PHP_BINARY, __DIR__ . '/backups-sftp/mitm-proxy.php', (string) $port_p, (string) $port_a, (string) $port_b, $log ), array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'file', $log, 'a' ), 2 => array( 'file', $log, 'a' ) ), $pipes );
				for ( $i = 0; $i < 100 && false === strpos( (string) @file_get_contents( $log ), 'escuchando' ); $i++ ) usleep( 100000 );
				$outcome = 'subió';
				try { ( new Digitalisimo_Backups_Destination_Sftp( $auth + array( 'sftp_port' => (string) $port_p, 'sftp_engine' => 'phpseclib', 'sftp_fingerprint' => $fp_a ) + $base, $context ) )->put( $file ); }
				catch ( Throwable $e ) { $outcome = get_class( $e ) . ': ' . $e->getMessage(); }
				usleep( 300000 );
				$seen = (string) @file_get_contents( $k . '/impostor.log' );
				$proxied = (string) @file_get_contents( $log );
				proc_terminate( $proxy ); proc_close( $proxy );
				$check( 'subió' !== $outcome && ! preg_match( '/Accepted|Failed password|Connection from/', $seen ), "[$label/$auth_label] el impostor recibió la conexión: $outcome · " . trim( $seen ) );
				$check( false === strpos( $proxied, 'conexión 2' ), "[$label/$auth_label] hubo una segunda conexión (reconexión): " . $proxied );
				if ( 'solo256' === $label ) $check( false !== strpos( $outcome, 'sólo ofrece algoritmos' ), "[$label] sin un cifrado admitido el mensaje no lo explica: $outcome" );
			}
			$check( ! glob( $root . '/remote/backups/*' ), "[$label] quedó algo en la carpeta." );
			proc_terminate( $real ); proc_close( $real );
		}
		proc_terminate( $impostor ); proc_close( $impostor );
	} );

	$case( 'j', function() use ( $check, $base, $key, $context, $file, $sha256, $root ) {
		$destination = new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $context );
		$receipt = $destination->put( $file );
		// La clave del servidor de la sesión cambia (otra conexión u otro intercambio con otra clave).
		$client = Closure::bind( function() { return $this->client; }, $destination, 'Digitalisimo_Backups_Destination_Sftp' )();
		$sftp = Closure::bind( function() { return $this->sftp; }, $client, get_class( $client ) )();
		Closure::bind( function() { $this->server_public_host_key = "\0\0\0\x0bssh-ed25519\0\0\0\x20" . str_repeat( 'Z', 32 ); }, $sftp, 'DigitalisimoBackups\\Vendor\\phpseclib3\\Net\\SSH2' )();
		try { $destination->delete( $receipt ); throw new LogicException( 'Siguió operando con otra clave del servidor.' ); }
		catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'cambió durante la sesión' ), 'Mensaje: ' . $e->getMessage() ); }
		$check( is_file( $receipt['path'] ), 'La operación se hizo con otra clave del servidor.' );
		@unlink( $receipt['path'] );
	} );

	$case( 'k', function() use ( $check, $base, $key, $context, $file, $sshd_with, $host_sha256 ) {
		list( $legacy, $port ) = $sshd_with( 'md5', "Ciphers aes128-ctr\nMACs hmac-md5\n" );
		try { ( new Digitalisimo_Backups_Destination_Sftp( array( 'sftp_port' => (string) $port, 'sftp_engine' => 'phpseclib', 'sftp_fingerprint' => $host_sha256( 'md5' ) ) + $key + $base, $context ) )->list(); throw new LogicException( 'Aceptó un servidor que sólo ofrece hmac-md5.' ); }
		catch ( Digitalisimo_Backups_Permanent_Error $e ) { $check( false !== strpos( $e->getMessage(), 'sólo ofrece algoritmos' ), 'Mensaje: ' . $e->getMessage() ); }
		proc_terminate( $legacy ); proc_close( $legacy );
	} );

	$case( 'l', function() use ( $check, $base, $key, $context, $sha256, $root ) {
		$dir = $root . '/remote/backups/cerrada';
		mkdir( $dir, 0700 );
		file_put_contents( $dir . '/digitalisimo-backup-x.zip', 'x' );
		chmod( $dir, 0000 );
		try {
			( new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $context ) )->delete( array( 'provider' => 'sftp', 'path' => $dir . '/digitalisimo-backup-x.zip' ) );
			$outcome = 'dado por borrado';
		} catch ( Exception $e ) {
			$outcome = 'error: ' . $e->getMessage();
		}
		chmod( $dir, 0700 );
		$check( 0 === strpos( $outcome, 'error: No se pudo eliminar' ) && is_file( $dir . '/digitalisimo-backup-x.zip' ), 'Un borrado sin permiso se dio por hecho: ' . $outcome );
		@unlink( $dir . '/digitalisimo-backup-x.zip' );
		@rmdir( $dir );
	} );

	$case( 'm', function() use ( $check, $base, $key, $context, $sha256, $root ) {
		$big = $root . '/digitalisimo-backup-mediano.zip';
		$h = fopen( $big, 'wb' ); for ( $i = 0; $i < 9; $i++ ) fwrite( $h, random_bytes( 1048576 ) ); fclose( $h );
		Digitalisimo_Backups_Sftp_Client::$openssl = false;
		try {
			$help = implode( ' ', Digitalisimo_Backups_Destination_Sftp::help() );
			try { ( new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $context ) )->put( $big ); $outcome = 'subió'; }
			catch ( Digitalisimo_Backups_Permanent_Error $e ) { $outcome = $e->getMessage(); }
		} finally {
			Digitalisimo_Backups_Sftp_Client::$openssl = null;
		}
		$check( false !== strpos( $outcome, 'OpenSSL' ) && ! glob( $root . '/remote/backups/*' ), 'Sin OpenSSL subió un respaldo grande: ' . $outcome );
		$check( false !== strpos( $help, 'no tiene la extensión OpenSSL' ), 'La pantalla no avisa de la falta de OpenSSL.' );
		@unlink( $big );
	} );

	$case( 'n', function() use ( $check, $base, $key, $context, $sha256, $root, $parts ) {
		$big = $root . '/digitalisimo-backup-tramos.zip';
		$h = fopen( $big, 'wb' ); for ( $i = 0; $i < 6; $i++ ) fwrite( $h, random_bytes( 1048576 ) ); fwrite( $h, random_bytes( 4321 ) ); fclose( $h );
		$state_path = $root . '/private/transfer.json';
		$die = 2;
		$sliced = array( 'state' => new Digitalisimo_Backups_Transfer_State( $state_path ), 'progress' => function( $percent, $message ) use ( &$die ) { if ( 0 === --$die ) throw new RuntimeException( 'proceso muerto' ); } ) + $context;
		$receipt = null;
		$slices = 0;
		$resumed_at = null;
		$first_after = null;
		for ( $i = 0; $i < 60 && ! $receipt; $i++ ) {
			$before = $parts();
			$before_size = $before ? filesize( $before[0] ) : 0;
			$destination = new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $sliced );
			$destination->set_deadline( microtime( true ) - 1 ); // Plazo vencido: cada tramo avanza el mínimo (un MiB).
			try {
				$receipt = $destination->put( $big );
			} catch ( Digitalisimo_Backups_Transfer_Pending $e ) {
				$slices++;
			} catch ( RuntimeException $e ) {
				if ( 'proceso muerto' !== $e->getMessage() ) throw $e;
				clearstatcache();
				$after = $parts();
				$check( $after && filesize( $after[0] ) > $before_size, 'La muerte simulada no dejó nada nuevo en el servidor.' );
				$resumed_at = filesize( $after[0] );
				continue;
			}
			if ( null !== $resumed_at && null === $first_after ) {
				$state = json_decode( (string) @file_get_contents( $state_path ), true );
				$first_after = (int) ( $state['offset'] ?? 0 );
				$check( $first_after > $resumed_at && (int) ( $state['sent'] ?? 0 ) <= filesize( $big ), 'Tras la muerte no siguió desde lo que el servidor ya tenía: ' . json_encode( array( $first_after, $resumed_at, $state['sent'] ?? null ) ) );
			}
		}
		clearstatcache();
		$check( $receipt && $slices >= 4 && null !== $resumed_at && is_file( $receipt['path'] ) && hash_file( 'sha256', $big ) === hash_file( 'sha256', $receipt['path'] ) && hash_file( 'sha256', $big ) === $receipt['sha256'], 'La subida por tramos no publicó la copia verificada: ' . json_encode( array( $slices, $resumed_at, $receipt ) ) );
		$check( ! $parts() && ! is_file( $state_path ), 'Tras publicar quedó el temporal o el estado de la subida.' );
		@unlink( $receipt['path'] );
		@unlink( $big );
	} );

	// Tramos con el plazo vencido (un MiB cada uno), cada uno en una conexión nueva; $before( $estado ) corre antes de cada
	// llamada. Devuelve array( recibo, errores, llamadas ).
	$slices = function( $big, $state_path, callable $before = null, $limit = 80 ) use ( $base, $key, $context, $sha256 ) {
		$sliced = array( 'state' => new Digitalisimo_Backups_Transfer_State( $state_path ) ) + $context;
		$receipt = null; $errors = array(); $calls = 0;
		while ( ! $receipt && $calls < $limit ) {
			$calls++;
			if ( $before ) $before( json_decode( (string) @file_get_contents( $state_path ), true ) );
			$destination = new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $sliced );
			$destination->set_deadline( microtime( true ) - 1 );
			try { $receipt = $destination->put( $big ); }
			catch ( Digitalisimo_Backups_Transfer_Pending $e ) { continue; }
			catch ( Exception $e ) { $errors[] = $e->getMessage(); }
		}
		return array( $receipt, $errors, $calls );
	};

	$case( 'o', function() use ( $check, $root, $parts, $slices ) {
		$big = $root . '/digitalisimo-backup-alterado.zip';
		$h = fopen( $big, 'wb' ); for ( $i = 0; $i < 5; $i++ ) fwrite( $h, random_bytes( 1048576 ) ); fwrite( $h, random_bytes( 999 ) ); fclose( $h );
		$altered = false;
		list( $receipt, $errors ) = $slices( $big, $root . '/private/transfer-o.json', function( $state ) use ( &$altered, $parts ) {
			if ( $altered || ! is_array( $state ) || 'verify' !== ( $state['stage'] ?? '' ) || (int) ( $state['verified'] ?? 0 ) < 2097152 ) return;
			$part = $parts();
			$h = fopen( $part[0], 'r+b' ); fseek( $h, 10 ); fwrite( $h, 'CORRUPTO' ); fclose( $h );
			$altered = true;
		} );
		$check( $altered, 'La prueba no llegó a alterar la zona ya verificada.' );
		$check( $receipt && hash_file( 'sha256', $big ) === hash_file( 'sha256', $receipt['path'] ), 'Se publicó una copia que no es el ZIP: ' . json_encode( array( $receipt ? hash_file( 'sha256', $receipt['path'] ) : null, hash_file( 'sha256', $big ), $errors ) ) );
		$check( (bool) preg_grep( '/cambió en el servidor|SHA-256/', $errors ), 'La alteración no se detectó: ' . json_encode( $errors ) );
		@unlink( $receipt['path'] ); @unlink( $big );
	} );

	$case( 'p', function() use ( $check, $root, $parts, $slices ) {
		$big = $root . '/digitalisimo-backup-renombre.zip';
		$h = fopen( $big, 'wb' ); for ( $i = 0; $i < 4; $i++ ) fwrite( $h, random_bytes( 1048576 ) ); fwrite( $h, random_bytes( 777 ) ); fclose( $h );
		$state_path = $root . '/private/transfer-p.json';
		$last = null;
		list( $receipt ) = $slices( $big, $state_path, function( $state ) use ( &$last ) { $last = $state; } );
		$check( $receipt && is_file( $receipt['path'] ) && is_array( $last ), 'La primera subida no se publicó.' );
		clearstatcache();
		$check( filemtime( $receipt['path'] ) >= time() - 600, 'El respaldo publicado quedó con la fecha de la marca: ' . gmdate( 'Y-m-d', filemtime( $receipt['path'] ) ) );
		// Muerte justo después del renombre: vuelve el estado de la llamada anterior.
		file_put_contents( $state_path, json_encode( $last ) );
		$sent = (int) ( $last['sent'] ?? 0 );
		$after = null;
		list( $again, $errors, $calls ) = $slices( $big, $state_path, function( $state ) use ( &$after ) { if ( is_array( $state ) ) $after = $state; } );
		$check( $again && $again['path'] === $receipt['path'] && hash_file( 'sha256', $big ) === hash_file( 'sha256', $again['path'] ) && ! $errors && $calls <= 12, 'Tras morir después del renombre la transferencia no terminó con el archivo que ya estaba: ' . json_encode( array( $again, $errors, $calls ) ) );
		$check( ! $parts() && (int) ( $after['sent'] ?? 0 ) === $sent, 'Se volvió a subir el respaldo tras morir después del renombre.' );
		@unlink( $receipt['path'] ); @unlink( $big );
	} );

	$case( 'r', function() use ( $check, $root, $base, $key, $context, $sha256 ) {
		// Hasta el primer error que no sea «sigue en el próximo paso»: devuelve array( excepción, llamadas ).
		$until_error = function( $big, $state_path ) use ( $base, $key, $context, $sha256 ) {
			$sliced = array( 'state' => new Digitalisimo_Backups_Transfer_State( $state_path ) ) + $context;
			for ( $calls = 1; $calls <= 40; $calls++ ) {
				$destination = new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $sliced );
				$destination->set_deadline( microtime( true ) - 1 );
				try { $destination->put( $big ); return array( null, $calls ); }
				catch ( Digitalisimo_Backups_Transfer_Pending $e ) { continue; }
				catch ( Exception $e ) { return array( $e, $calls ); }
			}
			return array( null, $calls );
		};
		$big = $root . '/digitalisimo-backup-ajeno.zip';
		$h = fopen( $big, 'wb' ); for ( $i = 0; $i < 2; $i++ ) fwrite( $h, random_bytes( 1048576 ) ); fwrite( $h, random_bytes( 555 ) ); fclose( $h );
		$final = $root . '/remote/backups/digitalisimo-backup-ajeno.zip';
		// 1) Ya hay un archivo con ese nombre y otro tamaño: el renombre no lo pisa y el error lo dice.
		file_put_contents( $final, 'otro respaldo de otra persona' );
		list( $error ) = $until_error( $big, $root . '/private/transfer-r1.json' );
		$check( $error instanceof Digitalisimo_Backups_Permanent_Error && false !== strpos( $error->getMessage(), 'no es este respaldo' ) && 'otro respaldo de otra persona' === file_get_contents( $final ), 'Un archivo final ajeno no terminó en un error permanente claro: ' . ( $error ? get_class( $error ) . ': ' . $error->getMessage() : 'sin error' ) );
		// 2) El proceso «murió» tras el renombre y el archivo final tiene el mismo tamaño pero otro contenido.
		unlink( $final );
		$state_path = $root . '/private/transfer-r2.json';
		$last = null;
		$sliced = array( 'state' => new Digitalisimo_Backups_Transfer_State( $state_path ) ) + $context;
		for ( $i = 0, $done = null; $i < 40 && ! $done; $i++ ) {
			$last = json_decode( (string) @file_get_contents( $state_path ), true );
			$destination = new Digitalisimo_Backups_Destination_Sftp( $base + $key + array( 'sftp_fingerprint' => $sha256['ed25519'] ), $sliced );
			$destination->set_deadline( microtime( true ) - 1 );
			try { $done = $destination->put( $big ); } catch ( Digitalisimo_Backups_Transfer_Pending $e ) { continue; }
		}
		$check( $done && is_file( $final ), 'La subida de control no se publicó.' );
		$h = fopen( $final, 'r+b' ); fseek( $h, 1048576 + 7 ); fwrite( $h, 'X' ); fclose( $h );
		file_put_contents( $state_path, json_encode( $last ) );
		list( $error, $calls ) = $until_error( $big, $state_path );
		$check( $error instanceof Digitalisimo_Backups_Permanent_Error && false !== strpos( $error->getMessage(), 'no es este respaldo' ) && $calls <= 6 && is_file( $final ), 'Un archivo final con otro contenido no terminó en un error permanente claro: ' . ( $error ? get_class( $error ) . ': ' . $error->getMessage() : 'sin error' ) . ' en ' . $calls . ' llamadas' );
		@unlink( $final ); @unlink( $big );
	} );

	proc_terminate( $server );
	proc_close( $server );
	if ( $GLOBALS['bk_fail'] && is_file( $k . '/sshd.log' ) ) echo "sshd: " . trim( (string) file_get_contents( $k . '/sshd.log' ) ) . "\n";
}

exec( 'rm -rf ' . escapeshellarg( $root ) );
if ( $GLOBALS['bk_fail'] ) { echo 'FALLAN ' . $GLOBALS['bk_fail'] . " casos de SFTP sin la extensión ssh2.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: SFTP sin la extensión ssh2 (cliente incluido con prefijo propio), clave privada cifrada y huella SHA-256.\n";
