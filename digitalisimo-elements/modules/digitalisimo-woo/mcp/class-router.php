<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * De la entrada de una herramienta a una petición a la API REST v3 de WooCommerce (puro: sólo `WP_Error`).
 *
 * La ruta y el método salen únicamente de `Tools::RESOURCES` y `Tools::REPORTS`: la entrada sólo elige la acción y el
 * recurso de sus enums y aporta IDs, que se validan aquí (enteros ≥ su mínimo, o texto con `Tools::SLUG_PATTERN`). Un ID
 * que no es de la ruta pedida, una acción que el recurso no admite o un parámetro que no aplica se rechazan con un
 * error que dice qué hacer. De `data` y `query` se quitan los parámetros de la ruta (los rellena WordPress desde la URL,
 * pero el cuerpo y la consulta les ganarían: `get_parameter_order()`), `force`, `api_refund` y todo lo que empieza por
 * `_` (`_method`, `_fields`, `_embed`…); `force` y `api_refund` sólo valen como parámetros de primer nivel.
 *
 * Guardas: `delete` sin `force` en un recurso sin papelera se rechaza antes de llamar a WooCommerce; un lote con
 * `delete` (WooCommerce lo borra sin papelera) exige `force:true`; un reembolso nuevo lleva siempre `api_refund`
 * (false salvo que el cliente pida true).
 *
 * Devuelve el plan: `tool`, `action`, `resource`, `method`, `route`, `query`, `body` (o null), `kind` (`read`/`write`),
 * `destructive`, `id` y `parent` (para la bitácora), `items` (elementos de un lote), `fields`, `secrets` y `local`
 * (`log` para la bitácora, sin REST).
 */
final class Router {
	/**
	 * @param string $tool  Herramienta (`products`…).
	 * @param mixed  $input Entrada del cliente.
	 * @return array|\WP_Error
	 */
	public static function resolve( string $tool, $input ) {
		if ( ! isset( Tools::TOOLS[ $tool ] ) ) {
			return self::error( 'digitalisimo_woo_mcp_unknown_tool', 'Herramienta desconocida.' );
		}
		if ( ! is_array( $input ) ) {
			return self::error( 'digitalisimo_woo_mcp_input', 'La entrada debe ser un objeto con action.' );
		}
		$config  = Tools::TOOLS[ $tool ];
		// Las mismas claves que el esquema de entrada (cerrado): lo que el esquema no declara no llega a WooCommerce.
		$schema  = Tools::input_schema( $tool );
		$known   = array_keys( $schema['properties'] );
		$unknown = array_diff( array_map( 'strval', array_keys( $input ) ), $known );
		if ( $unknown ) {
			return self::error( 'digitalisimo_woo_mcp_input', 'Parámetros que esta herramienta no admite: ' . implode( ', ', $unknown ) . '.' );
		}
		$action = isset( $input['action'] ) && is_string( $input['action'] ) ? $input['action'] : '';
		if ( ! in_array( $action, $config['actions'], true ) ) {
			return self::error( 'digitalisimo_woo_mcp_action', 'action debe ser una de: ' . implode( ', ', $config['actions'] ) . '.' );
		}
		$fields = self::fields( $input );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		if ( 'reports' === $tool ) {
			return self::report( $action, $input, $fields );
		}

		$resource = $config['resources'][0];
		if ( array_key_exists( 'resource', $input ) ) {
			if ( ! is_string( $input['resource'] ) || ! in_array( $input['resource'], $config['resources'], true ) ) {
				return self::error( 'digitalisimo_woo_mcp_resource', 'resource debe ser uno de: ' . implode( ', ', $config['resources'] ) . '.' );
			}
			$resource = $input['resource'];
		}
		$def = Tools::RESOURCES[ $resource ];
		if ( ! in_array( $action, $def['actions'], true ) ) {
			return self::error( 'digitalisimo_woo_mcp_action', sprintf( 'El recurso «%s» sólo admite: %s.', $resource, implode( ', ', $def['actions'] ) ) );
		}

		// IDs: los de los padres siempre; el del elemento en get, update y delete (salvo una acción de colección).
		$collection = in_array( $action, isset( $def['collection'] ) ? $def['collection'] : array(), true );
		$needs_item = ! $collection && in_array( $action, array( 'get', 'update', 'delete' ), true ) && ! empty( $def['item'] );
		$expected   = array_values( $def['parents'] );
		if ( $needs_item ) {
			$expected[] = $def['item'][1];
		}
		foreach ( $config['ids'] as $key ) {
			if ( array_key_exists( $key, $input ) && ! in_array( $key, $expected, true ) ) {
				return self::error( 'digitalisimo_woo_mcp_id', sprintf( '%s no se usa con %s en el recurso «%s».', $key, $action, $resource ) );
			}
		}
		$ids = array();
		foreach ( $expected as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				return self::error( 'digitalisimo_woo_mcp_id', sprintf( 'Falta %s para %s en el recurso «%s».', $key, $action, $resource ) );
			}
			$value = self::id_value( $key, $input[ $key ] );
			if ( null === $value ) {
				return self::error( 'digitalisimo_woo_mcp_id', isset( Tools::INT_IDS[ $key ] )
					? sprintf( '%s debe ser un número entero igual o mayor que %d.', $key, Tools::INT_IDS[ $key ] )
					: sprintf( '%s sólo admite letras, números, guion y guion bajo.', $key ) );
			}
			$ids[ $key ] = $value;
		}

