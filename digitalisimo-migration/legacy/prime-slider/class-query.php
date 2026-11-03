<?php
namespace Digitalisimo\Elements\Legacy\Prime_Slider;

defined( 'ABSPATH' ) || exit;

/**
 * Consultas de Prime Slider en los controles de consulta de PRO Elements (§3.E.4, frente F).
 *
 * La consulta de Prime Slider (`Group_Control_Query` de Lite) guarda los términos por **`term_taxonomy_id`**: su
 * selector dinámico devuelve `$term->term_taxonomy_id` y la consulta los lee así
 * (`L/traits/query-controls/select-input/dynamic-select-input-module.php:336`, `group-control-query.php:747-766`).
 * Es lo mismo que esperan los controles de PRO (`modules/query-control/classes/elementor-post-query.php:248-271`), así
 * que los IDs pasan **tal cual**. Por eso no se usa `Translator::posts_query()`, que los trata como `term_id` de
 * Element Pack y los estropearía.
 *
 * Todo es puro: no escribe nada. Las lecturas (`get_term_by`) sólo descartan términos que ya no existen en las
 * consultas de productos, cuyo renderizador de PRO no lo comprueba (`products-renderer.php:172-173,296-297`).
 *
 * Cada método devuelve `array( $ajustes, $codigos )`, con los códigos `ps.dyn.*` de lo que no tiene equivalente exacto
 * (`[ código, detalle ]`), para que `Dynamic` los informe.
 */
final class Query {
	/** Orden de entradas de Prime Slider → `orderby` de PRO (el resto es un valor de `WP_Query` y pasa tal cual). */
	const POSTS_ORDERBY = array( 'date' => 'post_date', 'title' => 'post_title', 'menu_order' => 'menu_order', 'comment_count' => 'comment_count', 'rand' => 'rand', 'modified' => 'modified', 'ID' => 'ID', 'author' => 'author' );

	/** Orden de productos de Prime Slider → el de WooCommerce de PRO (`products-trait.php:63-75`). */
	const PRODUCT_ORDERBY = array( 'date' => 'date', 'title' => 'title', 'menu_order' => 'menu_order', 'rand' => 'rand', 'price' => 'price', 'sales' => 'popularity' );

	/** Fechas de `posts_select_date` (las mismas opciones en los dos). */
	const DATES = array( 'anytime', 'today', 'week', 'month', 'quarter', 'year', 'exact' );

