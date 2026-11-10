<?php
/**
 * Ensayo E2 en el hosting del propietario (Webuzo + OpenLiteSpeed, memory_limit=64M): tres caminos que cargaban el
 * respaldo entero en memoria y fallaban con un ZIP mayor que la memoria de PHP. Cada caso corre en un proceso propio con
 * `-d memory_limit=64M` y un archivo de 80–100 MiB (más que memory_limit):
 *   (h9) SFTP: put() (subida, verificación y publicación) y get() con la extensión ssh2 simulada por un envoltorio de
 *        flujos «ssh2.sftp» en PHP: escrituras de 1 MiB como mucho, memoria de PHP y memoria del proceso (VmHWM de
 *        /proc) acotadas, y get() rechaza una descarga más corta que el tamaño que informa el servidor. Antes,
 *        stream_copy_to_stream() mapeaba el ZIP entero y se lo entregaba de una vez a la extensión: la memoria del proceso
 *        subía al tamaño del respaldo (en Webuzo, SIGKILL por el límite duro; E2 midió 204 MiB con un ZIP de 190 MB).
 *        Hacia un envoltorio en PHP como el de esta prueba, PHP parte esa copia en lecturas de 8 KiB y no se nota, así que
 *        la prueba exige además lo que distingue la copia por bloques: un latido por bloque (con 1.0.64, ninguno) y que
 *        cancelar a mitad detenga la subida sin dejar nada;
 *   (h16) «Descargar»: Digitalisimo_Backups::download() servido por el servidor web de PHP (`php -S`) con
 *        `output_buffering=On` (el «default output handler» sin límite del PHP de Webuzo): 200, Content-Length exacto y
 *        el respaldo completo, sin lo que se imprimió antes. Antes, readfile() con ese búfer intentaba reservar el archivo
 *        entero (500; con output_buffering=4096 el búfer se vacía solo y no falla, pero lo impreso antes iba al ZIP);
 *   (http) sin cURL, la API de WordPress recibe el cuerpo entero: un archivo que no cabe en lo que queda de memoria se
 *        rechaza con un mensaje claro antes de leerlo, en vez de agotar la memoria.
 * Necesita proc_open y un PHP del sistema (la CI y la PC); en php-wasm se omite y lo dice.
 * Debe fallar con 1.0.64 (d3ac3fa8).
 */
const BK64_MIB = 1048576;
/** Bloque $i del archivo grande de prueba: 1 MiB determinista y distinto en cada bloque. */
function bk64_block( $i ) { return str_repeat( hash( 'sha256', 'bloque-' . $i, true ), 32768 ); }
function bk64_write( $path, $mib ) {
	$handle = fopen( $path, 'wb' );
	for ( $i = 0; $i < $mib; $i++ ) fwrite( $handle, bk64_block( $i ) );
	fclose( $handle );
}
/**
 * Pico de memoria residente del proceso (VmHWM de /proc, en bytes), o 0 si el sistema no lo da. Cuenta también lo que
 * PHP mapea de un archivo (mmap), que no pasa por memory_limit pero sí por el límite duro del hosting (en Webuzo, 2 ×
 * memory_limit sobre la memoria del proceso: SIGKILL sin registro).
 */
function bk64_rss_peak() {
	$status = @file_get_contents( '/proc/self/status' );
	return is_string( $status ) && preg_match( '/^VmHWM:\s+(\d+) kB/m', $status, $m ) ? (int) $m[1] * 1024 : 0;
}
function bk64_sha( $mib ) {
	$hash = hash_init( 'sha256' );
	for ( $i = 0; $i < $mib; $i++ ) hash_update( $hash, bk64_block( $i ) );
	return hash_final( $hash );
}

