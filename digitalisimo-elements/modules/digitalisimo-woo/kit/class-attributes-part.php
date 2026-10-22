<?php
namespace Digitalisimo\Elements\Woo\Kit;

use Digitalisimo\Elements\Import\Kits\Early_Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Parts\Term_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Parte temprana `woo-attributes` del kit (F4-Woo, V.11.1): los atributos globales de WooCommerce y su configuración
 * de muestras. `digitalisimo/woo-attributes.json` = `{ v: 1, attributes: { <slug>: { label, type, orderby,
 * has_archives, hash, swatches? } } }`, por *slug* y sin IDs del origen. `kit_meta_keys()` está vacío: los atributos
 * son una tabla de WooCommerce y la configuración de muestras, la opción `digitalisimo_elements_swatches`.
 *
 * - **Exportar:** todos los atributos cuya taxonomía `pa_*` exporta el núcleo (`manifest.taxonomies`: las de los
 *   tipos de entrada exportados con algún término, la misma regla que `Parts\Term_Meta::exported_terms()`), con su
 *   entrada de muestras (`Swatches\Data::entry()`) y su huella.
 * - **`import_early()`** (runner `plugins`, antes de taxonomías y contenido): un atributo con ese *slug* en el destino
 *   se reutiliza sin cambiarlo (`woocommerce.attribute-differs` si la etiqueta o el tipo no coinciden); si no,
 *   `wc_create_attribute()` y `register_taxonomy()` en la misma petición, como el importador CSV de WooCommerce. En
 *   «agregar» siempre con `has_archives = 0` (`woocommerce.attribute-archives-off`): importar nunca abre un espacio de
 *   URL públicas nuevo. En «nuevo», tal cual. WooCommerce valida el tipo: uno que el destino no ofrece (como
 *   `wc-visual` con la función apagada) vuelve a `select`.
 * - **`import()`:** la configuración de muestras, sólo de los atributos que creó la sesión (`Swatches\Data::put()`,
 *   bajo su cerrojo); la de uno que ya existía no se aplica (`woocommerce.swatches-skipped`). La imagen de cada
 *   término viaja con la parte `term-meta` de K y la referencia acotada a `pa_*` (V.6.6).
 * - **`revert()`:** borra los atributos creados cuya huella no cambió, sin muestras editadas y que ningún término ni
 *   producto ajeno a la sesión usa; los demás se conservan y se informan.
 *
 * Sin WooCommerce, no exporta nada e informa `woocommerce.inactive` si el kit trae atributos: los términos `pa_*`
 * del kit los descarta el núcleo como siempre (`taxonomies.missing`).
 */
final class Attributes_Part implements Early_Part {
	const KEY     = 'woo-attributes';
	const VERSION = 1;

	/** Atributos que se leen de un kit, como mucho. */
	const MAX = 200;

	const ORDERBY      = array( 'menu_order', 'name', 'name_num', 'id' );
	const TYPE_PATTERN = '/^[a-z0-9_-]{1,20}$/';
	const LABEL_MAX    = 200;
	const SESSION_META = '_elementor_import_session_id';

	/** Contrato del frente D (V.15.2). */
	const SWATCHES = '\\Digitalisimo\\Elements\\Woo\\Swatches\\Data';

	public function key(): string {
		return self::KEY;
	}

	public function version(): int {
		return self::VERSION;
	}

	public function kit_meta_keys(): array {
		return array();
	}

	/** ¿Están las funciones de atributos de WooCommerce? */
	public static function available(): bool {
		foreach ( array( 'wc_get_attribute_taxonomies', 'wc_create_attribute', 'wc_get_attribute', 'wc_delete_attribute', 'wc_attribute_taxonomy_name', 'wc_attribute_taxonomy_id_by_name' ) as $function ) {
			if ( ! function_exists( $function ) ) {
				return false;
			}
		}
		return true;
	}

