<?php
namespace Digitalisimo\Elements;

use Digitalisimo\Elements\Import\Kits\Preserve;

defined( 'ABSPATH' ) || exit;

/**
 * Guarda hacia delante de los widgets propios (D28 y V.10.2 de F4-Woo), desde 4.3.0.165.
 *
 * El núcleo descarta al cargar y al guardar todo widget que no puede instanciar. Si un documento lleva un widget
 * `digitalisimo-*` de una versión posterior de Elements (o de un módulo que este sitio no tiene), abrirlo y guardarlo
 * en el editor lo borraría. En las tres peticiones del editor (la pantalla `action=elementor`, su vista previa
 * `elementor-preview` y `admin-ajax.php?action=elementor_ajax`), y para quien puede editar:
 *
 * - `elementor/document/load/data` con prioridad 15 (antes de la 20 de K) recorre el árbol y, si encuentra un widget
 *   `digitalisimo-*` que no está registrado, pasa el árbol por `Preserve::wrap()` con un `known` que sólo da
 *   «desconocido» a esos widgets: cada uno queda en el marcador `digitalisimo-unconverted` de K, con su original
 *   completo. Antes llama a `Preserve::expect( true )` y `Preserve::ensure_widget()`, que registran el marcador y
 *   recalculan los contadores al guardar. Cuando el tipo vuelve a existir, K lo devuelve al leer.
 * - En `admin-ajax.php?action=elementor_ajax`, si la petición trae marcadores (guardar, pintar) o pide la
 *   configuración de los widgets, `elementor/ajax/register_actions` registra el marcador: sin él, el guardado que sigue
 *   a una carga envuelta lo descartaría.
 *
 * Un widget conocido o ajeno (`acme-*`) no se toca; sin widgets desconocidos, el árbol no cambia. En el front, REST,
 * cron y WP-CLI no se engancha nada (coste 0). Sólo usa la API pública de `Preserve`; no cambia K.
 */
final class Unknown_Guard {
	/** Prefijo de los widgets propios que se protegen. */
	const PREFIX = 'digitalisimo-';

	/** Widget marcador de K (`Import\Module::WIDGET`). */
	const MARKER = 'digitalisimo-unconverted';

	/** Antes de `Preserve::PRIORITY` (20). */
	const PRIORITY = 15;

	/** Profundidad máxima que se recorre. */
	const MAX_DEPTH = 256;

	/** @var bool */
	private static $initialized = false;

	/** @var bool|null Memoria de `editor_request()`. */
	private static $editor = null;

