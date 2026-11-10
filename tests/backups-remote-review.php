<?php
/**
 * Revisión adversaria de R1 (restaurar desde un destino remoto y sincronizar): cada hallazgo, con el motor real y el
 * WebDAV simulado de tests/lib/destination-fakes.php.
 *   C1  cinco muertes seguidas del proceso en `receive`, cada una con avance en disco: ninguna gasta un intento, el
 *       fragmento baja a la mitad tras cada una y la descarga termina con el SHA-256 correcto;
 *   C2  ninguna petición de fragmento espera más de lo que le queda al tramo;
 *   C4  `restore X --from=webdav` con otro X «sin registro» en el almacén trae y restaura el del destino, nunca el local;
 *   C5  traer otra vez lo que ya está no lo duplica, y lo traído «sin registro» que nadie usa se borra a los 7 días;
 *   C7  una muerte justo después del `rename()` de `place` (con el nombre ocupado) no descarga otra vez ni duplica;
 *   C9  una fila sin SHA-256 no da por verificada una copia alterada: se trae como «sin registro» y se comprueba;
 *   C10 el índice recalcula `registered` con el historial;
 *   C11 la extensión en el almacén sale del contenido;
 *   P1  sincronizar sin tiempo deja el destino pendiente sin llamarlo ni perder el índice.
 */
