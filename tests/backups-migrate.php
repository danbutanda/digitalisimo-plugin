<?php
/**
 * Migración en un clic entre servidores y dominios (tirar desde el destino), con el motor real de los dos lados en un
 * mismo proceso (ver tests/backups-migrate/env.php): «origen» (https://origen.test, prefijo wp_) y «destino»
 * (https://destino.test, prefijo dst_), cada uno con su carpeta, su base de datos simulada, sus opciones y su almacén.
 *
 *   (a) la clave: se muestra una sola vez; la base de datos guarda la huella de su credencial y nunca la clave; la
 *       clave del paquete vive envuelta en el almacén; nada de eso entra en el SQL de un respaldo;
 *   (b) autenticación: sin credencial o con otra, 401; la clave en la URL, 400; 5 fallos bloquean la clave; muchos
 *       fallos de cualquier origen cierran la API (429);
 *   (c) caducidad y (d) revocación: 410 y la clave del paquete y el paquete se borran;
 *   (e) una clave atada a una migración no sirve a otra (409);
 *   (f) migración completa por la pantalla de REST: el origen crea el paquete con su motor de trabajos, el destino lo
 *       descarga por fragmentos con Range y se reanuda tras un corte de red, un fragmento truncado, una muerte del
 *       proceso y un byte alterado en el camino (SHA-256 distinto: se descarga otra vez); restaura con la reescritura
 *       de E1 (direcciones del destino, prefijo dst_, serializados, guid intacto) y la copia previa marcada; el origen
 *       no cambia y su clave queda usada (un solo uso);
 *   (g) un paquete alterado en el origen (otra cabecera o el cifrado cambiado, con su SHA-256 recalculado) se rechaza
 *       antes de tocar el destino;
 *   (h) «Deshacer la migración» devuelve el destino a su estado anterior y no se puede repetir;
 *   (i) red ↔ sitio individual y red de otro tipo se rechazan antes de descargar, diciendo qué cambiar en wp-config.php;
 *       una clave http sin «Permitir HTTP» también;
 *   (j) ningún secreto (clave, credencial ni clave del paquete) en URL, respuestas, opciones, trabajos ni registros.
 * Cada caso escribe «FALLA (x)» si falla.
 */
require __DIR__ . '/backups-migrate/env.php';

$big = random_bytes( 300 * 1024 ); // incompresible: el paquete necesita varios fragmentos de 64 KiB
mig_site( 'origen', 'origen.test', 'wp_', array(
	'wp-content/uploads/2026/11/foto-origen.jpg' => $big,
	'wp-content/uploads/elementor/css/post-7.css' => '.e{background:url(https://origen.test/wp-content/uploads/2026/11/foto-origen.jpg)}',
	'wp-content/plugins/solo-origen.php' => "<?php /* Plugin Name: Del origen */\n",
) );
mig_site( 'destino', 'destino.test', 'dst_', array( 'wp-content/uploads/2026/11/solo-destino.txt' => 'del destino' ) );
$GLOBALS['mig']['sites']['origen']['options'] += array(
	'widget_text' => array( 2 => array( 'text' => '<a href="https://origen.test/contacto">Contacto · ñ</a>', 'url' => 'https://origen.test' ) ),
	'elementor_json' => '{"url":"https:\/\/origen.test\/wp-content\/uploads\/2026\/11\/foto-origen.jpg"}',
	'admin_email' => 'yo@origen.test',
);
$GLOBALS['mig']['sites']['origen']['db']->posts = array( 7 => array( 'ID' => '7', 'post_content' => '<img src="https://origen.test/wp-content/uploads/2026/11/foto-origen.jpg"> origen.test.mx', 'guid' => 'https://origen.test/?p=7' ) );
$GLOBALS['mig']['sites']['destino']['db']->posts = array( 3 => array( 'ID' => '3', 'post_content' => 'Página del destino', 'guid' => 'https://destino.test/?p=3' ) );

