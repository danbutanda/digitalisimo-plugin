<?php
namespace Digitalisimo\Elements\F7b\Queries;

defined( 'ABSPATH' ) || exit;

/**
 * Control «Consulta guardada» y acciones del editor (V.4, D8, C2-17, C2-18, C2-26).
 *
 * - **Control** `digitalisimo_saved_query` (select, sin `default`: «Ninguna» es el valor vacío) al principio de la
 *   sección `section_query` de Posts, Portfolio, Loop Grid y Loop Carousel (`elementor/element/<widget>/section_query/
 *   before_section_end`, con inyección delante del origen del grupo). En el Loop Grid y el Loop Carousel sólo con el
 *   skin de entradas. `style_transfer => false`: «Pegar estilo», «Aplicar a todos» y los presets nunca copian el
 *   vínculo (C2-16). Se guarda como cadena (`"digitalisimo_saved_query":"123"`, la aguja de `Flags`).
 * - Debajo, un resumen de sólo lectura que llena `queries-editor.js` («Entradas · Noticias · 6 · contexto actual») con
 *   el enlace «Editar en Consultas (N usos)», y el botón «Desvincular».
 * - Los controles del grupo de PRO (origen, incluir, excluir, fecha, orden) quedan con la condición
 *   `digitalisimo_saved_query => ''`: con una consulta elegida no se ven ni se aplican (`get_settings_for_display()`
 *   los da como `null`), pero sus valores se conservan (D24: al volver a 4.3.0.168, o al elegir «Ninguna», el widget
 *   usa los suyos). `posts_per_page`, `ignore_sticky_posts` y el ID de consulta siguen siendo del widget (C1-5).
 * - **AJAX del editor** (`elementor/ajax/register_actions`; el nonce lo comprueba el núcleo), las dos con
 *   `current_user_can( 'edit_post', editor_post_id )` y sólo de lectura (C2-18):
 *   - `digitalisimo_f7b_queries`: la lista con resumen, usos y enlace de edición;
 *   - `digitalisimo_f7b_query_settings` `{ id }`: los ajustes de una consulta publicada en la forma del grupo (C2-17:
 *     el cliente los copia con `$e.run( 'document/elements/settings' )`, con su «Deshacer»).
 * - El script `assets/js/queries-editor.js` se encola sólo en el editor.
 */
final class Controls {
	const CONTROL = 'digitalisimo_saved_query';
	const SUMMARY = 'digitalisimo_saved_query_summary';
	const UNLINK  = 'digitalisimo_saved_query_unlink';
	const EVENT   = 'digitalisimo:queries:unlink';

	const AJAX_LIST     = 'digitalisimo_f7b_queries';
	const AJAX_SETTINGS = 'digitalisimo_f7b_query_settings';

	const HANDLE = 'digitalisimo-f7b-queries-editor';
	const OBJECT = 'digitalisimoSavedQueries';
	const SCRIPT = 'assets/js/queries-editor.js';

	/** Campos del grupo de PRO (`Group_Control_Query` y `Group_Control_Related`) que la consulta guardada sustituye. */
	const GROUP_FIELDS = array(
		'post_type',
		'query_args',
		'query_include',
		'query_exclude',
		'posts_ids',
		'include',
		'include_term_ids',
		'related_taxonomies',
		'include_authors',
		'exclude',
		'exclude_ids',
		'exclude_term_ids',
		'exclude_authors',
		'avoid_duplicates',
		'offset',
		'related_fallback',
		'fallback_ids',
		'select_date',
		'date_before',
		'date_after',
		'orderby',
		'order',
	);

	/** @var bool */
	private static $initialized = false;

	/** @var bool */
	private static $ajax = false;

