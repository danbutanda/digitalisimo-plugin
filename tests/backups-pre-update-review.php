<?php
/**
 * I1 · respaldo antes de actualizar, hallazgos de la revisión adversaria (H1 a H9), con el motor real:
 *
 * - H1 tope: con dos respaldos previos fallidos seguidos o 48 h desde la primera retención, WordPress actualiza igual
 *   durante 24 h, llega «Actualizado sin respaldo previo» aunque «Fallos» esté apagado y el escritorio lo avisa; el
 *   núcleo menor de la misma rama, lo forzado por WordPress.org y el propio Backups no se retienen; el correo del núcleo
 *   de WordPress sólo se calla mientras el primer respaldo está en marcha; un respaldo previo lento no caduca a las 24 h.
 * - H2 WP-CLI: el respaldo va antes del actualizador (`before_invoke`) y sólo si hay algo que actualizar; con el sitio
 *   en mantenimiento la guarda no respalda; un fallo no se repite por cada plugin del lote; `.maintenance` no entra en
 *   el ZIP.
 * - H3 red: WP-CLI con `--url` de un subsitio hace avanzar la cola del sitio principal y la espera tiene tope.
 * - H4 tras un fallo reciente, la puerta web responde con el motivo y no encola otro respaldo.
 * - H5 los respaldos previos no consumen la retención global. H6 un ZIP dañado del mismo tamaño no cuenta (ni uno con
 *   archivos a medias). H8 GET /pre-update no encola nada. H9 «sin respaldo» vale sólo para la cuenta que lo pidió.
 */
$root = sys_get_temp_dir() . '/digitalisimo-preupd-review-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );

// El entorno de cobertura trae add_filter() desde la 1.0.66 (en $GLOBALS['digi']['filters']); si no, como add_action().
if ( ! function_exists( 'add_filter' ) ) { function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return add_action( $hook, $callback, $priority, $args ); } }
/** Lo enganchado a una acción o un filtro. */
function digi_hooked( $hook ) { return array_merge( $GLOBALS['digi']['actions'][ $hook ] ?? array(), $GLOBALS['digi']['filters'][ $hook ] ?? array() ); }
$GLOBALS['doing'] = array();
function doing_action( $hook ) { return in_array( $hook, $GLOBALS['doing'], true ); }
$GLOBALS['single'] = array();
function wp_schedule_single_event( $timestamp, $hook, $args = array() ) { $GLOBALS['single'][] = $hook; return true; }
function spawn_cron() { return true; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false; }
$GLOBALS['mails'] = array();
function wp_mail( $to, $subject, $body ) { $GLOBALS['mails'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body ); return true; }
$GLOBALS['transients'] = array();
function get_site_transient( $name ) { return $GLOBALS['transients'][ $name ] ?? false; }
// Red: switch_to_blog() al sitio principal (el entorno sólo distingue principal o subsitio).
$GLOBALS['blogs'] = array();
function switch_to_blog( $id ) { $GLOBALS['blogs'][] = $GLOBALS['digi']['main']; $GLOBALS['digi']['main'] = 1 === (int) $id; return true; }
function restore_current_blog() { $GLOBALS['digi']['main'] = array_pop( $GLOBALS['blogs'] ); return true; }
class WP_Upgrader {}
class Core_Upgrader extends WP_Upgrader {}
class Plugin_Upgrader extends WP_Upgrader {}
class Theme_Upgrader extends WP_Upgrader {}