$failures = array();
function mig_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok   ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures'][] = $label;
		echo 'FALLA (' . $label . '): ' . $e->getMessage() . "\n";
		if ( getenv( 'MIG_TRACE' ) ) echo $e->getTraceAsString() . "\n";
	}
}
$NS = 'digitalisimo-backups/v1';
$M = 'Digitalisimo_Backups_Migrate';
$B = 'Digitalisimo_Backups';
/** Las piezas de una clave (sólo la prueba las conoce): secreto, credencial, clave del paquete y sus codificaciones. */
function mig_parts( $key ) {
	$json = json_decode( Digitalisimo_Backups_Keys::b64url_decode( substr( $key, 7 ) ), true );
	$s = Digitalisimo_Backups_Keys::b64url_decode( $json['s'], 32 );
	$a = sodium_crypto_generichash( 'DGBK1/migrate-auth', $s, 32 );
	$k = sodium_crypto_generichash( 'DGBK1/migrate-root', $s, 32 );
	return array( 'id' => $json['i'], 'url' => $json['u'], 's' => $s, 'a' => $a, 'k' => $k, 'header' => $json['i'] . '.' . Digitalisimo_Backups_Keys::b64url_encode( $a ), 'needles' => array( $key, $json['s'], Digitalisimo_Backups_Keys::b64url_encode( $s ), base64_encode( $s ), bin2hex( $s ), Digitalisimo_Backups_Keys::b64url_encode( $a ), base64_encode( $a ), bin2hex( $a ), Digitalisimo_Backups_Keys::b64url_encode( $k ), base64_encode( $k ), bin2hex( $k ) ) );
}
function mig_source( $action, $header, $client = '', array $body = array(), array $query = array(), $range = '' ) {
	$headers = array();
	if ( null !== $header ) $headers['X-Digitalisimo-Migration'] = $header;
	if ( '' !== $client ) $headers['X-Digitalisimo-Migration-Client'] = $client;
	if ( '' !== $range ) $headers['Range'] = $range;
	return mig_in( 'origen', function() use ( $action, $headers, $body, $query ) {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
		return mig_rest( 'download' === $action ? 'GET' : 'POST', 'digitalisimo-backups/v1/migrate/source/' . $action, $body, $headers, $query, false );
	} );
}
function mig_key( $hours = 24 ) {
	return mig_in( 'origen', function() use ( $hours ) {
		$reply = mig_rest( 'POST', 'digitalisimo-backups/v1/migration/keys', array( 'hours' => $hours, 'network' => is_multisite() ) );
		mig_assert( 201 === $reply[0], 'No se generó la clave: ' . json_encode( $reply[1] ) );
		return $reply[1];
	} );
}
function mig_state() { return mig_in( 'origen', function() { return get_option( 'digitalisimo_backups_vault_migration' ); } ); }
function mig_record( $id ) { return mig_state()['keys'][ $id ] ?? null; }

// Lo que debe fallar con el código anterior: no hay API de migración.
mig_use( 'origen' );
$first = mig_rest( 'POST', $NS . '/migration/keys', array( 'hours' => 24 ) );
if ( 201 !== $first[0] ) {
	echo 'FALLA (a): no hay forma de generar una clave de migración en el origen (POST /migration/keys respondió ' . $first[0] . ' ' . json_encode( $first[1] ) . ").\n";
	exit( 1 );
}
$all_needles = array();

mig_case( 'a · la clave se muestra una vez y sólo su huella queda en la base de datos', function() use ( $first, &$all_needles, $NS, $M, $B ) {
	$key = (string) $first[1]['key'];
	mig_assert( 0 === strpos( $key, 'DGMIG1.' ) && preg_match( '/^[0-9a-f]{16}$/', $first[1]['id'] ) && 'origen.test' === $first[1]['source'], 'La respuesta de la clave no es la esperada: ' . json_encode( $first[1] ) );
	$p = mig_parts( $key );
	$all_needles = array_merge( $all_needles, $p['needles'] );
	mig_assert( 'https://origen.test/index.php?rest_route=/digitalisimo-backups/v1/migrate/source' === $p['url'], 'La clave no lleva la API del origen: ' . $p['url'] );
	$record = mig_record( $p['id'] );
	mig_assert( $record && hash_equals( $record['verifier'], bin2hex( sodium_crypto_generichash( 'DGBK1/migrate-verifier', $p['a'], 32 ) ) ) && Digitalisimo_Backups_Keys::key_id( $p['k'] ) === $record['key_id'] && 'pending' === $record['state'], 'La base de datos no guarda la huella esperada.' );
	$stored = serialize( mig_in( 'origen', function() { return $GLOBALS['bk_options'] + $GLOBALS['bk_site_options']; } ) );
	foreach ( $p['needles'] as $needle ) mig_assert( false === strpos( $stored, $needle ), 'Las opciones del origen guardan la clave, la credencial o la clave del paquete.' );
	// La clave del paquete, envuelta en el almacén (0600) y nunca en claro.
	mig_in( 'origen', function() use ( $p ) {
		$file = mig_storage() . '/migrations/' . $p['id'] . '.key';
		mig_assert( is_file( $file ) && 0600 === ( fileperms( $file ) & 0777 ), 'Falta la clave del paquete envuelta en el almacén, o no es 0600.' );
		$raw = file_get_contents( $file );
		foreach ( $p['needles'] as $needle ) mig_assert( false === strpos( $raw, $needle ), 'La clave del paquete está en claro en el almacén.' );
		// El SQL de un respaldo no lleva el estado de la migración.
		$sql = mig_storage() . '/prueba.sql';
		Digitalisimo_Backups::job_export_database( $sql );
		$dump = file_get_contents( $sql );
		@unlink( $sql );
		mig_assert( false !== strpos( $dump, 'blogname' ) && false === strpos( $dump, 'digitalisimo_backups_vault_migration' ), 'El estado de la migración entró en el SQL de un respaldo.' );
	} );
	// El estado y la lista de claves no repiten la clave.
	$status = mig_in( 'origen', function() use ( $NS ) { return mig_rest( 'GET', $NS . '/migration' ); } );
	mig_assert( 200 === $status[0] && 1 === count( $status[1]['keys'] ) && 'pending' === $status[1]['keys'][0]['state'] && 'created' === $status[1]['log'][0]['event'], 'El estado de la migración no muestra la clave nueva: ' . json_encode( $status[1] ) );
	foreach ( $p['needles'] as $needle ) mig_assert( false === strpos( json_encode( $status[1] ), $needle ), 'El estado devuelve la clave.' );
	// Sin la capability de la pantalla, nada.
	mig_in( 'origen', function() use ( $NS ) {
		$GLOBALS['bk_caps'] = array( 'edit_posts' => true );
		try {
			foreach ( array( array( 'GET', '/migration', array() ), array( 'POST', '/migration/keys', array() ), array( 'POST', '/migration/pull', array( 'confirm' => 'MIGRAR' ) ), array( 'POST', '/migration/undo', array( 'confirm' => 'DESHACER' ) ) ) as $call ) {
				$reply = mig_rest( $call[0], $NS . $call[1], $call[2] );
				mig_assert( 403 === $reply[0], 'Un usuario sin permiso usó ' . $call[1] . ' (' . $reply[0] . ').' );
			}
		} finally {
			$GLOBALS['bk_caps'] = array( 'manage_options' => true, 'manage_network_options' => true );
		}
	} );
} );

