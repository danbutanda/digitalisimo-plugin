<?php
namespace Digitalisimo\Elements\Woo\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Datos de catálogo que los filtros necesitan para pintarse (F4-Woo, v1 §5.5 y V.8.4), cacheados en transitorios
 * que se invalidan con los eventos de producto de WooCommerce.
 *
 * - **Límites del precio** (`digitalisimo_elements_woo_price_bounds`): mínimo y máximo de `wc_product_meta_lookup`
 *   de los productos publicados. Una lectura por petición con un filtro de límites automáticos: 0 consultas con caché
 *   de objetos, 2 sin ella (caducidad y valor, R-M16), y una consulta más con la caché fría.
 * - **Términos con existencias** (`digitalisimo_elements_woo_terms_in_stock`): con la tabla de atributos activa y
 *   «Ocultar productos agotados», los términos que tienen algún producto o variación en existencia
 *   (`SELECT DISTINCT term_id … WHERE taxonomy = %s AND in_stock = 1`, R-M17). Un solo transitorio con una entrada por
 *   atributo, así un evento de producto lo invalida con un solo borrado. Sin la tabla o sin «Ocultar agotados», el
 *   widget usa `hide_empty` del término, que cuenta productos ocultos y agotados: se declara.
 */
final class Bounds {
	const PRICE  = 'digitalisimo_elements_woo_price_bounds';
	const TERMS  = 'digitalisimo_elements_woo_terms_in_stock';
	const EVENTS = array(
		'woocommerce_new_product',
		'woocommerce_update_product',
		'woocommerce_delete_product',
		'woocommerce_product_set_stock_status',
		'woocommerce_variation_set_stock_status',
		'wc_delete_product_transients',
	);

	/** @var bool Ya se borraron en esta petición y no se volvieron a calcular. */
	private static $flushed = false;

	/** Engancha la invalidación (con WooCommerce). */
	public static function init(): void {
		foreach ( self::EVENTS as $event ) {
			add_action( $event, array( __CLASS__, 'flush' ) );
		}
	}

	/** Borra los dos transitorios, una vez por petición mientras nadie los vuelva a calcular. */
	public static function flush(): void {
		if ( self::$flushed ) {
			return;
		}
		self::$flushed = true;
		delete_transient( self::PRICE );
		delete_transient( self::TERMS );
	}

	/**
	 * Mínimo y máximo del catálogo, tal como se guardan.
	 *
	 * @return array{min:float,max:float}
	 */
	public static function price(): array {
		$cached = get_transient( self::PRICE );
		if ( is_array( $cached ) && isset( $cached['min'], $cached['max'] ) ) {
			return array( 'min' => (float) $cached['min'], 'max' => (float) $cached['max'] );
		}
		global $wpdb;
		$table = isset( $wpdb->wc_product_meta_lookup ) ? $wpdb->wc_product_meta_lookup : $wpdb->prefix . 'wc_product_meta_lookup';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tablas del sitio, sin entrada.
		$row    = $wpdb->get_row( "SELECT MIN( l.min_price ) AS min_price, MAX( l.max_price ) AS max_price FROM {$table} l INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id WHERE p.post_type = 'product' AND p.post_status = 'publish'" );
		$bounds = array(
			'min' => is_object( $row ) && null !== $row->min_price ? max( 0.0, (float) $row->min_price ) : 0.0,
			'max' => is_object( $row ) && null !== $row->max_price ? max( 0.0, (float) $row->max_price ) : 0.0,
		);
		set_transient( self::PRICE, $bounds, DAY_IN_SECONDS );
		self::$flushed = false;
		return $bounds;
	}

	/**
	 * Límites para mostrar: con los impuestos añadidos si la tienda guarda sin ellos y los muestra con ellos, y
	 * redondeados hacia fuera al paso, como el widget nativo (`W:includes/widgets/class-wc-widget-price-filter.php:91-112`).
	 *
	 * @param float $step Paso del filtro (≥ 0,01).
	 * @return array{min:float,max:float}
	 */
	public static function price_display( float $step = 1.0 ): array {
		$bounds = self::price();
		$min    = $bounds['min'];
		$max    = $bounds['max'];
		if ( Query::prices_shown_with_tax() ) {
			$rates = \WC_Tax::get_rates( apply_filters( 'woocommerce_price_filter_widget_tax_class', '' ) );
			if ( $rates ) {
				$min += \WC_Tax::get_tax_total( \WC_Tax::calc_exclusive_tax( $min, $rates ) );
				$max += \WC_Tax::get_tax_total( \WC_Tax::calc_exclusive_tax( $max, $rates ) );
			}
		}
		$step = max( 0.01, $step );
		return array( 'min' => floor( $min / $step ) * $step, 'max' => max( $step, ceil( $max / $step ) * $step ) );
	}

	/**
	 * IDs de los términos de un atributo con algún producto en existencia según la tabla de atributos.
	 *
	 * @param string $taxonomy Atributo (`pa_color`), ya validado.
	 * @return int[]
	 */
	public static function terms_in_stock( string $taxonomy ): array {
		$all = get_transient( self::TERMS );
		$all = is_array( $all ) ? $all : array();
		if ( isset( $all[ $taxonomy ] ) && is_array( $all[ $taxonomy ] ) ) {
			return array_map( 'intval', $all[ $taxonomy ] );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'wc_product_attributes_lookup';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla del sitio.
		$ids              = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT term_id FROM {$table} WHERE taxonomy = %s AND in_stock = 1", $taxonomy ) );
		$all[ $taxonomy ] = array_values( array_map( 'intval', is_array( $ids ) ? $ids : array() ) );
		set_transient( self::TERMS, $all, DAY_IN_SECONDS );
		self::$flushed = false;
		return $all[ $taxonomy ];
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$flushed = false;
	}
}
