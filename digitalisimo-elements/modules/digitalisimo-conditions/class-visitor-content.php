<?php
namespace Digitalisimo\Elements\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Contenido que cambia según el visitante: lo que las condiciones de visualización deciden por país,
 * navegador, URL, carrito, hora o cookies no puede guardarse una vez y servirse a todos los visitantes.
 *
 * Dos cachés lo congelarían. La caché de elementos de Elementor guarda el HTML de cada elemento salvo
 * que se declare dinámico: los motores de condiciones ya lo declaran para el elemento condicionado,
 * pero un widget propio que incrusta una plantilla (acordeón, alternador, pestañas, panel lateral,
 * diapositivas de plantilla) guardaría la plantilla entera con el resultado del primer visitante. Y
 * la caché de página (plugins de caché, CDN) guarda la página completa: cuando la petición va a pintar
 * un elemento que varía entre visitantes, se le pide que no la guarde con las señales que todos entienden.
 *
 * No toda condición varía entre visitantes. Las cachés de página nunca sirven a usuarios con sesión, así
 * que una condición de sesión, rol o usuario produce la misma salida para todos los visitantes anónimos,
 * y una condición sobre el contenido (tipo, página, autor, categoría) produce la misma salida para una
 * URL. Esas páginas siguen en caché; sólo salen de ella las que dependen del visitante o del momento.
 *
 * Las cabeceras HTTP sólo sirven si salen antes del cuerpo, así que la decisión principal se toma en
 * `template_redirect` leyendo el JSON guardado del documento de la URL y de las plantillas del Theme
 * Builder que se van a pintar. Al pintar sólo se completa (plantillas incrustadas, bucles) con las
 * constantes y la acción de LiteSpeed, que siguen llegando a tiempo.
 */
final class Visitor_Content {
	/** Widgets propios que pintan plantillas guardadas dentro de su salida. */
	const TEMPLATE_WIDGETS = array( 'digitalisimo-accordion', 'digitalisimo-content-switcher', 'digitalisimo-fancy-tabs', 'digitalisimo-offcanvas' );

	/** Interruptores (valor «yes») de un motor que no sabe enumerar sus condiciones: cuentan como condición desconocida. */
	const SWITCH_KEYS = array();

	/** Textos que delatan condiciones en el JSON guardado; cada motor añade los suyos por filtro. */
	const MARKERS = array( 'e_display_conditions', 'display-conditions' );

	/**
	 * Condiciones cuya salida es la misma para todos los visitantes anónimos de una URL: sesión, rol y usuario
	 * (las cachés de página no sirven a usuarios con sesión) y propiedades del contenido. Nativas de Elementor,
	 * registradas por este módulo (`digitalisimo_*`) y claves de reglas propias y heredadas. Lo que no está
	 * aquí (país, navegador, idioma, URL de origen, parámetros, carrito, compras, visitas, fecha y hora,
	 * shortcodes, etiquetas dinámicas o cualquier condición desconocida) varía y saca la página de la caché.
	 */
	const CACHEABLE_CONDITIONS = array(
		'login_status', 'user_role', 'user', 'user_registration_date',
		'page', 'post', 'archive', 'archive_of_authors', 'archive_of_categories', 'archive_of_tags', 'category', 'post_tag', 'in_categories', 'in_tags',
		'page_author', 'post_author', 'page_parent', 'page_title', 'post_title', 'featured_image', 'number_of_comments', 'date_of_publish', 'date_of_modification',
		'digitalisimo_user', 'digitalisimo_specific_content', 'digitalisimo_post_type', 'digitalisimo_special_page', 'digitalisimo_url_path',
		'digitalisimo_product_category', 'digitalisimo_product_category_archive', 'digitalisimo_product_price', 'digitalisimo_product_status', 'digitalisimo_product_stock', 'digitalisimo_product_type',
		// F7a: campo del contenido o del término y autor (de la URL), campo del usuario (sólo con sesión) y búsqueda (de la URL).
		// Cookie, visitas y existencias de otro producto varían y no están aquí.
		'digitalisimo_field', 'digitalisimo_user_field', 'digitalisimo_author', 'digitalisimo_search',
		'authentication', 'role', 'post_type', 'static_page', 'acf_boolean', 'acf_choice', 'acf_text', 'current_category_page',
		'single_product_price', 'single_product_stock', 'single_product_category', 'single_product_downloadable', 'single_product_virtual', 'single_product_featured', 'single_product_backorder', 'single_product_onsale', 'single_product_sold_individually', 'single_product_type',
	);