mig_case( 'b · credencial ausente, equivocada, en la URL, bloqueo por clave y límite global', function() use ( &$all_needles ) {
	$second = mig_key();
	$p = mig_parts( $second['key'] );
	$all_needles = array_merge( $all_needles, $p['needles'] );
	$reply = mig_source( 'hello', null );
	mig_assert( 401 === $reply[0], 'Sin credencial no respondió 401: ' . $reply[0] );
	$reply = mig_source( 'hello', $p['header'], '', array(), array( 'key' => 'x' ) );
	mig_assert( 400 === $reply[0] && 'digitalisimo_backups_migration_in_url' === $reply[1]['code'], 'Una clave en la URL no se rechazó: ' . json_encode( $reply ) );
	$reply = mig_source( 'hello', $p['header'] );
	mig_assert( 200 === $reply[0] && 'https://origen.test/' === $reply[1]['home'] && false === $reply[1]['multisite'] && true === $reply[1]['ready'] && 'wp_' === $reply[1]['table_prefix'], 'La presentación del origen no es la esperada: ' . json_encode( $reply[1] ) );
	$wrong = $p['id'] . '.' . Digitalisimo_Backups_Keys::b64url_encode( random_bytes( 32 ) );
	for ( $i = 1; $i <= Digitalisimo_Backups_Migrate::MAX_FAILURES; $i++ ) {
		$reply = mig_source( 'hello', $wrong );
		mig_assert( 401 === $reply[0], 'Una credencial equivocada no respondió 401 (intento ' . $i . ').' );
	}
	mig_assert( 'blocked' === mig_record( $p['id'] )['state'], 'La clave no se bloqueó tras ' . Digitalisimo_Backups_Migrate::MAX_FAILURES . ' fallos.' );
	$reply = mig_source( 'hello', $p['header'] );
	mig_assert( 410 === $reply[0] && false !== strpos( $reply[1]['message'], 'bloqueó' ), 'Una clave bloqueada siguió sirviendo: ' . json_encode( $reply ) );
	mig_in( 'origen', function() use ( $p ) { mig_assert( ! is_file( mig_storage() . '/migrations/' . $p['id'] . '.key' ), 'La clave del paquete de una clave bloqueada sigue en el almacén.' ); } );
	// Límite global: con muchos fallos de cualquier origen la API se cierra, también para una clave válida.
	$third = mig_key();
	$q = mig_parts( $third['key'] );
	$all_needles = array_merge( $all_needles, $q['needles'] );
	for ( $i = 0; $i < Digitalisimo_Backups_Migrate::GLOBAL_FAILURES; $i++ ) mig_source( 'hello', bin2hex( random_bytes( 8 ) ) . '.' . Digitalisimo_Backups_Keys::b64url_encode( random_bytes( 32 ) ) );
	$reply = mig_source( 'hello', $q['header'] );
	mig_assert( 429 === $reply[0], 'Tras ' . Digitalisimo_Backups_Migrate::GLOBAL_FAILURES . ' fallos la API siguió abierta: ' . $reply[0] );
	// Pasada la ventana, vuelve a abrirse.
	mig_in( 'origen', function() {
		$state = get_option( 'digitalisimo_backups_vault_migration' );
		$state['fails'] = array_map( function( $at ) { return $at - Digitalisimo_Backups_Migrate::WINDOW - 1; }, $state['fails'] );
		update_option( 'digitalisimo_backups_vault_migration', $state );
	} );
	$reply = mig_source( 'hello', $q['header'] );
	mig_assert( 200 === $reply[0], 'Pasada la ventana, la API no volvió a abrirse: ' . $reply[0] );
	$log = array_column( mig_state()['log'], 'event' );
	mig_assert( in_array( 'auth_failed', $log, true ) && in_array( 'blocked', $log, true ) && in_array( 'hello', $log, true ), 'El registro no anota los usos y los fallos.' );
	$GLOBALS['mig_third'] = $third;
} );

