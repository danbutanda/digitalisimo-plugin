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
 *   U4  REST real: GET /pre-update como administrador (de la red, en Multisite);
 *   U5  WP-CLI (revisión H2): bulk_upgrade() de un plugin activo; el respaldo va antes del actualizador y nunca con el
 *       sitio en mantenimiento; sin él, la guarda rechaza sin respaldar;
 *   U6  red (revisión H3): desde un subsitio, el respaldo, la cola y el historial son del sitio principal;
 *   U7  tope (revisión H1): con dos respaldos previos fallidos seguidos la automática se hace igual, con su correo.
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
		// El último respaldo previo bueno envejece igual (la ventana del respaldo reciente).
		$state = Digitalisimo_Backups_Keys::get( Digitalisimo_Backups_Pre_Update::STATE, array() );
		if ( is_array( $state ) && ! empty( $state['last_ok'] ) ) { $state['last_ok'] = time() - 30 * 3600; Digitalisimo_Backups_Keys::put( Digitalisimo_Backups_Pre_Update::STATE, $state ); }
	};
	$drive = function() use ( $J ) {
		for ( $i = 0; $i < 80; $i++ ) {
			if ( ! $J::active() ) return;
			foreach ( $J::active() as $job ) Digitalisimo_Backups_Job_Store::with_lock( function() use ( $job ) { $fresh = Digitalisimo_Backups_Job_Store::read( $job['id'] ); if ( $fresh && ! Digitalisimo_Backups_Job_Store::is_terminal( $fresh ) ) { $fresh['retry_at'] = 0; Digitalisimo_Backups_Job_Store::write( $fresh ); } } );
			$J::tick();
		}
		throw new RuntimeException( 'La cola no terminó el respaldo previo.' );
	};
	// Cada pasada de WP_Automatic_Updater termina con wp_clean_plugins_cache(), que borra la oferta (con red, la vuelve a
	// pedir a WordPress.org): sin red, la sonda la repone antes de cada pasada.
	$auto_pass = function( $new ) use ( $offer ) {
		$offer( $new );
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
	$before = count( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) );
	$t = microtime( true );
	$auto_pass( '3.0' );
	$took = microtime( true ) - $t;
	$status = $P::service_status();
	$check( '2.0' === $version() && 1 === count( $J::active() ) && $before === count( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) ), 'U2 wp_maybe_auto_update retuvo el plugin y sólo encoló el respaldo (' . round( $took, 1 ) . ' s, sin respaldar en esa pasada)' );
	$check( in_array( 'Plugin ' . $file . ' 3.0', $status['retained'], true ) && ! $status['postponed'], 'U2 el estado muestra la actualización retenida' );
	$check( false === wp_next_scheduled( 'wp_maybe_auto_update' ), 'U2 no se relanza nada antes del respaldo' );
	$drive();
	$next = wp_next_scheduled( 'wp_maybe_auto_update' );
	$check( false !== $next && $next <= time() + 5, 'U2 al terminar el respaldo, wp_maybe_auto_update vuelve a estar programado' );
	$count = count( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) );
	$auto_pass( '3.0' );
	$check( '3.0' === $version() && ! $J::active() && $count === count( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) ), 'U2 la siguiente pasada actualizó a 3.0 sin otro respaldo' );

	// U3 · El respaldo falla: pospuesta con aviso; la pasada siguiente lo reintenta.
	$age();
	$mails = array();
	$J::$phase_runner = function( $phase, $job ) {
		if ( 'database' === $phase ) throw new Digitalisimo_Backups_Permanent_Error( 'Fallo simulado de la sonda.' );
		return Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
	};
	$auto_pass( '4.0' );
	$drive();
	$status = $P::service_status();
	$subjects = implode( ' | ', array_map( function( $mail ) { return (string) $mail['subject']; }, $mails ) );
	$bodies = implode( "\n", array_map( function( $mail ) { return (string) $mail['message']; }, $mails ) );
	$check( '3.0' === $version() && $status['postponed'] && false !== strpos( $status['failure'], 'Fallo simulado' ), 'U3 con el respaldo fallido la automática queda pospuesta (3.0)' );
	$check( false !== strpos( $subjects, 'El respaldo falló' ) && false !== strpos( $bodies, 'quedan pospuestas' ), 'U3 llegó el aviso por correo: ' . $subjects );
	$check( false === wp_next_scheduled( 'wp_maybe_auto_update' ), 'U3 un respaldo fallido no relanza las automáticas' );
	$J::$phase_runner = null;
	$auto_pass( '4.0' );
	$check( '3.0' === $version() && 1 === count( $J::active() ), 'U3 la siguiente pasada reintenta el respaldo sin actualizar' );
	$drive();
	$auto_pass( '4.0' );
	$check( '4.0' === $version() && ! $P::service_status()['postponed'], 'U3 con el reintento bien, la automática actualizó a 4.0' );

	// U5 · WP-CLI con bulk_upgrade() de un plugin ACTIVO (el actualizador pone el sitio en mantenimiento): el respaldo va
	// antes (before_invoke) y nunca con .maintenance presente; sin él, la guarda rechaza sin respaldar en mantenimiento.
	$P::$cli = true;
	$P::$sleep = function() { static $spins = 0; if ( ++$spins > 400 ) throw new RuntimeException( 'La espera de WP-CLI no avanza.' ); };
	wp_clean_plugins_cache( false );
	$activated = activate_plugin( $file, '', is_multisite() );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó digi-demo: ' . $activated->get_error_message() );
	$in_maintenance = array();
	$J::$phase_runner = function( $phase, $job ) use ( &$in_maintenance ) {
		$in_maintenance[ $phase ] = file_exists( ABSPATH . '.maintenance' );
		return Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
	};
	$age();
	$offer( '5.0' );
	$P::cli_before( 'plugin' );
	$check( $P::fresh() && $in_maintenance && ! array_filter( $in_maintenance ), 'U5 before_invoke hizo el respaldo previo antes del actualizador, sin mantenimiento (fases: ' . implode( ', ', array_keys( $in_maintenance ) ) . ')' );
	$in_maintenance = array();
	$skin = new WP_Ajax_Upgrader_Skin();
	( new Plugin_Upgrader( $skin ) )->bulk_upgrade( array( $file ) );
	$check( '5.0' === $version() && ! $in_maintenance && ! $skin->get_errors()->has_errors(), 'U5 bulk_upgrade() de un plugin activo actualizó a 5.0 sin otro respaldo' );
	$age();
	$offer( '6.0' );
	$skin = new WP_Ajax_Upgrader_Skin();
	( new Plugin_Upgrader( $skin ) )->bulk_upgrade( array( $file ) );
	$message = (string) $skin->get_errors()->get_error_message( 'digitalisimo_backups_pre_update' );
	$check( '5.0' === $version() && ! $in_maintenance && false !== strpos( $message, 'mantenimiento' ) && ! file_exists( ABSPATH . '.maintenance' ), 'U5 sin respaldo previo, la guarda rechazó sin respaldar con el sitio en mantenimiento (' . $message . ')' );
	$J::$phase_runner = null;
	$P::$cli_failure = null;
	deactivate_plugins( $file, true, is_multisite() );

	// U6 · red: WP-CLI con --url de un subsitio (switch_to_blog): el respaldo, la cola y el historial son del principal.
	if ( is_multisite() ) {
		$sub = wp_insert_site( array( 'domain' => 'playground.test', 'path' => '/uno/', 'title' => 'Uno', 'user_id' => 1 ) );
		if ( is_wp_error( $sub ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $sub->get_error_message() );
		$age();
		$offer( '7.0' );
		switch_to_blog( (int) $sub );
		$skin = new WP_Ajax_Upgrader_Skin();
		( new Plugin_Upgrader( $skin ) )->upgrade( $file );
		$errors = $skin->get_errors();
		$fresh_here = $P::fresh();
		restore_current_blog();
		$row = $last();
		$check( ! $errors->has_errors() && '7.0' === $version() && 'pre-update' === ( $row['policy'] ?? '' ) && 'network' === ( $row['scope'] ?? '' ) && $fresh_here, 'U6 desde el subsitio, WP-CLI hizo el respaldo de la red en el principal y actualizó a 7.0 (' . ( $row['file'] ?? '' ) . ')' );
	}
	$P::$cli = false;

	// U7 · tope: dos respaldos previos fallidos seguidos y la tercera pasada actualiza igual, con el correo.
	$age();
	$mails = array();
	$J::$phase_runner = function( $phase, $job ) {
		if ( 'database' === $phase ) throw new Digitalisimo_Backups_Permanent_Error( 'Fallo simulado de la sonda.' );
		return Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
	};
	$target = is_multisite() ? '8.0' : '7.0';
	for ( $i = 0; $i < 2; $i++ ) {
		$auto_pass( $target );
		$drive();
		$state = get_site_option( $P::STATE ) ?: get_option( $P::STATE );
		if ( is_multisite() ) { $state['failed_at'] = time() - 3600; update_site_option( $P::STATE, $state ); } else { $state['failed_at'] = time() - 3600; update_option( $P::STATE, $state, false ); }
	}
	$before = $version();
	$auto_pass( $target );
	$subjects = implode( ' | ', array_map( function( $mail ) { return (string) $mail['subject']; }, $mails ) );
	$check( $target === $version() && $before !== $target && false !== strpos( $subjects, 'Actualizado sin respaldo previo' ), 'U7 tras dos respaldos previos fallidos seguidos, la automática se hizo igual (' . $target . ') y llegó «Actualizado sin respaldo previo»' );
	$J::$phase_runner = null;
	delete_site_option( $P::STATE );
	delete_option( $P::STATE );

	// U4 · REST real.
	$response = rest_do_request( new WP_REST_Request( 'GET', '/digitalisimo-backups/v1/pre-update' ) );
	$data = $response->get_data();
	$check( 200 === $response->get_status() && 'all' === $data['settings']['plugin'] && array_key_exists( 'fresh', $data ) && array_key_exists( 'bypass_until', $data ), 'U4 GET /pre-update responde 200 con los ajustes y el estado' );
	wp_set_current_user( 0 );
	$check( 401 === rest_do_request( new WP_REST_Request( 'GET', '/digitalisimo-backups/v1/pre-update' ) )->get_status(), 'U4 sin sesión, 401' );
	$log( 'TERMINADO en ' . $elapsed() );
} catch ( Throwable $e ) {
	$log( 'FALLA: ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
}
