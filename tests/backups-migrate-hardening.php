<?php
/**
 * Seguridad operativa de la migración (revisión adversaria de 220b2419: H3, H5, H6, H8, P2, P5 y P8), con el mismo
 * entorno de dos instalaciones de tests/backups-migrate.php («origen» https://origen.test y «destino»
 * https://destino.test, motor real de los dos lados):
 *
 *   (h3) sin clave, nadie cierra la API del origen: 40 peticiones anónimas no dan 429 a la credencial válida, la
 *        migración en curso termina, el registro de usos conserva los usos (los rechazos van a un contador aparte) y
 *        una clave atada a una migración que falló sin poder liberarla se retoma pasado STALE desde otro trabajo;
 *   (p2) la caducidad no corta una migración que avanza; una clave en uso sin señales del destino sí caduca;
 *   (h6) atar la clave es atómico: de dos destinos que piden el paquete a la vez, el segundo recibe 409;
 *   (h5) el destino no se conecta a loopback, red privada, enlace local (metadatos), «localhost» ni a un nombre que
 *        resuelve a ellas, tampoco tras una redirección (que sí sigue a un host público); con el laboratorio, sí; el
 *        cuerpo de una respuesta ajena nunca llega al administrador; cada respuesta tiene tope (un fragmento de más se
 *        recorta y se rechaza);
 *   (h8) los avisos dicen «migración» y van al destinatario del destino (no al del origen); el paquete del origen no
 *        avisa como un respaldo;
 *   (p5) en una red, la API del origen sólo responde en su sitio principal;
 *   (p8) una clave de destino huérfana (el proceso murió durante la presentación) se barre;
 *   (n2) una clave cuyo paquete ya se entregó entero no se retoma desde otro destino (un solo uso);
 *   (n4) las peticiones anónimas no escriben en el estado y el contador, como mucho una vez por minuto;
 *   (n5) una dirección que no resuelve o un IPv6 con zona se rechazan, cada petición fija la IP comprobada y una
 *        redirección a otro host no se sigue (la credencial no sale de su host);
 *   (n7) los nombres y las reglas de una fase salen del tipo del trabajo, aunque dos tipos repitan el nombre.
 * Cada caso escribe «FALLA (x)» si falla. Con el código de 220b2419 fallan h3, p2, h6, h5, h8, p5 y p8; con el de
 * f86a3f17, n2, n4, n5 y n7.
 */
$GLOBALS['hard_mail'] = array();
function wp_mail( $to, $subject, $body ) { $GLOBALS['hard_mail'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body ); return true; }
require __DIR__ . '/backups-migrate/env.php';

mig_site( 'origen', 'origen.test', 'wp_', array(
	'wp-content/uploads/2026/11/foto-origen.jpg' => random_bytes( 300 * 1024 ),
	'wp-content/plugins/solo-origen.php' => "<?php /* Plugin Name: Del origen */\n",
) );
mig_site( 'destino', 'destino.test', 'dst_', array( 'wp-content/uploads/2026/11/solo-destino.txt' => 'del destino' ) );
$GLOBALS['mig']['sites']['origen']['options'] += array( 'admin_email' => 'yo@origen.test' );
$GLOBALS['mig']['sites']['destino']['options'] += array( 'admin_email' => 'yo@destino.test' );
$GLOBALS['mig']['sites']['origen']['db']->posts = array( 7 => array( 'ID' => '7', 'post_content' => '<img src="https://origen.test/wp-content/uploads/2026/11/foto-origen.jpg"> contenido-del-origen', 'guid' => 'https://origen.test/?p=7' ) );
$GLOBALS['mig']['sites']['destino']['db']->posts = array( 3 => array( 'ID' => '3', 'post_content' => 'Página del destino', 'guid' => 'https://destino.test/?p=3' ) );
$GLOBALS['bk_filters']['digitalisimo_backups_migration_chunk'] = 65536;
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$GLOBALS['bk_filters']['digitalisimo_backups_migration_poll'] = 0;
mig_use( 'origen' );

