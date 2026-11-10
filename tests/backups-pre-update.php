<?php
/**
 * I1 · Respaldo antes de actualizar el núcleo, los plugins y los temas, con el motor real (cola, ZIP e historial).
 *
 * - Automáticas (`auto_update_*` dentro de `wp_maybe_auto_update`): sin un respaldo reciente válido se retienen, se
 *   encola UN trabajo y la petición no espera; cuando termina bien se vuelve a programar `wp_maybe_auto_update` y la
 *   siguiente pasada actualiza sin otro respaldo. Si falla, quedan pospuestas, llega el correo y no se libera nada; la
 *   siguiente pasada lo reintenta. Si ni siquiera puede encolarse, se posponen y avisa una vez cada 12 h.
 * - Ajuste por tipo (all, auto, off) y antigüedad máxima: un respaldo reciente válido evita repetirlo; uno viejo, de
 *   otro alcance, subido o sin su ZIP, no.
 * - Manuales (`upgrader_pre_download`): sin respaldo se rechaza con un motivo claro y el respaldo queda en marcha;
 *   instalar algo nuevo y las traducciones no esperan; «Actualizar sin respaldo» deja pasar las manuales unos minutos
 *   pero nunca las automáticas; en WP-CLI el respaldo corre en el mismo proceso.
 * - Pantalla, AJAX, REST y WP-CLI sobre los mismos casos de uso; paridad sitio/red (sitemeta, sitio principal, alcance).
 */
$root = sys_get_temp_dir() . '/digitalisimo-preupd-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );

// Lo que este caso necesita y el entorno de cobertura no trae.
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return add_action( $hook, $callback, $priority, $args ); }
$GLOBALS['doing'] = array();
function doing_action( $hook ) { return in_array( $hook, $GLOBALS['doing'], true ); }
$GLOBALS['single'] = array();
$GLOBALS['spawned'] = 0;
function wp_schedule_single_event( $timestamp, $hook, $args = array() ) { $GLOBALS['single'][] = $hook; return true; }
function spawn_cron() { $GLOBALS['spawned']++; return true; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false; }
function wp_create_nonce( $action ) { return 'nonce-' . $action; }
function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/digitalisimo-backups/includes/' . $path; }
$GLOBALS['scripts'] = array();
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) { $GLOBALS['scripts'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'footer' => $footer, 'inline' => '' ); }
function wp_add_inline_script( $handle, $data, $position = 'after' ) { $GLOBALS['scripts'][ $handle ]['inline'] .= $data; return true; }
$GLOBALS['mails'] = array();
function wp_mail( $to, $subject, $body ) { $GLOBALS['mails'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body ); return true; }
class Digi_Json extends Exception { public $ok; public $data; public $status; public function __construct( $ok, $data, $status ) { parent::__construct( 'json' ); $this->ok = $ok; $this->data = $data; $this->status = $status; } }
function wp_send_json_success( $data = null, $status = 200 ) { throw new Digi_Json( true, $data, $status ); }
function wp_send_json_error( $data = null, $status = 400 ) { throw new Digi_Json( false, $data, $status ); }
function check_ajax_referer( $action, $arg ) { if ( ( $_REQUEST[ $arg ] ?? '' ) !== 'nonce-' . $action ) throw new Digi_Die( 'Nonce AJAX inválido para ' . $action ); return 1; }
// Las clases de los actualizadores de WordPress (sólo importa de cuál es cada uno).
class WP_Upgrader {}
class Core_Upgrader extends WP_Upgrader {}
class Plugin_Upgrader extends WP_Upgrader {}
class Theme_Upgrader extends WP_Upgrader {}
class Language_Pack_Upgrader extends WP_Upgrader {}

$P = 'Digitalisimo_Backups_Pre_Update';
$B = 'Digitalisimo_Backups';
$J = 'Digitalisimo_Backups_Jobs';
update_option( 'admin_email', 'admin@example.test' );
// La rotación ordena por fecha de modificación y estos respaldos nacen en el mismo segundo: sin rotar.
update_option( $B::OPTION, array( 'retention' => '100' ) );
Digitalisimo_Backups_Cli::$sleep = function() {};

