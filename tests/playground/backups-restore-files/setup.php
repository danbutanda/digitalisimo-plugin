<?php
/**
 * Preparación de los dos sitios del ensayo backups-restore-files.sh (último paso runPHP de cada blueprint). Con
 * DIGI_ROLE=origin: Backups activo, un plugin con CSS y JS en el front (digi-demo-assets), un tema clásico propio
 * (tema-origen), una imagen, una página interna con enlaces permanentes /%postname%/ y los drop-ins de su servidor
 * (object-cache.php que sólo funciona con /digi-origin-only, como una caché de Redis, y advanced-cache.php). Con
 * DIGI_ROLE=dest: Backups activo, su propio advanced-cache.php y una caché de páginas vieja (WP Super Cache, Divi). En los
 * dos, la regla del ensayo para escribir .htaccess (Playground no es Apache) y WP-Cron por loopback cortado. Deja
 * /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir = '/digi-probe';
$role = getenv( 'DIGI_ROLE' ) ?: 'origin';
try {
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	// En los dos sitios (los mu-plugins del origen llegan al destino con el respaldo): WP-Cron sin loopback y la regla de
	// .htaccess del ensayo (los ticks cargan los mu-plugins: DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS en wp-config.php).
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-restore-files.php', "<?php\nadd_filter( 'pre_http_request', static function ( \$pre, \$args, \$url ) { return false !== strpos( (string) \$url, 'wp-cron.php' ) ? new WP_Error( 'digi_no_cron', 'WP-Cron por loopback cortado en el ensayo.' ) : \$pre; }, 10, 3 );\nadd_filter( 'digitalisimo_backups_restore_htaccess', '__return_true' );\n" );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	wp_set_current_user( $admin->ID );
	$out = array( 'role' => $role, 'home' => home_url(), 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION );
	if ( 'origin' === $role ) {
		$plugin = WP_PLUGIN_DIR . '/digi-demo-assets';
		wp_mkdir_p( $plugin . '/assets' );
		file_put_contents( $plugin . '/digi-demo-assets.php', "<?php\n/* Plugin Name: Demo de recursos */\nadd_action( 'wp_enqueue_scripts', static function () { wp_enqueue_style( 'digi-demo', plugins_url( 'assets/demo.css', __FILE__ ), array(), '1' ); wp_enqueue_script( 'digi-demo', plugins_url( 'assets/demo.js', __FILE__ ), array(), '1', true ); } );\n" );
		file_put_contents( $plugin . '/assets/demo.css', "body{background-color:rgb(1,2,3)}\n" );
		file_put_contents( $plugin . '/assets/demo.js', "window.digiDemo=1;\n" );
		$activated = activate_plugin( 'digi-demo-assets/digi-demo-assets.php' );
		if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó el plugin de recursos: ' . $activated->get_error_message() );
		$theme = get_theme_root() . '/tema-origen';
		wp_mkdir_p( $theme );
		file_put_contents( $theme . '/style.css', "/*\nTheme Name: Tema del origen\n*/\n.tema-origen{color:rgb(4,5,6)}\n" );
		file_put_contents( $theme . '/functions.php', "<?php\nadd_action( 'wp_enqueue_scripts', static function () { wp_enqueue_style( 'tema-origen', get_stylesheet_uri(), array(), '1' ); } );\n" );
		file_put_contents( $theme . '/index.php', "<!doctype html><html><head><?php wp_head(); ?></head><body class=\"tema-origen\"><?php while ( have_posts() ) { the_post(); echo '<h1>' . esc_html( get_the_title() ) . '</h1>'; the_content(); } wp_footer(); ?></body></html>\n" );
		// Como «Activar» en Apariencia, sin el gancho de cambio de tema (es sólo el ensayo).
		update_option( 'template', 'tema-origen' );
		update_option( 'stylesheet', 'tema-origen' );
		$image = imagecreatetruecolor( 32, 32 );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 200, 30, 90 ) );
		ob_start();
		imagepng( $image );
		$upload = wp_upload_bits( 'foto-origen.png', null, ob_get_clean() );
		if ( ! empty( $upload['error'] ) ) throw new RuntimeException( 'No se subió la imagen: ' . $upload['error'] );
		update_option( 'permalink_structure', '/%postname%/' );
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página interna', 'post_name' => 'pagina-interna', 'post_content' => '<p>Contenido interno</p><img id="digi-foto" src="' . esc_url( $upload['url'] ) . '" alt="">' ) );
		if ( ! $id || is_wp_error( $id ) ) throw new RuntimeException( 'No se creó la página interna.' );
		flush_rewrite_rules( false );
		// Los drop-ins del servidor de origen.
		file_put_contents( WP_CONTENT_DIR . '/object-cache.php', "<?php\n// Caché de objetos del servidor de origen: sin su servidor (aquí, /digi-origin-only) cada petición muere.\nif ( ! is_file( '/digi-origin-only/ok' ) ) { header( 'HTTP/1.1 500 Internal Server Error' ); die( 'DROPIN-DEL-ORIGEN' ); }\n" );
		file_put_contents( WP_CONTENT_DIR . '/advanced-cache.php', "<?php // advanced-cache.php del ORIGEN\n" );
		$out['image'] = wp_parse_url( $upload['url'], PHP_URL_PATH );
		$out['page'] = wp_parse_url( get_permalink( $id ), PHP_URL_PATH );
	} else {
		file_put_contents( WP_CONTENT_DIR . '/advanced-cache.php', "<?php // advanced-cache.php de ESTE servidor\n" );
		wp_mkdir_p( WP_CONTENT_DIR . '/cache/supercache/destino/pagina-interna' );
		file_put_contents( WP_CONTENT_DIR . '/cache/supercache/destino/pagina-interna/index.html', '<html>PAGINA-VIEJA-EN-CACHE</html>' );
		file_put_contents( WP_CONTENT_DIR . '/cache/.htaccess', "Options -Indexes\n" );
		wp_mkdir_p( WP_CONTENT_DIR . '/et-cache/1' );
		file_put_contents( WP_CONTENT_DIR . '/et-cache/1/et-core-unified-1.min.css', 'body{}' );
		@unlink( ABSPATH . '.htaccess' );
	}
	$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-restore-files-' . $role ) );
	if ( is_wp_error( $created ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $created->get_error_message() );
	$out['password'] = $created[0];
	file_put_contents( $dir . '/setup.json', wp_json_encode( $out ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
