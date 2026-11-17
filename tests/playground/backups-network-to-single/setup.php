<?php
/**
 * Preparación del WordPress individual de destino (paso runPHP del blueprint de backups-network-to-single.sh): el
 * mu-plugin del ensayo (sin loopback ni correo), DIGITALÍSIMO Backups activo, contenido, un plugin y un tema propios de
 * este sitio. Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/falla.php', WPMU_PLUGIN_DIR . '/digi-n2s-falla.php' ) ) throw new RuntimeException( 'No se copió el mu-plugin del ensayo.' );
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	wp_mkdir_p( WP_PLUGIN_DIR . '/del-destino' );
	file_put_contents( WP_PLUGIN_DIR . '/del-destino/del-destino.php', "<?php\n/* Plugin Name: Del destino */\n" );
	activate_plugin( 'del-destino/del-destino.php' );
	update_option( 'blogname', 'WordPress de destino' );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	wp_set_current_user( $admin->ID );
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada del destino', 'post_content' => '<p>Sólo existe en el destino.</p>' ) );
	if ( is_wp_error( $id ) || ! $id ) throw new RuntimeException( 'No se creó la entrada.' );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'home' => home_url(), 'post' => $id, 'admin' => $admin->ID ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
