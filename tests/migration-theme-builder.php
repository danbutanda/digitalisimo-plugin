<?php
/**
 * Theme Builder de Element Pack en DIGITALÍSIMO Migración (F8, frente G): traducción de condiciones (tabla de datos con
 * sus casos), copias en borrador con huella y prioridad manual, publicación perezosa sin hueco ni duplicados
 * (`Publisher`: objetivo, `sync()` en los dos sentidos e idempotente, filtro `get_post_status`, guarda de publicación a
 * mano), las cinco condiciones antes de desactivar Element Pack y la reversión que omite lo editado. WordPress mínimo
 * propio (posts, metas, opciones, ganchos y un `$wpdb` que entiende las consultas de estas clases) y datos sintéticos
 * de `tests/sources/migration/theme-builder*.json`.
 */

namespace Digitalisimo\Elements\Design\Theme_Builder {
	/** Prioridad manual de F7a: registra a quién se reindexa. */
	final class Priority {
		public static $calls = array();
		public static function reindex( int $template_id, $value = null ): void {
			self::$calls[] = $template_id;
		}
	}
}

namespace Digitalisimo\Elements\Legacy\Platform {
	/** CSS global del frente H: la revalidación al publicar se simula. */
	final class Snippets {
		public static $deny = array();
		public static function revalidate( int $id ): bool {
			return ! in_array( $id, self::$deny, true );
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'EMPTY_TRASH_DAYS', 30 );
	error_reporting( E_ALL );

	$GLOBALS['checks']   = 0;
	$GLOBALS['failures'] = array();
	function check( $ok, $message ) {
		++$GLOBALS['checks'];
		if ( ! $ok ) {
			$GLOBALS['failures'][] = $message;
			fwrite( STDERR, "FALLA: $message\n" );
		}
	}
	function same( $a, $b, $message ) {
		check( $a === $b, $message . ' (esperado ' . json_encode( $b, JSON_UNESCAPED_UNICODE ) . ', obtenido ' . json_encode( $a, JSON_UNESCAPED_UNICODE ) . ')' );
	}

	/* ================================================================ WordPress mínimo */

	function t_reset() {
		$GLOBALS['t'] = array(
			'blog'        => 1,
			'stack'       => array(),
			'multisite'   => true,
			'options'     => array( 1 => array(), 2 => array() ),
			'posts'       => array( 1 => array(), 2 => array() ),
			'meta'        => array( 1 => array(), 2 => array() ),
			'next_id'     => 1000,
			'hooks'       => array(),
			'did'         => array(),
			'user'        => 1,
			'transients'  => array(),
			'post_types'  => array(),
			'taxonomies'  => array(),
			'terms'       => array(),
			'term_counts' => array(),
			'users'       => array(),
			'transitions' => array(),
			'corrupt'     => '',
			'stylesheet'  => '/var/tmp/digi-f8/no-theme',
		);
	}
	t_reset();
	$GLOBALS['wpdb'] = null;

