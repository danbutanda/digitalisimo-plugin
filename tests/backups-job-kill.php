<?php
/**
 * Proceso real muerto con SIGKILL a mitad de la exportación: mientras vive, su cerrojo impide tocar su trabajo; al
 * morir, el kernel libera el cerrojo, el detector lo reconoce, descarta el SQL parcial y el trabajo termina sin
 * repetir nada más. Después, un error fatal real (memoria agotada) dentro de la instantánea: el apagado de PHP lo anota
 * y devuelve el trabajo a la cola sin esperar al detector. Necesita proc_open y un PHP del sistema (la CI); en php-wasm
 * se omite y la cubre backups-job-resume.php con muertes simuladas. Las fases se pueden correr por separado con
 * --fase=… (preparar, vivo, tras-matar; preparar-fatal, tras-fatal) para ensayarla con procesos de php-wasm.
 */
$phase = '';
$root = '';
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--fase=' ) ) $phase = substr( $arg, 7 );
	elseif ( 0 === strpos( $arg, '--raiz=' ) ) $root = substr( $arg, 7 );
}
if ( $root ) $GLOBALS['bk_root'] = $root;
require __DIR__ . '/backups-jobs/env.php';
bk_no_backoff();

function kill_job() {
	$jobs = Digitalisimo_Backups_Jobs::all();
	bk_assert( 1 === count( $jobs ), 'La prueba esperaba un solo trabajo.' );
	return $jobs[0];
}
function kill_prepare() {
	@unlink( $GLOBALS['bk_root'] . '/worker-ready' );
	return bk_manual();
}
/** Con el hijo vivo y bloqueado dentro de la instantánea: nadie toca su trabajo. */
function kill_check_alive() {
	$job = kill_job();
	$pid = (int) file_get_contents( $GLOBALS['bk_root'] . '/worker-ready' );
	bk_assert( 'running' === $job['status'] && 'database' === $job['phase'] && $pid === (int) $job['lease']['pid'], 'El trabajador no tomó el trabajo antes de bloquearse.' );
	bk_assert( glob( bk_storage() . '/jobs/' . $job['id'] . '/staging/database.*.sql.part' ), 'El trabajador no dejó su SQL a medias.' );
	$rev = $job['rev'];
	$tick = Digitalisimo_Backups_Jobs::tick();
	bk_assert( 'busy' === $tick['result'] && ! Digitalisimo_Backups_Jobs::recover_if_idle() && $rev === kill_job()['rev'], 'Se tocó el trabajo de un proceso vivo.' );
}
/** Tras SIGKILL: el detector lo reconoce y el trabajo termina. */
function kill_after() {
	bk_assert( Digitalisimo_Backups_Jobs::recover_if_idle(), 'El cerrojo del proceso muerto no quedó libre.' );
	$job = kill_job();
	bk_assert( 'queued' === $job['status'] && 'interrupted' === $job['error']['code'] && 1 === $job['attempts']['database'] && null === $job['lease'], 'La muerte por SIGKILL no se reconoció como interrupción.' );
	bk_assert( ! glob( bk_storage() . '/jobs/' . $job['id'] . '/staging/database.*' ), 'Quedó el SQL parcial del proceso muerto.' );
	bk_assert( ! glob( bk_storage() . '/digitalisimo-*.zip' ) && ! bk_history(), 'Hay un respaldo registrado o con nombre final antes de terminar.' );
	bk_run_until_done( $job['id'] );
	$job = kill_job();
	$history = bk_history();
	bk_assert( 'success' === $job['status'] && 1 === count( $history ) && $job['spec']['file'] === $history[0]['file'], 'El trabajo no terminó con una sola fila tras la muerte.' );
	Digitalisimo_Backups::job_verify_package( bk_storage() . '/' . $job['spec']['file'] );
}

/**
 * Tras un error fatal dentro del paso (memoria agotada): el apagado de PHP ya lo anotó y devolvió el trabajo a la cola
 * antes de que nadie pase por el detector; el SQL parcial no queda y el trabajo termina después.
 */
