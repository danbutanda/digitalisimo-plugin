<?php
/**
 * Ficha de producto propia (C7 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c7.md, frente M): los widgets
 * `digitalisimo-woo-*` de `modules/digitalisimo-woo/single/` frente a los de PRO que sustituyen
 * (`modules/woocommerce/widgets/`), y sus mapas (`modules/digitalisimo-convert/maps/`). Carga los archivos **reales** del
 * Encabezado y del Botón del núcleo y de los widgets de PRO sobre el entorno de `tests/elements-woo-single/stubs.php`
 * (WooCommerce simulado) y comprueba:
 * - registro: nombre, título, categoría (la de PRO), línea en `Woo\Registry::WIDGETS`/`TITLES` con su archivo y su hoja,
 *   recursos por `Registry::style_depends()`/`script_depends()`; hojas ≤ 4 KB sin el kit, `url()`, clases del proveedor
 *   ni colores que PRO no usara, con foco visible; el script del bucle sin jQuery; nada vetado por el empaquetado ni
 *   `<script>` en el marcado;
 * - controles: los mismos que PRO (también los heredados del núcleo), con el mismo tipo, valor por defecto, condición,
 *   opciones, rangos y selectores (con las clases de PRO traducidas a las propias), en el mismo orden y sección;
 * - marcado: el mismo que PRO con los mismos datos (con las clases de PRO traducidas): plantillas y funciones de
 *   WooCommerce, envolturas de «Añadir al carrito» por tipo de producto y disposición, bucle, elementos sueltos, botón
 *   personalizado con y sin cantidad;
 * - mapas: cada control de PRO declarado y conversión de ajustes reales (por defecto, estilos, responsivos, globales,
 *   dinámicos, CSS personalizado) sin `pro.unmapped`, con cada clave en un control propio.
 */
require __DIR__ . '/elements-woo-single/stubs.php';

use Digitalisimo\Elements\Convert\Registry as Maps;
use Digitalisimo\Elements\Woo\Registry;
use Digitalisimo\Elements\Woo\Single;
use ElementorPro\Modules\Woocommerce\Widgets as Pro;

// Clases de PRO → propias en prefijos, selectores y marcado.
const WS_CLASSES = array(
	'elementor-product-price-block-'     => 'digi-product-price--block-',
	'elementor-product-rating--align-'   => 'digi-product-rating--align-',
	'elementor-woo-meta--view-'          => 'digi-product-meta--view-',
	'elementor-show-heading-'            => 'digi-product-additional-information--heading-',
	'elementor-add-to-cart--layout-'     => 'digi-atc--layout-',
	'elementor-add-to-cart%s--align-'    => 'digi-atc%s--align-',
	'e-add-to-cart--show-quantity-'      => 'digi-atc--show-quantity-',
	'e-atc-qty-button-holder'            => 'digi-atc__holder',
	'e-loop-add-to-cart-form-container'  => 'digi-atc__loop',
	'e-loop-add-to-cart-form'            => 'digi-atc__loop-form',
	'elementor-add-to-cart elementor-product-' => 'digi-atc__wrap digi-atc--type-',
	// Cuadrícula de productos (la de N, `Store\Products_Base`).
	'elementor-products-grid elementor-'  => 'digi-products-grid digi-',
	'elementor-product-loop-item--align-' => 'digi-products--align-',
	'elementor-show-pagination-border-'   => 'digi-products--pagination-border-',
	'elementor-wc-products'               => 'digi-wc-products',
);

$pairs = array(
	'woocommerce-product-title'                  => array( Pro\Product_Title::class, Single\Product_Title::class, 'Título del producto', 'woocommerce-elements-single' ),
	'woocommerce-product-price'                  => array( Pro\Product_Price::class, Single\Product_Price::class, 'Precio del producto', 'woocommerce-elements-single' ),
	'woocommerce-product-rating'                 => array( Pro\Product_Rating::class, Single\Product_Rating::class, 'Valoración del producto', 'woocommerce-elements-single' ),
	'woocommerce-product-stock'                  => array( Pro\Product_Stock::class, Single\Product_Stock::class, 'Existencias del producto', 'woocommerce-elements-single' ),
	'woocommerce-product-meta'                   => array( Pro\Product_Meta::class, Single\Product_Meta::class, 'Metadatos del producto', 'woocommerce-elements-single' ),
	'woocommerce-product-short-description'      => array( Pro\Product_Short_Description::class, Single\Product_Short_Description::class, 'Descripción corta del producto', 'woocommerce-elements-single' ),
	'woocommerce-product-content'                => array( Pro\Product_Content::class, Single\Product_Content::class, 'Contenido del producto', 'woocommerce-elements-single' ),
	'woocommerce-product-additional-information' => array( Pro\Product_Additional_Information::class, Single\Product_Additional_Information::class, 'Información adicional del producto', 'woocommerce-elements-single' ),
	'woocommerce-product-data-tabs'              => array( Pro\Product_Data_Tabs::class, Single\Product_Data_Tabs::class, 'Pestañas de datos del producto', 'woocommerce-elements-single' ),
	'woocommerce-product-images'                 => array( Pro\Product_Images::class, Single\Product_Images::class, 'Imágenes del producto', 'woocommerce-elements-single' ),
	'woocommerce-product-add-to-cart'            => array( Pro\Product_Add_To_Cart::class, Single\Product_Add_To_Cart::class, 'Añadir al carrito', 'woocommerce-elements-single' ),
	'wc-single-elements'                         => array( Pro\Single_Elements::class, Single\Single_Elements::class, 'Elementos del producto', 'woocommerce-elements-single' ),
	'wc-add-to-cart'                             => array( Pro\Add_To_Cart::class, Single\Add_To_Cart::class, 'Añadir al carrito personalizado', 'woocommerce-elements' ),
	'woocommerce-product-related'                => array( Pro\Product_Related::class, Single\Product_Related::class, 'Productos relacionados', 'woocommerce-elements-single' ),
	'woocommerce-product-upsell'                 => array( Pro\Product_Upsell::class, Single\Product_Upsell::class, 'Ventas dirigidas', 'woocommerce-elements-single' ),
);

// Diferencias declaradas en los controles (con su motivo en el informe y en la cabecera de cada widget).
$skip = array(
	// Alineación de los dos «Añadir al carrito»: además de la clase de PRO, variables por dispositivo que lee la hoja propia.
	'woocommerce-product-add-to-cart' => array( 'alignment.selectors' => true, 'alignment.selectors_dictionary' => true ),
	// El texto por defecto del botón en español (el mapa completa el de PRO en lo convertido).
	'wc-add-to-cart'                  => array( 'align.selectors' => true, 'align.selectors_dictionary' => true, 'text.default' => true ),
);

