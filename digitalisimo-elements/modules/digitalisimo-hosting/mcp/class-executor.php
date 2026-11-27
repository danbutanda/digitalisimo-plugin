<?php
namespace Digitalisimo\Elements\Hosting\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Ejecuta una herramienta de Hosting: permiso (también aquí, no sólo en `WP_Ability`), la API de DIGITALÍSIMO Hosting y
 * la bitácora de escrituras. La bitácora (`LOG`, opción del sitio sin carga automática, las últimas `LOG_MAX`) lleva
 * fecha UTC, ID del usuario, herramienta, acción, los NOMBRES de las claves cambiadas o el ID del producto, el resultado
 * y su código: nunca valores, secretos ni datos personales.
 */
final class Executor {
	const LOG     = 'digitalisimo_elements_hosting_mcp_log';
	const LOG_MAX = 200;

	/** @return true|\WP_Error */
	public static function permission( string $tool, $input = null ) {
		$caps = Tools::capabilities( $tool, is_array( $input ) && isset( $input['action'] ) && is_string( $input['action'] ) ? $input['action'] : '' );
		foreach ( $caps as $cap ) {
			if ( current_user_can( $cap ) ) {
				return true;
			}
		}
		return new \WP_Error(
			'digitalisimo_hosting_mcp_forbidden',
			sprintf( 'Tu usuario no tiene permiso para esto: hace falta la capacidad «%s».', implode( '» o «', $caps ) ),
			array( 'status' => 403 )
		);
	}

	/** @return array|\WP_Error */
	public static function run( string $tool, $input ) {
		$input  = is_array( $input ) ? $input : array();
		$action = isset( $input['action'] ) && is_string( $input['action'] ) ? $input['action'] : '';
		if ( ! in_array( $tool, Tools::TOOLS, true ) || ! in_array( $action, Tools::ACTIONS[ $tool ], true ) ) {
			return new \WP_Error( 'digitalisimo_hosting_mcp_action', 'Acción no válida. Usa: ' . ( isset( Tools::ACTIONS[ $tool ] ) ? implode( ', ', Tools::ACTIONS[ $tool ] ) : '' ) . '.', array( 'status' => 400 ) );
		}
		$extra = array_diff( array_keys( $input ), array_keys( Tools::input_schema( $tool )['properties'] ) );
		if ( $extra ) {
			return new \WP_Error( 'digitalisimo_hosting_mcp_input', 'Parámetros desconocidos: ' . implode( ', ', $extra ) . '.', array( 'status' => 400 ) );
		}
		$write   = Tools::is_write( $action );
		$allowed = self::permission( $tool, $input );
		if ( true !== $allowed ) {
			if ( $write ) {
				self::log( $tool, $action, array(), 0, 'denied', 'forbidden' );
			}
			return $allowed;
		}
		if ( ! Tools::hosting_ready() ) {
			return new \WP_Error( 'digitalisimo_hosting_mcp_inactive', 'DIGITALÍSIMO Hosting no está activo en este sitio (o es una versión sin estas herramientas): actívalo o actualízalo.', array( 'status' => 503 ) );
		}

		if ( 'settings' === $tool ) {
			if ( 'get' === $action ) {
				return \Digitalisimo_Hosting::mcp_settings();
			}
			if ( 'log' === $action ) {
				return self::log_page( (int) ( $input['page'] ?? 1 ), (int) ( $input['per_page'] ?? 20 ) );
			}
			if ( 'manual-payment' === $action ) {
				$service = isset( $input['service'] ) ? (int) $input['service'] : 0;
				$result  = \Digitalisimo_Hosting_Provisioning::manual_payment(
					$service,
					array(
						'months'       => (int) ( $input['months'] ?? 0 ),
						'method'       => isset( $input['method'] ) && is_string( $input['method'] ) ? $input['method'] : '',
						'amount'       => $input['amount'] ?? '',
						'note'         => isset( $input['note'] ) && is_string( $input['note'] ) ? $input['note'] : '',
						// Sin decirlo: lo decide Hosting (un servicio que es sólo el dominio lo renueva por omisión).
						'renew_domain'      => array_key_exists( 'renew_domain', $input ) ? ! empty( $input['renew_domain'] ) : null,
						'renewed_elsewhere' => ! empty( $input['renewed_elsewhere'] ),
						'key'          => isset( $input['request_id'] ) && is_string( $input['request_id'] ) ? $input['request_id'] : '',
					)
				);
				// Medio, periodo y monto como nombres (la bitácora no guarda textos libres ni datos personales).
				$keys = is_wp_error( $result ) ? array( 'manual_payment' ) : array( 'manual_payment', 'method_' . $result['method'], 'months_' . (int) $result['months'], null === $result['amount'] ? 'amount_none' : 'amount_' . str_replace( '.', '_', number_format( (float) $result['amount'], 2, '.', '' ) ), 'domain_' . $result['domain_renewal'] );
				self::log( $tool, $action, $keys, $service, is_wp_error( $result ) ? 'error' : ( ! empty( $result['duplicate'] ) ? 'duplicate' : 'ok' ), is_wp_error( $result ) ? $result->get_error_code() : '' );
				return $result;
			}
			$data   = $input['data'] ?? null;
			$result = \Digitalisimo_Hosting::mcp_update( is_object( $data ) ? (array) $data : $data );
			if ( is_wp_error( $result ) ) {
				self::log( $tool, $action, is_array( $data ) ? array_keys( $data ) : array(), 0, 'error', $result->get_error_code() );
				return $result;
			}
			self::log( $tool, $action, (array) ( $result['changed'] ?? array() ), 0, 'ok', '' );
			return $result;
		}

		if ( 'list' === $action ) {
			return array( 'ok' => true ) + \Digitalisimo_Hosting_Products::state();
		}
		$product = isset( $input['product_id'] ) ? (int) $input['product_id'] : 0;
		if ( $product < 1 ) {
			return new \WP_Error( 'digitalisimo_hosting_mcp_input', 'Falta product_id.', array( 'status' => 400 ) );
		}
		if ( 'mark' === $action && ( ! isset( $input['tld'] ) || ! is_string( $input['tld'] ) ) && 'transfer' !== ( $input['kind'] ?? '' ) ) {
			return new \WP_Error( 'digitalisimo_hosting_mcp_input', 'Falta tld (por ejemplo ".com").', array( 'status' => 400 ) );
		}
		$result = 'mark' === $action
			? \Digitalisimo_Hosting_Products::mark( $product, isset( $input['tld'] ) ? (string) $input['tld'] : '', 'transfer' === ( $input['kind'] ?? '' ) ? 'transfer' : 'register' )
			: \Digitalisimo_Hosting_Products::mark( $product, '', 'register' );
		self::log( $tool, $action, array(), $product, is_wp_error( $result ) ? 'error' : 'ok', is_wp_error( $result ) ? $result->get_error_code() : '' );
		return $result;
	}

