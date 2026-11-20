<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Ejecuta `digitalisimo-woo/skydropx` (definición en `Skydropx_Tool`) con las mismas reglas que las demás herramientas
 * de tienda: entrada cerrada, el modo «WooCommerce en el MCP» (las escrituras sólo en «Lectura y escritura»), el permiso
 * también aquí y la bitácora de cada escritura (`Log`, sin datos personales ni valores).
 *
 * Cómo llega a WordPress, sin SQL propio ni clases internas de WooCommerce ni del plugin:
 * - `update_products`: lotes de la API REST v3 de WooCommerce (`products/batch` y `…/variations/batch`), como
 *   `products`, para que WooCommerce valide, compruebe permisos y dispare sus ganchos y webhooks.
 * - `shipments`/`get_shipment`: la REST de pedidos (HPOS incluido).
 * - `set_tracking`: las tres metas de la línea de envío que lee el plugin (`Skydropx_Order_Label_Service`) con el CRUD
 *   de WooCommerce y un `save()` del pedido. No por la REST: con `shipping_lines` recalcula los totales (y los impuestos)
 *   de un pedido ya cobrado. El estado y la nota al cliente, sí por la REST.
 * - `resync`: `save()` del pedido con su fecha de modificación al día: WooCommerce dispara `woocommerce_update_order` y
 *   el webhook `order.updated` que la plataforma registró (y la sincronización por fecha también lo ve).
 * - `quote`: el método de envío del propio plugin (`calculate_shipping()`) con un carrito y una sesión que sólo existen
 *   durante la llamada (`Skydropx_Cart`, `Skydropx_Session`); su caché de 2 minutos se guarda por usuario.
 * - `label`: un enlace a `admin-post.php` (`handle_label()`) que, abierto en el navegador del administrador, lo lleva al
 *   endpoint de impresión del plugin con su nonce (el nonce va ligado a la sesión del navegador, por eso no sale del
 *   MCP) y sus permisos. Ni el PDF, ni la firma, ni la clave pasan por el modelo.
 *
 * Todo lo del plugin se busca con `class_exists`/`method_exists`/`defined`: si otra versión cambió una pieza, la acción
 * que la necesita lo dice y las demás siguen.
 */
final class Skydropx {
	/** Productos y variaciones que `audit_products` revisa como mucho en una llamada. */
	const MAX_SCAN = 5000;

	/** Transitorio de la caché de cotizaciones (por usuario) y su vida, la del plugin. */
	const QUOTE_CACHE = 'digitalisimo_woo_skydropx_quote_';
	const QUOTE_TTL   = 120;

	/** Transitorio del recorrido de `audit_products` (por usuario) para sus páginas siguientes, y su vida. */
	const AUDIT_CACHE = 'digitalisimo_woo_skydropx_audit_';
	const AUDIT_TTL   = 300;

	/** Tarifas ya cotizadas en esta petición PHP, por huella (ver `do_quote()`). @var array */
	private static $quoted = array();

	/** Valor numérico máximo aceptado para peso y medidas (en la unidad enviada). */
	const MAX_MEASURE = 1000000;

	// =================================================================================================================
	// Entrada.

	/**
	 * @param mixed         $input    Entrada del cliente.
	 * @param callable|null $dispatch Para las pruebas, como en `Executor::run()`; por defecto `Executor::rest()`.
	 * @return array|\WP_Error
	 */
	public static function run( $input, $dispatch = null ) {
		$dispatch = is_callable( $dispatch ) ? $dispatch : array( Executor::class, 'rest' );
		$plan     = self::resolve( $input );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$mode = Settings::mode();
		if ( 'off' === $mode ) {
			return new \WP_Error( 'digitalisimo_woo_mcp_off', 'Las herramientas de tienda están apagadas en este sitio («WooCommerce en el MCP»: apagado).', array( 'status' => 403 ) );
		}
		$allowed = Skydropx_Tool::permission( $input );
		if ( true !== $allowed ) {
			if ( 'write' === $plan['kind'] ) {
				Log::add( Log::entry( $plan, 'denied', 'forbidden', 403, $user ) );
			}
			return $allowed;
		}
		if ( 'write' === $plan['kind'] && 'write' !== $mode ) {
			Log::add( Log::entry( $plan, 'denied', 'read_mode', 403, $user ) );
			return Executor::read_mode_error( $plan );
		}
		if ( ! Skydropx_Tool::active() ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_inactive', 'El plugin de Skydropx no está activo en este sitio.', 409 );
		}
		try {
			$result = call_user_func( array( __CLASS__, 'do_' . $plan['action'] ), $plan, $dispatch );
		} catch ( \Throwable $e ) {
			$result = self::error( 'digitalisimo_woo_mcp_skydropx_failed', 'No se pudo completar ' . $plan['action'] . ': ' . self::clean_text( $e->getMessage(), 300 ), 500 );
		}
		if ( 'write' === $plan['kind'] ) {
			if ( is_wp_error( $result ) ) {
				$data   = $result->get_error_data();
				$denied = is_array( $data ) && ! empty( $data['denied'] );
				Log::add( Log::entry( $plan, $denied ? 'denied' : 'error', $denied ? 'forbidden' : (string) $result->get_error_code(), is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500, $user ) );
			} else {
				$partial = ! empty( $result['partial'] );
				Log::add( Log::entry( $plan, $partial ? 'partial' : 'ok', $partial ? (string) ( $result['code'] ?? '' ) : '', 200, $user ) );
				unset( $result['code'] );
			}
		}
		return $result;
	}

	/** Permiso de la herramienta: lo resuelve la clase ligera (ver `Skydropx_Tool::permission()`). */
	public static function permission( $input = null ) {
		return Skydropx_Tool::permission( $input );
	}

	/**
	 * Valida la entrada y la deja normalizada en un plan (la forma de `Router::resolve()` para la bitácora: `tool`,
	 * `action`, `resource`, `kind`, `id`, `parent`, `items`) más sus parámetros limpios.
	 *
	 * @return array|\WP_Error
	 */
	public static function resolve( $input ) {
		if ( ! is_array( $input ) ) {
			return self::error( 'digitalisimo_woo_mcp_input', 'La entrada debe ser un objeto con action.' );
		}
		$schema  = Skydropx_Tool::input_schema();
		$unknown = array_diff( array_map( 'strval', array_keys( $input ) ), array_keys( $schema['properties'] ) );
		if ( $unknown ) {
			return self::error( 'digitalisimo_woo_mcp_input', 'Parámetros que esta herramienta no admite: ' . implode( ', ', $unknown ) . '.' );
		}
		$action = isset( $input['action'] ) && is_string( $input['action'] ) ? $input['action'] : '';
		if ( ! in_array( $action, Skydropx_Tool::actions(), true ) ) {
			return self::error( 'digitalisimo_woo_mcp_action', 'action debe ser una de: ' . implode( ', ', Skydropx_Tool::actions() ) . '.' );
		}
		// Un parámetro de otra acción se rechaza, salvo su valor por defecto (lo rellenan algunos clientes).
		$defaults = array(
			'page'                 => 1,
			'per_page'             => Tools::PER_PAGE_DEFAULT,
			'cache'                => true,
			'tracking_filter'      => 'any',
			'after_id'             => 0,
			'create_shipping_line' => false,
		);
		foreach ( $input as $key => $value ) {
			$key = (string) $key;
			if ( 'action' === $key || in_array( $key, Skydropx_Tool::PARAMS[ $action ], true ) ) {
				continue;
			}
			if ( array_key_exists( $key, $defaults ) && $defaults[ $key ] === $value ) {
				unset( $input[ $key ] );
				continue;
			}
			return self::error( 'digitalisimo_woo_mcp_input', sprintf( '%s no se usa con %s.', $key, $action ) );
		}

		$plan = array(
			'tool'     => Skydropx_Tool::TOOL,
			'action'   => $action,
			'resource' => in_array( $action, array( 'audit_products', 'update_products' ), true ) ? 'product' : ( in_array( $action, array( 'status', 'quote' ), true ) ? 'store' : 'order' ),
			'kind'     => Skydropx_Tool::is_write( $action ) ? 'write' : 'read',
			'id'       => null,
			'parent'   => null,
			'items'    => 0,
		);
		if ( in_array( 'order_id', Skydropx_Tool::PARAMS[ $action ], true ) ) {
			$id = array_key_exists( 'order_id', $input ) ? Router::id_value( 'order_id', $input['order_id'] ) : null;
			if ( null === $id ) {
				return self::error( 'digitalisimo_woo_mcp_id', sprintf( '%s necesita order_id: un número entero igual o mayor que 1.', $action ) );
			}
			$plan['id'] = $id;
		}
		if ( in_array( 'page', Skydropx_Tool::PARAMS[ $action ], true ) ) {
			$page     = array_key_exists( 'page', $input ) ? $input['page'] : 1;
			$per_page = array_key_exists( 'per_page', $input ) ? $input['per_page'] : Tools::PER_PAGE_DEFAULT;
			if ( ! is_int( $page ) || $page < 1 ) {
				return self::error( 'digitalisimo_woo_mcp_page', 'page debe ser un número entero igual o mayor que 1.' );
			}
			if ( ! is_int( $per_page ) || $per_page < 1 || $per_page > Tools::PER_PAGE_MAX ) {
				return self::error( 'digitalisimo_woo_mcp_page', 'per_page debe ser un número entero entre 1 y 100.' );
			}
			$plan['page']     = $page;
			$plan['per_page'] = $per_page;
		}
		$method = 'resolve_' . $action;
		return method_exists( __CLASS__, $method ) ? call_user_func( array( __CLASS__, $method ), $plan, $input ) : $plan;
	}

	private static function resolve_audit_products( array $plan, array $input ) {
		$plan['checks'] = null; // null: los predeterminados (se deciden al ejecutar).
		if ( array_key_exists( 'checks', $input ) ) {
			$checks = $input['checks'];
			if ( ! is_array( $checks ) || ! self::is_list( $checks ) || ! $checks ) {
				return self::error( 'digitalisimo_woo_mcp_input', 'checks es una lista con weight, dimensions o shipping_class.' );
			}
			foreach ( $checks as $check ) {
				if ( ! is_string( $check ) || ! in_array( $check, Skydropx_Tool::CHECKS, true ) ) {
					return self::error( 'digitalisimo_woo_mcp_input', 'checks sólo admite weight, dimensions y shipping_class.' );
				}
			}
			$plan['checks'] = array_values( array_unique( $checks ) );
		}
		$plan['after_id'] = 0;
		if ( array_key_exists( 'after_id', $input ) ) {
			if ( ! is_int( $input['after_id'] ) || $input['after_id'] < 0 ) {
				return self::error( 'digitalisimo_woo_mcp_input', 'after_id es un entero igual o mayor que 0 (el next_after_id de la respuesta anterior).' );
			}
			$plan['after_id'] = $input['after_id'];
		}
		return $plan;
	}

