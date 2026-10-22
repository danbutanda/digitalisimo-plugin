<?php
/**
 * Preparación del sitio de design.sh (F7a, frente M). Dos pasos del blueprint, cada uno en su petición:
 *
 * - `DIGI_DS_PHASE=activate`: instala la sonda (mu-plugin) y el registro de errores, crea los sitios `/uno/` y `/dos/`
 *   en NET=1, activa los plugins (Elementor fijado con CORE=plugin, WooCommerce con WOO=1), el tema DIGITALÍSIMO y las
 *   opciones del modo (caché de elementos, v4), y crea los usuarios de DS1 (un editor y un administrador con la
 *   restricción `design` del gestor de roles).
 * - `DIGI_DS_PHASE=content`: medios (imagen, SVG válido y SVG hostil), menú con submenú, categoría, logotipo y los
 *   documentos de `cases.json` con `Document::save()` como administrador (así corren los filtros de guardado: el
 *   centinela de las condiciones, la copia saneada de los SVG y las guardas de presets). Los de `at: ajax` quedan
 *   vacíos: run.cjs los guarda por `elementor_ajax`. Escribe `ready.txt`.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once __DIR__ . '/lib.php';

function digi_ds_fail( $message ) {
	file_put_contents( DIGI_DS_DIR . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

$mode    = digi_ds_mode();
$phase   = (string) getenv( 'DIGI_DS_PHASE' );
$network = is_multisite();
$blogs   = array( 1 );

if ( 'activate' === $phase ) {
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
		mkdir( WPMU_PLUGIN_DIR, 0777, true );
	}
	// La sonda se carga desde la carpeta montada: un arreglo del arnés llega a un sitio de KEEP=1 sin prepararlo otra vez.
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-ds-probe.php', "<?php\n// Sólo Playground (design.sh).\nrequire WP_CONTENT_DIR . '/probe/probe.php';\n" );
	file_put_contents(
		WPMU_PLUGIN_DIR . '/digi-ds-errors.php',
		'<?php
// Sólo Playground (design.sh): fatales a fatal.log y avisos de PHP de DIGITALÍSIMO Elements a php-errors.log.
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "cli" ) . " " . $e["message"] . " en " . $e["file"] . ":" . $e["line"] . "\n", FILE_APPEND ); } } );
$GLOBALS["digi_ds_prev_handler"] = set_error_handler( static function ( $no, $str, $file = "", $line = 0 ) {
	if ( false !== strpos( (string) $file, "/plugins/digitalisimo-elements/" ) && false === strpos( (string) $file, "/digitalisimo-elements/elementor/" ) && ( $no & ( E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED ) ) ) {
		file_put_contents( "/wordpress/wp-content/probe/php-errors.log", ( $_SERVER["REQUEST_URI"] ?? "cli" ) . " [" . $no . "] " . $str . " en " . $file . ":" . $line . "\n", FILE_APPEND );
	}
	$prev = $GLOBALS["digi_ds_prev_handler"];
	return $prev ? call_user_func( $prev, $no, $str, $file, $line ) : false;
} );
'
	);

	if ( $network ) {
		// Dos subsitios en subdirectorios: sus tablas empiezan en el mismo autoincremento, así que los IDs se repiten.
		foreach ( array( 'uno' => 'Sitio uno', 'dos' => 'Sitio dos' ) as $path => $title ) {
			if ( ! get_blog_id_from_url( get_network()->domain, '/' . $path . '/' ) ) {
				$id = wpmu_create_blog( get_network()->domain, '/' . $path . '/', $title, 1 );
				if ( is_wp_error( $id ) ) {
					digi_ds_fail( 'No se pudo crear /' . $path . '/: ' . $id->get_error_message() );
				}
			}
		}
	}

	if ( 'plugin' === ( $mode['core'] ?? 'bundled' ) ) {
		if ( ! file_exists( WP_PLUGIN_DIR . '/elementor/elementor.php' ) ) {
			digi_ds_fail( 'CORE=plugin: el blueprint no instaló el plugin Elementor' );
		} else {
			$result = activate_plugin( 'elementor/elementor.php', '', $network );
			if ( is_wp_error( $result ) ) {
				digi_ds_fail( 'No se pudo activar Elementor: ' . $result->get_error_message() );
			}
		}
	}
	$plugins = array( 'digitalisimo-elements/pro-elements.php' );
	if ( ! $network && file_exists( WP_PLUGIN_DIR . '/digitalisimo-seo/digitalisimo-integrations.php' ) ) {
		$plugins[] = 'digitalisimo-seo/digitalisimo-integrations.php';
	}
	if ( ! empty( $mode['woo'] ) ) {
		$plugins[] = 'woocommerce/woocommerce.php';
	}
	foreach ( $plugins as $plugin ) {
		$result = activate_plugin( $plugin, '', $network );
		if ( is_wp_error( $result ) ) {
			digi_ds_fail( 'No se pudo activar ' . $plugin . ': ' . $result->get_error_message() );
		}
	}
	if ( $network ) {
		$allowed                       = (array) get_site_option( 'allowedthemes', array() );
		$allowed['digitalisimo-theme'] = true;
		$allowed['hello-elementor']    = true;
		update_site_option( 'allowedthemes', $allowed );
		$blogs = array_map( 'intval', get_sites( array( 'fields' => 'ids' ) ) );
	}
	foreach ( $blogs as $blog ) {
		$network && switch_to_blog( $blog );
		if ( file_exists( get_theme_root() . '/digitalisimo-theme/style.css' ) ) {
			switch_theme( 'digitalisimo-theme' );
		} else {
			digi_ds_fail( 'Falta el tema digitalisimo-theme montado' );
		}
		update_option( 'elementor_onboarded', true );
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'blogdescription', 'Pruebas de diseño F7a' );
		// CACHE=1 (y V4=1, para DS11): la caché de elementos en su valor por defecto (24 h); si no, desactivada.
		if ( ! empty( $mode['cache'] ) || ! empty( $mode['v4'] ) ) {
			delete_option( 'elementor_element_cache_ttl' );
		} else {
			update_option( 'elementor_element_cache_ttl', 'disable' );
		}
		if ( ! empty( $mode['v4'] ) ) {
			update_option( 'elementor_experiment-e_atomic_elements', 'active' );
		}
		$network && restore_current_blog();
	}

	// DS1: un editor y un administrador «sólo contenido» (restricción `design` del gestor de roles).
	if ( ! get_role( 'digi_ds_content' ) ) {
		add_role( 'digi_ds_content', 'Administrador de contenido', get_role( 'administrator' )->capabilities );
	}
	foreach ( array( 'ds-editor' => 'editor', 'ds-design' => 'digi_ds_content' ) as $login => $role ) {
		if ( ! username_exists( $login ) ) {
			$user = wp_insert_user( array( 'user_login' => $login, 'user_pass' => 'password', 'user_email' => $login . '@example.invalid', 'role' => $role ) );
			if ( is_wp_error( $user ) ) {
				digi_ds_fail( 'No se pudo crear ' . $login . ': ' . $user->get_error_message() );
			}
		}
	}
	update_option( 'elementor_role-manager', array( 'digi_ds_content' => array( 'design' ) ) );
	return;
}

if ( 'content' !== $phase ) {
	digi_ds_fail( 'Fase desconocida: ' . $phase );
	return;
}
if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
	digi_ds_fail( 'El constructor no está cargado en el segundo paso' );
	return;
}
wp_set_current_user( 1 );
// Los SVG de prueba se suben como adjuntos (WordPress no admite SVG por defecto; el saneado es el de F7a al guardar).
add_filter(
	'upload_mimes',
	static function ( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}
);
add_filter(
	'wp_check_filetype_and_ext',
	static function ( $data, $file, $filename ) {
		return '.svg' === substr( (string) $filename, -4 ) ? array( 'ext' => 'svg', 'type' => 'image/svg+xml', 'proper_filename' => false ) : $data;
	},
	10,
	3
);
$cases = digi_ds_cases();
if ( ! $cases ) {
	digi_ds_fail( 'cases.json no es JSON válido' );
	return;
}
if ( $network ) {
	$blogs = array_values( array_filter( array_map( 'intval', get_sites( array( 'fields' => 'ids' ) ) ), static function ( $b ) { return $b > 1; } ) );
}

/** ¿Este documento va en este modo y en este sitio? */
function digi_ds_doc_here( array $doc, array $mode, $network ) {
	$net = 'net' === ( $doc['scope'] ?? 'site' );
	if ( $net !== (bool) $network ) {
		return false;
	}
	if ( isset( $doc['modes'] ) ) {
		$on = array();
		foreach ( array( 'woo', 'v4', 'cache' ) as $flag ) {
			if ( ! empty( $mode[ $flag ] ) ) {
				$on[] = $flag;
			}
		}
		return (bool) array_intersect( (array) $doc['modes'], $on );
	}
	return true;
}

