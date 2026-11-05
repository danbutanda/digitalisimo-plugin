<?php
namespace Digitalisimo\Elements\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Recursos de los widgets de WooCommerce (F4-Woo, §4.2 y V.12.2).
 *
 * - Una hoja y un script por capacidad (`FEATURES`), con el handle `digitalisimo-woo-<capacidad>`. Los scripts van en
 *   el pie con `defer` y sin jQuery; las únicas dependencias ajenas son las de `SCRIPT_DEPS` (`wc-cart-fragments`
 *   para la barra y el cupón; `elementor-frontend` para el coordinador de filtros). `wc-add-to-cart-variation` sólo lo
 *   carga la vista rápida al abrir un producto variable (V.5.3), nunca como dependencia.
 * - Sólo se registran los archivos que existen (registro tolerante, V-M20): un frente que aún no entregó su hoja no
 *   deja un handle que daría un 404.
 * - Los widgets declaran sus handles con `get_style_depends()`/`get_script_depends()`; las partes de Productos y los
 *   rasgos, en `assets` de su control (V.12.2). Nada se encola desde el render salvo lo que llega después del HTML:
 * - **Plantillas de bucle** (§4.2): al pintar un Loop Grid o un Loop Carousel se buscan los widgets F4-Woo en el
 *   `_elementor_data` de su plantilla y de sus plantillas alternas (cadena cruda, sin decodificar) y se encolan sus
 *   recursos aunque el bucle salga vacío, porque un bucle que se llena al filtrar ya debe tenerlos. Coste: un
 *   `strpos` por plantilla con la meta en caché; una consulta por plantilla si el bucle salió vacío (V.16.5).
 */
final class Assets {
	const PREFIX = 'digitalisimo-woo-';

	/** Hoja y script de cada capacidad, relativos a la raíz del plugin. */
	const FEATURES = array(
		'wishlist'    => array( 'css' => 'assets/css/wishlist.css', 'js' => 'assets/js/wishlist.js' ),
		'quick-view'  => array( 'css' => 'assets/css/quick-view.css', 'js' => 'assets/js/quick-view.js' ),
		'swatches'    => array( 'css' => 'assets/css/swatches.css', 'js' => 'assets/js/swatches.js' ),
		'cart-extras' => array( 'css' => 'assets/css/cart-extras.css', 'js' => 'assets/js/cart-extras.js' ),
		'filters'     => array( 'css' => 'assets/css/filters.css', 'js' => 'assets/js/filters.js' ),
	);

	/** Dependencias de los scripts que las tienen. Sólo se admiten las de `FOREIGN`. */
	const SCRIPT_DEPS = array(
		'cart-extras' => array( 'wc-cart-fragments' ),
		'filters'     => array( 'elementor-frontend' ),
	);

	/** Handles ajenos admitidos en un recurso F4-Woo (§4.2): la revisión estática no acepta otros. */
	const FOREIGN = array( 'wc-cart-fragments', 'wc-add-to-cart-variation', 'elementor-frontend' );

	/** Widgets de bucle cuyas plantillas se revisan. */
	const LOOP_WIDGETS = array( 'loop-grid', 'loop-carousel' );

	/** Patrón de un widget F4-Woo en el JSON crudo de un documento. */
	const PATTERN = '/"widgetType":"(digitalisimo-woo-[a-z0-9-]+)"/';

	/** @var bool */
	private static $initialized = false;

	/** @var array<int,string[]> Capacidades halladas por plantilla, por petición. */
	private static $templates = array();

	/** Con WooCommerce: registra los recursos donde los registra el núcleo y revisa las plantillas de bucle. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'loop_before_render' ) );
	}

	/** `digitalisimo-woo-<capacidad>`. */
	public static function handle( string $feature ): string {
		return self::PREFIX . $feature;
	}

	/** URL de un archivo del plugin. */
	public static function url( string $path ): string {
		return plugins_url( $path, DIGITALISIMO_ELEMENTS_FILE );
	}

	/** Ruta en disco de un archivo del plugin. */
	public static function path( string $path ): string {
		return dirname( DIGITALISIMO_ELEMENTS_FILE ) . '/' . ltrim( $path, '/' );
	}

