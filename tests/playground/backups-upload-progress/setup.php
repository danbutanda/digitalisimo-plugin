<?php
/**
 * Preparación del ensayo «subir e importar con el panel de avance» (paso runPHP del blueprint de
 * backups-upload-progress.sh): claves propias en wp-config.php (como un sitio real), el laboratorio como mu-plugin,
 * DIGITALÍSIMO Backups activo y el nombre del sitio con una entrada propia. Deja /digi-probe/setup.json; un fallo queda en
 * setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	$config = ABSPATH . 'wp-config.php';
	if ( is_file( $config ) && is_writable( $config ) ) {
		$text = (string) file_get_contents( $config );
		$text = preg_replace_callback( "/define\\(\\s*'(AUTH_KEY|SECURE_AUTH_KEY)'\\s*,\\s*'put your unique phrase here'\\s*\\);/", function( $m ) { return "define( '" . $m[1] . "', '" . bin2hex( random_bytes( 32 ) ) . "' );"; }, $text );
		file_put_contents( $config, $text );
	}
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/lab.php', WPMU_PLUGIN_DIR . '/digi-upbar-lab.php' ) ) throw new RuntimeException( 'No se copió el laboratorio.' );
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	update_option( 'blogname', 'Sitio de la subida' );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	wp_set_current_user( $admin->ID );
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada del ensayo de la subida', 'post_content' => '<p>Contenido del sitio.</p>' ) );
	if ( is_wp_error( $id ) || ! $id ) throw new RuntimeException( 'No se creó la entrada.' );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'home' => home_url(), 'post' => $id ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
