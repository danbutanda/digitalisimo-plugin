<?php
namespace Digitalisimo\Elements\F7b\Queries;

defined( 'ABSPATH' ) || exit;

/**
 * Consultas guardadas (F7b, frente C; §3.2, D7 y V.4 de la especificación): el tipo `digitalisimo_query` y sus metas.
 *
 * - **Tipo** privado (`public => false`, `show_ui => false`, sin REST, sin URL), con título y revisiones; todas sus
 *   capacidades son `edit_theme_options`. Se edita sólo en la pantalla «Consultas» (`Screen`).
 * - **Metas** (registradas con revisiones y con este mismo saneado como `sanitize_callback`, así que también lo que
 *   escribe un kit pasa por él):
 *   - `_digitalisimo_query_uid`: UUID v4, fijo desde que se crea (K reutiliza por él);
 *   - `_digitalisimo_query`: los ajustes del grupo de consulta de PRO **sin prefijo** (`post_type`, `include`,
 *     `include_term_ids`… `orderby`, `order`), con lista cerrada de claves **y de valores** (C2-25): el tipo entre los
 *     públicos de `ElementorPro\Core\Utils::get_public_post_types()`, términos por `term_taxonomy_id` (como el grupo),
 *     autores y entradas por ID, fechas `Y-m-d[ H:i]`, `orderby` de la lista del grupo, sin `by_id`, `current_query`
 *     ni `related` (para eso están el contexto y la relación);
 *   - `_digitalisimo_query_context`: `none|current|root` (sin `parent`: con profundidad ≤ 2 coincidiría siempre con
 *     `current` o con `root`; un `parent` que llegue de un kit o de un dato manipulado se lee como `none` y, con
 *     relación, deja la consulta no válida: falla cerrada);
 *   - `_digitalisimo_query_relation`: `none|author|terms:<taxonomía>|children|meta:<clave>`;
 *   - `_digitalisimo_query_limits`: `per_page` (1–100) y `depth` (1–2).
 * - **Saneado** al guardar (`strict`: además, los términos, autores y entradas deben existir) y al consultar (forma:
 *   claves, valores y topes). Lo que no pasa se descarta; un tipo de contenido que ya no es público deja la consulta
 *   como no válida, y quien la usa falla cerrado.
 * - **Lectura** memorizada por petición: `get()` hace como mucho `get_post()` y una lectura de metas (C1-19: dos
 *   consultas SQL sin caché de objetos, ninguna después). Sólo devuelve consultas publicadas: un borrador (por ejemplo,
 *   una consulta de un kit con referencias sin destino) nunca se aplica.
 */
final class Store {
	const POST_TYPE  = 'digitalisimo_query';
	const CAPABILITY = 'edit_theme_options';

	const META_UID      = '_digitalisimo_query_uid';
	const META_SETTINGS = '_digitalisimo_query';
	const META_CONTEXT  = '_digitalisimo_query_context';
	const META_RELATION = '_digitalisimo_query_relation';
	const META_LIMITS   = '_digitalisimo_query_limits';

	/** Metas propias, con su tipo para `register_post_meta()`. */
	const META = array(
		self::META_UID      => 'string',
		self::META_SETTINGS => 'array',
		self::META_CONTEXT  => 'string',
		self::META_RELATION => 'string',
		self::META_LIMITS   => 'array',
	);

	const CONTEXTS  = array( 'none', 'current', 'root' );
	const RELATIONS = array( 'none', 'author', 'terms', 'children', 'meta' );
	const INCLUDE_BY = array( 'terms', 'authors' );
	const EXCLUDE_BY = array( 'current_post', 'manual_selection', 'terms', 'authors' );
	const DATES     = array( 'anytime', 'today', 'week', 'month', 'quarter', 'year', 'exact' );
	const ORDERBY   = array( 'post_date', 'post_title', 'menu_order', 'modified', 'comment_count', 'rand' );
	const ORDER     = array( 'asc', 'desc' );

	const MAX_IDS      = 100;
	const MAX_PER_PAGE = 100;
	const MAX_DEPTH    = 2;
	const MAX_OFFSET   = 500;
	const MAX_TITLE    = 120;
	const MAX_LIST     = 200;

