<?php
/**
 * Origen del ensayo de Backuply (paso runPHP del blueprint de backups-backuply.sh): activa Backuply 1.6.0 (de
 * wordpress.org) y DIGITALÍSIMO Backups, en toda la red si es Multisite; instala el controlador de base de datos del
 * ensayo (backuply-shim.php) y corta los loopback (WP-Cron y las llamadas de Backuply a sí mismo: el ensayo hace avanzar
 * a Backuply llamando a /wp-content/digi-web/backuply-run.php). Crea contenido con direcciones del sitio (una entrada con
 * enlace interno e imagen, una opción serializada) y, en una red, un subsitio «uno» con otra entrada. Deja
 * /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	$network = is_multisite();
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	copy( $dir . '/backuply-shim.php', WPMU_PLUGIN_DIR . '/digi-backuply-shim.php' );
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-backuply-no-loopback.php', "<?php\nadd_filter( 'pre_http_request', static function ( \$pre, \$args, \$url ) { \$host = (string) parse_url( (string) \$url, PHP_URL_HOST ); return in_array( \$host, array( parse_url( home_url(), PHP_URL_HOST ), '127.0.0.1', 'localhost' ), true ) ? new WP_Error( 'digi_no_loopback', 'Loopback cortado en el ensayo.' ) : \$pre; }, 10, 3 );\n" );
	foreach ( array( 'backuply/backuply.php', 'digitalisimo-backups/digitalisimo-backups.php' ) as $plugin ) {
		$activated = activate_plugin( $plugin, '', $network );
		if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $activated->get_error_message() );
	}
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	wp_set_current_user( $admin->ID );
	$content = function( $title ) {
		$image = imagecreatetruecolor( 32, 32 );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 200, 60, 30 ) );
		ob_start();
		imagepng( $image );
		$upload = wp_upload_bits( 'backuply-' . get_current_blog_id() . '.png', null, ob_get_clean() );
		if ( ! empty( $upload['error'] ) ) throw new RuntimeException( 'No se subió la imagen: ' . $upload['error'] );
		$url = $upload['url'];
		$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => 'hola-backuply', 'post_content' => "<p>Texto con 'comillas', \"dobles\", ; punto y coma, \\ barra y ñandú €.</p><p><a href=\"" . esc_url( home_url( '/contacto/' ) ) . "\">Contacto</a> <img src=\"" . esc_url( $url ) . "\"></p>" ) );
		if ( is_wp_error( $id ) || ! $id ) throw new RuntimeException( 'No se creó la entrada.' );
		update_option( 'digi_backuply_probe', array( 'home' => home_url( '/' ), 'nested' => serialize( array( 'imagen' => $url ) ) ) );
		return array( 'id' => $id, 'link' => get_permalink( $id ), 'image' => wp_parse_url( $url, PHP_URL_PATH ) );
	};
	$pages = array( 'main' => $content( 'Hola desde Backuply' ) );
	if ( $network ) {
		$row = get_network();
		$site = wp_insert_site( array( 'domain' => $row->domain, 'path' => $row->path . 'uno/', 'title' => 'Uno', 'user_id' => $admin->ID ) );
		if ( is_wp_error( $site ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $site->get_error_message() );
		switch_to_blog( $site );
		$pages['uno'] = $content( 'Hola desde Backuply en el subsitio' );
		restore_current_blog();
	}
	$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-backuply-origen' ) );
	if ( is_wp_error( $created ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $created->get_error_message() );
	global $wpdb;
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'network' => $network, 'password' => $created[0], 'pages' => $pages, 'prefix' => $wpdb->base_prefix, 'home' => home_url(), 'backuply' => defined( 'BACKUPLY_VERSION' ) ? BACKUPLY_VERSION : '', 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION . ' · Backuply ' . ( defined( 'BACKUPLY_VERSION' ) ? BACKUPLY_VERSION : '?' ) ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