/* ------------------------------------------------------------------ (h16) el servidor web de PHP como enrutador */
if ( 'cli-server' === PHP_SAPI ) {
	require __DIR__ . '/lib/backups-env.php';
	function nocache_headers() { header( 'Cache-Control: no-store' ); }
	$mib = (int) getenv( 'BK64_MIB' );
	$name = 'digitalisimo-backup-grande-20261109-120000-0000000a.zip';
	$path = bk_storage() . '/' . $name;
	bk64_write( $path, $mib );
	update_option( Digitalisimo_Backups::HISTORY, array( array( 'date' => '2026-11-09 12:00:00', 'file' => $name, 'size' => $mib * BK64_MIB, 'sha256' => '', 'destination' => 'local', 'scope' => 'installation', 'site_id' => 1, 'policy' => 'manual', 'backup_type' => 'full', 'remote' => array(), 'locked' => false ) ) );
	register_shutdown_function( function() { file_put_contents( (string) getenv( 'BK64_PEAK' ), (string) memory_get_peak_usage() ); bk_cleanup(); } );
	$_GET = array( 'action' => Digitalisimo_Backups::ACTION_DOWNLOAD, 'file' => $name );
	$_POST = array( '_wpnonce' => 'nonce-' . Digitalisimo_Backups::ACTION_DOWNLOAD );
	// Lo que imprime WordPress antes de la descarga (espacios de un plugin, avisos) queda en el búfer de salida.
	echo "\n";
	Digitalisimo_Backups::download();
	return;
}

/* ------------------------------------------------------------------ Casos en un proceso hijo con 64 MB */
$caso = '';
foreach ( array_slice( $argv, 1 ) as $arg ) if ( 0 === strpos( $arg, '--caso=' ) ) $caso = substr( $arg, 7 );

