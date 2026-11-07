<?php
namespace Digitalisimo\Elements\Carousel;

defined( 'ABSPATH' ) || exit;

/**
 * Carruseles propios (C1 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c1.md`): motor `digi-crs` y los cinco
 * widgets que sustituyen a los carruseles de PRO. Sin Swiper, sin jQuery y sin los paquetes de PRO.
 *
 * **Clases públicas** (namespace `Digitalisimo\Elements\Carousel`, autocarga acotada a ese prefijo):
 * - `Module` (este archivo): arranque, recursos y registro de los widgets.
 * - `Engine` (`class-engine.php`): marcado del motor (`open()`, `close()`, `slide_attributes()`, `slide_attribute_array()`,
 *   `scope()`), sus variables y modificadores (documentados en su cabecera) y `HANDLE`.
 * - `Base_Widget` (`class-base-widget.php`): base abstracta de multimedia, testimonios y reseñas (repite
 *   `modules/carousel/widgets/base.php` de PRO).
 * - `Nested_Controls` (`trait-nested-controls.php`): trait que repite `base/base-carousel-trait.php` de PRO más
 *   `carousel_args()`; lo usa el carrusel anidado y lo usará el Loop Carousel propio (C5).
 * - Widgets, uno por archivo en `widgets/` (namespace `Digitalisimo\Elements\Carousel\Widgets`):
 *   `digitalisimo-nested-carousel` → `Widgets\Nested_Carousel` (`widgets/class-nested-carousel.php`),
 *   `digitalisimo-media-carousel` → `Widgets\Media_Carousel` (`widgets/class-media-carousel.php`),
 *   `digitalisimo-testimonial-carousel` → `Widgets\Testimonial_Carousel` (`widgets/class-testimonial-carousel.php`),
 *   `digitalisimo-reviews` → `Widgets\Reviews` (`widgets/class-reviews.php`),
 *   `digitalisimo-slides` → `Widgets\Slides` (`widgets/class-slides.php`).
 *
 * **Recursos.** `Engine::HANDLE` (`digitalisimo-carousel`): `assets/css/carousel.css` (≤ 4,5 KB, excepción declarada en
 * `docs/pro/c1.md` §1.3) y `assets/js/carousel.js` (≤ 8 KB, `defer`, sin dependencias). Opcionales (`EXTRA`, sólo con los
 * ajustes que los usan, `extra_depends()`): `carousel-lazy.css|js` (indicador de la carga diferida) y
 * `carousel-3d.css|js` (coverflow y cubo del Carrusel multimedia). Cada widget tiene su hoja `assets/css/<nombre sin digitalisimo->.css` y, sólo si
 * existe, su script `assets/js/<nombre sin digitalisimo->.js`, registrados con el nombre del widget como handle (p. ej.
 * `digitalisimo-media-carousel`), y su script del editor `assets/js/<nombre sin digitalisimo->-editor.js` si existe (se
 * encola en el editor; el del carrusel anidado depende de `nested-elements`). Todo sólo se **registra** cuando el núcleo
 * registra los suyos; lo encola el núcleo por `get_style_depends()`/`get_script_depends()` de cada widget, que deben
 * devolver `Module::style_depends( $this->get_name() )` y `Module::script_depends( $this->get_name() )` (el motor y lo
 * propio que exista; `Base_Widget` ya lo hace). Así, una página sin carruseles no carga nada.
 *
 * **Registro** tolerante en `elementor/widgets/register` (20): un widget cuyo archivo falta, o que falla al cargarse, se
 * omite sin error; uno ya registrado no se registra otra vez. Los anidados (`NESTED`) sólo con el experimento
 * `nested-elements` y `Widget_Nested_Base`, como los anidados del núcleo.
 */
final class Module {
	const NAMESPACE_PREFIX = 'Digitalisimo\\Elements\\Carousel\\';

