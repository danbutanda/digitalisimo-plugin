<?php
/**
 * DIGITALÍSIMO Backups · subir e importar un respaldo externo con el mismo panel de avance que el respaldo (pedido del
 * propietario del 2026-11-16: «usa esa misma [barra], pero ahora para cuando se sube o importa un respaldo externo»). Con
 * el motor real, la validación real de una subida y la pantalla real (tests/lib/backups-env.php):
 *   S1  cerrar una subida anota en su sesión, mientras la comprueba, el paso en el que va (abrir, cada archivo contra el
 *       manifiesto, «Origen → este sitio», el registro) y lo que la validación informa dentro («Verificando archivo N de
 *       M»), sin rutas; al terminar no queda nada de eso, el observador del motor vuelve a como estaba y la vista de REST
 *       y WP-CLI (Uploads::view()) no cambia;
 *   S2  el estado por AJAX de la pantalla es la vista de pantalla (el paso, el registro en `placing`, los cortes que admite
 *       y desde cuándo); el de REST, el de siempre;
 *   S3  «Subir un respaldo» ya no pinta su barra: tiene el hueco del panel y sus subidas sin terminar (sólo las de la
 *       cuenta, sin rutas); con una comprobándose, la sección se abre; «Importar», «Restaurar un archivo suelto» y los
 *       respaldos en los destinos tienen su hueco; una importación en curso abre su sección;
 *   S4  el panel al cargar sigue lo que trae o envía un respaldo: la importación en curso (con las fases de
 *       Digitalisimo_Backups_Import_Job), «Traer al almacén» (fases del trabajo `fetch`, «Conexión con el destino») y
 *       «Enviar a un destino»; la que pidió la acción de la que viene la página (`?job=`), también terminada hace poco,
 *       nunca una vieja; una restauración en curso manda; «Trabajos» no repite lo que sigue el panel;
 *   S5  importar, traer y enviar vuelven a la pantalla con el trabajo que encolaron (`job=`), en su pestaña y sección;
 *   S6  el panel trae «Importarlo ahora» (la acción de importar un .tar.gz del almacén, con su nonce y su vuelta), el
 *       enlace a lo que sigue y «Terminar la comprobación», sin rutas ni secretos;
 *   S7  REST y WP-CLI no cambian: la vista de un trabajo (view()) y la de una subida;
 *   S8  el observador del avance sólo recibe lo de fuera de un trabajo, y si falla no interrumpe a quien informa.
 * Debe fallar con 1.0.78 (ad515c71). Uso: php tests/backups-upload-progress.php
 */
require __DIR__ . '/lib/backups-env.php';
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return (int) ( $GLOBALS['up_user'] ?? 1 ); } }
foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86400 ) as $up_name => $up_value ) defined( $up_name ) || define( $up_name, $up_value );
if ( ! function_exists( 'wp_create_nonce' ) ) { function wp_create_nonce( $action = '' ) { return 'nonce-' . $action; } }
if ( ! function_exists( 'is_user_logged_in' ) ) { function is_user_logged_in() { return true; } }
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
if ( ! function_exists( 'wp_max_upload_size' ) ) { function wp_max_upload_size() { return 204800; } }
if ( ! function_exists( 'check_ajax_referer' ) ) { function check_ajax_referer( $action, $field ) { if ( ( $_POST[ $field ] ?? '' ) !== 'nonce-' . $action ) throw new Up_Json( false, '-1', 403 ); return 1; } }
class Up_Json extends Error { public $success; public $payload; public $status; public function __construct( $success, $payload, $status ) { parent::__construct( 'json' ); $this->success = $success; $this->payload = $payload; $this->status = $status; } }
if ( ! function_exists( 'wp_send_json_success' ) ) { function wp_send_json_success( $data = null, $status = null ) { throw new Up_Json( true, $data, $status ); } }
if ( ! function_exists( 'wp_send_json_error' ) ) { function wp_send_json_error( $data = null, $status = null ) { throw new Up_Json( false, $data, $status ); } }

