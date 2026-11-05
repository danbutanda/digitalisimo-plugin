<?php
/**
 * Titular animado (`digitalisimo-animated-headline`, C2 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c2.md, frente
 * A), el widget propio que sustituye al Animated Headline de PRO, y su mapa
 * `modules/digitalisimo-convert/maps/animated-headline.php`:
 * - registro y recursos: hoja propia ≤ 4 KB (fotogramas comunes con variables, movimiento reducido, foco visible del
 *   enlace, sin el kit, `url()` ni clases del proveedor; sólo los colores por defecto de PRO) y script ≤ 6 KB sin jQuery;
 * - controles: se ejecutan los `register_controls()` de PRO y del widget propio con los mismos dobles y se comparan uno
 *   a uno (nombre, tipo, valor por defecto, opciones, condiciones, globales, selectores, grupos y sus exclusiones);
 * - marcado del servidor frente al de PRO con los mismos ajustes (mismo texto visible, mismas palabras, misma etiqueta y
 *   enlace), trazos SVG, variables de cada tipo de animación, escape y etiqueta validada;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos, responsivos, `__dynamic__`,
 *   `__globals__`, CSS personalizado con clases de PRO): ningún `pro.unmapped` y lo convertido se pinta con los textos
 *   de PRO.
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $attrs    = array();
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
		public function add_render_attribute( $el, $key, $value = null ) { $this->attrs[ $el ][ $key ][] = $value; }
		public function add_link_attributes( $el, $url ) {
			$this->add_render_attribute( $el, 'href', $url['url'] );
			if ( ! empty( $url['is_external'] ) ) { $this->add_render_attribute( $el, 'target', '_blank' ); }
			if ( ! empty( $url['nofollow'] ) ) { $this->add_render_attribute( $el, 'rel', 'nofollow' ); }
		}
		public function get_render_attribute_string( $el ) {
			$out = array();
			foreach ( $this->attrs[ $el ] ?? array() as $key => $values ) {
				$out[] = $key . '="' . htmlspecialchars( implode( ' ', $values ), ENT_QUOTES, 'UTF-8' ) . '"';
			}
			return implode( ' ', $out );
		}
		public function print_render_attribute_string( $el ) { echo $this->get_render_attribute_string( $el ); }
	}
	class Controls_Manager { const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const NUMBER = 'number'; const URL = 'url'; const CHOOSE = 'choose'; const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const TAB_STYLE = 'style'; }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Text_Stroke { public static function get_type() { return 'text-stroke'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Utils {
		public static function validate_html_tag( $tag ) { return in_array( strtolower( (string) $tag ), array( 'a', 'article', 'aside', 'div', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'main', 'nav', 'p', 'section', 'span' ), true ) ? $tag : 'div'; }
		public static function print_validated_html_tag( $tag ) { echo self::validate_html_tag( $tag ); }
		public static function print_unescaped_internal_string( $s ) { echo $s; }
	}
	class Plugin { public static $instance; public $experiments; }
}
namespace Elementor\Modules\DynamicTags {
	class Module { const TEXT_CATEGORY = 'text'; }
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace ElementorPro {
	class Plugin {
		public static function elementor() { return (object) array( 'experiments' => new class() { public function is_feature_active( $f ) { return ! empty( $GLOBALS['ah_optimized'] ); } } ); }
	}
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	function __( $v ) { return $v; }
	function _x( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8', false ); }
	function is_rtl() { return ! empty( $GLOBALS['ah_rtl'] ); }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_kses_post( $v ) { return preg_replace( '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ) ); }
	function check_ah( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return ! empty( $GLOBALS['ah_optimized'] ); } };

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/animated-headline/widgets/animated-headline.php';
	require $root . 'modules/digitalisimo-content/class-module.php';
	\Digitalisimo\Elements\Content\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Content\Module as Content;
	use Digitalisimo\Elements\Content\Widgets\Animated_Headline;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Translator;

	check_ah( Animated_Headline::class === Content::load( 'digitalisimo-animated-headline' ), 'El módulo de contenido carga el Titular animado.' );
	$render = static function ( $widget, array $settings ) {
		$widget->settings = $settings;
		$widget->attrs    = array();
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};
	// Texto visible: sin etiquetas ni el texto sólo para lectores de pantalla, espacios normalizados.
	$visible = static function ( string $html ) {
		$html = preg_replace( '@<span class="elementor-screen-only">.*?</span>@s', '', $html );
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
	};
	$checks = 0;
	$ok     = static function ( $value, $message ) use ( &$checks ) { check_ah( $value, $message ); $checks++; };

	// 1. Registro y recursos.
	$widget = new Animated_Headline();
	$ok( 'digitalisimo-animated-headline' === $widget->get_name() && 'Titular animado' === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories(), 'Nombre, título y categoría propios.' );
	$ok( array( 'digitalisimo-animated-headline' ) === $widget->get_style_depends() && array( 'digitalisimo-animated-headline' ) === $widget->get_script_depends(), 'Sólo la hoja y el script propios.' );
	$entry = Content::WIDGETS['digitalisimo-animated-headline'];
	$ok( 'Titular animado' === Content::TITLES['digitalisimo-animated-headline'] && 'widgets/class-animated-headline.php' === $entry['file'] && Animated_Headline::class === $entry['class'], 'El módulo lo declara con su archivo, su clase y su título.' );
	$css = (string) file_get_contents( $root . $entry['css'] );
	$js  = (string) file_get_contents( $root . $entry['js'] );
	$ok( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	$ok( strlen( $js ) > 0 && strlen( $js ) <= 6144, 'El script pesa ' . strlen( $js ) . ' B (tope 6 144).' );
	$ok( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( $css, 'elementor' ), 'La hoja no usa el kit, url() ni clases del proveedor.' );
	preg_match_all( '/#[0-9a-f]{3,6}\b|\bred\b/i', $css, $colors );
	$ok( array( '#aebcb9', 'red', '#0d0d0d' ) === array_values( array_unique( array_map( 'strtolower', $colors[0] ) ) ), 'Sólo los colores por defecto de PRO: ' . implode( ', ', array_unique( $colors[0] ) ) );
	$ok( str_contains( $css, '.digi-ah-link:focus-visible{outline:2px solid;outline-offset:2px}' ), 'Foco visible propio del enlace.' );
	$ok( str_contains( $css, '@media (prefers-reduced-motion:reduce){.digi-ah *,.digi-ah :after{animation:none!important;transition:none!important}.digi-ah--drawn svg path{opacity:1;stroke-dasharray:1500 1500}}' ), 'Sin animaciones ni transiciones con movimiento reducido: el trazo, en su estado final.' );
	$ok( str_contains( $css, '@keyframes digi-ah-in{' ) && str_contains( $css, '@keyframes digi-ah-out{' ) && 1 === substr_count( $css, '@keyframes digi-ah-in' ), 'Dos fotogramas comunes para las animaciones de palabras y letras.' );
	foreach ( array( '--t', '--a', '--b', '--c', '--o0', '--o6', '--d', '--e', '--f', '--p6', '--p1' ) as $var ) {
		$ok( str_contains( $css, 'var(' . $var . ')' ), "La hoja usa la variable $var del tipo." );
	}
	$ok( ! preg_match( '/jQuery|\$\(|fetch\(|XMLHttpRequest|https?:|innerHTML|elementorProFrontend/i', $js ), 'Script sin jQuery, sin peticiones, sin URL absolutas ni innerHTML.' );
	$ok( str_contains( $js, "rootMargin:'0px 0px -100px 0px'" ) && str_contains( $js, "matchMedia('(prefers-reduced-motion: reduce)')" ) && str_contains( $js, 'frontend/element_ready/global' ), 'Arranca al entrar en la pantalla (como PRO), respeta el movimiento reducido y se reinicia en el editor.' );
	$ok( str_contains( $js, 'anim:del||2500,letters:.02*del||50,typeL:.06*del||150,sel:.2*del||500,reveal:.24*del||600,revealWait:.6*del||1500' ) && str_contains( $js, 's.typeAnim=s.sel+800' ) && str_contains( $js, 'dur+.8*wait' ), 'Los mismos tiempos que el script de PRO.' );
	$source = '';
	foreach ( token_get_all( (string) file_get_contents( $root . 'modules/digitalisimo-content/' . $entry['file'] ) ) as $token ) {
		$source .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
	}
	$ok( '' !== $source && ! preg_match( '/jquery|wp_enqueue_script|wp_register_script|ElementorPro\\\\|elementor-headline/i', $source ), 'El widget no encola scripts a mano ni usa clases de PRO.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-content/' . $entry['file'], $entry['css'], $entry['js'], 'modules/digitalisimo-convert/maps/animated-headline.php' ) as $file ) {
		$ok( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file pasa la guarda de build-packages.sh." );
	}

	// 2. Los controles de PRO, uno a uno, en el widget propio.
	$pro = new \ElementorPro\Modules\AnimatedHeadline\Widgets\Animated_Headline();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
	( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	$ok( array_keys( $pro->controls ) === array_keys( $widget->controls ) && array_keys( $pro->sections ) === array_keys( $widget->sections ) && array_keys( $pro->groups ) === array_keys( $widget->groups ), 'Los mismos controles, secciones y grupos en el mismo orden.' );
	foreach ( $pro->sections as $name => $section ) {
		$ok( ( $section['tab'] ?? '' ) === ( $widget->sections[ $name ]['tab'] ?? '' ), "Sección $name en la misma pestaña." );
	}
	foreach ( $pro->controls as $name => $a ) {
		$b = $widget->controls[ $name ];
		foreach ( array( 'type', 'default', 'condition', 'conditions', 'responsive', 'size_units', 'range', 'tablet_default', 'mobile_default', 'label_block', 'classes', 'separator', 'global', 'render_type', 'frontend_available', 'dynamic', 'selectors_dictionary' ) as $key ) {
			if ( 'default' === $key && in_array( $a['type'], array( 'text', 'textarea' ), true ) && isset( $a['default'] ) ) {
				$ok( is_string( $b['default'] ?? null ) && '' !== $b['default'], "$name: texto por defecto propio." );
				continue;
			}
			$ok( ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ), "$name: «{$key}» distinto de PRO (" . json_encode( $a[ $key ] ?? null ) . ' / ' . json_encode( $b[ $key ] ?? null ) . ').' );
		}
		$ok( array_keys( (array) ( $a['options'] ?? array() ) ) === array_keys( (array) ( $b['options'] ?? array() ) ), "$name: las mismas opciones que PRO." );
		$ok( isset( $a['prefix_class'] ) === isset( $b['prefix_class'] ) && count( (array) ( $a['selectors'] ?? array() ) ) === count( (array) ( $b['selectors'] ?? array() ) ), "$name: aplica su estilo como PRO." );
		foreach ( (array) ( $b['selectors'] ?? array() ) as $selector => $rule ) {
			$ok( ! str_contains( $selector . $rule, 'elementor-headline' ) && ! preg_match( '/--(iteration-count|animation-duration|dynamic-text-color|typing-selected)/', $rule ), "$name: selector y variables propios." );
		}
	}
	foreach ( $pro->groups as $name => $a ) {
		$b = $widget->groups[ $name ];
		foreach ( array( 'type', 'global', 'exclude' ) as $key ) {
			$ok( ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ), "Grupo $name: «{$key}» distinto de PRO." );
		}
	}
	$ok( 'digi-ah--style-' === $widget->controls['headline_style']['prefix_class'] && array( '{{WRAPPER}} .digi-ah' => 'text-align: {{VALUE}}' ) === $widget->controls['alignment']['selectors'] && '{{WRAPPER}} .digi-ah__word' === $widget->groups['words_typography']['selector'], 'Clases de estilo, alineación y tipografía del texto animado sobre el marcado propio.' );
	$GLOBALS['ah_rtl'] = true;
	$rtl_pro = new \ElementorPro\Modules\AnimatedHeadline\Widgets\Animated_Headline();
	$rtl_own = new Animated_Headline();
	( new ReflectionMethod( $rtl_pro, 'register_controls' ) )->invoke( $rtl_pro );
	( new ReflectionMethod( $rtl_own, 'register_controls' ) )->invoke( $rtl_own );
	$ok( $rtl_pro->controls['alignment']['selectors_dictionary'] === $rtl_own->controls['alignment']['selectors_dictionary'] && 'end' === $rtl_own->controls['alignment']['selectors_dictionary']['left'], 'En RTL la alineación antigua izquierda/derecha se traduce igual que en PRO.' );
	$GLOBALS['ah_rtl'] = false;
	$map = require $root . 'modules/digitalisimo-convert/maps/animated-headline.php';
	$declared = array_merge( $map['keep'], array_keys( $map['groups'] ), $map['ui'] );
	foreach ( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) ) as $name ) {
		$ok( in_array( $name, $declared, true ), "$name: el mapa lo declara." );
	}
	foreach ( $map['defaults'] as $name => $value ) {
		$ok( $pro->controls[ $name ]['default'] === $value, "$name: el valor por defecto del mapa es el de PRO." );
	}
	$ok( 'ready' === $map['status'] && 'hide' === $map['panel'] && 'digitalisimo-animated-headline' === $map['target'] && 'animated-headline' === $map['source'], 'Mapa listo, con el widget de PRO fuera del panel.' );
	$ok( $widget->has_widget_inner_wrapper() && ( $GLOBALS['ah_optimized'] = true ) && ! $widget->has_widget_inner_wrapper() && ! $pro->has_widget_inner_wrapper(), 'Sin envoltorio interior con el marcado optimizado, como PRO.' );
	$GLOBALS['ah_optimized'] = false;

	// 3. Marcado frente al de PRO con los mismos ajustes (PRO pinta el trazo con su script; el propio, en el servidor).
	$pro_defaults = array();
	foreach ( $pro->controls as $name => $control ) {
		if ( array_key_exists( 'default', $control ) ) {
			$pro_defaults[ $name ] = $control['default'];
		}
	}
	$cases = array(
		'por defecto'          => array(),
		'rota, escritura'      => array( 'headline_style' => 'rotate' ),
		'rota, giro y enlace'  => array( 'headline_style' => 'rotate', 'animation_type' => 'flip', 'rotating_text' => "uno\ndos palabras\ntres", 'link' => array( 'url' => 'https://sitio.test/x', 'is_external' => 'on' ), 'tag' => 'h2' ),
		'resaltado con después' => array( 'marker' => 'x', 'before_text' => 'Antes &amp; <b>negrita</b>', 'highlighted_text' => 'medio', 'after_text' => 'después', 'tag' => 'p' ),
		'sin texto anterior'   => array( 'before_text' => '', 'after_text' => 'fin', 'tag' => 'span' ),
		'recorte con <em>'     => array( 'headline_style' => 'rotate', 'animation_type' => 'clip', 'rotating_text' => "<em class=\"a b\">uno dos</em>\r\ntres" ),
	);
	foreach ( $cases as $label => $settings ) {
		$settings = array_merge( $pro_defaults, $settings );
		$a        = $render( $pro, $settings );
		$b        = $render( $widget, $settings );
		$ok( $visible( $a ) === $visible( $b ), "$label: el mismo texto visible que PRO («" . $visible( $a ) . '» / «' . $visible( $b ) . '»).' );
		preg_match( '/<(h[1-6]|p|div|span)\b/', preg_replace( '/^\s*<a[^>]*>/', '', ltrim( $a ) ), $ta );
		preg_match( '/<(h[1-6]|p|div|span)\b/', preg_replace( '/^<a[^>]*>/', '', $b ), $tb );
		$ok( $ta[1] === $tb[1], "$label: la misma etiqueta ({$ta[1]})." );
		$ok( ( false !== strpos( $a, '<a ' ) ) === ( 0 === strpos( $b, '<a ' ) ), "$label: el mismo enlace." );
		$ok( substr_count( $a, 'elementor-headline-dynamic-text' ) === substr_count( $b, 'digi-ah__word' ) - substr_count( $b, 'digi-ah__word--on' ), "$label: las mismas palabras animadas." );
	}
	$b = $render( $widget, $pro_defaults );
	$ok( str_starts_with( $b, '<h3 class="digi-ah" data-digi-ah-style="highlight" data-digi-ah-loop="1" data-digi-ah-duration="1200" data-digi-ah-wait="8000">' ), 'Resaltado por defecto: h3 con los tiempos de PRO en datos.' );
	$ok( str_contains( $b, '<span class="digi-ah__plain digi-ah__wrap">This page is</span>' . "\n" . '<span class="digi-ah__dyn digi-ah__wrap"><span class="digi-ah__word digi-ah__word--on">Amazing</span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="' . Animated_Headline::SHAPES['circle'][0] . '"></path></svg></span></h3>' ), 'Texto anterior, palabra resaltada y trazo del círculo decorativo (aria-hidden), separados por un espacio como en PRO.' );
	foreach ( array_keys( $pro->controls['marker']['options'] ) as $marker ) {
		$ok( '' !== Animated_Headline::shape( $marker ), "Trazo de la forma $marker." );
	}
	$ok( 2 === substr_count( Animated_Headline::shape( 'x' ), '<path' ) && 2 === substr_count( Animated_Headline::shape( 'double' ), '<path' ) && '' === Animated_Headline::shape( 'otro' ), 'Las formas de dos trazos y una forma desconocida.' );
	$own_defaults = array();
	foreach ( $widget->controls as $name => $control ) {
		if ( array_key_exists( 'default', $control ) ) {
			$own_defaults[ $name ] = $control['default'];
		}
	}
	$b = $render( $widget, array_merge( $own_defaults, array( 'headline_style' => 'rotate' ) ) );
	$ok( str_contains( $b, 'class="digi-ah digi-ah--typing digi-ah--letters" data-digi-ah-style="rotate" data-digi-ah-loop="1" data-digi-ah-type="typing" data-digi-ah-delay="2500">' ) && ! str_contains( $b, ' style=' ) && ! str_contains( $b, '<svg' ), 'Escritura: por letras, sin variables en línea ni trazo.' );
	$ok( str_contains( $b, '<span class="digi-ah__word digi-ah__word--on">mejor</span>' . "\n" . '<span class="digi-ah__word">más&nbsp;grande</span>' . "\n" . '<span class="digi-ah__word">más&nbsp;rápida</span>' ), 'Una palabra por línea, la primera activa, espacios duros y un salto entre ellas (como PRO).' );
	foreach ( array( 'flip' => 'digi-ah--words', 'drop-in' => 'digi-ah--words', 'slide' => 'digi-ah--words', 'slide-down' => 'digi-ah--words', 'swirl' => 'digi-ah--letters digi-ah--fx', 'blinds' => 'digi-ah--letters digi-ah--fx', 'wave' => 'digi-ah--letters digi-ah--fx', 'clip' => '' ) as $type => $class ) {
		$b = $render( $widget, array_merge( $pro_defaults, array( 'headline_style' => 'rotate', 'animation_type' => $type ) ) );
		$ok( str_contains( $b, 'class="' . trim( 'digi-ah digi-ah--' . $type . ' ' . $class ) . '"' ), "$type: clases del tipo." );
		$style = Animated_Headline::type_style( $type );
		$ok( 'clip' === $type ? '' === $style && ! str_contains( $b, ' style=' ) : 11 === substr_count( $style, ':' ) && str_contains( $b, 'style="' . $style . '"' ), "$type: todas las variables del tipo en línea (ninguna se hereda)." );
	}
	$ok( str_contains( Animated_Headline::type_style( 'slide' ), '--t:.6s;--a:translateX(-100%);--b:translateX(10%);--c:none' ) && str_contains( Animated_Headline::type_style( 'wave' ), '--b:scale(1.2)' ) && str_contains( Animated_Headline::type_style( 'swirl' ), '--f:translateZ(-20px) rotateX(-90deg)' ), 'Fotogramas de PRO para deslizamiento, ola y remolino.' );
	$b = $render( $widget, array_merge( $pro_defaults, array( 'tag' => 'script', 'before_text' => '<script>alert(1)</script>Hola', 'after_text' => '<img src=x onerror=alert(1)>', 'headline_style' => 'rotate', 'animation_type' => 'wave', 'rotating_text' => '<b onclick="x()">uno</b>', 'loop' => '' ) ) );
	$ok( str_starts_with( $b, '<h3 ' ) && str_ends_with( $b, '</h3>' ) && ! str_contains( $b, '<script' ) && ! preg_match( '/onerror|onclick/', $b ) && str_contains( $b, 'data-digi-ah-loop="0"' ), 'Etiqueta fuera de la lista → h3; textos filtrados con wp_kses_post (también el que rota); sin bucle.' );
	$b = $render( $widget, array_merge( $pro_defaults, array( 'link' => array( 'url' => 'https://sitio.test/?a=1&b="2"', 'is_external' => 'on', 'nofollow' => 'on' ) ) ) );
	$ok( str_starts_with( $b, '<a href="https://sitio.test/?a=1&amp;b=&quot;2&quot;" target="_blank" rel="nofollow" class="digi-ah-link"><h3 ' ) && str_ends_with( $b, '</h3></a>' ), 'Enlace alrededor del titular, con sus atributos escapados y la clase del foco visible.' );
	$b = $render( $widget, array_merge( $pro_defaults, array( 'highlighted_text' => '' ) ) );
	$ok( str_contains( $b, '<span class="digi-ah__dyn digi-ah__wrap"><svg' ) && ! str_contains( $b, 'digi-ah__word' ), 'Sin texto resaltado: sólo el trazo, como PRO.' );

	// Fixture del navegador (`tests/fixtures/pro-animated-headline.html`, para elements-pro-animated-headline-chrome.mjs):
	// sus titulares salen de este mismo render; `--write-fixture` lo regenera.
	$fx = array(
		'h1' => array( 'highlight', array( 'highlight_animation_duration' => 600, 'highlight_iteration_delay' => 1000 ) ),
		'h2' => array( 'highlight', array( 'marker' => 'x', 'loop' => '', 'link' => array( 'url' => 'https://sitio.test/destino' ) ) ),
		'h3' => array( 'rotate', array( 'animation_type' => 'typing', 'rotate_iteration_delay' => 400, 'rotating_text' => "uno\ndos\ntres" ) ),
		'h4' => array( 'rotate', array( 'animation_type' => 'clip', 'rotate_iteration_delay' => 400, 'rotating_text' => "corto\nmucho más largo" ) ),
		'h5' => array( 'rotate', array( 'animation_type' => 'flip', 'rotate_iteration_delay' => 400, 'rotating_text' => "uno\ndos\ntres", 'loop' => '' ) ),
		'h6' => array( 'rotate', array( 'animation_type' => 'swirl', 'rotate_iteration_delay' => 400, 'rotating_text' => "alfa\nbeta" ) ),
		'h7' => array( 'rotate', array( 'animation_type' => 'wave', 'rotate_iteration_delay' => 400, 'rotating_text' => "alfa\nbeta" ) ),
		'h8' => array( 'rotate', array( 'animation_type' => 'blinds', 'rotate_iteration_delay' => 400, 'rotating_text' => "alfa\nbeta" ) ),
		'h9' => array( 'rotate', array( 'animation_type' => 'slide-down', 'rotate_iteration_delay' => 400, 'rotating_text' => "alfa\nbeta" ) ),
		'hz' => array( 'highlight', array() ),
	);
	$body = '';
	foreach ( $fx as $id => $w ) {
		$html  = $render( $widget, array_merge( $own_defaults, array( 'headline_style' => $w[0] ), $w[1] ) );
		$body .= ( 'hz' === $id ? "<div class=\"digi-test-spacer\"></div>\n" : '' ) . '<div class="elementor-element elementor-element-' . $id . ' elementor-widget elementor-widget-digitalisimo-animated-headline digi-ah--style-' . $w[0] . '" data-id="' . $id . '"><div class="elementor-widget-container">' . $html . "</div></div>\n";
	}
	$page    = "<!doctype html>\n<html lang=\"es\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>Titular animado</title>\n"
		. "<!-- Generado por tests/elements-pro-animated-headline.php --write-fixture: no editar a mano. Lo usa elements-pro-animated-headline-chrome.mjs. -->\n"
		. "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/elementor/assets/css/frontend.min.css\">\n<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-content/assets/css/animated-headline.css\">\n"
		. "<style>body{margin:0;font:16px/1.5 system-ui,sans-serif;color:#1a1a1a;background:#fff}.digi-test-page{padding:16px}.elementor-element{margin-bottom:24px}.digi-test-spacer{height:3000px}.digi-test-page{padding-bottom:600px}.elementor-element-h1{--digi-ah-duration:600ms}.digi-ah{font-size:32px}</style>\n"
		. "<script src=\"../../digitalisimo-elements/modules/digitalisimo-content/assets/js/animated-headline.js\" defer></script>\n</head>\n<body>\n<div class=\"digi-test-page\">\n" . $body . "</div>\n</body>\n</html>\n";
	$fixture = __DIR__ . '/fixtures/pro-animated-headline.html';
	if ( in_array( '--write-fixture', $argv ?? array(), true ) ) {
		file_put_contents( $fixture, $page );
		echo "Fixture escrito: tests/fixtures/pro-animated-headline.html\n";
	}
	$ok( is_file( $fixture ) && file_get_contents( $fixture ) === $page, 'El fixture del navegador es exactamente el marcado actual (regenerar con --write-fixture).' );

	// 4. Conversión de ajustes reales.
	$convert = static function ( array $settings ) {
		$log  = array();
		$tree = Converter::tree( array( array( 'id' => 'c1', 'elType' => 'container', 'elements' => array( array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'animated-headline', 'settings' => $settings ) ) ) ), $log );
		return array( $tree[0]['elements'][0], $log[0]['codes'] ?? array() );
	};
	list( $el, $codes ) = $convert( array() );
	$ok( 'digitalisimo-animated-headline' === $el['widgetType'] && 'w1' === $el['id'] && array() === $codes, 'Por defecto: el widget propio, el mismo id y ningún código.' );
	$ok( 'Esta página es' !== $el['settings']['before_text'] && 'This page is Amazing' === $visible( $render( $widget, $el['settings'] + $widget_defaults = array_map( static function ( $c ) { return $c['default'] ?? null; }, $widget->controls ) ) ), 'Lo convertido sin ajustes guardados se pinta con los textos de PRO («This page is Amazing»).' );
	$full = array(
		'headline_style'                    => 'rotate',
		'animation_type'                    => 'swirl',
		'rotating_text'                     => "Rápido\nSeguro",
		'before_text'                       => 'Somos',
		'after_text'                        => 'siempre',
		'loop'                              => '',
		'rotate_iteration_delay'            => 3000,
		'link'                              => array( 'url' => 'https://sitio.test/', 'is_external' => '', 'nofollow' => '' ),
		'tag'                               => 'h2',
		'alignment'                         => 'start',
		'alignment_tablet'                  => 'center',
		'alignment_mobile'                  => 'end',
		'title_color'                       => '#112233',
		'title_typography_typography'       => 'custom',
		'title_typography_font_size'        => array( 'unit' => 'px', 'size' => 50, 'sizes' => array() ),
		'title_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 30, 'sizes' => array() ),
		'text_stroke_text_stroke_type'      => 'yes',
		'title_text_shadow_text_shadow_type' => 'yes',
		'words_color'                       => '#ff0000',
		'words_typography_typography'       => 'custom',
		'words_typography_font_weight'      => '700',
		'animated_text_stroke_text_stroke_type' => 'yes',
		'animated_text_shadow_text_shadow_type' => 'yes',
		'highlighted_text_background_color' => '#000',
		'highlighted_text_color'            => '#fff',
		'marker_color'                      => '#00ff00',
		'stroke_width'                      => array( 'unit' => 'px', 'size' => 5, 'sizes' => array() ),
		'above_content'                     => 'yes',
		'rounded_edges'                     => 'yes',
		'highlight_animation_duration'      => 900,
		'highlight_iteration_delay'         => 4000,
		'marker'                            => 'curly',
		'highlighted_text'                  => 'algo',
		'__dynamic__'                       => array( 'before_text' => '[elementor-tag id="1" name="post-title" settings="%7B%7D"]' ),
		'__globals__'                       => array( 'words_color' => 'globals/colors?id=accent', 'title_typography_typography' => 'globals/typography?id=secondary' ),
		'_margin'                           => array( 'unit' => 'px', 'top' => '10' ),
		'custom_css'                        => 'selector .elementor-headline-dynamic-text.elementor-headline-text-active{color:red} selector .elementor-headline-animation-type-swirl .elementor-headline-dynamic-letter{x:y} selector.elementor-headline--style-rotate .elementor-headline{z:1}',
	);
	list( $el, $codes ) = $convert( $full );
	$ok( array( 'pro.css' ) === array_column( $codes, 'code' ), 'Completo: sólo el código de las clases del CSS personalizado (ningún pro.unmapped): ' . json_encode( array_column( $codes, 'code' ) ) );
	foreach ( $el['settings'] as $key => $value ) {
		if ( in_array( $key, array( Translator::MARKER, '__dynamic__', '__globals__', 'custom_css', '_margin' ), true ) ) {
			continue;
		}
		$base = preg_replace( '/_(tablet|mobile)$/', '', $key );
		$ok( isset( $widget->controls[ $base ] ) || (bool) array_filter( array_keys( $widget->groups ), static function ( $g ) use ( $key ) { return 0 === strpos( $key, $g . '_' ); } ), "$key cae en un control propio." );
	}
	$ok( $full['__dynamic__'] === $el['settings']['__dynamic__'] && $full['__globals__'] === $el['settings']['__globals__'] && $full['_margin'] === $el['settings']['_margin'], 'Etiquetas dinámicas, globales del kit y la pestaña Avanzado pasan tal cual.' );
	$ok( 'selector .digi-ah__word.digi-ah__word--on{color:red} selector .digi-ah--swirl .digi-ah__letter{x:y} selector.digi-ah--style-rotate .digi-ah{z:1}' === $el['settings']['custom_css'], 'El CSS personalizado apunta a las clases propias: ' . $el['settings']['custom_css'] );
	$b = $render( $widget, $el['settings'] + $widget_defaults );
	$ok( str_starts_with( $b, '<a href="https://sitio.test/" class="digi-ah-link"><h2 class="digi-ah digi-ah--swirl digi-ah--letters digi-ah--fx" data-digi-ah-style="rotate" data-digi-ah-loop="0" data-digi-ah-type="swirl" data-digi-ah-delay="3000" style="--t:.4s;' ) && 'Somos Rápido Seguro siempre' === $visible( $b ), 'Lo convertido se pinta con sus textos, su enlace, su etiqueta y su animación.' );
	list( $el2 ) = $convert( $el['settings'] );
	$ok( $el2['settings'] === $el['settings'], 'Una segunda pasada no cambia nada.' );

	echo "DIGITALÍSIMO Elements: Titular animado (" . count( $widget->controls ) . ' controles, ' . count( $widget->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . " B) y su mapa desde el Animated Headline de PRO validados ($checks comprobaciones).\n";
}
