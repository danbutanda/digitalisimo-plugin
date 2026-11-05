<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Session;
use Elementor\App\Modules\ImportExportCustomization\Utils as ImportExportUtils;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `taxonomies` de K (§7.5, A4, A8).
 *
 * Como el nativo: un término con el mismo slug se reutiliza y uno nuevo se crea con la sesión. Además:
 * - un menú que ya importó el mismo kit se reutiliza (par `{old_id, new_id}`) en vez de crear otro `-duplicate`;
 * - una taxonomía que el sitio no tiene se cuenta y se informa (F4-Woo puede registrarla antes con `Early_Part`);
 * - lo creado lleva `_digitalisimo_import_origin`.
 */
class Taxonomies extends \Elementor\App\Modules\ImportExportCustomization\Runners\Import\Taxonomies {
	/** @var string */
	private $k_session = '';

	public function import( array $data, array $imported_data ) {
		$this->k_session = (string) ( $data['session_id'] ?? '' );
		return parent::import( $data, $imported_data );
	}

	/**
	 * @param array  $taxonomies Taxonomías del manifiesto (nombre o `{ name }`).
	 * @param string $path       Carpeta `taxonomies/` del kit extraído.
	 */
	public function import_taxonomies( array $taxonomies, $path ) {
		$result = array();
		$done   = array();
		foreach ( $taxonomies as $object ) {
			$taxonomy = is_array( $object ) ? ( $object['name'] ?? '' ) : $object;
			if ( ! is_string( $taxonomy ) || '' === $taxonomy ) {
				continue;
			}
			if ( ! taxonomy_exists( $taxonomy ) ) {
				Context::report( 'taxonomies.missing', array( 'taxonomy' => $taxonomy ), Part_Context::WARNING );
				continue;
			}
			if ( isset( $done[ $taxonomy ] ) ) {
				$result[ $taxonomy ] = $done[ $taxonomy ];
				continue;
			}
			$terms = ImportExportUtils::read_json_file( $path . $taxonomy );
			if ( empty( $terms ) || ! is_array( $terms ) ) {
				continue;
			}
			$result[ $taxonomy ] = $this->import_terms( $terms );
			$done[ $taxonomy ]   = $result[ $taxonomy ];
		}
		return $result;
	}

	/** Importa los términos de una taxonomía; devuelve `[ { old_id, new_id, old_slug, new_slug } ]`. */
	private function import_terms( array $terms ): array {
		$session = '' !== $this->k_session ? $this->k_session : Context::session();
		$mapped  = array();
		foreach ( $terms as $term ) {
			if ( ! is_array( $term ) || ! isset( $term['slug'], $term['taxonomy'], $term['term_id'] ) ) {
				continue;
			}
			$taxonomy = (string) $term['taxonomy'];
			$old_id   = (int) $term['term_id'];
			$old_slug = (string) $term['slug'];
			$existing = term_exists( $old_slug, $taxonomy );
			if ( $existing ) {
				$existing_id = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
				if ( 'nav_menu' !== $taxonomy || self::from_same_kit( $existing_id, $old_id ) ) {
					$mapped[] = array(
						'old_id'   => $old_id,
						'new_id'   => $existing_id,
						'old_slug' => $old_slug,
						'new_slug' => $old_slug,
					);
					continue;
				}
				do {
					$term['slug'] = $term['slug'] . '-duplicate';
					$term['name'] = ( $term['name'] ?? '' ) . ' duplicate';
				} while ( term_exists( $term['slug'], 'nav_menu' ) );
			}
			$parent = 0;
			if ( ! empty( $term['parent'] ) ) {
				foreach ( $mapped as $item ) {
					if ( (int) $term['parent'] === (int) $item['old_id'] ) {
						$parent = (int) $item['new_id'];
						break;
					}
				}
			}
			$new = wp_insert_term(
				wp_slash( (string) ( $term['name'] ?? $term['slug'] ) ),
				$taxonomy,
				array(
					'slug'        => (string) $term['slug'],
					'description' => wp_slash( (string) ( $term['description'] ?? '' ) ),
					'parent'      => $parent,
				)
			);
			if ( is_wp_error( $new ) ) {
				Context::report(
					'taxonomies.failed',
					array(
						'taxonomy' => $taxonomy,
						'slug'     => $old_slug,
					),
					Part_Context::WARNING
				);
				continue;
			}
			$new_id = (int) $new['term_id'];
			$this->set_session_term_meta( $new_id, $session );
			Session::mark_term_origin( $new_id, $taxonomy, $old_id );
			$mapped[] = array(
				'old_id'   => $old_id,
				'new_id'   => $new_id,
				'old_slug' => $old_slug,
				'new_slug' => (string) $term['slug'],
			);
		}
		return $mapped;
	}

	/** ¿Importó ese término el mismo kit con ese ID de origen? */
	private static function from_same_kit( int $term_id, int $old_id ): bool {
		$record = Context::record();
		$origin = get_term_meta( $term_id, Module::META_ORIGIN, true );
		return null !== $record && is_array( $origin ) && (string) ( $origin['kit'] ?? '' ) === (string) ( $record['origin']['kit'] ?? '-' ) && (int) ( $origin['id'] ?? 0 ) === $old_id;
	}
}
