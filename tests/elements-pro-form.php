<?php
/**
 * Formulario (`digitalisimo-form`), C6 (docs/pro/c6.md): el widget propio que sustituye al Form de PRO, su anfitrión de
 * acciones (`Action_Host`), el reenvío de los ganchos de sección y su mapa `modules/digitalisimo-convert/maps/form.php`.
 * - registro y recursos (hoja ≤ 4 KB y script ≤ 6 KB, sin `--e-global-*`, sin jQuery como dependencia; guarda de
 *   build-packages.sh);
 * - controles: se ejecutan los `register_controls()` de PRO y del propio con los mismos dobles y se comparan uno a uno
 *   (también los de cada campo del repetidor): nombre, orden, tipo, valor por defecto, opciones, condiciones,
 *   dispositivos, unidades, rangos, selectores (los de los pasos, con las clases y variables propias), grupos, secciones;
 * - acciones: una acción corriente y una integración que exige el tipo del widget de PRO registran sus controles en el
 *   propio por `Action_Host`; una acción que falla no deja su sección abierta; `on_export()` deja a cada acción quitar
 *   sus secretos;
 * - el reenvío de `elementor/element/form/<sección>/<fase>` al propio;
 * - marcado del servidor: mismos nombres y parámetros del envío que PRO (`post_id`, `form_id`, `queried_id`,
 *   `referer_title`, `form_fields[…]`), los filtros y acciones de PRO por campo, radios y casillas en un grupo con nombre,
 *   desplegables, pasos pintados en el servidor (indicadores, barra, botones, región viva), validación propia;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos con acciones, `__globals__`,
 *   `__dynamic__`, dispositivos, CSS personalizado con clases de los pasos): ningún `pro.unmapped`.
 *
 * Uso: php-wasm-cli tests/elements-pro-form.php. php-wasm sale con 0 aunque haya un error fatal: se juzga por la línea final.
 */
