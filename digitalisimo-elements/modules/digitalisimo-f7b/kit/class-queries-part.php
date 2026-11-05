<?php
namespace Digitalisimo\Elements\F7b\Kit;

use Digitalisimo\Elements\F7b\Queries\Store;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Remapper;
use Digitalisimo\Elements\Import\Kits\Revert_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Parte `saved-queries` del kit: las consultas guardadas del sitio (F7b, frente K; D22 y V.10 de la especificación).
 *
 * Las consultas son entradas del tipo privado `digitalisimo_query` (frente C, `Queries\Store`), que el exportador del
 * núcleo no lleva (sólo exporta tipos públicos, `IEC:utils.php:88-102`). La parte las lee y las escribe sólo con las
 * reglas de `Store`, y `kit_meta_keys()` está vacío (no hay metas del kit):
 * - **exportar:** las publicadas y en borrador (las que `Store` lee), con su UUID, su título, su estado, sus ajustes ya
 *   saneados, su contexto, su relación y sus topes, y para cada término la pareja `term_id` y taxonomía: el grupo de
 *   consulta guarda `term_taxonomy_id` y el mapa de términos de K va por `term_id`;
 * - **importar** (petición final de K, antes de que el núcleo guarde los documentos y K los remapee: no hace falta
 *   `Early_Part`): reutiliza por UUID (`Store::find_by_uid()`) sin cambiar nada del sitio, también en «nuevo» (si los
 *   ajustes difieren, conserva los del sitio y lo informa); si no, crea la consulta con `Store::register()` ya llamado,
 *   así el `sanitize_callback` de sus metas sanea lo que llega. Términos, autores y entradas se traducen con las
 *   referencias de K (`Remapper::translate()`, `term`, `user` y `post`): un autor nunca tiene destino (los usuarios no
 *   viajan, `kits/class-references.php:44-45`), y lo que no resuelve se quita de su lista;
 * - **fallar cerrado:** una consulta que no llega igual que en el origen (una referencia de una lista que usa, un tipo de
 *   contenido que el sitio no publica, una relación con una taxonomía o un campo que no valen, un contexto desconocido)
 *   entra en **borrador**: `Store::get()` sólo aplica las publicadas, así que los elementos que la usan no muestran nada
 *   hasta que el administrador la revise («Guardar y publicar» en «Consultas»). Una exclusión perdida mostraría de más;
 *   nunca se quita un «incluir por» que quedó vacío (el saneado al consultar no lo hace: `Args::closed()` falla cerrado);
 * - **mapa:** `queries` (ID de origen → ID del sitio, como cadena: el control lo guarda así y la aguja de `Flags` lo
 *   busca entre comillas), que usa la referencia `element · * · digitalisimo_saved_query · map:queries` de `Module`;
 * - **revertir:** borra lo que creó si nadie lo editó ni lo usa contenido del sitio; si no, lo conserva y lo informa.
 *   Lo reutilizado nunca es de la sesión y no se toca.
 */
final class Queries_Part implements Part {
	const KEY     = 'saved-queries';
	const VERSION = 1;

	/** Mapa que publica la parte (`map:queries`). */
	const MAP = Module::QUERIES_MAP;

	/** Estados que `Store` lee y que viajan. */
	const STATUSES = array( 'publish', 'draft' );

	/** Consultas por kit, como la lista de la pantalla «Consultas» (`Store::MAX_LIST`). */
	const MAX = 200;

	/** Listas de IDs del grupo de consulta y su tipo de referencia de K. */
	const ID_FIELDS = array(
		'include_term_ids' => 'term',
		'exclude_term_ids' => 'term',
		'include_authors'  => 'user',
		'exclude_authors'  => 'user',
		'exclude_ids'      => 'post',
	);

