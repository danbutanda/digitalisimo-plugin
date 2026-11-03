<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Condiciones del Theme Builder de Element Pack traducidas a `_elementor_conditions` del Theme Builder de DIGITALÍSIMO
 * Elements (F8, frente G; §3.D.1 y research-ep §2.1.3, §2.1.7 y §2.1.8).
 *
 * Tres piezas, en este orden:
 *
 * 1. **Filas de origen** (`rows()`): el JSON de `_bdthemes_builder_template_conditions` o, si falta o no se puede leer,
 *    la fila heredada de las metas `_bdthemes_builder_template_condition_*` con el valor por defecto del tipo, como
 *    `Template_Conditions::get_rows()` de Element Pack. La subcondición vacía vale `all` desde 10.x y **no coincide**
 *    con nada en 9.9.1 (`normalize_rows()` de cada versión).
 * 2. **Ámbito del tipo** (`kind()` y `scope_rule()`): Element Pack sólo consulta cada tipo en ciertas peticiones
 *    (`builder-integration.php:1210-1451`), más estrechas que la ubicación de destino. Una fila que nunca puede
 *    cumplirse en ese ámbito se omite (`never`); una genérica se reduce al ámbito si hay equivalente exacto
 *    (`singular/all` en una plantilla de página → `singular/page`); una más amplia sin reducción posible se omite si
 *    es de inclusión (`drop`).
 * 3. **Tabla fila → destino** (`SINGULAR`, `ARCHIVE`, `WOO` y los prefijos `pt_`, `_archive` y `woo_tax_`) con su
 *    calidad. Regla general (fallar cerrado): una inclusión se traduce sólo si el destino es igual o menor; una
 *    exclusión, sólo si es igual o mayor; si una exclusión no se puede traducir, la plantilla queda sin condiciones.
 *
 * Después se añaden las exclusiones que acotan el ámbito (`post|single`: páginas, 404 y adjuntos; `page|single`: las
 * páginas de WooCommerce y las que usan una plantilla PHP del tema; `post|archive`: la búsqueda y las taxonomías
 * propias de las entradas; `product|shop`: la búsqueda de productos). Un ID que ya no existe se descarta; si todos
 * faltan, la fila nunca se cumplía.
 *
 * Todo es lectura: nada aquí escribe.
 */
final class Tb_Conditions {
	const META_CONDITIONS = '_bdthemes_builder_template_conditions';
	const META_LEGACY     = '_bdthemes_builder_template_condition_';
	const SEP             = '|';

	/** Tope de exclusiones por ID (páginas) que se añaden a una plantilla; más, y queda en borrador. */
	const MAX_EXCLUDED_IDS = 100;

	/** Tipos heredados → tipo consolidado (`builder-template-helper.php:181-203`). */
	const ALIASES = array(
		'post|category'    => 'post|archive',
		'post|tag'         => 'post|archive',
		'post|author'      => 'post|archive',
		'post|date'        => 'post|archive',
		'product|archive'  => 'product|shop',
		'product|category' => 'product|shop',
		'product|tag'      => 'product|shop',
	);

	/** Fila por defecto de cada tipo (`template-conditions.php:100-170`): condition_a, singular, archive, woo. */
	const DEFAULT_ROWS = array(
		'themes|header'     => array( 'entire_site', 'all', 'all', 'all' ),
		'themes|footer'     => array( 'entire_site', 'all', 'all', 'all' ),
		'post|single'       => array( 'singular', 'all', 'all', 'all' ),
		'page|single'       => array( 'singular', 'all_pages', 'all', 'all' ),
		'post|archive'      => array( 'archive', 'all', 'all', 'all' ),
		'page|search'       => array( 'archive', 'all', 'search', 'all' ),
		'product|single'    => array( 'woocommerce', 'all', 'all', 'single_product' ),
		'product|shop'      => array( 'woocommerce', 'all', 'all', 'product_archive' ),
		'product|archive'   => array( 'woocommerce', 'all', 'all', 'product_archive' ),
		'product|cart'      => array( 'woocommerce', 'all', 'all', 'cart' ),
		'product|checkout'  => array( 'woocommerce', 'all', 'all', 'checkout' ),
		'product|myaccount' => array( 'woocommerce', 'all', 'all', 'myaccount' ),
		'product|orders'    => array( 'woocommerce', 'all', 'all', 'orders' ),
		'product|thankyou'  => array( 'woocommerce', 'all', 'all', 'thankyou' ),
		'page|404'          => array( 'singular', 'not_found404', 'all', 'all' ),
	);

	/** Páginas de WooCommerce sin tipo de plantilla equivalente (§2.1.9) y la página a la que se asignarían. */
	const WOO_PAGES = array(
		'product|cart'      => 'cart',
		'product|checkout'  => 'checkout',
		'product|myaccount' => 'myaccount',
		'product|orders'    => 'myaccount',
		'product|thankyou'  => 'checkout',
	);

	/**
	 * Tabla `singular/<sub>` → destino (research-ep §2.1.7). `ids`: qué son los IDs (`post`, `term` o `user`); `of`: tipo
	 * de entrada o taxonomía que deben tener; `need`: sin IDs la fila nunca se cumplía en Element Pack.
	 */
	const SINGULAR = array(
		'all'                  => array( 'pro' => 'singular' ),
		'front_page'           => array( 'pro' => 'singular/front_page' ),
		'not_found404'         => array( 'pro' => 'singular/not_found404' ),
		'404page'              => array( 'pro' => 'singular/not_found404' ),
		'all_posts'            => array( 'pro' => 'singular/post', 'ids' => 'post', 'of' => 'post' ),
		'all_pages'            => array( 'pro' => 'singular/page', 'ids' => 'post', 'of' => 'page' ),
		'attachment'           => array( 'pro' => 'singular/attachment', 'ids' => 'post', 'of' => 'attachment' ),
		'in_category'          => array( 'pro' => 'singular/in_category', 'ids' => 'term', 'of' => 'category', 'need' => true ),
		'in_category_children' => array( 'pro' => 'singular/in_category_children', 'ids' => 'term', 'of' => 'category', 'need' => true ),
		'in_post_tag'          => array( 'pro' => 'singular/in_post_tag', 'ids' => 'term', 'of' => 'post_tag', 'need' => true ),
		'post_by_author'       => array( 'pro' => 'singular/post_by_author', 'ids' => 'user', 'need' => true ),
		'page_by_author'       => array( 'pro' => 'singular/page_by_author', 'ids' => 'user', 'need' => true ),
		'attachment_by_author' => array( 'pro' => 'singular/attachment_by_author', 'ids' => 'user', 'need' => true ),
		// Element Pack excluye los adjuntos y el destino no: ver by_author() (§2.1.7).
		'by_author'            => array( 'pro' => 'singular/by_author', 'ids' => 'user', 'need' => true, 'attachments' => true ),
		// Sin IDs: cualquier hijo (0 en el destino, `child-of.php:28-37`).
		'child_of'             => array( 'pro' => 'singular/child_of', 'ids' => 'post' ),
		'any_child_of'         => array( 'pro' => 'singular/any_child_of', 'ids' => 'post', 'need' => true ),
		// Un ID de cualquier tipo: `singular/<tipo del ID>/<ID>`.
		'selective'            => array( 'pro' => '{type}', 'ids' => 'post', 'need' => true ),
	);

