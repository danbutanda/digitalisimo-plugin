<?php
namespace {
	define( 'ABSPATH', __DIR__ );
	function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
	function get_bloginfo( $what ) { return 'Mi sitio'; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return (string) $v; }
	function wp_kses_post( $v ) { return strip_tags( (string) $v, '<p><a><strong><em>' ); }
	function check_adapter( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$base = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-legacy/';
	require $base . 'class-translator.php';
	require $base . 'class-migration.php';
	use Digitalisimo\Elements\Legacy\Translator;
	use Digitalisimo\Elements\Legacy\Migration;

	// Todos los mapas cargan, apuntan a un widget propio y sus estilos ya no nombran clases de Element Pack.
	$ids = Translator::ids();
	check_adapter( in_array( 'bdt-accordion', $ids, true ) && ! preg_grep( '/\.styles$/', $ids ), 'Los mapas deben listarse sin los archivos de estilos.' );
	foreach ( $ids as $id ) {
		$map = Translator::map( $id );
		check_adapter( is_array( $map ) && 0 === strpos( (string) $map['target'], 'digitalisimo-' ), 'Mapa inválido: ' . $id );
		foreach ( Translator::styles( $id ) as $control ) {
			$css = wp_json_encode_stub( $control );
			check_adapter( ! empty( $control['name'] ) && false === strpos( $css, '.bdt-' ), 'Estilo con clase de Element Pack en ' . $id . ': ' . $control['name'] );
			check_adapter( isset( $control['group'] ) ? 0 === strpos( $control['selector'], '{{WRAPPER}}' ) : ! empty( $control['selectors'] ), 'Estilo sin selector en ' . $id );
		}
	}
	function wp_json_encode_stub( $value ) { return json_encode( $value ); }
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
