<?php
/**
 * Dobles mínimos de WordPress para las pruebas de la base de F7b (frente A): ganchos con prioridad, opciones con carga
 * automática, entradas y metas en memoria, metas de usuario, capacidades, cron, redirecciones y un `$wpdb` que anota
 * cada consulta y resuelve, sobre esos datos, las cuatro que hace la base (contadores, usos, lote del recorrido y
 * papelera). Lo cargan `tests/elements-f7b-module.php` y `tests/elements-f7b-usages.php`.
 */
namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}
	foreach ( array( 'ARRAY_A', 'ARRAY_N', 'OBJECT' ) as $f7b_const ) {
		if ( ! defined( $f7b_const ) ) {
			define( $f7b_const, $f7b_const );
		}
	}

	/** Estado de los dobles. */
	function f7b_reset_wp(): void {
		$GLOBALS['f7b'] = array(
			'hooks'     => array(),
			'did'       => array(),
			'options'   => array(),
			'autoload'  => array(),
			'reads'     => 0,
			'writes'    => array(),
			'posts'     => array(),
			'meta'      => array(),
			'meta_ops'  => array(),
			'user_meta' => array(),
			'user'      => 1,
			'can'       => null,
			'cron'      => array(),
			'redirect'  => '',
			'admin'     => false,
			'ajax'      => false,
			'current'   => '',
		);
		$GLOBALS['wpdb'] = new F7b_WPDB();
	}

	function f7b_check( $ok, string $message ): void {
		if ( ! $ok ) {
			throw new \RuntimeException( $message );
		}
		$GLOBALS['f7b_passed'] = ( $GLOBALS['f7b_passed'] ?? 0 ) + 1;
	}

	/** Entrada en memoria. */
	function f7b_post( int $id, string $type = 'page', string $status = 'publish', array $meta = array(), string $title = '', int $author = 1 ): void {
		$GLOBALS['f7b']['posts'][ $id ] = array(
			'type'   => $type,
			'status' => $status,
			'title'  => '' === $title ? 'Documento ' . $id : $title,
			'author' => $author,
		);
		foreach ( $meta as $key => $value ) {
			$GLOBALS['f7b']['meta'][ $id ][ $key ] = array( $value );
		}
	}

	/** Callbacks de un gancho: `[ callback, prioridad, argumentos ]`. */
	function f7b_hooks( string $hook ): array {
		return $GLOBALS['f7b']['hooks'][ $hook ] ?? array();
	}

	/** Ganchos con algún callback cuyo nombre empieza por `$prefix`. */
	function f7b_hooks_of( string $prefix ): array {
		$out = array();
		foreach ( $GLOBALS['f7b']['hooks'] as $hook => $entries ) {
			foreach ( $entries as $entry ) {
				$cb    = $entry[0];
				$owner = is_array( $cb ) ? ( is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0] ) : ( is_string( $cb ) ? $cb : 'closure' );
				if ( 0 === strpos( ltrim( $owner, '\\' ), $prefix ) ) {
					$out[ $hook ][] = $owner . ( is_array( $cb ) ? '::' . $cb[1] : '' );
				}
			}
		}
		return $out;
	}

	// ── Ganchos ──────────────────────────────────────────────────────────────────────────────────────────────────
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['f7b']['hooks'][ $hook ][] = array( $callback, (int) $priority, (int) $args );
		return true;
	}
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_action( $hook, $callback, $priority, $args );
	}
	function f7b_sorted( string $hook ): array {
		$entries = f7b_hooks( $hook );
		usort(
			$entries,
			static function ( $a, $b ) {
				return $a[1] <=> $b[1];
			}
		);
		return $entries;
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['f7b']['did'][ $hook ][] = $args;
		$previous                         = $GLOBALS['f7b']['current'];
		$GLOBALS['f7b']['current']        = $hook;
		foreach ( f7b_sorted( $hook ) as $entry ) {
			call_user_func_array( $entry[0], array_slice( $args, 0, $entry[2] ) );
		}
		$GLOBALS['f7b']['current'] = $previous;
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( f7b_sorted( $hook ) as $entry ) {
			$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, $entry[2] ) ) );
		}
		return $value;
	}
	function did_action( $hook ) {
		return count( $GLOBALS['f7b']['did'][ $hook ] ?? array() );
	}
	function current_action() {
		return $GLOBALS['f7b']['current'];
	}
	function is_admin() {
		return $GLOBALS['f7b']['admin'];
	}
	function wp_doing_ajax() {
		return $GLOBALS['f7b']['ajax'];
	}
	function rest_get_url_prefix() {
		return 'wp-json';
	}

	// ── Opciones ─────────────────────────────────────────────────────────────────────────────────────────────────
	function wp_load_alloptions() {
		$out = array();
		foreach ( $GLOBALS['f7b']['options'] as $name => $value ) {
			if ( ! empty( $GLOBALS['f7b']['autoload'][ $name ] ) ) {
				$out[ $name ] = $value;
			}
		}
		return $out;
	}
	function get_option( $name, $default = false ) {
		++$GLOBALS['f7b']['reads'];
		return array_key_exists( $name, $GLOBALS['f7b']['options'] ) ? $GLOBALS['f7b']['options'][ $name ] : $default;
	}
	function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
		if ( array_key_exists( $name, $GLOBALS['f7b']['options'] ) ) {
			return false;
		}
		$GLOBALS['f7b']['options'][ $name ]  = $value;
		$GLOBALS['f7b']['autoload'][ $name ] = ( true === $autoload || 'yes' === $autoload || 'on' === $autoload );
		$GLOBALS['f7b']['writes'][]          = array( 'add', $name, $value );
		return true;
	}
	function update_option( $name, $value, $autoload = null ) {
		if ( ! array_key_exists( $name, $GLOBALS['f7b']['options'] ) ) {
			return add_option( $name, $value, '', null === $autoload ? true : $autoload );
		}
		$GLOBALS['f7b']['options'][ $name ] = $value;
		if ( null !== $autoload ) {
			$GLOBALS['f7b']['autoload'][ $name ] = (bool) $autoload;
		}
		$GLOBALS['f7b']['writes'][] = array( 'update', $name, $value );
		return true;
	}
	function wp_set_option_autoload( $name, $autoload ) {
		$GLOBALS['f7b']['autoload'][ $name ] = (bool) $autoload;
		return true;
	}
	function maybe_unserialize( $value ) {
		return $value;
	}
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}

	// ── Entradas y metas ─────────────────────────────────────────────────────────────────────────────────────────
	function get_post( $id ) {
		$id = is_object( $id ) ? (int) $id->ID : (int) $id;
		if ( ! isset( $GLOBALS['f7b']['posts'][ $id ] ) ) {
			return null;
		}
		$p                    = $GLOBALS['f7b']['posts'][ $id ];
		$post                 = new \stdClass();
		$post->ID             = $id;
		$post->post_type      = $p['type'];
		$post->post_status    = $p['status'];
		$post->post_title     = $p['title'];
		$post->post_author    = $p['author'];
		return $post;
	}
	function get_post_type( $id ) {
		return $GLOBALS['f7b']['posts'][ (int) $id ]['type'] ?? false;
	}
	function get_post_status( $id ) {
		return $GLOBALS['f7b']['posts'][ (int) $id ]['status'] ?? false;
	}
	function get_the_title( $id ) {
		return $GLOBALS['f7b']['posts'][ (int) $id ]['title'] ?? '';
	}
	function post_type_supports( $type, $feature ) {
		return 'elementor' === $feature && in_array( $type, array( 'page', 'post' ), true );
	}
	function wp_is_post_revision( $id ) {
		return 'revision' === get_post_type( $id ) ? 1 : false;
	}
	function wp_is_post_autosave( $id ) {
		return 'revision' === get_post_type( $id ) && 'autosave' === ( $GLOBALS['f7b']['posts'][ (int) $id ]['title'] ?? '' ) ? 1 : false;
	}
	function get_post_meta( $id, $key = '', $single = false ) {
		$GLOBALS['f7b']['meta_ops'][] = array( 'get', (int) $id, $key );
		$values                       = $GLOBALS['f7b']['meta'][ (int) $id ][ $key ] ?? array();
		return $single ? ( $values[0] ?? '' ) : $values;
	}
	function add_post_meta( $id, $key, $value, $unique = false ) {
		$GLOBALS['f7b']['meta_ops'][]                 = array( 'add', (int) $id, $key, $value );
		$GLOBALS['f7b']['meta'][ (int) $id ][ $key ][] = $value;
		return 1;
	}
	function delete_post_meta( $id, $key, $value = '' ) {
		$GLOBALS['f7b']['meta_ops'][] = array( 'delete', (int) $id, $key );
		unset( $GLOBALS['f7b']['meta'][ (int) $id ][ $key ] );
		return true;
	}
	/** Como `update_metadata()`: dispara `update_post_meta` (antes) y `updated_post_meta`/`added_post_meta` (después). */
	function update_post_meta( $id, $key, $value ) {
		$exists = isset( $GLOBALS['f7b']['meta'][ (int) $id ][ $key ] );
		if ( $exists ) {
			do_action( 'update_post_meta', 7, (int) $id, $key, $value );
		}
		$GLOBALS['f7b']['meta'][ (int) $id ][ $key ] = array( $value );
		do_action( $exists ? 'updated_post_meta' : 'added_post_meta', 7, (int) $id, $key, $value );
		return true;
	}

	// ── Usuarios ─────────────────────────────────────────────────────────────────────────────────────────────────
	function get_current_user_id() {
		return $GLOBALS['f7b']['user'];
	}
	function current_user_can( $cap, ...$args ) {
		$can = $GLOBALS['f7b']['can'];
		return null === $can ? true : (bool) $can( $cap, ...$args );
	}
	function get_user_meta( $user, $key = '', $single = false ) {
		$value = $GLOBALS['f7b']['user_meta'][ (int) $user ][ $key ] ?? null;
		return $single ? ( null === $value ? '' : $value ) : ( null === $value ? array() : array( $value ) );
	}
	function update_user_meta( $user, $key, $value ) {
		$GLOBALS['f7b']['user_meta'][ (int) $user ][ $key ] = $value;
		return true;
	}
	function delete_user_meta( $user, $key ) {
		unset( $GLOBALS['f7b']['user_meta'][ (int) $user ][ $key ] );
		return true;
	}

	// ── Textos, escapes, URLs ───────────────────────────────────────────────────────────────────────────────────
	function __( $text, $domain = 'default' ) {
		return $text;
	}
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return esc_html( $text );
	}
	function esc_url( $url ) {
		return esc_html( $url );
	}
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
	function sanitize_text_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
	function wp_unslash( $value ) {
		return $value;
	}
	function absint( $value ) {
		return abs( (int) $value );
	}
	function wp_verify_nonce( $nonce, $action ) {
		return 'nonce-' . $action === $nonce ? 1 : false;
	}
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
	function add_query_arg( $args, $url ) {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
	}
	function wp_get_referer() {
		return $GLOBALS['f7b']['referer'] ?? false;
	}
	function wp_safe_redirect( $url ) {
		$GLOBALS['f7b']['redirect'] = $url;
		return true;
	}
	function get_edit_post_link( $id ) {
		return 'https://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit';
	}
	function get_post_type_object( $type ) {
		return (object) array( 'labels' => (object) array( 'singular_name' => ucfirst( (string) $type ) ) );
	}
	function get_post_status_object( $status ) {
		return (object) array( 'label' => ucfirst( (string) $status ) );
	}
	function add_submenu_page( ...$args ) {
		$GLOBALS['f7b']['submenu'][] = $args;
		return 'hook';
	}
	function wp_add_inline_script( $handle, $script ) {
		$GLOBALS['f7b']['inline'][] = array( $handle, $script );
		return true;
	}

	// ── Cron ─────────────────────────────────────────────────────────────────────────────────────────────────────
	function wp_schedule_single_event( $time, $hook, $args = array() ) {
		$GLOBALS['f7b']['cron'][] = $hook;
		return true;
	}
	function wp_next_scheduled( $hook ) {
		return in_array( $hook, $GLOBALS['f7b']['cron'], true ) ? time() : false;
	}
	/** Corre un evento de cron programado (lo quita de la cola, como WordPress). */
	function f7b_run_cron( string $hook ): bool {
		$index = array_search( $hook, $GLOBALS['f7b']['cron'], true );
		if ( false === $index ) {
			return false;
		}
		array_splice( $GLOBALS['f7b']['cron'], $index, 1 );
		do_action( $hook );
		return true;
	}

	/** `$wpdb` que anota cada consulta y resuelve sobre las entradas y metas en memoria. */
	class F7b_WPDB {
		public $posts    = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public $log      = array();

		public function esc_like( $text ) {
			return addcslashes( (string) $text, '_%\\' );
		}
		public function prepare( $sql, ...$args ) {
			return array( $sql, $args );
		}
		private static function unlike( string $pattern ): string {
			return stripcslashes( trim( $pattern, '%' ) );
		}
		private function data( int $id ): string {
			$value = $GLOBALS['f7b']['meta'][ $id ]['_elementor_data'][0] ?? null;
			return is_string( $value ) ? $value : ( null === $value ? '' : json_encode( $value ) );
		}
		public function get_row( $query, $output = OBJECT ) {
			$this->log[] = $query;
			list( $sql, $args ) = $query;
			$sums               = array_fill( 0, count( $args ), 0 );
			preg_match( "/post_status IN \(([^)]+)\)/", $sql, $found );
			$statuses = array_map( static function ( $s ) { return trim( $s, " '" ); }, explode( ',', $found[1] ?? '' ) );
			foreach ( $GLOBALS['f7b']['posts'] as $id => $post ) {
				if ( 'revision' === $post['type'] || ! in_array( $post['status'], $statuses, true ) || ! isset( $GLOBALS['f7b']['meta'][ $id ]['_elementor_data'] ) ) {
					continue;
				}
				foreach ( $args as $i => $pattern ) {
					$sums[ $i ] += false !== strpos( $this->data( $id ), self::unlike( $pattern ) ) ? 1 : 0;
				}
			}
			return array_map( 'strval', $sums );
		}
		public function get_results( $query, $output = OBJECT ) {
			$this->log[]        = $query;
			list( $sql, $args ) = $query;
			$rows               = array();
			if ( false !== strpos( $sql, 'pm.meta_value IN' ) ) {
				$key    = array_shift( $args );
				$limit  = (int) array_pop( $args );
				preg_match( "/post_status IN \(([^)]+)\)/", $sql, $found );
				$statuses = array_map( static function ( $s ) { return trim( $s, " '" ); }, explode( ',', $found[1] ?? '' ) );
				ksort( $GLOBALS['f7b']['posts'] );
				foreach ( $GLOBALS['f7b']['posts'] as $id => $post ) {
					if ( 'revision' === $post['type'] || ! in_array( $post['status'], $statuses, true ) ) {
						continue;
					}
					foreach ( $GLOBALS['f7b']['meta'][ $id ][ $key ] ?? array() as $value ) {
						if ( in_array( $value, $args, true ) ) {
							$rows[] = array( 'ref' => $value, 'id' => (string) $id, 'status' => $post['status'] );
						}
					}
				}
				return array_slice( $rows, 0, $limit );
			}
			if ( false !== strpos( $sql, 'p.ID > %d' ) ) {
				list( $after, $limit ) = $args;
				ksort( $GLOBALS['f7b']['posts'] );
				foreach ( $GLOBALS['f7b']['posts'] as $id => $post ) {
					if ( $id <= $after || 'revision' === $post['type'] || in_array( $post['status'], array( 'auto-draft', 'inherit' ), true ) || ! isset( $GLOBALS['f7b']['meta'][ $id ]['_elementor_data'] ) ) {
						continue;
					}
					$rows[] = array( 'id' => (string) $id, 'data' => $this->data( $id ) );
				}
				return array_slice( $rows, 0, $limit );
			}
			throw new \RuntimeException( 'Consulta inesperada: ' . $sql );
		}
		public function get_col( $query ) {
			$this->log[]        = $query;
			list( $sql, $args ) = $query;
			$out                = array();
			foreach ( $GLOBALS['f7b']['posts'] as $id => $post ) {
				if ( $post['type'] === $args[0] && 'trash' === $post['status'] ) {
					$out[] = (string) $id;
				}
			}
			return $out;
		}
	}

	f7b_reset_wp();
}