	/** Tabla `archive/<sub>` → destino. `need => 'none'`: sin IDs coincidía con algo que el destino no puede expresar. */
	const ARCHIVE = array(
		'all'                => array( 'pro' => 'archive' ),
		'author'             => array( 'pro' => 'archive/author' ),
		'date'               => array( 'pro' => 'archive/date' ),
		// El destino deja fuera la búsqueda de productos (`archive.php:58-60`): menor; como exclusión se completa.
		'search'             => array( 'pro' => 'archive/search', 'quality' => 'smaller', 'widen' => 'woocommerce/product_search' ),
		'posts_archive'      => array( 'pro' => 'archive/post_archive' ),
		'category'           => array( 'pro' => 'archive/category', 'ids' => 'term', 'of' => 'category' ),
		'category_child'     => array( 'pro' => 'archive/child_of_category', 'ids' => 'term', 'of' => 'category', 'need' => 'none' ),
		'category_any_child' => array( 'pro' => 'archive/any_child_of_category', 'ids' => 'term', 'of' => 'category', 'need' => true ),
		'tag'                => array( 'pro' => 'archive/post_tag', 'ids' => 'term', 'of' => 'post_tag' ),
		// `post_tag` no es jerárquica: nunca se cumplían; si alguien la hizo jerárquica, sin equivalente.
		'tag_child'          => array( 'pro' => '', 'flat' => 'post_tag' ),
		'tag_any_child'      => array( 'pro' => '', 'flat' => 'post_tag' ),
	);

	/**
	 * Tabla `woocommerce/<sub>` → destino. Las cinco páginas van a `singular/page/<ID de la página>` con la calidad de
	 * cada polaridad (`include`/`exclude`): carrito y cuenta, aproximadas (`is_cart()` también cubre el shortcode);
	 * pago, pedidos y gracias sólo como exclusión (más amplia).
	 */
	const WOO = array(
		'all'              => array( 'pro' => 'woocommerce' ),
		'shop'             => array( 'pro' => 'woocommerce/shop_page' ),
		'shop_page'        => array( 'pro' => 'woocommerce/shop_page' ),
		'single_product'   => array( 'pro' => 'woocommerce/product', 'ids' => 'post', 'of' => 'product' ),
		'product_archive'  => array( 'pro' => 'woocommerce/product_archive' ),
		'product_search'   => array( 'pro' => 'woocommerce/product_search' ),
		'product_category' => array( 'pro' => 'woocommerce/product_cat', 'ids' => 'term', 'of' => 'product_cat' ),
		'product_tag'      => array( 'pro' => 'woocommerce/product_tag', 'ids' => 'term', 'of' => 'product_tag' ),
		'cart'             => array( 'page' => 'cart', 'include' => 'approx', 'exclude' => 'approx' ),
		'myaccount'        => array( 'page' => 'myaccount', 'include' => 'approx', 'exclude' => 'approx' ),
		'checkout'         => array( 'page' => 'checkout', 'include' => 'none', 'exclude' => 'larger' ),
		'orders'           => array( 'page' => 'myaccount', 'include' => 'none', 'exclude' => 'larger' ),
		'thankyou'         => array( 'page' => 'checkout', 'include' => 'none', 'exclude' => 'larger' ),
	);

	/** Condiciones que el destino registra siempre (`conditions-manager.php:197-232` y sus subcondiciones). */
	const ALWAYS = array( 'general', 'archive', 'singular', 'front_page', 'not_found404', 'child_of', 'any_child_of', 'by_author', 'author', 'date', 'search' );

	/** @var array<int, array> memoria de la petición por sitio (registro de condiciones, páginas, taxonomías). */
	private static $memo = array();

	/* ================================================================ Tipos */

	/** Tipo consolidado de un `_bdthemes_builder_template_type` (alias heredados y `<cpt>|<taxonomía>` → `<cpt>|archive`). */
	public static function canonical( string $raw ): string {
		$type = strtolower( trim( $raw ) );
		if ( isset( self::ALIASES[ $type ] ) ) {
			return self::ALIASES[ $type ];
		}
		$parts = explode( self::SEP, $type, 2 );
		if ( 2 === count( $parts ) && '' !== $parts[1] && ! in_array( $parts[1], array( 'single', 'archive', 'header', 'footer' ), true ) && self::taxonomy_exists( $parts[1] ) ) {
			return $parts[0] . self::SEP . 'archive';
		}
		return $type;
	}

	/**
	 * Destino de un tipo consolidado, o null si no se reconoce (tipo desconocido o de un tipo de entrada que ya no
	 * existe).
	 *
	 * @return array{pro:string, scope:string, location:string, rank:int, cpt:string, woo_page:string, fallback:bool, woo:bool}|null
	 */
	public static function kind( string $type ): ?array {
		$base = array( 'pro' => '', 'scope' => '', 'location' => '', 'rank' => 1, 'cpt' => '', 'woo_page' => '', 'fallback' => false, 'woo' => false );
		switch ( $type ) {
			case 'themes|header':
				return array( 'pro' => 'header', 'scope' => 'all', 'location' => 'header' ) + $base;
			case 'themes|footer':
				return array( 'pro' => 'footer', 'scope' => 'all', 'location' => 'footer' ) + $base;
			case 'post|single':
				return array( 'pro' => 'single-post', 'scope' => 'post_single', 'location' => 'single', 'cpt' => 'post' ) + $base;
			case 'page|single':
				return array( 'pro' => 'single-page', 'scope' => 'page_single', 'location' => 'single', 'cpt' => 'page' ) + $base;
			case 'page|404':
				return array( 'pro' => 'error-404', 'scope' => 'not_found', 'location' => 'single' ) + $base;
			case 'page|search':
				return array( 'pro' => 'search-results', 'scope' => 'search', 'location' => 'archive', 'rank' => 0, 'fallback' => true ) + $base;
			case 'post|archive':
				return array( 'pro' => 'archive', 'scope' => 'post_archive', 'location' => 'archive', 'rank' => 2, 'cpt' => 'post', 'fallback' => true ) + $base;
			case 'product|single':
				return array( 'pro' => 'product', 'scope' => 'cpt_single', 'location' => 'single', 'rank' => 0, 'cpt' => 'product', 'fallback' => true, 'woo' => true ) + $base;
			case 'product|shop':
				return array( 'pro' => 'product-archive', 'scope' => 'shop', 'location' => 'archive', 'rank' => 2, 'cpt' => 'product', 'fallback' => true, 'woo' => true ) + $base;
		}
		if ( isset( self::WOO_PAGES[ $type ] ) ) {
			return array( 'pro' => 'single-page', 'scope' => 'none', 'location' => 'single', 'woo_page' => self::WOO_PAGES[ $type ], 'woo' => true ) + $base;
		}
		$parts = explode( self::SEP, $type, 2 );
		if ( 2 !== count( $parts ) || in_array( $parts[0], array( 'themes', 'post', 'page', 'product' ), true ) || 1 !== preg_match( '/^[a-z0-9_-]{1,20}$/', $parts[0] ) || ! self::post_type_exists( $parts[0] ) ) {
			return null;
		}
		if ( 'single' === $parts[1] ) {
			return array( 'pro' => 'single', 'scope' => 'cpt_single', 'location' => 'single', 'rank' => 0, 'cpt' => $parts[0], 'fallback' => true ) + $base;
		}
		if ( 'archive' === $parts[1] ) {
			return array( 'pro' => 'archive', 'scope' => 'cpt_archive', 'location' => 'archive', 'rank' => 1, 'cpt' => $parts[0], 'fallback' => true ) + $base;
		}
		return null;
	}

	/* ================================================================ Filas de origen */

