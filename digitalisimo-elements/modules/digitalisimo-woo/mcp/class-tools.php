<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Herramientas de WooCommerce del servidor MCP de DIGITALÍSIMO Elements: la tabla cerrada y sus definiciones.
 *
 * Todo es declarativo y puro (sin WordPress), para que las pruebas lo lean tal cual:
 * - `TOOLS`: las siete herramientas (`digitalisimo-woo/<recurso>`), cada una con su `action` (enum) y sus recursos.
 * - `RESOURCES`: recurso → ruta de la API REST v3 de WooCommerce (`/wc/v3/…`), con los marcadores que el código rellena
 *   con IDs validados (`{product_id}`), el ID del elemento (`item`: parámetro de WooCommerce y clave de la entrada), las
 *   acciones que admite, si tiene papelera y de dónde sale su capacidad. Ni la ruta ni el método vienen nunca del
 *   cliente: `Router::resolve()` los arma sólo desde aquí.
 * - `definition()`: etiqueta, descripción para el modelo, esquema de entrada cerrado (`additionalProperties: false`,
 *   nunca la clave `post_id`) y anotaciones.
 * - `capability()`: la capacidad que la REST de WooCommerce exige para ese recurso y esa acción (las de
 *   `wc_rest_check_*_permissions()` con los valores por defecto de WooCommerce) más un mínimo por herramienta que deja
 *   fuera a un editor o un suscriptor. WooCommerce la vuelve a comprobar, objeto a objeto, al ejecutar.
 */
final class Tools {
	const PREFIX   = 'digitalisimo-woo/';
	const CATEGORY = 'digitalisimo-woo';
	const REST     = '/wc/v3/';

	const PER_PAGE_DEFAULT = 10;
	const PER_PAGE_MAX     = 100;
	const BATCH_MAX        = 100;
	const FIELDS_MAX       = 50;

	/** IDs de texto (grupo y opción de ajustes, método de envío, clase de impuesto, pasarela). */
	const SLUG_PATTERN = '/^[A-Za-z0-9_-]{1,100}$/';

	/** Acciones de lectura de las herramientas con recursos (los informes son todos de lectura). */
	const READ_ACTIONS = array( 'list', 'get' );

	/**
	 * Herramientas: recursos (el primero es el predeterminado), acciones del enum, IDs que aceptan y mínimo para usarla.
	 */
	const TOOLS = array(
		'products'       => array(
			'resources' => array( 'product', 'variation' ),
			'actions'   => array( 'list', 'get', 'create', 'update', 'delete', 'batch' ),
			'ids'       => array( 'product_id', 'variation_id' ),
			'floor'     => 'edit_products',
		),
		'catalog'        => array(
			'resources' => array( 'category', 'tag', 'brand', 'attribute', 'attribute_term', 'shipping_class', 'review' ),
			'actions'   => array( 'list', 'get', 'create', 'update', 'delete' ),
			'ids'       => array( 'term_id', 'attribute_id', 'review_id' ),
			'floor'     => 'edit_products',
		),
		'orders'         => array(
			'resources' => array( 'order', 'note', 'refund' ),
			'actions'   => array( 'list', 'get', 'create', 'update', 'delete' ),
			'ids'       => array( 'order_id', 'note_id', 'refund_id' ),
			'floor'     => 'edit_shop_orders',
		),
		'customers'      => array(
			'resources' => array( 'customer', 'download' ),
			'actions'   => array( 'list', 'get', 'create', 'update', 'delete' ),
			'ids'       => array( 'customer_id' ),
			'floor'     => 'list_users',
		),
		'coupons'        => array(
			'resources' => array( 'coupon' ),
			'actions'   => array( 'list', 'get', 'create', 'update', 'delete', 'batch' ),
			'ids'       => array( 'coupon_id' ),
			'floor'     => 'edit_shop_coupons',
		),
		'store-settings' => array(
			'resources' => array( 'setting_group', 'setting', 'shipping_zone', 'shipping_zone_location', 'shipping_zone_method', 'shipping_method', 'tax_class', 'tax_rate', 'payment_gateway' ),
			'actions'   => array( 'list', 'get', 'create', 'update', 'delete', 'batch' ),
			'ids'       => array( 'group_id', 'setting_id', 'zone_id', 'instance_id', 'method_id', 'tax_class', 'rate_id', 'gateway_id' ),
			'floor'     => 'manage_woocommerce',
		),
		'reports'        => array(
			'resources' => array(),
			'actions'   => array( 'list', 'sales', 'top_sellers', 'orders_totals', 'products_totals', 'customers_totals', 'coupons_totals', 'reviews_totals', 'log' ),
			'ids'       => array(),
			'floor'     => 'view_woocommerce_reports',
		),
	);

