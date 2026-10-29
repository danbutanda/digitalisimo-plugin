<?php
namespace Digitalisimo\Elements\F7b;

defined( 'ABSPATH' ) || exit;

/**
 * Contadores de F7b que existen siempre (§3.0, §4 y V.12): la opción `digitalisimo_elements_f7b_counts`, con carga
 * automática, guarda cuántos documentos del constructor usan cada capacidad. Con 0 en una capacidad, su submódulo no
 * engancha nada en el front (`active()`).
 *
 * - Claves y agujas (contrato del brief): cada capacidad se reconoce por una cadena del `_elementor_data` crudo
 *   (`NEEDLES`). Los controles de cada frente guardan exactamente esa clave y ese valor.
 * - Qué cuenta: documentos con `_elementor_data` en `publish`, `future`, `private`, `draft` o `pending` (los borradores
 *   también, para que su vista previa funcione); nunca revisiones, autoguardados ni la papelera.
 * - Lectura sin consultas: `has()` sólo lee `wp_load_alloptions()`; si la opción no está, todo vale 0.
 * - Recálculo como `Woo\Flags` (`class-flags.php` de F4-Woo), pero con una sola consulta para las seis claves
 *   (`refresh()`: `SUM(CASE WHEN … LIKE …)` sobre los `_elementor_data` de los estados que cuentan), y sólo cuando un
 *   documento empieza o deja de usar alguna capacidad:
 *   · `elementor/document/before_save` anota las capacidades que usaba (la meta ya está en caché) y
 *     `elementor/document/after_save` las compara con las guardadas;
 *   · `update_post_meta`/`updated_post_meta`/`added_post_meta` de `_elementor_data` fuera de un guardado del editor
 *     (DIGITALÍSIMO Migración, importaciones), con el valor anterior leído de la caché que `update_metadata()` acaba de
 *     cargar;
 *   · `transition_post_status` al entrar en los estados que cuentan o salir de ellos; `before_delete_post`/`deleted_post`;
 *   · `digitalisimo_elements_kit_imported` y el final del recorrido por lotes (`Scan`).
 *   Varios cambios en una petición se recalculan una sola vez, en `shutdown` (o con `flush()`).
 * - `ensure()` crea la opción con todo a 0 si falta, sin usar `add_option()` como cerrojo: no decide nada por su
 *   resultado (dos peticiones a la vez la crean igual). El primer recorrido por lotes calcula los valores.
 */
final class Flags {
	const OPTION = 'digitalisimo_elements_f7b_counts';

	/** Agujas en el `_elementor_data` crudo, por clave (contrato del brief). */
	const NEEDLES = array(
		'actions'         => '"_digitalisimo_actions_on":"yes"',
		'hidden'          => '"_digitalisimo_hidden_initial":"yes"',
		'overrides'       => '"digitalisimo_overrides":[{',
		'saved_queries'   => '"digitalisimo_saved_query":"',
		'inline_tags'     => '"digitalisimo_inline_tags":"yes"',
		'form_conditions' => '"digitalisimo_conditional":"yes"',
	);

	/** Estados de los documentos que cuentan. */
	const STATUSES = array( 'publish', 'future', 'private', 'draft', 'pending' );

	/** @var bool */
	private static $initialized = false;

	/** @var array<int,array<string,bool>> Capacidades de cada documento antes de guardarse (o de cambiar su meta). */
	private static $before = array();

	/** @var int Guardados del editor en curso (los ganchos de metas los dejan a `after_save`). */
	private static $saving = 0;

	/** @var array<int,bool> Documentos que se borran y usaban alguna capacidad. */
	private static $deleting = array();

	/** @var bool Hay que recalcular al final de la petición. */
	private static $dirty = false;

	/** @var bool El recálculo de `shutdown` ya está enganchado. */
	private static $shutdown = false;

