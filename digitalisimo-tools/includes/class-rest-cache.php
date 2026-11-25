<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

/**
 * La API pública (`/wp-json/`) sin respuestas viejas en la caché de página.
 *
 * Caso del 23-nov (digitalisimo.lat, LiteSpeed con QUIC.cloud): tras activar WooCommerce, `/wp-json/` sin sesión seguía
 * listando los namespaces de antes (sin `wc`) y `/wp-json/wp/v2/types` sin `product`, mientras que con sesión ya
 * aparecían. Ninguna caché de DIGITALÍSIMO guarda respuestas de la API (Tools sólo pone la sal de la caché de objetos, y
 * WordPress no guarda el índice de la API en ella): quien la servía era LiteSpeed Cache, que trae «Cache REST API»
 * encendido de fábrica (`cache-rest`, una semana de vida) y no la purga al activar ni al desactivar un plugin; sólo al
 * editar una entrada, por eso «volvió sola».
 *
 * Aquí, para cualquier caché de página:
 *  - las respuestas cuyo contenido depende de qué plugins están activos (el índice `/`, el índice de cada namespace,
 *    `/wp/v2/types`, `/wp/v2/taxonomies` y `/wp/v2/statuses`, con o sin su elemento, y el 404 `rest_no_route` de una
 *    ruta que todavía no existe) salen con las cabeceras sin caché de WordPress, `DONOTCACHEPAGE` y, con LiteSpeed
 *    Cache, `litespeed_control_set_nocache`. Sólo la petición principal (GET o HEAD): no toca las internas ni las
 *    precargas del editor;
 *  - al activar o desactivar un plugin se purga lo que LiteSpeed Cache guardó de la API (su etiqueta `REST`; activado o
 *    desactivado en toda la red, «*», la caché de página de todos los sitios en una sola purga). Las demás respuestas de un plugin
 *    (sus rutas) siguen cacheándose como hasta ahora y se renuevan con esa purga.
 * Sin LiteSpeed Cache no se purga nada: las otras cachés de página no guardan la API salvo que se les pida.
 */
final class Rest_Cache {
	/** Rutas de WordPress cuyo contenido cambia con los plugins (tipos de contenido, taxonomías y estados). */
	const CORE_DISCOVERY = '#^/wp/v2/(?:types|taxonomies|statuses)(?:/[^/]+)?$#D';
	/** Motivo que ve LiteSpeed Cache en su registro de depuración. */
	const REASON = 'DIGITALÍSIMO Tools: la API cambia al activar o desactivar plugins';

	public static function init() {
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'dispatch' ), 999, 3 );
		add_action( 'activated_plugin', array( __CLASS__, 'changed' ), 10, 2 );
		add_action( 'deactivated_plugin', array( __CLASS__, 'changed' ), 10, 2 );
	}

	/** ¿El contenido de esta ruta depende de los plugins activos? */
	public static function discovery( $route, array $namespaces = array() ) {
		$route = (string) $route;
		if ( '/' === $route || '' === $route ) return true;
		if ( preg_match( self::CORE_DISCOVERY, $route ) ) return true;
		return in_array( trim( $route, '/' ), array_map( 'strval', $namespaces ), true );
	}

	/** `rest_post_dispatch` (999): sin caché para el descubrimiento de la API y para el 404 de una ruta que no existe. */
	public static function dispatch( $response, $server = null, $request = null ) {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST || ! is_object( $response ) || ! method_exists( $response, 'header' ) || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) return $response;
		$method = method_exists( $request, 'get_method' ) ? strtoupper( (string) $request->get_method() ) : 'GET';
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || ! self::main( $request ) ) return $response;
		$route      = (string) $request->get_route();
		$namespaces = is_object( $server ) && method_exists( $server, 'get_namespaces' ) ? (array) $server->get_namespaces() : array();
		if ( self::discovery( $route, $namespaces ) || self::no_route( $response ) ) self::no_cache( $response );
		return $response;
	}

	/** ¿Es la petición que sirve esta carga (la ruta de `/wp-json/…` o de `?rest_route=`), como la calcula rest_api_loaded()? */
	private static function main( $request ) {
		$vars = isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->query_vars ) ? (array) $GLOBALS['wp']->query_vars : array();
		if ( ! isset( $vars['rest_route'] ) ) return false;
		$main = rtrim( (string) $vars['rest_route'], '/\\' );
		return ( '' === $main ? '/' : $main ) === (string) $request->get_route();
	}

	private static function no_route( $response ) {
		if ( ! method_exists( $response, 'get_status' ) || 404 !== (int) $response->get_status() || ! method_exists( $response, 'get_data' ) ) return false;
		$data = $response->get_data();
		return is_array( $data ) && 'rest_no_route' === ( $data['code'] ?? '' );
	}

	private static function no_cache( $response ) {
		$headers = function_exists( 'wp_get_nocache_headers' ) ? (array) wp_get_nocache_headers() : array( 'Cache-Control' => 'no-cache, must-revalidate, max-age=0, no-store, private' );
		foreach ( $headers as $name => $value ) {
			if ( false !== $value && '' !== (string) $value ) $response->header( $name, (string) $value );
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
		do_action( 'litespeed_control_set_nocache', self::REASON );
	}

	/** `activated_plugin` / `deactivated_plugin`: la API cambió; lo que LiteSpeed Cache guardó de ella ya no vale. */
	public static function changed( $plugin = '', $network_wide = false ) {
		if ( ! function_exists( 'has_action' ) || ! has_action( 'litespeed_purge' ) ) return;
		// En toda la red, «*»: LiteSpeed lo junta en una sola purga de la caché de página de todos los sitios (no
		// `litespeed_purge_all`, que además borra el CSS/JS combinado, vacía la caché de objetos, reinicia opcache y
		// purga Cloudflare, una vez por plugin).
		do_action( 'litespeed_purge', $network_wide && function_exists( 'is_multisite' ) && is_multisite() ? '*' : 'REST' );
	}

	/**
	 * Qué caché de página puede guardar la API pública en este sitio (para el MCP y «Salud del sitio»): LiteSpeed Cache
	 * con su «Cache REST API» encendido, o nada conocido.
	 */
	public static function status() {
		$litespeed = defined( 'LSCWP_V' ) || class_exists( '\\LiteSpeed\\Core', false );
		$rest      = $litespeed ? get_option( 'litespeed.conf.cache-rest', null ) : null;
		return array(
			'page_cache'      => $litespeed ? 'litespeed' : '',
			'caches_rest_api' => $litespeed ? ( null === $rest ? true : (bool) $rest ) : false,
			'protected'       => true,
			'note'            => $litespeed ? 'LiteSpeed Cache puede guardar la API pública; DIGITALÍSIMO Tools deja sin caché su índice, los tipos de contenido y las rutas que no existen, y purga lo guardado de la API al activar o desactivar un plugin.' : 'No hay una caché de página conocida que guarde la API pública.',
		);
	}
}