	function __( $text, $domain = 'default' ) { return $text; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function esc_url( $v ) { return esc_html( $v ); }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_generate_password( $n = 12, $s = true ) { return 'tok' . mt_rand( 100000, 999999 ) . mt_rand( 100000, 999999 ); }
	function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
	function is_wp_error( $v ) { return $v instanceof WP_Error; }
	final class WP_Error {}
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function wp_slash( $v ) {
		if ( is_array( $v ) ) {
			return array_map( 'wp_slash', $v );
		}
		return is_string( $v ) ? addslashes( $v ) : $v;
	}
	function wp_unslash( $v ) {
		if ( is_array( $v ) ) {
			return array_map( 'wp_unslash', $v );
		}
		return is_string( $v ) ? stripslashes( $v ) : $v;
	}

	// Sitios.
	function get_current_blog_id() { return $GLOBALS['t']['blog']; }
	function is_multisite() { return $GLOBALS['t']['multisite']; }
	function switch_to_blog( $id ) { $GLOBALS['t']['stack'][] = $GLOBALS['t']['blog']; $GLOBALS['t']['blog'] = (int) $id; }
	function restore_current_blog() { $GLOBALS['t']['blog'] = array_pop( $GLOBALS['t']['stack'] ); }
	function ms_is_switched() { return ! empty( $GLOBALS['t']['stack'] ); }

	// Ganchos.
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['t']['hooks'][ $hook ][ $priority ][] = array( $cb, $args ); return true; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
	function has_hook( $hook ) { return ! empty( $GLOBALS['t']['hooks'][ $hook ] ); }
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['t']['hooks'][ $hook ] ) ) {
			return $value;
		}
		ksort( $GLOBALS['t']['hooks'][ $hook ] );
		foreach ( $GLOBALS['t']['hooks'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $entry ) {
				$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) );
			}
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['t']['did'][ $hook ] = ( $GLOBALS['t']['did'][ $hook ] ?? 0 ) + 1;
		if ( empty( $GLOBALS['t']['hooks'][ $hook ] ) ) {
			return;
		}
		ksort( $GLOBALS['t']['hooks'][ $hook ] );
		foreach ( $GLOBALS['t']['hooks'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $entry ) {
				call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) );
			}
		}
	}
	function did_action( $hook ) { return $GLOBALS['t']['did'][ $hook ] ?? 0; }

	// Opciones.
	function &opts() { return $GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ]; }
	function get_option( $k, $d = false ) { $o = &opts(); return array_key_exists( $k, $o ) ? $o[ $k ] : $d; }
	function update_option( $k, $v, $a = null ) { $o = &opts(); $o[ $k ] = $v; return true; }
	function add_option( $k, $v = '', $x = '', $a = true ) { $o = &opts(); if ( array_key_exists( $k, $o ) ) { return false; } $o[ $k ] = $v; return true; }
	function delete_option( $k ) { $o = &opts(); unset( $o[ $k ] ); return true; }
	function wp_cache_delete( $k, $g = '' ) { return true; }
	function set_transient( $k, $v, $e = 0 ) { $GLOBALS['t']['transients'][ $k ] = $v; return true; }
	function get_transient( $k ) { return $GLOBALS['t']['transients'][ $k ] ?? false; }
	function delete_transient( $k ) { unset( $GLOBALS['t']['transients'][ $k ] ); return true; }

	// Usuarios.
	function get_current_user_id() { return $GLOBALS['t']['user']; }
	function current_user_can( $cap ) { return true; }
	function get_userdata( $id ) { return in_array( (int) $id, $GLOBALS['t']['users'], true ) ? (object) array( 'ID' => (int) $id ) : false; }

	// Entradas.
	function &posts() { return $GLOBALS['t']['posts'][ $GLOBALS['t']['blog'] ]; }
	function &metas() { return $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ]; }
	function get_post( $id ) {
		$id = is_object( $id ) ? (int) $id->ID : (int) $id;
		$p  = &posts();
		return isset( $p[ $id ] ) ? (object) $p[ $id ] : null;
	}
	function get_post_status( $id ) {
		$post = get_post( $id );
		return $post ? apply_filters( 'get_post_status', $post->post_status, $post ) : false;
	}
	function get_post_type( $id ) { $post = get_post( $id ); return $post ? $post->post_type : false; }
	function get_the_title( $id ) { $post = get_post( $id ); return $post ? $post->post_title : ''; }
	function get_edit_post_link( $id, $c = '' ) { return 'https://s.test/wp-admin/post.php?post=' . (int) $id; }
	function admin_url( $p = '' ) { return 'https://s.test/wp-admin/' . $p; }
	function wp_is_post_revision( $id ) { return false; }
	function clean_post_cache( $id ) {}
	function wp_transition_post_status( $new, $old, $post ) {
		$GLOBALS['t']['transitions'][] = array( (int) $post->ID, $old, $new );
		do_action( 'transition_post_status', $new, $old, $post );
	}
	function wp_insert_post( $postarr, $wp_error = false ) {
		$postarr = wp_unslash( $postarr );
		$p       = &posts();
		$update  = ! empty( $postarr['ID'] ) && isset( $p[ (int) $postarr['ID'] ] );
		$old     = $update ? $p[ (int) $postarr['ID'] ] : array( 'post_status' => 'new' );
		$data    = array_merge( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => '', 'post_content' => '', 'post_author' => 0, 'post_parent' => 0 ), $update ? $old : array() );
		foreach ( array( 'post_type', 'post_status', 'post_title', 'post_content', 'post_author', 'post_parent' ) as $field ) {
			if ( array_key_exists( $field, $postarr ) ) {
				$data[ $field ] = $postarr[ $field ];
			}
		}
		$data = apply_filters( 'wp_insert_post_data', $data, $postarr, $postarr, $update );
		$id   = $update ? (int) $postarr['ID'] : ++$GLOBALS['t']['next_id'];
		$data['ID'] = $id;
		$p[ $id ]   = $data;
		foreach ( (array) ( $postarr['meta_input'] ?? array() ) as $key => $value ) {
			update_post_meta( $id, $key, wp_slash( $value ) );
		}
		if ( $old['post_status'] !== $data['post_status'] ) {
			wp_transition_post_status( $data['post_status'], $old['post_status'], (object) $data );
		}
		return $id;
	}
	function wp_update_post( $postarr ) {
		$old = get_post( (int) $postarr['ID'] );
		if ( ! $old ) {
			return 0;
		}
		return wp_insert_post( array_merge( wp_slash( (array) $old ), $postarr ) );
	}
	function wp_trash_post( $id ) {
		$p = &posts();
		if ( ! isset( $p[ (int) $id ] ) ) {
			return false;
		}
		$old                             = $p[ (int) $id ]['post_status'];
		$p[ (int) $id ]['post_status']   = 'trash';
		wp_transition_post_status( 'trash', $old, (object) $p[ (int) $id ] );
		do_action( 'trashed_post', (int) $id );
		return true;
	}
	function wp_delete_post( $id ) {
		$p = &posts();
		$m = &metas();
		unset( $p[ (int) $id ], $m[ (int) $id ] );
		do_action( 'deleted_post', (int) $id );
	}
	function wp_set_object_terms( $id, $terms, $taxonomy ) { $m = &metas(); $m[ (int) $id ][ '_terms_' . $taxonomy ] = $terms; return true; }

	// Metas.
	function get_post_meta( $id, $key = '', $single = false ) { $m = &metas(); return $m[ (int) $id ][ $key ] ?? ''; }
	function update_post_meta( $id, $key, $value ) {
		$value = wp_unslash( $value );
		if ( '' !== $GLOBALS['t']['corrupt'] && '_elementor_data' === $key && is_string( $value ) && false !== strpos( $value, $GLOBALS['t']['corrupt'] ) ) {
			$value = str_replace( '\\', '', $value ); // Un guardado que pierde las barras invertidas.
		}
		$m                       = &metas();
		$m[ (int) $id ][ $key ] = $value;
		return true;
	}
	function add_post_meta( $id, $key, $value ) { return update_post_meta( $id, $key, $value ); }
	function delete_post_meta( $id, $key ) { $m = &metas(); unset( $m[ (int) $id ][ $key ] ); return true; }

	// Tipos y taxonomías.
	function post_type_exists( $type ) { return isset( $GLOBALS['t']['post_types'][ $type ] ); }
	function taxonomy_exists( $tax ) { return isset( $GLOBALS['t']['taxonomies'][ $tax ] ); }
	function get_post_types( $args = array(), $output = 'names' ) {
		$out = array();
		foreach ( $GLOBALS['t']['post_types'] as $name => $spec ) {
			foreach ( $args as $key => $value ) {
				if ( (bool) ( $spec[ $key ] ?? false ) !== (bool) $value ) {
					continue 2;
				}
			}
			$out[ $name ] = 'objects' === $output ? (object) ( array( 'name' => $name, 'label' => $name ) + $spec ) : $name;
		}
		return $out;
	}
	function get_object_taxonomies( $type, $output = 'names' ) {
		$out = array();
		foreach ( $GLOBALS['t']['taxonomies'] as $name => $spec ) {
			if ( in_array( $type, $spec['object_type'], true ) ) {
				$out[ $name ] = 'objects' === $output ? (object) ( array( 'name' => $name ) + $spec ) : $name;
			}
		}
		return $out;
	}
	function get_post_type_archive_link( $type ) {
		if ( 'post' === $type ) {
			return 'https://s.test/';
		}
		return ! empty( $GLOBALS['t']['post_types'][ $type ]['archive'] ) ? 'https://s.test/' . $type . '/' : false;
	}
	function is_post_type_hierarchical( $type ) { return ! empty( $GLOBALS['t']['post_types'][ $type ]['hierarchical'] ); }
	function is_taxonomy_hierarchical( $tax ) { return ! empty( $GLOBALS['t']['taxonomies'][ $tax ]['hierarchical'] ); }
	function is_object_in_taxonomy( $type, $tax ) { return isset( $GLOBALS['t']['taxonomies'][ $tax ] ) && in_array( $type, $GLOBALS['t']['taxonomies'][ $tax ]['object_type'], true ); }
	function get_term( $id, $tax = '' ) {
		$term = $GLOBALS['t']['terms'][ (int) $id ] ?? null;
		if ( ! $term || ( '' !== $tax && $term['taxonomy'] !== $tax ) ) {
			return null;
		}
		return (object) ( array( 'term_id' => (int) $id ) + $term );
	}
	function wp_count_terms( $args ) {
		$tax = is_array( $args ) ? $args['taxonomy'] : $args;
		if ( isset( $GLOBALS['t']['term_counts'][ $tax ] ) ) {
			return $GLOBALS['t']['term_counts'][ $tax ];
		}
		$n = 0;
		foreach ( $GLOBALS['t']['terms'] as $term ) {
			$n += $term['taxonomy'] === $tax ? 1 : 0;
		}
		return $n;
	}
	function get_stylesheet_directory() { return $GLOBALS['t']['stylesheet']; }
	function get_template_directory() { return $GLOBALS['t']['stylesheet']; }

	/** `$wpdb` mínimo: entiende las consultas de Tb_Conditions, Theme_Builder y Publisher. */
	final class Fake_Wpdb {
		public $posts    = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public $options  = 'wp_options';
		public $log      = array();
		public function esc_like( $t ) { return addcslashes( $t, '_%\\' ); }
		public function prepare( $sql, ...$args ) { return array( 'sql' => $sql, 'args' => 1 === count( $args ) && is_array( $args[0] ) ? $args[0] : $args ); }
		/** LIKE de SQL (con `\` de escape, sin distinguir mayúsculas como la intercalación de las opciones). */
		public static function like( $pattern, $value ) {
			$regex = '';
			$len   = strlen( $pattern );
			for ( $i = 0; $i < $len; $i++ ) {
				$c = $pattern[ $i ];
				if ( '\\' === $c && $i + 1 < $len ) {
					$regex .= preg_quote( $pattern[ ++$i ], '/' );
				} elseif ( '%' === $c ) {
					$regex .= '.*';
				} elseif ( '_' === $c ) {
					$regex .= '.';
				} else {
					$regex .= preg_quote( $c, '/' );
				}
			}
			return 1 === preg_match( '/^' . $regex . '$/is', (string) $value );
		}
		public function query( $q ) {
			$sql = $q['sql'];
			$a   = $q['args'];
			$this->log[] = $sql;
			$o = &opts();
			if ( 0 === strpos( $sql, 'INSERT IGNORE INTO wp_options' ) ) {
				if ( array_key_exists( $a[0], $o ) ) {
					return 0;
				}
				$o[ $a[0] ] = $a[1];
				return 1;
			}
			if ( 0 === strpos( $sql, 'UPDATE wp_options SET option_value = %s WHERE option_name = %s AND option_value = %s' ) ) {
				if ( ( $o[ $a[1] ] ?? null ) === $a[2] ) {
					$o[ $a[1] ] = $a[0];
					return 1;
				}
				return 0;
			}
			if ( 0 === strpos( $sql, 'DELETE FROM wp_options WHERE option_name = %s AND option_value = %s' ) ) {
				if ( ( $o[ $a[0] ] ?? null ) === $a[1] ) {
					unset( $o[ $a[0] ] );
					return 1;
				}
				return 0;
			}
			if ( 0 === strpos( $sql, 'UPDATE wp_posts SET post_status = %s WHERE ID = %d AND post_status = %s' ) ) {
				$p = &posts();
				if ( isset( $p[ (int) $a[1] ] ) && $p[ (int) $a[1] ]['post_status'] === $a[2] ) {
					$p[ (int) $a[1] ]['post_status'] = $a[0];
					return 1;
				}
				return 0;
			}
			throw new RuntimeException( 'consulta no simulada: ' . $sql );
		}
		public function get_var( $q ) {
			if ( 0 === strpos( $q['sql'], 'SELECT option_value FROM wp_options WHERE option_name = %s' ) ) {
				$o = &opts();
				return $o[ $q['args'][0] ] ?? null;
			}
			throw new RuntimeException( 'consulta no simulada: ' . $q['sql'] );
		}
		public function get_results( $q ) {
			$this->log[] = $q['sql'];
			if ( 0 === strpos( $q['sql'], 'SELECT option_name, option_value FROM wp_options WHERE option_name LIKE %s' ) ) {
				$out = array();
				foreach ( opts() as $name => $value ) {
					if ( self::like( $q['args'][0], $name ) ) {
						$out[] = (object) array( 'option_name' => $name, 'option_value' => (string) ( is_scalar( $value ) ? $value : '' ) );
					}
				}
				return $out;
			}
			throw new RuntimeException( 'consulta no simulada: ' . $q['sql'] );
		}
		public function get_col( $q ) {
			$sql = $q['sql'];
			$a   = $q['args'];
			$this->log[] = $sql;
			$p   = &posts();
			$m   = &metas();
			$out = array();
			$skip = array( 'trash', 'auto-draft', 'inherit' );
			if ( false !== strpos( $sql, 'SELECT ID FROM wp_posts WHERE post_type = %s AND post_status NOT IN' ) && false !== strpos( $sql, 'AND ID > %d' ) ) {
				foreach ( $p as $id => $post ) {
					if ( $post['post_type'] === $a[0] && ! in_array( $post['post_status'], $skip, true ) && $id > (int) $a[1] ) {
						$out[] = $id;
					}
				}
				sort( $out );
				return array_slice( $out, 0, (int) $a[2] );
			}
			if ( false !== strpos( $sql, "m.meta_value IN ( 'themes|header', 'themes|footer' )" ) ) {
				foreach ( $p as $id => $post ) {
					$type = $m[ $id ][ $a[0] ] ?? '';
					if ( $post['post_type'] === $a[1] && 'publish' === $post['post_status'] && in_array( $type, array( 'themes|header', 'themes|footer' ), true ) ) {
						$out[] = $id;
					}
				}
				sort( $out );
				return $out;
			}
			if ( 0 === strpos( $sql, 'SELECT post_id FROM wp_postmeta WHERE meta_key = %s AND meta_value LIKE %s' ) ) {
				foreach ( $m as $id => $values ) {
					if ( isset( $values[ $a[0] ] ) && self::like( $a[1], is_string( $values[ $a[0] ] ) ? $values[ $a[0] ] : serialize( $values[ $a[0] ] ) ) ) {
						$out[] = $id;
					}
				}
				rsort( $out );
				return array_slice( $out, 0, 5 );
			}
			if ( false !== strpos( $sql, 'post_content LIKE %s OR post_content LIKE %s' ) ) {
				foreach ( $p as $id => $post ) {
					if ( 'page' !== $post['post_type'] || in_array( $post['post_status'], $skip, true ) ) {
						continue;
					}
					foreach ( $a as $pattern ) {
						if ( self::like( $pattern, $post['post_content'] ) ) {
							$out[] = $id;
							break;
						}
					}
				}
				sort( $out );
				return $out;
			}
			if ( false !== strpos( $sql, "m.meta_value NOT IN ( '', 'default' ) AND m.meta_value NOT LIKE %s" ) ) {
				foreach ( $p as $id => $post ) {
					$template = $m[ $id ][ $a[0] ] ?? null;
					if ( 'page' === $post['post_type'] && ! in_array( $post['post_status'], $skip, true ) && null !== $template && ! in_array( $template, array( '', 'default' ), true ) && ! self::like( $a[1], $template ) ) {
						$out[] = $id;
					}
				}
				sort( $out );
				return $out;
			}
			throw new RuntimeException( 'consulta no simulada: ' . $sql );
		}
	}
	$GLOBALS['wpdb'] = new Fake_Wpdb();

	/* ================================================================ Carga */

	$mig = dirname( __DIR__ ) . '/digitalisimo-migration/legacy/';
	require_once $mig . 'class-context.php';
	require_once $mig . 'class-report.php';
	require_once $mig . 'platform/class-registry.php';
	require_once $mig . 'platform/class-documents.php';
	require_once $mig . 'platform/class-tb-conditions.php';
	require_once $mig . 'platform/class-publisher.php';
	require_once $mig . 'platform/class-theme-builder.php';

	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Platform\Registry;
	use Digitalisimo\Elements\Legacy\Platform\Documents;
	use Digitalisimo\Elements\Legacy\Platform\Tb_Conditions;
	use Digitalisimo\Elements\Legacy\Platform\Publisher;
	use Digitalisimo\Elements\Legacy\Platform\Theme_Builder;
	use Digitalisimo\Elements\Design\Theme_Builder\Priority;
	use Digitalisimo\Elements\Legacy\Platform\Snippets;

	$site  = json_decode( file_get_contents( __DIR__ . '/sources/migration/theme-builder-site.json' ), true );
	$cases = json_decode( file_get_contents( __DIR__ . '/sources/migration/theme-builder-conditions.json' ), true );
	check( is_array( $site ) && is_array( $cases ), 'los fixtures se leen' );

	/** Monta el sitio sintético en el blog en curso. */
	function load_site( array $site, bool $woo ) {
		$GLOBALS['t']['post_types'] = $site['post_types'] + ( $woo ? $site['woo_post_types'] : array() );
		$GLOBALS['t']['taxonomies'] = $site['taxonomies'] + ( $woo ? $site['woo_taxonomies'] : array() );
		$GLOBALS['t']['terms']      = array();
		foreach ( $site['terms'] as $id => $term ) {
			if ( isset( $GLOBALS['t']['taxonomies'][ $term['taxonomy'] ] ) ) {
				$GLOBALS['t']['terms'][ (int) $id ] = $term;
			}
		}
		$GLOBALS['t']['users'] = $site['users'];
		$p                     = &posts();
		$m                     = &metas();
		foreach ( $site['posts'] as $post ) {
			$p[ $post['ID'] ] = array(
				'ID'           => $post['ID'],
				'post_type'    => $post['post_type'],
				'post_status'  => $post['post_status'],
				'post_title'   => $post['post_title'],
				'post_content' => $post['post_content'] ?? '',
				'post_author'  => $post['post_author'] ?? 1,
				'post_parent'  => $post['post_parent'] ?? 0,
			);
			foreach ( $post['meta'] ?? array() as $key => $value ) {
				if ( is_array( $value ) && array_key_exists( '__json', $value ) ) {
					$value = json_encode( $value['__json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				}
				$m[ $post['ID'] ][ $key ] = $value;
			}
		}
		foreach ( $site['options'] as $pair ) {
			update_option( $pair[0], $pair[1] );
		}
	}

	/** Una «petición» nueva: ganchos vacíos, memoria olvidada y el arranque de Migración (`Publisher::init()`). */
	function new_request( array $env, bool $migration = true ) {
		$GLOBALS['t']['hooks'] = array();
		Publisher::reset();
		Publisher::set_environment( $env + array( 'ep' => false, 'conflict' => false, 'builder' => true ) );
		Tb_Conditions::reset();
		Theme_Builder::reset();
		Registry::flush_cache();
		if ( $migration ) {
			Publisher::init();
			do_action( 'init' );
		}
	}

	/** Fila compacta «polaridad:a/sub:ids» → fila del JSON de Element Pack. */
	function ep_row( string $compact ): array {
		$parts    = explode( ':', $compact, 3 );
		$row      = array( 'polarity' => $parts[0] );
		$location = $parts[1] ?? '';
		if ( false === strpos( $location, '/' ) ) {
			$row['condition_a'] = $location;
		} else {
			list( $a, $sub )    = explode( '/', $location, 2 );
			$row['condition_a'] = $a;
			$key                = array( 'singular' => 'condition_singular', 'archive' => 'condition_archive', 'woocommerce' => 'condition_woo' )[ $a ] ?? 'condition_singular';
			$row[ $key ]        = $sub;
		}
		if ( isset( $parts[2] ) ) {
			$row['condition_singular_id'] = $parts[2];
		}
		return $row;
	}

	function run_cases( array $list, string $phase ) {
		foreach ( $list as $case ) {
			$GLOBALS['t']['term_counts'] = $case['terms'] ?? array();
			Tb_Conditions::reset();
			$rows = Tb_Conditions::normalize( array_map( 'ep_row', $case['rows'] ), (string) ( $case['v'] ?? '10.0.1' ) );
			$out  = Tb_Conditions::translate( Tb_Conditions::canonical( $case['type'] ), $rows, (string) ( $case['v'] ?? '10.0.1' ) );
			$name = $phase . ' · ' . $case['n'];
			if ( isset( $case['fail'] ) ) {
				same( $out['fail'], $case['fail'], $name . ': falla' );
				same( $out['conditions'], array(), $name . ': sin condiciones al fallar' );
			} else {
				same( $out['fail'], '', $name . ': no falla' );
				same( $out['conditions'], $case['c'], $name . ': condiciones' );
			}
			$codes = array_column( $out['items'], 'code' );
			foreach ( $case['codes'] ?? array() as $code ) {
				check( in_array( $code, $codes, true ), $name . ': informa ' . $code . ' (' . implode( ',', $codes ) . ')' );
			}
			foreach ( $out['conditions'] as $condition ) {
				check( 1 === preg_match( '#^(include|exclude)/[a-z0-9_]+(/[a-z0-9_]+(/\d+)?)?$#', $condition ), $name . ': forma de la condición ' . $condition );
			}
		}
		$GLOBALS['t']['term_counts'] = array();
	}

	/** Las cabeceras que se pintarían: la de Element Pack si está cargado y la copia que elija el Theme Builder. */
	function rendered_headers(): array {
		$out = array();
		if ( Publisher::ep_loaded() ) {
			$out[] = 'ep';
		}
		$cache = get_option( 'elementor_pro_theme_builder_conditions', array() );
		$best  = null;
		foreach ( $cache['header'] ?? array() as $id => $conditions ) {
			if ( ! in_array( 'include/general', (array) $conditions, true ) || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$settings = get_post_meta( $id, '_elementor_page_settings', true );
			$priority = (int) ( $settings['digitalisimo_tb_priority'] ?? 100 );
			if ( null === $best || $priority < $best[1] ) {
				$best = array( $id, $priority );
			}
		}
		if ( null !== $best ) {
			$out[] = 'copia:' . $best[0];
		}
		return $out;
	}

	function copy_of( int $source ): int {
		$row = Registry::get( 'tb', (string) $source );
		return $row ? (int) $row['dest'] : 0;
	}

	function report_items( array $report, int $post_id = -1 ): array {
		$out = array();
		foreach ( $report['docs'] as $id => $doc ) {
			if ( $post_id >= 0 && (int) $id !== $post_id ) {
				continue;
			}
			foreach ( $doc['items'] as $item ) {
				$out[] = $item['code'];
			}
		}
		if ( $post_id < 0 || 0 === $post_id ) {
			foreach ( $report['site']['items'] as $item ) {
				$out[] = $item['code'];
			}
		}
		return $out;
	}

	/* ================================================================ 1. Tipos y filas */

	load_site( $site, false );
	new_request( array() );

	same( Tb_Conditions::canonical( 'post|category' ), 'post|archive', 'alias: categoría del blog' );
	same( Tb_Conditions::canonical( 'post|date' ), 'post|archive', 'alias: fecha' );
	same( Tb_Conditions::canonical( 'product|tag' ), 'product|shop', 'alias: etiqueta de producto' );
	same( Tb_Conditions::canonical( 'book|genre' ), 'book|archive', 'alias: taxonomía de un tipo' );
	same( Tb_Conditions::canonical( ' Themes|Header ' ), 'themes|header', 'tipo sin espacios ni mayúsculas' );
	same( Tb_Conditions::kind( 'themes|header' )['pro'], 'header', 'cabecera → header' );
	same( Tb_Conditions::kind( 'post|single' )['pro'], 'single-post', 'entrada → single-post' );
	same( Tb_Conditions::kind( 'page|single' )['pro'], 'single-page', 'página → single-page' );
	same( Tb_Conditions::kind( 'page|404' )['pro'], 'error-404', '404 → error-404' );
	same( Tb_Conditions::kind( 'page|search' )['pro'], 'search-results', 'búsqueda → search-results' );
	same( Tb_Conditions::kind( 'post|archive' )['pro'], 'archive', 'archivo → archive' );
	same( Tb_Conditions::kind( 'book|single' )['pro'], 'single', 'tipo personalizado → single' );
	same( Tb_Conditions::kind( 'book|archive' )['pro'], 'archive', 'archivo de un tipo → archive' );
	same( Tb_Conditions::kind( 'product|single' )['pro'], 'product', 'ficha → product' );
	same( Tb_Conditions::kind( 'product|shop' )['pro'], 'product-archive', 'tienda → product-archive' );
	same( Tb_Conditions::kind( 'product|checkout' )['woo_page'], 'checkout', 'pago: sin tipo, página de pago' );
	same( Tb_Conditions::kind( 'ghost|single' ), null, 'tipo de un contenido que ya no existe' );
	same( Tb_Conditions::kind( 'themes|sidebar' ), null, 'tipo desconocido' );

	$rows = Tb_Conditions::rows( 103, 'themes|footer', '10.0.1' );
	same( $rows[0]['a'], 'entire_site', 'fila heredada: condition_a vacía → valor por defecto del tipo' );
	$rows = Tb_Conditions::rows( 110, 'post|single', '10.0.1' );
	same( array( $rows[0]['a'], $rows[0]['singular'] ), array( 'singular', 'all' ), 'fila heredada de una entrada: singular/all' );
	$rows = Tb_Conditions::rows( 117, 'book|archive', '10.0.1' );
	same( $rows[0]['archive'], 'book_archive', 'fila heredada de un archivo de tipo: <tipo>_archive' );
	update_post_meta( 999, '_bdthemes_builder_template_conditions', '{no es json' );
	same( Tb_Conditions::rows( 999, 'page|single', '10.0.1' )[0]['singular'], 'all_pages', 'JSON ilegible → fila heredada' );
	update_post_meta( 999, '_bdthemes_builder_template_conditions', '[]' );
	same( Tb_Conditions::rows( 999, 'themes|header', '10.0.1' )[0]['a'], 'entire_site', 'JSON vacío → fila heredada' );
	update_post_meta( 999, '_bdthemes_builder_template_conditions', '[1,"x"]' );
	same( Tb_Conditions::rows( 999, 'themes|header', '10.0.1' ), array( Tb_Conditions::legacy_row( 0, '' ) ), 'JSON sin filas válidas → todo el sitio' );
	$n = Tb_Conditions::normalize( array( array( 'condition_a' => 'singular', 'condition_singular' => '' ) ), '9.9.1' );
	same( $n[0]['singular'], '', '9.9.1 conserva la subcondición vacía' );
	$n = Tb_Conditions::normalize( array( array( 'condition_a' => 'singular', 'condition_singular' => '' ) ), '10.0.1' );
	same( $n[0]['singular'], 'all', '10.x convierte la subcondición vacía en all' );
	same( Tb_Conditions::parse_ids( ' 5, 0,,abc, 7 ' ), array( '5', 'abc', '7' ), 'IDs: sin vacíos ni 0' );
	check( Tb_Conditions::registered( 'in_genre_children' ), 'registro: subcondición jerárquica de un tipo' );
	check( Tb_Conditions::registered( 'any_child_of_genre' ), 'registro: descendientes de una taxonomía de archivo' );
	check( ! Tb_Conditions::registered( 'post_format' ), 'registro: taxonomía fuera de los menús' );
	check( ! Tb_Conditions::registered( 'page_archive' ), 'registro: tipo sin archivo' );
	check( ! Tb_Conditions::registered( 'woocommerce' ), 'registro: WooCommerce sólo con WooCommerce' );

	/* ================================================================ 2. Tabla de condiciones */

	run_cases( $cases['without_woo'], 'sin WooCommerce' );

	// WooCommerce se activa: su clase, su tipo y sus taxonomías.
	eval( 'final class WooCommerce {}' );
	load_site( $site, true );
	Tb_Conditions::reset();
	check( Tb_Conditions::registered( 'product_by_author' ), 'registro con WooCommerce: autor de producto' );
	check( ! Tb_Conditions::registered( 'pa_color' ), 'registro con WooCommerce: atributo fuera de los menús' );
	run_cases( $cases['with_woo'], 'con WooCommerce' );

	// El orden de Element Pack dentro de cada ubicación.
	$group = array(
		110 => array( 'extra' => array( 'rank' => 1 ) ),
		124 => array( 'extra' => array( 'rank' => 0 ) ),
		111 => array( 'extra' => array( 'rank' => 0 ) ),
		115 => array( 'extra' => array( 'rank' => 1 ) ),
	);
	same( Theme_Builder::order( 'single', $group ), array( 124, 111, 115, 110 ), 'orden: <cpt>|single antes que post|single, por ID descendente' );
	same( Theme_Builder::order( 'header', array( 101 => array(), 100 => array(), 130 => array() ) ), array( 100, 101, 130 ), 'orden: cabecera por ID ascendente (gana la más antigua)' );

	/* ================================================================ 3. Análisis previo (sin escrituras) */

	new_request( array( 'ep' => true ) );
	Theme_Builder::set_environment( array( 'ep_version' => '10.0.1', 'builder' => true ) );
	Report::begin( 'run' );
	$before   = serialize( array( $GLOBALS['t']['options'], $GLOBALS['t']['posts'], $GLOBALS['t']['meta'] ) );
	$analysis = Theme_Builder::analyze();
	same( serialize( array( $GLOBALS['t']['options'], $GLOBALS['t']['posts'], $GLOBALS['t']['meta'] ) ), $before, 'el análisis no escribe nada' );
	$by = array();
	foreach ( $analysis as $entry ) {
		$by[ $entry[0] ][] = (int) ( $entry[1]['id'] ?? 0 );
	}
	check( in_array( 100, $by['tb.created'] ?? array(), true ), 'análisis: la cabecera principal se copiará' );
	check( in_array( 120, $by['tb.review.woo_page'] ?? array(), true ), 'análisis: el carrito queda en borrador' );
	check( in_array( 120, $by['tb.review.type_lost'] ?? array(), true ), 'análisis: el carrito activo deja de verse (plantilla del tema)' );
	check( in_array( 121, $by['tb.review.type_lost'] ?? array(), true ), 'análisis: el tipo desconocido activo deja de verse' );
	check( empty( $by['tb.blocked_retire'] ), 'análisis: ninguna cabecera visible sin copia publicable' );
	check( in_array( 123, $by['tb.cond.ids_missing'] ?? array(), true ), 'análisis: IDs que ya no existen' );
	check( ! in_array( 102, $by['tb.review.type_lost'] ?? array(), true ), 'análisis: lo inactivo no se anuncia como pérdida' );
	foreach ( $analysis as $entry ) {
		check( is_string( $entry[0] ) && 1 === preg_match( '/^(tb|core)\.[a-z0-9_.]+$/', $entry[0] ) && isset( Report::catalog()[ $entry[0] ] ), 'análisis: código del catálogo ' . $entry[0] );
		foreach ( $entry[1] as $key => $value ) {
			check( in_array( $key, Report::DETAIL_KEYS, true ) && is_scalar( $value ), 'análisis: detalle escalar ' . $key );
		}
	}
	Report::take();

	/* ================================================================ 4. Copias en borrador (platform_prepare) */

	Report::begin( 'run' );
	$cursor = array();
	$calls  = 0;
	do {
		++$calls;
		$done = Theme_Builder::prepare( $cursor, microtime( true ) - 1 ); // Sin tiempo: una plantilla por llamada.
	} while ( ! $done && $calls < 100 );
	check( $done, 'la preparación termina' );
	check( $calls > 20, 'avanza de una en una cuando no queda tiempo (' . $calls . ' llamadas)' );
	same( $cursor['phase'], 'done', 'el cursor llega a «done»' );
	check( strlen( serialize( $cursor ) ) < 4096, 'el cursor cabe en 4 KB' );
	$report = Report::take();

	$copies = array();
	foreach ( posts() as $id => $post ) {
		if ( 'elementor_library' === $post['post_type'] ) {
			$copies[] = $id;
		}
	}
	same( count( $copies ), 20, 'una copia por plantilla de Element Pack' );
	foreach ( $site['expect']['types'] as $source => $type ) {
		$dest = copy_of( (int) $source );
		check( $dest > 0, "copia de $source registrada" );
		same( get_post_meta( $dest, '_elementor_template_type', true ), $type, "tipo de la copia de $source" );
		same( posts()[ $dest ]['post_status'], 'draft', "la copia de $source nace en borrador" );
		$origin = Documents::origin( $dest );
		check( is_array( $origin ) && 'tb' === $origin['part'] && (string) $source === $origin['source'], "procedencia de la copia de $source" );
	}
	foreach ( $site['expect']['conditions'] as $source => $conditions ) {
		$stored = get_post_meta( copy_of( (int) $source ), '_elementor_conditions', true );
		same( '' === $stored ? array() : $stored, $conditions, "condiciones de la copia de $source" );
	}
	// Huella con comillas escapadas, barras invertidas, «á» y content:"\f105".
	$src  = get_post_meta( 100, '_elementor_data', true );
	$dest = copy_of( 100 );
	check( false !== strpos( $src, '\\"' ) && false !== strpos( $src, '\\\\' ) && false !== strpos( $src, 'á' ) && false !== strpos( $src, '\\\\f105' ), 'el original lleva \\", \\\\, á y \\f105' );
	same( get_post_meta( $dest, '_elementor_data', true ), $src, 'la copia es idéntica al original' );
	same( Documents::hash( $dest ), md5( $src ), 'la huella releída coincide' );
	same( Registry::get( 'tb', '100' )['hash'], md5( $src ), 'el registro guarda la huella de la copia' );
	$settings = get_post_meta( $dest, '_elementor_page_settings', true );
	same( $settings['custom_css'], 'selector{content:"\\201C"}', 'los ajustes de página conservan sus barras (wp_slash en arrays)' );
	same( $settings['digitalisimo_tb_priority'], 1, 'prioridad manual de la cabecera principal' );
	$settings = get_post_meta( copy_of( 110 ), '_elementor_page_settings', true );
	check( ! isset( $settings['bdt_builder_sample_post_id'] ), 'la copia no lleva la muestra de Element Pack' );
	same( array( $settings['preview_type'] ?? '', $settings['preview_id'] ?? 0 ), array( 'single/post', 20 ), 'la muestra pasa a la vista previa' );
	same( $settings['background_color'], '#fafafa', 'los demás ajustes de página se conservan' );
	same( posts()[ copy_of( 104 ) ]['post_content'], '<!-- wp:paragraph --><p>Pie</p><!-- /wp:paragraph -->', 'una plantilla de bloques conserva su contenido' );

	// Publicables, prioridad y razones.
	$state = Publisher::state();
	foreach ( $site['expect']['publishable'] as $source => $priority ) {
		$dest = copy_of( (int) $source );
		$row  = Registry::get( 'tb', (string) $source );
		check( ! empty( $row['extra']['publishable'] ), "la copia de $source es publicable" );
		same( (int) ( get_post_meta( $dest, '_elementor_page_settings', true )['digitalisimo_tb_priority'] ?? 0 ), $priority, "prioridad de la copia de $source" );
		same( Publisher::tracked( $dest ), 'tb', "la copia de $source se publicará sola" );
		check( isset( get_option( 'elementor_pro_theme_builder_conditions' )[ $row['extra']['location'] ][ $dest ] ), "la copia de $source está en la caché de condiciones" );
	}
	foreach ( $site['expect']['reasons'] as $source => $reason ) {
		$dest = copy_of( (int) $source );
		$row  = Registry::get( 'tb', (string) $source );
		same( $row['extra']['reason'], $reason, "motivo de la copia de $source" );
		check( empty( $row['extra']['publishable'] ), "la copia de $source no se publica sola" );
		same( Publisher::tracked( $dest ), 'hold', "la copia de $source queda protegida (hold)" );
		check( in_array( $reason, report_items( $report, $dest ), true ), "informe de la copia de $source: $reason" );
	}
	same( count( $state['tb'] ), 14, 'publicables en el publicador' );
	same( count( $state['hold'] ), 6, 'protegidas en el publicador' );
	same( array( $state['allow'], $state['published'] ), array( false, false ), 'sin permiso ni publicadas tras preparar' );
	check( in_array( 'tb.fallback_lost', report_items( $report, copy_of( 124 ) ), true ), 'informe: la última plantilla de un tipo era también su respaldo' );
	check( in_array( 'tb.cond.ids_missing', report_items( $report, copy_of( 123 ) ), true ), 'informe: IDs que ya no existen' );
	check( in_array( 'tb.scope.snapshot', report_items( $report, copy_of( 113 ) ), true ), 'informe: foto fija de las páginas con plantilla del tema' );
	check( in_array( 'tb.priority', report_items( $report, 0 ), true ), 'informe: prioridad manual asignada' );
	check( in_array( 'tb.created', report_items( $report, copy_of( 100 ) ), true ), 'informe: copia creada' );

	// Reanudar no duplica; una copia creada por una petición que murió se adopta.
	$cursor = array();
	Theme_Builder::prepare( $cursor, microtime( true ) + 30 );
	$after = 0;
	foreach ( posts() as $post ) {
		$after += 'elementor_library' === $post['post_type'] ? 1 : 0;
	}
	same( $after, 20, 'volver a preparar no duplica' );
	$orphan = copy_of( 124 );
	$row    = Registry::get( 'tb', '124' );
	Registry::put( 'tb', '124', array( 'dest' => 0, 'hash' => '', 'state' => 'pending', 'extra' => array() ) );
	$cursor = array();
	Theme_Builder::prepare( $cursor, microtime( true ) + 30 );
	same( copy_of( 124 ), $orphan, 'una copia sin registrar (petición caída) se adopta' );
	$after = 0;
	foreach ( posts() as $post ) {
		$after += 'elementor_library' === $post['post_type'] ? 1 : 0;
	}
	same( $after, 20, 'adoptar no crea otra copia' );
	same( (int) Registry::get( 'tb', '124' )['extra']['priority'], 1, 'la copia adoptada recupera su prioridad' );

	/* ================================================================ 5. Antes de desactivar Element Pack (V.5.3) */

	Report::begin( 'run' );
	$ok = Theme_Builder::before_deactivate( array( 'ep_active' => true, 'prepared' => true, 'conflict_after' => false, 'builder' => true ) );
	same( array( $ok['retire'], $ok['allow'], $ok['blocked'] ), array( true, true, array() ), 'las cinco condiciones: se retira y se permite publicar' );
	same( Theme_Builder::blocked(), array(), 'blocked() para la compuerta del runner: nada bloquea' );
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => true, 'prepared' => false, 'conflict_after' => false, 'builder' => true ) );
	same( array( $r['retire'], $r['allow'] ), array( false, false ), 'sin preparación terminada, Element Pack no se desactiva' );
	same( array_column( $r['codes'], 0 ), array( 'tb.unprepared' ), 'se informa tb.unprepared' );
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => true, 'prepared' => true, 'conflict_after' => true, 'builder' => true ) );
	same( array( $r['retire'], array_column( $r['codes'], 0 ) ), array( false, array( 'tb.conflict' ) ), 'con Elementor Pro o PRO Elements después de la etapa, no se desactiva' );
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => true, 'prepared' => true, 'conflict_after' => false, 'builder' => false ) );
	same( array( $r['retire'], array_column( $r['codes'], 0 ) ), array( false, array( 'tb.no_builder' ) ), 'sin Theme Builder, no se desactiva' );
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => false, 'prepared' => true ) );
	same( array( $r['retire'], $r['allow'], array_column( $r['codes'], 0 ) ), array( true, false, array( 'tb.plugin_inactive' ) ), 'Element Pack inactivo al empezar: borradores, sin publicar' );
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => true ) );
	same( $r['retire'], false, 'sin decir que la preparación terminó, falla cerrado' );
	// Una cabecera visible creada después de preparar no tiene copia: bloquea.
	$p          = &posts();
	$p[130]     = array( 'ID' => 130, 'post_type' => 'bdt-template-builder', 'post_status' => 'publish', 'post_title' => 'Cabecera <img src=x onerror=alert(1)>', 'post_content' => '', 'post_author' => 1, 'post_parent' => 0 );
	$m          = &metas();
	$m[130]     = array( '_bdthemes_builder_template_type' => 'themes|header', '_elementor_data' => '[{"id":"z"}]' );
	update_option( '_bdthemes_builder_themes|header__130', '130' );
	Theme_Builder::reset();
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => true, 'prepared' => true, 'conflict_after' => false, 'builder' => true ) );
	same( array( $r['retire'], $r['allow'], $r['blocked'] ), array( false, false, array( 130 ) ), 'una cabecera visible sin copia publicable bloquea la retirada' );
	same( array_column( $r['codes'], 0 ), array( 'tb.blocked_retire' ), 'se informa tb.blocked_retire' );
	same( Theme_Builder::blocked(), array( 130 ), 'blocked() devuelve la cabecera que bloquea' );
	same( $r['codes'][0][1]['id'], 130, 'el aviso lleva el ID de la plantilla' );
	// Sólo exclusiones: no se ve; no bloquea.
	$m[130]['_bdthemes_builder_template_conditions'] = '[{"polarity":"exclude","condition_a":"entire_site"}]';
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => true, 'prepared' => true, 'conflict_after' => false, 'builder' => true ) );
	same( $r['retire'], true, 'una cabecera sólo con exclusiones no se ve: no bloquea' );
	$m[130]['_bdthemes_builder_template_conditions'] = '[{"polarity":"include","condition_a":"entire_site"}]';
	delete_option( '_bdthemes_builder_themes|header__130' );
	Theme_Builder::reset();
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => true, 'prepared' => true, 'conflict_after' => false, 'builder' => true ) );
	same( $r['retire'], true, 'una cabecera inactiva no se ve: no bloquea' );
	unset( $p[130], $m[130] );
	// El original cambió después de copiarlo: se informa, no bloquea.
	$keep = $m[103]['_elementor_data'];
	$m[103]['_elementor_data'] = '[{"id":"f9"}]';
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => true, 'prepared' => true, 'conflict_after' => false, 'builder' => true ) );
	same( array( $r['retire'], array_column( $r['codes'], 0 ) ), array( true, array( 'tb.stale' ) ), 'un original cambiado tras copiarlo se informa sin bloquear' );
	$m[103]['_elementor_data'] = $keep;
	$report = Report::take();
	$html   = Report::render( $report, 'site' );
	check( false === strpos( $html, '<img' ) && false !== strpos( $html, 'Cabecera' ), 'el informe no deja pasar el HTML del título de la plantilla' );

	/* ================================================================ 6. Publicación perezosa (V.5.2, V.5.6) */

	$head = copy_of( 100 );
	new_request( array( 'ep' => true ) );
	same( rendered_headers(), array( 'ep' ), 'antes de migrar: sólo la cabecera de Element Pack' );
	check( ! Publisher::target(), 'objetivo falso con Element Pack cargado' );
	// Publicar a mano una copia con Element Pack cargado: vuelve a borrador y queda un aviso.
	wp_update_post( array( 'ID' => $head, 'post_status' => 'publish' ) );
	same( posts()[ $head ]['post_status'], 'draft', 'la guarda impide publicar una copia con Element Pack cargado' );
	same( rendered_headers(), array( 'ep' ), 'tras el intento, una sola cabecera' );
	same( (int) get_transient( Publisher::NOTICE . '1' ), $head, 'queda un aviso para quien lo intentó' );
	ob_start();
	Publisher::notice();
	$notice = (string) ob_get_clean();
	check( false !== strpos( $notice, 'notice-warning' ) && false !== strpos( $notice, 'Cabecera principal' ), 'el aviso nombra la copia' );
	check( false === get_transient( Publisher::NOTICE . '1' ), 'el aviso se muestra una vez' );
	$hold = copy_of( 102 );
	wp_update_post( array( 'ID' => $hold, 'post_status' => 'publish' ) );
	same( posts()[ $hold ]['post_status'], 'draft', 'tampoco se publica a mano una copia en revisión (hold) con Element Pack' );

	// Etapa deactivate: permiso, con Element Pack todavía cargado en esa petición.
	Publisher::allow( true );
	check( Publisher::state()['allow'], 'la tarea permite publicar' );
	check( ! Publisher::target(), 'en la petición que desactiva, Element Pack sigue cargado: objetivo falso' );
	same( rendered_headers(), array( 'ep' ), 'petición de la desactivación: sólo Element Pack' );
	// Una petición que empezó antes (Element Pack cargado) cuando la base de datos ya cambió.
	new_request( array( 'ep' => false ) );
	$transitions = count( $GLOBALS['t']['transitions'] );
	check( Publisher::state()['published'], 'la primera petición sin Element Pack publica en init' );
	same( posts()[ $head ]['post_status'], 'publish', 'la copia de la cabecera queda publicada' );
	same( rendered_headers(), array( 'copia:' . $head ), 'sin Element Pack: sólo la copia' );
	Publisher::set_environment( array( 'ep' => true, 'conflict' => false, 'builder' => true ) );
	same( rendered_headers(), array( 'ep' ), 'petición simultánea con Element Pack cargado: el filtro oculta la copia publicada' );
	foreach ( Publisher::state()['tb'] as $id ) {
		same( posts()[ $id ]['post_status'], 'publish', "publicada la copia $id" );
	}
	foreach ( Publisher::state()['hold'] as $id ) {
		same( posts()[ $id ]['post_status'], 'draft', "en borrador la copia protegida $id" );
	}
	check( in_array( $head, Priority::$calls, true ), 'se reindexa la prioridad manual al publicar' );

	// Petición simultánea sin Element Pack que todavía ve la base de datos sin publicar.
	new_request( array( 'ep' => false ), false );
	Publisher::init(); // Sin `init` todavía: sólo los ganchos.
	$p = &posts();
	$p[ $head ]['post_status'] = 'draft';
	same( rendered_headers(), array( 'copia:' . $head ), 'sin Element Pack y antes de sync(): el filtro ya la da por publicada' );
	$p[ $head ]['post_status'] = 'publish';

	// Idempotente: dos «peticiones» que leyeron published = false a la vez.
	$state              = get_option( Publisher::OPTION );
	$state['published'] = false;
	update_option( Publisher::OPTION, $state );
	$transitions = count( $GLOBALS['t']['transitions'] );
	new_request( array( 'ep' => false ) );
	same( count( $GLOBALS['t']['transitions'] ), $transitions, 'la segunda petición no vuelve a publicar nada (UPDATE condicionado)' );
	check( Publisher::state()['published'], 'y deja escrito published' );
	$updates = count( array_filter( $GLOBALS['wpdb']->log, static function ( $sql ) { return 0 === strpos( $sql, 'UPDATE wp_posts' ); } ) );
	new_request( array( 'ep' => false ) );
	same( count( array_filter( $GLOBALS['wpdb']->log, static function ( $sql ) { return 0 === strpos( $sql, 'UPDATE wp_posts' ); } ) ), $updates, 'una petición normal ya no consulta nada' );

	// Despublicar a mano una copia (sin Element Pack): deja de publicarse sola.
	$shop = copy_of( 101 );
	wp_update_post( array( 'ID' => $shop, 'post_status' => 'draft' ) );
	same( Publisher::tracked( $shop ), 'hold', 'una copia despublicada a mano pasa a protegida' );
	// Publicar a mano una protegida (sin Element Pack): pasa a publicable.
	wp_update_post( array( 'ID' => $hold, 'post_status' => 'publish' ) );
	same( array( posts()[ $hold ]['post_status'], Publisher::tracked( $hold ) ), array( 'publish', 'tb' ), 'publicar a mano sin Element Pack funciona y la copia pasa a publicable' );

	// Element Pack vuelve: el filtro primero, después sync() despublica.
	new_request( array( 'ep' => true ), false );
	Publisher::init();
	same( rendered_headers(), array( 'ep' ), 'Element Pack reactivado, antes de sync(): una sola cabecera' );
	do_action( 'init' );
	foreach ( array( $head, $hold ) as $id ) {
		same( posts()[ $id ]['post_status'], 'draft', "Element Pack reactivado: la copia $id vuelve a borrador" );
	}
	check( ! Publisher::state()['published'], 'published vuelve a falso' );
	same( rendered_headers(), array( 'ep' ), 'Element Pack reactivado: una sola cabecera' );
	// Y se va otra vez: vuelven las publicables; la despublicada a mano, no.
	new_request( array( 'ep' => false ) );
	same( array( posts()[ $head ]['post_status'], posts()[ $hold ]['post_status'], posts()[ $shop ]['post_status'] ), array( 'publish', 'publish', 'draft' ), 'sin Element Pack otra vez: lo publicable vuelve; lo retirado a mano, no' );

	// Conflicto: Elementor Pro activo y sin Element Pack: no se publica ni se despublica nada.
	new_request( array( 'ep' => false, 'conflict' => true ) );
	check( ! Publisher::target(), 'objetivo falso con Elementor Pro o PRO Elements' );
	same( posts()[ $head ]['post_status'], 'publish', 'sin Element Pack, un conflicto no despublica' );
	new_request( array( 'ep' => false, 'builder' => false ) );
	check( ! Publisher::target(), 'objetivo falso sin Theme Builder' );

	// Otro sitio de la red: sin copias no hay ganchos ni consultas; desde una petición que cambió de sitio, nada.
	switch_to_blog( 2 );
	new_request( array( 'ep' => false ) );
	same( Publisher::state(), null, 'sitio sin copias: sin estado' );
	check( ! has_hook( 'get_post_status' ), 'sitio sin copias: el filtro get_post_status no se engancha' );
	restore_current_blog();
	new_request( array( 'ep' => true ), false );
	Publisher::init();
	Publisher::set_environment( array( 'ep' => true, 'conflict' => false, 'builder' => true, 'switched' => true ) );
	same( get_post_status( $head ), 'publish', 'una petición que cambió de sitio no filtra el estado' );
	Publisher::set_environment( array( 'ep' => false, 'conflict' => false, 'builder' => true ) );

	// Sin Migración (desinstalada o 1.0.20): el estado guardado es coherente; reactivar EP sin ella, límite declarado.
	new_request( array( 'ep' => false ), false );
	same( rendered_headers(), array( 'copia:' . $head ), 'sin Migración y sin Element Pack: la copia, una cabecera' );
	Publisher::set_environment( array( 'ep' => true, 'conflict' => false, 'builder' => true ) );
	same( count( rendered_headers() ), 2, 'límite declarado: reactivar Element Pack sin Migración pinta las dos' );

	// CSS global de H: se publica sólo revalidado.
	new_request( array( 'ep' => true ) );
	$p          = &posts();
	$p[2001]    = array( 'ID' => 2001, 'post_type' => 'elementor_snippet', 'post_status' => 'draft', 'post_title' => 'CSS', 'post_content' => '', 'post_author' => 1, 'post_parent' => 0 );
	$p[2002]    = array( 'ID' => 2002, 'post_type' => 'elementor_snippet', 'post_status' => 'draft', 'post_title' => 'CSS 2', 'post_content' => '', 'post_author' => 1, 'post_parent' => 0 );
	Publisher::track( 'css', 2001 );
	do_action( 'init' ); // Element Pack cargado: despublica todo.
	new_request( array( 'ep' => false ) );
	same( posts()[2001]['post_status'], 'publish', 'CSS revalidado: publicado' );
	new_request( array( 'ep' => true ) );
	Snippets::$deny = array( 2002 );
	Publisher::track( 'css', 2002 );
	new_request( array( 'ep' => false ) );
	same( posts()[2002]['post_status'], 'draft', 'CSS que ya no se puede revalidar: borrador' );
	check( in_array( 2002, Publisher::denied(), true ) && '' === Publisher::tracked( 2002 ), 'y sale de la lista, anotado en denied' );
	Snippets::$deny = array();
	same( get_post_status( 2001 ), 'publish', 'el CSS revalidado sigue publicado' );
	Publisher::set_environment( array( 'ep' => true, 'conflict' => false, 'builder' => true ) );
	same( get_post_status( 2001 ), 'publish', 'el filtro no toca el CSS (sólo el cambio en la base de datos, V.5.2)' );
	Publisher::set_environment( array( 'ep' => false, 'conflict' => false, 'builder' => true ) );

	// Borrar del todo una copia la saca del publicador.
	wp_delete_post( 2001 );
	same( Publisher::tracked( 2001 ), '', 'una entrada borrada deja de seguirse' );

	// Cerrojo: ocupado por otra petición, sync() no escribe; caducado (60 s), se roba.
	new_request( array( 'ep' => true ) );
	update_option( Publisher::LOCK, time() . '|otro' );
	new_request( array( 'ep' => false ) );
	same( posts()[ $head ]['post_status'], 'publish', 'con el cerrojo ocupado, la base de datos se publica igual (idempotente)' );
	check( ! Publisher::state()['published'], 'pero published no se escribe sin el cerrojo' );
	same( get_option( Publisher::LOCK ), time() . '|otro', 'y el cerrojo ajeno sigue' );
	update_option( Publisher::LOCK, ( time() - 61 ) . '|otro' );
	new_request( array( 'ep' => false ) );
	check( Publisher::state()['published'], 'un cerrojo caducado se roba y published se escribe' );
	check( false === get_option( Publisher::LOCK ), 'el cerrojo propio se suelta' );

	// Tope de IDs.
	$before = Publisher::state();
	for ( $i = 0; $i < 200; $i++ ) {
		Publisher::track( 'hold', 5000 + $i );
	}
	same( count( Publisher::state()['tb'] ) + count( Publisher::state()['css'] ) + count( Publisher::state()['hold'] ), Publisher::MAX, 'nunca más de 150 IDs en el estado' );
	for ( $i = 0; $i < 200; $i++ ) {
		Publisher::untrack( 5000 + $i );
	}
	same( Publisher::state()['tb'], $before['tb'], 'quitar IDs no toca los demás' );

	/* ================================================================ 7. Etapa platform (finish) */

	new_request( array( 'ep' => false ) );
	Report::begin( 'run' );
	Priority::$calls = array();
	$cursor          = array();
	check( Theme_Builder::finish( $cursor, microtime( true ) + 30 ), 'finish termina en una llamada' );
	$report = Report::take();
	check( in_array( 'tb.published', report_items( $report, $head ), true ), 'informe: copia publicada' );
	check( in_array( $head, Priority::$calls, true ), 'finish reindexa la prioridad' );

	/* ================================================================ 8. Reversión (V.8) */

	new_request( array( 'ep' => true ) );
	$edited = copy_of( 110 );
	update_post_meta( $edited, '_elementor_data', '[{"id":"editado"}]' );
	$out = Theme_Builder::revert();
	same( posts()[ $edited ]['post_status'] !== 'trash', true, 'una copia editada después de migrar no se revierte' );
	check( in_array( array( 'core.revert_skipped', array( 'type' => 'elementor_library', 'id' => $edited, 'name' => 'Entrada' ) ), $out, true ), 'y se informa core.revert_skipped' );
	same( posts()[ $head ]['post_status'], 'trash', 'una copia sin cambios va a la papelera' );
	same( Registry::get( 'tb', '100' ), null, 'su fila del registro se borra' );
	same( Publisher::tracked( $head ), '', 'y sale del publicador' );
	same( Publisher::tracked( $edited ), 'tb', 'la editada sigue protegida' );
	same( posts()[100]['post_status'], 'publish', 'el original nunca se toca' );
	check( count( array_filter( $out, static function ( $e ) { return 'tb.reverted' === $e[0]; } ) ) >= 18, 'informe de lo revertido' );
	Theme_Builder::set_environment( array( 'ep_version' => '10.0.1', 'builder' => true, 'trash_days' => 0 ) );
	$out = Theme_Builder::revert( true );
	same( posts()[ $edited ]['post_status'], 'draft', 'con EMPTY_TRASH_DAYS = 0 (y --force), a borrador: nunca se borra para siempre' );
	same( Registry::all( 'tb' ), array(), 'el registro queda vacío' );
	same( get_option( Publisher::OPTION, null ), null, 'sin copias, el estado del publicador desaparece' );

	/* ================================================================ 9. Sitio con problemas (blog 2) */

	switch_to_blog( 2 );
	load_site( $site, true );
	new_request( array( 'ep' => true ) );
	Theme_Builder::set_environment( array( 'ep_version' => '10.0.1', 'builder' => true ) );
	// Una cabecera visible cuya copia pierde las barras invertidas al guardarse.
	$GLOBALS['t']['corrupt'] = '"h2"';
	Report::begin( 'run' );
	$cursor = array();
	Theme_Builder::prepare( $cursor, microtime( true ) + 30 );
	$GLOBALS['t']['corrupt'] = '';
	$report = Report::take();
	$row    = Registry::get( 'tb', '100' );
	same( array( $row['state'], $row['extra']['reason'] ), array( 'failed', 'tb.copy_failed' ), 'huella distinta: la copia no es publicable' );
	same( Publisher::tracked( (int) $row['dest'] ), 'hold', 'la copia fallida queda protegida en borrador' );
	check( in_array( 'tb.copy_failed', report_items( $report, (int) $row['dest'] ), true ), 'informe: tb.copy_failed' );
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => true, 'prepared' => true, 'conflict_after' => false, 'builder' => true ) );
	same( array( $r['retire'], $r['blocked'] ), array( false, array( 100 ) ), 'una cabecera visible con copia fallida impide desactivar Element Pack' );
	same( Theme_Builder::blocked(), array( 100 ), 'blocked(): la cabecera con copia fallida' );
	Publisher::allow( $r['allow'] );
	new_request( array( 'ep' => false ) );
	same( Publisher::state()['published'], false, 'sin permiso, nada se publica' );

	// 9.9.1: la plantilla que elige sin condiciones es la última fila de su consulta, sin mirar el estado.
	Theme_Builder::set_environment( array( 'ep_version' => '9.9.1', 'builder' => true ) );
	$p      = &posts();
	$p[115]['post_status'] = 'draft';
	same( Theme_Builder::winner( 'page|404' ), 115, '9.9.1: la última fila aunque esté en borrador' );
	Theme_Builder::set_environment( array( 'ep_version' => '10.0.1', 'builder' => true ) );
	same( Theme_Builder::winner( 'page|404' ), 114, '10.x: la última publicada' );
	$p[115]['post_status'] = 'publish';
	restore_current_blog();

	// Element Pack inactivo al empezar: borradores que no se publican solos y que sí se pueden publicar a mano.
	switch_to_blog( 2 );
	$r = Theme_Builder::before_deactivate( array( 'ep_active' => false, 'prepared' => true ) );
	same( array( $r['retire'], $r['allow'] ), array( true, false ), 'Element Pack inactivo: nada que proteger, nada se publica solo' );
	new_request( array( 'ep' => false ) );
	$foot = copy_of( 103 );
	same( posts()[ $foot ]['post_status'], 'draft', 'la copia sigue en borrador' );
	wp_update_post( array( 'ID' => $foot, 'post_status' => 'publish' ) );
	same( posts()[ $foot ]['post_status'], 'publish', 'sin Element Pack cargado, publicarla a mano funciona' );
	restore_current_blog();

	/* ================================================================ Resultado */

	if ( $GLOBALS['failures'] ) {
		echo 'migration-theme-builder: ' . count( $GLOBALS['failures'] ) . ' de ' . $GLOBALS['checks'] . " comprobaciones fallaron\n";
		exit( 1 );
	}
	echo 'migration-theme-builder: ' . $GLOBALS['checks'] . " comprobaciones en verde\n";
}
