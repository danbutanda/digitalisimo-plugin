<?php
/**
 * Tarjetas de C2 (frente B, contrato docs/pro/c2.md): Llamada a la acción con imagen (`digitalisimo-call-to-action`),
 * Tarjeta giratoria (`digitalisimo-flip-box`), Lista de precios (`digitalisimo-price-list`) y Tabla de precios
 * (`digitalisimo-price-table`), los widgets propios que sustituyen a Call to Action, Flip Box, Price List y Price Table de
 * PRO, y sus mapas `modules/digitalisimo-convert/maps/<widget de PRO>.php`:
 * - registro y recursos: el módulo los declara (archivo, clase, título), hoja propia ≤ 4 KB sin `--e-global-*`, `url()`
 *   ni clases de PRO, foco visible, movimiento reducido donde hay animación, sólo los colores por defecto de PRO, sin
 *   script en el sitio (la tarjeta giratoria sólo tiene el del editor), sin nombres vetados por el empaquetado;
 * - controles: se ejecutan los `register_controls()` de PRO y del widget propio con los mismos dobles y se comparan uno a
 *   uno (nombre y orden, tipo, valor por defecto, opciones, condiciones, dispositivos, unidades, rangos, diccionarios,
 *   globales, grupos y campos de los repetidores), con las excepciones declaradas;
 * - marcado del servidor de cada widget: pieles, enlaces (caja o botón), imagen con `srcset`, icono, cinta, animaciones
 *   en variables, caras, enfoque sin enlace, precio y moneda, características, escape;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos, `__globals__`, `__dynamic__`, CSS
 *   personalizado con clases de PRO, repetidores, responsivos): ningún `pro.unmapped`, cada clave cae en un control
 *   propio y lo convertido se pinta con los mismos textos.
 */
