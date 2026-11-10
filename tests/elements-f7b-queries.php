<?php
/**
 * F7b, frente C: consultas guardadas y contextos (V.4 de la especificación; C1-4, C1-5, C1-19, C2-13, C2-14, C2-17,
 * C2-18, C2-25, C2-26).
 *
 * Carga el constructor de consultas **real** de PRO (`modules/query-control/classes/elementor-post-query.php` y
 * `elementor-related-query.php`) y los archivos de `modules/digitalisimo-f7b/queries/`, con dobles de WordPress (un
 * `WP_Query` en memoria que entiende tipos, estados, IDs, autores, `tax_query`, `meta_query`, padres, fechas, orden,
 * paginación y desplazamiento) y de las piezas del frente A (`F7b\Module`, `Flags`, `Usages`) con sus firmas.
 *
 * Comprueba: el tipo y sus metas; el saneado por listas cerradas de claves y de valores; la fusión frente al grupo de
 * PRO con los mismos ajustes (mismos argumentos de selección y mismos resultados, también con el origen oculto en
 * «Relacionadas» o «Consulta actual»); cada contexto (`current`, `root`; `parent` retirado falla cerrado) y relación (`author`, `terms`,
 * `children`, `meta`); los límites y el presupuesto; fallar cerrado; `digi_ctx` público y privado y `data-digi-ctx`;
 * «Desvincular» sólo lee; permisos de las acciones AJAX con un suscriptor y un autor ajeno; la pantalla y «Probar»;
 * y que con la bandera apagada nada se engancha en el front.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace ElementorPro\Modules\QueryControl {
	class Module {
		public static $displayed_ids = array();
		public static function add_to_avoid_list( $ids ) {
			self::$displayed_ids = array_values( array_unique( array_merge( self::$displayed_ids, $ids ) ) );
		}
	}
}
namespace ElementorPro\Core {
	class Utils {
		public static function get_public_post_types( $args = array() ) {
			return $GLOBALS['t']['types'];
		}
	}
}
namespace Digitalisimo\Elements\F7b {
	/** Doble del frente A con las firmas del contrato. */
	class Module {
		public static $editing = false;
		public static $missing = array();
		public static function requires( array $symbols, string $who ): bool {
			foreach ( $symbols as $symbol ) {
				$function = '()' === substr( $symbol, -2 ) ? substr( $symbol, 0, -2 ) : '';
				$parts    = explode( '::', $symbol, 2 );
				$exists   = '' !== $function ? function_exists( $function ) : ( isset( $parts[1] ) ? method_exists( $parts[0], $parts[1] ) : class_exists( $symbol ) );
				if ( ! $exists ) {
					self::$missing[ $who ][] = $symbol;
					return false;
				}
			}
			return true;
		}
		public static function editing(): bool {
			return self::$editing;
		}
	}
	class Flags {
		public static $on = array();
		public static function has( string $key ): bool {
			return ! empty( self::$on[ $key ] );
		}
		public static function active( string $key ): bool {
			return self::has( $key ) || Module::editing();
		}
	}
	class Usages {
		const META          = '_digitalisimo_uses';
		const KIND_TEMPLATE = 't';
		const KIND_QUERY    = 'q';
		public static $map  = array();
		public static function of( int $id, string $kind ): array {
			return self::$map[ $kind . ':' . $id ] ?? array( 'count' => 0, 'posts' => array(), 'drafts' => array() );
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.169' );

	set_error_handler(
		function ( $severity, $message, $file, $line ) {
			throw new \ErrorException( $message, 0, $severity, $file, $line );
		}
	);

	$GLOBALS['checks'] = 0;
	function check( $ok, string $message ): void {
		if ( ! $ok ) {
			throw new \RuntimeException( 'FALLO: ' . $message );
		}
		++$GLOBALS['checks'];
	}

	// -----------------------------------------------------------------------------------------------------------------
	// WordPress en memoria.

	class WP_Post {
		public $ID;
		public $post_type     = 'post';
		public $post_status   = 'publish';
		public $post_title    = '';
		public $post_author   = 1;
		public $post_parent   = 0;
		public $post_date     = '';
		public $post_modified = '';
		public $menu_order    = 0;
		public $comment_count = 0;
		public function __construct( array $data ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
	class WP_Term {
		public $term_id;
		public $term_taxonomy_id;
		public $taxonomy;
		public $name;
		public function __construct( array $data ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	function t_reset(): void {
		$now          = time();
		$GLOBALS['t'] = array(
			'hooks'      => array(),
			'did'        => array(),
			'admin'      => false,
			'user'       => 1,
			'users'      => array(
				1 => array( 'caps' => array( 'read', 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'manage_options', 'edit_theme_options', 'unfiltered_html' ), 'display_name' => 'Ana Admin', 'user_login' => 'root', 'user_email' => 'admin@sitio.test' ),
				2 => array( 'caps' => array( 'read', 'edit_posts', 'edit_published_posts' ), 'display_name' => 'Beto Autor', 'user_login' => 'beto', 'user_email' => 'beto@sitio.test' ),
				3 => array( 'caps' => array( 'read' ), 'display_name' => 'Susana Suscriptora', 'user_login' => 'susana', 'user_email' => 'susana@sitio.test' ),
			),
			'types'      => array( 'post' => 'Entradas', 'page' => 'Páginas' ),
			'taxonomies' => array(
				'category'   => array( 'label' => 'Categorías', 'viewable' => true, 'types' => array( 'post' ) ),
				'post_tag'   => array( 'label' => 'Etiquetas', 'viewable' => true, 'types' => array( 'post' ) ),
				'secret_tax' => array( 'label' => 'Interna', 'viewable' => false, 'types' => array( 'post' ) ),
			),
			'terms'      => array(
				11 => new WP_Term( array( 'term_id' => 101, 'term_taxonomy_id' => 11, 'taxonomy' => 'category', 'name' => 'Noticias' ) ),
				12 => new WP_Term( array( 'term_id' => 102, 'term_taxonomy_id' => 12, 'taxonomy' => 'category', 'name' => 'Deportes' ) ),
				21 => new WP_Term( array( 'term_id' => 201, 'term_taxonomy_id' => 21, 'taxonomy' => 'post_tag', 'name' => 'Destacado' ) ),
			),
			'post_terms' => array(),
			'posts'      => array(),
			'meta'       => array(),
			'meta_reg'   => array(),
			'loaded'     => array(),
			'post_cache' => array(),
			'sql'        => 0,
			'queries'    => array(),
			'args'       => array(),
			'writes'     => 0,
			'revisions'  => 0,
			'queried'    => null,
			'the_id'     => 0,
			'transients' => array(),
			'registered' => array(),
			'scripts'    => array(),
			'inline'     => array(),
			'users_args' => array(),
			'terms_args' => array(),
			'now'        => $now,
			'next_id'    => 500,
			'deleted'    => array(),
			'method'     => 'POST',
		);
		$_POST = array();
		$_GET  = array();
		$_REQUEST = array();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		// Entradas: 1-8 de tipo post, 9-10 páginas (10 hija de 9), 20 privada, 30 borrador.
		$day   = 86400;
		$specs = array(
			1  => array( 'post_title' => 'Alfa', 'post_author' => 1, 'days' => 1, 'terms' => array( 'category' => array( 11 ), 'post_tag' => array( 21 ) ), 'meta' => array( 'color' => 'rojo' ) ),
			2  => array( 'post_title' => 'Beta', 'post_author' => 2, 'days' => 2, 'terms' => array( 'category' => array( 11 ) ), 'meta' => array( 'color' => 'azul' ) ),
			3  => array( 'post_title' => 'Gamma', 'post_author' => 2, 'days' => 3, 'terms' => array( 'category' => array( 12 ) ), 'meta' => array( 'color' => 'rojo' ) ),
			4  => array( 'post_title' => 'Delta', 'post_author' => 1, 'days' => 40, 'terms' => array( 'category' => array( 11 ) ), 'meta' => array( 'color' => 'rojo' ) ),
			5  => array( 'post_title' => 'Épsilon', 'post_author' => 1, 'days' => 400, 'terms' => array( 'category' => array( 12 ), 'post_tag' => array( 21 ) ) ),
			6  => array( 'post_title' => 'Zeta', 'post_author' => 2, 'days' => 5, 'terms' => array( 'category' => array( 11 ), 'post_tag' => array( 21 ) ) ),
			7  => array( 'post_title' => 'Eta', 'post_author' => 1, 'days' => 6, 'terms' => array() ),
			8  => array( 'post_title' => 'Theta', 'post_author' => 2, 'days' => 7, 'terms' => array( 'category' => array( 11 ) ), 'meta' => array( 'color' => 'azul' ) ),
			9  => array( 'post_title' => 'Página madre', 'post_type' => 'page', 'post_author' => 1, 'days' => 8 ),
			10 => array( 'post_title' => 'Página hija', 'post_type' => 'page', 'post_author' => 1, 'days' => 9, 'post_parent' => 9 ),
			11 => array( 'post_title' => 'Página hija 2', 'post_type' => 'page', 'post_author' => 2, 'days' => 10, 'post_parent' => 9 ),
			20 => array( 'post_title' => 'Privada', 'post_status' => 'private', 'post_author' => 1, 'days' => 1, 'terms' => array( 'category' => array( 11 ) ) ),
			30 => array( 'post_title' => 'Borrador', 'post_status' => 'draft', 'post_author' => 1, 'days' => 1, 'terms' => array( 'category' => array( 11 ) ) ),
		);
		foreach ( $specs as $id => $spec ) {
			$date = gmdate( 'Y-m-d H:i:s', $now - $spec['days'] * $day );
			t_post(
				array(
					'ID'            => $id,
					'post_type'     => $spec['post_type'] ?? 'post',
					'post_status'   => $spec['post_status'] ?? 'publish',
					'post_title'    => $spec['post_title'],
					'post_author'   => $spec['post_author'],
					'post_parent'   => $spec['post_parent'] ?? 0,
					'post_date'     => $date,
					'post_modified' => $date,
					'menu_order'    => $id,
					'comment_count' => 10 - $id,
				)
			);
			$GLOBALS['t']['post_terms'][ $id ] = $spec['terms'] ?? array();
			foreach ( $spec['meta'] ?? array() as $key => $value ) {
				$GLOBALS['t']['meta'][ $id ][ $key ] = array( $value );
			}
		}
		$GLOBALS['t']['loaded']     = array();
		$GLOBALS['t']['post_cache'] = array();
		$GLOBALS['t']['sql']        = 0;
		\Digitalisimo\Elements\F7b\Module::$editing = false;
		\Digitalisimo\Elements\F7b\Flags::$on       = array();
		\Digitalisimo\Elements\F7b\Usages::$map     = array();
		\ElementorPro\Modules\QueryControl\Module::$displayed_ids = array();
		if ( class_exists( '\Digitalisimo\Elements\F7b\Queries\Store', false ) ) {
			\Digitalisimo\Elements\F7b\Queries\Store::forget();
			\Digitalisimo\Elements\F7b\Queries\Runtime::reset();
			( digi_test_accessible( new \ReflectionProperty( \Digitalisimo\Elements\F7b\Queries\Store::class, 'registered' ) ) )->setValue( null, false );
		}
	}
	function t_post( array $data ): WP_Post {
		$post                                    = new WP_Post( $data );
		$GLOBALS['t']['posts'][ (int) $post->ID ] = $post;
		return $post;
	}

	// Ganchos.
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['t']['hooks'][ $hook ][ $priority ][] = array( $callback, (int) $args );
		return true;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $callback, $priority, $args );
	}
	function remove_filter( $hook, $callback, $priority = 10 ) {
		foreach ( $GLOBALS['t']['hooks'][ $hook ][ $priority ] ?? array() as $i => $entry ) {
			if ( $entry[0] === $callback ) {
				unset( $GLOBALS['t']['hooks'][ $hook ][ $priority ][ $i ] );
				return true;
			}
		}
		return false;
	}
	function remove_action( $hook, $callback, $priority = 10 ) {
		return remove_filter( $hook, $callback, $priority );
	}
	function apply_filters( $hook, $value, ...$args ) {
		$hooks = $GLOBALS['t']['hooks'][ $hook ] ?? array();
		ksort( $hooks );
		foreach ( $hooks as $entries ) {
			foreach ( $entries as $entry ) {
				$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, $entry[1] ) ) );
			}
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['t']['did'][ $hook ] = ( $GLOBALS['t']['did'][ $hook ] ?? 0 ) + 1;
		$hooks                        = $GLOBALS['t']['hooks'][ $hook ] ?? array();
		ksort( $hooks );
		foreach ( $hooks as $entries ) {
			foreach ( $entries as $entry ) {
				call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) );
			}
		}
	}
	function do_action_ref_array( $hook, $args ) {
		$hooks = $GLOBALS['t']['hooks'][ $hook ] ?? array();
		ksort( $hooks );
		foreach ( $hooks as $entries ) {
			foreach ( $entries as $entry ) {
				call_user_func_array( $entry[0], $args );
			}
		}
	}
	function did_action( $hook ) {
		return $GLOBALS['t']['did'][ $hook ] ?? 0;
	}
	function t_hooks(): array {
		$out = array();
		foreach ( $GLOBALS['t']['hooks'] as $hook => $priorities ) {
			foreach ( $priorities as $entries ) {
				if ( $entries ) {
					$out[ $hook ] = ( $out[ $hook ] ?? 0 ) + count( $entries );
				}
			}
		}
		return $out;
	}

	// Textos y escape.
	function __( $text, $domain = '' ) {
		return $text;
	}
	function esc_html__( $text, $domain = '' ) {
		return esc_html( $text );
	}
	function esc_attr__( $text, $domain = '' ) {
		return esc_attr( $text );
	}
	function _n( $single, $plural, $number, $domain = '' ) {
		return 1 === (int) $number ? $single : $plural;
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
	function sanitize_text_field( $text ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
	}
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
	function absint( $value ) {
		return abs( (int) $value );
	}
	function wp_unslash( $value ) {
		return $value;
	}
	function wp_list_pluck( $list, $field ) {
		return array_map(
			function ( $item ) use ( $field ) {
				return is_object( $item ) ? $item->$field : $item[ $field ];
			},
			(array) $list
		);
	}
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
	}
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
	function maybe_unserialize( $value ) {
		return $value;
	}
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, $decimals );
	}
	function selected( $a, $b, $echo = true ) {
		return (string) $a === (string) $b ? ' selected="selected"' : '';
	}
	function checked( $a, $b = true, $echo = true ) {
		return (string) $a === (string) $b ? ' checked="checked"' : '';
	}
	function get_option( $name, $default = false ) {
		return array( 'date_format' => 'Y-m-d', 'time_format' => 'H:i' )[ $name ] ?? $default;
	}
	function mysql2date( $format, $date ) {
		return gmdate( $format, strtotime( $date ) );
	}
	function wp_generate_uuid4() {
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000, mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
	}

	// Contexto de la petición.
	function is_admin() {
		return $GLOBALS['t']['admin'];
	}
	function wp_doing_cron() {
		return false;
	}
	function admin_url( $path = '' ) {
		return 'https://sitio.test/wp-admin/' . $path;
	}
	function plugins_url( $path, $plugin ) {
		return 'https://sitio.test/wp-content/plugins/x/' . $path;
	}
	function add_query_arg( $args, $url ) {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
	}
	function get_queried_object() {
		return $GLOBALS['t']['queried'];
	}
	function get_queried_object_id() {
		return $GLOBALS['t']['queried'] instanceof WP_Post ? $GLOBALS['t']['queried']->ID : 0;
	}
	function is_singular() {
		return $GLOBALS['t']['queried'] instanceof WP_Post;
	}
	function get_the_ID() {
		return $GLOBALS['t']['the_id'] ? $GLOBALS['t']['the_id'] : false;
	}
	function get_post_type( $post = null ) {
		$post = get_post( null === $post ? $GLOBALS['t']['the_id'] : $post );
		return $post ? $post->post_type : false;
	}
	function get_post_field( $field, $post ) {
		$post = get_post( $post );
		return $post ? $post->$field : '';
	}

	// Usuarios y permisos.
	function get_current_user_id() {
		return $GLOBALS['t']['user'];
	}
	function current_user_can( $cap, ...$args ) {
		$caps = $GLOBALS['t']['users'][ $GLOBALS['t']['user'] ]['caps'] ?? array();
		if ( 'edit_post' === $cap ) {
			$post = isset( $args[0] ) ? get_post( (int) $args[0] ) : null;
			if ( ! $post ) {
				return false;
			}
			if ( 'digitalisimo_query' === $post->post_type ) {
				return in_array( 'edit_theme_options', $caps, true );
			}
			if ( (int) $post->post_author === (int) $GLOBALS['t']['user'] ) {
				return in_array( 'edit_posts', $caps, true );
			}
			return in_array( 'edit_others_posts', $caps, true );
		}
		return in_array( $cap, $caps, true );
	}
	function get_userdata( $id ) {
		$user = $GLOBALS['t']['users'][ (int) $id ] ?? null;
		return $user ? (object) ( array( 'ID' => (int) $id ) + $user ) : false;
	}
	function get_users( $args ) {
		$GLOBALS['t']['users_args'][] = $args;
		$needle                       = strtolower( trim( (string) ( $args['search'] ?? '' ), '*' ) );
		$out                          = array();
		foreach ( $GLOBALS['t']['users'] as $id => $user ) {
			$published = false;
			foreach ( $GLOBALS['t']['posts'] as $post ) {
				if ( (int) $post->post_author === $id && 'publish' === $post->post_status && in_array( $post->post_type, (array) $args['has_published_posts'], true ) ) {
					$published = true;
				}
			}
			$haystack = '';
			foreach ( (array) ( $args['search_columns'] ?? array() ) as $column ) {
				$haystack .= ' ' . strtolower( (string) $user[ $column ] );
			}
			if ( $published && ( '' === $needle || false !== strpos( $haystack, $needle ) ) ) {
				$out[] = (object) array( 'ID' => $id, 'display_name' => $user['display_name'] );
			}
		}
		return $out;
	}

	// Tipos, taxonomías y términos.
	function register_post_type( $type, $args ) {
		$GLOBALS['t']['registered'][ $type ] = $args;
	}
	function post_type_exists( $type ) {
		return isset( $GLOBALS['t']['registered'][ $type ] );
	}
	function register_post_meta( $type, $key, $args ) {
		$GLOBALS['t']['meta_reg'][ $type ][ $key ] = $args;
		return true;
	}
	function get_post_types( $args = array(), $output = 'names' ) {
		return $GLOBALS['t']['types'];
	}
	function taxonomy_exists( $tax ) {
		return isset( $GLOBALS['t']['taxonomies'][ $tax ] );
	}
	function is_taxonomy_viewable( $tax ) {
		return ! empty( $GLOBALS['t']['taxonomies'][ $tax ]['viewable'] );
	}
	function get_taxonomy( $tax ) {
		return isset( $GLOBALS['t']['taxonomies'][ $tax ] ) ? (object) array( 'name' => $tax, 'label' => $GLOBALS['t']['taxonomies'][ $tax ]['label'] ) : false;
	}
	function get_object_taxonomies( $type, $output = 'names' ) {
		$out = array();
		foreach ( $GLOBALS['t']['taxonomies'] as $name => $tax ) {
			if ( in_array( $type, $tax['types'], true ) ) {
				$out[ $name ] = (object) array( 'name' => $name, 'label' => $tax['label'] );
			}
		}
		return $out;
	}
	function get_term_by( $field, $value ) {
		++$GLOBALS['t']['sql'];
		if ( 'term_taxonomy_id' === $field ) {
			return $GLOBALS['t']['terms'][ (int) $value ] ?? false;
		}
		return false;
	}
	function get_terms( $args ) {
		$GLOBALS['t']['terms_args'][] = $args;
		$out                          = array();
		foreach ( $GLOBALS['t']['terms'] as $term ) {
			if ( in_array( $term->taxonomy, (array) $args['taxonomy'], true ) && false !== stripos( $term->name, (string) $args['search'] ) ) {
				$out[] = $term;
			}
		}
		return $out;
	}
	function wp_get_post_terms( $id, $tax, $args = array() ) {
		++$GLOBALS['t']['sql'];
		return array_values( $GLOBALS['t']['post_terms'][ (int) $id ][ $tax ] ?? array() );
	}
	function is_protected_meta( $key, $type = '' ) {
		return '_' === substr( (string) $key, 0, 1 );
	}

	// Entradas y metas (cuentan las consultas SQL como WordPress sin caché de objetos persistente).
	function get_post( $id = null ) {
		if ( $id instanceof WP_Post ) {
			return $id;
		}
		$id = (int) $id;
		if ( ! isset( $GLOBALS['t']['posts'][ $id ] ) ) {
			return null;
		}
		if ( empty( $GLOBALS['t']['post_cache'][ $id ] ) ) {
			$GLOBALS['t']['post_cache'][ $id ] = true;
			++$GLOBALS['t']['sql'];
		}
		return $GLOBALS['t']['posts'][ $id ];
	}
	function get_post_meta( $id, $key = '', $single = false ) {
		$id = (int) $id;
		if ( empty( $GLOBALS['t']['loaded'][ $id ] ) ) {
			$GLOBALS['t']['loaded'][ $id ] = true;
			++$GLOBALS['t']['sql'];
		}
		$meta = $GLOBALS['t']['meta'][ $id ] ?? array();
		if ( '' === $key ) {
			return $meta;
		}
		if ( ! isset( $meta[ $key ] ) ) {
			return $single ? '' : array();
		}
		return $single ? $meta[ $key ][0] : $meta[ $key ];
	}
	function t_meta_input( int $id, string $type, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			$reg = $GLOBALS['t']['meta_reg'][ $type ][ $key ] ?? null;
			if ( $reg && isset( $reg['sanitize_callback'] ) ) {
				$value = call_user_func( $reg['sanitize_callback'], $value, $key, 'post', $type );
			}
			$GLOBALS['t']['meta'][ $id ][ $key ] = array( $value );
			++$GLOBALS['t']['writes'];
		}
	}
	function update_post_meta( $id, $key, $value ) {
		$post = get_post( $id );
		t_meta_input( (int) $id, $post ? $post->post_type : 'post', array( $key => $value ) );
		return true;
	}
	function wp_insert_post( $arr, $wp_error = false ) {
		$id = ++$GLOBALS['t']['next_id'];
		t_post(
			array(
				'ID'            => $id,
				'post_type'     => $arr['post_type'] ?? 'post',
				'post_status'   => $arr['post_status'] ?? 'draft',
				'post_title'    => $arr['post_title'] ?? '',
				'post_author'   => $GLOBALS['t']['user'],
				'post_date'     => gmdate( 'Y-m-d H:i:s' ),
				'post_modified' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		++$GLOBALS['t']['writes'];
		t_meta_input( $id, $arr['post_type'] ?? 'post', $arr['meta_input'] ?? array() );
		return $id;
	}
	function wp_update_post( $arr, $wp_error = false ) {
		$post = get_post( (int) $arr['ID'] );
		if ( ! $post ) {
			return $wp_error ? new WP_Error( 'invalid_post', 'x' ) : 0;
		}
		foreach ( array( 'post_title', 'post_status' ) as $field ) {
			if ( isset( $arr[ $field ] ) ) {
				$post->$field = $arr[ $field ];
			}
		}
		++$GLOBALS['t']['writes'];
		t_meta_input( $post->ID, $post->post_type, $arr['meta_input'] ?? array() );
		// `post_updated` → revisión con las metas ya escritas.
		if ( in_array( 'revisions', $GLOBALS['t']['registered'][ $post->post_type ]['supports'] ?? array(), true ) ) {
			++$GLOBALS['t']['revisions'];
		}
		return $post->ID;
	}
	function wp_delete_post( $id, $force = false ) {
		$check = apply_filters( 'pre_delete_post', null, get_post( $id ), $force );
		if ( null !== $check ) {
			return $check;
		}
		$post = get_post( $id );
		unset( $GLOBALS['t']['posts'][ (int) $id ], $GLOBALS['t']['meta'][ (int) $id ] );
		$GLOBALS['t']['deleted'][] = (int) $id;
		++$GLOBALS['t']['writes'];
		return $post;
	}
	function is_post_publicly_viewable( $id ) {
		$post = get_post( $id );
		return $post && 'publish' === $post->post_status && isset( $GLOBALS['t']['types'][ $post->post_type ] );
	}
	function get_the_title( $id ) {
		$post = get_post( $id );
		return $post ? $post->post_title : '';
	}
	function get_edit_post_link( $id, $context = 'display' ) {
		return 'https://sitio.test/wp-admin/post.php?post=' . (int) $id . '&action=edit';
	}
	function get_posts( $args ) {
		$args += array( 'posts_per_page' => 5, 'post_status' => 'publish' );
		$query = new WP_Query( $args );
		return $query->posts;
	}

	// Nonces, transitorios, scripts.
	function wp_create_nonce( $action ) {
		return 'n-' . md5( $action . '|' . $GLOBALS['t']['user'] );
	}
	function wp_verify_nonce( $nonce, $action ) {
		return $nonce === wp_create_nonce( $action ) ? 1 : false;
	}
	function wp_nonce_field( $action ) {
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $action ) ) . '">';
	}
	function get_transient( $key ) {
		return $GLOBALS['t']['transients'][ $key ] ?? false;
	}
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['t']['transients'][ $key ] = $value;
		return true;
	}
	function delete_transient( $key ) {
		unset( $GLOBALS['t']['transients'][ $key ] );
		return true;
	}
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = false ) {
		$GLOBALS['t']['scripts'][ $handle ] = array( $src, $deps, $args );
	}
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		$GLOBALS['t']['inline'][ $handle ] = $data;
		return true;
	}

	/**
	 * `WP_Query` en memoria. Como WordPress: `pre_get_posts`, `found_posts` (sin `no_found_rows`), `max_num_pages`,
	 * `offset` que manda sobre `paged`, `post__in => [0]` sin resultados, `post_type` por defecto `post`.
	 */
	class WP_Query {
		public $query;
		public $query_vars    = array();
		public $posts         = array();
		public $post_count    = 0;
		public $found_posts   = 0;
		public $max_num_pages = 0;
		public $is_paged      = false;
		public $in_the_loop   = false;

		public function __construct( $args = array() ) {
			if ( $args ) {
				$this->query( $args );
			}
		}
		public function get( $key, $default = '' ) {
			return $this->query_vars[ $key ] ?? $default;
		}
		public function set( $key, $value ) {
			$this->query_vars[ $key ] = $value;
		}
		public function query( $args ) {
			$this->query      = $args;
			$this->query_vars = $args + array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'paged'          => 0,
				'offset'         => '',
				'post__in'       => array(),
				'post__not_in'   => array(),
				'no_found_rows'  => false,
				'orderby'        => 'post_date',
				'order'          => 'DESC',
			);
			$this->is_paged   = (int) $this->query_vars['paged'] > 1;
			$GLOBALS['t']['args'][] = $args;
			$self                   = $this;
			do_action_ref_array( 'pre_get_posts', array( &$self ) );
			$q = $this->query_vars;
			++$GLOBALS['t']['sql'];
			$GLOBALS['t']['queries'][] = $q;

			$types    = 'any' === $q['post_type'] ? null : (array) $q['post_type'];
			$statuses = (array) $q['post_status'];
			$found    = array();
			foreach ( $GLOBALS['t']['posts'] as $post ) {
				if ( ( null !== $types && ! in_array( $post->post_type, $types, true ) ) || ! in_array( $post->post_status, $statuses, true ) ) {
					continue;
				}
				if ( $q['post__in'] && ! in_array( $post->ID, array_map( 'intval', (array) $q['post__in'] ), true ) ) {
					continue;
				}
				if ( $q['post__not_in'] && in_array( $post->ID, array_map( 'intval', (array) $q['post__not_in'] ), true ) ) {
					continue;
				}
				if ( ! empty( $q['author__in'] ) && ! in_array( (int) $post->post_author, array_map( 'intval', (array) $q['author__in'] ), true ) ) {
					continue;
				}
				if ( ! empty( $q['author__not_in'] ) && in_array( (int) $post->post_author, array_map( 'intval', (array) $q['author__not_in'] ), true ) ) {
					continue;
				}
				if ( isset( $q['post_parent'] ) && '' !== $q['post_parent'] && (int) $post->post_parent !== (int) $q['post_parent'] ) {
					continue;
				}
				if ( ! empty( $q['tax_query'] ) && ! self::tax( $post->ID, $q['tax_query'] ) ) {
					continue;
				}
				if ( ! empty( $q['meta_query'] ) && ! self::meta( $post->ID, $q['meta_query'] ) ) {
					continue;
				}
				if ( ! empty( $q['meta_key'] ) && ( $GLOBALS['t']['meta'][ $post->ID ][ $q['meta_key'] ][0] ?? null ) !== ( $q['meta_value'] ?? null ) ) {
					continue;
				}
				if ( ! empty( $q['date_query'] ) && ! self::date( $post, $q['date_query'] ) ) {
					continue;
				}
				if ( ! empty( $q['s'] ) && false === stripos( $post->post_title, (string) $q['s'] ) ) {
					continue;
				}
				$found[] = $post;
			}
			$map  = array( 'post_date' => 'post_date', 'date' => 'post_date', 'post_title' => 'post_title', 'title' => 'post_title', 'menu_order' => 'menu_order', 'modified' => 'post_modified', 'post_modified' => 'post_modified', 'comment_count' => 'comment_count' );
			$by   = $map[ $q['orderby'] ] ?? null;
			$desc = 'DESC' === strtoupper( (string) $q['order'] );
			if ( $by ) {
				usort(
					$found,
					function ( $a, $b ) use ( $by, $desc ) {
						$cmp = $a->$by <=> $b->$by;
						$cmp = 0 === $cmp ? $a->ID <=> $b->ID : $cmp;
						return $desc ? -$cmp : $cmp;
					}
				);
			}
			$total = count( $found );
			$ppp   = (int) $q['posts_per_page'];
			$start = '' !== $q['offset'] && null !== $q['offset'] ? (int) $q['offset'] : ( max( 1, (int) $q['paged'] ) - 1 ) * max( 0, $ppp );
			$this->posts      = $ppp > 0 ? array_slice( $found, $start, $ppp ) : array_slice( $found, $start );
			$this->post_count = count( $this->posts );
			if ( ! $q['no_found_rows'] && $this->posts ) {
				$this->found_posts   = (int) apply_filters( 'found_posts', $total, $this );
				$this->max_num_pages = $ppp > 0 ? (int) ceil( $this->found_posts / $ppp ) : 1;
			}
			if ( 'ids' === ( $q['fields'] ?? '' ) ) {
				$this->posts = array_map(
					function ( $p ) {
						return $p->ID;
					},
					$this->posts
				);
			}
			return $this->posts;
		}
		private static function tax( int $id, array $query ): bool {
			$relation = strtoupper( $query['relation'] ?? 'AND' );
			unset( $query['relation'] );
			$results = array();
			foreach ( $query as $clause ) {
				if ( isset( $clause['taxonomy'] ) ) {
					$have = array();
					foreach ( $GLOBALS['t']['post_terms'][ $id ][ $clause['taxonomy'] ] ?? array() as $tt ) {
						$have[] = 'term_id' === ( $clause['field'] ?? 'term_id' ) ? $GLOBALS['t']['terms'][ $tt ]->term_id : $tt;
					}
					$hit       = (bool) array_intersect( array_map( 'intval', (array) $clause['terms'] ), $have );
					$results[] = 'NOT IN' === ( $clause['operator'] ?? 'IN' ) ? ! $hit : $hit;
				} elseif ( is_array( $clause ) ) {
					$results[] = self::tax( $id, $clause );
				}
			}
			return 'OR' === $relation ? in_array( true, $results, true ) : ! in_array( false, $results, true );
		}
		private static function meta( int $id, array $query ): bool {
			foreach ( $query as $key => $clause ) {
				if ( 'relation' === $key ) {
					continue;
				}
				if ( ( $GLOBALS['t']['meta'][ $id ][ $clause['key'] ][0] ?? null ) !== $clause['value'] ) {
					return false;
				}
			}
			return true;
		}
		private static function date( WP_Post $post, array $query ): bool {
			$time = strtotime( $post->post_date );
			if ( isset( $query['after'] ) && $time < strtotime( $query['after'] ) ) {
				return false;
			}
			if ( isset( $query['before'] ) && $time > strtotime( $query['before'] . ( 10 === strlen( $query['before'] ) ? ' 23:59:59' : '' ) ) ) {
				return false;
			}
			return true;
		}
	}

	/**
	 * Widget con su pila de controles: aplica las condiciones como `Controls_Stack::get_settings_for_display()`
	 * (`elementor/includes/base/controls-stack.php:1445-1530`): un control oculto da `null`.
	 */
	class Fake_Widget {
		public $name;
		public $id;
		public $settings;
		public $controls  = array();
		public $attrs     = array();
		private $inject   = null;
		public function __construct( string $name, string $id, array $settings = array() ) {
			$this->name     = $name;
			$this->id       = $id;
			$this->settings = $settings;
		}
		public function get_name() {
			return $this->name;
		}
		public function get_id() {
			return $this->id;
		}
		public function get_controls( $id = null ) {
			return null === $id ? $this->controls : ( $this->controls[ $id ] ?? null );
		}
		public function start_injection( array $position ) {
			$this->inject = $position;
		}
		public function end_injection() {
			$this->inject = null;
		}
		public function add_control( $id, array $args ) {
			$args['name'] = $id;
			if ( $this->inject && isset( $this->controls[ $this->inject['of'] ] ) ) {
				$out = array();
				foreach ( $this->controls as $key => $control ) {
					if ( $key === $this->inject['of'] && 'before' === $this->inject['at'] ) {
						$out[ $id ] = $args;
					}
					$out[ $key ] = $control;
				}
				$this->controls = $out;
				$this->inject   = array( 'of' => $this->inject['of'], 'at' => 'before' );
				return;
			}
			$this->controls[ $id ] = $args;
		}
		public function update_control( $id, array $args ) {
			$this->controls[ $id ] = array_merge( $this->controls[ $id ], $args );
		}
		public function get_settings( $key = null ) {
			$values = $this->settings;
			foreach ( $this->controls as $id => $control ) {
				if ( ! array_key_exists( $id, $values ) && ! in_array( $control['type'] ?? '', array( 'raw_html', 'button', 'tabs', 'tab' ), true ) ) {
					$values[ $id ] = $control['default'] ?? '';
				}
			}
			return null === $key ? $values : ( $values[ $key ] ?? null );
		}
		public function get_settings_for_display( $key = null ) {
			$values = $this->get_settings();
			$out    = array();
			foreach ( $values as $id => $value ) {
				$out[ $id ] = isset( $this->controls[ $id ] ) && ! $this->visible( $this->controls[ $id ], $values ) ? null : $value;
			}
			return null === $key ? $out : ( $out[ $key ] ?? null );
		}
		private function visible( array $control, array $values ): bool {
			foreach ( $control['condition'] ?? array() as $key => $expected ) {
				$negative = '!' === substr( $key, -1 );
				$name     = rtrim( $key, '!' );
				if ( ! isset( $values[ $name ] ) ) {
					return false;
				}
				$value = $values[ $name ];
				if ( is_array( $expected ) && $expected ) {
					$hit = in_array( $value, $expected, true );
				} elseif ( is_array( $value ) && $value ) {
					$hit = in_array( $expected, $value, true );
				} else {
					$hit = $value === $expected;
				}
				if ( $hit === $negative ) {
					return false;
				}
			}
			return true;
		}
		public function add_render_attribute( $element, $key = null, $value = null ) {
			$this->attrs[ $element ][ $key ] = $value;
		}
	}

	/** Un widget con el grupo de consulta de PRO (los controles que importan aquí, con sus condiciones reales). */
	function t_widget( string $name, string $id, array $settings = array() ): Fake_Widget {
		$widget = new Fake_Widget( $name, $id, $settings );
		$prefix = \Digitalisimo\Elements\F7b\Queries\Runtime::WIDGETS[ $name ] . '_';
		$skin   = 'post_query' === \Digitalisimo\Elements\F7b\Queries\Runtime::WIDGETS[ $name ] ? array( '_skin' => 'post' ) : array();
		if ( $skin ) {
			$widget->controls['_skin'] = array( 'type' => 'select', 'default' => 'post', 'name' => '_skin' );
		}
		$not = array( 'post_type!' => array( 'by_id', 'current_query' ) );
		$fields = array(
			'post_type'           => array( 'type' => 'select', 'default' => 'post' ),
			'query_args'          => array( 'type' => 'tabs' ),
			'query_include'       => array( 'type' => 'tab', 'condition' => $not ),
			'posts_ids'           => array( 'type' => 'query', 'default' => array(), 'condition' => array( 'post_type' => 'by_id' ) ),
			'include'             => array( 'type' => 'select2', 'default' => array(), 'condition' => $not ),
			'include_term_ids'    => array( 'type' => 'query', 'default' => array(), 'condition' => array( 'include' => 'terms' ) + $not ),
			'related_taxonomies'  => array( 'type' => 'select2', 'default' => array(), 'condition' => array( 'include' => 'terms', 'post_type' => array( 'related' ) ) ),
			'include_authors'     => array( 'type' => 'query', 'default' => array(), 'condition' => array( 'include' => 'authors' ) + $not ),
			'query_exclude'       => array( 'type' => 'tab', 'condition' => $not ),
			'exclude'             => array( 'type' => 'select2', 'default' => array(), 'condition' => $not ),
			'exclude_ids'         => array( 'type' => 'query', 'default' => array(), 'condition' => array( 'exclude' => 'manual_selection' ) + $not ),
			'exclude_term_ids'    => array( 'type' => 'query', 'default' => array(), 'condition' => array( 'exclude' => 'terms' ) + $not ),
			'exclude_authors'     => array( 'type' => 'query', 'default' => array(), 'condition' => array( 'exclude' => 'authors' ) + $not ),
			'avoid_duplicates'    => array( 'type' => 'switcher', 'default' => '', 'condition' => $not ),
			'offset'              => array( 'type' => 'number', 'default' => 0, 'condition' => $not ),
			'related_fallback'    => array( 'type' => 'select', 'default' => 'fallback_none', 'condition' => array( 'post_type' => 'related' ) ),
			'fallback_ids'        => array( 'type' => 'query', 'default' => array(), 'condition' => array( 'post_type' => 'related', 'related_fallback' => 'fallback_by_id' ) ),
			'select_date'         => array( 'type' => 'select', 'default' => 'anytime', 'condition' => $not ),
			'date_before'         => array( 'type' => 'date_time', 'default' => '', 'condition' => array( 'select_date' => 'exact' ) + $not ),
			'date_after'          => array( 'type' => 'date_time', 'default' => '', 'condition' => array( 'select_date' => 'exact' ) + $not ),
			'orderby'             => array( 'type' => 'select', 'default' => 'post_date', 'condition' => array( 'post_type!' => 'current_query' ) ),
			'order'               => array( 'type' => 'select', 'default' => 'desc', 'condition' => array( 'post_type!' => 'current_query' ) ),
			'ignore_sticky_posts' => array( 'type' => 'switcher', 'default' => 'yes', 'condition' => array( 'post_type' => 'post' ) ),
			'query_id'            => array( 'type' => 'text', 'default' => '' ),
		);
		foreach ( $fields as $field => $control ) {
			$condition = array();
			foreach ( $control['condition'] ?? array() as $key => $value ) {
				$negative               = '!' === substr( $key, -1 );
				$condition[ $prefix . rtrim( $key, '!' ) . ( $negative ? '!' : '' ) ] = $value;
			}
			$control['condition']                 = $condition + $skin;
			$control['name']                      = $prefix . $field;
			$widget->controls[ $prefix . $field ] = $control;
		}
		$widget->controls['posts_per_page']  = array( 'type' => 'number', 'default' => 6, 'name' => 'posts_per_page' );
		$widget->controls['pagination_type'] = array( 'type' => 'select', 'default' => '', 'name' => 'pagination_type' );
		\Digitalisimo\Elements\F7b\Queries\Controls::register( $widget );
		return $widget;
	}

	/** Lo que haría PRO al pintar el widget: el constructor real con `posts_per_page` del widget. */
	function t_run( Fake_Widget $widget, array $query_args = array() ): \WP_Query {
		$prefix = \Digitalisimo\Elements\F7b\Queries\Runtime::WIDGETS[ $widget->get_name() ];
		$raw    = $widget->get_settings( $prefix . '_post_type' );
		$class  = 'related' === $raw ? '\ElementorPro\Modules\QueryControl\Classes\Elementor_Related_Query' : '\ElementorPro\Modules\QueryControl\Classes\Elementor_Post_Query';
		$query  = new $class( $widget, $prefix, $query_args + array( 'posts_per_page' => $widget->get_settings_for_display( 'posts_per_page' ) ) );
		return $query->get_query();
	}
	function t_ids( \WP_Query $query ): array {
		return array_map(
			function ( $p ) {
				return is_object( $p ) ? (int) $p->ID : (int) $p;
			},
			$query->posts
		);
	}
	function t_last(): array {
		return end( $GLOBALS['t']['queries'] );
	}

	t_reset();

	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	require $root . '/modules/query-control/classes/elementor-post-query.php';
	require $root . '/modules/query-control/classes/elementor-related-query.php';
	// Lista negra de metas: una sola fuente, la del módulo neutro de filtros (carga siempre antes que F7b).
	require $root . '/modules/digitalisimo-filters/class-query.php';
	$dir = $root . '/modules/digitalisimo-f7b/queries/';
	foreach ( array( 'store', 'args', 'runtime', 'controls', 'screen', 'module' ) as $file ) {
		require $dir . 'class-' . $file . '.php';
	}

	use Digitalisimo\Elements\F7b\Queries\Args;
	use Digitalisimo\Elements\F7b\Queries\Controls;
	use Digitalisimo\Elements\F7b\Queries\Module as Queries;
	use Digitalisimo\Elements\F7b\Queries\Runtime;
	use Digitalisimo\Elements\F7b\Queries\Screen;
	use Digitalisimo\Elements\F7b\Queries\Store;

	/** Crea una consulta publicada como administrador. */
	function t_query( array $settings, string $context = 'none', string $relation = 'none', array $limits = array(), string $title = 'Consulta' ): int {
		$user                 = $GLOBALS['t']['user'];
		$GLOBALS['t']['user'] = 1;
		$id                   = Store::save(
			array(
				'title'    => $title,
				'settings' => $settings,
				'context'  => $context,
				'relation' => $relation,
				'limits'   => $limits + array( 'per_page' => 50 ),
			)
		);
		$GLOBALS['t']['user'] = $user;
		check( is_int( $id ) && $id > 0, 'La consulta de prueba se guarda.' );
		Store::forget();
		return $id;
	}
	function t_linked( string $name, string $wid, int $query, array $settings = array() ): Fake_Widget {
		return t_widget( $name, $wid, array( Controls::CONTROL => (string) $query ) + $settings );
	}
	function t_front(): void {
		( digi_test_accessible( new ReflectionProperty( Runtime::class, 'initialized' ) ) )->setValue( null, false );
		Runtime::init();
	}

	// =================================================================================================================
	// 1. Arranque: con la bandera apagada, nada en el front; en la administración, la pantalla y el AJAX.

	t_reset();
	Queries::init();
	check( array() === t_hooks(), 'Front con la bandera a 0: el submódulo no engancha nada (ni el tipo).' );

	// =================================================================================================================
	// 2. Tipo y metas.

	t_reset();
	Store::register();
	$type = $GLOBALS['t']['registered']['digitalisimo_query'];
	check( false === $type['public'] && false === $type['show_ui'] && false === $type['show_in_rest'] && false === $type['rewrite'] && false === $type['publicly_queryable'], 'El tipo es privado, sin interfaz, sin REST ni URL.' );
	check( array( 'title', 'revisions' ) === $type['supports'], 'El tipo tiene título y revisiones.' );
	check( false === $type['map_meta_cap'] && array( 'edit_theme_options' ) === array_values( array_unique( $type['capabilities'] ) ) && isset( $type['capabilities']['edit_post'], $type['capabilities']['create_posts'] ), 'Todas sus capacidades son edit_theme_options.' );
	check( array( '_digitalisimo_query_uid', '_digitalisimo_query', '_digitalisimo_query_context', '_digitalisimo_query_relation', '_digitalisimo_query_limits' ) === array_keys( $GLOBALS['t']['meta_reg']['digitalisimo_query'] ), 'Las cinco metas de §3.2 están registradas.' );
	foreach ( $GLOBALS['t']['meta_reg']['digitalisimo_query'] as $key => $reg ) {
		check( true === $reg['single'] && false === $reg['show_in_rest'] && true === $reg['revisions_enabled'] && is_callable( $reg['sanitize_callback'] ) && is_callable( $reg['auth_callback'] ), "La meta $key tiene revisiones, saneado y permiso, sin REST." );
	}

	$id    = t_query( array( 'post_type' => 'post', 'include' => array( 'terms' ), 'include_term_ids' => array( 11 ) ), 'none', 'none', array( 'per_page' => 6 ), 'Noticias' );
	$saved = Store::get( $id );
	check( Store::is_uuid( $saved['uid'] ), 'La consulta nueva lleva un UUID v4.' );
	check( 'Noticias' === $saved['title'] && 'publish' === $saved['status'] && $saved['valid'], 'Se publica con su nombre.' );
	$uid = $saved['uid'];
	$GLOBALS['t']['user'] = 1;
	$revisions = $GLOBALS['t']['revisions'];
	check( $id === Store::save( array( 'title' => 'Noticias 2', 'settings' => array( 'post_type' => 'page' ) ), $id ), 'Se actualiza.' );
	Store::forget();
	check( $uid === Store::get( $id )['uid'] && 'page' === Store::get( $id )['settings']['post_type'], 'El UUID no cambia al actualizar.' );
	check( $GLOBALS['t']['revisions'] === $revisions + 1, 'Actualizar crea una revisión, con las metas ya escritas (meta_input).' );
	check( $id === Store::find_by_uid( strtoupper( $uid ) ) && 0 === Store::find_by_uid( 'no-es-un-uuid' ), 'find_by_uid() encuentra por UUID (sin distinguir mayúsculas).' );

	// =================================================================================================================
	// 3. Saneado: listas cerradas de claves y de valores (C2-25).

	t_reset();
	$clean = Store::sanitize_settings(
		array(
			'post_type'        => 'shop_order',
			'include'          => array( 'terms', 'evil', 'authors', 'terms' ),
			'include_term_ids' => array( '11', 'x', -3, 11, '12' ),
			'exclude'          => 'current_post',
			'avoid_duplicates' => 'si',
			'offset'           => 99999,
			'select_date'      => 'exact',
			'date_before'      => '2026-02-30',
			'date_after'       => '2026-01-15 10:30',
			'orderby'          => 'meta_value',
			'order'            => 'ASC',
			'meta_query'       => array( array( 'key' => 'x' ) ),
			'posts_per_page'   => 9999,
			'post_status'      => 'private',
		)
	);
	check( array_keys( Store::DEFAULTS ) === array_keys( $clean ), 'Sólo las claves del grupo, todas y en orden.' );
	check( '' === $clean['post_type'], 'Un tipo que no es público queda vacío (consulta no válida).' );
	check( array( 'terms', 'authors' ) === $clean['include'] && array( 'current_post' ) === $clean['exclude'], 'include/exclude sólo con valores de la lista, sin repetir.' );
	check( array( 11, 12 ) === $clean['include_term_ids'], 'IDs enteros positivos sin repetir.' );
	check( '' === $clean['avoid_duplicates'] && Store::MAX_OFFSET === $clean['offset'], 'Interruptor sólo «yes»; desplazamiento con tope.' );
	check( '' === $clean['date_before'] && '2026-01-15 10:30' === $clean['date_after'], 'Fechas válidas o vacías.' );
	check( 'post_date' === $clean['orderby'] && 'asc' === $clean['order'], 'orderby de la lista del grupo; order en minúsculas.' );
	check( ! isset( $clean['meta_query'] ) && ! isset( $clean['posts_per_page'] ) && ! isset( $clean['post_status'] ), 'Claves fuera de la lista: descartadas.' );
	check( 'post' === Store::sanitize_settings( array( 'post_type' => 'post' ) )['post_type'] && '' === Store::sanitize_settings( array( 'post_type' => 'by_id' ) )['post_type'] && '' === Store::sanitize_settings( array( 'post_type' => 'current_query' ) )['post_type'] && '' === Store::sanitize_settings( array( 'post_type' => 'related' ) )['post_type'], 'Sin «Selección manual», «Consulta actual» ni «Relacionadas».' );
	$strict = Store::sanitize_settings( array( 'post_type' => 'post', 'include' => array( 'terms', 'authors' ), 'include_term_ids' => array( 11, 999 ), 'include_authors' => array( 77 ), 'exclude' => array( 'manual_selection' ), 'exclude_ids' => array( 1, 9999 ), 'select_date' => 'exact' ), true );
	check( array( 11 ) === $strict['include_term_ids'] && array() === $strict['include_authors'] && array( 1 ) === $strict['exclude_ids'], 'Al guardar, términos, autores y entradas deben existir.' );
	check( array( 'terms' ) === $strict['include'] && 'anytime' === $strict['select_date'], 'Al guardar, un «incluir por» sin IDs se quita, y «entre fechas» sin fechas vuelve a «todas».' );
	check( 'current' === Store::sanitize_context( 'current' ) && 'root' === Store::sanitize_context( 'root' ) && 'none' === Store::sanitize_context( 'abuelo' ) && 'none' === Store::sanitize_context( array() ), 'Contexto de la lista.' );
	check( array( 'none', 'current', 'root' ) === Store::CONTEXTS && 'none' === Store::sanitize_context( 'parent' ), 'Lista cerrada none|current|root: «parent» se retiró y se lee como desconocido.' );
	check( 'author' === Store::sanitize_relation( 'author' ) && 'children' === Store::sanitize_relation( 'children' ) && 'none' === Store::sanitize_relation( 'author:1' ) && 'none' === Store::sanitize_relation( 'friends' ), 'Relaciones de la lista.' );
	check( 'terms:category' === Store::sanitize_relation( 'terms:category' ) && 'none' === Store::sanitize_relation( 'terms:secret_tax' ) && 'none' === Store::sanitize_relation( 'terms:nope' ), 'terms:<tax> sólo con taxonomías públicas existentes.' );
	check( 'meta:color' === Store::sanitize_relation( 'meta:color' ) && 'meta:keywords' === Store::sanitize_relation( 'meta:keywords' ), 'meta:<clave> pública.' );
	check( 'none' === Store::sanitize_relation( 'meta:_secret' ) && 'none' === Store::sanitize_relation( 'meta:api_key' ) && 'none' === Store::sanitize_relation( 'meta:user-email' ) && 'none' === Store::sanitize_relation( 'meta:a b' ), 'Sin metas protegidas ni de la lista negra (por fragmentos: api_key sí, keywords no).' );
	check( Store::DENIED === \Digitalisimo\Elements\Filters\Query::DENIED, 'Una sola fuente de la lista negra: Filters\\Query::DENIED.' );
	check( 'none' === Store::sanitize_relation( 'meta:correo_cliente' ) && 'none' === Store::sanitize_relation( 'meta:clave-api' ) && 'none' === Store::sanitize_relation( 'meta:telefono' ), 'La lista negra compartida también bloquea las palabras en español.' );
	add_filter(
		'digitalisimo_elements_inline_tags_denied',
		function ( $list ) {
			$list[] = 'color';
			return $list;
		}
	);
	check( 'none' === Store::sanitize_relation( 'meta:color' ) && Store::denied( 'Color_Favorito' ), 'Se amplía con el mismo filtro, digitalisimo_elements_inline_tags_denied.' );
	unset( $GLOBALS['t']['hooks']['digitalisimo_elements_inline_tags_denied'] );
	check( 'meta:color' === Store::sanitize_relation( 'meta:color' ), 'Sin el filtro, color vuelve a valer.' );
	check( array( 'per_page' => 100, 'depth' => 2 ) === Store::sanitize_limits( array( 'per_page' => 500, 'depth' => 3 ) ) && array( 'per_page' => 1, 'depth' => 1 ) === Store::sanitize_limits( array( 'per_page' => 0, 'depth' => 0 ) ), 'Topes: per_page 1–100, depth 1–2.' );
	Store::register();
	// Lo que escribe un kit pasa por el mismo saneado (`sanitize_callback`).
	$kit = wp_insert_post( array( 'post_type' => 'digitalisimo_query', 'post_status' => 'publish', 'post_title' => 'Kit', 'meta_input' => array( '_digitalisimo_query' => array( 'post_type' => 'post', 'orderby' => 'SLEEP(5)', 'evil' => 1 ), '_digitalisimo_query_relation' => 'meta:_wp_password', '_digitalisimo_query_uid' => 'x' ) ) );
	$raw = $GLOBALS['t']['meta'][ $kit ];
	check( 'post_date' === $raw['_digitalisimo_query'][0]['orderby'] && ! isset( $raw['_digitalisimo_query'][0]['evil'] ) && 'none' === $raw['_digitalisimo_query_relation'][0] && '' === $raw['_digitalisimo_query_uid'][0], 'sanitize_callback: lo que escribe un kit también se sanea.' );

	// =================================================================================================================
	// 4. Lectura memorizada: ≤ 2 consultas SQL por consulta guardada sin caché de objetos (C1-19), 0 después.

	t_reset();
	$id = t_query( array( 'post_type' => 'post' ) );
	$GLOBALS['t']['loaded']     = array();
	$GLOBALS['t']['post_cache'] = array();
	$GLOBALS['t']['sql']        = 0;
	Store::get( $id );
	check( 2 === $GLOBALS['t']['sql'], 'Primera lectura: 2 consultas (entrada y metas).' );
	Store::get( $id );
	Store::get( $id );
	check( 2 === $GLOBALS['t']['sql'], 'Lecturas siguientes en la petición: 0 consultas.' );
	check( null === Store::get( 1 ) && null === Store::get( 0 ) && null === Store::get( 999 ), 'Una entrada de otro tipo, 0 o inexistente no es una consulta.' );
	$GLOBALS['t']['posts'][ $id ]->post_status = 'draft';
	Store::forget();
	check( null === Store::get( $id ) && null !== Store::get( $id, true ), 'Un borrador no se aplica (sí se ve en la pantalla).' );

	// =================================================================================================================
	// 5. Control: select al principio de la sección, sin default, style_transfer false; grupo condicionado.

	t_reset();
	$w = t_widget( 'posts', 'aaaaaaa' );
	$keys = array_keys( $w->controls );
	check( Controls::CONTROL === $keys[0] && Controls::SUMMARY === $keys[1] && Controls::UNLINK === $keys[2] && 'posts_post_type' === $keys[3], 'Select, resumen y «Desvincular» delante del origen del grupo.' );
	$control = $w->controls[ Controls::CONTROL ];
	check( 'select' === $control['type'] && ! array_key_exists( 'default', $control ) && false === $control['style_transfer'] && array() === $control['condition'], 'Select sin default, con style_transfer false y sin condición en Posts.' );
	check( false === $w->controls[ Controls::SUMMARY ]['style_transfer'] && false === $w->controls[ Controls::UNLINK ]['style_transfer'] && 'digitalisimo:queries:unlink' === $w->controls[ Controls::UNLINK ]['event'], 'Resumen y botón con style_transfer false; el botón dispara su evento.' );
	check( array( '' => 'Ninguna' ) === $control['options'], 'En el front, las opciones no consultan nada.' );
	foreach ( Controls::GROUP_FIELDS as $field ) {
		check( '' === $w->controls[ 'posts_' . $field ]['condition'][ Controls::CONTROL ], "posts_$field se oculta con una consulta elegida." );
	}
	check( array( 'posts_post_type' => 'post' ) === $w->controls['posts_ignore_sticky_posts']['condition'] && array() === $w->controls['posts_query_id']['condition'], 'ignore_sticky_posts y query_id siguen siendo del widget (C1-5).' );
	$loop = t_widget( 'loop-grid', 'bbbbbbb' );
	check( array( '_skin' => 'post' ) === $loop->controls[ Controls::CONTROL ]['condition'] && Controls::CONTROL === array_keys( $loop->controls )[1], 'Loop Grid: sólo con el skin de entradas, delante de post_query_post_type.' );
	// C5: los bucles propios (`digitalisimo-loop-grid`, `digitalisimo-loop-carousel`) con la misma condición de skin, y
	// Entradas y Portafolio propios sin ella, como los de PRO a los que sustituyen.
	foreach ( array( 'digitalisimo-loop-grid' => array( '_skin' => 'post' ), 'digitalisimo-loop-carousel' => array( '_skin' => 'post' ), 'digitalisimo-posts' => array(), 'digitalisimo-portfolio' => array() ) as $own => $cond ) {
		$own_widget = t_widget( $own, 'c5' . substr( md5( $own ), 0, 5 ) );
		check( isset( $own_widget->controls[ Controls::CONTROL ] ) && $cond === $own_widget->controls[ Controls::CONTROL ]['condition'], "C5: $own con «Consulta guardada» (condición " . wp_json_encode( $cond ) . ').' );
	}
	$before = count( $loop->controls );
	Controls::register( $loop );
	check( count( $loop->controls ) === $before, 'Registrar dos veces no duplica nada.' );
	$GLOBALS['t']['admin'] = true;
	$opt_id                = t_query( array( 'post_type' => 'post' ), 'none', 'none', array(), 'Para el select' );
	$GLOBALS['t']['posts'][ t_query( array( 'post_type' => 'post' ), 'none', 'none', array(), 'Borrador' ) ]->post_status = 'draft';
	Store::forget();
	$options = Controls::options();
	check( 'Ninguna' === $options[''] && 'Para el select' === $options[ (string) $opt_id ] && 2 === count( $options ), 'En el editor: «Ninguna» y las consultas publicadas (sin borradores).' );
	$GLOBALS['t']['admin'] = false;
	// Valores conservados (D24): elegir una consulta sólo oculta; «Ninguna» devuelve los del widget.
	$w = t_widget( 'posts', 'ccccccc', array( 'posts_post_type' => 'page', 'posts_orderby' => 'post_title', Controls::CONTROL => '12' ) );
	check( null === $w->get_settings_for_display( 'posts_post_type' ) && null === $w->get_settings_for_display( 'posts_orderby' ) && 'page' === $w->get_settings( 'posts_post_type' ), 'Vinculado: el grupo da null para mostrar y conserva sus valores.' );
	$w->settings[ Controls::CONTROL ] = '';
	check( 'page' === $w->get_settings_for_display( 'posts_post_type' ) && 'post_title' === $w->get_settings_for_display( 'posts_orderby' ), 'Con «Ninguna», el widget vuelve a sus ajustes.' );

	// =================================================================================================================
	// 6. Fusión frente al grupo de PRO con los mismos ajustes (C1-5, criterio 5).

	t_reset();
	t_front();
	$settings_sets = array(
		'términos y orden'   => array( 'post_type' => 'post', 'include' => array( 'terms' ), 'include_term_ids' => array( 11 ), 'orderby' => 'post_title', 'order' => 'asc' ),
		'autores y exclusión' => array( 'post_type' => 'post', 'include' => array( 'authors' ), 'include_authors' => array( 2 ), 'exclude' => array( 'manual_selection', 'terms' ), 'exclude_ids' => array( 6 ), 'exclude_term_ids' => array( 12 ) ),
		'fecha y comentarios' => array( 'post_type' => 'post', 'select_date' => 'month', 'orderby' => 'comment_count', 'order' => 'desc', 'exclude' => array( 'authors' ), 'exclude_authors' => array( 2 ) ),
		'fechas exactas'     => array( 'post_type' => 'post', 'select_date' => 'exact', 'date_after' => gmdate( 'Y-m-d', time() - 6 * 86400 ), 'date_before' => gmdate( 'Y-m-d', time() ), 'orderby' => 'menu_order', 'order' => 'asc' ),
		'páginas'            => array( 'post_type' => 'page', 'orderby' => 'post_title', 'order' => 'desc' ),
		'desplazamiento'     => array( 'post_type' => 'post', 'offset' => 2, 'orderby' => 'post_date', 'order' => 'desc' ),
	);
	$n = 0;
	foreach ( $settings_sets as $label => $set ) {
		++$n;
		$prefixed = array( 'posts_per_page' => 3 );
		foreach ( $set as $key => $value ) {
			$prefixed[ 'posts_' . $key ] = $value;
		}
		$pro      = t_widget( 'posts', 'pro000' . $n, $prefixed );
		$expected = t_ids( t_run( $pro ) );
		$pro_args = t_last();
		$qid      = t_query( $set );
		// El widget vinculado tiene otros ajustes propios (ocultos): no deben aportar nada.
		$linked = t_linked( 'posts', 'sav000' . $n, $qid, array( 'posts_per_page' => 3, 'posts_post_type' => 'page', 'posts_include' => array( 'authors' ), 'posts_include_authors' => array( 1 ), 'posts_offset' => 5, 'posts_select_date' => 'today' ) );
		$got    = t_ids( t_run( $linked ) );
		$args   = t_last();
		check( array() !== $expected && $expected === $got, "Mismos resultados que el grupo de PRO ($label): " . json_encode( $expected ) . ' / ' . json_encode( $got ) );
		foreach ( array( 'post_type', 'tax_query', 'author__in', 'author__not_in', 'post__not_in', 'date_query', 'orderby', 'order', 'post_status', 'offset' ) as $key ) {
			check( ( $pro_args[ $key ] ?? null ) === ( $args[ $key ] ?? null ), "Mismo argumento $key que el grupo de PRO ($label)." );
		}
		check( (int) $args[ Runtime::TAG ] === $qid && true === $args['cache_results'], "La consulta va marcada y con cache_results ($label)." );
	}
	check( 6 === $n, 'Seis combinaciones comparadas con el grupo de PRO.' );

	// Lo que se queda del widget: paged, ignore_sticky_posts, has_custom_pagination; posts_per_page con tope.
	$qid    = t_query( array( 'post_type' => 'post' ), 'none', 'none', array( 'per_page' => 4 ) );
	$linked = t_linked( 'posts', 'keep001', $qid, array( 'posts_per_page' => 50, 'posts_ignore_sticky_posts' => '', 'pagination_type' => 'numbers' ) );
	t_run( $linked, array( 'paged' => 2, 'has_custom_pagination' => true ) );
	$args = t_last();
	check( 2 === $args['paged'] && true === $args['has_custom_pagination'] && false === $args['ignore_sticky_posts'], 'paged, has_custom_pagination e ignore_sticky_posts quedan como los puso el widget.' );
	check( 4 === $args['posts_per_page'], 'posts_per_page del widget con el per_page de la consulta como tope.' );
	check( false === $args['no_found_rows'], 'Con paginación, sin no_found_rows.' );
	$linked = t_linked( 'posts', 'keep002', $qid, array( 'posts_per_page' => 2 ) );
	$query  = t_run( $linked );
	check( 2 === t_last()['posts_per_page'] && true === t_last()['no_found_rows'], 'Por debajo del tope manda el widget; sin paginación, no_found_rows.' );
	check( 2 === $query->found_posts && 1 === $query->max_num_pages, 'Con no_found_rows, found_posts y max_num_pages coherentes (los skins de PRO no pintan con 0).' );
	$linked = t_linked( 'posts', 'keep003', $qid, array( 'posts_per_page' => -1 ) );
	t_run( $linked );
	check( 4 === t_last()['posts_per_page'], 'posts_per_page -1 («todas») también lleva el tope.' );
	$linked = t_linked( 'loop-grid', 'keep004', $qid, array( 'posts_per_page' => 3, 'alternate_template' => 'yes' ) );
	t_run( $linked );
	check( false === t_last()['no_found_rows'], 'Con plantillas alternas (cuentan con found_posts), sin no_found_rows.' );

	// Los filtros (prioridad 10) siguen aplicándose después.
	add_filter(
		'elementor/query/query_args',
		function ( $args ) {
			$args['tax_query']['relation'] = 'AND';
			$args['tax_query'][]           = array( 'taxonomy' => 'post_tag', 'field' => 'term_taxonomy_id', 'terms' => array( 21 ) );
			return $args;
		},
		10
	);
	$qid    = t_query( array( 'post_type' => 'post', 'include' => array( 'terms' ), 'include_term_ids' => array( 11 ) ) );
	$linked = t_linked( 'loop-grid', 'filt001', $qid, array( 'posts_per_page' => 10 ) );
	$ids    = t_ids( t_run( $linked ) );
	sort( $ids );
	check( array( 1, 6 ) === $ids, 'Un filtro a prioridad 10 se suma a la consulta guardada (Noticias + Destacado).' );
	unset( $GLOBALS['t']['hooks']['elementor/query/query_args'][10] );

	// Origen oculto «Relacionadas» y «Consulta actual»: la consulta guardada manda igual.
	$qid    = t_query( array( 'post_type' => 'post', 'include' => array( 'terms' ), 'include_term_ids' => array( 12 ) ) );
	$GLOBALS['t']['queried'] = get_post( 1 );
	$GLOBALS['t']['the_id']  = 1;
	$linked = t_linked( 'posts', 'rela001', $qid, array( 'posts_per_page' => 10, 'posts_post_type' => 'related', 'posts_related_fallback' => 'fallback_recent' ) );
	$ids    = t_ids( t_run( $linked ) );
	sort( $ids );
	check( array( 3, 5 ) === $ids, 'Con «Relacionadas» oculto en el widget: la consulta guardada, sin la reserva de PRO.' );
	$GLOBALS['wp_query'] = new WP_Query();
	$GLOBALS['wp_query']->query_vars = array( 'post_type' => 'page' );
	$linked = t_linked( 'posts', 'curr001', $qid, array( 'posts_per_page' => 10, 'posts_post_type' => 'current_query' ) );
	t_run( $linked );
	check( $qid === (int) t_last()[ Runtime::TAG ] && 'post' === t_last()['post_type'], 'Con «Consulta actual» oculto: pasa por el filtro y se aplica la guardada.' );
	$GLOBALS['t']['queried'] = null;
	$GLOBALS['t']['the_id']  = 0;

	// Fallar cerrado donde PRO amplía: términos incluidos que ya no existen.
	$qid = t_query( array( 'post_type' => 'post', 'include' => array( 'terms' ), 'include_term_ids' => array( 12 ) ) );
	unset( $GLOBALS['t']['terms'][12] );
	$pro = t_widget( 'posts', 'gone001', array( 'posts_post_type' => 'post', 'posts_include' => array( 'terms' ), 'posts_include_term_ids' => array( 12 ), 'posts_per_page' => 10 ) );
	check( count( t_ids( t_run( $pro ) ) ) > 2, 'El grupo de PRO, con el término borrado, muestra todas las entradas.' );
	$linked = t_linked( 'posts', 'gone002', $qid, array( 'posts_per_page' => 10 ) );
	check( array() === t_ids( t_run( $linked ) ) && array( 0 ) === t_last()['post__in'], 'La consulta guardada falla cerrada (post__in [0]).' );
	check( array( 'args', 'closed' ) === array_keys( Args::build( Store::sanitize_settings( array( 'post_type' => 'post', 'include' => array( 'authors' ) ) ) ) ) && true === Args::build( Store::sanitize_settings( array( 'post_type' => 'post', 'include' => array( 'authors' ) ) ) )['closed'], '«Por autores» sin autores: cerrada.' );

	// Fallar cerrado: consulta inexistente, borrador o no válida.
	foreach ( array( 'inexistente' => 99999, 'entrada normal' => 1 ) as $label => $missing ) {
		$linked = t_linked( 'posts', 'miss' . substr( md5( $label ), 0, 3 ), $missing, array( 'posts_per_page' => 10 ) );
		check( array() === t_ids( t_run( $linked ) ) && array( 0 ) === t_last()['post__in'], "Consulta $label: vacía." );
	}
	// Un valor que no es un ID (el «nunca coincide» de un kit, '0', texto): los ajustes propios siguen ocultos, así que
	// falla cerrado en lugar de consultar sin selección.
	foreach ( array( '0', 'x', '-3', '12abc' ) as $n => $never ) {
		$linked = t_widget( 'posts', 'nevr00' . $n, array( Controls::CONTROL => $never, 'posts_per_page' => 10, 'posts_post_type' => 'page' ) );
		check( null === $linked->get_settings_for_display( 'posts_post_type' ) && -1 === Runtime::linked( $linked ), "Valor «{$never}»: vinculado pero no válido." );
		check( array() === t_ids( t_run( $linked ) ) && array( 0 ) === t_last()['post__in'], "Valor «{$never}»: vacía, nunca todas las entradas." );
	}
	$qid = t_query( array( 'post_type' => 'post' ) );
	$GLOBALS['t']['posts'][ $qid ]->post_status = 'draft';
	Store::forget();
	check( array() === t_ids( t_run( t_linked( 'posts', 'draf001', $qid, array( 'posts_per_page' => 10 ) ) ) ), 'Consulta en borrador: vacía.' );
	$qid = t_query( array( 'post_type' => 'post' ) );
	unset( $GLOBALS['t']['types']['post'] );
	Store::forget();
	check( array() === t_ids( t_run( t_linked( 'posts', 'type001', $qid, array( 'posts_per_page' => 10 ) ) ) ), 'Consulta cuyo tipo ya no es público: vacía.' );

	// En el editor, el bucle vacío lo explica (una vez por widget, dentro de su contenido).
	\Digitalisimo\Elements\F7b\Module::$editing = true;
	ob_start();
	$note = t_linked( 'posts', 'note001', 99999, array( 'posts_per_page' => 10 ) );
	t_run( $note );
	t_run( $note );
	$out = ob_get_clean();
	check( 1 === substr_count( $out, 'elementor-alert elementor-alert-warning' ) && false !== strpos( $out, 'no existe, no está publicada' ), 'Editor: aviso de la consulta que falla, una sola vez.' );
	$GLOBALS['t']['queried'] = null;
	$GLOBALS['t']['the_id']  = 9;
	check( 9 === Runtime::top(), 'Editor sin consulta principal (render por AJAX): el contexto es la entrada del documento.' );
	\Digitalisimo\Elements\F7b\Module::$editing = false;
	ob_start();
	t_run( t_linked( 'posts', 'note002', 99999, array( 'posts_per_page' => 10 ) ) );
	check( '' === ob_get_clean() && 0 === Runtime::top(), 'Fuera del editor, ni aviso ni contexto por defecto.' );
	$GLOBALS['t']['the_id'] = 0;

	// Widget sin consulta: nada cambia.
	t_reset();
	t_front();
	$plain = t_widget( 'posts', 'plain01', array( 'posts_post_type' => 'post', 'posts_per_page' => 4 ) );
	t_run( $plain );
	$plain_args = end( $GLOBALS['t']['args'] );
	check( ! isset( $plain_args[ Runtime::TAG ] ) && ! isset( $plain_args['no_found_rows'] ) && ! isset( $plain_args['cache_results'] ) && 'post' === $plain_args['post_type'], 'Un widget sin consulta guardada llega a WP_Query exactamente como antes.' );
	$other = new Fake_Widget( 'archive-posts', 'arch001', array( Controls::CONTROL => '5' ) );
	check( array( 'x' => 1 ) === Runtime::apply( array( 'x' => 1 ), $other ), 'Otros widgets: el filtro no toca nada.' );

	// =================================================================================================================
	// 7. Contextos y relaciones (D9, V.4).

	t_reset();
	t_front();
	$author  = t_query( array( 'post_type' => 'post', 'orderby' => 'post_title', 'order' => 'asc' ), 'current', 'author' );
	$terms   = t_query( array( 'post_type' => 'post', 'orderby' => 'post_title', 'order' => 'asc' ), 'current', 'terms:category' );
	$rootq   = t_query( array( 'post_type' => 'post', 'orderby' => 'post_title', 'order' => 'asc' ), 'root', 'meta:color' );
	$kids    = t_query( array( 'post_type' => 'page', 'orderby' => 'post_title', 'order' => 'asc' ), 'current', 'children' );
	$depth1  = t_query( array( 'post_type' => 'post' ), 'current', 'author', array( 'depth' => 1 ) );
	// «parent» guardado como lo dejaría un kit o un dato manipulado: por la meta saneada y escrito a mano en la base.
	Store::register();
	$parent = wp_insert_post( array( 'post_type' => 'digitalisimo_query', 'post_status' => 'publish', 'post_title' => 'Padre', 'meta_input' => array( '_digitalisimo_query' => array( 'post_type' => 'post' ), '_digitalisimo_query_context' => 'parent', '_digitalisimo_query_relation' => 'author', '_digitalisimo_query_limits' => array( 'per_page' => 50 ) ) ) );
	check( 'none' === $GLOBALS['t']['meta'][ $parent ]['_digitalisimo_query_context'][0], 'Un «parent» que escribe un kit se sanea a none.' );
	$forged = wp_insert_post( array( 'post_type' => 'digitalisimo_query', 'post_status' => 'publish', 'post_title' => 'Forjada', 'meta_input' => array( '_digitalisimo_query' => array( 'post_type' => 'post' ), '_digitalisimo_query_relation' => 'author', '_digitalisimo_query_limits' => array( 'per_page' => 50 ) ) ) );
	$GLOBALS['t']['meta'][ $forged ]['_digitalisimo_query_context'] = array( 'parent' );
	Store::forget();
	check( false === Store::get( $parent )['valid'] && false === Store::get( $forged )['valid'] && 'none' === Store::get( $forged )['context'], '«parent» con relación (saneado o escrito a mano): consulta no válida.' );

	// current en el nivel superior: la entrada consultada (2, de Beto).
	$GLOBALS['t']['queried'] = get_post( 2 );
	$GLOBALS['t']['the_id']  = 2;
	$w = t_linked( 'posts', 'ctx0001', $author, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w );
	check( array( 3, 8, 6 ) === t_ids( t_run( $w ) ), 'current + author: las de su autor, sin la propia (Gamma, Theta, Zeta por título).' );
	check( array( 2 ) === t_last()['author__in'] && in_array( 2, t_last()['post__not_in'], true ), 'author__in del contexto y el contexto fuera.' );
	do_action( 'elementor/frontend/widget/after_render', $w );
	check( array() === Runtime::budget()['stack'], 'after_render desapila (C1-4).' );

	$w = t_linked( 'posts', 'ctx0002', $terms, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w );
	$ids = t_ids( t_run( $w ) );
	check( array( 1, 4, 8, 6 ) === $ids, 'current + terms:category: mismos términos (Noticias), sin la propia: ' . json_encode( $ids ) );
	check( array( array( 'taxonomy' => 'category', 'field' => 'term_taxonomy_id', 'terms' => array( 11 ) ) ) === t_last()['tax_query'], 'tax_query con los term_taxonomy_id del contexto.' );
	do_action( 'elementor/frontend/widget/after_render', $w );

	// root + meta: el valor del campo en la entrada de la página (2: azul).
	$w = t_linked( 'posts', 'ctx0003', $rootq, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w );
	check( array( 8 ) === t_ids( t_run( $w ) ), 'root + meta:color: mismo valor (azul), sin la propia.' );
	check( array( array( 'key' => 'color', 'value' => 'azul', 'compare' => '=' ) ) === t_last()['meta_query'], 'meta_query con el valor del contexto.' );
	do_action( 'elementor/frontend/widget/after_render', $w );

	// children.
	$GLOBALS['t']['queried'] = get_post( 9 );
	$GLOBALS['t']['the_id']  = 9;
	$w = t_linked( 'posts', 'ctx0004', $kids, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w );
	check( array( 10, 11 ) === t_ids( t_run( $w ) ) && 9 === t_last()['post_parent'], 'current + children: las hijas (post_parent).' );
	do_action( 'elementor/frontend/widget/after_render', $w );

	// Bucle en bucle: current = la entrada del bucle exterior que se pinta; «parent» falla cerrado en cualquier nivel.
	$GLOBALS['t']['queried'] = get_post( 7 );
	$GLOBALS['t']['the_id']  = 7;
	$outer = t_widget( 'loop-grid', 'outer01', array( 'posts_per_page' => 5 ) );
	do_action( 'elementor/widget/before_render_content', $outer );
	ob_start(); // Lo de dentro de un bucle se pinta en un búfer más hondo.
	$GLOBALS['t']['the_id'] = 3; // Entrada del bucle exterior (Gamma, de Beto).
	$inner = t_linked( 'loop-grid', 'inner01', $author, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $inner );
	$stack = Runtime::budget()['stack'];
	check( 2 === count( $stack ) && 7 === $stack[0]['ctx'] && 3 === $stack[1]['ctx'], 'La pila: la página (7) y la entrada del bucle exterior (3).' );
	check( array( 2, 8, 6 ) === t_ids( t_run( $inner ) ) && array( 2 ) === t_last()['author__in'], 'current dentro de un bucle: la entrada que se pinta (3, de Beto), sin ella.' );
	$nested_parent = t_linked( 'loop-grid', 'inner02', $parent, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $nested_parent );
	check( 2 === count( Runtime::budget()['stack'] ), 'Un bucle hermano al mismo nivel sustituye al anterior en la pila.' );
	check( array() === t_ids( t_run( $nested_parent ) ) && array( 0 ) === t_last()['post__in'], '«parent» dentro de un bucle: vacía (post__in [0]).' );
	check( 0 === Runtime::context( 'parent', array( 'ctx' => 3, 'depth' => 2 ) ) && 3 === Runtime::context( 'current', array( 'ctx' => 3, 'depth' => 2 ) ), 'Runtime::context(): «parent» (o cualquier otro) da 0; current, la entrada del bucle.' );
	$deep = t_linked( 'loop-grid', 'inner03', $depth1, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $deep );
	check( array() === t_ids( t_run( $deep ) ), 'Una consulta de profundidad 1 dentro de otro bucle: vacía.' );
	ob_start();
	$GLOBALS['t']['the_id'] = 4;
	$third = t_linked( 'loop-grid', 'third01', $author, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $third );
	check( 3 === count( Runtime::budget()['stack'] ) && array() === t_ids( t_run( $third ) ), 'Tercer nivel (profundidad > 2): vacía.' );
	ob_end_clean();
	ob_end_clean();
	do_action( 'elementor/frontend/widget/after_render', $outer );
	check( array() === Runtime::budget()['stack'], 'Al terminar el bucle exterior, la pila queda vacía.' );

	// Sin contexto: archivo de categoría (objeto consultado término) o sin consulta.
	$GLOBALS['t']['queried'] = $GLOBALS['t']['terms'][11];
	$GLOBALS['t']['the_id']  = 1;
	foreach ( array( $author, $terms, $rootq ) as $q ) {
		$w = t_linked( 'posts', 'noctx' . $q, $q, array( 'posts_per_page' => 10 ) );
		do_action( 'elementor/widget/before_render_content', $w );
		check( array() === t_ids( t_run( $w ) ) && array( 0 ) === t_last()['post__in'], "Sin entrada consultada, la consulta $q con contexto falla cerrada." );
		do_action( 'elementor/frontend/widget/after_render', $w );
	}
	$GLOBALS['t']['queried'] = get_post( 2 );
	foreach ( array( $parent, $forged ) as $n => $q ) {
		$top = t_linked( 'posts', 'nopar0' . $n, $q, array( 'posts_per_page' => 10 ) );
		do_action( 'elementor/widget/before_render_content', $top );
		check( array() === t_ids( t_run( $top ) ) && array( 0 ) === t_last()['post__in'], '«parent» en el nivel superior, con entrada consultada: vacía (post__in [0]).' );
		check( ! isset( $top->attrs['_wrapper'] ), '«parent» no lleva data-digi-ctx.' );
		do_action( 'elementor/frontend/widget/after_render', $top );
	}
	// El contexto no tiene el dato de la relación.
	$GLOBALS['t']['queried'] = get_post( 7 );
	$w = t_linked( 'posts', 'nodat01', $terms, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w );
	check( array() === t_ids( t_run( $w ) ), 'Contexto sin términos de la taxonomía: vacía.' );
	do_action( 'elementor/frontend/widget/after_render', $w );
	$w = t_linked( 'posts', 'nodat02', $rootq, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w );
	check( array() === t_ids( t_run( $w ) ), 'Contexto sin el campo: vacía.' );
	do_action( 'elementor/frontend/widget/after_render', $w );
	$mix = t_query( array( 'post_type' => 'post', 'include' => array( 'authors' ), 'include_authors' => array( 1 ) ), 'current', 'author' );
	$GLOBALS['t']['queried'] = get_post( 2 );
	$w = t_linked( 'posts', 'nodat03', $mix, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w );
	check( array() === t_ids( t_run( $w ) ), 'author del contexto fuera de los autores incluidos: vacía (nunca amplía).' );
	do_action( 'elementor/frontend/widget/after_render', $w );

	// =================================================================================================================
	// 8. Presupuesto por petición (C2-14).

	t_reset();
	t_front();
	$qid = t_query( array( 'post_type' => 'post' ), 'none', 'none', array( 'per_page' => 100 ) );
	for ( $i = 1; $i <= Runtime::MAX_RUNS; $i++ ) {
		t_run( t_linked( 'posts', sprintf( 'r%06d', $i ), $qid, array( 'posts_per_page' => 1 ) ) );
	}
	check( Runtime::MAX_RUNS === Runtime::budget()['runs'] && Runtime::MAX_RUNS === Runtime::budget()['posts'], '50 ejecuciones con una entrada cada una.' );
	check( array() === t_ids( t_run( t_linked( 'posts', 'r000051', $qid, array( 'posts_per_page' => 1 ) ) ) ), 'La ejecución 51: vacía.' );
	Runtime::reset();
	t_post( array( 'ID' => 1000, 'post_title' => 'x' ) );
	for ( $i = 1001; $i < 1400; $i++ ) {
		t_post( array( 'ID' => $i, 'post_title' => 'p' . $i, 'post_date' => gmdate( 'Y-m-d H:i:s', time() - $i ) ) );
	}
	Store::forget();
	$counts = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$counts[] = count( t_ids( t_run( t_linked( 'posts', sprintf( 'p%06d', $i ), $qid, array( 'posts_per_page' => 100 ) ) ) ) );
	}
	check( array( 100, 100, 100, 0 ) === $counts && Runtime::MAX_POSTS === Runtime::budget()['posts'], '≤ 300 entradas pintadas por consultas guardadas: ' . json_encode( $counts ) );
	Runtime::reset();
	t_run( t_linked( 'posts', 'q000001', $qid, array( 'posts_per_page' => 100 ) ) );
	t_run( t_linked( 'posts', 'q000002', $qid, array( 'posts_per_page' => 100 ) ) );
	t_run( t_linked( 'posts', 'q000003', $qid, array( 'posts_per_page' => 150 ) ) );
	check( 100 === t_last()['posts_per_page'] && 300 === Runtime::budget()['posts'], 'El tope por consulta (100) y el resto del presupuesto limitan posts_per_page.' );
	Runtime::reset();
	t_run( t_linked( 'posts', 'q000004', $qid, array( 'posts_per_page' => 100 ) ) );
	t_run( t_linked( 'posts', 'q000005', $qid, array( 'posts_per_page' => 100 ) ) );
	t_run( t_linked( 'posts', 'q000006', $qid, array( 'posts_per_page' => 60 ) ) );
	t_run( t_linked( 'posts', 'q000007', $qid, array( 'posts_per_page' => 100 ) ) );
	check( 40 === t_last()['posts_per_page'], 'La última consulta sólo pide lo que queda del presupuesto (40).' );

	// =================================================================================================================
	// 9. Precarga sólo si la plantilla del bucle usa metas o términos (D9).

	t_reset();
	t_front();
	$qid = t_query( array( 'post_type' => 'post' ) );
	$GLOBALS['t']['meta'][900]['_elementor_data'] = array( '[{"elType":"widget","widgetType":"heading","settings":{"title":"Hola"}}]' );
	$GLOBALS['t']['meta'][901]['_elementor_data'] = array( '[{"elType":"widget","widgetType":"theme-post-featured-image","settings":{}}]' );
	$GLOBALS['t']['meta'][902]['_elementor_data'] = array( '[{"elType":"widget","widgetType":"post-info","settings":{}}]' );
	$GLOBALS['t']['meta'][903]['_elementor_data'] = array( '[{"elType":"widget","widgetType":"loop-grid","settings":{"digitalisimo_saved_query":"5"}}]' );
	$cases = array( 900 => array( false, false ), 901 => array( true, false ), 902 => array( false, true ), 903 => array( true, true ) );
	foreach ( $cases as $template => $expect ) {
		t_run( t_linked( 'loop-grid', 'tpl' . $template . '0', $qid, array( 'posts_per_page' => 3, 'template_id' => $template ) ) );
		$args = t_last();
		check( $expect[0] === ! isset( $args['update_post_meta_cache'] ) && $expect[1] === ! isset( $args['update_post_term_cache'] ), "Plantilla $template: precarga de metas " . ( $expect[0] ? 'sí' : 'no' ) . ', de términos ' . ( $expect[1] ? 'sí' : 'no' ) . '.' );
	}
	t_run( t_linked( 'posts', 'tplpost', $qid, array( 'posts_per_page' => 3 ) ) );
	check( ! isset( t_last()['update_post_meta_cache'] ) && ! isset( t_last()['update_post_term_cache'] ), 'Posts y Portfolio (sin plantilla) dejan la precarga de WordPress.' );

	// Desplazamiento de la consulta con paginación: como fix_query_offset() y fix_query_found_posts() de PRO.
	$off = t_query( array( 'post_type' => 'post', 'offset' => 2, 'orderby' => 'post_date', 'order' => 'desc' ), 'none', 'none', array( 'per_page' => 2 ) );
	$q   = t_run( t_linked( 'posts', 'offs001', $off, array( 'posts_per_page' => 2, 'pagination_type' => 'numbers' ) ), array( 'paged' => 2 ) );
	check( 4 === t_last()['offset'] && array( 7, 8 ) === t_ids( $q ), 'Página 2 con desplazamiento 2: offset = 2 + (2 − 1) × 2 = 4.' );
	check( 6 === $q->found_posts && 3 === $q->max_num_pages, 'found_posts descuenta el desplazamiento (8 − 2).' );

	// =================================================================================================================
	// 10. REST: digi_ctx (C2-13) y data-digi-ctx.

	t_reset();
	t_front();
	$author = t_query( array( 'post_type' => 'post' ), 'current', 'author' );
	$rootq  = t_query( array( 'post_type' => 'post' ), 'root', 'author' );
	$plainq = t_query( array( 'post_type' => 'post' ) );
	$request = new class() {
		public $params = array();
		public $route  = '/elementor-pro/v1/refresh-loop';
		public function get_route() {
			return $this->route;
		}
		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}
	};
	$sentinel = new stdClass();
	$request->params = array( 'digi_ctx' => '2', 'post_id' => 900 );
	check( null === apply_filters( 'rest_request_before_callbacks', null, array(), $request ), 'El filtro REST no cambia la respuesta.' );
	check( 2 === Runtime::top(), 'digi_ctx de una entrada pública: es el contexto de la petición.' );
	$w = t_linked( 'loop-grid', 'rest001', $author, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w ); // refresh-loop llama a render_content() sin print_element().
	t_run( $w );
	check( array( 2 ) === t_last()['author__in'], 'En refresh-loop, current = digi_ctx.' );
	$w2 = t_linked( 'loop-grid', 'rest002', $rootq, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w2 );
	check( 1 === count( Runtime::budget()['stack'] ), 'Sin after_render (REST, editor), la pila se poda por nivel de búfer.' );
	t_run( $w2 );
	check( array( 2 ) === t_last()['author__in'], 'En refresh-loop, root = digi_ctx.' );
	foreach ( array( 'privada' => '20', 'borrador' => '30', 'consulta' => (string) $author, 'texto' => '2 OR 1=1', 'vacío' => '' ) as $label => $raw ) {
		Runtime::reset();
		$request->params = array( 'digi_ctx' => $raw, 'post_id' => 900 );
		apply_filters( 'rest_request_before_callbacks', $sentinel, array(), $request );
		$w = t_linked( 'loop-grid', 'rest' . substr( md5( $label ), 0, 3 ), $author, array( 'posts_per_page' => 10 ) );
		do_action( 'elementor/widget/before_render_content', $w );
		check( 0 === Runtime::top() && array() === t_ids( t_run( $w ) ), "digi_ctx $label: rechazado, la consulta con contexto queda vacía." );
	}
	// En el editor (REST de la vista previa de filtros), sin digi_ctx válido: el documento, sólo si quien pide lo edita.
	\Digitalisimo\Elements\F7b\Module::$editing = true;
	Runtime::reset();
	$request->params = array( 'digi_ctx' => '', 'post_id' => 2 );
	$GLOBALS['t']['user'] = 2;
	apply_filters( 'rest_request_before_callbacks', null, array(), $request );
	check( 2 === Runtime::top(), 'REST del editor: quien edita el documento lo usa de contexto.' );
	Runtime::reset();
	$GLOBALS['t']['user'] = 3;
	apply_filters( 'rest_request_before_callbacks', null, array(), $request );
	check( 0 === Runtime::top(), 'REST de un visitante (la ruta del constructor no basta): sin contexto.' );
	$GLOBALS['t']['user'] = 1;
	\Digitalisimo\Elements\F7b\Module::$editing = false;
	Runtime::reset();
	$request->route  = '/elementor-pro/v1/refresh-search';
	$request->params = array( 'digi_ctx' => '2' );
	apply_filters( 'rest_request_before_callbacks', null, array(), $request );
	$GLOBALS['t']['queried'] = null;
	check( 0 === Runtime::top(), 'Otra ruta: digi_ctx no se lee.' );

	// data-digi-ctx en el envoltorio del bucle del nivel superior con contexto current o root.
	Runtime::reset();
	$GLOBALS['t']['queried'] = get_post( 2 );
	$GLOBALS['t']['the_id']  = 2;
	$cases = array( 'current' => array( $author, '2' ), 'root' => array( $rootq, '2' ), 'sin contexto' => array( $plainq, null ) );
	foreach ( $cases as $label => list( $q, $attr ) ) {
		$w = t_linked( 'loop-grid', 'attr' . substr( md5( $label ), 0, 3 ), $q, array( 'posts_per_page' => 10 ) );
		do_action( 'elementor/widget/before_render_content', $w );
		check( $attr === ( $w->attrs['_wrapper']['data-digi-ctx'] ?? null ), "data-digi-ctx con consulta $label." );
		do_action( 'elementor/frontend/widget/after_render', $w );
	}
	$GLOBALS['t']['queried'] = get_post( 20 );
	$w = t_linked( 'loop-grid', 'attrprv', $author, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w );
	check( ! isset( $w->attrs['_wrapper'] ), 'Sin data-digi-ctx si la entrada no es pública.' );
	do_action( 'elementor/frontend/widget/after_render', $w );
	$GLOBALS['t']['queried'] = get_post( 2 );
	$outer = t_widget( 'loop-grid', 'attrout', array() );
	do_action( 'elementor/widget/before_render_content', $outer );
	ob_start();
	$w = t_linked( 'loop-grid', 'attrin1', $author, array( 'posts_per_page' => 10 ) );
	do_action( 'elementor/widget/before_render_content', $w );
	ob_end_clean();
	check( ! isset( $w->attrs['_wrapper'] ) && ! isset( $outer->attrs['_wrapper'] ), 'Ni en un bucle anidado ni en uno sin consulta.' );

	// =================================================================================================================
	// 11. Acciones AJAX del editor: permisos (C2-18) y «Desvincular» sólo lee (C2-17).

	t_reset();
	$qid  = t_query( array( 'post_type' => 'post', 'include' => array( 'terms' ), 'include_term_ids' => array( 11 ), 'orderby' => 'post_title' ), 'current', 'terms:category', array( 'per_page' => 6 ), 'Relacionadas' );
	$ajax = new class() {
		public $actions = array();
		public function register_ajax_action( $name, $callback ) {
			$this->actions[ $name ] = $callback;
		}
	};
	Controls::init_editor();
	do_action( 'elementor/ajax/register_actions', $ajax );
	check( array( 'digitalisimo_f7b_queries', 'digitalisimo_f7b_query_settings' ) === array_keys( $ajax->actions ), 'Dos acciones registradas con elementor/ajax/register_actions.' );
	$call = function ( string $action, array $data, int $user ) use ( $ajax ) {
		$GLOBALS['t']['user'] = $user;
		try {
			return call_user_func( $ajax->actions[ $action ], $data, $ajax );
		} catch ( \Exception $e ) {
			return $e->getCode();
		}
	};
	foreach ( array( 'digitalisimo_f7b_queries' => array(), 'digitalisimo_f7b_query_settings' => array( 'id' => $qid ) ) as $action => $data ) {
		check( 403 === $call( $action, $data + array( 'editor_post_id' => 2 ), 3 ), "$action: un suscriptor recibe 403." );
		check( 403 === $call( $action, $data + array( 'editor_post_id' => 1 ), 2 ), "$action: un autor sobre la entrada de otro recibe 403." );
		check( 403 === $call( $action, $data, 1 ), "$action: sin editor_post_id, 403." );
		check( 403 === $call( $action, $data + array( 'editor_post_id' => 99999 ), 1 ), "$action: con un documento inexistente, 403." );
		check( is_array( $call( $action, $data + array( 'editor_post_id' => 2 ), 2 ) ), "$action: el autor sobre su propia entrada sí." );
	}
	Store::forget();
	$writes = $GLOBALS['t']['writes'];
	$list   = $call( 'digitalisimo_f7b_queries', array( 'editor_post_id' => 2 ), 2 );
	check( isset( $list['queries'][ (string) $qid ] ) && 'Relacionadas' === $list['queries'][ (string) $qid ]['title'] && 'current' === $list['queries'][ (string) $qid ]['context'] && 'terms' === $list['queries'][ (string) $qid ]['relation'], 'La lista trae nombre, contexto y relación.' );
	check( 'Entradas · Noticias · 6 · contexto actual · mismos términos de «Categorías»' === $list['queries'][ (string) $qid ]['summary'], 'Resumen de sólo lectura: ' . $list['queries'][ (string) $qid ]['summary'] );
	check( '' === $list['queries'][ (string) $qid ]['edit'] && false === $list['can_manage'], 'Un autor no recibe el enlace de edición de la pantalla.' );
	\Digitalisimo\Elements\F7b\Usages::$map[ 'q:' . $qid ] = array( 'count' => 3, 'posts' => array( 1, 2, 3 ), 'drafts' => array( 30 ) );
	$list = $call( 'digitalisimo_f7b_queries', array( 'editor_post_id' => 2 ), 1 );
	check( 3 === $list['queries'][ (string) $qid ]['uses'] && 1 === $list['queries'][ (string) $qid ]['drafts'] && false !== strpos( $list['queries'][ (string) $qid ]['edit'], 'page=digitalisimo-elements-queries' ) && false !== strpos( $list['queries'][ (string) $qid ]['edit'], 'id=' . $qid ), 'Con edit_theme_options: usos (Usages::of) y enlace «Editar en Consultas».' );
	$read = $call( 'digitalisimo_f7b_query_settings', array( 'editor_post_id' => 2, 'id' => $qid ), 1 );
	check( Store::sanitize_settings( $read['settings'] ) === $read['settings'] && array( 11 ) === $read['settings']['include_term_ids'] && 'post_title' === $read['settings']['orderby'], 'Desvincular lee los ajustes en la forma del grupo de PRO.' );
	check( 6 === $read['per_page'] && 'current' === $read['context'] && 'terms' === $read['relation'], 'Con el tope y si usa contexto o relación (para avisar de que los resultados cambian).' );
	check( $writes === $GLOBALS['t']['writes'], 'Las acciones del editor no escriben nada (C2-17).' );
	check( 404 === $call( 'digitalisimo_f7b_query_settings', array( 'editor_post_id' => 2, 'id' => 1 ), 1 ) && 404 === $call( 'digitalisimo_f7b_query_settings', array( 'editor_post_id' => 2, 'id' => 'x' ), 1 ), 'Desvincular sólo lee consultas publicadas (404 si no).' );
	$GLOBALS['t']['posts'][ $qid ]->post_status = 'draft';
	Store::forget();
	check( 404 === $call( 'digitalisimo_f7b_query_settings', array( 'editor_post_id' => 2, 'id' => $qid ), 1 ), 'Un borrador tampoco.' );

	// Script del editor: sólo con el gancho del editor; configuración sin URLs de terceros.
	$GLOBALS['t']['user'] = 1;
	do_action( 'elementor/editor/after_enqueue_scripts' );
	check( isset( $GLOBALS['t']['scripts']['digitalisimo-f7b-queries-editor'] ) && array( 'elementor-editor' ) === $GLOBALS['t']['scripts']['digitalisimo-f7b-queries-editor'][1] && false !== strpos( $GLOBALS['t']['scripts']['digitalisimo-f7b-queries-editor'][0], 'assets/js/queries-editor.js' ), 'queries-editor.js se encola en el editor.' );
	$config = Controls::config();
	check( array( 'posts' => 'posts_', 'portfolio' => 'posts_', 'loop-grid' => 'post_query_', 'loop-carousel' => 'post_query_', 'digitalisimo-posts' => 'posts_', 'digitalisimo-portfolio' => 'posts_', 'digitalisimo-loop-grid' => 'post_query_', 'digitalisimo-loop-carousel' => 'post_query_' ) === $config['prefixes'] && array_keys( Store::DEFAULTS ) === $config['fields'], 'Configuración: prefijos de los cuatro widgets de PRO y de los cuatro propios de C5, y campos del grupo.' );

	// =================================================================================================================
	// 12. Pantalla «Consultas»: guardar, borrar, probar y buscar.

	t_reset();
	$GLOBALS['t']['admin'] = true;
	Screen::init();
	check( isset( $GLOBALS['t']['hooks']['admin_menu'], $GLOBALS['t']['hooks']['admin_post_digitalisimo_f7b_query_save'], $GLOBALS['t']['hooks']['admin_post_digitalisimo_f7b_query_delete'], $GLOBALS['t']['hooks']['admin_post_digitalisimo_f7b_query_test'], $GLOBALS['t']['hooks']['wp_ajax_digitalisimo_f7b_queries_search'] ), 'La pantalla engancha menú, tres acciones de admin-post y la búsqueda.' );
	check( 0 === strpos( Screen::PAGE, 'digitalisimo' ) && 'digitalisimo-elements-queries' === Screen::PAGE, 'Slug digitalisimo-elements-queries (recibe digitalisimo-ui).' );
	$GLOBALS['submenu'] = array( 'edit.php?post_type=elementor_library' => array() );
	check( 'edit.php?post_type=elementor_library' === Screen::parent_menu(), 'Cuelga de Plantillas.' );
	$GLOBALS['submenu'] = array();
	$GLOBALS['menu']    = array( array( 'x', 'y', 'digitalisimo' ) );
	check( 'digitalisimo' === Screen::parent_menu(), 'Sin Plantillas, junto a las pantallas de DIGITALÍSIMO.' );

	Store::register();
	$form = array(
		'title'         => '  Últimas noticias ',
		'settings'      => array( 'post_type' => 'post', 'include' => array( 'terms' ), 'include_term_ids' => array( '11', '999' ), 'orderby' => 'post_title', 'order' => 'asc', 'evil' => 'x' ),
		'context'       => 'none',
		'relation_kind' => 'terms',
		'relation_tax'  => 'category',
		'limits'        => array( 'per_page' => '4', 'depth' => '2' ),
	);
	$_POST = array( 'id' => '0', 'q' => $form, '_wpnonce' => wp_create_nonce( Screen::ACTION_SAVE . '-0' ) );
	$res   = Screen::process_save();
	check( 'created' === $res['notice'] && $res['id'] > 0, 'Crear con nonce y edit_theme_options.' );
	$new = Store::get( $res['id'] );
	check( 'Últimas noticias' === $new['title'] && array( 11 ) === $new['settings']['include_term_ids'] && 'terms:category' === $new['relation'] && 'current' === $new['context'] && 4 === $new['limits']['per_page'], 'Guardado saneado: término inexistente fuera, relación con contexto actual por defecto.' );
	$_POST['_wpnonce'] = 'malo';
	check( array( 'status' => 403, 'notice' => 'nonce', 'id' => 0 ) === Screen::process_save(), 'Nonce inválido: 403.' );
	$_POST['_wpnonce'] = wp_create_nonce( Screen::ACTION_SAVE . '-' . $res['id'] );
	check( 'nonce' === Screen::process_save()['notice'], 'El nonce va atado a la consulta: el de otra no vale.' );
	$_POST['_wpnonce']         = wp_create_nonce( Screen::ACTION_SAVE . '-0' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	check( 'method' === Screen::process_save()['notice'], 'Sólo por POST.' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	foreach ( array( 2 => 'autor', 3 => 'suscriptor' ) as $user => $label ) {
		$GLOBALS['t']['user'] = $user;
		$_POST['_wpnonce']    = wp_create_nonce( Screen::ACTION_SAVE . '-0' );
		check( 403 === ( Screen::process_save()['status'] ?? 0 ), "Un $label no puede guardar." );
	}
	$GLOBALS['t']['user'] = 1;
	$_POST = array( 'id' => '0', 'q' => array( 'title' => '', 'settings' => array( 'post_type' => 'post' ) ), '_wpnonce' => wp_create_nonce( Screen::ACTION_SAVE . '-0' ) );
	check( 'title' === Screen::process_save()['notice'], 'Sin nombre: error.' );
	$_POST['q'] = array( 'title' => 'x', 'settings' => array( 'post_type' => 'shop_order' ) );
	check( 'post_type' === Screen::process_save()['notice'], 'Tipo no público: error.' );
	$_POST['q'] = array( 'title' => 'x', 'settings' => array( 'post_type' => 'post' ), 'relation_kind' => 'author', 'context' => 'parent' );
	check( 'context' === Screen::process_save()['notice'], 'Un «parent» enviado desde el formulario (manipulado): error, nunca se cambia por otro contexto.' );
	$_POST['q']['context'] = 'none';
	$saved = Screen::process_save();
	check( 'created' === $saved['notice'] && 'current' === Store::get( $saved['id'] )['context'], 'Relación sin contexto elegido: el actual.' );
	check( array( 'title' => 'a', 'settings' => array(), 'context' => 'root', 'relation' => 'meta:color', 'limits' => array() ) === Screen::input( array( 'title' => 'a', 'context' => 'root', 'relation_kind' => 'meta', 'relation_meta' => ' color ' ) ), 'El formulario se traduce a la relación meta:<clave>.' );

	// Probar.
	$GLOBALS['t']['queried'] = null;
	$_POST = array( 'id' => (string) $res['id'], 'q' => array(), '_wpnonce' => wp_create_nonce( Screen::ACTION_TEST . '-' . $res['id'] ) );
	$test  = Screen::process_test();
	check( 'tested' === $test['notice'] && '' !== $test['result']['reason'] && array() === $test['result']['items'], 'Probar una consulta con relación sin contexto: lo explica.' );
	$_POST['q'] = array( 'ctx' => '2' );
	$test       = Screen::process_test();
	$titles     = array_column( $test['result']['items'], 'title' );
	check( array( 'Alfa', 'Delta', 'Theta', 'Zeta' ) === $titles && is_float( $test['result']['ms'] ), 'Probar con contexto: resultados en orden y el tiempo: ' . json_encode( $titles ) );
	$tested = t_last();
	check( true === $tested['no_found_rows'] && 4 === $tested['posts_per_page'] && false === $tested['cache_results'], 'Probar: no_found_rows, como mucho 10 (aquí el tope 4) y sin caché.' );
	for ( $i = 40; $i < 46; $i++ ) {
		t_post( array( 'ID' => $i, 'post_title' => 'Extra ' . $i, 'post_date' => gmdate( 'Y-m-d H:i:s', time() - $i * 86400 ) ) );
	}
	$big = t_query( array( 'post_type' => 'post' ), 'none', 'none', array( 'per_page' => 100 ) );
	$_POST = array( 'id' => (string) $big, '_wpnonce' => wp_create_nonce( Screen::ACTION_TEST . '-' . $big ) );
	check( Screen::TEST_LIMIT === count( Screen::process_test()['result']['items'] ) && 10 === t_last()['posts_per_page'], 'Probar: los 10 primeros (hay 14).' );
	$_POST['_wpnonce'] = 'malo';
	check( 'nonce' === Screen::process_test()['notice'], 'Probar exige nonce.' );
	$writes = $GLOBALS['t']['writes'];
	$_POST  = array( 'id' => (string) $big, '_wpnonce' => wp_create_nonce( Screen::ACTION_TEST . '-' . $big ) );
	Screen::process_test();
	check( $writes === $GLOBALS['t']['writes'], 'Probar no escribe nada.' );

	// Borrar sólo sin usos.
	\Digitalisimo\Elements\F7b\Usages::$map[ 'q:' . $res['id'] ] = array( 'count' => 2, 'posts' => array( 1, 2 ), 'drafts' => array() );
	$_POST = array( 'id' => (string) $res['id'], '_wpnonce' => wp_create_nonce( Screen::ACTION_DELETE . '-' . $res['id'] ) );
	check( 'in_use' === Screen::process_delete()['notice'] && null !== Store::get( $res['id'] ), 'Con usos, no se borra.' );
	\Digitalisimo\Elements\F7b\Usages::$map = array();
	$GLOBALS['t']['user'] = 2;
	$_POST['_wpnonce']    = wp_create_nonce( Screen::ACTION_DELETE . '-' . $res['id'] );
	check( 403 === Screen::process_delete()['status'], 'Un autor no puede borrar.' );
	$GLOBALS['t']['user'] = 1;
	$_POST['_wpnonce']    = wp_create_nonce( Screen::ACTION_DELETE . '-' . $res['id'] );
	check( 'deleted' === Screen::process_delete()['notice'] && in_array( $res['id'], $GLOBALS['t']['deleted'], true ), 'Sin usos, se borra.' );
	check( 'not_found' === Screen::process_delete()['notice'], 'Borrar dos veces: no existe.' );

	// Búsqueda: autores sólo por display_name y con entradas publicadas.
	$found = Screen::search( 'author', 'beto' );
	check( array( array( 'id' => 2, 'text' => 'Beto Autor' ) ) === $found, 'Autores: id y display_name.' );
	$args = end( $GLOBALS['t']['users_args'] );
	check( array( 'display_name' ) === $args['search_columns'] && array( 'ID', 'display_name' ) === $args['fields'] && array( 'post', 'page' ) === $args['has_published_posts'], 'Busca sólo en display_name, sólo pide ID y display_name, sólo autores con entradas publicadas.' );
	check( array() === Screen::search( 'author', 'susana' ) && array() === Screen::search( 'author', 'beto@sitio' ) && array() === Screen::search( 'author', 'root' ), 'Ni suscriptores sin entradas, ni por correo ni por nombre de usuario.' );
	check( array( array( 'id' => 11, 'text' => 'Categorías: Noticias' ) ) === Screen::search( 'term', 'noti', 'post' ) && array( 'category', 'post_tag' ) === end( $GLOBALS['t']['terms_args'] )['taxonomy'], 'Términos de las taxonomías públicas del tipo, por term_taxonomy_id.' );
	check( array() === Screen::search( 'term', 'n' ) && array() === Screen::search( 'term', str_repeat( 'n', 101 ) ) && array() === Screen::search( 'secret', 'noticias' ), 'Búsqueda de 2 a 100 caracteres y sólo de objetos conocidos.' );
	check( array( array( 'id' => 9, 'text' => 'Página madre (Páginas)' ) ) === Screen::search( 'post', 'madre', 'page' ), 'Entradas publicadas del tipo.' );

	// Render: lista con usos y «En uso», formulario con nonce y «Probar».
	$used = t_query( array( 'post_type' => 'post' ), 'none', 'none', array(), 'Usada' );
	$free = t_query( array( 'post_type' => 'post' ), 'none', 'none', array(), 'Libre' );
	\Digitalisimo\Elements\F7b\Usages::$map[ 'q:' . $used ] = array( 'count' => 1, 'posts' => array( 1 ), 'drafts' => array( 30 ) );
	ob_start();
	Screen::render();
	$html = ob_get_clean();
	check( false !== strpos( $html, 'class="wrap digitalisimo-elements-queries"' ) && false !== strpos( $html, '<table class="widefat striped">' ), 'La lista usa el marcado de digitalisimo-ui.' );
	check( false !== strpos( $html, 'En uso (1)' ) && 3 === substr_count( $html, 'name="action" value="digitalisimo_f7b_query_delete"' ), 'Con usos, «En uso (N)» en lugar de «Borrar»; sin usos (las otras tres), «Borrar».' );
	check( false !== strpos( $html, '(y 1 borrador)' ) && false !== strpos( $html, 'post.php?post=1&amp;action=edit' ), 'Los usos listan los documentos y los borradores aparte.' );
	$_GET = array( 'action' => 'edit', 'id' => (string) $used );
	ob_start();
	Screen::render();
	$html = ob_get_clean();
	check( false !== strpos( $html, 'value="digitalisimo_f7b_query_save"' ) && false !== strpos( $html, 'value="digitalisimo_f7b_query_test"' ) && false !== strpos( $html, wp_create_nonce( Screen::ACTION_TEST . '-' . $used ) ), 'Editar: formulario de guardado y «Probar» por POST con nonce.' );
	check( false !== strpos( $html, 'data-object="term"' ) && false !== strpos( $html, 'data-object="author"' ) && false !== strpos( $html, 'data-object="post"' ), 'Selectores de términos, autores y entradas.' );
	check( false !== strpos( $html, '<option value="current"' ) && false !== strpos( $html, '<option value="root"' ) && false === strpos( $html, 'value="parent"' ) && false === stripos( $html, 'padre' ), 'Contextos de la pantalla: actual y raíz, sin «padre».' );
	check( false === stripos( $html, 'elementor.com' ) && false === stripos( $html, 'bricks' ) && false === stripos( $html, 'http://' ), 'Sin enlaces ni nombres de proveedores.' );
	$_GET = array( 'action' => 'edit', 'id' => '99999' );
	ob_start();
	Screen::render();
	check( false !== strpos( ob_get_clean(), 'Esa consulta no existe.' ), 'Editar una consulta inexistente lo dice.' );
	$GLOBALS['t']['user'] = 2;
	ob_start();
	Screen::render();
	check( false !== strpos( ob_get_clean(), 'No tienes permiso' ), 'Sin edit_theme_options, la pantalla no muestra nada.' );
	$GLOBALS['t']['user'] = 1;
	$_GET = array( 'page' => Screen::PAGE );
	Screen::enqueue();
	check( isset( $GLOBALS['t']['scripts'][ Screen::HANDLE ] ) && array( 'in_footer' => true, 'strategy' => 'defer' ) === $GLOBALS['t']['scripts'][ Screen::HANDLE ][2], 'queries-screen.js sólo en la pantalla, con defer.' );
	$GLOBALS['t']['scripts'] = array();
	$_GET = array( 'page' => 'otra' );
	Screen::enqueue();
	check( array() === $GLOBALS['t']['scripts'], 'En otra pantalla, nada.' );

	// =================================================================================================================
	// 13. Arranque con la bandera, en la administración y sin las piezas.

	t_reset();
	\Digitalisimo\Elements\F7b\Flags::$on['saved_queries'] = 1;
	$GLOBALS['t']['did']['init'] = 1;
	$GLOBALS['t']['registered']  = array();
	$reflection = digi_test_accessible( new ReflectionProperty( Queries::class, 'initialized' ) );
	$reflection->setValue( null, false );
	( digi_test_accessible( new ReflectionProperty( Store::class, 'registered' ) ) )->setValue( null, false );
	foreach ( array( Runtime::class, Controls::class ) as $class ) {
		( digi_test_accessible( new ReflectionProperty( $class, 'initialized' ) ) )->setValue( null, false );
	}
	Queries::init();
	$hooks = t_hooks();
	foreach ( array( 'elementor/widget/before_render_content', 'elementor/frontend/widget/after_render', 'elementor/query/query_args', 'elementor/query/query_results', 'pre_get_posts', 'found_posts', 'rest_request_before_callbacks', 'elementor/element/posts/section_query/before_section_end', 'elementor/element/portfolio/section_query/before_section_end', 'elementor/element/loop-grid/section_query/before_section_end', 'elementor/element/loop-carousel/section_query/before_section_end' ) as $hook ) {
		check( isset( $hooks[ $hook ] ), "Con la bandera, engancha $hook." );
	}
	check( isset( $GLOBALS['t']['hooks']['elementor/query/query_args'][5] ), 'La fusión va a prioridad 5.' );
	check( isset( $GLOBALS['t']['registered']['digitalisimo_query'] ) && ! isset( $hooks['admin_menu'] ) && ! isset( $hooks['elementor/ajax/register_actions'] ), 'En el front: el tipo, sin pantalla ni AJAX.' );

	// Administración sin la bandera: pantalla, AJAX del editor y el tipo en `init`; nada del front.
	t_reset();
	$GLOBALS['t']['admin'] = true;
	foreach ( array( Queries::class, Runtime::class, Controls::class, Screen::class ) as $class ) {
		( digi_test_accessible( new ReflectionProperty( $class, 'initialized' ) ) )->setValue( null, false );
	}
	( digi_test_accessible( new ReflectionProperty( Controls::class, 'ajax' ) ) )->setValue( null, false );
	Queries::init();
	$hooks = t_hooks();
	check( isset( $hooks['admin_menu'], $hooks['elementor/ajax/register_actions'], $hooks['elementor/editor/after_enqueue_scripts'], $hooks['init'] ) && ! isset( $hooks['elementor/query/query_args'] ) && ! isset( $hooks['elementor/widget/before_render_content'] ), 'Administración sin bandera: pantalla, AJAX y tipo; sin aplicación.' );
	do_action( 'init' );
	check( isset( $GLOBALS['t']['registered']['digitalisimo_query'] ), 'El tipo se registra en init.' );
	$GLOBALS['t']['admin'] = false;
	$reflection->setValue( null, false );
	\Digitalisimo\Elements\F7b\Module::$missing = array();
	$GLOBALS['t']['hooks'] = array();
	check( false === \Digitalisimo\Elements\F7b\Module::requires( array( 'ElementorPro\Modules\QueryControl\Classes\No_Existe' ), 'queries' ) && array( 'ElementorPro\Modules\QueryControl\Classes\No_Existe' ) === \Digitalisimo\Elements\F7b\Module::$missing['queries'], 'Sin el constructor de PRO, requires() falla y lo anota.' );

	echo 'elements-f7b-queries: ' . $GLOBALS['checks'] . " comprobaciones en verde.\n";
}
