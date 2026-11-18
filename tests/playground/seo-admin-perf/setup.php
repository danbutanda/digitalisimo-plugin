<?php
/**
 * Preparación de seo-admin-perf.sh (pasos runPHP del blueprint; DIGI_AP_STEP elige el paso):
 *   prepare  SAVEQUERIES sólo en las peticiones con X-Digi-AP-Detail, sonda en mu-plugins, foto del autoload y del
 *            cron SIN los plugins medidos, activación de SEO, IA Tools y Hosting (en la red, en toda la red y con dos
 *            subsitios) y, con /digi-ap/options.txt = on, las funciones opcionales que más trabajo hacen;
 *   seed     contenido realista con los plugins ya cargados (sus ganchos de guardado corren como en un sitio real):
 *            usuarios, categorías, etiquetas, medios con sus tamaños, entradas con enlaces internos, imágenes y
 *            keywords, páginas y comentarios; deja /digi-ap/setup.json.
 * Un fallo queda en /digi-ap/setup-error.txt.
 */
$step = getenv( 'DIGI_AP_STEP' );
$dir  = '/digi-ap';
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

function digi_ap_snapshot() {
	global $wpdb;
	$values = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : array( 'yes' );
	$in = "'" . implode( "','", array_map( 'esc_sql', $values ) ) . "'";
	$autoload = array();
	foreach ( (array) $wpdb->get_results( "SELECT option_name, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE autoload IN ($in)", ARRAY_A ) as $row ) $autoload[ $row['option_name'] ] = (int) $row['bytes'];
	$cron = array();
	foreach ( (array) _get_cron_array() as $time => $hooks ) foreach ( (array) $hooks as $hook => $events ) foreach ( (array) $events as $event ) $cron[] = array( 'hook' => $hook, 'schedule' => $event['schedule'] ?? false, 'interval' => $event['interval'] ?? null );
	return array( 'autoload' => $autoload, 'cron' => $cron );
}

