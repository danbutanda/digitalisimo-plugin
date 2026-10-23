<?php
/**
 * F4-Woo · base del módulo `modules/digitalisimo-woo/` (frente A): arranque, guardas (D3), registro tolerante, recursos,
 * base REST, productos públicos, bandera de fragmentos (V.12.4), privacidad (V.4.5 y V.4.6) y el registro oculto sin
 * WooCommerce (`Inert`, V.10.1) en las tres peticiones del editor y nunca en REST.
 *
 * Sin WordPress, WooCommerce ni el núcleo: dobles mínimos. Las clases base se copian a una carpeta temporal junto a
 * widgets y submódulos de prueba (uno que coopera, uno que redefine `render()`, uno roto), así el resultado no depende
 * de lo que hayan entregado los demás frentes. Guardar sin perder ajustes con el núcleo real está en
 * `tests/elements-unknown-guard.php`.
 */
namespace Elementor {
	class Plugin {
		public static $instance;
	}
	/** Lo que `Woo\Widget`, `Inert_Widget` y `Inert` usan del widget del núcleo. */
	class Widget_Base {
		private $test_data;
		private $test_args = array();
		public function __construct( $data = array(), $args = null ) {
			$this->test_data = $data;
			if ( ! $data && $args ) {
				$this->test_args = $args;
			}
		}
		public function get_data( $item = null ) {
			return null === $item ? $this->test_data : ( is_array( $this->test_data ) && isset( $this->test_data[ $item ] ) ? $this->test_data[ $item ] : null );
		}
		public function get_default_args( $item = null ) {
			return null === $item ? $this->test_args : ( isset( $this->test_args[ $item ] ) ? $this->test_args[ $item ] : null );
		}
		public function show_in_panel() {
			return true;
		}
		protected function render() {}
		protected function content_template() {}
		public function test_render(): string {
			ob_start();
			$this->render();
			return (string) ob_get_clean();
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.165' );
	$GLOBALS['wt_tmp'] = sys_get_temp_dir() . '/digi-woo-module-' . getmypid();
	define( 'DIGITALISIMO_ELEMENTS_FILE', $GLOBALS['wt_tmp'] . '/plugin/pro-elements.php' );

	function check_wt( $ok, string $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	}

	/** Estado de WordPress en memoria. */
	function wt_reset(): void {
		$GLOBALS['wt'] = array(
			'hooks'     => array(),
			'options'   => array(),
			'get'       => array(),
			'posts'     => array(),
			'meta'      => array(),
			'caps'      => array( 'edit_posts', 'manage_options', 'activate_plugins' ),
			'edit_post' => array(),
			'styles'    => array(),
			'scripts'   => array(),
			'routes'    => array(),
			'admin'     => false,
			'ajax'      => false,
			'multisite' => false,
			'blog'      => 1,
			'deleted'   => array(),
			'primed'    => array(),
			'cart'      => 0,
			'nocache'   => 0,
			'litespeed' => 0,
			'visible'   => array(),
		);
		$GLOBALS['wpdb']->log = array();
		$_GET                 = array();
		$_POST                = array();
		$_REQUEST             = array();
		$_SERVER['REQUEST_URI'] = '/';
	}

	// Ganchos.
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['wt']['hooks'][ $hook ][] = array( $callback, $priority, $args );
		return true;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $callback, $priority, $args );
	}
	function apply_filters( $hook, $value, ...$args ) {
		$entries = $GLOBALS['wt']['hooks'][ $hook ] ?? array();
		usort( $entries, function ( $a, $b ) { return $a[1] <=> $b[1]; } );
		foreach ( $entries as $entry ) {
			$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, (int) $entry[2] ) ) );
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		if ( 'litespeed_control_set_nocache' === $hook ) {
			++$GLOBALS['wt']['litespeed'];
		}
		foreach ( $GLOBALS['wt']['hooks'][ $hook ] ?? array() as $entry ) {
			call_user_func_array( $entry[0], array_slice( $args, 0, (int) $entry[2] ) );
		}
	}
	function did_action( $hook ) { return 'woocommerce_init' === $hook || 'rest_api_init' === $hook && ! empty( $GLOBALS['wt']['rest_init'] ) ? 1 : 0; }
	function wt_hook_count(): int {
		$n = 0;
		foreach ( $GLOBALS['wt']['hooks'] as $entries ) {
			$n += count( $entries );
		}
		return $n;
	}

	// Entorno de la petición.
	function is_admin() { return $GLOBALS['wt']['admin']; }
	function wp_doing_ajax() { return $GLOBALS['wt']['ajax']; }
	function wp_doing_cron() { return false; }
	function rest_get_url_prefix() { return 'wp-json'; }
	function is_multisite() { return $GLOBALS['wt']['multisite']; }
	function get_current_blog_id() { return $GLOBALS['wt']['blog']; }
	function current_user_can( $cap, ...$args ) {
		if ( 'edit_post' === $cap ) {
			return in_array( (int) ( $args[0] ?? 0 ), $GLOBALS['wt']['edit_post'], true );
		}
		return in_array( $cap, $GLOBALS['wt']['caps'], true );
	}
	function __( $text, $domain = '' ) { return $text; }
	function esc_html__( $text, $domain = '' ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
	function plugins_url( $path = '', $file = '' ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
	function rest_url( $path = '' ) { return 'https://sitio.test/wp-json/' . ltrim( $path, '/' ); }

	// Opciones (el doble cuenta cada lectura que no es de `alloptions`).
	function get_option( $name, $default = false ) {
		$GLOBALS['wt']['get'][] = $name;
		return array_key_exists( $name, $GLOBALS['wt']['options'] ) ? $GLOBALS['wt']['options'][ $name ]['value'] : $default;
	}
	function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
		if ( array_key_exists( $name, $GLOBALS['wt']['options'] ) ) {
			return false;
		}
		$GLOBALS['wt']['options'][ $name ] = array( 'value' => $value, 'autoload' => true === $autoload );
		return true;
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['wt']['options'][ $name ] = array( 'value' => $value, 'autoload' => null === $autoload ? ( $GLOBALS['wt']['options'][ $name ]['autoload'] ?? true ) : (bool) $autoload );
		$GLOBALS['wt']['updates'][]        = $name;
		return true;
	}
	function wp_set_option_autoload( $name, $autoload ) { $GLOBALS['wt']['options'][ $name ]['autoload'] = (bool) $autoload; return true; }
	function wp_load_alloptions() {
		$out = array();
		foreach ( $GLOBALS['wt']['options'] as $name => $row ) {
			if ( $row['autoload'] ) {
				$out[ $name ] = is_array( $row['value'] ) ? serialize( $row['value'] ) : $row['value'];
			}
		}
		return $out;
	}
	function maybe_unserialize( $value ) { return is_string( $value ) && preg_match( '/^a:\d+:/', $value ) ? unserialize( $value ) : $value; }

	// Entradas y metas.
	function wt_post( int $id, string $type, string $status = 'publish', array $meta = array(), array $extra = array() ): void {
		$GLOBALS['wt']['posts'][ $id ] = (object) ( $extra + array( 'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_password' => '', 'post_parent' => 0 ) );
		$GLOBALS['wt']['meta'][ $id ]  = $meta;
	}
	function get_post( $id = null ) { return $GLOBALS['wt']['posts'][ (int) $id ] ?? null; }
	function get_post_type( $id = null ) { $post = get_post( $id ); return $post ? $post->post_type : false; }
	function get_post_status( $id = null ) { $post = get_post( $id ); return $post ? $post->post_status : false; }
	function get_post_meta( $id, $key = '', $single = false ) {
		$GLOBALS['wt']['meta_reads'][] = array( (int) $id, $key );
		return $GLOBALS['wt']['meta'][ (int) $id ][ $key ] ?? ( $single ? '' : array() );
	}
	function wp_is_post_revision( $id ) { return 'revision' === get_post_type( $id ) ? 1 : false; }
	function wp_is_post_autosave( $id ) { $post = get_post( $id ); return $post && ! empty( $post->autosave ) ? 1 : false; }
	function post_type_supports( $type, $feature ) { return 'elementor' === $feature && in_array( $type, array( 'page', 'post' ), true ); }
	function delete_user_meta( $user, $key ) { $GLOBALS['wt']['deleted'][] = array( $user, $key ); return true; }
	function _prime_post_caches( $ids, $terms = true, $meta = true ) { $GLOBALS['wt']['primed'][] = array( $ids, $terms, $meta ); }
	function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

	// REST.
	class WP_Error {
		public $code;
		public $data;
		public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->data = $data; }
	}
	class WP_REST_Response {
		public $data;
		public $status;
		public $headers = array();
		public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
		public function header( $name, $value ) { $this->headers[ $name ] = $value; }
	}
	class WP_REST_Request {
		public $route;
		public $body;
		public function __construct( $route = '', $body = '' ) { $this->route = $route; $this->body = $body; }
		public function get_route() { return $this->route; }
		public function get_body() { return $this->body; }
	}
	function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response || $value instanceof WP_Error ? $value : new WP_REST_Response( $value ); }
	function rest_convert_error_to_response( $error ) { return new WP_REST_Response( array( 'code' => $error->code ), is_array( $error->data ) && isset( $error->data['status'] ) ? $error->data['status'] : 500 ); }
	function register_rest_route( $namespace, $route, $args ) { $GLOBALS['wt']['routes'][] = array( $namespace, $route, $args ); return true; }
	function wp_get_nocache_headers() { return array( 'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT', 'Cache-Control' => 'no-cache, must-revalidate, max-age=0, no-store, private' ); }
	function nocache_headers() { ++$GLOBALS['wt']['nocache']; }

	// Recursos.
	function wp_style_is( $handle, $what = 'enqueued' ) { return isset( $GLOBALS['wt']['styles'][ $handle ] ) && ( 'registered' === $what || ! empty( $GLOBALS['wt']['styles'][ $handle ]['enqueued'] ) ); }
	function wp_script_is( $handle, $what = 'enqueued' ) { return isset( $GLOBALS['wt']['scripts'][ $handle ] ) && ( 'registered' === $what || ! empty( $GLOBALS['wt']['scripts'][ $handle ]['enqueued'] ) ); }
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) { $GLOBALS['wt']['styles'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver ); return true; }
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['wt']['scripts'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'args' => $args ); return true; }
	function wp_enqueue_style( $handle ) { if ( isset( $GLOBALS['wt']['styles'][ $handle ] ) ) { $GLOBALS['wt']['styles'][ $handle ]['enqueued'] = true; } }
	function wp_enqueue_script( $handle ) { if ( isset( $GLOBALS['wt']['scripts'][ $handle ] ) ) { $GLOBALS['wt']['scripts'][ $handle ]['enqueued'] = true; } }

	/** `$wpdb` que anota cada consulta y resuelve la de la bandera sobre las entradas y metas en memoria. */
	final class WT_WPDB {
		public $posts    = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public $prefix   = 'wp_';
		public $log      = array();
		private $prepared = array();
		public function prepare( $query, ...$args ) {
			$this->prepared[] = array( $query, $args );
			return '#' . ( count( $this->prepared ) - 1 );
		}
		public function esc_like( $text ) { return addcslashes( (string) $text, '_%\\' ); }
		public function get_blog_prefix( $blog_id = null ) { return 1 === (int) $blog_id ? 'wp_' : 'wp_' . (int) $blog_id . '_'; }
		public function get_var( $sql ) {
			list( $query, $args ) = $this->prepared[ (int) substr( (string) $sql, 1 ) ];
			$this->log[] = array( $query, $args );
			foreach ( $GLOBALS['wt']['meta'] as $id => $meta ) {
				$post = $GLOBALS['wt']['posts'][ $id ] ?? null;
				if ( ! $post || 'publish' !== $post->post_status || ! isset( $meta['_elementor_data'] ) ) {
					continue;
				}
				foreach ( $args as $pattern ) {
					$needle = stripslashes( trim( $pattern, '%' ) );
					if ( false !== strpos( (string) $meta['_elementor_data'], $needle ) ) {
						return '1';
					}
				}
			}
			return null;
		}
	}
	$GLOBALS['wpdb'] = new WT_WPDB();
	wt_reset();

	/** WooCommerce mínimo: `WC()` con un carrito que `wc_load_cart()` crea. */
	class WooCommerce {
		public $cart = null;
	}
	function WC() { return $GLOBALS['wt_wc']; } // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	function wc_load_cart() { ++$GLOBALS['wt']['cart']; $GLOBALS['wt_wc']->cart = new stdClass(); }
	$GLOBALS['wt_wc'] = new WooCommerce();
	class WT_Product {
		private $id;
		public function __construct( $id ) { $this->id = $id; }
		public function is_visible() { return ! in_array( $this->id, $GLOBALS['wt']['hidden'] ?? array(), true ); }
	}
	function wc_get_product( $id ) { return get_post( $id ) ? new WT_Product( (int) $id ) : false; }

	// -----------------------------------------------------------------------------------------------------------------
	// Carpeta temporal: las clases base del módulo, widgets y submódulos de prueba y los recursos del plugin.
	$real = realpath( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-woo' );
	$tmp  = $GLOBALS['wt_tmp'] . '/woo';
	foreach ( array( $tmp . '/wishlist', $tmp . '/quick-view', $tmp . '/filters', $tmp . '/cart', $tmp . '/kit', $GLOBALS['wt_tmp'] . '/plugin/assets/css', $GLOBALS['wt_tmp'] . '/plugin/assets/js' ) as $dir ) {
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}
	}
	$base = glob( $real . '/class-*.php' );
	check_wt( count( $base ) >= 10, 'El módulo tiene sus clases base (' . count( $base ) . ').' );
	foreach ( $base as $file ) {
		copy( $file, $tmp . '/' . basename( $file ) );
	}
	file_put_contents( $tmp . '/wishlist/class-button-widget.php', '<?php namespace Digitalisimo\Elements\Woo\Wishlist; class Button_Widget extends \Digitalisimo\Elements\Woo\Widget { public function get_name() { return "digitalisimo-woo-wishlist-button"; } protected function render_widget() { echo "BOTON"; } }' );
	file_put_contents( $tmp . '/quick-view/class-widget.php', '<?php namespace Digitalisimo\Elements\Woo\Quick_View; class Widget extends \Digitalisimo\Elements\Woo\Widget { public function get_name() { return "digitalisimo-woo-quick-view"; } protected function render() { echo wc_ajeno(); } }' );
	file_put_contents( $tmp . '/filters/class-price-widget.php', '<?php namespace Digitalisimo\Elements\Woo\Filters; class Price_Widget extends \No\Existe\Base {}' );
	file_put_contents( $tmp . '/wishlist/class-module.php', '<?php namespace Digitalisimo\Elements\Woo\Wishlist; final class Module { public static function init() { $GLOBALS["wt"]["boot"][] = "wishlist"; if ( ! \Digitalisimo\Elements\Woo\Module::requires( "wishlist", array( "wc_get_product()", "WooCommerce" ) ) ) { return; } $GLOBALS["wt"]["boot"][] = "wishlist:ok"; } }' );
	file_put_contents( $tmp . '/cart/class-module.php', '<?php namespace Digitalisimo\Elements\Woo\Cart; final class Module { public static function init() { if ( ! \Digitalisimo\Elements\Woo\Module::requires( "cart", array( "WC_Simbolo_Futuro::metodo", "wc_funcion_futura()", "const:WC_CONSTANTE_FUTURA" ) ) ) { $GLOBALS["wt"]["boot"][] = "cart:off"; return; } $GLOBALS["wt"]["boot"][] = "cart:ok"; } }' );
	// Kits (frente H): con WooCommerce, init(); sin él, init_inactive() con un solo filtro, como el real.
	file_put_contents( $tmp . '/kit/class-module.php', '<?php namespace Digitalisimo\Elements\Woo\Kit; final class Module { private static $parts = false; public static function init() { $GLOBALS["wt"]["boot"][] = "kit"; } public static function init_inactive() { if ( self::$parts ) { return; } self::$parts = true; add_filter( "digitalisimo_elements_kit_parts", array( __CLASS__, "parts" ) ); } public static function parts( $parts ) { $parts[] = "woo-inactive"; return $parts; } public static function reset() { self::$parts = false; } }' );
	file_put_contents( $GLOBALS['wt_tmp'] . '/plugin/assets/css/wishlist.css', '.digi-wish{}' );
	file_put_contents( $GLOBALS['wt_tmp'] . '/plugin/assets/js/wishlist.js', '/* lista */' );
	file_put_contents( $GLOBALS['wt_tmp'] . '/plugin/assets/js/filters.js', '/* filtros */' );

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-unknown-guard.php';
	require $tmp . '/class-module.php';

	use Digitalisimo\Elements\Unknown_Guard;
	use Digitalisimo\Elements\Woo\Assets;
	use Digitalisimo\Elements\Woo\Flags;
	use Digitalisimo\Elements\Woo\Inert;
	use Digitalisimo\Elements\Woo\Inert_Widget;
	use Digitalisimo\Elements\Woo\Module;
	use Digitalisimo\Elements\Woo\Privacy;
	use Digitalisimo\Elements\Woo\Products;
	use Digitalisimo\Elements\Woo\Registry;
	use Digitalisimo\Elements\Woo\Rest;

	/** Gestor de widgets mínimo. */
	final class WT_Widgets {
		public $types = array();
		public function register( $widget ) { $this->types[ $widget->get_name() ] = $widget; return true; }
		public function get_widget_types( $name = null ) { return null === $name ? $this->types : ( $this->types[ $name ] ?? null ); }
	}
	final class WT_Mode {
		public $on = false;
		public function is_edit_mode() { return $this->on; }
		public function is_preview_mode() { return $this->on; }
	}
	\Elementor\Plugin::$instance          = new stdClass();
	\Elementor\Plugin::$instance->editor  = new WT_Mode();
	\Elementor\Plugin::$instance->preview = new WT_Mode();

	/** Una petición nueva: estado limpio y módulo sin iniciar. */
	function wt_request( array $get = array(), bool $admin = false, bool $ajax = false, array $post = array() ): void {
		wt_reset();
		$_GET                  = $get;
		$_POST                 = $post;
		$_REQUEST              = array_merge( $get, $post );
		$GLOBALS['wt']['admin'] = $admin;
		$GLOBALS['wt']['ajax']  = $ajax;
		Unknown_Guard::reset();
		Module::reset();
		foreach ( array( Inert::class, Registry::class, Assets::class, Rest::class, Flags::class, Privacy::class, 'Digitalisimo\\Elements\\Woo\\Kit\\Module' ) as $class ) {
			if ( class_exists( $class, false ) ) {
				$class::reset();
			}
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// 1. Autocarga acotada y versiones (D3).
	check_wt( $tmp . '/quick-view/class-widget.php' === Module::file_for( 'Digitalisimo\\Elements\\Woo\\Quick_View\\Widget' ) || '' === Module::file_for( 'Digitalisimo\\Elements\\Woo\\Quick_View\\Widget' ), 'file_for() responde sin tocar otra carpeta.' );
	wt_request();
	Module::load( $tmp, array( 'woocommerce' => false ) );
	check_wt( $tmp . '/quick-view/class-widget.php' === Module::file_for( 'Digitalisimo\\Elements\\Woo\\Quick_View\\Widget' ), 'Quick_View\\Widget → quick-view/class-widget.php.' );
	check_wt( $tmp . '/class-rest.php' === Module::file_for( '\\Digitalisimo\\Elements\\Woo\\Rest' ), 'Woo\\Rest → class-rest.php.' );
	foreach ( array( 'Digitalisimo\\Elements\\Woo\\A\\B\\C', 'Digitalisimo\\Elements\\Woo\\..\\x', 'Digitalisimo\\Elements\\Design\\Css', 'Otra\\Rest', 'Digitalisimo\\Elements\\Woo\\Nada' ) as $class ) {
		check_wt( '' === Module::file_for( $class ), "file_for( {$class} ) no llega al disco." );
	}
	foreach ( array( '10.0.0' => true, '10.0.0-rc.1' => true, '10.1' => true, '11.2.1' => true, '9.9.9' => false, '8.0.0' => false, 'x' => false, '' => false ) as $version => $ok ) {
		check_wt( $ok === Module::wc_version_supported( (string) $version ), "WooCommerce {$version}: " . ( $ok ? 'admitido' : 'no admitido' ) . '.' );
	}

	// 2. Sin WooCommerce, en el front: sólo el arranque y el filtro de partes de kits; ni rutas, ni consultas, ni opciones.
	wt_request();
	$started = Module::load( $tmp, array( 'woocommerce' => false ) );
	check_wt( array( 'Inert', 'Kit\\Module::init_inactive' ) === $started && ! Module::active(), 'Sin WooCommerce sólo arrancan Inert y las partes de kits (init_inactive).' );
	check_wt( array( 'digitalisimo_elements_kit_parts' ) === array_keys( $GLOBALS['wt']['hooks'] ) && 1 === wt_hook_count() && array() === $GLOBALS['wpdb']->log && array() === $GLOBALS['wt']['get'] && empty( $GLOBALS['wt']['meta_reads'] ), 'Sin WooCommerce, en el front: un solo filtro (las partes de kits, que sólo corre al importar), 0 consultas y ninguna opción leída.' );
	check_wt( array( 'x', 'woo-inactive' ) === apply_filters( 'digitalisimo_elements_kit_parts', array( 'x' ) ) && array() === $GLOBALS['wpdb']->log, 'El filtro sólo añade las partes (para informar woocommerce.inactive), sin consultas.' );
	Module::reset();
	Module::load( $tmp, array( 'woocommerce' => false ) );
	check_wt( 1 === wt_hook_count(), 'init_inactive() no engancha dos veces.' );
	$kit_file = $real . '/kit/class-module.php';
	if ( is_file( $kit_file ) ) {
		$kit_source = (string) file_get_contents( $kit_file );
		$body       = function ( string $name ) use ( $kit_source ): string {
			$start = strpos( $kit_source, 'function ' . $name . '()' );
			if ( false === $start ) {
				return '';
			}
			$open  = strpos( $kit_source, '{', $start );
			$depth = 0;
			for ( $i = $open, $n = strlen( $kit_source ); $i < $n; $i++ ) {
				if ( '{' === $kit_source[ $i ] ) {
					++$depth;
				} elseif ( '}' === $kit_source[ $i ] && 0 === --$depth ) {
					return substr( $kit_source, $open, $i - $open + 1 );
				}
			}
			return '';
		};
		$inactive = $body( 'init_inactive' );
		$register = $body( 'register_parts' );
		$all      = $inactive . $register;
		check_wt( false !== strpos( $inactive, 'register_parts()' ) && substr_count( $all, 'add_filter(' ) >= 1 && substr_count( $all, 'add_filter(' ) <= 2 && false !== strpos( $register, 'FILTER_PARTS' ) && 0 === substr_count( $all, 'add_action(' ) && false === strpos( $all, '$wpdb' ) && false === strpos( $all, 'get_option(' ) && false === strpos( $all, 'get_post' ), 'El init_inactive() real de kit/ sólo engancha filtros de K (partes y, si acaso, etiquetas del informe), sin acciones, consultas ni opciones (' . substr_count( $all, 'add_filter(' ) . ' filtros).' );
	}
	check_wt( ! class_exists( 'Digitalisimo\\Elements\\Woo\\Rest', false ) && ! class_exists( 'Digitalisimo\\Elements\\Woo\\Assets', false ) && ! class_exists( 'Digitalisimo\\Elements\\Woo\\Flags', false ), 'Sin WooCommerce no se carga la base (ni REST, ni recursos, ni banderas).' );
	foreach ( array(
		'REST con elementor-preview'   => array( array( 'elementor-preview' => '5', 'rest_route' => '/elementor/v1/x' ), false, false, '/' ),
		'REST por /wp-json/'           => array( array( 'elementor-preview' => '5' ), false, false, '/wp-json/elementor/v1/import-export-customization/import' ),
		'otra acción AJAX'             => array( array( 'action' => 'heartbeat' ), true, true, '/wp-admin/admin-ajax.php' ),
		'otra pantalla de la admin'    => array( array( 'action' => 'edit' ), true, false, '/wp-admin/post.php' ),
		'front sin vista previa'       => array( array(), false, false, '/tienda/' ),
	) as $label => $case ) {
		wt_request( $case[0], $case[1], $case[2] );
		$_SERVER['REQUEST_URI'] = $case[3];
		Module::load( $tmp, array( 'woocommerce' => false ) );
		check_wt( array( 'digitalisimo_elements_kit_parts' ) === array_keys( $GLOBALS['wt']['hooks'] ) && ! Unknown_Guard::editor_request() && array() === $GLOBALS['wpdb']->log, "Sin WooCommerce, {$label}: nada enganchado salvo el filtro de partes de kits." );
	}

	// 3. Sin WooCommerce, en las tres peticiones del editor: los siete widgets, ocultos y con el aviso.
	$editor_cases = array(
		'pantalla del editor'     => array( array( 'post' => '7', 'action' => 'elementor' ), true, false, array() ),
		'vista previa'            => array( array( 'elementor-preview' => '7' ), false, false, array() ),
		'admin-ajax del editor'   => array( array( 'action' => 'elementor_ajax' ), true, true, array( 'action' => 'elementor_ajax' ) ),
	);
	foreach ( $editor_cases as $label => $case ) {
		wt_request( $case[0], $case[1], $case[2], $case[3] );
		Module::load( $tmp, array( 'woocommerce' => false ) );
		check_wt( Unknown_Guard::editor_request() && ! empty( $GLOBALS['wt']['hooks']['elementor/widgets/register'] ), "Sin WooCommerce, {$label}: Inert se engancha a elementor/widgets/register." );
	}
	$manager = new WT_Widgets();
	$names   = Inert::register( $manager );
	check_wt( Registry::names() === $names && 7 === count( $manager->types ) && Inert::active(), 'Inert registra los siete widgets.' );
	foreach ( $manager->types as $name => $widget ) {
		check_wt( 0 === strpos( $name, 'digitalisimo-woo-' ) && false === $widget->show_in_panel(), "{$name}: nombre digitalisimo-woo-* y oculto del panel." );
	}
	check_wt( $manager->types['digitalisimo-woo-wishlist-button'] instanceof \Digitalisimo\Elements\Woo\Wishlist\Button_Widget, 'El widget que sigue el contrato se registra con su propia clase (mismos controles).' );
	check_wt( $manager->types['digitalisimo-woo-quick-view'] instanceof Inert_Widget && $manager->types['digitalisimo-woo-price-filter'] instanceof Inert_Widget && $manager->types['digitalisimo-woo-swatches'] instanceof Inert_Widget, 'Uno que redefine render(), uno roto y uno sin archivo usan el sustituto, sin errores.' );
	check_wt( 'Vista rápida' === $manager->types['digitalisimo-woo-quick-view']->get_title() && 'digitalisimo-woo-quick-view' === $manager->types['digitalisimo-woo-quick-view']->get_name(), 'El sustituto conserva nombre y título.' );
	$full = new Inert_Widget( array( 'id' => 'abc1234', 'widgetType' => 'digitalisimo-woo-swatches' ), array( 'x' => 1 ) );
	check_wt( 'digitalisimo-woo-swatches' === $full->get_name(), 'Una instancia completa del sustituto toma el nombre de su widgetType.' );
	\Elementor\Plugin::$instance->editor->on = false;
	check_wt( '' === $manager->types['digitalisimo-woo-wishlist-button']->test_render() && '' === $manager->types['digitalisimo-woo-quick-view']->test_render(), 'Fuera del editor y de la vista previa, nada se pinta (ni el aviso ni la salida real).' );
	\Elementor\Plugin::$instance->editor->on = true;
	$notice = $manager->types['digitalisimo-woo-wishlist-button']->test_render();
	check_wt( false !== strpos( $notice, 'Necesita WooCommerce' ) && false === strpos( $notice, 'BOTON' ) && false === stripos( $notice, 'elementor' ) && false === strpos( $notice, 'style=' ), 'En el editor, el aviso «Necesita WooCommerce», sin la salida real ni colores propios.' );
	\Elementor\Plugin::$instance->editor->on = false;
	$GLOBALS['wt']['caps'] = array();
	Inert::reset();
	$manager = new WT_Widgets();
	check_wt( array() === Inert::register( $manager ) && ! Inert::active(), 'Sin edit_posts, Inert no registra nada.' );

	// 4. WooCommerce anterior a 10.0: como sin WooCommerce, y un aviso en la administración.
	wt_request( array(), false );
	check_wt( array( 'Inert', 'Kit\\Module::init_inactive' ) === Module::load( $tmp, array( 'woocommerce' => true, 'wc_version' => '9.9.0' ) ) && array( 'digitalisimo_elements_kit_parts' ) === array_keys( $GLOBALS['wt']['hooks'] ), 'WooCommerce 9.9: en el front, como sin WooCommerce.' );
	wt_request( array(), true );
	Module::load( $tmp, array( 'woocommerce' => true, 'wc_version' => '9.9.0' ) );
	check_wt( 1 === count( $GLOBALS['wt']['hooks']['admin_notices'] ?? array() ), 'WooCommerce 9.9: aviso en la administración.' );
	ob_start();
	Module::version_notice();
	$text = (string) ob_get_clean();
	check_wt( false !== strpos( $text, 'WooCommerce 10.0 o posterior' ) && false !== strpos( $text, '9.9.0' ), 'El aviso nombra la versión mínima y la instalada.' );
	check_wt( ! Module::requires( 'wishlist', array() ), 'Con WooCommerce incompatible, requires() siempre es false.' );

	// 5. WooCommerce compatible: la base, después los submódulos; un símbolo que falta deja el suyo sin nada.
	wt_request();
	$started = Module::load( $tmp, array( 'woocommerce' => true, 'wc_version' => '11.2.1' ) );
	check_wt( array( 'Assets', 'Rest', 'Flags', 'Privacy', 'Registry', 'Wishlist\\Module', 'Cart\\Module', 'Kit\\Module' ) === $started && Module::active(), 'Con WooCommerce 11.2.1: base y submódulos que existen, en orden (' . implode( ', ', $started ) . ').' );
	check_wt( array( 'wishlist', 'wishlist:ok', 'cart:off', 'kit' ) === $GLOBALS['wt']['boot'], 'requires(): el submódulo con sus símbolos arranca; el que pide símbolos ausentes no registra nada.' );
	check_wt( ! isset( $GLOBALS['wt']['hooks']['digitalisimo_elements_kit_parts'] ), 'Con WooCommerce, kits por init() y no por init_inactive().' );
	check_wt( array( 'cart' => array( 'WC_Simbolo_Futuro::metodo', 'wc_funcion_futura()', 'const:WC_CONSTANTE_FUTURA' ) ) === Module::missing() && 1 === count( $GLOBALS['wt']['hooks']['elementor/editor/after_enqueue_scripts'] ?? array() ), 'Los símbolos ausentes se anotan y el editor avisa una sola vez.' );
	check_wt( ! isset( $GLOBALS['wt']['hooks']['elementor/document/load/data'] ), 'En el front, la guarda hacia delante no se engancha.' );
	check_wt( Module::symbol_exists( 'WooCommerce' ) && Module::symbol_exists( 'wc_get_product()' ) && Module::symbol_exists( 'const:ABSPATH' ) && ! Module::symbol_exists( 'WooCommerce::nada' ) && ! Module::symbol_exists( '' ), 'symbol_exists(): clases, funciones, constantes y métodos.' );

	// 6. Registro tolerante (V-M20).
	$manager    = new WT_Widgets();
	$registered = Registry::widgets( $manager );
	check_wt( array( 'digitalisimo-woo-wishlist-button', 'digitalisimo-woo-quick-view' ) === $registered, 'Con WooCommerce se registran sólo los widgets cuyo archivo existe y carga (' . implode( ', ', $registered ) . ').' );
	check_wt( array() === Registry::widgets( $manager ), 'Un widget ya registrado no se registra otra vez.' );
	check_wt( true === $manager->types['digitalisimo-woo-wishlist-button']->show_in_panel() && 'BOTON' === $manager->types['digitalisimo-woo-wishlist-button']->test_render(), 'Con WooCommerce, el widget se ve en el panel y pinta su salida.' );
	check_wt( Registry::available( 'digitalisimo-woo-wishlist-button' ) && ! Registry::available( 'digitalisimo-woo-swatches' ) && ! Registry::available( 'acme-x' ), 'available() mira el archivo.' );
	foreach ( array( 'digitalisimo-woo-wishlist-button' => 'wishlist', 'digitalisimo-woo-wishlist' => 'wishlist', 'digitalisimo-woo-quick-view' => 'quick-view', 'digitalisimo-woo-swatches' => 'swatches', 'digitalisimo-woo-shipping-bar' => 'cart-extras', 'digitalisimo-woo-price-filter' => 'filters', 'digitalisimo-woo-attribute-filter' => 'filters', 'acme' => '' ) as $name => $feature ) {
		check_wt( $feature === Registry::feature( $name ), "{$name} → capacidad «{$feature}»." );
	}
	$source = file_get_contents( $real . '/class-registry.php' );
	preg_match_all( "/'(digitalisimo-woo-[a-z0-9-]+)'\s*=>\s*array\(([^()]*)\)/", $source, $entries, PREG_SET_ORDER );
	check_wt( 7 === count( $entries ), 'WIDGETS: siete entradas con el formato de Widget_Registry (sin paréntesis anidados).' );
	foreach ( $entries as $entry ) {
		foreach ( array( 'file', 'class', 'css', 'js' ) as $key ) {
			check_wt( false !== strpos( $entry[2], "'{$key}'" ), "{$entry[1]}: lleva «{$key}»." );
		}
	}

	// 7. Recursos: sólo los archivos que existen, con defer y sin jQuery; plantillas de bucle.
	Assets::register();
	check_wt( array( 'digitalisimo-woo-wishlist' ) === array_keys( $GLOBALS['wt']['styles'] ) && array( 'digitalisimo-woo-wishlist', 'digitalisimo-woo-filters' ) === array_keys( $GLOBALS['wt']['scripts'] ), 'Sólo se registran las hojas y los scripts que existen.' );
	foreach ( $GLOBALS['wt']['scripts'] as $handle => $script ) {
		check_wt( array( 'in_footer' => true, 'strategy' => 'defer' ) === $script['args'] && ! in_array( 'jquery', $script['deps'], true ) && array() === array_diff( $script['deps'], Assets::FOREIGN ), "{$handle}: en el pie, con defer, sin jQuery y sólo dependencias admitidas." );
	}
	check_wt( array( 'elementor-frontend' ) === $GLOBALS['wt']['scripts']['digitalisimo-woo-filters']['deps'] && '4.3.0.165' === $GLOBALS['wt']['styles']['digitalisimo-woo-wishlist']['ver'], 'El coordinador de filtros depende de elementor-frontend; versión del plugin.' );
	$assets_source = file_get_contents( $real . '/class-assets.php' );
	preg_match_all( '/wp_register_script\(.*?\);/s', $assets_source, $regs );
	check_wt( 1 === count( $regs[0] ) && false !== strpos( $regs[0][0], "'strategy' => 'defer'" ) && false === strpos( $assets_source, "'jquery'" ), 'class-assets.php: un solo registro de scripts, con defer y sin jquery.' );
	wt_post( 50, 'elementor_library', 'publish', array( '_elementor_data' => '[{"id":"a1","elType":"widget","widgetType":"digitalisimo-woo-wishlist-button","settings":[]}]' ) );
	wt_post( 51, 'elementor_library', 'publish', array( '_elementor_data' => '[{"id":"a2","elType":"widget","widgetType":"digitalisimo-woo-price-filter"},{"widgetType":"digitalisimo-woo-nada"}]' ) );
	wt_post( 52, 'elementor_library', 'publish', array( '_elementor_data' => '[{"id":"a3","elType":"widget","widgetType":"heading"}]' ) );
	$loop = new class() {
		public $name = 'loop-grid';
		public $settings = array( 'template_id' => '50', 'alternate_template' => 'yes', 'alternate_templates' => array( array( 'template_id' => '51' ), array( 'template_id' => '50' ), array( 'template_id' => '' ) ) );
		public function get_name() { return $this->name; }
		public function get_settings_for_display() { return $this->settings; }
	};
	check_wt( array( 50, 51 ) === Assets::loop_templates( $loop->settings ), 'Plantilla principal y alternas, sin repetir ni vacías.' );
	$GLOBALS['wt']['meta_reads'] = array();
	check_wt( array( 'wishlist', 'filters' ) === Assets::loop_before_render( $loop ), 'Un Loop Grid encola las capacidades de los widgets F4-Woo de sus plantillas.' );
	check_wt( ! empty( $GLOBALS['wt']['styles']['digitalisimo-woo-wishlist']['enqueued'] ) && ! empty( $GLOBALS['wt']['scripts']['digitalisimo-woo-wishlist']['enqueued'] ) && ! empty( $GLOBALS['wt']['scripts']['digitalisimo-woo-filters']['enqueued'] ), 'Se encolan aunque el bucle salga vacío.' );
	check_wt( 2 === count( $GLOBALS['wt']['meta_reads'] ), 'Una lectura de meta por plantilla.' );
	Assets::loop_before_render( $loop );
	check_wt( 2 === count( $GLOBALS['wt']['meta_reads'] ), 'Memorizado por petición.' );
	$loop->settings = array( 'template_id' => 52 );
	check_wt( array() === Assets::loop_before_render( $loop ), 'Una plantilla sin widgets F4-Woo no encola nada.' );
	$loop->name = 'heading';
	check_wt( array() === Assets::loop_before_render( $loop ), 'Otro widget no se revisa.' );

	// 8. REST: permiso explícito, carrito, sin caché sólo en las rutas propias, cuerpo acotado.
	check_wt( ! Rest::route( 'cards', array( 'methods' => 'POST', 'callback' => '__return_true' ) ), 'Una ruta sin permission_callback no se guarda.' );
	check_wt( ! Rest::route( '../x', array( 'methods' => 'GET', 'callback' => 'strval', 'permission_callback' => '__return_true' ) ), 'Una ruta con caracteres no admitidos no se guarda.' );
	$calls = 0;
	check_wt(
		Rest::route(
			'cards',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () { return true; },
				'digitalisimo_cart'   => true,
				'callback'            => function ( $request ) use ( &$calls ) { ++$calls; return array( 'ok' => true ); },
			)
		),
		'Ruta con permiso guardada.'
	);
	Rest::route( 'boom', array( 'methods' => 'GET', 'permission_callback' => function () { return true; }, 'callback' => function () { return new WP_Error( 'x', 'y', array( 'status' => 404 ) ); } ) );
	Rest::register();
	check_wt( 2 === count( $GLOBALS['wt']['routes'] ) && 'digitalisimo-elements/v1' === $GLOBALS['wt']['routes'][0][0] && '/woo/cards' === $GLOBALS['wt']['routes'][0][1], 'Las rutas van en digitalisimo-elements/v1/woo/.' );
	check_wt( ! isset( $GLOBALS['wt']['routes'][0][2][0]['digitalisimo_cart'] ), 'La clave propia no llega a register_rest_route().' );
	$response = call_user_func( $GLOBALS['wt']['routes'][0][2][0]['callback'], new WP_REST_Request( '/digitalisimo-elements/v1/woo/cards', '{}' ) );
	check_wt( 1 === $calls && 1 === $GLOBALS['wt']['cart'] && is_object( WC()->cart ), 'Con digitalisimo_cart, wc_load_cart() antes del callback.' );
	check_wt( $response instanceof WP_REST_Response && 'no-store, private' === $response->headers['Cache-Control'] && isset( $response->headers['Expires'] ), 'Respuesta con Cache-Control: no-store, private y las cabeceras de nocache_headers().' );
	check_wt( defined( 'DONOTCACHEPAGE' ) && defined( 'LSCACHE_NO_CACHE' ) && 1 === $GLOBALS['wt']['litespeed'] && 1 === $GLOBALS['wt']['nocache'], 'Señales de Visitor_Content::flag_page() en la ruta.' );
	call_user_func( $GLOBALS['wt']['routes'][0][2][0]['callback'], new WP_REST_Request() );
	check_wt( 1 === $GLOBALS['wt']['cart'], 'Con el carrito ya cargado, no se vuelve a cargar.' );
	$error = call_user_func( $GLOBALS['wt']['routes'][1][2][0]['callback'], new WP_REST_Request() );
	check_wt( $error instanceof WP_REST_Response && 404 === $error->status && 'no-store, private' === $error->headers['Cache-Control'], 'Un error también sale con sus cabeceras sin caché.' );
	$own   = Rest::post_dispatch( new WP_REST_Response( array() ), null, new WP_REST_Request( '/digitalisimo-elements/v1/woo/wishlist' ) );
	$other = Rest::post_dispatch( new WP_REST_Response( array() ), null, new WP_REST_Request( '/wc/store/v1/cart' ) );
	check_wt( 'no-store, private' === $own->headers['Cache-Control'] && array() === $other->headers, 'rest_post_dispatch: cabeceras sólo en las rutas propias (también los 401/403 del servidor).' );
	check_wt( ! isset( $GLOBALS['wt']['hooks']['rest_send_nocache_headers'] ), 'Nunca el filtro global rest_send_nocache_headers.' );
	check_wt( array( 'ids' => array( 1 ) ) === Rest::body( new WP_REST_Request( '', '{"ids":[1]}' ) ) && null === Rest::body( new WP_REST_Request( '', str_repeat( ' ', 8193 ) . '{}' ) ) && null === Rest::body( new WP_REST_Request( '', 'x' ) ) && null === Rest::body( new WP_REST_Request( '', '' ) ), 'body(): JSON de hasta 8 KB.' );
	check_wt( 'https://sitio.test/wp-json/digitalisimo-elements/v1/woo/cards' === Rest::url( 'cards' ) && Rest::is_own( Rest::full( 'quick-view' ) ), 'url(), full() e is_own().' );

	// Permiso por predicado (V-M22).
	$tree = array(
		array( 'id' => 'c1', 'elType' => 'container', 'elements' => array(
			array( 'id' => 'abc1234', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-wishlist', 'settings' => array( 'title' => 'x' ) ),
			array( 'id' => 'glob001', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 20 ),
			array( 'id' => 'glob002', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 21 ),
			array( 'id' => 'prod001', 'elType' => 'widget', 'widgetType' => 'woocommerce-products' ),
		) ),
	);
	wt_post( 10, 'page', 'publish', array( '_elementor_data' => json_encode( $tree ) ) );
	wt_post( 11, 'page', 'draft', array( '_elementor_data' => json_encode( $tree ) ) );
	wt_post( 12, 'page', 'publish', array( '_elementor_data' => json_encode( $tree ) ), array( 'post_password' => 'clave' ) );
	wt_post( 20, 'elementor_library', 'publish', array( '_elementor_data' => json_encode( array( array( 'id' => 'tpl0001', 'elType' => 'widget', 'widgetType' => 'woocommerce-products', 'settings' => array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'quickview' ) ) ) ) ) ) );
	wt_post( 21, 'elementor_library', 'publish', array( '_elementor_data' => json_encode( array( array( 'id' => 'tpl0002', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 20 ) ) ) ) );
	$is_list = function ( array $element ) { return 'digitalisimo-woo-wishlist' === ( $element['widgetType'] ?? '' ); };
	$has_qv  = function ( array $element ) { return 'woocommerce-products' === ( $element['widgetType'] ?? '' ) && 'yes' === ( $element['settings']['digitalisimo_wc_custom'] ?? '' ) && in_array( 'quickview', (array) ( $element['settings']['digitalisimo_wc_parts'] ?? array() ), true ); };
	$found   = Rest::element( 10, 'abc1234', $is_list );
	check_wt( is_array( $found ) && 'x' === $found['settings']['title'], 'element(): el widget del documento publicado, con sus ajustes.' );
	check_wt( null === Rest::element( 10, 'abc1234', $has_qv ) && null === Rest::element( 10, 'nadaaaa', $is_list ) && null === Rest::element( 10, 'a b', $is_list ) && null === Rest::element( 0, 'abc1234', $is_list ), 'Otro tipo, otro ID o un ID mal formado: null.' );
	check_wt( null === Rest::element( 11, 'abc1234', $is_list ) && null === Rest::element( 12, 'abc1234', $is_list ), 'Un borrador o un documento con contraseña: null para quien no puede editarlo.' );
	$GLOBALS['wt']['edit_post'] = array( 11 );
	check_wt( is_array( Rest::element( 11, 'abc1234', $is_list ) ), 'Quien puede editarlo (vista previa del editor) sí lo encuentra.' );
	$GLOBALS['wt']['edit_post'] = array();
	$global = Rest::element( 10, 'glob001', $has_qv );
	check_wt( is_array( $global ) && 'woocommerce-products' === $global['widgetType'] && array( 'id' => 'glob001', 'template' => 20 ) === $global['digitalisimo_global'], 'Un widget global se sigue una vez a su plantilla y el predicado ve sus ajustes.' );
	check_wt( null === Rest::element( 10, 'glob002', $has_qv ) && null === Rest::element( 10, 'prod001', $has_qv ), 'Un global que apunta a otro global no se sigue; sin la parte, null.' );
	check_wt( null === Rest::element( 10, 'abc1234', function () { return 1; } ), 'Sólo true vale como respuesta del predicado.' );

	// 9. Productos públicos: una sola regla.
	wt_post( 100, 'product', 'publish' );
	wt_post( 101, 'product', 'draft' );
	wt_post( 102, 'product', 'private' );
	wt_post( 103, 'product', 'publish', array(), array( 'post_password' => 'x' ) );
	wt_post( 104, 'product', 'publish' );
	wt_post( 105, 'product_variation', 'publish', array(), array( 'post_parent' => 100 ) );
	wt_post( 106, 'product_variation', 'publish', array(), array( 'post_parent' => 101 ) );
	wt_post( 107, 'product_variation', 'private', array(), array( 'post_parent' => 100 ) );
	wt_post( 108, 'page', 'publish' );
	$GLOBALS['wt']['hidden'] = array( 104 );
	foreach ( array( 100 => true, 101 => false, 102 => false, 103 => false, 104 => false, 105 => true, 106 => false, 107 => false, 108 => false, 999 => false, 0 => false, -1 => false ) as $id => $ok ) {
		check_wt( $ok === Products::is_public( $id ), "Producto {$id}: " . ( $ok ? 'público' : 'no público' ) . '.' );
	}
	check_wt( array( 100, 5, 7 ) === Products::ids( array( 100, '5', 'x', 0, -3, '100', 5.5, array(), '07', 7 ), 3 ) && array() === Products::ids( 'nada' ), 'ids(): enteros positivos sin repetir, con tope.' );
	$GLOBALS['wt']['primed'] = array();
	check_wt( array( 100, 105 ) === Products::filter_public( array( 101, 100, 105, 104, 108 ) ) && array( array( array( 101, 100, 105, 104, 108 ), false, true ) ) === $GLOBALS['wt']['primed'], 'filter_public(): sólo los públicos, en orden, con una precarga.' );

	// 10. Bandera de fragmentos (V.12.4): ninguna consulta en pedidos, productos ni autoguardados.
	wt_request();
	Module::load( $tmp, array( 'woocommerce' => true, 'wc_version' => '11.2.1' ) );
	$bar   = '[{"id":"b1","elType":"widget","widgetType":"digitalisimo-woo-shipping-bar"}]';
	$cart  = '[{"id":"b2","elType":"widget","widgetType":"woocommerce-menu-cart","settings":{"digitalisimo_cart_coupon":"yes"}}]';
	$plain = '[{"id":"b3","elType":"widget","widgetType":"heading"}]';
	$doc   = function ( int $id, bool $autosave = false ) {
		return new class( $id, $autosave ) {
			private $id;
			private $autosave;
			public function __construct( $id, $autosave ) { $this->id = $id; $this->autosave = $autosave; }
			public function get_id() { return $this->id; }
			public function get_main_id() { return $this->id; }
			public function is_autosave() { return $this->autosave; }
		};
	};
	Flags::admin_init();
	check_wt( 0 === (int) $GLOBALS['wt']['options'][ Flags::FRAGMENTS ]['value'] && $GLOBALS['wt']['options'][ Flags::FRAGMENTS ]['autoload'] && array() === $GLOBALS['wt']['options'][ Flags::SWATCHES ]['value'] && $GLOBALS['wt']['options'][ Flags::SWATCHES ]['autoload'], 'admin_init: las dos opciones existen siempre, con carga automática.' );
	check_wt( 1 === count( $GLOBALS['wpdb']->log ), 'Crear la bandera la calcula una vez.' );
	Flags::admin_init();
	check_wt( 1 === count( $GLOBALS['wpdb']->log ), 'Ya creadas, admin_init no consulta.' );
	$GLOBALS['wt']['get'] = array();
	check_wt( false === Flags::uses_fragments() && array() === $GLOBALS['wt']['get'], 'uses_fragments() sólo lee alloptions.' );
	$GLOBALS['wpdb']->log        = array();
	$GLOBALS['wt']['meta_reads'] = array();
	wt_post( 300, 'shop_order', 'wc-processing' );
	wt_post( 301, 'product', 'draft' );
	do_action( 'transition_post_status', 'wc-completed', 'wc-processing', get_post( 300 ) );
	do_action( 'transition_post_status', 'publish', 'draft', get_post( 301 ) );
	do_action( 'updated_post_meta', 1, 301, '_price', '10' );
	wt_post( 302, 'page', 'publish', array( '_elementor_edit_mode' => 'builder', '_elementor_data' => $plain ), array( 'autosave' => true ) );
	do_action( 'elementor/document/before_save', $doc( 302, true ), array() );
	do_action( 'elementor/document/after_save', $doc( 302, true ), array( 'elements' => json_decode( $bar, true ) ) );
	check_wt( array() === $GLOBALS['wpdb']->log && empty( $GLOBALS['wt']['meta_reads'] ) && false === Flags::uses_fragments(), 'Un pedido, un producto y un autoguardado: ni consultas ni lecturas de metas.' );
	wt_post( 310, 'page', 'publish', array( '_elementor_edit_mode' => 'builder', '_elementor_data' => $plain ) );
	do_action( 'elementor/document/before_save', $doc( 310 ), array() );
	do_action( 'elementor/document/after_save', $doc( 310 ), array( 'elements' => json_decode( $plain, true ) ) );
	check_wt( array() === $GLOBALS['wpdb']->log && false === Flags::uses_fragments(), 'Guardar sin la barra con la bandera en 0: ninguna consulta.' );
	$GLOBALS['wt']['meta'][310]['_elementor_data'] = $bar;
	do_action( 'elementor/document/before_save', $doc( 310 ), array() );
	do_action( 'elementor/document/after_save', $doc( 310 ), array( 'elements' => json_decode( $bar, true ) ) );
	check_wt( array() === $GLOBALS['wpdb']->log && true === Flags::uses_fragments(), 'Guardar con la barra: la bandera pasa a 1 sin consulta.' );
	wt_post( 311, 'page', 'publish', array( '_elementor_edit_mode' => 'builder', '_elementor_data' => $plain ) );
	do_action( 'elementor/document/before_save', $doc( 311 ), array() );
	do_action( 'elementor/document/after_save', $doc( 311 ), array( 'elements' => json_decode( $plain, true ) ) );
	check_wt( array() === $GLOBALS['wpdb']->log, 'Guardar otro documento que nunca la usó: ninguna consulta.' );
	wt_post( 312, 'elementor_library', 'publish', array( '_elementor_data' => $cart ) );
	do_action( 'elementor/document/before_save', $doc( 310 ), array() );
	$GLOBALS['wt']['meta'][310]['_elementor_data'] = $plain;
	do_action( 'updated_post_meta', 9, 310, '_elementor_data', $plain );
	check_wt( array() === $GLOBALS['wpdb']->log, 'Durante un guardado del editor, la meta no recalcula (lo hace after_save).' );
	do_action( 'elementor/document/after_save', $doc( 310 ), array( 'elements' => json_decode( $plain, true ) ) );
	check_wt( 1 === count( $GLOBALS['wpdb']->log ) && true === Flags::uses_fragments(), 'Quitar un uso consulta una vez; otro documento (el cupón) la mantiene.' );
	$sql = $GLOBALS['wpdb']->log[0][0];
	check_wt( false !== strpos( $sql, "JOIN wp_posts p ON p.ID = pm.post_id AND p.post_status = 'publish'" ) && false !== strpos( $sql, "pm.meta_key = '_elementor_data'" ) && 3 === substr_count( $sql, 'LIKE %s' ) && false !== strpos( $sql, 'LIMIT 1' ), 'La consulta: JOIN a posts publicados, tres LIKE y LIMIT 1.' );
	check_wt( '%"widgetType":"digitalisimo-woo-shipping-bar"%' === $GLOBALS['wpdb']->log[0][1][0] && '%"digitalisimo\\_cart\\_bar":"yes"%' === $GLOBALS['wpdb']->log[0][1][1], 'Los patrones pasan por esc_like().' );
	$GLOBALS['wpdb']->log = array();
	do_action( 'transition_post_status', 'draft', 'publish', (object) array( 'ID' => 312 ) );
	check_wt( 1 === count( $GLOBALS['wpdb']->log ), 'Despublicar un documento que la usa consulta.' );
	$GLOBALS['wt']['posts'][312]->post_status = 'draft';
	$GLOBALS['wpdb']->log = array();
	Flags::refresh();
	check_wt( false === Flags::uses_fragments(), 'Sin documentos publicados que la usen, la bandera vuelve a 0.' );
	$GLOBALS['wpdb']->log = array();
	$GLOBALS['wt']['posts'][312]->post_status = 'publish';
	do_action( 'transition_post_status', 'publish', 'draft', get_post( 312 ) );
	check_wt( array() === $GLOBALS['wpdb']->log && true === Flags::uses_fragments(), 'Publicar un documento que la usa: 1 sin consulta.' );
	do_action( 'transition_post_status', 'publish', 'publish', get_post( 312 ) );
	do_action( 'transition_post_status', 'draft', 'pending', get_post( 312 ) );
	check_wt( array() === $GLOBALS['wpdb']->log, 'Sin entrar en publish ni salir de él, nada.' );
	do_action( 'before_delete_post', 311 );
	do_action( 'deleted_post', 311 );
	check_wt( array() === $GLOBALS['wpdb']->log, 'Borrar un documento que no la usa: ninguna consulta.' );
	do_action( 'before_delete_post', 312 );
	do_action( 'deleted_post', 312 );
	check_wt( 1 === count( $GLOBALS['wpdb']->log ), 'Borrar uno que la usa: una consulta.' );
	$GLOBALS['wpdb']->log = array();
	wt_post( 313, 'page', 'publish', array( '_elementor_edit_mode' => 'builder' ) );
	Flags::set( false );
	do_action( 'updated_post_meta', 9, 313, '_elementor_data', $bar );
	check_wt( array() === $GLOBALS['wpdb']->log && true === Flags::uses_fragments(), 'Migración escribe _elementor_data con la barra: 1 sin consulta.' );
	do_action( 'added_post_meta', 9, 313, '_elementor_data', $plain );
	check_wt( 1 === count( $GLOBALS['wpdb']->log ), 'Migración la quita: una consulta (sin el valor anterior).' );
	wt_post( 314, 'revision', 'inherit', array( '_elementor_data' => $bar ) );
	$GLOBALS['wpdb']->log = array();
	do_action( 'updated_post_meta', 9, 314, '_elementor_data', $plain );
	check_wt( array() === $GLOBALS['wpdb']->log, 'Las revisiones no cuentan.' );
	do_action( 'digitalisimo_elements_kit_imported' );
	check_wt( 1 === count( $GLOBALS['wpdb']->log ), 'Importar un kit recalcula.' );
	check_wt( Flags::contains( $cart ) && Flags::contains( json_decode( $bar, true ) ) && ! Flags::contains( '[{"digitalisimo_cart_bar":""}]' ) && ! Flags::contains( null ), 'contains(): widget, rasgos en yes y nada más.' );
	check_wt( Flags::ensure( 'digitalisimo_elements_wishlist_legacy', 0 ) && ! Flags::ensure( 'digitalisimo_elements_wishlist_legacy', 0 ) && 0 === Flags::get( 'digitalisimo_elements_wishlist_legacy', -1 ) && 'x' === Flags::get( 'no_existe', 'x' ), 'ensure() y get() para otros frentes.' );

	// 11. Privacidad: un solo texto, clave por sitio, WP Consent API 2.1.0, borrado en el sitio correcto.
	wt_request();
	Module::load( $tmp, array( 'woocommerce' => true, 'wc_version' => '11.2.1' ) );
	$items = apply_filters( Privacy::FILTER, array( '<strong>digi_seen</strong>: visitas.' ) );
	check_wt( 2 === count( $items ) && '<strong>digi_seen</strong>: visitas.' === $items[0] && false !== strpos( $items[1], '<strong>digi_wishlist</strong>' ), 'Con un widget de lista en el paquete, el texto de política gana el elemento de la lista después de los de siempre.' );
	foreach ( array( 'almacenamiento del navegador', 'sólo después de pulsar el botón', '365 días', 'envía los números de producto al sitio, que no los guarda', 'acepta añadirla a su cuenta', 'exportar o borrar', 'lista de deseos de WooCommerce' ) as $needle ) {
		check_wt( false !== strpos( Privacy::wishlist_item(), $needle ), "El elemento de la lista dice «{$needle}»." );
	}
	check_wt( false === strpos( Privacy::wishlist_item(), 'No sale del navegador' ) && false === stripos( Privacy::wishlist_item(), 'cookie' ), 'Sin la frase falsa de la v1 ni llamarla cookie.' );
	Privacy::add_item( '<strong>digi_wishlist</strong>' );
	Privacy::add_item( '<strong>digi_wishlist</strong>' );
	check_wt( 2 === count( apply_filters( Privacy::FILTER, array() ) ), 'add_item() no repite.' );
	check_wt( 'digi_wishlist' === Privacy::storage_key(), 'Sitio único: digi_wishlist.' );
	$GLOBALS['wt']['multisite'] = true;
	$GLOBALS['wt']['blog']      = 3;
	check_wt( 'digi_wishlist_3' === Privacy::storage_key(), 'En una red: digi_wishlist_<sitio>.' );
	check_wt( false === Privacy::consent_supports_type(), 'Sin la WP Consent API no se declara nada.' );
	Privacy::register_cookie_info();
	// Firma de wp_add_cookie_info() en la WP Consent API 2.1.0 (inc/api-functions.php:392).
	eval( 'function wp_add_cookie_info( string $name, string $plugin_or_service, string $category, string $expires, string $cookie_function, string $collected_personal_data = "", bool $member_cookie = false, bool $administrator_cookie = false, string $type = "HTTP", string $domain = "" ): void { $GLOBALS["wt"]["cookies"][] = func_get_args(); }' );
	check_wt( Privacy::consent_supports_type(), 'Con la firma de 2.1.0 se admite el tipo de almacenamiento.' );
	do_action( 'init' );
	$cookie = $GLOBALS['wt']['cookies'][0] ?? array();
	check_wt( 'digi_wishlist_3' === ( $cookie[0] ?? '' ) && 'functional' === $cookie[2] && 'LOCALSTORAGE' === $cookie[8] && false === $cookie[6] && false === $cookie[7] && 9 === count( $cookie ), 'Se declara como almacenamiento local en functional, con la clave del sitio.' );
	$GLOBALS['wt']['multisite'] = false;
	$GLOBALS['wt']['blog']      = 1;
	check_wt( Privacy::forget_on_site( 5, 3, 'digitalisimo_wishlist' ) && array( array( 5, 'wp_3_digitalisimo_wishlist' ) ) === $GLOBALS['wt']['deleted'], 'forget_on_site(): la opción del sitio 3 con su prefijo, aunque el sitio en curso sea el 1.' );
	Privacy::forget_on_site( 5, 1, 'digitalisimo_wishlist' );
	check_wt( array( 5, 'wp_digitalisimo_wishlist' ) === $GLOBALS['wt']['deleted'][1] && ! Privacy::forget_on_site( 0, 1, 'x' ), 'Sitio principal con su prefijo; sin usuario, nada.' );
	check_wt( array( 'items_removed' => true, 'items_retained' => false, 'messages' => array( 'Hecho' ), 'done' => true ) === Privacy::eraser_result( true, array( 'Hecho' ) ), 'eraser_result(): los cuatro campos (W-10).' );
	Privacy::exporter( 'digitalisimo-elements-wishlist', 'Lista de deseos', 'strval' );
	Privacy::eraser( 'digitalisimo-elements-wishlist', 'Lista de deseos', 'strval' );
	$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array( 'otro' => array() ) );
	$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
	check_wt( array( 'exporter_friendly_name' => 'Lista de deseos', 'callback' => 'strval' ) === $exporters['digitalisimo-elements-wishlist'] && isset( $exporters['otro'] ) && 'Lista de deseos' === $erasers['digitalisimo-elements-wishlist']['eraser_friendly_name'], 'exporter() y eraser() se suman a los de WordPress.' );

	// 12. Textos visibles sin el nombre del proveedor y PHP 7.4 en la base.
	foreach ( $base as $file ) {
		$code = '';
		foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
			$code .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
		}
		check_wt( ! preg_match( '/\bmatch\s*\(|\bstr_contains\(|\bstr_starts_with\(|\bstr_ends_with\(|\?->|#\[|\bmixed\s+\$|\breadonly\b|\benum\s+[A-Z]/', $code ), basename( $file ) . ': compatible con PHP 7.4.' );
		check_wt( ! preg_match( "/__\(\s*'[^']*Elementor/i", $code ) && ! preg_match( "/esc_html__\(\s*'[^']*Elementor/i", $code ), basename( $file ) . ': textos visibles sin «Elementor».' );
		check_wt( false === strpos( $code, 'Automattic\\WooCommerce\\Internal' ), basename( $file ) . ': ninguna clase interna de WooCommerce.' );
	}
	define( 'REST_REQUEST', true );
	wt_request( array( 'post' => '7', 'action' => 'elementor' ), true );
	$GLOBALS['wt']['caps'] = array( 'edit_posts' );
	check_wt( ! Unknown_Guard::editor_request() && array() === Inert::register( new WT_Widgets() ), 'Con REST_REQUEST, ni la guarda ni Inert actúan.' );

	foreach ( array_reverse( glob( $GLOBALS['wt_tmp'] . '/{,*/,*/*/,*/*/*/}*', GLOB_BRACE ) ) as $path ) {
		is_dir( $path ) ? rmdir( $path ) : unlink( $path );
	}
	rmdir( $GLOBALS['wt_tmp'] );
	echo "F4-Woo · módulo: arranque y guardas, sin WooCommerce sólo el registro oculto del editor (nunca en REST), registro tolerante, recursos con defer, REST sin caché y con permiso por predicado, productos públicos, bandera sin consultas en pedidos, productos ni autoguardados, y privacidad en verde.\n";
}
