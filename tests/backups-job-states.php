<?php
/** Máquina de estados de la cola: transiciones, FIFO, un trabajo a la vez, duplicados, reintentos con tope, caducidad y errores. */
require __DIR__ . '/backups-jobs/env.php';

/** Fases simuladas: cada una avanza a la siguiente salvo que el guion pida otra cosa. */
$GLOBALS['script'] = array();
$GLOBALS['ran'] = array();
Digitalisimo_Backups_Jobs::$phase_runner = function( $phase, $job ) {
	$GLOBALS['ran'][] = $job['spec']['policy'] . ':' . $phase;
	$action = $GLOBALS['script'][ $job['spec']['policy'] . ':' . $phase ] ?? null;
	if ( is_array( $action ) ) $action = array_shift( $GLOBALS['script'][ $job['spec']['policy'] . ':' . $phase ] );
	if ( 'transient' === $action ) throw new RuntimeException( 'Fallo transitorio simulado en ' . $phase );
	if ( 'permanent' === $action ) throw new Digitalisimo_Backups_Permanent_Error( 'Fallo permanente simulado en ' . $phase );
	if ( 'back' === $action ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El ZIP cambió mientras se verificaba.', 'pack' );
	$order = array( 'preflight' => 'database', 'database' => 'pack', 'pack' => 'verify', 'verify' => 'publish', 'publish' => 'local' === $job['spec']['destination'] ? 'prune' : 'transfer', 'transfer' => 'prune', 'prune' => null );
	return array( 'next' => $order[ $phase ], 'checkpoint' => array( 'phase' => $phase, 'at' => time() ) );
};
function states_until_done( $id ) {
	$seen = array( bk_job( $id )['status'] );
	for ( $i = 0; $i < 30 && ! Digitalisimo_Backups_Job_Store::is_terminal( bk_job( $id ) ); $i++ ) {
		Digitalisimo_Backups_Jobs::tick( 0 );
		$seen[] = bk_job( $id )['status'];
	}
	return array_values( array_unique( $seen ) );
}
function force_ready( $id ) {
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id ) { $job = Digitalisimo_Backups_Job_Store::read( $id ); $job['retry_at'] = 0; Digitalisimo_Backups_Job_Store::write( $job ); } );
}

// Recorrido completo con destino remoto: pendiente → en curso → verificando → transfiriendo → completo.
$remote = bk_manual( 'google' );
$states = states_until_done( $remote['id'] );
bk_assert( array( 'queued', 'running', 'verifying', 'transferring', 'success' ) === $states, 'Estados con destino remoto: ' . implode( ' → ', $states ) );
$done = bk_job( $remote['id'] );
bk_assert( 100 === Digitalisimo_Backups_Jobs::view( $done )['percent'] && $done['finished_at'] && null === $done['lease'] && 7 === count( $done['checkpoint'] ), 'El trabajo completo no cerró su avance, arrendamiento o puntos de control.' );
// Terminal e inmutable.
$rev = $done['rev'];
Digitalisimo_Backups_Jobs::tick();
$cancel = Digitalisimo_Backups_Jobs::cancel( $remote['id'] );
bk_assert( $rev === bk_job( $remote['id'] )['rev'] && empty( $cancel['ok'] ) && 'terminal' === $cancel['code'], 'Un trabajo terminado cambió o se pudo cancelar.' );
// Sin destino remoto no hay «transfiriendo».
$local = bk_manual();
bk_assert( array( 'queued', 'running', 'verifying', 'success' ) === states_until_done( $local['id'] ), 'Un respaldo local pasó por la transferencia.' );

// FIFO y un trabajo a la vez: el segundo espera a que el primero termine.
$GLOBALS['ran'] = array();
$first = bk_manual( 'local', 'primero' );
$second = bk_manual( 'local', 'segundo' );
Digitalisimo_Backups_Jobs::tick( 0 );
bk_assert( array( 'primero:preflight' ) === $GLOBALS['ran'] && 'queued' === bk_job( $second['id'] )['status'], 'El orden de llegada no se respetó.' );
Digitalisimo_Backups_Jobs::tick();
bk_assert( 'success' === bk_job( $first['id'] )['status'] && 'success' === bk_job( $second['id'] )['status'], 'Un tick sin límite no completó la cola en orden.' );
bk_assert( 'primero:prune' === $GLOBALS['ran'][5] && 'segundo:preflight' === $GLOBALS['ran'][6], 'Las fases de dos trabajos se mezclaron: ' . implode( ', ', $GLOBALS['ran'] ) );

