<?php
/**
 * Carrusel anidado (`digitalisimo-nested-carousel`), el widget propio que sustituye al «Carrusel» anidado de PRO (C1 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c1.md §2 «N»), y su mapa
 * `modules/digitalisimo-convert/maps/nested-carousel.php`:
 * - registro y recursos (motor + su hoja, ≤ 4 KB, sin `--e-global-*` ni `url()`; script sólo del editor);
 * - controles: se cargan a la vez el widget de PRO (con su trait real, `base/base-carousel-trait.php`) y el propio, y
 *   cada control de PRO tiene uno propio con el mismo nombre, tipo, valor por defecto (salvo los textos, que se
 *   traducen), opciones, condiciones, dispositivos y sección; los grupos, con el mismo tipo, `types` y `exclude`;
 * - cobertura del mapa: cada control de PRO (también los del trait y los que arma por concatenación) está declarado;
 * - marcado del servidor: el del motor (`Engine::open()`), con los contenedores hijos como diapositivas (sólo el hijo
 *   directo recibe los atributos), y cada variante (una diapositiva, fracción, progreso, iconos, RTL, posiciones fuera,
 *   escape);
 * - plantilla del editor: las mismas piezas que el servidor y la lista oculta del repetidor mejorado;
 * - conversión con `Converter::tree()` de carruseles de PRO reales (por defecto, estilos, posiciones, responsivos,
 *   `__dynamic__` y `__globals__`, CSS personalizado) con sus hijos intactos: ningún `pro.unmapped`, cada clave cae en un
 *   control propio y lo convertido se pinta.
 */
