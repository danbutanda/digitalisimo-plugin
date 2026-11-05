<?php
namespace Digitalisimo\Elements\Woo\Quick_View;

use Digitalisimo\Elements\Woo\Products;
use Digitalisimo\Elements\Woo\Rest;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-render.php';
require_once __DIR__ . '/class-template.php';

/**
 * Ruta `GET digitalisimo-elements/v1/woo/quick-view` (V.5.1 y V.4.3).
 *
 * 1. **Permiso por predicado** (`Rest::element()`): el documento `post_id` (publicado, o editable por el usuario) tiene
 *    el elemento `widget_id` y es un `digitalisimo-woo-quick-view` o un `woocommerce-products` con «Elegir qué partes
 *    mostrar» y la parte `quickview`. Un `global` se sigue una vez a su plantilla. Los ajustes salen del documento,
 *    nunca de la petición. Carrito de WooCommerce cargado y cabeceras sin caché: `Woo\Rest`.
 * 2. **Producto público** (`Products::is_public()`) o 404 sin detalle.
 * 3. **Render** del modo elegido; una plantilla que ya no es válida vuelve a «Diseño propio».
 * 4. **Respuesta:** `{ mode, html, title, product, variable, assets: { styles, scripts, params }, templates,
 *    gallery_defaults, footer_html }`, o `{ fallback: true }` (el navegador abre la ficha).
 *
 * `return` (la página de origen) sólo se usa como acción del formulario, validada con `wp_validate_redirect()`.
 */
final class Route {
	const PATH = 'quick-view';

	/** @var array<string,array|null> Elemento hallado por petición. */
	private static $elements = array();

	public static function register(): void {
		Rest::route(
			self::PATH,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'respond' ),
				'permission_callback' => array( __CLASS__, 'permission' ),
				'digitalisimo_cart'   => true,
				'args'                => array(
					'post_id'   => array( 'required' => true, 'validate_callback' => array( __CLASS__, 'positive' ), 'sanitize_callback' => 'absint' ),
					'widget_id' => array( 'required' => true, 'validate_callback' => array( __CLASS__, 'element_id' ) ),
					'product'   => array( 'required' => true, 'validate_callback' => array( __CLASS__, 'positive' ), 'sanitize_callback' => 'absint' ),
					'return'    => array( 'required' => false, 'validate_callback' => array( __CLASS__, 'short_string' ) ),
				),
			)
		);
	}

	/** Entero positivo. */
	public static function positive( $value ): bool {
		return ( is_int( $value ) && $value > 0 ) || ( is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]{0,18}$/', $value ) );
	}

	/** ID de elemento con la forma de `Rest::ID_PATTERN`. */
	public static function element_id( $value ): bool {
		return is_string( $value ) && 1 === preg_match( Rest::ID_PATTERN, $value );
	}

	/** Cadena de hasta 2 KB. */
	public static function short_string( $value ): bool {
		return is_string( $value ) && strlen( $value ) <= 2048;
	}

	/**
	 * Elementos que pueden pedir una vista rápida.
	 *
	 * @param array $element Elemento hallado en el documento.
	 */
	public static function predicate( array $element ): bool {
		$type = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '';
		if ( 'digitalisimo-woo-quick-view' === $type ) {
			return true;
		}
		if ( 'woocommerce-products' !== $type ) {
			return false;
		}
		$s = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		return 'yes' === ( isset( $s['digitalisimo_wc_custom'] ) ? $s['digitalisimo_wc_custom'] : '' )
			&& isset( $s['digitalisimo_wc_parts'] ) && is_array( $s['digitalisimo_wc_parts'] ) && in_array( 'quickview', $s['digitalisimo_wc_parts'], true );
	}

	/** `permission_callback`: el elemento existe y cumple el predicado. */
	public static function permission( $request ) {
		return null !== self::element( $request ) ? true : self::error( 'digitalisimo_quick_view_forbidden', 'No permitido.', 403 );
	}

	/** @return array|null */
	private static function element( $request ): ?array {
		$post   = (int) $request['post_id'];
		$widget = (string) $request['widget_id'];
		$key    = $post . '|' . $widget;
		if ( ! array_key_exists( $key, self::$elements ) ) {
			self::$elements[ $key ] = Rest::element( $post, $widget, array( __CLASS__, 'predicate' ) );
		}
		return self::$elements[ $key ];
	}

	/** Callback de la ruta. */
	public static function respond( $request ) {
		$element = self::element( $request );
		if ( null === $element ) {
			return self::error( 'digitalisimo_quick_view_forbidden', 'No permitido.', 403 );
		}
		$id      = (int) $request['product'];
		$product = Products::is_public( $id ) && function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( ! $product instanceof \WC_Product || $product->is_type( 'variation' ) ) {
			return self::error( 'digitalisimo_quick_view_not_found', 'No encontrado.', 404 );
		}
		$return   = self::return_url( (string) $request['return'], $product );
		$settings = Render::settings( $element );
		$data     = null;
		if ( 'template' === $settings['mode'] && $settings['template'] > 0 ) {
			$data = Template::respond( $product, $settings['template'], $return );
		}
		if ( null === $data ) {
			$data = Render::respond( $product, $settings, $return );
		}
		return class_exists( '\\WP_REST_Response' ) ? new \WP_REST_Response( $data, 200 ) : $data;
	}

	/**
	 * Página de origen para la acción del formulario: una URL de este sitio sin fragmento, o la ficha del producto.
	 *
	 * @param string      $raw     Valor recibido.
	 * @param \WC_Product $product Producto.
	 */
	public static function return_url( string $raw, \WC_Product $product ): string {
		$raw  = trim( $raw );
		$hash = strpos( $raw, '#' );
		if ( false !== $hash ) {
			$raw = substr( $raw, 0, $hash );
		}
		$safe = '' !== $raw ? wp_validate_redirect( esc_url_raw( $raw ), '' ) : '';
		return '' !== $safe ? $safe : (string) $product->get_permalink();
	}

	private static function error( string $code, string $message, int $status ) {
		return class_exists( '\\WP_Error' ) ? new \WP_Error( $code, $message, array( 'status' => $status ) ) : false;
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$elements = array();
	}
}
