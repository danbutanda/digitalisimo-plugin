<?php
namespace Digitalisimo\Elements\Woo\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Estado del carrito para la barra de envío gratis y el cupón (F4-Woo V.7.1), una vez por petición de fragmentos.
 *
 * Sólo con las tarifas reales que WooCommerce calcula para el paquete. Nunca se deduce que hay envío gratis ni se
 * promete un umbral que WooCommerce no aplicaría: **si no se puede saber, `none`** y no se muestra nada.
 *
 * - `done`: las tarifas del único paquete traen una de `free_shipping` (lo que WooCommerce ofrecerá).
 * - `progress` («Te faltan X»): sin `done`, con destino conocido, un solo paquete, todos los métodos «Envío gratuito»
 *   activos de la zona con `requires = min_amount` y un `min_amount` numérico, y nadie enganchado a
 *   `woocommerce_shipping_free_shipping_is_available` ni a `woocommerce_package_rates` (un tercero podría negar el envío
 *   gratis). X = `min_amount` de la instancia ya construida (filtrado por `…_instance_option`, que es lo que convierten
 *   los plugins de divisas) menos el total con la regla de `is_available()`; X > 0; con varios métodos, el menor X.
 * - `none`: todo lo demás (sin carrito, vacío, sin envío, sin cálculo posible, destino desconocido, varios paquetes…).
 */
final class State {
	const FREE = 'free_shipping';

	/** @var array|null Estado de esta petición. */
	private static $memo = null;

	/** @var array<int, array> Métodos activos por zona, por petición (`get_shipping_methods()` consulta la base, W-2). */
	private static $methods = array();

	/**
	 * @return array{state: string, remaining_html: string, threshold_html: string, percent: int, coupons: string[], coupons_enabled: bool}
	 */
	public static function compute(): array {
		if ( null !== self::$memo ) {
			return self::$memo;
		}
		$state = self::blank();
		$cart  = self::cart();
		if ( $cart ) {
			$state['coupons_enabled'] = function_exists( 'wc_coupons_enabled' ) && wc_coupons_enabled() && ! $cart->is_empty();
			if ( $state['coupons_enabled'] ) {
				$state['coupons'] = array_values( array_filter( array_map( 'strval', (array) $cart->get_applied_coupons() ), 'strlen' ) );
			}
			$state = array_merge( $state, self::shipping( $cart ) );
		}
		self::$memo = $state;
		return $state;
	}

	/** Para las pruebas y tras cambiar el carrito en la misma petición. */
	public static function reset(): void {
		self::$memo    = null;
		self::$methods = array();
	}

	private static function blank(): array {
		return array(
			'state'           => 'none',
			'remaining_html'  => '',
			'threshold_html'  => '',
			'percent'         => 0,
			'coupons'         => array(),
			'coupons_enabled' => false,
		);
	}

