<?php
/**
 * Plugin Name: Sonda de woo-flow (sólo Playground)
 * Description: mu-plugin de tests/playground/woo-flow.sh. Nunca forma parte de un plugin.
 *
 * Controlador `?digi_wf=<op>&token=<token>` (el token sale de probe/mode.json, que escribe el arnés), en `wp_loaded`
 * del sitio de la petición, con WooCommerce ya cargado:
 * - `ping`: versiones cargadas (WordPress, WooCommerce, constructor, DIGITALÍSIMO Elements), tema y plugins activos;
 * - `setup`: prepara la tienda de este sitio (`store.php`, idempotente) y devuelve lo creado;
 * - `state`: ajustes de la tienda, zonas y métodos, cupones, pasarelas disponibles y la tabla de atributos;
 * - `orders`: los pedidos del sitio, del más reciente al más antiguo (estado, pago, envío, cupones y total).
 * Las peticiones con token corren como el usuario 1 (el administrador que prepara la tienda). Además da por enviados
 * los correos (`pre_wp_mail`): el pedido de prueba no sale del Playground.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'pre_wp_mail', '__return_true' );

/** Modo de la corrida (lo escribe woo-flow.sh; el blueprint no recibe variables de entorno). */
function digi_wf_probe_mode() {
	static $mode = null;
	if ( null === $mode ) {
		$raw  = @file_get_contents( WP_CONTENT_DIR . '/probe/mode.json' ); // phpcs:ignore
		$mode = is_string( $raw ) ? json_decode( $raw, true ) : null;
		$mode = is_array( $mode ) ? $mode : array();
	}
	return $mode;
}

// Las peticiones de la sonda las hace el administrador que prepara la tienda (usuario 1, superadministrador en la red):
// en un sitio nuevo de la red, la instalación perezosa de WooCommerce corre en `init` de su primera petición y escribe
// los ajustes del kit, lo que sólo puede hacer alguien con permiso (como visitante, el núcleo lanza «Access denied»).
add_action(
	'plugins_loaded',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sonda de Playground protegida por token.
		$mode = digi_wf_probe_mode();
		if ( isset( $_GET['digi_wf'], $_GET['token'] ) && ! empty( $mode['token'] ) && hash_equals( (string) $mode['token'], (string) wp_unslash( $_GET['token'] ) ) ) {
			wp_set_current_user( 1 );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	},
	0
);