if ( 'h9' === $caso ) {
	define( 'ABSPATH', __DIR__ );
	define( 'SSH2_FINGERPRINT_SHA1', 1 );
	define( 'SSH2_FINGERPRINT_HEX', 2 );
	$root = sys_get_temp_dir() . '/digitalisimo-64m-sftp-' . bin2hex( random_bytes( 4 ) );
	mkdir( $root . '/remote/backups', 0700, true );
	mkdir( $root . '/private', 0700, true );
	$GLOBALS['bk64_remote'] = $root . '/remote';
	$GLOBALS['bk64_max_write'] = 0;
	$GLOBALS['bk64_short'] = false;
	function wp_basename( $path ) { return basename( $path ); }
	function ssh2_connect( $host, $port ) { return fopen( 'php://memory', 'r+' ); }
	function ssh2_fingerprint( $connection, $flags ) { return str_repeat( 'A', 40 ); }
	function ssh2_auth_password( $connection, $user, $password ) { return true; }
	function ssh2_sftp( $connection ) { return fopen( 'php://memory', 'r+' ); }
	function ssh2_sftp_rename( $sftp, $from, $to ) { return rename( $GLOBALS['bk64_remote'] . $from, $GLOBALS['bk64_remote'] . $to ); }
	function ssh2_sftp_unlink( $sftp, $path ) { return @unlink( $GLOBALS['bk64_remote'] . $path ); }
	function ssh2_sftp_stat( $sftp, $path ) { $stat = @stat( $GLOBALS['bk64_remote'] . $path ); return is_array( $stat ) ? array( 'size' => $stat['size'] ) : false; }
	/** La extensión ssh2 simulada: cada escritura llega entera a stream_write(), como al libssh2 real. */
	class Bk64_Sftp_Stream {
		public $context;
		private $handle;
		private $read = 0;
		public function stream_open( $path, $mode, $options, &$opened ) { $this->handle = fopen( $GLOBALS['bk64_remote'] . parse_url( $path, PHP_URL_PATH ), $mode ); return (bool) $this->handle; }
		public function stream_read( $count ) {
			// Una conexión que se corta: el servidor deja de enviar a mitad y la lectura termina sin error.
			if ( $GLOBALS['bk64_short'] && $this->read >= 3 * BK64_MIB ) return '';
			$data = fread( $this->handle, $count );
			$this->read += strlen( (string) $data );
			return $data;
		}
		public function stream_write( $data ) { $GLOBALS['bk64_max_write'] = max( $GLOBALS['bk64_max_write'], strlen( $data ) ); return fwrite( $this->handle, $data ); }
		public function stream_eof() { return ( $GLOBALS['bk64_short'] && $this->read >= 3 * BK64_MIB ) || feof( $this->handle ); }
		public function stream_flush() { return fflush( $this->handle ); }
		public function stream_stat() { return fstat( $this->handle ); }
		public function stream_close() { fclose( $this->handle ); }
		public function stream_set_option( $option, $arg1, $arg2 ) { return false; }
		public function url_stat( $path, $flags ) { return @stat( $GLOBALS['bk64_remote'] . parse_url( $path, PHP_URL_PATH ) ); }
	}
	stream_wrapper_register( 'ssh2.sftp', 'Bk64_Sftp_Stream' );
	require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';
	$mib = 80;
	$file = $root . '/private/digitalisimo-backup-grande-20261109-120000-0000000b.zip';
	bk64_write( $file, $mib );
	$expected = bk64_sha( $mib );
	$base = memory_get_usage();
	$rss = bk64_rss_peak();
	$ticks = 0;
	$context = array( 'temp_dir' => $root . '/private', 'connection' => 'sftp', 'progress' => function( $percent, $message ) use ( &$ticks ) { $ticks++; } );
	$settings = array( 'sftp_host' => 'sftp.example.test', 'sftp_port' => '22', 'sftp_user' => 'respaldos', 'sftp_password' => 'clave', 'sftp_path' => '/backups', 'sftp_fingerprint' => str_repeat( 'A', 40 ) );
	$sftp = new Digitalisimo_Backups_Destination_Sftp( $settings, $context );
	$receipt = $sftp->put( $file );
	// Por bloques: un latido (y la ocasión de cancelar) por cada MiB, no una sola copia del archivo entero.
	if ( $ticks < $mib / 2 ) throw new RuntimeException( 'La subida SFTP no fue por bloques: ' . $ticks . ' latidos con ' . $mib . ' MiB (se esperaba uno por MiB).' );
	$up_ticks = $ticks;
	$copy = $GLOBALS['bk64_remote'] . $receipt['path'];
	if ( ! is_file( $copy ) || $expected !== hash_file( 'sha256', $copy ) || $mib * BK64_MIB !== filesize( $copy ) ) throw new RuntimeException( 'SFTP no publicó la copia íntegra.' );
	if ( $GLOBALS['bk64_max_write'] > BK64_MIB ) throw new RuntimeException( 'SFTP escribió ' . $GLOBALS['bk64_max_write'] . ' bytes de una vez (más de 1 MiB).' );
	if ( glob( $GLOBALS['bk64_remote'] . '/backups/*.part-*' ) ) throw new RuntimeException( 'SFTP dejó un temporal.' );
	$up = memory_get_peak_usage() - $base;
	// Memoria del proceso (VmHWM): lo que memory_limit no ve y el límite duro del hosting sí.
	$rss_up = $rss ? bk64_rss_peak() - $rss : 0;
	if ( $rss_up > 32 * BK64_MIB ) throw new RuntimeException( 'La subida SFTP hizo crecer la memoria del proceso ' . round( $rss_up / BK64_MIB, 1 ) . ' MiB con un ZIP de ' . $mib . ' MiB (mapeado entero en memoria).' );
	$back = $root . '/private/vuelta.zip';
	$sftp->get( $receipt, $back );
	if ( $expected !== hash_file( 'sha256', $back ) ) throw new RuntimeException( 'get() SFTP no trajo la copia íntegra.' );
	$peak = memory_get_peak_usage() - $base;
	if ( $peak > 16 * BK64_MIB ) throw new RuntimeException( 'La transferencia SFTP usó ' . round( $peak / BK64_MIB, 1 ) . ' MiB de memoria con un ZIP de ' . $mib . ' MiB.' );
	// Una descarga que se corta a mitad sin error de lectura: el tamaño del servidor lo delata.
	$GLOBALS['bk64_short'] = true;
	try { $sftp->get( $receipt, $back ); $short = 'aceptada'; } catch ( Exception $e ) { $short = $e->getMessage(); }
	$GLOBALS['bk64_short'] = false;
	if ( false === strpos( $short, 'incompleta' ) ) throw new RuntimeException( 'get() SFTP aceptó una descarga cortada: ' . $short );
	// Cancelar a mitad de la subida (el latido lanza): se detiene, no deja el temporal y no publica.
	@unlink( $copy );
	$seen = 0;
	$cancel = new Digitalisimo_Backups_Destination_Sftp( $settings, array( 'progress' => function( $percent, $message ) use ( &$seen ) { if ( ++$seen >= 5 ) throw new Digitalisimo_Backups_Job_Cancelled( 'cancelado en la prueba' ); } ) + $context );
	try { $cancel->put( $file ); $cancelled = false; } catch ( Digitalisimo_Backups_Job_Cancelled $e ) { $cancelled = true; }
	if ( ! $cancelled || glob( $GLOBALS['bk64_remote'] . '/backups/*' ) ) throw new RuntimeException( 'Cancelar a mitad de la subida SFTP no la detuvo o dejó archivos: ' . json_encode( array_map( 'basename', (array) glob( $GLOBALS['bk64_remote'] . '/backups/*' ) ) ) );
	foreach ( array( $copy, $back, $file ) as $path ) @unlink( $path );
	foreach ( array( '/remote/backups', '/remote', '/private', '' ) as $dir ) @rmdir( $root . $dir );
	printf( "OK h9: %d MiB por SFTP (subida, verificación, publicación y descarga) con memory_limit=%s: %d latidos al subir, pico adicional %.1f MiB al subir y %.1f MiB en total (memoria del proceso: %s); escrituras de %d KiB como mucho; descarga cortada rechazada; cancelar a mitad no deja nada.\n", $mib, ini_get( 'memory_limit' ), $up_ticks, $up / BK64_MIB, $peak / BK64_MIB, $rss ? sprintf( '+%.1f MiB al subir', $rss_up / BK64_MIB ) : 'sin /proc', $GLOBALS['bk64_max_write'] / 1024 );
	exit( 0 );
}