$B = 'Digitalisimo_Backups';
$J = 'Digitalisimo_Backups_Jobs';
$U = 'Digitalisimo_Backups_Uploads';
$failures = 0;
function up_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function up_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
/** Un trabajo en el almacén (como lo deja el motor), con sus campos sobrescritos. */
function up_job( $kind, array $spec, array $over = array() ) {
	$id = Digitalisimo_Backups_Job_Store::new_id();
	$job = bk_call( 'Digitalisimo_Backups_Jobs', 'blank', $id, $spec, 'queued', $kind );
	return Digitalisimo_Backups_Job_Store::create( array_replace( $job, $over ) );
}
function up_clear_jobs() { foreach ( Digitalisimo_Backups_Job_Store::ids() as $id ) bk_remove_tree( Digitalisimo_Backups_Job_Store::directory( $id ) ); }
function up_engine() { return array( 'plugin_version' => '9.9.9', 'job_format' => Digitalisimo_Backups_Job_Store::JOB_FORMAT ); }
function up_origin() { return array( 'via' => 'admin-post', 'user_id' => 1, 'blog_id' => 1, 'context' => 'site' ); }
function up_import_spec( $name = 'backup_sitio_2026.tar.gz' ) { return array( 'type' => 'import', 'format' => 1, 'source' => array( 'origin' => 'store', 'name' => $name, 'size' => 10, 'mtime' => time() ), 'policy' => 'import:store:' . $name, 'destination' => 'local', 'file' => 'digitalisimo-backuply-x.zip', 'scope' => 'installation', 'origin' => up_origin(), 'engine' => up_engine() ); }
function up_fetch_spec( $name = 'digitalisimo-sitio-2026-11-10.zip' ) { return array( 'scope' => 'installation', 'type' => 'fetch', 'kind' => 'fetch', 'destination' => 'local', 'policy' => 'fetch:sftp:' . $name, 'retain' => 1, 'retention' => 'none', 'site_id' => 1, 'file' => $name, 'inline' => false, 'fetch' => array( 'connection' => 'sftp', 'name' => $name, 'registered' => $name, 'sha256' => str_repeat( 'a', 64 ), 'size' => 10 ), 'origin' => up_origin(), 'engine' => up_engine() ); }
function up_send_spec( $name = 'digitalisimo-sitio.zip' ) { return array( 'scope' => 'installation', 'type' => 'send', 'kind' => 'send', 'destination' => 'sftp', 'policy' => 'send:' . $name, 'retain' => 1, 'retention' => 'none', 'site_id' => 1, 'file' => $name, 'inline' => false, 'send' => array( 'sha256' => str_repeat( 'b', 64 ), 'size' => 10 ), 'origin' => up_origin(), 'engine' => up_engine() ); }
/** La pantalla real con $_GET; $network: la administración de red. */
function up_render( $tab, array $get = array() ) {
	$_GET = array( 'page' => 'digitalisimo-backups', 'tab' => $tab ) + $get;
	ob_start();
	try { bk_call( 'Digitalisimo_Backups', 'render', false ); } finally { $html = (string) ob_get_clean(); $_GET = array(); }
	return $html;
}
/** El panel de una página: su data-view y si está dentro de la página. */
function up_panel( $html ) {
	if ( ! preg_match( '#<div id="digitalisimo-backups-manual-progress"[^>]*>#', $html, $m ) ) return null;
	return array( 'tag' => $m[0], 'view' => preg_match( '#data-view="([^"]*)"#', $m[0], $v ) ? json_decode( html_entity_decode( $v[1], ENT_QUOTES, 'UTF-8' ), true ) : null );
}
/** Una sección plegada de «Herramientas» o de «Destinos» (su etiqueta de apertura y su contenido). */
function up_section( $html, $id ) {
	$head = '<details class="digitalisimo-backups-section" id="digitalisimo-backups-section-' . $id . '"';
	$start = strpos( $html, $head );
	if ( false === $start ) return null;
	$tag = substr( $html, $start, strpos( $html, '>', $start ) - $start + 1 );
	// Hasta la sección siguiente (dentro hay otros <details>: «Más», «Opciones»…).
	$next = strpos( $html, '<details class="digitalisimo-backups-section"', $start + strlen( $head ) );
	return array( 'open' => (bool) preg_match( '/\sopen[\s>]/', $tag ), 'body' => false === $next ? (string) substr( $html, $start + strlen( $tag ) ) : (string) substr( $html, $start + strlen( $tag ), $next - $start - strlen( $tag ) ) ); // En PHP 7.4, substr() con largo null da ''.
}
/** Una petición AJAX de la subida: nonce, contexto y la respuesta JSON. */
function up_ajax( $step, array $post ) {
	$_POST = $post + array( 'nonce' => 'nonce-digitalisimo_backups_upload', 'context' => 'site' );
	$_REQUEST = $_POST;
	try { call_user_func( array( 'Digitalisimo_Backups_Uploads', 'ajax_' . $step ) ); } catch ( Up_Json $json ) { return $json; } finally { $_POST = $_REQUEST = array(); }
	throw new RuntimeException( 'ajax_' . $step . ' no respondió JSON.' );
}
$view_keys = array( 'id', 'name', 'size', 'received', 'chunk_size', 'phase', 'validating', 'attempts', 'mine', 'updated_at', 'expires_at' );