/** Una pasada de las actualizaciones automáticas de WordPress (dentro de `wp_maybe_auto_update`). */
$auto = function( $type, $item, $update = true ) use ( $P ) {
	$GLOBALS['doing'][] = 'wp_maybe_auto_update';
	try {
		return call_user_func( array( $P, 'auto_' . $type ), $update, (object) $item );
	} finally {
		array_pop( $GLOBALS['doing'] );
	}
};
/** Trabajos de respaldo sin terminar. */
$active = function() use ( $J ) { return $J::active(); };
/** Envejece todas las filas del historial (un respaldo «de hace» $seconds). */
$age = function( $seconds ) use ( $B ) {
	$history = (array) get_option( $B::HISTORY, array() );
	foreach ( $history as &$row ) $row['date'] = gmdate( 'Y-m-d H:i:s', time() - $seconds );
	unset( $row );
	update_option( $B::HISTORY, $history );
};
$fail_database = function( $phase, $job ) {
	if ( 'database' === $phase ) throw new Digitalisimo_Backups_Permanent_Error( 'Sin espacio en /home/usuario/privado/almacen para el respaldo de prueba.' );
	return Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
};
$state = function() use ( $P ) { return get_option( $P::STATE, array() ); };
$auto_runs = function() { return count( array_filter( $GLOBALS['single'], function( $hook ) { return 'wp_maybe_auto_update' === $hook; } ) ); };
$ajax = function( $handler, array $post ) use ( $P ) {
	$_POST = $post;
	$_REQUEST = $post;
	try {
		call_user_func( array( $P, $handler ) );
	} catch ( Digi_Json $reply ) {
		return array( $reply->ok, $reply->data, $reply->status );
	}
	throw new RuntimeException( 'FALLA: ' . $handler . ' terminó sin responder JSON.' );
};
$admin_post = function( $handler, $action, array $post ) use ( $P ) {
	$_POST = $post;
	$_REQUEST = array_merge( $post, array( '_wpnonce' => 'nonce-' . $action ) );
	try {
		call_user_func( array( $P, $handler ) );
	} catch ( Digi_Redirect $redirect ) {
		return $redirect->query;
	}
	throw new RuntimeException( 'FALLA: ' . $handler . ' terminó sin redirigir.' );
};