$failures = array();
function hard_case( $label, callable $test ) {
	// HARD_ONLY=h6 corre sólo ese caso (para ver cada uno por separado contra el código anterior).
	$only = (string) getenv( 'HARD_ONLY' );
	if ( '' !== $only && 0 !== strpos( $label, $only . ' ' ) ) return;
	try {
		$test();
		echo 'ok   ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures'][] = $label;
		echo 'FALLA (' . $label . '): ' . $e->getMessage() . "\n";
		if ( getenv( 'MIG_TRACE' ) ) echo $e->getTraceAsString() . "\n";
	}
}
const HARD_NS = 'digitalisimo-backups/v1';
function hard_parts( $key ) {
	$json = json_decode( Digitalisimo_Backups_Keys::b64url_decode( substr( $key, 7 ) ), true );
	$s = Digitalisimo_Backups_Keys::b64url_decode( $json['s'], 32 );
	return array( 'id' => $json['i'], 'url' => $json['u'], 'header' => $json['i'] . '.' . Digitalisimo_Backups_Keys::b64url_encode( sodium_crypto_generichash( 'DGBK1/migrate-auth', $s, 32 ) ) );
}
function hard_key( $hours = 24 ) {
	return mig_in( 'origen', function() use ( $hours ) {
		$reply = mig_rest( 'POST', HARD_NS . '/migration/keys', array( 'hours' => $hours, 'network' => is_multisite() ) );
		mig_assert( 201 === $reply[0], 'No se generó la clave: ' . json_encode( $reply[1] ) );
		return $reply[1];
	} );
}
/** Petición a la API del origen como la haría cualquiera en Internet (sin sesión). */
function hard_source( $action, $header, $client = '', array $body = array(), $ip = '198.51.100.7' ) {
	$headers = array();
	if ( null !== $header ) $headers['X-Digitalisimo-Migration'] = $header;
	if ( '' !== $client ) $headers['X-Digitalisimo-Migration-Client'] = $client;
	return mig_in( 'origen', function() use ( $action, $headers, $body, $ip ) {
		$_SERVER['REMOTE_ADDR'] = $ip;
		return mig_rest( 'download' === $action ? 'GET' : 'POST', HARD_NS . '/migrate/source/' . $action, $body, $headers, array(), false );
	} );
}
function hard_state() { return mig_in( 'origen', function() { return get_option( 'digitalisimo_backups_vault_migration' ); } ); }
function hard_rejected() { return (array) mig_in( 'origen', function() { return get_option( 'digitalisimo_backups_vault_migration_rejected', array() ); } ); }
function hard_record( $id ) { return hard_state()['keys'][ $id ] ?? null; }
function hard_edit_record( $id, callable $edit ) {
	mig_in( 'origen', function() use ( $id, $edit ) {
		$state = get_option( 'digitalisimo_backups_vault_migration' );
		$state['keys'][ $id ] = $edit( $state['keys'][ $id ] );
		update_option( 'digitalisimo_backups_vault_migration', $state );
	} );
}
function hard_pull( $key ) {
	mig_use( 'destino' );
	return mig_rest( 'POST', HARD_NS . '/migration/pull', array( 'key' => $key, 'confirm' => 'MIGRAR' ) );
}
/** Avanza el trabajo en el sitio en curso hasta $until( $job ) o hasta que termina. */
function hard_drive_until( $id, callable $until, $max = 80 ) {
	for ( $i = 0; $i < $max; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) || $until( $job ) ) return $job;
		if ( (int) $job['retry_at'] > time() ) { $job['retry_at'] = 0; Digitalisimo_Backups_Job_Store::write( $job ); }
		Digitalisimo_Backups_Jobs::tick( 0 );
	}
	return Digitalisimo_Backups_Jobs::get( $id );
}
/** Una clave fabricada que apunta a $url (la misma forma que una real). */
function hard_forged( $url ) {
	return 'DGMIG1.' . Digitalisimo_Backups_Keys::b64url_encode( json_encode( array( 'v' => 1, 'u' => $url, 'i' => '0123456789abcdef', 's' => Digitalisimo_Backups_Keys::b64url_encode( random_bytes( 32 ) ) ) ) );
}
function hard_undo_destination() {
	// La siguiente migración parte de un destino como el original (las pruebas comparan lo que llega del origen).
	mig_use( 'destino' );
	$reply = mig_rest( 'POST', HARD_NS . '/migration/undo', array( 'confirm' => 'DESHACER' ) );
	mig_assert( 200 === $reply[0], 'No se pudo deshacer la migración anterior: ' . json_encode( $reply[1] ) );
}