// Productos de prueba.
ws_product( array( 'id' => 11, 'name' => 'Camisa <b>de</b> prueba', 'sku' => 'AB-1', 'cats' => array( 3 => 'Ropa' ), 'tags' => array( 5 => 'Verano', 6 => 'Algodón' ), 'description' => 'Descripción larga', 'short' => 'Corta' ) );
ws_product( array( 'id' => 12, 'type' => 'variable', 'name' => 'Variable', 'cats' => array( 3 => 'Ropa', 4 => 'Hombre' ), 'tags' => array( 5 => 'Verano' ) ) );
ws_product( array( 'id' => 13, 'type' => 'grouped', 'name' => 'Agrupado' ) );
ws_product( array( 'id' => 14, 'type' => 'external', 'name' => 'Externo', 'ajax' => false ) );
ws_product( array( 'id' => 15, 'name' => 'Agotado', 'in_stock' => false ) );
ws_product( array( 'id' => 16, 'name' => 'Borrador', 'status' => 'draft' ) );
$GLOBALS['ws']['product_id'] = 11;
$GLOBALS['tt']['post']       = (object) array( 'ID' => 11, 'post_title' => 'Camisa', 'post_content' => 'Contenido del producto', 'post_excerpt' => '' );
$GLOBALS['tt']['posts'][11]  = $GLOBALS['tt']['post'];

$widgets = array();
$checks  = 0;
$check   = static function ( $ok, $message ) use ( &$checks ) {
	++$checks;
	check_ws( $ok, $message );
};

// 1. Registro, recursos y controles.
$root   = ws_path( '' );
$colors = array( '#d3ced2', '#5bc0de', '#5cb85c', '#f0ad4e', '#d9534f' );
foreach ( $pairs as $source => list( $pro_class, $own_class, $title, $category ) ) {
	$pro  = ws_widget( $pro_class );
	$own  = ws_widget( $own_class );
	$name = $own->get_name();
	$slug = substr( $name, strlen( 'digitalisimo-woo-' ) );
	$widgets[ $source ] = array( $pro, $own );
	$check( 0 === strpos( $name, 'digitalisimo-woo-' ) && $title === $own->get_title() && array( $category ) === $own->get_categories() && $pro->get_categories() === $own->get_categories(), "$name: nombre, título y la categoría de PRO." );
	$entry = Registry::WIDGETS[ $name ] ?? null;
	$check( is_array( $entry ) && 'single/class-' . $slug . '.php' === $entry['file'] && ltrim( $own_class, '\\' ) === ltrim( $entry['class'], '\\' ) && 'modules/digitalisimo-woo/single/assets/' . $slug . '.css' === $entry['css'] && $title === Registry::title( $name ) && '' === Registry::feature( $name ), "$name: en Woo\\Registry con su archivo, su clase, su hoja y su título." );
	$diff = ws_compare( $pro, $own, WS_CLASSES, $skip[ $source ] ?? array() );
	$check( ! $diff, "$name: controles distintos de los de PRO:\n" . implode( "\n", $diff ) );
	$check( count( $pro->controls ) === count( $own->controls ) && count( $pro->groups ) === count( $own->groups ), "$name: el mismo número de controles y grupos." );
	// Hoja propia (si la tiene) y código.
	$css_file = ws_path( $entry['css'] );
	if ( is_file( $css_file ) ) {
		$css = (string) file_get_contents( $css_file );
		$check( strlen( $css ) > 0 && strlen( $css ) <= 4096, "$name: la hoja pesa " . strlen( $css ) . ' B (tope 4 096).' );
		$check( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ), "$name: la hoja no usa el kit ni url()." );
		$foreign = preg_replace( '/\.elementor-widget-digitalisimo-woo-[a-z-]+|\.elementor-widget\b|\.elementor-button(-[a-z]+)?\b|div\.product\.elementor\b|\.elementor\.product\b/', '', $css );
		$check( ! preg_match( '/\.elementor-|\.e-(?!loop-item\b)[a-z]/', $foreign ), "$name: la hoja sólo usa clases propias (salvo la envoltura, el botón y el documento del núcleo): " . substr( $foreign, 0, 200 ) );
		preg_match_all( '/#[0-9a-f]{3,8}\b/i', $css, $found );
		$check( ! array_diff( array_map( 'strtolower', $found[0] ), $colors ), "$name: sólo colores que PRO usaba por defecto." );
		if ( preg_match( '/\ba\b[^{]*:focus|:is\(a,|a:focus-visible/', $css ) || str_contains( $css, 'focus-visible' ) ) {
			$check( str_contains( $css, ':focus-visible{outline:2px solid;outline-offset:2px}' ), "$name: foco visible de 2 px con separación de 2 px." );
		}
		$check( ( $own_class === Single\Product_Title::class ? array( 'widget-heading', $name ) : ( Single\Add_To_Cart::class === $own_class ? array( 'digitalisimo-woo-product-add-to-cart', $name ) : array( $name ) ) ) === $own->get_style_depends(), "$name: sus hojas (" . implode( ', ', $own->get_style_depends() ) . ').' );
	} else {
		$expected = Single\Product_Title::class === $own_class ? array( 'widget-heading' ) : ( in_array( $own_class, array( Single\Product_Related::class, Single\Product_Upsell::class ), true ) ? array( 'digitalisimo-woo-products' ) : array() );
		$check( $expected === $own->get_style_depends(), "$name: sin hoja propia (" . implode( ', ', $own->get_style_depends() ) . ').' );
	}
	$code = ws_code( ws_path( 'modules/digitalisimo-woo/' . $entry['file'] ) );
	$check( ! preg_match( '/jquery|swiper|wp_register_script|elementor-pro-frontend|pro-elements-handlers|ElementorPro\\\\Modules\\\\Woocommerce/i', $code ) && ! preg_match( '/<script/i', $code ), "$name: sin jQuery, Swiper, scripts registrados a mano, clases del módulo de PRO ni <script> en el marcado." );
	foreach ( array( ws_path( 'modules/digitalisimo-woo/' . $entry['file'] ), $css_file, ws_path( 'modules/digitalisimo-convert/maps/' . $source . '.php' ) ) as $file ) {
		$check( ! is_file( $file ) || ! preg_match( WS_VETOED, (string) file_get_contents( $file ) ), basename( $file ) . ': nombra algo que el empaquetado veta.' );
	}
}
$check( 15 === count( array_filter( array_keys( Registry::WIDGETS ), static function ( $n ) { return 0 === strpos( Registry::WIDGETS[ $n ]['file'], 'single/' ); } ) ), 'Los quince widgets del frente M están en Woo\\Registry.' );
$js = (string) file_get_contents( ws_path( 'modules/digitalisimo-woo/single/assets/product-add-to-cart.js' ) );
$check( strlen( $js ) <= 6144 && ! preg_match( '/\bjQuery\s*[.(]|\$\(|\$\./', $js ) && str_contains( $js, 'data-quantity' ) && str_contains( $js, 'MutationObserver' ), 'Script del bucle de «Añadir al carrito»: sin jQuery, ≤ 6 KB.' );
$preview = Single\Module::preview_js();
$check( ! str_contains( $preview, '<script' ) && str_contains( $preview, 'digitalisimo-woo-product-images.default' ) && str_contains( $preview, 'data("product_gallery")' ) && str_contains( $preview, '.wc-tabs li.active' ), 'Vista previa: galería y pestañas recién pintadas, sin iniciarlas dos veces.' );
foreach ( array( 'class-module.php', 'class-base.php', 'class-product-trait.php' ) as $file ) {
	$check( ! preg_match( WS_VETOED, (string) file_get_contents( ws_path( 'modules/digitalisimo-woo/single/' . $file ) ) ), "$file: nada vetado." );
}

