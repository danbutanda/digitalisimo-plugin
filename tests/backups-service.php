<?php
/**
 * La pantalla Backups, REST y WP-CLI comparten los casos de uso de Digitalisimo_Backups_Service: el botón «Crear
 * respaldo completo» y «Restaurar» pasan por ellos, y en una red el alcance se exige desde el sitio principal.
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
$store = $root . '/private';
$db = $GLOBALS['wpdb'];

try {
	// Estado real antes de crear nada.
	$status = Digitalisimo_Backups::service_status();
	digi_assert( true === $status['ready'] && array() === $status['problems'], 'Una instalación completa debe estar lista: ' . json_encode( $status['problems'] ) );
	digi_assert( 'installation' === $status['scope'] && false === $status['busy'] && 'abspath' === $status['wp_config'] && 0 === $status['backups'] && isset( $status['destinations']['local'] ), 'El estado no describe la instalación individual.' );
	digi_assert( 1 === $status['automations'], 'El estado debe contar la automatización completa por defecto.' );

	// Sin la capability de la pantalla nada se crea ni se consulta.
	$GLOBALS['digi']['caps'] = array( 'edit_posts' => true );
	foreach ( array( Digitalisimo_Backups::service_status(), Digitalisimo_Backups::service_create(), Digitalisimo_Backups::service_backups(), Digitalisimo_Backups::service_restore( 'x.zip', array( 'confirm' => 'RESTAURAR' ) ), Digitalisimo_Backups::service_verify( 'x.zip' ), Digitalisimo_Backups::service_automations(), Digitalisimo_Backups::service_run_automation( 'monthly-full' ) ) as $denied ) {
		digi_assert( is_wp_error( $denied ) && 403 === $denied->get_error_data()['status'], 'Un usuario sin capability usó el motor.' );
	}
	digi_assert( array() === digi_stored( $store ), 'Se creó un ZIP sin autorización.' );
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );

	// Alcance y destino.
	$error = Digitalisimo_Backups::service_create( array( 'network' => true ) );
	digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'no es una red' ), 'Una instalación individual aceptó el alcance de red.' );
	$error = Digitalisimo_Backups::service_create( array( 'destination' => 'sftp' ) );
	digi_assert( is_wp_error( $error ) && 'digitalisimo_backups_destination' === $error->get_error_code(), 'Se aceptó un destino sin conexión.' );

	// Respaldo completo por el caso de uso: ZIP verificado, historial y resumen sin rutas.
	$created = Digitalisimo_Backups::service_create();
	digi_assert( ! is_wp_error( $created ), 'El respaldo falló: ' . ( is_wp_error( $created ) ? $created->get_error_message() : '' ) );
	digi_assert( preg_match( '/^digitalisimo-backup-example-test-\d{8}-\d{6}-[0-9a-f]{8}\.zip$/', $created['file'] ) && $created['available'] && $created['restorable'] && 'manual' === $created['policy'] && 'installation' === $created['scope'], 'El resumen del respaldo no es el esperado: ' . json_encode( $created ) );
	digi_assert( hash_file( 'sha256', $store . '/' . $created['file'] ) === $created['sha256'], 'El SHA-256 del resumen no es el del ZIP.' );
	$history = get_option( Digitalisimo_Backups::HISTORY );
	digi_assert( 1 === count( $history ) && $created['file'] === $history[0]['file'] && 'local' === $history[0]['destination'], 'El historial no registró el respaldo.' );

	// El botón «Crear respaldo completo» (admin-post) usa el mismo caso de uso y conserva su redirección.
	$query = digi_admin_post( 'run', Digitalisimo_Backups::ACTION_RUN, array( 'digitalisimo_backup_scope' => 'installation', 'digitalisimo_backup_destination' => 'local' ) );
	$history = get_option( Digitalisimo_Backups::HISTORY );
	digi_assert( 'ok' === $query['backup'] && 'Respaldo completo creado: ' . $history[0]['file'] . ' en Hosting / local.' === $query['message'], 'La pantalla no informó el respaldo creado: ' . json_encode( $query ) );
	digi_assert( 2 === count( $history ) && 'manual' === $history[0]['policy'] && $history[0]['sha256'] === hash_file( 'sha256', $store . '/' . $history[0]['file'] ), 'La pantalla no registró el respaldo como el caso de uso.' );
	$query = digi_admin_post( 'run', Digitalisimo_Backups::ACTION_RUN, array( 'digitalisimo_backup_destination' => 'google' ) );
	digi_assert( 'error' === $query['backup'] && 'El destino seleccionado no está conectado o no es válido.' === $query['message'], 'La pantalla perdió el aviso de destino no válido.' );
	try {
		$GLOBALS['digi']['caps'] = array();
		digi_admin_post( 'run', Digitalisimo_Backups::ACTION_RUN, array() );
		throw new RuntimeException( 'FALLA: la pantalla creó un respaldo sin capability.' );
	} catch ( Digi_Die $denied ) {
		$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	}

	// Otra operación en curso: 409 en crear, restaurar y ejecutar; el estado lo muestra.
	$lock = digi_hold_lock();
	try {
		foreach ( array( Digitalisimo_Backups::service_create(), Digitalisimo_Backups::service_restore( $created['file'], array( 'confirm' => 'RESTAURAR' ) ), Digitalisimo_Backups::service_run_automation( 'monthly-full' ) ) as $busy ) {
			digi_assert( is_wp_error( $busy ) && 409 === $busy->get_error_data()['status'] && false !== strpos( $busy->get_error_message(), 'Ya hay un respaldo' ), 'Una operación se solapó con otra en curso.' );
		}
		digi_assert( true === Digitalisimo_Backups::service_status()['busy'], 'El estado no mostró la operación en curso.' );
	} finally {
		digi_release_lock( $lock );
	}
	digi_assert( false === Digitalisimo_Backups::service_status()['busy'], 'El estado siguió ocupado tras liberar el bloqueo.' );
	digi_assert( 2 === count( digi_stored( $store ) ), 'Una operación rechazada dejó un ZIP.' );

	// Historial público: sin rutas del servidor ni identificadores remotos.
	$backups = Digitalisimo_Backups::service_backups();
	digi_assert( 2 === count( $backups ) && $history[0]['file'] === $backups[0]['file'] && $created['file'] === $backups[1]['file'], 'El historial no va del más reciente al más antiguo.' );
	digi_assert( false === strpos( json_encode( $backups ), $root ) && false === strpos( json_encode( Digitalisimo_Backups::service_status() ), $root ), 'Una respuesta pública incluyó rutas del servidor.' );
	// Una copia remota sólo nombra su proveedor: ni la ruta SFTP ni el identificador de Google Drive salen en el historial ni en el estado.
	$remote_rows = array(
		array_merge( $history[0], array( 'file' => 'digitalisimo-backup-sftp.zip', 'destination' => 'sftp', 'remote' => array( 'provider' => 'sftp', 'path' => '/srv/respaldos-secretos/digitalisimo-backup-sftp.zip', 'sha256' => $history[0]['sha256'] ) ) ),
		array_merge( $history[0], array( 'file' => 'digitalisimo-backup-google.zip', 'destination' => 'google', 'remote' => array( 'provider' => 'google', 'id' => 'id-remoto-de-google-drive', 'sha256' => $history[0]['sha256'] ) ) ),
	);
	update_option( Digitalisimo_Backups::HISTORY, array_merge( $remote_rows, $history ) );
	$public = json_encode( Digitalisimo_Backups::service_backups() ) . json_encode( Digitalisimo_Backups::service_status() );
	digi_assert( false !== strpos( $public, '"remote":"google"' ) && false !== strpos( $public, '"remote":"sftp"' ) && false === strpos( $public, 'respaldos-secretos' ) && false === strpos( $public, 'id-remoto-de-google-drive' ), 'El historial público expuso la ruta o el identificador de una copia remota.' );
	update_option( Digitalisimo_Backups::HISTORY, $history );

	// Verificación sin tocar el sitio.
	$report = Digitalisimo_Backups::service_verify( $created['file'] );
	digi_assert( ! is_wp_error( $report ) && true === $report['verified'] && true === $report['registered'] && $created['sha256'] === $report['sha256'] && 'digitalisimo-backup/v3' === $report['format'] && $report['entries'] >= 9, 'La verificación de un respaldo íntegro falló: ' . json_encode( $report ) );
	foreach ( array( '', 'no-existe.zip', '../public/wp-load.php', '/etc/passwd' ) as $missing ) {
		$error = Digitalisimo_Backups::service_verify( $missing );
		digi_assert( is_wp_error( $error ) && 404 === $error->get_error_data()['status'], 'Se verificó un archivo fuera del almacén: ' . $missing );
	}
	$tampered = 'digitalisimo-backup-alterado.zip';
	copy( $store . '/' . $created['file'], $store . '/' . $tampered );
	$zip = new ZipArchive();
	$zip->open( $store . '/' . $tampered );
	$zip->addFromString( 'wordpress/wp-load.php', str_repeat( 'X', strlen( 'Contenido de wp-load.php' ) ) ); // mismo tamaño y CRC nuevo: sólo el SHA-256 lo delata
	$zip->close();
	$error = Digitalisimo_Backups::service_verify( $tampered );
	digi_assert( is_wp_error( $error ) && 422 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'SHA-256' ), 'Se aceptó una entrada alterada.' );
	unlink( $store . '/' . $tampered );
	// Un enlace simbólico dentro del almacén que apunta a un ZIP de fuera no es un respaldo del almacén: ni se verifica ni se restaura.
	$outside = $root . '/fuera-del-almacen.zip';
	copy( $store . '/' . $created['file'], $outside );
	symlink( $outside, $store . '/digitalisimo-backup-enlazado.zip' );
	try {
		foreach ( array( Digitalisimo_Backups::service_verify( 'digitalisimo-backup-enlazado.zip' ), Digitalisimo_Backups::service_restore( 'digitalisimo-backup-enlazado.zip', array( 'confirm' => 'RESTAURAR' ) ) ) as $error ) {
			digi_assert( is_wp_error( $error ) && 404 === $error->get_error_data()['status'], 'Se usó un ZIP de fuera del almacén a través de un enlace simbólico.' );
		}
	} finally {
		unlink( $store . '/digitalisimo-backup-enlazado.zip' );
		unlink( $outside );
	}
	$history = get_option( Digitalisimo_Backups::HISTORY );
	$original = $history[1]['sha256'];
	$history[1]['sha256'] = str_repeat( '0', 64 );
	update_option( Digitalisimo_Backups::HISTORY, $history );
	$error = Digitalisimo_Backups::service_verify( $created['file'] );
	digi_assert( is_wp_error( $error ) && false !== strpos( $error->get_error_message(), 'ya no coincide' ), 'Se aceptó un ZIP distinto del registrado.' );
	$history[1]['sha256'] = $original;
	update_option( Digitalisimo_Backups::HISTORY, $history );

	// Restauración: confirmación, archivo, y el estado anterior vuelve con una copia previa bloqueada.
	$error = Digitalisimo_Backups::service_restore( $created['file'], array( 'confirm' => 'si' ) );
	digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'RESTAURAR' ), 'Se restauró sin la confirmación RESTAURAR.' );
	$error = Digitalisimo_Backups::service_restore( 'no-existe.zip', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( is_wp_error( $error ) && 404 === $error->get_error_data()['status'], 'Se aceptó un respaldo inexistente.' );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado después del respaldo\n" );
	$db->set( 'blogname', 'Cambiado después del respaldo' );
	$restored = Digitalisimo_Backups::service_restore( $created['file'], array( 'confirm' => ' restaurar ' ) );
	digi_assert( ! is_wp_error( $restored ) && true === $restored['restored'] && 0 === strpos( $restored['safety_backup'], 'safety-before-restore-' ), 'La restauración no terminó: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : json_encode( $restored ) ) );
	digi_assert( "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ) && 'Sitio de prueba' === $db->value( 'blogname' ), 'Los archivos o la base de datos no volvieron al respaldo.' );
	$history = get_option( Digitalisimo_Backups::HISTORY );
	digi_assert( 'pre-restore' === $history[0]['policy'] && ! empty( $history[0]['locked'] ) && $restored['safety_backup'] === $history[0]['file'] && is_file( $store . '/' . $history[0]['file'] ), 'La restauración no dejó la copia previa bloqueada.' );

	// «Restaurar» de la pantalla pasa por el mismo caso de uso.
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado otra vez\n" );
	$query = digi_admin_post( 'restore_request', Digitalisimo_Backups::ACTION_RESTORE, array( 'backup_file' => $created['file'], 'confirmation' => 'RESTAURAR' ) );
	digi_assert( 'ok' === $query['backup'] && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'La pantalla no restauró por el caso de uso: ' . json_encode( $query ) );
	digi_assert( 2 === count( array_filter( get_option( Digitalisimo_Backups::HISTORY ), function( $row ) { return 'pre-restore' === $row['policy']; } ) ), 'La restauración de la pantalla no creó su copia previa.' );
	$query = digi_admin_post( 'restore_request', Digitalisimo_Backups::ACTION_RESTORE, array( 'backup_file' => 'no-existe.zip', 'confirmation' => 'RESTAURAR' ) );
	digi_assert( 'error' === $query['backup'] && 'La restauración se detuvo: El respaldo local solicitado no está disponible.' === $query['message'], 'La pantalla perdió el aviso de respaldo inexistente: ' . json_encode( $query ) );
	$query = digi_admin_post( 'restore_request', Digitalisimo_Backups::ACTION_RESTORE, array( 'backup_file' => $created['file'], 'confirmation' => '' ) );
	digi_assert( 'error' === $query['backup'] && false !== strpos( $query['message'], 'RESTAURAR' ), 'La pantalla restauró sin confirmación.' );

	// Automatizaciones: lista con próxima ejecución y «Ejecutar ahora» por run_policy().
	$GLOBALS['digi']['scheduled'] = 1800000000;
	$automations = Digitalisimo_Backups::service_automations();
	digi_assert( 1 === count( $automations ) && 'monthly-full' === $automations[0]['id'] && true === $automations[0]['enabled'] && '2027-01-15T08:00:00+00:00' === $automations[0]['next_run'], 'La lista de automatizaciones no es la esperada: ' . json_encode( $automations ) );
	$ran = Digitalisimo_Backups::service_run_automation( 'monthly-full' );
	digi_assert( ! is_wp_error( $ran ) && 'monthly-full' === $ran['policy'] && $ran['available'], 'La automatización no creó su respaldo.' );
	$error = Digitalisimo_Backups::service_run_automation( 'no-existe' );
	digi_assert( is_wp_error( $error ) && 404 === $error->get_error_data()['status'], 'Se ejecutó una automatización inexistente.' );

	// Red: capability de red, sitio principal y alcance de red explícito.
	$GLOBALS['digi']['multisite'] = true;
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	$error = Digitalisimo_Backups::service_backups();
	digi_assert( is_wp_error( $error ) && 403 === $error->get_error_data()['status'], 'En una red, un administrador de sitio consultó los respaldos de toda la red.' );
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
	$error = Digitalisimo_Backups::service_create();
	digi_assert( is_wp_error( $error ) && 400 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'alcance de red' ), 'En una red se creó un respaldo sin confirmar el alcance de red.' );
	$network = Digitalisimo_Backups::service_create( array( 'network' => true ) );
	digi_assert( ! is_wp_error( $network ) && 'network' === $network['scope'], 'La red no creó su respaldo completo: ' . ( is_wp_error( $network ) ? $network->get_error_message() : '' ) );
	$query = digi_admin_post( 'run', Digitalisimo_Backups::ACTION_RUN, array( 'digitalisimo_backups_network' => '1' ) );
	digi_assert( 'ok' === $query['backup'] && 'network' === get_option( Digitalisimo_Backups::HISTORY )[0]['scope'], 'El botón de la red no creó el respaldo de la red.' );
	// «Restaurar» de la pantalla de la red restaura la red entera: confirma el alcance de red por su cuenta.
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado en la red\n" );
	$query = digi_admin_post( 'restore_request', Digitalisimo_Backups::ACTION_RESTORE, array( 'backup_file' => $network['file'], 'confirmation' => 'RESTAURAR', 'digitalisimo_backups_network' => '1' ) );
	digi_assert( 'ok' === $query['backup'] && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'En una red, «Restaurar» de la pantalla no restauró la red: ' . json_encode( $query ) );
	$ran = Digitalisimo_Backups::service_run_automation( 'monthly-full', array( 'network' => true ) );
	digi_assert( ! is_wp_error( $ran ) && 'network' === $ran['scope'] && 'monthly-full' === $ran['policy'], 'En una red, la automatización no respaldó la red con el alcance confirmado.' );
	$GLOBALS['digi']['main'] = false;
	foreach ( array( Digitalisimo_Backups::service_create( array( 'network' => true ) ), Digitalisimo_Backups::service_backups(), Digitalisimo_Backups::service_verify( $network['file'] ), Digitalisimo_Backups::service_restore( $network['file'], array( 'confirm' => 'RESTAURAR', 'network' => true ) ), Digitalisimo_Backups::service_automations(), Digitalisimo_Backups::service_run_automation( 'monthly-full', array( 'network' => true ) ) ) as $error ) {
		digi_assert( is_wp_error( $error ) && 'digitalisimo_backups_main_site' === $error->get_error_code(), 'Un subsitio operó sobre los respaldos de la red.' );
	}
	$status = Digitalisimo_Backups::service_status();
	digi_assert( false === $status['main_site'] && false === $status['ready'] && 0 === $status['backups'] && false !== strpos( implode( ' ', $status['problems'] ), 'sitio principal' ), 'El estado de un subsitio no explica dónde gestionar los respaldos.' );
	$query = digi_admin_post( 'run', Digitalisimo_Backups::ACTION_RUN, array() );
	digi_assert( 'error' === $query['backup'] && false !== strpos( $query['message'], 'sitio principal' ), 'La pantalla de un subsitio inició un respaldo.' );

	// Varias redes en la misma base de datos: el superadministrador de una red secundaria, en su sitio principal, no
	// copia ni reemplaza las demás por ninguna superficie, y el motor tampoco exporta desde ahí (vista con avance y cron).
	$GLOBALS['digi']['main'] = true;
	$GLOBALS['digi']['main_network'] = false;
	$stored = digi_stored( $store );
	$history = get_option( Digitalisimo_Backups::HISTORY );
	foreach ( array( Digitalisimo_Backups::service_create( array( 'network' => true ) ), Digitalisimo_Backups::service_backups(), Digitalisimo_Backups::service_verify( $network['file'] ), Digitalisimo_Backups::service_restore( $network['file'], array( 'confirm' => 'RESTAURAR', 'network' => true ) ), Digitalisimo_Backups::service_automations(), Digitalisimo_Backups::service_run_automation( 'monthly-full', array( 'network' => true ) ) ) as $error ) {
		digi_assert( is_wp_error( $error ) && 'digitalisimo_backups_main_site' === $error->get_error_code() && false !== strpos( $error->get_error_message(), 'varias redes' ), 'Una red secundaria operó sobre los respaldos de toda la instalación.' );
	}
	$status = Digitalisimo_Backups::service_status();
	digi_assert( false === $status['main_site'] && false === $status['ready'] && false !== strpos( implode( ' ', $status['problems'] ), 'red principal' ), 'El estado de una red secundaria no explica dónde gestionar los respaldos.' );
	foreach ( array( array( 'run', Digitalisimo_Backups::ACTION_RUN, array( 'digitalisimo_backups_network' => '1' ) ), array( 'restore_request', Digitalisimo_Backups::ACTION_RESTORE, array( 'backup_file' => $network['file'], 'confirmation' => 'RESTAURAR' ) ) ) as $call ) {
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
	digi_assert( $stored === digi_stored( $store ) && $history === get_option( Digitalisimo_Backups::HISTORY ), 'Una red secundaria dejó un ZIP o cambió el historial.' );
} finally {
	digi_rmtree( $root );
}
echo "DIGITALÍSIMO Backups: pantalla, REST y WP-CLI comparten crear, verificar, restaurar y automatizaciones con el mismo alcance.\n";