	/**
	 * Consulta de entradas: `posts_*` de Prime Slider → los campos del control de consulta de PRO con `$prefix`
	 * (`post_query_` en el Loop Carousel; `posts_` en el widget Entradas).
	 *
	 * @return array{0:array, 1:array} ajustes y códigos.
	 */
	public static function posts( array $s, string $prefix ): array {
		$out    = array();
		$codes  = array();
		$source = (string) ( $s['posts_source'] ?? 'post' );
		$types  = array( 'manual_selection' => 'by_id', 'current_query' => 'current_query', '_related_post_type' => 'related' );
		$type   = $types[ $source ] ?? ( 1 === preg_match( '/^[a-z0-9_-]{1,20}$/', $source ) ? $source : 'post' );

		$out[ $prefix . 'post_type' ] = $type;
		if ( 'by_id' === $type ) {
			$out[ $prefix . 'posts_ids' ] = self::ids( $s['posts_selected_ids'] ?? array() );
		}
		if ( 'related' === $type ) {
			// Prime Slider relacionaba por el autor de la entrada; PRO, por taxonomías.
			$codes[] = array( 'ps.dyn.related', array() );
		}
		if ( ! in_array( $type, array( 'by_id', 'current_query' ), true ) ) {
			$include = array_values( array_intersect( array( 'terms', 'authors' ), self::lst( $s['posts_include_by'] ?? array() ) ) );
			if ( $include ) {
				$out[ $prefix . 'include' ] = $include;
				if ( in_array( 'terms', $include, true ) ) {
					$out[ $prefix . 'include_term_ids' ] = self::ids( $s['posts_include_term_ids'] ?? array() );
				}
				if ( in_array( 'authors', $include, true ) ) {
					$out[ $prefix . 'include_authors' ] = self::ids( $s['posts_include_author_ids'] ?? array() );
				}
			}
			$exclude = array_values( array_intersect( array( 'current_post', 'manual_selection', 'terms', 'authors' ), self::lst( $s['posts_exclude_by'] ?? array() ) ) );
			if ( $exclude ) {
				$out[ $prefix . 'exclude' ] = $exclude;
				if ( in_array( 'manual_selection', $exclude, true ) ) {
					$out[ $prefix . 'exclude_ids' ] = self::ids( $s['posts_exclude_ids'] ?? array() );
				}
				if ( in_array( 'terms', $exclude, true ) ) {
					$out[ $prefix . 'exclude_term_ids' ] = self::ids( $s['posts_exclude_term_ids'] ?? array() );
				}
				if ( in_array( 'authors', $exclude, true ) ) {
					$out[ $prefix . 'exclude_authors' ] = self::ids( $s['posts_exclude_author_ids'] ?? array() );
				}
			}
			$date = (string) ( $s['posts_select_date'] ?? 'anytime' );
			$date = in_array( $date, self::DATES, true ) ? $date : 'anytime';
			if ( 'anytime' !== $date ) {
				$out[ $prefix . 'select_date' ] = $date;
				if ( 'exact' === $date ) {
					foreach ( array( 'before', 'after' ) as $edge ) {
						$value = self::date( $s[ 'posts_date_' . $edge ] ?? '' );
						if ( '' !== $value ) {
							$out[ $prefix . 'date_' . $edge ] = $value;
						}
					}
				}
			}
		}
		if ( 'current_query' !== $type ) {
			$orderby = (string) ( $s['posts_orderby'] ?? 'date' );
			$out[ $prefix . 'orderby' ] = self::POSTS_ORDERBY[ $orderby ] ?? 'post_date';
			$out[ $prefix . 'order' ]   = self::order( $s['posts_order'] ?? 'desc' );
		}
		if ( 'post' === $type ) {
			$out[ $prefix . 'ignore_sticky_posts' ] = 'yes' === ( $s['posts_ignore_sticky_posts'] ?? 'yes' ) ? 'yes' : '';
		}
		$codes    = array_merge( $codes, self::common_codes( $s ) );
		$query_id = self::query_id( $s['query_id'] ?? '' );
		if ( '' !== $query_id ) {
			// PRO dispara `elementor/query/<id>`, no la acción de Prime Slider: el código que filtraba hay que moverlo.
			$out[ $prefix . 'query_id' ] = $query_id;
			$codes[]                     = array( 'ps.dyn.query_id', array( 'name' => $query_id ) );
		}
		return array( $out, $codes );
	}