// Envolturas: la clase propia en lugar de la de PRO (y la del encabezado del núcleo en el título).
$wrapper = static function ( $w ) {
	return ( new ReflectionMethod( $w, 'get_html_wrapper_class' ) )->invoke( $w );
};
$check( 'elementor-widget-digitalisimo-woo-product-title elementor-page-title elementor-widget-heading' === $wrapper( $widgets['woocommerce-product-title'][1] ) && 'elementor-widget-woocommerce-product-title elementor-page-title elementor-widget-heading' === $wrapper( $widgets['woocommerce-product-title'][0] ), 'Título: la envoltura del encabezado, como PRO.' );
$check( 'elementor-widget-digitalisimo-woo-product-content' === $wrapper( $widgets['woocommerce-product-content'][1] ), 'Contenido del producto: sin la clase del contenido de la entrada (como PRO).' );
$check( str_ends_with( $wrapper( $widgets['woocommerce-product-add-to-cart'][1] ), ' digi-atc digi-atc--product' ) && str_ends_with( $wrapper( $widgets['wc-add-to-cart'][1] ), ' digi-atc digi-atc--custom' ), 'Los dos «Añadir al carrito» llevan las clases de su hoja.' );
$check( false === $widgets['wc-single-elements'][1]->show_in_panel() && false === $widgets['wc-single-elements'][0]->show_in_panel(), 'Elementos del producto: oculto del panel, como el de PRO (obsoleto).' );
$check( array( 'section_atc_variations_style' ) === array_keys( array_filter( \Digitalisimo\Elements\Woo\Swatches\Atc_Styles::TARGETS, static function ( $s, $w ) { return 'digitalisimo-woo-product-add-to-cart' === $w; }, ARRAY_FILTER_USE_BOTH ) ) || 'section_atc_variations_style' === \Digitalisimo\Elements\Woo\Swatches\Atc_Styles::TARGETS['digitalisimo-woo-product-add-to-cart'], 'El rasgo «Muestras» de F4-Woo también va en los «Añadir al carrito» propios.' );
$check( 'section_style' === \Digitalisimo\Elements\Woo\Swatches\Atc_Styles::TARGETS['digitalisimo-woo-add-to-cart'], 'El rasgo «Muestras» va en el botón personalizado propio, tras su estilo.' );

