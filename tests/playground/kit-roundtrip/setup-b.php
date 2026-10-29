<?php
/**
 * Sitio B de kit-roundtrip.sh (§13.3): el destino.
 *
 * Paso del blueprint (sin modo): borra el contenido por defecto (CV-15), crea el editor de R9 y desplaza los
 * autoincrementos de entradas y términos (CV-8) con dos centinelas que no son contenido público (una revisión con
 * ID 100000 y un término de `link_category` con ID 100000): así un ID de A que el importador no traduzca nunca cae
 * por azar en algo de B y la firma lo cuenta como `unresolved:<id>`. Los centinelas se quedan: con el autoincremento
 * de SQLite no basta con borrarlos, y no cuentan para «sitio vacío» (D1) ni para la firma.
 *
 * Modos (los pide run.cjs por mu-kit.php):
 * - clash: el contenido de choque de R4 (colores con el mismo _id y otro valor, menú y términos con el mismo slug,
 *   separador digi-onda con otro sha256, cabecera publicada con include/general, botón flotante con condiciones,
 *   portada estática, fuente e icono propios, plantilla con el mismo título y adjunto con el mismo nombre de archivo);
 *   con WOO=1, además, pa_color con otra etiqueta y su propio «rojo», un atributo propio con muestras y un producto con
 *   el SKU de una variación del kit (F4-Woo, V.11.5);
 * - clash-v4: además, variables y clases globales v4 (R4v4);
 * - content: B con contenido propio que no viene del kit (R5);
 * - users: el editor sin manage_options de R9 (también lo crea el paso inicial).
 */

if ( ! defined( 'ABSPATH' ) ) {
	require '/wordpress/wp-load.php';
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once __DIR__ . '/lib.php';

$digi_mode = (string) ( $GLOBALS['digi_k_setup_b_mode'] ?? '' );
$digi_ids  = array();
wp_set_current_user( 1 );

$digi_editor = static function () {
	if ( ! get_user_by( 'login', 'editora' ) ) {
		wp_insert_user( array( 'user_login' => 'editora', 'user_pass' => 'editora-k', 'user_email' => 'editora@example.com', 'display_name' => 'Editora', 'role' => 'editor' ) );
	}
};

if ( '' === $digi_mode ) {
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		digi_k_fail( 'El constructor no está cargado en la preparación de B' );
		return;
	}
	// La misma identidad que A (setup-a.php): la identidad nunca viaja y así las capturas comparan sólo lo importado.
	update_option( 'blogname', 'Sitio del kit' );
	// Como A: la subida de SVG sin filtrar decide si los SVG de la biblioteca viajan (D15; R6 prueba la red sin SVG).
	update_option( 'elementor_unfiltered_files_upload', '1' );
	update_option( 'blogdescription', 'Ida y vuelta de K' );
	digi_k_clear_defaults();
	$digi_editor();
	global $wpdb;
	if ( ! get_post( 100000 ) ) {
		$sentinel = wp_insert_post( array( 'import_id' => 100000, 'post_type' => 'revision', 'post_status' => 'inherit', 'post_title' => 'Centinela del autoincremento', 'post_name' => 'digi-k-centinela' ), true );
		if ( is_wp_error( $sentinel ) || 100000 !== (int) $sentinel ) {
			digi_k_fail( 'No se pudo desplazar el autoincremento de entradas (CV-8)' );
		}
	}
	if ( ! term_exists( 100000 ) ) {
		$wpdb->insert( $wpdb->terms, array( 'term_id' => 100000, 'name' => 'Centinela', 'slug' => 'digi-k-centinela', 'term_group' => 0 ) );
		$wpdb->insert( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => 100000, 'term_id' => 100000, 'taxonomy' => 'link_category', 'description' => '', 'parent' => 0, 'count' => 0 ) );
		clean_term_cache( 100000, 'link_category' );
		$probe = wp_insert_term( 'Sonda', 'link_category' );
		if ( is_wp_error( $probe ) || (int) $probe['term_id'] <= 100000 ) {
			digi_k_fail( 'No se pudo desplazar el autoincremento de términos (CV-8)' );
		}
		if ( ! is_wp_error( $probe ) ) {
			wp_delete_term( $probe['term_id'], 'link_category' );
		}
	}
	$next = (int) wp_insert_post( array( 'post_type' => 'revision', 'post_status' => 'inherit', 'post_title' => 'Sonda' ) );
	if ( $next <= 100000 ) {
		digi_k_fail( "El autoincremento de entradas no quedó desplazado (siguiente ID {$next})" );
	}
	wp_delete_post( $next, true );
	update_option( 'digi_k_ready', time() );
	return;
}