// Un respaldo «de otro servidor»: se hace aquí y se quita del almacén y de la lista; sólo quedan sus bytes.
$made = bk_backup( false );
$bytes = file_get_contents( $made );
@unlink( $made );

up_case( 'S1 · cerrar una subida anota su paso de la comprobación, sin rutas, y no deja nada después', function() use ( $B, $U, $bytes, $view_keys ) {
	$states = array();
	$U::$on_check = function( $state ) use ( &$states ) { $states[] = $state; };
	$GLOBALS['bk_filters']['digitalisimo_backups_upload_check_every'] = 0;
	try {
		$session = $B::service_upload_start( array( 'name' => 'digitalisimo-de-otro-servidor.zip', 'size' => strlen( $bytes ) ) );
		up_check( ! is_wp_error( $session ), 'No empezó la subida.' );
		for ( $offset = 0; $offset < strlen( $bytes ); $offset += $session['chunk_size'] ) $B::service_upload_chunk( $session['id'], $offset, array( 'data' => substr( $bytes, $offset, $session['chunk_size'] ) ) );
		$result = $B::service_upload_finish( $session['id'], array( 'via' => 'admin-ajax' ) );
	} finally {
		$U::$on_check = null;
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_upload_check_every'] );
	}
	up_check( ! is_wp_error( $result ) && ! empty( $result['listed'] ), 'La subida no terminó en la lista: ' . json_encode( is_wp_error( $result ) ? $result->get_error_message() : $result ) );
	$steps = array_values( array_unique( array_map( function( $state ) { return (string) $state['check']['step']; }, $states ) ) );
	// «Origen → este sitio» (`plan`) sólo se comprueba si el respaldo se puede restaurar aquí: va entre los dos si está.
	up_check( array( 'open', 'contents', 'register' ) === array_values( array_intersect( $steps, array( 'open', 'contents', 'register' ) ) ) && 'open' === $steps[0] && ( ! in_array( 'plan', $steps, true ) || array_search( 'plan', $steps, true ) === array_search( 'contents', $steps, true ) + 1 ), 'La comprobación no anota sus pasos en orden: ' . json_encode( $steps ) );
	$details = array_filter( array_map( function( $state ) { return (string) $state['check']['detail']; }, $states ) );
	up_check( (bool) preg_grep( '/^Verificando archivo \d+ de \d+$/', $details ), 'Lo que la validación informa dentro del paso no llega a la sesión: ' . json_encode( array_values( $details ) ) );
	foreach ( $states as $state ) up_check( 'validating' === $state['phase'] && false === strpos( json_encode( $state['check'] ), $GLOBALS['bk_root'] ) && $state['check']['started'] <= time(), 'Un paso anotado sale de «validating» o lleva rutas del servidor: ' . json_encode( $state['check'] ) );
	$after = $U::read( $session['id'] );
	up_check( 'done' === $after['phase'] && ! isset( $after['check'] ) && ! isset( $after['placing'] ), 'Terminada, la sesión conserva el paso de la comprobación.' );
	up_check( null === Digitalisimo_Backups_Jobs::$observer, 'El observador del motor no vuelve a como estaba.' );
	$rest = $B::service_upload_status( $session['id'] );
	up_check( $view_keys === array_values( array_diff( array_keys( $rest ), array( 'error', 'result' ) ) ), 'La vista de REST y WP-CLI de una subida cambió: ' . json_encode( array_keys( $rest ) ) );
	// «Agregar al listado» usa la misma validación sin sesión: no anota nada (ni falla por no tener dónde).
	$loose = rtrim( bk_storage(), '/' ) . '/digitalisimo-suelto-de-prueba.zip';
	file_put_contents( $loose, $bytes . '' );
	$states = array();
	$U::$on_check = function( $state ) use ( &$states ) { $states[] = $state; };
	try { $added = $B::service_register( basename( $loose ), array( 'confirm' => 'AGREGAR SIN FIRMA', 'via' => 'admin-post' ) ); } finally { $U::$on_check = null; }
	up_check( array() === $states && null === Digitalisimo_Backups_Jobs::$observer, '«Agregar al listado» anotó pasos de una subida o dejó el observador puesto.' );
	up_check( is_wp_error( $added ) && 'digitalisimo_backups_register_duplicate' === $added->get_error_code(), 'El mismo contenido se agregó dos veces: ' . json_encode( is_wp_error( $added ) ? $added->get_error_code() : $added ) );
	@unlink( $loose );
	// El proceso murió después de mover el archivo al almacén, con el paso de la comprobación aún en la sesión: cerrarla
	// otra vez la termina y no deja el paso.
	$died = $B::service_upload_start( array( 'name' => 'digitalisimo-murio.zip', 'size' => 10 ) );
	$placed = rtrim( bk_storage(), '/' ) . '/digitalisimo-murio-colocado.zip';
	file_put_contents( $placed, 'diez bytes' );
	$state = $U::read( $died['id'] );
	@unlink( $U::data_path( $died['id'] ) );
	$state = array_replace( $state, array( 'phase' => 'validating', 'attempts' => 1, 'check' => array( 'step' => 'register', 'text' => 'Registrándolo…', 'detail' => '', 'started' => time(), 'at' => time() ), 'placing' => array( 'file' => basename( $placed ), 'sha256' => hash_file( 'sha256', $placed ), 'info' => array( 'container' => 'zip' ), 'loose' => true ) ) );
	$U::write( $state );
	$again = $B::service_upload_finish( $died['id'], array( 'via' => 'admin-ajax' ) );
	$after = $U::read( $died['id'] );
	up_check( ! is_wp_error( $again ) && 'done' === $after['phase'] && ! isset( $after['check'] ) && ! isset( $after['placing'] ), 'Cerrar otra vez una subida que murió tras colocarse deja el paso de la comprobación: ' . json_encode( is_wp_error( $again ) ? $again->get_error_message() : $after ) );
	@unlink( $placed );
} );

