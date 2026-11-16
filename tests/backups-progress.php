<?php
/**
 * DIGITALÍSIMO Backups · el panel de avance de la pantalla, el mismo para un respaldo y una restauración (docs/BACKUPS.md,
 * «Pantalla»). Con el motor de trabajos real y la pantalla real (render()):
 *   R1  la vista de pantalla de un respaldo trae sus tramos (sin «Transferencia» en local; con destino remoto, la escala de
 *       la barra) y el tiempo transcurrido; la de REST y WP-CLI (view()) no cambia;
 *   R2  la de una restauración: sus siete fases con los tramos de Digitalisimo_Backups_Restore_Job, qué se restaura (por
 *       partes), su copia previa; la vuelta a la copia previa dice de cuál y por qué, la que falló dice cuál la sigue; una
 *       vuelta que falla queda «a medio cambiar»; el informe sólo para quien gestiona Backups;
 *   R3  el tick por testigo da las vistas de pantalla y, a quien conserva su sesión de gestión, nonces nuevos (los de antes
 *       pueden no valer tras el cambio de tablas); sin sesión, ni nonces ni informe, y terminada, la vista mínima de siempre;
 *   R4  la pantalla pinta el panel (barra con role=progressbar y sus valores, estado vivo, «Volver a la copia previa» con
 *       su nonce) con el trabajo que sigue al cargar en data-view: la restauración en curso, también en las demás pestañas,
 *       o el respaldo manual en curso; «Trabajos» no lo repite (sí los demás); sin rutas ni secretos;
 *   R5  restaurar desde la pantalla vuelve a «Respaldos», donde está el panel, con un aviso corto.
 * Debe fallar con 1.0.77 (77678f25). Uso: php tests/backups-progress.php
 */
require __DIR__ . '/lib/backups-env.php';
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86400 ) as $bp_name => $bp_value ) defined( $bp_name ) || define( $bp_name, $bp_value );
if ( ! function_exists( 'wp_create_nonce' ) ) { function wp_create_nonce( $action = '' ) { return 'nonce-' . $action . '-' . ( $GLOBALS['bp_session'] ?? 'x' ); } }
if ( ! function_exists( 'is_user_logged_in' ) ) { function is_user_logged_in() { return ! empty( $GLOBALS['bp_logged_in'] ); } }
if ( ! function_exists( 'wp_login_url' ) ) { function wp_login_url( $redirect = '' ) { return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( $redirect ); } }
if ( ! function_exists( 'wp_nonce_url' ) ) { function wp_nonce_url( $url, $action = '' ) { return $url . '&_wpnonce=nonce-' . $action; } }
if ( ! function_exists( 'get_sites' ) ) { function get_sites() { return array(); } }
if ( ! function_exists( 'wp_next_scheduled' ) ) { function wp_next_scheduled( $hook, $args = array() ) { return false; } }
if ( ! function_exists( 'wp_timezone_string' ) ) { function wp_timezone_string() { return 'UTC'; } }
if ( ! function_exists( 'rest_url' ) ) { function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ); } }
if ( ! function_exists( 'wp_schedule_event' ) ) { function wp_schedule_event() { return true; } }
if ( ! function_exists( 'wp_schedule_single_event' ) ) { function wp_schedule_single_event() { return true; } }
if ( ! function_exists( 'wp_get_scheduled_event' ) ) { function wp_get_scheduled_event() { return false; } }
if ( ! function_exists( 'wp_unschedule_event' ) ) { function wp_unschedule_event() { return true; } }
if ( ! function_exists( 'get_userdata' ) ) { function get_userdata( $id ) { return (object) array( 'user_email' => 'admin@example.test' ); } }
if ( ! function_exists( 'check_ajax_referer' ) ) { function check_ajax_referer( $action, $field ) { if ( ( $_POST[ $field ] ?? '' ) !== 'nonce-' . $action . '-' . ( $GLOBALS['bp_session'] ?? 'x' ) ) throw new Bp_Json( false, '-1', 403 ); return 1; } }
class Bp_Json extends Error { public $success; public $payload; public $status; public function __construct( $success, $payload, $status ) { parent::__construct( 'json' ); $this->success = $success; $this->payload = $payload; $this->status = $status; } }
if ( ! function_exists( 'wp_send_json_success' ) ) { function wp_send_json_success( $data = null, $status = null ) { throw new Bp_Json( true, $data, $status ); } }
if ( ! function_exists( 'wp_send_json_error' ) ) { function wp_send_json_error( $data = null, $status = null ) { throw new Bp_Json( false, $data, $status ); } }

