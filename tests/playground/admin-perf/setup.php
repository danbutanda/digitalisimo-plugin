<?php
/**
 * Sitio de la auditoría del escritorio: contenido realista (entradas, páginas, medios, usuarios, comentarios),
 * páginas y plantillas hechas con el constructor, un plugin de prueba con una actualización local, la sonda
 * (probe.php) como mu-plugin y DIGITALÍSIMO Elements + Migración activos (en la red, activados en toda la red).
 * Antes de activar deja la foto de las opciones con carga automática y de WP-Cron (autoload-0.json, cron-0.json) y
 * después la de la activación (autoload-1.json, cron-1.json). Escribe ids.json y ready.txt, o setup-error.txt.
 */
require ( getenv( 'DIGI_AP_WP' ) ? getenv( 'DIGI_AP_WP' ) : '/wordpress' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$dir   = WP_CONTENT_DIR . '/probe';
$sizes = json_decode( (string) @file_get_contents( $dir . '/options.json' ), true ) ?: array();
$n     = static function ( $key, $default ) use ( $sizes ) {
	return (int) ( $sizes[ $key ] ?? $default );
};
try {
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		mkdir( WPMU_PLUGIN_DIR, 0777, true );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ap-probe.php', '<?php require WP_CONTENT_DIR . "/probe/probe.php";' );
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-fatal-log.php', '<?php register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( WP_CONTENT_DIR . "/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "" ) . "\n" . $e["message"] . " @ " . $e["file"] . ":" . $e["line"] . "\n\n", FILE_APPEND ); } } );' );
	require_once $dir . '/probe.php';

	global $wpdb;
	wp_defer_term_counting( true );
	wp_defer_comment_counting( true );
	$wpdb->query( 'START TRANSACTION' );
	$ids = array( 'posts' => array(), 'pages' => array(), 'media' => array(), 'users' => array(), 'builder' => array() );

	// Usuarios.
	foreach ( range( 1, $n( 'users', 40 ) ) as $i ) {
		$role           = $i % 10 === 0 ? 'editor' : ( $i % 3 === 0 ? 'author' : 'subscriber' );
		$ids['users'][] = wp_insert_user( array( 'user_login' => 'usuario' . $i, 'user_pass' => 'clave-de-prueba-' . $i, 'user_email' => 'usuario' . $i . '@ejemplo.test', 'role' => $role, 'display_name' => 'Usuario ' . $i ) );
	}
	// Categorías y etiquetas.
	$cats = array();
	foreach ( range( 1, 10 ) as $i ) {
		$t      = wp_insert_term( 'Categoría ' . $i, 'category' );
		$cats[] = is_array( $t ) ? (int) $t['term_id'] : 1;
	}
	$tags = array();
	foreach ( range( 1, 30 ) as $i ) {
		$tags[] = 'etiqueta-' . $i;
	}
	// Medios: PNG pequeños hechos con GD (o uno fijo si no hay GD).
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAGklEQVR4nGNkaGBgIA0wkah+VMOohlENQ0kDAP+8ARHVPDlXAAAAAElFTkSuQmCC' );
	foreach ( range( 1, $n( 'media', 120 ) ) as $i ) {
		$bytes = $png;
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$im = imagecreatetruecolor( 200, 120 );
			imagefill( $im, 0, 0, imagecolorallocate( $im, ( $i * 37 ) % 255, ( $i * 71 ) % 255, ( $i * 13 ) % 255 ) );
			ob_start();
			imagepng( $im );
			$bytes = (string) ob_get_clean();
			imagedestroy( $im );
		}
		$up = wp_upload_bits( 'imagen-' . $i . '.png', null, $bytes );
		if ( ! empty( $up['error'] ) ) {
			throw new RuntimeException( 'Medio: ' . $up['error'] );
		}
		$aid = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Imagen ' . $i, 'post_status' => 'inherit' ), $up['file'] );
		wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $up['file'] ) );
		$ids['media'][] = $aid;
	}
	// Entradas.
	$authors = array_values( array_filter( $ids['users'], 'is_int' ) );
	$lorem   = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a ante venenatis dapibus posuere velit aliquet. ';
	foreach ( range( 1, $n( 'posts', 400 ) ) as $i ) {
		$content = '';
		foreach ( range( 1, 6 ) as $p ) {
			$content .= "<!-- wp:paragraph -->\n<p>" . str_repeat( $lorem, 2 ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		}
		$id = wp_insert_post( array(
			'post_title'    => 'Entrada de prueba ' . $i,
			'post_content'  => $content,
			'post_status'   => $i % 25 === 0 ? 'draft' : 'publish',
			'post_author'   => $authors ? $authors[ $i % count( $authors ) ] : 1,
			'post_category' => array( $cats[ $i % count( $cats ) ] ),
			'tags_input'    => array( $tags[ $i % 30 ], $tags[ ( $i * 7 ) % 30 ] ),
			'post_date'     => gmdate( 'Y-m-d H:i:s', time() - $i * 3600 * 7 ),
		) );
		if ( $ids['media'] ) {
			set_post_thumbnail( $id, $ids['media'][ $i % count( $ids['media'] ) ] );
		}
		$ids['posts'][] = $id;
	}
	// Comentarios.
	foreach ( range( 1, $n( 'comments', 200 ) ) as $i ) {
		wp_insert_comment( array( 'comment_post_ID' => $ids['posts'][ $i % count( $ids['posts'] ) ], 'comment_content' => 'Comentario de prueba ' . $i, 'comment_author' => 'Visitante ' . $i, 'comment_author_email' => 'visitante' . $i . '@ejemplo.test', 'comment_approved' => $i % 5 ? 1 : 0 ) );
	}
	// Páginas (sin constructor).
	foreach ( range( 1, $n( 'pages', 60 ) ) as $i ) {
		$ids['pages'][] = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Página ' . $i, 'post_content' => "<!-- wp:paragraph -->\n<p>" . $lorem . "</p>\n<!-- /wp:paragraph -->", 'post_status' => 'publish' ) );
	}
	$wpdb->query( 'COMMIT' );
	wp_defer_term_counting( false );
	wp_defer_comment_counting( false );

	// Plugin de prueba con una actualización local (la sonda sirve el ZIP y la ofrece en update_plugins).
	$dummy = WP_PLUGIN_DIR . '/digi-ap-dummy';
	if ( ! is_dir( $dummy ) ) {
		mkdir( $dummy, 0777, true );
	}
	$header = static function ( $v ) {
		return "<?php\n/**\n * Plugin Name: Plugin de prueba de la auditoría\n * Version: $v\n * Description: Sólo para medir «actualizar plugins».\n */\n";
	};
	file_put_contents( $dummy . '/digi-ap-dummy.php', $header( '1.0' ) );
	file_put_contents( $dir . '/digi-ap-dummy-1.php', $header( '1.0' ) );
	if ( class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		$zip->open( $dir . '/digi-ap-dummy-2.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString( 'digi-ap-dummy/digi-ap-dummy.php', $header( '2.0' ) );
		$zip->close();
	}

	// Foto antes de activar los plugins auditados.
	file_put_contents( $dir . '/autoload-0.json', wp_json_encode( Digi_AP_Probe::autoload() ) );
	file_put_contents( $dir . '/cron-0.json', wp_json_encode( Digi_AP_Probe::cron() ) );

	$plugins = array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php' );
	if ( is_multisite() ) {
		foreach ( array( 'segundo', 'tercero' ) as $slug ) {
			$site = wpmu_create_blog( get_network()->domain, get_network()->path . $slug . '/', 'Sitio ' . $slug, 1 );
			if ( is_wp_error( $site ) ) {
				throw new RuntimeException( 'Sitio ' . $slug . ': ' . $site->get_error_message() );
			}
			$ids['sites'][] = $site;
		}
	}
	foreach ( $plugins as $plugin ) {
		$result = activate_plugin( $plugin, '', is_multisite() );
		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $plugin . ': ' . $result->get_error_message() );
		}
	}
	if ( ! defined( 'DIGITALISIMO_ELEMENTS_CORE' ) ) {
		throw new RuntimeException( 'DIGITALÍSIMO Elements no quedó activo con su núcleo incluido' );
	}

	// Páginas y plantillas del constructor.
	$doc = static function ( $post_type, $title, $type, array $data, array $extra = array() ) {
		$id = wp_insert_post( array( 'post_type' => $post_type, 'post_status' => 'publish', 'post_title' => $title ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', $type );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		if ( 'elementor_library' === $post_type ) {
			wp_set_object_terms( $id, $type, 'elementor_library_type' );
		}
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		foreach ( $extra as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	};
	$section = static function ( $k, $image ) {
		return array( 'id' => 'c' . $k, 'elType' => 'container', 'settings' => array( 'flex_direction' => 'column' ), 'elements' => array(
			array( 'id' => 'h' . $k, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Sección ' . $k ), 'elements' => array() ),
			array( 'id' => 't' . $k, 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Texto de la sección ' . $k . '. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>' ), 'elements' => array() ),
			array( 'id' => 'i' . $k, 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( 'image' => array( 'id' => $image, 'url' => wp_get_attachment_url( $image ) ) ), 'elements' => array() ),
			array( 'id' => 'b' . $k, 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Botón ' . $k, 'link' => array( 'url' => '#' ) ), 'elements' => array() ),
			array( 'id' => 'x' . $k, 'elType' => 'widget', 'widgetType' => 'icon-list', 'settings' => array( 'icon_list' => array( array( '_id' => 'l' . $k, 'text' => 'Elemento', 'selected_icon' => array( 'value' => 'fas fa-check', 'library' => 'fa-solid' ) ) ) ), 'elements' => array() ),
		) );
	};
	foreach ( range( 1, $n( 'builder', 12 ) ) as $i ) {
		$data = array();
		foreach ( range( 1, 6 ) as $s ) {
			$data[] = $section( sprintf( '%02d%02d', $i, $s ), $ids['media'][ ( $i + $s ) % count( $ids['media'] ) ] );
		}
		$ids['builder'][] = $doc( 'page', 'Página del constructor ' . $i, 'wp-page', $data );
	}
	$ids['templates'][] = $doc( 'elementor_library', 'Plantilla de sección', 'section', array( $section( '9001', $ids['media'][0] ) ) );
	$ids['templates'][] = $doc( 'elementor_library', 'Cabecera', 'header', array( $section( '9002', $ids['media'][1] ) ), array( '_elementor_conditions' => array( 'include/general' ) ) );
	$ids['templates'][] = $doc( 'elementor_library', 'Pie', 'footer', array( $section( '9003', $ids['media'][2] ) ), array( '_elementor_conditions' => array( 'include/general' ) ) );
	$ids['templates'][] = $doc( 'elementor_library', 'Popup', 'popup', array( $section( '9004', $ids['media'][3] ) ) );
	update_option( 'elementor_onboarded', true );
	// Enlaces permanentes «bonitos» (la REST en /wp-json/, como en los sitios).
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules( false );

	// Foto después de activar.
	file_put_contents( $dir . '/autoload-1.json', wp_json_encode( Digi_AP_Probe::autoload() ) );
	file_put_contents( $dir . '/cron-1.json', wp_json_encode( Digi_AP_Probe::cron() ) );
	$ids['post_edit'] = $ids['posts'][3];
	file_put_contents( $dir . '/ids.json', wp_json_encode( $ids ) );
	file_put_contents( $dir . '/ready.txt', 'ok' );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
