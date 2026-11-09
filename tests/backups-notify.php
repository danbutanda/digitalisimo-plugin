<?php
/**
 * Lote corto · B7: avisos por correo de respaldos, copias remotas y restauraciones.
 *
 * Con el motor real (cola de trabajos, ZIP, historial y restauración): un fallo definitivo avisa siempre por defecto y
 * una sola vez (ni los reintentos con espera, ni otro tick, ni el watchdog, ni dos procesos que vacían a la vez lo
 * repiten); el éxito avisa sólo si se activa; una copia remota fallida avisa que el ZIP local se conserva; la
 * restauración avisa al terminar o fallar, pero no por un error de quien la pide; el destinatario es el correo
 * configurado o el de administración (del sitio o de la red), y el correo no lleva secretos ni rutas del servidor.
 * Los ajustes son de toda la instalación (sitemeta en una red) y un subsitio no los cambia.
 */
$root = sys_get_temp_dir() . '/digitalisimo-notify-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
// Lo que la pantalla necesita y el entorno de cobertura no trae.
function absint( $value ) { return abs( (int) $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return (string) $value; }
function checked( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? ' checked="checked"' : ''; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $action ) . '">'; }
function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false; }
// Correo simulado: cada aviso queda anotado.
$GLOBALS['mails'] = array();
function wp_mail( $to, $subject, $body ) { $GLOBALS['mails'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body ); return true; }
// Google Drive simulado para la copia remota.
function digi_google( $method, $url, $args ) {
	if ( 'POST' === $method && false !== strpos( $url, 'oauth2' ) ) return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'access_token' => 'token' ) ) );
	return array( 'response' => array( 'code' => 500 ), 'body' => '' );
}
function wp_remote_post( $url, $args = array() ) { return digi_google( 'POST', $url, $args ); }
function wp_remote_get( $url, $args = array() ) { return digi_google( 'GET', $url, $args ); }
function wp_remote_request( $url, $args = array() ) { return digi_google( $args['method'] ?? 'GET', $url, $args ); }
function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : ''; }
function wp_remote_retrieve_header( $response, $header ) { return ''; }

$B = 'Digitalisimo_Backups';
$J = 'Digitalisimo_Backups_Jobs';
$store = $root . '/private';
update_option( 'admin_email', 'admin@example.test' );
$secret = 'Sftp-Secreto-De-Prueba-91f2';
/** Hace avanzar la cola sin esperar los reintentos (sus esperas se anulan) hasta que el trabajo termina. */
$finish = function( $id ) use ( $B ) {
	for ( $i = 0; $i < 40; $i++ ) {
		$view = $B::service_job( $id );
		if ( $view['terminal'] ) return $view;
		Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id ) { $job = Digitalisimo_Backups_Job_Store::read( $id ); if ( $job && ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) { $job['retry_at'] = 0; Digitalisimo_Backups_Job_Store::write( $job ); } } );
		$B::service_tick();
	}
	throw new RuntimeException( 'FALLA: el trabajo ' . $id . ' no terminó.' );
};
$enqueue = function( $destination = 'local' ) use ( $B ) {
	$queued = $B::service_create( array( 'destination' => $destination ) );
	digi_assert( ! is_wp_error( $queued ), 'No se encoló el respaldo: ' . ( is_wp_error( $queued ) ? $queued->get_error_message() : '' ) );
	return $queued['id'];
};
/** Fases reales salvo las que la prueba hace fallar. */
$runner = function( array $failures ) {
	$GLOBALS['attempts'] = array();
	return function( $phase, $job ) use ( $failures ) {
		$GLOBALS['attempts'][ $phase ] = ( $GLOBALS['attempts'][ $phase ] ?? 0 ) + 1;
		if ( isset( $failures[ $phase ] ) ) {
			$failure = $failures[ $phase ];
			if ( is_callable( $failure ) ) $failure = $failure( $GLOBALS['attempts'][ $phase ] );
			if ( $failure instanceof Throwable ) throw $failure;
		}
		return Digitalisimo_Backups_Backup_Job::run_phase( $phase, $job );
	};
};
$sent = function() { return $GLOBALS['mails']; };

