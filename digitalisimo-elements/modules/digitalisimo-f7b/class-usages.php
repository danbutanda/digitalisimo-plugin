<?php
namespace Digitalisimo\Elements\F7b;

defined( 'ABSPATH' ) || exit;

/**
 * Usos de plantillas y consultas guardadas (V.6, C2-20).
 *
 * - **Almacén:** la meta multivalor `_digitalisimo_uses` de cada documento del constructor, una fila por referencia:
 *   `t:<id>` por cada widget `template` con `template_id` y `q:<id>` por cada elemento (de cualquier tipo) con
 *   `digitalisimo_saved_query`. Se reescribe sólo si cambia, en `elementor/document/after_save` y en
 *   `added_post_meta`/`updated_post_meta` de `_elementor_data` fuera de un guardado del editor; nunca en revisiones
 *   ni autoguardados. El recorrido por lotes (`Scan`) la escribe para lo que ya existía.
 * - **Búsqueda inversa:** `of()` lee `meta_key = '_digitalisimo_uses' AND meta_value = 't:123'` (índice de
 *   `postmeta` por clave), sólo en la administración; cuenta `publish`, `future` y `private` y lista aparte los
 *   borradores (`draft`, `pending`). La papelera no cuenta. Memorizada por petición; `prime()` resuelve una página de
 *   la lista con una sola consulta.
 * - **Kits:** la meta no viaja. Su propio `wxr_export_skip_postmeta` respeta un `true` previo, como
 *   `Meta_Blacklist::skip()` de K (`kits/export/class-meta-blacklist.php:63`), sin tocar K. Los metadatos de los
 *   documentos que exporta el núcleo ya omiten las metas protegidas (`_*`, `elementor/core/base/document.php:1795`).
 */
final class Usages {
	const META          = '_digitalisimo_uses';
	const KIND_TEMPLATE = 't';
	const KIND_QUERY    = 'q';

	/** Estados que cuentan como uso. */
	const COUNTED = array( 'publish', 'future', 'private' );

	/** Estados que se listan aparte («borradores»). */
	const DRAFTS = array( 'draft', 'pending' );

	/** Filas como mucho por objeto en una lectura (más se cuentan igual por estado). */
	const LIMIT = 500;

	/** Profundidad máxima del árbol de elementos que se recorre. */
	const MAX_DEPTH = 64;

	/** @var bool */
	private static $initialized = false;

	/** @var int Guardados del editor en curso. */
	private static $saving = 0;

	/** @var array<string,array> Resultados de `of()` por `kind:id`. */
	private static $memo = array();

	/** Engancha la reescritura de la meta y la exclusión de la exportación. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/document/before_save', array( __CLASS__, 'before_save' ), 10, 0 );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'after_save' ), 10, 2 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 10, 4 );
		add_filter( 'wxr_export_skip_postmeta', array( __CLASS__, 'skip_export' ), 10, 2 );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Escritura.

	/**
	 * Referencias de un árbol de elementos, únicas y ordenadas (`t:12`, `q:34`).
	 *
	 * @param mixed $elements Árbol de elementos (o la cadena JSON de `_elementor_data`).
	 * @return string[]
	 */
	public static function refs( $elements ): array {
		if ( is_string( $elements ) ) {
			$elements = json_decode( $elements, true );
		}
		$found = array();
		if ( is_array( $elements ) ) {
			self::walk( $elements, $found, 0 );
		}
		$found = array_keys( $found );
		sort( $found, SORT_STRING );
		return $found;
	}

