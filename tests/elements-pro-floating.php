<?php
/**
 * Botones de contacto y Barra flotante de C8 (frente P, contrato docs/pro/c8.md): los widgets propios
 * `digitalisimo-contact-buttons` (nueve presentaciones) y `digitalisimo-floating-bar` (dos), que sustituyen a las
 * variantes de PRO `contact-buttons-var-<n>` y `floating-bars-var-<n>`. Sin WordPress, con dobles mínimos de Elementor
 * (los de tests/elements-pro-link-in-bio.php) y los archivos reales de la base del núcleo y de PRO:
 *
 * - módulo: declara cada widget, su título, sus presentaciones y su base del núcleo; hoja común (≤ 4 KB, foco visible y
 *   movimiento reducido) y una por presentación sin `--e-global-*`, `url()` ni clases del núcleo; script propio sin jQuery;
 * - sonda: para cada presentación, la base del núcleo con la configuración copiada de PRO registra los mismos controles
 *   que la variante de PRO (orden, tipo, condiciones, selectores, opciones, dispositivos y valores por defecto salvo los
 *   textos, en español);
 * - unión (`Variants::merge`): cada control de cada variante está en el widget propio con `presentation` en su condición
 *   cuando no está en todas; `rewrite()` pasa clases y variables del núcleo a las propias;
 * - marcado: el render propio de cada presentación es el de PRO con las clases propias, salvo nombres accesibles (en
 *   español), ids por elemento, el aspa en SVG, el estado anunciado del cupón y sin diálogos sin foco;
 * - ninguno de los archivos nombra lo que veta `scripts/build-packages.sh`.
 *
 * Uso: node …/php-wasm-cli tests/elements-pro-floating.php (php-wasm sale con 0 aunque falle: buscar «FALLA»).
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $recorded = array();
		public $sections = array();
		public $attrs    = array();
		public function __construct( $data = array(), $args = null ) {
			if ( isset( $data['settings'] ) ) {
				$this->settings = $data['settings'];
			}
		}
		public function print_render() { $this->render(); }
		public function build(): array {
			$this->recorded = array();
			$this->register_controls();
			return $this->recorded;
		}
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings_for_display( $key = null ) { return $this->get_settings( $key ); }
		public function is_type_instance() { return empty( $this->settings ); }
		public function get_id() { return 'abc1234'; }
		public function get_name() { return 'test'; }
		public function start_controls_section( $name, $args = array() ) { $this->recorded[ $name ] = array( 'type' => 'section' ) + $args; }
		public function end_controls_section() {}
		public function start_controls_tabs( $name, $args = array() ) { $this->recorded[ $name ] = array( 'type' => 'tabs' ) + $args; }
		public function end_controls_tabs() {}
		public function start_controls_tab( $name, $args = array() ) { $this->recorded[ $name ] = array( 'type' => 'tab' ) + $args; }
		public function end_controls_tab() {}
		public function start_popover() {}
		public function end_popover() {}
		public function add_inline_editing_attributes( $k, $t = 'basic' ) {}
		public function add_hidden_device_controls() { $this->recorded['hidden_devices'] = array( 'type' => 'hidden_devices' ); }
		public function start_injection( $args ) {}
		public function end_injection() {}
		public function add_control( $name, $args, $options = array() ) { $this->recorded[ $name ] = $args; }
		public function add_responsive_control( $name, $args, $options = array() ) { $this->recorded[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args = array(), $options = array() ) { $this->recorded[ 'group:' . $args['name'] ] = array( 'type' => 'group-' . $type ) + $args; }
		public function update_control( $name, $args, $options = array() ) { if ( isset( $this->recorded[ $name ] ) ) { $this->recorded[ $name ] = array_merge( $this->recorded[ $name ], $args ); } }
		public function get_controls( $name = null ) { return null === $name ? $this->recorded : ( $this->recorded[ $name ] ?? null ); }
		public function add_render_attribute( $key, $attr, $value = null ) {
			$pairs = is_array( $attr ) ? $attr : array( $attr => $value );
			foreach ( $pairs as $k => $v ) {
				$this->attrs[ $key ][ $k ] = array_merge( $this->attrs[ $key ][ $k ] ?? array(), (array) $v );
			}
		}
		public function get_render_attribute_string( $key ) {
			$out = array();
			foreach ( $this->attrs[ $key ] ?? array() as $k => $v ) {
				$out[] = $k . '="' . htmlspecialchars( implode( ' ', $v ), ENT_QUOTES, 'UTF-8' ) . '"';
			}
			return implode( ' ', $out );
		}
		public function print_render_attribute_string( $key ) { echo $this->get_render_attribute_string( $key ); }
		public function add_link_attributes( $key, $link ) { if ( ! empty( $link['url'] ) ) { $this->add_render_attribute( $key, 'href', $link['url'] ); } }
	}
	class Repeater extends Widget_Base {
		public function get_controls( $name = null ) {
			$out = array();
			foreach ( $this->recorded as $id => $c ) {
				$out[ $id ] = array( 'name' => $id ) + $c;
			}
			return $out;
		}
	}
	class Controls_Manager {
		const ALERT = 'alert'; const COLOR = 'color'; const DIMENSIONS = 'dimensions'; const DIVIDER = 'divider'; const HEADING = 'heading';
		const MEDIA = 'media'; const REPEATER = 'repeater'; const SELECT = 'select'; const SLIDER = 'slider'; const SWITCHER = 'switcher';
		const TAB_CONTENT = 'content'; const TAB_STYLE = 'style'; const TEXT = 'text'; const TEXTAREA = 'textarea'; const URL = 'url';
		const CHOOSE = 'choose'; const SELECT2 = 'select2'; const GALLERY = 'gallery'; const NUMBER2 = 'number'; const ICONS = 'icons'; const NUMBER = 'number'; const HIDDEN = 'hidden';
		const ANIMATION = 'animation'; const EXIT_ANIMATION = 'exit_animation';
		public static function add_tab( $a, $b ) {} const HOVER_ANIMATION = 'hover_animation'; const RAW_HTML = 'raw_html'; const TAB_ADVANCED = 'advanced';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Shapes {
		const FILTER_EXCLUDE = 'exclude';
		const FILTER_INCLUDE = 'include';
		public static function get_shapes( $shape = null ) { return array( 'mountains' => array( 'title' => 'Montañas', 'has_negative' => true ), 'wave' => array( 'title' => 'Ola', 'height_only' => true ) ); }
		public static function filter_shapes( $by, $filter = self::FILTER_INCLUDE ) { return array_filter( self::get_shapes(), function( $s ) use ( $by, $filter ) { return self::FILTER_INCLUDE === $filter xor empty( $s[ $by ] ); } ); }
		public static function get_shape_path( $name, $negative = false ) { return ''; }
	}
	class Utils {
		public static function get_placeholder_image_src() { return 'https://sitio.test/placeholder.png'; }
		public static function file_get_contents( $f ) { return ''; }
		public static function parse_custom_attributes( $s ) { return array(); }
		public static function has_pro() { return false; }
		public static function print_unescaped_internal_string( $s ) { echo $s; }
		public static function validate_html_tag( $t ) { return in_array( $t, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ? $t : 'div'; }
	}
	class Icons_Manager {
		public static function render_icon( $icon, $attrs = array() ) { echo '<svg class="e-font-icon-svg e-' . str_replace( ' ', '-', (string) ( $icon['value'] ?? '' ) ) . '" aria-hidden="true"></svg>'; return true; }
	}
	class Plugin {
		public static $instance;
		public $experiments;
		public $breakpoints;
		public $controls_manager;
		public static function elementor() { return self::$instance; }
	}
}
namespace Elementor\Modules\FloatingButtons\Control {
	class Hover_Animation_Floating_Buttons { const TYPE = 'hover_animation_floating_buttons'; }
}
namespace ElementorPro\Base {
	class Markdown_Utils {}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace Elementor\Modules\FloatingButtons\Documents {
	class Floating_Buttons { public static function get_title() { return 'Floating Element'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function _x( $v ) { return $v; }
	function esc_html_x( $v ) { return $v; }
	function is_rtl() { return false; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v, $p = null ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function wp_get_attachment_image( $id, $size, $icon, $attrs ) {
		$out = '<img src="https://sitio.test/' . $id . '-' . $size . '.jpg" srcset="https://sitio.test/' . $id . '-1024.jpg 1024w" alt="Texto ' . $id . '"';
		foreach ( $attrs as $k => $v ) { $out .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) . '"'; }
		return $out . '>';
	}
	function wp_get_attachment_url( $id ) { return 'https://sitio.test/' . $id . '.jpg'; }
	function get_the_ID() { return 42; }
	function sanitize_html_class( $v ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $v ); }
	function check_fl( $ok, $message ) {
		$GLOBALS['fl_checks'] = ( $GLOBALS['fl_checks'] ?? 0 ) + 1;
		if ( ! $ok ) {
			echo 'FALLA: ' . $message . "\n";
			$GLOBALS['fl_failed'] = true;
		}
	}
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return true; } };
	\Elementor\Plugin::$instance->breakpoints = new class() {
		public function get_active_breakpoints() { $b = new class() { public function get_label() { return 'Móvil'; } }; return array( 'tablet' => $b, 'mobile' => $b ); }
		public function get_active_devices_list( $a = array() ) { return array( 'desktop', 'tablet', 'mobile' ); }
	};
	\Elementor\Plugin::$instance->controls_manager = new class() { public function __call( $n, $a ) { return null; } };


	$root = dirname( __DIR__ );
	$el   = $root . '/digitalisimo-elements/';
	$fl   = $el . 'modules/digitalisimo-floating/';
	require $el . 'elementor/core/base/providers/social-network-provider.php';
	require $el . 'elementor/core/base/traits/shared-widget-controls-trait.php';
	require $el . 'elementor/modules/floating-buttons/base/widget-contact-button-base.php';
	require $el . 'elementor/modules/floating-buttons/base/widget-floating-bars-base.php';
	foreach ( array( 'contact-buttons-render-base', 'contact-buttons-core-render', 'floating-bars-render-base', 'floating-bars-core-render' ) as $file ) {
		require $el . "elementor/modules/floating-buttons/classes/render/$file.php";
	}
	require $el . 'modules/floating-buttons/base/widget-contact-button-base-pro.php';
	require $el . 'modules/floating-buttons/base/widget-floating-bars-base-pro.php';
	$families = array(
		'digitalisimo-contact-buttons' => array( 'pro' => 'contact-buttons-var-', 'class' => 'Contact_Buttons_Var_', 'probe' => 'Contact_Probe', 'widget' => 'Contact_Buttons', 'title' => 'Botones de contacto', 'block' => 'digi-cb', 'core' => 'e-contact-buttons', 'list' => array( '1', '3', '4', '5', '6', '7', '8', '9', '10' ) ),
		'digitalisimo-floating-bar'    => array( 'pro' => 'floating-bars-var-', 'class' => 'Floating_Bars_Var_', 'probe' => 'Bar_Probe', 'widget' => 'Floating_Bar', 'title' => 'Barra flotante', 'block' => 'digi-fbar', 'core' => 'e-floating-bars', 'list' => array( '2', '3' ) ),
	);
	foreach ( $families as $f ) {
		foreach ( $f['list'] as $n ) {
			require $el . 'modules/floating-buttons/classes/render/' . $f['pro'] . $n . '-render.php';
			require $el . 'modules/floating-buttons/widgets/' . $f['pro'] . $n . '.php';
		}
	}
	require $fl . 'class-module.php';
	\Digitalisimo\Elements\Floating\Module::init();
	foreach ( array( 'class-variants.php', 'class-probe.php', 'class-presentations.php', 'class-contact-probe.php', 'class-contact-render.php', 'class-bar-probe.php', 'class-bar-render.php', 'widgets/class-contact-buttons.php', 'widgets/class-floating-bar.php' ) as $file ) {
		require_once $fl . $file;
	}

	$norm     = static function ( string $html ) { $html = preg_replace( '/\s*(Warning|Notice|Deprecated): .*? on line \d+/', '', $html ); return trim( preg_replace( array( '/>\s+/', '/\s+</' ), array( '>', '<' ), preg_replace( '/\s+/', ' ', $html ) ) ); };
	// Valores de cada control como los da Elementor: su valor por defecto o el vacío de su tipo; filas con los de sus campos.
	$empty    = static function ( array $c ) {
		if ( array_key_exists( 'default', $c ) ) {
			return $c['default'];
		}
		$type = $c['type'] ?? '';
		if ( in_array( $type, array( 'url', 'media' ), true ) ) {
			return array( 'url' => '', 'id' => '' );
		}
		if ( 'icons' === $type ) {
			return array( 'value' => '', 'library' => '' );
		}
		if ( in_array( $type, array( 'slider', 'dimensions' ), true ) ) {
			return array( 'unit' => 'px', 'size' => '' );
		}
		return '';
	};
	$defaults = static function ( array $controls ) use ( $empty ) {
		$out = array();
		foreach ( $controls as $id => $c ) {
			if ( 0 === strpos( (string) $id, 'group:' ) || in_array( $c['type'] ?? '', array( 'section', 'tabs', 'tab', 'heading', 'divider', 'raw_html', 'alert' ), true ) ) {
				continue;
			}
			$out[ $id ] = $empty( $c );
			if ( 'repeater' === ( $c['type'] ?? '' ) ) {
				$out[ $id ] = array_map(
					static function ( $row ) use ( $c, $empty ) {
						foreach ( (array) $c['fields'] as $k => $field ) {
							$row += array( $field['name'] ?? $k => $empty( $field ) );
						}
						return $row;
					},
					(array) $out[ $id ]
				);
			}
		}
		return $out;
	};
	// Sin nombres accesibles (en español), ids, el aspa y lo que sólo tiene el propio (región de estado del cupón).
	$strip = static function ( $html ) {
		$html = preg_replace( '/ (aria-label|aria-controls|aria-hidden|aria-expanded|id|role|data-message|data-document-id|hidden|focusable)(="[^"]*")?/', '', $html );
		$html = preg_replace( '#<i class="eicon-close"></i>|<i class="digi-[a-z]+__close-icon".*?</i>#', 'X', $html );
		$html = preg_replace( '#<span class="digi-fbar__status"[^>]*></span>#', '', $html );
		// Un titular sin enlace es texto (PRO pintaba un enlace sin destino).
		$html = preg_replace( '#<a class="digi-fbar__headline">(.*?)</a>#', '<span class="digi-fbar__headline">$1</span>', $html );
		// Orden de los atributos.
		$html = preg_replace_callback( '/<(\w+)((?: [\w-]+="[^"]*")+)>/', static function ( $m ) { preg_match_all( '/ [\w-]+="[^"]*"/', $m[2], $a ); sort( $a[0] ); return '<' . $m[1] . implode( '', $a[0] ) . '>'; }, $html );
		return $html;
	};
	$M = '\\Digitalisimo\\Elements\\Floating\\Module';
	foreach ( $families as $name => $f ) {
		$probe  = 'Digitalisimo\\Elements\\Floating\\' . $f['probe'];
		$widget = 'Digitalisimo\\Elements\\Floating\\Widgets\\' . $f['widget'];
		// 1. Módulo y recursos.
		check_fl( isset( $M::WIDGETS[ $name ] ) && $f['title'] === $M::TITLES[ $name ] && $f['list'] === $M::PRESENTATIONS[ $name ] && $probe::PRESENTATIONS === $f['list'], "$name: el módulo declara el widget, su título y sus presentaciones." );
		check_fl( is_subclass_of( $widget, $M::BASES[ $name ] ), "$name: hereda la base del núcleo que declara el módulo." );
		$type = new $widget();
		check_fl( $name === $type->get_name() && $f['title'] === $type->get_title() && count( $type->get_style_depends() ) === 1 + count( $f['list'] ), "$name: nombre, título y, en la instancia de tipo, la hoja común y las de todas sus presentaciones." );
		check_fl( array( $M::TRACK, $name ) === $type->get_script_depends(), "$name: script propio y el del recuento de clics." );
		$js = (string) file_get_contents( $M::path( $M::WIDGETS[ $name ]['js'] ) );
		check_fl( strlen( $js ) <= 8192 && ! preg_match( '/jQuery\(|jQuery\.|\$\(|swiper/i', $js ) && str_contains( $js, 'prefers-reduced-motion' ), "$name: script ≤ 8 KB, sin jQuery ni Swiper, con movimiento reducido." );
		$common = (string) file_get_contents( $M::path( $M::WIDGETS[ $name ]['css'] ) );
		check_fl( strlen( $common ) <= 4096 && str_contains( $common, 'outline:2px solid' ) && str_contains( $common, 'outline-offset:2px' ) && str_contains( $common, 'prefers-reduced-motion' ), "$name: hoja común ≤ 4 KB con foco visible y movimiento reducido." );
		foreach ( $f['list'] as $p ) {
			$css  = (string) @file_get_contents( $M::path( $M::presentation_css( $name, $p ) ) );
			$bare = preg_replace( '#/\*.*?\*/#s', '', $css );
			check_fl( '' !== $css && strlen( $css ) <= 12288, "$name $p: hoja de su presentación (" . strlen( $css ) . ' B).' );
			check_fl( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( $bare, $f['core'] ), "$name $p: hoja sin el kit, url() ni clases del núcleo." );
			check_fl( array( $name, $name . '-' . $p ) === $M::style_depends( $name, $p ), "$name $p: `style_depends()` da la común y la suya." );
		}

		// 2. Sonda frente a la variante de PRO.
		$lists = array();
		foreach ( $f['list'] as $p ) {
			$probe::use_presentation( $p );
			$own = ( new $probe() )->build();
			$probe::use_presentation( '' );
			$class = '\\ElementorPro\\Modules\\FloatingButtons\\Widgets\\' . $f['class'] . $p;
			$pro   = ( new $class() )->build();
			$lists[ $p ] = $own;
			check_fl( array_keys( $pro ) === array_keys( $own ), "$name $p: mismos controles y en el mismo orden que {$f['pro']}$p (" . implode( ', ', array_slice( array_merge( array_diff( array_keys( $pro ), array_keys( $own ) ), array_diff( array_keys( $own ), array_keys( $pro ) ) ), 0, 6 ) ) . ').' );
			$texts = 0;
			foreach ( $pro as $id => $c ) {
				$o = $own[ $id ] ?? array();
				foreach ( array( 'type', 'condition', 'conditions', 'selectors', 'selector', 'responsive', 'size_units', 'range', 'prefix_class', 'selectors_dictionary', 'return_value', 'frontend_available', 'render_type', 'separator' ) as $arg ) {
					check_fl( ( $c[ $arg ] ?? null ) === ( $o[ $arg ] ?? null ), "$name $p: `$id` igual en `$arg`." );
				}
				check_fl( array_keys( (array) ( $c['options'] ?? array() ) ) === array_keys( (array) ( $o['options'] ?? array() ) ), "$name $p: `$id` con las mismas opciones." );
				if ( 'repeater' === ( $c['type'] ?? '' ) ) {
					check_fl( array_keys( (array) $c['fields'] ) === array_keys( (array) $o['fields'] ), "$name $p: repetidor `$id` con los mismos campos." );
					foreach ( (array) $c['fields'] as $k => $field ) {
						check_fl( ( $field['type'] ?? null ) === ( $o['fields'][ $k ]['type'] ?? null ) && ( $field['condition'] ?? null ) === ( $o['fields'][ $k ]['condition'] ?? null ) && array_keys( (array) ( $field['options'] ?? array() ) ) === array_keys( (array) ( $o['fields'][ $k ]['options'] ?? array() ) ), "$name $p: campo `$id.$k` igual (tipo, condición y opciones)." );
					}
					check_fl( count( (array) ( $c['default'] ?? array() ) ) === count( (array) ( $o['default'] ?? array() ) ), "$name $p: repetidor `$id` con las mismas filas por defecto." );
				} elseif ( in_array( $c['type'] ?? '', array( 'text', 'textarea' ), true ) && is_string( $c['default'] ?? null ) && ( $c['default'] ?? null ) !== ( $o['default'] ?? null ) ) {
					++$texts;
				} else {
					check_fl( ( $c['default'] ?? null ) === ( $o['default'] ?? null ), "$name $p: `$id` con el mismo valor por defecto." );
				}
			}
			check_fl( $texts <= 6, "$name $p: sólo cambian textos por defecto (en español): $texts." );
		}

		// 3. Unión.
		$merged = \Digitalisimo\Elements\Floating\Variants::merge( $lists );
		foreach ( $lists as $p => $controls ) {
			foreach ( $controls as $id => $c ) {
				$m   = $merged['controls'][ $id ] ?? null;
				$set = $merged['presentations'][ $id ] ?? array();
				check_fl( null !== $m && in_array( (string) $p, $set, true ), "$name unión: `$id` (presentación $p) está en el widget propio." );
				if ( count( $set ) < count( $f['list'] ) ) {
					check_fl( isset( $m['condition']['presentation'] ) && $set === $m['condition']['presentation'], "$name unión: `$id` sólo se ve en sus presentaciones." );
				}
			}
		}
		$rw = $widget::rewrite( array( 'k' => array( 'selectors' => array( '{{WRAPPER}} .' . $f['core'] . '__x' => '--' . $f['core'] . '-y: 1' ) ) ), $widget::RENAME );
		check_fl( array( '{{WRAPPER}} .' . $f['block'] . '__x' => '--' . $f['block'] . '-y: 1' ) === $rw['k']['selectors'], "$name: `rewrite()` pasa clases y variables del núcleo a las propias." );

		// 4. Marcado: el de PRO con las clases propias y las diferencias de accesibilidad declaradas.
		foreach ( $f['list'] as $p ) {
			$class = '\\ElementorPro\\Modules\\FloatingButtons\\Widgets\\' . $f['class'] . $p;
			$pro_w = new $class();
			$settings = $defaults( $pro_w->build() ) + array( 'advanced_custom_css_id' => '' );
			$pro_w->settings = $settings;
			ob_start();
			$pro_w->print_render();
			$pro_html = $norm( (string) ob_get_clean() );
			$own_w = new $widget( array( 'settings' => $settings + array( 'presentation' => $p ) ) );
			ob_start();
			$own_w->print_render();
			$own_html = $norm( (string) ob_get_clean() );
			check_fl( str_contains( $own_html, 'class="' . $f['block'] . ' ' . $f['block'] . '--p' . $p ) && ! str_contains( $own_html, $f['core'] ) && ! str_contains( $own_html, 'eicon' ), "$name $p: clases propias, sin la fuente de iconos del núcleo." );
			check_fl( ! preg_match( '/powered|elementor\.com|role="(dialog|alertdialog)"/i', $own_html ), "$name $p: sin rastro del proveedor ni diálogos sin foco." );
			$expected = str_replace( array( $f['core'] . ' ' . $f['core'] . '-var-' . $p, $f['core'] ), array( $f['block'] . ' ' . $f['block'] . '--p' . $p, $f['block'] ), $pro_html );
			$a = $strip( $expected );
			$b = $strip( $own_html );
			$at = strspn( $a ^ $b, "\0" );
			check_fl( $a === $b, "$name $p: el marcado de {$f['pro']}$p con las clases propias." . ( $a === $b ? '' : "\n  PRO: " . substr( $a, max( 0, $at - 150 ), 400 ) . "\n  propio: " . substr( $b, max( 0, $at - 150 ), 400 ) ) );
			preg_match_all( '/<button\b[^>]*>/', $own_html, $buttons );
			foreach ( $buttons[0] as $tag ) {
				check_fl( (bool) preg_match( '/aria-label="[^"]+"/', $tag ), "$name $p: cada botón tiene nombre accesible ($tag)." );
			}
		}
	}

	// 5. Conversión con los mapas (Converter::tree): presentación fija, ningún `pro.unmapped` y lo convertido se pinta con el
	// marcado del caso de PRO.
	require_once $el . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	foreach ( $families as $name => $f ) {
		foreach ( $f['list'] as $p ) {
			$source = $f['pro'] . $p;
			$map    = \Digitalisimo\Elements\Convert\Registry::map( $source, true );
			check_fl( null !== $map && $name === $map['target'] && 'hide' === $map['panel'] && array( 'presentation' => $p ) === $map['set'], "$source: mapa hacia $name con la presentación $p." );
			$probe = 'Digitalisimo\\Elements\\Floating\\' . $f['probe'];
			$probe::use_presentation( $p );
			$color = '';
			foreach ( ( new $probe() )->build() as $id => $c ) {
				if ( 'color' === ( $c['type'] ?? '' ) && '' === $color ) {
					$color = $id;
				}
			}
			$probe::use_presentation( '' );
			$samples = array(
				array(),
				array( 'advanced_vertical_position' => 'top', 'custom_css' => 'selector .' . $f['core'] . '-var-' . $p . '{color:red}', $color => '#123456', '__globals__' => array( $color => 'globals/colors?id=primary' ) ),
			);
			foreach ( $samples as $i => $settings ) {
				$log  = array();
				$tree = \Digitalisimo\Elements\Convert\Converter::tree( array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => $source, 'settings' => $settings, 'elements' => array() ) ) ) ), $log, true );
				$w    = $tree[0]['elements'][0];
				$codes = array();
				foreach ( $log as $entry ) {
					foreach ( (array) ( $entry['codes'] ?? array() ) as $code ) {
						$codes[] = $code['code'] . ' ' . $code['key'];
					}
				}
				check_fl( $name === $w['widgetType'] && $p === ( $w['settings']['presentation'] ?? '' ), "$source ($i): convertido a $name, presentación $p." );
				check_fl( ! preg_grep( '/^pro\.unmapped/', $codes ), "$source ($i): ninguna clave sin declarar (" . implode( ', ', $codes ) . ').' );
				if ( isset( $settings['custom_css'] ) ) {
					check_fl( str_contains( (string) ( $w['settings']['custom_css'] ?? '' ), '.' . $f['block'] . '--p' . $p . '{' ) && '#123456' === ( $w['settings'][ $color ] ?? '' ) && isset( $w['settings']['__globals__'][ $color ] ), "$source: el CSS personalizado pasa a las clases propias; color y global se conservan." );
				}
			}
		}
	}

	// 6. Marcado propio para la prueba en Chrome (`--write-fixture`): Botones de contacto 1, 4 y 8 y la Barra flotante 2
	// (con pausa y cierre) y 3, con sus hojas y scripts en línea.
	if ( in_array( '--write-fixture', $argv ?? array(), true ) ) {
		$cases = array(
			array( 'digitalisimo-contact-buttons', '1', array() ),
			array( 'digitalisimo-contact-buttons', '4', array( 'advanced_horizontal_position' => 'start' ) ),
			array( 'digitalisimo-contact-buttons', '8', array( 'advanced_horizontal_position' => 'center' ) ),
			array( 'digitalisimo-floating-bar', '2', array( 'floating_bar_pause_switch' => 'yes', 'floating_bar_close_switch' => 'yes', 'advanced_toggle_sticky' => '', 'floating_bar_pause_icon' => array( 'value' => 'fas fa-pause', 'library' => 'fa-solid' ), 'floating_bar_play_icon' => array( 'value' => 'fas fa-play', 'library' => 'fa-solid' ) ) ),
			array( 'digitalisimo-floating-bar', '3', array( 'advanced_toggle_sticky' => '', 'advanced_vertical_position' => 'bottom' ) ),
		);
		$body = '';
		$css  = '';
		$seen = array();
		foreach ( $cases as $i => $case ) {
			list( $name, $p, $extra ) = $case;
			$f     = $families[ $name ];
			$class = '\\ElementorPro\\Modules\\FloatingButtons\\Widgets\\' . $f['class'] . $p;
			$settings = $defaults( ( new $class() )->build() ) + array( 'advanced_custom_css_id' => '' );
			$widget = 'Digitalisimo\\Elements\\Floating\\Widgets\\' . $f['widget'];
			$w = new $widget( array( 'settings' => $extra + $settings + array( 'presentation' => $p ) ) );
			ob_start();
			$w->print_render();
			$html = (string) ob_get_clean();
			$html = str_replace( array( 'abc1234', 'https://sitio.test/placeholder.png' ), array( 'w' . $i, 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 1 1%22/%3E' ), $html );
			$body .= '<div class="elementor-element" data-id="w' . $i . '" data-settings="{}" id="case-' . $name . '-' . $p . '">' . $html . "</div>\n";
			foreach ( array( $M::path( $M::WIDGETS[ $name ]['css'] ), $M::path( $M::presentation_css( $name, $p ) ) ) as $sheet ) {
				if ( ! isset( $seen[ $sheet ] ) ) {
					$seen[ $sheet ] = true;
					$css .= (string) file_get_contents( $sheet ) . "\n";
				}
			}
		}
		$js = '';
		foreach ( array( $M::TRACK_JS, $M::WIDGETS['digitalisimo-contact-buttons']['js'], $M::WIDGETS['digitalisimo-floating-bar']['js'] ) as $file ) {
			$js .= '<script>' . file_get_contents( $M::path( $file ) ) . "</script>\n";
		}
		$page = "<!doctype html>\n<html lang=\"es\"><head><meta charset=\"utf-8\"><title>C8 · botones flotantes</title><style>\n" . $css . "</style></head>\n<body style=\"margin:0;min-height:1200px\">\n" . $body . $js . "</body></html>\n";
		file_put_contents( $root . '/tests/fixtures/pro-floating.html', $page );
		echo "Fixture escrita: tests/fixtures/pro-floating.html\n";
	}

	// 7. Nombres vetados por el empaquetado.
	foreach ( array_merge( glob( $fl . '*.php' ), glob( $fl . 'widgets/*.php' ), glob( $fl . 'assets/*/*' ) ) as $file ) {
		$src = (string) file_get_contents( $file );
		check_fl( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $src ), basename( $file ) . ': sin nombres vetados por scripts/build-packages.sh.' );
	}
	echo empty( $GLOBALS['fl_failed'] ) ? 'DIGITALÍSIMO Elements: Botones de contacto y Barra flotante propios (C8) validados: ' . $GLOBALS['fl_checks'] . " comprobaciones.\n" : "FALLA: Botones de contacto y Barra flotante propios (C8).\n";
}
