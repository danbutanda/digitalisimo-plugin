<?php
/**
 * WordPress mínimo en memoria para las pruebas unitarias de kits del frente G (tests/elements-kit-session.php,
 * tests/elements-kit-settings.php y tests/elements-kit-pro-fixes.php). No es WordPress: sólo lo que el importador de K
 * llama, con el mismo contrato observable (prioridades de los ganchos, opciones, transitorios, entradas, metas, la
 * carpeta de subidas real en un temporal y un $wpdb que entiende sólo las consultas que K hace). Una consulta que no
 * entiende lanza una excepción: la prueba falla con la consulta a la vista en vez de dar un falso verde.
 *
 * Todas las pruebas exigen ZipArchive (CV-30): sin él lanzan una excepción, nunca «SKIP».
 */

if ( ! class_exists( 'ZipArchive' ) ) {
	throw new RuntimeException( 'Las pruebas de kits necesitan la extensión ZipArchive de PHP (CV-30).' );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
foreach ( array( 'DAY_IN_SECONDS' => 86400, 'HOUR_IN_SECONDS' => 3600, 'MINUTE_IN_SECONDS' => 60, 'OBJECT' => 'OBJECT', 'ARRAY_A' => 'ARRAY_A', 'ELEMENTOR_VERSION' => '4.3.5' ) as $kt_name => $kt_value ) {
	if ( ! defined( $kt_name ) ) {
		define( $kt_name, $kt_value );
	}
}

/** Estado del sitio de prueba. `kt_reset()` lo vuelve a dejar vacío. */
function kt_reset( array $overrides = array() ) {
	$root = sys_get_temp_dir() . '/digi-kt-' . getmypid() . '-' . substr( md5( (string) microtime( true ) . mt_rand() ), 0, 8 );
	@mkdir( $root . '/uploads', 0777, true ); // phpcs:ignore
	$GLOBALS['kt'] = array_replace(
		array(
			'root'       => $root,
			'uploads'    => $root . '/uploads',
			'home'       => 'https://b.test',
			'hooks'      => array(),
			'done'       => array(),
			'options'    => array( 'show_on_front' => 'posts' ),
			'autoload'   => array(),
			'transients' => array(),
			'user'       => 1,
			'caps'       => array( 1 => array( 'manage_options', 'unfiltered_html', 'edit_theme_options', 'publish_posts', 'edit_posts', 'edit_post', 'publish_post' ), 2 => array( 'edit_posts', 'publish_posts', 'unfiltered_html' ) ),
			'posts'      => array(),
			'meta'       => array(),
			'term_meta'  => array(),
			'next_id'    => 1,
			'menus'      => array(),
			'scheduled'  => array(),
			'wrong'      => array(),
			'blog'       => 1,
			'multisite'  => false,
			'redirects'  => array(),
			'post_types' => array(
				'post'               => array( 'public' => true, 'viewable' => true ),
				'page'               => array( 'public' => true, 'viewable' => true ),
				'attachment'         => array( 'public' => true, 'viewable' => true ),
				'product'            => array( 'public' => true, 'viewable' => true ),
				'e-landing-page'     => array( 'public' => true, 'viewable' => true ),
				'elementor_library'  => array( 'public' => true, 'viewable' => true ),
				'nav_menu_item'      => array( 'public' => false, 'viewable' => false ),
				'revision'           => array( 'public' => false, 'viewable' => false ),
				'elementor_snippet'  => array( 'public' => false, 'viewable' => false ),
				'elementor_font'     => array( 'public' => false, 'viewable' => false ),
				'elementor_icons'    => array( 'public' => false, 'viewable' => false ),
				'e-floating-buttons' => array( 'public' => true, 'viewable' => true ),
				'product_variation'  => array( 'public' => false, 'viewable' => false ),
				'wp_block'           => array( 'public' => false, 'viewable' => false ),
			),
		),
		$overrides
	);
	$GLOBALS['wpdb'] = new Kt_Wpdb();
}

/** Borra recursivamente un temporal de la prueba (sólo bajo sys_get_temp_dir()). */
function kt_rmdir( $dir ) {
	if ( ! is_dir( $dir ) || 0 !== strpos( realpath( $dir ), realpath( sys_get_temp_dir() ) ) ) {
		return;
	}
	foreach ( (array) scandir( $dir ) as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		is_dir( $path ) && ! is_link( $path ) ? kt_rmdir( $path ) : @unlink( $path ); // phpcs:ignore
	}
	@rmdir( $dir ); // phpcs:ignore
}

/** Salida de wp_die() o wp_send_json_*(): la prueba la captura. */
class Kt_Exit extends RuntimeException {
	public $status;
	public $data;
	public function __construct( $message, $status = 200, $data = null ) {
		parent::__construct( (string) $message );
		$this->status = (int) $status;
		$this->data   = $data;
	}
}

class WP_Error {
	public $errors = array();
	public $error_data = array();
	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( '' !== $code ) {
			$this->errors[ $code ][] = $message;
			if ( '' !== $data ) {
				$this->error_data[ $code ] = $data;
			}
		}
	}
	public function get_error_code() {
		$codes = array_keys( $this->errors );
		return $codes ? $codes[0] : '';
	}
	public function get_error_message( $code = '' ) {
		$code = '' === $code ? $this->get_error_code() : $code;
		return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
	}
	public function get_error_data( $code = '' ) {
		$code = '' === $code ? $this->get_error_code() : $code;
		return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
	}
}