if ( 'users' === $digi_mode ) {
	$digi_editor();
	digi_k_write( 'ids-users.json', array( 'editora' => (int) get_user_by( 'login', 'editora' )->ID ) );
	return;
}

if ( 'content' === $digi_mode ) {
	$page = digi_k_document( 'wp-page', 'page', 'digi-b-propia', 'Página propia de B', array( array( 'id' => 'cb00001', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'eb00001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Contenido de B' ), 'elements' => array() ) ) ) ) );
	$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada propia de B', 'post_name' => 'digi-b-entrada', 'post_content' => '<p>Escrita en B.</p>' ) );
	digi_k_write( 'ids-content.json', array( 'page' => $page, 'post' => $post ) );
	return;
}

// R4: contenido de choque.
$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
$settings = (array) $kit->get_settings();
$settings['custom_colors'] = array(
	array( '_id' => 'a1b2c3d', 'title' => 'Marca de B', 'color' => '#00AA55' ),
	array( '_id' => 'bb00001', 'title' => 'Propio de B', 'color' => '#111111' ),
);
$settings['custom_typography'] = array( array( '_id' => 'c3d4e5f', 'title' => 'Titular de B', 'typography_typography' => 'custom', 'typography_font_family' => 'Georgia', 'typography_font_weight' => '400' ) );
$settings['custom_css']        = '/* CSS de B */ body{letter-spacing:0}';
$settings['container_width']   = array( 'unit' => 'px', 'size' => 1020, 'sizes' => array() );
$kit->save( array( 'settings' => $settings ) );
$kit->update_json_meta( '_elementor_elements_default_values', array( 'heading' => array( 'title_color' => '#00AA55' ) ) );
$digi_ids['kit'] = (int) $kit->get_id();

$menu = wp_get_nav_menu_object( 'principal' );
if ( ! $menu ) {
	$menu_id = wp_create_nav_menu( 'Principal de B' );
	wp_update_term( $menu_id, 'nav_menu', array( 'slug' => 'principal' ) );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Inicio de B', 'menu-item-type' => 'custom', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish' ) );
	$digi_ids['menu'] = (int) $menu_id;
}
if ( ! term_exists( 'noticias', 'category' ) ) {
	$digi_ids['category'] = (int) wp_insert_term( 'Noticias de B', 'category', array( 'slug' => 'noticias' ) )['term_id'];
}

