<?php
/**
 * F4-Woo, frente B: lista de deseos (V.4).
 *
 * Con las clases reales del frente A (`Woo\Module` y su autocarga, `Products`, `Rest`, `Privacy`, `Widget`) y dobles de
 * WordPress, WooCommerce y el constructor. Comprueba:
 * - una sola fuente (B1): con `product_wishlist` activa, `POST woo/wishlist` da 409 salvo la mudanza, `GET` también, y el
 *   exportador propio sólo existe mientras quede algún dato propio; sin la ruta de la Store API en REST, modo propio;
 * - privacidad (B2): `GET woo/cards` responde 405, las fichas se piden por POST con el cuerpo acotado y sólo para una
 *   instancia real del widget (también global); el HTML del botón y de la lista es igual para todos y sin estado;
 * - el almacén propio: clave por sitio, tope, validación con `Products::is_public()`, `merge`, permisos (401, 403 sin
 *   pertenencia), purga y contador de listas propias;
 * - el borrado desde la red en el sitio correcto (R-I12), el borrador con sus cuatro campos y el exportador;
 * - la configuración del pie: pública igual para todos, la de la cuenta sólo para miembros, nonce sólo en modo propio o
 *   con mudanza, mudanza sin variables;
 * - recursos: script ≤ 8 KB y hoja ≤ 4 KB, sin jQuery, sin `<script>` ni `--e-global-*`, textos sin «Elementor».
 */

namespace Elementor {
	class Controls_Manager {
		const TAB_STYLE = 'style';
		const SELECT = 'select';
		const SELECT2 = 'select2';
		const TEXT = 'text';
		const TEXTAREA = 'textarea';
		const SWITCHER = 'switcher';
		const RAW_HTML = 'raw_html';
		const COLOR = 'color';
		const SLIDER = 'slider';
		const DIMENSIONS = 'dimensions';
		const NUMBER = 'number';
		const HEADING = 'heading';
	}
	class Group_Control_Typography {
		public static function get_type() { return 'typography'; }
	}
	class Plugin {
		public static $instance;
		public $editor;
		public $preview;
		public $documents;
	}
	abstract class Widget_Base {
		public $controls = array();
		public $settings = array();
		public $id = 'abc1234';
		public function __construct( $settings = array(), $id = 'abc1234' ) { $this->settings = $settings; $this->id = $id; }
		public function get_id() { return $this->id; }
		public function start_controls_section( $id, $args = array() ) {}
		public function end_controls_section() {}
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function add_responsive_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function add_group_control( $type, $args ) { $this->controls[ $args['name'] ] = $args; }
		public function get_settings_for_display( $key = null ) {
			$all = $this->settings;
			foreach ( $this->controls as $id => $control ) {
				if ( ! array_key_exists( $id, $all ) && array_key_exists( 'default', $control ) ) {
					$all[ $id ] = $control['default'];
				}
			}
			return null === $key ? $all : ( $all[ $key ] ?? null );
		}
		public function boot() { $this->register_controls(); return $this; }
		public function html() { ob_start(); $this->render(); return ob_get_clean(); }
	}
}

namespace Elementor\Core\Frontend {
	class Performance {
		public static $optimize = false;
		public static function should_optimize_controls() { return self::$optimize; }
	}
}

namespace Automattic\WooCommerce\Utilities {
	class FeaturesUtil {
		public static $on = array();
		public static function feature_is_enabled( string $id ): bool { return ! empty( self::$on[ $id ] ); }
	}
}

namespace Automattic\WooCommerce\StoreApi\Routes\V1 {
	class ShopperListItems {}
}

namespace Digitalisimo\Elements {
	/** Doble del generador único de imágenes. */
	final class Image_Markup {
		public static function img( array $image, array $args = array() ) {
			return empty( $image['id'] ) ? '' : '<img src="/i/' . (int) $image['id'] . '.jpg" alt="" class="' . ( $args['class'] ?? '' ) . '" data-size="' . ( $args['size'] ?? '' ) . '">';
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', dirname( __DIR__ ) . '/digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.165' );
	define( 'WC_VERSION', '11.2.1' );
	define( 'ARRAY_FILTER_USE_BOTH_SAFE', 1 );

	class WooCommerce {}
	function WC() { return $GLOBALS['wt']['wc']; }

	$GLOBALS['wt'] = array();
	function wt_reset( array $over = array() ) {
		$GLOBALS['wt'] = array_merge(
			array(
				'hooks'     => array(),
				'blog'      => 1,
				'multisite' => false,
				'user'      => 0,
				'members'   => array(),
				'umeta'     => array(),
				'options'   => array( 1 => array(), 3 => array() ),
				'enqueued'  => array(),
				'inline'    => array(),
				'rest_init' => 0,
				'routes'    => array(),
				'queries'   => 0,
				'meta'      => array(),
				'caps'      => array(),
				'edit'      => false,
				'myaccount' => 99,
				'wc'        => (object) array( 'cart' => null ),
			),
			$over
		);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::$on = array();
		\Elementor\Core\Frontend\Performance::$optimize      = false;
		\Elementor\Plugin::$instance                         = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->editor                 = new class() { public function is_edit_mode() { return $GLOBALS['wt']['edit']; } };
		\Elementor\Plugin::$instance->preview                = new class() { public function is_preview_mode() { return false; } };
		\Elementor\Plugin::$instance->documents              = new class() { public function get_current() { return null; } };
		\Digitalisimo\Elements\Woo\Wishlist\Source::reset();
		\Digitalisimo\Elements\Woo\Wishlist\Routes::reset();
	}

	// --- Ganchos ---------------------------------------------------------------------------------------------------
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['wt']['hooks'][ $hook ][] = array( $cb, $priority ); return true; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { return add_action( $hook, $cb, $priority, $args ); }
	function has_action( $hook, $cb = false ) {
		foreach ( $GLOBALS['wt']['hooks'][ $hook ] ?? array() as $row ) {
			if ( false === $cb || $row[0] === $cb ) { return $row[1]; }
		}
		return false;
	}
	function has_filter( $hook, $cb = false ) { return has_action( $hook, $cb ); }
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['wt']['hooks'][ $hook ] ?? array() as $row ) { $value = call_user_func_array( $row[0], array_merge( array( $value ), $args ) ); }
		return $value;
	}
	function do_action( $hook, ...$args ) { foreach ( $GLOBALS['wt']['hooks'][ $hook ] ?? array() as $row ) { call_user_func_array( $row[0], $args ); } }
	function did_action( $hook ) { return 'rest_api_init' === $hook ? $GLOBALS['wt']['rest_init'] : ( 'woocommerce_init' === $hook ? 1 : 0 ); }

