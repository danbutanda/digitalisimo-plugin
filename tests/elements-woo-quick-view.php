<?php
/**
 * F4-Woo, frente C: vista rápida (V.5).
 *
 * Con las clases reales del frente A (`Woo\Module` y su autocarga, `Rest`, `Products`, `Assets`, `Widget`, `Inert`) y
 * dobles de WordPress, WooCommerce y el constructor. Comprueba:
 * - V-B3: la configuración del pie de un variable, con sus dependencias resueltas desde el registro (alias sin
 *   archivo fuera, lo impreso fuera), los parámetros capturados por `wc_add_to_cart_variation_params` y el script de
 *   variaciones desencolado; nada si la página ya lo trae o falta una dependencia;
 * - la ruta: registrada por `Woo\Rest` con el carrito de WooCommerce (`wc_load_cart()`) y sin caché; permiso por
 *   predicado (el widget, la parte `quickview` de Productos, un `global` seguido a su plantilla, otro widget no);
 *   producto privado → 404; `return` ajena → la ficha;
 * - «Diseño propio»: el marcado que busca `wc_variations_image_update()`, el título con `data-digi-qv-title`, la acción
 *   del formulario hacia la página de origen y `digi_qv=1` sólo durante el render, sin
 *   `woocommerce_single_product_summary`; `templates` con `tmpl-variation-template` y `gallery_defaults`;
 * - «Plantilla de producto»: diferencia de colas con su cierre, parámetros localizados como objetos, `footer_html` de
 *   los *callbacks* añadidos durante el render, lo de una ficha encolado en la consulta simulada; `fallback` si algo
 *   no se puede reproducir; una plantilla que no es válida vuelve a «Diseño propio»;
 * - el botón (sin nonce ni estado del visitante), el widget (contrato de `Woo\Widget`, aviso de coste de PRO, ancho
 *   por variable) y la redirección tras el `POST` (V.5.5);
 * - recursos: hoja ≤ 4 KB y script ≤ 8 KB; ninguna clase interna de WooCommerce; ningún `<script>` ejecutable ni
 *   `<img` a mano en el render.
 */

