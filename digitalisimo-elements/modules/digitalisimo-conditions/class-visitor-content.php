<?php
namespace Digitalisimo\Elements\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Contenido que cambia según el visitante: lo que las condiciones de visualización deciden por sesión,
 * rol, país, navegador, URL o carrito no puede guardarse una vez y servirse a todos.
 *
 * Dos cachés lo congelarían. La caché de elementos de Elementor guarda el HTML de cada elemento salvo
 * que se declare dinámico: los motores de condiciones ya lo declaran para el elemento condicionado,
 * pero un widget propio que incrusta una plantilla (acordeón, alternador, pestañas, panel lateral,
 * diapositivas de plantilla) guardaría la plantilla entera con el resultado del primer visitante. Y
 * la caché de página (plugins de caché, CDN) guarda la página completa: cuando la petición va a pintar
 * un elemento condicionado, se le pide que no la guarde con las señales que todos entienden.
 *
 * Las cabeceras HTTP sólo sirven si salen antes del cuerpo, así que la decisión principal se toma en
 * `template_redirect` leyendo el JSON guardado del documento de la URL y de las plantillas del Theme
 * Builder que se van a pintar. Al pintar sólo se completa (plantillas incrustadas, bucles, ventanas)
 * con las constantes y la acción de LiteSpeed, que siguen llegando a tiempo.
 */
final class Visitor_Content {
	/** Widgets propios que pintan plantillas guardadas dentro de su salida. */
	const TEMPLATE_WIDGETS = array( 'digitalisimo-accordion', 'digitalisimo-content-switcher', 'digitalisimo-fancy-tabs', 'digitalisimo-offcanvas' );

	/** Interruptores (valor «yes») de contenido condicionado: cada motor añade el suyo por filtro mientras está activo en el sitio. */
	const SWITCH_KEYS = array();

	/** Ubicaciones del Theme Builder cuyas plantillas se pintan en cualquier petición. */
	const THEME_LOCATIONS = array( 'header', 'footer', 'single', 'archive' );

	private static $flagged = false;