if ( 'http' === $caso ) {
	define( 'ABSPATH', __DIR__ );
	function wp_basename( $path ) { return basename( $path ); }
	function is_wp_error( $value ) { return false; }
	// La API HTTP de WordPress arma la petición con el cuerpo: lo copia (Requests, sin cURL, lo concatena a las cabeceras).
	function wp_remote_request( $url, $args ) { $copy = 'PUT / HTTP/1.1' . "\r\n\r\n" . $args['body']; $GLOBALS['bk64_sent'] = strlen( $copy ); return array( 'response' => array( 'code' => 201 ), 'headers' => array(), 'body' => '' ); }
	function wp_remote_retrieve_headers( $response ) { return array(); }
	function wp_remote_retrieve_response_code( $response ) { return 201; }
	function wp_remote_retrieve_body( $response ) { return ''; }
	require __DIR__ . '/../digitalisimo-backups/includes/class-backups.php';
	$root = sys_get_temp_dir() . '/digitalisimo-64m-http-' . bin2hex( random_bytes( 4 ) );
	mkdir( $root, 0700, true );
	$file = $root . '/digitalisimo-backup-mediano.zip';
	bk64_write( $file, 30 );
	// Lo que ya ocupa una petición de WordPress con plugins.
	$ballast = str_repeat( 'w', 20 * BK64_MIB );
	$request = array( 'method' => 'PUT', 'url' => 'https://dav.example.test/r/x.zip', 'headers' => array(), 'body' => '', 'file' => $file, 'offset' => 0, 'length' => 30 * BK64_MIB, 'sink' => '', 'timeout' => 60, 'verify' => true, 'ca' => '', 'redirection' => 0, 'progress' => null );
	$method = new ReflectionMethod( 'Digitalisimo_Backups_Http', 'wp' );
	if ( PHP_VERSION_ID < 80100 ) $method->setAccessible( true );
	try { $method->invoke( null, $request ); $result = 'enviado'; } catch ( Digitalisimo_Backups_Permanent_Error $e ) { $result = $e->getMessage(); }
	@unlink( $file );
	@rmdir( $root );
	if ( false === strpos( $result, 'cURL' ) || false === strpos( $result, 'MB' ) ) throw new RuntimeException( 'Sin cURL, un archivo de 30 MiB con 64 MB y 20 MiB ocupados no se rechazó con un mensaje claro: ' . $result );
	$limit = Digitalisimo_Backups_Http::memory_upload_limit();
	if ( $limit <= 0 || $limit > 16 * BK64_MIB ) throw new RuntimeException( 'El tope de la subida en memoria no baja con la memoria libre: ' . $limit );
	printf( "OK http: sin cURL, 30 MiB con %s y %d MiB ocupados se rechazan antes de leerlos (tope %.1f MiB): «%s»\n", ini_get( 'memory_limit' ), strlen( $ballast ) / BK64_MIB, $limit / BK64_MIB, $result );
	exit( 0 );
}

