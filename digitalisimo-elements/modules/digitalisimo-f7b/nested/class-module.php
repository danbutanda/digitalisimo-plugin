<?php
namespace Digitalisimo\Elements\F7b\Nested;

defined( 'ABSPATH' ) || exit;

/**
 * Anidados de F7b (frentes F y G): el slider anidado ligero (C5) y la presentación de pestañas y acordeón (C6).
 *
 * - **Guardas** (D3 de F7a), cuando el núcleo ya puede responder: `Widget_Nested_Base` hereda de `Widget_Base`, que el
 *   núcleo declara en `init` (`elementor/includes/managers/widgets.php:213-215`, desde `Plugin::init_components()`), así
 *   que en `plugins_loaded`, donde arranca F7b, comprobarla haría fallar su autocarga. Por eso `init()` sólo engancha y la
 *   guarda corre en `elementor/widgets/register` y al encolar el script del editor (`ready()`): sin el experimento
 *   `nested-elements` del núcleo no se registra nada, como los anidados del núcleo; sin `Widget_Nested_Base` o sin
 *   `Control_Nested_Repeater`, `F7b\Module::requires()` lo anota para el aviso del editor y tampoco.
 * - **Presentación** (frente G): si existe `Nested\Presentation`, se arranca aquí (su `init()` comprueba sus símbolos).
 * - **Registro** como F4-Woo (`Woo\Registry`): `WIDGETS` tiene el mismo formato que `Widget_Registry::WIDGETS`
 *   (`nombre => array( 'file', 'class', 'css', 'js' )`, sin paréntesis anidados) para que `tests/elements-front-budget.php`
 *   y `tests/elements-kit-inventory.mjs` lo lean con la misma expresión. `file` es relativo a `modules/digitalisimo-f7b/`;
 *   `css` y `js`, a la raíz del plugin. Registro tolerante: un archivo que falta o que falla se omite sin error.
 * - **Recursos** (V.13): `nested-slider.css` (≤ 4 KB) y `nested-slider.js` (≤ 6 KB, `defer`, sin jQuery ni dependencias)
 *   sólo se registran, cuando el núcleo registra los suyos (`elementor/frontend/after_register_*`, desde su
 *   `wp_enqueue_scripts`); los encola el núcleo por `get_style_depends()`/`get_script_depends()` del widget, es decir,
 *   sólo donde se pinta (y la caché de elementos guarda los handles). `nested-slider-editor.js` sólo en el editor.
 *
 * Nada de esto lee opciones ni engancha filtros de contenido en el front: con el widget ausente, 0 bytes y 0 consultas.
 */
final class Module {
	const BASE       = 'Elementor\\Modules\\NestedElements\\Base\\Widget_Nested_Base';
	const REPEATER   = 'Elementor\\Modules\\NestedElements\\Controls\\Control_Nested_Repeater';
	const EXPERIMENT = 'nested-elements';

	const WIDGETS = array(
		'digitalisimo-nested-slider' => array(
			'file'  => 'nested/class-slider-widget.php',
			'class' => Slider_Widget::class,
			'css'   => 'modules/digitalisimo-f7b/assets/css/nested-slider.css',
			'js'    => 'modules/digitalisimo-f7b/assets/js/nested-slider.js',
		),
	);

	/** Títulos del panel, para quien los liste sin cargar la clase. */
	const TITLES = array(
		'digitalisimo-nested-slider' => 'Slider anidado ligero',
	);

	/**
	 * Contador opcional del slider (F8, F8-D14; V.10.4): hoja (≤ 256 B) y script (≤ 512 B, `defer`, sin dependencias) con
	 * el mismo handle, `Slider_Widget::COUNTER`. Sólo se registran: los encola el cargador del núcleo por la declaración
	 * `assets` del control `counter`, en las páginas que lo activan, y en la vista previa del editor `preview_counter_*()`
	 * (allí el núcleo no aplica los recursos de página).
	 */
	const COUNTER_HANDLE = 'digitalisimo-nested-slider-counter';
	const COUNTER_CSS    = 'modules/digitalisimo-f7b/assets/css/nested-slider-counter.css';
	const COUNTER_JS     = 'modules/digitalisimo-f7b/assets/js/nested-slider-counter.js';