namespace Elementor {
	class Controls_Stack {}
	class Widget_Base extends Controls_Stack {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $tabs     = array();
		public $attrs    = array();
		public $order    = array();
		public $current  = null;
		public $id       = 'f0r1d2';
		public function get_id() { return $this->id; }
		public function get_unique_name() { return $this->get_name(); }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return $this->get_settings_for_display( $key ); }
		public function get_current_section() { return $this->current; }
		public function get_current_tab() { return null; }
		public function start_controls_section( $name, array $args = array() ) { $this->sections[ $name ] = $args; $this->current = $name; $this->order[] = 'section:' . $name; }
		public function end_controls_section() { $this->current = null; }
		public function start_controls_tabs( $name, array $args = array() ) { $this->tabs[] = $name; }
		public function start_controls_tab( $name, $args ) { $this->tabs[] = $name; }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, array $args, $options = array() ) { $this->controls[ $name ] = $args + array( '_section' => $this->current ); $this->order[] = $name; return true; }
		public function add_responsive_control( $name, array $args, $options = array() ) { return $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, array $args = array(), array $options = array() ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; $this->order[] = 'group:' . $args['name']; }
		public function update_control( $name, array $args, array $options = array() ) { $this->controls[ $name ] = array_replace_recursive( $this->controls[ $name ] ?? array(), $args ); return true; }
		public function remove_control( $name ) { unset( $this->controls[ $name ] ); return true; }
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function add_render_attribute( $element, $key = null, $value = null, $overwrite = false ) {
			if ( is_array( $element ) ) {
				foreach ( $element as $el => $pairs ) {
					foreach ( $pairs as $k => $v ) {
						$this->add_render_attribute( $el, $k, $v );
					}
				}
				return $this;
			}
			if ( is_array( $key ) ) {
				foreach ( $key as $k => $v ) {
					$this->add_render_attribute( $element, $k, $v, $overwrite );
				}
				return $this;
			}
			if ( $overwrite ) {
				unset( $this->attrs[ $element ][ $key ] );
			}
			$this->attrs[ $element ][ $key ] = $this->attrs[ $element ][ $key ] ?? array();
			foreach ( (array) $value as $v ) {
				$this->attrs[ $element ][ $key ][] = $v;
			}
			return $this;
		}
		public function set_render_attribute( $element, $key, $value ) { return $this->add_render_attribute( $element, $key, $value, true ); }
		public function remove_render_attribute( $element, $key = null ) { unset( $this->attrs[ $element ][ $key ] ); return $this; }
		public function get_render_attribute_string( $element ) {
			$out = array();
			foreach ( $this->attrs[ $element ] ?? array() as $k => $values ) {
				$values = array_filter( array_map( 'strval', $values ), 'strlen' );
				$out[]  = $values || 'class' === $k ? $k . '="' . htmlspecialchars( implode( ' ', $values ), ENT_QUOTES, 'UTF-8' ) . '"' : $k;
			}
			return implode( ' ', $out );
		}
		public function print_render_attribute_string( $element ) { echo $this->get_render_attribute_string( $element ); }
	}
	class Repeater extends Widget_Base { public function get_name() { return 'repeater'; } }
	class Control_Repeater {}
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SELECT2 = 'select2'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const URL = 'url'; const DIMENSIONS = 'dimensions'; const HOVER_ANIMATION = 'hover_animation'; const RAW_HTML = 'raw_html'; const NUMBER = 'number'; const HIDDEN = 'hidden'; const ICONS = 'icons'; const NOTICE = 'notice';
		const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Icons_Manager {
		public static function render_icon( $icon, $attrs = array() ) { echo '<svg class="icono ' . htmlspecialchars( (string) ( $icon['value'] ?? '' ) ) . '" aria-hidden="true"></svg>'; return true; }
		public static function is_migration_allowed() { return true; }
	}
	class Utils { public static function is_empty( $v ) { return '' === $v || null === $v; } }
	class Plugin {
		public static $instance;
		public $editor;
		public $experiments;
		public $documents;
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
	class Markdown_Utils {}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace ElementorPro\Modules\Forms {
	class Module {
		public $actions_registrar;
		private static $one;
		public static function instance() {
			if ( ! self::$one ) {
				self::$one                    = new self();
				self::$one->actions_registrar = new class() { public $list = array(); public function get() { return $this->list; } };
			}
			return self::$one;
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'ELEMENTOR_VERSION', '3.30.0' );
	$GLOBALS['fm'] = array( 'edit' => false, 'singular' => true, 'filters' => array(), 'actions' => array(), 'did' => array() );
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function is_rtl() { return false; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_textarea( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_kses_post( $v ) { return preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ); }
	function do_shortcode( $v ) { return str_replace( '[año]', '2026', (string) $v ); }
	function admin_url( $p ) { return 'https://sitio.test/wp-admin/' . $p; }
	function wp_title( $s = '', $d = true ) { return ' Contacto '; }
	function is_home() { return false; }
	function is_singular() { return $GLOBALS['fm']['singular']; }
	function get_the_ID() { return 42; }
	function get_option( $n ) { return 'blogname' === $n ? 'Sitio' : false; }
	function add_action( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['fm']['actions'][ $hook ][] = $cb; }
	function add_filter( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['fm']['filters'][ $hook ][] = $cb; }
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['fm']['filters'][ $hook ] ?? array() as $cb ) {
			$value = $cb( $value, ...$args );
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['fm']['did'][] = $hook;
		foreach ( $GLOBALS['fm']['actions'][ $hook ] ?? array() as $cb ) {
			$cb( ...$args );
		}
	}
	function check_fm( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }

	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor      = new class() { public function is_edit_mode() { return $GLOBALS['fm']['edit']; } };
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return false; } };
	\Elementor\Plugin::$instance->documents   = new class() { public function get_current() { return new class() { public function get_main_id() { return 77; } }; } };

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/forms/controls/fields-repeater.php';
	require $root . 'modules/forms/classes/ajax-handler.php';
	require $root . 'modules/forms/classes/form-base.php';
	require $root . 'modules/forms/widgets/form.php';
	require $root . 'modules/digitalisimo-forms/class-module.php';
	\Digitalisimo\Elements\Forms\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Forms\Module as Forms;
	use Digitalisimo\Elements\Forms\Widgets\Form;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Translator;

	$render = static function ( $widget, array $settings ) {
		// Como el núcleo: cada campo del repetidor recibe los valores por defecto de sus controles.
		if ( isset( $settings['form_fields'], $widget->controls['form_fields']['fields'] ) ) {
			$row = array();
			foreach ( $widget->controls['form_fields']['fields'] as $field => $args ) {
				$row[ $field ] = $args['default'] ?? '';
			}
			foreach ( $settings['form_fields'] as $i => $item ) {
				$settings['form_fields'][ $i ] = $item + $row;
			}
		}
		$widget->settings = $settings;
		$widget->attrs    = array();
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	$name  = 'digitalisimo-form';

	// 1. Registro y recursos.
	check_fm( Form::class === Forms::load( $name ), 'El módulo de formularios carga el Formulario.' );
	$widget = new Form();
	check_fm( $name === $widget->get_name() && 'Formulario' === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories() && 'Formulario' === Forms::TITLES[ $name ], 'Nombre, título y categoría propios.' );
	check_fm( array( $name ) === $widget->get_style_depends() && array( $name ) === $widget->get_script_depends() && $widget->has_widget_inner_wrapper(), 'Su hoja, su script y el envoltorio interior como PRO.' );
	$css = (string) file_get_contents( $root . Forms::WIDGETS[ $name ]['css'] );
	$js  = (string) file_get_contents( $root . Forms::WIDGETS[ $name ]['js'] );
	$ed  = (string) file_get_contents( $root . 'modules/digitalisimo-forms/assets/js/form-editor.js' );
	check_fm( strlen( $css ) <= 4096 && strlen( $js ) <= 6144, 'Hoja de ' . strlen( $css ) . ' B (tope 4 096) y script de ' . strlen( $js ) . ' B (tope 6 144).' );
	check_fm( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && str_contains( $css, 'outline:2px solid;outline-offset:2px' ) && str_contains( $css, 'prefers-reduced-motion' ), 'La hoja: sin el kit ni url(), con foco visible y movimiento reducido.' );
	check_fm( ! preg_match( '/\$\(|jQuery\.(ajax|fn)|\.ajax\(|swiper|elementorProFrontend\.config/i', $js ) && str_contains( $js, 'fetch(' ) && str_contains( $js, 'new FormData(f)' ) && str_contains( $js, "fd.append('action'" ) && str_contains( $js, "fd.append('referrer'" ), 'El script envía con fetch y FormData (acción y referrer como PRO), sin depender de jQuery.' );
	check_fm( str_contains( $js, 'if(busy)return;busy=1' ), 'Un envío en curso bloquea otro.' );
	foreach ( array( 'modules/digitalisimo-forms/widgets/class-form.php', 'modules/digitalisimo-forms/class-action-host.php', 'modules/digitalisimo-forms/class-module.php', Forms::WIDGETS[ $name ]['css'], Forms::WIDGETS[ $name ]['js'], 'modules/digitalisimo-forms/assets/js/form-editor.js', 'modules/digitalisimo-convert/maps/form.php' ) as $file ) {
		check_fm( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file pasa la guarda de build-packages.sh." );
	}
	check_fm( str_contains( $ed, "'panel/open_editor/widget/form'" ) && str_contains( $ed, 'new module.constructor( NAME )' ), 'El script del editor ata las ayudas del panel de PRO al propio.' );

	// 2. Acciones de prueba: una corriente, una integración que exige el widget de PRO y una que falla a mitad.
	$registrar       = \ElementorPro\Modules\Forms\Module::instance()->actions_registrar;
	$registrar->list = array(
		new class() {
			public function get_name() { return 'webhook'; }
			public function get_label() { return 'Webhook'; }
			public function register_settings_section( $widget ) {
				$widget->start_controls_section( 'section_webhook', array( 'label' => 'Webhook', 'condition' => array( 'submit_actions' => 'webhook' ) ) );
				$widget->add_control( 'webhooks', array( 'type' => 'text', 'default' => '' ) );
				$widget->end_controls_section();
			}
			public function on_export( $element ) { unset( $element['settings']['webhooks'] ); return $element; }
		},
		new class() {
			public function get_name() { return 'mailchimp'; }
			public function get_label() { return 'MailChimp'; }
			public function register_settings_section( $widget ) {
				$widget->start_controls_section( 'section_mailchimp', array( 'label' => 'MailChimp' ) );
				$widget->add_control( 'mailchimp_list', array( 'type' => 'select', 'default' => '' ) );
				$this->map( $widget );
				$widget->end_controls_section();
			}
			private function map( \ElementorPro\Modules\Forms\Widgets\Form $form ) { $form->add_control( 'mailchimp_fields_map', array( 'type' => 'fields_map', 'default' => array() ) ); }
			public function on_export( $element ) { return null; }
		},
		new class() {
			public function get_name() { return 'roto'; }
			public function get_label() { return 'Roto'; }
			public function register_settings_section( $widget ) {
				$widget->start_controls_section( 'section_roto', array() );
				throw new \Exception( 'falla' );
			}
			public function on_export( $element ) { return null; }
		},
	);
	// Un tipo de campo engancha `elementor/element/form/section_form_fields/before_section_end` (como los de PRO y F7b).
	add_action(
		'elementor/element/form/section_form_fields/before_section_end',
		static function ( $w ) {
			$w->update_control( 'form_fields', array( 'fields' => array( 'min_date' => array( 'name' => 'min_date', 'type' => 'date' ) ) ) );
		}
	);

	// 3. Controles de PRO, uno a uno.
	$broken          = array_pop( $registrar->list );
	$pro             = new \ElementorPro\Modules\Forms\Widgets\Form();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
	$registrar->list[] = $broken; // El de PRO no sobrevive a una acción rota: sólo el propio la recibe.
	$own = new Form();
	( new ReflectionMethod( $own, 'register_controls' ) )->invoke( $own );
	// En el núcleo, el gancho genérico de sección llega a `Module::forward()`: aquí se llama como lo haría el núcleo.
	Forms::forward_before_section_end( $own, 'section_form_fields', array() );
	Forms::forward_before_section_end( $pro, 'section_form_fields', array() );
	check_fm( in_array( 'elementor/element/form/section_form_fields/before_section_end', $GLOBALS['fm']['did'], true ) && 1 === count( array_keys( $GLOBALS['fm']['did'], 'elementor/element/form/section_form_fields/before_section_end', true ) ), 'El gancho de sección del widget de PRO llega al propio (y no se duplica para el de PRO).' );
	check_fm( isset( $own->controls['form_fields']['fields']['min_date'] ), 'El control que añade un tipo de campo llega al repetidor del propio.' );
	unset( $own->controls['form_fields']['fields']['min_date'] );
	check_fm( isset( $own->controls['webhooks'], $own->controls['mailchimp_list'], $own->controls['mailchimp_fields_map'] ) && 'section_mailchimp' === $own->controls['mailchimp_fields_map']['_section'], 'Las acciones registran sus controles en el propio; la integración que exige el widget de PRO, por Action_Host.' );
	check_fm( isset( $own->sections['section_roto'] ) && ! isset( $own->controls['roto'] ) && 'section:section_steps_settings' === $own->order[ array_search( 'section:section_roto', $own->order, true ) + 1 ], 'Una acción que falla no deja su sección abierta y el resto sigue.' );
	$steps_map = array(
		'.e-form__indicators__indicator__progress__meter' => '.digi-steps__meter',
		'.e-form__indicators__indicator__label'           => '.digi-step__label',
		'.e-form__indicators__indicator'                  => '.digi-step',
		'--e-form-steps-indicators-spacing'               => '--dfs-gap',
		'--e-form-steps-indicator-icon-size'              => '--dfs-icon',
		'--e-form-steps-indicator-padding'                => '--dfs-pad',
		'--e-form-steps-indicator-inactive-primary-color' => '--dfs-i1',
		'--e-form-steps-indicator-inactive-secondary-color' => '--dfs-i2',
		'--e-form-steps-indicator-active-primary-color'   => '--dfs-a1',
		'--e-form-steps-indicator-active-secondary-color' => '--dfs-a2',
		'--e-form-steps-indicator-completed-primary-color' => '--dfs-c1',
		'--e-form-steps-indicator-completed-secondary-color' => '--dfs-c2',
		'--e-form-steps-divider-width'                    => '--dfs-dw',
		'--e-form-steps-divider-gap'                      => '--dfs-dg',
		'--e-form-steps-indicator-progress-color'         => '--dfs-p',
		'--e-form-steps-indicator-progress-background-color' => '--dfs-pb',
		'--e-form-steps-indicator-progress-height'        => '--dfs-ph',
		'--e-form-steps-indicator-progress-border-radius' => '--dfs-pr',
		'--e-form-steps-indicator-progress-meter-color'   => '--dfs-pm',
	);
	$own_sel = static function ( $value ) use ( $steps_map ) {
		if ( is_string( $value ) ) {
			return Translator::rewrite_classes( str_replace( array_keys( array_filter( $steps_map, static function ( $k ) { return 0 === strpos( $k, '--' ); }, ARRAY_FILTER_USE_KEY ) ), array_values( array_filter( $steps_map, static function ( $k ) { return 0 === strpos( $k, '--' ); }, ARRAY_FILTER_USE_KEY ) ), $value ), array_filter( $steps_map, static function ( $k ) { return 0 === strpos( $k, '.' ); }, ARRAY_FILTER_USE_KEY ) );
		}
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ is_string( $k ) ? $GLOBALS['own_sel']( $k ) : $k ] = $GLOBALS['own_sel']( $v );
			}
			return $out;
		}
		return $value;
	};
	$GLOBALS['own_sel'] = $own_sel;
	$compare = static function ( array $a, array $b, string $where, bool $repeater = false ) use ( $own_sel ) {
		foreach ( array( 'type', 'default', 'condition', 'conditions', 'responsive', 'size_units', 'range', 'tablet_default', 'mobile_default', 'label_block', 'separator', 'render_type', 'frontend_available', 'return_value', 'placeholder', 'global', 'prefix_class', 'selectors', 'selector', 'selectors_dictionary', 'dynamic', 'ai', 'multiple', 'min', 'step', 'skin', 'classes', 'title_field', 'inner_tab', 'tabs_wrapper', 'exclude', 'fields_options' ) as $key ) {
			$va = $a[ $key ] ?? null;
			$vb = $b[ $key ] ?? null;
			if ( in_array( $key, array( 'selectors', 'selector' ), true ) ) {
				$va = $own_sel( $va );
			}
			if ( in_array( $key, array( 'default', 'placeholder' ), true ) && is_string( $va ) && preg_match( '/[A-Za-z]{3}/', $va ) && ! in_array( $key, array( 'placeholder' ), true ) === false ) {
				check_fm( is_string( $vb ) && '' !== $vb, "$where: «{$key}» propio en español ($vb)." );
				continue;
			}
			if ( 'default' === $key && is_string( $va ) && preg_match( '/^[A-Z][a-z]/', $va ) ) {
				check_fm( is_string( $vb ) && '' !== $vb && $va !== $vb, "$where: texto por defecto propio, en español ($vb)." );
				continue;
			}
			if ( 'default' === $key && is_array( $va ) && isset( $va[0]['field_label'] ) ) {
				check_fm( count( $va ) === count( $vb ) && array_column( $va, 'custom_id' ) === array_column( $vb, 'custom_id' ) && array_column( $va, 'field_type' ) === array_column( $vb, 'field_type' ) && array_column( $va, 'required' ) === array_column( $vb, 'required' ), "$where: las mismas filas por defecto." );
				continue;
			}
			check_fm( $va === $vb, "$where: «{$key}» distinto de PRO (" . json_encode( $va ) . ' / ' . json_encode( $vb ) . ').' );
		}
		check_fm( array_keys( (array) ( $a['options'] ?? array() ) ) === array_keys( (array) ( $b['options'] ?? array() ) ) && isset( $a['options'] ) === isset( $b['options'] ), "$where: las mismas opciones." );
	};
	check_fm( array_keys( $pro->controls ) === array_keys( $own->controls ), 'Los mismos controles en el mismo orden: ' . implode( ',', array_diff( array_keys( $pro->controls ), array_keys( $own->controls ) ) ) . ' / ' . implode( ',', array_diff( array_keys( $own->controls ), array_keys( $pro->controls ) ) ) );
	foreach ( $pro->controls as $control => $a ) {
		$b = $own->controls[ $control ];
		unset( $b['options']['roto'] ); // La acción rota sólo la recibe el propio.
		$compare( $a, $b, $control );
	}
	$pro_fields = $pro->controls['form_fields']['fields'];
	$own_fields = $own->controls['form_fields']['fields'];
	check_fm( array_keys( $pro_fields ) === array_keys( $own_fields ) && count( $own_fields ) >= 18, 'Los mismos controles en cada campo del repetidor (' . count( $own_fields ) . ').' );
	foreach ( $pro_fields as $field => $a ) {
		$compare( $a, $own_fields[ $field ], "form_fields.$field", true );
	}
	check_fm( 'form-fields-repeater' === $own->controls['form_fields']['type'] && \ElementorPro\Modules\Forms\Controls\Fields_Repeater::CONTROL_TYPE === Form::FIELDS_CONTROL, 'El repetidor usa el control de campos del constructor.' );
	check_fm( array_keys( $pro->groups ) === array_keys( $own->groups ), 'Los mismos grupos en el mismo orden.' );
	foreach ( $pro->groups as $group => $a ) {
		$b = $own->groups[ $group ];
		$a['selector'] = $own_sel( $a['selector'] ?? null );
		check_fm( array_diff_key( $a, array( 'selector' => 1 ) ) === array_diff_key( $b, array( 'selector' => 1 ) ) && $a['selector'] === ( $b['selector'] ?? null ), "$group: el grupo es el de PRO (con las clases propias de los pasos)." );
	}
	check_fm( array_keys( $pro->sections ) === array_values( array_diff( array_keys( $own->sections ), array( 'section_roto' ) ) ) && $pro->tabs === $own->tabs, 'Las mismas secciones y pestañas.' );
	check_fm( array( 'webhook', 'mailchimp', 'roto' ) === array_keys( $own->controls['submit_actions']['options'] ) && array( 'email' ) === $own->controls['submit_actions']['default'], 'Las acciones del registro en «Añadir acción», con el mismo valor por defecto.' );

	// 4. on_export: cada acción quita sus secretos.
	$exported = $own->on_export( array( 'settings' => array( 'webhooks' => 'https://secreto.test/hook', 'form_name' => 'X' ) ) );
	check_fm( ! isset( $exported['settings']['webhooks'] ) && 'X' === $exported['settings']['form_name'], 'on_export(): la URL del webhook no viaja en el kit.' );

	// 5. Marcado del servidor.
	$defaults = array();
	foreach ( $own->controls as $control => $args ) {
		if ( array_key_exists( 'default', $args ) ) {
			$defaults[ $control ] = $args['default'];
		}
	}
	$seen = array();
	add_filter( 'elementor_pro/forms/render/item', static function ( $item, $i, $w ) use ( &$seen ) { $seen[] = array( $item['custom_id'], $i, get_class( $w ) ); return $item; } );
	add_action( 'elementor_pro/forms/render_field/date', static function ( $item, $i, $w ) { $w->add_render_attribute( 'input' . $i, 'class', 'elementor-field-textual elementor-date-field' ); echo '<input ' . $w->get_render_attribute_string( 'input' . $i ) . '>'; } );
	$html = $render( $own, $defaults );
	check_fm( str_contains( $html, '<form class="elementor-form" method="post" data-digi-form="elementor_pro_forms_send_form" data-digi-ajax="https://sitio.test/wp-admin/admin-ajax.php" data-digi-error="No se pudo enviar el formulario por un error del servidor." name="Formulario nuevo" aria-label="Formulario nuevo">' ), 'El formulario: acción del manejador de PRO, admin-ajax.php del sitio y su nombre: ' . substr( $html, 0, 400 ) );
	check_fm( str_contains( $html, '<input type="hidden" name="post_id" value="77"/>' ) && str_contains( $html, '<input type="hidden" name="form_id" value="f0r1d2"/>' ) && str_contains( $html, '<input type="hidden" name="referer_title" value="Contacto" />' ) && str_contains( $html, '<input type="hidden" name="queried_id" value="42"/>' ), 'Los mismos parámetros ocultos que PRO (documento, id del elemento, título y entrada consultada).' );
	check_fm( str_contains( $html, '<div class="elementor-form-fields-wrapper elementor-labels-above">' ) && str_contains( $html, '<div class="elementor-field-type-text elementor-field-group elementor-column elementor-field-group-name elementor-col-100">' ), 'Grupos con las clases de formulario del núcleo.' );
	check_fm( str_contains( $html, '<label for="form-field-name" class="elementor-field-label">Nombre</label><input size="1" type="text" name="form_fields[name]" id="form-field-name" class="elementor-field elementor-size-sm elementor-field-textual" placeholder="Nombre">' ), 'Campo de texto: etiqueta asociada y nombre form_fields[name]: ' . $html );
	check_fm( str_contains( $html, 'type="email" name="form_fields[email]" id="form-field-email"' ) && str_contains( $html, 'elementor-field-required' ) && str_contains( $html, 'required="required"' ), 'Correo obligatorio.' );
	check_fm( str_contains( $html, '<textarea class="elementor-field-textual elementor-field elementor-size-sm" name="form_fields[message]" id="form-field-message" rows="4" placeholder="Mensaje"></textarea>' ), 'Área de texto.' );
	check_fm( str_contains( $html, '<div class="elementor-field-group elementor-column elementor-field-type-submit elementor-col-100 e-form__buttons"><button class="elementor-button elementor-size-sm" type="submit"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">Enviar</span></span></button></div>' ), 'Botón de envío con las clases del núcleo.' );
	check_fm( array( array( 'name', 0, Form::class ), array( 'email', 1, Form::class ), array( 'message', 2, Form::class ) ) === $seen, 'El filtro de PRO por campo recibe el campo, su índice y el widget propio.' );
	check_fm( ! str_contains( $html, 'data-digi-steps' ) && ! str_contains( $html, 'digi-steps' ) && in_array( 'elementor-pro/forms/pre_render', $GLOBALS['fm']['did'], true ), 'Sin pasos, nada de pasos; la acción previa al pintado de PRO.' );

	$fields = array(
		array( '_id' => 'a', 'custom_id' => 'tema', 'field_type' => 'radio', 'field_label' => 'Tema', 'field_options' => "Ventas|ventas\nSoporte|soporte", 'required' => 'true', 'field_value' => 'soporte', 'inline_list' => 'elementor-subgroup-inline', 'css_classes' => '', 'width' => '50', 'width_mobile' => '100' ),
		array( '_id' => 'b', 'custom_id' => 'extras', 'field_type' => 'checkbox', 'field_label' => '', 'field_options' => "Uno\nDos", 'required' => '', 'css_classes' => '', 'inline_list' => '', 'width' => '50' ),
		array( '_id' => 'c', 'custom_id' => 'pais', 'field_type' => 'select', 'field_label' => 'País', 'field_options' => "México|mx\nChile|cl", 'field_value' => 'cl', 'required' => '', 'allow_multiple' => '', 'css_classes' => 'extra', 'width' => '100' ),
		array( '_id' => 'd', 'custom_id' => 'fecha', 'field_type' => 'date', 'field_label' => 'Fecha', 'required' => '', 'css_classes' => '', 'width' => '100' ),
		array( '_id' => 'e', 'custom_id' => 'nota', 'field_type' => 'html', 'field_html' => '<p>Hola [año]</p>', 'field_label' => 'no se ve', 'css_classes' => '', 'width' => '100' ),
		array( '_id' => 'f', 'custom_id' => 'origen', 'field_type' => 'hidden', 'field_value' => 'web', 'field_label' => 'Origen', 'css_classes' => '', 'width' => '100' ),
	);
	$wide = $render( $own, array( 'form_fields' => $fields, 'show_labels' => '', 'mark_required' => 'true', 'form_validation' => 'custom', 'form_id' => 'contacto', 'button_text' => '', 'selected_button_icon' => array( 'value' => 'fas fa-paper-plane', 'library' => 'fa-solid' ), 'button_css_id' => 'enviar' ) + $defaults );
	check_fm( str_contains( $wide, 'id="contacto"' ) && str_contains( $wide, ' novalidate' ), 'ID del formulario y validación propia (novalidate).' );
	check_fm( str_contains( $wide, '<label class="elementor-field-label elementor-screen-only" id="form-field-tema-label">Tema</label><div class="elementor-field-subgroup  elementor-subgroup-inline" role="radiogroup" aria-labelledby="form-field-tema-label">' ), 'Radios: la etiqueta nombra el grupo (sin `for` que no apunta a nada): ' . $wide );
	check_fm( str_contains( $wide, '<input type="radio" value="soporte" id="form-field-tema-1" name="form_fields[tema]" checked="checked" required="required"> <label for="form-field-tema-1">Soporte</label>' ), 'Cada opción con su id, nombre, valor y etiqueta (como PRO).' );
	check_fm( str_contains( $wide, 'elementor-field-required elementor-mark-required' ) && str_contains( $wide, 'elementor-col-50 elementor-sm-100' ), 'Marca de obligatorio y anchos por dispositivo.' );
	check_fm( str_contains( $wide, 'role="group"><span class="elementor-field-option"><input type="checkbox" value="Uno" id="form-field-extras-0" name="form_fields[extras][]">' ), 'Casillas: grupo y nombre con [] para varias opciones.' );
	check_fm( str_contains( $wide, '<select name="form_fields[pais]" id="form-field-pais" class="elementor-field-textual elementor-size-sm"><option value="mx">México</option><option value="cl" selected="selected">Chile</option></select>' ) && str_contains( $wide, 'class="elementor-field elementor-select-wrapper remove-before extra"><div class="select-caret-down-wrapper"><svg class="icono eicon-caret-down"' ), 'Desplegable con su flecha y la opción elegida.' );
	check_fm( str_contains( $wide, 'name="form_fields[fecha]" id="form-field-fecha" class="elementor-field elementor-size-sm  elementor-field-textual elementor-date-field">' ) || str_contains( $wide, 'class="elementor-field elementor-size-sm elementor-field-textual elementor-date-field"' ), 'Un tipo de campo registrado se pinta con la acción de PRO: ' . $wide );
	check_fm( str_contains( $wide, '<p>Hola 2026</p>' ) && ! str_contains( $wide, 'no se ve' ) && str_contains( $wide, 'type="hidden" name="form_fields[origen]"' ) && ! str_contains( $wide, '>Origen<' ), 'HTML con códigos cortos y oculto sin etiqueta.' );
	check_fm( str_contains( $wide, '<button class="elementor-button elementor-size-sm" type="submit" id="enviar">' ) && str_contains( $wide, '<span class="elementor-screen-only">Enviar</span>' ), 'Sin texto, el botón conserva su nombre accesible.' );

	$steps = array(
		array( '_id' => 's1', 'custom_id' => 'p1', 'field_type' => 'step', 'field_label' => 'Datos', 'previous_button' => '', 'next_button' => 'Continuar', 'selected_icon' => array( 'value' => '', 'library' => '' ), 'css_classes' => '', 'width' => '100' ),
		array( '_id' => 'n1', 'custom_id' => 'nombre', 'field_type' => 'text', 'field_label' => 'Nombre', 'required' => 'true', 'css_classes' => '', 'width' => '100', 'placeholder' => '' ),
		array( '_id' => 's2', 'custom_id' => 'p2', 'field_type' => 'step', 'field_label' => 'Contacto', 'previous_button' => '', 'next_button' => '', 'selected_icon' => array( 'value' => '', 'library' => '' ), 'css_classes' => '', 'width' => '100' ),
		array( '_id' => 'n2', 'custom_id' => 'correo', 'field_type' => 'email', 'field_label' => 'Correo', 'required' => '', 'css_classes' => '', 'width' => '100', 'placeholder' => '' ),
		array( '_id' => 's3', 'custom_id' => 'p3', 'field_type' => 'step', 'field_label' => 'Fin', 'previous_button' => 'Volver', 'next_button' => '', 'selected_icon' => array( 'value' => '', 'library' => '' ), 'css_classes' => '', 'width' => '100' ),
		array( '_id' => 'n3', 'custom_id' => 'msg', 'field_type' => 'textarea', 'field_label' => 'Mensaje', 'required' => '', 'css_classes' => '', 'width' => '100', 'placeholder' => '', 'rows' => 3 ),
	);
	$stepped = $render( $own, array( 'form_fields' => $steps, 'button_width_mobile' => '50' ) + $defaults );
	check_fm( str_contains( $stepped, 'data-digi-steps="3"' ) && str_contains( $stepped, '<div class="digi-steps digi-steps--badge" aria-hidden="true"><div class="digi-step digi-step--on"><div class="digi-step__badge digi-step__badge--circle">1</div><span class="digi-step__label">Datos</span></div><div class="digi-step-sep"></div><div class="digi-step digi-step--off">' ), 'Indicadores de número y texto pintados en el servidor: ' . $stepped );
	check_fm( 3 === substr_count( $stepped, ' e-form__step"' ) && str_contains( $stepped, 'role="group" aria-label="Paso 1 de 3: Datos" data-digi-step="0">' ) && str_contains( $stepped, 'aria-label="Paso 2 de 3: Contacto" data-digi-step="1" hidden>' ), 'Cada paso es un grupo con nombre; sólo el primero visible.' );
	check_fm( str_contains( $stepped, '<div class="e-form__buttons elementor-column elementor-col-100 elementor-sm-50"><div class="elementor-field-group e-form__buttons__wrapper elementor-field-type-next"><button type="button" class="elementor-button elementor-size-sm e-form__buttons__wrapper__button e-form__buttons__wrapper__button-next" data-direction="next">Continuar</button></div></div></div>' ), 'Primer paso: «siguiente» con el texto del paso.' );
	check_fm( str_contains( $stepped, 'data-direction="previous">Anterior</button></div><div class="elementor-field-group e-form__buttons__wrapper elementor-field-type-next"><button type="button" class="elementor-button elementor-size-sm e-form__buttons__wrapper__button e-form__buttons__wrapper__button-next" data-direction="next">Siguiente</button>' ), 'Paso intermedio: «anterior» y «siguiente» con los textos del widget.' );
	check_fm( str_contains( $stepped, 'data-direction="previous">Volver</button></div><div class="elementor-field-group elementor-field-type-submit e-form__buttons__wrapper"><button class="elementor-button elementor-size-sm e-form__buttons__wrapper__button" type="submit">' ) && 1 === substr_count( $stepped, 'type="submit"' ), 'Último paso: «anterior» y el envío (una sola vez).' );
	check_fm( str_contains( $stepped, '<div class="elementor-screen-only" aria-live="polite" data-digi-status></div></form>' ), 'Región viva que anuncia el paso.' );
	$bar = $render( $own, array( 'form_fields' => $steps, 'step_type' => 'progress_bar' ) + $defaults );
	check_fm( str_contains( $bar, '<div class="digi-steps"><div class="digi-steps__bar" style="--dfs-pw:33%" role="progressbar" aria-label="Progreso del formulario" aria-valuemin="0" aria-valuemax="100" aria-valuenow="33" aria-valuetext="Paso 1 de 3"><div class="digi-steps__meter">33%</div></div></div>' ), 'Barra de progreso con su valor anunciado.' );
	check_fm( ! str_contains( $render( $own, array( 'form_fields' => $steps, 'step_type' => 'none' ) + $defaults ), 'class="digi-steps' ), 'Sin indicadores con el tipo «ninguno».' );

	// La página de la prueba en Chrome (tests/elements-pro-form-chrome.mjs) es este mismo marcado.
	$fixture = (string) file_get_contents( __DIR__ . '/fixtures/pro-form.html' );
	foreach ( array( array( 'form_validation' => 'custom', 'form_id' => 'basico' ), array( 'form_fields' => $steps, 'form_id' => 'pasos' ), array( 'form_fields' => $steps, 'step_type' => 'progress_bar', 'form_id' => 'barra' ) ) as $variant ) {
		check_fm( str_contains( $fixture, $render( $own, $variant + $defaults ) ), 'tests/fixtures/pro-form.html tiene el marcado actual del formulario «' . $variant['form_id'] . '».' );
	}

	// 6. El mapa y la conversión.
	$map = Registry::map( 'form', true );
	check_fm( is_array( $map ) && $name === $map['target'] && 'hide' === $map['panel'], 'Mapa hacia el Formulario, que oculta el widget de PRO.' );
	foreach ( $map['keep'] as $control ) {
		check_fm( isset( $own->controls[ $control ] ) || preg_match( '/^(email_|form_metadata|redirect_to|webhooks|mailchimp_|drip_|tags|activecampaign_|getresponse_|convertkit_|mailerlite_|allow_resubscribe|mailpoet|slack_|discord_|submissions_|popup_action|akismet_)/', $control ), "form.$control: se conserva en un control propio o de una acción." );
	}
	foreach ( $map['defaults'] as $control => $value ) {
		$pro_default = $pro->controls[ $control ]['default'] ?? null;
		check_fm( $value === $pro_default, "form.$control: el valor por defecto del mapa es el de PRO ($value / $pro_default)." );
	}
	check_fm( array_column( $map['repeaters']['form_fields']['default_rows'], 'field_label' ) === array_column( $pro->controls['form_fields']['default'], 'field_label' ), 'Las filas por defecto del mapa son las de PRO.' );
	foreach ( array_keys( $own_fields ) as $field ) {
		check_fm( in_array( $field, $map['repeaters']['form_fields']['keep'], true ) || in_array( $field, $map['ui'], true ), "form_fields.$field: el mapa lo declara." );
	}
	$cases = array(
		'fm-defaults' => array(),
		'fm-full'     => array(
			'form_name' => 'Contacto', 'form_id' => 'contacto', 'form_validation' => 'custom', 'custom_messages' => 'yes', 'success_message' => '¡Gracias!', 'required_field_message' => 'Obligatorio',
			'form_fields' => array_merge( $fields, $steps, array( array( '_id' => 'u', 'custom_id' => 'cv', 'field_type' => 'upload', 'field_label' => 'CV', 'file_sizes' => 2, 'file_types' => 'pdf', 'allow_multiple_upload' => 'yes', 'max_files' => 2, 'width_tablet' => '50', 'digitalisimo_conditional' => 'yes', 'digitalisimo_rules' => array( array( 'field' => 'tema', 'operator' => '==', 'value' => 'ventas' ) ) ) ) ),
			'input_size' => 'md', 'show_labels' => '', 'mark_required' => 'true', 'button_size' => 'lg', 'button_width' => '50', 'button_width_tablet' => '100', 'button_text' => 'Mandar', 'button_align' => 'center', 'button_align_mobile' => 'stretch',
			'selected_button_icon' => array( 'value' => 'fas fa-paper-plane', 'library' => 'fa-solid' ), 'button_icon_align' => 'row-reverse', 'button_css_id' => 'enviar', 'step_type' => 'progress_bar', 'step_next_label' => 'Sigue',
			'submit_actions' => array( 'save-to-database', 'email', 'email2', 'webhook', 'redirect', 'mailchimp', 'popup' ),
			'email_to' => 'ventas@sitio.test', 'email_subject' => 'Nuevo [field id="nombre"]', 'email_content' => '[all-fields]', 'email_from' => 'web@sitio.test', 'email_from_name' => 'Sitio', 'email_reply_to' => 'email', 'email_to_cc' => '', 'email_to_bcc' => 'archivo@sitio.test', 'form_metadata' => array( 'date', 'time', 'page_url' ), 'email_content_type' => 'html',
			'email_to_2' => '[field id="email"]', 'email_subject_2' => 'Recibimos tu mensaje', 'email_content_2' => 'Gracias', 'form_metadata_2' => array(),
			'webhooks' => 'https://receptor.test/hook', 'webhooks_advanced_data' => 'yes', 'redirect_to' => 'https://sitio.test/gracias/', 'mailchimp_api_key_source' => 'default', 'mailchimp_list' => 'abc', 'mailchimp_fields_map' => array( array( '_id' => 'm1', 'remote_id' => 'EMAIL', 'local_id' => 'email' ) ), 'mailchimp_double_opt_in' => 'yes',
			'popup_action' => 'close', 'popup_action_do_not_show_again' => 'yes', 'submissions_metadata' => array( 'remote_ip', 'user_agent' ),
			'column_gap' => array( 'unit' => 'px', 'size' => 20 ), 'row_gap' => array( 'unit' => 'px', 'size' => 15 ), 'label_spacing' => array( 'unit' => 'px', 'size' => 4 ), 'label_color' => '#111111', 'mark_required_color' => '#ff0000',
			'label_typography_typography' => 'custom', 'label_typography_font_weight' => '600', 'field_text_color' => '#222222', 'field_background_color' => '#f5f5f5', 'field_border_color' => '#cccccc', 'field_border_width' => array( 'top' => '0', 'right' => '0', 'bottom' => '2', 'left' => '0', 'unit' => 'px', 'isLinked' => false ), 'field_border_radius_mobile' => array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => true ),
			'button_background_color' => '#0044ff', 'button_text_color' => '#ffffff', 'button_border_border' => 'solid', 'button_border_width' => array( 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px' ), 'button_border_color' => '#000000', 'button_background_hover_color' => '#002288', 'button_hover_animation' => 'grow', 'hover_transition_duration' => array( 'unit' => 'ms', 'size' => 300 ),
			'button_border_radius' => array( 'top' => '30', 'right' => '30', 'bottom' => '30', 'left' => '30', 'unit' => 'px' ), 'button_text_padding' => array( 'top' => '12', 'right' => '24', 'bottom' => '12', 'left' => '24', 'unit' => 'px' ), 'previous_button_background_color' => '#999999',
			'success_message_color' => '#008800', 'error_message_color' => '#880000', 'inline_message_color' => '#aa0000', 'message_typography_typography' => 'custom', 'message_typography_font_style' => 'italic',
			'steps_gap' => array( 'unit' => 'px', 'size' => 30 ), 'step_progress_bar_color' => '#00aa00', 'step_progress_bar_height' => array( 'unit' => 'px', 'size' => 24 ), 'step_progress_bar_percentage__typography_typography' => 'custom',
			'custom_css' => "selector .e-form__indicators__indicator--state-active .e-form__indicators__indicator__number{border-width:2px}\n.elementor-widget-form .elementor-field{box-shadow:none}\nselector .e-form__indicators__indicator__progress__meter{font-weight:700}",
			'_css_classes' => 'mi-form', '_element_id' => 'form-ancla',
		),
		'fm-globals'  => array( '__globals__' => array( 'label_color' => 'globals/colors?id=text', 'button_background_color' => 'globals/colors?id=accent', 'button_typography_typography' => 'globals/typography?id=accent', 'field_typography_typography' => 'globals/typography?id=text', 'step_active_primary_color' => 'globals/colors?id=primary' ) ),
		'fm-dynamic'  => array( '__dynamic__' => array( 'form_name' => '[elementor-tag id="d1" name="post-title" settings="%7B%7D"]', 'button_text' => '[elementor-tag id="d2" name="site-title" settings="%7B%7D"]', 'email_to' => '[elementor-tag id="d3" name="author-info" settings="%7B%7D"]' ), 'form_fields' => array( array( '_id' => 'z', 'custom_id' => 'quien', 'field_type' => 'hidden', '__dynamic__' => array( 'field_value' => '[elementor-tag id="d4" name="user-info" settings="%7B%7D"]' ) ) ) ),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'form', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, true );
	check_fm( count( $cases ) === count( $log ), 'Converter::tree() convierte cada formulario.' );
	$out = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_fm( $name === $element['widgetType'] && isset( $cases[ $element['id'] ] ), $element['id'] . ": pasa a $name con el mismo id (el form_id de los envíos guardados)." );
		$out[ $element['id'] ] = $element['settings'];
	}
	$codes = array();
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_fm( array( 'fm-full:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped, pro.dropped ni aproximación; sólo el CSS convertido: ' . implode( ', ', $codes ) );
	$d = $out['fm-defaults'];
	check_fm( 'New Form' === $d['form_name'] && 'Send' === $d['button_text'] && array( 'Name', 'Email', 'Message' ) === array_column( $d['form_fields'], 'field_label' ) && 'form' === $d['_digitalisimo_pro'], 'Un formulario sin cambios conserva los textos y los campos de PRO.' );
	$f = $out['fm-full'];
	foreach ( $cases['fm-full'] as $key => $value ) {
		if ( 'form_fields' === $key ) {
			$norm = static function ( array $rows ) {
				return array_map( static function ( $row ) { ksort( $row ); return $row; }, $rows );
			};
			check_fm( $norm( $value ) === $norm( $f[ $key ] ), 'fm-full: cada campo pasa sin cambios (también los de los tipos de campo y las condiciones de F7b).' );
		} elseif ( 'custom_css' !== $key ) {
			check_fm( array_key_exists( $key, $f ) && $value === $f[ $key ], "fm-full: $key pasa sin cambios (acciones, destinatarios, campos)." );
		}
	}
	check_fm( "selector .digi-step--on .digi-step__badge{border-width:2px}\n.elementor-widget-digitalisimo-form .elementor-field{box-shadow:none}\nselector .digi-steps__meter{font-weight:700}" === $f['custom_css'], 'CSS personalizado con las clases propias: ' . $f['custom_css'] );
	check_fm( $cases['fm-globals']['__globals__'] === $out['fm-globals']['__globals__'] && $cases['fm-dynamic']['__dynamic__'] === $out['fm-dynamic']['__dynamic__'] && $cases['fm-dynamic']['form_fields'][0]['__dynamic__'] === $out['fm-dynamic']['form_fields'][0]['__dynamic__'], 'Globales y etiquetas dinámicas (también en un campo) siguen a su control.' );
	check_fm( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );
	$painted = $render( $own, $f + $defaults );
	check_fm( str_contains( $painted, 'id="contacto"' ) && str_contains( $painted, 'name="Contacto"' ) && str_contains( $painted, 'name="form_fields[cv]"' ) === false, 'Lo convertido se pinta (la subida la pinta su tipo de campo, ausente en esta prueba).' );

	echo 'DIGITALÍSIMO Elements: Formulario (' . count( $own->controls ) . ' controles con 3 de acciones de prueba, ' . count( $own_fields ) . ' por campo, ' . count( $own->groups ) . ' grupos; hoja de ' . strlen( $css ) . ' B y script de ' . strlen( $js ) . ' B), Action_Host, reenvío de ganchos y su mapa desde PRO (' . count( $cases ) . " casos de conversión) validados.\n";
}
