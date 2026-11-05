<?php
namespace Digitalisimo\Elements\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de los cuatro filtros ampliados de F7b (V.5), como el de F4-Woo (`Woo\Registry`).
 *
 * `WIDGETS` tiene el mismo formato que `Widget_Registry::WIDGETS` y `Woo\Registry::WIDGETS` (`nombre => array( 'file',
 * 'class', 'css', 'js' )`, sin paréntesis anidados), para que `tests/elements-front-budget.php` y
 * `tests/elements-kit-inventory.mjs` lo lean con la misma expresión. `file` es relativo a `modules/digitalisimo-filters/`;
 * `css` y `js`, a la raíz del plugin (la hoja propia; el coordinador de F4-Woo, compartido).
 *
 * Registro tolerante: un widget sólo se registra si su archivo existe y define su clase; uno que falta o que falla al
 * cargarse se omite sin error. Siempre (no depende de WooCommerce), en la categoría `digitalisimo`.
 */
final class Registry {
	const WIDGETS = array(
		'digitalisimo-loop-search' => array(
			'file'  => 'class-search-widget.php',
			'class' => Search_Widget::class,
			'css'   => 'modules/digitalisimo-filters/assets/f7b-filters.css',
			'js'    => 'assets/js/filters.js',
		),
		'digitalisimo-loop-meta-filter' => array(
			'file'  => 'class-meta-widget.php',
			'class' => Meta_Widget::class,
			'css'   => 'modules/digitalisimo-filters/assets/f7b-filters.css',
			'js'    => 'assets/js/filters.js',
		),
		'digitalisimo-loop-orderby' => array(
			'file'  => 'class-orderby-widget.php',
			'class' => Orderby_Widget::class,
			'css'   => 'modules/digitalisimo-filters/assets/f7b-filters.css',
			'js'    => 'assets/js/filters.js',
		),
		'digitalisimo-loop-per-page' => array(
			'file'  => 'class-per-page-widget.php',
			'class' => Per_Page_Widget::class,
			'css'   => 'modules/digitalisimo-filters/assets/f7b-filters.css',
			'js'    => 'assets/js/filters.js',
		),
	);

	/** Prioridad en `elementor/widgets/register`, la misma que `Widget_Registry::widgets()`. */
	const PRIORITY = 20;

	/** @var bool */
	private static $initialized = false;

	/** @var array<string,string> Clase cargada por widget ('' si no se pudo). */
	private static $loaded = array();

	/** Registra los widgets cuando el núcleo los pide. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ), self::PRIORITY );
	}

	/** Nombres del registro. */
	public static function names(): array {
		return array_keys( self::WIDGETS );
	}

	/** Carga el archivo de un widget y devuelve su clase, o '' si falta o falla. Nunca un error fatal. */
	public static function load( string $name ): string {
		if ( isset( self::$loaded[ $name ] ) ) {
			return self::$loaded[ $name ];
		}
		self::$loaded[ $name ] = '';
		if ( ! isset( self::WIDGETS[ $name ] ) || ! is_file( Module::dir() . self::WIDGETS[ $name ]['file'] ) || ! class_exists( '\Elementor\Widget_Base' ) || ! trait_exists( '\ElementorPro\Modules\Posts\Traits\Pagination_Trait' ) ) {
			return '';
		}
		$class = ltrim( (string) self::WIDGETS[ $name ]['class'], '\\' );
		try {
			if ( ! class_exists( $class, false ) ) {
				require_once Module::dir() . self::WIDGETS[ $name ]['file'];
			}
			if ( class_exists( $class, false ) && is_subclass_of( $class, '\Elementor\Widget_Base' ) ) {
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
		foreach ( self::names() as $name ) {
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

	/** Para las pruebas. */
	public static function reset(): void {
		self::$loaded      = array();
		self::$initialized = false;
	}
}
