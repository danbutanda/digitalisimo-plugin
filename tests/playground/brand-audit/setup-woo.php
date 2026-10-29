<?php
/**
 * WOO=1 (F4-Woo): con WooCommerce activo, un atributo con muestras, un producto simple y la página `digi-marca-woo`
 * con los siete widgets de WooCommerce de DIGITALÍSIMO Elements, el widget Productos de PRO con las tres partes nuevas
 * y el Menú del carrito con la barra y el cupón. run.cjs audita su editor (cada widget seleccionado) y su frontal.
 * Corre después de setup.php (que escribe ids.json) y añade la clave `woo` con el ID de la página.
 */
require '/wordpress/wp-load.php';
$digi_dir  = '/wordpress/wp-content/probe';
$digi_fail = static function ( $message ) use ( $digi_dir ) {
	file_put_contents( $digi_dir . '/setup-error.txt', $message . "\n", FILE_APPEND );
};
if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_create_attribute' ) ) {
	$digi_fail( 'WOO=1: WooCommerce no está activo' );
	return;
}
if ( ! class_exists( '\Digitalisimo\Elements\Woo\Registry' ) ) {
	$digi_fail( 'WOO=1: el módulo digitalisimo-woo no arrancó' );
	return;
}
update_option( 'woocommerce_currency', 'EUR' );
update_option( 'woocommerce_default_country', 'ES:M' );
update_option( 'woocommerce_newly_installed', 'no' );

// Atributo «Color» con dos términos y muestras visuales (metas propias; no es un atributo wc-visual).
$digi_attribute_id = wc_attribute_taxonomy_id_by_name( 'color' );
if ( ! $digi_attribute_id ) {
	$digi_attribute_id = wc_create_attribute( array( 'name' => 'Color', 'slug' => 'color', 'type' => 'select' ) );
	if ( is_wp_error( $digi_attribute_id ) ) {
		$digi_fail( 'WOO=1: no se pudo crear el atributo: ' . $digi_attribute_id->get_error_message() );
		return;
	}
	delete_transient( 'wc_attribute_taxonomies' );
	\WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
}
if ( ! taxonomy_exists( 'pa_color' ) ) {
	register_taxonomy( 'pa_color', array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ) );
}
$digi_terms = array();
foreach ( array( 'rojo' => '#D7263D', 'azul' => '#245EFF' ) as $digi_slug => $digi_color ) {
	$digi_term = get_term_by( 'slug', $digi_slug, 'pa_color' );
	if ( ! $digi_term ) {
		$digi_inserted = wp_insert_term( ucfirst( $digi_slug ), 'pa_color', array( 'slug' => $digi_slug ) );
		if ( is_wp_error( $digi_inserted ) ) {
			$digi_fail( 'WOO=1: no se pudo crear el término ' . $digi_slug . ': ' . $digi_inserted->get_error_message() );
			return;
		}
		$digi_term = get_term( (int) $digi_inserted['term_id'], 'pa_color' );
	}
	$digi_terms[ $digi_slug ] = (int) $digi_term->term_id;
	\Digitalisimo\Elements\Woo\Swatches\Data::save_term( (int) $digi_term->term_id, $digi_color );
}
\Digitalisimo\Elements\Woo\Swatches\Data::put( 'color', array( 'type' => 'visual', 'shape' => 'circle', 'size' => 28 ) );