mig_case( 'c · una clave caducada responde 410 y se retira', function() use ( &$all_needles ) {
	$key = mig_key( 1 );
	$p = mig_parts( $key['key'] );
	$all_needles = array_merge( $all_needles, $p['needles'] );
	mig_in( 'origen', function() use ( $p ) {
		$state = get_option( 'digitalisimo_backups_vault_migration' );
		$state['keys'][ $p['id'] ]['expires'] = time() - 1;
		update_option( 'digitalisimo_backups_vault_migration', $state );
	} );
	$reply = mig_source( 'hello', $p['header'] );
	mig_assert( 410 === $reply[0] && false !== strpos( $reply[1]['message'], 'caducó' ), 'Una clave caducada siguió sirviendo: ' . json_encode( $reply ) );
	mig_assert( 'expired' === mig_record( $p['id'] )['state'], 'La clave caducada no quedó como caducada.' );
	mig_in( 'origen', function() use ( $p ) { mig_assert( ! is_file( mig_storage() . '/migrations/' . $p['id'] . '.key' ), 'La clave del paquete de una clave caducada sigue en el almacén.' ); } );
} );

mig_case( 'd · revocar: 410 y sin paquete', function() use ( &$all_needles, $NS ) {
	$key = mig_key();
	$p = mig_parts( $key['key'] );
	$all_needles = array_merge( $all_needles, $p['needles'] );
	$client = Digitalisimo_Backups_Job_Store::new_id();
	$reply = mig_source( 'package', $p['header'], $client, array( 'site' => 'https://destino.test/' ) );
	mig_assert( 200 === $reply[0] && in_array( $reply[1]['state'], array( 'queued', 'running' ), true ), 'El origen no encoló el paquete: ' . json_encode( $reply ) );
	$job = mig_record( $p['id'] )['job'];
	$revoke = mig_in( 'origen', function() use ( $NS, $p ) { return mig_rest( 'POST', $NS . '/migration/keys/' . $p['id'] . '/revoke' ); } );
	mig_assert( 200 === $revoke[0] && 'revoked' === $revoke[1]['state'], 'No se revocó la clave: ' . json_encode( $revoke ) );
	$reply = mig_source( 'step', $p['header'], $client );
	mig_assert( 410 === $reply[0] && false !== strpos( $reply[1]['message'], 'revocada' ), 'Una clave revocada siguió sirviendo.' );
	mig_in( 'origen', function() use ( $job, $p ) {
		$state = Digitalisimo_Backups_Jobs::get( $job );
		mig_assert( 'cancelled' === $state['status'], 'El paquete de una clave revocada no se canceló: ' . $state['status'] );
		mig_assert( ! is_file( mig_storage() . '/migrations/' . $p['id'] . '.key' ), 'La clave del paquete de una clave revocada sigue en el almacén.' );
	} );
} );

mig_case( 'e · una clave atada a una migración no sirve a otra', function() use ( &$all_needles ) {
	$key = mig_key();
	$p = mig_parts( $key['key'] );
	$all_needles = array_merge( $all_needles, $p['needles'] );
	$one = Digitalisimo_Backups_Job_Store::new_id();
	$two = Digitalisimo_Backups_Job_Store::new_id();
	mig_assert( 200 === mig_source( 'package', $p['header'], $one )[0], 'La primera migración no pudo pedir el paquete.' );
	$reply = mig_source( 'step', $p['header'], $two );
	mig_assert( 409 === $reply[0] && false !== strpos( $reply[1]['message'], 'Otra migración' ), 'Otra migración usó una clave atada: ' . json_encode( $reply ) );
	$reply = mig_source( 'download', $p['header'], $two, array(), array(), 'bytes=0-10' );
	mig_assert( 409 === $reply[0], 'Otra migración descargó con una clave atada.' );
	// El destino que abandona la libera: vuelve a servir.
	mig_assert( 200 === mig_source( 'release', $p['header'], $one )[0] && 'pending' === mig_record( $p['id'] )['state'], 'La clave no se liberó.' );
	mig_assert( 200 === mig_source( 'package', $p['header'], $two )[0], 'Una clave liberada no sirvió a otra migración.' );
	mig_source( 'release', $p['header'], $two );
	mig_in( 'origen', function() use ( $p ) { Digitalisimo_Backups_Migrate::revoke( $p['id'] ); } );
} );

// Estado antes de migrar: el origen no debe cambiar y el destino debe volver a esto al deshacer.
$origin_before = mig_in( 'origen', function() { return array( 'options' => array_diff_key( $GLOBALS['bk_options'], array( 'digitalisimo_backups_vault_migration' => 1, 'digitalisimo_backups_history' => 1, 'digitalisimo_backups_private_storage' => 1 ) ), 'posts' => $GLOBALS['wpdb']->posts, 'files' => mig_tree( $GLOBALS['mig']['sites']['origen']['real'] ) ); } );
$destination_before = mig_in( 'destino', function() { return array( 'blogname' => get_option( 'blogname' ), 'home' => get_option( 'home' ), 'posts' => $GLOBALS['wpdb']->posts ); } );
$GLOBALS['bk_filters']['digitalisimo_backups_migration_chunk'] = 65536;
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$GLOBALS['bk_filters']['digitalisimo_backups_migration_poll'] = 0;
$migrated_job = '';

