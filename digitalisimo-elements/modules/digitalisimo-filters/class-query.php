<?php
namespace Digitalisimo\Elements\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Consulta de los filtros ampliados (F7b, V.5, C2-1 y C1-14): búsqueda, campo, orden y por página.
 *
 * **La petición nunca decide nada.** Cada parámetro lleva el ID del widget filtro en el nombre (`digi_s_<fid>`,
 * `digi_m_<fid>` y `digi_m_<fid>-max`, `digi_o_<fid>`, `digi_pp_<fid>`). El servidor busca ese widget en los datos
 * guardados del documento del bucle (memorizados por petición), comprueba que es del tipo del parámetro, que su destino
 * es el de la petición y que su `selected_element` es este bucle, y aplica sólo su clave, su tipo de dato, su lista de
 * valores y sus topes. Lo que no pasa se descarta en silencio: el bucle queda como sin ese filtro. Una petición REST
 * forjada (`refresh-loop` lleva `widget_filters` tal cual, `data/endpoints/refresh-loop.php:24-28`) no puede añadir una
 * clave, un `orderby` ni un `posts_per_page` que ningún widget del documento declare.
 *
 * **Documento del bucle.** Al pintar, el documento actual del núcleo (`Frontend::get_builder_content()` cambia a él
 * antes de pintar, `elementor/includes/frontend.php:1146`); en el refresco por REST, el `post_id` de la petición, que el
 * `permission_callback` de PRO ya comprobó (publicado y con el bucle dentro, `core/data/endpoints/refresh-base.php`).
 *
 * **Topes.** Un `meta_query` por widget y 4 widgets por petición (`META_PER_REQUEST`; el orden por un campo cuenta como
 * uno); `posts_per_page` de la lista del widget y ≤ 100; `orderby` de la lista del widget, sin `rand`; `s` de 2 a 100
 * caracteres. Ordenar por un campo conserva las entradas sin él (cláusula `EXISTS` o `NOT EXISTS`); por precio, con
 * WooCommerce y en bucles de productos, la tabla `wc_product_meta_lookup` con la guarda del JOIN de F4-Woo.
 *
 * **Destino «Archivo»** (C1-14, sin nonce): la URL lleva `digi_f=<documento>-<widget>`. En `pre_get_posts` (sólo si la
 * URL trae `digi_f`, sólo la consulta principal de un archivo, de la portada del blog o de la búsqueda), el documento
 * debe estar publicado y el widget debe ser uno de los cuatro con destino «Archivo»; después, cada parámetro propio se
 * comprueba contra su propio widget de ese documento, igual que con el bucle. La búsqueda con destino «Archivo» es el
 * buscador nativo (`s` hacia la página de resultados), no pasa por aquí.
 */
class Query {
	const SEARCH   = 'digitalisimo_search';
	const META     = 'digitalisimo_meta';
	const ORDERBY  = 'digitalisimo_orderby';
	const PER_PAGE = 'digitalisimo_per_page';

	/** Prefijo reservado de cada tipo (lo que sigue a `e-filter-<bucle>-`). */
	const PREFIXES = array(
		self::SEARCH   => 'digi_s_',
		self::META     => 'digi_m_',
		self::ORDERBY  => 'digi_o_',
		self::PER_PAGE => 'digi_pp_',
	);

	/** Widget que declara cada tipo. */
	const WIDGETS = array(
		self::SEARCH   => 'digitalisimo-loop-search',
		self::META     => 'digitalisimo-loop-meta-filter',
		self::ORDERBY  => 'digitalisimo-loop-orderby',
		self::PER_PAGE => 'digitalisimo-loop-per-page',
	);

	/** Sufijo del extremo superior de un rango (`digi_m_<fid>-max`): el derivado parte el nombre por el primer guion tras el ID del bucle (`module.php:220`). */
	const MAX_SUFFIX = '-max';

	const SEARCH_MIN       = 2;
	const SEARCH_MAX       = 100;
	const META_PER_REQUEST = 4;
	const PER_PAGE_MAX     = 100;
	const MAX_OPTIONS      = 50;
	const MAX_ORDERS       = 12;
	const MAX_PER_PAGE     = 10;
	const MAX_FIDS         = 20;
	const MAX_DEPTH        = 64;
	const MAX_ELEMENTS     = 5000;

	/** Opciones de orden: clave => ( `orderby`, `order`, texto ). Sin `rand`. */
	const ORDERS = array(
		'date_desc'     => array( 'date', 'DESC', 'Más recientes' ),
		'date_asc'      => array( 'date', 'ASC', 'Más antiguos' ),
		'title_asc'     => array( 'title', 'ASC', 'Título (A-Z)' ),
		'title_desc'    => array( 'title', 'DESC', 'Título (Z-A)' ),
		'modified_desc' => array( 'modified', 'DESC', 'Actualizados recientemente' ),
		'price_asc'     => array( 'price', 'ASC', 'Precio: de menor a mayor' ),
		'price_desc'    => array( 'price', 'DESC', 'Precio: de mayor a menor' ),
		'meta_asc'      => array( 'meta', 'ASC', 'Campo: de menor a mayor' ),
		'meta_desc'     => array( 'meta', 'DESC', 'Campo: de mayor a menor' ),
	);

