<?php
namespace Digitalisimo\Elements\F7b\Queries;

defined( 'ABSPATH' ) || exit;

/**
 * Aplicación de las consultas guardadas al pintar (V.4; C1-4, C1-5, C1-19, C2-13, C2-14).
 *
 * Sólo se engancha con `Flags::active( 'saved_queries' )` (algún documento las usa, o se está editando). Ganchos:
 *
 * - `elementor/widget/before_render_content` (Posts, Portfolio, Loop Grid, Loop Carousel): apila el contexto del
 *   bucle. En el nivel superior es la entrada consultada (o `digi_ctx` en `refresh-loop`); dentro de otro bucle,
 *   `get_the_ID()`, la entrada de ese bucle que se está pintando (`skin-loop-base.php:201-220`). Cada entrada guarda el
 *   nivel de `ob_get_level()`: lo que se pinta dentro de un bucle está siempre en un búfer más profundo que él, así que
 *   al apilar se descartan las entradas de nivel igual o mayor (bucles hermanos ya pintados). Así la pila también es
 *   correcta en `refresh-loop` y en el render del editor, que llaman a `render_content()` sin `print_element()` y
 *   nunca disparan el desapilado. Con una consulta de contexto `current` o `root` en el nivel superior, el envoltorio
 *   lleva `data-digi-ctx` (el bucle es dinámico: nunca entra en la caché de elementos).
 * - `elementor/frontend/widget/after_render`: desapila (C1-4; `elementor/widget/after_render_content` no existe).
 * - `elementor/query/query_args` a prioridad 5 (C1-5): **fusión**. Del widget se quitan las claves de selección
 *   (`Args::SELECTION_KEYS`) y se ponen las de la consulta con su relación; `paged`, `offset` de las plantillas
 *   alternas, `ignore_sticky_posts`, `has_custom_pagination` y lo que añaden después los filtros (prioridad 10) quedan
 *   como estaban. `posts_per_page` es el del widget con el `per_page` de la consulta como tope (y lo que quede del
 *   presupuesto). `post_status` siempre `publish`.
 * - `elementor/query/query_results`: cuenta las entradas pintadas y, con `no_found_rows`, deja `found_posts` y
 *   `max_num_pages` coherentes con lo obtenido (los skins de PRO no pintan nada si `found_posts` es 0).
 * - `pre_get_posts` (1) y `found_posts` (1): el desplazamiento (`offset`) de la consulta, con la misma regla que PRO
 *   (`fix_query_offset()` y `fix_query_found_posts()`), porque el del widget queda oculto.
 * - `rest_request_before_callbacks`: en `elementor-pro/v1/refresh-loop`, `digi_ctx` se acepta sólo si
 *   `is_post_publicly_viewable()`; si no, el contexto es 0 y las consultas con contexto fallan cerradas (C2-13).
 *
 * Contextos: `current` y `root` (`parent` se retiró: con profundidad ≤ 2 coincidía siempre con uno de los dos; un
 * `parent` guardado deja la consulta no válida en `Store` y falla cerrada).
 *
 * Fallar cerrado (`post__in => [0]`): consulta borrada, en borrador o no válida; sin contexto; relación sin valor en
 * el contexto; profundidad mayor que la de la consulta (≤ 2); más de 50 ejecuciones o 300 entradas pintadas por
 * consultas guardadas en la petición. En el editor, el widget lo explica con un aviso.
 *
 * Coste: la lectura de la consulta está memorizada (`Store::get()`: ≤ 2 consultas SQL sin caché de objetos). Los
 * argumentos idénticos no repiten SQL en la petición: la consulta va con `cache_results` y WordPress guarda sus
 * resultados en el grupo `post-queries` de la caché de objetos, que sin caché persistente vive lo que dura la petición
 * (`WP_Query::get_posts()`, WordPress 6.1+; no con `orderby => rand`, que nunca se repite igual). `no_found_rows`
 * salvo con paginación o plantillas alternas; la precarga de metas y términos sólo si la plantilla del bucle los usa.
 */