mig_case( 'f · migración en un clic con cortes, reanudación y un byte alterado en el camino', function() use ( &$all_needles, &$migrated_job, $NS, $origin_before ) {
	$key = mig_key();
	$p = mig_parts( $key['key'] );
	$all_needles = array_merge( $all_needles, $p['needles'] );
	// Fallos en el camino: un corte de red en la 2.ª descarga, un fragmento truncado en la 3.ª, la muerte del proceso en
	// la 5.ª y un byte cambiado en la 4.ª (sólo lo detecta el SHA-256 del paquete completo).
	$count = 0;
	$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) use ( &$count ) {
		if ( false === strpos( $route, '/migrate/source/download' ) ) return null;
		if ( null === $response ) {
			$count++;
			if ( 2 === $count ) return new WP_Error( 'http_request_failed', 'cURL error 56: Recv failure: Connection reset by peer' );
			if ( 5 === $count ) throw new Error( 'El proceso murió a mitad de la descarga.' );
			return null;
		}
		if ( 3 === $count ) { $response['body'] = substr( $response['body'], 0, 1000 ); return $response; }
		if ( 4 === $count ) { $response['body'][100] = chr( ord( $response['body'][100] ) ^ 1 ); return $response; }
		return null;
	} );
	mig_use( 'destino' );
	$reply = mig_rest( 'POST', $NS . '/migration/pull', array( 'key' => $key['key'], 'confirm' => 'MIGRAR' ) );
	mig_assert( 202 === $reply[0] && 'origen.test' === $reply[1]['source'] && 'migration' === $reply[1]['kind'], 'El destino no encoló la migración: ' . json_encode( $reply ) );
	$migrated_job = $reply[1]['id'];
	$job = mig_drive( $migrated_job );
	$GLOBALS['mig']['faults'] = array();
	mig_assert( $job && 'success' === $job['status'], 'La migración no terminó: ' . json_encode( array( $job['status'] ?? '', $job['phase'] ?? '', $job['error'] ?? null, $job['progress'] ?? null ) ) );
	// Las descargas: cada una pide desde lo que ya hay en disco; tras el SHA-256 distinto se empieza otra vez.
	$ranges = array();
	foreach ( $GLOBALS['mig']['requests'] as $request ) if ( false !== strpos( $request['route'], '/download' ) ) $ranges[] = (string) $request['args']['headers']['Range'];
	mig_assert( count( $ranges ) >= 9 && 'bytes=0-65535' === $ranges[0] && 'bytes=65536-131071' === $ranges[1] && $ranges[1] === $ranges[2] && $ranges[2] === $ranges[3], 'La descarga no se reanudó desde lo descargado: ' . json_encode( $ranges ) );
	mig_assert( count( array_keys( $ranges, 'bytes=0-65535', true ) ) >= 2, 'Tras el SHA-256 distinto no se descargó otra vez desde el principio: ' . json_encode( $ranges ) );
	// El destino es ahora el origen con su propia dirección (reescritura de E1).
	mig_assert( 'Origen' === get_option( 'blogname' ) && 'https://destino.test' === rtrim( (string) get_option( 'home' ), '/' ) && 'https://destino.test' === rtrim( (string) get_option( 'siteurl' ), '/' ), 'El destino no quedó como el origen con su dirección: ' . json_encode( array( get_option( 'blogname' ), get_option( 'home' ), get_option( 'siteurl' ) ) ) );
	$widget = get_option( 'widget_text' );
	mig_assert( is_array( $widget ) && '<a href="https://destino.test/contacto">Contacto · ñ</a>' === $widget[2]['text'] && 'https://destino.test' === $widget[2]['url'], 'El serializado no se reescribió con sus longitudes: ' . json_encode( $widget ) );
	mig_assert( '{"url":"https:\/\/destino.test\/wp-content\/uploads\/2026\/11\/foto-origen.jpg"}' === get_option( 'elementor_json' ), 'El JSON escapado no se reescribió.' );
	mig_assert( 'yo@origen.test' === get_option( 'admin_email' ), 'Se cambió un correo del dominio de origen.' );
	$post = $GLOBALS['wpdb']->posts[7] ?? null;
	mig_assert( $post && '<img src="https://destino.test/wp-content/uploads/2026/11/foto-origen.jpg"> origen.test.mx' === $post['post_content'] && 'https://origen.test/?p=7' === $post['guid'], 'El contenido no se reescribió o se tocó el guid o un dominio que sólo contiene al del origen: ' . json_encode( $post ) );
	$real = $GLOBALS['mig']['sites']['destino']['real'];
	mig_assert( is_file( $real . '/wp-content/uploads/2026/11/foto-origen.jpg' ) && is_file( $real . '/wp-content/plugins/solo-origen.php' ) && is_file( $real . '/wp-content/uploads/2026/11/solo-destino.txt' ), 'Los archivos del origen no llegaron al destino.' );
	mig_assert( ! is_file( $real . '/wp-content/uploads/elementor/css/post-7.css' ), 'El CSS generado con las URL del origen siguió en el destino.' );
	mig_assert( false !== strpos( (string) file_get_contents( $real . '/wp-config.php' ), "'dst_'" ), 'La restauración tocó el wp-config.php del destino.' );
	// La copia previa, bloqueada y marcada, es la que deshace la migración; el trabajo y la descarga no dejan restos.
	$target = Digitalisimo_Backups_Migrate::undo_target();
	mig_assert( $target && ! empty( $target['locked'] ) && 'pre-restore' === $target['policy'] && $migrated_job === $target['migration']['job'] && 'https://origen.test/' === $target['migration']['from'], 'La copia previa de la migración no quedó marcada: ' . json_encode( $target ) );
	mig_assert( ! is_dir( mig_storage() . '/jobs/' . $migrated_job . '/download' ) && ! glob( mig_storage() . '/migrations/pull-*.key' ), 'La migración dejó la descarga o la clave guardada en el destino.' );
	$view = Digitalisimo_Backups_Jobs::view( $job );
	mig_assert( 'Migración desde otro sitio' === $view['origin_label'] && 'desde origen.test' === $view['destination_label'] && false !== strpos( $view['detail'], 'Inicia sesión con un usuario del sitio de origen' ), 'La tarjeta «Trabajos» no describe la migración: ' . json_encode( $view ) );
	// El origen no cambió, su clave quedó usada y su paquete se borró; la clave no sirve otra vez.
	mig_assert( 'used' === mig_record( $p['id'] )['state'], 'La clave no quedó usada.' );
	mig_in( 'origen', function() use ( $origin_before, $p ) {
		$now = array( 'options' => array_diff_key( $GLOBALS['bk_options'], array( 'digitalisimo_backups_vault_migration' => 1, 'digitalisimo_backups_history' => 1, 'digitalisimo_backups_private_storage' => 1 ) ), 'posts' => $GLOBALS['wpdb']->posts, 'files' => mig_tree( $GLOBALS['mig']['sites']['origen']['real'] ) );
		mig_assert( $now === $origin_before, 'El origen cambió durante la migración.' );
		mig_assert( array() === (array) get_option( 'digitalisimo_backups_history', array() ), 'El paquete entró en el historial del origen.' );
		mig_assert( ! glob( mig_storage() . '/jobs/*/migration-' . $p['id'] . '.dgbk' ) && ! is_file( mig_storage() . '/migrations/' . $p['id'] . '.key' ), 'El paquete o su clave siguen en el origen.' );
	} );
	$reply = mig_source( 'hello', $p['header'] );
	mig_assert( 410 === $reply[0] && false !== strpos( $reply[1]['message'], 'ya se usó' ), 'Una clave usada siguió sirviendo: ' . json_encode( $reply ) );
	$events = array_column( mig_state()['log'], 'event' );
	foreach ( array( 'hello', 'package', 'ready', 'download', 'used' ) as $event ) mig_assert( in_array( $event, $events, true ), 'El registro del origen no anota «' . $event . '».' );
	mig_use( 'origen' );
} );