	/**
	 * Consulta de productos: `posts_*` y `product_*` de Prime Slider → el control de productos de PRO con `$prefix`
	 * (`product_query_` en el Loop Carousel con la piel de productos; `query_` en el widget Productos).
	 *
	 * @return array{0:array, 1:array} ajustes y códigos.
	 */
	public static function products( array $s, string $prefix ): array {
		$out    = array();
		$codes  = array();
		$source = (string) ( $s['posts_source'] ?? 'product' );
		$kinds  = array( 'onsale' => 'sale', 'featured' => 'featured' );
		$sources = array( 'manual_selection' => 'by_id', 'current_query' => 'current_query', '_related_post_type' => 'related_products' );
		if ( isset( $sources[ $source ] ) ) {
			$type = $sources[ $source ];
		} else {
			$type = $kinds[ (string) ( $s['product_show_product_type'] ?? 'all' ) ] ?? 'product';
			if ( 'product' !== $source ) {
				// Un widget de productos con otra fuente: el destino sólo consulta productos.
				$codes[] = array( 'ps.dyn.query_source', array( 'type' => self::slug( $source ) ) );
			}
		}
		$out[ $prefix . 'post_type' ] = $type;
		if ( 'by_id' === $type ) {
			$out[ $prefix . 'posts_ids' ] = self::ids( $s['posts_selected_ids'] ?? array() );
		}
		if ( in_array( $type, array( 'product', 'sale', 'featured' ), true ) ) {
			$include = self::lst( $s['posts_include_by'] ?? array() );
			if ( in_array( 'terms', $include, true ) ) {
				$out[ $prefix . 'include' ]          = array( 'terms' );
				$out[ $prefix . 'include_term_ids' ] = self::existing_terms( $s['posts_include_term_ids'] ?? array() );
			}
			$exclude = array_values( array_intersect( array( 'current_post', 'manual_selection', 'terms' ), self::lst( $s['posts_exclude_by'] ?? array() ) ) );
			if ( $exclude ) {
				$out[ $prefix . 'exclude' ] = $exclude;
				if ( in_array( 'manual_selection', $exclude, true ) ) {
					$out[ $prefix . 'exclude_ids' ] = self::ids( $s['posts_exclude_ids'] ?? array() );
				}
				if ( in_array( 'terms', $exclude, true ) ) {
					$out[ $prefix . 'exclude_term_ids' ] = self::existing_terms( $s['posts_exclude_term_ids'] ?? array() );
				}
			}
			if ( in_array( 'authors', $include, true ) || in_array( 'authors', self::lst( $s['posts_exclude_by'] ?? array() ), true ) ) {
				$codes[] = array( 'ps.dyn.query_authors', array() );
			}
			if ( 'anytime' !== (string) ( $s['posts_select_date'] ?? 'anytime' ) ) {
				$codes[] = array( 'ps.dyn.query_date', array() );
			}
		}
		if ( 'current_query' !== $type ) {
			$orderby = (string) ( $s['product_orderby'] ?? 'date' );
			if ( ! isset( self::PRODUCT_ORDERBY[ $orderby ] ) ) {
				$codes[] = array( 'ps.dyn.orderby', array( 'type' => self::slug( $orderby ) ) );
			}
			$out[ $prefix . 'orderby' ] = self::PRODUCT_ORDERBY[ $orderby ] ?? 'date';
			$out[ $prefix . 'order' ]   = self::order( $s['posts_order'] ?? 'desc' );
		}
		if ( 'yes' === ( $s['product_hide_free'] ?? '' ) || 'yes' === ( $s['product_hide_out_stock'] ?? '' ) ) {
			$codes[] = array( 'ps.dyn.loss.product_filters', array() );
		}
		$codes    = array_merge( $codes, self::common_codes( $s ) );
		$query_id = self::query_id( $s['query_id'] ?? '' );
		if ( '' !== $query_id ) {
			// El control de productos de PRO no tiene ID de consulta.
			$codes[] = array( 'ps.dyn.query_id', array( 'name' => $query_id ) );
		}
		return array( $out, $codes );
	}

	/**
	 * Consulta propia del calendario de eventos (familia L): `source`, `event_categories` (por slug de
	 * `tribe_events_cat`), `limit`, `orderby` y `order` → la consulta de entradas del Loop Carousel sobre `tribe_events`.
	 * Las fechas de inicio y fin no tienen equivalente (`ps.dyn.loss.event_dates` lo informa `Dynamic`).
	 *
	 * @return array{0:array, 1:array} ajustes y códigos.
	 */
	public static function events( array $s, string $prefix ): array {
		$out   = array( $prefix . 'post_type' => 'tribe_events' );
		$codes = array();
		$slugs = 'by_name' === (string) ( $s['source'] ?? '' ) ? array_values( array_filter( array_map( array( __CLASS__, 'slug' ), self::lst( $s['event_categories'] ?? array() ) ) ) ) : array();
		if ( $slugs ) {
			$ids = array();
			foreach ( $slugs as $slug ) {
				$term = function_exists( 'get_term_by' ) ? get_term_by( 'slug', $slug, 'tribe_events_cat' ) : null;
				if ( is_object( $term ) && ! empty( $term->term_taxonomy_id ) ) {
					$ids[] = (string) (int) $term->term_taxonomy_id;
				}
			}
			if ( $ids ) {
				$out[ $prefix . 'include' ]          = array( 'terms' );
				$out[ $prefix . 'include_term_ids' ] = $ids;
			} else {
				// Ninguna categoría existe: Prime Slider no mostraba nada. Falla cerrado (una selección vacía), nunca todo.
				$out[ $prefix . 'post_type' ] = 'by_id';
				$out[ $prefix . 'posts_ids' ] = array();
				$codes[]                      = array( 'ps.dyn.events_terms', array( 'count' => count( $slugs ) ) );
			}
		}
		$orderby = (string) ( $s['orderby'] ?? 'event_date' );
		$out[ $prefix . 'orderby' ] = array( 'title' => 'post_title', 'rand' => 'rand' )[ $orderby ] ?? 'post_date';
		$out[ $prefix . 'order' ]   = self::order( $s['order'] ?? 'DESC' );
		return array( $out, $codes );
	}