	public static function init() {
		add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'is_dynamic_content' ), 10, 2 );
		// Después de que el Theme Builder registre sus ubicaciones (prioridad 10) y antes de cualquier salida.
		add_action( 'template_redirect', array( __CLASS__, 'flag_before_output' ), 20 );
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ) );
	}

	/** Interruptores vigentes: los que cada motor activo declara por filtro. */
	public static function switches() {
		$keys = array_map( 'strval', (array) apply_filters( 'digitalisimo_elements_visitor_content_switches', self::SWITCH_KEYS ) );
		return array_values( array_unique( array_filter( $keys ) ) );
	}

	/** Un widget que incrusta plantillas, o un widget de diapositivas con plantillas, no se guarda en caché. */
	public static function is_dynamic_content( $is_dynamic, $raw_data ) {
		if ( $is_dynamic ) {
			return true;
		}
		$type = (string) ( $raw_data['widgetType'] ?? '' );
		if ( in_array( $type, self::TEMPLATE_WIDGETS, true ) ) {
			return true;
		}
		if ( 'slides' === $type || ( '' !== $type && ! empty( $raw_data['settings']['slides'] ) && is_array( $raw_data['settings']['slides'] ) ) ) {
			foreach ( (array) ( $raw_data['settings']['slides'] ?? array() ) as $slide ) {
				if ( is_array( $slide ) && ! empty( $slide['digitalisimo_template_id'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** ¿El elemento (por sus ajustes guardados) está condicionado por visitante? */
	public static function is_conditioned( array $settings ) {
		if ( ! empty( $settings['e_display_conditions'] ) ) {
			$conditions = $settings['e_display_conditions'];
			$json       = is_array( $conditions ) ? (string) ( $conditions[0] ?? '' ) : (string) $conditions;
			if ( '' !== $json && '[]' !== $json && '[[]]' !== $json ) {
				return true;
			}
		}
		// Elementos atómicos (editor v4): las condiciones van bajo la clave de su tipo de propiedad, con valor estructurado.
		if ( ! empty( $settings['display-conditions'] ) ) {
			$atomic = $settings['display-conditions'];
			$value  = is_array( $atomic ) && array_key_exists( 'value', $atomic ) ? $atomic['value'] : $atomic;
			if ( ! empty( $value ) ) {
				return true;
			}
		}
		foreach ( self::switches() as $key ) {
			if ( 'yes' === ( $settings[ $key ] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * ¿El JSON guardado de un documento (`_elementor_data`) contiene elementos condicionados? Se lee el texto
	 * sin decodificarlo: los documentos grandes se recorren en microsegundos y un falso positivo sólo cuesta
	 * la caché de esa página.
	 */
	public static function has_conditioned_markers( $json ) {
		$json = (string) $json;
		if ( '' === $json ) {
			return false;
		}
		// Nativo: cadena JSON (sola o en lista) que abre una lista de grupos con al menos una condición.
		if ( preg_match( '/"e_display_conditions"\s*:\s*\[?\s*"\[\s*\[?\s*\{/', $json ) ) {
			return true;
		}
		// Atómico (editor v4): objeto con un «value» que contiene al menos un grupo.
		if ( preg_match( '/"display-conditions"\s*:\s*\{[^{}]{0,200}"value"\s*:\s*\[\s*\{/', $json ) ) {
			return true;
		}
		foreach ( self::switches() as $key ) {
			if ( preg_match( '/"' . preg_quote( $key, '/' ) . '"\s*:\s*"yes"/', $json ) ) {
				return true;
			}
		}
		return false;
	}

	/** IDs del documento de la URL y de las plantillas del Theme Builder que se pintarán en esta petición. */
	public static function documents_to_scan() {
		$ids = array();
		if ( is_singular() ) {
			$ids[] = (int) get_queried_object_id();
		}
		if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
			try {
				$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
				foreach ( self::THEME_LOCATIONS as $location ) {
					foreach ( (array) $manager->get_documents_for_location( $location ) as $document ) {
						if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
							$ids[] = (int) $document->get_main_id();
						}
					}
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- una plantilla rota nunca rompe el frontal.
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/** Antes de la salida: si algún documento de la petición tiene elementos condicionados, la página no se guarda. */
	public static function flag_before_output() {
		if ( self::$flagged || is_admin() || self::in_editor() || is_feed() || is_robots() || is_embed() ) {
			return;
		}
		foreach ( self::documents_to_scan() as $post_id ) {
			if ( self::has_conditioned_markers( get_post_meta( $post_id, '_elementor_data', true ) ) ) {
				self::flag_page();
				return;
			}
		}
	}

	public static function before_render( $element ) {
		if ( self::$flagged || ! is_object( $element ) || ! method_exists( $element, 'get_settings' ) ) {
			return;
		}
		if ( self::is_conditioned( (array) $element->get_settings() ) ) {
			self::flag_page();
		}
	}

	private static function in_editor() {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		return ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );
	}

	/**
	 * Señales para que la página no se guarde en caché: la constante que leen WP Super Cache, W3 Total
	 * Cache, WP Rocket, LiteSpeed y otros, la de LiteSpeed propia, y las cabeceras HTTP si aún no se enviaron
	 * (CDN y proxies). Se avisa una vez por petición y sólo fuera del editor y de la administración.
	 */
	public static function flag_page() {
		if ( self::$flagged ) {
			return;
		}
		self::$flagged = true;
		if ( is_admin() || self::in_editor() ) {
			return;
		}
		if ( ! apply_filters( 'digitalisimo_elements_visitor_content_no_cache', true ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
			define( 'LSCACHE_NO_CACHE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'digitalisimo-elements: contenido condicionado por visitante' );
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/** Para las pruebas: vuelve al estado inicial de la petición. */
	public static function reset() {
		self::$flagged = false;
	}
}