hard_case( 'h3 · sin clave nadie cierra la API del origen ni vacía el registro de usos', function() {
	$key = hard_key();
	$p = hard_parts( $key['key'] );
	$reply = hard_pull( $key['key'] );
	mig_assert( 202 === $reply[0], 'El destino no encoló: ' . json_encode( $reply ) );
	$id = $reply[1]['id'];
	mig_use( 'destino' );
	$job = hard_drive_until( $id, function() use ( $p ) { return 'in_use' === ( hard_record( $p['id'] )['state'] ?? '' ); } );
	mig_assert( ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) && 'in_use' === hard_record( $p['id'] )['state'], 'La migración no llegó a atar su clave.' );
	// 40 peticiones anónimas (sin cabecera, basura o un id inventado) desde IP distintas.
	for ( $i = 0; $i < 40; $i++ ) hard_source( 'hello', 0 === $i % 3 ? null : ( 1 === $i % 3 ? 'basura' : bin2hex( random_bytes( 8 ) ) . '.' . Digitalisimo_Backups_Keys::b64url_encode( random_bytes( 32 ) ) ), '', array(), '203.0.113.' . ( 10 + $i ) );
	$probe = hard_source( 'hello', $p['header'] );
	mig_assert( 200 === $probe[0], 'Tras 40 peticiones anónimas la API no atendió a la credencial válida: HTTP ' . $probe[0] . ' ' . ( $probe[1]['message'] ?? '' ) );
	mig_use( 'destino' );
	$job = mig_drive( $id, 120 );
	mig_assert( 'success' === $job['status'], 'La migración en curso no terminó tras las peticiones anónimas: ' . $job['status'] . ' ' . json_encode( $job['error'] ) );
	$state = hard_state();
	$events = array_column( array_filter( $state['log'], function( $event ) use ( $p ) { return substr( $p['id'], 0, 8 ) === $event['key']; } ), 'event' );
	foreach ( array( 'created', 'hello', 'package', 'ready', 'download', 'used' ) as $event ) mig_assert( in_array( $event, $events, true ), 'El registro de usos perdió «' . $event . '»: ' . implode( ',', $events ) );
	mig_assert( ! in_array( 'auth_failed', array_column( $state['log'], 'event' ), true ) && (int) ( hard_rejected()['count'] ?? 0 ) >= 1, 'Las peticiones anónimas entraron en el registro de usos o no se contaron aparte.' );
	$status = mig_in( 'origen', function() { return mig_rest( 'GET', HARD_NS . '/migration' ); } );
	mig_assert( 200 === $status[0] && (int) $status[1]['rejected']['count'] >= 1, 'El estado no informa de las peticiones rechazadas.' );
	hard_undo_destination();

	// Una migración que falla sin poder liberar su clave (el origen no respondió al «release») no la deja atada: otro
	// trabajo del destino la retoma pasado STALE, y la migración termina.
	$key = hard_key();
	$p = hard_parts( $key['key'] );
	$reply = hard_pull( $key['key'] );
	$dead = $reply[1]['id'];
	mig_use( 'destino' );
	hard_drive_until( $dead, function( $job ) { return 'download' === $job['phase']; } );
	$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) {
		return null === $response && false !== strpos( $route, '/migrate/source/release' ) ? new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' ) : null;
	} );
	Digitalisimo_Backups_Jobs::cancel( $dead );
	$GLOBALS['mig']['faults'] = array();
	mig_assert( 'in_use' === hard_record( $p['id'] )['state'], 'La clave no quedó atada (la prueba necesita un «release» perdido).' );
	$reply = hard_pull( $key['key'] );
	mig_use( 'destino' );
	$early = mig_drive( $reply[1]['id'], 10 );
	mig_assert( 'failed' === $early['status'] && false !== strpos( (string) $early['error']['message'], 'Otra migración ya usa esta clave' ), 'Una clave atada a un trabajo recién abandonado se retomó antes de STALE: ' . json_encode( $early['error'] ) );
	hard_edit_record( $p['id'], function( $record ) { $record['seen'] = time() - Digitalisimo_Backups_Migrate::STALE - 5; $record['created'] = time() - Digitalisimo_Backups_Migrate::STALE - 5; return $record; } );
	$reply = hard_pull( $key['key'] );
	mig_assert( 202 === $reply[0], 'La clave abandonada no se pudo usar otra vez: ' . json_encode( $reply ) );
	mig_use( 'destino' );
	$job = mig_drive( $reply[1]['id'], 120 );
	mig_assert( 'success' === $job['status'] && 'used' === hard_record( $p['id'] )['state'], 'La migración con la clave retomada no terminó: ' . json_encode( $job['error'] ) );
	mig_assert( in_array( 'taken_over', array_column( hard_state()['log'], 'event' ), true ), 'El registro no anota que otro destino retomó la clave.' );
	hard_undo_destination();
} );