up_case( 'S2 · el estado por AJAX trae el paso de la comprobación y el registro; el de REST no cambia', function() use ( $B, $U, $view_keys ) {
	$session = $B::service_upload_start( array( 'name' => 'digitalisimo-a-medias.zip', 'size' => 1000 ) );
	$state = $U::read( $session['id'] );
	$state['phase'] = 'validating';
	$state['attempts'] = 2;
	$state['created_at'] = time() - 300;
	$state['check'] = array( 'step' => 'contents', 'text' => 'Comprobando cada archivo contra el manifiesto (CRC y SHA-256) y la base de datos en seco…', 'detail' => 'Verificando archivo 120 de 5 000', 'started' => time() - 40, 'at' => time() );
	$U::write( $state );
	$lock = $U::lock( $session['id'] );
	try {
		$answer = up_ajax( 'status', array( 'id' => $session['id'] ) );
	} finally { $U::unlock( $lock ); }
	$data = $answer->payload;
	up_check( $answer->success && true === $data['validating'] && 2 === $data['attempts'] && 3 === $data['max_attempts'] && $data['age'] >= 300 && 'contents' === $data['check']['step'] && 'Verificando archivo 120 de 5 000' === $data['check']['detail'] && $data['check']['for'] >= 40, 'El estado de la pantalla no trae el paso de la comprobación: ' . json_encode( $data ) );
	$rest = $B::service_upload_status( $session['id'] );
	up_check( $view_keys === array_keys( $rest ), 'El estado de REST trae lo de la pantalla: ' . json_encode( array_keys( $rest ) ) );
	$state['placing'] = array( 'file' => 'digitalisimo-a-medias.zip', 'sha256' => str_repeat( 'c', 64 ), 'info' => array(), 'loose' => false );
	$U::write( $state );
	$data = up_ajax( 'status', array( 'id' => $session['id'] ) )->payload;
	up_check( 'register' === $data['check']['step'] && '' === $data['check']['detail'], 'Con `placing` el estado no dice que se está registrando: ' . json_encode( $data['check'] ) );
	$B::service_upload_cancel( $session['id'] );
} );