	// --- Sitio, usuarios y metas ------------------------------------------------------------------------------------
	function is_multisite() { return $GLOBALS['wt']['multisite']; }
	function get_current_blog_id() { return $GLOBALS['wt']['blog']; }
	function get_current_user_id() { return $GLOBALS['wt']['user']; }
	function is_user_logged_in() { return $GLOBALS['wt']['user'] > 0; }
	function is_user_member_of_blog( $user = 0 ) { return in_array( $user ? $user : $GLOBALS['wt']['user'], $GLOBALS['wt']['members'], true ); }
	function current_user_can( $cap, ...$args ) { return in_array( $cap, $GLOBALS['wt']['caps'], true ); }
	function get_user_meta( $uid, $key, $single = false ) { return $GLOBALS['wt']['umeta'][ $uid ][ $key ] ?? ''; }
	function update_user_meta( $uid, $key, $value ) { $GLOBALS['wt']['umeta'][ $uid ][ $key ] = $value; return true; }
	function delete_user_meta( $uid, $key ) { $had = isset( $GLOBALS['wt']['umeta'][ $uid ][ $key ] ); unset( $GLOBALS['wt']['umeta'][ $uid ][ $key ] ); return $had; }
	function get_user_by( $field, $value ) { return 'a@a.test' === $value ? (object) array( 'ID' => 7 ) : false; }
	function get_option( $name, $default = false ) { return $GLOBALS['wt']['options'][ $GLOBALS['wt']['blog'] ][ $name ] ?? $default; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['wt']['options'][ $GLOBALS['wt']['blog'] ][ $name ] = $value; return true; }
	function update_blog_option( $blog, $name, $value ) { $GLOBALS['wt']['options'][ $blog ][ $name ] = $value; return true; }
	function wp_load_alloptions() { return $GLOBALS['wt']['options'][ $GLOBALS['wt']['blog'] ]; }
	function maybe_unserialize( $v ) { return $v; }

	class WT_DB {
		public $usermeta = 'wp_usermeta';
		public $prefix   = 'wp_';
		public function get_blog_prefix( $blog = null ) { $blog = null === $blog ? $GLOBALS['wt']['blog'] : (int) $blog; return 1 === $blog ? 'wp_' : 'wp_' . $blog . '_'; }
		public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
		public function get_var( $query ) {
			$GLOBALS['wt']['queries']++;
			$key = $query[1][0];
			$n   = 0;
			foreach ( $GLOBALS['wt']['umeta'] as $metas ) { $n += isset( $metas[ $key ] ) ? 1 : 0; }
			return $n;
		}
	}
	$GLOBALS['wpdb'] = new WT_DB();

	// --- Entradas y productos ---------------------------------------------------------------------------------------
	class WT_Product {
		public $id; public $type; public $name; public $visible = true;
		public function __construct( $id, $type = 'simple', $name = '' ) { $this->id = $id; $this->type = $type; $this->name = $name ? $name : 'Producto ' . $id; }
		public function get_id() { return $this->id; }
		public function get_name() { return $this->name; }
		public function is_type( $t ) { return $t === $this->type; }
		public function is_visible() { return $this->visible; }
		public function get_permalink() { return 'https://a.test/p/' . $this->id . '/'; }
		public function get_image_id() { return 100 + $this->id; }
		public function get_price_html() { return '<span class="amount">' . $this->id . ',00&nbsp;€</span>'; }
	}
	$GLOBALS['wt_posts'] = array(
		10 => array( 'product', 'publish', '', 0, 'simple', 'Camiseta <b>roja</b>' ),
		11 => array( 'product', 'publish', '', 0, 'variable', 'Sudadera' ),
		12 => array( 'product', 'draft', '', 0, 'simple', 'Borrador' ),
		14 => array( 'product', 'publish', 'secreto', 0, 'simple', 'Con contraseña' ),
		15 => array( 'product_variation', 'publish', '', 11, 'variation', 'Sudadera - Azul' ),
		16 => array( 'product', 'publish', '', 0, 'simple', 'Gorra' ),
		20 => array( 'page', 'publish', '', 0, '', 'Lista' ),
		21 => array( 'page', 'draft', '', 0, '', 'Borrador' ),
		30 => array( 'page', 'publish', '', 0, '', 'Página con la lista' ),
		31 => array( 'page', 'draft', '', 0, '', 'Página en borrador' ),
		40 => array( 'elementor_library', 'publish', '', 0, '', 'Lista global' ),
	);
	function get_post( $id ) {
		$p = $GLOBALS['wt_posts'][ (int) $id ] ?? null;
		return $p ? (object) array( 'ID' => (int) $id, 'post_type' => $p[0], 'post_status' => $p[1], 'post_password' => $p[2], 'post_parent' => $p[3] ) : null;
	}
	function get_post_status( $id ) { return $GLOBALS['wt_posts'][ (int) $id ][1] ?? false; }
	function get_post_type( $id ) { return $GLOBALS['wt_posts'][ (int) $id ][0] ?? false; }
	function get_permalink( $id ) { return 'https://a.test/?p=' . (int) $id; }
	function get_the_ID() { return 10; }
	function wc_get_product( $id ) {
		$p = $GLOBALS['wt_posts'][ (int) $id ] ?? null;
		return $p && in_array( $p[0], array( 'product', 'product_variation' ), true ) ? new WT_Product( (int) $id, $p[4], $p[5] ) : false;
	}
	function _prime_post_caches( $ids, $terms = true, $meta = true ) { $GLOBALS['wt']['primed'][] = $ids; }
	function update_meta_cache( $type, $ids ) {}
	// `wc_get_stock_html()` y `woocommerce_template_loop_add_to_cart()` se definen después de `Wishlist::init()`, como
	// WooCommerce, que carga sus funciones de plantilla en `after_setup_theme`, tras `plugins_loaded`.
	function wc_get_page_id( $page ) { return $GLOBALS['wt']['myaccount']; }
	function wc_get_page_permalink( $page ) { return 'https://a.test/mi-cuenta/'; }
	function wp_login_url() { return 'https://a.test/acceso-privado/'; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['wt']['meta'][ $id ][ $key ] ?? ''; }