$root = sys_get_temp_dir() . '/digitalisimo-remote-review-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
require __DIR__ . '/lib/destination-fakes.php';
digi_make_wordpress( $root . '/public' );
// 2 MB que no se comprimen: unos 32 fragmentos de 64 KiB.
file_put_contents( $root . '/public/wp-content/foto.bin', random_bytes( 2000000 ) );
function absint( $value ) { return abs( (int) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function wp_delete_file( $file ) { return @unlink( $file ); }
function size_format( $bytes ) { return $bytes . ' B'; }
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function checked( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? ' checked="checked"' : ''; }
function disabled( $a, $b = true, $echo = true ) { return $a === $b ? ' disabled="disabled"' : ''; }
$GLOBALS['filters'] = array();
function apply_filters( $hook, $value, ...$args ) { return isset( $GLOBALS['filters'][ $hook ] ) ? call_user_func( $GLOBALS['filters'][ $hook ], $value, ...$args ) : $value; }
$GLOBALS['filters']['digitalisimo_backups_fetch_chunk'] = function() { return 65536; };
// Sin esperas entre reintentos: la prueba hace avanzar la cola a mano.
$GLOBALS['filters']['digitalisimo_backups_job_backoff'] = function() { return 0; };

$B = 'Digitalisimo_Backups';
$ns = 'digitalisimo-backups/v1';
$store = $root . '/private';
$password = 'WebDav-Clave-Remota-7';
$dav = new Bk_Fake_Webdav( 'https://dav.ejemplo.test/remote.php/dav/files/ana/', 'ana', $password );
Digitalisimo_Backups_Http::$transport = $dav;
Digitalisimo_Backups_Http::$resolver = function( $host ) { return array( '203.0.113.10' ); };
$fetch_job = function( $name ) {
	$found = null;
	foreach ( Digitalisimo_Backups_Job_Store::all() as $job ) if ( $name === ( $job['spec']['fetch']['name'] ?? '' ) ) $found = $job;
	return $found;
};
/** Una muerte real deja el arrendamiento puesto y el punto de control sin tocar: el paso pierde $current y lanza. */
$die = function() {
	$p = new ReflectionProperty( 'Digitalisimo_Backups_Jobs', 'current' );
	if ( PHP_VERSION_ID < 80100 ) $p->setAccessible( true );
	$p->setValue( null, null );
	throw new Error( 'muerte simulada del proceso' );
};
/** Como el detector y WP-Cron tras una muerte: reconoce el proceso muerto y da otro tick, hasta que el trabajo termina. */
$drive = function( $job, $limit = 60 ) {
	for ( $i = 0; $i < $limit && $job && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ); $i++ ) {
		Digitalisimo_Backups_Jobs::recover_if_idle();
		$job = Digitalisimo_Backups_Job_Store::read( $job['id'] );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		$job['retry_at'] = 0;
		Digitalisimo_Backups_Job_Store::write( $job );
		Digitalisimo_Backups_Jobs::tick();
		$job = Digitalisimo_Backups_Job_Store::read( $job['id'] );
	}
	return $job;
};
$gets_of = function( $name ) use ( $dav ) { return count( array_filter( $dav->log, function( $e ) use ( $name ) { return 'GET' === $e['method'] && false !== strpos( rawurldecode( $e['url'] ), $name ); } ) ); };
$copies = function( $sha ) use ( $store ) { $out = array(); foreach ( glob( $store . '/*.zip' ) ?: array() as $path ) if ( $sha === hash_file( 'sha256', $path ) ) $out[] = basename( $path ); return $out; };
$history = function() use ( $B ) { return (array) get_option( $B::HISTORY, array() ); };

try {
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	Digitalisimo_Backups_Cli::register();
	$query = digi_admin_post( 'save', $B::ACTION_SAVE, array( 'digitalisimo_backups' => array( 'destination' => 'local', 'retention' => '100', 'webdav_url' => 'https://dav.ejemplo.test/remote.php/dav/files/ana/', 'webdav_user' => 'ana', 'webdav_password' => $password, 'webdav_path' => '/respaldos' ) ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ), 'No se guardó la conexión WebDAV: ' . json_encode( $query ) );
	$created = digi_backup( array( 'destination' => 'webdav' ) );
	digi_assert( ! is_wp_error( $created ), 'No se creó el respaldo: ' . ( is_wp_error( $created ) ? $created->get_error_message() : '' ) );
	$file = $created['file'];
	$bytes = file_get_contents( $store . '/' . $file );
	$sha = hash( 'sha256', $bytes );
	/** Otro respaldo con su propio contenido (otro SHA-256), sin fila ni archivo en este sitio: el «de otro sitio». */
	$foreign = function( $blogname ) use ( $B, $store, $history ) {
		$GLOBALS['wpdb']->set( 'blogname', $blogname );
		$made = digi_backup();
		digi_assert( ! is_wp_error( $made ), 'No se creó un respaldo auxiliar.' );
		$data = file_get_contents( $store . '/' . $made['file'] );
		update_option( $B::HISTORY, array_values( array_filter( $history(), function( $row ) use ( $made ) { return $made['file'] !== $row['file']; } ) ) );
		unlink( $store . '/' . $made['file'] );
		$GLOBALS['wpdb']->set( 'blogname', 'Sitio de prueba' );
		return $data;
	};
	$other_bytes = $foreign( 'Sitio de prueba' );
	$osha = hash( 'sha256', $other_bytes );
	$impostor_bytes = $foreign( 'Sitio IMPOSTOR' );
	$y_bytes = $foreign( 'Sitio de prueba' );
	$ysha = hash( 'sha256', $y_bytes );
	$fake_bytes = $foreign( 'Sitio de prueba' );

	// C1 y C2: cinco muertes, cada una tras anexar dos fragmentos; ningún GET espera más que lo que le queda al tramo.
	unlink( $store . '/' . $file );
	$gets = 0;
	$deaths = array();
	$timeouts = array();
	$lengths = array();
	$after = array();
	$dav->faults['get'] = function( $request, $data ) use ( &$gets, &$deaths, &$timeouts, &$lengths, &$after, $die, $store ) {
		$timeouts[] = (int) $request['timeout'];
		if ( preg_match( '/^bytes=(\d+)-(\d+)$/', (string) ( $request['headers']['Range'] ?? '' ), $m ) ) $lengths[] = (int) $m[2] - (int) $m[1] + 1;
		if ( count( $after ) < count( $deaths ) ) $after[] = end( $lengths );
		if ( count( $deaths ) >= 5 || 0 !== ( ++$gets ) % 3 ) return null;
		$parts = glob( $store . '/jobs/*/staging/remote.part' ) ?: array();
		clearstatcache();
		$job = $parts ? Digitalisimo_Backups_Job_Store::read( basename( dirname( dirname( $parts[0] ) ) ) ) : null;
		$deaths[] = array( 'part' => $parts ? filesize( $parts[0] ) : -1, 'attempts' => (int) ( $job['attempts']['receive'] ?? 0 ), 'chunk' => max( $lengths ) );
		$die();
	};
	$B::service_fetch( $file, array( 'drive_cli' => false ) );
	$job = $drive( $fetch_job( $file ) );
	unset( $dav->faults['get'] );
	digi_assert( 5 === count( $deaths ) && $job && 'success' === $job['status'] && $sha === hash_file( 'sha256', $store . '/' . $file ), 'Cinco muertes con avance no dejaron terminar la descarga: ' . json_encode( array( 'estado' => $job['status'] ?? null, 'error' => $job['error']['message'] ?? null, 'muertes' => $deaths ) ) );
	digi_assert( 0 === max( array_column( $deaths, 'attempts' ) ) && $deaths[4]['part'] > $deaths[0]['part'], 'Una muerte con avance gastó un intento: ' . json_encode( $deaths ) );
	foreach ( $deaths as $i => $death ) digi_assert( isset( $after[ $i ] ) && $after[ $i ] <= max( 65536, $death['chunk'] / 2 ), 'Tras una muerte el fragmento no bajó a la mitad: ' . json_encode( array( 'muertes' => $deaths, 'primero_después' => $after ) ) );
	digi_assert( $timeouts && max( $timeouts ) <= 20 && min( $timeouts ) >= 5, 'Un GET de fragmento no respeta el tiempo del tramo: ' . json_encode( array_values( array_unique( $timeouts ) ) ) );

	// C4: `restore X --from=webdav` con otro X «sin registro» en el almacén. En WebDAV, X es el respaldo de este sitio
	// («Sitio de prueba»); en el almacén, un X distinto («Sitio IMPOSTOR»).
	$other = 'digitalisimo-backup-otro-sitio-test-20261101-101010-0a1b2c3d.zip';
	$dav->files[ 'respaldos/' . $other ] = $other_bytes;
	file_put_contents( $store . '/' . $other, $impostor_bytes );
	$GLOBALS['wpdb']->set( 'blogname', 'Antes de restaurar' );
	$dav->log = array();
	list( $code, $out ) = digi_wp( 'digitalisimo-backups', 'restore', array( $other ), array( 'from' => 'webdav', 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	$restored_from = preg_match( '/Restauración terminada desde (\S+\.(?:zip|dgbk))\./', implode( "\n", $out ), $m ) ? $m[1] : '';
	digi_assert( 0 === $code && 'Sitio de prueba' === $GLOBALS['wpdb']->value( 'blogname' ) && $gets_of( $other ) > 0 && $restored_from !== $other && $osha === hash_file( 'sha256', $store . '/' . $restored_from ) && $impostor_bytes === file_get_contents( $store . '/' . $other ), '`restore X --from` restauró el X local en vez del del destino: ' . json_encode( array( 'salida' => $out, 'blogname' => $GLOBALS['wpdb']->value( 'blogname' ) ) ) );

	// C5: traer otra vez el mismo «sin registro» no lo duplica (tampoco por REST); se reconoce por tamaño y SHA-256.
	$before = $copies( $osha );
	list( $c1, $r1 ) = digi_rest( 'POST', $ns . '/destinations/webdav/backups/' . $other . '/fetch' );
	list( $c2, $r2 ) = digi_rest( 'POST', $ns . '/destinations/webdav/backups/' . $other . '/fetch' );
	list( $c3, $out ) = digi_wp( 'digitalisimo-backups destinations', 'fetch', array( $other ), array( 'from' => 'webdav' ) );
	digi_assert( 200 === $c1 && 200 === $c2 && 0 === $c3 && $before === $copies( $osha ) && $restored_from === $r1['file'] && ! empty( $r2['deduplicated'] ) && false !== strpos( implode( "\n", $out ), 'No se guardó otra copia' ), 'Traer otra vez el mismo respaldo lo duplicó: ' . json_encode( array( 'antes' => $before, 'después' => $copies( $osha ), 'r1' => $r1['file'] ?? null, 'r2' => $r2 ) ) );
	// Retención: pasados 7 días, lo traído «sin registro» que nadie usa se borra; nunca un archivo subido a mano.
	file_put_contents( $store . '/digitalisimo-upload-20261101-000000-aaaaaaaa.zip', $impostor_bytes );
	$records = get_option( $B::FETCHED, array() );
	digi_assert( isset( $records[ $restored_from ] ), 'Lo traído «sin registro» no quedó anotado para su retención: ' . json_encode( array_keys( (array) $records ) ) );
	digi_assert( array() === $B::fetched_cleanup( time() + 6 * 86400 ) && is_file( $store . '/' . $restored_from ), 'La retención borró antes de tiempo.' );
	$removed = $B::fetched_cleanup( time() + 8 * 86400 );
	digi_assert( array( $restored_from ) === $removed && ! is_file( $store . '/' . $restored_from ) && is_file( $store . '/digitalisimo-upload-20261101-000000-aaaaaaaa.zip' ) && is_file( $store . '/' . $other ) && is_file( $store . '/' . $file ), 'La retención de lo traído no borró sólo lo suyo: ' . json_encode( $removed ) );
	digi_assert( isset( $GLOBALS['digi']['actions'][ Digitalisimo_Backups_Jobs::HOOK_WATCHDOG ] ) && in_array( array( $B, 'fetched_cleanup' ), $GLOBALS['digi']['actions'][ Digitalisimo_Backups_Jobs::HOOK_WATCHDOG ], true ), 'La retención no está en el vigilante horario.' );

	// C7: muerte justo después del rename() de `place` con el nombre ocupado: no se descarga otra vez ni se duplica.
	$y = 'digitalisimo-backup-otro-sitio-test-20261105-101010-0e1f2a3b.zip';
	$dav->files[ 'respaldos/' . $y ] = $y_bytes;
	file_put_contents( $store . '/' . $y, $impostor_bytes );
	$died = false;
	$gets_at_death = -1;
	Digitalisimo_Backups_Jobs::$phase_runner = function( $phase, $job ) use ( &$died, &$gets_at_death, $die, $gets_of, $y ) {
		$outcome = Digitalisimo_Backups_Jobs::is_restore( $job ) ? Digitalisimo_Backups_Restore_Job::run_phase( $phase, $job ) : Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
		if ( 'place' === $phase && ! $died && 'fetch' === ( $job['spec']['kind'] ?? '' ) ) { $died = true; $gets_at_death = $gets_of( $y ); $die(); }
		return $outcome;
	};
	$dav->log = array();
	$B::service_remote_fetch( 'webdav', $y, array( 'drive_cli' => false ) );
	$job = $drive( $fetch_job( $y ) );
	Digitalisimo_Backups_Jobs::$phase_runner = null;
	$mine = $copies( $ysha );
	digi_assert( $died && 'success' === $job['status'] && 1 === count( $mine ) && $mine[0] === $job['result']['file'] && $mine[0] !== $y && $gets_at_death > 0 && $gets_of( $y ) === $gets_at_death, 'Tras morir justo después del rename() se descargó otra vez o quedó duplicado: ' . json_encode( array( 'estado' => $job['status'], 'copias' => $mine, 'GET' => $gets_of( $y ) ) ) );

	// C9: una fila sin SHA-256 (antigua) y la copia remota alterada: no entra con el nombre de la fila ni se dice «verificada».
	$rows = $history();
	$saved_rows = $rows;
	foreach ( $rows as $i => $row ) if ( $file === $row['file'] ) unset( $rows[ $i ]['sha256'], $rows[ $i ]['remote']['sha256'] );
	update_option( $B::HISTORY, $rows );
	unlink( $store . '/' . $file );
	$dav->files[ 'respaldos/' . $file ][ 90000 ] = chr( ord( $dav->files[ 'respaldos/' . $file ][ 90000 ] ) ^ 1 );
	$legacy = $B::service_fetch( $file );
	digi_assert( is_wp_error( $legacy ) && ! is_file( $store . '/' . $file ) && false === strpos( $legacy->get_error_message(), 'verificada por SHA-256' ), 'Una fila sin SHA-256 dejó entrar una copia alterada: ' . ( is_wp_error( $legacy ) ? $legacy->get_error_message() : json_encode( $legacy ) ) );
	$dav->files[ 'respaldos/' . $file ] = $bytes;
	$untrusted = $B::service_fetch( $file );
	digi_assert( ! is_wp_error( $untrusted ) && $file !== $untrusted['file'] && false === $untrusted['registered'] && false === strpos( $untrusted['message'], 'verificada por el SHA-256' ) && 'RESTAURAR SIN FIRMA' === $untrusted['confirmation'], 'Sin SHA-256 en la fila, la copia íntegra no entró como «sin registro»: ' . json_encode( is_wp_error( $untrusted ) ? $untrusted->get_error_message() : $untrusted ) );
	update_option( $B::HISTORY, $saved_rows );
	digi_assert( ! is_wp_error( $B::service_fetch( $file ) ) && $sha === hash_file( 'sha256', $store . '/' . $file ), 'Con el SHA-256 de vuelta, la copia de la fila no se trajo con su nombre.' );

	// C10: el índice recalcula `registered` con el historial.
	$B::service_sync();
	update_option( $B::HISTORY, array_values( array_filter( $saved_rows, function( $row ) use ( $file ) { return $file !== $row['file']; } ) ) );
	unlink( $store . '/' . $file );
	$item = null;
	foreach ( $B::service_remote_index()['items'] as $row ) if ( $file === $row['name'] ) $item = $row;
	digi_assert( $item && false === $item['registered'] && 'importable' === $item['state'] && false === strpos( $item['label'], 'De este sitio' ), 'Sin fila, el índice sigue diciendo «De este sitio»: ' . json_encode( $item ) );
	update_option( $B::HISTORY, $saved_rows );

	// C11: un ZIP con nombre .dgbk entra como .zip (la extensión sale del contenido).
	$fake = 'digitalisimo-backup-otro-sitio-test-20261104-101010-0d1e2f3a.dgbk';
	$dav->files[ 'respaldos/' . $fake ] = $fake_bytes;
	list( $code, $res ) = digi_rest( 'POST', $ns . '/destinations/webdav/backups/' . $fake . '/fetch' );
	digi_assert( 200 === $code && 'zip' === $res['container'] && '.zip' === substr( (string) $res['file'], -4 ) && empty( $res['deduplicated'] ), 'Un ZIP con nombre .dgbk entró con la extensión del nombre: ' . json_encode( $res ) );

	// P1: sin tiempo en la petición, sincronizar deja el destino pendiente, sin llamarlo y sin perder lo sincronizado.
	$synced = $B::service_sync();
	$kept = get_option( $B::REMOTE_INDEX );
	$GLOBALS['filters']['digitalisimo_backups_request_limit'] = function() { return 30; };
	$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 100;
	$calls = count( $dav->log );
	try {
		$late = $B::service_sync();
	} finally {
		unset( $GLOBALS['filters']['digitalisimo_backups_request_limit'], $_SERVER['REQUEST_TIME_FLOAT'] );
	}
	digi_assert( ! is_wp_error( $late ) && array() === $late['destinations'] && 'webdav' === ( $late['pending'][0]['destination'] ?? '' ) && count( $dav->log ) === $calls && $kept === get_option( $B::REMOTE_INDEX ), 'Sincronizar sin tiempo llamó al destino o perdió el índice: ' . json_encode( is_wp_error( $late ) ? $late->get_error_message() : $late ) );

	echo "OK: revisión de R1 (C1, C2, C4, C5, C7, C9, C10, C11 y P1)\n";
} finally {
	Digitalisimo_Backups_Jobs::$phase_runner = null;
	digi_rmtree( $root );
}