// Sin duplicados: una política o el respaldo manual nunca tienen dos trabajos sin terminar.
$a = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'semanal', 'destination' => 'local' ) ) );
$b = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'semanal', 'destination' => 'sftp' ) ) );
$m1 = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual' ) ) );
$m2 = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual' ) ) );
bk_assert( ! $a['duplicate'] && $b['duplicate'] && $b['job']['id'] === $a['job']['id'] && 'local' === $b['job']['spec']['destination'], 'Se duplicó una política en cola.' );
bk_assert( ! $m1['duplicate'] && $m2['duplicate'], 'Se duplicó el respaldo manual.' );
Digitalisimo_Backups_Jobs::tick();
bk_assert( ! Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'semanal' ) ) )['duplicate'], 'Una política terminada siguió bloqueando su siguiente respaldo.' );
Digitalisimo_Backups_Jobs::tick();

// La especificación es inmutable: cambiar la política después no altera el trabajo en cola.
update_option( Digitalisimo_Backups::POLICIES, array( array( 'id' => 'mensual', 'type' => 'full', 'schedule' => 'monthly', 'retain' => 3, 'destination' => 'local', 'enabled' => true ) ) );
$queued = Digitalisimo_Backups::run_policy( 'mensual', 1, 'test' );
update_option( Digitalisimo_Backups::POLICIES, array( array( 'id' => 'mensual', 'type' => 'full', 'schedule' => 'monthly', 'retain' => 9, 'destination' => 'onedrive', 'enabled' => true ) ) );
$spec = bk_job( $queued['job']['id'] )['spec'];
bk_assert( 3 === $spec['retain'] && 'local' === $spec['destination'] && 'policy' === $spec['retention'] && 'mensual' === $spec['policy'], 'El trabajo de la política no conservó su especificación.' );
bk_assert( ! isset( $spec['sftp_password'] ) && false === strpos( json_encode( $spec ), 'token' ), 'La especificación guarda credenciales.' );
Digitalisimo_Backups_Jobs::tick();

// Reintentos: un fallo transitorio espera (1, 5 y 15 min ±20 %), y al agotar el tope falla.
$GLOBALS['script']['reintento:pack'] = array( 'transient', 'transient' );
$retry = bk_manual( 'local', 'reintento' );
$GLOBALS['bk_cron'] = array(); // Los ticks anteriores ya habrían corrido en WP-Cron.
Digitalisimo_Backups_Jobs::tick();
$job = bk_job( $retry['id'] );
$wait = $job['retry_at'] - time();
bk_assert( 'queued' === $job['status'] && 'pack' === $job['phase'] && 1 === $job['attempts']['pack'] && $wait >= 47 && $wait <= 73 && 'retry' === $job['error']['code'], 'El primer fallo transitorio no esperó un minuto.' );
bk_assert( isset( $job['checkpoint']['database'] ) && null === $job['lease'], 'El reintento perdió el punto de control anterior o el arrendamiento.' );
$view = Digitalisimo_Backups_Jobs::view( $job );
bk_assert( 2 === $view['attempt'] && 3 === $view['max_attempts'] && $view['retry_in'] > 0 && false !== strpos( $view['detail'], 'Reintento a las' ), 'La vista no anuncia el reintento.' );
$cron = wp_next_scheduled( Digitalisimo_Backups_Jobs::HOOK_TICK );
bk_assert( $cron === $job['retry_at'], 'El tick del reintento no quedó programado a su hora.' );
$GLOBALS['ran'] = array();
Digitalisimo_Backups_Jobs::tick();
bk_assert( ! $GLOBALS['ran'], 'Se repitió antes de la hora del reintento.' );
force_ready( $retry['id'] );
Digitalisimo_Backups_Jobs::tick();
$job = bk_job( $retry['id'] );
$wait = $job['retry_at'] - time();
bk_assert( 2 === $job['attempts']['pack'] && $wait >= 239 && $wait <= 361, 'El segundo fallo no esperó cinco minutos.' );
force_ready( $retry['id'] );
Digitalisimo_Backups_Jobs::tick();
bk_assert( 'success' === bk_job( $retry['id'] )['status'] && array( 'reintento:pack', 'reintento:verify' ) === array_slice( $GLOBALS['ran'], 1, 2 ), 'Tras dos fallos el trabajo no siguió desde su fase.' );
foreach ( array( 1 => array( 48, 72 ), 2 => array( 240, 360 ), 3 => array( 720, 1080 ), 9 => array( 720, 1080 ) ) as $count => $range ) {
	$value = Digitalisimo_Backups_Jobs::backoff( $count );
	bk_assert( $value >= $range[0] && $value <= $range[1], 'Espera ' . $count . ' fuera de rango: ' . $value );
}
bk_no_backoff();
// Topes por fase: 2 para la base de datos, 3 para las demás, 5 para la transferencia.
foreach ( array( 'database' => 2, 'pack' => 3, 'transfer' => 5 ) as $phase => $max ) {
	$policy = 'tope-' . $phase;
	$GLOBALS['script'][ $policy . ':' . $phase ] = array_fill( 0, 10, 'transient' );
	$capped = bk_manual( 'transfer' === $phase ? 'sftp' : 'local', $policy );
	for ( $i = 0; $i < 20 && ! Digitalisimo_Backups_Job_Store::is_terminal( bk_job( $capped['id'] ) ); $i++ ) Digitalisimo_Backups_Jobs::tick();
	$job = bk_job( $capped['id'] );
	$tries = count( array_filter( $GLOBALS['ran'], function( $step ) use ( $policy, $phase ) { return $policy . ':' . $phase === $step; } ) );
	bk_assert( 'failed' === $job['status'] && 'attempts' === $job['error']['code'] && false === $job['error']['permanent'] && $max === $tries, 'Tope de ' . $phase . ': ' . $tries . ' intentos, estado ' . $job['status'] );
	bk_assert( false !== strpos( $job['error']['message'], 'se agotaron ' . $max . ' intentos' ), 'El error final no explica los intentos agotados.' );
}
// Un error permanente falla al momento.
$GLOBALS['script']['permanente:preflight'] = 'permanent';
$permanent = bk_manual( 'local', 'permanente' );
Digitalisimo_Backups_Jobs::tick();
$job = bk_job( $permanent['id'] );
bk_assert( 'failed' === $job['status'] && true === $job['error']['permanent'] && ! isset( $job['attempts']['preflight'] ), 'Un error permanente se reintentó.' );
// Un punto de control inválido vuelve a la fase que lo produjo y cuenta un intento de la fase actual.
$GLOBALS['script']['vuelta:verify'] = array( 'back' );
$GLOBALS['ran'] = array();
$back = bk_manual( 'local', 'vuelta' );
Digitalisimo_Backups_Jobs::tick();
$job = bk_job( $back['id'] );
bk_assert( 'pack' === $job['phase'] && 1 === $job['attempts']['verify'] && ! isset( $job['checkpoint']['pack'] ) && isset( $job['checkpoint']['database'] ), 'La verificación fallida no volvió al empaquetado conservando la base de datos.' );
Digitalisimo_Backups_Jobs::tick();
bk_assert( 'success' === bk_job( $back['id'] )['status'] && 2 === count( array_keys( $GLOBALS['ran'], 'vuelta:pack', true ) ) && 1 === count( array_keys( $GLOBALS['ran'], 'vuelta:database', true ) ), 'Tras volver al empaquetado se repitió la base de datos o no se completó.' );

