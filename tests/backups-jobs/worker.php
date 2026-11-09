<?php
/**
 * Trabajador de tests/backups-job-kill.php: corre un tick sobre la raíz de prueba indicada. Con «bloquear-db» se
 * detiene dentro de la instantánea de la exportación, con el cerrojo del almacén tomado, para que lo maten ahí; con
 * «fatal-db» agota la memoria en ese mismo punto; con «bloquear-db-tramos» (T2), dentro del tramo que sigue a un punto
 * de control ya guardado.
 */
$GLOBALS['bk_root'] = (string) ( $argv[1] ?? '' );
if ( '' === $GLOBALS['bk_root'] || ! is_dir( $GLOBALS['bk_root'] ) ) { fwrite( STDERR, "Falta la raíz de prueba.\n" ); exit( 2 ); }
require __DIR__ . '/env.php';
if ( in_array( 'bloquear-db', $argv, true ) ) {
	$GLOBALS['wpdb']->on_select = function() {
		if ( ! $GLOBALS['wpdb']->in_snapshot || file_exists( $GLOBALS['bk_root'] . '/worker-ready' ) ) return;
		file_put_contents( $GLOBALS['bk_root'] . '/worker-ready', (string) getmypid() );
		sleep( 120 );
	};
}
if ( in_array( 'fatal-db', $argv, true ) ) {
	// Memoria agotada dentro de la instantánea: un error fatal que ningún catch atrapa; sólo lo ve el apagado de PHP.
	$GLOBALS['wpdb']->on_select = function() {
		if ( ! $GLOBALS['wpdb']->in_snapshot ) return;
		ini_set( 'memory_limit', '64M' );
		$GLOBALS['bk_waste'] = str_repeat( 'x', 512 * 1048576 );
	};
}
if ( in_array( 'bloquear-db-tramos', $argv, true ) ) {
	// T2: tramos de un lote de dos filas; se detiene dentro de la instantánea del tramo que sigue a un punto de control ya
	// guardado (a mitad de wp_options), para que lo maten ahí.
	$GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function() { return 1; };
	$GLOBALS['bk_filters']['digitalisimo_backups_db_batch'] = function() { return 2; };
	$GLOBALS['wpdb']->on_select = function() {
		if ( ! $GLOBALS['wpdb']->in_snapshot || file_exists( $GLOBALS['bk_root'] . '/worker-ready' ) ) return;
		$jobs = Digitalisimo_Backups_Job_Store::all();
		if ( ( $jobs[0]['checkpoint']['database']['resume']['total'] ?? 0 ) < 2 ) return;
		file_put_contents( $GLOBALS['bk_root'] . '/worker-ready', (string) getmypid() );
		sleep( 120 );
	};
}
echo json_encode( Digitalisimo_Backups_Jobs::tick() ), "\n";