	/** Engancha el recálculo y la creación de la opción. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'ensure' ) );
		}
		add_action( 'elementor/document/before_save', array( __CLASS__, 'before_save' ), 10, 2 );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'after_save' ), 10, 2 );
		add_action( 'update_post_meta', array( __CLASS__, 'before_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 10, 4 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'before_delete' ), 10, 1 );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted' ), 10, 1 );
		add_action( 'digitalisimo_elements_kit_imported', array( __CLASS__, 'refresh' ), 10, 0 );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Lectura sin consultas.

	/** ¿Algún documento usa la capacidad `$key`? Sólo lee `wp_load_alloptions()`. */
	public static function has( string $key ): bool {
		$counts = self::counts();
		return isset( $counts[ $key ] ) && $counts[ $key ] > 0;
	}

	/** `has()` o una petición del editor (`Module::editing()`): con `false`, el submódulo no engancha nada en el front. */
	public static function active( string $key ): bool {
		return self::has( $key ) || Module::editing();
	}

	/**
	 * Los seis contadores, leídos sólo de `wp_load_alloptions()`: si la opción no está, todo 0 y ninguna consulta.
	 *
	 * @return array<string,int>
	 */
	public static function counts(): array {
		$counts = array_fill_keys( array_keys( self::NEEDLES ), 0 );
		if ( ! function_exists( 'wp_load_alloptions' ) ) {
			return $counts;
		}
		$all = wp_load_alloptions();
		if ( ! is_array( $all ) || ! array_key_exists( self::OPTION, $all ) ) {
			return $counts;
		}
		$value = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $all[ self::OPTION ] ) : $all[ self::OPTION ];
		return self::normalize( is_array( $value ) ? $value : array() );
	}

	/**
	 * Las seis claves, en su orden, con enteros ≥ 0; cualquier otra clave se descarta.
	 *
	 * @param array $values Valores leídos o calculados.
	 * @return array<string,int>
	 */
	public static function normalize( array $values ): array {
		$out = array();
		foreach ( array_keys( self::NEEDLES ) as $key ) {
			$out[ $key ] = isset( $values[ $key ] ) && is_numeric( $values[ $key ] ) ? max( 0, (int) $values[ $key ] ) : 0;
		}
		return $out;
	}

	/**
	 * `admin_init`: la opción existe, con carga automática. No decide nada por el resultado de `add_option()`.
	 *
	 * @return bool Si faltaba en las opciones de carga automática.
	 */
	public static function ensure(): bool {
		$all = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		if ( is_array( $all ) && array_key_exists( self::OPTION, $all ) ) {
			return false;
		}
		$stored = get_option( self::OPTION, null );
		if ( null === $stored || false === $stored ) {
			add_option( self::OPTION, self::normalize( array() ), '', true );
		} elseif ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::OPTION, true );
		}
		return true;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Recálculo.

	/**
	 * Capacidades que nombra un `_elementor_data` (cadena JSON cruda o árbol ya decodificado).
	 *
	 * @param mixed $raw Datos.
	 * @return array<string,bool>
	 */
	public static function features( $raw ): array {
		if ( is_array( $raw ) ) {
			$raw = function_exists( 'wp_json_encode' ) ? wp_json_encode( $raw ) : json_encode( $raw );
		}
		$out = array();
		foreach ( self::NEEDLES as $key => $needle ) {
			$out[ $key ] = is_string( $raw ) && '' !== $raw && false !== strpos( $raw, $needle );
		}
		return $out;
	}

	/** ¿Nombra el `_elementor_data` alguna capacidad? */
	public static function uses_any( $raw ): bool {
		return in_array( true, self::features( $raw ), true );
	}

	/**
	 * Recalcula los seis contadores con una sola consulta y los guarda (sin escritura si no cambian).
	 *
	 * @return array<string,int>
	 */
	public static function refresh(): array {
		global $wpdb;
		self::$dirty = false;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) ) {
			return self::counts();
		}
		$sums = array();
		$args = array();
		foreach ( self::NEEDLES as $needle ) {
			$sums[] = 'SUM(CASE WHEN pm.meta_value LIKE %s THEN 1 ELSE 0 END)';
			$args[] = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		$statuses = "'" . implode( "','", self::STATUSES ) . "'";
		$sql      = 'SELECT ' . implode( ', ', $sums ) . " FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_elementor_data' AND p.post_type <> 'revision' AND p.post_status IN ({$statuses})";
		$row      = $wpdb->get_row( $wpdb->prepare( $sql, ...$args ), ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$counts   = array();
		$i        = 0;
		foreach ( array_keys( self::NEEDLES ) as $key ) {
			$counts[ $key ] = is_array( $row ) && isset( $row[ $i ] ) ? (int) $row[ $i ] : 0;
			++$i;
		}
		self::save( $counts );
		return self::normalize( $counts );
	}

	/**
	 * Guarda los contadores con carga automática; sin escritura si no cambian.
	 *
	 * @param array $counts Contadores.
	 */
	public static function save( array $counts ): void {
		$counts = self::normalize( $counts );
		$all    = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		if ( is_array( $all ) && array_key_exists( self::OPTION, $all ) && self::counts() === $counts ) {
			return;
		}
		update_option( self::OPTION, $counts, true );
	}

	/** Pide un recálculo al final de la petición (una sola consulta aunque cambien muchos documentos). */
	public static function mark_dirty(): void {
		self::$dirty = true;
		if ( ! self::$shutdown && function_exists( 'add_action' ) ) {
			self::$shutdown = true;
			add_action( 'shutdown', array( __CLASS__, 'flush' ), 5 );
		}
	}

	/** Recalcula si algo lo pidió. */
	public static function flush(): void {
		if ( self::$dirty ) {
			self::refresh();
		}
	}

	/** ¿Hay un recálculo pendiente? */
	public static function dirty(): bool {
		return self::$dirty;
	}

	/**
	 * `elementor/document/before_save`: anota las capacidades que usaba el documento (meta en caché: el documento ya se
	 * cargó) y que hay un guardado en curso.
	 *
	 * @param object $document Documento.
	 */
	public static function before_save( $document, $data = null ): void {
		++self::$saving;
		$id = self::document_id( $document );
		if ( $id > 0 && self::is_document( $id ) ) {
			self::$before[ $id ] = self::features( get_post_meta( $id, '_elementor_data', true ) );
		}
	}

	/**
	 * `elementor/document/after_save`: si cambió lo que usa el documento, recálculo.
	 *
	 * @param object $document Documento guardado.
	 * @param mixed  $data     `{ elements, settings }` guardados.
	 */
	public static function after_save( $document, $data = null ): void {
		self::$saving = max( 0, self::$saving - 1 );
		$id           = self::document_id( $document );
		$before       = isset( self::$before[ $id ] ) ? self::$before[ $id ] : null;
		unset( self::$before[ $id ] );
		if ( $id <= 0 || ( is_object( $document ) && method_exists( $document, 'is_autosave' ) && $document->is_autosave() ) || ! self::is_document( $id ) ) {
			return;
		}
		$after = self::features( is_array( $data ) && isset( $data['elements'] ) ? $data['elements'] : get_post_meta( $id, '_elementor_data', true ) );
		if ( null === $before ? in_array( true, $after, true ) : $before !== $after ) {
			self::mark_dirty();
		}
	}

	/**
	 * `update_post_meta` (antes de escribir): las capacidades del valor anterior, de la caché que `update_metadata()`
	 * acaba de cargar. Sólo para `_elementor_data` fuera de un guardado del editor.
	 */
	public static function before_meta( $meta_id, $object_id, $meta_key = '', $value = null ): void {
		if ( '_elementor_data' !== $meta_key || self::$saving > 0 ) {
			return;
		}
		$id = (int) $object_id;
		if ( $id > 0 && ! self::is_revision( $id ) ) {
			self::$before[ $id ] = self::features( get_post_meta( $id, '_elementor_data', true ) );
		}
	}

	/**
	 * `added_post_meta`/`updated_post_meta` de `_elementor_data` fuera de un guardado del editor (Migración, kits).
	 *
	 * @param int    $meta_id   ID de la meta.
	 * @param int    $object_id Entrada.
	 * @param string $meta_key  Clave.
	 * @param mixed  $value     Valor nuevo.
	 */
	public static function on_meta( $meta_id, $object_id, $meta_key = '', $value = null ): void {
		if ( '_elementor_data' !== $meta_key || self::$saving > 0 ) {
			return;
		}
		$id     = (int) $object_id;
		$before = isset( self::$before[ $id ] ) ? self::$before[ $id ] : null;
		unset( self::$before[ $id ] );
		if ( $id <= 0 || self::is_revision( $id ) || ! self::counted_status( self::status( $id ) ) ) {
			return;
		}
		$after = self::features( $value );
		if ( null === $before ? in_array( true, $after, true ) : $before !== $after ) {
			self::mark_dirty();
		}
	}

	/**
	 * `transition_post_status`: sólo al entrar en los estados que cuentan o al salir de ellos.
	 *
	 * @param string $new  Estado nuevo.
	 * @param string $old  Estado anterior.
	 * @param object $post Entrada.
	 */
	public static function on_status( $new, $old, $post ): void {
		if ( self::counted_status( (string) $new ) === self::counted_status( (string) $old ) || ! is_object( $post ) || empty( $post->ID ) ) {
			return;
		}
		$id = (int) $post->ID;
		if ( self::is_document( $id ) && self::uses_any( get_post_meta( $id, '_elementor_data', true ) ) ) {
			self::mark_dirty();
		}
	}

	/** `before_delete_post`: anota si el documento borrado usaba alguna capacidad (las metas aún existen). */
	public static function before_delete( $post_id ): void {
		$id = (int) $post_id;
		if ( $id > 0 && self::is_document( $id ) && self::uses_any( get_post_meta( $id, '_elementor_data', true ) ) ) {
			self::$deleting[ $id ] = true;
		}
	}

	/** `deleted_post`: recálculo sólo si el documento borrado usaba alguna capacidad. */
	public static function on_deleted( $post_id ): void {
		$id = (int) $post_id;
		if ( empty( self::$deleting[ $id ] ) ) {
			return;
		}
		unset( self::$deleting[ $id ] );
		self::mark_dirty();
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Internos compartidos con `Usages` y `Scan`.

	/**
	 * Documento del constructor: ni revisión ni autoguardado, de la biblioteca o de un tipo con el constructor activo y
	 * en modo constructor. Un pedido o un producto sin constructor no leen ninguna meta (como `Woo\Flags::counts()`).
	 */
	public static function is_document( int $id ): bool {
		if ( $id <= 0 || self::is_revision( $id ) ) {
			return false;
		}
		$type = function_exists( 'get_post_type' ) ? (string) get_post_type( $id ) : '';
		if ( 'elementor_library' === $type ) {
			return true;
		}
		if ( '' === $type || 'revision' === $type || ! function_exists( 'post_type_supports' ) || ! post_type_supports( $type, 'elementor' ) ) {
			return false;
		}
		return 'builder' === get_post_meta( $id, '_elementor_edit_mode', true );
	}

	/** Revisión o autoguardado. */
	public static function is_revision( int $id ): bool {
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $id ) ) {
			return true;
		}
		return function_exists( 'wp_is_post_autosave' ) && (bool) wp_is_post_autosave( $id );
	}

	/** ¿Cuenta un documento en este estado? */
	public static function counted_status( string $status ): bool {
		return in_array( $status, self::STATUSES, true );
	}

	private static function status( int $id ): string {
		return function_exists( 'get_post_status' ) ? (string) get_post_status( $id ) : '';
	}

	/** ID de un documento del constructor (`get_main_id()` o `get_id()`). */
	public static function document_id( $document ): int {
		if ( ! is_object( $document ) ) {
			return 0;
		}
		if ( method_exists( $document, 'get_main_id' ) ) {
			return (int) $document->get_main_id();
		}
		return method_exists( $document, 'get_id' ) ? (int) $document->get_id() : 0;
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$before      = array();
		self::$saving      = 0;
		self::$deleting    = array();
		self::$dirty       = false;
		self::$shutdown    = false;
		self::$initialized = false;
	}
}
