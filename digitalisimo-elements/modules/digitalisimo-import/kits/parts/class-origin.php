<?php
namespace Digitalisimo\Elements\Import\Kits\Parts;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Part;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Export_Context;
use Digitalisimo\Elements\Import\Kits\Import_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Export\Meta_Blacklist;

defined( 'ABSPATH' ) || exit;

/**
 * Parte `origin`: procedencia y reimportación (D7, §9.6).
 *
 * Lo creado por un kit lleva `_digitalisimo_import_origin = { origin:'kit', kit, type, id, version, hash, local,
 * session, status }` (entradas, términos y adjuntos). Al volver a importar el mismo kit:
 * - si ni el origen (`hash`) ni la copia local (`local`) cambiaron, se reutiliza lo que hay, en cualquier estado
 *   salvo la papelera;
 * - si cambió el origen, se crea un borrador «actualización»; si se editó la copia local, uno «conflicto».
 * Nunca se actualiza en el sitio lo ya importado.
 *
 * Tres huellas:
 * - la del kit (`fingerprint()`): `manifest.digitalisimo.kit`; si no hay, `sha1( site|name )`; en un Template Kit
 *   de Envato, `sha1( title|fuentes ordenadas )` (nombres de archivo unidos por comas);
 * - la de origen (`document_hash()`, `wxr_hash()`): sha1 del documento normalizado sin `id` ni `import_settings`, o
 *   del título, contenido, estado y metas filtradas de una entrada del WXR;
 * - la local (`local_hash()`): lo que el administrador puede editar después de importar (no el estado: publicar un
 *   borrador importado no es editarlo).
 * Las fórmulas son las mismas que usa el importador (`Kits\Session`): una sola huella por objeto.
 *
 * La parte no exporta datos propios (la huella del kit va en el manifiesto) y su `import()` sólo resume lo que
 * hicieron los runners. El trabajo lo hacen sus funciones estáticas, que llaman los runners de K, el filtro
 * `digitalisimo_elements_kit_existing_document` y el filtro del WXR `wp_import_posts`.
 */
final class Origin implements Part {
	const KEY     = 'origin';
	const VERSION = 1;

	const NEW_DOCUMENT = 'new';
	const REUSE        = 'reuse';
	const UPDATE       = 'update';
	const CONFLICT     = 'conflict';

	const KIT_PATTERN = '/^[0-9a-f]{40}$/';
	const TYPE_PATTERN = '/^[a-z0-9_:-]{1,64}$/';

	/** Estados en los que no se busca: la papelera (lo revertido, D16). */
	const SKIP_STATUSES = array( 'trash' );

	/** @var string Huella del kit de la importación en curso, para el filtro de documentos existentes. */
	private static $kit = '';