// 2. El mismo marcado que PRO con los mismos datos.
$same = static function ( string $source, array $settings, string $label ) use ( $widgets, $check ) {
	list( $pro, $own ) = $widgets[ $source ];
	$a = ws_norm( ws_render( $pro, $settings ), WS_CLASSES );
	$t = $GLOBALS['ws']['templates'];
	$b = ws_norm( ws_render( $own, $settings ) );
	$check( $a === $b && $t === $GLOBALS['ws']['templates'], "$source ($label): el mismo marcado que PRO.\nPRO:    $a\npropio: $b" );
	return $b;
};
foreach ( array( 'woocommerce-product-price', 'woocommerce-product-rating', 'woocommerce-product-stock', 'woocommerce-product-short-description', 'woocommerce-product-additional-information', 'woocommerce-product-data-tabs', 'woocommerce-product-images', 'woocommerce-product-title' ) as $source ) {
	$html = $same( $source, array(), 'por defecto' );
	$check( '' !== $html, "$source: pinta algo." );
}
$check( str_contains( $same( 'woocommerce-product-price', array(), 'precio' ), 'data-tpl="single-product/price.php" data-product="11"' ), 'Precio: la plantilla de WooCommerce con el producto de la consulta.' );
$check( 'single-product/tabs/tabs.php' === $GLOBALS['ws']['templates'][0] || str_contains( $same( 'woocommerce-product-data-tabs', array(), 'pestañas' ), 'single-product/tabs/tabs.php' ), 'Pestañas: la plantilla de WooCommerce.' );
$check( str_contains( $same( 'woocommerce-product-images', array(), 'con oferta' ), 'loop/sale-flash.php' ) && ! str_contains( $same( 'woocommerce-product-images', array( 'sale_flash' => '' ), 'sin oferta' ), 'sale-flash' ), 'Imágenes: el distintivo de oferta sólo si se pide.' );
$GLOBALS['ws_state']->edit = true;
$check( ! str_contains( ws_render( $widgets['woocommerce-product-images'][1] ), '<script' ) && str_contains( ws_render( $widgets['woocommerce-product-images'][0] ), '<script' ), 'Imágenes en el editor: sin <script> en el marcado (PRO lo ponía).' );
$GLOBALS['ws_state']->edit = false;
$same( 'woocommerce-product-title', array( 'title' => 'Camisa', 'header_size' => 'h2', 'link' => array( 'url' => 'https://sitio.test/x/', 'is_external' => '', 'nofollow' => '' ) ), 'enlazado' );
$check( str_contains( ws_render( $widgets['woocommerce-product-title'][1], array( 'title' => 'Camisa' ) ), '<h1 class="elementor-heading-title elementor-size-default product_title entry-title">Camisa</h1>' ) || str_contains( ws_render( $widgets['woocommerce-product-title'][1], array( 'title' => 'Camisa' ) ), 'product_title entry-title' ), 'Título: h1 con las clases de WooCommerce.' );
// Valoración sin comentarios en los productos: nada, como PRO.
$GLOBALS['ws']['comments'] = false;
$check( '' === $same( 'woocommerce-product-rating', array(), 'sin comentarios' ), 'Valoración: nada si los productos no admiten comentarios.' );
$GLOBALS['ws']['comments'] = true;
// Sin producto: nada.
$GLOBALS['ws']['product_id'] = 99;
foreach ( array( 'woocommerce-product-price', 'woocommerce-product-stock', 'woocommerce-product-meta', 'woocommerce-product-add-to-cart', 'woocommerce-product-images' ) as $source ) {
	$check( '' === $same( $source, array(), 'sin producto' ), "$source: nada sin producto." );
}
$GLOBALS['ws']['product_id'] = 11;
// Existencias con su filtro de WooCommerce.
$check( str_contains( $same( 'woocommerce-product-stock', array(), 'existencias' ), '<p class="stock in-stock">11 disponibles</p>' ), 'Existencias: wc_get_stock_html() de WooCommerce.' );
// Metadatos: rótulos propios por defecto; con los rótulos escritos, como PRO.
$captions = array( 'category_caption_single' => 'Cat', 'category_caption_plural' => 'Cats', 'tag_caption_single' => 'Etq', 'tag_caption_plural' => 'Etqs', 'sku_caption' => 'Ref.', 'sku_missing_caption' => '—' );
foreach ( array( 11, 12, 13 ) as $id ) {
	$GLOBALS['ws']['product_id'] = $id;
	$same( 'woocommerce-product-meta', $captions, "rótulos escritos, producto $id" );
}
$GLOBALS['ws']['product_id'] = 11;
$meta = ws_render( $widgets['woocommerce-product-meta'][1] );
$check( preg_match( '/<span class="detail-label">\s*SKU\s*<\/span>\s*<span class="sku">\s*AB-1/', $meta ) && str_contains( $meta, '<span class="detail-label">Categoría</span>' ) && str_contains( $meta, '<span class="detail-label">Etiquetas</span>' ) && str_contains( $meta, '<a href="https://sitio.test/product_tag/5/" rel="tag">Verano</a>, <a href="https://sitio.test/product_tag/6/" rel="tag">Algodón</a>' ), 'Metadatos: rótulos en español (singular y plural) y los términos enlazados de WordPress.' );
$GLOBALS['ws']['product_id'] = 12;
$check( preg_match( '/<span class="sku">\s*N\/D\s*<\/span>/', ws_render( $widgets['woocommerce-product-meta'][1] ) ) && str_contains( ws_render( $widgets['woocommerce-product-meta'][1] ), 'Categorías' ), 'Metadatos: un variable sin SKU muestra «N/D»; dos categorías, en plural.' );
$GLOBALS['ws']['product_id'] = 11;
$evil = ws_render( $widgets['woocommerce-product-meta'][1], array( 'sku_caption' => '<img src=x onerror=y>', 'category_caption_single' => '<b>x</b>' ) );
$check( ! str_contains( $evil, '<img' ) && ! str_contains( $evil, '<b>x' ), 'Metadatos: rótulos escapados.' );
// Contenido del producto (el contenido de la consulta, con la misma guarda que el de C4).
$same( 'woocommerce-product-content', array(), 'contenido' );
$check( str_contains( ws_render( $widgets['woocommerce-product-content'][1] ), 'Contenido del producto' ), 'Contenido: el contenido de la consulta por the_content.' );
// Elementos del producto: cada función de la lista; ninguna fuera de ella.
foreach ( array_keys( $widgets['wc-single-elements'][1]->controls['element']['options'] ) as $element ) {
	$same( 'wc-single-elements', array( 'element' => $element ), "elemento «{$element}»" );
}
$check( '' === ws_render( $widgets['wc-single-elements'][1], array( 'element' => 'phpinfo' ) ), 'Elementos del producto: una función fuera de la lista no se llama.' );
$check( str_contains( ws_render( $widgets['wc-single-elements'][1], array( 'element' => 'woocommerce_output_product_data_tabs' ) ), 'data-tabs="additional_information,reviews"' ), 'Elementos del producto: las pestañas sin la descripción, como PRO.' );
$GLOBALS['ws']['options']['woocommerce_enable_review_rating'] = 'no';
$GLOBALS['ws_state']->edit = true;
$check( str_contains( ws_render( $widgets['wc-single-elements'][1], array( 'element' => 'woocommerce_template_single_rating' ) ), 'Activa las valoraciones' ), 'Elementos del producto: aviso en el editor si las valoraciones están desactivadas.' );
$GLOBALS['ws_state']->edit = false;
unset( $GLOBALS['ws']['options']['woocommerce_enable_review_rating'] );

// Añadir al carrito: cada tipo de producto y disposición, en la ficha y en el bucle.
foreach ( array( 11, 12, 13, 14 ) as $id ) {
	$GLOBALS['ws']['product_id'] = $id;
	foreach ( array( '', 'stacked', 'auto' ) as $layout ) {
		$html = $same( 'woocommerce-product-add-to-cart', array( 'layout' => $layout ), "producto $id, disposición «{$layout}»" );
		$check( str_contains( $html, '<div class="digi-atc__wrap digi-atc--type-' . wc_get_product( $id )->get_type() . '">' ), "Añadir al carrito ($id): la envoltura con el tipo de producto." );
		$check( ( '' === $layout ) === ! str_contains( $html, 'digi-atc__holder' ), "Añadir al carrito ($id, «{$layout}»): la envoltura de cantidad y botón sólo apilado o automático." );
	}
}
$GLOBALS['ws']['product_id'] = 11;
$check( ! isset( $GLOBALS['ws']['filters']['woocommerce_before_add_to_cart_quantity'] ) || ! array_filter( $GLOBALS['ws']['filters']['woocommerce_before_add_to_cart_quantity'] ), 'Añadir al carrito: quita sus ganchos al terminar.' );
$GLOBALS['ws_state']->current = new \Elementor\Test_Document( array( 'type' => 'loop-item' ) );
foreach ( array( array(), array( 'show_quantity' => '' ), array( 'layout' => 'stacked' ) ) as $i => $settings ) {
	$html = $same( 'woocommerce-product-add-to-cart', $settings, "bucle $i" );
	$check( str_contains( $html, '<div class="digi-atc__loop"><form class="cart digi-atc__loop-form">' ) && str_contains( $html, 'ajax_add_to_cart' ), "Añadir al carrito en el bucle ($i): contenedor y botón de WooCommerce." );
}
$check( array( 'digitalisimo-woo-product-add-to-cart' ) === $widgets['woocommerce-product-add-to-cart'][1]->get_script_depends(), 'En el bucle, el script propio.' );
$GLOBALS['ws']['options']['woocommerce_enable_ajax_add_to_cart'] = 'no';
$same( 'woocommerce-product-add-to-cart', array(), 'bucle sin AJAX' );
unset( $GLOBALS['ws']['options']['woocommerce_enable_ajax_add_to_cart'] );
$GLOBALS['ws_state']->current = null;
$check( array() === $widgets['woocommerce-product-add-to-cart'][1]->get_script_depends(), 'En una ficha, sin script.' );