	private static function cart() {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}
		$wc = WC();
		return is_object( $wc ) && isset( $wc->cart ) && is_object( $wc->cart ) ? $wc->cart : null;
	}

	/** @return array{state: string, remaining_html?: string, threshold_html?: string, percent?: int} */
	private static function shipping( $cart ): array {
		$none = array( 'state' => 'none' );
		// 1. Carrito vacío, sin envío o sin cálculo posible (respeta «calcular el envío sólo con dirección»).
		if ( $cart->is_empty() || ! $cart->needs_shipping() || ! $cart->show_shipping() ) {
			return $none;
		}
		// 2. Destino conocido: el cliente usó la calculadora o el pago, o su cuenta guarda un país de envío. La ubicación
		// por defecto o la geolocalización no cuentan: podrían ser otra zona.
		if ( ! self::destination_known() ) {
			return $none;
		}
		// 3. Un solo paquete, con sus tarifas (reutilizadas de la sesión por hash; ya pasaron por woocommerce_package_rates).
		$shipping = WC()->shipping();
		$shipping->calculate_shipping( $cart->get_shipping_packages() );
		$packages = array_values( array_filter( (array) $shipping->get_packages(), 'is_array' ) );
		if ( 1 !== count( $packages ) ) {
			return $none;
		}
		$package = $packages[0];
		// 4. Envío gratis: sólo si WooCommerce ya lo ofrece.
		foreach ( (array) ( $package['rates'] ?? array() ) as $rate ) {
			if ( self::FREE === self::rate_method( $rate ) ) {
				return array( 'state' => 'done', 'percent' => 100 );
			}
		}
		// 5. Cuánto falta, con las condiciones estrictas de V.7.1.
		return self::progress( $cart, $package );
	}

	private static function destination_known(): bool {
		$customer = WC()->customer;
		if ( is_object( $customer ) && method_exists( $customer, 'has_calculated_shipping' ) && $customer->has_calculated_shipping() ) {
			return true;
		}
		$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		return $user > 0 && '' !== trim( (string) get_user_meta( $user, 'shipping_country', true ) );
	}

	private static function progress( $cart, array $package ): array {
		$none = array( 'state' => 'none' );
		if ( has_filter( 'woocommerce_shipping_free_shipping_is_available' ) || has_filter( 'woocommerce_package_rates' ) ) {
			return $none;
		}
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $none;
		}
		$zone    = \WC_Shipping_Zones::get_zone_matching_package( $package );
		$zone_id = (int) $zone->get_id();
		if ( ! isset( self::$methods[ $zone_id ] ) ) {
			self::$methods[ $zone_id ] = (array) $zone->get_shipping_methods( true );
		}
		$best = null;
		foreach ( self::$methods[ $zone_id ] as $method ) {
			if ( ! is_object( $method ) || self::FREE !== ( $method->id ?? '' ) ) {
				continue;
			}
			// Con «o cupón», «y cupón», «sólo cupón» o sin requisito no se puede decir cuánto falta.
			if ( ! is_numeric( $method->min_amount ?? null ) ) {
				return $none;
			}
			$min       = (float) $method->min_amount;
			$total     = self::eligible_total( $cart, (string) ( $method->ignore_discounts ?? 'no' ) );
			$remaining = round( $min - $total, (int) wc_get_price_decimals() );
			if ( null === $best || $remaining < $best['remaining'] ) {
				$best = array( 'remaining' => $remaining, 'min' => $min, 'total' => $total );
			}
		}
		if ( null === $best || $best['remaining'] <= 0 || $best['min'] <= 0 ) {
			return $none; // Sin método, o WooCommerce debería ofrecerlo ya y no lo hace: no se promete nada.
		}
		return array(
			'state'          => 'progress',
			'remaining_html' => (string) wc_price( $best['remaining'] ),
			'threshold_html' => (string) wc_price( $best['min'] ),
			'percent'        => (int) max( 0, min( 99, floor( $best['total'] / $best['min'] * 100 ) ) ),
		);
	}

	/**
	 * El total con la regla de `WC_Shipping_Free_Shipping::is_available()`: subtotal mostrado, menos los descuentos (y su
	 * impuesto si los precios se muestran con impuestos) salvo «aplicar el mínimo antes del cupón», redondeado.
	 */
	public static function eligible_total( $cart, string $ignore_discounts ): float {
		$total = (float) $cart->get_displayed_subtotal();
		if ( 'no' === $ignore_discounts ) {
			$total -= (float) $cart->get_discount_total();
			if ( $cart->display_prices_including_tax() ) {
				$total -= (float) $cart->get_discount_tax();
			}
		}
		return round( $total, (int) wc_get_price_decimals() );
	}

	private static function rate_method( $rate ): string {
		if ( is_object( $rate ) && method_exists( $rate, 'get_method_id' ) ) {
			return (string) $rate->get_method_id();
		}
		return is_object( $rate ) && isset( $rate->method_id ) ? (string) $rate->method_id : '';
	}
}