	/** Tipo de dato del control => `type` de `meta_query`. */
	const VALUE_TYPES = array(
		'CHAR'    => 'CHAR',
		'NUMERIC' => 'NUMERIC',
		'DECIMAL' => 'DECIMAL(20,6)',
		'DATE'    => 'DATE',
	);

	/** Lista negra de V.8 (fragmentos de una clave separados por `_` o `-`), fuente única también de `F7b\Inline_Tags\Engine`; se amplía con `digitalisimo_elements_inline_tags_denied`. */
	const DENIED = array( 'pass', 'password', 'pwd', 'token', 'secret', 'key', 'hash', 'salt', 'nonce', 'email', 'mail', 'phone', 'tel', 'address', 'dni', 'rfc', 'curp', 'ssn', 'iban', 'card', 'name', 'apellido', 'nombre', 'telefono', 'celular', 'movil', 'correo', 'contrasena', 'clave', 'direccion', 'domicilio', 'tarjeta', 'nss' );

	/** Variable de consulta del orden por precio. */
	const ORDER_PRICE_VAR = 'digitalisimo_order_price';

	/** Parámetro del destino «Archivo». */
	const ARCHIVE_PARAM = 'digi_f';

	/** @var array<int,array<string,array{type:string,settings:array}>> Widgets filtro por documento, por petición. */
	private static $docs = array();

	/** @var array<string,bool> Widgets que ya usaron un `meta_query` en esta petición. */
	private static $meta_fids = array();

	/** @var int Documento del bucle en un refresco por REST. */
	private static $rest_doc = 0;

	/** @var bool */
	private static $hooked = false;

	// ---------------------------------------------------------------------------------------------------------------
	// Tipos del registro (`Types::register()`).

	/** Tipo `digitalisimo_search`. */
	public static function apply_search( array $args, array $filters, $widget = null ): array {
		return self::apply_loop( self::SEARCH, $args, $filters, $widget );
	}

	/** Tipo `digitalisimo_meta`. */
	public static function apply_meta( array $args, array $filters, $widget = null ): array {
		return self::apply_loop( self::META, $args, $filters, $widget );
	}

	/** Tipo `digitalisimo_orderby`. */
	public static function apply_orderby( array $args, array $filters, $widget = null ): array {
		return self::apply_loop( self::ORDERBY, $args, $filters, $widget );
	}

	/** Tipo `digitalisimo_per_page`. */
	public static function apply_per_page( array $args, array $filters, $widget = null ): array {
		return self::apply_loop( self::PER_PAGE, $args, $filters, $widget );
	}

	/** Con un bucle: su documento y su ID. Sin ellos, nada. */
	private static function apply_loop( string $type, array $args, array $filters, $widget ): array {
		$loop = is_object( $widget ) && method_exists( $widget, 'get_id' ) ? (string) $widget->get_id() : '';
		$doc  = self::loop_document();
		if ( ! preg_match( '/^[a-z0-9]{7}$/', $loop ) || $doc <= 0 ) {
			return $args;
		}
		return self::apply( $type, $args, $filters, array( 'doc' => $doc, 'loop' => $loop, 'destination' => 'loop', 'product' => false ) );
	}

	/**
	 * Aplica los filtros de un tipo a unos argumentos de `WP_Query`.
	 *
	 * @param string $type    Uno de los cuatro tipos.
	 * @param array  $args    Argumentos.
	 * @param array  $filters `{ nombre: { terms, logicalJoin } }`.
	 * @param array  $ctx     `doc`, `loop` (o ''), `destination` (`loop` o `archive`) y `product` (archivo de productos).
	 */
	public static function apply( string $type, array $args, array $filters, array $ctx ): array {
		if ( ! isset( self::PREFIXES[ $type ] ) ) {
			return $args;
		}
		$by_fid = array();
		$re     = '/^' . preg_quote( self::PREFIXES[ $type ], '/' ) . '([a-z0-9]{7,8})(' . preg_quote( self::MAX_SUFFIX, '/' ) . ')?$/';
		foreach ( $filters as $name => $filter ) {
			if ( ! is_string( $name ) || ! is_array( $filter ) || ! preg_match( $re, $name, $m ) || ( ! empty( $m[2] ) && self::META !== $type ) ) {
				continue;
			}
			if ( ! isset( $by_fid[ $m[1] ] ) && count( $by_fid ) >= self::MAX_FIDS ) {
				continue;
			}
			$by_fid[ $m[1] ][ empty( $m[2] ) ? 'value' : 'max' ] = $filter;
		}
		foreach ( $by_fid as $fid => $parts ) {
			$settings = self::target( $ctx, (string) $fid, $type );
			if ( null === $settings ) {
				continue;
			}
			if ( self::SEARCH === $type ) {
				$args = self::search( $args, $parts );
			} elseif ( self::META === $type ) {
				$args = self::meta( $args, $parts, $settings, (string) $fid );
			} elseif ( self::ORDERBY === $type ) {
				$args = self::orderby( $args, $parts, $settings, (string) $fid, ! empty( $ctx['product'] ) );
			} else {
				$args = self::per_page( $args, $parts, $settings );
			}
		}
		return $args;
	}

