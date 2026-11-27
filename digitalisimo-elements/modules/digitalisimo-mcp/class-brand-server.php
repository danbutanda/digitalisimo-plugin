<?php
namespace Digitalisimo\Elements\Mcp;

use Digitalisimo\Elements\Marca\Map;

defined( 'ABSPATH' ) || exit;

/**
 * El MCP del sitio con nombre propio (docs/MARCA_PROPIA.md, fase 1): `POST /wp-json/digitalisimo/mcp`.
 *
 * Es un segundo servidor del adaptador MCP con las MISMAS herramientas, recursos y prompts del registro compartido del
 * núcleo (`Elementor\MCP\Composer\Mcp\Registry`, con lo que suman el kit de ventas, la tienda, Hosting, IA Tools y
 * Tools por `elementor/mcp/server/tools`), el mismo interruptor del MCP del sitio (`McpSettingsController::is_enabled()`)
 * y el mismo transporte y permisos que el servidor del núcleo. Sólo cambia lo que ve el cliente:
 * - id `digitalisimo-mcp-server`, ruta `digitalisimo/mcp`, nombre «DIGITALÍSIMO MCP» y descripción propia;
 * - herramientas `digitalisimo-<x>` (`elementor-create-page` → `digitalisimo-create-page`) y recursos
 *   `digitalisimo://…`, decididos al construir el servidor con los filtros del adaptador (`mcp_adapter_tool_name`,
 *   `mcp_adapter_resource_uri`, `mcp_adapter_resource_name`, `mcp_adapter_prompt_name`) mientras se construye éste;
 * - descripciones, títulos y esquemas de las listas por `Map::out_deep()`; las respuestas de herramientas y recursos
 *   por `Map::data_out()` (identificadores siempre; el nombre «Elementor» sólo en los textos del motor, nunca en el
 *   contenido del cliente); lo que entra (argumentos y parámetros) por `Map::in_deep()`, así que valen los dos nombres
 *   (D-6).
 * El servidor del núcleo (`/wp-json/elementor/mcp`, nombres `elementor-*`) sigue igual hasta su retiro (`Legacy_Route`).
 * Nada de `elementor/` se edita.
 */
final class Brand_Server {
	const ID          = 'digitalisimo-mcp-server';
	const NS          = 'digitalisimo';
	const ROUTE       = 'mcp';
	const NAME        = 'DIGITALÍSIMO MCP';
	const DESCRIPTION = 'Lee y modifica el sitio con DIGITALÍSIMO Elements: páginas, plantillas, estilos, componentes, la tienda y el kit de ventas.';
	const VERSION     = 'v1.0.0';
	const REGISTRY    = 'Elementor\\MCP\\Composer\\Mcp\\Registry';
	const SETTINGS    = 'Elementor\\MCP\\Composer\\Admin\\McpSettingsController';
	/** Recursos que son guías fijas del motor (no datos del sitio): su texto sale con la marca. */
	const GUIDES      = array( 'elementor://wordpress/best-practices', 'elementor://style/best-practices', 'elementor://variables/tools/manage-global-variable-guide', 'elementor://interactions/schema', 'elementor://dynamic-tags' );

	/** ¿Se está construyendo este servidor? Sólo entonces los filtros de nombres cambian algo. */
	private static $building = false;
	/** @var array<string,string> nombre con marca => nombre de la ability (para ver choques) */
	private static $names = array();
	/** @var string[] choques de nombres al construir (deberían ser cero) */
	public static $collisions = array();

