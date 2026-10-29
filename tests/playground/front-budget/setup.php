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

/**
 * Lo que necesitan los casos de F4-Woo (`since` 4.3.0.166) para pintar algo real. Se crea **después** de las páginas,
 * sus documentos y el menú, así el catálogo de `digi_fb_woo_catalog()`, las páginas y los documentos de los casos
 * anteriores conservan sus IDs (comparación con 4.3.0.165 y con la línea base de 4.3.0.164). Todo se escribe con las
 * funciones de WordPress y WooCommerce, sin clases de Elements: la corrida de 4.3.0.165 prepara el mismo sitio.
 *
 * - Atributo global `pa_color` (tipo «select», como lo deja la pantalla de WooCommerce) con tres términos y la meta
 *   `color` de WooCommerce 11.2.1, y su entrada «Muestra visual» en `digitalisimo_elements_swatches` (formato `v` 1,
 *   con carga automática: el front sólo la lee si está en `wp_load_alloptions()`).
 * - `digi-fb-muestras`: variable con `pa_color` (tres variaciones), en `digi-fb-cat-uno` y más antiguo que el resto
 *   (no entra en los tres primeros del widget Productos de `woo-pro-existing`). La sonda lo quita de las consultas de
 *   productos de las páginas de los casos sin `since` (`digi_fb_woo_f4_products`), que miden el catálogo de siempre.
 * - Zona del país de la tienda con «Tarifa plana» y «Envío gratuito» desde 50: la barra sabe cuánto falta.
 *
 * Devuelve los marcadores `__WC_SIMPLE__` (el simple «Producto de medición 1», que ya existía) y `__WC_SWATCH__`.
 *
 * @param int $image_id    Adjunto de prueba.
 * @param int $variable_id Variable del catálogo de siempre.
 */
