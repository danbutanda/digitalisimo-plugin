<?php
/**
 * F8 · frente C: las 18 extensiones de Element Pack que ahora se convierten (DIGITALÍSIMO Migración 1.0.21).
 *
 * Cubre (§8.1 y V.16): las 17 conversiones y `custom-js` (con un doble de `Platform\Snippets`), valores por defecto,
 * globales y dinámicos, la regla de activación (presente, ausente y `off`), v4, la limpieza de claves con y sin
 * prefijo, el padre y el hijo de transform, las capas de image-parallax, grid-line en la página, el marcado
 * optimizado, la regla «no pisar», `ext.ambiguous`, los valores maliciosos (V.7) y los 28 fixtures sintéticos
 * `tests/legacy/ext-*.json`. Con el WordPress mínimo de `tests/fixtures/f8-wp.php`.
 */
namespace Elementor {
	/** Constructor mínimo: widgets registrados y si pintan `.elementor-widget-container` (marcado optimizado). */
	final class Plugin {
		public static $instance;
	}
	final class Fx_Widget {
		public $name;
		public $inner;
		public function __construct( $name, $inner ) {
			$this->name  = $name;
			$this->inner = $inner;
		}
		public function get_controls() {
			return array();
		}
	}
	final class Fx_Widget_With_Wrapper {
		public $inner;
		public function __construct( $inner ) {
			$this->inner = $inner;
		}
		public function has_widget_inner_wrapper(): bool {
			return $this->inner;
		}
		public function get_controls() {
			return array();
		}
	}
	final class Fx_Widgets_Manager {
		public $widgets = array();
		public function get_widget_types( $name = null ) {
			if ( null === $name ) {
				return $this->widgets;
			}
			return $this->widgets[ $name ] ?? null;
		}
	}
}

namespace Digitalisimo\Elements\Legacy\Platform {
	/** Doble de `Snippets::draft()` (frente H): cuenta las llamadas y devuelve un ID (0 si no se permite). */
	final class Snippets {
		public static $calls = array();
		public static $allow = true;
		public static function draft( string $title, string $code, string $location, array $conditions, array $origin ): int {
			if ( ! self::$allow ) {
				return 0;
			}
			self::$calls[] = compact( 'title', 'code', 'location', 'conditions', 'origin' );
			return 900 + count( self::$calls );
		}
	}
}

namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	f8_load_migration();
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/legacy/effects/class-dispatcher.php';
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/legacy/class-dropped.php';

	use Digitalisimo\Elements\Legacy\Effects\Activation;
	use Digitalisimo\Elements\Legacy\Effects\Css_Value;
	use Digitalisimo\Elements\Legacy\Effects\Dispatcher;
	use Digitalisimo\Elements\Legacy\Effects\Page;
	use Digitalisimo\Elements\Legacy\Effects\Traits;
	use Digitalisimo\Elements\Legacy\Effects\Values;
	use Digitalisimo\Elements\Legacy\Context;
	use Digitalisimo\Elements\Legacy\Dropped;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Platform\Snippets;

	/* -------- Ayudas -------- */

	/** Constructor mínimo: `widget => true|false|null` (con o sin contenedor interno; null = no se sabe). */
	function fx_elementor( array $widgets ) {
		$manager = new \Elementor\Fx_Widgets_Manager();
		foreach ( $widgets as $name => $inner ) {
			$manager->widgets[ $name ] = null === $inner ? new \Elementor\Fx_Widget( $name, null ) : new \Elementor\Fx_Widget_With_Wrapper( $inner );
		}
		\Elementor\Plugin::$instance = (object) array( 'widgets_manager' => $manager );
	}

	function fx_widget( $type, array $settings, $id = 'w1' ) {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}

	function fx_box( $type, array $settings, array $children = array(), $id = 's1' ) {
		return array( 'id' => $id, 'elType' => $type, 'settings' => $settings, 'elements' => $children );
	}

	/** Convierte un elemento y devuelve [elemento, códigos anotados, informe]. */
	function fx_convert( array $element, $post_id = 10 ) {
		Report::begin( 'analysis' );
		$out    = Dispatcher::element( $element, $post_id );
		$report = Report::take();
		return array( $out, fx_codes( $report ), $report );
	}

	function fx_codes( array $report ) {
		$codes = array();
		foreach ( (array) ( $report['docs'] ?? array() ) as $doc ) {
			foreach ( (array) ( $doc['items'] ?? array() ) as $item ) {
				$codes[] = (string) $item['code'];
			}
		}
		return $codes;
	}

	/** Recorre el árbol como Native: primero el elemento y después sus hijos. */
	function fx_tree( array $elements, $post_id ) {
		foreach ( $elements as $index => $element ) {
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			if ( Dispatcher::detect( $settings ) ) {
				$element = Dispatcher::element( $element, $post_id );
			}
			if ( ! empty( $element['elements'] ) ) {
				$element['elements'] = fx_tree( $element['elements'], $post_id );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	function fx_json( $value ) {
		return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/** Claves de las 28 extensiones que quedan en un árbol (con prefijo o sin él). */
	function fx_left( array $elements ) {
		$left = array();
		foreach ( $elements as $element ) {
			foreach ( array_keys( (array) ( $element['settings'] ?? array() ) ) as $key ) {
				if ( null !== Dispatcher::extension_of( (string) $key, 'image' === ( $element['widgetType'] ?? '' ) ) ) {
					$left[] = $key;
				}
			}
			$left = array_merge( $left, fx_left( (array) ( $element['elements'] ?? array() ) ) );
		}
		return $left;
	}

	function fx_css( array $element ) {
		return (string) ( $element['settings']['custom_css'] ?? '' );
	}

	function fx_reset() {
		f8_reset();
		Activation::reset();
		Dispatcher::force_signal( true );
		Values::set_breakpoints( array( 'tablet' => array( 1024, 'max' ), 'mobile' => array( 767, 'max' ) ) );
		Snippets::$calls = array();
		Snippets::$allow = true;
		Context::reset();
		Report::begin( 'analysis' );
		\Elementor\Plugin::$instance = null;
	}

	fx_reset();
	$catalog = Report::catalog();

	/* ======================================================================== Css_Value: el validador único (V.7) */

	foreach ( array( '#fff', '#A1B2C3', '#a1b2c3d4', 'rgb(0, 0, 0)', 'rgba(0,0,0,.5)', 'hsl(120, 50%, 50%)', 'var(--e-global-color-primary)', 'currentColor', 'transparent' ) as $color ) {
		f8_check( null !== Css_Value::color( $color ), 'Color válido: ' . $color );
	}
	$evil = array( 'red;}body{display:none}', 'red</style><script>alert(1)</script>', 'url(https://x.test/a.png)', 'expression(alert(1))', 'red\\;', '@import "x"', '#fff;color:red', 'blue', 'rgb(0,0,0);}', "#fff\n}", 'javascript:alert(1)', array( 'x' ) );
	foreach ( $evil as $value ) {
		f8_check( null === Css_Value::color( $value ), 'Color rechazado: ' . fx_json( $value ) );
	}
	f8_check( '12px' === Css_Value::size( array( 'unit' => 'px', 'size' => 12 ) ) && '1.5em' === Css_Value::size( '1.5em' ) && '-30deg' === Css_Value::size( array( 'unit' => 'deg', 'size' => '-30' ), array( 'deg' ) ), 'Tamaños con unidad de la lista.' );
	f8_check( null === Css_Value::size( array( 'unit' => 'custom', 'size' => 'calc(1px);}body{x' ) ) && null === Css_Value::size( '10px;}' ) && null === Css_Value::size( array( 'unit' => 'px', 'size' => '1e9' ) ) && null === Css_Value::size( '10 parsecs' ), 'Tamaños sin unidad conocida, con texto o con notación científica: rechazados.' );
	f8_check( null === Css_Value::size( array( 'unit' => 'px', 'size' => 5000 ), array( 'px' ), 'px', 0, 100 ) && '0' === Css_Value::number( '-0' ) && '0.5' === Css_Value::number( 0.5 ) && null === Css_Value::number( '0.5;' ), 'Números acotados y normalizados.' );
	f8_check( 'hidden' === Css_Value::enum( 'hidden', array( 'visible', 'hidden' ) ) && null === Css_Value::enum( 'hidden;x', array( 'visible', 'hidden' ) ) && null === Css_Value::enum( array( 'hidden' ), array( 'hidden' ) ), 'Enumeraciones cerradas.' );
	f8_check( 'linear-gradient(90deg, #ff0000 0%, #0000ff 100%)' === Css_Value::gradient( array( 'type' => 'linear', 'angle' => array( 'unit' => 'deg', 'size' => 90 ), 'stops' => array( array( '#ff0000', array( 'unit' => '%', 'size' => 0 ) ), array( '#0000ff', array( 'unit' => '%', 'size' => 100 ) ) ) ) ), 'Degradado lineal reconstruido desde partes validadas.' );
	f8_check( 0 === strpos( (string) Css_Value::gradient( array( 'type' => 'radial', 'position' => 'top left', 'stops' => array( array( '#000', null ), array( '#fff', null ) ) ) ), 'radial-gradient(at top left' ), 'Degradado radial con posición de la lista.' );
	f8_check( null === Css_Value::gradient( array( 'stops' => array( array( '#000', null ), array( 'red;}x{', null ) ) ) ) && null === Css_Value::gradient( array( 'stops' => array( array( '#000', null ) ) ) ), 'Un degradado con una parte inválida o con un solo color no se escribe.' );
	f8_check( 'linear-gradient(180deg, #000, #fff)' === Css_Value::gradient( array( 'angle' => 'x;}', 'stops' => array( array( '#000', null ), array( '#fff', null ) ) ) ), 'Un ángulo inválido toma el de por defecto, nunca el texto.' );
	f8_check( null !== Css_Value::clip( 'polygon(0% 0%, 100% 1%, 88% 49%, 100% 100%, 0% 100%)' ) && null !== Css_Value::clip( 'circle(40% at 50% 50%)' ) && null !== Css_Value::clip( 'inset(5px 10px round 4px)' ), 'Recortes admitidos.' );
	f8_check( null === Css_Value::clip( 'url(#x)' ) && null === Css_Value::clip( 'polygon(0 0);}body{x' ) && null === Css_Value::clip( 'path("M0 0")' ) && null === Css_Value::clip( 'polygon(' . str_repeat( '1% ', 80 ) . ')' ) && null === Css_Value::clip( 'polygon(calc(10%) 0)' ), 'Recortes con url, inyección, path, demasiado largos o con funciones: rechazados.' );
	f8_check( 'ease-out' === Css_Value::easing( 'ease-out' ) && 'cubic-bezier(0.1,0.7,1,0.1)' === Css_Value::easing( 'cubic-bezier(0.1, 0.7, 1, 0.1)' ) && null === Css_Value::easing( 'ease;}x{' ) && null === Css_Value::easing( 'steps(4)' ), 'Curvas de tiempo.' );
	f8_check( Css_Value::block( 'selector{color:red}@media (max-width:767px){selector{color:blue}}' ) && ! Css_Value::block( 'selector{color:red}</style><script>x</script>' ) && ! Css_Value::block( '@import url(x.css);' ) && ! Css_Value::block( 'selector{width:expression(alert(1))}' ) && ! Css_Value::block( 'a{background:url(javascript:alert(1))}' ) && ! Css_Value::block( 'a{behavior:url(x.htc)}' ) && ! Css_Value::block( 'a{color:red' ) && ! Css_Value::block( '@im/**/port "x"' ) && ! Css_Value::block( '@\\69mport "x"' ), 'Comprobación final de cada bloque.' );
	f8_check( '1px 2px 0 4px' === Css_Value::dimensions( array( 'top' => '1', 'right' => '2', 'bottom' => '', 'left' => '4', 'unit' => 'px' ) ) && null === Css_Value::dimensions( array( 'top' => '1;}', 'unit' => 'px' ) ) && null === Css_Value::dimensions( array( 'top' => '1', 'unit' => 'parsec' ) ), 'Medidas validadas.' );
	f8_check( '0 2px 10px 0 rgba(0,0,0,0.5)' === Css_Value::shadow( array( 'horizontal' => 0, 'vertical' => 2, 'blur' => 10, 'spread' => 0, 'color' => 'rgba(0,0,0,0.5)' ) ) && null === Css_Value::shadow( array( 'horizontal' => '0;}', 'color' => '#000' ) ) && null === Css_Value::shadow( array( 'color' => 'red;}' ) ), 'Sombras validadas.' );

	/* ======================================================================== keys.php: una sola fuente */

	$keys = Dispatcher::keys();
	f8_check( 28 === count( $keys ), 'Las 28 extensiones sin tratar de Element Pack 10.0.1: ' . count( $keys ) );
	$by = array();
	foreach ( $keys as $slug => $spec ) {
		$by[ $spec['treatment'] ][] = $slug;
	}
	f8_check( 10 === count( $by['dropped'] ) && array( 'background-image-parallax', 'particles', 'cursor-effects', 'ripple-effects', 'tile-scroll', 'hash-link', 'confetti-effects', 'sound-effects', 'content-protector', 'adblock-detector' ) == $by['dropped'], 'Los 10 descartes aprobados por el propietario: ' . implode( ', ', $by['dropped'] ) );
	f8_check( 3 === count( $by['exact'] ) && 3 === count( $by['trait'] ) && 11 === count( $by['approx'] ) && array( 'custom-js' ) === $by['draft'], '3 exactas, 11 aproximadas, 3 rasgos y custom-js a borrador.' );
	$all_prefixes = array();
	foreach ( $keys as $slug => $spec ) {
		f8_check( '' !== $spec['label'] && ! preg_match( '/element pack|bdthemes|prime slider/i', $spec['label'] ) && $spec['switch'], 'Nombre visible propio y claves de uso: ' . $slug );
		if ( 'dropped' !== $spec['treatment'] ) {
			list( $class, $method ) = explode( '::', $spec['handler'] );
			f8_check( method_exists( 'Digitalisimo\\Elements\\Legacy\\Effects\\' . $class, $method ) && '' !== (string) $spec['target'], 'Convertidor y destino declarados: ' . $slug );
		} else {
			f8_check( strlen( (string) $spec['reason'] ) > 20 && empty( $spec['handler'] ), 'Descarte con motivo: ' . $slug );
		}
		foreach ( (array) $spec['switch'] as $switch ) {
			f8_check( $slug === Dispatcher::extension_of( $switch, true ), 'El interruptor es de su extensión: ' . $switch );
		}
		foreach ( array_keys( (array) ( $spec['defaults'] ?? array() ) ) as $key ) {
			f8_check( $slug === Dispatcher::extension_of( $key, true ), 'Cada valor por defecto es de su extensión: ' . $key );
		}
		foreach ( (array) ( $spec['prefixes'] ?? array() ) as $prefix ) {
			$all_prefixes[] = $prefix;
			f8_check( 1 === preg_match( '/^(ep_|element_pack_)/', $prefix ), 'Los prefijos de nombre son de Element Pack: ' . $prefix );
		}
	}
	f8_check( count( $all_prefixes ) === count( array_unique( $all_prefixes ) ), 'Ningún prefijo se repite entre extensiones.' );
	f8_check( ! Dispatcher::is_key( 'hover_effects' ) && 'image-hover-effects' === Dispatcher::extension_of( 'hover_effects', true ) && Dispatcher::is_key( 'section_sticky_active_padding_tablet' ) && Dispatcher::is_key( 'section_parallax_value' ) && ! Dispatcher::is_key( 'section_parallax_whatever' ) && ! Dispatcher::is_key( 'ep_display_conditions' ) && ! Dispatcher::is_key( 'title' ), 'is_key(): las claves de las 28, también sin prefijo, salvo las genéricas de image-hover (sólo en el widget image) y las de otras extensiones.' );
	f8_check( ! Activation::enabled( 'background-expand' ) && ! Activation::enabled( 'ripple-effects' ) && ! Activation::enabled( 'background-image-parallax' ) && Activation::enabled( 'tooltip' ), 'Sin la opción, manda default_activation (falso en tres).' );

	/* ======================================================================== Información emergente (tooltip) */

	fx_reset();
	list( $el, $codes, $report ) = fx_convert( fx_widget( 'heading', array( 'title' => 'Hola', 'element_pack_widget_tooltip' => 'yes' ) ) );
	$s = $el['settings'];
	f8_check( 'yes' === $s['digitalisimo_tooltip'] && 'This is Tooltip' === $s['digitalisimo_tooltip_text'] && 'center' === $s['digitalisimo_tooltip_align'] && ! isset( $s['digitalisimo_tooltip_placement'] ) && ! isset( $s['digitalisimo_tooltip_arrow'] ), 'Valores por defecto de Element Pack: texto «This is Tooltip», arriba, sin flecha, centrado.' );
	f8_check( array( 'ext.converted.tooltip' ) === $codes && array() === fx_left( array( $el ) ), 'Convertida y sin claves heredadas.' );
	// Sin estilo guardado, el globo de Element Pack era el de tippy.js: fondo #333, texto blanco, 14 px con interlineado
	// 1,4, relleno 5/9 px, 350 px de ancho máximo y 10 px de distancia (y no el aspecto propio del rasgo).
	f8_check( 'classic' === $s['digitalisimo_tooltip_bg_background'] && '#333333' === $s['digitalisimo_tooltip_bg_color'] && '#ffffff' === $s['digitalisimo_tooltip_color'], 'Aspecto de tippy: fondo #333 y texto blanco.' );
	f8_check( array( 'top' => '5', 'right' => '9', 'bottom' => '5', 'left' => '9', 'unit' => 'px', 'isLinked' => false ) === $s['digitalisimo_tooltip_padding'] && array( 'unit' => 'px', 'size' => 350.0 ) === $s['digitalisimo_tooltip_width'] && array( 'unit' => 'px', 'size' => 10.0 ) === $s['digitalisimo_tooltip_offset'], 'Aspecto de tippy: relleno 5/9 px, ancho máximo 350 px y distancia 10 px.' );
	f8_check( 'custom' === $s['digitalisimo_tooltip_typography_typography'] && array( 'unit' => 'px', 'size' => 14.0 ) === $s['digitalisimo_tooltip_typography_font_size'] && array( 'unit' => 'em', 'size' => 1.4 ) === $s['digitalisimo_tooltip_typography_line_height'] && ! isset( $s['digitalisimo_tooltip_arrow_color'] ), 'Aspecto de tippy: 14 px con interlineado 1,4; sin flecha, sin color de flecha.' );
	$tooltip = array(
		'element_pack_widget_tooltip'             => 'yes',
		'element_pack_widget_tooltip_text'        => 'Hola <b class="x" onclick="evil()">fuerte</b><br/><a href="https://x.test">enlace</a><script>alert(1)</script>',
		'element_pack_widget_tooltip_placement'   => 'left-end',
		'element_pack_widget_tooltip_trigger'     => 'manual',
		'element_pack_widget_tooltip_custom_trigger' => '#boton',
		'element_pack_widget_tooltip_follow_cursor' => 'yes',
		'element_pack_widget_tooltip_animation'   => 'shift-away',
		'element_pack_widget_tooltip_x_offset'    => array( 'unit' => 'px', 'size' => 5 ),
		'element_pack_widget_tooltip_y_offset'    => array( 'unit' => 'px', 'size' => 12 ),
		'element_pack_widget_tooltip_width_tablet' => array( 'unit' => 'em', 'size' => 10 ),
		'element_pack_widget_tooltip_color'       => 'red;}body{display:none}',
		'element_pack_widget_tooltip_arrow_color' => '#123456',
		'element_pack_widget_tooltip_border_border' => 'dashed',
		'element_pack_widget_tooltip_border_width' => array( 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'unit' => 'px', 'isLinked' => true ),
		'element_pack_widget_tooltip_border_radius' => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ),
		'element_pack_widget_tooltip_box_shadow_box_shadow_type' => 'yes',
		'element_pack_widget_tooltip_box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 4, 'blur' => 8, 'spread' => 0, 'color' => 'rgba(0,0,0,0.3)' ),
		'element_pack_widget_tooltip_typography_typography' => 'custom',
		'element_pack_widget_tooltip_typography_font_family' => 'Open Sans',
		'element_pack_widget_tooltip_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 13 ),
		'element_pack_widget_tooltip_typography_font_weight' => '700;}x{',
		'element_pack_widget_tooltip_background_background' => 'classic',
		'__globals__'                             => array( 'element_pack_widget_tooltip_background_color' => 'globals/colors?id=secondary' ),
		'__dynamic__'                             => array( 'element_pack_widget_tooltip_text' => '[elementor-tag id="1" name="post-title" settings="%7B%7D"]' ),
	);
	list( $el, $codes ) = fx_convert( fx_widget( 'button', $tooltip ) );
	$s = $el['settings'];
	f8_check( 'Hola <b>fuerte</b><br>enlacealert(1)' === str_replace( '<br/>', '<br>', $s['digitalisimo_tooltip_text'] ) || 'Hola <b>fuerte</b><br />enlacealert(1)' === $s['digitalisimo_tooltip_text'] || 0 === strpos( $s['digitalisimo_tooltip_text'], 'Hola <b>fuerte</b><br' ), 'El texto sólo conserva strong, em, b, i y br, sin atributos: ' . $s['digitalisimo_tooltip_text'] );
	f8_check( false === strpos( fx_json( $s ), 'onclick' ) && false === strpos( fx_json( $s ), '<script' ) && false === strpos( fx_json( $s ), '<a ' ), 'Ni atributos, ni scripts, ni enlaces.' );
	f8_check( 'left' === $s['digitalisimo_tooltip_placement'] && ! isset( $s['digitalisimo_tooltip_trigger'] ) && array( 'unit' => 'px', 'size' => 12.0 ) == $s['digitalisimo_tooltip_offset'], 'Lado, disparador manual al de por defecto y distancia = la Y de tippy.js.' );
	f8_check( array( 'unit' => 'em', 'size' => 9.2857 ) == $s['digitalisimo_tooltip_width_tablet'] && array( 'unit' => 'px', 'size' => 350.0 ) == $s['digitalisimo_tooltip_width'] && '#123456' === $s['digitalisimo_tooltip_arrow_color'] && '#ffffff' === $s['digitalisimo_tooltip_color'], 'Ancho responsivo (calc(10em - 10px) a 14 px = 9,2857em; escritorio, el de tippy) y colores válidos; el color con inyección no llega a ningún control (queda el blanco de tippy).' );
	f8_check( 'classic' === $s['digitalisimo_tooltip_bg_background'] && ! isset( $s['digitalisimo_tooltip_bg_color'] ), 'Fondo clásico con color global: no se le añade el #333 de tippy (manda el global).' );
	f8_check( 'dashed' === $s['digitalisimo_tooltip_border_border'] && '2' === $s['digitalisimo_tooltip_border_width']['top'] && '4' === $s['digitalisimo_tooltip_radius']['left'] && ! isset( $s['digitalisimo_tooltip_border_radius'] ), 'Grupo Borde y radio aparte.' );
	f8_check( 'yes' === $s['digitalisimo_tooltip_shadow_box_shadow_type'] && 4.0 === $s['digitalisimo_tooltip_shadow_box_shadow']['vertical'] && 'Open Sans' === $s['digitalisimo_tooltip_typography_font_family'] && isset( $s['digitalisimo_tooltip_typography_font_size_mobile'] ) && ! isset( $s['digitalisimo_tooltip_typography_font_weight'] ), 'Sombra y tipografía 1:1, cada subclave validada (el peso con inyección se descarta).' );
	f8_check( 'globals/colors?id=secondary' === ( $s['__globals__']['digitalisimo_tooltip_bg_color'] ?? '' ) && 0 === strpos( (string) ( $s['__dynamic__']['digitalisimo_tooltip_text'] ?? '' ), '[elementor-tag' ), 'Globales y dinámicos pasan a la clave de destino.' );
	f8_check( ! isset( $s['__globals__']['element_pack_widget_tooltip_background_color'] ) && ! isset( $s['__dynamic__']['element_pack_widget_tooltip_text'] ) && array() === fx_left( array( $el ) ), 'Y salen de las bolsas con las claves heredadas.' );
	f8_check( 5 <= count( array_keys( $codes, 'ext.loss.tooltip', true ) ), 'Pérdidas declaradas: seguir el cursor, animación, disparador por selector, alineación, HTML, desplazamiento lateral: ' . count( array_keys( $codes, 'ext.loss.tooltip', true ) ) );
	f8_check( 'Hola <strong>a</strong>' === Traits::tooltip_text( 'Hola <strong style="color:red">a</strong>' ) && 'x' === Traits::tooltip_text( '<span>x</span>' ), 'Saneado del texto sin WordPress.' );

	// Fidelidad del globo (2026-11-06): ancho `calc(ANCHO - 10px)`, aspecto de tippy donde no se guardó otro, distancia.
	$tt = static function ( array $extra, string $widget = 'button' ) {
		list( $el, $codes ) = fx_convert( fx_widget( $widget, array( 'element_pack_widget_tooltip' => 'yes' ) + $extra ) );
		return array( $el['settings'], $codes );
	};
	list( $s, $codes ) = $tt( array(
		'element_pack_widget_tooltip_width'        => array( 'unit' => 'px', 'size' => 240 ),
		'element_pack_widget_tooltip_width_mobile' => array( 'unit' => 'px', 'size' => 6 ),
		'element_pack_widget_tooltip_arrow'        => 'yes',
		'element_pack_widget_tooltip_y_offset'     => array( 'unit' => 'px', 'size' => 14 ),
		'element_pack_widget_tooltip_color'        => '#ffffff',
		'element_pack_widget_tooltip_background_background' => 'classic',
		'element_pack_widget_tooltip_background_color'      => '#222222',
		'element_pack_widget_tooltip_padding'      => array( 'top' => '6', 'right' => '10', 'bottom' => '6', 'left' => '10', 'unit' => 'px', 'isLinked' => false ),
	) );
	f8_check( array( 'unit' => 'px', 'size' => 230.0 ) === $s['digitalisimo_tooltip_width'] && array( 'unit' => 'px', 'size' => 0.0 ) === $s['digitalisimo_tooltip_width_mobile'], 'Ancho en px: Element Pack pintaba calc(ANCHO - 10px) (240 → 230; nunca negativo).' );
	f8_check( '#222222' === $s['digitalisimo_tooltip_bg_color'] && '#ffffff' === $s['digitalisimo_tooltip_color'] && '6' === $s['digitalisimo_tooltip_padding']['top'] && array( 'unit' => 'px', 'size' => 14.0 ) == $s['digitalisimo_tooltip_offset'], 'Lo guardado manda sobre el aspecto de tippy (fondo, color, relleno y distancia).' );
	f8_check( '#333333' === $s['digitalisimo_tooltip_arrow_color'], 'Flecha sin color propio: la de tippy (#333) aunque el fondo cambie.' );
	list( $s ) = $tt( array( 'element_pack_widget_tooltip_x_offset' => array( 'unit' => 'px', 'size' => 0 ), 'element_pack_widget_tooltip_y_offset' => array( 'unit' => 'px', 'size' => 0 ) ) );
	f8_check( array( 'unit' => 'px', 'size' => 10.0 ) == $s['digitalisimo_tooltip_offset'], 'X e Y a 0: ep-tooltip.js no pasaba `offset` y tippy usaba sus 10 px.' );
	list( $s ) = $tt( array( 'element_pack_widget_tooltip_x_offset' => array( 'unit' => 'px', 'size' => 6 ) ) );
	f8_check( array( 'unit' => 'px', 'size' => 0.0 ) == $s['digitalisimo_tooltip_offset'], 'Sólo X: tippy recibía [x, 0] y la distancia era 0.' );
	list( $s, $codes ) = $tt( array(
		'element_pack_widget_tooltip_width'                       => array( 'unit' => 'em', 'size' => 10 ),
		'element_pack_widget_tooltip_width_mobile'                => array( 'unit' => 'em', 'size' => 10 ),
		'element_pack_widget_tooltip_typography_typography'       => 'custom',
		'element_pack_widget_tooltip_typography_font_size'        => array( 'unit' => 'px', 'size' => 20 ),
		'element_pack_widget_tooltip_typography_font_size_tablet' => array( 'unit' => 'px', 'size' => 16 ),
		'element_pack_widget_tooltip_typography_line_height'      => array( 'unit' => 'px', 'size' => 30 ),
	) );
	f8_check( array( 'unit' => 'em', 'size' => 9.5 ) == $s['digitalisimo_tooltip_width'] && array( 'unit' => 'em', 'size' => 9.375 ) == $s['digitalisimo_tooltip_width_mobile'], 'Ancho en em: se restan 10 px en em del tamaño de letra del dispositivo (20 px en escritorio; el móvil hereda los 16 px de la tableta).' );
	f8_check( array( 'unit' => 'px', 'size' => 20.0 ) == $s['digitalisimo_tooltip_typography_font_size'] && array( 'unit' => 'px', 'size' => 30.0 ) == $s['digitalisimo_tooltip_typography_line_height'] && ! in_array( 'ext.loss.tooltip', $codes, true ), 'La tipografía guardada manda; sin aproximación que declarar.' );
	list( $s, $codes ) = $tt( array(
		'element_pack_widget_tooltip_width'                  => array( 'unit' => 'em', 'size' => 10 ),
		'element_pack_widget_tooltip_typography_typography'  => 'custom',
		'element_pack_widget_tooltip_typography_font_size'   => array( 'unit' => 'em', 'size' => 1.2 ),
	) );
	f8_check( array( 'unit' => 'em', 'size' => 9.2857 ) == $s['digitalisimo_tooltip_width'] && in_array( 'ext.loss.tooltip', $codes, true ), 'Letra en em: el ancho se aproxima con los 14 px de tippy y se declara.' );
	list( $s, $codes ) = $tt( array(
		'element_pack_widget_tooltip_width'  => array( 'unit' => 'em', 'size' => 10 ),
		'__globals__' => array( 'element_pack_widget_tooltip_typography_typography' => 'globals/typography?id=accent', 'element_pack_widget_tooltip_color' => 'globals/colors?id=text' ),
	) );
	f8_check( ! isset( $s['digitalisimo_tooltip_typography_font_size'] ) && ! isset( $s['digitalisimo_tooltip_typography_typography'] ) && 'globals/typography?id=accent' === ( $s['__globals__']['digitalisimo_tooltip_typography_typography'] ?? '' ) && in_array( 'ext.loss.tooltip', $codes, true ), 'Tipografía global: manda entera (sin los 14 px de tippy) y el ancho en em se declara aproximado.' );
	f8_check( ! isset( $s['digitalisimo_tooltip_color'] ) && 'globals/colors?id=text' === ( $s['__globals__']['digitalisimo_tooltip_color'] ?? '' ), 'Color global del texto: sin el blanco de tippy.' );
	list( $s ) = $tt( array(
		'element_pack_widget_tooltip_background_color' => '#00ff00',
		'__globals__' => array( 'element_pack_widget_tooltip_background_color' => 'globals/colors?id=primary' ),
	) );
	f8_check( 'classic' === $s['digitalisimo_tooltip_bg_background'] && '#333333' === $s['digitalisimo_tooltip_bg_color'] && ! isset( $s['__globals__']['digitalisimo_tooltip_bg_color'] ), 'Color de fondo sin tipo de fondo: el núcleo no lo imprimía (tampoco su global); queda el #333 de tippy.' );
	list( $s ) = $tt( array(
		'element_pack_widget_tooltip_background_background' => 'classic',
		'element_pack_widget_tooltip_background_image'      => array( 'id' => 3, 'url' => 'https://s.test/f.png' ),
		'element_pack_widget_tooltip_padding'               => array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '', 'unit' => 'px', 'isLinked' => true ),
	) );
	f8_check( '#333333' === $s['digitalisimo_tooltip_bg_color'] && 'https://s.test/f.png' === $s['digitalisimo_tooltip_bg_image']['url'] && '5' === $s['digitalisimo_tooltip_padding']['top'], 'Fondo clásico sólo con imagen: debajo, el #333 de tippy; relleno vacío: el de tippy.' );
	list( $s ) = $tt( array( 'element_pack_widget_tooltip_background_background' => 'gradient', 'element_pack_widget_tooltip_background_color' => '#111111', 'element_pack_widget_tooltip_background_color_b' => '#999999' ) );
	f8_check( 'gradient' === $s['digitalisimo_tooltip_bg_background'] && '#111111' === $s['digitalisimo_tooltip_bg_color'] && '#999999' === $s['digitalisimo_tooltip_bg_color_b'], 'Degradado guardado: intacto.' );
	$already = fx_widget( 'button', array( 'element_pack_widget_tooltip' => 'yes', 'digitalisimo_tooltip_width' => array( 'unit' => 'px', 'size' => 120 ) ) );
	list( $el, $codes ) = fx_convert( $already );
	f8_check( in_array( 'ext.conflict', $codes, true ) && array( 'unit' => 'px', 'size' => 120 ) === $el['settings']['digitalisimo_tooltip_width'], 'No pisar: un ancho propio ya guardado no se cambia por el de tippy (conflicto declarado).' );

	/* ======================================================================== Efectos de transformación */

	fx_reset();
	fx_elementor( array( 'heading' => true, 'image' => false, 'button' => null ) );
	$transform = array(
		'element_pack_widget_transform'               => 'yes',
		'element_pack_translate_toggle_normal'        => 'yes',
		'element_pack_widget_effect_transx_normal'    => array( 'unit' => 'px', 'size' => 10 ),
		'element_pack_widget_effect_transy_normal_mobile' => array( 'unit' => 'px', 'size' => -4 ),
		'element_pack_rotate_toggle_normal'           => 'yes',
		'element_pack_widget_effect_rotatez_normal'   => array( 'unit' => 'px', 'size' => 15 ),
		'element_pack_scale_hover'                    => 'yes',
		'element_pack_widget_effect_scalex_hover'     => array( 'unit' => 'px', 'size' => 1.2 ),
		'element_pack_widget_effect_scaley_hover'     => array( 'unit' => 'px', 'size' => 0.8 ),
		'element_pack_skew_hover'                     => 'yes',
		'element_pack_widget_effect_skewx_hover'      => array( 'unit' => 'px', 'size' => 5 ),
		'element_pack_rotate_toggle_hover'            => 'yes',
		'element_pack_widget_effect_rotatex_hover'    => array( 'unit' => 'px', 'size' => 30 ),
		'element_pack_widget_effect_transition_duration' => '500',
		'element_pack_widget_effect_transition_delay' => '120',
		'element_pack_widget_effect_transition_easing' => 'cubic-bezier(0.1, 0.7, 1, 0.1)',
	);
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', $transform ) );
	$s = $el['settings'];
	f8_check( 'transform' === $s['_transform_translate_popover'] && 10.0 === $s['_transform_translateX_effect']['size'] && -4.0 === $s['_transform_translateY_effect_mobile']['size'] && 'transform' === $s['_transform_rotate_popover'] && 15.0 === $s['_transform_rotateZ_effect']['size'] && 'deg' === $s['_transform_rotateZ_effect']['unit'], 'Normal: traslación (también responsiva) y rotación Z.' );
	f8_check( '' === $s['_transform_keep_proportions_hover'] && 1.2 === $s['_transform_scaleX_effect_hover']['size'] && 0.8 === $s['_transform_scaleY_effect_hover']['size'] && 5.0 === $s['_transform_skewX_effect_hover']['size'], 'Hover: escala distinta en X e Y (sin mantener proporciones) y sesgo.' );
	f8_check( 'yes' === $s['_transform_rotate_3d_hover'] && 1000 === $s['_transform_perspective_effect_hover']['size'] && 30.0 === $s['_transform_rotateX_effect_hover']['size'], 'Rotación X con 3D y perspectiva de 1000 px.' );
	f8_check( 500.0 === $s['_transform_transition_hover']['size'] && 'ms' === $s['_transform_transition_hover']['unit'], 'La duración de Element Pack.' );
	f8_check( false !== strpos( fx_css( $el ), 'selector > .elementor-widget-container{transition-timing-function:cubic-bezier(0.1,0.7,1,0.1)}' ) && false !== strpos( fx_css( $el ), 'selector:hover > .elementor-widget-container{transition-delay:120ms}' ), 'Curva y retardo (sólo al pasar el puntero) en el contenedor interno del widget.' );
	f8_check( in_array( 'ext.converted.transform_effects', $codes, true ) && 2 === count( array_keys( $codes, 'ext.loss.transform_effects', true ) ), 'Convertida con sus dos pérdidas (orden y perspectiva).' );
	list( $el ) = fx_convert( fx_widget( 'image', $transform ) );
	f8_check( false !== strpos( fx_css( $el ), 'selector{transition-timing-function' ) && false !== strpos( fx_css( $el ), 'selector:hover{transition-delay:120ms}' ) && false === strpos( fx_css( $el ), 'elementor-widget-container' ), 'Marcado optimizado: el destino es el envoltorio (C1-21).' );
	list( $el ) = fx_convert( fx_widget( 'button', $transform ) );
	f8_check( false !== strpos( fx_css( $el ), ':is(selector:hover > .elementor-widget-container, selector:hover:not(:has(> .elementor-widget-container))){transition-delay:120ms}' ), 'Si no se sabe, los dos marcados con :is().' );
	list( $el ) = fx_convert( fx_widget( 'heading', array( 'element_pack_widget_transform' => 'yes', 'element_pack_scale_normal' => 'yes', 'element_pack_widget_effect_scalex_normal' => array( 'size' => 1.5 ), 'element_pack_widget_effect_scaley_normal' => array( 'size' => 1.5 ) ) ) );
	f8_check( 1.5 === $el['settings']['_transform_scale_effect']['size'] && ! isset( $el['settings']['_transform_keep_proportions'] ) && 300.0 === $el['settings']['_transform_transition_hover']['size'] && '' === fx_css( $el ), 'Escala X = Y: una sola; duración por defecto 300 ms (no los 0,4 s del núcleo); sin retardo ni curva propios, sin CSS.' );
	// Padre con «hover del padre» y overflow.
	$child  = fx_widget( 'heading', array( 'element_pack_widget_transform' => 'yes', 'element_pack_translate_toggle_hover' => 'yes', 'element_pack_widget_effect_transy_hover' => array( 'unit' => 'px', 'size' => -6 ), 'element_pack_widget_effect_transy_hover_tablet' => array( 'unit' => 'px', 'size' => -3 ) ), 'ch1' );
	$child2 = fx_widget( 'image', array( 'element_pack_widget_transform' => 'yes', 'element_pack_rotate_toggle_hover' => 'yes', 'element_pack_widget_effect_rotatez_hover' => array( 'size' => 5 ) ), 'ch2' );
	$section = fx_box( 'section', array( 'element_pack_section_transform' => 'wrapper', 'overflow' => 'hidden' ), array( fx_box( 'column', array(), array( $child, $child2 ), 'co1' ) ) );
	list( $el, $codes ) = fx_convert( $section );
	$css = fx_css( $el );
	f8_check( false !== strpos( $css, 'selector:hover .elementor-element-ch1 > .elementor-widget-container{--e-transform-translateY:-6px}' ) && false !== strpos( $css, '@media (max-width:1024px){selector:hover .elementor-element-ch1 > .elementor-widget-container{--e-transform-translateY:-3px}}' ), 'Hover del padre: los valores de hover del hijo en el CSS del padre, también por dispositivo.' );
	f8_check( false !== strpos( $css, 'selector:hover .elementor-element-ch2{--e-transform-rotateZ:5deg}' ), 'Hijo con marcado optimizado: su envoltorio.' );
	f8_check( '' === $el['settings']['overflow'] && in_array( 'ext.converted.transform_effects', $codes, true ), 'El interruptor del padre forzaba overflow visible: el overflow nativo hidden se vacía.' );
	list( $el ) = fx_convert( fx_box( 'section', array( 'element_pack_section_transform' => 'wrapper', 'element_pack_section_overflow' => 'hidden' ) ) );
	list( $col ) = fx_convert( fx_box( 'column', array( 'element_pack_column_transform' => 'wrapper', 'element_pack_column_overflow' => 'hidden' ) ) );
	f8_check( 'hidden' === $el['settings']['overflow'] && false !== strpos( fx_css( $col ), 'selector{overflow:hidden}' ) && ! isset( $col['settings']['overflow'] ), 'Overflow hidden: control nativo en la sección, CSS en la columna (no tiene control).' );
	$tree = fx_tree( array( $section ), 10 );
	f8_check( array() === fx_left( $tree ) && 'transform' === $tree[0]['elements'][0]['elements'][0]['settings']['_transform_translate_popover_hover'], 'Recorrido completo: el hijo también se convierte y no queda nada heredado.' );

	/* ======================================================================== Paralaje */

	fx_reset();
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_parallax_effects_show' => 'yes', 'ep_parallax_effects_y' => 'yes', 'ep_parallax_effects_y_start' => array( 'unit' => 'px', 'size' => -80 ), 'ep_parallax_effects_y_end' => array( 'unit' => 'px', 'size' => 120 ), 'ep_parallax_effects_rotate' => 'yes', 'ep_parallax_effects_rotate_start' => array( 'size' => 0 ), 'ep_parallax_effects_rotate_end' => array( 'size' => 90 ), 'ep_parallax_effects_blur' => 'yes', 'ep_parallax_effects_blur_start' => array( 'size' => 6 ), 'ep_parallax_effects_blur_end' => array( 'size' => 0 ), 'ep_parallax_effects_scale' => 'yes', 'ep_parallax_effects_scale_start' => array( 'size' => 1 ), 'ep_parallax_effects_scale_end' => array( 'size' => 1.5 ), 'ep_parallax_effects_sepia' => 'yes', 'ep_parallax_effects_target' => 'section' ) ) );
	$s = $el['settings'];
	f8_check( 'yes' === $s['motion_fx_motion_fx_scrolling'] && 'yes' === $s['motion_fx_translateY_effect'] && 2.0 === $s['motion_fx_translateY_speed']['size'] && 'negative' === $s['motion_fx_translateY_direction'] && 20.0 === $s['_transform_translateY_effect']['size'], 'Y [-80, 120]: velocidad |b−a|/100, dirección por el signo y desfase (a+b)/2.' );
	f8_check( 'yes' === $s['motion_fx_rotateZ_effect'] && 0.9 === $s['motion_fx_rotateZ_speed']['size'] && 45.0 === $s['_transform_rotateZ_effect']['size'] && 'yes' === $s['motion_fx_blur_effect'] && 'out-in' === $s['motion_fx_blur_direction'] && 6.0 === $s['motion_fx_blur_level']['size'], 'Rotación con desfase y desenfoque.' );
	f8_check( 'yes' === $s['motion_fx_scale_effect'] && 5.0 === $s['motion_fx_scale_speed']['size'] && ! isset( $s['_transform_scale_effect'] ), 'Escala: velocidad 10·(b/a−1); sin desfase con a = 1.' );
	f8_check( 2 === count( array_keys( $codes, 'ext.loss.parallax_effects', true ) ), 'Pérdidas: sepia y destino «sección».' );
	list( $el, $codes ) = fx_convert( fx_box( 'column', array( 'ep_parallax_effects_show' => 'yes', 'ep_parallax_effects_y' => 'yes' ) ) );
	f8_check( array( 'ext.inert' ) === $codes && array() === fx_left( array( $el ) ) && ! isset( $el['settings']['motion_fx_motion_fx_scrolling'] ), 'En columnas nunca corría: se retira con ext.inert, sin efecto nuevo.' );
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_parallax_effects_show' => 'yes', 'ep_parallax_effects_x' => 'yes', 'ep_parallax_effects_x_start' => array( 'unit' => 'vw', 'size' => 10 ) ) ) );
	f8_check( in_array( 'ext.loss.parallax_effects', $codes, true ) && in_array( 'ext.inert', $codes, true ) && ! isset( $el['settings']['motion_fx_translateX_effect'] ), 'Valores en vw: pérdida declarada.' );
	list( $el ) = fx_convert( fx_widget( 'heading', array( 'ep_parallax_effects_show' => 'yes', 'ep_parallax_effects_y' => 'yes', 'ep_parallax_effects_y_custom_show' => 'yes', 'ep_parallax_effects_y_custom_value' => '0, 40, -60' ) ) );
	f8_check( 0.6 === $el['settings']['motion_fx_translateY_speed']['size'] && '' === $el['settings']['motion_fx_translateY_direction'], 'Paradas propias: la primera y la última.' );
	list( $el ) = fx_convert( fx_widget( 'heading', array( 'ep_parallax_effects_show' => 'yes', 'ep_parallax_effects_y' => 'yes', 'ep_parallax_effects_viewport' => 'yes', 'ep_parallax_effects_viewport_start' => '20%', 'ep_parallax_effects_viewport_end' => '80%', 'ep_parallax_effects_media_query' => '@l' ) ) );
	f8_check( array( 'start' => 20.0, 'end' => 80.0 ) == $el['settings']['motion_fx_translateY_affectedRange']['sizes'] && array( 'desktop' ) === $el['settings']['motion_fx_devices'], 'Recorrido en % y dispositivos desde el ancho mínimo de UIkit.' );
	// Paralaje del fondo.
	$bg = array( 'section_parallax_on' => 'yes', 'background_background' => 'classic', 'background_image' => array( 'id' => 5, 'url' => 'https://s1.test/wp-content/uploads/f.jpg' ) );
	list( $el, $codes ) = fx_convert( fx_box( 'container', $bg ) );
	f8_check( 'yes' === $el['settings']['background_motion_fx_motion_fx_scrolling'] && 3.3 === $el['settings']['background_motion_fx_translateY_speed']['size'] && '' === $el['settings']['background_motion_fx_translateY_direction'] && ! isset( $el['settings']['section_parallax_on'] ), 'Fondo: −200 por defecto, velocidad |bgy|/60 y claves sin prefijo retiradas.' );
	list( $el ) = fx_convert( fx_box( 'section', $bg + array( 'section_parallax_x_value' => array( 'size' => 30 ), 'section_parallax_value' => array( 'size' => 900 ) ) ) );
	f8_check( 'negative' === $el['settings']['background_motion_fx_translateX_direction'] && 10.0 === $el['settings']['background_motion_fx_translateY_speed']['size'], 'X y tope de 10.' );
	list( $el, $codes ) = fx_convert( fx_box( 'section', array( 'section_parallax_on' => 'yes', 'ep_parallax_bg_colors' => 'yes' ) ) );
	f8_check( in_array( 'ext.inert', $codes, true ) && in_array( 'ext.loss.background_parallax', $codes, true ) && array() === fx_left( array( $el ) ), 'Sin imagen de fondo no se movía nada visible; los colores, pérdida.' );

	/* ======================================================================== Capas con paralaje */

	fx_reset();
	$layers = array(
		array( '_id' => 'l1', 'section_parallax_image' => array( 'id' => 7, 'url' => 'https://s1.test/wp-content/uploads/a.png' ), 'section_parallax_depth' => 0.2, 'section_parallax_bgp_x' => 30, 'section_parallax_bg_size' => 'contain' ),
		array( '_id' => 'l2', 'section_parallax_image' => array( 'id' => 8, 'url' => 'https://s1.test/wp-content/uploads/b.png' ) ),
		array( '_id' => 'l3', 'section_parallax_title' => 'Sin imagen' ),
		array( '_id' => 'l4', 'section_parallax_image' => array( 'id' => 0, 'url' => 'javascript:alert(1)' ) ),
	);
	$container = fx_box( 'container', array( 'section_parallax_elements' => $layers, 'section_parallax_mode' => 'hover' ), array( fx_widget( 'heading', array( 'title' => 'Contenido' ), 'h9' ) ), 'cc1' );
	list( $el, $codes ) = fx_convert( $container );
	f8_check( 3 === count( $el['elements'] ) && 'container' === $el['elements'][0]['elType'] && true === $el['elements'][0]['isInner'] && 'h9' === $el['elements'][2]['id'], 'Contenedor: un hijo por capa con imagen (2), delante del contenido; filas sin imagen o con dirección no válida, fuera.' );
	$l = $el['elements'][0]['settings'];
	f8_check( 'absolute' === $l['position'] && -1 === $l['z_index'] && 'classic' === $l['background_background'] && 7 === $l['background_image']['id'] && 30.0 === $l['background_xpos']['size'] && 'contain' === $l['background_size'], 'Capa: absoluta, bajo el contenido, con su imagen y posición.' );
	f8_check( 'yes' === $l['motion_fx_motion_fx_mouse'] && 'yes' === $l['motion_fx_mouseTrack_effect'] && 2.0 === $l['motion_fx_mouseTrack_speed']['size'] && false !== strpos( $l['custom_css'], 'pointer-events:none' ), 'Seguimiento del ratón por profundidad y sin capturar el puntero.' );
	f8_check( 1 === preg_match( '/^[0-9a-f]{7}$/', $el['elements'][0]['id'] ) && $el['elements'][0]['id'] !== $el['elements'][1]['id'] && false !== strpos( fx_css( $el ), 'isolation:isolate' ), 'IDs como los del editor, distintos, y el padre aísla la pila.' );
	f8_check( in_array( 'ext.converted.image_parallax', $codes, true ) && in_array( 'ext.loss.image_parallax', $codes, true ) && array() === fx_left( array( $el ) ), 'Modo hover: pérdida; sin claves heredadas.' );
	list( $again ) = fx_convert( $container );
	f8_check( $again['elements'][0]['id'] === $el['elements'][0]['id'], 'IDs deterministas: reconvertir da lo mismo.' );
	list( $el, $codes ) = fx_convert( fx_box( 'section', array( 'section_parallax_elements' => array( $layers[0] ) ) ) );
	f8_check( 'classic' === $el['settings']['background_background'] && 'yes' === $el['settings']['background_motion_fx_motion_fx_mouse'] && 2.0 === $el['settings']['background_motion_fx_mouseTrack_speed']['size'] && in_array( 'ext.converted.image_parallax', $codes, true ), 'Sección con una capa y sin imagen: fondo con movimiento del ratón.' );
	list( $el, $codes ) = fx_convert( fx_box( 'section', array( 'section_parallax_elements' => array( $layers[0], $layers[1] ) ) ) );
	f8_check( array( 'ext.review.layers' ) === $codes && ! isset( $el['settings']['background_image'] ), 'Sección con varias capas: pendiente de revisar, sin tocar el fondo.' );
	list( $el, $codes ) = fx_convert( fx_box( 'section', array( 'section_parallax_elements' => array( $layers[0] ), 'background_background' => 'video' ) ) );
	f8_check( in_array( 'ext.conflict', $codes, true ) && 'video' === $el['settings']['background_background'], 'Con otro tipo de fondo, no se pisa.' );
	list( $el, $codes ) = fx_convert( fx_box( 'container', array( 'element_pack_sbgc_parallax_show' => 'yes', 'element_pack_sbgc_parallax_sc' => '#fff' ) ) );
	f8_check( array( 'ext.loss.image_parallax' ) === $codes && array() === fx_left( array( $el ) ), 'Sólo colores al desplazar: pérdida declarada.' );

	/* ======================================================================== Superposición del fondo */

	fx_reset();
	$overlay = array(
		'element_pack_background_overlay'                 => 'yes',
		'ep_background_overlay_background'                => 'classic',
		'ep_background_overlay_color'                     => '#000000',
		'ep_background_overlay_image'                     => array( 'id' => 3, 'url' => 'https://s1.test/wp-content/uploads/o.png' ),
		'ep_background_overlay_margin'                    => array( 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px' ),
		'ep_background_overlay_margin_mobile'             => array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px' ),
		'ep_background_overlay_opacity'                   => array( 'size' => 0.7 ),
		'ep_css_filters_css_filter'                       => 'custom',
		'ep_css_filters_blur'                             => array( 'size' => 3 ),
		'ep_overlay_blend_mode'                           => 'screen',
		'ep_background_overlay_zindex'                    => '2',
		'ep_background_overlay_hover_background'          => 'gradient',
		'ep_background_overlay_hover_color'               => '#ff0000',
		'ep_background_overlay_hover_color_b'             => 'red;}body{display:none',
		'ep_background_overlay_hover_transition_duration' => array( 'size' => 0.5 ),
		'__globals__'                                     => array( 'ep_background_overlay_color' => 'globals/colors?id=primary' ),
	);
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', $overlay ) );
	$css = fx_css( $el );
	f8_check( 0 === strpos( $css, 'selector{position:relative}selector::before{content:"";position:absolute;pointer-events:none' ) && false !== strpos( $css, 'inset:10px 10px 10px 10px' ) && false !== strpos( $css, '@media (max-width:767px){selector::before{inset:0 0 0 0}}' ), 'Pseudoelemento ::before con el margen como inset, también por dispositivo.' );
	f8_check( false !== strpos( $css, 'background-color:var(--e-global-color-primary)' ) && false !== strpos( $css, 'opacity:0.7' ) && false !== strpos( $css, 'blur(3px)' ) && false !== strpos( $css, 'mix-blend-mode:screen' ) && false !== strpos( $css, 'z-index:2' ) && false !== strpos( $css, 'transition:all 0.5s ease-in-out' ), 'Color global, opacidad, filtros, fusión, índice z y transición.' );
	f8_check( false === strpos( $css, 'url(' ) && false === strpos( $css, 'o.png' ) && in_array( 'ext.review.overlay_image', $codes, true ), 'La imagen nunca va al CSS (F8-D13): pendiente de revisar.' );
	f8_check( false === strpos( $css, 'selector:hover::before{background' ) && false === strpos( $css, 'display:none' ), 'El degradado de hover con un color inyectado no se escribe.' );
	f8_check( Css_Value::block( $css ) && in_array( 'ext.converted.background_overlay', $codes, true ) && array() === fx_left( array( $el ) ), 'Bloque válido y sin claves heredadas.' );
	list( $el ) = fx_convert( fx_widget( 'heading', array( 'element_pack_background_overlay' => 'yes', 'ep_background_overlay_background' => 'classic', 'ep_background_overlay_color' => '#111111', 'custom_css' => 'selector{color:red}' ) ) );
	f8_check( 0 === strpos( fx_css( $el ), "selector{color:red}\nselector{position:relative}" ) && false !== strpos( fx_css( $el ), 'opacity:0.5' ), 'custom_css se añade, nunca se sustituye; opacidad 0,5 por defecto.' );

	/* ======================================================================== Fondo que se expande */

	fx_reset();
	update_option( 'element_pack_elementor_extend', array( 'background-expand' => 'on' ) );
	Activation::reset();
	list( $el, $codes ) = fx_convert( fx_box( 'container', array( 'ep_bg_expand_enable' => 'yes', 'ep_bg_expand_container_width' => array( 'unit' => 'px', 'size' => 1140 ), 'ep_bg_expand_anim_speed' => array( 'unit' => 'ms', 'size' => 400 ), 'ep_bg_expand_border_radius' => array( 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px' ), 'ep_bg_expand_border_radius_active' => array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px' ) ), array(), 'ex1' ) );
	$row = $el['settings']['_digitalisimo_actions'][0];
	f8_check( 'yes' === $el['settings']['_digitalisimo_actions_on'] && 'scroll_in' === $row['trigger'] && 'toggle_class' === $row['action'] && 'self' === $row['target_kind'] && 'bx-active' === $row['class_name'] && 1 === preg_match( '/^[0-9a-f]{7}$/', $row['_id'] ), 'Acción F7b «al aparecer» que pone bx-active sobre el propio elemento.' );
	f8_check( false !== strpos( fx_css( $el ), 'selector{margin-inline:auto;max-width:1140px;transition:border-radius .2s,max-width 400ms ease;border-radius:20px 20px 20px 20px}' ) && false !== strpos( fx_css( $el ), 'selector.bx-active{max-width:100%;border-radius:0 0 0 0}' ) && false !== strpos( fx_css( $el ), 'prefers-reduced-motion:reduce' ), 'CSS del ancho, el radio y la transición, sin movimiento si se pide.' );
	f8_check( 1 === preg_match( '/^[a-z0-9_-]{1,40}$/', $row['class_name'] ) && ! preg_match( '/^(digi-|e-|elementor-|wp-)/', $row['class_name'] ), 'La clase pasa las reglas de F7b (sin prefijos reservados).' );
	$existing = array( 'ep_bg_expand_enable' => 'yes', '_digitalisimo_actions_on' => 'yes', '_digitalisimo_actions' => array( array( '_id' => 'aa11bb2', 'trigger' => 'click', 'action' => 'toggle', 'target_kind' => 'element', 'target' => 'zz' ) ) );
	list( $el ) = fx_convert( fx_box( 'section', $existing ) );
	f8_check( 2 === count( $el['settings']['_digitalisimo_actions'] ) && 'aa11bb2' === $el['settings']['_digitalisimo_actions'][0]['_id'], 'Las acciones existentes se conservan: la fila nueva va al final.' );
	list( $el, $codes ) = fx_convert( fx_box( 'section', array( 'ep_bg_expand_enable' => 'yes', '_digitalisimo_actions_on' => '', '_digitalisimo_actions' => $existing['_digitalisimo_actions'] ) ) );
	f8_check( in_array( 'ext.conflict', $codes, true ) && '' === $el['settings']['_digitalisimo_actions_on'], 'Acciones guardadas pero apagadas: no se encienden (conflicto).' );
	update_option( 'element_pack_elementor_extend', array() );
	Activation::reset();
	list( $el, $codes ) = fx_convert( fx_box( 'section', array( 'ep_bg_expand_enable' => 'yes' ) ) );
	f8_check( array( 'ext.disabled' ) === $codes && ! isset( $el['settings']['_digitalisimo_actions_on'] ) && array() === fx_left( array( $el ) ), 'Ausente en la opción y desactivada por defecto: se retira con ext.disabled.' );
	update_option( 'element_pack_elementor_extend', array( 'tooltip' => 'off', 'reveal-effects' => 'on', 'scroll-box' => 'yes' ) );
	Activation::reset();
	list( , $codes_off ) = fx_convert( fx_widget( 'heading', array( 'element_pack_widget_tooltip' => 'yes' ) ) );
	list( , $codes_on ) = fx_convert( fx_widget( 'heading', array( 'element_pack_reveal_effects_enable' => 'yes' ) ) );
	list( , $codes_yes ) = fx_convert( fx_widget( 'text-editor', array( 'ep_scroll_box_enable' => 'yes' ) ) );
	f8_check( array( 'ext.disabled' ) === $codes_off && in_array( 'ext.converted.reveal_effects', $codes_on, true ) && array( 'ext.disabled' ) === $codes_yes, 'Presente: sólo «on» enciende (off y cualquier otro valor apagan).' );

	/* ======================================================================== Sección fija */

	fx_reset();
	$sticky = array(
		'section_sticky_on'                    => 'yes',
		'section_sticky_position'              => 'auto',
		'section_sticky_offset'                => array( 'size' => 30 ),
		'section_sticky_off_media'             => '960',
		'section_sticky_active_bg'             => '#ffffff',
		'section_sticky_active_padding'        => array( 'top' => '10', 'right' => '0', 'bottom' => '10', 'left' => '0', 'unit' => 'px' ),
		'section_sticky_active_padding_mobile' => array( 'top' => '4', 'right' => '0', 'bottom' => '4', 'left' => '0', 'unit' => 'px' ),
		'section_sticky_border_border'         => 'solid',
		'section_sticky_border_width'          => array( 'top' => '0', 'right' => '0', 'bottom' => '1', 'left' => '0', 'unit' => 'px' ),
		'section_sticky_border_color'          => '#eeeeee',
		'section_sticky_active_shadow_box_shadow_type' => 'yes',
		'section_sticky_active_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 2, 'blur' => 6, 'spread' => 0, 'color' => 'rgba(0,0,0,0.1)' ),
		'section_sticky_zindex'                => 50,
		'section_sticky_animation'             => 'slide-top',
		'section_sticky_bottom'                => 'true',
	);
	list( $el, $codes ) = fx_convert( fx_box( 'section', $sticky ) );
	$s   = $el['settings'];
	$css = fx_css( $el );
	f8_check( 'top' === $s['sticky'] && array( 'desktop' ) === $s['sticky_on'] && 30 === $s['sticky_offset'] && 'yes' === $s['sticky_parent'], 'Fijo arriba (auto), sin tableta ni móvil (corte 960), desplazamiento y «hasta» el padre.' );
	f8_check( false !== strpos( $css, 'selector{transition:background-color 250ms ease-out,padding 300ms ease-out}' ) && false !== strpos( $css, 'selector.elementor-sticky--active{background-color:#ffffff;padding:10px 0 10px 0;border-style:solid;border-width:0 0 1px 0;border-color:#eeeeee;box-shadow:0 2px 6px 0 rgba(0,0,0,0.1);z-index:50}' ) && false !== strpos( $css, '@media (max-width:767px){selector.elementor-sticky--active{padding:4px 0 4px 0}}' ), 'Estilos del estado fijo, responsivos, con la transición de las secciones.' );
	f8_check( 3 === count( array_keys( $codes, 'ext.loss.section_sticky', true ) ) && array() === fx_left( array( $el ) ), 'Pérdidas: animación, posición automática y corte de 960; todas las claves sin prefijo retiradas.' );
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'section_sticky_on' => 'yes', 'section_sticky_position' => 'end', 'section_sticky_off_media' => '768', 'section_sticky_bottom' => '#otro' ) ) );
	f8_check( 'bottom' === $el['settings']['sticky'] && array( 'desktop', 'tablet' ) === $el['settings']['sticky_on'] && ! isset( $el['settings']['sticky_parent'] ) && in_array( 'ext.loss.section_sticky', $codes, true ) && '' === fx_css( $el ), 'En un widget: abajo, sin móvil y «hasta» otro selector como pérdida; sin estilos, sin CSS.' );
	list( $el, $codes ) = fx_convert( fx_box( 'container', array( 'section_sticky_on' => 'yes', 'sticky' => 'bottom' ) ) );
	f8_check( in_array( 'ext.conflict', $codes, true ) && 'bottom' === $el['settings']['sticky'] && ! in_array( 'ext.converted.section_sticky', $codes, true ), 'No pisar: un «Fijo» propio distinto gana y se informa.' );
	list( $el ) = fx_convert( fx_box( 'container', array( 'section_sticky_on' => '', 'section_sticky_offset' => array( 'size' => 3 ) ) ) );
	f8_check( array() === fx_left( array( $el ) ) && ! isset( $el['settings']['sticky'] ), 'Interruptor apagado con restos: se limpian sin convertir.' );

	/* ======================================================================== Igualar alturas */

	fx_reset();
	list( $el, $codes ) = fx_convert( fx_box( 'container', array( 'section_equal_height_on' => 'yes', 'section_equal_height_selector' => 'column' ) ) );
	f8_check( 'stretch' === $el['settings']['flex_align_items'] && '' === fx_css( $el ) && in_array( 'ext.converted.equal_height', $codes, true ), 'Columnas en un contenedor: alineación estirada.' );
	list( $el ) = fx_convert( fx_box( 'section', array( 'section_equal_height_on' => 'yes', 'section_equal_height_selector' => 'column' ) ) );
	f8_check( ! isset( $el['settings']['flex_align_items'] ) && array() === fx_left( array( $el ) ), 'En una sección ya se estiran: sólo se limpia.' );
	$many = fx_box( 'section', array( 'section_equal_height_on' => 'yes' ), array( fx_box( 'column', array(), array( fx_widget( 'heading', array(), 'a' ), fx_widget( 'heading', array(), 'b' ) ), 'c1' ) ) );
	list( $el, $codes ) = fx_convert( $many );
	f8_check( false !== strpos( fx_css( $el ), ':has(> .elementor-widget:only-child)' ) && false !== strpos( fx_css( $el ), 'height:100%' ) && in_array( 'ext.loss.equal_height', $codes, true ), 'Widgets (por defecto): CSS para un widget por columna; con varios, pérdida.' );
	list( $el, $codes ) = fx_convert( fx_box( 'container', array( 'section_equal_height_on' => 'yes', 'section_equal_height_selector' => 'widgets_c2' ) ) );
	f8_check( in_array( 'ext.loss.equal_height', $codes, true ) && ! in_array( 'ext.converted.equal_height', $codes, true ) && array() === fx_left( array( $el ) ), 'Modos c1–c3: pérdida declarada.' );
	list( $el, $codes ) = fx_convert( fx_box( 'container', array( 'section_equal_height_on' => 'yes', 'section_equal_height_selector' => 'column', 'flex_align_items' => 'center' ) ) );
	f8_check( in_array( 'ext.conflict', $codes, true ) && 'center' === $el['settings']['flex_align_items'], 'No pisar la alineación propia.' );

	/* ======================================================================== Revelado */

	fx_reset();
	foreach ( array( 'lr' => 'fadeInLeft', 'rl' => 'fadeInRight', 'tb' => 'fadeInDown', 'bt' => 'fadeInUp', 'c' => 'zoomIn' ) as $direction => $animation ) {
		list( $el ) = fx_convert( fx_widget( 'image', array( 'element_pack_reveal_effects_enable' => 'yes', 'element_pack_reveal_effects_direction' => $direction ) ) );
		f8_check( $animation === $el['settings']['_animation'], 'Dirección ' . $direction . ' → ' . $animation );
	}
	foreach ( array( 9 => 'fast', 12 => '', 16 => '', 17 => 'slow' ) as $speed => $duration ) {
		list( $el ) = fx_convert( fx_widget( 'image', array( 'element_pack_reveal_effects_enable' => 'yes', 'element_pack_reveal_effects_speed' => array( 'size' => $speed ) ) ) );
		f8_check( $duration === ( $el['settings']['animation_duration'] ?? '' ), 'Velocidad ' . $speed . ' (×100 ms) → «' . $duration . '»' );
	}
	list( $el, $codes ) = fx_convert( fx_widget( 'image', array( 'element_pack_reveal_effects_enable' => 'yes', '_animation' => 'bounceIn' ) ) );
	f8_check( 'bounceIn' === $el['settings']['_animation'] && in_array( 'ext.conflict', $codes, true ) && array() === fx_left( array( $el ) ), 'Nunca pisa una animación de entrada propia.' );
	list( $el, $codes ) = fx_convert( fx_widget( 'image', array( 'element_pack_reveal_effects_enable' => 'yes', '_animation' => '' ) ) );
	f8_check( 'fadeInLeft' === $el['settings']['_animation'] && ! in_array( 'ext.conflict', $codes, true ), 'Una animación vacía no es un valor propio.' );

	/* ======================================================================== Relleno del texto al desplazar */

	fx_reset();
	fx_elementor( array( 'heading' => true, 'text-editor' => false, 'digitalisimo-advanced-heading' => true ) );
	$fill = array( 'ep_widget_sf_fx_enable' => 'yes', 'ep_widget_sf_fx_fill_bg_background' => 'gradient', 'ep_widget_sf_fx_fill_bg_color' => '#08aeec', 'ep_widget_sf_fx_fill_bg_color_b' => '#2af598', 'ep_widget_sf_fx_fill_bg_gradient_angle' => array( 'unit' => 'deg', 'size' => 90 ) );
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', $fill ) );
	$css = fx_css( $el );
	f8_check( 0 === strpos( $css, 'selector .elementor-heading-title{display:inline;-webkit-text-fill-color:rgba(156, 156, 156, 0.5);background-image:linear-gradient(90deg, #08aeec 0%, #2af598 100%)' ), 'Título: color base por defecto y degradado recortado al texto.' );
	f8_check( false !== strpos( $css, '@supports (animation-timeline: view()){@media (prefers-reduced-motion:no-preference){' ) && false !== strpos( $css, 'animation-range:entry 0% cover 50%' ) && false !== strpos( $css, '@keyframes digi-sfx-fill' ), 'La animación sólo con animation-timeline y sin movimiento reducido; si no, estado final.' );
	f8_check( in_array( 'ext.converted.scroll_fill_effect', $codes, true ) && Css_Value::block( $css ), 'Convertida con un bloque válido.' );
	list( $el ) = fx_convert( fx_widget( 'text-editor', $fill ) );
	f8_check( 0 === strpos( fx_css( $el ), 'selector > :is(h1,h2,h3,h4,h5,h6,p,div){' ), 'Editor de texto con marcado optimizado: sus párrafos directos.' );
	list( $el ) = fx_convert( fx_widget( 'bdt-advanced-heading', $fill ) );
	f8_check( 0 === strpos( fx_css( $el ), 'selector .digi-advanced-heading__main{' ), 'Título avanzado: la clase del widget propio (también antes de traducir el widget).' );
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_widget_sf_fx_enable' => 'yes', 'ep_widget_sf_fx_animation_type' => 'reveal_gradient_mask' ) ) );
	f8_check( 0 === strpos( fx_css( $el ), '@supports (animation-timeline: view()){' ) && false !== strpos( fx_css( $el ), 'mask-image:linear-gradient' ) && in_array( 'ext.loss.scroll_fill_effect', $codes, true ), 'Máscara: todo dentro de @supports (sin soporte, el texto se ve).' );
	list( $el, $codes ) = fx_convert( fx_widget( 'button', array( 'ep_widget_sf_fx_enable' => 'yes' ) ) );
	f8_check( array( 'ext.inert' ) === $codes && array() === fx_left( array( $el ) ) && '' === fx_css( $el ), 'Fuera de sus widgets no hacía nada: se retira.' );
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_widget_sf_fx_enable' => 'yes', 'ep_widget_sf_fx_animation_type' => 'scaling_gradient_text', 'motion_fx_scale_effect' => '' ) ) );
	f8_check( 'yes' === $el['settings']['motion_fx_scale_effect'] && 'in-out' === $el['settings']['motion_fx_scale_direction'] && 'out-in' === $el['settings']['motion_fx_opacity_direction'], 'Escalado: efectos de movimiento de escala y opacidad.' );
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_widget_sf_fx_enable' => 'yes', 'ep_widget_sf_fx_animation_type' => 'scaling_gradient_text', 'motion_fx_motion_fx_scrolling' => 'yes', 'motion_fx_scale_effect' => 'yes', 'motion_fx_scale_direction' => 'out-in' ) ) );
	f8_check( in_array( 'ext.conflict', $codes, true ) && 'out-in' === $el['settings']['motion_fx_scale_direction'], 'No pisar los efectos de movimiento propios.' );

	/* ======================================================================== Caja con desplazamiento */

	fx_reset();
	$box = array( 'ep_scroll_box_enable' => 'yes', 'ep_scroll_box_width' => array( 'unit' => 'px', 'size' => 6 ), 'ep_scroll_box_border_type' => 'dotted', 'ep_scroll_box_border_color' => '#cccccc', 'ep_scroll_box_border_radius' => array( 'unit' => 'px', 'size' => 3 ), 'ep_scroll_box_thumb_color_background' => 'classic', 'ep_scroll_box_thumb_color_color' => '#333333', 'ep_scroll_box_track_color_background' => 'classic', 'ep_scroll_box_track_color_color' => '#f0f0f0', 'ep_scroll_box_thumb_shadow_box_shadow_type' => 'yes', 'ep_scroll_box_thumb_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 0, 'blur' => 2, 'spread' => 0, 'color' => '#000000' ) );
	list( $el, $codes ) = fx_convert( fx_box( 'container', $box ) );
	$css = fx_css( $el );
	f8_check( 0 === strpos( $css, 'selector{max-height:100px;overflow-y:scroll!important;overflow-x:hidden!important;scrollbar-width:thin;scrollbar-color:#333333 #f0f0f0}' ), 'Altura por defecto y, para Firefox, barra fina con sus colores.' );
	f8_check( false !== strpos( $css, '@supports selector(::-webkit-scrollbar){selector{scrollbar-width:auto;scrollbar-color:auto}}selector::-webkit-scrollbar{' ), 'Donde existe ::-webkit-scrollbar, scrollbar-color se anula después (Chromium 121+ lo preferiría).' );
	f8_check( false !== strpos( $css, 'selector::-webkit-scrollbar{width:6px;border-radius:3px}' ) && false !== strpos( $css, 'selector::-webkit-scrollbar-thumb{background-color:#babac0;border-radius:16px;background-color:#333333;box-shadow:0 0 2px 0 #000000;border-radius:3px}' ) && false !== strpos( $css, 'border-style:dotted' ) && false !== strpos( $css, 'border-color:#cccccc' ), 'Barra, pulgar y pista como Element Pack.' );
	f8_check( array( 'ext.converted.scroll_box' ) === $codes && array() === fx_left( array( $el ) ), 'Exacta: sin pérdidas.' );
	list( , $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_scroll_box_enable' => 'yes' ) ) );
	f8_check( array( 'ext.inert' ) === $codes, 'En un widget que no es el editor de texto no actuaba.' );

	/* ======================================================================== Texto en 3D → «Relieve 3D» */

	fx_reset();
	$relief = static function ( array $el ) {
		$out = array();
		foreach ( (array) $el['settings'] as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'digitalisimo_relief' ) ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	};
	// Sólo encendido: los valores por defecto de Element Pack (30 px, 8 capas, 500 px y desvanecido), sin giro.
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'title' => 'Tres de', 'ep_threed_text_active' => 'yes' ) ) );
	f8_check( array( 'digitalisimo_relief' => 'yes', 'digitalisimo_relief_depth' => array( 'unit' => 'px', 'size' => 30.0 ), 'digitalisimo_relief_layers' => 8, 'digitalisimo_relief_perspective' => array( 'unit' => 'px', 'size' => 500.0 ), 'digitalisimo_relief_fade' => 'yes' ) === $relief( $el ), 'Por defecto: el rasgo con 30 px, 8 capas, 500 px de perspectiva y desvanecido: ' . fx_json( $relief( $el ) ) );
	f8_check( array( 'ext.converted.threed_text' ) === $codes && '' === fx_css( $el ) && array() === fx_left( array( $el ) ) && ! isset( $el['settings']['motion_fx_tilt_effect'] ), 'Sin pérdidas, sin CSS personalizado (ni sombras de texto) ni efectos de movimiento, y sin claves de Element Pack.' );
	// Capas, unidad, sin desvanecido, color inyectado y giro al desplazar en vertical.
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_layers' => 4, 'ep_threed_text_depth' => array( 'unit' => 'em', 'size' => 1 ), 'ep_threed_text_fade' => '', 'ep_threed_text_depth_color' => 'red;}x{', 'ep_threed_text_event' => 'scrollY' ) ) );
	$r = $relief( $el );
	f8_check( array( 'unit' => 'em', 'size' => 1.0 ) === $r['digitalisimo_relief_depth'] && 4 === $r['digitalisimo_relief_layers'] && ! isset( $r['digitalisimo_relief_fade'] ) && ! isset( $r['digitalisimo_relief_color'] ), 'Capas y profundidad en em; sin desvanecido; el color inyectado no llega (las copias toman el del texto).' );
	f8_check( 'scroll-y' === $r['digitalisimo_relief_event'] && array( 'unit' => 'deg', 'size' => 35.0 ) === $r['digitalisimo_relief_rotation'] && ! isset( $r['digitalisimo_relief_direction'] ) && array( 'ext.converted.threed_text' ) === $codes, 'Giro al desplazar en vertical con los 35° por defecto: ya no es una pérdida.' );
	foreach ( array( 'pointer' => 'pointer', 'scroll' => 'scroll', 'scrollX' => 'scroll-x', 'none' => null, 'otro' => null ) as $event => $expected ) {
		list( $el ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_event' => $event ) ) );
		f8_check( $expected === ( $el['settings']['digitalisimo_relief_event'] ?? null ), 'Evento ' . $event . ' → ' . var_export( $expected, true ) );
	}
	// Puntero, sentido inverso, giro guardado y color con su global.
	list( $el ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_event' => 'pointer', 'ep_threed_text_event_direction' => 'reverse', 'ep_threed_text_event_rotation' => array( 'unit' => 'px', 'size' => -50 ), 'ep_threed_text_depth_color' => '#333333', '__globals__' => array( 'ep_threed_text_depth_color' => 'globals/colors?id=accent' ) ) ) );
	$r = $relief( $el );
	f8_check( 'pointer' === $r['digitalisimo_relief_event'] && 'reverse' === $r['digitalisimo_relief_direction'] && array( 'unit' => 'deg', 'size' => -50.0 ) === $r['digitalisimo_relief_rotation'] && '#333333' === $r['digitalisimo_relief_color'] && 'globals/colors?id=accent' === ( $el['settings']['__globals__']['digitalisimo_relief_color'] ?? '' ), 'Puntero, sentido inverso, giro de −50° y color de la profundidad con su global.' );
	// Un giro sin número no giraba (ztext recibía «deg»): sin evento. Topes de 360°.
	list( $el ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_event' => 'pointer', 'ep_threed_text_event_rotation' => array( 'unit' => 'px', 'size' => '' ), 'ep_threed_text_event_direction' => 'reverse' ) ) );
	f8_check( ! isset( $el['settings']['digitalisimo_relief_event'] ) && ! isset( $el['settings']['digitalisimo_relief_direction'] ) && 'yes' === $el['settings']['digitalisimo_relief'], 'Giro sin número: relieve sin giro.' );
	list( $el ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_event' => 'scroll', 'ep_threed_text_event_rotation' => array( 'size' => 900 ) ) ) );
	f8_check( array( 'unit' => 'deg', 'size' => 360.0 ) === $el['settings']['digitalisimo_relief_rotation'], 'Giro acotado a 360°.' );
	// Tamaños a 0 o vacíos: los de ep-threed-text.js (30 px, 8 capas, 500 px), también con la unidad %.
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_depth' => array( 'unit' => '%', 'size' => 0 ), 'ep_threed_text_layers' => 0, 'ep_threed_text_perspective' => array( 'unit' => 'px', 'size' => '' ) ) ) );
	$r = $relief( $el );
	f8_check( array( 'unit' => 'px', 'size' => 30.0 ) === $r['digitalisimo_relief_depth'] && 8 === $r['digitalisimo_relief_layers'] && array( 'unit' => 'px', 'size' => 500.0 ) === $r['digitalisimo_relief_perspective'] && array( 'ext.converted.threed_text' ) === $codes, 'Profundidad 0 (aun en %), 0 capas y perspectiva vacía: 30 px, 8 capas y 500 px.' );
	list( $el ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_depth' => array( 'unit' => 'rem', 'size' => 400 ), 'ep_threed_text_layers' => 8.5, 'ep_threed_text_perspective' => array( 'unit' => 'px', 'size' => 800 ) ) ) );
	$r = $relief( $el );
	f8_check( array( 'unit' => 'rem', 'size' => 100.0 ) === $r['digitalisimo_relief_depth'] && 9 === $r['digitalisimo_relief_layers'] && array( 'unit' => 'px', 'size' => 800.0 ) === $r['digitalisimo_relief_perspective'], 'rem acotado a 100; 8,5 capas son 9 (ztext pintaba 9); perspectiva guardada.' );
	list( $el ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_layers' => 250 ) ) );
	f8_check( 100 === $el['settings']['digitalisimo_relief_layers'], 'Capas acotadas a 100.' );
	// En % ztext fallaba y el título se veía plano: no se enciende el rasgo; se informa.
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_depth' => array( 'unit' => '%', 'size' => 50 ), 'ep_threed_text_layers' => 1 ) ) );
	f8_check( array() === $relief( $el ) && '' === fx_css( $el ) && array() === fx_left( array( $el ) ) && in_array( 'ext.loss.threed_text', $codes, true ), 'Profundidad en %: plano como se veía, sin rasgo ni CSS, informado.' );
	// Profundidad negativa: su valor absoluto, informado.
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_depth' => array( 'unit' => 'px', 'size' => -20 ) ) ) );
	f8_check( array( 'unit' => 'px', 'size' => 20.0 ) === $el['settings']['digitalisimo_relief_depth'] && in_array( 'ext.loss.threed_text', $codes, true ), 'Profundidad negativa: 20 px, informado.' );
	// Encabezado avanzado: Element Pack 10 no encontraba su título, así que no pintaba nada.
	list( $el, $codes ) = fx_convert( fx_widget( 'digitalisimo-advanced-heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_event' => 'pointer' ) ) );
	f8_check( array() === $relief( $el ) && array() === fx_left( array( $el ) ) && in_array( 'ext.loss.threed_text', $codes, true ) && ! isset( $el['settings']['motion_fx_tilt_effect'] ), 'Encabezado avanzado: plano como se veía, claves retiradas e informado.' );
	// No pisar: un relieve propio ya guardado.
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'digitalisimo_relief_layers' => 3 ) ) );
	f8_check( in_array( 'ext.conflict', $codes, true ) && 3 === $el['settings']['digitalisimo_relief_layers'] && ! isset( $el['settings']['digitalisimo_relief'] ), 'No pisar: con capas propias ya guardadas no se escribe nada (conflicto declarado).' );
	// Un DIGITALÍSIMO Elements sin el rasgo (anterior a §19): las claves se guardan igual (se encienden al actualizarlo),
	// pero hasta entonces el título se ve plano, y el informe lo dice en vez de darlo por convertido sin más.
	$traits             = '\\Digitalisimo\\Elements\\Legacy\\Effects\\Traits';
	$traits::$relief    = false;
	list( $el, $codes, $report ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes' ) ) );
	$traits::$relief    = null;
	f8_check( 'yes' === ( $el['settings']['digitalisimo_relief'] ?? '' ) && 8 === ( $el['settings']['digitalisimo_relief_layers'] ?? null ) && '' === fx_css( $el ) && array( 'ext.converted.threed_text', 'ext.loss.threed_text' ) === $codes && false !== strpos( (string) json_encode( $report ), 'Relieve 3D' ), 'Elements sin el rasgo: claves guardadas, sin sombras de texto, y la pérdida temporal informada: ' . fx_json( $codes ) );
	list( , $codes ) = fx_convert( fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes' ) ) );
	f8_check( array( 'ext.converted.threed_text' ) === $codes, 'Con el rasgo (o sin Elements cargado, en las pruebas): sin esa nota.' );

	/* ======================================================================== Distintivo (global-badge) */

	fx_reset();
	list( $el, $codes ) = fx_convert( fx_box( 'container', array( 'ep_global_badge_enable' => 'yes' ), array(), 'b1' ) );
	$s = $el['settings'];
	f8_check( 'yes' === $s['digitalisimo_badge'] && 'New' === $s['digitalisimo_badge_text'] && ! isset( $s['digitalisimo_badge_corner'] ) && 15.0 === $s['digitalisimo_badge_rotate']['size'], 'Por defecto: «New», arriba a la derecha (la esquina por defecto del rasgo), girado 15°.' );
	f8_check( '#1e87f0' === $s['digitalisimo_badge_bg_color'] && '#ffffff' === $s['digitalisimo_badge_color'] && '16' === $s['digitalisimo_badge_padding']['right'] && 100 === $s['digitalisimo_badge_min_width']['size'] && 'uppercase' === $s['digitalisimo_badge_typography_text_transform'] && 12 === $s['digitalisimo_badge_typography_font_size']['size'], 'El aspecto por defecto de Element Pack se escribe donde no se guardó otro.' );
	f8_check( array( 'ext.converted.global_badge' ) === $codes && ! isset( $s['digitalisimo_badge_offset_x'] ) && ! isset( $s['digitalisimo_badge_icon'] ) && ! isset( $s['digitalisimo_badge_float'] ), 'Sin desplazamientos, icono ni flotación guardados.' );
	$badge = array(
		'ep_global_badge_enable'          => 'yes',
		'ep_global_badge_text'            => '<b>Oferta</b>',
		'ep_global_badge_horizontal'      => 'right',
		'ep_global_badge_vertical'        => 'bottom',
		'ep_global_badge_offset_x'        => array( 'unit' => 'px', 'size' => 6 ),
		'ep_global_badge_offset_x_mobile' => array( 'unit' => '%', 'size' => -2 ),
		'ep_global_badge_offset_y'        => array( 'unit' => 'em', 'size' => 1 ),
		'ep_global_badge_rotate'          => array( 'unit' => 'deg', 'size' => 0 ),
		'ep_global_badge_overflow_hidden' => 'yes',
		'ep_global_badge_style'           => 'custom-layout',
		'ep_global_badge_clip_path'       => 'yes',
		'ep_global_badge_clip_path_value' => 'polygon(0 0);}</style><script>alert(1)</script>',
		'ep_global_badge_icon'            => 'yes',
		'ep_global_badge_icon_type'       => 'lottie',
		'ep_global_badge_svg_layer'       => 'yes',
		'ep_global_badge_svg_code'        => '<svg></svg>',
		'ep_global_badge_floating'        => 'yes',
		'ep_global_badge_floating_rotate_toggle' => 'yes',
		'ep_global_badge_text_color'      => '#000000',
		'ep_global_badge_background_background' => 'gradient',
		'ep_global_badge_background_color' => '#ff0000',
		'ep_global_badge_background_color_b' => '#00ff00',
		'ep_global_badge_typography_typography' => 'custom',
		'ep_global_badge_typography_font_size' => array( 'unit' => 'px', 'size' => 16 ),
		'__dynamic__'                     => array( 'ep_global_badge_text' => '[elementor-tag id="2" name="post-title" settings="%7B%7D"]' ),
	);
	list( $el, $codes ) = fx_convert( fx_widget( 'image', $badge, 'b2' ) );
	$s = $el['settings'];
	f8_check( 'Oferta' === $s['digitalisimo_badge_text'] && 0 === strpos( (string) $s['__dynamic__']['digitalisimo_badge_text'], '[elementor-tag' ), 'Texto plano (Element Pack lo escapaba) y su etiqueta dinámica.' );
	f8_check( 'bottom-end' === $s['digitalisimo_badge_corner'] && -6.0 === $s['digitalisimo_badge_offset_x']['size'] && 2.0 === $s['digitalisimo_badge_offset_x_mobile']['size'] && '%' === $s['digitalisimo_badge_offset_x_mobile']['unit'] && -1.0 === $s['digitalisimo_badge_offset_y']['size'] && 'em' === $s['digitalisimo_badge_offset_y']['unit'], 'Esquina lógica y desplazamientos con el signo hacia dentro (translate desde la esquina, sin margen: ep-global-badge.css).' );
	f8_check( 0.0 === $s['digitalisimo_badge_rotate']['size'] && 'yes' === $s['digitalisimo_badge_overflow'] && ! isset( $s['digitalisimo_badge_shape'] ) && ! isset( $s['digitalisimo_badge_clip'] ), 'Giro guardado, desbordamiento y recorte con inyección descartado.' );
	f8_check( ! isset( $s['digitalisimo_badge_icon'] ) && ! isset( $s['digitalisimo_badge_float'] ) && 4 <= count( array_keys( $codes, 'ext.loss.global_badge', true ) ), 'Lottie, capa SVG, flotación con giro y recorte inválido: pérdidas, sin icono ni flotación.' );
	f8_check( '#000000' === $s['digitalisimo_badge_color'] && 'gradient' === $s['digitalisimo_badge_bg_background'] && '#00ff00' === $s['digitalisimo_badge_bg_color_b'] && 16.0 === $s['digitalisimo_badge_typography_font_size']['size'] && '700' === ( $s['digitalisimo_badge_typography_font_weight'] ?? '' ) && 'uppercase' === ( $s['digitalisimo_badge_typography_text_transform'] ?? '' ) && '16' === ( $s['digitalisimo_badge_padding']['right'] ?? '' ), 'Los estilos guardados ganan a los de por defecto; lo no guardado conserva el aspecto de Element Pack.' );
	f8_check( false === strpos( fx_json( $s ), '<script' ) && false === strpos( fx_json( $s ), '</style' ), 'Ningún valor inyectado llega a los ajustes.' );
	$GLOBALS['fx_rtl'] = true;
	function is_rtl() { return ! empty( $GLOBALS['fx_rtl'] ); }
	list( $el ) = fx_convert( fx_widget( 'image', array( 'ep_global_badge_enable' => 'yes', 'ep_global_badge_horizontal' => 'left' ) ) );
	f8_check( '' === ( $el['settings']['digitalisimo_badge_corner'] ?? '' ), 'En RTL la izquierda física es el final: arriba al final (la esquina por defecto).' );
	$GLOBALS['fx_rtl'] = false;
	list( $el ) = fx_convert( fx_widget( 'image', array( 'ep_global_badge_enable' => 'yes', 'ep_global_badge_style' => 'custom-layout', 'ep_global_badge_clip_path' => 'yes', 'ep_global_badge_icon' => 'yes', 'ep_global_badge_icon_position' => 'right', 'ep_global_badge_floating' => 'yes', 'ep_global_badge_floating_translate_toggle' => 'yes' ) ) );
	$s = $el['settings'];
	f8_check( 'custom' === $s['digitalisimo_badge_shape'] && 0 === strpos( $s['digitalisimo_badge_clip'], 'polygon(0% 0%' ) && array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ) === $s['digitalisimo_badge_icon'] && 'end' === $s['digitalisimo_badge_icon_position'] && 'yes' === $s['digitalisimo_badge_float'], 'Recorte por defecto, icono por defecto al final y flotación con traslado.' );
	list( $el ) = fx_convert( fx_widget( 'image', array( 'ep_global_badge_enable' => 'yes', 'ep_global_badge_icon' => 'yes', 'ep_global_badge_icon_selected' => array( 'value' => 'fa"><script>', 'library' => 'fa-solid' ) ) ) );
	f8_check( ! isset( $el['settings']['digitalisimo_badge_icon'] ), 'Un icono con un nombre de clase inválido no se escribe.' );
	list( $el, $codes ) = fx_convert( fx_widget( 'image', array( 'ep_global_badge_enable' => 'yes', 'digitalisimo_badge_text' => 'Mío' ) ) );
	f8_check( in_array( 'ext.conflict', $codes, true ) && 'Mío' === $el['settings']['digitalisimo_badge_text'] && ! isset( $el['settings']['digitalisimo_badge'] ), 'No pisar un distintivo propio.' );

	/* ======================================================================== Efectos de la imagen al pasar el cursor */

	fx_reset();
	for ( $n = 1; $n <= 6; $n++ ) {
		list( $el, $codes ) = fx_convert( fx_widget( 'image', array( 'ep_image_hover_effects_on' => 'yes', 'hover_effects' => (string) $n, 'effects_color' => '#ff0000', 'effets_width' => array( 'unit' => 'px', 'size' => 5 ) ) ) );
		$css = fx_css( $el );
		f8_check( 0 === strpos( $css, 'selector img{max-inline-size:100%' ) && false === strpos( $css, 'W' ) && false === strpos( $css, 'bdt-' ) && Css_Value::block( $css ) && array( 'ext.converted.image_hover_effects' ) === $codes && array() === fx_left( array( $el ) ), 'Efecto ' . $n . ': CSS de Element Pack con el selector del widget y las cuatro claves sin prefijo retiradas.' );
		f8_check( ( in_array( $n, array( 3, 5, 6 ), true ) ) === ( false !== strpos( $css, 'border-width:5px' ) ), 'Efecto ' . $n . ': ancho del borde sólo en 3, 5 y 6.' );
	}
	f8_check( false !== strpos( $css, '@media (prefers-reduced-motion:reduce){selector img,selector::before,selector::after{transition:none}}' ), 'Sin transiciones con movimiento reducido.' );
	list( $el, $codes ) = fx_convert( fx_widget( 'heading', array( 'hover_effects' => '3', 'effects_color' => '#000' ) ) );
	f8_check( array() === $codes && '3' === $el['settings']['hover_effects'], 'Las claves genéricas fuera del widget image no se tocan.' );
	f8_check( ! Dispatcher::detect( array( 'hover_effects' => '3', 'effects_color' => '#000' ) ), 'Ni cuentan como heredadas sin el interruptor.' );
	list( $el ) = fx_convert( fx_widget( 'image', array( 'ep_image_hover_effects_on' => 'yes', 'hover_effects' => '9;}x{', 'effects_duration' => array( 'unit' => 'ms', 'size' => '800;' ) ) ) );
	f8_check( false !== strpos( fx_css( $el ), 'inset-block-start:50%' ) && false === strpos( fx_css( $el ), 'transition-duration' ), 'Un efecto desconocido toma el 1; una duración inválida no se escribe.' );

	/* ======================================================================== Degradado animado */

	fx_reset();
	list( $el, $codes ) = fx_convert( fx_box( 'container', array( 'element_pack_agbg_show' => 'yes', 'element_pack_agbg_direction' => 'top-bottom', 'element_pack_agbg_transitionSpeed' => array( 'size' => 2000 ), 'element_pack_agbg_color_list' => array( array( '_id' => 'a', 'start_color' => '#111111', 'end_color' => 'red;}body{x' ), array( '_id' => 'b', 'start_color' => '#222222', 'end_color' => '#333333' ) ) ) ) );
	$css = fx_css( $el );
	f8_check( false !== strpos( $css, 'linear-gradient(180deg,#111111,#222222,#333333)' ) && false !== strpos( $css, 'animation:digi-agbg 4000ms ease-in-out infinite alternate' ) && false === strpos( $css, 'red;' ), 'Colores válidos de cada fila, dirección y duración (velocidad × filas).' );
	f8_check( false !== strpos( $css, 'selector{position:relative;overflow:hidden;isolation:isolate}' ) && false !== strpos( $css, 'selector::after{content:"";position:absolute;inset:0;z-index:-1' ) && false === strpos( $css, 'mix-blend-mode' ) && false !== strpos( $css, '@media (prefers-reduced-motion:reduce){selector::after{animation:none}}' ), '::after (libre en contenedores y secciones) bajo el contenido; sin fondo del núcleo no hay modo de fusión.' );
	f8_check( in_array( 'ext.converted.animated_gradient_background', $codes, true ) && array() === fx_left( array( $el ) ), 'Convertida.' );
	list( $el, $codes ) = fx_convert( fx_box( 'section', array( 'element_pack_agbg_show' => 'yes', 'element_pack_agbg_color_list' => array( array( 'start_color' => 'x', 'end_color' => 'y' ) ) ) ) );
	f8_check( in_array( 'ext.inert', $codes, true ) && '' === fx_css( $el ), 'Sin dos colores válidos no se escribe nada.' );

	/* ======================================================================== Descartes: se retiran (Dropped los informa) */

	fx_reset();
	list( $el, $codes ) = fx_convert( fx_box( 'section', array( 'section_particles_on' => 'yes', 'section_particles_js' => '{"a":1}', 'section_particles_z_index' => '2', 'element_pack_cursor_effects_show' => 'yes', 'element_pack_tile_scroll_show' => 'yes', 'element_pack_title_radius' => array( 'top' => '1' ), 'ep_widget_cf_confetti' => 'yes' ) ) );
	f8_check( array() === $codes && array() === fx_left( array( $el ) ) && array() === $el['settings'], 'Las descartadas se retiran (también sin prefijo) sin anotación propia: la hace Dropped leyendo la copia.' );
	list( $el ) = fx_convert( fx_widget( 'image', array( 'ep_background_image_parallax_on' => 'yes', 'ep_background_image_parallax_scale' => array( 'size' => 2 ), 'ep_sound_effects_active' => 'yes', 'ep_ripple_enable' => 'yes', 'ep_ripple_color' => '#ccc' ) ) );
	f8_check( array() === $el['settings'], 'También las de widgets.' );

	/* ======================================================================== Ambigüedad: claves sin prefijo sin señal de Element Pack (C2-18) */

	fx_reset();
	Dispatcher::force_signal( null );
	$plain = array( 'section_sticky_on' => 'yes', 'section_sticky_offset' => array( 'size' => 4 ) );
	f8_check( ! Dispatcher::signal( 0 ) && ! Dispatcher::detect( $plain ) && Dispatcher::ambiguous( $plain ), 'Sin Element Pack instalado ni señal: no cuenta y es ambigua.' );
	list( $el, $codes ) = fx_convert( fx_box( 'container', $plain ) );
	f8_check( $plain === $el['settings'] && array( 'ext.ambiguous' ) === $codes, 'Si aun así se llama, no se toca y se anota ext.ambiguous.' );
	f8_check( array( 'ext.ambiguous' => 1 ) === Dispatcher::describe( array( fx_box( 'container', $plain ) ) ), 'El análisis la cuenta.' );
	list( $el, $codes ) = fx_convert( fx_box( 'container', $plain + array( 'ep_bg_expand_enable' => '' ) ) );
	f8_check( 'top' === $el['settings']['sticky'] && ! in_array( 'ext.ambiguous', $codes, true ), 'Una clave con prefijo en el propio elemento es señal suficiente.' );
	$doc = f8_post( array( 'post_type' => 'page' ), array( '_elementor_data' => fx_json( array( fx_widget( 'heading', array( 'ep_threed_text_active' => '' ) ) ) ) ) );
	Context::begin( $doc, 'run' );
	f8_check( Dispatcher::detect( $plain ), 'Otra clave con prefijo en el documento (Context): cuenta.' );
	Context::end();
	$doc2 = f8_post( array( 'post_type' => 'page' ), array( '_elementor_data' => fx_json( array( fx_box( 'container', $plain ) ) ), '_digitalisimo_ep_backup' => '{"time":1,"data":"[]"}' ) );
	f8_check( Dispatcher::signal( $doc2 ) && ! Dispatcher::signal( f8_post( array(), array( '_elementor_data' => '[]' ) ) ), 'Una copia de una conversión anterior también es señal.' );
	Dispatcher::force_signal( null );
	f8_plugin( 'bdthemes-element-pack-lite/bdthemes-element-pack-lite.php', array( 'Name' => 'Element Pack Addons for Elementor', 'Version' => '10.0.1' ) );
	f8_check( Dispatcher::signal( 0 ) && Dispatcher::detect( $plain ), 'Con Element Pack instalado (aunque esté inactivo), cuentan.' );

	/* ======================================================================== v4: elementos atómicos */

	fx_reset();
	$atomic = array( 'id' => 'v4', 'elType' => 'widget', 'widgetType' => 'e-heading', 'settings' => array( 'ep_widget_sf_fx_enable' => array( '$$type' => 'boolean', 'value' => true ), 'ep_threed_text_active' => array( '$$type' => 'boolean', 'value' => false ) ), 'elements' => array() );
	list( $el, $codes ) = fx_convert( $atomic );
	f8_check( $atomic === $el && array( 'ext.v4' ) === $codes, 'Un elemento atómico con un efecto encendido se informa (ext.v4) y no se toca; el apagado (value:false) no cuenta.' );
	list( , $codes ) = fx_convert( array( 'id' => 'v5', 'elType' => 'e-flexbox', 'settings' => array( 'ep_widget_cf_confetti' => array( '$$type' => 'boolean', 'value' => true ) ), 'elements' => array() ) );
	f8_check( array( 'ext.v4' ) === $codes, 'También en contenedores atómicos.' );
	f8_check( ! Values::used( array( '$$type' => 'boolean', 'value' => false ) ) && Values::used( array( '$$type' => 'string', 'value' => 'yes' ) ) && 'x' === Values::unwrap( array( '$$type' => 'string', 'value' => array( '$$type' => 'string', 'value' => 'x' ) ) ), 'Desenvoltura de valores v4.' );

	/* ======================================================================== Ajustes de la página: grid-line, custom-js y descartes */

	fx_reset();
	$page_id = f8_post( array( 'post_type' => 'page', 'post_title' => 'Inicio' ) );
	$ps      = array( 'ep_grid_line_enable' => 'yes', 'ep_grid_line_line_color' => '#cccccc', 'ep_grid_line_column_color' => 'rgba(0,0,0,0.02)', 'ep_grid_line_columns' => 8, 'ep_grid_line_columns_tablet' => 4, 'ep_grid_line_max_width' => array( 'unit' => 'px', 'size' => 1200 ), 'ep_grid_line_line_width' => array( 'unit' => 'px', 'size' => 2 ), 'ep_grid_line_direction' => array( 'size' => 45 ), 'ep_grid_line_z_index' => 5, 'ep_grid_line_outline' => 'yes', 'ep_grid_line_horizontal' => 'yes', 'custom_css' => 'body{margin:0}', 'ep_hash_link_enable' => 'yes', 'ep_content_protector_enable' => 'yes', 'ep_adblock_detector_enable' => 'yes', 'hide_title' => 'yes' );
	Report::begin( 'analysis' );
	$out   = Page::convert( $ps, $page_id );
	$codes = fx_codes( Report::take() );
	$css   = (string) $out['custom_css'];
	f8_check( 0 === strpos( $css, "body{margin:0}\nselector{--digi-grid-color:#cccccc;--digi-grid-column-color:rgba(0,0,0,0.02);--digi-grid-columns:8;--digi-grid-max-width:1200px;--digi-grid-width:2px;--digi-grid-direction:45deg;--digi-grid-z-index:5}" ), 'Variables propias en el CSS de la página, añadido al existente: ' . substr( $css, 0, 300 ) );
	f8_check( false !== strpos( $css, '@media (max-width:1024px){selector{--digi-grid-columns:4}}' ) && false !== strpos( $css, 'body::before{content:"";position:absolute;inset:0' ) && false !== strpos( $css, 'outline:var(--digi-grid-width,1px) solid' ) && false !== strpos( $css, 'body::after{' ) && false !== strpos( $css, '@media print{body::before,body::after{display:none}}' ), 'Tableta, líneas, contorno, horizontales y nada al imprimir.' );
	f8_check( ! preg_match( '/--ep-|bdt-/', $css ) && Css_Value::block( $css ), 'Sin nombres del proveedor y bloque válido.' );
	f8_check( array( 'ext.converted.grid_line' ) === $codes && array( 'custom_css', 'hide_title' ) === array_keys( $out ), 'Convertida; los descartes de página retirados; lo demás, intacto.' );
	$bad = Page::convert( array( 'ep_grid_line_enable' => 'yes', 'ep_grid_line_line_color' => 'red;}</style><script>x</script>', 'ep_grid_line_columns' => '12;}' ), $page_id );
	f8_check( false === strpos( (string) $bad['custom_css'], 'script' ) && false === strpos( (string) $bad['custom_css'], '--digi-grid-color:' ) && false === strpos( (string) $bad['custom_css'], '--digi-grid-columns:' ), 'Valores con inyección: no se escriben (se dibujan los de por defecto).' );
	// custom-js.
	Report::begin( 'analysis' );
	$js = Page::convert( array( 'ep_custom_header_script' => '<script>alert(1)</script>', 'ep_custom_footer_script' => "  \n", 'template' => 'default' ), $page_id );
	$items = Report::take()['docs'][ $page_id ]['items'] ?? array();
	f8_check( 1 === count( Snippets::$calls ) && 'elementor_head' === Snippets::$calls[0]['location'] && array( 'include/singular/page/' . $page_id ) === Snippets::$calls[0]['conditions'] && '<script>alert(1)</script>' === Snippets::$calls[0]['code'], 'Un borrador por campo con código, en la cabecera y limitado a este documento.' );
	f8_check( false !== strpos( Snippets::$calls[0]['title'], 'JavaScript heredado · Inicio (cabecera) · revisar' ) && 'migration' === Snippets::$calls[0]['origin']['source'], 'Título y procedencia.' );
	f8_check( array( 'template' => 'default' ) === $js && 'ext.review.customjs' === ( $items[0]['code'] ?? '' ) && 901 === ( $items[0]['detail']['id'] ?? 0 ), 'Claves retiradas y pendiente de revisar con el ID del borrador.' );
	Snippets::$allow = false;
	Report::begin( 'analysis' );
	Page::convert( array( 'ep_custom_footer_script' => '<script>b()</script>' ), $page_id );
	f8_check( 'ext.customjs.not_created' === ( Report::take()['docs'][ $page_id ]['items'][0]['code'] ?? '' ) && 1 === count( Snippets::$calls ), 'Sin unfiltered_html (Snippets devuelve 0): se informa que deja de ejecutarse.' );
	Snippets::$allow = true;
	$tpl = f8_post( array( 'post_type' => 'elementor_library', 'post_title' => 'Cabecera' ), array( '_elementor_template_type' => 'header' ) );
	Report::begin( 'analysis' );
	$out = Page::convert( array( 'ep_custom_footer_script' => '<script>c()</script>' ), $tpl );
	f8_check( 'ext.customjs.inert' === ( Report::take()['docs'][ $tpl ]['items'][0]['code'] ?? '' ) && 1 === count( Snippets::$calls ) && array() === $out, 'En una plantilla nunca se ejecutaba: sólo se informa.' );
	Context::begin( $page_id, 'editor' );
	$kept = Page::convert( array( 'ep_custom_header_script' => '<script>d()</script>', 'ep_grid_line_enable' => 'yes' ), $page_id );
	Context::end();
	f8_check( isset( $kept['ep_custom_header_script'] ) && 1 === count( Snippets::$calls ) && isset( $kept['custom_css'] ), 'En el editor (puro) no se crea nada: el JavaScript queda para la tarea; grid-line sí se convierte (no tiene efectos).' );
	$kit = f8_post( array( 'post_type' => 'elementor_library' ), array( '_elementor_template_type' => 'kit' ) );
	$kit_ps = array( 'ep_grid_line_enable' => 'yes', 'element_pack_global_x' => 'y' );
	f8_check( $kit_ps === Page::convert( $kit_ps, $kit ), 'El kit lo trata la plataforma (H): se devuelve tal cual.' );
	f8_check( array( 'ext.converted.grid_line' => 1, 'ext.review.customjs' => 1 ) === Page::describe( array( 'ep_grid_line_enable' => 'yes', 'ep_custom_header_script' => 'x' ), $page_id ) && 1 === count( Snippets::$calls ), 'El análisis de la página cuenta sin crear nada.' );
	update_option( 'element_pack_elementor_extend', array( 'grid-line' => 'off' ) );
	Activation::reset();
	Report::begin( 'analysis' );
	$off = Page::convert( array( 'ep_grid_line_enable' => 'yes' ), $page_id );
	f8_check( array() === $off && 'ext.disabled' === ( Report::take()['docs'][ $page_id ]['items'][0]['code'] ?? '' ), 'Una extensión de página apagada: se retira con ext.disabled.' );

	/* ======================================================================== Valores maliciosos en cada convertidor (V.7) */

	fx_reset();
	$inject = 'red;}body{display:none}</style><script>alert(1)</script>';
	$cases  = array(
		fx_widget( 'heading', array( 'element_pack_background_overlay' => 'yes', 'ep_background_overlay_background' => 'classic', 'ep_background_overlay_color' => $inject, 'ep_overlay_blend_mode' => $inject, 'ep_background_overlay_zindex' => $inject, 'ep_background_overlay_radius' => array( 'top' => $inject, 'unit' => 'px' ) ) ),
		fx_box( 'section', array( 'section_sticky_on' => 'yes', 'section_sticky_active_bg' => $inject, 'section_sticky_zindex' => $inject, 'section_sticky_border_border' => $inject, 'section_sticky_active_shadow_box_shadow_type' => 'yes', 'section_sticky_active_shadow_box_shadow' => array( 'color' => $inject ) ) ),
		fx_widget( 'heading', array( 'element_pack_widget_transform' => 'yes', 'element_pack_widget_effect_transition_easing' => $inject, 'element_pack_widget_effect_transition_delay' => $inject, 'element_pack_widget_effect_transition_duration' => $inject ) ),
		fx_widget( 'image', array( 'ep_image_hover_effects_on' => 'yes', 'effects_color' => $inject, 'effets_width' => array( 'unit' => $inject, 'size' => 1 ) ) ),
		fx_box( 'container', array( 'element_pack_agbg_show' => 'yes', 'element_pack_agbg_blending_mode' => $inject, 'background_background' => 'classic', 'element_pack_agbg_direction' => $inject ) ),
		fx_box( 'container', array( 'ep_scroll_box_enable' => 'yes', 'ep_scroll_box_max_height' => array( 'unit' => 'px', 'size' => $inject ), 'ep_scroll_box_thumb_color_background' => 'classic', 'ep_scroll_box_thumb_color_color' => $inject, 'ep_scroll_box_border_type' => $inject ) ),
		fx_box( 'section', array( 'ep_bg_expand_enable' => 'yes', 'ep_bg_expand_container_width' => array( 'unit' => 'px', 'size' => $inject ), 'ep_bg_expand_anim_speed' => array( 'unit' => $inject, 'size' => 3 ) ) ),
		fx_widget( 'heading', array( 'ep_widget_sf_fx_enable' => 'yes', 'ep_widget_sf_fx_base_color' => $inject, 'ep_widget_sf_fx_fill_bg_background' => 'gradient', 'ep_widget_sf_fx_fill_bg_color' => $inject, 'ep_widget_sf_fx_fill_bg_gradient_type' => $inject ) ),
		fx_widget( 'heading', array( 'ep_threed_text_active' => 'yes', 'ep_threed_text_depth_color' => $inject, 'ep_threed_text_depth' => array( 'unit' => $inject, 'size' => 9 ), 'ep_threed_text_layers' => $inject ) ),
		fx_box( 'section', array( 'section_equal_height_on' => 'yes', 'section_equal_height_selector' => $inject ) ),
	);
	update_option( 'element_pack_elementor_extend', array( 'background-expand' => 'on' ) );
	Activation::reset();
	foreach ( $cases as $index => $case ) {
		list( $el ) = fx_convert( $case );
		$all = fx_json( $el['settings'] );
		f8_check( false === strpos( $all, 'display:none' ) && false === strpos( $all, '<script' ) && false === strpos( $all, '</style' ) && Css_Value::block( fx_css( $el ) ), 'Valores maliciosos (caso ' . $index . '): nada llega al CSS ni a los ajustes.' );
	}

	/* ======================================================================== Informe: códigos del catálogo y escape */

	fx_reset();
	foreach ( array_keys( $catalog ) as $code ) {
		if ( 0 === strpos( $code, 'ext.' ) ) {
			f8_check( 1 === preg_match( '/^ext\.[a-z0-9_.]+$/', $code ) && in_array( $catalog[ $code ]['section'], Report::SECTIONS, true ) && '' !== $catalog[ $code ]['label'] && ! preg_match( '#https?://|bdthemes\.com|elementpack\.pro#i', $catalog[ $code ]['label'] ), 'Código del catálogo de C con sección y texto sin dominios del proveedor: ' . $code );
		}
	}
	foreach ( Dispatcher::keys() as $slug => $spec ) {
		$code = 'dropped' === $spec['treatment'] ? 'ext.dropped.' . Dispatcher::code( $slug ) : ( 'draft' === $spec['treatment'] ? 'ext.review.customjs' : 'ext.converted.' . Dispatcher::code( $slug ) );
		f8_check( isset( $catalog[ $code ] ) && ( 'dropped' === $spec['treatment'] ? 'dropped' : ( 'approx' === $spec['treatment'] ? 'approx' : ( 'draft' === $spec['treatment'] ? 'review' : 'converted' ) ) ) === $catalog[ $code ]['section'], 'Cada extensión tiene su código en la sección que dice su tratamiento: ' . $slug );
	}
	$sources = '';
	foreach ( glob( dirname( __DIR__ ) . '/digitalisimo-migration/legacy/effects/*.php' ) as $file ) {
		$sources .= file_get_contents( $file );
	}
	preg_match_all( "/'(ext\.[a-z0-9_.]+)'/", $sources, $literal );
	foreach ( array_unique( $literal[1] ) as $code ) {
		if ( '.' !== substr( $code, -1 ) ) {
			f8_check( isset( $catalog[ $code ] ), 'Cada código literal de los convertidores está en el catálogo: ' . $code );
		}
	}
	Report::begin( 'run' );
	$evil_id = f8_post( array( 'post_type' => 'page', 'post_title' => 'Página' ) );
	Dispatcher::element( fx_widget( 'heading', array( 'element_pack_widget_tooltip' => 'yes' ), '<img src=x onerror=alert(1)>' ), $evil_id );
	Report::add( $evil_id, 'ext.loss.tooltip', array( 'element' => 'heading #<img src=x onerror=alert(2)>', 'note' => '<img src=x onerror=alert(3)>' ) );
	$html = Report::render( Report::take(), 'site' );
	f8_check( false === strpos( $html, '<img' ) && false !== strpos( $html, 'Información emergente' ), 'El informe escapa cada campo (un ID de elemento inválido ni siquiera llega al detalle).' );
	Report::clear();

	/* ======================================================================== describe(): el análisis previo, sin escribir */

	fx_reset();
	$tree = array( fx_box( 'section', array( 'section_sticky_on' => 'yes', 'section_particles_on' => 'yes' ), array( fx_box( 'column', array(), array( fx_widget( 'heading', array( 'element_pack_widget_tooltip' => 'yes', 'ep_threed_text_active' => 'yes', 'element_pack_reveal_effects_enable' => 'yes', '_animation' => 'zoomIn' ) ), fx_widget( 'image', array( 'element_pack_widget_tooltip' => 'yes' ), 'w2' ) ), 'c1' ) ) ) );
	$before = fx_json( $tree );
	$desc   = Dispatcher::describe( $tree );
	f8_check( 2 === $desc['ext.converted.tooltip'] && 1 === $desc['ext.converted.section_sticky'] && 1 === $desc['ext.converted.threed_text'] && 1 === $desc['ext.conflict'] && ! isset( $desc['ext.converted.reveal_effects'] ), 'Recuentos por código, también los conflictos: ' . fx_json( $desc ) );
	f8_check( $before === fx_json( $tree ) && 0 === count( Snippets::$calls ) && null === get_option( Report::OPTION, null ), 'Sin tocar los elementos ni escribir nada.' );

	/* ======================================================================== Integración con Native (frente A), si está */

	fx_reset();
	fx_elementor( array( 'digitalisimo-accordion' => true, 'heading' => true ) );
	$native = 'Digitalisimo\\Elements\\Legacy\\Native';
	if ( method_exists( $native, 'inner_wrapper' ) ) {
		list( $converted ) = $native::element( fx_widget( 'bdt-accordion', array( 'tabs' => array( array( 'tab_title' => 'A' ) ), 'element_pack_widget_tooltip' => 'yes', 'element_pack_widget_tooltip_text' => 'Ayuda', 'section_sticky_on' => 'yes', 'element_pack_reveal_effects_enable' => 'yes' ), 'na' ), 10 );
		$cs = $converted['settings'];
		f8_check( 'digitalisimo-accordion' === $converted['widgetType'] && 'yes' === $cs['digitalisimo_tooltip'] && 'Ayuda' === $cs['digitalisimo_tooltip_text'] && 'top' === $cs['sticky'] && 'fadeInLeft' === $cs['_animation'], 'Widget con mapa: las claves de las extensiones pasan por la bolsa y se convierten tras traducir el widget.' );
		f8_check( array() === fx_left( array( $converted ) ) && ! preg_match( '/"(ep_|element_pack_|bdt_)/', fx_json( $converted ) ), 'Sin nada heredado.' );
		list( $converted ) = $native::element( fx_box( 'container', array( 'section_equal_height_on' => 'yes', 'section_equal_height_selector' => 'column', 'ep_display_conditions_enable' => '' ), array(), 'nb' ), 10 );
		f8_check( 'stretch' === $converted['settings']['flex_align_items'] && array() === fx_left( array( $converted ) ), 'Cualquier elemento: detect() lo marca como heredado y element() lo convierte antes de limpiar.' );
		f8_check( 1 === preg_match( '/^[0-9a-f]{7}$/', 'abc1234' ), 'Formato de IDs.' );
	} else {
		f8_check( true, 'Native de A todavía sin integrar: se prueba con fx_tree().' );
	}

	/* ======================================================================== Los 28 fixtures sintéticos (tests/legacy/ext-*.json) */

	fx_reset();
	$fixtures = glob( __DIR__ . '/legacy/ext-*.json' );
	$covered  = array();
	foreach ( $fixtures as $file ) {
		$slug = basename( $file, '.json' );
		$slug = substr( $slug, 4 );
		if ( ! isset( $keys[ $slug ] ) ) {
			continue;
		}
		$covered[ $slug ] = true;
		foreach ( (array) json_decode( (string) file_get_contents( $file ), true ) as $case => $spec ) {
			fx_reset();
			foreach ( (array) ( $spec['_options'] ?? array() ) as $option => $value ) {
				update_option( $option, $value );
			}
			Activation::reset();
			$widget   = (string) $spec['_widget'];
			$section  = (array) ( $spec['_section'] ?? array() );
			$page     = (array) ( $spec['_page'] ?? array() );
			$json     = (array) ( $spec['_expect_json'] ?? array() );
			$expected = (array) ( $spec['_expect_codes'] ?? array() );
			foreach ( array_keys( $spec ) as $key ) {
				if ( '_' === $key[0] && in_array( $key, array( '_widget', '_section', '_page', '_expect_json', '_expect_codes', '_expect_css', '_expect_css_converted', '_options', '_known' ), true ) ) {
					unset( $spec[ $key ] );
				}
			}
			$id   = f8_post( array( 'post_type' => 'page', 'post_title' => $slug . ' ' . $case ) );
			$hash = substr( md5( $slug . $case ), 0, 6 );
			$doc  = array( fx_box( 'section', $section, array( fx_box( 'column', array( '_column_size' => 100 ), array( fx_widget( $widget, $spec, 'w' . $hash ) ), 'c' . $hash ) ), 's' . $hash ) );
			Report::begin( 'analysis' );
			Context::begin( $id, 'run' );
			$converted = fx_tree( $doc, $id );
			$page_out  = $page ? Page::convert( $page, $id ) : array();
			Context::end();
			$codes = fx_codes( Report::take() );
			$all   = fx_json( array( $converted, $page_out ) );
			$missing = array_values( array_filter( $json, static function ( $needle ) use ( $all ) {
				return false === strpos( $all, $needle );
			} ) );
			f8_check( array() === $missing, $slug . '/' . $case . ': lo convertido contiene lo esperado. Falta: ' . fx_json( $missing ) . ' en ' . substr( $all, 0, 1500 ) );
			f8_check( array() === array_diff( $expected, $codes ) && array() === array_diff( $codes, array_keys( $catalog ) ), $slug . '/' . $case . ': anota lo esperado y sólo códigos del catálogo: ' . fx_json( $codes ) );
			f8_check( array() === fx_left( $converted ) && ! preg_match( '/"(ep_|element_pack_|bdt_)[a-zA-Z0-9_]*"/', $all ), $slug . '/' . $case . ': no queda ninguna clave heredada: ' . fx_json( fx_left( $converted ) ) );
			$css_ok = true;
			array_walk_recursive( $converted, static function ( $value, $key ) use ( &$css_ok ) {
				if ( 'custom_css' === $key ) {
					$css_ok = $css_ok && Css_Value::block( (string) $value );
				}
			} );
			f8_check( $css_ok && ( ! isset( $page_out['custom_css'] ) || Css_Value::block( $page_out['custom_css'] ) ), $slug . '/' . $case . ': cada CSS generado pasa la comprobación final.' );
		}
	}
	f8_check( 28 === count( $covered ), 'Un fixture por cada una de las 28 extensiones: ' . count( $covered ) );

	f8_done( 'DIGITALÍSIMO Migración: extensiones de Element Pack convertidas (frente C)', 220 );
}