up_case( 'S3 · «Subir un respaldo» sin barra propia, con el hueco del panel y sus subidas sin terminar; los huecos de cada sección', function() use ( $B, $U, $J ) {
	up_clear_jobs();
	$mine = $B::service_upload_start( array( 'name' => 'digitalisimo-mia.zip', 'size' => 2000 ) );
	$B::service_upload_chunk( $mine['id'], 0, array( 'data' => str_repeat( 'x', 700 ) ) );
	$GLOBALS['up_user'] = 2;
	$other = $B::service_upload_start( array( 'name' => 'digitalisimo-de-otra-cuenta.zip', 'size' => 2000 ) );
	$GLOBALS['up_user'] = 1;
	$tools = up_render( 'tools' );
	$upload = up_section( $tools, 'upload' );
	up_check( $upload && false === strpos( $upload['body'], '<progress' ) && false !== strpos( $upload['body'], 'data-progress-slot="upload"' ) && false === strpos( $upload['body'], 'data-upload-cancel' ), '«Subir un respaldo» sigue pintando su barra o no tiene el hueco del panel.' );
	up_check( preg_match( '#id="digitalisimo-backups-upload"[^>]*data-sessions="([^"]*)"#', $upload['body'], $m ), 'La tarjeta no lleva sus subidas sin terminar para el panel.' );
	$sessions = json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
	up_check( 1 === count( $sessions ) && $mine['id'] === $sessions[0]['id'] && 700 === $sessions[0]['received'] && 3 === $sessions[0]['max_attempts'] && false === strpos( $m[1], $GLOBALS['bk_root'] ), 'La tarjeta no lleva sólo las subidas de esta cuenta, o lleva rutas: ' . json_encode( $sessions ) );
	up_check( ! $upload['open'] && ! $U::checking_mine(), 'Sin una comprobación en curso, «Subir un respaldo» se abre sola.' );
	$state = $U::read( $mine['id'] );
	$state['phase'] = 'validating';
	$U::write( $state );
	up_check( $U::checking_mine() && up_section( up_render( 'tools' ), 'upload' )['open'], 'Con una subida de esta cuenta comprobándose, «Subir un respaldo» no se abre para seguirla.' );
	$GLOBALS['up_user'] = 2;
	try { up_check( ! $U::checking_mine(), 'La comprobación de otra cuenta abre «Subir un respaldo».' ); } finally { $GLOBALS['up_user'] = 1; }
	foreach ( array( 'import' => 'import', 'restore-file' => 'restore-file' ) as $section => $slot ) up_check( false !== strpos( (string) up_section( $tools, $section )['body'], 'data-progress-slot="' . $slot . '"' ), 'La sección «' . $section . '» no tiene el hueco del panel.' );
	up_check( ! up_section( $tools, 'import' )['open'], 'Sin importación en curso, «Importar» se abre sola.' );
	up_job( 'import', up_import_spec(), array( 'status' => 'running', 'phase' => 'files' ) );
	up_check( up_section( up_render( 'tools' ), 'import' )['open'], 'Con una importación en curso, «Importar» no se abre para seguirla.' );
	$servers = up_render( 'servers' );
	$remote = up_section( $servers, 'remote' );
	up_check( $remote && false !== strpos( $remote['body'], 'data-progress-slot="remote"' ), '«Respaldos guardados en los destinos» no tiene el hueco del panel: ' . ( $remote ? substr( $remote['body'], 0, 300 ) : 'sin la sección; ' . substr( preg_replace( '/\s+/', ' ', strip_tags( $servers ) ), 0, 400 ) ) );
	$B::service_upload_cancel( $mine['id'] );
	$B::service_upload_cancel( $other['id'] );
	up_clear_jobs();
} );

