<?php
/**
 * H23, segunda parte: un sitio nuevo que sólo creó una conexión para traer el respaldo de un sitio perdido conservaba su
 * configuración y terminaba SIN respaldos automáticos sin que nadie lo notara. Ahora la configuración que trajo el
 * respaldo y no se aplicó queda pendiente (sus credenciales cifradas con las claves de aquí), con un aviso persistente en
 * el escritorio y en la tarjeta de Backups y dos acciones, también por REST y WP-CLI:
 *   1. el aviso dice cuántas automatizaciones y conexiones del origen no se aplicaron; la configuración pendiente no lleva
 *      ningún secreto en claro;
 *   2. «Aplicar la del respaldo» deja la configuración del respaldo con sus automatizaciones EN PAUSA (sin eventos de
 *      WP-Cron) y su contraseña descifrable; el aviso dice que están en pausa;
 *   3. «Descartar» (REST y WP-CLI) olvida la configuración del respaldo y deja la de aquí;
 *   4. tras cualquier restauración (también de este mismo sitio) sin ninguna automatización activa, el aviso lo dice;
 * y lo de la revisión de 7b8a1916 (cada uno se anota por separado, para que la base muestre los tres):
 *   I1 lo pendiente va cifrado entero: ni nombres de conexiones ni automatizaciones del origen en claro en la base;
 *   D2 «Aplicar» un respaldo sin la opción de automatizaciones (el origen usaba las de serie) no activa la de serie;
 *   D3 una muerte entre anotar lo pendiente y retirar el diario no lo pierde (la operación siguiente vuelve a aplicar el
 *      diario y ya no lo recalcula); y lo pendiente alterado (o con otras claves) no se aplica (409);
 * y H26 del ensayo E5 (el caso real: el sitio tiene su conexión con nombre, su automatización y «Completo mensual»; el
 * respaldo trae la automatización del origen y «Completo mensual»):
 *   (a) el aviso concuerda en número («1 automatización … que no se aplicó»; «actívala» con una en pausa);
 *   (b) el aviso cuenta las automatizaciones como las cuenta «Aplicar» después: las 2 del respaldo, que quedan en pausa;
 *   (c) antes de aplicar se dice lo que se pierde (su conexión y su automatización, con nombre si cabe en 140
 *       caracteres) y la pantalla sólo aplica con APLICAR escrito (lo comprueba el handler, misma acción y nonce);
 *       WP-CLI `apply` sigue sin pedirlo.
 */
