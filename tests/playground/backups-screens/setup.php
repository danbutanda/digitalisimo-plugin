<?php
/**
 * Preparación del ensayo de pantallas (paso runPHP del blueprint de backups-screens.sh). Según /digi-probe/mode.txt:
 *   backuply   activa Backuply 1.6.0 (de wordpress.org) con el controlador de base de datos del ensayo de Backuply
 *              (backuply-shim.php), sin DIGITALÍSIMO Backups;
 *   single     activa DIGITALÍSIMO Backups en un sitio individual;
 *   multisite  activa DIGITALÍSIMO Backups en toda la red y crea el subsitio «uno».
 * En todos corta el loopback (WP-Cron y las llamadas de Backuply a sí mismo: un servidor de un solo proceso se esperaría
 * a sí mismo) y el correo, crea una entrada con imagen y una contraseña de aplicación del admin. Deja
 * /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
try {
	$mode = trim( (string) file_get_contents( $dir . '/mode.txt' ) );
	// Claves propias en wp-config.php, como un sitio real (con las de ejemplo, Backups avisa de que no puede cifrar las
	// credenciales de los destinos). Se reescriben las líneas de ejemplo: definirlas otra vez desde el blueprint daría
	// «Constant already defined» antes de las cabeceras y no se podría iniciar sesión.
	$config = ABSPATH . 'wp-config.php';
	if ( is_file( $config ) && is_writable( $config ) ) {
		$text = (string) file_get_contents( $config );
		$text = preg_replace_callback( "/define\\(\\s*'(AUTH_KEY|SECURE_AUTH_KEY)'\\s*,\\s*'put your unique phrase here'\\s*\\);/", function( $m ) { return "define( '" . $m[1] . "', '" . bin2hex( random_bytes( 32 ) ) . "' );"; }, $text );
		file_put_contents( $config, $text );
	}
	$network = is_multisite();
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	// La petición con la que «Create Backup» de Backuply arranca su primer tramo responde 200 sin hacerse (el ensayo
	// hace avanzar los tramos con backuply-run.php), así su ventana de progreso sigue como en un sitio real.
	$cut = "<?php\n// Ensayo de pantallas: sin loopback (un solo proceso) y sin correo.\n"
		. "add_filter( 'pre_http_request', static function ( \$pre, \$args, \$url ) { \$host = (string) parse_url( (string) \$url, PHP_URL_HOST ); if ( ! in_array( \$host, array( parse_url( home_url(), PHP_URL_HOST ), '127.0.0.1', 'localhost' ), true ) ) return \$pre; if ( false !== strpos( (string) \$url, 'action=backuply_handle_backup' ) ) return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null ); return new WP_Error( 'digi_no_loopback', 'Loopback cortado en el ensayo.' ); }, 10, 3 );\n"
		. "add_filter( 'pre_wp_mail', '__return_true' );\n";
	if ( false === file_put_contents( WPMU_PLUGIN_DIR . '/digi-screens-no-loopback.php', $cut ) ) throw new RuntimeException( 'No se escribió el mu-plugin sin loopback.' );
	if ( 'backuply' === $mode ) {
		copy( $dir . '/backuply-shim.php', WPMU_PLUGIN_DIR . '/digi-backuply-shim.php' );
		$plugins = array( 'backuply/backuply.php' );
	} else {
		$plugins = array( 'digitalisimo-backups/digitalisimo-backups.php' );
	}
	foreach ( $plugins as $plugin ) {
		$activated = activate_plugin( $plugin, '', $network );
		if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $activated->get_error_message() );
	}
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	wp_set_current_user( $admin->ID );
	$content = function( $title ) {
		$image = imagecreatetruecolor( 64, 64 );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 30, 90, 200 ) );
		ob_start();
		imagepng( $image );
		$upload = wp_upload_bits( 'pantallas-' . get_current_blog_id() . '.png', null, ob_get_clean() );
		if ( ! empty( $upload['error'] ) ) throw new RuntimeException( 'No se subió la imagen: ' . $upload['error'] );
		$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => '<p>Contenido de prueba.</p><p><img src="' . esc_url( $upload['url'] ) . '"></p>' ) );
		if ( is_wp_error( $id ) || ! $id ) throw new RuntimeException( 'No se creó la entrada.' );
		return $id;
	};
	$content( 'Entrada de prueba' );
	$sites = array();
	if ( $network ) {
		$row = get_network();
		$site = wp_insert_site( array( 'domain' => $row->domain, 'path' => $row->path . 'uno/', 'title' => 'Uno', 'user_id' => $admin->ID ) );
		if ( is_wp_error( $site ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $site->get_error_message() );
		switch_to_blog( $site );
		$content( 'Entrada del subsitio' );
		restore_current_blog();
		$sites['uno'] = $site;
	}
	$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-screens' ) );
	if ( is_wp_error( $created ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $created->get_error_message() );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array(
		'mode' => $mode,
		'network' => $network,
		'sites' => $sites,
		'password' => $created[0],
		'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION
			. ( defined( 'BACKUPLY_VERSION' ) ? ' · Backuply ' . BACKUPLY_VERSION : '' )
			. ( defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? ' · Backups ' . DIGITALISIMO_BACKUPS_VERSION : '' ),
	) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
