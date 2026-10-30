<?php
/**
 * Preparación del sitio de f7b.sh (F7b, frente L). Dos pasos del blueprint, cada uno en su petición:
 *
 * - `DIGI_F7B_PHASE=activate`: instala la sonda (mu-plugin, cargada desde la carpeta montada) y el registro de
 *   errores, activa DIGITALÍSIMO Elements (y WooCommerce con WOO=1), el tema DIGITALÍSIMO y las opciones del sitio
 *   (caché de elementos desactivada: B1, B8 y B9 la encienden), y crea los usuarios de prueba (autor con nombre propio,
 *   suscriptor y editor).
 * - `DIGI_F7B_PHASE=content`: dos imágenes, dos categorías, las entradas de prueba (con autores, categorías y los
 *   campos `precio` y `color`), una privada y un borrador, las consultas guardadas de `cases.json` (con
 *   `Queries\Store::save()`, como la pantalla «Consultas»), y los documentos de `cases.json` con `Document::save()`
 *   como administrador (así corren los ganchos de guardado: banderas, usos, CSS). Los de `raw` se escriben tal cual en
 *   `_elementor_data` (v4 con el experimento apagado). Después, la transición de «Rendimiento» (puerta de PRO
 *   aplicada), el recorrido por lotes de V.6 hasta el final y `ready.txt`.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once __DIR__ . '/lib.php';

function digi_f7b_fail( $message ) {
	file_put_contents( DIGI_F7B_DIR . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

$mode  = digi_f7b_mode();
$phase = (string) getenv( 'DIGI_F7B_PHASE' );

if ( 'activate' === $phase ) {
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		mkdir( WPMU_PLUGIN_DIR, 0777, true );
	}
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-f7b-probe.php', "<?php\n// Sólo Playground (f7b.sh).\nrequire WP_CONTENT_DIR . '/probe/probe.php';\n" );
	file_put_contents(
		WPMU_PLUGIN_DIR . '/digi-f7b-errors.php',
		'<?php
// Sólo Playground (f7b.sh): fatales a fatal.log y avisos de PHP de DIGITALÍSIMO Elements (sin el núcleo) a php-errors.log.
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "cli" ) . " " . $e["message"] . " en " . $e["file"] . ":" . $e["line"] . "\n", FILE_APPEND ); } } );
$GLOBALS["digi_f7b_prev_handler"] = set_error_handler( static function ( $no, $str, $file = "", $line = 0 ) {
	if ( false !== strpos( (string) $file, "/plugins/digitalisimo-elements/" ) && false === strpos( (string) $file, "/digitalisimo-elements/elementor/" ) && ( $no & ( E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED ) ) ) {
		file_put_contents( "/wordpress/wp-content/probe/php-errors.log", ( $_SERVER["REQUEST_URI"] ?? "cli" ) . " [" . $no . "] " . $str . " en " . $file . ":" . $line . "\n", FILE_APPEND );
	}
	$prev = $GLOBALS["digi_f7b_prev_handler"];
	return $prev ? call_user_func( $prev, $no, $str, $file, $line ) : false;
} );
'
	);
	$plugins = array( 'digitalisimo-elements/pro-elements.php' );
	if ( ! empty( $mode['woo'] ) ) {
		array_unshift( $plugins, 'woocommerce/woocommerce.php' );
	}
	foreach ( $plugins as $plugin ) {
		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			digi_f7b_fail( 'No se pudo activar ' . $plugin . ': ' . $result->get_error_message() );
		}
	}
	if ( file_exists( get_theme_root() . '/digitalisimo-theme/style.css' ) ) {
		switch_theme( 'digitalisimo-theme' );
	} else {
		digi_f7b_fail( 'Falta el tema digitalisimo-theme montado' );
	}
	update_option( 'elementor_onboarded', true );
	update_option( 'permalink_structure', '/%postname%/' );
	update_option( 'blogname', 'Sitio F7b' );
	update_option( 'blogdescription', 'Pruebas de F7b' );
	update_option( 'elementor_element_cache_ttl', 'disable' );
	update_option( 'date_format', 'Y-m-d' );
	foreach ( array( 'f7b-autor' => array( 'author', 'Ana', 'Autora' ), 'f7b-sub' => array( 'subscriber', 'Sofía', 'Suscriptora' ), 'f7b-editor' => array( 'editor', 'Elena', 'Editora' ) ) as $login => $data ) {
		if ( ! username_exists( $login ) ) {
			$user = wp_insert_user( array( 'user_login' => $login, 'user_pass' => 'password', 'user_email' => $login . '@example.invalid', 'role' => $data[0], 'first_name' => $data[1], 'last_name' => $data[2], 'display_name' => $data[1] . ' ' . $data[2] ) );
			if ( is_wp_error( $user ) ) {
				digi_f7b_fail( 'No se pudo crear ' . $login . ': ' . $user->get_error_message() );
			}
		}
	}
	wp_update_user( array( 'ID' => 1, 'first_name' => 'Admin', 'display_name' => 'Admin F7b' ) );
	return;
}

if ( 'content' !== $phase ) {
	digi_f7b_fail( 'Fase desconocida: ' . $phase );
	return;
}
if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
	digi_f7b_fail( 'El constructor no está cargado en el segundo paso' );
	return;
}
wp_set_current_user( 1 );
$cases = digi_f7b_cases();
if ( ! $cases ) {
	digi_f7b_fail( 'cases.json no es JSON válido' );
	return;
}
$map = array( 'docs' => array(), 'posts' => array(), 'users' => array(), 'media' => array(), 'queries' => array(), 'tt' => array(), 'terms' => array() );

// Usuarios.
$map['users']['admin'] = 1;
foreach ( array( 'f7b-autor', 'f7b-sub', 'f7b-editor' ) as $login ) {
	$user                    = get_user_by( 'login', $login );
	$map['users'][ $login ] = $user ? (int) $user->ID : 0;
}

// Medios: dos adjuntos de la misma imagen (URL distintas: la sobrescritura de imagen de B8 cambia de una a otra).
foreach ( array( 'image' => 'Imagen F7b', 'image2' => 'Imagen F7b dos' ) as $key => $title ) {
	$found = get_posts( array( 'post_type' => 'attachment', 'name' => sanitize_title( $title ), 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) );
	$id    = $found ? (int) $found[0] : 0;
	if ( ! $id ) {
		$upload = wp_upload_bits( sanitize_title( $title ) . '.png', null, (string) file_get_contents( ELEMENTOR_PATH . 'assets/images/placeholder.png' ) );
		if ( ! empty( $upload['error'] ) ) {
			digi_f7b_fail( 'No se pudo subir ' . $title . ': ' . $upload['error'] );
			continue;
		}
		$id   = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_status' => 'inherit' ), $upload['file'] );
		$meta = wp_generate_attachment_metadata( $id, $upload['file'] );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $id, $meta );
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $title );
	}
	$map['media'][ $key ] = array( 'id' => $id, 'url' => (string) wp_get_attachment_url( $id ) );
}

// Categorías y entradas de prueba.
foreach ( (array) ( $cases['terms'] ?? array() ) as $slug => $name ) {
	$term = term_exists( $slug, 'category' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
	}
	if ( is_wp_error( $term ) ) {
		digi_f7b_fail( 'Categoría ' . $slug . ': ' . $term->get_error_message() );
		continue;
	}
	$map['terms'][ $slug ] = (int) $term['term_id'];
	$map['tt'][ $slug ]    = (int) $term['term_taxonomy_id'];
}
foreach ( (array) ( $cases['posts'] ?? array() ) as $key => $p ) {
	$found = get_posts( array( 'post_type' => 'post', 'name' => 'f7b-' . $key, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	$id    = $found ? (int) $found[0] : (int) wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => (string) ( $p['status'] ?? 'publish' ),
			'post_title'    => (string) $p['title'],
			'post_name'     => 'f7b-' . $key,
			'post_excerpt'  => 'Extracto de ' . wp_strip_all_tags( (string) $p['title'] ),
			'post_content'  => '<p>Contenido de ' . esc_html( wp_strip_all_tags( (string) $p['title'] ) ) . '.</p>',
			'post_author'   => (int) ( $map['users'][ $p['author'] ?? 'admin' ] ?? 1 ),
			'post_date'     => (string) $p['date'],
			'post_category' => array_map( static function ( $slug ) use ( $map ) { return (int) ( $map['terms'][ $slug ] ?? 0 ); }, (array) ( $p['cats'] ?? array() ) ),
		)
	);
	if ( ! $id ) {
		digi_f7b_fail( 'No se pudo crear la entrada ' . $key );
		continue;
	}
	foreach ( (array) ( $p['meta'] ?? array() ) as $meta_key => $value ) {
		update_post_meta( $id, $meta_key, $value );
	}
	$map['posts'][ $key ] = $id;
}
update_option( 'digi_f7b_ids', $map );

// Consultas guardadas (C2): como las guarda la pantalla «Consultas».
$store = '\\Digitalisimo\\Elements\\F7b\\Queries\\Store';
if ( class_exists( $store ) ) {
	$store::register();
	foreach ( (array) ( $cases['queries'] ?? array() ) as $slug => $q ) {
		$q        = digi_f7b_resolve( (array) $q, $map );
		$existing = get_posts( array( 'post_type' => 'digitalisimo_query', 'title' => (string) $q['title'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		$id       = $store::save( $q, $existing ? (int) $existing[0] : 0 );
		if ( is_wp_error( $id ) ) {
			digi_f7b_fail( 'Consulta ' . $slug . ': ' . $id->get_error_code() . ' ' . $id->get_error_message() );
			continue;
		}
		$map['queries'][ $slug ] = (int) $id;
	}
} else {
	digi_f7b_fail( 'Sin Queries\\Store: las consultas guardadas no se crearon' );
}
update_option( 'digi_f7b_ids', $map );

// 1. Reservar todos los documentos (los marcadores necesitan los IDs).
foreach ( (array) $cases['docs'] as $key => $doc ) {
	if ( ! empty( $doc['woo'] ) && empty( $mode['woo'] ) ) {
		continue;
	}
	$type      = (string) $doc['type'];
	$post_type = in_array( $type, array( 'page', 'post' ), true ) ? $type : 'elementor_library';
	$found     = get_posts( array( 'post_type' => $post_type, 'name' => $key, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	$id        = $found ? (int) $found[0] : (int) wp_insert_post( array( 'post_type' => $post_type, 'post_status' => 'publish', 'post_title' => (string) $doc['title'], 'post_name' => $key, 'post_content' => '', 'post_author' => (int) ( $map['users'][ $doc['author'] ?? 'admin' ] ?? 1 ) ) );
	if ( ! $id ) {
		digi_f7b_fail( 'No se pudo crear ' . $key );
		continue;
	}
	$map['docs'][ $key ] = $id;
	foreach ( (array) ( $doc['meta'] ?? array() ) as $meta_key => $value ) {
		update_post_meta( $id, (string) $meta_key, $value );
	}
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_template_type', 'page' === $type ? 'wp-page' : ( 'post' === $type ? 'wp-post' : $type ) );
	if ( 'elementor_library' === $post_type ) {
		wp_set_object_terms( $id, $type, 'elementor_library_type' );
		if ( 'loop-item' === $type ) {
			update_post_meta( $id, '_elementor_source', (string) ( $doc['source'] ?? 'post' ) );
		}
	}
}
update_option( 'digi_f7b_ids', $map );

// 2. Contenido con Document::save() (o crudo con `raw`), condiciones del Theme Builder y popups.
$manager = class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ? \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager() : null;
foreach ( (array) $cases['docs'] as $key => $doc ) {
	if ( ! isset( $map['docs'][ $key ] ) ) {
		continue;
	}
	$id     = $map['docs'][ $key ];
	$status = (string) ( $doc['status'] ?? 'publish' );
	$tree   = digi_f7b_doc_tree( $key, $map );
	if ( ! empty( $doc['raw'] ) ) {
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree['elements'] ) ) );
	} else {
		$document = \Elementor\Plugin::$instance->documents->get( $id, false );
		if ( ! $document ) {
			digi_f7b_fail( 'Sin documento para ' . $key );
			continue;
		}
		$settings = array_merge( $tree['settings'], array( 'post_status' => $status ) );
		if ( false === $document->save( array( 'elements' => $tree['elements'], 'settings' => $settings ) ) ) {
			digi_f7b_fail( 'Document::save() falló en ' . $key );
		}
	}
	if ( 'publish' !== $status && get_post_status( $id ) !== $status ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
	}
	if ( 'popup' === $doc['type'] ) {
		update_post_meta( $id, '_elementor_popup_display_settings', array( 'triggers' => (array) ( $doc['triggers'] ?? array() ), 'timing' => array() ) );
	}
	if ( ! empty( $doc['conditions'] ) ) {
		if ( ! $manager ) {
			digi_f7b_fail( 'Sin Theme Builder: no se guardaron las condiciones de ' . $key );
			continue;
		}
		$rows = array();
		foreach ( (array) digi_f7b_resolve( (array) $doc['conditions'], $map ) as $condition ) {
			$parts  = array_pad( explode( '/', (string) $condition ), 4, '' );
			$rows[] = array( 'type' => $parts[0], 'name' => $parts[1], 'sub_name' => $parts[2], 'sub_id' => $parts[3] );
		}
		$manager->save_conditions( $id, $rows );
	}
}
update_option( 'digi_f7b_ids', $map );
if ( $manager ) {
	$manager->get_cache()->regenerate();
}

// Transición de «Rendimiento» (puerta de PRO aplicada, como en un sitio tras su primera visita a la administración).
if ( class_exists( '\\Digitalisimo\\Elements\\Front\\Transition' ) ) {
	\Digitalisimo\Elements\Front\Transition::run_now();
}
// Estado de las interacciones como en un sitio que acaba de actualizar (admin_init) y recorrido por lotes completo.
if ( class_exists( '\\Digitalisimo\\Elements\\F7b\\Actions\\Interactions_State' ) ) {
	\Digitalisimo\Elements\F7b\Actions\Interactions_State::admin_init();
}
if ( class_exists( '\\Digitalisimo\\Elements\\F7b\\Flags' ) ) {
	\Digitalisimo\Elements\F7b\Flags::ensure();
}
if ( class_exists( '\\Digitalisimo\\Elements\\F7b\\Scan' ) ) {
	$scan = '\\Digitalisimo\\Elements\\F7b\\Scan';
	$scan::schedule();
	for ( $i = 0; $i < 40 && $scan::running(); $i++ ) {
		$scan::run();
		wp_cache_delete( 'alloptions', 'options' );
	}
	wp_clear_scheduled_hook( $scan::HOOK );
}
if ( class_exists( '\\Digitalisimo\\Elements\\F7b\\Flags' ) ) {
	\Digitalisimo\Elements\F7b\Flags::refresh();
}
flush_rewrite_rules( false );
// Direcciones de cada documento y entrada (rutas relativas: run.cjs pide con su propio host).
$map['urls'] = array();
foreach ( array_merge( $map['docs'], $map['posts'] ) as $key => $id ) {
	$link                 = (string) get_permalink( (int) $id );
	$map['urls'][ $key ] = (string) wp_parse_url( $link, PHP_URL_PATH ) . ( wp_parse_url( $link, PHP_URL_QUERY ) ? '?' . wp_parse_url( $link, PHP_URL_QUERY ) : '' );
}
update_option( 'digi_f7b_ids', $map );
delete_transient( 'doing_cron' );
update_option( 'digi_f7b_ready', count( $map['docs'] ) );
file_put_contents( DIGI_F7B_DIR . '/ready.txt', (string) time() );
