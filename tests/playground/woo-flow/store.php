<?php
/**
 * Tienda de prueba de woo-flow.sh (F4-Woo, V.16.4), por sitio y en una petición de ese sitio (`?digi_wf=setup`, con
 * WooCommerce ya instalado y cargado). Idempotente: una segunda llamada devuelve lo creado sin duplicarlo.
 *
 * - Tienda en España y euros, sin impuestos, compra como invitado, cupones activos, sin «Próximamente» y sin retener
 *   existencias durante el pago (la consulta de la reserva no funciona sobre la base SQLite de Playground).
 * - Catálogo: dos categorías, seis productos simples (12–60 €), dos atributos globales (`pa_color` y `pa_talla`, tipo
 *   «select», sin muestras) y un producto variable con las seis combinaciones.
 * - Envío: zona «España» con «Tarifa plana» (5 €) y «Envío gratuito» desde 50 € (`requires = min_amount`, descuentos
 *   incluidos); la zona 0 («Resto del mundo») sin métodos.
 * - Cupones: `DIGI10` (10 %) y `DIGIENVIO` (envío gratuito, sin descuento).
 * - Pago en modo de prueba: «Contra reembolso» (fuera de línea, sin pasarela ni red), también para pedidos virtuales.
 * - Páginas clásicas `digi-wf-carrito` ([woocommerce_cart]) y `digi-wf-pago` ([woocommerce_checkout]), además de las
 *   de bloques que crea WooCommerce (W11 prueba las dos).
 * - Tabla de búsqueda de atributos activa y con actualización directa (W9: el filtro nativo y el propio leen el mismo
 *   estado, sin esperar a Action Scheduler).
 */

defined( 'ABSPATH' ) || exit;

