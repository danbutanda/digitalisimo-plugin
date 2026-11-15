<?php
// Proceso 2: el sitio ya tiene el Backups del repositorio. Restauración COMPLETA (sin partes) por la web hasta que el
// trabajo entra en la limpieza; ahí termina la petición (como un tick que agota su tiempo).
require __DIR__ . '/common.php';
require getenv( 'DIGI_PARTS_UPGRADE' ) . '/site/wp-content/plugins/tests/lib/backups-env.php';
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
up_load( getenv( 'DIGI_PARTS_UPGRADE' ) . '/state1.ser' );
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$GLOBALS['bk_options']['blogname'] = 'Cambiado después';
Digitalisimo_Backups_Jobs::$phase_runner = function( $phase, $job ) {
	if ( 'cleanup' === $phase ) {
		up_save( getenv( 'DIGI_PARTS_UPGRADE' ) . '/state2.ser' );
		echo "p2: el trabajo llegó a la limpieza; la petición termina aquí\n";
		exit( 0 );
	}
	return Digitalisimo_Backups_Restore_Job::run_phase( $phase, $job );
};
$r = Digitalisimo_Backups::service_restore( trim( file_get_contents( getenv( 'DIGI_PARTS_UPGRADE' ) . '/backup.txt' ) ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'rest' ) );
for ( $i = 0; $i < 200; $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
echo "FALLA · p2: no llegó a la limpieza: " . json_encode( is_wp_error( $r ) ? $r->get_error_message() : $r ) . "\n";