try {
	// Credenciales guardadas por la pantalla (para comprobar que ningún correo las lleva) y retención sin efecto.
	$query = digi_admin_post( 'save', $B::ACTION_SAVE, array( 'digitalisimo_backups' => array( 'destination' => 'local', 'retention' => '100', 'sftp_host' => 'sftp.example.test', 'sftp_port' => '22', 'sftp_user' => 'respaldos', 'sftp_password' => $secret, 'sftp_fingerprint' => str_repeat( 'A', 40 ), 'google_client_id' => 'cliente', 'google_client_secret' => 'GOCSPX-secreto-google', 'google_refresh_token' => '1//renovar-google' ) ) );
	digi_assert( 'ok' === ( $query['backup'] ?? '' ), 'No se guardaron las conexiones: ' . json_encode( $query ) );

	// 1. Fallo definitivo con los ajustes por defecto: un aviso al correo de administración, sin rutas ni secretos.
	$message = 'No se pudo leer ' . ABSPATH . 'wp-content/uploads/x.jpg ni ' . $store . '/jobs/a/staging/database.sql ni /home/cliente/privado/datos.sql (contraseña ' . $secret . ', clave ' . AUTH_KEY . ').';
	$J::$phase_runner = $runner( array( 'database' => new Digitalisimo_Backups_Permanent_Error( $message ) ) );
	$id = $enqueue();
	$done = $finish( $id );
	digi_assert( 'failed' === $done['status'], 'El trabajo debía fallar.' );
	digi_assert( 1 === count( $sent() ), 'Un respaldo fallido no envió un aviso (o envió varios): ' . json_encode( $sent() ) );
	$mail = $sent()[0];
	digi_assert( 'admin@example.test' === $mail['to'] && false !== strpos( $mail['subject'], 'El respaldo falló' ), 'El aviso de fallo no va al correo de administración o no lo dice: ' . json_encode( $mail ) );
	digi_assert( false !== strpos( $mail['body'], 'Motivo: No se pudo leer [ruta] ni [ruta] ni [ruta] (contraseña [oculto], clave [oculto]).' ) && false !== strpos( $mail['body'], 'Fase: ' ) && false !== strpos( $mail['body'], 'respaldo manual' ), 'El aviso no da el motivo limpio: ' . $mail['body'] );
	foreach ( array( $root, $store, '/home/cliente', $secret, AUTH_KEY, 'GOCSPX-secreto-google', '1//renovar-google' ) as $forbidden ) digi_assert( false === strpos( $mail['subject'] . $mail['body'], $forbidden ), 'El aviso lleva una ruta del servidor o un secreto: ' . $forbidden );
	// R7: rutas con espacios, file:, un nivel, open_basedir, Windows y el detalle de MySQL (cita datos de las filas).
	$cases = array(
		'No se pudo leer /mnt/Copias de seguridad/cliente/viejo.zip' => array( 'No se pudo leer [ruta]', array( 'Copias', 'viejo.zip' ) ),
		'fopen(file:/home/cliente/privado/clave.txt): failed' => array( 'fopen([ruta]): failed', array( 'clave.txt', 'privado' ) ),
		'No se pudo escribir en /backups' => array( 'No se pudo escribir en [ruta]', array( 'backups' ) ),
		'open_basedir restriction in effect. File(/var/lib/php/sessions/x) is not within the allowed path(s): (/home/cliente:/tmp)' => array( 'open_basedir restriction in effect. File([ruta]) is not within the allowed path(s): ([ruta])', array( 'sessions', '/tmp', 'cliente' ) ),
		'No se pudo abrir C:\\Program Files\\Datos Cliente\\respaldo.zip' => array( 'No se pudo abrir [ruta]', array( 'Datos Cliente', 'respaldo.zip' ) ),
		"La restauración falló: Falló una sentencia SQL: Duplicate entry 'admin@cliente.com' for key 'user_email'" => array( 'La restauración falló: Falló una sentencia SQL (el detalle, con datos del sitio, no va en el correo).', array( 'admin@cliente.com' ) ),
		"El archivo 'mis-datos-privados.zip' no es válido" => array( "El archivo '…' no es válido", array( 'mis-datos' ) ),
		'Revisa https://example.test/wp-admin/admin.php?page=digitalisimo-backups y la fase «Verificación».' => array( 'Revisa https://example.test/wp-admin/admin.php?page=digitalisimo-backups y la fase «Verificación».', array() ),
	);
	foreach ( $cases as $raw => list( $expected, $gone ) ) {
		$clean = Digitalisimo_Backups_Notify::clean( $raw );
		digi_assert( $expected === $clean, 'clean() no dejó el texto esperado: ' . $clean );
		foreach ( $gone as $piece ) digi_assert( false === strpos( $clean, $piece ), 'clean() dejó pasar «' . $piece . '»: ' . $clean );
	}
	// Ni otro tick, ni el watchdog, ni vaciar de nuevo repiten el aviso.
	$B::service_tick();
	$J::watchdog();
	Digitalisimo_Backups_Notify::flush_jobs();
	digi_assert( 1 === count( $sent() ), 'El aviso del mismo fallo se repitió.' );

	// 2. Éxito con los ajustes por defecto: sin aviso. Un fallo transitorio que se reintenta y termina bien: tampoco.
	$J::$phase_runner = $runner( array( 'pack' => function( $attempt ) { return 1 === $attempt ? new Exception( 'Falla transitoria del empaquetado.' ) : null; } ) );
	$GLOBALS['mails'] = array();
	$id = $enqueue();
	$B::service_tick();
	$view = $B::service_job( $id );
	digi_assert( ! $view['terminal'] && 'queued' === $view['status'] && $view['retry_in'] > 0, 'El fallo transitorio no dejó el trabajo esperando su reintento: ' . json_encode( $view ) );
	digi_assert( array() === $sent(), 'Un reintento con espera envió un aviso.' );
	digi_assert( 'success' === $finish( $id )['status'] && 2 === $GLOBALS['attempts']['pack'], 'El reintento no terminó bien.' );
	digi_assert( array() === $sent(), 'Un éxito avisó con los éxitos desactivados.' );

	// 3. Intentos agotados: ningún aviso en los reintentos y uno solo al fallar definitivamente.
	$J::$phase_runner = $runner( array( 'database' => new Exception( 'La base de datos no respondió.' ) ) );
	$id = $enqueue();
	$B::service_tick();
	digi_assert( ! $B::service_job( $id )['terminal'] && array() === $sent(), 'El primer intento fallido terminó el trabajo o avisó.' );
	digi_assert( 'failed' === $finish( $id )['status'] && 1 === count( $sent() ) && false !== strpos( $sent()[0]['body'], 'La base de datos no respondió.' ), 'Los intentos agotados no avisaron una sola vez: ' . json_encode( $sent() ) );

	// 4. La copia remota falla: el aviso dice que el respaldo local verificado se conserva.
	$J::$phase_runner = null;
	$GLOBALS['mails'] = array();
	$id = $enqueue( 'google' );
	$done = $finish( $id );
	digi_assert( 'failed' === $done['status'] && 1 === count( $sent() ), 'La copia remota fallida no avisó una vez: ' . json_encode( array( $done, $sent() ) ) );
	$mail = $sent()[0];
	digi_assert( false !== strpos( $mail['subject'], 'La copia a Google Drive falló' ) && false !== strpos( $mail['body'], 'El respaldo local está verificado y se conserva' ) && false !== strpos( $mail['body'], 'Respaldo: digitalisimo-backup-' ), 'El aviso de la copia remota no dice que el ZIP local se conserva: ' . json_encode( $mail ) );

	// 5. Ajustes de la pestaña «Avisos»: correo propio y éxitos; un correo no válido no cambia nada; correo de prueba.
	$status = $B::service_status()['notifications'];
	digi_assert( array( 'failure' => true, 'success' => false, 'recipient' => 'admin' ) === $status, 'El estado no informa los avisos por defecto: ' . json_encode( $status ) );

	$save = function( array $input, $action = Digitalisimo_Backups_Notify::ACTION_SAVE ) {
		$_POST = array( 'digitalisimo_backups_notify' => $input );
		$_REQUEST = array_merge( $_POST, array( '_wpnonce' => 'nonce-' . $action ) );
		try {
			Digitalisimo_Backups_Notify::save();
		} catch ( Digi_Redirect $redirect ) {
			return $redirect->query;
		}
		throw new RuntimeException( 'FALLA: guardar los avisos no redirigió.' );
	};
	$GLOBALS['mails'] = array();
	$query = $save( array( 'failure' => '1', 'success' => '1', 'email' => 'no-es-un-correo' ) );
	digi_assert( 'error' === $query['backup'] && 'notifications' === $query['tab'] && array( 'failure' => true, 'success' => false, 'email' => '' ) === Digitalisimo_Backups_Notify::settings(), 'Un correo no válido cambió los ajustes: ' . json_encode( $query ) );
	try { $save( array( 'success' => '1' ), 'otra-accion' ); throw new RuntimeException( 'FALLA: se guardaron los avisos con un nonce ajeno.' ); } catch ( Digi_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$GLOBALS['digi']['caps'] = array( 'edit_posts' => true );
	try { $save( array( 'success' => '1' ) ); throw new RuntimeException( 'FALLA: se guardaron los avisos sin capability.' ); } catch ( Digi_Die $die ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	$query = $save( array( 'failure' => '1', 'success' => '1', 'email' => 'avisos@example.test', 'test' => '1' ) );
	digi_assert( 'ok' === $query['backup'] && array( 'failure' => true, 'success' => true, 'email' => 'avisos@example.test' ) === Digitalisimo_Backups_Notify::settings(), 'No se guardaron los avisos: ' . json_encode( $query ) );
	digi_assert( 1 === count( $GLOBALS['mails'] ) && 'avisos@example.test' === $GLOBALS['mails'][0]['to'] && false !== strpos( $GLOBALS['mails'][0]['subject'], 'Correo de prueba' ), 'No se envió el correo de prueba al correo configurado.' );
	digi_assert( 'custom' === Digitalisimo_Backups::service_status()['notifications']['recipient'], 'El estado no dice que hay un correo propio.' );
	digi_assert( false === strpos( json_encode( Digitalisimo_Backups::service_status() ), 'avisos@example.test' ), 'El estado expone el correo de los avisos.' );

	// 6. Éxito con los éxitos activados: un aviso al correo configurado con el archivo, sin la ruta del almacén.
	$GLOBALS['mails'] = array();
	Digitalisimo_Backups_Jobs::$phase_runner = null;
	$created = digi_backup();
	digi_assert( ! is_wp_error( $created ) && 1 === count( $GLOBALS['mails'] ), 'Un respaldo terminado no avisó con los éxitos activados.' );
	$mail = $GLOBALS['mails'][0];
	digi_assert( 'avisos@example.test' === $mail['to'] && false !== strpos( $mail['subject'], 'Respaldo completo creado y verificado' ) && false !== strpos( $mail['body'], 'Respaldo: ' . $created['file'] ) && false === strpos( $mail['body'], $store ) && false === strpos( $mail['body'], $root ), 'El aviso de éxito no es el esperado: ' . json_encode( $mail ) );

	// 7. Dos procesos que vacían a la vez el mismo aviso pendiente (uno murió tras terminar el trabajo): un solo correo.
	$job = Digitalisimo_Backups_Jobs::get( Digitalisimo_Backups_Jobs::views( 1 )[0]['id'] );
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $job ) { $fresh = Digitalisimo_Backups_Job_Store::read( $job['id'] ); $fresh['notice'] = 'pending'; Digitalisimo_Backups_Job_Store::write( $fresh ); } );
	$GLOBALS['mails'] = array();
	digi_assert( 1 === Digitalisimo_Backups_Notify::flush_jobs() && 0 === Digitalisimo_Backups_Notify::flush_jobs() && 1 === count( $GLOBALS['mails'] ), 'Un aviso pendiente se envió más de una vez o ninguna.' );
	// La carrera: dos procesos leyeron la lista con el aviso pendiente; sólo el primero que lo reclama lo envía.
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $job ) { $fresh = Digitalisimo_Backups_Job_Store::read( $job['id'] ); $fresh['notice'] = 'pending'; Digitalisimo_Backups_Job_Store::write( $fresh ); } );
	$first = Digitalisimo_Backups_Notify::claim( $job['id'] );
	digi_assert( is_array( $first ) && 'sent' === $first['notice'] && 'sent' === Digitalisimo_Backups_Jobs::get( $job['id'] )['notice'] && null === Digitalisimo_Backups_Notify::claim( $job['id'] ), 'Dos procesos pudieron reclamar el mismo aviso.' );
	// Un trabajo sin terminar nunca se reclama, aunque lleve la marca.
	$queued = Digitalisimo_Backups::service_create();
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $queued ) { $fresh = Digitalisimo_Backups_Job_Store::read( $queued['id'] ); $fresh['notice'] = 'pending'; Digitalisimo_Backups_Job_Store::write( $fresh ); } );
	digi_assert( null === Digitalisimo_Backups_Notify::claim( $queued['id'] ), 'Se reclamó el aviso de un trabajo sin terminar.' );
	digi_assert( 'success' === digi_drive( $queued['id'] )['status'] && 2 === count( $GLOBALS['mails'] ), 'El trabajo que terminó después no avisó una vez.' );
	// Un trabajo terminado antes de esta versión (sin marca) nunca avisa.
	Digitalisimo_Backups_Job_Store::with_lock( function() use ( $job ) { $fresh = Digitalisimo_Backups_Job_Store::read( $job['id'] ); unset( $fresh['notice'] ); Digitalisimo_Backups_Job_Store::write( $fresh ); } );
	digi_assert( 0 === Digitalisimo_Backups_Notify::flush_jobs(), 'Un trabajo anterior sin marca envió un aviso.' );

	// 8. Restauración: terminada (con la copia previa), rechazada sin cambiar nada; un error de quien la pide no avisa.
	$GLOBALS['mails'] = array();
	$error = Digitalisimo_Backups::service_restore( $created['file'], array( 'confirm' => 'BORRAR' ) );
	digi_assert( is_wp_error( $error ) && array() === $GLOBALS['mails'], 'Una confirmación equivocada envió un aviso.' );
	$result = Digitalisimo_Backups::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR', 'via' => 'rest' ) );
	digi_assert( ! is_wp_error( $result ) && 1 === count( $GLOBALS['mails'] ), 'Una restauración terminada no avisó: ' . json_encode( is_wp_error( $result ) ? $result->get_error_message() : $GLOBALS['mails'] ) );
	// La copia previa es un trabajo del mismo motor, pero nunca avisa como un respaldo.
	Digitalisimo_Backups_Notify::flush_jobs();
	digi_assert( 1 === count( $GLOBALS['mails'] ), 'La copia previa de la restauración envió su propio aviso.' );
	$mail = $GLOBALS['mails'][0];
	digi_assert( false !== strpos( $mail['subject'], 'Restauración terminada' ) && false !== strpos( $mail['body'], 'Archivo: ' . $created['file'] ) && false !== strpos( $mail['body'], 'Copia previa bloqueada: ' . $result['safety_backup'] ) && false !== strpos( $mail['body'], 'Origen: API REST' ), 'El aviso de la restauración no es el esperado: ' . $mail['body'] );
	$GLOBALS['mails'] = array();
	file_put_contents( $store . '/' . $created['file'], 'alterado', FILE_APPEND );
	$error = Digitalisimo_Backups::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( is_wp_error( $error ) && 1 === count( $GLOBALS['mails'] ) && false !== strpos( $GLOBALS['mails'][0]['subject'], 'La restauración se rechazó sin cambiar nada' ) && false !== strpos( $GLOBALS['mails'][0]['body'], 'SHA-256' ), 'Un respaldo alterado no avisó del rechazo: ' . json_encode( $GLOBALS['mails'] ) );

	// 9. Red: ajustes en sitemeta, correo de administración de la red, y un subsitio no los cambia.
	$GLOBALS['digi']['multisite'] = true;
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
	update_site_option( 'admin_email', 'red@example.test' );
	digi_assert( array( 'failure' => true, 'success' => false, 'email' => '' ) === Digitalisimo_Backups_Notify::settings() && 'red@example.test' === Digitalisimo_Backups_Notify::snapshot()['to'], 'En una red los avisos no son los de la red.' );
	$query = $save( array( 'failure' => '1', 'success' => '1' ) );
	digi_assert( 'ok' === $query['backup'] && true === ( $GLOBALS['digi']['site_options'][ Digitalisimo_Backups_Notify::OPTION ]['success'] ?? null ), 'En una red los avisos no se guardaron en sitemeta.' );
	$GLOBALS['digi']['main'] = false;
	$query = $save( array( 'failure' => '', 'success' => '' ) );
	digi_assert( 'error' === $query['backup'] && true === Digitalisimo_Backups_Notify::settings()['success'], 'Un subsitio cambió los avisos de la red.' );
	ob_start();
	Digitalisimo_Backups_Notify::panel( false );
	$html = (string) ob_get_clean();
	digi_assert( false === strpos( $html, '<form' ) && false !== strpos( $html, 'administración de red' ), 'Un subsitio pinta el formulario de los avisos de la red.' );
	$GLOBALS['digi']['main'] = true;
	ob_start();
	Digitalisimo_Backups_Notify::panel( true );
	$html = (string) ob_get_clean();
	digi_assert( false !== strpos( $html, 'name="digitalisimo_backups_network" value="1"' ) && false !== strpos( $html, 'red@example.test' ) && false !== strpos( $html, 'de la red' ), 'La pantalla de red no pinta los avisos de la red.' );

	echo "DIGITALÍSIMO Backups: avisos por correo de fallos (definitivos, una vez, sin rutas ni secretos), éxitos opcionales, copias remotas y restauraciones, en un sitio y en una red.\n";
} finally {
	digi_rmtree( $root );
}