	/**
	 * Mismo formato que `Widget_Registry::WIDGETS` y `F7b\Nested\Module::WIDGETS` (`nombre => array( 'file', 'class',
	 * 'css', 'js' )`, sin paréntesis anidados), para que las pruebas de presupuesto lo lean con la misma expresión. `file`
	 * es relativo a este módulo; `css` y `js`, a la raíz del plugin.
	 */
	const WIDGETS = array(
		'digitalisimo-nested-carousel'      => array( 'file' => 'widgets/class-nested-carousel.php', 'class' => 'Digitalisimo\\Elements\\Carousel\\Widgets\\Nested_Carousel', 'css' => 'modules/digitalisimo-carousel/assets/css/nested-carousel.css', 'js' => 'modules/digitalisimo-carousel/assets/js/nested-carousel.js' ),
		'digitalisimo-media-carousel'       => array( 'file' => 'widgets/class-media-carousel.php', 'class' => 'Digitalisimo\\Elements\\Carousel\\Widgets\\Media_Carousel', 'css' => 'modules/digitalisimo-carousel/assets/css/media-carousel.css', 'js' => 'modules/digitalisimo-carousel/assets/js/media-carousel.js' ),
		'digitalisimo-testimonial-carousel' => array( 'file' => 'widgets/class-testimonial-carousel.php', 'class' => 'Digitalisimo\\Elements\\Carousel\\Widgets\\Testimonial_Carousel', 'css' => 'modules/digitalisimo-carousel/assets/css/testimonial-carousel.css', 'js' => 'modules/digitalisimo-carousel/assets/js/testimonial-carousel.js' ),
		'digitalisimo-reviews'              => array( 'file' => 'widgets/class-reviews.php', 'class' => 'Digitalisimo\\Elements\\Carousel\\Widgets\\Reviews', 'css' => 'modules/digitalisimo-carousel/assets/css/reviews.css', 'js' => 'modules/digitalisimo-carousel/assets/js/reviews.js' ),
		'digitalisimo-slides'               => array( 'file' => 'widgets/class-slides.php', 'class' => 'Digitalisimo\\Elements\\Carousel\\Widgets\\Slides', 'css' => 'modules/digitalisimo-carousel/assets/css/slides.css', 'js' => 'modules/digitalisimo-carousel/assets/js/slides.js' ),
	);

	/** Títulos del panel, para quien los liste sin cargar la clase. */
	const TITLES = array(
		'digitalisimo-nested-carousel'      => 'Carrusel anidado',
		'digitalisimo-media-carousel'       => 'Carrusel multimedia',
		'digitalisimo-testimonial-carousel' => 'Carrusel de testimonios',
		'digitalisimo-reviews'              => 'Reseñas',
		'digitalisimo-slides'               => 'Diapositivas',
	);

	/** Widgets anidados: necesitan el experimento `nested-elements` y `Widget_Nested_Base`. */
	const NESTED = array( 'digitalisimo-nested-carousel' );

	const NESTED_BASE = 'Elementor\\Modules\\NestedElements\\Base\\Widget_Nested_Base';

	/** Hoja y script del motor (relativos a la raíz del plugin). */
	const ENGINE_CSS = 'modules/digitalisimo-carousel/assets/css/carousel.css';
	const ENGINE_JS  = 'modules/digitalisimo-carousel/assets/js/carousel.js';

	/**
	 * Recursos opcionales del motor, que sólo piden los widgets cuyos ajustes los usan (y el editor): `LAZY`, el indicador
	 * de la «Carga diferida» (el aro de Swiper en PRO, retirado al cargar la imagen), y `FX`, los efectos 3D del Carrusel
	 * multimedia («Coverflow» y el efecto «Cubo»). Se registran con los del motor (`register_assets()`), también para
	 * los recursos de página del núcleo y la caché de elementos, que los encolan por su handle sin instanciar el widget.
	 */
	const LAZY = 'digitalisimo-carousel-lazy';
	const FX   = 'digitalisimo-carousel-3d';
	const EXTRA = array(
		self::LAZY => array( 'css' => 'modules/digitalisimo-carousel/assets/css/carousel-lazy.css', 'js' => 'modules/digitalisimo-carousel/assets/js/carousel-lazy.js' ),
		self::FX   => array( 'css' => 'modules/digitalisimo-carousel/assets/css/carousel-3d.css', 'js' => 'modules/digitalisimo-carousel/assets/js/carousel-3d.js' ),
	);