hard_case( 'p2 · la caducidad no corta una migración que avanza', function() {
	$key = hard_key( 1 );
	$p = hard_parts( $key['key'] );
	$reply = hard_pull( $key['key'] );
	$id = $reply[1]['id'];
	mig_use( 'destino' );
	// Un corte de red tras el primer fragmento deja la descarga a medias (se reanuda en el paso siguiente).
	$GLOBALS['hard_cut'] = 0;
	$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) {
		if ( null !== $response || false === strpos( $route, '/migrate/source/download' ) ) return null;
		return in_array( ++$GLOBALS['hard_cut'], array( 2, 3 ), true ) ? new WP_Error( 'http_request_failed', 'cURL error 56: corte' ) : null;
	} );
	$job = hard_drive_until( $id, function( $job ) { return (int) ( $job['checkpoint']['download']['offset'] ?? 0 ) > 0; } );
	$GLOBALS['mig']['faults'] = array();
	mig_assert( 'download' === $job['phase'] && (int) $job['checkpoint']['download']['offset'] > 0, 'La descarga no quedó a medias (la prueba necesita una migración que avanza).' );
	// La hora de la clave pasó a mitad de la descarga (el destino acaba de dar señales).
	hard_edit_record( $p['id'], function( $record ) { $record['expires'] = time() - 60; $record['seen'] = time(); return $record; } );
	mig_in( 'origen', function() { Digitalisimo_Backups_Migrate::sweep(); } );
	mig_assert( 'in_use' === hard_record( $p['id'] )['state'], 'La clave en uso caducó a mitad de la descarga.' );
	mig_use( 'destino' );
	$job = mig_drive( $id, 120 );
	mig_assert( 'success' === $job['status'], 'La migración no terminó tras pasar la hora de su clave: ' . json_encode( $job['error'] ) );
	hard_undo_destination();
	// En uso pero sin señales del destino durante STALE: caduca y se retira.
	$key = hard_key( 1 );
	$p = hard_parts( $key['key'] );
	$reply = hard_pull( $key['key'] );
	mig_use( 'destino' );
	hard_drive_until( $reply[1]['id'], function( $job ) { return 'download' === $job['phase']; } );
	hard_edit_record( $p['id'], function( $record ) { $record['expires'] = time() - 60; $record['seen'] = time() - Digitalisimo_Backups_Migrate::STALE - 5; $record['created'] = $record['seen']; return $record; } );
	mig_in( 'origen', function() { Digitalisimo_Backups_Migrate::sweep(); } );
	mig_assert( 'expired' === hard_record( $p['id'] )['state'], 'Una clave en uso abandonada y pasada su hora no caducó.' );
	mig_use( 'destino' );
	Digitalisimo_Backups_Jobs::cancel( $reply[1]['id'] );
} );

hard_case( 'h6 · atar la clave a un destino es atómico', function() {
	$key = hard_key();
	$p = hard_parts( $key['key'] );
	$one = Digitalisimo_Backups_Job_Store::new_id();
	$two = Digitalisimo_Backups_Job_Store::new_id();
	mig_use( 'origen' );
	// Las dos peticiones pasan la autenticación antes de que ninguna escriba (como dos procesos de PHP).
	$record_one = Digitalisimo_Backups_Migrate::authenticate( $p['header'], $one, 'package' );
	$record_two = Digitalisimo_Backups_Migrate::authenticate( $p['header'], $two, 'package' );
	mig_assert( is_array( $record_one ) && is_array( $record_two ), 'La autenticación falló.' );
	$first = Digitalisimo_Backups_Migrate::source_package( $record_one, $one, array( 'site' => 'https://uno.test/' ) );
	$second = Digitalisimo_Backups_Migrate::source_package( $record_two, $two, array( 'site' => 'https://dos.test/' ) );
	mig_assert( is_array( $first ) && ! is_wp_error( $first ), 'El primer destino no recibió el paquete.' );
	mig_assert( is_wp_error( $second ) && 409 === ( $second->get_error_data()['status'] ?? 0 ), 'El segundo destino también recibió el paquete con la misma clave de un solo uso.' );
	$step_one = hard_source( 'step', $p['header'], $one );
	mig_assert( 200 === $step_one[0] && 'uno.test' === hard_record( $p['id'] )['site'], 'El primero perdió la clave: HTTP ' . $step_one[0] . ' · sitio ' . hard_record( $p['id'] )['site'] );
	mig_in( 'origen', function() use ( $p ) { Digitalisimo_Backups_Migrate::revoke( $p['id'] ); } );
} );

