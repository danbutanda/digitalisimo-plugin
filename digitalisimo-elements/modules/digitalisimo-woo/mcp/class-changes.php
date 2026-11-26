<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de cambios de las herramientas de tienda, con la opción de deshacer donde es seguro (tanda B de la auditoría
 * de digitalisimo.lat, 2026-11-24).
 *
 * Aparte de la bitácora (`Log`, que sigue sin valores): para deshacer hay que guardar valores, así que este registro
 * sólo los guarda de lo que no lleva datos personales ni secretos y cabe en `MAX_BYTES`. Una opción del sitio sin carga
 * automática (`OPTION`) con las últimas `MAX` entradas de los últimos `DAYS` días, la más reciente primero.
 *
 * Qué se deshace (`undo: true`):
 * - **update** de un producto, una variación, una categoría, etiqueta, marca, atributo, término de atributo, clase de
 *   envío o un cupón: se guardan el valor de antes y el de después **sólo de las claves enviadas**. Al deshacer, si el
 *   valor actual de alguna ya no es el «después» (alguien lo cambió), no se toca nada y se dice cuáles.
 * - **create** de lo mismo: deshacer manda a la papelera un producto o un cupón; una variación se borra (no tiene
 *   papelera) sólo si no cambió desde que se creó; un término o un atributo, sólo si nadie lo usa.
 * - **delete a la papelera** de un producto o un cupón: deshacer lo saca de la papelera con `wp_untrash_post()` y su
 *   estado anterior.
 * Todo lo demás queda registrado (quién, qué, cuándo) con `undo: false` y el motivo: lotes, pedidos, notas,
 * reembolsos, clientes y reseñas (datos personales), ajustes, envíos, impuestos y pasarelas (claves), borrados
 * definitivos, metadatos y correos de un cupón.
 */
final class Changes {
	const OPTION    = 'digitalisimo_elements_woo_mcp_changes';
	const MAX       = 50;
	const DAYS      = 30;
	const MAX_BYTES = 32768;
	const ID_PATTERN = '/^[a-f0-9]{16}$/';

	/** Recursos cuyo update y create se deshacen. */
	const UNDOABLE = array( 'product', 'variation', 'category', 'tag', 'brand', 'attribute', 'attribute_term', 'shipping_class', 'coupon' );

	/** Recursos que se sacan de la papelera (tipo de contenido de WordPress). */
	const TRASH = array(
		'product' => 'product',
		'coupon'  => 'shop_coupon',
	);

	/** Claves que impiden guardar valores: metadatos (WooCommerce los combina y no se revierten) y correos. */
	const BLOCKING_KEYS = array(
		'meta_data'          => 'Cambia metadatos: WooCommerce los combina y no se pueden deshacer con seguridad.',
		'email_restrictions' => 'Lleva correos de clientes: no se guardan sus valores.',
		'used_by'            => 'Lleva quién usó el cupón: no se guardan sus valores.',
	);

	/** Herramientas con la acción `undo`. */
	const TOOLS = array( 'products', 'catalog', 'coupons' );

	/** ¿Hay que leer el objeto antes de escribir para poder deshacer? Sólo un update que se puede deshacer. */
	public static function wants_before( array $plan ): bool {
		return 'update' === $plan['action'] && in_array( $plan['resource'], self::UNDOABLE, true ) && null !== $plan['id'] && '' === self::blocked( $plan );
	}

	/** Motivo por el que una escritura no se puede deshacer ('' si se puede, a falta del tamaño y la lectura previa). */
	public static function blocked( array $plan ): string {
		$action   = (string) $plan['action'];
		$resource = (string) $plan['resource'];
		if ( 'batch' === $action ) {
			return 'Un lote no se deshace desde aquí: cada elemento queda en la bitácora.';
		}
		if ( 'delete' === $action ) {
			if ( ! empty( $plan['query']['force'] ) ) {
				return 'Borrado definitivo: no se puede deshacer.';
			}
			return isset( self::TRASH[ $resource ] ) ? '' : 'Sólo se deshace mandar a la papelera un producto o un cupón.';
		}
		if ( in_array( $resource, array( 'order', 'note', 'refund', 'customer', 'review' ), true ) ) {
			return 'Lleva datos personales o dinero: no se guardan sus valores para deshacerlo.';
		}
		if ( ! in_array( $resource, self::UNDOABLE, true ) ) {
			return 'Ajustes, envíos, impuestos y pasarelas no se deshacen desde aquí (pueden llevar claves).';
		}
		if ( ! in_array( $action, array( 'create', 'update' ), true ) ) {
			return 'Esta acción no se deshace.';
		}
		$body = is_array( $plan['body'] ) ? $plan['body'] : array();
		foreach ( self::BLOCKING_KEYS as $key => $reason ) {
			if ( array_key_exists( $key, $body ) ) {
				return $reason;
			}
		}
		return '';
	}