try {
	$plugins = array( 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-hosting/digitalisimo-hosting.php' );
	$options_on = is_file( $dir . '/options.txt' ) && 'on' === trim( (string) file_get_contents( $dir . '/options.txt' ) );
	if ( 'prepare' === $step ) {
		$config = ABSPATH . 'wp-config.php';
		$text   = (string) file_get_contents( $config );
		if ( false === strpos( $text, 'DIGI_AP_SAVEQUERIES' ) ) {
			$block = "/* DIGI_AP_SAVEQUERIES: consultas con su llamador sólo en las peticiones de detalle de seo-admin-perf.sh. */\n"
				. "if ( ! defined( 'SAVEQUERIES' ) ) define( 'SAVEQUERIES', ! empty( \$_SERVER['HTTP_X_DIGI_AP_DETAIL'] ) || ! empty( \$_COOKIE['digi_ap_detail'] ) );\n";
			$text = preg_replace( "/(require_once\\s+ABSPATH\\s*\\.\\s*'wp-settings\\.php';)/", $block . '$1', $text, 1, $count );
			if ( 1 !== $count ) throw new RuntimeException( 'No se encontró wp-settings.php en wp-config.php' );
			file_put_contents( $config, $text );
		}
		wp_mkdir_p( WPMU_PLUGIN_DIR );
		if ( ! copy( $dir . '/probe-mu.php', WPMU_PLUGIN_DIR . '/digi-ap-probe.php' ) ) throw new RuntimeException( 'No se copió la sonda' );
		// Enlaces permanentes reales (las reglas de reescritura y url_to_postid trabajan como en un sitio).
		$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
		flush_rewrite_rules( false );
		file_put_contents( $dir . '/before.json', wp_json_encode( digi_ap_snapshot() ) );
		$network = is_multisite();
		if ( $network ) {
			foreach ( array( 'uno', 'dos' ) as $slug ) {
				$id = wpmu_create_blog( parse_url( home_url(), PHP_URL_HOST ), '/' . $slug . '/', 'Sitio ' . $slug, 1 );
				if ( is_wp_error( $id ) ) throw new RuntimeException( 'No se creó /' . $slug . '/: ' . $id->get_error_message() );
				switch_to_blog( $id ); $GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' ); flush_rewrite_rules( false ); restore_current_blog();
			}
		}
		if ( is_file( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
			$woo = activate_plugin( 'woocommerce/woocommerce.php', '', $network );
			if ( is_wp_error( $woo ) ) throw new RuntimeException( 'WooCommerce: ' . $woo->get_error_message() );
		}
		foreach ( $plugins as $plugin ) {
			$result = activate_plugin( $plugin, '', $network );
			if ( is_wp_error( $result ) ) throw new RuntimeException( $plugin . ': ' . $result->get_error_message() );
		}
		if ( $options_on ) {
			// Funciones opcionales que hacen trabajo en el escritorio o al guardar: sitemap de vídeo, monitor 404,
			// comprobación de enlaces rotos, IndexNow y la base de conocimiento de IA Tools.
			$seo = (array) get_option( 'digitalisimo_integrations_options', array() );
			update_option( 'digitalisimo_integrations_options', array_merge( $seo, array( 'seo_sitemap_video_enabled' => 1, 'seo_404_enabled' => 1, 'seo_links_check' => 1, 'seo_ai_indexnow_enabled' => 1, 'seo_ai_indexnow_key' => 'b1a2c3d4e5f60718293a4b5c6d7e8f90' ) ) );
			$ai = (array) get_option( 'digitalisimo_ai_options', array() );
			update_option( 'digitalisimo_ai_options', array_merge( $ai, array( 'knowledge_enabled' => 1, 'knowledge_auto_index' => 1 ) ) );
		}
		file_put_contents( $dir . '/prepared.txt', 'ok' );
		return;
	}

	if ( 'seed' !== $step ) throw new RuntimeException( 'Paso desconocido: ' . $step );
	wp_set_current_user( 1 );
	mt_srand( 20261118 );
	$words = explode( ' ', 'sitio web diseño contenido página negocio cliente servicio producto marketing digital estrategia posicionamiento búsqueda usuario experiencia rendimiento velocidad tienda dominio hosting correo campaña redes sociales análisis datos informe venta compra precio oferta calidad soporte equipo proyecto plan meta objetivo resultado mejora proceso herramienta plataforma integración seguridad respaldo actualización versión móvil escritorio imagen video texto artículo guía consejo ejemplo paso detalle opción ajuste' );
	$keywords = array();
	for ( $i = 0; $i < 60; $i++ ) $keywords[] = $words[ mt_rand( 0, count( $words ) - 1 ) ] . ' ' . $words[ mt_rand( 0, count( $words ) - 1 ) ];
	$sentence = function( $n ) use ( $words ) { $s = array(); for ( $i = 0; $i < $n; $i++ ) $s[] = $words[ mt_rand( 0, count( $words ) - 1 ) ]; return ucfirst( implode( ' ', $s ) ) . '.'; };
	$sites = is_multisite() ? array_map( 'intval', wp_list_pluck( get_sites( array( 'number' => 10 ) ), 'blog_id' ) ) : array( 1 );
	$summary = array( 'sites' => array() );
	foreach ( $sites as $blog ) {
		if ( is_multisite() ) switch_to_blog( $blog );
		$main = 1 === $blog;
		// SCALE (escala.txt) reduce el contenido para una corrida de prueba del arnés; la medida usa 1.
		$scale = is_file( $dir . '/scale.txt' ) ? max( 0.02, min( 1, (float) file_get_contents( $dir . '/scale.txt' ) ) ) : 1;
		$n_posts = (int) ceil( ( $main ? 300 : 30 ) * $scale ); $n_pages = (int) ceil( ( $main ? 60 : 8 ) * $scale ); $n_media = (int) ceil( ( $main ? 120 : 10 ) * $scale ); $n_users = $main ? (int) ceil( 40 * $scale ) : 0;
		$users = array( 1 );
		for ( $i = 0; $i < $n_users; $i++ ) {
			$role = $i < 5 ? 'editor' : ( $i < 15 ? 'author' : 'subscriber' );
			$id = wp_insert_user( array( 'user_login' => 'usuario' . $i, 'user_email' => 'usuario' . $i . '@example.test', 'user_pass' => wp_generate_password( 20 ), 'role' => $role, 'display_name' => 'Usuario ' . $i ) );
			if ( is_wp_error( $id ) ) throw new RuntimeException( 'Usuario: ' . $id->get_error_message() );
			if ( 'subscriber' !== $role ) $users[] = $id;
		}
		$cats = array(); $tags = array();
		for ( $i = 0; $i < 12; $i++ ) { $t = wp_insert_term( 'Categoría ' . $i, 'category' ); if ( ! is_wp_error( $t ) ) $cats[] = (int) $t['term_id']; }
		for ( $i = 0; $i < 40; $i++ ) { $t = wp_insert_term( 'etiqueta ' . $i, 'post_tag' ); if ( ! is_wp_error( $t ) ) $tags[] = (int) $t['term_id']; }
		$media = array();
		for ( $i = 0; $i < $n_media; $i++ ) {
			$img = imagecreatetruecolor( 400, 300 );
			imagefill( $img, 0, 0, imagecolorallocate( $img, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) );
			imagefilledellipse( $img, mt_rand( 50, 350 ), mt_rand( 50, 250 ), 120, 90, imagecolorallocate( $img, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) );
			ob_start(); imagejpeg( $img, null, 80 ); $bytes = ob_get_clean();
			$up = wp_upload_bits( 'imagen-' . $blog . '-' . $i . '.jpg', null, $bytes );
			if ( ! empty( $up['error'] ) ) throw new RuntimeException( 'Medio: ' . $up['error'] );
			$att = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Imagen ' . $i, 'post_status' => 'inherit', 'post_author' => $users[ array_rand( $users ) ] ), $up['file'] );
			wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $up['file'] ) );
			if ( 0 === $i % 3 ) update_post_meta( $att, '_wp_attachment_image_alt', 'Imagen de ' . $words[ mt_rand( 0, count( $words ) - 1 ) ] );
			$media[] = $att;
		}
		$posts = array();
		for ( $i = 0; $i < $n_posts; $i++ ) {
			$body = '';
			$sections = mt_rand( 2, 4 );
			for ( $s = 0; $s < $sections; $s++ ) {
				$body .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $sentence( 5 ) ) . "</h2>\n<!-- /wp:heading -->\n\n";
				for ( $p = 0; $p < mt_rand( 2, 4 ); $p++ ) {
					$text = esc_html( $sentence( mt_rand( 30, 70 ) ) );
					if ( $posts && 0 === $p ) { $to = $posts[ array_rand( $posts ) ]; $text .= ' <a href="' . esc_url( get_permalink( $to ) ) . '">' . esc_html( get_the_title( $to ) ) . '</a>'; }
					if ( 1 === $p ) $text .= ' <a href="https://example.org/recurso-' . mt_rand( 1, 50 ) . '">fuente externa</a>';
					$body .= "<!-- wp:paragraph -->\n<p>" . $text . "</p>\n<!-- /wp:paragraph -->\n\n";
				}
				if ( $media && 0 === $s ) { $m = $media[ array_rand( $media ) ]; $body .= '<!-- wp:image {"id":' . $m . '} -->' . "\n" . '<figure class="wp-block-image"><img src="' . esc_url( wp_get_attachment_url( $m ) ) . '" alt="" class="wp-image-' . $m . '"/></figure>' . "\n<!-- /wp:image -->\n\n"; }
			}
			$date = gmdate( 'Y-m-d H:i:s', time() - mt_rand( 3600, 3 * 365 * DAY_IN_SECONDS ) );
			$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => $i % 25 === 0 ? 'draft' : 'publish', 'post_title' => rtrim( $sentence( mt_rand( 4, 8 ) ), '.' ), 'post_content' => $body, 'post_author' => $users[ array_rand( $users ) ], 'post_date' => $date, 'post_category' => array( $cats[ array_rand( $cats ) ] ), 'tags_input' => array( $tags[ array_rand( $tags ) ], $tags[ array_rand( $tags ) ] ) ), true );
			if ( is_wp_error( $id ) ) throw new RuntimeException( 'Entrada: ' . $id->get_error_message() );
			if ( $media ) set_post_thumbnail( $id, $media[ array_rand( $media ) ] );
			if ( mt_rand( 1, 10 ) <= 7 ) update_post_meta( $id, 'digitalisimo_seo_keywords', $keywords[ array_rand( $keywords ) ] . ', ' . $keywords[ array_rand( $keywords ) ] );
			$posts[] = $id;
		}
		// Clusters: diez pilares y la mitad de las entradas publicadas apuntando a uno (columna «Cluster», edición masiva).
		$pillars = array_slice( $posts, 0, max( 1, (int) ceil( 10 * $scale ) ) );
		foreach ( $pillars as $pillar ) update_post_meta( $pillar, 'digitalisimo_seo_pillar', 'on' );
		foreach ( $posts as $k => $id ) if ( 0 === $k % 2 && ! in_array( $id, $pillars, true ) ) update_post_meta( $id, '_related_pillar', $pillars[ array_rand( $pillars ) ] );
		$pages = array();
		for ( $i = 0; $i < $n_pages; $i++ ) {
			$parent = ( $pages && 0 === $i % 4 ) ? $pages[ array_rand( $pages ) ] : 0;
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parent, 'post_title' => 'Página ' . $i . ' ' . rtrim( $sentence( 3 ), '.' ), 'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html( $sentence( 80 ) ) . "</p>\n<!-- /wp:paragraph -->" ), true );
			if ( is_wp_error( $id ) ) throw new RuntimeException( 'Página: ' . $id->get_error_message() );
			$pages[] = $id;
		}
		$published = array_values( array_filter( $posts, function( $id ) { return 'publish' === get_post_status( $id ); } ) );
		for ( $i = 0; $i < (int) ceil( ( $main ? 300 : 20 ) * $scale ); $i++ ) {
			wp_insert_comment( array( 'comment_post_ID' => $published[ array_rand( $published ) ], 'comment_author' => 'Lector ' . $i, 'comment_author_email' => 'lector' . $i . '@example.test', 'comment_content' => $sentence( 20 ), 'comment_approved' => $i % 7 ? 1 : 0 ) );
		}
		$summary['sites'][ $blog ] = array( 'home' => home_url( '/' ), 'admin' => admin_url( '/' ), 'post' => $published[ (int) ( count( $published ) / 2 ) ], 'page' => $pages[0], 'attachment' => $media ? $media[0] : 0, 'posts' => count( $posts ), 'pages' => count( $pages ), 'media' => count( $media ) );
		if ( is_multisite() ) restore_current_blog();
	}
	// Imagen que el conductor sube por la REST (1200×800: WordPress genera sus tamaños intermedios, como una foto real).
	$img = imagecreatetruecolor( 1200, 800 );
	for ( $y = 0; $y < 800; $y += 20 ) imagefilledrectangle( $img, 0, $y, 1199, $y + 19, imagecolorallocate( $img, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) );
	imagejpeg( $img, $dir . '/upload.jpg', 85 );
	$summary['multisite'] = is_multisite();
	$summary['options'] = $options_on ? 'on' : 'default';
	$summary['php'] = PHP_VERSION;
	$summary['wp'] = get_bloginfo( 'version' );
	$summary['woocommerce'] = is_file( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
	file_put_contents( $dir . '/setup.json', wp_json_encode( $summary ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $step . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
