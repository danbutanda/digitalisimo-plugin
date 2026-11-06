<?php
/**
 * Motor de carrusel `digi-crs` (C1 de docs/PRO_WIDGETS_OBJECTIVE.md, frente E de docs/pro/c1.md §1;
 * modules/digitalisimo-carousel/):
 * - `Engine`: marcado de la raíz, los controles (pausa, flechas, puntos, fracción, barra) antes de la pista, la pista y
 *   las diapositivas; nada de controles con una sola diapositiva; escape; modificadores; RTL; `scope()`;
 * - `Base_Widget` frente a `modules/carousel/widgets/base.php` de PRO y `Nested_Controls` frente a
 *   `base/base-carousel-trait.php` (con los parámetros del carrusel anidado de PRO): mismas secciones, mismos controles
 *   en el mismo orden, con el mismo tipo, valor por defecto (también por dispositivo), opciones, unidades y condiciones,
 *   en LTR y en RTL; y que lo propio no usa selectores ni clases de PRO o de Swiper;
 * - `Base_Widget::print_slider()` y `Nested_Controls::carousel_args()` traducen los ajustes al motor;
 * - `Module`: autocarga, registro tolerante, recursos (≤ 4,5 KB de hoja y ≤ 8 KB de script, `defer`, sin dependencias) y el
 *   formato de `WIDGETS` que leen las pruebas de presupuesto;
 * - la guarda de `scripts/build-packages.sh` (ninguna mención del proveedor retirado) en los archivos del motor;
 * - `tests/fixtures/crs-engine.html` (la que usa tests/elements-crs-engine-chrome.mjs) es el marcado de `Engine`
 *   de hoy: con `--write`, se regenera.
 * Uso: php-wasm-cli tests/elements-crs-engine.php [--write]
 */