mig_case( 'g · un paquete alterado en el origen se rechaza antes de tocar el destino', function() use ( &$all_needles, $NS ) {
	foreach ( array( 'cabecera' => 'no está cifrado con la clave de esta migración', 'cifrado' => 'no supera la comprobación de su cifrado' ) as $what => $expected ) {
		$key = mig_key();
		$p = mig_parts( $key['key'] );
		$all_needles = array_merge( $all_needles, $p['needles'] );
		// Quien controla el almacén del origen cambia el paquete ya listo y el SHA-256 que anuncia (cambiarlo a mitad de la
		// descarga ya lo detecta la cabecera de cada fragmento: «cambió durante la descarga»).
		$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) use ( $p, $what ) {
			if ( null === $response || false === strpos( $route, '/migrate/source/step' ) ) return null;
			$view = json_decode( $response['body'], true );
			if ( empty( $view['ready'] ) ) return null;
			$view['sha256'] = mig_in( 'origen', function() use ( $p, $what ) {
				$job_id = mig_record( $p['id'] )['job'];
				$path = mig_storage() . '/jobs/' . $job_id . '/migration-' . $p['id'] . '.dgbk';
				$raw = file_get_contents( $path );
				if ( 'cabecera' === $what ) $raw = preg_replace( '/"id":"([0-9a-f])/', '"id":"' . ( 'a' === substr( strstr( $raw, '"id":"' ), 6, 1 ) ? 'b' : 'a' ), $raw, 1 );
				else $raw[ strlen( $raw ) - 40 ] = chr( ord( $raw[ strlen( $raw ) - 40 ] ) ^ 1 );
				file_put_contents( $path, $raw );
				$job = Digitalisimo_Backups_Jobs::get( $job_id );
				$job['result']['sha256'] = hash_file( 'sha256', $path );
				Digitalisimo_Backups_Job_Store::write( $job );
				return $job['result']['sha256'];
			} );
			$response['body'] = json_encode( $view );
			return $response;
		} );
		mig_use( 'destino' );
		$history = (array) get_option( 'digitalisimo_backups_history', array() );
		$state = array( get_option( 'blogname' ), $GLOBALS['wpdb']->posts );
		$reply = mig_rest( 'POST', $NS . '/migration/pull', array( 'key' => $key['key'], 'confirm' => 'MIGRAR' ) );
		mig_assert( 202 === $reply[0], 'El destino no encoló la migración (' . $what . '): ' . json_encode( $reply ) );
		$job = mig_drive( $reply[1]['id'] );
		$GLOBALS['mig']['faults'] = array();
		mig_assert( 'failed' === $job['status'] && ! empty( $job['error']['permanent'] ) && false !== strpos( (string) $job['error']['message'], $expected ) && false !== strpos( (string) $job['error']['message'], 'No se cambió nada de este sitio' ), 'Un paquete alterado (' . $what . ') no se rechazó como debía: ' . json_encode( $job['error'] ) );
		mig_assert( $history === (array) get_option( 'digitalisimo_backups_history', array() ) && $state === array( get_option( 'blogname' ), $GLOBALS['wpdb']->posts ), 'Un paquete alterado (' . $what . ') cambió el destino o creó una copia previa.' );
		mig_assert( ! is_dir( mig_storage() . '/jobs/' . $job['id'] . '/download' ) && ! glob( mig_storage() . '/migrations/pull-*.key' ), 'El rechazo dejó la descarga o la clave en el destino.' );
		mig_use( 'origen' );
	}
} );