function digi_ds_attachment( $file, $mime, $title ) {
	$found = get_posts( array( 'post_type' => 'attachment', 'name' => sanitize_title( $title ), 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $found ) {
		return (int) $found[0];
	}
	$upload = wp_upload_bits( basename( $file ), null, (string) file_get_contents( $file ) );
	if ( ! empty( $upload['error'] ) ) {
		digi_ds_fail( 'No se pudo subir ' . basename( $file ) . ': ' . $upload['error'] );
		return 0;
	}
	$id = wp_insert_attachment( array( 'post_mime_type' => $mime, 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_status' => 'inherit' ), $upload['file'] );
	if ( 'image/png' === $mime ) {
		$meta = wp_generate_attachment_metadata( $id, $upload['file'] );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $id, $meta );
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $title );
	}
	return (int) $id;
}

foreach ( $blogs as $blog ) {
	$network && switch_to_blog( $blog );
	wp_set_current_user( 1 );

	// Medios.
	$media    = array();
	$image_id = digi_ds_attachment( ELEMENTOR_PATH . 'assets/images/placeholder.png', 'image/png', 'Imagen de diseño' );
	if ( $image_id ) {
		$media['image'] = array( 'id' => $image_id, 'url' => (string) wp_get_attachment_url( $image_id ) );
		set_theme_mod( 'custom_logo', $image_id );
	}
	foreach ( array( 'valida' => 'svg-valida.svg', 'hostil' => 'svg-hostil.svg' ) as $name => $file ) {
		$id = digi_ds_attachment( DIGI_DS_DIR . '/' . $file, 'image/svg+xml', 'SVG ' . $name );
		if ( $id ) {
			$media[ 'svg-' . $name ] = array( 'id' => $id, 'url' => (string) wp_get_attachment_url( $id ), 'source' => 'library' );
		}
	}
	update_option( 'digi_ds_media', $media );

	// Menú con un submenú largo (el desplegable cae sobre el contenedor siguiente).
	$menu = wp_get_nav_menu_object( 'digi-ds-menu' );
	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( 'Menú de diseño' );
		wp_update_term( $menu_id, 'nav_menu', array( 'slug' => 'digi-ds-menu' ) );
		foreach ( (array) $cases['menu'] as $item ) {
			$parent = wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $item['title'], 'menu-item-type' => 'custom', 'menu-item-url' => isset( $item['url'] ) ? home_url( $item['url'] ) : '#', 'menu-item-status' => 'publish' ) );
			foreach ( (array) ( $item['children'] ?? array() ) as $child ) {
				wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $child, 'menu-item-type' => 'custom', 'menu-item-url' => home_url( '/#' . sanitize_title( $child ) ), 'menu-item-parent-id' => $parent, 'menu-item-status' => 'publish' ) );
			}
		}
	}

	// WOO=1 (DS1): la tienda visible (una instalación nueva queda en «próximamente» para los visitantes) y un producto
	// simple para la plantilla de producto con el widget de precio.
	if ( ! empty( $mode['woo'] ) ) {
		update_option( 'woocommerce_coming_soon', 'no' );
		update_option( 'woocommerce_store_pages_only', 'no' );
	}
	if ( ! empty( $mode['woo'] ) && class_exists( 'WC_Product_Simple' ) && ! get_page_by_path( 'digi-ds-producto', OBJECT, 'product' ) ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Producto de diseño' );
		$product->set_slug( 'digi-ds-producto' );
		$product->set_regular_price( '10' );
		$product->set_status( 'publish' );
		if ( ! $product->save() ) {
			digi_ds_fail( 'No se pudo crear el producto de WOO=1' );
		}
	} elseif ( ! empty( $mode['woo'] ) && ! class_exists( 'WC_Product_Simple' ) ) {
		digi_ds_fail( 'WOO=1 sin WooCommerce activo' );
	}

	$term = term_exists( 'digi-ds-cat', 'category' );
	if ( ! $term ) {
		$term = wp_insert_term( 'Categoría de diseño', 'category', array( 'slug' => 'digi-ds-cat' ) );
	}
	$category = is_array( $term ) ? (int) $term['term_id'] : (int) $term;

	// 1. Reservar todos los documentos (los marcadores necesitan los IDs).
	$ids = array();
	foreach ( (array) $cases['docs'] as $key => $doc ) {
		if ( ! digi_ds_doc_here( (array) $doc, $mode, $network ) ) {
			continue;
		}
		$type      = (string) $doc['type'];
		$post_type = in_array( $type, array( 'page', 'post' ), true ) ? $type : 'elementor_library';
		$found     = get_posts( array( 'post_type' => $post_type, 'name' => $key, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		$id        = $found ? (int) $found[0] : (int) wp_insert_post( array( 'post_type' => $post_type, 'post_status' => 'publish', 'post_title' => (string) $doc['title'], 'post_name' => $key, 'post_content' => '', 'post_category' => 'post' === $type ? array( $category ) : array() ) );
		if ( ! $id ) {
			digi_ds_fail( 'No se pudo crear ' . $key );
			continue;
		}
		$ids[ $key ] = $id;
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_template_type', 'page' === $type ? 'wp-page' : ( 'post' === $type ? 'wp-post' : $type ) );
		if ( 'elementor_library' === $post_type ) {
			wp_set_object_terms( $id, $type, 'elementor_library_type' );
			if ( 'loop-item' === $type ) {
				update_post_meta( $id, '_elementor_source', 'post' );
			}
		}
		if ( ! empty( $doc['thumbnail'] ) && $image_id ) {
			set_post_thumbnail( $id, $image_id );
		}
	}
	update_option( 'digi_ds_ids', $ids );

	// 2. Contenido con Document::save() (los de «ajax» los guarda run.cjs).
	$manager = class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ? \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager() : null;
	foreach ( (array) $cases['docs'] as $key => $doc ) {
		if ( ! isset( $ids[ $key ] ) ) {
			continue;
		}
		$id     = $ids[ $key ];
		$status = (string) ( $doc['status'] ?? 'publish' );
		if ( 'ajax' !== ( $doc['at'] ?? 'setup' ) ) {
			$document = \Elementor\Plugin::$instance->documents->get( $id, false );
			if ( ! $document ) {
				digi_ds_fail( 'Sin documento para ' . $key );
				continue;
			}
			if ( isset( $doc['first'] ) ) {
				// Una versión anterior que queda sólo en una revisión.
				$first = array();
				foreach ( (array) $doc['first'] as $node ) {
					$first[] = digi_ds_node( (array) $node, $ids, $media );
				}
				$document->save( array( 'elements' => $first, 'settings' => array( 'post_status' => $status ) ) );
				add_filter( 'wp_save_post_revision_check_for_changes', '__return_false' );
				wp_save_post_revision( $id );
				remove_filter( 'wp_save_post_revision_check_for_changes', '__return_false' );
				$document = \Elementor\Plugin::$instance->documents->get( $id, false );
			}
			$tree     = digi_ds_doc_tree( $key, $ids, $media );
			$settings = array_merge( $tree['settings'], array( 'post_status' => $status ) );
			if ( false === $document->save( array( 'elements' => $tree['elements'], 'settings' => $settings ) ) ) {
				digi_ds_fail( 'Document::save() falló en ' . $key );
			}
		} elseif ( 'publish' !== $status ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
		}
		if ( 'draft' === $status && 'publish' === get_post_status( $id ) ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		}
		if ( ! empty( $doc['conditions'] ) ) {
			if ( ! $manager ) {
				digi_ds_fail( 'Sin Theme Builder: no se guardaron las condiciones de ' . $key );
				continue;
			}
			$rows = array();
			foreach ( (array) digi_ds_resolve( (array) $doc['conditions'], $ids, $media ) as $condition ) {
				$parts  = array_pad( explode( '/', (string) $condition ), 4, '' );
				$rows[] = array( 'type' => $parts[0], 'name' => $parts[1], 'sub_name' => $parts[2], 'sub_id' => $parts[3] );
			}
			$manager->save_conditions( $id, $rows );
		}
	}
	if ( $manager ) {
		$manager->get_cache()->regenerate();
	}
	flush_rewrite_rules( false );
	delete_transient( 'doing_cron' );
	update_option( 'digi_ds_ready', count( $ids ) );
	$network && restore_current_blog();
}
file_put_contents( DIGI_DS_DIR . '/ready.txt', (string) time() );