hard_case( 'h5 · el destino sólo se conecta a sitios públicos, con tope de tamaño y sin mostrar cuerpos ajenos', function() {
	$GLOBALS['mig']['dns']['interno.example'] = array( '10.0.0.7' );
	$GLOBALS['mig']['dns']['otro.example'] = array( '192.0.2.44' );
	$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) {
		if ( null !== $response ) return null;
		$url = end( $GLOBALS['mig']['requests'] )['url'];
		if ( 0 !== strpos( $url, 'https://otro.example/' ) ) return null;
		return array( 'response' => array( 'code' => 400 ), 'body' => json_encode( array( 'message' => 'respuesta-del-servicio-interno: AKIAEJEMPLO' ) ), 'headers' => array() );
	} );
	foreach ( array( 'https://169.254.169.254/latest/meta-data/iam', 'https://127.0.0.1:6379/', 'https://10.0.0.5:9200/_cluster', 'https://[::1]/', 'https://[::ffff:127.0.0.1]/', 'https://localhost/', 'https://interno.example/wp-json/' ) as $url ) {
		$before = count( $GLOBALS['mig']['requests'] );
		$reply = hard_pull( hard_forged( $url ) );
		mig_assert( 422 === $reply[0] && false !== strpos( (string) $reply[1]['message'], 'dirección interna' ) && count( $GLOBALS['mig']['requests'] ) === $before, 'El destino se conectó a ' . $url . ': HTTP ' . $reply[0] . ' · ' . ( $reply[1]['message'] ?? '' ) );
	}
	// Una respuesta ajena (de un host público que no es la API de migración) no llega al administrador.
	$reply = hard_pull( hard_forged( 'https://otro.example/api' ) );
	mig_assert( 422 === $reply[0] && false === strpos( (string) $reply[1]['message'], 'respuesta-del-servicio-interno' ) && false !== strpos( (string) $reply[1]['message'], 'HTTP 400' ), 'El cuerpo de una respuesta ajena llegó al administrador: ' . ( $reply[1]['message'] ?? '' ) );
	$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) {
		if ( null !== $response || 0 !== strpos( end( $GLOBALS['mig']['requests'] )['url'], 'https://otro.example/' ) ) return null;
		return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'ready' => false, 'problems' => array( 'secreto-ajeno' ) ) ), 'headers' => array() );
	} );
	$reply = hard_pull( hard_forged( 'https://otro.example/api' ) );
	mig_assert( 422 === $reply[0] && false === strpos( (string) $reply[1]['message'], 'secreto-ajeno' ) && false !== strpos( (string) $reply[1]['message'], 'no responde como la API de migración' ), 'Un 200 ajeno se tomó por la API de migración: ' . ( $reply[1]['message'] ?? '' ) );
	// Una redirección se sigue validando cada salto: hacia los metadatos, nunca; hacia el origen público, sí.
	$key = hard_key();
	$p = hard_parts( $key['key'] );
	$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) use ( $p ) {
		if ( null !== $response || false === strpos( $route, '/migrate/source/hello' ) ) return null;
		$url = end( $GLOBALS['mig']['requests'] )['url'];
		if ( false !== strpos( $url, '&salto=1' ) ) return null;
		return array( 'response' => array( 'code' => 301 ), 'body' => '', 'headers' => array( 'location' => empty( $GLOBALS['hard_safe_hop'] ) ? 'https://169.254.169.254/latest/meta-data/' : $url . '&salto=1' ) );
	} );
	$before = count( $GLOBALS['mig']['requests'] );
	$reply = hard_pull( $key['key'] );
	$hosts = array_map( function( $request ) { return parse_url( $request['url'], PHP_URL_HOST ); }, array_slice( $GLOBALS['mig']['requests'], $before ) );
	mig_assert( 422 === $reply[0] && false !== strpos( (string) $reply[1]['message'], 'otro host' ) && ! in_array( '169.254.169.254', $hosts, true ), 'Una redirección a los metadatos se siguió: ' . json_encode( array( $reply, $hosts ) ) );
	$GLOBALS['hard_safe_hop'] = true;
	$reply = hard_pull( $key['key'] );
	mig_assert( 202 === $reply[0], 'Una redirección dentro del origen no se siguió: ' . json_encode( $reply ) );
	$GLOBALS['mig']['faults'] = array();
	// Tope de tamaño: un fragmento con bytes de más se recorta y se rechaza; la descarga se reanuda y termina.
	$GLOBALS['hard_long'] = 0;
	$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) {
		if ( null === $response || false === strpos( $route, '/migrate/source/download' ) || $GLOBALS['hard_long']++ > 0 ) return null;
		$response['body'] .= str_repeat( 'x', 4096 );
		return $response;
	} );
	mig_use( 'destino' );
	$job = mig_drive( $reply[1]['id'], 120 );
	$GLOBALS['mig']['faults'] = array();
	mig_assert( 'success' === $job['status'], 'La migración no terminó tras un fragmento demasiado largo: ' . json_encode( $job['error'] ) );
	mig_assert( in_array( 'digitalisimo-backups/v1/migrate/source/download', array_map( function( $route ) { return ltrim( $route, '/' ); }, (array) ( $GLOBALS['mig']['truncated'] ?? array() ) ), true ), 'El fragmento de más no se recortó al recibirlo (sin limit_response_size).' );
	foreach ( $GLOBALS['mig']['requests'] as $request ) {
		$limit = (int) ( $request['args']['limit_response_size'] ?? 0 );
		$range = preg_match( '/bytes=(\d+)-(\d+)/', (string) ( $request['args']['headers']['Range'] ?? '' ), $m ) ? (int) $m[2] - (int) $m[1] + 1 : 0;
		mig_assert( $limit > 0 && ( $range ? $limit <= $range + 1 : $limit <= 1048576 ), 'Una petición al origen no tiene tope de tamaño: ' . $request['url'] );
	}
	hard_undo_destination();
	// Con el laboratorio, el destino sí acepta direcciones internas (ensayo en una red propia o en el mismo servidor).
	mig_in( 'destino', function() { Digitalisimo_Backups_Migrate::set_allow_http( true ); } );
	$before = count( $GLOBALS['mig']['requests'] );
	hard_pull( hard_forged( 'https://127.0.0.1:9573/' ) );
	mig_assert( count( $GLOBALS['mig']['requests'] ) > $before, 'Con el laboratorio activado el destino no intentó la dirección interna.' );
	mig_in( 'destino', function() { Digitalisimo_Backups_Migrate::set_allow_http( false ); } );
} );

