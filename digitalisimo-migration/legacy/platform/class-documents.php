<?php
namespace Digitalisimo\Elements\Legacy\Platform;

defined( 'ABSPATH' ) || exit;

/**
 * Documentos que crea la migración (plantillas, Loop Items, fragmentos, paneles…), siempre en borrador y con su
 * procedencia (V.14 C6, V.5.5).
 *
 * - `create()` usa el gestor de documentos del constructor si el tipo está registrado y hay un usuario que puede
 *   editar; si no (WP-CLI sin --user, WP-Cron), wp_insert_post() con las metas y el término del tipo, como
 *   Native::loop_item(). En los dos casos cada meta va con wp_slash() (cadenas y arrays: `meta_input` y
 *   update_post_meta() quitan las barras) y la huella de `_elementor_data` releída debe coincidir con la del origen.
 *   Si no coincide, devuelve 0 y deja el ID del borrador en `$last_failed` (para registrarlo como `failed`).
 * - La procedencia va en `_digitalisimo_import_origin` = `{v:1, origin:"migration", part, source, hash}`.
 */
final class Documents {
	const ORIGIN_META = '_digitalisimo_import_origin';

	/** @var int borrador creado cuya huella no coincidió (0 si el último create() fue bien). */
	public static $last_failed = 0;

	/**
	 * Crea un documento en borrador.
	 *
	 * @param string $type   Tipo de plantilla del constructor (`header`, `footer`, `single-post`, `loop-item`, `section`…).
	 * @param array  $post   Campos de la entrada (`post_title`, `post_type`…; `post_status` se fuerza a `draft`).
	 * @param array  $meta   Metas (`_elementor_data` en texto JSON, `_elementor_page_settings`, `_elementor_conditions`…).
	 * @param array  $origin `part` (tipo del registro) y `source` (ID o huella de origen).
	 * @return int ID, o 0 si falla.
	 */
	public static function create( string $type, array $post, array $meta, array $origin ): int {
		self::$last_failed = 0;
		$context           = '\\Digitalisimo\\Elements\\Legacy\\Context';
		if ( class_exists( $context, false ) && $context::pure() ) {
			return 0;
		}
		$type = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $type ) );
		if ( '' === $type ) {
			return 0;
		}
		$data = isset( $meta['_elementor_data'] ) ? $meta['_elementor_data'] : null;
		if ( is_array( $data ) ) {
			$data                    = (string) wp_json_encode( $data );
			$meta['_elementor_data'] = $data;
		}
		$expected                         = is_string( $data ) ? md5( $data ) : '';
		$meta['_elementor_edit_mode']     = 'builder';
		$meta['_elementor_template_type'] = $type;
		// Como la cabecera y el pie de Hello y los kits: un array (K ignora una procedencia sin su clave de kit).
		$meta[ self::ORIGIN_META ]        = (
			array(
				'v'      => 1,
				'origin' => 'migration',
				'part'   => preg_replace( '/[^a-z0-9_]/', '', (string) ( $origin['part'] ?? '' ) ),
				'source' => (string) ( $origin['source'] ?? '' ),
				'hash'   => $expected,
			)
		);
		$post['post_status'] = 'draft';
		$post['post_type']   = (string) ( $post['post_type'] ?? 'elementor_library' );
		$post['post_title']  = (string) ( $post['post_title'] ?? '' );
		unset( $post['ID'], $post['meta_input'] );

		$id = self::through_manager( $type, $post, $meta );
		if ( ! $id ) {
			$id = (int) wp_insert_post( wp_slash( $post ) + array( 'meta_input' => array() ) );
			if ( ! $id || ( function_exists( 'is_wp_error' ) && is_wp_error( $id ) ) ) {
				return 0;
			}
			foreach ( $meta as $key => $value ) {
				update_post_meta( $id, (string) $key, wp_slash( $value ) );
			}
			if ( 'elementor_library' === $post['post_type'] && function_exists( 'wp_set_object_terms' ) ) {
				wp_set_object_terms( $id, $type, 'elementor_library_type' );
			}
		}
		// El constructor puede haberla publicado o cambiado de estado al guardarla: siempre borrador.
		$current = get_post( $id );
		if ( $current && 'draft' !== $current->post_status ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		}
		if ( '' !== $expected && md5( (string) get_post_meta( $id, '_elementor_data', true ) ) !== $expected ) {
			self::$last_failed = $id;
			return 0;
		}
		return $id;
	}

	/** Con el gestor de documentos del constructor (tipo registrado y usuario que puede editar); 0 si no se pudo. */
	private static function through_manager( string $type, array $post, array $meta ): int {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->documents ) ) {
			return 0;
		}
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) ) {
			return 0;
		}
		$manager = \Elementor\Plugin::$instance->documents;
		if ( ! method_exists( $manager, 'get_document_type' ) || ! $manager->get_document_type( $type, false ) ) {
			return 0;
		}
		try {
			$document = $manager->create( $type, wp_slash( $post ), wp_slash( $meta ) );
		} catch ( \Throwable $e ) {
			return 0;
		}
		if ( ! is_object( $document ) || ( function_exists( 'is_wp_error' ) && is_wp_error( $document ) ) || ! method_exists( $document, 'get_main_id' ) ) {
			return 0;
		}
		return (int) $document->get_main_id();
	}

	/** Huella de `_elementor_data` (md5 del texto guardado). */
	public static function hash( int $id ): string {
		$raw = get_post_meta( $id, '_elementor_data', true );
		return is_string( $raw ) && '' !== $raw ? md5( $raw ) : '';
	}

	/** Procedencia de un documento creado por la migración, o null (también si la creó otro, por ejemplo un kit). */
	public static function origin( int $id ): ?array {
		$raw    = get_post_meta( $id, self::ORIGIN_META, true );
		$origin = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
		return is_array( $origin ) && 'migration' === ( $origin['origin'] ?? '' ) ? $origin : null;
	}

	/** ¿Sigue igual que cuando la migración lo creó o lo convirtió? (`$hash` del registro.) */
	public static function unchanged( int $id, string $hash ): bool {
		return '' !== $hash && self::hash( $id ) === $hash;
	}
}
