<?php
namespace Elementor {
	class Controls_Manager { const SELECT = 'select'; const SELECT2 = 'select2'; const TEXT = 'text'; const DATE_TIME = 'date_time'; }
	abstract class Controls_Stack {
		public $controls = array();
		public function __construct( array $data = array() ) {}
		public function start_controls_section( $id ) {}
		public function end_controls_section() {}
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
	}
}
namespace ElementorPro\Modules\QueryControl {
	class Module { const QUERY_CONTROL_ID = 'query'; const QUERY_OBJECT_POST = 'post'; const QUERY_OBJECT_TAX = 'tax'; const QUERY_OBJECT_USER = 'user'; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	class WP_Post { public $ID; public function __construct( $id ) { $this->ID = $id; } }
	class Fake_Order {
		private $date;
		public function __construct( $date ) { $this->date = new DateTime( $date, new DateTimeZone( 'UTC' ) ); }
		public function get_date_paid() { return $this->date; }
		public function get_date_created() { return $this->date; }
	}
	class Fake_Product {
		public function get_id() { return 50; }
		public function get_type() { return 'variable'; }
		public function get_price() { return '199.90'; }
		public function get_stock_quantity() { return 3; }
		public function get_stock_status() { return 'instock'; }
		public function is_on_sale() { return true; }
		public function is_featured() { return false; }
		public function is_virtual() { return false; }
		public function is_downloadable() { return false; }
		public function is_sold_individually() { return false; }
	}
	class Fake_Cart {
		public function get_cart() { return array( array( 'product_id' => 50, 'variation_id' => 51 ), array( 'product_id' => 60 ) ); }
		public function get_cart_contents_count() { return 3; }
		public function get_displayed_subtotal() { return 1250.5; }
	}
	$GLOBALS['digi'] = array( 'hooks' => array(), 'filters' => array(), 'user' => 0, 'singular' => false, 'type' => '', 'queried' => null, 'flags' => array() );
	function add_action( $hook, $callback ) { $GLOBALS['digi']['hooks'][ $hook ] = $callback; }
	function apply_filters( $hook, $value ) { return array_key_exists( $hook, $GLOBALS['digi']['filters'] ) ? $GLOBALS['digi']['filters'][ $hook ] : $value; }
	function esc_html__( $text ) { return $text; }
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function wp_unslash( $value ) { return $value; }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function wp_get_raw_referer() { return $_SERVER['HTTP_REFERER'] ?? false; }
	function home_url( $path = '' ) { return $GLOBALS['digi']['home'] . ltrim( $path, '/' ); }
	function get_current_user_id() { return $GLOBALS['digi']['user']; }
	function get_current_blog_id() { return 2; }
	function get_locale() { return $GLOBALS['digi']['locale'] ?? 'es_MX'; }
	function get_available_languages() { return array( 'es_MX', 'xx_TEST' ); }
	function is_singular() { return $GLOBALS['digi']['singular']; }
	function get_post_type( $id = null ) { return $GLOBALS['digi']['type']; }
	function get_queried_object_id() { return $GLOBALS['digi']['queried'] ? $GLOBALS['digi']['queried']->ID : 0; }
	function get_queried_object() { return $GLOBALS['digi']['queried']; }
	function get_the_ID() { return $GLOBALS['digi']['queried'] ? $GLOBALS['digi']['queried']->ID : false; }
	function get_post_types( $args, $output ) { return array( 'post' => (object) array( 'labels' => (object) array( 'singular_name' => 'Entrada' ) ), 'attachment' => (object) array( 'labels' => (object) array( 'singular_name' => 'Medio' ) ) ); }
	foreach ( array( 'is_front_page', 'is_home', 'is_archive', 'is_search', 'is_404' ) as $flag ) {
		eval( 'function ' . $flag . '() { return ! empty( $GLOBALS["digi"]["flags"]["' . $flag . '"] ); }' );
	}
	function is_page( $id ) { return ! empty( $GLOBALS['digi']['flags']['privacy'] ); }
	function is_tax( $taxonomy ) { return 'product_cat' === ( $GLOBALS['digi']['tax'] ?? '' ); }
	function get_option( $name ) { return 'wp_page_for_privacy_policy' === $name ? 3 : false; }
	function do_shortcode( $code ) { return '[estado]' === $code ? ' <b>activo</b> ' : $code; }
	function wp_timezone() { return new DateTimeZone( 'America/Mexico_City' ); }
	function WC() { return (object) array( 'cart' => new Fake_Cart() ); }
	function wc_get_product( $id ) { return 50 === $id ? new Fake_Product() : false; }
	function wc_get_product_term_ids( $id, $taxonomy ) { return 'product_cat' === $taxonomy ? array( 50 === $id ? 7 : 8 ) : array( 11 ); }
	function wc_get_is_paid_statuses() { return array( 'processing', 'completed' ); }
	function wc_get_orders( $args ) {
		$GLOBALS['digi']['order_queries'] = ( $GLOBALS['digi']['order_queries'] ?? 0 ) + 1;
		return 9 === $args['customer_id'] ? array( new Fake_Order( '2026-01-05 03:00:00' ), new Fake_Order( '2026-09-30 18:00:00' ) ) : array();
	}
	function wc_customer_bought_product( $email, $user, $product ) { return 9 === $user && 60 === $product; }
	function wc_get_product_types() { return array( 'simple' => 'Simple', 'variable' => 'Variable' ); }

