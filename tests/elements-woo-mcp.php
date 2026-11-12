<?php
/**
 * WooCommerce en el MCP de DIGITALÍSIMO Elements (`modules/digitalisimo-woo/mcp/`): definiciones (IDs, esquemas
 * cerrados, anotaciones), tabla acción → ruta sin inyección (IDs no enteros, `../`, rutas ajenas, parámetros de la ruta
 * en el cuerpo o en la consulta), modos off/read/write con la herencia de la red, `force` y `api_refund` por defecto,
 * enmascarado de secretos, bitácora sin datos personales, permisos y el registro en el contrato de abilities del núcleo
 * (con su registro y sin él).
 *
 * Sin WordPress, WooCommerce ni el núcleo: dobles mínimos. La REST de WooCommerce se sustituye por un despachador que
 * anota cada petición y responde lo que el caso pide (`$GLOBALS['wm_rest']`).
 */
namespace Elementor\Modules\Mcp\Abilities {
	class Ability_Definition {
		public $label;
		public $description;
		public $category;
		public $output_schema;
		public $meta;
		public $permission_callback;
		public $input_schema;
		public function __construct( $label, $description, $category, $output_schema, $meta, $permission_callback, $input_schema = array() ) {
			$this->label               = $label;
			$this->description         = $description;
			$this->category            = $category;
			$this->output_schema       = $output_schema;
			$this->meta                = $meta;
			$this->permission_callback = $permission_callback;
			$this->input_schema        = $input_schema;
		}
	}
	/** Lo que el núcleo hace en `execute_guarded()` y `check_permission()`. */
	abstract class Abstract_Ability {
		const ABILITY_ID_PREFIX = 'elementor/';
		private $cached = null;
		abstract protected function get_ability_id(): string;
		abstract protected function get_definition(): Ability_Definition;
		abstract public function execute( $input = array() );
		final public function execute_guarded( $input = array() ) {
			$GLOBALS['wm_guarded'][] = $this->get_id();
			$result = $this->execute( $input );
			if ( isset( $input['post_id'] ) ) {
				$GLOBALS['wm_post_marked'][] = $input['post_id'];
			}
			return $result;
		}
		public function check_permission(): bool {
			// Como el núcleo real (abstract-ability.php): (bool) del callback; un WP_Error valdría true.
			return (bool) call_user_func( $this->definition()->permission_callback );
		}
		public function register(): void {
			$GLOBALS['wm_core_register'][] = $this->get_id();
		}
		public function get_id(): string {
			return $this->get_ability_id();
		}
		public function is_exposed_via_proxy(): bool {
			return true;
		}
		public function is_exposed_on_server(): bool {
			return true;
		}
		protected function definition(): Ability_Definition {
			if ( null === $this->cached ) {
				$this->cached = $this->get_definition();
			}
			return $this->cached;
		}
	}
}

namespace Elementor\Modules\Mcp\Registry {
	class Ability_Registry {
		public $abilities = array();
		public function add( $ability ): void {
			$this->abilities[ $ability->get_id() ] = $ability;
		}
	}
}

namespace Elementor\Modules\Mcp {
	class Module {
		public $test_registry;
		public function registry() {
			return $this->test_registry;
		}
	}
}

namespace Elementor\MCP\Composer\Admin {
	class McpSettingsController {
		public static function is_enabled(): bool {
			return ! empty( $GLOBALS['wm']['options']['elementor_mcp_enabled'] );
		}
	}
}

