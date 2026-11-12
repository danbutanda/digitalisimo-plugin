<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Bitácora de las escrituras hechas con las herramientas de tienda del MCP, por sitio.
 *
 * Una opción del sitio sin carga automática (`OPTION`) con las últimas `MAX` entradas, la más reciente primero. Cada
 * entrada lleva sólo `FIELDS`: fecha (UTC), usuario (su ID), herramienta, acción, recurso, ID y padre (números o claves
 * de la ruta: un pedido, una opción de ajustes, una pasarela), elementos de un lote, resultado (`ok`, `partial`
 * —un lote con elementos fallidos—, `error` o `denied`), el código de error y el estado HTTP. Nunca nombres, correos, direcciones, importes ni valores: `entry()`
 * no recibe la entrada del cliente ni la respuesta, sólo el plan del enrutador. Se consulta con la acción `log` de la
 * herramienta de informes.
 */
final class Log {
	const OPTION = 'digitalisimo_elements_woo_mcp_log';
	const MAX    = 200;
	const FIELDS = array( 'time', 'user', 'tool', 'action', 'resource', 'id', 'parent', 'items', 'result', 'code', 'status' );

	/**
	 * Entrada de la bitácora a partir del plan de `Router::resolve()`.
	 *
	 * @param array  $plan   Plan del enrutador (sólo se leen herramienta, acción, recurso, IDs y elementos).
	 * @param string $result `ok`, `partial`, `error` o `denied`.
	 * @param string $code   Código de error ('' si fue bien), reducido a `[a-z0-9_]`.
	 * @param int    $status Estado HTTP.
	 * @param int    $user   ID del usuario.
	 * @param string $time   Fecha UTC (ISO 8601); por defecto, ahora.
	 */
	public static function entry( array $plan, string $result, string $code, int $status, int $user, string $time = '' ): array {
		return array(
			'time'     => '' !== $time ? $time : gmdate( 'Y-m-d\TH:i:s\Z' ),
			'user'     => max( 0, $user ),
			'tool'     => self::token( isset( $plan['tool'] ) ? $plan['tool'] : '' ),
			'action'   => self::token( isset( $plan['action'] ) ? $plan['action'] : '' ),
			'resource' => self::token( isset( $plan['resource'] ) ? $plan['resource'] : '' ),
			'id'       => self::ref( isset( $plan['id'] ) ? $plan['id'] : null ),
			'parent'   => self::ref( isset( $plan['parent'] ) ? $plan['parent'] : null ),
			'items'    => isset( $plan['items'] ) ? max( 0, (int) $plan['items'] ) : 0,
			'result'   => in_array( $result, array( 'ok', 'partial', 'error', 'denied' ), true ) ? $result : 'error',
			'code'     => substr( (string) preg_replace( '/[^a-z0-9_]/', '', strtolower( $code ) ), 0, 80 ),
			'status'   => max( 0, $status ),
		);
	}

	/** Añade una entrada (la más reciente primero) y deja las últimas `MAX`. */
	public static function add( array $entry ): void {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}
		$clean = array();
		foreach ( self::FIELDS as $field ) {
			$clean[ $field ] = isset( $entry[ $field ] ) ? $entry[ $field ] : null;
		}
		$entries = self::entries();
		array_unshift( $entries, $clean );
		$entries = array_slice( $entries, 0, self::MAX );
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $entries, '', false );
		} else {
			update_option( self::OPTION, $entries, false );
		}
	}

	/** Entradas guardadas, la más reciente primero. */
	public static function entries(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_filter( $raw, 'is_array' ) );
	}

	/** Una página de la bitácora, con el total. */
	public static function page( int $page, int $per_page ): array {
		$entries = self::entries();
		$total   = count( $entries );
		$per     = max( 1, $per_page );
		return array(
			'data'        => array_slice( $entries, ( max( 1, $page ) - 1 ) * $per, $per ),
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per ),
			'page'        => max( 1, $page ),
			'per_page'    => $per,
		);
	}

	private static function token( $value ): string {
		return is_string( $value ) ? substr( (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ), 0, 40 ) : '';
	}

	/** Un ID de la ruta: entero, o una clave validada por el enrutador; nada más. */
	private static function ref( $value ) {
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) && 1 === preg_match( Tools::SLUG_PATTERN, $value ) ) {
			return $value;
		}
		return null;
	}
}