/* ------------------------------------------------------------------ Proceso principal */
if ( ! function_exists( 'proc_open' ) || '' === PHP_BINARY || 'Unknown' === PHP_OS_FAMILY || 'Windows' === PHP_OS_FAMILY ) {
	echo "DIGITALÍSIMO Backups: memoria con 64 MB OMITIDA (requiere proc_open y un PHP del sistema).\n";
	exit( 0 );
}
$failures = array();
$lines = array();
/** Corre un caso en un proceso propio con 64 MB; devuelve su salida (la prueba falla si no termina bien). */
$child = function( $label ) use ( &$failures, &$lines ) {
	$process = proc_open( array( PHP_BINARY, '-d', 'memory_limit=64M', '-d', 'display_errors=stderr', __FILE__, '--caso=' . $label ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$code = proc_close( $process );
	if ( 0 !== $code || 0 !== strpos( $out, 'OK ' . $label ) ) {
		$failures[] = $label;
		echo 'FALLA (' . $label . '): código ' . $code . '; ' . trim( substr( $out . ' ' . $err, 0, 600 ) ) . "\n";
		return;
	}
	$lines[] = trim( $out );
	echo trim( $out ) . "\n";
};
$child( 'h9' );
$child( 'http' );

// (h16) «Descargar» por el servidor web de PHP con 64 MB y output_buffering.
$mib = 100;
$port = 0;
for ( $try = 0; $try < 20 && ! $port; $try++ ) {
	$candidate = random_int( 20000, 60000 );
	$probe = @stream_socket_server( 'tcp://127.0.0.1:' . $candidate );
	if ( $probe ) { fclose( $probe ); $port = $candidate; }
}
$peak_file = tempnam( sys_get_temp_dir(), 'bk64-peak-' );
$log = tempnam( sys_get_temp_dir(), 'bk64-server-' );
$env = array( 'BK64_MIB' => (string) $mib, 'BK64_PEAK' => $peak_file, 'TMPDIR' => sys_get_temp_dir(), 'PATH' => (string) getenv( 'PATH' ) );
$server = proc_open( array( PHP_BINARY, '-d', 'memory_limit=64M', '-d', 'output_buffering=On', '-d', 'display_errors=1', '-S', '127.0.0.1:' . $port, __FILE__ ), array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'file', $log, 'a' ), 2 => array( 'file', $log, 'a' ) ), $pipes, __DIR__, $env );
try {
	$socket = false;
	for ( $wait = 0; $wait < 100 && ! $socket; $wait++ ) { $socket = @stream_socket_client( 'tcp://127.0.0.1:' . $port, $errno, $error, 1 ); if ( ! $socket ) usleep( 100000 ); }
	if ( ! $socket ) throw new RuntimeException( 'El servidor web de PHP no arrancó: ' . file_get_contents( $log ) );
	stream_set_timeout( $socket, 120 );
	fwrite( $socket, "GET /wp-admin/admin-post.php HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n" );
	$head = '';
	while ( false === strpos( $head, "\r\n\r\n" ) && ! feof( $socket ) ) $head .= fread( $socket, 1 );
	$hash = hash_init( 'sha256' );
	$length = 0;
	$start = '';
	while ( ! feof( $socket ) ) {
		$chunk = fread( $socket, 1048576 );
		if ( false === $chunk || '' === $chunk ) { if ( feof( $socket ) ) break; continue; }
		if ( strlen( $start ) < 300 ) $start .= substr( $chunk, 0, 300 );
		hash_update( $hash, $chunk );
		$length += strlen( $chunk );
	}
	fclose( $socket );
	$size = $mib * BK64_MIB;
	$ok = 0 === strpos( $head, 'HTTP/1.0 200' ) || 0 === strpos( $head, 'HTTP/1.1 200' );
	$header = function( $name ) use ( $head ) { return preg_match( '/^' . preg_quote( $name, '/' ) . ':\s*(.+?)\r?$/mi', $head, $m ) ? trim( $m[1] ) : ''; };
	$problems = array();
	if ( ! $ok ) $problems[] = 'estado ' . strtok( $head, "\r\n" );
	if ( (string) $size !== $header( 'Content-Length' ) ) $problems[] = 'Content-Length «' . $header( 'Content-Length' ) . '»';
	if ( 'application/zip' !== $header( 'Content-Type' ) || false === strpos( $header( 'Content-Disposition' ), 'attachment' ) ) $problems[] = 'tipo o disposición «' . $header( 'Content-Type' ) . ' / ' . $header( 'Content-Disposition' ) . '»';
	if ( $length !== $size || bk64_sha( $mib ) !== hash_final( $hash ) ) $problems[] = 'cuerpo de ' . $length . ' bytes que no es el respaldo (empieza por ' . json_encode( substr( $start, 0, 160 ) ) . ')';
	$peak = (int) @file_get_contents( $peak_file );
	if ( $peak > 24 * BK64_MIB ) $problems[] = 'pico de memoria de ' . round( $peak / BK64_MIB, 1 ) . ' MiB';
	if ( $problems ) {
		$failures[] = 'h16';
		echo 'FALLA (h16): «Descargar» de un respaldo de ' . $mib . ' MiB con 64 MB y output_buffering=On: ' . implode( '; ', $problems ) . '. Registro del servidor: ' . trim( substr( (string) file_get_contents( $log ), -600 ) ) . "\n";
	} else {
		$lines[] = 'h16';
		printf( "OK h16: «Descargar» de %d MiB por el servidor web de PHP con memory_limit=64M y output_buffering=On: 200, Content-Length exacto, SHA-256 del respaldo y pico de %.1f MiB.\n", $mib, $peak / BK64_MIB );
	}
} catch ( Throwable $e ) {
	$failures[] = 'h16';
	echo 'FALLA (h16): ' . $e->getMessage() . "\n";
} finally {
	proc_terminate( $server );
	proc_close( $server );
	@unlink( $peak_file );
	@unlink( $log );
}

if ( $failures ) throw new RuntimeException( 'Memoria con 64 MB: fallaron ' . implode( ', ', $failures ) . '.' );
echo "DIGITALÍSIMO Backups: SFTP, «Descargar» y la subida sin cURL caben en 64 MB con un respaldo mayor que la memoria.\n";