class WP_Post {
	public $ID = 0, $post_type = 'post', $post_status = 'publish', $post_title = '', $post_name = '', $post_content = '', $post_parent = 0, $post_author = 1, $post_excerpt = '', $menu_order = 0;
	public $post_content_filtered = '', $post_password = '', $comment_status = 'closed', $ping_status = 'closed', $guid = '', $post_mime_type = '';
	public function __construct( array $data ) {
		foreach ( $data as $key => $value ) {
			$this->$key = $value;
		}
	}
}

class WP_REST_Request {
	private $method, $route, $params = array(), $files = array();
	public function __construct( $method = 'GET', $route = '' ) {
		$this->method = $method;
		$this->route  = $route;
	}
	public function get_method() { return $this->method; }
	public function get_route() { return $this->route; }
	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
	public function set_param( $key, $value ) { $this->params[ $key ] = $value; }
	public function get_params() { return $this->params; }
	public function get_file_params() { return $this->files; }
	public function set_file_params( array $files ) { $this->files = $files; }
}

class WP_HTTP_Response {
	public $data, $status, $headers = array();
	public function __construct( $data = null, $status = 200, $headers = array() ) {
		$this->data    = $data;
		$this->status  = $status;
		$this->headers = $headers;
	}
	public function get_data() { return $this->data; }
	public function set_data( $data ) { $this->data = $data; }
	public function get_status() { return $this->status; }
}
class WP_REST_Response extends WP_HTTP_Response {}