	private static function walk( array $elements, array &$found, int $depth ): void {
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			if ( isset( $element['widgetType'] ) && 'template' === $element['widgetType'] ) {
				$id = self::id( isset( $settings['template_id'] ) ? $settings['template_id'] : 0 );
				if ( $id > 0 ) {
					$found[ self::KIND_TEMPLATE . ':' . $id ] = true;
				}
			}
			$query = self::id( isset( $settings['digitalisimo_saved_query'] ) ? $settings['digitalisimo_saved_query'] : 0 );
			if ( $query > 0 ) {
				$found[ self::KIND_QUERY . ':' . $query ] = true;
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::walk( $element['elements'], $found, $depth + 1 );
			}
		}
	}

	/** ID entero positivo de un ajuste (`12`, `"12"`), o 0. */
	private static function id( $value ): int {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}
		return is_string( $value ) && ctype_digit( $value ) ? (int) $value : 0;
	}

	/**
	 * Reescribe la meta del documento si cambió. Sin referencias ni filas previas, sólo lee la caché de metas.
	 *
	 * @param int   $post_id  Documento.
	 * @param mixed $elements Árbol de elementos o cadena JSON.
	 * @return bool Si escribió.
	 */
	public static function sync( int $post_id, $elements ): bool {
		if ( $post_id <= 0 || Flags::is_revision( $post_id ) ) {
			return false;
		}
		$refs = self::refs( $elements );
		$old  = get_post_meta( $post_id, self::META, false );
		$old  = is_array( $old ) ? array_values( array_unique( array_map( 'strval', $old ) ) ) : array();
		sort( $old, SORT_STRING );
		if ( $old === $refs ) {
			return false;
		}
		delete_post_meta( $post_id, self::META );
		foreach ( $refs as $ref ) {
			add_post_meta( $post_id, self::META, $ref, false );
		}
		self::$memo = array();
		return true;
	}

	/** `elementor/document/before_save`: hay un guardado del editor en curso. */
	public static function before_save(): void {
		++self::$saving;
	}

	/**
	 * `elementor/document/after_save`.
	 *
	 * @param object $document Documento guardado.
	 * @param mixed  $data     `{ elements, settings }` guardados.
	 */
	public static function after_save( $document, $data = null ): void {
		self::$saving = max( 0, self::$saving - 1 );
		$id           = Flags::document_id( $document );
		if ( $id <= 0 || ( is_object( $document ) && method_exists( $document, 'is_autosave' ) && $document->is_autosave() ) || ! Flags::is_document( $id ) ) {
			return;
		}
		self::sync( $id, is_array( $data ) && isset( $data['elements'] ) ? $data['elements'] : get_post_meta( $id, '_elementor_data', true ) );
	}

	/**
	 * `added_post_meta`/`updated_post_meta` de `_elementor_data` fuera de un guardado del editor. La clave ya dice que
	 * es un documento del constructor: sólo se descartan revisiones y autoguardados.
	 */
	public static function on_meta( $meta_id, $object_id, $meta_key = '', $value = null ): void {
		if ( '_elementor_data' !== $meta_key || self::$saving > 0 ) {
			return;
		}
		$id = (int) $object_id;
		if ( $id > 0 && ! Flags::is_revision( $id ) ) {
			self::sync( $id, $value );
		}
	}

	/** `wxr_export_skip_postmeta`: la meta de usos nunca sale en un WXR; respeta un `true` previo. */
	public static function skip_export( $skip, $key = '' ): bool {
		return (bool) $skip || self::META === $key;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Lectura (administración).

	/**
	 * Usos de una plantilla (`t`) o una consulta guardada (`q`).
	 *
	 * @return array{count:int,posts:int[],drafts:int[]} `count` y `posts`: `publish`, `future` y `private`;
	 *                                                    `drafts`: `draft` y `pending`. El propio objeto no cuenta.
	 */
	public static function of( int $id, string $kind ): array {
		$empty = array(
			'count'  => 0,
			'posts'  => array(),
			'drafts' => array(),
		);
		if ( $id <= 0 || ! in_array( $kind, array( self::KIND_TEMPLATE, self::KIND_QUERY ), true ) ) {
			return $empty;
		}
		$key = $kind . ':' . $id;
		if ( ! isset( self::$memo[ $key ] ) ) {
			self::prime( array( $id ), $kind );
		}
		return isset( self::$memo[ $key ] ) ? self::$memo[ $key ] : $empty;
	}

	/**
	 * Resuelve los usos de varios objetos con una sola consulta y los memoriza.
	 *
	 * @param int[]  $ids  Plantillas o consultas.
	 * @param string $kind `t` o `q`.
	 */
	public static function prime( array $ids, string $kind ): void {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		$ids = array_values( array_filter( $ids, static function ( $id ) use ( $kind ) {
			return $id > 0 && ! isset( self::$memo[ $kind . ':' . $id ] );
		} ) );
		if ( ! $ids || ! in_array( $kind, array( self::KIND_TEMPLATE, self::KIND_QUERY ), true ) ) {
			return;
		}
		foreach ( $ids as $id ) {
			self::$memo[ $kind . ':' . $id ] = array(
				'count'  => 0,
				'posts'  => array(),
				'drafts' => array(),
			);
		}
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return;
		}
		$values   = array_map(
			static function ( $id ) use ( $kind ) {
				return $kind . ':' . $id;
			},
			$ids
		);
		$holders  = implode( ',', array_fill( 0, count( $values ), '%s' ) );
		$statuses = "'" . implode( "','", array_merge( self::COUNTED, self::DRAFTS ) ) . "'";
		$limit    = self::LIMIT * count( $ids );
		$sql      = "SELECT pm.meta_value AS ref, p.ID AS id, p.post_status AS status FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value IN ({$holders}) AND p.post_type <> 'revision' AND p.post_status IN ({$statuses}) ORDER BY p.ID ASC LIMIT %d";
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, self::META, ...array_merge( $values, array( $limit ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$ref = isset( $row['ref'] ) ? (string) $row['ref'] : '';
			$doc = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( ! isset( self::$memo[ $ref ] ) || $doc <= 0 || $ref === $kind . ':' . $doc ) {
				continue;
			}
			$status = isset( $row['status'] ) ? (string) $row['status'] : '';
			$group  = in_array( $status, self::COUNTED, true ) ? 'posts' : 'drafts';
			if ( ! in_array( $doc, self::$memo[ $ref ][ $group ], true ) ) {
				self::$memo[ $ref ][ $group ][] = $doc;
			}
		}
		foreach ( $values as $ref ) {
			self::$memo[ $ref ]['count'] = count( self::$memo[ $ref ]['posts'] );
		}
	}

	/** Olvida lo memorizado (tras escribir o en las pruebas). */
	public static function forget(): void {
		self::$memo = array();
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$memo        = array();
		self::$saving      = 0;
		self::$initialized = false;
	}
}
