<?php
namespace Digitalisimo\Elements\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Servicios y plantillas propios (C9 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c9.md`): widgets que sustituyen a
 * los de PRO de la etapa, repitiendo sus controles con el mismo nombre. Sin jQuery, sin Swiper y sin los paquetes de PRO.
 * Mismo esqueleto que `modules/digitalisimo-carousel/class-module.php` (C1), sin motor común.
 *
 * - Widgets, uno por archivo en `widgets/` (namespace `Digitalisimo\Elements\Services\Widgets`); cada frente añade su
 *   línea en `WIDGETS` y `TITLES` (y en `NESTED` si es anidado).
 * - Recursos: cada widget declara su hoja (`css`) y su script (`js`, `defer`, sin dependencias) y, sólo si existe, su
 *   script del editor `assets/js/<nombre sin digitalisimo->-editor.js`. Varios widgets pueden compartir archivo (los
 *   cuatro de Facebook, `facebook.css`/`facebook.js`; los dos de pago, `payment-button.*`): el handle sale del archivo
 *   (`handle()`: `digitalisimo-<archivo sin extensión>`), así que una página con dos de ellos lo carga una vez. Sólo se
 *   registran si el archivo existe; los encola el núcleo por `get_style_depends()`/`get_script_depends()`, que
 *   devuelven `Module::style_depends( $this->get_name() )` y `Module::script_depends( $this->get_name() )`. Una página
 *   sin estos widgets no carga nada.
 * - Registro tolerante en `elementor/widgets/register` (20): un widget cuyo archivo falta o falla se omite sin error.
 */
final class Module {
	const NAMESPACE_PREFIX = 'Digitalisimo\\Elements\\Services\\';

	/**
	 * Mismo formato que `Widget_Registry::WIDGETS` y `F7b\Nested\Module::WIDGETS` (`nombre => array( 'file', 'class',
	 * 'css', 'js' )`, sin paréntesis anidados), para que las pruebas de presupuesto lo lean con la misma expresión. `file`
	 * es relativo a este módulo; `css` y `js`, a la raíz del plugin.
	 */
	const WIDGETS = array(
		'digitalisimo-facebook-button'   => array( 'file' => 'widgets/class-facebook-button.php', 'class' => 'Digitalisimo\\Elements\\Services\\Widgets\\Facebook_Button', 'css' => 'modules/digitalisimo-services/assets/css/facebook.css', 'js' => 'modules/digitalisimo-services/assets/js/facebook.js' ),
		'digitalisimo-facebook-comments' => array( 'file' => 'widgets/class-facebook-comments.php', 'class' => 'Digitalisimo\\Elements\\Services\\Widgets\\Facebook_Comments', 'css' => 'modules/digitalisimo-services/assets/css/facebook.css', 'js' => 'modules/digitalisimo-services/assets/js/facebook.js' ),
		'digitalisimo-facebook-embed'    => array( 'file' => 'widgets/class-facebook-embed.php', 'class' => 'Digitalisimo\\Elements\\Services\\Widgets\\Facebook_Embed', 'css' => 'modules/digitalisimo-services/assets/css/facebook.css', 'js' => 'modules/digitalisimo-services/assets/js/facebook.js' ),
		'digitalisimo-facebook-page'     => array( 'file' => 'widgets/class-facebook-page.php', 'class' => 'Digitalisimo\\Elements\\Services\\Widgets\\Facebook_Page', 'css' => 'modules/digitalisimo-services/assets/css/facebook.css', 'js' => 'modules/digitalisimo-services/assets/js/facebook.js' ),
		'digitalisimo-paypal-button'     => array( 'file' => 'widgets/class-paypal-button.php', 'class' => 'Digitalisimo\\Elements\\Services\\Widgets\\Paypal_Button', 'css' => 'modules/digitalisimo-services/assets/css/payment-button.css', 'js' => 'modules/digitalisimo-services/assets/js/payment-button.js' ),
		'digitalisimo-stripe-button'     => array( 'file' => 'widgets/class-stripe-button.php', 'class' => 'Digitalisimo\\Elements\\Services\\Widgets\\Stripe_Button', 'css' => 'modules/digitalisimo-services/assets/css/payment-button.css', 'js' => 'modules/digitalisimo-services/assets/js/payment-button.js' ),
		'digitalisimo-template'          => array( 'file' => 'widgets/class-template.php', 'class' => 'Digitalisimo\\Elements\\Services\\Widgets\\Template', 'css' => 'modules/digitalisimo-services/assets/css/template.css', 'js' => 'modules/digitalisimo-services/assets/js/template.js' ),
	);

	/** Títulos del panel, para quien los liste sin cargar la clase. */
	const TITLES = array(
		'digitalisimo-facebook-button'   => 'Botón de Facebook',
		'digitalisimo-facebook-comments' => 'Comentarios de Facebook',
		'digitalisimo-facebook-embed'    => 'Publicación de Facebook',
		'digitalisimo-facebook-page'     => 'Página de Facebook',
		'digitalisimo-paypal-button'     => 'Botón de PayPal',
		'digitalisimo-stripe-button'     => 'Botón de Stripe',
		'digitalisimo-template'          => 'Plantilla',
	);

	/** Widgets anidados: necesitan el experimento `nested-elements` y `Widget_Nested_Base`. */
	const NESTED = array();

	const NESTED_BASE = 'Elementor\\Modules\\NestedElements\\Base\\Widget_Nested_Base';

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
	 * Autocarga: `Widgets\Foo_Bar` → `widgets/class-foo-bar.php`, el resto `class-<nombre>.php`. Cualquier otro nombre se ignora sin tocar el disco.
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
		$file = __DIR__ . '/' . $dir . 'class-' . $slug . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}

	/** Ruta en disco de un archivo relativo a la raíz del plugin. */
	public static function path( string $relative ): string {
		return dirname( dirname( __DIR__ ) ) . '/' . ltrim( $relative, '/' );
	}

	/** Registra la hoja y el script de cada widget cuyo archivo existe (sin encolarlos). Idempotente. */
	public static function register_assets(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		foreach ( self::WIDGETS as $asset ) {
			$style = self::handle( $asset['css'] );
			if ( ! wp_style_is( $style, 'registered' ) && is_file( self::path( $asset['css'] ) ) ) {
				wp_register_style( $style, plugins_url( $asset['css'], DIGITALISIMO_ELEMENTS_FILE ), array(), $version );
			}
			$script = self::handle( $asset['js'] );
			if ( ! wp_script_is( $script, 'registered' ) && is_file( self::path( $asset['js'] ) ) ) {
				wp_register_script( $script, plugins_url( $asset['js'], DIGITALISIMO_ELEMENTS_FILE ), array(), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
			}
		}
	}

	/** Handle de un recurso del módulo: `digitalisimo-<archivo sin extensión>` (`facebook.css` → `digitalisimo-facebook`). */
	public static function handle( string $file ): string {
		return 'digitalisimo-' . preg_replace( '/\.(css|js)$/', '', basename( $file ) );
	}

	/** Hojas de un widget del módulo: la suya si existe (para `get_style_depends()`). */
	public static function style_depends( string $name ): array {
		$out = array();
		if ( isset( self::WIDGETS[ $name ] ) && is_file( self::path( self::WIDGETS[ $name ]['css'] ) ) ) {
			$out[] = self::handle( self::WIDGETS[ $name ]['css'] );
		}
		return $out;
	}

	/**
	 * Scripts de un widget del módulo: el suyo si existe (para `get_script_depends()`). Los de Facebook que Meta retiró
	 * (`Facebook::DISCONTINUED`) no tienen fachada ni piden el SDK, así que no llevan script.
	 */
	public static function script_depends( string $name ): array {
		$out = array();
		if ( isset( self::WIDGETS[ $name ] ) && ! in_array( $name, Facebook::DISCONTINUED, true ) && is_file( self::path( self::WIDGETS[ $name ]['js'] ) ) ) {
			$out[] = self::handle( self::WIDGETS[ $name ]['js'] );
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
			$file = 'modules/digitalisimo-services/assets/js/' . substr( $name, 13 ) . '-editor.js';
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