/** $wpdb que entiende sólo las consultas del importador de K; cualquier otra lanza una excepción. */
class Kt_Wpdb {
	public $prefix = 'wp_', $options = 'wp_options', $posts = 'wp_posts', $postmeta = 'wp_postmeta', $terms = 'wp_terms', $term_taxonomy = 'wp_term_taxonomy', $termmeta = 'wp_termmeta', $usermeta = 'wp_usermeta', $last_error = '';
	public $queries = array();
	public function esc_like( $text ) { return addcslashes( (string) $text, '_%\\' ); }
	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$i = 0;
		return preg_replace_callback( '/%[sd]/', function ( $m ) use ( $args, &$i ) {
			$value = $args[ $i++ ] ?? '';
			return '%d' === $m[0] ? (string) (int) $value : "'" . addslashes( (string) $value ) . "'";
		}, $query );
	}
	private function like_options( $sql ) {
		if ( ! preg_match( "/option_name LIKE '((?:[^'\\\\]|\\\\.)*)'/", $sql, $m ) ) {
			return null;
		}
		$pattern = stripslashes( str_replace( "\\'", "'", $m[1] ) );
		$re      = '';
		// Primero el literal de SQL (stripslashes) y después los escapes de LIKE (\_ y \% son literales).
		for ( $i = 0, $n = strlen( $pattern ); $i < $n; $i++ ) {
			$c = $pattern[ $i ];
			if ( '\\' === $c && $i + 1 < $n ) {
				$re .= preg_quote( $pattern[ ++$i ], '/' );
			} elseif ( '%' === $c ) {
				$re .= '.*';
			} elseif ( '_' === $c ) {
				$re .= '.';
			} else {
				$re .= preg_quote( $c, '/' );
			}
		}
		return array_values( array_filter( array_keys( $GLOBALS['kt']['options'] ), function ( $name ) use ( $re ) { return 1 === preg_match( '/^' . $re . '$/s', $name ); } ) );
	}
	public function get_col( $sql ) {
		$this->queries[] = $sql;
		$names = $this->like_options( $sql );
		if ( null !== $names && false !== stripos( $sql, 'FROM ' . $this->options ) ) {
			return $names;
		}
		throw new RuntimeException( 'Kt_Wpdb::get_col no entiende: ' . $sql );
	}
	public function get_results( $sql, $output = 'OBJECT' ) {
		$this->queries[] = $sql;
		if ( preg_match( "/FROM {$this->posts} WHERE post_type IN \\(([^)]*)\\) AND post_status <> 'auto-draft'/", $sql, $m ) ) {
			$types = array_map( function ( $t ) { return trim( $t, " '" ); }, explode( ',', $m[1] ) );
			$rows  = array();
			foreach ( $GLOBALS['kt']['posts'] as $post ) {
				if ( in_array( $post['post_type'], $types, true ) && 'auto-draft' !== $post['post_status'] ) {
					$rows[] = (object) array( 'ID' => $post['ID'], 'post_name' => $post['post_name'] );
				}
			}
			return array_slice( $rows, 0, 10 );
		}
		throw new RuntimeException( 'Kt_Wpdb::get_results no entiende: ' . $sql );
	}
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		if ( preg_match( "/^SELECT MAX\\(ID\\) FROM {$this->posts}$/", trim( $sql ) ) ) {
			return (string) ( $GLOBALS['kt']['posts'] ? max( array_keys( $GLOBALS['kt']['posts'] ) ) : 0 );
		}
		if ( false !== strpos( $sql, "p.post_type = 'elementor_library'" ) && false !== stripos( $sql, 'COUNT(' ) ) {
			$n = 0;
			foreach ( $GLOBALS['kt']['posts'] as $post ) {
				if ( 'elementor_library' === $post['post_type'] && ! in_array( $post['post_status'], array( 'auto-draft', 'trash' ), true ) && 'kit' !== get_post_meta( $post['ID'], '_elementor_template_type', true ) ) {
					++$n;
				}
			}
			return (string) $n;
		}
		throw new RuntimeException( 'Kt_Wpdb::get_var no entiende: ' . $sql );
	}
	public function query( $sql ) {
		throw new RuntimeException( 'Kt_Wpdb::query no entiende: ' . $sql );
	}
}

