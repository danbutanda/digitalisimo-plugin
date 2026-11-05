<?php
// Sitio con widgets Blockquote de PRO: una página (con uno anidado), una plantilla de cabecera y una revisión; una
// página con los cinco carruseles de PRO (C1), otra con los catorce widgets de contenido de PRO y una cuenta regresiva
// de Element Pack (C2) y otra con los seis de entradas y bucles de PRO (con una plantilla de Elemento de bucle) y lo que
// F8 lleva hacia ellos desde Element Pack y Prime Slider (C5); la cabecera lleva además unas Entradas de PRO (C5).
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
update_post_meta( $header, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c9', 'elType' => 'container', 'settings' => array(), 'elements' => array( $quote( 'q9', array( 'blockquote_content' => 'Cita de la cabecera' ) ), array( 'id' => 'hp1', 'elType' => 'widget', 'widgetType' => 'posts', 'settings' => array( 'classic_posts_per_page' => 3 ), 'elements' => array() ) ) ) ) ) ) );
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
// C5: plantilla de Elemento de bucle, plantilla de sección (la del Dynamic Grid de Element Pack, que F8 copia como Loop
// Item) y una página con los seis widgets de entradas y bucles de PRO (el filtro apunta a la cuadrícula; tres con CSS
// personalizado) más una rejilla de entradas y un Dynamic Grid de Element Pack y un Blog de Prime Slider, que F8 lleva a
// Posts, Loop Grid y Loop Carousel de PRO y la etapa `pro` a los propios en la misma pasada.
$template = static function ( $slug, $type, array $meta = array() ) {
	$id = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
	foreach ( array( '_elementor_template_type' => $type, '_elementor_edit_mode' => 'builder' ) + $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'l' . substr( md5( $slug ), 0, 6 ), 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'h' . substr( md5( $slug ), 0, 6 ), 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Elemento' ), 'elements' => array() ) ) ) ) ) ) );
	return $id;
};
$loop_item = $template( 'pm-elemento-de-bucle', 'loop-item', array( '_elementor_source' => 'post' ) );
$card      = $template( 'pm-tarjeta', 'section' );
$loops_data = array( array( 'id' => 'c8', 'elType' => 'container', 'settings' => array(), 'elements' => array(
	$w( 'p1', 'posts', array( '_skin' => 'cards', 'cards_posts_per_page' => 3, 'custom_css' => 'selector .elementor-post__title{letter-spacing:1px}' ) ),
	$w( 'p2', 'archive-posts', array() ),
	$w( 'p3', 'portfolio', array( 'show_filter_bar' => 'yes' ) ),
	$w( 'p4', 'taxonomy-filter', array( 'selected_element' => 'p5', 'taxonomy' => 'category', 'custom_css' => 'selector .e-filter-item{letter-spacing:1px}' ) ),
	$w( 'p5', 'loop-grid', array( 'template_id' => (string) $loop_item, 'posts_per_page' => 3, 'custom_css' => 'selector .elementor-loop-container{outline:1px solid}' ) ),
	$w( 'p6', 'loop-carousel', array( 'template_id' => (string) $loop_item, 'posts_per_page' => 3 ) ),
	$w( 'ep2', 'bdt-post-grid', array( 'show_pagination' => 'yes' ) ),
	$w( 'ep3', 'bdt-dynamic-grid', array( 'template_id' => (string) $card, 'posts_per_page' => 4, 'columns' => 2 ) ),
	$w( 'ps1', 'prime-slider-blog', array() ),
) ) );
$loops = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Página con bucles', 'post_name' => 'pm-bucles' ) );
update_post_meta( $loops, '_elementor_edit_mode', 'builder' );
update_post_meta( $loops, '_elementor_data', wp_slash( wp_json_encode( $loops_data ) ) );
update_option( 'pm_ids', array( 'page' => $page, 'header' => $header, 'revision' => (int) $rev, 'carousels' => $carousels, 'content' => $content, 'loops' => $loops, 'loop_item' => $loop_item, 'card' => $card ) );
file_put_contents( '/wordpress/wp-content/probe/setup.json', wp_json_encode( get_option( 'pm_ids' ) ) );