	/** Controles en los cuatro widgets. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		foreach ( array_keys( Runtime::WIDGETS ) as $widget ) {
			add_action( 'elementor/element/' . $widget . '/section_query/before_section_end', array( __CLASS__, 'register' ), 10, 2 );
		}
	}

	/** Acciones AJAX y script del editor (en la administración). Idempotente. */
	public static function init_editor(): void {
		if ( self::$ajax ) {
			return;
		}
		self::$ajax = true;
		add_action( 'elementor/ajax/register_actions', array( __CLASS__, 'register_ajax' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Añade el select, el resumen y «Desvincular», y condiciona los controles del grupo.
	 *
	 * @param object $element Widget.
	 */
	public static function register( $element ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || ! isset( Runtime::WIDGETS[ $element->get_name() ] ) ) {
			return;
		}
		$prefix = Runtime::WIDGETS[ $element->get_name() ] . '_';
		$first  = $prefix . 'post_type';
		if ( ! $element->get_controls( $first ) || $element->get_controls( self::CONTROL ) ) {
			return;
		}
		$loop      = 'post_query' === Runtime::WIDGETS[ $element->get_name() ];
		$condition = $loop ? array( '_skin' => 'post' ) : array();
		$linked    = $condition + array( self::CONTROL . '!' => '' );

		$element->start_injection(
			array(
				'type' => 'control',
				'at'   => 'before',
				'of'   => $first,
			)
		);
		$element->add_control(
			self::CONTROL,
			array(
				'label'          => __( 'Consulta guardada', 'pro-elements' ),
				'type'           => 'select',
				'options'        => self::options(),
				'label_block'    => true,
				'description'    => __( 'Usa una consulta de Plantillas → Consultas. Los ajustes de consulta de este widget se conservan: vuelven al elegir «Ninguna».', 'pro-elements' ),
				'condition'      => $condition,
				'style_transfer' => false,
			)
		);
		$element->add_control(
			self::SUMMARY,
			array(
				'type'            => 'raw_html',
				'raw'             => '<div class="digitalisimo-saved-query-summary" data-digi-sq-summary aria-live="polite"></div>',
				'content_classes' => 'elementor-descriptor',
				'condition'       => $linked,
				'style_transfer'  => false,
			)
		);
		$element->add_control(
			self::UNLINK,
			array(
				'label'          => __( 'Copiar sus ajustes a este widget', 'pro-elements' ),
				'type'           => 'button',
				'text'           => __( 'Desvincular', 'pro-elements' ),
				'event'          => self::EVENT,
				'separator'      => 'after',
				'condition'      => $linked,
				'style_transfer' => false,
			)
		);
		$element->end_injection();

		$ids = array_map(
			static function ( $field ) use ( $prefix ) {
				return $prefix . $field;
			},
			self::GROUP_FIELDS
		);
		// La nota de PRO sobre «Consulta actual» en plantillas de archivo (`Query_Note_Trait`) tampoco aplica.
		$ids[] = 'archive_query_note';
		foreach ( $ids as $id ) {
			$control = $element->get_controls( $id );
			if ( ! is_array( $control ) ) {
				continue;
			}
			$current = isset( $control['condition'] ) && is_array( $control['condition'] ) ? $control['condition'] : array();
			$element->update_control( $id, array( 'condition' => $current + array( self::CONTROL => '' ) ) );
		}
	}

	/**
	 * Opciones del select: «Ninguna» y las consultas publicadas. Sólo se consultan en la administración (editor y su
	 * AJAX); en el front no hacen falta (el valor guardado no se valida contra las opciones) y no cuestan nada.
	 */
	public static function options(): array {
		$options = array( '' => __( 'Ninguna', 'pro-elements' ) );
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return $options;
		}
		foreach ( Store::all() as $id => $query ) {
			$options[ (string) $id ] = '' !== $query['title'] ? $query['title'] : sprintf( '#%d', $id );
		}
		return $options;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// AJAX del editor.

	/** @param object $ajax Módulo AJAX del núcleo. */
	public static function register_ajax( $ajax ): void {
		if ( ! is_object( $ajax ) || ! method_exists( $ajax, 'register_ajax_action' ) ) {
			return;
		}
		$ajax->register_ajax_action( self::AJAX_LIST, array( __CLASS__, 'ajax_list' ) );
		$ajax->register_ajax_action( self::AJAX_SETTINGS, array( __CLASS__, 'ajax_settings' ) );
	}

	/**
	 * Lista de consultas publicadas: `{ queries: { id: { title, summary, context, relation, uses, drafts, edit } },
	 * screen, can_manage }`.
	 *
	 * @param mixed $data `{ editor_post_id }` (lo añade el núcleo).
	 * @throws \Exception 403 sin `edit_post` sobre el documento del editor.
	 */
	public static function ajax_list( $data ): array {
		self::authorize( $data );
		$manage  = Store::can_manage();
		$queries = array();
		Store::prime_uses( array_keys( Store::all() ) );
		foreach ( Store::all() as $id => $row ) {
			$query = Store::get( (int) $id );
			if ( null === $query ) {
				continue;
			}
			$uses                    = Store::uses( (int) $id );
			$queries[ (string) $id ] = array(
				'title'    => $query['title'],
				'summary'  => Store::summary( $query ),
				'context'  => $query['context'],
				'relation' => Store::relation_parts( $query['relation'] )[0],
				'valid'    => (bool) $query['valid'],
				'uses'     => $uses['count'],
				'drafts'   => count( $uses['drafts'] ),
				'edit'     => $manage ? Screen::url( array( 'action' => 'edit', 'id' => (int) $id ) ) : '',
			);
		}
		return array(
			'queries'    => $queries,
			'screen'     => $manage ? Screen::url() : '',
			'can_manage' => $manage,
		);
	}

	/**
	 * Ajustes de una consulta publicada en la forma del grupo de PRO, para «Desvincular». Sólo lee.
	 *
	 * @param mixed $data `{ id, editor_post_id }`.
	 * @throws \Exception 403 sin permiso; 404 si no es una consulta publicada.
	 */
	public static function ajax_settings( $data ): array {
		self::authorize( $data );
		$id    = is_array( $data ) && isset( $data['id'] ) && is_numeric( $data['id'] ) ? (int) $data['id'] : 0;
		$query = Store::get( $id );
		if ( null === $query ) {
			throw new \Exception( esc_html__( 'Esa consulta no existe o no está publicada.', 'pro-elements' ), 404 );
		}
		return array(
			'id'       => $id,
			'title'    => $query['title'],
			'settings' => $query['settings'],
			'per_page' => (int) $query['limits']['per_page'],
			'context'  => $query['context'],
			'relation' => Store::relation_parts( $query['relation'] )[0],
			'summary'  => Store::summary( $query ),
		);
	}

	/**
	 * `edit_post` sobre el documento que se edita (C2-18). El núcleo sólo comprueba el nonce.
	 *
	 * @param mixed $data
	 * @throws \Exception 403.
	 */
	private static function authorize( $data ): void {
		$post = is_array( $data ) && isset( $data['editor_post_id'] ) && is_numeric( $data['editor_post_id'] ) ? (int) $data['editor_post_id'] : 0;
		if ( $post <= 0 || ! current_user_can( 'edit_post', $post ) ) {
			throw new \Exception( esc_html__( 'No tienes permiso para editar este documento.', 'pro-elements' ), 403 );
		}
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Script del editor.

	/** `queries-editor.js` y su configuración. El gancho es sólo del editor. */
	public static function enqueue(): void {
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::HANDLE, plugins_url( self::SCRIPT, dirname( __DIR__ ) . '/class-module.php' ), array( 'elementor-editor' ), $version, true );
		wp_add_inline_script( self::HANDLE, 'window.' . self::OBJECT . '=' . wp_json_encode( self::config() ) . ';', 'before' );
	}

	/** Configuración de `queries-editor.js`. */
	public static function config(): array {
		$prefixes = array();
		foreach ( Runtime::WIDGETS as $widget => $group ) {
			$prefixes[ $widget ] = $group . '_';
		}
		return array(
			'control'  => self::CONTROL,
			'event'    => self::EVENT,
			'list'     => self::AJAX_LIST,
			'read'     => self::AJAX_SETTINGS,
			'prefixes' => $prefixes,
			'fields'   => array_keys( Store::DEFAULTS ),
			'i18n'     => array(
				'loading'      => __( 'Cargando la consulta…', 'pro-elements' ),
				'missing'      => __( 'Esta consulta ya no existe o no está publicada: el bucle queda vacío.', 'pro-elements' ),
				/* translators: %d: número de documentos. */
				'edit'         => __( 'Editar en Consultas (%d usos)', 'pro-elements' ),
				/* translators: %d: número de documentos. */
				'uses'         => __( '%d usos', 'pro-elements' ),
				'title'        => __( 'Desvincular la consulta', 'pro-elements' ),
				'confirm'      => __( 'Se copiarán los ajustes de la consulta a este widget y dejará de usarla. Puedes deshacerlo.', 'pro-elements' ),
				'changes'      => __( 'Esta consulta usa contexto o relación, que el widget no tiene: los resultados cambiarán.', 'pro-elements' ),
				'ok'           => __( 'Desvincular', 'pro-elements' ),
				'cancel'       => __( 'Cancelar', 'pro-elements' ),
				'error'        => __( 'No se pudo leer la consulta.', 'pro-elements' ),
			),
		);
	}
}