$B = 'Digitalisimo_Backups';
$J = 'Digitalisimo_Backups_Jobs';
$GLOBALS['bk_caps'] = array( 'manage_options' => true );
$GLOBALS['bp_logged_in'] = true;
$GLOBALS['bp_session'] = 'a';
$failures = 0;
function bp_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function bp_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
/** Un trabajo en el almacén (como lo deja el motor), con sus campos sobrescritos. */
function bp_job( $kind, array $spec, array $over = array() ) {
	$id = Digitalisimo_Backups_Job_Store::new_id();
	$job = bk_call( 'Digitalisimo_Backups_Jobs', 'blank', $id, $spec, 'queued', $kind );
	return Digitalisimo_Backups_Job_Store::create( array_replace( $job, $over ) );
}
function bp_restore_spec( array $more = array() ) {
	return array_replace( array( 'type' => 'restore', 'scope' => 'installation', 'destination' => 'local', 'policy' => 'restore', 'inline' => false, 'file' => 'digitalisimo-sitio-2026-11-15.zip', 'safety' => 'safety-before-restore-20261115.zip', 'token' => str_repeat( 'ab', 32 ), 'parts' => null, 'origin' => array( 'via' => 'admin-post' ) ), $more );
}
function bp_clear_jobs() { foreach ( Digitalisimo_Backups_Job_Store::ids() as $id ) bk_remove_tree( Digitalisimo_Backups_Job_Store::directory( $id ) ); }
/** La pantalla real con $_GET. */
function bp_render( $tab, array $get = array() ) {
	$_GET = array( 'page' => 'digitalisimo-backups', 'tab' => $tab ) + $get;
	ob_start();
	try { bk_call( 'Digitalisimo_Backups', 'render', false ); } finally { $html = (string) ob_get_clean(); $_GET = array(); }
	return $html;
}
/** El panel de avance de una página: sus atributos (data-view decodificado) y su HTML. */
function bp_panel( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="bp-root">' . $html . '</div>' );
	libxml_clear_errors();
	$panel = $doc->getElementById( 'digitalisimo-backups-manual-progress' );
	if ( ! $panel ) return null;
	$bar = $doc->getElementById( 'digitalisimo-backups-manual-bar' );
	$message = $doc->getElementById( 'digitalisimo-backups-manual-message' );
	$rollback = $doc->getElementById( 'digitalisimo-backups-progress-rollback' );
	$fields = array();
	if ( $rollback ) foreach ( $rollback->getElementsByTagName( 'input' ) as $input ) $fields[ $input->getAttribute( 'name' ) ] = $input->getAttribute( 'value' );
	return array(
		'hidden' => $panel->hasAttribute( 'hidden' ),
		'view' => $panel->hasAttribute( 'data-view' ) ? json_decode( $panel->getAttribute( 'data-view' ), true ) : null,
		'bar' => $bar ? array( 'role' => $bar->getAttribute( 'role' ), 'min' => $bar->getAttribute( 'aria-valuemin' ), 'max' => $bar->getAttribute( 'aria-valuemax' ), 'now' => $bar->getAttribute( 'aria-valuenow' ), 'label' => $bar->getAttribute( 'aria-labelledby' ) ) : null,
		'live' => $message ? $message->getAttribute( 'role' ) . '/' . $message->getAttribute( 'aria-live' ) : '',
		'rollback' => $fields,
		'title' => (bool) $doc->getElementById( (string) ( $bar ? $bar->getAttribute( 'aria-labelledby' ) : '' ) ),
		'order' => strpos( $html, '<div id="digitalisimo-backups-manual-progress"' ),
		'card' => $panel->getAttribute( 'class' ),
	);
}

