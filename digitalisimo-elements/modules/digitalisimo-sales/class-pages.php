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

	/** Meta propia de las páginas que crea el kit: ['blog' => ID del sitio, 'template' => id de la plantilla]. */
	const META = '_digitalisimo_sales_kit';

	/**
	 * ¿Se puede regenerar esta página? Sólo un borrador que creó el kit (su meta propia), en este sitio de la red,
	 * editable por el usuario; nunca una página publicada, programada, privada o pendiente de revisión.
	 *
	 * @return true|\WP_Error
	 */
	public static function regenerable( int $post_id ) {
		$post = $post_id > 0 && function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( ! $post || 'page' !== ( $post->post_type ?? '' ) ) {
			return new \WP_Error( 'digitalisimo_sales_post_id', sprintf( 'post_id %d no es una página de este sitio.', $post_id ), array( 'status' => 404 ) );
		}
		$meta = get_post_meta( $post_id, self::META, true );
		$blog = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
		$kit  = is_array( $meta ) && (int) ( $meta['blog'] ?? 0 ) === $blog;
		/**
		 * Otro borrador que se puede regenerar aunque no lleve la marca del kit (las páginas creadas antes de que el kit
		 * la guardara, o las que creó el Asistente IA de IA Tools para esta persona). Sólo se consulta para un borrador
		 * de este sitio; publicar, programar o una página ajena nunca pasan (lo de abajo se comprueba igual).
		 */
		if ( ! $kit && ! is_array( $meta ) && 'draft' === ( $post->post_status ?? '' ) && function_exists( 'apply_filters' ) ) {
			$kit = true === apply_filters( 'digitalisimo_sales_regenerable', false, $post_id );
		}
		if ( ! $kit ) {
			return new \WP_Error( 'digitalisimo_sales_post_id', sprintf( 'La página %d no es un borrador del kit de ventas de este sitio: create-page sólo regenera los borradores que creó; quita post_id para crear una nueva.', $post_id ), array( 'status' => 400 ) );
		}
		if ( 'draft' !== ( $post->post_status ?? '' ) ) {
			return new \WP_Error( 'digitalisimo_sales_post_id', sprintf( 'La página %d está %s, no en borrador: create-page nunca toca una página publicada, programada, privada o en revisión; quita post_id para crear un borrador nuevo.', $post_id, (string) $post->post_status ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'digitalisimo_sales_forbidden', sprintf( 'Tu usuario no puede editar la página %d.', $post_id ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Crea el borrador o, con `$post_id`, lo regenera en el mismo borrador del kit (`regenerable()`): mismo ID, nuevo
	 * título y elementos, sigue en borrador.
	 *
	 * @param string $layout `site` (por defecto: la plantilla de página con la cabecera y el pie del sitio) o `blank` (el
	 *                       lienzo vacío).
	 * @return array|\WP_Error `id`, `status`, `edit_url`, `preview_url`, `regenerated` y `layout`.
	 */
	public static function create( string $title, array $elements, int $post_id = 0, string $template = '', string $layout = 'site' ) {
		if ( ! post_type_exists( 'page' ) || ! post_type_supports( 'page', 'elementor' ) ) {
			return new \WP_Error( 'digitalisimo_sales_post_type', 'Las páginas de este sitio no se editan con el constructor (Configuración del constructor → tipos de contenido).', array( 'status' => 400 ) );
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\Elementor\User' ) || ! \Elementor\User::is_current_user_can_edit_post_type( 'page' ) ) {
			return new \WP_Error( 'digitalisimo_sales_forbidden', 'Tu usuario no puede editar páginas con el constructor.', array( 'status' => 403 ) );
		}
		$regenerate = $post_id > 0;
		if ( $regenerate ) {
			$ok = self::regenerable( $post_id );
			if ( true !== $ok ) {
				return $ok;
			}
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_title'  => $title,
					'post_status' => 'draft',
				)
			);
			// Un borrador que se aceptó por el filtro queda ya como del kit: la próxima vez se regenera sin él. Uno del kit
			// rehecho con otra plantilla (p. ej. de producto a oferta de tienda) anota la nueva.
			$mark = get_post_meta( $post_id, self::META, true );
			if ( is_array( $mark ) && '' !== $template && ( $mark['template'] ?? '' ) !== $template ) {
				$mark['template'] = $template;
				update_post_meta( $post_id, self::META, $mark );
			}
			if ( ! is_array( $mark ) ) {
				update_post_meta(
					$post_id,
					self::META,
					array(
						'blog'     => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1,
						'template' => $template,
					)
				);
			}
		} else {
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
			update_post_meta(
				(int) $post_id,
				self::META,
				array(
					'blog'     => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1,
					'template' => $template,
				)
			);
		}
		$post_id  = (int) $post_id;
		$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
		if ( ! $document ) {
			if ( ! $regenerate ) {
				wp_delete_post( $post_id, true );
			}
			return new \WP_Error( 'digitalisimo_sales_document', 'El constructor no pudo abrir la página nueva.', array( 'status' => 500 ) );
		}
		$document->set_is_built_with_elementor( true );
		// Con la cabecera y el pie del sitio, para que la landing se vea como parte del sitio (caso plomerito 3); el lienzo
		// vacío sólo si se pide (`blank`).
		$canvas = array( 'template' => 'blank' === $layout ? 'elementor_canvas' : 'elementor_header_footer' );
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
			if ( $regenerate ) {
				return new \WP_Error( 'digitalisimo_sales_save', 'El constructor no pudo guardar la página; el borrador se queda como estaba.', array( 'status' => 500 ) );
			}
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
		// La huella de lo que dejó el kit: quien lo rehace después (el Asistente IA) sabe si alguien lo retocó a mano.
		$mark = function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, self::META, true ) : null;
		if ( is_array( $mark ) ) {
			$mark['hash'] = md5( (string) get_post_meta( $post_id, '_elementor_data', true ) );
			update_post_meta( $post_id, self::META, $mark );
		}
		$document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
		return array(
			'id'          => $post_id,
			'status'      => (string) get_post_status( $post_id ),
			'edit_url'    => $document ? (string) $document->get_edit_url() : '',
			'preview_url' => $document ? (string) remove_query_arg( 'preview_nonce', $document->get_wp_preview_url() ) : (string) get_preview_post_link( $post_id ),
			'regenerated' => $regenerate,
			'layout'      => $canvas['template'],
		);
	}
}