hard_case( 'h8 · los avisos dicen «migración» y van al destinatario del destino', function() {
	mig_in( 'origen', function() { update_option( 'digitalisimo_backups_notifications', array( 'failure' => true, 'success' => true, 'email' => 'avisos@origen.test' ) ); } );
	mig_in( 'destino', function() { update_option( 'digitalisimo_backups_notifications', array( 'failure' => true, 'success' => true, 'email' => 'avisos@destino.test' ) ); } );
	$GLOBALS['hard_mail'] = array();
	// Una migración fallida (el origen revoca la clave antes de que el destino pida el paquete).
	$key = hard_key();
	$p = hard_parts( $key['key'] );
	$reply = hard_pull( $key['key'] );
	mig_in( 'origen', function() use ( $p ) { Digitalisimo_Backups_Migrate::revoke( $p['id'] ); } );
	mig_use( 'destino' );
	$job = mig_drive( $reply[1]['id'] );
	mig_in( 'destino', function() { Digitalisimo_Backups_Notify::flush_jobs(); } );
	$failed = array_values( array_filter( $GLOBALS['hard_mail'], function( $mail ) { return 'avisos@destino.test' === $mail['to']; } ) );
	mig_assert( 'failed' === $job['status'] && $failed && false !== strpos( $failed[0]['subject'], 'La migración falló' ) && false !== strpos( $failed[0]['body'], 'No se cambió nada de este sitio' ) && false === strpos( $failed[0]['body'], 'respaldo' ), 'Una migración fallida no avisó como migración: ' . json_encode( $failed ) );
	// Una migración terminada.
	$GLOBALS['hard_mail'] = array();
	$key = hard_key();
	$reply = hard_pull( $key['key'] );
	mig_use( 'destino' );
	$job = mig_drive( $reply[1]['id'], 120 );
	mig_assert( 'success' === $job['status'], 'La migración no terminó: ' . json_encode( $job['error'] ) );
	mig_in( 'origen', function() { Digitalisimo_Backups_Notify::flush_jobs(); } );
	mig_in( 'destino', function() { Digitalisimo_Backups_Notify::flush_jobs(); } );
	$package = array_values( array_filter( $GLOBALS['hard_mail'], function( $mail ) { return false !== strpos( $mail['body'], 'migration-' ) || false !== strpos( $mail['subject'], 'Respaldo completo' ); } ) );
	mig_assert( ! $package, 'Un aviso trata la migración como un respaldo: ' . json_encode( $package ) );
	$done = array_values( array_filter( $GLOBALS['hard_mail'], function( $mail ) { return false !== strpos( $mail['subject'], 'Migración terminada' ); } ) );
	mig_assert( 1 === count( $done ) && 'avisos@destino.test' === $done[0]['to'] && false !== strpos( $done[0]['body'], 'origen.test' ), 'La migración terminada no avisó a quien se avisaba en el destino: ' . json_encode( $GLOBALS['hard_mail'] ) );
	$restore = array_values( array_filter( $GLOBALS['hard_mail'], function( $mail ) { return false !== strpos( $mail['subject'], 'Restauración' ); } ) );
	mig_assert( ! $restore, 'La restauración que aplica la migración avisó además por su cuenta: ' . json_encode( $restore ) );
	hard_undo_destination();
} );

hard_case( 'p5 · en una red, la API del origen responde sólo en su sitio principal', function() {
	$key = hard_key();
	$p = hard_parts( $key['key'] );
	// Un subsitio de la red (el entorno es de un sitio: se simula la red y que este no es el principal).
	$reply = mig_in( 'origen', function() use ( $p ) {
		$GLOBALS['bk_multisite'] = true;
		$GLOBALS['mig']['main_site'] = false;
		try {
			return Digitalisimo_Backups_Migrate::authenticate( $p['header'], Digitalisimo_Backups_Job_Store::new_id(), 'step' );
		} finally {
			$GLOBALS['bk_multisite'] = false;
			unset( $GLOBALS['mig']['main_site'] );
		}
	} );
	mig_assert( is_wp_error( $reply ) && 'digitalisimo_backups_migration_not_main' === $reply->get_error_code() && 404 === ( $reply->get_error_data()['status'] ?? 0 ), 'La API del origen respondió en un sitio que no es el principal: ' . ( is_wp_error( $reply ) ? $reply->get_error_code() : json_encode( $reply ) ) );
	mig_in( 'origen', function() use ( $p ) { Digitalisimo_Backups_Migrate::revoke( $p['id'] ); } );
} );

