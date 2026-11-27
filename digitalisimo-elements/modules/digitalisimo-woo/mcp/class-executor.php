<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Ejecuta una herramienta de tienda: enrutador, permiso (también aquí, no sólo en `WP_Ability`), secretos, una
 * petición interna a la API REST v3 de WooCommerce (`rest_do_request()`, como el usuario autenticado del MCP:
 * WooCommerce valida, comprueba permisos objeto a objeto y dispara sus ganchos; con HPOS también) y la bitácora de las
 * escrituras.
 *
 * Sin modos ni interruptores (decisión del dueño del 2026-11-27): toda herramienta lee y escribe; lo que puede hacer
 * cada usuario lo deciden sus capacidades de WooCommerce, aquí y en la REST de WooCommerce, objeto a objeto.
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
		if ( Tools::SKYDROPX === $tool ) {
			// Otra forma (acciones propias, no una tabla de rutas), las mismas reglas: modo, permiso y bitácora.
			return Skydropx::run( $input, $dispatch );
		}
		$dispatch = is_callable( $dispatch ) ? $dispatch : array( __CLASS__, 'rest' );
		$plan     = Router::resolve( $tool, $input );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( 'undo' === $plan['local'] ) {
			// El recurso, el objeto y la acción inversa salen del registro de cambios, no del cliente.
			$plan = self::undo_plan( $tool, $plan );
			if ( is_wp_error( $plan ) ) {
				return $plan;
			}
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
		if ( ! empty( $plan['dry_run'] ) ) {
			// Simular no escribe: dice qué cambiaría.
			return self::simulate( $tool, $plan, $dispatch );
		}

		if ( 'undo' === $plan['local'] ) {
			return self::undo( $tool, $plan, $dispatch, $user );
		}
		if ( 'changes' === $plan['local'] ) {
			return array_merge(
				array(
					'ok'     => true,
					'tool'   => $tool,
					'action' => 'changes',
				),
				Changes::page( (int) $plan['query']['page'], (int) $plan['query']['per_page'] )
			);
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

		// Para poder deshacer un update: cómo estaba antes (sólo lo que se puede deshacer; ver `Changes`).
		$before = null;
		if ( 'write' === $plan['kind'] && Changes::wants_before( $plan ) ) {
			list( $read_status, $read ) = call_user_func( $dispatch, 'GET', $plan['route'], array(), null );
			$before                     = $read_status < 400 && is_array( $read ) ? $read : null;
		}

		list( $status, $data, $headers ) = call_user_func( $dispatch, $plan['method'], $plan['route'], $plan['query'], $plan['body'] );
		if ( $status >= 400 ) {
			$error = self::woo_error( (int) $status, $data );
			if ( 'write' === $plan['kind'] ) {
				Log::add( Log::entry( $plan, 'error', $error->get_error_code(), (int) $status, $user ) );
			}
			return $error;
		}
		// Un lote responde 200 aunque fallen elementos: cada fallo viene dentro, con su `error`.
		$batch = null;
		if ( 'batch' === $plan['action'] ) {
			list( $data, $batch ) = self::batch_summary( $data );
			if ( $batch['failed'] > 0 && 0 === $batch['done'] ) {
				$first = $batch['errors'][0];
				Log::add( Log::entry( $plan, 'error', $first['code'], (int) $status, $user ) );
				return new \WP_Error(
					'' !== $first['code'] ? $first['code'] : 'digitalisimo_woo_mcp_batch_failed',
					sprintf( 'WooCommerce no aplicó ningún elemento del lote (%d con error). El primero (%s, posición %d): %s', $batch['failed'], $first['list'], $first['index'], $first['message'] ),
					array( 'status' => 400 )
				);
			}
		}
		$change = null;
		if ( 'write' === $plan['kind'] ) {
			$failed = $batch && $batch['failed'] > 0;
			Log::add( Log::entry( $plan, $failed ? 'partial' : 'ok', $failed ? $batch['errors'][0]['code'] : '', (int) $status, $user ) );
			$change = Changes::record( $plan, $user, $before, $data );
		}

		if ( 'store-settings' === $tool ) {
			$data = Secrets::mask( $data );
		}
		$result = array(
			'ok'       => ! ( $batch && $batch['failed'] > 0 ),
			'tool'     => $tool,
			'action'   => $plan['action'],
			'resource' => $plan['resource'],
			'status'   => (int) $status,
			'data'     => $batch ? self::pick_batch_fields( $data, $plan['fields'] ) : self::pick_fields( $data, $plan['fields'], 'list' === $plan['action'] || 'reports' === $tool ),
		);
		if ( $batch ) {
			$result['batch'] = $batch;
			if ( $batch['failed'] > 0 ) {
				$result['partial'] = true;
				$result['notice']  = sprintf( 'WooCommerce aplicó %d de %d elementos del lote; los errores están en batch.errors.', $batch['done'], $batch['done'] + $batch['failed'] );
			}
		}
		if ( 'list' === $plan['action'] && ! isset( $plan['query']['page'] ) && 'reports' !== $tool && is_array( $data ) ) {
			// Lista que WooCommerce no pagina: todo en una respuesta, sin page, per_page ni total_pages.
			$result['total'] = count( $data );
		}
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
		if ( $change ) {
			// Registro de cambios: su id (para undo) y si se puede deshacer, o por qué no.
			$result['change'] = $change;
		}
		return $result;
	}

	/**
	 * El plan de un undo con lo que dice el registro de cambios: recurso, objeto, padre y la acción inversa
	 * (`undo_as`: update para un update, delete para un create o una papelera), que decide la capacidad.
	 *
	 * @return array|\WP_Error
	 */
	private static function undo_plan( string $tool, array $plan ) {
		$entry = Changes::get( (string) $plan['change_id'] );
		if ( null === $entry || $tool !== $entry['tool'] ) {
			return new \WP_Error( 'digitalisimo_woo_mcp_undo_unknown', 'No hay un cambio con ese change_id en esta herramienta (el registro guarda los últimos ' . Changes::MAX . ' de ' . Changes::DAYS . ' días; ver reports → changes).', array( 'status' => 404 ) );
		}
		$plan['resource'] = (string) $entry['resource'];
		$plan['id']       = is_int( $entry['item'] ) ? $entry['item'] : null;
		$plan['parent']   = is_int( $entry['parent'] ) ? $entry['parent'] : null;
		$plan['undo_as']  = 'update' === $entry['action'] ? 'update' : 'delete';
		$plan['entry']    = $entry;
		return $plan;
	}

	/** Deshace un cambio del registro (o lo simula con dry_run), con la bitácora. */
	private static function undo( string $tool, array $plan, $dispatch, int $user ) {
		$done = Changes::undo( $plan['entry'], $dispatch, false );
		if ( is_wp_error( $done ) ) {
			$data = $done->get_error_data();
			Log::add( Log::entry( $plan, 'error', $done->get_error_code(), is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500, $user ) );
			return $done;
		}
		Changes::mark_undone( (string) $plan['change_id'], $user );
		Log::add( Log::entry( $plan, 'ok', '', (int) $done['status'], $user ) );
		return array(
			'ok'        => true,
			'tool'      => $tool,
			'action'    => 'undo',
			'resource'  => $plan['resource'],
			'change_id' => $plan['change_id'],
			'status'    => (int) $done['status'],
			'undone'    => $done['would'],
			'data'      => self::pick_fields( $done['data'], $plan['fields'], false ),
		);
	}

	/**
	 * `dry_run: true`: lo que cambiaría (`Simulation::would()`, sólo lecturas). `allowed` es siempre true: sin modos, lo
	 * que decide es la capacidad, que ya se comprobó. No escribe nada ni deja entrada en la bitácora de escrituras.
	 *
	 * @return array|\WP_Error
	 */
	private static function simulate( string $tool, array $plan, $dispatch ) {
		if ( 'undo' === $plan['local'] ) {
			$undo = Changes::undo( $plan['entry'], $dispatch, true );
			if ( is_wp_error( $undo ) && in_array( $undo->get_error_code(), array( 'digitalisimo_woo_mcp_undo_conflict', 'digitalisimo_woo_mcp_undo_unavailable', 'digitalisimo_woo_mcp_undo_done' ), true ) ) {
				// La simulación dice por qué no se desharía, sin tratarlo como un error.
				return array(
					'ok'       => true,
					'tool'     => $tool,
					'action'   => 'undo',
					'resource' => $plan['resource'],
					'dry_run'  => true,
					'allowed'  => false,
					'would'    => null,
					'reason'   => $undo->get_error_message(),
					'notice'   => 'Simulación: no se cambió nada. Este cambio no se desharía (ver reason).',
				);
			}
			$would = is_wp_error( $undo ) ? $undo : $undo['would'];
		} else {
			$would = Simulation::would( $tool, $plan, $dispatch );
		}
		if ( is_wp_error( $would ) ) {
			return $would;
		}
		return array(
			'ok'       => true,
			'tool'     => $tool,
			'action'   => $plan['action'],
			'resource' => $plan['resource'],
			'dry_run'  => true,
			'allowed'  => true,
			'would'    => $would,
			'notice'   => 'Simulación: no se cambió nada.',
		);
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
		if ( Tools::SKYDROPX === $tool ) {
			return Skydropx_Tool::permission( $input );
		}
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
		if ( 'undo' === $plan['local'] ) {
			// La capacidad de la acción inversa sobre el recurso del cambio; un change_id desconocido lo rechaza run().
			$plan = self::undo_plan( $tool, $plan );
			if ( is_wp_error( $plan ) ) {
				return true;
			}
		}
		$cap = Tools::capability( $tool, $plan['resource'], isset( $plan['undo_as'] ) ? $plan['undo_as'] : $plan['action'] );
		if ( ! current_user_can( $cap ) ) {
			return self::forbidden( $cap );
		}
		return true;
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

	/** Error de WooCommerce con su código y su mensaje. */
	public static function woo_error( int $status, $data ): \WP_Error {
		$code    = is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : 'digitalisimo_woo_mcp_woocommerce';
		$message = is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) && '' !== $data['message'] ? $data['message'] : 'WooCommerce rechazó la petición.';
		return new \WP_Error( $code, 'WooCommerce: ' . $message, array( 'status' => $status ) );
	}

	private static function status_of( \WP_Error $error ): int {
		$data = $error->get_error_data();
		return is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
	}

	/**
	 * Recuento de un lote: elementos hechos y con error (create, update, delete), y cada error con su lista, su posición,
	 * el ID que trae, el código y el mensaje. Del error se quitan los datos de WooCommerce (pueden repetir lo enviado).
	 *
	 * @return array{0:mixed,1:array{done:int,failed:int,errors:array}}
	 */
	public static function batch_summary( $data ): array {
		$summary = array(
			'done'   => 0,
			'failed' => 0,
			'errors' => array(),
		);
		if ( ! is_array( $data ) ) {
			return array( $data, $summary );
		}
		foreach ( array( 'create', 'update', 'delete' ) as $list ) {
			if ( ! isset( $data[ $list ] ) || ! is_array( $data[ $list ] ) ) {
				continue;
			}
			foreach ( $data[ $list ] as $index => $item ) {
				if ( is_array( $item ) && isset( $item['error'] ) ) {
					$error                          = is_array( $item['error'] ) ? $item['error'] : array();
					$code                           = isset( $error['code'] ) && is_string( $error['code'] ) ? $error['code'] : 'digitalisimo_woo_mcp_batch_item';
					$message                        = isset( $error['message'] ) && is_string( $error['message'] ) ? $error['message'] : '';
					$data[ $list ][ $index ]['error'] = array(
						'code'    => $code,
						'message' => $message,
					);
					++$summary['failed'];
					$summary['errors'][] = array(
						'list'    => $list,
						'index'   => (int) $index,
						'id'      => isset( $item['id'] ) && is_numeric( $item['id'] ) ? (int) $item['id'] : 0,
						'code'    => $code,
						'message' => $message,
					);
				} else {
					++$summary['done'];
				}
			}
		}
		return array( $data, $summary );
	}

	/** Campos pedidos de cada elemento de cada lista de un lote (siempre con `id` y, si lo trae, su `error`). */
	public static function pick_batch_fields( $data, array $fields ) {
		if ( ! $fields || ! is_array( $data ) ) {
			return $data;
		}
		$keep = array_flip( array_merge( array( 'id', 'error' ), $fields ) );
		foreach ( array( 'create', 'update', 'delete' ) as $list ) {
			if ( isset( $data[ $list ] ) && is_array( $data[ $list ] ) ) {
				foreach ( $data[ $list ] as $i => $item ) {
					$data[ $list ][ $i ] = is_array( $item ) ? array_intersect_key( $item, $keep ) : $item;
				}
			}
		}
		return $data;
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
	public static function header( $headers, string $name ) {
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
