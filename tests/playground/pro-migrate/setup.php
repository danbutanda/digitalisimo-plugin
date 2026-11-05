<?php
// Sitio con widgets Blockquote de PRO: una página (con uno anidado), una plantilla de cabecera y una revisión; una
// página con los cinco carruseles de PRO (C1); otra con los catorce widgets de contenido de PRO y una cuenta regresiva
// de Element Pack (C2); y una plantilla de entrada con los elementos de tema (C4).
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
update_option( 'pm_ids', array( 'page' => $page, 'header' => $header, 'revision' => (int) $rev, 'carousels' => $carousels, 'content' => $content, 'single' => $single ) );
file_put_contents( '/wordpress/wp-content/probe/setup.json', wp_json_encode( get_option( 'pm_ids' ) ) );
