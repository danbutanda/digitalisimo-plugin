<?php
// Proceso 3: otro tick web (WP-Cron o POST /jobs/tick) que retoma la limpieza: pone la carpeta de Backups del respaldo
// (1.0.75, sin class-restore-parts.php) y después escribe el informe con la restauración por partes.
require __DIR__ . '/common.php';
require getenv( 'DIGI_PARTS_UPGRADE' ) . '/site/wp-content/plugins/tests/lib/backups-env.php';
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
up_load( getenv( 'DIGI_PARTS_UPGRADE' ) . '/state2.ser' );
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
$GLOBALS['bk_filters']['digitalisimo_backups_job_takeover'] = -1;
register_shutdown_function( function() { $e = error_get_last(); if ( $e && in_array( $e['type'], array( E_ERROR, E_COMPILE_ERROR, E_CORE_ERROR ), true ) ) echo "FALLA · p3: fatal en la limpieza: " . $e['message'] . "\n"; } );
for ( $i = 0; $i < 20 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] ) echo ( 'success' === $job['status'] ? 'p3: ' : 'FALLA · p3: ' ) . 'trabajo ' . $job['status'] . ' · ' . $job['phase'] . ' · ' . json_encode( $job['error'] ?? null, JSON_UNESCAPED_UNICODE ) . "\n";
echo ( 'Sitio del respaldo' === ( $GLOBALS['bk_options']['blogname'] ?? '' ) ? 'p3: ' : 'FALLA · p3: ' ) . 'base de datos del respaldo: ' . json_encode( $GLOBALS['bk_options']['blogname'] ?? null ) . "\n";