	/** En `Widget_Registry::init()`. Sólo engancha algo en las peticiones del editor. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		if ( ! function_exists( 'add_filter' ) || ! self::editor_request() ) {
			return;
		}
		add_filter( 'elementor/document/load/data', array( __CLASS__, 'filter_load_data' ), self::PRIORITY, 2 );
		if ( self::ajax_needs_marker() ) {
			add_action( 'elementor/ajax/register_actions', array( __CLASS__, 'register_marker' ), 5 );
		}
	}

	/**
	 * ¿Es una de las tres peticiones del editor? `action=elementor` en la administración, `elementor-preview` en el
	 * front o `admin-ajax.php?action=elementor_ajax`. Nunca REST, cron ni WP-CLI. El permiso se comprueba aparte, en
	 * cada gancho (`edit_posts`), porque aquí el usuario aún no está cargado.
	 */
	public static function editor_request(): bool {
		if ( null !== self::$editor ) {
			return self::$editor;
		}
		self::$editor = false;
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || self::rest_request() ) {
			return false;
		}
		$ajax = function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();
		// phpcs:disable WordPress.Security.NonceVerification -- sólo decide si se enganchan filtros; el núcleo valida su nonce.
		if ( $ajax ) {
			self::$editor = isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'];
		} elseif ( function_exists( 'is_admin' ) && is_admin() ) {
			self::$editor = isset( $_GET['action'] ) && 'elementor' === $_GET['action'];
		} else {
			self::$editor = isset( $_GET['elementor-preview'] );
		}
		// phpcs:enable
		return self::$editor;
	}

	/**
	 * ¿Es una petición REST? `REST_REQUEST` aún no existe en `plugins_loaded`: se mira también la URL (`rest_route` o el
	 * prefijo de la API), para que un `elementor-preview` añadido a una ruta REST no la convierta en petición del editor.
	 */
	public static function rest_request(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo se mira si existe.
		if ( isset( $_GET['rest_route'] ) ) {
			return true;
		}
		$uri    = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$prefix = function_exists( 'rest_get_url_prefix' ) ? trim( (string) rest_get_url_prefix(), '/' ) : 'wp-json';
		return '' !== $uri && '' !== $prefix && false !== strpos( $uri, '/' . $prefix . '/' );
	}

	/**
	 * `elementor/document/load/data` (15): envuelve en el marcador cada widget `digitalisimo-*` no registrado.
	 *
	 * @param mixed  $data     Árbol de elementos.
	 * @param object $document Documento que se carga.
	 * @return mixed
	 */
	public static function filter_load_data( $data, $document = null ) {
		if ( ! is_array( $data ) || ! $data || self::rest_request() || ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) ) {
			return $data;
		}
		if ( ! class_exists( Preserve::class ) || ! self::has_unknown( $data, 0 ) ) {
			return $data;
		}
		Preserve::expect( true );
		if ( ! Preserve::ensure_widget() ) {
			return $data;
		}
		return Preserve::wrap( $data, Preserve::SOURCE_LIBRARY, array( 'known' => array( __CLASS__, 'known' ) ) );
	}

	/**
	 * `known` para `Preserve::wrap()`: sólo un widget `digitalisimo-*` (no el marcador) sin registrar es «desconocido».
	 */
	public static function known( string $el_type, ?string $widget_type = null ): bool {
		if ( 'widget' !== $el_type || ! self::protected_type( $widget_type ) ) {
			return true;
		}
		return self::registered( (string) $widget_type );
	}

	/** ¿Hay en el árbol algún widget `digitalisimo-*` sin registrar? */
	public static function has_unknown( array $elements, int $depth = 0 ): bool {
		if ( $depth > self::MAX_DEPTH ) {
			return false;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$el_type     = isset( $element['elType'] ) ? $element['elType'] : '';
			$widget_type = isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : null;
			if ( 'widget' === $el_type && self::protected_type( $widget_type ) && ! self::registered( (string) $widget_type ) ) {
				return true;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && self::has_unknown( $element['elements'], $depth + 1 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * `elementor/ajax/register_actions` (5): registra el marcador para el guardado o el pintado que trae marcadores.
	 */
	public static function register_marker(): void {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) || ! class_exists( Preserve::class ) ) {
			return;
		}
		Preserve::expect( true );
		Preserve::ensure_widget();
	}

	/**
	 * ¿Trae esta petición AJAX del editor marcadores o pide la configuración de los widgets? Una búsqueda de cadena en
	 * el cuerpo, sin decodificarlo.
	 */
	public static function ajax_needs_marker(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- sólo se busca una cadena; el núcleo valida la petición.
		$actions = isset( $_POST['actions'] ) && is_string( $_POST['actions'] ) ? $_POST['actions'] : '';
		return '' !== $actions && ( false !== strpos( $actions, self::MARKER ) || false !== strpos( $actions, 'get_widgets_config' ) );
	}

	/** Widget propio que se protege: `digitalisimo-*`, salvo el marcador. */
	private static function protected_type( ?string $widget_type ): bool {
		return is_string( $widget_type ) && 0 === strpos( $widget_type, self::PREFIX ) && self::MARKER !== $widget_type;
	}

	/** ¿Está registrado el tipo? Sin núcleo no se puede decidir: se responde que sí y nada cambia. */
	private static function registered( string $type ): bool {
		if ( ! class_exists( '\Elementor\Plugin', false ) || empty( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return true;
		}
		return is_object( \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $type ) );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
		self::$editor      = null;
	}
}
