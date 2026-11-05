<?php
namespace Digitalisimo\Elements\Woo\Kit;

use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Remapper;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Export\Meta_Blacklist;

defined( 'ABSPATH' ) || exit;

/**
 * Parte `woo-variations` del kit (F4-Woo, V.11.2 y V.11.3). `digitalisimo/woo-variations.json` = `{ v: 1, variations:
 * { "<ID del padre en el origen>": [ fila, … ] } }`.
 *
 * - **Exportar:** las variaciones `publish` y `private` de los productos variables exportados, con los datos de
 *   WooCommerce (atributos, precios con fechas, SKU, existencias, peso y medidas, clase de envío por *slug*, clase de
 *   impuesto, descripción, `virtual`, `downloadable` sin archivos) y la imagen como ID del origen; sus otras metas,
 *   las de `get_meta_data()`, con la lista negra de K. Nada de pedidos, clientes ni reseñas. Los archivos
 *   descargables no viajan (`not_exported.downloads`). Las imágenes las anota `Media_Runner` para la parte `media`.
 * - **Contadores (V.11.4):** K anota todas las variaciones del sitio como no exportadas; `not_exported()` baja
 *   `variations` en las que esta parte exportó.
 * - **Importar:** sólo bajo padres que creó la sesión (`Import_Context::is_new_post()`); un padre reutilizado ya es
 *   del sitio y no se toca (`woocommerce.variations-kept`). Cada variación con `WC_Product_Variation::save()`; un SKU
 *   repetido queda vacío (`woocommerce.sku-conflict`); una imagen sin destino, sin imagen (`reference.attachment`); un
 *   término de atributo que el destino no tiene descarta la variación (nunca pasa a «cualquiera»). Después,
 *   `WC_Product_Variable::sync()` y `wc_delete_product_transients()` de cada padre.
 * - **Tabla de búsqueda (V.11.3):** al final, `refresh_product_lookup_table()` (WooCommerce 10.8+; antes, `save()`)
 *   de cada producto que creó la sesión, también los simples, para el filtro y la ordenación por precio
 *   (`woocommerce.lookup-refreshed`).
 * - **Revertir:** borra las variaciones creadas cuya huella no cambió; las editadas después se conservan y se
 *   informan. Si el núcleo ya borró su padre, WooCommerce las borró con él.
 */
final class Variations_Part implements Part {
	const KEY     = 'woo-variations';
	const VERSION = 1;

	/** Variaciones por padre y en total que se leen de un kit, como mucho. */
	const MAX_PER_PARENT = 100;
	const MAX_TOTAL      = 2000;
	const MAX_METAS      = 50;
	const KEY_PATTERN    = '/^[A-Za-z0-9_.:-]{1,191}$/';
	const STATUSES       = array( 'publish', 'private' );
	const STOCK_STATUSES = array( 'instock', 'outofstock', 'onbackorder' );
	const BACKORDERS     = array( 'no', 'notify', 'yes' );

	/** Metas que WooCommerce guarda con las propiedades de la variación: nunca viajan como metas sueltas. */
	const INTERNAL_META = array(
		'_visibility', '_sku', '_global_unique_id', '_price', '_regular_price', '_sale_price', '_sale_price_dates_from',
		'_sale_price_dates_to', 'total_sales', '_tax_status', '_tax_class', '_manage_stock', '_stock', '_stock_status',
		'_backorders', '_low_stock_amount', '_sold_individually', '_weight', '_length', '_width', '_height',
		'_upsell_ids', '_crosssell_ids', '_purchase_note', '_default_attributes', '_product_attributes', '_virtual',
		'_downloadable', '_download_limit', '_download_expiry', '_featured', '_downloadable_files', '_wc_rating_count',
		'_wc_average_rating', '_wc_review_count', '_variation_description', '_thumbnail_id', '_file_paths',
		'_product_image_gallery', '_wc_video_gallery', '_product_version', '_wp_old_slug', '_edit_last', '_edit_lock',
		'_cogs_total_value', '_cogs_value_is_additive',
	);

	/** @var int[] Variaciones exportadas por contexto de exportación (`spl_object_id`). */
	private static $exported = array();

	public function key(): string {
		return self::KEY;
	}

	public function version(): int {
		return self::VERSION;
	}

	public function kit_meta_keys(): array {
		return array();
	}