final class Runtime {
	/**
	 * Widgets con el grupo de consulta de PRO => nombre de su grupo (`get_query_name()`). Los propios de C5
	 * (`modules/digitalisimo-loop/`) usan el mismo grupo y la misma consulta que el de PRO al que sustituyen.
	 */
	const WIDGETS = array(
		'posts'                      => 'posts',
		'portfolio'                  => 'posts',
		'loop-grid'                  => 'post_query',
		'loop-carousel'              => 'post_query',
		'digitalisimo-posts'         => 'posts',
		'digitalisimo-portfolio'     => 'posts',
		'digitalisimo-loop-grid'     => 'post_query',
		'digitalisimo-loop-carousel' => 'post_query',
	);

	/** Variable de consulta que marca un `WP_Query` construido con una consulta guardada (su ID). */
	const TAG = 'digitalisimo_saved_query';

	const MAX_RUNS  = 50;
	const MAX_POSTS = 300;

	const ROUTE = '/elementor-pro/v1/refresh-loop';
	const PARAM = 'digi_ctx';

	/** @var bool */
	private static $initialized = false;

	/** @var array<int,array{id:string,ctx:int,level:int}> */
	private static $stack = array();

	/** @var int */
	private static $runs = 0;

	/** @var int */
	private static $posts = 0;

	/** @var int|null Contexto de `refresh-loop` (`null`: no es esa petición). */
	private static $rest_ctx = null;

	/** @var int Documento de `refresh-loop` si quien pide puede editarlo (vista previa del editor), o 0. */
	private static $rest_doc = 0;

	/** @var array<int,array{meta:bool,terms:bool}> */
	private static $templates = array();

	/** @var array<string,string> Motivo del vacío por widget (avisos del editor). */
	private static $notes = array();