$P = 'Digitalisimo_Backups_Pre_Update';
$B = 'Digitalisimo_Backups';
$J = 'Digitalisimo_Backups_Jobs';
update_option( 'admin_email', 'admin@example.test' );
update_option( $B::OPTION, array( 'retention' => '100' ) );
$auto = function( $type, $item, $update = true ) use ( $P ) {
	$GLOBALS['doing'][] = 'wp_maybe_auto_update';
	try { return call_user_func( array( $P, 'auto_' . $type ), $update, (object) $item ); } finally { array_pop( $GLOBALS['doing'] ); }
};
$age = function( $seconds ) use ( $B ) {
	$history = (array) get_option( $B::HISTORY, array() );
	foreach ( $history as &$row ) $row['date'] = gmdate( 'Y-m-d H:i:s', time() - $seconds );
	unset( $row );
	update_option( $B::HISTORY, $history );
	// El último respaldo previo bueno envejece igual (la ventana del respaldo reciente).
	$state = Digitalisimo_Backups_Keys::get( Digitalisimo_Backups_Pre_Update::STATE, array() );
	if ( is_array( $state ) && ! empty( $state['last_ok'] ) ) { $state['last_ok'] = time() - $seconds; Digitalisimo_Backups_Keys::put( Digitalisimo_Backups_Pre_Update::STATE, $state ); }
};
$fail = function( $phase, $job ) {
	if ( 'database' === $phase ) throw new Digitalisimo_Backups_Permanent_Error( 'Sin espacio para el respaldo de prueba.' );
	return Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
};
$set_state = function( array $changes ) use ( $P ) { update_option( $P::STATE, array_merge( (array) get_option( $P::STATE, array() ), $changes ) ); };
$reset = function() use ( $P, $J, $age ) {
	foreach ( $J::active() as $job ) { $J::cancel( $job['id'] ); }
	$J::tick();
	update_option( $P::STATE, array() );
	update_site_option( $P::STATE, array() ); // El estado de la red (sitemeta), también.
	$J::$phase_runner = null;
	$P::$cli = false;
	foreach ( array( 'cli_failure' => null, 'user' => null, 'clock' => 'time', 'cli_args' => null ) as $property => $value ) if ( property_exists( $P, $property ) ) $P::$$property = $value;
	$P::$sleep = function() {};
	$GLOBALS['digi']['multisite'] = false;
	$GLOBALS['digi']['main'] = true;
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	Digitalisimo_Backups_Keys::put( Digitalisimo_Backups_Notify::OPTION, array() );
	if ( is_file( ABSPATH . '.maintenance' ) ) unlink( ABSPATH . '.maintenance' );
	$GLOBALS['mails'] = array();
	$age( 30 * 3600 );
};
$pre_jobs = function() use ( $J ) { return count( array_filter( $J::all(), function( $job ) { return 'pre-update' === ( $job['spec']['policy'] ?? '' ); } ) ); };
$notice = function() use ( $P ) { ob_start(); $P::notice(); return ob_get_clean(); };
$update = array( 'plugin' => 'hello/hello.php', 'type' => 'plugin', 'action' => 'update' );