		// Ruta: plantilla de la tabla con los IDs validados.
		$path = $def['path'];
		foreach ( $def['parents'] as $placeholder => $key ) {
			$path = str_replace( '{' . $placeholder . '}', (string) $ids[ $key ], $path );
		}
		if ( $needs_item ) {
			$path .= '/' . $ids[ $def['item'][1] ];
		}
		if ( 'batch' === $action ) {
			$path .= '/batch';
		}
		$reserved = array_keys( $def['parents'] );
		if ( ! empty( $def['item'] ) ) {
			$reserved[] = $def['item'][0];
		}

		$force = self::flag( $input, 'force' );
		if ( is_wp_error( $force ) ) {
			return $force;
		}
		$api_refund = self::flag( $input, 'api_refund' );
		if ( is_wp_error( $api_refund ) ) {
			return $api_refund;
		}
		if ( $force && ! in_array( $action, array( 'delete', 'batch' ), true ) ) {
			return self::error( 'digitalisimo_woo_mcp_force', 'force sólo se usa con delete o con un batch que borra.' );
		}
		// Un valor por defecto (force o api_refund en false, page y per_page) que el cliente rellena se ignora fuera de
		// su acción; true fuera de ella es un error, para que nunca se crea que se pidió algo que no ocurrirá.
		if ( $api_refund && ! ( 'refund' === $resource && 'create' === $action ) ) {
			return self::error( 'digitalisimo_woo_mcp_api_refund', 'api_refund sólo se usa al crear un reembolso (resource "refund", action "create").' );
		}
		if ( array_key_exists( 'reassign', $input ) && ! ( 'customer' === $resource && 'delete' === $action ) ) {
			return self::error( 'digitalisimo_woo_mcp_reassign', 'reassign sólo se usa al borrar un cliente.' );
		}

		$plan = array(
			'tool'        => $tool,
			'action'      => $action,
			'resource'    => $resource,
			'method'      => 'GET',
			'route'       => Tools::REST . $path,
			'query'       => array(),
			'body'        => null,
			'kind'        => Tools::is_write( $tool, $action ) ? 'write' : 'read',
			'destructive' => 'delete' === $action,
			'id'          => $needs_item ? $ids[ $def['item'][1] ] : null,
			'parent'      => $def['parents'] ? $ids[ reset( $def['parents'] ) ] : null,
			'items'       => 0,
			'fields'      => $fields,
			'secrets'     => isset( $def['secrets'] ) ? $def['secrets'] : '',
			'local'       => '',
		);

		if ( in_array( $action, array( 'list', 'get' ), true ) ) {
			$query = self::query( $input, $reserved );
			if ( is_wp_error( $query ) ) {
				return $query;
			}
			if ( array_key_exists( 'data', $input ) ) {
				return self::error( 'digitalisimo_woo_mcp_data', 'data sólo se usa con create, update o batch; los filtros van en query.' );
			}
			if ( 'list' === $action && ! empty( $def['paged'] ) ) {
				$paging = self::paging( $input );
				if ( is_wp_error( $paging ) ) {
					return $paging;
				}
				$query = array_merge( $query, $paging );
			} elseif ( 'list' === $action && array_key_exists( 'page', $input ) && 1 !== $input['page'] ) {
				// WooCommerce no pagina esta lista: una página 2 repetiría la misma lista entera.
				return self::error( 'digitalisimo_woo_mcp_page', sprintf( 'El recurso «%s» no se pagina en WooCommerce: list devuelve todos sus elementos en una sola respuesta. Quita page.', $resource ) );
			}
			$plan['query'] = $query;
			return $plan;
		}
		if ( array_key_exists( 'query', $input ) ) {
			return self::error( 'digitalisimo_woo_mcp_query', 'query sólo se usa con list o get.' );
		}