bp_case( 'R1 · un respaldo: tramos de la barra, tiempo y la vista de REST sin cambios', function() use ( $J ) {
	bp_clear_jobs();
	$local = bp_job( 'backup', $J::spec( array( 'policy' => 'manual', 'destination' => 'local' ) ), array( 'created_at' => time() - 75, 'phase' => 'pack', 'status' => 'running' ) );
	$view = $J::screen_view( $local );
	bp_check( array( array( 'preflight', 'Comprobaciones', 0 ), array( 'database', 'Base de datos', 3 ), array( 'pack', 'Archivos y ZIP', 40 ), array( 'verify', 'Verificación', 81 ), array( 'publish', 'Registro', 98 ), array( 'prune', 'Retención', 99 ) ) === ( $view['track'] ?? null ), 'Los tramos de un respaldo local no son los de la barra: ' . json_encode( $view['track'] ?? null, JSON_UNESCAPED_UNICODE ) );
	bp_check( $view['elapsed'] >= 75 && $view['elapsed'] < 90, 'El tiempo transcurrido no sale de cuándo se encoló: ' . json_encode( $view['elapsed'] ?? null ) );
	$plain = $J::view( $local );
	bp_check( ! isset( $plain['track'] ) && ! isset( $plain['elapsed'] ) && array_diff_key( $view, $plain ) && $plain === array_intersect_key( $view, $plain ), 'La vista de REST y WP-CLI cambió o la de pantalla no la incluye entera.' );
	// Con destino remoto lo local ocupa el 90 % de la barra y la transferencia, el resto (Jobs::scale()).
	$remote = $local;
	$remote['spec']['destination'] = 'sftp';
	$track = array_column( $J::screen_view( $remote )['track'], 2, 0 );
	// La transferencia empieza en el 90 % y su tramo lo llena el destino (antes saltaba al 98 % y se quedaba quieta).
	bp_check( array( 'preflight' => 0, 'database' => 2, 'pack' => 36, 'verify' => 72, 'publish' => 88, 'transfer' => 90, 'prune' => 99 ) === $track, 'Con destino remoto los tramos no siguen la escala de la barra: ' . json_encode( $track ) );
} );

