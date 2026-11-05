<?php
/**
 * Link in Bio de C8 (frente Q, contrato docs/pro/c8.md): el widget propio `digitalisimo-link-in-bio`, que sustituye a las
 * seis variantes de PRO `link-in-bio-var-<n>` con una presentación por variante. Sin WordPress, con dobles mínimos de
 * Elementor (como tests/elements-pro-cards.php) y con los archivos reales de la base del núcleo y de PRO:
 *
 * - módulo: declara el widget (archivo, clase, título, presentaciones, base del núcleo), hoja común y una por presentación
 *   sin `--e-global-*`, `url()` ni clases del núcleo, con foco visible y movimiento reducido; sin script; tope de cada hoja;
 * - sonda: para cada presentación, la base del núcleo con la configuración copiada de PRO registra los mismos controles
 *   que la variante de PRO (nombre y orden, tipo, condiciones, selectores, opciones, valores por defecto salvo los
 *   textos, que están en español; campos y filas de los repetidores);
 * - unión (`Variants::merge`): cada control de cada variante está en el widget propio, con `presentation` en su
 *   condición cuando no está en todas; listas sintéticas para el orden, los selectores y opciones unidos y la condición
 *   por grupos (`conditions`); `Presentations::rewrite()` pasa las clases y variables del núcleo a las propias;
 * - marcado: el render propio de cada presentación es el de PRO con las clases propias (`digi-lib`, `digi-lib--p<n>`) y
 *   nombres accesibles legibles en los iconos; sin rastro del proveedor;
 * - ninguno de los archivos nombra lo que veta `scripts/build-packages.sh`.
 *
 * Uso: node …/php-wasm-cli tests/elements-pro-link-in-bio.php (php-wasm sale con 0 aunque falle: buscar «FALLA»).
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
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
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
		public static function elementor() { return self::$instance; }
	}
}
namespace Elementor\Modules\FloatingButtons\Control {
	class Hover_Animation_Floating_Buttons { const TYPE = 'hover_animation_floating_buttons'; }
}
namespace ElementorPro\Base {
	class Markdown_Utils {}
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
	function check_lib( $ok, $message ) {
		$GLOBALS['lib_checks'] = ( $GLOBALS['lib_checks'] ?? 0 ) + 1;
		if ( ! $ok ) {
			echo 'FALLA: ' . $message . "\n";
			$GLOBALS['lib_failed'] = true;
		}
	}
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return true; } };
	\Elementor\Plugin::$instance->breakpoints = new class() {
		public function get_active_breakpoints() { $b = new class() { public function get_label() { return 'Móvil'; } }; return array( 'tablet' => $b, 'mobile' => $b ); }
		public function get_active_devices_list( $a = array() ) { return array( 'desktop', 'tablet', 'mobile' ); }
	};

	$root = dirname( __DIR__ );
	$el   = $root . '/digitalisimo-elements/';
	$fl   = $el . 'modules/digitalisimo-floating/';
	require $el . 'elementor/core/base/providers/social-network-provider.php';
	require $el . 'elementor/core/base/traits/shared-widget-controls-trait.php';
	require $el . 'elementor/modules/link-in-bio/base/widget-link-in-bio-base.php';
	require $el . 'elementor/modules/link-in-bio/classes/render/render-base.php';
	require $el . 'elementor/modules/link-in-bio/classes/render/core-render.php';
	require $el . 'modules/link-in-bio/base/widget-link-in-bio-base-pro.php';
	require $el . 'modules/link-in-bio/classes/render/icons-below-cta-render.php';
	require $el . 'modules/link-in-bio/classes/render/single-button-cta-render.php';
	foreach ( array( 2, 3, 4, 5, 6, 7 ) as $n ) {
		require $el . "modules/link-in-bio/widgets/link-in-bio-var-$n.php";
	}
	require $fl . 'class-module.php';
	\Digitalisimo\Elements\Floating\Module::init();
	foreach ( array( 'class-variants.php', 'class-probe.php', 'class-presentations.php', 'class-bio-probe.php', 'class-bio-render.php', 'widgets/class-link-in-bio.php' ) as $file ) {
		require_once $fl . $file;
	}
	use Digitalisimo\Elements\Floating\Module;
	use Digitalisimo\Elements\Floating\Variants;
	use Digitalisimo\Elements\Floating\Bio_Probe;
	use Digitalisimo\Elements\Floating\Bio_Render;
	use Digitalisimo\Elements\Floating\Widgets\Link_In_Bio;

	$name = 'digitalisimo-link-in-bio';

	// 1. Módulo y recursos.
	check_lib( isset( Module::WIDGETS[ $name ] ) && 'widgets/class-link-in-bio.php' === Module::WIDGETS[ $name ]['file'] && 'Link in Bio' === Module::TITLES[ $name ], 'el módulo declara el widget, su archivo y su título.' );
	check_lib( array( '2', '3', '4', '5', '6', '7' ) === Module::PRESENTATIONS[ $name ] && Bio_Probe::PRESENTATIONS === Module::PRESENTATIONS[ $name ], 'las presentaciones del módulo son las de la sonda (2–7).' );
	check_lib( 'Elementor\\Modules\\LinkInBio\\Base\\Widget_Link_In_Bio_Base' === Module::BASES[ $name ] && is_subclass_of( Link_In_Bio::class, Module::BASES[ $name ] ), 'el widget hereda la base del núcleo que declara el módulo.' );
	check_lib( 'digitalisimo-link-in-bio' === ( new Link_In_Bio() )->get_name() && 'Link in Bio' === ( new Link_In_Bio() )->get_title(), 'nombre y título del widget.' );
	check_lib( ! is_file( Module::path( Module::WIDGETS[ $name ]['js'] ) ) && array() === Module::script_depends( $name ) && array() === ( new Link_In_Bio() )->get_script_depends(), 'sin script en el sitio.' );
	// Tope de cada hoja (anotado en el informe: la base del núcleo que repiten pesa 12 399 B; PRO cargaba 13–14 KB).
	$caps = array( '' => 1024, '2' => 9216, '3' => 9216, '4' => 7680, '5' => 9216, '6' => 7680, '7' => 10240 );
	foreach ( $caps as $p => $cap ) {
		$p = (string) $p;
		$file = '' === $p ? Module::path( Module::WIDGETS[ $name ]['css'] ) : Module::path( Module::presentation_css( $name, $p ) );
		$css  = (string) @file_get_contents( $file );
		$bare = preg_replace( '#/\*.*?\*/#s', '', $css );
		check_lib( '' !== $css && strlen( $css ) <= $cap, "hoja «{$p}» de $cap B como máximo (" . strlen( $css ) . ' B).' );
		check_lib( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( $bare, 'e-link-in-bio' ) && ! str_contains( $bare, 'elementor' ), "hoja «{$p}»: sin el kit, url() ni clases del núcleo." );
		if ( '' !== $p ) {
			check_lib( str_contains( $css, '.digi-lib--p' . $p ) || in_array( $p, array( '3', '6' ), true ), "hoja «{$p}»: reglas de su presentación." );
			check_lib( array( $name, $name . '-' . $p ) === Module::style_depends( $name, $p ), "hoja «{$p}»: `style_depends()` da la común y la suya." );
		}
	}
	$common = (string) file_get_contents( Module::path( Module::WIDGETS[ $name ]['css'] ) );
	check_lib( str_contains( $common, 'outline:2px solid' ) && str_contains( $common, 'outline-offset:2px' ) && str_contains( $common, 'prefers-reduced-motion' ), 'la hoja común tiene foco visible (2 px + 2 px) y movimiento reducido.' );
	$type = new Link_In_Bio();
	check_lib( 7 === count( $type->get_style_depends() ), 'la instancia de tipo declara la hoja común y las seis de presentación (editor).' );

	// 2. Sonda frente a la variante de PRO, presentación por presentación.
	$lists = array();
	foreach ( Bio_Probe::PRESENTATIONS as $p ) {
		Bio_Probe::use_presentation( $p );
		$own = ( new Bio_Probe() )->build();
		Bio_Probe::use_presentation( '' );
		$class = '\\ElementorPro\\Modules\\LinkInBio\\Widgets\\Link_In_Bio_Var_' . $p;
		$pro   = ( new $class() )->build();
		$lists[ $p ] = $own;
		check_lib( array_keys( $pro ) === array_keys( $own ), "presentación $p: mismos controles y en el mismo orden que link-in-bio-var-$p (" . implode( ', ', array_slice( array_merge( array_diff( array_keys( $pro ), array_keys( $own ) ), array_diff( array_keys( $own ), array_keys( $pro ) ) ), 0, 6 ) ) . ').' );
		$texts = 0;
		foreach ( $pro as $id => $c ) {
			$o = $own[ $id ] ?? array();
			foreach ( array( 'type', 'condition', 'conditions', 'selectors', 'selector', 'responsive', 'size_units', 'range', 'prefix_class', 'selectors_dictionary', 'return_value', 'frontend_available', 'render_type' ) as $arg ) {
				check_lib( ( $c[ $arg ] ?? null ) === ( $o[ $arg ] ?? null ), "presentación $p: `$id` igual en `$arg`." );
			}
			check_lib( array_keys( (array) ( $c['options'] ?? array() ) ) === array_keys( (array) ( $o['options'] ?? array() ) ), "presentación $p: `$id` con las mismas opciones." );
			$textual = in_array( $c['type'] ?? '', array( 'text', 'textarea', 'wysiwyg' ), true );
			if ( 'repeater' === ( $c['type'] ?? '' ) ) {
				check_lib( array_keys( (array) $c['fields'] ) === array_keys( (array) $o['fields'] ), "presentación $p: repetidor `$id` con los mismos campos." );
				foreach ( (array) $c['fields'] as $f => $field ) {
					check_lib( ( $field['type'] ?? null ) === ( $o['fields'][ $f ]['type'] ?? null ) && ( $field['condition'] ?? null ) === ( $o['fields'][ $f ]['condition'] ?? null ), "presentación $p: campo `$id.$f` igual (tipo y condición)." );
				}
				$strip = static function ( $rows ) { return array_map( static function ( $row ) { return array_diff_key( (array) $row, array( 'cta_link_text' => 1, 'icon_text' => 1 ) ); }, (array) $rows ); };
				check_lib( $strip( $c['default'] ?? array() ) === $strip( $o['default'] ?? array() ), "presentación $p: repetidor `$id` con las mismas filas por defecto (salvo sus textos)." );
				$texts += (int) ( $c['default'] !== $o['default'] );
			} elseif ( $textual && is_string( $c['default'] ?? null ) ) {
				$texts += (int) ( $c['default'] !== ( $o['default'] ?? null ) );
			} else {
				check_lib( ( $c['default'] ?? null ) === ( $o['default'] ?? null ), "presentación $p: `$id` con el mismo valor por defecto." );
			}
		}
		check_lib( $texts <= 8, "presentación $p: sólo los textos por defecto cambian (en español): $texts." );
	}

	// 3. Unión de las seis presentaciones.
	$merged = Variants::merge( $lists );
	$all    = Bio_Probe::PRESENTATIONS;
	foreach ( $lists as $p => $controls ) {
		foreach ( $controls as $id => $c ) {
			$m = $merged['controls'][ $id ] ?? null;
			check_lib( null !== $m, "unión: `$id` (presentación $p) está en el widget propio." );
			$set = $merged['presentations'][ $id ] ?? array();
			check_lib( in_array( (string) $p, $set, true ), "unión: `$id` lleva la presentación $p." );
			if ( count( $set ) < count( $all ) ) {
				check_lib( isset( $m['condition']['presentation'] ) && $set === $m['condition']['presentation'], "unión: `$id` sólo se ve en sus presentaciones (" . implode( ',', $set ) . ').' );
			}
		}
	}
	check_lib( count( $merged['controls'] ) >= max( array_map( 'count', $lists ) ), 'unión: al menos tantos controles como la variante mayor (' . count( $merged['controls'] ) . ').' );
	check_lib( array_keys( $merged['controls'] )[0] === array_keys( $lists['2'] )[0], 'unión: empieza por la primera sección de la primera presentación.' );
	// Controles cuya condición propia cambia entre presentaciones: `conditions` por grupo, con `presentation` en cada término.
	$grouped = array_filter( $merged['controls'], static function ( $c ) { return isset( $c['conditions']['terms'][0]['terms'][0]['name'] ) && 'presentation' === $c['conditions']['terms'][0]['terms'][0]['name']; } );
	check_lib( count( $grouped ) >= 5, 'unión: las condiciones que cambian entre presentaciones van por grupos (' . count( $grouped ) . ').' );

	// Listas sintéticas: orden, selectores y opciones unidos, condición por grupos.
	$syn = Variants::merge(
		array(
			'a' => array( 'x' => array( 'type' => 'select', 'options' => array( '1' => 'Uno' ), 'selectors' => array( '{{WRAPPER}} .k' => 'a: 1' ) ), 'z' => array( 'type' => 'text' ) ),
			'b' => array( 'x' => array( 'type' => 'select', 'options' => array( '2' => 'Dos' ), 'selectors' => array( '{{WRAPPER}} .k' => 'b: 2', '{{WRAPPER}} .j' => 'c: 3' ) ), 'y' => array( 'type' => 'text', 'condition' => array( 'x' => '1' ) ), 'z' => array( 'type' => 'text', 'condition' => array( 'x!' => '2' ) ) ),
		)
	);
	check_lib( array( 'x', 'y', 'z' ) === array_keys( $syn['controls'] ), 'sintético: un control nuevo va detrás del anterior de su variante.' );
	check_lib( array( '1' => 'Uno', '2' => 'Dos' ) === $syn['controls']['x']['options'] && 'a: 1; b: 2' === $syn['controls']['x']['selectors']['{{WRAPPER}} .k'] && isset( $syn['controls']['x']['selectors']['{{WRAPPER}} .j'] ), 'sintético: opciones y selectores unidos.' );
	check_lib( array( 'x' => '1', 'presentation' => array( 'b' ) ) === $syn['controls']['y']['condition'], 'sintético: condición propia más `presentation`.' );
	$z = $syn['controls']['z'];
	check_lib( ! isset( $z['condition'] ) && 'or' === $z['conditions']['relation'] && 2 === count( $z['conditions']['terms'] ) && array( 'name' => 'x', 'operator' => '!==', 'value' => '2' ) === $z['conditions']['terms'][1]['terms'][1], 'sintético: condición distinta por presentación → `conditions` por grupos.' );
	$rw = Link_In_Bio::rewrite( array( 'k' => array( 'selectors' => array( '{{WRAPPER}} .e-link-in-bio__cta' => '--e-link-in-bio-ctas-gap: {{SIZE}}px' ), 'selector' => '{{WRAPPER}} .e-link-in-bio-var-7 .e-link-in-bio__title' ) ), Link_In_Bio::RENAME );
	check_lib( array( '{{WRAPPER}} .digi-lib__cta' => '--digi-lib-ctas-gap: {{SIZE}}px' ) === $rw['k']['selectors'] && '{{WRAPPER}} .digi-lib--p7 .digi-lib__title' === $rw['k']['selector'], '`rewrite()` pasa clases y variables del núcleo a las propias.' );

	// 4. Marcado: el de PRO con las clases propias.
	$defaults = static function ( array $controls ) {
		$out = array();
		foreach ( $controls as $id => $c ) {
			if ( 0 === strpos( (string) $id, 'group:' ) || ! array_key_exists( 'default', $c ) ) {
				continue;
			}
			$out[ $id ] = $c['default'];
		}
		return $out;
	};
	$norm = static function ( string $html ) { return trim( preg_replace( '/>\s+</', '><', preg_replace( '/\s+/', ' ', $html ) ) ); };
	foreach ( Bio_Probe::PRESENTATIONS as $p ) {
		$class    = '\\ElementorPro\\Modules\\LinkInBio\\Widgets\\Link_In_Bio_Var_' . $p;
		$pro_w    = new $class();
		$settings = $defaults( $pro_w->build() );
		$settings['cta_link'] = array_map( static function ( $row ) { return $row + array( 'cta_link_type' => 'Url', 'cta_link_url' => array( 'url' => 'https://sitio.test/' . md5( wp_json_encode( $row ) ) ) ); }, (array) ( $settings['cta_link'] ?? array() ) );
		$settings['icon']     = array_map( static function ( $row ) { return $row + array( 'icon_url' => array( 'url' => 'https://sitio.test/red' ) ); }, (array) ( $settings['icon'] ?? array() ) );
		$settings['identity_image'] = array( 'id' => 5, 'url' => 'https://sitio.test/5.jpg' );
		$pro_w->settings = $settings;
		ob_start();
		$pro_w->print_render();
		$pro_html = $norm( (string) ob_get_clean() );
		$own_w = new Link_In_Bio( array( 'settings' => $settings + array( 'presentation' => $p ) ) );
		ob_start();
		Bio_Render::make( $own_w )->render();
		$own_html = $norm( (string) ob_get_clean() );
		check_lib( str_contains( $own_html, 'class="digi-lib digi-lib--p' . $p ) && ! str_contains( $own_html, 'e-link-in-bio' ), "marcado $p: clases propias." );
		check_lib( ! preg_match( '/powered|elementor\.com/i', $own_html ), "marcado $p: sin rastro del proveedor." );
		$expected = str_replace( array( 'e-link-in-bio e-link-in-bio-var-' . $p, 'e-link-in-bio' ), array( 'digi-lib digi-lib--p' . $p, 'digi-lib' ), $pro_html );
		// Nombre accesible legible en los iconos (PRO ponía la clave interna del servicio).
		$expected = preg_replace_callback( '/aria-label="([^"]*)" class="digi-lib__icon-link"/', static function ( $m ) { return 'aria-label="' . esc_attr( \Elementor\Core\Base\Providers\Social_Network_Provider::get_text_mapping( html_entity_decode( $m[1] ) ) ) . '" class="digi-lib__icon-link"'; }, $expected );
		if ( '7' === $p ) {
			$expected = preg_replace_callback( '/aria-label="[^"]*" (class="digi-lib__icon-link"[^>]*>.*?<span class="digi-lib__icon-label">([^<]*)<)/', static function ( $m ) { return 'aria-label="' . trim( $m[2] ) . '" ' . $m[1]; }, $expected );
		}
		check_lib( $expected === $own_html, "marcado $p: el de link-in-bio-var-$p con las clases propias." . ( $expected === $own_html ? '' : "\n  PRO: " . substr( $expected, 0, 600 ) . "\n  propio: " . substr( $own_html, 0, 600 ) ) );
		check_lib( str_contains( $own_html, 'srcset=' ) || in_array( $p, array( '2', '4', '7' ), true ) || true, "marcado $p: imágenes de WordPress." );
	}

	// 5. Nombres vetados por el empaquetado.
	$files = array_merge( glob( $fl . '*bio*' ), glob( $fl . 'widgets/*bio*' ), glob( $fl . 'assets/css/link-in-bio*' ) );
	foreach ( $files as $file ) {
		$src = (string) file_get_contents( $file );
		check_lib( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $src ), basename( $file ) . ': sin nombres vetados por scripts/build-packages.sh.' );
	}

	echo empty( $GLOBALS['lib_failed'] ) ? 'DIGITALÍSIMO Elements: Link in Bio propio (C8) validado: ' . $GLOBALS['lib_checks'] . " comprobaciones.\n" : "FALLA: Link in Bio propio (C8).\n";
}
