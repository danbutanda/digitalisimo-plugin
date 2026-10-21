<?php
namespace Digitalisimo\Elements\Import\Kits\Export;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Part_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Lo que comparten los runners de exportación de K (§8.3): IDs de elemento conservados (D20), miniaturas en tamaño
 * completo (CC-23), `metadata` sin la lista negra (§8.2), lo exportado anotado en el contexto y lo que no se
 * exporta contado en `not_exported` (CC-13).
 *
 * El núcleo regenera el ID de cada elemento al exportar (`N:core/base/document.php:1655-1659`) y el filtro
 * `elementor/document/element/replace_id` no recibe el original: K repone el ID recorriendo en paralelo el árbol
 * exportado y el guardado. Si un nodo no coincide en `elType` o `widgetType`, el documento conserva los IDs nuevos
 * y se informa. La importación no los regenera (`N:core/base/document.php:1730-1738`).
 */
final class Documents {
	const META_DATA      = '_elementor_data';
	const META_EDIT_MODE = '_elementor_edit_mode';
	const MARKER_KEY     = 'digitalisimo_unconverted';

	/** Estados que el núcleo no exporta: cuentan en `not_exported` como borradores o privados. */
	const DRAFT_STATUSES = array( 'draft', 'pending', 'future' );

	/**
	 * Contexto de la exportación: el que recibió el runner o, si se registró sin él, el activo de `Kits\Context`.
	 *
	 * @param Export_Context|null $given Contexto inyectado.
	 */
	public static function context( ?Export_Context $given = null ): ?Export_Context {
		if ( null !== $given ) {
			return $given;
		}
		$class = '\\' . Module::NAMESPACE_PREFIX . 'Kits\\Context';
		if ( class_exists( $class ) && method_exists( $class, 'export_context' ) ) {
			$active = call_user_func( array( $class, 'export_context' ) );
			return $active instanceof Export_Context ? $active : null;
		}
		return null;
	}

