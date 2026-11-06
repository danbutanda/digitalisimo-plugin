<?php
/** El estado de los trabajos vive en el almacén privado: escritura atómica, lectura validada, poda y vista pública filtrada. */
require __DIR__ . '/backups-jobs/env.php';

$job = bk_manual();
$id = $job['id'];
$root = bk_storage() . '/jobs';
$dir = $root . '/' . $id;
bk_assert( Digitalisimo_Backups_Job_Store::valid_id( $id ) && is_file( $dir . '/job.json' ), 'El trabajo no se creó en jobs/<uuid>/job.json.' );
bk_assert( 'queued' === $job['status'] && 'preflight' === $job['phase'] && 1 === $job['rev'], 'El trabajo nuevo no empieza pendiente en la primera fase.' );
if ( DIRECTORY_SEPARATOR === '/' ) {
	clearstatcache();
	bk_assert( 0700 === ( fileperms( $root ) & 0777 ) && 0700 === ( fileperms( $dir ) & 0777 ), 'Las carpetas de trabajos no son 0700.' );
	bk_assert( 0600 === ( fileperms( $dir . '/job.json' ) & 0777 ), 'job.json no es 0600.' );
}

// Escritura atómica: un temporal suelto de una escritura interrumpida no cuenta y no reemplaza el estado.
file_put_contents( $dir . '/job.json.tmp-abcdef123456', '{"a medias' );
bk_assert( 'queued' === bk_job( $id )['status'] && array( $id ) === Digitalisimo_Backups_Job_Store::ids(), 'Un temporal suelto alteró el estado o la lista.' );
$written = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id ) { $job = Digitalisimo_Backups_Job_Store::read( $id ); $job['progress']['message'] = 'Mensaje con ñ y "comillas"'; return Digitalisimo_Backups_Job_Store::write( $job ); } );
bk_assert( 2 === $written['rev'] && 'Mensaje con ñ y "comillas"' === bk_job( $id )['progress']['message'], 'La escritura no incrementó la revisión o perdió el texto.' );
bk_assert( 1 === count( glob( $dir . '/job.json.tmp-*' ) ), 'La escritura atómica dejó temporales propios.' );
touch( $dir . '/job.json.tmp-abcdef123456', time() - 7200 );
Digitalisimo_Backups_Job_Store::prune();
bk_assert( ! glob( $dir . '/job.json.tmp-*' ), 'La poda no retiró el temporal suelto de más de una hora.' );

// Identificadores: nunca una ruta.
foreach ( array( '../' . $id, $id . '/..', 'abc', '', strtoupper( $id ) ) as $bad ) {
	try {
		Digitalisimo_Backups_Job_Store::directory( $bad );
		bk_fail( 'Se aceptó el identificador ' . $bad );
	} catch ( InvalidArgumentException $e ) {
		// Esperado.
	}
	bk_assert( null === Digitalisimo_Backups_Jobs::get( $bad ), 'get() aceptó un identificador inválido.' );
}
$cancel = Digitalisimo_Backups_Jobs::cancel( '../../etc/passwd' );
bk_assert( empty( $cancel['ok'] ) && 'invalid' === $cancel['code'], 'cancel() aceptó una ruta como identificador.' );

// Un estado ilegible se aparta y el trabajo queda fallido, sin área de trabajo ni respaldo registrado.
$broken = bk_manual( 'local', 'politica-rota' );
$staging = Digitalisimo_Backups_Job_Store::staging( $broken['id'] );
file_put_contents( $staging . '/database.x.sql', 'parcial' );
file_put_contents( $root . '/' . $broken['id'] . '/job.json', '{"format":"digitalisimo-backups-job/v1","id":' );
$read = bk_job( $broken['id'] );
bk_assert( 'failed' === $read['status'] && 'corrupt' === $read['error']['code'] && true === $read['error']['permanent'], 'Un job.json ilegible no se convirtió en fallido.' );
bk_assert( 1 === count( glob( $root . '/' . $broken['id'] . '/job.json.bad-*' ) ) && ! is_dir( $staging ), 'El estado ilegible no se apartó o quedó el área de trabajo.' );
bk_assert( ! bk_history(), 'Un trabajo ilegible registró un respaldo.' );
// Uno de otro trabajo (id distinto dentro del JSON) tampoco se acepta.
$alien = bk_manual( 'local', 'otra' );
$copy = json_decode( file_get_contents( $dir . '/job.json' ), true );
file_put_contents( $root . '/' . $alien['id'] . '/job.json', json_encode( $copy ) );
bk_assert( 'corrupt' === bk_job( $alien['id'] )['error']['code'], 'Se aceptó un estado copiado de otro trabajo.' );