namespace Elementor {
	class Controls_Stack_Stub {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $tabs     = array();
		public $order    = array();
		public function get_settings_for_display() { return $this->settings; }
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function start_controls_tabs( $name, $args = array() ) { $this->tabs[] = $name; }
		public function start_controls_tab( $name, $args ) { $this->tabs[] = $name; }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; $this->order[] = $name; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'responsive' => true ); $this->order[] = $name; }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; $this->order[] = 'group:' . $args['name']; }
		public function get_title() { return ''; }
		public function get_id() { return 'abc1234'; }
	}
	class Widget_Base extends Controls_Stack_Stub {}
	class Repeater extends Controls_Stack_Stub {
		public function get_controls() {
			$out = $this->controls;
			foreach ( $this->groups as $name => $group ) {
				$out[ $name ] = array( 'group' => true ) + $group;
			}
			return $out;
		}
	}
	class Controls_Manager { const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const TAB_STYLE = 'style'; const TAB_CONTENT = 'content'; const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater'; const NUMBER = 'number'; const DIMENSIONS = 'dimensions'; const ICONS = 'icons'; const HIDDEN = 'hidden'; const HOVER_ANIMATION = 'hover_animation'; }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Text_Stroke { public static function get_type() { return 'text-stroke'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Css_Filter { public static function get_type() { return 'css-filter'; } }
	class Group_Control_Image_Size {
		public static function get_type() { return 'image-size'; }
		public static function get_attachment_image_src( $id, $key, $settings ) { $d = $settings[ $key . '_custom_dimension' ] ?? array(); return 'https://sitio.test/' . $id . '-' . ( $d['width'] ?? 0 ) . 'x' . ( $d['height'] ?? 0 ) . '.jpg'; }
	}
	class Control_Media { public static function get_image_alt( $i ) { return $i['alt'] ?? ''; } }
	class Utils {
		public static function get_placeholder_image_src() { return 'https://sitio.test/placeholder.png'; }
		public static function html_to_plain_text( $h ) { return trim( preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( (string) $h ), ENT_QUOTES, 'UTF-8' ) ) ); }
		public static function parse_custom_attributes( $s ) { $out = array(); foreach ( explode( ',', (string) $s ) as $pair ) { $kv = explode( '|', $pair ); if ( '' !== trim( $kv[0] ) ) { $out[ trim( $kv[0] ) ] = $kv[1] ?? ''; } } return $out; }
	}
	class Icons_Manager {
		public static function render_icon( $icon, $attrs = array() ) { echo '<svg class="e-font-icon-svg e-' . str_replace( ' ', '-', $icon['value'] ) . '" aria-hidden="true"></svg>'; return true; }
		public static function is_migration_allowed() { return true; }
	}
	class Plugin { public static $instance; public $experiments; public static function elementor() { return self::$instance; } }
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
	class Markdown_Utils {}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace {
	if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
	$GLOBALS['cards_loading'] = 0;
	$GLOBALS['cards_rtl']     = false;
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function _x( $v ) { return $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function is_rtl() { return ! empty( $GLOBALS['cards_rtl'] ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v, $p = null ) { return preg_match( '~^(https?://|/|#|mailto:)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $v ) ) ); }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@</?script[^>]*>@i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ) ); }
	function get_post_meta( $id, $key ) { return 7 === (int) $id ? 'Equipo trabajando' : ''; }
	function get_post_mime_type( $id ) { return 'image/jpeg'; }
	function wp_get_attachment_image_src( $id, $size ) { return array( 'https://sitio.test/' . $id . '-' . $size . '.jpg', 300, 200 ); }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) {
		$attrs += 0 === $GLOBALS['cards_loading']++ ? array( 'fetchpriority' => 'high' ) : array( 'loading' => 'lazy' );
		$out = '<img src="https://sitio.test/' . $id . '-' . $size . '.jpg" srcset="https://sitio.test/' . $id . '-1024.jpg 1024w" width="300" height="200"';
		foreach ( $attrs as $k => $v ) { $out .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) . '"'; }
		return $out . '>';
	}
	function wp_get_loading_optimization_attributes( $tag, $attrs, $context ) { return 0 === $GLOBALS['cards_loading']++ ? array( 'fetchpriority' => 'high' ) : array( 'loading' => 'lazy' ); }
	function check_cards( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$root = __DIR__ . '/../digitalisimo-elements/';
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	require $root . 'modules/call-to-action/widgets/call-to-action.php';
	require $root . 'modules/flip-box/widgets/flip-box.php';
	require $root . 'modules/pricing/widgets/price-list.php';
	require $root . 'modules/pricing/widgets/price-table.php';
	require $root . 'modules/digitalisimo-content/class-module.php';
	\Digitalisimo\Elements\Content\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Content\Module as Content;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$widgets = array(
		'call-to-action' => array( 'pro' => 'ElementorPro\\Modules\\CallToAction\\Widgets\\Call_To_Action', 'title' => 'Llamada a la acción con imagen', 'colors' => array( '#3f444b', '#f9fafa', '#fff', '#000' ) ),
		'flip-box'       => array( 'pro' => 'ElementorPro\\Modules\\FlipBox\\Widgets\\Flip_Box', 'title' => 'Tarjeta giratoria', 'colors' => array( '#1abc9c', '#4054b2', '#fff' ) ),
		'price-list'     => array( 'pro' => 'ElementorPro\\Modules\\Pricing\\Widgets\\Price_List', 'title' => 'Lista de precios', 'colors' => array() ),
		'price-table'    => array( 'pro' => 'ElementorPro\\Modules\\Pricing\\Widgets\\Price_Table', 'title' => 'Tabla de precios', 'colors' => array( '#555', '#fff', '#f9fafa', '#000' ) ),
	);
	// Excepciones declaradas a «repite el control de PRO»: qué difiere y por qué (las mismas en el informe del frente).
	$exceptions = array(
		// La posición de la imagen por dispositivo va en variables y no en una clase por dispositivo.
		'call-to-action.layout'          => array( 'prefix_class', 'selectors', 'selectors_dictionary' ),
		// Los interruptores cuyo valor era el nombre de una clase de PRO devuelven la clase propia (el mapa traduce el valor).
		'call-to-action.sequenced_animation' => array( 'return_value' ),
		'flip-box.flip_3d'               => array( 'return_value' ),
		// La hoja de la cinta va en la del widget: sin la hoja condicional de PRO.
		'call-to-action.ribbon_title'    => array( 'assets' ),
		'price-table.ribbon_title'       => array( 'assets' ),
		// Espacio de la imagen con propiedades lógicas, sin depender de la clase `rtl` del `body`.
		'price-list.image_spacing'       => array(),
	);
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	$own   = array();
	$total = array( 'controls' => 0, 'groups' => 0, 'css' => array() );

	// 1. Registro, recursos y controles de los cuatro.
	foreach ( $widgets as $type => $conf ) {
		$name  = 'digitalisimo-' . $type;
		$class = Content::load( $name );
		check_cards( '' !== $class, "$name: el módulo lo carga." );
		$spec = Content::WIDGETS[ $name ];
		check_cards( 'widgets/class-' . $type . '.php' === $spec['file'] && $conf['title'] === Content::TITLES[ $name ], "$name: archivo y título declarados en el módulo." );
		$widget = new $class();
		check_cards( $name === $widget->get_name() && $conf['title'] === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories(), "$name: nombre, título y categoría propios." );
		check_cards( array( $name ) === $widget->get_style_depends() && array() === $widget->get_script_depends() && ! is_file( $root . $spec['js'] ), "$name: sólo su hoja, sin script en el sitio." );
		$css = (string) file_get_contents( $root . $spec['css'] );
		$total['css'][ $type ] = strlen( $css );
		check_cards( strlen( $css ) > 0 && strlen( $css ) <= 4096, "$name: la hoja pesa " . strlen( $css ) . ' B (tope 4 096).' );
		$bare = preg_replace( '/\.elementor-widget-digitalisimo-' . preg_quote( $type, '/' ) . '\b|\.elementor-(icon-wrapper|icon|view-[a-z]+|button|widget-container|widget)\b|\.e-con(-inner)?\b/', '', $css );
		check_cards( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( $bare, 'elementor' ) && ! str_contains( $css, '--e-price-table' ), "$name: la hoja no usa el kit, url() ni clases de PRO (sólo las del núcleo)." );
		check_cards( str_contains( $css, ':focus-visible{outline:2px solid;outline-offset:2px}' ), "$name: foco visible propio (contorno de 2 px con separación de 2 px)." );
		preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css, $colors );
		check_cards( ! array_diff( array_unique( array_map( 'strtolower', $colors[0] ) ), $conf['colors'] ), "$name: sólo los colores por defecto de PRO: " . implode( ', ', array_unique( $colors[0] ) ) );
		$source = '';
		foreach ( token_get_all( (string) file_get_contents( $root . 'modules/digitalisimo-content/' . $spec['file'] ) ) as $token ) {
			$source .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
		}
		check_cards( ! preg_match( '/jquery|swiper|wp_enqueue_script|wp_register_script|elementor-pro-frontend|ElementorPro\\\\|Markdown_Utils|elementor-pro\'|\.elementor-(cta|flip-box|price|ribbon)/i', $source ), "$name: sin jQuery, scripts a mano, clases ni textos de PRO." );
		foreach ( array( 'modules/digitalisimo-content/' . $spec['file'], $spec['css'], 'modules/digitalisimo-convert/maps/' . $type . '.php' ) as $file ) {
			check_cards( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file pasa la guarda de build-packages.sh." );
		}

		$pro = new $conf['pro']();
		( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
		( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
		check_cards( $pro->order === $widget->order, "$name: los mismos controles y grupos en el mismo orden que PRO." );
		check_cards( array_keys( $pro->sections ) === array_keys( $widget->sections ) && $pro->tabs === $widget->tabs, "$name: las mismas secciones y pestañas." );
		foreach ( $pro->sections as $section => $args ) {
			foreach ( array( 'tab', 'condition', 'conditions' ) as $key ) {
				check_cards( ( $args[ $key ] ?? null ) === ( $widget->sections[ $section ][ $key ] ?? null ), "$name: sección $section con «{$key}» distinto." );
			}
		}
		$same = static function ( string $where, array $a, array $b ) use ( $exceptions, $type ) {
			$skip = $exceptions[ $type . '.' . preg_replace( '/^.*\./', '', $where ) ] ?? array();
			foreach ( array( 'type', 'default', 'condition', 'conditions', 'responsive', 'size_units', 'range', 'tablet_default', 'mobile_default', 'show_label', 'label_block', 'title_field', 'separator', 'toggle', 'render_type', 'return_value', 'fa4compatibility', 'selectors_dictionary', 'global', 'classes', 'rows', 'label_on', 'assets' ) as $key ) {
				if ( in_array( $key, $skip, true ) || in_array( $key, array( 'label_on' ), true ) ) {
					continue;
				}
				$va = $a[ $key ] ?? null;
				$vb = $b[ $key ] ?? null;
				if ( 'default' === $key && in_array( $a['type'] ?? '', array( 'text', 'textarea' ), true ) && is_string( $va ) && '' !== $va && ! is_numeric( $va ) ) {
					check_cards( is_string( $vb ) && '' !== $vb && ( $va !== $vb || 'Popular' === $va ), "$where: texto por defecto propio, en español." );
					continue;
				}
				if ( 'default' === $key && 'repeater' === ( $a['type'] ?? '' ) ) {
					check_cards( count( $va ) === count( $vb ), "$where: las mismas filas por defecto." );
					foreach ( $va as $i => $row ) {
						check_cards( array_keys( $row ) === array_keys( $vb[ $i ] ), "$where: fila $i con los mismos campos." );
						foreach ( $row as $field => $value ) {
							check_cards( is_string( $value ) && ! is_numeric( $value ) && '$' !== $value[0] ? '' !== $vb[ $i ][ $field ] : $value === $vb[ $i ][ $field ], "$where: fila $i, $field." );
						}
					}
					continue;
				}
				check_cards( $va === $vb, "$where: «{$key}» distinto de PRO (" . json_encode( $va ) . ' / ' . json_encode( $vb ) . ').' );
			}
			if ( isset( $a['options'] ) || isset( $b['options'] ) ) {
				check_cards( array_keys( (array) ( $a['options'] ?? array() ) ) === array_keys( (array) ( $b['options'] ?? array() ) ), "$where: las mismas opciones que PRO." );
			}
			if ( isset( $a['groups'] ) ) {
				check_cards( array_map( static function ( $g ) { return array_keys( $g['options'] ); }, $a['groups'] ) === array_map( static function ( $g ) { return array_keys( $g['options'] ); }, $b['groups'] ?? array() ), "$where: los mismos grupos de opciones." );
			}
			if ( ! in_array( 'selectors', $skip, true ) ) {
				check_cards( isset( $a['selectors'] ) === isset( $b['selectors'] ) && ( isset( $a['prefix_class'] ) === isset( $b['prefix_class'] ) || in_array( 'prefix_class', $skip, true ) ), "$where: aplica su estilo como PRO." );
			}
			foreach ( array_keys( (array) ( $b['selectors'] ?? array() ) ) as $selector ) {
				check_cards( ! preg_match( '/\.elementor-(cta|flip-box|price|ribbon|bg|animated)/', $selector ) && ! str_contains( (string) implode( '', (array) $b['selectors'] ), '--e-price-table' ), "$where: selector propio ($selector)." );
			}
		};
		$groups = static function ( string $where, array $a, array $b ) {
			foreach ( array( 'type', 'global', 'types', 'exclude', 'fields_options', 'conditions', 'condition', 'default', 'separator' ) as $key ) {
				check_cards( ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ), "$where: «{$key}» del grupo distinto de PRO." );
			}
			check_cards( isset( $a['selector'] ) === isset( $b['selector'] ), "$where: el grupo tiene selector como en PRO." );
			check_cards( ! preg_match( '/\.elementor-(cta|flip-box|price|ribbon|bg)/', (string) ( $b['selector'] ?? '' ) ), "$where: selector propio." );
		};
		foreach ( $pro->controls as $control => $args ) {
			check_cards( isset( $widget->controls[ $control ] ), "$name.$control: sin control propio." );
			$same( "$name.$control", $args, $widget->controls[ $control ] );
			if ( 'repeater' === $args['type'] ) {
				$pf = $args['fields'];
				$of = $widget->controls[ $control ]['fields'];
				check_cards( array_keys( $pf ) === array_keys( $of ), "$name.$control: los mismos campos del repetidor." );
				foreach ( $pf as $field => $fargs ) {
					if ( ! empty( $fargs['group'] ) ) {
						$groups( "$name.$control.$field", $fargs, $of[ $field ] );
						continue;
					}
					$same( "$name.$control.$field", $fargs, $of[ $field ] );
				}
			}
		}
		foreach ( $pro->groups as $group => $args ) {
			$groups( "$name.$group", $args, $widget->groups[ $group ] );
		}
		// Cobertura del mapa: cada control, grupo y campo de PRO, declarado.
		$map      = require $root . 'modules/digitalisimo-convert/maps/' . $type . '.php';
		$declared = array_merge( $map['keep'] ?? array(), array_keys( $map['groups'] ?? array() ), array_keys( $map['approx'] ?? array() ), array_keys( $map['drop'] ?? array() ), $map['ui'] ?? array(), array_keys( $map['repeaters'] ?? array() ) );
		foreach ( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) ) as $control ) {
			check_cards( in_array( $control, $declared, true ), "$type: el mapa no declara $control." );
		}
		foreach ( $map['repeaters'] ?? array() as $key => $rep ) {
			foreach ( array_keys( $pro->controls[ $key ]['fields'] ) as $field ) {
				check_cards( in_array( $field, array_merge( $rep['keep'] ?? array(), array_keys( $rep['groups'] ?? array() ) ), true ), "$type.$key.$field: el mapa no lo declara." );
			}
		}
		foreach ( $map['ui'] ?? array() as $control ) {
			check_cards( 'heading' === $widget->controls[ $control ]['type'], "$type: $control es un encabezado del panel." );
		}
		check_cards( $name === $map['target'] && 'hide' === $map['panel'] && 'ready' === $map['status'] && null !== Registry::map( $type ), "$type: el mapa, listo tras el A/B, apunta al widget propio y oculta el de PRO del panel." );
		$own[ $type ] = $widget;
		$total['controls'] += count( $widget->controls );
		$total['groups']   += count( $widget->groups );
	}
	// El script del editor de la tarjeta giratoria: sólo en el editor, sin dependencias.
	$editor = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/js/flip-box-editor.js' );
	check_cards( str_contains( $editor, "'section:activated'" ) && str_contains( $editor, "'section_side_b_content', 'section_style_b'" ) && str_contains( $editor, 'digi-flip-box--flipped' ) && ! preg_match( '/jQuery|\$\(|fetch\(|XMLHttpRequest/', $editor ), 'El editor muestra el reverso al abrir sus secciones, sin jQuery ni peticiones.' );
	$fbcss = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/css/flip-box.css' );
	check_cards( str_contains( $fbcss, '@media (prefers-reduced-motion:reduce){.digi-flip-box .digi-flip-box__layer{transition:none!important}}' ) && str_contains( $fbcss, '.digi-flip-box:is(:hover,:focus-within) .digi-flip-box__back{transform:none}' ), 'La tarjeta gira con el ratón y con el foco del teclado; sin transición con movimiento reducido.' );
	check_cards( str_contains( $fbcss, '.digi-flip-box--effect-push .digi-flip-box,.digi-flip-box--effect-slide .digi-flip-box{overflow:hidden;overflow:clip}' ), 'Empujar y deslizar recortan con overflow:clip: al llegar con el teclado al enlace del reverso, el navegador no desplaza la tarjeta.' );
	$ctacss = (string) file_get_contents( $root . 'modules/digitalisimo-content/assets/css/call-to-action.css' );
	check_cards( str_contains( $ctacss, '.digi-cta:is(:hover,:focus-within) .digi-cta__content-item{opacity:var(--digi-cta-o1,1);transform:var(--digi-cta-a1,none)}' ) && str_contains( $ctacss, '@media (prefers-reduced-motion:reduce){.digi-cta,.digi-cta *{transition:none!important}.digi-cta .digi-cta__content-item{--digi-cta-a:none;--digi-cta-a1:none;--digi-cta-o:1}' ), 'La llamada a la acción anima con el ratón y con el foco; con movimiento reducido, sin movimiento y con el contenido a la vista.' );

	$render = static function ( $widget, array $settings ) {
		$widget->settings = $settings;
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};
	$defaults = static function ( $widget ) {
		$out = array();
		foreach ( $widget->controls as $name => $args ) {
			if ( array_key_exists( 'default', $args ) ) {
				$out[ $name ] = $args['default'];
			}
		}
		foreach ( $widget->groups as $name => $args ) {
			if ( 'image-size' === $args['type'] ) {
				$out[ $name . '_size' ] = $args['default'] ?? 'full';
			}
		}
		return $out;
	};

	// 2. Marcado del servidor.
	$cta = $own['call-to-action'];
	$d   = $defaults( $cta );
	$GLOBALS['cards_loading'] = 0;
	$html = $render( $cta, $d );
	check_cards( 0 === strpos( $html, '<div class="digi-cta" style="--digi-cta-b1:scale(1.2)"><div class="digi-cta__bg-wrapper"><div class="digi-cta__bg"><img class="digi-cta__bg-img" alt="" decoding="async" src="https://sitio.test/placeholder.png" fetchpriority="high"></div><div class="digi-cta__bg-overlay"></div></div><div class="digi-cta__content"><h2 class="digi-cta__title digi-cta__content-item">' ) && str_contains( $html, '<div class="digi-cta__button-wrapper digi-cta__content-item"><span class="digi-cta__button elementor-button elementor-size-sm">' ), 'CTA por defecto: piel clásica, imagen de fondo como <img> descubrible, título h2, efecto «acercar» en variables y botón sin enlace como span: ' . $html );
	$GLOBALS['cards_loading'] = 1;
	$cover = $render( $cta, array( 'skin' => 'cover', 'content_animation' => 'enter-from-left', 'transformation' => 'move-up', 'graphic_element' => 'icon', 'icon_view' => 'stacked', 'icon_shape' => 'square', 'link' => array( 'url' => 'https://a.test/x', 'is_external' => 'on', 'nofollow' => 'on' ), 'link_click' => 'box', 'ribbon_title' => 'Nuevo', 'ribbon_horizontal_position' => 'left', 'bg_image' => array( 'id' => 7, 'url' => 'https://sitio.test/7.jpg' ), 'bg_image_size' => 'large', 'title' => 'Título', 'title_tag' => 'h3', 'description' => '', 'button' => 'Ver' ) + $d );
	check_cards( 0 === strpos( $cover, '<a class="digi-cta" style="--digi-cta-b0:scale(1.2) translateY(8%);--digi-cta-b1:scale(1.2) translateY(-8%)" href="https://a.test/x" target="_blank" rel="nofollow">' ) && str_contains( $cover, '<img src="https://sitio.test/7-large.jpg" srcset="https://sitio.test/7-1024.jpg 1024w" width="300" height="200" class="digi-cta__bg-img" alt="Equipo trabajando" decoding="async" loading="lazy">' ), 'Enlace en toda la caja (a raíz), imagen de Medios con su tamaño, srcset y su texto alternativo: ' . $cover );
	check_cards( str_contains( $cover, '<div class="elementor-icon-wrapper digi-cta__icon elementor-view-stacked elementor-shape-square digi-cta__content-item digi-cta__item--enter-from-left" style="--digi-cta-a:translateX(-1000px);--digi-cta-o:0"><div class="elementor-icon"><svg' ) && str_contains( $cover, '<h3 class="digi-cta__title digi-cta__content-item digi-cta__item--enter-from-left" style="--digi-cta-a:translateX(-1000px);--digi-cta-o:0">Título</h3>' ) && ! str_contains( $cover, 'digi-cta__description' ) && str_contains( $cover, '<span class="digi-cta__button elementor-button elementor-size-sm">Ver</span>' ), 'Piel cubierta: icono apilado cuadrado, animación de entrada en variables en cada elemento, sin descripción vacía y botón como span dentro del enlace.' );
	check_cards( str_ends_with( $cover, '<div class="digi-ribbon digi-ribbon--left"><div class="digi-ribbon__inner">Nuevo</div></div></a>' ), 'Cinta a la izquierda dentro de la caja.' );
	$classic = $render( $cta, array( 'skin' => 'classic', 'content_animation' => 'grow', 'bg_image' => array( 'url' => '' ), 'graphic_element' => 'image', 'graphic_image' => array( 'id' => 7, 'url' => 'x' ), 'graphic_image_size' => 'custom', 'graphic_image_custom_dimension' => array( 'width' => 120, 'height' => 80 ), 'link' => array( 'url' => '/contacto/' ), 'link_click' => 'button', 'transformation' => '' ) + $d );
	check_cards( 0 === strpos( $classic, '<div class="digi-cta"><div class="digi-cta__content"><div class="digi-cta__image digi-cta__content-item"><img class="" alt="Equipo trabajando"' ) || ( 0 === strpos( $classic, '<div class="digi-cta"><div class="digi-cta__content"><div class="digi-cta__image digi-cta__content-item">' ) && str_contains( $classic, 'src="https://sitio.test/7-120x80.jpg" width="120" height="80"' ) ), 'Clásica sin imagen de fondo: sin envoltorio de fondo; la animación sólo en la piel cubierta; imagen gráfica con tamaño personalizado y sus medidas: ' . $classic );
	check_cards( str_contains( $classic, '<a class="digi-cta__button elementor-button elementor-size-sm" href="/contacto/">' ), 'Enlace sólo en el botón.' );
	$evil = $render( $cta, array( 'title' => 'A<script>x()</script><em onclick="y()">b</em>', 'title_tag' => 'script', 'description_tag' => 'iframe', 'link' => array( 'url' => 'javascript:alert(1)' ), 'link_click' => 'box', 'ribbon_title' => '<img src=x onerror=alert(1)>' ) + $d );
	check_cards( ! str_contains( $evil, '<script' ) && ! str_contains( $evil, 'onclick' ) && ! str_contains( $evil, 'onerror' ) && ! str_contains( $evil, 'javascript:' ) && str_contains( $evil, '<h2 class="digi-cta__title digi-cta__content-item">A<em>b</em></h2>' ) && 0 === strpos( $evil, '<div class="digi-cta"' ), 'Escape: HTML permitido, etiqueta fuera de la lista vuelve a la de respaldo y un enlace no permitido no enlaza: ' . $evil );
	$cta->settings = array( 'bg_image' => array( 'id' => 7, 'url' => '' ), 'graphic_element' => 'none', 'title' => 'Hola', 'title_tag' => 'h3', 'description' => 'Texto', 'button' => 'Ir', 'link' => array( 'url' => 'https://a.test/' ) );
	check_cards( "![Equipo trabajando](https://sitio.test/7-full.jpg)\n\n### Hola\n\nTexto\n\n[**Ir**](https://a.test/)" === $cta->render_markdown(), 'Markdown de la CTA como en PRO: ' . $cta->render_markdown() );

	$fb = $own['flip-box'];
	$d  = $defaults( $fb );
	$html = $render( $fb, $d );
	check_cards( 0 === strpos( $html, '<div class="digi-flip-box" style="--digi-fb-f:rotateX(180deg) rotateY(0);--digi-fb-b:rotateX(-180deg) rotateY(0);--digi-fb-p:translateX(0) translateY(-100%);--digi-fb-q:translateX(0) translateY(100%)" tabindex="0" role="group" aria-label="Este es el título"><div class="digi-flip-box__layer digi-flip-box__front"><div class="digi-flip-box__overlay"><div class="digi-flip-box__inner"><div class="elementor-icon-wrapper digi-flip-box__icon elementor-view-default"><div class="elementor-icon"><svg' ) && str_contains( $html, '<h3 class="digi-flip-box__title">' ) && str_contains( $html, '<span class="digi-flip-box__button elementor-button elementor-size-sm">' ), 'Tarjeta por defecto: giro hacia arriba en variables, enfocable con su nombre (sin enlace), icono del anverso, título h3 y botón sin enlace como span: ' . $html );
	$linked = $render( $fb, array( 'flip_effect' => 'push', 'flip_direction' => 'left', 'link' => array( 'url' => 'https://a.test/', 'is_external' => 'on' ), 'graphic_element' => 'image', 'image' => array( 'id' => 7, 'url' => 'x' ), 'image_size' => 'medium', 'title_tag' => 'p' ) + $d );
	check_cards( 0 === strpos( $linked, '<div class="digi-flip-box" style="--digi-fb-f:rotateX(0) rotateY(-180deg);' ) && ! str_contains( $linked, 'tabindex' ) && str_contains( $linked, '<a class="digi-flip-box__button elementor-button elementor-size-sm" href="https://a.test/" target="_blank">' ) && str_contains( $linked, '<div class="digi-flip-box__image"><img src="https://sitio.test/7-medium.jpg"' ) && str_contains( $linked, '<p class="digi-flip-box__title">' ), 'Con enlace en el botón: la tarjeta no es un grupo enfocable (gira al llegar al enlace); imagen con su tamaño; etiqueta p.' );
	$box = $render( $fb, array( 'flip_effect' => 'fade', 'link' => array( 'url' => '/x/' ), 'link_click' => 'box', 'button_text' => 'Ir' ) + $d );
	check_cards( 0 === strpos( $box, '<div class="digi-flip-box">' ) && str_contains( $box, '<a class="digi-flip-box__layer digi-flip-box__back" href="/x/"><div class="digi-flip-box__overlay">' ) && str_contains( $box, '<span class="digi-flip-box__button elementor-button elementor-size-sm">Ir</span>' ), 'Enlace en todo el reverso; fundido sin variables de dirección.' );
	$fb->settings = array( 'title_text_a' => 'A', 'description_text_a' => 'a', 'title_text_b' => 'B', 'description_text_b' => '', 'button_text' => 'Ir', 'link' => array( 'url' => '/x/' ) );
	check_cards( "### A\n\na\n\n#### B\n\n[**Ir**](/x/)" === $fb->render_markdown(), 'Markdown de la tarjeta como en PRO: ' . $fb->render_markdown() );

	$pl = $own['price-list'];
	$d  = $defaults( $pl );
	$GLOBALS['cards_loading'] = 1;
	$html = $render( $pl, array( 'price_list' => array(
		array( '_id' => 'p1', 'title' => 'Café', 'price' => '$2', 'item_description' => 'Recién hecho', 'image' => array( 'id' => 7, 'url' => 'x' ), 'link' => array( 'url' => '' ) ),
		array( '_id' => 'p2', 'title' => '', 'price' => '', 'item_description' => '', 'link' => array( 'url' => '#' ) ),
		array( '_id' => 'p3', 'title' => 'Té', 'price' => '', 'item_description' => '', 'image' => array( 'url' => 'https://otro.test/te.png' ), 'link' => array( 'url' => '/te/' ) ),
	) ) + $d );
	check_cards( '<ul class="digi-price-list"><li class="digi-price-list__item elementor-repeater-item-p1"><div class="digi-price-list__image"><img src="https://sitio.test/7-thumbnail.jpg" srcset="https://sitio.test/7-1024.jpg 1024w" width="300" height="200" alt="" decoding="async" aria-hidden="true" loading="lazy"></div><div class="digi-price-list__text"><div class="digi-price-list__header"><span class="digi-price-list__title">Café</span><span class="digi-price-list__separator"></span><span class="digi-price-list__price">$2</span></div><p class="digi-price-list__description">Recién hecho</p></div></li><li class="elementor-repeater-item-p3"><a class="digi-price-list__item" href="/te/"><div class="digi-price-list__image"><img alt="" decoding="async" aria-hidden="true" src="https://otro.test/te.png" loading="lazy"></div><div class="digi-price-list__text"><div class="digi-price-list__header"><span class="digi-price-list__title">Té</span><span class="digi-price-list__separator"></span></div></div></a></li></ul>' === $html, 'Lista: imagen con el tamaño del grupo, decorativa junto al título (PRO repetía el título como texto alternativo), separador, fila vacía omitida y fila enlazada entera: ' . $html );
	check_cards( str_contains( $render( $pl, array( 'price_list' => array( array( 'title' => '', 'price' => '$5', 'image' => array( 'id' => 7, 'url' => 'x' ) ) ) ) + $d ), 'alt="Equipo trabajando"' ), 'Sin título, la imagen lleva el texto de Medios.' );
	check_cards( ! str_contains( $render( $pl, array( 'separator_style' => 'none', 'title_tag' => 'h4' ) + $d ), 'separator' ) && str_contains( $render( $pl, array( 'title_tag' => 'h4' ) + $d ), '<h4 class="digi-price-list__title">' ), 'Sin separador con «Ninguno»; etiqueta del título.' );
	$pl->settings = array( 'price_list' => array( array( 'title' => 'Café', 'price' => '$2', 'item_description' => 'Rico', 'link' => array( 'url' => '/c/' ) ) ) );
	check_cards( "- [**Café**](/c/) — *\$2*\n  Rico" === $pl->render_markdown(), 'Markdown de la lista como en PRO: ' . $pl->render_markdown() );

	$pt = $own['price-table'];
	$d  = $defaults( $pt );
	$html = $render( $pt, $d );
	check_cards( str_contains( $html, '<span class="elementor-screen-only">$39.99 Al mes</span><div class="digi-price-table__price" aria-hidden="true"><span class="digi-price-table__currency">$</span><span class="digi-price-table__integer-part">39</span><div class="digi-price-table__after-price"><span class="digi-price-table__fractional-part">99</span></div><span class="digi-price-table__period digi-price-table__plain">Al mes</span></div>' ) && str_ends_with( $html, '</div><div class="digi-ribbon"><div class="digi-ribbon__inner">Popular</div></div>' ) && str_contains( $html, '<a class="digi-price-table__button elementor-button elementor-size-md" href="#">' ), 'Tabla por defecto: precio completo para lectores, partes visibles ocultas a ellos, periodo debajo, botón enlazado y cinta: ' . $html );
	$sale = $render( $pt, array( 'sale' => 'yes', 'currency_symbol' => 'euro', 'currency_position' => 'after', 'price' => '1.234,5', 'currency_format' => ',', 'period_position' => 'beside', 'show_ribbon' => '', 'link' => array( 'url' => '' ), 'button_hover_animation' => 'grow', 'features_list' => array( array( '_id' => 'f1', 'item_text' => 'Uno', 'item_icon' => 'fa fa-check' ), array( '_id' => 'f2', 'item_text' => '', 'selected_item_icon' => array( 'value' => '' ) ) ) ) + $d );
	check_cards( str_contains( $sale, '<span class="elementor-screen-only">Antes 59€, ahora 1.234,5€ Al mes</span>' ) && str_contains( $sale, '<del class="digi-price-table__original-price digi-price-table__plain">59<span class="digi-price-table__currency">€</span></del><span class="digi-price-table__integer-part">1.234</span><div class="digi-price-table__after-price"><span class="digi-price-table__fractional-part">5</span><span class="digi-price-table__period digi-price-table__plain">Al mes</span></div><span class="digi-price-table__currency">€</span></div>' ), 'Oferta: precio original tachado, símbolo después, formato con coma y periodo al lado: ' . $sale );
	check_cards( str_contains( $sale, '<li class="elementor-repeater-item-f1"><div class="digi-price-table__feature-inner"><i class="fa fa-check" aria-hidden="true"></i> <span>Uno</span></div></li><li class="elementor-repeater-item-f2"><div class="digi-price-table__feature-inner">&nbsp;</div></li>' ) && str_contains( $sale, '<span class="digi-price-table__button elementor-button elementor-size-md elementor-animation-grow">' ) && ! str_contains( $sale, 'digi-ribbon' ), 'Icono FA4 heredado (con un espacio antes del texto, como el salto de línea de la plantilla de PRO), fila vacía sin espacio, botón sin enlace con la animación del núcleo y sin cinta.' );
	check_cards( str_contains( $render( $pt, array( 'features_list' => array( array( '_id' => 'f3', 'item_text' => '', 'item_icon' => 'fa fa-star' ) ) ) + $d ), '<i class="fa fa-star" aria-hidden="true"></i> &nbsp;</div>' ), 'Icono sin texto: el espacio y el &nbsp; de PRO.' );
	check_cards( str_contains( $render( $pt, array( 'currency_symbol' => 'custom', 'currency_symbol_custom' => '<b>MX$</b>', 'heading_tag' => 'h1' ) + $d ), '<span class="digi-price-table__currency">&lt;b&gt;MX$&lt;/b&gt;</span>' ) && str_contains( $render( $pt, array( 'heading_tag' => 'h1' ) + $d ), '<h3 class="digi-price-table__heading">' ), 'Símbolo personalizado escapado; etiqueta del título sólo h2–h6 (como PRO).' );
	$pt->settings = array( 'heading' => 'Plan', 'sub_heading' => '', 'price' => '9', 'currency_symbol' => 'dollar', 'period' => 'al mes', 'features_list' => array( array( 'item_text' => 'A' ) ), 'footer_additional_info' => '', 'button_text' => 'Comprar', 'link' => array( 'url' => '/c/' ) );
	check_cards( "## Plan\n\n**\$9 al mes**\n\n- A\n\n[**Comprar**](/c/)" === $pt->render_markdown(), 'Markdown de la tabla como en PRO: ' . $pt->render_markdown() );

	// 3. Conversión de los widgets de PRO con sus mapas.
	$lands = static function ( $key, $widget ) {
		$base = Translator::base( (string) $key );
		if ( isset( $widget->controls[ $base ] ) || Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER, 'icon' ), true ) ) {
			return true;
		}
		foreach ( array_keys( $widget->groups ) as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$row_lands = static function ( $key, array $fields ) {
		$base = Translator::base( (string) $key );
		return isset( $fields[ $base ] ) || in_array( $key, array( '_id', '__globals__', '__dynamic__', 'item_icon', '__fa4_migrated' ), true );
	};
	$cases = array(
		'call-to-action' => array(
			'defaults' => array(),
			'full'     => array(
				'skin' => 'cover', 'layout' => 'left', 'layout_mobile' => 'above', 'bg_image' => array( 'id' => 7, 'url' => 'https://sitio.test/7.jpg' ), 'bg_image_size' => 'custom', 'bg_image_custom_dimension' => array( 'width' => 800, 'height' => '' ),
				'graphic_element' => 'icon', 'selected_icon' => array( 'value' => 'fas fa-heart', 'library' => 'fa-solid' ), 'icon_view' => 'framed', 'icon_shape' => 'square', 'icon_size' => array( 'unit' => 'px', 'size' => 40 ), 'icon_border_width' => array( 'unit' => 'px', 'size' => 3 ),
				'title' => 'Oferta', 'title_tag' => 'h3', 'description' => 'Sólo hoy', 'button' => 'Comprar', 'link' => array( 'url' => 'https://a.test/', 'is_external' => 'on', 'nofollow' => '', 'custom_attributes' => '' ), 'link_click' => 'box',
				'ribbon_title' => 'Nuevo', 'ribbon_horizontal_position' => 'right', 'min-height' => array( 'unit' => 'px', 'size' => 400 ), 'min-height_mobile' => array( 'unit' => 'px', 'size' => 250 ), 'alignment' => 'start', 'vertical_position' => 'bottom', 'padding' => array( 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px', 'isLinked' => true ),
				'title_typography_typography' => 'custom', 'title_typography_font_size' => array( 'unit' => 'px', 'size' => 32 ), 'text_stroke_text_stroke_type' => 'yes', 'title_text_shadow_text_shadow_type' => 'yes', 'title_spacing' => array( 'unit' => 'px', 'size' => 10 ), 'description_spacing_tablet' => array( 'unit' => 'px', 'size' => 8 ),
				'content_bg_color' => '#ffffff', 'title_color' => '#111111', 'description_color' => '#222222', 'button_color' => '#333333', 'content_bg_color_hover' => '#eeeeee', 'title_color_hover' => '#ff0000', 'description_color_hover' => '#00ff00', 'button_color_hover' => '#0000ff',
				'button_size' => 'md', 'button_typography_typography' => 'custom', 'button_text_shadow_text_shadow_type' => 'yes', 'button_text_color' => '#ffffff', 'button_background_color' => '#000000', 'button_border_color' => '#000000', 'button_hover_text_color' => '#000000', 'button_hover_background_color' => '#ffffff', 'button_hover_border_color' => '#ffffff', 'button_border_width' => array( 'unit' => 'px', 'size' => 1 ), 'button_border_radius' => array( 'unit' => 'px', 'size' => 4 ), 'button_box_shadow_box_shadow_type' => 'yes', 'button_padding' => array( 'top' => '10', 'right' => '20', 'bottom' => '10', 'left' => '20', 'unit' => 'px', 'isLinked' => false ),
				'ribbon_bg_color' => '#cc0000', 'ribbon_text_color' => '#ffffff', 'ribbon_distance' => array( 'unit' => 'px', 'size' => 30 ), 'ribbon_typography_typography' => 'custom', 'box_shadow_box_shadow_type' => 'yes',
				'content_animation' => 'move-up', 'content_animation_duration' => array( 'size' => 600 ), 'sequenced_animation' => 'elementor-cta--sequenced-animation', 'transformation' => 'move-left', 'overlay_color' => 'rgba(0,0,0,.4)', 'bg_filters_css_filter' => 'custom', 'bg_filters_blur' => array( 'size' => 2 ), 'overlay_blend_mode' => 'multiply', 'overlay_color_hover' => 'rgba(0,0,0,.7)', 'bg_filters_hover_css_filter' => 'custom', 'effect_duration' => array( 'size' => 900 ),
				'custom_css' => "selector .elementor-cta:hover .elementor-cta__title{letter-spacing:1px}\nselector.elementor-cta--skin-cover .elementor-cta__content{gap:4px}\nselector .elementor-animated-item--move-up{opacity:.9}\nselector.elementor-bg-transform-move-left .elementor-bg{filter:none}\nselector .elementor-ribbon-inner{font-size:12px}",
				'_css_classes' => 'mi-cta',
			),
			'image'    => array( 'graphic_element' => 'image', 'graphic_image' => array( 'id' => 7, 'url' => 'https://sitio.test/7.jpg' ), 'graphic_image_size' => 'medium', 'graphic_image_spacing' => array( 'unit' => 'px', 'size' => 12 ), 'graphic_image_width' => array( 'unit' => '%', 'size' => 40 ), 'graphic_image_border_border' => 'solid', 'graphic_image_border_radius' => array( 'unit' => 'px', 'size' => 50 ), 'link' => array( 'url' => '/x/' ) ),
			'legacy'   => array( 'graphic_element' => 'icon', 'icon' => 'fa fa-star', 'icon_view' => 'stacked', 'icon_primary_color' => '#ff0000', 'icon_secondary_color' => '#ffffff', 'icon_padding' => array( 'unit' => 'px', 'size' => 10 ), 'icon_border_radius' => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ), 'icon_spacing' => array( 'unit' => 'px', 'size' => 6 ) ),
			'globals'  => array( 'title_color' => '', '__globals__' => array( 'title_color' => 'globals/colors?id=primary', 'ribbon_bg_color' => 'globals/colors?id=accent', 'title_typography_typography' => 'globals/typography?id=secondary', 'button_typography_typography' => 'globals/typography?id=accent', '_background_color' => 'globals/colors?id=text' ), 'ribbon_title' => 'Global' ),
			'dynamic'  => array( '__dynamic__' => array( 'title' => '[elementor-tag id="d1" name="post-title" settings="%7B%7D"]', 'description' => '[elementor-tag id="d2" name="post-excerpt" settings="%7B%7D"]', 'link' => '[elementor-tag id="d3" name="post-url" settings="%7B%7D"]', 'bg_image' => '[elementor-tag id="d4" name="post-featured-image" settings="%7B%7D"]' ) ),
		),
		'flip-box' => array(
			'defaults' => array(),
			'full'     => array(
				'graphic_element' => 'image', 'image' => array( 'id' => 7, 'url' => 'https://sitio.test/7.jpg' ), 'image_size' => 'medium', 'title_text_a' => 'Anverso', 'description_text_a' => 'Texto A', 'title_text_b' => 'Reverso', 'description_text_b' => 'Texto B', 'button_text' => 'Ir', 'link' => array( 'url' => 'https://a.test/', 'is_external' => '', 'nofollow' => 'on', 'custom_attributes' => '' ), 'link_click' => 'button',
				'title_tag' => 'h2', 'description_tag' => 'p', 'height' => array( 'unit' => 'px', 'size' => 320 ), 'height_mobile' => array( 'unit' => 'px', 'size' => 240 ), 'border_radius' => array( 'unit' => 'px', 'size' => 12 ), 'flip_effect' => 'flip', 'flip_direction' => 'right', 'flip_3d' => 'elementor-flip-box--3d',
				'background_a_background' => 'classic', 'background_a_color' => '#222222', 'background_a_image' => array( 'id' => 8, 'url' => 'https://sitio.test/8.jpg' ), 'background_overlay_a' => 'rgba(0,0,0,.5)', 'background_overlay_a_filters_css_filter' => 'custom', 'background_overlay_a_blend_mode' => 'multiply', 'padding_a' => array( 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px', 'isLinked' => true ), 'alignment_a' => 'start', 'vertical_position_a' => 'top', 'border_a_border' => 'solid', 'shadow_a_box_shadow_type' => 'yes',
				'image_spacing' => array( 'unit' => 'px', 'size' => 10 ), 'image_width' => array( 'unit' => '%', 'size' => 30 ), 'image_opacity' => array( 'size' => 0.8 ), 'image_border_border' => 'solid', 'image_border_radius' => array( 'unit' => 'px', 'size' => 50 ),
				'title_spacing_a' => array( 'unit' => 'px', 'size' => 8 ), 'title_color_a' => '#ffffff', 'title_typography_a_typography' => 'custom', 'text_stroke_text_stroke_type' => 'yes', 'description_color_a' => '#eeeeee', 'description_typography_a_typography' => 'custom',
				'background_b_background' => 'gradient', 'background_b_color' => '#000000', 'background_b_color_b' => '#333333', 'background_overlay_b' => '', 'padding_b' => array( 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px', 'isLinked' => true ), 'alignment_b' => 'end', 'vertical_position_b' => 'bottom', 'border_b_border' => 'dashed', 'shadow_b_box_shadow_type' => 'yes',
				'title_spacing_b' => array( 'unit' => 'px', 'size' => 6 ), 'title_color_b' => '#ffff00', 'title_typography_b_typography' => 'custom', 'text_stroke_b_text_stroke_type' => 'yes', 'description_spacing_b' => array( 'unit' => 'px', 'size' => 6 ), 'description_color_b' => '#dddddd', 'description_typography_b_typography' => 'custom',
				'button_size' => 'lg', 'button_typography_typography' => 'custom', 'button_text_color' => '#000000', 'button_background_background' => 'classic', 'button_background_color' => '#ffffff', 'button_border_color' => '#ffffff', 'button_hover_text_color' => '#ffffff', 'button_hover_background_background' => 'classic', 'button_hover_background_color' => '#000000', 'button_hover_border_color' => '#000000', 'button_hover_transition_duration' => array( 'unit' => 'ms', 'size' => 300 ), 'button_border_width' => array( 'unit' => 'px', 'size' => 2 ), 'button_border_radius' => array( 'unit' => 'px', 'size' => 20 ),
				'custom_css' => "selector .elementor-flip-box__front .elementor-flip-box__layer__title{letter-spacing:1px}\nselector.elementor-flip-box--3d .elementor-flip-box__layer__inner{transform:none}\nselector .elementor-flip-box__button:hover{color:red}",
			),
			'icon'     => array( 'graphic_element' => 'icon', 'icon' => 'fa fa-star', 'icon_view' => 'framed', 'icon_shape' => 'square', 'icon_spacing' => array( 'unit' => 'px', 'size' => 10 ), 'icon_primary_color' => '#ff0000', 'icon_secondary_color' => '#ffffff', 'icon_size' => array( 'unit' => 'px', 'size' => 30 ), 'icon_padding' => array( 'unit' => 'px', 'size' => 5 ), 'icon_rotate' => array( 'unit' => 'deg', 'size' => 45 ), 'icon_border_width' => array( 'unit' => 'px', 'size' => 2 ), 'icon_border_radius' => array( 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px', 'isLinked' => true ), 'flip_effect' => 'zoom-out' ),
			'globals'  => array( '__globals__' => array( 'title_color_a' => 'globals/colors?id=primary', 'button_background_color' => 'globals/colors?id=accent', 'title_typography_b_typography' => 'globals/typography?id=primary' ) ),
			'dynamic'  => array( '__dynamic__' => array( 'title_text_a' => '[elementor-tag id="d1" name="post-title" settings="%7B%7D"]', 'link' => '[elementor-tag id="d2" name="post-url" settings="%7B%7D"]' ) ),
		),
		'price-list' => array(
			'defaults' => array(),
			'full'     => array(
				'price_list' => array(
					array( '_id' => 'r1', 'title' => 'Café', 'price' => '$2', 'item_description' => 'Recién hecho', 'image' => array( 'id' => 7, 'url' => 'https://sitio.test/7.jpg' ), 'link' => array( 'url' => '/cafe/', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ) ),
					array( '_id' => 'r2', 'title' => 'Té', 'price' => '$1', '__dynamic__' => array( 'price' => '[elementor-tag id="d1" name="acf-text" settings="%7B%7D"]' ) ),
				),
				'title_tag' => 'h4', 'description_tag' => 'div', 'image_size_size' => 'medium', 'vertical_align' => 'center', 'row_gap' => array( 'unit' => 'px', 'size' => 30 ), 'row_gap_mobile' => array( 'unit' => 'px', 'size' => 15 ), 'image_spacing' => array( 'unit' => 'px', 'size' => 10 ), 'content_spacing' => array( 'unit' => 'px', 'size' => 4 ),
				'heading_color' => '#111111', 'heading_typography_typography' => 'custom', 'text_stroke_text_stroke_type' => 'yes', 'price_color' => '#222222', 'price_typography_typography' => 'custom', 'description_color' => '#333333', 'description_typography_typography' => 'custom',
				'separator_style' => 'dashed', 'separator_weight' => array( 'unit' => 'px', 'size' => 1 ), 'separator_color' => '#cccccc', 'separator_spacing' => array( 'unit' => 'px', 'size' => 5 ), 'border_radius' => array( 'top' => '50', 'right' => '50', 'bottom' => '50', 'left' => '50', 'unit' => '%', 'isLinked' => true ),
				'custom_css' => "selector .elementor-price-list-item .elementor-price-list-title{font-style:italic}\nselector .elementor-price-list li{padding:2px}",
			),
			'globals'  => array( '__globals__' => array( 'heading_color' => 'globals/colors?id=secondary', 'price_typography_typography' => 'globals/typography?id=accent' ), 'price_list' => array( array( '_id' => 'g1', 'title' => 'Global', 'price' => '$3' ) ) ),
		),
		'price-table' => array(
			'defaults' => array(),
			'full'     => array(
				'heading' => 'Pro', 'sub_heading' => 'Para equipos', 'heading_tag' => 'h2', 'currency_symbol' => 'custom', 'currency_symbol_custom' => 'MX$', 'price' => '199,90', 'currency_format' => ',', 'sale' => 'yes', 'original_price' => '249', 'period' => 'al mes',
				'features_list' => array( array( '_id' => 'f1', 'item_text' => 'Soporte', 'selected_item_icon' => array( 'value' => 'fas fa-check', 'library' => 'fa-solid' ), 'item_icon_color' => '#00aa00' ), array( '_id' => 'f2', 'item_text' => 'Antiguo', 'item_icon' => 'fa fa-star' ) ),
				'button_text' => 'Comprar', 'link' => array( 'url' => 'https://a.test/comprar', 'is_external' => 'on', 'nofollow' => '', 'custom_attributes' => '' ), 'footer_additional_info' => 'Sin permanencia', 'show_ribbon' => 'yes', 'ribbon_title' => 'Mejor', 'ribbon_horizontal_position' => 'left',
				'header_bg_color' => '#123456', 'header_padding' => array( 'top' => '10', 'right' => '0', 'bottom' => '10', 'left' => '0', 'unit' => 'px', 'isLinked' => false ), 'heading_color' => '#ffffff', 'heading_typography_typography' => 'custom', 'sub_heading_color' => '#eeeeee', 'sub_heading_typography_typography' => 'custom',
				'pricing_element_bg_color' => '#fafafa', 'pricing_element_padding' => array( 'top' => '20', 'right' => '0', 'bottom' => '20', 'left' => '0', 'unit' => 'px', 'isLinked' => false ), 'price_color' => '#111111', 'price_typography_typography' => 'custom', 'currency_size' => array( 'size' => 40 ), 'currency_position' => 'after', 'currency_vertical_position' => 'middle', 'fractional-part_size' => array( 'size' => 30 ), 'fractional_part_vertical_position' => 'bottom',
				'original_price_color' => '#999999', 'original_price_typography_typography' => 'custom', 'original_price_vertical_position' => 'top', 'period_color' => '#777777', 'period_typography_typography' => 'custom', 'period_position' => 'beside',
				'features_list_bg_color' => '#ffffff', 'features_list_padding' => array( 'top' => '10', 'right' => '0', 'bottom' => '10', 'left' => '0', 'unit' => 'px', 'isLinked' => false ), 'features_list_color' => '#333333', 'features_list_typography_typography' => 'custom', 'features_list_alignment' => 'start', 'item_width' => array( 'unit' => '%', 'size' => 80 ), 'list_divider' => 'yes', 'divider_style' => 'dashed', 'divider_color' => '#cccccc', 'divider_weight' => array( 'unit' => 'px', 'size' => 1 ), 'divider_width' => array( 'unit' => '%', 'size' => 50 ), 'divider_gap' => array( 'unit' => 'px', 'size' => 10 ),
				'footer_bg_color' => '#f0f0f0', 'footer_padding' => array( 'top' => '20', 'right' => '0', 'bottom' => '20', 'left' => '0', 'unit' => 'px', 'isLinked' => false ), 'button_size' => 'lg', 'button_text_color' => '#ffffff', 'button_typography_typography' => 'custom', 'button_background_background' => 'classic', 'button_background_color' => '#000000', 'button_border_border' => 'solid', 'button_border_radius' => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ), 'button_text_padding' => array( 'top' => '10', 'right' => '30', 'bottom' => '10', 'left' => '30', 'unit' => 'px', 'isLinked' => false ),
				'button_hover_color' => '#000000', 'button_background_hover_background' => 'classic', 'button_background_hover_color' => '#ffffff', 'button_hover_border_color' => '#000000', 'button_hover_animation' => 'grow', 'additional_info_color' => '#666666', 'additional_info_typography_typography' => 'custom', 'additional_info_margin' => array( 'top' => '10', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ),
				'ribbon_bg_color' => '#ff0000', 'ribbon_distance' => array( 'unit' => 'px', 'size' => 25 ), 'ribbon_text_color' => '#ffffff', 'ribbon_typography_typography' => 'custom', 'box_shadow_box_shadow_type' => 'yes',
				'custom_css' => "selector{--e-price-table-header-background-color:#000}\nselector .elementor-price-table__price .elementor-typo-excluded{font-size:12px}\nselector .elementor-ribbon-inner{letter-spacing:1px}",
			),
			'globals'  => array( '__globals__' => array( 'header_bg_color' => 'globals/colors?id=primary', 'button_background_color' => 'globals/colors?id=secondary', 'features_list_typography_typography' => 'globals/typography?id=text' ), 'features_list' => array( array( '_id' => 'g1', 'item_text' => 'Global', '__globals__' => array( 'item_icon_color' => 'globals/colors?id=accent' ) ) ) ),
			'dynamic'  => array( '__dynamic__' => array( 'price' => '[elementor-tag id="d1" name="woocommerce-product-price-tag" settings="%7B%7D"]', 'heading' => '[elementor-tag id="d2" name="post-title" settings="%7B%7D"]' ) ),
		),
	);
	$converted_all = array();
	foreach ( $cases as $type => $list ) {
		$widget = $own[ $type ];
		$tree   = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
		foreach ( $list as $id => $settings ) {
			$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
		}
		Registry::reset();
		$converted = Converter::tree( $tree, $log, true );
		check_cards( count( $list ) === count( $log ), "$type: se convierte cada widget del árbol." );
		$codes = array();
		foreach ( $log as $entry ) {
			foreach ( $entry['codes'] as $code ) {
				$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
			}
		}
		$css_cases = array_keys( array_filter( $list, static function ( $s ) { return isset( $s['custom_css'] ); } ) );
		check_cards( array_map( static function ( $id ) { return $id . ':pro.css:custom_css'; }, $css_cases ) === $codes, "$type: ningún pro.unmapped, pro.dropped ni pro.approx; sólo el CSS convertido: " . implode( ', ', $codes ) );
		foreach ( $converted[0]['elements'] as $element ) {
			check_cards( 'digitalisimo-' . $type === $element['widgetType'] && $type === $element['settings'][ Translator::MARKER ], "$type/{$element['id']}: pasa al widget propio con el mismo id." );
			foreach ( $element['settings'] as $key => $value ) {
				if ( isset( $widget->controls[ $key ] ) && 'repeater' === $widget->controls[ $key ]['type'] ) {
					foreach ( (array) $value as $row ) {
						foreach ( array_merge( array_keys( $row ), array_keys( $row['__globals__'] ?? array() ), array_keys( $row['__dynamic__'] ?? array() ) ) as $field ) {
							check_cards( $row_lands( $field, $widget->controls[ $key ]['fields'] ), "$type/{$element['id']}: $key.$field no cae en un campo propio." );
						}
					}
					continue;
				}
				check_cards( $lands( $key, $widget ), "$type/{$element['id']}: la clave $key no cae en un control propio." );
			}
			foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
				foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
					check_cards( $lands( $key, $widget ), "$type/{$element['id']}: $bag.$key no cae en un control propio." );
				}
			}
			$converted_all[ $type ][ $element['id'] ] = $element['settings'];
		}
		check_cards( $converted === Converter::tree( $converted, $again, true ) && array() === $again, "$type: lo convertido no se convierte otra vez." );
	}
	$c = $converted_all['call-to-action'];
	check_cards( 'This is the heading' === $c['defaults']['title'] && 'Click Here' === $c['defaults']['button'], 'CTA sin cambios: llega con los textos por defecto de PRO.' );
	check_cards( str_contains( $render( $cta, $c['defaults'] + $defaults( $cta ) ), '>This is the heading</h2>' ), 'Lo convertido se pinta con los textos de PRO.' );
	$f = $c['full'];
	check_cards( 'digi-cta--sequenced-animation' === $f['sequenced_animation'] && 'above' === $f['layout_mobile'] && 250 === $f['min-height_mobile']['size'] && 'custom' === $f['bg_image_size'] && 800 === $f['bg_image_custom_dimension']['width'] && 'custom' === $f['bg_filters_css_filter'] && 'mi-cta' === $f['_css_classes'], 'Valor de la animación en secuencia traducido; dispositivos, grupos de imagen y filtros, y Avanzado.' );
	check_cards( "selector .digi-cta:hover .digi-cta__title{letter-spacing:1px}\nselector.digi-cta--skin-cover .digi-cta__content{gap:4px}\nselector .digi-cta__item--move-up{opacity:.9}\nselector.digi-cta--bg-move-left .digi-cta__bg{filter:none}\nselector .digi-ribbon__inner{font-size:12px}" === $f['custom_css'], 'CSS personalizado con las clases propias: ' . $f['custom_css'] );
	$full_html = $render( $cta, $f + $defaults( $cta ) );
	check_cards( 0 === strpos( $full_html, '<a class="digi-cta" style="--digi-cta-b0:scale(1.2) translateX(8%);--digi-cta-b1:scale(1.2) translateX(-8%)" href="https://a.test/" target="_blank">' ) && str_contains( $full_html, 'elementor-view-framed elementor-shape-square digi-cta__content-item digi-cta__item--move-up" style="--digi-cta-a1:translateY(-30px)"' ) && str_contains( $full_html, '<div class="digi-ribbon digi-ribbon--right"><div class="digi-ribbon__inner">Nuevo</div></div></a>' ), 'Lo convertido se pinta con su enlace en la caja, sus efectos y su cinta.' );
	check_cards( str_contains( $render( $cta, $c['legacy'] + $defaults( $cta ) ), '<div class="elementor-icon"><i class="fa fa-star" aria-hidden="true"></i></div>' ), 'El icono FA4 heredado se pinta igual.' );
	check_cards( array( 'title_color' => 'globals/colors?id=primary', 'ribbon_bg_color' => 'globals/colors?id=accent', 'title_typography_typography' => 'globals/typography?id=secondary', 'button_typography_typography' => 'globals/typography?id=accent', '_background_color' => 'globals/colors?id=text' ) === $c['globals']['__globals__'] && array( 'title', 'description', 'link', 'bg_image' ) === array_keys( $c['dynamic']['__dynamic__'] ), 'Globales y dinámicos siguen a su control.' );
	$c = $converted_all['flip-box'];
	check_cards( 'This is the heading' === $c['defaults']['title_text_a'] && 'Click Here' === $c['defaults']['button_text'] && 'digi-flip-box--3d' === $c['full']['flip_3d'] && 240 === $c['full']['height_mobile']['size'] && '#333333' === $c['full']['background_b_color_b'] && 8 === $c['full']['background_a_image']['id'], 'Tarjeta: textos de PRO, profundidad 3D traducida, dispositivos y fondos.' );
	check_cards( "selector .digi-flip-box__front .digi-flip-box__title{letter-spacing:1px}\nselector.digi-flip-box--3d .digi-flip-box__inner{transform:none}\nselector .digi-flip-box__button:hover{color:red}" === $c['full']['custom_css'], 'CSS de la tarjeta con las clases propias: ' . $c['full']['custom_css'] );
	check_cards( str_contains( $render( $fb, $c['full'] + $defaults( $fb ) ), '<a class="digi-flip-box__button elementor-button elementor-size-lg" href="https://a.test/" rel="nofollow">Ir</a>' ) && str_contains( $render( $fb, $c['icon'] + $defaults( $fb ) ), '<i class="fa fa-star" aria-hidden="true"></i>' ), 'Lo convertido se pinta con su botón enlazado y el icono FA4 heredado.' );
	$c = $converted_all['price-list'];
	check_cards( 3 === count( $c['defaults']['price_list'] ) && 'First item on the list' === $c['defaults']['price_list'][0]['title'] && '$32' === $c['defaults']['price_list'][2]['price'] && '#' === $c['defaults']['price_list'][1]['link']['url'], 'Lista sin repetidor guardado: las tres filas de PRO.' );
	check_cards( 'r1' === $c['full']['price_list'][0]['_id'] && 7 === $c['full']['price_list'][0]['image']['id'] && '#' === $c['full']['price_list'][1]['link']['url'] && isset( $c['full']['price_list'][1]['__dynamic__']['price'] ) && 15 === $c['full']['row_gap_mobile']['size'] && "selector .digi-price-list__item .digi-price-list__title{font-style:italic}\nselector .digi-price-list li{padding:2px}" === $c['full']['custom_css'], 'Filas con su id, imagen, enlace por defecto y dinámicos; dispositivos y CSS personalizado.' );
	check_cards( 3 === substr_count( $render( $pl, $c['defaults'] + $defaults( $pl ) ), '<a class="digi-price-list__item" href="#">' ), 'Lo convertido se pinta con las filas enlazadas de PRO.' );
	$c = $converted_all['price-table'];
	check_cards( 'Enter your title' === $c['defaults']['heading'] && 'Monthly' === $c['defaults']['period'] && 'Popular' === $c['defaults']['ribbon_title'] && 'List Item #2' === $c['defaults']['features_list'][1]['item_text'], 'Tabla sin cambios: textos y filas de PRO.' );
	check_cards( 'fa fa-star' === $c['full']['features_list'][1]['item_icon'] && '#00aa00' === $c['full']['features_list'][0]['item_icon_color'] && 30 === $c['full']['fractional-part_size']['size'] && 'grow' === $c['full']['button_hover_animation'] && "selector{--digi-pt-header-bg:#000}\nselector .digi-price-table__price .digi-price-table__plain{font-size:12px}\nselector .digi-ribbon__inner{letter-spacing:1px}" === $c['full']['custom_css'], 'Icono FA4 de la fila, nombre con guion, animación y CSS (también las variables) con lo propio: ' . $c['full']['custom_css'] );
	$pt_html = $render( $pt, $c['full'] + $defaults( $pt ) );
	check_cards( str_contains( $pt_html, '<span class="elementor-screen-only">Antes 249MX$, ahora 199,90MX$ al mes</span>' ) && str_contains( $pt_html, '<i class="fa fa-star" aria-hidden="true"></i> <span>Antiguo</span>' ) && str_contains( $pt_html, '<div class="digi-ribbon digi-ribbon--left"><div class="digi-ribbon__inner">Mejor</div></div>' ), 'Lo convertido se pinta con su precio, sus características y su cinta: ' . $pt_html );
	check_cards( array( 'item_icon_color' => 'globals/colors?id=accent' ) === $c['globals']['features_list'][0]['__globals__'] && array( 'price', 'heading' ) === array_keys( $c['dynamic']['__dynamic__'] ), 'Globales de las filas y dinámicos siguen a su control.' );

	echo 'DIGITALÍSIMO Elements: tarjetas de C2 (Llamada a la acción con imagen, Tarjeta giratoria, Lista de precios y Tabla de precios: ' . $total['controls'] . ' controles y ' . $total['groups'] . ' grupos; hojas de ' . implode( ', ', array_map( static function ( $t, $b ) { return "$t $b B"; }, array_keys( $total['css'] ), $total['css'] ) ) . ') y sus mapas (' . array_sum( array_map( 'count', $cases ) ) . " casos de conversión) validados.\n";
}