	/** Script del editor: registra el tipo sobre `NestedElementBase`, como `nested-tabs.js` del núcleo. */
	const EDITOR_HANDLE = 'digitalisimo-nested-slider-editor';
	const EDITOR_SCRIPT = 'modules/digitalisimo-f7b/assets/js/nested-slider-editor.js';

	/** Prioridad en `elementor/widgets/register`, la de `Widget_Registry` y `Woo\Registry`. */
	const PRIORITY = 20;

	/** @var bool */
	private static $initialized = false;

	/** @var bool|null Resultado de la guarda en esta petición. */
	private static $ready = null;

	/** @var array<string,string> Clase cargada por widget ('' si no se pudo). */
	private static $loaded = array();

	/**
	 * Arranque desde `F7b\Module::BOOT` (en `plugins_loaded`). Idempotente. Sólo engancha: no carga nada del núcleo ni
	 * lee opciones. La presentación del frente G arranca aquí con sus propias guardas.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		self::presentation();
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ), self::PRIORITY );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_script' ) );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'preview_counter_style' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'preview_counter_script' ) );
	}

	/**
	 * Guarda del slider, memorizada por petición: el experimento de anidados activo y, después, `Widget_Nested_Base` y
	 * `Control_Nested_Repeater` (por `F7b\Module::requires()`, que anota lo que falte). Sólo desde `init` en adelante.
	 */
	public static function ready(): bool {
		if ( null === self::$ready ) {
			self::$ready = self::feature( self::EXPERIMENT ) && \Digitalisimo\Elements\F7b\Module::requires( array( self::BASE, self::REPEATER ), 'nested' );
		}
		return self::$ready;
	}

	/** Presentación de pestañas y acordeón (frente G), si su clase existe. */
	private static function presentation(): void {
		$class = __NAMESPACE__ . '\\Presentation';
		if ( ! class_exists( $class ) && is_file( __DIR__ . '/class-presentation.php' ) ) {
			require_once __DIR__ . '/class-presentation.php';
		}
		if ( class_exists( $class, false ) && method_exists( $class, 'init' ) ) {
			$class::init();
		}
	}