namespace Elementor {
	class Controls_Manager {
		const TAB_STYLE = 'style'; const SELECT = 'select'; const SELECT2 = 'select2'; const TEXT = 'text'; const SWITCHER = 'switcher';
		const SLIDER = 'slider'; const COLOR = 'color'; const NUMBER = 'number'; const RAW_HTML = 'raw_html'; const ICONS = 'icons'; const DIMENSIONS = 'dimensions';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Plugin { public static $instance; public $editor; public $preview; public $frontend; public $documents; }
	abstract class Widget_Base {
		public $controls = array();
		public $settings = array();
		public function __construct( $settings = array() ) { $this->settings = $settings; }
		public function get_id() { return 'qv12345'; }
		public function start_controls_section( $id, $args = array() ) {}
		public function end_controls_section() {}
		public function add_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function add_responsive_control( $id, $args ) { $this->controls[ $id ] = $args; }
		public function add_group_control( $type, $args ) { $this->controls[ $args['name'] ] = $args; }
		public function get_settings_for_display() {
			$all = $this->settings;
			foreach ( $this->controls as $id => $c ) { if ( ! array_key_exists( $id, $all ) && array_key_exists( 'default', $c ) ) { $all[ $id ] = $c['default']; } }
			return $all;
		}
		public function boot() { $this->register_controls(); return $this; }
		public function html() { ob_start(); $this->render(); return ob_get_clean(); }
		protected function content_template() {}
	}
}
namespace Elementor\Core\Frontend { class Performance { public static function should_optimize_controls() { return false; } } }
namespace Digitalisimo\Elements {
	final class Image_Markup {
		public static function img( array $image, array $args = array() ) {
			$src = ! empty( $image['id'] ) ? '/i/' . (int) $image['id'] . '.jpg' : (string) ( $image['url'] ?? '' );
			return '' === $src ? '' : '<img src="' . $src . '" class="' . ( $args['class'] ?? '' ) . '" alt="' . ( $args['alt_exact'] ?? ( $args['alt'] ?? '' ) ) . '"' . ( ! empty( $args['decorative'] ) ? ' aria-hidden="true"' : '' ) . '>';
		}
		public static function alt( array $image, $fallback = '' ) { return (string) $fallback; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', dirname( __DIR__ ) . '/digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.165' );
	define( 'WC_VERSION', '11.2.1' );
	function check_qv( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }

	// ---------------------------------------------------------------- Ganchos con `$wp_filter` (como WP_Hook).
	$GLOBALS['wp_filter'] = array();
	$GLOBALS['qv_did']    = array();
	function qv_hook( $hook ) { if ( ! isset( $GLOBALS['wp_filter'][ $hook ] ) ) { $GLOBALS['wp_filter'][ $hook ] = (object) array( 'callbacks' => array() ); } return $GLOBALS['wp_filter'][ $hook ]; }
	function qv_id( $cb ) { return is_string( $cb ) ? $cb : ( is_array( $cb ) ? ( is_object( $cb[0] ) ? spl_object_hash( $cb[0] ) : $cb[0] ) . '::' . $cb[1] : spl_object_hash( $cb ) ); }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { qv_hook( $hook )->callbacks[ $priority ][ qv_id( $cb ) ] = array( 'function' => $cb, 'accepted_args' => $args ); return true; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
	function remove_filter( $hook, $cb, $priority = 10 ) { unset( qv_hook( $hook )->callbacks[ $priority ][ qv_id( $cb ) ] ); return true; }
	function remove_action( $hook, $cb, $priority = 10 ) { return remove_filter( $hook, $cb, $priority ); }
	function has_filter( $hook ) { foreach ( qv_hook( $hook )->callbacks as $list ) { if ( $list ) { return true; } } return false; }
	function apply_filters( $hook, $value, ...$rest ) {
		$callbacks = qv_hook( $hook )->callbacks;
		ksort( $callbacks );
		foreach ( $callbacks as $list ) { foreach ( $list as $c ) { $value = call_user_func_array( $c['function'], array_slice( array_merge( array( $value ), $rest ), 0, $c['accepted_args'] ) ); } }
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['qv_did'][ $hook ] = ( $GLOBALS['qv_did'][ $hook ] ?? 0 ) + 1;
		$callbacks = qv_hook( $hook )->callbacks;
		ksort( $callbacks );
		foreach ( $callbacks as $list ) { foreach ( $list as $c ) { call_user_func_array( $c['function'], array_slice( $args, 0, $c['accepted_args'] ) ); } }
	}
	function did_action( $hook ) { return $GLOBALS['qv_did'][ $hook ] ?? 0; }
	function __( $t ) { return $t; }
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $t ) { return esc_html( $t ); }
	function esc_html__( $t ) { return esc_html( $t ); }
	function esc_url( $u ) { return esc_html( $u ); }
	function esc_url_raw( $u ) { return (string) $u; }
	function wp_kses_post( $t ) { return (string) $t; }
	function wp_strip_all_tags( $t ) { return trim( strip_tags( (string) $t ) ); }
	function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
	function sanitize_text_field( $t ) { return trim( (string) $t ); }
	function wp_unslash( $v ) { return $v; }
	function absint( $v ) { return abs( (int) $v ); }
	function add_query_arg( $k, $v, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
	function remove_query_arg( $keys, $url ) { $parts = explode( '?', $url, 2 ); if ( ! isset( $parts[1] ) ) { return $url; } parse_str( $parts[1], $q ); foreach ( (array) $keys as $k ) { unset( $q[ $k ] ); } return $parts[0] . ( $q ? '?' . http_build_query( $q ) : '' ); }
	function wp_validate_redirect( $url, $default = '' ) { $host = parse_url( $url, PHP_URL_HOST ); return ( null === $host || 'sitio.test' === $host ) && '' !== $url ? $url : $default; }
	function is_ssl() { return true; }
	function get_option( $k, $d = false ) { return $GLOBALS['qv_options'][ $k ] ?? $d; }
	function rest_url( $path = '' ) { return 'https://sitio.test/wp-json/' . ltrim( $path, '/' ); }
	function wp_get_nocache_headers() { return array( 'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT', 'Cache-Control' => 'no-cache, must-revalidate, max-age=0' ); }
	function is_wp_error( $v ) { return $v instanceof WP_Error; }
	function rest_ensure_response( $v ) { return $v instanceof WP_REST_Response ? $v : new WP_REST_Response( $v ); }
	function rest_convert_error_to_response( $e ) { return new WP_REST_Response( array( 'code' => $e->code ), $e->data['status'] ?? 500 ); }
	function register_rest_route( $ns, $route, $args ) { $GLOBALS['qv_routes'][ $ns . $route ] = $args; return true; }
	function current_user_can( $cap, $id = 0 ) { return false; }
	function plugins_url( $path, $file ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	class WP_Error { public $code; public $data; public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; $this->data = $data; } }
	class WP_REST_Response {
		public $data; public $status; public $headers = array();
		public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
		public function header( $k, $v ) { $this->headers[ $k ] = $v; }
	}
	class WP_Post { public $ID; public $post_type; public $post_status = 'publish'; public $post_password = ''; public $post_parent = 0; public $post_excerpt = ''; }

	// ---------------------------------------------------------------- Entradas, productos y documentos.
	function qv_post( $id, $type, $status = 'publish', $excerpt = '' ) { $p = new WP_Post(); $p->ID = $id; $p->post_type = $type; $p->post_status = $status; $p->post_excerpt = $excerpt; return $p; }
	$GLOBALS['qv_posts'] = array(
		5  => qv_post( 5, 'page' ),
		6  => qv_post( 6, 'elementor_library' ),
		7  => qv_post( 7, 'page', 'draft' ),
		10 => qv_post( 10, 'product', 'publish', 'Algodón orgánico.' ),
		11 => qv_post( 11, 'product', 'private' ),
		12 => qv_post( 12, 'product' ),
		77 => qv_post( 77, 'elementor_library' ),
	);
	$GLOBALS['qv_meta'] = array(
		77 => array( '_elementor_template_type' => 'product' ),
		6  => array( '_elementor_template_type' => 'widget', '_elementor_data' => wp_json_encode( array( array( 'id' => 'g1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-quick-view', 'settings' => array() ) ) ) ),
		5  => array( '_elementor_data' => wp_json_encode( array( array( 'id' => 'sec', 'elType' => 'container', 'elements' => array(
			array( 'id' => 'qv12345', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-quick-view', 'settings' => array( 'qv_parts' => array( 'image', 'title', 'price', 'cart', 'meta' ), 'qv_title_tag' => 'h3' ) ),
			array( 'id' => 'tpl1234', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-quick-view', 'settings' => array( 'content_mode' => 'template', 'digitalisimo_template_id' => '77' ) ),
			array( 'id' => 'bad1234', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-quick-view', 'settings' => array( 'content_mode' => 'template', 'digitalisimo_template_id' => '6' ) ),
			array( 'id' => 'prod123', 'elType' => 'widget', 'widgetType' => 'woocommerce-products', 'settings' => array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'image', 'quickview' ) ) ),
			array( 'id' => 'prod456', 'elType' => 'widget', 'widgetType' => 'woocommerce-products', 'settings' => array( 'digitalisimo_wc_parts' => array( 'quickview' ) ) ),
			array( 'id' => 'glob123', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 6 ),
			array( 'id' => 'head123', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array() ),
		) ) ) ) ),
		7  => array( '_elementor_data' => wp_json_encode( array( array( 'id' => 'qv12345', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-quick-view', 'settings' => array() ) ) ) ),
	);
	function get_post( $id = null ) { if ( null === $id ) { return $GLOBALS['post'] ?? null; } return $GLOBALS['qv_posts'][ (int) $id ] ?? null; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['qv_meta'][ $id ][ $key ] ?? ''; }
	function get_post_field( $field, $id ) { $p = get_post( $id ); return $p ? $p->$field : ''; }
	function get_post_type( $id ) { $p = get_post( $id ); return $p ? $p->post_type : false; }
	function get_the_ID() { return isset( $GLOBALS['post'] ) && $GLOBALS['post'] ? $GLOBALS['post']->ID : 0; }
	function setup_postdata( $post ) { do_action( 'the_post', $post ); return true; }
	function is_product() { return isset( $GLOBALS['wp_query'] ) && is_object( $GLOBALS['wp_query'] ) && ! empty( $GLOBALS['wp_query']->is_product ); }
	class WP_Query {
		public $args; public $is_product; private $done = false;
		public function __construct( $args ) { $this->args = $args; $this->is_product = 'product' === ( $args['post_type'] ?? '' ); }
		public function have_posts() { return ! $this->done; }
		public function the_post() { $this->done = true; $GLOBALS['post'] = get_post( $this->args['p'] ); setup_postdata( $GLOBALS['post'] ); }
	}
	class WooCommerce { public function include_template_functions() {} }
	$GLOBALS['qv_wc'] = (object) array( 'cart' => null );
	function WC() { return $GLOBALS['qv_wc']; }
	function wc_load_cart() { $GLOBALS['qv_wc']->cart = new \stdClass(); }
	class WC_Product {
		public $d;
		public function __construct( array $d ) { $this->d = $d + array( 'type' => 'simple', 'image' => 0, 'gallery' => array(), 'sku' => '', 'rating' => 0 ); }
		public function get_id() { return $this->d['id']; }
		public function get_type() { return $this->d['type']; }
		public function is_type( $t ) { return $this->d['type'] === $t; }
		public function get_name() { return $this->d['name']; }
		public function get_permalink() { return 'https://sitio.test/producto/' . $this->d['slug'] . '/'; }
		public function get_image_id() { return $this->d['image']; }
		public function get_gallery_image_ids() { return $this->d['gallery']; }
		public function get_price_html() { return '<span class="amount">25 €</span>'; }
		public function get_sku() { return $this->d['sku']; }
		public function get_rating_count() { return $this->d['rating']; }
		public function get_average_rating() { return '4.5'; }
		public function is_visible() { return true; }
	}
	$GLOBALS['qv_products'] = array(
		10 => new WC_Product( array( 'id' => 10, 'name' => 'Camiseta <i>lisa</i>', 'slug' => 'camiseta', 'type' => 'variable', 'image' => 55, 'gallery' => array( 56, 57 ), 'sku' => 'CAM-1', 'rating' => 3 ) ),
		11 => new WC_Product( array( 'id' => 11, 'name' => 'Privado', 'slug' => 'privado' ) ),
		12 => new WC_Product( array( 'id' => 12, 'name' => 'Taza', 'slug' => 'taza' ) ),
	);
	function wc_get_product( $id = false ) { return $GLOBALS['qv_products'][ (int) $id ] ?? null; }
	function wc_review_ratings_enabled() { return true; }
	function wc_get_rating_html( $avg, $count ) { return '<div class="star-rating">' . $avg . '</div>'; }
	function wc_product_sku_enabled() { return true; }
	function wc_get_product_category_list( $id, $sep = ', ' ) { return '<a href="/cat/ropa/">Ropa</a>'; }
	function wc_placeholder_img_src( $size = '' ) { return '/placeholder.png'; }
	function wp_get_attachment_image_src( $id, $size = '' ) { return array( '/i/' . $id . '-' . $size . '.jpg', 600, 600 ); }
	function wp_get_attachment_image_srcset( $id, $size = '' ) { return '/i/' . $id . '.jpg 600w'; }
	function wc_get_template( $name ) { if ( 'single-product/add-to-cart/variation.php' === $name ) { echo '<script type="text/template" id="tmpl-variation-template"><div class="woocommerce-variation-price">{{{ data.variation.price_html }}}</div></script><script type="text/template" id="tmpl-unavailable-variation-template"><p role="alert">No disponible.</p></script>'; } }
	function wc_get_product_gallery_html( $product ) { return '<div class="woocommerce-product-gallery">' . $product->get_id() . '</div>'; }
	function woocommerce_template_single_add_to_cart() {
		global $product;
		echo '<form class="' . ( $product->is_type( 'variable' ) ? 'variations_form ' : '' ) . 'cart" action="' . esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ) . '" method="post">';
		do_action( 'woocommerce_before_add_to_cart_button' );
		echo '<button type="submit" name="add-to-cart" value="' . $product->get_id() . '" class="single_add_to_cart_button">Añadir</button>';
		do_action( 'woocommerce_after_add_to_cart_button' );
		echo '</form>';
	}

	// ---------------------------------------------------------------- Registro de scripts y hojas (como WP_Dependencies).
	final class QV_Deps {
		public $registered = array(); public $queue = array(); public $done = array();
		public $base_url = 'https://sitio.test'; public $content_url = 'https://sitio.test/wp-content'; public $default_version = '7.1';
		public function add( $h, $src, $deps = array(), $ver = false, $args = null ) { if ( isset( $this->registered[ $h ] ) ) { return false; } $this->registered[ $h ] = (object) array( 'handle' => $h, 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'args' => $args, 'extra' => array() ); return true; }
		public function get_data( $h, $k ) { return $this->registered[ $h ]->extra[ $k ] ?? false; }
		public function add_data( $h, $k, $v ) { if ( ! isset( $this->registered[ $h ] ) ) { return false; } $this->registered[ $h ]->extra[ $k ] = $v; return true; }
	}
	$GLOBALS['qv_scripts'] = new QV_Deps();
	$GLOBALS['qv_styles']  = new QV_Deps();
	function wp_scripts() { return $GLOBALS['qv_scripts']; }
	function wp_styles() { return $GLOBALS['qv_styles']; }
	function qv_in( $deps, $h ) {
		foreach ( $deps->queue as $q ) { if ( $q === $h || qv_dep( $deps, $q, $h ) ) { return true; } }
		return false;
	}
	function qv_dep( $deps, $from, $h ) { foreach ( (array) ( $deps->registered[ $from ]->deps ?? array() ) as $d ) { if ( $d === $h || qv_dep( $deps, $d, $h ) ) { return true; } } return false; }
	function wp_script_is( $h, $list = 'enqueued' ) { return 'registered' === $list ? isset( wp_scripts()->registered[ $h ] ) : qv_in( wp_scripts(), $h ); }
	function wp_style_is( $h, $list = 'enqueued' ) { return 'registered' === $list ? isset( wp_styles()->registered[ $h ] ) : qv_in( wp_styles(), $h ); }
	function wp_register_script( $h, $src, $deps = array(), $ver = false, $args = array() ) { return wp_scripts()->add( $h, $src, $deps, $ver ); }
	function wp_register_style( $h, $src, $deps = array(), $ver = false, $media = 'all' ) { return wp_styles()->add( $h, $src, $deps, $ver, $media ); }
	function wp_enqueue_script( $h ) { if ( isset( wp_scripts()->registered[ $h ] ) && ! in_array( $h, wp_scripts()->queue, true ) ) { wp_scripts()->queue[] = $h; } }
	function wp_enqueue_style( $h ) { if ( isset( wp_styles()->registered[ $h ] ) && ! in_array( $h, wp_styles()->queue, true ) ) { wp_styles()->queue[] = $h; } }
	function wp_dequeue_script( $h ) { wp_scripts()->queue = array_values( array_diff( wp_scripts()->queue, array( $h ) ) ); }
	function wp_add_inline_script( $h, $code, $pos = 'after' ) { $cur = wp_scripts()->get_data( $h, $pos ) ?: array(); $cur[] = $code; return wp_scripts()->add_data( $h, $pos, $cur ); }
	function wp_localize_script( $h, $name, $data ) { return wp_scripts()->add_data( $h, 'data', 'var ' . $name . ' = ' . wp_json_encode( $data ) . ';' ); }
	function qv_register_wc() {
		$s = wp_scripts();
		$s->add( 'jquery-core', '/wp-includes/js/jquery/jquery.min.js', array(), '3.7.1' );
		$s->add( 'jquery', false, array( 'jquery-core' ) );
		$s->add( 'underscore', '/wp-includes/js/underscore.min.js', array(), '1.13.7' );
		$s->add( 'wp-util', '/wp-includes/js/wp-util.min.js', array( 'underscore', 'jquery' ) );
		$s->add_data( 'wp-util', 'data', 'var _wpUtilSettings = {"ajax":{"url":"\/wp-admin\/admin-ajax.php"}};' );
		$s->add( 'wc-jquery-blockui', 'https://sitio.test/wp-content/plugins/woocommerce/assets/js/jquery-blockui/jquery.blockUI.min.js', array( 'jquery' ), '2.7.0-wc.11.2.1' );
		$s->add( 'jquery-blockui', false, array( 'wc-jquery-blockui' ), '2.7.0-wc.11.2.1' );
		$s->add( 'wc-add-to-cart-variation', 'https://sitio.test/wp-content/plugins/woocommerce/assets/js/frontend/add-to-cart-variation.min.js', array( 'jquery', 'wp-util', 'jquery-blockui' ), '11.2.1' );
		$s->add( 'wc-single-product', 'https://sitio.test/wp-content/plugins/woocommerce/assets/js/frontend/single-product.min.js', array( 'jquery' ), '11.2.1' );
		$s->add( 'woocommerce', 'https://sitio.test/wp-content/plugins/woocommerce/assets/js/frontend/woocommerce.min.js', array( 'jquery' ), '11.2.1' );
		wp_styles()->add( 'woocommerce-general', 'https://sitio.test/wc.css', array(), '11.2.1', 'all' );
		wp_styles()->add( 'photoswipe-default-skin', 'https://sitio.test/pswp.css', array(), '11.2.1', 'all' );
	}
	/** Doble de `WC_Frontend_Scripts`: registra, encola lo de una ficha si `is_product()` y localiza como 11.2.1. */
	class WC_Frontend_Scripts {
		public static $localized = array();
		public static function load_scripts() {
			qv_register_wc();
			wp_enqueue_script( 'woocommerce' );
			wp_enqueue_style( 'woocommerce-general' );
			if ( is_product() ) {
				wp_enqueue_script( 'wc-single-product' );
				wp_enqueue_style( 'photoswipe-default-skin' );
				add_action( 'wp_footer', 'woocommerce_photoswipe' );
			}
		}
		public static function localize_printed_scripts() {
			foreach ( array( 'wc-add-to-cart-variation', 'wc-single-product' ) as $h ) {
				if ( in_array( $h, self::$localized, true ) || ! wp_script_is( $h ) ) { continue; }
				self::$localized[] = $h;
				if ( 'wc-add-to-cart-variation' === $h ) {
					wc_get_template( 'single-product/add-to-cart/variation.php' );
					wp_localize_script( $h, 'wc_add_to_cart_variation_params', apply_filters( 'wc_add_to_cart_variation_params', array( 'wc_ajax_url' => '/?wc-ajax=%%endpoint%%', 'i18n_no_matching_variations_text' => 'Sin coincidencias' ) ) );
				} else {
					wp_localize_script( $h, 'wc_single_product_params', apply_filters( 'wc_single_product_params', array( 'flexslider_enabled' => 1 ) ) );
				}
			}
		}
	}
	function woocommerce_photoswipe() { echo '<div class="pswp" tabindex="-1" role="dialog"></div>'; }

	// ---------------------------------------------------------------- Clases reales.
	$root = dirname( __DIR__ ) . '/digitalisimo-elements';
	require $root . '/modules/digitalisimo-woo/class-module.php';
	$woo = \Digitalisimo\Elements\Woo\Module::class;
	$woo::register_autoloader();
	\Digitalisimo\Elements\Woo\Rest::init();
	$_POST = array();
	require $root . '/modules/digitalisimo-woo/quick-view/class-module.php';
	\Digitalisimo\Elements\Woo\Quick_View\Module::init();
	// Los símbolos se comprueban en `plugins_loaded`, antes de que WooCommerce cargue `wc-template-functions.php`
	// (`after_setup_theme`): exigir una función de plantilla (`woocommerce_*()`) dejaba la vista rápida sin ruta ni
	// configuración en todos los sitios (woo-flow W4/W5). Se exige el método que las carga.
	$qv_symbols = \Digitalisimo\Elements\Woo\Quick_View\Module::SYMBOLS;
	check_qv( ! preg_grep( '/^woocommerce_[a-z_]+\(\)$/', $qv_symbols ) && in_array( 'WooCommerce::include_template_functions', $qv_symbols, true ), 'Ningún símbolo exigido al arrancar es una función de plantilla de WooCommerce: ' . implode( ', ', $qv_symbols ) );
	check_qv( array() === ( \Digitalisimo\Elements\Woo\Module::missing()['quick-view'] ?? array() ), 'La vista rápida arranca con WooCommerce: ' . wp_json_encode( \Digitalisimo\Elements\Woo\Module::missing() ) );
	$qv       = 'Digitalisimo\\Elements\\Woo\\Quick_View\\';
	$list     = $qv . 'Asset_List';
	$route    = $qv . 'Route';
	$render   = $qv . 'Render';
	$template = $qv . 'Template';
	$trigger  = $qv . 'Trigger';

	// ---------------------------------------------------------------- Lista de recursos.
	qv_register_wc();
	$scripts = wp_scripts();
	check_qv( array( 'jquery-core', 'jquery', 'underscore', 'wp-util', 'wc-jquery-blockui', 'jquery-blockui', 'wc-add-to-cart-variation' ) === $list::closure( array( 'wc-add-to-cart-variation' ), $scripts ), 'Cierre ordenado desde el registro, con el alias de versiones anteriores (V-M24).' );
	check_qv( null === $list::closure( array( 'nada' ), $scripts ), 'Una dependencia sin registrar invalida la lista.' );
	check_qv( array( 'a' => array( 'b' => 1 ), 'c' => 'x' ) === $list::localized( "var a = {\"b\":1};\nvar c = \"x\";" ) && null === $list::localized( 'window.a = 1;' ), 'Datos localizados como objetos; otra forma, nada.' );
	$one = $list::scripts( array( 'wc-add-to-cart-variation' ), $scripts, array( 'jquery-core' => true ) );
	check_qv( array( 'underscore', 'wp-util', 'wc-jquery-blockui', 'wc-add-to-cart-variation' ) === array_column( $one['scripts'], 'handle' ), 'Sin alias ni lo que la página ya tiene.' );
	check_qv( 'https://sitio.test/wp-includes/js/underscore.min.js?ver=1.13.7' === $one['scripts'][0]['src'] && 'https://sitio.test/wp-includes/js/wp-util.min.js?ver=7.1' === $one['scripts'][1]['src'], 'URL como la imprime WordPress: `base_url` y `ver` (por defecto si es `false`).' );
	check_qv( isset( $one['params']['_wpUtilSettings'] ), 'Los datos de `wp-util` viajan como parámetros.' );
	wp_add_inline_script( 'underscore', 'window.x=1;', 'after' );
	check_qv( null === $list::scripts( array( 'wc-add-to-cart-variation' ), $scripts, array(), false ) && 'window.x=1;' === $list::scripts( array( 'underscore' ), $scripts )['scripts'][0]['after'], 'Código en línea: rechazado en la configuración del pie, admitido en el modo plantilla.' );
	$GLOBALS['qv_scripts'] = new QV_Deps();
	$GLOBALS['qv_styles']  = new QV_Deps();

	// ---------------------------------------------------------------- V-B3: configuración del pie.
	\Digitalisimo\Elements\Woo\Assets::register();
	qv_register_wc();
	wp_scripts()->done = array( 'jquery-core' );
	ob_start();
	$config = \Digitalisimo\Elements\Woo\Quick_View\Variation::footer_config();
	$printed = ob_get_clean();
	check_qv( '' === $printed, 'La plantilla que imprime la localización se descarta en la página (viaja en la respuesta).' );
	check_qv( is_array( $config ) && array( 'underscore', 'wp-util', 'wc-jquery-blockui', 'wc-add-to-cart-variation' ) === array_column( $config['scripts'], 'handle' ), 'Dependencias resueltas y sin lo impreso: ' . wp_json_encode( $config ) );
	check_qv( array( 'wc_ajax_url' => '/?wc-ajax=%%endpoint%%', 'i18n_no_matching_variations_text' => 'Sin coincidencias' ) === $config['params']['wc_add_to_cart_variation_params'], 'Parámetros capturados por `wc_add_to_cart_variation_params`.' );
	check_qv( ! wp_script_is( 'wc-add-to-cart-variation' ) && ! has_filter( 'wc_add_to_cart_variation_params' ), 'El script vuelve a quedar sin encolar y el filtro, quitado.' );
	WC_Frontend_Scripts::$localized = array();
	wp_enqueue_script( 'wc-add-to-cart-variation' );
	check_qv( null === \Digitalisimo\Elements\Woo\Quick_View\Variation::footer_config(), 'Con el script de variaciones ya en la página, nada.' );
	wp_dequeue_script( 'wc-add-to-cart-variation' );
	unset( wp_scripts()->registered['wc-add-to-cart-variation'] );
	check_qv( null === \Digitalisimo\Elements\Woo\Quick_View\Variation::footer_config(), 'Sin registrar, nada (la vista rápida de un variable abre la ficha).' );
	qv_register_wc();
	WC_Frontend_Scripts::$localized = array();
	check_qv( ! did_action( 'x' ) && null !== \Digitalisimo\Elements\Woo\Quick_View\Variation::footer_config(), 'Vuelve a calcularse.' );
	WC_Frontend_Scripts::$localized = array();
	wp_scripts()->add_data( 'wc-add-to-cart-variation', 'data', false );
	ob_start();
	\Digitalisimo\Elements\Woo\Quick_View\Module::footer_config();
	ob_end_clean();
	check_qv( false === wp_scripts()->get_data( 'digitalisimo-woo-quick-view', 'before' ), 'Sin el script de la vista rápida encolado, la página no paga nada.' );
	wp_enqueue_script( 'digitalisimo-woo-quick-view' );
	WC_Frontend_Scripts::$localized = array();
	wp_scripts()->add_data( 'wc-add-to-cart-variation', 'data', false );
	\Digitalisimo\Elements\Woo\Quick_View\Module::footer_config();
	$inline = (array) wp_scripts()->get_data( 'digitalisimo-woo-quick-view', 'before' );
	check_qv( 1 === count( $inline ) && 0 === strpos( $inline[0], 'window.digitalisimoQuickView={"rest":"https://sitio.test/wp-json/digitalisimo-elements/v1/woo/quick-view","variation":{"scripts":[' ), 'Configuración con `wp_add_inline_script( …, before )` (R-M10): ' . ( $inline[0] ?? '' ) );
	check_qv( 6 === ( qv_hook( 'wp_print_footer_scripts' )->callbacks ? array_keys( qv_hook( 'wp_print_footer_scripts' )->callbacks )[0] : 0 ), 'En `wp_print_footer_scripts` 6, tras la localización de WooCommerce (5).' );
	check_qv( false === strpos( $inline[0], 'nonce' ), 'La configuración es igual para todos: sin nonce.' );

	// ---------------------------------------------------------------- Ruta.
	$routes = \Digitalisimo\Elements\Woo\Rest::routes();
	check_qv( isset( $routes['quick-view'][0] ) && 'GET' === $routes['quick-view'][0]['methods'], 'Ruta `woo/quick-view` declarada en Woo\Rest.' );
	$endpoint = $routes['quick-view'][0];
	$request  = static function ( array $p ) { return new class( $p ) implements \ArrayAccess {
		private $p; public function __construct( $p ) { $this->p = $p; }
		public function offsetExists( $k ): bool { return isset( $this->p[ $k ] ); }
		#[\ReturnTypeWillChange] public function offsetGet( $k ) { return $this->p[ $k ] ?? null; }
		public function offsetSet( $k, $v ): void {}
		public function offsetUnset( $k ): void {}
		public function get_route() { return '/digitalisimo-elements/v1/woo/quick-view'; }
	}; };
	$base = array( 'post_id' => 5, 'widget_id' => 'qv12345', 'product' => 10, 'return' => 'https://sitio.test/tienda/?orden=precio#arriba' );
	check_qv( true === call_user_func( $endpoint['permission_callback'], $request( $base ) ), 'Permiso: el widget del documento publicado.' );
	foreach ( array( 'prod123' => true, 'prod456' => false, 'glob123' => true, 'head123' => false, 'nadie99' => false ) as $id => $ok ) {
		$route::reset();
		$result = call_user_func( $endpoint['permission_callback'], $request( array( 'widget_id' => $id ) + $base ) );
		check_qv( $ok === ( true === $result ), 'Predicado de permiso para ' . $id . ' (V-M22).' );
		check_qv( $ok || ( $result instanceof WP_Error && 403 === $result->data['status'] ), 'Sin permiso, 403.' );
	}
	$route::reset();
	check_qv( true !== call_user_func( $endpoint['permission_callback'], $request( array( 'post_id' => 7 ) + $base ) ), 'Un borrador no da permiso a un visitante.' );
	check_qv( call_user_func( $endpoint['args']['widget_id']['validate_callback'], 'qv12345' ) && ! call_user_func( $endpoint['args']['widget_id']['validate_callback'], '<x>' ) && ! call_user_func( $endpoint['args']['product']['validate_callback'], '-3' ), 'Entradas validadas.' );

	$route::reset();
	$GLOBALS['qv_did'] = array( 'woocommerce_init' => 1 );
	$response = call_user_func( $endpoint['callback'], $request( array( 'product' => 11 ) + $base ) );
	check_qv( 404 === $response->status && 'digitalisimo_quick_view_not_found' === $response->data['code'], 'Producto privado: 404 sin detalle.' );
	check_qv( null !== $GLOBALS['qv_wc']->cart, 'La ruta carga el carrito de WooCommerce (V-I8).' );
	check_qv( 'no-store, private' === $response->headers['Cache-Control'], 'Sin caché, también en los errores.' );

	// ---------------------------------------------------------------- «Diseño propio».
	$GLOBALS['qv_scripts'] = new QV_Deps();
	$GLOBALS['qv_styles']  = new QV_Deps();
	$route::reset();
	$GLOBALS['qv_did'] = array( 'woocommerce_init' => 1 );
	$response = call_user_func( $endpoint['callback'], $request( $base ) );
	$data     = $response->data;
	check_qv( 200 === $response->status && 'own' === $data['mode'] && true === $data['variable'] && 10 === $data['product'] && 'Camiseta lisa' === $data['title'], 'Respuesta de «Diseño propio».' );
	$html = $data['html'];
	check_qv( 1 === preg_match( '#<div class="digi-qv__product product[^"]*"><div class="digi-qv__media images"><div class="woocommerce-product-gallery__image"><img src="/i/55.jpg" class="wp-post-image"#', $html ), 'Marcado de `wc_variations_image_update()` (V-I6): ' . substr( $html, 0, 200 ) );
	check_qv( false !== strpos( $html, '<h3 class="digi-qv__title product_title" data-digi-qv-title>Camiseta lisa</h3>' ), 'Título visible con `data-digi-qv-title` y la etiqueta elegida.' );
	check_qv( false !== strpos( $html, 'action="https://sitio.test/tienda/?orden=precio"' ) && false !== strpos( $html, '<input type="hidden" name="digi_qv" value="1">' ), 'Formulario hacia la página de origen (sin fragmento) con `digi_qv=1` (V.5.5).' );
	check_qv( false !== strpos( $html, '<span class="sku_wrapper">Referencia: <span class="sku">CAM-1</span>' ), 'Referencia con las clases que actualiza el script de variaciones.' );
	check_qv( false === strpos( $html, 'digi-qv__thumb' ) && false === strpos( $html, 'digi-qv__excerpt' ), 'Sólo las partes elegidas.' );
	check_qv( ! has_filter( 'woocommerce_add_to_cart_form_action' ) && ! has_filter( 'woocommerce_after_add_to_cart_button' ), 'Los filtros del formulario sólo durante el render.' );
	check_qv( ! did_action( 'woocommerce_single_product_summary' ), 'Sin `woocommerce_single_product_summary`: sin JSON-LD duplicado (V.13).' );
	check_qv( false !== strpos( $data['templates'], 'id="tmpl-variation-template"' ) && false !== strpos( $data['templates'], 'tmpl-unavailable-variation-template' ), '`templates` con las plantillas de variaciones (V.5.1).' );
	check_qv( '<div class="woocommerce-product-gallery">10</div>' === $data['gallery_defaults'], '`gallery_defaults` del producto.' );
	check_qv( ! preg_match( '/<script(?![^>]*type="text\/template")/', $html ), 'Ningún <script> ejecutable en el render.' );
	check_qv( array( 'styles' => array(), 'scripts' => array(), 'params' => array() ) === $data['assets'] && '' === $data['footer_html'], 'Sin muestras, `assets` vacío.' );
	check_qv( ! isset( $GLOBALS['product'] ), 'El producto global vuelve a como estaba.' );

	$route::reset();
	$response = call_user_func( $endpoint['callback'], $request( array( 'widget_id' => 'prod123', 'product' => 12, 'return' => 'https://otro.test/robo' ) + $base ) );
	$html = $response->data['html'];
	check_qv( false === $response->data['variable'] && '' === $response->data['templates'] && '' === $response->data['gallery_defaults'], 'Un simple no trae plantillas.' );
	check_qv( false !== strpos( $html, 'action="https://sitio.test/producto/taza/"' ), 'Una `return` de otro sitio se rechaza: la acción es la ficha.' );
	check_qv( false !== strpos( $html, 'woocommerce-product-gallery__image--placeholder' ) && false !== strpos( $html, 'Ver la ficha completa<span class="screen-reader-text"> de Taza</span>' ), 'La parte `quickview` de Productos usa las partes por defecto.' );
	$route::reset();
	$response = call_user_func( $endpoint['callback'], $request( array( 'widget_id' => 'glob123' ) + $base ) );
	$html = $response->data['html'];
	check_qv( false !== strpos( $html, 'digi-qv__thumbs' ) && 3 === substr_count( $html, 'class="digi-qv__thumb"' ) && false !== strpos( $html, 'aria-pressed="true"' ) && false !== strpos( $html, 'Ver imagen 2 de 3' ), 'Galería de miniaturas con `aria-pressed` y «Ver imagen N de M».' );
	check_qv( false !== strpos( $html, 'Algodón orgánico.' ), 'Descripción corta.' );

	// ---------------------------------------------------------------- «Plantilla de producto».
	$GLOBALS['qv_scripts'] = new QV_Deps();
	$GLOBALS['qv_styles']  = new QV_Deps();
	WC_Frontend_Scripts::$localized = array();
	$GLOBALS['wp_filter']['wp_footer'] = (object) array( 'callbacks' => array( 10 => array( 'previo' => array( 'function' => function () { echo '<div class="previo"></div>'; }, 'accepted_args' => 1 ) ) ) );
	$frontend = new class() {
		public $mode = 'ok';
		public function register_styles() { wp_register_style( 'widget-woocommerce-product-images', 'https://sitio.test/w.css', array(), '4.3.5', 'all' ); }
		public function register_scripts() { wp_register_script( 'elementor-frontend', 'https://sitio.test/f.js', array( 'jquery' ), '4.3.5' ); wp_register_script( 'solo-codigo', false ); wp_add_inline_script( 'solo-codigo', 'x()', 'after' ); }
		public function get_builder_content_for_display( $id, $css ) {
			global $product;
			wp_enqueue_style( 'widget-woocommerce-product-images' );
			wp_enqueue_script( 'wc-add-to-cart-variation' );
			wp_enqueue_script( 'elementor-frontend' );
			if ( 'raro' === $this->mode ) { wp_enqueue_script( 'solo-codigo' ); }
			add_action( 'wp_footer', function () { echo '<div class="nuevo"></div>'; } );
			ob_start();
			woocommerce_template_single_add_to_cart();
			return '<style>.elementor-' . $id . '{}</style><div class="elementor elementor-' . $id . '">' . ( is_product() ? 'ficha:' . $product->get_id() : '' ) . ob_get_clean() . '</div>';
		}
	};
	\Elementor\Plugin::$instance           = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->frontend = $frontend;
	$route::reset();
	$response = call_user_func( $endpoint['callback'], $request( array( 'widget_id' => 'tpl1234' ) + $base ) );
	$data = $response->data;
	check_qv( 'template' === $data['mode'] && false !== strpos( $data['html'], 'ficha:10' ), 'La plantilla se pinta con el producto como consulta principal.' );
	check_qv( false !== strpos( $data['html'], 'name="digi_qv"' ) && false !== strpos( $data['html'], 'action="https://sitio.test/tienda/?orden=precio"' ), 'También en la plantilla, el formulario vuelve a la página de origen.' );
	check_qv( array( 'photoswipe-default-skin', 'widget-woocommerce-product-images' ) === array_column( $data['assets']['styles'], 'handle' ), 'Hojas: las de la plantilla y las de una ficha; no las que ya estaban (V-I7): ' . wp_json_encode( array_column( $data['assets']['styles'], 'handle' ) ) );
	$handles = array_column( $data['assets']['scripts'], 'handle' );
	check_qv( in_array( 'wc-single-product', $handles, true ) && in_array( 'wc-add-to-cart-variation', $handles, true ) && ! in_array( 'woocommerce', $handles, true ), 'Scripts: los de la plantilla y los de una ficha, sin lo de toda página.' );
	$late = array_values( array_filter( $data['assets']['scripts'], function ( $s ) { return 'elementor-frontend' === $s['handle']; } ) );
	check_qv( isset( $late[0]['late'] ) && false === $late[0]['late'], 'El constructor no se carga tarde (`late: false`).' );
	check_qv( isset( $data['assets']['params']['wc_single_product_params'], $data['assets']['params']['wc_add_to_cart_variation_params'] ), 'Parámetros localizados de WooCommerce como objetos.' );
	check_qv( false !== strpos( $data['footer_html'], 'class="nuevo"' ) && false !== strpos( $data['footer_html'], 'class="pswp"' ) && false === strpos( $data['footer_html'], 'previo' ), '`footer_html`: sólo los *callbacks* añadidos durante el render (PhotoSwipe incluido).' );
	check_qv( false !== strpos( $data['templates'], 'tmpl-variation-template' ), 'Las plantillas de variaciones, desde la localización.' );
	$frontend->mode = 'raro';
	$GLOBALS['qv_scripts'] = new QV_Deps();
	$GLOBALS['qv_styles']  = new QV_Deps();
	WC_Frontend_Scripts::$localized = array();
	$route::reset();
	$response = call_user_func( $endpoint['callback'], $request( array( 'widget_id' => 'tpl1234' ) + $base ) );
	check_qv( array( 'fallback' => true ) === $response->data, 'Código en línea sin archivo: `fallback` (abre la ficha).' );
	$route::reset();
	$response = call_user_func( $endpoint['callback'], $request( array( 'widget_id' => 'bad1234' ) + $base ) );
	check_qv( 'own' === $response->data['mode'], 'Una plantilla que no es de producto vuelve a «Diseño propio» (nunca amplía nada).' );

	// ---------------------------------------------------------------- Botón y widget.
	check_qv( '' === $trigger::markup( 11, array( 'post' => 5, 'widget' => 'qv12345' ) ) && '' === $trigger::markup( 10, array( 'post' => 5, 'widget' => '<x>' ) ), 'Sin producto público o sin elemento válido, nada.' );
	$button = $trigger::markup( 10, array( 'post' => 5, 'widget' => 'qv12345', 'text' => '' ) );
	check_qv( '<a class="digi-qv__open no-prefetch" href="https://sitio.test/producto/camiseta/" data-digi-qv="10" data-post="5" data-widget="qv12345" aria-haspopup="dialog" data-variable><span class="digi-qv__text">Vista rápida</span><span class="screen-reader-text"> de Camiseta lisa</span></a>' === $button, 'Enlace a la ficha con lo que necesita el script, igual para todos y fuera de la carga especulativa de WordPress (`no-prefetch`): ' . $button );
	check_qv( false !== strpos( $trigger::markup( 12, array( 'post' => 5, 'widget' => 'qv12345', 'template' => true ) ), ' data-digi-qv-template>' ), 'El modo plantilla se marca en el botón.' );
	$widget_class = $qv . 'Widget';
	class_exists( $widget_class );
	check_qv( is_subclass_of( $widget_class, \Digitalisimo\Elements\Woo\Widget::class ) && \Digitalisimo\Elements\Woo\Inert::cooperates( $widget_class ), 'Contrato de Woo\Widget: sin WooCommerce, Inert usa la misma clase.' );
	$widget = ( new $widget_class() )->boot();
	check_qv( 'library_template' === $widget->controls['digitalisimo_template_id']['autocomplete']['object'] && 'product' === $widget->controls['digitalisimo_template_id']['autocomplete']['query']['meta_query'][0]['value'], 'Plantillas de «Producto individual» en `digitalisimo_template_id` (la puerta de PRO lo recorre).' );
	check_qv( false !== strpos( $widget->controls['template_cost']['raw'], '372 KB' ) && array( 'content_mode' => 'template' ) === $widget->controls['template_cost']['condition'], 'El editor declara el coste de PRO del modo plantilla.' );
	check_qv( array( '{{WRAPPER}}' => '--digi-qv-width: {{SIZE}}{{UNIT}};' ) === $widget->controls['dialog_width']['selectors'], 'El ancho es una variable en el widget (V-I9).' );
	check_qv( $render::DEFAULT_PARTS === $widget->controls['qv_parts']['default'] && 'own' === $widget->controls['content_mode']['default'], 'Los valores por defecto de los controles son los de la ruta.' );
	$GLOBALS['post'] = get_post( 10 );
	$GLOBALS['product'] = wc_get_product( 10 );
	\Elementor\Plugin::$instance->documents = new class() { public function get_current() { return new class() { public function get_main_id() { return 88; } }; } };
	check_qv( false !== strpos( $widget->html(), 'data-post="88" data-widget="qv12345"' ), 'El botón lleva el documento que contiene el widget (en un bucle, su plantilla).' );
	unset( $GLOBALS['post'], $GLOBALS['product'] );

	// ---------------------------------------------------------------- Compra: redirección tras el POST.
	$_SERVER['HTTP_HOST'] = 'sitio.test';
	$_SERVER['REQUEST_URI'] = '/tienda/?orden=precio&add-to-cart=10';
	$module = $qv . 'Module';
	check_qv( 'https://sitio.test/tienda/?orden=precio' === $module::redirect( false ), 'Tras el POST, a la página de origen: recargar no repite la compra.' );
	check_qv( '/otro/' === $module::redirect( '/otro/' ), 'Si otro filtro ya decidió, no se toca.' );
	$GLOBALS['qv_options']['woocommerce_cart_redirect_after_add'] = 'yes';
	check_qv( false === $module::redirect( false ), 'Si la tienda redirige al carrito, manda la tienda.' );
	$GLOBALS['qv_options'] = array();
	$_SERVER['HTTP_HOST'] = 'otro.test';
	check_qv( false === $module::redirect( false ), 'Nunca a otro sitio.' );
	check_qv( ! has_filter( 'woocommerce_add_to_cart_redirect' ), 'Sin `digi_qv=1`, la redirección no se engancha.' );

	// ---------------------------------------------------------------- Recursos y revisión estática.
	foreach ( array( 'assets/css/quick-view.css' => 4096, 'assets/js/quick-view.js' => 8192 ) as $file => $cap ) {
		$source = file_get_contents( $root . '/' . $file );
		check_qv( strlen( $source ) <= $cap, $file . ' pesa ' . strlen( $source ) . ' B (tope ' . $cap . ').' );
		check_qv( false === strpos( $source, '--e-global-' ), $file . ': sin la paleta del sitio.' );
	}
	check_qv( false !== strpos( file_get_contents( $root . '/assets/css/quick-view.css' ), 'prefers-reduced-motion' ), 'La aparición respeta el movimiento reducido.' );
	foreach ( glob( $root . '/modules/digitalisimo-woo/quick-view/*.php' ) as $file ) {
		$code = '';
		foreach ( token_get_all( file_get_contents( $file ) ) as $token ) {
			if ( ! is_array( $token ) || ! in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { $code .= is_array( $token ) ? $token[1] : $token; }
		}
		check_qv( false === strpos( $code, 'Automattic\\WooCommerce\\Internal' ) && false === strpos( $code, 'Automattic\\\\WooCommerce\\\\Internal' ), basename( $file ) . ': ninguna clase interna de WooCommerce.' );
		check_qv( ! preg_match( '/<img[\s>]/', $code ) && ! preg_match( '/<script(?![^\'"]*type="text\/template")/', $code ), basename( $file ) . ': sin <img> ni <script> a mano.' );
		check_qv( false === strpos( $code, 'rest_send_nocache_headers' ), basename( $file ) . ': nunca el filtro global de cabeceras.' );
	}
	echo "DIGITALÍSIMO Elements: vista rápida (F4-Woo, frente C) validada.\n";
}