	function check_conditions( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$base = __DIR__ . '/../digitalisimo-elements/modules/display-conditions/';
	require $base . 'classes/comparator-provider.php';
	require $base . 'classes/comparators-checker.php';
	require $base . 'conditions/base/condition-base.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-conditions/class-module.php';

	class Fake_Manager {
		public $groups = array(), $conditions = array();
		public function add_group( $name, $props ) { $this->groups[ $name ] = $props['label']; }
		public function register_condition_instance( $instance ) { $this->conditions[ $instance->get_name() ] = $instance; }
	}

	\Digitalisimo\Elements\Display_Conditions::init();
	check_conditions( isset( $GLOBALS['digi']['hooks']['elementor/display_conditions/register'], $GLOBALS['digi']['hooks']['elementor/display_conditions/register_groups'] ), 'Las condiciones deben registrarse en el motor nativo.' );
	$manager = new Fake_Manager();
	\Digitalisimo\Elements\Display_Conditions::register_groups( $manager );
	\Digitalisimo\Elements\Display_Conditions::register_conditions( $manager );
	check_conditions( 12 === count( $manager->conditions ) && ! isset( $manager->groups['digitalisimo_woocommerce'] ), 'Sin WooCommerce sólo deben existir las doce condiciones generales.' );

	eval( 'class WooCommerce {}' ); // Declarada aquí para no adelantarse a la primera comprobación.
	$manager = new Fake_Manager();
	\Digitalisimo\Elements\Display_Conditions::register_groups( $manager );
	\Digitalisimo\Elements\Display_Conditions::register_conditions( $manager );
	check_conditions( 28 === count( $manager->conditions ) && 'WooCommerce' === ( $manager->groups['digitalisimo_woocommerce'] ?? '' ), 'Con WooCommerce deben añadirse sus dieciséis condiciones y su grupo.' );
	$c = $manager->conditions;

	foreach ( $c as $name => $condition ) {
		check_conditions( 0 === strpos( $name, 'digitalisimo_' ), 'Cada condición debe usar un prefijo propio: ' . $name );
		$condition->get_options();
		check_conditions( isset( $condition->controls['comparator']['options'], $condition->controls['comparator']['default'] ) && '' !== $condition->get_label(), 'Cada condición necesita comparador y etiqueta: ' . $name );
		foreach ( $condition->controls as $key => $control ) {
			check_conditions( in_array( $control['type'], array( 'select', 'select2', 'text', 'date_time', 'query' ), true ), 'La interfaz nativa no sabe pintar el control ' . $name . '.' . $key );
		}
	}
	check_conditions( 'term_id' === $c['digitalisimo_cart_categories']->controls['categories']['autocomplete']['by_field'], 'Las taxonomías deben guardar term_id, no term_taxonomy_id.' );
	check_conditions( 'México' === $c['digitalisimo_country']->controls['countries']['options']['MX'] && 249 === count( $c['digitalisimo_country']->controls['countries']['options'] ), 'La lista de países debe ser ISO y estar en español.' );
	check_conditions( isset( $c['digitalisimo_language']->controls['languages']['options']['xx_TEST'] ) && ! isset( $c['digitalisimo_post_type']->controls['types']['options']['attachment'] ), 'Idiomas instalados sí; adjuntos no.' );

	$is  = array( 'comparator' => 'is_one_of' );
	$not = array( 'comparator' => 'is_none_of' );

	$GLOBALS['digi']['user'] = 9;
	check_conditions( $c['digitalisimo_user']->check( $is + array( 'users' => array( array( 'id' => 9, 'label' => 'Ana' ) ) ) ), 'El usuario seleccionado debe cumplir.' );
	$GLOBALS['digi']['user'] = 0;
	check_conditions( ! $c['digitalisimo_user']->check( $is + array( 'users' => array( array( 'id' => 9 ) ) ) ) && $c['digitalisimo_user']->check( $not + array( 'users' => array( array( 'id' => 9 ) ) ) ), 'Un visitante anónimo no es ningún usuario seleccionado.' );

	$agents = array(
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Edg/130.0' => array( 'windows', 'edge' ),
		'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1' => array( 'ios', 'safari' ),
		'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0 Mobile Safari/537.36' => array( 'android', 'samsung' ),
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 OPR/115.0' => array( 'macos', 'opera' ),
		'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0' => array( 'linux', 'firefox' ),
		'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36' => array( 'chromeos', 'chrome' ),
	);
	foreach ( $agents as $agent => $expected ) {
		$_SERVER['HTTP_USER_AGENT'] = $agent;
		check_conditions( $c['digitalisimo_os']->check( $is + array( 'systems' => array( $expected[0] ) ) ), 'Sistema mal detectado: ' . $agent );
		check_conditions( $c['digitalisimo_browser']->check( $is + array( 'browsers' => array( $expected[1] ) ) ), 'Navegador mal detectado: ' . $agent );
		check_conditions( 'chrome' === $expected[1] || ! $c['digitalisimo_browser']->check( $is + array( 'browsers' => array( 'chrome' ) ) ), 'Un navegador derivado no debe contarse como Chrome: ' . $agent );
	}

	check_conditions( ! $c['digitalisimo_country']->check( $is + array( 'countries' => array( 'MX' ) ) ) && ! $c['digitalisimo_country']->check( $not + array( 'countries' => array( 'MX' ) ) ), 'Un país desconocido no debe cumplir ningún comparador.' );
	$_SERVER['HTTP_CF_IPCOUNTRY'] = 'mx';
	check_conditions( $c['digitalisimo_country']->check( $is + array( 'countries' => array( 'MX', 'US' ) ) ), 'El país del CDN debe reconocerse.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_country'] = 'CO';
	check_conditions( $c['digitalisimo_country']->check( $not + array( 'countries' => array( 'MX' ) ) ), 'El filtro del sitio debe tener prioridad.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_country'] = 'T1';
	check_conditions( ! $c['digitalisimo_country']->check( $not + array( 'countries' => array( 'MX' ) ) ), 'Tor y valores no ISO cuentan como país desconocido.' );

	check_conditions( $c['digitalisimo_language']->check( $is + array( 'source' => 'site', 'languages' => array( 'es_MX' ) ) ), 'El idioma del sitio debe compararse por locale.' );
	$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-GB,en;q=0.8,es;q=0.5';
	check_conditions( $c['digitalisimo_language']->check( $is + array( 'source' => 'browser', 'languages' => array( 'en_US' ) ) ) && ! $c['digitalisimo_language']->check( $is + array( 'source' => 'browser', 'languages' => array( 'es_MX' ) ) ), 'El navegador debe compararse por idioma principal.' );

	$_GET = array( 'utm_source' => 'Newsletter-Octubre', 'vacio' => '' );
	$param = $c['digitalisimo_url_parameter'];
	check_conditions( $param->check( array( 'parameter' => 'utm_source', 'comparator' => 'contains', 'value' => 'newsletter' ) ), 'El valor del parámetro debe compararse sin mayúsculas.' );
	check_conditions( $param->check( array( 'parameter' => 'vacio', 'comparator' => 'exists' ) ) && $param->check( array( 'parameter' => 'gclid', 'comparator' => 'not_exists' ) ) && ! $param->check( array( 'parameter' => '', 'comparator' => 'not_exists' ) ), 'La existencia del parámetro debe evaluarse aparte de su valor.' );

	$GLOBALS['digi']['home'] = 'https://red.example.test/sub/';
	$_SERVER['REQUEST_URI'] = '/sub/Tienda/Ofertas?orden=precio';
	$path = $c['digitalisimo_url_path'];
	check_conditions( $path->check( array( 'comparator' => 'is', 'url' => '/tienda/ofertas' ) ) && $path->check( array( 'comparator' => 'is', 'url' => 'https://red.example.test/sub/tienda/ofertas/' ) ), 'La ruta debe ser relativa al subsitio y tolerar la barra final.' );
	check_conditions( $path->check( array( 'comparator' => 'contains', 'url' => 'orden=precio' ) ) && $path->check( array( 'comparator' => 'is', 'url' => '/sub/tienda/ofertas/' ) ) && ! $path->check( array( 'comparator' => 'is', 'url' => '/tienda/' ) ), 'Contiene revisa la URL completa; Es compara la ruta exacta del sitio.' );

	$_SERVER['HTTP_REFERER'] = 'https://www.google.com.mx/search?q=hosting';
	check_conditions( $c['digitalisimo_search_engine']->check( $is + array( 'engines' => array( 'bing', 'google' ) ) ), 'Google con dominio local debe reconocerse.' );
	$_SERVER['HTTP_REFERER'] = 'https://google.example.test/';
	check_conditions( $c['digitalisimo_search_engine']->check( $not + array( 'engines' => array( 'google' ) ) ), 'Un dominio que sólo contiene google no es Google.' );

	$GLOBALS['digi']['singular'] = true;
	$GLOBALS['digi']['type'] = 'post';
	$GLOBALS['digi']['queried'] = new WP_Post( 3 );
	$GLOBALS['digi']['flags'] = array( 'privacy' => true );
	check_conditions( $c['digitalisimo_post_type']->check( $is + array( 'types' => array( 'page', 'post' ) ) ), 'El tipo de contenido actual debe cumplir.' );
	check_conditions( $c['digitalisimo_specific_content']->check( $is + array( 'items' => array( array( 'id' => 3 ) ) ) ), 'El contenido seleccionado debe cumplir.' );
	check_conditions( $c['digitalisimo_special_page']->check( $is + array( 'pages' => array( 'singular' ) ) ) && $c['digitalisimo_special_page']->check( $is + array( 'pages' => array( 'privacy' ) ) ) && $c['digitalisimo_special_page']->check( $not + array( 'pages' => array( 'not_found', 'search' ) ) ), 'Las páginas especiales deben detectarse.' );

	$short = $c['digitalisimo_shortcode'];
	check_conditions( $short->check( array( 'shortcode' => '[estado]', 'comparator' => 'is', 'value' => '<b>activo</b>' ) ) && $short->check( array( 'shortcode' => '[estado]', 'comparator' => 'is_not_empty' ) ), 'El resultado del shortcode debe compararse.' );
	check_conditions( ! $short->check( array( 'shortcode' => 'texto libre', 'comparator' => 'is_empty' ) ), 'Sólo se ejecutan shortcodes.' );

	check_conditions( $c['digitalisimo_cart_products']->check( $is + array( 'products' => array( array( 'id' => 60 ) ) ) ) && $c['digitalisimo_cart_products']->check( $not + array( 'products' => array( array( 'id' => 51 ) ) ) ), 'El carrito compara productos principales.' );
	check_conditions( $c['digitalisimo_cart_categories']->check( $is + array( 'categories' => array( array( 'id' => 8 ) ) ) ) && $c['digitalisimo_cart_tags']->check( $is + array( 'tags' => array( array( 'id' => 11 ) ) ) ), 'El carrito debe reunir categorías y etiquetas.' );
	check_conditions( $c['digitalisimo_cart_count']->check( array( 'comparator' => 'is_greater_than_inclusive', 'value' => '3' ) ) && $c['digitalisimo_cart_subtotal']->check( array( 'comparator' => 'is_greater_than_inclusive', 'value' => '1250,50' ) ) && ! $c['digitalisimo_cart_subtotal']->check( array( 'comparator' => 'is', 'value' => 'mil' ) ), 'Las cantidades aceptan decimales y rechazan texto.' );

	check_conditions( ! $c['digitalisimo_customer_orders']->check( array( 'comparator' => 'is_greater_than_inclusive', 'value' => '1' ) ) && 0 === ( $GLOBALS['digi']['order_queries'] ?? 0 ), 'Un visitante anónimo no tiene pedidos ni dispara consultas.' );
	$GLOBALS['digi']['user'] = 9;
	check_conditions( $c['digitalisimo_customer_orders']->check( array( 'comparator' => 'is', 'value' => '2' ) ) && $c['digitalisimo_customer_bought']->check( $is + array( 'products' => array( array( 'id' => 50 ), array( 'id' => 60 ) ) ) ), 'Los pedidos pagados y productos comprados deben contarse.' );
	// 2026-01-05 03:00 UTC es todavía el 4 de enero en la Ciudad de México.
	check_conditions( $c['digitalisimo_customer_first_purchase']->check( array( 'comparator' => 'is', 'date' => '01-04-2026' ) ) && $c['digitalisimo_customer_last_purchase']->check( array( 'comparator' => 'is_after', 'date' => '09-01-2026' ) ), 'Las fechas de compra deben usar la zona horaria del sitio.' );
	check_conditions( $c['digitalisimo_customer_purchase_date']->check( array( 'comparator' => 'is', 'date' => '09-30-2026' ) ) && $c['digitalisimo_customer_purchase_date']->check( array( 'comparator' => 'is_not', 'date' => '09-29-2026' ) ), 'Debe localizar una compra en un día concreto.' );
	check_conditions( 1 === $GLOBALS['digi']['order_queries'], 'Los pedidos del cliente se consultan una sola vez por petición.' );

	$GLOBALS['digi']['type'] = 'product';
	$GLOBALS['digi']['queried'] = new WP_Post( 50 );
	check_conditions( $c['digitalisimo_product_status']->check( $is + array( 'statuses' => array( 'on_sale' ) ) ) && $c['digitalisimo_product_status']->check( $not + array( 'statuses' => array( 'out_of_stock', 'featured' ) ) ), 'El estado del producto debe evaluarse.' );
	check_conditions( $c['digitalisimo_product_type']->check( $is + array( 'types' => array( 'variable' ) ) ) && $c['digitalisimo_product_category']->check( $is + array( 'categories' => array( array( 'id' => 7 ) ) ) ), 'Tipo y categoría del producto deben evaluarse.' );
	check_conditions( $c['digitalisimo_product_price']->check( array( 'comparator' => 'is_less_than_inclusive', 'value' => '199.90' ) ) && $c['digitalisimo_product_stock']->check( array( 'comparator' => 'is_less_than_inclusive', 'value' => '5' ) ), 'Precio y existencias deben compararse.' );
	$GLOBALS['digi']['type'] = 'post';
	check_conditions( ! $c['digitalisimo_product_status']->check( $not + array( 'statuses' => array( 'on_sale' ) ) ), 'Fuera de un producto, sus condiciones no se cumplen.' );
	$GLOBALS['digi']['tax'] = 'product_cat';
	$GLOBALS['digi']['queried'] = new WP_Post( 7 );
	check_conditions( $c['digitalisimo_product_category_archive']->check( $is + array( 'categories' => array( array( 'id' => 7 ) ) ) ), 'El archivo de categoría de producto debe reconocerse.' );

	echo "DIGITALÍSIMO Elements: condiciones de visualización ampliadas validadas.\n";
}