		if ( 'delete' === $action ) {
			if ( array_key_exists( 'data', $input ) ) {
				return self::error( 'digitalisimo_woo_mcp_data', 'delete no lleva data.' );
			}
			if ( ! $force && ! $def['trash'] ) {
				return self::error( 'digitalisimo_woo_mcp_no_trash', sprintf( 'WooCommerce no tiene papelera para «%s»: para borrarlo para siempre repite la acción con force:true, sólo si el usuario lo pidió.', $resource ) );
			}
			$plan['method'] = 'DELETE';
			$plan['query']  = $force ? array( 'force' => true ) : array();
			if ( array_key_exists( 'reassign', $input ) ) {
				$reassign = self::id_value( 'customer_id', $input['reassign'] );
				if ( null === $reassign ) {
					return self::error( 'digitalisimo_woo_mcp_reassign', 'reassign debe ser un número entero igual o mayor que 1.' );
				}
				$plan['query']['reassign'] = $reassign;
			}
			return $plan;
		}

		// create, update y batch: cuerpo obligatorio, sin parámetros de la ruta ni reservados.
		if ( ! array_key_exists( 'data', $input ) || ! is_array( $input['data'] ) || ( $input['data'] && self::is_list( $input['data'] ) ) ) {
			return self::error( 'digitalisimo_woo_mcp_data', sprintf( '%s necesita data: un objeto con los campos de WooCommerce.', $action ) );
		}
		$body = self::clean( $input['data'], $reserved );
		if ( 'batch' === $action ) {
			$route_params = array();
			foreach ( $def['parents'] as $placeholder => $key ) {
				$route_params[ $placeholder ] = $ids[ $key ];
			}
			$batch = self::batch( $body, 'setting' === $resource ? array( 'update' ) : array( 'create', 'update', 'delete' ), $force, $route_params );
			if ( is_wp_error( $batch ) ) {
				return $batch;
			}
			list( $items, $body ) = $batch;
			$plan['items']       = $items;
			$plan['destructive'] = ! empty( $body['delete'] );
		} elseif ( ! $body ) {
			return self::error( 'digitalisimo_woo_mcp_data', sprintf( '%s necesita al menos un campo en data.', $action ) );
		}
		if ( 'shipping_zone_location' === $resource ) {
			// WooCommerce reemplaza todas las ubicaciones con una lista JSON.
			if ( array( 'locations' ) !== array_keys( $body ) || ! is_array( $body['locations'] ) || ! self::is_list( $body['locations'] ) ) {
				return self::error( 'digitalisimo_woo_mcp_data', 'Las ubicaciones van en data {"locations":[{"code":"MX","type":"country"}]}; la lista reemplaza todas las de la zona.' );
			}
			$body = $body['locations'];
		}
		if ( 'refund' === $resource && 'create' === $action ) {
			$body['api_refund']  = $api_refund;
			$plan['destructive'] = true;
		}
		$plan['method'] = 'update' === $action ? 'PUT' : 'POST';
		$plan['body']   = $body;
		return $plan;
	}

	/** Informes: ruta fija por acción; la bitácora no llama a WooCommerce. */
	private static function report( string $action, array $input, array $fields ) {
		foreach ( array( 'data', 'reassign', 'resource' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( '%s no se usa en los informes.', $key ) );
			}
		}
		$plan = array(
			'tool'        => 'reports',
			'action'      => $action,
			'resource'    => 'report',
			'method'      => 'GET',
			'route'       => '',
			'query'       => array(),
			'body'        => null,
			'kind'        => 'read',
			'destructive' => false,
			'id'          => null,
			'parent'      => null,
			'items'       => 0,
			'fields'      => $fields,
			'secrets'     => '',
			'local'       => '',
		);
		if ( 'log' === $action ) {
			if ( array_key_exists( 'query', $input ) ) {
				return self::error( 'digitalisimo_woo_mcp_query', 'log no admite query: usa page y per_page.' );
			}
			$paging = self::paging( $input );
			if ( is_wp_error( $paging ) ) {
				return $paging;
			}
			$plan['local'] = 'log';
			$plan['query'] = $paging;
			return $plan;
		}
		$query = self::query( $input, array() );
		if ( is_wp_error( $query ) ) {
			return $query;
		}
		$plan['route'] = Tools::REST . Tools::REPORTS[ $action ];
		$plan['query'] = $query;
		return $plan;
	}

	/** Un ID validado (entero ≥ su mínimo o texto con `SLUG_PATTERN`), o null. */
	public static function id_value( string $key, $value ) {
		if ( isset( Tools::INT_IDS[ $key ] ) ) {
			if ( is_int( $value ) ) {
				$int = $value;
			} elseif ( is_string( $value ) && 1 === preg_match( '/^[0-9]{1,18}$/', $value ) ) {
				$int = (int) $value;
			} else {
				return null;
			}
			return $int >= Tools::INT_IDS[ $key ] ? $int : null;
		}
		if ( in_array( $key, Tools::SLUG_IDS, true ) ) {
			return is_string( $value ) && 1 === preg_match( Tools::SLUG_PATTERN, $value ) ? $value : null;
		}
		return null;
	}

	/** Un booleano de primer nivel (`force`, `api_refund`): ausente es false; otro tipo es un error. */
	private static function flag( array $input, string $key ) {
		if ( ! array_key_exists( $key, $input ) ) {
			return false;
		}
		if ( ! is_bool( $input[ $key ] ) ) {
			return self::error( 'digitalisimo_woo_mcp_' . $key, sprintf( '%s debe ser true o false.', $key ) );
		}
		return $input[ $key ];
	}

	/** `page` y `per_page` validados (por defecto 1 y `PER_PAGE_DEFAULT`). */
	private static function paging( array $input ) {
		$page     = array_key_exists( 'page', $input ) ? $input['page'] : 1;
		$per_page = array_key_exists( 'per_page', $input ) ? $input['per_page'] : Tools::PER_PAGE_DEFAULT;
		if ( ! is_int( $page ) || $page < 1 ) {
			return self::error( 'digitalisimo_woo_mcp_page', 'page debe ser un número entero igual o mayor que 1.' );
		}
		if ( ! is_int( $per_page ) || $per_page < 1 || $per_page > Tools::PER_PAGE_MAX ) {
			return self::error( 'digitalisimo_woo_mcp_page', 'per_page debe ser un número entero entre 1 y 100.' );
		}
		return array(
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/** `fields`: lista de nombres de campo, como mucho `FIELDS_MAX`. */
	private static function fields( array $input ) {
		if ( ! array_key_exists( 'fields', $input ) ) {
			return array();
		}
		$fields = $input['fields'];
		if ( ! is_array( $fields ) || ! self::is_list( $fields ) || count( $fields ) > Tools::FIELDS_MAX ) {
			return self::error( 'digitalisimo_woo_mcp_fields', 'fields debe ser una lista de hasta 50 nombres de campo.' );
		}
		foreach ( $fields as $field ) {
			if ( ! is_string( $field ) || 1 !== preg_match( '/^[a-z0-9_]{1,64}$/', $field ) ) {
				return self::error( 'digitalisimo_woo_mcp_fields', 'Cada campo de fields sólo lleva minúsculas, números y guion bajo.' );
			}
		}
		return array_values( array_unique( $fields ) );
	}

	/** `query`: claves `^[a-z][a-z0-9_]*$` con escalares o listas de escalares, sin las de la ruta ni reservadas. */
	private static function query( array $input, array $reserved ) {
		if ( ! array_key_exists( 'query', $input ) ) {
			return array();
		}
		$query = $input['query'];
		if ( ! is_array( $query ) || ( $query && self::is_list( $query ) ) ) {
			return self::error( 'digitalisimo_woo_mcp_query', 'query debe ser un objeto de filtros.' );
		}
		foreach ( $query as $key => $value ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/', $key ) ) {
				return self::error( 'digitalisimo_woo_mcp_query', 'Los filtros de query sólo llevan minúsculas, números y guion bajo.' );
			}
			if ( ! self::scalar_or_list( $value ) ) {
				return self::error( 'digitalisimo_woo_mcp_query', sprintf( 'El filtro %s debe ser texto, número, booleano o una lista de ellos.', $key ) );
			}
		}
		$query = self::clean( $query, $reserved );
		unset( $query['page'], $query['per_page'] );
		return $query;
	}

	/** Quita del primer nivel los parámetros de la ruta, `force`, `api_refund` y lo que empieza por `_`. */
	public static function clean( array $values, array $reserved ): array {
		$out = array();
		foreach ( $values as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key || '_' === $key[0] || in_array( $key, $reserved, true ) || in_array( $key, array( 'force', 'api_refund' ), true ) ) {
				continue;
			}
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Lote: sólo las claves permitidas, listas, entre 1 y `BATCH_MAX` elementos, y `delete` sólo con `force:true`.
	 *
	 * Los elementos de create y update son objetos; un parámetro de la ruta dentro de un elemento (`group_id` en un lote
	 * de ajustes, `product_id` en uno de variaciones) se quita si coincide con el de la ruta y, si no, el lote se rechaza:
	 * WooCommerce lo mezcla sobre los de la URL y ganaría (otro grupo esquivaría la protección de secretos). Los de delete
	 * son IDs enteros. Devuelve [elementos, cuerpo limpio].
	 *
	 * @param array $route_params Marcador de la ruta → valor validado.
	 * @return array|\WP_Error
	 */
	private static function batch( array $body, array $allowed, bool $force, array $route_params ) {
		if ( ! $body || array_diff( array_keys( $body ), $allowed ) ) {
			return self::error( 'digitalisimo_woo_mcp_batch', 'batch lleva data con ' . implode( ', ', $allowed ) . ' (listas).' );
		}
		$count = 0;
		foreach ( $body as $key => $list ) {
			if ( ! is_array( $list ) || ( $list && ! self::is_list( $list ) ) ) {
				return self::error( 'digitalisimo_woo_mcp_batch', sprintf( '%s debe ser una lista.', $key ) );
			}
			$count += count( $list );
		}
		if ( $count < 1 || $count > Tools::BATCH_MAX ) {
			return self::error( 'digitalisimo_woo_mcp_batch', 'Un lote lleva entre 1 y 100 elementos en total (el tope de WooCommerce).' );
		}
		if ( ! empty( $body['delete'] ) && ! $force ) {
			return self::error( 'digitalisimo_woo_mcp_batch_force', 'WooCommerce borra los elementos de un lote para siempre, sin papelera: un batch con delete exige force:true, sólo si el usuario lo pidió. Para mandar a la papelera usa delete con un solo ID.' );
		}
		foreach ( $body as $list => $items ) {
			foreach ( $items as $index => $item ) {
				if ( 'delete' === $list ) {
					$id = is_int( $item ) || ( is_string( $item ) && 1 === preg_match( '/^[0-9]{1,18}$/', $item ) ) ? (int) $item : 0;
					if ( $id < 1 ) {
						return self::error( 'digitalisimo_woo_mcp_batch', 'Los elementos de delete de un lote son IDs enteros.' );
					}
					$body[ $list ][ $index ] = $id;
					continue;
				}
				if ( ! is_array( $item ) || ( $item && self::is_list( $item ) ) ) {
					return self::error( 'digitalisimo_woo_mcp_batch', sprintf( 'Cada elemento de %s es un objeto con los campos de WooCommerce.', $list ) );
				}
				foreach ( $route_params as $param => $value ) {
					if ( ! array_key_exists( $param, $item ) ) {
						continue;
					}
					if ( ! is_scalar( $item[ $param ] ) || (string) $item[ $param ] !== (string) $value ) {
						return self::error( 'digitalisimo_woo_mcp_batch_route', sprintf( '%s va en el parámetro de primer nivel: un lote actúa sobre un solo %s (%s); un elemento con otro valor no se admite.', $param, $param, (string) $value ) );
					}
					unset( $body[ $list ][ $index ][ $param ] );
				}
			}
		}
		return array( $count, $body );
	}

	private static function scalar_or_list( $value ): bool {
		if ( is_scalar( $value ) ) {
			return true;
		}
		if ( ! is_array( $value ) || ! self::is_list( $value ) || count( $value ) > 100 ) {
			return false;
		}
		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				return false;
			}
		}
		return true;
	}

	private static function is_list( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 ) || array() === $value;
	}

	private static function error( string $code, string $message ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => 400 ) );
	}
}
