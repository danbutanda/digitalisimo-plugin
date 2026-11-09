<?php
/**
 * Destino del ensayo (último paso runPHP): activa Backups (en toda la red si es Multisite), corta el loopback de WP-Cron
 * y crea una contraseña de aplicación para admin. Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	if ( is_file( $dir . '/setup-error.txt' ) ) return;
	global $wpdb;
	$target = trim( (string) @file_get_contents( $dir . '/prefix.txt' ) );
	if ( '' === $target ) $target = 'dst_';
	if ( $target !== $wpdb->base_prefix ) throw new RuntimeException( 'El destino no quedó con el prefijo ' . $target . ' (' . $wpdb->base_prefix . ').' );
	$network = is_multisite();
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', $network );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-relocate-no-cron.php', "<?php\nadd_filter( 'pre_http_request', static function ( \$pre, \$args, \$url ) { return false !== strpos( (string) \$url, 'wp-cron.php' ) ? new WP_Error( 'digi_no_cron', 'WP-Cron por loopback cortado en el ensayo.' ) : \$pre; }, 10, 3 );\n" );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin del destino.' );
	$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-relocate-destino' ) );
	if ( is_wp_error( $created ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $created->get_error_message() );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'network' => $network, 'password' => $created[0], 'prefix' => $wpdb->base_prefix, 'home' => home_url(), 'other_options' => 'wp_' === $wpdb->base_prefix ? null : (int) $wpdb->get_var( 'SELECT COUNT(*) FROM wp_options' ), 'tables' => $wpdb->get_col( 'SHOW TABLES' ) ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