	/** ¿Están las clases y funciones de productos de WooCommerce? */
	public static function available(): bool {
		return class_exists( '\\WC_Product_Variation' ) && class_exists( '\\WC_Product_Variable' ) && function_exists( 'wc_get_product' ) && function_exists( 'wc_delete_product_transients' );
	}

	// ── Exportar ────────────────────────────────────────────────────────────────────────────────────────────────

	public function export( Export_Context $context ): ?array {
		self::$exported[ spl_object_id( $context ) ] = 0;
		if ( ! self::available() || ! $context->includes( 'content' ) ) {
			return null;
		}
		$out       = array();
		$count     = 0;
		$downloads = 0;
		foreach ( self::children( $context ) as $parent => $ids ) {
			foreach ( $ids as $id ) {
				$variation = wc_get_product( $id );
				if ( ! $variation instanceof \WC_Product_Variation ) {
					continue;
				}
				$row = self::row( $variation );
				if ( $row['downloadable'] && count( (array) $variation->get_downloads( 'edit' ) ) > 0 ) {
					++$downloads;
				}
				$out[ (string) $parent ][] = $row;
				++$count;
			}
		}
		if ( $downloads > 0 ) {
			$context->not_exported( 'downloads', $downloads );
		}
		self::$exported[ spl_object_id( $context ) ] = $count;
		return $out ? array(
			'v'          => self::VERSION,
			'variations' => $out,
		) : null;
	}

	/**
	 * `digitalisimo_elements_kit_not_exported` (V.11.4): `variations` baja en las que esta parte exportó con ese
	 * contexto. K acota el resultado: nunca sube ni crea un motivo.
	 *
	 * @param mixed $map     Motivo => número.
	 * @param mixed $context Contexto de exportación.
	 * @return mixed
	 */
	public static function not_exported( $map, $context = null ) {
		if ( ! is_array( $map ) || ! isset( $map['variations'] ) || ! is_object( $context ) ) {
			return $map;
		}
		$done = isset( self::$exported[ spl_object_id( $context ) ] ) ? self::$exported[ spl_object_id( $context ) ] : 0;
		if ( $done > 0 ) {
			$map['variations'] = max( 0, (int) $map['variations'] - $done );
		}
		return $map;
	}

	/** Imágenes de las variaciones que se exportan (para `Media_Runner`), sin repetir. */
	public static function image_ids( Export_Context $context ): array {
		if ( ! self::available() ) {
			return array();
		}
		$ids = array();
		foreach ( self::children( $context ) as $children ) {
			foreach ( $children as $id ) {
				$image = (int) get_post_meta( (int) $id, '_thumbnail_id', true );
				if ( $image > 0 ) {
					$ids[ $image ] = true;
				}
			}
		}
		$ids = array_keys( $ids );
		sort( $ids );
		return $ids;
	}