up_case( 'S4 · el panel al cargar sigue la importación, «Traer al almacén» y el envío; la de ?job=; la restauración manda', function() use ( $J ) {
	up_clear_jobs();
	$import = up_job( 'import', up_import_spec(), array( 'status' => 'running', 'phase' => 'files', 'created_at' => time() - 30 ) );
	$view = up_panel( up_render( 'tools' ) )['view'];
	$want = array();
	foreach ( Digitalisimo_Backups_Import_Job::PHASES as $phase ) $want[] = array( $phase, $J::phase_label( $phase, $import ), Digitalisimo_Backups_Import_Job::FLOORS[ $phase ] );
	up_check( $view && $import['id'] === $view['id'] && 'import' === $view['kind'] && $want === $view['track'] && 'ZIP convertido' === $view['track'][3][1] && $view['elapsed'] >= 30, 'El panel no sigue la importación en curso con sus fases: ' . json_encode( $view ) );
	up_clear_jobs();
	$fetch = up_job( 'backup', up_fetch_spec(), array( 'status' => 'transferring', 'phase' => 'receive' ) );
	$home = up_render( 'backups' );
	$view = up_panel( $home )['view'];
	up_check( $view && $fetch['id'] === $view['id'] && 'fetch' === $view['kind'] && array( 'preflight', 'Conexión con el destino', 0 ) === $view['track'][0] && 'Descarga del destino' === $view['track'][1][1] && 'digitalisimo-sitio-2026-11-10.zip' === $view['fetch']['name'], 'El panel no sigue «Traer al almacén» con sus fases: ' . json_encode( $view ) );
	up_check( preg_match( '#<section class="digitalisimo-backups-card digitalisimo-backups-jobs" id="digitalisimo-backups-jobs" data-active="1" data-hide-idle="1" hidden>#', $home ), '«Trabajos» repite lo que ya sigue el panel.' );
	up_clear_jobs();
	$send = up_job( 'backup', up_send_spec(), array( 'status' => 'transferring', 'phase' => 'deliver' ) );
	$view = up_panel( up_render( 'backups' ) )['view'];
	up_check( $view && 'send' === $view['kind'] && array( 'Comprobación del respaldo', 'Copia al destino' ) === array_column( $view['track'], 1 ), 'El panel no sigue el envío con sus fases: ' . json_encode( $view ) );
	// Terminada: sólo la que pidió la acción (`?job=`) y sólo si terminó hace poco.
	$done = Digitalisimo_Backups_Job_Store::write( array_replace( Digitalisimo_Backups_Jobs::get( $send['id'] ), array( 'status' => 'success', 'finished_at' => time() - 60, 'progress' => array( 'percent' => 100, 'message' => 'Copia en SFTP verificada: digitalisimo-sitio.zip.' ) ) ) );
	up_check( null === up_panel( up_render( 'backups' ) )['view'], 'Un envío terminado se enseña sin que lo pida la página.' );
	$view = up_panel( up_render( 'backups', array( 'job' => $done['id'] ) ) )['view'];
	up_check( $view && $done['id'] === $view['id'] && true === $view['terminal'] && 'Copia en SFTP verificada: digitalisimo-sitio.zip.' === $view['detail'], 'La página que vuelve de «Enviar a un destino» no enseña su resultado: ' . json_encode( $view ) );
	Digitalisimo_Backups_Job_Store::write( array_replace( $done, array( 'finished_at' => time() - 3 * 3600 ) ) );
	up_check( null === up_panel( up_render( 'backups', array( 'job' => $done['id'] ) ) )['view'], 'Un envío de hace horas se enseña como recién terminado.' );
	up_check( null === up_panel( up_render( 'backups', array( 'job' => '../../etc' ) ) )['view'], 'Un ?job= que no es un identificador se sigue.' );
	// Una restauración en curso manda (se sigue arriba, en cualquier pestaña).
	$fetch = up_job( 'backup', up_fetch_spec( 'digitalisimo-otro.zip' ), array( 'status' => 'transferring', 'phase' => 'receive' ) );
	$restore = up_job( 'restore', array( 'type' => 'restore', 'scope' => 'installation', 'destination' => 'local', 'policy' => 'restore', 'inline' => false, 'file' => 'digitalisimo-sitio.zip', 'safety' => 'safety-before-restore-x.zip', 'token' => str_repeat( 'ab', 32 ), 'parts' => null, 'origin' => up_origin(), 'engine' => up_engine() ), array( 'status' => 'running', 'phase' => 'database' ) );
	$view = up_panel( up_render( 'backups', array( 'job' => $fetch['id'] ) ) )['view'];
	up_check( $view && $restore['id'] === $view['id'], 'Con una restauración en curso, el panel sigue otra cosa: ' . json_encode( $view['id'] ?? null ) );
	up_clear_jobs();
} );