	/** Reglas que escriben cookies al pintar: la página se bufferiza para que lleguen a tiempo (el constructor de origen bufferizaba todas). */
	const COOKIE_CONDITIONS = array( 'visit_count', 'session_count' );

	/** Ubicaciones del Theme Builder que se imprimen con la página (las ventanas van en el pie). */
	const THEME_LOCATIONS = array( 'header', 'footer', 'single', 'archive', 'popup' );

	private static $flagged = false;

	public static function init() {
		add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'is_dynamic_content' ), 10, 2 );
		// Después de que el Theme Builder registre sus ubicaciones (prioridad 10) y antes de cualquier salida.
		add_action( 'template_redirect', array( __CLASS__, 'flag_before_output' ), 20 );
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ) );
	}

	/** Interruptores vigentes de motores que no enumeran sus condiciones (compatibilidad). */
	public static function switches() {
		$keys = array_map( 'strval', (array) apply_filters( 'digitalisimo_elements_visitor_content_switches', self::SWITCH_KEYS ) );
		return array_values( array_unique( array_filter( $keys ) ) );
	}

	/** Textos a buscar en el JSON guardado antes de decodificarlo. */
	public static function markers() {
		$markers = array_merge( self::MARKERS, self::switches(), array_map( 'strval', (array) apply_filters( 'digitalisimo_elements_visitor_content_markers', array() ) ) );
		return array_values( array_unique( array_filter( $markers ) ) );
	}

	/** Condiciones cuya salida no cambia entre visitantes anónimos de una URL; un sitio puede ampliarla. */
	public static function cacheable_conditions() {
		return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'digitalisimo_elements_visitor_content_cacheable_conditions', self::CACHEABLE_CONDITIONS ) ) ) );
	}

	/**
	 * Un widget que incrusta plantillas, o un widget de diapositivas con plantillas, no se guarda en caché. Tampoco un
	 * elemento atómico (v4) con condiciones en su prop `display-conditions`: PRO sólo mira `e_display_conditions`, así
	 * que sin esto la caché de elementos serviría a todos lo que vio el primer visitante (F7a, V.4.1).
	 */
	public static function is_dynamic_content( $is_dynamic, $raw_data ) {
		if ( $is_dynamic ) {
			return true;
		}
		if ( self::has_atomic_conditions( isset( $raw_data['settings'] ) && is_array( $raw_data['settings'] ) ? $raw_data['settings'] : array() ) ) {
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

	/** ¿Tiene el prop v4 `display-conditions` algún grupo? Un prop sin valor o con la lista vacía no condiciona nada. */
	public static function has_atomic_conditions( array $settings ) {
		$prop = $settings['display-conditions'] ?? null;
		if ( is_array( $prop ) && array_key_exists( 'value', $prop ) ) {
			return is_array( $prop['value'] ) && ! empty( $prop['value'] );
		}
		return ! empty( $prop );
	}

	/**
	 * Identificadores del prop v4 tal como lo guardan el control atómico y el editor: cada condición es una cadena JSON
	 * (`{ $$type: string, value: "{\"condition\":…}" }`) dentro de un `condition-group`. Sin esto, una condición v4
	 * variable no sacaría la página de la caché de página (F7a).
	 */
	public static function atomic_condition_ids( array $prop ) {
		$ids = array();
		foreach ( isset( $prop['value'] ) && is_array( $prop['value'] ) ? $prop['value'] : array() as $group ) {
			foreach ( is_array( $group ) && isset( $group['value'] ) && is_array( $group['value'] ) ? $group['value'] : array() as $item ) {
				$condition = is_array( $item ) && isset( $item['value'] ) && is_string( $item['value'] ) ? json_decode( $item['value'], true ) : null;
				if ( is_array( $condition ) && isset( $condition['condition'] ) && is_string( $condition['condition'] ) ) {
					$ids[] = $condition['condition'];
				}
			}
		}
		return $ids;
	}

	/** Identificadores bajo claves «condition» (cadena, o {value: cadena} en los elementos atómicos), a cualquier profundidad. */
	public static function collect_condition_keys( $value ) {
		$ids = array();
		if ( ! is_array( $value ) ) {
			return $ids;
		}
		foreach ( $value as $key => $item ) {
			if ( 'condition' === $key ) {
				if ( is_string( $item ) ) {
					$ids[] = $item;
					continue;
				}
				if ( is_array( $item ) && isset( $item['value'] ) && is_string( $item['value'] ) ) {
					$ids[] = $item['value'];
					continue;
				}
			}
			if ( is_array( $item ) ) {
				$ids = array_merge( $ids, self::collect_condition_keys( $item ) );
			}
		}
		return $ids;
	}

	/**
	 * Identificadores de las condiciones de un elemento (por sus ajustes guardados): nativas, atómicas (editor v4),
	 * las que cada motor declara por el filtro `digitalisimo_elements_visitor_content_conditions`, y «*» por cada
	 * interruptor de compatibilidad encendido (condición desconocida).
	 */
	public static function condition_ids( array $settings ) {
		$ids = array();
		if ( ! empty( $settings['e_display_conditions'] ) ) {
			$raw = $settings['e_display_conditions'];
			$raw = is_array( $raw ) ? (string) ( $raw[0] ?? '' ) : (string) $raw;
			$ids = array_merge( $ids, self::collect_condition_keys( json_decode( $raw, true ) ) );
		}
		if ( ! empty( $settings['display-conditions'] ) ) {
			$ids = array_merge( $ids, self::collect_condition_keys( (array) $settings['display-conditions'] ), self::atomic_condition_ids( (array) $settings['display-conditions'] ) );
		}
		$ids = (array) apply_filters( 'digitalisimo_elements_visitor_content_conditions', $ids, $settings );
		foreach ( self::switches() as $key ) {
			if ( 'yes' === ( $settings[ $key ] ?? '' ) ) {
				$ids[] = '*';
			}
		}
		return array_values( array_unique( array_filter( array_map( 'strval', $ids ) ) ) );
	}

	/** ¿Alguna de estas condiciones cambia la salida entre visitantes anónimos (o con el tiempo)? */
	public static function varies_by_visitor( array $ids ) {
		return (bool) array_diff( $ids, self::cacheable_conditions() );
	}

	/** ¿El elemento (por sus ajustes guardados) pinta algo distinto según el visitante? */
	public static function is_conditioned( array $settings ) {
		return self::varies_by_visitor( self::condition_ids( $settings ) );
	}

	/**
	 * ¿El JSON guardado de un documento (`_elementor_data`) contiene elementos que varían por visitante? Primero se
	 * busca el texto de los marcadores (microsegundos); sólo si aparece alguno se decodifica y se clasifican las
	 * condiciones de cada elemento.
	 */
	public static function has_visitor_conditions( $json ) {
		return self::varies_by_visitor( self::document_condition_ids( $json ) );
	}

	/** Identificadores de todas las condiciones de un documento (`_elementor_data`), a cualquier profundidad; sin marcadores no se decodifica. */
	public static function document_condition_ids( $json ) {
		$json = (string) $json;
		if ( '' === $json ) {
			return array();
		}
		$found = false;
		foreach ( self::markers() as $marker ) {
			if ( false !== strpos( $json, $marker ) ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			return array();
		}
		$elements = json_decode( $json, true );
		$ids      = array();
		if ( is_array( $elements ) ) {
			self::collect_element_ids( $elements, $ids );
		}
		return array_values( array_unique( $ids ) );
	}

	private static function collect_element_ids( array $elements, array &$ids ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			if ( $settings ) {
				$ids = array_merge( $ids, self::condition_ids( $settings ) );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect_element_ids( $element['elements'], $ids );
			}
		}
	}

	/** ¿Alguna regla escribe cookies al pintar (visitas, sesiones)? Entonces la salida se bufferiza. */
	public static function needs_buffer( array $ids ) {
		return (bool) array_intersect( $ids, self::COOKIE_CONDITIONS );
	}

	/**
	 * Ubicaciones del Theme Builder que esta petición imprime. Un tema que registra ubicaciones propias (las del
	 * tema DIGITALÍSIMO alrededor de la cabecera, el pie y el contenido) las añade con el filtro
	 * `digitalisimo_conditions_theme_locations` según las que ya se imprimen.
	 */
	public static function theme_locations() {
		return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'digitalisimo_conditions_theme_locations', self::base_theme_locations() ) ) ) );
	}

	/**
	 * Con la plantilla Canvas ninguna; con «Cabecera y pie» o cualquier plantilla del tema distinta de la
	 * predeterminada, cabecera, pie y ventanas, pero no la plantilla de entrada; en archivos, cabecera, pie,
	 * archivo y ventanas.
	 */
	private static function base_theme_locations() {
		$locations = self::THEME_LOCATIONS;
		if ( ! is_singular() ) {
			return array_values( array_diff( $locations, array( 'single' ) ) );
		}
		$template = (string) get_page_template_slug( (int) get_queried_object_id() );
		if ( 'elementor_canvas' === $template ) {
			return array();
		}
		if ( '' !== $template && 'default' !== $template ) {
			return array_values( array_diff( $locations, array( 'single', 'archive' ) ) );
		}
		return array_values( array_diff( $locations, array( 'archive' ) ) );
	}

	/** IDs de los documentos que se pintarán: el de la URL (si está construido con el editor) y las plantillas del Theme Builder que su plantilla de página deja imprimir. */
	public static function documents_to_scan() {
		$ids = array();
		if ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
			if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
				$ids[] = $post_id;
			}
		}
		$locations = self::theme_locations();
		if ( $locations && class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
			try {
				$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
				foreach ( $locations as $location ) {
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

	/**
	 * Antes de la salida: si algún documento de la petición varía por visitante, la página no se guarda; si alguna regla
	 * escribe cookies al pintar, se abre un buffer de salida para que las cabeceras sigan abiertas (el constructor de
	 * origen lo abría en todas las páginas; aquí sólo cuando hace falta).
	 */
	public static function flag_before_output() {
		if ( self::$flagged || is_admin() || self::in_editor() || is_feed() || is_robots() || is_embed() ) {
			return;
		}
		$ids = array();
		foreach ( self::documents_to_scan() as $post_id ) {
			$ids = array_merge( $ids, self::document_condition_ids( get_post_meta( $post_id, '_elementor_data', true ) ) );
		}
		$ids = array_values( array_unique( $ids ) );
		if ( self::needs_buffer( $ids ) && apply_filters( 'digitalisimo_elements_visitor_content_buffer', true ) ) {
			self::$buffered = true;
			ob_start();
		}
		if ( self::varies_by_visitor( $ids ) ) {
			self::flag_page();
		}
	}

	private static $buffered = false;

	/** ¿Se abrió el buffer de salida en esta petición? */
	public static function buffered() {
		return self::$buffered;
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
		self::$flagged  = false;
		self::$buffered = false;
	}
}
