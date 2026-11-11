<?php
/**
 * H23 (ensayo E4, escenario 2): restaurar el respaldo de otra instalación conserva la configuración de Backups de este
 * sitio. Antes, el SQL del respaldo traía la del origen: la conexión con nombre creada aquí desaparecía y este sitio
 * heredaba la automatización del origen hacia el mismo destino (los dos respaldaban en la misma carpeta).
 *
 *   1. con configuración propia: tras restaurar, la conexión con nombre de este sitio y su contraseña (cifrada con las
 *      claves de aquí, atada a su conexión) siguen, igual que la automatización, los avisos y el respaldo antes de
 *      actualizar; la conexión y la automatización del origen no aparecen, ni sus credenciales en el almacén, y el
 *      evento de WP-Cron de la automatización del origen se retira;
 *   2. una restauración interrumpida tras el SQL la devuelve igual (diario de la restauración);
 *   3. un sitio sin configuración propia (instalación nueva) recibe la del respaldo, como antes (S4);
 *   4. un respaldo de esta misma instalación (con su fila e identidad) no cambia: devuelve la configuración de su momento.
 * El respaldo «de otra instalación» es uno sin fila en el historial de aquí (como uno traído de un destino o subido).
 */
// WP-Cron mínimo: eventos por marca de tiempo, como _get_cron_array().
$GLOBALS['rk_cron'] = array();
function _get_cron_array() { return $GLOBALS['rk_cron']; }
function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) { $GLOBALS['rk_cron'][ (int) $timestamp ][ $hook ][ md5( serialize( $args ) ) ] = array( 'schedule' => $recurrence, 'args' => $args ); return true; }
function wp_unschedule_event( $timestamp, $hook, $args = array() ) { unset( $GLOBALS['rk_cron'][ (int) $timestamp ][ $hook ][ md5( serialize( $args ) ) ] ); return true; }
function wp_get_scheduled_event( $hook, $args = array() ) {
	foreach ( $GLOBALS['rk_cron'] as $timestamp => $hooks ) if ( isset( $hooks[ $hook ][ md5( serialize( $args ) ) ] ) ) return (object) array( 'timestamp' => $timestamp, 'schedule' => $hooks[ $hook ][ md5( serialize( $args ) ) ]['schedule'] );
	return false;
}
function rk_events( $id ) { $out = 0; foreach ( $GLOBALS['rk_cron'] as $hooks ) foreach ( (array) ( $hooks['digitalisimo_backups_policy'] ?? array() ) as $event ) if ( $id === ( $event['args'][0] ?? '' ) ) $out++; return $out; }
require __DIR__ . '/lib/backups-env.php';
function rk_check( $ok, $message ) { if ( ! $ok ) throw new RuntimeException( 'FALLA: ' . $message ); }
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Credentials';
$D = 'Digitalisimo_Backups_Destinations';
$connection = function( $name, $host ) { return array( 'type' => 'sftp', 'name' => $name, 'settings' => array( 'sftp_host' => $host, 'sftp_port' => '22', 'sftp_user' => 'respaldos', 'sftp_path' => '/respaldos', 'sftp_fingerprint' => str_repeat( 'A', 40 ) ) ); };
$policy = function( $id, $destination ) { return array( array( 'id' => $id, 'label' => 'Diario ' . $id, 'type' => 'full', 'schedule' => 'daily', 'retain' => 2, 'destination' => $destination, 'enabled' => true ) ); };
/** Configura un «sitio»: su conexión con nombre (con contraseña cifrada), su automatización, avisos y antes de actualizar. */
$configure = function( $id, $name, $host, $password, $policy_id, $email ) use ( $B, $C, $D, $connection, $policy ) {
	update_option( $D::CONNECTIONS, array( $id => $connection( $name, $host ) ), false );
	update_option( $B::POLICIES, $policy( $policy_id, $id ), false );
	$C::put( array( $id . '/sftp_password' => $password ), bk_call( $B, 'credential_settings' ) );
	Digitalisimo_Backups_Keys::put( Digitalisimo_Backups_Notify::OPTION, array( 'email' => $email, 'success' => true ) );
	Digitalisimo_Backups_Keys::put( Digitalisimo_Backups_Pre_Update::OPTION, array( 'core' => true, 'plugins' => $policy_id === 'origen-diario' ) );
	wp_schedule_event( time() + 3600, 'daily', 'digitalisimo_backups_policy', array( $policy_id, 1 ) );
};
$restore = function( $file, $confirm = 'RESTAURAR SIN FIRMA' ) use ( $B ) {
	$result = $B::service_restore( basename( $file ), array( 'confirm' => $confirm, 'via' => 'test' ) );
	for ( $i = 0; $i < 2000 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	rk_check( ! is_wp_error( $result ), 'La restauración no se encoló: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
	rk_check( 'Sitio de prueba' === ( $GLOBALS['bk_options']['blogname'] ?? '' ), 'La restauración no devolvió la base del respaldo.' );
};
try {
	// El «origen»: su conexión y su automatización diaria hacia ella, en el respaldo.
	$configure( 'sftp-aaaaaaaa', 'SFTP del origen', 'sftp.compartido.test', 'Clave-Del-Origen-1', 'origen-diario', 'origen@ejemplo.test' );
	// Sin fila en el historial de este sitio: es «de otra instalación».
	$backup = bk_backup( false );
	// El «destino»: otra configuración (la conexión que creó para traer el respaldo) y su evento del origen se quita.
	$GLOBALS['rk_cron'] = array();
	delete_option( $C::OPTION );
	delete_option( $C::OPTION_MORE );
	$configure( 'sftp-bbbbbbbb', 'SFTP del destino (E4)', 'sftp.compartido.test', 'Clave-Del-Destino-2', 'destino-semanal', 'destino@ejemplo.test' );
	$mine = array( 'connections' => get_option( $D::CONNECTIONS ), 'policies' => get_option( $B::POLICIES ), 'notify' => Digitalisimo_Backups_Keys::get( Digitalisimo_Backups_Notify::OPTION ), 'pre' => Digitalisimo_Backups_Keys::get( Digitalisimo_Backups_Pre_Update::OPTION ) );
	// El SQL de una instalación real trae su opción `cron`, con el evento de la automatización del origen.
	wp_schedule_event( time() + 7200, 'daily', 'digitalisimo_backups_policy', array( 'origen-diario', 1 ) );
	$GLOBALS['bk_options']['blogname'] = 'Cambiado después del respaldo';

	// 1. Con configuración propia, se conserva.
	$restore( $backup );
	rk_check( $mine['connections'] === get_option( $D::CONNECTIONS ), 'La conexión con nombre de este sitio no sobrevivió a la restauración (o volvió la del origen): ' . json_encode( array_keys( (array) get_option( $D::CONNECTIONS, array() ) ) ) );
	rk_check( $mine['policies'] === get_option( $B::POLICIES ), 'La automatización de este sitio no sobrevivió (o heredó la del origen): ' . json_encode( array_column( (array) get_option( $B::POLICIES, array() ), 'id' ) ) );
	rk_check( $mine['notify'] === Digitalisimo_Backups_Keys::get( Digitalisimo_Backups_Notify::OPTION ) && $mine['pre'] === Digitalisimo_Backups_Keys::get( Digitalisimo_Backups_Pre_Update::OPTION ), 'Los avisos o el respaldo antes de actualizar volvieron a los del origen.' );
	$settings = $B::connection_settings( 'sftp-bbbbbbbb' );
	rk_check( 'Clave-Del-Destino-2' === ( $settings['sftp_password'] ?? '' ), 'La contraseña de la conexión de este sitio ya no se descifra con sus claves.' );
	rk_check( ! preg_grep( '#^sftp-aaaaaaaa/#', array_keys( (array) $C::raw() ) ), 'Las credenciales del origen quedaron en el almacén de este sitio: ' . json_encode( array_keys( (array) $C::raw() ) ) );
	rk_check( 0 === rk_events( 'origen-diario' ) && 1 <= rk_events( 'destino-semanal' ), 'Los eventos de WP-Cron no son los de las automatizaciones de este sitio: ' . json_encode( array( 'origen' => rk_events( 'origen-diario' ), 'destino' => rk_events( 'destino-semanal' ) ) ) );

	// 2. Interrumpida tras el SQL: el diario la devuelve.
	bk_call( $B, 'write_restore_journal' );
	$journal = json_decode( (string) file_get_contents( bk_storage() . '/' . $B::RESTORE_JOURNAL ), true );
	rk_check( null === $journal['config_kept'], 'Fuera de una restauración, el diario anotó una configuración.' );
	$snapshot = bk_call( $B, 'keep_config_snapshot' );
	$property = new ReflectionProperty( $B, 'config_kept' );
	if ( PHP_VERSION_ID < 80100 ) $property->setAccessible( true );
	$property->setValue( null, $snapshot );
	bk_call( $B, 'write_restore_journal' );
	$property->setValue( null, null );
	update_option( $D::CONNECTIONS, array( 'sftp-aaaaaaaa' => $connection( 'SFTP del origen', 'sftp.compartido.test' ) ), false );
	update_option( $B::POLICIES, $policy( 'origen-diario', 'sftp-aaaaaaaa' ), false );
	bk_call( $B, 'recover_interrupted_restore' );
	rk_check( $mine['connections'] === get_option( $D::CONNECTIONS ) && $mine['policies'] === get_option( $B::POLICIES ) && 'Clave-Del-Destino-2' === ( $B::connection_settings( 'sftp-bbbbbbbb' )['sftp_password'] ?? '' ), 'El diario de una restauración interrumpida no devolvió la configuración de este sitio.' );

	// 3. Un sitio sin configuración propia recibe la del respaldo (recuperar un sitio perdido en un servidor nuevo).
	foreach ( array( $D::CONNECTIONS, $B::POLICIES, $C::OPTION, $C::OPTION_MORE, Digitalisimo_Backups_Notify::OPTION, Digitalisimo_Backups_Pre_Update::OPTION ) as $name ) delete_option( $name );
	$GLOBALS['bk_options']['blogname'] = 'Otra vez cambiado';
	$restore( $backup );
	rk_check( array( 'sftp-aaaaaaaa' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ) && 'Clave-Del-Origen-1' === ( $B::connection_settings( 'sftp-aaaaaaaa' )['sftp_password'] ?? '' ), 'Un sitio sin configuración propia no recibió la del respaldo con su contraseña.' );
	// 4. Un respaldo de esta misma instalación devuelve la configuración de su momento, como siempre (S4).
	$GLOBALS['rk_cron'] = array();
	delete_option( $C::OPTION );
	delete_option( $C::OPTION_MORE );
	$configure( 'sftp-cccccccc', 'SFTP de este sitio', 'sftp.propio.test', 'Clave-Propia-3', 'propio-diario', 'propio@ejemplo.test' );
	$own = bk_backup( true );
	update_option( $D::CONNECTIONS, array( 'sftp-dddddddd' => $connection( 'Creada después del respaldo', 'sftp.otro.test' ) ), false );
	$GLOBALS['bk_options']['blogname'] = 'Cambiado otra vez';
	$restore( $own, 'RESTAURAR' );
	rk_check( array( 'sftp-cccccccc' ) === array_keys( (array) get_option( $D::CONNECTIONS, array() ) ) && 'Clave-Propia-3' === ( $B::connection_settings( 'sftp-cccccccc' )['sftp_password'] ?? '' ), 'Un respaldo de esta misma instalación ya no devuelve la configuración de su momento: ' . json_encode( array_keys( (array) get_option( $D::CONNECTIONS, array() ) ) ) );
	echo "OK: la restauración conserva la configuración propia de Backups (H23)\n";
} finally {
	bk_cleanup();
}
