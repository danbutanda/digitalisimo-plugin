<?php
/**
 * «Conservar» por cadenas en REST y WP-CLI (docs/BACKUPS.md, «Incrementales» → «Pantalla, REST y WP-CLI»), con el
 * mismo caso de uso que los formularios de la pantalla (service_save_automation()):
 *   S1 REST: rutas `POST /automations` y `POST /automations/<id>` con el permiso de la pantalla (401/403 sin él);
 *      crear una incremental con `retain_unit` = chains (201) devuelve la unidad y la estimación; cambiar sólo
 *      `retain` conserva la unidad; el tope de «Conservar» se aplica y se avisa (`notice`); una unidad, un modo, una
 *      frecuencia o un destino que no existen dan 400 sin guardar nada; una automatización que no existe, 404;
 *      `GET /automations` lista `retain_unit` y `retain_estimate`; una automatización anterior sin unidad, `points`;
 *      en una red, cambiarla exige `network=true`;
 *   S2 WP-CLI: `automation add` con `--retain-unit=chains`, `automation update` (lo que no se pasa se queda; sin
 *      identificador falla sin crear otra) y `automation list` con las columnas `retain_unit` y `estimate`;
 *   S3 (revisión): crear o cambiar por REST y WP-CLI programa la automatización en WP-Cron; una parcial anterior no se
 *      convierte en completa; WP-CLI rechaza como REST un número que no es un entero de su rango; pedir «cadenas» en
 *      «Completo cada vez» se avisa; no se crean más de 50 automatizaciones.
 *
 * Debe fallar con el código anterior (sin esas rutas ni esos subcomandos, y sin la unidad).
 */
$root = sys_get_temp_dir() . '/digitalisimo-retain-chains-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-coverage/cli.php';
require __DIR__ . '/backups-coverage/env.php';
digi_make_wordpress( $root . '/public' );
if ( ! function_exists( 'absint' ) ) { function absint( $value ) { return abs( (int) $value ); } }
$GLOBALS['scheduled'] = array();
if ( ! function_exists( 'wp_schedule_event' ) ) { function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) { $GLOBALS['scheduled'][] = array( $hook, $args, $recurrence ); return true; } }
if ( ! function_exists( 'wp_unschedule_event' ) ) { function wp_unschedule_event( $timestamp, $hook, $args = array() ) { return true; } }
$ns = 'digitalisimo-backups/v1';
$B = 'Digitalisimo_Backups';
$cases = 0;
$failures = 0;
function rcs_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . "\n";
	}
}
function rcs_policy( $id ) { foreach ( Digitalisimo_Backups::policies() as $policy ) if ( $id === ( $policy['id'] ?? '' ) ) return $policy; return null; }

