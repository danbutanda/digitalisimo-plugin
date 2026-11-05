<?php
namespace Digitalisimo\Elements\Import\Kits\Parts;

use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Remapper;
use Digitalisimo\Elements\Import\Kits\References;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Export\Meta_Blacklist;

defined( 'ABSPATH' ) || exit;

/**
 * Parte `term-meta`: las metas de los términos exportados (B18, CC-11).
 *
 * El núcleo exporta los términos de las taxonomías de los tipos exportados, pero no sus metas (por ejemplo, la
 * imagen de una categoría de producto, `thumbnail_id`). Esta parte las exporta con la lista negra de §8.2, sin
 * valores serializados con objetos, como `{ "<term_id>": { taxonomy, slug, meta: { clave: [ valores ] } } }`.
 *
 * Al importar, cada término se busca en el mapa `term` y sólo se escriben metas en los términos que **creó** la
 * sesión (`_elementor_import_session_id`): un término reutilizado por su slug ya es del sitio y no se toca. Las
 * referencias `scope = term_meta` (por ejemplo `thumbnail_id` → `attachment`) se traducen con el remapeo; una
 * acotada con `taxonomies` (V.11.4 de F4-Woo, como `image` en los atributos `pa_*`) sólo en los términos de sus
 * taxonomías, aquí y en `attachment_ids()`.
 */
final class Term_Meta implements Part {
	const KEY     = 'term-meta';
	const VERSION = 1;

	const SESSION_META = '_elementor_import_session_id';
	const KEY_PATTERN  = '/^[A-Za-z0-9_.:-]{1,191}$/';
	const MAX_VALUES   = 50;

	public function key(): string {
		return self::KEY;
	}

	public function version(): int {
		return self::VERSION;
	}

	public function kit_meta_keys(): array {
		return array();
	}

	public function export( Export_Context $context ): ?array {
		$out = array();
		foreach ( self::exported_terms( $context ) as $term ) {
			$metas = function_exists( 'get_term_meta' ) ? get_term_meta( $term->term_id ) : array();
			$clean = array();
			foreach ( is_array( $metas ) ? $metas : array() as $key => $values ) {
				if ( ! is_string( $key ) || 1 !== preg_match( self::KEY_PATTERN, $key ) || Meta_Blacklist::blocks( $key ) ) {
					continue;
				}
				foreach ( array_slice( (array) $values, 0, self::MAX_VALUES ) as $raw ) {
					list( $ok, $value ) = Meta_Blacklist::value( $raw );
					if ( ! $ok ) {
						$context->report( 'term-meta.object', array( 'term' => (int) $term->term_id, 'key' => $key ), Part_Context::WARNING );
						continue;
					}
					$clean[ $key ][] = $value;
				}
			}
			if ( $clean ) {
				ksort( $clean, SORT_STRING );
				$out[ (string) $term->term_id ] = array(
					'taxonomy' => (string) $term->taxonomy,
					'slug'     => (string) $term->slug,
					'meta'     => $clean,
				);
			}
		}
		return $out ? $out : null;
	}

	public function import( array $data, int $version, Import_Context $context ): array {
		$summary  = array(
			'terms' => 0,
			'metas' => 0,
			'kept'  => 0,
		);
		$remapper = new Remapper( $context );
		$added    = array();
		foreach ( $data as $old => $entry ) {
			if ( ! is_scalar( $old ) || ! ctype_digit( (string) $old ) || ! is_array( $entry ) || ! isset( $entry['meta'] ) || ! is_array( $entry['meta'] ) ) {
				continue;
			}
			$new = $context->mapped( 'term', (int) $old );
			if ( null === $new ) {
				$context->report( 'term-meta.no-term', array( 'term' => (int) $old ), Part_Context::WARNING );
				continue;
			}
			$new = (int) $new;
			if ( (string) get_term_meta( $new, self::SESSION_META, true ) !== $context->session() ) {
				// Término que ya existía en el sitio (reutilizado por su slug): no se toca.
				$summary['kept'] += count( $entry['meta'] );
				$context->report( 'term-meta.kept', array( 'term' => $new ) );
				continue;
			}
			++$summary['terms'];
			$taxonomy = null;
			foreach ( $entry['meta'] as $key => $values ) {
				if ( ! is_string( $key ) || 1 !== preg_match( self::KEY_PATTERN, $key ) || Meta_Blacklist::blocks( $key ) ) {
					continue;
				}
				// Una referencia acotada con `taxonomies` (V.11.4) sólo se aplica a los términos de sus taxonomías.
				$restricted = References::term_meta_restricted( $key );
				if ( $restricted && null === $taxonomy ) {
					$taxonomy = self::taxonomy_of( $new );
				}
				foreach ( array_slice( is_array( $values ) ? $values : array( $values ), 0, self::MAX_VALUES ) as $raw ) {
					list( $ok, $value ) = Meta_Blacklist::value( $raw );
					if ( ! $ok ) {
						continue;
					}
					if ( $restricted ) {
						$value = References::within_taxonomy(
							(string) $taxonomy,
							function () use ( $remapper, $key, $value, $new ) {
								return $remapper->apply_meta( 'term_meta', $key, $value, $new );
							}
						);
					} else {
						$value = $remapper->apply_meta( 'term_meta', $key, $value, $new );
					}
					if ( null === $value || '' === $value ) {
						continue;
					}
					if ( false !== add_term_meta( $new, $key, $value ) ) {
						$added[ $new ][ $key ][] = Part_Context::hash( $value );
						++$summary['metas'];
					}
				}
			}
		}
		$context->store( array( 'added' => $added ) );
		return $summary;
	}