$uploads = wp_upload_dir();
$shape   = trailingslashit( $uploads['basedir'] ) . 'digitalisimo-elements/shapes/onda.svg';
wp_mkdir_p( dirname( $shape ) );
file_put_contents( $shape, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" preserveAspectRatio="none"><path class="elementor-shape-fill" d="M0 0h1000v40H0z"/></svg>' );

$header = digi_k_document( 'header', 'elementor_library', 'digi-b-cabecera', 'Cabecera de B', array( array( 'id' => 'cb00002', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'eb00002', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Cabecera de B' ), 'elements' => array() ) ) ) ) );
digi_k_conditions( $header, array( 'include/general' ) );
$digi_ids['header'] = $header;

if ( post_type_exists( 'e-floating-buttons' ) ) {
	$floating = digi_k_document( 'floating-buttons', 'e-floating-buttons', 'digi-b-flotante', 'Botón flotante de B', array() );
	update_post_meta( $floating, '_elementor_conditions', array( 'include/general' ) );
	$digi_ids['floating'] = $floating;
}

$front = digi_k_document( 'wp-page', 'page', 'digi-b-portada', 'Portada de B', array( array( 'id' => 'cb00003', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'eb00003', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Portada de B' ), 'elements' => array() ) ) ) ) );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front );
$digi_ids['front'] = $front;

if ( post_type_exists( 'elementor_font' ) && ! get_page_by_path( 'digi-sans-b', OBJECT, 'elementor_font' ) ) {
	$file = digi_k_attachment( 'fuente-b', array( 'woff2' => true, 'title' => 'Digi Sans B' ) );
	$font = (int) wp_insert_post( array( 'post_type' => 'elementor_font', 'post_status' => 'publish', 'post_title' => 'Digi Sans B', 'post_name' => 'digi-sans-b' ) );
	update_post_meta( $font, 'elementor_font_files', array( array( 'font_weight' => '400', 'font_style' => 'normal', 'woff2' => array( 'id' => $file, 'url' => wp_get_attachment_url( $file ) ) ) ) );
	update_post_meta( $font, 'elementor_font_face', "@font-face{font-family:'Digi Sans B';src:url('" . wp_get_attachment_url( $file ) . "') format('woff2');}" );
	update_post_meta( $font, 'elementor_font_type', 'custom' );
	$digi_ids['font'] = $font;
}
if ( post_type_exists( 'elementor_icons' ) && ! get_page_by_path( 'digi-b-iconos', OBJECT, 'elementor_icons' ) ) {
	$icons = (int) wp_insert_post( array( 'post_type' => 'elementor_icons', 'post_status' => 'publish', 'post_title' => 'digi-b-iconos', 'post_name' => 'digi-b-iconos' ) );
	update_post_meta( $icons, 'elementor_custom_icon_set_config', wp_json_encode( array( 'name' => 'digi-b-iconos', 'label' => 'Digi B Iconos', 'url' => '', 'enqueue' => false, 'prefix' => 'dbi-', 'displayPrefix' => '', 'labelIcon' => 'eicon eicon-folder', 'ver' => '1.0.0', 'custom_icon_type' => 'fontello', 'count' => 1, 'icons' => array( 'uno' ) ) ) );
	$digi_ids['icons'] = $icons;
}

$digi_ids['same_title'] = digi_k_document( 'section', 'elementor_library', 'digi-b-seccion', 'Sección del kit', array( array( 'id' => 'cb00004', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'eb00004', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Sección de B con el mismo título' ), 'elements' => array() ) ) ) ) );

// Adjunto con el mismo nombre de archivo que imagen-a de A, con otros bytes.
$same = get_posts( array( 'post_type' => 'attachment', 'name' => 'digi-k-imagen-a', 'posts_per_page' => 1, 'fields' => 'ids', 'post_status' => 'inherit' ) );
$digi_ids['same_file'] = $same ? (int) $same[0] : digi_k_attachment( 'imagen-a', array( 'png' => array( 10, 200, 10 ), 'w' => 16, 'h' => 16, 'title' => 'Imagen A de B' ) );

// F7a: un preset `tarjeta` propio de B con otro estilo (el del kit debe llegar como `tarjeta-k<hex>` y sus vínculos,
// reescritos) y la subida de SVG sin filtrar apagada (la copia saneada de la máscara del sitio viaja por la parte
// `files`, no como adjunto).
$digi_store = '\\Digitalisimo\\Elements\\Design\\Presets\\Store';
$digi_perf  = '\\Elementor\\Core\\Frontend\\Performance';
if ( class_exists( $digi_store ) && method_exists( $digi_perf, 'set_use_style_controls' ) ) {
	$previous = (bool) $digi_perf::is_use_style_controls();
	$digi_perf::set_use_style_controls( true );
	try {
		$digi_store::replace( array( 'v' => 1, 'presets' => array( 'tarjeta' => array( 'type' => 'heading', 'label' => 'Tarjeta de B', 'updated' => 1791000000, 'settings' => array( 'title_color' => '#00AA55', 'typography_typography' => 'custom', 'typography_font_weight' => '300' ) ) ) ) );
	} finally {
		$digi_perf::set_use_style_controls( $previous );
	}
	$digi_ids['presets'] = array_keys( $digi_store::all() );
	if ( array( 'tarjeta' ) !== $digi_ids['presets'] ) {
		digi_k_fail( 'R4: no se pudo sembrar el preset tarjeta de B: ' . wp_json_encode( $digi_store::dropped() ) );
	}
} else {
	digi_k_fail( 'R4: falta Store de presets (F7a) o Performance::set_use_style_controls()' );
}
update_option( 'elementor_unfiltered_files_upload', '0' );

// F4-Woo (WOO=1, V.11.5): pa_color ya existe en B con otra etiqueta, sin muestras y con su propio «rojo» (otro nombre y
// otra meta color: «agregar» lo reutiliza sin cambiarlo); un atributo propio con muestras (su entrada no cambia: sólo
// se añaden las de los atributos que crea la sesión) y un producto con el SKU de una variación del kit
// (woocommerce.sku-conflict). Ningún atributo wc-visual y la función apagada (docs/fusion/f4woo.md §1.3): el
// wc-visual del kit vuelve a select.
if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_create_attribute' ) ) {
	$digi_ids['woo_color']    = digi_k_woo_attribute( array( 'slug' => 'color', 'label' => 'Color de B', 'type' => 'select', 'orderby' => 'name', 'has_archives' => false, 'terms' => array( array( 'slug' => 'rojo', 'name' => 'Rojo de B', 'meta' => array( 'color' => '#ff0000' ) ) ) ), null, $digi_ids );
	$digi_ids['woo_material'] = digi_k_woo_attribute( array( 'slug' => 'material-b', 'label' => 'Material de B', 'type' => 'select', 'orderby' => 'menu_order', 'has_archives' => false, 'swatches' => array( 'type' => 'label', 'shape' => 'rounded', 'size' => 40 ), 'terms' => array( array( 'slug' => 'lana', 'name' => 'Lana' ) ) ), null, $digi_ids );
	if ( ! wc_get_product_id_by_sku( 'DIGI-K-CC-AZUL' ) ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Producto de B' );
		$product->set_slug( 'digi-b-producto' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '9' );
		$product->set_sku( 'DIGI-K-CC-AZUL' );
		$digi_ids['woo_product'] = (int) $product->save();
	}
	if ( 'yes' === get_option( 'woocommerce_feature_wc_visual_attribute_enabled' ) ) {
		digi_k_fail( 'R4: la función wc-visual de WooCommerce está encendida en B' );
	}
}

if ( 'clash-v4' === $digi_mode ) {
	update_post_meta( $kit->get_id(), '_elementor_global_variables', wp_slash( wp_json_encode( array( 'format_version' => 1, 'data' => array( 'e-gv-b000001' => array( 'type' => 'global-color-variable', 'label' => 'color-de-b', 'value' => '#00AA55' ) ), 'watermark' => 1 ) ) ) );
	update_post_meta( $kit->get_id(), '_elementor_global_classes', wp_slash( wp_json_encode( array( 'items' => array( 'g-b000001' => array( 'id' => 'g-b000001', 'type' => 'class', 'label' => 'clase-de-b', 'variants' => array() ) ), 'order' => array( 'g-b000001' ) ) ) ) );
	$digi_ids['v4'] = true;
}

\Elementor\Plugin::$instance->files_manager->clear_cache();
digi_k_write( 'ids-' . $digi_mode . '.json', $digi_ids );