	/**
	 * Ajustes normalizados del widget `<fid>` si es del tipo, del destino y del bucle de la petición; si no, `null`.
	 *
	 * @param array  $ctx  Contexto de `apply()`.
	 * @param string $fid  ID del widget filtro.
	 * @param string $type Tipo del parámetro.
	 */
	public static function target( array $ctx, string $fid, string $type ): ?array {
		$found = self::widget( (int) ( isset( $ctx['doc'] ) ? $ctx['doc'] : 0 ), $fid );
		if ( null === $found || ! isset( self::WIDGETS[ $type ] ) || self::WIDGETS[ $type ] !== $found['type'] ) {
			return null;
		}
		$settings    = self::normalize( $found['type'], $found['settings'] );
		$destination = isset( $ctx['destination'] ) ? $ctx['destination'] : 'loop';
		if ( $settings['destination'] !== $destination ) {
			return null;
		}
		if ( 'loop' === $destination && ( '' === $settings['selected_element'] || ( isset( $ctx['loop'] ) ? $ctx['loop'] : '' ) !== $settings['selected_element'] ) ) {
			return null;
		}
		return $settings;
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Cada tipo.

	/** `s` del widget de búsqueda: el texto de 2 a 100 caracteres. El orden del bucle no se toca. */
	private static function search( array $args, array $parts ): array {
		$text = self::search_text( isset( $parts['value'] ) ? $parts['value'] : array() );
		if ( '' !== $text ) {
			$args['s'] = $text;
		}
		return $args;
	}

	/**
	 * Texto de una búsqueda tal como llega: la URL lo parte por espacios (o por `~`) y el refresco lo manda entero.
	 *
	 * @param mixed $filter `{ terms, logicalJoin }`.
	 */
	public static function search_text( $filter ): string {
		$terms = self::terms( $filter, 20 );
		$glue  = is_array( $filter ) && isset( $filter['logicalJoin'] ) && 'OR' === $filter['logicalJoin'] ? '~' : ' ';
		$text  = trim( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( implode( $glue, $terms ) ) : implode( $glue, $terms ) );
		$len   = self::length( $text );
		return $len >= self::SEARCH_MIN && $len <= self::SEARCH_MAX ? $text : '';
	}

	/** Un `meta_query` del widget de campo, si quedan de los 4 de la petición. */
	private static function meta( array $args, array $parts, array $s, string $fid ): array {
		if ( '' === $s['key'] ) {
			return $args;
		}
		$clause = self::meta_clause( $s, $parts );
		if ( null === $clause || ! self::claim_meta( $fid ) ) {
			return $args;
		}
		return self::merge_meta( $args, $clause );
	}

	/**
	 * Cláusula de `meta_query` de un widget de campo, o `null` si nada de lo recibido es válido.
	 *
	 * @param array $s     Ajustes normalizados.
	 * @param array $parts `value` (y `max` en un rango), cada uno `{ terms, logicalJoin }`.
	 */
	public static function meta_clause( array $s, array $parts ): ?array {
		$base = array( 'key' => $s['key'], 'type' => $s['cast'] );
		if ( 'range' === $s['mode'] ) {
			$min = self::typed( self::first( isset( $parts['value'] ) ? $parts['value'] : null ), $s['value_type'] );
			$max = self::typed( self::first( isset( $parts['max'] ) ? $parts['max'] : null ), $s['value_type'] );
			$min = null === $min ? null : self::clamp( $min, $s );
			$max = null === $max ? null : self::clamp( $max, $s );
			if ( null !== $min && null !== $max && self::compare( $min, $max, $s['value_type'] ) > 0 ) {
				list( $min, $max ) = array( $max, $min );
			}
			if ( null !== $min && null !== $max ) {
				return $base + array( 'value' => array( $min, $max ), 'compare' => 'BETWEEN' );
			}
			if ( null !== $min ) {
				return $base + array( 'value' => $min, 'compare' => '>=' );
			}
			return null === $max ? null : $base + array( 'value' => $max, 'compare' => '<=' );
		}
		$values = array();
		foreach ( self::list_terms( isset( $parts['value'] ) ? $parts['value'] : null ) as $token ) {
			if ( isset( $s['options'][ $token ] ) && ! in_array( $s['options'][ $token ]['value'], $values, true ) ) {
				$values[] = $s['options'][ $token ]['value'];
			}
		}
		if ( ! $values ) {
			return null;
		}
		if ( 'equal' === $s['mode'] ) {
			return $base + array( 'value' => $values[0], 'compare' => '=' );
		}
		return $base + array( 'value' => $values, 'compare' => 'IN' );
	}

	/** Orden de la lista del widget. */
	private static function orderby( array $args, array $parts, array $s, string $fid, bool $product ): array {
		$key = self::first( isset( $parts['value'] ) ? $parts['value'] : null );
		if ( ! is_string( $key ) || ! isset( $s['orders'][ $key ], self::ORDERS[ $key ] ) ) {
			return $args;
		}
		list( $field, $order ) = self::ORDERS[ $key ];
		if ( 'price' === $field ) {
			if ( ! self::price_available( $args, $product ) ) {
				return $args;
			}
			$args['orderby']               = 'none';
			$args['order']                 = $order;
			$args[ self::ORDER_PRICE_VAR ] = $order;
			self::hook();
			return $args;
		}
		if ( 'meta' === $field ) {
			if ( '' === $s['meta_key'] || ! self::claim_meta( $fid ) ) {
				return $args;
			}
			$name = 'digi_order_' . $fid;
			$args = self::merge_meta(
				$args,
				array(
					'relation' => 'OR',
					$name      => array( 'key' => $s['meta_key'], 'compare' => 'EXISTS', 'type' => self::VALUE_TYPES['DECIMAL'] ),
					array( 'key' => $s['meta_key'], 'compare' => 'NOT EXISTS' ),
				)
			);
			unset( $args[ self::ORDER_PRICE_VAR ] );
			$args['orderby'] = array( $name => $order );
			$args['order']   = $order;
			return $args;
		}
		unset( $args[ self::ORDER_PRICE_VAR ] );
		$args['orderby'] = $field;
		$args['order']   = $order;
		return $args;
	}

	/** `posts_per_page` de la lista del widget (≤ 100). */
	private static function per_page( array $args, array $parts, array $s ): array {
		$raw = self::first( isset( $parts['value'] ) ? $parts['value'] : null );
		if ( ! is_string( $raw ) || ! preg_match( '/^\d{1,3}$/', $raw ) ) {
			return $args;
		}
		$value = (int) $raw;
		if ( in_array( $value, $s['values'], true ) && $value >= 1 && $value <= self::PER_PAGE_MAX ) {
			$args['posts_per_page'] = $value;
		}
		return $args;
	}

	/** ¿Se puede ordenar por precio? Con WooCommerce y en un bucle o un archivo de productos. */
	private static function price_available( array $args, bool $product ): bool {
		if ( ! class_exists( 'WooCommerce', false ) ) {
			return false;
		}
		$type = isset( $args['post_type'] ) ? $args['post_type'] : '';
		return $product || 'product' === $type || ( is_array( $type ) && array( 'product' ) === array_values( $type ) );
	}

	/**
	 * Añade una cláusula a `meta_query` sin cambiar el sentido de las que ya hubiera: dentro de un grupo `AND`, al
	 * final; si el grupo es `OR`, el grupo entero y la cláusula, en uno `AND`.
	 *
	 * @param array $args   Argumentos.
	 * @param array $clause Cláusula o grupo.
	 */
	public static function merge_meta( array $args, array $clause ): array {
		$existing = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		if ( ! $existing ) {
			$args['meta_query'] = array( 'relation' => 'AND', $clause ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			return $args;
		}
		$relation = isset( $existing['relation'] ) && is_string( $existing['relation'] ) ? strtoupper( $existing['relation'] ) : 'AND';
		if ( 'AND' === $relation ) {
			$existing[]         = $clause;
			$args['meta_query'] = $existing; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			return $args;
		}
		$args['meta_query'] = array( 'relation' => 'AND', $existing, $clause ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		return $args;
	}

	/** Reserva uno de los 4 `meta_query` de la petición para un widget (el mismo widget no gasta otro). */
	private static function claim_meta( string $fid ): bool {
		if ( isset( self::$meta_fids[ $fid ] ) ) {
			return true;
		}
		if ( count( self::$meta_fids ) >= self::META_PER_REQUEST ) {
			return false;
		}
		self::$meta_fids[ $fid ] = true;
		return true;
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Ajustes guardados de los widgets.

	/**
	 * Ajustes de un widget filtro con los valores por defecto de sus controles, saneados. La misma función sirve al
	 * pintar el widget y al aplicar sus parámetros.
	 *
	 * @param string $widget Nombre del widget.
	 * @param mixed  $raw    Ajustes guardados.
	 */
	public static function normalize( string $widget, $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();
		$get = function ( $key, $default = '' ) use ( $raw ) {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( (string) $raw[ $key ] ) : $default;
		};
		$out = array(
			'destination'      => 'archive' === $get( 'destination', 'loop' ) ? 'archive' : 'loop',
			'selected_element' => preg_match( '/^[a-z0-9]{7}$/', $get( 'selected_element' ) ) ? $get( 'selected_element' ) : '',
		);
		if ( self::WIDGETS[ self::META ] === $widget ) {
			$type                = $get( 'value_type', 'CHAR' );
			$type                = isset( self::VALUE_TYPES[ $type ] ) ? $type : 'CHAR';
			$mode                = $get( 'mode', 'equal' );
			$mode                = in_array( $mode, array( 'equal', 'in', 'range' ), true ) ? $mode : 'equal';
			$out['key']          = self::meta_key( '__custom' === $get( 'meta_key' ) ? $get( 'meta_key_custom' ) : $get( 'meta_key' ) );
			$out['value_type']   = $type;
			$out['cast']         = self::VALUE_TYPES[ $type ];
			$out['mode']         = 'range' === $mode && 'CHAR' === $type ? 'equal' : $mode;
			$out['options']      = self::options( isset( $raw['options'] ) ? $raw['options'] : array(), $type );
			$out['min']          = self::typed( $get( 'range_min' ), $type );
			$out['max']          = self::typed( $get( 'range_max' ), $type );
			if ( null !== $out['min'] && null !== $out['max'] && self::compare( $out['min'], $out['max'], $type ) > 0 ) {
				list( $out['min'], $out['max'] ) = array( $out['max'], $out['min'] );
			}
		} elseif ( self::WIDGETS[ self::ORDERBY ] === $widget ) {
			$out['meta_key'] = self::meta_key( '__custom' === $get( 'order_meta_key' ) ? $get( 'order_meta_key_custom' ) : $get( 'order_meta_key' ) );
			$out['orders']   = self::orders( array_key_exists( 'orders', $raw ) ? $raw['orders'] : self::default_orders(), '' !== $out['meta_key'] );
		} elseif ( self::WIDGETS[ self::PER_PAGE ] === $widget ) {
			$out['values'] = self::per_page_values( $get( 'values', '12, 24, 48' ) );
		}
		return $out;
	}

	/** Filas por defecto del repetidor de órdenes (las mismas que el control). */
	public static function default_orders(): array {
		$rows = array();
		foreach ( array( 'date_desc', 'title_asc', 'title_desc' ) as $key ) {
			$rows[] = array( 'order_key' => $key, 'label' => self::ORDERS[ $key ][2] );
		}
		return $rows;
	}

	/**
	 * Valores del widget de campo: `ficha => { value, label }`. La ficha es la que viaja en la URL (sin espacios, `~` ni
	 * comas, que son separadores); el valor, el que se compara.
	 *
	 * @param mixed  $rows Filas del repetidor.
	 * @param string $type Tipo de dato.
	 */
	public static function options( $rows, string $type ): array {
		$out = array();
		$i   = 0;
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( count( $out ) >= self::MAX_OPTIONS ) {
				break;
			}
			$i++;
			$value = is_array( $row ) && isset( $row['value'] ) && is_scalar( $row['value'] ) ? trim( (string) $row['value'] ) : '';
			if ( '' === $value || self::length( $value ) > 200 || ( 'CHAR' !== $type && null === self::typed( $value, $type ) ) ) {
				continue;
			}
			if ( 'CHAR' !== $type ) {
				$value = (string) self::typed( $value, $type );
			}
			$label = is_array( $row ) && isset( $row['label'] ) && is_scalar( $row['label'] ) ? trim( (string) $row['label'] ) : '';
			$token = function_exists( 'sanitize_title' ) ? (string) sanitize_title( $value ) : strtolower( $value );
			if ( ! preg_match( '/^[a-z0-9_\-]{1,60}$/', $token ) || isset( $out[ $token ] ) ) {
				$token = 'v' . $i;
			}
			$out[ $token ] = array( 'value' => $value, 'label' => '' !== $label ? $label : $value );
		}
		return $out;
	}

	/**
	 * Órdenes del widget: `clave => texto`, sin repetir y de la lista cerrada (sin `rand`; los de campo, sólo con clave).
	 *
	 * @param mixed $rows     Filas del repetidor.
	 * @param bool  $has_meta El widget tiene una clave válida.
	 */
	public static function orders( $rows, bool $has_meta ): array {
		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$key = is_array( $row ) && isset( $row['order_key'] ) && is_string( $row['order_key'] ) ? $row['order_key'] : '';
			if ( ! isset( self::ORDERS[ $key ] ) || isset( $out[ $key ] ) || ( 'meta' === self::ORDERS[ $key ][0] && ! $has_meta ) || count( $out ) >= self::MAX_ORDERS ) {
				continue;
			}
			$label       = isset( $row['label'] ) && is_scalar( $row['label'] ) ? trim( (string) $row['label'] ) : '';
			$out[ $key ] = '' !== $label ? $label : self::ORDERS[ $key ][2];
		}
		return $out;
	}

	/** Valores de «Por página»: enteros de 1 a 100, sin repetir, como mucho 10, en el orden escrito. */
	public static function per_page_values( string $text ): array {
		$out = array();
		foreach ( preg_split( '/[\s,;]+/', $text ) as $part ) {
			if ( preg_match( '/^\d{1,3}$/', $part ) && (int) $part >= 1 && (int) $part <= self::PER_PAGE_MAX && ! in_array( (int) $part, $out, true ) && count( $out ) < self::MAX_PER_PAGE ) {
				$out[] = (int) $part;
			}
		}
		return $out;
	}

	/**
	 * Clave de un campo que se puede filtrar u ordenar, o ''. Nunca protegida (`_*` o `is_protected_meta()`) ni con un
	 * fragmento de la lista negra de V.8.
	 *
	 * @param mixed $raw Clave.
	 */
	public static function meta_key( $raw ): string {
		$key = trim( is_scalar( $raw ) ? (string) $raw : '' );
		if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9_\-]{0,63}$/', $key ) ) {
			return '';
		}
		if ( function_exists( 'is_protected_meta' ) && is_protected_meta( $key, 'post' ) ) {
			return '';
		}
		return self::denied( $key ) ? '' : $key;
	}

