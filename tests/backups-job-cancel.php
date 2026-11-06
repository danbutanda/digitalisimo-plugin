<?php
/** Cancelación en cada estado, con capability, nonce y contexto de red; nunca deja un parcial ni pierde un respaldo verificado. */
require __DIR__ . '/backups-jobs/env.php';
bk_no_backoff();
$db = $GLOBALS['wpdb'];
function until_phase( $id, $phase ) {
	for ( $i = 0; $i < 20 && $phase !== bk_job( $id )['phase']; $i++ ) Digitalisimo_Backups_Jobs::tick( 0 );
	bk_assert( $phase === bk_job( $id )['phase'], 'El trabajo no llegó a la fase ' . $phase );
}
function rows_for( $file ) { return array_values( array_filter( bk_history(), function( $row ) use ( $file ) { return ( $row['file'] ?? '' ) === $file; } ) ); }
function respond( callable $call ) {
	try {
		$call();
	} catch ( RuntimeException $response ) {
		return $response->getMessage();
	}
	bk_fail( 'El controlador no respondió.' );
}

// Pendiente: se cancela al momento, sin área de trabajo ni historial.
$queued = bk_manual( 'local', 'pendiente' );
$result = Digitalisimo_Backups_Jobs::cancel( $queued['id'] );
$job = bk_job( $queued['id'] );
bk_assert( $result['ok'] && 'cancelled' === $result['code'] && 'cancelled' === $job['status'] && ! Digitalisimo_Backups_Job_Store::cancel_requested( $queued['id'] ), 'Un trabajo pendiente no se canceló al momento.' );
Digitalisimo_Backups_Jobs::tick();
bk_assert( 'cancelled' === bk_job( $queued['id'] )['status'] && ! bk_history(), 'Un trabajo cancelado volvió a correr o registró algo.' );

// En curso, dentro de la instantánea: se pide, el paso se detiene en su siguiente latido y la transacción se revierte.
$running = bk_manual( 'local', 'en-curso' );
$GLOBALS['answer'] = null;
$db->on_select = function() use ( $running, $db ) {
	if ( $db->in_snapshot && null === $GLOBALS['answer'] ) $GLOBALS['answer'] = Digitalisimo_Backups_Jobs::cancel( $running['id'] );
};
$db->queries = array();
Digitalisimo_Backups_Jobs::tick();
$db->on_select = null;
$job = bk_job( $running['id'] );
bk_assert( 'requested' === $GLOBALS['answer']['code'] && 'cancelled' === $job['status'] && null === $job['lease'], 'La cancelación pedida en curso no detuvo el trabajo.' );
$start = array_search( 'START TRANSACTION WITH CONSISTENT SNAPSHOT', $db->queries, true );
bk_assert( false !== $start && in_array( 'ROLLBACK', array_slice( $db->queries, $start ), true ) && ! in_array( 'COMMIT', array_slice( $db->queries, $start ), true ), 'La cancelación no revirtió la instantánea.' );
bk_assert( ! is_dir( bk_storage() . '/jobs/' . $running['id'] . '/staging' ) && ! is_file( bk_storage() . '/' . $running['spec']['file'] ) && ! bk_history(), 'La cancelación dejó SQL, ZIP o historial.' );

// Con el dueño muerto: el detector termina la cancelación.
$orphan = bk_manual( 'local', 'huerfano' );
bk_kill_in( $orphan['id'], 'pack', array( 'package.dddddddd.zip' => 'PK' ) );
$result = Digitalisimo_Backups_Jobs::cancel( $orphan['id'] );
bk_assert( 'requested' === $result['code'] && 'running' === bk_job( $orphan['id'] )['status'] && bk_job( $orphan['id'] )['cancel_requested_at'], 'La cancelación de un trabajo con arrendamiento no quedó pedida.' );
Digitalisimo_Backups_Jobs::recover_if_idle();
bk_assert( 'cancelled' === bk_job( $orphan['id'] )['status'] && ! is_dir( bk_storage() . '/jobs/' . $orphan['id'] . '/staging' ), 'El detector no completó la cancelación del dueño muerto.' );
$view = Digitalisimo_Backups_Jobs::view( bk_job( $orphan['id'] ) );
bk_assert( ! $view['can_cancel'] && 'Cancelado' === $view['status_label'], 'La vista de un trabajo cancelado ofrece cancelar.' );

