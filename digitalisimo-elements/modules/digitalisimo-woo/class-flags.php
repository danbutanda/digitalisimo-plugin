<?php
namespace Digitalisimo\Elements\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Banderas y opciones de F4-Woo que existen siempre (§4.7 y V.12.4).
 *
 * `digitalisimo_elements_woo_fragments` (0 o 1, con carga automática): algún documento publicado del constructor usa
 * la barra de envío gratis o el cupón (el widget `digitalisimo-woo-shipping-bar` o `digitalisimo_cart_bar`/
 * `digitalisimo_cart_coupon` en `yes` en un Menú del carrito). Sin ella, el filtro de fragmentos no calcula nada.
 *
 * Qué la recalcula, y sólo en documentos del constructor (`_elementor_edit_mode = builder` o `elementor_library`), nunca
 * en revisiones ni autoguardados:
 * - `elementor/document/after_save`: si el documento publicado usa la barra o el cupón, la bandera pasa a 1 **sin
 *   consulta**; si no los usa y la bandera está en 0, no se consulta nada; sólo se consulta si el documento los usaba
 *   antes de guardar (leído en `before_save`, de la caché de metas) y ya no los usa: es quitar un uso;
 * - `transition_post_status` al entrar en `publish` o salir de él; `before_delete_post`/`deleted_post`;
 * - `added_post_meta`/`updated_post_meta` de `_elementor_data` fuera de un guardado del editor (DIGITALÍSIMO Migración
 *   la escribe sin `after_save`), como `Pro_Gate::on_meta()`: sin el valor anterior, con la bandera en 1 y un valor
 *   que ya no la usa, se consulta;
 * - `digitalisimo_elements_kit_imported`: siempre se recalcula (una consulta).
 *
 * La consulta: `SELECT 1 … JOIN posts publicados … WHERE meta_key = '_elementor_data' AND ( LIKE … ) LIMIT 1`.
 * Un pedido, un producto o un autoguardado nunca consultan (V.12.4, prueba en `tests/elements-woo-module.php`).
 *
 * Opciones que existen siempre (`ALWAYS`): se crean en `admin_init` con WooCommerce activo, y el front sólo las lee si
 * están en `wp_load_alloptions()` (patrón de `Page_Visits::admin_init()`). Otros frentes usan `ensure()` y `get()`.
 */
final class Flags {
	const FRAGMENTS = 'digitalisimo_elements_woo_fragments';

	/** Opción de las muestras por atributo (frente D, V.14): existe siempre, vacía. */
	const SWATCHES = 'digitalisimo_elements_swatches';

	/** Opciones que existen siempre, con su valor inicial. */
	const ALWAYS = array(
		self::FRAGMENTS => 0,
		self::SWATCHES  => array(),
	);

	/** Cadenas del JSON de `_elementor_data` que marcan un uso de los fragmentos. */
	const FRAGMENT_NEEDLES = array(
		'"widgetType":"digitalisimo-woo-shipping-bar"',
		'"digitalisimo_cart_bar":"yes"',
		'"digitalisimo_cart_coupon":"yes"',
	);

	/** @var bool */
	private static $initialized = false;

	/** @var array<int,bool> ¿Usaba el documento los fragmentos antes de guardarse? */
	private static $before = array();

	/** @var int Guardados del editor en curso (los ganchos de metas los dejan a `after_save`). */
	private static $saving = 0;

	/** @var array<int,bool> Documentos que se borran y usaban los fragmentos. */
	private static $deleting = array();

	/** Con WooCommerce: engancha el recálculo y la creación de las opciones. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'elementor/document/before_save', array( __CLASS__, 'before_save' ), 10, 2 );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'after_save' ), 10, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'before_delete' ), 10, 1 );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted' ), 10, 1 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 10, 4 );
		add_action( 'digitalisimo_elements_kit_imported', array( __CLASS__, 'refresh' ), 10, 0 );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Lectura sin consultas.

	/** ¿Algún documento publicado usa la barra o el cupón? Sólo lee `alloptions`. */
	public static function uses_fragments(): bool {
		return 1 === (int) self::get( self::FRAGMENTS, 0 );
	}