namespace Elementor {
	class Plugin { public static $instance; public $experiments; }
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const NUMBER = 'number';
		const CHOOSE = 'choose'; const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const DIMENSIONS = 'dimensions';
		const ICONS = 'icons'; const RAW_HTML = 'raw_html'; const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Icons_Manager {
		public static function try_get_icon_html( $icon, $attributes = array(), $tag = 'i' ) {
			return '<i class="' . htmlspecialchars( (string) ( $icon['value'] ?? '' ), ENT_QUOTES ) . '" aria-hidden="true"></i>';
		}
		public static function render_icon( $icon, $attributes = array(), $tag = 'i' ) { echo self::try_get_icon_html( $icon, $attributes, $tag ); }
	}
	/** Registro de controles común: el de PRO y el propio pasan por aquí. */
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $order    = array();
		public $children = array();
		public $data     = array();
		protected $section = '';
		protected $tab = '';
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return $this->get_settings_for_display( $key ); }
		public function get_children() { return $this->children; }
		public function get_id() { return 'abc1234'; }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; $this->section = $name; }
		public function end_controls_section() { $this->section = ''; }
		public function start_controls_tabs( $name ) {}
		public function start_controls_tab( $name, $args ) { $this->tab = $name; }
		public function end_controls_tab() { $this->tab = ''; }
		public function end_controls_tabs() { $this->tab = ''; }
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args + array( '_section' => $this->section, '_tab' => $this->tab ); $this->order[] = $name; }
		public function add_responsive_control( $name, $args ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type, '_section' => $this->section, '_tab' => $this->tab ) + $args; $this->order[] = '@' . $args['name']; }
		protected function get_initial_config() { return array(); }
	}
	class Repeater extends Widget_Base {
		public function get_controls() { return $this->controls; }
	}
}
namespace Elementor\Modules\NestedElements\Base {
	abstract class Widget_Nested_Base extends \Elementor\Widget_Base {
		abstract protected function get_default_children_elements();
		abstract protected function get_default_repeater_title_setting_key();
		protected function get_default_children_title() { return 'Item #%d'; }
		protected function get_default_children_placeholder_selector() { return ''; }
		protected function get_default_children_container_placeholder_selector() { return ''; }
		protected function get_initial_config() {
			return array( 'defaults' => array( 'elements' => $this->get_default_children_elements(), 'elements_placeholder_selector' => $this->get_default_children_placeholder_selector() ), 'support_nesting' => true );
		}
		public function print_child( $index ) {
			if ( isset( $this->children[ $index ] ) ) {
				$this->children[ $index ]->print_element();
			}
		}
	}
}
namespace Elementor\Modules\NestedElements\Controls {
	class Control_Nested_Repeater { const CONTROL_TYPE = 'nested-elements-repeater'; }
}
namespace ElementorPro\Base {
	trait Base_Widget_Trait {}
	abstract class Widget_Nested_Base_Pro extends \Elementor\Modules\NestedElements\Base\Widget_Nested_Base {}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['nc'] = array( 'filters' => array(), 'rtl' => false, 'features' => array( 'container' => true, 'nested-elements' => true ) );
	function __( $t, $d = '' ) { return $t; }
	function esc_html__( $t, $d = '' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $t, $d = '' ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_js( $v ) { return addslashes( (string) $v ); }
	function is_rtl() { return $GLOBALS['nc']['rtl']; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['nc']['filters'][ $hook ][] = $cb; }
	function remove_filter( $hook, $cb, $priority = 10 ) {
		foreach ( $GLOBALS['nc']['filters'][ $hook ] ?? array() as $i => $f ) {
			if ( $f === $cb ) {
				unset( $GLOBALS['nc']['filters'][ $hook ][ $i ] );
			}
		}
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['nc']['filters'][ $hook ] ?? array() as $cb ) {
			$value = $cb( $value, ...$args );
		}
		return $value;
	}
	function check_nc( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	/** Contenedor hijo de prueba: pinta `div.e-con` con los atributos que le añada el filtro del núcleo, y sus hijos. */
	class NC_Child {
		public $attrs = array();
		public $html;
		public $inner;
		public function __construct( $html, $inner = null ) { $this->html = $html; $this->inner = $inner; }
		public function add_render_attribute( $key, $attrs ) { $this->attrs = array_merge( $this->attrs, $attrs ); }
		public function print_element() {
			$this->attrs = array();
			if ( ! apply_filters( 'elementor/frontend/container/should_render', true, $this ) ) {
				return;
			}
			$class = trim( 'e-con ' . ( $this->attrs['class'] ?? '' ) );
			$out   = '<div class="' . esc_attr( $class ) . '"';
			foreach ( $this->attrs as $name => $value ) {
				if ( 'class' !== $name ) {
					$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
				}
			}
			echo $out . '>' . $this->html;
			if ( $this->inner ) {
				$this->inner->print_element();
			}
			echo '</div>';
		}
	}

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/digitalisimo-carousel/class-module.php';
	\Digitalisimo\Elements\Carousel\Module::init();
	require $root . 'modules/digitalisimo-carousel/class-engine.php';
	require $root . 'modules/digitalisimo-carousel/trait-nested-controls.php';
	require $root . 'modules/digitalisimo-carousel/widgets/class-nested-carousel.php';
	require $root . 'base/base-carousel-trait.php';
	require $root . 'modules/nested-carousel/widgets/nested-carousel.php';
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return ! empty( $GLOBALS['nc']['features'][ $f ] ); } };

	use Digitalisimo\Elements\Carousel\Engine;
	use Digitalisimo\Elements\Carousel\Module;
	use Digitalisimo\Elements\Carousel\Widgets\Nested_Carousel;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$own = new Nested_Carousel();
	( new ReflectionMethod( $own, 'register_controls' ) )->invoke( $own );
	$pro = new \ElementorPro\Modules\NestedCarousel\Widgets\Nested_Carousel();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );

	/** Ajustes como los daría Elementor: los valores por defecto de cada control (y de cada dispositivo) más los guardados. */
	$defaults = static function ( $widget ) {
		$out = array();
		foreach ( $widget->controls as $name => $control ) {
			if ( array_key_exists( 'default', $control ) ) {
				$out[ $name ] = $control['default'];
			}
			foreach ( array( 'tablet', 'mobile' ) as $device ) {
				if ( array_key_exists( $device . '_default', $control ) ) {
					$out[ $name . '_' . $device ] = $control[ $device . '_default' ];
				}
			}
		}
		return $out;
	};
	$children = static function ( int $n ) {
		$out = array();
		for ( $i = 1; $i <= $n; $i++ ) {
			$out[] = new NC_Child( '<h3>Diapositiva ' . $i . '</h3>' );
		}
		return $out;
	};
	$render = static function ( array $settings, array $kids ) use ( $own, $defaults ) {
		$widget           = new Nested_Carousel();
		$widget->controls = $own->controls;
		$widget->settings = $settings + $defaults( $own );
		$widget->children = $kids;
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};
	$items = static function ( int $n ) {
		$out = array();
		for ( $i = 1; $i <= $n; $i++ ) {
			$out[] = array( '_id' => 'it' . $i, 'slide_title' => 'Slide #' . $i );
		}
		return $out;
	};

	// 1. Registro y recursos.
	check_nc( 'digitalisimo-nested-carousel' === $own->get_name() && 'Carrusel anidado' === $own->get_title() && array( 'digitalisimo' ) === $own->get_categories() && 'eicon-nested-carousel' === $own->get_icon(), 'Nombre, título, icono y categoría propios.' );
	check_nc( array( Engine::HANDLE, 'digitalisimo-nested-carousel' ) === $own->get_style_depends() && array( Engine::HANDLE ) === $own->get_script_depends(), 'Hoja del motor y la propia; sólo el script del motor.' );
	check_nc( isset( Module::WIDGETS['digitalisimo-nested-carousel'] ) && Nested_Carousel::class === Module::WIDGETS['digitalisimo-nested-carousel']['class'] && in_array( 'digitalisimo-nested-carousel', Module::NESTED, true ), 'El módulo de carruseles registra el widget como anidado.' );
	check_nc( true === $own->show_in_panel() && true === $own->has_widget_inner_wrapper(), 'Con contenedores, en el panel; sin el marcado optimizado, con envoltorio interior.' );
	$GLOBALS['nc']['features'] = array( 'e_optimized_markup' => true );
	check_nc( false === $own->show_in_panel() && false === $own->has_widget_inner_wrapper(), 'Sin contenedores, fuera del panel (como PRO); con el marcado optimizado, sin envoltorio.' );
	$GLOBALS['nc']['features'] = array( 'container' => true, 'nested-elements' => true );
	$css = (string) file_get_contents( $root . 'modules/digitalisimo-carousel/assets/css/nested-carousel.css' );
	check_nc( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_nc( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! preg_match( '/swiper|e-n-carousel/i', $css ), 'La hoja no usa el kit, url() ni clases de Swiper o de PRO.' );
	check_nc( ! is_file( $root . 'modules/digitalisimo-carousel/assets/js/nested-carousel.js' ) && is_file( $root . 'modules/digitalisimo-carousel/assets/js/nested-carousel-editor.js' ), 'Sin script propio en el front; sí el del editor.' );
	$editor_js = (string) file_get_contents( $root . 'modules/digitalisimo-carousel/assets/js/nested-carousel-editor.js' );
	check_nc( strlen( $editor_js ) <= 6144 && ! preg_match( '/jQuery|\$\(|swiper/i', $editor_js ) && str_contains( $editor_js, "'digitalisimo-nested-carousel'" ) && str_contains( $editor_js, 'digi-crs-go' ) && str_contains( $editor_js, 'elementor/nested-container/atomic-repeater' ), 'Script del editor sin jQuery ni Swiper: registra el tipo, puentea la diapositiva activa y repinta tras el repetidor.' );
	$source = (string) file_get_contents( $root . 'modules/digitalisimo-carousel/widgets/class-nested-carousel.php' );
	foreach ( array( $source, $css, $editor_js, (string) file_get_contents( $root . 'modules/digitalisimo-convert/maps/nested-carousel.php' ) ) as $text ) {
		check_nc( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $text ), 'Sin nombres de otros proveedores (guarda de build-packages.sh).' );
	}
	$config = ( new ReflectionMethod( $own, 'get_initial_config' ) )->invoke( $own );
	check_nc( true === $config['support_improved_repeaters'] && array( '.digi-ncarousel__items' ) === $config['target_container'] && 'span' === $config['node'] && '.digi-crs__track' === $config['defaults']['elements_placeholder_selector'] && 3 === count( $config['defaults']['elements'] ), 'Repetidor mejorado sobre la lista oculta; hijos en la pista; tres diapositivas nuevas.' );

	// 2. Un control propio por cada control de PRO, igual salvo los textos.
	$own_names   = array( 'carousel_name', 'carousel_items', 'image_spacing_custom', 'border_radius', 'content_padding' );
	$trait_notes = array();
	$same = static function ( $key, $a, $b ) { return ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ); };
	check_nc( array_keys( $pro->controls ) === array_keys( $own->controls ), 'Los mismos controles en el mismo orden. Sobran: ' . implode( ', ', array_diff( array_keys( $own->controls ), array_keys( $pro->controls ) ) ) . ' · faltan: ' . implode( ', ', array_diff( array_keys( $pro->controls ), array_keys( $own->controls ) ) ) );
	check_nc( $pro->order === $own->order, 'Los mismos controles y grupos en el mismo orden.' );
	foreach ( $pro->controls as $name => $p ) {
		$o = $own->controls[ $name ];
		foreach ( array( 'type', 'condition', 'conditions', 'responsive', 'tablet_default', 'mobile_default', '_section', '_tab' ) as $key ) {
			check_nc( $same( $key, $p, $o ), "$name: «{$key}» distinto de PRO (" . json_encode( $p[ $key ] ?? null ) . ' / ' . json_encode( $o[ $key ] ?? null ) . ').' );
		}
		// Detalles del panel: obligatorios en los controles propios; en los del trait (frente E) sólo se anotan.
		foreach ( array( 'size_units', 'separator', 'frontend_available', 'render_type', 'label_block', 'inherit_placeholders' ) as $key ) {
			if ( ! $same( $key, $p, $o ) ) {
				check_nc( ! in_array( $name, $own_names, true ), "$name: «{$key}» distinto de PRO (" . json_encode( $p[ $key ] ?? null ) . ' / ' . json_encode( $o[ $key ] ?? null ) . ').' );
				$trait_notes[] = "$name.$key";
			}
		}
		if ( ! in_array( $p['type'], array( 'text', 'heading', 'nested-elements-repeater' ), true ) ) {
			check_nc( $same( 'default', $p, $o ), "$name: el valor por defecto no es el de PRO (" . json_encode( $p['default'] ?? null ) . ' / ' . json_encode( $o['default'] ?? null ) . ').' );
		}
		if ( isset( $p['options'] ) ) {
			check_nc( array_keys( $p['options'] ) === array_keys( $o['options'] ), "$name: las opciones no son las de PRO." );
		}
		if ( isset( $p['range'] ) ) {
			check_nc( $p['range'] == $o['range'], "$name: el rango no es el de PRO." );
		}
		check_nc( ! isset( $p['selectors'] ) || isset( $o['selectors'] ), "$name: con selectores en PRO y sin ellos aquí." );
		if ( ! empty( $o['selectors'] ) ) {
			check_nc( ! preg_match( '/swiper|e-n-carousel|elementor-pagination|elementor-arrows/', implode( ' ', array_keys( $o['selectors'] ) ) . implode( ' ', $o['selectors'] ) . implode( ' ', (array) ( $o['selectors_dictionary'] ?? array() ) ) ), "$name: selector o variable de PRO." );
		}
		check_nc( empty( $o['prefix_class'] ), "$name: sin prefix_class (las posiciones son modificadores del motor)." );
	}
	foreach ( $pro->groups as $name => $p ) {
		check_nc( isset( $own->groups[ $name ] ), "$name: sin grupo propio." );
		$o = $own->groups[ $name ];
		foreach ( array( 'type', 'types', 'exclude', 'condition', '_section', '_tab' ) as $key ) {
			check_nc( $same( $key, $p, $o ), "Grupo $name: «{$key}» distinto de PRO." );
		}
		check_nc( ! preg_match( '/swiper|e-n-carousel/', (string) $o['selector'] ), "Grupo $name: selector de PRO." );
	}
	foreach ( $pro->sections as $name => $p ) {
		check_nc( isset( $own->sections[ $name ] ) && $same( 'tab', $p, $own->sections[ $name ] ) && $same( 'condition', $p, $own->sections[ $name ] ), "Sección $name: falta o cambia de pestaña o condición." );
	}
	$pro_fields = $pro->controls['carousel_items']['fields'];
	$own_fields = $own->controls['carousel_items']['fields'];
	check_nc( array_keys( $pro_fields ) === array_keys( $own_fields ) && 'text' === $own_fields['slide_title']['type'] && true === $own_fields['slide_title']['dynamic']['active'] && 3 === count( $own->controls['carousel_items']['default'] ) && '{{{ slide_title }}}' === $own->controls['carousel_items']['title_field'], 'Repetidor: el mismo campo, dinámico, tres filas por defecto y el título en el navegador.' );
	check_nc( '' === ( $own->controls['slides_to_show']['default'] ?? '' ) && '2' === $own->controls['slides_to_show']['tablet_default'] && '1' === $own->controls['slides_to_show']['mobile_default'], 'Diapositivas visibles: «Predeterminado» (3) en escritorio, 2 en tableta y 1 en móvil, como PRO.' );
	check_nc( str_contains( implode( '', $own->controls['slides_to_show']['selectors'] ), '--digi-crs-view' ) && str_contains( implode( '', $own->controls['image_spacing_custom']['selectors'] ), '--digi-crs-gap' ) && 10 === $own->controls['image_spacing_custom']['default']['size'], 'Visibles y separación por las variables del motor; 10 px por defecto.' );
	check_nc( ! isset( $own->controls['slides_to_scroll']['default'] ) && array( 'default' => '1' ) === ( $own->controls['slides_to_scroll']['device_args']['tablet'] ?? null ) && array( 'default' => '1' ) === ( $own->controls['slides_to_scroll']['device_args']['mobile'] ?? null ) && str_contains( implode( '', $own->controls['slides_to_scroll']['selectors'] ), '--digi-crs-scroll' ), 'Paso: el de escritorio sólo en escritorio; en los demás dispositivos, 1 si no se fija (como Swiper en PRO).' );
	foreach ( array( 'content_background', 'content_border' ) as $group ) {
		check_nc( Nested_Carousel::SLIDES === $own->groups[ $group ]['selector'], "$group: sobre los contenedores-diapositiva del propio widget." );
	}
	check_nc( str_contains( Nested_Carousel::SLIDES, ':where(' ) && str_contains( Nested_Carousel::SLIDES, '{{WRAPPER}} > .digi-crs > .digi-crs__track' ) && str_contains( Nested_Carousel::SLIDES, '{{WRAPPER}} > .elementor-widget-container > .digi-crs > .digi-crs__track' ), 'Selector de baja especificidad que no alcanza un carrusel anidado dentro de una diapositiva.' );
	check_nc( str_contains( implode( '', $own->controls['content_padding']['selectors'] ), '--padding-top: {{TOP}}{{UNIT}}' ) && str_contains( implode( '', $own->controls['border_radius']['selectors'] ), '--border-radius:' ), 'Relleno y radio por las variables del contenedor, como PRO.' );

	// 3. Cobertura del mapa: cada control de PRO, también los del trait.
	$map = require $root . 'modules/digitalisimo-convert/maps/nested-carousel.php';
	check_nc( 'nested-carousel' === $map['source'] && 'digitalisimo-nested-carousel' === $map['target'] && 'hide' === $map['panel'] && in_array( $map['status'], array( 'draft', 'ready' ), true ), 'Origen, destino, estado y panel del mapa.' );
	$declared = array_merge( $map['keep'], array_keys( $map['rename'] ?? array() ), array_keys( $map['groups'] ), $map['ui'], array_keys( $map['repeaters'] ), array_keys( $map['drop'] ?? array() ) );
	foreach ( array_keys( $pro->controls ) as $name ) {
		check_nc( in_array( $name, $declared, true ), "$name: el mapa no lo declara." );
		if ( in_array( $name, $map['ui'], true ) ) {
			check_nc( 'heading' === $own->controls[ $name ]['type'], "$name: encabezado del panel." );
		} elseif ( in_array( $name, $map['keep'], true ) ) {
			check_nc( isset( $own->controls[ $name ] ), "$name: sin control propio." );
		}
	}
	foreach ( array_keys( $pro->groups ) as $name ) {
		check_nc( ( $map['groups'][ $name ] ?? '' ) === $name && isset( $own->groups[ $name ] ), "$name: grupo sin declarar o sin destino." );
	}
	check_nc( count( $declared ) === count( array_unique( $declared ) ) && count( $declared ) === count( $pro->controls ) + count( $pro->groups ), 'El mapa declara cada control de PRO una sola vez (' . count( $declared ) . ' / ' . ( count( $pro->controls ) + count( $pro->groups ) ) . ').' );
	check_nc( array( 'slide_title' ) === $map['repeaters']['carousel_items']['keep'] && 3 === count( $map['repeaters']['carousel_items']['default_rows'] ), 'El repetidor conserva sus filas y, sin guardar, las tres de PRO.' );
	foreach ( array_keys( $map['approx'] ) as $key ) {
		check_nc( in_array( $key, $map['keep'], true ), "$key: la aproximación pasa con keep." );
	}
	foreach ( $map['classes'] as $from => $to ) {
		check_nc( 0 === strpos( $from, '.' ) && '' !== $to && ! preg_match( '/swiper|e-n-carousel/', $to ), "Clase $from → $to." );
	}
	check_nc( '.digi-ncarousel' === $map['classes']['.e-n-carousel'] && '.elementor-widget-digitalisimo-nested-carousel' === $map['classes']['.elementor-widget-n-carousel'], 'Las clases del widget de PRO pasan a las propias.' );

	// 4. Marcado del servidor.
	$html = $render( array( 'carousel_items' => $items( 3 ) ), $children( 3 ) );
	check_nc( 0 === strpos( $html, '<section class="digi-crs digi-crs--pag-out digi-ncarousel" aria-roledescription="carrusel" aria-label="Carrusel" data-digi-autoplay="5000" data-digi-hover="1" data-digi-interaction="1" data-digi-loop="1" data-digi-effect="slide" data-digi-pagination="bullets" style="--digi-crs-speed:500ms">' ), 'Raíz del motor con los valores por defecto de PRO: ' . substr( $html, 0, 400 ) );
	check_nc( 1 === substr_count( $html, 'class="digi-crs__pause"' ) && 1 === preg_match_all( '/class="[^"]*digi-crs__prev[^"]*"/', $html ) && 1 === preg_match_all( '/class="[^"]*digi-crs__next[^"]*"/', $html ) && 3 === substr_count( $html, 'class="digi-crs__dot"' ) && str_contains( $html, Engine::ICON_PREV ) && str_contains( $html, Engine::ICON_NEXT ), 'Pausa (autoplay por defecto), flechas con el chevron propio y un punto por diapositiva.' );
	check_nc( strpos( $html, 'digi-crs__pause' ) < strpos( $html, 'digi-crs__prev' ) && strpos( $html, 'digi-crs__prev' ) < strpos( $html, 'digi-crs__pagination' ) && strpos( $html, 'digi-crs__pagination' ) < strpos( $html, 'digi-crs__next' ) && strpos( $html, 'digi-crs__next' ) < strpos( $html, 'digi-crs__track' ), 'Controles antes de la pista.' );
	check_nc( str_contains( $html, '<div class="digi-crs__track" aria-live="off" dir="ltr"><div class="e-con digi-crs__slide" role="group" aria-roledescription="diapositiva" aria-label="1 de 3"><h3>Diapositiva 1</h3></div><div class="e-con digi-crs__slide" role="group" aria-roledescription="diapositiva" aria-label="2 de 3">' ) && str_ends_with( $html, 'aria-label="3 de 3"><h3>Diapositiva 3</h3></div></div></section>' ), 'Los contenedores hijos son las diapositivas, directamente en la pista: ' . $html );
	check_nc( array() === ( $GLOBALS['nc']['filters']['elementor/frontend/container/should_render'] ?? array() ), 'El filtro del hijo se quita después de pintarlo.' );
	$inner = $render( array( 'carousel_items' => $items( 2 ) ), array( new NC_Child( 'A', new NC_Child( 'interior' ) ), new NC_Child( 'B' ) ) );
	check_nc( str_contains( $inner, 'aria-label="1 de 2">A<div class="e-con">interior</div></div>' ), 'Un contenedor dentro de la diapositiva no recibe los atributos: ' . $inner );
	$plain = $render( array( 'carousel_items' => $items( 4 ), 'autoplay' => '' ), $children( 3 ) );
	check_nc( ! str_contains( $plain, 'digi-crs__pause' ) && ! str_contains( $plain, 'data-digi-autoplay' ) && str_contains( $plain, 'aria-live="polite"' ) && 3 === substr_count( $plain, 'digi-crs__dot"' ) && str_contains( $plain, 'aria-label="3 de 3"' ), 'Sin autoplay: sin pausa y región viva educada; filas sin hijo no se pintan.' );
	$one = $render( array( 'carousel_items' => $items( 1 ) ), $children( 1 ) );
	check_nc( ! preg_match( '/digi-crs__(pause|prev|next|pagination)/', $one ) && str_contains( $one, 'aria-label="1 de 1"' ), 'Una diapositiva: sin flechas, paginación ni pausa, como PRO.' );
	$fraction = $render( array( 'carousel_items' => $items( 4 ), 'autoplay' => '', 'pagination' => 'fraction', 'fraction_position' => 'inside', 'arrows' => '', 'infinite' => '', 'carousel_name' => 'Nov"edades <b>' ), $children( 4 ) );
	check_nc( str_contains( $fraction, '<section class="digi-crs digi-ncarousel" aria-roledescription="carrusel" aria-label="Nov&quot;edades &lt;b&gt;" data-digi-effect="slide" data-digi-pagination="fraction"' ) && str_contains( $fraction, '<span class="digi-crs__fraction"><span class="digi-crs__now">1</span> / <span class="digi-crs__total">4</span></span>' ) && ! str_contains( $fraction, 'digi-crs__prev' ) && ! str_contains( $fraction, 'data-digi-loop' ), 'Fracción dentro, sin flechas ni bucle, nombre escapado: ' . substr( $fraction, 0, 400 ) );
	$bar = $render( array( 'carousel_items' => $items( 3 ), 'autoplay' => '', 'pagination' => 'progressbar', 'arrows_position' => 'outside', 'direction' => 'rtl', 'speed' => 800 ), $children( 3 ) );
	check_nc( str_contains( $bar, 'class="digi-crs digi-crs--arrows-out digi-ncarousel"' ) && preg_match( '/class="digi-crs__bar"[^>]*>.*class="digi-crs__fill"/', $bar ) && str_contains( $bar, 'dir="rtl"' ) && str_contains( $bar, '--digi-crs-speed:800ms' ), 'Barra de progreso (sin reserva), flechas fuera, RTL en la pista y velocidad.' );
	$none = $render( array( 'carousel_items' => $items( 3 ), 'autoplay' => '', 'pagination' => '' ), $children( 3 ) );
	check_nc( ! str_contains( $none, 'digi-crs__pagination' ) && ! str_contains( $none, 'digi-crs--pag-out' ) && ! str_contains( $none, 'data-digi-pagination' ), 'Sin paginación.' );
	$icons = $render( array( 'carousel_items' => $items( 3 ), 'autoplay' => '', 'navigation_previous_icon' => array( 'value' => 'fas fa-arrow-left', 'library' => 'fa-solid' ), 'navigation_next_icon' => array( 'value' => '', 'library' => '' ) ), $children( 3 ) );
	check_nc( preg_match( '/digi-crs__prev"[^>]*><i class="fas fa-arrow-left" aria-hidden="true"><\/i><\/button>/', $icons ) && preg_match( '/digi-crs__next"[^>]*><\/button>/', $icons ), 'Icono elegido con Icons_Manager; icono vaciado, flecha sin icono (como PRO).' );

	// 5. Plantilla del editor: construida con las piezas de Engine::open() (la equivalencia, caso por caso, la comprueba
	// tests/elements-pro-nested-carousel-editor.mjs con el volcado de NC_DUMP).
	ob_start();
	( new ReflectionMethod( $own, 'content_template' ) )->invoke( $own );
	$template = (string) ob_get_clean();
	$markup   = Nested_Carousel::editor_markup();
	foreach ( array( 'digi-crs__pause', 'digi-crs__prev', 'digi-crs__pagination', 'digi-crs__dot', 'digi-crs__fraction', 'digi-crs__total">{{ digiCount }}', 'digi-crs__bar', 'digi-crs__next', 'digi-crs__track', '{{ digiLabel }}', '{{ digiDelay }}', '{{ digiPag }}', '{{ digiSpeed }}', '{{ digiI }}', "'digi-crs--arrows-out'", "'digi-crs--pag-out'", "'digi-ncarousel'" ) as $piece ) {
		check_nc( str_contains( $markup, $piece ), 'La plantilla del editor no tiene: ' . $piece );
	}
	check_nc( ! preg_match( '/@L@|@PREV@|@NEXT@|4321|1234|rtl"|>7</', $markup ), 'No quedan valores de muestra en la plantilla: ' . $markup );
	check_nc( str_contains( $template, $markup ) && str_contains( $template, 'class="digi-ncarousel__items" data-digi-slide="diapositiva" data-digi-of="%1$d de %2$d" hidden' ) && ! preg_match( '/var[^;#]*[\s,]view\s*=/', $template ) && ! str_contains( $template, '{{{ settings.' ) && ! str_contains( $template, '{{{ digiS.' ), 'Plantilla completa, con la lista oculta del repetidor, sin tapar el objeto view ni imprimir ajustes sin escapar.' );
	ob_start();
	( new ReflectionMethod( $own, 'content_template_single_repeater_item' ) )->invoke( $own );
	check_nc( '<span></span>' === trim( (string) ob_get_clean() ), 'Nodo de la lista oculta por fila.' );
	if ( getenv( 'NC_DUMP' ) ) {
		$variants = array(
			array(),
			array( 'autoplay' => '' ),
			array( 'autoplay' => 'yes', 'autoplay_speed' => 3000, 'pause_on_hover' => '', 'pause_on_interaction' => 'yes', 'infinite' => '' ),
			array( 'pagination' => 'fraction', 'fraction_position' => 'inside', 'arrows' => '', 'carousel_name' => 'Nov"edades <b>' ),
			array( 'pagination' => 'fraction', 'carousel_name' => '' ),
			array( 'pagination' => 'progressbar', 'arrows_position' => 'outside', 'direction' => 'rtl', 'speed' => 800 ),
			array( 'pagination' => '', 'dots_position' => 'inside', 'direction' => 'ltr' ),
			array( 'pagination' => 'bullets', 'dots_position' => 'inside', 'direction' => '' ),
			array( 'navigation_previous_icon' => array( 'value' => 'fas fa-arrow-left', 'library' => 'fa-solid' ), 'navigation_next_icon' => array( 'value' => '', 'library' => '' ) ),
			array( 'autoplay_speed' => '', 'speed' => '' ),
		);
		$dump = array( 'template' => $template, 'cases' => array() );
		foreach ( array( 1, 2, 5 ) as $n ) {
			foreach ( $variants as $variant ) {
				$settings         = array( 'carousel_items' => $items( $n ) ) + $variant + $defaults( $own );
				$widget           = new Nested_Carousel();
				$dump['cases'][] = array( 'settings' => $settings, 'html' => Engine::open( $widget->engine_args( $settings, $n ) ) );
			}
		}
		file_put_contents( getenv( 'NC_DUMP' ), json_encode( $dump, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	// 6. Conversión de carruseles de PRO con sus hijos.
	Registry::reset();
	$kid = static function ( string $id, string $title, string $color ) {
		return array(
			'id'       => $id,
			'elType'   => 'container',
			'isInner'  => true,
			'settings' => array( '_title' => $title, 'content_width' => 'full', 'background_background' => 'classic', 'background_color' => $color ),
			'elements' => array(
				array( 'id' => $id . 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => $title ), 'elements' => array() ),
				array( 'id' => $id . 'q', 'elType' => 'widget', 'widgetType' => 'blockquote', 'settings' => array( 'blockquote_content' => 'Cita' ), 'elements' => array() ),
			),
		);
	};
	$cases = array(
		'defaults'  => array(),
		'layout'    => array( 'carousel_items' => $items( 3 ), 'slides_to_show' => '1', 'slides_to_show_mobile' => '1', 'slides_to_scroll' => '1', 'equal_height' => '', 'autoplay' => '', 'infinite' => '', 'speed' => 800, 'direction' => 'rtl', 'image_spacing_custom' => array( 'unit' => 'px', 'size' => 30 ), 'image_spacing_custom_tablet' => array( 'unit' => 'px', 'size' => 12 ) ),
		'autoplay'  => array( 'autoplay_speed' => 3000, 'pause_on_hover' => '', 'pause_on_interaction' => '', 'offset_sides' => 'both', 'offset_width' => array( 'unit' => 'px', 'size' => 60 ), 'offset_width_mobile' => array( 'unit' => 'px', 'size' => 20 ) ),
		'style'     => array( 'content_background_background' => 'classic', 'content_background_color' => '#eeeeee', 'content_border_border' => 'solid', 'content_border_width' => array( 'unit' => 'px', 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'isLinked' => true ), 'content_border_color' => '#111111', 'border_radius' => array( 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true ), 'content_padding' => array( 'unit' => 'px', 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'isLinked' => true ), 'content_padding_mobile' => array( 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true ) ),
		'arrows'    => array( 'navigation_previous_icon' => array( 'value' => 'fas fa-chevron-left', 'library' => 'fa-solid' ), 'navigation_previous_icon_horizontal_orientation' => 'center', 'navigation_previous_icon_horizontal_position' => array( 'unit' => 'px', 'size' => 12 ), 'navigation_previous_icon_vertical_orientation' => 'start', 'navigation_previous_icon_vertical_position' => array( 'unit' => '%', 'size' => 5 ), 'navigation_next_icon_horizontal_orientation_mobile' => 'center', 'navigation_next_icon_vertical_position_tablet' => array( 'unit' => 'px', 'size' => -10 ), 'arrows_size' => array( 'unit' => 'px', 'size' => 40 ), 'arrow_normal_color' => '#ffffff', 'arrow_hover_color' => '#ffd166', 'arrows_normal_background_background' => 'classic', 'arrows_normal_background_color' => '#111111', 'arrows_normal_border_type_border' => 'solid', 'arrows_normal_box_shadow_box_shadow_type' => 'yes', 'arrows_hover_background_background' => 'gradient', 'arrows_hover_border_type_border' => 'dashed', 'arrows_hover_box_shadow_box_shadow_type' => 'yes', 'arrows_border_radius' => array( 'unit' => '%', 'top' => '50', 'right' => '50', 'bottom' => '50', 'left' => '50', 'isLinked' => true ), 'arrows_padding' => array( 'unit' => 'px', 'top' => '6', 'right' => '6', 'bottom' => '6', 'left' => '6', 'isLinked' => true ), 'arrows_position' => 'outside' ),
		'dots'      => array( 'dots_gap' => array( 'unit' => 'px', 'size' => 8 ), 'dots_size' => array( 'unit' => 'px', 'size' => 10 ), 'dots_normal_color' => '#245eff', 'dots_hover_color' => '#e63946', 'dots_custom_position' => 'yes', 'dots_horizontal_position' => 'start', 'dots_horizontal_offset' => array( 'unit' => 'px', 'size' => 10 ), 'dots_vertical_position' => 'start', 'dots_vertical_offset' => array( 'unit' => 'px', 'size' => 4 ), 'dots_position' => 'inside', 'dots_pagination_spacing' => array( 'unit' => 'px', 'size' => 20 ) ),
		'fraction'  => array( 'pagination' => 'fraction', 'typography_fraction_typography' => 'custom', 'typography_fraction_font_size' => array( 'unit' => 'px', 'size' => 20 ), 'fraction_color' => '#e63946', 'fraction_custom_position' => 'yes', 'fraction_horizontal_position' => 'end', 'fraction_horizontal_offset' => array( 'unit' => 'px', 'size' => 4 ), 'fraction_vertical_position' => 'center', 'fraction_vertical_offset' => array( 'unit' => 'px', 'size' => 0 ), 'fraction_position' => 'outside', 'fraction_pagination_spacing' => array( 'unit' => 'px', 'size' => 12 ) ),
		'progress'  => array( 'pagination' => 'progressbar', 'progressbar_height' => array( 'unit' => 'px', 'size' => 6 ), 'progressbar_normal_color' => '#245eff', 'progressbar_hover_color' => '#111111', 'arrows' => '' ),
		'globals'   => array( 'carousel_items' => $items( 3 ), 'arrow_normal_color' => '', 'dots_normal_color' => '', '__globals__' => array( 'arrow_normal_color' => 'globals/colors?id=primary', 'arrow_hover_color' => 'globals/colors?id=accent', 'dots_normal_color' => 'globals/colors?id=secondary', 'fraction_color' => 'globals/colors?id=text', 'progressbar_normal_color' => 'globals/colors?id=accent', 'content_background_color' => 'globals/colors?id=secondary', 'content_border_color' => 'globals/colors?id=text', 'arrows_normal_background_color' => 'globals/colors?id=primary', 'typography_fraction_typography' => 'globals/typography?id=secondary', '_background_color' => 'globals/colors?id=text' ) ),
		'dynamic'   => array( 'carousel_items' => array( array( '_id' => 'd1', 'slide_title' => 'Uno', '__dynamic__' => array( 'slide_title' => '[elementor-tag id="t1" name="post-title" settings="%7B%7D"]' ) ), array( '_id' => 'd2', 'slide_title' => 'Dos' ), array( '_id' => 'd3', 'slide_title' => 'Tres' ) ), '__dynamic__' => array( 'carousel_name' => '[elementor-tag id="t2" name="site-title" settings="%7B%7D"]' ) ),
		'css'       => array( 'custom_css' => "selector .e-n-carousel{padding:4px}\nselector .swiper-slide > .e-con{color:red}\nselector .elementor-swiper-button-next svg{fill:red}\n.elementor-widget-n-carousel .swiper-pagination-bullet{opacity:1}", '_css_classes' => 'mi-carrusel', '_element_id' => 'novedades', 'motion_fx_motion_fx_scrolling' => 'yes', 'hide_mobile' => 'hidden-mobile' ),
	);
	$tree = array( array( 'id' => 'p1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'nested-carousel', 'settings' => $settings, 'elements' => array( $kid( $id . '1', 'Uno', '#245eff' ), $kid( $id . '2', 'Dos', '#e63946' ), $kid( $id . '3', 'Tres', '#2a9d8f' ) ) );
	}
	$converted = Converter::tree( $tree, $log, true );
	$carousels = array_values( array_filter( $log, static function ( $entry ) { return 'nested-carousel' === $entry['source']; } ) );
	check_nc( count( $cases ) === count( $carousels ), 'Se convierte cada carrusel anidado del árbol (' . count( $carousels ) . ').' );
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
		check_nc( 'digitalisimo-nested-carousel' === $element['widgetType'] && $original['id'] === $element['id'] && 'widget' === $element['elType'], $element['id'] . ': pasa al Carrusel anidado con el mismo id.' );
		foreach ( $element['elements'] as $k => $child ) {
			$expect                            = $original['elements'][ $k ];
			$expect['elements'][1]['settings'] = $child['elements'][1]['settings'];
			$expect['elements'][1]['widgetType'] = $child['elements'][1]['widgetType'];
			check_nc( $expect === $child, $element['id'] . ": el contenedor hijo $k no se toca (sólo los widgets de PRO de dentro, con su propio mapa)." );
		}
		$out[ $element['id'] ] = $element['settings'];
		foreach ( $element['settings'] as $key => $value ) {
			check_nc( $lands( $key ), $element['id'] . ": la clave $key no cae en un control propio." );
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( array_keys( $element['settings'][ $bag ] ?? array() ) as $key ) {
				check_nc( $lands( $key ), $element['id'] . ": $bag.$key no cae en un control propio." );
			}
		}
	}
	$codes = array();
	foreach ( $carousels as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_nc( array( 'layout:pro.approx:infinite', 'autoplay:pro.approx:offset_sides', 'css:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped ni pro.dropped; sólo las aproximaciones declaradas y el CSS: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_nc( 'Carousel' === $d['carousel_name'] && 'yes' === $d['autoplay'] && 'yes' === $d['infinite'] && array( 'Slide #1', 'Slide #2', 'Slide #3' ) === array_column( $d['carousel_items'], 'slide_title' ) && 'nested-carousel' === $d['_digitalisimo_pro'], 'Un carrusel sin cambios llega con los valores visibles de PRO y sus tres filas.' );
	$l = $out['layout'];
	check_nc( '1' === $l['slides_to_show'] && '1' === $l['slides_to_show_mobile'] && '' === $l['equal_height'] && '' === $l['autoplay'] && '' === $l['infinite'] && 800 === $l['speed'] && 'rtl' === $l['direction'] && 12 === $l['image_spacing_custom_tablet']['size'] && array( 'it1', 'it2', 'it3' ) === array_column( $l['carousel_items'], '_id' ), 'Diseño y ajustes, por dispositivo, y las filas con su _id.' );
	check_nc( 3000 === $out['autoplay']['autoplay_speed'] && 'both' === $out['autoplay']['offset_sides'] && 20 === $out['autoplay']['offset_width_mobile']['size'], 'Autoplay y asomo lateral.' );
	check_nc( '#eeeeee' === $out['style']['content_background_color'] && 'solid' === $out['style']['content_border_border'] && '8' === $out['style']['border_radius']['top'] && '8' === $out['style']['content_padding_mobile']['top'], 'Estilo de las diapositivas.' );
	$a = $out['arrows'];
	check_nc( 'fas fa-chevron-left' === $a['navigation_previous_icon']['value'] && 'center' === $a['navigation_next_icon_horizontal_orientation_mobile'] && -10 === $a['navigation_next_icon_vertical_position_tablet']['size'] && 'gradient' === $a['arrows_hover_background_background'] && 'yes' === $a['arrows_normal_box_shadow_box_shadow_type'] && 'outside' === $a['arrows_position'] && '#ffd166' === $a['arrow_hover_color'], 'Flechas: iconos, posiciones por dispositivo, colores, fondos, bordes, sombras y posición.' );
	check_nc( 'inside' === $out['dots']['dots_position'] && 'yes' === $out['dots']['dots_custom_position'] && 4 === $out['dots']['dots_vertical_offset']['size'] && 20 === $out['fraction']['typography_fraction_font_size']['size'] && 'outside' === $out['fraction']['fraction_position'] && 6 === $out['progress']['progressbar_height']['size'], 'Puntos, fracción y barra con sus posiciones.' );
	check_nc( array( 'arrow_normal_color' => 'globals/colors?id=primary', 'arrow_hover_color' => 'globals/colors?id=accent', 'dots_normal_color' => 'globals/colors?id=secondary', 'fraction_color' => 'globals/colors?id=text', 'progressbar_normal_color' => 'globals/colors?id=accent', 'content_background_color' => 'globals/colors?id=secondary', 'content_border_color' => 'globals/colors?id=text', 'arrows_normal_background_color' => 'globals/colors?id=primary', 'typography_fraction_typography' => 'globals/typography?id=secondary', '_background_color' => 'globals/colors?id=text' ) === $out['globals']['__globals__'], 'Colores y tipografías globales del kit siguen a su control.' );
	check_nc( array( 'carousel_name' => '[elementor-tag id="t2" name="site-title" settings="%7B%7D"]' ) === $out['dynamic']['__dynamic__'] && array( 'slide_title' => '[elementor-tag id="t1" name="post-title" settings="%7B%7D"]' ) === $out['dynamic']['carousel_items'][0]['__dynamic__'] && 'd1' === $out['dynamic']['carousel_items'][0]['_id'], 'Las etiquetas dinámicas siguen a su control, también en las filas.' );
	check_nc( "selector .digi-ncarousel{padding:4px}\nselector .digi-crs__slide > .e-con{color:red}\nselector .digi-crs__next svg{fill:red}\n.elementor-widget-digitalisimo-nested-carousel .digi-crs__dot{opacity:1}" === $out['css']['custom_css'] || ! is_file( $root . 'modules/digitalisimo-convert/maps/_carousel-classes.php' ), 'CSS personalizado con las clases propias: ' . json_encode( $out['css']['custom_css'] ) );
	check_nc( 'mi-carrusel' === $out['css']['_css_classes'] && 'novedades' === $out['css']['_element_id'] && 'yes' === $out['css']['motion_fx_motion_fx_scrolling'] && 'hidden-mobile' === $out['css']['hide_mobile'], 'Avanzado intacto.' );
	check_nc( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );
	// Lo convertido se pinta.
	$painted = $render( $out['layout'], $children( 3 ) );
	check_nc( str_contains( $painted, 'aria-label="Carousel"' ) && str_contains( $painted, 'dir="rtl"' ) && ! str_contains( $painted, 'data-digi-loop' ) && ! str_contains( $painted, 'digi-crs__pause' ) && 3 === substr_count( $painted, 'digi-crs__dot"' ), 'El carrusel convertido se pinta con sus ajustes.' );
	$painted = $render( $out['defaults'], $children( 3 ) );
	check_nc( str_contains( $painted, 'aria-label="Carousel"' ) && str_contains( $painted, 'data-digi-autoplay="5000"' ) && str_contains( $painted, 'data-digi-loop="1"' ), 'El carrusel por defecto convertido conserva el nombre y el autoplay de PRO.' );

	echo 'DIGITALÍSIMO Elements: Carrusel anidado (' . count( $own->controls ) . ' controles, ' . count( $own->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B) y su mapa desde el carrusel anidado de PRO (' . count( $cases ) . " casos con hijos) validados.\n";
	if ( $trait_notes ) {
		echo 'Aviso (trait del frente E, no bloquea): detalles del panel distintos de PRO en ' . implode( ', ', $trait_notes ) . ".\n";
	}
}