// ── Ganchos ─────────────────────────────────────────────────────────────────────────────────────────────────────
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['kt']['hooks'][ $hook ][ $priority ][] = array( $callback, (int) $args );
	return true;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return add_filter( $hook, $callback, $priority, $args );
}
function kt_same_callback( $a, $b ) {
	if ( $a instanceof Closure || $b instanceof Closure ) {
		return $a === $b;
	}
	if ( is_array( $a ) && is_array( $b ) ) {
		$ca = is_object( $a[0] ) ? spl_object_id( $a[0] ) : ltrim( strtolower( (string) $a[0] ), '\\' );
		$cb = is_object( $b[0] ) ? spl_object_id( $b[0] ) : ltrim( strtolower( (string) $b[0] ), '\\' );
		return $ca === $cb && strtolower( $a[1] ) === strtolower( $b[1] );
	}
	return is_string( $a ) && is_string( $b ) && ltrim( strtolower( $a ), '\\' ) === ltrim( strtolower( $b ), '\\' );
}
function remove_filter( $hook, $callback, $priority = 10 ) {
	$removed = false;
	foreach ( $GLOBALS['kt']['hooks'][ $hook ][ $priority ] ?? array() as $i => $entry ) {
		if ( kt_same_callback( $entry[0], $callback ) ) {
			unset( $GLOBALS['kt']['hooks'][ $hook ][ $priority ][ $i ] );
			$removed = true;
		}
	}
	return $removed;
}
function remove_action( $hook, $callback, $priority = 10 ) {
	return remove_filter( $hook, $callback, $priority );
}
/** Prioridad registrada de un callback (como has_filter de WordPress), o false. */
function has_filter( $hook, $callback = false ) {
	if ( false === $callback ) {
		foreach ( $GLOBALS['kt']['hooks'][ $hook ] ?? array() as $entries ) {
			if ( $entries ) {
				return true;
			}
		}
		return false;
	}
	foreach ( $GLOBALS['kt']['hooks'][ $hook ] ?? array() as $priority => $entries ) {
		foreach ( $entries as $entry ) {
			if ( kt_same_callback( $entry[0], $callback ) ) {
				return $priority;
			}
		}
	}
	return false;
}
function has_action( $hook, $callback = false ) {
	return has_filter( $hook, $callback );
}
function apply_filters( $hook, $value, ...$args ) {
	$hooks = $GLOBALS['kt']['hooks'][ $hook ] ?? array();
	ksort( $hooks );
	foreach ( $hooks as $entries ) {
		foreach ( $entries as $entry ) {
			$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, $entry[1] ) ) );
		}
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	$GLOBALS['kt']['done'][ $hook ] = ( $GLOBALS['kt']['done'][ $hook ] ?? 0 ) + 1;
	$hooks = $GLOBALS['kt']['hooks'][ $hook ] ?? array();
	ksort( $hooks );
	foreach ( $hooks as $entries ) {
		foreach ( $entries as $entry ) {
			call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) );
		}
	}
}
function did_action( $hook ) {
	return $GLOBALS['kt']['done'][ $hook ] ?? 0;
}
function _doing_it_wrong( $function, $message, $version = '' ) {
	$GLOBALS['kt']['wrong'][] = $function . ': ' . $message;
}

// ── Opciones y transitorios ─────────────────────────────────────────────────────────────────────────────────────
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['kt']['options'] ) ? $GLOBALS['kt']['options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$old   = get_option( $name );
	$value = apply_filters( 'pre_update_option_' . $name, $value, $old, $name );
	if ( $value === $old && array_key_exists( $name, $GLOBALS['kt']['options'] ) ) {
		return false;
	}
	$GLOBALS['kt']['options'][ $name ] = $value;
	if ( null !== $autoload ) {
		$GLOBALS['kt']['autoload'][ $name ] = (bool) $autoload && 'no' !== $autoload;
	}
	return true;
}
function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $name, $GLOBALS['kt']['options'] ) ) {
		return false;
	}
	$GLOBALS['kt']['options'][ $name ]  = $value;
	$GLOBALS['kt']['autoload'][ $name ] = ! in_array( $autoload, array( false, 'no', 'off' ), true );
	return true;
}
function delete_option( $name ) {
	$had = array_key_exists( $name, $GLOBALS['kt']['options'] );
	unset( $GLOBALS['kt']['options'][ $name ], $GLOBALS['kt']['autoload'][ $name ] );
	return $had;
}
function wp_load_alloptions() {
	$out = array();
	foreach ( $GLOBALS['kt']['options'] as $name => $value ) {
		if ( $GLOBALS['kt']['autoload'][ $name ] ?? true ) {
			$out[ $name ] = $value;
		}
	}
	return $out;
}
function get_transient( $name ) {
	$t = $GLOBALS['kt']['transients'][ $name ] ?? null;
	if ( null === $t || ( $t[1] && $t[1] < time() ) ) {
		return false;
	}
	return $t[0];
}
function set_transient( $name, $value, $ttl = 0 ) {
	$GLOBALS['kt']['transients'][ $name ] = array( $value, $ttl ? time() + $ttl : 0 );
	return true;
}
function delete_transient( $name ) {
	unset( $GLOBALS['kt']['transients'][ $name ] );
	return true;
}
/** Hace caducar un transitorio (para probar un token vencido). */
function kt_expire_transient( $name ) {
	if ( isset( $GLOBALS['kt']['transients'][ $name ] ) ) {
		$GLOBALS['kt']['transients'][ $name ][1] = time() - 1;
	}
}

