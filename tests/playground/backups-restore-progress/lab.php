<?php
// Ensayo del panel de avance (backups-restore-progress.sh): mu-plugin que se suma a los laboratorios de backups-restore y
// backups-restore-parts.
// Ticks de /digi-ctl/tick-budget segundos (después del laboratorio, que pone 15): en un sitio pequeño, con 15 s un
// respaldo entero cabe en un tick y el panel no tendría avance intermedio que enseñar.
add_filter( 'digitalisimo_backups_job_budget', static function ( $budget ) {
	$seconds = (int) @file_get_contents( '/digi-ctl/tick-budget' );
	return $seconds > 0 ? $seconds : $budget;
}, 20 );
// La petición que empieza una restauración responde a los /digi-ctl/respond-after segundos (25 por defecto): en un sitio
// pequeño, con 25 s la restauración entera cabe en ella y el panel no tendría nada que seguir.
add_filter( 'digitalisimo_backups_restore_respond_after', static function ( $seconds ) {
	$file = '/digi-ctl/respond-after';
	return is_file( $file ) ? max( 0, (int) file_get_contents( $file ) ) : $seconds;
}, 20 );
// Un error fatal (la página «critical error» de WordPress): la petición, el error y la memoria, para el informe. Lo llama
// el manejador de errores fatales de WordPress antes de pintar esa página.
add_filter( 'wp_php_error_message', static function ( $message, $error ) {
	@file_put_contents( '/digi-ctl/fatal.jsonl', json_encode( array( 'uri' => (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 'method' => (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), 'action' => (string) ( $_REQUEST['action'] ?? '' ), 'error' => $error, 'peak' => memory_get_peak_usage( true ), 'limit' => ini_get( 'memory_limit' ) ), JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n", FILE_APPEND );
	return $message;
}, 10, 2 );
// Con WP_DISABLE_FATAL_ERROR_HANDLER (lo pone el ensayo), WordPress no corta con exit las funciones de cierre: un error
// fatal (también la memoria agotada) se anota aquí con la petición y la memoria.
register_shutdown_function( static function () {
	$error = error_get_last();
	if ( ! $error || ! in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) return;
	@file_put_contents( '/digi-ctl/fatal.jsonl', json_encode( array( 'uri' => (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 'method' => (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), 'action' => (string) ( $_REQUEST['action'] ?? '' ), 'error' => $error, 'peak' => memory_get_peak_usage( true ), 'limit' => ini_get( 'memory_limit' ) ), JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n", FILE_APPEND );
} );