	private static function resolve_quote( array $plan, array $input ) {
		if ( ! isset( $input['address'] ) || ! is_array( $input['address'] ) || ( $input['address'] && self::is_list( $input['address'] ) ) ) {
			return self::error( 'digitalisimo_woo_mcp_input', 'quote necesita address: un objeto con al menos postcode.' );
		}
		$address = array();
		$limits  = array(
			'postcode'  => 20,
			'country'   => 2,
			'state'     => 50,
			'city'      => 100,
			'address_1' => 200,
			'address_2' => 200,
		);
		foreach ( $input['address'] as $key => $value ) {
			if ( ! isset( $limits[ $key ] ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( 'address no admite %s (sólo %s).', $key, implode( ', ', array_keys( $limits ) ) ) );
			}
			if ( ! is_string( $value ) || self::length( $value ) > $limits[ $key ] || preg_match( '/[\x00-\x1F\x7F<>]/', $value ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( 'address.%s debe ser texto de hasta %d caracteres.', $key, $limits[ $key ] ) );
			}
			$address[ $key ] = trim( $value );
		}
		if ( ! isset( $address['postcode'] ) || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9 -]{0,19}$/', $address['postcode'] ) ) {
			return self::error( 'digitalisimo_woo_mcp_input', 'address.postcode es obligatorio: letras, números, espacio o guion.' );
		}
		if ( isset( $address['country'] ) ) {
			if ( 1 !== preg_match( '/^[A-Za-z]{2}$/', $address['country'] ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', 'address.country es el código ISO de dos letras (MX, CO…).' );
			}
			$address['country'] = strtoupper( $address['country'] );
		}
		if ( ! isset( $input['cart'] ) || ! is_array( $input['cart'] ) || ! self::is_list( $input['cart'] ) || ! $input['cart'] || count( $input['cart'] ) > Skydropx_Tool::CART_MAX ) {
			return self::error( 'digitalisimo_woo_mcp_input', 'quote necesita cart: una lista de 1 a 50 productos [{product_id, variation_id, quantity}].' );
		}
		$cart = array();
		foreach ( $input['cart'] as $index => $line ) {
			if ( ! is_array( $line ) || self::is_list( $line ) || array_diff( array_keys( $line ), array( 'product_id', 'variation_id', 'quantity' ) ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( 'cart[%d] es un objeto con product_id, variation_id y quantity.', $index ) );
			}
			$product   = isset( $line['product_id'] ) ? Router::id_value( 'product_id', $line['product_id'] ) : null;
			$variation = array_key_exists( 'variation_id', $line ) ? Router::id_value( 'variation_id', $line['variation_id'] ) : 0;
			$quantity  = array_key_exists( 'quantity', $line ) ? $line['quantity'] : 1;
			if ( null === $product || null === $variation ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( 'cart[%d]: product_id y variation_id son enteros mayores que 0.', $index ) );
			}
			if ( ! is_int( $quantity ) || $quantity < 1 || $quantity > 1000 ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( 'cart[%d].quantity es un entero entre 1 y 1000.', $index ) );
			}
			$cart[] = array(
				'product_id'   => $product,
				'variation_id' => $variation,
				'quantity'     => $quantity,
			);
		}
		if ( array_key_exists( 'cache', $input ) && ! is_bool( $input['cache'] ) ) {
			return self::error( 'digitalisimo_woo_mcp_input', 'cache debe ser true o false.' );
		}
		$plan['address'] = $address;
		$plan['cart']    = $cart;
		$plan['cache']   = array_key_exists( 'cache', $input ) ? $input['cache'] : true;
		$plan['items']   = count( $cart );
		return $plan;
	}

	private static function resolve_shipments( array $plan, array $input ) {
		$plan['statuses'] = array();
		if ( array_key_exists( 'statuses', $input ) ) {
			if ( ! is_array( $input['statuses'] ) || ! self::is_list( $input['statuses'] ) || count( $input['statuses'] ) > 20 ) {
				return self::error( 'digitalisimo_woo_mcp_input', 'statuses es una lista de hasta 20 estados de pedido.' );
			}
			foreach ( $input['statuses'] as $status ) {
				if ( ! is_string( $status ) || 1 !== preg_match( '/^[a-z0-9_-]{1,40}$/', $status ) ) {
					return self::error( 'digitalisimo_woo_mcp_input', 'Cada estado de statuses lleva minúsculas, números, guion o guion bajo (processing, completed…).' );
				}
			}
			$plan['statuses'] = array_values( array_unique( array_map( array( __CLASS__, 'unprefix_status' ), $input['statuses'] ) ) );
		}
		foreach ( array( 'after', 'before' ) as $key ) {
			$plan[ $key ] = '';
			if ( array_key_exists( $key, $input ) ) {
				$date = self::date( $input[ $key ] );
				if ( '' === $date ) {
					return self::error( 'digitalisimo_woo_mcp_input', sprintf( '%s es una fecha AAAA-MM-DD (o AAAA-MM-DDTHH:MM:SS).', $key ) );
				}
				$plan[ $key ] = $date;
			}
		}
		$plan['tracking_filter'] = 'any';
		if ( array_key_exists( 'tracking_filter', $input ) ) {
			if ( ! is_string( $input['tracking_filter'] ) || ! in_array( $input['tracking_filter'], Skydropx_Tool::TRACKING_FILTER, true ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', 'tracking_filter es any, with o without.' );
			}
			$plan['tracking_filter'] = $input['tracking_filter'];
		}
		return $plan;
	}

	private static function resolve_update_products( array $plan, array $input ) {
		if ( ! isset( $input['items'] ) || ! is_array( $input['items'] ) || ! self::is_list( $input['items'] ) || ! $input['items'] ) {
			return self::error( 'digitalisimo_woo_mcp_input', 'update_products necesita items: una lista de productos [{product_id, variation_id, weight, length, width, height, shipping_class}].' );
		}
		if ( count( $input['items'] ) > Skydropx_Tool::BATCH_MAX ) {
			return self::error( 'digitalisimo_woo_mcp_batch', 'Un lote lleva entre 1 y 100 productos (el tope de WooCommerce).' );
		}
		foreach ( array( 'weight_unit' => Skydropx_Tool::WEIGHT_UNITS, 'dimension_unit' => Skydropx_Tool::DIMENSION_UNITS ) as $key => $units ) {
			$plan[ $key ] = '';
			if ( array_key_exists( $key, $input ) ) {
				if ( ! is_string( $input[ $key ] ) || ! in_array( $input[ $key ], $units, true ) ) {
					return self::error( 'digitalisimo_woo_mcp_unit', sprintf( '%s debe ser una de: %s.', $key, implode( ', ', $units ) ) );
				}
				$plan[ $key ] = $input[ $key ];
			}
		}
		$allowed = array( 'product_id', 'variation_id', 'weight', 'length', 'width', 'height', 'shipping_class' );
		$items   = array();
		$seen    = array();
		foreach ( $input['items'] as $index => $item ) {
			if ( ! is_array( $item ) || ( $item && self::is_list( $item ) ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( 'items[%d] es un objeto.', $index ) );
			}
			$extra = array_diff( array_map( 'strval', array_keys( $item ) ), $allowed );
			if ( $extra ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( 'items[%d] no admite %s: sólo %s.', $index, implode( ', ', $extra ), implode( ', ', $allowed ) ) );
			}
			$product   = isset( $item['product_id'] ) ? Router::id_value( 'product_id', $item['product_id'] ) : null;
			$variation = array_key_exists( 'variation_id', $item ) ? Router::id_value( 'variation_id', $item['variation_id'] ) : 0;
			if ( null === $product || null === $variation ) {
				return self::error( 'digitalisimo_woo_mcp_id', sprintf( 'items[%d]: product_id (y variation_id, si va) son enteros mayores que 0.', $index ) );
			}
			$key = $product . ':' . $variation;
			if ( isset( $seen[ $key ] ) ) {
				return self::error( 'digitalisimo_woo_mcp_batch', sprintf( 'items[%d] repite el producto de items[%d].', $index, $seen[ $key ] ) );
			}
			$seen[ $key ] = $index;
			$clean        = array(
				'position'     => $index,
				'product_id'   => $product,
				'variation_id' => $variation,
			);
			foreach ( array( 'weight', 'length', 'width', 'height' ) as $field ) {
				if ( ! array_key_exists( $field, $item ) ) {
					continue;
				}
				$value = self::measure( $item[ $field ] );
				if ( null === $value ) {
					return self::error( 'digitalisimo_woo_mcp_value', sprintf( 'items[%d].%s debe ser un número mayor que 0 (y menor que un millón): un peso o una medida en cero es justo lo que la cotización no puede usar.', $index, $field ) );
				}
				$clean[ $field ] = $value;
			}
			if ( array_key_exists( 'shipping_class', $item ) ) {
				if ( ! is_string( $item['shipping_class'] ) || 1 !== preg_match( '/^[a-z0-9_-]{0,200}$/', $item['shipping_class'] ) ) {
					return self::error( 'digitalisimo_woo_mcp_value', sprintf( 'items[%d].shipping_class es el slug de una clase de envío (minúsculas, números y guiones); vacío la quita.', $index ) );
				}
				$clean['shipping_class'] = $item['shipping_class'];
			}
			if ( 3 === count( $clean ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( 'items[%d] no cambia nada: manda weight, length, width, height o shipping_class.', $index ) );
			}
			$items[] = $clean;
		}
		$plan['products'] = $items;
		$plan['items']    = count( $items );
		return $plan;
	}

	private static function resolve_set_tracking( array $plan, array $input ) {
		if ( ! isset( $input['tracking'] ) || ! is_array( $input['tracking'] ) || ! $input['tracking'] || self::is_list( $input['tracking'] ) ) {
			return self::error( 'digitalisimo_woo_mcp_input', 'set_tracking necesita tracking: {number, company, url} (lo que no se manda se conserva).' );
		}
		$tracking = array();
		foreach ( $input['tracking'] as $key => $value ) {
			if ( ! in_array( $key, array( 'number', 'company', 'url' ), true ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( 'tracking no admite %s (sólo number, company y url).', $key ) );
			}
			if ( ! is_string( $value ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', sprintf( 'tracking.%s es texto.', $key ) );
			}
			$value = trim( $value );
			if ( 'number' === $key && 1 !== preg_match( '#^[A-Za-z0-9][A-Za-z0-9 ._/-]{0,99}$#', $value ) ) {
				return self::error( 'digitalisimo_woo_mcp_value', 'tracking.number es el número de guía: letras, números, espacio, punto, guion o diagonal, hasta 100.' );
			}
			if ( 'company' === $key && ( '' === $value || self::length( $value ) > 100 || preg_match( '/[\x00-\x1F\x7F<>]/', $value ) ) ) {
				return self::error( 'digitalisimo_woo_mcp_value', 'tracking.company es el nombre de la paquetería, hasta 100 caracteres.' );
			}
			if ( 'url' === $key && ! self::valid_url( $value ) ) {
				return self::error( 'digitalisimo_woo_mcp_value', 'tracking.url es la dirección de rastreo completa, con http o https, hasta 500 caracteres.' );
			}
			$tracking[ $key ] = $value;
		}
		$plan['tracking']         = $tracking;
		$plan['shipping_line_id'] = 0;
		if ( array_key_exists( 'shipping_line_id', $input ) ) {
			$line = Router::id_value( 'order_id', $input['shipping_line_id'] );
			if ( null === $line ) {
				return self::error( 'digitalisimo_woo_mcp_id', 'shipping_line_id es un entero mayor que 0.' );
			}
			$plan['shipping_line_id'] = $line;
		}
		$plan['create_shipping_line'] = false;
		if ( array_key_exists( 'create_shipping_line', $input ) ) {
			if ( ! is_bool( $input['create_shipping_line'] ) ) {
				return self::error( 'digitalisimo_woo_mcp_input', 'create_shipping_line debe ser true o false.' );
			}
			$plan['create_shipping_line'] = $input['create_shipping_line'];
		}
		$plan['order_status'] = '';
		if ( array_key_exists( 'order_status', $input ) ) {
			if ( ! is_string( $input['order_status'] ) || 1 !== preg_match( '/^[a-z0-9_-]{1,40}$/', $input['order_status'] ) ) {
				return self::error( 'digitalisimo_woo_mcp_value', 'order_status es un estado de pedido (processing, completed…).' );
			}
			$plan['order_status'] = self::unprefix_status( $input['order_status'] );
		}
		$plan['customer_note'] = '';
		if ( array_key_exists( 'customer_note', $input ) ) {
			if ( ! is_string( $input['customer_note'] ) || '' === trim( $input['customer_note'] ) || self::length( $input['customer_note'] ) > 1000 ) {
				return self::error( 'digitalisimo_woo_mcp_value', 'customer_note es texto de 1 a 1000 caracteres.' );
			}
			$plan['customer_note'] = trim( $input['customer_note'] );
		}
		return $plan;
	}

	// =================================================================================================================
	// Lo que se sabe del plugin.

	/**
	 * Piezas del plugin que esta herramienta usa y no están en la versión instalada (vacío con 2.1.5). Cada acción dice
	 * la suya al fallar; `status` las lista todas.
	 */
	public static function compat(): array {
		$missing = array();
		if ( ! defined( 'SKYDROPX_VERSION' ) ) {
			$missing[] = 'SKYDROPX_VERSION';
		}
		foreach ( array(
			array( Skydropx_Tool::REPOSITORY, 'has_plugin_api_keys' ),
			array( Skydropx_Tool::QUOTATION, 'get_enabled' ),
			array( Skydropx_Tool::QUOTATION, 'get_base_url' ),
			array( Skydropx_Tool::LABEL_SERVICE, 'has_label_tracking_meta' ),
			array( Skydropx_Tool::LABEL_UI, 'add_orders_list_action' ),
		) as $piece ) {
			if ( ! self::has_method( $piece[0], $piece[1] ) ) {
				$missing[] = $piece[0] . '::' . $piece[1] . '()';
			}
		}
		if ( ! defined( Skydropx_Tool::LABEL_UI . '::PRINT_ACTION' ) ) {
			$missing[] = Skydropx_Tool::LABEL_UI . '::PRINT_ACTION';
		}
		$constants = self::class_constants( Skydropx_Tool::LABEL_SERVICE );
		foreach ( array( 'META_TRACKING_NUMBER', 'META_TRACKING_COMPANY', 'META_TRACKING_URL' ) as $constant ) {
			if ( ! isset( $constants[ $constant ] ) || ! is_string( $constants[ $constant ] ) ) {
				$missing[] = Skydropx_Tool::LABEL_SERVICE . '::' . $constant;
			}
		}
		// Con los envíos apagados WooCommerce no carga ningún método: no es una pieza faltante del plugin.
		if ( self::shipping_enabled() && '' === self::shipping_method_class() ) {
			$missing[] = Skydropx_Tool::SHIPPING_METHOD . '::calculate_shipping()';
		}
		return $missing;
	}

	/** ¿Calcula envíos la tienda? (WooCommerce → Ajustes → General, «Ubicaciones de envío» distinto de «Desactivar»). */
	private static function shipping_enabled(): bool {
		return ! function_exists( 'wc_shipping_enabled' ) || wc_shipping_enabled();
	}

	/**
	 * Metas de rastreo de la línea de envío que lee el plugin (sus constantes privadas, leídas sin tocarlas); si otra
	 * versión no las declara, las de 2.1.5.
	 */
	public static function meta_keys(): array {
		$keys      = Skydropx_Tool::DEFAULT_META;
		$constants = self::class_constants( Skydropx_Tool::LABEL_SERVICE );
		foreach ( array(
			'number'  => 'META_TRACKING_NUMBER',
			'company' => 'META_TRACKING_COMPANY',
			'url'     => 'META_TRACKING_URL',
		) as $key => $constant ) {
			if ( isset( $constants[ $constant ] ) && is_string( $constants[ $constant ] ) && '' !== $constants[ $constant ] ) {
				$keys[ $key ] = $constants[ $constant ];
			}
		}
		return $keys;
	}

	private static function class_constants( string $class ): array {
		if ( ! class_exists( $class ) ) {
			return array();
		}
		try {
			return ( new \ReflectionClass( $class ) )->getConstants();
		} catch ( \Throwable $e ) {
			return array();
		}
	}

	private static function has_method( string $class, string $method ): bool {
		return class_exists( $class ) && method_exists( $class, $method );
	}

	/** ID del método de envío y nombre de la zona del plugin. */
	private static function method_id(): string {
		return defined( 'SKYDROPX_SHIPPING_METHOD_ID' ) && is_string( SKYDROPX_SHIPPING_METHOD_ID ) ? SKYDROPX_SHIPPING_METHOD_ID : Skydropx_Tool::DEFAULT_METHOD_ID;
	}

	private static function zone_name(): string {
		return defined( 'SKYDROPX_SHIPPING_ZONE_NAME' ) && is_string( SKYDROPX_SHIPPING_ZONE_NAME ) ? SKYDROPX_SHIPPING_ZONE_NAME : Skydropx_Tool::DEFAULT_ZONE_NAME;
	}

	/** Clase del método de envío del plugin, ya cargada por WooCommerce (`woocommerce_shipping_init`), o ''. */
	private static function shipping_method_class(): string {
		$class = Skydropx_Tool::SHIPPING_METHOD;
		if ( ! class_exists( $class, false ) && function_exists( 'WC' ) && is_object( WC() ) && method_exists( WC(), 'shipping' ) ) {
			WC()->shipping();
		}
		return class_exists( $class, false ) && method_exists( $class, 'calculate_shipping' ) ? $class : '';
	}

	// =================================================================================================================
	// status.

	private static function do_status( array $plan ): array {
		$warnings  = array();
		$missing   = self::compat();
		$linked    = self::linked();
		$quotation = self::quotation();
		$zone      = self::zone_info();
		$origin    = self::origin();
		$webhooks  = self::webhooks();
		$pretty    = '' !== trim( (string) get_option( 'permalink_structure', '' ) );
		$shipping  = self::shipping_enabled();
		if ( ! $shipping ) {
			$warnings[] = 'Los envíos están desactivados en WooCommerce (Ajustes → General → «Ubicaciones de envío»): el carrito no calcula envíos, así que no hay tarifas de Skydropx ni se carga su método.';
		}
		if ( $missing ) {
			$warnings[] = 'Esta versión del plugin de Skydropx no trae algunas piezas que la herramienta usa (' . implode( ', ', $missing ) . '): las acciones que las necesitan lo dirán; lo demás funciona.';
		}
		if ( false === $linked ) {
			$warnings[] = 'La tienda no está vinculada con Skydropx: un administrador lo hace en el menú «Skydropx» del escritorio («Vincular mi tienda»).';
		}
		if ( false === $quotation['enabled'] || null === $quotation['enabled'] ) {
			$warnings[] = 'La cotización en el carrito está desactivada: la activa la plataforma desde el panel de Skydropx; hasta entonces el carrito no muestra sus tarifas.';
		} elseif ( ! $quotation['url_set'] ) {
			$warnings[] = 'La cotización está activa pero la plataforma no ha fijado su URL: el plugin no cotiza hasta que Skydropx la envíe.';
		}
		if ( $shipping && ! $zone['found'] && ! $zone['instances'] ) {
			$warnings[] = 'No hay ninguna zona de envío con el método de Skydropx: la plataforma la crea al activar la cotización.';
		} elseif ( $zone['found'] && ! $zone['first'] ) {
			$warnings[] = 'La zona «' . $zone['name'] . '» no es la primera: una zona anterior que cubra la misma dirección gana y el carrito no ofrecería las tarifas de Skydropx.';
		}
		if ( $origin['missing'] ) {
			$warnings[] = 'Faltan datos de la dirección de la tienda (' . implode( ', ', $origin['missing'] ) . ')' . ( in_array( 'postcode', $origin['missing'], true ) ? ': sin código postal de origen el plugin no cotiza.' : '.' );
		}
		if ( ! $pretty ) {
			$warnings[] = 'Los enlaces permanentes están en «Simple»: Skydropx necesita «Nombre de la entrada» para comunicarse con la tienda.';
		}
		if ( is_array( $webhooks ) && 0 === $webhooks['active'] ) {
			$warnings[] = 'No hay webhooks activos de Skydropx: los pedidos nuevos no se le avisan al momento (la plataforma los crea al vincular la tienda).';
		}
		// Las zonas, sus ubicaciones y los webhooks son ajustes de la tienda: `store-settings` los reserva a quien la
		// administra. A los demás (sólo productos o sólo pedidos), un resumen que basta para los avisos.
		$summary = ! current_user_can( 'manage_woocommerce' );
		if ( $summary ) {
			$zone     = array(
				'found'          => $zone['found'],
				'first'          => $zone['first'],
				'method_enabled' => is_array( $zone['method'] ) && ! empty( $zone['method']['enabled'] ),
			);
			$webhooks = is_array( $webhooks ) ? array( 'active' => $webhooks['active'] > 0 ) : null;
		}
		return array(
			'ok'       => true,
			'tool'     => Skydropx_Tool::TOOL,
			'action'   => 'status',
			'data'     => array(
				'detail'        => $summary ? 'summary' : 'full',
				'shipping'      => $shipping,
				'plugin'        => array(
					'version'     => defined( 'SKYDROPX_VERSION' ) ? (string) SKYDROPX_VERSION : '',
					'tested_with' => '2.1.5',
					'missing'     => $missing,
				),
				'linked'        => $linked,
				'quotation'     => $quotation,
				'zone'          => $zone,
				'units'         => array(
					'weight'       => (string) get_option( 'woocommerce_weight_unit', 'kg' ),
					'dimension'    => (string) get_option( 'woocommerce_dimension_unit', 'cm' ),
					'plugin_sends' => array(
						'weight'    => 'kg',
						'dimension' => 'cm',
					),
				),
				'origin'        => $origin,
				'permalinks'    => array( 'pretty' => $pretty ),
				'webhooks'      => $webhooks,
				'tracking_meta' => array_values( self::meta_keys() ),
				'read_only'     => 'La vinculación, las claves de la API y la URL de cotización las fija la plataforma: aquí sólo se leen (sin valores).',
			),
			'warnings' => $warnings,
		);
	}

	/** ¿Tiene la tienda su clave de Skydropx? (sin leerla: el método del plugin sólo responde sí o no). null: no se sabe. */
	private static function linked() {
		if ( ! self::has_method( Skydropx_Tool::REPOSITORY, 'has_plugin_api_keys' ) ) {
			return null;
		}
		try {
			$class = Skydropx_Tool::REPOSITORY;
			return (bool) ( new $class() )->has_plugin_api_keys();
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/** Cotización: activa, y si la plataforma fijó su URL (sólo el host, nunca la ruta ni la consulta). */
	private static function quotation(): array {
		$out   = array(
			'enabled'  => null,
			'url_set'  => false,
			'url_host' => '',
			'https'    => false,
		);
		$class = Skydropx_Tool::QUOTATION;
		if ( self::has_method( $class, 'get_enabled' ) ) {
			$enabled        = call_user_func( array( $class, 'get_enabled' ) );
			$out['enabled'] = null === $enabled ? null : (bool) $enabled;
		}
		if ( self::has_method( $class, 'get_base_url' ) ) {
			$url = call_user_func( array( $class, 'get_base_url' ) );
			if ( is_string( $url ) && '' !== $url ) {
				$out['url_set']  = true;
				$host            = wp_parse_url( $url, PHP_URL_HOST );
				$out['url_host'] = is_string( $host ) ? $host : '';
				$out['https']    = 0 === stripos( $url, 'https://' );
			}
		}
		return $out;
	}

	/** Zona del plugin, su método y las zonas que la preceden; las instancias del método en cualquier zona. */
	private static function zone_info(): array {
		$out = array(
			'name'            => self::zone_name(),
			'found'           => false,
			'id'              => 0,
			'position'        => 0,
			'first'           => false,
			'locations'       => array(),
			'locations_total' => 0,
			'method'          => null,
			'zones_before'    => array(),
			'instances'       => array(),
		);
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $out;
		}
		$method_id = self::method_id();
		$position  = 0;
		$before    = array();
		$zones     = \WC_Shipping_Zones::get_zones( 'admin' );
		$zones[]   = array(
			'zone_id'          => 0,
			'zone_name'        => 'Resto del mundo',
			'zone_locations'   => array(),
			'shipping_methods' => ( new \WC_Shipping_Zone( 0 ) )->get_shipping_methods( false, 'admin' ),
		);
		foreach ( $zones as $zone ) {
			$zone_id = (int) $zone['zone_id'];
			$name    = (string) $zone['zone_name'];
			$methods = is_array( $zone['shipping_methods'] ) ? $zone['shipping_methods'] : array();
			if ( $zone_id > 0 ) {
				++$position;
			}
			$mine = null;
			foreach ( $methods as $method ) {
				if ( is_object( $method ) && isset( $method->id ) && $method_id === $method->id ) {
					$mine                 = array(
						'instance_id' => (int) $method->instance_id,
						'enabled'     => method_exists( $method, 'is_enabled' ) ? (bool) $method->is_enabled() : 'yes' === $method->enabled,
						'title'       => method_exists( $method, 'get_title' ) ? (string) $method->get_title() : '',
					);
					$out['instances'][] = array(
						'zone_id'     => $zone_id,
						'zone_name'   => $name,
						'instance_id' => $mine['instance_id'],
						'enabled'     => $mine['enabled'],
					);
				}
			}
			if ( ! $out['found'] && $zone_id > 0 && $name === $out['name'] ) {
				$locations              = array();
				foreach ( (array) $zone['zone_locations'] as $location ) {
					$location    = (array) $location;
					$locations[] = array(
						'code' => isset( $location['code'] ) ? (string) $location['code'] : '',
						'type' => isset( $location['type'] ) ? (string) $location['type'] : '',
					);
				}
				$out['found']           = true;
				$out['id']              = $zone_id;
				$out['position']        = $position;
				$out['first']           = 1 === $position;
				$out['locations']       = array_slice( $locations, 0, 50 );
				$out['locations_total'] = count( $locations );
				$out['method']          = $mine;
				$out['zones_before']    = $before;
			}
			if ( $zone_id > 0 && ! $out['found'] ) {
				$before[] = array(
					'id'   => $zone_id,
					'name' => $name,
				);
			}
		}
		return $out;
	}

	/** Dirección de la tienda (origen de los envíos) y lo que falta. */
	private static function origin(): array {
		$raw     = explode( ':', (string) get_option( 'woocommerce_default_country', '' ) );
		$origin  = array(
			'address'   => (string) get_option( 'woocommerce_store_address', '' ),
			'address_2' => (string) get_option( 'woocommerce_store_address_2', '' ),
			'city'      => (string) get_option( 'woocommerce_store_city', '' ),
			'postcode'  => (string) get_option( 'woocommerce_store_postcode', '' ),
			'country'   => isset( $raw[0] ) ? (string) $raw[0] : '',
			'state'     => isset( $raw[1] ) ? (string) $raw[1] : '',
		);
		$missing = array();
		foreach ( array( 'address', 'city', 'postcode', 'country', 'state' ) as $key ) {
			if ( '' === trim( $origin[ $key ] ) ) {
				$missing[] = $key;
			}
		}
		$origin['complete'] = ! $missing;
		$origin['missing']  = $missing;
		return $origin;
	}

	/** Webhooks de la tienda hacia la plataforma: cuántos y de qué temas (nunca su URL ni su secreto). null: no se sabe. */
	private static function webhooks() {
		if ( ! class_exists( 'WC_Data_Store' ) || ! function_exists( 'wc_get_webhook' ) ) {
			return null;
		}
		$host = defined( 'SKYDROPX_ECOMMERCE_URL' ) ? wp_parse_url( (string) SKYDROPX_ECOMMERCE_URL, PHP_URL_HOST ) : '';
		$host = is_string( $host ) && '' !== $host ? strtolower( $host ) : Skydropx_Tool::DEFAULT_HOST;
		$out  = array(
			'total'  => 0,
			'active' => 0,
			'topics' => array(),
		);
		try {
			// `WC_Data_Store` reenvía sus métodos con `__call`: `method_exists()` daría falso.
			$store = \WC_Data_Store::load( 'webhook' );
			$ids   = is_callable( array( $store, 'get_webhooks_ids' ) ) ? (array) $store->get_webhooks_ids() : array();
		} catch ( \Throwable $e ) {
			return null;
		}
		foreach ( $ids as $id ) {
			$webhook = wc_get_webhook( (int) $id );
			if ( ! $webhook ) {
				continue;
			}
			$delivery = wp_parse_url( (string) $webhook->get_delivery_url(), PHP_URL_HOST );
			if ( ! is_string( $delivery ) || strtolower( $delivery ) !== $host ) {
				continue;
			}
			++$out['total'];
			if ( 'active' === $webhook->get_status() ) {
				++$out['active'];
				$out['topics'][] = (string) $webhook->get_topic();
			}
		}
		$out['topics'] = array_values( array_unique( $out['topics'] ) );
		sort( $out['topics'] );
		return $out;
	}

	// =================================================================================================================
	// audit_products.

	/**
	 * La página 1 recorre la tienda; las siguientes (5 minutos, el mismo usuario y los mismos `checks` y `after_id`) leen
	 * ese recorrido, guardado en compacto (ID y campos que faltan), y sólo cargan los productos de la página pedida.
	 * `update_products` lo borra.
	 */
	private static function do_audit_products( array $plan ): array {
		$checks = null !== $plan['checks'] ? $plan['checks'] : self::default_checks();
		$key    = self::AUDIT_CACHE . (int) get_current_user_id();
		$sig    = md5( (string) wp_json_encode( array( $checks, $plan['after_id'] ) ) );
		$scan   = null;
		if ( $plan['page'] > 1 ) {
			$stored = get_transient( $key );
			$scan   = is_array( $stored ) && isset( $stored['sig'], $stored['issues'] ) && $sig === $stored['sig'] ? $stored : null;
		}
		$cached = null !== $scan;
		if ( ! $cached ) {
			$scan        = self::audit_scan( $checks, $plan['after_id'] );
			$scan['sig'] = $sig;
			set_transient( $key, $scan, self::AUDIT_TTL );
		}
		$per_page = $plan['per_page'];
		$rows     = array();
		foreach ( array_slice( $scan['issues'], ( $plan['page'] - 1 ) * $per_page, $per_page ) as $issue ) {
			$product = wc_get_product( $issue[0] );
			$rows[]  = $product ? self::product_row( $product, $issue[1] ) : array(
				'id'      => (int) $issue[0],
				'missing' => $issue[1],
				'gone'    => true,
			);
		}
		$total  = count( $scan['issues'] );
		$result = array(
			'ok'          => true,
			'tool'        => Skydropx_Tool::TOOL,
			'action'      => 'audit_products',
			'data'        => $rows,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $plan['page'],
			'per_page'    => $per_page,
			'cached'      => $cached,
			'summary'     => array(
				'scanned'         => $scan['scanned'],
				'skipped_virtual' => $scan['virtual'],
				'with_issues'     => $total,
				'missing'         => array_intersect_key( $scan['counts'], array_flip( self::fields_of( $checks ) ) ),
				'checks'          => $checks,
				'after_id'        => $plan['after_id'],
				'units'           => array(
					'weight'    => (string) get_option( 'woocommerce_weight_unit', 'kg' ),
					'dimension' => (string) get_option( 'woocommerce_dimension_unit', 'cm' ),
				),
			),
			'notice'      => 'Una variación sin peso o medidas propios usa los de su producto padre: ponerlos en el padre (update_products con su product_id) corrige todas las que heredan. Los productos virtuales o descargables no se cotizan.' . ( $cached ? ' Esta página sale del recorrido de la página 1 (de hace menos de 5 minutos); page 1 vuelve a revisar la tienda.' : '' ),
		);
		if ( $scan['next_after_id'] ) {
			$result['truncated']     = true;
			$result['next_after_id'] = $scan['next_after_id'];
			$result['notice']       .= sprintf( ' La tienda tiene más de %d productos y variaciones publicados: se revisaron %d, en orden de ID; sigue con after_id: %d.', self::MAX_SCAN, self::MAX_SCAN, $scan['next_after_id'] );
		}
		return $result;
	}

	/**
	 * Recorre hasta `MAX_SCAN` productos y variaciones (orden de ID, después de `$after_id`) en bloques de 100: precarga
	 * entradas, metas y términos de cada bloque y de sus padres (una variación lee los datos del padre) y libera la memoria
	 * de la petición al terminar cada uno.
	 */
	private static function audit_scan( array $checks, int $after_id ): array {
		$all = self::quotable_ids();
		if ( $after_id > 0 ) {
			$all = array_values(
				array_filter(
					$all,
					static function ( $id ) use ( $after_id ) {
						return $id > $after_id;
					}
				)
			);
		}
		$ids    = array_slice( $all, 0, self::MAX_SCAN );
		$scan   = array(
			'issues'        => array(),
			'counts'        => array_fill_keys( array( 'weight', 'length', 'width', 'height', 'shipping_class' ), 0 ),
			'scanned'       => 0,
			'virtual'       => 0,
			'next_after_id' => count( $all ) > self::MAX_SCAN ? (int) end( $ids ) : 0,
		);
		$prime  = function_exists( '_prime_post_caches' );
		foreach ( array_chunk( $ids, 100 ) as $chunk ) {
			if ( $prime ) {
				_prime_post_caches( $chunk, true, true );
				$parents = array();
				foreach ( $chunk as $id ) {
					$parent = (int) wp_get_post_parent_id( $id );
					if ( $parent > 0 ) {
						$parents[ $parent ] = $parent;
					}
				}
				if ( $parents ) {
					_prime_post_caches( array_values( $parents ), true, true );
				}
			}
			foreach ( $chunk as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product ) {
					continue;
				}
				++$scan['scanned'];
				if ( $product->is_virtual() || $product->is_downloadable() ) {
					// El plugin no los manda a cotizar (descargables o virtuales).
					++$scan['virtual'];
					continue;
				}
				$missing = self::missing_of( $product, $checks );
				if ( $missing ) {
					foreach ( $missing as $field ) {
						++$scan['counts'][ $field ];
					}
					$scan['issues'][] = array( (int) $id, $missing );
				}
			}
			self::release( $chunk );
		}
		return $scan;
	}

	/**
	 * Saca de la memoria de la petición lo ya revisado: con una caché persistente que lo permite, vacía su capa en
	 * memoria (lo demás vuelve de la caché, no de la base); si no, borra las entradas, metas y términos del bloque.
	 */
	private static function release( array $ids ): void {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_runtime' ) ) {
			wp_cache_flush_runtime();
			return;
		}
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			return; // Sin poder vaciar sólo la memoria, no se borra nada de la caché compartida.
		}
		foreach ( $ids as $id ) {
			wp_cache_delete( $id, 'posts' );
			wp_cache_delete( $id, 'post_meta' );
			foreach ( array( 'product_type', 'product_visibility', 'product_cat', 'product_tag', 'product_shipping_class' ) as $taxonomy ) {
				wp_cache_delete( $id, $taxonomy . '_relationships' );
			}
		}
	}

	/** Por defecto, lo que la cotización de Skydropx usa: peso y medidas (la clase de envío, sólo si se pide). */
	private static function default_checks(): array {
		return array( 'weight', 'dimensions' );
	}

	private static function fields_of( array $checks ): array {
		$fields = array();
		foreach ( $checks as $check ) {
			$fields = array_merge( $fields, 'dimensions' === $check ? array( 'length', 'width', 'height' ) : array( $check ) );
		}
		return $fields;
	}

	/**
	 * IDs que el carrito puede mandar a cotizar, en orden de ID: productos publicados que no son variables, agrupados ni
	 * externos, y las variaciones activas de los variables publicados (sólo IDs: barato aun en una tienda grande).
	 */
	private static function quotable_ids(): array {
		$base     = array(
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		$type     = static function ( array $terms, string $operator ): array {
			return array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => $terms,
					'operator' => $operator,
				),
			);
		};
		$simple   = get_posts( $base + array( 'post_type' => 'product', 'tax_query' => $type( array( 'variable', 'grouped', 'external' ), 'NOT IN' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$variable = get_posts( $base + array( 'post_type' => 'product', 'tax_query' => $type( array( 'variable' ), 'IN' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$children = $variable ? get_posts( $base + array( 'post_type' => 'product_variation', 'post_parent__in' => array_map( 'intval', $variable ) ) ) : array();
		$ids      = array_values( array_unique( array_merge( array_map( 'intval', $simple ), array_map( 'intval', $children ) ) ) );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	/** Lo que le falta a un producto (con la herencia del padre, como lo lee el plugin). */
	private static function missing_of( $product, array $checks ): array {
		$missing = array();
		$values  = array(
			'weight' => $product->get_weight(),
			'length' => $product->get_length(),
			'width'  => $product->get_width(),
			'height' => $product->get_height(),
		);
		foreach ( self::fields_of( $checks ) as $field ) {
			if ( 'shipping_class' === $field ) {
				if ( ! $product->get_shipping_class_id() ) {
					$missing[] = 'shipping_class';
				}
				continue;
			}
			if ( ! self::stored_measure( $values[ $field ] ) ) {
				$missing[] = $field;
			}
		}
		return $missing;
	}

	/** Fila compacta de un producto con lo que le falta (los valores son los que el plugin leería, con herencia). */
	private static function product_row( $product, array $missing ): array {
		$parent = (int) $product->get_parent_id();
		$row    = array(
			'id'             => (int) $product->get_id(),
			'type'           => (string) $product->get_type(),
			'name'           => (string) $product->get_name(),
			'sku'            => (string) $product->get_sku(),
			'missing'        => $missing,
			'weight'         => (string) $product->get_weight(),
			'length'         => (string) $product->get_length(),
			'width'          => (string) $product->get_width(),
			'height'         => (string) $product->get_height(),
			'shipping_class' => (string) $product->get_shipping_class(),
		);
		if ( $parent > 0 ) {
			$row['parent_id'] = $parent;
			$row['fix']       = array(
				'product_id'   => $parent,
				'variation_id' => (int) $product->get_id(),
			);
		} else {
			$row['fix'] = array( 'product_id' => (int) $product->get_id() );
		}
		return $row;
	}

	// =================================================================================================================
	// update_products.

	private static function do_update_products( array $plan, $dispatch ) {
		$store_weight    = (string) get_option( 'woocommerce_weight_unit', 'kg' );
		$store_dimension = (string) get_option( 'woocommerce_dimension_unit', 'cm' );
		$from_weight     = '' !== $plan['weight_unit'] ? $plan['weight_unit'] : $store_weight;
		$from_dimension  = '' !== $plan['dimension_unit'] ? $plan['dimension_unit'] : $store_dimension;

		// Todo se comprueba antes de escribir: un producto que no existe, una clase de envío que no es el slug de una
		// existente (WooCommerce la busca por slug y, si no la encuentra, deja «ninguna» sin avisar) o un valor que en la
		// unidad de la tienda quedaría en cero detienen el lote entero.
		$groups   = array();
		$problems = array();
		$warnings = array();
		foreach ( $plan['products'] as $item ) {
			$target  = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
			$product = wc_get_product( $target );
			if ( ! $product ) {
				$problems[] = sprintf( 'items[%d]: no existe el producto %d.', $item['position'], $target );
				continue;
			}
			if ( $item['variation_id'] && ( ! $product->is_type( 'variation' ) || (int) $product->get_parent_id() !== $item['product_id'] ) ) {
				$problems[] = sprintf( 'items[%d]: %d no es una variación del producto %d.', $item['position'], $item['variation_id'], $item['product_id'] );
				continue;
			}
			if ( ! $item['variation_id'] && $product->is_type( 'variation' ) ) {
				$problems[] = sprintf( 'items[%d]: %d es una variación: manda product_id del padre (%d) y variation_id.', $item['position'], $target, (int) $product->get_parent_id() );
				continue;
			}
			if ( isset( $item['shipping_class'] ) && '' !== $item['shipping_class'] ) {
				$term = get_term_by( 'slug', $item['shipping_class'], 'product_shipping_class' );
				if ( ! $term ) {
					$named = get_term_by( 'name', $item['shipping_class'], 'product_shipping_class' );
					if ( ! $named ) {
						$problems[] = sprintf( 'items[%d]: no existe la clase de envío con el slug «%s» (catalog, resource shipping_class, las lista; créala ahí si hace falta).', $item['position'], $item['shipping_class'] );
						continue;
					}
					$warnings[]             = sprintf( 'items[%d]: «%s» es el nombre de la clase de envío, no su slug: se usó su slug «%s».', $item['position'], $item['shipping_class'], $named->slug );
					$item['shipping_class'] = (string) $named->slug;
				}
			} elseif ( isset( $item['shipping_class'] ) && $item['variation_id'] ) {
				$warnings[] = sprintf( 'items[%d]: en una variación, shipping_class vacío significa «la misma clase que el padre» (no «sin clase»).', $item['position'] );
			}
			$entry = array( 'id' => $target );
			$tiny  = array();
			if ( isset( $item['weight'] ) ) {
				$entry['weight'] = self::convert( 'weight', $item['weight'], $from_weight, $store_weight );
				if ( '' === $entry['weight'] ) {
					$tiny[] = 'weight';
				}
			}
			foreach ( array( 'length', 'width', 'height' ) as $field ) {
				if ( isset( $item[ $field ] ) ) {
					$entry['dimensions'][ $field ] = self::convert( 'dimension', $item[ $field ], $from_dimension, $store_dimension );
					if ( '' === $entry['dimensions'][ $field ] ) {
						$tiny[] = $field;
					}
				}
			}
			if ( $tiny ) {
				$problems[] = sprintf( 'items[%d]: %s quedaría en cero con cuatro decimales en la unidad de la tienda (%s, %s): revisa la unidad.', $item['position'], implode( ', ', $tiny ), $store_weight, $store_dimension );
				continue;
			}
			if ( isset( $item['shipping_class'] ) ) {
				$entry['shipping_class'] = $item['shipping_class'];
			}
			$route                                     = $item['variation_id'] ? Tools::REST . 'products/' . $item['product_id'] . '/variations/batch' : Tools::REST . 'products/batch';
			$groups[ $route ]['entries'][]             = $entry;
			$groups[ $route ]['items'][]               = $item;
		}
		if ( $problems ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_items', 'No se cambió nada. ' . implode( ' ', array_slice( $problems, 0, 10 ) ) . ( count( $problems ) > 10 ? sprintf( ' (y %d más)', count( $problems ) - 10 ) : '' ) );
		}

		$results = array();
		$done    = 0;
		$failed  = 0;
		$first   = '';
		foreach ( $groups as $route => $group ) {
			list( $status, $data ) = call_user_func( $dispatch, 'POST', $route, array(), array( 'update' => $group['entries'] ) );
			if ( $status >= 400 ) {
				$error = Executor::woo_error( (int) $status, $data );
				foreach ( $group['items'] as $item ) {
					$results[] = self::item_result( $item, null, $error->get_error_code(), $error->get_error_message() );
					++$failed;
				}
				$first = '' !== $first ? $first : (string) $error->get_error_code();
				continue;
			}
			list( $data ) = Executor::batch_summary( $data );
			$updated      = is_array( $data ) && isset( $data['update'] ) && is_array( $data['update'] ) ? array_values( $data['update'] ) : array();
			foreach ( $group['items'] as $index => $item ) {
				$answer = isset( $updated[ $index ] ) && is_array( $updated[ $index ] ) ? $updated[ $index ] : array();
				if ( isset( $answer['error'] ) || ! $answer ) {
					$code      = isset( $answer['error']['code'] ) ? (string) $answer['error']['code'] : 'digitalisimo_woo_mcp_batch_item';
					$results[] = self::item_result( $item, null, $code, isset( $answer['error']['message'] ) ? (string) $answer['error']['message'] : 'WooCommerce no respondió por este producto.' );
					$first     = '' !== $first ? $first : $code;
					++$failed;
					continue;
				}
				$results[] = self::item_result( $item, $answer, '', '' );
				++$done;
			}
		}
		usort(
			$results,
			static function ( $a, $b ) {
				return $a['position'] <=> $b['position'];
			}
		);
		if ( 0 === $done ) {
			return self::error( '' !== $first ? $first : 'digitalisimo_woo_mcp_batch_failed', sprintf( 'WooCommerce no aplicó ningún producto del lote (%d con error). El primero (items[%d]): %s', $failed, $results[0]['position'], $results[0]['error']['message'] ), 400 );
		}
		$result = array(
			'ok'       => 0 === $failed,
			'tool'     => Skydropx_Tool::TOOL,
			'action'   => 'update_products',
			'data'     => $results,
			'batch'    => array(
				'done'   => $done,
				'failed' => $failed,
			),
			'units'    => array(
				'sent'  => array(
					'weight'    => $from_weight,
					'dimension' => $from_dimension,
				),
				'store' => array(
					'weight'    => $store_weight,
					'dimension' => $store_dimension,
				),
			),
		);
		// Lo guardado ya no es lo que vieron la auditoría ni la cotización de este usuario: se vuelven a calcular.
		delete_transient( self::AUDIT_CACHE . (int) get_current_user_id() );
		delete_transient( self::QUOTE_CACHE . (int) get_current_user_id() );
		if ( $warnings ) {
			$result['warnings'] = $warnings;
		}
		if ( $failed ) {
			$result['partial'] = true;
			$result['code']    = $first;
			$result['notice']  = sprintf( 'WooCommerce aplicó %d de %d productos; los errores van en cada elemento.', $done, $done + $failed );
		}
		return $result;
	}

	/** Resultado de un elemento del lote: lo que quedó guardado (en las unidades de la tienda) o su error. */
	private static function item_result( array $item, $answer, string $code, string $message ): array {
		$out = array(
			'position'     => $item['position'],
			'product_id'   => $item['product_id'],
			'variation_id' => $item['variation_id'],
			'ok'           => null !== $answer,
		);
		if ( null === $answer ) {
			$out['error'] = array(
				'code'    => $code,
				'message' => $message,
			);
			return $out;
		}
		$out['weight']         = isset( $answer['weight'] ) ? (string) $answer['weight'] : '';
		$out['dimensions']     = isset( $answer['dimensions'] ) && is_array( $answer['dimensions'] ) ? array_intersect_key( $answer['dimensions'], array_flip( array( 'length', 'width', 'height' ) ) ) : array();
		$out['shipping_class'] = isset( $answer['shipping_class'] ) ? (string) $answer['shipping_class'] : '';
		return $out;
	}

	/** Un valor convertido a la unidad de la tienda (con las funciones de WooCommerce) y escrito sin ceros sobrantes. */
	private static function convert( string $kind, float $value, string $from, string $to ): string {
		if ( $from !== $to ) {
			$value = 'weight' === $kind ? (float) wc_get_weight( $value, $to, $from ) : (float) wc_get_dimension( $value, $to, $from );
		}
		$text = rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' );
		return '' === $text || '0' === $text ? '' : $text;
	}

	// =================================================================================================================
	// quote.

	private static function do_quote( array $plan ) {
		if ( ! self::shipping_enabled() ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_shipping_off', 'Los envíos están desactivados en WooCommerce (Ajustes → General → «Ubicaciones de envío»): el carrito no calcula envíos ni carga el método de Skydropx.', 409 );
		}
		$class = self::shipping_method_class();
		if ( '' === $class ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_compat', 'Esta versión del plugin de Skydropx no trae su método de envío (' . Skydropx_Tool::SHIPPING_METHOD . '::calculate_shipping()): la cotización de prueba no está disponible.', 501 );
		}
		if ( ! class_exists( 'WC_Cart' ) || ! class_exists( 'WC_Session' ) || ! class_exists( 'WC_Shipping_Zones' ) || ! function_exists( 'WC' ) ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_compat', 'WooCommerce no cargó su carrito ni sus zonas en esta petición: la cotización de prueba no está disponible.', 501 );
		}
		$quotation = self::quotation();
		if ( true !== $quotation['enabled'] ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_quotation_off', 'La cotización en el carrito está desactivada en Skydropx (la activa la plataforma desde su panel): el plugin no cotiza y el carrito no muestra sus tarifas.', 409 );
		}
		if ( ! $quotation['url_set'] ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_quotation_url', 'La plataforma Skydropx no ha fijado la URL de cotización de esta tienda: el plugin no cotiza hasta que la envíe.', 409 );
		}
		$origin = self::origin();
		if ( '' === trim( $origin['postcode'] ) ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_origin', 'La tienda no tiene código postal de origen (WooCommerce → Ajustes → General): sin él el plugin no cotiza.', 409 );
		}

		// Artículos: lo que el plugin leerá del carrito.
		$warnings = array();
		$lines    = array();
		$items    = array();
		$subtotal = 0.0;
		$with_tax = 0.0;
		foreach ( $plan['cart'] as $index => $line ) {
			$target  = $line['variation_id'] ? $line['variation_id'] : $line['product_id'];
			$product = wc_get_product( $target );
			$published = 'publish' === get_post_status( $line['product_id'] ) && ( ! $line['variation_id'] || 'publish' === get_post_status( $line['variation_id'] ) );
			if ( ! $product || ! $published ) {
				return self::error( 'digitalisimo_woo_mcp_skydropx_cart', sprintf( 'cart[%d]: el producto %d no existe o no está publicado.', $index, $target ), 404 );
			}
			if ( $line['variation_id'] && ( ! $product->is_type( 'variation' ) || (int) $product->get_parent_id() !== $line['product_id'] ) ) {
				return self::error( 'digitalisimo_woo_mcp_skydropx_cart', sprintf( 'cart[%d]: %d no es una variación del producto %d.', $index, $line['variation_id'], $line['product_id'] ) );
			}
			if ( ! $line['variation_id'] && ( $product->is_type( 'variable' ) || $product->is_type( 'variation' ) ) ) {
				return self::error( 'digitalisimo_woo_mcp_skydropx_cart', sprintf( 'cart[%d]: un producto variable se cotiza por variación: manda variation_id.', $index ) );
			}
			if ( $product->is_virtual() || $product->is_downloadable() ) {
				$warnings[] = sprintf( 'cart[%d] (%d) es virtual o descargable: el plugin no lo manda a cotizar.', $index, $target );
			} else {
				$missing = self::missing_of( $product, array( 'weight', 'dimensions' ) );
				if ( $missing ) {
					$warnings[] = sprintf( 'cart[%d] (%d) no tiene %s: el plugin lo manda en cero y la cotización puede fallar o salir mal (corrígelo con update_products).', $index, $target, implode( ', ', $missing ) );
				}
				$items[] = array(
					'product_id'   => $line['product_id'],
					'variation_id' => $line['variation_id'],
					'quantity'     => $line['quantity'],
					'weight_kg'    => self::number( wc_get_weight( (float) $product->get_weight(), 'kg' ) ),
					'length_cm'    => self::number( wc_get_dimension( (float) $product->get_length(), 'cm' ) ),
					'width_cm'     => self::number( wc_get_dimension( (float) $product->get_width(), 'cm' ) ),
					'height_cm'    => self::number( wc_get_dimension( (float) $product->get_height(), 'cm' ) ),
				);
			}
			$excl      = (float) wc_get_price_excluding_tax( $product, array( 'qty' => $line['quantity'] ) );
			$incl      = (float) wc_get_price_including_tax( $product, array( 'qty' => $line['quantity'] ) );
			$subtotal += $excl;
			$with_tax += $incl;
			$key       = 'digi-quote-' . $index;
			$lines[ $key ] = array(
				'key'           => $key,
				'product_id'    => $line['product_id'],
				'variation_id'  => $line['variation_id'],
				'variation'     => array(),
				'quantity'      => $line['quantity'],
				'data'          => $product,
				'data_hash'     => '',
				'line_total'    => $excl,
				'line_subtotal' => $excl,
				'line_tax'      => $incl - $excl,
			);
		}
		$store       = explode( ':', (string) get_option( 'woocommerce_default_country', '' ) );
		$address     = $plan['address'];
		$destination = array(
			'country'   => isset( $address['country'] ) ? $address['country'] : strtoupper( (string) $store[0] ),
			'state'     => isset( $address['state'] ) ? $address['state'] : '',
			'postcode'  => $address['postcode'],
			'city'      => isset( $address['city'] ) ? $address['city'] : '',
			'address'   => isset( $address['address_1'] ) ? $address['address_1'] : '',
			'address_1' => isset( $address['address_1'] ) ? $address['address_1'] : '',
			'address_2' => isset( $address['address_2'] ) ? $address['address_2'] : '',
		);
		$package     = array(
			'contents'        => $lines,
			'contents_cost'   => $subtotal,
			'applied_coupons' => array(),
			'user'            => array( 'ID' => (int) get_current_user_id() ),
			'destination'     => $destination,
		);

		// Instancia del método: la de la zona que el carrito usaría con esta dirección; si esa zona no lo tiene, la
		// primera que lo tenga (y se avisa: el cliente no vería estas tarifas).
		$zone     = \WC_Shipping_Zones::get_zone_matching_package( $package );
		$method   = self::method_in( $zone->get_shipping_methods( true ), $class );
		$matches  = null !== $method;
		$zone_out = array(
			'id'      => (int) $zone->get_id(),
			'name'    => (string) $zone->get_zone_name(),
			'matches' => $matches,
		);
		if ( ! $method ) {
			foreach ( self::zone_info()['instances'] as $instance ) {
				$candidate = \WC_Shipping_Zones::get_shipping_method( $instance['instance_id'] );
				if ( $candidate instanceof $class ) {
					$method = $candidate;
					break;
				}
			}
			if ( ! $method ) {
				return self::error( 'digitalisimo_woo_mcp_skydropx_method', 'Ninguna zona de envío tiene el método de Skydropx: la plataforma lo crea al activar la cotización.', 409 );
			}
			$warnings[] = sprintf( 'Con esta dirección el carrito usa la zona «%s», que no tiene el método de Skydropx activo: el cliente no vería estas tarifas.', $zone_out['name'] );
		}

		// Cotiza con el método del plugin, con su caché: un carrito y una sesión que sólo existen durante la llamada.
		// La huella del plugin no cuenta peso ni medidas (como en un carrito real): la caché guardada aquí lleva además la
		// de los artículos tal como se mandan, y se descarta si cambiaron (p. ej. tras update_products).
		$cache_key = self::QUOTE_CACHE . (int) get_current_user_id();
		$print     = md5( (string) wp_json_encode( array( $items, $destination, round( $subtotal, 2 ) ) ) );
		$stored    = $plan['cache'] ? get_transient( $cache_key ) : false;
		$reuse     = is_array( $stored ) && isset( $stored['items'], $stored['data'] ) && $stored['items'] === md5( (string) wp_json_encode( $items ) ) && is_array( $stored['data'] );
		$session   = new Skydropx_Session( $reuse ? $stored['data'] : array() );
		$tax_cart  = 'incl' === get_option( 'woocommerce_tax_display_cart' ) ? $with_tax - $subtotal : 0.0;
		$cart      = new Skydropx_Cart( $lines, $subtotal, $tax_cart );
		$wc        = WC();
		$previous  = array( $wc->cart, $wc->session );
		$method->rates = array();
		try {
			$wc->cart    = $cart;
			$wc->session = $session;
			$method->calculate_shipping( $package );
		} finally {
			list( $wc->cart, $wc->session ) = $previous;
		}
		if ( $session->changed() ) {
			set_transient(
				$cache_key,
				array(
					'items' => md5( (string) wp_json_encode( $items ) ),
					'data'  => $session->data(),
				),
				self::QUOTE_TTL
			);
		}
		$rates = array();
		foreach ( (array) $method->rates as $rate ) {
			if ( ! is_object( $rate ) || ! method_exists( $rate, 'get_cost' ) ) {
				continue;
			}
			$meta    = method_exists( $rate, 'get_meta_data' ) ? (array) $rate->get_meta_data() : array();
			$rates[] = array(
				'id'       => (string) $rate->get_id(),
				'label'    => (string) $rate->get_label(),
				'cost'     => self::number( $rate->get_cost() ),
				'provider' => isset( $meta['provider'] ) ? (string) $meta['provider'] : '',
				'service'  => isset( $meta['service_level_name'] ) ? (string) $meta['service_level_name'] : '',
				'code'     => isset( $meta['service_level_code'] ) ? (string) $meta['service_level_code'] : '',
				'days'     => isset( $meta['days'] ) ? (string) $meta['days'] : '',
				'currency' => isset( $meta['currency_local'] ) ? (string) $meta['currency_local'] : '',
			);
		}
		usort(
			$rates,
			static function ( $a, $b ) {
				return $a['cost'] <=> $b['cost'];
			}
		);
		// El plugin no vuelve a cotizar la misma huella en la misma petición PHP (un cliente que encadena llamadas en una
		// sola petición): entonces valen las tarifas de la primera, no «sin tarifas».
		$repeated = false;
		if ( $rates ) {
			self::$quoted[ $print ] = $rates;
		} elseif ( isset( self::$quoted[ $print ] ) ) {
			$rates    = self::$quoted[ $print ];
			$repeated = true;
		}
		if ( $repeated ) {
			$warnings[] = 'Esta misma cotización ya se hizo en esta petición: el plugin no repite la llamada; son sus tarifas.';
		} elseif ( ! $rates ) {
			$warnings[] = 'Skydropx no devolvió tarifas para esta dirección y estos productos (el detalle queda en WooCommerce → Estado → Registros, fuente «Skydropx»).';
		}
		return array(
			'ok'       => true,
			'tool'     => Skydropx_Tool::TOOL,
			'action'   => 'quote',
			'data'     => array(
				'rates'      => $rates,
				'total'      => count( $rates ),
				'from_cache' => $repeated || ( $rates && ! $session->changed() ),
				'zone'       => $zone_out,
				'method'     => array(
					'instance_id' => (int) $method->get_instance_id(),
					'title'       => (string) $method->get_title(),
				),
				'package'    => array(
					'destination'   => array_intersect_key( $destination, array_flip( array( 'country', 'state', 'postcode', 'city' ) ) ),
					'items'         => $items,
					'contents_cost' => self::number( $subtotal ),
				),
			),
			'warnings' => $warnings,
		);
	}

	/** El método del plugin entre los de una zona, o null. */
	private static function method_in( $methods, string $class ) {
		foreach ( (array) $methods as $method ) {
			if ( $method instanceof $class ) {
				return $method;
			}
		}
		return null;
	}

	// =================================================================================================================
	// shipments, get_shipment.

	private static function do_shipments( array $plan, $dispatch ) {
		$query = array(
			'page'     => $plan['page'],
			'per_page' => $plan['per_page'],
			'_fields'  => 'id,number,status,date_created,shipping_lines',
		);
		if ( $plan['statuses'] ) {
			$query['status'] = $plan['statuses'];
		}
		foreach ( array( 'after', 'before' ) as $key ) {
			if ( '' !== $plan[ $key ] ) {
				$query[ $key ] = $plan[ $key ];
			}
		}
		list( $status, $data, $headers ) = call_user_func( $dispatch, 'GET', Tools::REST . 'orders', $query, null );
		if ( $status >= 400 ) {
			return Executor::woo_error( (int) $status, $data );
		}
		$keys = self::meta_keys();
		$rows = array();
		$with = 0;
		foreach ( is_array( $data ) ? $data : array() as $order ) {
			if ( ! is_array( $order ) ) {
				continue;
			}
			$row = self::shipment_row( $order, $keys );
			if ( $row['has_tracking'] ) {
				++$with;
			}
			if ( ( 'with' === $plan['tracking_filter'] && ! $row['has_tracking'] ) || ( 'without' === $plan['tracking_filter'] && $row['has_tracking'] ) ) {
				continue;
			}
			$rows[] = $row;
		}
		$result = array(
			'ok'       => true,
			'tool'     => Skydropx_Tool::TOOL,
			'action'   => 'shipments',
			'data'     => $rows,
			'page'     => $plan['page'],
			'per_page' => $plan['per_page'],
			'summary'  => array(
				'orders_in_page' => is_array( $data ) ? count( $data ) : 0,
				'with_tracking'  => $with,
			),
		);
		$total = Executor::header( $headers, 'X-WP-Total' );
		$pages = Executor::header( $headers, 'X-WP-TotalPages' );
		if ( null !== $total ) {
			$result['total'] = $total;
		}
		if ( null !== $pages ) {
			$result['total_pages'] = $pages;
		}
		if ( 'any' !== $plan['tracking_filter'] ) {
			$result['notice'] = 'tracking_filter se aplica dentro de la página pedida: total y total_pages cuentan todos los pedidos de los filtros de estado y fechas; sigue con page para ver los demás.';
		}
		return $result;
	}

	private static function do_get_shipment( array $plan, $dispatch ) {
		// El permiso sobre este pedido, también aquí (la REST lo vuelve a comprobar al leerlo).
		$order = self::order_access( $plan['id'], 'read' );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		list( $status, $data ) = call_user_func( $dispatch, 'GET', Tools::REST . 'orders/' . $plan['id'], array( '_fields' => 'id,number,status,date_created,date_modified,shipping,shipping_lines,shipping_total' ), null );
		if ( $status >= 400 ) {
			return Executor::woo_error( (int) $status, $data );
		}
		$data = is_array( $data ) ? $data : array();
		$row  = self::shipment_row( $data, self::meta_keys() );
		$ship = isset( $data['shipping'] ) && is_array( $data['shipping'] ) ? $data['shipping'] : array();
		if ( 'manual' !== $row['label'] ) {
			$row['label'] = self::label_available( $order, $row['label'] );
		}
		$row['date_modified']  = isset( $data['date_modified'] ) ? (string) $data['date_modified'] : '';
		$row['shipping_total'] = isset( $data['shipping_total'] ) ? (string) $data['shipping_total'] : '';
		$row['destination']    = array_intersect_key( $ship, array_flip( array( 'city', 'state', 'postcode', 'country' ) ) );
		$row['edit_url']       = method_exists( $order, 'get_edit_order_url' ) ? (string) $order->get_edit_order_url() : '';
		$notices               = array(
			'manual' => 'Rastreo registrado a mano: no hay guía de Skydropx en PDF (la caja del plugin dice «Guía generada» porque sólo mira las tres metas).',
			'yes'    => 'Tiene guía de Skydropx: action label da el enlace para descargarla en el navegador.',
			'no'     => 'Sin guía: el plugin la muestra cuando la línea de envío tiene número, paquetería y URL de rastreo (los pone Skydropx al generarla, o set_tracking para un envío hecho a mano).',
		);
		return array(
			'ok'     => true,
			'tool'   => Skydropx_Tool::TOOL,
			'action' => 'get_shipment',
			'data'   => $row,
			'notice' => $notices[ 'manual' === $row['label'] ? 'manual' : ( $row['label'] ? 'yes' : 'no' ) ],
		);
	}

	/**
	 * Pedido de la REST → fila compacta con su rastreo: el de la primera línea de envío con número (si ninguna lo tiene, la
	 * primera con algún campo). `label`: true si el plugin muestra la guía (número, paquetería y URL), «manual» si ese
	 * rastreo lo registró `set_tracking` (no hay PDF de Skydropx) y false si no.
	 */
	private static function shipment_row( array $order, array $keys ): array {
		$lines = array();
		$best  = null;
		foreach ( isset( $order['shipping_lines'] ) && is_array( $order['shipping_lines'] ) ? $order['shipping_lines'] : array() as $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}
			$found  = array(
				'number'  => '',
				'company' => '',
				'url'     => '',
			);
			$manual = '';
			foreach ( isset( $line['meta_data'] ) && is_array( $line['meta_data'] ) ? $line['meta_data'] : array() as $meta ) {
				if ( ! is_array( $meta ) || ! isset( $meta['key'] ) || ! isset( $meta['value'] ) || ! is_scalar( $meta['value'] ) ) {
					continue;
				}
				if ( Skydropx_Tool::MANUAL_META === $meta['key'] ) {
					$manual = trim( (string) $meta['value'] );
					continue;
				}
				$field = array_search( (string) $meta['key'], $keys, true );
				if ( false !== $field ) {
					$found[ $field ] = trim( (string) $meta['value'] );
				}
			}
			$has     = '' !== $found['number'] || '' !== $found['company'] || '' !== $found['url'];
			$entry   = array(
				'id'           => isset( $line['id'] ) ? (int) $line['id'] : 0,
				'method_id'    => isset( $line['method_id'] ) ? (string) $line['method_id'] : '',
				'method_title' => isset( $line['method_title'] ) ? (string) $line['method_title'] : '',
				'total'        => isset( $line['total'] ) ? (string) $line['total'] : '',
				'tracking'     => $has ? $found : null,
				'manual'       => '' !== $found['number'] && $manual === $found['number'],
			);
			$lines[] = $entry;
			if ( $has && ( null === $best || ( '' === $best['tracking']['number'] && '' !== $found['number'] ) ) ) {
				$best = $entry;
			}
		}
		$tracking = null !== $best ? $best['tracking'] : null;
		$complete = null !== $tracking && '' !== $tracking['number'] && '' !== $tracking['company'] && '' !== $tracking['url'];
		return array(
			'id'             => isset( $order['id'] ) ? (int) $order['id'] : 0,
			'number'         => isset( $order['number'] ) ? (string) $order['number'] : '',
			'status'         => isset( $order['status'] ) ? (string) $order['status'] : '',
			'date_created'   => isset( $order['date_created'] ) ? (string) $order['date_created'] : '',
			'shipping_lines' => $lines,
			'has_tracking'   => null !== $tracking && '' !== $tracking['number'],
			'tracking'       => $tracking,
			'label'          => $complete && $best['manual'] ? 'manual' : $complete,
		);
	}

	/** ¿Muestra el plugin la guía de este pedido? Con su propio método si existe; si no, la misma regla. */
	private static function label_available( $order, bool $fallback ): bool {
		if ( ! self::has_method( Skydropx_Tool::LABEL_SERVICE, 'has_label_tracking_meta' ) ) {
			return $fallback;
		}
		try {
			$class = Skydropx_Tool::LABEL_SERVICE;
			return (bool) ( new $class() )->has_label_tracking_meta( $order );
		} catch ( \Throwable $e ) {
			return $fallback;
		}
	}

	/** Línea de envío del rastreo del pedido (la primera con número) y si ese rastreo se registró a mano. */
	private static function tracking_line( $order ): array {
		$keys = self::meta_keys();
		foreach ( $order->get_items( 'shipping' ) as $line ) {
			$number = trim( (string) $line->get_meta( $keys['number'], true ) );
			if ( '' !== $number ) {
				return array( $line, $number === trim( (string) $line->get_meta( Skydropx_Tool::MANUAL_META, true ) ) );
			}
		}
		return array( null, false );
	}

	// =================================================================================================================
	// label.

	private static function do_label( array $plan ) {
		$order = self::order_access( $plan['id'], 'read' );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$ui = self::label_ui();
		if ( null === $ui ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_compat', 'Esta versión del plugin de Skydropx no trae su descarga de guías (' . Skydropx_Tool::LABEL_UI . '::add_orders_list_action() y PRINT_ACTION): descárgala desde el panel de Skydropx.', 501 );
		}
		if ( '' === self::print_url( $ui, $order ) ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_no_label', sprintf( 'El pedido %d no tiene guía en el plugin: se muestra cuando su línea de envío tiene número, paquetería y URL de rastreo (los pone Skydropx al generarla).', $plan['id'] ), 404 );
		}
		if ( self::tracking_line( $order )[1] ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_manual', sprintf( 'El envío del pedido %d se registró a mano (set_tracking): no hay guía de Skydropx en PDF. La caja del plugin dice «Guía generada» porque sólo mira las tres metas; su botón «Imprimir guía» no encontraría el PDF.', $plan['id'] ), 409 );
		}
		$user    = (int) get_current_user_id();
		$expires = time() + Skydropx_Tool::LABEL_TTL;
		return array(
			'ok'     => true,
			'tool'   => Skydropx_Tool::TOOL,
			'action' => 'label',
			'data'   => array(
				'order_id'   => $plan['id'],
				'url'        => add_query_arg(
					array(
						'action'   => Skydropx_Tool::LABEL_ACTION,
						'order_id' => $plan['id'],
						'exp'      => $expires,
						'sig'      => self::label_signature( $plan['id'], $user, $expires ),
					),
					admin_url( 'admin-post.php' )
				),
				'expires'    => gmdate( 'Y-m-d\TH:i:s\Z', $expires ),
				'order_url'  => method_exists( $order, 'get_edit_order_url' ) ? (string) $order->get_edit_order_url() : '',
			),
			'notice' => 'Abre url en el navegador con la sesión del mismo usuario (administrador o gerente de la tienda) durante la próxima hora: lleva al enlace de impresión del plugin de Skydropx (con su nonce y sus permisos), que descarga el PDF de la guía. Después de esa hora, o con otro usuario, muestra un botón para descargarla. En order_url está el pedido, con el botón «Imprimir guía» del plugin.',
		);
	}

	/** Firma del enlace de la guía: pedido, usuario y caducidad con las sales del sitio (`wp_hash()`); no es una clave. */
	private static function label_signature( int $order_id, int $user, int $expires ): string {
		return substr( wp_hash( 'skydropx-label|' . $order_id . '|' . $user . '|' . $expires, 'nonce' ), 0, 20 );
	}

	/** Interfaz de guías del plugin, si esta versión la trae. */
	private static function label_ui() {
		$class = Skydropx_Tool::LABEL_UI;
		if ( ! self::has_method( $class, 'add_orders_list_action' ) || ! defined( $class . '::PRINT_ACTION' ) ) {
			return null;
		}
		try {
			return new $class();
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/** El enlace de impresión del plugin para el usuario de esta petición (con su nonce), o '' si no hay guía. */
	private static function print_url( $ui, $order ): string {
		$action  = constant( Skydropx_Tool::LABEL_UI . '::PRINT_ACTION' );
		$actions = $ui->add_orders_list_action( array(), $order );
		$url     = is_array( $actions ) && isset( $actions[ $action ]['url'] ) && is_string( $actions[ $action ]['url'] ) ? $actions[ $action ]['url'] : '';
		// `wp_nonce_url()` lo devuelve escapado para HTML.
		return html_entity_decode( $url, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * `admin-post.php?action=digitalisimo_woo_skydropx_label&order_id=…&exp=…&sig=…`, abierto en el navegador. Sólo
	 * responde con el MCP del sitio activo y «WooCommerce en el MCP» distinto de apagado; sin sesión, al acceso. Con el
	 * permiso sobre ese pedido (como la REST) y una guía de Skydropx:
	 * - con la firma de `label` para este usuario y sin caducar, redirige al endpoint de impresión del plugin con un nonce
	 *   de esta sesión (el plugin comprueba su nonce y sus permisos, firma la petición y entrega el PDF);
	 * - sin ella (un enlace caducado, de otro usuario o puesto por otra página), sólo muestra un botón con ese enlace: un
	 *   GET ajeno no ejecuta nada.
	 */
	public static function handle_label(): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}
		if ( ! Module::mcp_enabled() || 'off' === Settings::mode() ) {
			wp_die( esc_html( 'Las herramientas de tienda del MCP no están activas en este sitio.' ), '', array( 'response' => 404 ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- la firma propia (o el botón) hace de nonce; el endpoint del plugin pide el suyo.
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
		$expires  = isset( $_GET['exp'] ) ? absint( wp_unslash( $_GET['exp'] ) ) : 0;
		$sig      = isset( $_GET['sig'] ) && is_string( $_GET['sig'] ) ? sanitize_key( wp_unslash( $_GET['sig'] ) ) : '';
		// phpcs:enable
		$hpos = class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && method_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$back = admin_url( $hpos ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html( 'No tienes permiso para ver las guías de los pedidos.' ), '', array( 'response' => 403 ) );
		}
		$order = $order_id > 0 && Skydropx_Tool::active() ? self::order_access( $order_id, 'read' ) : null;
		if ( is_wp_error( $order ) && 403 === ( $order->get_error_data()['status'] ?? 0 ) ) {
			wp_die( esc_html( $order->get_error_message() ), '', array( 'response' => 403 ) );
		}
		if ( ! $order || is_wp_error( $order ) ) {
			wp_die( esc_html( 'El pedido no existe o el plugin de Skydropx no está activo.' ), '', array( 'response' => 404 ) );
		}
		$back = method_exists( $order, 'get_edit_order_url' ) ? (string) $order->get_edit_order_url() : $back;
		$link = ' <a href="' . esc_url( $back ) . '">' . esc_html( 'Ver el pedido' ) . '</a>';
		$ui   = self::label_ui();
		$url  = null !== $ui ? self::print_url( $ui, $order ) : '';
		if ( '' === $url ) {
			wp_die( esc_html( 'Este pedido no tiene guía de Skydropx (o esta versión del plugin no permite descargarla desde aquí).' ) . $link, '', array( 'response' => 404 ) );
		}
		if ( self::tracking_line( $order )[1] ) {
			wp_die( esc_html( 'El envío de este pedido se registró a mano: no hay guía de Skydropx en PDF.' ) . $link, '', array( 'response' => 404 ) );
		}
		if ( $expires >= time() && '' !== $sig && hash_equals( self::label_signature( $order_id, (int) get_current_user_id(), $expires ), $sig ) ) {
			wp_safe_redirect( $url );
			exit;
		}
		wp_die(
			'<p>' . esc_html( sprintf( 'Guía de Skydropx del pedido %d.', $order_id ) ) . '</p><p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html( 'Descargar la guía' ) . '</a>' . $link . '</p>',
			esc_html( 'Guía de Skydropx' ),
			array( 'response' => 200 )
		);
	}

	// =================================================================================================================
	// set_tracking, resync.

	private static function do_set_tracking( array $plan, $dispatch ) {
		$order = self::order_access( $plan['id'], 'edit' );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		// El estado se comprueba antes de escribir nada (si no, el rastreo quedaría guardado y el estado no).
		if ( '' !== $plan['order_status'] && function_exists( 'wc_get_order_statuses' ) && ! array_key_exists( 'wc-' . $plan['order_status'], wc_get_order_statuses() ) ) {
			return self::error( 'digitalisimo_woo_mcp_value', sprintf( 'No se cambió nada: «%s» no es un estado de pedido de esta tienda (%s).', $plan['order_status'], implode( ', ', array_map( array( __CLASS__, 'unprefix_status' ), array_keys( wc_get_order_statuses() ) ) ) ) );
		}
		$keys  = self::meta_keys();
		$lines = $order->get_items( 'shipping' );
		$item  = null;
		if ( $plan['shipping_line_id'] ) {
			foreach ( $lines as $id => $line ) {
				if ( (int) $id === $plan['shipping_line_id'] ) {
					$item = $line;
				}
			}
			if ( ! $item ) {
				return self::error( 'digitalisimo_woo_mcp_skydropx_line', sprintf( 'El pedido %d no tiene la línea de envío %d (get_shipment las lista).', $plan['id'], $plan['shipping_line_id'] ), 404 );
			}
		} else {
			$item = self::tracking_line( $order )[0];
			if ( ! $item && $lines ) {
				$item = reset( $lines );
			}
		}
		if ( ! $item && ! $plan['create_shipping_line'] ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_no_line', sprintf( 'No se cambió nada: el pedido %d no tiene línea de envío, y el plugin de Skydropx guarda el rastreo en ella. Para añadir una sin costo («Envío»), que el cliente verá en su pedido y en sus correos, repite con create_shipping_line:true, sólo si el usuario lo pide.', $plan['id'] ), 409 );
		}
		$current = array();
		foreach ( $keys as $field => $meta ) {
			$current[ $field ] = $item ? trim( (string) $item->get_meta( $meta, true ) ) : '';
		}
		$next = array_merge( $current, $plan['tracking'] );
		$gaps = array_keys(
			array_filter(
				$next,
				static function ( $value ) {
					return '' === $value;
				}
			)
		);
		if ( $gaps ) {
			return self::error( 'digitalisimo_woo_mcp_skydropx_tracking', 'No se cambió nada: el plugin muestra la guía sólo con número, paquetería y URL de rastreo, y faltaría ' . implode( ', ', $gaps ) . ' (mándalo en tracking).' );
		}
		$created = false;
		if ( ! $item ) {
			// Sólo con create_shipping_line: una línea sin costo, sin recalcular los totales.
			$item = new \WC_Order_Item_Shipping();
			$item->set_method_title( 'Envío' );
			$item->set_method_id( 'digitalisimo_manual' );
			$item->set_total( 0 );
			$order->add_item( $item );
			$created = true;
		}
		$values = array(
			'number'  => $next['number'],
			'company' => sanitize_text_field( $next['company'] ),
			'url'     => esc_url_raw( $next['url'] ),
		);
		foreach ( $values as $field => $value ) {
			$item->update_meta_data( $keys[ $field ], $value );
		}
		// Un número nuevo (o uno que ya era manual) es un envío hecho a mano: se marca para no ofrecer un PDF que no existe.
		// Corregir paquetería o URL de una guía de Skydropx, con su mismo número, no la vuelve manual.
		$manual = $values['number'] !== $current['number'] || ( '' !== $current['number'] && $current['number'] === trim( (string) $item->get_meta( Skydropx_Tool::MANUAL_META, true ) ) );
		if ( $manual ) {
			$item->update_meta_data( Skydropx_Tool::MANUAL_META, $values['number'] );
		}
		// El CRUD de WooCommerce (HPOS o entradas): guarda la línea y el pedido, y dispara `woocommerce_update_order`.
		$order->save();

		$result   = array(
			'ok'     => true,
			'tool'   => Skydropx_Tool::TOOL,
			'action' => 'set_tracking',
			'data'   => array(
				'order_id'         => $plan['id'],
				'shipping_line_id' => (int) $item->get_id(),
				'created_line'     => $created,
				'tracking'         => $values,
				'label'            => $manual ? 'manual' : self::label_available( $order, true ),
				'edit_url'         => method_exists( $order, 'get_edit_order_url' ) ? (string) $order->get_edit_order_url() : '',
			),
		);
		$notices  = array();
		$errors   = array();
		if ( $manual ) {
			$notices[] = 'Rastreo registrado a mano: la caja del plugin de Skydropx en el pedido mostrará «Guía generada» e «Imprimir guía» (sólo mira las tres metas), pero no hay PDF de Skydropx para este envío; label lo dice.';
		}
		if ( $created ) {
			$notices[] = 'El pedido no tenía línea de envío: se añadió una sin costo («Envío»), que el cliente verá en su pedido y en sus correos; los totales no cambian.';
		}
		if ( '' !== $plan['order_status'] && $plan['order_status'] !== $order->get_status() ) {
			list( $status, $data ) = call_user_func( $dispatch, 'PUT', Tools::REST . 'orders/' . $plan['id'], array(), array( 'status' => $plan['order_status'] ) );
			if ( $status >= 400 ) {
				$errors['order_status'] = Executor::woo_error( (int) $status, $data );
			} else {
				$result['data']['order_status'] = is_array( $data ) && isset( $data['status'] ) ? (string) $data['status'] : $plan['order_status'];
			}
		}
		if ( '' !== $plan['customer_note'] ) {
			$note = sanitize_textarea_field( $plan['customer_note'] );
			if ( self::last_customer_note( $plan['id'] ) === trim( $note ) ) {
				// Un reintento no le manda al cliente otro correo con la misma nota.
				$result['data']['note_skipped'] = true;
				$notices[]                      = 'La nota al cliente no se repitió: es igual a la última que ya tiene el pedido.';
			} else {
				list( $status, $data ) = call_user_func(
					$dispatch,
					'POST',
					Tools::REST . 'orders/' . $plan['id'] . '/notes',
					array(),
					array(
						'note'          => $note,
						'customer_note' => true,
					)
				);
				if ( $status >= 400 ) {
					$errors['customer_note'] = Executor::woo_error( (int) $status, $data );
				} else {
					$result['data']['note_id'] = is_array( $data ) && isset( $data['id'] ) ? (int) $data['id'] : 0;
				}
			}
		}
		if ( $errors ) {
			$result['ok']      = false;
			$result['partial'] = true;
			$result['code']    = (string) reset( $errors )->get_error_code();
			$result['errors']  = array();
			foreach ( $errors as $step => $error ) {
				$result['errors'][ $step ] = array(
					'code'    => $error->get_error_code(),
					'message' => $error->get_error_message(),
				);
			}
			array_unshift( $notices, 'El rastreo quedó guardado, pero no todo lo demás: ver errors.' );
		}
		if ( $notices ) {
			$result['notice'] = implode( ' ', $notices );
		}
		return $result;
	}

	/** Texto de la última nota al cliente del pedido, o ''. */
	private static function last_customer_note( int $order_id ): string {
		if ( ! function_exists( 'wc_get_order_notes' ) ) {
			return '';
		}
		$notes = wc_get_order_notes(
			array(
				'order_id' => $order_id,
				'type'     => 'customer',
				'limit'    => 1,
			)
		);
		return $notes && isset( $notes[0]->content ) ? trim( wp_strip_all_tags( (string) $notes[0]->content ) ) : '';
	}

	private static function do_resync( array $plan ) {
		$order = self::order_access( $plan['id'], 'edit' );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		// La fecha de modificación al día y `save()`: WooCommerce dispara `woocommerce_update_order` y, con él, el webhook
		// `order.updated` de la plataforma; quien sincroniza por fecha también lo ve. Nada más del pedido cambia.
		$order->set_date_modified( time() );
		$order->save();
		$webhooks    = self::webhooks();
		$order_hooks = is_array( $webhooks ) ? array_values(
			array_filter(
				$webhooks['topics'],
				static function ( $topic ) {
					return 0 === strpos( $topic, 'order.' );
				}
			)
		) : array();
		$result      = array(
			'ok'     => true,
			'tool'   => Skydropx_Tool::TOOL,
			'action' => 'resync',
			'data'   => array(
				'order_id'      => $plan['id'],
				'date_modified' => $order->get_date_modified() ? $order->get_date_modified()->date( 'c' ) : '',
				'webhooks'      => $webhooks,
			),
			'notice' => 'Pedido guardado: WooCommerce encola el aviso a Skydropx (webhook order.updated) y lo entrega con su cola de tareas, normalmente en segundos o con la siguiente tarea programada. Si ya había una entrega pendiente de este pedido, no agenda otra: la pendiente llevará el pedido al día.',
		);
		if ( ! in_array( 'order.updated', $order_hooks, true ) ) {
			$result['warnings'] = array( 'No hay un webhook activo de Skydropx para order.updated: la plataforma verá el pedido en su próxima sincronización, no al momento. Vincular de nuevo la tienda (menú «Skydropx») vuelve a crearlo.' );
		}
		return $result;
	}

	/**
	 * Un pedido con el permiso sobre ese pedido que pide la REST de WooCommerce (`orders`): el de su tipo de contenido para
	 * ese objeto (`edit_shop_order`: un pedido ajeno exige `edit_others_shop_orders`; al leer, `read_private_shop_orders`)
	 * y el filtro `woocommerce_rest_check_permissions`, con el que los plugins de marketplace o de roles limitan a cada
	 * usuario a sus pedidos. Como la REST: un pedido que no existe es 404 y uno ajeno, 403.
	 *
	 * @param string $context `read` o `edit`.
	 * @return \WC_Order|\WP_Error
	 */
	private static function order_access( int $id, string $context ) {
		$order = self::order( $id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$allowed = function_exists( 'wc_rest_check_post_permissions' )
			? wc_rest_check_post_permissions( 'shop_order', $context, $id )
			: current_user_can( 'edit' === $context ? 'edit_shop_order' : 'read_private_shop_orders', $id );
		if ( ! $allowed ) {
			return new \WP_Error(
				'edit' === $context ? 'woocommerce_rest_cannot_edit' : 'woocommerce_rest_cannot_view',
				sprintf( 'Tu usuario no tiene permiso sobre el pedido %d: WooCommerce lo comprueba pedido a pedido, igual que en la herramienta orders.', $id ),
				array(
					'status' => 403,
					'denied' => true,
				)
			);
		}
		return $order;
	}

	/** Un pedido (no un reembolso), o el error. */
	private static function order( int $id ) {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;
		if ( ! $order || ! is_a( $order, 'WC_Order' ) || 'shop_order' !== $order->get_type() ) {
			return self::error( 'woocommerce_rest_shop_order_invalid_id', sprintf( 'No existe el pedido %d.', $id ), 404 );
		}
		return $order;
	}

	// =================================================================================================================
	// Utilidades.

	/** Un peso o una medida válidos (> 0, finitos, < `MAX_MEASURE`) como float, o null. */
	public static function measure( $value ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( 1 !== preg_match( '/^[0-9]+(\.[0-9]+)?$/', $value ) ) {
				return null;
			}
			$value = (float) $value;
		}
		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}
		$value = (float) $value;
		return is_finite( $value ) && $value > 0 && $value < self::MAX_MEASURE ? $value : null;
	}

	/** Un peso o una medida guardados como el plugin los lee (`(float)`): numérico y mayor que 0 (también «.5» o «1.»). */
	private static function stored_measure( $value ): bool {
		return is_numeric( $value ) && is_finite( (float) $value ) && (float) $value > 0;
	}

	private static function number( $value ): float {
		return round( (float) $value, 4 );
	}

	/** `wc-completed` → `completed`. */
	private static function unprefix_status( string $status ): string {
		return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
	}

	/** AAAA-MM-DD[THH:MM[:SS]] → fecha de la REST de WooCommerce (`AAAA-MM-DDTHH:MM:SS`), o ''. */
	private static function date( $value ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2})(?::(\d{2}))?)?$/', $value, $m ) ) {
			return '';
		}
		if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) || ( isset( $m[4] ) && ( (int) $m[4] > 23 || (int) $m[5] > 59 || ( isset( $m[6] ) && (int) $m[6] > 59 ) ) ) ) {
			return '';
		}
		return sprintf( '%s-%s-%sT%s:%s:%s', $m[1], $m[2], $m[3], isset( $m[4] ) ? $m[4] : '00', isset( $m[5] ) ? $m[5] : '00', isset( $m[6] ) ? $m[6] : '00' );
	}

	private static function valid_url( string $url ): bool {
		if ( '' === $url || self::length( $url ) > 500 || preg_match( '/[\s<>"]/', $url ) ) {
			return false;
		}
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		return in_array( $scheme, array( 'http', 'https' ), true ) && is_string( $host ) && '' !== $host && false !== filter_var( $url, FILTER_VALIDATE_URL );
	}

	private static function length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	private static function clean_text( string $text, int $max ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max, 'UTF-8' ) : substr( $text, 0, $max );
	}

	private static function is_list( array $value ): bool {
		return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	private static function error( string $code, string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
