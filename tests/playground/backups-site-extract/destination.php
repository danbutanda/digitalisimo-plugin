<?php
/**
 * Fase «destino» de backups-site-extract.sh (paso runPHP del blueprint del servidor): un WordPress individual recién
 * instalado, con otra URL (127.0.0.1:<puerto>) y con Backups, al que el arnés copió el paquete en su almacén privado
 * (un archivo suelto: sin firma ni registro aquí). Guarda un ajuste de Backups propio (debe conservarse) y restaura el
 * paquete por el caso de uso de la pantalla, REST y WP-CLI. Deja restore.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
$dir = '/digi-probe';
try {
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	$admin = get_user_by( 'login', 'admin' );
	wp_set_current_user( $admin->ID );
	update_option( 'digitalisimo_backups_settings', array( 'retention' => '7', 'destination' => 'local' ), false );
	$before = array( 'home' => get_option( 'home' ), 'prefix' => $GLOBALS['wpdb']->prefix, 'users' => array_map( function( $u ) { return $u->user_login; }, get_users() ) );
	$file = trim( (string) file_get_contents( $dir . '/package.txt' ) );
	$check = Digitalisimo_Backups::service_verify( $file );
	$started = microtime( true );
	$result = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR SIN FIRMA' ) );
	if ( is_wp_error( $result ) ) throw new RuntimeException( 'La restauración falló: ' . $result->get_error_message() );
	file_put_contents( $dir . '/restore.json', wp_json_encode( array( 'before' => $before, 'verify' => is_wp_error( $check ) ? $check->get_error_message() : $check, 'result' => $result, 'seconds' => round( microtime( true ) - $started, 1 ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