namespace Elementor {
	class Plugin {
		public static $instance;
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	// Cualquier aviso o advertencia de PHP es un fallo (un índice que falta, una conversión dudosa…).
	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message . ' en ' . basename( $file ) . ':' . $line, 0, $severity, $file, $line );
		}
	);
	/** Comprobación de un hallazgo de la revisión (R1…R6): se anota y la prueba sigue; falla al final con la lista. */
	function review( string $label, $ok, string $message ) {
		if ( ! $ok ) {
			$GLOBALS['wm_review'][ $label ][] = $message;
		}
		$GLOBALS['wm_review_count'] = ( $GLOBALS['wm_review_count'] ?? 0 ) + 1;
	}
	/** Un bloque de la revisión: una excepción o un aviso de PHP dentro cuentan como falla de ese hallazgo. */
	function review_block( string $label, callable $block ) {
		try {
			$block();
		} catch ( Throwable $e ) {
			review( $label, false, 'excepción: ' . $e->getMessage() );
		}
	}
	function check( $ok, string $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	}

	class WP_Error {
		private $code;
		private $message;
		private $data;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
		public function get_error_data() {
			return $this->data;
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	/** Estado de WordPress en memoria. */
	function wm_reset( array $over = array() ): void {
		$GLOBALS['wm'] = array_merge(
			array(
				'options'   => array( 'elementor_mcp_enabled' => true ),
				'autoload'  => array(),
				'site'      => array(),
				'multisite' => false,
				'blog'      => 1,
				'caps'      => array(),
				'user'      => 7,
				'hooks'     => array(),
				'admin'     => false,
			),
			$over
		);
		$GLOBALS['wm_rest']          = array();
		$GLOBALS['wm_rest_responses'] = array();
		$GLOBALS['wm_registered']    = array();
		$GLOBALS['wm_categories']    = array();
		$GLOBALS['wm_guarded']       = array();
		$GLOBALS['wm_post_marked']   = array();
		$GLOBALS['wm_core_register'] = array();
	}
	wm_reset();

	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['wm']['hooks'][ $hook ][] = $callback;
	}
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['wm']['hooks'][ $hook ][] = $callback;
	}
	function apply_filters( $hook, $value ) {
		foreach ( $GLOBALS['wm']['hooks'][ $hook ] ?? array() as $callback ) {
			$value = call_user_func( $callback, $value );
		}
		return $value;
	}
	function do_action( $hook ) {
		foreach ( $GLOBALS['wm']['hooks'][ $hook ] ?? array() as $callback ) {
			call_user_func( $callback );
		}
	}
	function get_option( $name, $default = false ) {
		$GLOBALS['wm_reads'][] = $name;
		return array_key_exists( $name, $GLOBALS['wm']['options'] ) ? $GLOBALS['wm']['options'][ $name ] : $default;
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['wm']['options'][ $name ] = $value;
		if ( null !== $autoload ) {
			$GLOBALS['wm']['autoload'][ $name ] = $autoload;
		}
		return true;
	}
	function add_option( $name, $value, $deprecated = '', $autoload = true ) {
		$GLOBALS['wm']['options'][ $name ]  = $value;
		$GLOBALS['wm']['autoload'][ $name ] = $autoload;
		return true;
	}
	function get_site_option( $name, $default = false ) {
		$GLOBALS['wm_reads'][] = 'site:' . $name;
		return array_key_exists( $name, $GLOBALS['wm']['site'] ) ? $GLOBALS['wm']['site'][ $name ] : $default;
	}
	function update_site_option( $name, $value ) {
		$GLOBALS['wm']['site'][ $name ] = $value;
		return true;
	}
	function is_multisite() {
		return $GLOBALS['wm']['multisite'];
	}
	function is_admin() {
		return $GLOBALS['wm']['admin'];
	}
	function get_current_blog_id() {
		return $GLOBALS['wm']['blog'];
	}
	function get_current_user_id() {
		return $GLOBALS['wm']['user'];
	}
	function current_user_can( $cap ) {
		return in_array( $cap, $GLOBALS['wm']['caps'], true );
	}
	function admin_url( $path = '' ) {
		return 'https://tienda.test/wp-admin/' . $path;
	}
	function network_admin_url( $path = '' ) {
		return 'https://tienda.test/wp-admin/network/' . $path;
	}
	function rest_url( $path = '' ) {
		return 'https://tienda.test/wp-json/' . $path;
	}
	function add_query_arg( array $args, $url ) {
		return $url . '?' . http_build_query( $args );
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_url( $url ) {
		return (string) $url;
	}
	function wp_nonce_field( $action ) {
		echo '<input type="hidden" name="_wpnonce" value="nonce:' . esc_attr( $action ) . '">';
	}
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
	function wp_unslash( $value ) {
		return $value;
	}
	function wp_verify_nonce( $nonce, $action ) {
		return 'nonce:' . $action === $nonce;
	}
	function sanitize_text_field( $text ) {
		return trim( (string) $text );
	}
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
	/** La petición interna a WooCommerce (`Executor::rest()`): se anota y responde `$GLOBALS['wm_rest_responses']`. */
	class WP_REST_Request {
		public $method;
		public $route;
		public $query   = array();
		public $headers = array();
		public $body    = '';
		public function __construct( $method, $route ) {
			$this->method = $method;
			$this->route  = $route;
		}
		public function set_query_params( $query ) {
			$this->query = $query;
		}
		public function set_header( $name, $value ) {
			$this->headers[ $name ] = $value;
		}
		public function set_body( $body ) {
			$this->body = $body;
		}
	}
	class WP_REST_Response {
		private $status;
		private $data;
		private $headers;
		public function __construct( $data, $status, $headers = array() ) {
			$this->data    = $data;
			$this->status  = $status;
			$this->headers = $headers;
		}
		public function get_status() {
			return $this->status;
		}
		public function get_data() {
			return $this->data;
		}
		public function get_headers() {
			return $this->headers;
		}
		public function is_error() {
			return $this->status >= 400;
		}
		public function as_error() {
			return new WP_Error( $this->data['code'], $this->data['message'], $this->data['data'] );
		}
	}
	function rest_do_request( $request ) {
		check( 'application/json' === ( $request->headers['Content-Type'] ?? 'application/json' ), 'El cuerpo viaja como JSON.' );
		list( $status, $data, $headers ) = wm_dispatch( $request->method, $request->route, $request->query, '' === $request->body ? null : json_decode( $request->body, true ) );
		return new WP_REST_Response( $data, $status, $headers );
	}
	function wp_register_ability( $id, $args ) {
		$GLOBALS['wm_registered'][ $id ] = $args;
	}
	function wp_register_ability_category( $id, $args ) {
		$GLOBALS['wm_categories'][ $id ] = $args;
	}

	/** Despachador de prueba: anota la petición y responde lo que el caso preparó para ese método y ruta. */
	function wm_dispatch( $method, $route, $query, $body ) {
		$GLOBALS['wm_rest'][] = array( $method, $route, $query, $body );
		$key                  = $method . ' ' . $route;
		if ( isset( $GLOBALS['wm_rest_responses'][ $key ] ) ) {
			return $GLOBALS['wm_rest_responses'][ $key ];
		}
		return array( 'GET' === $method ? 200 : ( 'POST' === $method ? 201 : 200 ), array( 'id' => 1, 'name' => 'Algo' ), array( 'X-WP-Total' => '3', 'X-WP-TotalPages' => '1' ) );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Carga: el módulo Woo (autocarga acotada) con WooCommerce 11.2.1 simulado.
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-woo/class-module.php';
	use Digitalisimo\Elements\Woo\Module as Woo;
	use Digitalisimo\Elements\Woo\Mcp\Tools;
	use Digitalisimo\Elements\Woo\Mcp\Router;
	use Digitalisimo\Elements\Woo\Mcp\Executor;
	use Digitalisimo\Elements\Woo\Mcp\Secrets;
	use Digitalisimo\Elements\Woo\Mcp\Log;
	use Digitalisimo\Elements\Woo\Mcp\Settings;
	use Digitalisimo\Elements\Woo\Mcp\Settings_Section;
	use Digitalisimo\Elements\Woo\Mcp\Module as Mcp;

	Woo::register_autoloader();
	check( in_array( 'Mcp\\Module', Woo::BOOT, true ), 'Mcp\\Module está en Woo\\Module::BOOT (sólo arranca con WooCommerce compatible).' );
	check( '' !== Woo::file_for( Mcp::class ) && '' !== Woo::file_for( Settings_Section::class ), 'La autocarga del módulo Woo encuentra mcp/class-module.php y mcp/class-settings-section.php.' );

	$run = static function ( string $tool, array $input ) {
		return Executor::run( $tool, $input, 'wm_dispatch' );
	};
	$all_keys = static function ( $value ) use ( &$all_keys ) {
		$keys = array();
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				$keys[] = (string) $key;
				$keys   = array_merge( $keys, $all_keys( $child ) );
			}
		}
		return $keys;
	};

	// =================================================================================================================
	// 1. Definiciones.
	check( array( 'products', 'catalog', 'orders', 'customers', 'coupons', 'store-settings', 'reports' ) === Tools::tools(), 'Siete herramientas, en orden.' );
	foreach ( Tools::tools() as $tool ) {
		$id = Tools::id( $tool );
		check( 1 === preg_match( '#^digitalisimo-woo/[a-z-]+$#', $id ) && $tool === Tools::tool_of( $id ), "ID propio sin el prefijo del núcleo: $id" );
		$def = Tools::definition( $tool, 'read' );
		check( 'digitalisimo-woo' === $def['category'], "Categoría propia: $tool" );
		check( '' !== $def['label'] && strlen( $def['description'] ) > 200, "Etiqueta y descripción para el modelo: $tool" );
		check( ! preg_match( '/\bElementor\b/', $def['label'] . $def['description'] ), "Sin el nombre del constructor original en lo visible: $tool" );
		$schema = $def['input_schema'];
		check( 'object' === $schema['type'] && false === $schema['additionalProperties'] && array( 'action' ) === $schema['required'], "Entrada cerrada con action obligatoria: $tool" );
		check( ! in_array( 'post_id', $all_keys( $schema ), true ), "Nunca la clave post_id en la entrada: $tool" );
		check( Tools::TOOLS[ $tool ]['actions'] === $schema['properties']['action']['enum'], "action con enum: $tool" );
		check( 100 === $schema['properties']['per_page']['maximum'] && 50 === $schema['properties']['fields']['maxItems'], "Listas paginadas (≤ 100) y fields: $tool" );
		check( false === $def['meta']['show_in_rest'] && false === $def['meta']['mcp']['public'], "Sólo en el servidor MCP de Elements (ni REST de abilities ni servidor genérico): $tool" );
		$ann = $def['meta']['annotations'];
		if ( 'reports' === $tool ) {
			check( true === $ann['readonly'] && false === $ann['destructive'] && true === $ann['idempotent'], 'Informes: sólo lectura.' );
			check( ! isset( $schema['properties']['data'] ) && ! isset( $schema['properties']['force'] ), 'Informes: sin data ni force.' );
		} else {
			check( false === $ann['readonly'] && true === $ann['destructive'], "Herramienta con delete: destructiva ($tool)" );
			check( false === $schema['properties']['force']['default'], "force por defecto false: $tool" );
		}
		foreach ( $schema['properties'] as $key => $prop ) {
			check( isset( $prop['type'] ) && isset( $prop['description'] ) && '' !== $prop['description'], "Cada parámetro con tipo y descripción: $tool.$key" );
		}
	}
	check( false === Tools::input_schema( 'orders' )['properties']['api_refund']['default'], 'api_refund por defecto false.' );
	check( ! isset( Tools::input_schema( 'products' )['properties']['api_refund'] ), 'api_refund sólo en pedidos.' );
	check( false !== strpos( Tools::definition( 'orders', 'read' )['description'], 'sólo permite leer' ) && false !== strpos( Tools::definition( 'orders', 'write' )['description'], 'permite escribir' ), 'La descripción dice el modo del sitio.' );
	foreach ( Tools::RESOURCES as $name => $def ) {
		check( in_array( $name, Tools::resources( $def['tool'] ), true ), "Recurso de su herramienta: $name" );
		check( false === strpos( $def['path'], '..' ) && 1 === preg_match( '#^[a-z_/{}]+$#', $def['path'] ), "Ruta fija de la tabla: $name" );
		preg_match_all( '/\{([a-z_]+)\}/', $def['path'], $m );
		check( $m[1] === array_keys( $def['parents'] ), "Cada marcador de la ruta tiene su ID: $name" );
		check( ! array_diff( $def['actions'], Tools::TOOLS[ $def['tool'] ]['actions'] ), "Acciones dentro del enum de la herramienta: $name" );
	}

	// =================================================================================================================
	// 2. Tabla acción → ruta.
	$cases = array(
		array( 'products', array( 'action' => 'list' ), 'GET', '/wc/v3/products', array( 'page' => 1, 'per_page' => 10 ) ),
		array( 'products', array( 'action' => 'list', 'query' => array( 'sku' => 'A1', 'category' => '7' ), 'per_page' => 100, 'page' => 2 ), 'GET', '/wc/v3/products', array( 'sku' => 'A1', 'category' => '7', 'page' => 2, 'per_page' => 100 ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => 12 ), 'GET', '/wc/v3/products/12', array() ),
		array( 'products', array( 'action' => 'get', 'product_id' => '12' ), 'GET', '/wc/v3/products/12', array() ),
		array( 'products', array( 'action' => 'update', 'resource' => 'variation', 'product_id' => 12, 'variation_id' => 34, 'data' => array( 'stock_quantity' => 5 ) ), 'PUT', '/wc/v3/products/12/variations/34', array() ),
		array( 'products', array( 'action' => 'batch', 'data' => array( 'create' => array( array( 'name' => 'A' ) ) ) ), 'POST', '/wc/v3/products/batch', array() ),
		array( 'products', array( 'action' => 'delete', 'product_id' => 12 ), 'DELETE', '/wc/v3/products/12', array() ),
		array( 'products', array( 'action' => 'delete', 'product_id' => 12, 'force' => true ), 'DELETE', '/wc/v3/products/12', array( 'force' => true ) ),
		array( 'catalog', array( 'action' => 'create', 'resource' => 'attribute_term', 'attribute_id' => 3, 'data' => array( 'name' => 'Rojo' ) ), 'POST', '/wc/v3/products/attributes/3/terms', array() ),
		array( 'catalog', array( 'action' => 'delete', 'resource' => 'category', 'term_id' => 9, 'force' => true ), 'DELETE', '/wc/v3/products/categories/9', array( 'force' => true ) ),
		array( 'catalog', array( 'action' => 'update', 'resource' => 'review', 'review_id' => 4, 'data' => array( 'status' => 'approved' ) ), 'PUT', '/wc/v3/products/reviews/4', array() ),
		array( 'orders', array( 'action' => 'update', 'order_id' => 5, 'data' => array( 'status' => 'completed' ) ), 'PUT', '/wc/v3/orders/5', array() ),
		array( 'orders', array( 'action' => 'create', 'resource' => 'note', 'order_id' => 5, 'data' => array( 'note' => 'Hola' ) ), 'POST', '/wc/v3/orders/5/notes', array() ),
		array( 'orders', array( 'action' => 'create', 'resource' => 'refund', 'order_id' => 5, 'data' => array( 'amount' => '10.00' ) ), 'POST', '/wc/v3/orders/5/refunds', array() ),
		array( 'orders', array( 'action' => 'delete', 'resource' => 'refund', 'order_id' => 5, 'refund_id' => 8, 'force' => true ), 'DELETE', '/wc/v3/orders/5/refunds/8', array( 'force' => true ) ),
		array( 'customers', array( 'action' => 'list', 'resource' => 'download', 'customer_id' => 2 ), 'GET', '/wc/v3/customers/2/downloads', null ),
		array( 'customers', array( 'action' => 'delete', 'customer_id' => 2, 'force' => true, 'reassign' => 1 ), 'DELETE', '/wc/v3/customers/2', array( 'force' => true, 'reassign' => 1 ) ),
		array( 'coupons', array( 'action' => 'create', 'data' => array( 'code' => 'otono', 'amount' => '10' ) ), 'POST', '/wc/v3/coupons', array() ),
		array( 'store-settings', array( 'action' => 'list', 'resource' => 'setting_group' ), 'GET', '/wc/v3/settings', null ),
		array( 'store-settings', array( 'action' => 'update', 'resource' => 'setting', 'group_id' => 'general', 'setting_id' => 'woocommerce_currency', 'data' => array( 'value' => 'MXN' ) ), 'PUT', '/wc/v3/settings/general/woocommerce_currency', array() ),
		array( 'store-settings', array( 'action' => 'batch', 'resource' => 'setting', 'group_id' => 'general', 'data' => array( 'update' => array( array( 'id' => 'woocommerce_currency', 'value' => 'MXN' ) ) ) ), 'POST', '/wc/v3/settings/general/batch', array() ),
		array( 'store-settings', array( 'action' => 'update', 'resource' => 'shipping_zone_location', 'zone_id' => 3, 'data' => array( 'locations' => array( array( 'code' => 'MX', 'type' => 'country' ) ) ) ), 'PUT', '/wc/v3/shipping/zones/3/locations', array() ),
		array( 'store-settings', array( 'action' => 'get', 'resource' => 'shipping_zone', 'zone_id' => 0 ), 'GET', '/wc/v3/shipping/zones/0', array() ),
		array( 'store-settings', array( 'action' => 'update', 'resource' => 'shipping_zone_method', 'zone_id' => 3, 'instance_id' => 6, 'data' => array( 'enabled' => true ) ), 'PUT', '/wc/v3/shipping/zones/3/methods/6', array() ),
		array( 'store-settings', array( 'action' => 'delete', 'resource' => 'tax_class', 'tax_class' => 'reduced-rate', 'force' => true ), 'DELETE', '/wc/v3/taxes/classes/reduced-rate', array( 'force' => true ) ),
		array( 'store-settings', array( 'action' => 'get', 'resource' => 'payment_gateway', 'gateway_id' => 'bacs' ), 'GET', '/wc/v3/payment_gateways/bacs', array() ),
		array( 'reports', array( 'action' => 'sales', 'query' => array( 'period' => 'month' ) ), 'GET', '/wc/v3/reports/sales', array( 'period' => 'month' ) ),
		array( 'reports', array( 'action' => 'customers_totals' ), 'GET', '/wc/v3/reports/customers/totals', array() ),
	);
	foreach ( $cases as $i => $case ) {
		list( $tool, $input, $method, $route, $query ) = $case;
		$plan = Router::resolve( $tool, $input );
		check( ! is_wp_error( $plan ), "Caso $i: plan válido (" . ( is_wp_error( $plan ) ? $plan->get_error_message() : '' ) . ')' );
		check( $method === $plan['method'] && $route === $plan['route'], "Caso $i: $method $route (salió {$plan['method']} {$plan['route']})" );
		if ( null !== $query ) { // null: la consulta de una lista sin paginar se comprueba en R4.
			check( $query == $plan['query'], "Caso $i: consulta " . json_encode( $plan['query'] ) ); // phpcs:ignore -- orden indiferente.
		}
	}
	$plan = Router::resolve( 'store-settings', array( 'action' => 'update', 'resource' => 'shipping_zone_location', 'zone_id' => 3, 'data' => array( 'locations' => array( array( 'code' => 'MX', 'type' => 'country' ) ) ) ) );
	check( array( array( 'code' => 'MX', 'type' => 'country' ) ) === $plan['body'], 'Las ubicaciones viajan como la lista JSON que WooCommerce reemplaza.' );
	$plan = Router::resolve( 'reports', array( 'action' => 'log', 'per_page' => 5 ) );
	check( 'log' === $plan['local'] && '' === $plan['route'] && 5 === $plan['query']['per_page'], 'La bitácora no llama a WooCommerce.' );

	// Sin inyección: IDs no enteros, recorridos, rutas ajenas, parámetros de la ruta en el cuerpo o la consulta.
	$bad = array(
		array( 'products', array( 'action' => 'get', 'product_id' => '12/../../wp/v2/users' ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => '../users' ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => -1 ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => 0 ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => 1.5 ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => '1 OR 1=1' ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => '12?force=true' ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => array( 12 ) ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => true ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => '0x1A' ) ),
		array( 'store-settings', array( 'action' => 'get', 'resource' => 'payment_gateway', 'gateway_id' => 'bacs/../../wp/v2/users' ) ),
		array( 'store-settings', array( 'action' => 'list', 'resource' => 'setting', 'group_id' => '../..' ) ),
		array( 'store-settings', array( 'action' => 'get', 'resource' => 'setting', 'group_id' => 'general', 'setting_id' => 'a%2Fb' ) ),
		array( 'store-settings', array( 'action' => 'delete', 'resource' => 'tax_class', 'tax_class' => 'a b', 'force' => true ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => 1, 'route' => '/wp/v2/users' ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => 1, 'method' => 'DELETE' ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => 1, 'path' => 'users' ) ),
		array( 'products', array( 'action' => 'get', 'post_id' => 1 ) ),
		array( 'products', array( 'action' => 'get', 'resource' => 'order', 'product_id' => 1 ) ),
		array( 'products', array( 'action' => 'purge' ) ),
		array( 'products', array( 'action' => 'GET' ) ),
		array( 'store-settings', array( 'action' => 'delete', 'resource' => 'setting', 'group_id' => 'general', 'setting_id' => 'x', 'force' => true ) ),
		array( 'store-settings', array( 'action' => 'create', 'resource' => 'payment_gateway', 'data' => array( 'id' => 'x' ) ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => 1, 'variation_id' => 2 ) ),
		array( 'products', array( 'action' => 'list', 'resource' => 'variation' ) ),
		array( 'orders', array( 'action' => 'get', 'resource' => 'note', 'order_id' => 1 ) ),
		array( 'products', array( 'action' => 'list', 'query' => array( 'A' => 1 ) ) ),
		array( 'products', array( 'action' => 'list', 'query' => array( 'search' => array( 'nested' => array( 1 ) ) ) ) ),
		array( 'products', array( 'action' => 'list', 'query' => array( 'x' ) ) ),
		array( 'products', array( 'action' => 'list', 'per_page' => 101 ) ),
		array( 'products', array( 'action' => 'list', 'per_page' => 0 ) ),
		array( 'products', array( 'action' => 'list', 'page' => '2' ) ),
		array( 'products', array( 'action' => 'list', 'fields' => array( 'name', 'x;y' ) ) ),
		array( 'products', array( 'action' => 'list', 'data' => array( 'a' => 1 ) ) ),
		array( 'products', array( 'action' => 'create' ) ),
		array( 'products', array( 'action' => 'create', 'data' => array() ) ),
		array( 'products', array( 'action' => 'create', 'data' => array( 1, 2 ) ) ),
		array( 'products', array( 'action' => 'update', 'product_id' => 1, 'data' => array( 'id' => 9 ) ) ),
		array( 'products', array( 'action' => 'delete', 'product_id' => 1, 'data' => array( 'x' => 1 ) ) ),
		array( 'products', array( 'action' => 'update', 'product_id' => 1, 'query' => array( 'x' => 1 ), 'data' => array( 'name' => 'A' ) ) ),
		array( 'products', array( 'action' => 'get', 'product_id' => 1, 'force' => 'yes' ) ),
		array( 'reports', array( 'action' => 'sales', 'data' => array( 'x' => 1 ) ) ),
		array( 'reports', array( 'action' => 'sales', 'resource' => 'x' ) ),
		array( 'nope', array( 'action' => 'list' ) ),
		array( 'products', 'list' ),
	);
	foreach ( $bad as $i => $case ) {
		$plan = Router::resolve( $case[0], $case[1] );
		check( is_wp_error( $plan ), "Rechazo $i: " . json_encode( $case ) );
		check( '' !== $plan->get_error_message() && 400 === $plan->get_error_data()['status'], "Rechazo $i: con un motivo claro." );
	}
	// Parámetros de la ruta, force, api_refund y `_…` fuera del cuerpo y de la consulta (el cuerpo les ganaría a la URL).
	$plan = Router::resolve( 'products', array( 'action' => 'update', 'resource' => 'variation', 'product_id' => 12, 'variation_id' => 34, 'data' => array( 'id' => 99, 'product_id' => 77, 'force' => true, '_method' => 'DELETE', 'regular_price' => '9.90' ) ) );
	check( array( 'regular_price' => '9.90' ) === $plan['body'] && '/wc/v3/products/12/variations/34' === $plan['route'], 'Del cuerpo salen id, product_id, force y _method: ' . json_encode( $plan['body'] ) );
	foreach ( array( '_method', '_fields', '_embed', '_envelope' ) as $key ) {
		check( is_wp_error( Router::resolve( 'products', array( 'action' => 'list', 'query' => array( $key => 'x' ) ) ) ), "Un filtro $key se rechaza." );
	}
	$plan = Router::resolve( 'products', array( 'action' => 'list', 'query' => array( 'id' => 5, 'force' => true, 'api_refund' => true, 'search' => 'mesa' ) ) );
	check( array( 'search' => 'mesa', 'page' => 1, 'per_page' => 10 ) == $plan['query'], 'De la consulta salen force, api_refund e id: ' . json_encode( $plan['query'] ) ); // phpcs:ignore
	$plan = Router::resolve( 'products', array( 'action' => 'get', 'product_id' => 3, 'query' => array( 'id' => 5, 'context' => 'edit' ) ) );
	check( array( 'context' => 'edit' ) === $plan['query'], 'En una ruta con {id}, id no viaja en la consulta.' );
	$plan = Router::resolve( 'orders', array( 'action' => 'update', 'order_id' => 5, 'data' => array( 'customer_id' => 2, 'status' => 'processing' ) ) );
	check( array( 'customer_id' => 2, 'status' => 'processing' ) === $plan['body'], 'customer_id de un pedido sí viaja (no es de su ruta).' );
	$plan = Router::resolve( 'catalog', array( 'action' => 'create', 'resource' => 'review', 'data' => array( 'product_id' => 4, 'review' => 'Bien', 'rating' => 5 ) ) );
	check( 4 === $plan['body']['product_id'], 'product_id de una reseña sí viaja (no es de su ruta).' );
	// Fuzz: ninguna entrada saca la ruta de /wc/v3/ ni mete «..», «?» o «#».
	$noise = array( '..', '../', '/', '?', '#', '%2e', '%2F', "\0", ' ', '12a', 'a12', '-3', '+3', '3.0', '1e3', 'ü', '', 'x/y' );
	$fuzz  = 0;
	foreach ( Tools::RESOURCES as $resource => $def ) {
		foreach ( $def['actions'] as $action ) {
			foreach ( $noise as $n ) {
				$input = array( 'action' => $action, 'resource' => $resource, 'data' => array( 'a' => 1 ), 'force' => true );
				foreach ( Tools::TOOLS[ $def['tool'] ]['ids'] as $key ) {
					$input[ $key ] = $n;
				}
				$plan = Router::resolve( $def['tool'], $input );
				++$fuzz;
				check( is_wp_error( $plan ), 'Fuzz: un ID con ' . json_encode( $n ) . " nunca llega a una ruta ($resource, $action)." );
			}
		}
	}
	check( $fuzz > 500, "Fuzz de rutas: $fuzz entradas." );

	// =================================================================================================================
	// 3. Guardas: papelera, force, lotes y api_refund.
	$plan = Router::resolve( 'products', array( 'action' => 'delete', 'product_id' => 12 ) );
	check( array() === $plan['query'] && true === $plan['destructive'], 'delete sin force: a la papelera (sin force en la consulta).' );
	foreach ( array( array( 'products', 'variation', array( 'product_id' => 1, 'variation_id' => 2 ) ), array( 'orders', 'note', array( 'order_id' => 1, 'note_id' => 2 ) ), array( 'orders', 'refund', array( 'order_id' => 1, 'refund_id' => 2 ) ), array( 'customers', 'customer', array( 'customer_id' => 2 ) ), array( 'catalog', 'tag', array( 'term_id' => 2 ) ), array( 'store-settings', 'shipping_zone', array( 'zone_id' => 2 ) ), array( 'store-settings', 'tax_rate', array( 'rate_id' => 2 ) ) ) as $case ) {
		$plan = Router::resolve( $case[0], array_merge( array( 'action' => 'delete', 'resource' => $case[1] ), $case[2] ) );
		check( is_wp_error( $plan ) && 'digitalisimo_woo_mcp_no_trash' === $plan->get_error_code() && false !== strpos( $plan->get_error_message(), 'force:true' ), "Sin papelera, delete sin force se rechaza antes de WooCommerce: {$case[1]}" );
	}
	foreach ( array( 'product', 'order', 'coupon', 'review' ) as $resource ) {
		check( true === Tools::RESOURCES[ $resource ]['trash'], "Con papelera: $resource" );
	}
	$plan = Router::resolve( 'products', array( 'action' => 'batch', 'data' => array( 'delete' => array( 1, 2 ) ) ) );
	check( is_wp_error( $plan ) && 'digitalisimo_woo_mcp_batch_force' === $plan->get_error_code(), 'Un lote con delete (WooCommerce lo borra sin papelera) exige force:true.' );
	$plan = Router::resolve( 'products', array( 'action' => 'batch', 'force' => true, 'data' => array( 'delete' => array( 1, 2 ), 'update' => array( array( 'id' => 3, 'name' => 'B' ) ) ) ) );
	check( ! is_wp_error( $plan ) && 3 === $plan['items'] && true === $plan['destructive'], 'Lote con force:true: 3 elementos y destructivo.' );
	$plan = Router::resolve( 'coupons', array( 'action' => 'batch', 'data' => array( 'create' => array_fill( 0, 101, array( 'code' => 'x' ) ) ) ) );
	check( is_wp_error( $plan ), 'Un lote de más de 100 elementos se rechaza.' );
	$plan = Router::resolve( 'products', array( 'action' => 'batch', 'data' => array( 'purge' => array( 1 ) ) ) );
	check( is_wp_error( $plan ), 'Un lote sólo lleva create, update y delete.' );
	$plan = Router::resolve( 'store-settings', array( 'action' => 'batch', 'resource' => 'setting', 'group_id' => 'general', 'data' => array( 'create' => array( array( 'id' => 'x' ) ) ) ) );
	check( is_wp_error( $plan ), 'El lote de ajustes sólo actualiza.' );
	$plan = Router::resolve( 'products', array( 'action' => 'update', 'product_id' => 1, 'force' => true, 'data' => array( 'name' => 'A' ) ) );
	check( is_wp_error( $plan ) && 'digitalisimo_woo_mcp_force' === $plan->get_error_code(), 'force:true fuera de delete o batch se rechaza.' );
	$plan = Router::resolve( 'products', array( 'action' => 'get', 'product_id' => 1, 'force' => false, 'page' => 1, 'per_page' => 10 ) );
	check( ! is_wp_error( $plan ) && array() === $plan['query'], 'Los valores por defecto que un cliente rellena (force false, page, per_page) se ignoran fuera de su acción.' );
	$plan = Router::resolve( 'orders', array( 'action' => 'create', 'resource' => 'refund', 'order_id' => 5, 'data' => array( 'amount' => '10.00', 'api_refund' => true ) ) );
	check( false === $plan['body']['api_refund'] && true === $plan['destructive'], 'Reembolso: api_refund false por defecto, aunque el cuerpo diga true (sólo vale el parámetro de primer nivel).' );
	$plan = Router::resolve( 'orders', array( 'action' => 'create', 'resource' => 'refund', 'order_id' => 5, 'api_refund' => true, 'data' => array( 'amount' => '10.00' ) ) );
	check( true === $plan['body']['api_refund'], 'Reembolso: api_refund true sólo si se pide explícitamente.' );
	$plan = Router::resolve( 'orders', array( 'action' => 'update', 'order_id' => 5, 'api_refund' => true, 'data' => array( 'status' => 'refunded' ) ) );
	check( is_wp_error( $plan ), 'api_refund true fuera de crear un reembolso se rechaza.' );
	$plan = Router::resolve( 'customers', array( 'action' => 'get', 'customer_id' => 2, 'reassign' => 1 ) );
	check( is_wp_error( $plan ), 'reassign sólo al borrar un cliente.' );

	// =================================================================================================================
	// 4. Modos off/read/write con la herencia de la red.
	wm_reset();
	check( 'read' === Settings::mode() && 'default' === Settings::origin(), 'Sitio único sin guardar: sólo lectura (predeterminado).' );
	update_option( Settings::OPTION, Settings::sanitize( array( 'mode' => 'write' ) ), false );
	check( 'write' === Settings::mode() && 'site' === Settings::origin() && array( 'mode' ) === Settings::stored()['own'], 'Sitio único: lo guardado rige, sin herencia.' );
	check( false === $GLOBALS['wm']['autoload'][ Settings::OPTION ], 'El modo se guarda sin carga automática.' );
	update_option( Settings::OPTION, Settings::sanitize( array( 'mode' => 'root' ) ) );
	check( 'write' === Settings::mode(), 'Un modo desconocido conserva el guardado.' );
	wm_reset( array( 'multisite' => true ) );
	check( 'read' === Settings::mode() && Settings::inherits_network() && 'default' === Settings::origin(), 'Red sin valores: el sitio hereda el predeterminado.' );
	Settings::save_network( array( 'mode' => 'write' ) );
	check( 'write' === Settings::mode() && 'network' === Settings::origin(), 'Un valor de red recién guardado rige en el sitio que nunca personalizó.' );
	Settings::save_network( array( 'mode' => 'everything' ) );
	check( array( 'mode' => 'write' ) === get_site_option( Settings::NETWORK_OPTION ), 'La red no guarda un modo desconocido.' );
	update_option( Settings::OPTION, Settings::sanitize( array( 'mode' => 'off', 'inherit' => array( 'mode' => '0' ) ) ) );
	check( 'off' === Settings::mode() && 'site' === Settings::origin() && ! Settings::inherits_network(), 'El sitio personaliza y apaga, aunque la red diga escritura.' );
	update_option( Settings::OPTION, Settings::sanitize( array( 'inherit' => array( 'mode' => '1' ) ) ) );
	check( 'write' === Settings::mode() && Settings::inherits_network() && 'off' === Settings::stored()['mode'], 'Volver a heredar (desplegable deshabilitado, sin modo): rige la red y lo del sitio se conserva.' );
	Settings::save_network( array( 'mode' => 'off' ) );
	check( 'off' === Settings::mode(), 'La red apaga a los sitios que heredan.' );
	check( array( 'mode' => 'read' ) === Settings::defaults() && array( 'off', 'read', 'write' ) === Settings::MODES, 'Tres modos; predeterminado sólo lectura.' );

	// La sección de la pantalla: «Heredar de la red» sólo en el sitio de una red.
	wm_reset( array( 'multisite' => true ) );
	ob_start();
	Settings_Section::render( false );
	$site_html = (string) ob_get_clean();
	ob_start();
	Settings_Section::render( true );
	$net_html = (string) ob_get_clean();
	check( false !== strpos( $site_html, 'Heredar de la red' ) && false !== strpos( $site_html, 'data-field="digitalisimo-woo-mcp-mode"' ) && false !== strpos( $site_html, 'value="' . Settings_Section::ACTION_SAVE . '"' ), 'Sitio de una red: desplegable, «Heredar de la red» y su propia acción.' );
	check( false === strpos( $net_html, 'Heredar de la red' ) && false !== strpos( $net_html, 'name="' . Settings::NETWORK_OPTION . '[mode]"' ) && false !== strpos( $net_html, 'nonce:' . Settings_Section::ACTION_SAVE_NETWORK . '|network' ), 'Red: el desplegable de red, sin «Heredar de la red», con el nonce de red.' );
	check( 1 === substr_count( $site_html, '<form' ) && 1 === substr_count( $net_html, '<form' ) && false !== strpos( $site_html, 'id="digitalisimo-woo-mcp"' ), 'Un formulario propio por superficie, con su ancla.' );
	check( ! preg_match( '/\bElementor\b/', $site_html . $net_html ), 'La sección no nombra el constructor original.' );
	wm_reset();
	ob_start();
	Settings_Section::render( false );
	check( false === strpos( (string) ob_get_clean(), 'Heredar de la red' ), 'Sitio único: sin «Heredar de la red».' );

	// Guardados: capacidad por acción, sólo POST, nonce de la acción y del sitio; el sitio sin carga automática.
	wm_reset( array( 'multisite' => true, 'caps' => array( 'manage_options' ) ) );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = array( '_wpnonce' => 'nonce:' . Settings_Section::nonce_action( Settings_Section::ACTION_SAVE ), Settings::OPTION => array( 'mode' => 'write', 'inherit' => array( 'mode' => '0' ) ) );
	check( array( 'notice' => 'saved' ) === Settings_Section::process_save() && 'write' === Settings::mode() && 'site' === Settings::origin() && false === $GLOBALS['wm']['autoload'][ Settings::OPTION ], 'Guardar en el sitio: personaliza el modo (sin carga automática).' );
	$_POST['_wpnonce'] = 'nonce:' . Settings_Section::ACTION_SAVE . '|2';
	check( 'nonce' === Settings_Section::process_save()['notice'], 'Un nonce de otro sitio de la red no vale.' );
	$_POST = array( '_wpnonce' => 'nonce:' . Settings_Section::nonce_action( Settings_Section::ACTION_SAVE_NETWORK ), Settings::NETWORK_OPTION => array( 'mode' => 'off' ) );
	check( 'forbidden' === Settings_Section::process_save_network()['notice'] && array() === $GLOBALS['wm']['site'], 'Un administrador de sitio no guarda la red.' );
	$GLOBALS['wm']['caps'][] = 'manage_network_options';
	check( 'network-saved' === Settings_Section::process_save_network()['notice'] && array( 'mode' => 'off' ) === get_site_option( Settings::NETWORK_OPTION ), 'La red guarda su modo.' );
	$GLOBALS['wm']['caps']     = array( 'edit_posts' );
	$_POST['_wpnonce']         = 'nonce:' . Settings_Section::nonce_action( Settings_Section::ACTION_SAVE );
	check( 'forbidden' === Settings_Section::process_save()['notice'], 'Un editor no guarda el modo.' );
	$GLOBALS['wm']['caps']     = array( 'manage_options' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	check( 'method' === Settings_Section::process_save()['notice'], 'Sólo por POST.' );
	$_POST = array();

	// Ejecución por modo: read rechaza escrituras con un error que dice dónde se cambia; off no hace nada.
	wm_reset( array( 'caps' => array( 'edit_products', 'publish_products', 'read_private_products', 'delete_products', 'edit_others_products', 'view_woocommerce_reports' ) ) );
	$result = $run( 'products', array( 'action' => 'create', 'data' => array( 'name' => 'Mesa' ) ) );
	check( is_wp_error( $result ) && 'digitalisimo_woo_mcp_read_only' === $result->get_error_code(), 'Modo read: create se rechaza.' );
	check( false !== strpos( $result->get_error_message(), '«Rendimiento»' ) && false !== strpos( $result->get_error_message(), 'page=digitalisimo-elements-front#digitalisimo-woo-mcp' ), 'El error dice dónde se cambia el modo: ' . $result->get_error_message() );
	check( array() === $GLOBALS['wm_rest'], 'Modo read: ninguna petición a WooCommerce.' );
	check( 'denied' === Log::entries()[0]['result'] && 'read_mode' === Log::entries()[0]['code'], 'El rechazo queda en la bitácora.' );
	$GLOBALS['wm_rest_responses']['GET /wc/v3/products'] = array( 200, array( array( 'id' => 1, 'name' => 'Mesa', 'price' => '10', 'description' => 'Larga' ), array( 'id' => 2, 'name' => 'Silla', 'price' => '5' ) ), array( 'X-WP-Total' => '12', 'X-WP-TotalPages' => '2' ) );
	$result = $run( 'products', array( 'action' => 'list', 'fields' => array( 'name' ) ) );
	check( ! is_wp_error( $result ) && 12 === $result['total'] && 2 === $result['total_pages'] && 1 === $result['page'] && 10 === $result['per_page'], 'Modo read: list funciona, con total y páginas.' );
	check( array( array( 'id' => 1, 'name' => 'Mesa' ), array( 'id' => 2, 'name' => 'Silla' ) ) === $result['data'], 'fields recorta cada elemento (siempre con id).' );
	foreach ( array( 'delete', 'update', 'batch' ) as $action ) {
		$input  = array( 'action' => $action, 'product_id' => 4, 'data' => array( 'name' => 'x' ) );
		$input  = 'delete' === $action ? array( 'action' => 'delete', 'product_id' => 4 ) : ( 'batch' === $action ? array( 'action' => 'batch', 'data' => array( 'update' => array( array( 'id' => 4 ) ) ) ) : $input );
		$result = $run( 'products', $input );
		check( is_wp_error( $result ) && 'digitalisimo_woo_mcp_read_only' === $result->get_error_code(), "Modo read: $action se rechaza." );
	}
	$GLOBALS['wm_rest_responses']['GET /wc/v3/reports'] = array( 200, array( array( 'slug' => 'sales', 'description' => 'Ventas' ) ), array() );
	$result = $run( 'reports', array( 'action' => 'list' ) );
	check( ! is_wp_error( $result ) && 'sales' === $result['data'][0]['slug'] && ! isset( $result['page'] ), 'reports list (sin paginar) en modo read.' );
	$result = $run( 'reports', array( 'action' => 'sales', 'query' => array( 'period' => 'month' ) ) );
	check( ! is_wp_error( $result ) && array( 'GET', '/wc/v3/reports/sales', array( 'period' => 'month' ), null ) === end( $GLOBALS['wm_rest'] ), 'reports sales con su periodo.' );
	update_option( Settings::OPTION, array( 'mode' => 'off', 'own' => array( 'mode' ) ) );
	$result = $run( 'products', array( 'action' => 'list' ) );
	check( is_wp_error( $result ) && 'digitalisimo_woo_mcp_off' === $result->get_error_code(), 'Modo off: ni siquiera lee.' );
	update_option( Settings::OPTION, array( 'mode' => 'write', 'own' => array( 'mode' ) ) );
	$GLOBALS['wm_rest'] = array();
	$result             = $run( 'products', array( 'action' => 'delete', 'product_id' => 12 ) );
	check( ! is_wp_error( $result ) && 'trash' === $result['deleted'] && array( array( 'DELETE', '/wc/v3/products/12', array(), null ) ) === $GLOBALS['wm_rest'], 'Modo write: delete va a la papelera.' );
	$result = $run( 'products', array( 'action' => 'delete', 'product_id' => 12, 'force' => true ) );
	check( 'permanent' === $result['deleted'], 'force:true: borrado definitivo, dicho en la respuesta.' );
	$GLOBALS['wm_rest_responses']['POST /wc/v3/products'] = array( 400, array( 'code' => 'woocommerce_rest_invalid_sku', 'message' => 'SKU repetido.', 'data' => array( 'status' => 400, 'params' => array( 'sku' => 'ABC' ) ) ), array() );
	$result = $run( 'products', array( 'action' => 'create', 'data' => array( 'name' => 'A', 'sku' => 'ABC' ) ) );
	check( is_wp_error( $result ) && 'woocommerce_rest_invalid_sku' === $result->get_error_code() && array( 'status' => 400 ) === $result->get_error_data(), 'Un error de WooCommerce vuelve con su código y su mensaje, sin sus datos.' );
	check( 'error' === Log::entries()[0]['result'] && 'woocommerce_rest_invalid_sku' === Log::entries()[0]['code'], 'El error queda en la bitácora con su código.' );

	// =================================================================================================================
	// 5. Secretos.
	$gateway = array(
		'id'       => 'digi_test',
		'enabled'  => true,
		'settings' => array(
			'title'          => array( 'id' => 'title', 'type' => 'text', 'value' => 'Pago de prueba' ),
			'api_key'        => array( 'id' => 'api_key', 'type' => 'password', 'value' => 'sk_live_SECRETO', 'default' => '' ),
			'identity_token' => array( 'id' => 'identity_token', 'type' => 'text', 'value' => 'tok_SECRETO' ),
			'empty_pass'     => array( 'id' => 'empty_pass', 'type' => 'password', 'value' => '' ),
		),
	);
	$masked = Secrets::mask( array( $gateway ) );
	$text   = json_encode( $masked );
	check( false === strpos( $text, 'SECRETO' ) && Secrets::MASK === $masked[0]['settings']['api_key']['value'] && true === $masked[0]['settings']['api_key']['masked'], 'Una clave de tipo password sale enmascarada.' );
	check( Secrets::MASK === $masked[0]['settings']['identity_token']['value'], 'Un token en un campo de texto también.' );
	check( '' === $masked[0]['settings']['empty_pass']['value'] && 'Pago de prueba' === $masked[0]['settings']['title']['value'], 'Una clave vacía sigue vacía y lo demás sale tal cual.' );
	$options = array( array( 'id' => 'woocommerce_currency', 'type' => 'select', 'value' => 'MXN' ), array( 'id' => 'woocommerce_x_password', 'type' => 'password', 'value' => 'abc' ) );
	check( 'MXN' === Secrets::mask( $options )[0]['value'] && Secrets::MASK === Secrets::mask( $options )[1]['value'], 'Opciones de ajustes: sólo las secretas.' );
	check( array( 'api_key', 'identity_token', 'empty_pass' ) === Secrets::secret_ids( $gateway ) && array( 'woocommerce_x_password' ) === Secrets::secret_ids( $options ), 'IDs secretos de una pasarela y de un grupo.' );
	foreach ( array( '', '   ', null, Secrets::MASK, '********', '••••', '···' ) as $value ) {
		check( Secrets::keeps_stored( $value ), 'Vacío o máscara conserva el guardado: ' . json_encode( $value ) );
	}
	check( ! Secrets::keeps_stored( 'nueva' ) && ! Secrets::keeps_stored( 0 ), 'Un valor nuevo sí se escribe.' );

	wm_reset( array( 'caps' => array( 'manage_woocommerce' ), 'options' => array( 'elementor_mcp_enabled' => true, Settings::OPTION => array( 'mode' => 'write', 'own' => array( 'mode' ) ) ) ) );
	$GLOBALS['wm_rest_responses']['GET /wc/v3/payment_gateways/digi_test'] = array( 200, $gateway, array() );
	$GLOBALS['wm_rest_responses']['PUT /wc/v3/payment_gateways/digi_test'] = array( 200, $gateway, array() );
	$result = $run( 'store-settings', array( 'action' => 'get', 'resource' => 'payment_gateway', 'gateway_id' => 'digi_test' ) );
	check( false === strpos( json_encode( $result ), 'SECRETO' ) && Secrets::MASK === $result['data']['settings']['api_key']['value'], 'get de una pasarela: la clave enmascarada.' );
	$GLOBALS['wm_rest'] = array();
	$result             = $run( 'store-settings', array( 'action' => 'update', 'resource' => 'payment_gateway', 'gateway_id' => 'digi_test', 'data' => array( 'title' => 'Nuevo', 'settings' => array( 'api_key' => Secrets::MASK, 'identity_token' => '', 'title' => 'Nuevo' ) ) ) );
	$put                = end( $GLOBALS['wm_rest'] );
	check( 'PUT' === $put[0] && array( 'title' => 'Nuevo', 'settings' => array( 'title' => 'Nuevo' ) ) === $put[3], 'update con la máscara o vacío: esas claves no se envían (se conservan): ' . json_encode( $put[3] ) );
	check( false !== strpos( $result['notice'], '2 claves' ) && false === strpos( json_encode( $result ), 'SECRETO' ), 'La respuesta dice cuántas se conservaron y sigue enmascarada.' );
	$GLOBALS['wm_rest'] = array();
	$run( 'store-settings', array( 'action' => 'update', 'resource' => 'payment_gateway', 'gateway_id' => 'digi_test', 'data' => array( 'settings' => array( 'api_key' => 'sk_live_NUEVA' ) ) ) );
	$put = end( $GLOBALS['wm_rest'] );
	check( array( 'settings' => array( 'api_key' => 'sk_live_NUEVA' ) ) === $put[3], 'Escribir una clave nueva sí se permite.' );
	check( false === strpos( json_encode( Log::entries() ), 'NUEVA' ), 'La clave nueva no llega a la bitácora.' );
	$GLOBALS['wm_rest'] = array();
	$result             = $run( 'store-settings', array( 'action' => 'update', 'resource' => 'payment_gateway', 'gateway_id' => 'digi_test', 'data' => array( 'settings' => array( 'api_key' => '' ) ) ) );
	check( 1 === count( $GLOBALS['wm_rest'] ) && 'GET' === $GLOBALS['wm_rest'][0][0] && null === $result['data'] && 'ok' === Log::entries()[0]['result'], 'Sólo una clave vacía: nada que enviar, nada cambia.' );
	$GLOBALS['wm_rest_responses']['GET /wc/v3/settings/advanced/woocommerce_x_password'] = array( 200, $options[1], array() );
	$GLOBALS['wm_rest'] = array();
	$run( 'store-settings', array( 'action' => 'update', 'resource' => 'setting', 'group_id' => 'advanced', 'setting_id' => 'woocommerce_x_password', 'data' => array( 'value' => '' ) ) );
	check( 1 === count( $GLOBALS['wm_rest'] ), 'Una opción secreta con valor vacío no se envía.' );
	$GLOBALS['wm_rest_responses']['GET /wc/v3/settings/advanced'] = array( 200, $options, array() );
	$GLOBALS['wm_rest'] = array();
	$run( 'store-settings', array( 'action' => 'batch', 'resource' => 'setting', 'group_id' => 'advanced', 'data' => array( 'update' => array( array( 'id' => 'woocommerce_x_password', 'value' => Secrets::MASK ), array( 'id' => 'woocommerce_currency', 'value' => 'USD' ) ) ) ) );
	$post = end( $GLOBALS['wm_rest'] );
	check( 'POST' === $post[0] && '/wc/v3/settings/advanced/batch' === $post[1] && array( 'update' => array( array( 'id' => 'woocommerce_currency', 'value' => 'USD' ) ) ) === $post[3], 'Lote de opciones: la secreta enmascarada se quita, la otra viaja.' );

	// =================================================================================================================
	// 6. Bitácora sin datos personales.
	wm_reset( array( 'caps' => array( 'list_users', 'create_customers', 'view_woocommerce_reports' ), 'options' => array( 'elementor_mcp_enabled' => true, Settings::OPTION => array( 'mode' => 'write', 'own' => array( 'mode' ) ) ) ) );
	$GLOBALS['wm_rest_responses']['POST /wc/v3/customers'] = array( 201, array( 'id' => 44, 'email' => 'ana@example.test', 'first_name' => 'Ana', 'billing' => array( 'address_1' => 'Calle 1', 'phone' => '5555' ) ), array() );
	$result = $run( 'customers', array( 'action' => 'create', 'data' => array( 'email' => 'ana@example.test', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'password' => 'Secreta-123', 'billing' => array( 'address_1' => 'Calle 1', 'phone' => '5555' ) ) ) );
	check( ! is_wp_error( $result ) && 'ana@example.test' === $result['data']['email'], 'create de cliente: la respuesta es la de WooCommerce.' );
	$GLOBALS['wm_rest_responses']['POST /wc/v3/orders/5/refunds'] = array( 201, array( 'id' => 9, 'amount' => '123.45' ), array() );
	$GLOBALS['wm']['caps'][] = 'publish_shop_orders';
	$GLOBALS['wm']['caps'][] = 'edit_shop_orders';
	$run( 'orders', array( 'action' => 'create', 'resource' => 'refund', 'order_id' => 5, 'data' => array( 'amount' => '123.45', 'reason' => 'Ana pidió devolución' ) ) );
	$log  = Log::entries();
	$text = json_encode( $log );
	foreach ( array( 'ana@', 'Ana', 'Pérez', 'P\u00e9rez', 'Secreta', 'Calle', '5555', '123.45', 'devoluci' ) as $needle ) {
		check( false === strpos( $text, $needle ), "La bitácora no lleva datos personales ni valores: $needle" );
	}
	check( 2 === count( $log ) && array_keys( $log[0] ) === Log::FIELDS, 'Cada entrada lleva sólo sus campos.' );
	check( 'refund' === $log[0]['resource'] && 'create' === $log[0]['action'] && 5 === $log[0]['parent'] && 'ok' === $log[0]['result'] && 7 === $log[0]['user'] && 'orders' === $log[0]['tool'], 'Entrada: usuario, herramienta, acción, recurso, padre y resultado.' );
	check( 1 === preg_match( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $log[0]['time'] ), 'Fecha UTC.' );
	check( false === $GLOBALS['wm']['autoload'][ Log::OPTION ], 'La bitácora es una opción sin carga automática.' );
	for ( $i = 0; $i < 210; $i++ ) {
		Log::add( Log::entry( array( 'tool' => 'coupons', 'action' => 'update', 'resource' => 'coupon', 'id' => $i ), 'ok', '', 200, 1 ) );
	}
	check( Log::MAX === count( Log::entries() ) && 209 === Log::entries()[0]['id'], 'Últimas 200, la más reciente primero.' );
	$entry = Log::entry( array( 'tool' => 'x', 'action' => 'y', 'resource' => 'z', 'id' => 'ana@example.test', 'parent' => array( 'a' ) ), 'raro', 'Código <b>', -5, -1 );
	check( null === $entry['id'] && null === $entry['parent'] && 'error' === $entry['result'] && 'cdigob' === $entry['code'] && 0 === $entry['status'] && 0 === $entry['user'], 'entry() sólo acepta IDs de la ruta y códigos limpios.' );
	$GLOBALS['wm']['caps'][] = 'manage_woocommerce';
	$page                    = $run( 'reports', array( 'action' => 'log', 'per_page' => 5, 'page' => 2 ) );
	check( 5 === count( $page['data'] ) && 200 === $page['total'] && 40 === $page['total_pages'] && 204 === $page['data'][0]['id'], 'log: paginada, de sólo lectura.' );
	$GLOBALS['wm_rest'] = array();
	$run( 'reports', array( 'action' => 'log' ) );
	check( array() === $GLOBALS['wm_rest'], 'log no llama a WooCommerce.' );

	// =================================================================================================================
	// 7. Permisos.
	$roles = array(
		'administrator' => array( 'edit_products', 'read_private_products', 'publish_products', 'delete_products', 'edit_others_products', 'manage_product_terms', 'edit_product_terms', 'delete_product_terms', 'moderate_comments', 'edit_shop_orders', 'read_private_shop_orders', 'publish_shop_orders', 'delete_shop_orders', 'edit_shop_coupons', 'read_private_shop_coupons', 'publish_shop_coupons', 'delete_shop_coupons', 'list_users', 'create_customers', 'edit_users', 'delete_users', 'manage_woocommerce', 'view_woocommerce_reports' ),
		'shop_manager'  => array( 'edit_products', 'read_private_products', 'publish_products', 'delete_products', 'edit_others_products', 'manage_product_terms', 'edit_product_terms', 'delete_product_terms', 'moderate_comments', 'edit_shop_orders', 'read_private_shop_orders', 'publish_shop_orders', 'delete_shop_orders', 'edit_shop_coupons', 'read_private_shop_coupons', 'publish_shop_coupons', 'delete_shop_coupons', 'list_users', 'create_customers', 'edit_users', 'manage_woocommerce', 'view_woocommerce_reports' ),
		'editor'        => array( 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'moderate_comments', 'manage_categories', 'read' ),
		'subscriber'    => array( 'read' ),
	);
	$ask = static function ( string $role, string $tool, $input = null ) use ( $roles ) {
		$GLOBALS['wm']['caps'] = $roles[ $role ];
		return Executor::permission( $tool, $input );
	};
	foreach ( Tools::tools() as $tool ) {
		check( true === $ask( 'administrator', $tool ), "Administrador: $tool" );
		check( true === $ask( 'shop_manager', $tool ), "Gerente de tienda: $tool" );
		check( is_wp_error( $ask( 'editor', $tool ) ) && is_wp_error( $ask( 'subscriber', $tool ) ), "Ni un editor ni un suscriptor: $tool" );
		check( is_wp_error( $ask( 'editor', $tool, array( 'action' => 'list' ) ) ), "Un editor no lista nada: $tool" );
	}
	check( is_wp_error( $ask( 'editor', 'catalog', array( 'action' => 'list', 'resource' => 'review' ) ) ), 'Las reseñas (que WooCommerce deja leer a quien modera comentarios) tampoco para un editor.' );
	check( true === $ask( 'shop_manager', 'products', array( 'action' => 'create', 'data' => array( 'name' => 'x' ) ) ), 'Gerente: crear productos (publish_products).' );
	$denied = $ask( 'shop_manager', 'customers', array( 'action' => 'delete', 'customer_id' => 3, 'force' => true ) );
	check( is_wp_error( $denied ) && false !== strpos( $denied->get_error_message(), 'delete_users' ) && 403 === $denied->get_error_data()['status'], 'Gerente: borrar clientes exige delete_users, como en WooCommerce.' );
	check( true === $ask( 'administrator', 'customers', array( 'action' => 'delete', 'customer_id' => 3, 'force' => true ) ), 'Administrador: borrar clientes.' );
	$GLOBALS['wm']['caps'] = array( 'edit_products' );
	check( is_wp_error( Executor::permission( 'products', array( 'action' => 'list' ) ) ), 'Listar productos exige read_private_products (además del mínimo).' );
	check( 'read_private_products' === Tools::capability( 'products', 'product', 'list' ) && 'edit_others_products' === Tools::capability( 'products', 'product', 'batch' ) && 'publish_shop_orders' === Tools::capability( 'orders', 'refund', 'create' ), 'Capacidades de wc_rest_check_post_permissions().' );
	check( 'manage_product_terms' === Tools::capability( 'catalog', 'category', 'list' ) && 'delete_product_terms' === Tools::capability( 'catalog', 'attribute_term', 'delete' ) && 'manage_product_terms' === Tools::capability( 'catalog', 'attribute', 'update' ), 'Capacidades de términos y atributos.' );
	check( 'list_users' === Tools::capability( 'customers', 'download', 'list' ) && 'manage_woocommerce' === Tools::capability( 'store-settings', 'payment_gateway', 'update' ) && 'view_woocommerce_reports' === Tools::capability( 'reports', 'report', 'sales' ) && 'manage_woocommerce' === Tools::capability( 'reports', 'report', 'log' ), 'Capacidades de clientes, ajustes e informes.' );
	$GLOBALS['wm']['caps'] = array( 'view_woocommerce_reports' );
	check( is_wp_error( Executor::permission( 'reports', array( 'action' => 'log' ) ) ) && true === Executor::permission( 'reports', array( 'action' => 'sales' ) ), 'La bitácora exige administrar WooCommerce; los informes, verlos.' );

	// =================================================================================================================
	// 8. Registro en el contrato de abilities del núcleo.
	$registry                                 = new \Elementor\Modules\Mcp\Registry\Ability_Registry();
	$core                                     = new \Elementor\Modules\Mcp\Module();
	$core->test_registry                      = $registry;
	$manager                                  = new class() {
		public $mcp;
		public function get_modules( $name ) {
			return 'mcp' === $name ? $this->mcp : null;
		}
	};
	$manager->mcp                             = $core;
	\Elementor\Plugin::$instance              = new stdClass();
	\Elementor\Plugin::$instance->modules_manager = $manager;

	wm_reset( array( 'admin' => true ) );
	Woo::load( dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-woo', array( 'woocommerce' => false ) );
	check( ! isset( $GLOBALS['wm']['hooks']['elementor/init'] ), 'Sin WooCommerce: el submódulo no arranca.' );
	check( isset( $GLOBALS['wm']['hooks'][ 'admin_post_' . Settings_Section::ACTION_SAVE ], $GLOBALS['wm']['hooks'][ 'admin_post_' . Settings_Section::ACTION_SAVE_NETWORK ] ), 'Sin WooCommerce, los guardados del modo se enganchan igual (la red lo define aunque su sitio no tenga tienda).' );
	// El módulo real con WooCommerce arrancaría la base y los demás submódulos: aquí sólo cuenta el MCP.
	$env = new ReflectionProperty( Woo::class, 'env' );
	if ( PHP_VERSION_ID < 80100 ) {
		$env->setAccessible( true );
	}
	foreach ( array( '9.9.0' => false, '11.2.1' => true ) as $version => $hooked ) {
		Mcp::reset();
		wm_reset();
		$env->setValue( null, array( 'woocommerce' => true, 'wc_version' => $version ) );
		Mcp::init();
		check( $hooked === isset( $GLOBALS['wm']['hooks']['elementor/init'] ), "WooCommerce $version: " . ( $hooked ? 'el submódulo espera a elementor/init.' : 'anterior a MIN_WC, nada.' ) );
	}
	Mcp::boot();
	check( 7 === count( $registry->abilities ), 'Con el registro del núcleo: las siete herramientas en él (' . count( $registry->abilities ) . ').' );
	foreach ( $registry->abilities as $id => $ability ) {
		check( 0 === strpos( $id, 'digitalisimo-woo/' ) && false === $ability->is_exposed_via_proxy() && $ability->is_exposed_on_server(), "En el servidor, fuera del proxy del editor: $id" );
	}
	check( ! isset( $GLOBALS['wm']['hooks']['elementor/mcp/server/tools'] ), 'Con registro, sin el filtro de respaldo.' );
	do_action( 'wp_abilities_api_categories_init' );
	check( isset( $GLOBALS['wm_categories']['digitalisimo-woo'] ), 'Categoría propia registrada (MCP activo).' );
	$products = $registry->abilities['digitalisimo-woo/products'];
	$products->register();
	$args = $GLOBALS['wm_registered']['digitalisimo-woo/products'];
	check( false === $args['meta']['show_in_rest'] && false === $args['meta']['mcp']['public'] && 'digitalisimo-woo' === $args['category'], 'register(): fuera de la REST de abilities y del servidor genérico.' );
	check( array( $products, 'execute_guarded' ) === $args['execute_callback'], 'Se ejecuta por execute_guarded() del núcleo.' );
	$GLOBALS['wm']['caps'] = $roles['editor'];
	check( is_wp_error( call_user_func( $args['permission_callback'], array( 'action' => 'list' ) ) ), 'Permiso de la ability: un editor no.' );
	review( 'R1', false === $products->check_permission(), 'check_permission() del núcleo (que hace (bool) del callback) deja pasar a un editor.' );
	$GLOBALS['wm']['caps'] = $roles['shop_manager'];
	check( true === $products->check_permission(), 'Permiso del núcleo: un gerente sí.' );
	$GLOBALS['wm_rest'] = array();
	$result             = call_user_func( $args['execute_callback'], array( 'action' => 'get', 'product_id' => 5 ) );
	check( ! is_wp_error( $result ) && array( 'digitalisimo-woo/products' ) === $GLOBALS['wm_guarded'] && array() === $GLOBALS['wm_post_marked'], 'La ejecución pasa por la guarda del núcleo sin marcar ningún documento del editor.' );
	check( array( array( 'GET', '/wc/v3/products/5', array(), null ) ) === $GLOBALS['wm_rest'], 'Por rest_do_request(): GET /wc/v3/products/5, sin cuerpo.' );
	$GLOBALS['wm_rest_responses']['GET /wc/v3/products/6'] = array( 404, array( 'code' => 'woocommerce_rest_product_invalid_id', 'message' => 'ID no válido.', 'data' => array( 'status' => 404 ) ), array() );
	$result = call_user_func( $args['execute_callback'], array( 'action' => 'get', 'product_id' => 6 ) );
	check( is_wp_error( $result ) && 'woocommerce_rest_product_invalid_id' === $result->get_error_code() && 404 === $result->get_error_data()['status'] && 'WooCommerce: ID no válido.' === $result->get_error_message(), 'Un error de la REST vuelve con su código, su estado y su mensaje.' );
	update_option( Settings::OPTION, array( 'mode' => 'write', 'own' => array( 'mode' ) ) );
	$GLOBALS['wm']['caps'] = $roles['shop_manager'];
	$GLOBALS['wm_rest']    = array();
	call_user_func( $args['execute_callback'], array( 'action' => 'update', 'product_id' => 5, 'data' => array( 'stock_quantity' => 3, 'id' => 9 ) ) );
	check( array( array( 'PUT', '/wc/v3/products/5', array(), array( 'stock_quantity' => 3 ) ) ) === $GLOBALS['wm_rest'], 'Por rest_do_request(): PUT con el cuerpo JSON sin el id de la ruta.' );

	// Modo off: nada; sin la Abilities API, nada; sin registro del núcleo, respaldo por filtro.
	$registry->abilities = array();
	Mcp::reset();
	wm_reset( array( 'options' => array( 'elementor_mcp_enabled' => true, Settings::OPTION => array( 'mode' => 'off', 'own' => array( 'mode' ) ) ) ) );
	Mcp::boot();
	check( array() === $registry->abilities && array() === Mcp::abilities() && ! isset( $GLOBALS['wm']['hooks']['wp_abilities_api_categories_init'] ), 'Modo off: ninguna herramienta ni categoría.' );
	wm_reset();
	$core->test_registry = null;
	Mcp::boot();
	check( array() === $registry->abilities && isset( $GLOBALS['wm']['hooks']['wp_abilities_api_init'] ), 'Sin registro del núcleo: respaldo en wp_abilities_api_init.' );
	$tools = apply_filters( 'elementor/mcp/server/tools', array( 'elementor/otra' ) );
	check( array_merge( array( 'elementor/otra' ), array_map( array( Tools::class, 'id' ), Tools::tools() ) ) === $tools, 'Respaldo: los siete IDs en el filtro de herramientas del servidor.' );
	do_action( 'wp_abilities_api_init' );
	check( 7 === count( $GLOBALS['wm_registered'] ), 'Respaldo con el MCP activo: siete abilities registradas.' );
	wm_reset( array( 'options' => array() ) );
	Mcp::boot();
	do_action( 'wp_abilities_api_init' );
	do_action( 'wp_abilities_api_categories_init' );
	check( array() === $GLOBALS['wm_registered'] && array() === $GLOBALS['wm_categories'], 'Con el MCP del sitio apagado: nada registrado.' );

	// =================================================================================================================
	// 9. Revisión adversarial (REVISION.md): cada hallazgo con su etiqueta; todas fallaban con a43c428f.
	$admin_caps = $roles['administrator'];
	$write_mode = array( 'elementor_mcp_enabled' => true, Settings::OPTION => array( 'mode' => 'write', 'own' => array( 'mode' ) ) );

	// R1 · run() comprueba el permiso por sí mismo (no sólo WP_Ability), y check_permission() del núcleo no deja pasar.
	review_block(
		'R1',
		static function () use ( $roles, $write_mode ) {
			wm_reset( array( 'caps' => $roles['editor'], 'options' => $write_mode ) );
			$result = Executor::run( 'customers', array( 'action' => 'delete', 'customer_id' => 3, 'force' => true ), 'wm_dispatch' );
			review( 'R1', is_wp_error( $result ) && 'digitalisimo_woo_mcp_forbidden' === $result->get_error_code(), 'run() como editor debe rechazar sin llamar a WooCommerce.' );
			review( 'R1', array() === $GLOBALS['wm_rest'], 'run() como editor llegó a WooCommerce: ' . json_encode( $GLOBALS['wm_rest'] ) );
			review( 'R1', isset( Log::entries()[0] ) && 'denied' === Log::entries()[0]['result'] && 'forbidden' === Log::entries()[0]['code'], 'La escritura sin permiso queda como «denied» (forbidden) en la bitácora.' );
			$GLOBALS['wm']['caps'] = $roles['shop_manager'];
			$result = Executor::run( 'catalog', array( 'action' => 'list', 'resource' => 'review' ), 'wm_dispatch' );
			review( 'R1', ! is_wp_error( $result ), 'Un gerente sí lista reseñas por run().' );
			foreach ( array( 'editor', 'subscriber' ) as $role ) {
				$GLOBALS['wm']['caps'] = $roles[ $role ];
				foreach ( Tools::tools() as $tool ) {
					review( 'R1', false === ( new \Digitalisimo\Elements\Woo\Mcp\Ability( $tool ) )->check_permission(), "check_permission() del núcleo: $role no en $tool." );
				}
			}
		}
	);

	if ( ! empty( $GLOBALS['wm_review'] ) ) {
		$lines = array();
		foreach ( $GLOBALS['wm_review'] as $label => $messages ) {
			$lines[] = $label . ' (' . count( $messages ) . '): ' . $messages[0];
		}
		throw new RuntimeException( 'Hallazgos de la revisión sin corregir: ' . implode( ' | ', $lines ) );
	}

	echo "WooCommerce en el MCP de Elements: 7 herramientas, " . count( $cases ) . " rutas, " . count( $bad ) . " rechazos y $fuzz entradas de fuzz sin salir de /wc/v3/; modos, secretos, bitácora y permisos en verde; revisión R1–R1 con {$GLOBALS['wm_review_count']} comprobaciones.\n";
}
