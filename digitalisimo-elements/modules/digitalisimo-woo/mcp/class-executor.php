<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Ejecuta una herramienta de tienda: enrutador, modo, permiso (también aquí, no sólo en `WP_Ability`), secretos, una
 * petición interna a la API REST v3 de WooCommerce (`rest_do_request()`, como el usuario autenticado del MCP:
 * WooCommerce valida, comprueba permisos objeto a objeto y dispara sus ganchos; con HPOS también) y la bitácora de las
 * escrituras.
 *
 * Nada de SQL propio ni de clases internas de WooCommerce; sin telemetría. Un error de WooCommerce vuelve como
 * `WP_Error` con su código y su mensaje, sin sus datos (pueden repetir lo enviado).
 */
final class Executor {
	/**
	 * @param string        $tool     Herramienta.
	 * @param mixed         $input    Entrada del cliente.
	 * @param callable|null $dispatch Para las pruebas: `function( $method, $route, $query, $body )` que devuelve
	 *                                `array( status, data, headers )`; por defecto, `rest_do_request()`.
	 * @return array|\WP_Error
	 */
	public static function run( string $tool, $input, $dispatch = null ) {
		$dispatch = is_callable( $dispatch ) ? $dispatch : array( __CLASS__, 'rest' );
		$plan     = Router::resolve( $tool, $input );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$mode = Settings::mode();

		if ( 'off' === $mode ) {
			// Apagado no registra las herramientas; si una llega a ejecutarse (el modo cambió a mitad), no hace nada.
			return new \WP_Error( 'digitalisimo_woo_mcp_off', 'Las herramientas de tienda están apagadas en este sitio («WooCommerce en el MCP»: apagado).', array( 'status' => 403 ) );
		}
		// El permiso, también aquí: no depende de que quien llama haya pasado por WP_Ability::check_permissions().
		$allowed = self::permission( $tool, $input );
		if ( true !== $allowed ) {
			$error = is_wp_error( $allowed ) ? $allowed : self::forbidden( Tools::floor( $tool ) );
			if ( 'write' === $plan['kind'] ) {
				Log::add( Log::entry( $plan, 'denied', 'forbidden', 403, $user ) );
			}
			return $error;
		}
		if ( 'write' === $plan['kind'] && 'write' !== $mode ) {
			Log::add( Log::entry( $plan, 'denied', 'read_mode', 403, $user ) );
			return self::read_mode_error( $plan );
		}

		if ( 'log' === $plan['local'] ) {
			$page = Log::page( (int) $plan['query']['page'], (int) $plan['query']['per_page'] );
			return array_merge(
				array(
					'ok'     => true,
					'tool'   => $tool,
					'action' => 'log',
				),
				$page
			);
		}

		$kept = 0;
		if ( 'write' === $plan['kind'] && '' !== $plan['secrets'] && is_array( $plan['body'] ) && in_array( $plan['action'], array( 'update', 'batch' ), true ) ) {
			$protected = self::protect_secrets( $plan, $dispatch );
			if ( is_wp_error( $protected ) ) {
				Log::add( Log::entry( $plan, 'error', $protected->get_error_code(), self::status_of( $protected ), $user ) );
				return $protected;
			}
			list( $plan, $kept, $nothing ) = $protected;
			if ( $nothing ) {
				Log::add( Log::entry( $plan, 'ok', 'secret_kept', 200, $user ) );
				return array(
					'ok'       => true,
					'tool'     => $tool,
					'action'   => $plan['action'],
					'resource' => $plan['resource'],
					'status'   => 200,
					'data'     => null,
					'notice'   => 'Nada que cambiar: un valor vacío o enmascarado conserva la clave guardada.',
				);
			}
		}

		list( $status, $data, $headers ) = call_user_func( $dispatch, $plan['method'], $plan['route'], $plan['query'], $plan['body'] );
		if ( $status >= 400 ) {
			$error = self::woo_error( (int) $status, $data );
			if ( 'write' === $plan['kind'] ) {
				Log::add( Log::entry( $plan, 'error', $error->get_error_code(), (int) $status, $user ) );
			}
			return $error;
		}
		if ( 'write' === $plan['kind'] ) {
			Log::add( Log::entry( $plan, 'ok', '', (int) $status, $user ) );
		}

		if ( 'store-settings' === $tool ) {
			$data = Secrets::mask( $data );
		}
		$result = array(
			'ok'       => true,
			'tool'     => $tool,
			'action'   => $plan['action'],
			'resource' => $plan['resource'],
			'status'   => (int) $status,
			'data'     => self::pick_fields( $data, $plan['fields'], 'list' === $plan['action'] || 'reports' === $tool ),
		);
		if ( 'list' === $plan['action'] && isset( $plan['query']['page'], $plan['query']['per_page'] ) ) {
			$result['page']     = (int) $plan['query']['page'];
			$result['per_page'] = (int) $plan['query']['per_page'];
			$total              = self::header( $headers, 'X-WP-Total' );
			$pages              = self::header( $headers, 'X-WP-TotalPages' );
			if ( null !== $total ) {
				$result['total'] = $total;
			}
			if ( null !== $pages ) {
				$result['total_pages'] = $pages;
			}
		}
		if ( 'delete' === $plan['action'] ) {
			$result['deleted'] = ! empty( $plan['query']['force'] ) ? 'permanent' : 'trash';
		}
		if ( $kept ) {
			$result['notice'] = sprintf( 'Se conservaron %d claves guardadas: venían vacías o enmascaradas.', $kept );
		}
		return $result;
	}