// Durante la transferencia: el ZIP local verificado se queda registrado y se retira la sesión remota.
update_option( 'digitalisimo_backups_settings', array( 'google_client_id' => 'cliente', 'google_client_secret' => 'secreto', 'google_refresh_token' => 'renovar' ) );
$GLOBALS['bk_remote'] = bk_google_ok();
$between = bk_manual( 'google', 'entre-pasos' );
until_phase( $between['id'], 'transfer' );
$result = Digitalisimo_Backups_Jobs::cancel( $between['id'] );
$row = rows_for( $between['spec']['file'] )[0];
bk_assert( 'cancelled' === $result['code'] && 'local' === $row['destination'] && 'cancelled' === $row['transfer'] && is_file( bk_storage() . '/' . $between['spec']['file'] ), 'Cancelar antes de transferir perdió el respaldo local.' );
bk_assert( false !== strpos( Digitalisimo_Backups_Jobs::view( bk_job( $between['id'] ) )['detail'], 'respaldo local verificado se conserva' ), 'La vista no explica que el respaldo local se conserva.' );
$during = bk_manual( 'google', 'durante' );
until_phase( $during['id'], 'transfer' );
$GLOBALS['bk_remote_log'] = array();
$GLOBALS['bk_remote'] = bk_google_ok( function( $method, $url ) use ( $during ) {
	if ( 'POST' === $method && false !== strpos( $url, 'oauth2' ) ) $GLOBALS['answer'] = Digitalisimo_Backups_Jobs::cancel( $during['id'] );
} );
Digitalisimo_Backups_Jobs::tick();
$row = rows_for( $during['spec']['file'] )[0];
bk_assert( 'requested' === $GLOBALS['answer']['code'] && 'cancelled' === bk_job( $during['id'] )['status'] && 'cancelled' === $row['transfer'] && 'local' === $row['destination'], 'La transferencia cancelada en curso no conservó el respaldo local.' );
bk_assert( in_array( array( 'DELETE', 'https://upload.test/session/1' ), $GLOBALS['bk_remote_log'], true ) && ! in_array( 'PUT', array_column( $GLOBALS['bk_remote_log'], 0 ), true ), 'No se retiró la sesión de carga remota o se siguió subiendo.' );
Digitalisimo_Backups::job_verify_package( bk_storage() . '/' . $during['spec']['file'] );
$GLOBALS['bk_remote'] = bk_google_ok();

// Tras verificar y registrar: no se cancela (el respaldo ya existe; se elimina desde el historial).
$late = bk_manual( 'local', 'tarde' );
until_phase( $late['id'], 'publish' );
$result = Digitalisimo_Backups_Jobs::cancel( $late['id'] );
bk_assert( empty( $result['ok'] ) && 'verified' === $result['code'] && false !== strpos( $result['message'], 'historial' ), 'Se aceptó cancelar un respaldo ya verificado.' );
bk_run_until_done( $late['id'] );
bk_assert( 'success' === bk_job( $late['id'] )['status'] && 1 === count( rows_for( $late['spec']['file'] ) ), 'El rechazo de la cancelación alteró el respaldo verificado.' );

// AJAX: capability, nonce, contexto e identificador.
$target = bk_manual( 'local', 'ajax' );
$_POST = array( 'nonce' => 'ok', 'context' => 'site', 'id' => $target['id'] );
$GLOBALS['bk_can'] = false;
bk_assert( 0 === strpos( respond( array( 'Digitalisimo_Backups_Jobs', 'ajax_cancel' ) ), 'ERROR:403:' ) && 'queued' === bk_job( $target['id'] )['status'], 'Se canceló sin capability.' );
bk_assert( 0 === strpos( respond( array( 'Digitalisimo_Backups_Jobs', 'ajax_status' ) ), 'ERROR:403:' ) && 0 === strpos( respond( array( 'Digitalisimo_Backups_Jobs', 'ajax_tick' ) ), 'ERROR:403:' ), 'El estado o el tick respondieron sin capability.' );
$GLOBALS['bk_can'] = true;
$_POST['nonce'] = 'otro';
bk_assert( 0 === strpos( respond( array( 'Digitalisimo_Backups_Jobs', 'ajax_cancel' ) ), 'NONCE:digitalisimo_backups_jobs' ), 'Se canceló sin nonce válido.' );
$_POST['nonce'] = 'ok';
$_POST['context'] = 'network';
bk_assert( 0 === strpos( respond( array( 'Digitalisimo_Backups_Jobs', 'ajax_cancel' ) ), 'ERROR:400:' ), 'Se aceptó el contexto de red en un WordPress individual.' );
$_POST['context'] = 'site';
$_POST['id'] = '../' . $target['id'];
bk_assert( 0 === strpos( respond( array( 'Digitalisimo_Backups_Jobs', 'ajax_cancel' ) ), 'ERROR:409:' ), 'Se aceptó un identificador con ruta.' );
$_POST['id'] = $target['id'];
$ok = respond( array( 'Digitalisimo_Backups_Jobs', 'ajax_cancel' ) );
$data = json_decode( substr( $ok, 3 ), true );
bk_assert( 0 === strpos( $ok, 'OK:' ) && 'cancelled' === bk_job( $target['id'] )['status'] && is_array( $data['jobs'] ) && false === strpos( $ok, bk_storage() ), 'La cancelación por AJAX no respondió con la vista pública.' );
$status = respond( array( 'Digitalisimo_Backups_Jobs', 'ajax_status' ) );
bk_assert( 0 === strpos( $status, 'OK:' ) && false !== strpos( $status, $target['id'] ), 'El estado por AJAX no lista el trabajo.' );