	/**
	 * ¿Tiene el nombre un fragmento de la lista negra? Fragmentos separados por `_` o `-`, sin distinguir mayúsculas
	 * (`api_key` sí, `keywords` no).
	 *
	 * @param string $name Nombre.
	 */
	public static function denied( string $name ): bool {
		$list      = function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_elements_inline_tags_denied', self::DENIED ) : self::DENIED;
		$fragments = array_filter( preg_split( '/[_\-]+/', strtolower( $name ) ) );
		foreach ( is_array( $list ) ? $list : self::DENIED as $item ) {
			if ( is_scalar( $item ) && in_array( strtolower( trim( (string) $item ) ), $fragments, true ) ) {
				return true;
			}
		}
		return false;
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Valores.

	/**
	 * Términos recibidos como cadenas cortas (como mucho `$max`).
	 *
	 * @param mixed $filter `{ terms, logicalJoin }`.
	 * @param int   $max    Tope.
	 * @return string[]
	 */
	public static function terms( $filter, int $max = 50 ): array {
		$terms = is_array( $filter ) && isset( $filter['terms'] ) ? $filter['terms'] : array();
		$terms = is_array( $terms ) ? $terms : array( $terms );
		$out   = array();
		foreach ( $terms as $term ) {
			if ( is_scalar( $term ) && strlen( (string) $term ) <= 400 && count( $out ) < $max ) {
				$out[] = (string) $term;
			}
		}
		return $out;
	}

	/**
	 * Fichas de una lista: con `OR`, cada término; si no, un único valor (la URL parte por espacios lo que el refresco
	 * manda entero, y una ficha nunca tiene espacios).
	 *
	 * @param mixed $filter `{ terms, logicalJoin }`.
	 * @return string[]
	 */
	public static function list_terms( $filter ): array {
		$terms = self::terms( $filter );
		if ( ! $terms ) {
			return array();
		}
		if ( is_array( $filter ) && isset( $filter['logicalJoin'] ) && 'OR' === $filter['logicalJoin'] ) {
			return array_values( array_filter( array_map( 'trim', $terms ), 'strlen' ) );
		}
		$one = trim( implode( ' ', $terms ) );
		return '' === $one ? array() : array( $one );
	}

	/**
	 * Primer término (la URL deja `terms: [ '10' ]`; el refresco, igual).
	 *
	 * @param mixed $filter `{ terms, logicalJoin }`.
	 * @return string|null
	 */
	public static function first( $filter ): ?string {
		$terms = self::terms( $filter, 1 );
		return $terms ? trim( $terms[0] ) : null;
	}

	/**
	 * Primer término de un nombre, como lo dejan la URL (`terms: [ '10' ]`) y el refresco (F4-Woo, V.8.4).
	 *
	 * @param array  $filters Filtros del tipo.
	 * @param string $name    Nombre.
	 * @return mixed
	 */
	protected static function first_term( array $filters, string $name ) {
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
	 * Valor tipado: número (con punto o una coma decimal) o fecha `AAAA-MM-DD`; texto tal cual. Inválido: `null`.
	 *
	 * @param mixed  $raw  Valor.
	 * @param string $type `CHAR`, `NUMERIC`, `DECIMAL` o `DATE`.
	 * @return string|null
	 */
	public static function typed( $raw, string $type ): ?string {
		if ( ! is_scalar( $raw ) ) {
			return null;
		}
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return null;
		}
		if ( 'DATE' === $type ) {
			return preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $raw : null;
		}
		if ( 'CHAR' === $type ) {
			return self::length( $raw ) <= 200 ? $raw : null;
		}
		if ( false === strpos( $raw, '.' ) && 1 === substr_count( $raw, ',' ) ) {
			$raw = str_replace( ',', '.', $raw );
		}
		if ( ! preg_match( '/^-?(?:\d{1,15}(?:\.\d{0,8})?|\.\d{1,8})$/', $raw ) ) {
			return null;
		}
		if ( 'NUMERIC' === $type ) {
			return (string) (int) round( (float) $raw );
		}
		$text = rtrim( rtrim( number_format( (float) $raw, 6, '.', '' ), '0' ), '.' );
		return '' === $text || '-0' === $text ? '0' : $text;
	}

	/** Compara dos valores tipados (números o fechas `AAAA-MM-DD`). */
	private static function compare( string $a, string $b, string $type ): int {
		if ( 'DATE' === $type || 'CHAR' === $type ) {
			return strcmp( $a, $b );
		}
		return (float) $a <=> (float) $b;
	}

	/** Lleva un valor de un rango a los límites del widget. */
	private static function clamp( string $value, array $s ): string {
		if ( null !== $s['min'] && self::compare( $value, $s['min'], $s['value_type'] ) < 0 ) {
			return $s['min'];
		}
		if ( null !== $s['max'] && self::compare( $value, $s['max'], $s['value_type'] ) > 0 ) {
			return $s['max'];
		}
		return $value;
	}

	/** Longitud en caracteres. */
	private static function length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Documentos.

	/** Documento del bucle que se pinta: el del refresco por REST o el actual del núcleo. */
	public static function loop_document(): int {
		if ( self::$rest_doc > 0 ) {
			return self::$rest_doc;
		}
		$plugin = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		$docs   = is_object( $plugin ) && isset( $plugin->documents ) ? $plugin->documents : null;
		$doc    = is_object( $docs ) && method_exists( $docs, 'get_current' ) ? $docs->get_current() : null;
		$post   = is_object( $doc ) && method_exists( $doc, 'get_post' ) ? $doc->get_post() : null;
		return is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
	}

	/**
	 * `rest_request_before_callbacks`: en `refresh-loop`, el documento del bucle es el `post_id` de la petición (el
	 * `permission_callback` de PRO ya comprobó que está publicado, o que quien edita puede editarlo, y que tiene el bucle).
	 *
	 * @param mixed  $response Respuesta (se devuelve tal cual).
	 * @param mixed  $handler  Manejador.
	 * @param object $request  `WP_REST_Request`.
	 * @return mixed
	 */
	public static function rest_document( $response, $handler = null, $request = null ) {
		if ( is_object( $request ) && method_exists( $request, 'get_route' ) && method_exists( $request, 'get_param' ) && '/elementor-pro/v1/refresh-loop' === $request->get_route() ) {
			$id             = $request->get_param( 'post_id' );
			self::$rest_doc = is_numeric( $id ) && (int) $id > 0 ? (int) $id : 0;
		}
		return $response;
	}

	/**
	 * Widget filtro `<fid>` de un documento: `{ type, settings }`, o `null`.
	 *
	 * @param int    $doc Documento.
	 * @param string $fid ID del widget.
	 */
	public static function widget( int $doc, string $fid ): ?array {
		if ( $doc <= 0 || ! preg_match( '/^[a-z0-9]{7,8}$/', $fid ) ) {
			return null;
		}
		if ( ! isset( self::$docs[ $doc ] ) ) {
			self::$docs[ $doc ] = self::index( self::elements( $doc ) );
		}
		return isset( self::$docs[ $doc ][ $fid ] ) ? self::$docs[ $doc ][ $fid ] : null;
	}

	/** Árbol de elementos guardado de un documento (`_elementor_data`). */
	private static function elements( int $doc ): array {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return array();
		}
		$raw = get_post_meta( $doc, '_elementor_data', true );
		if ( is_string( $raw ) && '' !== $raw ) {
			if ( false === strpos( $raw, '"digitalisimo-loop-' ) ) {
				return array();
			}
			$raw = json_decode( $raw, true );
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Índice `fid => { type, settings }` de los cuatro widgets filtro de un árbol, con topes de profundidad y tamaño.
	 *
	 * @param array $elements Árbol.
	 */
	public static function index( array $elements ): array {
		$out   = array();
		$names = array_flip( self::WIDGETS );
		$stack = array( array( $elements, 0 ) );
		$seen  = 0;
		while ( $stack ) {
			list( $list, $depth ) = array_pop( $stack );
			foreach ( $list as $element ) {
				if ( ! is_array( $element ) || ++$seen > self::MAX_ELEMENTS ) {
					continue;
				}
				$type = isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : '';
				$id   = isset( $element['id'] ) && is_string( $element['id'] ) ? $element['id'] : '';
				if ( isset( $names[ $type ] ) && preg_match( '/^[a-z0-9]{7,8}$/', $id ) && ! isset( $out[ $id ] ) ) {
					$out[ $id ] = array( 'type' => $type, 'settings' => isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array() );
				}
				if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) && $depth < self::MAX_DEPTH ) {
					$stack[] = array( $element['elements'], $depth + 1 );
				}
			}
		}
		return $out;
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Destino «Archivo».

	/**
	 * `pre_get_posts` (20): con `digi_f` válido, los filtros propios en la consulta principal del archivo.
	 *
	 * @param object $query `WP_Query`.
	 */
	public static function archive( $query ): void {
		if ( ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! $query->is_main_query() || ( function_exists( 'is_admin' ) && is_admin() ) ) {
			return;
		}
		if ( $query->is_feed() || ! ( $query->is_archive() || $query->is_home() || $query->is_search() ) ) {
			return;
		}
		$ref = self::archive_reference();
		if ( null === $ref ) {
			return;
		}
		$found = self::widget( $ref['doc'], $ref['fid'] );
		if ( null === $found || 'archive' !== self::normalize( $found['type'], $found['settings'] )['destination'] ) {
			return;
		}
		$product = class_exists( 'WooCommerce', false ) && ( $query->is_post_type_archive( 'product' ) || ( function_exists( 'get_object_taxonomies' ) && $query->is_tax( get_object_taxonomies( 'product' ) ) ) );
		$ctx     = array( 'doc' => $ref['doc'], 'loop' => '', 'destination' => 'archive', 'product' => $product );
		$args    = is_array( $query->query_vars ) ? $query->query_vars : array();
		$before  = $args;
		foreach ( self::archive_filters() as $type => $filters ) {
			$args = self::apply( $type, $args, $filters, $ctx );
		}
		foreach ( array( 'meta_query', 'orderby', 'order', 'posts_per_page', self::ORDER_PRICE_VAR ) as $key ) {
			$now = array_key_exists( $key, $args ) ? $args[ $key ] : null;
			$was = array_key_exists( $key, $before ) ? $before[ $key ] : null;
			if ( $now !== $was ) {
				$query->set( $key, null === $now ? '' : $now );
			}
		}
	}

	/** `digi_f=<documento>-<widget>` de la URL, si el documento está publicado; si no, `null`. */
	public static function archive_reference(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro público de sólo lectura; lo que aplica sale del widget guardado.
		$raw = isset( $_GET[ self::ARCHIVE_PARAM ] ) && is_string( $_GET[ self::ARCHIVE_PARAM ] ) ? $_GET[ self::ARCHIVE_PARAM ] : '';
		if ( ! preg_match( '/^([1-9]\d{0,19})-([a-z0-9]{7,8})$/', $raw, $m ) ) {
			return null;
		}
		$doc = (int) $m[1];
		if ( ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $doc ) ) {
			return null;
		}
		return array( 'doc' => $doc, 'fid' => $m[2] );
	}

