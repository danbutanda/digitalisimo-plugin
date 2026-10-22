<?php
/**
 * F7a, frente H: condiciones nuevas de DIGITALÍSIMO Elements (3.4.1, V.4.1 y V.4.2).
 *
 * Sin WordPress ni el constructor: dobles en memoria y las clases reales de PRO que evalúan (`Or_Condition`,
 * `And_Condition`, los comparadores y la base de condiciones). Comprueba:
 * - registro: las siete siempre, también sin WooCommerce; etiquetas en español, comparador y controles que el modal sabe
 *   pintar; el aviso común en la configuración del editor; las dos referencias de kit con `gate`;
 * - caché: las cuatro cacheables en `CACHEABLE_CONDITIONS`, las tres variables fuera, `COOKIE_CONDITIONS` sin cambios y
 *   un elemento v4 con condiciones dinámico para la caché de elementos;
 * - `check()` de cada una: listas y valores esperados vacíos nunca cumplen, sin WooCommerce no hay existencias, la
 *   cookie con sus vetos ampliados y normalizados y sin «contiene», D25/R14 en los campos, y nunca se imprime un valor;
 * - centinela: con el código de 4.3.0.163 (este archivo sin el bloque marcado, sha256 fijado) un grupo con una condición
 *   nueva y el centinela no se cumple; sin centinela sí (fallaría abierto); con el código actual se evalúa la condición;
 * - guardado y carga: centinela al guardar (v3, formato antiguo y v4), idempotente, retirado sólo en modo de edición, y
 *   la guarda de la cookie para quien no tiene `unfiltered_html` (o no es superadministrador en una red);
 * - datos del script del editor (lo ejecuta `tests/elements-page-visits.mjs`).
 */

namespace Elementor {
	class Controls_Manager {
		const SELECT    = 'select';
		const SELECT2   = 'select2';
		const TEXT      = 'text';
		const DATE_TIME = 'date_time';
	}
	abstract class Controls_Stack {
		public $controls = array();
		public function __construct( array $data = array() ) {}
		public function start_controls_section( $id ) {}
		public function end_controls_section() {}
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function get_config() { return $this->get_initial_config(); }
		protected function get_initial_config() { return array( 'controls' => $this->controls ); }
	}
	class Plugin {
		public static $instance;
		public $editor;
	}
}

namespace Elementor\Modules\AtomicWidgets\PropsResolver {
	abstract class Transformer_Base {}
	class Props_Resolver_Context {}
}

namespace ElementorPro\Modules\QueryControl {
	class Module { const QUERY_CONTROL_ID = 'query'; const QUERY_OBJECT_POST = 'post'; const QUERY_OBJECT_TAX = 'tax'; const QUERY_OBJECT_USER = 'user'; }
}

namespace {
	define( 'ABSPATH', __DIR__ );

	class WP_Post { public $ID; public function __construct( $id ) { $this->ID = $id; } }
	class WP_Term { public $term_id; public $taxonomy; public function __construct( $id, $taxonomy ) { $this->term_id = $id; $this->taxonomy = $taxonomy; } }
	class More_Product {
		private $status;
		private $quantity;
		public function __construct( $status, $quantity ) { $this->status = $status; $this->quantity = $quantity; }
		public function get_stock_status() { return $this->status; }
		public function get_stock_quantity() { return $this->quantity; }
	}
	class More_Document {
		private $main;
		private $post;
		public function __construct( $main, $post = null ) { $this->main = $main; $this->post = new WP_Post( null === $post ? $main : $post ); }
		public function get_main_id() { return $this->main; }
		public function get_post() { return $this->post; }
	}

