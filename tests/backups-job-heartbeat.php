<?php
/**
 * El latido no entra en la instantánea de la base de datos y el detector distingue un proceso muerto (cerrojo libre)
 * de uno vivo (cerrojo tomado aunque su latido sea viejo) y de otro servidor (sólo tras 30 min sin latido, con fencing).
 */
require __DIR__ . '/backups-jobs/env.php';
bk_no_backoff();
$GLOBALS['bk_filters']['digitalisimo_backups_job_heartbeat'] = function() { return 0; };
$db = $GLOBALS['wpdb'];

// D1: durante la exportación, el avance se ve en el almacén y no hay ninguna lectura ni escritura de opciones.
foreach ( array( 'InnoDB' => 'transaction', 'MyISAM' => 'lock' ) as $engine => $mode ) {
	$db->engine = $engine;
	$db->snapshot_violations = array();
	$seen = array();
	$job = bk_manual();
	$db->on_select = function() use ( $job, $db, &$seen ) {
		if ( ! $db->in_snapshot ) return;
		$state = json_decode( file_get_contents( bk_storage() . '/jobs/' . $job['id'] . '/job.json' ), true );
		$seen[] = array( $state['rev'], $state['progress']['message'], $state['lease']['heartbeat_at'] ?? 0 );
	};
	bk_run_until_done( $job['id'] );
	$db->on_select = null;
	$done = bk_job( $job['id'] );
	bk_assert( 'success' === $done['status'] && $mode === $done['checkpoint']['database']['mode'], 'El respaldo con ' . $engine . ' no terminó con su modo de consistencia.' );
	bk_assert( ! $db->snapshot_violations, 'Se usaron opciones de la base de datos dentro de la instantánea (' . $engine . '): ' . implode( ', ', $db->snapshot_violations ) );
	bk_assert( count( $seen ) >= 2 && $seen[0][0] < end( $seen )[0] && false !== strpos( end( $seen )[1], 'wp_posts' ), 'El latido no avanzó en el almacén durante la instantánea (' . $engine . ').' );
	$inside = false;
	foreach ( $db->queries as $query ) {
		if ( 0 === strpos( $query, 'START TRANSACTION' ) || 0 === strpos( $query, 'LOCK TABLES' ) ) $inside = true;
		elseif ( in_array( $query, array( 'COMMIT', 'ROLLBACK', 'UNLOCK TABLES' ), true ) ) $inside = false;
		elseif ( $inside && ! preg_match( '/^(SHOW CREATE TABLE|SHOW INDEX FROM|SELECT \* FROM) `/', $query ) ) bk_fail( 'Consulta ajena a la exportación dentro de la instantánea: ' . $query );
	}
	$db->queries = array();
}
$db->engine = 'InnoDB';

// Muerto con el cerrojo libre: se reconoce en el siguiente tick, se descarta lo parcial y se cuenta un intento.
$dead = bk_manual( 'local', 'muerto' );
bk_kill_in( $dead['id'], 'database', array( 'database.dddddddd.sql.part' => 'INSERT a medias' ) );
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = function() { return 600; };
$result = Digitalisimo_Backups_Jobs::tick();
$job = bk_job( $dead['id'] );
bk_assert( 'worked' !== $result['result'] && 'queued' === $job['status'] && 'interrupted' === $job['error']['code'] && 1 === $job['attempts']['database'] && null === $job['lease'], 'El proceso muerto no se reconoció como interrumpido.' );
bk_assert( ! glob( bk_storage() . '/jobs/' . $dead['id'] . '/staging/database.*' ), 'Quedó el SQL parcial del proceso muerto.' );
$events = Digitalisimo_Backups_Job_Store::events( $dead['id'] );
bk_assert( 'interrupted' === end( $events )['type'], 'La interrupción no quedó en el registro del trabajo.' );
bk_no_backoff();

// Vivo: otro proceso tiene el cerrojo; aunque su latido sea viejo, nadie toma su trabajo ni lo marca.
$alive = bk_manual( 'local', 'vivo' );
bk_kill_in( $alive['id'], 'pack' );
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $alive ) { $job = Digitalisimo_Backups_Job_Store::read( $alive['id'] ); $job['lease']['heartbeat_at'] = time() - 700; Digitalisimo_Backups_Job_Store::write( $job ); } );
$holder = fopen( bk_storage() . '/.operation.lock', 'c' );
bk_assert( flock( $holder, LOCK_EX | LOCK_NB ), 'La prueba no pudo simular el proceso vivo.' );
$rev = bk_job( $alive['id'] )['rev'];
$GLOBALS['bk_cron'] = array(); // WP-Cron retira el evento antes de llamar al tick.
$busy = Digitalisimo_Backups_Jobs::tick();
bk_assert( 'busy' === $busy['result'] && ! Digitalisimo_Backups_Jobs::recover_if_idle() && $rev === bk_job( $alive['id'] )['rev'], 'Se tocó el trabajo de un proceso vivo.' );
// El tick que encontró el cerrojo ocupado deja otro programado, no para ya mismo: si el dueño muere, alguien lo reconoce.
$next_tick = wp_next_scheduled( Digitalisimo_Backups_Jobs::HOOK_TICK );
bk_assert( false !== $next_tick && $next_tick >= time() + 55, 'Un tick con el cerrojo ocupado no dejó otro programado.' );
$view = Digitalisimo_Backups_Jobs::view( bk_job( $alive['id'] ) );
bk_assert( $view['stalled'] && $view['running'] && false !== strpos( $view['detail'], 'Sin señal desde' ), 'Un latido de hace más de 10 min no se anunció como «sin señal».' );
flock( $holder, LOCK_UN );
fclose( $holder );
$GLOBALS['bk_cron'] = array();
Digitalisimo_Backups_Jobs::recover_if_idle();
bk_assert( 'interrupted' === bk_job( $alive['id'] )['error']['code'], 'Al liberarse el cerrojo, el trabajo no se reconoció como interrumpido.' );
$next_tick = wp_next_scheduled( Digitalisimo_Backups_Jobs::HOOK_TICK );
bk_assert( false !== $next_tick && $next_tick <= time() + 1, 'El trabajo devuelto a la cola por el detector quedó sin tick programado.' );
bk_ready( $dead['id'] );
bk_run_until_done( $dead['id'] );
bk_run_until_done( $alive['id'] );
bk_assert( 'success' === bk_job( $dead['id'] )['status'] && 'success' === bk_job( $alive['id'] )['status'], 'Los trabajos interrumpidos no terminaron después.' );