	/**
	 * Valor de una opción con carga automática, leído sólo de `wp_load_alloptions()`: si no está, `$default` y ninguna
	 * consulta.
	 *
	 * @param mixed $default Lo que se devuelve si la opción no está entre las de carga automática.
	 * @return mixed
	 */
	public static function get( string $option, $default = null ) {
		if ( ! function_exists( 'wp_load_alloptions' ) ) {
			return $default;
		}
		$all = wp_load_alloptions();
		if ( ! is_array( $all ) || ! array_key_exists( $option, $all ) ) {
			return $default;
		}
		return function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $all[ $option ] ) : $all[ $option ];
	}

	/**
	 * Crea la opción con carga automática si falta, o le pone la carga automática si existe sin ella.
	 *
	 * @param mixed $initial Valor inicial.
	 * @return bool Si hubo que crearla.
	 */
	public static function ensure( string $option, $initial ): bool {
		$all = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
		if ( is_array( $all ) && array_key_exists( $option, $all ) ) {
			return false;
		}
		if ( false === get_option( $option, false ) ) {
			add_option( $option, $initial, '', true );
			return true;
		}
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( $option, true );
		}
		return false;
	}

	/** `admin_init`: las opciones de `ALWAYS` existen; la bandera nueva se calcula una vez. */
	public static function admin_init(): void {
		foreach ( self::ALWAYS as $option => $initial ) {
			if ( self::ensure( $option, $initial ) && self::FRAGMENTS === $option ) {
				self::refresh();
			}
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Recálculo.

	/** ¿Nombra este JSON crudo de elementos la barra o el cupón? */
	public static function contains( $raw ): bool {
		if ( is_array( $raw ) ) {
			$raw = function_exists( 'wp_json_encode' ) ? wp_json_encode( $raw ) : json_encode( $raw );
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return false;
		}
		foreach ( self::FRAGMENT_NEEDLES as $needle ) {
			if ( false !== strpos( $raw, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/** Recalcula la bandera con una consulta (`LIMIT 1`) sobre los documentos publicados. */
	public static function refresh(): bool {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return false;
		}
		$likes = array();
		$args  = array();
		foreach ( self::FRAGMENT_NEEDLES as $needle ) {
			$likes[] = 'pm.meta_value LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		$sql = "SELECT 1 FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status = 'publish' WHERE pm.meta_key = '_elementor_data' AND ( " . implode( ' OR ', $likes ) . ' ) LIMIT 1';
		$on  = (bool) $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		self::set( $on );
		return $on;
	}

	/** Fija la bandera (con carga automática; sin escritura si no cambia). */
	public static function set( bool $on ): void {
		$value = $on ? 1 : 0;
		if ( (int) self::get( self::FRAGMENTS, -1 ) === $value ) {
			return;
		}
		update_option( self::FRAGMENTS, $value, true );
	}

	/**
	 * `elementor/document/before_save`: anota si el documento usaba los fragmentos (meta en caché: el documento ya se
	 * cargó) y que hay un guardado en curso.
	 *
	 * @param object $document Documento.
	 */
	public static function before_save( $document, $data = null ): void {
		++self::$saving;
		$id = self::document_id( $document );
		if ( $id > 0 && self::uses_fragments() && self::counts( $id ) ) {
			self::$before[ $id ] = self::contains( get_post_meta( $id, '_elementor_data', true ) );
		}
	}

	/**
	 * `elementor/document/after_save`.
	 *
	 * @param object $document Documento guardado.
	 * @param mixed  $data     `{ elements, settings }` guardados.
	 */
	public static function after_save( $document, $data = null ): void {
		self::$saving = max( 0, self::$saving - 1 );
		$id           = self::document_id( $document );
		$was          = ! empty( self::$before[ $id ] );
		unset( self::$before[ $id ] );
		if ( $id <= 0 || ( is_object( $document ) && method_exists( $document, 'is_autosave' ) && $document->is_autosave() ) || ! self::counts( $id ) ) {
			return;
		}
		$elements = is_array( $data ) && isset( $data['elements'] ) ? $data['elements'] : get_post_meta( $id, '_elementor_data', true );
		if ( self::published( $id ) && self::contains( $elements ) ) {
			self::set( true );
			return;
		}
		if ( $was && self::uses_fragments() ) {
			self::refresh();
		}
	}

	/**
	 * `transition_post_status`: sólo al entrar en `publish` o al salir de él.
	 *
	 * @param string $new  Estado nuevo.
	 * @param string $old  Estado anterior.
	 * @param object $post Entrada.
	 */
	public static function on_status( $new, $old, $post ): void {
		if ( ( 'publish' === $new ) === ( 'publish' === $old ) || ! is_object( $post ) || empty( $post->ID ) ) {
			return;
		}
		$id = (int) $post->ID;
		if ( ! self::counts( $id ) || ! self::contains( get_post_meta( $id, '_elementor_data', true ) ) ) {
			return;
		}
		if ( 'publish' === $new ) {
			self::set( true );
		} elseif ( self::uses_fragments() ) {
			self::refresh();
		}
	}

	/** `before_delete_post`: anota si el documento borrado usaba los fragmentos (las metas aún existen). */
	public static function before_delete( $post_id ): void {
		$id = (int) $post_id;
		if ( $id > 0 && self::uses_fragments() && self::counts( $id ) && self::contains( get_post_meta( $id, '_elementor_data', true ) ) ) {
			self::$deleting[ $id ] = true;
		}
	}

	/** `deleted_post`: recalcula sólo si el documento borrado usaba los fragmentos. */
	public static function on_deleted( $post_id ): void {
		$id = (int) $post_id;
		if ( empty( self::$deleting[ $id ] ) ) {
			return;
		}
		unset( self::$deleting[ $id ] );
		self::refresh();
	}

	/**
	 * `added_post_meta`/`updated_post_meta` de `_elementor_data` fuera de un guardado del editor (Migración).
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
		$id = (int) $object_id;
		if ( $id <= 0 || ! self::counts( $id ) ) {
			return;
		}
		if ( self::published( $id ) && self::contains( $value ) ) {
			self::set( true );
		} elseif ( self::uses_fragments() ) {
			self::refresh();
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Internos.

	/** Documento del constructor que cuenta: ni revisión ni autoguardado, con el constructor o de la biblioteca. */
	public static function counts( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $id ) ) {
			return false;
		}
		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $id ) ) {
			return false;
		}
		$type = function_exists( 'get_post_type' ) ? (string) get_post_type( $id ) : '';
		if ( 'elementor_library' === $type ) {
			return true;
		}
		// Sólo los tipos donde el constructor está activo (el núcleo les da el soporte `elementor` en `init`): un pedido o
		// un producto sin constructor no leen ninguna meta.
		if ( '' === $type || 'revision' === $type || ! function_exists( 'post_type_supports' ) || ! post_type_supports( $type, 'elementor' ) ) {
			return false;
		}
		return 'builder' === get_post_meta( $id, '_elementor_edit_mode', true );
	}

	private static function published( int $id ): bool {
		return function_exists( 'get_post_status' ) && 'publish' === get_post_status( $id );
	}

	private static function document_id( $document ): int {
		if ( ! is_object( $document ) ) {
			return 0;
		}
		if ( method_exists( $document, 'get_id' ) ) {
			return (int) $document->get_id();
		}
		return method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$before      = array();
		self::$saving      = 0;
		self::$deleting    = array();
		self::$initialized = false;
	}
}
