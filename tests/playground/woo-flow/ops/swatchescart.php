<?php
/**
 * Operaciones de sonda del grupo `swatchescart` de woo-flow.sh (W6–W8). `?digi_wf=<op>&token=…` (con parámetros
 * propios en la consulta), en una petición del sitio, como el usuario 1.
 *
 * - `swatchescart_set&case=<caso>`: deja el sitio en un caso de W7/W8 y antes restablece todos los demás (`case=` vacío
 *   los restablece). Casos: `rates`, `available`, `instance`, `packages` (los activa el mu-plugin), `requires_empty`,
 *   `requires_coupon` (el «Envío gratuito» de la zona), `nocountry` (sin ubicación por defecto del cliente),
 *   `coupons_off`, `clock` y `nonce_fail` (W8). Invalida las tarifas guardadas en las sesiones.
 * - `swatchescart_select&product=<id>&attribute=<pa_*>`: el desplegable de WooCommerce de ese atributo, con los
 *   filtros de Elements y sin el de las muestras (lo que imprime 4.3.0.165, que no engancha ese filtro), con los mismos
 *   argumentos que la plantilla `variable.php`.
 * - `swatchescart_state`: el estado que lee el escenario (casos, opciones y «Envío gratuito» de la zona).
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'digi_wf_sc_free_method' ) ) {
	/** Instancia y ajustes del «Envío gratuito» de la zona «España». */
	function digi_wf_sc_free_method() {
		$zone = WC_Shipping_Zones::get_zone( (int) get_option( 'digi_wf_zone' ) );
		foreach ( $zone ? $zone->get_shipping_methods() : array() as $method ) {
			if ( 'free_shipping' === $method->id ) {
				$id = (int) $method->get_instance_id();
				return array( $id, (array) get_option( 'woocommerce_free_shipping_' . $id . '_settings', array() ) );
			}
		}
		return array( 0, array() );
	}

	function digi_wf_sc_state() {
		list( $id, $settings ) = digi_wf_sc_free_method();
		return array(
			'case'             => (string) get_option( 'digi_wf_sc_case', '' ),
			'clock'            => (bool) get_option( 'digi_wf_sc_clock' ),
			'nonce_fail'       => (bool) get_option( 'digi_wf_sc_nonce_fail' ),
			'coupons'          => get_option( 'woocommerce_enable_coupons' ),
			'customer_address' => get_option( 'woocommerce_default_customer_address' ),
			'free'             => array( 'instance' => $id, 'requires' => $settings['requires'] ?? null, 'min_amount' => $settings['min_amount'] ?? null ),
			'mu'               => file_exists( WPMU_PLUGIN_DIR . '/digi-wf-swatchescart.php' ),
		);
	}
}

return array(
	'swatchescart_set'    => static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sonda de Playground protegida por token.
		$case  = isset( $_GET['case'] ) ? sanitize_key( wp_unslash( $_GET['case'] ) ) : '';
		$known = array( '', 'rates', 'available', 'instance', 'packages', 'requires_empty', 'requires_coupon', 'nocountry', 'coupons_off', 'clock', 'nonce_fail' );
		if ( ! in_array( $case, $known, true ) ) {
			return array( 'error' => 'caso desconocido: ' . $case );
		}
		// Todo a su valor de la tienda.
		delete_option( 'digi_wf_sc_case' );
		delete_option( 'digi_wf_sc_clock' );
		delete_option( 'digi_wf_sc_nonce_fail' );
		update_option( 'woocommerce_enable_coupons', 'yes' );
		update_option( 'woocommerce_default_customer_address', 'base' );
		list( $id, $settings ) = digi_wf_sc_free_method();
		$settings['requires']   = 'min_amount';
		$settings['min_amount'] = '50';
		// Y el caso pedido.
		if ( in_array( $case, array( 'rates', 'available', 'instance', 'packages' ), true ) ) {
			update_option( 'digi_wf_sc_case', $case );
		} elseif ( 'requires_empty' === $case ) {
			$settings['requires'] = '';
		} elseif ( 'requires_coupon' === $case ) {
			$settings['requires'] = 'coupon';
		} elseif ( 'nocountry' === $case ) {
			update_option( 'woocommerce_default_customer_address', '' );
		} elseif ( 'coupons_off' === $case ) {
			update_option( 'woocommerce_enable_coupons', 'no' );
		} elseif ( 'clock' === $case ) {
			update_option( 'digi_wf_sc_clock', 1 );
		} elseif ( 'nonce_fail' === $case ) {
			update_option( 'digi_wf_sc_nonce_fail', 1 );
		}
		if ( $id ) {
			update_option( 'woocommerce_free_shipping_' . $id . '_settings', $settings );
		}
		// Las tarifas guardadas en la sesión llevan la versión de la caché de envío en su hash.
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		return digi_wf_sc_state();
	},
	'swatchescart_state'  => static function () {
		return digi_wf_sc_state();
	},
	'swatchescart_select' => static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sonda de Playground protegida por token.
		$product   = isset( $_GET['product'] ) ? wc_get_product( absint( $_GET['product'] ) ) : null;
		$attribute = isset( $_GET['attribute'] ) ? sanitize_title( wp_unslash( $_GET['attribute'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! $product instanceof WC_Product_Variable || '' === $attribute ) {
			return array( 'error' => 'producto o atributo' );
		}
		$attributes = $product->get_variation_attributes();
		$args       = array( 'options' => $attributes[ $attribute ] ?? array(), 'attribute' => $attribute, 'product' => $product );
		$render     = static function () use ( $args ) {
			ob_start();
			wc_dropdown_variation_attribute_options( $args );
			return (string) ob_get_clean();
		};
		// Sin barra inicial: el identificador del gancho es el de `__CLASS__` (con ella, has_filter() no lo encuentra).
		$filter   = array( 'Digitalisimo\\Elements\\Woo\\Swatches\\Form', 'filter' );
		$hooked   = is_callable( $filter ) ? has_filter( 'woocommerce_dropdown_variation_attribute_options_html', $filter ) : false;
		$with     = $render();
		if ( false !== $hooked ) {
			remove_filter( 'woocommerce_dropdown_variation_attribute_options_html', $filter, $hooked );
		}
		$without = $render();
		if ( false !== $hooked ) {
			add_filter( 'woocommerce_dropdown_variation_attribute_options_html', $filter, $hooked, 2 );
		}
		return array( 'hooked' => $hooked, 'with' => $with, 'without' => $without );
	},
);