hard_case( 'p8 · una clave de destino huérfana se barre', function() {
	mig_use( 'destino' );
	$dir = Digitalisimo_Backups::storage_directory() . '/migrations';
	if ( ! is_dir( $dir ) ) mkdir( $dir, 0700, true );
	$orphan = $dir . '/pull-' . bin2hex( random_bytes( 16 ) ) . '.key';
	file_put_contents( $orphan, 'sobre' );
	touch( $orphan, time() - 7200 );
	$key = hard_key();
	$reply = hard_pull( $key['key'] );
	mig_assert( 202 === $reply[0] && ! is_file( $orphan ), 'La clave de destino huérfana sigue en el almacén.' );
	mig_use( 'destino' );
	Digitalisimo_Backups_Jobs::cancel( $reply[1]['id'] );
} );

hard_case( 'n2 · una clave cuyo paquete se entregó entero no se retoma', function() {
	$key = hard_key();
	$p = hard_parts( $key['key'] );
	$reply = hard_pull( $key['key'] );
	$id = $reply[1]['id'];
	mig_use( 'destino' );
	// El destino legítimo descarga el paquete entero y pasa a comprobarlo y restaurarlo.
	$job = hard_drive_until( $id, function( $job ) { return in_array( $job['phase'], array( 'check', 'restore' ), true ); } );
	mig_assert( in_array( $job['phase'], array( 'check', 'restore' ), true ) && ! empty( hard_record( $p['id'] )['delivered'] ), 'La migración no llegó a comprobar el paquete o el origen no anotó que lo entregó: ' . $job['phase'] );
	// Su restauración dura más de STALE sin hablar con el origen, y alguien más con la clave pide el paquete.
	hard_edit_record( $p['id'], function( $record ) { $record['seen'] = time() - Digitalisimo_Backups_Migrate::STALE - 60; $record['created'] = time() - Digitalisimo_Backups_Migrate::STALE - 120; return $record; } );
	$thief = Digitalisimo_Backups_Job_Store::new_id();
	$package = hard_source( 'package', $p['header'], $thief, array( 'site' => 'https://ladron.test/' ) );
	$bytes = hard_source( 'download', $p['header'], $thief );
	mig_assert( 409 === $package[0] && 409 === $bytes[0], 'Otro cliente retomó la clave de un paquete ya entregado: package HTTP ' . $package[0] . ' · download HTTP ' . $bytes[0] );
	mig_use( 'destino' );
	$job = mig_drive( $id, 200 );
	$record = hard_record( $p['id'] );
	$events = array_column( array_filter( hard_state()['log'], function( $event ) use ( $p ) { return substr( $p['id'], 0, 8 ) === $event['key']; } ), 'event' );
	mig_assert( 'success' === $job['status'] && 'used' === $record['state'] && ! in_array( 'taken_over', $events, true ), 'La migración legítima no dejó la clave usada o se retomó: ' . $job['status'] . ' · ' . $record['state'] . ' · ' . implode( ',', $events ) );
	hard_undo_destination();
} );

hard_case( 'n4 · las peticiones anónimas escriben como mucho una vez por minuto', function() {
	$state_before = serialize( hard_state() );
	$count_before = (int) ( hard_rejected()['count'] ?? 0 );
	hard_edit_rejected_last( time() - 3600 );
	for ( $i = 0; $i < 300; $i++ ) hard_source( 'hello', 0 === $i % 2 ? null : 'basura', '', array(), '203.0.113.' . ( $i % 250 ) );
	mig_assert( $state_before === serialize( hard_state() ), 'Las peticiones anónimas escribieron en el estado de la migración.' );
	mig_assert( (int) hard_rejected()['count'] === $count_before + 1, 'Las peticiones anónimas escribieron el contador más de una vez por minuto: ' . json_encode( array( $count_before, hard_rejected() ) ) );
} );
function hard_edit_rejected_last( $at ) {
	mig_in( 'origen', function() use ( $at ) {
		$seen = (array) get_option( 'digitalisimo_backups_vault_migration_rejected', array() );
		$seen['last'] = $at;
		update_option( 'digitalisimo_backups_vault_migration_rejected', $seen );
	} );
}

