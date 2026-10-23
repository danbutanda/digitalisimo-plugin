<?php
namespace Digitalisimo\Elements\Woo\Quick_View;

use Digitalisimo\Elements\Woo\Assets;
use Digitalisimo\Elements\Woo\Module as Woo;
use Digitalisimo\Elements\Woo\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Vista rápida de F4-Woo (frente C, V.5 de la especificación).
 *
 * - **Botón** (`Widget` y `Trigger::markup()`, que usa también la parte `quickview` de Productos): un enlace a la ficha
 *   que, con JS y `<dialog>`, abre un diálogo modal nativo. Sin JS, con teclas de modificación o sin
 *   `HTMLDialogElement`, abre la ficha.
 * - **Ruta** `woo/quick-view` (`Route`): permiso por predicado sobre el documento, carrito de WooCommerce cargado y
 *   cabeceras sin caché (las pone `Woo\Rest`).
 * - **Variables** (`Variation`): la configuración de `wc-add-to-cart-variation` se calcula en el pie de la página y las
 *   plantillas `tmpl-variation-*` viajan en la respuesta.
 * - **Plantilla de producto** (`Template`): CSS y scripts de los widgets de la plantilla, y los paquetes de PRO por la
 *   puerta de PRO (coste declarado).
 * - **Compra** (V.5.5): con el campo `digi_qv=1`, tras el `POST` hay una redirección a la página de origen.
 */
final class Module {
	const FEATURE = 'quick-view';

	/** Lo que usa de WooCommerce sin detección propia (D3). */
	const SYMBOLS = array( 'wc_get_product()', 'woocommerce_template_single_add_to_cart()', 'wc_get_template()', 'WC_Product' );

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		if ( ! Woo::requires( self::FEATURE, self::SYMBOLS ) ) {
			return;
		}
		foreach ( array( 'asset-list', 'trigger', 'variation', 'render', 'template', 'route' ) as $file ) {
			require_once __DIR__ . '/class-' . $file . '.php';
		}
		Route::register();
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'footer_config' ), 6 );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- sólo decide si se engancha la redirección; WooCommerce valida la compra.
		if ( ! empty( $_POST['digi_qv'] ) && '1' === $_POST['digi_qv'] ) {
			add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect' ), 20, 2 );
		}
	}

	public static function handle(): string {
		return Assets::handle( self::FEATURE );
	}

	/**
	 * Configuración del navegador, en el pie y sólo si el script está encolado: la ruta y, si la página no trae el
	 * script de variaciones, lo que hace falta para cargarlo al abrir un variable (V.5.3). Es igual para todos los
	 * visitantes: no rompe la caché de página.
	 */
	public static function footer_config(): void {
		if ( ! function_exists( 'wp_script_is' ) || ! wp_script_is( self::handle(), 'enqueued' ) ) {
			return;
		}
		$config    = array( 'rest' => Rest::url( Route::PATH ) );
		$variation = Variation::footer_config();
		if ( null !== $variation ) {
			$config['variation'] = $variation;
		}
		wp_add_inline_script( self::handle(), 'window.digitalisimoQuickView=' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * `woocommerce_add_to_cart_redirect`, sólo con `digi_qv=1` en el `POST`: vuelve a la página de origen (la acción del
	 * formulario), así recargar no repite la compra. Si la tienda redirige al carrito, o otro filtro ya decidió, no se
	 * toca. Los avisos de WooCommerce se ven donde la página los imprima (Menú del carrito, carrito).
	 *
	 * @param mixed $url     Destino.
	 * @param mixed $product Producto añadido.
	 */
	public static function redirect( $url, $product = null ) {
		if ( ! empty( $url ) || 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) || empty( $_SERVER['HTTP_HOST'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return $url;
		}
		$here = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$here = remove_query_arg( array( 'add-to-cart', 'quantity', 'variation_id', 'digi_qv' ), $here );
		$safe = wp_validate_redirect( $here, '' );
		return '' !== $safe ? $safe : $url;
	}
}