add_action(
	'wp_loaded',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sonda de Playground protegida por token.
		if ( ! isset( $_GET['digi_wf'] ) ) {
			return;
		}
		$mode  = digi_wf_probe_mode();
		$token = isset( $_GET['token'] ) ? (string) wp_unslash( $_GET['token'] ) : '';
		if ( empty( $mode['token'] ) || ! hash_equals( (string) $mode['token'], $token ) ) {
			wp_send_json( array( 'error' => 'token' ), 403 );
		}
		$op = sanitize_key( wp_unslash( $_GET['digi_wf'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'ping' === $op ) {
			wp_send_json( digi_wf_probe_ping() );
		}
		if ( 'setup' === $op ) {
			require_once WP_CONTENT_DIR . '/probe/store.php';
			wp_send_json( digi_wf_store_setup() );
		}
		if ( 'state' === $op ) {
			wp_send_json( digi_wf_probe_state() );
		}
		if ( 'orders' === $op ) {
			wp_send_json( digi_wf_probe_orders() );
		}
		wp_send_json( array( 'error' => 'desconocido' ), 404 );
	},
	1
);

function digi_wf_probe_ping() {
	$theme = wp_get_theme();
	return array(
		'ready'       => (int) get_option( 'digi_wf_activated' ) > 0,
		'blog'        => get_current_blog_id(),
		'home'        => home_url( '/' ),
		'multisite'   => is_multisite(),
		'wordpress'   => get_bloginfo( 'version' ),
		'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : null,
		'wc_db'       => get_option( 'woocommerce_db_version' ),
		'elementor'   => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
		'elements'    => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null,
		'bundled'     => class_exists( 'Digitalisimo_Elements_Core' ) && method_exists( 'Digitalisimo_Elements_Core', 'bundled' ) && Digitalisimo_Elements_Core::bundled(),
		'theme'       => $theme->get_stylesheet(),
		'plugins'     => array_values( array_merge( (array) get_option( 'active_plugins', array() ), is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array() ) ),
		'store'       => (bool) get_option( 'digi_wf_store' ),
	);
}

function digi_wf_probe_state() {
	global $wpdb;
	$state = array( 'blog' => get_current_blog_id(), 'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : null );
	if ( ! function_exists( 'WC' ) ) {
		return $state;
	}
	foreach ( array( 'woocommerce_currency', 'woocommerce_default_country', 'woocommerce_calc_taxes', 'woocommerce_enable_coupons', 'woocommerce_coming_soon', 'woocommerce_attribute_lookup_enabled', 'woocommerce_attribute_lookup_direct_updates', 'woocommerce_hide_out_of_stock_items', 'woocommerce_checkout_page_id', 'woocommerce_cart_page_id' ) as $option ) {
		$state['options'][ $option ] = get_option( $option );
	}
	$state['gateways'] = array();
	foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
		$state['gateways'][ $id ] = 'yes' === $gateway->enabled;
	}
	$state['zones'] = array();
	foreach ( array_merge( array( 0 => array( 'zone_id' => 0 ) ), WC_Shipping_Zones::get_zones() ) as $zone_data ) {
		$zone    = WC_Shipping_Zones::get_zone( (int) $zone_data['zone_id'] );
		$methods = array();
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			$methods[] = array( 'id' => $method->id, 'instance' => (int) $method->get_instance_id(), 'requires' => $method->get_option( 'requires', '' ), 'min_amount' => $method->get_option( 'min_amount', '' ) );
		}
		$state['zones'][] = array( 'id' => $zone->get_id(), 'name' => $zone->get_zone_name(), 'locations' => wp_list_pluck( $zone->get_zone_locations(), 'code' ), 'methods' => $methods );
	}
	$state['coupons'] = array();
	foreach ( array( 'digi10', 'digienvio' ) as $code ) {
		$coupon                    = new WC_Coupon( $code );
		$state['coupons'][ $code ] = $coupon->get_id() ? array( 'type' => $coupon->get_discount_type(), 'amount' => $coupon->get_amount(), 'free_shipping' => $coupon->get_free_shipping() ) : null;
	}
	$state['products'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$state['lookup']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_product_attributes_lookup" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $state;
}

function digi_wf_probe_orders() {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}
	$out = array();
	foreach ( wc_get_orders( array( 'limit' => 20, 'orderby' => 'id', 'order' => 'DESC' ) ) as $order ) {
		$shipping = array();
		foreach ( $order->get_shipping_methods() as $item ) {
			$shipping[] = $item->get_method_id();
		}
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array( 'product' => (int) $item->get_product_id(), 'variation' => (int) $item->get_variation_id(), 'qty' => (int) $item->get_quantity(), 'total' => wc_format_decimal( $item->get_total(), 2 ) );
		}
		$out[] = array(
			'id'       => $order->get_id(),
			'status'   => $order->get_status(),
			'payment'  => $order->get_payment_method(),
			'created'  => $order->get_created_via(),
			'shipping' => $shipping,
			'coupons'  => $order->get_coupon_codes(),
			'items'    => $items,
			'discount' => wc_format_decimal( $order->get_discount_total(), 2 ),
			'total'    => wc_format_decimal( $order->get_total(), 2 ),
			'currency' => $order->get_currency(),
			'country'  => $order->get_billing_country(),
			'customer' => (int) $order->get_customer_id(),
		);
	}
	return $out;
}