mig_case( 'h · deshacer la migración', function() use ( $NS, $destination_before ) {
	mig_use( 'destino' );
	$reply = mig_rest( 'POST', $NS . '/migration/undo', array( 'confirm' => 'deshacerlo' ) );
	mig_assert( 400 === $reply[0], 'Se deshizo sin confirmar.' );
	$reply = mig_rest( 'POST', $NS . '/migration/undo', array( 'confirm' => 'DESHACER' ) );
	mig_assert( 200 === $reply[0] && true === $reply[1]['undone'] && 'https://origen.test/' === $reply[1]['from'], 'No se deshizo la migración: ' . json_encode( $reply ) );
	mig_assert( $destination_before === array( 'blogname' => get_option( 'blogname' ), 'home' => get_option( 'home' ), 'posts' => $GLOBALS['wpdb']->posts ), 'El destino no volvió a su estado anterior: ' . json_encode( array( get_option( 'blogname' ), get_option( 'home' ) ) ) );
	$history = (array) get_option( 'digitalisimo_backups_history', array() );
	$undone = array_values( array_filter( $history, function( $row ) { return is_array( $row['migration'] ?? null ); } ) );
	mig_assert( 1 === count( $undone ) && 'undone' === $undone[0]['migration']['state'] && null === Digitalisimo_Backups_Migrate::undo_target(), 'La migración deshecha no quedó anotada.' );
	mig_assert( 2 <= count( array_filter( $history, function( $row ) { return 'pre-restore' === ( $row['policy'] ?? '' ); } ) ), 'Deshacer no guardó antes el estado migrado en otra copia previa.' );
	$reply = mig_rest( 'POST', $NS . '/migration/undo', array( 'confirm' => 'DESHACER' ) );
	mig_assert( 404 === $reply[0] && 'digitalisimo_backups_no_migration' === $reply[1]['code'], 'Se pudo deshacer dos veces: ' . json_encode( $reply ) );
	mig_assert( in_array( 'undone', array_column( (array) get_option( 'digitalisimo_backups_vault_migration' )['log'], 'event' ), true ), 'El registro del destino no anota que se deshizo.' );
	mig_use( 'origen' );
} );