	/**
	 * Anota una escritura hecha. Devuelve lo que se dice al cliente: `id`, `undo` y, sin deshacer, `reason`.
	 *
	 * @param array      $plan     Plan del enrutador.
	 * @param int        $user     Usuario.
	 * @param array|null $before   Objeto leído antes (update), o null.
	 * @param mixed      $response Respuesta de WooCommerce.
	 */
	public static function record( array $plan, int $user, $before, $response ): array {
		$entry  = array(
			'id'       => self::new_id(),
			'time'     => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'user'     => max( 0, $user ),
			'tool'     => (string) $plan['tool'],
			'action'   => (string) $plan['action'],
			'resource' => (string) $plan['resource'],
			'item'     => null,
			'parent'   => is_int( $plan['parent'] ) ? $plan['parent'] : null,
			'items'    => (int) $plan['items'],
			'undo'     => false,
			'reason'   => self::blocked( $plan ),
			'undone'   => null,
		);
		$item = is_int( $plan['id'] ) ? $plan['id'] : null;
		if ( 'create' === $plan['action'] && is_array( $response ) && isset( $response['id'] ) && is_numeric( $response['id'] ) ) {
			$item = (int) $response['id'];
		}
		$entry['item'] = $item;
		if ( '' === $entry['reason'] && null === $item ) {
			$entry['reason'] = 'WooCommerce no devolvió el ID del objeto.';
		}
		if ( '' === $entry['reason'] ) {
			if ( 'update' === $plan['action'] ) {
				if ( ! is_array( $before ) ) {
					$entry['reason'] = 'No se pudo leer cómo estaba antes: no se puede deshacer.';
				} else {
					$keys            = array_keys( is_array( $plan['body'] ) ? $plan['body'] : array() );
					$entry['before'] = array();
					$entry['after']  = array();
					foreach ( $keys as $key ) {
						$entry['before'][ $key ] = array_key_exists( $key, $before ) ? $before[ $key ] : null;
						$entry['after'][ $key ]  = is_array( $response ) && array_key_exists( $key, $response ) ? $response[ $key ] : $plan['body'][ $key ];
					}
				}
			} elseif ( 'create' === $plan['action'] ) {
				$entry['stamp'] = self::stamp( $response );
			}
		}
		if ( '' === $entry['reason'] ) {
			$entry['undo'] = true;
			if ( strlen( (string) wp_json_encode( $entry ) ) > self::MAX_BYTES ) {
				unset( $entry['before'], $entry['after'] );
				$entry['undo']   = false;
				$entry['reason'] = 'El cambio es demasiado grande para guardarlo (más de 32 KB): no se puede deshacer.';
			}
		}
		if ( ! $entry['undo'] ) {
			unset( $entry['before'], $entry['after'], $entry['stamp'] );
		}
		self::add( $entry );
		$out = array(
			'id'   => $entry['id'],
			'undo' => $entry['undo'],
		);
		if ( ! $entry['undo'] ) {
			$out['reason'] = $entry['reason'];
		}
		return $out;
	}

	/** Fecha de modificación de un objeto (para saber si cambió después de crearlo), o ''. */
	public static function stamp( $object ): string {
		if ( ! is_array( $object ) ) {
			return '';
		}
		foreach ( array( 'date_modified_gmt', 'date_modified' ) as $key ) {
			if ( isset( $object[ $key ] ) && is_string( $object[ $key ] ) ) {
				return $object[ $key ];
			}
		}
		return '';
	}