	/** Cuándo cuenta cada lista: grupo y opción del «incluir por» o «excluir por» que la usa. */
	const ACTIVE = array(
		'include_term_ids' => array( 'include', 'terms' ),
		'include_authors'  => array( 'include', 'authors' ),
		'exclude_ids'      => array( 'exclude', 'manual_selection' ),
		'exclude_term_ids' => array( 'exclude', 'terms' ),
		'exclude_authors'  => array( 'exclude', 'authors' ),
	);

	/** Motivo del borrador por lista. */
	const REASONS = array(
		'include_term_ids' => 'terms',
		'exclude_term_ids' => 'terms',
		'include_authors'  => 'authors',
		'exclude_authors'  => 'authors',
		'exclude_ids'      => 'posts',
	);

	const TAXONOMY_PATTERN = '/^[a-z0-9_-]{1,32}$/';

	public function key(): string {
		return self::KEY;
	}

	public function version(): int {
		return self::VERSION;
	}

	public function kit_meta_keys(): array {
		return array();
	}

	/** `{ v: 1, queries: [ { id, uid, title, status, settings, context, relation, limits, terms } ] }`, o null sin consultas. */
	public function export( Export_Context $context ): ?array {
		if ( ! self::ready() ) {
			return null;
		}
		Store::register();
		$ids = get_posts(
			array(
				'post_type'        => Store::POST_TYPE,
				'post_status'      => self::STATUSES,
				'posts_per_page'   => self::MAX,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		$out = array();
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			$id = (int) $id;
			Store::forget( $id );
			$query = $id > 0 ? Store::get( $id, true ) : null;
			if ( null === $query ) {
				continue;
			}
			$terms = array();
			foreach ( array( 'include_term_ids', 'exclude_term_ids' ) as $field ) {
				foreach ( $query['settings'][ $field ] as $tt_id ) {
					$term = function_exists( 'get_term_by' ) ? get_term_by( 'term_taxonomy_id', (int) $tt_id ) : null;
					if ( is_object( $term ) && isset( $term->term_id, $term->taxonomy ) ) {
						$terms[ (string) (int) $tt_id ] = array(
							'term'     => (int) $term->term_id,
							'taxonomy' => (string) $term->taxonomy,
						);
					}
				}
			}
			$out[] = array(
				'id'       => $id,
				'uid'      => '' !== $query['uid'] ? $query['uid'] : self::derived_uid( $id ),
				'title'    => $query['title'],
				'status'   => $query['status'],
				'settings' => $query['settings'],
				'context'  => $query['context'],
				'relation' => $query['relation'],
				'limits'   => $query['limits'],
				'terms'    => $terms,
			);
		}
		return $out ? array(
			'v'       => self::VERSION,
			'queries' => $out,
		) : null;
	}

	public function import( array $data, int $version, Import_Context $context ): array {
		$rows = isset( $data['queries'] ) && is_array( $data['queries'] ) ? array_values( $data['queries'] ) : array();
		if ( ! $rows ) {
			// El kit no trae consultas (o es de terceros): nada cambia, tampoco en «nuevo».
			return array();
		}
		if ( ! self::ready() ) {
			$context->report( 'requested.saved-queries-unavailable', array( 'queries' => count( $rows ) ), Part_Context::WARNING );
			return array();
		}
		Store::register();
		$remapper = new Remapper( $context );
		$silent   = new Remapper( self::silent( $context ) );
		$map      = array();
		$created  = array();
		$counts   = array_fill_keys( array( 'created', 'reused', 'drafts', 'differs', 'failed', 'invalid' ), 0 );
		foreach ( array_slice( $rows, 0, self::MAX ) as $row ) {
			$query = self::shape( $row );
			if ( null === $query ) {
				++$counts['invalid'];
				$context->report( 'reference.saved-query-invalid', array( 'title' => is_array( $row ) && isset( $row['title'] ) && is_string( $row['title'] ) ? $row['title'] : '' ), Part_Context::WARNING );
				continue;
			}
			$existing = Store::find_by_uid( $query['uid'] );
			if ( $existing > 0 ) {
				// Reutilizar (D7): lo del sitio nunca cambia. Se compara en silencio con lo que traería el kit.
				$map[ $query['id'] ] = (string) $existing;
				++$counts['reused'];
				$incoming = self::translate( $query, $silent, $silent );
				Store::forget( $existing );
				$current = Store::get( $existing, true );
				if ( null === $current || self::fingerprint( $incoming['query'] ) !== self::fingerprint( $current ) ) {
					++$counts['differs'];
					$context->report(
						'requested.saved-query',
						array(
							'title' => $query['title'],
							'query' => $existing,
						),
						Part_Context::WARNING
					);
				}
				continue;
			}
			$result = self::translate( $query, $remapper, $silent, $context );
			$status = $result['reasons'] ? 'draft' : $query['status'];
			$id     = self::insert( $result['query'], $query['uid'], $status );
			if ( $id <= 0 ) {
				++$counts['failed'];
				$context->report( 'reference.saved-query-failed', array( 'title' => $query['title'] ), Part_Context::WARNING );
				continue;
			}
			$map[ $query['id'] ]     = (string) $id;
			$created[ (string) $id ] = Part_Context::hash( self::state( $id ) );
			++$counts['created'];
			if ( $result['reasons'] ) {
				++$counts['drafts'];
				$context->report(
					'draft.saved-query',
					array(
						'title'   => $query['title'],
						'query'   => $id,
						'reasons' => $result['reasons'],
					),
					Part_Context::WARNING
				);
			}
		}
		if ( $map ) {
			$context->add_map( self::MAP, $map );
		}
		if ( $created ) {
			$context->store( array( 'created' => $created ) );
		}
		return array( 'queries' => count( $rows ) ) + $counts;
	}

	/** Borra lo que creó la importación si sigue como quedó y nada del sitio lo usa; si no, lo conserva y lo informa. */
	public function revert( array $stored, Revert_Context $context ): void {
		$created = isset( $stored['created'] ) && is_array( $stored['created'] ) ? $stored['created'] : array();
		if ( ! $created || ! self::ready() ) {
			return;
		}
		Store::register();
		$deleted = array();
		foreach ( $created as $id => $hash ) {
			$id   = (int) $id;
			$post = $id > 0 && function_exists( 'get_post' ) ? get_post( $id ) : null;
			if ( ! is_object( $post ) || Store::POST_TYPE !== ( isset( $post->post_type ) ? $post->post_type : '' ) ) {
				continue;
			}
			$title = isset( $post->post_title ) ? (string) $post->post_title : '';
			// Los documentos de la sesión ya están en la papelera (los runners nativos revierten antes): lo que queda
			// contando son documentos del sitio, publicados o en borrador.
			$uses = Store::uses( $id );
			if ( $uses['count'] > 0 || $uses['drafts'] ) {
				$context->report(
					'revert.saved-query-in-use',
					array(
						'title' => $title,
						'query' => $id,
						'posts' => array_slice( array_merge( $uses['posts'], $uses['drafts'] ), 0, 10 ),
					)
				);
				continue;
			}
			if ( ! $context->unchanged( self::state( $id ), (string) $hash ) ) {
				$context->report(
					'revert.saved-query-edited',
					array(
						'title' => $title,
						'query' => $id,
					)
				);
				continue;
			}
			if ( wp_delete_post( $id, true ) ) {
				$deleted[] = $id;
			}
			Store::forget( $id );
		}
		if ( $deleted ) {
			$context->report( 'revert.saved-queries', array( 'queries' => $deleted ) );
		}
	}

	// ── Utilidades ──────────────────────────────────────────────────────────────────────────────────────────────

	/** ¿Están el almacén del frente C y las funciones de WordPress que usa la parte? */
	public static function ready(): bool {
		return class_exists( 'Digitalisimo\\Elements\\F7b\\Queries\\Store' ) && function_exists( 'get_posts' ) && function_exists( 'wp_insert_post' ) && function_exists( 'wp_delete_post' );
	}

	/**
	 * Una consulta del kit con su forma, o null si no la tiene: ID de origen, UUID v4, título y ajustes son
	 * obligatorios. Los ajustes pasan por el saneado de forma de `Store` (claves y valores de sus listas, IDs enteros);
	 * el tipo de contenido tal como llegó queda en `post_type_raw` para saber si el sitio lo publica.
	 *
	 * @param mixed $row Entrada de `queries`.
	 * @return array|null
	 */
	public static function shape( $row ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$id    = isset( $row['id'] ) && ( is_int( $row['id'] ) || ( is_string( $row['id'] ) && ctype_digit( $row['id'] ) ) ) ? (int) $row['id'] : 0;
		$uid   = isset( $row['uid'] ) && Store::is_uuid( $row['uid'] ) ? strtolower( (string) $row['uid'] ) : '';
		$title = isset( $row['title'] ) && is_string( $row['title'] ) ? trim( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $row['title'] ) : strip_tags( $row['title'] ) ) : '';
		$title = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, Store::MAX_TITLE ) : substr( $title, 0, Store::MAX_TITLE );
		if ( $id <= 0 || '' === $uid || '' === $title || ! isset( $row['settings'] ) || ! is_array( $row['settings'] ) ) {
			return null;
		}
		$terms = array();
		foreach ( isset( $row['terms'] ) && is_array( $row['terms'] ) ? $row['terms'] : array() as $tt_id => $lookup ) {
			if ( ! ctype_digit( (string) $tt_id ) || ! is_array( $lookup ) || ! isset( $lookup['term'], $lookup['taxonomy'] ) ) {
				continue;
			}
			$term = is_int( $lookup['term'] ) || ( is_string( $lookup['term'] ) && ctype_digit( $lookup['term'] ) ) ? (int) $lookup['term'] : 0;
			if ( $term > 0 && is_string( $lookup['taxonomy'] ) && 1 === preg_match( self::TAXONOMY_PATTERN, $lookup['taxonomy'] ) ) {
				$terms[ (int) $tt_id ] = array(
					'term'     => $term,
					'taxonomy' => $lookup['taxonomy'],
				);
			}
		}
		return array(
			'id'            => $id,
			'uid'           => $uid,
			'title'         => $title,
			'status'        => isset( $row['status'] ) && in_array( $row['status'], self::STATUSES, true ) ? (string) $row['status'] : 'draft',
			'settings'      => Store::sanitize_settings( $row['settings'] ),
			'post_type_raw' => isset( $row['settings']['post_type'] ) && is_string( $row['settings']['post_type'] ) ? $row['settings']['post_type'] : '',
			'context'       => isset( $row['context'] ) && is_string( $row['context'] ) ? $row['context'] : 'none',
			'relation'      => isset( $row['relation'] ) && is_string( $row['relation'] ) && '' !== $row['relation'] ? $row['relation'] : 'none',
			'limits'        => isset( $row['limits'] ) && is_array( $row['limits'] ) ? $row['limits'] : array(),
			'terms'         => $terms,
		);
	}

	/**
	 * Ajustes de la consulta con los IDs del sitio y los motivos por los que no llega igual que en el origen.
	 *
	 * Cada lista se traduce con las referencias de K: términos por su `term_id` (`term`) y de vuelta a su
	 * `term_taxonomy_id` en la misma taxonomía, autores (`user`, nunca resuelven) y entradas (`post`). Lo que no resuelve se
	 * quita; si la lista la usa un «incluir por» o un «excluir por», es un motivo de borrador. Las listas que no usa la
	 * consulta se traducen en silencio (no muestran nada, pero un ID del origen no debe quedarse).
	 *
	 * @param array               $query    De `shape()`.
	 * @param Remapper            $remapper Traducción que informa lo que no resuelve.
	 * @param Remapper            $silent   La misma traducción sin informe (`silent()`).
	 * @param Import_Context|null $context  Para informar los términos sin pareja en el kit (null: en silencio).
	 * @return array{query: array, reasons: string[]}
	 */
	private static function translate( array $query, Remapper $remapper, Remapper $silent, ?Import_Context $context = null ): array {
		$settings = $query['settings'];
		$reasons  = array();
		foreach ( self::ID_FIELDS as $field => $kind ) {
			$list = $settings[ $field ];
			if ( ! $list ) {
				continue;
			}
			$active = self::active( $settings, $field );
			$using  = $active ? $remapper : $silent;
			$ref    = array(
				'kind' => $kind,
				'path' => $field,
			);
			if ( 'term' === $kind ) {
				$out = array();
				foreach ( $list as $tt_id ) {
					$new = self::term( $query['terms'], (int) $tt_id, $using, $ref, $active ? $context : null );
					if ( $new > 0 ) {
						$out[] = $new;
					}
				}
			} else {
				$out = Store::ids( $using->translate( $ref, array_values( $list ), true ) );
			}
			$out = array_values( array_unique( $out ) );
			if ( count( $out ) < count( $list ) && $active ) {
				$reasons[] = self::REASONS[ $field ];
			}
			$settings[ $field ] = $out;
		}
		$settings = Store::sanitize_settings( $settings );
		if ( '' === $settings['post_type'] ) {
			$reasons[] = 'post_type';
		}
		$relation = Store::sanitize_relation( $query['relation'] );
		if ( $relation !== $query['relation'] ) {
			$reasons[] = 'relation';
		}
		$scope = 'none';
		if ( 'none' !== $relation ) {
			$scope = Store::sanitize_context( $query['context'] );
			if ( 'none' === $scope || $scope !== $query['context'] ) {
				$reasons[] = 'context';
			}
		}
		return array(
			'query'   => array(
				'title'    => $query['title'],
				'settings' => $settings,
				'context'  => $scope,
				'relation' => $relation,
				'limits'   => Store::sanitize_limits( $query['limits'] ),
			),
			'reasons' => array_values( array_unique( $reasons ) ),
		);
	}

	/**
	 * `term_taxonomy_id` del sitio para uno del kit: su `term_id` de origen pasa por el mapa de términos de K y vuelve a
	 * `term_taxonomy_id` con `get_term()` en la misma taxonomía. 0 si no resuelve.
	 *
	 * @param array               $terms   `term_taxonomy_id` => `{ term, taxonomy }` del kit.
	 * @param int                 $tt_id   `term_taxonomy_id` de origen.
	 * @param Remapper            $using   Traducción.
	 * @param array               $ref     Referencia `term` de la lista.
	 * @param Import_Context|null $context Para informar un término sin pareja en el kit.
	 */
	private static function term( array $terms, int $tt_id, Remapper $using, array $ref, ?Import_Context $context ): int {
		if ( ! isset( $terms[ $tt_id ] ) ) {
			// El origen ya no tenía ese término al exportar: nunca se traduce como si fuera un `term_id`.
			if ( null !== $context ) {
				$context->report(
					'unresolved',
					array(
						'path'   => $ref['path'],
						'kind'   => 'term',
						'values' => array( $tt_id ),
					),
					Part_Context::WARNING
				);
			}
			return 0;
		}
		$new = $using->translate( $ref, $terms[ $tt_id ]['term'] );
		if ( ! is_int( $new ) && ! ( is_string( $new ) && ctype_digit( $new ) ) ) {
			return 0;
		}
		$term = function_exists( 'get_term' ) ? get_term( (int) $new, $terms[ $tt_id ]['taxonomy'] ) : null;
		if ( ! is_object( $term ) || ! isset( $term->term_taxonomy_id ) || ( isset( $term->taxonomy ) && $term->taxonomy !== $terms[ $tt_id ]['taxonomy'] ) ) {
			if ( null !== $context ) {
				$context->report(
					'unresolved',
					array(
						'path'   => $ref['path'],
						'kind'   => 'term',
						'values' => array( $tt_id ),
					),
					Part_Context::WARNING
				);
			}
			return 0;
		}
		return (int) $term->term_taxonomy_id;
	}

	/** ¿Usa la consulta esa lista (su «incluir por» o «excluir por» la tiene elegida)? */
	private static function active( array $settings, string $field ): bool {
		list( $group, $value ) = self::ACTIVE[ $field ];
		return isset( $settings[ $group ] ) && in_array( $value, (array) $settings[ $group ], true );
	}

	/** Crea la consulta con sus metas (el `sanitize_callback` de cada una las sanea). Devuelve el ID o 0. */
	private static function insert( array $query, string $uid, string $status ): int {
		$result = wp_insert_post(
			array(
				'post_type'   => Store::POST_TYPE,
				'post_title'  => $query['title'],
				'post_status' => $status,
				'meta_input'  => array(
					Store::META_UID      => $uid,
					Store::META_SETTINGS => $query['settings'],
					Store::META_CONTEXT  => $query['context'],
					Store::META_RELATION => $query['relation'],
					Store::META_LIMITS   => $query['limits'],
				),
			),
			true
		);
		if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) || ! is_numeric( $result ) || (int) $result <= 0 ) {
			return 0;
		}
		Store::forget( (int) $result );
		return (int) $result;
	}

	/** Lo que «no pisar» compara al revertir: la consulta tal como la lee `Store`, o vacío si ya no existe. */
	private static function state( int $id ): array {
		Store::forget( $id );
		$query = Store::get( $id, true );
		if ( null === $query ) {
			return array();
		}
		return array(
			'uid'      => $query['uid'],
			'title'    => $query['title'],
			'status'   => $query['status'],
			'settings' => $query['settings'],
			'context'  => $query['context'],
			'relation' => $query['relation'],
			'limits'   => $query['limits'],
		);
	}

	/** Huella de lo que decide los resultados: ajustes, contexto, relación y topes (no el título ni el estado). */
	private static function fingerprint( array $query ): string {
		$relation = isset( $query['relation'] ) ? (string) $query['relation'] : 'none';
		return Part_Context::hash(
			array(
				isset( $query['settings'] ) && is_array( $query['settings'] ) ? $query['settings'] : array(),
				'none' === $relation ? 'none' : ( isset( $query['context'] ) ? (string) $query['context'] : 'none' ),
				$relation,
				isset( $query['limits'] ) && is_array( $query['limits'] ) ? $query['limits'] : array(),
			)
		);
	}

	/** Un contexto con los mismos mapas cuyo informe se descarta: para comparar o traducir sin anotar nada. */
	private static function silent( Import_Context $context ): Import_Context {
		return new Import_Context(
			array(
				'session'     => $context->session(),
				'mode'        => $context->mode(),
				'origin'      => $context->origin(),
				'maps'        => $context->maps(),
				'max_post_id' => $context->max_post_id(),
			)
		);
	}

	/**
	 * UUID v4 estable para una consulta del origen sin UUID (sólo si se creó fuera de la pantalla «Consultas»): sale del
	 * sitio y del ID, así una reimportación del mismo kit la reutiliza en vez de duplicarla. No escribe nada en el origen.
	 */
	private static function derived_uid( int $id ): string {
		$hex = sha1( 'digitalisimo_query|' . ( function_exists( 'home_url' ) ? (string) home_url() : '' ) . '|' . $id );
		return sprintf(
			'%s-%s-4%s-%s%s-%s',
			substr( $hex, 0, 8 ),
			substr( $hex, 8, 4 ),
			substr( $hex, 13, 3 ),
			dechex( 8 + ( hexdec( $hex[16] ) & 3 ) ),
			substr( $hex, 17, 3 ),
			substr( $hex, 20, 12 )
		);
	}
}
