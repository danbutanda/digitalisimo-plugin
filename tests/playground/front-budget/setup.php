<?php
/**
 * Segundo paso del blueprint de front-budget.sh (el constructor ya está cargado): una página por caso de
 * `cases.json` (`digi-fb-<caso>`) y los documentos `extra` de la biblioteca (popups con su tipo, término, disparadores
 * y condiciones; plantillas; plantillas de bucle; cabeceras; widget global), con los árboles de `lib.php`, los mismos
 * que recorre `tests/elements-front-cases.php`. Además: el adjunto de prueba (un PNG subido a Medios para que las
 * imágenes salgan con srcset y dimensiones), seis entradas en dos categorías y un menú con submenú.
 *
 * Marcadores en `cases.json`: `__SITE__`, `__IMAGE__` y `__MENU__` se sustituyen en el texto; `__SELF__`,
 * `__POPUP__`, `__TEMPLATE__`, `__LOOP__` y `__DOC:<key>__`, por caso (`lib.php`). Al final se regenera la caché de
 * condiciones del Theme Builder.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once __DIR__ . '/lib.php';

const DIGI_FB_DIR  = '/wordpress/wp-content/probe';
const DIGI_FB_MENU = 'digi-fb-menu';

function digi_fb_fail( $message ) {
	file_put_contents( DIGI_FB_DIR . '/setup-error.txt', $message . "\n", FILE_APPEND );
}

/**
 * Catálogo de WOO=1. Devuelve el ID del producto variable; los fallos van a setup-error.txt.
 *
 * @param int $image_id Adjunto de prueba.
 */
function digi_fb_woo_catalog( $image_id ) {
	if ( ! function_exists( 'wc_create_attribute' ) || ! class_exists( 'WC_Product_Simple' ) ) {
		digi_fb_fail( 'WOO=1: WooCommerce no está cargado en el segundo paso' );
		return 0;
	}
	$existing = (int) get_option( 'digi_fb_woo_variable' );
	if ( $existing && wc_get_product( $existing ) ) {
		return $existing;
	}
	$cats = array();
	foreach ( array( 'digi-fb-cat-uno' => 'Categoría de medición uno', 'digi-fb-cat-dos' => 'Categoría de medición dos' ) as $slug => $name ) {
		$term = term_exists( $slug, 'product_cat' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		}
		if ( is_wp_error( $term ) ) {
			digi_fb_fail( 'WOO=1: no se pudo crear la categoría ' . $slug . ': ' . $term->get_error_message() );
			return 0;
		}
		$cats[ $slug ] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	}
	for ( $i = 1; $i <= 6; $i++ ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Producto de medición ' . $i );
		$product->set_slug( 'digi-fb-producto-' . $i );
		$product->set_status( 'publish' );
		$product->set_regular_price( (string) ( 10 + $i ) );
		$product->set_sku( 'DIGI-FB-' . $i );
		$product->set_short_description( 'Producto ' . $i . ' para medir el front con WooCommerce.' );
		$product->set_category_ids( array( $cats[ $i <= 3 ? 'digi-fb-cat-uno' : 'digi-fb-cat-dos' ] ) );
		$product->set_date_created( time() - $i * HOUR_IN_SECONDS );
		if ( $image_id ) {
			$product->set_image_id( $image_id );
		}
		$product->save();
	}
	// Atributo global sin muestras: el «Añadir al carrito» del variable pinta su <select> de siempre.
	$taxonomy = 'pa_digi-fb-talla';
	if ( ! wc_attribute_taxonomy_id_by_name( $taxonomy ) ) {
		$created = wc_create_attribute( array( 'name' => 'Talla de medición', 'slug' => 'digi-fb-talla', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
		if ( is_wp_error( $created ) ) {
			digi_fb_fail( 'WOO=1: no se pudo crear el atributo: ' . $created->get_error_message() );
			return 0;
		}
	}
	// La taxonomía del atributo recién creado no está registrada en esta petición (WooCommerce la registra en `init`).
	if ( ! taxonomy_exists( $taxonomy ) ) {
		register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => false, 'rewrite' => false ) );
	}
	$terms = array();
	foreach ( array( 's' => 'S', 'm' => 'M', 'l' => 'L' ) as $slug => $name ) {
		$term = term_exists( $slug, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		}
		if ( is_wp_error( $term ) ) {
			digi_fb_fail( 'WOO=1: no se pudo crear el término ' . $slug . ': ' . $term->get_error_message() );
			return 0;
		}
		$terms[ $slug ] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	}
	$attribute = new WC_Product_Attribute();
	$attribute->set_id( (int) wc_attribute_taxonomy_id_by_name( $taxonomy ) );
	$attribute->set_name( $taxonomy );
	$attribute->set_options( array_values( $terms ) );
	$attribute->set_position( 0 );
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	$variable = new WC_Product_Variable();
	$variable->set_name( 'Producto variable de medición' );
	$variable->set_slug( 'digi-fb-producto-variable' );
	$variable->set_status( 'publish' );
	$variable->set_sku( 'DIGI-FB-VAR' );
	$variable->set_category_ids( array( $cats['digi-fb-cat-uno'] ) );
	$variable->set_attributes( array( $attribute ) );
	$variable->set_date_created( time() - 7 * HOUR_IN_SECONDS );
	if ( $image_id ) {
		$variable->set_image_id( $image_id );
	}
	$variable_id = (int) $variable->save();
	$price       = 20;
	foreach ( array_keys( $terms ) as $slug ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $variable_id );
		$variation->set_attributes( array( $taxonomy => $slug ) );
		$variation->set_regular_price( (string) $price++ );
		$variation->set_sku( 'DIGI-FB-VAR-' . strtoupper( $slug ) );
		$variation->set_status( 'publish' );
		$variation->save();
	}
	WC_Product_Variable::sync( $variable_id );
	wc_delete_product_transients( $variable_id );
	if ( count( wc_get_product( $variable_id )->get_children() ) !== 3 ) {
		digi_fb_fail( 'WOO=1: el producto variable no quedó con sus tres variaciones' );
	}
	update_option( 'digi_fb_woo_variable', $variable_id );
	return $variable_id;
}

