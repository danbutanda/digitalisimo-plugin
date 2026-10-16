<?php
/** Logotipos: cuadrícula y carrusel unificados, recursos por presentación y los cuatro alias del contenido guardado. */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		private $attributes = array();
		public function get_settings_for_display() { return $this->settings; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function is_type_instance() { return array() === $this->settings; }
		public function start_controls_section( $name, $args ) {}
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args; foreach ( array( '_tablet', '_mobile' ) as $device ) { $this->controls[ $name . $device ] = $args; } }
		public function get_controls() { return $this->controls; }
		public function add_link_attributes( $name, $link ) { $this->attributes[ $name ] = array( 'href' => $link['url'] ); if ( ! empty( $link['is_external'] ) ) { $this->attributes[ $name ]['target'] = '_blank'; } if ( ! empty( $link['nofollow'] ) ) { $this->attributes[ $name ]['rel'] = array( 'nofollow' ); } }
		public function add_render_attribute( $name, $key, $value ) { $this->attributes[ $name ][ $key ] = array_merge( (array) ( $this->attributes[ $name ][ $key ] ?? array() ), (array) $value ); }
		public function get_render_attribute_string( $name ) { $out = array(); foreach ( $this->attributes[ $name ] as $key => $value ) { $out[] = $key . '="' . htmlspecialchars( implode( ' ', (array) $value ), ENT_QUOTES, 'UTF-8' ) . '"'; } return implode( ' ', $out ); }
	}
	class Repeater { private $controls = array(); public function add_control( $name, $args ) { $this->controls[ $name ] = $args; } public function get_controls() { return $this->controls; } }
	class Controls_Manager { const GALLERY = 'gallery'; const MEDIA = 'media'; const TEXT = 'text'; const TEXTAREA = 'textarea'; const URL = 'url'; const SWITCHER = 'switcher'; const REPEATER = 'repeater'; const SELECT = 'select'; const SLIDER = 'slider'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const TAB_STYLE = 'style'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function esc_url( $v ) { return esc_html( $v ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $v ); }
	function get_post_meta( $id, $key, $single ) { return 12 === $id ? 'Marca & Compañía' : ''; }
	function get_the_title( $id ) { return 'Adjunto ' . $id; }
	function wp_get_attachment_url( $id ) { return 'https://example.test/media/' . $id . '.webp'; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) { $GLOBALS['logos_image_attrs'][] = $attrs; return 12 === $id ? '<img class="' . esc_attr( $attrs['class'] ) . '" alt="' . esc_attr( $attrs['alt'] ) . '" data-size="' . esc_attr( $size ) . '">' : ''; }
	function check_logos( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function render_logos( $widget ) { ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); return ob_get_clean(); }
	foreach ( array( 'brand-carousel', 'logo-carousel', 'brand-grid', 'logo-grid' ) as $alias ) { require_once __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-' . $alias . '.php'; }

	$widget = new \Digitalisimo\Elements\Logos_Widget();
	check_logos( 'digitalisimo-logos' === $widget->get_name() && array( 'digitalisimo-carousel-engine', 'digitalisimo-logos', 'digitalisimo-logos-grid' ) === $widget->get_style_depends() && array( 'digitalisimo-carousel-engine' ) === $widget->get_script_depends(), 'La instancia de registro declara el motor y sus dos hojas para que el editor los tenga.' );
	( new \ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	check_logos( isset( $widget->controls['gallery_images'], $widget->controls['items']['fields']['link_text'], $widget->controls['items']['fields']['caption'], $widget->controls['layout'], $widget->controls['columns'], $widget->controls['style'], $widget->controls['details'], $widget->controls['navigation'], $widget->controls['show_link_text'] ), 'Faltan controles de galería, datos por logotipo o presentación.' );
	check_logos( str_contains( $widget->controls['columns']['selectors']['{{WRAPPER}} .digi-logos__list'], 'grid-template-columns' ) && str_contains( $widget->controls['columns']['selectors']['{{WRAPPER}} .digi-carousel'], '--digi-carousel-visible' ), 'Las columnas sirven a la cuadrícula y al carrusel.' );

	// Cuadrícula: imágenes de Medios, datos individuales, enlace en la tarjeta y modo de detalles.
	$widget->settings = array( 'layout' => 'grid', 'style' => 'border', 'details' => 'tooltip', 'gallery_images' => array( array( 'id' => 12 ), array( 'url' => 'https://example.test/manual.webp', 'alt' => 'Manual', 'width' => 320, 'height' => 160 ) ), 'items' => array( array( '_id' => 'x1', 'image' => array( 'url' => 'https://example.test/tercero.webp' ), 'name' => '<script>mal</script>', 'description' => 'Descripción & más', 'link' => array( 'url' => 'https://example.test/', 'is_external' => true, 'nofollow' => true ) ), array( 'image' => array() ) ) );
	check_logos( array( 'digitalisimo-logos', 'digitalisimo-logos-grid' ) === $widget->get_style_depends() && array() === $widget->get_script_depends(), 'La cuadrícula carga su hoja y no el motor de carrusel.' );
	$html = render_logos( $widget );
	check_logos( 3 === substr_count( $html, '<li class="digi-logos__item' ) && str_contains( $html, 'class="digi-logos digi-logos--grid digi-logos--border digi-logos--tooltip"' ) && str_contains( $html, '<ul class="digi-logos__list">' ), 'Deben omitirse imágenes vacías y declararse presentación, tarjetas y detalles.' );
	check_logos( str_contains( $html, 'alt="Marca &amp; Compañía"' ) && str_contains( $html, 'alt="Manual"' ) && str_contains( $html, 'width="320" height="160"' ) && ! str_contains( $html, 'loading="' ), 'ALT y dimensiones se conservan y WordPress decide la carga.' );
	check_logos( str_contains( $html, '<a class="digi-logos__card" href="https://example.test/" target="_blank" rel="nofollow noopener noreferrer">' ) && str_contains( $html, 'digi-logos__content digi-logos__content--tooltip' ) && str_contains( $html, '<h3 class="digi-logos__name">mal</h3>' ) && str_contains( $html, 'Descripción &amp; más' ) && str_contains( $html, 'elementor-repeater-item-x1' ), 'Sin texto de enlace, la tarjeta es el enlace, el pie va como globo y el HTML del nombre se retira.' );
	check_logos( ! str_contains( $html, 'digi-brand-' ) && ! str_contains( $html, 'digi-logo-' ), 'El widget nuevo sólo imprime sus propias clases.' );

	// Texto del enlace visible: el texto es el enlace y la imagen no se enlaza; el nombre visible vacía el ALT.
	$widget->settings['show_link_text'] = 'yes';
	$widget->settings['details'] = 'always';
	$text = render_logos( $widget );
	check_logos( str_contains( $text, '<div class="digi-logos__card"><img class="digi-logos__image" alt="" decoding="async" src="https://example.test/tercero.webp"></div>' ) && str_contains( $text, '<a class="digi-logos__link" href="https://example.test/" target="_blank" rel="nofollow noopener noreferrer">Visitar mal</a>' ), 'Con texto de enlace, un solo destino por logotipo y ALT vacío junto al nombre visible.' );

	// Al abrir: detalles nativos, sin enlace en la imagen.
	$widget->settings['details'] = 'click';
	$click = render_logos( $widget );
	check_logos( 3 === substr_count( $click, '<details class="digi-logos__details">' ) && 2 === substr_count( $click, 'aria-label="Mostrar logotipo"' ) && str_contains( $click, '<summary class="digi-logos__summary" aria-label="mal">' ) && str_contains( $click, 'digi-logos__link' ) && ! str_contains( $click, 'a class="digi-logos__card"' ), 'Al abrir, los detalles se despliegan sin JavaScript y el enlace va en el contenido.' );

	// Carrusel: motor compartido, nombre y texto del enlace dentro de la tarjeta.
	$widget->settings = array( 'layout' => 'carousel', 'label' => 'Clientes', 'navigation' => 'arrows', 'show_link_text' => 'yes', 'items' => array( array( 'image' => array( 'id' => 12 ), 'name' => 'Primera', 'link' => array( 'url' => 'https://example.test/a' ), 'link_text' => 'Visitar' ), array( 'image' => array( 'url' => 'https://example.test/b.png' ), 'name' => 'Segunda' ) ) );
	check_logos( array( 'digitalisimo-carousel-engine', 'digitalisimo-logos' ) === $widget->get_style_depends() && array( 'digitalisimo-carousel-engine' ) === $widget->get_script_depends(), 'El carrusel carga el motor compartido y no la hoja de cuadrícula.' );
	$widget->settings['navigation'] = 'none';
	check_logos( array() === $widget->get_script_depends(), 'Sin flechas no hace falta el motor.' );
	$widget->settings['navigation'] = 'arrows';
	$carousel = render_logos( $widget );
	check_logos( str_contains( $carousel, 'class="digi-logos digi-logos--carousel"' ) && str_contains( $carousel, 'aria-label="Clientes"' ) && str_contains( $carousel, 'style="--digi-carousel-count:2"' ) && 2 === substr_count( $carousel, '<li class="digi-carousel__slide digi-logos__item">' ) && str_contains( $carousel, 'data-digi-carousel-next' ), 'El carrusel usa el motor con dos diapositivas, nombre accesible y flechas.' );
	check_logos( str_contains( $carousel, '<a class="digi-logos__card digi-logos__card--stretch" href="https://example.test/a">' ) && str_contains( $carousel, '<h3 class="digi-logos__name">Primera</h3>' ) && str_contains( $carousel, '<span class="digi-logos__link-text">Visitar</span>' ) && str_contains( $carousel, '<div class="digi-logos__card digi-logos__card--stretch">' ), 'La tarjeta enlazada lleva imagen, nombre y texto del enlace.' );
	ob_start(); ( new \ReflectionMethod( $widget, 'content_template' ) )->invoke( $widget ); $editor = ob_get_clean();
	check_logos( str_contains( $editor, 'var P = [["digi-logos",[]]]' ) && str_contains( $editor, 'data-digi-carousel-track' ) && str_contains( $editor, "cls( 'details' )" ) && str_contains( $editor, 'settings.gallery_images' ), 'La vista previa pinta ambas presentaciones con el mismo generador de clases.' );

	// Alias: Carrusel de marcas conserva nombre, clase, ajustes traducidos y clases CSS de antes.
	$brand = new \Digitalisimo\Elements\Brand_Carousel_Widget();
	check_logos( 'digitalisimo-brand-carousel' === $brand->get_name() && false === $brand->show_in_panel() && $brand instanceof \Digitalisimo\Elements\Logos_Widget, 'El alias conserva su nombre, se oculta del panel y es un Logotipos.' );
	( new \ReflectionMethod( $brand, 'register_controls' ) )->invoke( $brand );
	$legacy = array( 'brand_html_tag' => 'h4', 'columns_tablet' => '1', 'item_gap_mobile' => array( 'size' => 4 ), 'show_website_link' => 'yes', 'brand_items' => array(), 'image_size' => 'large', 'navigation' => 'arrows', 'item_background' => '#fff' );
	foreach ( array_keys( $legacy ) as $key ) { check_logos( isset( $brand->controls[ $key ] ), "La clave de antes {$key} debe tener control en el alias para que la conversión de la Migración la conserve." ); }
	check_logos( 'hidden' === $brand->controls['brand_items']['type'] && 'hidden' === $brand->controls['item_gap']['type'] && 'select' === $brand->controls['columns']['type'] && isset( $brand->controls['items'], $brand->controls['layout'] ), 'Las claves de antes van ocultas y las unificadas no se repiten.' );
	foreach ( array( new \Digitalisimo\Elements\Logo_Carousel_Widget(), new \Digitalisimo\Elements\Brand_Grid_Widget(), new \Digitalisimo\Elements\Logo_Grid_Widget() ) as $other ) {
		( new \ReflectionMethod( $other, 'register_controls' ) )->invoke( $other );
		foreach ( $other::LEGACY_KEYS as $key => $responsive ) { check_logos( isset( $other->controls[ $key ] ) && ( ! $responsive || isset( $other->controls[ $key . '_tablet' ] ) ), $other->get_name() . ": la clave {$key} debe tener control oculto." ); }
	}
	$brand->settings = \Digitalisimo\Elements\Brand_Carousel_Widget::upgrade_settings( array( 'brand_html_tag' => 'h4', 'columns_tablet' => '1', 'item_gap' => array( 'size' => 10 ), 'brand_items' => array( array( '_id' => 'b1', 'brand_name' => 'Marca', 'image' => array( 'id' => 12 ), 'link' => array( 'url' => 'https://example.test/' ), 'website_link_text' => 'Web' ), array( 'brand_name' => '', 'image' => array() ) ) ) );
	check_logos( 'carousel' === $brand->settings['layout'] && '3' === $brand->settings['columns'] && '1' === $brand->settings['columns_tablet'] && 10 === $brand->settings['gap']['size'] && 'h4' === $brand->settings['name_tag'] && 'yes' === $brand->settings['show_link_text'] && 'Marca' === $brand->settings['items'][0]['name'] && 'Web' === $brand->settings['items'][0]['link_text'] && ! isset( $brand->settings['brand_items'], $brand->settings['item_gap'] ), 'Los ajustes del carrusel de marcas pasan a los de Logotipos con sus defaults de antes.' );
	$again = \Digitalisimo\Elements\Brand_Carousel_Widget::upgrade_settings( $brand->settings + array( 'brand_items' => '', 'item_gap' => '', 'show_website_link' => '', 'brand_html_tag' => '' ) );
	check_logos( $again === $brand->settings, 'Un documento ya traducido y vuelto a guardar (claves de antes vacías) no se vuelve a traducir.' );
	$regrid = \Digitalisimo\Elements\Brand_Grid_Widget::upgrade_settings( array( 'items' => array( array( 'name' => 'Nueva' ) ), 'details' => 'overlay', 'brand_items' => '', 'brand_event' => '' ) );
	check_logos( 'overlay' === $regrid['details'] && 'Nueva' === $regrid['items'][0]['name'] && ! isset( $regrid['brand_event'], $regrid['brand_items'] ), 'Las claves de antes vacías no pisan el modo de detalles ni los logotipos ya traducidos.' );
	$html = render_logos( $brand );
	check_logos( str_contains( $html, 'class="digi-logos digi-logos--carousel digi-brand-carousel digi-brand-carousel--carousel"' ) && str_contains( $html, 'aria-label="Carrusel de marcas"' ) && str_contains( $html, '<li class="digi-carousel__slide digi-logos__item digi-brand-carousel__slide elementor-repeater-item-b1">' ) && str_contains( $html, 'class="digi-logos__card digi-logos__card--stretch digi-brand-carousel__card digi-brand-carousel__card--stretch"' ) && str_contains( $html, '<h4 class="digi-logos__name digi-brand-carousel__name">Marca</h4>' ) && str_contains( $html, '<span class="digi-logos__link-text digi-brand-carousel__website">Web</span>' ) && str_contains( $html, 'class="digi-logos__image digi-brand-carousel__image"' ), 'El carrusel de marcas imprime sus clases de antes junto a las nuevas.' );
	check_logos( 1 === substr_count( $html, '<li class="digi-carousel__slide' ), 'Una marca sin nombre ni imagen se omite.' );

	// Alias: Carrusel de logotipos (galería múltiple, sin nombres visibles, enlace sólo con nombre accesible).
	$logo = new \Digitalisimo\Elements\Logo_Carousel_Widget();
	$logo->settings = \Digitalisimo\Elements\Logo_Carousel_Widget::upgrade_settings( array( 'gallery_images' => array( array( 'id' => 12 ) ), 'logo_items' => array( array( 'image' => array( 'id' => 0, 'url' => 'https://example.test/last.png' ), 'name' => 'Último', 'link' => array( 'url' => 'https://example.test/firma' ) ), array( 'image' => array( 'url' => 'https://example.test/anon.png' ), 'link' => array( 'url' => 'https://example.test/anon' ) ) ), 'navigation' => 'none' ) );
	$html = render_logos( $logo );
	check_logos( 'digitalisimo-logo-carousel' === $logo->get_name() && '' === $logo->settings['show_name'] && '4' === $logo->settings['columns'] && 3 === substr_count( $html, 'digi-logo-carousel__slide' ) && str_contains( $html, 'href="https://example.test/firma"' ) && ! str_contains( $html, 'example.test/anon"' ) && ! str_contains( $html, 'digi-logos__name' ) && ! str_contains( $html, 'data-digi-carousel-next' ), 'El carrusel de logotipos no muestra nombres, no enlaza sin nombre accesible y respeta la navegación apagada.' );
	check_logos( str_contains( $html, 'class="digi-logos__card digi-logos__card--stretch digi-logo-carousel__card digi-logo-carousel__card--stretch"' ) && str_contains( $html, 'alt="Último"' ), 'Las clases de antes y el nombre accesible como ALT se conservan.' );

	// Alias: Cuadrícula de marcas (sin tarjeta, detalles al apuntar, enlace de texto, imagen con sus clases de antes).
	$grid = new \Digitalisimo\Elements\Brand_Grid_Widget();
	$grid->settings = \Digitalisimo\Elements\Brand_Grid_Widget::upgrade_settings( array( 'brand_event' => 'hover-item', 'column_gap' => array( 'size' => 8 ), 'row_gap' => array( 'size' => 4 ), 'brand_items' => array( array( 'brand_name' => 'Marca & Co', 'image' => array( 'id' => 12 ), 'link' => array( 'url' => 'https://example.test/' ) ), array( 'brand_name' => 'Cuatro', 'image' => array( 'url' => 'https://example.test/cuatro.webp' ) ) ) ) );
	$html = render_logos( $grid );
	check_logos( 'overlay' === $grid->settings['details'] && 'plain' === $grid->settings['style'] && 8 === $grid->settings['gap']['size'] && 4 === $grid->settings['row_gap']['size'] && 'yes' === $grid->settings['show_link_text'] && ! isset( $grid->settings['brand_event'], $grid->settings['column_gap'] ), 'Los ajustes de la cuadrícula de marcas se traducen, incluido el modo de detalles.' );
	check_logos( str_contains( $html, 'class="digi-logos digi-logos--grid digi-logos--plain digi-logos--overlay digi-brand-grid digi-brand-grid--grid digi-brand-grid--plain digi-brand-grid--overlay"' ) && str_contains( $html, '<ul class="digi-logos__list digi-brand-grid__list">' ) && str_contains( $html, '<div class="digi-logos__card digi-brand-grid__image"><img class="digi-logos__image digi-brand-grid__logo" alt=""' ) && str_contains( $html, '<figcaption class="digi-logos__content digi-brand-grid__content">' ) && str_contains( $html, '<a class="digi-logos__link digi-brand-grid__link" href="https://example.test/">Visitar Marca &amp; Co</a>' ), 'La cuadrícula de marcas mantiene su marcado accesible con las clases de antes.' );
	$grid->settings['details'] = 'click';
	$html = render_logos( $grid );
	check_logos( 2 === substr_count( $html, '<details class="digi-logos__details digi-brand-grid__details">' ) && str_contains( $html, '<summary class="digi-logos__summary digi-brand-grid__summary" aria-label="Marca &amp; Co">' ), 'Al abrir, los detalles conservan las clases de antes.' );

	// Alias: Cuadrícula de logotipos (tarjetas, pie como globo sólo con enlace, cubrir).
	$lgrid = new \Digitalisimo\Elements\Logo_Grid_Widget();
	$lgrid->settings = \Digitalisimo\Elements\Logo_Grid_Widget::upgrade_settings( array( 'layout' => 'border', 'thumbnail_size' => 'large', 'logo_size_cover' => 'yes', 'grid_bg_color' => '#fff', 'gallery_images' => array( array( 'id' => 12 ) ), 'logo_list' => array( array( 'image' => array( 'url' => 'https://example.test/tercero.webp' ), 'name' => 'Tercero', 'description' => 'Detalle', 'link' => array( 'url' => 'https://example.test/', 'is_external' => true ), 'logo_tooltip' => 'yes' ), array( 'image' => array( 'url' => 'https://example.test/cuarto.webp' ), 'name' => 'Cuarto', 'logo_tooltip' => 'yes' ), array( 'image' => array( 'url' => 'https://example.test/quinto.webp' ), 'name' => 'Quinto' ) ) ) );
	$html = render_logos( $lgrid );
	check_logos( 'border' === $lgrid->settings['style'] && 'large' === $lgrid->settings['image_size'] && 'yes' === $lgrid->settings['cover'] && '#fff' === $lgrid->settings['item_background'] && 'tooltip' === $lgrid->settings['details'] && 'strong' === $lgrid->settings['name_tag'] && 'yes' === $lgrid->settings['items'][0]['caption'] && '' === $lgrid->settings['items'][2]['caption'] && ! isset( $lgrid->settings['logo_list'], $lgrid->settings['thumbnail_size'] ), 'Los ajustes de la cuadrícula de logotipos se traducen, incluido el pie por elemento.' );
	check_logos( str_contains( $html, 'digi-logos--border digi-logos--tooltip digi-logos--cover digi-logo-grid digi-logo-grid--grid digi-logo-grid--border' ) && 4 === substr_count( $html, '<li class="digi-logos__item' ) && str_contains( $html, 'data-size="large"' ), 'El diseño, la cobertura y el tamaño de imagen se conservan.' );
	check_logos( str_contains( $html, '<figcaption class="digi-logos__content digi-logos__content--tooltip digi-logo-grid__caption digi-logo-grid__caption--tooltip"><strong class="digi-logos__name">Tercero</strong><span class="digi-logos__description">Detalle</span></figcaption>' ) && str_contains( $html, '<figcaption class="digi-logos__content digi-logo-grid__caption"><strong class="digi-logos__name">Cuarto</strong></figcaption>' ) && ! str_contains( $html, 'Quinto</strong>' ), 'El pie es globo sólo con enlace, estático sin enlace y ausente sin la opción.' );
	check_logos( str_contains( $html, '<a class="digi-logos__card digi-logo-grid__card" href="https://example.test/" target="_blank" rel="noopener noreferrer">' ) && str_contains( $html, 'alt="Tercero"' ), 'La tarjeta enlazada protege la pestaña y conserva el ALT por nombre.' );
	ob_start(); ( new \ReflectionMethod( $lgrid, 'content_template' ) )->invoke( $lgrid ); $editor = ob_get_clean();
	check_logos( str_contains( $editor, '["digi-logo-grid",{"content":"caption"' ), 'La vista previa del alias conoce sus clases de antes.' );

	$js = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/js/carousel-engine.js' );
	$css = file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/logos.css' ) . file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/css/logos-grid.css' );
	check_logos( str_contains( $js, 'frontend/element_ready/digitalisimo-logos.default' ) && str_contains( $js, 'frontend/element_ready/digitalisimo-brand-carousel.default' ) && str_contains( $js, 'frontend/element_ready/digitalisimo-logo-carousel.default' ), 'El motor se engancha al widget y a sus alias en el editor.' );
	check_logos( str_contains( $css, '.digi-logos--overlay .digi-logos__content' ) && str_contains( $css, '.digi-logos__content--tooltip' ) && str_contains( $css, '.digi-logos--tictactoe' ) && str_contains( $css, '@media(hover:none)' ) && ! str_contains( $css, 'digi-brand-' ) && ! str_contains( $css, 'digi-logo-grid' ), 'La hoja unificada cubre los cuatro diseños y sólo con sus propias clases.' );
	foreach ( array( 'brand-carousel', 'logo-carousel', 'brand-grid', 'logo-grid' ) as $retired ) {
		check_logos( ! is_file( __DIR__ . '/../digitalisimo-elements/assets/css/' . $retired . '.css' ), 'La hoja de ' . $retired . ' ya no existe: el alias usa la de Logotipos.' );
	}
	echo "DIGITALÍSIMO Elements: Logotipos y sus cuatro alias validados.\n";
}