// ── Usuarios y permisos ─────────────────────────────────────────────────────────────────────────────────────────
function get_current_user_id() {
	return (int) $GLOBALS['kt']['user'];
}
function wp_set_current_user( $id ) {
	$GLOBALS['kt']['user'] = (int) $id;
}
function current_user_can( $cap, ...$args ) {
	return in_array( $cap, $GLOBALS['kt']['caps'][ get_current_user_id() ] ?? array(), true );
}
function user_can( $user, $cap ) {
	return in_array( $cap, $GLOBALS['kt']['caps'][ is_object( $user ) ? $user->ID : (int) $user ] ?? array(), true );
}
function check_admin_referer( $action = -1, $name = '_wpnonce' ) {
	return true;
}

// ── Entradas y metas ────────────────────────────────────────────────────────────────────────────────────────────
function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
	$update = ! empty( $postarr['ID'] ) && isset( $GLOBALS['kt']['posts'][ (int) $postarr['ID'] ] );
	$data   = array(
		'post_type'    => $postarr['post_type'] ?? 'post',
		'post_status'  => $postarr['post_status'] ?? 'draft',
		'post_title'   => $postarr['post_title'] ?? '',
		'post_name'    => $postarr['post_name'] ?? '',
		'post_content' => $postarr['post_content'] ?? '',
		'post_parent'  => (int) ( $postarr['post_parent'] ?? 0 ),
		'post_author'  => (int) ( $postarr['post_author'] ?? get_current_user_id() ),
	);
	$data = apply_filters( 'wp_insert_post_data', $data, $postarr, $postarr, $update );
	$id   = $update ? (int) $postarr['ID'] : ( ! empty( $postarr['import_id'] ) && ! isset( $GLOBALS['kt']['posts'][ (int) $postarr['import_id'] ] ) ? (int) $postarr['import_id'] : $GLOBALS['kt']['next_id'] );
	$GLOBALS['kt']['next_id'] = max( $GLOBALS['kt']['next_id'], $id + 1 );
	$GLOBALS['kt']['posts'][ $id ] = array( 'ID' => $id ) + $data + ( $GLOBALS['kt']['posts'][ $id ] ?? array() );
	foreach ( (array) ( $postarr['meta_input'] ?? array() ) as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return $id;
}
function wp_update_post( $postarr, $wp_error = false ) {
	$id = (int) ( $postarr['ID'] ?? 0 );
	if ( ! isset( $GLOBALS['kt']['posts'][ $id ] ) ) {
		return 0;
	}
	return wp_insert_post( array_replace( $GLOBALS['kt']['posts'][ $id ], $postarr ), $wp_error );
}
function get_post( $id = null ) {
	$id = is_object( $id ) ? $id->ID : (int) $id;
	return isset( $GLOBALS['kt']['posts'][ $id ] ) ? new WP_Post( $GLOBALS['kt']['posts'][ $id ] ) : null;
}
function get_post_status( $id = null ) {
	$post = get_post( $id );
	return $post ? $post->post_status : false;
}
function get_post_type( $id = null ) {
	$post = get_post( $id );
	return $post ? $post->post_type : false;
}
function get_posts( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['kt']['posts'] as $post ) {
		if ( isset( $args['post_type'] ) && 'any' !== $args['post_type'] && ! in_array( $post['post_type'], (array) $args['post_type'], true ) ) {
			continue;
		}
		if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && ! in_array( $post['post_status'], (array) $args['post_status'], true ) ) {
			continue;
		}
		if ( isset( $args['meta_key'] ) && '' === (string) get_post_meta( $post['ID'], $args['meta_key'], true ) ) {
			continue;
		}
		$out[] = ( $args['fields'] ?? '' ) === 'ids' ? $post['ID'] : new WP_Post( $post );
	}
	return $out;
}
function get_post_meta( $id, $key = '', $single = false ) {
	$all = $GLOBALS['kt']['meta'][ (int) $id ] ?? array();
	if ( '' === $key ) {
		return $all;
	}
	if ( ! isset( $all[ $key ] ) ) {
		return $single ? '' : array();
	}
	return $single ? $all[ $key ][0] : $all[ $key ];
}
function update_post_meta( $id, $key, $value, $prev = '' ) {
	$GLOBALS['kt']['meta'][ (int) $id ][ $key ] = array( is_string( $value ) ? stripslashes( $value ) : $value );
	return true;
}
function add_post_meta( $id, $key, $value, $unique = false ) {
	if ( $unique && isset( $GLOBALS['kt']['meta'][ (int) $id ][ $key ] ) ) {
		return false;
	}
	$GLOBALS['kt']['meta'][ (int) $id ][ $key ][] = is_string( $value ) ? stripslashes( $value ) : $value;
	return true;
}
function delete_post_meta( $id, $key, $value = '' ) {
	unset( $GLOBALS['kt']['meta'][ (int) $id ][ $key ] );
	return true;
}
function metadata_exists( $type, $id, $key ) {
	return 'post' === $type ? isset( $GLOBALS['kt']['meta'][ (int) $id ][ $key ] ) : isset( $GLOBALS['kt']['term_meta'][ (int) $id ][ $key ] );
}
function update_term_meta( $id, $key, $value ) {
	$GLOBALS['kt']['term_meta'][ (int) $id ][ $key ] = array( $value );
	return true;
}
function get_term_meta( $id, $key = '', $single = false ) {
	$all = $GLOBALS['kt']['term_meta'][ (int) $id ] ?? array();
	return '' === $key ? $all : ( $single ? ( $all[ $key ][0] ?? '' ) : ( $all[ $key ] ?? array() ) );
}
function get_post_types( $args = array(), $output = 'names' ) {
	$out = array();
	foreach ( $GLOBALS['kt']['post_types'] as $name => $type ) {
		if ( isset( $args['public'] ) && (bool) $args['public'] !== (bool) $type['public'] ) {
			continue;
		}
		$out[ $name ] = $name;
	}
	return $out;
}
function post_type_exists( $type ) {
	return isset( $GLOBALS['kt']['post_types'][ $type ] );
}
function is_post_type_viewable( $type ) {
	$name = is_object( $type ) ? $type->name : (string) $type;
	return ! empty( $GLOBALS['kt']['post_types'][ $name ]['viewable'] );
}
function taxonomy_exists( $taxonomy ) {
	return in_array( $taxonomy, array( 'category', 'post_tag', 'nav_menu', 'product_cat', 'product_tag' ), true );
}
function wp_get_nav_menus() {
	return $GLOBALS['kt']['menus'];
}
function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
	return array( 'https://b.test/wp-content/uploads/' . $id . '-' . $size . '.png', 10, 10, false );
}

