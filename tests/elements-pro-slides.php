<?php
/**
 * Diapositivas (`digitalisimo-slides`, C1 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c1.md §2 «S»), el widget
 * propio que sustituye al Slides de PRO, y su mapa `modules/digitalisimo-convert/maps/slides.php`:
 * - registro y recursos (hoja propia ≤ 4 KB sin JavaScript propio, sobre el motor `digi-crs`; movimiento reducido, foco
 *   visible, sin `--e-global-*` ni `url()`, sólo los colores por defecto de PRO);
 * - controles: se ejecutan los `register_controls()` de PRO y del widget propio con los mismos dobles y se comparan
 *   uno a uno (nombre, valor por defecto, opciones, condiciones, dispositivos, unidades, rangos, grupos y sus globales),
 *   también los campos del repetidor;
 * - marcado del servidor: motor, fondo (`<img>` con las decisiones de carga de WordPress), Ken Burns, superposición,
 *   título y descripción con sus etiquetas, botón, enlace en la diapositiva o en el botón, navegación, reproducción
 *   automática, transición, animación, escape;
 * - «Plantilla en lugar del texto» por diapositiva (`Slides_Templates`), con las reglas del widget de PRO, y la caché
 *   de elementos (`Visitor_Content`) que la detecta también en el widget propio;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos, repetidor con estilo propio,
 *   `__dynamic__` y `__globals__` en el widget y en las filas, CSS personalizado con clases de PRO, responsivos):
 *   ningún `pro.unmapped`, cada clave cae en un control propio y lo convertido se pinta con los mismos textos.
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $tabs     = array();
		public function get_settings_for_display() { return $this->settings; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function start_controls_tabs( $name ) { $this->tabs[] = $name; }
		public function start_controls_tab( $name, $args ) { $this->tabs[] = $name; }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
		public function get_title() { return ''; }
	}
	class Repeater extends Widget_Base {
		public function get_controls() {
			$out = $this->controls;
			foreach ( $this->groups as $name => $group ) {
				$out[ $name ] = array( 'group' => true ) + $group;
			}
			return $out;
		}
	}
	class Controls_Manager { const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const TAB_STYLE = 'style'; const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater'; const NUMBER = 'number'; const DIMENSIONS = 'dimensions'; const SECTION = 'section'; const SELECT2 = 'select2'; }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Plugin {
		public static $instance;
		public $frontend;
		public static function instance() { return self::$instance; }
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
}
namespace ElementorPro\Modules\Slides\Controls {
	class Control_Slides_Animation {
		const TYPE = 'animation_slides_content';
		public static function get_animations() { return array( 'fadeInDown' => 'Down', 'fadeInUp' => 'Up', 'fadeInRight' => 'Right', 'fadeInLeft' => 'Left', 'zoomIn' => 'Zoom' ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['sl_status']   = array();
	$GLOBALS['sl_actions']  = array();
	$GLOBALS['sl_loading']  = 0;
	$GLOBALS['sl_rendered'] = array();
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function is_rtl() { return ! empty( $GLOBALS['sl_rtl'] ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v, $p = null ) { return preg_match( '~^(https?://|/|#|mailto:)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function wp_allowed_protocols() { return array( 'http', 'https', 'mailto' ); }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $v ) ) ); }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@</?script[^>]*>@i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ) ); }
	function get_post_meta( $id, $key ) { return 7 === $id ? 'Montañas al amanecer' : ''; }
	function get_post_mime_type( $id ) { return 'image/jpeg'; }
	function get_post_status( $id ) { return $GLOBALS['sl_status'][ $id ] ?? false; }
	function add_action( $hook, $callback ) { $GLOBALS['sl_actions'][] = $hook; }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) {
		// Como WordPress: la primera imagen de contenido, en prioridad alta; las siguientes, diferidas.
		$attrs += 0 === $GLOBALS['sl_loading']++ ? array( 'fetchpriority' => 'high' ) : array( 'loading' => 'lazy' );
		$out = '<img src="https://sitio.test/' . $id . '-' . $size . '.jpg" srcset="https://sitio.test/' . $id . '-1024.jpg 1024w" width="1920" height="1080"';
		foreach ( $attrs as $k => $v ) { $out .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) . '"'; }
		return $out . '>';
	}
	function wp_get_loading_optimization_attributes( $tag, $attrs, $context ) { return 0 === $GLOBALS['sl_loading']++ ? array( 'fetchpriority' => 'high' ) : array( 'loading' => 'lazy' ); }
	function check_sl( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	\Elementor\Plugin::$instance           = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->frontend = new class() {
		public function get_builder_content_for_display( $id, $css ) {
			$GLOBALS['sl_rendered'][] = $id;
			return '<div class="elementor elementor-' . $id . '"><p>Plantilla ' . $id . '</p><script>var x=1;</script></div>';
		}
	};

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/slides/widgets/slides.php';
	require $root . 'modules/digitalisimo-widgets/class-image-markup.php';
	require $root . 'modules/digitalisimo-widgets/class-slides-templates.php';
	require $root . 'modules/digitalisimo-conditions/class-visitor-content.php';
	require $root . 'modules/digitalisimo-carousel/class-module.php';
	\Digitalisimo\Elements\Carousel\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Carousel\Engine;
	use Digitalisimo\Elements\Carousel\Module as Carousel;
	use Digitalisimo\Elements\Carousel\Widgets\Slides;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;
	use Digitalisimo\Elements\Slides_Templates;

	check_sl( 'Digitalisimo\\Elements\\Carousel\\Widgets\\Slides' === Carousel::load( 'digitalisimo-slides' ) || class_exists( Slides::class ), 'El módulo de carruseles carga las Diapositivas.' );
	$render = static function ( array $settings ) {
		$widget           = new Slides();
		$widget->settings = $settings;
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};

	// 1. Registro y recursos.
	$widget = new Slides();
	check_sl( 'digitalisimo-slides' === $widget->get_name() && 'Diapositivas' === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories(), 'Nombre, título y categoría propios.' );
	check_sl( array( Engine::HANDLE, 'digitalisimo-slides' ) === $widget->get_style_depends() && array( Engine::HANDLE, 'digitalisimo-slides' ) === $widget->get_script_depends(), 'La hoja y el script del motor y los propios.' );
	check_sl( 'Diapositivas' === Carousel::TITLES['digitalisimo-slides'] && 'widgets/class-slides.php' === Carousel::WIDGETS['digitalisimo-slides']['file'] && Slides::class === Carousel::WIDGETS['digitalisimo-slides']['class'], 'El módulo lo declara con su archivo, su clase y su título.' );
	$js = (string) file_get_contents( $root . Carousel::WIDGETS['digitalisimo-slides']['js'] );
	check_sl( strlen( $js ) > 0 && strlen( $js ) <= 1024 && ! preg_match( '/jQuery|\$\(|swiper|fetch\(|XMLHttpRequest/i', $js ) && str_contains( $js, "addEventListener('digi-crs-change'" ) && str_contains( $js, 'prefers-reduced-motion: reduce' ) && str_contains( $js, "'--digi-crs-speed'" ), 'Script propio de ' . strlen( $js ) . ' B (tope 1 KB), sin jQuery ni Swiper: oculta el contenido con cada cambio y lo deja entrar al terminar la transición; nada con movimiento reducido.' );
	$css = (string) file_get_contents( $root . Carousel::WIDGETS['digitalisimo-slides']['css'] );
	check_sl( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_sl( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( str_replace( '.elementor-widget-digitalisimo-slides{', '', $css ), 'elementor' ) && ! str_contains( $css, 'swiper' ), 'La hoja no usa el kit, url(), clases del proveedor ni de Swiper.' );
	check_sl( 0 === strpos( $css, '.elementor-widget-digitalisimo-slides{--digi-crs-view:1;--digi-crs-scroll:1;--digi-crs-gap:0px}' ), 'Las variables de maquetación del motor se declaran en el widget: un carrusel dentro de una diapositiva no hereda las de fuera.' );
	check_sl( str_contains( $css, '@media (prefers-reduced-motion:reduce){.digi-slides__bg--kb,.digi-slides__contents{transition:none!important}.digi-slides__bg--kb{transform:none!important}}' ), 'Ken Burns y la animación del contenido se apagan con movimiento reducido.' );
	check_sl( str_contains( $css, '.digi-slides .digi-slides__button:focus-visible{outline:2px solid;outline-offset:2px}' ) && str_contains( $css, '.digi-slides .digi-slides__inner:focus-visible{outline:2px solid;' ), 'Foco visible propio en el botón y en la diapositiva enlazada.' );
	preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css, $colors );
	check_sl( array( '#fff' ) === array_values( array_unique( array_map( 'strtolower', $colors[0] ) ) ), 'Sólo el blanco que PRO usaba por defecto: ' . implode( ', ', $colors[0] ) );
	foreach ( Slides::ANIMATIONS as $animation ) {
		check_sl( str_contains( $css, '.digi-slides--out[data-digi-animation=' . $animation . ']{--digi-slides-t:' ), "La animación $animation tiene su estado de entrada." );
	}
	check_sl( str_contains( $css, '.digi-slides{--digi-slides-o:1;--digi-slides-t:none;--digi-slides-d:1.25s}' ) && str_contains( $css, '.digi-slides.digi-slides--out{--digi-slides-o:0;--digi-slides-d:0s}' ), 'El contenido se oculta al instante y entra en 1,25 s (como `.animated`); cada raíz restablece sus variables para un carrusel anidado.' );
	$source = '';
	foreach ( token_get_all( (string) file_get_contents( $root . 'modules/digitalisimo-carousel/widgets/class-slides.php' ) ) as $token ) {
		$source .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
	}
	check_sl( ! preg_match( '/jquery|swiper|wp_enqueue_script|wp_register_script|Icons_Manager|eicon-chevron|elementor-pro-frontend|ElementorPro\\\\/i', $source ), 'Sin jQuery, Swiper, scripts encolados a mano, iconos del proveedor ni clases de PRO.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-carousel/widgets/class-slides.php', 'modules/digitalisimo-carousel/assets/css/slides.css', 'modules/digitalisimo-carousel/assets/js/slides.js', 'modules/digitalisimo-convert/maps/slides.php', 'modules/digitalisimo-widgets/class-slides-templates.php' ) as $file ) {
		check_sl( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file pasa la guarda de build-packages.sh." );
	}

	// 2. Los controles de PRO, uno a uno, en el widget propio.
	$pro = new \ElementorPro\Modules\Slides\Widgets\Slides();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
	( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	$map = require $root . 'modules/digitalisimo-convert/maps/slides.php';
	$same = static function ( string $where, string $name, array $a, array $b ) {
		foreach ( array( 'type', 'default', 'condition', 'conditions', 'responsive', 'size_units', 'range', 'tablet_default', 'mobile_default', 'show_label', 'label_block', 'title_field', 'classes', 'separator' ) as $key ) {
			$va = $a[ $key ] ?? null;
			$vb = $b[ $key ] ?? null;
			if ( 'type' === $key && 'animation_slides_content' === $va ) {
				check_sl( 'select' === $vb && array_merge( array( '' ), array_keys( \ElementorPro\Modules\Slides\Controls\Control_Slides_Animation::get_animations() ) ) === array_map( 'strval', array_keys( $b['options'] ) ), "$where$name: las animaciones de PRO (y «Ninguna»), como lista." );
				continue;
			}
			if ( 'default' === $key && in_array( $a['type'] ?? '', array( 'text', 'textarea' ), true ) ) {
				check_sl( is_string( $vb ) && '' !== $vb, "$where$name: texto por defecto propio." );
				continue;
			}
			if ( 'default' === $key && 'repeater' === ( $a['type'] ?? '' ) ) {
				check_sl( count( $va ) === count( $vb ), "$where$name: las mismas filas por defecto." );
				foreach ( $va as $i => $row ) {
					check_sl( $row['background_color'] === $vb[ $i ]['background_color'] && $row['background_image'] === $vb[ $i ]['background_image'] && array_keys( $row ) === array_keys( $vb[ $i ] ), "$where$name: fila $i con los mismos colores y campos." );
				}
				continue;
			}
			check_sl( $va === $vb, "$where$name: «{$key}» distinto de PRO (" . json_encode( $va ) . ' / ' . json_encode( $vb ) . ').' );
		}
		if ( isset( $a['options'] ) && 'animation_slides_content' !== ( $a['type'] ?? '' ) ) {
			check_sl( array_keys( $a['options'] ) === array_keys( $b['options'] ?? array() ), "$where$name: las mismas opciones que PRO." );
		}
		if ( isset( $a['selectors_dictionary'] ) && 'background_size' !== $name ) {
			check_sl( $a['selectors_dictionary'] === ( $b['selectors_dictionary'] ?? null ), "$where$name: el mismo diccionario de valores." );
		}
		// Excepciones: la imagen es un <img> (sin fondo CSS) y la regla de PRO con la duración del autoplay la anulaba Swiper.
		if ( ! in_array( $name, array( 'background_image', 'autoplay_speed' ), true ) ) {
			check_sl( isset( $a['selectors'] ) === isset( $b['selectors'] ) && isset( $a['prefix_class'] ) === ( isset( $b['prefix_class'] ) || in_array( $name, array( 'arrows_position', 'dots_position' ), true ) ), "$where$name: aplica su estilo como PRO." );
		}
	};
	$compare_groups = static function ( string $where, string $name, array $a, array $b ) {
		foreach ( array( 'type', 'global', 'types', 'exclude', 'fields_options', 'conditions', 'condition' ) as $key ) {
			check_sl( ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ), "$where$name: «{$key}» del grupo distinto de PRO." );
		}
		check_sl( isset( $a['selector'] ) && isset( $b['selector'] ), "$where$name: el grupo tiene selector." );
	};
	foreach ( $pro->controls as $name => $args ) {
		check_sl( isset( $widget->controls[ $name ] ), "$name: sin control propio." );
		$same( '', $name, $args, $widget->controls[ $name ] );
	}
	check_sl( array_keys( $pro->controls ) === array_keys( $widget->controls ), 'Los mismos controles en el mismo orden.' );
	foreach ( $pro->groups as $name => $args ) {
		check_sl( isset( $widget->groups[ $name ] ), "$name: sin grupo propio." );
		$compare_groups( '', $name, $args, $widget->groups[ $name ] );
	}
	check_sl( array_keys( $pro->groups ) === array_keys( $widget->groups ) && array_keys( $pro->sections ) === array_keys( $widget->sections ) && $pro->tabs === $widget->tabs, 'Los mismos grupos, secciones y pestañas.' );
	foreach ( $pro->sections as $name => $args ) {
		check_sl( ( $args['tab'] ?? null ) === ( $widget->sections[ $name ]['tab'] ?? null ) && ( $args['condition'] ?? null ) === ( $widget->sections[ $name ]['condition'] ?? null ), "$name: la sección está en la misma pestaña con la misma condición." );
	}
	$pro_fields = $pro->controls['slides']['fields'];
	$own_fields = $widget->controls['slides']['fields'];
	check_sl( array_keys( $pro_fields ) === array_keys( $own_fields ), 'Los mismos campos en el repetidor: ' . implode( ', ', array_keys( $own_fields ) ) );
	foreach ( $pro_fields as $name => $args ) {
		if ( ! empty( $args['group'] ) ) {
			$compare_groups( 'slides.', $name, $args, $own_fields[ $name ] );
			continue;
		}
		$same( 'slides.', $name, $args, $own_fields[ $name ] );
		foreach ( (array) ( $own_fields[ $name ]['selectors'] ?? array() ) as $selector => $rule ) {
			check_sl( str_contains( $selector, '{{CURRENT_ITEM}}' ), "slides.$name: su estilo es sólo de su diapositiva." );
		}
	}
	// Cobertura del mapa: cada control de PRO, del widget y del repetidor, declarado.
	$declared = array_merge( $map['keep'], array_keys( $map['groups'] ), array_keys( $map['approx'] ), $map['ui'], array_keys( $map['repeaters'] ) );
	foreach ( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) ) as $name ) {
		check_sl( in_array( $name, $declared, true ), "$name: el mapa no lo declara." );
	}
	foreach ( array_keys( $pro_fields ) as $name ) {
		check_sl( in_array( $name, array_merge( $map['repeaters']['slides']['keep'], array_keys( $map['repeaters']['slides']['groups'] ) ), true ), "slides.$name: el mapa no lo declara." );
	}
	foreach ( $map['ui'] as $name ) {
		check_sl( 'heading' === $widget->controls[ $name ]['type'], "$name: encabezado del panel." );
	}
	foreach ( $map['defaults'] as $name => $value ) {
		$own = $widget->controls[ $name ]['default'] ?? null;
		check_sl( $own === $value || ( is_array( $value ) && is_array( $own ) && array_intersect_key( $value, $own ) == $own ) || 'slides_name' === $name, "$name: el valor por defecto del mapa es el del control." );
	}
	foreach ( array( 'heading_typography' => 'primary', 'description_typography' => 'secondary', 'button_typography' => 'accent' ) as $group => $id ) {
		check_sl( array( 'default' => 'globals/typography?id=' . $id ) === $widget->groups[ $group ]['global'], "$group: la tipografía global de PRO por defecto." );
	}
	// Plantilla por diapositiva: el campo se añade a las Diapositivas propias con las mismas reglas.
	Slides_Templates::init();
	check_sl( in_array( 'elementor/element/slides/section_slides/before_section_end', $GLOBALS['sl_actions'], true ) && in_array( 'elementor/element/digitalisimo-slides/section_slides/before_section_end', $GLOBALS['sl_actions'], true ), 'El campo «Plantilla en lugar del texto» se añade al repetidor de los dos widgets.' );

	// 3. Marcado del servidor.
	check_sl( '' === $render( array( 'slides' => array() ) ) && '' === $render( array() ), 'Sin diapositivas no se pinta nada.' );
	$base = array(
		'slides_name' => 'Portada', 'navigation' => 'both', 'autoplay' => '', 'infinite' => 'yes', 'transition' => 'slide', 'transition_speed' => 500,
		'content_animation' => 'fadeInUp', 'slides_title_tag' => 'h2', 'slides_description_tag' => 'p', 'button_size' => 'sm', 'arrows_position' => 'inside', 'dots_position' => 'inside',
		'slides' => array(
			array( '_id' => 'a1', 'heading' => 'Uno', 'description' => 'Texto uno', 'button_text' => 'Ver', 'link' => array( 'url' => 'https://a.test/uno', 'is_external' => 'on', 'nofollow' => 'on' ), 'link_click' => 'slide', 'background_image' => array( 'id' => 7, 'url' => 'https://sitio.test/7.jpg' ), 'background_ken_burns' => 'yes', 'zoom_direction' => 'out', 'background_overlay' => 'yes' ),
			array( '_id' => 'b2', 'heading' => 'Dos', 'description' => '', 'button_text' => 'Comprar', 'link' => array( 'url' => '/tienda/' ), 'link_click' => 'button', 'background_image' => array( 'url' => 'https://otro.test/fondo.jpg', 'width' => 800, 'height' => 400 ) ),
			array( '_id' => 'c3', 'heading' => '', 'description' => 'Sólo texto', 'button_text' => '', 'background_image' => array( 'url' => '' ), 'background_ken_burns' => 'yes' ),
		),
	);
	$GLOBALS['sl_loading'] = 0;
	$html = $render( $base );
	check_sl( 0 === strpos( $html, '<section class="digi-crs digi-slides" aria-roledescription="carrusel" aria-label="Portada" data-digi-loop="1" data-digi-effect="slide" data-digi-pagination="bullets" style="--digi-crs-speed:500ms;--digi-crs-n:3" data-digi-animation="fadeInUp">' ), 'Raíz del motor con el nombre, el bucle, la paginación y la animación: ' . substr( $html, 0, 300 ) );
	check_sl( 1 === substr_count( $html, ' digi-crs__prev"' ) && 1 === substr_count( $html, ' digi-crs__next"' ) && 3 === substr_count( $html, 'class="digi-crs__dot"' ) && ! str_contains( $html, 'digi-crs__pause' ) && str_contains( $html, '<div class="digi-crs__track" aria-live="polite">' ), 'Flechas, tres puntos y sin pausa (sin reproducción automática).' );
	check_sl( str_ends_with( $html, '</div></section>' ) && 3 === substr_count( $html, 'role="group" aria-roledescription="diapositiva"' ) && str_contains( $html, 'aria-label="2 de 3"' ), 'Tres diapositivas con su nombre accesible.' );
	$one = substr( $html, strpos( $html, '<div class="digi-crs__slide digi-slides__slide elementor-repeater-item-a1"' ) );
	$one = substr( $one, 0, strpos( $one, '<div class="digi-crs__slide', 10 ) );
	check_sl( '<div class="digi-crs__slide digi-slides__slide elementor-repeater-item-a1" role="group" aria-roledescription="diapositiva" aria-label="1 de 3"><div class="digi-slides__bg digi-slides__bg--kb digi-slides__bg--kb-out"><img src="https://sitio.test/7-full.jpg" srcset="https://sitio.test/7-1024.jpg 1024w" width="1920" height="1080" class="digi-slides__img" alt="Montañas al amanecer" decoding="async" sizes="(max-width: 767px) 100vw, (max-width: 1024px) 100vw, 1140px" fetchpriority="high"></div><div class="digi-slides__overlay"></div><a class="digi-slides__inner" href="https://a.test/uno" target="_blank" rel="nofollow"><div class="digi-slides__contents"><h2 class="digi-slides__heading">Uno</h2><p class="digi-slides__description">Texto uno</p><div class="elementor-button digi-slides__button elementor-size-sm">Ver</div></div></a></div>' === $one, 'Primera diapositiva: imagen de Medios en prioridad alta (sin fondo CSS que retrase el LCP), Ken Burns hacia fuera, superposición y enlace en toda la diapositiva: ' . $one );
	check_sl( str_contains( $html, '<div class="digi-slides__bg"><img class="digi-slides__img" alt="" decoding="async" src="https://otro.test/fondo.jpg" width="800" height="400" loading="lazy"></div><div class="digi-slides__inner"><div class="digi-slides__contents"><h2 class="digi-slides__heading">Dos</h2><a class="elementor-button digi-slides__button elementor-size-sm" href="/tienda/">Comprar</a></div></div>' ), 'Imagen sólo con URL por el mismo generador; enlace sólo en el botón; sin descripción vacía.' );
	check_sl( str_contains( $html, '<div class="digi-slides__bg digi-slides__bg--kb digi-slides__bg--kb-in"></div><div class="digi-slides__inner"><div class="digi-slides__contents"><p class="digi-slides__description">Sólo texto</p></div></div>' ), 'Sin imagen ni título ni botón: sólo el fondo y la descripción.' );
	$single = $render( array( 'slides' => array( $base['slides'][2] ), 'navigation' => 'both', 'autoplay' => 'yes', 'autoplay_speed' => 3000 ) );
	check_sl( ! str_contains( $single, 'digi-crs__prev' ) && ! str_contains( $single, 'digi-crs__dot' ) && ! str_contains( $single, 'digi-crs__pause' ), 'Una sola diapositiva: sin flechas, puntos ni pausa (como PRO).' );
	$auto = $render( array( 'autoplay' => 'yes', 'autoplay_speed' => 3000, 'pause_on_hover' => 'yes', 'pause_on_interaction' => '', 'transition' => 'fade', 'transition_speed' => 800, 'navigation' => 'arrows', 'content_animation' => '', 'infinite' => '', 'arrows_position' => 'outside' ) + $base );
	check_sl( str_contains( $auto, 'class="digi-crs digi-crs--fade digi-crs--arrows-out digi-slides"' ) && str_contains( $auto, 'data-digi-autoplay="3000" data-digi-hover="1" data-digi-effect="fade"' ) && ! str_contains( $auto, 'data-digi-interaction' ) && ! str_contains( $auto, 'data-digi-loop' ) && str_contains( $auto, '--digi-crs-speed:800ms' ) && ! str_contains( $auto, 'data-digi-animation' ), 'Reproducción automática, pausa al pasar el cursor, fundido, velocidad, sin bucle, flechas fuera y sin animación.' );
	check_sl( str_contains( $auto, 'class="digi-crs__pause"' ) && str_contains( $auto, 'aria-live="off"' ) && ! str_contains( $auto, 'digi-crs__dot' ) && str_contains( $auto, 'digi-crs__next' ), 'Con reproducción automática, botón de pausa y región viva apagada; sólo flechas.' );
	$dots = $render( array( 'navigation' => 'dots', 'dots_position' => 'outside' ) + $base );
	check_sl( str_contains( $dots, 'digi-crs--pag-out' ) && ! str_contains( $dots, 'digi-crs__prev' ) && str_contains( $dots, 'digi-crs__dot' ), 'Sólo puntos, fuera de la pista.' );
	$none = $render( array( 'navigation' => 'none' ) + $base );
	check_sl( ! str_contains( $none, 'digi-crs__prev' ) && ! str_contains( $none, 'digi-crs__pagination' ), 'Sin navegación.' );
	$tags = $render( array( 'slides_title_tag' => 'script', 'slides_description_tag' => 'iframe' ) + $base );
	check_sl( str_contains( $tags, '<div class="digi-slides__heading">Uno</div>' ) && str_contains( $tags, '<div class="digi-slides__description">Texto uno</div>' ), 'Una etiqueta fuera de la lista de PRO vuelve a div.' );
	$evil = $render( array( 'slides_name' => '"><script>', 'slides' => array( array( '_id' => 'x"y', 'heading' => 'A<script>alert(1)</script><em onclick="x()">b</em>', 'description' => '<img src=x onerror=alert(1)>', 'button_text' => 'B<script>', 'link' => array( 'url' => 'javascript:alert(1)' ) ) ) ) + $base );
	check_sl( ! str_contains( $evil, '<script' ) && ! str_contains( $evil, 'onclick' ) && ! str_contains( $evil, 'onerror' ) && ! str_contains( $evil, 'javascript:' ) && str_contains( $evil, '<em>b</em>' ) && str_contains( $evil, 'aria-label="&quot;&gt;&lt;script&gt;"' ) && str_contains( $evil, 'elementor-repeater-item-xy' ) && str_contains( $evil, '<div class="digi-slides__inner">' ), 'Textos con el HTML permitido, nombre y clase escapados y un enlace no permitido no enlaza: ' . $evil );
	$GLOBALS['sl_rtl'] = true;
	check_sl( str_contains( $render( $base ), '<div class="digi-crs__track" dir="rtl" aria-live="polite">' ) || str_contains( $render( $base ), 'dir="rtl"' ), 'En un sitio RTL, la pista lleva dir="rtl" (como PRO en su envoltorio).' );
	$GLOBALS['sl_rtl'] = false;
	$md           = new Slides();
	$md->settings = $base;
	check_sl( "## Diapositivas\n\n### Uno\n\nTexto uno\n\n[**Ver**](https://a.test/uno)\n\n![](https://sitio.test/7.jpg)\n\n### Dos\n\n[**Comprar**](/tienda/)\n\n![](https://otro.test/fondo.jpg)\n\nSólo texto" === $md->render_markdown(), 'Markdown de las diapositivas como en PRO: ' . $md->render_markdown() );

	// 4. Plantilla en lugar del texto (mismas reglas que en PRO) y caché de elementos.
	$GLOBALS['sl_status'] = array( 40 => 'publish', 41 => 'draft' );
	$templated = $render( array( 'slides' => array( array( '_id' => 't1', 'heading' => 'Con plantilla', 'description' => 'No se ve', 'digitalisimo_template_id' => '40' ), array( '_id' => 't2', 'heading' => 'Borrador', 'description' => 'Sí se ve', 'digitalisimo_template_id' => 41 ) ) ) + $base );
	check_sl( str_contains( $templated, '<div class="digi-slides__description digi-slides__template"><div class="elementor elementor-40"><p>Plantilla 40</p><script>var x=1;</script></div></div>' ) && ! str_contains( $templated, 'No se ve' ) && str_contains( $templated, '<p class="digi-slides__description">Sí se ve</p>' ) && array( 40 ) === $GLOBALS['sl_rendered'], 'Una plantilla publicada ocupa el lugar de la descripción (con su marcado del constructor); un borrador no.' );
	$depth = new ReflectionProperty( Slides_Templates::class, 'depth' );
	$depth->setValue( null, 2 );
	check_sl( '' === Slides_Templates::content( array( 'digitalisimo_template_id' => 40 ) ), 'Más allá del tope de anidamiento, no se pinta la plantilla (como en PRO).' );
	$depth->setValue( null, 0 );
	check_sl( '' === Slides_Templates::content( array( 'digitalisimo_template_id' => 'x' ) ) && '' === Slides_Templates::content( array() ), 'Sin un ID válido, no hay plantilla.' );
	check_sl( true === \Digitalisimo\Elements\Conditions\Visitor_Content::is_dynamic_content( false, array( 'widgetType' => 'digitalisimo-slides', 'settings' => array( 'slides' => array( array( 'digitalisimo_template_id' => '40' ) ) ) ) ) && false === \Digitalisimo\Elements\Conditions\Visitor_Content::is_dynamic_content( false, array( 'widgetType' => 'digitalisimo-slides', 'settings' => array( 'slides' => array( array( 'heading' => 'x' ) ) ) ) ), 'La caché de elementos no guarda unas Diapositivas con plantilla (y sí las que no la tienen).' );

	// 5. Conversión de Slides de PRO con el mapa (listo tras el A/B de C1).
	Registry::reset();
	check_sl( null !== Registry::map( 'slides', true ) && 'ready' === $map['status'] && 'hide' === $map['panel'] && 'digitalisimo-slides' === $map['target'], 'Mapa listo tras el A/B de C1; oculta el widget de PRO.' );
	$own_keys = array_keys( $widget->controls );
	$lands    = static function ( $key, array $controls, array $groups ) {
		$base = Translator::base( (string) $key );
		if ( in_array( $base, $controls, true ) || Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
			return true;
		}
		foreach ( $groups as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$row_controls = array_keys( array_filter( $own_fields, static function ( $f ) { return empty( $f['group'] ); } ) );
	$row_groups   = array_keys( array_filter( $own_fields, static function ( $f ) { return ! empty( $f['group'] ); } ) );
	$cases        = array(
		'defaults' => array(),
		'full'     => array(
			'slides_name' => 'Portada', 'slides_height' => array( 'unit' => 'vh', 'size' => 80 ), 'slides_height_mobile' => array( 'unit' => 'px', 'size' => 300 ), 'slides_title_tag' => 'h2', 'slides_description_tag' => 'p',
			'navigation' => 'arrows', 'autoplay' => '', 'infinite' => '', 'transition' => 'fade', 'transition_speed' => 900, 'content_animation' => 'zoomIn',
			'content_max_width' => array( 'unit' => 'px', 'size' => 600 ), 'content_max_width_tablet' => array( 'unit' => '%', 'size' => 90 ), 'slides_padding' => array( 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px', 'isLinked' => true ),
			'slides_horizontal_position' => 'left', 'slides_vertical_position' => 'bottom', 'slides_text_align' => 'start', 'text_shadow_text_shadow_type' => 'yes', 'text_shadow_text_shadow' => array( 'horizontal' => 1, 'vertical' => 1, 'blur' => 2, 'color' => 'rgba(0,0,0,.5)' ),
			'heading_spacing' => array( 'unit' => 'px', 'size' => 12 ), 'heading_color' => '#ffeeaa', 'heading_typography_typography' => 'custom', 'heading_typography_font_size' => array( 'unit' => 'px', 'size' => 48 ), 'heading_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 28 ),
			'description_spacing' => array( 'unit' => 'px', 'size' => 8 ), 'description_color' => '#eeeeee', 'description_typography_typography' => 'custom', 'description_typography_line_height' => array( 'unit' => 'em', 'size' => 1.6 ),
			'button_size' => 'lg', 'button_typography_typography' => 'custom', 'button_border_width' => array( 'unit' => 'px', 'size' => 3 ), 'button_border_radius' => array( 'unit' => 'px', 'size' => 30 ), 'button_text_color' => '#111111', 'button_background_background' => 'classic', 'button_background_color' => '#ffffff', 'button_border_color' => '#ffffff',
			'button_hover_text_color' => '#ffffff', 'button_hover_background_background' => 'gradient', 'button_hover_background_color' => '#000000', 'button_hover_background_color_b' => '#333333', 'button_hover_border_color' => '#000000', 'button_hover_transition_duration' => array( 'unit' => 'ms', 'size' => 300 ),
			'arrows_position' => 'outside', 'arrows_size' => array( 'unit' => 'px', 'size' => 40 ), 'arrows_size_mobile' => array( 'unit' => 'px', 'size' => 24 ), 'arrows_color' => '#ff0000',
			'slides' => array(
				array( '_id' => 'r1', 'heading' => 'Bienvenida', 'description' => 'Hola', 'button_text' => 'Entrar', 'link' => array( 'url' => 'https://a.test/', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ), 'link_click' => 'button', 'background_color' => '#222222', 'background_image' => array( 'id' => 7, 'url' => 'https://sitio.test/7.jpg', 'alt' => '', 'source' => 'library', 'size' => '' ), 'background_size' => 'contain', 'background_ken_burns' => 'yes', 'zoom_direction' => 'out', 'background_overlay' => 'yes', 'background_overlay_color' => 'rgba(10,20,30,0.6)', 'background_overlay_blend_mode' => 'multiply', 'custom_style' => 'yes', 'horizontal_position' => 'right', 'vertical_position' => 'top', 'text_align' => 'end', 'content_color' => '#00ff00', 'repeater_text_shadow_text_shadow_type' => 'yes', 'repeater_text_shadow_text_shadow' => array( 'horizontal' => 0, 'vertical' => 2, 'blur' => 4, 'color' => '#000' ), 'digitalisimo_template_id' => '40' ),
				array( '_id' => 'r2', 'heading' => 'Segunda', 'description' => 'Adiós', 'button_text' => '', 'background_color' => '#333333' ),
			),
			'custom_css' => "selector .elementor-slides .swiper-slide-inner .elementor-slide-heading{letter-spacing:1px}\nselector.elementor--h-position-left .swiper-slide-contents{margin-left:5%}\nselector .elementor-ken-burns--active{filter:none}\n.elementor-widget-slides .elementor-slide-button:hover{color:red}",
			'_css_classes' => 'mis-diapositivas', '_margin' => array( 'top' => '10', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ),
		),
		'dots'     => array( 'navigation' => 'dots', 'dots_position' => 'outside', 'dots_gap' => array( 'unit' => 'px', 'size' => 10 ), 'dots_size' => array( 'unit' => 'px', 'size' => 12 ), 'dots_size_tablet' => array( 'unit' => 'px', 'size' => 9 ), 'dots_color_inactive' => '#cccccc', 'dots_color' => '#ff6600', 'autoplay' => 'yes', 'autoplay_speed' => 2500, 'pause_on_hover' => '', 'pause_on_interaction' => '' ),
		'globals'  => array(
			'heading_color' => '', 'button_text_color' => '',
			'__globals__' => array( 'heading_color' => 'globals/colors?id=primary', 'description_color' => 'globals/colors?id=text', 'heading_typography_typography' => 'globals/typography?id=secondary', 'button_background_color' => 'globals/colors?id=accent', 'button_hover_border_color' => 'globals/colors?id=secondary', 'arrows_color' => 'globals/colors?id=accent', 'dots_color' => 'globals/colors?id=primary', '_background_color' => 'globals/colors?id=text' ),
			'slides' => array( array( '_id' => 'g1', 'heading' => 'Global', 'background_color' => '', 'content_color' => '', 'custom_style' => 'yes', '__globals__' => array( 'background_color' => 'globals/colors?id=primary', 'content_color' => 'globals/colors?id=accent' ) ) ),
		),
		'dynamic'  => array(
			'__dynamic__' => array( 'slides_name' => '[elementor-tag id="d0" name="post-title" settings="%7B%7D"]' ),
			'slides'      => array( array( '_id' => 'd1', 'heading' => '', 'description' => '', 'button_text' => 'Leer', 'link' => array( 'url' => '' ), '__dynamic__' => array( 'heading' => '[elementor-tag id="d1" name="post-title" settings="%7B%7D"]', 'description' => '[elementor-tag id="d2" name="post-excerpt" settings="%7B%7D"]', 'button_text' => '[elementor-tag id="d3" name="site-title" settings="%7B%7D"]', 'link' => '[elementor-tag id="d4" name="post-url" settings="%7B%7D"]' ) ) ),
		),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'slides', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_sl( count( $cases ) === count( $log ), 'Se convierte cada Slides del árbol.' );
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_sl( 'digitalisimo-slides' === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa a las Diapositivas con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			if ( 'slides' === $key ) {
				continue;
			}
			check_sl( $lands( $key, $own_keys, array_keys( $widget->groups ) ), $element['id'] . ": la clave $key no cae en un control propio." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_sl( $lands( $key, $own_keys, array_keys( $widget->groups ) ), $element['id'] . ": $bag.$key no cae en un control propio." );
			}
		}
		foreach ( (array) ( $element['settings']['slides'] ?? array() ) as $row ) {
			foreach ( $row as $key => $value ) {
				check_sl( $lands( $key, $row_controls, $row_groups ), $element['id'] . ": la clave slides.$key no cae en un campo propio." );
			}
			foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
				foreach ( array_keys( $row[ $bag ] ?? array() ) as $key ) {
					check_sl( $lands( $key, $row_controls, $row_groups ), $element['id'] . ": slides.$bag.$key no cae en un campo propio." );
				}
			}
		}
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_sl( array( 'full:pro.approx:infinite', 'full:pro.approx:content_animation', 'full:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped ni pro.dropped; sólo el CSS convertido y las aproximaciones declaradas: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_sl( 'Slides' === $d['slides_name'] && 400 === $d['slides_height']['size'] && 'both' === $d['navigation'] && 'yes' === $d['autoplay'] && 5000 === $d['autoplay_speed'] && 'yes' === $d['infinite'] && 'fadeInUp' === $d['content_animation'] && 66 === $d['content_max_width']['size'] && 'sm' === $d['button_size'] && 'slides' === $d['_digitalisimo_pro'], 'Unas Slides sin cambios llegan con los valores por defecto visibles de PRO.' );
	check_sl( 3 === count( $d['slides'] ) && 'Slide 1 Heading' === $d['slides'][0]['heading'] && '#833ca3' === $d['slides'][0]['background_color'] && '#1abc9c' === $d['slides'][2]['background_color'] && 'cover' === $d['slides'][1]['background_size'] && 'slide' === $d['slides'][0]['link_click'], 'Sin repetidor guardado, las tres diapositivas por defecto de PRO.' );
	$GLOBALS['sl_loading'] = 0;
	$painted = $render( $d );
	check_sl( 3 === substr_count( $painted, 'digi-slides__heading">Slide ' ) && str_contains( $painted, '>Click Here</div>' ) && str_contains( $painted, 'aria-label="Slides"' ) && str_contains( $painted, 'data-digi-autoplay="5000" data-digi-hover="1" data-digi-interaction="1" data-digi-loop="1"' ), 'Lo convertido se pinta con los mismos textos y la misma reproducción automática que PRO.' );
	$f = $out['full'];
	check_sl( 'vh' === $f['slides_height']['unit'] && 300 === $f['slides_height_mobile']['size'] && 'fade' === $f['transition'] && 900 === $f['transition_speed'] && 'zoomIn' === $f['content_animation'] && 90 === $f['content_max_width_tablet']['size'] && 'left' === $f['slides_horizontal_position'] && 'start' === $f['slides_text_align'] && 'yes' === $f['text_shadow_text_shadow_type'] && 28 === $f['heading_typography_font_size_mobile']['size'] && 1.6 === $f['description_typography_line_height']['size'] && 'gradient' === $f['button_hover_background_background'] && '#333333' === $f['button_hover_background_color_b'] && 'outside' === $f['arrows_position'] && 24 === $f['arrows_size_mobile']['size'], 'Ajustes del widget, grupos y dispositivos pasan a su control.' );
	$r = $f['slides'][0];
	check_sl( 'r1' === $r['_id'] && 7 === $r['background_image']['id'] && 'contain' === $r['background_size'] && 'out' === $r['zoom_direction'] && 'multiply' === $r['background_overlay_blend_mode'] && 'button' === $r['link_click'] && 'right' === $r['horizontal_position'] && 'end' === $r['text_align'] && '#00ff00' === $r['content_color'] && 4 === $r['repeater_text_shadow_text_shadow']['blur'] && '40' === $r['digitalisimo_template_id'], 'La fila con estilo propio conserva su id, su fondo, su enlace, su estilo y su plantilla.' );
	check_sl( 'in' === $f['slides'][1]['zoom_direction'] && 'cover' === $f['slides'][1]['background_size'] && '#333333' === $f['slides'][1]['background_color'], 'Una fila corta recibe los valores por defecto de PRO.' );
	check_sl( "selector .digi-crs__track .digi-slides__inner .digi-slides__heading{letter-spacing:1px}\nselector.digi-slides--h-left .digi-slides__contents{margin-left:5%}\nselector .digi-slides__bg--kb:is(.digi-crs__current>*){filter:none}\n.elementor-widget-digitalisimo-slides .digi-slides__button:hover{color:red}" === $f['custom_css'] && 'mis-diapositivas' === $f['_css_classes'], 'CSS personalizado con las clases propias; Avanzado intacto: ' . $f['custom_css'] );
	$GLOBALS['sl_status'] = array( 40 => 'publish' );
	$full_html            = $render( $f );
	check_sl( str_contains( $full_html, 'digi-crs--fade digi-crs--arrows-out digi-slides' ) && str_contains( $full_html, 'data-digi-animation="zoomIn"' ) && str_contains( $full_html, '<a class="elementor-button digi-slides__button elementor-size-lg" href="https://a.test/">Entrar</a>' ) && str_contains( $full_html, 'Plantilla 40' ) && ! str_contains( $full_html, '>Hola<' ), 'Lo convertido se pinta con su transición, su botón enlazado y la plantilla de la diapositiva.' );
	$o = $out['dots'];
	check_sl( 'outside' === $o['dots_position'] && 12 === $o['dots_size']['size'] && 9 === $o['dots_size_tablet']['size'] && '#cccccc' === $o['dots_color_inactive'] && '#ff6600' === $o['dots_color'] && 2500 === $o['autoplay_speed'] && '' === $o['pause_on_hover'], 'Paginación y reproducción automática.' );
	check_sl( str_contains( $render( $o ), 'data-digi-autoplay="2500" data-digi-loop="1"' ) && str_contains( $render( $o ), 'digi-crs--pag-out' ), 'Lo convertido pinta la reproducción automática sin pausas y los puntos fuera.' );
	check_sl( array( 'heading_color' => 'globals/colors?id=primary', 'description_color' => 'globals/colors?id=text', 'heading_typography_typography' => 'globals/typography?id=secondary', 'button_background_color' => 'globals/colors?id=accent', 'button_hover_border_color' => 'globals/colors?id=secondary', 'arrows_color' => 'globals/colors?id=accent', 'dots_color' => 'globals/colors?id=primary', '_background_color' => 'globals/colors?id=text' ) === $out['globals']['__globals__'], 'Colores y tipografías globales del widget siguen a su control.' );
	check_sl( array( 'background_color' => 'globals/colors?id=primary', 'content_color' => 'globals/colors?id=accent' ) === $out['globals']['slides'][0]['__globals__'], 'Los globales de una diapositiva siguen a su campo.' );
	check_sl( array( 'slides_name' ) === array_keys( $out['dynamic']['__dynamic__'] ) && array( 'heading', 'description', 'button_text', 'link' ) === array_keys( $out['dynamic']['slides'][0]['__dynamic__'] ) && '[elementor-tag id="d4" name="post-url" settings="%7B%7D"]' === $out['dynamic']['slides'][0]['__dynamic__']['link'], 'Las etiquetas dinámicas del widget y de cada diapositiva siguen a su control.' );
	check_sl( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );

	echo 'DIGITALÍSIMO Elements: Diapositivas (' . count( $widget->controls ) . ' controles, ' . count( $widget->groups ) . ' grupos, ' . count( $own_fields ) . ' campos del repetidor, hoja de ' . strlen( $css ) . ' B) y su mapa desde las Slides de PRO (' . count( $cases ) . " casos) validados.\n";
}
