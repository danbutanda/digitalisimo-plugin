<?php
namespace Digitalisimo\Elements\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Lo que los filtros ampliados necesitan en el editor (F7b, V.5 y C2-18).
 *
 * - **Script** `filters-editor.js` (sólo en el editor): rellena «Loop Grid» con los Loop Grid de este documento (los de
 *   una plantilla incrustada son de otro documento y el servidor los descartaría) y la lista de claves de campo.
 * - **Acción AJAX de sólo lectura** `digitalisimo_filters_meta_keys`, registrada con `elementor/ajax/register_actions`:
 *   exige `edit_post` sobre el documento del editor; sólo un tipo de contenido público; muestra las claves de las 100
 *   entradas publicadas más recientes de ese tipo (dos consultas acotadas, nunca un `SELECT DISTINCT` sobre la tabla
 *   entera), como mucho 200, sin las protegidas (`_*`, `is_protected_meta()`) ni las de la lista negra de V.8; en un
 *   transitorio de una hora por tipo.
 */
final class Editor {
	const ACTION    = 'digitalisimo_filters_meta_keys';
	const HANDLE    = 'digitalisimo-filters-editor';
	const SCRIPT    = 'modules/digitalisimo-filters/assets/filters-editor.js';
	const TRANSIENT = 'digi_filters_keys_';
	const SAMPLE    = 100;
	const LIMIT     = 200;

	/** @var bool */
	private static $initialized = false;

	/** Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/ajax/register_actions', array( __CLASS__, 'register_ajax' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'script' ) );
	}

	/**
	 * `elementor/ajax/register_actions`.
	 *
	 * @param object $ajax Gestor AJAX del núcleo.
	 */
	public static function register_ajax( $ajax ): void {
		if ( is_object( $ajax ) && method_exists( $ajax, 'register_ajax_action' ) ) {
			$ajax->register_ajax_action( self::ACTION, array( __CLASS__, 'meta_keys' ) );
		}
	}

	/**
	 * Claves de campo de un tipo de contenido público.
	 *
	 * @param mixed $data `editor_post_id` (lo pone el núcleo) y `post_type`.
	 * @return array{keys:string[]}
	 * @throws \Exception Sin permiso (403) o con un tipo inválido (400).
	 */
	public static function meta_keys( $data ): array {
		$data = is_array( $data ) ? $data : array();
		$post = isset( $data['editor_post_id'] ) && is_numeric( $data['editor_post_id'] ) ? (int) $data['editor_post_id'] : 0;
		if ( $post <= 0 || ! current_user_can( 'edit_post', $post ) ) {
			throw new \Exception( 'Acceso denegado.', 403 );
		}
		$type  = isset( $data['post_type'] ) && is_string( $data['post_type'] ) ? sanitize_key( $data['post_type'] ) : '';
		$types = get_post_types( array( 'public' => true ) );
		if ( '' === $type || 'attachment' === $type || ! is_array( $types ) || ! in_array( $type, array_map( 'strval', $types ), true ) ) {
			throw new \Exception( 'Tipo de contenido no válido.', 400 );
		}
		$cached = get_transient( self::TRANSIENT . $type );
		if ( is_array( $cached ) ) {
			return array( 'keys' => array_values( array_filter( $cached, 'is_string' ) ) );
		}
		global $wpdb;
		$ids  = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY ID DESC LIMIT %d", $type, self::SAMPLE ) ) );
		$ids  = array_values( array_filter( $ids ) );
		$keys = array();
		if ( $ids ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs enteros.
			$found = (array) $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id IN (" . implode( ',', $ids ) . ") AND meta_key NOT LIKE '\\_%' ORDER BY meta_key LIMIT " . (int) self::LIMIT );
			foreach ( $found as $key ) {
				if ( is_string( $key ) && '' !== Query::meta_key( $key ) ) {
					$keys[] = $key;
				}
			}
		}
		set_transient( self::TRANSIENT . $type, $keys, defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600 );
		return array( 'keys' => $keys );
	}

	/** `elementor/editor/after_enqueue_scripts`: el script del editor. */
	public static function script(): void {
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) || ! is_file( dirname( DIGITALISIMO_ELEMENTS_FILE ) . '/' . self::SCRIPT ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::HANDLE, plugins_url( self::SCRIPT, DIGITALISIMO_ELEMENTS_FILE ), array( 'elementor-editor' ), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$initialized = false;
	}
}