bp_case( 'R2 · una restauración: siete fases, partes, copia previa, la vuelta y por qué, a medio cambiar e informe sólo al gestor', function() use ( $J ) {
	bp_clear_jobs();
	$restore = bp_job( 'restore', bp_restore_spec( array( 'parts' => array( 'parts' => array(), 'plugins' => array( 'demo' ), 'themes' => array(), 'sites' => array() ) ) ), array( 'phase' => 'files', 'status' => 'running', 'created_at' => time() - 30 ) );
	$view = $J::screen_view( $restore );
	$want = array();
	foreach ( Digitalisimo_Backups_Restore_Job::PHASES as $phase ) $want[] = array( $phase, $J::phase_label( $phase, $restore ), Digitalisimo_Backups_Restore_Job::FLOORS[ $phase ] );
	bp_check( $want === $view['track'] && 7 === count( $view['track'] ) && 'Base de datos' === $view['track'][2][1], 'Los tramos de la restauración no son los de Restore_Job: ' . json_encode( $view['track'], JSON_UNESCAPED_UNICODE ) );
	bp_check( 'el plugin demo' === $view['parts'] && 'safety-before-restore-20261115.zip' === $view['safety'] && '' === $view['rollback_of'] && '' === $view['followed_by'] && false === $view['midway'], 'Lo que se restaura o la copia previa no se dicen: ' . json_encode( array_intersect_key( $view, array_flip( array( 'parts', 'safety', 'rollback_of', 'followed_by', 'midway' ) ) ), JSON_UNESCAPED_UNICODE ) );
	// Falla a mitad del cambio: la que falló dice cuál la sigue; la vuelta, de cuál es y por qué.
	$failed = Digitalisimo_Backups_Job_Store::write( array_replace( $restore, array( 'status' => 'failed', 'phase' => 'switch', 'finished_at' => time(), 'error' => array( 'message' => 'La restauración falló a mitad del cambio' ) ) ) );
	$back = bp_job( 'restore', bp_restore_spec( array( 'file' => 'safety-before-restore-20261115.zip', 'safety' => '', 'rollback' => array( 'of' => $failed['id'], 'file' => 'digitalisimo-sitio-2026-11-15.zip' ) ) ), array( 'phase' => 'database', 'status' => 'running' ) );
	file_put_contents( Digitalisimo_Backups_Job_Store::directory( $failed['id'] ) . '/rollback.json', json_encode( array( 'job' => $back['id'], 'cause' => 'fallo inyectado al mover los archivos' ) ) );
	$was = $J::screen_view( $failed );
	$now = $J::screen_view( $back );
	bp_check( $back['id'] === $was['followed_by'] && false === $was['midway'], 'La restauración que falló no dice qué vuelta a la copia previa la sigue: ' . json_encode( $was['followed_by'] ) );
	bp_check( $failed['id'] === $now['rollback_of'] && 'digitalisimo-sitio-2026-11-15.zip' === $now['rollback_file'] && 'fallo inyectado al mover los archivos' === $now['rollback_cause'] && 'Vuelta a la copia previa' === $now['origin_label'], 'La vuelta a la copia previa no dice de cuál es ni por qué: ' . json_encode( array_intersect_key( $now, array_flip( array( 'rollback_of', 'rollback_file', 'rollback_cause' ) ) ), JSON_UNESCAPED_UNICODE ) );
	$lost = Digitalisimo_Backups_Job_Store::write( array_replace( $back, array( 'status' => 'failed', 'phase' => 'switch', 'finished_at' => time() ) ) );
	bp_check( true === $J::screen_view( $lost )['midway'], 'Una vuelta a la copia previa que falla no queda «a medio cambiar».' );
	$early = Digitalisimo_Backups_Job_Store::write( array_replace( $restore, array( 'id' => $restore['id'], 'status' => 'failed', 'phase' => 'inspect' ) ) );
	@unlink( Digitalisimo_Backups_Job_Store::directory( $failed['id'] ) . '/rollback.json' );
	bp_check( false === $J::screen_view( $early )['midway'], 'Una restauración que falló antes del cambio cuenta como «a medio cambiar».' );
	// El informe: sólo para quien gestiona Backups.
	$done = Digitalisimo_Backups_Job_Store::write( array_replace( $restore, array( 'status' => 'success', 'phase' => 'cleanup', 'finished_at' => time(), 'result' => array( 'restore' => array( 'notice' => 'Restauración por partes: se restauró el plugin demo.', 'warnings' => array( 'Aviso uno', 'Aviso dos' ), 'removed' => array( 'count' => 3, 'kept' => 1 ) ) ) ) ) );
	$report = $J::screen_view( $done )['result'] ?? null;
	bp_check( is_array( $report ) && 'Restauración por partes: se restauró el plugin demo.' === $report['notice'] && array( 'Aviso uno', 'Aviso dos' ) === $report['warnings'] && 2 === $report['warnings_total'] && 3 === $report['removed'] && 1 === $report['kept'], 'El informe de la restauración no llega al panel: ' . json_encode( $report, JSON_UNESCAPED_UNICODE ) );
	$caps = $GLOBALS['bk_caps'];
	$GLOBALS['bk_caps'] = array();
	try { bp_check( ! isset( $J::screen_view( $done )['result'] ), 'Sin permiso de gestión, la vista lleva el informe.' ); } finally { $GLOBALS['bk_caps'] = $caps; }
} );

