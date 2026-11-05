<?php
// Sitio con widgets Blockquote de PRO: una página (con uno anidado), una plantilla de cabecera y una revisión; una
// página con los cinco carruseles de PRO (C1), otra con los catorce widgets de contenido de PRO y una cuenta regresiva
// de Element Pack (C2), otra con los cuatro widgets de navegación de PRO y el Navbar y el Mega Menu de Element Pack,
// más una plantilla de cabecera con el Menú y la Búsqueda de PRO (C3), una plantilla de entrada con los elementos de
// tema (C4) y los treinta widgets de WooCommerce de PRO en una plantilla de producto, una de archivo de productos, una
// página y una cabecera (C7).
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
activate_plugin( 'digitalisimo-elements/pro-elements.php' );
activate_plugin( 'digitalisimo-migration/digitalisimo-migration.php' );
update_option( 'elementor_element_cache_ttl', 'disable' );
$quote = static function ( $id, array $settings ) {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'blockquote', 'settings' => $settings, 'elements' => array() );
};
$page_data = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
	$quote( 'q1', array( 'blockquote_content' => 'Cita de la página', 'author_name' => 'Ana', 'blockquote_skin' => 'boxed', 'custom_css' => 'selector .elementor-blockquote__content{letter-spacing:1px}' ) ),
	array( 'id' => 'c2', 'elType' => 'container', 'settings' => array(), 'elements' => array( $quote( 'q2', array( 'blockquote_content' => 'Cita anidada', 'tweet_button' => '' ) ) ) ),
	array( 'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Encabezado' ), 'elements' => array() ),
) ) );
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página con citas', 'post_name' => 'pm-pagina' ) );
update_post_meta( $page, '_elementor_edit_mode', 'builder' );
update_post_meta( $page, '_elementor_data', wp_slash( wp_json_encode( $page_data ) ) );
// Revisión con el contenido de PRO (se cuenta aparte y no se convierte).
$rev = wp_save_post_revision( $page ) ?: _wp_put_post_revision( get_post( $page ) );
if ( $rev ) {
	update_metadata( 'post', $rev, '_elementor_data', wp_slash( wp_json_encode( $page_data ) ) );
}
$header = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Cabecera con cita', 'post_name' => 'pm-cabecera' ) );
update_post_meta( $header, '_elementor_template_type', 'header' );
update_post_meta( $header, '_elementor_edit_mode', 'builder' );
update_post_meta( $header, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c9', 'elType' => 'container', 'settings' => array(), 'elements' => array( $quote( 'q9', array( 'blockquote_content' => 'Cita de la cabecera' ) ) ) ) ) ) ) );
// C1: página con los cinco carruseles de PRO (el anidado, con dos contenedores hijos que se conservan).
$w = static function ( $id, $type, array $settings, array $children = array() ) {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => $children );
};
$slide = static function ( $id, $text ) {
	return array( 'id' => $id, 'elType' => 'container', 'isInner' => true, 'settings' => array(), 'elements' => array( array( 'id' => $id . 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => $text ), 'elements' => array() ) ) );
};
$carousels_data = array( array( 'id' => 'c5', 'elType' => 'container', 'settings' => array(), 'elements' => array(
	$w( 'n1', 'nested-carousel', array( 'carousel_items' => array( array( '_id' => 'i1', 'slide_title' => 'Uno' ), array( '_id' => 'i2', 'slide_title' => 'Dos' ) ), 'custom_css' => 'selector .swiper-slide{outline:1px solid}' ), array( $slide( 'n1a', 'Diapositiva uno' ), $slide( 'n1b', 'Diapositiva dos' ) ) ),
	$w( 's1', 'slides', array( 'slides' => array( array( '_id' => 'sa', 'heading' => 'Hola', 'description' => 'Texto', 'button_text' => 'Ver', 'background_color' => '#245eff' ) ) ) ),
	$w( 'm1', 'media-carousel', array() ),
	$w( 't1', 'testimonial-carousel', array( 'slides' => array( array( '_id' => 'ta', 'content' => 'Muy bien', 'name' => 'Ana', 'title' => 'Clienta' ) ) ) ),
	$w( 'r1', 'reviews', array( 'slides' => array( array( '_id' => 'ra', 'content' => 'Excelente', 'name' => 'Luis', 'title' => '@luis', 'rating' => 4.5 ) ) ) ),
) ) );
$carousels = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página con carruseles', 'post_name' => 'pm-carruseles' ) );
update_post_meta( $carousels, '_elementor_edit_mode', 'builder' );
update_post_meta( $carousels, '_elementor_data', wp_slash( wp_json_encode( $carousels_data ) ) );
// C2: página con los catorce widgets de contenido de PRO (dos con CSS personalizado) y una cuenta regresiva de Element
// Pack (`bdt-countdown`, en bucle de 5 h), que F8 lleva a la de PRO y la etapa `pro` a la propia en la misma pasada.
$content_data = array( array( 'id' => 'c7', 'elType' => 'container', 'settings' => array(), 'elements' => array(
	$w( 'e1', 'animated-headline', array() ),
	$w( 'e2', 'code-highlight', array( 'language' => 'php', 'code' => '<?php echo 1;' ) ),
	$w( 'e3', 'table-of-contents', array() ),
	$w( 'e4', 'share-buttons', array() ),
	$w( 'e5', 'call-to-action', array( 'title' => 'Llamada' ) ),
	$w( 'e6', 'flip-box', array( 'custom_css' => 'selector .elementor-flip-box__front{outline:1px solid}' ) ),
	$w( 'e7', 'price-list', array() ),
	$w( 'e8', 'price-table', array( 'heading' => 'Plan', 'custom_css' => 'selector .elementor-price-table__heading{letter-spacing:1px}' ) ),
	$w( 'e9', 'gallery', array() ),
	$w( 'e10', 'hotspot', array() ),
	$w( 'e11', 'lottie', array() ),
	$w( 'e12', 'video-playlist', array() ),
	$w( 'e13', 'countdown', array( 'due_date' => '2030-01-04 10:05' ) ),
	$w( 'e14', 'progress-tracker', array( 'type' => 'circular' ) ),
	$w( 'ep1', 'bdt-countdown', array( 'loop_time' => 'yes', 'loop_hours' => 5 ) ),
) ) );
$content = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página con contenido', 'post_name' => 'pm-contenido' ) );
update_post_meta( $content, '_elementor_edit_mode', 'builder' );
update_post_meta( $content, '_elementor_data', wp_slash( wp_json_encode( $content_data ) ) );
// C3: menú de WordPress con un submenú; página con los cuatro widgets de navegación de PRO (el Mega Menu y el Off-Canvas
// con sus contenedores hijos, que se conservan; el Menú con CSS personalizado) y el Navbar y el Mega Menu de Element Pack,
// que F8 lleva al Menú de PRO y la etapa `pro` al propio en la misma pasada; y una cabecera con el Menú y la Búsqueda.
$menu_id = wp_create_nav_menu( 'pm-menu' );
if ( ! is_wp_error( $menu_id ) ) {
	$parent = wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Servicios', 'menu-item-url' => home_url( '/servicios/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Diseño', 'menu-item-url' => home_url( '/diseno/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-parent-id' => $parent ) );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Contacto', 'menu-item-url' => '#contacto', 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
}
$nav_data = array( array( 'id' => 'c11', 'elType' => 'container', 'settings' => array(), 'elements' => array(
	$w( 'v1', 'nav-menu', array( 'menu' => 'pm-menu', 'pointer' => 'framed', 'custom_css' => 'selector .elementor-nav-menu--main .elementor-item{letter-spacing:1px}' ) ),
	$w( 'v2', 'mega-menu', array( 'menu_items' => array( array( '_id' => 'mi1', 'item_title' => 'Servicios', 'item_dropdown_content' => 'yes' ), array( '_id' => 'mi2', 'item_title' => 'Equipo', 'item_dropdown_content' => 'yes' ) ) ), array( $slide( 'v2a', 'Contenido de servicios' ), $slide( 'v2b', 'Contenido del equipo' ) ) ),
	$w( 'v3', 'off-canvas', array( 'off_canvas_name' => 'Panel' ), array( $slide( 'v3a', 'Contenido del panel' ) ) ),
	$w( 'v4', 'search', array( 'search_input_placeholder_text' => 'Buscar en el sitio' ) ),
	$w( 'ep2', 'bdt-navbar', array( 'navbar' => 'pm-menu', 'align' => 'center' ) ),
	$w( 'ep3', 'bdt-mega-menu', array( 'navbar' => 'pm-menu', 'ep_megamenu_direction' => 'vertical', 'show_hamburger_menu' => 'tablet' ) ),
) ) );
$navigation = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página con navegación', 'post_name' => 'pm-navegacion' ) );
update_post_meta( $navigation, '_elementor_edit_mode', 'builder' );
update_post_meta( $navigation, '_elementor_data', wp_slash( wp_json_encode( $nav_data ) ) );
$nav_header = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Cabecera con navegación', 'post_name' => 'pm-cabecera-nav' ) );
update_post_meta( $nav_header, '_elementor_template_type', 'header' );
update_post_meta( $nav_header, '_elementor_edit_mode', 'builder' );
update_post_meta( $nav_header, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c12', 'elType' => 'container', 'settings' => array(), 'elements' => array( $w( 'v9', 'nav-menu', array( 'menu' => 'pm-menu' ) ), $w( 'v8', 'search', array() ) ) ) ) ) ) );
// C4: plantilla de entrada del Theme Builder con los quince elementos de tema de PRO y la Información de la entrada de
// Element Pack (`bdt-post-info`), que F8 lleva a la de PRO y la etapa `pro` a la propia en la misma pasada.
$single_data = array( array( 'id' => 'c8', 'elType' => 'container', 'settings' => array(), 'elements' => array(
	$w( 'g1', 'theme-site-logo', array( 'width' => array( 'unit' => 'px', 'size' => 120 ) ) ),
	$w( 'g2', 'theme-site-title', array( 'header_size' => 'p' ) ),
	$w( 'g3', 'theme-page-title', array() ),
	$w( 'g4', 'theme-post-title', array( 'header_size' => 'h1', 'custom_css' => 'selector.elementor-widget-theme-post-title{margin:0}' ) ),
	$w( 'g5', 'theme-archive-title', array() ),
	$w( 'g6', 'theme-post-excerpt', array( 'align' => 'center' ) ),
	$w( 'g7', 'theme-post-content', array() ),
	$w( 'g8', 'theme-post-featured-image', array( 'image_size' => 'medium' ) ),
	$w( 'h1', 'post-info', array( 'icon_list' => array( array( '_id' => 'pa', 'type' => 'author' ), array( '_id' => 'pb', 'type' => 'date' ) ), 'custom_css' => 'selector .elementor-icon-list-item{gap:4px}' ) ),
	$w( 'h2', 'post-navigation', array( 'prev_label' => 'Anterior', 'next_label' => 'Siguiente' ) ),
	$w( 'h3', 'author-box', array( 'source' => 'custom', 'author_name' => 'Ana' ) ),
	$w( 'h4', 'post-comments', array() ),
	$w( 'h5', 'search-form', array( 'placeholder' => 'Buscar' ) ),
	$w( 'h6', 'sitemap', array( 'sitemap_items' => array( array( '_id' => 'sx', 'sitemap_type_selector' => 'post_type', 'sitemap_source_post_type' => 'page', 'sitemap_title' => 'Páginas' ) ) ) ),
	$w( 'h7', 'breadcrumbs', array( 'html_tag' => 'nav' ) ),
	$w( 'e1', 'bdt-post-info', array() ),
) ) );
$single = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Entrada individual', 'post_name' => 'pm-entrada' ) );
update_post_meta( $single, '_elementor_template_type', 'single-post' );
update_post_meta( $single, '_elementor_edit_mode', 'builder' );
update_post_meta( $single, '_elementor_data', wp_slash( wp_json_encode( $single_data ) ) );
wp_set_object_terms( $single, 'single-post', 'elementor_library_type' );
// C7 (WooCommerce, con el WooCommerce fijado que monta pro-migrate.sh): una plantilla de producto con los quince widgets
// de la ficha de PRO y el «Añadir al carrito» de Element Pack (`bdt-wc-add-to-cart` → `wc-add-to-cart` → propio); una de
// archivo de productos con los nueve de listados y archivo y la rejilla de Element Pack (`bdt-wc-products` →
// `woocommerce-products` → propio); una página con los seis de carrito, pago y cuenta; y una cabecera con el carrito del
// menú de PRO y el de Element Pack (`bdt-wc-mini-cart` → `woocommerce-menu-cart` → propio). F8 y la etapa `pro`, en la
// misma pasada.
$c7_doc = static function ( $slug, $title, $type, array $elements ) {
	$id = wp_insert_post( array( 'post_type' => $type ? 'elementor_library' : 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c' . substr( md5( $slug ), 0, 6 ), 'elType' => 'container', 'settings' => array(), 'elements' => $elements ) ) ) ) );
	if ( $type ) {
		update_post_meta( $id, '_elementor_template_type', $type );
		wp_set_object_terms( $id, $type, 'elementor_library_type' );
	}
	return $id;
};
$product = $c7_doc( 'pm-producto', 'Producto individual', 'product', array(
	$w( 'w1', 'woocommerce-product-title', array( 'header_size' => 'h2', 'custom_css' => 'selector .product_title{margin:0}' ) ),
	$w( 'w2', 'woocommerce-product-price', array( 'price_color' => '#123456' ) ),
	$w( 'w3', 'woocommerce-product-rating', array() ),
	$w( 'w4', 'woocommerce-product-stock', array() ),
	$w( 'w5', 'woocommerce-product-meta', array( 'view' => 'table' ) ),
	$w( 'w6', 'woocommerce-product-short-description', array() ),
	$w( 'w7', 'woocommerce-product-content', array() ),
	$w( 'w8', 'woocommerce-product-additional-information', array() ),
	$w( 'w9', 'woocommerce-product-data-tabs', array() ),
	$w( 'w10', 'woocommerce-product-images', array() ),
	$w( 'w11', 'woocommerce-product-add-to-cart', array( 'show_quantity' => 'yes', 'custom_css' => 'selector .e-atc-qty-button-holder{gap:4px}' ) ),
	$w( 'w12', 'woocommerce-product-related', array( 'posts_per_page' => 4 ) ),
	$w( 'w13', 'woocommerce-product-upsell', array() ),
	$w( 'w14', 'wc-single-elements', array( 'element' => 'woocommerce_output_product_data_tabs' ) ),
	$w( 'w15', 'wc-add-to-cart', array( 'text' => 'Comprar' ) ),
	$w( 'ew1', 'bdt-wc-add-to-cart', array( 'text' => 'Comprar ya' ) ),
) );
$shop = $c7_doc( 'pm-tienda', 'Archivo de productos', 'product-archive', array(
	$w( 'x1', 'wc-archive-products', array( 'paginate' => 'yes' ) ),
	$w( 'x2', 'woocommerce-archive-products', array() ),
	$w( 'x3', 'woocommerce-archive-description', array() ),
	$w( 'x4', 'woocommerce-breadcrumb', array() ),
	$w( 'x5', 'woocommerce-notices', array() ),
	$w( 'x6', 'wc-categories', array( 'columns' => 3 ) ),
	$w( 'x7', 'woocommerce-category-image', array() ),
	$w( 'x8', 'woocommerce-products', array( 'columns' => 3, 'rows' => 1, 'custom_css' => 'selector .elementor-products-grid ul.products{gap:8px}' ) ),
	$w( 'x9', 'wc-products', array() ),
	$w( 'ew2', 'bdt-wc-products', array() ),
) );
$cart = $c7_doc( 'pm-carrito', 'Página de carrito', '', array(
	$w( 'k1', 'woocommerce-cart', array( 'cart_layout' => 'one-column' ) ),
	$w( 'k2', 'woocommerce-checkout-page', array() ),
	$w( 'k3', 'woocommerce-my-account', array() ),
	$w( 'k4', 'woocommerce-purchase-summary', array() ),
	$w( 'k5', 'woocommerce-menu-cart', array() ),
	$w( 'k6', 'wc-elements', array( 'element' => 'woocommerce_cart' ) ),
) );
$shop_header = $c7_doc( 'pm-cabecera-tienda', 'Cabecera de la tienda', 'header', array(
	$w( 'm1', 'woocommerce-menu-cart', array( 'custom_css' => 'selector .elementor-menu-cart__toggle .elementor-button{gap:4px}' ) ),
	$w( 'em1', 'bdt-wc-mini-cart', array() ),
) );
update_option( 'pm_ids', array( 'page' => $page, 'header' => $header, 'revision' => (int) $rev, 'carousels' => $carousels, 'content' => $content, 'navigation' => $navigation, 'nav_header' => $nav_header, 'single' => $single, 'product' => $product, 'shop' => $shop, 'cart' => $cart, 'shop_header' => $shop_header ) );
file_put_contents( '/wordpress/wp-content/probe/setup.json', wp_json_encode( get_option( 'pm_ids' ) ) );
