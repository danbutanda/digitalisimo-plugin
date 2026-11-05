<?php
namespace Digitalisimo\Elements\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Base de las rutas REST de F4-Woo: `digitalisimo-elements/v1/woo/<ruta>` (§4.3 y V.4.3).
 *
 * - `route()` guarda la ruta y la registra en `rest_api_init`. Cada punto final debe traer su `permission_callback`
 *   (sin él, la ruta no se registra). Con `'digitalisimo_cart' => true`, el punto final llama a `wc_load_cart()`
 *   antes de su callback (V-I8): REST no es una petición de front para WooCommerce (W-15) y sin carrito los precios,
 *   impuestos por ubicación y precios por sesión no serían los del visitante.
 * - **Sin caché, sólo en las rutas propias** (R-M2): cada respuesta de `woo/*`, también los errores de permiso que
 *   genera el servidor, lleva `Cache-Control: no-store, private` y las cabeceras de `nocache_headers()`, y la
 *   petición emite las señales de `Visitor_Content::flag_page()` (`DONOTCACHEPAGE`, `LSCACHE_NO_CACHE`,
 *   `litespeed_control_set_nocache`). Nunca el filtro global `rest_send_nocache_headers`.
 * - `element()` es el permiso de las rutas públicas (V-M22): halla el widget en el documento `post_id` (publicado, o
 *   editable por el usuario) y devuelve sus datos sólo si cumple el predicado de la ruta. Un `global` se sigue una vez a
 *   su `templateID`. Los ajustes salen siempre del documento, nunca de la petición.
 * - `body()` lee un cuerpo JSON con tope de tamaño (8 KB por defecto).
 */
final class Rest {
	const REST_NAMESPACE = 'digitalisimo-elements/v1';
	const PREFIX    = 'woo/';

	/** Tope del cuerpo JSON de una petición (V.4.3). */
	const MAX_BODY = 8192;

	/** Profundidad máxima que se recorre al buscar un elemento. */
	const MAX_DEPTH = 64;

	/** Forma de un ID de elemento (la de K, `Preserve::ID_PATTERN`). */
	const ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

	/** Ruta relativa admitida (`wishlist`, `quick-view`, `wishlist/(?P<id>\d+)`). */
	const PATH_PATTERN = '#^[a-z0-9][a-z0-9-]*(?:/[A-Za-z0-9_\-\\\\()?<>+*\[\]{}.]+)*$#';

	/** @var bool */
	private static $initialized = false;

	/** @var array<string,array> Rutas pendientes de registrar: `ruta relativa => argumentos`. */
	private static $routes = array();

	/** @var bool */
	private static $signals = false;

	/** Con WooCommerce: registra las rutas guardadas y las cabeceras de sus respuestas. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'post_dispatch' ), 10, 3 );
	}

	/**
	 * Declara una ruta `digitalisimo-elements/v1/woo/<$path>`.
	 *
	 * @param string $path Ruta relativa (`wishlist`, `cards`, `quick-view`).
	 * @param array  $args Un punto final (`methods`, `callback`, `permission_callback`, `args`…) o una lista de ellos,
	 *                     como `register_rest_route()`. Clave propia: `digitalisimo_cart` (bool).
	 * @return bool Si la ruta quedó guardada.
	 */
	public static function route( string $path, array $args ): bool {
		$path = trim( $path, '/' );
		if ( '' === $path || ! preg_match( self::PATH_PATTERN, $path ) ) {
			return false;
		}
		$endpoints = isset( $args['callback'] ) || isset( $args['methods'] ) ? array( $args ) : array_values( $args );
		$prepared  = array();
		foreach ( $endpoints as $endpoint ) {
			if ( ! is_array( $endpoint ) || empty( $endpoint['callback'] ) || ! is_callable( $endpoint['callback'] ) || ! isset( $endpoint['permission_callback'] ) || ! is_callable( $endpoint['permission_callback'] ) ) {
				return false; // Sin callback o sin permiso explícito, la ruta no existe.
			}
			$prepared[] = self::wrap( $endpoint );
		}
		if ( ! $prepared ) {
			return false;
		}
		self::$routes[ $path ] = $prepared;
		if ( function_exists( 'did_action' ) && did_action( 'rest_api_init' ) && function_exists( 'register_rest_route' ) ) {
			register_rest_route( self::REST_NAMESPACE, '/' . self::PREFIX . $path, $prepared );
		}
		return true;
	}