	/**
	 * Permiso de una herramienta (`permission_callback`): el mínimo de la herramienta y, con una entrada que el enrutador
	 * entiende, la capacidad que la REST de WooCommerce exige para ese recurso y esa acción. Sin entrada (lista de
	 * herramientas), sólo el mínimo. Una entrada que el enrutador rechaza pasa aquí y la rechaza `run()` con su motivo.
	 * WooCommerce vuelve a comprobar, objeto a objeto, al ejecutar.
	 *
	 * @return true|\WP_Error
	 */
	public static function permission( string $tool, $input = null ) {
		$floor = Tools::floor( $tool );
		if ( ! current_user_can( $floor ) ) {
			return self::forbidden( $floor );
		}
		if ( ! is_array( $input ) || ! isset( $input['action'] ) ) {
			return true;
		}
		$plan = Router::resolve( $tool, $input );
		if ( is_wp_error( $plan ) ) {
			return true;
		}
		$cap = Tools::capability( $tool, $plan['resource'], $plan['action'] );
		return current_user_can( $cap ) ? true : self::forbidden( $cap );
	}

	private static function forbidden( string $cap ): \WP_Error {
		return new \WP_Error(
			'digitalisimo_woo_mcp_forbidden',
			sprintf( 'Tu usuario no tiene permiso para esto en la tienda: WooCommerce exige la capacidad «%s».', $cap ),
			array( 'status' => 403 )
		);
	}

	/** Petición interna a WooCommerce. @return array{0:int,1:mixed,2:array} */
	public static function rest( string $method, string $route, array $query, $body ): array {
		$request = new \WP_REST_Request( $method, $route );
		if ( $query ) {
			$request->set_query_params( $query );
		}
		if ( null !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}
		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			$error = $response->as_error();
			$data  = $error->get_error_data();
			return array(
				(int) ( is_array( $data ) && isset( $data['status'] ) ? $data['status'] : 500 ),
				array(
					'code'    => $error->get_error_code(),
					'message' => $error->get_error_message(),
				),
				$response->get_headers(),
			);
		}
		return array( (int) $response->get_status(), $response->get_data(), $response->get_headers() );
	}

	/**
	 * Antes de cambiar una pasarela, un método de envío u opciones de ajustes: lee lo guardado y quita del envío los
	 * secretos que llegan vacíos o enmascarados (se conservan). Devuelve [plan, conservados, nada que enviar].
	 */
	private static function protect_secrets( array $plan, $dispatch ) {
		if ( 'option' === $plan['secrets'] ) {
			// Una opción: la de la ruta; un lote: el grupo entero.
			$route = 'batch' === $plan['action'] ? substr( $plan['route'], 0, -strlen( '/batch' ) ) : $plan['route'];
			$kind  = 'batch' === $plan['action'] ? 'option-batch' : 'option';
		} else {
			$route = $plan['route'];
			$kind  = 'map';
		}
		list( $status, $current ) = call_user_func( $dispatch, 'GET', $route, array(), null );
		if ( $status >= 400 ) {
			return self::woo_error( (int) $status, $current );
		}
		list( $body, $kept ) = Secrets::strip_unchanged( $plan['body'], Secrets::secret_ids( $current ), $kind );
		$plan['body'] = $body;
		$nothing      = ( 'option' === $kind && ! array_key_exists( 'value', $body ) )
			|| ( 'option-batch' === $kind && empty( $body['update'] ) )
			|| ( 'map' === $kind && ! $body );
		return array( $plan, $kept, $nothing );
	}

	/** Error de «sólo lectura» que dice dónde se cambia el modo. */
	public static function read_mode_error( array $plan ): \WP_Error {
		$where = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=' . Settings_Section::FRONT_PAGE . '#' . Settings_Section::ANCHOR ) : '';
		return new \WP_Error(
			'digitalisimo_woo_mcp_read_only',
			sprintf(
				'Este sitio tiene «WooCommerce en el MCP» en «Sólo lectura»: %s en «%s» no se hizo. Un administrador lo cambia a «Lectura y escritura» en la pantalla «Rendimiento» de DIGITALÍSIMO Elements, sección «WooCommerce en el MCP»%s; en una red, si el sitio hereda el modo, también en esa pantalla de la administración de la red.',
				$plan['action'],
				$plan['resource'],
				'' !== $where ? ' (' . $where . ')' : ''
			),
			array( 'status' => 403 )
		);
	}

	/** Error de WooCommerce con su código y su mensaje. */
	private static function woo_error( int $status, $data ): \WP_Error {
		$code    = is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : 'digitalisimo_woo_mcp_woocommerce';
		$message = is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) && '' !== $data['message'] ? $data['message'] : 'WooCommerce rechazó la petición.';
		return new \WP_Error( $code, 'WooCommerce: ' . $message, array( 'status' => $status ) );
	}

	private static function status_of( \WP_Error $error ): int {
		$data = $error->get_error_data();
		return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
	}

	/** Campos pedidos de un objeto o de cada objeto de una lista (siempre con `id`). */
	public static function pick_fields( $data, array $fields, bool $list ) {
		if ( ! $fields || ! is_array( $data ) ) {
			return $data;
		}
		$keep = array_flip( array_merge( array( 'id' ), $fields ) );
		if ( $list && array_keys( $data ) === range( 0, count( $data ) - 1 ) ) {
			foreach ( $data as $i => $item ) {
				$data[ $i ] = is_array( $item ) ? array_intersect_key( $item, $keep ) : $item;
			}
			return $data;
		}
		return array_intersect_key( $data, $keep );
	}

	/** Cabecera numérica de la respuesta (sin distinguir mayúsculas), o null. */
	private static function header( $headers, string $name ) {
		if ( ! is_array( $headers ) ) {
			return null;
		}
		foreach ( $headers as $key => $value ) {
			if ( 0 === strcasecmp( (string) $key, $name ) && is_numeric( $value ) ) {
				return (int) $value;
			}
		}
		return null;
	}
}