up_case( 'S5 · importar, traer y enviar vuelven a la pantalla con el trabajo que encolaron', function() use ( $B ) {
	up_clear_jobs();
	$store = Digitalisimo_Backups_Backuply_Import::store_directory( true );
	file_put_contents( $store . '/backup_sitio_2026.tar.gz', gzencode( str_repeat( "\0", 1024 ) ) );
	list( $ok, $message, $url ) = bk_post( array( $B, 'backuply_import_request' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_BACKUPLY_IMPORT, 'backuply_file' => 'backup_sitio_2026.tar.gz', 'backuply_source' => 'store', 'digitalisimo_backups_return' => 'tools:import' ) );
	$active = array_values( array_filter( Digitalisimo_Backups_Jobs::active(), array( 'Digitalisimo_Backups_Import_Job', 'is_import' ) ) );
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
	up_check( $ok && 1 === count( $active ) && $active[0]['id'] === ( $query['job'] ?? '' ) && 'tools' === ( $query['tab'] ?? '' ) && 'import' === ( $query['open'] ?? '' ) && false !== strpos( $message, 'Importación de Backuply en cola' ) && false !== strpos( $message, 'su avance se ve en el panel' ), 'Importar no vuelve con su trabajo a su sección: ' . $url . ' · ' . $message );
	// La vuelta de la pantalla lleva el trabajo sólo si es un identificador válido.
	try { $B::redirect( true, 'x', false, 'backups', '../x' ); } catch ( Bk_Redirect $redirect ) { $bad = $redirect->url; }
	up_check( false === strpos( (string) $bad, 'job=' ), 'Un trabajo que no es un identificador viaja en la vuelta: ' . $bad );
	// «Traer al almacén» (de una fila y de un destino) y «Enviar a un destino» pasan el trabajo que encolaron.
	$code = file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/destinations/trait-backups-destinations.php' ) . file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/trait-backups-remote-service.php' ) . file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/trait-backups-send-service.php' );
	foreach ( array( 'fetch_request', 'remote_fetch_request', 'send_request' ) as $handler ) {
		up_check( preg_match( '#function ' . $handler . '\(\) \{(.*?)\n\t\}#s', $code, $body ) && preg_match( "#self::redirect\( true, [^;]*\\\$result\['job'\]\['id'\]#", $body[1] ), '«' . $handler . '» no vuelve con el trabajo que encoló.' );
	}
	up_clear_jobs();
	@unlink( $store . '/backup_sitio_2026.tar.gz' );
} );

