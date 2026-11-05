<?php
namespace Digitalisimo\Elements\F7b\Overrides;

defined( 'ABSPATH' ) || exit;

/**
 * Acciones del editor de las sobrescrituras, de sólo lectura (F7b, frente E; V.2 y C2-18).
 *
 * Se registran con `elementor/ajax/register_actions`: el núcleo comprueba el nonce
 * (`elementor/core/common/modules/ajax/module.php:130-133`) y pone `editor_post_id` en los datos
 * (`:135-171`). Cada acción exige `current_user_can( 'edit_post', editor_post_id )`; sin permiso lanza una excepción
 * con código 403, que el núcleo devuelve como `code` de la respuesta. Nada de la petición decide claves, tipos ni
 * topes: sólo el ID de la plantilla.
 *
 * - `digitalisimo_f7b_slots` `{ template_id }`: claves de una plantilla de la biblioteca (`elementor_library`)
 *   publicada o que el usuario puede editar, leídas de su `_elementor_data` guardado (`Apply::slots()`).
 * - `digitalisimo_f7b_slot_uses` `{}`: con el editor abierto en una plantilla, cuántas instancias del Plantilla
 *   sobrescriben cada clave, leyendo los documentos que la usan (`F7b\Usages::of( $plantilla, 't' )`, meta de usos
 *   de V.6; como mucho `MAX_DOCUMENTS`). Sin `Usages` devuelve `ready: false` y el editor no avisa.
 */
final class Ajax {
	const SLOTS = 'digitalisimo_f7b_slots';
	const USES  = 'digitalisimo_f7b_slot_uses';

	const MAX_DOCUMENTS = 100;

	const USAGES = 'Digitalisimo\\Elements\\F7b\\Usages';

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/ajax/register_actions', array( __CLASS__, 'register' ) );
	}

	/** @param mixed $ajax Módulo AJAX del núcleo. */
	public static function register( $ajax ): void {
		if ( ! is_object( $ajax ) || ! method_exists( $ajax, 'register_ajax_action' ) ) {
			return;
		}
		$ajax->register_ajax_action( self::SLOTS, array( __CLASS__, 'slots' ) );
		$ajax->register_ajax_action( self::USES, array( __CLASS__, 'uses' ) );
	}

	/**
	 * @param mixed $data `{ template_id, editor_post_id }`.
	 * @throws \Exception Sin permiso sobre el documento o la plantilla.
	 */
	public static function slots( $data ): array {
		$data     = is_array( $data ) ? $data : array();
		$editor   = self::editor( $data );
		$template = isset( $data['template_id'] ) && is_scalar( $data['template_id'] ) ? absint( $data['template_id'] ) : 0;
		$post     = $template ? get_post( $template ) : null;
		$allowed  = $post && Render::LIBRARY === $post->post_type
			&& ( 'publish' === $post->post_status || current_user_can( 'edit_post', $template ) );
		if ( ! $allowed ) {
			throw new \Exception( esc_html__( 'No puedes leer esa plantilla.', 'pro-elements' ), 403 );
		}
		return array(
			'editor'   => $editor,
			'template' => $template,
			'slots'    => Apply::slots( self::elements( $template ) ),
		);
	}

	/**
	 * @param mixed $data `{ editor_post_id }`.
	 * @throws \Exception Sin permiso sobre el documento.
	 */
	public static function uses( $data ): array {
		$data     = is_array( $data ) ? $data : array();
		$template = self::editor( $data );
		$usages   = self::USAGES;
		if ( Render::LIBRARY !== get_post_type( $template ) || ! class_exists( $usages ) || ! method_exists( $usages, 'of' ) ) {
			return array(
				'ready' => false,
				'slots' => new \stdClass(),
			);
		}
		$kind  = defined( $usages . '::KIND_TEMPLATE' ) ? constant( $usages . '::KIND_TEMPLATE' ) : 't';
		$found = call_user_func( array( $usages, 'of' ), $template, $kind );
		$ids   = array();
		foreach ( array( 'posts', 'drafts' ) as $group ) {
			foreach ( isset( $found[ $group ] ) && is_array( $found[ $group ] ) ? $found[ $group ] : array() as $id ) {
				$id = absint( $id );
				if ( $id && $id !== $template ) {
					$ids[ $id ] = $id;
				}
			}
		}
		$ids    = array_slice( array_values( $ids ), 0, self::MAX_DOCUMENTS );
		$counts = array();
		foreach ( $ids as $id ) {
			Apply::count_instances( self::elements( $id ), $template, $counts );
		}
		return array(
			'ready'     => true,
			'documents' => count( $ids ),
			'slots'     => $counts ? $counts : new \stdClass(),
		);
	}

	/**
	 * Documento del editor con permiso de edición, o excepción 403.
	 *
	 * @throws \Exception Sin documento o sin permiso.
	 */
	private static function editor( array $data ): int {
		$id = isset( $data['editor_post_id'] ) && is_scalar( $data['editor_post_id'] ) ? absint( $data['editor_post_id'] ) : 0;
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			throw new \Exception( esc_html__( 'No tienes permiso para editar este documento.', 'pro-elements' ), 403 );
		}
		return $id;
	}

	/** Datos de elementos guardados de un documento. */
	private static function elements( int $id ): array {
		$raw = get_post_meta( $id, '_elementor_data', true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = json_decode( $raw, true );
		}
		return is_array( $raw ) ? $raw : array();
	}
}
