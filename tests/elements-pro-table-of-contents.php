<?php
/**
 * Tabla de contenido (`digitalisimo-table-of-contents`, C2 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c2.md,
 * frente A), el widget propio que sustituye al «Table of Contents» de PRO, y su mapa
 * `modules/digitalisimo-convert/maps/table-of-contents.php`:
 * - registro y recursos (hoja ≤ 4 KB y script ≤ 6 KB propios, sin jQuery, DOMPurify ni innerHTML; foco visible, movimiento
 *   reducido, objetivos de 24 px, sólo los colores por defecto de PRO, sin `--e-global-*` ni `url()`; guarda de
 *   scripts/build-packages.sh);
 * - controles: se ejecutan los `register_controls()` de PRO y del widget propio con los mismos dobles y se comparan uno a
 *   uno (nombre, orden, valor por defecto, opciones —también las de «Minimizada en», de los puntos de corte activos—,
 *   condiciones, dispositivos, unidades, rangos, clases, separadores, estilo y grupos con sus globales);
 * - marcado del servidor: `nav` con nombre, título con su etiqueta, botón para minimizar con sus iconos, cuerpo con el
 *   indicador de carga, plantillas del marcador y del mensaje, configuración del script, escape;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos, `__dynamic__` y `__globals__`, CSS
 *   personalizado con clases de PRO, responsivos): ningún `pro.unmapped`, cada clave cae en un control propio.
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $tabs     = array();
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_id() { return 'ab12cd3'; }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function start_controls_tabs( $name, $args = array() ) { $this->tabs[] = array( $name, $args ); }
		public function start_controls_tab( $name, $args ) { $this->tabs[] = $name; }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
	}
	class Controls_Manager { const TEXT = 'text'; const SELECT = 'select'; const SELECT2 = 'select2'; const ICONS = 'icons'; const SWITCHER = 'switcher'; const COLOR = 'color'; const SLIDER = 'slider'; const CHOOSE = 'choose'; const HEADING = 'heading'; const TAB_STYLE = 'style'; }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Icons_Manager {
		public static function try_get_icon_html( $icon, $attributes = array() ) {
			return empty( $icon['library'] ) ? '' : '<i aria-hidden="true" class="' . htmlspecialchars( (string) $icon['value'], ENT_QUOTES ) . '"></i>';
		}
	}
	class Breakpoint {
		public $label;
		public $value;
		public function __construct( $label, $value ) { $this->label = $label; $this->value = $value; }
		public function get_label() { return $this->label; }
		public function get_value() { return $this->value; }
	}
	class Plugin {
		public static $instance;
		public $breakpoints;
		public $experiments;
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
}
namespace ElementorPro {
	class Plugin {
		public static function elementor() { return \Elementor\Plugin::$instance; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function is_rtl() { return false; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function check_toc( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->breakpoints = new class() {
		public function get_active_breakpoints() {
			return array( 'mobile' => new \Elementor\Breakpoint( 'Móvil vertical', 767 ), 'tablet' => new \Elementor\Breakpoint( 'Tableta vertical', 1024 ), 'laptop' => new \Elementor\Breakpoint( 'Portátil', 1366 ), 'widescreen' => new \Elementor\Breakpoint( 'Pantalla ancha', 2400 ) );
		}
	};
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return false; } };

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/table-of-contents/widgets/table-of-contents.php';
	require $root . 'modules/digitalisimo-content/class-module.php';
	\Digitalisimo\Elements\Content\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Content\Module as Content;
	use Digitalisimo\Elements\Content\Widgets\Table_Of_Contents;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$name = 'digitalisimo-table-of-contents';
	check_toc( Table_Of_Contents::class === Content::load( $name ), 'El módulo de contenido carga la Tabla de contenido.' );
	$render = static function ( array $settings ) {
		$widget           = new Table_Of_Contents();
		$widget->settings = $settings;
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};

	// 1. Registro y recursos.
	$widget = new Table_Of_Contents();
	check_toc( $name === $widget->get_name() && 'Tabla de contenido' === $widget->get_title() && array( 'digitalisimo', 'theme-elements-single' ) === $widget->get_categories(), 'Nombre, título y categorías propios (también la de entradas del Theme Builder, como PRO).' );
	check_toc( array( $name ) === $widget->get_style_depends() && array( $name ) === $widget->get_script_depends(), 'Su hoja y su script, nada de PRO.' );
	check_toc( 'Tabla de contenido' === Content::TITLES[ $name ] && 'widgets/class-table-of-contents.php' === Content::WIDGETS[ $name ]['file'] && Table_Of_Contents::class === Content::WIDGETS[ $name ]['class'], 'El módulo lo declara con su archivo, su clase y su título.' );
	$js = (string) file_get_contents( $root . Content::WIDGETS[ $name ]['js'] );
	check_toc( strlen( $js ) > 0 && strlen( $js ) <= 6144, 'El script pesa ' . strlen( $js ) . ' B (tope 6 144).' );
	$code = (string) preg_replace( array( '@/\*.*?\*/@s', '@^\s*//.*$@m' ), '', $js );
	check_toc( ! preg_match( '/jQuery|\$\(|purify|innerHTML|insertAdjacentHTML|outerHTML|fetch\(|XMLHttpRequest|eval\(/i', $code ), 'Sin jQuery, DOMPurify, HTML desde texto ni peticiones.' );
	foreach ( array( 'IntersectionObserver', "'frontend/element_ready/global'", 'textContent', 'aria-expanded', 'aria-current', 'elementor-toc__heading-anchor-', 'data-elementor-type="popup"', '.elementor-toc__header-title', 'scrollend', 'matchMedia' ) as $needle ) {
		check_toc( str_contains( $js, $needle ), "El script usa $needle." );
	}
	$css = (string) file_get_contents( $root . Content::WIDGETS[ $name ]['css'] );
	check_toc( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_toc( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! preg_match( '/\.elementor-(?!element)/', $css ), 'La hoja no usa el kit, url() ni clases del proveedor.' );
	preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css, $colors );
	check_toc( array( '#9da5ae', '#babfc5' ) === array_values( array_unique( array_map( 'strtolower', $colors[0] ) ) ), 'Sólo los colores por defecto de PRO (borde y barra de desplazamiento): ' . implode( ', ', $colors[0] ) );
	check_toc( str_contains( $css, '.digi-toc .digi-toc__toggle:focus-visible,.digi-toc .digi-toc__link:focus-visible{outline:2px solid;outline-offset:2px}' ), 'Foco visible propio en el botón y en los enlaces.' );
	check_toc( str_contains( $css, 'min-height:24px;min-width:24px' ) && str_contains( $css, 'margin:calc(.5em - 12px)' ), 'Botón de 24 × 24 px como mínimo sin mover el icono.' );
	check_toc( str_contains( $css, '@media (prefers-reduced-motion:reduce){.digi-toc{transition:none}.digi-toc__spinner{animation:none}}' ), 'Con «reducir movimiento», sin transición de altura ni giro del indicador.' );
	check_toc( str_contains( $css, 'ol.digi-toc__list .digi-toc__row:before{content:counters(item,".") ". "}' ) && str_contains( $css, '.digi-toc__body--collapsible>.digi-toc__list>.digi-toc__item>.digi-toc__list:not(.digi-toc__list--open){display:none}' ), 'Números con contadores anidados y subelementos plegables, como PRO.' );
	$source = '';
	foreach ( token_get_all( (string) file_get_contents( $root . 'modules/digitalisimo-content/widgets/class-table-of-contents.php' ) ) as $token ) {
		$source .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
	}
	check_toc( ! preg_match( '/jquery|wp_enqueue_script|wp_register_script|elementor-pro-frontend|ElementorPro\\\\|eicon-loading|content_template/i', $source ), 'Sin jQuery, scripts encolados a mano, clases de PRO, la fuente de iconos para el indicador ni plantilla del editor.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-content/widgets/class-table-of-contents.php', 'modules/digitalisimo-content/assets/css/table-of-contents.css', 'modules/digitalisimo-content/assets/js/table-of-contents.js', 'modules/digitalisimo-convert/maps/table-of-contents.php' ) as $file ) {
		check_toc( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file pasa la guarda de build-packages.sh." );
	}

	// 2. Los controles de PRO, uno a uno, en el widget propio.
	$pro = new \ElementorPro\Modules\TableOfContents\Widgets\Table_Of_Contents();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
	( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	$map = require $root . 'modules/digitalisimo-convert/maps/table-of-contents.php';
	foreach ( $pro->controls as $control => $a ) {
		check_toc( isset( $widget->controls[ $control ] ), "$control: sin control propio." );
		$b = $widget->controls[ $control ];
		foreach ( array( 'type', 'default', 'condition', 'conditions', 'responsive', 'size_units', 'range', 'tablet_default', 'mobile_default', 'show_label', 'label_block', 'classes', 'separator', 'multiple', 'return_value', 'toggle', 'skin', 'recommended', 'exclude_inline_options', 'global' ) as $key ) {
			$va = $a[ $key ] ?? null;
			$vb = $b[ $key ] ?? null;
			if ( 'default' === $key && in_array( $control, array( 'title', 'no_headings_message' ), true ) ) {
				check_toc( is_string( $vb ) && '' !== $vb && $map['defaults'][ $control ] === $va, "$control: texto por defecto propio; el mapa completa el de PRO." );
				continue;
			}
			check_toc( $va === $vb, "$control: «{$key}» distinto de PRO (" . json_encode( $va ) . ' / ' . json_encode( $vb ) . ').' );
		}
		if ( isset( $a['options'] ) ) {
			check_toc( array_keys( $a['options'] ) === array_keys( $b['options'] ?? array() ), "$control: las mismas opciones que PRO." );
		}
		check_toc( isset( $a['selectors'] ) === isset( $b['selectors'] ) && isset( $a['prefix_class'] ) === isset( $b['prefix_class'] ), "$control: aplica su estilo como PRO." );
		foreach ( (array) ( $b['selectors'] ?? array() ) as $selector => $rule ) {
			check_toc( ! str_contains( $selector . $rule, 'elementor-toc' ) && ! str_contains( $rule, '--e-global' ), "$control: selector y variable propios." );
		}
	}
	check_toc( array_keys( $pro->controls ) === array_keys( $widget->controls ), 'Los mismos controles en el mismo orden.' );
	check_toc( array( 'mobile', 'tablet', 'laptop', 'desktop' ) === array_keys( $widget->controls['minimized_on']['options'] ) && 'Tableta vertical (< 1024px)' === $widget->controls['minimized_on']['options']['tablet'], 'Las opciones de «Minimizada en» salen de los puntos de corte activos, sin pantallas anchas, más el escritorio.' );
	foreach ( $pro->groups as $group => $a ) {
		check_toc( isset( $widget->groups[ $group ] ), "$group: sin grupo propio." );
		foreach ( array( 'type', 'global', 'exclude', 'condition' ) as $key ) {
			check_toc( ( $a[ $key ] ?? null ) === ( $widget->groups[ $group ][ $key ] ?? null ), "$group: «{$key}» del grupo distinto de PRO." );
		}
	}
	check_toc( array_keys( $pro->groups ) === array_keys( $widget->groups ) && array_keys( $pro->sections ) === array_keys( $widget->sections ), 'Los mismos grupos y secciones.' );
	check_toc( array_map( static function ( $t ) { return is_array( $t ) ? $t[0] : $t; }, $pro->tabs ) === array_map( static function ( $t ) { return is_array( $t ) ? $t[0] : $t; }, $widget->tabs ), 'Las mismas pestañas.' );
	foreach ( $pro->sections as $section => $args ) {
		check_toc( ( $args['tab'] ?? null ) === ( $widget->sections[ $section ]['tab'] ?? null ), "$section: la sección está en la misma pestaña." );
	}
	// Cobertura del mapa.
	$declared = array_merge( $map['keep'], array_keys( $map['groups'] ), array_keys( $map['approx'] ), $map['ui'] );
	foreach ( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) ) as $control ) {
		check_toc( in_array( $control, $declared, true ), "$control: el mapa no lo declara." );
	}
	foreach ( $map['ui'] as $control ) {
		check_toc( 'heading' === $widget->controls[ $control ]['type'], "$control: encabezado del panel." );
	}
	foreach ( $map['keep'] as $control ) {
		check_toc( isset( $widget->controls[ $control ] ), "$control: `keep` apunta a un control propio." );
	}

	// 3. Marcado del servidor.
	$defaults = array();
	foreach ( $widget->controls as $control => $args ) {
		if ( array_key_exists( 'default', $args ) ) {
			$defaults[ $control ] = $args['default'];
		}
	}
	$html = $render( $defaults );
	$cfg  = '{"tags":["h2","h3","h4","h5","h6"],"container":"","exclude":"","view":"numbers","nested":true,"collapse":false,"minimize":1024}';
	check_toc( 0 === strpos( $html, '<nav class="digi-toc" aria-labelledby="digi-toc-ab12cd3-title" data-digi-toc="' . htmlspecialchars( $cfg, ENT_QUOTES ) . '">' ), 'Raíz `nav` con el título como nombre y la configuración del script (etiquetas, vista, jerarquía, punto de corte de la tableta): ' . substr( $html, 0, 300 ) );
	check_toc( str_contains( $html, '<div class="digi-toc__header"><h4 class="digi-toc__title" id="digi-toc-ab12cd3-title">Tabla de contenido</h4><button type="button" class="digi-toc__toggle" aria-expanded="true" aria-controls="digi-toc-ab12cd3" aria-label="Mostrar u ocultar la tabla de contenido"><span class="digi-toc__toggle-icon digi-toc__toggle-icon--expand"><i aria-hidden="true" class="fas fa-chevron-down"></i></span><span class="digi-toc__toggle-icon digi-toc__toggle-icon--collapse"><i aria-hidden="true" class="fas fa-chevron-up"></i></span></button></div>' ), 'Cabecera con el título h4 y un solo botón con los dos iconos.' );
	check_toc( str_contains( $html, '<div class="digi-toc__body" id="digi-toc-ab12cd3"><span class="digi-toc__spinner" aria-hidden="true"></span></div>' ) && str_ends_with( $html, '<template class="digi-toc__empty">No se encontraron encabezados en esta página.</template></nav>' ) && ! str_contains( $html, 'digi-toc__marker' ), 'Cuerpo con el indicador de carga y el mensaje sin encabezados en su plantilla; sin marcador con números.' );
	$bullets = $render( array( 'marker_view' => 'bullets', 'minimize_box' => '', 'title' => '', 'hierarchical_view' => 'yes', 'collapse_subitems' => 'yes', 'headings_by_tags' => array( 'h2', 'h9', 'h3"><x' ), 'container' => '#main', 'exclude_headings_by_selector' => '.no-toc, .widget' ) + $defaults );
	check_toc( 0 === strpos( $bullets, '<nav class="digi-toc" aria-label="Tabla de contenido" data-digi-toc="' . htmlspecialchars( '{"tags":["h2"],"container":"#main","exclude":".no-toc, .widget","view":"bullets","nested":true,"collapse":true}', ENT_QUOTES ) . '"><div class="digi-toc__body digi-toc__body--collapsible"' ), 'Sin título ni caja minimizable: sin cabecera, nombre propio, sólo etiquetas válidas, contenedor, exclusión y plegado: ' . substr( $bullets, 0, 400 ) );
	check_toc( str_contains( $bullets, '<template class="digi-toc__marker"><i aria-hidden="true" class="fas fa-circle"></i></template>' ), 'Viñetas: el icono elegido en su plantilla.' );
	$flat = $render( array( 'hierarchical_view' => '', 'collapse_subitems' => 'yes', 'minimized_on' => 'desktop', 'html_tag' => 'script', 'icon' => array( 'value' => '', 'library' => '' ), 'marker_view' => 'bullets' ) + $defaults );
	check_toc( str_contains( $flat, htmlspecialchars( '"nested":false,"collapse":false,"minimize":"all"', ENT_QUOTES ) ) && str_contains( $flat, '<h4 class="digi-toc__title"' ) && ! str_contains( $flat, 'digi-toc__marker' ) && ! str_contains( $flat, 'digi-toc__body--collapsible' ), 'Plana (sin plegado), minimizada en todo, etiqueta fuera de la lista → h4, viñetas sin icono → sin marcador.' );
	$mob = $render( array( 'minimized_on' => 'mobile' ) + $defaults );
	check_toc( str_contains( $mob, htmlspecialchars( '"minimize":767', ENT_QUOTES ) ), 'Minimizada en el móvil: su ancho máximo.' );
	$evil = $render( array( 'title' => 'Índice <script>alert(1)</script><em onclick="x()">sí</em>', 'no_headings_message' => '<img src=x onerror=alert(1)>Nada', 'container' => '"><script>' ) + $defaults );
	check_toc( ! str_contains( $evil, '<script' ) && ! str_contains( $evil, 'onclick' ) && ! str_contains( $evil, 'onerror' ) && str_contains( $evil, '<em>sí</em>' ) && str_contains( $evil, '&quot;container&quot;:&quot;\&quot;&gt;&lt;script&gt;&quot;' ), 'Título y mensaje con el HTML permitido; la configuración escapada en su atributo: ' . $evil );
	$md           = new Table_Of_Contents();
	$md->settings = array( 'title' => 'Índice <b>rápido</b>', 'html_tag' => 'h3' );
	check_toc( '### Índice rápido' === $md->render_markdown(), 'Markdown: el título con su nivel.' );

	// 4. Conversión del Table of Contents de PRO.
	Registry::reset();
	check_toc( null !== Registry::map( 'table-of-contents', true ) && 'digitalisimo-table-of-contents' === $map['target'] && 'hide' === $map['panel'], 'Mapa hacia el widget propio; oculta el de PRO.' );
	$own_keys = array_keys( $widget->controls );
	$lands    = static function ( $key ) use ( $own_keys, $widget ) {
		$base = Translator::base( (string) $key );
		if ( in_array( $base, $own_keys, true ) || Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
			return true;
		}
		foreach ( array_keys( $widget->groups ) as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$cases = array(
		'defaults' => array(),
		'full'     => array(
			'title' => 'Contenido', 'html_tag' => 'h2', 'headings_by_tags' => array( 'h2', 'h3' ), 'container' => '.entry', 'exclude_headings_by_selector' => '.skip', 'marker_view' => 'bullets', 'icon' => array( 'value' => 'far fa-dot-circle', 'library' => 'fa-regular' ),
			'no_headings_message' => 'Vacío', 'word_wrap' => 'ellipsis', 'minimize_box' => 'yes', 'expand_icon' => array( 'value' => 'fas fa-angle-down', 'library' => 'fa-solid' ), 'collapse_icon' => array( 'value' => 'fas fa-angle-up', 'library' => 'fa-solid' ), 'minimized_on' => 'mobile', 'hierarchical_view' => 'yes', 'collapse_subitems' => 'yes',
			'background_color' => '#fafafa', 'border_color' => '#cccccc', 'loader_color' => '#ff0000', 'border_width' => array( 'unit' => 'px', 'size' => 2 ), 'border_width_mobile' => array( 'unit' => 'px', 'size' => 1 ), 'border_radius' => array( 'unit' => 'px', 'size' => 8 ), 'header_separator_width' => array( 'unit' => 'px', 'size' => 3 ),
			'padding' => array( 'unit' => 'px', 'size' => 24 ), 'padding_tablet' => array( 'unit' => 'px', 'size' => 16 ), 'min_height' => array( 'unit' => 'px', 'size' => 200 ), 'box_shadow_box_shadow_type' => 'yes', 'box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 4, 'blur' => 10, 'spread' => 0, 'color' => 'rgba(0,0,0,.2)' ),
			'header_text_align' => 'center', 'header_background_color' => '#eeeeee', 'header_text_color' => '#111111', 'header_typography_typography' => 'custom', 'header_typography_font_size' => array( 'unit' => 'px', 'size' => 22 ), 'toggle_button_color' => '#333333', 'toggle_button_position' => 'row-reverse', 'heading_gap' => array( 'unit' => 'px', 'size' => 12 ),
			'max_height' => array( 'unit' => 'px', 'size' => 300 ), 'list_typography_typography' => 'custom', 'list_typography_line_height' => array( 'unit' => 'em', 'size' => 1.8 ), 'list_indent' => array( 'unit' => 'em', 'size' => 2 ), 'item_text_color_normal' => '#222222', 'item_text_underline_normal' => 'yes', 'item_text_color_hover' => '#0055aa', 'item_text_underline_hover' => '', 'item_text_hover_transition_duration' => array( 'unit' => 'ms', 'size' => 300 ),
			'item_text_color_active' => '#aa0000', 'item_text_underline_active' => 'yes', 'marker_color' => '#999999', 'marker_size' => array( 'unit' => 'px', 'size' => 8 ),
			'custom_css' => "selector .elementor-toc__header-title{letter-spacing:1px}\nselector.elementor-toc--collapsed .elementor-toc__toggle-button--expand{color:red}\nselector .elementor-toc__list-item-text.elementor-item-active{font-weight:700}\n.elementor-widget-table-of-contents .elementor-toc__list-wrapper .elementor-toc__list-item{margin:0}",
			'_css_classes' => 'mi-indice', '_element_id' => 'indice',
		),
		'globals'  => array(
			'header_text_color' => '', 'item_text_color_normal' => '',
			'__globals__' => array( 'header_text_color' => 'globals/colors?id=primary', 'item_text_color_normal' => 'globals/colors?id=text', 'item_text_color_hover' => 'globals/colors?id=accent', 'marker_color' => 'globals/colors?id=secondary', 'header_typography_typography' => 'globals/typography?id=secondary', 'list_typography_typography' => 'globals/typography?id=text' ),
		),
		'dynamic'  => array( '__dynamic__' => array( 'title' => '[elementor-tag id="d1" name="post-title" settings="%7B%7D"]', 'no_headings_message' => '[elementor-tag id="d2" name="site-title" settings="%7B%7D"]' ) ),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'table-of-contents', 'settings' => $settings, 'elements' => array() );
	}
	$log       = array();
	$converted = Converter::tree( $tree, $log, true );
	check_toc( count( $cases ) === count( $log ), 'Se convierte cada Table of Contents del árbol.' );
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_toc( $name === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa a la Tabla de contenido con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_toc( $lands( $key ), $element['id'] . ": la clave $key no cae en un control propio." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_toc( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control propio." );
			}
		}
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_toc( array( 'full:pro.approx:box_shadow_box_shadow_type', 'full:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped ni pro.dropped; sólo la sombra aproximada y el CSS convertido: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_toc( 'Table of Contents' === $d['title'] && 'No headings were found on this page.' === $d['no_headings_message'] && 'table-of-contents' === $d['_digitalisimo_pro'], 'Un Table of Contents sin cambios llega con los textos que PRO pintaba.' );
	$painted = $render( $d + $defaults );
	check_toc( str_contains( $painted, '>Table of Contents</h4>' ) && str_contains( $painted, '>No headings were found on this page.</template>' ), 'Lo convertido se pinta con los mismos textos que PRO.' );
	$f = $out['full'];
	check_toc( 'Contenido' === $f['title'] && array( 'h2', 'h3' ) === $f['headings_by_tags'] && '.skip' === $f['exclude_headings_by_selector'] && 'mobile' === $f['minimized_on'] && 1 === $f['border_width_mobile']['size'] && 16 === $f['padding_tablet']['size'] && 22 === $f['header_typography_font_size']['size'] && 1.8 === $f['list_typography_line_height']['size'] && 'yes' === $f['box_shadow_box_shadow_type'] && 'row-reverse' === $f['toggle_button_position'] && 'indice' === $f['_element_id'], 'Ajustes, grupos, dispositivos y Avanzado pasan a su control.' );
	check_toc( "selector .digi-toc__title{letter-spacing:1px}\nselector.digi-toc--collapsed .digi-toc__toggle-icon--expand{color:red}\nselector .digi-toc__link.digi-toc__link--active{font-weight:700}\n.elementor-widget-digitalisimo-table-of-contents .digi-toc__list .digi-toc__item{margin:0}" === $f['custom_css'], 'CSS personalizado con las clases propias: ' . $f['custom_css'] );
	$full_html = $render( $f + $defaults );
	check_toc( str_contains( $full_html, '<h2 class="digi-toc__title"' ) && str_contains( $full_html, 'fas fa-angle-down' ) && str_contains( $full_html, 'far fa-dot-circle' ) && str_contains( $full_html, htmlspecialchars( '"tags":["h2","h3"],"container":".entry","exclude":".skip","view":"bullets","nested":true,"collapse":true,"minimize":767', ENT_QUOTES ) ), 'Lo convertido se pinta con su etiqueta, sus iconos y su configuración.' );
	check_toc( array( 'header_text_color' => 'globals/colors?id=primary', 'item_text_color_normal' => 'globals/colors?id=text', 'item_text_color_hover' => 'globals/colors?id=accent', 'marker_color' => 'globals/colors?id=secondary', 'header_typography_typography' => 'globals/typography?id=secondary', 'list_typography_typography' => 'globals/typography?id=text' ) === $out['globals']['__globals__'], 'Colores y tipografías globales siguen a su control.' );
	check_toc( array( 'title', 'no_headings_message' ) === array_keys( $out['dynamic']['__dynamic__'] ), 'Las etiquetas dinámicas siguen a su control.' );
	check_toc( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );

	echo 'DIGITALÍSIMO Elements: Tabla de contenido (' . count( $widget->controls ) . ' controles, ' . count( $widget->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . ' B) y su mapa desde el Table of Contents de PRO (' . count( $cases ) . " casos) validados.\n";
}