up_case( 'S6 · el panel trae «Importarlo ahora», el enlace a lo que sigue y «Terminar la comprobación», sin rutas ni secretos', function() use ( $B ) {
	up_clear_jobs();
	$html = up_render( 'tools' );
	up_check( preg_match( '#<form id="digitalisimo-backups-progress-import" method="post" action="[^"]*admin-post\.php" hidden>(.*?)</form>#s', $html, $form ), 'El panel no trae «Importarlo ahora».' );
	up_check( false !== strpos( $form[1], 'name="_wpnonce" value="nonce-' . $B::ACTION_BACKUPLY_IMPORT . '"' ) && false !== strpos( $form[1], 'name="action" value="' . $B::ACTION_BACKUPLY_IMPORT . '"' ) && false !== strpos( $form[1], 'name="backuply_source" value="store"' ) && false !== strpos( $form[1], 'name="digitalisimo_backups_return" value="tools:import"' ), '«Importarlo ahora» no es la acción de importar del almacén con su nonce y su vuelta: ' . $form[1] );
	up_check( preg_match( '#<a class="button button-primary" id="digitalisimo-backups-progress-go" href="[^"]*" hidden>#', $html ) && preg_match( '#<button type="button" class="button button-primary" id="digitalisimo-backups-progress-resume" hidden>Terminar la comprobación</button>#', $html ), 'El panel no trae el enlace a lo que sigue o «Terminar la comprobación».' );
	$panel = substr( $html, strpos( $html, '<div id="digitalisimo-backups-manual-progress"' ) );
	up_check( false === strpos( $panel, $GLOBALS['bk_root'] ), 'El panel lleva rutas del servidor.' );
} );

up_case( 'S7 · REST y WP-CLI no cambian: la vista de un trabajo y la de una subida', function() use ( $J, $B, $view_keys ) {
	up_clear_jobs();
	foreach ( array( up_job( 'import', up_import_spec() ), up_job( 'backup', up_fetch_spec() ), up_job( 'backup', up_send_spec() ) ) as $job ) {
		$plain = $J::view( $job );
		$screen = $J::screen_view( $job );
		up_check( ! isset( $plain['track'] ) && ! isset( $plain['elapsed'] ) && $plain === array_intersect_key( $screen, $plain ), 'La vista de REST y WP-CLI de un trabajo «' . $plain['kind'] . '» cambió.' );
		up_check( false === strpos( json_encode( $screen ), $GLOBALS['bk_root'] ), 'La vista de pantalla lleva rutas del servidor.' );
	}
	$service = $B::service_job( up_job( 'backup', up_fetch_spec( 'digitalisimo-tres.zip' ) )['id'] );
	up_check( ! isset( $service['track'] ), 'GET /jobs/<id> trae lo de la pantalla.' );
	$session = $B::service_upload_start( array( 'name' => 'digitalisimo-vista.zip', 'size' => 100 ) );
	$listed = array_map( function( $view ) { return array_values( array_diff( array_keys( $view ), array( 'error', 'result' ) ) ); }, $B::service_upload_list() );
	up_check( $view_keys === array_keys( $session ) && $listed && array( $view_keys ) === array_values( array_unique( $listed, SORT_REGULAR ) ), 'La vista de REST de una subida (o la lista) cambió: ' . json_encode( $listed ) );
	$B::service_upload_cancel( $session['id'] );
	up_clear_jobs();
} );

up_case( 'S8 · el observador del avance sólo recibe lo de fuera de un trabajo y nunca interrumpe', function() {
	$seen = array();
	Digitalisimo_Backups_Jobs::$observer = function( $percent, $message ) use ( &$seen ) { $seen[] = array( $percent, $message ); throw new RuntimeException( 'falla del observador' ); };
	try {
		Digitalisimo_Backups_Jobs::progress( 82, 'Verificando archivo 1 de 2' );
		Digitalisimo_Backups_Jobs::progress( 83, 'Verificando archivo 2 de 2', true );
	} finally { Digitalisimo_Backups_Jobs::$observer = null; }
	up_check( array( array( 82, 'Verificando archivo 1 de 2' ), array( 83, 'Verificando archivo 2 de 2' ) ) === $seen, 'El observador no recibe el avance de fuera de un trabajo: ' . json_encode( $seen ) );
} );

bk_cleanup();
if ( $failures ) { echo "FALLA: {$failures} caso(s)\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: la subida anota su comprobación para el panel, el panel sigue la importación, «Traer al almacén» y el envío al cargar y en su sección, y REST y WP-CLI no cambian.\n";
