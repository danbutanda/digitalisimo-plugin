<?php
namespace Digitalisimo\Elements\F7b\Queries;

defined( 'ABSPATH' ) || exit;

/**
 * Pantalla «Consultas» (`admin.php?page=digitalisimo-elements-queries`; §3.2, V.4, C2-26), sólo en la administración
 * de cada sitio (en una red, las consultas son por sitio; no hay pantalla de red). Cuelga de Plantillas.
 *
 * - **Lista:** nombre, tipo, resumen, estado, usos (`F7b\Usages::of( $id, 'q' )`, con los documentos y los borradores
 *   aparte; «Calculando…» mientras el recorrido de usos no termina) y fecha. «Borrar» sólo sin usos; con usos, «En uso
 *   (N)».
 * - **Crear y editar:** los campos del grupo de consulta de PRO (tipo; incluir y excluir por términos, autores o
 *   entradas con búsqueda AJAX; fecha; orden; desplazamiento; evitar duplicados), el tope por página, la profundidad,
 *   el contexto y la relación. Guardar publica la consulta.
 * - **Probar:** por POST con nonce; los 10 primeros resultados en el servidor, con `no_found_rows` y el tiempo, con
 *   una entrada de contexto opcional para las consultas con relación.
 * - **Búsqueda AJAX** (`digitalisimo_f7b_queries_search`, nonce y `edit_theme_options`): términos de las taxonomías
 *   públicas del tipo, entradas publicadas y autores **sólo por `display_name` y sólo con entradas publicadas** (C2-25).
 *
 * Todo lo que cambia algo va a `admin-post.php` con capacidad, método POST y nonce por acción y consulta; cada acción
 * tiene un `process_*()` que valida y trabaja sin redirigir (lo prueban las pruebas) y un `handle_*()` que lo envuelve.
 * Diseño: `digitalisimo-ui` (la clase `digitalisimo-admin` la pone su cargador en `?page=digitalisimo*`).
 */
final class Screen {
	const PAGE       = 'digitalisimo-elements-queries';
	const TEMPLATES  = 'edit.php?post_type=elementor_library';
	const NOTICE     = 'queries_notice';
	const FLASH      = 'digitalisimo_f7b_query_test_';
	const TEST_LIMIT = 10;

	const ACTION_SAVE   = 'digitalisimo_f7b_query_save';
	const ACTION_DELETE = 'digitalisimo_f7b_query_delete';
	const ACTION_TEST   = 'digitalisimo_f7b_query_test';
	const AJAX_SEARCH   = 'digitalisimo_f7b_queries_search';

	const HANDLE = 'digitalisimo-f7b-queries-screen';
	const SCRIPT = 'assets/queries-screen.js';

	const SEARCH_MIN   = 2;
	const SEARCH_MAX   = 100;
	const SEARCH_LIMIT = 20;

	/** @var bool */
	private static $initialized = false;

	/** Avisos tras una acción: código => [ tipo, texto ]. */
	public static function notices(): array {
		return array(
			'created'   => array( 'success', __( 'Se creó la consulta. Ya puedes elegirla en los widgets de entradas y de bucle.', 'pro-elements' ) ),
			'saved'     => array( 'success', __( 'Se guardó la consulta.', 'pro-elements' ) ),
			'deleted'   => array( 'success', __( 'Se borró la consulta.', 'pro-elements' ) ),
			'forbidden' => array( 'error', __( 'No tienes permiso para gestionar consultas.', 'pro-elements' ) ),
			'nonce'     => array( 'error', __( 'El formulario caducó. Vuelve a la pantalla «Consultas» e inténtalo de nuevo.', 'pro-elements' ) ),
			'method'    => array( 'error', __( 'Esta acción sólo se envía desde el formulario de la pantalla «Consultas».', 'pro-elements' ) ),
			'not_found' => array( 'error', __( 'Esa consulta no existe.', 'pro-elements' ) ),
			'title'     => array( 'error', __( 'Escribe un nombre para la consulta.', 'pro-elements' ) ),
			'post_type' => array( 'error', __( 'Elige un tipo de contenido público.', 'pro-elements' ) ),
			'save'      => array( 'error', __( 'La consulta no se pudo guardar.', 'pro-elements' ) ),
			'in_use'    => array( 'error', __( 'La consulta está en uso: desvincúlala de esos documentos antes de borrarla.', 'pro-elements' ) ),
			'delete'    => array( 'error', __( 'La consulta no se pudo borrar.', 'pro-elements' ) ),
			'tested'    => array( 'info', '' ),
		);
	}