	/** @var array[] Huella => `[ "<type>:<id>" => [ objeto => procedencia ] ]`, por petición. */
	private static $index = array();

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
		return null;
	}

	/** Resumen de la procedencia de lo que creó la sesión (reutilizado no se crea, así que no cuenta aquí). */
	public function import( array $data, int $version, Import_Context $context ): array {
		$summary = array(
			self::NEW_DOCUMENT => 0,
			self::UPDATE       => 0,
			self::CONFLICT     => 0,
		);
		if ( '' === $context->kit() ) {
			return $summary;
		}
		foreach ( self::index( $context->kit(), true ) as $objects ) {
			foreach ( $objects as $origin ) {
				if ( $context->session() === $origin['session'] && isset( $summary[ $origin['status'] ] ) ) {
					++$summary[ $origin['status'] ];
				}
			}
		}
		return $summary;
	}

	public function revert( array $stored, Revert_Context $context ): void {
	}

	// ── Huellas ─────────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Huella del kit (D7). Recibe `manifest.json` completo: la de K, la de un Template Kit de Envato o la del núcleo.
	 */
	public static function fingerprint( array $manifest ): string {
		$own = isset( $manifest[ Module::MANIFEST_KEY ]['kit'] ) ? $manifest[ Module::MANIFEST_KEY ]['kit'] : '';
		if ( is_string( $own ) && 1 === preg_match( self::KIT_PATTERN, $own ) ) {
			return $own;
		}
		if ( isset( $manifest['manifest_version'] ) ) {
			$sources = array();
			foreach ( isset( $manifest['templates'] ) && is_array( $manifest['templates'] ) ? $manifest['templates'] : array() as $template ) {
				if ( is_array( $template ) && isset( $template['source'] ) && is_string( $template['source'] ) ) {
					$sources[] = $template['source'];
				}
			}
			return self::envato_fingerprint( isset( $manifest['title'] ) && is_string( $manifest['title'] ) ? $manifest['title'] : '', $sources );
		}
		$site = isset( $manifest['site'] ) && is_string( $manifest['site'] ) ? $manifest['site'] : '';
		$name = isset( $manifest['name'] ) && is_string( $manifest['name'] ) ? $manifest['name'] : '';
		return self::site_fingerprint( $site, $name );
	}

	/**
	 * `sha1( site|name )`, con `site` sin barra final: la huella que escribe la exportación de K en
	 * `manifest.digitalisimo.kit` y la que calcula el normalizador para un kit del núcleo.
	 */
	public static function site_fingerprint( string $site, string $name ): string {
		return sha1( rtrim( $site, '/' ) . '|' . $name );
	}

	/** `sha1( title|fuentes )`: nombres de archivo de las plantillas, ordenados y unidos por comas (como el normalizador). */
	public static function envato_fingerprint( string $title, array $sources ): string {
		$names = array();
		foreach ( $sources as $source ) {
			if ( is_string( $source ) ) {
				$names[] = basename( $source );
			}
		}
		sort( $names, SORT_STRING );
		return sha1( $title . '|' . implode( ',', $names ) );
	}

	/**
	 * Huella de origen de un documento del kit: el JSON normalizado sin `id` ni `import_settings` (§9.6). Es la misma
	 * fórmula que `Kits\Session::document_hash()`; la prueba de partes comprueba que no se separen.
	 */
	public static function document_hash( array $data ): string {
		unset( $data['id'], $data['import_settings'] );
		return Part_Context::hash( $data );
	}

	/**
	 * Huella de origen de cualquier objeto del kit (documentos, fuentes, iconos, fragmentos): la de documento.
	 */
	public static function data_hash( array $data ): string {
		return self::document_hash( $data );
	}

	/**
	 * Huella de origen de una entrada del WXR (forma de `WP_Import::$posts`): título, contenido, estado y metas
	 * filtradas por la lista negra, agrupadas por clave. Misma fórmula que `Kits\Session::wxr_hash()`.
	 */
	public static function wxr_hash( array $post ): string {
		$meta = array();
		foreach ( isset( $post['postmeta'] ) && is_array( $post['postmeta'] ) ? $post['postmeta'] : array() as $item ) {
			if ( is_array( $item ) && isset( $item['key'] ) && ! Meta_Blacklist::blocks( (string) $item['key'] ) ) {
				$meta[ (string) $item['key'] ][] = isset( $item['value'] ) && is_scalar( $item['value'] ) ? (string) $item['value'] : '';
			}
		}
		return Part_Context::hash(
			array(
				'title'   => isset( $post['post_title'] ) && is_scalar( $post['post_title'] ) ? (string) $post['post_title'] : '',
				'content' => isset( $post['post_content'] ) && is_scalar( $post['post_content'] ) ? (string) $post['post_content'] : '',
				'status'  => isset( $post['status'] ) && is_scalar( $post['status'] ) ? (string) $post['status'] : '',
				'meta'    => $meta,
			)
		);
	}

	/**
	 * Huella local de un objeto creado por un kit: lo que el administrador puede editar, sin el estado (publicar un
	 * borrador importado no es editarlo). En entradas y adjuntos es la misma fórmula que
	 * `Kits\Session::local_hash()`: título, contenido, `_elementor_data` y ajustes de página.
	 *
	 * @param int    $id     Objeto.
	 * @param string $object `post` (también adjuntos) o `term`.
	 */
	public static function local_hash( int $id, string $object = 'post' ): string {
		if ( 'term' === $object ) {
			$term = function_exists( 'get_term' ) ? get_term( $id ) : null;
			if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
				return '';
			}
			return Part_Context::hash(
				array(
					'name'        => (string) $term->name,
					'slug'        => (string) $term->slug,
					'description' => (string) $term->description,
				)
			);
		}
		$post = function_exists( 'get_post' ) ? get_post( $id ) : null;
		if ( ! is_object( $post ) ) {
			return '';
		}
		return Part_Context::hash(
			array(
				'title'    => (string) $post->post_title,
				'content'  => (string) $post->post_content,
				'data'     => (string) get_post_meta( $id, '_elementor_data', true ),
				'settings' => get_post_meta( $id, '_elementor_page_settings', true ),
			)
		);
	}

	// ── Procedencia ─────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Busca en cualquier estado (salvo la papelera) lo que creó antes el mismo kit con ese tipo e ID de origen.
	 *
	 * @param string     $kit    Huella del kit.
	 * @param string     $type   Tipo de entrada (`page`, `elementor_library`, `attachment`…) o `term:<taxonomía>`.
	 * @param int|string $old_id ID en el sitio de origen.
	 * @param string     $hash   Huella de origen del objeto que llega.
	 *
	 * @return array{status: string, id: int|null, previous: int} `reuse` (con `id`), `update`, `conflict` o `new`.
	 */
	public static function lookup( string $kit, string $type, $old_id, string $hash ): array {
		$result = array(
			'status'   => self::NEW_DOCUMENT,
			'id'       => null,
			'previous' => 0,
		);
		if ( 1 !== preg_match( self::KIT_PATTERN, $kit ) || ( ! is_int( $old_id ) && ! is_string( $old_id ) ) || '' === (string) $old_id ) {
			return $result;
		}
		$index      = self::index( $kit );
		$key        = self::object_key( $type, $old_id );
		$candidates = isset( $index[ $key ] ) ? $index[ $key ] : array();
		if ( ! $candidates ) {
			return $result;
		}
		// El más reciente decide, como `Kits\Session::lookup()`.
		krsort( $candidates, SORT_NUMERIC );
		$id                 = (int) key( $candidates );
		$origin             = reset( $candidates );
		$object             = 0 === strpos( $type, 'term:' ) ? 'term' : 'post';
		$result['previous'] = $id;
		if ( $origin['hash'] !== $hash ) {
			$result['status'] = self::UPDATE;
			return $result;
		}
		if ( '' !== $origin['local'] && ! hash_equals( $origin['local'], self::local_hash( $id, $object ) ) ) {
			$result['status'] = self::CONFLICT;
			return $result;
		}
		$result['status'] = self::REUSE;
		$result['id']     = $id;
		return $result;
	}

	/** ID para reutilizar, o null (crear un borrador «actualización» o «conflicto», o nuevo). */
	public static function existing( string $kit, string $type, $old_id, string $hash ): ?int {
		$found = self::lookup( $kit, $type, $old_id, $hash );
		return self::REUSE === $found['status'] ? $found['id'] : null;
	}

	/**
	 * Valor de `_digitalisimo_import_origin` (§5.3), sin `local`: se calcula al terminar la importación con
	 * `write()`, cuando el documento ya tiene su contenido definitivo.
	 *
	 * @param string     $kit     Huella del kit.
	 * @param string     $type    Tipo (como en `lookup()`).
	 * @param int|string $old_id  ID de origen.
	 * @param string     $hash    Huella de origen.
	 * @param string     $session Sesión de la importación.
	 * @param string     $status  `new`, `update` o `conflict`.
	 * @param int        $version Versión del formato del kit.
	 */
	public static function record( string $kit, string $type, $old_id, string $hash, string $session, string $status = self::NEW_DOCUMENT, int $version = 1 ): array {
		return array(
			'origin'  => 'kit',
			'kit'     => $kit,
			'type'    => $type,
			'id'      => (string) $old_id,
			'version' => $version,
			'hash'    => $hash,
			'local'   => '',
			'session' => $session,
			'status'  => in_array( $status, array( self::NEW_DOCUMENT, self::UPDATE, self::CONFLICT ), true ) ? $status : self::NEW_DOCUMENT,
		);
	}

	/**
	 * Escribe la procedencia de un objeto con su huella local actual. Sólo con datos válidos.
	 *
	 * @param int    $id     Entrada, adjunto o término.
	 * @param array  $origin `record()`; `local` se recalcula.
	 * @param string $object `post` o `term`.
	 */
	public static function write( int $id, array $origin, string $object = 'post' ): bool {
		$origin = self::clean( $origin );
		if ( $id <= 0 || null === $origin ) {
			return false;
		}
		$origin['local'] = self::local_hash( $id, $object );
		$saved           = 'term' === $object
			? update_term_meta( $id, Module::META_ORIGIN, $origin )
			: update_post_meta( $id, Module::META_ORIGIN, $origin );
		self::$index = array();
		return false !== $saved;
	}

	/** Procedencia guardada de un objeto, o null. */
	public static function read( int $id, string $object = 'post' ): ?array {
		$value = 'term' === $object ? get_term_meta( $id, Module::META_ORIGIN, true ) : get_post_meta( $id, Module::META_ORIGIN, true );
		return is_array( $value ) ? self::clean( $value ) : null;
	}

	/** Recalcula `local` después de que el runner final guardó el contenido definitivo. */
	public static function refresh_local( int $id, string $object = 'post' ): bool {
		$origin = self::read( $id, $object );
		return null !== $origin && self::write( $id, $origin, $object );
	}

	/** Fija la huella del kit de la importación en curso (la usa el filtro de documentos existentes). */
	public static function use_kit( string $kit ): void {
		self::$kit = 1 === preg_match( self::KIT_PATTERN, $kit ) ? $kit : '';
	}

	/**
	 * Callback de `digitalisimo_elements_kit_existing_document( ?int $id, string $type, $old_id, array $data )`
	 * (§7.8 #1, #3, #5, #6 y #7): el ID para reutilizar, o el valor que ya traía el filtro.
	 *
	 * @param int|null   $id     Lo que ya traía el filtro.
	 * @param string     $type   Tipo de entrada del documento.
	 * @param int|string $old_id ID de origen.
	 * @param array      $data   Documento del kit (para la huella de origen).
	 */
	public static function existing_document( $id, $type = '', $old_id = 0, $data = array() ) {
		if ( null !== $id && (int) $id > 0 ) {
			return $id;
		}
		if ( '' === self::$kit || ! is_string( $type ) || ! is_array( $data ) ) {
			return $id;
		}
		$found = self::existing( self::$kit, $type, $old_id, self::data_hash( $data ) );
		return null === $found ? $id : $found;
	}

	/**
	 * Olvida la memoria de la petición.
	 *
	 * @internal Sólo para pruebas.
	 */
	public static function reset(): void {
		self::$kit   = '';
		self::$index = array();
	}

	// ── Internos ────────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Todo lo que creó un kit, en una consulta por petición: `"<type>:<id>"` => `[ objeto => procedencia ]`.
	 *
	 * @param string $kit   Huella.
	 * @param bool   $fresh Vuelve a leer aunque haya memoria.
	 */
	private static function index( string $kit, bool $fresh = false ): array {
		if ( ! $fresh && isset( self::$index[ $kit ] ) ) {
			return self::$index[ $kit ];
		}
		$index = array();
		if ( function_exists( 'get_posts' ) ) {
			// Todos los tipos registrados, no `any`: `any` deja fuera los que tienen `exclude_from_search`, como la
			// biblioteca de plantillas, y una reimportación las duplicaría (A8). `any` en el estado tampoco incluye
			// la papelera ni los estados internos; `SKIP_STATUSES` ya excluye la papelera.
			$ids = get_posts(
				array(
					'post_type'        => function_exists( 'get_post_types' ) ? array_values( get_post_types() ) : 'any',
					'post_status'      => 'any',
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
					'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => Module::META_ORIGIN,
							'value'   => $kit,
							'compare' => 'LIKE',
						),
					),
				)
			);
			foreach ( is_array( $ids ) ? $ids : array() as $id ) {
				$status = get_post_status( $id );
				if ( in_array( $status, self::SKIP_STATUSES, true ) ) {
					continue;
				}
				$origin = self::read( (int) $id );
				if ( null !== $origin && $kit === $origin['kit'] ) {
					// El tipo es el que usó quien la anotó (tipo de entrada o de documento); sin él, el de la entrada.
					$type = '' !== $origin['type'] ? $origin['type'] : (string) get_post_type( $id );
					$index[ self::object_key( $type, $origin['id'] ) ][ (int) $id ] = $origin;
				}
			}
		}
		if ( function_exists( 'get_terms' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => function_exists( 'get_taxonomies' ) ? array_values( get_taxonomies() ) : array(),
					'hide_empty' => false,
					'fields'     => 'ids',
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => Module::META_ORIGIN,
							'value'   => $kit,
							'compare' => 'LIKE',
						),
					),
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term_id ) {
				$origin = self::read( (int) $term_id, 'term' );
				$term   = get_term( (int) $term_id );
				if ( null !== $origin && $kit === $origin['kit'] && is_object( $term ) && isset( $term->taxonomy ) ) {
					$index[ self::object_key( 'term:' . $term->taxonomy, $origin['id'] ) ][ (int) $term_id ] = $origin;
				}
			}
		}
		self::$index[ $kit ] = $index;
		return $index;
	}

	private static function object_key( string $type, $old_id ): string {
		return $type . ':' . (string) $old_id;
	}

	/** Procedencia con sus campos y tipos, o null si no es válida. */
	private static function clean( array $origin ): ?array {
		$kit  = isset( $origin['kit'] ) && is_string( $origin['kit'] ) ? $origin['kit'] : '';
		$type = isset( $origin['type'] ) && is_string( $origin['type'] ) ? $origin['type'] : '';
		$id   = isset( $origin['id'] ) && is_scalar( $origin['id'] ) ? (string) $origin['id'] : '';
		if ( 1 !== preg_match( self::KIT_PATTERN, $kit ) || ( '' !== $type && 1 !== preg_match( self::TYPE_PATTERN, $type ) ) || '' === $id ) {
			return null;
		}
		$status = isset( $origin['status'] ) && is_string( $origin['status'] ) ? $origin['status'] : self::NEW_DOCUMENT;
		return array(
			'origin'  => 'kit',
			'kit'     => $kit,
			'type'    => $type,
			'id'      => $id,
			'version' => isset( $origin['version'] ) ? max( 1, (int) $origin['version'] ) : 1,
			'hash'    => isset( $origin['hash'] ) && is_string( $origin['hash'] ) ? $origin['hash'] : '',
			'local'   => isset( $origin['local'] ) && is_string( $origin['local'] ) ? $origin['local'] : '',
			'session' => isset( $origin['session'] ) && is_string( $origin['session'] ) ? $origin['session'] : '',
			'status'  => in_array( $status, array( self::NEW_DOCUMENT, self::UPDATE, self::CONFLICT ), true ) ? $status : self::NEW_DOCUMENT,
		);
	}
}