try {
	$P::$cli = false;
	// 0. Enganches: los tres filtros automáticos, la puerta de las manuales, la pantalla y el watchdog.
	$P::init();
	foreach ( array( 'auto_update_core', 'auto_update_plugin', 'auto_update_theme', 'upgrader_pre_download', 'digitalisimo_backups_job_origin', 'admin_post_' . $P::ACTION_SAVE, 'wp_ajax_' . $P::AJAX_START, 'wp_ajax_' . $P::AJAX_STATUS, 'wp_ajax_' . $P::AJAX_SKIP, 'admin_enqueue_scripts', $J::HOOK_WATCHDOG ) as $hook ) digi_assert( ! empty( $GLOBALS['digi']['actions'][ $hook ] ), 'No se engancha ' . $hook );
	digi_assert( array( 'core' => 'all', 'plugin' => 'all', 'theme' => 'all', 'max_age' => 24 ) === $P::settings(), 'Ajustes por defecto inesperados: ' . json_encode( $P::settings() ) );
	digi_assert( 'Respaldo antes de actualizar' === $P::origin( '', array( 'policy' => 'pre-update' ) ) && 'Otro' === $P::origin( 'Otro', array( 'policy' => 'pre-update' ) ) && '' === $P::origin( '', array( 'policy' => 'manual' ) ), 'La etiqueta de origen del trabajo no es la esperada.' );

	// 1. Fuera de `wp_maybe_auto_update` (la lista de plugins, Salud del sitio) el filtro no cambia nada ni encola.
	digi_assert( true === $P::auto_plugin( true, (object) array( 'plugin' => 'hello/hello.php' ) ) && null === $P::auto_plugin( null, (object) array() ), 'El filtro cambió una decisión fuera de las actualizaciones automáticas.' );
	digi_assert( ! $active(), 'Se encoló un respaldo fuera de las actualizaciones automáticas.' );
	// WordPress no iba a actualizar: el filtro no lo decide.
	digi_assert( false === $auto( 'plugin', array( 'plugin' => 'hello/hello.php' ), false ) && ! $active(), 'Una actualización que WordPress no iba a hacer encoló un respaldo.' );

	// 2. Automáticas sin respaldo: se retienen las tres y se encola UN trabajo, sin esperarlo ni hacer el respaldo aquí.
	$start = microtime( true );
	$core = $auto( 'core', array( 'current' => '6.9.1' ) );
	$plugin = $auto( 'plugin', array( 'plugin' => 'hello/hello.php', 'new_version' => '2.0' ) );
	$theme = $auto( 'theme', array( 'theme' => 'twentytwentysix', 'new_version' => '1.2' ) );
	$elapsed = microtime( true ) - $start;
	digi_assert( false === $core && false === $plugin && false === $theme, 'Una actualización automática siguió sin respaldo.' );
	digi_assert( $elapsed < 3, 'Retener las actualizaciones tardó ' . round( $elapsed, 2 ) . ' s: la petición no debe esperar al respaldo.' );
	$jobs = $active();
	digi_assert( 1 === count( $jobs ), 'Se esperaba un solo respaldo previo encolado, hay ' . count( $jobs ) );
	$job = $jobs[0];
	digi_assert( 'pre-update' === $job['spec']['policy'] && 'local' === $job['spec']['destination'] && 'policy' === $job['spec']['retention'] && 'cron' === $job['spec']['origin']['via'] && 'installation' === $job['spec']['scope'], 'El trabajo previo no tiene la especificación esperada: ' . json_encode( $job['spec'] ) );
	digi_assert( 'queued' === $job['status'] && ! get_option( $B::HISTORY ), 'El respaldo se hizo dentro del filtro en vez de por la cola.' );
	$status = $P::service_status();
	digi_assert( 3 === count( $status['retained'] ) && in_array( 'WordPress 6.9.1', $status['retained'], true ) && in_array( 'Plugin hello/hello.php 2.0', $status['retained'], true ) && ! $status['postponed'] && $status['job'] && $job['id'] === $status['job']['id'], 'El estado no muestra las actualizaciones retenidas: ' . json_encode( $status ) );
	digi_assert( ! empty( $GLOBALS['digi']['actions']['send_core_update_notification_email'] ), 'El núcleo retenido debería callar el correo de «hay una versión nueva».' );
	digi_assert( 0 === $auto_runs(), 'Se relanzaron las automáticas antes de tener el respaldo.' );

	// 3. Con un tipo en «Nunca», ese tipo no espera (aunque los demás sí).
	digi_assert( ! is_wp_error( $P::store( array( 'theme' => 'off' ) ) ) && true === $auto( 'theme', array( 'theme' => 'twentytwentysix' ) ), 'Un tipo desactivado siguió esperando el respaldo.' );
	digi_assert( false === $auto( 'plugin', array( 'plugin' => 'akismet/akismet.php' ) ) && 1 === count( $active() ), 'Otra actualización retenida encoló un segundo respaldo.' );

	// 4. La cola hace el respaldo; al terminar, `wp_maybe_auto_update` se vuelve a programar y la siguiente pasada sigue.
	$done = digi_drive( $job['id'] );
	digi_assert( 'success' === $done['status'], 'El respaldo previo no terminó bien: ' . json_encode( $done ) );
	digi_assert( 1 === $auto_runs(), 'Al terminar el respaldo no se volvió a programar wp_maybe_auto_update.' );
	$history = get_option( $B::HISTORY );
	digi_assert( 'pre-update' === $history[0]['policy'] && 'installation' === $history[0]['scope'], 'El respaldo previo no quedó en el historial: ' . json_encode( $history[0] ) );
	$status = $P::service_status();
	digi_assert( ! $status['retained'] && ! $status['job'] && '' === $status['failure'] && $status['fresh'] && $history[0]['file'] === $status['fresh']['file'], 'El estado tras el respaldo no es el esperado: ' . json_encode( $status ) );
	digi_assert( true === $auto( 'core', array( 'current' => '6.9.1' ) ) && true === $auto( 'plugin', array( 'plugin' => 'hello/hello.php' ) ) && ! $active(), 'Con un respaldo reciente válido la actualización automática no siguió o se repitió el respaldo.' );

	// 5. Respaldo reciente: más viejo que la antigüedad máxima, de otro alcance, subido o sin su ZIP no cuenta.
	$age( 2 * 3600 );
	digi_assert( $P::fresh() && ! is_wp_error( $P::store( array( 'max_age' => 1 ) ) ) && null === $P::fresh(), 'Un respaldo de hace 2 h contó como reciente con 1 h de antigüedad máxima.' );
	digi_assert( ! is_wp_error( $P::store( array( 'max_age' => 24 ) ) ) && $P::fresh(), 'Un respaldo de hace 2 h no contó con 24 h.' );
	$saved = get_option( $B::HISTORY );
	foreach ( array( array( 'scope' => 'network' ), array( 'origin' => 'uploaded' ), array( 'policy' => 'pre-restore' ), array( 'size' => 1 ), array( 'date' => gmdate( 'Y-m-d H:i:s', time() + 7200 ) ) ) as $change ) {
		$rows = $saved;
		$rows[0] = array_merge( $rows[0], $change );
		update_option( $B::HISTORY, array( $rows[0] ) );
		digi_assert( null === $P::fresh(), 'Contó como respaldo reciente válido una fila con ' . json_encode( $change ) );
	}
	// Sin el ZIP local pero con la copia remota verificada sí cuenta (sólo en el remoto).
	$rows = $saved;
	$rows[0] = array_merge( $rows[0], array( 'file' => 'digitalisimo-backup-no-esta.zip', 'transfer_destination' => 'sftp', 'transfer' => 'ok' ) );
	update_option( $B::HISTORY, array( $rows[0] ) );
	digi_assert( $P::fresh(), 'Una copia remota verificada no contó como respaldo válido.' );
	$rows[0]['transfer'] = 'failed';
	update_option( $B::HISTORY, array( $rows[0] ) );
	digi_assert( null === $P::fresh(), 'Una copia remota fallida contó como respaldo válido.' );
	update_option( $B::HISTORY, $saved );
	$age( 30 * 3600 );
	digi_assert( null === $P::fresh() && false === $auto( 'plugin', array( 'plugin' => 'hello/hello.php' ) ) && 1 === count( $active() ), 'Un respaldo de hace 30 h evitó el respaldo previo.' );
	digi_drive( $active()[0]['id'] );
	digi_assert( 2 === $auto_runs(), 'El segundo respaldo previo no relanzó las automáticas.' );

	// 6. El respaldo falla: las automáticas quedan pospuestas, llega el correo (sin rutas) y no se relanza nada.
	$age( 30 * 3600 );
	$GLOBALS['mails'] = array();
	$J::$phase_runner = $fail_database;
	digi_assert( false === $auto( 'core', array( 'current' => '6.9.2' ) ), 'Se actualizó el núcleo sin respaldo.' );
	$failed = digi_drive( $active()[0]['id'] );
	digi_assert( 'failed' === $failed['status'], 'El respaldo previo de prueba no falló.' );
	$status = $P::service_status();
	digi_assert( $status['postponed'] && in_array( 'WordPress 6.9.2', $status['retained'], true ) && false !== strpos( $status['failure'], 'Sin espacio' ) && '' !== $status['failed_at'], 'Tras el fallo las automáticas no quedaron pospuestas: ' . json_encode( $status ) );
	digi_assert( 2 === $auto_runs(), 'Un respaldo fallido relanzó las actualizaciones automáticas.' );
	digi_assert( 1 === count( $GLOBALS['mails'] ), 'Se esperaba un correo del fallo, hubo ' . count( $GLOBALS['mails'] ) );
	$mail = $GLOBALS['mails'][0];
	digi_assert( false !== strpos( $mail['subject'], 'El respaldo falló' ) && false !== strpos( $mail['body'], 'Origen: respaldo antes de actualizar (WP-Cron)' ) && false !== strpos( $mail['body'], 'quedan pospuestas' ), 'El correo no dice que las actualizaciones quedan pospuestas: ' . $mail['body'] );
	digi_assert( false === strpos( $mail['body'], '/home/usuario' ), 'El correo lleva una ruta del servidor: ' . $mail['body'] );
	// La siguiente pasada lo vuelve a intentar (un trabajo nuevo) y, si sale bien, relanza las automáticas.
	$J::$phase_runner = null;
	digi_assert( false === $auto( 'core', array( 'current' => '6.9.2' ) ), 'Tras un fallo, la siguiente pasada actualizó sin respaldo.' );
	$retry = $active();
	digi_assert( 1 === count( $retry ) && $retry[0]['id'] !== $failed['id'], 'La siguiente pasada no reintentó el respaldo.' );
	digi_drive( $retry[0]['id'] );
	$status = $P::service_status();
	digi_assert( 3 === $auto_runs() && ! $status['postponed'] && ! $status['retained'] && '' === $status['failure'], 'El reintento correcto no liberó las automáticas: ' . json_encode( $status ) );

	// 7. Sin poder encolar (cifrado activo y sin clave): se posponen y avisa, como mucho una vez cada 12 h.
	$age( 30 * 3600 );
	$GLOBALS['mails'] = array();
	Digitalisimo_Backups_Keys::put( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => true ) );
	digi_assert( false === $auto( 'plugin', array( 'plugin' => 'hello/hello.php' ) ) && false === $auto( 'core', array( 'current' => '6.9.3' ) ) && ! $active(), 'Sin poder encolar el respaldo, una automática siguió o se encoló algo.' );
	digi_assert( 1 === count( $GLOBALS['mails'] ) && false !== strpos( $GLOBALS['mails'][0]['subject'], 'pospuestas' ) && false !== strpos( $GLOBALS['mails'][0]['body'], 'Plugin hello/hello.php' ), 'El aviso de un respaldo que no pudo encolarse no llegó una sola vez: ' . json_encode( $GLOBALS['mails'] ) );
	$status = $P::service_status();
	digi_assert( $status['postponed'] && false !== strpos( $status['failure'], 'No se pudo iniciar' ), 'El estado no dice que no se pudo iniciar: ' . json_encode( $status ) );
	Digitalisimo_Backups_Keys::put( Digitalisimo_Backups_Security::OPTION, array() );
	// Un respaldo manual válido libera las pospuestas sin esperar a la siguiente comprobación de WordPress.
	$runs = $auto_runs();
	digi_assert( ! is_wp_error( digi_backup() ), 'El respaldo manual de prueba falló.' );
	$status = $P::service_status();
	digi_assert( ! $status['postponed'] && ! $status['retained'] && '' === $status['failure'] && $runs + 1 === $auto_runs(), 'Un respaldo manual válido no liberó las automáticas pospuestas: ' . json_encode( $status ) );
	update_option( $P::STATE, array() );

	// 8. Manuales: sin respaldo, la descarga se rechaza con un motivo y el respaldo queda en marcha.
	$age( 30 * 3600 );
	$P::store( array( 'theme' => 'auto' ) );
	$update = array( 'plugin' => 'hello/hello.php', 'type' => 'plugin', 'action' => 'update' );
	$blocked = $P::gate_download( false, 'https://downloads.example.test/hello.zip', new Plugin_Upgrader(), $update );
	digi_assert( is_wp_error( $blocked ) && 'digitalisimo_backups_pre_update' === $blocked->get_error_code() && false !== strpos( $blocked->get_error_message(), 'creando un respaldo completo antes de actualizar' ), 'Una actualización manual sin respaldo no se rechazó con su motivo.' );
	$manual = $active();
	digi_assert( 1 === count( $manual ) && 'admin-post' === $manual[0]['spec']['origin']['via'], 'La actualización manual no dejó el respaldo en marcha.' );
	digi_assert( is_wp_error( $P::gate_download( false, '', new Core_Upgrader(), array() ) ) && 1 === count( $active() ), 'El núcleo pasó sin respaldo o encoló un segundo trabajo.' );
	// No esperan: instalar algo nuevo, traducciones, un tipo «sólo automáticas» o una decisión previa de otro filtro.
	digi_assert( false === $P::gate_download( false, '', new Plugin_Upgrader(), array( 'type' => 'plugin', 'action' => 'install' ) ), 'Instalar un plugin nuevo esperó un respaldo.' );
	digi_assert( false === $P::gate_download( false, '', new Language_Pack_Upgrader(), array() ), 'Una traducción esperó un respaldo.' );
	digi_assert( false === $P::gate_download( false, '', new Theme_Upgrader(), array( 'theme' => 'twentytwentysix' ) ), 'Un tipo «sólo automáticas» esperó en una actualización manual.' );
	$other = new WP_Error( 'otro', 'Otro filtro' );
	digi_assert( $other === $P::gate_download( $other, '', new Plugin_Upgrader(), $update ), 'La puerta pisó la decisión de otro filtro.' );
	$GLOBALS['doing'][] = 'wp_maybe_auto_update';
	digi_assert( false === $P::gate_download( false, '', new Plugin_Upgrader(), $update ), 'Dentro de las automáticas la puerta repitió la decisión del filtro.' );
	array_pop( $GLOBALS['doing'] );
	digi_drive( $manual[0]['id'] );
	digi_assert( false === $P::gate_download( false, '', new Plugin_Upgrader(), $update ) && false === $P::gate_download( false, '', new Core_Upgrader(), array() ), 'Con el respaldo listo la actualización manual siguió bloqueada.' );
	digi_assert( 3 === $auto_runs(), 'Un respaldo manual relanzó las automáticas sin que hubiera ninguna retenida.' );

	// 9. Manual con el respaldo fallido: «Actualizar sin respaldo» deja pasar las manuales unos minutos, nunca las automáticas.
	$age( 30 * 3600 );
	$J::$phase_runner = $fail_database;
	$blocked = $P::gate_download( false, '', new Plugin_Upgrader(), $update );
	$id = $active()[0]['id'];
	digi_drive( $id );
	$check = $P::check( $id );
	digi_assert( 'failed' === $check['state'] && false !== strpos( $check['message'], 'Sin espacio' ), 'La consulta del respaldo fallido no dice el motivo: ' . json_encode( $check ) );
	$skip = $P::service_skip( array( 'via' => 'rest' ) );
	digi_assert( ! is_wp_error( $skip ) && '' !== $skip['skip_until'], 'No se pudo elegir «Actualizar sin respaldo».' );
	digi_assert( false === $P::gate_download( false, '', new Plugin_Upgrader(), $update ) && ! $active(), '«Actualizar sin respaldo» no dejó pasar la actualización manual.' );
	digi_assert( false === $auto( 'plugin', array( 'plugin' => 'hello/hello.php' ) ), '«Actualizar sin respaldo» dejó pasar una automática.' );
	$J::cancel( $active()[0]['id'] );
	$J::tick();
	update_option( $P::STATE, array() );
	$J::$phase_runner = null;

	// 10. WP-CLI: el respaldo corre en el mismo proceso y la actualización sigue; si falla, se rechaza con la salida.
	$P::$cli = true;
	$P::$sleep = function() {};
	$age( 30 * 3600 );
	WP_CLI::$output = array();
	digi_assert( false === $P::gate_download( false, '', new Plugin_Upgrader(), $update ) && ! $active() && $P::fresh(), 'En WP-CLI el respaldo previo no se hizo en el mismo proceso.' );
	digi_assert( (bool) array_filter( WP_CLI::$output, function( $line ) { return 0 === strpos( $line, 'Respaldo antes de actualizar:' ); } ), 'WP-CLI no mostró el avance del respaldo previo.' );
	$age( 30 * 3600 );
	$J::$phase_runner = $fail_database;
	$blocked = $P::gate_download( false, '', new Core_Upgrader(), array() );
	digi_assert( is_wp_error( $blocked ) && false !== strpos( $blocked->get_error_message(), 'wp digitalisimo-backups pre-update skip' ) && false !== strpos( $blocked->get_error_message(), 'Sin espacio' ), 'En WP-CLI un respaldo fallido no rechazó la actualización con el motivo y la salida.' );
	$J::$phase_runner = null;
	$P::$cli = false;
	update_option( $P::STATE, array() );

	// 11. Pantalla: la tarjeta y su formulario; un valor desconocido no cambia nada.
	ob_start();
	$P::panel( false );
	$html = ob_get_clean();
	foreach ( array( 'Antes de actualizar', 'digitalisimo_backups_pre_update[core]', 'digitalisimo_backups_pre_update[plugin]', 'digitalisimo_backups_pre_update[theme]', 'digitalisimo_backups_pre_update[max_age]', 'nonce-' . $P::ACTION_SAVE ) as $needle ) digi_assert( false !== strpos( $html, $needle ), 'La tarjeta no muestra ' . $needle );
	$query = $admin_post( 'save', $P::ACTION_SAVE, array( 'digitalisimo_backups_pre_update' => array( 'core' => 'auto', 'plugin' => 'off', 'theme' => 'all', 'max_age' => '12' ) ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ) && array( 'core' => 'auto', 'plugin' => 'off', 'theme' => 'all', 'max_age' => 12 ) === $P::settings(), 'La pantalla no guardó los ajustes: ' . json_encode( $P::settings() ) );
	$query = $admin_post( 'save', $P::ACTION_SAVE, array( 'digitalisimo_backups_pre_update' => array( 'core' => 'siempre', 'plugin' => 'all' ) ) );
	digi_assert( 'error' === ( $query['backup'] ?? '' ) && 'auto' === $P::settings()['core'] && 'off' === $P::settings()['plugin'], 'Un modo desconocido cambió los ajustes.' );
	$GLOBALS['digi']['caps'] = array();
	try { $admin_post( 'save', $P::ACTION_SAVE, array( 'digitalisimo_backups_pre_update' => array( 'core' => 'off' ) ) ); throw new RuntimeException( 'FALLA: guardó sin permiso.' ); } catch ( Digi_Die $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );

	// 12. Pantallas de actualización: el script sólo para quien administra, sólo en esas pantallas y sin secretos.
	$P::enqueue( 'index.php' );
	digi_assert( ! $GLOBALS['scripts'], 'El script se cargó fuera de las pantallas de actualización.' );
	$P::enqueue( 'plugins.php' );
	$script = $GLOBALS['scripts']['digitalisimo-backups-pre-update'] ?? null;
	digi_assert( $script && false !== strpos( $script['src'], 'assets/pre-update.js' ) && in_array( 'updates', $script['deps'], true ) && $script['footer'], 'El script no se cargó en Plugins con updates.js.' );
	digi_assert( false !== strpos( $script['inline'], '"types":{"theme":true}' ), 'La configuración del script no sigue los tipos con «Manuales y automáticas»: ' . $script['inline'] );
	digi_assert( false === strpos( $script['inline'], DIGITALISIMO_BACKUPS_STORAGE_DIR ) && false === strpos( $script['inline'], AUTH_KEY ), 'La configuración del script lleva rutas o claves.' );
	$GLOBALS['scripts'] = array();
	$P::store( array( 'core' => 'off', 'plugin' => 'auto', 'theme' => 'off' ) );
	$P::enqueue( 'update-core.php' );
	digi_assert( ! $GLOBALS['scripts'], 'Sin tipos manuales el script se cargó igual.' );
	$GLOBALS['digi']['caps'] = array( 'update_plugins' => true );
	$P::store( array( 'core' => 'all', 'plugin' => 'all', 'theme' => 'all', 'max_age' => 24 ) );
	$P::enqueue( 'update-core.php' );
	digi_assert( ! $GLOBALS['scripts'], 'El script se cargó para una cuenta que no administra respaldos.' );
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );

	// 13. AJAX de la pantalla: permiso, nonce, empezar (o «listo»), avanzar con un tick y «sin respaldo».
	$age( 30 * 3600 );
	$GLOBALS['digi']['caps'] = array();
	list( $ok, $data, $code ) = $ajax( 'ajax_start', array( 'nonce' => 'nonce-' . $P::NONCE ) );
	digi_assert( ! $ok && 403 === $code && ! $active(), 'AJAX sin permiso empezó un respaldo.' );
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	try { $ajax( 'ajax_start', array( 'nonce' => 'otro' ) ); throw new RuntimeException( 'FALLA: AJAX aceptó un nonce inválido.' ); } catch ( Digi_Die $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	list( $ok, $data ) = $ajax( 'ajax_start', array( 'nonce' => 'nonce-' . $P::NONCE ) );
	digi_assert( $ok && 'running' === $data['state'] && ! empty( $data['job']['id'] ) && 'ajax' === $active()[0]['spec']['origin']['via'], 'AJAX no empezó el respaldo previo: ' . json_encode( $data ) );
	$id = $data['job']['id'];
	for ( $i = 0; $i < 20 && 'running' === $data['state']; $i++ ) list( $ok, $data ) = $ajax( 'ajax_status', array( 'nonce' => 'nonce-' . $P::NONCE, 'job' => $id ) );
	digi_assert( $ok && 'ready' === $data['state'] && $data['fresh'], 'Los ticks de la pantalla no terminaron el respaldo previo: ' . json_encode( $data ) );
	list( $ok, $data ) = $ajax( 'ajax_start', array( 'nonce' => 'nonce-' . $P::NONCE ) );
	digi_assert( $ok && 'ready' === $data['state'] && ! $active(), 'Con un respaldo reciente, AJAX encoló otro.' );
	list( $ok, $data, $code ) = $ajax( 'ajax_status', array( 'nonce' => 'nonce-' . $P::NONCE, 'job' => '../../wp-config' ) );
	digi_assert( ! $ok && 400 === $code, 'AJAX aceptó un identificador de trabajo inválido.' );
	list( $ok, $data ) = $ajax( 'ajax_skip', array( 'nonce' => 'nonce-' . $P::NONCE ) );
	digi_assert( $ok && '' !== $data['skip_until'], 'AJAX no activó «Actualizar sin respaldo».' );
	update_option( $P::STATE, array() );

	// 14. REST: rutas con la capability de la pantalla, ajustes validados, respaldo (202 o «listo») y «sin respaldo».
	$ns = 'digitalisimo-backups/v1';
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	foreach ( array( '/pre-update' => 'GET,POST', '/pre-update/backup' => 'POST', '/pre-update/skip' => 'POST' ) as $route => $methods ) {
		$handlers = $GLOBALS['digi']['routes'][ $ns . $route ] ?? array();
		digi_assert( $methods === implode( ',', array_column( $handlers, 'methods' ) ), 'La ruta ' . $route . ' no está registrada con ' . $methods );
		foreach ( $handlers as $handler ) digi_assert( array( 'Digitalisimo_Backups_Rest', 'allowed' ) === $handler['permission_callback'], 'La ruta ' . $route . ' no usa la capability de la pantalla.' );
	}
	$GLOBALS['digi']['caps'] = array();
	foreach ( array( array( 'GET', '/pre-update' ), array( 'POST', '/pre-update' ), array( 'POST', '/pre-update/backup' ), array( 'POST', '/pre-update/skip' ) ) as $call ) {
		list( $code ) = digi_rest( $call[0], $ns . $call[1], array(), false );
		digi_assert( 401 === $code, 'Sin sesión no hubo 401 en ' . $call[1] );
	}
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	list( $code, $body ) = digi_rest( 'POST', $ns . '/pre-update', array( 'plugin' => 'auto', 'max_age' => 5 ) );
	digi_assert( 400 === $code && 'all' === $P::settings()['plugin'], 'REST aceptó una antigüedad no válida o cambió algo: ' . json_encode( $body ) );
	list( $code, $body ) = digi_rest( 'POST', $ns . '/pre-update', array( 'plugin' => 'auto', 'max_age' => 48 ) );
	digi_assert( 200 === $code && 'auto' === $body['settings']['plugin'] && 48 === $body['settings']['max_age'] && 'all' === $body['settings']['core'], 'REST no guardó los ajustes: ' . json_encode( $body ) );
	list( $code, $body ) = digi_rest( 'GET', $ns . '/pre-update' );
	digi_assert( 200 === $code && $body['fresh'] && array_key_exists( 'retained', $body ) && array_key_exists( 'postponed', $body ), 'GET /pre-update no da el estado: ' . json_encode( $body ) );
	list( $code, $body ) = digi_rest( 'POST', $ns . '/pre-update/backup' );
	digi_assert( 200 === $code && 'ready' === $body['state'], 'Con un respaldo reciente, POST /pre-update/backup no respondió «listo»: ' . json_encode( $body ) );
	$age( 72 * 3600 );
	list( $code, $body ) = digi_rest( 'POST', $ns . '/pre-update/backup', array( 'network' => true ) );
	digi_assert( 400 === $code && ! $active(), 'En un sitio individual se aceptó el alcance de red.' );
	list( $code, $body ) = digi_rest( 'POST', $ns . '/pre-update/backup' );
	digi_assert( 202 === $code && 'running' === $body['state'] && 'rest' === $active()[0]['spec']['origin']['via'], 'POST /pre-update/backup no encoló el respaldo (202): ' . json_encode( $body ) );
	digi_drive( $body['job']['id'] );
	list( $code, $body ) = digi_rest( 'POST', $ns . '/pre-update/skip', array( 'minutes' => 5 ) );
	digi_assert( 200 === $code && '' !== $body['skip_until'], 'POST /pre-update/skip no respondió: ' . json_encode( $body ) );
	update_option( $P::STATE, array() );

	// 15. WP-CLI: status, set (valida), run (hace el respaldo) y skip.
	Digitalisimo_Backups_Cli::register();
	list( $code, $out ) = digi_wp( 'digitalisimo-backups pre-update', 'set', array(), array( 'core' => 'auto', 'max-age' => '6' ) );
	digi_assert( 0 === $code && 'auto' === $P::settings()['core'] && 6 === $P::settings()['max_age'], 'wp … pre-update set no guardó: ' . implode( ' | ', $out ) );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups pre-update', 'set', array(), array( 'theme' => 'nunca' ) );
	digi_assert( 1 === $code && 'all' === $P::settings()['theme'], 'wp … pre-update set aceptó un modo desconocido.' );
	$age( 30 * 3600 );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups pre-update', 'run', array(), array() );
	digi_assert( 0 === $code && $P::fresh() && (bool) preg_grep( '/^Success: Respaldo completo creado y verificado/', $out ), 'wp … pre-update run no hizo el respaldo: ' . implode( ' | ', $out ) );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups pre-update', 'run', array(), array() );
	digi_assert( 0 === $code && (bool) preg_grep( '/^Success: Hay un respaldo reciente válido/', $out ), 'wp … pre-update run repitió un respaldo reciente: ' . implode( ' | ', $out ) );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups pre-update', 'status', array(), array( 'format' => 'json' ) );
	$json = json_decode( (string) end( $out ), true );
	digi_assert( 0 === $code && 'auto' === $json['settings']['core'] && $json['fresh'], 'wp … pre-update status --format=json no da el estado: ' . implode( ' | ', $out ) );
	list( $code, $out ) = digi_wp( 'digitalisimo-backups pre-update', 'skip', array(), array( 'minutes' => '10' ) );
	digi_assert( 0 === $code && (bool) preg_grep( '/^Success: Las actualizaciones manuales no esperarán/', $out ), 'wp … pre-update skip no respondió: ' . implode( ' | ', $out ) );
	update_option( $P::STATE, array() );

	// 16. Red: ajustes en sitemeta, respaldo de la red entera (uno de la instalación no cuenta) y un subsitio no decide.
	$GLOBALS['digi']['multisite'] = true;
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
	$P::store( array( 'core' => 'all', 'plugin' => 'all', 'theme' => 'all', 'max_age' => 24 ) );
	digi_assert( isset( $GLOBALS['digi']['site_options'][ $P::OPTION ] ), 'En una red los ajustes no van a sitemeta.' );
	digi_assert( null === $P::fresh(), 'En una red contó un respaldo de la instalación individual.' );
	$GLOBALS['digi']['main'] = false;
	digi_assert( true === $auto( 'plugin', array( 'plugin' => 'hello/hello.php' ) ) && ! $active(), 'Un subsitio retuvo o encoló un respaldo de la red.' );
	digi_assert( is_wp_error( $P::service_status() ) && 400 === $P::service_status()->get_error_data()['status'], 'Un subsitio consultó el estado de la red.' );
	ob_start();
	$P::panel( false );
	digi_assert( false !== strpos( ob_get_clean(), 'se configura desde la administración de red' ), 'La tarjeta del subsitio no remite a la red.' );
	$GLOBALS['digi']['main'] = true;
	digi_assert( false === $auto( 'plugin', array( 'plugin' => 'hello/hello.php' ) ), 'En la red una automática siguió sin respaldo de la red.' );
	$network = $active();
	digi_assert( 1 === count( $network ) && 'network' === $network[0]['spec']['scope'] && 'network' === $network[0]['spec']['origin']['context'], 'El respaldo previo de la red no abarca la red: ' . json_encode( $network[0]['spec'] ?? null ) );
	list( $code ) = digi_rest( 'POST', $ns . '/pre-update/skip' );
	digi_assert( 400 === $code, 'En una red «sin respaldo» no pidió el alcance de red.' );
	list( $code ) = digi_rest( 'POST', $ns . '/pre-update/skip', array( 'network' => true ) );
	digi_assert( 200 === $code, 'En una red «sin respaldo» con el alcance de red falló.' );

	echo "Respaldo antes de actualizar: automáticas retenidas y liberadas por la cola, pospuestas con aviso, ajuste por tipo, respaldo reciente, manuales, WP-CLI, REST y red: OK\n";
} finally {
	digi_rmtree( $root );
}
