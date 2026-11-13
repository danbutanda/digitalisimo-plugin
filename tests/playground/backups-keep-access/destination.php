<?php
/**
 * Destino del ensayo keep-access (último paso runPHP): el WordPress individual (el /wp del incidente) con Backups y «admin» con
 * una contraseña de aplicación (como en el incidente), con el mismo correo que «digitalisimo» de la red (la misma
 * persona: el informe debe avisarlo). Un mu-plugin corta el loopback de WP-Cron (lo hace el ensayo con wp-cron.php,
 * como el cron del sistema) y limita cada petición web a 12 s para que la restauración siga por pasos, como en el
 * hosting real. Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	if ( is_file( $dir . '/setup-error.txt' ) ) return;
	global $wpdb;
	if ( 'wpkx_' !== $wpdb->base_prefix ) throw new RuntimeException( 'El destino no quedó con el prefijo wpkx_ (' . $wpdb->base_prefix . ').' );
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-keep-access-lab.php', "<?php\nadd_filter( 'pre_http_request', static function ( \$pre, \$args, \$url ) { return false !== strpos( (string) \$url, 'wp-cron.php' ) ? new WP_Error( 'digi_no_cron', 'WP-Cron por loopback cortado en el ensayo.' ) : \$pre; }, 10, 3 );\nadd_filter( 'digitalisimo_backups_request_limit', static function () { return 12; } );\n" );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin del destino.' );
	wp_update_user( array( 'ID' => $admin->ID, 'user_email' => 'dueno@digitalisimo.test' ) );
	$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'Cliente REST de /wp' ) );
	if ( is_wp_error( $created ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $created->get_error_message() );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'password' => $created[0], 'prefix' => $wpdb->base_prefix, 'home' => home_url(), 'login_url' => wp_login_url() ) ) );
	@chmod( $dir . '/setup.json', 0600 );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