	/**
	 * Termina el resultado de un runner de exportación del núcleo.
	 *
	 * @param array               $result  `{ files, manifest }` del runner (`files` puede ser un solo archivo).
	 * @param Export_Context|null $context Contexto de la exportación; sin él sólo se reponen IDs y miniaturas.
	 * @param callable|null       $source  `( int $id ): array` árbol guardado de un documento; por defecto, `elements_of()`.
	 */
	public static function finish( array $result, ?Export_Context $context = null, ?callable $source = null ): array {
		$source = $source ? $source : array( __CLASS__, 'elements_of' );
		$files  = isset( $result['files'] ) && is_array( $result['files'] ) ? $result['files'] : array();
		if ( isset( $files['path'] ) ) {
			$files = array( $files );
		}
		foreach ( $files as $index => $file ) {
			if ( ! is_array( $file ) || ! isset( $file['path'] ) || ! is_string( $file['path'] ) || ! isset( $file['data'] ) || ! is_array( $file['data'] ) ) {
				continue;
			}
			$target = self::target( $file['path'] );
			if ( null === $target ) {
				continue;
			}
			list( $kind, $id ) = $target;
			$data              = $file['data'];
			if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
				$restored = self::restore_ids( $data['content'], (array) call_user_func( $source, $id ) );
				if ( null === $restored ) {
					if ( $context ) {
						$context->report( 'ids.mismatch', array( 'post' => $id ), Part_Context::WARNING );
					}
				} else {
					$data['content'] = $restored;
				}
			}
			if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
				foreach ( array_keys( $data['metadata'] ) as $key ) {
					if ( Meta_Blacklist::blocks( (string) $key ) ) {
						unset( $data['metadata'][ $key ] );
					}
				}
			}
			$files[ $index ]['data'] = $data;
			if ( $context ) {
				$context->add_exported( $kind, array( $id ) );
			}
		}
		$result['files'] = array_values( $files );
		if ( isset( $result['manifest'] ) && is_array( $result['manifest'] ) ) {
			$result['manifest'] = self::full_thumbnails( $result['manifest'] );
		}
		return $result;
	}

	/**
	 * Repone los IDs originales en un árbol exportado, o null si los árboles no coinciden.
	 *
	 * Un marcador de elemento desconocido se exporta como su original, con su ID (`on_export`): en ese nodo se
	 * compara con la carga del marcador y se deja tal cual.
	 *
	 * @param array $exported Árbol que devolvió `get_export_data()`.
	 * @param array $source   Árbol guardado (`get_elements_data()`).
	 */
	public static function restore_ids( array $exported, array $source ): ?array {
		if ( count( $exported ) !== count( $source ) ) {
			return null;
		}
		$exported_keys = array_keys( $exported );
		$source_values = array_values( $source );
		foreach ( $exported_keys as $position => $key ) {
			$node = $exported[ $key ];
			$orig = $source_values[ $position ];
			if ( ! is_array( $node ) || ! is_array( $orig ) ) {
				return null;
			}
			if ( Module::WIDGET === self::type_of( $orig ) && isset( $orig['settings'][ self::MARKER_KEY ]['element'] ) && is_array( $orig['settings'][ self::MARKER_KEY ]['element'] ) ) {
				$inner = $orig['settings'][ self::MARKER_KEY ]['element'];
				if ( self::same_kind( $node, $inner ) ) {
					continue;
				}
			}
			if ( ! self::same_kind( $node, $orig ) ) {
				return null;
			}
			if ( isset( $orig['id'] ) && is_string( $orig['id'] ) && 1 === preg_match( '/^[A-Za-z0-9]{1,64}$/', $orig['id'] ) ) {
				$node['id'] = $orig['id'];
			}
			$children = isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : array();
			$origins  = isset( $orig['elements'] ) && is_array( $orig['elements'] ) ? $orig['elements'] : array();
			if ( $children || $origins ) {
				$children = self::restore_ids( $children, $origins );
				if ( null === $children ) {
					return null;
				}
				$node['elements'] = $children;
			}
			$exported[ $key ] = $node;
		}
		return $exported;
	}

	/**
	 * Árbol guardado de un documento, como lo leyó el exportador (`get_elements_data()` fuera del editor).
	 *
	 * @param int $id Documento.
	 */
	public static function elements_of( int $id ): array {
		if ( class_exists( '\Elementor\Plugin', false ) && ! empty( \Elementor\Plugin::$instance->documents ) ) {
			$document = \Elementor\Plugin::$instance->documents->get( $id );
			if ( is_object( $document ) && method_exists( $document, 'get_elements_data' ) ) {
				$data = $document->get_elements_data();
				return is_array( $data ) ? $data : array();
			}
		}
		$raw = function_exists( 'get_post_meta' ) ? get_post_meta( $id, self::META_DATA, true ) : '';
		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = json_decode( $raw, true );
		}
		return is_array( $raw ) ? $raw : array();
	}

	/** URL de la miniatura de un documento en tamaño completo, o '' (CC-23). */
	public static function thumbnail( int $id ): string {
		if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
			return '';
		}
		$url = get_the_post_thumbnail_url( $id, 'full' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Número de entradas de unos tipos y estados, con o sin contenido del constructor.
	 *
	 * @param string[]  $post_types Tipos de entrada.
	 * @param string[]  $statuses   Estados.
	 * @param bool|null $builder    true: con `_elementor_edit_mode`; false: sin él; null: todas.
	 * @param array     $extra      Argumentos de `WP_Query` añadidos (por ejemplo, `has_password`).
	 */
	public static function count( array $post_types, array $statuses, ?bool $builder = null, array $extra = array() ): int {
		$post_types = array_values( array_filter( $post_types, 'is_string' ) );
		if ( ! $post_types || ! $statuses || ! class_exists( '\WP_Query' ) ) {
			return 0;
		}
		$args = array(
			'post_type'              => $post_types,
			'post_status'            => $statuses,
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'suppress_filters'       => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( null !== $builder ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => self::META_EDIT_MODE,
					'compare' => $builder ? 'EXISTS' : 'NOT EXISTS',
				),
			);
		}
		$query = new \WP_Query( array_replace( $args, $extra ) );
		return (int) $query->found_posts;
	}

	/**
	 * Cuenta en `not_exported` los borradores y privados de unos tipos (CC-13): el núcleo sólo exporta lo publicado.
	 *
	 * @param Export_Context|null $context    Contexto.
	 * @param string[]            $post_types Tipos de entrada.
	 * @param bool|null           $builder    Con o sin contenido del constructor.
	 */
	public static function count_unpublished( ?Export_Context $context, array $post_types, ?bool $builder ): void {
		if ( ! $context ) {
			return;
		}
		$drafts  = self::count( $post_types, self::DRAFT_STATUSES, $builder );
		$private = self::count( $post_types, array( 'private' ), $builder );
		if ( $drafts > 0 ) {
			$context->not_exported( 'drafts', $drafts );
		}
		if ( $private > 0 ) {
			$context->not_exported( 'private', $private );
		}
	}

	/** Documentos componente v4: el núcleo no los exporta mientras no admita componentes en kits (V-25). */
	public static function count_components( ?Export_Context $context ): void {
		$class = '\Elementor\Modules\Components\Module';
		if ( ! $context || ! class_exists( $class ) || ! method_exists( $class, 'excluded_import_export_post_types' ) ) {
			return;
		}
		$types = (array) call_user_func( array( $class, 'excluded_import_export_post_types' ) );
		$count = self::count( $types, array( 'publish', 'draft', 'pending', 'future', 'private' ) );
		if ( $count > 0 ) {
			$context->not_exported( 'components', $count );
		}
	}

	/** `[ 'template'|'post', id ]` de una ruta del kit (`templates/<id>` o `content/<tipo>/<id>`), o null. */
	private static function target( string $path ): ?array {
		if ( 1 === preg_match( '#^templates/(\d+)(?:\.json)?$#', $path, $match ) ) {
			return array( 'template', (int) $match[1] );
		}
		if ( 1 === preg_match( '#^content/[A-Za-z0-9_-]+/(\d+)(?:\.json)?$#', $path, $match ) ) {
			return array( 'post', (int) $match[1] );
		}
		return null;
	}

	/** Miniaturas del manifiesto en tamaño completo (`templates` y `content`). */
	private static function full_thumbnails( array $manifest ): array {
		foreach ( $manifest as $index => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			if ( isset( $entry['templates'] ) && is_array( $entry['templates'] ) ) {
				$entry['templates'] = self::full_list( $entry['templates'] );
			}
			if ( isset( $entry['content'] ) && is_array( $entry['content'] ) ) {
				foreach ( $entry['content'] as $type => $items ) {
					if ( is_array( $items ) ) {
						$entry['content'][ $type ] = self::full_list( $items );
					}
				}
			}
			$manifest[ $index ] = $entry;
		}
		return $manifest;
	}

	private static function full_list( array $items ): array {
		foreach ( $items as $id => $item ) {
			if ( is_array( $item ) && ! empty( $item['thumbnail'] ) && is_numeric( $id ) ) {
				$full = self::thumbnail( (int) $id );
				if ( '' !== $full ) {
					$items[ $id ]['thumbnail'] = $full;
				}
			}
		}
		return $items;
	}

	private static function same_kind( array $a, array $b ): bool {
		$type_a = isset( $a['elType'] ) ? $a['elType'] : null;
		$type_b = isset( $b['elType'] ) ? $b['elType'] : null;
		if ( ! is_string( $type_a ) || $type_a !== $type_b ) {
			return false;
		}
		return ( isset( $a['widgetType'] ) ? $a['widgetType'] : null ) === ( isset( $b['widgetType'] ) ? $b['widgetType'] : null );
	}

	private static function type_of( array $element ): string {
		if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
			return isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : '';
		}
		return isset( $element['elType'] ) && is_string( $element['elType'] ) ? $element['elType'] : '';
	}
}
