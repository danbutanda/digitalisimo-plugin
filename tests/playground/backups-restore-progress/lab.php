<?php
// Ensayo del panel de avance (backups-restore-progress.sh): mu-plugin que se suma a los laboratorios de backups-restore y
// backups-restore-parts.
// Ticks de /digi-ctl/tick-budget segundos (después del laboratorio, que pone 15): en un sitio pequeño, con 15 s un
// respaldo entero cabe en un tick y el panel no tendría avance intermedio que enseñar.
add_filter( 'digitalisimo_backups_job_budget', static function ( $budget ) {
	$seconds = (int) @file_get_contents( '/digi-ctl/tick-budget' );
	return $seconds > 0 ? $seconds : $budget;
}, 20 );
// Un error fatal (la página «critical error» de WordPress): la petición, el error y la memoria, para el informe. Lo llama
// el manejador de errores fatales de WordPress antes de pintar esa página.
add_filter( 'wp_php_error_message', static function ( $message, $error ) {
	@file_put_contents( '/digi-ctl/fatal.jsonl', json_encode( array( 'uri' => (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 'method' => (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), 'action' => (string) ( $_REQUEST['action'] ?? '' ), 'error' => $error, 'peak' => memory_get_peak_usage( true ), 'limit' => ini_get( 'memory_limit' ) ) ) . "\n", FILE_APPEND );
	return $message;
}, 10, 2 );
