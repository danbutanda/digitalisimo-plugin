<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * La herramienta `digitalisimo-woo/skydropx`: lo que el plugin de Skydropx deja a WordPress (productos con peso y
 * medidas para su cotización, la cotización de su método de envío, el rastreo de los pedidos en las metas que lee, su
 * enlace de la guía y el reenvío de un pedido a la plataforma), declarado y puro, como `Tools`.
 *
 * Sólo existe con el plugin de Skydropx cargado en el sitio (`active()`, sin tocar el disco: `Tools::tools()` lo
 * pregunta en cada petición con el MCP activo). Crear y pagar envíos en la plataforma Skydropx sigue fuera: se hace a
 * mano en su panel. Lo que el plugin guarda y fija la plataforma (claves de la API, URL de cotización, vinculación,
 * zona y método creados por ella) sólo se lee.
 *
 * La ejecución vive en `Skydropx` (sólo se carga al llamar a la herramienta).
 */
final class Skydropx_Tool {
	const TOOL = Tools::SKYDROPX;

	/** Acciones que sólo leen (en «Sólo lectura» también). */
	const READ_ACTIONS = array( 'status', 'audit_products', 'quote', 'shipments', 'get_shipment', 'label' );

	/** Acciones que escriben (sólo en «Lectura y escritura», con la bitácora). */
	const WRITE_ACTIONS = array( 'update_products', 'set_tracking', 'resync' );

	/** Tope de un lote de productos y de las líneas de una cotización. */
	const BATCH_MAX = 100;
	const CART_MAX  = 50;

	/** Unidades que WooCommerce sabe convertir (`wc_get_weight()`, `wc_get_dimension()`). */
	const WEIGHT_UNITS    = array( 'kg', 'g', 'lbs', 'oz' );
	const DIMENSION_UNITS = array( 'm', 'cm', 'mm', 'in', 'yd' );

	/** Comprobaciones de `audit_products`. */
	const CHECKS = array( 'weight', 'dimensions', 'shipping_class' );

	/** Filtro de rastreo de `shipments`. */
	const TRACKING_FILTER = array( 'any', 'with', 'without' );

	/** Lo que se espera del plugin (2.1.5), para decir qué falta si otra versión lo cambió. */
	const PLUGIN_CLASS    = 'Skydropx';
	const REPOSITORY      = 'Skydropx\\Includes\\Skydropx_Repository';
	const QUOTATION       = 'Skydropx\\Includes\\Skydropx_Quotation_Settings';
	const LABEL_SERVICE   = 'Skydropx\\Admin\\Skydropx_Order_Label_Service';
	const LABEL_UI        = 'Skydropx\\Admin\\Skydropx_Order_Label_UI';
	const SHIPPING_METHOD = 'Skydropx_Shipping_Method';

	/** Valores de 2.1.5 por si otra versión deja de declararlos. */
	const DEFAULT_METHOD_ID = 'skydropx';
	const DEFAULT_ZONE_NAME = 'Skydropx';
	const DEFAULT_HOST      = 'ecommerce.pro.skydropx.com';
	const DEFAULT_META      = array(
		'number'  => 'Tracking Number',
		'company' => 'Tracking Company',
		'url'     => 'Tracking URL',
	);

	/** Acción de `admin-post.php` del enlace de la guía (lo abre el navegador del administrador). */
	const LABEL_ACTION = Module::SKYDROPX_LABEL_ACTION;

	/** Vida del enlace firmado de la guía (después, una página con un botón: nada se ejecuta solo). */
	const LABEL_TTL = 3600;

	/** Meta oculta de la línea de envío: el número de rastreo que `set_tracking` registró a mano (el plugin la ignora). */
	const MANUAL_META = '_digitalisimo_manual_tracking';

	/** ¿Está cargado el plugin de Skydropx en este sitio? Sin autocarga ni disco. */
	public static function active(): bool {
		return defined( 'SKYDROPX_VERSION' ) || class_exists( self::PLUGIN_CLASS, false );
	}

	/** Todas las acciones, en orden. */
	public static function actions(): array {
		return array_merge( self::READ_ACTIONS, self::WRITE_ACTIONS );
	}

	public static function is_write( string $action ): bool {
		return in_array( $action, self::WRITE_ACTIONS, true );
	}