	/** @var array<string,bool> Avisos ya impresos. */
	private static $noted = array();

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/widget/before_render_content', array( __CLASS__, 'push' ), 10, 1 );
		add_action( 'elementor/frontend/widget/after_render', array( __CLASS__, 'pop' ), 10, 1 );
		add_filter( 'elementor/query/query_args', array( __CLASS__, 'apply' ), 5, 2 );
		add_action( 'elementor/query/query_results', array( __CLASS__, 'results' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'offset' ), 1, 1 );
		add_filter( 'found_posts', array( __CLASS__, 'found' ), 1, 2 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'rest' ), 10, 3 );
	}

	/** Estado limpio (pruebas). */
	public static function reset(): void {
		self::$stack     = array();
		self::$runs      = 0;
		self::$posts     = 0;
		self::$rest_ctx  = null;
		self::$rest_doc  = 0;
		self::$templates = array();
		self::$notes     = array();
		self::$noted     = array();
	}

	/** Contadores de la petición (pruebas y diagnóstico). */
	public static function budget(): array {
		return array(
			'runs'  => self::$runs,
			'posts' => self::$posts,
			'stack' => self::$stack,
		);
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Pila de contextos.

	/** @param object $widget */
	public static function push( $widget ): void {
		if ( ! self::is_loop( $widget ) ) {
			return;
		}
		$level = ob_get_level();
		while ( self::$stack && end( self::$stack )['level'] >= $level ) {
			array_pop( self::$stack );
		}
		$nested        = ! empty( self::$stack );
		$ctx           = $nested ? (int) get_the_ID() : self::top();
		self::$stack[] = array(
			'id'    => (string) $widget->get_id(),
			'ctx'   => max( 0, $ctx ),
			'level' => $level,
		);
		if ( $nested || $ctx <= 0 || ! method_exists( $widget, 'add_render_attribute' ) ) {
			return;
		}
		$query = Store::get( self::linked( $widget ) );
		if ( null !== $query && in_array( $query['context'], array( 'current', 'root' ), true ) && self::public_post( $ctx ) ) {
			$widget->add_render_attribute( '_wrapper', 'data-digi-ctx', (string) $ctx );
		}
	}

	/** @param object $element */
	public static function pop( $element ): void {
		if ( ! self::is_loop( $element ) ) {
			return;
		}
		$id = (string) $element->get_id();
		for ( $i = count( self::$stack ) - 1; $i >= 0; $i-- ) {
			if ( self::$stack[ $i ]['id'] === $id ) {
				self::$stack = array_slice( self::$stack, 0, $i );
				return;
			}
		}
	}

	/**
	 * Contexto del nivel superior y de `root`: en `refresh-loop`, `digi_ctx` validado (o, para quien puede editar el
	 * documento, el documento: la vista previa de los filtros en el editor); si no, la entrada consultada; y en el
	 * editor (render por AJAX, sin consulta principal), la entrada del documento que se edita. Nada más: sin eso, 0.
	 */
	public static function top(): int {
		if ( null !== self::$rest_ctx ) {
			return self::$rest_ctx > 0 ? self::$rest_ctx : self::$rest_doc;
		}
		$object = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
		if ( $object instanceof \WP_Post ) {
			return (int) $object->ID;
		}
		return self::preview() ? max( 0, (int) get_the_ID() ) : 0;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Fusión de argumentos.

	/**
	 * `elementor/query/query_args` (prioridad 5).
	 *
	 * @param mixed  $args   Argumentos que construyó el grupo de PRO con los ajustes del widget (los suyos, ocultos, no
	 *                       aportan selección: `get_settings_for_display()` los da como `null`).
	 * @param object $widget Widget.
	 * @return mixed
	 */
	public static function apply( $args, $widget = null ) {
		if ( ! is_array( $args ) || ! self::is_loop( $widget ) ) {
			return $args;
		}
		$id = self::linked( $widget );
		if ( 0 === $id ) {
			return $args;
		}
		$wid   = (string) $widget->get_id();
		$query = Store::get( $id );
		$entry = self::entry( $wid );
		$depth = $entry['depth'];

		$merged = $args;
		foreach ( Args::SELECTION_KEYS as $key ) {
			unset( $merged[ $key ] );
		}
		$merged['post_status']   = 'publish';
		$merged[ self::TAG ]     = $id;
		$merged['cache_results'] = true;

		$reason = '';
		if ( null === $query || empty( $query['valid'] ) ) {
			$reason = 'missing';
		} elseif ( $depth > (int) $query['limits']['depth'] ) {
			$reason = 'depth';
		} elseif ( self::$runs >= self::MAX_RUNS || self::$posts >= self::MAX_POSTS ) {
			$reason = 'budget';
		}
		++self::$runs;

		if ( '' === $reason ) {
			$built = Args::build( $query['settings'] );
			$sel   = $built['args'];
			if ( $built['closed'] ) {
				$reason = 'closed';
			} else {
				$sel = self::relate( $sel, $query, self::context( $query['context'], $entry ) );
				if ( null === $sel ) {
					$reason = 'context';
				}
			}
			if ( '' === $reason ) {
				$merged = array_merge( $merged, $sel );
			}
		}

		$cap = null === $query ? 1 : min( (int) $query['limits']['per_page'], max( 1, self::MAX_POSTS - self::$posts ) );
		$ppp = isset( $args['posts_per_page'] ) && is_numeric( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 0;
		$merged['posts_per_page'] = ( $ppp <= 0 || $ppp > $cap ) ? $cap : $ppp;

		if ( '' !== $reason ) {
			$merged['post__in'] = array( 0 );
			self::$notes[ $wid ] = $reason;
		} else {
			unset( self::$notes[ $wid ] );
		}

		$merged['no_found_rows'] = ! self::paginated( $widget );
		$loads                   = self::template_loads( $widget );
		if ( ! $loads['meta'] ) {
			$merged['update_post_meta_cache'] = false;
		}
		if ( ! $loads['terms'] ) {
			$merged['update_post_term_cache'] = false;
		}
		return $merged;
	}

	/**
	 * Contexto de la consulta para el widget que la pinta: `current` (la entrada del bucle que lo contiene, o la de la
	 * página en el nivel superior) o `root` (la de la página). Cualquier otro valor (`parent`, que se retiró, o uno
	 * manipulado) da 0: la relación falla cerrada.
	 *
	 * @param array $entry `{ ctx, depth }` de `entry()`.
	 */
	public static function context( string $context, array $entry ): int {
		switch ( $context ) {
			case 'current':
				return $entry['ctx'];
			case 'root':
				return self::top();
		}
		return 0;
	}

	/**
	 * Añade la relación con el contexto a la selección. `null` si no hay contexto o el contexto no tiene el dato
	 * (fallar cerrado). El contexto se quita siempre de los resultados de `author`, `terms` y `meta` (como «Relacionadas»
	 * de PRO quita la entrada de la que parte).
	 */
	public static function relate( array $sel, array $query, int $ctx ): ?array {
		list( $kind, $arg ) = Store::relation_parts( (string) $query['relation'] );
		if ( 'none' === $kind ) {
			return $sel;
		}
		$post = $ctx > 0 ? get_post( $ctx ) : null;
		if ( ! is_object( $post ) ) {
			return null;
		}
		switch ( $kind ) {
			case 'author':
				$author = (int) ( $post->post_author ?? 0 );
				if ( $author <= 0 || ( isset( $sel['author__in'] ) && ! in_array( $author, array_map( 'intval', (array) $sel['author__in'] ), true ) ) || ( isset( $sel['author__not_in'] ) && in_array( $author, array_map( 'intval', (array) $sel['author__not_in'] ), true ) ) ) {
					return null;
				}
				$sel['author__in'] = array( $author );
				unset( $sel['author__not_in'] );
				break;
			case 'terms':
				if ( ! Store::taxonomy_ok( $arg ) ) {
					return null;
				}
				$terms = wp_get_post_terms( $ctx, $arg, array( 'fields' => 'tt_ids' ) );
				if ( ! is_array( $terms ) || empty( $terms ) ) {
					return null;
				}
				$clause = array(
					'taxonomy' => $arg,
					'field'    => 'term_taxonomy_id',
					'terms'    => array_map( 'intval', $terms ),
				);
				if ( empty( $sel['tax_query'] ) ) {
					$sel['tax_query'] = array( $clause );
				} else {
					$sel['tax_query']['relation'] = 'AND';
					$sel['tax_query'][]           = $clause;
				}
				break;
			case 'children':
				$sel['post_parent'] = $ctx;
				return $sel;
			case 'meta':
				if ( ! Store::meta_key_ok( $arg ) ) {
					return null;
				}
				$value = get_post_meta( $ctx, $arg, true );
				if ( ! is_scalar( $value ) || '' === (string) $value ) {
					return null;
				}
				$sel['meta_query'] = array(
					array(
						'key'     => $arg,
						'value'   => (string) $value,
						'compare' => '=',
					),
				);
				break;
			default:
				return null;
		}
		$not_in               = isset( $sel['post__not_in'] ) ? array_map( 'intval', (array) $sel['post__not_in'] ) : array();
		$not_in[]             = $ctx;
		$sel['post__not_in'] = array_values( array_unique( $not_in ) );
		return $sel;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Después de la consulta.

	/**
	 * `elementor/query/query_results`: presupuesto de entradas, `found_posts` con `no_found_rows` y aviso del editor.
	 *
	 * @param object $query  `WP_Query`.
	 * @param object $widget Widget.
	 */
	public static function results( $query, $widget = null ): void {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! $query->get( self::TAG ) ) {
			return;
		}
		$posts        = isset( $query->posts ) && is_array( $query->posts ) ? $query->posts : array();
		self::$posts += count( $posts );
		if ( $query->get( 'no_found_rows' ) ) {
			$query->found_posts   = count( $posts );
			$query->max_num_pages = $posts ? 1 : 0;
		}
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_id' ) || ! self::preview() ) {
			return;
		}
		$wid = (string) $widget->get_id();
		if ( isset( self::$notes[ $wid ] ) && empty( self::$noted[ $wid ] ) ) {
			self::$noted[ $wid ] = true;
			// Dentro del búfer de `render_content()`: el aviso queda en el contenido del widget, sólo en el editor.
			echo '<div class="elementor-alert elementor-alert-warning" role="note">' . esc_html( self::note( self::$notes[ $wid ] ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba.
		}
	}

	/** Texto del aviso del editor para cada motivo. */
	public static function note( string $reason ): string {
		$notes = array(
			'missing' => __( 'Consulta guardada: no existe, no está publicada o ya no es válida. El bucle queda vacío hasta que elijas otra o «Ninguna».', 'pro-elements' ),
			'depth'   => __( 'Consulta guardada: este bucle está anidado más hondo de lo que permite la consulta. Queda vacío.', 'pro-elements' ),
			'budget'  => __( 'Consulta guardada: la página ya ejecutó 50 consultas guardadas o pintó 300 entradas con ellas. Este bucle queda vacío.', 'pro-elements' ),
			'closed'  => __( 'Consulta guardada: los términos o autores que incluye ya no existen. Queda vacía hasta que la revises en Consultas.', 'pro-elements' ),
			'context' => __( 'Consulta guardada: aquí no hay entrada de contexto, o no tiene el dato de la relación. En esta vista previa queda vacía.', 'pro-elements' ),
		);
		return $notes[ $reason ] ?? $notes['missing'];
	}

	/**
	 * `pre_get_posts` (1): el desplazamiento de la consulta guardada, como `Elementor_Post_Query::fix_query_offset()`.
	 *
	 * @param object $query `WP_Query`.
	 */
	public static function offset( $query ): void {
		$offset = self::query_offset( $query );
		if ( $offset <= 0 ) {
			return;
		}
		if ( ! empty( $query->is_paged ) ) {
			$query->query_vars['offset'] = $offset + ( ( (int) $query->query_vars['paged'] - 1 ) * (int) $query->query_vars['posts_per_page'] );
		} else {
			$query->query_vars['offset'] = $offset;
		}
	}

	/**
	 * `found_posts` (1): como `Elementor_Post_Query::fix_query_found_posts()`.
	 *
	 * @param mixed  $found
	 * @param object $query
	 * @return mixed
	 */
	public static function found( $found, $query = null ) {
		$offset = self::query_offset( $query );
		return $offset > 0 ? max( 0, (int) $found - $offset ) : $found;
	}

	/** @param mixed $query */
	private static function query_offset( $query ): int {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return 0;
		}
		$id = (int) $query->get( self::TAG );
		if ( $id <= 0 || array( 0 ) === array_values( (array) $query->get( 'post__in' ) ) ) {
			return 0;
		}
		$saved = Store::get( $id );
		return null === $saved ? 0 : (int) $saved['settings']['offset'];
	}

	// -----------------------------------------------------------------------------------------------------------------
	// REST (`refresh-loop`).

	/**
	 * `rest_request_before_callbacks`: lee `digi_ctx` de `refresh-loop`. No cambia la respuesta.
	 *
	 * @param mixed  $response
	 * @param mixed  $handler
	 * @param object $request `WP_REST_Request`.
	 * @return mixed
	 */
	public static function rest( $response, $handler = null, $request = null ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || self::ROUTE !== untrailingslashit( (string) $request->get_route() ) ) {
			return $response;
		}
		$raw            = $request->get_param( self::PARAM );
		$id             = ( is_int( $raw ) || ( is_string( $raw ) && ctype_digit( $raw ) ) ) ? (int) $raw : 0;
		self::$rest_ctx = ( $id > 0 && self::public_post( $id ) ) ? $id : 0;
		$doc            = $request->get_param( 'post_id' );
		$doc            = is_numeric( $doc ) ? max( 0, (int) $doc ) : 0;
		self::$rest_doc = ( $doc > 0 && current_user_can( 'edit_post', $doc ) ) ? $doc : 0;
		return $response;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Utilidades.

	/** @param mixed $widget */
	public static function is_loop( $widget ): bool {
		return is_object( $widget ) && method_exists( $widget, 'get_name' ) && method_exists( $widget, 'get_id' ) && isset( self::WIDGETS[ $widget->get_name() ] );
	}

	/**
	 * ID de la consulta guardada del widget: 0 si no tiene (vacío: usa sus propios ajustes); -1 si el valor no es un ID
	 * válido (`'0'`, texto: por ejemplo, el valor que nunca coincide de un kit), que falla cerrado como una consulta
	 * que no existe, porque los ajustes propios del widget siguen ocultos. Se lee de los ajustes para mostrar: con otro
	 * skin del Loop Grid (taxonomías, productos) el control está oculto y no se aplica.
	 *
	 * @param object $widget
	 */
	public static function linked( $widget ): int {
		if ( ! method_exists( $widget, 'get_settings_for_display' ) ) {
			return 0;
		}
		$value = $widget->get_settings_for_display( Controls::CONTROL );
		if ( null === $value || '' === $value || array() === $value ) {
			return 0;
		}
		$id = ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) ? (int) $value : 0;
		return $id > 0 ? $id : -1;
	}

	/**
	 * Entrada de la pila del widget: `{ ctx, depth }`. Si no está (un render sin `before_render_content`), se calcula
	 * como si se apilara ahora. La pila sólo sirve para el `current` de un bucle anidado y para la profundidad.
	 */
	private static function entry( string $wid ): array {
		for ( $i = count( self::$stack ) - 1; $i >= 0; $i-- ) {
			if ( self::$stack[ $i ]['id'] === $wid ) {
				return array(
					'ctx'   => self::$stack[ $i ]['ctx'],
					'depth' => $i + 1,
				);
			}
		}
		$nested = ! empty( self::$stack );
		return array(
			'ctx'   => $nested ? max( 0, (int) get_the_ID() ) : self::top(),
			'depth' => count( self::$stack ) + 1,
		);
	}

	/** ¿Necesita el widget el total de resultados (paginación o plantillas alternas)? */
	private static function paginated( $widget ): bool {
		$pagination = $widget->get_settings_for_display( 'pagination_type' );
		$alternate  = $widget->get_settings_for_display( 'alternate_template' );
		return ( is_string( $pagination ) && '' !== $pagination ) || 'yes' === $alternate;
	}

	/**
	 * ¿Usa la plantilla del bucle metas o términos de cada entrada? Se mira una vez por plantilla y petición en su
	 * `_elementor_data` crudo (ya en la caché de metas: el skin lo leyó antes de consultar). Ante la duda, sí: decir
	 * que no sólo ahorra la precarga, y equivocarse sólo cuesta consultas, nunca resultados. Posts y Portfolio (sin
	 * plantilla) dejan los valores de WordPress.
	 *
	 * @return array{meta:bool,terms:bool}
	 */
	public static function template_loads( $widget ): array {
		$all = array( 'meta' => true, 'terms' => true );
		if ( ! in_array( $widget->get_name(), array( 'loop-grid', 'loop-carousel', 'digitalisimo-loop-grid', 'digitalisimo-loop-carousel' ), true ) || 'yes' === $widget->get_settings_for_display( 'alternate_template' ) ) {
			return $all;
		}
		$template = (int) $widget->get_settings_for_display( 'template_id' );
		if ( $template <= 0 ) {
			return $all;
		}
		if ( ! isset( self::$templates[ $template ] ) ) {
			$raw = get_post_meta( $template, '_elementor_data', true );
			$raw = is_array( $raw ) ? wp_json_encode( $raw ) : (string) $raw;
			if ( '' === $raw ) {
				self::$templates[ $template ] = $all;
			} else {
				$lower = strtolower( $raw );
				$has   = static function ( array $needles ) use ( $lower ): bool {
					foreach ( $needles as $needle ) {
						if ( false !== strpos( $lower, $needle ) ) {
							return true;
						}
					}
					return false;
				};
				// Un bucle anidado con consulta guardada lee términos y metas de cada entrada (su contexto).
				$nested                       = $has( array( '"digitalisimo_saved_query":"' ) );
				self::$templates[ $template ] = array(
					'meta'  => $nested || $has( array( 'featured', 'thumbnail', 'custom-field', 'meta', 'acf', 'toolset', 'pods', 'woocommerce', 'product', 'price', 'gallery' ) ),
					'terms' => $nested || $has( array( 'term', 'categor', 'post_tag', 'post-tags', 'taxonom', 'post-info', 'woocommerce', 'product' ) ),
				);
			}
		}
		return self::$templates[ $template ];
	}

	private static function public_post( int $id ): bool {
		return function_exists( 'is_post_publicly_viewable' ) ? (bool) is_post_publicly_viewable( $id ) : false;
	}

	private static function editing(): bool {
		$module = '\Digitalisimo\Elements\F7b\Module';
		return class_exists( $module ) && method_exists( $module, 'editing' ) && $module::editing();
	}

	/**
	 * ¿Editor o vista previa **de quien puede editar**? `F7b\Module::editing()` sólo mira la petición (un visitante
	 * puede añadir `?elementor-preview=`); el núcleo comprueba además el permiso (`Editor::is_edit_mode()`,
	 * `Preview::is_preview_mode()`). Sin el núcleo cargado (pruebas), decide `editing()`.
	 */
	private static function preview(): bool {
		if ( ! self::editing() ) {
			return false;
		}
		$plugin = class_exists( '\Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
		if ( ! is_object( $plugin ) ) {
			return true;
		}
		if ( isset( $plugin->editor ) && is_object( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
			return true;
		}
		$id = isset( $_GET['elementor-preview'] ) ? absint( $_GET['elementor-preview'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el núcleo comprueba el permiso.
		return $id > 0 && isset( $plugin->preview ) && is_object( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode( $id );
	}
}
