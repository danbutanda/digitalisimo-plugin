<?php
/**
 * API REST `digitalisimo-backups/v1`: cada ruta pide la capability de la pantalla antes de llamar al caso de uso
 * compartido, y en una red exige el sitio principal y `network=true` para lo que abarca todos los sitios. Crear y
 * ejecutar encolan (202) en la cola de «Trabajos», que se consulta, avanza y cancela por la API; restaurar y verificar
 * abren el paquete con «Seguridad»: un respaldo cifrado sin clave da un error claro, y la frase sólo viaja en el cuerpo
 * de la petición y nunca vuelve en una respuesta.
 */
$root = sys_get_temp_dir() . '/digitalisimo-rest-' . bin2hex( random_bytes( 4 ) );
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
$ns = 'digitalisimo-backups/v1';
$job_route = '/jobs/' . Digitalisimo_Backups_Rest::JOB;
/** POST /jobs/tick hasta que el trabajo termina; devuelve GET /jobs/<id>. */
$drive = function( $id ) use ( $ns ) {
	for ( $i = 0; $i < 20; $i++ ) {
		list( $code, $job ) = digi_rest( 'GET', $ns . '/jobs/' . $id );
		digi_assert( 200 === $code, 'GET /jobs/<id> respondió ' . $code . ': ' . json_encode( $job ) );
		if ( $job['terminal'] ) return $job;
		list( $code, $tick ) = digi_rest( 'POST', $ns . '/jobs/tick' );
		digi_assert( 200 === $code && 'error' !== $tick['result'], 'POST /jobs/tick falló: ' . json_encode( $tick ) );
	}
	throw new RuntimeException( 'FALLA: el trabajo ' . $id . ' no terminó por la API.' );
};

