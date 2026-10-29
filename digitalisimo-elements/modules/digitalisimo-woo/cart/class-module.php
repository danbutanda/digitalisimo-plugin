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
		add_filter( 'woocommerce_cart_hash', array( __CLASS__, 'cart_hash' ) );
	}

	/**
	 * `woocommerce_cart_hash`: el *hash* con el que `cart-fragments.js` decide si reutiliza los fragmentos guardados en
	 * `sessionStorage` o los vuelve a pedir. WooCommerce lo calcula con el contenido y el total del carrito, y el estado de
	 * la barra depende además del destino (V.7.1.2: sin destino conocido no se muestra nada). Dar la dirección en la
	 * calculadora del carrito o en el pago no cambia el total cuando la ubicación por defecto ya tenía la misma tarifa, así
	 * que, sin esto, el mini carrito de las demás páginas seguía mostrando los fragmentos de antes (sin barra) hasta el
	 * siguiente cambio del carrito (W11). Sólo con la bandera activa, y sólo si hay hash (un carrito vacío no tiene).
	 *
	 * @param mixed $hash Hash de WooCommerce.
	 * @return mixed
	 */
	public static function cart_hash( $hash ) {
		if ( ! is_string( $hash ) || '' === $hash || ! Flags::uses_fragments() || ! function_exists( 'WC' ) ) {
			return $hash;
		}
		$customer = WC()->customer ?? null;
		if ( ! is_object( $customer ) ) {
			return $hash;
		}
		$parts = array( method_exists( $customer, 'has_calculated_shipping' ) && $customer->has_calculated_shipping() ? '1' : '0' );
		foreach ( array( 'get_shipping_country', 'get_shipping_state', 'get_shipping_postcode', 'get_shipping_city' ) as $getter ) {
			$parts[] = method_exists( $customer, $getter ) ? (string) $customer->$getter() : '';
		}
		return md5( $hash . '|digi|' . implode( '|', $parts ) );
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
