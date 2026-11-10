<?php
/**
 * Respaldo antes de actualizar (I1) en un WordPress real (Playground, SQLite, php-wasm), con los actualizadores reales
 * de WordPress y un plugin de prueba («digi-demo», inactivo) cuyos paquetes son ZIP locales. Sin red: toda petición HTTP
 * se corta. Escribe cada resultado en result.txt; una línea «FALLA» detiene el arnés.
 *
 *   U1  manual: Plugin_Upgrader::upgrade() sin respaldo reciente se rechaza con el motivo de Backups, el plugin no
 *       cambia y el respaldo previo queda en la cola; con la cola terminada, la misma actualización se hace;
 *   U2  automática: `wp_maybe_auto_update` real (WP_Automatic_Updater) retiene el plugin con auto-actualización activa,
 *       encola el respaldo sin hacerlo en esa pasada; al terminar, `wp_maybe_auto_update` vuelve a estar programado y la
 *       siguiente pasada actualiza sin otro respaldo;
 *   U3  fallo: con el respaldo fallido la automática queda pospuesta, llega el correo y no se programa otra pasada; la
 *       siguiente pasada lo reintenta, y con el respaldo bien, actualiza;
 *   U4  REST real: GET /pre-update como administrador (de la red, en Multisite).
 */
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', '/var/digitalisimo-backups-probe' );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
$dir = '/digitalisimo-probe';
$result = $dir . '/result.txt';
@unlink( $result );
$log = function( $line ) use ( $result ) { file_put_contents( $result, $line . "\n", FILE_APPEND ); };
$check = function( $ok, $what ) use ( $log ) { if ( ! $ok ) throw new RuntimeException( $what ); $log( 'ok · ' . $what ); };
// Sin red: los paquetes son locales y WP-Cron lo simula la sonda.
add_filter( 'pre_http_request', function() { return new WP_Error( 'digitalisimo_probe_offline', 'La sonda no usa la red.' ); }, 1 );
$mails = array();
add_filter( 'pre_wp_mail', function( $pre, $atts ) use ( &$mails ) { $mails[] = $atts; return true; }, 10, 2 );
$timer = microtime( true );
$elapsed = function() use ( $timer ) { return round( microtime( true ) - $timer ) . ' s'; };
try {
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', is_multisite() );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( $activated->get_error_message() );
	if ( ! class_exists( 'Digitalisimo_Backups' ) ) require_once WP_PLUGIN_DIR . '/digitalisimo-backups/digitalisimo-backups.php';
	if ( ! has_filter( 'auto_update_plugin', array( 'Digitalisimo_Backups_Pre_Update', 'auto_plugin' ) ) ) { Digitalisimo_Backups::init(); Digitalisimo_Backups_Rest::register(); }
	wp_set_current_user( 1 );
	$P = 'Digitalisimo_Backups_Pre_Update';
	$J = 'Digitalisimo_Backups_Jobs';
	$log( 'modo: ' . ( is_multisite() ? 'red' : 'sitio individual' ) . ' · WordPress ' . get_bloginfo( 'version' ) . ' · PHP ' . PHP_VERSION );

	// El plugin de prueba 1.0 y sus paquetes (ZIP locales: download_package() los acepta tal cual).
	$file = 'digi-demo/digi-demo.php';
	$header = function( $version ) { return "<?php\n/**\n * Plugin Name: Digi Demo\n * Version: " . $version . "\n */\n"; };
	wp_mkdir_p( WP_PLUGIN_DIR . '/digi-demo' );
	file_put_contents( WP_PLUGIN_DIR . '/' . $file, $header( '1.0' ) );
	$version = function() use ( $file ) { wp_clean_plugins_cache( false ); return get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false )['Version']; };
	$offer = function( $new ) use ( $file, $header ) {
		$zip = get_temp_dir() . 'digi-demo-' . $new . '.zip';
		$archive = new ZipArchive();
		$archive->open( $zip, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$archive->addFromString( 'digi-demo/digi-demo.php', $header( $new ) );
		$archive->close();
		wp_clean_plugins_cache( false );
		$transient = new stdClass();
		$transient->last_checked = time();
		$transient->checked = array_map( function( $plugin ) { return $plugin['Version']; }, get_plugins() );
		$transient->response = array( $file => (object) array( 'id' => 'digi-demo', 'slug' => 'digi-demo', 'plugin' => $file, 'new_version' => $new, 'url' => '', 'package' => $zip ) );
		$transient->no_update = array();
		$transient->translations = array();
		set_site_transient( 'update_plugins', $transient );
	};
	// Todos los respaldos del historial «de hace 30 h»: ninguno cuenta como reciente.
	$age = function() {
		$history = (array) get_option( Digitalisimo_Backups::HISTORY, array() );
		foreach ( $history as &$row ) $row['date'] = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - 30 * 3600 );
		unset( $row );
		update_option( Digitalisimo_Backups::HISTORY, $history );
	};
	$drive = function() use ( $J ) {
		for ( $i = 0; $i < 80; $i++ ) {
			if ( ! $J::active() ) return;
			foreach ( $J::active() as $job ) Digitalisimo_Backups_Job_Store::with_lock( function() use ( $job ) { $fresh = Digitalisimo_Backups_Job_Store::read( $job['id'] ); if ( $fresh && ! Digitalisimo_Backups_Job_Store::is_terminal( $fresh ) ) { $fresh['retry_at'] = 0; Digitalisimo_Backups_Job_Store::write( $fresh ); } } );
			$J::tick();
		}
		throw new RuntimeException( 'La cola no terminó el respaldo previo.' );
	};
	$auto_pass = function() {
		wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
		delete_option( 'auto_updater.lock' );
		do_action( 'wp_maybe_auto_update' );
	};
	$last = function() { $history = (array) get_option( Digitalisimo_Backups::HISTORY, array() ); return $history ? $history[0] : array(); };

	// U1 · Manual.
	$offer( '2.0' );
	$skin = new WP_Ajax_Upgrader_Skin();
	( new Plugin_Upgrader( $skin ) )->upgrade( $file );
	$errors = $skin->get_errors();
	$check( in_array( 'digitalisimo_backups_pre_update', $errors->get_error_codes(), true ) && '1.0' === $version(), 'U1 la actualización manual sin respaldo se rechaza con el motivo de Backups y el plugin sigue en 1.0 (' . $errors->get_error_message( 'digitalisimo_backups_pre_update' ) . ')' );
	$queued = $J::active();
	$check( 1 === count( $queued ) && 'pre-update' === $queued[0]['spec']['policy'] && ( is_multisite() ? 'network' : 'installation' ) === $queued[0]['spec']['scope'], 'U1 el respaldo previo queda en la cola, con el alcance de ' . ( is_multisite() ? 'la red' : 'la instalación' ) );
	$drive();
	$row = $last();
	$check( 'pre-update' === ( $row['policy'] ?? '' ) && $P::fresh(), 'U1 la cola terminó el respaldo previo (' . ( $row['file'] ?? '' ) . ', ' . $elapsed() . ')' );
	$skin = new WP_Ajax_Upgrader_Skin();
	( new Plugin_Upgrader( $skin ) )->upgrade( $file );
	$check( ! $skin->get_errors()->has_errors() && '2.0' === $version(), 'U1 con el respaldo listo, la misma actualización manual se hace (2.0)' );

	// U2 · Automática.
	update_site_option( 'auto_update_plugins', array( $file ) );
	$age();
	$offer( '3.0' );
	$before = count( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) );
	$t = microtime( true );
	$auto_pass();
	$took = microtime( true ) - $t;
	$status = $P::service_status();
	$check( '2.0' === $version() && 1 === count( $J::active() ) && $before === count( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) ), 'U2 wp_maybe_auto_update retuvo el plugin y sólo encoló el respaldo (' . round( $took, 1 ) . ' s, sin respaldar en esa pasada)' );
	$check( in_array( 'Plugin ' . $file . ' 3.0', $status['retained'], true ) && ! $status['postponed'], 'U2 el estado muestra la actualización retenida' );
	$check( false === wp_next_scheduled( 'wp_maybe_auto_update' ), 'U2 no se relanza nada antes del respaldo' );
	$drive();
	$next = wp_next_scheduled( 'wp_maybe_auto_update' );
	$check( false !== $next && $next <= time() + 5, 'U2 al terminar el respaldo, wp_maybe_auto_update vuelve a estar programado' );
	$count = count( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) );
	$auto_pass();
	$check( '3.0' === $version() && ! $J::active() && $count === count( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) ), 'U2 la siguiente pasada actualizó a 3.0 sin otro respaldo' );

	// U3 · El respaldo falla: pospuesta con aviso; la pasada siguiente lo reintenta.
	$age();
	$offer( '4.0' );
	$mails = array();
	$J::$phase_runner = function( $phase, $job ) {
		if ( 'database' === $phase ) throw new Digitalisimo_Backups_Permanent_Error( 'Fallo simulado de la sonda.' );
		return Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
	};
	$auto_pass();
	$drive();
	$status = $P::service_status();
	$subjects = implode( ' | ', array_map( function( $mail ) { return (string) $mail['subject']; }, $mails ) );
	$bodies = implode( "\n", array_map( function( $mail ) { return (string) $mail['message']; }, $mails ) );
	$check( '3.0' === $version() && $status['postponed'] && false !== strpos( $status['failure'], 'Fallo simulado' ), 'U3 con el respaldo fallido la automática queda pospuesta (3.0)' );
	$check( false !== strpos( $subjects, 'El respaldo falló' ) && false !== strpos( $bodies, 'quedan pospuestas' ), 'U3 llegó el aviso por correo: ' . $subjects );
	$check( false === wp_next_scheduled( 'wp_maybe_auto_update' ), 'U3 un respaldo fallido no relanza las automáticas' );
	$J::$phase_runner = null;
	$auto_pass();
	$check( '3.0' === $version() && 1 === count( $J::active() ), 'U3 la siguiente pasada reintenta el respaldo sin actualizar' );
	$drive();
	$auto_pass();
	$check( '4.0' === $version() && ! $P::service_status()['postponed'], 'U3 con el reintento bien, la automática actualizó a 4.0' );

	// U4 · REST real.
	$response = rest_do_request( new WP_REST_Request( 'GET', '/digitalisimo-backups/v1/pre-update' ) );
	$data = $response->get_data();
	$check( 200 === $response->get_status() && 'all' === $data['settings']['plugin'] && $data['fresh'], 'U4 GET /pre-update responde 200 con los ajustes y el respaldo reciente' );
	wp_set_current_user( 0 );
	$check( 401 === rest_do_request( new WP_REST_Request( 'GET', '/digitalisimo-backups/v1/pre-update' ) )->get_status(), 'U4 sin sesión, 401' );
	$log( 'TERMINADO en ' . $elapsed() );
} catch ( Throwable $e ) {
	$log( 'FALLA: ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
}