try {
	// Registro: sólo en rest_api_init, con las rutas declaradas y un permiso real en cada una.
	Digitalisimo_Backups_Rest::register();
	digi_assert( array() === $GLOBALS['digi']['routes'] && 1 === count( $GLOBALS['digi']['actions']['rest_api_init'] ?? array() ), 'Las rutas deben registrarse en rest_api_init.' );
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	$expected = array( '/status' => 'GET', '/backups' => 'GET,POST', '/backups/' . Digitalisimo_Backups_Rest::FILE . '/verify' => 'GET, POST', '/backups/' . Digitalisimo_Backups_Rest::FILE . '/restore' => 'POST', '/backups/' . Digitalisimo_Backups_Rest::FILE . '/fetch' => 'POST', '/destinations' => 'GET', '/destinations/(?P<id>[a-z0-9]+(?:-[a-f0-9]{8})?)/test' => 'POST', '/automations' => 'GET', '/automations/(?P<id>[a-z0-9_-]+)/run' => 'POST', '/jobs' => 'GET', '/jobs/tick' => 'POST', $job_route => 'GET', $job_route . '/cancel' => 'POST', '/sites/(?P<site>[0-9]+)/extract' => 'POST', '/uploads' => 'GET,POST', '/uploads/' . Digitalisimo_Backups_Rest::JOB => 'GET,DELETE', '/uploads/' . Digitalisimo_Backups_Rest::JOB . '/chunk' => 'POST, PUT', '/uploads/' . Digitalisimo_Backups_Rest::JOB . '/finish' => 'POST' );
	// Respaldo antes de actualizar (I1).
	$expected += array( '/pre-update' => 'GET,POST', '/pre-update/backup' => 'POST', '/pre-update/skip' => 'POST' );
	digi_assert( array_map( function( $route ) use ( $ns ) { return $ns . $route; }, array_keys( $expected ) ) === array_keys( $GLOBALS['digi']['routes'] ), 'Las rutas registradas no son las documentadas: ' . implode( ', ', array_keys( $GLOBALS['digi']['routes'] ) ) );
	foreach ( $expected as $route => $methods ) {
		$handlers = $GLOBALS['digi']['routes'][ $ns . $route ];
		digi_assert( $methods === implode( ',', array_column( $handlers, 'methods' ) ), 'Métodos inesperados en ' . $route );
		foreach ( $handlers as $handler ) digi_assert( array( 'Digitalisimo_Backups_Rest', 'allowed' ) === $handler['permission_callback'], 'La ruta ' . $route . ' no usa la capability de la pantalla.' );
	}
	digi_assert( ! preg_match( '@^' . $job_route . '$@', '/jobs/../../wp-config' ) && ! preg_match( '@^' . $job_route . '$@', '/jobs/tick' ), 'El patrón de un trabajo acepta algo que no es un UUID.' );

	// Sin sesión: 401; con una cuenta sin la capability: 403. Nada llega al motor.
	$GLOBALS['digi']['caps'] = array();
	$uuid = '0b6f6b8e-3c1d-4f7a-9a1e-2f4d5c6b7a8e';
	$every = array( array( 'GET', '/status' ), array( 'GET', '/backups' ), array( 'POST', '/backups' ), array( 'GET', '/backups/x.zip/verify' ), array( 'POST', '/backups/x.zip/restore', array( 'confirm' => 'RESTAURAR' ) ), array( 'POST', '/backups/x.zip/fetch' ), array( 'GET', '/destinations' ), array( 'POST', '/destinations/s3/test' ), array( 'GET', '/automations' ), array( 'POST', '/automations/monthly-full/run' ), array( 'GET', '/jobs' ), array( 'POST', '/jobs/tick' ), array( 'GET', '/jobs/' . $uuid ), array( 'POST', '/jobs/' . $uuid . '/cancel' ) );
	foreach ( $every as $call ) {
		list( $code ) = digi_rest( $call[0], $ns . $call[1], $call[2] ?? array(), false );
		digi_assert( 401 === $code, 'Una petición sin sesión no recibió 401 en ' . $call[1] );
	}
	$GLOBALS['digi']['caps'] = array( 'edit_posts' => true, 'upload_files' => true );
	foreach ( $every as $call ) {
		list( $code ) = digi_rest( $call[0], $ns . $call[1], $call[2] ?? array() );
		digi_assert( 403 === $code, 'Una cuenta sin capability recibió ' . $code . ' en ' . $call[0] . ' ' . $call[1] );
	}
	digi_assert( ! is_dir( $root . '/private' ) || ( array() === digi_stored( $root . '/private' ) && ! glob( $root . '/private/jobs/*' ) ), 'Una petición sin permiso creó un respaldo o un trabajo.' );
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );

	// Estado, creación en cola (202), cola, historial, verificación y restauración por la API.
	list( $code, $status ) = digi_rest( 'GET', $ns . '/status' );
	digi_assert( 200 === $code && true === $status['ready'] && 'installation' === $status['scope'] && 0 === $status['jobs'], 'El estado REST no es el esperado.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups', array( 'network' => true ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_scope' === $error['code'], 'La API aceptó el alcance de red en una instalación individual.' );
	list( $code, $job ) = digi_rest( 'POST', $ns . '/backups', array( 'destination' => 'local' ) );
	digi_assert( 202 === $code && 'queued' === $job['status'] && 'local' === $job['destination'] && Digitalisimo_Backups_Job_Store::valid_id( $job['id'] ), 'POST /backups no encoló el respaldo: ' . json_encode( $job ) );
	digi_assert( 'rest' === Digitalisimo_Backups_Jobs::get( $job['id'] )['spec']['origin']['via'], 'El trabajo no anota que llegó por REST.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups' );
	digi_assert( 409 === $code && 'digitalisimo_backups_duplicate' === $error['code'] && $job['id'] === $error['data']['job'], 'Un respaldo manual se encoló dos veces por la API.' );
	list( $code, $jobs ) = digi_rest( 'GET', $ns . '/jobs' );
	digi_assert( 200 === $code && 1 === count( $jobs ) && $job['id'] === $jobs[0]['id'], 'GET /jobs no listó el trabajo.' );
	list( $code, $error ) = digi_rest( 'GET', $ns . '/jobs/' . $uuid );
	digi_assert( 404 === $code && 'digitalisimo_backups_job_missing' === $error['code'], 'Un trabajo inexistente no devolvió 404.' );
	$done = $drive( $job['id'] );
	digi_assert( 'success' === $done['status'] && '' !== $done['file'], 'El trabajo no terminó por la API: ' . json_encode( $done ) );
	list( $code, $list ) = digi_rest( 'GET', $ns . '/backups' );
	digi_assert( 200 === $code && 1 === count( $list ) && $done['file'] === $list[0]['file'] && $list[0]['restorable'], 'GET /backups no devolvió el respaldo creado.' );
	$backup = $list[0];
	list( $code, $report ) = digi_rest( 'GET', $ns . '/backups/' . $backup['file'] . '/verify' );
	digi_assert( 200 === $code && true === $report['verified'] && $backup['sha256'] === $report['sha256'] && 'provenance' === $report['trust'], 'La verificación REST falló.' );
	list( $code, $report ) = digi_rest( 'POST', $ns . '/backups/' . $backup['file'] . '/verify' );
	digi_assert( 200 === $code && true === $report['verified'], 'La verificación por POST falló.' );
	list( $code ) = digi_rest( 'GET', $ns . '/backups/..%2Fwp-config.php/verify' );
	digi_assert( 404 === $code, 'La API aceptó un nombre de archivo con ruta.' );
	list( $code, $error ) = digi_rest( 'GET', $ns . '/backups/no-existe.zip/verify' );
	digi_assert( 404 === $code && 'digitalisimo_backups_not_found' === $error['code'], 'Un respaldo inexistente no devolvió 404.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $backup['file'] . '/restore' );
	digi_assert( 400 === $code && 'rest_missing_callback_param' === $error['code'], 'La restauración no exige el parámetro confirm.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $backup['file'] . '/restore', array( 'confirm' => 'sí' ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_confirm' === $error['code'], 'Se restauró sin escribir RESTAURAR.' );
	// «Conservar las direcciones del respaldo» (E1): el mismo parámetro en restaurar y verificar, con sus dos valores.
	foreach ( array( '/restore', '/verify' ) as $route ) {
		$arg = $GLOBALS['digi']['routes'][ $ns . '/backups/' . Digitalisimo_Backups_Rest::FILE . $route ][0]['args']['addresses'] ?? array();
		digi_assert( array( 'current', 'backup' ) === ( $arg['enum'] ?? null ) && 'current' === ( $arg['default'] ?? null ), 'La ruta ' . $route . ' no declara addresses.' );
	}
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $backup['file'] . '/restore', array( 'confirm' => 'RESTAURAR', 'addresses' => 'otra' ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_addresses' === $error['code'], 'Un addresses inválido no se rechazó antes de restaurar.' );
	list( $code, $report ) = digi_rest( 'GET', $ns . '/backups/' . $backup['file'] . '/verify', array( 'addresses' => 'backup' ) );
	digi_assert( 200 === $code && is_array( $report['relocation'] ) && 'backup' === $report['relocation']['addresses'] && false === $report['relocation']['needed'], 'verify no transmitió addresses o anunció cambios en el mismo sitio: ' . json_encode( $report['relocation'] ?? null ) );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado\n" );
	list( $code, $restored ) = digi_rest( 'POST', $ns . '/backups/' . $backup['file'] . '/restore', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 200 === $code && true === $restored['restored'] && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'POST restore no restauró: ' . json_encode( $restored ) );

	// Ocupado: con el bloqueo del almacén tomado, restaurar responde 409 y la cola «busy»; encolar sigue (202).
	$lock = digi_hold_lock();
	try {
		list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $backup['file'] . '/restore', array( 'confirm' => 'RESTAURAR' ) );
		digi_assert( 409 === $code && 'digitalisimo_backups_busy' === $error['code'], 'Dos operaciones se solaparon por la API.' );
		list( $code, $queued ) = digi_rest( 'POST', $ns . '/backups' );
		digi_assert( 202 === $code && 'queued' === $queued['status'], 'Encolar falló con otra operación en curso.' );
		list( $code, $tick ) = digi_rest( 'POST', $ns . '/jobs/tick' );
		digi_assert( 200 === $code && 'busy' === $tick['result'] && 'queued' === $tick['jobs'][0]['status'], 'La cola avanzó con el almacén tomado.' );
	} finally {
		digi_release_lock( $lock );
	}
	// Un trabajo pendiente impide restaurar; se cancela por la API.
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $backup['file'] . '/restore', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 409 === $code && 'digitalisimo_backups_busy' === $error['code'] && false !== strpos( $error['message'], 'pendiente o en curso' ), 'Se restauró con un trabajo pendiente.' );
	list( $code, $cancelled ) = digi_rest( 'POST', $ns . '/jobs/' . $queued['id'] . '/cancel' );
	digi_assert( 200 === $code && 'cancelled' === $cancelled['result'] && 'cancelled' === $cancelled['job']['status'], 'POST /jobs/<id>/cancel no canceló: ' . json_encode( $cancelled ) );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/jobs/' . $queued['id'] . '/cancel' );
	digi_assert( 409 === $code && 'digitalisimo_backups_job_terminal' === $error['code'], 'Se canceló dos veces un trabajo.' );
	list( $code ) = digi_rest( 'POST', $ns . '/jobs/' . $uuid . '/cancel' );
	digi_assert( 404 === $code, 'Se canceló un trabajo inexistente.' );

	// Automatizaciones.
	list( $code, $automations ) = digi_rest( 'GET', $ns . '/automations' );
	digi_assert( 200 === $code && 'monthly-full' === $automations[0]['id'], 'GET /automations no listó la automatización.' );
	list( $code, $ran ) = digi_rest( 'POST', $ns . '/automations/monthly-full/run' );
	digi_assert( 202 === $code && 'monthly-full' === $ran['policy'] && 'queued' === $ran['status'], 'POST /automations/<id>/run no encoló la automatización.' );
	digi_assert( 'success' === $drive( $ran['id'] )['status'] && 'monthly-full' === get_option( Digitalisimo_Backups::HISTORY )[0]['policy'], 'La automatización no creó su respaldo.' );
	list( $code ) = digi_rest( 'POST', $ns . '/automations/no-existe/run' );
	digi_assert( 404 === $code, 'Una automatización inexistente no devolvió 404.' );

	// Ninguna respuesta incluye rutas del servidor.
	foreach ( array( '/status', '/backups', '/automations', '/jobs', '/backups/' . $backup['file'] . '/verify' ) as $route ) {
		list( , $data ) = digi_rest( 'GET', $ns . $route );
		digi_assert( false === strpos( json_encode( $data ), $root ), 'GET ' . $route . ' incluyó rutas del servidor.' );
	}

	// Seguridad: un respaldo cifrado con la frase del sitio, y el mismo archivo en «otro servidor» (sin llavero).
	$passphrase = 'frase "de la API" con <signos> & barra \\ larga';
	$key = Digitalisimo_Backups_Keys::set_passphrase( $passphrase )['key_id'];
	update_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => true, 'require_signed' => false ) );
	list( $code, $status ) = digi_rest( 'GET', $ns . '/status' );
	digi_assert( 200 === $code && true === $status['encrypt'] && 'passphrase' === $status['key_source'] && true === $status['ready'], 'El estado no muestra el cifrado con frase.' );
	list( $code, $job ) = digi_rest( 'POST', $ns . '/backups' );
	digi_assert( 202 === $code && true === $job['encrypt'], 'El trabajo encolado no fija el cifrado.' );
	$sealed = $drive( $job['id'] );
	digi_assert( 'success' === $sealed['status'] && '.dgbk' === substr( $sealed['file'], -5 ), 'El respaldo cifrado no terminó como .dgbk: ' . json_encode( $sealed ) );
	list( , $list ) = digi_rest( 'GET', $ns . '/backups' );
	digi_assert( $sealed['file'] === $list[0]['file'] && 'dgbk' === $list[0]['container'] && true === $list[0]['encrypted'] && Digitalisimo_Backups_Keys::short( $key ) === $list[0]['key'] && false === $list[0]['needs_secret'], 'El historial no describe el respaldo cifrado: ' . json_encode( $list[0] ) );
	list( $code, $report ) = digi_rest( 'GET', $ns . '/backups/' . $sealed['file'] . '/verify' );
	digi_assert( 200 === $code && 'dgbk' === $report['container'] && 'signed' === $report['trust'] && Digitalisimo_Backups_Keys::short( $key ) === $report['key'], 'La verificación de un .dgbk con la clave del sitio falló: ' . json_encode( $report ) );
	$keyring = get_option( Digitalisimo_Backups_Keys::KEYRING );
	delete_option( Digitalisimo_Backups_Keys::KEYRING );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups' );
	digi_assert( 422 === $code && false !== strpos( $error['message'], 'no hay clave' ), 'Con el cifrado activo y sin clave se encoló un respaldo en claro.' );
	list( , $list ) = digi_rest( 'GET', $ns . '/backups' );
	digi_assert( true === $list[0]['needs_secret'], 'El historial no avisa de que falta la clave.' );
	list( $code, $error ) = digi_rest( 'GET', $ns . '/backups/' . $sealed['file'] . '/verify' );
	digi_assert( 400 === $code && 'digitalisimo_backups_secret_required' === $error['code'] && false !== strpos( $error['message'], 'cifrado con la clave ' . Digitalisimo_Backups_Keys::short( $key ) ) && false !== strpos( $error['message'], 'en el cuerpo de la petición' ), 'Un .dgbk sin clave no dio un error claro: ' . json_encode( $error ) );
	list( $code, $error ) = digi_rest( 'GET', $ns . '/backups/' . $sealed['file'] . '/verify', array(), true, array( 'secret' => $passphrase ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_secret_in_url' === $error['code'], 'La API aceptó la frase en la URL.' );
	list( $code, $report ) = digi_rest( 'POST', $ns . '/backups/' . $sealed['file'] . '/verify', array( 'secret' => $passphrase ) );
	digi_assert( 200 === $code && 'signed' === $report['trust'] && false === strpos( json_encode( $report ), 'frase' ), 'La verificación con la frase en el cuerpo falló: ' . json_encode( $report ) );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado antes del .dgbk\n" );
	$before = digi_stored( $root . '/private' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $sealed['file'] . '/restore', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_secret_required' === $error['code'], 'Se intentó restaurar un .dgbk sin su frase.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $sealed['file'] . '/restore', array( 'confirm' => 'RESTAURAR' ), true, array( 'secret' => $passphrase ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_secret_in_url' === $error['code'], 'La API restauró con la frase en la URL.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $sealed['file'] . '/restore', array( 'confirm' => 'RESTAURAR', 'secret' => 'otra frase equivocada pero larga' ) );
	digi_assert( 422 === $code && 'digitalisimo_backups_invalid' === $error['code'] && false !== strpos( $error['message'], 'no corresponde' ) && false === strpos( json_encode( $error ), 'equivocada' ), 'Una frase equivocada no se rechazó sin repetirla: ' . json_encode( $error ) );
	digi_assert( $before === digi_stored( $root . '/private' ) && "<?php // cambiado antes del .dgbk\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'Un rechazo creó la copia previa o tocó el sitio.' );
	list( $code, $restored ) = digi_rest( 'POST', $ns . '/backups/' . $sealed['file'] . '/restore', array( 'confirm' => 'RESTAURAR', 'secret' => $passphrase, 'adopt_secret' => true ) );
	digi_assert( 200 === $code && true === $restored['restored'] && 'signed' === $restored['trust'] && true === $restored['adopted'] && Digitalisimo_Backups_Keys::short( $key ) === $restored['key'] && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'El .dgbk no se restauró con la frase en el cuerpo: ' . json_encode( $restored ) );
	digi_assert( false === strpos( json_encode( $restored ), 'frase "de' ) && false === strpos( json_encode( $restored ), 'signos' ), 'La respuesta de la restauración devolvió la frase.' );
	digi_assert( Digitalisimo_Backups_Keys::can_open( $key ) && $key === Digitalisimo_Backups_Keys::keyring()['active'], 'adopt_secret no guardó la frase en el llavero.' );
	foreach ( array( '/status', '/backups', '/jobs', '/automations' ) as $route ) {
		list( , $data ) = digi_rest( 'GET', $ns . $route );
		digi_assert( false === strpos( json_encode( $data ), 'signos' ) && false === strpos( json_encode( $data ), '"wrap"' ) && false === strpos( json_encode( $data ), $key ), 'GET ' . $route . ' expuso la frase, su envoltura o la huella completa.' );
	}
	update_option( Digitalisimo_Backups_Keys::KEYRING, $keyring );
	delete_option( Digitalisimo_Backups_Security::OPTION );

	// Red: administrador de sitio 403; red sin network=true 400; sitio principal con network=true 202; subsitio 400.
	$GLOBALS['digi']['multisite'] = true;
	list( $code ) = digi_rest( 'GET', $ns . '/backups' );
	digi_assert( 403 === $code, 'En una red, un administrador de sitio consultó los respaldos.' );
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups' );
	digi_assert( 400 === $code && 'digitalisimo_backups_scope' === $error['code'], 'En una red, la API creó un respaldo sin network=true.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/automations/monthly-full/run' );
	digi_assert( 400 === $code && 'digitalisimo_backups_scope' === $error['code'], 'En una red, una automatización corrió sin network=true.' );
	list( $code, $job ) = digi_rest( 'POST', $ns . '/backups', array( 'network' => true ) );
	digi_assert( 202 === $code && 'network' === Digitalisimo_Backups_Jobs::get( $job['id'] )['spec']['scope'], 'La red no encoló su respaldo por la API.' );
	$network = $drive( $job['id'] );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $network['file'] . '/restore', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_scope' === $error['code'], 'En una red, la API restauró sin network=true.' );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado en la red\n" );
	list( $code, $restored ) = digi_rest( 'POST', $ns . '/backups/' . $network['file'] . '/restore', array( 'confirm' => 'RESTAURAR', 'network' => true ) );
	digi_assert( 200 === $code && true === $restored['restored'] && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'La red no se restauró por la API con network=true: ' . json_encode( $restored ) );
	list( $code, $ran ) = digi_rest( 'POST', $ns . '/automations/monthly-full/run', array( 'network' => true ) );
	digi_assert( 202 === $code && 'success' === $drive( $ran['id'] )['status'] && 'network' === get_option( Digitalisimo_Backups::HISTORY )[0]['scope'], 'En una red, la automatización no corrió con network=true: ' . json_encode( $ran ) );
	// Otra red de la misma instalación: su superadministrador no gestiona los respaldos de todas.
	$GLOBALS['digi']['main_network'] = false;
	foreach ( array( array( 'GET', '/backups', array() ), array( 'GET', '/jobs', array() ), array( 'POST', '/jobs/tick', array() ), array( 'POST', '/backups/' . $network['file'] . '/restore', array( 'confirm' => 'RESTAURAR', 'network' => true ) ) ) as $call ) {
		list( $code, $error ) = digi_rest( $call[0], $ns . $call[1], $call[2] );
		digi_assert( 400 === $code && 'digitalisimo_backups_main_site' === $error['code'], 'Una red secundaria operó en ' . $call[1] );
	}
	$GLOBALS['digi']['main_network'] = true;
	$GLOBALS['digi']['main'] = false;
	list( $code, $status ) = digi_rest( 'GET', $ns . '/status' );
	digi_assert( 200 === $code && false === $status['main_site'] && false === $status['ready'], 'El estado de un subsitio no lo identifica.' );
	foreach ( array( array( 'GET', '/backups', array() ), array( 'POST', '/backups', array( 'network' => true ) ), array( 'POST', '/backups/' . $network['file'] . '/restore', array( 'confirm' => 'RESTAURAR', 'network' => true ) ), array( 'GET', '/jobs', array() ), array( 'GET', '/jobs/' . $ran['id'], array() ), array( 'POST', '/jobs/' . $ran['id'] . '/cancel', array() ), array( 'POST', '/jobs/tick', array() ) ) as $call ) {
		list( $code, $error ) = digi_rest( $call[0], $ns . $call[1], $call[2] );
		digi_assert( 400 === $code && 'digitalisimo_backups_main_site' === $error['code'], 'Un subsitio operó en ' . $call[1] );
	}
} finally {
	digi_rmtree( $root );
}
echo "DIGITALÍSIMO Backups: API REST con capability, 401/403, contexto de red, cola de trabajos (202, tick, cancelar) y frase sólo en el cuerpo.\n";