$mode = json_decode( (string) file_get_contents( DIGI_FB_DIR . '/mode.json' ), true );
$mode = is_array( $mode ) ? $mode : array();

if ( ! is_plugin_active( 'digitalisimo-elements/pro-elements.php' ) && ! is_plugin_active_for_network( 'digitalisimo-elements/pro-elements.php' ) ) {
	digi_fb_fail( 'digitalisimo-elements no quedó activo' );
}
if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
	digi_fb_fail( 'El constructor no está cargado en el segundo paso' );
	return;
}

// Adjunto de prueba: el marcador del núcleo copiado a Medios, con sus tamaños intermedios si el servidor puede generarlos.
$image_id = (int) get_option( 'digi_fb_image' );
if ( ! $image_id || ! get_post( $image_id ) ) {
	$source = ELEMENTOR_PATH . 'assets/images/placeholder.png';
	$upload = wp_upload_bits( 'digi-medicion.png', null, (string) file_get_contents( $source ) );
	if ( empty( $upload['error'] ) ) {
		$image_id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Imagen de medición', 'post_status' => 'inherit' ), $upload['file'] );
		update_post_meta( $image_id, '_wp_attachment_image_alt', 'Imagen de medición' );
		$metadata = wp_generate_attachment_metadata( $image_id, $upload['file'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $image_id, $metadata );
		}
		update_option( 'digi_fb_image', $image_id );
	} else {
		digi_fb_fail( 'No se pudo subir la imagen de prueba: ' . $upload['error'] );
	}
}
$image_url = $image_id ? (string) wp_get_attachment_url( $image_id ) : ELEMENTOR_ASSETS_URL . 'images/placeholder.png';

