<?php
namespace Digitalisimo\Elements\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * La ruta vieja del MCP (`/wp-json/elementor/mcp`, nombres `elementor-*`) mientras dure el alias (D-7 de
 * docs/MARCA_PROPIA.md):
 * - no aparece en el índice REST (`/wp-json/`), aunque sigue funcionando para quien ya la usa;
 * - su `initialize` agrega una nota con la ruta nueva y la fecha de retiro (`Brand_Server::initialize()`), sólo con la
 *   marca propia en vigor o con el retiro habilitado (`announce()`): apagada, nada cambia para quien la usa;
 * - cada uso queda contado en la opción `digitalisimo_elements_mcp_legacy` (sin carga automática): el día del último uso
 *   y en cuántos días se usó cada herramienta. Sin usuarios, IP ni argumentos. Se escribe a lo sumo una vez por
 *   herramienta y por día (`today`), no en cada llamada;
 * - su retiro: el 2027-01-31 si lleva 14 días sin uso; si en esa fecha se sigue usando, se prorroga 30 días una vez
 *   (queda anotado y «Rendimiento» lo avisa). Retirar es quitar sus rutas de `rest_endpoints` (el servidor del núcleo no
 *   se toca), y sólo lo hace la versión que lo habilita (`retire_enabled()`, versión D de la fase 4): esta versión
 *   calcula y avisa las fechas, pero nunca deja sin MCP a un cliente que no cambió la dirección.
 */
final class Legacy_Route {
	const OPTION    = 'digitalisimo_elements_mcp_legacy';
	const ID        = 'elementor-mcp-server';
	const NS        = 'elementor';
	const RETIRE    = '2027-01-31';
	const QUIET     = 14;
	const EXTENSION = 30;

