<?php
namespace Digitalisimo\Elements\Navigation;

defined( 'ABSPATH' ) || exit;

/**
 * Resultados en vivo de la Búsqueda (`digitalisimo-search`, frente F de C3) por la REST del propio sitio:
 * `POST digitalisimo-elements/v1/search` con `post_id`, `widget_id`, `search_term`, `page_number` y `breakpoint` (y, en
 * el editor, `widget_model`). Devuelve `{ data, pagination }`: los resultados pintados con la plantilla de bucle del
 * widget y su paginación, igual que el punto `refresh-search` de PRO (`modules/search/data/endpoints/refresh-search.php`,
 * con su comprobación de permisos de `core/data/endpoints/refresh-base.php`; derivado de PRO Elements, GPL).
 *
 * Permisos: en el front, el documento está publicado y contiene un widget `digitalisimo-search` con ese `id` (los ajustes
 * salen siempre de la base de datos, nunca de la petición); en el editor, quien pide puede editar el documento y el
 * modelo que envía es de este widget. La petición no decide la consulta: sólo el término (texto plano, ≤ 200
 * caracteres), la página (≥ 1) y el dispositivo.
 */
final class Search_Rest {
	const REST_NAMESPACE = 'digitalisimo-elements/v1';

	const ROUTE = '/search';

	const WIDGET = 'digitalisimo-search';

	const MAX_TERM = 200;

	/** `rest_api_init`: registra la ruta. */
	public static function register(): void {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'results' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
					'args'                => array(
						'post_id'      => array(
							'type'              => 'integer',
							'required'          => true,
							'validate_callback' => static function ( $value ) {
								return is_numeric( $value ) && (int) $value > 0;
							},
						),
						'widget_id'    => array(
							'type'              => 'string',
							'required'          => true,
							'validate_callback' => array( __CLASS__, 'valid_id' ),
						),
						'search_term'  => array(
							'type'    => 'string',
							'default' => '',
						),
						'page_number'  => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'breakpoint'   => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						),
						'widget_model' => array(
							'type'     => 'object',
							'required' => false,
						),
					),
				),
			)
		);
	}

	/** URL de la ruta en el sitio actual (subdirectorios y subdominios incluidos). */
	public static function url(): string {
		return function_exists( 'rest_url' ) ? (string) rest_url( self::REST_NAMESPACE . self::ROUTE ) : '';
	}

	/** Un `id` de elemento: 7 letras o números, como el núcleo. */
	public static function valid_id( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9]{7}$/', $value );
	}

	/** ¿Puede quien pide editar el documento? (en el editor se envía el modelo del widget). */
	public static function editing( int $post_id ): bool {
		$elementor = self::elementor();
		$document  = $elementor && isset( $elementor->documents ) ? $elementor->documents->get( $post_id ) : null;
		return $document && method_exists( $document, 'is_editable_by_current_user' ) && $document->is_editable_by_current_user();
	}

	/**
	 * Permisos: el documento está publicado y tiene el widget, o quien pide puede editarlo y envía su modelo.
	 *
	 * @param \WP_REST_Request $request Petición.
	 */
	public static function permission( $request ): bool {
		$post_id = (int) $request->get_param( 'post_id' );
		$id      = (string) $request->get_param( 'widget_id' );
		if ( $post_id <= 0 || ! self::valid_id( $id ) ) {
			return false;
		}
		$model = $request->get_param( 'widget_model' );
		if ( null !== $model ) {
			return self::editing( $post_id ) && self::valid_model( $model, $id );
		}
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || ( function_exists( 'post_password_required' ) && post_password_required( $post ) ) ) {
			return false;
		}
		$widget = self::find( $post_id, $id );
		return is_array( $widget ) && 'widget' === ( $widget['elType'] ?? '' ) && self::WIDGET === ( $widget['widgetType'] ?? '' );
	}

	/** Modelo del editor de este widget: `id`, `widgetType` y `settings`. */
	public static function valid_model( $model, string $id ): bool {
		return is_array( $model ) && $id === ( $model['id'] ?? null ) && self::WIDGET === ( $model['widgetType'] ?? null ) && is_array( $model['settings'] ?? null );
	}

	/**
	 * Resultados y paginación del término pedido.
	 *
	 * @param \WP_REST_Request $request Petición.
	 * @return array{data: string, pagination: string}|\WP_Error
	 */
	public static function results( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$id      = (string) $request->get_param( 'widget_id' );
		$model   = $request->get_param( 'widget_model' );
		$data    = null !== $model && self::editing( $post_id ) && self::valid_model( $model, $id ) ? $model : self::find( $post_id, $id );
		$widget  = self::instance( $data );
		if ( ! $widget ) {
			return new \WP_Error( 'digitalisimo_search_widget', 'Widget no encontrado.', array( 'status' => 404 ) );
		}
		$term = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $request->get_param( 'search_term' ) ) : (string) $request->get_param( 'search_term' );
		$term = function_exists( 'mb_substr' ) ? mb_substr( $term, 0, self::MAX_TERM ) : substr( $term, 0, self::MAX_TERM );
		$widget->set_search_term( $term );
		$widget->set_page_number( max( 1, (int) $request->get_param( 'page_number' ) ) );
		$widget->set_breakpoint( (string) $request->get_param( 'breakpoint' ) );
		$elementor = self::elementor();
		$document  = $elementor && isset( $elementor->documents ) ? $elementor->documents->get( $post_id ) : null;
		if ( $document ) {
			$elementor->documents->switch_to_document( $document );
		}
		ob_start();
		$widget->render_results();
		$markup = (string) ob_get_clean();
		$widget->set_search_term( '' );
		ob_start();
		$widget->render_pagination();
		$pagination = (string) ob_get_clean();
		if ( $document ) {
			$elementor->documents->restore_document();
		}
		return array(
			'data'       => $markup,
			'pagination' => $pagination,
		);
	}

	/** Datos guardados del elemento `id` en el documento, o `null`. */
	public static function find( int $post_id, string $id ): ?array {
		$elementor = self::elementor();
		$document  = $elementor && isset( $elementor->documents ) ? $elementor->documents->get( $post_id ) : null;
		if ( ! $document || ! class_exists( '\Elementor\Utils' ) ) {
			return null;
		}
		$found = \Elementor\Utils::find_element_recursive( (array) $document->get_elements_data(), $id );
		return is_array( $found ) && $found ? $found : null;
	}

	/** Instancia del widget propio con esos datos, o `null` si no es una Búsqueda. */
	private static function instance( $data ) {
		$elementor = self::elementor();
		if ( ! is_array( $data ) || self::WIDGET !== ( $data['widgetType'] ?? '' ) || ! $elementor || ! isset( $elementor->elements_manager ) ) {
			return null;
		}
		$widget = $elementor->elements_manager->create_element_instance( $data );
		return $widget instanceof Widgets\Search ? $widget : null;
	}

	private static function elementor() {
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
	}
}
