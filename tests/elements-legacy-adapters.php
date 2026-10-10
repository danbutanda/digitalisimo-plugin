<?php
namespace {
	define( 'ABSPATH', __DIR__ );
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function get_term( $id ) { return (object) array( 'term_id' => $id, 'term_taxonomy_id' => $id * 11 ); }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
	function get_bloginfo( $what ) { return 'Mi sitio'; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8', false ); }
	function esc_url( $v ) { return (string) $v; }
	function wp_kses_post( $v ) { return strip_tags( (string) $v, '<p><a><strong><em>' ); }
	function check_adapter( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$base = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-legacy/';
	require $base . 'class-translator.php';
	require $base . 'class-migration.php';
	use Digitalisimo\Elements\Legacy\Translator;
	use Digitalisimo\Elements\Legacy\Migration;

	// Todos los mapas cargan, apuntan a un widget propio o de Elementor Pro y sus estilos ya no nombran clases de Element Pack.
	$ids = Translator::ids();
	check_adapter( in_array( 'bdt-accordion', $ids, true ) && ! preg_grep( '/\.styles$/', $ids ), 'Los mapas deben listarse sin los archivos de estilos.' );
	foreach ( $ids as $id ) {
		$map = Translator::map( $id );
		check_adapter( is_array( $map ) && preg_match( '/^[a-z][a-z0-9-]*$/', (string) $map['target'] ) && 0 !== strpos( (string) $map['target'], 'bdt-' ), 'Mapa inválido: ' . $id );
		foreach ( Translator::styles( $id ) as $control ) {
			$css = wp_json_encode_stub( $control );
			check_adapter( ! empty( $control['name'] ) && false === strpos( $css, '.bdt-' ), 'Estilo con clase de Element Pack en ' . $id . ': ' . $control['name'] );
			check_adapter( isset( $control['group'] ) ? 0 === strpos( $control['selector'], '{{WRAPPER}}' ) : ! empty( $control['selectors'] ), 'Estilo sin selector en ' . $id );
		}
	}
	function wp_json_encode_stub( $value ) { return json_encode( $value ); }
	// Cada mapa tiene su clase adaptadora en Adapters::CLASSES y cada clase su mapa.
	$adapters = file_get_contents( $base . 'class-adapters.php' );
	preg_match_all( "/^\t\t'([a-z0-9-]+)' => (\w+)::class,$/m", $adapters, $registered );
	check_adapter( array() === array_diff( $ids, $registered[1] ) && array() === array_diff( $registered[1], $ids, array( 'bdt-animated-link' ) ), 'Mapas y clases adaptadoras deben coincidir: ' . implode( ', ', array_merge( array_diff( $ids, $registered[1] ), array_diff( $registered[1], $ids ) ) ) );
	foreach ( $registered[2] as $class ) {
		check_adapter( 1 === preg_match( '/final class ' . $class . ' extends /', $adapters ), 'Falta declarar ' . $class );
	}
	check_adapter( null === Translator::map( '../class-translator' ) && null === Translator::map( 'bdt-no-existe' ), 'Sólo se cargan mapas por ID válido.' );

	// Defaults de Element Pack: Elementor no guarda lo que coincide con ellos.
	$empty = Translator::translate( 'bdt-accordion', array() );
	check_adapter( 3 === count( $empty['tabs'] ) && 'Accordion #1' === $empty['tabs'][0]['tab_title'] && 'custom' === $empty['tabs'][0]['source'], 'Sin filas guardadas deben usarse las tres de Element Pack.' );
	check_adapter( 0 === $empty['active_item'] && 'span' === $empty['title_html_tag'] && 'fas fa-plus' === $empty['accordion_icon']['value'], 'Los defaults de Element Pack deben traducirse a valores propios.' );
	check_adapter( 'bdt-accordion' === $empty[ Translator::MARKER ] && ! isset( $empty['collapsible'], $empty['view'] ), 'Se marca la traducción y se descartan opciones sin equivalente.' );

	$custom = Translator::translate( 'bdt-accordion', array(
		'tabs'                    => array( array( '_id' => 'x', 'tab_title' => 'Uno' ) ),
		'active_item'             => '2',
		'always_active_all_items' => 'yes',
		'title_html_tag'          => 'h1',
		'_margin'                 => array( 'top' => '10' ),
		'title_color'             => '#123456',
	) );
	check_adapter( 2 === $custom['active_item'] && 'yes' === $custom['open_all_initially'] && ! isset( $custom['always_active_all_items'] ) && 'h2' === $custom['title_html_tag'], 'Renombres y valores deben aplicarse.' );
	check_adapter( 'Accordion Content' === $custom['tabs'][0]['tab_content'] && 'x' === $custom['tabs'][0]['_id'], 'Las filas completan defaults y conservan su ID.' );
	check_adapter( array( 'top' => '10' ) === $custom['_margin'] && '#123456' === $custom['title_color'], 'Ajustes comunes y de estilo pasan sin cambios.' );
	check_adapter( $custom === Translator::translate( 'bdt-accordion', $custom ), 'Un documento ya traducido no se vuelve a traducir.' );

	// Variantes responsivas, dinámicas y limpieza de HTML.
	$heading = Translator::translate( 'bdt-animated-heading', array(
		'pre_heading'        => 'Hola <b>soy</b>',
		'pre_heading_mobile' => 'x',
		'animated_heading'   => 'uno, dos ,tres,',
		'__dynamic__'        => array( 'post_heading' => '[elementor-tag id="1"]' ),
	) );
	check_adapter( 'Hola soy' === $heading['before_text'] && 'x' === $heading['before_text_mobile'] && ! isset( $heading['pre_heading'] ), 'Renombrar debe incluir variantes responsivas y quitar HTML.' );
	check_adapter( "uno\ndos\ntres" === $heading['animated_text'] && 'yes' === $heading['rotate'] && 2500 === $heading['interval'], 'Las palabras separadas por coma deben alternarse.' );
	check_adapter( isset( $heading['__dynamic__']['after_text'] ) && ! isset( $heading['__dynamic__']['post_heading'] ), 'Las etiquetas dinámicas siguen al ajuste renombrado.' );
	$split = Translator::translate( 'bdt-animated-heading', array( 'heading_layout' => 'split_text', 'animated_heading' => 'una, frase' ) );
	check_adapter( 'una, frase' === $split['animated_text'] && '' === $split['rotate'], 'El texto partido es una sola frase.' );

	$divider = Translator::translate( 'bdt-advanced-divider', array( 'advanced_divider_select' => 'line-star', 'divider_line_align' => 'left', 'max_width' => array( 'size' => 200 ) ) );
	check_adapter( 'star' === $divider['divider_type'] && 'left' === $divider['divider_align'] && 200 === $divider['divider_width']['size'], 'El separador usa la forma propia equivalente.' );
	$asset = Translator::translate( 'bdt-advanced-divider', array( 'advanced_divider_type' => 'choose', 'advanced_divider_choose' => array( 'url' => 'https://s.test/wp-content/plugins/bdthemes-element-pack/assets/images/divider/heart.svg' ) ) );
	$media = Translator::translate( 'bdt-advanced-divider', array( 'advanced_divider_type' => 'choose', 'advanced_divider_choose' => array( 'id' => 9, 'url' => 'https://s.test/wp-content/uploads/linea.svg' ) ) );
	check_adapter( 'line' === $asset['divider_type'] && 'image' === $media['divider_type'] && 9 === $media['divider_image']['id'], 'Sólo se conservan imágenes propias del sitio, no recursos de Element Pack.' );

	// Defaults de valor múltiple: las partes no guardadas toman el default, como en Elementor.
	$grid = Translator::translate( 'bdt-brand-grid', array( 'show_website_link' => 'yes', 'brand_items' => array( array( 'link' => array( 'url' => 'https://a.test/' ) ) ) ) );
	check_adapter( 'https://a.test/' === $grid['brand_items'][0]['link']['url'] && true === $grid['brand_items'][0]['link']['is_external'] && 'hover-item' === $grid['brand_event'] && 'medium' === $grid['image_size'], 'Los enlaces conservan externo y nofollow de Element Pack.' );
	$hidden = Translator::translate( 'bdt-brand-carousel', array( 'show_website_link' => '', 'navigation' => 'dots', 'brand_items' => array( array( 'link' => array( 'url' => 'https://a.test/' ) ) ) ) );
	check_adapter( ! isset( $hidden['brand_items'][0]['link'] ) && 'arrows' === $hidden['navigation'], 'Sin enlace visible en Element Pack, la marca no se enlaza; la navegación pasa a flechas.' );
	$crumbs = Translator::translate( 'bdt-breadcrumbs', array( 'breadcrumbs_separator' => '»' ) );
	check_adapter( 'custom' === $crumbs['separator'] && '»' === $crumbs['separator_custom'] && 'Mi sitio' === $crumbs['home_text'] && false !== strpos( $crumbs['__dynamic__']['home_text'], 'site-title' ) && 'yes' === $crumbs['show_home_only'], 'Breadcrumbs conserva separador libre y nombre dinámico del sitio.' );
	$dual = Translator::translate( 'bdt-dual-button', array( 'dual_button_size' => 'xl', 'button_a_onclick' => 'yes', 'button_a_onclick_event' => 'alert(1)', 'button_b_select_icon' => array( 'value' => 'fas fa-x' ) ) );
	check_adapter( 'large' === $dual['size'] && ! isset( $dual['button_a_onclick_event'] ) && 'fas fa-x' === $dual['button_b_icon']['value'] && 'Click Me' === $dual['button_a_text'], 'Dual Button traduce tamaño e iconos y descarta JavaScript libre.' );
	$button = Translator::translate( 'bdt-advanced-button', array() );
	check_adapter( 'solid' === $button['button_border_style'] && '#666' === $button['button_border_color'] && 'left' === $button['align'], 'Advanced Button repite el borde por defecto de Element Pack.' );

	$switch = Translator::translate( 'bdt-content-switcher', array( 'switcher_items' => array(
		array( 'title' => 'Mes', 'content_type' => 'price_card', 'price' => '99', 'period' => 'al mes', 'button_text' => 'Comprar', 'link' => array( 'url' => 'https://a.test/' ) ),
		array( 'title' => 'Año', 'content_type' => 'template', 'saved_templates' => '42' ),
		array( 'title' => 'Otro', 'content_type' => 'content' ),
	) ) );
	check_adapter( 2 === count( $switch['switcher_items'] ) && false !== strpos( $switch['switcher_items'][0]['content'], '<strong>$99</strong>' ) && false !== strpos( $switch['switcher_items'][0]['content'], 'href="https://a.test/"' ) && '42' === $switch['switcher_items'][1]['template_id'], 'Content Switcher traduce precio y plantilla y respeta las dos opciones del interruptor.' );
	$gallery = Translator::translate( 'bdt-custom-gallery', array( 'gallery' => array( array( 'gallery_image' => array( 'url' => 'https://a.test/f.jpg' ), 'image_link_type' => 'youtube', 'image_link_youtube' => array( 'url' => 'https://youtu.be/x' ) ) ) ) );
	check_adapter( isset( $gallery['gallery_items'][0] ) && ! isset( $gallery['gallery'] ) && 'https://youtu.be/x' === $gallery['gallery_items'][0]['image_link']['url'] && ! isset( $gallery['gallery_items'][0]['image_link_type'] ), 'La galería usa el repetidor propio y el destino elegido como enlace.' );
	$creative = Translator::translate( 'bdt-creative-button', array( 'button_style' => 'aura', 'alignment' => 'right' ) );
	check_adapter( 'glow' === $creative['effect'] && 'right' === $creative['align'] && 'Read More' === $creative['text'], 'Creative Button usa el efecto propio más parecido.' );
	$device = Translator::translate( 'bdt-device-slider', array( 'device_type' => 'macbookpro', 'slides' => array( array( 'title' => 'A', 'background' => 'image', 'image' => array( 'url' => 'x.png' ), 'title_link' => array( 'url' => 'https://a.test/' ) ), array( 'title' => 'B' ) ) ) );
	check_adapter( 'desktop' === $device['device_type'] && 'https://a.test/' === $device['slides'][0]['link']['url'] && ! isset( $device['slides'][1]['image'] ), 'Device Slider traduce marco, enlace y sólo diapositivas con imagen.' );
	$comparison = Translator::translate( 'bdt-comparison-list', array() );
	check_adapter( 'Feature list' === $comparison['comparison_list_title'] && 'Pro' === $comparison['comparison_header_list'][1]['header_title'] && '1|1' === $comparison['comparison_list'][2]['feature_ability'], 'Comparison List usa las filas por defecto de Element Pack.' );

	$card = Translator::translate( 'bdt-fancy-card', array( 'readmore' => '', 'global_link' => 'yes', 'global_link_url' => array( 'url' => 'https://a.test/' ), 'badge_text' => 'X' ) );
	check_adapter( '' === $card['button_text'] && 'https://a.test/' === $card['link']['url'] && '' === $card['badge_text'] && 'Laugh' === $card['title_text'] && ! isset( $card['readmore_link'] ), 'Fancy Card usa el enlace global sin botón y oculta el distintivo apagado.' );
	$icons = Translator::translate( 'bdt-fancy-icons', array() );
	$own   = Translator::translate( 'bdt-fancy-icons', array( 'background_image' => array( 'url' => 'https://a.test/fondo.jpg' ), 'background_attachment' => 'inherit' ) );
	check_adapter( ! isset( $icons['background_image'] ) && 'https://a.test/fondo.jpg' === $own['background_image']['url'] && '' === $own['background_attachment'], 'Fancy Icons conserva sólo un fondo elegido por el usuario.' );
	$slider = Translator::translate( 'bdt-fancy-slider', array( 'show_description' => '', 'slides' => array( array( 'title' => 'A', 'description' => 'B' ) ) ) );
	check_adapter( ! isset( $slider['slides'][0]['description'] ) && 'A' === $slider['slides'][0]['title'] && 'full' === $slider['image_size'], 'Fancy Slider respeta los interruptores de cada parte.' );
	$tabs = Translator::translate( 'bdt-fancy-tabs', array( 'show_button' => '' ) );
	check_adapter( '' === $tabs['tabs'][0]['tabs_button'] && 'Fancy Tabs Item One' === $tabs['tabs'][0]['tab_title'] && ! isset( $tabs['show_button'] ), 'Fancy Tabs usa sus filas por defecto y oculta botones apagados.' );
	$box = Translator::translate( 'bdt-featured-box', array( '_skin' => 'split' ) );
	check_adapter( 'split' === $box['layout'] && 'Featured Box Title' === $box['title_text'] && 'overlay' === Translator::translate( 'bdt-featured-box', array() )['layout'], 'Featured Box traduce su diseño.' );

	$notice = Translator::translate( 'bdt-notification', array( 'notification_timeout' => array( 'size' => 8000 ), 'notification_position' => 'top-center', 'ex_system' => 'yes' ) );
	check_adapter( 8000 === $notice['notification_timeout'] && 'top-right' === $notice['notification_position'] && ! isset( $notice['ex_system'] ), 'Notification convierte tiempos y posiciones.' );
	$qr = Translator::translate( 'bdt-qrcode', array( 'label_type' => 'none', 'size' => array( 'size' => 320 ), 'fill' => '#123456' ) );
	check_adapter( 'http://bdthemes.com' === $qr['text'] && '' === $qr['site_link'] && '' === $qr['label'] && 320 === $qr['size'] && '#123456' === $qr['foreground'], 'QR Code conserva el texto codificado, tamaño y color.' );
	$grid = Translator::translate( 'bdt-product-grid', array( 'show_price' => '', 'readmore_text' => 'Comprar' ) );
	check_adapter( ! isset( $grid['product_items'][0]['price'] ) && 'Comprar' === $grid['product_items'][0]['button_text'] && 10678 === $grid['product_items'][0]['rating_count'] && 'Pizza' === $grid['product_items'][0]['title'], 'Product Grid aplica los interruptores y la valoración.' );
	$logos = Translator::translate( 'bdt-logo-grid', array() );
	check_adapter( 8 === count( $logos['logo_list'] ) && 'Brand Name' === $logos['logo_list'][0]['name'], 'Logo Grid repite sus ocho logotipos de ejemplo.' );
	check_adapter( '' === Translator::translate( 'bdt-google-reviews', array() )['heading'], 'Google Reviews no añade un encabezado que Element Pack no mostraba.' );

	$html = Translator::translate( 'bdt-table', array() );
	check_adapter( 'manual' === $html['source'] && 0 === strpos( $html['content'], "Name,Age,Phone\nTom,5,010281065" ) && 'comma' === $html['delimiter'], 'La tabla HTML por defecto pasa a CSV.' );
	$static = Translator::translate( 'bdt-table', array( 'source' => 'static', 'static_columns_data' => array( array( 'static_column_name' => 'Plan' ), array( 'static_column_name' => 'Precio, IVA' ) ), 'static_rows_data' => array( array( 'static_row_column_type' => 'row' ), array( 'static_row_column_type' => 'column', 'static_cell_name' => 'Pro' ), array( 'static_row_column_type' => 'column', 'static_cell_name' => '99' ) ) ) );
	check_adapter( "Plan,\"Precio, IVA\"\nPro,99" === $static['content'] && ! isset( $static['static_rows_data'] ), 'Las filas escritas en Element Pack pasan a CSV con comillas cuando hace falta.' );
	$cloud = Translator::translate( 'bdt-tags-cloud', array( 'data_source' => 'static', 'static_open_new_window' => 'yes' ) );
	check_adapter( 'static' === $cloud['source'] && 'Design' === $cloud['static_tags'][0]['tag_text'] && 8 === $cloud['static_tags'][0]['tag_weight'] && 'on' === $cloud['static_tags'][0]['tag_link']['is_external'], 'Tags Cloud conserva sus etiquetas escritas, pesos y ventana nueva.' );
	check_adapter( 'post_tag' === Translator::translate( 'bdt-tags-cloud', array() )['taxonomy'], 'Las etiquetas dinámicas usan la taxonomía de etiquetas.' );
	$count = Translator::translate( 'bdt-total-count', array( 'fake_count' => 50, 'custom_post_type' => 'page' ) );
	check_adapter( 'comment' === $count['source'] && 'page' === $count['post_type'] && 50 === $count['extra_count'] && 'Total Count' === $count['label'], 'Total Count conserva tipo, etiqueta y cantidad añadida.' );
	$video = Translator::translate( 'bdt-video-player', array( 'source' => 'https://a.test/v.mp4', 'title_hide' => 'yes' ) );
	check_adapter( 'url' === $video['source'] && 'https://a.test/v.mp4' === $video['video_url'] && '' === $video['title'], 'Video Player usa la dirección guardada como URL externa.' );

	$switcher = Translator::translate( 'bdt-switcher', array( 'source_b' => 'elementor', 'template_id_b' => '12', 'default_active' => 'b' ) );
	check_adapter( 'Switch A' === $switcher['switcher_items'][0]['title'] && 'Switch Content A' === $switcher['switcher_items'][0]['content'] && 'template' === $switcher['switcher_items'][1]['content_type'] && '12' === $switcher['switcher_items'][1]['template_id'] && 'yes' === $switcher['switcher_items'][1]['switcher_active'], 'Switcher pasa sus dos opciones al alternador.' );
	$tabs2 = Translator::translate( 'bdt-tabs', array( 'active_item' => 2, 'tabs' => array( array( 'tab_title' => 'A' ), array( 'tab_title' => 'B', 'source' => 'link_section', 'source_link_section' => 'precios' ) ) ) );
	check_adapter( 'link_section' === $tabs2['switcher_items'][1]['content_type'] && 'precios' === $tabs2['switcher_items'][1]['link_target'] && 'yes' === $tabs2['switcher_items'][1]['switcher_active'] && 'Tab Content' === $tabs2['switcher_items'][0]['content'], 'Tabs conserva secciones enlazadas y pestaña activa.' );
	$lottie = Translator::translate( 'bdt-lottie-image', array( 'lottie_json_path' => 'https://a.test/a.json', 'play_action' => 'click', 'caption_source' => 'custom_caption', 'caption' => 'C' ) );
	check_adapter( 'external_url' === $lottie['source'] && 'https://a.test/a.json' === $lottie['source_external_url']['url'] && 'on_click' === $lottie['trigger'] && 'custom' === $lottie['caption_source'], 'Lottie Image usa el widget Lottie con la misma animación.' );
	$share = Translator::translate( 'bdt-social-share', array() );
	check_adapter( 'x-twitter' === $share['share_buttons'][2]['button'] && 'flat' === $share['skin'] && 'official' === $share['color_source'], 'Social Share conserva redes, estilo y colores.' );
	$table = Translator::translate( 'bdt-price-table', array() );
	check_adapter( 'fas fa-check' === $table['features_list'][0]['selected_item_icon']['value'] && 'Service Name' === $table['heading'] && ! isset( $table['layout'] ), 'Price Table traduce sus características.' );
	$toc = Translator::translate( 'bdt-table-of-content', array( 'selectors' => array( 'h2', 'h3', 'div' ) ) );
	check_adapter( array( 'h2', 'h3' ) === $toc['headings_by_tags'] && ! isset( $toc['layout'] ), 'La tabla de contenidos conserva los niveles de encabezado.' );
	$card = Translator::translate( 'bdt-profile-card', array( 'profile_name' => 'Ana' ) );
	check_adapter( 'custom' === $card['source'] && 'Ana' === $card['author_name'] && 'Follow' === $card['link_text'] && ! isset( $card['profile_posts'] ), 'Profile Card pasa a un cuadro de autor propio.' );

	// Menús: «child_start/child_end» pasan a niveles y Navbar conserva alineación y colores por posición.
	$vm = Translator::translate( 'bdt-vertical-menu', array() );
	check_adapter( 'static' === $vm['source'] && 'yes' === $vm['parent_toggles'] && 'collapse' === $vm['mode'] && array( '0', '0', '1', '1', '2', '2', '2', '1', '0' ) === array_column( $vm['items'], 'item_level' ) && array( 'url' => '#' ) === $vm['items'][0]['item_link'], 'Las filas de ejemplo de Vertical Menu deben anidarse por nivel con su enlace «#».' );
	$vm = Translator::translate( 'bdt-vertical-menu', array( 'dynamic_menu' => 'yes', 'navbar' => 'principal', 'submenu_type' => 'inner' ) );
	check_adapter( 'menu' === $vm['source'] && 'principal' === $vm['menu'] && '' === $vm['parent_toggles'] && ! isset( $vm['navbar'], $vm['menus'] ), 'El menú de WordPress y el padre con enlace deben conservarse.' );
	$slinky = Translator::translate( 'bdt-slinky-vertical-menu', array() );
	check_adapter( 'drill' === $slinky['mode'] && '' === $slinky['parent_toggles'], 'Slinky abre cada submenú en su panel y conserva el enlace del padre.' );
	$nav = Translator::translate( 'bdt-navbar', array( 'navbar' => 'principal', 'align' => 'center', 'align_mobile' => 'flex-end', 'individuL_menu_style' => 'yes', 'custom_css' => 'selector{gap:0}', 'repeater_field' => array( array( 'menu_number' => 2, 'menu_color' => '#ff0000', 'menu_hover_background' => 'red;}body{x' ) ) ) );
	check_adapter( 'principal' === $nav['menu'] && 'center' === $nav['align_items'] && 'end' === $nav['align_items_mobile'] && 'none' === $nav['dropdown'] && 'none' === $nav['pointer'] && '' === $nav['submenu_icon']['value'], 'Navbar debe pasar a un menú horizontal sin hamburguesa ni flecha.' );
	check_adapter( "selector{gap:0}\nselector .elementor-nav-menu--main > .elementor-nav-menu > li:nth-child(2) > .elementor-item{color:#ff0000;}" === $nav['custom_css'] && ! isset( $nav['repeater_field'] ), 'Los colores por posición pasan al CSS del elemento y sólo con valores de color válidos.' );
	$plain = Translator::translate( 'bdt-navbar', array( 'menu_parent_arrow' => 'yes' ) );
	check_adapter( '' === $plain['menu'] && ! isset( $plain['custom_css'], $plain['submenu_icon'] ), 'Sin estilo individual no se añade CSS y la flecha queda la del menú.' );

	// Slider: filas a diapositivas de PRO Elements; una plantilla obliga a seguir en el adaptador.
	$slider = Translator::translate( 'bdt-slider', array( 'origin' => 'bottom-left', 'align' => 'right', 'navigation' => 'arrows-fraction', 'show_button' => 'yes', 'button_text' => 'Ver <b>más</b>', 'tabs' => array(
		array( '_id' => 'a', 'tab_title' => 'Uno', 'tab_link' => array( 'url' => 'https://s.test/uno' ) ),
		array( '_id' => 'b', 'source' => 'elementor', 'template_id' => '12' ),
	) ) );
	check_adapter( 'bottom' === $slider['slides_vertical_position'] && 'left' === $slider['slides_horizontal_position'] && 'end' === $slider['slides_text_align'] && 'arrows' === $slider['navigation'] && 'yes' === $slider['infinite'], 'Posición, alineación, navegación y bucle deben traducirse.' );
	check_adapter( 'Uno' === $slider['slides'][0]['heading'] && 'Ver &lt;b&gt;más&lt;/b&gt;' === $slider['slides'][0]['button_text'] && 'button' === $slider['slides'][0]['link_click'] && 'Slide Content' === $slider['slides'][0]['description'], 'Cada fila conserva título, contenido y botón global con su enlace.' );
	check_adapter( 12 === $slider['slides'][1]['digitalisimo_template_id'] && 'yes' === $slider['digitalisimo_legacy_templates'] && ! isset( $slider['tabs'] ), 'Una diapositiva de plantilla queda marcada para el render del adaptador.' );
	$plain = Translator::translate( 'bdt-slider', array( 'show_button' => '' ) );
	check_adapter( 4 === count( $plain['slides'] ) && '' === $plain['slides'][0]['button_text'] && ! isset( $plain['digitalisimo_legacy_templates'] ) && 600 === $plain['slides_height']['size'], 'Sin botón no hay enlace visible y la altura de Element Pack se conserva.' );

	// Entradas: la consulta de Element Pack pasa a los campos `posts_*` y la presentación a la piel «classic».
	$query = Translator::posts_query( array( 'posts_source' => 'manual_selection', 'posts_selected_ids' => array( '7' ), 'posts_include_by' => array( 'terms' ), 'posts_include_term_ids' => array( '5' ), 'posts_orderby' => 'title', 'posts_order' => 'ASC', 'query_id' => 'q', 'posts_only_with_featured_image' => 'yes' ) );
	check_adapter( 'by_id' === $query['posts_post_type'] && array( '7' ) === $query['posts_posts_ids'] && array( 'terms' ) === $query['posts_include'] && array( '55' ) === $query['posts_include_term_ids'] && 'post_title' === $query['posts_orderby'] && 'asc' === $query['posts_order'] && 'q' === $query['posts_query_id'] && ! isset( $query['posts_source'], $query['posts_only_with_featured_image'] ), 'La consulta debe usar los nombres y valores de PRO Elements, con términos por term_taxonomy_id.' );
	$grid = Translator::translate( 'bdt-post-grid', array( '_skin' => 'bdt-carmie', 'carmie_item_limit' => array( 'size' => 3 ), 'show_comments' => '', 'show_tags' => 'yes', 'show_pagination' => 'yes', 'columns_mobile' => '2' ) );
	check_adapter( 'classic' === $grid['_skin'] && 3 === $grid['classic_posts_per_page'] && array( 'author', 'date' ) === $grid['classic_meta_data'] && array( 'category', 'post_tag' ) === $grid['digitalisimo_post_terms'] && 'Tags:' === $grid['digitalisimo_tags_label'] && 'numbers' === $grid['pagination_type'] && '2' === $grid['classic_columns_mobile'] && 'post' === $grid['posts_post_type'], 'Post Grid debe pasar límite de su piel, metadatos, términos, paginación y columnas.' );
	check_adapter( ! isset( $grid['carmie_item_limit'], $grid['show_tags'], $grid['columns'] ) && array( 'category' ) === Translator::translate( 'bdt-post-grid', array() )['digitalisimo_post_terms'], 'Las etiquetas sólo se muestran si se activaron: su default estaba comentado en Element Pack.' );
	$list = Translator::translate( 'bdt-post-list', array() );
	check_adapter( '1' === $list['classic_columns'] && 'left' === $list['classic_thumbnail'] && array( 'date' ) === $list['classic_meta_data'] && 6 === $list['classic_posts_per_page'], 'Post List vertical pone la miniatura a un lado en una columna.' );
	$single = Translator::translate( 'bdt-single-post', array( 'post_list' => '42' ) );
	check_adapter( 'by_id' === $single['posts_post_type'] && array( '42' ) === $single['posts_posts_ids'] && 1 === $single['classic_posts_per_page'] && array( '0' ) === Translator::translate( 'bdt-single-post', array() )['posts_posts_ids'], 'Single Post consulta sólo la entrada elegida, o ninguna.' );

	// Offcanvas: botón o selector externo, plantilla o barra lateral y comportamiento del panel.
	$oc = Translator::translate( 'bdt-offcanvas', array( 'layout' => 'custom', 'offcanvas_custom_id' => '.abrir', 'source' => 'elementor', 'template_id' => '8', 'offcanvas_flip' => 'right', 'offcanvas_bg_close' => '', 'offcanvas_width' => array( 'unit' => 'px', 'size' => 400 ), 'button_align' => 'center' ) );
	check_adapter( 'selector' === $oc['trigger'] && '.abrir' === $oc['trigger_selector'] && 'template' === $oc['source'] && '8' === $oc['template_id'] && 'right' === $oc['side'] && '' === $oc['close_on_overlay'] && 'yes' === $oc['close_on_escape'] && 400 === $oc['panel_width']['size'] && 'center' === $oc['button_align'] && ! isset( $oc['offcanvas_width'], $oc['layout'] ), 'Offcanvas debe traducir apertura, contenido, lado, cierre y ancho.' );
	$oc = Translator::translate( 'bdt-offcanvas', array() );
	check_adapter( 'button' === $oc['trigger'] && 'sidebar' === $oc['source'] && '' === $oc['sidebar'] && '' === $oc['overlay'] && '' === $oc['content_before'] && 'Offcanvas' === $oc['button_text'] && 'fas fa-bars' === $oc['button_icon']['value'] && 'flex-start' === $oc['button_align'], 'Los defaults de Offcanvas muestran su botón sin oscurecer el fondo.' );

	// Widgets de integración: el mismo shortcode que armaba Element Pack, en el widget Shortcode de Elementor.
	$sc = static function ( $id, $settings ) { return Translator::translate( $id, $settings )['shortcode']; };
	check_adapter( '[contact-form-7 id="12"]' === $sc( 'bdt-contact-form-7', array( 'contact_form' => '12' ) ) && '' === $sc( 'bdt-contact-form-7', array() ), 'Contact Form 7 usa su id y sin formulario no muestra nada.' );
	check_adapter( '[wpforms id="3"]' === $sc( 'bdt-wp-forms', array( 'contact_form' => '3' ) ) && '[fluentform id="4"]' === $sc( 'bdt-fluent-forms', array( 'fluent_form' => '4' ) ) && '[ninja_form id="5"]' === $sc( 'bdt-ninja-form', array( 'ninja_form' => '5' ) ), 'Los formularios de terceros usan su etiqueta.' );
	check_adapter( '[give_totals ids="1 2" total_goal="100" message="Meta &amp; más" link="https://s.test/d" link_text="Donar" progress_bar="yes"]' === $sc( 'bdt-give-totals', array( 'forms' => array( '1', '2' ), 'total_goal' => '100', 'message' => 'Meta & más', 'link' => array( 'url' => 'https://s.test/d' ), 'link_text' => 'Donar', 'show_progress' => 'yes' ) ), 'Las listas se separan con espacios y los textos se escapan como en Element Pack.' );
	check_adapter( 0 === strpos( $sc( 'bdt-give-donor-wall', array( 'all_forms' => 'yes', 'show_comments' => 'yes' ) ), '[give_donor_wall form_id=""' ) && false !== strpos( $sc( 'bdt-give-donor-wall', array( 'all_forms' => 'yes', 'show_comments' => 'yes' ) ), 'show_comments="1" only_comments=""' ), 'Los lógicos pasan como «1» o vacío.' );
	check_adapter( '[campaigns orderby="post_date" order="DESC" number="10" button="donate"]' === $sc( 'bdt-charitable-campaigns', array( 'campaigns' => array( 'all' ), 'orderby' => 'post_date', 'order' => 'DESC', 'number' => '10', 'button' => 'donate' ) ) && false !== strpos( $sc( 'bdt-charitable-campaigns', array( 'campaigns' => array( '7', '9' ) ) ), 'id="7,9"' ), 'Charitable sólo filtra campañas si no se eligieron todas.' );
	check_adapter( '[download_history]' === $sc( 'bdt-easy-digital-download-history', array() ) && '[edd_register redirect="https://s.test/ok"]' === $sc( 'bdt-edd-register', array( 'form_register_redirect_url' => array( 'url' => 'https://s.test/ok' ) ) ), 'EDD usa sus shortcodes fijos o con redirección.' );
	check_adapter( '[weforms id="2" data-x="1"]' === $sc( 'bdt-we-form', array( 'we_form' => '2', 'custom_attributes' => "data-x|1\nonclick|x\nstyle|y\n9bad|z" ) ), 'weForms descarta atributos de evento, estilo y clase.' );
	check_adapter( '[gravityform id="8" title="true" description="false" ajax="true" tabindex="0"]' === $sc( 'bdt-gravity-form', array( 'gravity_form' => '8', 'title_hide' => 'yes', 'description_hide' => '', 'form_ajax' => 'yes' ) ), 'Gravity Forms usa los mismos argumentos que gravity_form().' );
	$foo = Translator::translate( 'fooevents-calendar', array( 'fooevents_calendar_list' => 'month', 'fooevents_calendar_startday' => '1', 'fooevents_calendar_num' => '5' ) );
	check_adapter( Translator::is_legacy_id( 'fooevents-calendar' ) && ! Translator::is_legacy_id( 'shortcode' ) && 0 === strpos( $foo['shortcode'], '[fooevents_calendar defaultView="month" firstDay="1" num="5"' ) && ! isset( $foo['fooevents_calendar_list'] ), 'El calendario de FooEvents también se traduce aunque su ID no lleve prefijo.' );

	check_adapter( '[bbp-single-topic id="15"]' === $sc( 'bdt-bbpress-single-topic', array( 'bbpress_topic_id' => '15' ) ) && '[bbp-topic-form forum_id="4"]' === $sc( 'bdt-bbpress-topic-form', array( 'bbpress_forum_id' => '4' ) ) && '[bbp-topic-form ]' === $sc( 'bdt-bbpress-topic-form', array() ) && '[bbp-stats]' === $sc( 'bdt-bbpress-stats', array() ), 'bbPress usa sus propios shortcodes.' );

	check_adapter( '[download id="5"]' === $sc( 'bdt-download-monitor', array( 'file_id' => '5' ) ) && '[download_cart]' === $sc( 'bdt-edd-cart', array() ) && '[woocommerce_cart ]' === $sc( 'bdt-wc-elements', array( 'element' => 'woocommerce_cart' ) ) && '[product_page id="9"]' === $sc( 'bdt-wc-elements', array( 'element' => 'product_page', 'product_id' => '9' ) ) && '' === $sc( 'bdt-wc-elements', array( 'element' => 'product_page' ) ), 'Descargas, EDD y páginas de WooCommerce usan sus shortcodes.' );
	$cal = Translator::translate( 'bdt-calendly', array( 'calendly_username' => 'https://calendly.com/equipo/', 'calendly_time' => '30min', 'text_color' => '#AbC', 'event_type_details' => 'yes' ) );
	check_adapter( false !== strpos( $cal['html'], 'data-url="https://calendly.com/equipo/30min?hide_event_type_details=1&#038;text_color=aabbcc"' ) || false !== strpos( $cal['html'], 'https://calendly.com/equipo/30min?hide_event_type_details=1' ), 'Calendly arma la misma URL que Element Pack.' );
	$ifr = Translator::translate( 'bdt-iframe', array( 'source' => array( 'url' => 'https://s.test/x' ), 'show_responsive_ratio' => 'yes', 'responsive_ratio_size' => array( 'width' => 4, 'height' => 3 ) ) );
	check_adapter( false !== strpos( $ifr['html'], 'src="https://s.test/x"' ) && false !== strpos( $ifr['html'], 'aspect-ratio:4/3' ) && false !== strpos( $ifr['html'], 'loading="lazy"' ) && ! isset( $ifr['source'] ), 'El iframe conserva URL, proporción y carga diferida.' );

	// Condiciones como Elementor: comparación estricta, listas, negación y subclaves.
	$act = Translator::active( array( 'a' => 'x', 'b' => '1', 'c' => '2', 'd' => array( 'url' => '' ), 'e' => '3' ), array( 'b' => array( 'a' => 'x' ), 'c' => array( 'a!' => 'x' ), 'd' => array( 'a' => array( 'y', 'x' ) ), 'e' => array( 'd[url]!' => '' ) ) );
	check_adapter( '1' === $act['b'] && null === $act['c'] && array( 'url' => '' ) === $act['d'] && null === $act['e'], 'Las condiciones deben evaluarse como en Elementor.' );
	check_adapter( false !== strpos( $sc( 'bdt-charitable-stat', array( 'campaign' => array( '3' ), 'display' => 'donors' ) ), 'goal=""' ) && false !== strpos( $sc( 'bdt-charitable-stat', array( 'campaign' => array( '3' ), 'display' => 'progress' ) ), 'goal="1000"' ), 'Un ajuste oculto por su condición llega vacío al shortcode, como en Element Pack.' );

	// Equivalentes de PRO Elements y Elementor.
	$cd = Translator::translate( 'bdt-countdown', array( 'loop_time' => 'yes', 'loop_hours' => '5', 'end_action_type' => 'url', 'end_redirect_link' => array( 'url' => 'https://s.test/fin' ) ) );
	check_adapter( 'evergreen' === $cd['countdown_type'] && 5 === $cd['evergreen_counter_hours'] && array( 'redirect' ) === $cd['expire_actions'] && 'https://s.test/fin' === $cd['expire_redirect_url']['url'] && ! isset( $cd['loop_time'] ), 'Countdown traduce bucle y acción final.' );
	$fb = Translator::translate( 'bdt-flip-box', array( 'front_title_text' => 'A', 'back_title_text' => 'B', 'flip_effect' => 'zoom-up' ) );
	check_adapter( 'A' === $fb['title_text_a'] && 'B' === $fb['title_text_b'] && 'zoom-in' === $fb['flip_effect'] && 'yes' === $fb['flip_3d'] && 'fas fa-star' !== '' , 'Flip Box renombra caras y efectos.' );
	$ct = Translator::translate( 'bdt-advanced-counter', array( 'content_number' => '5000', 'duration' => '1.5', 'use_grouping' => 'yes', 'counter_separator' => '.' ) );
	check_adapter( 5000.0 === $ct['ending_number'] && 1500 === $ct['duration'] && 'yes' === $ct['thousand_separator'] && '.' === $ct['thousand_separator_char'] && 'Cool Number' === $ct['title'], 'El contador usa milisegundos y el separador de miles.' );
	$pt = Translator::translate( 'bdt-post-title', array( 'bdt_post_link' => 'yes' ) );
	check_adapter( false !== strpos( $pt['__dynamic__']['title'], 'name="post-title"' ) && false !== strpos( $pt['__dynamic__']['link'], 'name="post-url"' ) && 'h2' === $pt['header_size'], 'El título de entrada fija sus etiquetas dinámicas.' );
	check_adapter( false !== strpos( Translator::translate( 'bdt-post-content', array( 'limit' => array( 'size' => 25 ) ) )['__dynamic__']['excerpt'], rawurlencode( '{"max_length":25}' ) ), 'El extracto conserva su número de palabras.' );

	$pf = Translator::translate( 'bdt-portfolio-gallery', array( 'columns' => '4', 'show_excerpt' => 'yes' ) );
	check_adapter( 'portfolio' === $pf['posts_post_type'] && 9 === $pf['classic_posts_per_page'] && '4' === $pf['classic_columns'] && 'yes' === $pf['classic_show_excerpt'] && 10 === $pf['classic_excerpt_length'] && ! isset( $pf['columns'] ), 'Portfolio consulta su tipo de contenido con la piel clásica.' );
	$pb = Translator::translate( 'bdt-post-block', array() );
	check_adapter( 5 === $pb['classic_posts_per_page'] && array( 'date' ) === $pb['classic_meta_data'] && array( 'category', 'post_tag' ) === $pb['digitalisimo_post_terms'], 'Post Block usa los campos de su entrada destacada.' );

	// Migración: al widget propio sólo si todos los ajustes existen allí.
	$element  = array( 'id' => 'e1', 'elType' => 'widget', 'widgetType' => 'bdt-accordion', 'settings' => array( 'tabs' => array( array( 'tab_title' => 'A' ) ), '_padding' => array() ) );
	$controls = array_fill_keys( array( 'tabs', 'active_item', 'multiple', 'open_all_initially', 'title_html_tag', 'show_custom_icon', 'accordion_icon', 'accordion_active_icon', 'icon_align' ), array() );
	list( $native, $state ) = Migration::convert_element( $element, $controls );
	check_adapter( 'native' === $state && 'digitalisimo-accordion' === $native['widgetType'] && ! isset( $native['settings'][ Translator::MARKER ] ), 'Sin estilos de Element Pack el elemento pasa al widget propio.' );
	$element['settings']['accordion_item_background_color'] = '#000';
	list( $kept, $state ) = Migration::convert_element( $element, $controls );
	check_adapter( 'adapter' === $state && 'bdt-accordion' === $kept['widgetType'] && 'bdt-accordion' === $kept['settings'][ Translator::MARKER ], 'Con estilos que sólo tiene el adaptador, se queda en él ya traducido.' );
	list( , $state ) = Migration::convert_element( array( 'widgetType' => 'bdt-sin-mapa', 'settings' => array() ), $controls );
	check_adapter( 'skip' === $state, 'Un widget sin mapa no se toca.' );

	echo 'DIGITALÍSIMO Elements: adaptadores de Element Pack (' . count( $ids ) . ") validados.\n";
}
