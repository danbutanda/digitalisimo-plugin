<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Carrito de la cotización de prueba de Skydropx: sólo vive mientras el método del plugin cotiza (`Skydropx::quote()`
 * lo pone en `WC()->cart` y devuelve el que había).
 *
 * El plugin lee los artículos con `get_cart()` y el importe con `Helper::get_cart_total()`, que exige un `WC_Cart`. El
 * constructor no llama al de WooCommerce (que engancha la sesión, el carrito persistente del usuario y los recálculos):
 * pone sus líneas y sus totales con los setters públicos, y una sesión de carrito que no carga nada, así que
 * `get_cart()` devuelve estas líneas sin leer ni escribir el carrito guardado de nadie. No sobrescribe ningún método.
 */
final class Skydropx_Cart extends \WC_Cart {
	/**
	 * @param array $lines    Líneas con la forma de WooCommerce (`data`, `quantity`, `product_id`, `variation_id`…).
	 * @param float $subtotal Importe sin impuestos.
	 * @param float $tax      Impuestos (sólo cuentan si la tienda muestra los precios con impuestos en el carrito).
	 */
	public function __construct( array $lines, float $subtotal, float $tax ) {
		$this->cart_contents = $lines;
		$this->session       = new class() {
			/** El carrito real se cargaría de la sesión aquí; éste ya tiene sus líneas. */
			public function get_cart_from_session() {}
		};
		$this->set_cart_contents_total( $subtotal );
		$this->set_cart_contents_tax( $tax );
	}
}