	/** Ajustes de una consulta nueva: los valores por defecto del grupo de PRO, sin prefijo. */
	const DEFAULTS = array(
		'post_type'        => 'post',
		'include'          => array(),
		'include_term_ids' => array(),
		'include_authors'  => array(),
		'exclude'          => array(),
		'exclude_ids'      => array(),
		'exclude_term_ids' => array(),
		'exclude_authors'  => array(),
		'avoid_duplicates' => '',
		'offset'           => 0,
		'select_date'      => 'anytime',
		'date_before'      => '',
		'date_after'       => '',
		'orderby'          => 'post_date',
		'order'            => 'desc',
	);

	const DEFAULT_LIMITS = array(
		'per_page' => 10,
		'depth'    => self::MAX_DEPTH,
	);

	/**
	 * Lista negra de nombres de metas: una sola fuente, la de V.8 en el módulo neutro de filtros (que carga siempre
	 * antes que F7b), ampliable con `digitalisimo_elements_inline_tags_denied`. La usan también los filtros y
	 * `F7b\Inline_Tags\Engine`.
	 */
	const DENIED = \Digitalisimo\Elements\Filters\Query::DENIED;

	/** @var bool */
	private static $registered = false;

	/** @var array<int,array|null> Consultas leídas en esta petición. */
	private static $memo = array();

	/** @var array<string,array>|null */
	private static $all = null;

	/** @var array<string,string>|null Tipos públicos: nombre => etiqueta. */
	private static $types = null;

	// -----------------------------------------------------------------------------------------------------------------
	// Registro.