// Otro servidor (almacén compartido): con latido reciente no se toca; tras 30 min se toma con un dueño nuevo.
$remote = bk_manual( 'local', 'otro-servidor' );
bk_kill_in( $remote['id'], 'pack' );
$set_lease = function( $age ) use ( $remote ) {
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $remote, $age ) { $job = Digitalisimo_Backups_Job_Store::read( $remote['id'] ); $job['lease']['host'] = 'otro-servidor'; $job['lease']['owner'] = str_repeat( 'e', 32 ); $job['lease']['heartbeat_at'] = time() - $age; Digitalisimo_Backups_Job_Store::write( $job ); } );
};
$set_lease( 1200 );
$rev = bk_job( $remote['id'] )['rev'];
$idle = Digitalisimo_Backups_Jobs::tick();
bk_assert( 'idle' === $idle['result'] && $rev === bk_job( $remote['id'] )['rev'], 'Se tomó el trabajo de otro servidor con latido de hace 20 min.' );
$set_lease( 1900 );
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = function() { return 600; };
Digitalisimo_Backups_Jobs::tick();
$job = bk_job( $remote['id'] );
bk_assert( 'takeover' === $job['error']['code'] && null === $job['lease'] && 'queued' === $job['status'], 'El trabajo de otro servidor sin latido no se tomó.' );
// Fencing: el dueño anterior ya no puede escribir; aborta en su siguiente latido sin tocar el estado.
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $remote ) { $job = Digitalisimo_Backups_Job_Store::read( $remote['id'] ); $job['lease'] = array( 'owner' => str_repeat( 'f', 32 ), 'host' => Digitalisimo_Backups_Jobs::host(), 'pid' => 1, 'since' => time(), 'heartbeat_at' => time() ); Digitalisimo_Backups_Job_Store::write( $job ); } );
$current = new ReflectionProperty( 'Digitalisimo_Backups_Jobs', 'current' );
if ( PHP_VERSION_ID < 80100 ) $current->setAccessible( true );
$current->setValue( null, array( 'id' => $remote['id'], 'owner' => str_repeat( 'e', 32 ), 'phase' => 'pack', 'written' => 0, 'lost' => false, 'abort' => false, 'remote' => false, 'inline' => false ) );
$rev = bk_job( $remote['id'] )['rev'];
try {
	Digitalisimo_Backups_Jobs::progress( 50, 'Escritura del dueño anterior', true );
	bk_fail( 'El dueño anterior escribió sobre el trabajo tomado.' );
} catch ( Digitalisimo_Backups_Job_Lease_Lost $e ) {
	// Esperado.
}
bk_assert( Digitalisimo_Backups_Jobs::should_abort() && $rev === bk_job( $remote['id'] )['rev'] && str_repeat( 'f', 32 ) === bk_job( $remote['id'] )['lease']['owner'], 'El rechazo del dueño anterior alteró el trabajo.' );
$current->setValue( null, null );

// Apagado de PHP a mitad de un paso: se anota en el almacén y el trabajo queda para reintentar.
$cut = bk_manual( 'local', 'apagado' );
bk_kill_in( $cut['id'], 'pack', array( 'package.cccccccc.zip' => 'PK a medias' ) );
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $cut ) { $job = Digitalisimo_Backups_Job_Store::read( $cut['id'] ); $job['lease']['owner'] = str_repeat( 'c', 32 ); Digitalisimo_Backups_Job_Store::write( $job ); } );
$current->setValue( null, array( 'id' => $cut['id'], 'owner' => str_repeat( 'c', 32 ), 'phase' => 'pack', 'written' => 0, 'lost' => false, 'abort' => false, 'remote' => false, 'inline' => false ) );
Digitalisimo_Backups_Jobs::on_shutdown();
$job = bk_job( $cut['id'] );
bk_assert( 'queued' === $job['status'] && in_array( $job['error']['code'], array( 'interrupted', 'fatal' ), true ) && 1 === $job['attempts']['pack'] && ! glob( bk_storage() . '/jobs/' . $cut['id'] . '/staging/package.*' ), 'El apagado a mitad de paso no dejó el trabajo listo para reintentar.' );

bk_cleanup();
echo "DIGITALÍSIMO Backups: latido fuera de la instantánea, detector de procesos muertos, vivos y de otro servidor, y fencing verificados.\n";
