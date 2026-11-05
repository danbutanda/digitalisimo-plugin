<?php
namespace Digitalisimo\Elements\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Valores por defecto de los ajustes del núcleo incluido (F-front, frente C).
 *
 * Nunca se escribe un ajuste ni un experimento del núcleo: sólo se da un valor por defecto. Para los ajustes,
 * con `default_option_{opción}`, que WordPress consulta únicamente cuando la fila no existe (también cuando
 * get_option() recibe su propio valor por defecto); un sitio que eligió un valor en los ajustes del constructor
 * conserva el suyo. Para los iconos en línea, con el valor por defecto del experimento, que el núcleo sólo usa
 * cuando el sitio no guardó una elección. Todo se lee del valor **aplicado** por la transición, nunca del
 * deseado, y sólo con el núcleo incluido: con el plugin Elementor original manda su propia configuración.
 *
 * Cada filtro `default_option_` existe sólo mientras su clave está aplicada, así que `has_filter()` dice la verdad
 * a quien lo consulte (DIGITALÍSIMO SEO muestra con él quién decide `font-display`).
 */
final class Core_Defaults {
	/** Experimento de iconos en línea (SVG) del núcleo. */
	const FEATURE = 'e_font_icon_svg';
	/** Opción que escribe la transición (Transition::APPLIED). */
	const APPLIED = 'digitalisimo_elements_front_applied';
	/** Clave aplicada => opción del núcleo y valor por defecto que se le da. */
	const DEFAULTS = array(
		'font_swap'         => array( 'elementor_font_display', 'swap' ),
		'element_cache_off' => array( 'elementor_element_cache_ttl', 'disable' ),
		'image_loading_off' => array( 'elementor_optimized_image_loading', '0' ),
	);
	/** Hojas de Font Awesome que el núcleo registra en modo de fuente (Icons_Manager::register_styles()). */
	const FA_SHARED  = 'elementor-icons-shared-0';
	const FA_STYLES  = array( 'fa-regular' => 'regular', 'fa-solid' => 'solid', 'fa-brands' => 'brands' );
	const FA_VERSION = '5.15.3';
	/** Respaldo si otro código ya ocupó el handle compartido con otra hoja. */
	const FA_OWN_SHARED = 'digitalisimo-elements-icons-fa-shared';

	private static $started = false;
	/** @var array<string,bool> Filtros `default_option_` añadidos, por clave. */
	private static $hooked = array();
	/** Estado del experimento de iconos sin el valor por defecto propio: active|inactive, o '' si no se conoce. */
	private static $native = '';
	/** Si el valor por defecto propio del experimento se aplicó en esta petición. */
	private static $forced = false;

	public static function init(): void {
		if ( self::$started || ! self::active() ) {
			return;
		}
		self::$started = true;
		self::sync();
		// La transición escribe el aplicado: los filtros se ponen o se quitan en la misma petición.
		add_action( 'add_option_' . self::APPLIED, array( __CLASS__, 'sync' ), 10, 0 );
		add_action( 'update_option_' . self::APPLIED, array( __CLASS__, 'sync' ), 10, 0 );
		add_action( 'delete_option_' . self::APPLIED, array( __CLASS__, 'sync' ), 10, 0 );
		// En una red, cada sitio tiene su aplicado: al cambiar de sitio se vuelve a decidir qué filtros existen.
		add_action( 'switch_blog', array( __CLASS__, 'sync' ), 10, 0 );
		// Antes de `init` 0, donde el núcleo crea los experimentos y lee sus ajustes.
		add_action( 'elementor/experiments/default-features-registered', array( __CLASS__, 'experiments' ) );
		// Después del registro del núcleo (prioridad 10 de la misma acción).
		add_action( 'elementor/frontend/after_register_styles', array( __CLASS__, 'register_icon_compat' ), 20 );
		if ( ! class_exists( Icon_Scan::class ) && is_readable( __DIR__ . '/class-icon-scan.php' ) ) {
			require_once __DIR__ . '/class-icon-scan.php';
		}
		if ( class_exists( Icon_Scan::class ) ) {
			Icon_Scan::init();
		}
	}

	/** Los valores por defecto sólo existen con el núcleo incluido (D3). */
	public static function active(): bool {
		return class_exists( '\Digitalisimo_Elements_Core', false ) && \Digitalisimo_Elements_Core::bundled();
	}

	/** Pone o quita cada filtro `default_option_` según el valor aplicado. */
	public static function sync(): void {
		foreach ( self::DEFAULTS as $key => $default ) {
			$hook = 'default_option_' . $default[0];
			$want = self::applied( $key );
			if ( $want && empty( self::$hooked[ $key ] ) ) {
				add_filter( $hook, array( __CLASS__, 'default_value' ), 10, 3 );
				self::$hooked[ $key ] = true;
			} elseif ( ! $want && ! empty( self::$hooked[ $key ] ) ) {
				remove_filter( $hook, array( __CLASS__, 'default_value' ), 10 );
				self::$hooked[ $key ] = false;
			}
		}
	}

