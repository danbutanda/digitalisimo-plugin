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
		// C7, frente M (ficha de producto): sustituyen a los widgets de PRO de la ficha (docs/pro/c7.md).
		'digitalisimo-woo-product-title' => array( 'file' => 'single/class-product-title.php', 'class' => Single\Product_Title::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-title.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-title.js' ),
		'digitalisimo-woo-product-price' => array( 'file' => 'single/class-product-price.php', 'class' => Single\Product_Price::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-price.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-price.js' ),
		'digitalisimo-woo-product-rating' => array( 'file' => 'single/class-product-rating.php', 'class' => Single\Product_Rating::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-rating.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-rating.js' ),
		'digitalisimo-woo-product-stock' => array( 'file' => 'single/class-product-stock.php', 'class' => Single\Product_Stock::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-stock.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-stock.js' ),
		'digitalisimo-woo-product-meta' => array( 'file' => 'single/class-product-meta.php', 'class' => Single\Product_Meta::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-meta.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-meta.js' ),
		'digitalisimo-woo-product-short-description' => array( 'file' => 'single/class-product-short-description.php', 'class' => Single\Product_Short_Description::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-short-description.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-short-description.js' ),
		'digitalisimo-woo-product-content' => array( 'file' => 'single/class-product-content.php', 'class' => Single\Product_Content::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-content.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-content.js' ),
		'digitalisimo-woo-product-additional-information' => array( 'file' => 'single/class-product-additional-information.php', 'class' => Single\Product_Additional_Information::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-additional-information.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-additional-information.js' ),
		'digitalisimo-woo-product-data-tabs' => array( 'file' => 'single/class-product-data-tabs.php', 'class' => Single\Product_Data_Tabs::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-data-tabs.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-data-tabs.js' ),
		'digitalisimo-woo-product-images' => array( 'file' => 'single/class-product-images.php', 'class' => Single\Product_Images::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-images.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-images.js' ),
		'digitalisimo-woo-product-add-to-cart' => array( 'file' => 'single/class-product-add-to-cart.php', 'class' => Single\Product_Add_To_Cart::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-add-to-cart.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-add-to-cart.js' ),
		'digitalisimo-woo-product-related' => array( 'file' => 'single/class-product-related.php', 'class' => Single\Product_Related::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-related.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-related.js' ),
		'digitalisimo-woo-product-upsell' => array( 'file' => 'single/class-product-upsell.php', 'class' => Single\Product_Upsell::class, 'css' => 'modules/digitalisimo-woo/single/assets/product-upsell.css', 'js' => 'modules/digitalisimo-woo/single/assets/product-upsell.js' ),
		'digitalisimo-woo-single-elements' => array( 'file' => 'single/class-single-elements.php', 'class' => Single\Single_Elements::class, 'css' => 'modules/digitalisimo-woo/single/assets/single-elements.css', 'js' => 'modules/digitalisimo-woo/single/assets/single-elements.js' ),
		'digitalisimo-woo-add-to-cart' => array( 'file' => 'single/class-add-to-cart.php', 'class' => Single\Add_To_Cart::class, 'css' => 'modules/digitalisimo-woo/single/assets/add-to-cart.css', 'js' => 'modules/digitalisimo-woo/single/assets/add-to-cart.js' ),
		// C7, frentes N (listados y archivo) y O (carrito, pago y cuenta): sustituyen a los widgets de PRO de la tienda.
		'digitalisimo-woo-products' => array( 'file' => 'store/class-products.php', 'class' => Store\Products::class, 'css' => 'modules/digitalisimo-woo/store/assets/products.css', 'js' => 'modules/digitalisimo-woo/store/assets/products.js' ),
		'digitalisimo-woo-products-classic' => array( 'file' => 'store/class-products-classic.php', 'class' => Store\Products_Classic::class, 'css' => 'modules/digitalisimo-woo/store/assets/products-classic.css', 'js' => 'modules/digitalisimo-woo/store/assets/products-classic.js' ),
		'digitalisimo-woo-archive-products' => array( 'file' => 'store/class-archive-products.php', 'class' => Store\Archive_Products::class, 'css' => 'modules/digitalisimo-woo/store/assets/archive-products.css', 'js' => 'modules/digitalisimo-woo/store/assets/archive-products.js' ),
		'digitalisimo-woo-archive-products-classic' => array( 'file' => 'store/class-archive-products-classic.php', 'class' => Store\Archive_Products_Classic::class, 'css' => 'modules/digitalisimo-woo/store/assets/archive-products-classic.css', 'js' => 'modules/digitalisimo-woo/store/assets/archive-products-classic.js' ),
		'digitalisimo-woo-categories' => array( 'file' => 'store/class-categories.php', 'class' => Store\Categories::class, 'css' => 'modules/digitalisimo-woo/store/assets/categories.css', 'js' => 'modules/digitalisimo-woo/store/assets/categories.js' ),
		'digitalisimo-woo-archive-description' => array( 'file' => 'store/class-archive-description.php', 'class' => Store\Archive_Description::class, 'css' => 'modules/digitalisimo-woo/store/assets/archive-description.css', 'js' => 'modules/digitalisimo-woo/store/assets/archive-description.js' ),
		'digitalisimo-woo-category-image' => array( 'file' => 'store/class-category-image.php', 'class' => Store\Category_Image::class, 'css' => 'modules/digitalisimo-woo/store/assets/category-image.css', 'js' => 'modules/digitalisimo-woo/store/assets/category-image.js' ),
		'digitalisimo-woo-breadcrumb' => array( 'file' => 'store/class-breadcrumb.php', 'class' => Store\Breadcrumb::class, 'css' => 'modules/digitalisimo-woo/store/assets/breadcrumb.css', 'js' => 'modules/digitalisimo-woo/store/assets/breadcrumb.js' ),
		'digitalisimo-woo-notices' => array( 'file' => 'store/class-notices.php', 'class' => Store\Notices::class, 'css' => 'modules/digitalisimo-woo/store/assets/notices.css', 'js' => 'modules/digitalisimo-woo/store/assets/notices.js' ),
		'digitalisimo-woo-cart' => array( 'file' => 'store/class-cart.php', 'class' => Store\Cart::class, 'css' => 'modules/digitalisimo-woo/store/assets/cart.css', 'js' => 'modules/digitalisimo-woo/store/assets/cart.js' ),
		'digitalisimo-woo-checkout' => array( 'file' => 'store/class-checkout.php', 'class' => Store\Checkout::class, 'css' => 'modules/digitalisimo-woo/store/assets/checkout.css', 'js' => 'modules/digitalisimo-woo/store/assets/checkout.js' ),
		'digitalisimo-woo-my-account' => array( 'file' => 'store/class-my-account.php', 'class' => Store\My_Account::class, 'css' => 'modules/digitalisimo-woo/store/assets/my-account.css', 'js' => 'modules/digitalisimo-woo/store/assets/my-account.js' ),
		'digitalisimo-woo-purchase-summary' => array( 'file' => 'store/class-purchase-summary.php', 'class' => Store\Purchase_Summary::class, 'css' => 'modules/digitalisimo-woo/store/assets/purchase-summary.css', 'js' => 'modules/digitalisimo-woo/store/assets/purchase-summary.js' ),
		'digitalisimo-woo-menu-cart' => array( 'file' => 'store/class-menu-cart.php', 'class' => Store\Menu_Cart::class, 'css' => 'modules/digitalisimo-woo/store/assets/menu-cart.css', 'js' => 'modules/digitalisimo-woo/store/assets/menu-cart.js' ),
		'digitalisimo-woo-pages' => array( 'file' => 'store/class-pages.php', 'class' => Store\Pages::class, 'css' => 'modules/digitalisimo-woo/store/assets/pages.css', 'js' => 'modules/digitalisimo-woo/store/assets/pages.js' ),
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
		// C7, frente M.
		'digitalisimo-woo-product-title' => 'Título del producto',
		'digitalisimo-woo-product-price' => 'Precio del producto',
		'digitalisimo-woo-product-rating' => 'Valoración del producto',
		'digitalisimo-woo-product-stock' => 'Existencias del producto',
		'digitalisimo-woo-product-meta' => 'Metadatos del producto',
		'digitalisimo-woo-product-short-description' => 'Descripción corta del producto',
		'digitalisimo-woo-product-content' => 'Contenido del producto',
		'digitalisimo-woo-product-additional-information' => 'Información adicional del producto',
		'digitalisimo-woo-product-data-tabs' => 'Pestañas de datos del producto',
		'digitalisimo-woo-product-images' => 'Imágenes del producto',
		'digitalisimo-woo-product-add-to-cart' => 'Añadir al carrito',
		'digitalisimo-woo-product-related' => 'Productos relacionados',
		'digitalisimo-woo-product-upsell' => 'Ventas dirigidas',
		'digitalisimo-woo-single-elements' => 'Elementos del producto',
		'digitalisimo-woo-add-to-cart' => 'Añadir al carrito personalizado',
		// C7, frentes N y O.
		'digitalisimo-woo-products' => 'Productos',
		'digitalisimo-woo-products-classic' => 'Productos (clásico)',
		'digitalisimo-woo-archive-products' => 'Productos del archivo',
		'digitalisimo-woo-archive-products-classic' => 'Productos del archivo (clásico)',
		'digitalisimo-woo-categories' => 'Categorías de productos',
		'digitalisimo-woo-archive-description' => 'Descripción del archivo de la tienda',
		'digitalisimo-woo-category-image' => 'Imagen de la categoría',
		'digitalisimo-woo-breadcrumb' => 'Migas de la tienda',
		'digitalisimo-woo-notices' => 'Avisos de la tienda',
		'digitalisimo-woo-cart' => 'Carrito',
		'digitalisimo-woo-checkout' => 'Finalizar compra',
		'digitalisimo-woo-my-account' => 'Mi cuenta',
		'digitalisimo-woo-purchase-summary' => 'Resumen de la compra',
		'digitalisimo-woo-menu-cart' => 'Carrito del menú',
		'digitalisimo-woo-pages' => 'Páginas de la tienda',
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
		// C7: recursos propios de los widgets que no son de una capacidad de `Assets::FEATURES`.
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_assets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
	}

	/**
	 * Recursos de los widgets de C7 (programa «sin widgets de PRO», `docs/pro/c7.md`): una entrada de `WIDGETS` cuya hoja
	 * no es la de una capacidad (`feature()` vacío) registra su hoja y su script, si existen, con el nombre del widget
	 * como handle (`css` y `js` relativos a la raíz del plugin; el script en el pie con `defer` y sin dependencias). Sólo
	 * se registran; el núcleo los encola por `get_style_depends()`/`get_script_depends()` (`style_depends()` y
	 * `script_depends()`). Idempotente.
	 */
	public static function register_assets(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		foreach ( self::WIDGETS as $name => $entry ) {
			if ( '' !== self::feature( $name ) ) {
				continue;
			}
			if ( ! empty( $entry['css'] ) && ! wp_style_is( $name, 'registered' ) && is_file( Assets::path( $entry['css'] ) ) ) {
				wp_register_style( $name, Assets::url( $entry['css'] ), array(), $version );
			}
			if ( ! empty( $entry['js'] ) && ! wp_script_is( $name, 'registered' ) && is_file( Assets::path( $entry['js'] ) ) ) {
				wp_register_script( $name, Assets::url( $entry['js'] ), array(), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
			}
		}
	}

	/** Hojas de un widget de C7: la suya si existe (para `get_style_depends()`). */
	public static function style_depends( string $name ): array {
		return isset( self::WIDGETS[ $name ]['css'] ) && '' === self::feature( $name ) && is_file( Assets::path( (string) self::WIDGETS[ $name ]['css'] ) ) ? array( $name ) : array();
	}

	/** Scripts de un widget de C7: el suyo si existe (para `get_script_depends()`). */
	public static function script_depends( string $name ): array {
		return isset( self::WIDGETS[ $name ]['js'] ) && '' === self::feature( $name ) && is_file( Assets::path( (string) self::WIDGETS[ $name ]['js'] ) ) ? array( $name ) : array();
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