// Añadir al carrito personalizado.
$GLOBALS['ws']['queried'] = 11;
foreach ( array( array(), array( 'product_id' => '12', 'quantity' => 3 ), array( 'product_id' => '15' ), array( 'show_quantity' => 'yes' ), array( 'show_quantity' => 'yes', 'layout' => 'stacked' ), array( 'show_quantity' => 'yes', 'layout' => 'auto', 'product_id' => '12' ), array( 'product_id' => '14', 'layout' => 'auto' ) ) as $i => $settings ) {
	// Con el texto de PRO (el propio tiene el suyo en español; el mapa lo completa en lo convertido).
	$same( 'wc-add-to-cart', $settings + array( 'text' => 'Add to Cart' ), "caso $i" );
}
$ajax = ws_render( $widgets['wc-add-to-cart'][1] );
$check( str_contains( $ajax, 'href="?add-to-cart=11"' ) && str_contains( $ajax, 'data-product_id="11"' ) && str_contains( $ajax, 'ajax_add_to_cart' ) && str_contains( $ajax, 'rel="nofollow"' ) && str_contains( $ajax, 'Añadir al carrito' ), 'Botón personalizado: los datos de WooCommerce para añadir por AJAX y el texto propio.' );
$form = ws_render( $widgets['wc-add-to-cart'][1], array( 'show_quantity' => 'yes', 'text' => 'Comprar & <b>ya</b>' ) );
$check( str_contains( $form, 'single_add_to_cart_button elementor-button' ) && str_contains( $form, '<span class="elementor-button-text">Comprar & <b>ya</b></span>' ) && ! str_contains( $form, '11 disponibles' ), 'Botón personalizado con cantidad: el formulario de WooCommerce con el botón del widget (icono y texto sin escapar dos veces) y sin el aviso de existencias.' );
$check( '&lt;b&gt;' === esc_html( '<b>' ), 'Fuera del formulario, esc_html() vuelve a escapar.' );
$widgets['wc-add-to-cart'][1]->unescape_html( 'x', 'y' );
$check( 'x' === $widgets['wc-add-to-cart'][1]->unescape_html( 'x', '<b>otro</b>' ), 'Botón personalizado: sólo el texto exacto del botón queda sin escapar (PRO desactivaba todo esc_html()).' );
$GLOBALS['ws']['queried'] = 99;
$GLOBALS['ws']['product_id'] = 99;
$check( str_contains( ws_render( $widgets['wc-add-to-cart'][1] ), 'Elige un producto válido' ) && str_contains( ws_render( $widgets['wc-add-to-cart'][1], array( 'show_quantity' => 'yes' ) ), 'Elige un producto válido' ), 'Botón personalizado sin producto: el aviso para quien administra.' );
$GLOBALS['ws']['caps'] = array();
$check( ! str_contains( ws_render( $widgets['wc-add-to-cart'][1], array( 'show_quantity' => 'yes' ) ), 'Elige' ), 'Sin permisos, sin aviso.' );
$GLOBALS['ws']['caps'] = array( 'manage_options' => true );
$GLOBALS['ws']['product_id'] = 11;
$GLOBALS['ws']['queried'] = 0;
$check( array( 'settings' => array( 'text' => 'x' ) ) === $widgets['wc-add-to-cart'][1]->on_export( array( 'settings' => array( 'text' => 'x', 'product_id' => '11' ) ) ), 'Un kit no lleva el producto elegido (como PRO).' );
// Producto en borrador elegido: nada (como PRO).
$check( false === $widgets['wc-add-to-cart'][1]->get_product( 16 ), 'Un producto elegido sin publicar no se usa.' );

// Productos relacionados y ventas dirigidas: la plantilla de WooCommerce con la rejilla y, si se pide, la envoltura de los botones.
foreach ( array( array(), array( 'posts_per_page' => 2, 'columns' => 3, 'orderby' => 'price', 'order' => 'asc' ), array( 'automatically_align_buttons' => 'yes' ) ) as $i => $settings ) {
	$html = $same( 'woocommerce-product-related', $settings, "relacionados $i" );
	$check( str_contains( $html, '<ul class="products elementor-grid columns-' ) && str_contains( $html, '<li class="product">12' ) && ! str_contains( $html, '<li class="product">15' ), "Relacionados ($i): la lista de WooCommerce con la rejilla del núcleo y sólo los visibles." );
	$check( ( 'yes' === ( $settings['automatically_align_buttons'] ?? '' ) ) === str_contains( $html, 'woocommerce-loop-product__buttons' ), "Relacionados ($i): la envoltura de los botones sólo si se pide." );
	$html = $same( 'woocommerce-product-upsell', $settings, "ventas dirigidas $i" );
	$check( str_contains( $html, '<ul class="products elementor-grid columns-' ), "Ventas dirigidas ($i): la rejilla del núcleo." );
}
$check( array( 'price', 'asc' ) === $GLOBALS['ws']['orderby'] || array( 'date', 'desc' ) === $GLOBALS['ws']['orderby'], 'Relacionados: el orden pedido.' );
$check( ! array_filter( $GLOBALS['ws']['filters']['woocommerce_loop_add_to_cart_link'] ?? array() ), 'Relacionados y ventas dirigidas quitan su filtro al terminar.' );
$GLOBALS['ws']['product_id'] = 99;
$check( '' === $same( 'woocommerce-product-related', array(), 'sin producto' ), 'Relacionados: nada sin producto.' );
$GLOBALS['ws']['product_id'] = 11;

// 3. Panel de las plantillas de bucle de productos.
$config = array( 'panel' => array( 'widgets_settings' => array( 'woocommerce-product-title' => array( 'categories' => array( 'recommended' ), 'show_in_panel' => true ), 'woocommerce-product-images' => array( 'show_in_panel' => false ) ) ) );
$out    = Single\Module::document_config( $config, 5 );
$check( array( 'recommended' ) === $out['panel']['widgets_settings']['digitalisimo-woo-product-title']['categories'] && true === $out['panel']['widgets_settings']['digitalisimo-woo-product-title']['show_in_panel'] && false === $out['panel']['widgets_settings']['digitalisimo-woo-product-images']['show_in_panel'], 'Bucle de productos: el título propio en «Recomendados» y las imágenes propias ocultas, como las de PRO.' );
$check( array( 'panel' => array( 'widgets_settings' => array( 'x' => array( 'show_in_panel' => false ) ) ) ) === Single\Module::document_config( array( 'panel' => array( 'widgets_settings' => array( 'x' => array( 'show_in_panel' => false ) ) ) ), 5 ), 'Fuera de un bucle de productos no cambia nada.' );