/** @return array Resultado de la preparación (`errors` vacío si todo quedó como se pide). */
function digi_wf_store_setup() {
	$out = array( 'blog' => get_current_blog_id(), 'errors' => array() );
	if ( ! function_exists( 'WC' ) || ! class_exists( 'WC_Product_Simple' ) ) {
		$out['errors'][] = 'WooCommerce no está cargado';
		return $out;
	}
	$fail = static function ( $message ) use ( &$out ) {
		$out['errors'][] = $message;
	};

	// Ajustes generales de la tienda.
	$options = array(
		'woocommerce_coming_soon'                     => 'no',
		'woocommerce_store_pages_only'                => 'no',
		'woocommerce_default_country'                 => 'ES:M',
		'woocommerce_store_address'                   => 'Calle de Prueba 1',
		'woocommerce_store_city'                      => 'Madrid',
		'woocommerce_store_postcode'                  => '28001',
		'woocommerce_currency'                        => 'EUR',
		'woocommerce_currency_pos'                    => 'right_space',
		'woocommerce_price_thousand_sep'              => '.',
		'woocommerce_price_decimal_sep'               => ',',
		'woocommerce_price_num_decimals'              => '2',
		'woocommerce_calc_taxes'                      => 'no',
		'woocommerce_enable_coupons'                  => 'yes',
		'woocommerce_enable_guest_checkout'           => 'yes',
		'woocommerce_enable_checkout_login_reminder'  => 'no',
		'woocommerce_enable_signup_and_login_from_checkout' => 'no',
		'woocommerce_default_customer_address'        => 'base',
		'woocommerce_ship_to_countries'               => '',
		'woocommerce_allowed_countries'               => 'all',
		'woocommerce_shipping_cost_requires_address'  => 'no',
		'woocommerce_cart_redirect_after_add'         => 'no',
		'woocommerce_enable_ajax_add_to_cart'         => 'yes',
		'woocommerce_hide_out_of_stock_items'         => 'no',
		// «Retener existencias» vacío: la reserva de ReserveStock usa `NOW() + INTERVAL n MINUTE`, que la base SQLite de
		// Playground no traduce («near "60": syntax error»), y el pago fallaría con «Not enough units… in stock».
		'woocommerce_hold_stock_minutes'              => '',
		'woocommerce_attribute_lookup_enabled'        => 'yes',
		'woocommerce_attribute_lookup_direct_updates' => 'yes',
		'woocommerce_allow_tracking'                  => 'no',
		'woocommerce_show_marketplace_suggestions'    => 'no',
		'woocommerce_onboarding_profile'              => array( 'skipped' => true ),
	);
	foreach ( $options as $name => $value ) {
		update_option( $name, $value );
	}

	// Pago fuera de línea: «Contra reembolso».
	update_option(
		'woocommerce_cod_settings',
		array(
			'enabled'            => 'yes',
			'title'              => 'Contra reembolso',
			'description'        => 'Pago al recibir el pedido (modo de prueba).',
			'instructions'       => 'Pago al recibir el pedido.',
			'enable_for_methods' => array(),
			'enable_for_virtual' => 'yes',
		)
	);
	update_option( 'woocommerce_gateway_order', array( 'cod' => 0 ) );

	// Categorías.
	$cats = array();
	foreach ( array( 'digi-wf-ropa' => 'Ropa de prueba', 'digi-wf-hogar' => 'Hogar de prueba' ) as $slug => $name ) {
		$term = term_exists( $slug, 'product_cat' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		}
		if ( is_wp_error( $term ) ) {
			$fail( "categoría $slug: " . $term->get_error_message() );
			continue;
		}
		$cats[ $slug ] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	}

	// Productos simples (el mismo orden siempre: los IDs coinciden entre corridas).
	$simple = array(
		'digi-wf-camiseta' => array( 'Camiseta de prueba', '60', 'digi-wf-ropa' ),
		'digi-wf-gorra'    => array( 'Gorra de prueba', '18', 'digi-wf-ropa' ),
		'digi-wf-calcetin' => array( 'Calcetines de prueba', '12', 'digi-wf-ropa' ),
		'digi-wf-taza'     => array( 'Taza de prueba', '15', 'digi-wf-hogar' ),
		'digi-wf-lampara'  => array( 'Lámpara de prueba', '45', 'digi-wf-hogar' ),
		'digi-wf-cojin'    => array( 'Cojín de prueba', '25', 'digi-wf-hogar' ),
	);
	$out['products'] = array();
	$index           = 0;
	foreach ( $simple as $slug => $data ) {
		$index++;
		$existing = get_page_by_path( $slug, OBJECT, 'product' );
		if ( $existing ) {
			$out['products'][ $slug ] = (int) $existing->ID;
			continue;
		}
		$product = new WC_Product_Simple();
		$product->set_name( $data[0] );
		$product->set_slug( $slug );
		$product->set_status( 'publish' );
		$product->set_regular_price( $data[1] );
		$product->set_sku( strtoupper( $slug ) );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 50 );
		$product->set_short_description( $data[0] . ' para las pruebas de F4-Woo.' );
		if ( isset( $cats[ $data[2] ] ) ) {
			$product->set_category_ids( array( $cats[ $data[2] ] ) );
		}
		$product->set_date_created( time() - $index * HOUR_IN_SECONDS );
		$out['products'][ $slug ] = (int) $product->save();
	}

	// Atributos globales sin muestras y sus términos.
	$attributes = array(
		'color' => array( 'Color', array( 'rojo' => 'Rojo', 'azul' => 'Azul', 'verde' => 'Verde' ) ),
		'talla' => array( 'Talla', array( 's' => 'S', 'm' => 'M' ) ),
	);
	$terms = array();
	foreach ( $attributes as $slug => $data ) {
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		if ( ! wc_attribute_taxonomy_id_by_name( $taxonomy ) ) {
			$created = wc_create_attribute( array( 'name' => $data[0], 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
			if ( is_wp_error( $created ) ) {
				$fail( "atributo $slug: " . $created->get_error_message() );
				continue;
			}
		}
		// WooCommerce registra las taxonomías de los atributos en `init`: la recién creada aún no existe en esta petición.
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => false, 'rewrite' => false ) );
		}
		foreach ( $data[1] as $term_slug => $term_name ) {
			$term = term_exists( $term_slug, $taxonomy );
			if ( ! $term ) {
				$term = wp_insert_term( $term_name, $taxonomy, array( 'slug' => $term_slug ) );
			}
			if ( is_wp_error( $term ) ) {
				$fail( "término $taxonomy/$term_slug: " . $term->get_error_message() );
				continue;
			}
			$terms[ $taxonomy ][ $term_slug ] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
		}
	}

	// Producto variable: color × talla, seis variaciones de 30 a 35 €.
	$variable_slug = 'digi-wf-sudadera';
	$existing      = get_page_by_path( $variable_slug, OBJECT, 'product' );
	if ( $existing ) {
		$out['products'][ $variable_slug ] = (int) $existing->ID;
	} elseif ( count( $terms ) === 2 ) {
		$list     = array();
		$position = 0;
		foreach ( $terms as $taxonomy => $ids ) {
			$attribute = new WC_Product_Attribute();
			$attribute->set_id( (int) wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$attribute->set_name( $taxonomy );
			$attribute->set_options( array_values( $ids ) );
			$attribute->set_position( $position++ );
			$attribute->set_visible( true );
			$attribute->set_variation( true );
			$list[] = $attribute;
		}
		$variable = new WC_Product_Variable();
		$variable->set_name( 'Sudadera de prueba' );
		$variable->set_slug( $variable_slug );
		$variable->set_status( 'publish' );
		$variable->set_sku( 'DIGI-WF-SUDADERA' );
		if ( isset( $cats['digi-wf-ropa'] ) ) {
			$variable->set_category_ids( array( $cats['digi-wf-ropa'] ) );
		}
		$variable->set_attributes( $list );
		$variable->set_date_created( time() - 7 * HOUR_IN_SECONDS );
		$variable_id = (int) $variable->save();
		$price       = 30;
		foreach ( array_keys( $terms['pa_color'] ) as $color ) {
			foreach ( array_keys( $terms['pa_talla'] ) as $size ) {
				$variation = new WC_Product_Variation();
				$variation->set_parent_id( $variable_id );
				$variation->set_attributes( array( 'pa_color' => $color, 'pa_talla' => $size ) );
				$variation->set_regular_price( (string) $price++ );
				$variation->set_sku( 'DIGI-WF-SUDADERA-' . strtoupper( $color . '-' . $size ) );
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( 20 );
				$variation->set_status( 'publish' );
				$variation->save();
			}
		}
		WC_Product_Variable::sync( $variable_id );
		wc_delete_product_transients( $variable_id );
		$out['products'][ $variable_slug ] = $variable_id;
	} else {
		$fail( 'el producto variable necesita los dos atributos' );
	}
	$variable_product = isset( $out['products'][ $variable_slug ] ) ? wc_get_product( $out['products'][ $variable_slug ] ) : null;
	$out['variations'] = $variable_product ? count( $variable_product->get_children() ) : 0;
	if ( 6 !== $out['variations'] ) {
		$fail( 'el producto variable tiene ' . $out['variations'] . ' variaciones (se esperaban 6)' );
	}

	// Envío: zona «España» con tarifa plana y envío gratuito desde 50 €.
	$zone_id = (int) get_option( 'digi_wf_zone' );
	$zone    = $zone_id ? WC_Shipping_Zones::get_zone( $zone_id ) : null;
	if ( ! $zone || ! $zone->get_id() ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'España' );
		$zone->set_zone_order( 1 );
		$zone->add_location( 'ES', 'country' );
		$zone->save();
		$flat = $zone->add_shipping_method( 'flat_rate' );
		$free = $zone->add_shipping_method( 'free_shipping' );
		update_option( 'woocommerce_flat_rate_' . $flat . '_settings', array( 'title' => 'Tarifa plana', 'tax_status' => 'none', 'cost' => '5' ) );
		update_option( 'woocommerce_free_shipping_' . $free . '_settings', array( 'title' => 'Envío gratuito', 'requires' => 'min_amount', 'min_amount' => '50', 'ignore_discounts' => 'no' ) );
		update_option( 'digi_wf_zone', $zone->get_id() );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
	$out['zone'] = array( 'id' => $zone->get_id(), 'methods' => array() );
	foreach ( $zone->get_shipping_methods( true ) as $method ) {
		$out['zone']['methods'][] = array( 'id' => $method->id, 'instance' => (int) $method->get_instance_id(), 'requires' => $method->get_option( 'requires', '' ), 'min_amount' => $method->get_option( 'min_amount', '' ), 'cost' => $method->get_option( 'cost', '' ) );
	}
	if ( count( $out['zone']['methods'] ) !== 2 ) {
		$fail( 'la zona España no quedó con sus dos métodos' );
	}

	// Cupones.
	$coupons = array(
		'digi10'    => array( 'discount_type' => 'percent', 'amount' => '10', 'free_shipping' => false ),
		'digienvio' => array( 'discount_type' => 'fixed_cart', 'amount' => '0', 'free_shipping' => true ),
	);
	$out['coupons'] = array();
	foreach ( $coupons as $code => $data ) {
		$id = wc_get_coupon_id_by_code( $code );
		if ( ! $id ) {
			$coupon = new WC_Coupon();
			$coupon->set_code( $code );
			$coupon->set_discount_type( $data['discount_type'] );
			$coupon->set_amount( $data['amount'] );
			$coupon->set_free_shipping( $data['free_shipping'] );
			$coupon->set_individual_use( false );
			$id = $coupon->save();
		}
		$out['coupons'][ $code ] = (int) $id;
	}

	// Páginas clásicas de carrito y pago.
	$out['pages'] = array();
	foreach ( array( 'digi-wf-carrito' => array( 'Carrito clásico', '[woocommerce_cart]' ), 'digi-wf-pago' => array( 'Pago clásico', '[woocommerce_checkout]' ) ) as $slug => $data ) {
		$page = get_page_by_path( $slug );
		$id   = $page ? (int) $page->ID : (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $data[0], 'post_name' => $slug, 'post_content' => '<!-- wp:shortcode -->' . $data[1] . '<!-- /wp:shortcode -->' ) );
		$out['pages'][ $slug ] = get_permalink( $id );
	}
	foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $page ) {
		$out['pages'][ 'wc-' . $page ] = get_permalink( wc_get_page_id( $page ) );
	}

	// Tabla de búsqueda de atributos (W9): con la actualización directa, guardar cada producto ya la llenó (en una
	// instalación nueva y sin productos WooCommerce no agenda ninguna regeneración). Se comprueba con una consulta.
	global $wpdb;
	$lookup        = $wpdb->prefix . 'wc_product_attributes_lookup';
	$out['lookup'] = array(
		'enabled' => get_option( 'woocommerce_attribute_lookup_enabled' ),
		'direct'  => get_option( 'woocommerce_attribute_lookup_direct_updates' ),
		'rows'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$lookup}" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
	if ( isset( $out['products'][ $variable_slug ] ) && ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$lookup} WHERE product_or_parent_id = %d", $out['products'][ $variable_slug ] ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fail( 'la tabla de búsqueda de atributos no tiene filas del producto variable' );
	}

	flush_rewrite_rules( false );
	$out['urls'] = array();
	foreach ( $out['products'] as $slug => $id ) {
		$out['urls'][ $slug ] = get_permalink( $id );
	}
	update_option( 'digi_wf_store', $out, false );
	return $out;
}
