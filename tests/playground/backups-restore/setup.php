<?php
/**
 * Preparación del ensayo de la restauración robusta (paso runPHP del blueprint de backups-restore.sh). Activa Backups (en
 * la red, para toda la red), escribe el mu-plugin del ensayo, crea contenido (entradas con metadatos, opciones
 * serializadas, un plugin «demo», un archivo del tema y medios; en la red, también el subsitio «uno») y deja
 * /digi-probe/setup.json; un fallo queda en setup-error.txt.
 *
 * El mu-plugin pone en cada petición los límites de la pila del propietario (max_execution_time=30 y memory_limit=64M,
 * también para admin y WP-Cron), los presupuestos que la web calcula con 30 s (php-wasm se identifica como CLI), corta el
 * loopback de WP-Cron (la cola sólo avanza con lo que pide el ensayo), registra cada petición que hace avanzar la cola
 * (duración, memoria, fase), da el inventario del sitio y lo cambia (con el testigo de /digi-probe/token.txt) y mata la
 * petición a mitad de una fase de la restauración cuando el ensayo deja una marca (die-rest, die-db, die-files) o hace fallar el
 * movimiento de archivos (fail-move), como lo haría el hosting.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	$network = is_multisite();
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', $network );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	$mu = <<<'PHP'
<?php
// Ensayo de la restauración robusta de DIGITALÍSIMO Backups: límites, registro de peticiones, inventario y muertes.
@ini_set( 'max_execution_time', '30' );
@ini_set( 'memory_limit', '64M' );
foreach ( array( 'admin_memory_limit', 'cron_memory_limit', 'image_memory_limit' ) as $digi_r_filter ) add_filter( $digi_r_filter, static function () { return '64M'; } );
if ( 'cli' === PHP_SAPI ) {
	add_filter( 'digitalisimo_backups_job_budget', static function () { return 15; } );
	add_filter( 'digitalisimo_backups_request_limit', static function () { return 30; } );
}
add_filter( 'digitalisimo_backups_job_backoff', static function ( $wait ) { return min( (int) $wait, 2 ); } );
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) { return false !== strpos( (string) $url, 'wp-cron.php' ) ? new WP_Error( 'digi_r_no_cron', 'Sin loopback de WP-Cron en el ensayo.' ) : $pre; }, 10, 3 );
function digi_r_kill( $flag ) {
	file_put_contents( '/digi-ctl/deaths.jsonl', json_encode( array( 'flag' => $flag, 'at' => microtime( true ), 'job' => Digitalisimo_Backups_Jobs::current_id() ) ) . "\n", FILE_APPEND );
	// Como SIGKILL: el apagado del motor no anota nada; el arrendamiento queda y lo reconoce el detector.
	$current = new ReflectionProperty( 'Digitalisimo_Backups_Jobs', 'current' );
	if ( PHP_VERSION_ID < 80100 ) $current->setAccessible( true );
	$current->setValue( null, null );
	exit;
}
// Tramos de 400 unidades (sentencias, archivos) y las muertes o el fallo que pide el ensayo, dentro de su fase y cuando ya
// guardó un punto de control con avance: lo hecho después de él queda a medias.
add_filter( 'digitalisimo_backups_step_units', static function () {
	if ( ! class_exists( 'Digitalisimo_Backups_Jobs', false ) ) return 400;
	$id = Digitalisimo_Backups_Jobs::current_id();
	$job = '' !== $id ? Digitalisimo_Backups_Jobs::get( $id ) : null;
	if ( ! $job || 'restore' !== $job['kind'] ) return 400;
	$cursor = (array) ( $job['checkpoint'][ $job['phase'] ] ?? array() );
	// La petición de REST que pidió la restauración muere ya en mantenimiento: el cliente no recibe ni el trabajo ni el
	// testigo, y sólo WP-Cron (el cron del sistema, sin testigo) puede seguirla.
	$uri = rawurldecode( (string) ( $GLOBALS['digi_r_request']['uri'] ?? '' ) );
	if ( 'database' === $job['phase'] && false !== strpos( $uri, '/restore' ) && is_file( '/digi-ctl/die-rest' ) && @unlink( '/digi-ctl/die-rest' ) ) digi_r_kill( 'die-rest' );
	if ( 'database' === $job['phase'] && (int) ( $cursor['offset'] ?? 0 ) > 0 && is_file( '/digi-ctl/die-db' ) && @unlink( '/digi-ctl/die-db' ) ) digi_r_kill( 'die-db' );
	if ( 'files' === $job['phase'] && 'extract' === ( $cursor['step'] ?? '' ) && (int) ( $cursor['i'] ?? 0 ) > 0 && is_file( '/digi-ctl/die-files' ) && @unlink( '/digi-ctl/die-files' ) ) digi_r_kill( 'die-files' );
	return 400;
} );
// El plan B: con la marca, la restauración (no su vuelta a la copia previa) falla al mover el código, ya hecho el RENAME.
add_action( 'digitalisimo_backups_restore_move_unit', static function ( $unit, $job ) {
	if ( empty( $job['spec']['rollback'] ) && is_file( '/digi-ctl/fail-move' ) ) throw new RuntimeException( 'fallo inyectado al mover los archivos' );
}, 10, 2 );
$GLOBALS['digi_r_request'] = array( 'uri' => (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 'action' => (string) ( $_REQUEST['action'] ?? '' ) );
// Dónde estaba la restauración al empezar y al terminar la petición (fase, paso y posición), para el informe.
function digi_r_where() {
	$job = class_exists( 'Digitalisimo_Backups_Jobs', false ) ? Digitalisimo_Backups_Jobs::restore_active() : null;
	if ( ! $job ) return '';
	$cursor = (array) ( $job['checkpoint'][ $job['phase'] ] ?? array() );
	return substr( (string) $job['id'], 0, 8 ) . ':' . $job['phase'] . ':' . ( $cursor['step'] ?? $cursor['sub'] ?? '' ) . ':' . ( $cursor['i'] ?? $cursor['offset'] ?? $cursor['line'] ?? '' );
}
add_action( 'init', static function () { $GLOBALS['digi_r_request']['init'] = microtime( true ); $GLOBALS['digi_r_request']['from'] = digi_r_where(); }, -1000 );
register_shutdown_function( static function () {
	$request = $GLOBALS['digi_r_request'];
	$cron = false !== strpos( $request['uri'], 'wp-cron.php' );
	$uri = rawurldecode( $request['uri'] );
	$rest = false !== strpos( $uri, 'digitalisimo-backups' ) && false !== strpos( $uri, '/restore' );
	if ( ! $cron && ! $rest && ! in_array( $request['action'], array( 'digitalisimo_backups_job_tick', 'digitalisimo_backups_restore_tick', 'digitalisimo_backups_manual_start' ), true ) ) return;
	$start = (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
	$restore = class_exists( 'Digitalisimo_Backups_Jobs', false ) ? Digitalisimo_Backups_Jobs::restore_active() : null;
	file_put_contents( '/digi-ctl/requests.jsonl', json_encode( array(
		'kind' => $cron ? 'wp-cron' : ( $rest ? 'rest-restore' : $request['action'] ),
		'start' => $start,
		'seconds' => round( microtime( true ) - $start, 3 ),
		'work' => isset( $request['init'] ) ? round( microtime( true ) - $request['init'], 3 ) : null,
		'peak_mb' => round( memory_get_peak_usage( true ) / 1048576, 1 ),
		'memory_limit' => ini_get( 'memory_limit' ),
		'request_limit' => class_exists( 'Digitalisimo_Backups_Step_Budget', false ) ? Digitalisimo_Backups_Step_Budget::request_limit() : null,
		'restore_phase' => $restore ? $restore['phase'] : '',
		'from' => $request['from'] ?? '',
		'to' => digi_r_where(),
		'error' => error_get_last()['message'] ?? '',
	) ) . "\n", FILE_APPEND );
} );
// Inventario y cambios del ensayo, con su testigo.
add_action( 'init', static function () {
	if ( empty( $_GET['digi_lab'] ) || ! hash_equals( trim( (string) @file_get_contents( '/digi-probe/token.txt' ) ), (string) ( $_GET['token'] ?? '' ) ) ) return;
	require_once '/digi-probe/lab.php';
	header( 'Content-Type: application/json' );
	echo wp_json_encode( 'inventory' === $_GET['digi_lab'] ? digi_lab_inventory() : digi_lab_mutate( (int) ( $_GET['round'] ?? 1 ) ) );
	exit;
}, 1 );
PHP;
	if ( false === file_put_contents( WPMU_PLUGIN_DIR . '/digi-restore-lab.php', $mu ) ) throw new RuntimeException( 'No se escribió el mu-plugin del ensayo.' );

	// Contenido: entradas con metadatos y opciones serializadas (en una red, también en el subsitio «uno»).
	$fill = function( $posts, $label ) {
		global $wpdb;
		$paragraph = str_repeat( 'Contenido del ensayo de restauración con acentos (año, ñandú), "comillas" y \\ barra. ', 30 );
		for ( $start = 1; $start <= $posts; $start += 50 ) {
			$values = array();
			for ( $i = $start; $i < $start + 50 && $i <= $posts; $i++ ) $values[] = $wpdb->prepare( '(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)', 1, gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s' ), $paragraph . ' #' . $i, $label . ' ' . $i, '', 'publish', sanitize_title( $label ) . '-' . $i, '', '', '' );
			$wpdb->query( "INSERT INTO {$wpdb->posts} (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, post_name, to_ping, pinged, post_content_filtered) VALUES " . implode( ',', $values ) );
			if ( $wpdb->last_error ) throw new RuntimeException( 'No se insertaron las entradas: ' . $wpdb->last_error );
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE %s", $wpdb->esc_like( sanitize_title( $label ) ) . '-%' ) );
		foreach ( array_chunk( $ids, 100 ) as $chunk ) {
			$values = array();
			foreach ( $chunk as $id ) $values[] = $wpdb->prepare( '(%d, %s, %s)', $id, 'campo_ensayo', serialize( array( 'id' => (int) $id, 'url' => home_url( '/?p=' . $id ), 'ñ' => 'ñandú' ) ) );
			$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $values ) );
		}
		update_option( 'digi_lab_opcion', array( 'anidada' => array( 'url' => home_url( '/' ), 'n' => 7 ), 'texto' => 'ñandú' ) );
	};
	$posts = (int) ( getenv( 'DIGI_RESTORE_POSTS' ) ?: 1200 );
	$fill( $posts, 'Entrada' );
	$sites = array( 1 );
	if ( $network ) {
		$domain = (string) get_network()->domain;
		$path = (string) get_network()->path . 'uno/';
		if ( is_subdomain_install() ) { $domain = 'uno.' . $domain; $path = (string) get_network()->path; }
		$blog = wpmu_create_blog( $domain, $path, 'Uno', 1, array( 'public' => 1 ), get_current_network_id() );
		if ( is_wp_error( $blog ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $blog->get_error_message() );
		switch_to_blog( $blog );
		$fill( 200, 'Subsitio' );
		restore_current_blog();
		$sites[] = (int) $blog;
	}
	// Un plugin «demo» (una unidad de código), un archivo propio del tema y medios en varias carpetas.
	foreach ( array( 'demo.php' => "<?php\n/* Plugin Name: Demo del ensayo */\n", 'inc/a.php' => "<?php // a original\n", 'inc/b.php' => "<?php // b original\n" ) as $name => $content ) { wp_mkdir_p( dirname( WP_PLUGIN_DIR . '/demo/' . $name ) ); file_put_contents( WP_PLUGIN_DIR . '/demo/' . $name, $content ); }
	file_put_contents( get_stylesheet_directory() . '/extra-ensayo.txt', "archivo del tema\n" );
	$uploads = wp_upload_dir( null, false )['basedir'];
	$files = (int) ( getenv( 'DIGI_RESTORE_FILES' ) ?: 1500 );
	for ( $i = 0; $i < $files; $i++ ) {
		$folder = $uploads . '/2026/' . sprintf( '%02d', $i % 12 + 1 );
		wp_mkdir_p( $folder );
		file_put_contents( $folder . '/medio-' . $i . '.jpg', random_bytes( 2048 ) . str_repeat( 'x', 2048 ) );
	}
	for ( $i = 0; $i < 8; $i++ ) file_put_contents( $uploads . '/2026/11/grande-' . $i . '.bin', random_bytes( 1048576 ) );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'network' => $network, 'sites' => $sites, 'posts' => $posts, 'files' => $files + 8, 'php' => PHP_VERSION, 'sapi' => PHP_SAPI ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