// Registro de eventos acotado: conserva el final.
for ( $i = 0; $i < 2200; $i++ ) Digitalisimo_Backups_Job_Store::event( $id, 'relleno', array( 'n' => $i, 'texto' => str_repeat( 'x', 100 ) ) );
clearstatcache();
$events = Digitalisimo_Backups_Job_Store::events( $id );
bk_assert( filesize( $dir . '/events.jsonl' ) <= Digitalisimo_Backups_Job_Store::EVENTS_MAX && 2199 === end( $events )['n'], 'El registro de eventos creció sin límite o perdió el final.' );

// Vista pública: sin rutas, arrendamiento, sesión remota ni usuario.
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id ) {
	$job = Digitalisimo_Backups_Job_Store::read( $id );
	$job['lease'] = array( 'owner' => str_repeat( 'a', 32 ), 'host' => 'servidor-secreto', 'pid' => 4242, 'since' => time(), 'heartbeat_at' => time() );
	$job['checkpoint']['transfer'] = array( 'session' => 'https://upload.test/sesion-privada' );
	$job['checkpoint']['database'] = array( 'file' => 'database.aaaa.sql' );
	Digitalisimo_Backups_Job_Store::write( $job );
} );
$view = Digitalisimo_Backups_Jobs::view( bk_job( $id ) );
$json = json_encode( $view, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
foreach ( array( bk_storage(), 'servidor-secreto', str_repeat( 'a', 32 ), 'sesion-privada', 'database.aaaa.sql', '"user_id"', '"spec"', '"lease"', '"checkpoint"' ) as $secret ) bk_assert( false === strpos( $json, $secret ), 'La vista pública expone ' . $secret );
bk_assert( '' === $view['file'] && $view['running'] && $view['can_cancel'], 'La vista pública no describe un trabajo en curso.' );
foreach ( Digitalisimo_Backups_Jobs::views() as $item ) bk_assert( false === strpos( json_encode( $item, JSON_UNESCAPED_SLASHES ), bk_storage() ), 'views() expone rutas del almacén.' );

// Poda: se conservan los últimos 20 terminados o los de 30 días; nunca los activos.
$now = time();
$terminal = array();
for ( $i = 0; $i < 26; $i++ ) {
	$old = bk_manual( 'local', 'poda-' . $i );
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $old, $now, $i ) {
		$job = Digitalisimo_Backups_Job_Store::read( $old['id'] );
		$job['status'] = 'success';
		$job['finished_at'] = $now - ( $i < 24 ? 40 * 86400 + $i : 86400 );
		Digitalisimo_Backups_Job_Store::write( $job );
	} );
	$terminal[] = $old['id'];
}
mkdir( $root . '/' . Digitalisimo_Backups_Job_Store::new_id(), 0700 );
$orphan = $root . '/' . Digitalisimo_Backups_Job_Store::new_id();
mkdir( $orphan, 0700 );
touch( $orphan, $now - 7200 );
Digitalisimo_Backups_Job_Store::prune();
$left = Digitalisimo_Backups_Job_Store::ids();
$kept = array_values( array_intersect( $terminal, $left ) );
bk_assert( in_array( $id, $left, true ), 'La poda borró un trabajo activo.' );
bk_assert( in_array( $terminal[24], $kept, true ) && in_array( $terminal[25], $kept, true ), 'La poda borró trabajos de menos de 30 días.' );
$failed_kept = count( array_filter( Digitalisimo_Backups_Job_Store::all(), function( $job ) { return Digitalisimo_Backups_Job_Store::is_terminal( $job ); } ) );
bk_assert( 20 === $failed_kept, 'La poda no dejó los 20 terminados más recientes (quedan ' . $failed_kept . ').' );
bk_assert( ! is_dir( $orphan ), 'La poda no retiró una carpeta sin estado de más de una hora.' );
bk_assert( 1 === count( array_filter( glob( $root . '/*', GLOB_ONLYDIR ), function( $path ) { return ! is_file( $path . '/job.json' ); } ) ), 'La poda retiró una carpeta reciente sin estado (creación en curso).' );

bk_cleanup();
echo "DIGITALÍSIMO Backups: almacén de trabajos atómico, validado, podado y sin datos internos en la vista pública.\n";
