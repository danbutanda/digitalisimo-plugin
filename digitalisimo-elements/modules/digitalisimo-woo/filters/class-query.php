<?php
namespace Digitalisimo\Elements\Woo\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Consulta de los dos tipos de filtro del Loop Grid (F4-Woo, V.8.4). Clean-room: sólo la API pública de WooCommerce
 * y sus tablas, nunca una clase `Automattic\WooCommerce\Internal\…`.
 *
 * **Precio (`digitalisimo_price`).** Valida cada extremo (número ≥ 0 y ≤ 10⁹, con los decimales de la tienda; un
 * valor vacío o inválido no filtra) e intercambia mínimo y máximo si llegan al revés. Añade la variable de consulta
 * `digitalisimo_price_range`; un `posts_clauses` propio, enganchado la primera vez que hace falta y sólo para consultas
 * con esa variable, une `wc_product_meta_lookup` con el alias y la guarda de
 * `ProductUtil::append_product_sorting_table_join()` (`\bwc_product_meta_lookup\b`, W-12), así no se repite el JOIN de
 * la ordenación por precio, y aplica la condición de solapamiento y el ajuste de impuestos de
 * `WC_Query::price_filter_post_clauses()` (`W:includes/class-wc-query.php:789-834`).
 *
 * **Atributo (`digitalisimo_attribute`, nombre `digiattr_<taxonomía>`).** Sólo atributos globales `pa_*` registrados.
 * Con `woocommerce_attribute_lookup_enabled = yes`, la regla de `Filterer::filter_by_attribute_post_clauses()`
 * (`W:src/Internal/ProductAttributesLookup/Filterer.php:59-153`) reescrita sobre `{prefijo}wc_product_attributes_lookup`:
 * `in_stock = 1` si se ocultan los agotados (con su filtro), los términos de una «Y» juntos con `GROUP BY … HAVING
 * COUNT` en los dos ramales `is_variation_attribute`, y `1=0` si ningún término existe. Sin la tabla, el `tax_query` por
 * *slug* de `WC_Query::get_tax_query()` (`:916-933`). En los dos casos, el mismo conjunto que la tienda nativa. Un nombre
 * que no es un atributo deja el bucle vacío (`post__in = 0`): falla cerrado, nunca muestra de más.
 */
final class Query {
	const PRICE_VAR      = 'digitalisimo_price_range';
	const ATTRIBUTE_VAR  = 'digitalisimo_attribute_filter';
	const MAX_PRICE      = 1000000000;
	const MAX_TERMS      = 50;
	const MAX_ATTRIBUTES = 10;
	const PRICE_NAMES    = array( 'min' => 'digi_price_min', 'max' => 'digi_price_max' );

	/** @var bool */
	private static $hooked = false;

	/**
	 * Tipo `digitalisimo_price`.
	 *
	 * @param array  $args    Argumentos de la consulta del bucle.
	 * @param array  $filters `{ digi_price_min: { terms: [ '10' ] }, digi_price_max: … }`.
	 * @param object $widget  Bucle.
	 */
	public static function apply_price( array $args, array $filters, $widget = null ): array {
		$range = self::price_range( $filters );
		if ( null === $range ) {
			return $args;
		}
		$args[ self::PRICE_VAR ] = $range;
		self::hook();
		return $args;
	}

	/**
	 * Rango validado, o `null` si ningún extremo es válido.
	 *
	 * @param array $filters Filtros del tipo.
	 * @return array{min:?float,max:?float}|null
	 */
	public static function price_range( array $filters ): ?array {
		$min = self::price_value( self::first_term( $filters, self::PRICE_NAMES['min'] ) );
		$max = self::price_value( self::first_term( $filters, self::PRICE_NAMES['max'] ) );
		if ( null === $min && null === $max ) {
			return null;
		}
		if ( null !== $min && null !== $max && $min > $max ) {
			list( $min, $max ) = array( $max, $min );
		}
		return array( 'min' => $min, 'max' => $max );
	}

