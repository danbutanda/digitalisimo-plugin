<?php
/**
 * Revisión de R1 · C2 y C3: get_range() del contrato por HTTP real (cURL) contra un servidor `php -S`
 * (tests/lib/remote-net-server.php), como un destino compartido hostil o lento.
 *   C3  desde el byte 0, un servidor que ignora `Range` y envía 20 MiB no escribe en disco más de lo pedido o del total
 *       que listó el destino: se corta y se rechaza (también a mitad);
 *   C2  una petición de fragmento espera como mucho lo que le queda al tramo (`timeout`), nunca 120 s fijos;
 *   y un servidor correcto sigue descargando por rangos exactos.
 */
$root = sys_get_temp_dir() . '/digitalisimo-remote-net-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
require __DIR__ . '/lib/destination-fakes.php';
$server = null;
try {
	if ( ! Digitalisimo_Backups_Http::curl_available() ) { echo "OMITIDA: este PHP no tiene cURL.\n"; return; }
	// Servidor propio en un puerto libre; se cierra al terminar.
	$probe = stream_socket_server( 'tcp://127.0.0.1:0', $errno, $error );
	$port = (int) substr( strrchr( stream_socket_get_name( $probe, false ), ':' ), 1 );
	fclose( $probe );
	// Un solo proceso (sin PHP_CLI_SERVER_WORKERS: sus trabajadores sobreviven a quien mata al principal) y con un tope de
	// vida: aunque esta prueba muera de golpe (SIGKILL, el tiempo de la corrida), el servidor se cierra solo a los 90 s.
	$command = array( PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/lib/remote-net-server.php' );
	foreach ( array( '/usr/bin/timeout', '/bin/timeout' ) as $timeout ) if ( is_executable( $timeout ) ) { $command = array_merge( array( $timeout, '-s', 'KILL', '90' ), $command ); break; }
	$server = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $root . '/server.log', 'w' ), 2 => array( 'file', $root . '/server.log', 'a' ) ), $pipes );
	// También ante un error fatal de esta prueba (el finally no corre): se cierra al terminar el proceso. SIGTERM, que
	// `timeout` pasa al servidor (un SIGKILL lo mataría a él y dejaría huérfano a php -S).
	register_shutdown_function( function() use ( &$server ) { if ( is_resource( $server ) ) { proc_terminate( $server ); @proc_close( $server ); } } );
	for ( $i = 0; $i < 50 && ! @fsockopen( '127.0.0.1', $port, $errno, $error, 0.2 ); $i++ ) usleep( 100000 );
	$dav = new Digitalisimo_Backups_Destination_Webdav( array( 'webdav_url' => 'http://127.0.0.1:' . $port . '/', 'webdav_user' => 'ana', 'webdav_password' => 'x', 'webdav_path' => '/', 'webdav_allow_http' => '1' ), array( 'connection' => 'webdav' ) );
	$piece = $root . '/piece.tmp';
	$size = function() use ( $piece ) { clearstatcache(); return is_file( $piece ) ? filesize( $piece ) : 0; };

	// C3 desde 0: se piden 64 KiB de un respaldo de 70 000 bytes; el servidor manda 20 MiB con un 200.
	$error = '';
	try { $dav->get_range( array( 'provider' => 'webdav', 'path' => 'overflow/x.zip' ), $piece, 0, 65536, array( 'total' => 70000, 'timeout' => 30 ) ); } catch ( Exception $e ) { $error = get_class( $e ) . ': ' . $e->getMessage(); }
	digi_assert( '' !== $error && $size() <= 70001, 'Desde el byte 0 se escribieron ' . $size() . ' bytes de un servidor que envía de más: ' . $error );
	// Sin el total, el tope es lo pedido.
	@unlink( $piece );
	$error = '';
	try { $dav->get_range( array( 'provider' => 'webdav', 'path' => 'overflow/x.zip' ), $piece, 0, 65536, array( 'timeout' => 30 ) ); } catch ( Exception $e ) { $error = $e->getMessage(); }
	digi_assert( '' !== $error && $size() <= 65537, 'Sin total, desde 0 se escribieron ' . $size() . ' bytes: ' . $error );
	// C3 a mitad.
	@unlink( $piece );
	$error = '';
	try { $dav->get_range( array( 'provider' => 'webdav', 'path' => 'overflow/x.zip' ), $piece, 65536, 65536, array( 'total' => 999999999, 'timeout' => 30 ) ); } catch ( Exception $e ) { $error = $e->getMessage(); }
	digi_assert( '' !== $error && $size() <= 65537, 'A mitad se escribieron ' . $size() . ' bytes de más: ' . $error );

	// Un servidor correcto: fragmentos exactos hasta completar el archivo.
	$out = '';
	for ( $offset = 0; $offset < 300000; ) {
		$got = $dav->get_range( array( 'provider' => 'webdav', 'path' => 'ok/x.zip' ), $piece, $offset, 65536, array( 'total' => 300000, 'timeout' => 30 ) );
		$out .= file_get_contents( $piece );
		$offset += $got;
	}
	digi_assert( str_repeat( 'DIGITALISIMO', 25000 ) === $out, 'La descarga por rangos de un servidor correcto no es el archivo.' );
	// C2: el servidor tarda 15 s; al tramo le quedan 5.
	@unlink( $piece );
	$started = microtime( true );
	$error = '';
	try { $dav->get_range( array( 'provider' => 'webdav', 'path' => 'slow/x.zip' ), $piece, 0, 10, array( 'total' => 1000, 'timeout' => 5 ) ); } catch ( Exception $e ) { $error = $e->getMessage(); }
	$took = microtime( true ) - $started;
	digi_assert( '' !== $error && $took < 10, 'Un fragmento esperó ' . round( $took, 1 ) . ' s con 5 s de tramo: ' . $error );

	// C3 en FTP (sin rangos: el archivo entero desde 0): con 70 000 bytes listados, un servidor que manda 20 MiB se corta
	// en cuanto pasa del total y no deja nada en disco.
	Bk_Fake_Ftp::install();
	Bk_Fake_Ftp::reset( 'explicit' );
	Bk_Fake_Ftp::$dirs['/respaldos'] = true;
	Bk_Fake_Ftp::$files['/respaldos/x.zip'] = str_repeat( "\0", 20 << 20 );
	$ftp = new Digitalisimo_Backups_Destination_Ftp( array( 'ftp_host' => 'ftp.ejemplo.test', 'ftp_port' => '21', 'ftp_security' => 'explicit', 'ftp_user' => 'respaldos', 'ftp_password' => 'Ftp-Clave-1', 'ftp_path' => '/respaldos', 'ftp_passive' => 'passive' ), array( 'connection' => 'ftp' ) );
	@unlink( $piece );
	Bk_Fake_Ftp::$served = 0;
	$error = '';
	try { $ftp->get_range( array( 'provider' => 'ftp', 'path' => '/respaldos/x.zip' ), $piece, 0, 20 << 20, array( 'total' => 70000, 'timeout' => 30 ) ); } catch ( Exception $e ) { $error = get_class( $e ) . ': ' . $e->getMessage(); }
	digi_assert( '' !== $error && ! is_file( $piece ) && Bk_Fake_Ftp::$served < 1048576, 'FTP escribió ' . $size() . ' bytes (y leyó ' . Bk_Fake_Ftp::$served . ') de un servidor que envía de más: ' . $error );
	// Un archivo que sí mide lo listado llega entero.
	Bk_Fake_Ftp::$files['/respaldos/x.zip'] = str_repeat( 'F', 70000 );
	digi_assert( 70000 === $ftp->get_range( array( 'provider' => 'ftp', 'path' => '/respaldos/x.zip' ), $piece, 0, 70000, array( 'total' => 70000 ) ) && str_repeat( 'F', 70000 ) === file_get_contents( $piece ), 'FTP no trajo entero un archivo del tamaño listado.' );
	echo "OK: fragmentos por HTTP real y FTP con tope de bytes y tiempo del tramo (C2, C3)\n";
} finally {
	if ( is_resource( $server ) ) { proc_terminate( $server ); @proc_close( $server ); }
	digi_rmtree( $root );
}
