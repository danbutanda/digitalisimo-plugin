<?php
/**
 * Preparación de admin-perf-all.sh (paso runPHP del blueprint). Contenido realista antes de activar nada (300 entradas con
 * categorías, etiquetas y comentarios, 40 páginas, 30 usuarios y 40 imágenes con sus tamaños), una foto del estado sin
 * los plugins (autoload y WP-Cron), y después los siete plugins (en una red, activados en toda la red) y el tema
 * DIGITALÍSIMO activos en la base; la sonda (probe.php, mu-plugin) elige en cada petición qué conjunto carga. Deja
 * un plugin de prueba (digi-ap-dummy 1.0) con una actualización 1.1 ofrecida, para «Actualizar» en Plugins.
 * Resultado en /wordpress/wp-content/probe/setup.json; un fallo, en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$dir = '/wordpress/wp-content/probe';
try {
	// Claves propias en wp-config.php, como un sitio real (con las de ejemplo, Backups avisa de que no puede cifrar).
	$config = ABSPATH . 'wp-config.php';
	if ( is_file( $config ) && is_writable( $config ) ) {
		$text = (string) file_get_contents( $config );
		$text = preg_replace_callback( "/define\\(\\s*'(AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY)'\\s*,\\s*'put your unique phrase here'\\s*\\);/", function ( $m ) { return "define( '" . $m[1] . "', '" . bin2hex( random_bytes( 32 ) ) . "' );"; }, $text );
		file_put_contents( $config, $text );
	}
	$network = is_multisite();
	// Playground no trae wp-content/mu-plugins: sin ella, la sonda y la oferta del plugin de prueba no se escribirían.
	if ( ! wp_mkdir_p( WPMU_PLUGIN_DIR ) ) throw new RuntimeException( 'No se pudo crear ' . WPMU_PLUGIN_DIR );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	wp_set_current_user( $admin->ID );
	mt_srand( 20261118 );

	// Usuarios.
	$authors = array( $admin->ID );
	$roles = array( 'editor', 'author', 'author', 'contributor', 'subscriber', 'subscriber' );
	for ( $i = 1; $i <= 30; $i++ ) {
		$id = wp_insert_user( array( 'user_login' => 'usuario' . $i, 'user_pass' => wp_generate_password( 20 ), 'user_email' => 'usuario' . $i . '@example.test', 'display_name' => 'Usuario ' . $i, 'role' => $roles[ $i % count( $roles ) ] ) );
		if ( is_wp_error( $id ) ) throw new RuntimeException( 'Usuario: ' . $id->get_error_message() );
		if ( in_array( $roles[ $i % count( $roles ) ], array( 'editor', 'author' ), true ) ) $authors[] = $id;
	}

	// Taxonomías.
	$cats = array();
	for ( $i = 1; $i <= 10; $i++ ) { $t = wp_insert_term( 'Categoría ' . $i, 'category' ); if ( ! is_wp_error( $t ) ) $cats[] = (int) $t['term_id']; }
	$tags = array();
	for ( $i = 1; $i <= 30; $i++ ) $tags[] = 'etiqueta-' . $i;

	// Imágenes (antes que las entradas, para usarlas como destacadas).
	$images = array();
	for ( $i = 1; $i <= 40; $i++ ) {
		$im = imagecreatetruecolor( 1200, 800 );
		for ( $r = 0; $r < 24; $r++ ) imagefilledrectangle( $im, mt_rand( 0, 1100 ), mt_rand( 0, 700 ), mt_rand( 100, 1200 ), mt_rand( 100, 800 ), imagecolorallocate( $im, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) );
		ob_start();
		imagejpeg( $im, null, 82 );
		$bits = wp_upload_bits( 'imagen-' . $i . '.jpg', null, ob_get_clean() );
		if ( ! empty( $bits['error'] ) ) throw new RuntimeException( 'Imagen: ' . $bits['error'] );
		$att = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Imagen ' . $i, 'post_status' => 'inherit', 'post_author' => $authors[ $i % count( $authors ) ] ), $bits['file'] );
		wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $bits['file'] ) );
		update_post_meta( $att, '_wp_attachment_image_alt', 'Imagen de prueba ' . $i );
		$images[] = $att;
	}

	// Entradas con bloques, autores, categorías, etiquetas, destacada y comentarios.
	$words = explode( ' ', 'respaldo sitio red página entrada contenido imagen servidor plugin escritorio rendimiento consulta medios usuarios ajustes actualización tema bloque editor lista código herramienta sitio web dominio correo cliente' );
	$sentence = function ( $n ) use ( $words ) { $s = array(); for ( $i = 0; $i < $n; $i++ ) $s[] = $words[ mt_rand( 0, count( $words ) - 1 ) ]; return ucfirst( implode( ' ', $s ) ) . '.'; };
	$posts = array();
	for ( $i = 1; $i <= 300; $i++ ) {
		$content = '';
		for ( $p = 0; $p < 6; $p++ ) $content .= "<!-- wp:paragraph -->\n<p>" . $sentence( 18 ) . ' ' . $sentence( 14 ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		$content .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . $sentence( 4 ) . "</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item --><li>" . $sentence( 5 ) . "</li><!-- /wp:list-item --></ul>\n<!-- /wp:list -->";
		$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 0 === $i % 25 ? 'draft' : 'publish', 'post_title' => 'Entrada ' . $i . ': ' . $sentence( 5 ), 'post_content' => $content, 'post_excerpt' => $sentence( 12 ), 'post_author' => $authors[ $i % count( $authors ) ], 'post_date' => gmdate( 'Y-m-d H:i:s', time() - $i * 7200 ), 'post_category' => array( $cats[ $i % count( $cats ) ], $cats[ ( $i * 3 ) % count( $cats ) ] ), 'tags_input' => array( $tags[ $i % 30 ], $tags[ ( $i * 7 ) % 30 ] ) ), true );
		if ( is_wp_error( $id ) ) throw new RuntimeException( 'Entrada: ' . $id->get_error_message() );
		if ( 0 === $i % 2 ) set_post_thumbnail( $id, $images[ $i % count( $images ) ] );
		$posts[] = $id;
	}
	for ( $i = 1; $i <= 200; $i++ ) wp_insert_comment( array( 'comment_post_ID' => $posts[ $i % 60 ], 'comment_author' => 'Lector ' . $i, 'comment_author_email' => 'lector' . $i . '@example.test', 'comment_content' => $sentence( 16 ), 'comment_approved' => 0 === $i % 10 ? 0 : 1 ) );
	for ( $i = 1; $i <= 40; $i++ ) {
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página ' . $i, 'post_content' => "<!-- wp:paragraph -->\n<p>" . $sentence( 30 ) . "</p>\n<!-- /wp:paragraph -->", 'post_parent' => $i > 10 ? 0 : 0, 'menu_order' => $i ), true );
		if ( is_wp_error( $id ) ) throw new RuntimeException( 'Página: ' . $id->get_error_message() );
	}

	// Subsitios de la red, con un poco de contenido propio.
	$sites = array();
	if ( $network ) {
		foreach ( array( 'uno', 'dos' ) as $slug ) {
			$blog = wpmu_create_blog( (string) get_network()->domain, get_network()->path . $slug . '/', 'Sitio ' . $slug, $admin->ID );
			if ( is_wp_error( $blog ) ) throw new RuntimeException( 'Subsitio: ' . $blog->get_error_message() );
			switch_to_blog( $blog );
			for ( $i = 1; $i <= 20; $i++ ) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada ' . $slug . ' ' . $i, 'post_content' => "<!-- wp:paragraph -->\n<p>" . $sentence( 20 ) . "</p>\n<!-- /wp:paragraph -->" ) );
			restore_current_blog();
			$sites[ $slug ] = (int) $blog;
		}
	}

	// Foto del estado sin los plugins.
	$snapshot = function () {
		global $wpdb;
		$autoload = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : array( 'yes' );
		$rows = $wpdb->get_results( "SELECT option_name, LENGTH(option_value) AS size, autoload FROM {$wpdb->options}", ARRAY_A );
		$out = array();
		foreach ( $rows as $row ) $out[ $row['option_name'] ] = array( (int) $row['size'], in_array( $row['autoload'], $autoload, true ) );
		$cron = array();
		foreach ( (array) _get_cron_array() as $hooks ) foreach ( (array) $hooks as $hook => $events ) $cron[ $hook ] = true;
		return array( 'options' => $out, 'cron' => array_keys( $cron ) );
	};
	file_put_contents( $dir . '/baseline.json', wp_json_encode( $snapshot() ) );

	// Los siete plugins y el tema.
	$plugins = array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-hosting/digitalisimo-hosting.php', 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-migration/digitalisimo-migration.php' );
	foreach ( $plugins as $plugin ) {
		$result = activate_plugin( $plugin, '', $network );
		if ( is_wp_error( $result ) ) throw new RuntimeException( $plugin . ': ' . $result->get_error_message() );
	}
	if ( $network ) {
		$allowed = (array) get_site_option( 'allowedthemes', array() );
		$allowed['digitalisimo-theme'] = true;
		update_site_option( 'allowedthemes', $allowed );
	}
	switch_theme( 'digitalisimo-theme' );
	update_option( 'elementor_onboarded', true );
	delete_transient( 'elementor_activation_redirect' );
	delete_option( 'elementor_activation_redirect' );

	// Plugin de prueba con una actualización ofrecida (la sonda sirve el ZIP de la 1.1 sin red).
	$dummy = WP_PLUGIN_DIR . '/digi-ap-dummy';
	wp_mkdir_p( $dummy );
	$header = function ( $version ) { return "<?php\n/**\n * Plugin Name: Plugin de prueba admin-perf\n * Version: " . $version . "\n */\n"; };
	file_put_contents( $dummy . '/digi-ap-dummy.php', $header( '1.0' ) );
	if ( class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		if ( true === $zip->open( $dir . '/digi-ap-dummy.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			$zip->addFromString( 'digi-ap-dummy/digi-ap-dummy.php', $header( '1.1' ) );
			$zip->close();
		}
	}
	wp_clean_plugins_cache( false );
	$result = activate_plugin( 'digi-ap-dummy/digi-ap-dummy.php', '', $network );
	if ( is_wp_error( $result ) ) throw new RuntimeException( 'digi-ap-dummy: ' . $result->get_error_message() );
	if ( ! is_file( $dir . '/digi-ap-dummy.zip' ) ) throw new RuntimeException( 'No se creó el ZIP del plugin de prueba.' );
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ap-dummy-offer.php', "<?php\n// Ensayo admin-perf: actualización ofrecida del plugin de prueba.\nforeach ( array( 'site_transient_update_plugins', 'pre_set_site_transient_update_plugins' ) as \$digi_ap_h ) add_filter( \$digi_ap_h, static function ( \$t ) { if ( ! is_object( \$t ) ) return \$t; if ( ! defined( 'DIGI_AP_DUMMY_DONE' ) && is_file( WP_PLUGIN_DIR . '/digi-ap-dummy/digi-ap-dummy.php' ) && false !== strpos( (string) file_get_contents( WP_PLUGIN_DIR . '/digi-ap-dummy/digi-ap-dummy.php' ), 'Version: 1.0' ) ) { if ( ! isset( \$t->response ) || ! is_array( \$t->response ) ) \$t->response = array(); \$t->response['digi-ap-dummy/digi-ap-dummy.php'] = (object) array( 'slug' => 'digi-ap-dummy', 'plugin' => 'digi-ap-dummy/digi-ap-dummy.php', 'new_version' => '1.1', 'url' => '', 'package' => 'https://downloads.example.test/digi-ap-dummy.1.1.zip' ); } return \$t; }, 99 );\n" );

	// Índice de Releases servido por la sonda: las versiones instaladas (ninguna actualización de los plugins medidos).
	$manifest = array( 'tag' => 'v2026.11.18.999', 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.11.18.999', 'generated' => gmdate( 'Y-m-d\TH:i:s\Z' ), 'plugins' => array(), 'themes' => array() );
	$slugs = array( 'digitalisimo-elements/pro-elements.php' => 'digitalisimo-elements', 'digitalisimo-seo/digitalisimo-integrations.php' => 'digitalisimo-seo', 'digitalisimo.chatbot/digitalisimo-chatbot.php' => 'digitalisimo-ia-tools', 'digitalisimo-hosting/digitalisimo-hosting.php' => 'digitalisimo-hosting', 'digitalisimo-backups/digitalisimo-backups.php' => 'digitalisimo-backups', 'digitalisimo-tools/digitalisimo-tools.php' => 'digitalisimo-tools', 'digitalisimo-migration/digitalisimo-migration.php' => 'digitalisimo-migration' );
	foreach ( $slugs as $file => $slug ) {
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
		$manifest['plugins'][ $slug ] = array( 'version' => $data['Version'], 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.18.999/' . $slug . '-' . $data['Version'] . '.zip' );
	}
	$theme = wp_get_theme( 'digitalisimo-theme' );
	$manifest['themes']['digitalisimo-theme'] = array( 'version' => $theme->get( 'Version' ), 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.11.18.999/digitalisimo-theme-' . $theme->get( 'Version' ) . '.zip', 'requires' => '6.0', 'requires_php' => '7.4' );
	file_put_contents( $dir . '/manifest.json', wp_json_encode( $manifest ) );

	// La sonda como mu-plugin (desde la petición siguiente).
	if ( ! file_put_contents( WPMU_PLUGIN_DIR . '/digi-ap-probe.php', "<?php require '/wordpress/wp-content/probe/probe.php';\n" ) ) throw new RuntimeException( 'No se escribió la sonda.' );
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'network' => $network, 'sites' => $sites, 'posts' => count( $posts ), 'images' => count( $images ), 'php' => PHP_VERSION, 'wp' => get_bloginfo( 'version' ) ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