	/** Una entrada de la bitácora: sólo nombres de claves válidos, nunca valores. */
	public static function log( string $tool, string $action, array $keys, int $product, string $result, string $code ): void {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}
		$clean = array();
		foreach ( $keys as $key ) {
			if ( is_string( $key ) && preg_match( '/^[a-z0-9_]{1,40}$/', $key ) ) {
				$clean[] = $key;
			}
		}
		$entry   = array(
			'time'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'user'    => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			'tool'    => $tool,
			'action'  => $action,
			'keys'    => array_slice( $clean, 0, 40 ),
			'product' => max( 0, $product ),
			'result'  => in_array( $result, array( 'ok', 'error', 'denied', 'duplicate' ), true ) ? $result : 'error',
			'code'    => substr( (string) preg_replace( '/[^a-z0-9_]/', '', strtolower( $code ) ), 0, 80 ),
		);
		$entries = get_option( self::LOG, array() );
		$entries = is_array( $entries ) ? $entries : array();
		array_unshift( $entries, $entry );
		$entries = array_slice( $entries, 0, self::LOG_MAX );
		if ( false === get_option( self::LOG, false ) ) {
			add_option( self::LOG, $entries, '', false );
		} else {
			update_option( self::LOG, $entries, false );
		}
	}

	private static function log_page( int $page, int $per_page ): array {
		$entries = get_option( self::LOG, array() );
		$entries = is_array( $entries ) ? array_values( $entries ) : array();
		$per     = max( 1, min( 100, $per_page ) );
		$page    = max( 1, $page );
		return array(
			'ok'          => true,
			'data'        => array_slice( $entries, ( $page - 1 ) * $per, $per ),
			'total'       => count( $entries ),
			'page'        => $page,
			'per_page'    => $per,
			'total_pages' => (int) ceil( count( $entries ) / $per ),
		);
	}
}