// Entradas para los bucles y el archivo de THEME=1: tres en cada categoría.
$categories = array();
foreach ( array( 'digi-fb-uno' => 'Medición uno', 'digi-fb-dos' => 'Medición dos' ) as $slug => $name ) {
	$term = term_exists( $slug, 'category' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
	}
	$categories[ $slug ] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
}
for ( $i = 1; $i <= 6; $i++ ) {
	if ( ! get_page_by_path( 'digi-fb-post-' . $i, OBJECT, 'post' ) ) {
		wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada de medición ' . $i, 'post_name' => 'digi-fb-post-' . $i, 'post_content' => '<p>Entrada ' . $i . ' para medir los bucles.</p>', 'post_category' => array( $categories[ $i <= 3 ? 'digi-fb-uno' : 'digi-fb-dos' ] ), 'post_date' => gmdate( 'Y-m-d H:i:s', time() - $i * HOUR_IN_SECONDS ) ) );
	}
}

// WOO=1 (F4-Woo, V.16.3): dos categorías de producto con tres productos simples cada una y un producto variable con
// un atributo global sin muestras (`pa_digi-fb-talla`, tipo «select»), siempre en el mismo orden para que dos corridas
// den los mismos IDs. `__WC_VARIABLE__` en `cases.json` es el ID del variable (0 sin WooCommerce).
$digi_fb_variable = 0;
if ( ! empty( $mode['woo'] ) ) {
	$digi_fb_variable = digi_fb_woo_catalog( $image_id );
}

$raw = (string) file_get_contents( __DIR__ . '/cases.json' );
$raw = str_replace( array( '"__IMAGE__"', '__SITE__', '__MENU__', '__WC_VARIABLE__' ), array( wp_json_encode( array( 'id' => $image_id, 'url' => $image_url ) ), untrailingslashit( home_url() ), DIGI_FB_MENU, (string) $digi_fb_variable ), $raw );
$cases = json_decode( $raw, true );
if ( ! is_array( $cases ) ) {
	digi_fb_fail( 'cases.json no es JSON válido' );
	$cases = array();
}

// F7a: presets de los casos (`presets`, slug → { type, label, settings }) en la opción del sitio, con `Store::replace()`
// dentro de los controles de estilo, como los siembra un kit. Sólo si la versión medida los tiene (4.3.0.163 no).
$digi_fb_presets = array();
foreach ( $cases as $case ) {
	foreach ( (array) ( $case['presets'] ?? array() ) as $preset_slug => $preset ) {
		$digi_fb_presets[ (string) $preset_slug ] = $preset;
	}
}
if ( $digi_fb_presets ) {
	$store = '\\Digitalisimo\\Elements\\Design\\Presets\\Store';
	$css   = '\\Digitalisimo\\Elements\\Design\\Css';
	if ( class_exists( $store ) && class_exists( $css ) && method_exists( $store, 'replace' ) ) {
		$css::with_style_controls(
			static function () use ( $store, $digi_fb_presets ) {
				$store::replace( array( 'v' => 1, 'presets' => $digi_fb_presets ) );
			}
		);
		$dropped = method_exists( $store, 'dropped' ) ? (array) $store::dropped() : array();
		if ( $dropped ) {
			digi_fb_fail( 'Presets descartados por Store::replace(): ' . wp_json_encode( $dropped ) );
		}
	} else {
		file_put_contents( DIGI_FB_DIR . '/setup-notes.txt', "Sin Presets\\Store en esta versión: los presets de los casos no se siembran\n", FILE_APPEND );
	}
}
// F7a: ajustes de «Rendimiento» que piden los casos (`front_settings`, p. ej. «Registrar visitas sin gestor de
// consentimiento»), como propios del sitio y sin tocar las demás claves (LAZY y DEFAULTS=off ya escribieron las suyas).
$digi_fb_front = array();
foreach ( $cases as $case ) {
	foreach ( (array) ( $case['front_settings'] ?? array() ) as $key => $value ) {
		$digi_fb_front[ (string) $key ] = (bool) $value;
	}
}
if ( $digi_fb_front ) {
	$stored = get_option( 'digitalisimo_elements_front', array() );
	$stored = is_array( $stored ) ? $stored : array();
	$own    = isset( $stored['own'] ) && is_array( $stored['own'] ) ? $stored['own'] : array();
	foreach ( $digi_fb_front as $key => $value ) {
		$stored[ $key ] = $value;
		$own[]          = $key;
	}
	$stored['own'] = array_values( array_unique( $own ) );
	update_option( 'digitalisimo_elements_front', $stored, true );
}