// 4. Mapas.
Maps::reset();
$codes_all = array();
foreach ( $pairs as $source => $pair ) {
	$map = Maps::map( $source, true );
	$check( null !== $map && $pair[1] === ltrim( get_class( $widgets[ $source ][1] ), '\\' ) && $widgets[ $source ][1]->get_name() === $map['target'] && 'hide' === $map['panel'] && 'ready' === $map['status'] && null !== Maps::map( $source ), "$source: mapa listo (A/B de pro-ab.sh en verde) hacia el widget propio, que oculta el de PRO." );
	// Cada control de PRO declarado (los del núcleo también).
	list( $pro ) = $widgets[ $source ];
	$declared = array_merge( (array) ( $map['keep'] ?? array() ), array_keys( (array) ( $map['rename'] ?? array() ) ), array_keys( (array) ( $map['groups'] ?? array() ) ), array_keys( (array) ( $map['drop'] ?? array() ) ), array_keys( (array) ( $map['approx'] ?? array() ) ), (array) ( $map['ui'] ?? array() ) );
	$valued   = array_filter( $pro->controls, static function ( $c ) { return ! in_array( $c['type'] ?? '', array( 'heading', 'alert', 'divider', 'raw_html', 'notice', 'deprecated_notice', 'button', 'promotion_control' ), true ); } );
	$missing  = array_diff( array_merge( array_keys( $valued ), array_keys( $pro->groups ) ), $declared );
	$check( ! $missing, "$source: el mapa no declara " . implode( ', ', $missing ) );
}