	/**
	 * Filas de una plantilla como las lee Element Pack: `{polarity, a, singular, archive, woo, ids}`, con la subcondición
	 * vacía según la versión (10.x: `all`; 9.9.1: `''`, que nunca coincide).
	 *
	 * @param int    $post_id  Plantilla `bdt-template-builder`.
	 * @param string $raw_type Su `_bdthemes_builder_template_type` tal cual (la fila heredada usa el valor por defecto de ese tipo).
	 * @param string $version  Versión de Element Pack instalada ('' = la actual).
	 */
	public static function rows( int $post_id, string $raw_type, string $version = '' ): array {
		$raw = get_post_meta( $post_id, self::META_CONDITIONS, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && $decoded ) {
				return self::normalize( $decoded, $version, $post_id, $raw_type );
			}
		}
		return array( self::legacy_row( $post_id, $raw_type ) );
	}

	/** `normalize_rows()` de la versión instalada. */
	public static function normalize( array $rows, string $version = '', int $post_id = 0, string $raw_type = '' ): array {
		$old = self::is_old( $version );
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$sub = static function ( string $key ) use ( $row, $old ): string {
				if ( $old ) {
					return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? (string) $row[ $key ] : 'all';
				}
				return ! empty( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? (string) $row[ $key ] : 'all';
			};
			$out[] = array(
				'polarity' => isset( $row['polarity'] ) && 'exclude' === $row['polarity'] ? 'exclude' : 'include',
				'a'        => isset( $row['condition_a'] ) && is_scalar( $row['condition_a'] ) ? (string) $row['condition_a'] : 'entire_site',
				'singular' => $sub( 'condition_singular' ),
				'archive'  => $sub( 'condition_archive' ),
				'woo'      => $sub( 'condition_woo' ),
				'ids'      => isset( $row['condition_singular_id'] ) && is_scalar( $row['condition_singular_id'] ) ? (string) $row['condition_singular_id'] : '',
			);
		}
		return $out ? $out : array( self::legacy_row( 0, '' ) );
	}

	/** `legacy_row()`: las metas por plantilla con el valor por defecto del tipo (todo el sitio si no lo tiene). */
	public static function legacy_row( int $post_id, string $raw_type ): array {
		$default = self::DEFAULT_ROWS[ trim( $raw_type ) ] ?? null;
		if ( null === $default ) {
			$parts = explode( self::SEP, trim( $raw_type ), 2 );
			if ( 2 === count( $parts ) && ! in_array( $parts[0], array( 'themes', 'post', 'page', 'product' ), true ) && self::post_type_exists( $parts[0] ) ) {
				if ( 'single' === $parts[1] ) {
					$default = array( 'singular', 'pt_' . $parts[0], 'all', 'all' );
				} elseif ( 'archive' === $parts[1] ) {
					$default = array( 'archive', 'all', $parts[0] . '_archive', 'all' );
				}
			}
		}
		$default = $default ?? array( 'entire_site', 'all', 'all', 'all' );
		$meta    = static function ( string $key ) use ( $post_id ): string {
			if ( $post_id <= 0 ) {
				return '';
			}
			$value = get_post_meta( $post_id, self::META_LEGACY . $key, true );
			return is_scalar( $value ) ? (string) $value : '';
		};
		$pick    = static function ( string $value, string $fallback ): string {
			return '' !== $value && '0' !== $value ? $value : $fallback; // `?:` de PHP.
		};
		return array(
			'polarity' => 'include',
			'a'        => $pick( $meta( 'a' ), $default[0] ),
			'singular' => $pick( $meta( 'singular' ), $default[1] ),
			'archive'  => $pick( $meta( 'archive' ), $default[2] ),
			'woo'      => $pick( $meta( 'woo' ), $default[3] ),
			'ids'      => $pick( $meta( 'singular_id' ), '' ),
		);
	}

	/** ¿Es 9.9.1 o anterior (subcondición vacía = no coincide)? */
	public static function is_old( string $version ): bool {
		return '' !== $version && version_compare( $version, '10.0.0', '<' );
	}

	/** `parse_ids()`: separados por comas, sin vacíos ni «0». */
	public static function parse_ids( string $csv ): array {
		$out = array();
		foreach ( explode( ',', $csv ) as $part ) {
			$part = trim( $part );
			if ( '' !== $part && '0' !== $part ) {
				$out[] = $part;
			}
		}
		return $out;
	}

	/* ================================================================ Traducción */

	/**
	 * Traduce las filas de una plantilla de un tipo consolidado.
	 *
	 * @return array{
	 *     conditions: string[],
	 *     includes: string[],
	 *     excludes: string[],
	 *     scope: string[],
	 *     items: array<int, array{code:string, row:string, note:string, count:int}>,
	 *     fail: string,
	 *     effective: bool,
	 *     full: bool
	 * }
	 * `fail` ('' si nada falló): `conditions` (una exclusión sin traducción), `no_include` (ninguna inclusión), `scope`
	 * (una exclusión de ámbito que el destino no puede expresar) o `scope_size` (demasiadas páginas que excluir).
	 * `effective`: alguna inclusión podía cumplirse en Element Pack (para «visible hoy»). `full`: alguna inclusión cubre
	 * todo el ámbito del tipo.
	 */
	public static function translate( string $type, array $rows, string $version = '' ): array {
		$out  = array( 'conditions' => array(), 'includes' => array(), 'excludes' => array(), 'scope' => array(), 'items' => array(), 'fail' => '', 'effective' => false, 'full' => false );
		$kind = self::kind( $type );
		if ( null === $kind ) {
			$out['fail'] = 'type';
			return $out;
		}
		if ( 'not_found' === $kind['scope'] ) {
			// `page|404` no mira sus condiciones (`get_template_id('404','page')`): sólo el 404.
			$out['includes']   = array( 'singular/not_found404' );
			$out['conditions'] = array( 'include/singular/not_found404' );
			$out['effective']  = true;
			$out['full']       = true;
			return $out;
		}
		if ( 'none' === $kind['scope'] ) {
			$out['fail'] = 'woo_page';
			return $out;
		}
		$old      = self::is_old( $version );
		$includes = array();
		$excludes = array();
		$pending  = array(); // by_author con adjuntos: se decide al final, con las demás inclusiones.
		foreach ( $rows as $row ) {
			$a        = '' === (string) $row['a'] ? 'entire_site' : (string) $row['a'];
			$sub      = self::sub_of( $row, $a );
			$label    = 'entire_site' === $a ? $a : $a . '/' . ( '' === $sub ? '∅' : $sub );
			$polarity = 'exclude' === $row['polarity'] ? 'exclude' : 'include';
			$ids      = self::parse_ids( (string) $row['ids'] );
			if ( $old && 'entire_site' !== $a && '' === $sub ) {
				$out['items'][] = self::item( 'tb.cond.never', $polarity, $label, 'subcondición vacía' );
				continue;
			}
			$rule = self::scope_rule( $kind, $a, $sub, $ids );
			if ( 'never' === $rule['do'] ) {
				$out['items'][] = self::item( 'tb.cond.never', $polarity, $label, $rule['note'] ?? '' );
				continue;
			}
			if ( 'include' === $polarity && 'drop' === $rule['do'] ) {
				$out['effective'] = true;
				$out['items'][]   = self::item( 'tb.cond.dropped', $polarity, $label, $rule['note'] ?? 'más amplia en el destino' );
				continue;
			}
			if ( 'include' === $polarity && 'pro' === $rule['do'] ) {
				$out['effective'] = true;
				$kept             = array();
				foreach ( $rule['pro'] as $condition ) {
					if ( self::registered_condition( $condition ) ) {
						$kept[] = $condition;
					}
				}
				if ( ! $kept ) {
					$out['items'][] = self::item( 'tb.cond.dropped', $polarity, $label, 'sin equivalente registrado' );
					continue;
				}
				if ( count( $kept ) < count( $rule['pro'] ) ) {
					$out['items'][] = self::item( 'tb.cond.approx', $polarity, $label, 'parte del ámbito sin equivalente' );
				} elseif ( ! empty( $rule['full'] ) ) {
					$out['full'] = true;
				}
				$includes = array_merge( $includes, $kept );
				continue;
			}
			// Genérica (o reescrita dentro del ámbito).
			$ra = $rule['a'] ?? $a;
			$rs = $rule['sub'] ?? $sub;
			if ( 'include' === $polarity && isset( $rule['ids'] ) ) {
				$ids = $rule['ids'];
			}
			$generic = self::generic( $ra, $rs, $ids );
			if ( 'woo' === $generic['quality'] ) {
				// Páginas de WooCommerce: la calidad depende de la polaridad (§2.1.7).
				$generic['quality'] = (string) $generic['woo_spec'][ $polarity ];
				if ( 'none' === $generic['quality'] ) {
					$generic['state'] = 'none';
					$generic['note']  = 'el destino también la aplicaría al pedido recibido';
				} else {
					$generic['note'] = 'por el ID de la página';
				}
			}
			if ( $generic['missing'] ) {
				$out['items'][] = self::item( 'tb.cond.ids_missing', $polarity, $label, '', $generic['missing'] );
			}
			if ( 'never' === $generic['state'] ) {
				$out['items'][] = self::item( 'tb.cond.never', $polarity, $label, $generic['note'] );
				continue;
			}
			if ( 'include' === $polarity ) {
				$out['effective'] = true;
				if ( 'ok' !== $generic['state'] || 'larger' === $generic['quality'] || 'none' === $generic['quality'] ) {
					$out['items'][] = self::item( 'tb.cond.dropped', $polarity, $label, '' !== $generic['note'] ? $generic['note'] : 'sin equivalente exacto' );
					continue;
				}
				if ( ! empty( $generic['attachments'] ) && 'all' === $kind['scope'] ) {
					$pending[] = array( 'conditions' => $generic['conditions'], 'label' => $label );
					continue;
				}
				if ( in_array( $generic['quality'], array( 'approx', 'smaller' ), true ) ) {
					$out['items'][] = self::item( 'tb.cond.approx', $polarity, $label, $generic['note'] );
				}
				if ( ! empty( $rule['full'] ) && 'exact' === $generic['quality'] ) {
					$out['full'] = true;
				}
				$includes = array_merge( $includes, $generic['conditions'] );
				continue;
			}
			// Exclusión: igual o mayor.
			if ( 'ok' !== $generic['state'] || 'none' === $generic['quality'] ) {
				$out['items'][] = self::item( 'tb.cond.untranslatable', $polarity, $label, '' !== $generic['note'] ? $generic['note'] : 'sin equivalente' );
				$out['fail']    = 'conditions';
				continue;
			}
			if ( 'smaller' === $generic['quality'] ) {
				if ( '' !== $generic['widen'] && self::registered_condition( $generic['widen'] ) ) {
					$generic['conditions'][] = $generic['widen'];
				} elseif ( '' !== $generic['widen'] && self::woo() ) {
					$out['items'][] = self::item( 'tb.cond.untranslatable', $polarity, $label, 'el destino la cubre en menos páginas' );
					$out['fail']    = 'conditions';
					continue;
				}
			}
			if ( 'approx' === $generic['quality'] || 'larger' === $generic['quality'] ) {
				$out['items'][] = self::item( 'tb.cond.approx', $polarity, $label, $generic['note'] );
			}
			$excludes = array_merge( $excludes, $generic['conditions'] );
		}

		// by_author fuera de un ámbito acotado: el destino también coincide en adjuntos (§2.1.7).
		foreach ( $pending as $entry ) {
			$coverage = self::attachment_coverage( $rows, $old );
			if ( 'all' === $coverage ) {
				$includes = array_merge( $includes, $entry['conditions'] );
			} elseif ( 'none' === $coverage && self::registered_condition( 'singular/attachment' ) ) {
				$includes   = array_merge( $includes, $entry['conditions'] );
				$excludes[] = 'singular/attachment';
				$out['items'][] = self::item( 'tb.cond.approx', 'include', $entry['label'], 'sin los adjuntos, como en Element Pack' );
			} else {
				$out['items'][] = self::item( 'tb.cond.dropped', 'include', $entry['label'], 'el destino también la aplicaría a adjuntos' );
			}
		}

		$includes = array_values( array_unique( $includes ) );
		if ( '' !== $out['fail'] ) {
			return $out;
		}
		if ( ! $includes ) {
			$out['fail'] = 'no_include';
			return $out;
		}
		// Exclusiones que acotan el ámbito de Element Pack.
		$scope = self::scope_excludes( $kind, $includes );
		if ( '' !== $scope['fail'] ) {
			$out['fail']    = $scope['fail'];
			$out['items'][] = self::item( 'tb.review.scope', 'exclude', $kind['scope'], $scope['note'] );
			return $out;
		}
		if ( $scope['conditions'] ) {
			$out['scope']   = $scope['conditions'];
			$out['items'][] = self::item( 'tb.scope.added', 'exclude', $kind['scope'], '', count( $scope['conditions'] ) );
			if ( $scope['snapshot'] ) {
				$out['items'][] = self::item( 'tb.scope.snapshot', 'exclude', $kind['scope'], '', $scope['snapshot'] );
			}
		}
		$excludes            = array_values( array_unique( array_merge( $excludes, $scope['conditions'] ) ) );
		$out['includes']     = $includes;
		$out['excludes']     = $excludes;
		$out['conditions']   = array_merge(
			array_map( static function ( $c ) { return 'include/' . $c; }, $includes ),
			array_map( static function ( $c ) { return 'exclude/' . $c; }, $excludes )
		);
		return $out;
	}

	/** Subcondición de la fila según su `condition_a`. */
	private static function sub_of( array $row, string $a ): string {
		switch ( $a ) {
			case 'singular':
				return (string) $row['singular'];
			case 'archive':
				return (string) $row['archive'];
			case 'woocommerce':
				return (string) $row['woo'];
		}
		return '';
	}

	private static function item( string $code, string $polarity, string $row, string $note = '', int $count = 0 ): array {
		return array( 'code' => $code, 'polarity' => $polarity, 'row' => $row, 'note' => $note, 'count' => $count );
	}

	/**
	 * Regla del ámbito para una fila (§2.1.8): `generic` (la tabla, quizá con la fila reescrita en `a`/`sub` o los IDs
	 * filtrados en `ids`), `pro` (condiciones de destino directas, exactas), `never` (nunca se cumplía en ese tipo) o
	 * `drop` (como inclusión sería más amplia; como exclusión vale la tabla). `full`: cubre todo el ámbito.
	 */
	public static function scope_rule( array $kind, string $a, string $sub, array $ids ): array {
		$generic = array( 'do' => 'generic' );
		if ( ! in_array( $a, array( 'entire_site', 'singular', 'archive', 'woocommerce' ), true ) ) {
			return array( 'do' => 'never', 'note' => 'condición desconocida' );
		}
		$cpt = (string) $kind['cpt'];
		switch ( $kind['scope'] ) {
			case 'all':
				return 'entire_site' === $a || 'all' === $sub ? $generic + array( 'full' => 'entire_site' === $a ) : $generic;

			case 'post_single':
				if ( 'archive' === $a ) {
					return self::never( 'archivo en una plantilla de entrada' );
				}
				if ( 'woocommerce' === $a ) {
					return in_array( $sub, array( 'all', 'single_product' ), true ) ? $generic : self::never( 'página de WooCommerce en una plantilla de entrada' );
				}
				if ( 'entire_site' === $a || 'all' === $sub ) {
					return $generic + array( 'full' => true );
				}
				if ( in_array( $sub, array( 'front_page', 'not_found404', '404page', 'all_pages', 'page_by_author', 'pt_page' ), true ) ) {
					return self::never( 'Element Pack no la consultaba en páginas ni en el 404' );
				}
				if ( in_array( $sub, array( 'attachment', 'attachment_by_author', 'pt_attachment' ), true ) ) {
					return array( 'do' => 'drop', 'note' => 'los adjuntos quedan fuera de la copia' );
				}
				if ( 'selective' === $sub ) {
					return self::filter_ids( $ids, array( 'page' ), array( 'attachment' ) );
				}
				return $generic;

			case 'page_single':
				if ( 'archive' === $a || 'woocommerce' === $a ) {
					return self::never( 'no es una página' );
				}
				if ( 'entire_site' === $a || 'all' === $sub ) {
					return array( 'do' => 'pro', 'pro' => array( 'singular/page' ), 'full' => true );
				}
				switch ( $sub ) {
					case 'front_page':
						return $generic;
					case 'all_pages':
						return $generic + array( 'full' => ! $ids );
					case 'page_by_author':
						return $generic;
					case 'pt_page':
						return $generic + array( 'a' => 'singular', 'sub' => 'all_pages', 'full' => ! $ids );
					case 'by_author':
						return $generic + array( 'sub' => 'page_by_author' );
					case 'selective':
					case 'any_child_of':
						return self::keep_ids( $ids, 'page' );
					case 'child_of':
						return self::only_hierarchical( 'page' ) ? $generic : array( 'do' => 'drop', 'note' => 'el destino también la aplicaría a otros tipos jerárquicos' );
					case 'in_category':
					case 'in_category_children':
						return self::in_taxonomy( 'page', 'category' ) ? array( 'do' => 'drop', 'note' => 'el destino también la aplicaría a entradas' ) : self::never( 'las páginas no tienen categorías' );
					case 'in_post_tag':
						return self::in_taxonomy( 'page', 'post_tag' ) ? array( 'do' => 'drop', 'note' => 'el destino también la aplicaría a entradas' ) : self::never( 'las páginas no tienen etiquetas' );
				}
				return self::never( 'no es una página' );

			case 'cpt_single':
				$product = 'product' === $cpt;
				if ( 'archive' === $a ) {
					return self::never( 'archivo en una plantilla individual' );
				}
				if ( 'woocommerce' === $a ) {
					if ( ! $product ) {
						return self::never( 'WooCommerce en otro tipo de entrada' );
					}
					if ( 'all' === $sub ) {
						return $generic + array( 'a' => 'woocommerce', 'sub' => 'single_product', 'full' => true );
					}
					return 'single_product' === $sub ? $generic + array( 'full' => ! $ids ) : self::never( 'no es una ficha de producto' );
				}
				if ( 'entire_site' === $a || 'all' === $sub ) {
					return $product
						? $generic + array( 'a' => 'woocommerce', 'sub' => 'single_product', 'full' => true )
						: $generic + array( 'a' => 'singular', 'sub' => 'pt_' . $cpt, 'full' => true );
				}
				if ( 'pt_' . $cpt === $sub ) {
					return $generic + array( 'full' => ! $ids );
				}
				if ( 0 === strpos( $sub, 'pt_' ) ) {
					return self::never( 'otro tipo de entrada' );
				}
				switch ( $sub ) {
					case 'selective':
					case 'any_child_of':
						return self::keep_ids( $ids, $cpt );
					case 'by_author':
						$base = $product ? 'woocommerce/product_by_author' : 'singular/' . $cpt . '_by_author';
						$pro  = array();
						foreach ( $ids as $id ) {
							if ( ctype_digit( $id ) && self::user_exists( (int) $id ) ) {
								$pro[] = $base . '/' . (int) $id;
							}
						}
						return $pro ? array( 'do' => 'pro', 'pro' => $pro ) : self::never( 'sin autores que existan' );
					case 'child_of':
						if ( ! self::is_hierarchical_type( $cpt ) ) {
							return self::never( 'tipo de entrada no jerárquico' );
						}
						return self::only_hierarchical( $cpt ) ? $generic : array( 'do' => 'drop', 'note' => 'el destino también la aplicaría a otros tipos jerárquicos' );
					case 'in_category':
					case 'in_category_children':
						return self::in_taxonomy( $cpt, 'category' ) ? array( 'do' => 'drop', 'note' => 'el destino también la aplicaría a entradas' ) : self::never( 'el tipo no usa categorías' );
					case 'in_post_tag':
						return self::in_taxonomy( $cpt, 'post_tag' ) ? array( 'do' => 'drop', 'note' => 'el destino también la aplicaría a entradas' ) : self::never( 'el tipo no usa etiquetas' );
				}
				return self::never( 'no aplica a este tipo de entrada' );

			case 'post_archive':
				if ( 'singular' === $a || 'woocommerce' === $a ) {
					return self::never( 'no es un archivo del blog' );
				}
				if ( 'entire_site' === $a ) {
					return $generic + array( 'a' => 'archive', 'sub' => 'all', 'full' => true );
				}
				if ( 'search' === $sub ) {
					return self::never( 'Element Pack no la consultaba en la búsqueda' );
				}
				return $generic + array( 'full' => 'all' === $sub );

			case 'cpt_archive':
				if ( 'singular' === $a || 'woocommerce' === $a ) {
					return self::never( 'no es un archivo de este tipo' );
				}
				if ( 'entire_site' === $a || 'all' === $sub ) {
					$pro = array();
					if ( self::post_type_has_archive( $cpt ) ) {
						$pro[] = 'archive/' . $cpt . '_archive';
					}
					foreach ( self::cpt_taxonomies( $cpt ) as $taxonomy ) {
						$pro[] = 'archive/' . $taxonomy;
					}
					return $pro ? array( 'do' => 'pro', 'pro' => $pro, 'full' => true ) : self::never( 'el tipo no tiene archivo' );
				}
				if ( $cpt . '_archive' === $sub ) {
					return $generic;
				}
				return self::never( 'no es un archivo de este tipo' );

			case 'search':
				if ( 'singular' === $a ) {
					return self::never( 'no es una búsqueda' );
				}
				if ( 'entire_site' === $a || ( 'archive' === $a && 'search' === $sub ) ) {
					$pro = array( 'archive/search' );
					if ( self::woo() ) {
						$pro[] = 'woocommerce/product_search';
					}
					return array( 'do' => 'pro', 'pro' => $pro, 'full' => true );
				}
				if ( 'archive' === $a ) {
					if ( 'all' === $sub ) {
						return array( 'do' => 'pro', 'pro' => array( 'archive/search' ) );
					}
					if ( strlen( $sub ) > 8 && '_archive' === substr( $sub, -8 ) ) {
						return array( 'do' => 'drop', 'note' => 'el destino también la aplicaría al archivo sin buscar' );
					}
					return self::never( 'no es una búsqueda' );
				}
				if ( in_array( $sub, array( 'all', 'product_search', 'product_archive' ), true ) ) {
					return self::woo() ? array( 'do' => 'pro', 'pro' => array( 'woocommerce/product_search' ) ) : self::never( 'sin WooCommerce' );
				}
				return self::never( 'no es una búsqueda' );

			case 'shop':
				if ( 'singular' === $a || 'archive' === $a ) {
					return self::never( 'no es la tienda' );
				}
				if ( 'entire_site' === $a || in_array( $sub, array( 'all', 'product_archive' ), true ) ) {
					// Igual al ámbito de Element Pack: la tienda y los archivos de categoría y etiqueta de producto.
					return array( 'do' => 'pro', 'pro' => array( 'woocommerce/shop_page', 'woocommerce/product_cat', 'woocommerce/product_tag' ), 'full' => true );
				}
				if ( in_array( $sub, array( 'shop', 'shop_page', 'product_category', 'product_tag', 'woo_tax_product_cat', 'woo_tax_product_tag' ), true ) ) {
					return $generic;
				}
				return self::never( 'no es la tienda ni una categoría o etiqueta de producto' );
		}
		return self::never( 'tipo sin ámbito' );
	}

	private static function never( string $note ): array {
		return array( 'do' => 'never', 'note' => $note );
	}

	/** `selective`/`any_child_of` dentro de un tipo: sólo los IDs de ese tipo (los demás nunca coincidían). */
	private static function keep_ids( array $ids, string $type ): array {
		$keep = array();
		$found = false;
		foreach ( $ids as $id ) {
			$post = ctype_digit( $id ) ? get_post( (int) $id ) : null;
			if ( ! $post ) {
				$keep[] = $id; // La tabla lo cuenta como inexistente.
			} elseif ( $type === $post->post_type ) {
				$keep[] = $id;
				$found  = true;
			}
		}
		return $found || $keep ? array( 'do' => 'generic', 'ids' => $keep ) : self::never( 'ningún ID de este tipo' );
	}

	/** `selective` en `post|single`: fuera las páginas (nunca) y, si sólo quedan adjuntos, fuera (omitidos). */
	private static function filter_ids( array $ids, array $never, array $drop ): array {
		$keep    = array();
		$dropped = false;
		foreach ( $ids as $id ) {
			$post = ctype_digit( $id ) ? get_post( (int) $id ) : null;
			if ( ! $post ) {
				$keep[] = $id; // Lo cuenta como inexistente la tabla.
				continue;
			}
			if ( in_array( $post->post_type, $never, true ) ) {
				continue;
			}
			if ( in_array( $post->post_type, $drop, true ) ) {
				$dropped = true;
				continue;
			}
			$keep[] = $id;
		}
		if ( $keep ) {
			return array( 'do' => 'generic', 'ids' => $keep );
		}
		return $dropped ? array( 'do' => 'drop', 'note' => 'los adjuntos quedan fuera de la copia' ) : self::never( 'ningún ID de este tipo' );
	}

	/**
	 * Traducción de la tabla, sin ámbito.
	 *
	 * @return array{state:string, quality:string, conditions:string[], missing:int, note:string, widen:string, attachments:bool}
	 *         `state`: `ok`, `never` (nunca se cumplía) o `none` (sin equivalente).
	 */
	public static function generic( string $a, string $sub, array $ids ): array {
		$out = array( 'state' => 'ok', 'quality' => 'exact', 'conditions' => array(), 'missing' => 0, 'note' => '', 'widen' => '', 'attachments' => false );
		if ( 'entire_site' === $a ) {
			$out['conditions'] = array( 'general' );
			return $out;
		}
		$table = array( 'singular' => self::SINGULAR, 'archive' => self::ARCHIVE, 'woocommerce' => self::WOO );
		if ( ! isset( $table[ $a ] ) ) {
			return self::state( $out, 'never', 'condición desconocida' );
		}
		if ( 'woocommerce' === $a && ! self::woo() ) {
			return self::state( $out, 'never', 'sin WooCommerce' );
		}
		$spec = $table[ $a ][ $sub ] ?? null;
		// Prefijos dinámicos.
		if ( null === $spec ) {
			if ( 'singular' === $a && strlen( $sub ) > 3 && 'pt_' === substr( $sub, 0, 3 ) ) {
				$pt = substr( $sub, 3 );
				if ( ! self::post_type_exists( $pt ) ) {
					return self::state( $out, 'never', 'tipo de entrada inexistente' );
				}
				$spec = array( 'pro' => self::singular_base( $pt ), 'ids' => 'post', 'of' => $pt );
			} elseif ( 'archive' === $a && strlen( $sub ) > 8 && '_archive' === substr( $sub, -8 ) ) {
				$pt = substr( $sub, 0, -8 );
				if ( '' === $pt || ! self::post_type_exists( $pt ) ) {
					return self::state( $out, 'never', 'tipo de entrada inexistente' );
				}
				$spec = array( 'pro' => 'archive/' . $pt . '_archive' );
			} elseif ( 'woocommerce' === $a && strlen( $sub ) > 8 && 'woo_tax_' === substr( $sub, 0, 8 ) ) {
				$tax = substr( $sub, 8 );
				if ( ! self::taxonomy_exists( $tax ) ) {
					return self::state( $out, 'never', 'taxonomía inexistente' );
				}
				$spec = array( 'pro' => 'woocommerce/' . $tax, 'ids' => 'term', 'of' => $tax );
			} else {
				return self::state( $out, 'never', 'subcondición desconocida' );
			}
		}
		if ( ! empty( $spec['flat'] ) ) {
			return self::is_hierarchical_taxonomy( $spec['flat'] ) ? self::state( $out, 'none', 'sin equivalente' ) : self::state( $out, 'never', 'la taxonomía no es jerárquica' );
		}
		if ( isset( $spec['page'] ) ) {
			$page = self::woo_page_id( $spec['page'] );
			if ( $page <= 0 ) {
				return self::state( $out, 'none', 'la página de WooCommerce no está asignada' );
			}
			$out['conditions'] = array( 'singular/page/' . $page );
			return self::woo_quality( $out, $spec );
		}
		if ( ! empty( $spec['attachments'] ) ) {
			$out['attachments'] = true;
		}
		if ( isset( $spec['quality'] ) ) {
			$out['quality'] = $spec['quality'];
			$out['note']    = 'el destino la cubre en menos páginas';
		}
		if ( isset( $spec['widen'] ) ) {
			$out['widen'] = $spec['widen'];
			if ( ! self::woo() ) {
				// Sin WooCommerce no hay búsqueda de productos: igual.
				$out['quality'] = 'exact';
				$out['note']    = '';
				$out['widen']   = '';
			}
		}
		$base = (string) $spec['pro'];
		if ( empty( $spec['ids'] ) || ! $ids ) {
			if ( ! empty( $spec['need'] ) && ! $ids ) {
				return 'none' === $spec['need'] ? self::state( $out, 'none', 'sin IDs no tiene equivalente' ) : self::state( $out, 'never', 'sin IDs nunca se cumplía' );
			}
			if ( '{type}' === $base ) {
				return self::state( $out, 'never', 'sin IDs nunca se cumplía' );
			}
			if ( ! self::registered_condition( $base ) ) {
				return self::state( $out, 'none', 'sin equivalente registrado' );
			}
			$out['conditions'] = array( $base );
			return $out;
		}
		// Una cadena por ID que existe (y es del tipo que la fila exige).
		$valid = array();
		foreach ( $ids as $raw ) {
			if ( ! ctype_digit( $raw ) ) {
				++$out['missing'];
				continue;
			}
			$id = (int) $raw;
			if ( 'post' === $spec['ids'] ) {
				$post = get_post( $id );
				if ( ! $post ) {
					++$out['missing'];
					continue;
				}
				if ( isset( $spec['of'] ) && $spec['of'] !== $post->post_type ) {
					continue; // Element Pack nunca la aplicaba a otro tipo.
				}
				$condition = '{type}' === $base ? self::singular_base( (string) $post->post_type ) . '/' . $id : $base . '/' . $id;
			} elseif ( 'term' === $spec['ids'] ) {
				if ( ! self::term_exists( $id, (string) $spec['of'] ) ) {
					++$out['missing'];
					continue;
				}
				$condition = $base . '/' . $id;
			} else {
				if ( ! self::user_exists( $id ) ) {
					++$out['missing'];
					continue;
				}
				$condition = $base . '/' . $id;
			}
			if ( ! self::registered_condition( $condition ) ) {
				if ( '{type}' === $base ) {
					$out['note'] = 'IDs de un tipo sin condición en el destino';
					continue;
				}
				return self::state( $out, 'none', 'sin equivalente registrado' );
			}
			$valid[] = $condition;
		}
		if ( ! $valid ) {
			return self::state( $out, 'never', '' !== $out['note'] ? $out['note'] : 'ningún ID existe' );
		}
		$out['conditions'] = array_values( array_unique( $valid ) );
		return $out;
	}

	/** Páginas de WooCommerce: la calidad de cada polaridad la resuelve translate(). */
	private static function woo_quality( array $out, array $spec ): array {
		$out['quality']  = 'woo';
		$out['woo_spec'] = array( 'include' => $spec['include'], 'exclude' => $spec['exclude'] );
		return $out;
	}

	private static function state( array $out, string $state, string $note ): array {
		$out['state'] = $state;
		$out['note']  = $note;
		if ( 'none' === $state ) {
			$out['quality'] = 'none';
		}
		return $out;
	}

	/** Base de una condición individual de un tipo: `woocommerce/product` con WooCommerce, si no `singular/<tipo>`. */
	private static function singular_base( string $type ): string {
		return 'product' === $type && self::woo() ? 'woocommerce/product' : 'singular/' . $type;
	}

	/**
	 * ¿Cubren las demás inclusiones todos los adjuntos (`all`), ninguno (`none`) o algunos (`some`)? Para decidir
	 * `by_author` en cabecera y pie.
	 */
	private static function attachment_coverage( array $rows, bool $old ): string {
		$coverage = 'none';
		foreach ( $rows as $row ) {
			if ( 'exclude' === $row['polarity'] ) {
				continue;
			}
			$a   = '' === (string) $row['a'] ? 'entire_site' : (string) $row['a'];
			$sub = self::sub_of( $row, $a );
			if ( $old && 'entire_site' !== $a && '' === $sub ) {
				continue;
			}
			$ids = self::parse_ids( (string) $row['ids'] );
			if ( 'entire_site' === $a || ( 'singular' === $a && ( 'all' === $sub || ( in_array( $sub, array( 'attachment', 'pt_attachment' ), true ) && ! $ids ) ) ) ) {
				return 'all';
			}
			if ( 'singular' === $a && in_array( $sub, array( 'attachment', 'pt_attachment', 'attachment_by_author', 'selective', 'child_of', 'any_child_of', 'in_category', 'in_category_children', 'in_post_tag' ), true ) ) {
				$coverage = 'some';
			}
		}
		return $coverage;
	}

	/**
	 * Exclusiones que acotan el ámbito de cada tipo (§2.1.8).
	 *
	 * @return array{conditions:string[], fail:string, note:string, snapshot:int}
	 */
	public static function scope_excludes( array $kind, array $includes ): array {
		$out = array( 'conditions' => array(), 'fail' => '', 'note' => '', 'snapshot' => 0 );
		switch ( $kind['scope'] ) {
			case 'post_single':
				foreach ( array( 'singular/page', 'singular/not_found404', 'singular/attachment' ) as $condition ) {
					if ( ! self::registered_condition( $condition ) ) {
						$out['fail'] = 'scope';
						$out['note'] = 'exclusión sin condición en el destino: ' . $condition;
						return $out;
					}
					$out['conditions'][] = $condition;
				}
				return $out;

			case 'page_single':
				$ids = array();
				if ( self::woo() ) {
					foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
						$id = self::woo_page_id( $page );
						if ( $id > 0 ) {
							$ids[ $id ] = $id;
						}
					}
					foreach ( self::woo_shortcode_pages() as $id ) {
						$ids[ $id ] = $id;
					}
				}
				$templated = self::theme_template_pages();
				foreach ( $templated as $id ) {
					$ids[ $id ] = $id;
				}
				if ( count( $ids ) > self::MAX_EXCLUDED_IDS ) {
					$out['fail'] = 'scope_size';
					$out['note'] = 'demasiadas páginas que excluir';
					return $out;
				}
				ksort( $ids );
				foreach ( $ids as $id ) {
					$out['conditions'][] = 'singular/page/' . $id;
				}
				$out['snapshot'] = count( $templated );
				return $out;

			case 'post_archive':
				$out['conditions'][] = 'archive/search';
				$broad = (bool) array_intersect( $includes, array( 'archive', 'general' ) );
				foreach ( self::post_only_taxonomies() as $taxonomy ) {
					if ( self::registered_condition( 'archive/' . $taxonomy ) ) {
						$out['conditions'][] = 'archive/' . $taxonomy;
					} elseif ( $broad ) {
						$out['fail'] = 'scope';
						$out['note'] = 'taxonomía de entradas sin condición en el destino: ' . $taxonomy;
						return $out;
					}
				}
				return $out;

			case 'shop':
				if ( self::registered_condition( 'woocommerce/product_search' ) ) {
					$out['conditions'][] = 'woocommerce/product_search';
				}
				return $out;
		}
		return $out;
	}

	/* ================================================================ Destino registrado */

	/** ¿Existe en el destino una cadena `nombre[/subnombre[/id]]` (sin polaridad)? */
	public static function registered_condition( string $condition ): bool {
		$parts = explode( '/', $condition );
		if ( ! self::registered( $parts[0] ) ) {
			return false;
		}
		return ! isset( $parts[1] ) || '' === $parts[1] || self::registered( $parts[1] );
	}

	/** ¿Registra el Theme Builder de destino una condición con ese nombre? (`conditions-manager.php:197-232`, plano). */
	public static function registered( string $name ): bool {
		$manager = self::manager();
		if ( null !== $manager ) {
			try {
				return false !== $manager->get_condition( $name );
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
		$memo = &self::memo();
		if ( ! isset( $memo['registry'] ) ) {
			$memo['registry'] = self::registry();
		}
		return isset( $memo['registry'][ $name ] );
	}

	/** Gestor de condiciones del Theme Builder cargado (ya con sus condiciones registradas), o null. */
	private static function manager() {
		$class = '\\ElementorPro\\Modules\\ThemeBuilder\\Module';
		if ( ! class_exists( $class, false ) || ! function_exists( 'did_action' ) || ! did_action( 'wp_loaded' ) || ! method_exists( $class, 'instance' ) ) {
			return null;
		}
		try {
			$module  = $class::instance();
			$manager = is_object( $module ) && method_exists( $module, 'get_conditions_manager' ) ? $module->get_conditions_manager() : null;
		} catch ( \Throwable $e ) {
			return null;
		}
		return is_object( $manager ) && method_exists( $manager, 'get_condition' ) ? $manager : null;
	}

	/**
	 * Las mismas reglas de registro que el destino, sin cargarlo: `Singular`, `Archive`, `Post`, `Post_Type_Archive` y
	 * los de WooCommerce (`modules/theme-builder/conditions/*.php`, `modules/woocommerce/conditions/*.php`).
	 */
	private static function registry(): array {
		$names  = array_fill_keys( self::ALWAYS, true );
		$woo    = self::woo();
		$public = function_exists( 'get_post_types' ) ? array_values( (array) get_post_types( array( 'show_in_nav_menus' => true ) ) ) : array( 'post', 'page' );
		if ( $woo ) {
			$public = array_values( array_diff( $public, array( 'product' ) ) );
		}
		foreach ( array_merge( $public, array( 'attachment' ) ) as $type ) {
			self::register_post_type( $names, (string) $type );
		}
		foreach ( $public as $type ) {
			if ( ! self::post_type_has_archive( (string) $type ) ) {
				continue;
			}
			$names[ $type . '_archive' ] = true;
			foreach ( self::nav_taxonomies( (string) $type ) as $taxonomy => $hierarchical ) {
				$names[ $taxonomy ] = true;
				if ( $hierarchical ) {
					$names[ 'child_of_' . $taxonomy ]     = true;
					$names[ 'any_child_of_' . $taxonomy ] = true;
				}
			}
		}
		if ( $woo ) {
			foreach ( array( 'woocommerce', 'product_archive', 'shop_page', 'product_search' ) as $name ) {
				$names[ $name ] = true;
			}
			self::register_post_type( $names, 'product' );
			foreach ( self::nav_taxonomies( 'product' ) as $taxonomy => $hierarchical ) {
				$names[ $taxonomy ] = true;
			}
		}
		return $names;
	}

	/** `Post` y sus subcondiciones (`post.php`): el tipo, `<tipo>_by_author`, `in_<tax>` e `in_<tax>_children`. */
	private static function register_post_type( array &$names, string $type ): void {
		$names[ $type ]               = true;
		$names[ $type . '_by_author' ] = true;
		foreach ( self::nav_taxonomies( $type ) as $taxonomy => $hierarchical ) {
			$names[ 'in_' . $taxonomy ] = true;
			if ( $hierarchical ) {
				$names[ 'in_' . $taxonomy . '_children' ] = true;
			}
		}
	}

	/** Taxonomías públicas y en menús de un tipo: nombre => jerárquica. */
	private static function nav_taxonomies( string $type ): array {
		$out = array();
		if ( ! function_exists( 'get_object_taxonomies' ) ) {
			return $out;
		}
		foreach ( (array) get_object_taxonomies( $type, 'objects' ) as $name => $taxonomy ) {
			if ( is_object( $taxonomy ) && ! empty( $taxonomy->public ) && ! empty( $taxonomy->show_in_nav_menus ) ) {
				$out[ (string) ( $taxonomy->name ?? $name ) ] = ! empty( $taxonomy->hierarchical );
			}
		}
		return $out;
	}

	/* ================================================================ Datos del sitio */

	/** ¿WooCommerce activo? (Element Pack y el destino miran la clase.) */
	public static function woo(): bool {
		return class_exists( 'WooCommerce', false );
	}

	/** ID de una página de WooCommerce (cart, checkout, myaccount, shop), o 0. */
	public static function woo_page_id( string $page ): int {
		if ( function_exists( 'wc_get_page_id' ) ) {
			return max( 0, (int) wc_get_page_id( $page ) );
		}
		return max( 0, (int) get_option( 'woocommerce_' . $page . '_page_id', 0 ) );
	}

	/** Páginas con el shortcode de carrito, pago o cuenta (`is_cart()` y compañía también las cuentan). Foto fija. */
	private static function woo_shortcode_pages(): array {
		$memo = &self::memo();
		if ( isset( $memo['woo_pages'] ) ) {
			return $memo['woo_pages'];
		}
		global $wpdb;
		$ids = array();
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_col' ) ) {
			$like = array();
			foreach ( array( '[woocommerce_cart', '[woocommerce_checkout', '[woocommerce_my_account' ) as $code ) {
				$like[] = '%' . $wpdb->esc_like( $code ) . '%';
			}
			$ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s ) ORDER BY ID ASC LIMIT 200", $like[0], $like[1], $like[2] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$memo['woo_pages'] = array_values( array_filter( array_map( 'intval', $ids ) ) );
		return $memo['woo_pages'];
	}

	/** Páginas con una plantilla PHP del tema (ni `default` ni del constructor): Element Pack no les aplica `page|single`. */
	private static function theme_template_pages(): array {
		$memo = &self::memo();
		if ( isset( $memo['templated'] ) ) {
			return $memo['templated'];
		}
		global $wpdb;
		$ids = array();
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_col' ) ) {
			$ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'page' AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND m.meta_value NOT IN ( '', 'default' ) AND m.meta_value NOT LIKE %s ORDER BY p.ID ASC LIMIT 200", '_wp_page_template', $wpdb->esc_like( 'elementor' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$memo['templated'] = array_values( array_filter( array_map( 'intval', $ids ) ) );
		return $memo['templated'];
	}

	/**
	 * Taxonomías propias de las entradas con archivo y con algún término (sin `category` ni `post_tag`, y que no
	 * comparte un tipo personalizado): Element Pack no aplica `post|archive` en ellas (`builder-integration.php:772-799`).
	 */
	private static function post_only_taxonomies(): array {
		$out = array();
		if ( ! function_exists( 'get_object_taxonomies' ) ) {
			return $out;
		}
		foreach ( (array) get_object_taxonomies( 'post', 'objects' ) as $name => $taxonomy ) {
			$name = (string) ( is_object( $taxonomy ) && isset( $taxonomy->name ) ? $taxonomy->name : $name );
			if ( in_array( $name, array( 'category', 'post_tag' ), true ) || ! is_object( $taxonomy ) || empty( $taxonomy->publicly_queryable ) && empty( $taxonomy->public ) ) {
				continue;
			}
			$others = array_diff( (array) ( $taxonomy->object_type ?? array( 'post' ) ), array( 'post', 'page' ) );
			if ( $others ) {
				continue; // La comparte un tipo personalizado: Element Pack sí la cubre (respaldo del archivo del tipo).
			}
			if ( self::has_terms( $name ) ) {
				$out[] = $name;
			}
		}
		return $out;
	}

	/** Taxonomías de un tipo personalizado cuyos archivos van a `<cpt>|archive` (`get_custom_post_type_archive_context()`). */
	private static function cpt_taxonomies( string $cpt ): array {
		$out = array();
		if ( ! function_exists( 'get_object_taxonomies' ) ) {
			return $out;
		}
		foreach ( (array) get_object_taxonomies( $cpt, 'objects' ) as $name => $taxonomy ) {
			if ( ! is_object( $taxonomy ) ) {
				continue;
			}
			$name = (string) ( $taxonomy->name ?? $name );
			// Element Pack toma el primer tipo que no es entrada ni página.
			$first = '';
			foreach ( (array) ( $taxonomy->object_type ?? array() ) as $candidate ) {
				if ( ! in_array( $candidate, array( 'post', 'page' ), true ) ) {
					$first = (string) $candidate;
					break;
				}
			}
			if ( $first === $cpt && ( ! empty( $taxonomy->public ) || ! empty( $taxonomy->publicly_queryable ) ) ) {
				$out[] = $name;
			}
		}
		return $out;
	}

	private static function has_terms( string $taxonomy ): bool {
		if ( function_exists( 'wp_count_terms' ) ) {
			$count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			return is_numeric( $count ) && (int) $count > 0;
		}
		return true;
	}

	private static function post_type_exists( string $type ): bool {
		return function_exists( 'post_type_exists' ) && post_type_exists( $type );
	}

	private static function taxonomy_exists( string $taxonomy ): bool {
		return function_exists( 'taxonomy_exists' ) && taxonomy_exists( $taxonomy );
	}

	private static function post_type_has_archive( string $type ): bool {
		return function_exists( 'get_post_type_archive_link' ) && (bool) get_post_type_archive_link( $type );
	}

	private static function is_hierarchical_type( string $type ): bool {
		return function_exists( 'is_post_type_hierarchical' ) && is_post_type_hierarchical( $type );
	}

	private static function is_hierarchical_taxonomy( string $taxonomy ): bool {
		return function_exists( 'is_taxonomy_hierarchical' ) && is_taxonomy_hierarchical( $taxonomy );
	}

	/** ¿Es ése el único tipo público jerárquico? Entonces `child_of` no se sale de él. */
	private static function only_hierarchical( string $type ): bool {
		if ( ! function_exists( 'get_post_types' ) ) {
			return false;
		}
		$types = array_values( (array) get_post_types( array( 'hierarchical' => true, 'public' => true ) ) );
		return array( $type ) === $types;
	}

	private static function in_taxonomy( string $type, string $taxonomy ): bool {
		return function_exists( 'is_object_in_taxonomy' ) && is_object_in_taxonomy( $type, $taxonomy );
	}

	private static function term_exists( int $id, string $taxonomy ): bool {
		if ( ! function_exists( 'get_term' ) ) {
			return false;
		}
		$term = get_term( $id, $taxonomy );
		return is_object( $term ) && ! ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) && ( ! isset( $term->taxonomy ) || $taxonomy === $term->taxonomy );
	}

	private static function user_exists( int $id ): bool {
		return function_exists( 'get_userdata' ) && (bool) get_userdata( $id );
	}

	/** Memoria del sitio en curso. */
	private static function &memo(): array {
		$blog = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		if ( ! isset( self::$memo[ $blog ] ) ) {
			self::$memo[ $blog ] = array();
		}
		return self::$memo[ $blog ];
	}

	/** Olvida la memoria de la petición (pruebas; cambio de datos del sitio). */
	public static function reset(): void {
		self::$memo = array();
	}
}