	/**
	 * Capacidades de una acción: hace falta una de cada grupo. Las de productos y pedidos son las mismas que piden
	 * `products` y `orders` para esa operación (lista, lote o cambio) más su mínimo; el estado y la cotización, una u
	 * otra de las dos herramientas.
	 *
	 * @return string[][]
	 */
	public static function capabilities( string $action ): array {
		switch ( $action ) {
			case 'status':
			case 'quote':
				return array( array( 'edit_shop_orders', 'edit_products' ) );
			case 'audit_products':
				return array( array( 'edit_products' ), array( Tools::capability( 'products', 'product', 'list' ) ) );
			case 'update_products':
				return array( array( 'edit_products' ), array( Tools::capability( 'products', 'product', 'batch' ) ) );
			case 'shipments':
			case 'get_shipment':
			case 'label':
				return array( array( 'edit_shop_orders' ), array( Tools::capability( 'orders', 'order', 'list' ) ) );
			case 'set_tracking':
			case 'resync':
				return array( array( 'edit_shop_orders' ), array( Tools::capability( 'orders', 'order', 'update' ) ) );
		}
		return array( array( 'do_not_allow' ) );
	}

	/** Mínimo para ver la herramienta (sin entrada): una de las dos. */
	public static function floor(): array {
		return array( 'edit_shop_orders', 'edit_products' );
	}