	/**
	 * Parámetros propios de la URL agrupados por tipo, con las listas unidas por comas (las de un formulario GET).
	 *
	 * @return array<string,array<string,array{terms:string[],logicalJoin:string}>>
	 */
	public static function archive_filters(): array {
		$out   = array();
		$count = 0;
		$types = array_flip( self::PREFIXES );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro público de sólo lectura.
		foreach ( isset( $_GET ) && is_array( $_GET ) ? $_GET : array() as $name => $value ) {
			if ( ++$count > 50 || ! is_string( $name ) || ! is_string( $value ) || strlen( $value ) > 2000 || ! preg_match( '/^(digi_(?:m|o|pp)_)[a-z0-9]{7,8}(?:-max)?$/', $name, $m ) ) {
				continue;
			}
			$value = function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : $value;
			$out[ $types[ $m[1] ] ][ $name ] = array( 'terms' => array_slice( explode( ',', $value ), 0, self::MAX_OPTIONS ), 'logicalJoin' => 'OR' );
		}
		return $out;
	}

	// ---------------------------------------------------------------------------------------------------------------
	// Orden por precio.

	/** Engancha `posts_clauses` la primera vez que una consulta ordena por precio. */
	private static function hook(): void {
		if ( self::$hooked || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$hooked = true;
		add_filter( 'posts_clauses', array( __CLASS__, 'order_clauses' ), 10, 2 );
	}

	/**
	 * `posts_clauses`: sólo en las consultas con la variable propia; el orden de la tienda de WooCommerce
	 * (`WC_Query::order_by_price_asc_post_clauses()`/`…_desc_…`) sobre `wc_product_meta_lookup`.
	 *
	 * @param mixed  $clauses Cláusulas.
	 * @param object $query   `WP_Query`.
	 * @return mixed
	 */
	public static function order_clauses( $clauses, $query = null ) {
		if ( ! is_array( $clauses ) || ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return $clauses;
		}
		$order = $query->get( self::ORDER_PRICE_VAR );
		if ( 'ASC' !== $order && 'DESC' !== $order ) {
			return $clauses;
		}
		$clauses['join']    = self::append_join( isset( $clauses['join'] ) ? $clauses['join'] : '' );
		$clauses['orderby'] = 'ASC' === $order
			? ' wc_product_meta_lookup.min_price ASC, wc_product_meta_lookup.product_id ASC '
			: ' wc_product_meta_lookup.max_price DESC, wc_product_meta_lookup.product_id DESC ';
		return $clauses;
	}

	/**
	 * JOIN de `wc_product_meta_lookup` con su alias, salvo que la cláusula ya lo nombre: la guarda de
	 * `ProductUtil::append_product_sorting_table_join()` de 11.2 (`W:src/Internal/Utilities/ProductUtil.php:150-168`).
	 * La usan el filtro de precio de F4-Woo y el orden por precio.
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

	/** Para las pruebas: olvida documentos, cupos, el documento del refresco y el gancho. */
	public static function reset(): void {
		self::$docs      = array();
		self::$meta_fids = array();
		self::$rest_doc  = 0;
		self::$hooked    = false;
	}
}