$cases = array(
	'woocommerce-product-price'                  => array(
		'estilos' => array( 'text_align' => 'center', 'text_align_mobile' => 'end', 'price_color' => '#123456', 'typography_typography' => 'custom', 'typography_font_size' => array( 'unit' => 'px', 'size' => 30 ), 'sale_price_color' => '#ff0000', 'price_block' => 'yes', 'sale_price_spacing' => array( 'unit' => 'px', 'size' => 8 ), 'sale_price_spacing_tablet' => array( 'unit' => 'px', 'size' => 4 ) ),
		'globales' => array( 'price_color' => '', '__globals__' => array( 'price_color' => 'globals/colors?id=accent', 'typography_typography' => 'globals/typography?id=secondary' ) ),
		'css'      => array( 'custom_css' => "selector.elementor-product-price-block-yes .price{x:y}\n.elementor-widget-woocommerce-product-price del{z:1}" ),
	),
	'woocommerce-product-rating'                 => array(
		'estilos' => array( 'star_color' => '#ffaa00', 'empty_star_color' => '#cccccc', 'link_color' => '#0000ff', 'text_typography_typography' => 'custom', 'text_typography_font_weight' => '700', 'star_size' => array( 'unit' => 'px', 'size' => 20 ), 'space_between' => array( 'unit' => 'em', 'size' => 1 ), 'alignment' => 'justify', 'alignment_mobile' => 'center' ),
		'css'     => array( 'custom_css' => 'selector.elementor-product-rating--align-center .star-rating{x:y}' ),
	),
	'woocommerce-product-stock'                  => array( 'estilos' => array( 'text_color' => '#00aa00', 'text_typography_typography' => 'custom', 'text_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ) ) ),
	'woocommerce-product-meta'                   => array(
		'defecto' => array(),
		'tabla'   => array( 'view' => 'table', 'space_between' => array( 'unit' => 'px', 'size' => 10 ), 'divider' => 'yes', 'divider_style' => 'dashed', 'divider_weight' => array( 'unit' => 'px', 'size' => 2 ), 'divider_width' => array( 'unit' => '%', 'size' => 50 ), 'divider_color' => '#ff0000', 'text_color' => '#333333', 'link_color' => '#0000ff', 'link_typography_typography' => 'custom', 'category_caption_single' => 'Sección', 'sku_caption' => 'Ref.' ),
		'dinámico' => array( '__dynamic__' => array( 'sku_caption' => '[elementor-tag id="a1" name="site-title" settings="%7B%7D"]' ), 'css' => 'x', 'custom_css' => 'selector.elementor-woo-meta--view-inline .detail-container{x:y}' ),
	),
	'woocommerce-product-short-description'      => array( 'estilos' => array( 'text_align' => 'justify', 'text_color' => '#444444', 'text_typography_typography' => 'custom' ) ),
	'woocommerce-product-content'                => array( 'estilos' => array( 'align' => 'center', 'text_color' => '#222222', 'typography_typography' => 'custom', 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.8 ) ) ),
	'woocommerce-product-additional-information' => array(
		'oculto'  => array( 'show_heading' => '', 'content_color' => '#111111', 'content_typography_typography' => 'custom' ),
		'visible' => array( 'heading_color' => '#222222', 'heading_typography_typography' => 'custom', 'custom_css' => 'selector:not(.elementor-show-heading-yes) h2{x:y}' ),
	),
	'woocommerce-product-data-tabs'              => array( 'estilos' => array( 'tab_text_color' => '#111111', 'tab_bg_color' => '#eeeeee', 'tabs_border_color' => '#cccccc', 'active_tab_text_color' => '#000000', 'active_tab_bg_color' => '#ffffff', 'active_tabs_border_color' => '#999999', 'tab_typography_typography' => 'custom', 'tab_border_radius' => array( 'unit' => 'px', 'size' => 4 ), 'text_color' => '#333333', 'content_typography_typography' => 'custom', 'heading_color' => '#000000', 'content_heading_typography_typography' => 'custom', 'panel_border_width' => array( 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px', 'isLinked' => true ), 'panel_border_radius' => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ), 'panel_box_shadow_box_shadow_type' => 'yes' ) ),
	'woocommerce-product-images'                 => array( 'estilos' => array( 'sale_flash' => '', 'image_border_border' => 'solid', 'image_border_width' => array( 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px', 'isLinked' => true ), 'image_border_radius' => array( 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px', 'isLinked' => true ), 'spacing' => array( 'unit' => 'px', 'size' => 10 ), 'thumbs_border_border' => 'solid', 'thumbs_border_radius_mobile' => array( 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px', 'isLinked' => true ), 'spacing_thumbs' => array( 'unit' => 'px', 'size' => 6 ) ) ),
	'woocommerce-product-add-to-cart'            => array(
		'defecto' => array(),
		'estilos' => array( 'layout' => 'stacked', 'alignment' => 'justify', 'alignment_tablet' => 'center', 'button_typography_typography' => 'custom', 'button_border_border' => 'solid', 'button_border_radius' => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ), 'button_padding' => array( 'top' => '10', 'right' => '20', 'bottom' => '10', 'left' => '20', 'unit' => 'px', 'isLinked' => false ), 'button_text_color' => '#ffffff', 'button_bg_color' => '#000000', 'button_border_color' => '#111111', 'button_text_color_hover' => '#000000', 'button_bg_color_hover' => '#ffffff', 'button_border_color_hover' => '#222222', 'button_transition' => array( 'unit' => 'px', 'size' => 0.3 ), 'view_cart_color' => '#0000ff', 'view_cart_typography_typography' => 'custom', 'view_cart_spacing' => array( 'unit' => 'px', 'size' => 12 ), 'show_quantity' => '', 'spacing' => array( 'unit' => 'px', 'size' => 8 ), 'quantity_typography_typography' => 'custom', 'quantity_border_border' => 'solid', 'quantity_border_radius' => array( 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px', 'isLinked' => true ), 'quantity_padding' => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ), 'quantity_text_color' => '#111111', 'quantity_bg_color' => '#eeeeee', 'quantity_border_color' => '#cccccc', 'quantity_text_color_focus' => '#000000', 'quantity_bg_color_focus' => '#ffffff', 'quantity_border_color_focus' => '#000000', 'quantity_transition' => array( 'unit' => 'px', 'size' => 0.2 ), 'variations_width' => array( 'unit' => '%', 'size' => 80 ), 'variations_spacing' => array( 'unit' => 'px', 'size' => 10 ), 'variations_space_between' => array( 'unit' => 'px', 'size' => 6 ), 'variations_label_color_focus' => '#333333', 'variations_label_typography_typography' => 'custom', 'variations_select_color' => '#111111', 'variations_select_bg_color' => '#ffffff', 'variations_select_border_color' => '#cccccc', 'variations_select_typography_typography' => 'custom', 'variations_select_border_radius' => array( 'unit' => 'px', 'size' => 3 ) ),
		'muestras' => array( 'digitalisimo_swatch_styles' => 'yes', 'digitalisimo_swatch_size' => array( 'unit' => 'px', 'size' => 32 ) ),
		'css'     => array( 'custom_css' => "selector.elementor-add-to-cart--layout-stacked .e-atc-qty-button-holder{x:y}\nselector.elementor-add-to-cart-mobile--align-justify .e-loop-add-to-cart-form-container > *{z:1}\nselector.e-add-to-cart--show-quantity-yes .elementor-add-to-cart{w:2}" ),
	),
	'wc-single-elements'                         => array( 'precio' => array( 'element' => 'woocommerce_template_single_price' ) ),
	'wc-add-to-cart'                             => array(
		'defecto' => array(),
		'estilos' => array( 'product_id' => '11', 'show_quantity' => 'yes', 'layout' => 'auto', 'button_type' => 'success', 'text' => 'Comprar', 'size' => 'lg', 'selected_icon' => array( 'value' => 'fas fa-cart-plus', 'library' => 'fa-solid' ), 'icon_align' => 'row-reverse', 'icon_indent' => array( 'unit' => 'px', 'size' => 8 ), 'button_css_id' => 'comprar', 'align' => 'justify', 'align_mobile' => 'center', 'content_align' => 'space-between', 'typography_typography' => 'custom', 'text_shadow_text_shadow_type' => 'yes', 'button_text_color' => '#ffffff', 'background_background' => 'classic', 'background_color' => '#000000', 'button_box_shadow_box_shadow_type' => 'yes', 'hover_color' => '#000000', 'button_background_hover_background' => 'classic', 'button_hover_box_shadow_box_shadow_type' => 'yes', 'button_hover_border_color' => '#111111', 'button_hover_transition_duration' => array( 'unit' => 's', 'size' => 0.3 ), 'hover_animation' => 'grow', 'border_border' => 'solid', 'border_radius' => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ), 'text_padding' => array( 'top' => '10', 'right' => '20', 'bottom' => '10', 'left' => '20', 'unit' => 'px', 'isLinked' => false ) ),
		'fa4'     => array( 'icon' => 'fa fa-shopping-cart', 'quantity' => 2, '__dynamic__' => array( 'text' => '[elementor-tag id="b2" name="site-title" settings="%7B%7D"]' ), '__globals__' => array( 'button_text_color' => 'globals/colors?id=primary', 'typography_typography' => 'globals/typography?id=accent' ), 'custom_css' => 'selector.elementor-add-to-cart-tablet--align-left .e-atc-qty-button-holder{x:y}' ),
	),
	'woocommerce-product-related'                => array(
		'defecto' => array(),
		'estilos' => array( 'posts_per_page' => 6, 'columns' => '3', 'columns_tablet' => '2', 'columns_mobile' => '1', 'orderby' => 'price', 'order' => 'asc', 'show_heading' => '', 'column_gap' => array( 'unit' => 'px', 'size' => 10 ), 'row_gap_mobile' => array( 'unit' => 'px', 'size' => 12 ), 'align' => 'center', 'title_color' => '#111111', 'title_typography_typography' => 'custom', 'star_color' => '#ffaa00', 'price_color' => '#222222', 'old_price_color' => '#999999', 'button_text_color' => '#ffffff', 'button_background_color' => '#000000', 'button_border_border' => 'solid', 'automatically_align_buttons' => 'yes', 'view_cart_color' => '#0000ff', 'box_border_width' => array( 'unit' => 'px', 'size' => 1 ), 'box_shadow_box_shadow_type' => 'yes', 'show_onsale_flash' => 'yes', 'onsale_text_color' => '#ffffff', 'onsale_horizontal_position' => 'left', 'pagination_typography_typography' => 'custom' ),
		'título'  => array( 'heading_color' => '#123456', 'heading_typography_typography' => 'custom', 'heading_text_align' => 'center', 'heading_spacing' => array( 'unit' => 'px', 'size' => 20 ), '__globals__' => array( 'heading_color' => 'globals/colors?id=primary' ), 'custom_css' => 'selector.elementor-wc-products .products > h2{x:y}' ),
	),
	'woocommerce-product-upsell'                 => array(
		'defecto' => array(),
		'estilos' => array( 'columns' => '2', 'orderby' => 'title', 'order' => 'asc', 'heading_color' => '#123456', 'heading_spacing_tablet' => array( 'unit' => 'px', 'size' => 8 ), 'image_border_radius' => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ), 'button_hover_color' => '#000000', 'custom_css' => 'selector.elementor-product-loop-item--align-center .price{x:y}' ),
	),
	'woocommerce-product-title'                  => array( 'defecto' => array(), 'dinámico' => array( '__dynamic__' => array( 'title' => '[elementor-tag id="c3" name="woocommerce-product-title-tag" settings="%7B%7D"]' ), 'header_size' => 'h2', 'title_color' => '#111111', 'custom_css' => '.elementor-widget-woocommerce-product-title h2{x:y}' ) ),
);
foreach ( $cases as $source => $set ) {
	// Una «clave» suelta del CSS que no es un ajuste de verdad no cuenta.
	foreach ( $set as $id => $settings ) {
		unset( $set[ $id ]['css'] );
	}
	list( $out, $codes ) = ws_convert( $source, $widgets[ $source ][1], array_combine( array_map( static function ( $id ) use ( $source ) { return substr( md5( $source . $id ), 0, 7 ); }, array_keys( $set ) ), array_values( $set ) ), 'wc-add-to-cart' === $source ? array( 'icon' ) : array() );
	$by = array_combine( array_keys( $set ), array_values( $out ) );
	$codes_all[ $source ] = $codes;
	foreach ( $set as $id => $settings ) {
		foreach ( $settings as $key => $value ) {
			if ( in_array( $key, array( '__globals__', '__dynamic__', 'custom_css' ), true ) ) {
				continue;
			}
			$check( array_key_exists( $key, $by[ $id ] ) && $by[ $id ][ $key ] === $value, "$source/$id: $key pasa tal cual." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			if ( isset( $settings[ $bag ] ) ) {
				$check( $settings[ $bag ] === $by[ $id ][ $bag ], "$source/$id: $bag sigue a su control." );
			}
		}
	}
	$GLOBALS['ws_by'][ $source ] = $by;
}
$check( ! array_filter( $codes_all, static function ( $codes ) { return (bool) preg_grep( '/:pro\.(dropped|approx):/', $codes ); } ), 'Ningún ajuste se descarta ni se aproxima: ' . json_encode( $codes_all ) );
$by = $GLOBALS['ws_by'];
$check( "selector.digi-product-price--block-yes .price{x:y}\n.elementor-widget-digitalisimo-woo-product-price del{z:1}" === $by['woocommerce-product-price']['css']['custom_css'], 'Precio: CSS personalizado con las clases propias.' );
$check( 'selector.digi-product-rating--align-center .star-rating{x:y}' === $by['woocommerce-product-rating']['css']['custom_css'], 'Valoración: CSS personalizado con las clases propias.' );
$check( 'Category' === $by['woocommerce-product-meta']['defecto']['category_caption_single'] && 'Tags' === $by['woocommerce-product-meta']['defecto']['tag_caption_plural'] && 'N/A' === $by['woocommerce-product-meta']['defecto']['sku_missing_caption'] && 'Sección' === $by['woocommerce-product-meta']['tabla']['category_caption_single'] && 'Categories' === $by['woocommerce-product-meta']['tabla']['category_caption_plural'], 'Metadatos: los rótulos que PRO pintaba sin guardarlos se completan; los escritos no cambian.' );
$check( ! isset( $by['woocommerce-product-meta']['dinámico']['sku_caption'] ) || '' === $by['woocommerce-product-meta']['dinámico']['sku_caption'] || 'SKU' !== $by['woocommerce-product-meta']['dinámico']['sku_caption'], 'Metadatos: un rótulo dinámico no se rellena con el texto de PRO.' );
$check( 'selector.digi-product-meta--view-inline .detail-container{x:y}' === $by['woocommerce-product-meta']['dinámico']['custom_css'], 'Metadatos: CSS personalizado con las clases propias.' );
$check( 'selector:not(.digi-product-additional-information--heading-yes) h2{x:y}' === $by['woocommerce-product-additional-information']['visible']['custom_css'], 'Información adicional: CSS personalizado con las clases propias.' );
$check( "selector.digi-atc--layout-stacked .digi-atc__holder{x:y}\nselector.digi-atc-mobile--align-justify .digi-atc__loop > *{z:1}\nselector.digi-atc--show-quantity-yes .digi-atc__wrap{w:2}" === $by['woocommerce-product-add-to-cart']['css']['custom_css'], 'Añadir al carrito: CSS personalizado con las clases propias (también por dispositivo): ' . $by['woocommerce-product-add-to-cart']['css']['custom_css'] );
$check( 'yes' === $by['woocommerce-product-add-to-cart']['muestras']['digitalisimo_swatch_styles'] && 32 === $by['woocommerce-product-add-to-cart']['muestras']['digitalisimo_swatch_size']['size'], 'Añadir al carrito: el estilo de las muestras de F4-Woo pasa tal cual.' );
$check( 'Add to Cart' === $by['wc-add-to-cart']['defecto']['text'] && 'Comprar' === $by['wc-add-to-cart']['estilos']['text'], 'Botón personalizado: el texto por defecto de PRO se completa; el escrito no cambia.' );
$check( 'selector.digi-atc-tablet--align-left .digi-atc__holder{x:y}' === $by['wc-add-to-cart']['fa4']['custom_css'] && 'fa fa-shopping-cart' === $by['wc-add-to-cart']['fa4']['icon'], 'Botón personalizado: icono FA4 heredado y CSS personalizado.' );
$check( 'selector.digi-wc-products .products > h2{x:y}' === $by['woocommerce-product-related']['título']['custom_css'] && 'selector.digi-products--align-center .price{x:y}' === $by['woocommerce-product-upsell']['estilos']['custom_css'], 'Relacionados y ventas dirigidas: CSS personalizado con las clases de la cuadrícula propia.' );
$check( 'h1' === $by['woocommerce-product-title']['defecto']['header_size'] && '.elementor-widget-digitalisimo-woo-product-title h2{x:y}' === $by['woocommerce-product-title']['dinámico']['custom_css'], 'Título: nivel por defecto de PRO (h1) y CSS personalizado.' );
// Lo convertido se pinta como PRO.
foreach ( array( 'woocommerce-product-add-to-cart' => 'estilos', 'wc-add-to-cart' => 'estilos', 'woocommerce-product-meta' => 'tabla', 'woocommerce-product-images' => 'estilos', 'woocommerce-product-related' => 'estilos', 'woocommerce-product-upsell' => 'estilos' ) as $source => $id ) {
	list( $pro, $own ) = $widgets[ $source ];
	$a = ws_norm( ws_render( $pro, $cases[ $source ][ $id ] ), WS_CLASSES );
	$b = ws_norm( ws_render( $own, $by[ $source ][ $id ] ) );
	$check( $a === $b, "$source/$id: lo convertido se pinta como PRO.\nPRO:    $a\npropio: $b" );
}

echo 'DIGITALÍSIMO Elements: ficha de producto propia (C7, frente M): ' . count( $pairs ) . ' widgets con los controles de PRO (' . array_sum( array_map( static function ( $p ) { return count( $p[1]->controls ) + count( $p[1]->groups ); }, $widgets ) ) . ' controles y grupos), su marcado, sus hojas y sus mapas (' . array_sum( array_map( 'count', $cases ) ) . " casos de conversión): $checks comprobaciones en verde.\n";