	/** Añade una entrada y deja las últimas `MAX` de los últimos `DAYS` días. */
	public static function add( array $entry ): void {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}
		$entries = self::entries();
		array_unshift( $entries, $entry );
		self::save( $entries );
	}

	/** Entradas vigentes, la más reciente primero. */
	public static function entries(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$cutoff = gmdate( 'Y-m-d\TH:i:s\Z', time() - self::DAYS * 86400 );
		return array_values(
			array_filter(
				$raw,
				static function ( $entry ) use ( $cutoff ) {
					return is_array( $entry ) && isset( $entry['id'], $entry['time'] ) && (string) $entry['time'] >= $cutoff;
				}
			)
		);
	}

	/** Una entrada por su ID, o null. */
	public static function get( string $id ) {
		if ( 1 !== preg_match( self::ID_PATTERN, $id ) ) {
			return null;
		}
		foreach ( self::entries() as $entry ) {
			if ( $id === $entry['id'] ) {
				return $entry;
			}
		}
		return null;
	}

	/** Marca una entrada como deshecha (y deja de guardar sus valores). */
	public static function mark_undone( string $id, int $user ): void {
		$entries = self::entries();
		foreach ( $entries as $i => $entry ) {
			if ( $id === $entry['id'] ) {
				$entries[ $i ]['undone'] = array(
					'time' => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'user' => max( 0, $user ),
				);
				unset( $entries[ $i ]['before'], $entries[ $i ]['after'], $entries[ $i ]['stamp'] );
			}
		}
		self::save( $entries );
	}

	/** Una página del registro para el cliente: sin valores, sólo qué campos cambió cada entrada. */
	public static function page( int $page, int $per_page ): array {
		$entries = self::entries();
		$total   = count( $entries );
		$per     = max( 1, $per_page );
		$data    = array();
		foreach ( array_slice( $entries, ( max( 1, $page ) - 1 ) * $per, $per ) as $entry ) {
			$row = array_intersect_key( $entry, array_flip( array( 'id', 'time', 'user', 'tool', 'action', 'resource', 'item', 'parent', 'items', 'undo', 'reason', 'undone' ) ) );
			if ( isset( $entry['before'] ) && is_array( $entry['before'] ) ) {
				$row['fields'] = array_keys( $entry['before'] );
			}
			if ( ! empty( $entry['undone'] ) ) {
				$row['undo'] = false;
			}
			$data[] = $row;
		}
		return array(
			'data'        => $data,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per ),
			'page'        => max( 1, $page ),
			'per_page'    => $per,
		);
	}

	/** Ruta REST del objeto de una entrada (de la tabla cerrada, con sus IDs validados). */
	public static function route( array $entry ): string {
		$def = isset( Tools::RESOURCES[ $entry['resource'] ] ) ? Tools::RESOURCES[ $entry['resource'] ] : null;
		if ( ! $def || ! is_int( $entry['item'] ) || $entry['item'] < 1 ) {
			return '';
		}
		$path = $def['path'];
		foreach ( $def['parents'] as $placeholder => $key ) {
			if ( ! is_int( $entry['parent'] ) || $entry['parent'] < 1 ) {
				return '';
			}
			$path = str_replace( '{' . $placeholder . '}', (string) $entry['parent'], $path );
		}
		return Tools::REST . $path . '/' . $entry['item'];
	}

	/**
	 * Deshace una entrada (o, con `$dry`, dice qué haría). Comprueba antes que lo actual sigue como lo dejó el cambio.
	 *
	 * @return array|\WP_Error `would` (lo que se revierte) y, al hacerlo, `status` y `data` de WooCommerce.
	 */
	public static function undo( array $entry, $dispatch, bool $dry ) {
		if ( ! empty( $entry['undone'] ) ) {
			return self::error( 'digitalisimo_woo_mcp_undo_done', 'Ese cambio ya se deshizo.', 409 );
		}
		if ( empty( $entry['undo'] ) ) {
			return self::error( 'digitalisimo_woo_mcp_undo_unavailable', 'Ese cambio no se puede deshacer: ' . (string) $entry['reason'], 409 );
		}
		$route = self::route( $entry );
		if ( '' === $route ) {
			return self::error( 'digitalisimo_woo_mcp_undo_unavailable', 'Ese cambio no se puede deshacer: falta el objeto.', 409 );
		}
		if ( 'delete' === $entry['action'] ) {
			return self::untrash( $entry, $dry );
		}
		list( $status, $current ) = call_user_func( $dispatch, 'GET', $route, array(), null );
		if ( $status >= 400 ) {
			return Executor::woo_error( (int) $status, $current );
		}
		$current = is_array( $current ) ? $current : array();

		if ( 'update' === $entry['action'] ) {
			$before    = is_array( $entry['before'] ?? null ) ? $entry['before'] : array();
			$after     = is_array( $entry['after'] ?? null ) ? $entry['after'] : array();
			$conflicts = array();
			$revert    = array();
			foreach ( $after as $key => $value ) {
				$now = array_key_exists( $key, $current ) ? $current[ $key ] : null;
				if ( ! Simulation::same( $now, $value ) ) {
					$conflicts[] = (string) $key;
				}
				$revert[] = array(
					'field' => (string) $key,
					'from'  => $now,
					'to'    => array_key_exists( $key, $before ) ? $before[ $key ] : null,
				);
			}
			if ( $conflicts ) {
				return self::error( 'digitalisimo_woo_mcp_undo_conflict', 'No se deshizo para no pisar cambios posteriores: desde ese cambio se modificó ' . implode( ', ', $conflicts ) . '.', 409, array( 'conflicts' => $conflicts ) );
			}
			$would = array( 'revert' => $revert );
			if ( $dry ) {
				return array( 'would' => $would );
			}
			list( $status, $data ) = call_user_func( $dispatch, 'PUT', $route, array(), $before );
			return $status >= 400 ? Executor::woo_error( (int) $status, $data ) : array( 'would' => $would, 'status' => (int) $status, 'data' => $data );
		}

		// create: quitar lo creado, sólo si sigue como se creó (o nadie lo usa).
		$resource = $entry['resource'];
		$stamp    = (string) ( $entry['stamp'] ?? '' );
		$query    = array();
		if ( in_array( $resource, array( 'product', 'coupon' ), true ) ) {
			$mode = 'trash';
		} elseif ( 'variation' === $resource ) {
			if ( '' === $stamp || self::stamp( $current ) !== $stamp ) {
				return self::error( 'digitalisimo_woo_mcp_undo_conflict', 'No se deshizo: la variación cambió después de crearla (y borrarla no tiene papelera).', 409 );
			}
			$mode  = 'permanent';
			$query = array( 'force' => true );
		} elseif ( 'attribute' === $resource ) {
			list( $status, $terms ) = call_user_func( $dispatch, 'GET', $route . '/terms', array( 'per_page' => 1 ), null );
			if ( $status >= 400 || ( is_array( $terms ) && $terms ) ) {
				return self::error( 'digitalisimo_woo_mcp_undo_conflict', 'No se deshizo: el atributo ya tiene términos.', 409 );
			}
			$mode  = 'permanent';
			$query = array( 'force' => true );
		} else {
			if ( ! isset( $current['count'] ) || 0 !== (int) $current['count'] ) {
				return self::error( 'digitalisimo_woo_mcp_undo_conflict', 'No se deshizo: ya hay productos que lo usan.', 409 );
			}
			$mode  = 'permanent';
			$query = array( 'force' => true );
		}
		if ( 'trash' === $mode && '' !== $stamp && self::stamp( $current ) !== $stamp ) {
			return self::error( 'digitalisimo_woo_mcp_undo_conflict', 'No se deshizo: cambió después de crearlo. Si ya no lo quieres, bórralo con delete.', 409 );
		}
		$would = array(
			'delete' => Simulation::summary( $current ),
			'mode'   => $mode,
		);
		if ( $dry ) {
			return array( 'would' => $would );
		}
		list( $status, $data ) = call_user_func( $dispatch, 'DELETE', $route, $query, null );
		return $status >= 400 ? Executor::woo_error( (int) $status, $data ) : array( 'would' => $would, 'status' => (int) $status, 'data' => Simulation::summary( $data ) );
	}

	/** Saca de la papelera un producto o un cupón, con su estado anterior. */
	private static function untrash( array $entry, bool $dry ) {
		$type = self::TRASH[ $entry['resource'] ] ?? '';
		$post = function_exists( 'get_post' ) ? get_post( $entry['item'] ) : null;
		if ( '' === $type || ! is_object( $post ) || $type !== $post->post_type ) {
			return self::error( 'digitalisimo_woo_mcp_undo_conflict', 'No se deshizo: ya no existe (quizá se vació la papelera).', 409 );
		}
		if ( 'trash' !== $post->post_status ) {
			return self::error( 'digitalisimo_woo_mcp_undo_conflict', 'No se deshizo: ya no está en la papelera.', 409 );
		}
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'delete_post', $entry['item'] ) ) {
			return self::error( 'digitalisimo_woo_mcp_forbidden', 'Tu usuario no puede sacarlo de la papelera.', 403 );
		}
		$would = array(
			'restore' => array(
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
			),
		);
		if ( $dry ) {
			return array( 'would' => $would );
		}
		$previous = static function ( $status, $post_id, $previous_status ) {
			return is_string( $previous_status ) && '' !== $previous_status ? $previous_status : $status;
		};
		add_filter( 'wp_untrash_post_status', $previous, 10, 3 );
		$done = function_exists( 'wp_untrash_post' ) ? wp_untrash_post( $entry['item'] ) : false;
		remove_filter( 'wp_untrash_post_status', $previous, 10 );
		if ( ! $done ) {
			return self::error( 'digitalisimo_woo_mcp_undo_failed', 'WordPress no lo sacó de la papelera.', 500 );
		}
		return array( 'would' => $would, 'status' => 200, 'data' => $would['restore'] );
	}

	private static function save( array $entries ): void {
		$entries = array_slice( $entries, 0, self::MAX );
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $entries, '', false );
		} else {
			update_option( self::OPTION, $entries, false );
		}
	}

	private static function new_id(): string {
		try {
			return bin2hex( random_bytes( 8 ) );
		} catch ( \Exception $e ) {
			return substr( md5( uniqid( '', true ) ), 0, 16 );
		}
	}

	private static function error( string $code, string $message, int $status, array $extra = array() ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) + $extra );
	}
}
