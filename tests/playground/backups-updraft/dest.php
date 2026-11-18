<?php
/**
 * Destino del ensayo de UpdraftPlus (último paso runPHP, después de backups-relocate/destination.php): la sonda como
 * mu-plugin y lo que sólo tiene este sitio (una entrada y un plugin propio activo), para comprobar que la restauración
 * deja el sitio como el origen. Un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	if ( is_file( $dir . '/setup-error.txt' ) ) return;
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-ud-probe.php' ) ) throw new RuntimeException( 'No se copió la sonda.' );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin del destino.' );
	wp_set_current_user( $admin->ID );
	update_option( 'blogname', 'Destino antes de restaurar' );
	wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada del destino', 'post_content' => 'Sólo existe en el destino.' ) );
	wp_mkdir_p( WP_PLUGIN_DIR . '/demo-destino' );
	file_put_contents( WP_PLUGIN_DIR . '/demo-destino/demo-destino.php', "<?php\n/* Plugin Name: Demo del destino */\n" );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