	/**
	 * Permiso (`permission_callback` y `run()`): con una entrada que nombra una acción, una capacidad de cada grupo de
	 * `capabilities()`; sin ella (lista de herramientas), el mínimo. Vive aquí, en la clase ligera, para que comprobarlo
	 * (cada `tools/list`) no cargue la ejecución. El permiso sobre cada pedido lo comprueba `Skydropx` al ejecutar.
	 *
	 * @return true|\WP_Error
	 */
	public static function permission( $input = null ) {
		$groups = array( self::floor() );
		if ( is_array( $input ) && isset( $input['action'] ) && is_string( $input['action'] ) && in_array( $input['action'], self::actions(), true ) ) {
			$groups = array_merge( $groups, self::capabilities( $input['action'] ) );
		}
		foreach ( $groups as $group ) {
			$ok = false;
			foreach ( $group as $cap ) {
				if ( current_user_can( $cap ) ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				return new \WP_Error(
					'digitalisimo_woo_mcp_forbidden',
					sprintf( 'Tu usuario no tiene permiso para esto en la tienda: WooCommerce exige la capacidad «%s».', implode( '» o «', $group ) ),
					array( 'status' => 403 )
				);
			}
		}
		return true;
	}

	/** Definición, con la misma forma que `Tools::definition()`. */
	public static function definition( string $mode = 'read' ): array {
		$tail = 'write' === $mode
			? ' Este sitio permite escribir («WooCommerce en el MCP»: lectura y escritura); toda escritura queda en la bitácora sin datos personales.'
			: ' Este sitio sólo permite leer («WooCommerce en el MCP»: sólo lectura): update_products, set_tracking y resync devuelven un error que explica dónde se cambia el modo.';
		return array(
			'label'         => 'Tienda: envíos con Skydropx',
			'description'   => self::DESCRIPTION . $tail,
			'category'      => Tools::CATEGORY,
			'input_schema'  => self::input_schema(),
			'output_schema' => array( 'type' => 'object' ),
			'meta'          => array(
				// Cambia valores guardados (medidas, rastreo, fecha del pedido): como las demás, no es de sólo lectura.
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
				'show_in_rest' => false,
				'mcp'          => array(
					'public' => false,
					'type'   => 'tool',
				),
			),
		);
	}

	const DESCRIPTION = 'Envíos con Skydropx en la tienda WooCommerce de este sitio: lo que le toca a WordPress. Crear, pagar o cancelar envíos en la plataforma Skydropx se hace a mano en su panel, no aquí. Acciones de lectura: status (vinculación con Skydropx sin claves, cotización activa, zona y método de Skydropx, unidades de la tienda, dirección de origen completa o no, webhooks y versión del plugin); audit_products (productos y variaciones publicados a los que la cotización les manda peso o medidas vacíos o en cero, y sin clase de envío si se pide en checks; paginado con page y per_page y con totales; con más de 5 000, sigue con after_id); quote (cotización de prueba con el método de Skydropx del propio plugin: address {postcode obligatorio, country, state, city, address_1} y cart [{product_id, variation_id, quantity}]; respeta la caché de 2 minutos del plugin salvo con cache:false); shipments (pedidos con su rastreo: statuses, after y before AAAA-MM-DD y tracking_filter any|with|without dentro de la página; paginado); get_shipment (un pedido con order_id: rastreo, si tiene guía de Skydropx —label true—, si el rastreo se registró a mano —label "manual", sin PDF— y destino); label (order_id: un enlace de 1 hora para descargar la guía de Skydropx en PDF desde el navegador del administrador con su sesión; nunca el PDF ni la firma). Acciones que escriben: update_products (items [{product_id, variation_id, weight, length, width, height, shipping_class}] hasta 100, con weight_unit y dimension_unit si los valores no van en las unidades de la tienda; se convierten y se guardan con la API REST de WooCommerce, como products, para que Skydropx los reciba); set_tracking (order_id y tracking {number, company, url} de un envío hecho a mano, en las metas de la línea de envío que lee el plugin; opcionales shipping_line_id, order_status y customer_note, que avisa al cliente por correo; un pedido sin línea de envío falla salvo create_shipping_line:true; la caja del plugin mostrará «Guía generada» aunque no haya PDF de Skydropx); resync (order_id: guarda el pedido sin cambiar nada más para que WooCommerce avise a Skydropx por su webhook). Las claves, la URL de cotización y la vinculación del plugin nunca salen ni se cambian.';

	/** Esquema de entrada cerrado. */
	public static function input_schema(): array {
		$number  = array(
			'type'             => 'number',
			'exclusiveMinimum' => 0,
		);
		$item    = array(
			'type'                 => 'object',
			'properties'           => array(
				'product_id'     => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'ID del producto (o del padre de la variación).' ),
				'variation_id'   => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'ID de la variación, si es una.' ),
				'weight'         => $number + array( 'description' => 'Peso, mayor que 0.' ),
				'length'         => $number + array( 'description' => 'Largo, mayor que 0.' ),
				'width'          => $number + array( 'description' => 'Ancho, mayor que 0.' ),
				'height'         => $number + array( 'description' => 'Alto, mayor que 0.' ),
				'shipping_class' => array( 'type' => 'string', 'pattern' => '^[a-z0-9_-]{0,200}$', 'description' => 'Slug de una clase de envío existente (si coincide con un nombre y no con un slug, se usa su slug y se avisa). Vacío: en un producto, sin clase; en una variación, la misma del padre.' ),
			),
			'required'             => array( 'product_id' ),
			'additionalProperties' => false,
		);
		$line    = array(
			'type'                 => 'object',
			'properties'           => array(
				'product_id'   => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'ID del producto.' ),
				'variation_id' => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'ID de la variación (obligatorio en un producto variable).' ),
				'quantity'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 1, 'description' => 'Cantidad.' ),
			),
			'required'             => array( 'product_id' ),
			'additionalProperties' => false,
		);
		$text    = static function ( int $max, string $help ): array {
			return array( 'type' => 'string', 'maxLength' => $max, 'description' => $help );
		};
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'action'           => array(
					'type'        => 'string',
					'enum'        => self::actions(),
					'description' => 'Qué hacer: status, audit_products, quote, shipments, get_shipment y label leen; update_products, set_tracking y resync escriben.',
				),
				'order_id'         => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'ID del pedido (get_shipment, label, set_tracking y resync).' ),
				'items'            => array(
					'type'        => 'array',
					'items'       => $item,
					'minItems'    => 1,
					'maxItems'    => self::BATCH_MAX,
					'description' => 'update_products: productos o variaciones a corregir (100 como mucho), cada uno con al menos un campo.',
				),
				'weight_unit'      => array( 'type' => 'string', 'enum' => self::WEIGHT_UNITS, 'description' => 'update_products: unidad del peso enviado (por defecto, la de la tienda).' ),
				'dimension_unit'   => array( 'type' => 'string', 'enum' => self::DIMENSION_UNITS, 'description' => 'update_products: unidad de las medidas enviadas (por defecto, la de la tienda).' ),
				'checks'           => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string', 'enum' => self::CHECKS ),
					'maxItems'    => 3,
					'description' => 'audit_products: qué revisar (por defecto weight y dimensions; shipping_class sólo si se pide: Skydropx no la usa para cotizar).',
				),
				'after_id'         => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0, 'description' => 'audit_products: revisar a partir de este ID (next_after_id de la respuesta anterior, cuando la tienda tiene más de 5 000 productos y variaciones).' ),
				'address'          => array(
					'type'                 => 'object',
					'properties'           => array(
						'postcode'  => $text( 20, 'Código postal de destino (obligatorio).' ),
						'country'   => array( 'type' => 'string', 'pattern' => '^[A-Za-z]{2}$', 'description' => 'País (ISO de dos letras; por defecto el de la tienda).' ),
						'state'     => $text( 50, 'Estado (código de WooCommerce, p. ej. DF o JAL).' ),
						'city'      => $text( 100, 'Ciudad.' ),
						'address_1' => $text( 200, 'Calle y número.' ),
						'address_2' => $text( 200, 'Colonia o referencia.' ),
					),
					'required'             => array( 'postcode' ),
					'additionalProperties' => false,
					'description'          => 'quote: dirección de destino.',
				),
				'cart'             => array(
					'type'        => 'array',
					'items'       => $line,
					'minItems'    => 1,
					'maxItems'    => self::CART_MAX,
					'description' => 'quote: productos que se cotizan (50 líneas como mucho).',
				),
				'cache'            => array( 'type' => 'boolean', 'default' => true, 'description' => 'quote: false salta la caché de 2 minutos del plugin y vuelve a cotizar.' ),
				'statuses'         => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string', 'pattern' => '^[a-z0-9_-]{1,40}$' ),
					'maxItems'    => 20,
					'description' => 'shipments: estados de pedido (processing, completed, on-hold…); por defecto todos.',
				),
				'after'            => array( 'type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}(T\\d{2}:\\d{2}(:\\d{2})?)?$', 'description' => 'shipments: pedidos creados desde esta fecha (AAAA-MM-DD).' ),
				'before'           => array( 'type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}(T\\d{2}:\\d{2}(:\\d{2})?)?$', 'description' => 'shipments: pedidos creados antes de esta fecha (AAAA-MM-DD).' ),
				'tracking'         => array(
					'type'                 => 'object',
					'properties'           => array(
						'number'  => $text( 100, 'Número de guía o de rastreo.' ),
						'company' => $text( 100, 'Paquetería.' ),
						'url'     => $text( 500, 'URL de rastreo (http o https).' ),
					),
					'additionalProperties' => false,
					'description'          => 'set_tracking: rastreo del envío; lo que no se manda se conserva. El plugin muestra la guía cuando hay número, paquetería y URL.',
				),
				'tracking_filter'  => array( 'type' => 'string', 'enum' => self::TRACKING_FILTER, 'default' => 'any', 'description' => 'shipments: any, with (con rastreo) o without (sin rastreo), dentro de la página pedida.' ),
				'shipping_line_id' => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'set_tracking: línea de envío del pedido (por defecto la que ya tiene rastreo o la primera).' ),
				'create_shipping_line' => array( 'type' => 'boolean', 'default' => false, 'description' => 'set_tracking: sólo si el pedido no tiene línea de envío y el usuario lo pide: true añade una sin costo («Envío»), que el cliente verá en su pedido y en sus correos. Sin ella, set_tracking falla en un pedido sin línea de envío.' ),
				'order_status'     => array( 'type' => 'string', 'pattern' => '^[a-z0-9_-]{1,40}$', 'description' => 'set_tracking: estado nuevo del pedido (p. ej. completed), opcional.' ),
				'customer_note'    => $text( 1000, 'set_tracking: nota para el cliente (WooCommerce se la manda por correo), opcional.' ),
				'page'             => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1, 'description' => 'Página de audit_products o shipments.' ),
				'per_page'         => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => Tools::PER_PAGE_MAX, 'default' => Tools::PER_PAGE_DEFAULT, 'description' => 'Elementos por página (100 como mucho).' ),
			),
			'required'             => array( 'action' ),
			'additionalProperties' => false,
		);
	}

	/** Parámetros que admite cada acción (además de `action`). */
	const PARAMS = array(
		'status'          => array(),
		'audit_products'  => array( 'checks', 'after_id', 'page', 'per_page' ),
		'quote'           => array( 'address', 'cart', 'cache' ),
		'shipments'       => array( 'statuses', 'after', 'before', 'tracking_filter', 'page', 'per_page' ),
		'get_shipment'    => array( 'order_id' ),
		'label'           => array( 'order_id' ),
		'update_products' => array( 'items', 'weight_unit', 'dimension_unit' ),
		'set_tracking'    => array( 'order_id', 'tracking', 'shipping_line_id', 'create_shipping_line', 'order_status', 'customer_note' ),
		'resync'          => array( 'order_id' ),
	);
}