// admin-post sin JavaScript: capability, nonce y vuelta a la pantalla de origen (sitio o red).
$form = bk_manual( 'local', 'formulario' );
$_POST = array( '_wpnonce' => 'ok', 'job' => $form['id'] );
$GLOBALS['bk_can'] = false;
bk_assert( 0 === strpos( respond( array( 'Digitalisimo_Backups_Jobs', 'admin_cancel' ) ), 'DIE:' ), 'admin-post canceló sin capability.' );
$GLOBALS['bk_can'] = true;
$_POST['_wpnonce'] = 'x';
bk_assert( 0 === strpos( respond( array( 'Digitalisimo_Backups_Jobs', 'admin_cancel' ) ), 'NONCE:' ), 'admin-post canceló sin nonce.' );
$_POST['_wpnonce'] = 'ok';
$redirect = respond( array( 'Digitalisimo_Backups_Jobs', 'admin_cancel' ) );
bk_assert( 0 === strpos( $redirect, 'REDIRECT:https://ejemplo.test/wp-admin/admin.php?page=digitalisimo-backups' ) && false !== strpos( $redirect, 'backup=ok' ), 'admin-post no volvió a la pantalla del sitio.' );
$GLOBALS['bk_multisite'] = true;
$network_job = bk_manual( 'local', 'red' );
$_POST = array( '_wpnonce' => 'ok', 'job' => $network_job['id'], 'digitalisimo_backups_network' => '1' );
$_REQUEST = $_POST;
$redirect = respond( array( 'Digitalisimo_Backups_Jobs', 'admin_cancel' ) );
bk_assert( 0 === strpos( $redirect, 'REDIRECT:https://ejemplo.test/wp-admin/network/admin.php?page=digitalisimo-network-backups' ) && 'cancelled' === bk_job( $network_job['id'] )['status'], 'La cancelación desde la red no conservó su contexto.' );
// La tarjeta «Trabajos» ofrece cancelar con su nonce y conserva el contexto de red en el formulario.
$panel = new ReflectionMethod( 'Digitalisimo_Backups', 'jobs_panel' );
if ( PHP_VERSION_ID < 80100 ) $panel->setAccessible( true );
$shown = bk_manual( 'local', 'tarjeta' );
ob_start();
$panel->invoke( null, true );
$html = ob_get_clean();
bk_assert( false !== strpos( $html, 'name="job" value="' . $shown['id'] . '"' ) && false !== strpos( $html, 'nonce-' . Digitalisimo_Backups_Jobs::ACTION_CANCEL ) && false !== strpos( $html, 'name="digitalisimo_backups_network" value="1"' ) && false !== strpos( $html, 'data-active="1"' ), 'La tarjeta no ofrece cancelar con nonce y contexto de red.' );
bk_assert( false === strpos( $html, bk_storage() ), 'La tarjeta expone rutas del almacén.' );
// Desde un subsitio de la red nada avanza, recupera ni cancela la cola (es de toda la instalación y su historial vive en
// el sitio principal): ni el AJAX de 1.0.58, ni admin-post, ni el tick, y la tarjeta sólo informa.
$GLOBALS['bk_blog_id'] = 3;
$rev = bk_job( $shown['id'] )['rev'];
$_POST = array( 'nonce' => 'ok', 'token' => $shown['id'], 'context' => 'site' );
bk_assert( 0 === strpos( respond( array( 'Digitalisimo_Backups', 'manual_work' ) ), 'ERROR:400:' ) && 0 === strpos( respond( array( 'Digitalisimo_Backups', 'manual_status' ) ), 'ERROR:400:' ), 'El AJAX anterior aceptó trabajar desde un subsitio.' );
bk_assert( 'error' === Digitalisimo_Backups_Jobs::tick()['result'] && ! Digitalisimo_Backups_Jobs::recover_if_idle(), 'El tick o el detector corrieron desde un subsitio.' );
$_POST = array( '_wpnonce' => 'ok', 'job' => $shown['id'] );
$redirect = respond( array( 'Digitalisimo_Backups_Jobs', 'admin_cancel' ) );
bk_assert( false !== strpos( $redirect, 'backup=error' ) && 'queued' === bk_job( $shown['id'] )['status'] && $rev === bk_job( $shown['id'] )['rev'], 'Se tocó un trabajo de la red desde un subsitio.' );
ob_start();
$panel->invoke( null, false );
$html = ob_get_clean();
bk_assert( false !== strpos( $html, 'data-active="0"' ) && false === strpos( $html, 'data-cancel=' ) && false !== strpos( $html, $shown['id'] ), 'La tarjeta de un subsitio ofrece avanzar o cancelar la cola de la red.' );
$GLOBALS['bk_blog_id'] = 1;
$GLOBALS['bk_multisite'] = false;
Digitalisimo_Backups_Jobs::cancel( $shown['id'] );

bk_cleanup();
echo "DIGITALÍSIMO Backups: cancelación pendiente, en curso, con dueño muerto, en la transferencia y tras verificar, con capability, nonce y contexto.\n";