	/** Quita las metas añadidas que siguen igual (si el término sigue existiendo; la reversión del núcleo lo borra). */
	public function revert( array $stored, Revert_Context $context ): void {
		foreach ( isset( $stored['added'] ) && is_array( $stored['added'] ) ? $stored['added'] : array() as $term_id => $keys ) {
			$term_id = (int) $term_id;
			if ( $term_id <= 0 || ! is_array( $keys ) || ! function_exists( 'get_term' ) || ! is_object( get_term( $term_id ) ) ) {
				continue;
			}
			foreach ( $keys as $key => $hashes ) {
				foreach ( (array) get_term_meta( $term_id, (string) $key ) as $value ) {
					if ( in_array( Part_Context::hash( $value ), (array) $hashes, true ) ) {
						delete_term_meta( $term_id, (string) $key, $value );
					}
				}
			}
		}
	}

	// ── Utilidades ──────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Términos que exporta el núcleo: todos los de las taxonomías de los tipos de entrada exportados (como el runner
	 * `taxonomies`, que no omite los vacíos).
	 *
	 * @return \WP_Term[]
	 */
	public static function exported_terms( Export_Context $context ): array {
		if ( ! $context->includes( 'content' ) || ! function_exists( 'get_terms' ) || ! function_exists( 'get_object_taxonomies' ) ) {
			return array();
		}
		$types = array();
		foreach ( $context->exported( 'post' ) as $id ) {
			$type = get_post_type( (int) $id );
			if ( is_string( $type ) && '' !== $type ) {
				$types[ $type ] = true;
			}
		}
		$taxonomies = array();
		foreach ( array_keys( $types ) as $type ) {
			foreach ( (array) get_object_taxonomies( $type ) as $taxonomy ) {
				if ( is_string( $taxonomy ) ) {
					$taxonomies[ $taxonomy ] = true;
				}
			}
		}
		if ( ! $taxonomies ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => array_keys( $taxonomies ),
				'hide_empty' => false,
				'get'        => 'all',
			)
		);
		return is_array( $terms ) ? array_values(
			array_filter(
				$terms,
				function ( $term ) {
					return is_object( $term ) && isset( $term->term_id, $term->taxonomy );
				}
			)
		) : array();
	}

	/** Taxonomía real de un término del sitio, o '' si no existe (una referencia acotada no se le aplica). */
	private static function taxonomy_of( int $term_id ): string {
		$term = function_exists( 'get_term' ) ? get_term( $term_id ) : null;
		return is_object( $term ) && isset( $term->taxonomy ) && is_string( $term->taxonomy ) ? $term->taxonomy : '';
	}

	/**
	 * Adjuntos de las metas de término con referencia `attachment` (para que viajen en `media`). Una referencia acotada
	 * con `taxonomies` (V.11.4) sólo cuenta en los términos de sus taxonomías.
	 */
	public static function attachment_ids( Export_Context $context ): array {
		$keys = array();
		foreach ( References::all() as $scope => $types ) {
			if ( 'term_meta' !== $scope ) {
				continue;
			}
			foreach ( $types as $key => $refs ) {
				foreach ( $refs as $ref ) {
					if ( 'attachment' === $ref['kind'] && '' === $ref['path'] ) {
						$keys[ $key ][] = $ref;
					}
				}
			}
		}
		$ids = array();
		if ( ! $keys ) {
			return $ids;
		}
		foreach ( self::exported_terms( $context ) as $term ) {
			foreach ( $keys as $key => $refs ) {
				$applies = false;
				foreach ( $refs as $ref ) {
					$applies = $applies || References::applies_to_taxonomy( $ref, (string) $term->taxonomy );
				}
				if ( ! $applies ) {
					continue;
				}
				foreach ( (array) get_term_meta( $term->term_id, $key ) as $value ) {
					if ( is_numeric( $value ) && (int) $value > 0 ) {
						$ids[ (int) $value ] = true;
					}
				}
			}
		}
		return array_keys( $ids );
	}
}