	/** Registra la hoja y el script de cada capacidad cuyo archivo existe. Idempotente. */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		foreach ( self::FEATURES as $feature => $files ) {
			$handle = self::handle( $feature );
			if ( ! wp_style_is( $handle, 'registered' ) && is_file( self::path( $files['css'] ) ) ) {
				wp_register_style( $handle, self::url( $files['css'] ), array(), $version );
			}
			if ( ! wp_script_is( $handle, 'registered' ) && is_file( self::path( $files['js'] ) ) ) {
				$deps = isset( self::SCRIPT_DEPS[ $feature ] ) ? self::SCRIPT_DEPS[ $feature ] : array();
				wp_register_script( $handle, self::url( $files['js'] ), $deps, $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
			}
		}
	}

	/** ¿Están registrados la hoja o el script de la capacidad? */
	public static function registered( string $feature ): bool {
		$handle = self::handle( $feature );
		return function_exists( 'wp_style_is' ) && ( wp_style_is( $handle, 'registered' ) || wp_script_is( $handle, 'registered' ) );
	}

	/**
	 * Encola la hoja y el script ya registrados de una capacidad. Para lo que llega después del HTML (bucles, vista
	 * rápida); un widget declara sus recursos con `get_style_depends()`/`get_script_depends()`.
	 */
	public static function enqueue( string $feature ): void {
		if ( ! isset( self::FEATURES[ $feature ] ) || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		self::register();
		$handle = self::handle( $feature );
		if ( wp_style_is( $handle, 'registered' ) ) {
			wp_enqueue_style( $handle );
		}
		if ( wp_script_is( $handle, 'registered' ) ) {
			wp_enqueue_script( $handle );
		}
	}

	/**
	 * `elementor/frontend/widget/before_render`: en un Loop Grid o un Loop Carousel, encola los recursos de los widgets
	 * F4-Woo de su plantilla y de sus plantillas alternas.
	 *
	 * @param object $element Elemento que se va a pintar.
	 * @return string[] Capacidades encoladas.
	 */
	public static function loop_before_render( $element ): array {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || ! in_array( $element->get_name(), self::LOOP_WIDGETS, true ) || ! method_exists( $element, 'get_settings_for_display' ) ) {
			return array();
		}
		$settings = (array) $element->get_settings_for_display();
		$features = array();
		foreach ( self::loop_templates( $settings ) as $template_id ) {
			foreach ( self::template_features( $template_id ) as $feature ) {
				$features[ $feature ] = true;
			}
		}
		foreach ( array_keys( $features ) as $feature ) {
			self::enqueue( $feature );
		}
		return array_keys( $features );
	}

	/**
	 * Plantilla principal y alternas de un bucle, sin repetir.
	 *
	 * @param array $settings Ajustes del Loop Grid o del Loop Carousel.
	 * @return int[]
	 */
	public static function loop_templates( array $settings ): array {
		$ids = array();
		if ( ! empty( $settings['template_id'] ) ) {
			$ids[] = (int) $settings['template_id'];
		}
		if ( 'yes' === ( isset( $settings['alternate_template'] ) ? $settings['alternate_template'] : '' ) && ! empty( $settings['alternate_templates'] ) && is_array( $settings['alternate_templates'] ) ) {
			foreach ( $settings['alternate_templates'] as $row ) {
				if ( is_array( $row ) && ! empty( $row['template_id'] ) ) {
					$ids[] = (int) $row['template_id'];
				}
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Capacidades de los widgets F4-Woo de una plantilla, leídas de su `_elementor_data` crudo (memorizado por petición).
	 *
	 * @return string[]
	 */
	public static function template_features( int $template_id ): array {
		if ( $template_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return array();
		}
		if ( ! isset( self::$templates[ $template_id ] ) ) {
			$raw = get_post_meta( $template_id, '_elementor_data', true );
			if ( is_array( $raw ) ) {
				$raw = function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $raw ) : (string) json_encode( $raw );
			}
			self::$templates[ $template_id ] = self::features_in( (string) $raw );
		}
		return self::$templates[ $template_id ];
	}

	/**
	 * Capacidades de los widgets F4-Woo que nombra un JSON crudo de elementos.
	 *
	 * @return string[]
	 */
	public static function features_in( string $raw ): array {
		if ( '' === $raw || false === strpos( $raw, '"digitalisimo-woo-' ) || ! preg_match_all( self::PATTERN, $raw, $found ) ) {
			return array();
		}
		$features = array();
		foreach ( array_unique( $found[1] ) as $name ) {
			$feature = Registry::feature( $name );
			if ( '' !== $feature ) {
				$features[ $feature ] = true;
			}
		}
		return array_keys( $features );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$templates   = array();
		self::$initialized = false;
	}
}