// Caducidad: un trabajo sin terminar a las 24 h falla limpio.
$old = bk_manual( 'local', 'caducado' );
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $old ) { $job = Digitalisimo_Backups_Job_Store::read( $old['id'] ); $job['created_at'] = time() - 86500; $job['phase'] = 'pack'; Digitalisimo_Backups_Job_Store::write( $job ); } );
Digitalisimo_Backups_Job_Store::staging( $old['id'] );
Digitalisimo_Backups_Jobs::tick();
$job = bk_job( $old['id'] );
bk_assert( 'failed' === $job['status'] && 'expired' === $job['error']['code'] && ! is_dir( bk_storage() . '/jobs/' . $old['id'] . '/staging' ), 'Un trabajo de más de 24 h no caducó o dejó su área de trabajo.' );

// Otra versión del motor: falla limpio y pide repetir.
Digitalisimo_Backups_Jobs::$phase_runner = null;
$future = bk_manual( 'local', 'futuro' );
Digitalisimo_Backups_Job_Store::with_lock( function() use ( $future ) { $job = Digitalisimo_Backups_Job_Store::read( $future['id'] ); $job['spec']['engine']['job_format'] = 2; Digitalisimo_Backups_Job_Store::write( $job ); } );
Digitalisimo_Backups_Jobs::tick();
$job = bk_job( $future['id'] );
bk_assert( 'failed' === $job['status'] && $job['error']['permanent'] && false !== strpos( $job['error']['message'], 'otra versión del motor' ), 'Un trabajo de otro formato no falló limpio.' );

// Políticas parciales y fuera del sitio principal nunca se encolan.
update_option( Digitalisimo_Backups::POLICIES, array( array( 'id' => 'parcial', 'type' => 'database', 'schedule' => 'daily', 'retain' => 3, 'destination' => 'local', 'enabled' => true ) ) );
bk_assert( Digitalisimo_Backups::run_policy( 'parcial' ) instanceof WP_Error, 'Se encoló una política parcial.' );
bk_assert( ! Digitalisimo_Backups_Jobs::has_active(), 'Quedaron trabajos activos al final de la prueba.' );

bk_cleanup();
echo "DIGITALÍSIMO Backups: estados, orden, duplicados, reintentos con tope, caducidad y errores permanentes verificados.\n";