	/** `rest_api_init`: registra las rutas guardadas. */
	public static function register(): void {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}
		foreach ( self::$routes as $path => $endpoints ) {
			register_rest_route( self::REST_NAMESPACE, '/' . self::PREFIX . $path, $endpoints );
		}
	}

	/** @return array<string,array> Rutas guardadas (para las pruebas y el inventario). */
	public static function routes(): array {
		return self::$routes;
	}

	/** Ruta absoluta (`/digitalisimo-elements/v1/woo/…`) de una ruta relativa. */
	public static function full( string $path ): string {
		return '/' . self::REST_NAMESPACE . '/' . self::PREFIX . trim( $path, '/' );
	}

	/** URL de una ruta para el navegador (la configuración del pie y los `data-*` la usan). */
	public static function url( string $path ): string {
		return function_exists( 'rest_url' ) ? rest_url( self::REST_NAMESPACE . '/' . self::PREFIX . trim( $path, '/' ) ) : self::full( $path );
	}

	/** ¿Es una ruta propia (`/digitalisimo-elements/v1/woo/…`)? */
	public static function is_own( string $route ): bool {
		return 0 === strpos( $route, '/' . self::REST_NAMESPACE . '/' . self::PREFIX );
	}

	/**
	 * Envuelve el callback: carrito si se pidió, señales sin caché y respuesta (también los errores) con sus cabeceras.
	 */
	private static function wrap( array $endpoint ): array {
		$callback = $endpoint['callback'];
		$cart     = ! empty( $endpoint['digitalisimo_cart'] );
		unset( $endpoint['digitalisimo_cart'] );
		$endpoint['callback'] = static function ( $request ) use ( $callback, $cart ) {
			self::signals();
			if ( $cart ) {
				self::load_cart();
			}
			$result = call_user_func( $callback, $request );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) && function_exists( 'rest_convert_error_to_response' ) ) {
				$result = rest_convert_error_to_response( $result );
			}
			if ( function_exists( 'rest_ensure_response' ) ) {
				$result = rest_ensure_response( $result );
			}
			return self::nocache( $result );
		};
		return $endpoint;
	}

	/**
	 * `rest_post_dispatch`: cabeceras sin caché en toda respuesta de una ruta propia (también los 401/403 que el
	 * servidor genera antes del callback). Las demás rutas no cambian.
	 *
	 * @param mixed  $response Respuesta.
	 * @param mixed  $server   Servidor REST.
	 * @param object $request  Petición.
	 * @return mixed
	 */
	public static function post_dispatch( $response, $server = null, $request = null ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! self::is_own( (string) $request->get_route() ) ) {
			return $response;
		}
		self::signals();
		return self::nocache( $response );
	}

	/**
	 * Cabeceras sin caché en una respuesta.
	 *
	 * @param mixed $response `WP_REST_Response` (otro valor se devuelve igual).
	 * @return mixed
	 */
	public static function nocache( $response ) {
		if ( ! is_object( $response ) || ! method_exists( $response, 'header' ) ) {
			return $response;
		}
		$headers = function_exists( 'wp_get_nocache_headers' ) ? (array) wp_get_nocache_headers() : array();
		foreach ( $headers as $name => $value ) {
			if ( '' !== (string) $value ) {
				$response->header( $name, (string) $value );
			}
		}
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	/**
	 * Señales para que ninguna caché guarde la respuesta, como `Visitor_Content::flag_page()` (pero sin su filtro: una
	 * respuesta de estas rutas depende siempre del visitante). Una vez por petición.
	 */
	public static function signals(): void {
		if ( self::$signals ) {
			return;
		}
		self::$signals = true;
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
			define( 'LSCACHE_NO_CACHE', true );
		}
		if ( function_exists( 'do_action' ) ) {
			do_action( 'litespeed_control_set_nocache', 'digitalisimo-elements: respuesta por visitante' );
		}
		if ( function_exists( 'headers_sent' ) && ! headers_sent() && function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
	}

	/**
	 * Carga la sesión y el carrito de WooCommerce si aún no existen (V-I8). Devuelve si hay carrito.
	 */
	public static function load_cart(): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		$wc = WC();
		if ( is_object( $wc ) && isset( $wc->cart ) && null !== $wc->cart ) {
			return true;
		}
		if ( function_exists( 'wc_load_cart' ) && function_exists( 'did_action' ) && did_action( 'woocommerce_init' ) ) {
			try {
				wc_load_cart();
			} catch ( \Throwable $error ) {
				return false;
			}
		}
		$wc = WC();
		return is_object( $wc ) && isset( $wc->cart ) && null !== $wc->cart;
	}

	/**
	 * Cuerpo JSON de una petición, o null si pasa de `$max` bytes o no es un objeto o lista JSON.
	 *
	 * @param object $request `WP_REST_Request`.
	 */
	public static function body( $request, int $max = self::MAX_BODY ): ?array {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_body' ) ) {
			return null;
		}
		$raw = (string) $request->get_body();
		if ( '' === $raw || strlen( $raw ) > $max ) {
			return null;
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Permiso de una ruta pública (V-M22): el widget `$widget_id` del documento `$post_id`, si cumple `$predicate`.
	 *
	 * - El documento existe y está publicado sin contraseña, o el usuario puede editarlo (el editor y su vista previa).
	 *   Dentro de una plantilla de bucle, `$post_id` es esa plantilla: el documento que contiene el widget.
	 * - Un elemento `global` se sigue **una vez** a su `templateID` (también publicado o editable): el predicado recibe el
	 *   widget de la plantilla, con sus ajustes. Un `global` dentro de esa plantilla no se sigue.
	 * - `$predicate( array $element ): bool` decide por tipo y ajustes; sólo `true` vale.
	 *
	 * @return array|null Datos del elemento (`id`, `elType`, `widgetType`, `settings`…), o null.
	 */
	public static function element( int $post_id, string $widget_id, callable $predicate ): ?array {
		if ( $post_id <= 0 || ! preg_match( self::ID_PATTERN, $widget_id ) ) {
			return null;
		}
		$data = self::document_data( $post_id );
		if ( null === $data ) {
			return null;
		}
		$element = self::find( $data, $widget_id, 0 );
		if ( null === $element ) {
			return null;
		}
		if ( 'widget' === ( isset( $element['elType'] ) ? $element['elType'] : '' ) && 'global' === ( isset( $element['widgetType'] ) ? $element['widgetType'] : '' ) ) {
			$template = isset( $element['templateID'] ) ? (int) $element['templateID'] : 0;
			$inner    = $template > 0 ? self::document_data( $template ) : null;
			$first    = is_array( $inner ) ? reset( $inner ) : null;
			if ( ! is_array( $first ) || 'widget' !== ( isset( $first['elType'] ) ? $first['elType'] : '' ) || 'global' === ( isset( $first['widgetType'] ) ? $first['widgetType'] : '' ) ) {
				return null;
			}
			$first['digitalisimo_global'] = array(
				'id'       => $widget_id,
				'template' => $template,
			);
			$element = $first;
		}
		if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
			$element['settings'] = array();
		}
		return true === call_user_func( $predicate, $element ) ? $element : null;
	}

	/**
	 * Árbol de elementos guardado de un documento que el visitante puede ver, o null.
	 *
	 * @return array|null
	 */
	public static function document_data( int $post_id ): ?array {
		if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
			return null;
		}
		$post = get_post( $post_id );
		if ( ! is_object( $post ) ) {
			return null;
		}
		$editable = function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $post_id );
		$public   = 'publish' === ( isset( $post->post_status ) ? $post->post_status : '' ) && '' === (string) ( isset( $post->post_password ) ? $post->post_password : '' );
		if ( ! $public && ! $editable ) {
			return null;
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = json_decode( $raw, true );
		}
		return is_array( $raw ) ? $raw : null;
	}

	/** Busca un elemento por su ID en un árbol, sin pasar de `MAX_DEPTH`. */
	private static function find( array $elements, string $id, int $depth ): ?array {
		if ( $depth > self::MAX_DEPTH ) {
			return null;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['id'] ) && (string) $element['id'] === $id ) {
				return $element;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = self::find( $element['elements'], $id, $depth + 1 );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$routes      = array();
		self::$signals     = false;
		self::$initialized = false;
	}
}