try {
	Digitalisimo_Backups_Rest::register();
	call_user_func( $GLOBALS['digi']['actions']['rest_api_init'][0] );
	Digitalisimo_Backups_Cli::register();

	rcs_case( 'S1 REST: las rutas para crear y cambiar automatizaciones piden el permiso de la pantalla', function() use ( $ns ) {
		$routes = $GLOBALS['digi']['routes'];
		digi_assert( 'GET,POST' === implode( ',', array_column( $routes[ $ns . '/automations' ] ?? array(), 'methods' ) ), 'GET y POST /automations no están registradas.' );
		digi_assert( 'POST' === implode( ',', array_column( $routes[ $ns . '/automations/(?P<id>[a-z0-9_-]+)' ] ?? array(), 'methods' ) ), 'POST /automations/<id> no está registrada.' );
		foreach ( array( '/automations', '/automations/(?P<id>[a-z0-9_-]+)' ) as $route ) foreach ( $routes[ $ns . $route ] as $handler ) digi_assert( array( 'Digitalisimo_Backups_Rest', 'allowed' ) === $handler['permission_callback'], 'La ruta ' . $route . ' no usa el permiso de la pantalla.' );
		$args = $routes[ $ns . '/automations' ][1]['args'];
		digi_assert( array( 'points', 'chains' ) === ( $args['retain_unit']['enum'] ?? null ) && ! array_key_exists( 'default', $args['retain_unit'] ) && ! array_key_exists( 'default', $args['retain'] ), 'retain_unit no es una enumeración sin valor por defecto (lo que no se envía se queda como está).' );
		$before = get_option( Digitalisimo_Backups::POLICIES );
		$GLOBALS['digi']['caps'] = array();
		list( $code ) = digi_rest( 'POST', $ns . '/automations', array( 'mode' => 'incremental' ), false );
		digi_assert( 401 === $code, 'Sin sesión no hubo 401: ' . $code );
		$GLOBALS['digi']['caps'] = array( 'edit_posts' => true );
		list( $code ) = digi_rest( 'POST', $ns . '/automations/monthly-full', array( 'retain' => 9 ) );
		digi_assert( 403 === $code, 'Sin capability no hubo 403: ' . $code );
		$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
		digi_assert( $before === get_option( Digitalisimo_Backups::POLICIES ), 'Una petición sin permiso cambió las automatizaciones.' );
	} );

	$created = array();
	rcs_case( 'S1 REST: crear «Conservar 2 cadenas» y cambiarla sin perder la unidad; el tope se aplica y se avisa', function() use ( $ns, &$created ) {
		list( $code, $row ) = digi_rest( 'POST', $ns . '/automations', array( 'schedule' => 'daily', 'mode' => 'incremental', 'full_every' => 6, 'full_days' => 7, 'retain' => 2, 'retain_unit' => 'chains', 'destination' => 'local' ) );
		digi_assert( 201 === $code && 'chains' === $row['retain_unit'] && 2 === $row['retain'] && 'incremental' === $row['mode'] && '' === $row['notice'], 'POST /automations no creó «Conservar 2 cadenas»: ' . $code . ' ' . json_encode( $row ) );
		digi_assert( array( 8, 14, 2, 2, 1 ) === array( $row['retain_estimate']['min'], $row['retain_estimate']['max'], $row['retain_estimate']['chains_min'], $row['retain_estimate']['chains_max'], $row['retain_estimate']['previous_min'] ) && 'Guardarás entre 8 y 14 respaldos en 2 cadenas. Siempre queda al menos una cadena anterior completa.' === $row['retain_estimate']['text'], 'La respuesta no trae la estimación: ' . json_encode( $row['retain_estimate'] ) );
		$created = $row;
		$stored = rcs_policy( $row['id'] );
		digi_assert( $stored && 'chains' === $stored['retain_unit'] && true === $stored['enabled'], 'La automatización no se guardó: ' . json_encode( $stored ) );
		// Sólo «Conservar»: la unidad, el modo y la frecuencia se quedan.
		list( $code, $row ) = digi_rest( 'POST', $ns . '/automations/' . $created['id'], array( 'retain' => 3 ) );
		digi_assert( 200 === $code && 3 === $row['retain'] && 'chains' === $row['retain_unit'] && 'incremental' === $row['mode'] && 'daily' === $row['schedule'], 'Cambiar sólo «Conservar» perdió algo: ' . json_encode( $row ) );
		// Más cadenas de las que caben: se acota a 14 (con un completo cada 6) y se avisa.
		list( $code, $row ) = digi_rest( 'POST', $ns . '/automations/' . $created['id'], array( 'retain' => 40 ) );
		digi_assert( 200 === $code && 14 === $row['retain'] && false !== strpos( $row['notice'], '«Conservar» quedó en 14 cadenas' ) && 98 === $row['retain_estimate']['max'], 'El tope de cadenas no se aplicó o no se avisó: ' . json_encode( $row ) );
		// A respaldos: el tope vuelve a ser 100 − M.
		list( $code, $row ) = digi_rest( 'POST', $ns . '/automations/' . $created['id'], array( 'retain_unit' => 'points', 'retain' => 4 ) );
		digi_assert( 200 === $code && 'points' === $row['retain_unit'] && 'Guardarás entre 4 y 10 respaldos en 1 o 2 cadenas. Hay días con una sola cadena: si su base se daña, no hay otra copia.' === $row['retain_estimate']['text'], 'Pasar a respaldos no cambió la estimación: ' . json_encode( $row ) );
		list( $code, $row ) = digi_rest( 'POST', $ns . '/automations/' . $created['id'], array( 'retain_unit' => 'chains', 'retain' => 2 ) );
		digi_assert( 200 === $code && 'chains' === $row['retain_unit'], 'No volvió a cadenas.' );
	} );

	rcs_case( 'S1 REST: valores que no existen dan 400 sin guardar nada; una automatización que no existe, 404', function() use ( $ns, &$created ) {
		$before = get_option( Digitalisimo_Backups::POLICIES );
		foreach ( array( array( 'retain_unit' => 'semanas' ), array( 'mode' => 'parcial' ), array( 'schedule' => 'cada-rato' ), array( 'destination' => 'ftp-que-no-existe' ), array( 'retain' => 'muchos' ), array( 'time' => '25:00' ) ) as $bad ) {
			list( $code, $error ) = digi_rest( 'POST', $ns . '/automations/' . $created['id'], $bad );
			digi_assert( 400 === $code && 0 === strpos( (string) $error['code'], 'digitalisimo_backups_automation' ), 'Se aceptó ' . json_encode( $bad ) . ': ' . $code . ' ' . json_encode( $error ) );
			list( $code ) = digi_rest( 'POST', $ns . '/automations', $bad );
			digi_assert( 400 === $code, 'Se creó una automatización con ' . json_encode( $bad ) );
		}
		list( $code, $error ) = digi_rest( 'POST', $ns . '/automations/no-existe', array( 'retain' => 2 ) );
		digi_assert( 404 === $code && 'digitalisimo_backups_automation' === $error['code'], 'Una automatización que no existe no dio 404: ' . $code );
		digi_assert( $before === get_option( Digitalisimo_Backups::POLICIES ), 'Un rechazo cambió las automatizaciones.' );
	} );

	rcs_case( 'S1 REST: GET /automations lista la unidad y la estimación; una anterior sin unidad cuenta respaldos', function() use ( $ns, &$created ) {
		$policies = get_option( Digitalisimo_Backups::POLICIES, Digitalisimo_Backups::default_policies() );
		$policies[] = array( 'id' => 'vieja', 'label' => '', 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 4, 'enabled' => true, 'time' => '', 'remote_only' => false, 'mode' => 'incremental', 'full_every' => 6, 'full_days' => 7 );
		update_option( Digitalisimo_Backups::POLICIES, $policies );
		list( $code, $rows ) = digi_rest( 'GET', $ns . '/automations' );
		$by = array();
		foreach ( $rows as $row ) $by[ $row['id'] ] = $row;
		digi_assert( 200 === $code && 'chains' === $by[ $created['id'] ]['retain_unit'] && 'Guardarás entre 8 y 14 respaldos en 2 cadenas. Siempre queda al menos una cadena anterior completa.' === $by[ $created['id'] ]['retain_estimate']['text'], 'GET /automations no lista la unidad ni la estimación: ' . json_encode( $by[ $created['id'] ] ?? null ) );
		digi_assert( 'points' === $by['vieja']['retain_unit'] && 10 === $by['vieja']['retain_estimate']['max'], 'Una automatización anterior sin unidad no cuenta respaldos: ' . json_encode( $by['vieja'] ) );
		digi_assert( 'points' === $by['monthly-full']['retain_unit'] && 'Guardarás 4 respaldos completos.' === $by['monthly-full']['retain_estimate']['text'], 'Una completa no dice N respaldos completos: ' . json_encode( $by['monthly-full'] ) );
		// Cambiar otra cosa de la anterior no le pone unidad.
		list( $code, $row ) = digi_rest( 'POST', $ns . '/automations/vieja', array( 'schedule' => 'weekly' ) );
		digi_assert( 200 === $code && 'points' === $row['retain_unit'] && 'weekly' === $row['schedule'] && 'points' === Digitalisimo_Backups_Chain::retain_unit( rcs_policy( 'vieja' ) ), 'Cambiar la frecuencia de una anterior le cambió la unidad: ' . json_encode( $row ) );
	} );

	rcs_case( 'S2 WP-CLI: automation add, update y list con la unidad y la estimación', function() {
		$cmd = 'digitalisimo-backups automation';
		list( $code, $out ) = digi_wp( $cmd, 'add', array(), array( 'schedule' => 'daily', 'mode' => 'incremental', 'full-every' => '6', 'retain' => '2', 'retain-unit' => 'chains', 'porcelain' => true ) );
		digi_assert( 0 === $code && 1 === count( $out ) && preg_match( '/^automation-[0-9a-f-]{36}$/', $out[0] ), 'automation add --porcelain no imprimió sólo el identificador: ' . implode( "\n", $out ) );
		$id = $out[0];
		digi_assert( 'chains' === ( rcs_policy( $id )['retain_unit'] ?? '' ) && 2 === rcs_policy( $id )['retain'], 'automation add no guardó «Conservar 2 cadenas»: ' . json_encode( rcs_policy( $id ) ) );
		list( $code, $out ) = digi_wp( $cmd, 'update', array( $id ), array( 'retain' => '20' ) );
		digi_assert( 0 === $code && 'Warning: «Conservar» quedó en 14 cadenas: con un completo cada 6 incrementales, sus cadenas caben así en el historial.' === $out[0] && 0 === strpos( end( $out ), 'Success: Automatización guardada: ' . $id . '. Guardarás entre 92 y 98 respaldos en 14 cadenas.' ), 'automation update no acotó ni avisó: ' . implode( "\n", $out ) );
		list( $code, $out ) = digi_wp( $cmd, 'update', array( $id ), array( 'retain' => '4', 'retain-unit' => 'points', 'destination' => 'local' ) );
		digi_assert( 0 === $code && 'Success: Automatización guardada: ' . $id . '. Guardarás entre 4 y 10 respaldos en 1 o 2 cadenas. Hay días con una sola cadena: si su base se daña, no hay otra copia.' === end( $out ) && 'incremental' === rcs_policy( $id )['mode'], 'automation update no cambió a respaldos: ' . implode( "\n", $out ) );
		list( $code, $out ) = digi_wp( $cmd, 'update', array( $id ), array( 'no-enabled' => true, 'enabled' => false ) );
		digi_assert( 0 === $code && false === rcs_policy( $id )['enabled'] && 'points' === rcs_policy( $id )['retain_unit'], 'automation update --no-enabled no la pausó sin tocar lo demás: ' . json_encode( rcs_policy( $id ) ) );
		list( $code, $out ) = digi_wp( $cmd, 'update', array( $id ), array( 'retain-unit' => 'meses' ) );
		digi_assert( 1 === $code && false !== strpos( end( $out ), 'retain_unit' ), 'Una unidad que no existe no falló: ' . implode( "\n", $out ) );
		$count = count( Digitalisimo_Backups::policies() );
		list( $code, $out ) = digi_wp( $cmd, 'update', array(), array( 'retain' => '3' ) );
		digi_assert( 1 === $code && $count === count( Digitalisimo_Backups::policies() ), 'automation update sin identificador no falló o creó una automatización: ' . implode( "\n", $out ) );
		list( $code, $out ) = digi_wp( $cmd, 'list', array(), array( 'format' => 'json' ) );
		$rows = array();
		foreach ( json_decode( $out[0], true ) as $row ) $rows[ $row['id'] ] = $row;
		digi_assert( 0 === $code && 'points' === $rows[ $id ]['retain_unit'] && 0 === strpos( $rows[ $id ]['estimate'], 'Guardarás entre 4 y 10 respaldos' ) && 'points' === $rows['monthly-full']['retain_unit'] && ! isset( $rows[ $id ]['retain_estimate'] ), 'automation list no lleva retain_unit y estimate: ' . $out[0] );
		list( $code, $out ) = digi_wp( $cmd, 'add', array(), array( 'mode' => 'incremental' ) );
		digi_assert( 0 === $code && 0 === strpos( end( $out ), 'Success: Automatización creada: automation-' ) && false !== strpos( end( $out ), 'Guardarás entre 4 y 10 respaldos' ), 'automation add sin más opciones no creó una incremental diaria con «Conservar 4»: ' . implode( "\n", $out ) );
	} );

	rcs_case( 'S3 crear y cambiar por REST o WP-CLI la programan en WP-Cron; una parcial anterior no se convierte en completa', function() use ( $ns ) {
		$scheduled = function( $id, $recurrence ) { foreach ( $GLOBALS['scheduled'] as $event ) if ( 'digitalisimo_backups_policy' === $event[0] && array( $id, 1 ) === $event[1] && $recurrence === $event[2] ) return true; return false; };
		$GLOBALS['scheduled'] = array();
		list( $code, $row ) = digi_rest( 'POST', $ns . '/automations', array( 'schedule' => 'hourly', 'mode' => 'incremental' ) );
		digi_assert( 201 === $code && $scheduled( $row['id'], 'hourly' ), 'POST /automations no programó la automatización: ' . json_encode( $GLOBALS['scheduled'] ) );
		$GLOBALS['scheduled'] = array();
		list( $code ) = digi_rest( 'POST', $ns . '/automations/' . $row['id'], array( 'schedule' => 'daily' ) );
		digi_assert( 200 === $code && $scheduled( $row['id'], 'daily' ), 'Cambiar la frecuencia por REST no la reprogramó: ' . json_encode( $GLOBALS['scheduled'] ) );
		$GLOBALS['scheduled'] = array();
		list( $code, $out ) = digi_wp( 'digitalisimo-backups automation', 'add', array(), array( 'schedule' => 'weekly', 'porcelain' => true ) );
		digi_assert( 0 === $code && $scheduled( $out[0], 'weekly' ), '`automation add` no programó la automatización: ' . json_encode( $GLOBALS['scheduled'] ) );
		$policies = get_option( Digitalisimo_Backups::POLICIES, Digitalisimo_Backups::default_policies() );
		$policies[] = array( 'id' => 'parcial-vieja', 'label' => 'BD', 'type' => 'database', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 3, 'enabled' => false );
		update_option( Digitalisimo_Backups::POLICIES, $policies );
		list( $code, $error ) = digi_rest( 'POST', $ns . '/automations/parcial-vieja', array( 'retain' => 2 ) );
		digi_assert( 400 === $code && 'digitalisimo_backups_automation_partial' === $error['code'] && 'database' === rcs_policy( 'parcial-vieja' )['type'] && 3 === rcs_policy( 'parcial-vieja' )['retain'], 'Una automatización parcial anterior se cambió por REST: ' . $code . ' ' . json_encode( rcs_policy( 'parcial-vieja' ) ) );
		list( $code ) = digi_wp( 'digitalisimo-backups automation', 'update', array( 'parcial-vieja' ), array( 'retain' => '2' ) );
		digi_assert( 1 === $code && 'database' === rcs_policy( 'parcial-vieja' )['type'], 'Una automatización parcial anterior se cambió por WP-CLI.' );
	} );

	rcs_case( 'S3 WP-CLI rechaza como REST lo que no es un entero de su rango; «cadenas» en «Completo cada vez» se avisa', function() use ( $ns ) {
		$cmd = 'digitalisimo-backups automation';
		$before = get_option( Digitalisimo_Backups::POLICIES );
		foreach ( array( array( 'retain' => '-3' ), array( 'retain' => '0' ), array( 'retain' => '101' ), array( 'retain' => '2.5' ), array( 'retain' => '1e1' ), array( 'full-every' => '500' ), array( 'full-every' => '0' ), array( 'full-days' => '9999' ), array( 'full-days' => '91' ) ) as $bad ) {
			list( $code, $out ) = digi_wp( $cmd, 'add', array(), array( 'mode' => 'incremental' ) + $bad );
			digi_assert( 1 === $code && false !== strpos( end( $out ), 'debe ser un número entero de 1 a' ), '`automation add` aceptó ' . json_encode( $bad ) . ': ' . implode( ' | ', $out ) );
			$rest = array();
			foreach ( $bad as $key => $value ) $rest[ str_replace( '-', '_', $key ) ] = $value;
			list( $code, $error ) = digi_rest( 'POST', $ns . '/automations', array( 'mode' => 'incremental' ) + $rest );
			digi_assert( 400 === $code && 0 === strpos( (string) $error['code'], 'digitalisimo_backups_automation_' ), 'POST /automations aceptó ' . json_encode( $rest ) . ': ' . $code );
		}
		digi_assert( $before === get_option( Digitalisimo_Backups::POLICIES ), 'Un número rechazado cambió las automatizaciones.' );
		list( $code, $out ) = digi_wp( $cmd, 'add', array(), array( 'mode' => 'incremental', 'retain' => '100', 'full-every' => '60', 'full-days' => '90', 'porcelain' => true ) );
		digi_assert( 0 === $code && 40 === rcs_policy( $out[0] )['retain'] && 60 === rcs_policy( $out[0] )['full_every'] && 90 === rcs_policy( $out[0] )['full_days'], 'Los máximos de cada rango no se aceptaron (con «Conservar» acotado a 40): ' . json_encode( rcs_policy( $out[0] ) ) );
		list( $code, $row ) = digi_rest( 'POST', $ns . '/automations', array( 'schedule' => 'daily', 'retain' => 2, 'retain_unit' => 'chains' ) );
		digi_assert( 201 === $code && 'points' === $row['retain_unit'] && false !== strpos( $row['notice'], 'la unidad «cadenas» sólo aplica a una automatización incremental' ), 'Pedir «cadenas» en una completa no avisó: ' . json_encode( $row ) );
		list( $code, $out ) = digi_wp( $cmd, 'update', array( $row['id'] ), array( 'retain-unit' => 'chains' ) );
		digi_assert( 0 === $code && 0 === strpos( $out[0], 'Warning: «Conservar» cuenta respaldos: la unidad «cadenas»' ), '`automation update --retain-unit=chains` en una completa no avisó: ' . implode( ' | ', $out ) );
	} );

	rcs_case( 'S3 no se crean más de 50 automatizaciones por REST ni por WP-CLI (cambiarlas sí)', function() use ( $ns ) {
		$saved = get_option( Digitalisimo_Backups::POLICIES );
		try {
			$made = 0;
			for ( $i = 0; $i < 80; $i++ ) { list( $code, $row ) = digi_rest( 'POST', $ns . '/automations', array( 'schedule' => 'hourly' ) ); if ( 201 !== $code ) break; $made++; }
			digi_assert( 50 === count( Digitalisimo_Backups::policies() ) && 400 === $code && 'digitalisimo_backups_automation_limit' === $row['code'] && false !== strpos( $row['message'], 'Ya hay 50 automatizaciones, el tope' ), 'El tope de automatizaciones no se aplicó por REST: ' . count( Digitalisimo_Backups::policies() ) . ' ' . json_encode( $row ) );
			list( $code, $out ) = digi_wp( 'digitalisimo-backups automation', 'add', array(), array( 'schedule' => 'daily' ) );
			digi_assert( 1 === $code && false !== strpos( end( $out ), 'Ya hay 50 automatizaciones' ) && 50 === count( Digitalisimo_Backups::policies() ), '`automation add` pasó del tope: ' . implode( ' | ', $out ) );
			list( $code ) = digi_rest( 'POST', $ns . '/automations/monthly-full', array( 'retain' => 3 ) );
			digi_assert( 200 === $code, 'Con el tope lleno no se pudo cambiar una automatización: ' . $code );
		} finally {
			update_option( Digitalisimo_Backups::POLICIES, $saved );
		}
	} );

	rcs_case( 'S1 REST y WP-CLI en una red: cambiar una automatización exige el alcance de red', function() use ( $ns, &$created ) {
		$GLOBALS['digi']['multisite'] = true;
		$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
		try {
			list( $code, $error ) = digi_rest( 'POST', $ns . '/automations/' . $created['id'], array( 'retain' => 3 ) );
			digi_assert( 400 === $code && 'digitalisimo_backups_scope' === $error['code'], 'En una red se cambió una automatización sin network=true: ' . $code );
			list( $code, $row ) = digi_rest( 'POST', $ns . '/automations/' . $created['id'], array( 'retain' => 3, 'network' => true ) );
			digi_assert( 200 === $code && 3 === $row['retain'] && 'chains' === $row['retain_unit'], 'En una red no se cambió con network=true: ' . json_encode( $row ) );
			list( $code, $out ) = digi_wp( 'digitalisimo-backups automation', 'add', array(), array( 'mode' => 'incremental' ) );
			digi_assert( 1 === $code && false !== strpos( end( $out ), 'alcance de red' ), 'En una red, automation add corrió sin --network: ' . implode( "\n", $out ) );
		} finally {
			$GLOBALS['digi']['multisite'] = false;
			$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
		}
	} );
} finally {
	digi_rmtree( $root );
}
if ( $failures ) { echo "FALLA: «Conservar» por cadenas en REST y WP-CLI con {$failures} caso(s) fallido(s).\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: «Conservar» por cadenas en REST (crear, cambiar y listar con la estimación) y en WP-CLI (automation add, update y list).\n";