// ── Subidas, URL y utilidades ───────────────────────────────────────────────────────────────────────────────────
function wp_upload_dir( $time = null, $create = true, $refresh = false ) {
	return array( 'basedir' => $GLOBALS['kt']['uploads'], 'baseurl' => $GLOBALS['kt']['home'] . '/wp-content/uploads', 'path' => $GLOBALS['kt']['uploads'], 'url' => $GLOBALS['kt']['home'] . '/wp-content/uploads', 'subdir' => '', 'error' => false );
}
function wp_mkdir_p( $dir ) {
	return is_dir( $dir ) || @mkdir( $dir, 0777, true ); // phpcs:ignore
}
function get_temp_dir() {
	return rtrim( $GLOBALS['kt']['root'], '/' ) . '/';
}
function wp_tempnam( $name = '', $dir = '' ) {
	return tempnam( '' !== $dir ? $dir : get_temp_dir(), 'kt' );
}
function home_url( $path = '' ) {
	return $GLOBALS['kt']['home'] . ( '' !== $path ? '/' . ltrim( $path, '/' ) : '' );
}
function site_url( $path = '' ) {
	return home_url( $path );
}
function get_site_url( $blog = null, $path = '' ) {
	return home_url( $path );
}
function admin_url( $path = '' ) {
	return $GLOBALS['kt']['home'] . '/wp-admin/' . ltrim( $path, '/' );
}
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$url = $args[1] ?? '';
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args[0] );
	}
	$url = $args[2] ?? '';
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $args[0] ) . '=' . rawurlencode( (string) $args[1] );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}
function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}
function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}
function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_text_field( $value ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $value ) ) );
}
function sanitize_title( $value ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' );
}
function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}
function wp_slash( $value ) {
	return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
}
function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $value ) {
	return esc_html( $value );
}
function esc_url( $value ) {
	return esc_html( $value );
}
function esc_url_raw( $value ) {
	return (string) $value;
}
function esc_html__( $text, $domain = '' ) {
	return esc_html( $text );
}
function __( $text, $domain = '' ) {
	return $text;
}
function absint( $value ) {
	return abs( (int) $value );
}
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}
function wp_doing_ajax() {
	return ! empty( $GLOBALS['kt']['ajax'] );
}
function wp_send_json_error( $data = null, $status = null ) {
	throw new Kt_Exit( 'json_error', (int) $status, $data );
}
function wp_send_json_success( $data = null, $status = null ) {
	throw new Kt_Exit( 'json_success', (int) $status, $data );
}
function wp_die( $message = '', $title = '', $args = array() ) {
	throw new Kt_Exit( (string) $message, (int) ( is_array( $args ) && isset( $args['response'] ) ? $args['response'] : 500 ) );
}
function wp_safe_redirect( $location, $status = 302 ) {
	$GLOBALS['kt']['redirects'][] = $location;
	return true;
}
function wp_redirect( $location, $status = 302 ) {
	return wp_safe_redirect( $location, $status );
}
function wp_next_scheduled( $hook, $args = array() ) {
	return $GLOBALS['kt']['scheduled'][ $hook . '|' . serialize( $args ) ] ?? false;
}
function wp_schedule_single_event( $time, $hook, $args = array() ) {
	$GLOBALS['kt']['scheduled'][ $hook . '|' . serialize( $args ) ] = $time;
	return true;
}
function is_serialized( $data, $strict = true ) {
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
	return 'N;' === $data || (bool) preg_match( '/^([adObisC]):/', $data );
}
function maybe_unserialize( $data ) {
	return is_serialized( $data ) ? @unserialize( trim( $data ) ) : $data; // phpcs:ignore
}
function maybe_serialize( $data ) {
	return is_array( $data ) || is_object( $data ) ? serialize( $data ) : $data;
}
function get_current_blog_id() {
	return (int) $GLOBALS['kt']['blog'];
}
function is_multisite() {
	return (bool) $GLOBALS['kt']['multisite'];
}
function get_bloginfo( $show = '' ) {
	return 'version' === $show ? '7.1.3' : 'Sitio B';
}
function get_allowed_mime_types( $user = null ) {
	return array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'mp4|m4v' => 'video/mp4', 'webm' => 'video/webm', 'mp3|m4a|m4b' => 'audio/mpeg', 'ogg|oga' => 'audio/ogg', 'wav' => 'audio/wav', 'csv' => 'text/csv', 'pdf' => 'application/pdf', 'json' => 'application/json' );
}
function wp_generate_password( $length = 12, $special = true ) {
	return substr( bin2hex( random_bytes( $length ) ), 0, $length );
}
function wp_create_nonce( $action = -1 ) {
	return substr( md5( (string) $action . '|' . get_current_user_id() ), 0, 10 );
}
function wp_verify_nonce( $nonce, $action = -1 ) {
	return hash_equals( wp_create_nonce( $action ), (string) $nonce ) ? 1 : false;
}

