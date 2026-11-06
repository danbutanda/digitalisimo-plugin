<?php
/**
 * API REST `digitalisimo-backups/v1`: cada ruta pide la capability de la pantalla antes de llamar al caso de uso
 * compartido, y en una red exige el sitio principal y `network=true` para lo que abarca todos los sitios.
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
$ns = 'digitalisimo-backups/v1';

try {
	// Registro: sólo en rest_api_init, con las rutas declaradas y un permiso real en cada una.
	Digitalisimo_Backups_Rest::register();
	digi_assert( array() === $GLOBALS['digi']['routes'] && 1 === count( $GLOBALS['digi']['actions']['rest_api_init'] ?? array() ), 'Las rutas deben registrarse en rest_api_init.' );
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	$expected = array( '/status' => 'GET', '/backups' => 'GET,POST', '/backups/' . Digitalisimo_Backups_Rest::FILE . '/verify' => 'GET', '/backups/' . Digitalisimo_Backups_Rest::FILE . '/restore' => 'POST', '/automations' => 'GET', '/automations/(?P<id>[a-z0-9_-]+)/run' => 'POST' );
	digi_assert( array_map( function( $route ) use ( $ns ) { return $ns . $route; }, array_keys( $expected ) ) === array_keys( $GLOBALS['digi']['routes'] ), 'Las rutas registradas no son las documentadas: ' . implode( ', ', array_keys( $GLOBALS['digi']['routes'] ) ) );
	foreach ( $expected as $route => $methods ) {
		$handlers = $GLOBALS['digi']['routes'][ $ns . $route ];
		digi_assert( $methods === implode( ',', array_column( $handlers, 'methods' ) ), 'Métodos inesperados en ' . $route );
		foreach ( $handlers as $handler ) digi_assert( array( 'Digitalisimo_Backups_Rest', 'allowed' ) === $handler['permission_callback'], 'La ruta ' . $route . ' no usa la capability de la pantalla.' );
	}

	// Sin sesión: 401; con una cuenta sin la capability: 403. Nada llega al motor.
	$GLOBALS['digi']['caps'] = array();
	list( $code ) = digi_rest( 'POST', $ns . '/backups', array(), false );
	digi_assert( 401 === $code, 'Una petición sin sesión no recibió 401.' );
	$GLOBALS['digi']['caps'] = array( 'edit_posts' => true, 'upload_files' => true );
	foreach ( array( array( 'GET', '/status' ), array( 'GET', '/backups' ), array( 'POST', '/backups' ), array( 'GET', '/automations' ), array( 'POST', '/automations/monthly-full/run' ) ) as $call ) {
		list( $code ) = digi_rest( $call[0], $ns . $call[1] );
		digi_assert( 403 === $code, 'Una cuenta sin capability recibió ' . $code . ' en ' . implode( ' ', $call ) );
	}
	digi_assert( ! is_dir( $root . '/private' ) || array() === digi_stored( $root . '/private' ), 'Una petición sin permiso creó un respaldo.' );
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );

	// Estado, creación (201), historial, verificación y restauración por la API.
	list( $code, $status ) = digi_rest( 'GET', $ns . '/status' );
	digi_assert( 200 === $code && true === $status['ready'] && 'installation' === $status['scope'], 'El estado REST no es el esperado.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups', array( 'network' => true ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_scope' === $error['code'], 'La API aceptó el alcance de red en una instalación individual.' );
	list( $code, $backup ) = digi_rest( 'POST', $ns . '/backups', array( 'destination' => 'local' ) );
	digi_assert( 201 === $code && $backup['available'] && $backup['restorable'] && 'local' === $backup['destination'], 'POST /backups no creó el respaldo: ' . json_encode( $backup ) );
	list( $code, $list ) = digi_rest( 'GET', $ns . '/backups' );
	digi_assert( 200 === $code && 1 === count( $list ) && $backup['file'] === $list[0]['file'], 'GET /backups no devolvió el respaldo creado.' );
	list( $code, $report ) = digi_rest( 'GET', $ns . '/backups/' . $backup['file'] . '/verify' );
	digi_assert( 200 === $code && true === $report['verified'] && $backup['sha256'] === $report['sha256'], 'La verificación REST falló.' );
	list( $code ) = digi_rest( 'GET', $ns . '/backups/..%2Fwp-config.php/verify' );
	digi_assert( 404 === $code, 'La API aceptó un nombre de archivo con ruta.' );
	list( $code, $error ) = digi_rest( 'GET', $ns . '/backups/no-existe.zip/verify' );
	digi_assert( 404 === $code && 'digitalisimo_backups_not_found' === $error['code'], 'Un respaldo inexistente no devolvió 404.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $backup['file'] . '/restore' );
	digi_assert( 400 === $code && 'rest_missing_callback_param' === $error['code'], 'La restauración no exige el parámetro confirm.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $backup['file'] . '/restore', array( 'confirm' => 'sí' ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_confirm' === $error['code'], 'Se restauró sin escribir RESTAURAR.' );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado\n" );
	list( $code, $restored ) = digi_rest( 'POST', $ns . '/backups/' . $backup['file'] . '/restore', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 200 === $code && true === $restored['restored'] && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'POST restore no restauró: ' . json_encode( $restored ) );

	// Ocupado: 409 con el bloqueo del almacén tomado por otra petición.
	$lock = digi_hold_lock();
	try {
		list( $code, $error ) = digi_rest( 'POST', $ns . '/backups' );
		digi_assert( 409 === $code && 'digitalisimo_backups_busy' === $error['code'], 'Dos operaciones se solaparon por la API.' );
	} finally {
		digi_release_lock( $lock );
	}

	// Automatizaciones.
	list( $code, $automations ) = digi_rest( 'GET', $ns . '/automations' );
	digi_assert( 200 === $code && 'monthly-full' === $automations[0]['id'], 'GET /automations no listó la automatización.' );
	list( $code, $ran ) = digi_rest( 'POST', $ns . '/automations/monthly-full/run' );
	digi_assert( 201 === $code && 'monthly-full' === $ran['policy'], 'POST /automations/<id>/run no creó el respaldo.' );
	list( $code ) = digi_rest( 'POST', $ns . '/automations/no-existe/run' );
	digi_assert( 404 === $code, 'Una automatización inexistente no devolvió 404.' );

	// Ninguna respuesta incluye rutas del servidor.
	foreach ( array( '/status', '/backups', '/automations', '/backups/' . $backup['file'] . '/verify' ) as $route ) {
		list( , $data ) = digi_rest( 'GET', $ns . $route );
		digi_assert( false === strpos( json_encode( $data ), $root ), 'GET ' . $route . ' incluyó rutas del servidor.' );
	}

	// Red: administrador de sitio 403; red sin network=true 400; sitio principal con network=true 201; subsitio 400.
	$GLOBALS['digi']['multisite'] = true;
	list( $code ) = digi_rest( 'GET', $ns . '/backups' );
	digi_assert( 403 === $code, 'En una red, un administrador de sitio consultó los respaldos.' );
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups' );
	digi_assert( 400 === $code && 'digitalisimo_backups_scope' === $error['code'], 'En una red, la API creó un respaldo sin network=true.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/automations/monthly-full/run' );
	digi_assert( 400 === $code && 'digitalisimo_backups_scope' === $error['code'], 'En una red, una automatización corrió sin network=true.' );
	list( $code, $network ) = digi_rest( 'POST', $ns . '/backups', array( 'network' => true ) );
	digi_assert( 201 === $code && 'network' === $network['scope'], 'La red no creó su respaldo por la API.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $network['file'] . '/restore', array( 'confirm' => 'RESTAURAR' ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_scope' === $error['code'], 'En una red, la API restauró sin network=true.' );
	file_put_contents( WP_PLUGIN_DIR . '/example.php', "<?php // cambiado en la red\n" );
	list( $code, $restored ) = digi_rest( 'POST', $ns . '/backups/' . $network['file'] . '/restore', array( 'confirm' => 'RESTAURAR', 'network' => true ) );
	digi_assert( 200 === $code && true === $restored['restored'] && "<?php // plugin de ejemplo\n" === file_get_contents( WP_PLUGIN_DIR . '/example.php' ), 'La red no se restauró por la API con network=true: ' . json_encode( $restored ) );
	list( $code, $ran ) = digi_rest( 'POST', $ns . '/automations/monthly-full/run', array( 'network' => true ) );
	digi_assert( 201 === $code && 'network' === $ran['scope'], 'En una red, la automatización no corrió con network=true: ' . json_encode( $ran ) );
	// Otra red de la misma instalación: su superadministrador no gestiona los respaldos de todas.
	$GLOBALS['digi']['main_network'] = false;
	list( $code, $error ) = digi_rest( 'GET', $ns . '/backups' );
	digi_assert( 400 === $code && 'digitalisimo_backups_main_site' === $error['code'], 'Una red secundaria consultó los respaldos de toda la instalación.' );
	list( $code, $error ) = digi_rest( 'POST', $ns . '/backups/' . $network['file'] . '/restore', array( 'confirm' => 'RESTAURAR', 'network' => true ) );
	digi_assert( 400 === $code && 'digitalisimo_backups_main_site' === $error['code'], 'Una red secundaria restauró toda la instalación.' );
	$GLOBALS['digi']['main_network'] = true;
	$GLOBALS['digi']['main'] = false;
	list( $code, $status ) = digi_rest( 'GET', $ns . '/status' );
	digi_assert( 200 === $code && false === $status['main_site'] && false === $status['ready'], 'El estado de un subsitio no lo identifica.' );
	foreach ( array( array( 'GET', '/backups', array() ), array( 'POST', '/backups', array( 'network' => true ) ), array( 'POST', '/backups/' . $network['file'] . '/restore', array( 'confirm' => 'RESTAURAR', 'network' => true ) ) ) as $call ) {
		list( $code, $error ) = digi_rest( $call[0], $ns . $call[1], $call[2] );
		digi_assert( 400 === $code && 'digitalisimo_backups_main_site' === $error['code'], 'Un subsitio operó en ' . $call[1] );
	}
} finally {
	digi_rmtree( $root );
}
echo "DIGITALÍSIMO Backups: API REST con capability, 401/403, contexto de red y los mismos casos de uso que la pantalla.\n";
