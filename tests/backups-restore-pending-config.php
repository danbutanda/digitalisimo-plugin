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
 *   4. tras cualquier restauración (también de este mismo sitio) sin ninguna automatización activa, el aviso lo dice.
 */
$GLOBALS['rk_cron'] = array();
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
	rk_check( array( 'sftp-bbbbbbbb' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ), 'No se conservó la configuración de este sitio.' );
	$response = Digitalisimo_Backups_Rest::restore_config( array() );
	rk_check( $response instanceof WP_REST_Response && true === $response->data['pending'], 'GET /restore-config no informa lo pendiente.' );

	// 2. «Aplicar la del respaldo» (pantalla): la del respaldo, con su automatización EN PAUSA y su contraseña descifrable.
	list( $ok, $message ) = bk_post( array( $B, 'restore_config_request' ), array( '_wpnonce' => 'nonce-digitalisimo_backups_restore_config', 'do' => 'apply' ) );
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
	rk_check( false !== strpos( implode( "\n", WP_CLI::$out ), 'no se aplicaron' ), 'wp … restore-config status no muestra el aviso.' );
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
	echo "OK: configuración del respaldo pendiente y aviso tras restaurar (H23)\n";
} finally {
	bk_cleanup();
}