// Un producto simple y uno variable por color, publicados y con precio.
$digi_product_id = 0;
$digi_simple     = get_page_by_path( 'digi-marca-producto', OBJECT, 'product' );
if ( $digi_simple ) {
	$digi_product_id = (int) $digi_simple->ID;
} else {
	$digi_product = new WC_Product_Simple();
	$digi_product->set_name( 'Producto de prueba de marca' );
	$digi_product->set_slug( 'digi-marca-producto' );
	$digi_product->set_status( 'publish' );
	$digi_product->set_regular_price( '20' );
	$digi_product->set_manage_stock( false );
	$digi_product->set_stock_status( 'instock' );
	$digi_product_id = (int) $digi_product->save();
}
$digi_variable_id = 0;
$digi_variable    = get_page_by_path( 'digi-marca-variable', OBJECT, 'product' );
if ( $digi_variable ) {
	$digi_variable_id = (int) $digi_variable->ID;
} else {
	$digi_parent = new WC_Product_Variable();
	$digi_parent->set_name( 'Variable de prueba de marca' );
	$digi_parent->set_slug( 'digi-marca-variable' );
	$digi_parent->set_status( 'publish' );
	$digi_attr = new WC_Product_Attribute();
	$digi_attr->set_id( (int) $digi_attribute_id );
	$digi_attr->set_name( 'pa_color' );
	$digi_attr->set_options( array_values( $digi_terms ) );
	$digi_attr->set_visible( true );
	$digi_attr->set_variation( true );
	$digi_parent->set_attributes( array( $digi_attr ) );
	$digi_variable_id = (int) $digi_parent->save();
	foreach ( $digi_terms as $digi_slug => $digi_term_id ) {
		$digi_variation = new WC_Product_Variation();
		$digi_variation->set_parent_id( $digi_variable_id );
		$digi_variation->set_attributes( array( 'pa_color' => $digi_slug ) );
		$digi_variation->set_regular_price( '25' );
		$digi_variation->set_status( 'publish' );
		$digi_variation->set_stock_status( 'instock' );
		$digi_variation->save();
	}
	if ( class_exists( 'WC_Product_Variable' ) ) {
		WC_Product_Variable::sync( $digi_variable_id );
	}
}
if ( ! $digi_product_id || ! $digi_variable_id ) {
	$digi_fail( 'WOO=1: no se pudieron crear los productos' );
	return;
}

// La página con los siete widgets, Productos con las partes nuevas y el Menú del carrito con la barra y el cupón.
$digi_page = get_page_by_path( 'digi-marca-woo', OBJECT, 'page' );
if ( $digi_page ) {
	$digi_woo_page_id = (int) $digi_page->ID;
} else {
	$digi_woo_page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Prueba de marca con la tienda', 'post_name' => 'digi-marca-woo' ) );
	$digi_widget      = static function ( $id, $type, array $settings = array() ) {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	};
	$digi_data = array(
		array( 'id' => 'wc00001', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			$digi_widget( 'ww00001', 'woocommerce-menu-cart', array( 'digitalisimo_cart_bar' => 'yes', 'digitalisimo_cart_coupon' => 'yes' ) ),
			$digi_widget( 'ww00002', 'digitalisimo-woo-wishlist-button', array( 'product_source' => 'custom', 'product_id' => $digi_product_id ) ),
			$digi_widget( 'ww00003', 'digitalisimo-woo-quick-view', array( 'product_source' => 'custom', 'product_id' => $digi_variable_id ) ),
			$digi_widget( 'ww00004', 'digitalisimo-woo-shipping-bar' ),
		) ),
		array( 'id' => 'wc00002', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			$digi_widget( 'ww00005', 'digitalisimo-woo-price-filter', array( 'destination' => 'shop' ) ),
			$digi_widget( 'ww00006', 'digitalisimo-woo-attribute-filter', array( 'destination' => 'shop', 'attribute' => 'pa_color', 'display' => 'swatches' ) ),
			$digi_widget( 'ww00007', 'digitalisimo-woo-swatches', array( 'attribute' => 'pa_color' ) ),
			$digi_widget( 'ww00008', 'digitalisimo-woo-wishlist' ),
		) ),
		array( 'id' => 'wc00003', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			$digi_widget( 'ww00009', 'woocommerce-products', array( 'columns' => 2, 'rows' => 1, 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'image', 'title', 'price', 'cart', 'quickview', 'wishlist', 'swatches' ) ) ),
		) ),
	);
	update_post_meta( $digi_woo_page_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $digi_woo_page_id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $digi_woo_page_id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $digi_woo_page_id, '_elementor_data', wp_slash( wp_json_encode( $digi_data ) ) );
}
if ( class_exists( '\Digitalisimo\Elements\Woo\Flags' ) && method_exists( '\Digitalisimo\Elements\Woo\Flags', 'refresh' ) ) {
	\Digitalisimo\Elements\Woo\Flags::refresh();
}
$digi_ids        = json_decode( (string) @file_get_contents( $digi_dir . '/ids.json' ), true ) ?: array();
$digi_ids['woo'] = (int) $digi_woo_page_id;
file_put_contents( $digi_dir . '/ids.json', wp_json_encode( $digi_ids ) );