	/**
	 * Variaciones `publish` y `private` de los productos variables exportados, por padre: dos consultas (los productos
	 * entre lo exportado y sus hijas) más la lectura de cada padre con hijas.
	 *
	 * @return array<int,int[]> ID del padre => IDs de sus variaciones, por `menu_order` e ID.
	 */
	private static function children( Export_Context $context ): array {
		$exported = array();
		foreach ( $context->exported( 'post' ) as $id ) {
			if ( is_int( $id ) || ctype_digit( (string) $id ) ) {
				$exported[] = (int) $id;
			}
		}
		if ( ! $exported || ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$parents = get_posts(
			array(
				'post_type'        => 'product',
				'post__in'         => $exported,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		if ( ! $parents ) {
			return array();
		}
		// Entradas completas, sin precargar metas ni términos: `fields => 'id=>parent'` no sirve, porque WP_Query devuelve
		// `ID => padre` y, con la consulta en caché (la segunda vez en la misma petición: Media_Runner y después la
		// parte), `'post_parent:<ID>' => padre` (WP_Query::get_posts()).
		$rows = get_posts(
			array(
				'post_type'              => 'product_variation',
				'post_parent__in'        => array_map( 'intval', $parents ),
				'post_status'            => self::STATUSES,
				'posts_per_page'         => -1,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( is_object( $row ) && isset( $row->ID, $row->post_parent ) && (int) $row->ID > 0 && (int) $row->post_parent > 0 ) {
				$out[ (int) $row->post_parent ][] = (int) $row->ID;
			}
		}
		foreach ( array_keys( $out ) as $parent ) {
			$product = wc_get_product( $parent );
			if ( ! is_object( $product ) || ! method_exists( $product, 'is_type' ) || ! $product->is_type( 'variable' ) ) {
				unset( $out[ $parent ] );
			}
		}
		ksort( $out );
		return $out;
	}

	/** Fila de una variación para el kit. */
	private static function row( \WC_Product_Variation $variation ): array {
		$from  = $variation->get_date_on_sale_from( 'edit' );
		$to    = $variation->get_date_on_sale_to( 'edit' );
		$class = (int) $variation->get_shipping_class_id( 'edit' );
		$term  = $class > 0 && function_exists( 'get_term' ) ? get_term( $class, 'product_shipping_class' ) : null;
		$stock = $variation->get_stock_quantity( 'edit' );
		$row   = array(
			'id'             => (int) $variation->get_id(),
			'status'         => (string) $variation->get_status( 'edit' ),
			'menu_order'     => (int) $variation->get_menu_order( 'edit' ),
			'attributes'     => self::scalars( (array) $variation->get_attributes( 'edit' ) ),
			'regular_price'  => (string) $variation->get_regular_price( 'edit' ),
			'sale_price'     => (string) $variation->get_sale_price( 'edit' ),
			'sale_from'      => is_object( $from ) && method_exists( $from, 'getTimestamp' ) ? (int) $from->getTimestamp() : null,
			'sale_to'        => is_object( $to ) && method_exists( $to, 'getTimestamp' ) ? (int) $to->getTimestamp() : null,
			'sku'            => (string) $variation->get_sku( 'edit' ),
			'manage_stock'   => (bool) $variation->get_manage_stock( 'edit' ),
			'stock'          => null === $stock ? null : (float) $stock,
			'stock_status'   => (string) $variation->get_stock_status( 'edit' ),
			'backorders'     => (string) $variation->get_backorders( 'edit' ),
			'weight'         => (string) $variation->get_weight( 'edit' ),
			'length'         => (string) $variation->get_length( 'edit' ),
			'width'          => (string) $variation->get_width( 'edit' ),
			'height'         => (string) $variation->get_height( 'edit' ),
			'shipping_class' => is_object( $term ) && isset( $term->slug ) ? (string) $term->slug : '',
			'tax_class'      => (string) $variation->get_tax_class( 'edit' ),
			'description'    => (string) $variation->get_description( 'edit' ),
			'virtual'        => (bool) $variation->get_virtual( 'edit' ),
			'downloadable'   => (bool) $variation->get_downloadable( 'edit' ),
			'image'          => (int) $variation->get_image_id( 'edit' ),
			'meta'           => array(),
		);
		foreach ( (array) $variation->get_meta_data() as $meta ) {
			$key = is_object( $meta ) && isset( $meta->key ) ? (string) $meta->key : '';
			if ( ! self::exportable_meta( $key ) || count( $row['meta'] ) >= self::MAX_METAS ) {
				continue;
			}
			list( $ok, $value ) = Meta_Blacklist::value( isset( $meta->value ) ? $meta->value : null );
			if ( $ok ) {
				$row['meta'][] = array( $key, $value );
			}
		}
		return $row;
	}

	/** ¿Puede viajar una meta suelta? Ni las internas de WooCommerce, ni las de atributos, ni la lista negra de K. */
	private static function exportable_meta( string $key ): bool {
		return '' !== $key && 1 === preg_match( self::KEY_PATTERN, $key ) && ! Meta_Blacklist::blocks( $key )
			&& ! in_array( $key, self::INTERNAL_META, true ) && 0 !== stripos( $key, 'attribute_' ) && 0 !== stripos( $key, 'wp_' );
	}

	/** Pares clave => valor escalar (los atributos de una variación). */
	private static function scalars( array $values ): array {
		$out = array();
		foreach ( $values as $key => $value ) {
			if ( is_string( $key ) && ( is_scalar( $value ) || null === $value ) ) {
				$out[ $key ] = (string) $value;
			}
		}
		return $out;
	}

	// ── Importar ────────────────────────────────────────────────────────────────────────────────────────────────

	public function import( array $data, int $version, Import_Context $context ): array {
		$summary = array(
			'created' => 0,
			'kept'    => 0,
			'skipped' => 0,
			'sku'     => 0,
			'lookup'  => 0,
		);
		$groups  = isset( $data['variations'] ) && is_array( $data['variations'] ) ? $data['variations'] : array();
		if ( ! self::available() ) {
			if ( $groups ) {
				$context->report( 'woocommerce.inactive', array( 'part' => self::KEY, 'count' => count( $groups ) ), Part_Context::WARNING );
			}
			return $summary;
		}
		$stored   = $context->stored();
		$created  = isset( $stored['created'] ) && is_array( $stored['created'] ) ? $stored['created'] : array();
		$remapper = $groups ? new Remapper( $context ) : null;
		$total    = 0;
		foreach ( $groups as $old_parent => $rows ) {
			if ( ! is_scalar( $old_parent ) || ! ctype_digit( (string) $old_parent ) || ! is_array( $rows ) ) {
				continue;
			}
			$count  = count( $rows );
			$parent = (int) $context->mapped( 'post', (int) $old_parent, 0 );
			if ( $parent <= 0 ) {
				$summary['skipped'] += $count;
				$context->report( 'woocommerce.variations-orphan', array( 'parent' => (int) $old_parent, 'count' => $count ), Part_Context::WARNING );
				continue;
			}
			if ( ! $context->is_new_post( $parent ) ) {
				$summary['kept'] += $count;
				$context->report( 'woocommerce.variations-kept', array( 'product' => $parent, 'count' => $count ) );
				continue;
			}
			if ( isset( $created[ (string) $parent ] ) ) {
				continue; // Ya creadas por esta sesión en otra petición.
			}
			$product = wc_get_product( $parent );
			if ( ! is_object( $product ) || ! method_exists( $product, 'is_type' ) || ! $product->is_type( 'variable' ) ) {
				$summary['skipped'] += $count;
				$context->report( 'woocommerce.variations-parent', array( 'product' => $parent, 'count' => $count ), Part_Context::WARNING );
				continue;
			}
			$made = array();
			foreach ( array_slice( $rows, 0, self::MAX_PER_PARENT ) as $row ) {
				if ( $total >= self::MAX_TOTAL ) {
					break;
				}
				++$total;
				$variation = is_array( $row ) ? self::create( $parent, $row, $context, $remapper, $summary ) : null;
				if ( null === $variation ) {
					++$summary['skipped'];
					continue;
				}
				$made[ (string) $variation->get_id() ] = array(
					'hash'   => self::fingerprint( $variation ),
					'status' => (string) $variation->get_status( 'edit' ),
				);
				++$summary['created'];
			}
			if ( $count > self::MAX_PER_PARENT ) {
				$summary['skipped'] += $count - self::MAX_PER_PARENT;
				$context->report( 'woocommerce.variations-limit', array( 'product' => $parent, 'limit' => self::MAX_PER_PARENT ), Part_Context::WARNING );
			}
			\WC_Product_Variable::sync( $parent );
			wc_delete_product_transients( $parent );
			$created[ (string) $parent ] = $made;
		}
		$summary['lookup'] = self::refresh_lookup( $context );
		if ( $summary['lookup'] > 0 ) {
			$context->report( 'woocommerce.lookup-refreshed', array( 'products' => $summary['lookup'] ) );
		}
		$context->store( array( 'created' => $created ) );
		return $summary;
	}

	/**
	 * Crea una variación bajo un padre de la sesión; null si su forma no vale o un término de atributo no existe.
	 *
	 * @param array $summary Contadores del resumen (SKU repetidos).
	 */
	private static function create( int $parent, array $row, Import_Context $context, ?Remapper $remapper, array &$summary ): ?\WC_Product_Variation {
		$attributes = self::attributes( isset( $row['attributes'] ) ? $row['attributes'] : array() );
		if ( null === $attributes ) {
			$context->report( 'woocommerce.variation-attribute', array( 'product' => $parent, 'variation' => isset( $row['id'] ) ? (int) $row['id'] : 0 ), Part_Context::WARNING );
			return null;
		}
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent );
		$variation->set_attributes( $attributes );
		$manage = ! empty( $row['manage_stock'] );
		$props  = array(
			'status'            => isset( $row['status'] ) && in_array( $row['status'], self::STATUSES, true ) ? $row['status'] : 'publish',
			'menu_order'        => isset( $row['menu_order'] ) ? (int) $row['menu_order'] : 0,
			'regular_price'     => self::decimal( isset( $row['regular_price'] ) ? $row['regular_price'] : '' ),
			'sale_price'        => self::decimal( isset( $row['sale_price'] ) ? $row['sale_price'] : '' ),
			'date_on_sale_from' => isset( $row['sale_from'] ) && is_int( $row['sale_from'] ) && $row['sale_from'] > 0 ? $row['sale_from'] : null,
			'date_on_sale_to'   => isset( $row['sale_to'] ) && is_int( $row['sale_to'] ) && $row['sale_to'] > 0 ? $row['sale_to'] : null,
			'manage_stock'      => $manage,
			'stock_quantity'    => $manage && isset( $row['stock'] ) && is_numeric( $row['stock'] ) ? ( function_exists( 'wc_stock_amount' ) ? wc_stock_amount( $row['stock'] ) : (int) $row['stock'] ) : null,
			'stock_status'      => isset( $row['stock_status'] ) && in_array( $row['stock_status'], self::STOCK_STATUSES, true ) ? $row['stock_status'] : 'instock',
			'backorders'        => isset( $row['backorders'] ) && in_array( $row['backorders'], self::BACKORDERS, true ) ? $row['backorders'] : 'no',
			'weight'            => self::decimal( isset( $row['weight'] ) ? $row['weight'] : '' ),
			'length'            => self::decimal( isset( $row['length'] ) ? $row['length'] : '' ),
			'width'             => self::decimal( isset( $row['width'] ) ? $row['width'] : '' ),
			'height'            => self::decimal( isset( $row['height'] ) ? $row['height'] : '' ),
			'shipping_class_id' => self::shipping_class( isset( $row['shipping_class'] ) ? $row['shipping_class'] : '', $parent, $context ),
			'tax_class'         => self::tax_class( isset( $row['tax_class'] ) ? $row['tax_class'] : 'parent' ),
			'description'       => isset( $row['description'] ) && is_string( $row['description'] ) ? ( function_exists( 'wp_kses_post' ) ? wp_kses_post( $row['description'] ) : strip_tags( $row['description'] ) ) : '',
			'virtual'           => ! empty( $row['virtual'] ),
			'downloadable'      => ! empty( $row['downloadable'] ),
			'image_id'          => self::image( isset( $row['image'] ) ? $row['image'] : 0, $parent, $row, $remapper, $context ),
		);
		$variation->set_props( $props );
		$sku = isset( $row['sku'] ) && is_string( $row['sku'] ) ? trim( $row['sku'] ) : '';
		if ( '' !== $sku ) {
			try {
				$variation->set_sku( $sku );
			} catch ( \Exception $error ) {
				// Un SKU que ya usa otro producto del sitio: la variación entra sin SKU (los SKU son únicos).
				++$summary['sku'];
				$context->report( 'woocommerce.sku-conflict', array( 'product' => $parent, 'sku' => $sku ), Part_Context::WARNING );
			}
		}
		foreach ( isset( $row['meta'] ) && is_array( $row['meta'] ) ? array_slice( $row['meta'], 0, self::MAX_METAS ) : array() as $pair ) {
			if ( ! is_array( $pair ) || ! isset( $pair[0] ) || ! is_string( $pair[0] ) || ! self::exportable_meta( $pair[0] ) ) {
				continue;
			}
			list( $ok, $value ) = Meta_Blacklist::value( array_key_exists( 1, $pair ) ? $pair[1] : null );
			if ( $ok ) {
				$variation->add_meta_data( $pair[0], $value, false );
			}
		}
		try {
			$id = (int) $variation->save();
		} catch ( \Exception $error ) {
			$id = 0;
		}
		if ( $id <= 0 ) {
			$context->report( 'woocommerce.variation-failed', array( 'product' => $parent ), Part_Context::WARNING );
			return null;
		}
		return $variation;
	}

	/**
	 * Atributos de una fila: los globales (`pa_*`) sólo con un término que el destino tiene; vacío vale «cualquiera»
	 * sólo si ya lo era en el origen. Null si alguno no se puede resolver (la variación no se crea: nunca se amplía).
	 */
	private static function attributes( $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$out = array();
		foreach ( $raw as $key => $value ) {
			if ( ! is_string( $key ) || ! is_scalar( $value ) ) {
				return null;
			}
			$name  = function_exists( 'sanitize_title' ) ? sanitize_title( $key ) : strtolower( $key );
			$value = (string) $value;
			if ( '' === $name || strlen( $name ) > 200 ) {
				return null;
			}
			if ( 0 === strpos( $name, 'pa_' ) && '' !== $value ) {
				if ( ! taxonomy_exists( $name ) ) {
					return null;
				}
				$term = get_term_by( 'slug', $value, $name );
				if ( ! is_object( $term ) || ! isset( $term->slug ) ) {
					return null;
				}
				$value = (string) $term->slug;
			} elseif ( '' !== $value ) {
				$value = function_exists( 'wc_clean' ) ? (string) wc_clean( $value ) : trim( strip_tags( $value ) );
				$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 200 ) : substr( $value, 0, 200 );
			}
			$out[ $name ] = $value;
		}
		return $out;
	}

	/** Número decimal de WooCommerce, o '' si no es un número. */
	private static function decimal( $value ): string {
		if ( ! is_scalar( $value ) || '' === (string) $value || ! is_numeric( $value ) ) {
			return '';
		}
		return function_exists( 'wc_format_decimal' ) ? (string) wc_format_decimal( $value ) : (string) $value;
	}

	/** Clase de envío del destino con ese *slug*, o 0 (informa si el kit nombraba una que el sitio no tiene). */
	private static function shipping_class( $slug, int $parent, Import_Context $context ): int {
		if ( ! is_string( $slug ) || '' === $slug ) {
			return 0;
		}
		$term = function_exists( 'get_term_by' ) ? get_term_by( 'slug', $slug, 'product_shipping_class' ) : null;
		if ( is_object( $term ) && isset( $term->term_id ) ) {
			return (int) $term->term_id;
		}
		$context->report( 'woocommerce.shipping-class', array( 'product' => $parent, 'class' => $slug ) );
		return 0;
	}

	/** `parent`, '' (estándar) o una clase de impuesto del destino; cualquier otra, `parent`. */
	private static function tax_class( $value ): string {
		if ( ! is_string( $value ) || 'parent' === $value || '' === $value ) {
			return is_string( $value ) ? $value : 'parent';
		}
		$classes = class_exists( '\\WC_Tax' ) && method_exists( '\\WC_Tax', 'get_tax_class_slugs' ) ? (array) \WC_Tax::get_tax_class_slugs() : array();
		return in_array( $value, $classes, true ) ? $value : 'parent';
	}

	/** Imagen nueva (mapa `attachment` de la parte `media`), o 0 con `reference.attachment` si no tiene destino. */
	private static function image( $old, int $parent, array $row, ?Remapper $remapper, Import_Context $context ): int {
		$old = is_numeric( $old ) ? (int) $old : 0;
		if ( $old <= 0 ) {
			return 0;
		}
		$new = null === $remapper ? null : $remapper->map_id( 'attachment', $old );
		if ( null !== $new && (int) $new > 0 && function_exists( 'get_post_type' ) && 'attachment' === get_post_type( (int) $new ) ) {
			return (int) $new;
		}
		$context->report(
			'reference.attachment',
			array(
				'product'    => $parent,
				'variation'  => isset( $row['id'] ) ? (int) $row['id'] : 0,
				'attachment' => $old,
			),
			Part_Context::WARNING
		);
		return 0;
	}

	/**
	 * V.11.3: refresca la fila de `wc_product_meta_lookup` de cada producto que creó la sesión (el WXR los crea sin
	 * ella). Una consulta para hallarlos y una escritura por producto.
	 */
	private static function refresh_lookup( Import_Context $context ): int {
		$ids = array();
		foreach ( $context->map( 'post' ) as $new ) {
			if ( is_numeric( $new ) && (int) $new > 0 && $context->is_new_post( (int) $new ) ) {
				$ids[ (int) $new ] = true;
			}
		}
		if ( ! $ids || ! function_exists( 'get_posts' ) ) {
			return 0;
		}
		$products = get_posts(
			array(
				'post_type'        => 'product',
				'post__in'         => array_keys( $ids ),
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
		if ( ! $products ) {
			return 0;
		}
		$store = null;
		try {
			$store = class_exists( '\\WC_Data_Store' ) ? \WC_Data_Store::load( 'product' ) : null;
		} catch ( \Exception $error ) {
			$store = null;
		}
		$direct = is_object( $store ) && method_exists( $store, 'has_callable' ) && $store->has_callable( 'refresh_product_lookup_table' );
		$done   = 0;
		foreach ( $products as $id ) {
			$id = (int) $id;
			if ( $direct ) {
				$store->refresh_product_lookup_table( $id );
				++$done;
				continue;
			}
			$product = wc_get_product( $id );
			if ( is_object( $product ) && method_exists( $product, 'save' ) ) {
				$product->save();
				++$done;
			}
		}
		return $done;
	}

	/** Huella de una variación (`Part_Context::hash()` de `state()`). */
	public static function fingerprint( \WC_Product_Variation $variation ): string {
		return Part_Context::hash( self::state( $variation ) );
	}

	/** Datos de WooCommerce de una variación para su huella, sin el estado (que la papelera del padre cambia). */
	public static function state( \WC_Product_Variation $variation ): array {
		$from = $variation->get_date_on_sale_from( 'edit' );
		$to   = $variation->get_date_on_sale_to( 'edit' );
		return array(
			'parent'         => (int) $variation->get_parent_id( 'edit' ),
			'menu_order'     => (int) $variation->get_menu_order( 'edit' ),
			'attributes'     => self::scalars( (array) $variation->get_attributes( 'edit' ) ),
			'regular_price'  => (string) $variation->get_regular_price( 'edit' ),
			'sale_price'     => (string) $variation->get_sale_price( 'edit' ),
			'sale_from'      => is_object( $from ) && method_exists( $from, 'getTimestamp' ) ? (int) $from->getTimestamp() : null,
			'sale_to'        => is_object( $to ) && method_exists( $to, 'getTimestamp' ) ? (int) $to->getTimestamp() : null,
			'sku'            => (string) $variation->get_sku( 'edit' ),
			'manage_stock'   => (bool) $variation->get_manage_stock( 'edit' ),
			'stock'          => $variation->get_stock_quantity( 'edit' ),
			'stock_status'   => (string) $variation->get_stock_status( 'edit' ),
			'backorders'     => (string) $variation->get_backorders( 'edit' ),
			'weight'         => (string) $variation->get_weight( 'edit' ),
			'length'         => (string) $variation->get_length( 'edit' ),
			'width'          => (string) $variation->get_width( 'edit' ),
			'height'         => (string) $variation->get_height( 'edit' ),
			'shipping_class' => (int) $variation->get_shipping_class_id( 'edit' ),
			'tax_class'      => (string) $variation->get_tax_class( 'edit' ),
			'description'    => (string) $variation->get_description( 'edit' ),
			'virtual'        => (bool) $variation->get_virtual( 'edit' ),
			'downloadable'   => (bool) $variation->get_downloadable( 'edit' ),
			'image'          => (int) $variation->get_image_id( 'edit' ),
		);
	}

	// ── Revertir ────────────────────────────────────────────────────────────────────────────────────────────────

	public function revert( array $stored, Revert_Context $context ): void {
		if ( ! self::available() ) {
			return;
		}
		$touched = array();
		foreach ( isset( $stored['created'] ) && is_array( $stored['created'] ) ? $stored['created'] : array() as $parent => $items ) {
			foreach ( is_array( $items ) ? $items : array() as $id => $info ) {
				$id   = (int) $id;
				$post = $id > 0 && function_exists( 'get_post' ) ? get_post( $id ) : null;
				if ( ! is_object( $post ) || 'product_variation' !== $post->post_type ) {
					continue; // WooCommerce la borró con su padre.
				}
				$variation = wc_get_product( $id );
				if ( ! $variation instanceof \WC_Product_Variation ) {
					continue;
				}
				$status = (string) $variation->get_status( 'edit' );
				$same   = is_array( $info ) && isset( $info['hash'] ) && $context->unchanged( self::state( $variation ), (string) $info['hash'] )
					&& ( 'trash' === $status || ( isset( $info['status'] ) && (string) $info['status'] === $status ) );
				if ( ! $same ) {
					$context->report( 'woocommerce.variation-edited', array( 'variation' => $id ), Part_Context::INFO );
					continue;
				}
				$variation->delete( true );
				$touched[ (int) $parent ] = true;
			}
		}
		foreach ( array_keys( $touched ) as $parent ) {
			if ( $parent > 0 && is_object( wc_get_product( $parent ) ) ) {
				\WC_Product_Variable::sync( $parent );
				wc_delete_product_transients( $parent );
			}
		}
	}
}