	// --- REST, recursos y escapes -----------------------------------------------------------------------------------
	class WP_Error {
		public $code; public $message; public $data;
		public function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
		public function get_error_data() { return $this->data; }
	}
	function is_wp_error( $v ) { return $v instanceof WP_Error; }
	class WP_REST_Response {
		public $data; public $status; public $headers = array();
		public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
		public function header( $k, $v ) { $this->headers[ $k ] = $v; }
		public function get_status() { return $this->status; }
		public function get_data() { return $this->data; }
	}
	class WP_REST_Request {
		public $method; public $params; public $body; public $route;
		public function __construct( $method, $route, $params = array(), $body = '' ) { $this->method = $method; $this->route = $route; $this->params = $params; $this->body = $body; }
		public function get_param( $k ) { return $this->params[ $k ] ?? null; }
		public function get_body() { return $this->body; }
		public function get_method() { return $this->method; }
		public function get_route() { return $this->route; }
	}
	function rest_ensure_response( $r ) { return $r instanceof WP_REST_Response ? $r : new WP_REST_Response( $r, 200 ); }
	function rest_convert_error_to_response( $e ) { return new WP_REST_Response( array( 'code' => $e->code, 'message' => $e->message ), (int) ( $e->data['status'] ?? 500 ) ); }
	function rest_authorization_required_code() { return 401; }
	function register_rest_route( $ns, $route, $args ) { $GLOBALS['wt']['routes'][ $ns . $route ] = $args; }
	function rest_url( $path = '' ) { return 'https://a.test/wp-json/' . ltrim( $path, '/' ); }
	function admin_url( $path = '' ) { return 'https://a.test/wp-admin/' . $path; }
	function wp_get_nocache_headers() { return array( 'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT', 'Cache-Control' => 'no-cache, must-revalidate, max-age=0, no-store, private' ); }
	function nocache_headers() {}
	function wp_create_nonce( $action ) { return 'n-' . $action . '-' . $GLOBALS['wt']['user']; }
	function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
	function wp_script_is( $h, $what = 'enqueued' ) { return in_array( $h, $GLOBALS['wt']['enqueued'], true ); }
	function wp_add_inline_script( $h, $data, $pos = 'after' ) { $GLOBALS['wt']['inline'][] = array( $h, $data, $pos ); return true; }
	function plugins_url( $path, $file ) { return 'https://a.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8', false ); }
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8', false ); }
	function esc_url( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8', false ); }
	function esc_html__( $t, $d = '' ) { return esc_html( $t ); }
	function __( $t, $d = '' ) { return $t; }
	function wp_strip_all_tags( $t ) { return trim( strip_tags( (string) $t ) ); }
	function wp_kses_post( $t ) { return $t; }
	function __return_true() { return true; }
	function absint( $v ) { return abs( (int) $v ); }
	function wc_get_price_decimals() { return 2; }
	function __return_false() { return false; }

	function check( $ok, $message ) {
		if ( ! $ok ) { throw new RuntimeException( $message ); }
	}

	$root = dirname( __DIR__ ) . '/digitalisimo-elements';
	require_once $root . '/modules/digitalisimo-woo/class-module.php';
	\Digitalisimo\Elements\Woo\Module::register_autoloader();
	use Digitalisimo\Elements\Woo\Rest;
	use Digitalisimo\Elements\Woo\Privacy;
	use Digitalisimo\Elements\Woo\Wishlist\Button;
	use Digitalisimo\Elements\Woo\Wishlist\Button_Widget;
	use Digitalisimo\Elements\Woo\Wishlist\Cards;
	use Digitalisimo\Elements\Woo\Wishlist\List_Widget;
	use Digitalisimo\Elements\Woo\Wishlist\Module as Wishlist;
	use Digitalisimo\Elements\Woo\Wishlist\Personal_Data;
	use Digitalisimo\Elements\Woo\Wishlist\Source;
	use Digitalisimo\Elements\Woo\Wishlist\Store;

	/** Llama a una ruta propia como el servidor REST: permiso y, si pasa, el callback envuelto por `Rest`. */
	function wt_call( $path, $method, array $params = array(), $body = '' ) {
		$route = Rest::routes()[ $path ] ?? null;
		check( is_array( $route ), 'La ruta woo/' . $path . ' está registrada.' );
		foreach ( $route as $endpoint ) {
			if ( $endpoint['methods'] !== $method ) { continue; }
			$request = new WP_REST_Request( $method, Rest::full( $path ), $params, $body );
			\Digitalisimo\Elements\Woo\Wishlist\Routes::reset(); // una petición nueva
			$allowed = call_user_func( $endpoint['permission_callback'], $request );
			if ( true !== $allowed ) {
				return Rest::nocache( rest_convert_error_to_response( $allowed instanceof WP_Error ? $allowed : new WP_Error( 'rest_forbidden', '', array( 'status' => 403 ) ) ) );
			}
			return call_user_func( $endpoint['callback'], $request );
		}
		return new WP_REST_Response( array( 'code' => 'rest_no_route' ), 404 );
	}
	function wt_items( $response ) { return array_map( function ( $row ) { return $row['id']; }, $response->get_data()['items'] ); }

	wt_reset();
	Rest::init();
	Privacy::init();
	// Al arrancar (`plugins_loaded`) WooCommerce aún no cargó sus funciones de plantilla: la guarda no puede exigirlas.
	check( ! function_exists( 'wc_get_stock_html' ) && ! function_exists( 'woocommerce_template_loop_add_to_cart' ), 'Las funciones de plantilla de WooCommerce aún no existen al arrancar.' );
	Wishlist::init();
	check( false !== has_action( 'remove_user_from_blog', array( Personal_Data::class, 'remove_user_from_blog' ) ), 'La limpieza al quitar a un usuario de un sitio está enganchada.' );
	check( false !== has_action( 'wp_print_footer_scripts', array( Wishlist::class, 'footer_config' ) ), 'La configuración se decide en el pie.' );
	check( isset( Rest::routes()['wishlist'], Rest::routes()['cards'] ), 'Las dos rutas de la lista existen aunque las funciones de plantilla lleguen después (`after_setup_theme`).' );
	if ( ! function_exists( 'wc_get_stock_html' ) ) {
		function wc_get_stock_html( $product ) { return '<p class="stock in-stock">Hay existencias</p>'; }
		function woocommerce_template_loop_add_to_cart() { global $product; echo '<a href="?add-to-cart=' . $product->get_id() . '" class="button add_to_cart_button" rel="nofollow">Añadir al carrito</a>'; }
	}
	$hooks_after_init = $GLOBALS['wt']['hooks'];

	// --- Fuente única (V.4.1) ---------------------------------------------------------------------------------------
	check( 'own' === Source::mode(), 'Sin la función de WooCommerce, el almacén propio.' );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::$on['product_wishlist'] = true;
	Source::reset();
	check( 'store' === Source::mode(), 'Con product_wishlist activa (y su ruta), la Store API.' );
	$GLOBALS['wt']['rest_init'] = 1;
	Source::reset();
	$GLOBALS['wt']['rest_server_routes'] = array();
	function rest_get_server() { return new class() { public function get_routes() { return $GLOBALS['wt']['rest_server_routes']; } }; }
	check( 'own' === Source::mode(), 'En REST, sin la ruta registrada, nunca se usa la Store API.' );
	$GLOBALS['wt']['rest_server_routes'] = array( Source::ROUTE => array() );
	Source::reset();
	check( 'store' === Source::mode(), 'En REST, con la ruta registrada, la Store API.' );

	// --- Almacén propio y rutas con sesión ---------------------------------------------------------------------------
	$hooks = $GLOBALS['wt']['hooks'];
	wt_reset( array( 'hooks' => $hooks, 'user' => 0 ) );
	$r = wt_call( 'wishlist', 'GET' );
	check( 401 === $r->get_status() && 'no-store, private' === $r->headers['Cache-Control'], 'Sin sesión: 401 y sin caché.' );
	wt_reset( array( 'hooks' => $hooks, 'user' => 7, 'multisite' => true, 'blog' => 3, 'members' => array() ) );
	check( 403 === wt_call( 'wishlist', 'POST', array( 'op' => 'add', 'product' => 10 ) )->get_status(), 'En una red, un usuario que no es miembro del sitio: 403.' );
	wt_reset( array( 'hooks' => $hooks, 'user' => 7, 'members' => array( 7 ) ) );
	$r = wt_call( 'wishlist', 'POST', array( 'op' => 'add', 'product' => 10 ) );
	check( 200 === $r->get_status() && array( 10 ) === wt_items( $r ), 'Añadir un producto público.' );
	check( isset( $GLOBALS['wt']['umeta'][7]['wp_digitalisimo_wishlist'] ) && 1 === $GLOBALS['wt']['umeta'][7]['wp_digitalisimo_wishlist']['v'], 'Se guarda en la opción de usuario del sitio, con su forma { v: 1, items }.' );
	check( 1 === get_option( Store::LEGACY ), 'El contador de listas propias sube al crear la primera.' );
	foreach ( array( 12, 14, 99 ) as $invalid ) {
		check( 400 === wt_call( 'wishlist', 'POST', array( 'op' => 'add', 'product' => $invalid ) )->get_status(), 'Un producto en borrador, con contraseña o inexistente no se guarda (' . $invalid . ').' );
	}
	$r = wt_call( 'wishlist', 'POST', array( 'op' => 'merge', 'items' => array( 16, 12, 10, 15 ) ) );
	check( array( 10, 16, 15 ) === wt_items( $r ), 'merge añade sólo lo público y lo nuevo, en orden (una variación publicada vale).' );
	$GLOBALS['wt_posts'][16][1] = 'private';
	$r = wt_call( 'wishlist', 'GET' );
	check( array( 10, 15 ) === wt_items( $r ), 'Lo que deja de ser público se omite al leer.' );
	$r = wt_call( 'wishlist', 'POST', array( 'op' => 'remove', 'product' => 10 ) );
	check( array( 15 ) === wt_items( $r ) && 1 === count( $GLOBALS['wt']['umeta'][7]['wp_digitalisimo_wishlist']['items'] ), '…y se purga en la escritura siguiente.' );
	$GLOBALS['wt_posts'][16][1] = 'publish';
	add_filter( 'digitalisimo_elements_wishlist_limit', function () { return 2; } );
	wt_call( 'wishlist', 'POST', array( 'op' => 'add', 'product' => 10 ) );
	$r = wt_call( 'wishlist', 'POST', array( 'op' => 'add', 'product' => 16 ) );
	check( 409 === $r->get_status() && 'digitalisimo_wishlist_full' === $r->get_data()['code'], 'Con la lista llena, 409.' );
	check( 2 === Store::limit(), 'El tope se filtra.' );
	$GLOBALS['wt']['hooks']['digitalisimo_elements_wishlist_limit'] = array( array( function () { return 9999; }, 10 ) );
	check( 500 === Store::limit(), 'El tope filtrado no pasa de 500.' );
	unset( $GLOBALS['wt']['hooks']['digitalisimo_elements_wishlist_limit'] );
	$r = wt_call( 'wishlist', 'POST', array( 'op' => 'clear' ) );
	check( array() === wt_items( $r ) && ! isset( $GLOBALS['wt']['umeta'][7]['wp_digitalisimo_wishlist'] ) && 0 === get_option( Store::LEGACY ), 'Vaciar borra la meta: no queda nada del usuario, y el contador baja.' );

	// --- Con la lista de WooCommerce activa: nada se escribe en el almacén propio salvo la mudanza (B1) ----------------
	wt_reset( array( 'hooks' => $hooks, 'user' => 7, 'members' => array( 7 ) ) );
	wt_call( 'wishlist', 'POST', array( 'op' => 'merge', 'items' => array( 10, 11 ) ) );
	$before = $GLOBALS['wt']['umeta'];
	\Automattic\WooCommerce\Utilities\FeaturesUtil::$on['product_wishlist'] = true;
	Source::reset();
	foreach ( array( array( 'GET', array() ), array( 'POST', array( 'op' => 'add', 'product' => 16 ) ), array( 'POST', array( 'op' => 'merge', 'items' => array( 16 ) ) ), array( 'POST', array( 'op' => 'clear' ) ), array( 'POST', array( 'op' => 'remove', 'product' => 10 ) ) ) as $call ) {
		$r = wt_call( 'wishlist', $call[0], $call[1] );
		check( 409 === $r->get_status() && 'digitalisimo_wishlist_moved' === $r->get_data()['code'], 'Con product_wishlist activa: 409 (' . $call[0] . ' ' . ( $call[1]['op'] ?? '' ) . ').' );
	}
	check( $before === $GLOBALS['wt']['umeta'], 'Nada cambió en el almacén propio.' );
	$config = Wishlist::config();
	check( 'store' === $config['mode'] && true === $config['legacy'] && array( 10 ) === $config['items'] && isset( $config['nonce'], $config['store'] ), 'Mudanza: la configuración la anuncia, sin el producto variable (la Store API exige su variación), con el nonce de la ruta propia.' );
	check( 1 === Store::legacy_count(), 'Queda una lista propia por mudar.' );
	$GLOBALS['wt']['hooks']['wp_privacy_personal_data_exporters'] = array();
	$GLOBALS['wt']['hooks']['wp_privacy_personal_data_erasers']   = array();
	Personal_Data::register();
	$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array( 'woocommerce-shopper-lists' => array() ) );
	check( isset( $exporters[ Personal_Data::ID ] ), 'Mientras quede algún dato propio, el exportador propio sigue registrado.' );
	$r = wt_call( 'wishlist', 'POST', array( 'op' => 'clear', 'moved' => true ) );
	check( 200 === $r->get_status() && true === $r->get_data()['moved'] && ! isset( $GLOBALS['wt']['umeta'][7]['wp_digitalisimo_wishlist'] ) && 0 === Store::legacy_count(), 'La mudanza vacía el almacén propio y deja el contador a 0.' );
	$config = Wishlist::config();
	check( false === $config['legacy'] && ! isset( $config['nonce'] ) && array() === $config['items'], 'Mudado todo: en modo Store API la configuración no lleva nonce ni lista propia.' );
	$GLOBALS['wt']['hooks']['wp_privacy_personal_data_exporters'] = array();
	$GLOBALS['wt']['hooks']['wp_privacy_personal_data_erasers']   = array();
	Personal_Data::register();
	$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array( 'woocommerce-shopper-lists' => array() ) );
	check( ! isset( $exporters[ Personal_Data::ID ] ) && 1 === count( $exporters ), 'Sin datos propios y con la lista de WooCommerce, sólo responde el exportador de WooCommerce.' );

	// --- Exportador, borrador y borrado desde la red (R-I12) ------------------------------------------------------
	wt_reset( array( 'hooks' => $hooks, 'user' => 7, 'members' => array( 7 ) ) );
	wt_call( 'wishlist', 'POST', array( 'op' => 'merge', 'items' => array( 10 ) ) );
	$export = Personal_Data::export( 'a@a.test' );
	check( true === $export['done'] && 1 === count( $export['data'] ) && Personal_Data::ID === $export['data'][0]['group_id'] && 'Camiseta roja' === $export['data'][0]['data'][0]['value'], 'El exportador da nombre, dirección y fecha de cada producto del sitio.' );
	$erase = Personal_Data::erase( 'a@a.test' );
	check( array( 'items_removed', 'items_retained', 'messages', 'done' ) === array_keys( $erase ) && true === $erase['items_removed'] && false === $erase['items_retained'] && true === $erase['done'], 'El borrador devuelve sus cuatro campos (W-10).' );
	check( false === Personal_Data::erase( 'a@a.test' )['items_removed'], 'Borrar dos veces no dice que borró.' );
	wt_reset( array( 'hooks' => $hooks, 'multisite' => true, 'blog' => 1 ) );
	$GLOBALS['wt']['umeta'][7] = array( 'wp_digitalisimo_wishlist' => array( 'v' => 1, 'items' => array( array( 10, 1 ) ) ), 'wp_3_digitalisimo_wishlist' => array( 'v' => 1, 'items' => array( array( 16, 1 ) ) ) );
	$GLOBALS['wt']['options'][3][ Store::LEGACY ] = 1;
	do_action( 'remove_user_from_blog', 7, 3 );
	check( ! isset( $GLOBALS['wt']['umeta'][7]['wp_3_digitalisimo_wishlist'] ) && isset( $GLOBALS['wt']['umeta'][7]['wp_digitalisimo_wishlist'] ), 'Quitar al usuario del sitio 3 desde la red borra la lista del 3, no la del sitio principal.' );
	check( 0 === $GLOBALS['wt']['options'][3][ Store::LEGACY ] && ! isset( $GLOBALS['wt']['options'][1][ Store::LEGACY ] ), 'El contador que baja es el del sitio 3.' );
	check( Privacy::storage_key() === 'digi_wishlist_1', 'En una red, la clave del navegador lleva el número del sitio.' );
	$items = apply_filters( 'digitalisimo_elements_privacy_items', array() );
	check( 1 === count( array_filter( $items, function ( $i ) { return false !== strpos( $i, 'digi_wishlist' ); } ) ), 'El elemento de la política sale una sola vez (lo añade Woo\\Privacy).' );

	// --- Fichas: POST, cuerpo acotado e instancia real (B2, V-M22) ----------------------------------------------------
	$wl = array( 'id' => 'abc1234', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-wishlist', 'settings' => array( 'parts' => array( 'title', 'price', 'remove' ), 'item_tag' => 'h4' ) );
	$GLOBALS['wt_meta'] = array(
		30 => array( '_elementor_data' => wp_json_encode( array( array( 'id' => 's1', 'elType' => 'container', 'elements' => array( $wl, array( 'id' => 'glob123', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 40 ), array( 'id' => 'btn1234', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-wishlist-button', 'settings' => array() ) ) ) ) ) ),
		31 => array( '_elementor_data' => wp_json_encode( array( $wl ) ) ),
		40 => array( '_elementor_data' => wp_json_encode( array( array( 'id' => 'tpl1234', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-wishlist', 'settings' => array( 'parts' => array( 'image', 'cart' ) ) ) ) ) ),
	);
	wt_reset( array( 'hooks' => $hooks, 'meta' => $GLOBALS['wt_meta'] ) );
	$r = wt_call( 'cards', 'GET', array( 'ids' => '10,16' ) );
	check( 405 === $r->get_status() && 'POST' === $r->headers['Allow'], 'GET woo/cards responde 405: los IDs nunca van en la URL.' );
	$cards = function ( $body ) { return wt_call( 'cards', 'POST', array(), is_string( $body ) ? $body : wp_json_encode( $body ) ); };
	$r = $cards( array( 'post_id' => 30, 'widget_id' => 'abc1234', 'ids' => array( 16, 12, 10, 15 ) ) );
	check( 200 === $r->get_status() && array( 16, 10, 15 ) === $r->get_data()['ids'], 'Fichas sólo de productos públicos, en el orden pedido.' );
	$html = $r->get_data()['html'];
	check( 3 === substr_count( $html, '<li class="digi-wishlist__item"' ) && false !== strpos( $html, '<h4 class="digi-wishlist__name"><a href="https://a.test/p/10/">Camiseta roja</a></h4>' ), 'Los ajustes salen del documento (partes y etiqueta del nombre), con el nombre escapado.' );
	check( false === strpos( $html, '<img' ) && false === strpos( $html, 'add_to_cart_button' ) && false !== strpos( $html, 'data-digi-wish-remove="10">Quitar<span class="screen-reader-text"> Camiseta roja</span>' ), 'Sólo las partes elegidas; «Quitar» con nombre accesible.' );
	check( 'no-store, private' === $r->headers['Cache-Control'] && isset( $r->headers['Expires'] ), 'Las fichas salen sin caché.' );
	$r = $cards( array( 'post_id' => 30, 'widget_id' => 'glob123', 'ids' => array( 10 ) ) );
	check( 200 === $r->get_status() && false !== strpos( $r->get_data()['html'], 'data-size="woocommerce_thumbnail"' ) && false !== strpos( $r->get_data()['html'], 'add_to_cart_button' ), 'Un widget global se sigue a su plantilla y usa sus ajustes.' );
	foreach ( array(
		'otro tipo'           => array( 'post_id' => 30, 'widget_id' => 'btn1234', 'ids' => array( 10 ) ),
		'widget inexistente'  => array( 'post_id' => 30, 'widget_id' => 'zzz9999', 'ids' => array( 10 ) ),
		'documento borrador'  => array( 'post_id' => 31, 'widget_id' => 'abc1234', 'ids' => array( 10 ) ),
	) as $case => $body ) {
		check( 403 === $cards( $body )->get_status(), 'Sin una instancia del widget en un documento visible: 403 (' . $case . ').' );
	}
	$GLOBALS['wt']['caps'] = array( 'edit_post' );
	check( 200 === $cards( array( 'post_id' => 31, 'widget_id' => 'abc1234', 'ids' => array( 10 ) ) )->get_status(), 'Quien puede editar el documento ve sus fichas en el editor.' );
	$GLOBALS['wt']['caps'] = array();
	foreach ( array(
		'101 IDs'          => array( 'post_id' => 30, 'widget_id' => 'abc1234', 'ids' => range( 1, 101 ) ),
		'ID no numérico'   => array( 'post_id' => 30, 'widget_id' => 'abc1234', 'ids' => array( 10, 'x' ) ),
		'ID negativo'      => array( 'post_id' => 30, 'widget_id' => 'abc1234', 'ids' => array( -3 ) ),
		'sin documento'    => array( 'widget_id' => 'abc1234', 'ids' => array( 10 ) ),
		'widget raro'      => array( 'post_id' => 30, 'widget_id' => '../etc', 'ids' => array( 10 ) ),
		'cuerpo de 9 KB'   => '{"post_id":30,"widget_id":"abc1234","ids":[10],"x":"' . str_repeat( 'a', 9000 ) . '"}',
		'cuerpo no JSON'   => 'post_id=30',
	) as $case => $body ) {
		check( 400 === $cards( $body )->get_status(), 'Entrada inválida: 400 (' . $case . ').' );
	}

	// --- Marcado igual para todos y sin estado (D21) ------------------------------------------------------------------
	wt_reset( array( 'hooks' => $hooks ) );
	$button = Button::markup( 10 );
	check( false !== strpos( $button, '<button type="button" class="digi-wish" data-digi-wish="10" aria-pressed="false" hidden>' ) && false !== strpos( $button, '<span class="screen-reader-text"> Camiseta roja en la lista de deseos</span>' ), 'Botón oculto, sin pulsar, con su nombre accesible completo.' );
	check( false === strpos( $button, 'data-digi-wish-login' ), 'Sin «pedir iniciar sesión», ningún enlace de acceso.' );
	check( '' === Button::markup( 12 ) && '' === Button::markup( 2147483647 ) && '' === Button::markup( 0 ), 'Un producto no público o la referencia de kit sin destino no pintan nada.' );
	check( false !== strpos( Button::markup( 11 ), 'data-variable="1"' ), 'Un producto variable se marca: con la lista de WooCommerce el script no lo ofrece.' );
	$login = Button::markup( 10, array( 'guest' => 'login', 'text' => '' ) );
	check( false !== strpos( $login, 'href="https://a.test/mi-cuenta/" data-digi-wish-login hidden>Inicia sesión para guardar<span class="screen-reader-text"> Camiseta roja en la lista de deseos</span></a>' ) && false !== strpos( $login, '<span class="screen-reader-text">Guardar Camiseta roja en la lista de deseos</span>' ), '«Pedir iniciar sesión»: enlace oculto a «Mi cuenta», igual para todos; sólo icono con nombre completo.' );
	check( false === strpos( $login, 'acceso-privado' ), 'Nunca se imprime la dirección de wp_login_url(), que puede ser la ruta privada de acceso.' );
	add_filter( 'digitalisimo_elements_wishlist_guest_storage', '__return_false' );
	check( false !== strpos( Button::markup( 10 ), 'data-digi-wish-login' ) && false === Wishlist::config()['guest'], 'Con el modo invitado apagado en el sitio, cada botón lleva su enlace de acceso.' );
	unset( $GLOBALS['wt']['hooks']['digitalisimo_elements_wishlist_guest_storage'] );

	check( '' === Button::link( 21 ) && false !== strpos( Button::link( 20 ), '<span class="digi-wish-count" data-digi-wish-count>0</span>' ), 'El enlace con contador: sólo con una página publicada y a 0 en el HTML.' );

	$render = function ( $class, $settings, $user ) {
		$GLOBALS['wt']['user'] = $user;
		List_Widget::reset_count();
		$widget = ( new $class( $settings ) )->boot();
		return $widget->html();
	};
	$guest = $render( List_Widget::class, array( 'title' => 'Mis deseos' ), 0 );
	$member = $render( List_Widget::class, array( 'title' => 'Mis deseos' ), 7 );
	check( $guest === $member, 'La lista pinta el mismo HTML para un invitado y para un usuario con sesión.' );
	check( false !== strpos( $guest, 'data-digi-wishlist data-post="10" data-widget="abc1234"' ) && false !== strpos( $guest, '<ul class="digi-wishlist__items" data-digi-wish-items></ul>' ) && false !== strpos( $guest, '<noscript>' ), 'Contenedor vacío con su documento y su instancia, y aviso sin JavaScript.' );
	check( false !== strpos( $guest, 'aria-labelledby="digi-wl-abc1234"' ) && false !== strpos( $guest, '<h2 id="digi-wl-abc1234" class="digi-wishlist__title" tabindex="-1">Mis deseos</h2>' ), 'Región con nombre: el título visible (destino del foco al quitar el último).' );
	$untitled = $render( List_Widget::class, array(), 0 ) . ( new List_Widget( array() ) )->boot()->html();
	check( false !== strpos( $untitled, 'class="digi-wishlist__title screen-reader-text" tabindex="-1">Lista de deseos</' ) && false !== strpos( $untitled, 'id="digi-wl-abc1234-2"' ), 'Sin título visible, uno para lectores de pantalla; dos instancias en la página, dos id distintos.' );
	$b1 = $render( Button_Widget::class, array(), 0 );
	$b2 = $render( Button_Widget::class, array(), 7 );
	check( $b1 === $b2 && false !== strpos( $b1, 'data-digi-wish="10"' ), 'El botón del producto actual pinta lo mismo para todos.' );
	check( false !== strpos( $render( Button_Widget::class, array( 'mode' => 'link', 'wishlist_page' => 20 ), 0 ), 'href="https://a.test/?p=20"' ), 'Modo enlace a la página de la lista.' );
	check( '' === trim( $render( Button_Widget::class, array( 'product_source' => 'custom', 'product_id' => 2147483647 ), 0 ) ), 'Un producto concreto sin destino (kit) no pinta nada.' );
	foreach ( array( $guest, $member, $b1, $button ) as $html ) {
		check( false === strpos( $html, 'n-wp_rest' ) && ! preg_match( '/<script/i', $html ), 'Ningún nonce ni <script> en el HTML de la página.' );
	}
	// Controles: ninguno pide WooCommerce al registrarse; el aviso del modo Store API sólo fuera de la pila optimizada.
	\Automattic\WooCommerce\Utilities\FeaturesUtil::$on['product_wishlist'] = true;
	Source::reset();
	check( isset( ( new Button_Widget() )->boot()->controls['store_notice'] ), 'El editor avisa cuando la lista de WooCommerce está activa.' );
	\Elementor\Core\Frontend\Performance::$optimize = true;
	check( ! isset( ( new List_Widget() )->boot()->controls['store_notice'] ), 'En el front optimizado, el aviso no se construye.' );

	// --- Configuración del pie ---------------------------------------------------------------------------------------
	wt_reset( array( 'hooks' => $hooks ) );
	Wishlist::footer_config();
	check( array() === $GLOBALS['wt']['inline'], 'Sin el script encolado, ninguna configuración.' );
	$GLOBALS['wt']['enqueued'] = array( 'digitalisimo-woo-wishlist' );
	Wishlist::footer_config();
	check( 1 === count( $GLOBALS['wt']['inline'] ) && 'before' === $GLOBALS['wt']['inline'][0][2] && 0 === strpos( $GLOBALS['wt']['inline'][0][1], 'window.digitalisimoWishlist=' ), 'Configuración en línea antes del script propio (wp_add_inline_script).' );
	$public = Wishlist::config();
	check( ! isset( $public['loggedIn'], $public['nonce'], $public['items'], $public['rest'] ) && 'digi_wishlist' === $public['key'] && isset( $public['t']['saved'] ) && ! isset( $public['t']['ask'] ), 'Un invitado recibe sólo lo público: clave, ruta de las fichas, modo y textos.' );
	$GLOBALS['wt']['user'] = 7;
	$GLOBALS['wt']['members'] = array( 7 );
	$GLOBALS['wt']['umeta'][7]['wp_digitalisimo_wishlist'] = array( 'v' => 1, 'items' => array( array( 10, 5 ), array( 12, 6 ) ) );
	$own = Wishlist::config();
	check( true === $own['loggedIn'] && 'own' === $own['mode'] && array( 10 ) === $own['items'] && 'n-wp_rest-7' === $own['nonce'] && isset( $own['t']['ask1'], $own['t']['ask'] ), 'Con sesión, modo propio: su lista válida, su nonce y los textos de la fusión.' );
	$GLOBALS['wt']['multisite'] = true;
	$GLOBALS['wt']['members']   = array();
	check( ! isset( Wishlist::config()['loggedIn'] ), 'Un usuario de la red que no es miembro del sitio usa el modo invitado.' );

	// --- Recursos -------------------------------------------------------------------------------------------------
	$js  = (string) file_get_contents( $root . '/assets/js/wishlist.js' );
	$css = (string) file_get_contents( $root . '/assets/css/wishlist.css' );
	check( strlen( $js ) <= 8192 && strlen( $css ) <= 4096, 'wishlist.js (' . strlen( $js ) . ' B) y wishlist.css (' . strlen( $css ) . ' B) en su tope.' );
	check( false === stripos( $js, 'jquery' ) && false === strpos( $css, '--e-global' ) && false === strpos( $css, 'url(' ), 'Sin jQuery, sin la paleta global ni url() en la hoja.' );
	check( false !== strpos( $js, "'Nonce'" ) && false !== strpos( $js, 'rest-nonce' ) && false !== strpos( $js, "'digitalisimo:wishlist'" ) && false !== strpos( $js, "'storage'" ), 'El script reutiliza el Nonce de la Store API, renueva una vez el de WordPress y sincroniza widgets y pestañas.' );
	$php = '';
	foreach ( glob( $root . '/modules/digitalisimo-woo/wishlist/*.php' ) as $file ) { $php .= file_get_contents( $file ); }
	check( ! preg_match( '/<script(?!\s+type="text\/template")/', $php ) && false === strpos( str_replace( '\\\\', '\\', $php ), 'WooCommerce\\Internal' ), 'Ningún <script> propio ni clases internas de WooCommerce.' );
	$names = preg_replace( array( '/\\\\*Elementor(?:Pro)?(?:\\\\+[A-Za-z_]+)+/', '/[a-z_-]*elementor[A-Za-z_-]*/' ), '', $php . $js );
	check( false === stripos( $names, 'elementor' ), 'Ningún texto ni comentario nombra al proveedor del núcleo (sólo identificadores).' );
	foreach ( array( 'gettext', 'ld+json', '--e-global' ) as $needle ) {
		check( false === strpos( $php, $needle ), 'Sin ' . $needle . ' en el módulo.' );
	}

	echo "DIGITALÍSIMO Elements: lista de deseos de F4-Woo validada (fuente única, privacidad, rutas, marcado y configuración).\n";
}
