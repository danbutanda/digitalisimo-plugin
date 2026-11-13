<?php
/** Utilidades de las pruebas de incrementales (tests/backups-incremental*.php), sobre tests/lib/backups-env.php. */
// Lo que las automatizaciones y la cola piden a WP-Cron (el entorno común no lo simula).
foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86400 ) as $constant => $value ) defined( $constant ) || define( $constant, $value );
$GLOBALS['inc_cron'] = array();
if ( ! function_exists( 'wp_schedule_event' ) ) { function wp_schedule_event( $at, $recurrence, $hook, $args = array() ) { $GLOBALS['inc_cron'][ $hook . json_encode( $args ) ] = $at; return true; } }
if ( ! function_exists( 'wp_schedule_single_event' ) ) { function wp_schedule_single_event( $at, $hook, $args = array() ) { $GLOBALS['inc_cron'][ $hook . json_encode( $args ) ] = $at; return true; } }
if ( ! function_exists( 'wp_next_scheduled' ) ) { function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['inc_cron'][ $hook . json_encode( $args ) ] ?? false; } }
if ( ! function_exists( 'wp_unschedule_event' ) ) { function wp_unschedule_event( $at, $hook, $args = array() ) { unset( $GLOBALS['inc_cron'][ $hook . json_encode( $args ) ] ); return true; } }
if ( ! function_exists( 'spawn_cron' ) ) { function spawn_cron() { return true; } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
function inc_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function inc_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() : '' ) . "\n";
	}
}
function inc_put( $name, $content ) {
	$path = rtrim( ABSPATH, '/' ) . '/' . $name;
	if ( ! is_dir( dirname( $path ) ) ) mkdir( dirname( $path ), 0700, true );
	file_put_contents( $path, $content );
}
/** Archivos del sitio que el respaldo gestiona (ruta del ZIP => SHA-256), sin wp-config.php, que nunca se restaura. */
function inc_files() {
	$out = array();
	foreach ( bk_tree() as $path => $hash ) if ( 'dir' !== $hash && '/wp-config.php' !== $path ) $out[ 'wordpress' . $path ] = $hash;
	ksort( $out );
	return $out;
}
/**
 * Lo que se compara de la base de datos simulada: sus opciones (wp_options es la tabla que la base simulada reemplaza al
 * restaurar), sin las de Backups, que la restauración conserva de este sitio. wp_posts se comprueba en el SQL de cada punto.
 */
function inc_db() {
	$options = $GLOBALS['bk_options'];
	foreach ( array_keys( $options ) as $key ) if ( 0 === strpos( $key, 'digitalisimo_backups' ) || 0 === strpos( $key, '_transient' ) || 0 === strpos( $key, 'cron' ) ) unset( $options[ $key ] );
	ksort( $options );
	return json_encode( $options );
}
/** Una automatización (o el manual) por el motor real, por ticks, hasta terminar. */
function inc_run( $policy, $every = 3, $days = 7, $retain = 20, $units = 0 ) {
	if ( $units ) $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] = function() use ( $units ) { return $units; };
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => $policy, 'destination' => 'local', 'retain' => $retain, 'retention' => 'policy', 'via' => 'test', 'incremental' => array( 'full_every' => $every, 'full_days' => $days ) ) ) );
	$id = $queued['job']['id'];
	for ( $i = 0; $i < 4000; $i++ ) {
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
		Digitalisimo_Backups_Jobs::tick( $units ? 0 : PHP_INT_MAX );
	}
	unset( $GLOBALS['bk_filters']['digitalisimo_backups_step_units'] );
	$job = Digitalisimo_Backups_Jobs::get( $id );
	inc_check( 'success' === $job['status'], 'El respaldo de ' . $policy . ' no terminó: ' . json_encode( $job['error'] ) );
	return $job;
}
function inc_row( $file ) { foreach ( (array) $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] as $row ) if ( ( $row['file'] ?? '' ) === basename( $file ) ) return $row; return null; }
function inc_path( array $job ) { return rtrim( bk_storage(), '/' ) . '/' . basename( (string) $job['spec']['file'] ); }
/** Entradas de un ZIP (nombre => SHA-256 del contenido) y su manifiesto. */
function inc_zip( $path ) {
	$zip = new ZipArchive();
	inc_check( true === $zip->open( $path, ZipArchive::CHECKCONS ), 'El ZIP no supera CHECKCONS: ' . basename( $path ) );
	$out = array();
	for ( $i = 0; $i < $zip->numFiles; $i++ ) { $name = $zip->getNameIndex( $i ); $out[ $name ] = hash( 'sha256', (string) $zip->getFromIndex( $i ) ); }
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$inventory = (string) $zip->getFromName( 'inventory.jsonl' );
	$sql = (string) $zip->getFromName( 'database.sql' );
	$zip->close();
	return array( 'entries' => $out, 'manifest' => $manifest, 'inventory' => array_map( function( $line ) { return json_decode( $line, true ); }, array_values( array_filter( explode( "\n", $inventory ) ) ) ), 'sql' => $sql );
}
function inc_local( $row ) { return bk_call( 'Digitalisimo_Backups', 'local_copy', $row ); }
function inc_materialize( $file, $secret = '' ) {
	return Digitalisimo_Backups_Chain_Restore::materialize( inc_row( $file ), (array) $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ], $secret, bk_storage(), 'inc_local' );
}
function inc_restore( $file ) {
	$result = Digitalisimo_Backups::service_restore( basename( $file ), array( 'confirm' => 'RESTAURAR', 'sync' => false, 'drive_cli' => false, 'via' => 'test' ) );
	for ( $i = 0; $i < 4000 && ! is_wp_error( $result ); $i++ ) {
		if ( ! Digitalisimo_Backups_Jobs::active() ) break;
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	return $result;
}
function inc_last_restore_ok() {
	$last = null;
	foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] && ( ! $last || (float) $job['queued_at'] >= (float) $last['queued_at'] ) ) $last = $job;
	return $last && 'success' === $last['status'] ? $last : null;
}
/** Simula un proceso muerto a mitad de una fase: arrendamiento de este servidor puesto y sin cerrojo. */
function inc_kill( $id ) {
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id ) {
		$job = Digitalisimo_Backups_Job_Store::read( $id );
		$job['status'] = Digitalisimo_Backups_Jobs::status_for( $job['phase'], $job['spec'] );
		$job['retry_at'] = 0;
		$job['lease'] = array( 'owner' => str_repeat( 'd', 32 ), 'host' => Digitalisimo_Backups_Jobs::host(), 'pid' => 999999, 'since' => time() - 30, 'heartbeat_at' => time() - 20 );
		Digitalisimo_Backups_Job_Store::write( $job );
	} );
	Digitalisimo_Backups_Jobs::recover_if_idle();
}