	/**
	 * Un importe: número sin signo ni exponente, entre 0 y 10⁹, redondeado a los decimales de la tienda. Admite una
	 * coma decimal si no hay punto. Otro valor, `null`.
	 *
	 * @param mixed $raw Lo recibido.
	 */
	public static function price_value( $raw ): ?float {
		if ( ! is_scalar( $raw ) ) {
			return null;
		}
		$raw = trim( (string) $raw );
		if ( false === strpos( $raw, '.' ) && 1 === substr_count( $raw, ',' ) ) {
			$raw = str_replace( ',', '.', $raw );
		}
		if ( ! preg_match( '/^(?:\d{1,10}(?:\.\d{0,8})?|\.\d{1,8})$/', $raw ) ) {
			return null;
		}
		$value = (float) $raw;
		if ( $value > self::MAX_PRICE ) {
			return null;
		}
		return round( $value, self::decimals() );
	}

	/** Decimales de la tienda (2 sin WooCommerce). */
	public static function decimals(): int {
		return function_exists( 'wc_get_price_decimals' ) ? max( 0, min( 8, (int) wc_get_price_decimals() ) ) : 2;
	}

	/**
	 * Primer término de un nombre, como lo dejan la URL (`terms: [ '10' ]`) y el refresco.
	 *
	 * @param array  $filters Filtros del tipo.
	 * @param string $name    Nombre.
	 * @return mixed
	 */
	private static function first_term( array $filters, string $name ) {
		if ( ! isset( $filters[ $name ] ) || ! is_array( $filters[ $name ] ) || ! isset( $filters[ $name ]['terms'] ) ) {
			return null;
		}
		$terms = $filters[ $name ]['terms'];
		if ( is_array( $terms ) ) {
			$terms = reset( $terms );
		}
		return $terms;
	}

	/**
	 * Tipo `digitalisimo_attribute`.
	 *
	 * @param array  $args    Argumentos de la consulta del bucle.
	 * @param array  $filters `{ digiattr_pa_color: { terms: [ 'rojo' ], logicalJoin: 'OR' }, … }`.
	 * @param object $widget  Bucle.
	 */
	public static function apply_attribute( array $args, array $filters, $widget = null ): array {
		$chosen  = array();
		$invalid = false;
		$count   = 0;
		foreach ( $filters as $name => $filter ) {
			if ( ! is_string( $name ) || 0 !== strpos( $name, Types::ATTRIBUTE_PREFIX ) ) {
				continue;
			}
			$terms = self::slugs( is_array( $filter ) && isset( $filter['terms'] ) ? $filter['terms'] : array() );
			if ( ! $terms ) {
				continue;
			}
			$taxonomy = substr( $name, strlen( Types::ATTRIBUTE_PREFIX ) );
			if ( ++$count > self::MAX_ATTRIBUTES || ! self::is_attribute( $taxonomy ) ) {
				$invalid = true;
				continue;
			}
			$or                  = is_array( $filter ) && isset( $filter['logicalJoin'] ) && is_string( $filter['logicalJoin'] ) && 'OR' === strtoupper( $filter['logicalJoin'] );
			$chosen[ $taxonomy ] = array( 'terms' => $terms, 'query_type' => $or ? 'or' : 'and' );
		}
		if ( $invalid ) {
			$args['post__in'] = array( 0 );
			return $args;
		}
		if ( ! $chosen ) {
			return $args;
		}
		if ( self::lookup_active() ) {
			$args[ self::ATTRIBUTE_VAR ] = $chosen;
			self::hook();
			return $args;
		}
		$tax_query = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array( 'relation' => 'AND' );
		foreach ( $chosen as $taxonomy => $data ) {
			$tax_query[] = array(
				'taxonomy'         => $taxonomy,
				'field'            => 'slug',
				'terms'            => $data['terms'],
				'operator'         => 'and' === $data['query_type'] ? 'AND' : 'IN',
				'include_children' => false,
			);
		}
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		return $args;
	}