hard_case( 'n5 · el destino no da por pública una dirección sin resolver, fija la IP comprobada y no lleva la credencial a otro host', function() {
	$GLOBALS['mig']['dns']['nada.example'] = array();
	foreach ( array( 'https://nada.example/wp-json/' => 'No se pudo resolver', 'https://[fe80::1%25eth0]/' => 'dirección interna' ) as $url => $expected ) {
		$before = count( $GLOBALS['mig']['requests'] );
		$reply = hard_pull( hard_forged( $url ) );
		mig_assert( 422 === $reply[0] && false !== strpos( (string) $reply[1]['message'], $expected ) && count( $GLOBALS['mig']['requests'] ) === $before, 'El destino se conectó a ' . $url . ': HTTP ' . $reply[0] . ' · ' . ( $reply[1]['message'] ?? '' ) );
	}
	// Una redirección del origen a otro host público no se sigue: la credencial no sale de su host.
	$key = hard_key();
	$GLOBALS['mig']['dns']['recolector.example'] = array( '192.0.2.77' );
	$GLOBALS['mig']['faults'] = array( function( $method, $route, $args, $response ) {
		if ( null !== $response || false === strpos( $route, '/migrate/source/hello' ) ) return null;
		return array( 'response' => array( 'code' => 307 ), 'body' => '', 'headers' => array( 'location' => 'https://recolector.example/robar' ) );
	} );
	$before = count( $GLOBALS['mig']['requests'] );
	$reply = hard_pull( $key['key'] );
	$GLOBALS['mig']['faults'] = array();
	$sent = array_slice( $GLOBALS['mig']['requests'], $before );
	$hosts = array_map( function( $request ) { return parse_url( $request['url'], PHP_URL_HOST ); }, $sent );
	mig_assert( 422 === $reply[0] && false !== strpos( (string) $reply[1]['message'], 'otro host' ) && ! in_array( 'recolector.example', $hosts, true ), 'La credencial viajó a otro host por una redirección: ' . json_encode( array( $reply, $hosts ) ) );
	// Cada petición lleva la IP que se comprobó, fijada para la conexión (cURL: CURLOPT_RESOLVE).
	mig_assert( $sent && 'origen.test:443:192.0.2.10' === (string) ( $sent[0]['args']['digitalisimo_pin'] ?? '' ), 'La petición no fija la IP comprobada: ' . json_encode( $sent[0]['args']['digitalisimo_pin'] ?? null ) );
	mig_in( 'origen', function() use ( $key ) { Digitalisimo_Backups_Migrate::revoke( hard_parts( $key['key'] )['id'] ); } );
} );

hard_case( 'n7 · los nombres de fase salen del tipo del trabajo, aunque dos tipos repitan nombre', function() {
	Digitalisimo_Backups_Jobs::register_kind( 'falso', 'Hard_Fake_Kind' );
	$restore = array( 'kind' => 'restore', 'spec' => array( 'type' => 'restore' ) );
	$fake = array( 'kind' => 'backup', 'spec' => array( 'kind' => 'falso' ) );
	$backup = array( 'kind' => 'backup', 'spec' => array( 'destination' => 'local' ) );
	mig_assert( 'Direcciones y cachés' === Digitalisimo_Backups_Jobs::phase_label( 'finish', $restore ) && 'Fin falso' === Digitalisimo_Backups_Jobs::phase_label( 'finish', $fake ) && 'Comprobaciones' === Digitalisimo_Backups_Jobs::phase_label( 'preflight', $backup ), 'phase_label() mezcla fases que se llaman igual en dos tipos: ' . json_encode( array( Digitalisimo_Backups_Jobs::phase_label( 'finish', $restore ), Digitalisimo_Backups_Jobs::phase_label( 'finish', $fake ) ) ) );
	mig_assert( Digitalisimo_Backups_Jobs::cancellable_phase( 'finish', $fake ) && 7 === Digitalisimo_Backups_Jobs::max_attempts( 'finish', $fake ) && 'transferring' === Digitalisimo_Backups_Jobs::status_for( 'finish', $fake['spec'] ) && 5 === Digitalisimo_Backups_Jobs::max_attempts( 'finish', $restore + array( 'spec' => array( 'type' => 'restore' ) ) ), 'Las consultas por fase no miran el tipo del trabajo.' );
} );
final class Hard_Fake_Kind {
	public static function job_phases() { return array( 'preflight', 'finish' ); }
	public static function job_phase( $phase ) { return 'finish' === $phase ? array( 'label' => 'Fin falso', 'floor' => 1, 'max' => 7, 'cancellable' => true, 'status' => 'transferring' ) : null; }
	public static function job_view( array $job, array $view ) { return $view; }
}

echo $failures ? 'Seguridad operativa de la migración con fallos: ' . implode( ', ', $failures ) . "\n" : "Seguridad operativa de la migración: todo correcto.\n";
mig_remove_tree( $GLOBALS['mig_root'] );
exit( $failures ? 1 : 0 );