	/**
	 * `default_option_{opción}`: el valor propio sólo si su clave sigue aplicada. El aplicado se vuelve a leer en
	 * cada llamada; un `pre_option_` (el de DIGITALÍSIMO SEO, por ejemplo) se resuelve antes y sigue ganando.
	 *
	 * @param mixed  $default Valor por defecto recibido.
	 * @param string $option  Opción consultada.
	 * @return mixed
	 */
	public static function default_value( $default, $option = '', $passed_default = false ) {
		foreach ( self::DEFAULTS as $key => $value ) {
			if ( $value[0] === $option ) {
				return self::applied( $key ) ? $value[1] : $default;
			}
		}
		return $default;
	}

	/**
	 * `elementor/experiments/default-features-registered`: con `svg_icons` aplicado y sin elección guardada del
	 * sitio (estado vacío o `default`), el valor por defecto del experimento pasa a activo. Una elección guardada
	 * (activa o inactiva) siempre manda.
	 *
	 * @param object $manager Gestor de experimentos del núcleo.
	 */
	public static function experiments( $manager ): void {
		self::$forced = false;
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'get_features' ) ) {
			return;
		}
		$feature = $manager->get_features( self::FEATURE );
		if ( ! is_array( $feature ) ) {
			return;
		}
		$state    = isset( $feature['state'] ) ? (string) $feature['state'] : '';
		$unsaved  = '' === $state || 'default' === $state;
		$default  = isset( $feature['default'] ) ? (string) $feature['default'] : 'inactive';
		self::$native = $unsaved ? $default : $state;
		if ( ! $unsaved || 'active' === $default || ! self::applied( 'svg_icons' ) || ! method_exists( $manager, 'set_feature_default_state' ) ) {
			return;
		}
		$manager->set_feature_default_state( self::FEATURE, 'active' );
		self::$forced = true;
	}

	/** Estado del experimento de iconos sin el valor por defecto propio; '' si el núcleo aún no lo registró. */
	public static function native_icons(): string {
		return self::$native;
	}

	/** Si los iconos en línea están activos en esta petición por el valor por defecto de Elements. */
	public static function icons_forced(): bool {
		return self::$forced;
	}

	/**
	 * Con los iconos en línea puestos por Elements, el núcleo deja de registrar las hojas de Font Awesome. Se
	 * registran sin encolar, con las mismas URL que usa el núcleo en modo de fuente, para que una hoja ajena que
	 * dependa de ellas siga imprimiéndose con su fuente. Nunca pisa un handle ya registrado.
	 */
	public static function register_icon_compat(): void {
		if ( ! self::$forced || ! self::applied( 'svg_icons' ) || ! self::svg_active() || ! defined( 'ELEMENTOR_ASSETS_URL' ) || ! function_exists( 'wp_register_style' ) ) {
			return;
		}
		$base   = ELEMENTOR_ASSETS_URL . 'lib/font-awesome/css/';
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ( defined( 'ELEMENTOR_TESTS' ) && ELEMENTOR_TESTS ) ? '' : '.min';
		$shared = self::FA_SHARED;
		$src    = $base . 'fontawesome' . $suffix . '.css';
		if ( ! wp_style_is( $shared, 'registered' ) ) {
			wp_register_style( $shared, $src, array(), self::FA_VERSION );
		} elseif ( ! self::registered_src_is( $shared, $src ) ) {
			// Otra biblioteca de iconos ocupó el handle compartido: Font Awesome usa uno propio.
			$shared = self::FA_OWN_SHARED;
			if ( ! wp_style_is( $shared, 'registered' ) ) {
				wp_register_style( $shared, $src, array(), self::FA_VERSION );
			}
		}
		foreach ( self::FA_STYLES as $name => $file ) {
			$handle = 'elementor-icons-' . $name;
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style( $handle, $base . $file . $suffix . '.css', array( $shared ), self::FA_VERSION );
			}
		}
	}

	/** Si la clave está en el valor aplicado por la transición. Sin la clase de la transición, nada lo está. */
	private static function applied( string $key ): bool {
		if ( ! class_exists( __NAMESPACE__ . '\Transition' ) || ! method_exists( __NAMESPACE__ . '\Transition', 'applied' ) ) {
			return false;
		}
		$applied = Transition::applied();
		return is_array( $applied ) && ! empty( $applied[ $key ] );
	}

	private static function svg_active(): bool {
		if ( ! class_exists( '\Elementor\Plugin', false ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$experiments = isset( \Elementor\Plugin::$instance->experiments ) ? \Elementor\Plugin::$instance->experiments : null;
		return is_object( $experiments ) && method_exists( $experiments, 'is_feature_active' ) && $experiments->is_feature_active( self::FEATURE );
	}

	private static function registered_src_is( string $handle, string $src ): bool {
		$styles = function_exists( 'wp_styles' ) ? wp_styles() : null;
		if ( ! is_object( $styles ) || ! isset( $styles->registered[ $handle ] ) ) {
			return true;
		}
		return (string) $styles->registered[ $handle ]->src === $src;
	}
}