	/** Experimento del núcleo activo (sin el gestor de experimentos, `false`). */
	public static function feature( string $feature ): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$experiments = isset( \Elementor\Plugin::$instance->experiments ) ? \Elementor\Plugin::$instance->experiments : null;
		return is_object( $experiments ) && method_exists( $experiments, 'is_feature_active' ) && (bool) $experiments->is_feature_active( $feature );
	}

	/** Carpeta del módulo F7b (`modules/digitalisimo-f7b/`), con barra final. */
	public static function dir(): string {
		return dirname( __DIR__ ) . '/';
	}

	/** Ruta en disco de un archivo relativo a la raíz del plugin. */
	public static function path( string $relative ): string {
		return dirname( dirname( dirname( __DIR__ ) ) ) . '/' . ltrim( $relative, '/' );
	}

	/** Registra la hoja y el script de cada widget cuyo archivo existe (sin encolarlos). Idempotente. */
	public static function register_assets(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		foreach ( self::WIDGETS as $name => $widget ) {
			if ( ! wp_style_is( $name, 'registered' ) && is_file( self::path( $widget['css'] ) ) ) {
				wp_register_style( $name, plugins_url( $widget['css'], DIGITALISIMO_ELEMENTS_FILE ), array(), $version );
			}
			if ( ! wp_script_is( $name, 'registered' ) && is_file( self::path( $widget['js'] ) ) ) {
				wp_register_script( $name, plugins_url( $widget['js'], DIGITALISIMO_ELEMENTS_FILE ), array(), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
			}
		}
		if ( ! wp_style_is( self::COUNTER_HANDLE, 'registered' ) && is_file( self::path( self::COUNTER_CSS ) ) ) {
			wp_register_style( self::COUNTER_HANDLE, plugins_url( self::COUNTER_CSS, DIGITALISIMO_ELEMENTS_FILE ), array(), $version );
		}
		if ( ! wp_script_is( self::COUNTER_HANDLE, 'registered' ) && is_file( self::path( self::COUNTER_JS ) ) ) {
			wp_register_script( self::COUNTER_HANDLE, plugins_url( self::COUNTER_JS, DIGITALISIMO_ELEMENTS_FILE ), array(), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	}

	/**
	 * `elementor/preview/enqueue_styles` y `…_scripts`: en la vista previa del editor el núcleo no aplica los recursos de
	 * página (`elementor/core/base/elements-iteration-actions/assets.php`), así que el contador se encola siempre, como la
	 * hoja de la presentación (`Presentation::preview_style()`). Sólo con el slider disponible.
	 */
	public static function preview_counter_style(): void {
		if ( self::ready() ) {
			self::register_assets();
			wp_enqueue_style( self::COUNTER_HANDLE );
		}
	}

	/** Ídem, el script (`elementor/preview/enqueue_scripts`). */
	public static function preview_counter_script(): void {
		if ( self::ready() ) {
			self::register_assets();
			wp_enqueue_script( self::COUNTER_HANDLE );
		}
	}

	/** ¿Es un widget del registro y existe su archivo? */
	public static function available( string $name ): bool {
		return isset( self::WIDGETS[ $name ] ) && is_file( self::dir() . self::WIDGETS[ $name ]['file'] );
	}

	/**
	 * Carga el archivo de un widget y devuelve su clase, o '' si falta el archivo, la clase o la base de anidados, o si
	 * cargarlo falla. Nunca un error fatal.
	 */
	public static function load( string $name ): string {
		if ( isset( self::$loaded[ $name ] ) ) {
			return self::$loaded[ $name ];
		}
		self::$loaded[ $name ] = '';
		if ( ! self::available( $name ) || ! self::ready() ) {
			return '';
		}
		$class = ltrim( (string) self::WIDGETS[ $name ]['class'], '\\' );
		try {
			if ( ! class_exists( $class, false ) ) {
				require_once self::dir() . self::WIDGETS[ $name ]['file'];
			}
			if ( class_exists( $class, false ) && is_subclass_of( $class, '\\' . self::BASE ) ) {
				self::$loaded[ $name ] = $class;
			}
		} catch ( \Throwable $error ) {
			self::$loaded[ $name ] = '';
		}
		return self::$loaded[ $name ];
	}

	/**
	 * `elementor/widgets/register` (20): registra cada widget disponible que el núcleo aún no tenga, sólo si pasa la
	 * guarda (`ready()`): sin el experimento de anidados, como `nested-tabs`, cuyo módulo no se carga sin él.
	 *
	 * @param object $manager `Widgets_Manager` del núcleo.
	 * @return string[] Widgets registrados en esta llamada.
	 */
	public static function widgets( $manager ): array {
		$registered = array();
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'register' ) || ! self::ready() ) {
			return $registered;
		}
		foreach ( array_keys( self::WIDGETS ) as $name ) {
			if ( method_exists( $manager, 'get_widget_types' ) && $manager->get_widget_types( $name ) ) {
				continue;
			}
			$class = self::load( $name );
			if ( '' === $class ) {
				continue;
			}
			try {
				$manager->register( new $class() );
				$registered[] = $name;
			} catch ( \Throwable $error ) {
				continue;
			}
		}
		return $registered;
	}

	/**
	 * `elementor/editor/after_enqueue_scripts`: el script que registra el tipo en el editor. Depende de `nested-elements`
	 * (el script del núcleo que define `NestedElementBase`): sin él, WordPress no lo imprime.
	 */
	public static function editor_script(): void {
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) || ! is_file( self::path( self::EDITOR_SCRIPT ) ) || ! self::ready() ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::EDITOR_HANDLE, plugins_url( self::EDITOR_SCRIPT, DIGITALISIMO_ELEMENTS_FILE ), array( 'nested-elements' ), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		if ( self::load( 'digitalisimo-nested-slider' ) ) {
			wp_add_inline_script( self::EDITOR_HANDLE, 'window.digitalisimoNestedSlider=' . wp_json_encode( Slider_Widget::slide_strings() ) . ';', 'before' );
		}
	}

	/** Para las pruebas: olvida el arranque, la guarda y las clases cargadas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$ready       = null;
		self::$loaded      = array();
	}
}