$GLOBALS['rk_cron'] = array();
// Reprogramar una automatización activa de este sitio tras restaurar (H26, caso del ensayo E5) usa MINUTE_IN_SECONDS.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) define( 'MINUTE_IN_SECONDS', 60 );
function _get_cron_array() { return $GLOBALS['rk_cron']; }
function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) { $GLOBALS['rk_cron'][ (int) $timestamp ][ $hook ][ md5( serialize( $args ) ) ] = array( 'schedule' => $recurrence, 'args' => $args ); return true; }
function wp_unschedule_event( $timestamp, $hook, $args = array() ) { unset( $GLOBALS['rk_cron'][ (int) $timestamp ][ $hook ][ md5( serialize( $args ) ) ] ); return true; }
function wp_get_scheduled_event( $hook, $args = array() ) {
	foreach ( $GLOBALS['rk_cron'] as $timestamp => $hooks ) if ( isset( $hooks[ $hook ][ md5( serialize( $args ) ) ] ) ) return (object) array( 'timestamp' => $timestamp, 'schedule' => $hooks[ $hook ][ md5( serialize( $args ) ) ]['schedule'] );
	return false;
}
function rk_events( $id ) { $out = 0; foreach ( $GLOBALS['rk_cron'] as $hooks ) foreach ( (array) ( $hooks['digitalisimo_backups_policy'] ?? array() ) as $event ) if ( $id === ( $event['args'][0] ?? '' ) ) $out++; return $out; }
// REST y WP-CLI mínimos: los manejadores reales sobre los casos de uso.
class WP_REST_Response { public $data; public $status = 200; public function __construct( $data ) { $this->data = $data; } public function set_status( $status ) { $this->status = $status; } }
function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value ); }
class Rk_Cli_Exit extends Exception {}
class WP_CLI {
	public static $out = array();
	public static function success( $message ) { self::$out[] = 'Success: ' . $message; }
	public static function line( $message = '' ) { self::$out[] = (string) $message; }
	public static function warning( $message ) { self::$out[] = 'Warning: ' . $message; }
	public static function error( $message ) { self::$out[] = 'Error: ' . $message; throw new Rk_Cli_Exit( (string) $message ); }
}
require __DIR__ . '/lib/backups-env.php';
require_once __DIR__ . '/../digitalisimo-backups/includes/class-backups-rest.php';
require_once __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php';
function rk_check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( 'FALLA: ' . $message ); }
$GLOBALS['rk_fails'] = array();
function rk_soft( $ok, $message ) { if ( ! $ok ) { $GLOBALS['rk_fails'][] = $message; fwrite( STDERR, 'FALLA: ' . $message . "\n" ); } }
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Credentials';
$D = 'Digitalisimo_Backups_Destinations';
$connection = function( $name, $host ) { return array( 'type' => 'sftp', 'name' => $name, 'settings' => array( 'sftp_host' => $host, 'sftp_port' => '22', 'sftp_user' => 'respaldos', 'sftp_path' => '/respaldos', 'sftp_fingerprint' => str_repeat( 'A', 40 ) ) ); };
$policy = function( $id, $destination, $enabled = true ) { return array( array( 'id' => $id, 'label' => 'Diario ' . $id, 'type' => 'full', 'schedule' => 'daily', 'retain' => 2, 'destination' => $destination, 'enabled' => $enabled ) ); };
$configure = function( $id, $name, $host, $password, $policy_id, $enabled = true ) use ( $B, $C, $D, $connection, $policy ) {
	delete_option( $C::OPTION );
	delete_option( $C::OPTION_MORE );
	update_option( $D::CONNECTIONS, array( $id => $connection( $name, $host ) ), false );
	update_option( $B::POLICIES, $policy( $policy_id, $id, $enabled ), false );
	$C::put( array( $id . '/sftp_password' => $password ), bk_call( $B, 'credential_settings' ) );
	if ( $enabled ) wp_schedule_event( time() + 3600, 'daily', 'digitalisimo_backups_policy', array( $policy_id, 1 ) );
};
$restore = function( $file, $confirm = 'RESTAURAR SIN FIRMA' ) use ( $B ) {
	$GLOBALS['bk_options']['blogname'] = 'Cambiado ' . bin2hex( random_bytes( 3 ) );
	$result = $B::service_restore( basename( $file ), array( 'confirm' => $confirm, 'via' => 'test' ) );
	for ( $i = 0; $i < 2000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	rk_check( ! is_wp_error( $result ) && 'Sitio de prueba' === ( $GLOBALS['bk_options']['blogname'] ?? '' ), 'La restauración no terminó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
};
$notice_html = function() use ( $B ) { ob_start(); $B::restore_config_admin_notice(); return (string) ob_get_clean(); };
$GLOBALS['bk_caps'] = array( 'manage_options' => true );
try {
	// El sitio perdido: su conexión y su automatización diaria, en un respaldo sin fila aquí (de otra instalación).
	$configure( 'sftp-aaaaaaaa', 'SFTP del sitio perdido', 'sftp.ejemplo.test', 'Clave-Del-Origen-1', 'origen-diario' );
	$backup = bk_backup( false );
	// La instalación nueva: sólo la conexión que creó para traerlo, y la automatización de serie en pausa.
	$GLOBALS['rk_cron'] = array();
	$configure( 'sftp-bbbbbbbb', 'SFTP para traer el respaldo', 'sftp.ejemplo.test', 'Clave-Del-Destino-2', 'monthly-full', false );
	$restore( $backup );

	// 1. El aviso, sin secretos en claro en lo pendiente.
	$status = $B::service_restore_config();
	rk_check( true === $status['pending'] && 1 === $status['automations'] && 1 === $status['connections'] && true === $status['no_active'], 'La configuración del respaldo no quedó pendiente: ' . json_encode( $status ) );
	rk_check( false !== strpos( $status['message'], 'Este sitio conservó su configuración de Backups; el respaldo restaurado traía 1 automatización y 1 conexión del sitio de origen que no se aplicaron.' ), 'El aviso no dice lo que no se aplicó: ' . $status['message'] );
	$html = $notice_html();
	rk_check( false !== strpos( $html, 'no se aplicaron' ) && false !== strpos( $html, 'Aplicar la del respaldo' ) && false !== strpos( $html, 'Descartar' ) && false !== strpos( $html, 'value="digitalisimo_backups_restore_config"' ), 'El aviso del escritorio no ofrece las dos acciones: ' . $html );
	$stored = json_encode( array( get_option( 'digitalisimo_backups_restore_notice' ), $GLOBALS['bk_options'] ) );
	rk_check( false === strpos( $stored, 'Clave-Del-Origen-1' ) && false === strpos( $stored, 'Clave-Del-Destino-2' ), 'La configuración pendiente lleva una contraseña en claro.' );
	// I1: la configuración del origen tampoco (la conexión con nombre, la automatización): va cifrada entera.
	$raw_notice = json_encode( get_option( 'digitalisimo_backups_restore_notice' ) );
	rk_soft( false === strpos( $raw_notice, 'SFTP del sitio perdido' ) && false === strpos( $raw_notice, 'origen-diario' ) && false === strpos( $raw_notice, 'sftp_host' ), 'I1: lo pendiente guarda en claro la configuración del origen: ' . substr( $raw_notice, 0, 300 ) );
	rk_check( array( 'sftp-bbbbbbbb' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ), 'No se conservó la configuración de este sitio.' );
	$response = Digitalisimo_Backups_Rest::restore_config( array() );
	rk_check( $response instanceof WP_REST_Response && true === $response->data['pending'], 'GET /restore-config no informa lo pendiente.' );

	// 2. «Aplicar la del respaldo» (pantalla): la del respaldo, con su automatización EN PAUSA y su contraseña descifrable.
	list( $ok, $message ) = bk_post( array( $B, 'restore_config_request' ), array( '_wpnonce' => 'nonce-digitalisimo_backups_restore_config', 'do' => 'apply', 'digitalisimo_backups_config_confirmation' => 'APLICAR' ) );
	rk_check( $ok && false !== strpos( $message, 'en pausa' ), '«Aplicar la del respaldo» no respondió: ' . $message );
	$policies = (array) get_option( $B::POLICIES, array() );
	rk_check( array( 'sftp-aaaaaaaa' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ) && 'origen-diario' === ( $policies[0]['id'] ?? '' ) && false === $policies[0]['enabled'], 'No se aplicó la configuración del respaldo con la automatización en pausa: ' . json_encode( $policies ) );
	rk_check( 'Clave-Del-Origen-1' === ( $B::connection_settings( 'sftp-aaaaaaaa' )['sftp_password'] ?? '' ), 'La contraseña de la conexión del respaldo no se descifra con las claves de este sitio.' );
	rk_check( 0 === rk_events( 'origen-diario' ), 'La automatización en pausa tiene un evento de WP-Cron.' );
	$status = $B::service_restore_config();
	rk_check( false === $status['pending'] && 1 === $status['paused'] && false !== strpos( $status['message'], 'automatización en pausa' ), 'El aviso no dice que la automatización está en pausa: ' . json_encode( $status ) );

	// 3. «Descartar» por WP-CLI y por REST.
	$cli = new Digitalisimo_Backups_Cli_Restore_Config();
	WP_CLI::$out = array();
	$cli->discard( array(), array() );
	rk_check( '' === $B::service_restore_config()['message'] && '' === $notice_html(), 'wp … restore-config discard no retiró el aviso.' );
	// Otra vez, ahora con la configuración aplicada (en pausa) como la de aquí: el respaldo trae la suya activa.
	$restore( $backup );
	rk_check( true === $B::service_restore_config()['pending'], 'La segunda restauración no dejó pendiente la configuración del respaldo.' );
	WP_CLI::$out = array();
	$cli->status( array(), array() );
	// Sólo la automatización del respaldo es distinta de la de aquí (la conexión ya es la misma): el número concuerda (H26).
	rk_check( false !== strpos( implode( "\n", WP_CLI::$out ), 'el respaldo restaurado traía 1 automatización' ), 'wp … restore-config status no muestra el aviso: ' . implode( "\n", WP_CLI::$out ) );
	rk_soft( false !== strpos( implode( "\n", WP_CLI::$out ), 'el respaldo restaurado traía 1 automatización del sitio de origen que no se aplicó.' ), 'H26 (a): wp … restore-config status no concuerda en número: ' . implode( "\n", WP_CLI::$out ) );
	$response = Digitalisimo_Backups_Rest::restore_config_discard( array( 'network' => false ) );
	rk_check( $response instanceof WP_REST_Response && ! empty( $response->data['discarded'] ) && false === $B::service_restore_config()['pending'] && false === ( (array) get_option( $B::POLICIES, array() ) )[0]['enabled'], 'POST /restore-config/discard no descartó lo pendiente o cambió la configuración de aquí.' );
	$response = Digitalisimo_Backups_Rest::restore_config_apply( array( 'network' => false ) );
	rk_check( is_wp_error( $response ) && 404 === ( $response->get_error_data()['status'] ?? 0 ), 'Aplicar sin nada pendiente no respondió 404.' );

	// 4. Una restauración de este mismo sitio que lo deja sin automatizaciones activas: el aviso lo dice.
	$own = bk_backup( true );
	$restore( $own, 'RESTAURAR' );
	$status = $B::service_restore_config();
	rk_check( false === $status['pending'] && true === $status['no_active'] && false !== strpos( $status['message'], 'no tiene ninguna automatización de respaldo activa' ), 'Tras restaurar sin automatizaciones activas, el aviso no lo dice: ' . json_encode( $status ) );
	rk_check( false === strpos( $notice_html(), 'Aplicar la del respaldo' ) && false !== strpos( $notice_html(), 'Descartar' ), 'Sin nada pendiente, el aviso ofrece aplicar.' );

	// 5. D2: el respaldo no traía la opción de automatizaciones (el origen usaba las de serie): «Aplicar» no la borra,
	// porque la de serie volvería ACTIVA y con su evento; queda también en pausa.
	$configure( 'sftp-cccccccc', 'SFTP del origen sin automatizaciones', 'sftp.otro.test', 'Clave-Del-Origen-3', 'sin-uso' );
	delete_option( $B::POLICIES );
	$plain_policies = bk_backup( false );
	$GLOBALS['rk_cron'] = array();
	$configure( 'sftp-bbbbbbbb', 'SFTP para traer el respaldo', 'sftp.ejemplo.test', 'Clave-Del-Destino-2', 'monthly-full', false );
	$restore( $plain_policies );
	$status = $B::service_restore_config();
	rk_check( true === $status['pending'] && 1 === $status['connections'], 'El respaldo sin automatizaciones no dejó pendiente su conexión: ' . json_encode( $status ) );
	$result = $B::service_restore_config_apply();
	$active = bk_call( $B, 'active_policies', $B::policies() );
	rk_check( ! is_wp_error( $result ) && array( 'sftp-cccccccc' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ), '«Aplicar» no aplicó la configuración del respaldo sin automatizaciones: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	rk_soft( ! $active && 0 === rk_events( 'monthly-full' ) && is_array( get_option( $B::POLICIES, null ) ) && 1 === ( $result['paused'] ?? null ), 'D2: «Aplicar» activó la automatización de serie que este sitio tenía en pausa: ' . json_encode( array( 'activas' => array_column( $active, 'id' ), 'eventos' => rk_events( 'monthly-full' ), 'pausadas' => $result['paused'] ?? null ) ) );

	// 6. D3: el proceso muere después de anotar lo pendiente y antes de retirar el diario (la restauración por pasos, como
	// en restore/trait-backups-restore-engine.php: foto de aquí en el diario, el SQL, after_restore_sql() y nada más).
	$GLOBALS['rk_cron'] = array();
	$configure( 'sftp-bbbbbbbb', 'SFTP para traer el respaldo', 'sftp.ejemplo.test', 'Clave-Del-Destino-2', 'monthly-full', false );
	$B::service_restore_config_discard();
	$kept = new ReflectionProperty( $B, 'config_kept' );
	if ( PHP_VERSION_ID < 80100 ) $kept->setAccessible( true );
	$kept->setValue( null, bk_call( $B, 'keep_config_snapshot' ) );
	$history = bk_call( $B, 'fresh_history' );
	$vault = Digitalisimo_Backups_Keys::snapshot();
	bk_call( $B, 'write_restore_journal' );
	$kept->setValue( null, null );
	$configure( 'sftp-aaaaaaaa', 'SFTP del sitio perdido', 'sftp.ejemplo.test', 'Clave-Del-Origen-1', 'origen-diario' ); // lo que trae el SQL
	bk_call( $B, 'after_restore_sql', $history, $vault );
	rk_check( true === $B::service_restore_config()['pending'] && array( 'sftp-bbbbbbbb' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ), 'Tras el SQL simulado no quedó lo pendiente.' );
	$journal = Digitalisimo_Backups::storage_directory() . '/' . $B::RESTORE_JOURNAL;
	rk_check( is_file( $journal ), 'La simulación no dejó el diario (la muerte antes de retirarlo).' );
	bk_call( $B, 'recover_interrupted_restore' );
	$status = $B::service_restore_config();
	rk_check( ! is_file( $journal ) && array( 'sftp-bbbbbbbb' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ), 'La operación siguiente no aplicó el diario o no conservó la configuración de aquí.' );
	rk_soft( true === $status['pending'] && 1 === $status['automations'] && 1 === $status['connections'], 'D3: una muerte entre anotar lo pendiente y retirar el diario perdió la configuración del respaldo: ' . json_encode( $status ) );
	// Lo pendiente alterado (o sellado con otras claves) no se aplica y no cambia nada.
	$notice = get_option( 'digitalisimo_backups_restore_notice' );
	if ( ! is_string( $notice['pending']['box'] ?? null ) ) {
		rk_soft( false, 'I1: lo pendiente no está sellado: ' . substr( json_encode( $notice['pending'] ?? null ), 0, 120 ) );
	} else {
		$notice['pending']['box'] = substr( $notice['pending']['box'], 0, -4 ) . ( 'AAAA' === substr( $notice['pending']['box'], -4 ) ? 'BBBB' : 'AAAA' );
		update_option( 'digitalisimo_backups_restore_notice', $notice, false );
		$result = $B::service_restore_config_apply();
		rk_check( is_wp_error( $result ) && 409 === ( $result->get_error_data()['status'] ?? 0 ) && array( 'sftp-bbbbbbbb' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ), 'Lo pendiente alterado se aplicó o no respondió 409.' );
	}
	// 7. H26 (ensayo E5). (a) El número concuerda, también con una sola en pausa.
	$one = bk_call( $B, 'restore_notice_text', array( 'automations' => 1, 'connections' => 0, 'pending' => array( 'v' => 1 ), 'no_active' => false, 'paused' => 0 ) );
	rk_soft( false !== strpos( $one, 'el respaldo restaurado traía 1 automatización del sitio de origen que no se aplicó.' ), 'H26 (a): el aviso no concuerda en número: ' . $one );
	$one = bk_call( $B, 'restore_notice_text', array( 'paused' => 1 ) );
	rk_soft( false !== strpos( $one, 'con 1 automatización en pausa: actívala en ' ) && false !== strpos( $one, 'que no respalda a la misma carpeta' ), 'H26 (a): el aviso de una en pausa no concuerda en número: ' . $one );
	// El caso real: el origen tiene su automatización diaria y «Completo mensual», sin conexiones con nombre.
	$B::service_restore_config_discard();
	$GLOBALS['rk_cron'] = array();
	delete_option( $C::OPTION );
	delete_option( $C::OPTION_MORE );
	delete_option( $D::CONNECTIONS );
	update_option( $B::POLICIES, array_merge( $B::default_policies(), array( array( 'id' => 'automation-origen-e5', 'label' => '', 'type' => 'full', 'schedule' => 'daily', 'retain' => 2, 'destination' => 'local', 'enabled' => true ) ) ), false );
	$origin_e5 = bk_backup( false );
	// Este sitio: su conexión con nombre, su automatización semanal y «Completo mensual» (igual que la del origen).
	$GLOBALS['rk_cron'] = array();
	$own_e5 = function() use ( $B, $D, $connection ) {
		update_option( $D::CONNECTIONS, array( 'sftp-dddddddd' => $connection( 'SFTP E5 (destino)', 'sftp.destino.test' ) ), false );
		update_option( $B::POLICIES, array_merge( $B::default_policies(), array( array( 'id' => 'automation-propia-e5', 'label' => '', 'type' => 'full', 'schedule' => 'weekly', 'time' => '04:45', 'retain' => 3, 'destination' => 'local', 'enabled' => true ) ) ), false );
	};
	$own_e5();
	$restore( $origin_e5 );
	$status = $B::service_restore_config();
	rk_check( true === $status['pending'] && array( 'sftp-dddddddd' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ), 'E5: la restauración no conservó la configuración de aquí o no dejó pendiente la del respaldo: ' . json_encode( $status ) );
	// (b) Cuenta las 2 automatizaciones del respaldo, las mismas que «Aplicar» deja en pausa.
	rk_soft( 2 === $status['automations'] && false !== strpos( $status['message'], 'el respaldo restaurado traía 2 automatizaciones del sitio de origen que no se aplicaron.' ), 'H26 (b): el aviso no cuenta las automatizaciones que «Aplicar» dejará en pausa: ' . json_encode( array( $status['automations'], $status['message'] ) ) );
	// (c) Lo que se pierde, antes de aplicar: en el estado (REST y WP-CLI), en el escritorio y en la franja de Backups.
	$loses = 'Aplicarla reemplaza la de este sitio: se pierden 1 conexión («SFTP E5 (destino)») y 1 automatización («Semanal 04:45»).';
	rk_soft( false !== strpos( $status['message'], $loses ), 'H26 (c): el estado no dice lo que se pierde al aplicarla: ' . $status['message'] );
	WP_CLI::$out = array();
	$cli->status( array(), array() );
	rk_soft( false !== strpos( implode( "\n", WP_CLI::$out ), $loses ), 'H26 (c): wp … restore-config status no dice lo que se pierde: ' . implode( "\n", WP_CLI::$out ) );
	$html = $notice_html();
	rk_soft( false !== strpos( $html, esc_html( $loses ) ) && 1 === preg_match( '#<input type="text" name="digitalisimo_backups_config_confirmation"[^>]*required#', $html ) && false === strpos( $html, 'confirm(' ), 'H26 (c): el aviso del escritorio no dice lo que se pierde o no pide APLICAR: ' . $html );
	$strip = bk_call( $B, 'restore_config_strip_item' );
	rk_soft( $loses === ( $strip['warning'] ?? '' ) && mb_strlen( $loses, 'UTF-8' ) <= 140 && false === strpos( (string) $strip['detail'], $loses ) && 1 === preg_match( '#value="apply">.*name="digitalisimo_backups_config_confirmation"#s', (string) $strip['actions'] ) && false === strpos( (string) $strip['actions'], 'confirm(' ), 'H26 (c): la franja no dice a la vista lo que se pierde o no pide APLICAR: ' . json_encode( $strip ) );
	// (c) Sin APLICAR (o con otra palabra) no cambia nada, con la misma acción y el mismo nonce.
	foreach ( array( null, 'SI', 'APLICA' ) as $word ) {
		$post = array( '_wpnonce' => 'nonce-digitalisimo_backups_restore_config', 'action' => 'digitalisimo_backups_restore_config', 'do' => 'apply' );
		if ( null !== $word ) $post['digitalisimo_backups_config_confirmation'] = $word;
		list( $ok, $message ) = bk_post( array( $B, 'restore_config_request' ), $post );
		$policies = array_column( (array) get_option( $B::POLICIES, array() ), 'id' );
		rk_soft( ! $ok && false !== strpos( $message, 'Escribe APLICAR' ) && array( 'sftp-dddddddd' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ) && in_array( 'automation-propia-e5', $policies, true ) && true === $B::service_restore_config()['pending'], 'H26 (c): «Aplicar la del respaldo» se aplicó sin escribir APLICAR (' . json_encode( $word ) . '): ' . $message );
		if ( $ok ) break;
	}
	// Con la palabra se aplica; el mensaje y el aviso cuentan las mismas 2 que el aviso de antes.
	list( $ok, $message ) = bk_post( array( $B, 'restore_config_request' ), array( '_wpnonce' => 'nonce-digitalisimo_backups_restore_config', 'action' => 'digitalisimo_backups_restore_config', 'do' => 'apply', 'digitalisimo_backups_config_confirmation' => ' aplicar ' ) );
	$after = $B::service_restore_config();
	$paused = array_values( array_filter( (array) get_option( $B::POLICIES, array() ), function( $policy ) { return empty( $policy['enabled'] ); } ) );
	rk_soft( $ok && false !== strpos( $message, 'con 2 automatizaciones en pausa: actívalas' ) && 2 === $after['paused'] && false !== strpos( $after['message'], 'con 2 automatizaciones en pausa' ) && array( 'monthly-full', 'automation-origen-e5' ) === array_column( $paused, 'id' ), 'H26 (b): tras aplicar no se cuentan las mismas 2 automatizaciones del aviso: ' . json_encode( array( $ok, $message, $after['paused'], array_column( $paused, 'id' ) ) ) );
	// WP-CLI `apply` sigue como estaba: aplica sin pedir la palabra.
	$restore( $origin_e5 );
	$status = $B::service_restore_config();
	rk_soft( 2 === $status['automations'] && false !== strpos( $status['message'], 'Aplicarla reemplaza los ajustes y el destino principal de este sitio.' ), 'H26 (c): sin conexiones ni automatizaciones propias que se pierdan, el aviso no lo dice así: ' . $status['message'] );
	WP_CLI::$out = array();
	$cli->apply( array(), array() );
	rk_check( false !== strpos( implode( "\n", WP_CLI::$out ), 'Success: Se aplicó la configuración de Backups del respaldo restaurado' ) && false === $B::service_restore_config()['pending'], 'wp … restore-config apply dejó de aplicar sin confirmación: ' . implode( "\n", WP_CLI::$out ) );
	// (c) Con nombres que no caben en 140 caracteres, la advertencia los cuenta sin nombrarlos.
	if ( ! method_exists( $B, 'restore_config_replaces' ) ) {
		rk_soft( false, 'H26 (c): no hay advertencia de lo que se pierde al aplicar.' );
	} else {
		update_option( $D::CONNECTIONS, array( 'sftp-eeeeeeee' => $connection( str_repeat( 'Conexión de nombre larguísimo ', 3 ), 'sftp.largo.test' ) ), false );
		update_option( $B::POLICIES, array( array( 'id' => 'automation-larga', 'label' => str_repeat( 'Automatización de nombre largo ', 2 ), 'type' => 'full', 'schedule' => 'daily', 'retain' => 2, 'destination' => 'local', 'enabled' => true ) ), false );
		$long = bk_call( $B, 'restore_config_replaces', array( 'format' => 1, 'site' => array(), 'network' => null ) );
		rk_soft( 'Aplicarla reemplaza la de este sitio: se pierden 1 conexión y 1 automatización.' === $long, 'H26 (c): con nombres largos la advertencia no se acorta a 140 caracteres: ' . $long );
	}
	rk_check( ! $GLOBALS['rk_fails'], count( $GLOBALS['rk_fails'] ) . ' hallazgo(s) de la revisión siguen: ' . implode( ' | ', $GLOBALS['rk_fails'] ) );
	echo "OK: configuración del respaldo pendiente y aviso tras restaurar (H23), con el número, el conteo y la confirmación de H26\n";
} finally {
	bk_cleanup();
}
