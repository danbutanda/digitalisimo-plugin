<?php
/**
 * La pantalla Backups, REST y WP-CLI comparten los casos de uso de Digitalisimo_Backups_Service sobre el motor de
 * «Trabajos» y «Seguridad»: crear y «Ejecutar ahora» encolan en Digitalisimo_Backups_Jobs (sin duplicar), la cola se
 * consulta, avanza y se cancela por el mismo caso de uso, y restaurar y verificar abren el paquete con su nivel de
 * confianza. En una red, el alcance se exige desde el sitio principal.
 */
$root = sys_get_temp_dir() . '/digitalisimo-service-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
// La rotación ordena por fecha de modificación y estos respaldos nacen en el mismo segundo: sin rotar, cada prueba decide qué existe.
update_option( Digitalisimo_Backups::OPTION, array( 'retention' => '100' ) );
$store = $root . '/private';
$db = $GLOBALS['wpdb'];
$S = 'Digitalisimo_Backups';

try {
	// Estado real antes de crear nada.
	$status = $S::service_status();
	digi_assert( true === $status['ready'] && array() === $status['problems'], 'Una instalación completa debe estar lista: ' . json_encode( $status['problems'] ) );
	digi_assert( 'installation' === $status['scope'] && false === $status['busy'] && 0 === $status['jobs'] && 'abspath' === $status['wp_config'] && 0 === $status['backups'] && isset( $status['destinations']['local'] ), 'El estado no describe la instalación individual.' );
	digi_assert( 1 === $status['automations'] && false === $status['encrypt'] && false === $status['require_signed'] && 'none' === $status['key_source'], 'El estado debe contar la automatización por defecto y la seguridad sin clave.' );

	// Sin la capability de la pantalla nada se crea, se consulta ni se cancela.
	$GLOBALS['digi']['caps'] = array( 'edit_posts' => true );
	$job_id = '0b6f6b8e-3c1d-4f7a-9a1e-2f4d5c6b7a8e';
	foreach ( array( $S::service_status(), $S::service_create(), $S::service_backups(), $S::service_restore( 'x.zip', array( 'confirm' => 'RESTAURAR' ) ), $S::service_verify( 'x.zip' ), $S::service_automations(), $S::service_run_automation( 'monthly-full' ), $S::service_jobs(), $S::service_job( $job_id ), $S::service_cancel( $job_id ), $S::service_tick() ) as $denied ) {
		digi_assert( is_wp_error( $denied ) && 403 === $denied->get_error_data()['status'], 'Un usuario sin capability usó el motor.' );
	}
	digi_assert( array() === digi_stored( $store ) && array() === Digitalisimo_Backups_Jobs::all(), 'Se creó un ZIP o un trabajo sin autorización.' );
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );

	// Alcance y destino.
	$error = $S::service_create( array( 'network' => true ) );
	digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'no es una red' ), 'Una instalación individual aceptó el alcance de red.' );
	$error = $S::service_create( array( 'destination' => 'sftp' ) );
	digi_assert( is_wp_error( $error ) && 'digitalisimo_backups_destination' === $error->get_error_code(), 'Se aceptó un destino sin conexión.' );

	// Crear encola un trabajo de la misma cola que la pantalla; no hay ZIP hasta que la cola avanza.
	$queued = $S::service_create( array( 'via' => 'rest' ) );
	digi_assert( ! is_wp_error( $queued ), 'No se encoló el respaldo: ' . ( is_wp_error( $queued ) ? $queued->get_error_message() : '' ) );
	digi_assert( Digitalisimo_Backups_Job_Store::valid_id( $queued['id'] ) && 'queued' === $queued['status'] && 'local' === $queued['destination'] && 'manual' === $queued['policy'] && false === $queued['encrypt'] && $queued['can_cancel'] && '' === $queued['file'], 'La vista del trabajo encolado no es la esperada: ' . json_encode( $queued ) );
	$job = Digitalisimo_Backups_Jobs::get( $queued['id'] );
	digi_assert( 'rest' === $job['spec']['origin']['via'] && 'site' === $job['spec']['origin']['context'] && 'global' === $job['spec']['retention'], 'La especificación del trabajo no anota su origen.' );
	digi_assert( array() === digi_stored( $store ) && array() === (array) get_option( $S::HISTORY, array() ), 'Encolar creó un ZIP o una fila del historial.' );
	// Sin duplicar: un segundo respaldo manual mientras el primero sigue pendiente responde 409 con su trabajo.
	$duplicate = $S::service_create();
	digi_assert( is_wp_error( $duplicate ) && 409 === $duplicate->get_error_data()['status'] && $queued['id'] === $duplicate->get_error_data()['job'] && 'digitalisimo_backups_duplicate' === $duplicate->get_error_code(), 'Se encoló un respaldo manual duplicado.' );
	digi_assert( 1 === count( Digitalisimo_Backups_Jobs::all() ), 'El duplicado dejó otro trabajo.' );
	// La cola: lista, un trabajo, identificadores inválidos o inexistentes.
	$jobs = $S::service_jobs();
	digi_assert( 1 === count( $jobs ) && $queued['id'] === $jobs[0]['id'] && 'local' === $jobs[0]['destination'], 'La lista de trabajos no muestra el encolado.' );
	digi_assert( 1 === $S::service_status()['jobs'], 'El estado no cuenta el trabajo en curso.' );
	foreach ( array( '../../wp-config', 'no-es-un-uuid', '' ) as $bad ) {
		$error = $S::service_job( $bad );
		digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'], 'Se aceptó un identificador de trabajo inválido: ' . $bad );
	}
	$error = $S::service_job( $job_id );
	digi_assert( is_wp_error( $error ) && 404 === $error->get_error_data()['status'], 'Se encontró un trabajo inexistente.' );
	// Con un trabajo pendiente no se restaura nada (ni siquiera se abre el paquete).
	$done = digi_drive( $queued['id'] );
	digi_assert( 'success' === $done['status'] && preg_match( '/^digitalisimo-backup-example-test-\d{8}-\d{6}-[0-9a-f]{8}\.zip$/', $done['file'] ) && 100 === $done['percent'] && ! $done['can_cancel'], 'El trabajo no terminó con su ZIP: ' . json_encode( $done ) );
	$created = $S::service_backups()[0];
	digi_assert( $done['file'] === $created['file'] && $created['available'] && $created['restorable'] && 'manual' === $created['policy'] && 'installation' === $created['scope'] && 'zip' === $created['container'] && false === $created['encrypted'] && false === $created['signed'] && false === $created['needs_secret'], 'El resumen del respaldo no es el esperado: ' . json_encode( $created ) );
	digi_assert( hash_file( 'sha256', $store . '/' . $created['file'] ) === $created['sha256'], 'El SHA-256 del resumen no es el del ZIP.' );

	// El botón «Crear respaldo completo» (admin-post) usa el mismo caso de uso y conserva su redirección.
	$query = digi_admin_post( 'run', $S::ACTION_RUN, array( 'digitalisimo_backup_scope' => 'installation', 'digitalisimo_backup_destination' => 'local' ) );
	digi_assert( 'ok' === $query['backup'] && 'Respaldo completo en cola hacia Hosting / local. Su avance aparece en «Trabajos»; si cierras esta página, WP-Cron lo continuará.' === $query['message'], 'La pantalla no informó el respaldo encolado: ' . json_encode( $query ) );
	$active = Digitalisimo_Backups_Jobs::active();
	digi_assert( 1 === count( $active ) && 'admin-post' === $active[0]['spec']['origin']['via'], 'La pantalla no encoló por el caso de uso.' );
	$query = digi_admin_post( 'run', $S::ACTION_RUN, array( 'digitalisimo_backup_destination' => 'local' ) );
	digi_assert( 'error' === $query['backup'] && 'Ya hay un respaldo manual pendiente o en curso; su avance está en «Trabajos».' === $query['message'], 'La pantalla duplicó el respaldo manual.' );
	digi_drive( $active[0]['id'] );
	$history = get_option( $S::HISTORY );
	digi_assert( 2 === count( $history ) && 'manual' === $history[0]['policy'] && $history[0]['sha256'] === hash_file( 'sha256', $store . '/' . $history[0]['file'] ), 'La pantalla no registró el respaldo como el caso de uso.' );
	$query = digi_admin_post( 'run', $S::ACTION_RUN, array( 'digitalisimo_backup_destination' => 'google' ) );
	digi_assert( 'error' === $query['backup'] && 'El destino seleccionado no está conectado o no es válido.' === $query['message'], 'La pantalla perdió el aviso de destino no válido.' );
	try {
		$GLOBALS['digi']['caps'] = array();
		digi_admin_post( 'run', $S::ACTION_RUN, array() );
		throw new RuntimeException( 'FALLA: la pantalla creó un respaldo sin capability.' );
	} catch ( Digi_Die $denied ) {
		$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	}

	// Otra operación con el almacén: encolar sigue respondiendo (el trabajo espera), la cola responde «busy»,
	// restaurar responde 409 y el estado lo muestra. Un trabajo pendiente también impide restaurar; se cancela.
	$lock = digi_hold_lock();
	try {
		$waiting = $S::service_create();
		digi_assert( ! is_wp_error( $waiting ) && 'queued' === $waiting['status'], 'Encolar falló con otra operación en curso.' );
		digi_assert( 'busy' === $S::service_tick()['result'], 'La cola avanzó con el almacén tomado por otra operación.' );
		$busy = $S::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR' ) );
		digi_assert( is_wp_error( $busy ) && 409 === $busy->get_error_data()['status'] && false !== strpos( $busy->get_error_message(), 'Ya hay un respaldo' ), 'Una restauración se solapó con otra operación.' );
		$status = $S::service_status();
		digi_assert( true === $status['busy'] && 1 === $status['jobs'], 'El estado no mostró la operación y el trabajo en curso.' );
	} finally {
		digi_release_lock( $lock );
	}
	$pending = $S::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( is_wp_error( $pending ) && 409 === $pending->get_error_data()['status'] && false !== strpos( $pending->get_error_message(), 'pendiente o en curso' ), 'Se restauró con un trabajo pendiente.' );
	$query = digi_admin_post( 'restore_request', $S::ACTION_RESTORE, array( 'backup_file' => $created['file'], 'confirmation' => 'RESTAURAR' ) );
	digi_assert( 'error' === $query['backup'] && false !== strpos( $query['message'], 'pendiente o en curso' ), 'La pantalla restauró con un trabajo pendiente.' );
	$cancelled = $S::service_cancel( $waiting['id'] );
	digi_assert( ! is_wp_error( $cancelled ) && 'cancelled' === $cancelled['result'] && 'cancelled' === $cancelled['job']['status'] && $cancelled['job']['terminal'], 'No se canceló el trabajo en cola: ' . json_encode( $cancelled ) );
	foreach ( array( array( $waiting['id'], 409, 'job_terminal' ), array( $job_id, 404, 'job_missing' ), array( '../jobs', 400, 'job_invalid' ) ) as $case ) {
		$error = $S::service_cancel( $case[0] );
		digi_assert( is_wp_error( $error ) && $case[1] === $error->get_error_data()['status'] && 'digitalisimo_backups_' . $case[2] === $error->get_error_code(), 'La cancelación de ' . $case[0] . ' no respondió ' . $case[1] . '.' );
	}
	digi_assert( false === $S::service_status()['busy'] && 0 === $S::service_status()['jobs'], 'El estado siguió ocupado tras liberar el bloqueo y cancelar.' );
	digi_assert( 2 === count( digi_stored( $store ) ), 'Una operación rechazada o cancelada dejó un ZIP.' );

	// Historial público: sin rutas del servidor ni identificadores remotos.
	$backups = $S::service_backups();
	digi_assert( 2 === count( $backups ) && $history[0]['file'] === $backups[0]['file'] && $created['file'] === $backups[1]['file'], 'El historial no va del más reciente al más antiguo.' );
	$public = json_encode( $backups ) . json_encode( $S::service_status() ) . json_encode( $S::service_jobs( 50 ) );
	digi_assert( false === strpos( $public, $root ) && false === strpos( $public, 'jobs/' ), 'Una respuesta pública incluyó rutas del servidor.' );
	foreach ( array( 'lease', 'owner', 'user_id', 'checkpoint', 'staging' ) as $private ) digi_assert( false === strpos( json_encode( $S::service_jobs( 50 ) ), '"' . $private . '"' ), 'La vista de los trabajos expuso ' . $private . '.' );
	// Una copia remota sólo nombra su proveedor: ni la ruta SFTP ni el identificador de Google Drive salen en el historial ni en el estado.
	$remote_rows = array(
		array_merge( $history[0], array( 'file' => 'digitalisimo-backup-sftp.zip', 'destination' => 'sftp', 'remote' => array( 'provider' => 'sftp', 'path' => '/srv/respaldos-secretos/digitalisimo-backup-sftp.zip', 'sha256' => $history[0]['sha256'] ), 'transfer' => 'ok' ) ),
		array_merge( $history[0], array( 'file' => 'digitalisimo-backup-google.zip', 'destination' => 'google', 'remote' => array( 'provider' => 'google', 'id' => 'id-remoto-de-google-drive', 'sha256' => $history[0]['sha256'] ), 'transfer_error' => 'mensaje-del-proveedor' ) ),
	);
	update_option( $S::HISTORY, array_merge( $remote_rows, $history ) );
	$public = json_encode( $S::service_backups() ) . json_encode( $S::service_status() );
	digi_assert( false !== strpos( $public, '"remote":"google"' ) && false !== strpos( $public, '"remote":"sftp"' ) && false !== strpos( $public, '"transfer":"ok"' ) && false === strpos( $public, 'respaldos-secretos' ) && false === strpos( $public, 'id-remoto-de-google-drive' ) && false === strpos( $public, 'mensaje-del-proveedor' ), 'El historial público expuso la ruta o el identificador de una copia remota.' );
	update_option( $S::HISTORY, $history );

	// Verificación sin tocar el sitio, con las comprobaciones del paquete de la restauración.
	$report = $S::service_verify( $created['file'] );
	digi_assert( ! is_wp_error( $report ) && true === $report['verified'] && true === $report['registered'] && $created['sha256'] === $report['sha256'] && 'digitalisimo-backup/v3' === $report['format'] && $report['entries'] >= 9, 'La verificación de un respaldo íntegro falló: ' . json_encode( $report ) );
	digi_assert( 'zip' === $report['container'] && 'provenance' === $report['trust'] && 'unsigned' === $report['signature'] && 'RESTAURAR' === $report['confirmation'] && true === $report['compatible'] && array() === $report['problems'], 'La verificación no informa la confianza de un respaldo registrado: ' . json_encode( $report ) );
	foreach ( array( '', 'no-existe.zip', '../public/wp-load.php', '/etc/passwd', '.operation.lock', '.restore-journal.json' ) as $missing ) {
		$error = $S::service_verify( $missing );
		digi_assert( is_wp_error( $error ) && 404 === $error->get_error_data()['status'], 'Se verificó un archivo fuera de los respaldos del almacén: ' . $missing );
	}
	// Una copia suelta (sin fila) es no confiable: se verifica, y restaurarla pide RESTAURAR SIN FIRMA.
	$loose = 'digitalisimo-backup-suelto.zip';
	copy( $store . '/' . $created['file'], $store . '/' . $loose );
	$report = $S::service_verify( $loose );
	digi_assert( ! is_wp_error( $report ) && false === $report['registered'] && 'untrusted' === $report['trust'] && 'RESTAURAR SIN FIRMA' === $report['confirmation'], 'La copia suelta no se informó como no confiable: ' . json_encode( $report ) );
	// Una instalación con otro prefijo de tablas (desde E1): el paquete es compatible y la verificación anuncia el cambio
	// de prefijo, «Origen → este sitio», antes de confirmar.
	$db->base_prefix = 'otro_';
	$report = $S::service_verify( $created['file'] );
	digi_assert( ! is_wp_error( $report ) && true === $report['compatible'] && ! empty( $report['relocation']['needed'] ) && false !== strpos( $report['relocation']['text'], 'prefijo de tablas wp_ → otro_' ), 'La verificación no anunció el cambio de prefijo: ' . json_encode( is_wp_error( $report ) ? $report->get_error_message() : $report ) );
	$db->base_prefix = 'wp_';
	// Un respaldo individual en una red se detiene antes de la copia previa: 422 (el sitio no cambió), no 500, con qué cambiar.
	$GLOBALS['digi']['multisite'] = true;
	$GLOBALS['digi']['caps']['manage_network_options'] = true;
	$stored = digi_stored( $store );
	$error = $S::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR', 'network' => true ) );
	$GLOBALS['digi']['multisite'] = false;
	unset( $GLOBALS['digi']['caps']['manage_network_options'] );
	digi_assert( is_wp_error( $error ) && 'digitalisimo_backups_restore_rejected' === $error->get_error_code() && 422 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'Restáuralo en una instalación individual' ), 'Una restauración rechazada antes de la copia previa no respondió 422: ' . ( is_wp_error( $error ) ? $error->get_error_code() . ' ' . $error->get_error_message() : 'terminó' ) );
	digi_assert( $stored === digi_stored( $store ) && array() === array_filter( get_option( $S::HISTORY ), function( $row ) { return 'pre-restore' === $row['policy']; } ), 'La restauración rechazada creó una copia previa.' );
	$tampered = 'digitalisimo-backup-alterado.zip';
	copy( $store . '/' . $created['file'], $store . '/' . $tampered );
	$zip = new ZipArchive();
	$zip->open( $store . '/' . $tampered );
	$zip->addFromString( 'wordpress/wp-load.php', str_repeat( 'X', strlen( 'Contenido de wp-load.php' ) ) ); // mismo tamaño y CRC nuevo: sólo el SHA-256 lo delata
	$zip->close();
	$error = $S::service_verify( $tampered );
	digi_assert( is_wp_error( $error ) && 422 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'SHA-256' ), 'Se aceptó una entrada alterada.' );
	unlink( $store . '/' . $tampered );
	// Un enlace simbólico dentro del almacén que apunta a un ZIP de fuera no es un respaldo del almacén: ni se verifica ni se restaura.
	$outside = $root . '/fuera-del-almacen.zip';
	copy( $store . '/' . $created['file'], $outside );
	symlink( $outside, $store . '/digitalisimo-backup-enlazado.zip' );
	try {
		foreach ( array( $S::service_verify( 'digitalisimo-backup-enlazado.zip' ), $S::service_restore( 'digitalisimo-backup-enlazado.zip', array( 'confirm' => 'RESTAURAR SIN FIRMA' ) ) ) as $error ) {
			digi_assert( is_wp_error( $error ) && 404 === $error->get_error_data()['status'], 'Se usó un ZIP de fuera del almacén a través de un enlace simbólico.' );
		}
	} finally {
		unlink( $store . '/digitalisimo-backup-enlazado.zip' );
		unlink( $outside );
	}
	$history = get_option( $S::HISTORY );
	$original = $history[1]['sha256'];
	$history[1]['sha256'] = str_repeat( '0', 64 );
	update_option( $S::HISTORY, $history );
	$error = $S::service_verify( $created['file'] );
	digi_assert( is_wp_error( $error ) && false !== strpos( $error->get_error_message(), 'ya no coincide' ), 'Se aceptó un ZIP distinto del registrado.' );
	$history[1]['sha256'] = $original;
	update_option( $S::HISTORY, $history );

	// Restauración: confirmación, archivo, confianza, y el estado anterior vuelve con una copia previa bloqueada.
	$error = $S::service_restore( $created['file'], array( 'confirm' => 'si' ) );
	digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'RESTAURAR' ), 'Se restauró sin la confirmación RESTAURAR.' );
	$error = $S::service_restore( 'no-existe.zip', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( is_wp_error( $error ) && 404 === $error->get_error_data()['status'], 'Se aceptó un respaldo inexistente.' );
	$before = count( digi_stored( $store ) );
	$error = $S::service_restore( $loose, array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( is_wp_error( $error ) && 'digitalisimo_backups_confirm_unsigned' === $error->get_error_code() && 400 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'RESTAURAR SIN FIRMA' ), 'Una copia sin firma ni registro se restauró con RESTAURAR.' );
	update_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => false, 'require_signed' => true ) );
	$error = $S::service_restore( $loose, array( 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	digi_assert( is_wp_error( $error ) && 'digitalisimo_backups_signed_only' === $error->get_error_code() && 403 === $error->get_error_data()['status'], '«Restaurar sólo respaldos firmados» no bloqueó una copia sin firma.' );
	digi_assert( $S::service_status()['require_signed'], 'El estado no muestra «sólo firmados».' );
	delete_option( Digitalisimo_Backups_Security::OPTION );
	digi_assert( $before === count( digi_stored( $store ) ), 'Una restauración rechazada creó su copia previa.' );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado después del respaldo\n" );
	$db->set( 'blogname', 'Cambiado después del respaldo' );
	$restored = $S::service_restore( $created['file'], array( 'confirm' => ' restaurar ' ) );
	digi_assert( ! is_wp_error( $restored ) && true === $restored['restored'] && 'provenance' === $restored['trust'] && 0 === strpos( $restored['safety_backup'], 'safety-before-restore-' ) && '' === $restored['notice'] && false === $restored['adopted'], 'La restauración no terminó: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : json_encode( $restored ) ) );
	digi_assert( "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ) && 'Sitio de prueba' === $db->value( 'blogname' ), 'Los archivos o la base de datos no volvieron al respaldo.' );
	$history = get_option( $S::HISTORY );
	digi_assert( 'pre-restore' === $history[0]['policy'] && ! empty( $history[0]['locked'] ) && $restored['safety_backup'] === $history[0]['file'] && is_file( $store . '/' . $history[0]['file'] ), 'La restauración no dejó la copia previa bloqueada.' );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado antes de la copia suelta\n" );
	$restored = $S::service_restore( $loose, array( 'confirm' => 'restaurar  sin firma' ) );
	digi_assert( ! is_wp_error( $restored ) && 'untrusted' === $restored['trust'] && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'La copia suelta no se restauró con RESTAURAR SIN FIRMA.' );
	unlink( $store . '/' . $loose );

	// «Restaurar» de la pantalla pasa por el mismo caso de uso.
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado otra vez\n" );
	$query = digi_admin_post( 'restore_request', $S::ACTION_RESTORE, array( 'backup_file' => $created['file'], 'confirmation' => 'RESTAURAR' ) );
	digi_assert( 'ok' === $query['backup'] && 'Restauración terminada. Entra de nuevo y revisa enlaces permanentes.' === $query['message'] && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'La pantalla no restauró por el caso de uso: ' . json_encode( $query ) );
	digi_assert( 3 === count( array_filter( get_option( $S::HISTORY ), function( $row ) { return 'pre-restore' === $row['policy']; } ) ), 'La restauración de la pantalla no creó su copia previa.' );
	$query = digi_admin_post( 'restore_request', $S::ACTION_RESTORE, array( 'backup_file' => 'no-existe.zip', 'confirmation' => 'RESTAURAR' ) );
	digi_assert( 'error' === $query['backup'] && 'La restauración se detuvo: El respaldo local solicitado no está disponible.' === $query['message'], 'La pantalla perdió el aviso de respaldo inexistente: ' . json_encode( $query ) );
	$query = digi_admin_post( 'restore_request', $S::ACTION_RESTORE, array( 'backup_file' => $created['file'], 'confirmation' => '' ) );
	digi_assert( 'error' === $query['backup'] && false !== strpos( $query['message'], 'RESTAURAR' ), 'La pantalla restauró sin confirmación.' );

	// Automatizaciones: lista con próxima ejecución y «Ejecutar ahora» encola por run_policy() sin duplicar.
	$GLOBALS['digi']['scheduled'] = 1800000000;
	$automations = $S::service_automations();
	digi_assert( 1 === count( $automations ) && 'monthly-full' === $automations[0]['id'] && true === $automations[0]['enabled'] && '2027-01-15T08:00:00+00:00' === $automations[0]['next_run'], 'La lista de automatizaciones no es la esperada: ' . json_encode( $automations ) );
	$ran = $S::service_run_automation( 'monthly-full', array( 'via' => 'cli' ) );
	digi_assert( ! is_wp_error( $ran ) && 'monthly-full' === $ran['policy'] && 'queued' === $ran['status'] && 'Automatización' === $ran['origin_label'], 'La automatización no se encoló: ' . json_encode( is_wp_error( $ran ) ? $ran->get_error_message() : $ran ) );
	$error = $S::service_run_automation( 'monthly-full' );
	digi_assert( is_wp_error( $error ) && 409 === $error->get_error_data()['status'] && $ran['id'] === $error->get_error_data()['job'], 'Se encoló dos veces la misma automatización.' );
	$query = digi_admin_post( 'run_policy_request', $S::ACTION_RUN_POLICY, array( 'digitalisimo_backup_policy' => 'monthly-full' ) );
	digi_assert( 'error' === $query['backup'] && 'Esta automatización ya tiene un respaldo pendiente o en curso; su avance está en «Trabajos».' === $query['message'], '«Ejecutar ahora» de la pantalla no pasó por el caso de uso.' );
	$done = digi_drive( $ran['id'] );
	digi_assert( 'success' === $done['status'] && 'monthly-full' === get_option( $S::HISTORY )[0]['policy'], 'La automatización no creó su respaldo.' );
	$query = digi_admin_post( 'run_policy_request', $S::ACTION_RUN_POLICY, array( 'digitalisimo_backup_policy' => 'monthly-full' ) );
	digi_assert( 'ok' === $query['backup'] && 1 === count( Digitalisimo_Backups_Jobs::active() ), '«Ejecutar ahora» de la pantalla no encoló.' );
	digi_drive( Digitalisimo_Backups_Jobs::active()[0]['id'] );
	$error = $S::service_run_automation( 'no-existe' );
	digi_assert( is_wp_error( $error ) && 404 === $error->get_error_data()['status'], 'Se ejecutó una automatización inexistente.' );

	// Red: capability de red, sitio principal y alcance de red explícito.
	$GLOBALS['digi']['multisite'] = true;
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	$error = $S::service_backups();
	digi_assert( is_wp_error( $error ) && 403 === $error->get_error_data()['status'], 'En una red, un administrador de sitio consultó los respaldos de toda la red.' );
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
	$error = $S::service_create();
	digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'alcance de red' ), 'En una red se creó un respaldo sin confirmar el alcance de red.' );
	$network = $S::service_create( array( 'network' => true ) );
	digi_assert( ! is_wp_error( $network ) && 'network' === Digitalisimo_Backups_Jobs::get( $network['id'] )['spec']['scope'] && 'network' === Digitalisimo_Backups_Jobs::get( $network['id'] )['spec']['origin']['context'], 'La red no encoló su respaldo completo: ' . ( is_wp_error( $network ) ? $network->get_error_message() : '' ) );
	$network = digi_drive( $network['id'] );
	digi_assert( 'success' === $network['status'] && 'network' === get_option( $S::HISTORY )[0]['scope'], 'La red no creó su respaldo completo.' );
	$query = digi_admin_post( 'run', $S::ACTION_RUN, array( 'digitalisimo_backups_network' => '1' ) );
	digi_assert( 'ok' === $query['backup'], 'El botón de la red no encoló el respaldo de la red.' );
	digi_drive( Digitalisimo_Backups_Jobs::active()[0]['id'] );
	digi_assert( 'network' === get_option( $S::HISTORY )[0]['scope'], 'El botón de la red no creó el respaldo de la red.' );
	// «Restaurar» de la pantalla de la red restaura la red entera: confirma el alcance de red por su cuenta.
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado en la red\n" );
	$query = digi_admin_post( 'restore_request', $S::ACTION_RESTORE, array( 'backup_file' => $network['file'], 'confirmation' => 'RESTAURAR', 'digitalisimo_backups_network' => '1' ) );
	digi_assert( 'ok' === $query['backup'] && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'En una red, «Restaurar» de la pantalla no restauró la red: ' . json_encode( $query ) );
	$ran = $S::service_run_automation( 'monthly-full', array( 'network' => true ) );
	digi_assert( ! is_wp_error( $ran ) && 'monthly-full' === $ran['policy'], 'En una red, la automatización no se encoló con el alcance confirmado.' );
	digi_assert( 'success' === digi_drive( $ran['id'] )['status'] && 'network' === get_option( $S::HISTORY )[0]['scope'], 'En una red, la automatización no respaldó la red.' );
	$GLOBALS['digi']['main'] = false;
	foreach ( array( $S::service_create( array( 'network' => true ) ), $S::service_backups(), $S::service_verify( $network['file'] ), $S::service_restore( $network['file'], array( 'confirm' => 'RESTAURAR', 'network' => true ) ), $S::service_automations(), $S::service_run_automation( 'monthly-full', array( 'network' => true ) ), $S::service_jobs(), $S::service_job( $ran['id'] ), $S::service_cancel( $ran['id'] ), $S::service_tick() ) as $error ) {
		digi_assert( is_wp_error( $error ) && 'digitalisimo_backups_main_site' === $error->get_error_code(), 'Un subsitio operó sobre los respaldos de la red.' );
	}
	$status = $S::service_status();
	digi_assert( false === $status['main_site'] && false === $status['ready'] && 0 === $status['backups'] && 0 === $status['jobs'] && false !== strpos( implode( ' ', $status['problems'] ), 'sitio principal' ), 'El estado de un subsitio no explica dónde gestionar los respaldos.' );
	$query = digi_admin_post( 'run', $S::ACTION_RUN, array() );
	digi_assert( 'error' === $query['backup'] && false !== strpos( $query['message'], 'sitio principal' ), 'La pantalla de un subsitio inició un respaldo.' );
	// Restaurar un conjunto de UpdraftPlus también reemplaza la instalación: desde un subsitio, ni se intenta.
	$query = digi_admin_post( 'restore_request', $S::ACTION_RESTORE, array( 'updraft' => '1', 'updraft_set' => 'cualquiera', 'confirmation' => 'RESTAURAR SIN FIRMA' ) );
	digi_assert( 'error' === $query['backup'] && false !== strpos( $query['message'], 'sitio principal' ), 'Un subsitio intentó restaurar un conjunto de UpdraftPlus.' );

	// Varias redes en la misma base de datos: el superadministrador de una red secundaria, en su sitio principal, no
	// copia ni reemplaza las demás por ninguna superficie, y el motor tampoco exporta desde ahí (vista con avance y cron).
	$GLOBALS['digi']['main'] = true;
	$GLOBALS['digi']['main_network'] = false;
	$stored = digi_stored( $store );
	$history = get_option( $S::HISTORY );
	foreach ( array( $S::service_create( array( 'network' => true ) ), $S::service_backups(), $S::service_verify( $network['file'] ), $S::service_restore( $network['file'], array( 'confirm' => 'RESTAURAR', 'network' => true ) ), $S::service_automations(), $S::service_run_automation( 'monthly-full', array( 'network' => true ) ), $S::service_jobs(), $S::service_tick() ) as $error ) {
		digi_assert( is_wp_error( $error ) && 'digitalisimo_backups_main_site' === $error->get_error_code() && false !== strpos( $error->get_error_message(), 'varias redes' ), 'Una red secundaria operó sobre los respaldos de toda la instalación.' );
	}
	$status = $S::service_status();
	digi_assert( false === $status['main_site'] && false === $status['ready'] && false !== strpos( implode( ' ', $status['problems'] ), 'red principal' ), 'El estado de una red secundaria no explica dónde gestionar los respaldos.' );
	foreach ( array( array( 'run', $S::ACTION_RUN, array( 'digitalisimo_backups_network' => '1' ) ), array( 'restore_request', $S::ACTION_RESTORE, array( 'backup_file' => $network['file'], 'confirmation' => 'RESTAURAR' ) ) ) as $call ) {
		$query = digi_admin_post( $call[0], $call[1], $call[2] );
		digi_assert( 'error' === $query['backup'] && false !== strpos( $query['message'], 'varias redes' ), 'La pantalla de una red secundaria operó sobre toda la instalación: ' . $call[0] );
	}
	$create = new ReflectionMethod( 'Digitalisimo_Backups', 'create_network' );
	if ( PHP_VERSION_ID < 80100 ) $create->setAccessible( true );
	try {
		$create->invoke( null );
		throw new RuntimeException( 'FALLA: el motor exportó la instalación desde una red secundaria.' );
	} catch ( Exception $e ) {
		digi_assert( false !== strpos( $e->getMessage(), 'varias redes' ), 'El motor no explicó por qué no respalda desde una red secundaria: ' . $e->getMessage() );
	}
	digi_assert( $stored === digi_stored( $store ) && $history === get_option( $S::HISTORY ), 'Una red secundaria dejó un ZIP o cambió el historial.' );
} finally {
	digi_rmtree( $root );
}
echo "DIGITALÍSIMO Backups: pantalla, REST y WP-CLI comparten crear (en cola), trabajos, verificar, restaurar y automatizaciones con el mismo alcance.\n";
