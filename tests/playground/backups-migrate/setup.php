<?php
/**
 * Preparación de cada sitio del ensayo de migración (paso runPHP del blueprint de backups-migrate.sh). Lee
 * /digi-probe/site.json (`role` origen|destino, `password`, `elements`) y deja el resultado en /digi-probe/setup.json
 * (un fallo, en setup-error.txt).
 *
 * - Los dos: Backups activo (en la red, en toda la red), sólo el tema en uso (sitio de prueba reducido: un paquete y
 *   unas copias previas más pequeñas), contraseña propia de admin, «Permitir HTTP» (laboratorio: el ensayo es por HTTP
 *   entre dos puertos), sin loopback de WP-Cron (la cola sólo avanza cuando el ensayo lo pide) y un mu-plugin que
 *   resuelve los hosts del arnés (origen.test → 127.0.0.1:<puerto>) para las peticiones salientes, como un DNS de
 *   laboratorio.
 * - Origen: contenido propio (entrada, medio con su archivo, una editora) y, con Elements, una página de Elements cuya
 *   imagen y fondo apuntan a sus medios (su CSS generado lleva la dirección del origen); en la red, el subsitio «uno»
 *   con su entrada y su medio.
 * - Destino: su propio contenido (para comprobar que se reemplaza y que «Deshacer» lo devuelve).
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = '/digi-probe';
try {
	$opts = json_decode( (string) file_get_contents( $dir . '/site.json' ), true );
	$network = is_multisite();
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', $network );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	if ( ! empty( $opts['elements'] ) ) {
		$activated = activate_plugin( 'digitalisimo-elements/pro-elements.php', '', $network );
		if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Elements: ' . $activated->get_error_message() );
	}
	// Sitio de prueba reducido: fuera los temas que no se usan (≈ 14 MB menos en el paquete y en cada copia previa).
	$keep = array( get_stylesheet(), get_template(), defined( 'WP_DEFAULT_THEME' ) ? WP_DEFAULT_THEME : '' );
	foreach ( (array) glob( get_theme_root() . '/*', GLOB_ONLYDIR ) as $theme ) {
		if ( in_array( basename( $theme ), $keep, true ) ) continue;
		$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $items as $item ) $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		rmdir( $theme );
	}
	wp_clean_themes_cache();
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	$mu = "<?php\n// Ensayo de migración de DIGITALÍSIMO Backups: sin loopback de WP-Cron y DNS de laboratorio para los hosts del arnés.\n"
		. "add_filter( 'pre_http_request', static function ( \$pre, \$args, \$url ) {\n"
		. "\tstatic \$busy = false;\n"
		. "\tif ( false !== \$pre || \$busy ) return \$pre;\n"
		. "\tif ( false !== strpos( (string) \$url, 'wp-cron.php' ) ) return new WP_Error( 'digi_migrate_no_cron', 'WP-Cron por loopback cortado en el ensayo.' );\n"
		. "\t\$hosts = json_decode( (string) @file_get_contents( '/digi-probe/hosts.json' ), true );\n"
		. "\t\$host = (string) wp_parse_url( \$url, PHP_URL_HOST );\n"
		. "\tif ( ! is_array( \$hosts ) || ! isset( \$hosts[ \$host ] ) ) return \$pre;\n"
		. "\t\$busy = true;\n"
		. "\ttry {\n"
		. "\t\t\$args['headers'] = (array) ( \$args['headers'] ?? array() ) + array( 'Host' => \$host );\n"
		. "\t\treturn wp_remote_request( preg_replace( '#^(https?://)' . preg_quote( \$host, '#' ) . '(?::\\\\d+)?#', '\${1}' . \$hosts[ \$host ], (string) \$url ), \$args );\n"
		. "\t} finally {\n"
		. "\t\t\$busy = false;\n"
		. "\t}\n"
		. "}, 10, 3 );\n"
		// Paquetes grandes con sodium_compat en php-wasm: el paso del origen puede tardar más de 10 minutos.
		. "add_filter( 'digitalisimo_backups_migration_step_timeout', static function () { return 1800; } );\n";
	if ( false === file_put_contents( WPMU_PLUGIN_DIR . '/digi-migrate-lab.php', $mu ) ) throw new RuntimeException( 'No se escribió el mu-plugin del ensayo.' );
	$admin = get_user_by( 'login', 'admin' );
	wp_set_password( (string) $opts['password'], $admin->ID );
	wp_set_current_user( $admin->ID );
	Digitalisimo_Backups_Migrate::set_allow_http( true );
	update_option( 'blogname', 'origen' === $opts['role'] ? 'Sitio de origen' : 'Sitio de destino' );
	$out = array( 'role' => $opts['role'], 'network' => $network, 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION . ( defined( 'DB_ENGINE' ) ? ' · ' . DB_ENGINE : '' ), 'home' => home_url() );
	$media = static function ( $name, $title ) {
		$upload = wp_upload_dir();
		wp_mkdir_p( $upload['path'] );
		$file = $upload['path'] . '/' . $name;
		// Un PNG de 1×1 con bytes propios del sitio (el comentario tEXt lleva el nombre): se compara byte a byte.
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
		file_put_contents( $file, $png . str_repeat( $title, 64 ) );
		$id = wp_insert_attachment( array( 'post_title' => $title, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $file );
		update_attached_file( $id, $file );
		return array( 'id' => $id, 'url' => wp_get_attachment_url( $id ), 'sha256' => hash_file( 'sha256', $file ) );
	};
	if ( 'origen' === $opts['role'] ) {
		wp_insert_user( array( 'user_login' => 'editora', 'user_pass' => (string) $opts['password'] . '-editora', 'user_email' => 'editora@origen.test', 'role' => 'editor' ) );
		$image = $media( 'foto-origen.png', 'Foto del origen' );
		$post = wp_insert_post( array( 'post_title' => 'Entrada del origen', 'post_content' => '<p>Contenido del origen con <a href="' . esc_url( home_url( '/contacto/' ) ) . '">enlace</a>.</p><img src="' . esc_url( $image['url'] ) . '" alt="foto">', 'post_status' => 'publish' ) );
		$out += array( 'post' => $post, 'image' => $image );
		if ( ! empty( $opts['elements'] ) ) {
			$data = array( array( 'id' => 'a1b2c3d', 'elType' => 'section', 'settings' => array( 'background_background' => 'classic', 'background_image' => array( 'url' => $image['url'], 'id' => $image['id'] ) ), 'elements' => array( array( 'id' => 'b2c3d4e', 'elType' => 'column', 'settings' => array( '_column_size' => 100 ), 'elements' => array(
				array( 'id' => 'c3d4e5f', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Página de Elements del origen', 'title_color' => '#C72F91' ), 'elements' => array() ),
				array( 'id' => 'd4e5f6a', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( 'image' => array( 'url' => $image['url'], 'id' => $image['id'] ) ), 'elements' => array() ),
			) ) ) ) );
			$page = wp_insert_post( array( 'post_title' => 'Página de Elements', 'post_name' => 'pagina-elements', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '' ) );
			update_post_meta( $page, '_elementor_edit_mode', 'builder' );
			update_post_meta( $page, '_elementor_template_type', 'wp-page' );
			update_post_meta( $page, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
			update_post_meta( $page, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			update_option( 'elementor_css_print_method', 'external' );
			$out['page'] = $page;
		}
		if ( $network ) {
			$net = get_network();
			$site = wp_insert_site( array( 'domain' => $net->domain, 'path' => $net->path . 'uno/', 'title' => 'Uno del origen', 'user_id' => $admin->ID ) );
			if ( is_wp_error( $site ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $site->get_error_message() );
			switch_to_blog( $site );
			$sub_image = $media( 'foto-uno.png', 'Foto de uno' );
			$sub_post = wp_insert_post( array( 'post_title' => 'Entrada del subsitio uno', 'post_content' => '<p>Subsitio con <img src="' . esc_url( $sub_image['url'] ) . '"></p>', 'post_status' => 'publish' ) );
			$out += array( 'sub' => array( 'id' => $site, 'home' => home_url(), 'post' => $sub_post, 'image' => $sub_image ) );
			restore_current_blog();
		}
	} else {
		$post = wp_insert_post( array( 'post_title' => 'Entrada propia del destino', 'post_content' => '<p>Antes de migrar.</p>', 'post_status' => 'publish' ) );
		$out['post'] = $post;
	}
	file_put_contents( $dir . '/setup.json', wp_json_encode( $out ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