// Páginas y documentos: primero se reservan todos (para resolver los marcadores), después se guardan sus datos.
$pages  = array();
$extras = array();
foreach ( $cases as $slug => $case ) {
	$page_slug = digi_fb_page_slug( $slug );
	$existing  = get_page_by_path( $page_slug );
	$pages[ $slug ] = $existing ? (int) $existing->ID : (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $page_slug, 'post_name' => $page_slug, 'post_content' => str_repeat( '<p>Texto de la página para medir el tiempo de lectura y el progreso.</p>', 40 ) ) );
	foreach ( (array) ( $case['extra'] ?? array() ) as $extra ) {
		$extra_slug = digi_fb_extra_slug( $slug, (string) $extra['key'] );
		$found      = get_posts( array( 'post_type' => 'elementor_library', 'name' => $extra_slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		$id         = $found ? (int) $found[0] : (int) wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $extra_slug, 'post_name' => $extra_slug ) );
		$extras[ $slug ][ (string) $extra['key'] ] = array( 'id' => $id, 'type' => (string) $extra['type'] );
	}
}

// Menú con un submenú (Menú de PRO y menú heredado).
$menu = wp_get_nav_menu_object( DIGI_FB_MENU );
if ( ! $menu ) {
	$menu_id = wp_create_nav_menu( 'Menú de medición' );
	wp_update_term( $menu_id, 'nav_menu', array( 'slug' => DIGI_FB_MENU ) );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Inicio', 'menu-item-type' => 'custom', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish' ) );
	$parent = wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Páginas', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $pages['empty'], 'menu-item-status' => 'publish' ) );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Doce widgets', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $pages['all-own'], 'menu-item-parent-id' => $parent, 'menu-item-status' => 'publish' ) );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Categoría', 'menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => $categories['digi-fb-uno'], 'menu-item-status' => 'publish' ) );
}

$save_data = static function ( $id, array $tree ) {
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
};
$conditions_manager = class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ? \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager() : null;

