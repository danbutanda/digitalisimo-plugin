<?php
/**
 * `digitalisimo-woo/skydropx` en el MCP de DIGITALÍSIMO Elements (`modules/digitalisimo-woo/mcp/class-skydropx*.php`):
 * registro sólo con el plugin de Skydropx cargado (y sin cargar nada sin él), definición cerrada, permisos por acción,
 * modos off/read/write con la bitácora, cada acción (status, audit_products, update_products, quote, shipments,
 * get_shipment, set_tracking, label con su redirección y resync), unidades, el lote con tope, las metas que lee el
 * plugin, ningún secreto en respuestas ni bitácora y, en un subproceso (`--compat`), un plugin de otra versión al que
 * le faltan piezas.
 *
 * Sin WordPress, WooCommerce ni el plugin: dobles mínimos con la API pública de WooCommerce 11.2.1 y de Skydropx 2.1.5
 * (`Skydropx_Repository`, `Skydropx_Quotation_Settings`, `Skydropx_Order_Label_Service` con sus metas en constantes
 * privadas, `Skydropx_Order_Label_UI` y `Skydropx_Shipping_Method`, que cotiza leyendo `WC()->cart` y guarda su caché en
 * `WC()->session` como el original). La REST de WooCommerce es un despachador que anota cada petición. El ensayo con el
 * plugin real es `tests/playground/skydropx-mcp.sh`.
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
	abstract class Abstract_Ability {
		private $cached = null;
		abstract protected function get_ability_id(): string;
		abstract protected function get_definition(): Ability_Definition;
		abstract public function execute( $input = array() );
		final public function execute_guarded( $input = array() ) {
			return $this->execute( $input );
		}
		public function check_permission(): bool {
			return (bool) call_user_func( $this->definition()->permission_callback );
		}
		public function register(): void {}
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
		public function test_definition(): Ability_Definition {
			return $this->definition();
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
			return ! empty( $GLOBALS['sk']['options']['elementor_mcp_enabled'] );
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
	const SK_COMPAT = 'compat';
	$sk_mode = in_array( '--compat', $argv, true ) ? SK_COMPAT : 'main';

	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message . ' en ' . basename( $file ) . ':' . $line, 0, $severity, $file, $line );
		}
	);
	$GLOBALS['sk_checks'] = 0;
	function check( $ok, string $message ) {
		++$GLOBALS['sk_checks'];
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// WordPress en memoria.
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
	class Sk_Exit extends RuntimeException {
		public $kind;
		public $value;
		public function __construct( string $kind, $value, int $status = 0 ) {
			parent::__construct( $kind . ': ' . ( is_string( $value ) ? $value : '' ), $status );
			$this->kind  = $kind;
			$this->value = $value;
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
	function sk_reset( array $over = array() ): void {
		$GLOBALS['sk'] = array_merge(
			array(
				'options'    => array(
					'elementor_mcp_enabled'      => true,
					'woocommerce_weight_unit'    => 'kg',
					'woocommerce_dimension_unit' => 'cm',
					'woocommerce_default_country' => 'MX:JAL',
					'woocommerce_store_address'  => 'Av. Vallarta 100',
					'woocommerce_store_city'     => 'Guadalajara',
					'woocommerce_store_postcode' => '44100',
					'permalink_structure'        => '/%postname%/',
					'woocommerce_tax_display_cart' => 'excl',
				),
				'transients' => array(),
				'site'       => array(),
				'multisite'  => false,
				'caps'       => array(),
				'user'       => 7,
				'logged_in'  => true,
				'hooks'      => array(),
				'actions'    => array(),
				'admin'      => false,
			),
			$over
		);
		$GLOBALS['sk_rest']      = array();
		$GLOBALS['sk_responses'] = array();
	}
	sk_reset();
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['sk']['hooks'][ $hook ][] = $callback;
	}
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['sk']['hooks'][ $hook ][] = $callback;
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['sk']['hooks'][ $hook ] ?? array() as $callback ) {
			$value = call_user_func( $callback, $value, ...$args );
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['sk']['actions'][ $hook ] = ( $GLOBALS['sk']['actions'][ $hook ] ?? 0 ) + 1;
		foreach ( $GLOBALS['sk']['hooks'][ $hook ] ?? array() as $callback ) {
			call_user_func_array( $callback, $args );
		}
	}
	function did_action( $hook ) {
		return $GLOBALS['sk']['actions'][ $hook ] ?? 0;
	}
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['sk']['options'] ) ? $GLOBALS['sk']['options'][ $name ] : $default;
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['sk']['options'][ $name ] = $value;
		return true;
	}
	function add_option( $name, $value, $deprecated = '', $autoload = true ) {
		$GLOBALS['sk']['options'][ $name ] = $value;
		return true;
	}
	function get_site_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['sk']['site'] ) ? $GLOBALS['sk']['site'][ $name ] : $default;
	}
	function get_transient( $name ) {
		return $GLOBALS['sk']['transients'][ $name ] ?? false;
	}
	function set_transient( $name, $value, $ttl = 0 ) {
		$GLOBALS['sk']['transients'][ $name ] = $value;
		$GLOBALS['sk']['transient_ttl'][ $name ] = $ttl;
		return true;
	}
	function is_multisite() {
		return $GLOBALS['sk']['multisite'];
	}
	function get_current_blog_id() {
		return 1;
	}
	function is_admin() {
		return $GLOBALS['sk']['admin'];
	}
	function get_current_user_id() {
		return $GLOBALS['sk']['user'];
	}
	function is_user_logged_in() {
		return $GLOBALS['sk']['logged_in'];
	}
	function current_user_can( $cap ) {
		return in_array( $cap, $GLOBALS['sk']['caps'], true );
	}
	function admin_url( $path = '' ) {
		return 'https://tienda.test/wp-admin/' . $path;
	}
	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) {
			list( $params, $url ) = $args;
		} else {
			$params = array( $args[0] => $args[1] );
			$url    = $args[2];
		}
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $params );
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return esc_html( $text );
	}
	function esc_url( $url ) {
		return (string) $url;
	}
	function esc_url_raw( $url ) {
		return (string) $url;
	}
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
	function sanitize_text_field( $text ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
	}
	function sanitize_textarea_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
	function wp_unslash( $value ) {
		return $value;
	}
	function absint( $value ) {
		return abs( (int) $value );
	}
	function maybe_serialize( $value ) {
		return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
	}
	function maybe_unserialize( $value ) {
		return is_string( $value ) && 0 === strpos( $value, 'a:' ) ? unserialize( $value ) : $value;
	}
	function wp_cache_delete( $key, $group = '' ) {
		$GLOBALS['sk']['cache_deleted'][] = $group . ':' . $key;
		return true;
	}
	function wp_using_ext_object_cache() {
		return ! empty( $GLOBALS['sk']['ext_cache'] );
	}
	function wp_cache_supports( $feature ) {
		return 'flush_runtime' === $feature;
	}
	function wp_cache_flush_runtime() {
		$GLOBALS['sk']['flushes'] = ( $GLOBALS['sk']['flushes'] ?? 0 ) + 1;
		return true;
	}
	function _prime_post_caches( $ids, $terms = true, $meta = true ) {
		$GLOBALS['sk']['primed'][] = array( $ids, $terms, $meta );
	}
	function wp_get_post_parent_id( $id ) {
		return isset( $GLOBALS['sk_products'][ $id ] ) ? $GLOBALS['sk_products'][ $id ]->parent : 0;
	}
	function delete_transient( $name ) {
		unset( $GLOBALS['sk']['transients'][ $name ] );
		return true;
	}
	/** Clases de envío: slug → nombre (`get_term_by()` por slug, o por nombre sin distinguir mayúsculas, como MySQL). */
	function get_term_by( $field, $value, $taxonomy ) {
		foreach ( $GLOBALS['sk_classes'] ?? array() as $slug => $name ) {
			if ( ( 'slug' === $field && $slug === $value ) || ( 'name' === $field && 0 === strcasecmp( $name, $value ) ) ) {
				return (object) array( 'slug' => $slug, 'name' => $name );
			}
		}
		return false;
	}
	function wp_hash( $data, $scheme = 'auth' ) {
		return hash_hmac( 'md5', $data, 'sal-de-prueba-' . $scheme );
	}
	function wc_shipping_enabled() {
		return empty( $GLOBALS['sk']['shipping_off'] );
	}
	function wc_get_order_statuses() {
		return array( 'wc-pending' => 'Pendiente', 'wc-processing' => 'Procesando', 'wc-on-hold' => 'En espera', 'wc-completed' => 'Completado', 'wc-cancelled' => 'Cancelado' );
	}
	function wc_get_order_notes( $args ) {
		$note = $GLOBALS['sk_notes'][ $args['order_id'] ] ?? null;
		return null === $note ? array() : array( (object) array( 'content' => $note ) );
	}
	/** Como WooCommerce: la meta-capacidad del pedido (uno ajeno exige edit_others_shop_orders) y su filtro. */
	function wc_rest_check_post_permissions( $post_type, $context = 'read', $object_id = 0 ) {
		$own        = in_array( (int) $object_id, $GLOBALS['sk']['own_orders'] ?? array(), true );
		$permission = 'edit' === $context
			? current_user_can( 'edit_others_shop_orders' ) || ( $own && current_user_can( 'edit_shop_orders' ) )
			: current_user_can( 'read_private_shop_orders' ) || $own;
		$GLOBALS['sk']['order_checks'][] = array( $context, (int) $object_id );
		return apply_filters( 'woocommerce_rest_check_permissions', $permission, $context, $object_id, $post_type );
	}
	function get_post_status( $id ) {
		$product = $GLOBALS['sk_products'][ (int) $id ] ?? null;
		return $product ? $product->status : false;
	}
	/** `get_posts()` sobre el catálogo de prueba: tipo, estado, `product_type` (IN/NOT IN) y padres. */
	function get_posts( $args ) {
		$GLOBALS['sk']['get_posts'][] = $args;
		$out                          = array();
		foreach ( $GLOBALS['sk_products'] ?? array() as $id => $product ) {
			$type = 'variation' === $product->type ? 'product_variation' : 'product';
			if ( $type !== $args['post_type'] || $product->status !== $args['post_status'] ) {
				continue;
			}
			if ( isset( $args['tax_query'][0] ) ) {
				$in = in_array( $product->type, $args['tax_query'][0]['terms'], true );
				if ( ( 'IN' === $args['tax_query'][0]['operator'] ) !== $in ) {
					continue;
				}
			}
			if ( isset( $args['post_parent__in'] ) && ! in_array( $product->parent, $args['post_parent__in'], true ) ) {
				continue;
			}
			$out[] = $id;
		}
		sort( $out );
		return -1 === $args['posts_per_page'] ? $out : array_slice( $out, 0, $args['posts_per_page'] );
	}
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new Sk_Exit( 'die', (string) $message, (int) ( $args['response'] ?? 500 ) );
	}
	function wp_safe_redirect( $url ) {
		throw new Sk_Exit( 'redirect', (string) $url );
	}
	function auth_redirect() {
		throw new Sk_Exit( 'login', 'auth' );
	}
	function wp_register_ability( $id, $args ) {
		$GLOBALS['sk']['registered'][ $id ] = $args;
	}
	function wp_register_ability_category( $id, $args ) {}
	function wp_nonce_url( $url, $action ) {
		// Como WordPress: el nonce depende del usuario (y de su sesión) y la URL sale escapada para HTML.
		return esc_html( add_query_arg( '_wpnonce', substr( md5( $action . '|' . get_current_user_id() ), 0, 10 ), $url ) );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// WooCommerce 11.2.1 (lo que usa la herramienta).
	function wc_get_weight( $weight, $to_unit, $from_unit = '' ) {
		$weight    = (float) $weight;
		$from_unit = '' !== $from_unit ? $from_unit : get_option( 'woocommerce_weight_unit' );
		$to_kg     = array( 'kg' => 1, 'g' => 0.001, 'lbs' => 0.453592, 'oz' => 0.0283495 );
		$from_kg   = array( 'kg' => 1, 'g' => 1000, 'lbs' => 2.20462, 'oz' => 35.274 );
		return $from_unit === $to_unit ? $weight : $weight * $to_kg[ $from_unit ] * $from_kg[ $to_unit ];
	}
	function wc_get_dimension( $dimension, $to_unit, $from_unit = '' ) {
		$dimension = (float) $dimension;
		$from_unit = '' !== $from_unit ? $from_unit : get_option( 'woocommerce_dimension_unit' );
		$to_cm     = array( 'cm' => 1, 'm' => 100, 'mm' => 0.1, 'in' => 2.54, 'yd' => 91.44 );
		$from_cm   = array( 'cm' => 1, 'm' => 0.01, 'mm' => 10, 'in' => 0.3937, 'yd' => 0.010936133 );
		return $from_unit === $to_unit ? $dimension : $dimension * $to_cm[ $from_unit ] * $from_cm[ $to_unit ];
	}
	function wc_get_price_excluding_tax( $product, $args = array() ) {
		return (float) $product->price * ( $args['qty'] ?? 1 );
	}
	function wc_get_price_including_tax( $product, $args = array() ) {
		return round( (float) $product->price * ( $args['qty'] ?? 1 ) * 1.16, 2 );
	}
	class WC_Product {
		public $id;
		public $type;
		public $status   = 'publish';
		public $parent   = 0;
		public $name     = '';
		public $sku      = '';
		public $price    = 100;
		public $virtual  = false;
		public $download = false;
		public $props    = array( 'weight' => '', 'length' => '', 'width' => '', 'height' => '', 'shipping_class_id' => 0 );
		public function __construct( int $id, string $type, array $props = array(), int $parent = 0 ) {
			$this->id     = $id;
			$this->type   = $type;
			$this->parent = $parent;
			$this->name   = 'Producto ' . $id;
			$this->sku    = 'SKU-' . $id;
			$this->props  = array_merge( $this->props, $props );
		}
		private function prop( string $key ) {
			$value = $this->props[ $key ];
			if ( ( '' === $value || 0 === $value ) && $this->parent && isset( $GLOBALS['sk_products'][ $this->parent ] ) ) {
				return $GLOBALS['sk_products'][ $this->parent ]->prop( $key ); // Como WC_Product_Variation en «view».
			}
			return $value;
		}
		public function get_id() {
			return $this->id;
		}
		public function get_type() {
			return $this->type;
		}
		public function is_type( $type ) {
			return $type === $this->type;
		}
		public function get_parent_id() {
			return $this->parent;
		}
		public function get_name() {
			return $this->name;
		}
		public function get_sku() {
			return $this->sku;
		}
		public function get_weight() {
			return $this->prop( 'weight' );
		}
		public function get_length() {
			return $this->prop( 'length' );
		}
		public function get_width() {
			return $this->prop( 'width' );
		}
		public function get_height() {
			return $this->prop( 'height' );
		}
		public function get_shipping_class_id() {
			return (int) $this->prop( 'shipping_class_id' );
		}
		public function get_shipping_class() {
			return $this->get_shipping_class_id() ? 'clase-' . $this->get_shipping_class_id() : '';
		}
		public function is_virtual() {
			return $this->virtual;
		}
		public function is_downloadable() {
			return $this->download;
		}
	}
	function wc_get_product( $id ) {
		return $GLOBALS['sk_products'][ (int) $id ] ?? false;
	}
	class WC_DateTime extends DateTime {
		public function date( $format ) {
			return $this->format( $format );
		}
	}
	class WC_Order_Item_Shipping {
		public $id    = 0;
		public $meta  = array();
		public $title = '';
		public $total = 0;
		public function __construct( int $id = 0, array $meta = array() ) {
			$this->id   = $id;
			$this->meta = $meta;
		}
		public function get_id() {
			return $this->id;
		}
		public function get_meta( $key, $single = true ) {
			return $this->meta[ $key ] ?? '';
		}
		public function update_meta_data( $key, $value ) {
			$this->meta[ $key ] = $value;
		}
		public function set_method_title( $title ) {
			$this->title = $title;
		}
		public $method_id = '';
		public function set_method_id( $id ) {
			$this->method_id = $id;
		}
		public function set_total( $total ) {
			$this->total = $total;
		}
	}
	class WC_Order {
		public $id;
		public $status   = 'processing';
		public $lines    = array();
		public $saves    = 0;
		public $modified = null;
		public $type     = 'shop_order';
		public function __construct( int $id, array $lines = array() ) {
			$this->id = $id;
			foreach ( $lines as $line ) {
				$this->lines[ $line->id ] = $line;
			}
		}
		public function get_id() {
			return $this->id;
		}
		public function get_type() {
			return $this->type;
		}
		public function get_status() {
			return $this->status;
		}
		public function get_items( $type ) {
			return 'shipping' === $type ? $this->lines : array();
		}
		public function add_item( $item ) {
			$item->id                  = 900 + count( $this->lines );
			$this->lines[ $item->id ] = $item;
		}
		public function set_date_modified( $time ) {
			$this->modified = new WC_DateTime( '@' . $time );
		}
		public function get_date_modified() {
			return $this->modified;
		}
		public function get_edit_order_url() {
			return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $this->id );
		}
		public function save() {
			++$this->saves;
			$GLOBALS['sk']['saved'][ $this->id ] = array_map(
				static function ( $line ) {
					return $line->meta;
				},
				$this->lines
			);
			do_action( 'woocommerce_update_order', $this->id, $this );
			return $this->id;
		}
	}
	function wc_get_order( $id ) {
		return $GLOBALS['sk_orders'][ (int) $id ] ?? false;
	}
	/** Como WC_Cart: su constructor engancha la sesión y el carrito persistente; `get_cart()` lo carga si no se cargó. */
	class WC_Cart {
		public $cart_contents = array();
		protected $session;
		protected $totals         = array();
		protected $default_totals = array( 'cart_contents_total' => 0, 'cart_contents_tax' => 0 );
		public function __construct() {
			$GLOBALS['sk']['wc_cart_constructed'] = ( $GLOBALS['sk']['wc_cart_constructed'] ?? 0 ) + 1;
		}
		public function get_cart() {
			if ( ! did_action( 'woocommerce_load_cart_from_session' ) ) {
				$this->session->get_cart_from_session();
			}
			return array_filter( $this->get_cart_contents() );
		}
		public function get_cart_contents() {
			return apply_filters( 'woocommerce_get_cart_contents', (array) $this->cart_contents );
		}
		public function get_cart_contents_total() {
			return $this->totals['cart_contents_total'] ?? $this->default_totals['cart_contents_total'];
		}
		public function get_cart_contents_tax() {
			return $this->totals['cart_contents_tax'] ?? $this->default_totals['cart_contents_tax'];
		}
		public function set_cart_contents_total( $value ) {
			$this->totals['cart_contents_total'] = (string) $value;
		}
		public function set_cart_contents_tax( $value ) {
			$this->totals['cart_contents_tax'] = $value;
		}
		public function display_prices_including_tax() {
			return 'incl' === get_option( 'woocommerce_tax_display_cart' );
		}
	}
	abstract class WC_Session {
		protected $_customer_id;
		protected $_data  = array();
		protected $_dirty = false;
		public function get( $key, $default_value = null ) {
			$key = sanitize_key( $key );
			return isset( $this->_data[ $key ] ) ? maybe_unserialize( $this->_data[ $key ] ) : $default_value;
		}
		public function set( $key, $value ) {
			$key = sanitize_key( $key );
			if ( ( $this->_data[ $key ] ?? null ) === maybe_serialize( $value ) ) {
				return;
			}
			$this->_dirty        = true;
			$this->_data[ $key ] = maybe_serialize( $value );
		}
	}
	class WC_Shipping_Rate {
		public $args;
		public function __construct( array $args ) {
			$this->args = $args;
		}
		public function get_id() {
			return $this->args['id'];
		}
		public function get_label() {
			return $this->args['label'];
		}
		public function get_cost() {
			return $this->args['cost'];
		}
		public function get_meta_data() {
			return $this->args['meta_data'];
		}
	}
	abstract class WC_Shipping_Method {
		public $id          = '';
		public $instance_id = 0;
		public $enabled     = 'yes';
		public $title       = '';
		public $rates       = array();
		public function __construct( $instance_id = 0 ) {
			$this->instance_id = (int) $instance_id;
		}
		public function is_enabled() {
			return 'yes' === $this->enabled;
		}
		public function get_instance_id() {
			return $this->instance_id;
		}
		public function get_title() {
			return $this->title;
		}
		public function get_rate_id( $suffix = '' ) {
			return $this->id . ':' . $this->instance_id . ( $suffix ? ':' . $suffix : '' );
		}
		public function add_rate( $args = array() ) {
			$this->rates[ $args['id'] ] = new WC_Shipping_Rate( $args );
		}
	}
	class Sk_Flat_Rate extends WC_Shipping_Method {
		public $id = 'flat_rate';
	}
	class WC_Shipping_Zone {
		public $id;
		public $name;
		public $methods;
		public $locations;
		public function __construct( $id = 0, string $name = 'Resto del mundo', array $methods = array(), array $locations = array() ) {
			$this->id        = (int) $id;
			$this->name      = $name;
			$this->methods   = $methods;
			$this->locations = $locations;
			if ( 0 === $this->id && isset( $GLOBALS['sk_zone0'] ) ) {
				$this->methods = $GLOBALS['sk_zone0'];
			}
		}
		public function get_id() {
			return $this->id;
		}
		public function get_zone_name() {
			return $this->name;
		}
		public function get_shipping_methods( $enabled_only = false, $context = 'view' ) {
			return $enabled_only ? array_filter( $this->methods, static function ( $m ) {
				return $m->is_enabled();
			} ) : $this->methods;
		}
	}
	class WC_Shipping_Zones {
		public static function get_zones( $context = 'admin' ) {
			$out = array();
			foreach ( $GLOBALS['sk_zones'] ?? array() as $zone ) {
				$out[ $zone->id ] = array(
					'zone_id'          => $zone->id,
					'zone_name'        => $zone->name,
					'zone_locations'   => $zone->locations,
					'shipping_methods' => $zone->methods,
				);
			}
			return $out;
		}
		public static function get_zone_matching_package( $package ) {
			$GLOBALS['sk']['matched'][] = $package['destination'];
			foreach ( $GLOBALS['sk_zones'] ?? array() as $zone ) {
				foreach ( $zone->locations as $location ) {
					if ( $location->code === $package['destination']['country'] ) {
						return $zone;
					}
				}
			}
			return new WC_Shipping_Zone( 0 );
		}
		public static function get_shipping_method( $instance_id ) {
			foreach ( $GLOBALS['sk_zones'] ?? array() as $zone ) {
				foreach ( $zone->methods as $method ) {
					if ( $method->instance_id === (int) $instance_id ) {
						return $method;
					}
				}
			}
			return false;
		}
	}
	class Sk_WooCommerce {
		public $cart    = null;
		public $session = null;
		public function shipping() {
			// Como WC_Shipping::init(): `woocommerce_shipping_init` carga la clase del método del plugin.
			if ( isset( $GLOBALS['sk_shipping_init'] ) ) {
				call_user_func( $GLOBALS['sk_shipping_init'] );
			}
			do_action( 'woocommerce_shipping_init' );
			return $this;
		}
	}
	function WC() {
		static $wc = null;
		$wc = $wc ?: new Sk_WooCommerce();
		return $wc;
	}
	/** Como el real: un envoltorio que reenvía cada método al almacén con `__call` (method_exists() da falso). */
	class WC_Data_Store {
		private $instance;
		public static function load( $type ) {
			$store           = new self();
			$store->instance = new class() {
				public function get_webhooks_ids( $status = '' ) {
					return array_keys( $GLOBALS['sk_webhooks'] ?? array() );
				}
			};
			return $store;
		}
		public function __call( $method, $args ) {
			return call_user_func_array( array( $this->instance, $method ), $args );
		}
	}
	class Sk_Webhook {
		public $url;
		public $status;
		public $topic;
		public $secret;
		public function __construct( $url, $status, $topic, $secret ) {
			$this->url    = $url;
			$this->status = $status;
			$this->topic  = $topic;
			$this->secret = $secret;
		}
		public function get_delivery_url() {
			return $this->url;
		}
		public function get_status() {
			return $this->status;
		}
		public function get_topic() {
			return $this->topic;
		}
		public function get_secret() {
			return $this->secret;
		}
	}
	function wc_get_webhook( $id ) {
		return $GLOBALS['sk_webhooks'][ $id ] ?? false;
	}

	/** La REST de WooCommerce: anota y responde lo que el caso preparó (`$GLOBALS['sk_responses']`). */
	function sk_dispatch( $method, $route, $query, $body ) {
		$GLOBALS['sk_rest'][] = array( $method, $route, $query, $body );
		$key                  = $method . ' ' . $route;
		if ( isset( $GLOBALS['sk_responses'][ $key ] ) ) {
			$answer = $GLOBALS['sk_responses'][ $key ];
			return is_callable( $answer ) ? $answer( $query, $body ) : $answer;
		}
		return array( 200, array( 'id' => 1 ), array() );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Elements: el módulo Woo con su autocarga.
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-woo/class-module.php';
	use Digitalisimo\Elements\Woo\Module as Woo;
	use Digitalisimo\Elements\Woo\Mcp\Tools;
	use Digitalisimo\Elements\Woo\Mcp\Executor;
	use Digitalisimo\Elements\Woo\Mcp\Log;
	use Digitalisimo\Elements\Woo\Mcp\Settings;
	use Digitalisimo\Elements\Woo\Mcp\Skydropx;
	use Digitalisimo\Elements\Woo\Mcp\Skydropx_Tool;
	use Digitalisimo\Elements\Woo\Mcp\Module as Mcp;
	Woo::register_autoloader();
	$env = new ReflectionProperty( Woo::class, 'env' );
	if ( PHP_VERSION_ID < 80100 ) {
		$env->setAccessible( true );
	}
	$env->setValue( null, array( 'woocommerce' => true, 'wc_version' => '11.2.1' ) );

	$manager = array( 'edit_products', 'read_private_products', 'edit_others_products', 'edit_shop_orders', 'read_private_shop_orders', 'edit_others_shop_orders', 'publish_shop_orders', 'manage_woocommerce' );
	$write   = array( Settings::OPTION => array( 'mode' => 'write', 'own' => array( 'mode' ) ) );
	$run     = static function ( array $input ) {
		return Executor::run( 'skydropx', $input, 'sk_dispatch' );
	};
	$ok      = static function ( $result, string $what ) {
		check( ! is_wp_error( $result ), $what . ( is_wp_error( $result ) ? ' · ' . $result->get_error_code() . ': ' . $result->get_error_message() : '' ) );
		return $result;
	};
	$err     = static function ( $result, string $code, string $what ) {
		check( is_wp_error( $result ) && $code === $result->get_error_code(), $what . ' · ' . ( is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : 'sin error: ' . json_encode( $result ) ) );
		return $result;
	};
	$boot    = static function () {
		$registry                                     = new \Elementor\Modules\Mcp\Registry\Ability_Registry();
		$core                                         = new \Elementor\Modules\Mcp\Module();
		$core->test_registry                          = $registry;
		$manager                                      = new class() {
			public $mcp;
			public function get_modules( $name ) {
				return $this->mcp;
			}
		};
		$manager->mcp                                 = $core;
		\Elementor\Plugin::$instance                  = new stdClass();
		\Elementor\Plugin::$instance->modules_manager = $manager;
		Mcp::reset();
		Mcp::boot();
		return $registry;
	};

	// =================================================================================================================
	// A. Sin el plugin de Skydropx (y en el subproceso de otra versión, nada de esto).
	if ( 'main' === $sk_mode ) {
		sk_reset();
		$registry = $boot();
		check( array( 'products', 'catalog', 'orders', 'customers', 'coupons', 'store-settings', 'reports' ) === Tools::tools(), 'Sin Skydropx: las siete de siempre.' );
		check( 7 === count( $registry->abilities ) && ! isset( $registry->abilities['digitalisimo-woo/skydropx'] ), 'Sin Skydropx: no se registra.' );
		check( '' === Tools::tool_of( 'digitalisimo-woo/skydropx' ), 'Sin Skydropx: su ID no es una herramienta.' );
		check( ! class_exists( Skydropx_Tool::class, false ) && ! class_exists( Skydropx::class, false ), 'Sin Skydropx no se carga ningún archivo de la herramienta (sin peso en ninguna petición).' );
		// El enlace de la guía sólo engancha su manejador en su propia petición de admin-post.php.
		foreach ( array( array( true, 'heartbeat', false ), array( false, Mcp::SKYDROPX_LABEL_ACTION, false ), array( true, Mcp::SKYDROPX_LABEL_ACTION, true ) ) as $case ) {
			sk_reset( array( 'admin' => $case[0] ) );
			$_GET = array( 'action' => $case[1] );
			Mcp::reset();
			Mcp::init();
			check( $case[2] === isset( $GLOBALS['sk']['hooks'][ 'admin_post_' . Mcp::SKYDROPX_LABEL_ACTION ], $GLOBALS['sk']['hooks'][ 'admin_post_nopriv_' . Mcp::SKYDROPX_LABEL_ACTION ] ), 'Manejador de la guía sólo en admin-post con su acción: ' . json_encode( $case ) );
			check( isset( $GLOBALS['sk']['hooks']['elementor/init'] ), 'Mcp::init() sigue enganchando elementor/init.' );
		}
		check( ! class_exists( Skydropx::class, false ), 'Engancharlo no carga la clase.' );
		$_GET = array();
		sk_reset( array( 'caps' => $manager, 'options' => array_merge( $GLOBALS['sk']['options'], $write ) ) );
		$err( $run( array( 'action' => 'status' ) ), 'digitalisimo_woo_mcp_skydropx_inactive', 'Llamada directa sin el plugin: inactiva, sin romper.' );
	}

	// =================================================================================================================
	// El plugin de Skydropx (dobles con la API pública de 2.1.5, o una versión «3.0.0» sin piezas en el subproceso).
	define( 'SKYDROPX_VERSION', SK_COMPAT === $sk_mode ? '3.0.0' : '2.1.5' );
	define( 'SKYDROPX_ECOMMERCE_URL', 'https://ecommerce.pro.skydropx.com' );
	define( 'SKYDROPX_SHIPPING_METHOD_ID', 'skydropx' );
	define( 'SKYDROPX_SHIPPING_ZONE_NAME', 'Skydropx' );
	const SK_SECRET     = 'cs_SECRETO0123456789abcdef0123456789abcdef';
	const SK_QUOTE_URL  = 'https://cotiza.skydropx.test/v2/rates/tienda-abc?token=TOKENSECRETO98765';
	const SK_HOOK_URL   = 'https://ecommerce.pro.skydropx.com/api/v3/webhooks/woocommerce?key=WEBHOOKSECRETO4242';
	const SK_HOOK_SIGN  = 'whsec_FIRMASECRETA77777';
	eval( 'class Skydropx {}' ); // Después de A: una clase de primer nivel existiría desde el principio.
	$GLOBALS['sk_plugin'] = array( 'linked' => true, 'enabled' => true, 'url' => SK_QUOTE_URL, 'http' => 0, 'rates' => null );
	if ( 'main' === $sk_mode ) {
		eval( <<<'PHP'
namespace Skydropx\Includes;
class Skydropx_Repository {
	public function has_plugin_api_keys(): bool { return $GLOBALS['sk_plugin']['linked']; }
	public function fetch_plugin_api_key(): ?array { return array( 'consumer_secret' => SK_SECRET, 'user_id' => 1 ); }
}
class Skydropx_Quotation_Settings {
	public static function get_enabled(): ?bool { return $GLOBALS['sk_plugin']['enabled']; }
	public static function is_enabled(): bool { return true === $GLOBALS['sk_plugin']['enabled']; }
	public static function get_base_url(): ?string { return $GLOBALS['sk_plugin']['url']; }
}
namespace Skydropx\Admin;
class Skydropx_Order_Label_Service {
	private const META_TRACKING_NUMBER  = 'Tracking Number';
	private const META_TRACKING_COMPANY = 'Tracking Company';
	private const META_TRACKING_URL     = 'Tracking URL';
	public function has_label_tracking_meta( $order ): bool {
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( '' !== trim( (string) $item->get_meta( self::META_TRACKING_NUMBER ) ) && '' !== trim( (string) $item->get_meta( self::META_TRACKING_COMPANY ) ) && '' !== trim( (string) $item->get_meta( self::META_TRACKING_URL ) ) ) {
				return true;
			}
		}
		return false;
	}
}
class Skydropx_Order_Label_UI {
	public const PRINT_ACTION = 'skydropx_print_label';
	public function add_orders_list_action( $actions, $order ) {
		$id = (int) $order->get_id();
		if ( ! ( current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' ) ) || ! ( new Skydropx_Order_Label_Service() )->has_label_tracking_meta( $order ) ) {
			return $actions;
		}
		$actions[ self::PRINT_ACTION ] = array(
			'url'    => wp_nonce_url( add_query_arg( array( 'action' => self::PRINT_ACTION, 'order_id' => $id ), admin_url( 'admin-post.php' ) ), self::PRINT_ACTION . ':' . $id ),
			'name'   => 'Imprimir guía Skydropx',
			'action' => 'skydropx-print-label',
		);
		return $actions;
	}
}
PHP
		);
		// El método de envío: como el plugin, sólo se declara en `woocommerce_shipping_init` y cotiza con WC()->cart y
		// WC()->session (caché de 2 minutos por instancia, con huella de artículos, destino y total).
		$GLOBALS['sk_shipping_init'] = static function () {
			if ( class_exists( 'Skydropx_Shipping_Method', false ) ) {
				return;
			}
			eval( <<<'PHP'
class Skydropx_Shipping_Method extends WC_Shipping_Method {
	public $id = 'skydropx';
	public $title = 'Tarifas dinámicas en carrito';
	public function calculate_shipping( $package = array() ) {
		if ( true !== $GLOBALS['sk_plugin']['enabled'] || null === $GLOBALS['sk_plugin']['url'] || empty( $package['destination']['postcode'] ) ) {
			return;
		}
		$cart  = WC()->cart;
		$items = array();
		foreach ( $cart->get_cart() as $line ) {
			$product = $line['data'];
			if ( $product->is_virtual() || $product->is_downloadable() ) {
				continue;
			}
			$items[] = array( 'id' => $product->get_id(), 'quantity' => $line['quantity'], 'weight' => (float) wc_get_weight( (float) $product->get_weight(), 'kg' ), 'length' => (float) wc_get_dimension( (float) $product->get_length(), 'cm' ) );
		}
		$total = $cart instanceof WC_Cart ? (float) $cart->get_cart_contents_total() + ( $cart->display_prices_including_tax() ? (float) $cart->get_cart_contents_tax() : 0 ) : 0.0;
		// Como compute_quote_fingerprint() del plugin: IDs y cantidades, destino y total; nunca peso ni medidas.
		$print = md5( json_encode( array( array_map( static function ( $i ) { return array( $i['id'], $i['quantity'] ); }, $items ), $package['destination']['postcode'], $total ) ) );
		$key   = 'SKYDROPX_QUOTE_CACHE:' . (int) $this->instance_id;
		$cache = WC()->session ? WC()->session->get( $key ) : null;
		if ( is_array( $cache ) && $print === $cache['fingerprint'] && time() <= $cache['expires'] ) {
			foreach ( $cache['rates'] as $rate ) {
				$this->add_rate( $rate );
			}
			return;
		}
		++$GLOBALS['sk_plugin']['http'];
		$GLOBALS['sk_plugin']['sent'] = array( 'items' => $items, 'total_price' => $total, 'url' => $GLOBALS['sk_plugin']['url'] );
		$rates = array();
		foreach ( $GLOBALS['sk_plugin']['rates'] ?? array( array( 'service_provider' => 'fedex', 'service_name' => 'Estándar', 'service_code' => 'std', 'total_price' => 189.5, 'description' => '3 días', 'currency' => 'MXN' ), array( 'service_provider' => 'dhl', 'service_name' => 'Express', 'service_code' => 'exp', 'total_price' => 99.0, 'description' => '1 día', 'currency' => 'MXN' ) ) as $quote ) {
			$rates[] = array( 'id' => $this->get_rate_id() . '_' . $quote['service_provider'] . '_' . $quote['service_code'], 'label' => $quote['service_name'] . ' (' . $quote['description'] . ')', 'cost' => $quote['total_price'], 'calc_tax' => 'per_order', 'meta_data' => array( 'provider' => $quote['service_provider'], 'service_level_name' => $quote['service_name'], 'service_level_code' => $quote['service_code'], 'days' => $quote['description'], 'total_pricing' => $quote['total_price'], 'currency_local' => $quote['currency'] ) );
		}
		if ( WC()->session && $rates ) {
			WC()->session->set( $key, array( 'fingerprint' => $print, 'expires' => time() + 120, 'rates' => $rates ) );
		}
		foreach ( $rates as $rate ) {
			$this->add_rate( $rate );
		}
	}
}
PHP
			);
		};
	}

	// Tienda de prueba.
	$sk_store = static function () {
		$GLOBALS['sk_classes']  = array( 'chica' => 'Chica', 'grande' => 'XL' );
		$GLOBALS['sk_notes']    = array();
		$GLOBALS['sk_products'] = array();
		$add                    = static function ( WC_Product $p ) {
			$GLOBALS['sk_products'][ $p->id ] = $p;
			return $p;
		};
		$add( new WC_Product( 10, 'simple', array( 'weight' => '1.2', 'length' => '20', 'width' => '15', 'height' => '10', 'shipping_class_id' => 3 ) ) );
		$add( new WC_Product( 11, 'simple', array( 'weight' => '0', 'length' => '20', 'width' => '', 'height' => '10' ) ) );
		$v = $add( new WC_Product( 12, 'simple' ) );
		$v->virtual = true;
		$d = $add( new WC_Product( 13, 'simple' ) );
		$d->download = true;
		$add( new WC_Product( 14, 'grouped' ) );
		$add( new WC_Product( 15, 'external' ) );
		$draft         = $add( new WC_Product( 16, 'simple' ) );
		$draft->status = 'draft';
		$add( new WC_Product( 20, 'variable', array( 'weight' => '2', 'length' => '30', 'width' => '20', 'height' => '10', 'shipping_class_id' => 4 ) ) );
		$add( new WC_Product( 21, 'variation', array(), 20 ) );
		$add( new WC_Product( 22, 'variation', array( 'weight' => '2.5' ), 20 ) );
		$add( new WC_Product( 30, 'variable', array( 'length' => '30', 'width' => '20', 'height' => '10' ) ) );
		$add( new WC_Product( 31, 'variation', array( 'weight' => '' ), 30 ) );
		$off         = $add( new WC_Product( 32, 'variation', array(), 30 ) );
		$off->status = 'private';
		$m1          = new Sk_Flat_Rate( 4 );
		$GLOBALS['sk_zones'] = array( new WC_Shipping_Zone( 3, 'Skydropx', array(), array( (object) array( 'code' => 'MX', 'type' => 'country' ), (object) array( 'code' => 'CO', 'type' => 'country' ) ) ), new WC_Shipping_Zone( 5, 'Local', array( $m1 ), array( (object) array( 'code' => 'US', 'type' => 'country' ) ) ) );
		WC()->shipping();
		if ( class_exists( 'Skydropx_Shipping_Method', false ) ) {
			$method                            = new Skydropx_Shipping_Method( 9 );
			$GLOBALS['sk_zones'][0]->methods = array( 9 => $method );
		}
		$GLOBALS['sk_zone0']    = array();
		$GLOBALS['sk_webhooks'] = array(
			1 => new Sk_Webhook( SK_HOOK_URL, 'active', 'order.created', SK_HOOK_SIGN ),
			2 => new Sk_Webhook( SK_HOOK_URL, 'active', 'order.updated', SK_HOOK_SIGN ),
			3 => new Sk_Webhook( 'https://otro.test/hook', 'active', 'order.updated', 'otro' ),
			4 => new Sk_Webhook( SK_HOOK_URL, 'paused', 'product.updated', SK_HOOK_SIGN ),
		);
		$GLOBALS['sk_orders']   = array(
			50 => new WC_Order( 50, array( new WC_Order_Item_Shipping( 501, array( 'Tracking Number' => 'GUIA123', 'Tracking Company' => 'FedEx', 'Tracking URL' => 'https://rastreo.test/GUIA123' ) ) ) ),
			51 => new WC_Order( 51, array( new WC_Order_Item_Shipping( 511 ), new WC_Order_Item_Shipping( 512 ) ) ),
			52 => new WC_Order( 52 ),
		);
	};
	$GLOBALS['sk_mode_name'] = $sk_mode;

	// Todo lo que sale de la herramienta (respuestas y bitácora) pasa por aquí: ningún secreto, nunca.
	$GLOBALS['sk_seen'] = array();
	$seen               = static function ( $value ) {
		$GLOBALS['sk_seen'][] = json_encode( $value instanceof WP_Error ? array( $value->get_error_code(), $value->get_error_message(), $value->get_error_data() ) : $value );
		return $value;
	};
	$no_secrets = static function () {
		$all = implode( "\n", $GLOBALS['sk_seen'] ) . json_encode( Log::entries() );
		foreach ( array( SK_SECRET, 'TOKENSECRETO98765', 'WEBHOOKSECRETO4242', SK_HOOK_SIGN, '/v2/rates/tienda-abc', '_wpnonce' ) as $secret ) {
			check( false === strpos( $all, $secret ) && false === strpos( $all, str_replace( '/', '\\/', $secret ) ), "Ningún secreto en respuestas ni bitácora: $secret" );
		}
	};

	// =================================================================================================================
	// C. Otra versión del plugin, sin piezas (subproceso): lo dice y no rompe.
	if ( SK_COMPAT === $sk_mode ) {
		sk_reset( array( 'caps' => $manager, 'options' => array_merge( $GLOBALS['sk']['options'], $write ) ) );
		$sk_store();
		check( in_array( 'skydropx', Tools::tools(), true ), 'Otra versión: la herramienta sigue registrada.' );
		$status = $ok( $seen( $run( array( 'action' => 'status' ) ) ), 'Otra versión: status responde.' );
		$missing = $status['data']['plugin']['missing'];
		foreach ( array( 'Skydropx\\Includes\\Skydropx_Repository::has_plugin_api_keys()', 'Skydropx\\Admin\\Skydropx_Order_Label_UI::add_orders_list_action()', 'Skydropx\\Admin\\Skydropx_Order_Label_Service::META_TRACKING_NUMBER', 'Skydropx_Shipping_Method::calculate_shipping()' ) as $piece ) {
			check( in_array( $piece, $missing, true ), "Otra versión: status dice que falta $piece (" . implode( ', ', $missing ) . ').' );
		}
		check( null === $status['data']['linked'] && null === $status['data']['quotation']['enabled'] && '3.0.0' === $status['data']['plugin']['version'], 'Otra versión: lo que no se sabe sale como null, sin inventar.' );
		$err( $run( array( 'action' => 'label', 'order_id' => 50 ) ), 'digitalisimo_woo_mcp_skydropx_compat', 'Otra versión sin la interfaz de guías: label lo dice.' );
		$err( $run( array( 'action' => 'quote', 'address' => array( 'postcode' => '06700' ), 'cart' => array( array( 'product_id' => 10 ) ) ) ), 'digitalisimo_woo_mcp_skydropx_compat', 'Otra versión sin método de envío: quote lo dice.' );
		$track = $ok( $run( array( 'action' => 'set_tracking', 'order_id' => 51, 'tracking' => array( 'number' => 'X1', 'company' => 'Estafeta', 'url' => 'https://rastreo.test/X1' ) ) ), 'Otra versión: set_tracking usa las metas de 2.1.5.' );
		check( 'X1' === $GLOBALS['sk_orders'][51]->lines[511]->meta['Tracking Number'] && 'manual' === $track['data']['label'], 'Otra versión: metas «Tracking …» de 2.1.5 escritas (rastreo manual).' );
		$ok( $run( array( 'action' => 'get_shipment', 'order_id' => 50 ) ), 'Otra versión: get_shipment responde.' );
		echo "compat-ok {$GLOBALS['sk_checks']}\n";
		exit( 0 );
	}

	// =================================================================================================================
	// B. Plugin 2.1.5 cargado: registro y definición.
	sk_reset();
	$sk_store();
	$registry = $boot();
	check( array( 'products', 'catalog', 'orders', 'customers', 'coupons', 'store-settings', 'reports', 'skydropx' ) === Tools::tools(), 'Con Skydropx: ocho herramientas, la suya al final.' );
	check( 8 === count( $registry->abilities ) && isset( $registry->abilities['digitalisimo-woo/skydropx'] ), 'Con Skydropx: se registra en el registro del núcleo.' );
	check( 'skydropx' === Tools::tool_of( 'digitalisimo-woo/skydropx' ), 'Su ID es de la herramienta.' );
	$ability = $registry->abilities['digitalisimo-woo/skydropx'];
	check( false === $ability->is_exposed_via_proxy(), 'Fuera del proxy del editor, como las demás.' );
	foreach ( array( 'read', 'write' ) as $mode ) {
		$def = Tools::definition( 'skydropx', $mode );
		check( 'digitalisimo-woo' === $def['category'] && '' !== $def['label'] && strlen( $def['description'] ) > 400, "Definición con categoría, etiqueta y descripción ($mode)." );
		check( false !== strpos( $def['description'], 'write' === $mode ? 'permite escribir' : 'sólo permite leer' ), "La descripción dice el modo ($mode)." );
		check( false !== strpos( $def['description'], 'a mano' ), 'La descripción dice que crear y pagar envíos se hace a mano.' );
		check( false === $def['meta']['show_in_rest'] && false === $def['meta']['mcp']['public'], 'Sólo en el servidor MCP de Elements.' );
		check( false === $def['meta']['annotations']['readonly'] && true === $def['meta']['annotations']['destructive'] && false === $def['meta']['annotations']['idempotent'], 'Anotaciones como las otras que escriben.' );
	}
	$schema = Tools::input_schema( 'skydropx' );
	check( 'object' === $schema['type'] && false === $schema['additionalProperties'] && array( 'action' ) === $schema['required'], 'Entrada cerrada con action obligatoria.' );
	check( Skydropx_Tool::actions() === $schema['properties']['action']['enum'] && 9 === count( Skydropx_Tool::actions() ), 'action con enum de nueve acciones.' );
	check( false === strpos( json_encode( $schema ), '"post_id"' ), 'Nunca la clave post_id.' );
	foreach ( $schema['properties'] as $key => $prop ) {
		check( isset( $prop['type'], $prop['description'] ) && '' !== $prop['description'], "Cada parámetro con tipo y descripción: $key" );
		if ( 'object' === $prop['type'] ) {
			check( false === $prop['additionalProperties'], "Objeto cerrado: $key" );
		}
		if ( 'array' === $prop['type'] && 'object' === $prop['items']['type'] ) {
			check( false === $prop['items']['additionalProperties'], "Elementos cerrados: $key" );
		}
	}
	check( 100 === $schema['properties']['items']['maxItems'] && 100 === $schema['properties']['per_page']['maximum'], 'Lote y páginas con tope de 100.' );
	foreach ( Skydropx_Tool::PARAMS as $action => $params ) {
		check( ! array_diff( $params, array_keys( $schema['properties'] ) ), "Parámetros de $action en el esquema." );
	}
	check( array( 'status', 'audit_products', 'quote', 'shipments', 'get_shipment', 'label' ) === Skydropx_Tool::READ_ACTIONS && array( 'update_products', 'set_tracking', 'resync' ) === Skydropx_Tool::WRITE_ACTIONS, 'Lecturas y escrituras.' );
	foreach ( Skydropx_Tool::actions() as $action ) {
		check( Skydropx_Tool::is_write( $action ) === Tools::is_write( 'skydropx', $action ), "Tools::is_write coincide: $action" );
	}

	// =================================================================================================================
	// Permisos.
	$ask = static function ( array $caps, $input = null ) {
		$GLOBALS['sk']['caps'] = $caps;
		return Executor::permission( 'skydropx', $input );
	};
	check( true === $ask( $manager ) && true === $ask( array( 'edit_products' ) ) && true === $ask( array( 'edit_shop_orders' ) ), 'Mínimo: edit_products o edit_shop_orders.' );
	check( is_wp_error( $ask( array( 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'moderate_comments' ) ) ) && is_wp_error( $ask( array( 'read' ) ) ), 'Un editor o un suscriptor: nada.' );
	foreach ( Skydropx_Tool::actions() as $action ) {
		check( true === $ask( $manager, array( 'action' => $action ) ), "Gerente de tienda: $action" );
		check( is_wp_error( $ask( array( 'edit_posts', 'read' ), array( 'action' => $action ) ) ), "Editor: $action no." );
	}
	check( true === $ask( array( 'edit_products', 'read_private_products' ), array( 'action' => 'audit_products' ) ), 'Auditar: edit_products y read_private_products (como listar productos).' );
	check( is_wp_error( $ask( array( 'edit_products' ), array( 'action' => 'audit_products' ) ) ), 'Auditar sin read_private_products: no.' );
	$denied = $ask( array( 'edit_products', 'read_private_products' ), array( 'action' => 'update_products' ) );
	check( is_wp_error( $denied ) && false !== strpos( $denied->get_error_message(), 'edit_others_products' ) && 403 === $denied->get_error_data()['status'], 'Corregir productos: edit_others_products (el lote de products).' );
	check( is_wp_error( $ask( array( 'edit_products', 'read_private_products', 'edit_others_products' ), array( 'action' => 'shipments' ) ) ), 'Quien sólo edita productos no ve pedidos.' );
	check( is_wp_error( $ask( array( 'edit_shop_orders' ), array( 'action' => 'shipments' ) ) ) && true === $ask( array( 'edit_shop_orders', 'read_private_shop_orders' ), array( 'action' => 'shipments' ) ), 'Envíos: read_private_shop_orders además de edit_shop_orders.' );
	check( true === $ask( array( 'edit_shop_orders' ), array( 'action' => 'set_tracking' ) ) && is_wp_error( $ask( array( 'edit_products', 'read_private_products', 'edit_others_products' ), array( 'action' => 'set_tracking' ) ) ), 'Rastreo: edit_shop_orders (actualizar pedidos).' );
	check( true === $ask( array( 'edit_products' ), array( 'action' => 'quote' ) ) && true === $ask( array( 'edit_shop_orders' ), array( 'action' => 'status' ) ), 'Estado y cotización: cualquiera de las dos.' );
	$GLOBALS['sk']['caps'] = array( 'edit_posts' );
	check( false === $ability->check_permission(), 'check_permission() del núcleo: un editor no (sólo un true estricto).' );
	$GLOBALS['sk']['caps'] = $manager;
	check( true === $ability->check_permission(), 'check_permission() del núcleo: un gerente sí.' );

	// =================================================================================================================
	// Modos y bitácora.
	sk_reset( array( 'caps' => $manager, 'options' => array_merge( $GLOBALS['sk']['options'], array( Settings::OPTION => array( 'mode' => 'off', 'own' => array( 'mode' ) ) ) ) ) );
	$sk_store();
	$err( $run( array( 'action' => 'status' ) ), 'digitalisimo_woo_mcp_off', 'Modo off: nada.' );
	check( 0 === count( $boot()->abilities ), 'Modo off: ninguna herramienta registrada.' );
	sk_reset( array( 'caps' => $manager ) );
	$sk_store();
	$ok( $run( array( 'action' => 'status' ) ), 'Sólo lectura: status.' );
	foreach ( array(
		array( 'action' => 'update_products', 'items' => array( array( 'product_id' => 11, 'weight' => 1 ) ) ),
		array( 'action' => 'set_tracking', 'order_id' => 51, 'tracking' => array( 'number' => 'A1', 'company' => 'DHL', 'url' => 'https://r.test/A1' ) ),
		array( 'action' => 'resync', 'order_id' => 50 ),
	) as $input ) {
		$denied = $err( $run( $input ), 'digitalisimo_woo_mcp_read_only', 'Sólo lectura: ' . $input['action'] . ' se rechaza diciendo dónde se cambia.' );
		check( false !== strpos( $denied->get_error_message(), 'Rendimiento' ), 'El error dice dónde se cambia el modo.' );
		check( 'denied' === Log::entries()[0]['result'] && 'read_mode' === Log::entries()[0]['code'] && $input['action'] === Log::entries()[0]['action'] && 'skydropx' === Log::entries()[0]['tool'], 'La escritura rechazada queda en la bitácora.' );
	}
	check( array() === $GLOBALS['sk_rest'] && 0 === $GLOBALS['sk_orders'][51]->saves && 0 === $GLOBALS['sk_orders'][50]->saves, 'Sólo lectura: nada llegó a WooCommerce.' );
	// Tanda B (B3): «Escritura sólo en lo elegido». Sin Skydropx marcado, sus escrituras se rechazan como en sólo
	// lectura, diciendo qué marcar; con Skydropx marcado, escribe. Fallaba con fee02105 (el modo custom no existía).
	sk_reset( array( 'caps' => $manager, 'options' => array_merge( $GLOBALS['sk']['options'], array( Settings::OPTION => array( 'mode' => 'custom', 'own' => array( 'mode' ), 'write_tools' => array( 'products' ) ) ) ) ) );
	$sk_store();
	$denied = $err( $run( array( 'action' => 'resync', 'order_id' => 50 ) ), 'digitalisimo_woo_mcp_read_only', 'Escritura elegida sin Skydropx: resync se rechaza.' );
	check( false !== strpos( $denied->get_error_message(), 'Envíos con Skydropx' ) && 0 === $GLOBALS['sk_orders'][50]->saves, 'El error dice que falta marcar Skydropx, y nada se guarda.' );
	check( false !== strpos( Skydropx_Tool::definition( 'custom' )['description'], 'no está elegido' ), 'La descripción lo dice.' );
	sk_reset( array( 'caps' => $manager, 'options' => array_merge( $GLOBALS['sk']['options'], array( Settings::OPTION => array( 'mode' => 'custom', 'own' => array( 'mode' ), 'write_tools' => array( 'skydropx' ) ) ) ) ) );
	$sk_store();
	$ok( $run( array( 'action' => 'resync', 'order_id' => 50 ) ), 'Escritura elegida con Skydropx: resync se hace.' );
	$GLOBALS['sk']['caps'] = array( 'edit_posts' );
	sk_reset( array( 'caps' => array( 'edit_posts' ), 'options' => array_merge( $GLOBALS['sk']['options'], $write ) ) );
	$err( $run( array( 'action' => 'resync', 'order_id' => 50 ) ), 'digitalisimo_woo_mcp_forbidden', 'Sin permiso: rechazado por run() mismo.' );
	check( 'denied' === Log::entries()[0]['result'] && 'forbidden' === Log::entries()[0]['code'], 'Escritura sin permiso: denied en la bitácora.' );

	// Entrada cerrada.
	sk_reset( array( 'caps' => $manager, 'options' => array_merge( $GLOBALS['sk']['options'], $write ) ) );
	$sk_store();
	$unknown = $err( $run( array( 'action' => 'status', 'post_id' => 3 ) ), 'digitalisimo_woo_mcp_input', 'Parámetro desconocido.' );
	check( false !== strpos( $unknown->get_error_message(), 'no admite: post_id' ), 'Un parámetro que el esquema no declara se nombra como desconocido.' );
	$err( $run( array( 'action' => 'status', 'page' => 2 ) ), 'digitalisimo_woo_mcp_input', 'Un valor que no es el predeterminado, fuera de su acción, se rechaza.' );
	$err( $run( array( 'action' => 'borrar' ) ), 'digitalisimo_woo_mcp_action', 'Acción desconocida.' );
	$err( $run( array( 'action' => 'status', 'order_id' => 50 ) ), 'digitalisimo_woo_mcp_input', 'Un parámetro de otra acción se rechaza.' );
	$ok( $run( array( 'action' => 'status', 'page' => 1, 'per_page' => 10, 'cache' => true, 'tracking_filter' => 'any' ) ), 'Los valores por defecto que rellena un cliente se ignoran.' );
	$err( $run( array( 'action' => 'get_shipment', 'order_id' => '5/../7' ) ), 'digitalisimo_woo_mcp_id', 'order_id no entero.' );
	$err( $run( array( 'action' => 'label' ) ), 'digitalisimo_woo_mcp_id', 'label sin order_id.' );
	$err( $run( array( 'action' => 'shipments', 'per_page' => 101 ) ), 'digitalisimo_woo_mcp_page', 'per_page ≤ 100.' );

	// =================================================================================================================
	// status.
	$status = $ok( $seen( $run( array( 'action' => 'status' ) ) ), 'status' );
	$data   = $status['data'];
	check( '2.1.5' === $data['plugin']['version'] && array() === $data['plugin']['missing'], 'status: versión y ninguna pieza faltante con 2.1.5.' );
	check( true === $data['linked'] && true === $data['quotation']['enabled'] && true === $data['quotation']['url_set'] && 'cotiza.skydropx.test' === $data['quotation']['url_host'] && true === $data['quotation']['https'], 'status: vinculada y cotización activa; de la URL sólo el host.' );
	check( true === $data['zone']['found'] && 3 === $data['zone']['id'] && true === $data['zone']['first'] && 9 === $data['zone']['method']['instance_id'] && true === $data['zone']['method']['enabled'], 'status: zona Skydropx primera con su método.' );
	check( array( array( 'code' => 'MX', 'type' => 'country' ), array( 'code' => 'CO', 'type' => 'country' ) ) === $data['zone']['locations'], 'status: ubicaciones de la zona.' );
	check( 'kg' === $data['units']['weight'] && 'cm' === $data['units']['dimension'] && 'kg' === $data['units']['plugin_sends']['weight'], 'status: unidades de la tienda y las que manda el plugin.' );
	check( true === $data['origin']['complete'] && '44100' === $data['origin']['postcode'] && 'MX' === $data['origin']['country'] && 'JAL' === $data['origin']['state'], 'status: origen completo.' );
	check( array( 'total' => 3, 'active' => 2, 'topics' => array( 'order.created', 'order.updated' ) ) === $data['webhooks'], 'status: sólo los webhooks hacia Skydropx, sin URL: ' . json_encode( $data['webhooks'] ) );
	check( array( 'Tracking Number', 'Tracking Company', 'Tracking URL' ) === $data['tracking_meta'], 'status: las metas del plugin (de sus constantes privadas).' );
	check( array() === $status['warnings'], 'status: sin avisos en una tienda bien configurada: ' . json_encode( $status['warnings'] ) );
	// Lo que falla, en avisos.
	$GLOBALS['sk_plugin']['linked']  = false;
	$GLOBALS['sk_plugin']['enabled'] = false;
	$GLOBALS['sk']['options']['woocommerce_store_postcode'] = '';
	$GLOBALS['sk']['options']['permalink_structure']        = '';
	$GLOBALS['sk_zones']             = array_reverse( $GLOBALS['sk_zones'] );
	$GLOBALS['sk_webhooks']          = array();
	$status = $seen( $run( array( 'action' => 'status' ) ) );
	$text   = implode( ' ', $status['warnings'] );
	check( 5 <= count( $status['warnings'] ) && false !== strpos( $text, 'vinculada' ) && false !== strpos( $text, 'desactivada' ) && false !== strpos( $text, 'código postal' ) && false !== strpos( $text, 'no es la primera' ) && false !== strpos( $text, 'webhooks' ) && false !== strpos( $text, 'enlaces permanentes' ), 'status: avisos de vinculación, cotización, origen, orden de zonas, webhooks y enlaces: ' . $text );
	check( false === $status['data']['zone']['first'] && array( array( 'id' => 5, 'name' => 'Local' ) ) === $status['data']['zone']['zones_before'], 'status: qué zona va antes.' );
	// B1 · Envíos apagados en la tienda: lo dice, no culpa a la versión del plugin, y quote responde 409 claro.
	$GLOBALS['sk']['shipping_off'] = true;
	$status = $seen( $run( array( 'action' => 'status' ) ) );
	check( false === $status['data']['shipping'] && false !== strpos( implode( ' ', $status['warnings'] ), 'envíos están desactivados' ) && ! in_array( 'Skydropx_Shipping_Method::calculate_shipping()', $status['data']['plugin']['missing'], true ), 'status: envíos apagados, sin culpar a la versión del plugin.' );
	$err( $run( array( 'action' => 'quote', 'address' => array( 'postcode' => '06700' ), 'cart' => array( array( 'product_id' => 10 ) ) ) ), 'digitalisimo_woo_mcp_skydropx_shipping_off', 'quote: envíos apagados, 409 claro.' );
	unset( $GLOBALS['sk']['shipping_off'] );
	// B7 · Sin manage_woocommerce: un resumen de zona y webhooks (store-settings los reserva a quien administra).
	$GLOBALS['sk']['caps'] = array( 'edit_products' );
	$status = $seen( $run( array( 'action' => 'status' ) ) );
	check( 'summary' === $status['data']['detail'] && array( 'found', 'first', 'method_enabled' ) === array_keys( $status['data']['zone'] ) && array( 'active' => false ) === $status['data']['webhooks'], 'status: resumen sin ubicaciones, zonas ni temas para quien sólo edita productos: ' . json_encode( $status['data']['zone'] ) );
	$GLOBALS['sk']['caps'] = $manager;
	check( 'full' === $seen( $run( array( 'action' => 'status' ) ) )['data']['detail'], 'status: completo con manage_woocommerce.' );
	$GLOBALS['sk_plugin'] = array( 'linked' => true, 'enabled' => true, 'url' => SK_QUOTE_URL, 'http' => 0, 'rates' => null );
	sk_reset( array( 'caps' => $manager, 'options' => array_merge( $GLOBALS['sk']['options'], $write ) ) );
	$sk_store();

	// =================================================================================================================
	// audit_products.
	$log_before = count( Log::entries() );
	$GLOBALS['sk']['primed'] = array();
	$audit      = $ok( $seen( $run( array( 'action' => 'audit_products', 'per_page' => 100 ) ) ), 'audit_products' );
	$ids        = array_column( $audit['data'], 'id' );
	check( array( 11, 31 ) === $ids, 'audit: el simple con peso 0 y ancho vacío y la variación sin peso propio ni del padre; no los virtuales/descargables, agrupados, externos, borradores, variaciones desactivadas ni las que heredan: ' . json_encode( $ids ) );
	check( array( 'weight', 'width' ) === $audit['data'][0]['missing'] && array( 'weight' ) === $audit['data'][1]['missing'], 'audit: qué falta de cada uno (por defecto sólo peso y medidas, lo que Skydropx usa): ' . json_encode( array_column( $audit['data'], 'missing' ) ) );
	check( 30 === $audit['data'][1]['parent_id'] && array( 'product_id' => 30, 'variation_id' => 31 ) === $audit['data'][1]['fix'] && array( 'product_id' => 11 ) === $audit['data'][0]['fix'], 'audit: cómo corregir cada uno (padre y variación).' );
	check( 2 === $audit['total'] && 1 === $audit['total_pages'] && 7 === $audit['summary']['scanned'] && 2 === $audit['summary']['skipped_virtual'] && false === $audit['cached'], 'audit: totales (' . json_encode( $audit['summary'] ) . ').' );
	check( array( 'weight' => 2, 'length' => 0, 'width' => 1, 'height' => 0 ) === $audit['summary']['missing'] && array( 'weight', 'dimensions' ) === $audit['summary']['checks'], 'audit: recuento por campo, sin la clase de envío por defecto (B2).' );
	$primed = $GLOBALS['sk']['primed'];
	check( $primed && true === $primed[0][1] && true === $primed[0][2] && in_array( array( array( 20, 30 ), true, true ), $primed, true ), 'audit: precarga entradas, metas y términos de cada bloque y de los padres de sus variaciones: ' . json_encode( $primed ) );
	check( in_array( 'posts:11', $GLOBALS['sk']['cache_deleted'], true ) && in_array( 'product_type_relationships:11', $GLOBALS['sk']['cache_deleted'], true ), 'audit: sin caché persistente, libera entradas, metas y términos de cada bloque.' );
	$queries = count( $GLOBALS['sk']['get_posts'] );
	$page    = $ok( $run( array( 'action' => 'audit_products', 'per_page' => 1, 'page' => 2 ) ), 'audit: página 2' );
	check( array( 31 ) === array_column( $page['data'], 'id' ) && 2 === $page['total'] && 2 === $page['total_pages'], 'audit: paginado con totales.' );
	check( true === $page['cached'] && $queries === count( $GLOBALS['sk']['get_posts'] ), 'audit: la página 2 lee el recorrido de la 1 sin volver a recorrer la tienda (M5).' );
	$only = $ok( $run( array( 'action' => 'audit_products', 'checks' => array( 'dimensions' ) ) ), 'audit: sólo medidas' );
	check( array( 11 ) === array_column( $only['data'], 'id' ) && array( 'width' ) === $only['data'][0]['missing'], 'audit: checks elige qué se revisa.' );
	$cls = $ok( $run( array( 'action' => 'audit_products', 'checks' => array( 'weight', 'shipping_class' ) ) ), 'audit con clase de envío' );
	check( array( 'weight', 'shipping_class' ) === $cls['data'][0]['missing'] && array( 'weight', 'shipping_class' ) === $cls['data'][1]['missing'], 'audit: la clase de envío sólo si se pide.' );
	$other = $ok( $run( array( 'action' => 'audit_products', 'checks' => array( 'dimensions' ), 'page' => 2 ) ), 'audit: otra consulta en la página 2' );
	check( false === $other['cached'], 'audit: la caché de páginas es por checks y after_id (otra consulta vuelve a recorrer).' );
	$after = $ok( $run( array( 'action' => 'audit_products', 'after_id' => 21 ) ), 'audit después del 21' );
	check( array( 31 ) === array_column( $after['data'], 'id' ) && 2 === $after['summary']['scanned'] && ! isset( $after['next_after_id'] ), 'audit: after_id revisa lo que sigue, en orden de ID.' );
	$err( $run( array( 'action' => 'audit_products', 'checks' => array( 'peso' ) ) ), 'digitalisimo_woo_mcp_input', 'audit: checks cerrado.' );
	$err( $run( array( 'action' => 'audit_products', 'after_id' => -1 ) ), 'digitalisimo_woo_mcp_input', 'audit: after_id no negativo.' );
	$last = end( $GLOBALS['sk']['get_posts'] );
	check( 'product_variation' === $last['post_type'] && array( 20, 30 ) === $last['post_parent__in'] && 'publish' === $last['post_status'] && -1 === $last['posts_per_page'] && 'ids' === $last['fields'], 'audit: sólo IDs de las variaciones publicadas de los variables publicados.' );
	// B3 · Un valor guardado como «.5» o «1.» se cotiza (el plugin hace (float)): no es un problema.
	$GLOBALS['sk_products'][11]->props['weight'] = '.5';
	$GLOBALS['sk_products'][11]->props['width']  = '1.';
	$loose = $ok( $run( array( 'action' => 'audit_products' ) ), 'audit con valores sueltos' );
	check( array( 31 ) === array_column( $loose['data'], 'id' ), 'audit: «.5» y «1.» cuentan como valores, como en el plugin.' );
	$GLOBALS['sk_products'][11]->props['weight'] = '0';
	$GLOBALS['sk_products'][11]->props['width']  = '';
	// Más de 5 000: los primeros 5 000 por ID y un cursor para seguir; con caché persistente, la memoria se vacía por bloque.
	for ( $i = 10001; $i <= 15000; $i++ ) {
		$GLOBALS['sk_products'][ $i ] = new WC_Product( $i, 'simple', array( 'weight' => '1', 'length' => '1', 'width' => '1', 'height' => '1' ) );
	}
	$GLOBALS['sk']['ext_cache'] = true;
	$GLOBALS['sk']['flushes']   = 0;
	$big                        = $ok( $run( array( 'action' => 'audit_products' ) ), 'audit con 5 007' );
	check( true === $big['truncated'] && 14993 === $big['next_after_id'] && 5000 === $big['summary']['scanned'] && array( 11, 31 ) === array_column( $big['data'], 'id' ), 'audit: tope de 5 000 en orden de ID con next_after_id: ' . json_encode( array( $big['next_after_id'] ?? null, $big['summary']['scanned'] ) ) );
	check( 50 === $GLOBALS['sk']['flushes'], 'audit: con caché persistente, vacía su capa en memoria en cada bloque de 100 (' . $GLOBALS['sk']['flushes'] . ').' );
	$rest = $ok( $run( array( 'action' => 'audit_products', 'after_id' => $big['next_after_id'] ) ), 'audit: lo que sigue' );
	check( 7 === $rest['summary']['scanned'] && ! isset( $rest['next_after_id'] ) && array() === $rest['data'], 'audit: after_id sigue con los 7 restantes.' );
	unset( $GLOBALS['sk']['ext_cache'] );
	foreach ( range( 10001, 15000 ) as $id ) {
		unset( $GLOBALS['sk_products'][ $id ] );
	}
	check( array() === $GLOBALS['sk_rest'] && $log_before === count( Log::entries() ), 'audit: sólo lee (nada a la REST, nada en la bitácora).' );

	// =================================================================================================================
	// update_products: unidades, validación, lote con tope y la vía de products.
	$GLOBALS['sk_responses']['POST /wc/v3/products/batch'] = static function ( $query, $body ) {
		$out = array();
		foreach ( $body['update'] as $item ) {
			$out[] = 404 === $item['id'] ? array( 'id' => 404, 'error' => array( 'code' => 'woocommerce_rest_product_invalid_id', 'message' => 'ID no válido.', 'data' => array( 'status' => 400, 'params' => array( 'weight' => '9' ) ) ) ) : array( 'id' => $item['id'], 'weight' => $item['weight'] ?? '1.2', 'dimensions' => array_merge( array( 'length' => '20', 'width' => '15', 'height' => '10' ), $item['dimensions'] ?? array() ), 'shipping_class' => $item['shipping_class'] ?? '', 'name' => 'Producto' );
		}
		return array( 200, array( 'update' => $out ), array() );
	};
	$GLOBALS['sk_responses']['POST /wc/v3/products/30/variations/batch'] = static function ( $query, $body ) {
		return array( 200, array( 'update' => array_map( static function ( $item ) {
			return array( 'id' => $item['id'], 'weight' => $item['weight'] ?? '', 'dimensions' => array( 'length' => '30', 'width' => '20', 'height' => '10' ), 'shipping_class' => '' );
		}, $body['update'] ) ), array() );
	};
	$result = $ok(
		$seen(
			$run(
				array(
					'action'         => 'update_products',
					'weight_unit'    => 'g',
					'dimension_unit' => 'in',
					'items'          => array(
						array( 'product_id' => 11, 'weight' => 1500, 'width' => 10, 'shipping_class' => 'chica' ),
						array( 'product_id' => 30, 'variation_id' => 31, 'weight' => '250.5' ),
					),
				)
			)
		),
		'update_products en gramos y pulgadas'
	);
	check( array( array( 'POST', '/wc/v3/products/batch', array(), array( 'update' => array( array( 'id' => 11, 'weight' => '1.5', 'dimensions' => array( 'width' => '25.4' ), 'shipping_class' => 'chica' ) ) ) ), array( 'POST', '/wc/v3/products/30/variations/batch', array(), array( 'update' => array( array( 'id' => 31, 'weight' => '0.2505' ) ) ) ) ) === $GLOBALS['sk_rest'], 'update: lotes de la REST como products, convertidos a kg y cm de la tienda: ' . json_encode( $GLOBALS['sk_rest'] ) );
	check( true === $result['ok'] && array( 'done' => 2, 'failed' => 0 ) === $result['batch'] && '1.5' === $result['data'][0]['weight'] && '25.4' === $result['data'][0]['dimensions']['width'] && 'chica' === $result['data'][0]['shipping_class'] && 31 === $result['data'][1]['variation_id'], 'update: resultado por elemento en las unidades de la tienda.' );
	check( array( 'weight' => 'g', 'dimension' => 'in' ) === $result['units']['sent'] && array( 'weight' => 'kg', 'dimension' => 'cm' ) === $result['units']['store'], 'update: dice las unidades enviadas y las de la tienda.' );
	$entry = Log::entries()[0];
	check( 'ok' === $entry['result'] && 'update_products' === $entry['action'] && 'product' === $entry['resource'] && 2 === $entry['items'], 'update: en la bitácora con sus elementos.' );
	// Mismas unidades: sin conversión.
	$GLOBALS['sk_rest'] = array();
	$ok( $run( array( 'action' => 'update_products', 'items' => array( array( 'product_id' => 11, 'weight' => 0.75, 'length' => '12.50' ) ) ) ), 'update en kg y cm' );
	check( array( 'id' => 11, 'weight' => '0.75', 'dimensions' => array( 'length' => '12.5' ) ) === $GLOBALS['sk_rest'][0][3]['update'][0], 'update: en las unidades de la tienda, tal cual.' );
	// Rechazos antes de escribir.
	$GLOBALS['sk_rest'] = array();
	foreach ( array(
		array( array( array( 'product_id' => 11, 'weight' => 0 ) ), 'digitalisimo_woo_mcp_value', 'peso 0' ),
		array( array( array( 'product_id' => 11, 'weight' => -2 ) ), 'digitalisimo_woo_mcp_value', 'peso negativo' ),
		array( array( array( 'product_id' => 11, 'height' => '1e3' ) ), 'digitalisimo_woo_mcp_value', 'notación científica' ),
		array( array( array( 'product_id' => 11, 'length' => 'NaN' ) ), 'digitalisimo_woo_mcp_value', 'NaN' ),
		array( array( array( 'product_id' => 11, 'width' => 5000000 ) ), 'digitalisimo_woo_mcp_value', 'enorme' ),
		array( array( array( 'product_id' => 11, 'weight' => true ) ), 'digitalisimo_woo_mcp_value', 'booleano' ),
		array( array( array( 'product_id' => 11 ) ), 'digitalisimo_woo_mcp_input', 'sin campos' ),
		array( array( array( 'product_id' => 11, 'price' => 3 ) ), 'digitalisimo_woo_mcp_input', 'campo ajeno' ),
		array( array( array( 'product_id' => 11, 'weight' => 1 ), array( 'product_id' => 11, 'weight' => 2 ) ), 'digitalisimo_woo_mcp_batch', 'repetido' ),
		array( array( array( 'product_id' => 11, 'shipping_class' => 'Chica!' ) ), 'digitalisimo_woo_mcp_value', 'slug inválido' ),
		array( array( array( 'product_id' => 11, 'shipping_class' => 'mediana' ) ), 'digitalisimo_woo_mcp_skydropx_items', 'clase inexistente' ),
		array( array( array( 'product_id' => 999, 'weight' => 1 ) ), 'digitalisimo_woo_mcp_skydropx_items', 'producto inexistente' ),
		array( array( array( 'product_id' => 20, 'variation_id' => 31, 'weight' => 1 ) ), 'digitalisimo_woo_mcp_skydropx_items', 'variación de otro padre' ),
		array( array( array( 'product_id' => 21, 'weight' => 1 ) ), 'digitalisimo_woo_mcp_skydropx_items', 'variación sin su padre' ),
		array( array(), 'digitalisimo_woo_mcp_input', 'lista vacía' ),
	) as $case ) {
		$err( $run( array( 'action' => 'update_products', 'items' => $case[0] ) ), $case[1], 'update rechaza: ' . $case[2] );
	}
	$err( $run( array( 'action' => 'update_products', 'weight_unit' => 'ton', 'items' => array( array( 'product_id' => 11, 'weight' => 1 ) ) ) ), 'digitalisimo_woo_mcp_unit', 'update: unidad desconocida.' );
	// B8 · Un valor que en la unidad de la tienda quedaría en cero se rechaza (no se guarda «0.0001» en silencio).
	$err( $run( array( 'action' => 'update_products', 'weight_unit' => 'g', 'items' => array( array( 'product_id' => 11, 'weight' => 0.04 ) ) ) ), 'digitalisimo_woo_mcp_skydropx_items', 'update: 0.04 g en una tienda en kg.' );
	$many = array();
	for ( $i = 1; $i <= 101; $i++ ) {
		$many[] = array( 'product_id' => 1000 + $i, 'weight' => 1 );
	}
	$err( $run( array( 'action' => 'update_products', 'items' => $many ) ), 'digitalisimo_woo_mcp_batch', 'update: 101 productos superan el tope.' );
	check( array() === $GLOBALS['sk_rest'], 'update: ningún rechazo llegó a WooCommerce.' );
	foreach ( range( 1001, 1100 ) as $id ) {
		$GLOBALS['sk_products'][ $id ] = new WC_Product( $id, 'simple' );
	}
	// M6 · La clase se busca por slug; un nombre («xl» de «XL») se cambia por su slug con aviso; en una variación, «» es la del padre.
	$GLOBALS['sk_rest'] = array();
	$named              = $ok( $run( array( 'action' => 'update_products', 'items' => array( array( 'product_id' => 11, 'shipping_class' => 'xl' ), array( 'product_id' => 30, 'variation_id' => 31, 'shipping_class' => '' ) ) ) ), 'update con el nombre de la clase' );
	check( 'grande' === $GLOBALS['sk_rest'][0][3]['update'][0]['shipping_class'] && false !== strpos( implode( ' ', $named['warnings'] ), 'se usó su slug «grande»' ) && false !== strpos( implode( ' ', $named['warnings'] ), 'misma clase que el padre' ), 'update: nombre → slug con aviso, y «» en una variación explicado: ' . json_encode( $named['warnings'] ?? null ) );
	// M4 · Corregir productos borra la caché de cotizaciones y la del recorrido de la auditoría de este usuario.
	$GLOBALS['sk']['transients'][ Skydropx::QUOTE_CACHE . '7' ] = array( 'x' );
	$GLOBALS['sk']['transients'][ Skydropx::AUDIT_CACHE . '7' ] = array( 'x' );
	$GLOBALS['sk_rest'] = array();
	$hundred = $ok( $run( array( 'action' => 'update_products', 'items' => array_slice( $many, 0, 100 ) ) ), 'update: 100 productos sí.' );
	check( ! isset( $GLOBALS['sk']['transients'][ Skydropx::QUOTE_CACHE . '7' ] ) && ! isset( $GLOBALS['sk']['transients'][ Skydropx::AUDIT_CACHE . '7' ] ), 'update: borra las cachés de cotización y de auditoría del usuario.' );
	check( 100 === $hundred['batch']['done'] && 1 === count( $GLOBALS['sk_rest'] ) && 100 === count( $GLOBALS['sk_rest'][0][3]['update'] ), 'update: el lote de 100 en una sola petición.' );
	// Parcial y total.
	$GLOBALS['sk_products'][404] = new WC_Product( 404, 'simple' );
	$partial                     = $seen( $run( array( 'action' => 'update_products', 'items' => array( array( 'product_id' => 11, 'weight' => 1 ), array( 'product_id' => 404, 'weight' => 9 ) ) ) ) );
	check( ! is_wp_error( $partial ) && false === $partial['ok'] && true === $partial['partial'] && array( 'done' => 1, 'failed' => 1 ) === $partial['batch'] && 'woocommerce_rest_product_invalid_id' === $partial['data'][1]['error']['code'] && ! isset( $partial['code'] ), 'update parcial: ok false, partial y el error en su elemento.' );
	check( false === strpos( json_encode( $partial ), 'params' ), 'update: del error de WooCommerce no salen sus datos (pueden repetir lo enviado).' );
	check( 'partial' === Log::entries()[0]['result'] && 'woocommerce_rest_product_invalid_id' === Log::entries()[0]['code'], 'update parcial en la bitácora.' );
	$err( $run( array( 'action' => 'update_products', 'items' => array( array( 'product_id' => 404, 'weight' => 9 ) ) ) ), 'woocommerce_rest_product_invalid_id', 'update sin ningún elemento aplicado: error.' );
	check( 'error' === Log::entries()[0]['result'], 'update fallido: error en la bitácora.' );
	$GLOBALS['sk_responses']['POST /wc/v3/products/batch'] = array( 403, array( 'code' => 'woocommerce_rest_cannot_batch', 'message' => 'No puedes.' ), array() );
	$err( $run( array( 'action' => 'update_products', 'items' => array( array( 'product_id' => 11, 'weight' => 1 ) ) ) ), 'woocommerce_rest_cannot_batch', 'update: un 403 de WooCommerce vuelve con su código.' );
	check( false === strpos( json_encode( Log::entries() ), '1.5' ) && false === strpos( json_encode( Log::entries() ), 'chica' ), 'Bitácora sin valores.' );

	// =================================================================================================================
	// quote: el método del plugin, su caché y nada del carrito de nadie.
	sk_reset( array( 'caps' => $manager, 'user' => 7 ) );
	$sk_store();
	$previous_cart    = new stdClass();
	WC()->cart        = $previous_cart;
	WC()->session     = null;
	$quote_input      = array( 'action' => 'quote', 'address' => array( 'postcode' => '06700', 'state' => 'DF', 'city' => 'CDMX' ), 'cart' => array( array( 'product_id' => 10, 'quantity' => 2 ), array( 'product_id' => 20, 'variation_id' => 22 ), array( 'product_id' => 12 ) ) );
	$quote            = $ok( $seen( $run( $quote_input ) ), 'quote' );
	check( $previous_cart === WC()->cart && null === WC()->session, 'quote: WC()->cart y WC()->session vuelven a lo que eran.' );
	check( ! isset( $GLOBALS['sk']['wc_cart_constructed'] ), 'quote: nunca se construye un WC_Cart real (que cargaría el carrito persistente del usuario).' );
	check( 1 === $GLOBALS['sk_plugin']['http'] && 2 === $quote['data']['total'] && 'dhl' === $quote['data']['rates'][0]['provider'] && 99.0 === $quote['data']['rates'][0]['cost'] && 'Express' === $quote['data']['rates'][0]['service'] && 'MXN' === $quote['data']['rates'][0]['currency'], 'quote: tarifas del plugin, de la más barata a la más cara: ' . json_encode( $quote['data']['rates'] ) );
	check( false === $quote['data']['from_cache'] && 9 === $quote['data']['method']['instance_id'] && true === $quote['data']['zone']['matches'] && 3 === $quote['data']['zone']['id'], 'quote: la instancia de la zona que usa el carrito.' );
	check( array( array( 'id' => 10, 'quantity' => 2, 'weight' => 1.2, 'length' => 20.0 ), array( 'id' => 22, 'quantity' => 1, 'weight' => 2.5, 'length' => 30.0 ) ) === $GLOBALS['sk_plugin']['sent']['items'] && 400.0 === $GLOBALS['sk_plugin']['sent']['total_price'], 'quote: el plugin leyó los artículos (sin el virtual, con la herencia de la variación) y el total del carrito (con el virtual, como un carrito real): ' . json_encode( $GLOBALS['sk_plugin']['sent'] ) );
	check( 'MX' === $GLOBALS['sk']['matched'][0]['country'] && '06700' === $GLOBALS['sk']['matched'][0]['postcode'], 'quote: país por defecto, el de la tienda.' );
	check( 1 === count( $quote['warnings'] ) && false !== strpos( $quote['warnings'][0], 'virtual o descargable' ), 'quote: avisa del producto que el plugin no cotiza.' );
	$transient = Skydropx::QUOTE_CACHE . '7';
	check( isset( $GLOBALS['sk']['transients'][ $transient ] ) && 120 === $GLOBALS['sk']['transient_ttl'][ $transient ], 'quote: la caché del plugin se guarda 2 minutos por usuario.' );
	$again = $ok( $run( $quote_input ), 'quote con caché' );
	check( 1 === $GLOBALS['sk_plugin']['http'] && true === $again['data']['from_cache'] && $quote['data']['rates'] === $again['data']['rates'], 'quote: la segunda vez, de la caché del plugin (sin otra petición).' );
	$fresh = $ok( $run( $quote_input + array( 'cache' => false ) ), 'quote sin caché' );
	check( 2 === $GLOBALS['sk_plugin']['http'] && false === $fresh['data']['from_cache'], 'quote: cache:false vuelve a cotizar.' );
	// M4 · La huella del plugin no cuenta el peso: si cambió (p. ej. tras update_products), la caché guardada no vale.
	$GLOBALS['sk_products'][10]->props['weight'] = '1.4';
	$heavier = $ok( $run( $quote_input ), 'quote con otro peso' );
	check( 3 === $GLOBALS['sk_plugin']['http'] && false === $heavier['data']['from_cache'] && 1.4 === $GLOBALS['sk_plugin']['sent']['items'][0]['weight'], 'quote: con otro peso no sale de la caché (M4).' );
	$GLOBALS['sk_products'][10]->props['weight'] = '1.2';
	$GLOBALS['sk']['options']['woocommerce_tax_display_cart'] = 'incl';
	$ok( $run( array( 'action' => 'quote', 'cache' => false, 'address' => array( 'postcode' => '06700' ), 'cart' => array( array( 'product_id' => 10 ) ) ) ), 'quote con impuestos incluidos' );
	check( 116.0 === $GLOBALS['sk_plugin']['sent']['total_price'], 'quote: con precios con impuestos, el total que manda el plugin los incluye.' );
	$other = $ok( $run( array( 'action' => 'quote', 'address' => array( 'postcode' => '10001', 'country' => 'us' ), 'cart' => array( array( 'product_id' => 11 ) ) ) ), 'quote fuera de la zona' );
	check( false === $other['data']['zone']['matches'] && 'Local' === $other['data']['zone']['name'] && false !== strpos( implode( ' ', $other['warnings'] ), 'no vería estas tarifas' ) && false !== strpos( implode( ' ', $other['warnings'] ), 'cero' ), 'quote: avisa si la zona del cliente no tiene Skydropx y del producto sin peso.' );
	foreach ( array(
		array( array( 'cart' => array( array( 'product_id' => 20 ) ) ), 'digitalisimo_woo_mcp_skydropx_cart', 'variable sin variación' ),
		array( array( 'cart' => array( array( 'product_id' => 16 ) ) ), 'digitalisimo_woo_mcp_skydropx_cart', 'borrador' ),
		array( array( 'cart' => array( array( 'product_id' => 30, 'variation_id' => 32 ) ) ), 'digitalisimo_woo_mcp_skydropx_cart', 'variación desactivada' ),
		array( array( 'cart' => array( array( 'product_id' => 20, 'variation_id' => 31 ) ) ), 'digitalisimo_woo_mcp_skydropx_cart', 'variación de otro padre' ),
		array( array( 'cart' => array( array( 'product_id' => 10, 'quantity' => 0 ) ) ), 'digitalisimo_woo_mcp_input', 'cantidad 0' ),
		array( array( 'address' => array( 'city' => 'CDMX' ) ), 'digitalisimo_woo_mcp_input', 'sin código postal' ),
		array( array( 'address' => array( 'postcode' => '<b>1</b>' ) ), 'digitalisimo_woo_mcp_input', 'código postal con HTML' ),
		array( array( 'address' => array( 'postcode' => '06700', 'country' => 'MEX' ) ), 'digitalisimo_woo_mcp_input', 'país de 3 letras' ),
		array( array( 'cart' => array_fill( 0, 51, array( 'product_id' => 10 ) ) ), 'digitalisimo_woo_mcp_input', '51 líneas' ),
	) as $case ) {
		$err( $run( array_merge( $quote_input, $case[0] ) ), $case[1], 'quote rechaza: ' . $case[2] );
	}
	$GLOBALS['sk_plugin']['enabled'] = false;
	$err( $run( $quote_input ), 'digitalisimo_woo_mcp_skydropx_quotation_off', 'quote: cotización desactivada.' );
	$GLOBALS['sk_plugin']['enabled'] = true;
	$GLOBALS['sk_plugin']['url']     = null;
	$err( $run( $quote_input ), 'digitalisimo_woo_mcp_skydropx_quotation_url', 'quote: sin URL de la plataforma.' );
	$GLOBALS['sk_plugin']['url']                            = SK_QUOTE_URL;
	$GLOBALS['sk']['options']['woocommerce_store_postcode'] = '';
	$err( $run( $quote_input ), 'digitalisimo_woo_mcp_skydropx_origin', 'quote: sin código postal de origen.' );
	$GLOBALS['sk']['options']['woocommerce_store_postcode'] = '44100';
	$GLOBALS['sk_plugin']['rates']                          = array();
	$empty = $ok( $run( array_merge( $quote_input, array( 'cache' => false, 'address' => array( 'postcode' => '99999' ) ) ) ), 'quote sin tarifas' );
	check( 0 === $empty['data']['total'] && false !== strpos( implode( ' ', $empty['warnings'] ), 'no devolvió tarifas' ), 'quote: sin tarifas, lo dice.' );
	// B5 · La misma cotización otra vez en la misma petición (el plugin no repite la llamada): valen las tarifas de la primera.
	$again = $ok( $run( $quote_input + array( 'cache' => false ) ), 'quote repetida en la petición' );
	check( 2 === $again['data']['total'] && true === $again['data']['from_cache'] && false !== strpos( implode( ' ', $again['warnings'] ), 'ya se hizo en esta petición' ), 'quote: repetida en la misma petición, no «sin tarifas».' );
	$GLOBALS['sk_plugin']['rates'] = null;
	check( 0 === count( Log::entries() ), 'quote: es lectura (sin bitácora).' );

	// =================================================================================================================
	// shipments y get_shipment: la REST de pedidos y las metas del plugin.
	sk_reset( array( 'caps' => $manager ) );
	$sk_store();
	$GLOBALS['sk_responses']['GET /wc/v3/orders'] = array(
		200,
		array(
			array( 'id' => 50, 'number' => '50', 'status' => 'processing', 'date_created' => '2026-11-10T10:00:00', 'shipping_lines' => array( array( 'id' => 501, 'method_id' => 'skydropx', 'method_title' => 'Express', 'total' => '99.00', 'meta_data' => array( array( 'id' => 1, 'key' => 'Tracking Number', 'value' => 'GUIA123' ), array( 'id' => 2, 'key' => 'Tracking Company', 'value' => 'FedEx' ), array( 'id' => 3, 'key' => 'Tracking URL', 'value' => 'https://rastreo.test/GUIA123' ), array( 'id' => 4, 'key' => 'provider', 'value' => 'fedex' ) ) ) ) ),
			array( 'id' => 51, 'number' => '51', 'status' => 'processing', 'date_created' => '2026-11-11T10:00:00', 'shipping_lines' => array( array( 'id' => 511, 'method_id' => 'skydropx', 'method_title' => 'Estándar', 'total' => '189.50', 'meta_data' => array() ) ) ),
			array( 'id' => 52, 'number' => '52', 'status' => 'on-hold', 'date_created' => '2026-11-12T10:00:00', 'shipping_lines' => array() ),
		),
		array( 'X-WP-Total' => '23', 'X-WP-TotalPages' => '3' ),
	);
	$list = $ok( $seen( $run( array( 'action' => 'shipments', 'statuses' => array( 'processing', 'wc-on-hold' ), 'after' => '2026-11-01', 'before' => '2026-11-15T12:30' ) ) ), 'shipments' );
	check( array( 'GET', '/wc/v3/orders', array( 'page' => 1, 'per_page' => 10, '_fields' => 'id,number,status,date_created,shipping_lines', 'status' => array( 'processing', 'on-hold' ), 'after' => '2026-11-01T00:00:00', 'before' => '2026-11-15T12:30:00' ), null ) === $GLOBALS['sk_rest'][0], 'shipments: la REST de pedidos con estados y fechas normalizados: ' . json_encode( $GLOBALS['sk_rest'][0] ) );
	check( 3 === count( $list['data'] ) && 23 === $list['total'] && 3 === $list['total_pages'] && 1 === $list['summary']['with_tracking'], 'shipments: filas y totales.' );
	check( array( 'number' => 'GUIA123', 'company' => 'FedEx', 'url' => 'https://rastreo.test/GUIA123' ) === $list['data'][0]['tracking'] && true === $list['data'][0]['label'] && true === $list['data'][0]['has_tracking'] && false === $list['data'][1]['has_tracking'] && null === $list['data'][1]['tracking'], 'shipments: rastreo de las metas del plugin.' );
	$without = $ok( $run( array( 'action' => 'shipments', 'tracking_filter' => 'without' ) ), 'shipments sin rastreo' );
	check( array( 51, 52 ) === array_column( $without['data'], 'id' ) && isset( $without['notice'] ), 'shipments: tracking_filter dentro de la página, con aviso.' );
	$with = $ok( $run( array( 'action' => 'shipments', 'tracking_filter' => 'with' ) ), 'shipments con rastreo' );
	check( array( 50 ) === array_column( $with['data'], 'id' ), 'shipments: sólo con rastreo.' );
	foreach ( array( array( 'after' => '2026-13-01' ), array( 'before' => 'ayer' ), array( 'statuses' => array( 'Pagado!' ) ), array( 'tracking_filter' => 'todos' ) ) as $bad ) {
		$err( $run( array( 'action' => 'shipments' ) + $bad ), 'digitalisimo_woo_mcp_input', 'shipments rechaza ' . json_encode( $bad ) );
	}
	$GLOBALS['sk_responses']['GET /wc/v3/orders/50'] = array( 200, array( 'id' => 50, 'number' => '50', 'status' => 'processing', 'shipping' => array( 'first_name' => 'Ana', 'address_1' => 'Calle 1', 'city' => 'CDMX', 'state' => 'DF', 'postcode' => '06700', 'country' => 'MX' ), 'shipping_lines' => $GLOBALS['sk_responses']['GET /wc/v3/orders'][1][0]['shipping_lines'] ), array() );
	$one = $ok( $seen( $run( array( 'action' => 'get_shipment', 'order_id' => 50 ) ) ), 'get_shipment' );
	check( true === $one['data']['label'] && 'GUIA123' === $one['data']['tracking']['number'] && array( 'city' => 'CDMX', 'state' => 'DF', 'postcode' => '06700', 'country' => 'MX' ) === $one['data']['destination'] && false !== strpos( $one['data']['edit_url'], 'id=50' ), 'get_shipment: rastreo, guía (del plugin), destino sin nombre ni calle y enlace al pedido.' );
	$GLOBALS['sk_responses']['GET /wc/v3/orders/999'] = array( 404, array( 'code' => 'woocommerce_rest_shop_order_invalid_id', 'message' => 'ID no válido.' ), array() );
	$err( $run( array( 'action' => 'get_shipment', 'order_id' => 999 ) ), 'woocommerce_rest_shop_order_invalid_id', 'get_shipment: el 404 de WooCommerce.' );
	// B8/M3 · El rastreo es el de la primera línea con número; uno registrado a mano es «manual», no una guía de Skydropx.
	$GLOBALS['sk_responses']['GET /wc/v3/orders'] = array( 200, array( array( 'id' => 53, 'shipping_lines' => array( array( 'id' => 531, 'meta_data' => array( array( 'key' => 'Tracking Company', 'value' => 'DHL' ) ) ), array( 'id' => 532, 'meta_data' => array( array( 'key' => 'Tracking Number', 'value' => 'M-1' ), array( 'key' => 'Tracking Company', 'value' => 'Estafeta' ), array( 'key' => 'Tracking URL', 'value' => 'https://r.test/M-1' ), array( 'key' => Skydropx_Tool::MANUAL_META, 'value' => 'M-1' ) ) ) ) ) ), array() );
	$manual_row = $ok( $run( array( 'action' => 'shipments' ) ), 'shipments con rastreo manual' )['data'][0];
	check( true === $manual_row['has_tracking'] && 'M-1' === $manual_row['tracking']['number'] && 'manual' === $manual_row['label'] && true === $manual_row['shipping_lines'][1]['manual'], 'shipments: línea con número y rastreo manual marcado: ' . json_encode( $manual_row ) );
	// A1 · Leer un pedido exige el permiso sobre ese pedido (y su filtro), como la REST.
	add_filter( 'woocommerce_rest_check_permissions', static function ( $permission, $context, $id ) {
		return 50 === (int) $id ? false : $permission;
	} );
	$err( $run( array( 'action' => 'get_shipment', 'order_id' => 50 ) ), 'woocommerce_rest_cannot_view', 'get_shipment: el filtro woocommerce_rest_check_permissions niega ese pedido.' );
	$err( $run( array( 'action' => 'label', 'order_id' => 50 ) ), 'woocommerce_rest_cannot_view', 'label: el filtro niega ese pedido.' );
	unset( $GLOBALS['sk']['hooks']['woocommerce_rest_check_permissions'] );
	check( in_array( array( 'read', 50 ), $GLOBALS['sk']['order_checks'], true ), 'get_shipment y label comprueban el pedido con contexto read.' );

	// =================================================================================================================
	// set_tracking: las metas del plugin con el CRUD (sin recalcular totales), estado y nota por la REST.
	sk_reset( array( 'caps' => $manager, 'options' => array_merge( $GLOBALS['sk']['options'], $write ) ) );
	$sk_store();
	$updates = 0;
	add_action(
		'woocommerce_update_order',
		static function () use ( &$updates ) {
			++$updates;
		}
	);
	$GLOBALS['sk_responses']['PUT /wc/v3/orders/51']        = array( 200, array( 'id' => 51, 'status' => 'completed' ), array() );
	$GLOBALS['sk_responses']['POST /wc/v3/orders/51/notes'] = array( 201, array( 'id' => 77, 'note' => 'x' ), array() );
	$set = $ok( $seen( $run( array( 'action' => 'set_tracking', 'order_id' => 51, 'tracking' => array( 'number' => ' 7788-99 ', 'company' => 'Estafeta', 'url' => 'https://rastreo.test/778899' ), 'order_status' => 'wc-completed', 'customer_note' => 'Tu pedido va en camino.' ) ) ), 'set_tracking' );
	$line = $GLOBALS['sk_orders'][51]->lines[511];
	check( array( 'Tracking Number' => '7788-99', 'Tracking Company' => 'Estafeta', 'Tracking URL' => 'https://rastreo.test/778899', Skydropx_Tool::MANUAL_META => '7788-99' ) === $line->meta && array() === $GLOBALS['sk_orders'][51]->lines[512]->meta, 'set_tracking: las tres metas del plugin en la primera línea de envío.' );
	check( 1 === $GLOBALS['sk_orders'][51]->saves && 1 === $updates, 'set_tracking: un save() del pedido que dispara woocommerce_update_order (webhooks).' );
	check( array( array( 'PUT', '/wc/v3/orders/51', array(), array( 'status' => 'completed' ) ), array( 'POST', '/wc/v3/orders/51/notes', array(), array( 'note' => 'Tu pedido va en camino.', 'customer_note' => true ) ) ) === $GLOBALS['sk_rest'], 'set_tracking: estado y nota al cliente por la REST, nunca shipping_lines (recalcularía los totales): ' . json_encode( $GLOBALS['sk_rest'] ) );
	check( true === $set['ok'] && 511 === $set['data']['shipping_line_id'] && 'manual' === $set['data']['label'] && false !== strpos( $set['notice'], 'Guía generada' ) && 'completed' === $set['data']['order_status'] && 77 === $set['data']['note_id'] && false === $set['data']['created_line'], 'set_tracking: resultado.' );
	$entry = Log::entries()[0];
	check( 'ok' === $entry['result'] && 'set_tracking' === $entry['action'] && 51 === $entry['id'] && 'order' === $entry['resource'], 'set_tracking en la bitácora.' );
	check( false === strpos( json_encode( Log::entries() ), '7788' ) && false === strpos( json_encode( Log::entries() ), 'Estafeta' ) && false === strpos( json_encode( Log::entries() ), 'camino' ), 'Bitácora sin número, paquetería ni nota.' );
	// Corregir sólo el número: lo demás se conserva; sin estado ni nota, nada a la REST.
	$GLOBALS['sk_rest'] = array();
	$ok( $run( array( 'action' => 'set_tracking', 'order_id' => 50, 'tracking' => array( 'number' => 'GUIA124' ) ) ), 'corregir el número' );
	check( array( 'Tracking Number' => 'GUIA124', 'Tracking Company' => 'FedEx', 'Tracking URL' => 'https://rastreo.test/GUIA123', Skydropx_Tool::MANUAL_META => 'GUIA124' ) === $GLOBALS['sk_orders'][50]->lines[501]->meta && array() === $GLOBALS['sk_rest'], 'set_tracking: corrige un campo y conserva los otros.' );
	$ok( $run( array( 'action' => 'set_tracking', 'order_id' => 50, 'tracking' => array( 'number' => 'GUIA124' ), 'order_status' => 'processing' ) ), 'mismo estado' );
	check( array() === $GLOBALS['sk_rest'], 'set_tracking: un estado igual al actual no llama a WooCommerce.' );
	$ok( $run( array( 'action' => 'set_tracking', 'order_id' => 51, 'shipping_line_id' => 512, 'tracking' => array( 'number' => 'B2', 'company' => 'DHL', 'url' => 'http://r.test/B2' ) ) ), 'otra línea' );
	check( 'B2' === $GLOBALS['sk_orders'][51]->lines[512]->meta['Tracking Number'] && '7788-99' === $GLOBALS['sk_orders'][51]->lines[511]->meta['Tracking Number'], 'set_tracking: shipping_line_id elige la línea.' );
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 51, 'shipping_line_id' => 999, 'tracking' => array( 'number' => 'C' ) ) ), 'digitalisimo_woo_mcp_skydropx_line', 'set_tracking: línea que no es del pedido.' );
	$saves = $GLOBALS['sk_orders'][52]->saves;
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 50, 'tracking' => array( 'number' => 'C3', 'company' => '' ) ) ), 'digitalisimo_woo_mcp_value', 'set_tracking: paquetería vacía.' );
	$GLOBALS['sk_orders'][56] = new WC_Order( 56, array( new WC_Order_Item_Shipping( 561 ) ) );
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 56, 'tracking' => array( 'number' => 'C4', 'url' => 'https://r.test/C4' ) ) ), 'digitalisimo_woo_mcp_skydropx_tracking', 'set_tracking: incompleto (sin paquetería) no escribe nada.' );
	check( 0 === $GLOBALS['sk_orders'][56]->saves && array() === $GLOBALS['sk_orders'][56]->lines[561]->meta, 'set_tracking incompleto: nada guardado.' );
	// M2 · Un pedido sin línea de envío falla con un mensaje claro; sólo con create_shipping_line:true se añade una.
	$full = array( 'number' => 'C3', 'company' => 'Paquetexpress', 'url' => 'https://r.test/C3' );
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 52, 'tracking' => $full ) ), 'digitalisimo_woo_mcp_skydropx_no_line', 'set_tracking: sin línea de envío, no la crea por su cuenta.' );
	check( $saves === $GLOBALS['sk_orders'][52]->saves && array() === $GLOBALS['sk_orders'][52]->lines, 'set_tracking sin línea: nada guardado.' );
	$new     = $ok( $run( array( 'action' => 'set_tracking', 'order_id' => 52, 'tracking' => $full, 'create_shipping_line' => true ) ), 'pedido sin línea de envío, con create_shipping_line' );
	$created = $GLOBALS['sk_orders'][52]->lines[ $new['data']['shipping_line_id'] ];
	check( true === $new['data']['created_line'] && 0 === $created->total && 'digitalisimo_manual' === $created->method_id && 'C3' === $created->meta['Tracking Number'] && false !== strpos( $new['notice'], 'el cliente verá' ), 'set_tracking: con create_shipping_line, una línea sin costo y el aviso de lo que verá el cliente.' );
	// M3 · Corregir paquetería o URL de una guía de Skydropx (mismo número) no la vuelve manual.
	$GLOBALS['sk_orders'][54] = new WC_Order( 54, array( new WC_Order_Item_Shipping( 541, array( 'Tracking Number' => 'SKY-1', 'Tracking Company' => 'FedEx', 'Tracking URL' => 'https://r.test/SKY-1' ) ) ) );
	$fix                      = $ok( $run( array( 'action' => 'set_tracking', 'order_id' => 54, 'tracking' => array( 'company' => 'FedEx Express' ) ) ), 'corregir la paquetería de una guía de Skydropx' );
	check( true === $fix['data']['label'] && ! isset( $GLOBALS['sk_orders'][54]->lines[541]->meta[ Skydropx_Tool::MANUAL_META ] ), 'set_tracking: corregir la paquetería de una guía de Skydropx no la marca como manual.' );
	// A1 · Escribir exige el permiso sobre ese pedido: sin edit_others_shop_orders, sólo los propios; y el filtro.
	$GLOBALS['sk']['caps']       = array( 'edit_shop_orders', 'read_private_shop_orders' );
	$GLOBALS['sk']['own_orders'] = array( 54 );
	$before                      = $GLOBALS['sk_orders'][51]->lines[511]->meta;
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 51, 'tracking' => array( 'number' => 'AJENO' ) ) ), 'woocommerce_rest_cannot_edit', 'set_tracking: un pedido ajeno sin edit_others_shop_orders.' );
	check( $before === $GLOBALS['sk_orders'][51]->lines[511]->meta && 'denied' === Log::entries()[0]['result'] && 'forbidden' === Log::entries()[0]['code'], 'set_tracking ajeno: nada escrito y «denied» en la bitácora.' );
	$err( $run( array( 'action' => 'resync', 'order_id' => 51 ) ), 'woocommerce_rest_cannot_edit', 'resync: un pedido ajeno tampoco.' );
	$ok( $run( array( 'action' => 'resync', 'order_id' => 54 ) ), 'resync: el pedido propio sí' );
	$GLOBALS['sk']['caps'] = $manager;
	unset( $GLOBALS['sk']['own_orders'] );
	add_filter( 'woocommerce_rest_check_permissions', static function ( $permission, $context, $id ) {
		return 'edit' === $context && 50 === (int) $id ? false : $permission;
	} );
	$saves = $GLOBALS['sk_orders'][50]->saves;
	$err( $run( array( 'action' => 'resync', 'order_id' => 50 ) ), 'woocommerce_rest_cannot_edit', 'resync: el filtro woocommerce_rest_check_permissions lo niega.' );
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 50, 'tracking' => array( 'number' => 'X9' ) ) ), 'woocommerce_rest_cannot_edit', 'set_tracking: el filtro lo niega.' );
	check( $saves === $GLOBALS['sk_orders'][50]->saves, 'Con el filtro en contra, nada guardado.' );
	unset( $GLOBALS['sk']['hooks']['woocommerce_rest_check_permissions'] );
	foreach ( array(
		array( 'url' => 'javascript:alert(1)' ),
		array( 'url' => 'ftp://r.test/x' ),
		array( 'url' => 'https://r.test/x y' ),
		array( 'number' => '<script>' ),
		array( 'number' => '' ),
		array( 'company' => "Fed\nEx" ),
		array( 'firma' => 'x' ),
	) as $bad ) {
		$err( $run( array( 'action' => 'set_tracking', 'order_id' => 50, 'tracking' => $bad ) ), isset( $bad['firma'] ) ? 'digitalisimo_woo_mcp_input' : 'digitalisimo_woo_mcp_value', 'set_tracking rechaza ' . json_encode( $bad ) );
	}
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 50 ) ), 'digitalisimo_woo_mcp_input', 'set_tracking sin tracking.' );
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 50, 'tracking' => array( 'number' => 'A' ), 'customer_note' => str_repeat( 'x', 1001 ) ) ), 'digitalisimo_woo_mcp_value', 'set_tracking: nota de más de 1000.' );
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 404, 'tracking' => array( 'number' => 'A' ) ) ), 'woocommerce_rest_shop_order_invalid_id', 'set_tracking: pedido inexistente.' );
	$GLOBALS['sk_orders'][60]       = new WC_Order( 60 );
	$GLOBALS['sk_orders'][60]->type = 'shop_order_refund';
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 60, 'tracking' => array( 'number' => 'A', 'company' => 'B', 'url' => 'https://r.test/a' ) ) ), 'woocommerce_rest_shop_order_invalid_id', 'set_tracking: un reembolso no es un pedido.' );
	// B6 · Un estado que no existe se rechaza antes de escribir nada.
	$err( $run( array( 'action' => 'set_tracking', 'order_id' => 50, 'tracking' => array( 'number' => 'GUIA126' ), 'order_status' => 'enviado' ) ), 'digitalisimo_woo_mcp_value', 'set_tracking: estado inexistente, rechazado antes.' );
	check( 'GUIA124' === $GLOBALS['sk_orders'][50]->lines[501]->meta['Tracking Number'], 'set_tracking con estado inexistente: el rastreo no cambió.' );
	// B6 · Un reintento no le repite al cliente la misma nota (ni el correo).
	$GLOBALS['sk_notes'][51] = 'Tu pedido va en camino.';
	$GLOBALS['sk_rest']      = array();
	$retry                   = $ok( $run( array( 'action' => 'set_tracking', 'order_id' => 51, 'tracking' => array( 'number' => '7788-99' ), 'customer_note' => 'Tu pedido va en camino.' ) ), 'reintento con la misma nota' );
	check( true === $retry['data']['note_skipped'] && array() === $GLOBALS['sk_rest'], 'set_tracking: la misma nota no se vuelve a mandar.' );
	// Estado válido que WooCommerce rechaza: el rastreo queda y el resultado es parcial.
	$GLOBALS['sk_responses']['PUT /wc/v3/orders/50'] = array( 400, array( 'code' => 'rest_invalid_param', 'message' => 'Estado no válido.' ), array() );
	$part = $seen( $run( array( 'action' => 'set_tracking', 'order_id' => 50, 'tracking' => array( 'number' => 'GUIA125' ), 'order_status' => 'cancelled' ) ) );
	check( ! is_wp_error( $part ) && false === $part['ok'] && true === $part['partial'] && 'rest_invalid_param' === $part['errors']['order_status']['code'] && 'GUIA125' === $GLOBALS['sk_orders'][50]->lines[501]->meta['Tracking Number'], 'set_tracking: estado rechazado, rastreo guardado, resultado parcial.' );
	check( 'partial' === Log::entries()[0]['result'] && 'rest_invalid_param' === Log::entries()[0]['code'], 'set_tracking parcial en la bitácora.' );

	// =================================================================================================================
	// label: un enlace propio que lleva al endpoint del plugin con su nonce; nada sensible al modelo.
	$sk_store();
	$label = $ok( $seen( $run( array( 'action' => 'label', 'order_id' => 50 ) ) ), 'label' );
	parse_str( (string) parse_url( $label['data']['url'], PHP_URL_QUERY ), $q );
	$sign  = static function ( int $order, int $user, int $exp ): string {
		return substr( wp_hash( 'skydropx-label|' . ( $GLOBALS['sk_sign_blog'] ?? 1 ) . '|' . $order . '|' . $user . '|' . $exp, 'nonce' ), 0, 20 );
	};
	check( 0 === strpos( $label['data']['url'], 'https://tienda.test/wp-admin/admin-post.php?' ) && Mcp::SKYDROPX_LABEL_ACTION === $q['action'] && '50' === $q['order_id'] && abs( (int) $q['exp'] - ( time() + 3600 ) ) < 5 && $sign( 50, 7, (int) $q['exp'] ) === $q['sig'] && false !== strpos( $label['data']['order_url'], 'id=50' ), 'label: enlace propio firmado para este usuario, de una hora (M1): ' . $label['data']['url'] );
	check( false === strpos( json_encode( $label ), 'skydropx_print_label' ), 'label: el enlace del plugin (con su nonce) no sale al modelo.' );
	$nolabel = $err( $run( array( 'action' => 'label', 'order_id' => 51 ) ), 'digitalisimo_woo_mcp_skydropx_no_label', 'label: sin guía completa, error que dice por qué.' );
	check( 404 === $nolabel->get_error_data()['status'], 'label sin guía: 404.' );
	$err( $run( array( 'action' => 'label', 'order_id' => 404 ) ), 'woocommerce_rest_shop_order_invalid_id', 'label: pedido inexistente.' );
	// M3 · Un rastreo registrado a mano no tiene PDF de Skydropx: label lo dice en lugar de dar un enlace que falla.
	$GLOBALS['sk_orders'][55] = new WC_Order( 55, array( new WC_Order_Item_Shipping( 551, array( 'Tracking Number' => 'M-9', 'Tracking Company' => 'DHL', 'Tracking URL' => 'https://r.test/M-9', Skydropx_Tool::MANUAL_META => 'M-9' ) ) ) );
	$manual = $err( $run( array( 'action' => 'label', 'order_id' => 55 ) ), 'digitalisimo_woo_mcp_skydropx_manual', 'label: rastreo manual, sin guía de Skydropx.' );
	check( 409 === $manual->get_error_data()['status'] && false !== strpos( $manual->get_error_message(), 'Guía generada' ), 'label manual: 409 y explica la caja del plugin.' );
	// La redirección en el navegador: sólo con la firma de este usuario y vigente; si no, una página con un botón.
	$open = static function ( array $state, int $order, array $extra = array() ) {
		sk_reset( $state );
		$_GET = array_merge( array( 'action' => Mcp::SKYDROPX_LABEL_ACTION, 'order_id' => (string) $order ), $extra );
		try {
			Skydropx::handle_label();
		} catch ( Sk_Exit $exit ) {
			return $exit;
		} finally {
			$_GET = array();
		}
		return null;
	};
	$exp     = time() + 600;
	$valid   = array( 'exp' => (string) $exp, 'sig' => $sign( 50, 3, $exp ) );
	$plugin  = 'https://tienda.test/wp-admin/admin-post.php?action=skydropx_print_label&order_id=50&_wpnonce=' . substr( md5( 'skydropx_print_label:50|3' ), 0, 10 );
	$exit    = $open( array( 'caps' => $manager, 'user' => 3 ), 50, $valid );
	check( $exit && 'redirect' === $exit->kind && $plugin === $exit->value, 'handle_label: con la firma de este usuario, redirige al endpoint del plugin con el nonce de su sesión (sin &amp;): ' . ( $exit ? $exit->value : '' ) );
	foreach ( array(
		'sin firma'           => array(),
		'de otro usuario'     => array( 'exp' => (string) $exp, 'sig' => $sign( 50, 4, $exp ) ),
		'caducada'            => array( 'exp' => (string) ( time() - 1 ), 'sig' => $sign( 50, 3, time() - 1 ) ),
		'de otro pedido'      => array( 'exp' => (string) $exp, 'sig' => $sign( 51, 3, $exp ) ),
		'de otro sitio de la red' => ( function () use ( $sign, $exp ) { $GLOBALS['sk_sign_blog'] = 2; $sig = $sign( 50, 3, $exp ); unset( $GLOBALS['sk_sign_blog'] ); return array( 'exp' => (string) $exp, 'sig' => $sig ); } )(),
	) as $case => $extra ) {
		$exit = $open( array( 'caps' => $manager, 'user' => 3 ), 50, $extra );
		check( $exit && 'die' === $exit->kind && 200 === $exit->getCode() && false !== strpos( $exit->value, 'Descargar la guía' ) && false !== strpos( $exit->value, $plugin ), "handle_label $case: no redirige solo; muestra un botón (un GET ajeno no ejecuta nada)." );
	}
	$exit = $open( array( 'caps' => $manager, 'user' => 3, 'options' => array( 'elementor_mcp_enabled' => false ) ), 50, $valid );
	check( $exit && 'die' === $exit->kind && 404 === $exit->getCode(), 'handle_label: con el MCP del sitio apagado no hace nada (M1).' );
	$exit = $open( array( 'caps' => $manager, 'user' => 3, 'options' => array( 'elementor_mcp_enabled' => true, Settings::OPTION => array( 'mode' => 'off', 'own' => array( 'mode' ) ) ) ), 50, $valid );
	check( $exit && 'die' === $exit->kind && 404 === $exit->getCode(), 'handle_label: con «WooCommerce en el MCP» apagado tampoco.' );
	$exit = $open( array( 'caps' => array( 'read' ) ), 50, $valid );
	check( $exit && 'die' === $exit->kind && 403 === $exit->getCode(), 'handle_label: sin permiso sobre pedidos, 403.' );
	$exit = $open( array( 'caps' => array( 'read_private_shop_orders' ), 'user' => 3 ), 50, $valid );
	check( $exit && 'die' === $exit->kind && 403 === $exit->getCode(), 'handle_label: quien lee pedidos pero no los edita, 403 (como el plugin).' );
	$exit = $open( array( 'caps' => array( 'edit_shop_orders' ), 'user' => 3 ), 50, $valid );
	check( $exit && 'die' === $exit->kind && 403 === $exit->getCode(), 'handle_label: sin permiso sobre ese pedido (como la REST), 403.' );
	$exit = $open( array( 'logged_in' => false ), 50 );
	check( $exit && 'login' === $exit->kind, 'handle_label: sin sesión, al acceso.' );
	$exit = $open( array( 'caps' => $manager ), 51 );
	check( $exit && 'die' === $exit->kind && 404 === $exit->getCode() && false !== strpos( $exit->value, 'id=51' ), 'handle_label: sin guía, 404 con enlace al pedido.' );
	$exit = $open( array( 'caps' => $manager ), 55 );
	check( $exit && 'die' === $exit->kind && 404 === $exit->getCode() && false !== strpos( $exit->value, 'a mano' ), 'handle_label: rastreo manual, sin PDF.' );
	$exit = $open( array( 'caps' => $manager ), 999 );
	check( $exit && 'die' === $exit->kind && 404 === $exit->getCode(), 'handle_label: pedido inexistente.' );

	// =================================================================================================================
	// resync.
	sk_reset( array( 'caps' => $manager, 'options' => array_merge( $GLOBALS['sk']['options'], $write ) ) );
	$sk_store();
	$updates = 0;
	add_action(
		'woocommerce_update_order',
		static function () use ( &$updates ) {
			++$updates;
		}
	);
	$before = time();
	$sync   = $ok( $seen( $run( array( 'action' => 'resync', 'order_id' => 50 ) ) ), 'resync' );
	$order  = $GLOBALS['sk_orders'][50];
	check( 1 === $order->saves && 1 === $updates && $order->modified && $order->modified->getTimestamp() >= $before, 'resync: fecha de modificación al día y un save() que dispara woocommerce_update_order.' );
	check( array() === $GLOBALS['sk_rest'] && ! isset( $sync['warnings'] ) && 2 === $sync['data']['webhooks']['active'], 'resync: con el webhook order.updated activo, sin avisos.' );
	check( 'ok' === Log::entries()[0]['result'] && 'resync' === Log::entries()[0]['action'] && 50 === Log::entries()[0]['id'], 'resync en la bitácora.' );
	unset( $GLOBALS['sk_webhooks'][2] );
	$sync = $ok( $run( array( 'action' => 'resync', 'order_id' => 50 ) ), 'resync sin webhook' );
	check( isset( $sync['warnings'][0] ) && false !== strpos( $sync['warnings'][0], 'order.updated' ), 'resync: sin webhook order.updated, lo dice.' );
	$err( $run( array( 'action' => 'resync', 'order_id' => 404 ) ), 'woocommerce_rest_shop_order_invalid_id', 'resync: pedido inexistente.' );
	check( 'error' === Log::entries()[0]['result'], 'resync fallido en la bitácora.' );

	// =================================================================================================================
	// Ningún secreto y un plugin de otra versión.
	$no_secrets();
	$php = escapeshellarg( PHP_BINARY ) . ' -d date.timezone=UTC ' . escapeshellarg( __FILE__ ) . ' --compat 2>&1';
	$out = (string) shell_exec( $php );
	check( 1 === preg_match( '/^compat-ok (\d+)$/m', $out, $m ), 'Subproceso de otra versión del plugin: ' . trim( $out ) );
	$GLOBALS['sk_checks'] += (int) $m[1];

	echo "Skydropx en el MCP de Elements: {$GLOBALS['sk_checks']} comprobaciones en verde (registro condicionado, nueve acciones, permisos, modos, bitácora, unidades, lote con tope, metas del plugin, ningún secreto y otra versión sin piezas).\n";
}