/** WP-CLI mínimo: los ganchos `before_invoke` y `error()` (que corta como en WP-CLI). */
class WP_CLI {
	public static $hooks = array();
	public static $runner;
	public static function add_hook( $when, $callback ) {
		self::$hooks[ $when ][] = $callback;
	}
	public static function add_command( $name, $callable, $args = array() ) {
		self::$hooks[ 'command:' . $name ][] = $callable;
	}
	public static function error( $message, $exit = true ) {
		throw new Kt_Exit( 'WP_CLI::error: ' . $message, 1 );
	}
	public static function get_runner() {
		return self::$runner;
	}
	/** Lo que haría WP-CLI antes de ejecutar un comando: los ganchos `before_invoke:<comando>`. */
	public static function invoke( $command, array $arguments ) {
		self::$runner = (object) array( 'arguments' => $arguments );
		foreach ( self::$hooks[ 'before_invoke:' . $command ] ?? array() as $callback ) {
			call_user_func( $callback );
		}
	}
}

/**
 * Autocarga de las clases del núcleo incluido con su propio cargador (`Elementor\Autoloader`, con su mapa de
 * `includes/`) y de los derivados de PRO (`ElementorPro\…` → digitalisimo-elements/) con la regla del cargador de
 * PRO: CamelCase y guiones bajos pasan a minúsculas con guiones. Sólo carga lo que la prueba no definió antes (sus
 * stubs mandan).
 */