	public static function init(): void {
		add_action( 'mcp_adapter_init', array( __CLASS__, 'register' ), 20 );
		add_filter( 'mcp_adapter_tool_name', array( __CLASS__, 'tool_name' ), 20, 2 );
		add_filter( 'mcp_adapter_resource_uri', array( __CLASS__, 'resource_uri' ), 20, 2 );
		add_filter( 'mcp_adapter_resource_name', array( __CLASS__, 'component_name' ), 20, 2 );
		add_filter( 'mcp_adapter_prompt_name', array( __CLASS__, 'component_name' ), 20, 2 );
		add_filter( 'mcp_adapter_tools_list', array( __CLASS__, 'tools_list' ), 20, 2 );
		add_filter( 'mcp_adapter_resources_list', array( __CLASS__, 'dto_list' ), 20, 2 );
		add_filter( 'mcp_adapter_prompts_list', array( __CLASS__, 'dto_list' ), 20, 2 );
		add_filter( 'mcp_adapter_pre_tool_call', array( __CLASS__, 'pre_tool_call' ), 1, 4 );
		add_filter( 'mcp_adapter_tool_call_result', array( __CLASS__, 'tool_call_result' ), 999, 5 );
		add_filter( 'mcp_adapter_pre_resource_read', array( __CLASS__, 'pre_resource_read' ), 1, 4 );
		add_filter( 'mcp_adapter_resource_read_result', array( __CLASS__, 'resource_read_result' ), 999, 5 );
		add_filter( 'mcp_adapter_prompt_get_result', array( __CLASS__, 'prompt_result' ), 999, 5 );
		add_filter( 'mcp_adapter_initialize_response', array( __CLASS__, 'initialize' ), 20, 2 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'credentials' ), 20, 3 );
	}

	/**
	 * La pantalla «MCP» del núcleo crea una contraseña de aplicación y devuelve la configuración que se copia a los
	 * clientes (Claude, Cursor, Codex…): sale con la dirección y el nombre propios (`<sitio>-digitalisimo`).
	 */
	public static function credentials( $response, $server = null, $request = null ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
			return $response;
		}
		$route = (string) $request->get_route();
		if ( ! preg_match( '#^/elementor-mcp-composer/v\d+/mcp-credentials$#', $route ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return $response;
		}
		if ( isset( $data['data']['serverUrl'] ) ) {
			$data['data']['serverUrl'] = function_exists( 'rest_url' ) ? rest_url( self::NS . '/' . self::ROUTE . '/' ) : $data['data']['serverUrl'];
		}
		if ( isset( $data['data']['serverName'] ) && is_string( $data['data']['serverName'] ) ) {
			$data['data']['serverName'] = (string) preg_replace( '/-?elementor$/i', '', $data['data']['serverName'] ) . '-digitalisimo';
		}
		$response->set_data( $data );
		return $response;
	}

	/** ¿El MCP del sitio está encendido? El mismo interruptor que el servidor del núcleo. */
	public static function enabled(): bool {
		return class_exists( self::SETTINGS ) && (bool) call_user_func( array( self::SETTINGS, 'is_enabled' ) );
	}

	/** URL del servidor con nombre propio (la que se muestra en el escritorio). */
	public static function url(): string {
		return function_exists( 'rest_url' ) ? rest_url( self::NS . '/' . self::ROUTE ) : '/wp-json/' . self::NS . '/' . self::ROUTE;
	}

	/** ¿Este servidor del adaptador es el nuestro? */
	public static function is_ours( $server ): bool {
		return is_object( $server ) && method_exists( $server, 'get_server_id' ) && self::ID === $server->get_server_id();
	}

	/** @param mixed $adapter `WP\MCP\Core\McpAdapter` */
	public static function register( $adapter ): void {
		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) || ! class_exists( self::REGISTRY ) || ! self::enabled() ) {
			return;
		}
		if ( method_exists( $adapter, 'get_server' ) && $adapter->get_server( self::ID ) ) {
			return;
		}
		$registry  = call_user_func( array( self::REGISTRY, 'instance' ) );
		$tools     = (array) $registry->get_tools();
		$resources = self::resources_supported() ? (array) $registry->get_resources() : array();
		$prompts   = (array) $registry->get_prompts();
		if ( ! $tools && ! $resources && ! $prompts ) {
			return;
		}
		self::$building = true;
		self::$names    = array();
		try {
			$result = $adapter->create_server(
				self::ID,
				self::NS,
				self::ROUTE,
				self::NAME,
				self::DESCRIPTION,
				self::VERSION,
				array( 'WP\\MCP\\Transport\\HttpTransport' ),
				'WP\\MCP\\Infrastructure\\ErrorHandling\\ErrorLogMcpErrorHandler',
				'WP\\MCP\\Infrastructure\\Observability\\NullMcpObservabilityHandler',
				$tools,
				$resources,
				$prompts
			);
		} finally {
			self::$building = false;
		}
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[DIGITALÍSIMO MCP] No se registró el servidor: " . $result->get_error_message() );
		}
	}

	/** Los recursos con `mcp.uri` necesitan el adaptador 0.5.0 o posterior, como en el servidor del núcleo. */
	private static function resources_supported(): bool {
		$bootstrap = 'Elementor\\MCP\\Composer\\Mcp\\Server_Bootstrap';
		$adapter   = 'WP\\MCP\\Core\\McpAdapter';
		$version   = defined( $adapter . '::VERSION' ) ? (string) constant( $adapter . '::VERSION' ) : '0';
		return ! class_exists( $bootstrap ) || ! method_exists( $bootstrap, 'supports_namespaced_resource_meta' ) || (bool) call_user_func( array( $bootstrap, 'supports_namespaced_resource_meta' ), $version );
	}

	/** Mientras se construye este servidor: `elementor-x` → `digitalisimo-x`, sin chocar con otra herramienta. */
	public static function tool_name( $name, $ability = null ) {
		if ( ! self::$building || ! is_string( $name ) ) {
			return $name;
		}
		return self::unique( Map::tool_name( $name ), $name, $ability );
	}

	public static function resource_uri( $uri, $ability = null ) {
		return self::$building && is_string( $uri ) ? Map::uri( $uri ) : $uri;
	}

	/** Nombres de recursos y prompts: sólo letras, números, guiones, puntos y guiones bajos (regla del MCP). */
	public static function component_name( $name, $ability = null ) {
		if ( ! self::$building || ! is_string( $name ) || false === stripos( $name, 'elementor' ) ) {
			return $name;
		}
		return preg_replace_callback(
			'/elementor/i',
			static function ( $m ) {
				return ctype_upper( $m[0][0] ) ? 'Digitalisimo' : 'digitalisimo';
			},
			$name
		);
	}

	/** Si el nombre con marca ya lo usa otra ability, se conserva el original (y queda anotado). */
	private static function unique( string $branded, string $original, $ability ): string {
		$owner = is_object( $ability ) && method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : $original;
		if ( isset( self::$names[ $branded ] ) && self::$names[ $branded ] !== $owner ) {
			self::$collisions[] = $branded . ' (' . self::$names[ $branded ] . ' y ' . $owner . ')';
			return $original;
		}
		self::$names[ $branded ] = $owner;
		return $branded;
	}

	/** @param array $tools Tool DTO */
	public static function tools_list( $tools, $server = null ) {
		return self::is_ours( $server ) && is_array( $tools ) ? self::map_dtos( $tools ) : $tools;
	}

	/** Recursos y prompts: mismos DTO, misma regla. */
	public static function dto_list( $items, $server = null ) {
		return self::is_ours( $server ) && is_array( $items ) ? self::map_dtos( $items ) : $items;
	}

	/** Cada DTO por toArray() → out_deep() → fromArray() de su clase; si algo falla, el DTO sale como estaba. */
	private static function map_dtos( array $items ): array {
		$out = array();
		foreach ( $items as $key => $dto ) {
			$out[ $key ] = $dto;
			if ( ! is_object( $dto ) || ! method_exists( $dto, 'toArray' ) || ! method_exists( $dto, 'fromArray' ) ) {
				continue;
			}
			try {
				// Como arreglos (los objetos vacíos del esquema salen como `{}` en toArray() y fromArray() pide arreglos).
				$class       = get_class( $dto );
				$data        = json_decode( (string) wp_json_encode( $dto->toArray() ), true );
				$out[ $key ] = is_array( $data ) ? $class::fromArray( Map::out_deep( $data ) ) : $dto;
			} catch ( \Throwable $e ) {
				$out[ $key ] = $dto;
			}
		}
		return $out;
	}

	public static function pre_tool_call( $args, $tool_name = '', $tool = null, $server = null ) {
		return self::is_ours( $server ) && is_array( $args ) ? Map::in_deep( $args ) : $args;
	}

	public static function tool_call_result( $result, $args = array(), $tool_name = '', $tool = null, $server = null ) {
		return self::is_ours( $server ) ? Map::data_out( $result ) : $result;
	}

	public static function pre_resource_read( $params, $uri = '', $resource = null, $server = null ) {
		return self::is_ours( $server ) && is_array( $params ) ? Map::in_deep( $params ) : $params;
	}

	public static function resource_read_result( $contents, $params = array(), $uri = '', $resource = null, $server = null ) {
		if ( ! self::is_ours( $server ) ) {
			return $contents;
		}
		// Las guías del motor (texto fijo) salen con la marca; los recursos con datos del sitio, sólo con identificadores.
		$contents = in_array( Map::in( (string) $uri ), self::GUIDES, true ) ? Map::out_deep( $contents ) : Map::data_out( $contents );
		// El URI del contenido es el que pidió el cliente (con marca), aunque la ability devuelva el suyo.
		if ( is_array( $contents ) && is_string( $uri ) && '' !== $uri ) {
			if ( isset( $contents['uri'] ) && is_string( $contents['uri'] ) ) {
				$contents['uri'] = Map::uri( $contents['uri'] );
			}
			foreach ( $contents as $i => $item ) {
				if ( is_array( $item ) && isset( $item['uri'] ) && is_string( $item['uri'] ) ) {
					$contents[ $i ]['uri'] = Map::uri( $item['uri'] );
				}
			}
		}
		return $contents;
	}

	public static function prompt_result( $result, $arguments = array(), $name = '', $prompt = null, $server = null ) {
		return self::is_ours( $server ) ? Map::data_out( $result ) : $result;
	}

	/** `initialize`: en el nuestro, la descripción propia con marca; en el del núcleo, la nota del retiro (D-7). */
	public static function initialize( $result, $server = null ) {
		if ( ! is_object( $result ) || ! method_exists( $result, 'toArray' ) ) {
			return $result;
		}
		if ( self::is_ours( $server ) ) {
			return $result;
		}
		if ( ! Legacy_Route::is_legacy( $server ) ) {
			return $result;
		}
		Legacy_Route::touch( 'initialize' );
		try {
			$data                 = $result->toArray();
			$data['instructions'] = trim( (string) ( $data['instructions'] ?? '' ) . "\n\n" . Legacy_Route::notice() );
			$class                = get_class( $result );
			return $class::fromArray( $data );
		} catch ( \Throwable $e ) {
			return $result;
		}
	}
}
