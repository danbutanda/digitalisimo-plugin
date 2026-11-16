<?php
/**
 * Laboratorio del ensayo «subir e importar con el panel de avance» (mu-plugin): sin loopback (la cola sólo avanza con
 * los ticks de la pantalla y los de wp-cron.php que pide el ensayo) ni correo, y lo que controla /digi-ctl:
 *   chunk-delay-ms  cada fragmento de la subida espera esos milisegundos (fragmentos de 1 MiB): así se ve su avance;
 *   check-delay     la comprobación de una subida espera esos segundos al empezar (con el cerrojo de su sesión y en
 *                   `validating`): así se ve y se puede recargar la página a mitad;
 *   tick-budget     segundos de trabajo de cada tick (la importación avanza por fases que se ven);
 * y anota en /digi-ctl/chunks.log el desplazamiento de cada fragmento que llega. `/?digi_upbar=state&token=<testigo>`
 * (el de /digi-probe/token.txt): las filas del historial (sin rutas), las subidas, los trabajos y los archivos del almacén.
 */
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) parse_url( (string) $url, PHP_URL_HOST );
	if ( ! in_array( $host, array( parse_url( home_url(), PHP_URL_HOST ), '127.0.0.1', 'localhost' ), true ) ) return $pre;
	return new WP_Error( 'digi_no_loopback', 'Loopback cortado en el ensayo.' );
}, 10, 3 );
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'digitalisimo_backups_upload_chunk_size', static function () { return 1048576; } );
add_action( 'wp_ajax_digitalisimo_backups_upload_chunk', static function () {
	@file_put_contents( '/digi-ctl/chunks.log', (int) ( $_POST['offset'] ?? -1 ) . "\n", FILE_APPEND );
	$ms = (int) @file_get_contents( '/digi-ctl/chunk-delay-ms' );
	if ( $ms > 0 ) usleep( $ms * 1000 );
}, 1 );
add_filter( 'digitalisimo_backups_upload_check_every', static function ( $every ) {
	$seconds = (int) @file_get_contents( '/digi-ctl/check-delay' );
	if ( $seconds > 0 ) sleep( $seconds );
	return $every;
} );
add_filter( 'digitalisimo_backups_job_budget', static function ( $budget ) {
	$seconds = (int) @file_get_contents( '/digi-ctl/tick-budget' );
	return $seconds > 0 ? $seconds : $budget;
}, 20 );
add_action( 'init', static function () {
	if ( empty( $_GET['digi_upbar'] ) ) return;
	$token = trim( (string) @file_get_contents( '/digi-probe/token.txt' ) );
	if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) { status_header( 403 ); exit; }
	$out = array();
	try {
		wp_cache_flush();
		$history = array();
		foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $row ) {
			if ( ! is_array( $row ) ) continue;
			$history[] = array( 'file' => (string) ( $row['file'] ?? '' ), 'size' => (int) ( $row['size'] ?? 0 ), 'origin' => (string) ( $row['origin'] ?? 'created' ), 'sha256' => (string) ( $row['sha256'] ?? '' ) );
		}
		$uploads = array();
		foreach ( Digitalisimo_Backups_Uploads::sessions() as $state ) $uploads[] = array( 'id' => (string) $state['id'], 'phase' => (string) ( $state['phase'] ?? '' ), 'received' => Digitalisimo_Backups_Uploads::received( (string) $state['id'] ), 'size' => (int) $state['size'], 'check' => $state['check'] ?? null );
		$jobs = array();
		foreach ( Digitalisimo_Backups_Jobs::all() as $job ) $jobs[] = array( 'id' => (string) $job['id'], 'kind' => (string) ( $job['kind'] ?? '' ), 'spec_kind' => (string) ( $job['spec']['kind'] ?? '' ), 'status' => (string) $job['status'], 'phase' => (string) ( $job['phase'] ?? '' ), 'file' => (string) ( $job['result']['import']['file'] ?? ( $job['result']['file'] ?? '' ) ) );
		$store = rtrim( Digitalisimo_Backups::storage_directory(), '/' );
		$files = array_values( array_map( 'basename', glob( $store . '/*.{zip,dgbk}', GLOB_BRACE ) ?: array() ) );
		$backuply = array_values( array_map( 'basename', glob( $store . '/backuply/*.tar.gz' ) ?: array() ) );
		$out = array( 'history' => $history, 'uploads' => $uploads, 'jobs' => $jobs, 'files' => $files, 'backuply' => $backuply );
	} catch ( Throwable $e ) {
		$out['error'] = $e->getMessage();
	}
	nocache_headers();
	wp_send_json( $out );
}, 1 );