function kill_after_fatal( $id ) {
	$job = bk_job( $id );
	bk_assert( 'queued' === $job['status'] && null === $job['lease'] && 'fatal' === ( $job['error']['code'] ?? '' ) && false !== strpos( (string) $job['error']['message'], 'PHP se detuvo durante «Base de datos»: Allowed memory size' ) && 1 === $job['attempts']['database'], 'El apagado de PHP no anotó el error fatal del paso: ' . json_encode( array( $job['status'], $job['error'], $job['lease'] ) ) );
	bk_assert( ! glob( bk_storage() . '/jobs/' . $id . '/staging/database.*' ), 'Quedó el SQL parcial del paso con error fatal.' );
	bk_ready( $id ); // El trabajador programó su espera de reintento sin el filtro de la prueba.
	bk_run_until_done( $id );
	bk_assert( 'success' === bk_job( $id )['status'], 'El trabajo no terminó tras el error fatal.' );
}

if ( 'preparar' === $phase ) { kill_prepare(); echo $GLOBALS['bk_root'], "\n"; exit; }
if ( 'preparar-fatal' === $phase ) { echo bk_manual( 'local', 'fatal' )['id'], "\n"; exit; }
if ( 'tras-fatal' === $phase ) { foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'fatal' === $job['spec']['policy'] ) kill_after_fatal( $job['id'] ); bk_cleanup(); echo "fatal: ok\n"; exit; }
if ( 'vivo' === $phase ) { kill_check_alive(); echo "vivo: ok\n"; exit; }
if ( 'tras-matar' === $phase ) { kill_after(); bk_cleanup(); echo "DIGITALÍSIMO Backups: proceso muerto con SIGKILL reconocido y trabajo terminado sin repetir lo confirmado.\n"; exit; }

/** Sólo con un PHP del sistema que pueda lanzar otro proceso y matarlo. */
function kill_can_spawn() {
	if ( ! function_exists( 'proc_open' ) || ! function_exists( 'proc_terminate' ) || 'Unknown' === PHP_OS_FAMILY || 'Windows' === PHP_OS_FAMILY || '' === PHP_BINARY ) return false;
	$probe = @proc_open( array( PHP_BINARY, '-r', 'echo "listo";' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $probe ) ) return false;
	$output = stream_get_contents( $pipes[1] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	return 0 === proc_close( $probe ) && 'listo' === $output;
}
if ( ! kill_can_spawn() ) {
	bk_cleanup();
	echo "DIGITALÍSIMO Backups: prueba con SIGKILL OMITIDA (requiere proc_open y un PHP del sistema); backups-job-resume.php cubre las muertes simuladas.\n";
	exit;
}
kill_prepare();
$process = proc_open( array( PHP_BINARY, __DIR__ . '/backups-jobs/worker.php', $GLOBALS['bk_root'], 'bloquear-db' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
bk_assert( is_resource( $process ), 'No se pudo lanzar el trabajador.' );
for ( $i = 0; $i < 600 && ! is_file( $GLOBALS['bk_root'] . '/worker-ready' ); $i++ ) {
	$status = proc_get_status( $process );
	if ( ! $status['running'] ) bk_fail( 'El trabajador terminó antes de bloquearse: ' . stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] ) );
	usleep( 100000 );
}
bk_assert( is_file( $GLOBALS['bk_root'] . '/worker-ready' ), 'El trabajador no llegó a la exportación en 60 s.' );
kill_check_alive();
bk_assert( proc_terminate( $process, 9 ), 'No se pudo enviar SIGKILL al trabajador.' );
for ( $i = 0; $i < 100 && proc_get_status( $process )['running']; $i++ ) usleep( 100000 );
$status = proc_get_status( $process );
bk_assert( ! $status['running'] && ( $status['signaled'] || -1 === $status['exitcode'] ), 'El trabajador no murió por la señal.' );
fclose( $pipes[1] );
fclose( $pipes[2] );
proc_close( $process );
kill_after();
// Error fatal dentro de la instantánea: lo anota el apagado de PHP, sin esperar al detector.
$fatal = bk_manual( 'local', 'fatal' );
$process = proc_open( array( PHP_BINARY, __DIR__ . '/backups-jobs/worker.php', $GLOBALS['bk_root'], 'fatal-db' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
bk_assert( is_resource( $process ), 'No se pudo lanzar el trabajador del error fatal.' );
$output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
fclose( $pipes[1] );
fclose( $pipes[2] );
bk_assert( 255 === proc_close( $process ), 'El trabajador no terminó con un error fatal: ' . $output );
kill_after_fatal( $fatal['id'] );
bk_cleanup();
echo "DIGITALÍSIMO Backups: proceso muerto con SIGKILL y error fatal reconocidos, y trabajos terminados sin repetir lo confirmado.\n";