	/** Tipo y metas. Idempotente; también lo puede llamar K antes de importar. */
	public static function register(): void {
		if ( self::$registered || ! function_exists( 'register_post_type' ) ) {
			return;
		}
		self::$registered = true;
		if ( function_exists( 'post_type_exists' ) && post_type_exists( self::POST_TYPE ) ) {
			return;
		}
		$caps = array();
		foreach ( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'create_posts' ) as $cap ) {
			$caps[ $cap ] = self::CAPABILITY;
		}
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Consultas guardadas', 'pro-elements' ),
					'singular_name' => __( 'Consulta guardada', 'pro-elements' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'hierarchical'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'delete_with_user'    => false,
				'supports'            => array( 'title', 'revisions' ),
				'capabilities'        => $caps,
				'map_meta_cap'        => false,
			)
		);
		if ( ! function_exists( 'register_post_meta' ) ) {
			return;
		}
		foreach ( self::META as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => false,
					'revisions_enabled' => true,
					'auth_callback'     => array( __CLASS__, 'can_manage' ),
					'sanitize_callback' => array( __CLASS__, 'sanitize_meta' ),
				)
			);
		}
	}

	/** ¿Puede el usuario actual crear, editar, probar y borrar consultas? */
	public static function can_manage(): bool {
		return function_exists( 'current_user_can' ) && current_user_can( self::CAPABILITY );
	}

	/**
	 * `sanitize_callback` de las metas: la misma forma que al consultar.
	 *
	 * @param mixed  $value Valor que se escribe.
	 * @param string $key   Meta.
	 * @return mixed
	 */
	public static function sanitize_meta( $value, $key = '' ) {
		switch ( $key ) {
			case self::META_UID:
				return self::is_uuid( $value ) ? strtolower( (string) $value ) : '';
			case self::META_SETTINGS:
				return self::sanitize_settings( is_array( $value ) ? $value : array() );
			case self::META_CONTEXT:
				return self::sanitize_context( $value );
			case self::META_RELATION:
				return self::sanitize_relation( $value );
			case self::META_LIMITS:
				return self::sanitize_limits( is_array( $value ) ? $value : array() );
		}
		return $value;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Saneado (listas cerradas de claves y de valores).

	/** Tipos de contenido públicos (los del grupo de PRO): nombre => etiqueta. Memorizado. */
	public static function post_types(): array {
		if ( null !== self::$types ) {
			return self::$types;
		}
		$types = array();
		if ( class_exists( '\ElementorPro\Core\Utils' ) && method_exists( '\ElementorPro\Core\Utils', 'get_public_post_types' ) ) {
			$types = (array) \ElementorPro\Core\Utils::get_public_post_types();
		} elseif ( function_exists( 'get_post_types' ) ) {
			foreach ( (array) get_post_types( array( 'show_in_nav_menus' => true ), 'objects' ) as $name => $object ) {
				$types[ $name ] = is_object( $object ) && isset( $object->label ) ? (string) $object->label : (string) $name;
			}
		}
		unset( $types[ self::POST_TYPE ], $types['by_id'], $types['current_query'], $types['related'] );
		self::$types = array();
		foreach ( $types as $name => $label ) {
			if ( is_string( $name ) && '' !== $name ) {
				self::$types[ $name ] = (string) $label;
			}
		}
		return self::$types;
	}

	/**
	 * Ajustes del grupo de consulta, sin prefijo. Siempre devuelve todas las claves de `DEFAULTS` y sólo ellas.
	 *
	 * @param array $raw    Ajustes recibidos (de la pantalla, de una meta o de un kit).
	 * @param bool  $strict Al guardar: además, términos, autores y entradas deben existir, y un «incluir por» sin IDs
	 *                      se quita (así una consulta guardada nunca incluye «por términos» con la lista vacía).
	 */
	public static function sanitize_settings( array $raw, bool $strict = false ): array {
		$out  = self::DEFAULTS;
		$type = isset( $raw['post_type'] ) && is_string( $raw['post_type'] ) ? $raw['post_type'] : '';
		// Un tipo que no es público deja la consulta sin tipo: no es válida y quien la usa falla cerrado.
		$out['post_type'] = isset( self::post_types()[ $type ] ) ? $type : '';

		$out['include']          = self::pick( $raw['include'] ?? array(), self::INCLUDE_BY );
		$out['exclude']          = self::pick( $raw['exclude'] ?? array(), self::EXCLUDE_BY );
		$out['include_term_ids'] = self::ids( $raw['include_term_ids'] ?? array() );
		$out['include_authors']  = self::ids( $raw['include_authors'] ?? array() );
		$out['exclude_ids']      = self::ids( $raw['exclude_ids'] ?? array() );
		$out['exclude_term_ids'] = self::ids( $raw['exclude_term_ids'] ?? array() );
		$out['exclude_authors']  = self::ids( $raw['exclude_authors'] ?? array() );
		$out['avoid_duplicates'] = isset( $raw['avoid_duplicates'] ) && 'yes' === $raw['avoid_duplicates'] ? 'yes' : '';
		$offset                  = isset( $raw['offset'] ) && is_numeric( $raw['offset'] ) ? (int) $raw['offset'] : 0;
		$out['offset']           = max( 0, min( self::MAX_OFFSET, $offset ) );
		$out['select_date']      = self::one( $raw['select_date'] ?? '', self::DATES, 'anytime' );
		$out['date_before']      = 'exact' === $out['select_date'] ? self::date( $raw['date_before'] ?? '' ) : '';
		$out['date_after']       = 'exact' === $out['select_date'] ? self::date( $raw['date_after'] ?? '' ) : '';
		$out['orderby']          = self::one( $raw['orderby'] ?? '', self::ORDERBY, 'post_date' );
		$out['order']            = self::one( is_string( $raw['order'] ?? null ) ? strtolower( $raw['order'] ) : '', self::ORDER, 'desc' );

		if ( $strict ) {
			$out['include_term_ids'] = array_values( array_filter( $out['include_term_ids'], array( __CLASS__, 'term_exists' ) ) );
			$out['exclude_term_ids'] = array_values( array_filter( $out['exclude_term_ids'], array( __CLASS__, 'term_exists' ) ) );
			$out['include_authors']  = array_values( array_filter( $out['include_authors'], array( __CLASS__, 'user_exists' ) ) );
			$out['exclude_authors']  = array_values( array_filter( $out['exclude_authors'], array( __CLASS__, 'user_exists' ) ) );
			$out['exclude_ids']      = array_values( array_filter( $out['exclude_ids'], array( __CLASS__, 'post_exists' ) ) );
			$pairs                   = array(
				'include' => array( 'terms' => 'include_term_ids', 'authors' => 'include_authors' ),
				'exclude' => array( 'manual_selection' => 'exclude_ids', 'terms' => 'exclude_term_ids', 'authors' => 'exclude_authors' ),
			);
			foreach ( $pairs as $group => $map ) {
				foreach ( $map as $value => $ids ) {
					if ( empty( $out[ $ids ] ) ) {
						$out[ $group ] = array_values( array_diff( $out[ $group ], array( $value ) ) );
					}
				}
			}
			if ( 'exact' === $out['select_date'] && '' === $out['date_before'] && '' === $out['date_after'] ) {
				$out['select_date'] = 'anytime';
			}
		}
		return $out;
	}

	/** @param mixed $raw */
	public static function sanitize_context( $raw ): string {
		return is_string( $raw ) && in_array( $raw, self::CONTEXTS, true ) ? $raw : 'none';
	}

	/**
	 * `none|author|children|terms:<taxonomía>|meta:<clave>`. La taxonomía debe existir y ser pública; la clave, ser
	 * una meta no protegida (sin `_` inicial) y fuera de la lista negra.
	 *
	 * @param mixed $raw
	 */
	public static function sanitize_relation( $raw ): string {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return 'none';
		}
		$parts = explode( ':', $raw, 2 );
		$kind  = $parts[0];
		$arg   = $parts[1] ?? '';
		switch ( $kind ) {
			case 'author':
			case 'children':
				return '' === $arg ? $kind : 'none';
			case 'terms':
				return self::taxonomy_ok( $arg ) ? 'terms:' . $arg : 'none';
			case 'meta':
				return self::meta_key_ok( $arg ) ? 'meta:' . $arg : 'none';
		}
		return 'none';
	}

	public static function sanitize_limits( array $raw ): array {
		$per_page = isset( $raw['per_page'] ) && is_numeric( $raw['per_page'] ) ? (int) $raw['per_page'] : self::DEFAULT_LIMITS['per_page'];
		$depth    = isset( $raw['depth'] ) && is_numeric( $raw['depth'] ) ? (int) $raw['depth'] : self::DEFAULT_LIMITS['depth'];
		return array(
			'per_page' => max( 1, min( self::MAX_PER_PAGE, $per_page ) ),
			'depth'    => max( 1, min( self::MAX_DEPTH, $depth ) ),
		);
	}

	/** Relación partida: `[ tipo, argumento ]` (`[ 'terms', 'category' ]`, `[ 'none', '' ]`). */
	public static function relation_parts( string $relation ): array {
		$parts = explode( ':', $relation, 2 );
		return array( in_array( $parts[0], self::RELATIONS, true ) ? $parts[0] : 'none', $parts[1] ?? '' );
	}

	public static function is_uuid( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}

	/** UUID v4 nuevo (el de WordPress si existe). */
	public static function uuid(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return strtolower( (string) wp_generate_uuid4() );
		}
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
	}

	/**
	 * ¿Bloquea la lista negra este nombre? Por fragmentos separados por `_` o `-`, sin distinguir mayúsculas (C2-28):
	 * `Filters\Query::denied()`, la misma regla y el mismo filtro que los filtros y las etiquetas en el texto.
	 */
	public static function denied( string $name ): bool {
		return \Digitalisimo\Elements\Filters\Query::denied( $name );
	}

	public static function meta_key_ok( string $key ): bool {
		if ( 1 !== preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/', $key ) || self::denied( $key ) ) {
			return false;
		}
		return ! function_exists( 'is_protected_meta' ) || ! is_protected_meta( $key, 'post' );
	}

	public static function taxonomy_ok( string $taxonomy ): bool {
		if ( 1 !== preg_match( '/^[a-z0-9_-]{1,32}$/', $taxonomy ) || ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}
		return ! function_exists( 'is_taxonomy_viewable' ) || is_taxonomy_viewable( $taxonomy );
	}

	/** Taxonomías públicas de un tipo: nombre => etiqueta. */
	public static function taxonomies( string $post_type = '' ): array {
		$out = array();
		if ( ! function_exists( 'get_object_taxonomies' ) ) {
			return $out;
		}
		$types = '' !== $post_type ? array( $post_type ) : array_keys( self::post_types() );
		foreach ( $types as $type ) {
			foreach ( (array) get_object_taxonomies( $type, 'objects' ) as $name => $tax ) {
				if ( is_string( $name ) && ! isset( $out[ $name ] ) && self::taxonomy_ok( $name ) ) {
					$out[ $name ] = is_object( $tax ) && isset( $tax->label ) ? (string) $tax->label : $name;
				}
			}
		}
		return $out;
	}

	/** @param mixed $raw */
	private static function pick( $raw, array $allowed ): array {
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array( $raw ) as $value ) {
			if ( is_string( $value ) && in_array( $value, $allowed, true ) && ! in_array( $value, $out, true ) ) {
				$out[] = $value;
			}
		}
		return $out;
	}

	/** @param mixed $raw */
	private static function one( $raw, array $allowed, string $default ): string {
		return is_string( $raw ) && in_array( $raw, $allowed, true ) ? $raw : $default;
	}

	/**
	 * IDs enteros positivos, sin repetir, como mucho `MAX_IDS`. Acepta una lista o una cadena separada por comas.
	 *
	 * @param mixed $raw
	 */
	public static function ids( $raw ): array {
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $value ) {
			if ( ( is_int( $value ) || ( is_string( $value ) && ctype_digit( trim( $value ) ) ) ) && (int) $value > 0 ) {
				$out[ (int) $value ] = (int) $value;
			}
			if ( count( $out ) >= self::MAX_IDS ) {
				break;
			}
		}
		return array_values( $out );
	}

	/** @param mixed $raw `Y-m-d` o `Y-m-d H:i[:s]` válida, o ''. */
	private static function date( $raw ): string {
		if ( ! is_string( $raw ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/', trim( $raw ), $m ) ) {
			return '';
		}
		if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) || ( isset( $m[4] ) && ( (int) $m[4] > 23 || (int) $m[5] > 59 ) ) ) {
			return '';
		}
		return isset( $m[4] ) ? sprintf( '%s-%s-%s %s:%s', $m[1], $m[2], $m[3], $m[4], $m[5] ) : sprintf( '%s-%s-%s', $m[1], $m[2], $m[3] );
	}

	public static function term_exists( int $tt_id ): bool {
		return function_exists( 'get_term_by' ) && is_object( get_term_by( 'term_taxonomy_id', $tt_id ) );
	}

	public static function user_exists( int $id ): bool {
		return function_exists( 'get_userdata' ) && is_object( get_userdata( $id ) );
	}

	public static function post_exists( int $id ): bool {
		return function_exists( 'get_post' ) && is_object( get_post( $id ) );
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Lectura.

	/**
	 * Una consulta, saneada. Sin `$any_status`, sólo si está publicada (lo que aplican los widgets).
	 *
	 * @return array|null `{ id, uid, title, status, settings, context, relation, limits, valid }`.
	 */
	public static function get( int $id, bool $any_status = false ): ?array {
		if ( $id <= 0 || ! function_exists( 'get_post' ) ) {
			return null;
		}
		if ( ! array_key_exists( $id, self::$memo ) ) {
			self::$memo[ $id ] = self::read( $id );
		}
		$query = self::$memo[ $id ];
		if ( null === $query || ( ! $any_status && 'publish' !== $query['status'] ) ) {
			return null;
		}
		return $query;
	}

	private static function read( int $id ): ?array {
		$post = get_post( $id );
		if ( ! is_object( $post ) || self::POST_TYPE !== ( $post->post_type ?? '' ) || ! in_array( $post->post_status ?? '', array( 'publish', 'draft' ), true ) ) {
			return null;
		}
		$meta     = function_exists( 'get_post_meta' ) ? get_post_meta( $id ) : array();
		$value    = static function ( string $key ) use ( $meta ) {
			$raw = isset( $meta[ $key ][0] ) ? $meta[ $key ][0] : null;
			return function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $raw ) : $raw;
		};
		$settings = self::sanitize_settings( is_array( $value( self::META_SETTINGS ) ) ? $value( self::META_SETTINGS ) : array() );
		$relation = self::sanitize_relation( $value( self::META_RELATION ) );
		$context  = self::sanitize_context( $value( self::META_CONTEXT ) );
		$valid    = '' !== $settings['post_type'] && ( 'none' === $relation || 'none' !== $context );
		$uid      = $value( self::META_UID );
		return array(
			'id'       => $id,
			'uid'      => self::is_uuid( $uid ) ? strtolower( $uid ) : '',
			'title'    => (string) ( $post->post_title ?? '' ),
			'status'   => (string) $post->post_status,
			'settings' => $settings,
			'context'  => 'none' === $relation ? 'none' : $context,
			'relation' => $relation,
			'limits'   => self::sanitize_limits( is_array( $value( self::META_LIMITS ) ) ? $value( self::META_LIMITS ) : array() ),
			'valid'    => $valid,
		);
	}

	/** Olvida lo leído (tras guardar o borrar, y en las pruebas). */
	public static function forget( int $id = 0 ): void {
		if ( $id > 0 ) {
			unset( self::$memo[ $id ] );
		} else {
			self::$memo  = array();
			self::$types = null;
		}
		self::$all = null;
	}

	/**
	 * Consultas del sitio para el select y la pantalla: ID => `{ title, status, modified }`, por título.
	 *
	 * @param bool $published_only Sólo publicadas (el select del editor).
	 */
	public static function all( bool $published_only = true ): array {
		$key = $published_only ? 'publish' : 'any';
		if ( isset( self::$all[ $key ] ) ) {
			return self::$all[ $key ];
		}
		$out = array();
		if ( function_exists( 'get_posts' ) ) {
			$posts = get_posts(
				array(
					'post_type'              => self::POST_TYPE,
					'post_status'            => $published_only ? 'publish' : array( 'publish', 'draft' ),
					'posts_per_page'         => self::MAX_LIST,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'suppress_filters'       => true,
				)
			);
			foreach ( (array) $posts as $post ) {
				if ( is_object( $post ) && isset( $post->ID ) ) {
					$out[ (int) $post->ID ] = array(
						'title'    => (string) $post->post_title,
						'status'   => (string) $post->post_status,
						'modified' => (string) ( $post->post_modified ?? '' ),
					);
				}
			}
		}
		self::$all         = is_array( self::$all ) ? self::$all : array();
		self::$all[ $key ] = $out;
		return $out;
	}

	/** ID de la consulta con ese UUID (K reutiliza por él), o 0. */
	public static function find_by_uid( string $uid ): int {
		if ( ! self::is_uuid( $uid ) || ! function_exists( 'get_posts' ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'publish', 'draft' ),
				'meta_key'         => self::META_UID, // phpcs:ignore WordPress.DB.SlowDBQuery -- búsqueda puntual por UUID, sólo en administración e importación.
				'meta_value'       => strtolower( $uid ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		return isset( $ids[0] ) ? (int) $ids[0] : 0;
	}

	/**
	 * Resumen de una línea («Entradas · Noticias · 6 · contexto actual · mismo autor»).
	 *
	 * @param array $query De `get()`.
	 */
	public static function summary( array $query ): string {
		$settings = $query['settings'] ?? self::DEFAULTS;
		$types    = self::post_types();
		$parts    = array( $types[ $settings['post_type'] ] ?? __( 'Tipo no disponible', 'pro-elements' ) );
		if ( in_array( 'terms', $settings['include'], true ) ) {
			$names = array();
			foreach ( array_slice( $settings['include_term_ids'], 0, 2 ) as $tt_id ) {
				$term = function_exists( 'get_term_by' ) ? get_term_by( 'term_taxonomy_id', $tt_id ) : null;
				if ( is_object( $term ) && isset( $term->name ) ) {
					$names[] = (string) $term->name;
				}
			}
			if ( count( $settings['include_term_ids'] ) > 2 ) {
				$names[] = '…';
			}
			if ( $names ) {
				$parts[] = implode( ', ', $names );
			}
		}
		if ( in_array( 'authors', $settings['include'], true ) && $settings['include_authors'] ) {
			/* translators: %d: número de autores. */
			$parts[] = sprintf( _n( '%d autor', '%d autores', count( $settings['include_authors'] ), 'pro-elements' ), count( $settings['include_authors'] ) );
		}
		$parts[] = (string) ( $query['limits']['per_page'] ?? self::DEFAULT_LIMITS['per_page'] );
		$parts[] = self::context_label( (string) ( $query['context'] ?? 'none' ) );
		$relation = self::relation_label( (string) ( $query['relation'] ?? 'none' ) );
		if ( '' !== $relation ) {
			$parts[] = $relation;
		}
		return implode( ' · ', $parts );
	}

	public static function context_label( string $context ): string {
		$labels = array(
			'none'    => __( 'sin contexto', 'pro-elements' ),
			'current' => __( 'contexto actual', 'pro-elements' ),
			'root'    => __( 'contexto raíz', 'pro-elements' ),
		);
		return $labels[ $context ] ?? $labels['none'];
	}

	public static function relation_label( string $relation ): string {
		list( $kind, $arg ) = self::relation_parts( $relation );
		switch ( $kind ) {
			case 'author':
				return __( 'mismo autor', 'pro-elements' );
			case 'children':
				return __( 'entradas hijas', 'pro-elements' );
			case 'terms':
				$tax   = function_exists( 'get_taxonomy' ) ? get_taxonomy( $arg ) : null;
				$label = is_object( $tax ) && isset( $tax->label ) ? (string) $tax->label : $arg;
				/* translators: %s: taxonomía. */
				return sprintf( __( 'mismos términos de «%s»', 'pro-elements' ), $label );
			case 'meta':
				/* translators: %s: clave del campo. */
				return sprintf( __( 'mismo valor del campo «%s»', 'pro-elements' ), $arg );
		}
		return '';
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Escritura (pantalla «Consultas»).

	/**
	 * Crea o actualiza una consulta publicada. Las metas van en `meta_input`, antes de `post_updated`: la revisión que
	 * crea WordPress lleva los ajustes nuevos.
	 *
	 * @param array $input `{ title, settings, context, relation, limits }`.
	 * @return int|\WP_Error ID o error (`forbidden`, `title`, `post_type`, `context`, `not_found`, `save`).
	 */
	public static function save( array $input, int $id = 0 ) {
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'No tienes permiso para gestionar consultas.', 'pro-elements' ) );
		}
		$title = isset( $input['title'] ) && is_string( $input['title'] ) ? trim( sanitize_text_field( $input['title'] ) ) : '';
		$title = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, self::MAX_TITLE ) : substr( $title, 0, self::MAX_TITLE );
		if ( '' === $title ) {
			return new \WP_Error( 'title', __( 'Escribe un nombre para la consulta.', 'pro-elements' ) );
		}
		$settings = self::sanitize_settings( isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array(), true );
		if ( '' === $settings['post_type'] ) {
			return new \WP_Error( 'post_type', __( 'Elige un tipo de contenido público.', 'pro-elements' ) );
		}
		$relation = self::sanitize_relation( $input['relation'] ?? 'none' );
		$raw      = $input['context'] ?? 'none';
		$context  = self::sanitize_context( $raw );
		if ( 'none' === $relation ) {
			$context = 'none';
		} elseif ( 'none' === $context ) {
			// Sin contexto elegido, el actual; un contexto desconocido (`parent`, manipulado) nunca se cambia por otro.
			if ( '' !== $raw && 'none' !== $raw ) {
				return new \WP_Error( 'context', __( 'Elige un contexto válido para la relación: actual o raíz.', 'pro-elements' ) );
			}
			$context = 'current';
		}
		$meta = array(
			self::META_SETTINGS => $settings,
			self::META_CONTEXT  => $context,
			self::META_RELATION => $relation,
			self::META_LIMITS   => self::sanitize_limits( isset( $input['limits'] ) && is_array( $input['limits'] ) ? $input['limits'] : array() ),
		);
		if ( $id > 0 ) {
			$current = self::get( $id, true );
			if ( null === $current ) {
				return new \WP_Error( 'not_found', __( 'Esa consulta no existe.', 'pro-elements' ) );
			}
			if ( '' === $current['uid'] ) {
				$meta[ self::META_UID ] = self::uuid();
			}
			$result = wp_update_post(
				array(
					'ID'          => $id,
					'post_title'  => $title,
					'post_status' => 'publish',
					'meta_input'  => $meta,
				),
				true
			);
		} else {
			$meta[ self::META_UID ] = self::uuid();
			$result                 = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_title'  => $title,
					'post_status' => 'publish',
					'meta_input'  => $meta,
				),
				true
			);
		}
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			return new \WP_Error( 'save', __( 'La consulta no se pudo guardar.', 'pro-elements' ) );
		}
		$id = (int) $result;
		self::forget( $id );
		return $id;
	}

	/**
	 * Borra una consulta sin usos (D14). Con usos, devuelve el error `in_use`. Mientras el recorrido de usos del frente
	 * A no termina, `Usages::of()` puede contar de menos y el borrado no se bloquea, como antes de F7b (V.6).
	 *
	 * @return true|\WP_Error
	 */
	public static function delete( int $id ) {
		if ( ! self::can_manage() ) {
			return new \WP_Error( 'forbidden', __( 'No tienes permiso para gestionar consultas.', 'pro-elements' ) );
		}
		if ( null === self::get( $id, true ) ) {
			return new \WP_Error( 'not_found', __( 'Esa consulta no existe.', 'pro-elements' ) );
		}
		$uses = self::uses( $id );
		if ( $uses['count'] > 0 ) {
			return new \WP_Error( 'in_use', __( 'La consulta está en uso: desvincúlala de esos documentos antes de borrarla.', 'pro-elements' ) );
		}
		if ( ! wp_delete_post( $id, true ) ) {
			return new \WP_Error( 'delete', __( 'La consulta no se pudo borrar.', 'pro-elements' ) );
		}
		self::forget( $id );
		return true;
	}

	/**
	 * Resuelve de una vez los usos de varias consultas (`Usages::prime()`, una sola consulta SQL para una lista).
	 *
	 * @param int[] $ids
	 */
	public static function prime_uses( array $ids ): void {
		$usages = '\Digitalisimo\Elements\F7b\Usages';
		if ( $ids && class_exists( $usages ) && method_exists( $usages, 'prime' ) ) {
			$usages::prime( array_map( 'intval', $ids ), defined( $usages . '::KIND_QUERY' ) ? constant( $usages . '::KIND_QUERY' ) : 'q' );
		}
	}

	/**
	 * Usos de una consulta (`F7b\Usages::of( $id, 'q' )` del frente A). Sin `Usages`, cero.
	 *
	 * @return array{count:int,posts:int[],drafts:int[]}
	 */
	public static function uses( int $id ): array {
		$usages = '\Digitalisimo\Elements\F7b\Usages';
		$empty  = array( 'count' => 0, 'posts' => array(), 'drafts' => array() );
		if ( ! class_exists( $usages ) || ! method_exists( $usages, 'of' ) ) {
			return $empty;
		}
		$kind = defined( $usages . '::KIND_QUERY' ) ? constant( $usages . '::KIND_QUERY' ) : 'q';
		$uses = $usages::of( $id, $kind );
		if ( ! is_array( $uses ) ) {
			return $empty;
		}
		return array(
			'count'  => isset( $uses['count'] ) ? max( 0, (int) $uses['count'] ) : 0,
			'posts'  => isset( $uses['posts'] ) && is_array( $uses['posts'] ) ? array_map( 'intval', $uses['posts'] ) : array(),
			'drafts' => isset( $uses['drafts'] ) && is_array( $uses['drafts'] ) ? array_map( 'intval', $uses['drafts'] ) : array(),
		);
	}
}