bp_case( 'R3 · tick por testigo: vistas de pantalla, nonces nuevos con sesión; sin sesión ni nonces ni informe', function() use ( $J ) {
	bp_clear_jobs();
	$token = str_repeat( 'cd', 32 );
	// Esperando su reintento: el tick no ejecuta ninguna fase (sólo se miran las respuestas).
	$restore = bp_job( 'restore', bp_restore_spec( array( 'token' => $token ) ), array( 'phase' => 'switch', 'status' => 'running', 'retry_at' => time() + 3600 ) );
	$tick = function( $session ) use ( $J, $token ) {
		$caps = $GLOBALS['bk_caps'];
		$logged = $GLOBALS['bp_logged_in'];
		$GLOBALS['bk_caps'] = $session ? $caps : array();
		$GLOBALS['bp_logged_in'] = $session;
		$_COOKIE[ Digitalisimo_Backups_Restore_Maintenance::COOKIE ] = $token;
		try { $J::ajax_restore_tick(); return null; } catch ( Bp_Json $json ) { return $json; } finally { unset( $_COOKIE[ Digitalisimo_Backups_Restore_Maintenance::COOKIE ] ); $GLOBALS['bk_caps'] = $caps; $GLOBALS['bp_logged_in'] = $logged; }
	};
	// Tras el cambio de tablas la sesión sigue («Conservar mi acceso») pero con otro token: los nonces de la página no valen.
	$GLOBALS['bp_session'] = 'b';
	$answer = $tick( true );
	$view = $answer && $answer->success ? ( $answer->payload['jobs'][0] ?? array() ) : array();
	bp_check( $answer && $answer->success && $restore['id'] === ( $view['id'] ?? '' ) && 7 === count( (array) ( $view['track'] ?? array() ) ) && isset( $view['elapsed'] ), 'El tick por testigo no da la vista de pantalla: ' . json_encode( $answer ? $answer->payload : null ) );
	bp_check( array( 'jobs' => 'nonce-digitalisimo_backups_jobs-b', 'restore' => 'nonce-' . Digitalisimo_Backups::ACTION_RESTORE . '-b' ) === ( $answer->payload['nonces'] ?? null ), 'Con la sesión de gestión el tick no trae nonces nuevos: ' . json_encode( $answer->payload['nonces'] ?? null ) );
	bp_check( true === ( $answer->payload['manager'] ?? null ), 'El tick por testigo no dice que la pestaña conserva su sesión de gestión.' );
	$answer = $tick( false );
	bp_check( $answer && $answer->success && array() === ( $answer->payload['nonces'] ?? null ) && false === ( $answer->payload['manager'] ?? null ), 'Sin sesión el tick por testigo dio nonces o no dice que la sesión se perdió: ' . json_encode( $answer ? $answer->payload : null ) );
	// Terminada: con sesión, la vista de pantalla con su informe; sin ella, la mínima de siempre (C4).
	Digitalisimo_Backups_Job_Store::write( array_replace( Digitalisimo_Backups_Jobs::get( $restore['id'] ), array( 'status' => 'success', 'phase' => 'cleanup', 'finished_at' => time(), 'retry_at' => 0, 'result' => array( 'restore' => array( 'notice' => 'Cómo entrar ahora: https://example.test/wp-login.php' ) ), 'progress' => array( 'percent' => 100, 'message' => 'Restauración terminada desde digitalisimo-sitio-2026-11-15.zip.' ) ) ) );
	$answer = $tick( true );
	$view = $answer->payload['jobs'][0] ?? array();
	bp_check( 'finished' === ( $answer->payload['result'] ?? '' ) && isset( $view['track'], $view['result'] ) && 'Cómo entrar ahora: https://example.test/wp-login.php' === $view['result']['notice'], 'Terminada, con sesión, el tick no da su informe: ' . json_encode( $view, JSON_UNESCAPED_UNICODE ) );
	$answer = $tick( false );
	$view = $answer->payload['jobs'][0] ?? array();
	bp_check( 'finished' === ( $answer->payload['result'] ?? '' ) && ! isset( $view['track'] ) && ! isset( $view['result'] ) && ! isset( $view['file'] ) && false !== strpos( (string) $view['detail'], 'Entra al escritorio' ) && array() === ( $answer->payload['nonces'] ?? null ), 'Terminada, sin sesión, el tick no da la vista mínima de siempre: ' . json_encode( $answer->payload, JSON_UNESCAPED_UNICODE ) );
	$GLOBALS['bp_session'] = 'a';
	// El tick con nonce y el estado también dan la vista de pantalla.
	$_POST = array( 'nonce' => 'nonce-digitalisimo_backups_jobs-a', 'context' => 'site' );
	try { $J::ajax_status(); $status = null; } catch ( Bp_Json $json ) { $status = $json; } finally { $_POST = array(); }
	bp_check( $status && $status->success && isset( $status->payload['jobs'][0]['track'] ), 'El estado de la cola no da la vista de pantalla.' );
} );