	/**
	 * Número de elementos de la consulta: `posts_limit` (o `limit`). 0 o vacío valía el número de entradas por página
	 * del sitio (Prime Slider no fijaba `posts_per_page`); un negativo, todas (se acota a 100).
	 */
	public static function limit( array $s, int $default, string $key = 'posts_limit' ): int {
		$value = $s[ $key ] ?? $default;
		$value = is_array( $value ) ? ( $value['size'] ?? $default ) : $value;
		$value = is_numeric( $value ) ? (int) $value : $default;
		if ( 0 === $value ) {
			$value = function_exists( 'get_option' ) ? (int) get_option( 'posts_per_page', 10 ) : 10;
		}
		if ( $value < 0 ) {
			return 100;
		}
		return max( 1, min( 100, $value ) );
	}

	/* ---------------------------------------------------------------- Auxiliares */

	/** Lo que la consulta de Prime Slider hacía y la de PRO no. */
	private static function common_codes( array $s ): array {
		$codes = array();
		if ( 'yes' === ( $s['posts_only_with_featured_image'] ?? '' ) ) {
			$codes[] = array( 'ps.dyn.loss.featured_only', array() );
		}
		return $codes;
	}

	/** IDs numéricos como cadenas (el formato de los controles de PRO), sin duplicados. */
	private static function ids( $values ): array {
		$out = array();
		foreach ( self::lst( $values ) as $value ) {
			if ( is_numeric( $value ) && (int) $value > 0 ) {
				$out[] = (string) (int) $value;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * `term_taxonomy_id` que siguen existiendo (sólo para el renderizador de productos de PRO, que lee
	 * `$term_data->taxonomy` sin comprobarlo). Sin la función de WordPress, tal cual.
	 */
	private static function existing_terms( $values ): array {
		$ids = self::ids( $values );
		if ( ! function_exists( 'get_term_by' ) ) {
			return $ids;
		}
		return array_values( array_filter( $ids, static function ( $id ) {
			return is_object( get_term_by( 'term_taxonomy_id', (int) $id ) );
		} ) );
	}

	/** Lista de un valor guardado como lista, cadena con comas o escalar. */
	private static function lst( $value ): array {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( static function ( $v ) {
				return is_scalar( $v ) ? trim( (string) $v ) : '';
			}, $value ), 'strlen' ) );
		}
		if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' ) );
		}
		return array();
	}

	private static function order( $value ): string {
		return 'asc' === strtolower( (string) $value ) ? 'asc' : 'desc';
	}

	/** Fecha del control DATE_TIME (`Y-m-d H:i` o `Y-m-d`); cualquier otra cosa, vacía. */
	private static function date( $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}(?::\d{2})?)?$/', $value ) ? $value : '';
	}

	/** ID de consulta: el mismo juego de caracteres que un nombre de acción. */
	private static function query_id( $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return 1 === preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $value ) ? $value : '';
	}

	/** Slug o nombre corto, sólo con caracteres seguros. */
	private static function slug( $value ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return 1 === preg_match( '/^[a-z0-9_-]{1,64}$/', $value ) ? $value : '';
	}
}