namespace Elementor {
	class Controls_Manager {
		const TEXT = 'text'; const TEXTAREA = 'textarea'; const SELECT = 'select'; const SWITCHER = 'switcher'; const CHOOSE = 'choose';
		const HEADING = 'heading'; const COLOR = 'color'; const SLIDER = 'slider'; const NUMBER = 'number'; const DIMENSIONS = 'dimensions';
		const MEDIA = 'media'; const URL = 'url'; const REPEATER = 'repeater'; const ICONS = 'icons'; const TAB_CONTENT = 'content'; const TAB_STYLE = 'style';
	}
	class Repeater {
		public $controls = array();
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function get_controls() { return $this->controls; }
	}
	class Group_Control_Image_Size {
		public static function get_type() { return 'image-size'; }
		public static function get_attachment_image_src( $id, $key, $settings ) { return 'https://sitio.test/wp-content/uploads/' . (int) $id . '-' . ( $settings[ $key . '_size' ] ?? 'full' ) . '.jpg'; }
	}
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Icons_Manager {
		public static function try_get_icon_html( $icon, $attributes = array() ) { return '<i class="' . $icon['value'] . '" aria-hidden="true"></i>'; }
		public static function render_icon( $icon, $attributes = array() ) { echo self::try_get_icon_html( $icon, $attributes ); }
	}
	class Breakpoints { public function get_active_breakpoints() { return array( 'mobile' => 1, 'tablet' => 1 ); } }
	class Plugin {
		public static $instance;
		public $breakpoints;
		public $experiments;
	}
	/** Registro de controles en orden: lo que comparan las pruebas. */
	abstract class Widget_Base {
		public $log = array();
		public $settings = array();
		private $section = '';
		private $tab = '';
		public function start_controls_section( $id, $args = array() ) { $this->section = $id; $this->log[] = array( 'section', $id, array( 'tab' => $args['tab'] ?? 'content', 'condition' => $args['condition'] ?? null ) ); }
		public function end_controls_section() { $this->section = ''; }
		public function start_controls_tabs( $id, $args = array() ) { $this->log[] = array( 'tabs', $id, array() ); }
		public function end_controls_tabs() {}
		public function start_controls_tab( $id, $args = array() ) { $this->log[] = array( 'tab', $id, array( 'condition' => $args['condition'] ?? null ) ); }
		public function end_controls_tab() {}
		public function add_control( $id, $args ) { $this->log[] = array( 'control', $id, $args + array( '_section' => $this->section ) ); }
		public function add_responsive_control( $id, $args ) { $this->log[] = array( 'responsive', $id, $args + array( '_section' => $this->section ) ); }
		public function add_group_control( $type, $args ) { $this->log[] = array( 'group', $args['name'], $args + array( '_type' => $type, '_section' => $this->section ) ); }
		public function get_settings_for_display( $key = null ) {
			$all = $this->settings;
			foreach ( $this->log as $row ) {
				if ( in_array( $row[0], array( 'control', 'responsive' ), true ) && array_key_exists( 'default', $row[2] ) && ! array_key_exists( $row[1], $all ) ) {
					$all[ $row[1] ] = $row[2]['default'];
				}
			}
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
		public function get_name() { return 'probe'; }
	}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace ElementorPro\Base {
	abstract class Base_Widget extends \Elementor\Widget_Base {}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', dirname( __DIR__ ) . '/digitalisimo-elements/pro-elements.php' );
	$GLOBALS['ce'] = array( 'rtl' => false, 'actions' => array(), 'styles' => array(), 'scripts' => array() );
	function __( $text, $domain = '' ) { return $text; }
	function esc_html__( $text, $domain = '' ) { return htmlspecialchars( $text, ENT_QUOTES ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
	function esc_attr__( $text, $domain = '' ) { return esc_attr( $text ); }
	function esc_url( $url ) { return (string) $url; }
	function is_rtl() { return $GLOBALS['ce']['rtl']; }
	function add_action( $hook, $cb, $priority = 10 ) { $GLOBALS['ce']['actions'][ $hook ][] = $cb; }
	function wp_get_attachment_image( $id, $size, $icon = false, $attrs = array() ) {
		$out = '<img src="https://sitio.test/' . (int) $id . '-' . $size . '.jpg" srcset="https://sitio.test/' . (int) $id . '-300.jpg 300w"';
		foreach ( $attrs as $k => $v ) { $out .= ' ' . $k . '="' . esc_attr( $v ) . '"'; }
		return $out . '>';
	}
	function wp_style_is( $h, $s ) { return isset( $GLOBALS['ce']['styles'][ $h ] ); }
	function wp_script_is( $h, $s ) { return isset( $GLOBALS['ce']['scripts'][ $h ] ); }
	function wp_register_style( $h, $src, $deps, $ver ) { $GLOBALS['ce']['styles'][ $h ] = array( $src, $deps ); }
	function wp_register_script( $h, $src, $deps, $ver, $args ) { $GLOBALS['ce']['scripts'][ $h ] = array( $src, $deps, $args ); }
	function plugins_url( $path, $file ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	function check_ce( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $GLOBALS['ce']['n'] = ( $GLOBALS['ce']['n'] ?? 0 ) + 1; }

	$root   = dirname( __DIR__ );
	$module = $root . '/digitalisimo-elements/modules/digitalisimo-carousel/';
	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->breakpoints = new \Elementor\Breakpoints();
	require $module . 'class-module.php';
	\Digitalisimo\Elements\Carousel\Module::init();
	use Digitalisimo\Elements\Carousel\Engine;
	use Digitalisimo\Elements\Carousel\Module;

	// 1. Módulo: ganchos, autocarga y formato del registro.
	foreach ( array( 'elementor/frontend/after_register_styles', 'elementor/frontend/after_register_scripts', 'elementor/widgets/register', 'elementor/editor/after_enqueue_scripts' ) as $hook ) {
		check_ce( ! empty( $GLOBALS['ce']['actions'][ $hook ] ), "Module::init() engancha $hook." );
	}
	check_ce( class_exists( Engine::class ) && class_exists( \Digitalisimo\Elements\Carousel\Base_Widget::class ) && trait_exists( \Digitalisimo\Elements\Carousel\Nested_Controls::class ), 'La autocarga encuentra Engine, Base_Widget y el trait Nested_Controls.' );
	$registry = (string) file_get_contents( $module . 'class-module.php' );
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*array\(([^()]*)\)/", $registry, $entries, PREG_SET_ORDER );
	$names = array_column( $entries, 1 );
	check_ce( array( 'digitalisimo-nested-carousel', 'digitalisimo-media-carousel', 'digitalisimo-testimonial-carousel', 'digitalisimo-reviews', 'digitalisimo-slides' ) === array_slice( $names, 0, 5 ) && 5 === count( Module::WIDGETS ), 'WIDGETS: los cinco carruseles, con el formato que leen las pruebas de presupuesto.' );
	foreach ( Module::WIDGETS as $name => $widget ) {
		check_ce( 'widgets/class-' . substr( $name, 13 ) . '.php' === $widget['file'] && 0 === strpos( $widget['class'], 'Digitalisimo\\Elements\\Carousel\\Widgets\\' ) && 'modules/digitalisimo-carousel/assets/css/' . substr( $name, 13 ) . '.css' === $widget['css'], "$name: archivo, clase y hoja con los nombres anotados en la cabecera de Module." );
		check_ce( isset( Module::TITLES[ $name ] ), "$name: título del panel." );
	}
	check_ce( Engine::HANDLE === Module::style_depends( 'digitalisimo-x' )[0] && array( Engine::HANDLE ) === Module::script_depends( 'digitalisimo-x' ), 'style_depends()/script_depends(): el motor siempre; lo propio sólo si existe.' );
	$manager = new class() { public $registered = array(); public function get_widget_types( $n ) { return null; } public function register( $w ) { $this->registered[] = get_class( $w ); } };
	check_ce( ! in_array( 'digitalisimo-nested-carousel', Module::widgets( $manager ), true ), 'Sin el experimento de anidados, el carrusel anidado no se registra (y nada falla).' );
	Module::register_assets();
	check_ce( isset( $GLOBALS['ce']['styles'][ Engine::HANDLE ], $GLOBALS['ce']['scripts'][ Engine::HANDLE ] ) && array() === $GLOBALS['ce']['scripts'][ Engine::HANDLE ][1] && array( 'in_footer' => true, 'strategy' => 'defer' ) === $GLOBALS['ce']['scripts'][ Engine::HANDLE ][2], 'Recursos del motor registrados: script defer, sin dependencias.' );

	// 2. Recursos: tamaños y contenido.
	$css = (string) file_get_contents( $module . 'assets/css/carousel.css' );
	$js  = (string) file_get_contents( $module . 'assets/js/carousel.js' );
	// Topes de la corrección de fidelidad (bucle real, arrastre, pausa oculta, puntos de PRO): la hoja la comparten los seis
	// carruseles (4,5 KB, excepción declarada en docs/pro/c1.md §1.3) y el script cumple el criterio 7 (8 KB).
	check_ce( strlen( $css ) <= 4608 && strlen( $js ) <= 8192, 'carousel.css ≤ 4,5 KB (' . strlen( $css ) . ' B) y carousel.js ≤ 8 KB (' . strlen( $js ) . ' B).' );
	check_ce( ! preg_match( '/jquery|swiper|elementor-pro-frontend|pro-elements-handlers|\$\(/i', $js ) && ! preg_match( '/swiper|--e-global|url\(/i', $css ), 'Sin jQuery, sin Swiper, sin paquetes de PRO, sin paleta de Elementor ni url().' );
	check_ce( false !== strpos( $js, "'--ready'" ) && false !== strpos( $js, 'frontend/element_ready/global' ) && false !== strpos( $js, "'go'" ) && false !== strpos( $js, "'change'" ) && false !== strpos( $js, 'digiCrs' ) && false !== strpos( $js, 'inert' ), 'El script marca --ready, se reinicia en el editor y expone digiCrs, digi-crs-go y digi-crs-change.' );
	check_ce( false !== strpos( $css, 'prefers-reduced-motion' ) && false !== strpos( $css, 'outline:2px solid;outline-offset:2px' ) && false !== strpos( $css, 'min-width:24px;min-height:24px' ) && false !== strpos( $css, 'height:max(24px,var(--_d) + 7px)' ), 'Movimiento reducido, foco visible (2 px a 2 px), flechas de 24 × 24 px como mínimo y puntos de 24 px de alto.' );
	// Fidelidad con PRO (docs/pro/c1.md, «Diferencias declaradas»): el paso de los puntos es el de Swiper (tamaño + 2 ×
	// separación) con el área táctil sólo en vertical; la pausa, oculta a la vista salvo con el foco del teclado y a salvo
	// del :hover del tema; sin bucle y todo a la vista, las flechas quedan atenuadas; cursor de agarre.
	check_ce( false !== strpos( $css, 'width:calc(var(--_d) + 2*var(--digi-crs-dot-gap,6px))' ) && false === strpos( $css, 'max(24px,var(--_d) + 2*' ) && false !== strpos( $css, '.digi-crs__dot:after{content:"";position:absolute;inset:calc(50% - 12px) 0}' ), 'Puntos con el paso de PRO (tamaño + 2 × separación) y área táctil vertical de 24 px.' );
	check_ce( (bool) preg_match( '/\.digi-crs>button\.digi-crs__pause\{[^}]*clip-path:inset\(50%\)[^}]*\}\.digi-crs>button:focus-visible,\.digi-crs__dot:focus-visible\{[^}]*clip-path:none\}/', $css ), 'Pausa recortada a la vista (0,2,1: gana al :hover del tema) y visible con :focus-visible, que va después.' );
	check_ce( false !== strpos( $css, '.digi-crs--locked>:not(.digi-crs__track,.digi-crs__arrow){display:none}' ) && false !== strpos( $css, '[data-digi-grab]:not(.digi-crs--locked){cursor:grab}' ) && false !== strpos( $css, '[data-digi-grab].digi-crs--drag{cursor:grabbing}' ), 'Bloqueado: flechas atenuadas a la vista; cursor de agarre como grabCursor.' );
	check_ce( false !== strpos( $js, "C+'clone','slick-cloned'" ) && false !== strpos( $js, 'runReadyTrigger' ) && false !== strpos( $js, 'setPointerCapture' ) && false !== strpos( $js, "attr('click')" ) && false !== strpos( $js, "attr('long')" ) && false === strpos( $js, "cl.toggle(R+'--locked',n<2)" ), 'Script: bucle con copias (excluidas del lightbox del núcleo, con sus manejadores), arrastre con el ratón, clic en una diapositiva y sin bloqueo con bucle.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array_merge( glob( $module . '*.php' ), glob( $module . 'assets/*/carousel.*' ), array( $root . '/digitalisimo-elements/modules/digitalisimo-convert/maps/_carousel-classes.php' ) ) as $file ) {
		check_ce( ! preg_match( $guard, (string) file_get_contents( $file ) ), basename( $file ) . ': la guarda del empaquetado no encuentra menciones prohibidas.' );
	}

	// 3. Engine: marcado.
	$html = Engine::open( array( 'count' => 3, 'label' => 'Mi <carrusel> "x"', 'arrows' => true, 'pagination' => 'bullets', 'autoplay' => 4000, 'loop' => true, 'hover' => true, 'interaction' => true, 'speed' => 300, 'id' => 'c1', 'classes' => 'mia otra', 'attributes' => array( 'data-x' => '1', 'onclick' => 'mal()', 'bad name' => 'x', 'class' => 'no' ) ) );
	check_ce( 0 === strpos( $html, '<section class="digi-crs mia otra" aria-roledescription="carrusel" aria-label="Mi &lt;carrusel&gt; &quot;x&quot;" id="c1" data-digi-autoplay="4000" data-digi-hover="1" data-digi-interaction="1" data-digi-loop="1" data-digi-effect="slide" data-digi-pagination="bullets" style="--digi-crs-speed:300ms;--digi-crs-n:3" data-x="1">' ), 'Raíz: clases, nombre escapado, datos del script, velocidad y atributos extra (sin pisar los propios, ni `on*` ni nombres no válidos): ' . substr( $html, 0, 400 ) );
	$order = array( 'digi-crs__pause', 'digi-crs__prev', 'digi-crs__pagination', 'digi-crs__next', 'digi-crs__track' );
	$at    = array_map( static function ( $c ) use ( $html ) { return strpos( $html, $c ); }, $order );
	check_ce( ! in_array( false, $at, true ) && $at === array_values( array_unique( $at ) ) && $at == array_values( ( function ( $a ) { sort( $a ); return $a; } )( $at ) ), 'Controles antes de la pista: pausa, anterior, paginación, siguiente.' );
	check_ce( false !== strpos( $html, '<button type="button" class="digi-crs__pause" data-digi-pause="Pausar" data-digi-play="Reanudar" aria-label="Pausar" hidden>' ) && false !== strpos( $html, 'class="digi-crs__arrow digi-crs__prev" aria-label="Diapositiva anterior">' . Engine::ICON_PREV ) && false !== strpos( $html, 'class="digi-crs__arrow digi-crs__next" aria-label="Diapositiva siguiente">' . Engine::ICON_NEXT ), 'Pausa oculta hasta el script; flechas con nombre y el chevron propio.' );
	check_ce( 3 === substr_count( $html, 'class="digi-crs__dot"' ) && false !== strpos( $html, 'data-digi-dot="Ir a la diapositiva %d"' ) && false !== strpos( $html, 'aria-label="Ir a la diapositiva 1" aria-current="true"' ) && false !== strpos( $html, 'aria-label="Ir a la diapositiva 3"></button>' ), 'Un punto por diapositiva, con nombre; el primero actual.' );
	check_ce( '<div class="digi-crs__track" aria-live="off">' === substr( $html, -strlen( '<div class="digi-crs__track" aria-live="off">' ) ) && '</div></section>' === Engine::close(), 'La pista abre al final (región viva off con autoplay) y close() la cierra con la raíz.' );
	check_ce( 'class="digi-crs__slide x" role="group" aria-roledescription="diapositiva" aria-label="2 de 3"' === Engine::slide_attributes( 1, 3, 'x' ) && array( 'class' => 'digi-crs__slide', 'role' => 'group', 'aria-roledescription' => 'diapositiva', 'aria-label' => '1 de 1' ) === Engine::slide_attribute_array( 0, 0 ), 'Diapositivas: grupo «N de M».' );
	$one = Engine::open( array( 'count' => 1, 'arrows' => true, 'pagination' => 'bullets', 'autoplay' => 5000 ) );
	check_ce( false === strpos( $one, '<button' ) && false === strpos( $one, 'digi-crs__pagination' ) && false !== strpos( $one, 'aria-label="Carrusel"' ), 'Una diapositiva: sin flechas, paginación ni pausa; nombre por defecto.' );
	$fr = Engine::open( array( 'count' => 4, 'pagination' => 'fraction', 'pagination_position' => 'outside', 'effect' => 'fade', 'center' => true, 'arrows' => true, 'arrows_position' => 'outside', 'dir' => 'rtl', 'sync' => '#principal', 'prev' => '', 'next' => '<i class="x"></i>' ) );
	check_ce( false !== strpos( $fr, 'class="digi-crs digi-crs--fade digi-crs--center digi-crs--arrows-out digi-crs--pag-out"' ) && false !== strpos( $fr, 'data-digi-effect="fade" data-digi-center="1" data-digi-pagination="fraction" data-digi-sync="#principal"' ), 'Modificadores y datos: fundido, centrado, flechas y paginación fuera, sincronización.' );
	check_ce( false !== strpos( $fr, '<span class="digi-crs__fraction"><span class="digi-crs__now">1</span> / <span class="digi-crs__total">4</span></span>' ) && false !== strpos( $fr, 'digi-crs__prev" aria-label="Diapositiva anterior"></button>' ) && false !== strpos( $fr, '<i class="x"></i></button>' ) && false !== strpos( $fr, '<div class="digi-crs__track" aria-live="polite" dir="rtl">' ) && false === strpos( $fr, 'digi-crs__pause' ), 'Fracción, iconos vaciados o propios, pista RTL y región viva polite sin autoplay.' );
	$bar = Engine::open( array( 'count' => 2, 'pagination' => 'progressbar', 'effect' => 'cube', 'dir' => 'up' ) );
	check_ce( false !== strpos( $bar, '<div class="digi-crs__bar" aria-hidden="true"><div class="digi-crs__fill"></div></div>' ) && false !== strpos( $bar, 'data-digi-effect="slide"' ) && false === strpos( $bar, 'dir=' ) && false === strpos( $bar, 'digi-crs--pag-out' ), 'Barra decorativa; un efecto o una dirección desconocidos no pasan.' );
	$GLOBALS['ce']['rtl'] = true;
	$rtl                  = Engine::open( array( 'count' => 2, 'arrows' => true ) );
	$GLOBALS['ce']['rtl'] = false;
	check_ce( false !== strpos( $rtl, 'digi-crs__prev" aria-label="Diapositiva anterior">' . Engine::ICON_NEXT ) && false !== strpos( $rtl, 'digi-crs__next" aria-label="Diapositiva siguiente">' . Engine::ICON_PREV ), 'En RTL los chevrons propios se invierten, como PRO.' );
	check_ce( '{{WRAPPER}} > .digi-crs > .a, {{WRAPPER}} > .elementor-widget-container > .digi-crs > .a' === Engine::scope( ' > .a' ), 'scope(): la raíz del propio widget, con o sin envoltorio interior.' );
	check_ce( false !== strpos( $js, "'" . Engine::PATH_PLAY . "'" ) && false !== strpos( Engine::ICON_PAUSE, Engine::PATH_PAUSE ), 'El script conoce el icono Reanudar del botón de pausa.' );

	// 4. Controles frente a PRO.
	require $root . '/digitalisimo-elements/modules/carousel/widgets/base.php';
	require $root . '/digitalisimo-elements/base/base-carousel-trait.php';
	$fields = static function ( array $log ): array {
		$out = array();
		foreach ( $log as $row ) {
			list( $kind, $id, $a ) = $row;
			$keep = array( 'kind' => $kind, 'id' => $id );
			foreach ( array( 'type', 'default', 'condition', 'conditions', 'size_units', 'tab', '_section', '_type', 'exclude', 'types', 'inherit_placeholders', 'label_block', 'skin', 'fa4compatibility' ) as $k ) {
				if ( array_key_exists( $k, $a ) && null !== $a[ $k ] ) {
					$keep[ $k ] = $a[ $k ];
				}
			}
			foreach ( $a as $k => $v ) {
				// Diferencia declarada: «Predeterminado» de visibles y paso, por dispositivo (se comprueba aparte).
				if ( preg_match( '/_default$/', $k ) && ! in_array( $id, array( 'slides_per_view', 'slides_to_scroll' ), true ) ) {
					$keep[ $k ] = $v;
				}
			}
			if ( 'text' === ( $a['type'] ?? '' ) ) {
				unset( $keep['default'] ); // Textos visibles por defecto, en español (el mapa completa los de PRO al convertir).
			}
			if ( isset( $a['options'] ) && is_array( $a['options'] ) ) {
				$keep['options'] = array_map( 'strval', array_keys( $a['options'] ) );
			}
			if ( isset( $a['range'] ) ) {
				$keep['range'] = $a['range'];
			}
			if ( isset( $a['fields_options'] ) ) {
				$keep['fields_options'] = array_keys( $a['fields_options'] );
			}
			$out[] = $keep;
		}
		return $out;
	};
	$diff = static function ( array $a, array $b ): string {
		$n = max( count( $a ), count( $b ) );
		for ( $i = 0; $i < $n; $i++ ) {
			if ( ( $a[ $i ] ?? null ) !== ( $b[ $i ] ?? null ) ) {
				return "#$i propio " . json_encode( $a[ $i ] ?? null ) . ' · PRO ' . json_encode( $b[ $i ] ?? null );
			}
		}
		return '';
	};
	$css_rules = static function ( array $log ): string {
		$text = '';
		foreach ( $log as $row ) {
			foreach ( array( 'selectors', 'selector', 'prefix_class' ) as $k ) {
				if ( isset( $row[2][ $k ] ) ) {
					$text .= ' ' . json_encode( $row[2][ $k ] );
				}
			}
			foreach ( (array) ( $row[2]['fields_options'] ?? array() ) as $f ) {
				$text .= ' ' . json_encode( $f['selectors'] ?? '' );
			}
		}
		return $text;
	};
	foreach ( array( false, true ) as $rtl_mode ) {
		$GLOBALS['ce']['rtl'] = $rtl_mode;
		$pro = new class() extends \ElementorPro\Modules\Carousel\Widgets\Base {
			protected function add_repeater_controls( \Elementor\Repeater $r ) { $r->add_control( 'x', array( 'type' => 'text' ) ); }
			protected function get_repeater_defaults() { return array( array( 'x' => 1 ) ); }
			protected function print_slide( array $slide, array $settings, $element_key ) {}
			public function reg() { $this->register_controls(); return $this->log; }
		};
		$own = new class() extends \Digitalisimo\Elements\Carousel\Base_Widget {
			protected function add_repeater_controls( \Elementor\Repeater $r ) { $r->add_control( 'x', array( 'type' => 'text' ) ); }
			protected function get_repeater_defaults() { return array( array( 'x' => 1 ) ); }
			protected function print_slide( array $slide, array $settings, $element_key ) { echo '<p>' . esc_attr( $slide['x'] ?? '' ) . '|' . $element_key . '</p>'; }
			public function reg() { $this->register_controls(); return $this->log; }
			public function slider( array $s ) { ob_start(); $this->print_slider( $s ); return ob_get_clean(); }
			public function image( array $slide, array $s ) { return $this->get_slide_image( $slide, $s ); }
		};
		$pro_log = $pro->reg();
		$own_log = $own->reg();
		$d       = $diff( $fields( $own_log ), $fields( $pro_log ) );
		check_ce( '' === $d, 'Base_Widget repite base.php de PRO' . ( $rtl_mode ? ' (RTL)' : '' ) . ': ' . $d );
		$defaults_of = static function ( array $log, string $id ): array {
			foreach ( $log as $row ) {
				if ( $id === $row[1] ) {
					return array_filter( $row[2], static function ( $k ) { return (bool) preg_match( '/_default$/', $k ); }, ARRAY_FILTER_USE_KEY );
				}
			}
			return array();
		};
		check_ce( array() === $defaults_of( $pro_log, 'slides_per_view' ) && array( 'desktop_default' => '3', 'tablet_default' => '2', 'mobile_default' => '1' ) === $defaults_of( $own_log, 'slides_per_view' ) && array( 'tablet_default' => '1', 'mobile_default' => '1' ) === $defaults_of( $own_log, 'slides_to_scroll' ), 'Visibles y paso por dispositivo con los valores que PRO aplicaba en «Predeterminado» (3/2/1 y paso 1): ' . json_encode( array( $defaults_of( $own_log, 'slides_per_view' ), $defaults_of( $own_log, 'slides_to_scroll' ) ) ) );
		$one_view = new class() extends \Digitalisimo\Elements\Carousel\Base_Widget {
			protected function add_repeater_controls( \Elementor\Repeater $r ) {}
			protected function get_repeater_defaults() { return array(); }
			protected function print_slide( array $slide, array $settings, $element_key ) {}
			protected function slides_per_view_defaults(): array { return array( 'desktop' => '1' ); }
			public function reg() { $this->register_controls(); return $this->log; }
		};
		check_ce( array( 'desktop_default' => '1', 'tablet_default' => '1', 'mobile_default' => '1' ) === $defaults_of( $one_view->reg(), 'slides_per_view' ), 'slides_per_view_defaults() redefinible (testimonios y reseñas: 1 en cada dispositivo).' );
		check_ce( 31 <= count( array_filter( $pro_log, static function ( $r ) { return in_array( $r[0], array( 'control', 'responsive', 'group' ), true ); } ) ), 'base.php de PRO aporta sus 31 controles.' );
		check_ce( ! preg_match( '/swiper|elementor-arrows|elementor-pagination/i', $css_rules( $own_log ) ), 'Base_Widget: sin selectores ni clases de PRO o de Swiper.' );

		$trait_calls = static function ( $w ) {
			$w->start_controls_section( 'section_slides', array() );
			$w->add_carousel_layout_controls( array( 'css_prefix' => 'e-n-carousel-', 'slides_to_show_custom_settings' => array( 'separator' => 'before', 'tablet_default' => '2', 'mobile_default' => '1', 'frontend_available' => true, 'render_type' => 'template' ), 'slides_to_scroll_custom_settings' => array(), 'equal_height_custom_settings' => array(), 'slides_on_display' => 8 ) );
			$w->end_controls_section();
			$w->add_carousel_settings_controls( array( 'css_prefix' => 'e-n-carousel-', 'autoplay_custom_settings' => array( 'description' => 'x' ), 'infinite_custom_settings' => array( 'description' => 'x' ), 'offset_sides_custom_settings' => array( 'description' => 'x' ) ) );
			$w->add_carousel_navigation_controls( array( 'css_prefix' => 'e-n-carousel-' ) );
			$w->add_carousel_pagination_controls( array( 'css_prefix' => 'e-n-carousel-' ) );
			$w->add_carousel_navigation_styling_controls( array( 'css_prefix' => 'e-n-carousel-', 'navigation_styling_custom_settings' => array( 'condition' => array( 'arrows' => 'yes' ) ) ) );
			$w->add_carousel_pagination_style_controls( array( 'css_prefix' => 'e-n-carousel-' ) );
			return $w->log;
		};
		$pro_t = $trait_calls( new class() extends \Elementor\Widget_Base { use \ElementorPro\Base\Base_Carousel_Trait; } );
		$own_t = new class() extends \Elementor\Widget_Base { use \Digitalisimo\Elements\Carousel\Nested_Controls; };
		$own_l = $trait_calls( $own_t );
		$d     = $diff( $fields( $own_l ), $fields( $pro_t ) );
		check_ce( '' === $d, 'Nested_Controls repite Base_Carousel_Trait de PRO' . ( $rtl_mode ? ' (RTL)' : '' ) . ': ' . $d );
		check_ce( 55 <= count( array_filter( $pro_t, static function ( $r ) { return in_array( $r[0], array( 'control', 'responsive', 'group' ), true ); } ) ), 'El trait de PRO aporta sus controles (' . count( $pro_t ) . ' filas).' );
		check_ce( ! preg_match( '/swiper|e-n-carousel|elementor-arrows|elementor-pagination/i', $css_rules( $own_l ) ), 'Nested_Controls: sin selectores ni variables de PRO o de Swiper.' );
	}
	$GLOBALS['ce']['rtl'] = false;
	$names_of = static function ( array $log ): array { return array_column( array_filter( $log, static function ( $r ) { return 'section' !== $r[0] && 'tabs' !== $r[0] && 'tab' !== $r[0]; } ), 1 ); };
	check_ce( array() === array_diff( array( 'arrow_normal_color', 'arrow_hover_color', 'arrows_normal_background', 'arrows_hover_border_type', 'arrows_hover_box_shadow', 'dots_custom_position', 'heading_dotspagination_vertical', 'fraction_vertical_offset' ), $names_of( $own_l ) ), 'Los nombres que PRO arma por concatenación existen igual.' );
	$dict = array();
	foreach ( $own_l as $row ) {
		$dict[ $row[1] ] = $row[2]['selectors_dictionary'] ?? null;
	}
	check_ce( '--digi-crs-prev-tx: -50%; --digi-crs-prev-x: calc(50% + var(--digi-crs-prev-px, 0px)); --digi-crs-prev-t: calc(var(--digi-crs-prev-tx) * var(--direction-multiplier, 1)) var(--digi-crs-prev-ty, -50%);' === $dict['navigation_previous_icon_horizontal_orientation']['center'] && false !== strpos( $dict['navigation_next_icon_vertical_orientation']['center'], '--digi-crs-next-y: calc(50% + var(--digi-crs-next-py, 0px) - 15px)' ) && false !== strpos( $dict['dots_vertical_position']['end'], '--digi-crs-pag-y: calc(100% + var(--digi-crs-pag-oy, 0px))' ), 'Las posiciones de PRO se componen en las variables del motor (con el margen de 15 px de PRO).' );

	// 5. Traducción de los ajustes al motor.
	$out = $own->slider( array( 'slides' => array( array( 'x' => 'a' ), array( 'x' => 'b' ), array( 'x' => 'c' ) ), 'effect' => 'cube', 'pagination' => 'fraction', 'container_class' => 'digi-mc', 'slide_class' => 'digi-mc__s', 'engine' => array( 'id' => 'p1' ) ) + $own->get_settings_for_display() );
	check_ce( false !== strpos( $out, 'class="digi-crs digi-crs--pag-out digi-mc"' ) && false !== strpos( $out, 'aria-label="Diapositivas" id="p1" data-digi-autoplay="5000" data-digi-hover="1" data-digi-interaction="1" data-digi-loop="1" data-digi-effect="slide" data-digi-pagination="fraction" data-digi-grab="1" data-digi-click="1" style="--digi-crs-speed:500ms;--digi-crs-n:3"' ), 'print_slider(): valores por defecto de PRO al motor (autoplay 5 s con sus pausas, bucle, cubo como slide, paginación fuera, grabCursor y slideToClickedSlide) y los extra: ' . substr( $out, 0, 500 ) );
	$grab = Engine::open( array( 'count' => 2, 'grab' => true ) );
	check_ce( false !== strpos( $grab, 'data-digi-effect="slide" data-digi-grab="1" style=' ) && false === strpos( $grab, 'data-digi-click' ) && false === strpos( Engine::open( array( 'count' => 2 ) ), 'data-digi-grab' ) && false !== strpos( Engine::open( array( 'count' => 2, 'long' => true ) ), 'data-digi-effect="slide" data-digi-long="1" style=' ), 'Engine: data-digi-grab, data-digi-click y data-digi-long sólo si se piden.' );
	check_ce( 3 === substr_count( $out, 'class="digi-crs__slide digi-mc__s" role="group"' ) && false !== strpos( $out, '<p>b|slide-1-2</p>' ) && '</div></section>' === substr( $out, -16 ) && 2 === substr_count( $out, 'digi-crs__arrow' ), 'print_slider(): una diapositiva por fila con la clave de PRO, flechas por defecto.' );
	$off = $own->slider( array( 'slides' => array( array(), array() ), 'autoplay' => '', 'loop' => '', 'show_arrows' => '', 'pagination' => '', 'effect' => 'fade' ) + $own->get_settings_for_display() );
	check_ce( false === strpos( $off, 'data-digi-autoplay' ) && false === strpos( $off, 'data-digi-loop' ) && false === strpos( $off, '<button' ) && false !== strpos( $off, 'digi-crs--fade' ), 'Sin autoplay, bucle, flechas ni paginación; fundido.' );
	check_ce( false !== strpos( $own->image( array( 'image' => array( 'id' => 7, 'url' => 'u', 'alt' => 'Alt' ) ), array( 'image_size_size' => 'large', 'lazyload' => 'yes' ) ), 'src="https://sitio.test/7-large.jpg" srcset="https://sitio.test/7-300.jpg 300w" alt="Alt" decoding="async">' ) && 'src="https://x.test/a.jpg" alt="" decoding="async" width="300" height="200"' === substr( $own->image( array( 'image' => array( 'url' => 'https://x.test/a.jpg' ) ), array( 'image_size_size' => 'custom', 'image_size_custom_dimension' => array( 'width' => 300, 'height' => 200 ) ) ), 5, -1 ), 'Imágenes: wp_get_attachment_image con srcset y decoding, sin `loading` propio (lo pone WordPress o el núcleo; uno propio saldría duplicado); sin adjunto, la URL con sus medidas.' );
	check_ce( 'src="https://x.test/b.jpg" alt="" decoding="async" loading="lazy"' === substr( $own->image( array( 'image' => array( 'url' => 'https://x.test/b.jpg' ) ), array( 'lazyload' => 'yes' ) ), 5, -1 ) && false === strpos( $own->image( array( 'image' => array( 'url' => 'https://x.test/a.jpg' ) ), array( 'lazyload' => 'yes', 'image_size_size' => 'custom', 'image_size_custom_dimension' => array( 'width' => 3, 'height' => 2 ) ) ), 'loading' ), 'Carga diferida: `loading="lazy"` propio sólo en una imagen sin medidas, una sola vez.' );
	$own_t->settings = array( 'carousel_name' => 'Galería', 'navigation_previous_icon' => array( 'value' => 'fas fa-arrow-left', 'library' => 'fa-solid' ), 'pagination' => 'bullets', 'dots_position' => 'inside', 'arrows_position' => 'outside', 'infinite' => '' );
	$own_t->num_of_carousel_items = 4;
	$args = $own_t->carousel_args( $own_t->get_settings_for_display() );
	check_ce( 'Galería' === $args['label'] && 4 === $args['count'] && 5000 === $args['autoplay'] && false === $args['loop'] && true === $args['hover'] && 'bullets' === $args['pagination'] && 'inside' === $args['pagination_position'] && 'outside' === $args['arrows_position'] && '<i class="fas fa-arrow-left" aria-hidden="true"></i>' === $args['prev'] && null === $args['next'] && 'rtl' === $args['dir'], 'carousel_args(): nombre, recuento, autoplay, bucle, pausas, paginación y flechas con su posición, iconos (el chevron por defecto, el propio) y dirección (por defecto la del sitio, aquí RTL).' );

	// 6. Página de prueba del navegador: el marcado de Engine de hoy.
	$slide = static function ( int $n, int $count, string $extra = '' ): string {
		return '<div ' . Engine::slide_attributes( $n, $count ) . '><div class="s" style="height:' . ( 100 + 10 * $n ) . 'px">Diapositiva ' . ( $n + 1 ) . ' <a href="#e' . $n . '">enlace ' . ( $n + 1 ) . '</a>' . $extra . '</div></div>';
	};
	$carousel = static function ( string $id, array $args, int $count, string $style = '', string $attrs = '' ) use ( $slide ): string {
		$html = '<div class="elementor-element elementor-widget" data-id="' . $id . '" style="' . $style . '"' . $attrs . '>' . Engine::open( $args + array( 'count' => $count, 'label' => 'Carrusel ' . $id ) );
		for ( $i = 0; $i < $count; $i++ ) {
			$html .= $slide( $i, $count );
		}
		return $html . Engine::close() . '</div>' . "\n";
	};
	$fixture  = "<!doctype html>\n<html lang=\"es\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width\"><title>digi-crs</title>\n";
	$fixture .= "<!-- Generada por tests/elements-crs-engine.php (--write); la usa tests/elements-crs-engine-chrome.mjs. -->\n";
	$fixture .= "<style>:root{--direction-multiplier:1}html[dir=rtl],[dir=rtl]{--direction-multiplier:-1}body{margin:0;font:16px/1.5 sans-serif}.elementor-widget{position:relative;margin:0 0 40px;padding:0 20px}.s{background:#ddd;box-sizing:border-box;padding:8px}.spacer{height:1600px}button{border:1px solid #c36;padding:.5rem 1rem;border-radius:3px;color:#c36}[type=button]:hover,[type=button]:focus{background-color:#c36;color:#fff}</style>\n";
	$fixture .= "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-carousel/assets/css/carousel.css\">\n<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-carousel/assets/css/nested-carousel.css\">\n</head><body>\n";
	$fixture .= $carousel( 'w1', array( 'arrows' => true, 'pagination' => 'bullets', 'pagination_position' => 'outside' ), 6, '--digi-crs-view:3;--digi-crs-scroll:2;--digi-crs-gap:10px' );
	$fixture .= $carousel( 'w2', array( 'autoplay' => 1000, 'loop' => true, 'hover' => true, 'effect' => 'fade', 'pagination' => 'fraction', 'arrows' => true, 'speed' => 200 ), 4 );
	$fixture .= $carousel( 'w3', array( 'loop' => true, 'arrows' => true, 'pagination' => 'progressbar' ), 5, '', ' dir="rtl"' );
	$fixture .= $carousel( 'w5', array( 'arrows' => true, 'id' => 'principal', 'speed' => 0 ), 5 );
	$fixture .= $carousel( 'w4', array( 'center' => true, 'sync' => '#principal' ), 5, '--digi-crs-view:3;--digi-crs-gap:10px' );
	$fixture .= $carousel( 'w6', array( 'arrows' => true, 'pagination' => 'bullets' ), 1 );
	$fixture .= $carousel( 'w7', array( 'autoplay' => 800, 'interaction' => true, 'arrows' => true, 'pagination' => 'bullets', 'speed' => 0 ), 3 );
	$fixture .= $carousel( 'w8', array( 'arrows' => true, 'pagination' => 'bullets' ), 3, '--digi-crs-view:4' );
	$fixture .= $carousel( 'w12', array( 'loop' => true, 'arrows' => true, 'pagination' => 'bullets', 'speed' => 0 ), 4, '--digi-crs-view:2;--digi-crs-gap:10px' );
	$fixture .= $carousel( 'w13', array( 'loop' => true, 'arrows' => true, 'pagination' => 'fraction', 'speed' => 0 ), 4, '--digi-crs-view:2;--digi-crs-gap:10px' );
	$fixture .= $carousel( 'w14', array( 'arrows' => true, 'pagination' => 'bullets', 'pagination_position' => 'outside' ), 6 );
	$fixture .= $carousel( 'w11', array( 'autoplay' => 700, 'loop' => true, 'hover' => true, 'interaction' => true, 'arrows' => true, 'pagination' => 'bullets', 'speed' => 300 ), 4 );
	// Fidelidad con PRO: bucle con todo a la vista (w15), miniaturas centradas en bucle (w16), autoplay en bucle que siempre
	// avanza (w17), bucle con copias repetidas (w18, 4 diapositivas, 3 visibles, paso 2), arrastre y clic (w19), puntos del
	// carrusel anidado pegados al borde (w20) y sin acotar las visibles (w21).
	$fixture .= $carousel( 'w15', array( 'loop' => true, 'arrows' => true, 'pagination' => 'bullets', 'speed' => 0 ), 3, '--digi-crs-view:3;--digi-crs-gap:10px' );
	$fixture .= $carousel( 'w16', array( 'loop' => true, 'center' => true, 'speed' => 0 ), 7, '--digi-crs-view:4;--digi-crs-gap:10px' );
	$fixture .= $carousel( 'w17', array( 'autoplay' => 900, 'loop' => true, 'arrows' => true, 'pagination' => 'bullets', 'speed' => 300 ), 5, '--digi-crs-view:3;--digi-crs-gap:10px' );
	$fixture .= $carousel( 'w18', array( 'loop' => true, 'arrows' => true, 'pagination' => 'bullets', 'speed' => 0 ), 4, '--digi-crs-view:3;--digi-crs-scroll:2;--digi-crs-gap:10px' );
	$fixture .= $carousel( 'w19', array( 'arrows' => true, 'pagination' => 'bullets', 'grab' => true, 'click' => true, 'speed' => 200 ), 6, '--digi-crs-view:3;--digi-crs-gap:10px' );
	$fixture .= $carousel( 'w20', array( 'arrows' => true, 'pagination' => 'bullets', 'pagination_position' => 'outside', 'classes' => 'digi-ncarousel', 'long' => true, 'speed' => 0 ), 5, '--digi-crs-view:3;--digi-crs-gap:10px;--digi-crs-pag-y:100%;--digi-crs-pag-space:16px' );
	$fixture .= $carousel( 'w21', array( 'loop' => true, 'arrows' => true, 'classes' => 'digi-ncarousel', 'speed' => 0 ), 2, '--digi-crs-view:3;--digi-crs-gap:10px' );
	$fixture .= "<div class=\"spacer\"></div>\n";
	$fixture .= $carousel( 'w9', array( 'autoplay' => 600, 'arrows' => true, 'speed' => 0 ), 3 );
	$fixture .= "<script src=\"../../digitalisimo-elements/modules/digitalisimo-carousel/assets/js/carousel.js\" defer></script>\n</body></html>\n";
	$path = __DIR__ . '/fixtures/crs-engine.html';
	if ( in_array( '--write', $argv ?? array(), true ) ) {
		file_put_contents( $path, $fixture );
	}
	check_ce( is_file( $path ) && file_get_contents( $path ) === $fixture, 'tests/fixtures/crs-engine.html es el marcado de Engine de hoy (regenerar con --write).' );

	echo 'DIGITALÍSIMO Elements: motor de carrusel digi-crs (marcado, controles de base.php y del trait de PRO, recursos y página de prueba) validado: ' . $GLOBALS['ce']['n'] . " comprobaciones.\n";
}
