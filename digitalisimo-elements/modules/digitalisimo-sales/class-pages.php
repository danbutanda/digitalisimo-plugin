<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Guarda una página del kit: siempre en borrador y por el guardado normal del documento del constructor
 * (`Document::save()`, el mismo del editor), que guarda los elementos, el lienzo, la versión y borra el CSS de la
 * página para que el siguiente render lo genere con estos elementos. Nunca publica ni consulta `publish_pages`.
 */
final class Pages {
	/**
	 * @return array|\WP_Error `id`, `status`, `edit_url` y `preview_url`.
	 */
	public static function create( string $title, array $elements ) {
		if ( ! post_type_exists( 'page' ) || ! post_type_supports( 'page', 'elementor' ) ) {
			return new \WP_Error( 'digitalisimo_sales_post_type', 'Las páginas de este sitio no se editan con el constructor (Configuración del constructor → tipos de contenido).', array( 'status' => 400 ) );
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\Elementor\User' ) || ! \Elementor\User::is_current_user_can_edit_post_type( 'page' ) ) {
			return new \WP_Error( 'digitalisimo_sales_forbidden', 'Tu usuario no puede editar páginas con el constructor.', array( 'status' => 403 ) );
		}
		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_status'  => 'draft',
				'post_type'    => 'page',
				'post_content' => '',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$post_id  = (int) $post_id;
		$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
		if ( ! $document ) {
			wp_delete_post( $post_id, true );
			return new \WP_Error( 'digitalisimo_sales_document', 'El constructor no pudo abrir la página nueva.', array( 'status' => 500 ) );
		}
		$document->set_is_built_with_elementor( true );
		try {
			$saved = $document->save(
				array(
					'elements' => $elements,
					'settings' => array( 'template' => 'elementor_canvas' ),
				)
			);
		} catch ( \Throwable $e ) {
			$saved = false;
		}
		if ( ! $saved ) {
			wp_delete_post( $post_id, true );
			return new \WP_Error( 'digitalisimo_sales_save', 'El constructor no pudo guardar la página; no se creó nada.', array( 'status' => 500 ) );
		}
		// Defensa: la página sale siempre en borrador, aunque otro código haya cambiado su estado al guardar.
		if ( 'draft' !== get_post_status( $post_id ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);
		}
		$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
		return array(
			'id'          => $post_id,
			'status'      => (string) get_post_status( $post_id ),
			'edit_url'    => $document ? (string) $document->get_edit_url() : '',
			'preview_url' => $document ? (string) remove_query_arg( 'preview_nonce', $document->get_wp_preview_url() ) : (string) get_preview_post_link( $post_id ),
		);
	}
}