function digi_fb_woo_f4( $image_id, $variable_id ) {
	$markers = digi_fb_woo_markers();
	$simple  = get_page_by_path( 'digi-fb-producto-1', OBJECT, 'product' );
	if ( ! $simple ) {
		digi_fb_fail( 'WOO=1: falta el producto digi-fb-producto-1' );
		return $markers;
	}
	$markers['__WC_SIMPLE__']   = (string) $simple->ID;
	$markers['__WC_VARIABLE__'] = (string) $variable_id;
	$notes                      = array();

	// La tabla de atributos de WooCommerce se actualiza al guardar (sin Action Scheduler): el filtro de atributo la usa.
	update_option( 'woocommerce_attribute_lookup_direct_updates', 'yes' );
	$taxonomy = 'pa_color';
	if ( ! wc_attribute_taxonomy_id_by_name( $taxonomy ) ) {
		$created = wc_create_attribute( array( 'name' => 'Color', 'slug' => 'color', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
		if ( is_wp_error( $created ) ) {
			digi_fb_fail( 'WOO=1: no se pudo crear pa_color: ' . $created->get_error_message() );
			return $markers;
		}
	}
	if ( ! taxonomy_exists( $taxonomy ) ) {
		register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => false, 'rewrite' => false ) );
	}
	$terms = array();
	$order = 0;
	foreach ( array( 'rojo' => array( 'Rojo', '#c0392b' ), 'azul' => array( 'Azul', '#2155cf' ), 'verde' => array( 'Verde', '#1e8449' ) ) as $slug => $def ) {
		$term = term_exists( $slug, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $def[0], $taxonomy, array( 'slug' => $slug ) );
		}
		if ( is_wp_error( $term ) ) {
			digi_fb_fail( 'WOO=1: no se pudo crear el término ' . $slug . ': ' . $term->get_error_message() );
			return $markers;
		}
		$terms[ $slug ] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
		// Formato de `VisualAttributeTermMeta::save_term_visual()` de WooCommerce 11.2.1: `color` hexadecimal, sin `image`.
		update_term_meta( $terms[ $slug ], 'color', $def[1] );
		update_term_meta( $terms[ $slug ], 'order', $order++ );
	}
	update_option( 'digitalisimo_elements_swatches', array( 'v' => 1, 'attributes' => array( 'color' => array( 'type' => 'visual', 'shape' => 'circle', 'size' => 32 ) ) ), true );

	$existing = get_page_by_path( 'digi-fb-muestras', OBJECT, 'product' );
	if ( $existing ) {
		$swatch_id = (int) $existing->ID;
	} else {
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( (int) wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( array_values( $terms ) );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product = new WC_Product_Variable();
		$product->set_name( 'Producto con muestras de medición' );
		$product->set_slug( 'digi-fb-muestras' );
		$product->set_status( 'publish' );
		$product->set_sku( 'DIGI-FB-MUESTRAS' );
		$product->set_short_description( 'Producto variable con muestras de color para medir el front.' );
		$product->set_category_ids( array( (int) get_term_by( 'slug', 'digi-fb-cat-uno', 'product_cat' )->term_id ) );
		$product->set_attributes( array( $attribute ) );
		$product->set_date_created( time() - 30 * DAY_IN_SECONDS );
		if ( $image_id ) {
			$product->set_image_id( $image_id );
		}
		$swatch_id = (int) $product->save();
		$price     = 30;
		foreach ( array_keys( $terms ) as $slug ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $swatch_id );
			$variation->set_attributes( array( $taxonomy => $slug ) );
			$variation->set_regular_price( (string) $price++ );
			$variation->set_sku( 'DIGI-FB-MUESTRAS-' . strtoupper( $slug ) );
			$variation->set_status( 'publish' );
			$variation->save();
		}
		WC_Product_Variable::sync( $swatch_id );
		wc_delete_product_transients( $swatch_id );
	}
	if ( ! $swatch_id || count( wc_get_product( $swatch_id )->get_children() ) !== 3 ) {
		digi_fb_fail( 'WOO=1: digi-fb-muestras no quedó con sus tres variaciones' );
	}
	$markers['__WC_SWATCH__'] = (string) $swatch_id;
	update_option( 'digi_fb_woo_f4_products', array( $swatch_id ), true );

	// Zona del país de la tienda: «Tarifa plana» de 5 y «Envío gratuito» desde 50 (sin descuentos).
	$country = WC()->countries->get_base_country();
	$zone_id = (int) get_option( 'digi_fb_woo_zone' );
	$zone    = $zone_id ? WC_Shipping_Zones::get_zone( $zone_id ) : null;
	if ( ! $zone || ! $zone->get_id() ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Zona de medición' );
		$zone->set_zone_order( 1 );
		$zone->add_location( $country, 'country' );
		$zone->save();
		$flat = $zone->add_shipping_method( 'flat_rate' );
		$free = $zone->add_shipping_method( 'free_shipping' );
		update_option( 'woocommerce_flat_rate_' . $flat . '_settings', array( 'title' => 'Tarifa plana', 'tax_status' => 'none', 'cost' => '5' ) );
		update_option( 'woocommerce_free_shipping_' . $free . '_settings', array( 'title' => 'Envío gratuito', 'requires' => 'min_amount', 'min_amount' => '50', 'ignore_discounts' => 'no' ) );
		update_option( 'digi_fb_woo_zone', $zone->get_id() );
	}
	$notes[] = sprintf(
		'WOO=1 F4-Woo: simple %d, variable %d, muestras %d; pa_color %s; zona %d (%s) con %d métodos; envío %s, ubicación del cliente %s, cupones %s, tabla de atributos %s',
		$simple->ID,
		$variable_id,
		$swatch_id,
		wp_json_encode( $terms ),
		$zone->get_id(),
		$country,
		count( $zone->get_shipping_methods( true ) ),
		function_exists( 'wc_shipping_enabled' ) && wc_shipping_enabled() ? 'activo' : 'inactivo',
		(string) get_option( 'woocommerce_default_customer_address' ),
		(string) get_option( 'woocommerce_enable_coupons' ),
		(string) get_option( 'woocommerce_attribute_lookup_enabled' )
	);
	file_put_contents( DIGI_FB_DIR . '/setup-notes.txt', implode( "\n", $notes ) . "\n", FILE_APPEND );
	return $markers;
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
	// El sitio se mide en el estado en que WooCommerce lo deja al instalarse, sin pasar por la administración. Su
	// `woocommerce_newly_installed` corre en el primer `admin_init`, que en el front llega con el primer `admin-ajax.php`
	// (el Menú del carrito de PRO con productos lo pide): a mitad de corrida fijaría `woocommerce_hooked_blocks_version` y
	// el tema de bloques empezaría a pintar el mini carrito y la cuenta de WooCommerce en la cabecera.
	file_put_contents( DIGI_FB_DIR . '/setup-notes.txt', 'WOO=1: woocommerce_newly_installed ' . wp_json_encode( get_option( 'woocommerce_newly_installed', null ) ) . ' → no; woocommerce_hooked_blocks_version ' . wp_json_encode( get_option( 'woocommerce_hooked_blocks_version', null ) ) . "\n", FILE_APPEND );
	update_option( 'woocommerce_newly_installed', 'no' );
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

// WOO=1, casos de F4-Woo: lo nuevo, después de todo lo anterior (mismos IDs), y sus marcadores `__WC_*__` (0 sin
// WooCommerce). `markers.json` los deja a run.cjs para las URL de `views` y de las acciones.
$digi_fb_woo_markers = ! empty( $mode['woo'] ) && $digi_fb_variable ? digi_fb_woo_f4( $image_id, $digi_fb_variable ) : digi_fb_woo_markers();
$cases               = digi_fb_replace( $cases, $digi_fb_woo_markers );
file_put_contents( DIGI_FB_DIR . '/markers.json', wp_json_encode( $digi_fb_woo_markers ) );

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
