<?php
/**
 * Router de `php -S` para backups-remote-net.php: servidores WebDAV hostiles o lentos para get_range() por cURL real.
 *   /overflow/*  ignora el rango: 200 desde el byte 0 (o 206 con un Content-Range «correcto» a mitad) y siempre 20 MiB.
 *   /slow/*      tarda 15 s y después responde un 206 válido de 10 bytes.
 *   /ok/*        un archivo de 300 000 bytes con rangos de verdad (206 exactos).
 */
$path = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$range = (string) ( $_SERVER['HTTP_RANGE'] ?? '' );
preg_match( '/bytes=(\d+)-(\d+)/', $range, $m );
$start = (int) ( $m[1] ?? 0 );
$end = (int) ( $m[2] ?? 0 );
if ( 0 === strpos( $path, '/overflow/' ) ) {
	if ( 0 === $start ) http_response_code( 200 );
	else { http_response_code( 206 ); header( 'Content-Range: bytes ' . $start . '-' . $end . '/999999999' ); }
	header( 'Content-Length: ' . ( 20 << 20 ) );
	$chunk = str_repeat( "\0", 1 << 20 );
	for ( $i = 0; $i < 20; $i++ ) { echo $chunk; flush(); }
	exit;
}
if ( 0 === strpos( $path, '/slow/' ) ) {
	sleep( 15 );
	http_response_code( 206 );
	header( 'Content-Range: bytes ' . $start . '-' . ( $start + 9 ) . '/1000' );
	header( 'Content-Length: 10' );
	echo '0123456789';
	exit;
}
if ( 0 === strpos( $path, '/ok/' ) ) {
	$data = str_repeat( 'DIGITALISIMO', 25000 );
	$end = min( strlen( $data ) - 1, $end );
	http_response_code( 206 );
	header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . strlen( $data ) );
	header( 'Content-Length: ' . ( $end - $start + 1 ) );
	echo substr( $data, $start, $end - $start + 1 );
	exit;
}
http_response_code( 404 );