function kt_core_autoload( $class ) {
	if ( 0 !== strpos( $class, 'ElementorPro\\' ) ) {
		return;
	}
	$relative = strtolower( preg_replace( array( '/([a-z])([A-Z])/', '/_/', '/\\\\/' ), array( '$1-$2', '-', '/' ), substr( $class, strlen( 'ElementorPro\\' ) ) ) );
	$file     = __DIR__ . '/../../../digitalisimo-elements/' . $relative . '.php';
	if ( is_file( $file ) ) {
		require_once $file;
	}
}
spl_autoload_register( 'kt_core_autoload' );
if ( ! defined( 'ELEMENTOR_PATH' ) ) {
	define( 'ELEMENTOR_PATH', realpath( __DIR__ . '/../../../digitalisimo-elements/elementor' ) . '/' );
}
require_once ELEMENTOR_PATH . 'includes/autoloader.php';
\Elementor\Autoloader::run();

/** Comprobación con mensaje en español. */
function kt_check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
}

/** Ejecuta y devuelve la salida Kt_Exit (wp_die, wp_send_json_*, WP_CLI::error) o null si no salió. */
function kt_exit_of( callable $fn ) {
	try {
		$fn();
	} catch ( Kt_Exit $exit ) {
		return $exit;
	}
	return null;
}

/** Un ZIP en disco con las entradas dadas (nombre => contenido). */
function kt_zip( $path, array $entries ) {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		throw new RuntimeException( 'No se pudo crear el ZIP de prueba ' . $path );
	}
	foreach ( $entries as $name => $content ) {
		$zip->addFromString( $name, is_string( $content ) ? $content : json_encode( $content ) );
	}
	$zip->close();
	return $path;
}

kt_reset();
