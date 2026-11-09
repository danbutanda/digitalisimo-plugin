<?php
/**
 * Preparación del ensayo T2 de DIGITALÍSIMO Backups (paso runPHP del blueprint de backups-steps.sh). Activa el plugin,
 * escribe el mu-plugin del ensayo y llena la base de datos con entradas y metadatos (los archivos de ~200 MB los genera
 * el arnés en la carpeta de medios montada). Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 *
 * El mu-plugin pone en cada petición los límites de la pila del propietario (max_execution_time=30, memory_limit=64M,
 * también para admin y WP-Cron, que WordPress eleva a WP_MAX_MEMORY_LIMIT), corta el loopback de WP-Cron (la cola sólo
 * avanza con los ticks del navegador y las llamadas a wp-cron.php que hace el ensayo), registra cada petición que hace
 * avanzar la cola (duración desde REQUEST_TIME_FLOAT, memoria pico, límites, SAPI) y mata la petición a mitad de un
 * tramo cuando el ensayo deja una marca (die-db, die-pack o die-verify), como lo haría el hosting.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	$mu = <<<'PHP'
<?php
// Ensayo T2 de DIGITALÍSIMO Backups: límites de la pila del propietario, registro de peticiones y muertes inyectadas.
@ini_set( 'max_execution_time', '30' );
@ini_set( 'memory_limit', '64M' );
foreach ( array( 'admin_memory_limit', 'cron_memory_limit', 'image_memory_limit' ) as $digi_t2_filter ) add_filter( $digi_t2_filter, static function () { return '64M'; } );
// php-wasm se identifica como CLI aunque sirva peticiones web: se le dan los valores que la web calcula con 30 s.
if ( 'cli' === PHP_SAPI ) {
	add_filter( 'digitalisimo_backups_job_budget', static function () { return 15; } );
	add_filter( 'digitalisimo_backups_request_limit', static function () { return 30; } );
}
// En php-wasm la verificación de este sitio cabe en un tramo de 5 s: con tramos de 2 s (lo que se guarda el punto de
// control, no el presupuesto de la petición, que sigue en 15 s) también la verificación se parte y se puede matar a medias.
add_filter( 'digitalisimo_backups_step_slice', static function () { return 2; } );
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) { return false !== strpos( (string) $url, 'wp-cron.php' ) ? new WP_Error( 'digi_t2_no_cron', 'Sin loopback de WP-Cron en el ensayo.' ) : $pre; }, 10, 3 );
// Cada marca mata la petición dentro de su fase, pero sólo cuando esa fase ya guardó un punto de control con avance:
// la muerte cae a mitad de un tramo retomado y lo escrito después del punto de control queda a medias.
foreach ( array( 'digitalisimo_backups_db_batch' => array( 'die-db', 'database', 'total', 0 ), 'digitalisimo_backups_zip_chunk' => array( 'die-pack', 'pack', 'done', 100 ), 'digitalisimo_backups_verify_parts_from' => array( 'die-verify', 'verify', 'index', 100 ) ) as $digi_t2_hook => $digi_t2_rule ) {
	add_filter( $digi_t2_hook, static function ( $value ) use ( $digi_t2_rule ) {
		list( $digi_t2_flag, $phase, $field, $after ) = $digi_t2_rule;
		$flag = '/digi-ctl/' . $digi_t2_flag;
		if ( ! is_file( $flag ) || ! class_exists( 'Digitalisimo_Backups_Jobs', false ) ) return $value;
		$job = Digitalisimo_Backups_Jobs::get( Digitalisimo_Backups_Jobs::current_id() );
		if ( (int) ( $job['checkpoint'][ $phase ]['resume'][ $field ] ?? -1 ) <= $after ) return $value;
		if ( @unlink( $flag ) ) {
			file_put_contents( '/digi-ctl/deaths.jsonl', json_encode( array( 'flag' => $digi_t2_flag, 'at' => microtime( true ), 'job' => class_exists( 'Digitalisimo_Backups_Jobs', false ) ? Digitalisimo_Backups_Jobs::current_id() : '' ) ) . "\n", FILE_APPEND );
			exit; // La petición muere a mitad del tramo, con lo escrito a medias en el área de trabajo.
		}
		return $value;
	} );
}
$GLOBALS['digi_t2_request'] = array( 'uri' => (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 'action' => (string) ( $_REQUEST['action'] ?? '' ) );
// Lo que tarda WordPress en cargar se separa de lo que hace la petición después (un atasco del servidor cae en lo primero).
add_action( 'init', static function () { $GLOBALS['digi_t2_request']['init'] = microtime( true ); }, -1000 );
register_shutdown_function( static function () {
	$request = $GLOBALS['digi_t2_request'];
	if ( false === strpos( $request['uri'], 'wp-cron.php' ) && 'digitalisimo_backups_job_tick' !== $request['action'] && 'digitalisimo_backups_manual_start' !== $request['action'] ) return;
	$start = (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
	file_put_contents( '/digi-ctl/requests.jsonl', json_encode( array(
		'kind' => false !== strpos( $request['uri'], 'wp-cron.php' ) ? 'wp-cron' : ( 'digitalisimo_backups_manual_start' === $request['action'] ? 'start' : 'navegador' ),
		'start' => $start,
		'seconds' => round( microtime( true ) - $start, 3 ),
		'bootstrap' => isset( $request['init'] ) ? round( $request['init'] - $start, 3 ) : null,
		'work' => isset( $request['init'] ) ? round( microtime( true ) - $request['init'], 3 ) : null,
		'peak_mb' => round( memory_get_peak_usage( true ) / 1048576, 1 ),
		'memory_limit' => ini_get( 'memory_limit' ),
		'request_limit' => class_exists( 'Digitalisimo_Backups_Step_Budget', false ) ? Digitalisimo_Backups_Step_Budget::request_limit() : null,
		'sapi' => PHP_SAPI,
		'error' => error_get_last()['message'] ?? '',
	) ) . "\n", FILE_APPEND );
} );
PHP;
	if ( false === file_put_contents( WPMU_PLUGIN_DIR . '/digi-t2-limits.php', $mu ) ) throw new RuntimeException( 'No se escribió el mu-plugin del ensayo.' );

	// Base de datos: entradas con texto y metadatos (unos 30 MB de SQL), en sentencias de varias filas.
	global $wpdb;
	$posts = (int) ( getenv( 'DIGI_T2_POSTS' ) ?: 3000 );
	$paragraph = str_repeat( 'Contenido de prueba del ensayo T2 con acentos (año, ñandú) y "comillas" para el exportador. ', 90 );
	$wpdb->query( 'START TRANSACTION' );
	for ( $start = 1; $start <= $posts; $start += 50 ) {
		$values = array();
		for ( $i = $start; $i < $start + 50 && $i <= $posts; $i++ ) $values[] = $wpdb->prepare( '(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)', 1, gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s' ), $paragraph . ' #' . $i, 'Entrada ' . $i, '', 'publish', 'entrada-' . $i, '', '', '' );
		$wpdb->query( "INSERT INTO {$wpdb->posts} (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, post_name, to_ping, pinged, post_content_filtered) VALUES " . implode( ',', $values ) );
		if ( $wpdb->last_error ) throw new RuntimeException( 'No se insertaron las entradas: ' . $wpdb->last_error );
	}
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE 'entrada-%'" );
	foreach ( array_chunk( $ids, 100 ) as $chunk ) {
		$values = array();
		foreach ( $chunk as $id ) foreach ( array( 'campo_a', 'campo_b' ) as $key ) $values[] = $wpdb->prepare( '(%d, %s, %s)', $id, $key, str_repeat( 'v', 200 ) . $id );
		$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $values ) );
		if ( $wpdb->last_error ) throw new RuntimeException( 'No se insertaron los metadatos: ' . $wpdb->last_error );
	}
	$wpdb->query( 'COMMIT' );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array(
		'posts' => count( $ids ),
		'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION . ( defined( 'DB_ENGINE' ) ? ' · ' . DB_ENGINE : '' ),
	) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