	/** Ganchos de la pantalla (sólo en la administración). Idempotente. */
	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}
		self::$initialized = true;
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 100 );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_' . self::ACTION_TEST, array( __CLASS__, 'handle_test' ) );
		add_action( 'wp_ajax_' . self::AJAX_SEARCH, array( __CLASS__, 'ajax_search' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/** URL de la pantalla en este sitio. */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Menú y script.

	/** Bajo Plantillas; si ese menú no está, junto a las demás pantallas de DIGITALÍSIMO Elements. Nunca en la red. */
	public static function menu(): void {
		add_submenu_page( self::parent_menu(), __( 'Consultas · DIGITALÍSIMO Elements', 'pro-elements' ), __( 'Consultas', 'pro-elements' ), Store::CAPABILITY, self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function parent_menu(): string {
		global $menu, $submenu;
		if ( isset( $submenu[ self::TEMPLATES ] ) ) {
			return self::TEMPLATES;
		}
		$slugs = array();
		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && is_string( $item[2] ) ) {
				$slugs[ $item[2] ] = true;
			}
		}
		$about = class_exists( '\Digitalisimo_Elements_Brand' ) ? \Digitalisimo_Elements_Brand::PAGE : 'digitalisimo-elements-about';
		foreach ( array( self::TEMPLATES, 'digitalisimo', 'elementor-home', 'elementor', $about ) as $slug ) {
			if ( isset( $slugs[ $slug ] ) ) {
				return $slug;
			}
		}
		return 'tools.php';
	}

	/** `queries-screen.js` sólo en esta pantalla. */
	public static function enqueue(): void {
		if ( ! self::on_page() || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::HANDLE, plugins_url( self::SCRIPT, __FILE__ ), array(), $version, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script(
			self::HANDLE,
			'window.digitalisimoQueriesScreen=' . wp_json_encode(
				array(
					'url'    => admin_url( 'admin-ajax.php' ),
					'action' => self::AJAX_SEARCH,
					'nonce'  => wp_create_nonce( self::AJAX_SEARCH ),
					'i18n'   => array(
						'short'   => __( 'Escribe al menos 2 letras.', 'pro-elements' ),
						'none'    => __( 'Sin resultados.', 'pro-elements' ),
						/* translators: %d: número de resultados. */
						'found'   => __( '%d resultados.', 'pro-elements' ),
						/* translators: %s: nombre. */
						'add'     => __( 'Añadir «%s»', 'pro-elements' ),
						/* translators: %s: nombre. */
						'remove'  => __( 'Quitar «%s»', 'pro-elements' ),
						'removeT' => __( 'Quitar', 'pro-elements' ),
						'error'   => __( 'La búsqueda falló. Inténtalo de nuevo.', 'pro-elements' ),
						/* translators: %s: nombre. */
						'added'   => __( 'Se añadió «%s».', 'pro-elements' ),
					),
				)
			) . ';',
			'before'
		);
	}

	private static function on_page(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si se encola.
		return self::PAGE === $page;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Pantalla.

	public static function render(): void {
		if ( ! Store::can_manage() ) {
			echo '<div class="wrap"><p>' . esc_html( self::notices()['forbidden'][1] ) . '</p></div>';
			return;
		}
		Store::register();
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige la vista.
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap digitalisimo-elements-queries">';
		if ( 'edit' === $action || 'new' === $action ) {
			self::render_form( 'edit' === $action ? $id : 0 );
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	private static function render_notice(): void {
		$code    = isset( $_GET[ self::NOTICE ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el texto.
		$notices = self::notices();
		if ( ! isset( $notices[ $code ] ) || '' === $notices[ $code ][1] ) {
			return;
		}
		echo '<div class="notice notice-' . esc_attr( $notices[ $code ][0] ) . '"><p>' . esc_html( $notices[ $code ][1] ) . '</p></div>';
	}

	private static function render_list(): void {
		echo '<h1>' . esc_html__( 'Consultas', 'pro-elements' ) . '</h1>';
		echo '<p>' . esc_html__( 'Consultas de entradas que se eligen en los widgets Entradas, Portafolio, Loop Grid y Loop Carousel con «Consulta guardada». Cambiar una consulta aquí cambia todos los widgets que la usan.', 'pro-elements' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( self::url( array( 'action' => 'new' ) ) ) . '">' . esc_html__( 'Nueva consulta', 'pro-elements' ) . '</a></p>';
		self::render_notice();
		$rows = Store::all( false );
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Todavía no hay consultas guardadas en este sitio.', 'pro-elements' ) . '</p>';
			return;
		}
		$scanning = self::scanning();
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Nombre', 'pro-elements' ), __( 'Consulta', 'pro-elements' ), __( 'Estado', 'pro-elements' ), __( 'Usos', 'pro-elements' ), __( 'Modificada', 'pro-elements' ) ) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Acciones', 'pro-elements' ) . '</span></th></tr></thead><tbody>';
		foreach ( $rows as $id => $row ) {
			$query = Store::get( (int) $id, true );
			if ( null === $query ) {
				continue;
			}
			$uses = Store::uses( (int) $id );
			echo '<tr>';
			echo '<td><a href="' . esc_url( self::url( array( 'action' => 'edit', 'id' => (int) $id ) ) ) . '"><strong>' . esc_html( '' !== $query['title'] ? $query['title'] : '#' . $id ) . '</strong></a></td>';
			echo '<td>' . esc_html( Store::summary( $query ) ) . '</td>';
			echo '<td>' . self::status_badge( $query ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en status_badge().
			echo '<td>' . self::uses_cell( $uses, $scanning ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en uses_cell().
			echo '<td>' . esc_html( self::date( $row['modified'] ) ) . '</td>';
			echo '<td>';
			if ( $uses['count'] > 0 ) {
				/* translators: %d: número de documentos. */
				echo '<span>' . esc_html( sprintf( __( 'En uso (%d)', 'pro-elements' ), $uses['count'] ) ) . '</span>';
			} else {
				self::render_delete_form( (int) $id, $query['title'] );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function status_badge( array $query ): string {
		if ( 'publish' !== $query['status'] ) {
			return '<span class="digitalisimo-effective-state is-inactive">' . esc_html__( 'Borrador: revisar antes de publicar', 'pro-elements' ) . '</span>';
		}
		if ( empty( $query['valid'] ) ) {
			return '<span class="digitalisimo-effective-state is-inactive">' . esc_html__( 'No válida: los bucles que la usan quedan vacíos', 'pro-elements' ) . '</span>';
		}
		return '<span class="digitalisimo-effective-state is-active">' . esc_html__( 'Publicada', 'pro-elements' ) . '</span>';
	}

	private static function uses_cell( array $uses, bool $scanning ): string {
		if ( $scanning ) {
			return esc_html__( 'Calculando…', 'pro-elements' );
		}
		$out = esc_html( (string) $uses['count'] );
		if ( $uses['drafts'] ) {
			/* translators: %d: número de borradores. */
			$out .= ' <span class="description">' . esc_html( sprintf( _n( '(y %d borrador)', '(y %d borradores)', count( $uses['drafts'] ), 'pro-elements' ), count( $uses['drafts'] ) ) ) . '</span>';
		}
		$docs = array_slice( array_merge( $uses['posts'], $uses['drafts'] ), 0, 20 );
		if ( ! $docs ) {
			return $out;
		}
		$out .= '<details><summary>' . esc_html__( 'Documentos', 'pro-elements' ) . '</summary><ul class="ul-disc">';
		foreach ( $docs as $doc ) {
			$title = function_exists( 'get_the_title' ) ? get_the_title( $doc ) : '';
			$link  = function_exists( 'get_edit_post_link' ) ? get_edit_post_link( $doc, 'raw' ) : '';
			$label = '' !== $title ? $title : '#' . $doc;
			$out  .= '<li>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $label ) . '</a>' : esc_html( $label ) ) . '</li>';
		}
		return $out . '</ul></details>';
	}

	private static function render_delete_form( int $id, string $title ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="digitalisimo-sq-delete">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE ) . '">';
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '">';
		wp_nonce_field( self::ACTION_DELETE . '-' . $id );
		/* translators: %s: nombre de la consulta. */
		$label = sprintf( __( 'Borrar «%s»', 'pro-elements' ), '' !== $title ? $title : '#' . $id );
		echo '<button type="submit" class="button button-secondary" aria-label="' . esc_attr( $label ) . '" data-confirm="' . esc_attr__( '¿Borrar esta consulta? No se puede deshacer.', 'pro-elements' ) . '">' . esc_html__( 'Borrar', 'pro-elements' ) . '</button>';
		echo '</form>';
	}

	/** ¿Sigue el recorrido de usos del frente A (`Scan::running()`, si existe)? */
	private static function scanning(): bool {
		$scan = '\Digitalisimo\Elements\F7b\Scan';
		return class_exists( $scan ) && method_exists( $scan, 'running' ) && (bool) $scan::running();
	}

	private static function render_form( int $id ): void {
		$query = $id > 0 ? Store::get( $id, true ) : null;
		if ( $id > 0 && null === $query ) {
			echo '<h1>' . esc_html__( 'Consultas', 'pro-elements' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html( self::notices()['not_found'][1] ) . '</p></div>';
			echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Volver a Consultas', 'pro-elements' ) . '</a></p>';
			return;
		}
		$settings = $query ? $query['settings'] : Store::DEFAULTS;
		$limits   = $query ? $query['limits'] : Store::DEFAULT_LIMITS;
		$context  = $query ? $query['context'] : 'none';
		list( $kind, $arg ) = Store::relation_parts( $query ? $query['relation'] : 'none' );

		echo '<h1>' . esc_html( $query ? __( 'Editar consulta', 'pro-elements' ) : __( 'Nueva consulta', 'pro-elements' ) ) . '</h1>';
		echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( '← Volver a Consultas', 'pro-elements' ) . '</a></p>';
		self::render_notice();
		if ( $query ) {
			echo '<p>' . self::status_badge( $query ) . ' ' . esc_html( Store::summary( $query ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en status_badge().
			self::render_test_result( $id );
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '">';
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '">';
		wp_nonce_field( self::ACTION_SAVE . '-' . $id );
		echo '<table class="form-table" role="presentation"><tbody>';

		self::row( 'digi-sq-title', __( 'Nombre', 'pro-elements' ), '<input type="text" id="digi-sq-title" name="q[title]" class="regular-text" required maxlength="' . esc_attr( (string) Store::MAX_TITLE ) . '" value="' . esc_attr( $query ? $query['title'] : '' ) . '">' );

		$types = '';
		foreach ( Store::post_types() as $name => $label ) {
			$types .= '<option value="' . esc_attr( $name ) . '"' . selected( $settings['post_type'], $name, false ) . '>' . esc_html( $label ) . '</option>';
		}
		self::row( 'digi-sq-type', __( 'Tipo de contenido', 'pro-elements' ), '<select id="digi-sq-type" name="q[settings][post_type]" data-digi-sq-type>' . $types . '</select>' );

		// Incluir.
		$include  = self::checks( 'include', Store::INCLUDE_BY, $settings['include'], array( 'terms' => __( 'Términos', 'pro-elements' ), 'authors' => __( 'Autores', 'pro-elements' ) ) );
		$include .= self::picker( 'include_term_ids', 'term', __( 'Términos que se incluyen', 'pro-elements' ), $settings['include_term_ids'] );
		$include .= self::picker( 'include_authors', 'author', __( 'Autores que se incluyen', 'pro-elements' ), $settings['include_authors'] );
		self::row( '', __( 'Incluir por', 'pro-elements' ), '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Incluir por', 'pro-elements' ) . '</legend>' . $include . '</fieldset>' );

		// Excluir.
		$exclude  = self::checks(
			'exclude',
			Store::EXCLUDE_BY,
			$settings['exclude'],
			array(
				'current_post'     => __( 'Entrada actual', 'pro-elements' ),
				'manual_selection' => __( 'Selección manual', 'pro-elements' ),
				'terms'            => __( 'Términos', 'pro-elements' ),
				'authors'          => __( 'Autores', 'pro-elements' ),
			)
		);
		$exclude .= self::picker( 'exclude_ids', 'post', __( 'Entradas que se excluyen', 'pro-elements' ), $settings['exclude_ids'] );
		$exclude .= self::picker( 'exclude_term_ids', 'term', __( 'Términos que se excluyen', 'pro-elements' ), $settings['exclude_term_ids'] );
		$exclude .= self::picker( 'exclude_authors', 'author', __( 'Autores que se excluyen', 'pro-elements' ), $settings['exclude_authors'] );
		$exclude .= '<p><label><input type="checkbox" name="q[settings][avoid_duplicates]" value="yes"' . checked( $settings['avoid_duplicates'], 'yes', false ) . '> ' . esc_html__( 'Evitar duplicados (no repetir entradas ya mostradas en la página)', 'pro-elements' ) . '</label></p>';
		$exclude .= '<p><label for="digi-sq-offset">' . esc_html__( 'Saltar las primeras', 'pro-elements' ) . '</label> <input type="number" id="digi-sq-offset" name="q[settings][offset]" min="0" max="' . esc_attr( (string) Store::MAX_OFFSET ) . '" step="1" class="small-text" value="' . esc_attr( (string) $settings['offset'] ) . '"></p>';
		self::row( '', __( 'Excluir por', 'pro-elements' ), '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Excluir por', 'pro-elements' ) . '</legend>' . $exclude . '</fieldset>' );

		// Fecha.
		$dates = array(
			'anytime' => __( 'Todas', 'pro-elements' ),
			'today'   => __( 'Último día', 'pro-elements' ),
			'week'    => __( 'Última semana', 'pro-elements' ),
			'month'   => __( 'Último mes', 'pro-elements' ),
			'quarter' => __( 'Último trimestre', 'pro-elements' ),
			'year'    => __( 'Último año', 'pro-elements' ),
			'exact'   => __( 'Entre dos fechas', 'pro-elements' ),
		);
		$date  = '<select id="digi-sq-date" name="q[settings][select_date]">' . self::options( $dates, $settings['select_date'] ) . '</select>';
		$date .= '<p><label for="digi-sq-after">' . esc_html__( 'Desde', 'pro-elements' ) . '</label> <input type="date" id="digi-sq-after" name="q[settings][date_after]" value="' . esc_attr( substr( $settings['date_after'], 0, 10 ) ) . '"> ';
		$date .= '<label for="digi-sq-before">' . esc_html__( 'Hasta', 'pro-elements' ) . '</label> <input type="date" id="digi-sq-before" name="q[settings][date_before]" value="' . esc_attr( substr( $settings['date_before'], 0, 10 ) ) . '"></p>';
		$date .= '<p class="description">' . esc_html__( 'Las dos fechas sólo cuentan con «Entre dos fechas», y se incluyen.', 'pro-elements' ) . '</p>';
		self::row( 'digi-sq-date', __( 'Fecha', 'pro-elements' ), $date );

		// Orden.
		$orderby = array(
			'post_date'     => __( 'Fecha', 'pro-elements' ),
			'post_title'    => __( 'Título', 'pro-elements' ),
			'menu_order'    => __( 'Orden del menú', 'pro-elements' ),
			'modified'      => __( 'Última modificación', 'pro-elements' ),
			'comment_count' => __( 'Número de comentarios', 'pro-elements' ),
			'rand'          => __( 'Aleatorio', 'pro-elements' ),
		);
		$order   = '<select id="digi-sq-orderby" name="q[settings][orderby]">' . self::options( $orderby, $settings['orderby'] ) . '</select> ';
		$order  .= '<label for="digi-sq-order" class="screen-reader-text">' . esc_html__( 'Sentido', 'pro-elements' ) . '</label><select id="digi-sq-order" name="q[settings][order]">' . self::options( array( 'desc' => __( 'Descendente', 'pro-elements' ), 'asc' => __( 'Ascendente', 'pro-elements' ) ), $settings['order'] ) . '</select>';
		self::row( 'digi-sq-orderby', __( 'Ordenar por', 'pro-elements' ), $order );

		// Topes.
		$limit  = '<input type="number" id="digi-sq-per-page" name="q[limits][per_page]" min="1" max="' . esc_attr( (string) Store::MAX_PER_PAGE ) . '" step="1" class="small-text" value="' . esc_attr( (string) $limits['per_page'] ) . '" aria-describedby="digi-sq-per-page-help">';
		$limit .= '<p class="description" id="digi-sq-per-page-help">' . esc_html__( 'Tope de entradas por página (hasta 100). Cada widget usa su propia cantidad, sin pasar de este tope.', 'pro-elements' ) . '</p>';
		self::row( 'digi-sq-per-page', __( 'Cantidad máxima', 'pro-elements' ), $limit );

		// Contexto y relación.
		$contexts = array(
			'none'    => __( 'Ninguno', 'pro-elements' ),
			'current' => __( 'Actual: la entrada que se está mostrando (o la del bucle que contiene este widget)', 'pro-elements' ),
			'parent'  => __( 'Padre: la entrada del bucle exterior', 'pro-elements' ),
			'root'    => __( 'Raíz: la entrada de la página', 'pro-elements' ),
		);
		$relations = array(
			'none'     => __( 'Ninguna', 'pro-elements' ),
			'author'   => __( 'Del mismo autor', 'pro-elements' ),
			'terms'    => __( 'Con los mismos términos de la taxonomía', 'pro-elements' ),
			'children' => __( 'Entradas hijas', 'pro-elements' ),
			'meta'     => __( 'Con el mismo valor en el campo', 'pro-elements' ),
		);
		$rel  = '<p><label for="digi-sq-relation">' . esc_html__( 'Relación', 'pro-elements' ) . '</label><br><select id="digi-sq-relation" name="q[relation_kind]">' . self::options( $relations, $kind ) . '</select></p>';
		$rel .= '<p><label for="digi-sq-relation-tax">' . esc_html__( 'Taxonomía (para «mismos términos»)', 'pro-elements' ) . '</label><br><select id="digi-sq-relation-tax" name="q[relation_tax]">' . self::options( array( '' => __( 'Elige una', 'pro-elements' ) ) + Store::taxonomies(), 'terms' === $kind ? $arg : '' ) . '</select></p>';
		$rel .= '<p><label for="digi-sq-relation-meta">' . esc_html__( 'Clave del campo (para «mismo valor»)', 'pro-elements' ) . '</label><br><input type="text" id="digi-sq-relation-meta" name="q[relation_meta]" class="regular-text" maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9_\-]{0,63}" value="' . esc_attr( 'meta' === $kind ? $arg : '' ) . '" aria-describedby="digi-sq-relation-meta-help"></p>';
		$rel .= '<p class="description" id="digi-sq-relation-meta-help">' . esc_html__( 'Sólo campos públicos: sin «_» al principio y sin nombres de datos personales o secretos (contraseña, correo, teléfono, clave…).', 'pro-elements' ) . '</p>';
		$rel .= '<p><label for="digi-sq-context">' . esc_html__( 'Contexto', 'pro-elements' ) . '</label><br><select id="digi-sq-context" name="q[context]">' . self::options( $contexts, $context ) . '</select></p>';
		$rel .= '<p class="description">' . esc_html__( 'La relación se calcula con la entrada de contexto, que nunca aparece en los resultados. Sin contexto en la página, la consulta no muestra nada. Un bucle con contexto «padre» no admite filtros por AJAX.', 'pro-elements' ) . '</p>';
		self::row( 'digi-sq-relation', __( 'Relacionar con', 'pro-elements' ), $rel );

		$depth = '<select id="digi-sq-depth" name="q[limits][depth]">' . self::options( array( '1' => __( 'Sólo en el nivel superior', 'pro-elements' ), '2' => __( 'También dentro de otro bucle', 'pro-elements' ) ), (string) $limits['depth'] ) . '</select>';
		self::row( 'digi-sq-depth', __( 'Dónde se puede usar', 'pro-elements' ), $depth );

		echo '</tbody></table>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html( $query ? __( 'Guardar y publicar', 'pro-elements' ) : __( 'Crear la consulta', 'pro-elements' ) ) . '</button></p>';
		echo '</form>';

		if ( $query ) {
			self::render_test_form( $id, $query );
			$uses = Store::uses( $id );
			echo '<h2>' . esc_html__( 'Usos', 'pro-elements' ) . '</h2>';
			echo '<p>' . self::uses_cell( $uses, self::scanning() ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en uses_cell().
			if ( 0 === $uses['count'] ) {
				self::render_delete_form( $id, $query['title'] );
			}
		}
	}

	private static function row( string $for, string $label, string $field ): void {
		$head = '' !== $for ? '<label for="' . esc_attr( $for ) . '">' . esc_html( $label ) . '</label>' : esc_html( $label );
		echo '<tr><th scope="row">' . $head . '</th><td>' . $field . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partes ya escapadas.
	}

	private static function options( array $options, string $current ): string {
		$out = '';
		foreach ( $options as $value => $label ) {
			$out .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( $current, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $out;
	}

	private static function checks( string $name, array $allowed, array $current, array $labels ): string {
		$out = '';
		foreach ( $allowed as $value ) {
			$out .= '<label><input type="checkbox" name="q[settings][' . esc_attr( $name ) . '][]" value="' . esc_attr( $value ) . '"' . checked( in_array( $value, $current, true ), true, false ) . '> ' . esc_html( $labels[ $value ] ?? $value ) . '</label> ';
		}
		return '<p>' . $out . '</p>';
	}

	/**
	 * Selector con búsqueda AJAX: los elegidos van como campos ocultos dentro de la lista (se quitan con un botón); la
	 * búsqueda y «Añadir» los pone `queries-screen.js`.
	 *
	 * @param int[] $ids
	 */
	private static function picker( string $field, string $object, string $label, array $ids, string $name = '' ): string {
		$name   = '' !== $name ? $name : 'q[settings][' . $field . '][]';
		$id     = 'digi-sq-' . str_replace( '_', '-', $field );
		$chosen = '';
		foreach ( $ids as $item ) {
			$text    = self::label( $object, (int) $item );
			$chosen .= '<li><span>' . esc_html( $text ) . '</span> <input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $item ) . '">';
			/* translators: %s: nombre. */
			$chosen .= '<button type="button" class="button-link" data-remove aria-label="' . esc_attr( sprintf( __( 'Quitar «%s»', 'pro-elements' ), $text ) ) . '">' . esc_html__( 'Quitar', 'pro-elements' ) . '</button></li>';
		}
		$out  = '<div class="digitalisimo-sq-picker" data-digi-sq-picker data-object="' . esc_attr( $object ) . '" data-name="' . esc_attr( $name ) . '"' . ( 'q[ctx]' === $name ? ' data-single' : '' ) . '>';
		$out .= '<p><strong id="' . esc_attr( $id ) . '-label">' . esc_html( $label ) . '</strong></p>';
		$out .= '<ul class="ul-disc" data-chosen aria-labelledby="' . esc_attr( $id ) . '-label">' . $chosen . '</ul>';
		$out .= '<p><label for="' . esc_attr( $id ) . '" class="screen-reader-text">' . esc_html( $label ) . '</label>';
		$out .= '<input type="search" id="' . esc_attr( $id ) . '" class="regular-text" data-search autocomplete="off" maxlength="' . esc_attr( (string) self::SEARCH_MAX ) . '" placeholder="' . esc_attr__( 'Buscar…', 'pro-elements' ) . '"> ';
		$out .= '<button type="button" class="button" data-go>' . esc_html__( 'Buscar', 'pro-elements' ) . '</button></p>';
		$out .= '<ul data-results></ul><p class="screen-reader-text" aria-live="polite" data-status></p>';
		return $out . '</div>';
	}

	/** Nombre para mostrar de un término (`term_taxonomy_id`), autor o entrada ya elegidos. */
	public static function label( string $object, int $id ): string {
		switch ( $object ) {
			case 'term':
				$term = function_exists( 'get_term_by' ) ? get_term_by( 'term_taxonomy_id', $id ) : null;
				if ( is_object( $term ) ) {
					$tax = function_exists( 'get_taxonomy' ) ? get_taxonomy( $term->taxonomy ) : null;
					return ( is_object( $tax ) ? $tax->label . ': ' : '' ) . $term->name;
				}
				break;
			case 'author':
				$user = function_exists( 'get_userdata' ) ? get_userdata( $id ) : null;
				if ( is_object( $user ) ) {
					return (string) $user->display_name;
				}
				break;
			case 'post':
				$post = function_exists( 'get_post' ) ? get_post( $id ) : null;
				if ( is_object( $post ) ) {
					return (string) $post->post_title;
				}
				break;
		}
		/* translators: %d: ID. */
		return sprintf( __( '#%d (ya no existe)', 'pro-elements' ), $id );
	}

	private static function render_test_form( int $id, array $query ): void {
		echo '<h2>' . esc_html__( 'Probar', 'pro-elements' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_TEST ) . '">';
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '">';
		wp_nonce_field( self::ACTION_TEST . '-' . $id );
		echo '<p>' . esc_html__( 'Ejecuta la consulta guardada en el servidor y muestra los 10 primeros resultados con el tiempo que tardó.', 'pro-elements' ) . '</p>';
		if ( 'none' !== $query['relation'] ) {
			echo self::picker( 'ctx', 'post', __( 'Entrada de contexto para la prueba', 'pro-elements' ), array(), 'q[ctx]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en picker().
		}
		echo '<p><button type="submit" class="button">' . esc_html__( 'Probar la consulta', 'pro-elements' ) . '</button></p>';
		echo '</form>';
	}

	private static function render_test_result( int $id ): void {
		if ( ! function_exists( 'get_transient' ) ) {
			return;
		}
		$key    = self::FLASH . get_current_user_id();
		$result = get_transient( $key );
		if ( ! is_array( $result ) || (int) ( $result['id'] ?? 0 ) !== $id ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-info"><p>';
		if ( '' !== ( $result['reason'] ?? '' ) ) {
			echo esc_html( $result['reason'] );
		} else {
			/* translators: 1: número de resultados, 2: milisegundos. */
			echo esc_html( sprintf( __( 'Prueba: %1$d resultados en %2$s ms (como mucho los 10 primeros).', 'pro-elements' ), count( $result['items'] ), number_format_i18n( (float) $result['ms'], 1 ) ) );
		}
		echo '</p>';
		if ( ! empty( $result['items'] ) ) {
			echo '<ol>';
			foreach ( $result['items'] as $item ) {
				echo '<li>' . esc_html( $item['title'] ) . ' <span class="description">(' . esc_html( $item['type'] . ' · ' . $item['date'] ) . ')</span></li>';
			}
			echo '</ol>';
		}
		echo '</div>';
	}

	private static function date( string $mysql ): string {
		if ( '' === $mysql || ! function_exists( 'mysql2date' ) ) {
			return $mysql;
		}
		return (string) mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $mysql );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Acciones (admin-post.php).

	/**
	 * Capacidad, método POST y nonce de la acción para esa consulta. `[]` si vale; si no, el aviso.
	 */
	public static function check_request( string $action, int $id ): array {
		if ( ! Store::can_manage() ) {
			return array( 'status' => 403, 'notice' => 'forbidden' );
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sólo se compara.
			return array( 'notice' => 'method' );
		}
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action . '-' . $id ) ) {
			return array( 'status' => 403, 'notice' => 'nonce' );
		}
		return array();
	}

	private static function posted_id(): int {
		return isset( $_POST['id'] ) && is_numeric( $_POST['id'] ) ? max( 0, (int) $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- el nonce incluye el ID y se comprueba después.
	}

	/** El formulario `q[...]` como entrada de `Store::save()`. */
	public static function input( array $q ): array {
		$kind     = isset( $q['relation_kind'] ) && is_string( $q['relation_kind'] ) ? $q['relation_kind'] : 'none';
		$relation = $kind;
		if ( 'terms' === $kind ) {
			$relation = 'terms:' . ( isset( $q['relation_tax'] ) && is_string( $q['relation_tax'] ) ? $q['relation_tax'] : '' );
		} elseif ( 'meta' === $kind ) {
			$relation = 'meta:' . ( isset( $q['relation_meta'] ) && is_string( $q['relation_meta'] ) ? trim( $q['relation_meta'] ) : '' );
		}
		return array(
			'title'    => isset( $q['title'] ) && is_string( $q['title'] ) ? $q['title'] : '',
			'settings' => isset( $q['settings'] ) && is_array( $q['settings'] ) ? $q['settings'] : array(),
			'context'  => isset( $q['context'] ) && is_string( $q['context'] ) ? $q['context'] : 'none',
			'relation' => $relation,
			'limits'   => isset( $q['limits'] ) && is_array( $q['limits'] ) ? $q['limits'] : array(),
		);
	}

	/** @return array `{ notice, id?, status? }`. */
	public static function process_save(): array {
		$id    = self::posted_id();
		$check = self::check_request( self::ACTION_SAVE, $id );
		if ( $check ) {
			return $check + array( 'id' => $id );
		}
		$q      = isset( $_POST['q'] ) && is_array( $_POST['q'] ) ? wp_unslash( $_POST['q'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- `Store::save()` sanea cada clave con listas cerradas.
		$result = Store::save( self::input( $q ), $id );
		if ( is_wp_error( $result ) ) {
			return array(
				'notice' => $result->get_error_code(),
				'id'     => $id,
			);
		}
		return array(
			'notice' => $id > 0 ? 'saved' : 'created',
			'id'     => (int) $result,
		);
	}

	/** @return array `{ notice, id?, status? }`. */
	public static function process_delete(): array {
		$id    = self::posted_id();
		$check = self::check_request( self::ACTION_DELETE, $id );
		if ( $check ) {
			return $check;
		}
		$result = Store::delete( $id );
		if ( is_wp_error( $result ) ) {
			return array(
				'notice' => $result->get_error_code(),
				'id'     => 'not_found' === $result->get_error_code() ? 0 : $id,
			);
		}
		return array( 'notice' => 'deleted' );
	}

	/** @return array `{ notice, id, result? }`. */
	public static function process_test(): array {
		$id    = self::posted_id();
		$check = self::check_request( self::ACTION_TEST, $id );
		if ( $check ) {
			return $check + array( 'id' => $id );
		}
		$query = Store::get( $id, true );
		if ( null === $query ) {
			return array( 'notice' => 'not_found' );
		}
		$q   = isset( $_POST['q'] ) && is_array( $_POST['q'] ) ? wp_unslash( $_POST['q'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sólo se lee un ID entero.
		$ctx = isset( $q['ctx'] ) ? Store::ids( (array) $q['ctx'] ) : array();
		return array(
			'notice' => 'tested',
			'id'     => $id,
			'result' => array( 'id' => $id ) + self::test( $query, $ctx ? $ctx[0] : 0 ),
		);
	}

	/**
	 * Ejecuta una consulta guardada en el servidor: los 10 primeros resultados y el tiempo. Sin efectos.
	 *
	 * @return array `{ items: { title, type, date }[], ms, reason }`.
	 */
	public static function test( array $query, int $ctx = 0 ): array {
		$out = array( 'items' => array(), 'ms' => 0.0, 'reason' => '' );
		if ( empty( $query['valid'] ) ) {
			$out['reason'] = Runtime::note( 'missing' );
			return $out;
		}
		$built = Args::build( $query['settings'] );
		if ( $built['closed'] ) {
			$out['reason'] = Runtime::note( 'closed' );
			return $out;
		}
		$sel = Runtime::relate( $built['args'], $query, $ctx );
		if ( null === $sel ) {
			$out['reason'] = __( 'Esta consulta necesita una entrada de contexto con el dato de la relación: elige una para probarla.', 'pro-elements' );
			return $out;
		}
		$args = array_merge(
			$sel,
			array(
				'post_status'            => 'publish',
				'posts_per_page'         => min( self::TEST_LIMIT, (int) $query['limits']['per_page'] ),
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( $query['settings']['offset'] > 0 ) {
			$args['offset'] = (int) $query['settings']['offset'];
		}
		$start  = microtime( true );
		$wp     = new \WP_Query( $args );
		$out['ms'] = round( ( microtime( true ) - $start ) * 1000, 1 );
		$types  = Store::post_types();
		foreach ( (array) $wp->posts as $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}
			$out['items'][] = array(
				'title' => '' !== (string) $post->post_title ? (string) $post->post_title : '#' . (int) $post->ID,
				'type'  => $types[ $post->post_type ] ?? (string) $post->post_type,
				'date'  => function_exists( 'mysql2date' ) ? (string) mysql2date( get_option( 'date_format' ), $post->post_date ) : (string) $post->post_date,
			);
		}
		return $out;
	}

	public static function handle_save(): void {
		$result = self::process_save();
		$args   = array( self::NOTICE => $result['notice'] );
		if ( ! empty( $result['id'] ) ) {
			$args += array( 'action' => 'edit', 'id' => (int) $result['id'] );
		} elseif ( ! in_array( $result['notice'], array( 'created', 'saved' ), true ) ) {
			$args['action'] = 'new';
		}
		self::finish( $result, $args );
	}

	public static function handle_delete(): void {
		$result = self::process_delete();
		$args   = array( self::NOTICE => $result['notice'] );
		if ( ! empty( $result['id'] ) ) {
			$args += array( 'action' => 'edit', 'id' => (int) $result['id'] );
		}
		self::finish( $result, $args );
	}

	public static function handle_test(): void {
		$result = self::process_test();
		if ( isset( $result['result'] ) ) {
			set_transient( self::FLASH . get_current_user_id(), $result['result'], 5 * MINUTE_IN_SECONDS );
		}
		$args = array( self::NOTICE => $result['notice'] );
		if ( ! empty( $result['id'] ) ) {
			$args += array( 'action' => 'edit', 'id' => (int) $result['id'] );
		}
		self::finish( $result, $args );
	}

	private static function finish( array $result, array $args ): void {
		if ( isset( $result['status'] ) && 403 === (int) $result['status'] ) {
			$notices = self::notices();
			wp_die( esc_html( $notices[ $result['notice'] ][1] ?? $notices['forbidden'][1] ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Búsqueda AJAX.

	public static function ajax_search(): void {
		if ( ! check_ajax_referer( self::AJAX_SEARCH, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => self::notices()['nonce'][1] ), 403 );
		}
		if ( ! Store::can_manage() ) {
			wp_send_json_error( array( 'message' => self::notices()['forbidden'][1] ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- comprobado arriba.
		$object = isset( $_REQUEST['object'] ) && is_string( $_REQUEST['object'] ) ? sanitize_key( wp_unslash( $_REQUEST['object'] ) ) : '';
		$term   = isset( $_REQUEST['q'] ) && is_string( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['q'] ) ) : '';
		$type   = isset( $_REQUEST['post_type'] ) && is_string( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '';
		// phpcs:enable
		wp_send_json_success( array( 'results' => self::search( $object, $term, $type ) ) );
	}

	/**
	 * Búsqueda para los selectores. Autores: sólo `display_name` (nunca el usuario ni el correo) y sólo de quien tiene
	 * entradas publicadas de un tipo público (C2-25).
	 *
	 * @return array<int,array{id:int,text:string}>
	 */
	public static function search( string $object, string $term, string $post_type = '' ): array {
		$term   = trim( $term );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $term ) : strlen( $term );
		if ( $length < self::SEARCH_MIN || $length > self::SEARCH_MAX ) {
			return array();
		}
		$types     = Store::post_types();
		$post_type = isset( $types[ $post_type ] ) ? $post_type : '';
		$out       = array();
		switch ( $object ) {
			case 'term':
				$taxonomies = Store::taxonomies( $post_type );
				if ( ! $taxonomies ) {
					return array();
				}
				$terms = get_terms(
					array(
						'taxonomy'   => array_keys( $taxonomies ),
						'search'     => $term,
						'number'     => self::SEARCH_LIMIT,
						'hide_empty' => false,
					)
				);
				foreach ( is_array( $terms ) ? $terms : array() as $item ) {
					if ( is_object( $item ) && isset( $taxonomies[ $item->taxonomy ] ) ) {
						$out[] = array(
							'id'   => (int) $item->term_taxonomy_id,
							'text' => $taxonomies[ $item->taxonomy ] . ': ' . $item->name,
						);
					}
				}
				break;
			case 'author':
				$users = get_users(
					array(
						'has_published_posts' => array_keys( $types ),
						'search'              => '*' . $term . '*',
						'search_columns'      => array( 'display_name' ),
						'fields'              => array( 'ID', 'display_name' ),
						'number'              => self::SEARCH_LIMIT,
						'orderby'             => 'display_name',
					)
				);
				foreach ( (array) $users as $user ) {
					if ( is_object( $user ) && isset( $user->ID ) ) {
						$out[] = array(
							'id'   => (int) $user->ID,
							'text' => (string) $user->display_name,
						);
					}
				}
				break;
			case 'post':
				if ( ! $types ) {
					return array();
				}
				$posts = get_posts(
					array(
						'post_type'              => '' !== $post_type ? array( $post_type ) : array_keys( $types ),
						'post_status'            => 'publish',
						's'                      => $term,
						'posts_per_page'         => self::SEARCH_LIMIT,
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						'suppress_filters'       => true,
					)
				);
				foreach ( (array) $posts as $post ) {
					if ( is_object( $post ) && isset( $post->ID ) ) {
						$out[] = array(
							'id'   => (int) $post->ID,
							'text' => (string) $post->post_title . ' (' . ( $types[ $post->post_type ] ?? $post->post_type ) . ')',
						);
					}
				}
				break;
		}
		return $out;
	}
}