	/**
	 * *Slugs* saneados como `WC_Query::get_layered_nav_chosen_attributes()` (`sanitize_title`), sin repetir y como mucho
	 * `MAX_TERMS`.
	 *
	 * @param mixed $terms Lista o cadena.
	 * @return string[]
	 */
	public static function slugs( $terms ): array {
		if ( is_string( $terms ) ) {
			$terms = array( $terms );
		}
		$out = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( ! is_scalar( $term ) ) {
				continue;
			}
			$slug = sanitize_title( (string) $term );
			if ( '' !== $slug && ! in_array( $slug, $out, true ) ) {
				$out[] = $slug;
			}
			if ( count( $out ) >= self::MAX_TERMS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * ¿Es un atributo global registrado? Sólo nombres ASCII (`pa_[a-z0-9_-]+`): uno que no lo sea no cabe en la URL
	 * (`e-filter-…` sólo admite esos caracteres) y el widget no lo ofrece.
	 *
	 * @param string $taxonomy Taxonomía.
	 */
	public static function is_attribute( string $taxonomy ): bool {
		return 1 === preg_match( '/^pa_[a-z0-9_\-]{1,29}$/', $taxonomy )
			&& function_exists( 'taxonomy_exists' ) && taxonomy_exists( $taxonomy )
			&& function_exists( 'wc_get_attribute_taxonomy_names' ) && in_array( $taxonomy, (array) wc_get_attribute_taxonomy_names(), true );
	}

	/** La tienda filtra los atributos con su tabla de búsqueda (`Filterer::filtering_via_lookup_table_is_active()`). */
	public static function lookup_active(): bool {
		return function_exists( 'get_option' ) && 'yes' === get_option( 'woocommerce_attribute_lookup_enabled' );
	}

	/** «Ocultar productos agotados» para el filtro por atributo, con el filtro de WooCommerce (desde 9.8). */
	public static function hide_out_of_stock(): bool {
		return (bool) apply_filters( 'woocommerce_product_attributes_filterer_hide_out_of_stock', 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) );
	}

	/** Engancha `posts_clauses` la primera vez que una consulta lo necesita. */
	private static function hook(): void {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_filter( 'posts_clauses', array( __CLASS__, 'clauses' ), 10, 2 );
	}

	/**
	 * `posts_clauses`: sólo actúa en las consultas con las variables propias.
	 *
	 * @param mixed  $clauses Cláusulas.
	 * @param object $query   `WP_Query`.
	 * @return mixed
	 */
	public static function clauses( $clauses, $query = null ) {
		if ( ! is_array( $clauses ) || ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return $clauses;
		}
		$range = $query->get( self::PRICE_VAR );
		if ( is_array( $range ) && array_key_exists( 'min', $range ) && array_key_exists( 'max', $range ) ) {
			$clauses = self::price_clauses( $clauses, $range );
		}
		$chosen = $query->get( self::ATTRIBUTE_VAR );
		if ( is_array( $chosen ) && $chosen ) {
			$clauses = self::attribute_clauses( $clauses, $chosen );
		}
		return $clauses;
	}

	/**
	 * Condición de precio de `WC_Query::price_filter_post_clauses()` con el JOIN guardado.
	 *
	 * @param array $clauses Cláusulas.
	 * @param array $range   `{ min, max }`, cada uno número o `null`.
	 */
	public static function price_clauses( array $clauses, array $range ): array {
		global $wpdb;
		$min = null === $range['min'] ? 0.0 : (float) $range['min'];
		$max = null === $range['max'] ? (float) PHP_INT_MAX : (float) $range['max'];
		if ( self::prices_shown_with_tax() ) {
			self::load_customer();
			$rates = \WC_Tax::get_rates( apply_filters( 'woocommerce_price_filter_widget_tax_class', '' ) );
			if ( $rates ) {
				$min -= \WC_Tax::get_tax_total( \WC_Tax::calc_inclusive_tax( $min, $rates ) );
				$max -= \WC_Tax::get_tax_total( \WC_Tax::calc_inclusive_tax( $max, $rates ) );
			}
		}
		$clauses['join']  = self::append_join( isset( $clauses['join'] ) ? $clauses['join'] : '' );
		$clauses['where'] = ( isset( $clauses['where'] ) ? (string) $clauses['where'] : '' ) . $wpdb->prepare( ' AND NOT (%f<wc_product_meta_lookup.min_price OR %f>wc_product_meta_lookup.max_price ) ', $max, $min );
		return $clauses;
	}

	/** La tienda guarda sin impuestos y los muestra con ellos (la condición de WooCommerce para ajustar). */
	public static function prices_shown_with_tax(): bool {
		return function_exists( 'wc_tax_enabled' ) && wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_shop' )
			&& function_exists( 'wc_prices_include_tax' ) && ! wc_prices_include_tax() && class_exists( 'WC_Tax' );
	}

	/**
	 * En el refresco del bucle (REST, donde WooCommerce no carga el carrito, W-15) los impuestos se calculan con la
	 * ubicación del visitante, como al pintar la página.
	 */
	private static function load_customer(): void {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && function_exists( 'WC' ) && null === WC()->customer && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
	}

	/**
	 * JOIN de `wc_product_meta_lookup` con su alias, salvo que la cláusula ya lo nombre: la guarda de
	 * `ProductUtil::append_product_sorting_table_join()` de 11.2 (`W:src/Internal/Utilities/ProductUtil.php:150-168`).
	 *
	 * @param mixed $join Cláusula JOIN.
	 */
	public static function append_join( $join ): string {
		global $wpdb;
		if ( ! is_string( $join ) ) {
			$join = is_object( $join ) && method_exists( $join, '__toString' ) ? (string) $join : '';
		}
		if ( ! preg_match( '/\bwc_product_meta_lookup\b/', $join ) ) {
			$table = isset( $wpdb->wc_product_meta_lookup ) ? $wpdb->wc_product_meta_lookup : $wpdb->prefix . 'wc_product_meta_lookup';
			$join .= " LEFT JOIN {$table} wc_product_meta_lookup ON {$wpdb->posts}.ID = wc_product_meta_lookup.product_id ";
		}
		return $join;
	}

	/**
	 * Regla de `Filterer::filter_by_attribute_post_clauses()` sobre la tabla de búsqueda de atributos.
	 *
	 * @param array $clauses Cláusulas.
	 * @param array $chosen  `{ pa_x: { terms: [ slug… ], query_type: and|or } }`.
	 */
	public static function attribute_clauses( array $clauses, array $chosen ): array {
		global $wpdb;
		$table    = $wpdb->prefix . 'wc_product_attributes_lookup';
		$root     = " {$wpdb->posts}.ID IN ( SELECT product_or_parent_id FROM (";
		$in_stock = self::hide_out_of_stock() ? ' AND in_stock = 1' : '';
		$and_ids  = array();
		$parts    = array();
		foreach ( $chosen as $taxonomy => $data ) {
			if ( ! is_string( $taxonomy ) || ! is_array( $data ) || empty( $data['terms'] ) ) {
				continue;
			}
			$ids   = self::term_ids( $taxonomy, (array) $data['terms'] );
			$count = count( $ids );
			if ( 0 === $count ) {
				continue;
			}
			if ( 'and' === ( isset( $data['query_type'] ) ? $data['query_type'] : 'and' ) && $count > 1 ) {
				$and_ids = array_merge( $and_ids, $ids );
				continue;
			}
			$parts[] = "{$root} SELECT product_or_parent_id FROM {$table} lt WHERE term_id in (" . implode( ',', $ids ) . "){$in_stock} )";
		}
		if ( $and_ids ) {
			$count   = count( $and_ids );
			$list    = '(' . implode( ',', $and_ids ) . ')';
			$parts[] = "{$root} SELECT product_or_parent_id FROM {$table} lt WHERE is_variation_attribute=0{$in_stock} AND term_id in {$list} GROUP BY product_id HAVING COUNT(product_id)={$count}"
				. " UNION SELECT product_or_parent_id FROM {$table} lt WHERE is_variation_attribute=1{$in_stock} AND term_id in {$list} GROUP BY product_or_parent_id HAVING COUNT(DISTINCT term_id)={$count} )";
		}
		$where = isset( $clauses['where'] ) ? (string) $clauses['where'] : '';
		if ( $parts ) {
			$where .= ' AND (' . implode( ' temp ) AND ', $parts ) . ' temp ))';
		} else {
			$where .= ' AND 1=0';
		}
		$clauses['where'] = $where;
		return $clauses;
	}

	/**
	 * IDs de los términos de esos *slugs* en la taxonomía (sólo los que existen), como enteros.
	 *
	 * @param string   $taxonomy Taxonomía.
	 * @param string[] $slugs    Slugs saneados.
	 * @return int[]
	 */
	public static function term_ids( string $taxonomy, array $slugs ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'slug'       => array_values( $slugs ),
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $terms ) ) ) );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$hooked = false;
	}
}