mig_case( 'i · tipos de sitio incompatibles y HTTP sin laboratorio, antes de descargar', function() use ( &$all_needles, $NS ) {
	$cases = array(
		array( true, false, false, 'no declara una red', 'MULTISITE' ),
		array( false, true, false, 'sitio individual a sitio individual o de red a red', '' ),
		array( true, true, true, 'mismo tipo', 'SUBDOMAIN_INSTALL' ),
	);
	foreach ( $cases as $case ) {
		list( $origin_network, $destination_network, $origin_subdomain, $expected, $config ) = $case;
		$GLOBALS['mig']['sites']['origen']['multisite'] = $origin_network;
		$GLOBALS['mig']['sites']['origen']['subdomain'] = $origin_subdomain;
		mig_use( 'destino' );
		mig_use( 'origen' );
		$key = mig_key();
		$all_needles = array_merge( $all_needles, mig_parts( $key['key'] )['needles'] );
		$GLOBALS['mig']['sites']['destino']['multisite'] = $destination_network;
		$before = count( $GLOBALS['mig']['requests'] );
		mig_use( 'destino' );
		$reply = mig_rest( 'POST', $NS . '/migration/pull', array( 'key' => $key['key'], 'confirm' => 'MIGRAR', 'network' => $destination_network ) );
		$routes = array_column( array_slice( $GLOBALS['mig']['requests'], $before ), 'route' );
		mig_assert( 422 === $reply[0] && false !== strpos( $reply[1]['message'], $expected ) && ( '' === $config || false !== strpos( $reply[1]['message'], $config ) ), 'La incompatibilidad no se explicó: ' . json_encode( $reply ) );
		mig_assert( array( '/digitalisimo-backups/v1/migrate/source/hello' ) === $routes && ! array_filter( Digitalisimo_Backups_Jobs::active(), function( $job ) { return 'migrate' === ( $job['spec']['kind'] ?? '' ); } ), 'Se pidió o se encoló algo más que la presentación: ' . json_encode( $routes ) );
		$GLOBALS['mig']['sites']['destino']['multisite'] = false;
		mig_use( 'origen' );
	}
	$GLOBALS['mig']['sites']['origen']['multisite'] = false;
	$GLOBALS['mig']['sites']['origen']['subdomain'] = false;
	mig_use( 'destino' );
	mig_use( 'origen' );
	// Una clave de un origen sin HTTPS: el origen sólo la genera con «Permitir HTTP» y el destino sólo la usa con él.
	update_option( 'home', 'http://origen.test' );
	$key = mig_in( 'origen', function() use ( $NS ) { return mig_rest( 'POST', $NS . '/migration/keys', array() ); } );
	mig_assert( 422 === $key[0] && false !== strpos( $key[1]['message'], 'HTTPS' ), 'Un origen sin HTTPS generó una clave sin «Permitir HTTP».' );
	Digitalisimo_Backups_Migrate::set_allow_http( true );
	$key = mig_key();
	$all_needles = array_merge( $all_needles, mig_parts( $key['key'] )['needles'] );
	Digitalisimo_Backups_Migrate::set_allow_http( false );
	update_option( 'home', 'https://origen.test' );
	mig_use( 'destino' );
	$reply = mig_rest( 'POST', $NS . '/migration/pull', array( 'key' => $key['key'], 'confirm' => 'MIGRAR' ) );
	mig_assert( 422 === $reply[0] && false !== strpos( $reply[1]['message'], 'sin HTTPS' ) && false !== strpos( $reply[1]['message'], 'Permitir HTTP' ), 'El destino aceptó una clave http sin «Permitir HTTP»: ' . json_encode( $reply ) );
	$reply = mig_rest( 'POST', $NS . '/migration/pull', array( 'confirm' => 'MIGRAR' ), array(), array( 'key' => $key['key'] ) );
	mig_assert( 400 === $reply[0] && 'digitalisimo_backups_key_in_url' === $reply[1]['code'], 'Una clave en la URL de /migration/pull no se rechazó.' );
	$reply = mig_rest( 'POST', $NS . '/migration/pull', array( 'key' => 'DGMIG1.no-es-una-clave', 'confirm' => 'MIGRAR' ) );
	mig_assert( 400 === $reply[0] && 'digitalisimo_backups_migration_key_invalid' === $reply[1]['code'] && false === strpos( json_encode( $reply ), 'no-es-una-clave' ), 'Una clave mal formada no se rechazó sin repetirla.' );
	mig_use( 'origen' );
} );

mig_case( 'j · ningún secreto en URL, respuestas, opciones, trabajos ni registros', function() use ( &$all_needles ) {
	$haystacks = array();
	foreach ( $GLOBALS['mig']['requests'] as $request ) {
		$haystacks[] = $request['url'];
		$haystacks[] = (string) ( $request['args']['body'] ?? '' );
	}
	foreach ( array( 'origen', 'destino' ) as $site ) {
		mig_in( $site, function() use ( &$haystacks ) {
			$haystacks[] = serialize( $GLOBALS['bk_options'] ) . serialize( $GLOBALS['bk_site_options'] );
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( mig_storage(), FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) if ( preg_match( '/\.(json|jsonl|key)$/', $file->getFilename() ) ) $haystacks[] = (string) file_get_contents( $file->getPathname() );
		} );
	}
	$text = implode( "\n", $haystacks );
	foreach ( array_unique( $all_needles ) as $needle ) mig_assert( false === strpos( $text, $needle ), 'Un secreto de la migración apareció en una URL, un cuerpo, una opción, un trabajo o un registro.' );
	// La credencial sólo viaja en su cabecera.
	foreach ( $GLOBALS['mig']['requests'] as $request ) mig_assert( isset( $request['args']['headers']['X-Digitalisimo-Migration'] ) && false === strpos( $request['url'], 'X-Digitalisimo' ), 'Una petición al origen no llevó la credencial en su cabecera.' );
} );

echo $failures ? 'Migración entre servidores con fallos: ' . implode( ', ', $failures ) . "\n" : "Migración entre servidores: todo correcto.\n";
mig_remove_tree( $GLOBALS['mig_root'] );
exit( $failures ? 1 : 0 );
