<?php
namespace Digitalisimo\Elements\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de los widgets de WooCommerce de DIGITALÍSIMO Elements (F4-Woo, D1 y V.15.2).
 *
 * `WIDGETS` tiene el mismo formato que `Widget_Registry::WIDGETS` (`nombre => array( 'file', 'class', 'css', 'js' )`,
 * sin paréntesis anidados), para que `tests/elements-front-budget.php` y `tests/elements-kit-inventory.mjs` lo lean con
 * la misma expresión. `file` es relativo a `modules/digitalisimo-woo/`; `css` y `js`, a la raíz del plugin. La hoja y el
 * script de cada entrada son los de su capacidad (`Assets::FEATURES`): dos widgets pueden compartirlos.
 *
 * Registro tolerante (V-M20): un widget sólo se registra si su archivo existe y define su clase; un archivo que falta
 * o que falla al cargarse se omite sin error. Con WooCommerce lo registra `widgets()`; sin él, `Inert` (sólo en el
 * editor). Todos van en la categoría `digitalisimo`, que crea `Widget_Registry::category()`.
 */
final class Registry {
	const WIDGETS = array(
		'digitalisimo-woo-wishlist-button' => array(
			'file'  => 'wishlist/class-button-widget.php',
			'class' => Wishlist\Button_Widget::class,
			'css'   => 'assets/css/wishlist.css',
			'js'    => 'assets/js/wishlist.js',
		),
		'digitalisimo-woo-wishlist' => array(
			'file'  => 'wishlist/class-list-widget.php',
			'class' => Wishlist\List_Widget::class,
			'css'   => 'assets/css/wishlist.css',
			'js'    => 'assets/js/wishlist.js',
		),
		'digitalisimo-woo-quick-view' => array(
			'file'  => 'quick-view/class-widget.php',
			'class' => Quick_View\Widget::class,
			'css'   => 'assets/css/quick-view.css',
			'js'    => 'assets/js/quick-view.js',
		),
		'digitalisimo-woo-swatches' => array(
			'file'  => 'swatches/class-loop-widget.php',
			'class' => Swatches\Loop_Widget::class,
			'css'   => 'assets/css/swatches.css',
			'js'    => 'assets/js/swatches.js',
		),
		'digitalisimo-woo-shipping-bar' => array(
			'file'  => 'cart/class-shipping-bar-widget.php',
			'class' => Cart\Shipping_Bar_Widget::class,
			'css'   => 'assets/css/cart-extras.css',
			'js'    => 'assets/js/cart-extras.js',
		),
		'digitalisimo-woo-price-filter' => array(
			'file'  => 'filters/class-price-widget.php',
			'class' => Filters\Price_Widget::class,
			'css'   => 'assets/css/filters.css',
			'js'    => 'assets/js/filters.js',
		),
		'digitalisimo-woo-attribute-filter' => array(
			'file'  => 'filters/class-attribute-widget.php',
			'class' => Filters\Attribute_Widget::class,
			'css'   => 'assets/css/filters.css',
			'js'    => 'assets/js/filters.js',
		),
	);

	/** Títulos del panel (los usa también el registro oculto de `Inert` cuando un widget no coopera). */
	const TITLES = array(
		'digitalisimo-woo-wishlist-button'  => 'Botón de lista de deseos',
		'digitalisimo-woo-wishlist'         => 'Lista de deseos',
		'digitalisimo-woo-quick-view'       => 'Vista rápida',
		'digitalisimo-woo-swatches'         => 'Muestras de variación',
		'digitalisimo-woo-shipping-bar'     => 'Barra de envío gratis',
		'digitalisimo-woo-price-filter'     => 'Filtro de precio',
		'digitalisimo-woo-attribute-filter' => 'Filtro de atributo',
	);

	/** Prioridad en `elementor/widgets/register`, la misma que `Widget_Registry::widgets()`. */
	const PRIORITY = 20;

	/** @var bool */
	private static $initialized = false;

	/** @var array<string,string> Clase cargada por widget ('' si no se pudo). */
	private static $loaded = array();

	/** Con WooCommerce: registra los widgets disponibles cuando el núcleo los pide. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ), self::PRIORITY );
	}

	/** Nombres del registro, existan o no sus archivos. */
	public static function names(): array {
		return array_keys( self::WIDGETS );
	}

	/** ¿Es un widget del registro y existe su archivo? */
	public static function available( string $name ): bool {
		return isset( self::WIDGETS[ $name ] ) && is_file( Module::dir() . self::WIDGETS[ $name ]['file'] );
	}

	/** Capacidad de un widget (`wishlist`, `quick-view`, `swatches`, `cart-extras`, `filters`), o ''. */
	public static function feature( string $name ): string {
		if ( ! isset( self::WIDGETS[ $name ]['css'] ) ) {
			return '';
		}
		$feature = basename( (string) self::WIDGETS[ $name ]['css'], '.css' );
		return isset( Assets::FEATURES[ $feature ] ) ? $feature : '';
	}

	/** Título del panel de un widget. */
	public static function title( string $name ): string {
		return isset( self::TITLES[ $name ] ) ? self::TITLES[ $name ] : $name;
	}

	/**
	 * Carga el archivo de un widget y devuelve su clase, o '' si falta el archivo, la clase o `Widget_Base`, o si cargarlo
	 * falla. Nunca un error fatal: un frente que aún no existe, o que falla, sólo deja su widget sin registrar.
	 */
	public static function load( string $name ): string {
		if ( isset( self::$loaded[ $name ] ) ) {
			return self::$loaded[ $name ];
		}
		self::$loaded[ $name ] = '';
		if ( ! self::available( $name ) || ! class_exists( '\Elementor\Widget_Base' ) ) {
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
	 * `elementor/widgets/register` (20), con WooCommerce: registra cada widget disponible que el núcleo aún no tenga.
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

	/** Para las pruebas: olvida las clases cargadas y permite volver a iniciar. */
	public static function reset(): void {
		self::$loaded      = array();
		self::$initialized = false;
	}
}
