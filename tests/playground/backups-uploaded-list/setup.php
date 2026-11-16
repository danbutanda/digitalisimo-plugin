<?php
/**
 * Preparación del ensayo «lo subido queda en la lista» (paso runPHP del blueprint de backups-uploaded-list.sh). Según
 * /digi-probe/site.txt (`a` o `b`): claves propias en wp-config.php (como un sitio real), la sonda del ensayo como
 * mu-plugin (sin loopback ni correo), DIGITALÍSIMO Backups activo, el nombre del sitio, una entrada y un plugin de
 * ejemplo (inactivo) propios de cada sitio. Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	$site = trim( (string) file_get_contents( $dir . '/site.txt' ) );
	if ( ! in_array( $site, array( 'a', 'b' ), true ) ) throw new RuntimeException( 'Sitio desconocido: ' . $site );
	$config = ABSPATH . 'wp-config.php';
	if ( is_file( $config ) && is_writable( $config ) ) {
		$text = (string) file_get_contents( $config );
		$text = preg_replace_callback( "/define\\(\\s*'(AUTH_KEY|SECURE_AUTH_KEY)'\\s*,\\s*'put your unique phrase here'\\s*\\);/", function( $m ) { return "define( '" . $m[1] . "', '" . bin2hex( random_bytes( 32 ) ) . "' );"; }, $text );
		file_put_contents( $config, $text );
	}
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-uplist-probe.php' ) ) throw new RuntimeException( 'No se copió la sonda.' );
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	update_option( 'blogname', 'a' === $site ? 'Sitio A' : 'Sitio B' );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	wp_set_current_user( $admin->ID );
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada del sitio ' . strtoupper( $site ), 'post_content' => '<p>Sólo existe en el sitio ' . strtoupper( $site ) . '.</p>' ) );
	if ( is_wp_error( $id ) || ! $id ) throw new RuntimeException( 'No se creó la entrada.' );
	wp_mkdir_p( WP_PLUGIN_DIR . '/demo-' . $site );
	file_put_contents( WP_PLUGIN_DIR . '/demo-' . $site . '/demo-' . $site . '.php', "<?php\n/* Plugin Name: Demo del sitio " . strtoupper( $site ) . " */\n" );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'site' => $site, 'home' => home_url(), 'post' => $id ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