bp_case( 'R4 · la pantalla: el panel accesible con el trabajo que sigue, en cualquier pestaña; «Trabajos» no lo repite', function() use ( $J ) {
	bp_clear_jobs();
	$home = bp_panel( bp_render( 'backups' ) );
	bp_check( $home && $home['hidden'] && null === $home['view'], 'Sin trabajos, «Respaldos» no tiene el panel oculto y vacío: ' . json_encode( $home ) );
	bp_check( array( 'role' => 'progressbar', 'min' => '0', 'max' => '100', 'now' => '0', 'label' => 'digitalisimo-backups-progress-title' ) === $home['bar'] && $home['title'] && 'status/polite' === $home['live'], 'La barra no es un progressbar con su nombre o el estado no es una región viva: ' . json_encode( $home ) );
	bp_check( Digitalisimo_Backups::ACTION_RESTORE === ( $home['rollback']['action'] ?? '' ) && '1' === ( $home['rollback']['rollback_retry'] ?? '' ) && '' !== ( $home['rollback']['_wpnonce'] ?? '' ), '«Volver a la copia previa» del panel no lleva la acción, rollback_retry y su nonce: ' . json_encode( $home['rollback'] ) );
	bp_check( null === bp_panel( bp_render( 'settings' ) )['view'], 'Sin restauración, «Ajustes» lleva un trabajo en el panel.' );
	// Una restauración en curso: el panel la sigue al cargar en «Respaldos» y en las demás pestañas; «Trabajos» no la repite.
	$restore = bp_job( 'restore', bp_restore_spec(), array( 'phase' => 'database', 'status' => 'running', 'progress' => array( 'percent' => 45, 'message' => 'Importando la base de datos a tablas temporales (3 MB de 9 MB)…' ) ) );
	$page = bp_render( 'backups' );
	$panel = bp_panel( $page );
	bp_check( $restore['id'] === ( $panel['view']['id'] ?? '' ) && 45 === $panel['view']['percent'] && 'database' === $panel['view']['phase'] && 7 === count( $panel['view']['track'] ), 'Con una restauración en curso, el panel no la sigue al cargar: ' . json_encode( $panel['view'] ) );
	bp_check( (bool) preg_match( '#<section class="digitalisimo-backups-card digitalisimo-backups-jobs" id="digitalisimo-backups-jobs" data-active="1" data-hide-idle="1" hidden>#', $page ), '«Trabajos» repite la restauración que ya sigue el panel (o no hace avanzar la cola).' );
	foreach ( array( 'settings', 'servers', 'tools' ) as $tab ) {
		$html = bp_render( $tab );
		$other = bp_panel( $html );
		bp_check( $other && $restore['id'] === ( $other['view']['id'] ?? '' ) && false !== strpos( $other['card'], 'digitalisimo-backups-card' ) && strpos( $html, '</nav>' ) + 6 === $other['order'], '«' . $tab . '» no enseña arriba, bajo las pestañas, el panel de la restauración en curso.' );
	}
	foreach ( array( bk_storage(), $restore['spec']['token'], ABSPATH ) as $secret ) bp_check( false === strpos( json_encode( $panel['view'] ), $secret ) && false === strpos( json_encode( $panel['view'] ), str_replace( '/', '\\/', $secret ) ), 'El panel lleva una ruta del servidor o el testigo.' );
	// Otro trabajo en curso a la vez (una automatización que espera): «Trabajos» sí se ve.
	$auto = bp_job( 'backup', $J::spec( array( 'policy' => 'p-semanal', 'destination' => 'local' ) ) );
	bp_check( (bool) preg_match( '#id="digitalisimo-backups-jobs" data-active="1" data-hide-idle="1">#', bp_render( 'backups' ) ), 'Con otro trabajo en curso, «Trabajos» queda oculta.' );
	bk_remove_tree( Digitalisimo_Backups_Job_Store::directory( $auto['id'] ) );
	// Terminada hace poco, el resultado; hace más de 15 minutos, nada (lo dice la franja si falló).
	Digitalisimo_Backups_Job_Store::write( array_replace( Digitalisimo_Backups_Jobs::get( $restore['id'] ), array( 'status' => 'success', 'phase' => 'cleanup', 'finished_at' => time() - 60 ) ) );
	bp_check( $restore['id'] === ( bp_panel( bp_render( 'backups' ) )['view']['id'] ?? '' ), 'Una restauración que acaba de terminar no se enseña en el panel al volver.' );
	Digitalisimo_Backups_Job_Store::write( array_replace( Digitalisimo_Backups_Jobs::get( $restore['id'] ), array( 'finished_at' => time() - 1000 ) ) );
	bp_check( null === bp_panel( bp_render( 'backups' ) )['view'], 'Una restauración de hace más de 15 minutos sigue en el panel.' );
	// El respaldo manual en curso: al recargar «Respaldos», el panel vuelve a él (no en las demás pestañas).
	$manual = bp_job( 'backup', $J::spec( array( 'policy' => 'manual', 'destination' => 'local' ) ), array( 'phase' => 'pack', 'status' => 'running' ) );
	bp_check( $manual['id'] === ( bp_panel( bp_render( 'backups' ) )['view']['id'] ?? '' ) && null === bp_panel( bp_render( 'tools' ) )['view'], 'Al recargar «Respaldos», el panel no vuelve al respaldo manual en curso (o lo enseña en otra pestaña).' );
	// La restauración de una migración la sigue «Migrar», no este panel.
	bp_clear_jobs();
	bp_job( 'restore', bp_restore_spec( array( 'aside' => array( 'kind' => 'migration', 'migration' => 'm1' ) ) ), array( 'phase' => 'files', 'status' => 'running' ) );
	bp_check( null === bp_panel( bp_render( 'backups' ) )['view'], 'El panel sigue la restauración de una migración.' );
	bp_clear_jobs();
} );