try {
	$P::$cli = false;
	$P::$sleep = function() {};
	Digitalisimo_Backups_Cli::$sleep = function() {};
	digi_assert( ! is_wp_error( digi_backup() ), 'El respaldo de partida falló.' );
	$reset();
	// Cada hallazgo se prueba por separado (con el código anterior, cada uno dice si falla) y vuelve al estado de partida.
	$failures = array();
	$ctx = compact( 'P', 'B', 'J', 'auto', 'age', 'fail', 'set_state', 'reset', 'pre_jobs', 'notice', 'update' );
	$case = function( $name, callable $test ) use ( &$failures, $ctx ) {
		try {
			$test( $ctx );
			echo 'ok · ' . $name . "\n";
		} catch ( Throwable $e ) {
			$failures[] = $name;
			echo 'FALLA · ' . $name . ': ' . preg_replace( '/^FALLA: /', '', $e->getMessage() ) . "\n";
		}
		try { $ctx['reset'](); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	};
	$case( 'H1 · dos respaldos previos fallidos seguidos', function( $ctx ) {
		extract( $ctx );
		// H1 · dos respaldos previos fallidos seguidos: la tercera pasada actualiza igual, con el correo (aunque «Fallos» esté
		// apagado), el aviso del escritorio y 24 h sin esperar ni reintentar.
		Digitalisimo_Backups_Keys::put( Digitalisimo_Backups_Notify::OPTION, array( 'failure' => false ) );
		$J::$phase_runner = $fail;
		for ( $i = 0; $i < 2; $i++ ) {
			digi_assert( false === $auto( 'plugin', array( 'plugin' => 'hello/hello.php', 'new_version' => '2.0' ) ), 'H1: la pasada ' . ( $i + 1 ) . ' no retuvo.' );
			digi_drive( $J::active()[0]['id'] );
			$set_state( array( 'failed_at' => time() - 3600 ) );
		}
		digi_assert( 2 === (int) get_option( $P::STATE )['fails'], 'H1: no se contaron los dos fallos seguidos: ' . json_encode( get_option( $P::STATE ) ) );
		$before = $pre_jobs();
		digi_assert( true === $auto( 'plugin', array( 'plugin' => 'hello/hello.php', 'new_version' => '2.0' ) ) && true === $auto( 'core', array( 'current' => '7.0' ) ) && true === $auto( 'theme', array( 'theme' => 'twentytwentysix' ) ), 'H1: tras dos fallos seguidos las automáticas siguieron retenidas.' );
		digi_assert( $before === $pre_jobs() && ! $J::active(), 'H1: la ventana sin respaldo lanzó otro respaldo previo.' );
		$subjects = array_column( $GLOBALS['mails'], 'subject' );
		digi_assert( 1 === count( preg_grep( '/Actualizado sin respaldo previo/', $subjects ) ) && false !== strpos( implode( "\n", array_column( $GLOBALS['mails'], 'body' ) ), 'Plugin hello/hello.php 2.0' ), 'H1: el correo «Actualizado sin respaldo previo» no llegó una vez con «Fallos» apagado: ' . json_encode( $subjects ) );
		digi_assert( ! preg_grep( '/El respaldo falló/', $subjects ), 'H1: con «Fallos» apagado llegó un correo de fallo.' );
		$status = $P::service_status();
		digi_assert( '' !== $status['bypass_until'] && false !== strpos( $status['bypass_reason'], '2 respaldos previos fallidos' ) && ! $status['retained'], 'H1: el estado no muestra la ventana sin respaldo: ' . json_encode( $status ) );
		digi_assert( false !== strpos( $notice(), 'sin respaldo previo hasta' ), 'H1: el escritorio no avisa de las actualizaciones sin respaldo.' );
		// Revisión de 1.0.74 (H11): la línea de la franja de Backups dice, en su detalle, qué actualizaciones esperaban.
		$strip = $P::strip_item();
		digi_assert( $status['bypassed'] && is_array( $strip ) && false !== strpos( $strip['detail'], '2 respaldos previos fallidos' ) && false !== strpos( $strip['detail'], 'esperaban: ' . implode( ', ', $status['bypassed'] ) ), 'H1: la franja de Backups no dice qué actualizaciones esperaban: ' . json_encode( $strip ) . ' · ' . json_encode( $status['bypassed'] ) );
		Digitalisimo_Backups_Keys::put( Digitalisimo_Backups_Notify::OPTION, array() );
	} );
	$case( 'H1 · 48 h desde la primera retención con el respaldo todavía en marcha', function( $ctx ) {
		extract( $ctx );
		// H1 · 48 h desde la primera retención con el respaldo todavía en marcha: actualiza igual.
		digi_assert( false === $auto( 'core', array( 'current' => '7.0' ) ), 'H1: el núcleo 7.0 no se retuvo.' );
		$set_state( array( 'since' => time() - 49 * 3600 ) );
		digi_assert( true === $auto( 'core', array( 'current' => '7.0' ) ) && 1 === count( preg_grep( '/Actualizado sin respaldo previo/', array_column( $GLOBALS['mails'], 'subject' ) ) ), 'H1: a las 49 h de retención el núcleo siguió retenido o no avisó.' );
		digi_assert( false !== strpos( implode( "\n", array_column( $GLOBALS['mails'], 'body' ) ), 'más de 48 h' ), 'H1: el correo del tope no dice el motivo.' );
	} );
	$case( 'H1 · nunca se retienen el núcleo menor de la misma rama, lo forzado por WordPress.org y el propio Backups', function( $ctx ) {
		extract( $ctx );
		// H1 · nunca se retienen el núcleo menor de la misma rama, lo forzado por WordPress.org y el propio Backups.
		$GLOBALS['wp_version'] = '6.9.1';
		digi_assert( true === $auto( 'core', array( 'current' => '6.9.2' ) ) && ! $J::active(), 'H1: se retuvo el núcleo menor 6.9.2 (de 6.9.1).' );
		digi_assert( true === $auto( 'plugin', array( 'plugin' => 'akismet/akismet.php', 'autoupdate' => true ) ) && ! $J::active(), 'H1: se retuvo una actualización forzada por WordPress.org.' );
		digi_assert( true === $auto( 'plugin', array( 'plugin' => 'digitalisimo-backups/digitalisimo-backups.php' ) ) && ! $J::active(), 'H1: se retuvo la actualización del propio Backups.' );
		digi_assert( false === $P::gate_download( false, '', new Plugin_Upgrader(), array( 'plugin' => 'digitalisimo-backups/digitalisimo-backups.php', 'type' => 'plugin', 'action' => 'update' ) ) && ! $J::active(), 'H1: la actualización manual del propio Backups esperó un respaldo.' );
		digi_assert( false === $auto( 'core', array( 'current' => '7.0' ) ), 'H1: el núcleo 7.0 (otra rama) no se retuvo.' );
		unset( $GLOBALS['wp_version'] );
	} );
	$case( 'H1 · aviso fijo mientras espera y, tras un fallo, pospuestas; el correo del núcleo sólo se calla la primera vez', function( $ctx ) {
		extract( $ctx );
		// H1 · aviso fijo mientras espera y, tras un fallo, pospuestas; el correo del núcleo sólo se calla la primera vez.
		digi_assert( false === $auto( 'core', array( 'current' => '7.0' ) ), 'H1: el núcleo 7.0 no se retuvo.' );
		digi_assert( false !== strpos( $notice(), 'en espera del respaldo previo' ), 'H1: el escritorio no avisa de la retención en curso.' );
		// Revisión de 1.0.74 (H11): la franja también dice que se actualizarán igual, sin respaldo, pasado el tope.
		digi_assert( false !== strpos( (string) ( $P::strip_item()['detail'] ?? '' ), 'Se actualizarán igual, sin respaldo' ), 'H1: la franja de Backups no dice que se actualizarán igual sin respaldo: ' . json_encode( $P::strip_item() ) );
		$silenced = count( digi_hooked( 'send_core_update_notification_email' ) );
		$J::$phase_runner = $fail;
		digi_drive( $J::active()[0]['id'] );
		digi_assert( false !== strpos( $notice(), 'pospuestas' ), 'H1: el escritorio no avisa de las pospuestas.' );
		digi_assert( false === $auto( 'core', array( 'current' => '7.0' ) ) && $silenced === count( digi_hooked( 'send_core_update_notification_email' ) ), 'H1: con las actualizaciones ya pospuestas se calló el correo del núcleo de WordPress.' );
	} );
	$case( 'H1 · un respaldo previo lento (más de 24 h) no caduca; a las 72 h, sí', function( $ctx ) {
		extract( $ctx );
		// H1 · un respaldo previo lento (más de 24 h) no caduca; a las 72 h, sí.
		$auto( 'plugin', array( 'plugin' => 'hello/hello.php' ) );
		$slow = $J::active()[0];
		digi_assert( 259200 === (int) ( $slow['spec']['expires_after'] ?? 0 ), 'H1: el respaldo previo no lleva su caducidad propia.' );
		$backdate = function( $id, $seconds ) { Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id, $seconds ) { $job = Digitalisimo_Backups_Job_Store::read( $id ); $job['created_at'] = time() - $seconds; Digitalisimo_Backups_Job_Store::write( $job ); } ); };
		$backdate( $slow['id'], 86401 );
		$J::tick();
		$after = $J::get( $slow['id'] );
		digi_assert( 'expired' !== ( $after['error']['code'] ?? '' ), 'H1: un respaldo previo de más de 24 h caducó: ' . json_encode( $after['error'] ) );
		$reset();
		$auto( 'plugin', array( 'plugin' => 'hello/hello.php' ) );
		$slow = $J::active()[0];
		$backdate( $slow['id'], 259201 );
		$J::tick();
		digi_assert( 'expired' === ( $J::get( $slow['id'] )['error']['code'] ?? '' ), 'H1: un respaldo previo de más de 72 h no caducó.' );
	} );
	$case( 'H2 · WP-CLI', function( $ctx ) {
		extract( $ctx );
		// H2 · WP-CLI: el respaldo antes del actualizador, sólo si hay algo que actualizar (sin contar Backups).
		$P::$cli = true;
		$GLOBALS['transients']['update_plugins'] = (object) array( 'response' => array( 'digitalisimo-backups/digitalisimo-backups.php' => (object) array() ) );
		$P::cli_before( 'plugin' );
		digi_assert( ! $J::active() && null === $P::fresh(), 'H2: con sólo Backups por actualizar, WP-CLI hizo un respaldo previo.' );
		$GLOBALS['transients']['update_plugins'] = (object) array( 'response' => array( 'hello/hello.php' => (object) array() ) );
		$P::cli_before( 'plugin' );
		digi_assert( $P::fresh() && ! $J::active(), 'H2: before_invoke no hizo el respaldo previo antes del actualizador.' );
		file_put_contents( ABSPATH . '.maintenance', '<?php $upgrading = ' . time() . ';' );
		digi_assert( false === $P::gate_download( false, '', new Plugin_Upgrader(), $update ), 'H2: con el respaldo hecho antes, la guarda rechazó la actualización.' );
		// .maintenance nunca entra en el ZIP.
		$row = digi_backup();
		digi_assert( ! is_wp_error( $row ), 'H2: el respaldo con .maintenance falló.' );
		$zip = new ZipArchive();
		digi_assert( true === $zip->open( DIGITALISIMO_BACKUPS_STORAGE_DIR . '/' . $row['file'] ) && false === $zip->locateName( 'wordpress/.maintenance' ), 'H2: el ZIP guardó el .maintenance.' );
		$zip->close();
		// Sin respaldo y con el sitio en mantenimiento, la guarda no respalda: rechaza con el motivo.
		$age( 30 * 3600 );
		$jobs = count( $J::all() );
		$blocked = $P::gate_download( false, '', new Plugin_Upgrader(), $update );
		digi_assert( is_wp_error( $blocked ) && false !== strpos( $blocked->get_error_message(), 'mantenimiento' ) && count( $J::all() ) === $jobs, 'H2: con el sitio en mantenimiento la guarda de WP-CLI hizo o encoló un respaldo.' );
		unlink( ABSPATH . '.maintenance' );
		$P::$cli_failure = null;
		// Un fallo en el proceso no se repite por cada plugin del lote.
		$J::$phase_runner = $fail;
		$before = $pre_jobs();
		$P::cli_before( 'plugin' );
		foreach ( array( 'a/a.php', 'b/b.php', 'c/c.php' ) as $plugin ) {
			$blocked = $P::gate_download( false, '', new Plugin_Upgrader(), array( 'plugin' => $plugin, 'type' => 'plugin', 'action' => 'update' ) );
			digi_assert( is_wp_error( $blocked ) && false !== strpos( $blocked->get_error_message(), 'Sin espacio' ), 'H2: un plugin del lote no se rechazó con el motivo del fallo.' );
		}
		digi_assert( 1 === $pre_jobs() - $before, 'H2: el lote lanzó ' . ( $pre_jobs() - $before ) . ' respaldos previos (debía ser uno).' );
	} );
	$case( 'H3 · red', function( $ctx ) {
		extract( $ctx );
		// H3 · red: WP-CLI con --url de un subsitio hace avanzar la cola del sitio principal; la espera tiene tope.
		$GLOBALS['digi']['multisite'] = true;
		$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
		$GLOBALS['digi']['main'] = false;
		$P::$cli = true;
		$spins = 0;
		$P::$sleep = function() use ( &$spins ) { if ( ++$spins > 40 ) throw new RuntimeException( 'FALLA: H3: la espera de WP-CLI no avanza (vueltas sin fin desde el subsitio).' ); };
		$result = $P::gate_download( false, '', new Plugin_Upgrader(), $update );
		digi_assert( $GLOBALS['digi']['main'] === false, 'H3: no se volvió al subsitio.' );
		digi_assert( false === $result || ( is_wp_error( $result ) && false === strpos( $result->get_error_message(), 'no aparece como respaldo reciente' ) ), 'H3: desde el subsitio el respaldo de la red terminado no contó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
		$queued = array_values( array_filter( $J::all(), function( $job ) { return 'pre-update' === ( $job['spec']['policy'] ?? '' ); } ) );
		digi_assert( $queued && 'network' === end( $queued )['spec']['scope'] && Digitalisimo_Backups_Job_Store::is_terminal( end( $queued ) ), 'H3: el respaldo de la red no terminó desde el subsitio.' );
		// Tope de espera: con el almacén ocupado por otra operación, a los 20 min sin avance termina con el motivo.
		$GLOBALS['digi']['main'] = true;
		$reset();
		$GLOBALS['digi']['multisite'] = true;
		$P::$cli = true;
		$lock = digi_hold_lock();
		$now = time();
		$P::$clock = function() use ( &$now ) { $now += 300; return $now; };
		$P::$sleep = function() {};
		$blocked = $P::gate_download( false, '', new Plugin_Upgrader(), $update );
		digi_release_lock( $lock );
		digi_assert( is_wp_error( $blocked ) && false !== strpos( $blocked->get_error_message(), 'no avanzó en 20 minutos' ), 'H3: la espera de WP-CLI sin avance no terminó con el motivo.' );
		$P::$clock = 'time';
		$GLOBALS['digi']['multisite'] = false;
		$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	} );
	$case( 'H4 · tras un fallo reciente, la puerta web responde con el motivo y no encola otro respaldo; más tarde, sí', function( $ctx ) {
		extract( $ctx );
		// H4 · tras un fallo reciente, la puerta web responde con el motivo y no encola otro respaldo; más tarde, sí.
		$J::$phase_runner = $fail;
		$P::gate_download( false, '', new Plugin_Upgrader(), $update );
		digi_drive( $J::active()[0]['id'] );
		$before = $pre_jobs();
		foreach ( array( 'a/a.php', 'b/b.php' ) as $plugin ) {
			$blocked = $P::gate_download( false, '', new Plugin_Upgrader(), array( 'plugin' => $plugin, 'type' => 'plugin', 'action' => 'update' ) );
			digi_assert( is_wp_error( $blocked ) && false !== strpos( $blocked->get_error_message(), 'Sin espacio' ) && false === strpos( $blocked->get_error_message(), 'está creando' ), 'H4: tras el fallo la puerta web no dio el motivo: ' . ( is_wp_error( $blocked ) ? $blocked->get_error_message() : 'pasó' ) );
		}
		digi_assert( $before === $pre_jobs(), 'H4: tras el fallo la puerta web encoló otro respaldo.' );
		$set_state( array( 'failed_at' => time() - 1000 ) );
		digi_assert( is_wp_error( $P::gate_download( false, '', new Plugin_Upgrader(), $update ) ) && $before + 1 === $pre_jobs(), 'H4: pasado el fallo reciente, la puerta web no volvió a encolar.' );
	} );
	$case( 'H5 · los respaldos previos no consumen la retención global de los del usuario', function( $ctx ) {
		extract( $ctx );
		// H5 · los respaldos previos no consumen la retención global de los del usuario.
		update_option( $B::OPTION, array( 'retention' => '2' ) );
		$a = digi_backup();
		$b = digi_backup();
		$age( 30 * 3600 );
		$auto( 'plugin', array( 'plugin' => 'hello/hello.php', 'new_version' => '2.0' ) );
		digi_drive( $J::active()[0]['id'] );
		$p = get_option( $B::HISTORY )[0];
		digi_assert( 'pre-update' === $p['policy'], 'H5: no hubo respaldo previo.' );
		$dir = DIGITALISIMO_BACKUPS_STORAGE_DIR . '/';
		// Los respaldos de los bloques anteriores, más viejos que A y B: la rotación decide sólo entre estos.
		foreach ( (array) get_option( $B::HISTORY ) as $old ) if ( ! in_array( $old['file'], array( $a['file'], $b['file'], $p['file'] ), true ) && is_file( $dir . $old['file'] ) ) touch( $dir . $old['file'], time() - 10000 );
		touch( $dir . $a['file'], time() - 300 );
		touch( $dir . $b['file'], time() - 200 );
		touch( $dir . $p['file'], time() - 100 );
		clearstatcache();
		$c = digi_backup();
		clearstatcache();
		$left = array( 'A' => is_file( $dir . $a['file'] ), 'B' => is_file( $dir . $b['file'] ), 'previo' => is_file( $dir . $p['file'] ), 'C' => is_file( $dir . $c['file'] ) );
		digi_assert( array( 'A' => false, 'B' => true, 'previo' => true, 'C' => true ) === $left, 'H5: la retención global contó el respaldo previo: ' . json_encode( $left ) );
		update_option( $B::OPTION, array( 'retention' => '100' ) );
	} );
	$case( 'H6 · un ZIP dañado del mismo tamaño no cuenta (ni el principio ni el final), tampoco uno con archivos a medias', function( $ctx ) {
		extract( $ctx );
		// H6 · un ZIP dañado del mismo tamaño no cuenta (ni el principio ni el final), tampoco uno con archivos a medias.
		$row = digi_backup();
		$path = DIGITALISIMO_BACKUPS_STORAGE_DIR . '/' . $row['file'];
		$good = file_get_contents( $path );
		digi_assert( $P::fresh(), 'H6: el respaldo sano no contó.' );
		file_put_contents( $path, str_repeat( "\0", 4096 ) . substr( $good, 4096 ) );
		clearstatcache();
		digi_assert( null === $P::fresh() && is_wp_error( $P::gate_download( false, '', new Plugin_Upgrader(), $update ) ), 'H6: un ZIP con el principio a ceros contó como respaldo válido.' );
		foreach ( $J::active() as $job ) $J::cancel( $job['id'] );
		$J::tick();
		update_option( $P::STATE, array() );
		file_put_contents( $path, substr( $good, 0, -200 ) . str_repeat( "\0", 200 ) );
		clearstatcache();
		digi_assert( null === $P::fresh(), 'H6: un ZIP sin el final (EOCD) contó como respaldo válido.' );
		file_put_contents( $path, $good );
		clearstatcache();
		digi_assert( $P::fresh(), 'H6: el ZIP restaurado no volvió a contar.' );
		$history = get_option( $B::HISTORY );
		$history[0]['notices'] = array( array( 'code' => 'files_changed', 'text' => 'carpetas a medias' ) );
		update_option( $B::HISTORY, $history );
		// Revisión N1: los avisos de archivos (una caché que desaparece) no descalifican el respaldo.
		digi_assert( (bool) $P::fresh(), 'N1: un respaldo con el aviso files_changed no contó como válido.' );
	} );
	$case( 'H8 · GET /pre-update no encola nada (aunque lo esperado sea un respaldo ajeno que falló con automáticas retenidas)', function( $ctx ) {
		extract( $ctx );
		// H8 · GET /pre-update no encola nada (aunque lo esperado sea un respaldo ajeno que falló con automáticas retenidas).
		$queued = Digitalisimo_Backups::service_create( array() );
		digi_assert( ! is_wp_error( $queued ), 'H8: no se encoló el respaldo manual.' );
		$set_state( array( 'job' => $queued['id'], 'auto' => true, 'since' => time(), 'items' => array( 'Plugin hello/hello.php' ) ) );
		$J::$phase_runner = $fail;
		Digitalisimo_Backups_Job_Store::with_lock( function() use ( $queued ) { $job = Digitalisimo_Backups_Job_Store::read( $queued['id'] ); $job['status'] = 'failed'; $job['finished_at'] = time(); $job['error'] = array( 'code' => 'permanent', 'message' => 'Sin espacio.', 'permanent' => true ); Digitalisimo_Backups_Job_Store::write( $job ); } );
		$jobs = count( $J::all() );
		$runs = count( $GLOBALS['single'] );
		Digitalisimo_Backups_Rest::register();
		call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
		list( $code, $body ) = digi_rest( 'GET', 'digitalisimo-backups/v1/pre-update' );
		digi_assert( 200 === $code && $body['postponed'] && $jobs === count( $J::all() ) && $runs === count( $GLOBALS['single'] ), 'H8: GET /pre-update tuvo efectos (encoló o programó): ' . json_encode( $body ) );
	} );
	$case( 'H9 · «sin respaldo» vale sólo para la cuenta que lo pidió', function( $ctx ) {
		extract( $ctx );
		// H9 · «sin respaldo» vale sólo para la cuenta que lo pidió.
		$P::service_skip( array( 'via' => 'rest' ) );
		$P::$user = 9;
		digi_assert( is_wp_error( $P::gate_download( false, '', new Plugin_Upgrader(), $update ) ), 'H9: «sin respaldo» de otra cuenta dejó actualizar sin respaldo.' );
		$P::$user = null;
		digi_assert( false === $P::gate_download( false, '', new Plugin_Upgrader(), $update ), 'H9: «sin respaldo» no valió para la cuenta que lo pidió.' );
	} );
	$case( 'N1 · un respaldo previo que termina bien con files_missing libera las automáticas y no se repite', function( $ctx ) {
		extract( $ctx );
		// Una caché que desaparece entre el inventario y la copia: el respaldo termina bien, con el aviso files_missing.
		$J::$phase_runner = function( $phase, $job ) {
			$out = Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
			if ( 'pack' === $phase && 'verify' === ( $out['next'] ?? '' ) ) $out['warnings'][] = array( 'notice' => 'files_missing', 'text' => '1 archivo(s) desaparecieron entre el inventario y su copia y no están en el respaldo (wp-content/cache/x.html).' );
			return $out;
		};
		$before = $pre_jobs();
		$seq = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$seq[] = $auto( 'plugin', array( 'plugin' => 'akismet/akismet.php', 'new_version' => '9' ) ) ? 'sí' : 'no';
			foreach ( $J::active() as $job ) digi_drive( $job['id'] );
		}
		digi_assert( array( 'no', 'sí', 'sí', 'sí' ) === $seq && 1 === $pre_jobs() - $before, 'N1: con un respaldo previo que termina con files_missing, pasadas ' . implode( ',', $seq ) . ' y ' . ( $pre_jobs() - $before ) . ' respaldos previos (debía actualizar en la segunda con uno solo).' );
		$row = get_option( $B::HISTORY )[0];
		digi_assert( 'pre-update' === $row['policy'] && 'files_missing' === ( $row['notices'][0]['code'] ?? '' ), 'N1: el respaldo previo no quedó con su aviso.' );
		// Límite 1 de la segunda revisión: si el respaldo previo se borra dentro de su ventana, ya no cuenta y se permite UNO
		// más; si ése también se daña, cuenta igual (nunca respaldos completos sin fin).
		unlink( DIGITALISIMO_BACKUPS_STORAGE_DIR . '/' . $row['file'] );
		clearstatcache();
		digi_assert( false === $auto( 'plugin', array( 'plugin' => 'akismet/akismet.php', 'new_version' => '9' ) ) && 2 === $pre_jobs() - $before, 'N1: con el respaldo previo borrado, la actualización siguió sin respaldo o no se lanzó uno nuevo.' );
		foreach ( $J::active() as $job ) digi_drive( $job['id'] );
		digi_assert( true === $auto( 'plugin', array( 'plugin' => 'akismet/akismet.php', 'new_version' => '9' ) ), 'N1: con el respaldo previo nuevo, la actualización no siguió.' );
		$second = get_option( $B::HISTORY )[0];
		$path = DIGITALISIMO_BACKUPS_STORAGE_DIR . '/' . $second['file'];
		file_put_contents( $path, str_repeat( "\0", 4096 ) . substr( (string) file_get_contents( $path ), 4096 ) );
		clearstatcache();
		digi_assert( null === $P::fresh() && true === $auto( 'plugin', array( 'plugin' => 'akismet/akismet.php', 'new_version' => '9' ) ) && 2 === $pre_jobs() - $before, 'N1: tras el respaldo de más de la ventana, uno dañado lanzó otro respaldo previo.' );
	} );
	$case( 'N4 · WP-CLI no respalda sin nada del núcleo pendiente ni con --dry-run', function( $ctx ) {
		extract( $ctx );
		$P::$cli = true;
		$args = function( array $positional, array $named ) use ( $P ) { if ( property_exists( $P, 'cli_args' ) ) $P::$cli_args = array( $positional, $named ); };
		$before = $pre_jobs();
		$args( array( 'core', 'update' ), array() );
		$GLOBALS['transients']['update_core'] = (object) array( 'updates' => array( (object) array( 'response' => 'latest' ) ) );
		$P::cli_before( 'core' );
		digi_assert( $before === $pre_jobs(), 'N4: wp core update sin nada pendiente hizo un respaldo previo.' );
		$GLOBALS['transients']['update_plugins'] = (object) array( 'response' => array( 'hello/hello.php' => (object) array() ) );
		$args( array( 'plugin', 'update' ), array( 'all' => true, 'dry-run' => true ) );
		$P::cli_before( 'plugin' );
		digi_assert( $before === $pre_jobs(), 'N4: wp plugin update --dry-run hizo un respaldo previo.' );
		$args( array( 'core', 'update' ), array() );
		$GLOBALS['transients']['update_core'] = (object) array( 'updates' => array( (object) array( 'response' => 'upgrade' ) ) );
		$P::cli_before( 'core' );
		digi_assert( $before + 1 === $pre_jobs() && $P::fresh(), 'N4: con una actualización del núcleo pendiente no se hizo el respaldo previo.' );
		unset( $GLOBALS['transients']['update_core'], $GLOBALS['transients']['update_plugins'] );
	} );
	$case( 'N5 · «sin respaldo» de WP-CLI vale para la consola con cualquier --user', function( $ctx ) {
		extract( $ctx );
		$P::$cli = true;
		$P::service_skip( array( 'via' => 'cli' ) );
		$P::$user = 5;
		$before = $pre_jobs();
		digi_assert( false === $P::gate_download( false, '', new Plugin_Upgrader(), $update ) && $before === $pre_jobs(), 'N5: con «sin respaldo» de WP-CLI, wp plugin update --user=… esperó o hizo un respaldo.' );
		$P::$cli = false;
		digi_assert( is_wp_error( $P::gate_download( false, '', new Plugin_Upgrader(), $update ) ), 'N5: el «sin respaldo» de WP-CLI dejó actualizar sin respaldo en la web.' );
	} );
	if ( $failures ) throw new RuntimeException( 'FALLA: ' . count( $failures ) . ' hallazgo(s) de la revisión sin arreglar: ' . implode( ' | ', $failures ) );
	echo "Respaldo antes de actualizar, revisión: tope de la retención, exenciones, aviso fijo, caducidad propia, WP-CLI antes del actualizador y fuera del mantenimiento, red, fallo reciente, retención global, ZIP dañado, GET sin efectos y «sin respaldo» por cuenta: OK\n";
} finally {
	digi_rmtree( $root );
}