	public function export( Export_Context $context ): ?array {
		if ( ! self::available() ) {
			return null;
		}
		$taxonomies = self::exported_taxonomies( $context );
		if ( ! $taxonomies ) {
			return null;
		}
		$out = array();
		foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
			if ( ! is_object( $attribute ) || ! isset( $attribute->attribute_name ) ) {
				continue;
			}
			$slug = (string) $attribute->attribute_name;
			if ( '' === self::valid_slug( $slug ) || ! isset( $taxonomies[ wc_attribute_taxonomy_name( $slug ) ] ) ) {
				continue;
			}
			$entry         = array(
				'label'        => isset( $attribute->attribute_label ) && '' !== (string) $attribute->attribute_label ? (string) $attribute->attribute_label : $slug,
				'type'         => isset( $attribute->attribute_type ) ? (string) $attribute->attribute_type : 'select',
				'orderby'      => isset( $attribute->attribute_orderby ) ? (string) $attribute->attribute_orderby : 'menu_order',
				'has_archives' => ! empty( $attribute->attribute_public ),
			);
			$entry['hash'] = self::fingerprint( $slug, $entry );
			$swatches      = self::swatches_entry( $slug );
			if ( null !== $swatches ) {
				$entry['swatches'] = $swatches;
			}
			$out[ $slug ] = $entry;
		}
		if ( ! $out ) {
			return null;
		}
		ksort( $out, SORT_STRING );
		return array(
			'v'          => self::VERSION,
			'attributes' => $out,
		);
	}

	public function import_early( array $data, int $version, Import_Context $context ): array {
		$attributes = self::incoming( $data, $context );
		if ( ! $attributes ) {
			return array();
		}
		if ( ! self::available() ) {
			$context->report(
				'woocommerce.inactive',
				array(
					'part'  => self::KEY,
					'count' => count( $attributes ),
				),
				Part_Context::WARNING
			);
			return array( 'inactive' => count( $attributes ) );
		}
		$stored  = $context->stored();
		$created = isset( $stored['attributes'] ) && is_array( $stored['attributes'] ) ? $stored['attributes'] : array();
		$summary = array(
			'created' => 0,
			'reused'  => 0,
			'failed'  => 0,
		);
		$closed  = array();
		foreach ( $attributes as $slug => $entry ) {
			if ( isset( $created[ $slug ] ) ) {
				continue; // Ya lo creó esta sesión en otra petición.
			}
			$existing = (int) wc_attribute_taxonomy_id_by_name( $slug );
			if ( $existing > 0 ) {
				++$summary['reused'];
				self::report_differs( $slug, $entry, $existing, $context );
				continue;
			}
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			if ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( $taxonomy ) ) {
				// Otra extensión ya registra esa taxonomía: WooCommerce no puede crear el atributo.
				++$summary['failed'];
				$context->report( 'woocommerce.attribute-failed', array( 'attribute' => $slug, 'reason' => 'taxonomy' ), Part_Context::WARNING );
				continue;
			}
			$archives = $context->is_fresh() && $entry['has_archives'];
			$id       = wc_create_attribute(
				array(
					'name'         => $entry['label'],
					'slug'         => $slug,
					'type'         => $entry['type'],
					'order_by'     => $entry['orderby'],
					'has_archives' => $archives,
				)
			);
			if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $id ) ) || ! is_numeric( $id ) || (int) $id <= 0 ) {
				++$summary['failed'];
				$context->report(
					'woocommerce.attribute-failed',
					array(
						'attribute' => $slug,
						'reason'    => is_object( $id ) && method_exists( $id, 'get_error_code' ) ? (string) $id->get_error_code() : 'create',
					),
					Part_Context::WARNING
				);
				continue;
			}
			if ( $entry['has_archives'] && ! $archives ) {
				$closed[] = $slug;
			}
			self::register_taxonomy( $taxonomy, $entry['label'], $archives );
			$created[ $slug ] = array(
				'id'   => (int) $id,
				'hash' => self::current_hash( (int) $id ),
			);
			++$summary['created'];
		}
		if ( $closed ) {
			$context->report( 'woocommerce.attribute-archives-off', array( 'attributes' => $closed ), Part_Context::INFO );
		}
		if ( $summary['created'] > 0 ) {
			self::refresh_globals();
		}
		$context->store( array( 'attributes' => $created ) );
		return $summary;
	}

	public function import( array $data, int $version, Import_Context $context ): array {
		$attributes = self::incoming( $data );
		if ( ! $attributes || ! self::available() ) {
			return array();
		}
		$stored  = $context->stored();
		$created = isset( $stored['attributes'] ) && is_array( $stored['attributes'] ) ? $stored['attributes'] : array();
		$written = isset( $stored['swatches'] ) && is_array( $stored['swatches'] ) ? $stored['swatches'] : array();
		$class   = self::swatches_class();
		$summary = array(
			'attributes' => count( $created ),
			'swatches'   => 0,
			'same'       => 0,
			'skipped'    => 0,
			'failed'     => 0,
		);
		foreach ( $attributes as $slug => $entry ) {
			if ( null === $entry['swatches'] ) {
				continue;
			}
			if ( '' === $class ) {
				++$summary['failed'];
				$context->report( 'woocommerce.swatches-unavailable', array( 'attribute' => $slug ), Part_Context::WARNING );
				continue;
			}
			$clean = $class::sanitize_entry( $entry['swatches'] );
			if ( null === $clean || 'select' === $clean['type'] ) {
				continue;
			}
			$site = $class::entry( $slug );
			if ( $site === $clean ) {
				++$summary['same'];
				continue;
			}
			// Sólo en un atributo que creó esta sesión; en «agregar», nunca sobre una entrada que ya tenía el sitio.
			if ( ! isset( $created[ $slug ] ) || isset( $written[ $slug ] ) || ( null !== $site && ! $context->is_fresh() ) ) {
				++$summary['skipped'];
				$context->report( 'woocommerce.swatches-skipped', array( 'attribute' => $slug ), Part_Context::INFO );
				continue;
			}
			if ( ! $class::put( $slug, $clean ) ) {
				++$summary['failed'];
				$context->report( 'woocommerce.swatches-failed', array( 'attribute' => $slug ), Part_Context::WARNING );
				continue;
			}
			$written[ $slug ] = Part_Context::hash( $class::entry( $slug ) );
			++$summary['swatches'];
		}
		$context->store( array( 'swatches' => $written ) );
		return $summary;
	}

	public function revert( array $stored, Revert_Context $context ): void {
		if ( ! self::available() ) {
			return;
		}
		$created  = isset( $stored['attributes'] ) && is_array( $stored['attributes'] ) ? $stored['attributes'] : array();
		$swatches = isset( $stored['swatches'] ) && is_array( $stored['swatches'] ) ? $stored['swatches'] : array();
		$class    = self::swatches_class();
		$deleted  = false;
		foreach ( $created as $slug => $info ) {
			$slug = (string) $slug;
			$id   = is_array( $info ) && isset( $info['id'] ) ? (int) $info['id'] : 0;
			$attr = $id > 0 ? wc_get_attribute( $id ) : null;
			$hash = is_array( $info ) && isset( $info['hash'] ) ? (string) $info['hash'] : '';
			if ( ! is_object( $attr ) ) {
				continue; // Ya no existe: WooCommerce borró también su entrada de muestras (gancho del frente D).
			}
			$entry        = '' === $class ? null : $class::entry( $slug );
			$swatches_ok  = isset( $swatches[ $slug ] ) ? $context->unchanged( $entry, (string) $swatches[ $slug ] ) : null === $entry;
			if ( ! $context->unchanged( self::print_of( $attr ), $hash ) || ! $swatches_ok ) {
				$context->report( 'woocommerce.attribute-edited', array( 'attribute' => $slug ), Part_Context::INFO );
				continue;
			}
			if ( self::in_use( wc_attribute_taxonomy_name( $slug ), $context->session() ) ) {
				$context->report( 'woocommerce.attribute-in-use', array( 'attribute' => $slug ), Part_Context::INFO );
				continue;
			}
			if ( wc_delete_attribute( $id ) ) {
				$deleted = true;
				if ( '' !== $class && null !== $entry ) {
					$class::remove( $slug );
				}
			}
		}
		if ( $deleted ) {
			self::refresh_globals();
		}
	}

	// ── Utilidades ──────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Taxonomías `pa_*` que exporta el núcleo: las de los términos de `Term_Meta::exported_terms()` (tipos de entrada
	 * exportados; el runner `taxonomies` omite las que no tienen términos).
	 *
	 * @return array<string,bool>
	 */
	public static function exported_taxonomies( Export_Context $context ): array {
		$out = array();
		foreach ( Term_Meta::exported_terms( $context ) as $term ) {
			$taxonomy = isset( $term->taxonomy ) ? (string) $term->taxonomy : '';
			if ( 0 === strpos( $taxonomy, 'pa_' ) ) {
				$out[ $taxonomy ] = true;
			}
		}
		return $out;
	}

	/** *Slug* de atributo válido (el de `wc_sanitize_taxonomy_name()`, sin `pa_` y dentro del tope de bytes), o ''. */
	public static function valid_slug( string $slug ): string {
		$max = function_exists( 'wc_get_attribute_slug_max_byte_length' ) ? (int) wc_get_attribute_slug_max_byte_length() : 28;
		if ( '' === $slug || strlen( $slug ) > $max || 0 === strpos( $slug, 'pa_' ) ) {
			return '';
		}
		if ( function_exists( 'wc_sanitize_taxonomy_name' ) ) {
			return wc_sanitize_taxonomy_name( $slug ) === $slug ? $slug : '';
		}
		return 1 === preg_match( '/^[a-z0-9_-]+$/', $slug ) ? $slug : '';
	}

	/**
	 * Atributos del kit con su forma comprobada: `slug => { label, type, orderby, has_archives, swatches }`. Lo que no
	 * tiene forma se descarta (con aviso si hay contexto) y como mucho se leen `MAX`.
	 */
	private static function incoming( array $data, ?Import_Context $context = null ): array {
		$raw     = isset( $data['attributes'] ) && is_array( $data['attributes'] ) ? $data['attributes'] : array();
		$out     = array();
		$invalid = array();
		foreach ( $raw as $slug => $entry ) {
			$clean = self::valid_slug( (string) $slug );
			if ( '' === $clean || ! is_array( $entry ) ) {
				$invalid[] = (string) $slug;
				continue;
			}
			if ( count( $out ) >= self::MAX ) {
				$invalid[] = $clean;
				continue;
			}
			$label = isset( $entry['label'] ) && is_string( $entry['label'] ) ? trim( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $entry['label'] ) : strip_tags( $entry['label'] ) ) : '';
			$label = '' === $label ? $clean : ( function_exists( 'mb_substr' ) ? mb_substr( $label, 0, self::LABEL_MAX ) : substr( $label, 0, self::LABEL_MAX ) );
			$type  = isset( $entry['type'] ) && is_string( $entry['type'] ) && 1 === preg_match( self::TYPE_PATTERN, $entry['type'] ) ? $entry['type'] : 'select';
			$order = isset( $entry['orderby'] ) && is_string( $entry['orderby'] ) && in_array( $entry['orderby'], self::ORDERBY, true ) ? $entry['orderby'] : 'menu_order';
			$arch  = isset( $entry['has_archives'] ) ? $entry['has_archives'] : false;

			$out[ $clean ] = array(
				'label'        => $label,
				'type'         => $type,
				'orderby'      => $order,
				'has_archives' => true === $arch || 1 === $arch || '1' === $arch,
				'swatches'     => isset( $entry['swatches'] ) && is_array( $entry['swatches'] ) ? $entry['swatches'] : null,
			);
		}
		if ( $invalid && null !== $context ) {
			$context->report( 'woocommerce.attribute-invalid', array( 'attributes' => array_slice( $invalid, 0, 50 ) ), Part_Context::WARNING );
		}
		return $out;
	}

	/** Huella de un atributo: *slug*, etiqueta, tipo, orden y archivos públicos (la configuración de muestras va aparte). */
	public static function fingerprint( string $slug, array $entry ): string {
		return Part_Context::hash(
			array(
				'slug'         => $slug,
				'label'        => isset( $entry['label'] ) ? (string) $entry['label'] : '',
				'type'         => isset( $entry['type'] ) ? (string) $entry['type'] : '',
				'orderby'      => isset( $entry['orderby'] ) ? (string) $entry['orderby'] : '',
				'has_archives' => ! empty( $entry['has_archives'] ),
			)
		);
	}

	/** Huella de un atributo del sitio (`wc_get_attribute()`), con la misma forma que `fingerprint()`. */
	public static function print_of( $attribute ): string {
		if ( ! is_object( $attribute ) ) {
			return '';
		}
		return self::fingerprint(
			(string) preg_replace( '/^pa_/', '', isset( $attribute->slug ) ? (string) $attribute->slug : '' ),
			array(
				'label'        => isset( $attribute->name ) ? (string) $attribute->name : '',
				'type'         => isset( $attribute->type ) ? (string) $attribute->type : '',
				'orderby'      => isset( $attribute->order_by ) ? (string) $attribute->order_by : '',
				'has_archives' => ! empty( $attribute->has_archives ),
			)
		);
	}

	private static function current_hash( int $id ): string {
		return self::print_of( wc_get_attribute( $id ) );
	}

	/** Atributo reutilizado: si la etiqueta o el tipo del sitio no son los del kit, se informa (el del sitio gana). */
	private static function report_differs( string $slug, array $entry, int $id, Import_Context $context ): void {
		$current = wc_get_attribute( $id );
		if ( ! is_object( $current ) ) {
			return;
		}
		$label = isset( $current->name ) ? (string) $current->name : '';
		$type  = isset( $current->type ) ? (string) $current->type : '';
		if ( $label !== $entry['label'] || $type !== $entry['type'] ) {
			$context->report(
				'woocommerce.attribute-differs',
				array(
					'attribute' => $slug,
					'kit'       => $entry['label'] . ' · ' . $entry['type'],
					'site'      => $label . ' · ' . $type,
				),
				Part_Context::INFO
			);
		}
	}

	/**
	 * Registra la taxonomía de un atributo recién creado en esta misma petición, con los filtros y los permisos de
	 * WooCommerce y sin reescritura (en la próxima petición la registra WooCommerce con lo guardado).
	 */
	private static function register_taxonomy( string $taxonomy, string $label, bool $public ): void {
		if ( ! function_exists( 'register_taxonomy' ) || ( function_exists( 'taxonomy_exists' ) && taxonomy_exists( $taxonomy ) ) ) {
			return;
		}
		$objects = apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy, array( 'product' ) );
		$args    = apply_filters(
			'woocommerce_taxonomy_args_' . $taxonomy,
			array(
				'labels'                => array(
					'name'          => $label,
					'singular_name' => $label,
				),
				'hierarchical'          => false,
				'update_count_callback' => '_update_post_term_count',
				'show_ui'               => false,
				'query_var'             => $public,
				'rewrite'               => false,
				'public'                => $public,
				'capabilities'          => array(
					'manage_terms' => 'manage_product_terms',
					'edit_terms'   => 'edit_product_terms',
					'delete_terms' => 'delete_product_terms',
					'assign_terms' => 'assign_product_terms',
				),
			)
		);
		register_taxonomy( $taxonomy, $objects, $args );
	}

	/** `$wc_product_attributes` al día, como el importador CSV de WooCommerce tras crear un atributo. */
	private static function refresh_globals(): void {
		global $wc_product_attributes;
		$wc_product_attributes = array();
		foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
			if ( is_object( $attribute ) && isset( $attribute->attribute_name ) ) {
				$wc_product_attributes[ wc_attribute_taxonomy_name( (string) $attribute->attribute_name ) ] = $attribute;
			}
		}
	}

	/**
	 * ¿Usa el atributo algo que no creó la sesión? Un término sin su marca de sesión, un objeto de sus términos sin
	 * ella o un producto que lo nombra en `_product_attributes`. Si no se puede comprobar, se da por usado (no se borra).
	 */
	private static function in_use( string $taxonomy, string $session ): bool {
		if ( ! function_exists( 'get_terms' ) ) {
			return true;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_array( $terms ) ) {
			return true;
		}
		$terms = array_map( 'intval', $terms );
		foreach ( $terms as $term_id ) {
			if ( (string) get_term_meta( $term_id, self::SESSION_META, true ) !== $session ) {
				return true;
			}
		}
		if ( $terms ) {
			$objects = function_exists( 'get_objects_in_term' ) ? get_objects_in_term( $terms, $taxonomy ) : null;
			if ( ! is_array( $objects ) ) {
				return true;
			}
			foreach ( $objects as $object ) {
				if ( (string) get_post_meta( (int) $object, self::SESSION_META, true ) !== $session ) {
					return true;
				}
			}
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) || ! isset( $wpdb->postmeta ) ) {
			return true;
		}
		$found = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->postmeta} s ON s.post_id = pm.post_id AND s.meta_key = %s WHERE pm.meta_key = '_product_attributes' AND pm.meta_value LIKE %s AND ( s.meta_value IS NULL OR s.meta_value <> %s ) LIMIT 1",
				self::SESSION_META,
				'%' . $wpdb->esc_like( '"' . $taxonomy . '"' ) . '%',
				$session
			)
		);
		return null !== $found && '' !== (string) $found;
	}

	/** Entrada de muestras de un atributo (`Swatches\Data::entry()`), o null (desplegable, o sin el frente D). */
	private static function swatches_entry( string $slug ): ?array {
		$class = self::swatches_class();
		if ( '' === $class ) {
			return null;
		}
		$entry = $class::entry( $slug );
		return is_array( $entry ) ? $entry : null;
	}

	/** `Swatches\Data` con las operaciones que usa la parte, o '' si no está. */
	private static function swatches_class(): string {
		$class = self::SWATCHES;
		if ( ! class_exists( $class ) ) {
			return '';
		}
		foreach ( array( 'entry', 'put', 'remove', 'sanitize_entry' ) as $method ) {
			if ( ! method_exists( $class, $method ) ) {
				return '';
			}
		}
		return $class;
	}
}
