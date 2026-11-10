<?php
/**
 * Panel fuera de lienzo (`digitalisimo-nested-off-canvas`), el widget propio que sustituye al «Off-Canvas» anidado de PRO
 * (C3 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c3.md, frente F), y su mapa
 * `modules/digitalisimo-convert/maps/off-canvas.php`:
 * - registro y recursos (hoja ≤ 4 KB y script ≤ 6 KB sin jQuery, script del editor; sin `--e-global-*`, `url()` ni clases de
 *   PRO; sin nombres de otros proveedores);
 * - controles: se cargan a la vez el widget de PRO y el propio, y cada control de PRO tiene uno propio con el mismo nombre,
 *   tipo, valor por defecto (salvo los textos, que se traducen), opciones, condiciones, dispositivos y sección; los grupos,
 *   con el mismo tipo, `types`, `exclude`, `fields_options` de valores y condición;
 * - cobertura del mapa: cada control de PRO está declarado una vez;
 * - marcado del servidor: diálogo cerrado con el `id` de PRO (también con el filtro de los bucles), el hijo dentro del
 *   contenido, la etiqueta validada, el nombre escapado y la configuración del script; la plantilla del editor;
 * - conversión con `Converter::tree()` de paneles de PRO reales (por defecto, posiciones, animaciones por dispositivo,
 *   estilos, `__dynamic__` y `__globals__`, CSS personalizado) con su contenedor hijo intacto y el enlace que lo abre sin
 *   tocar: ningún `pro.unmapped`, cada clave cae en un control propio y lo convertido se pinta.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Plugin { public static $instance; public $experiments; }
	class Controls_Manager {
		const TEXT = 'text'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const HEADING = 'heading';
		const SLIDER = 'slider'; const DIMENSIONS = 'dimensions'; const ALERT = 'alert'; const ANIMATION = 'animation';
		const EXIT_ANIMATION = 'exit_animation'; const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Utils {
		public static function validate_html_tag( $tag ) {
			return in_array( strtolower( (string) $tag ), array( 'a', 'article', 'aside', 'div', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'main', 'nav', 'p', 'section', 'span' ), true ) ? strtolower( (string) $tag ) : 'div';
		}
		public static function print_validated_html_tag( $tag ) { echo self::validate_html_tag( $tag ); }
	}
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $order    = array();
		public $children = array();
		public $id       = 'abc1234';
		public $attrs    = array();
		protected $section = '';
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_children() { return $this->children; }
		public function get_id() { return $this->id; }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; $this->section = $name; }
		public function end_controls_section() { $this->section = ''; }
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args + array( '_section' => $this->section ); $this->order[] = $name; }
		public function add_responsive_control( $name, $args ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type, '_section' => $this->section ) + $args; $this->order[] = '@' . $args['name']; }
		public function add_render_attribute( $key, $attrs ) { $this->attrs[ $key ] = $attrs; }
		public function print_render_attribute_string( $key ) {
			foreach ( $this->attrs[ $key ] ?? array() as $name => $value ) {
				echo ' ' . $name . '="' . htmlspecialchars( (string) $value, ENT_QUOTES ) . '"';
			}
		}
		protected function get_initial_config() { return array(); }
	}
}
namespace Elementor\Modules\NestedElements\Base {
	abstract class Widget_Nested_Base extends \Elementor\Widget_Base {
		abstract protected function get_default_children_elements();
		abstract protected function get_default_repeater_title_setting_key();
		protected function get_default_children_placeholder_selector() { return ''; }
		protected function get_initial_config() {
			return array( 'defaults' => array( 'elements' => $this->get_default_children_elements(), 'elements_placeholder_selector' => $this->get_default_children_placeholder_selector(), 'repeater_title_setting' => $this->get_default_repeater_title_setting_key() ), 'support_nesting' => true );
		}
	}
}
namespace ElementorPro\Base {
	trait Base_Widget_Trait {}
	abstract class Widget_Nested_Base_Pro extends \Elementor\Modules\NestedElements\Base\Widget_Nested_Base {}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['oc'] = array( 'filters' => array(), 'features' => array( 'container' => true, 'nested-elements' => true ) );
	function __( $t, $d = '' ) { return $t; }
	function esc_html__( $t, $d = '' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_js( $v ) { return addslashes( (string) $v ); }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function add_filter( $hook, $cb ) { $GLOBALS['oc']['filters'][ $hook ][] = $cb; }
	function remove_all_filters( $hook ) { unset( $GLOBALS['oc']['filters'][ $hook ] ); }
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['oc']['filters'][ $hook ] ?? array() as $cb ) {
			$value = $cb( $value, ...$args );
		}
		return $value;
	}
	function check_oc( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	/** Contenedor hijo de prueba. */
	class OC_Child {
		public $html;
		public function __construct( $html ) { $this->html = $html; }
		public function print_element() { echo '<div class="e-con">' . $this->html . '</div>'; }
	}

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/digitalisimo-navigation/class-module.php';
	\Digitalisimo\Elements\Navigation\Module::init();
	require $root . 'modules/digitalisimo-navigation/widgets/class-nested-off-canvas.php';
	require $root . 'modules/off-canvas/widgets/off-canvas.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return ! empty( $GLOBALS['oc']['features'][ $f ] ); } };

	use Digitalisimo\Elements\Navigation\Module;
	use Digitalisimo\Elements\Navigation\Widgets\Nested_Off_Canvas;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$own = new Nested_Off_Canvas();
	( digi_test_accessible( new ReflectionMethod( $own, 'register_controls' ) ) )->invoke( $own );
	$pro = new \ElementorPro\Modules\OffCanvas\Widgets\Off_Canvas();
	( digi_test_accessible( new ReflectionMethod( $pro, 'register_controls' ) ) )->invoke( $pro );

	$defaults = static function ( $widget ) {
		$out = array();
		foreach ( $widget->controls as $name => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$out[ $name ] = $control['default'];
			}
		}
		return $out;
	};
	$render = static function ( array $settings, ?array $kids = null, string $id = 'abc1234' ) use ( $own, $defaults ) {
		$widget           = new Nested_Off_Canvas();
		$widget->controls = $own->controls;
		$widget->settings = $settings + $defaults( $own );
		$widget->children = null === $kids ? array( new OC_Child( '<a href="/contacto/">Contacto</a>' ) ) : $kids;
		$widget->id       = $id;
		ob_start();
		( digi_test_accessible( new ReflectionMethod( $widget, 'render' ) ) )->invoke( $widget );
		return (string) ob_get_clean();
	};

	// 1. Registro y recursos.
	$name = 'digitalisimo-nested-off-canvas';
	check_oc( $name === $own->get_name() && 'Panel fuera de lienzo' === $own->get_title() && array( 'digitalisimo' ) === $own->get_categories() && 'eicon-off-canvas' === $own->get_icon(), 'Nombre, título, icono y categoría propios.' );
	check_oc( isset( Module::WIDGETS[ $name ] ) && Nested_Off_Canvas::class === Module::WIDGETS[ $name ]['class'] && in_array( $name, Module::NESTED, true ) && 'Panel fuera de lienzo' === Module::TITLES[ $name ], 'El módulo de navegación registra el widget como anidado, con su título.' );
	check_oc( array( $name ) === $own->get_style_depends() && array( $name ) === $own->get_script_depends(), 'Su hoja y su script, por el módulo.' );
	check_oc( true === $own->show_in_panel() && true === $own->has_widget_inner_wrapper(), 'Con contenedores, en el panel; sin el marcado optimizado, con envoltorio interior.' );
	$GLOBALS['oc']['features'] = array( 'e_optimized_markup' => true );
	check_oc( false === $own->show_in_panel() && false === $own->has_widget_inner_wrapper(), 'Sin contenedores, fuera del panel (como PRO); con el marcado optimizado, sin envoltorio.' );
	$GLOBALS['oc']['features'] = array( 'container' => true, 'nested-elements' => true );
	$dir    = $root . 'modules/digitalisimo-navigation/';
	$css    = (string) file_get_contents( $dir . 'assets/css/nested-off-canvas.css' );
	$js     = (string) file_get_contents( $dir . 'assets/js/nested-off-canvas.js' );
	$editor = (string) file_get_contents( $dir . 'assets/js/nested-off-canvas-editor.js' );
	check_oc( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_oc( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! preg_match( '/e-off-canvas|--e-/', $css ) && str_contains( $css, 'prefers-reduced-motion' ), 'La hoja no usa el kit, url() ni clases o variables de PRO, y respeta el movimiento reducido.' );
	check_oc( strlen( $js ) <= 6144 && ! preg_match( '/jQuery|\$\(|swiper|elementorProFrontend|ElementorProFrontendConfig/', $js ), 'Script de ' . strlen( $js ) . ' B (tope 6 144) sin jQuery ni los globales de PRO.' );
	foreach ( array( "'off_canvas:'", 'elementor-pro/off-canvas/toggle-display-mode', 'runReadyTrigger', "'Escape'", "'Tab'", 'aria-expanded', 'prefers-reduced-motion', 'digi-noc-lock' ) as $piece ) {
		check_oc( str_contains( $js, $piece ), 'El script no tiene: ' . $piece );
	}
	check_oc( strlen( $editor ) <= 6144 && ! preg_match( '/jQuery|\$\(/', $editor ) && str_contains( $editor, "'digitalisimo-nested-off-canvas'" ) && str_contains( $editor, 'section_layout:activated' ) && str_contains( $editor, 'change:editing_mode' ) && str_contains( $editor, ':off-canvas:settings:activated' ), 'Script del editor sin jQuery: tipo anidado, abrir al editar, «Modo de edición» y lista de la etiqueta dinámica.' );
	$source = (string) file_get_contents( $dir . 'widgets/class-nested-off-canvas.php' );
	$map    = require $root . 'modules/digitalisimo-convert/maps/off-canvas.php';
	foreach ( array( $source, $css, $js, $editor, (string) file_get_contents( $root . 'modules/digitalisimo-convert/maps/off-canvas.php' ) ) as $text ) {
		check_oc( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $text ), 'Sin nombres de otros proveedores (guarda de build-packages.sh).' );
	}
	$config = ( digi_test_accessible( new ReflectionMethod( $own, 'get_initial_config' ) ) )->invoke( $own );
	check_oc( '.digi-noc__content' === $config['defaults']['elements_placeholder_selector'] && array( array( 'elType' => 'container', 'settings' => array( 'content_width' => 'full' ) ) ) === $config['defaults']['elements'] && '' === $config['defaults']['repeater_title_setting'], 'Un contenedor hijo de ancho completo, dentro del contenido; sin repetidor.' );

	// 2. Un control propio por cada control de PRO, igual salvo los textos.
	$same = static function ( $key, $a, $b ) { return ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ); };
	check_oc( $pro->order === $own->order, 'Los mismos controles y grupos en el mismo orden. Sobran: ' . implode( ', ', array_diff( $own->order, $pro->order ) ) . ' · faltan: ' . implode( ', ', array_diff( $pro->order, $own->order ) ) );
	foreach ( $pro->controls as $cname => $p ) {
		$o = $own->controls[ $cname ];
		foreach ( array( 'type', 'condition', 'conditions', 'responsive', 'tab', '_section', 'size_units', 'separator', 'frontend_available', 'render_type', 'editor_available', 'return_value', 'alert_type', 'label_block' ) as $key ) {
			check_oc( $same( $key, $p, $o ), "$cname: «{$key}» distinto de PRO (" . json_encode( $p[ $key ] ?? null ) . ' / ' . json_encode( $o[ $key ] ?? null ) . ').' );
		}
		if ( 'text' !== $p['type'] ) {
			check_oc( $same( 'default', $p, $o ), "$cname: el valor por defecto no es el de PRO." );
		}
		if ( isset( $p['options'] ) ) {
			check_oc( array_keys( $p['options'] ) === array_keys( $o['options'] ), "$cname: las opciones no son las de PRO." );
		}
		if ( isset( $p['range'] ) ) {
			check_oc( $p['range'] == $o['range'], "$cname: el rango no es el de PRO." );
		}
		if ( isset( $p['selectors_dictionary'] ) ) {
			check_oc( array_keys( $p['selectors_dictionary'] ) === array_keys( $o['selectors_dictionary'] ), "$cname: el diccionario de selectores no tiene los valores de PRO." );
		}
		check_oc( isset( $p['selectors'] ) === isset( $o['selectors'] ), "$cname: con selectores en uno y sin ellos en el otro." );
		if ( ! empty( $o['selectors'] ) ) {
			check_oc( ! preg_match( '/e-off-canvas|--e-/', implode( ' ', array_keys( $o['selectors'] ) ) . implode( ' ', $o['selectors'] ) . implode( ' ', (array) ( $o['selectors_dictionary'] ?? array() ) ) ), "$cname: selector o variable de PRO." );
		}
	}
	foreach ( $pro->groups as $gname => $p ) {
		$o = $own->groups[ $gname ] ?? null;
		check_oc( null !== $o, "$gname: sin grupo propio." );
		foreach ( array( 'type', 'types', 'exclude', 'condition', '_section' ) as $key ) {
			check_oc( $same( $key, $p, $o ), "Grupo $gname: «{$key}» distinto de PRO." );
		}
		foreach ( (array) ( $p['fields_options'] ?? array() ) as $field => $opts ) {
			check_oc( ( $opts['default'] ?? null ) === ( $o['fields_options'][ $field ]['default'] ?? null ), "Grupo $gname: valor por defecto de $field distinto de PRO." );
		}
		check_oc( ! preg_match( '/e-off-canvas/', (string) $o['selector'] ) && str_contains( (string) $o['selector'], '.digi-noc__' ), "Grupo $gname: selector propio." );
	}
	foreach ( $pro->sections as $sname => $p ) {
		check_oc( isset( $own->sections[ $sname ] ) && $same( 'tab', $p, $own->sections[ $sname ] ) && $same( 'condition', $p, $own->sections[ $sname ] ), "Sección $sname: falta o cambia de pestaña o condición." );
	}
	check_oc( 'Panel fuera de lienzo' === $own->controls['off_canvas_name']['default'] && 'Off-Canvas' === $pro->controls['off_canvas_name']['default'], 'El nombre por defecto se traduce (el de PRO lo pone el mapa en lo convertido).' );
	check_oc( 'rgba(0,0,0,.8)' === $own->groups['overlay_background']['fields_options']['color']['default'] && 'classic' === $own->groups['overlay_background']['fields_options']['background']['default'], 'Capa: negro al 80 % por defecto, como PRO.' );

	// 3. Cobertura del mapa.
	check_oc( 'off-canvas' === $map['source'] && $name === $map['target'] && 'hide' === $map['panel'] && in_array( $map['status'], array( 'draft', 'ready' ), true ), 'Origen, destino, estado y panel del mapa.' );
	$declared = array_merge( $map['keep'], array_keys( $map['rename'] ?? array() ), array_keys( $map['groups'] ), $map['ui'], array_keys( $map['drop'] ?? array() ) );
	foreach ( array_keys( $pro->controls ) as $cname ) {
		check_oc( in_array( $cname, $declared, true ), "$cname: el mapa no lo declara." );
		if ( in_array( $cname, $map['ui'], true ) ) {
			check_oc( in_array( $own->controls[ $cname ]['type'], array( 'heading', 'alert' ), true ), "$cname: aviso o encabezado del panel." );
		}
	}
	foreach ( array_keys( $pro->groups ) as $gname ) {
		check_oc( ( $map['groups'][ $gname ] ?? '' ) === $gname, "$gname: grupo sin declarar." );
	}
	check_oc( count( $declared ) === count( array_unique( $declared ) ) && count( $declared ) === count( $pro->controls ) + count( $pro->groups ), 'El mapa declara cada control de PRO una sola vez (' . count( $declared ) . ' / ' . ( count( $pro->controls ) + count( $pro->groups ) ) . ').' );

	// 4. Marcado del servidor.
	$html = $render( array() );
	$cfg  = htmlspecialchars( json_encode( array( 'in' => (object) array(), 'out' => (object) array(), 'o' => true, 'e' => true, 's' => false ) ), ENT_QUOTES );
	check_oc( '<div id="off-canvas-abc1234" class="digi-noc" role="dialog" aria-modal="true" aria-label="Panel fuera de lienzo" aria-hidden="true" inert data-delay-child-handlers="true" data-digi-noc="' . $cfg . '"><div class="digi-noc__overlay"></div><div class="digi-noc__main"><div class="digi-noc__content"><div class="e-con"><a href="/contacto/">Contacto</a></div></div></div></div>' === $html, 'Diálogo cerrado con el id de PRO y el hijo dentro del contenido: ' . $html );
	$html = $render( array( 'wrapper_html_tag' => 'aside', 'off_canvas_name' => 'Menú "móvil" <b>', 'entrance_animation' => 'slideInLeft', 'entrance_animation_mobile' => 'fadeIn', 'exit_animation' => 'slideInLeft', 'exit_animation_tablet' => 'bad value!', 'is_not_close_on_overlay' => 'yes', 'is_not_close_on_esc_overlay' => 'yes', 'prevent_scroll' => 'yes' ) );
	check_oc( 0 === strpos( $html, '<aside id="off-canvas-abc1234" class="digi-noc" role="dialog" aria-modal="true" aria-label="Menú &quot;móvil&quot; &lt;b&gt;"' ) && str_ends_with( $html, '</div></div></aside>' ), 'Etiqueta elegida y nombre escapado: ' . $html );
	preg_match( '/data-digi-noc="([^"]*)"/', $html, $m );
	$data = json_decode( html_entity_decode( $m[1] ?? '', ENT_QUOTES ), true );
	check_oc( array( 'in' => array( '' => 'slideInLeft', 'mobile' => 'fadeIn' ), 'out' => array( '' => 'slideInLeft' ), 'o' => false, 'e' => false, 's' => true ) === $data, 'Animaciones por dispositivo (sólo valores válidos), cierres y bloqueo para el script: ' . json_encode( $data ) );
	check_oc( str_starts_with( $render( array( 'wrapper_html_tag' => 'script' ) ), '<div ' ) && str_contains( $render( array( 'off_canvas_name' => '  ' ) ), 'aria-label="Panel fuera de lienzo"' ), 'Etiqueta no válida → div; nombre vacío → nombre propio (el diálogo siempre tiene nombre).' );
	add_filter( 'elementor-pro/off-canvas/id', static function ( $id ) { return 'loop1-42-' . $id; } );
	check_oc( str_contains( $render( array(), null, 'abc1234' ), 'id="off-canvas-loop1-42-abc1234"' ), 'Dentro de un bucle, el id lleva el filtro de PRO (el mismo que aplica su etiqueta dinámica).' );
	remove_all_filters( 'elementor-pro/off-canvas/id' );
	check_oc( str_contains( $render( array(), array() ), '<div class="digi-noc__content"></div>' ), 'Sin hijo, contenido vacío.' );

	// 5. Plantilla del editor.
	ob_start();
	( digi_test_accessible( new ReflectionMethod( $own, 'content_template' ) ) )->invoke( $own );
	$template = (string) ob_get_clean();
	foreach ( array( "'off-canvas-' + view.getID()", "'role': 'dialog'", "'aria-modal': 'true'", "'data-digi-noc': JSON.stringify( digiConfig )", "'data-delay-child-handlers': 'true'", 'elementor.previewView.isBuffering', "'yes' !== settings.editing_mode", 'validateHTMLTag', '<div class="digi-noc__overlay"></div><div class="digi-noc__main"><div class="digi-noc__content"></div></div>', 'eicon-off-canvas' ) as $piece ) {
		check_oc( str_contains( $template, $piece ), 'La plantilla del editor no tiene: ' . $piece );
	}
	check_oc( ! str_contains( $template, '{{{ settings.' ) && ! preg_match( '/var[^;#]*[\s,]view\s*=/', $template ), 'La plantilla no imprime ajustes sin escapar ni tapa el objeto view.' );

	// 6. Conversión de paneles de PRO con su contenedor hijo.
	Registry::reset();
	$kid  = static function ( string $id ) {
		return array(
			'id'       => $id . 'c',
			'elType'   => 'container',
			'isInner'  => true,
			'settings' => array( 'content_width' => 'full', 'background_background' => 'classic', 'background_color' => '#f4f4f4' ),
			'elements' => array(
				array( 'id' => $id . 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Menú' ), 'elements' => array() ),
				array( 'id' => $id . 'b', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Cerrar', '__dynamic__' => array( 'link' => '[elementor-tag id="c1" name="off-canvas" settings="%7B%22action%22%3A%22close%22%2C%22off_canvas%22%3A%22' . $id . '%22%7D"]' ) ), 'elements' => array() ),
			),
		);
	};
	$cases = array(
		'defaults' => array(),
		'layout'   => array( 'editing_mode' => '', 'off_canvas_name' => 'Menú móvil', 'horizontal_position' => 'flex-end', 'horizontal_position_mobile' => 'center', 'vertical_position' => 'center', 'width' => array( 'unit' => 'px', 'size' => 320 ), 'width_mobile' => array( 'unit' => 'vw', 'size' => 90 ), 'height' => 'fit-content', 'height_tablet' => 'custom', 'custom_height_tablet' => array( 'unit' => 'vh', 'size' => 60 ) ),
		'settings' => array( 'entrance_animation' => 'slideInRight', 'entrance_animation_mobile' => 'fadeInUp', 'exit_animation' => 'slideInRight', 'offcanvas_animation_duration' => array( 'unit' => 'ms', 'size' => 400 ), 'is_not_close_on_overlay' => 'yes', 'is_not_close_on_esc_overlay' => 'yes', 'prevent_scroll' => 'yes', 'wrapper_html_tag' => 'nav' ),
		'style'    => array( 'background_background' => 'gradient', 'background_color' => '#111111', 'background_color_b' => '#245eff', 'border_border' => 'solid', 'border_width' => array( 'unit' => 'px', 'top' => '0', 'right' => '2', 'bottom' => '0', 'left' => '0', 'isLinked' => false ), 'border_color' => '#e63946', 'border_radius' => array( 'unit' => 'px', 'top' => '0', 'right' => '16', 'bottom' => '16', 'left' => '0', 'isLinked' => false ), 'border_radius_mobile' => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ), 'box_shadow_box_shadow_type' => 'yes', 'box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 0, 'blur' => 30, 'spread' => 0, 'color' => 'rgba(0,0,0,.4)' ) ),
		'overlay'  => array( 'overlay_background_background' => 'gradient', 'overlay_background_color' => 'rgba(36,94,255,.6)', 'overlay_background_color_b' => 'rgba(0,0,0,.9)', 'overlay_background_gradient_angle' => array( 'unit' => 'deg', 'size' => 90 ) ),
		'nolayer'  => array( 'has_overlay' => '' ),
		'globals'  => array( 'background_color' => '', '__globals__' => array( 'background_color' => 'globals/colors?id=primary', 'border_color' => 'globals/colors?id=accent', 'overlay_background_color' => 'globals/colors?id=text', '_background_color' => 'globals/colors?id=secondary' ) ),
		'dynamic'  => array( '__dynamic__' => array( 'off_canvas_name' => '[elementor-tag id="t1" name="site-title" settings="%7B%7D"]' ) ),
		'css'      => array( 'custom_css' => "selector .e-off-canvas__content{padding:24px}\nselector .e-off-canvas__overlay{backdrop-filter:blur(2px)}\n.elementor-widget-off-canvas .e-off-canvas{z-index:99}\nbody.e-off-canvas__no-scroll{overflow:clip}", '_css_classes' => 'mi-panel', '_element_id' => 'menu', 'hide_desktop' => 'hidden-desktop' ),
	);
	$tree = array( array( 'id' => 'p1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => 'b' . $id, 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Abrir', '__dynamic__' => array( 'link' => '[elementor-tag id="o1" name="off-canvas" settings="%7B%22off_canvas%22%3A%22' . $id . '%22%7D"]' ) ), 'elements' => array() );
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'off-canvas', 'settings' => $settings, 'elements' => array( $kid( $id ) ) );
	}
	$converted = Converter::tree( $tree, $log, true );
	$entries   = array_values( array_filter( $log, static function ( $entry ) { return 'off-canvas' === $entry['source']; } ) );
	check_oc( count( $cases ) === count( $entries ) && count( $cases ) === count( $log ), 'Se convierte cada panel del árbol y nada más (' . count( $entries ) . ').' );
	$own_keys = array_keys( $own->controls );
	$lands    = static function ( $key ) use ( $own_keys, $own ) {
		$base = Translator::base( (string) $key );
		if ( in_array( $base, $own_keys, true ) || Translator::common( (string) $key ) || in_array( $key, array( '__globals__', '__dynamic__', Translator::MARKER ), true ) ) {
			return true;
		}
		foreach ( array_keys( $own->groups ) as $group ) {
			if ( 0 === strpos( (string) $key, $group . '_' ) ) {
				return true;
			}
		}
		return false;
	};
	$out = array();
	foreach ( $converted[0]['elements'] as $index => $element ) {
		$original = $tree[0]['elements'][ $index ];
		if ( 'button' === $original['widgetType'] ) {
			check_oc( $original === $element, $element['id'] . ': el botón que abre el panel no se toca (su enlace sigue nombrando el mismo id).' );
			continue;
		}
		check_oc( $name === $element['widgetType'] && $original['id'] === $element['id'] && 'widget' === $element['elType'], $element['id'] . ': pasa al Panel fuera de lienzo con el mismo id.' );
		check_oc( $original['elements'] === $element['elements'], $element['id'] . ': el contenedor hijo no se toca (mismo id, ajustes y widgets).' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_oc( $lands( $key ), $element['id'] . ": la clave $key no cae en un control propio." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_oc( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control propio." );
			}
		}
	}
	$codes = array();
	foreach ( $entries as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_oc( array( 'css:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped, pro.dropped ni pro.approx; sólo el CSS: ' . implode( ', ', $codes ) );
	check_oc( 'Off-Canvas' === $out['defaults']['off_canvas_name'] && 'off-canvas' === $out['defaults']['_digitalisimo_pro'], 'Un panel sin cambios conserva el nombre accesible de PRO.' );
	$l = $out['layout'];
	check_oc( '' === $l['editing_mode'] && 'Menú móvil' === $l['off_canvas_name'] && 'center' === $l['horizontal_position_mobile'] && 90 === $l['width_mobile']['size'] && 'fit-content' === $l['height'] && 60 === $l['custom_height_tablet']['size'], 'Diseño por dispositivo.' );
	$s = $out['settings'];
	check_oc( 'fadeInUp' === $s['entrance_animation_mobile'] && 400 === $s['offcanvas_animation_duration']['size'] && 'yes' === $s['is_not_close_on_esc_overlay'] && 'nav' === $s['wrapper_html_tag'], 'Animaciones, cierres y etiqueta.' );
	check_oc( 'gradient' === $out['style']['background_background'] && '#245eff' === $out['style']['background_color_b'] && '16' === $out['style']['border_radius']['right'] && 'yes' === $out['style']['box_shadow_box_shadow_type'] && 'rgba(36,94,255,.6)' === $out['overlay']['overlay_background_color'] && '' === $out['nolayer']['has_overlay'], 'Fondo, borde, radio, sombra y capa.' );
	check_oc( array( 'background_color' => 'globals/colors?id=primary', 'border_color' => 'globals/colors?id=accent', 'overlay_background_color' => 'globals/colors?id=text', '_background_color' => 'globals/colors?id=secondary' ) === $out['globals']['__globals__'] && array( 'off_canvas_name' => '[elementor-tag id="t1" name="site-title" settings="%7B%7D"]' ) === $out['dynamic']['__dynamic__'], 'Globales del kit y etiquetas dinámicas siguen a su control.' );
	check_oc( "selector .digi-noc__content{padding:24px}\nselector .digi-noc__overlay{backdrop-filter:blur(2px)}\n.elementor-widget-digitalisimo-nested-off-canvas .digi-noc{z-index:99}\nbody.digi-noc-lock{overflow:clip}" === $out['css']['custom_css'] && 'mi-panel' === $out['css']['_css_classes'] && 'menu' === $out['css']['_element_id'] && 'hidden-desktop' === $out['css']['hide_desktop'], 'CSS personalizado con las clases propias y Avanzado intacto: ' . json_encode( $out['css']['custom_css'] ) );
	check_oc( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );
	$painted = $render( $out['settings'] );
	check_oc( str_starts_with( $painted, '<nav id="off-canvas-abc1234" class="digi-noc" role="dialog" aria-modal="true" aria-label="Off-Canvas"' ) && str_contains( $painted, '&quot;mobile&quot;:&quot;fadeInUp&quot;' ) && str_contains( $painted, '&quot;e&quot;:false' ), 'Lo convertido se pinta con sus ajustes: ' . substr( $painted, 0, 300 ) );

	echo 'DIGITALÍSIMO Elements: Panel fuera de lienzo (' . count( $own->controls ) . ' controles, ' . count( $own->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . ' B) y su mapa desde el Off-Canvas de PRO (' . count( $cases ) . " casos con su contenedor hijo) validados.\n";
}