	/** IDs enteros de la entrada y su mínimo (la zona 0 es «resto del mundo»). */
	const INT_IDS = array(
		'product_id'   => 1,
		'variation_id' => 1,
		'term_id'      => 1,
		'attribute_id' => 1,
		'review_id'    => 1,
		'order_id'     => 1,
		'note_id'      => 1,
		'refund_id'    => 1,
		'customer_id'  => 1,
		'coupon_id'    => 1,
		'zone_id'      => 0,
		'instance_id'  => 1,
		'rate_id'      => 1,
	);

	/** IDs de texto de la entrada (`SLUG_PATTERN`). */
	const SLUG_IDS = array( 'group_id', 'setting_id', 'method_id', 'tax_class', 'gateway_id' );

	/**
	 * Recursos. `path`: ruta bajo `/wc/v3/` con marcadores `{parámetro de WooCommerce}`; `parents`: marcador → clave de
	 * la entrada; `item`: [parámetro de WooCommerce, clave de la entrada] del elemento, o nada; `collection`: acciones
	 * que van a la ruta de la colección aunque cambien algo (las ubicaciones de una zona se reemplazan enteras);
	 * `paged`: WooCommerce 11.2.1 pagina su lista (`page`/`per_page`; en los demás devuelve todo y aquí no se pagina);
	 * `trash`: WooCommerce manda a la papelera sin `force`; `caps`: origen de la capacidad; `secrets`: los ajustes que
	 * devuelve llevan campos que se enmascaran (`map`: mapa `settings`; `option`: opciones de un grupo).
	 */
	const RESOURCES = array(
		'product'                => array( 'tool' => 'products', 'path' => 'products', 'parents' => array(), 'item' => array( 'id', 'product_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete', 'batch' ), 'paged' => true, 'trash' => true, 'caps' => 'post:product' ),
		'variation'              => array( 'tool' => 'products', 'path' => 'products/{product_id}/variations', 'parents' => array( 'product_id' => 'product_id' ), 'item' => array( 'id', 'variation_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete', 'batch' ), 'paged' => true, 'trash' => false, 'caps' => 'post:product' ),
		'category'               => array( 'tool' => 'catalog', 'path' => 'products/categories', 'parents' => array(), 'item' => array( 'id', 'term_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'paged' => true, 'trash' => false, 'caps' => 'term:product_cat' ),
		'tag'                    => array( 'tool' => 'catalog', 'path' => 'products/tags', 'parents' => array(), 'item' => array( 'id', 'term_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'paged' => true, 'trash' => false, 'caps' => 'term:product_tag' ),
		'brand'                  => array( 'tool' => 'catalog', 'path' => 'products/brands', 'parents' => array(), 'item' => array( 'id', 'term_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'paged' => true, 'trash' => false, 'caps' => 'term:product_brand' ),
		'attribute'              => array( 'tool' => 'catalog', 'path' => 'products/attributes', 'parents' => array(), 'item' => array( 'id', 'attribute_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'trash' => false, 'caps' => 'attributes' ),
		'attribute_term'         => array( 'tool' => 'catalog', 'path' => 'products/attributes/{attribute_id}/terms', 'parents' => array( 'attribute_id' => 'attribute_id' ), 'item' => array( 'id', 'term_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'paged' => true, 'trash' => false, 'caps' => 'term:pa' ),
		'shipping_class'         => array( 'tool' => 'catalog', 'path' => 'products/shipping_classes', 'parents' => array(), 'item' => array( 'id', 'term_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'paged' => true, 'trash' => false, 'caps' => 'term:product_shipping_class' ),
		'review'                 => array( 'tool' => 'catalog', 'path' => 'products/reviews', 'parents' => array(), 'item' => array( 'id', 'review_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'paged' => true, 'trash' => true, 'caps' => 'review' ),
		'order'                  => array( 'tool' => 'orders', 'path' => 'orders', 'parents' => array(), 'item' => array( 'id', 'order_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'paged' => true, 'trash' => true, 'caps' => 'post:shop_order' ),
		'note'                   => array( 'tool' => 'orders', 'path' => 'orders/{order_id}/notes', 'parents' => array( 'order_id' => 'order_id' ), 'item' => array( 'id', 'note_id' ), 'actions' => array( 'list', 'get', 'create', 'delete' ), 'trash' => false, 'caps' => 'post:shop_order' ),
		'refund'                 => array( 'tool' => 'orders', 'path' => 'orders/{order_id}/refunds', 'parents' => array( 'order_id' => 'order_id' ), 'item' => array( 'id', 'refund_id' ), 'actions' => array( 'list', 'get', 'create', 'delete' ), 'paged' => true, 'trash' => false, 'caps' => 'post:shop_order' ),
		'customer'               => array( 'tool' => 'customers', 'path' => 'customers', 'parents' => array(), 'item' => array( 'id', 'customer_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'paged' => true, 'trash' => false, 'caps' => 'user' ),
		'download'               => array( 'tool' => 'customers', 'path' => 'customers/{customer_id}/downloads', 'parents' => array( 'customer_id' => 'customer_id' ), 'item' => array(), 'actions' => array( 'list' ), 'trash' => false, 'caps' => 'user' ),
		'coupon'                 => array( 'tool' => 'coupons', 'path' => 'coupons', 'parents' => array(), 'item' => array( 'id', 'coupon_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete', 'batch' ), 'paged' => true, 'trash' => true, 'caps' => 'post:shop_coupon' ),
		'setting_group'          => array( 'tool' => 'store-settings', 'path' => 'settings', 'parents' => array(), 'item' => array(), 'actions' => array( 'list' ), 'trash' => false, 'caps' => 'manager' ),
		'setting'                => array( 'tool' => 'store-settings', 'path' => 'settings/{group_id}', 'parents' => array( 'group_id' => 'group_id' ), 'item' => array( 'id', 'setting_id' ), 'actions' => array( 'list', 'get', 'update', 'batch' ), 'trash' => false, 'caps' => 'manager', 'secrets' => 'option' ),
		'shipping_zone'          => array( 'tool' => 'store-settings', 'path' => 'shipping/zones', 'parents' => array(), 'item' => array( 'id', 'zone_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'trash' => false, 'caps' => 'manager' ),
		'shipping_zone_location' => array( 'tool' => 'store-settings', 'path' => 'shipping/zones/{id}/locations', 'parents' => array( 'id' => 'zone_id' ), 'item' => array(), 'actions' => array( 'list', 'update' ), 'collection' => array( 'update' ), 'trash' => false, 'caps' => 'manager' ),
		'shipping_zone_method'   => array( 'tool' => 'store-settings', 'path' => 'shipping/zones/{zone_id}/methods', 'parents' => array( 'zone_id' => 'zone_id' ), 'item' => array( 'instance_id', 'instance_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete' ), 'trash' => false, 'caps' => 'manager', 'secrets' => 'map' ),
		'shipping_method'        => array( 'tool' => 'store-settings', 'path' => 'shipping_methods', 'parents' => array(), 'item' => array( 'id', 'method_id' ), 'actions' => array( 'list', 'get' ), 'trash' => false, 'caps' => 'manager' ),
		'tax_class'              => array( 'tool' => 'store-settings', 'path' => 'taxes/classes', 'parents' => array(), 'item' => array( 'slug', 'tax_class' ), 'actions' => array( 'list', 'create', 'delete' ), 'trash' => false, 'caps' => 'manager' ),
		'tax_rate'               => array( 'tool' => 'store-settings', 'path' => 'taxes', 'parents' => array(), 'item' => array( 'id', 'rate_id' ), 'actions' => array( 'list', 'get', 'create', 'update', 'delete', 'batch' ), 'paged' => true, 'trash' => false, 'caps' => 'manager' ),
		'payment_gateway'        => array( 'tool' => 'store-settings', 'path' => 'payment_gateways', 'parents' => array(), 'item' => array( 'id', 'gateway_id' ), 'actions' => array( 'list', 'get', 'update' ), 'trash' => false, 'caps' => 'manager', 'secrets' => 'map' ),
	);

	/** Informes de sólo lectura: acción → ruta bajo `/wc/v3/` (`log` es la bitácora propia, sin REST). */
	const REPORTS = array(
		'list'             => 'reports',
		'sales'            => 'reports/sales',
		'top_sellers'      => 'reports/top_sellers',
		'orders_totals'    => 'reports/orders/totals',
		'products_totals'  => 'reports/products/totals',
		'customers_totals' => 'reports/customers/totals',
		'coupons_totals'   => 'reports/coupons/totals',
		'reviews_totals'   => 'reports/reviews/totals',
		'log'              => '',
	);

	/** Etiquetas (título de la herramienta en el cliente). */
	const LABELS = array(
		'products'       => 'Tienda: productos y variaciones',
		'catalog'        => 'Tienda: catálogo (categorías, etiquetas, marcas, atributos, clases de envío y reseñas)',
		'orders'         => 'Tienda: pedidos, notas y reembolsos',
		'customers'      => 'Tienda: clientes y sus descargas',
		'coupons'        => 'Tienda: cupones',
		'store-settings' => 'Tienda: ajustes, envíos, impuestos y pasarelas de pago',
		'reports'        => 'Tienda: informes y bitácora (sólo lectura)',
	);

	/** Qué hace cada herramienta, para el modelo. */
	const DESCRIPTIONS = array(
		'products'       => 'Administra los productos de la tienda WooCommerce de este sitio y sus variaciones, a través de la API REST v3 de WooCommerce (WooCommerce valida cada campo y comprueba los permisos del usuario). resource: "product" (por defecto) o "variation" (siempre con product_id). Acciones: list (filtros de WooCommerce en query: search, sku, status, type, category, tag, stock_status, on_sale, orderby, order…; paginada con page y per_page ≤ 100), get, create, update, delete y batch. data lleva los campos de WooCommerce: name, type (simple, variable, grouped, external), status, regular_price, sale_price, sku, manage_stock, stock_quantity, stock_status, categories [{"id":…}], tags, images, attributes [{"id" o "name", "options", "variation": true}] y, en una variación, attributes [{"id" o "name", "option"}], regular_price, stock_quantity… delete manda un producto a la papelera; force:true lo borra para siempre (las variaciones no tienen papelera y lo exigen). batch acepta data {"create":[…],"update":[…],"delete":[ids]} con 100 elementos como mucho; WooCommerce borra en lote sin papelera, así que un lote con delete exige force:true. fields recorta la respuesta a esos campos.',
		'catalog'        => 'Administra el catálogo de la tienda WooCommerce de este sitio. resource: "category", "tag", "brand" o "shipping_class" (con term_id), "attribute" (con attribute_id), "attribute_term" (términos de un atributo: attribute_id y term_id) o "review" (reseñas de productos, con review_id). Acciones: list (paginada salvo los atributos, que salen todos; filtros en query, p. ej. search, parent, hide_empty, product o status), get, create, update y delete. data lleva los campos de WooCommerce (name, slug, parent, description, image… y, en una reseña, product_id, review, reviewer, reviewer_email, rating, status). Categorías, etiquetas, marcas, atributos, términos y clases de envío no tienen papelera: borrarlos exige force:true. Una reseña va a la papelera salvo con force:true.',
		'orders'         => 'Administra los pedidos de la tienda WooCommerce de este sitio. resource: "order" (por defecto, con order_id), "note" (notas del pedido: order_id y note_id) o "refund" (reembolsos: order_id y refund_id). Acciones de los pedidos: list (filtros en query: status, customer, product, after, before, search…), get, create (data: status, customer_id, billing, shipping, line_items [{"product_id","variation_id","quantity"}], shipping_lines, set_paid…), update (p. ej. {"status":"completed"}) y delete (a la papelera; force:true lo borra para siempre). Notas: list (todas, sin paginar), get, create (data {"note":"…","customer_note":false}) y delete (sin papelera: force:true). Reembolsos: list, get, create (data: amount, reason, line_items) y delete (sin papelera: force:true). Un reembolso sólo registra el importe y no mueve dinero: api_refund:true (parámetro de primer nivel) pide además a la pasarela de pago que devuelva el dinero, y sólo debe usarse si el usuario lo pide expresamente.',
		'customers'      => 'Administra los clientes de la tienda WooCommerce de este sitio. resource: "customer" (por defecto, con customer_id) o "download" (descargas de un cliente: sólo list, con customer_id). Acciones: list (filtros en query: search, email, role, orderby…), get, create (data: email, first_name, last_name, username, password, billing, shipping), update y delete (los usuarios no tienen papelera: exige force:true; reassign pasa sus contenidos a otro usuario). Las respuestas llevan datos personales del cliente: úsalos sólo para la tarea pedida.',
		'coupons'        => 'Administra los cupones de la tienda WooCommerce de este sitio (coupon_id). Acciones: list (filtros en query: search, code…), get, create (data: code, discount_type percent, fixed_cart o fixed_product, amount, individual_use, product_ids, usage_limit, date_expires, free_shipping…), update, delete (a la papelera; force:true lo borra para siempre) y batch (data {"create","update","delete"}, 100 elementos como mucho; WooCommerce borra en lote sin papelera, así que un lote con delete exige force:true).',
		'store-settings' => 'Ajustes de la tienda WooCommerce de este sitio. resource: "setting_group" (list: grupos de ajustes), "setting" (opciones de un grupo, group_id: list, get y update con setting_id y data {"value":…}; batch con data {"update":[{"id","value"}]}), "shipping_zone" (zone_id: list, get, create, update, delete con force:true), "shipping_zone_location" (ubicaciones de una zona: list y update con data {"locations":[{"code":"MX","type":"country"}]}, que las reemplaza todas), "shipping_zone_method" (métodos de una zona: zone_id e instance_id; create con data {"method_id":"flat_rate"}; update con data {"enabled", "settings":{…}}; delete con force:true), "shipping_method" (métodos de envío disponibles: list y get con method_id), "tax_class" (clases de impuesto: list, create, delete con tax_class y force:true), "tax_rate" (tasas de impuesto, rate_id: list, get, create, update, delete con force:true, batch) y "payment_gateway" (pasarelas de pago, gateway_id: list, get y update con data {"enabled", "title", "description", "settings":{…}}). Estas listas devuelven todo en una sola respuesta, salvo las tasas de impuesto (paginadas). Las claves y contraseñas guardadas nunca se devuelven: salen enmascaradas ("masked": true). Se pueden escribir enviando el valor nuevo; un valor vacío o la máscara conservan el guardado.',
		'reports'        => 'Informes de la tienda WooCommerce de este sitio, sólo lectura. Acciones: list (informes disponibles), sales (ventas; query: period week, month, last_month o year, o date_min y date_max AAAA-MM-DD), top_sellers (más vendidos, mismos filtros), orders_totals, products_totals, customers_totals, coupons_totals y reviews_totals (totales por estado o tipo) y log (bitácora de las escrituras hechas con estas herramientas en este sitio: fecha, usuario, herramienta, acción, recurso, ID y resultado, sin datos personales ni valores; paginada con page y per_page; exige administrar WooCommerce).',
	);

	// -----------------------------------------------------------------------------------------------------------------

	/** ID de la ability de una herramienta (`digitalisimo-woo/products`). */
	public static function id( string $tool ): string {
		return self::PREFIX . $tool;
	}

	/** Herramienta de un ID de ability, o '' si no es una de éstas. */
	public static function tool_of( string $id ): string {
		if ( 0 !== strpos( $id, self::PREFIX ) ) {
			return '';
		}
		$tool = substr( $id, strlen( self::PREFIX ) );
		return isset( self::TOOLS[ $tool ] ) ? $tool : '';
	}

	/** Las siete herramientas, en orden. */
	public static function tools(): array {
		return array_keys( self::TOOLS );
	}

	/** Recursos de una herramienta. */
	public static function resources( string $tool ): array {
		return isset( self::TOOLS[ $tool ] ) ? self::TOOLS[ $tool ]['resources'] : array();
	}

	/** ¿La acción escribe? Todo lo que no es `list` o `get` (los informes, incluida `log`, sólo leen). */
	public static function is_write( string $tool, string $action ): bool {
		return 'reports' !== $tool && ! in_array( $action, self::READ_ACTIONS, true );
	}

	/** Herramientas que sólo leen (anotación `readonly`). */
	public static function is_readonly_tool( string $tool ): bool {
		return 'reports' === $tool;
	}

	/**
	 * Definición de una herramienta: `label`, `description`, `category`, `input_schema`, `output_schema` y `meta`
	 * (`annotations`, `show_in_rest` y `mcp`). `$mode` (`read` o `write`) sólo cambia la última frase de la descripción.
	 */
	public static function definition( string $tool, string $mode = 'read' ): array {
		$readonly = self::is_readonly_tool( $tool );
		$tail     = $readonly ? '' : ( 'write' === $mode
			? ' Este sitio permite escribir («WooCommerce en el MCP»: lectura y escritura); toda escritura queda en la bitácora sin datos personales.'
			: ' Este sitio sólo permite leer («WooCommerce en el MCP»: sólo lectura): create, update, delete y batch devuelven un error que explica dónde se cambia el modo.' );
		return array(
			'label'         => self::LABELS[ $tool ],
			'description'   => self::DESCRIPTIONS[ $tool ] . $tail,
			'category'      => self::CATEGORY,
			'input_schema'  => self::input_schema( $tool ),
			'output_schema' => array( 'type' => 'object' ),
			'meta'          => array(
				// Una herramienta con delete (o con reembolsos) es destructiva; create y update no lo son por sí mismas,
				// pero las anotaciones son por herramienta: la única que sólo lee son los informes.
				'annotations'  => array(
					'readonly'    => $readonly,
					'destructive' => ! $readonly,
					'idempotent'  => $readonly,
				),
				// Sólo en el servidor MCP de DIGITALÍSIMO Elements: ni en la REST de abilities ni en el servidor genérico.
				'show_in_rest' => false,
				'mcp'          => array(
					'public' => false,
					'type'   => 'tool',
				),
			),
		);
	}

	/** Esquema de entrada cerrado de una herramienta. */
	public static function input_schema( string $tool ): array {
		$config     = self::TOOLS[ $tool ];
		$properties = array(
			'action' => array(
				'type'        => 'string',
				'enum'        => $config['actions'],
				'description' => 'reports' === $tool ? 'Informe que se pide.' : 'Qué hacer: list y get leen; create, update, delete y batch escriben.',
			),
		);
		if ( $config['resources'] && count( $config['resources'] ) > 1 ) {
			$properties['resource'] = array(
				'type'        => 'string',
				'enum'        => $config['resources'],
				'default'     => $config['resources'][0],
				'description' => 'Sobre qué recurso actúa (por defecto «' . $config['resources'][0] . '»).',
			);
		}
		foreach ( $config['ids'] as $key ) {
			$properties[ $key ] = isset( self::INT_IDS[ $key ] )
				? array( 'type' => 'integer', 'minimum' => self::INT_IDS[ $key ], 'description' => self::id_help( $key ) )
				: array( 'type' => 'string', 'pattern' => '^[A-Za-z0-9_-]{1,100}$', 'description' => self::id_help( $key ) );
		}
		if ( 'reports' !== $tool ) {
			$properties['data'] = array(
				'type'        => 'object',
				'description' => 'Cuerpo de create, update o batch con los campos de la API REST v3 de WooCommerce. Los IDs de la ruta van en sus parámetros propios, nunca aquí.',
			);
		}
		$properties['query']    = array(
			'type'        => 'object',
			'description' => 'Filtros de WooCommerce para list y get (texto, números, booleanos o listas de ellos).',
		);
		$properties['page']     = array(
			'type'        => 'integer',
			'minimum'     => 1,
			'default'     => 1,
			'description' => 'Página de una lista paginada (productos, variaciones, categorías, etiquetas, marcas, términos de atributo, clases de envío, reseñas, pedidos, reembolsos, clientes, cupones y tasas de impuesto). Las demás listas devuelven todo en una sola respuesta.',
		);
		$properties['per_page'] = array(
			'type'        => 'integer',
			'minimum'     => 1,
			'maximum'     => self::PER_PAGE_MAX,
			'default'     => self::PER_PAGE_DEFAULT,
			'description' => 'Elementos por página de una lista paginada (100 como mucho).',
		);
		$properties['fields']   = array(
			'type'        => 'array',
			'items'       => array(
				'type'    => 'string',
				'pattern' => '^[a-z0-9_]{1,64}$',
			),
			'maxItems'    => self::FIELDS_MAX,
			'description' => 'Campos de primer nivel que se devuelven (siempre con id), para respuestas cortas.',
		);
		if ( 'reports' !== $tool ) {
			$properties['force'] = array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => 'Sólo con delete (o un batch con delete): true borra para siempre, sin papelera. Úsalo sólo si el usuario lo pide.',
			);
		}
		if ( 'orders' === $tool ) {
			$properties['api_refund'] = array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => 'Sólo al crear un reembolso: true pide a la pasarela de pago que devuelva el dinero. Por defecto false: se registra el reembolso sin mover dinero.',
			);
		}
		if ( 'customers' === $tool ) {
			$properties['reassign'] = array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => 'Sólo al borrar un cliente: usuario que recibe sus contenidos.',
			);
		}
		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array( 'action' ),
			'additionalProperties' => false,
		);
	}

	/** Ayuda de un ID de la entrada. */
	private static function id_help( string $key ): string {
		$help = array(
			'product_id'   => 'ID del producto (también el producto padre de una variación).',
			'variation_id' => 'ID de la variación (resource "variation").',
			'term_id'      => 'ID de la categoría, etiqueta, marca, clase de envío o término de atributo.',
			'attribute_id' => 'ID del atributo global (resource "attribute" o "attribute_term").',
			'review_id'    => 'ID de la reseña.',
			'order_id'     => 'ID del pedido (también el de sus notas y reembolsos).',
			'note_id'      => 'ID de la nota del pedido.',
			'refund_id'    => 'ID del reembolso.',
			'customer_id'  => 'ID del cliente (usuario).',
			'coupon_id'    => 'ID del cupón.',
			'group_id'     => 'Grupo de ajustes (general, products, tax, shipping, checkout, account, email, advanced…).',
			'setting_id'   => 'Opción dentro del grupo (p. ej. woocommerce_currency).',
			'zone_id'      => 'ID de la zona de envío (0 es «resto del mundo»).',
			'instance_id'  => 'ID de instancia de un método de envío dentro de su zona.',
			'method_id'    => 'Método de envío disponible (flat_rate, free_shipping, local_pickup…).',
			'tax_class'    => 'Slug de la clase de impuesto (p. ej. reduced-rate).',
			'rate_id'      => 'ID de la tasa de impuesto.',
			'gateway_id'   => 'ID de la pasarela de pago (bacs, cheque, cod…).',
		);
		return isset( $help[ $key ] ) ? $help[ $key ] : '';
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Permisos.

	/** Tipo de acción para las capacidades de WooCommerce: read, create, edit, delete o batch. */
	public static function context( string $action ): string {
		$map = array(
			'list'   => 'read',
			'get'    => 'read',
			'create' => 'create',
			'update' => 'edit',
			'delete' => 'delete',
			'batch'  => 'batch',
		);
		return isset( $map[ $action ] ) ? $map[ $action ] : 'read';
	}

	/**
	 * Capacidad que la REST de WooCommerce exige para el recurso y la acción, con sus valores por defecto (los de un
	 * tipo de contenido o una taxonomía se leen de su objeto si existe). En un informe, la de los informes; la bitácora
	 * exige administrar WooCommerce.
	 */
	public static function capability( string $tool, string $resource, string $action ): string {
		if ( 'reports' === $tool ) {
			return 'log' === $action ? 'manage_woocommerce' : 'view_woocommerce_reports';
		}
		if ( ! isset( self::RESOURCES[ $resource ] ) ) {
			return 'do_not_allow';
		}
		$context = self::context( $action );
		$source  = self::RESOURCES[ $resource ]['caps'];
		if ( 0 === strpos( $source, 'post:' ) ) {
			return self::post_capability( substr( $source, 5 ), $context );
		}
		if ( 0 === strpos( $source, 'term:' ) ) {
			return self::term_capability( substr( $source, 5 ), $context );
		}
		switch ( $source ) {
			case 'attributes':
				return 'manage_product_terms';
			case 'review':
				return 'read' === $context ? 'moderate_comments' : 'edit_products';
			case 'user':
				$users = array(
					'read'   => 'list_users',
					'create' => 'create_customers',
					'edit'   => 'edit_users',
					'delete' => 'delete_users',
					'batch'  => 'promote_users',
				);
				return $users[ $context ];
			case 'manager':
				return 'manage_woocommerce';
		}
		return 'do_not_allow';
	}

	/** `wc_rest_check_post_permissions()`: read_private, publish, edit, delete y edit_others del tipo de contenido. */
	private static function post_capability( string $post_type, string $context ): string {
		$generic = array(
			'read'   => 'read_private_posts',
			'create' => 'publish_posts',
			'edit'   => 'edit_posts',
			'delete' => 'delete_posts',
			'batch'  => 'edit_others_posts',
		);
		$cap = $generic[ $context ];
		if ( function_exists( 'get_post_type_object' ) ) {
			$object = get_post_type_object( $post_type );
			if ( is_object( $object ) && isset( $object->cap ) && is_object( $object->cap ) && isset( $object->cap->$cap ) && is_string( $object->cap->$cap ) ) {
				return $object->cap->$cap;
			}
		}
		return str_replace( 'posts', $post_type . 's', $cap );
	}

	/** `wc_rest_check_product_term_permissions()`: manage_terms, edit_terms y delete_terms de la taxonomía. */
	private static function term_capability( string $taxonomy, string $context ): string {
		$generic = array(
			'read'   => 'manage_terms',
			'create' => 'edit_terms',
			'edit'   => 'edit_terms',
			'delete' => 'delete_terms',
			'batch'  => 'edit_terms',
		);
		$cap = $generic[ $context ];
		if ( 'pa' !== $taxonomy && function_exists( 'get_taxonomy' ) ) {
			$object = get_taxonomy( $taxonomy );
			if ( is_object( $object ) && isset( $object->cap ) && is_object( $object->cap ) && isset( $object->cap->$cap ) && is_string( $object->cap->$cap ) ) {
				return $object->cap->$cap;
			}
		}
		return str_replace( '_terms', '_product_terms', $cap );
	}

	/** Mínimo para usar la herramienta: deja fuera a un editor y a un suscriptor aunque WooCommerce les deje algo. */
	public static function floor( string $tool ): string {
		return isset( self::TOOLS[ $tool ] ) ? self::TOOLS[ $tool ]['floor'] : 'do_not_allow';
	}
}