	public static function init(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		add_filter( 'mcp_adapter_pre_tool_call', array( __CLASS__, 'count_tool' ), 2, 4 );
		add_filter( 'mcp_adapter_pre_resource_read', array( __CLASS__, 'count_resource' ), 2, 4 );
		add_filter( 'mcp_adapter_pre_prompt_get', array( __CLASS__, 'count_prompt' ), 2, 4 );
		add_filter( 'rest_index', array( __CLASS__, 'hide_from_index' ), 20 );
		add_filter( 'rest_endpoints', array( __CLASS__, 'retire' ), 20 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'unmask' ), 1, 3 );
	}

	/**
	 * Con el paquete transformado, las guías del motor llegan enmascaradas (un separador invisible dentro de la palabra,
	 * scripts/marca-empaquetar.mjs): en la ruta vieja y en el proxy del editor vuelven a su forma, así los nombres de
	 * herramientas que citan siguen sirviendo. Sin paquete transformado no hay nada que hacer.
	 */
	public static function unmask( $response, $server = null, $request = null ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
			return $response;
		}
		if ( ! preg_match( '#^/elementor/(mcp|v1/mcp-proxy)(/|$)#', (string) $request->get_route() ) ) {
			return $response;
		}
		$response->set_data( \Digitalisimo\Elements\Marca\Map::unmask_deep( $response->get_data() ) );
		return $response;
	}

	public static function is_legacy( $server ): bool {
		return is_object( $server ) && method_exists( $server, 'get_server_id' ) && self::ID === $server->get_server_id();
	}

	public static function count_tool( $args, $tool_name = '', $tool = null, $server = null ) {
		if ( self::is_legacy( $server ) ) {
			self::touch( is_string( $tool_name ) ? $tool_name : '' );
		}
		return $args;
	}

	public static function count_resource( $params, $uri = '', $resource = null, $server = null ) {
		if ( self::is_legacy( $server ) ) {
			self::touch( 'resources-read' );
		}
		return $params;
	}

	public static function count_prompt( $args, $name = '', $prompt = null, $server = null ) {
		if ( self::is_legacy( $server ) ) {
			self::touch( 'prompts-get' );
		}
		return $args;
	}

	/**
	 * ¿Esta versión puede retirar la ruta? No: el retiro llega en su propia versión (fase 4, versión D de
	 * docs/MARCA_PROPIA.md), que cambia este valor. Hasta entonces la fecha y la prórroga sólo se calculan y se avisan en
	 * «Rendimiento» y en el `initialize` de la ruta vieja. Filtro `digitalisimo_elements_mcp_legacy_retire`.
	 */
	public static function retire_enabled(): bool {
		return (bool) apply_filters( 'digitalisimo_elements_mcp_legacy_retire', false );
	}

	/**
	 * ¿Se avisa el retiro a quien usa la ruta vieja? Sólo con la marca propia en vigor (entonces la ruta nueva es la
	 * que el sitio muestra) o con el retiro habilitado (`retire_enabled()`).
	 */
	public static function announce(): bool {
		if ( self::retire_enabled() ) {
			return true;
		}
		return class_exists( \Digitalisimo\Elements\Marca\Map::class ) && \Digitalisimo\Elements\Marca\Map::active();
	}

	/** @return array{last:string,tools:array<string,int>,extended:string,today:string[]} */
	public static function state(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'last'     => is_string( $raw['last'] ?? null ) ? $raw['last'] : '',
			'tools'    => is_array( $raw['tools'] ?? null ) ? array_map( 'intval', $raw['tools'] ) : array(),
			'extended' => is_string( $raw['extended'] ?? null ) ? $raw['extended'] : '',
			'today'    => is_array( $raw['today'] ?? null ) ? array_values( array_filter( $raw['today'], 'is_string' ) ) : array(),
		);
	}

	/**
	 * Anota un uso de hoy (una herramienta o `initialize`): el día del último uso y, por herramienta, en cuántos días se
	 * usó. Si hoy ya está anotada, no escribe nada (sin una escritura en la base por cada llamada ni conteos que se
	 * pisen entre llamadas simultáneas).
	 */
	public static function touch( string $what, ?int $now = null ): void {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}
		$day   = gmdate( 'Y-m-d', $now ?? time() );
		$what  = substr( (string) preg_replace( '/[^a-z0-9_-]/i', '', $what ), 0, 64 );
		$state = self::state();
		if ( $day === $state['last'] && ( '' === $what || in_array( $what, $state['today'], true ) ) ) {
			return;
		}
		if ( $day !== $state['last'] ) {
			$state['today'] = array();
		}
		$state['last'] = $day;
		if ( '' !== $what ) {
			$state['today'][]        = $what;
			$state['tools'][ $what ] = ( $state['tools'][ $what ] ?? 0 ) + 1;
			if ( count( $state['tools'] ) > 100 ) {
				arsort( $state['tools'] );
				$state['tools'] = array_slice( $state['tools'], 0, 100, true );
			}
			$state['today'] = array_slice( array_values( array_intersect( $state['today'], array_keys( $state['tools'] ) ) ), 0, 100 );
		}
		update_option( self::OPTION, $state, false );
	}

	/**
	 * Fecha (Y-m-d) hasta la que sigue la ruta vieja, decidida con el estado: la de retiro, o el fin de la prórroga si
	 * en esa fecha se siguió usando. Al cruzar la fecha con usos recientes, anota la prórroga (una sola vez).
	 */
	public static function until( ?int $now = null ): string {
		$now   = $now ?? time();
		$today = gmdate( 'Y-m-d', $now );
		$state = self::state();
		if ( '' !== $state['extended'] ) {
			return $state['extended'];
		}
		if ( $today < self::RETIRE ) {
			return self::RETIRE;
		}
		if ( self::used_within( $state['last'], self::QUIET, $now ) ) {
			$state['extended'] = gmdate( 'Y-m-d', strtotime( self::RETIRE . ' +' . self::EXTENSION . ' days' ) );
			if ( $today >= $state['extended'] ) {
				$state['extended'] = gmdate( 'Y-m-d', $now + self::EXTENSION * DAY_IN_SECONDS );
			}
			if ( function_exists( 'update_option' ) ) {
				update_option( self::OPTION, $state, false );
			}
			return $state['extended'];
		}
		return self::RETIRE;
	}

	/** ¿Ya se retiró la ruta vieja? */
	public static function retired( ?int $now = null ): bool {
		$now = $now ?? time();
		return gmdate( 'Y-m-d', $now ) >= self::until( $now );
	}

	private static function used_within( string $last, int $days, int $now ): bool {
		if ( '' === $last ) {
			return false;
		}
		$time = strtotime( $last . ' 00:00:00 UTC' );
		return false !== $time && ( $now - $time ) < ( $days + 1 ) * DAY_IN_SECONDS;
	}

	/** Nota para los clientes que siguen en la ruta vieja. */
	public static function notice( ?int $now = null ): string {
		return sprintf(
			'Esta dirección (/wp-json/elementor/mcp) es un alias que se retira el %1$s; aquí las herramientas siguen con sus nombres de siempre. Cambia la dirección a %2$s: allí las mismas herramientas se llaman digitalisimo-* y los recursos digitalisimo://.',
			self::until( $now ),
			Brand_Server::url()
		);
	}

	/** El índice REST no lista la ruta vieja del MCP (D-7). */
	public static function hide_from_index( $response ) {
		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ! method_exists( $response, 'set_data' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}
		$route = '/' . self::NS . '/mcp';
		if ( isset( $data['routes'] ) && is_array( $data['routes'] ) ) {
			foreach ( array_keys( $data['routes'] ) as $key ) {
				if ( $route === $key || 0 === strpos( (string) $key, $route . '/' ) || '/' . self::NS === $key ) {
					unset( $data['routes'][ $key ] );
				}
			}
		}
		if ( isset( $data['namespaces'] ) && is_array( $data['namespaces'] ) ) {
			$others = false;
			foreach ( (array) ( $data['routes'] ?? array() ) as $info ) {
				if ( is_array( $info ) && self::NS === ( $info['namespace'] ?? '' ) ) {
					$others = true;
					break;
				}
			}
			if ( ! $others ) {
				$data['namespaces'] = array_values( array_diff( $data['namespaces'], array( self::NS ) ) );
			}
		}
		$response->set_data( $data );
		return $response;
	}

	/** Retirada: sin las rutas `/elementor/mcp` (el servidor del núcleo no se toca). */
	public static function retire( $endpoints ) {
		if ( ! is_array( $endpoints ) || ! self::retire_enabled() || ! self::retired() ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( '/' . self::NS . '/mcp' === $route || 0 === strpos( (string) $route, '/' . self::NS . '/mcp/' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}
}