bp_case( 'R5 · restaurar desde la pantalla vuelve a «Respaldos» con un aviso corto', function() use ( $B ) {
	bp_clear_jobs();
	$path = bk_backup( true );
	$filters = $GLOBALS['bk_filters'];
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] = 0;
	try {
		// Desde «Herramientas → Restaurar un archivo suelto»: su campo de vuelta pide esa sección.
		list( $ok, $message, $url ) = bk_post( array( $B, 'restore_request' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_RESTORE, 'action' => $B::ACTION_RESTORE, 'backup_file' => basename( $path ), 'confirmation' => 'RESTAURAR', 'digitalisimo_backups_return' => 'tools:restore-file' ) );
	} finally {
		$GLOBALS['bk_filters'] = $filters;
	}
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
	bp_check( $ok && 'backups' === ( $query['tab'] ?? '' ) && ! isset( $query['open'] ) && false === strpos( $url, '#digitalisimo-backups-section-' ), 'Restaurar no vuelve a «Respaldos», donde está el panel: ' . $url );
	$safety = (string) ( Digitalisimo_Backups_Jobs::restore_active()['spec']['safety'] ?? '' );
	bp_check( 0 === strpos( $message, 'Restauración en curso: su avance se ve abajo.' ) && '' !== $safety && false !== strpos( $message, $safety ) && mb_strlen( str_replace( $safety, '', $message ) ) <= 120 && false === strpos( $message, '«Trabajos»' ), 'El aviso al empezar no es corto, no nombra la copia previa o manda a «Trabajos»: ' . $message );
	$active = Digitalisimo_Backups_Jobs::restore_active();
	bp_check( $active && 'inspect' === $active['phase'], 'No quedó la restauración en curso.' );
	Digitalisimo_Backups_Jobs::cancel( $active['id'] );
	Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	@unlink( $path );
	bp_clear_jobs();
} );

bk_cleanup();
if ( $failures ) { echo 'FALLA: ' . $failures . " caso(s) del panel de avance (PHP)\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: el panel de avance recibe sus tramos, el tiempo, la vuelta a la copia previa y el informe; el tick por testigo da nonces nuevos sólo a quien conserva su sesión; la pantalla lo pinta accesible con el trabajo en curso en cualquier pestaña y restaurar vuelve a él.\n";
