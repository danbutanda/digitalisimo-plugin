<?php
namespace Digitalisimo\Elements\Woo\Cart;

use Digitalisimo\Elements\Woo\Flags;
use Digitalisimo\Elements\Woo\Module as Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Extras del carrito de F4-Woo (frente E): el widget «Barra de envío gratis», el rasgo `Mini_Cart_Extras` del Menú del
 * carrito y sus fragmentos. La arranca `Woo\Module` con WooCommerce; la hoja y el script (capacidad `cart-extras`, que
 * depende de `wc-cart-fragments`) los registra `Woo\Assets` y el widget, `Woo\Registry`.
 *
 * Los fragmentos sólo se calculan si la bandera `digitalisimo_elements_woo_fragments` dice que algún documento publicado
 * usa la barra o el cupón (V.12.4): sin ella, el filtro devuelve lo recibido sin tocar el carrito. Un sitio cuyo plugin
 * de rendimiento quita `wc-cart-fragments` nunca verá la barra ni el cupón: falla cerrado (R-M15).
 */
final class Module {
	const FEATURE = 'cart-extras';

	/** Lo que usa de WooCommerce, comprobado al arrancar (D3). */
	const SYMBOLS = array( 'WC_Cart::get_displayed_subtotal', 'WC_Cart::show_shipping', 'WC_Customer::has_calculated_shipping', 'WC_Shipping_Zones::get_zone_matching_package', 'wc_coupons_enabled()', 'wc_price()' );

	private static $booted = false;

	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		foreach ( array( 'state', 'markup', 'mini-cart-extras' ) as $file ) {
			require_once __DIR__ . '/class-' . $file . '.php';
		}
		if ( ! Woo::requires( self::FEATURE, self::SYMBOLS ) ) {
			return;
		}
		Mini_Cart_Extras::init();
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ) );
	}

	/**
	 * `woocommerce_add_to_cart_fragments`: los dos nodos de estado, iguales para cualquier página (el refresco no sabe
	 * cuál lo pide). Sólo con la bandera activa.
	 *
	 * @param mixed $fragments Fragmentos de WooCommerce.
	 * @return mixed
	 */
	public static function fragments( $fragments ) {
		if ( ! is_array( $fragments ) || ! Flags::uses_fragments() ) {
			return $fragments;
		}
		$state                               = State::compute();
		$fragments['div.digi-ship__state']   = Markup::ship_state( $state );
		$fragments['div.digi-coupon__state'] = Markup::coupon_state( $state );
		return $fragments;
	}
}