	$GLOBALS['mc'] = array();
	function mc_reset( array $over = array() ) {
		$GLOBALS['mc'] = array_merge(
			array(
				'hooks'     => array(),
				'filters'   => array(),
				'user'      => 0,
				'caps'      => array(),
				'multisite' => false,
				'super'     => false,
				'admin'     => false,
				'edit'      => false,
				'singular'  => false,
				'author'    => false,
				'search'    => false,
				'taxonomy'  => false,
				'queried'   => null,
				'the_id'    => 0,
				'post_meta' => array(),
				'term_meta' => array(),
				'user_meta' => array(),
				'authors'   => array(),
				'roles'     => array(),
				'query'     => '',
				'acf'       => array(),
				'inline'    => array(),
				'scripts'   => array(),
			),
			$over
		);
		$_COOKIE = array();
		$GLOBALS['wp_query'] = (object) array( 'found_posts' => 0 );
		\Elementor\Plugin::$instance         = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->editor = new class() { public function is_edit_mode() { return $GLOBALS['mc']['edit']; } };
	}
	mc_reset();

	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['mc']['hooks'][ $hook ][] = array( $callback, $priority ); }
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['mc']['hooks'][ $hook ][] = array( $callback, $priority ); }
	function apply_filters( $hook, $value, ...$args ) {
		if ( ! array_key_exists( $hook, $GLOBALS['mc']['filters'] ) ) {
			return $value;
		}
		$filter = $GLOBALS['mc']['filters'][ $hook ];
		return is_callable( $filter ) ? $filter( $value, ...$args ) : $filter;
	}
	function do_action( $hook, ...$args ) {}
	function esc_html__( $text ) { return $text; }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
	function wp_timezone() { return new DateTimeZone( 'America/Mexico_City' ); }
	function is_admin() { return $GLOBALS['mc']['admin']; }
	function wp_doing_ajax() { return ! empty( $GLOBALS['mc']['ajax'] ); }
	function is_multisite() { return $GLOBALS['mc']['multisite']; }
	function is_super_admin() { return $GLOBALS['mc']['super']; }
	function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['mc']['caps'], true ); }
	function get_current_user_id() { return $GLOBALS['mc']['user']; }
	function get_current_blog_id() { return 1; }
	function home_url( $path = '' ) { return 'https://a.test/' . ltrim( $path, '/' ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function get_option( $name, $default = false ) { return 'wp_page_for_privacy_policy' === $name ? 3 : $default; }
	function is_singular() { return $GLOBALS['mc']['singular']; }
	function is_author() { return $GLOBALS['mc']['author']; }
	function is_search() { return $GLOBALS['mc']['search']; }
	function is_category() { return 'category' === $GLOBALS['mc']['taxonomy']; }
	function is_tag() { return 'post_tag' === $GLOBALS['mc']['taxonomy']; }
	function is_tax() { return ! in_array( $GLOBALS['mc']['taxonomy'], array( false, 'category', 'post_tag' ), true ); }
	foreach ( array( 'is_front_page', 'is_home', 'is_archive', 'is_404', 'is_feed', 'is_embed', 'is_preview' ) as $flag ) {
		eval( 'function ' . $flag . '() { return false; }' );
	}
	function is_page( $id ) { return false; }
	function get_queried_object() { return $GLOBALS['mc']['queried']; }
	function get_queried_object_id() {
		$object = $GLOBALS['mc']['queried'];
		return $object instanceof WP_Post ? $object->ID : ( $object instanceof WP_Term ? $object->term_id : ( is_int( $object ) ? $object : 0 ) );
	}
	function get_the_ID() { return $GLOBALS['mc']['the_id']; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['mc']['post_meta'][ $id ][ $key ] ?? ''; }
	function get_term_meta( $id, $key, $single = false ) { return $GLOBALS['mc']['term_meta'][ $id ][ $key ] ?? ''; }
	function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['mc']['user_meta'][ $id ][ $key ] ?? ''; }
	function get_post_field( $field, $id ) { return 'post_author' === $field ? ( $GLOBALS['mc']['authors'][ $id ] ?? 0 ) : ''; }
	function get_userdata( $id ) { return isset( $GLOBALS['mc']['roles'][ $id ] ) ? (object) array( 'roles' => $GLOBALS['mc']['roles'][ $id ] ) : false; }
	function is_protected_meta( $key, $type = '' ) { return '_' === substr( (string) $key, 0, 1 ); }
	function get_search_query( $escaped = true ) { return $GLOBALS['mc']['query']; }
	function wp_roles() { return new class() { public function get_names() { return array( 'administrator' => 'Administrator', 'editor' => 'Editor', 'author' => 'Author' ); } }; }
	function translate_user_role( $name ) { return array( 'Administrator' => 'Administrador', 'Editor' => 'Editor', 'Author' => 'Autor' )[ $name ] ?? $name; }
	function wc_get_product( $id ) {
		$products = array( 50 => new More_Product( 'instock', 3 ), 60 => new More_Product( 'outofstock', null ), 70 => new More_Product( 'onbackorder', 0 ) );
		return $products[ $id ] ?? false;
	}
	function wp_script_is( $handle, $status ) { return in_array( $handle, $GLOBALS['mc']['scripts'], true ); }
	function wp_add_inline_script( $handle, $data, $position ) { $GLOBALS['mc']['inline'][] = array( $handle, $data, $position ); return true; }
	function admin_url( $path = '' ) { return 'https://a.test/wp-admin/' . ltrim( $path, '/' ); }

	function check_more( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$elements = __DIR__ . '/../digitalisimo-elements/';
	$pro      = $elements . 'modules/display-conditions/';
	$module   = $elements . 'modules/digitalisimo-conditions/';
	require $pro . 'classes/comparator-provider.php';
	require $pro . 'classes/comparators-checker.php';
	require $pro . 'classes/and-condition.php';
	require $pro . 'classes/or-condition.php';
	require $pro . 'conditions/base/condition-base.php';
	require $elements . 'modules/atomic-widgets/transformers/condition-group.php';
	require $module . 'class-module.php';

	use Digitalisimo\Elements\Display_Conditions;
	use Digitalisimo\Elements\Conditions\Condition_Groups as Groups;
	use Digitalisimo\Elements\Conditions\Conditions_Guard as Guard;
	use Digitalisimo\Elements\Conditions\Visitor_Content;
	use ElementorPro\Modules\DisplayConditions\Classes\Or_Condition;

	class More_Manager {
		public $groups = array(), $conditions = array();
		public function add_group( $name, $props ) { $this->groups[ $name ] = $props['label']; }
		public function register_condition_instance( $instance ) { $this->conditions[ $instance->get_name() ] = $instance; }
		public function get_condition( $id ) { return $this->conditions[ $id ] ?? false; }
	}
	/** ¿Se ve un elemento con estos grupos? La misma evaluación que PRO (`Or_Condition` → `And_Condition`). */
	function more_visible( $manager, array $groups ) {
		return ( new Or_Condition( $manager, $groups ) )->check();
	}
	/** Ejecuta un check() y falla si imprime algo. */
	function more_check( $condition, array $args ) {
		ob_start();
		$result = $condition->check( $args );
		$output = ob_get_clean();
		check_more( '' === $output, 'Una condición imprimió algo: ' . $output );
		return $result;
	}

	// 1. Registro: las siete, siempre.
	Display_Conditions::init();
	$hooks = $GLOBALS['mc']['hooks'];
	check_more( isset( $hooks['elementor/document/save/data'], $hooks['elementor/document/load/data'], $hooks['elementor/editor/after_enqueue_scripts'], $hooks['digitalisimo_elements_kit_references'] ), 'init() arranca el guardián y declara las referencias de kit.' );
	check_more( isset( $hooks['wp_footer'], $hooks['transition_post_status'], $hooks['elementor/document/after_save'], $hooks['digitalisimo_elements_kit_imported'] ), 'init() arranca Page_Visits (V-I11).' );
	$manager = new More_Manager();
	Display_Conditions::register_groups( $manager );
	Display_Conditions::register_conditions( $manager );
	check_more( 19 === count( $manager->conditions ) && ! isset( $manager->groups['digitalisimo_woocommerce'] ), 'Sin WooCommerce: las doce generales y las siete de F7a.' );
	$more = Display_Conditions::MORE;
	check_more( array( 'digitalisimo_cookie', 'digitalisimo_page_visit', 'digitalisimo_field', 'digitalisimo_user_field', 'digitalisimo_author', 'digitalisimo_product_stock_of', 'digitalisimo_search' ) === $more, 'MORE nombra las siete de D22.' );
	$names = array();
	foreach ( Display_Conditions::more_classes() as $class ) {
		$names[] = ( new $class() )->get_name();
	}
	check_more( $more === $names && array() === array_diff( Display_Conditions::more_classes(), Display_Conditions::condition_classes() ), 'more_classes() sigue a MORE y condition_classes() las incluye siempre.' );
	$c = $manager->conditions;
	foreach ( $more as $name ) {
		check_more( isset( $c[ $name ] ), "Registrada sin WooCommerce: {$name}." );
		$condition = $c[ $name ];
		$condition->get_options();
		$label = $condition->get_label();
		check_more( '' !== $label && false === stripos( $label, 'elementor' ) && preg_match( '/[a-záéíóúñ]/u', $label ), "Etiqueta en español y sin el constructor original: {$name}." );
		check_more( isset( $condition->controls['comparator']['options'], $condition->controls['comparator']['default'] ), "Comparador con opciones y valor por defecto: {$name}." );
		foreach ( $condition->controls as $key => $control ) {
			check_more( in_array( $control['type'], array( 'select', 'select2', 'text', 'date_time', 'query' ), true ), "El modal no sabe pintar {$name}.{$key}." );
			if ( isset( $control['options'] ) ) {
				foreach ( $control['options'] as $option ) {
					check_more( false === stripos( (string) $option, 'elementor' ), "Opción sin el constructor original: {$name}.{$key}." );
				}
			}
		}
		$config = $condition->get_config();
		check_more( Guard::NOTICE === ( $config['digitalisimo_notice'] ?? '' ) && $label === $config['label'], "El aviso común va en la configuración del editor: {$name}." );
	}
	check_more( 'Decide qué se muestra, no quién puede leerlo: el contenido oculto sigue en el código de la página para otros canales y una cookie la puede cambiar el visitante.' === Guard::NOTICE, 'El aviso es el texto de 3.4.1.' );
	check_more( array( 'is_not_empty', 'is_empty', 'is', 'is_not' ) === array_keys( $c['digitalisimo_cookie']->controls['comparator']['options'] ) && 'Existe' === $c['digitalisimo_cookie']->controls['comparator']['options']['is_not_empty'], 'Cookie: existe, no existe, es y no es; nunca «contiene».' );
	check_more( 'digitalisimo_woocommerce' === $c['digitalisimo_product_stock_of']->get_group() && 'digitalisimo_visitor' === $c['digitalisimo_cookie']->get_group() && 'digitalisimo_visitor' === $c['digitalisimo_page_visit']->get_group() && 'user' === $c['digitalisimo_user_field']->get_group() && 'page' === $c['digitalisimo_field']->get_group(), 'Grupos de 3.4.1: el de WooCommerce sólo se ofrece con WooCommerce.' );
	check_more( 'Administrador' === $c['digitalisimo_author']->controls['roles']['options']['administrator'] && false === $c['digitalisimo_author']->controls['roles']['required'], 'Autor: roles traducidos y opcionales (sólo para «tiene» y «no tiene»).' );
	check_more( 'publish' === $c['digitalisimo_page_visit']->controls['pages']['autocomplete']['query']['post_status'] && 'product' === $c['digitalisimo_product_stock_of']->controls['products']['autocomplete']['query']['post_type'], 'Consultas de páginas publicadas y de productos.' );

	// Referencias de kit.
	$refs = Display_Conditions::kit_references( array( array( 'element', '*', 'x', 'post' ) ) );
	check_more( 3 === count( $refs ) && in_array( array( 'condition', 'digitalisimo_page_visit', 'pages.*', 'post', true ), $refs, true ) && in_array( array( 'condition', 'digitalisimo_product_stock_of', 'products.*', 'post', true ), $refs, true ), 'Las dos referencias con IDs, con gate, sin tocar las demás.' );

	// 2. Caché.
	foreach ( array( 'digitalisimo_field', 'digitalisimo_user_field', 'digitalisimo_author', 'digitalisimo_search' ) as $name ) {
		check_more( in_array( $name, Visitor_Content::CACHEABLE_CONDITIONS, true ) && ! Visitor_Content::varies_by_visitor( array( $name ) ), "Cacheable: {$name}." );
	}
	foreach ( array( 'digitalisimo_cookie', 'digitalisimo_page_visit', 'digitalisimo_product_stock_of' ) as $name ) {
		check_more( ! in_array( $name, Visitor_Content::CACHEABLE_CONDITIONS, true ) && Visitor_Content::varies_by_visitor( array( $name ) ), "Variable: {$name}." );
	}
	check_more( array( 'visit_count', 'session_count' ) === Visitor_Content::COOKIE_CONDITIONS, 'COOKIE_CONDITIONS sin cambios: ninguna condición nueva escribe cookies desde PHP.' );
	check_more( ! Visitor_Content::varies_by_visitor( array( Guard::SENTINEL_CONDITION ) ), 'El centinela es cacheable: no saca la página de la caché.' );
	$v4 = array( 'display-conditions' => array( '$$type' => 'display-conditions', 'value' => array( array( '$$type' => 'condition-group', 'value' => array( array( '$$type' => 'string', 'value' => '{"condition":"digitalisimo_cookie","name":"vip","comparator":"is_not_empty"}' ) ) ) ) ) );
	check_more( true === Visitor_Content::is_dynamic_content( false, array( 'elType' => 'e-div-block', 'settings' => $v4 ) ), 'Un elemento v4 con condiciones es dinámico en la caché de elementos (V-I9).' );
	check_more( false === Visitor_Content::is_dynamic_content( false, array( 'elType' => 'e-div-block', 'settings' => array( 'display-conditions' => array( '$$type' => 'display-conditions', 'value' => array() ) ) ) ) && false === Visitor_Content::is_dynamic_content( false, array( 'elType' => 'e-div-block', 'settings' => array( 'display-conditions' => null ) ) ), 'Un prop v4 vacío no cambia la caché.' );

	// 3. Cookie.
	$cookie = $c['digitalisimo_cookie'];
	$denied = array( 'wordpress_logged_in_abc', 'wordpress_sec_1', 'wp-postpass_abc', 'wp_postpass_abc', 'wp-settings-1', 'wp-settings-time-1', 'wp_settings_1', 'wp_woocommerce_session_abc', 'woocommerce_cart_hash', 'woocommerce_items_in_cart', 'comment_author_email_abc', 'comment_author_abc', 'PHPSESSID', 'phpsessid', 'my.session', 'Mi Sess', 'x_auth', 'AUTHOR_pref', 'csrftoken', 'xsrf_token', 'login_hint', 'user pass', 'client_secret', 'api-key', 'KeyBoard', 'myhash', 'nonce_1' );
	foreach ( $denied as $name ) {
		$_COOKIE[ str_replace( array( '.', ' ' ), '_', $name ) ] = 'abc';
		check_more( '' === Guard::cookie_name( $name ) && ! more_check( $cookie, array( 'name' => $name, 'comparator' => 'is_not_empty' ) ) && ! more_check( $cookie, array( 'name' => $name, 'comparator' => 'is', 'value' => 'abc' ) ), "Cookie prohibida: {$name}." );
	}
	$_COOKIE = array( 'ab_test' => 'b', 'promo_code' => 'x', 'lista' => array( 'a' ), 'vacia' => '' );
	check_more( 'ab_test' === Guard::cookie_name( 'ab.test' ) && 'ab_test' === Guard::cookie_name( ' ab test ' ) && more_check( $cookie, array( 'name' => 'ab.test', 'comparator' => 'is_not_empty' ) ), 'El nombre se normaliza como PHP (punto y espacio a guion bajo).' );
	check_more( more_check( $cookie, array( 'name' => 'ab_test', 'comparator' => 'is', 'value' => 'b' ) ) && ! more_check( $cookie, array( 'name' => 'ab_test', 'comparator' => 'is', 'value' => 'B' ) ) && more_check( $cookie, array( 'name' => 'ab_test', 'comparator' => 'is_not', 'value' => 'a' ) ), 'Es y no es comparan el valor exacto.' );
	check_more( ! more_check( $cookie, array( 'name' => 'ab_test', 'comparator' => 'is', 'value' => '' ) ) && ! more_check( $cookie, array( 'name' => 'ab_test', 'comparator' => 'is_not', 'value' => '' ) ), 'Un valor esperado vacío nunca cumple.' );
	check_more( ! more_check( $cookie, array( 'name' => 'ab_test', 'comparator' => 'contains', 'value' => 'b' ) ) && ! more_check( $cookie, array( 'name' => 'ab_test', 'comparator' => 'not_contain', 'value' => 'z' ) ), '«Contiene» no existe en la cookie: nunca cumple.' );
	check_more( more_check( $cookie, array( 'name' => 'falta', 'comparator' => 'is_empty' ) ) && ! more_check( $cookie, array( 'name' => 'falta', 'comparator' => 'is_not_empty' ) ) && more_check( $cookie, array( 'name' => 'vacia', 'comparator' => 'is_empty' ) ), 'Existe y no existe.' );
	check_more( ! more_check( $cookie, array( 'name' => 'lista', 'comparator' => 'is_empty' ) ) && ! more_check( $cookie, array( 'name' => 'lista', 'comparator' => 'is_not_empty' ) ), 'Una cookie con corchetes (lista) no se compara.' );
	foreach ( array( '', 'ñandú', 'a;b', 'a=b', str_repeat( 'a', 65 ), array( 'x' ) ) as $name ) {
		check_more( '' === Guard::cookie_name( $name ) && ! more_check( $cookie, array( 'name' => $name, 'comparator' => 'is_empty' ) ), 'Un nombre inválido no cumple ningún comparador.' );
	}
	$GLOBALS['mc']['filters']['digitalisimo_elements_cookie_condition_denied'] = function ( $list ) { return array_merge( $list, array( 'promo' ) ); };
	check_more( '' === Guard::cookie_name( 'promo_code' ) && '' === Guard::cookie_name( 'wordpress_x' ), 'El filtro amplía los vetos.' );
	$GLOBALS['mc']['filters']['digitalisimo_elements_cookie_condition_denied'] = function () { return array(); };
	check_more( '' === Guard::cookie_name( 'wordpress_x' ) && '' === Guard::cookie_name( 'mi_token' ), 'El filtro no puede quitar los vetos propios.' );
	unset( $GLOBALS['mc']['filters']['digitalisimo_elements_cookie_condition_denied'] );

	// 4. Visitó la página.
	$visit = $c['digitalisimo_page_visit'];
	$_COOKIE = array();
	check_more( ! more_check( $visit, array( 'comparator' => 'is_one_of', 'pages' => array( array( 'id' => 12 ) ) ) ) && more_check( $visit, array( 'comparator' => 'is_none_of', 'pages' => array( array( 'id' => 12 ) ) ) ), 'Sin cookie: «visitó» no se cumple y «no visitó» sí.' );
	$_COOKIE = array( 'digi_seen' => base_convert( '12', 10, 36 ) . '.' . base_convert( '480', 10, 36 ) );
	check_more( more_check( $visit, array( 'comparator' => 'is_one_of', 'pages' => array( array( 'id' => 480 ), array( 'id' => 9 ) ) ) ) && ! more_check( $visit, array( 'comparator' => 'is_none_of', 'pages' => array( array( 'id' => 480 ) ) ) ), 'Lee los IDs en base 36 de la cookie del sitio.' );
	check_more( ! more_check( $visit, array( 'comparator' => 'is_one_of', 'pages' => array() ) ) && ! more_check( $visit, array( 'comparator' => 'is_none_of', 'pages' => array() ) ) && ! more_check( $visit, array( 'comparator' => 'is_none_of' ) ), 'Lista vacía: nunca cumple, con ningún comparador.' );
	check_more( ! more_check( $visit, array( 'comparator' => 'is', 'pages' => array( 12 ) ) ), 'Un comparador desconocido no cumple.' );
	$_COOKIE = array( 'digi_seen' => 'c.<script>' );
	check_more( more_check( $visit, array( 'comparator' => 'is_none_of', 'pages' => array( 12 ) ) ), 'Una cookie dañada cuenta como ninguna visita.' );

	// 5. Campo del contenido o del término.
	$field = $c['digitalisimo_field'];
	$GLOBALS['mc']['singular']  = true;
	$GLOBALS['mc']['queried']   = new WP_Post( 10 );
	$GLOBALS['mc']['the_id']    = 99;
	$GLOBALS['mc']['post_meta'] = array(
		10 => array( 'color' => 'Rojo', 'precio' => '12.50', 'fecha' => '2026-10-21', 'ymd' => '20261021', 'hora' => '2026-10-21 23:30:00', 'stamp' => '1792000000', 'vacio' => '', 'api_key' => 'x', '_privado' => 'x', 'interno' => 'x' ),
		99 => array( 'color' => 'Azul' ),
	);
	$f = array( 'source' => 'post', 'key' => 'color' );
	check_more( more_check( $field, $f + array( 'comparator' => 'is', 'value' => 'rojo' ) ) && ! more_check( $field, $f + array( 'comparator' => 'is', 'value' => 'azul' ) ), 'Compara el contenido consultado, no el del bucle (get_the_ID).' );
	check_more( more_check( $field, $f + array( 'comparator' => 'contains', 'value' => 'oj' ) ) && more_check( $field, $f + array( 'comparator' => 'not_contain', 'value' => 'verde' ) ) && more_check( $field, $f + array( 'comparator' => 'is_not', 'value' => 'verde' ) ), 'Texto sin mayúsculas.' );
	check_more( ! more_check( $field, $f + array( 'comparator' => 'contains', 'value' => '' ) ) && ! more_check( $field, $f + array( 'comparator' => 'is', 'value' => '' ) ) && ! more_check( $field, $f + array( 'comparator' => 'not_contain' ) ), 'Un valor esperado vacío nunca cumple.' );
	check_more( more_check( $field, array( 'key' => 'vacio', 'comparator' => 'is_empty' ) ) && more_check( $field, array( 'key' => 'falta', 'comparator' => 'is_empty' ) ) && more_check( $field, $f + array( 'comparator' => 'is_not_empty' ) ), 'Está vacío y no está vacío.' );
	check_more( more_check( $field, array( 'key' => 'precio', 'comparator' => 'is_less_than_inclusive', 'value' => '12,5' ) ) && ! more_check( $field, array( 'key' => 'precio', 'comparator' => 'is_greater_than_inclusive', 'value' => '13' ) ) && ! more_check( $field, $f + array( 'comparator' => 'is_less_than_inclusive', 'value' => '1' ) ), 'Números con decimales; un texto no es un número.' );
	foreach ( array( 'fecha', 'ymd', 'hora' ) as $key ) {
		check_more( more_check( $field, array( 'key' => $key, 'comparator' => 'is_before', 'value' => '2026-10-22' ) ) && more_check( $field, array( 'key' => $key, 'comparator' => 'is_after', 'value' => '2026-10-20' ) ) && ! more_check( $field, array( 'key' => $key, 'comparator' => 'is_after', 'value' => '2026-10-21' ) ), "Fechas por día: {$key}." );
	}
	check_more( more_check( $field, array( 'key' => 'stamp', 'comparator' => 'is_after', 'value' => '2026-10-01' ) ) && ! more_check( $field, array( 'key' => 'fecha', 'comparator' => 'is_before', 'value' => '10-22-2026' ) ) && ! more_check( $field, array( 'key' => 'fecha', 'comparator' => 'is_before', 'value' => '2026-02-30' ) ) && ! more_check( $field, $f + array( 'comparator' => 'is_before', 'value' => '2026-10-22' ) ), 'Marca de tiempo en la zona del sitio; la fecha esperada sólo AAAA-MM-DD; un texto no es una fecha.' );
	foreach ( array( '_privado', 'api_key', 'user_pass', 'session_token', 'wp_salt', 'nonce', 'clave hash', 'a/b', '' ) as $key ) {
		check_more( '' === \Digitalisimo\Elements\Conditions\Field_Condition::field_key( $key ) && ! more_check( $field, array( 'key' => $key, 'comparator' => 'is_empty' ) ), "Clave rechazada (D25/R14): {$key}." );
	}
	$GLOBALS['mc']['filters']['digitalisimo_elements_field_condition_denied'] = function ( $list ) { return array_merge( $list, array( 'intern' ) ); };
	check_more( ! more_check( $field, array( 'key' => 'interno', 'comparator' => 'is_not_empty' ) ), 'El filtro amplía las claves rechazadas.' );
	unset( $GLOBALS['mc']['filters']['digitalisimo_elements_field_condition_denied'] );
	$GLOBALS['mc']['singular'] = false;
	check_more( ! more_check( $field, $f + array( 'comparator' => 'is_not_empty' ) ) && ! more_check( $field, array( 'key' => 'vacio', 'comparator' => 'is_empty' ) ), 'Fuera de una vista individual no cumple ningún comparador.' );
	$GLOBALS['mc']['taxonomy']  = 'product_cat';
	$GLOBALS['mc']['queried']   = new WP_Term( 7, 'product_cat' );
	$GLOBALS['mc']['term_meta'] = array( 7 => array( 'color' => 'Verde' ) );
	check_more( more_check( $field, array( 'source' => 'term', 'key' => 'color', 'comparator' => 'is', 'value' => 'verde' ) ) && ! more_check( $field, array( 'source' => 'post', 'key' => 'color', 'comparator' => 'is_not_empty' ) ), 'Término consultado con source = term.' );
	$GLOBALS['mc']['taxonomy'] = false;
	check_more( ! more_check( $field, array( 'source' => 'term', 'key' => 'color', 'comparator' => 'is_not_empty' ) ), 'Sin archivo de término, source = term no cumple.' );

	// 6. Campo del usuario.
	$user_field = $c['digitalisimo_user_field'];
	check_more( array( 'first_name', 'last_name', 'nickname', 'description', 'locale', 'billing_country' ) === array_keys( $user_field->controls['key']['options'] ) && 'Nombre' === $user_field->controls['key']['options']['first_name'], 'Lista blanca de D25 con etiquetas en español.' );
	$GLOBALS['mc']['user_meta'] = array( 9 => array( 'first_name' => 'Ana', 'billing_country' => 'MX', 'twitter' => '@ana', 'session_tokens' => 'x' ) );
	check_more( ! more_check( $user_field, array( 'key' => 'first_name', 'comparator' => 'is_empty' ) ) && ! more_check( $user_field, array( 'key' => 'first_name', 'comparator' => 'is_not', 'value' => 'x' ) ), 'Sin sesión no se cumple ningún comparador.' );
	$GLOBALS['mc']['user'] = 9;
	check_more( more_check( $user_field, array( 'key' => 'first_name', 'comparator' => 'is', 'value' => 'ana' ) ) && more_check( $user_field, array( 'key' => 'last_name', 'comparator' => 'is_empty' ) ) && more_check( $user_field, array( 'key' => 'billing_country', 'comparator' => 'is', 'value' => 'MX' ) ), 'Campos de la lista blanca del usuario actual.' );
	check_more( ! more_check( $user_field, array( 'key' => 'twitter', 'comparator' => 'is_not_empty' ) ) && ! more_check( $user_field, array( 'key' => 'session_tokens', 'comparator' => 'is_not_empty' ) ), 'Una clave fuera de la lista no se compara.' );
	$GLOBALS['mc']['filters']['digitalisimo_elements_field_condition_user_keys'] = function ( $keys ) { return array_merge( $keys, array( 'twitter', 'session_tokens', '_secreto' ) ); };
	check_more( more_check( $user_field, array( 'key' => 'twitter', 'comparator' => 'contains', 'value' => 'ana' ) ) && ! more_check( $user_field, array( 'key' => 'session_tokens', 'comparator' => 'is_not_empty' ) ) && ! isset( \Digitalisimo\Elements\Conditions\User_Field_Condition::keys()['_secreto'] ), 'El filtro amplía la lista, pero nunca con metas protegidas ni secretas.' );
	unset( $GLOBALS['mc']['filters']['digitalisimo_elements_field_condition_user_keys'] );

	// 7. Autor.
	$author = $c['digitalisimo_author'];
	$GLOBALS['mc']['singular'] = true;
	$GLOBALS['mc']['queried']  = new WP_Post( 10 );
	$GLOBALS['mc']['authors']  = array( 10 => 5 );
	$GLOBALS['mc']['roles']    = array( 5 => array( 'editor' ) );
	$GLOBALS['mc']['user']     = 5;
	check_more( more_check( $author, array( 'comparator' => 'is_current_user' ) ) && ! more_check( $author, array( 'comparator' => 'is_not_current_user' ) ), 'El autor del contenido consultado es el usuario actual.' );
	$GLOBALS['mc']['user'] = 0;
	check_more( ! more_check( $author, array( 'comparator' => 'is_current_user' ) ) && more_check( $author, array( 'comparator' => 'is_not_current_user' ) ), 'Un visitante anónimo no es el autor (igual para todos: cacheable).' );
	check_more( more_check( $author, array( 'comparator' => 'role_in', 'roles' => array( 'editor', 'author' ) ) ) && ! more_check( $author, array( 'comparator' => 'role_not_in', 'roles' => array( 'editor' ) ) ) && more_check( $author, array( 'comparator' => 'role_not_in', 'roles' => array( 'administrator' ) ) ), 'Rol del autor.' );
	check_more( ! more_check( $author, array( 'comparator' => 'role_in', 'roles' => array() ) ) && ! more_check( $author, array( 'comparator' => 'role_not_in', 'roles' => array() ) ) && ! more_check( $author, array( 'comparator' => 'role_not_in' ) ), 'Lista de roles vacía: nunca cumple.' );
	$GLOBALS['mc']['singular'] = false;
	check_more( ! more_check( $author, array( 'comparator' => 'is_not_current_user' ) ) && ! more_check( $author, array( 'comparator' => 'role_not_in', 'roles' => array( 'administrator' ) ) ), 'Sin contenido ni archivo de autor no cumple ningún comparador.' );
	$GLOBALS['mc']['author']  = true;
	$GLOBALS['mc']['queried'] = 5;
	check_more( more_check( $author, array( 'comparator' => 'role_in', 'roles' => array( 'editor' ) ) ), 'En el archivo de autor, el autor del archivo.' );
	$GLOBALS['mc']['author'] = false;

	// 8. Existencias de productos concretos: sin WooCommerce, false; con él, alguno o todos.
	$stock = $c['digitalisimo_product_stock_of'];
	$p     = array( array( 'id' => 50 ), array( 'id' => 60 ) );
	foreach ( array( 'instock', 'outofstock', 'onbackorder', 'is_less_than_inclusive', 'is_greater_than_inclusive' ) as $comparator ) {
		foreach ( array( 'any', 'all' ) as $match ) {
			check_more( ! more_check( $stock, array( 'products' => $p, 'match' => $match, 'comparator' => $comparator, 'value' => '9' ) ) && ! more_check( $stock, array( 'products' => array(), 'match' => $match, 'comparator' => $comparator ) ), "Sin WooCommerce no se cumple ({$comparator}, {$match})." );
		}
	}
	eval( 'class WooCommerce {}' );
	check_more( more_check( $stock, array( 'products' => $p, 'match' => 'any', 'comparator' => 'outofstock' ) ) && ! more_check( $stock, array( 'products' => $p, 'match' => 'all', 'comparator' => 'outofstock' ) ) && more_check( $stock, array( 'products' => array( array( 'id' => 60 ) ), 'match' => 'all', 'comparator' => 'outofstock' ) ), 'Alguno y todos por estado.' );
	check_more( more_check( $stock, array( 'products' => $p, 'match' => 'any', 'comparator' => 'is_less_than_inclusive', 'value' => '5' ) ) && ! more_check( $stock, array( 'products' => $p, 'match' => 'all', 'comparator' => 'is_less_than_inclusive', 'value' => '5' ) ) && more_check( $stock, array( 'products' => array( array( 'id' => 70 ) ), 'comparator' => 'is_less_than_inclusive', 'value' => '0' ) ), 'Cantidad: sin gestión de inventario no hay cantidad y no cumple.' );
	check_more( ! more_check( $stock, array( 'products' => array(), 'match' => 'all', 'comparator' => 'outofstock' ) ) && ! more_check( $stock, array( 'products' => array( array( 'id' => 60 ), array( 'id' => 404 ) ), 'match' => 'all', 'comparator' => 'outofstock' ) ) && ! more_check( $stock, array( 'products' => $p, 'comparator' => 'is', 'value' => '3' ) ), 'Lista vacía, producto que no existe o comparador desconocido: no cumple.' );

	// 9. Búsqueda.
	$search = $c['digitalisimo_search'];
	$GLOBALS['mc']['query'] = '  Zapatos ROJOS ';
	check_more( ! more_check( $search, array( 'comparator' => 'is', 'value' => 'zapatos rojos' ) ) && ! more_check( $search, array( 'comparator' => 'no_results' ) ), 'Fuera de la página de resultados no cumple.' );
	$GLOBALS['mc']['search'] = true;
	check_more( more_check( $search, array( 'comparator' => 'is', 'value' => 'Zapatos Rojos' ) ) && more_check( $search, array( 'comparator' => 'contains', 'value' => 'rojo' ) ) && more_check( $search, array( 'comparator' => 'not_contain', 'value' => 'botas' ) ) && more_check( $search, array( 'comparator' => 'is_not', 'value' => 'botas' ) ), 'Término recortado y en minúsculas.' );
	check_more( more_check( $search, array( 'comparator' => 'is_one_of', 'value' => 'botas, zapatos rojos ,' ) ) && ! more_check( $search, array( 'comparator' => 'is_one_of', 'value' => ' , ' ) ) && ! more_check( $search, array( 'comparator' => 'contains', 'value' => '' ) ) && ! more_check( $search, array( 'comparator' => 'is', 'value' => '.*' ) ), 'Lista separada por comas, sin expresiones regulares; vacía no cumple.' );
	check_more( more_check( $search, array( 'comparator' => 'no_results' ) ) && ! more_check( $search, array( 'comparator' => 'has_results' ) ), 'Búsqueda sin resultados.' );
	$GLOBALS['wp_query']->found_posts = 3;
	check_more( ! more_check( $search, array( 'comparator' => 'no_results' ) ) && more_check( $search, array( 'comparator' => 'has_results' ) ), 'Búsqueda con resultados.' );
	$GLOBALS['mc']['search'] = false;

	// 10. Centinela con el código de 4.3.0.163.
	$source = file_get_contents( $module . 'class-conditions.php' );
	$v163   = preg_replace( '/^[ \t]*\/\/ F7a · centinela: inicio\n.*?^[ \t]*\/\/ F7a · centinela: fin\n/ms', '', $source, -1, $blocks );
	check_more( 1 === $blocks && '1ba5f9da1f1aa28cf092586a1b76315a18b08eee6e7e7d7abfe750928dd22e42' === hash( 'sha256', $v163 ), 'class-conditions.php sin el bloque del centinela es byte a byte el de 4.3.0.163 (Release v2026.10.21.417).' );
	check_more( false !== strpos( $source, "array( '" . Guard::SENTINEL_PAGE . "' ) === self::values( \$args['pages'] )" ), 'Special_Page_Condition reconoce exactamente la página del centinela.' );
	foreach ( array( 'classes/and-condition.php' => '31f3546788cbc88c91737e1861a3ef8737745ff12f8bdc8db443203afe54c41d', 'classes/or-condition.php' => 'e38eb9bdaa87e7d8c494ec2ff70c09f5f78203d7f6617ce16f50319fa147b9ba' ) as $file => $sha ) {
		check_more( $sha === hash_file( 'sha256', $pro . $file ), "La evaluación de PRO ({$file}) es la de 4.3.0.163." );
	}
	$v163 = str_replace( array( '<?php', 'namespace Digitalisimo\Elements\Conditions;', "require_once __DIR__ . '/class-visitor.php';" ), array( '', 'namespace Digitalisimo\Elements\Conditions\V163;', '' ), $v163 );
	eval( $v163 );
	$old = new More_Manager();
	foreach ( array( 'User', 'Operating_System', 'Browser', 'Language', 'Country', 'Url_Parameter', 'Url_Path', 'Search_Engine', 'Post_Type', 'Specific_Content', 'Special_Page', 'Shortcode' ) as $short ) {
		$class = '\\Digitalisimo\\Elements\\Conditions\\V163\\' . $short . '_Condition';
		$old->register_condition_instance( new $class() );
	}
	$_COOKIE  = array( 'vip' => '1' );
	$sentinel = Guard::sentinel();
	$cookie_c = array( 'condition' => 'digitalisimo_cookie', 'name' => 'vip', 'comparator' => 'is_not_empty' );
	$none_c   = array( 'condition' => 'digitalisimo_page_visit', 'comparator' => 'is_none_of', 'pages' => array( array( 'id' => 12 ) ) );
	$stock_c  = array( 'condition' => 'digitalisimo_product_stock_of', 'products' => array( array( 'id' => 50 ) ), 'match' => 'all', 'comparator' => 'outofstock' );
	$search_c = array( 'condition' => 'digitalisimo_special_page', 'comparator' => 'is_one_of', 'pages' => array( 'search' ) );
	check_more( 12 === count( $old->conditions ) && false === $old->get_condition( 'digitalisimo_cookie' ), '4.3.0.163 no conoce las condiciones de F7a.' );
	foreach ( array( $cookie_c, $none_c, $stock_c ) as $condition ) {
		check_more( true === more_visible( $old, array( array( $condition ) ) ), '4.3.0.163 sin centinela: una condición desconocida se cumple (fallaría abierto): ' . $condition['condition'] );
		check_more( false === more_visible( $old, array( array( $condition, $sentinel ) ) ), '4.3.0.163 con centinela: el grupo no se cumple: ' . $condition['condition'] );
		check_more( false === more_visible( $old, array( array( $condition, $sentinel ), array( $search_c ) ) ), '4.3.0.163: con otro grupo O que no se cumple, el elemento sigue oculto: ' . $condition['condition'] );
	}
	check_more( true === more_visible( $old, array( array( $cookie_c, $sentinel ), array( array( 'condition' => 'digitalisimo_special_page', 'comparator' => 'is_none_of', 'pages' => array( 'search' ) ) ) ) ), '4.3.0.163: los grupos sin condiciones nuevas siguen evaluándose igual.' );
	check_more( true === more_visible( $manager, array( array( $cookie_c, $sentinel ) ) ) && false === more_visible( $manager, array( array( array( 'condition' => 'digitalisimo_cookie', 'name' => 'otra', 'comparator' => 'is_not_empty' ), $sentinel ) ) ), '4.3.0.164: el centinela se cumple y decide la condición.' );
	check_more( false === more_visible( $manager, array( array( array( 'condition' => 'digitalisimo_special_page', 'comparator' => 'is_one_of', 'pages' => array( Guard::SENTINEL_PAGE, 'search' ) ) ) ) ), 'Sólo la lista exacta del centinela se da por cumplida.' );

	// 11. Guardado: centinela en v3, en el formato antiguo y en v4; idempotente; un documento sin F7a sale igual.
	mc_reset( array( 'caps' => array( 'unfiltered_html' ) ) );
	$v3   = function ( array $groups ) { return array( 'e_display_conditions' => array( Groups::json( $groups ) ) ); };
	$el   = function ( $id, array $settings, array $children = array() ) { return array( 'id' => $id, 'elType' => 'container', 'settings' => $settings, 'elements' => $children ); };
	$read = function ( array $element, $key = 'e_display_conditions' ) { return Groups::read( $element['settings'], $key ); };
	$plain = array(
		'elements' => array( $el( 'p1', $v3( array( array( $search_c ) ) ), array( $el( 'p2', array( 'title' => 'x' ) ) ) ) ),
		'settings' => array( 'hide_title' => 'yes' ),
	);
	check_more( $plain === Guard::on_save( $plain, new More_Document( 10 ) ), 'Un documento sin condiciones de F7a sale idéntico.' );
	$data = array(
		'elements' => array(
			$el(
				'a1',
				$v3( array( array( $cookie_c ), array( $search_c ) ) ),
				array(
					$el( 'a2', array( 'e_display_conditions' => Groups::json( array( $none_c, $search_c ) ) ) ),
					array( 'id' => 'a3', 'elType' => 'e-div-block', 'settings' => $v4 ),
					array( 'id' => 'm1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-unconverted', 'settings' => $v3( array( array( $cookie_c ) ) ) ),
				)
			),
		),
	);
	$saved  = Guard::on_save( $data, new More_Document( 10 ) );
	$groups = $read( $saved['elements'][0] );
	check_more( array( array( $cookie_c, $sentinel ), array( $search_c ) ) === $groups && is_array( $saved['elements'][0]['settings']['e_display_conditions'] ), 'v3: el centinela va al final de cada grupo con una condición nueva, sólo ahí, y el formato se conserva.' );
	check_more( array( array( $none_c, $search_c, $sentinel ) ) === $read( $saved['elements'][0]['elements'][0] ) && is_string( $saved['elements'][0]['elements'][0]['settings']['e_display_conditions'] ), 'Formato antiguo (lista plana, cadena): un grupo con el centinela.' );
	$prop = $saved['elements'][0]['elements'][1]['settings']['display-conditions'];
	$last = end( $prop['value'][0]['value'] );
	check_more( 'display-conditions' === $prop['$$type'] && 'condition-group' === $prop['value'][0]['$$type'] && 'string' === $last['$$type'] && Guard::is_sentinel( json_decode( $last['value'], true ) ), 'v4: el centinela entra en el prop como un string más del grupo.' );
	$strings  = array_map( function ( $item ) { return $item['value']; }, $prop['value'][0]['value'] );
	$resolved = ( new \ElementorPro\Modules\AtomicWidgets\Transformers\Condition_Group() )->transform( $strings, new \Elementor\Modules\AtomicWidgets\PropsResolver\Props_Resolver_Context() );
	check_more( '9cde19e1fc0ca5db0a5d90f339df7ac4b93f40bdfd08b734c506730b698c1357' === hash_file( 'sha256', $elements . 'modules/atomic-widgets/transformers/condition-group.php' ) && Guard::is_sentinel( end( $resolved ) ) && false === more_visible( $old, array( $resolved ) ), 'v4 en 4.3.0.163: el transformador de PRO decodifica el centinela y el grupo no se cumple.' );
	check_more( $data['elements'][0]['elements'][2] === $saved['elements'][0]['elements'][2], 'Un marcador de K no se toca.' );
	check_more( $saved === Guard::on_save( $saved, new More_Document( 10 ) ), 'Guardar otra vez no añade otro centinela.' );

	// 12. Carga: sólo en modo de edición se retira el centinela.
	mc_reset( array( 'admin' => false, 'edit' => true ) );
	check_more( $saved['elements'] === Guard::on_load( $saved['elements'] ), 'En el front (que también aplica load/data) no se decodifica ni se cambia nada.' );
	mc_reset( array( 'admin' => true, 'edit' => false ) );
	check_more( $saved['elements'] === Guard::on_load( $saved['elements'] ), 'Fuera del modo de edición, nada cambia.' );
	mc_reset( array( 'admin' => true, 'edit' => false, 'ajax' => true ) );
	$_REQUEST = array( 'action' => 'heartbeat' );
	check_more( $saved['elements'] === Guard::on_load( $saved['elements'] ), 'Otra petición AJAX de la administración no cambia nada.' );
	$_REQUEST = array( 'action' => 'elementor_ajax' );
	check_more( array( array( $cookie_c ), array( $search_c ) ) === $read( Guard::on_load( $saved['elements'] )[0] ), 'Una plantilla insertada desde la biblioteca (elementor_ajax) llega al editor sin el centinela.' );
	$_REQUEST = array();
	mc_reset( array( 'admin' => true, 'edit' => true, 'caps' => array( 'unfiltered_html' ) ) );
	$loaded = Guard::on_load( $saved['elements'] );
	check_more( array( array( $cookie_c ), array( $search_c ) ) === $read( $loaded[0] ) && array( array( $none_c, $search_c ) ) === $read( $loaded[0]['elements'][0] ), 'En el editor no se ve el centinela (v3).' );
	check_more( array( array( json_decode( $v4['display-conditions']['value'][0]['value'][0]['value'], true ) ) ) === $read( $loaded[0]['elements'][1], 'display-conditions' ), 'En el editor no se ve el centinela (v4).' );
	check_more( $saved === Guard::on_save( array( 'elements' => $loaded ), new More_Document( 10 ) ), 'Cargar y guardar devuelve lo mismo.' );
	$only = Guard::on_load( array( $el( 'o1', $v3( array( array( $sentinel ), array( $search_c ) ) ) ) ) );
	check_more( array( array( $search_c ) ) === $read( $only[0] ), 'Un grupo que sólo tenía el centinela desaparece.' );

	// 13. Cookie sin permiso: se restaura lo guardado o se deja sin nombre; nunca se quita una condición.
	$promo = array( 'condition' => 'digitalisimo_cookie', 'name' => 'promo', 'comparator' => 'is', 'value' => 'si' );
	$vip   = array( 'condition' => 'digitalisimo_cookie', 'name' => 'vip', 'comparator' => 'is_not_empty' );
	$stored = Groups::json( array( $el( 'e1', $v3( array( array( $promo, $sentinel ) ) ) ), $el( 'e2', $v3( array( array( $search_c ) ) ) ) ) );
	$guard  = function ( array $over, array $elements ) use ( $stored ) {
		mc_reset( $over + array( 'post_meta' => array( 10 => array( '_elementor_data' => $stored ) ) ) );
		return Guard::on_save( array( 'elements' => $elements ), new More_Document( 10 ) )['elements'];
	};
	$editor = array( 'caps' => array( 'edit_posts' ) );
	$out    = $guard( $editor, array( $el( 'e1', $v3( array( array( $vip ) ) ) ) ) );
	check_more( array( array( $promo, $sentinel ) ) === $read( $out[0] ), 'Un editor que cambia la cookie de un elemento que existía recupera las condiciones guardadas.' );
	$out = $guard( $editor, array( $el( 'e2', $v3( array( array( $vip, $search_c ) ) ) ) ) );
	check_more( array( array( $search_c ) ) === $read( $out[0] ), 'Un editor que añade una cookie a un elemento que existía recupera las condiciones guardadas.' );
	$out  = $guard( $editor, array( $el( 'n1', $v3( array( array( $vip ) ) ) ) ) );
	$kept = $read( $out[0] );
	check_more( 'digitalisimo_cookie' === $kept[0][0]['condition'] && '' === $kept[0][0]['name'] && Guard::is_sentinel( $kept[0][1] ) && false === more_visible( $manager, $kept ), 'En un elemento nuevo, la cookie que no estaba se queda sin nombre: nunca se cumple.' );
	$out = $guard( $editor, array( $el( 'n2', $v3( array( array( $promo ) ) ) ) ) );
	check_more( array( array( $promo, $sentinel ) ) === $read( $out[0] ), 'Copiar una condición de cookie que ya estaba en el documento no es configurarla.' );
	$out = $guard( $editor, array( $el( 'e1', $v3( array( array( $search_c ) ) ) ) ) );
	check_more( array( array( $search_c ) ) === $read( $out[0] ), 'Quitar una condición de cookie no necesita el permiso.' );
	$out = $guard( $editor, array( array( 'id' => 'n3', 'elType' => 'e-div-block', 'settings' => $v4 ) ) );
	$kept = Groups::read( $out[0]['settings'], 'display-conditions' );
	check_more( '' === $kept[0][0]['name'] && Guard::is_sentinel( $kept[0][1] ), 'La guarda también vale para el prop v4.' );
	$out = $guard( array( 'caps' => array( 'unfiltered_html' ), 'multisite' => true, 'super' => false ), array( $el( 'n1', $v3( array( array( $vip ) ) ) ) ) );
	check_more( '' === $read( $out[0] )[0][0]['name'], 'En una red, unfiltered_html sin superadministrador no basta.' );
	$out = $guard( array( 'caps' => array( 'unfiltered_html' ), 'multisite' => true, 'super' => true ), array( $el( 'e1', $v3( array( array( $vip ) ) ) ) ) );
	check_more( array( array( $vip, $sentinel ) ) === $read( $out[0] ), 'Un superadministrador configura la cookie.' );
	$out = $guard( array( 'caps' => array( 'unfiltered_html' ) ), array( $el( 'e1', $v3( array( array( $vip ) ) ) ) ) );
	check_more( array( array( $vip, $sentinel ) ) === $read( $out[0] ) && Guard::can_configure_cookies(), 'Con unfiltered_html en un sitio único, la cookie se guarda.' );

	// 14. Datos y script del editor (el comportamiento lo ejecuta tests/elements-page-visits.mjs).
	mc_reset( array( 'caps' => array( 'unfiltered_html' ) ) );
	$editor_data = Guard::editor_data();
	check_more( $more === $editor_data['more'] && array() === $editor_data['hide3'] && array( 'header', 'footer' ) === $editor_data['locations'] && Guard::NOTICE === $editor_data['notice'] && Guard::LOCATION_NOTICE === $editor_data['location'], 'Datos del editor: v4 no ofrece las de F7a; un administrador ve la de cookie.' );
	check_more( array() === array_diff( array( 'digitalisimo_field', 'digitalisimo_user_field', 'digitalisimo_author', 'digitalisimo_search' ), $editor_data['cacheable'] ) && ! in_array( 'digitalisimo_cookie', $editor_data['cacheable'], true ), 'El editor sabe qué condiciones varían.' );
	check_more( Guard::VISIT_NOTICE === $editor_data['visit'] && '' === $editor_data['privacy'] && false !== strpos( Guard::VISIT_NOTICE, 'Privacidad' ), 'La ayuda de «Visitó una página» explica el consentimiento; sin manage_options, sin enlace.' );
	mc_reset( array( 'caps' => array( 'unfiltered_html', 'manage_options' ) ) );
	check_more( 'https://a.test/wp-admin/admin.php?page=digitalisimo-elements-front#digitalisimo-front-privacy' === Guard::editor_data()['privacy'], 'Con manage_options, enlace a «Rendimiento» → «Privacidad» del sitio.' );
	mc_reset( array( 'caps' => array( 'edit_posts' ) ) );
	check_more( array( 'digitalisimo_cookie' ) === Guard::editor_data()['hide3'], 'Quien no puede guardarla no ve la condición de cookie.' );
	Guard::editor_script();
	check_more( array() === $GLOBALS['mc']['inline'], 'Sin el modal de condiciones no se añade nada.' );
	mc_reset( array( 'scripts' => array( 'e-display-conditions' ) ) );
	Guard::editor_script();
	$inline = $GLOBALS['mc']['inline'];
	check_more( 1 === count( $inline ) && 'e-display-conditions' === $inline[0][0] && 'before' === $inline[0][2] && 0 === strpos( $inline[0][1], 'window.digitalisimoConditions={' ) && false !== strpos( $inline[0][1], 'elementor/display-conditions/open' ), 'El script va delante del modal, con sus datos.' );
	check_more( false === stripos( Guard::LOCATION_NOTICE . Guard::NOTICE . Guard::VISIT_NOTICE, 'elementor' ) && strlen( Guard::EDITOR_JS ) < 4096, 'Avisos sin el constructor original y script del editor de menos de 4 KB.' );

	echo "F7a · condiciones: las siete registradas siempre, listas vacías y WooCommerce ausente cerrados, cookie vetada y con permiso, caché, centinela comprobado con el código de 4.3.0.163, guardado, carga y editor en verde.\n";
}