	/** Prioridad en `elementor/widgets/register`, la de `Widget_Registry`, `Woo\Registry` y F7b. */
	const PRIORITY = 20;

	/** @var bool */
	private static $initialized = false;

	/** @var array<string,string> Clase cargada por widget ('' si no se pudo). */
	private static $loaded = array();

	/** Arranque desde `pro-elements.php` (en `plugins_loaded`). Idempotente: sólo autocarga y ganchos. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ), self::PRIORITY );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_scripts' ) );
	}

	/**
	 * Autocarga: `Widgets\Foo_Bar` → `widgets/class-foo-bar.php`, `Nested_Controls` → `trait-nested-controls.php`, el
	 * resto `class-<nombre>.php`. Cualquier otro nombre se ignora sin tocar el disco.
	 */
	public static function autoload( $class ): void {
		$class = ltrim( (string) $class, '\\' );
		if ( 0 !== strpos( $class, self::NAMESPACE_PREFIX ) ) {
			return;
		}
		$name = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
		$dir  = '';
		if ( 0 === strpos( $name, 'Widgets\\' ) ) {
			$dir  = 'widgets/';
			$name = substr( $name, 8 );
		}
		if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $name ) ) {
			return;
		}
		$slug = str_replace( '_', '-', strtolower( $name ) );
		$file = __DIR__ . '/' . $dir . ( 'nested-controls' === $slug && '' === $dir ? 'trait-' : 'class-' ) . $slug . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}

	/** Ruta en disco de un archivo relativo a la raíz del plugin. */
	public static function path( string $relative ): string {
		return dirname( dirname( __DIR__ ) ) . '/' . ltrim( $relative, '/' );
	}

	/** Registra la hoja y el script del motor y los de cada widget cuyo archivo existe (sin encolarlos). Idempotente. */
	public static function register_assets(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		$assets  = array( Engine::HANDLE => array( 'css' => self::ENGINE_CSS, 'js' => self::ENGINE_JS ) ) + self::EXTRA + self::WIDGETS;
		foreach ( $assets as $handle => $asset ) {
			if ( ! wp_style_is( $handle, 'registered' ) && is_file( self::path( $asset['css'] ) ) ) {
				wp_register_style( $handle, plugins_url( $asset['css'], DIGITALISIMO_ELEMENTS_FILE ), array(), $version );
			}
			if ( ! wp_script_is( $handle, 'registered' ) && is_file( self::path( $asset['js'] ) ) ) {
				// Los opcionales actúan sobre el marcado que inicia el motor: van detrás de su script.
				$deps = isset( self::EXTRA[ $handle ] ) ? array( Engine::HANDLE ) : array();
				wp_register_script( $handle, plugins_url( $asset['js'], DIGITALISIMO_ELEMENTS_FILE ), $deps, $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
			}
		}
	}

	/**
	 * Los recursos opcionales (`EXTRA`) que necesitan unos ajustes: `LAZY` con «Carga diferida» y `FX` con «Coverflow» o
	 * el efecto «Cubo» (sólo el Carrusel multimedia los tiene). `$all`: el tipo del editor, que los pide todos.
	 *
	 * @return string[] Handles.
	 */
	public static function extra_depends( array $settings, bool $all = false ): array {
		$out = array();
		if ( $all || 'yes' === ( $settings['lazyload'] ?? '' ) ) {
			$out[] = self::LAZY;
		}
		if ( $all || 'coverflow' === ( $settings['skin'] ?? '' ) || 'cube' === ( $settings['effect'] ?? '' ) ) {
			$out[] = self::FX;
		}
		return $out;
	}

	/** Hojas de un widget del módulo: la del motor y la suya si existe (para `get_style_depends()`). */
	public static function style_depends( string $name ): array {
		$out = array( Engine::HANDLE );
		if ( isset( self::WIDGETS[ $name ] ) && is_file( self::path( self::WIDGETS[ $name ]['css'] ) ) ) {
			$out[] = $name;
		}
		return $out;
	}

	/** Scripts de un widget del módulo: el del motor y el suyo si existe (para `get_script_depends()`). */
	public static function script_depends( string $name ): array {
		$out = array( Engine::HANDLE );
		if ( isset( self::WIDGETS[ $name ] ) && is_file( self::path( self::WIDGETS[ $name ]['js'] ) ) ) {
			$out[] = $name;
		}
		return $out;
	}

	/** Experimento del núcleo activo (sin el gestor de experimentos, `false`). */
	public static function feature( string $feature ): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$experiments = isset( \Elementor\Plugin::$instance->experiments ) ? \Elementor\Plugin::$instance->experiments : null;
		return is_object( $experiments ) && method_exists( $experiments, 'is_feature_active' ) && (bool) $experiments->is_feature_active( $feature );
	}

	/** ¿Es un widget del módulo, existe su archivo y, si es anidado, puede registrarse? */
	public static function available( string $name ): bool {
		if ( ! isset( self::WIDGETS[ $name ] ) || ! is_file( __DIR__ . '/' . self::WIDGETS[ $name ]['file'] ) ) {
			return false;
		}
		return ! in_array( $name, self::NESTED, true ) || ( self::feature( 'nested-elements' ) && class_exists( '\\' . self::NESTED_BASE ) );
	}

	/** Carga el archivo de un widget y devuelve su clase, o '' si falta o falla. Nunca un error fatal. */
	public static function load( string $name ): string {
		if ( isset( self::$loaded[ $name ] ) ) {
			return self::$loaded[ $name ];
		}
		self::$loaded[ $name ] = '';
		if ( ! self::available( $name ) ) {
			return '';
		}
		$class = ltrim( (string) self::WIDGETS[ $name ]['class'], '\\' );
		try {
			if ( ! class_exists( $class, false ) ) {
				require_once __DIR__ . '/' . self::WIDGETS[ $name ]['file'];
			}
			if ( class_exists( $class, false ) && is_subclass_of( $class, '\\Elementor\\Widget_Base' ) ) {
				self::$loaded[ $name ] = $class;
			}
		} catch ( \Throwable $error ) {
			self::$loaded[ $name ] = '';
		}
		return self::$loaded[ $name ];
	}

	/**
	 * `elementor/widgets/register` (20): registra cada widget disponible que el núcleo aún no tenga.
	 *
	 * @param object $manager `Widgets_Manager` del núcleo.
	 * @return string[] Widgets registrados en esta llamada.
	 */
	public static function widgets( $manager ): array {
		$registered = array();
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'register' ) ) {
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
	 * `elementor/editor/after_enqueue_scripts`: el script del editor de cada widget disponible que lo tenga
	 * (`assets/js/<nombre sin digitalisimo->-editor.js`). El de un anidado depende de `nested-elements`, el script del
	 * núcleo que define `NestedElementBase`.
	 */
	public static function editor_scripts(): void {
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		foreach ( array_keys( self::WIDGETS ) as $name ) {
			$file = 'modules/digitalisimo-carousel/assets/js/' . substr( $name, 13 ) . '-editor.js';
			if ( ! is_file( self::path( $file ) ) || ! self::available( $name ) ) {
				continue;
			}
			$deps = in_array( $name, self::NESTED, true ) ? array( 'nested-elements' ) : array();
			wp_enqueue_script( $name . '-editor', plugins_url( $file, DIGITALISIMO_ELEMENTS_FILE ), $deps, $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	}

	/** Para las pruebas: olvida el arranque y las clases cargadas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$loaded      = array();
	}
}
