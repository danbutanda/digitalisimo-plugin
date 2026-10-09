<?php
namespace Elementor {
	class Controls_Manager { const TAB_ADVANCED = 'advanced'; const RAW_HTML = 'raw_html'; const SWITCHER = 'switcher'; const SELECT = 'select'; const SELECT2 = 'select2'; const TEXT = 'text'; const TEXTAREA = 'textarea'; const NUMBER = 'number'; const DATE_TIME = 'date_time'; const REPEATER = 'repeater'; }
	class Repeater {
		public $controls = array();
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function get_controls() { return $this->controls; }
	}
	class Plugin { public static $instance; public $editor; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'MINUTE_IN_SECONDS', 60 );
	$GLOBALS['digi'] = array( 'hooks' => array(), 'user' => 0, 'roles' => array(), 'flags' => array(), 'queried' => 0, 'type' => 'page', 'edit' => false );
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor = new class() { public function is_edit_mode() { return $GLOBALS['digi']['edit']; } };
	class Fake_Element {
		public $controls = array(), $sections = array(), $settings = array(), $id = 'abc123';
		public function start_controls_section( $id, $args ) { $this->sections[ $id ] = $args; }
		public function end_controls_section() {}
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function get_settings() { return $this->settings; }
		public function get_id() { return $this->id; }
	}
	class Fake_Product {
		public $id, $parent, $type;
		public function __construct( $id, $type = 'simple', $parent = 0 ) { $this->id = $id; $this->type = $type; $this->parent = $parent; }
		public function is_type( $type ) { return $type === $this->type; }
		public function get_parent_id() { return $this->parent; }
		public function get_id() { return $this->id; }
		public function get_category_ids() { return array( 7 ); }
		public function get_tag_ids() { return array( 11 ); }
		public function get_type() { return $this->type; }
		public function get_price() { return '120'; }
		public function get_stock_quantity() { return 3; }
		public function is_in_stock() { return true; }
		public function backorders_allowed() { return false; }
		public function is_on_sale() { return true; }
		public function get_featured() { return false; }
		public function get_virtual() { return false; }
		public function get_downloadable() { return false; }
		public function is_on_backorder() { return false; }
		public function is_sold_individually() { return false; }
	}
	class Fake_Cart {
		public $items = array();
		public function is_empty() { return ! $this->items; }
		public function get_cart() { return $this->items; }
		public function get_cart_contents_count() { return count( $this->items ); }
		public function get_displayed_subtotal() { return 240.0; }
	}
	$GLOBALS['digi']['cart'] = new Fake_Cart();
	function add_action( $hook, $callback ) { $GLOBALS['digi']['hooks'][] = $hook; }
	function add_filter( $hook, $callback ) { $GLOBALS['digi']['hooks'][] = $hook; }
	function apply_filters( $hook, $value ) { return array_key_exists( $hook, $GLOBALS['digi']['filters'] ?? array() ) ? $GLOBALS['digi']['filters'][ $hook ] : $value; }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
	function wp_unslash( $value ) { return $value; }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function home_url( $path = '' ) { return 'https://www.mi-sitio.test/' . ltrim( $path, '/' ); }
	function get_option( $name, $default = false ) { return 'gmt_offset' === $name ? -6 : ( array_key_exists( $name, $GLOBALS['digi']['options'] ?? array() ) ? $GLOBALS['digi']['options'][ $name ] : $default ); }
	function current_time( $type ) { return time() - 6 * HOUR_IN_SECONDS; }
	function is_user_logged_in() { return $GLOBALS['digi']['user'] > 0; }
	function get_current_user_id() { return $GLOBALS['digi']['user']; }
	function wp_get_current_user() { return (object) array( 'roles' => $GLOBALS['digi']['roles'] ); }
	function is_single( $id = '' ) { return 'post' === $GLOBALS['digi']['type'] && ( '' === $id || (string) $id === (string) $GLOBALS['digi']['queried'] ); }
	function is_singular( $types = '' ) { return in_array( $GLOBALS['digi']['type'], array( 'post', 'page', 'product' ), true ) && ( '' === $types || array() === $types || in_array( $GLOBALS['digi']['type'], (array) $types, true ) ); }
	foreach ( array( 'is_front_page', 'is_home', 'is_404' ) as $flag ) {
		eval( 'function ' . $flag . '() { return ! empty( $GLOBALS["digi"]["flags"]["' . $flag . '"] ); }' );
	}
	function get_the_ID() { return $GLOBALS['digi']['queried']; }
	function get_queried_object_id() { return $GLOBALS['digi']['queried']; }
	function get_queried_object() { return $GLOBALS['digi']['term'] ?? null; }
	function get_post_type() { return $GLOBALS['digi']['type']; }
	function get_locale() { return 'es_MX'; }
	function do_shortcode( $code ) { return '[plan]' === $code ? 'pro' : $code; }
	function shortcode_unautop( $code ) { return $code; }
	function WC() { return (object) array( 'cart' => $GLOBALS['digi']['cart'] ); }
	function wc_get_product( $id ) { return 50 === (int) $id ? new Fake_Product( 50, 'simple' ) : false; }
	function wc_get_orders( $args ) { return 9 === $args['customer_id'] ? array( new class() { public function get_date_completed() { return '2026-03-10T15:00:00+00:00'; } } ) : array(); }
	function get_field_object( $key ) { return 'field_promo' === $key ? array( 'parent' => 4, 'value' => true, 'type' => 'true_false' ) : false; }
	function wp_roles() { return new class() { public function get_names() { return array( 'subscriber' => 'Suscriptor' ); } }; }
	function get_post_types() { return array(); }
	function taxonomy_exists() { return false; }
	function get_posts() { return array( (object) array( 'post_name' => 'field_promo', 'post_title' => 'Promo', 'post_parent' => 4, 'post_content' => serialize( array( 'type' => 'true_false' ) ) ) ); }
	function is_serialized( $value ) { return is_string( $value ) && false !== @unserialize( $value ); }
	function get_the_title( $id ) { return 'Grupo'; }
	function check_legacy( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	class WooCommerce {}
	class ACF {}
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-conditions/class-legacy-visibility.php';
	use Digitalisimo\Elements\Conditions\Legacy_Rules as Rules;
	use Digitalisimo\Elements\Legacy_Visibility;

	$rule = function ( $key, $value, $extra = array() ) {
		return array( 'ep_condition_key' => $key, 'ep_condition_operator' => $extra['operator'] ?? 'is', 'ep_condition_' . $key . '_value' => $value ) + $extra;
	};
	$settings = function ( array $rows, $to = 'show', $relation = 'all' ) {
		return array( 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions_to' => $to, 'ep_display_conditions_relation' => $relation, 'ep_display_conditions' => $rows );
	};

	// Composición de reglas: misma lógica de mostrar/ocultar y todas/alguna.
	check_legacy( null === Rules::should_show( array( 'ep_display_conditions_enable' => '' ), 'a' ), 'Sin condiciones activas no se altera el render.' );
	$GLOBALS['digi']['user'] = 9;
	$GLOBALS['digi']['roles'] = array( 'customer' );
	check_legacy( true === Rules::should_show( $settings( array( $rule( 'authentication', 'authenticated' ), $rule( 'role', 'customer' ) ) ), 'a' ), 'Todas cumplidas: mostrar.' );
	check_legacy( false === Rules::should_show( $settings( array( $rule( 'authentication', 'authenticated' ), $rule( 'role', 'administrator' ) ) ), 'a' ), 'Una sin cumplir con «todas»: ocultar.' );
	check_legacy( true === Rules::should_show( $settings( array( $rule( 'authentication', 'authenticated' ), $rule( 'role', 'administrator' ) ), 'show', 'any' ), 'a' ), 'Alguna cumplida: mostrar.' );
	check_legacy( false === Rules::should_show( $settings( array( $rule( 'authentication', 'authenticated' ) ), 'hide' ), 'a' ), '«Ocultar» invierte el resultado.' );
	check_legacy( false === Rules::should_show( $settings( array( $rule( 'no_existe', 'x' ) ) ), 'a' ) && true === Rules::should_show( $settings( array( $rule( 'no_existe', 'x' ) ), 'hide' ), 'a' ), 'Sin reglas evaluables Element Pack considera la condición no cumplida.' );
	check_legacy( false === Rules::should_show( $settings( array( $rule( 'language', array() ) ) ), 'a' ), 'Una regla sin valor cuenta como no cumplida.' );

	// Reglas generales con sus particularidades.
	check_legacy( Rules::check( 'user', $rule( 'user', array( '3', '9' ) ) ) && ! Rules::check( 'user', $rule( 'user', array( '9' ), array( 'operator' => 'not' ) ) ), 'Usuario por ID.' );
	$GLOBALS['digi']['user'] = 0;
	check_legacy( Rules::check( 'authentication', $rule( 'authentication', 'authenticated', array( 'operator' => 'not' ) ) ) && ! Rules::check( 'role', $rule( 'role', 'customer' ) ), 'Visitante anónimo sin rol.' );
	$GLOBALS['digi']['queried'] = 12;
	check_legacy( Rules::check( 'static_page', $rule( 'static_page', 'custom', array( 'ep_condition_custom_page_id' => '12' ) ) ) && ! Rules::check( 'static_page', $rule( 'static_page', 'custom', array( 'ep_condition_custom_page_id' => '0' ) ) ), 'Página por ID.' );
	check_legacy( Rules::check( 'post_type', $rule( 'post_type', array( 'page' ) ) ) && Rules::check( 'post_type', $rule( 'post_type', '' ) ), 'Tipo de contenido, incluido el valor vacío que acepta cualquiera.' );
	$day = gmdate( 'w', time() - 6 * HOUR_IN_SECONDS );
	check_legacy( Rules::check( 'day', $rule( 'day', array( $day ) ) ) && ! Rules::check( 'day', $rule( 'day', array( (string) ( ( (int) $day + 1 ) % 7 ) ) ) ), 'Día de la semana en hora del sitio.' );
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36 Edg/130.0';
	check_legacy( Rules::check( 'os', $rule( 'os', 'windows' ) ) && Rules::check( 'browser', $rule( 'browser', 'edge' ) ) && ! Rules::check( 'browser', $rule( 'browser', 'chrome' ) ) && ! Rules::check( 'browser', $rule( 'browser', 'safari' ) ), 'Sistema y navegador como Element Pack.' );
	$_SERVER['REQUEST_URI'] = '/oferta/?utm_source=mail&ref=vip';
	$_GET = array( 'utm_source' => 'mail', 'ref' => 'vip' );
	check_legacy( Rules::check( 'url_parameters', $rule( 'url_parameters', "utm_source=mail\nother=1" ) ) && Rules::check( 'url_parameters', $rule( 'url_parameters', 'ref' ) ) && Rules::check( 'url_string', $rule( 'url_string', 'oferta' ) ), 'Parámetros y texto de URL.' );
	$_SERVER['HTTP_REFERER'] = 'https://www.bing.com/search?q=x';
	check_legacy( Rules::check( 'search_engine_url', $rule( 'search_engine_url', array( 'bing.com' ) ) ) && ! Rules::check( 'search_engine_url', $rule( 'search_engine_url', array( 'google.com', 'bing.com' ) ) ) && Rules::check( 'search_engine_url', $rule( 'search_engine_url', array() ) ), 'Buscador: sólo el primero elegido y cualquier referencia sin selección.' );
	check_legacy( Rules::check( 'ex_url', $rule( 'ex_url', '' ) ), 'Una referencia de otro dominio es externa.' );
	$_SERVER['HTTP_REFERER'] = 'https://mi-sitio.test/blog/';
	check_legacy( false === Rules::check( 'ex_url', $rule( 'ex_url', '' ) ), 'El propio sitio no es una referencia externa.' );
	check_legacy( Rules::check( 'language', $rule( 'language', array( 'es_MX', 'en_US' ) ) ), 'Idioma del sitio.' );
	check_legacy( Rules::check( 'shortcode', $rule( 'shortcode', 'pro', array( 'ep_condition_shortcode_name' => '[plan]' ) ) ) && null === Rules::check( 'shortcode', $rule( 'shortcode', 'pro' ) ), 'Resultado del shortcode.' );
	$_COOKIE['bdt-visit-count-abc123'] = '2';
	check_legacy( Rules::check( 'visit_count', $rule( 'visit_count', '5' ), 'abc123' ) && ! Rules::check( 'visit_count', $rule( 'visit_count', '3' ), 'abc123' ), 'Lee el contador de visitas existente.' );

	// País: código actual, nombre antiguo y desconocido como Element Pack.
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_country'] = 'MX';
	check_legacy( Rules::check( 'country', $rule( 'country', array( 'mx' ) ) ) && Rules::check( 'country', $rule( 'country', array( 'Mexico' ) ) ) && Rules::check( 'country', $rule( 'country', array( 'united states' ), array( 'operator' => 'not' ) ) ), 'El país admite códigos y nombres antiguos.' );
	$GLOBALS['digi']['filters']['digitalisimo_elements_visitor_country'] = '';
	check_legacy( ! Rules::check( 'country', $rule( 'country', array( 'mx' ) ) ) && Rules::check( 'country', $rule( 'country', array( 'mx' ), array( 'operator' => 'not' ) ) ), 'País desconocido: «no es» se cumple, como en Element Pack.' );

	// WooCommerce y ACF.
	check_legacy( false === Rules::check( 'products_in_cart', $rule( 'products_in_cart', array( '50' ) ) ) && Rules::check( 'cart_item_number', $rule( 'cart_item_number', 0 ) ), 'Carrito vacío.' );
	$GLOBALS['digi']['cart']->items = array( array( 'data' => new Fake_Product( 51, 'variation', 50 ) ) );
	check_legacy( Rules::check( 'products_in_cart', $rule( 'products_in_cart', array( '50' ) ) ) && Rules::check( 'categories_in_cart', $rule( 'categories_in_cart', array( '7' ) ) ) && Rules::check( 'cart_subtotal_price', $rule( 'cart_subtotal_price', 200 ) ), 'Variaciones cuentan como su producto padre.' );
	$GLOBALS['digi']['type'] = 'product';
	$GLOBALS['digi']['queried'] = 50;
	check_legacy( Rules::check( 'single_product_onsale', $rule( 'single_product_onsale', 'yes' ) ) && Rules::check( 'single_product_price', $rule( 'single_product_price', 100 ) ) && ! Rules::check( 'single_product_stock', $rule( 'single_product_stock', 5 ) ) && Rules::check( 'single_product_type', $rule( 'single_product_type', 'simple' ) ), 'Producto consultado.' );
	$GLOBALS['digi']['user'] = 9;
	check_legacy( Rules::check( 'last_purchased_date', $rule( 'last_purchased_date', '2026-03-10' ) ) && Rules::check( 'purchased_date', $rule( 'purchased_date', '2026-03-10' ) ) && ! Rules::check( 'purchased_date', $rule( 'purchased_date', '2026/03/10' ) ), 'Fechas de compra completada.' );
	check_legacy( Rules::check( 'orders_placed', $rule( 'orders_placed', 1, array( 'ep_condition_addition_operator' => 'equal' ) ) ), 'Pedidos realizados.' );
	check_legacy( Rules::check( 'acf_boolean', $rule( 'acf_boolean', 'true', array( 'ep_condition_acf_boolean_name' => 'field_promo' ) ) ) && null === Rules::check( 'acf_boolean', $rule( 'acf_boolean', 'true', array( 'ep_condition_acf_boolean_name' => 'field_otro' ) ) ), 'Campo ACF verdadero/falso.' );

	// Integración con Elementor: controles con los mismos nombres y valores por defecto.
	Legacy_Visibility::init();
	check_legacy( in_array( 'elementor/frontend/widget/should_render', $GLOBALS['digi']['hooks'], true ) && in_array( 'elementor/element/common/section_visibility_control_controls/before_section_end', $GLOBALS['digi']['hooks'], true ), 'Sin Element Pack deben registrarse controles y render.' );
	$element = new Fake_Element();
	Legacy_Visibility::register_section( $element );
	check_legacy( array( 'ep_display_conditions_enable' => 'yes' ) === $element->sections['section_visibility_control_controls']['condition'], 'La sección sólo aparece en elementos que ya usan las reglas.' );
	Legacy_Visibility::register_controls( $element );
	$fields = $element->controls['ep_display_conditions']['fields'];
	check_legacy( 'Promo ( Grupo )' === ( $fields['ep_condition_acf_boolean_name']['options']['field_promo'] ?? '' ), 'Los campos ACF deben listarse con la clave que guardaba Element Pack.' );
	foreach ( Rules::supported_keys() as $key ) {
		check_legacy( isset( $fields[ 'ep_condition_' . $key . '_value' ] ), 'Falta el control de valor de ' . $key );
	}
	$defaults = array( 'authentication' => 'authenticated', 'role' => 'subscriber', 'static_page' => 'home', 'day' => '1', 'os' => 'iphone', 'browser' => 'ie', 'search_engine_url' => 'google.com', 'visit_count' => '1', 'session_count' => '1', 'cart_item_number' => 1, 'cart_subtotal_price' => 50, 'single_product_price' => 50, 'single_product_stock' => 5, 'single_product_onsale' => 'yes', 'single_product_type' => 'simple', 'acf_boolean' => 'true', 'orders_placed' => 1 );
	foreach ( $defaults as $key => $default ) {
		check_legacy( $default === $fields[ 'ep_condition_' . $key . '_value' ]['default'], 'Valor por defecto distinto de Element Pack en ' . $key );
	}
	check_legacy( 'authentication' === $fields['ep_condition_key']['default'] && 'is' === $fields['ep_condition_operator']['default'] && 'equal' === $fields['ep_condition_addition_operator']['default'] && '10' === $fields['ep_condition_custom_page_id']['default'] && isset( $fields['ep_condition_shortcode_name'], $fields['ep_condition_time_range_end_time'] ), 'Controles comunes del repetidor equivalentes.' );

	$element->settings = $settings( array( $rule( 'authentication', 'authenticated', array( 'operator' => 'not' ) ) ) );
	check_legacy( false === Legacy_Visibility::should_render( true, $element ), 'El frontend debe ocultar según la regla.' );
	$GLOBALS['digi']['options']['element_pack_elementor_extend'] = array( 'visibility-controls' => 'on' );
	check_legacy( false === Legacy_Visibility::should_render( true, $element ), 'Con la extensión encendida en Element Pack se aplican las reglas.' );
	$GLOBALS['digi']['options']['element_pack_elementor_extend'] = array( 'visibility-controls' => 'off' );
	check_legacy( true === Legacy_Visibility::should_render( true, $element ), 'Si Element Pack tenía la extensión apagada, las reglas no se aplicaban y siguen sin aplicarse.' );
	$GLOBALS['digi']['options']['element_pack_elementor_extend'] = array( 'otra' => 'on' );
	check_legacy( true === Legacy_Visibility::should_render( true, $element ), 'La extensión venía apagada por defecto.' );
	unset( $GLOBALS['digi']['options'] );
	$GLOBALS['digi']['edit'] = true;
	check_legacy( true === Legacy_Visibility::should_render( true, $element ), 'El editor siempre muestra el elemento.' );
	check_legacy( Legacy_Visibility::is_dynamic_content( false, array( 'settings' => array( 'ep_display_conditions_enable' => 'yes' ) ) ), 'La caché de elementos no debe guardar contenido condicionado.' );

	$GLOBALS['digi']['hooks'] = array();
	define( 'BDTEP_VER', '9.9.1' );
	Legacy_Visibility::init();
	check_legacy( array() === $GLOBALS['digi']['hooks'], 'Con Element Pack activo no se compite por sus controles.' );

	echo "DIGITALÍSIMO Elements: condiciones heredadas de Element Pack validadas.\n";
}
