<?php
namespace Digitalisimo\Elements\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Guarda una página del kit: siempre en borrador y por el guardado normal del documento del constructor
 * (`Document::update_settings()` para el lienzo y `Document::save()`, el mismo del editor, para los elementos, la
 * versión y el CSS de la página, que borra para que el siguiente render lo genere con estos elementos). Nunca publica
 * ni consulta `publish_pages`. Guardar como editora no deja avisos de PHP del saneador del núcleo (ver `with_types()`).
 */
final class Pages {
	/**
	 * Antes del saneador del núcleo (`content-sanitizer`, prioridad 10, para quien no tiene `manage_options`), que lee
	 * `widgetType` de cada elemento, también de las secciones, columnas y contenedores, y deja un aviso de PHP por cada
	 * uno: les pone un `widgetType` vacío (no es `heading`, así que el saneador no los toca). `without_types()` lo quita
	 * después (y el núcleo no lo guardaría: guarda los datos de cada elemento sin claves de más). Sólo durante el
	 * guardado de `create()`.
	 */
	public static function with_types( $data ) {
		if ( is_array( $data ) && isset( $data['elements'] ) && is_array( $data['elements'] ) ) {
			$data['elements'] = self::types( $data['elements'], true );
		}
		return $data;
	}

	public static function without_types( $data ) {
		if ( is_array( $data ) && isset( $data['elements'] ) && is_array( $data['elements'] ) ) {
			$data['elements'] = self::types( $data['elements'], false );
		}
		return $data;
	}

	private static function types( array $elements, bool $add ): array {
		foreach ( $elements as $i => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( 'widget' !== ( $element['elType'] ?? '' ) ) {
				if ( $add && ! array_key_exists( 'widgetType', $element ) ) {
					$element['widgetType'] = '';
				} elseif ( ! $add && '' === ( $element['widgetType'] ?? null ) ) {
					unset( $element['widgetType'] );
				}
			}
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::types( $element['elements'], $add );
			}
			$elements[ $i ] = $element;
		}
		return $elements;
	}

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
		$canvas = array( 'template' => 'elementor_canvas' );
		$data   = array( 'elements' => $elements );
		add_filter( 'elementor/document/save/data', array( self::class, 'with_types' ), 9 );
		add_filter( 'elementor/document/save/data', array( self::class, 'without_types' ), 11 );
		try {
			// El lienzo va aparte, por los ajustes del documento: dentro de `save()`, el saneador del núcleo (para quien no
			// tiene `manage_options`) recorre también los ajustes como si fueran elementos y deja un aviso de PHP
			// (`includes/db.php`, `foreach` sobre una cadena) que ensuciaría la respuesta del MCP.
			if ( method_exists( $document, 'update_settings' ) ) {
				$document->update_settings( $canvas );
			} else {
				$data['settings'] = $canvas;
			}
			$saved = $document->save( $data );
		} catch ( \Throwable $e ) {
			$saved = false;
		} finally {
			remove_filter( 'elementor/document/save/data', array( self::class, 'with_types' ), 9 );
			remove_filter( 'elementor/document/save/data', array( self::class, 'without_types' ), 11 );
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