foreach ( $cases as $slug => $case ) {
	$id      = $pages[ $slug ];
	$markers = digi_fb_markers( $id, (array) ( $extras[ $slug ] ?? array() ) );
	$tree    = digi_fb_page_tree( $slug, $case );
	if ( null !== $tree ) {
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		$save_data( $id, digi_fb_replace( $tree, $markers ) );
	}
	// F7a: ajustes del documento (`page_settings`, p. ej. «Ocultar imagen destacada») e imagen destacada (`thumbnail`).
	if ( ! empty( $case['page_settings'] ) && is_array( $case['page_settings'] ) ) {
		update_post_meta( $id, '_elementor_page_settings', $case['page_settings'] );
	}
	if ( ! empty( $case['thumbnail'] ) && $image_id ) {
		set_post_thumbnail( $id, $image_id );
	}
	// `save: document`: además se guarda por `Document::save()` como el editor (administrador), para que corran
	// `elementor/document/save/data` (centinela de las condiciones de F7a) y `after_save` (índice de visitas).
	if ( 'document' === ( $case['save'] ?? '' ) && null !== $tree ) {
		wp_set_current_user( 1 );
		$document = \Elementor\Plugin::$instance->documents->get( $id, false );
		$saved    = $document ? $document->save( array( 'elements' => json_decode( wp_json_encode( digi_fb_replace( $tree, $markers ) ), true ) ) ) : false;
		wp_set_current_user( 0 );
		if ( ! $saved ) {
			digi_fb_fail( "$slug: Document::save() no guardó el documento" );
		}
	}
	foreach ( (array) ( $case['extra'] ?? array() ) as $extra ) {
		$key  = (string) $extra['key'];
		$type = (string) $extra['type'];
		$xid  = $extras[ $slug ][ $key ]['id'];
		update_post_meta( $xid, '_elementor_template_type', $type );
		wp_set_object_terms( $xid, $type, 'elementor_library_type' );
		if ( 'loop-item' === $type ) {
			// `source`: «post» por defecto; «product» para las plantillas de bucle de productos (WOO=1).
			update_post_meta( $xid, '_elementor_source', isset( $extra['source'] ) ? (string) $extra['source'] : 'post' );
		}
		$save_data( $xid, digi_fb_replace( digi_fb_extra_tree( $slug, $extra ), $markers ) );
		if ( 'popup' === $type ) {
			update_post_meta( $xid, '_elementor_popup_display_settings', array( 'triggers' => (array) ( $extra['triggers'] ?? array() ), 'timing' => (array) ( $extra['timing'] ?? array() ) ) );
		}
		// Las cabeceras de THEME=1 se activan de una en una desde la sonda (`?digi_probe=header`).
		if ( ! empty( $extra['conditions'] ) && 'header' !== $type ) {
			if ( ! $conditions_manager ) {
				digi_fb_fail( 'El Theme Builder no está cargado: no se guardaron las condiciones de ' . $slug . '/' . $key );
				continue;
			}
			$rows = array();
			foreach ( (array) digi_fb_replace( (array) $extra['conditions'], $markers ) as $condition ) {
				$parts  = array_pad( explode( '/', (string) $condition ), 4, '' );
				$rows[] = array( 'type' => $parts[0], 'name' => $parts[1], 'sub_name' => $parts[2], 'sub_id' => $parts[3] );
			}
			$conditions_manager->save_conditions( $xid, $rows );
		}
	}
}
if ( $conditions_manager ) {
	$conditions_manager->get_cache()->regenerate();
}
// SEO=guard: la tipografía del kit pasa a una familia de archivos estáticos por peso (Lato 400 y 700). Con una familia
// variable (Roboto, la del kit por defecto) todos los pesos comparten archivo y el blindaje no tiene nada que recortar.
if ( 'guard' === ( $mode['seo'] ?? '' ) && isset( \Elementor\Plugin::$instance->kits_manager ) ) {
	$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
	if ( $kit && $kit->get_id() ) {
		$kit_settings                      = (array) $kit->get_settings();
		$kit_settings['system_typography'] = array();
		foreach ( array( 'primary' => 'Primary', 'secondary' => 'Secondary', 'text' => 'Text', 'accent' => 'Accent' ) as $id => $title ) {
			$kit_settings['system_typography'][] = array( '_id' => $id, 'title' => $title, 'typography_typography' => 'custom', 'typography_font_family' => 'Lato', 'typography_font_weight' => in_array( $id, array( 'primary', 'accent' ), true ) ? '700' : '400' );
		}
		// Document::save() exige un usuario que pueda editar el kit.
		wp_set_current_user( 1 );
		if ( false === $kit->save( array( 'settings' => $kit_settings ) ) ) {
			digi_fb_fail( 'SEO=guard: no se pudo guardar la tipografía del kit' );
		}
		wp_set_current_user( 0 );
	} else {
		digi_fb_fail( 'SEO=guard: no hay kit activo' );
	}
}
flush_rewrite_rules( false );
// El primer paso cargó WordPress antes de que existiera la sonda: su WP-Cron dejó el cerrojo `doing_cron` (60 s) con un
// lazo de red que Playground no atiende. Se suelta para que el arnés pueda ejecutar wp-cron.php en cuanto arranque.
delete_transient( 'doing_cron' );
update_option( 'digi_fb_ready', count( $cases ) );
file_put_contents( DIGI_FB_DIR . '/ready.txt', (string) count( $cases ) );
