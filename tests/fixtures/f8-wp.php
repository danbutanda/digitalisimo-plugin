<?php
/**
 * F8 · WordPress mínimo para las pruebas de DIGITALÍSIMO Migración (V.14 C14).
 *
 * Lo incluyen las pruebas de todos los frentes (`require __DIR__ . '/fixtures/f8-wp.php';`) y lo amplían en su propio
 * archivo, nunca aquí: cada función está protegida con `function_exists()`, así que una prueba puede declarar la suya
 * ANTES de incluir este archivo, y `F8_Wpdb` no es final (una prueba puede extenderla y asignar `$GLOBALS['wpdb']`).
 *
 * Estado en `$GLOBALS['wp']` (ver f8_reset()): sitios con sus opciones (y carga automática), entradas, metadatos,
 * términos, usuarios con capacidades por sitio, plugins instalados y activos, WP-Cron por sitio y ganchos reales
 * (prioridades y número de argumentos). `$wpdb` en memoria entiende las consultas de Migración: búsquedas `LIKE` sobre
 * metadatos (con `post_id > %d`, revisiones aparte y `LIMIT`), los cerrojos `INSERT IGNORE` / `UPDATE` / `DELETE` sobre
 * la tabla `options` de cada sitio (`wp_options`, `wp_2_options`…), el cambio de estado de una entrada, la última
 * revisión y la suma de una meta. Lo que no entiende queda en `$wpdb->unknown` y devuelve vacío.
 *
 * Ayudas: f8_reset(), f8_new_request(), f8_check(), f8_done(), f8_post(), f8_meta(), f8_user(), f8_site(),
 * f8_plugin(), f8_cron_fire(), f8_writes().
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/f8-wp-content' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}
if ( ! defined( 'WP_LANG_DIR' ) ) {
	define( 'WP_LANG_DIR', WP_CONTENT_DIR . '/languages' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
}

/* ------------------------------------------------------------------ Estado y ayudas */

{
	/**
	 * Estado limpio: un sitio (o `sites` => n), el usuario 1 administrador (superadministrador en una red) y nada más.
	 * `$over` sustituye claves de primer nivel (por ejemplo `array( 'multisite' => true, 'sites' => 3 )`).
	 */
	if ( ! function_exists( 'f8_reset' ) ) {
		function f8_reset( array $over = array() ) {
			$sites = (int) ( $over['sites'] ?? 1 );
			unset( $over['sites'] );
			$GLOBALS['wp'] = array_merge(
				array(
					'blog'          => 1,
					'stack'         => array(),
					'multisite'     => false,
					'main_site'     => 1,
					'network_id'    => 1,
					'sites'         => array(),
					'options'       => array(),
					'autoload'      => array(),
					'site_options'  => array(),
					'posts'         => array(),
					'next_id'       => array(),
					'meta'          => array(),
					'user_meta'     => array(),
					'users'         => array(),
					'current_user'  => 1,
					'terms'         => array(),
					'next_term'     => 100,
					'object_terms'  => array(),
					'plugins'       => array(),
					'writes'        => array(),
					'cron'          => array(),
					'filters'       => array(),
					'actions_done'  => array(),
					'trashed'       => array(),
					'deleted'       => array(),
					'cleaned'       => array(),
					'transitions'   => array(),
					'ajax'          => false,
					'cron_request'  => false,
					'admin'         => true,
					'network_admin' => false,
					'time'          => null,
					'nonces'        => true,
				),
				$over
			);
			for ( $i = 1; $i <= max( 1, $sites ); $i++ ) {
				f8_site( $i );
			}
			f8_user( 1, array( 'display_name' => 'Admin', 'caps' => array( 'administrator' => true ), 'super' => true ) );
			f8_new_request();
			if ( isset( $GLOBALS['wpdb'] ) && $GLOBALS['wpdb'] instanceof F8_Wpdb ) {
				$GLOBALS['wpdb']->queries = array();
				$GLOBALS['wpdb']->unknown = array();
			}
		}
	}

	/** Un sitio (o sus datos): `archived`, `spam`, `deleted`, `domain`, `path`. */
	if ( ! function_exists( 'f8_site' ) ) {
		function f8_site( $id, array $data = array() ) {
			$id                                   = (int) $id;
			$GLOBALS['wp']['sites'][ $id ]        = array_merge( array( 'blog_id' => $id, 'domain' => 's' . $id . '.test', 'path' => '/', 'archived' => 0, 'spam' => 0, 'deleted' => 0, 'public' => 1, 'network_id' => 1 ), $GLOBALS['wp']['sites'][ $id ] ?? array(), $data );
			$GLOBALS['wp']['options'][ $id ]      = $GLOBALS['wp']['options'][ $id ] ?? array( 'active_plugins' => array() );
			$GLOBALS['wp']['autoload'][ $id ]     = $GLOBALS['wp']['autoload'][ $id ] ?? array();
			$GLOBALS['wp']['posts'][ $id ]        = $GLOBALS['wp']['posts'][ $id ] ?? array();
			$GLOBALS['wp']['meta'][ $id ]         = $GLOBALS['wp']['meta'][ $id ] ?? array();
			$GLOBALS['wp']['next_id'][ $id ]      = $GLOBALS['wp']['next_id'][ $id ] ?? 1000;
			$GLOBALS['wp']['terms'][ $id ]        = $GLOBALS['wp']['terms'][ $id ] ?? array();
			$GLOBALS['wp']['object_terms'][ $id ] = $GLOBALS['wp']['object_terms'][ $id ] ?? array();
			$GLOBALS['wp']['cron'][ $id ]         = $GLOBALS['wp']['cron'][ $id ] ?? array();
		}
	}

	/**
	 * Un usuario: `display_name`, `user_login`, `caps` (capacidades o roles: `administrator`, `editor`), `caps_by_blog`
	 * (sitio => capacidades), `super` (superadministrador de la red) y `blogs` (sitios de los que es miembro; todos si
	 * se omite).
	 */
	if ( ! function_exists( 'f8_user' ) ) {
		function f8_user( $id, array $data = array() ) {
			$GLOBALS['wp']['users'][ (int) $id ] = array_merge( array( 'ID' => (int) $id, 'display_name' => 'Usuario ' . $id, 'user_login' => 'usuario' . $id, 'caps' => array(), 'caps_by_blog' => array(), 'super' => false, 'blogs' => null ), $GLOBALS['wp']['users'][ (int) $id ] ?? array(), $data );
		}
	}

	/** Una entrada del sitio en curso. Devuelve su ID. */
	if ( ! function_exists( 'f8_post' ) ) {
		function f8_post( array $post, array $meta = array() ) {
			$blog = $GLOBALS['wp']['blog'];
			$id   = isset( $post['ID'] ) ? (int) $post['ID'] : ++$GLOBALS['wp']['next_id'][ $blog ];
			if ( $id > $GLOBALS['wp']['next_id'][ $blog ] ) {
				$GLOBALS['wp']['next_id'][ $blog ] = $id;
			}
			$GLOBALS['wp']['posts'][ $blog ][ $id ] = f8_post_defaults( array( 'ID' => $id ) + $post );
			foreach ( $meta as $key => $value ) {
				$GLOBALS['wp']['meta'][ $blog ][ $id ][ $key ] = $value;
			}
			return $id;
		}
	}

	if ( ! function_exists( 'f8_post_defaults' ) ) {
		function f8_post_defaults( array $post ) {
			$now = gmdate( 'Y-m-d H:i:s', f8_time() );
			return array_merge( array( 'ID' => 0, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => '', 'post_name' => '', 'post_content' => '', 'post_excerpt' => '', 'post_parent' => 0, 'post_author' => 1, 'post_date' => $now, 'post_date_gmt' => $now, 'post_modified' => $now, 'post_modified_gmt' => $now, 'menu_order' => 0, 'post_mime_type' => '', 'guid' => '' ), $post );
		}
	}

	/** Lee una meta del sitio en curso tal como está guardada (sin deserializar nada). */
	if ( ! function_exists( 'f8_meta' ) ) {
		function f8_meta( $id, $key ) {
			return $GLOBALS['wp']['meta'][ $GLOBALS['wp']['blog'] ][ (int) $id ][ $key ] ?? null;
		}
	}

	/** Un plugin instalado (`Name`, `Version`, `Author`…) y, opcional, dónde está activo: 'site' (sitio en curso), 'network' o un ID de sitio. */
	if ( ! function_exists( 'f8_plugin' ) ) {
		function f8_plugin( $file, array $headers, $active = '' ) {
			$GLOBALS['wp']['plugins'][ $file ] = $headers + array( 'Name' => basename( $file, '.php' ), 'Version' => '1.0', 'Author' => '' );
			if ( 'network' === $active ) {
				$GLOBALS['wp']['site_options']['active_sitewide_plugins'][ $file ] = time();
			} elseif ( '' !== $active ) {
				$blog = 'site' === $active ? $GLOBALS['wp']['blog'] : (int) $active;
				$list = (array) ( $GLOBALS['wp']['options'][ $blog ]['active_plugins'] ?? array() );
				if ( ! in_array( $file, $list, true ) ) {
					$list[] = $file;
				}
				$GLOBALS['wp']['options'][ $blog ]['active_plugins'] = $list;
			}
		}
	}

	/** Llamadas a deactivate_plugins(): `array( files[], network, blog )` cada una (una llamada = una escritura). */
	if ( ! function_exists( 'f8_writes' ) ) {
		function f8_writes() {
			return $GLOBALS['wp']['writes'];
		}
	}

	/** Una petición nueva: arranca después de cualquier desactivación anterior. */
	if ( ! function_exists( 'f8_new_request' ) ) {
		function f8_new_request() {
			$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) + 1;
			usleep( 1000 );
		}
	}

	/** Reloj de la prueba (time() salvo que `$GLOBALS['wp']['time']` lo fije). */
	if ( ! function_exists( 'f8_time' ) ) {
		function f8_time() {
			return null === ( $GLOBALS['wp']['time'] ?? null ) ? time() : (int) $GLOBALS['wp']['time'];
		}
	}

	/** Comprobación con recuento. */
	if ( ! function_exists( 'f8_check' ) ) {
		function f8_check( $ok, $message ) {
			$GLOBALS['f8_checks'] = (int) ( $GLOBALS['f8_checks'] ?? 0 ) + 1;
			if ( ! $ok ) {
				throw new \RuntimeException( 'Falló la comprobación ' . $GLOBALS['f8_checks'] . ': ' . $message );
			}
		}
	}

	/** Línea final con el número de comprobaciones (y un mínimo opcional). */
	if ( ! function_exists( 'f8_done' ) ) {
		function f8_done( $label, $min = 0 ) {
			$count = (int) ( $GLOBALS['f8_checks'] ?? 0 );
			if ( $min && $count < $min ) {
				throw new \RuntimeException( sprintf( '%s: %d comprobaciones, se esperaban al menos %d.', $label, $count, $min ) );
			}
			echo $label . ': ' . $count . " comprobaciones en verde.\n";
		}
	}

	/** Dispara un evento de WP-Cron del sitio en curso (o del indicado) y lo quita de la cola. */
	if ( ! function_exists( 'f8_cron_fire' ) ) {
		function f8_cron_fire( $hook, $blog = null ) {
			$blog = null === $blog ? $GLOBALS['wp']['blog'] : (int) $blog;
			$args = $GLOBALS['wp']['cron'][ $blog ][ $hook ]['args'] ?? array();
			$swap = $blog !== $GLOBALS['wp']['blog'];
			if ( $swap ) {
				switch_to_blog( $blog );
			}
			unset( $GLOBALS['wp']['cron'][ $blog ][ $hook ] );
			$GLOBALS['wp']['cron_request'] = true;
			try {
				do_action_ref_array( $hook, (array) $args );
			} finally {
				$GLOBALS['wp']['cron_request'] = false;
				if ( $swap ) {
					restore_current_blog();
				}
			}
		}
	}
}

/* ------------------------------------------------------------------ Ganchos */

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['wp']['filters'][ $hook ][ (int) $priority ][] = array( $callback, (int) $args );
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $callback, $priority, $args );
	}
}
if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( $hook, $callback, $priority = 10 ) {
		foreach ( (array) ( $GLOBALS['wp']['filters'][ $hook ][ (int) $priority ] ?? array() ) as $i => $row ) {
			if ( $row[0] === $callback ) {
				unset( $GLOBALS['wp']['filters'][ $hook ][ (int) $priority ][ $i ] );
				return true;
			}
		}
		return false;
	}
}
if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( $hook, $callback, $priority = 10 ) {
		return remove_filter( $hook, $callback, $priority );
	}
}
if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( $hook ) {
		unset( $GLOBALS['wp']['filters'][ $hook ] );
		return true;
	}
}
if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( $hook, $callback = false ) {
		if ( empty( $GLOBALS['wp']['filters'][ $hook ] ) ) {
			return false;
		}
		if ( false === $callback ) {
			foreach ( $GLOBALS['wp']['filters'][ $hook ] as $rows ) {
				if ( $rows ) {
					return true;
				}
			}
			return false;
		}
		foreach ( $GLOBALS['wp']['filters'][ $hook ] as $priority => $rows ) {
			foreach ( $rows as $row ) {
				if ( $row[0] === $callback ) {
					return $priority;
				}
			}
		}
		return false;
	}
}
if ( ! function_exists( 'has_action' ) ) {
	function has_action( $hook, $callback = false ) {
		return has_filter( $hook, $callback );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['wp']['filters'][ $hook ] ) ) {
			return $value;
		}
		$rows = $GLOBALS['wp']['filters'][ $hook ];
		ksort( $rows );
		foreach ( $rows as $callbacks ) {
			foreach ( $callbacks as $row ) {
				$value = call_user_func_array( $row[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, $row[1] ) ) );
			}
		}
		return $value;
	}
}
if ( ! function_exists( 'apply_filters_ref_array' ) ) {
	function apply_filters_ref_array( $hook, array $args ) {
		return apply_filters( $hook, ...$args );
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		$GLOBALS['wp']['actions_done'][ $hook ] = (int) ( $GLOBALS['wp']['actions_done'][ $hook ] ?? 0 ) + 1;
		if ( empty( $GLOBALS['wp']['filters'][ $hook ] ) ) {
			return;
		}
		$rows = $GLOBALS['wp']['filters'][ $hook ];
		ksort( $rows );
		foreach ( $rows as $callbacks ) {
			foreach ( $callbacks as $row ) {
				call_user_func_array( $row[0], array_slice( $args, 0, max( 0, $row[1] ) ) );
			}
		}
	}
}
if ( ! function_exists( 'do_action_ref_array' ) ) {
	function do_action_ref_array( $hook, array $args ) {
		do_action( $hook, ...$args );
	}
}
if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) {
		return (int) ( $GLOBALS['wp']['actions_done'][ $hook ] ?? 0 );
	}
}

/* ------------------------------------------------------------------ Sitios y red */

{
	if ( ! function_exists( 'is_multisite' ) ) {
		function is_multisite() {
			return ! empty( $GLOBALS['wp']['multisite'] );
		}
	}
	if ( ! function_exists( 'get_current_blog_id' ) ) {
		function get_current_blog_id() {
			return (int) $GLOBALS['wp']['blog'];
		}
	}
	if ( ! function_exists( 'get_main_site_id' ) ) {
		function get_main_site_id( $network_id = null ) {
			return (int) $GLOBALS['wp']['main_site'];
		}
	}
	if ( ! function_exists( 'is_main_site' ) ) {
		function is_main_site( $site_id = null ) {
			return (int) ( null === $site_id ? $GLOBALS['wp']['blog'] : $site_id ) === (int) $GLOBALS['wp']['main_site'];
		}
	}
	if ( ! function_exists( 'get_current_network_id' ) ) {
		function get_current_network_id() {
			return (int) $GLOBALS['wp']['network_id'];
		}
	}
	if ( ! function_exists( 'switch_to_blog' ) ) {
		function switch_to_blog( $id ) {
			$GLOBALS['wp']['stack'][] = $GLOBALS['wp']['blog'];
			$GLOBALS['wp']['blog']    = (int) $id;
			if ( ! isset( $GLOBALS['wp']['options'][ (int) $id ] ) ) {
				f8_site( (int) $id );
			}
			return true;
		}
	}
	if ( ! function_exists( 'restore_current_blog' ) ) {
		function restore_current_blog() {
			if ( ! $GLOBALS['wp']['stack'] ) {
				return false;
			}
			$GLOBALS['wp']['blog'] = array_pop( $GLOBALS['wp']['stack'] );
			return true;
		}
	}
	if ( ! function_exists( 'ms_is_switched' ) ) {
		function ms_is_switched() {
			return ! empty( $GLOBALS['wp']['stack'] );
		}
	}
	/** Objeto de sitio con `blog_id`, `domain`, `path`, `archived`, `spam`, `deleted`, `public` (como WP_Site, en texto). */
	if ( ! function_exists( 'get_site' ) ) {
		function get_site( $site = null ) {
			$id = is_object( $site ) ? (int) $site->blog_id : (int) ( null === $site ? $GLOBALS['wp']['blog'] : $site );
			if ( ! isset( $GLOBALS['wp']['sites'][ $id ] ) ) {
				return null;
			}
			$data = $GLOBALS['wp']['sites'][ $id ];
			foreach ( array( 'archived', 'spam', 'deleted', 'public', 'blog_id', 'network_id' ) as $key ) {
				$data[ $key ] = (string) (int) $data[ $key ];
			}
			$data['id']      = (int) $id;
			$data['siteurl'] = 'https://' . $data['domain'] . rtrim( $data['path'], '/' );
			$data['blogname'] = 'Sitio ' . $id;
			return (object) $data;
		}
	}
	if ( ! function_exists( 'get_blog_details' ) ) {
		function get_blog_details( $id = null ) {
			return get_site( $id );
		}
	}
	/** Sitios: `fields` ('ids'), `number`, `network_id`, `archived`, `spam`, `deleted`, `site__in`. */
	if ( ! function_exists( 'get_sites' ) ) {
		function get_sites( $args = array() ) {
			$out = array();
			foreach ( $GLOBALS['wp']['sites'] as $id => $site ) {
				foreach ( array( 'archived', 'spam', 'deleted' ) as $flag ) {
					if ( isset( $args[ $flag ] ) && (int) $args[ $flag ] !== (int) $site[ $flag ] ) {
						continue 2;
					}
				}
				if ( isset( $args['site__in'] ) && ! in_array( (int) $id, array_map( 'intval', (array) $args['site__in'] ), true ) ) {
					continue;
				}
				if ( isset( $args['network_id'] ) && null !== $args['network_id'] && (int) $args['network_id'] !== (int) $site['network_id'] ) {
					continue;
				}
				$out[] = 'ids' === ( $args['fields'] ?? '' ) ? (int) $id : get_site( $id );
			}
			$number = (int) ( $args['number'] ?? 100 );
			return $number > 0 ? array_slice( $out, 0, $number ) : $out;
		}
	}
	if ( ! function_exists( 'get_blog_option' ) ) {
		function get_blog_option( $blog, $key, $default = false ) {
			switch_to_blog( $blog );
			try {
				return get_option( $key, $default );
			} finally {
				restore_current_blog();
			}
		}
	}
	if ( ! function_exists( 'update_blog_option' ) ) {
		function update_blog_option( $blog, $key, $value ) {
			switch_to_blog( $blog );
			try {
				return update_option( $key, $value );
			} finally {
				restore_current_blog();
			}
		}
	}
}

/* ------------------------------------------------------------------ Opciones */

{
	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $key, $default = false ) {
			$opts = $GLOBALS['wp']['options'][ $GLOBALS['wp']['blog'] ] ?? array();
			return array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
		}
	}
	if ( ! function_exists( 'update_option' ) ) {
		function update_option( $key, $value, $autoload = null ) {
			$blog = $GLOBALS['wp']['blog'];
			$GLOBALS['wp']['options'][ $blog ][ $key ] = $value;
			if ( null !== $autoload || ! isset( $GLOBALS['wp']['autoload'][ $blog ][ $key ] ) ) {
				$GLOBALS['wp']['autoload'][ $blog ][ $key ] = null === $autoload ? true : (bool) ( 'no' === $autoload || 'off' === $autoload ? false : $autoload );
			}
			// Última escritura de cada opción, con su carga automática: sobrevive a delete_option() para las pruebas de lo
			// que una etapa escribe y borra dentro del mismo paso (con PHP nativo una etapa entera cabe en un paso).
			$GLOBALS['wp']['writes'][ $blog ][ $key ] = array( 'value' => $value, 'autoload' => $GLOBALS['wp']['autoload'][ $blog ][ $key ] );
			return true;
		}
	}
	if ( ! function_exists( 'add_option' ) ) {
		function add_option( $key, $value = '', $deprecated = '', $autoload = true ) {
			$blog = $GLOBALS['wp']['blog'];
			if ( array_key_exists( $key, $GLOBALS['wp']['options'][ $blog ] ?? array() ) ) {
				return false;
			}
			return update_option( $key, $value, $autoload );
		}
	}
	if ( ! function_exists( 'delete_option' ) ) {
		function delete_option( $key ) {
			$blog = $GLOBALS['wp']['blog'];
			$had  = array_key_exists( $key, $GLOBALS['wp']['options'][ $blog ] ?? array() );
			unset( $GLOBALS['wp']['options'][ $blog ][ $key ], $GLOBALS['wp']['autoload'][ $blog ][ $key ] );
			return $had;
		}
	}
	/** ¿La opción se carga automáticamente? (Para las pruebas.) */
	if ( ! function_exists( 'f8_autoload' ) ) {
		function f8_autoload( $key, $blog = null ) {
			$blog = null === $blog ? $GLOBALS['wp']['blog'] : (int) $blog;
			return $GLOBALS['wp']['autoload'][ $blog ][ $key ] ?? null;
		}
	}
	if ( ! function_exists( 'get_site_option' ) ) {
		function get_site_option( $key, $default = false ) {
			return array_key_exists( $key, $GLOBALS['wp']['site_options'] ) ? $GLOBALS['wp']['site_options'][ $key ] : $default;
		}
	}
	if ( ! function_exists( 'update_site_option' ) ) {
		function update_site_option( $key, $value ) {
			$GLOBALS['wp']['site_options'][ $key ] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'add_site_option' ) ) {
		function add_site_option( $key, $value ) {
			if ( array_key_exists( $key, $GLOBALS['wp']['site_options'] ) ) {
				return false;
			}
			return update_site_option( $key, $value );
		}
	}
	if ( ! function_exists( 'delete_site_option' ) ) {
		function delete_site_option( $key ) {
			unset( $GLOBALS['wp']['site_options'][ $key ] );
			return true;
		}
	}
	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( $key ) {
			return get_option( '_transient_' . $key );
		}
	}
	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $key, $value, $ttl = 0 ) {
			return update_option( '_transient_' . $key, $value, false );
		}
	}
	if ( ! function_exists( 'delete_transient' ) ) {
		function delete_transient( $key ) {
			return delete_option( '_transient_' . $key );
		}
	}
}

/* ------------------------------------------------------------------ Caché y utilidades */

{
	if ( ! function_exists( 'wp_cache_get' ) ) {
		function wp_cache_get( $key, $group = '' ) {
			return $GLOBALS['wp']['cache'][ $group ][ $key ] ?? false;
		}
	}
	if ( ! function_exists( 'wp_cache_set' ) ) {
		function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
			$GLOBALS['wp']['cache'][ $group ][ $key ] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'wp_cache_delete' ) ) {
		function wp_cache_delete( $key, $group = '' ) {
			unset( $GLOBALS['wp']['cache'][ $group ][ $key ] );
			return true;
		}
	}
	if ( ! function_exists( 'wp_cache_flush' ) ) {
		function wp_cache_flush() {
			$GLOBALS['wp']['cache'] = array();
			return true;
		}
	}
}
if ( ! function_exists( 'wp_slash' ) ) {
	/** Como WordPress: addslashes recursivo sobre cadenas. */
	function wp_slash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_slash', $value );
		}
		return is_string( $value ) ? addslashes( $value ) : $value;
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		if ( is_object( $value ) ) {
			foreach ( get_object_vars( $value ) as $k => $v ) {
				$value->$k = wp_unslash( $v );
			}
			return $value;
		}
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( ! is_array( $args ) ) {
			parse_str( (string) $args, $args );
		}
		return array_merge( (array) $defaults, $args );
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special = true, $extra = false ) {
		return substr( bin2hex( random_bytes( max( 8, (int) $length ) ) ), 0, (int) $length );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( (int) $v );
	}
}
{
	if ( ! function_exists( 'maybe_serialize' ) ) {
		function maybe_serialize( $data ) {
			return is_array( $data ) || is_object( $data ) ? serialize( $data ) : $data;
		}
	}
	if ( ! function_exists( 'maybe_unserialize' ) ) {
		function maybe_unserialize( $data ) {
			if ( is_string( $data ) && preg_match( '/^(a|O|s|i|b|d):/', $data ) ) {
				$value = @unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore
				return false === $value && 'b:0;' !== $data ? $data : $value;
			}
			return $data;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_message() {
			return $this->message;
		}
		public function get_error_code() {
			return $this->code;
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
{
	if ( ! function_exists( 'trailingslashit' ) ) {
		function trailingslashit( $value ) {
			return rtrim( (string) $value, '/\\' ) . '/';
		}
	}
	if ( ! function_exists( 'untrailingslashit' ) ) {
		function untrailingslashit( $value ) {
			return rtrim( (string) $value, '/\\' );
		}
	}
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $dir ) {
		return is_dir( $dir ) || @mkdir( $dir, 0777, true ); // phpcs:ignore
	}
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	/** Carpeta de subidas de cada sitio en un temporal propio de la prueba. */
	function wp_upload_dir( $time = null, $create = true, $refresh = false ) {
		$blog = (int) $GLOBALS['wp']['blog'];
		$sub  = $blog > 1 ? '/sites/' . $blog : '';
		$base = WP_CONTENT_DIR . '/uploads' . $sub;
		if ( $create ) {
			wp_mkdir_p( $base );
		}
		return array( 'basedir' => $base, 'baseurl' => 'https://s' . $blog . '.test/wp-content/uploads' . $sub, 'path' => $base, 'url' => 'https://s' . $blog . '.test/wp-content/uploads' . $sub, 'subdir' => '', 'error' => false );
	}
}
{
	if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
		function wp_convert_hr_to_bytes( $value ) {
			$value = strtolower( trim( (string) $value ) );
			$bytes = (int) $value;
			if ( false !== strpos( $value, 'g' ) ) {
				$bytes *= 1073741824;
			} elseif ( false !== strpos( $value, 'm' ) ) {
				$bytes *= 1048576;
			} elseif ( false !== strpos( $value, 'k' ) ) {
				$bytes *= 1024;
			}
			return $bytes;
		}
	}
	if ( ! function_exists( 'wp_raise_memory_limit' ) ) {
		function wp_raise_memory_limit( $context = 'admin' ) {
			return false;
		}
	}
}
{
	if ( ! function_exists( 'wp_doing_ajax' ) ) {
		function wp_doing_ajax() {
			return ! empty( $GLOBALS['wp']['ajax'] );
		}
	}
	if ( ! function_exists( 'wp_doing_cron' ) ) {
		function wp_doing_cron() {
			return ! empty( $GLOBALS['wp']['cron_request'] );
		}
	}
	if ( ! function_exists( 'is_admin' ) ) {
		function is_admin() {
			return ! empty( $GLOBALS['wp']['admin'] );
		}
	}
	if ( ! function_exists( 'is_network_admin' ) ) {
		function is_network_admin() {
			return ! empty( $GLOBALS['wp']['network_admin'] );
		}
	}
}
{
	if ( ! function_exists( 'current_time' ) ) {
		function current_time( $type, $gmt = 0 ) {
			return 'timestamp' === $type || 'U' === $type ? f8_time() : gmdate( 'mysql' === $type ? 'Y-m-d H:i:s' : $type, f8_time() );
		}
	}
	if ( ! function_exists( 'wp_date' ) ) {
		function wp_date( $format, $timestamp = null ) {
			return gmdate( $format, null === $timestamp ? f8_time() : (int) $timestamp );
		}
	}
}

/* ------------------------------------------------------------------ Traducción y escapes */

{
	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ) {
			return $text;
		}
	}
	if ( ! function_exists( '_e' ) ) {
		function _e( $text, $domain = 'default' ) {
			echo $text; // phpcs:ignore
		}
	}
	if ( ! function_exists( '_x' ) ) {
		function _x( $text, $context, $domain = 'default' ) {
			return $text;
		}
	}
	if ( ! function_exists( '_n' ) ) {
		function _n( $single, $plural, $number, $domain = 'default' ) {
			return 1 === (int) $number ? $single : $plural;
		}
	}
	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( $text, $domain = 'default' ) {
			return esc_html( $text );
		}
	}
	if ( ! function_exists( 'esc_attr__' ) ) {
		function esc_attr__( $text, $domain = 'default' ) {
			return esc_attr( $text );
		}
	}
	if ( ! function_exists( 'translate' ) ) {
		function translate( $text, $domain = 'default' ) {
			return $text;
		}
	}
}
{
	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $v ) {
			return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' );
		}
	}
	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $v ) {
			return esc_html( $v );
		}
	}
	if ( ! function_exists( 'esc_textarea' ) ) {
		function esc_textarea( $v ) {
			return esc_html( $v );
		}
	}
	/** Sólo http(s), rutas relativas y `#`; lo demás, vacío (como esc_url con sus protocolos). */
	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( $url ) {
			$url = trim( (string) $url );
			if ( '' === $url || preg_match( '#^(javascript|data|vbscript):#i', preg_replace( '/[\x00-\x20]+/', '', $url ) ) ) {
				return '';
			}
			return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
		}
	}
	if ( ! function_exists( 'esc_url_raw' ) ) {
		function esc_url_raw( $url ) {
			$url = trim( (string) $url );
			return preg_match( '#^(javascript|data|vbscript):#i', $url ) ? '' : $url;
		}
	}
	if ( ! function_exists( 'esc_js' ) ) {
		function esc_js( $v ) {
			return addslashes( (string) $v );
		}
	}
}
{
	if ( ! function_exists( 'wp_strip_all_tags' ) ) {
		function wp_strip_all_tags( $text, $remove_breaks = false ) {
			$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
			$text = strip_tags( $text );
			return trim( $remove_breaks ? preg_replace( '/[\r\n\t ]+/', ' ', $text ) : $text );
		}
	}
	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
		}
	}
	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $text ) {
			return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
		}
	}
	if ( ! function_exists( 'sanitize_title' ) ) {
		function sanitize_title( $title ) {
			$title = strtolower( remove_accents_f8( strip_tags( (string) $title ) ) );
			$title = preg_replace( '/[^a-z0-9_\-]+/', '-', $title );
			return trim( preg_replace( '/-+/', '-', $title ), '-' );
		}
	}
	if ( ! function_exists( 'sanitize_file_name' ) ) {
		function sanitize_file_name( $name ) {
			$name = preg_replace( '/[^A-Za-z0-9._\-]+/', '-', remove_accents_f8( (string) $name ) );
			return trim( $name, '.-' );
		}
	}
	if ( ! function_exists( 'remove_accents_f8' ) ) {
		function remove_accents_f8( $text ) {
			return strtr( $text, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N' ) );
		}
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $text ) {
		return preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	}
}
if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( $text ) {
		return remove_accents_f8( (string) $text );
	}
}

/* ------------------------------------------------------------------ Usuarios y capacidades */

{
	if ( ! function_exists( 'get_current_user_id' ) ) {
		function get_current_user_id() {
			return (int) $GLOBALS['wp']['current_user'];
		}
	}
	if ( ! function_exists( 'wp_set_current_user' ) ) {
		function wp_set_current_user( $id ) {
			$GLOBALS['wp']['current_user'] = (int) $id;
			return get_userdata( $id );
		}
	}
	if ( ! function_exists( 'get_userdata' ) ) {
		function get_userdata( $id ) {
			$user = $GLOBALS['wp']['users'][ (int) $id ] ?? null;
			return $user ? (object) $user : false;
		}
	}
	if ( ! function_exists( 'get_user_by' ) ) {
		function get_user_by( $field, $value ) {
			foreach ( $GLOBALS['wp']['users'] as $user ) {
				if ( ( 'id' === $field && (int) $user['ID'] === (int) $value ) || ( 'login' === $field && $user['user_login'] === $value ) ) {
					return (object) $user;
				}
			}
			return false;
		}
	}
	if ( ! function_exists( 'is_user_member_of_blog' ) ) {
		function is_user_member_of_blog( $user_id = 0, $blog_id = 0 ) {
			$user = $GLOBALS['wp']['users'][ (int) ( $user_id ? $user_id : get_current_user_id() ) ] ?? null;
			$blog = (int) ( $blog_id ? $blog_id : $GLOBALS['wp']['blog'] );
			return $user && ( null === $user['blogs'] || in_array( $blog, array_map( 'intval', (array) $user['blogs'] ), true ) );
		}
	}
	/** Superadministrador: en una red, `super`; en un sitio individual, quien puede `delete_users` (como WordPress). */
	if ( ! function_exists( 'is_super_admin' ) ) {
		function is_super_admin( $user_id = false ) {
			$id   = (int) ( false === $user_id ? get_current_user_id() : $user_id );
			$user = $GLOBALS['wp']['users'][ $id ] ?? null;
			if ( ! $user ) {
				return false;
			}
			return is_multisite() ? ! empty( $user['super'] ) : f8_has_cap( $user, 'delete_users' );
		}
	}
	/** Capacidades por rol (lo justo): `administrator` lo puede todo salvo lo de la red; `editor`, editar. */
	if ( ! function_exists( 'f8_has_cap' ) ) {
		function f8_has_cap( array $user, $cap ) {
			$blog = (int) $GLOBALS['wp']['blog'];
			$caps = isset( $user['caps_by_blog'][ $blog ] ) ? (array) $user['caps_by_blog'][ $blog ] : (array) $user['caps'];
			if ( isset( $caps[ $cap ] ) ) {
				return (bool) $caps[ $cap ];
			}
			$network_caps = array( 'manage_network', 'manage_network_options', 'manage_network_plugins', 'manage_sites', 'manage_network_users' );
			if ( ! empty( $caps['administrator'] ) ) {
				if ( is_multisite() && ( in_array( $cap, $network_caps, true ) || 'unfiltered_html' === $cap ) ) {
					return false;
				}
				return true;
			}
			if ( ! empty( $caps['editor'] ) ) {
				return in_array( $cap, array( 'edit_posts', 'edit_others_posts', 'edit_pages', 'edit_others_pages', 'publish_posts', 'publish_pages', 'upload_files', 'unfiltered_html', 'read' ), true ) && ( 'unfiltered_html' !== $cap || ! is_multisite() );
			}
			return false;
		}
	}
	if ( ! function_exists( 'user_can' ) ) {
		function user_can( $user, $cap, ...$args ) {
			$id   = is_object( $user ) ? (int) $user->ID : (int) $user;
			$data = $GLOBALS['wp']['users'][ $id ] ?? null;
			if ( ! $data ) {
				return false;
			}
			if ( 'do_not_allow' === $cap ) {
				return false;
			}
			if ( is_multisite() && ! empty( $data['super'] ) ) {
				return true;
			}
			if ( is_multisite() && ! is_user_member_of_blog( $id ) ) {
				return false;
			}
			// Capacidades de un objeto: editar o borrar una entrada concreta = editar entradas.
			if ( in_array( $cap, array( 'edit_post', 'delete_post', 'read_post', 'publish_post' ), true ) ) {
				$cap = 'read_post' === $cap ? 'read' : ( 'delete_post' === $cap ? 'delete_posts' : 'edit_posts' );
				if ( 'delete_posts' === $cap && ! isset( $data['caps']['delete_posts'] ) && ! empty( $data['caps']['administrator'] ) ) {
					return true;
				}
			}
			return f8_has_cap( $data, $cap );
		}
	}
	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( $cap, ...$args ) {
			return user_can( get_current_user_id(), $cap, ...$args );
		}
	}
	if ( ! function_exists( 'get_user_meta' ) ) {
		function get_user_meta( $user_id, $key = '', $single = false ) {
			$value = $GLOBALS['wp']['user_meta'][ (int) $user_id ][ $key ] ?? null;
			return $single ? ( null === $value ? '' : $value ) : ( null === $value ? array() : array( $value ) );
		}
	}
	if ( ! function_exists( 'update_user_meta' ) ) {
		function update_user_meta( $user_id, $key, $value ) {
			$GLOBALS['wp']['user_meta'][ (int) $user_id ][ $key ] = wp_unslash( $value );
			return true;
		}
	}
	if ( ! function_exists( 'delete_user_meta' ) ) {
		function delete_user_meta( $user_id, $key ) {
			unset( $GLOBALS['wp']['user_meta'][ (int) $user_id ][ $key ] );
			return true;
		}
	}
	if ( ! function_exists( 'get_users' ) ) {
		function get_users( $args = array() ) {
			$out = array();
			foreach ( $GLOBALS['wp']['users'] as $id => $user ) {
				if ( isset( $args['blog_id'] ) && $args['blog_id'] && ! is_user_member_of_blog( $id, (int) $args['blog_id'] ) ) {
					continue;
				}
				$out[] = 'ID' === ( $args['fields'] ?? '' ) || 'ids' === ( $args['fields'] ?? '' ) ? (int) $id : (object) $user;
			}
			return $out;
		}
	}
}

/* ------------------------------------------------------------------ Nonces y URL */

{
	if ( ! function_exists( 'wp_create_nonce' ) ) {
		function wp_create_nonce( $action = -1 ) {
			return 'n-' . $action;
		}
	}
	if ( ! function_exists( 'wp_verify_nonce' ) ) {
		function wp_verify_nonce( $nonce, $action = -1 ) {
			return ! empty( $GLOBALS['wp']['nonces'] ) && 'n-' . $action === $nonce ? 1 : false;
		}
	}
	if ( ! function_exists( 'check_ajax_referer' ) ) {
		function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
			$nonce = $_REQUEST[ $query_arg ? $query_arg : '_ajax_nonce' ] ?? ( $_REQUEST['_wpnonce'] ?? '' ); // phpcs:ignore
			$ok    = wp_verify_nonce( $nonce, $action );
			if ( ! $ok && $die ) {
				throw new \RuntimeException( 'check_ajax_referer: nonce no válido para ' . $action );
			}
			return $ok;
		}
	}
	if ( ! function_exists( 'check_admin_referer' ) ) {
		function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
			return check_ajax_referer( $action, $query_arg );
		}
	}
}
{
	if ( ! function_exists( 'home_url' ) ) {
		function home_url( $path = '' ) {
			$site = $GLOBALS['wp']['sites'][ $GLOBALS['wp']['blog'] ] ?? array( 'domain' => 's1.test', 'path' => '/' );
			return 'https://' . $site['domain'] . rtrim( $site['path'], '/' ) . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) );
		}
	}
	if ( ! function_exists( 'site_url' ) ) {
		function site_url( $path = '' ) {
			return home_url( $path );
		}
	}
	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( $path = '' ) {
			return home_url( '/wp-admin/' . ltrim( $path, '/' ) );
		}
	}
	if ( ! function_exists( 'network_admin_url' ) ) {
		function network_admin_url( $path = '' ) {
			$site = $GLOBALS['wp']['sites'][ $GLOBALS['wp']['main_site'] ] ?? array( 'domain' => 's1.test', 'path' => '/' );
			return 'https://' . $site['domain'] . rtrim( $site['path'], '/' ) . '/wp-admin/network/' . ltrim( $path, '/' );
		}
	}
	if ( ! function_exists( 'get_admin_url' ) ) {
		function get_admin_url( $blog_id = null, $path = '' ) {
			switch_to_blog( null === $blog_id ? $GLOBALS['wp']['blog'] : $blog_id );
			try {
				return admin_url( $path );
			} finally {
				restore_current_blog();
			}
		}
	}
	if ( ! function_exists( 'wp_parse_url' ) ) {
		function wp_parse_url( $url, $component = -1 ) {
			return parse_url( $url, $component );
		}
	}
	if ( ! function_exists( 'add_query_arg' ) ) {
		function add_query_arg( ...$args ) {
			if ( is_array( $args[0] ) ) {
				$url = $args[1] ?? '';
				$q   = $args[0];
			} else {
				$url = $args[2] ?? '';
				$q   = array( $args[0] => $args[1] );
			}
			return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $q );
		}
	}
	if ( ! function_exists( 'plugins_url' ) ) {
		function plugins_url( $path = '', $plugin = '' ) {
			return 'https://s' . $GLOBALS['wp']['blog'] . '.test/wp-content/plugins/' . ( '' !== $plugin ? basename( dirname( $plugin ) ) . '/' : '' ) . ltrim( $path, '/' );
		}
	}
	if ( ! function_exists( 'get_bloginfo' ) ) {
		function get_bloginfo( $show = '' ) {
			return 'version' === $show ? '7.1.4' : 'Sitio ' . $GLOBALS['wp']['blog'];
		}
	}
}

/* ------------------------------------------------------------------ Plugins */

if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins( $folder = '' ) {
		return $GLOBALS['wp']['plugins'];
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		$file = str_replace( '\\', '/', (string) $file );
		if ( preg_match( '#/plugins/(.+)$#', $file, $m ) ) {
			return $m[1];
		}
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
if ( ! function_exists( 'deactivate_plugins' ) ) {
	/** Una llamada = una escritura de `active_plugins` (o de `active_sitewide_plugins` con $network). */
	function deactivate_plugins( $plugins, $silent = false, $network_wide = null ) {
		$files                      = array_values( (array) $plugins );
		$network                    = (bool) $network_wide;
		$GLOBALS['wp']['writes'][]  = array( $files, $network, (int) $GLOBALS['wp']['blog'] );
		if ( $network ) {
			foreach ( $files as $file ) {
				unset( $GLOBALS['wp']['site_options']['active_sitewide_plugins'][ $file ] );
			}
			return;
		}
		$active = (array) get_option( 'active_plugins', array() );
		update_option( 'active_plugins', array_values( array_diff( $active, $files ) ) );
	}
}
{
	if ( ! function_exists( 'is_plugin_active' ) ) {
		function is_plugin_active( $file ) {
			return in_array( $file, (array) get_option( 'active_plugins', array() ), true ) || is_plugin_active_for_network( $file );
		}
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		function is_plugin_active_for_network( $file ) {
			return is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $file ] );
		}
	}
}

/* ------------------------------------------------------------------ WP-Cron */

{
	if ( ! function_exists( 'wp_next_scheduled' ) ) {
		function wp_next_scheduled( $hook, $args = array() ) {
			return $GLOBALS['wp']['cron'][ $GLOBALS['wp']['blog'] ][ $hook ]['time'] ?? false;
		}
	}
	if ( ! function_exists( 'wp_schedule_single_event' ) ) {
		function wp_schedule_single_event( $time, $hook, $args = array(), $wp_error = false ) {
			$GLOBALS['wp']['cron'][ $GLOBALS['wp']['blog'] ][ $hook ] = array( 'time' => (int) $time, 'args' => (array) $args, 'recurrence' => false );
			return true;
		}
	}
	if ( ! function_exists( 'wp_schedule_event' ) ) {
		function wp_schedule_event( $time, $recurrence, $hook, $args = array(), $wp_error = false ) {
			$GLOBALS['wp']['cron'][ $GLOBALS['wp']['blog'] ][ $hook ] = array( 'time' => (int) $time, 'args' => (array) $args, 'recurrence' => $recurrence );
			return true;
		}
	}
	if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
		function wp_clear_scheduled_hook( $hook, $args = array() ) {
			$had = isset( $GLOBALS['wp']['cron'][ $GLOBALS['wp']['blog'] ][ $hook ] );
			unset( $GLOBALS['wp']['cron'][ $GLOBALS['wp']['blog'] ][ $hook ] );
			return $had ? 1 : 0;
		}
	}
	if ( ! function_exists( 'wp_unschedule_event' ) ) {
		function wp_unschedule_event( $time, $hook, $args = array() ) {
			return wp_clear_scheduled_hook( $hook );
		}
	}
	if ( ! function_exists( 'wp_unschedule_hook' ) ) {
		function wp_unschedule_hook( $hook ) {
			return wp_clear_scheduled_hook( $hook );
		}
	}
}

/* ------------------------------------------------------------------ Entradas, metadatos y términos */

{
	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $post = null, $output = 'OBJECT', $filter = 'raw' ) {
			if ( is_object( $post ) ) {
				$post = (int) $post->ID;
			}
			$row = $GLOBALS['wp']['posts'][ $GLOBALS['wp']['blog'] ][ (int) $post ] ?? null;
			return $row ? (object) $row : null;
		}
	}
	if ( ! function_exists( 'get_post_field' ) ) {
		function get_post_field( $field, $post = null ) {
			$p = get_post( $post );
			return $p && isset( $p->$field ) ? $p->$field : '';
		}
	}
	if ( ! function_exists( 'get_post_type' ) ) {
		function get_post_type( $post = null ) {
			$p = get_post( $post );
			return $p ? $p->post_type : false;
		}
	}
	if ( ! function_exists( 'get_the_title' ) ) {
		function get_the_title( $post = 0 ) {
			$p = get_post( $post );
			return $p ? ( '' !== $p->post_title ? $p->post_title : 'Página ' . $p->ID ) : 'Página ' . ( is_object( $post ) ? $post->ID : (int) $post );
		}
	}
	if ( ! function_exists( 'get_post_status' ) ) {
		function get_post_status( $post = null ) {
			$p = get_post( $post );
			if ( ! $p ) {
				return false;
			}
			return apply_filters( 'get_post_status', $p->post_status, $p );
		}
	}
	if ( ! function_exists( 'get_edit_post_link' ) ) {
		function get_edit_post_link( $post = 0, $context = 'display' ) {
			$p = get_post( $post );
			return $p ? admin_url( 'post.php?post=' . $p->ID . '&action=edit' ) : null;
		}
	}
	if ( ! function_exists( 'get_permalink' ) ) {
		function get_permalink( $post = 0 ) {
			$p = get_post( $post );
			return $p ? home_url( '/?p=' . $p->ID ) : false;
		}
	}
	if ( ! function_exists( 'wp_is_post_revision' ) ) {
		function wp_is_post_revision( $post ) {
			$p = get_post( $post );
			return $p && 'revision' === $p->post_type ? (int) $p->post_parent : false;
		}
	}
	if ( ! function_exists( 'wp_is_post_autosave' ) ) {
		function wp_is_post_autosave( $post ) {
			$p = get_post( $post );
			return $p && 'revision' === $p->post_type && false !== strpos( (string) $p->post_name, '-autosave-v' ) ? (int) $p->post_parent : false;
		}
	}
	/**
	 * Como WordPress: aplica `wp_insert_post_data`, guarda, escribe `meta_input` con update_post_meta() (que quita las
	 * barras: la causa de C2-10) y dispara `save_post` y `wp_insert_post`.
	 */
	if ( ! function_exists( 'wp_insert_post' ) ) {
		function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
			$postarr = is_object( $postarr ) ? get_object_vars( $postarr ) : (array) $postarr;
			$blog    = $GLOBALS['wp']['blog'];
			$update  = ! empty( $postarr['ID'] );
			$id      = $update ? (int) $postarr['ID'] : ++$GLOBALS['wp']['next_id'][ $blog ];
			$data    = wp_unslash( array_intersect_key( $postarr, f8_post_defaults( array() ) ) );
			if ( ! isset( $data['post_status'] ) && ! $update ) {
				$data['post_status'] = 'draft';
			}
			if ( ! isset( $data['post_type'] ) && ! $update ) {
				$data['post_type'] = 'post';
			}
			$data = apply_filters( 'wp_insert_post_data', $data, $postarr, $postarr, $update );
			if ( ! empty( $GLOBALS['wp']['fail_insert'] ) ) {
				return $wp_error ? new WP_Error( 'db_insert_error', 'No se pudo insertar.' ) : 0;
			}
			$old  = $update ? ( $GLOBALS['wp']['posts'][ $blog ][ $id ] ?? array() ) : array();
			$row  = f8_post_defaults( array_merge( $old, $data, array( 'ID' => $id ) ) );
			$GLOBALS['wp']['posts'][ $blog ][ $id ] = $row;
			foreach ( (array) ( $postarr['meta_input'] ?? array() ) as $key => $value ) {
				update_post_meta( $id, $key, $value );
			}
			foreach ( (array) ( $postarr['tax_input'] ?? array() ) as $taxonomy => $terms ) {
				wp_set_object_terms( $id, $terms, $taxonomy );
			}
			if ( $fire_after_hooks ) {
				do_action( 'save_post', $id, (object) $row, $update );
				do_action( 'wp_insert_post', $id, (object) $row, $update );
			}
			return $id;
		}
	}
	if ( ! function_exists( 'wp_update_post' ) ) {
		function wp_update_post( $postarr = array(), $wp_error = false ) {
			$postarr = is_object( $postarr ) ? get_object_vars( $postarr ) : (array) $postarr;
			if ( empty( $postarr['ID'] ) || ! get_post( (int) $postarr['ID'] ) ) {
				return $wp_error ? new WP_Error( 'invalid_post', 'No existe.' ) : 0;
			}
			return wp_insert_post( $postarr, $wp_error );
		}
	}
	if ( ! function_exists( 'wp_trash_post' ) ) {
		function wp_trash_post( $post_id = 0 ) {
			$blog = $GLOBALS['wp']['blog'];
			if ( ! isset( $GLOBALS['wp']['posts'][ $blog ][ (int) $post_id ] ) ) {
				return false;
			}
			$GLOBALS['wp']['trashed'][] = array( $blog, (int) $post_id );
			$GLOBALS['wp']['posts'][ $blog ][ (int) $post_id ]['post_status'] = 'trash';
			return get_post( $post_id );
		}
	}
	if ( ! function_exists( 'wp_delete_post' ) ) {
		function wp_delete_post( $post_id = 0, $force = false ) {
			$blog = $GLOBALS['wp']['blog'];
			$post = get_post( $post_id );
			if ( ! $post ) {
				return false;
			}
			$GLOBALS['wp']['deleted'][] = array( $blog, (int) $post_id, (bool) $force );
			unset( $GLOBALS['wp']['posts'][ $blog ][ (int) $post_id ], $GLOBALS['wp']['meta'][ $blog ][ (int) $post_id ] );
			return $post;
		}
	}
	if ( ! function_exists( 'clean_post_cache' ) ) {
		function clean_post_cache( $post ) {
			$GLOBALS['wp']['cleaned'][] = is_object( $post ) ? (int) $post->ID : (int) $post;
		}
	}
	if ( ! function_exists( 'wp_transition_post_status' ) ) {
		function wp_transition_post_status( $new_status, $old_status, $post ) {
			$GLOBALS['wp']['transitions'][] = array( (int) $post->ID, $old_status, $new_status );
			do_action( 'transition_post_status', $new_status, $old_status, $post );
			do_action( "{$old_status}_to_{$new_status}", $post );
			do_action( "{$new_status}_{$post->post_type}", (int) $post->ID, $post );
		}
	}
	if ( ! function_exists( 'wp_publish_post' ) ) {
		function wp_publish_post( $post ) {
			$p = get_post( $post );
			if ( ! $p ) {
				return;
			}
			$GLOBALS['wp']['posts'][ $GLOBALS['wp']['blog'] ][ $p->ID ]['post_status'] = 'publish';
			wp_transition_post_status( 'publish', $p->post_status, get_post( $p->ID ) );
		}
	}
	/**
	 * Entradas: `post_type` (o 'any'), `post_status` (por defecto 'publish'; 'any' sin papelera), `numberposts` /
	 * `posts_per_page`, `fields` ('ids'), `post__in`, `include`, `post_parent`, `meta_key`/`meta_value`, `meta_query`
	 * sencilla (`key`, `value`, `compare` = / EXISTS / NOT EXISTS), `orderby` (ID, date, menu_order) y `order`.
	 */
	if ( ! function_exists( 'get_posts' ) ) {
		function get_posts( $args = array() ) {
			$args   = (array) $args;
			$types  = $args['post_type'] ?? 'post';
			$status = $args['post_status'] ?? 'publish';
			$limit  = (int) ( $args['numberposts'] ?? ( $args['posts_per_page'] ?? 5 ) );
			$in     = array_map( 'intval', (array) ( $args['post__in'] ?? ( $args['include'] ?? array() ) ) );
			$out    = array();
			foreach ( $GLOBALS['wp']['posts'][ $GLOBALS['wp']['blog'] ] as $id => $post ) {
				if ( 'any' !== $types && ! in_array( $post['post_type'], (array) $types, true ) ) {
					continue;
				}
				if ( 'any' === $status ? in_array( $post['post_status'], array( 'trash', 'auto-draft' ), true ) : ! in_array( $post['post_status'], (array) $status, true ) ) {
					continue;
				}
				if ( $in && ! in_array( (int) $id, $in, true ) ) {
					continue;
				}
				if ( isset( $args['post_parent'] ) && (int) $args['post_parent'] !== (int) $post['post_parent'] ) {
					continue;
				}
				$queries = (array) ( $args['meta_query'] ?? array() );
				if ( isset( $args['meta_key'] ) ) {
					$queries[] = array( 'key' => $args['meta_key'], 'value' => $args['meta_value'] ?? null, 'compare' => isset( $args['meta_value'] ) ? '=' : 'EXISTS' );
				}
				foreach ( $queries as $query ) {
					if ( ! is_array( $query ) || ! isset( $query['key'] ) ) {
						continue;
					}
					$has     = isset( $GLOBALS['wp']['meta'][ $GLOBALS['wp']['blog'] ][ $id ][ $query['key'] ] );
					$value   = $has ? $GLOBALS['wp']['meta'][ $GLOBALS['wp']['blog'] ][ $id ][ $query['key'] ] : null;
					$compare = strtoupper( (string) ( $query['compare'] ?? ( isset( $query['value'] ) ? '=' : 'EXISTS' ) ) );
					if ( ( 'EXISTS' === $compare && ! $has ) || ( 'NOT EXISTS' === $compare && $has ) || ( '=' === $compare && ( ! $has || (string) $value !== (string) $query['value'] ) ) ) {
						continue 2;
					}
				}
				$out[ $id ] = $post;
			}
			$orderby = $args['orderby'] ?? 'date';
			$order   = strtoupper( (string) ( $args['order'] ?? 'DESC' ) );
			uasort( $out, static function ( $a, $b ) use ( $orderby ) {
				if ( 'menu_order' === $orderby ) {
					return $a['menu_order'] <=> $b['menu_order'] ?: $a['ID'] <=> $b['ID'];
				}
				if ( 'date' === $orderby ) {
					return strcmp( $a['post_date'], $b['post_date'] ) ?: $a['ID'] <=> $b['ID'];
				}
				return $a['ID'] <=> $b['ID'];
			} );
			if ( 'DESC' === $order ) {
				$out = array_reverse( $out, true );
			}
			if ( $limit > 0 ) {
				$out = array_slice( $out, 0, $limit, true );
			}
			$ids = array_map( 'intval', array_keys( $out ) );
			return 'ids' === ( $args['fields'] ?? '' ) ? $ids : array_map( static function ( $id ) {
				return get_post( $id );
			}, $ids );
		}
	}
	if ( ! function_exists( 'get_metadata' ) ) {
		function get_metadata( $type, $id, $key = '', $single = false ) {
			$store = 'user' === $type ? ( $GLOBALS['wp']['user_meta'][ (int) $id ] ?? array() ) : ( $GLOBALS['wp']['meta'][ $GLOBALS['wp']['blog'] ][ (int) $id ] ?? array() );
			if ( '' === $key ) {
				return array_map( static function ( $v ) {
					return array( $v );
				}, $store );
			}
			if ( ! array_key_exists( $key, $store ) ) {
				return $single ? '' : array();
			}
			return $single ? $store[ $key ] : array( $store[ $key ] );
		}
	}
	/** Como WordPress: quita las barras del valor (también en arrays) antes de guardarlo. */
	if ( ! function_exists( 'update_metadata' ) ) {
		function update_metadata( $type, $id, $key, $value, $prev = '' ) {
			$value = wp_unslash( $value );
			if ( 'user' === $type ) {
				$GLOBALS['wp']['user_meta'][ (int) $id ][ $key ] = $value;
			} else {
				$GLOBALS['wp']['meta'][ $GLOBALS['wp']['blog'] ][ (int) $id ][ $key ] = $value;
			}
			return true;
		}
	}
	if ( ! function_exists( 'add_metadata' ) ) {
		function add_metadata( $type, $id, $key, $value, $unique = false ) {
			$current = get_metadata( $type, $id, $key, false );
			if ( $unique && $current ) {
				return false;
			}
			return update_metadata( $type, $id, $key, $value );
		}
	}
	if ( ! function_exists( 'delete_metadata' ) ) {
		function delete_metadata( $type, $id, $key, $value = '', $all = false ) {
			if ( 'user' === $type ) {
				unset( $GLOBALS['wp']['user_meta'][ (int) $id ][ $key ] );
			} else {
				unset( $GLOBALS['wp']['meta'][ $GLOBALS['wp']['blog'] ][ (int) $id ][ $key ] );
			}
			return true;
		}
	}
	/** update_post_meta() de una revisión escribe en su padre, como en WordPress (la trampa de las revisiones). */
	if ( ! function_exists( 'f8_meta_target' ) ) {
		function f8_meta_target( $id ) {
			$parent = wp_is_post_revision( $id );
			return $parent ? $parent : (int) $id;
		}
	}
	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( $id, $key = '', $single = false ) {
			return get_metadata( 'post', $id, $key, $single );
		}
	}
	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( $id, $key, $value, $prev = '' ) {
			return update_metadata( 'post', f8_meta_target( $id ), $key, $value );
		}
	}
	if ( ! function_exists( 'add_post_meta' ) ) {
		function add_post_meta( $id, $key, $value, $unique = false ) {
			return add_metadata( 'post', f8_meta_target( $id ), $key, $value, $unique );
		}
	}
	if ( ! function_exists( 'delete_post_meta' ) ) {
		function delete_post_meta( $id, $key, $value = '' ) {
			return delete_metadata( 'post', f8_meta_target( $id ), $key );
		}
	}
	/* Términos: `$GLOBALS['wp']['terms'][blog][term_id] = { term_id, term_taxonomy_id, taxonomy, slug, name }`. */
	if ( ! function_exists( 'wp_insert_term' ) ) {
		function wp_insert_term( $name, $taxonomy, $args = array() ) {
			$slug = (string) ( $args['slug'] ?? sanitize_title( $name ) );
			$id   = ++$GLOBALS['wp']['next_term'];
			$GLOBALS['wp']['terms'][ $GLOBALS['wp']['blog'] ][ $id ] = array( 'term_id' => $id, 'term_taxonomy_id' => $id + 1000, 'taxonomy' => $taxonomy, 'slug' => $slug, 'name' => (string) $name );
			return array( 'term_id' => $id, 'term_taxonomy_id' => $id + 1000 );
		}
	}
	if ( ! function_exists( 'get_term' ) ) {
		function get_term( $term, $taxonomy = '' ) {
			$row = $GLOBALS['wp']['terms'][ $GLOBALS['wp']['blog'] ][ (int) $term ] ?? null;
			return $row && ( '' === $taxonomy || $row['taxonomy'] === $taxonomy ) ? (object) $row : null;
		}
	}
	if ( ! function_exists( 'get_term_by' ) ) {
		function get_term_by( $field, $value, $taxonomy = '' ) {
			foreach ( $GLOBALS['wp']['terms'][ $GLOBALS['wp']['blog'] ] as $row ) {
				if ( ( '' === $taxonomy || $row['taxonomy'] === $taxonomy ) && (string) ( 'id' === $field ? $row['term_id'] : ( $row[ $field ] ?? '' ) ) === (string) $value ) {
					return (object) $row;
				}
			}
			return false;
		}
	}
	if ( ! function_exists( 'term_exists' ) ) {
		function term_exists( $term, $taxonomy = '' ) {
			$row = is_numeric( $term ) ? get_term( (int) $term, $taxonomy ) : get_term_by( 'slug', $term, $taxonomy );
			return $row ? array( 'term_id' => $row->term_id, 'term_taxonomy_id' => $row->term_taxonomy_id ) : null;
		}
	}
	if ( ! function_exists( 'wp_set_object_terms' ) ) {
		function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
			$ids = array();
			foreach ( (array) $terms as $term ) {
				$row = is_int( $term ) ? get_term( $term, $taxonomy ) : get_term_by( 'slug', (string) $term, $taxonomy );
				if ( ! $row ) {
					$new = wp_insert_term( (string) $term, $taxonomy, array( 'slug' => sanitize_title( (string) $term ) ) );
					$row = get_term( $new['term_id'] );
				}
				$ids[] = (int) $row->term_id;
			}
			$blog = $GLOBALS['wp']['blog'];
			$old  = $append ? (array) ( $GLOBALS['wp']['object_terms'][ $blog ][ (int) $object_id ][ $taxonomy ] ?? array() ) : array();
			$GLOBALS['wp']['object_terms'][ $blog ][ (int) $object_id ][ $taxonomy ] = array_values( array_unique( array_merge( $old, $ids ) ) );
			return $ids;
		}
	}
	if ( ! function_exists( 'wp_get_object_terms' ) ) {
		function wp_get_object_terms( $object_ids, $taxonomies, $args = array() ) {
			$out = array();
			foreach ( (array) $object_ids as $object_id ) {
				foreach ( (array) $taxonomies as $taxonomy ) {
					foreach ( (array) ( $GLOBALS['wp']['object_terms'][ $GLOBALS['wp']['blog'] ][ (int) $object_id ][ $taxonomy ] ?? array() ) as $term_id ) {
						$out[] = 'ids' === ( $args['fields'] ?? '' ) ? (int) $term_id : get_term( $term_id );
					}
				}
			}
			return $out;
		}
	}
	if ( ! function_exists( 'get_the_terms' ) ) {
		function get_the_terms( $post, $taxonomy ) {
			$terms = wp_get_object_terms( is_object( $post ) ? $post->ID : (int) $post, $taxonomy );
			return $terms ? $terms : false;
		}
	}
}

/* ------------------------------------------------------------------ $wpdb en memoria */

if ( ! class_exists( 'F8_Wpdb' ) ) {
	/**
	 * Base de datos en memoria sobre `$GLOBALS['wp']`. Entiende las consultas de Migración y de sus frentes; registra
	 * cada consulta en `$queries` (para las pruebas de escala) y las que no entiende en `$unknown`.
	 */
	class F8_Wpdb {
		public $base_prefix = 'wp_';
		public $sitemeta    = 'wp_sitemeta';
		public $blogs       = 'wp_blogs';
		public $users       = 'wp_users';
		public $usermeta    = 'wp_usermeta';
		public $queries     = array();
		public $unknown     = array();
		public $last_error  = '';
		public $insert_id   = 0;

		public function __get( $name ) {
			$prefix = $this->get_blog_prefix();
			$tables = array( 'prefix' => $prefix, 'options' => $prefix . 'options', 'posts' => $prefix . 'posts', 'postmeta' => $prefix . 'postmeta', 'terms' => $prefix . 'terms', 'term_taxonomy' => $prefix . 'term_taxonomy', 'term_relationships' => $prefix . 'term_relationships', 'termmeta' => $prefix . 'termmeta', 'blogid' => (int) $GLOBALS['wp']['blog'] );
			return $tables[ $name ] ?? null;
		}

		public function get_blog_prefix( $blog_id = null ) {
			$blog = (int) ( null === $blog_id ? $GLOBALS['wp']['blog'] : $blog_id );
			return $blog > 1 ? $this->base_prefix . $blog . '_' : $this->base_prefix;
		}

		public function esc_like( $text ) {
			return addcslashes( (string) $text, '_%\\' );
		}

		/** Devuelve la consulta con sus argumentos adjuntos (los métodos los separan). */
		public function prepare( $query, ...$args ) {
			if ( 1 === count( $args ) && is_array( $args[0] ) ) {
				$args = $args[0];
			}
			return $query . "\0" . serialize( array_values( $args ) );
		}

		private function split( $sql ) {
			$pos = strpos( (string) $sql, "\0" );
			if ( false === $pos ) {
				return array( (string) $sql, array() );
			}
			return array( substr( $sql, 0, $pos ), (array) unserialize( substr( $sql, $pos + 1 ) ) );
		}

		/** Blog de una tabla con prefijo (`wp_options` → 1, `wp_3_postmeta` → 3). */
		private function table_blog( $sql, $table ) {
			if ( preg_match( '/\b' . preg_quote( $this->base_prefix, '/' ) . '(\d+)_' . $table . '\b/', $sql, $m ) ) {
				return (int) $m[1];
			}
			return preg_match( '/\b' . preg_quote( $this->base_prefix, '/' ) . $table . '\b/', $sql ) ? 1 : (int) $GLOBALS['wp']['blog'];
		}

		/** Condición LIKE de SQL sobre un texto (el patrón llega escapado con esc_like). */
		public static function like( $value, $pattern ) {
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
			return 1 === preg_match( '/^' . $regex . '$/s', (string) $value );
		}

		private static function stored( $value ) {
			return is_string( $value ) ? $value : ( is_scalar( $value ) ? (string) $value : serialize( $value ) );
		}

		private function remember( $sql, $args ) {
			$this->queries[] = $sql;
		}

		public function get_col( $query, $x = 0 ) {
			list( $sql, $args ) = $this->split( $query );
			$this->remember( $sql, $args );
			// Búsqueda por LIKE en metadatos: Migration::query_meta(), Dropped::documents(), etc.
			if ( preg_match( '/FROM\s+\S*postmeta\s+m\b/i', $sql ) || preg_match( '/FROM\s+\S*postmeta\s+WHERE\s+meta_key\s*=\s*%s/i', $sql ) ) {
				return $this->meta_search( $sql, $args );
			}
			if ( preg_match( '/SELECT\s+(?:DISTINCT\s+)?post_id\s+FROM\s+\S*postmeta\s+WHERE\s+meta_key\s*=\s*%s/i', $sql ) ) {
				return $this->meta_search( $sql, $args );
			}
			if ( preg_match( '/SELECT\s+ID\s+FROM\s+\S*posts\b/i', $sql ) ) {
				return $this->post_search( $sql, $args );
			}
			if ( preg_match( '/SELECT\s+option_name\s+FROM\s+(\S*options)\s+WHERE\s+option_name\s+LIKE\s+%s/i', $sql, $m ) ) {
				$blog = $this->table_blog( $sql, 'options' );
				$out  = array();
				foreach ( array_keys( $GLOBALS['wp']['options'][ $blog ] ?? array() ) as $name ) {
					if ( self::like( $name, (string) $args[0] ) ) {
						$out[] = $name;
					}
				}
				return $out;
			}
			$this->unknown[] = $sql;
			return array();
		}

		/** Metadatos: claves (`= '…'`, `= %s`, `IN (…)`), agujas LIKE, revisiones aparte, cursor y límite. */
		private function meta_search( $sql, array $args ) {
			$blog  = $this->table_blog( $sql, 'postmeta' );
			$keys  = array();
			$likes = array();
			$rest  = $args;
			if ( preg_match( "/meta_key\s+IN\s*\(([^)]*)\)/i", $sql, $m ) ) {
				preg_match_all( "/'([^']+)'/", $m[1], $mm );
				$keys = $mm[1];
			}
			if ( preg_match_all( "/meta_key\s*=\s*'([^']+)'/i", $sql, $mm ) ) {
				$keys = array_merge( $keys, $mm[1] );
			}
			$cursor = 0;
			// Los %s y %d en orden: meta_key = %s, post_id > %d, LIKE %s.
			preg_match_all( '/(meta_key\s*=\s*%s|post_id\s*>\s*%d|LIKE\s+%s|ID\s*>\s*%d)/i', $sql, $order );
			$i = 0;
			foreach ( $order[1] as $token ) {
				$value = $rest[ $i++ ] ?? null;
				if ( stripos( $token, 'meta_key' ) === 0 ) {
					$keys[] = (string) $value;
				} elseif ( stripos( $token, 'LIKE' ) === 0 ) {
					$likes[] = (string) $value;
				} else {
					$cursor = (int) $value;
				}
			}
			$revisions = null;
			if ( preg_match( "/post_type\s*<>\s*'revision'/i", $sql ) ) {
				$revisions = false;
			} elseif ( preg_match( "/post_type\s*=\s*'revision'/i", $sql ) ) {
				$revisions = true;
			}
			$ids = array();
			foreach ( $GLOBALS['wp']['meta'][ $blog ] ?? array() as $id => $meta ) {
				if ( $id <= $cursor ) {
					continue;
				}
				if ( null !== $revisions ) {
					$type = $GLOBALS['wp']['posts'][ $blog ][ $id ]['post_type'] ?? null;
					if ( null === $type || ( 'revision' === $type ) !== $revisions ) {
						continue;
					}
				}
				foreach ( $keys as $key ) {
					if ( ! array_key_exists( $key, $meta ) ) {
						continue;
					}
					if ( ! $likes ) {
						$ids[] = (int) $id;
						break;
					}
					$stored = self::stored( $meta[ $key ] );
					foreach ( $likes as $pattern ) {
						if ( self::like( $stored, $pattern ) ) {
							$ids[] = (int) $id;
							break 2;
						}
					}
				}
			}
			$ids = array_values( array_unique( $ids ) );
			sort( $ids );
			if ( preg_match( '/LIMIT\s+(\d+)(?:\s*,\s*(\d+))?/i', $sql, $m ) ) {
				$ids = isset( $m[2] ) ? array_slice( $ids, (int) $m[1], (int) $m[2] ) : array_slice( $ids, 0, (int) $m[1] );
			}
			return $ids;
		}

		/** IDs de entradas: por tipo, estado, padre o lista; la última revisión guardada. */
		private function post_search( $sql, array $args ) {
			$blog  = $this->table_blog( $sql, 'posts' );
			$posts = $GLOBALS['wp']['posts'][ $blog ] ?? array();
			$out   = array();
			preg_match_all( '/(post_parent\s*=\s*%d|post_type\s*=\s*%s|post_status\s*=\s*%s|post_name\s+NOT\s+LIKE\s+%s|ID\s*>\s*%d)/i', $sql, $order );
			$filters = array();
			$i       = 0;
			foreach ( $order[1] as $token ) {
				$filters[] = array( strtolower( preg_replace( '/\s+/', ' ', $token ) ), $args[ $i++ ] ?? null );
			}
			foreach ( $posts as $id => $post ) {
				foreach ( $filters as $filter ) {
					list( $token, $value ) = $filter;
					if ( 0 === strpos( $token, 'post_parent' ) && (int) $post['post_parent'] !== (int) $value ) {
						continue 2;
					}
					if ( 0 === strpos( $token, 'post_type' ) && $post['post_type'] !== (string) $value ) {
						continue 2;
					}
					if ( 0 === strpos( $token, 'post_status' ) && $post['post_status'] !== (string) $value ) {
						continue 2;
					}
					if ( 0 === strpos( $token, 'post_name not like' ) && self::like( $post['post_name'], (string) $value ) ) {
						continue 2;
					}
					if ( 0 === strpos( $token, 'id' ) && (int) $id <= (int) $value ) {
						continue 2;
					}
				}
				if ( preg_match( "/post_type\s*=\s*'([a-z0-9_-]+)'/i", $sql, $m ) && $post['post_type'] !== $m[1] ) {
					continue;
				}
				$out[] = (int) $id;
			}
			sort( $out );
			if ( preg_match( '/ORDER BY ID DESC/i', $sql ) ) {
				$out = array_reverse( $out );
			}
			if ( preg_match( '/LIMIT\s+(\d+)/i', $sql, $m ) ) {
				$out = array_slice( $out, 0, (int) $m[1] );
			}
			return $out;
		}

		public function get_var( $query, $x = 0, $y = 0 ) {
			list( $sql, $args ) = $this->split( $query );
			$this->remember( $sql, $args );
			if ( preg_match( '/SELECT\s+option_value\s+FROM\s+\S*options\s+WHERE\s+option_name\s*=\s*%s/i', $sql ) ) {
				$blog = $this->table_blog( $sql, 'options' );
				$opts = $GLOBALS['wp']['options'][ $blog ] ?? array();
				return array_key_exists( (string) $args[0], $opts ) ? self::stored( $opts[ (string) $args[0] ] ) : null;
			}
			if ( preg_match( '/SUM\(\s*CAST\(\s*meta_value/i', $sql ) ) {
				$blog = $this->table_blog( $sql, 'postmeta' );
				$sum  = 0;
				foreach ( $GLOBALS['wp']['meta'][ $blog ] ?? array() as $meta ) {
					$sum += (int) ( $meta[ (string) $args[0] ] ?? 0 );
				}
				return $sum;
			}
			if ( preg_match( '/SELECT\s+ID\s+FROM/i', $sql ) ) {
				$col = $this->post_search( $sql, $args );
				return $col ? $col[0] : null;
			}
			if ( preg_match( '/SELECT\s+COUNT\(\s*\*\s*\)/i', $sql ) && preg_match( '/postmeta/i', $sql ) ) {
				return count( $this->meta_search( $sql, $args ) );
			}
			$this->unknown[] = $sql;
			return null;
		}

		/** Cerrojos y cambios de estado: INSERT IGNORE, UPDATE condicionado y DELETE por token sobre `options`; `post_status`. */
		public function query( $query ) {
			list( $sql, $args ) = $this->split( $query );
			$this->remember( $sql, $args );
			if ( preg_match( '/^\s*INSERT\s+IGNORE\s+INTO\s+(\S*options)\b/i', $sql ) ) {
				$blog = $this->table_blog( $sql, 'options' );
				$name = (string) $args[0];
				if ( array_key_exists( $name, $GLOBALS['wp']['options'][ $blog ] ?? array() ) ) {
					return 0;
				}
				$GLOBALS['wp']['options'][ $blog ][ $name ]  = (string) $args[1];
				$GLOBALS['wp']['autoload'][ $blog ][ $name ] = ! preg_match( "/'(off|no)'/i", $sql );
				return 1;
			}
			if ( preg_match( '/^\s*UPDATE\s+(\S*options)\s+SET\s+option_value\s*=\s*%s\s+WHERE\s+option_name\s*=\s*%s\s+AND\s+option_value\s*(=|LIKE)\s*%s/i', $sql, $m ) ) {
				$blog = $this->table_blog( $sql, 'options' );
				$name = (string) $args[1];
				$have = $GLOBALS['wp']['options'][ $blog ][ $name ] ?? null;
				if ( null === $have || ( '=' === $m[2] ? (string) $have !== (string) $args[2] : ! self::like( (string) $have, (string) $args[2] ) ) ) {
					return 0;
				}
				$GLOBALS['wp']['options'][ $blog ][ $name ] = (string) $args[0];
				return 1;
			}
			if ( preg_match( '/^\s*DELETE\s+FROM\s+(\S*options)\s+WHERE\s+option_name\s*=\s*%s(?:\s+AND\s+option_value\s*(=|LIKE)\s*%s)?/i', $sql, $m ) ) {
				$blog = $this->table_blog( $sql, 'options' );
				$name = (string) $args[0];
				if ( ! array_key_exists( $name, $GLOBALS['wp']['options'][ $blog ] ?? array() ) ) {
					return 0;
				}
				if ( isset( $m[2] ) && '' !== $m[2] ) {
					$have = (string) $GLOBALS['wp']['options'][ $blog ][ $name ];
					if ( '=' === $m[2] ? $have !== (string) $args[1] : ! self::like( $have, (string) $args[1] ) ) {
						return 0;
					}
				}
				unset( $GLOBALS['wp']['options'][ $blog ][ $name ], $GLOBALS['wp']['autoload'][ $blog ][ $name ] );
				return 1;
			}
			if ( preg_match( '/^\s*UPDATE\s+(\S*posts)\s+SET\s+post_status\s*=\s*%s\s+WHERE\s+ID\s*=\s*%d(?:\s+AND\s+post_status\s*=\s*%s)?/i', $sql, $m ) ) {
				$blog = $this->table_blog( $sql, 'posts' );
				$id   = (int) $args[1];
				if ( ! isset( $GLOBALS['wp']['posts'][ $blog ][ $id ] ) || ( isset( $args[2] ) && $GLOBALS['wp']['posts'][ $blog ][ $id ]['post_status'] !== (string) $args[2] ) ) {
					return 0;
				}
				$GLOBALS['wp']['posts'][ $blog ][ $id ]['post_status'] = (string) $args[0];
				return 1;
			}
			$this->unknown[] = $sql;
			return 0;
		}

		/** Filas: opciones y metadatos con agujas LIKE (y exclusiones NOT LIKE 'prefijo%'), y metadatos de entradas. */
		public function get_results( $query, $output = 'OBJECT' ) {
			list( $sql, $args ) = $this->split( $query );
			$this->remember( $sql, $args );
			$rows = array();
			if ( preg_match( '/FROM\s+(\S*options)\b/i', $sql ) ) {
				$blog    = $this->table_blog( $sql, 'options' );
				$likes   = array();
				$exclude = array();
				preg_match_all( '/(option_name\s+NOT\s+LIKE\s+%s|option_name\s+LIKE\s+%s|option_value\s+LIKE\s+%s)/i', $sql, $order );
				$i = 0;
				foreach ( $order[1] as $token ) {
					$value = (string) ( $args[ $i++ ] ?? '' );
					if ( preg_match( '/NOT\s+LIKE/i', $token ) ) {
						$exclude[] = $value;
					} elseif ( 0 === stripos( $token, 'option_name' ) ) {
						$likes[] = array( 'name', $value );
					} else {
						$likes[] = array( 'value', $value );
					}
				}
				foreach ( $GLOBALS['wp']['options'][ $blog ] ?? array() as $name => $value ) {
					foreach ( $exclude as $pattern ) {
						if ( self::like( $name, $pattern ) ) {
							continue 2;
						}
					}
					$stored = self::stored( $value );
					$match  = ! $likes;
					foreach ( $likes as $like ) {
						$match = $match || self::like( 'name' === $like[0] ? $name : $stored, $like[1] );
					}
					if ( $match ) {
						$rows[] = (object) array( 'option_name' => $name, 'option_value' => $stored, 'autoload' => ! empty( $GLOBALS['wp']['autoload'][ $blog ][ $name ] ) ? 'on' : 'off' );
					}
				}
				return $this->output( $rows, $output );
			}
			if ( preg_match( '/FROM\s+(\S*postmeta)\b/i', $sql ) ) {
				$blog = $this->table_blog( $sql, 'postmeta' );
				$ids  = $this->meta_search( $sql, $args );
				$keys = array();
				if ( preg_match_all( "/meta_key\s*=\s*'([^']+)'/i", $sql, $mm ) ) {
					$keys = $mm[1];
				}
				foreach ( $ids as $id ) {
					foreach ( $GLOBALS['wp']['meta'][ $blog ][ $id ] as $key => $value ) {
						if ( $keys && ! in_array( $key, $keys, true ) ) {
							continue;
						}
						$rows[] = (object) array( 'post_id' => $id, 'meta_key' => $key, 'meta_value' => self::stored( $value ) );
					}
				}
				return $this->output( $rows, $output );
			}
			$this->unknown[] = $sql;
			return array();
		}

		public function get_row( $query, $output = 'OBJECT', $y = 0 ) {
			$rows = $this->get_results( $query, $output );
			return $rows ? $rows[0] : null;
		}

		private function output( array $rows, $output ) {
			if ( 'ARRAY_A' === $output ) {
				return array_map( 'get_object_vars', $rows );
			}
			return $rows;
		}

		/** INSERT de una fila de `options` (para quien no use INSERT IGNORE): falla si ya existe. */
		public function insert( $table, $data, $format = null ) {
			if ( preg_match( '/options$/', (string) $table ) && isset( $data['option_name'] ) ) {
				$blog = $this->table_blog( (string) $table, 'options' );
				if ( array_key_exists( $data['option_name'], $GLOBALS['wp']['options'][ $blog ] ?? array() ) ) {
					return false;
				}
				$GLOBALS['wp']['options'][ $blog ][ $data['option_name'] ] = (string) ( $data['option_value'] ?? '' );
				return 1;
			}
			$this->unknown[] = 'insert ' . $table;
			return false;
		}

		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			if ( preg_match( '/posts$/', (string) $table ) && isset( $where['ID'] ) ) {
				$blog = $this->table_blog( (string) $table, 'posts' );
				if ( ! isset( $GLOBALS['wp']['posts'][ $blog ][ (int) $where['ID'] ] ) ) {
					return 0;
				}
				foreach ( $where as $column => $value ) {
					if ( 'ID' !== $column && (string) ( $GLOBALS['wp']['posts'][ $blog ][ (int) $where['ID'] ][ $column ] ?? '' ) !== (string) $value ) {
						return 0;
					}
				}
				$GLOBALS['wp']['posts'][ $blog ][ (int) $where['ID'] ] = array_merge( $GLOBALS['wp']['posts'][ $blog ][ (int) $where['ID'] ], $data );
				return 1;
			}
			$this->unknown[] = 'update ' . $table;
			return false;
		}

		public function delete( $table, $where, $where_format = null ) {
			if ( preg_match( '/options$/', (string) $table ) && isset( $where['option_name'] ) ) {
				$blog = $this->table_blog( (string) $table, 'options' );
				$had  = array_key_exists( $where['option_name'], $GLOBALS['wp']['options'][ $blog ] ?? array() );
				unset( $GLOBALS['wp']['options'][ $blog ][ $where['option_name'] ] );
				return $had ? 1 : 0;
			}
			$this->unknown[] = 'delete ' . $table;
			return false;
		}
	}
}

if ( ! function_exists( 'f8_wpdb' ) ) {
	/** Instala (o reinstala) el $wpdb en memoria. */
	function f8_wpdb( $class = 'F8_Wpdb' ) {
		$GLOBALS['wpdb'] = new $class();
		return $GLOBALS['wpdb'];
	}
}

if ( ! function_exists( 'f8_load_migration' ) ) {
	/**
	 * Carga Migración como su arranque (sin WordPress real): constantes, la autocarga de `Effects`, `Prime_Slider` y
	 * `Platform` (la misma regla que Plugin::autoload()) y las clases de primer nivel indicadas (por defecto, las de A:
	 * traductor, contexto, informe, nativa, migración, retirada, alias y runner). Las de otros frentes se cargan solas
	 * por la autocarga, o con require en cada prueba.
	 */
	function f8_load_migration( array $files = array( 'translator', 'context', 'report', 'native', 'migration', 'retire', 'aliases', 'runner' ) ) {
		$root = dirname( __DIR__, 2 ) . '/digitalisimo-migration/';
		if ( ! defined( 'DIGITALISIMO_MIGRATION_DIR' ) ) {
			define( 'DIGITALISIMO_MIGRATION_DIR', $root );
		}
		if ( ! defined( 'DIGITALISIMO_MIGRATION_FILE' ) ) {
			define( 'DIGITALISIMO_MIGRATION_FILE', $root . 'digitalisimo-migration.php' );
		}
		if ( ! defined( 'DIGITALISIMO_MIGRATION_VERSION' ) ) {
			define( 'DIGITALISIMO_MIGRATION_VERSION', '1.0.21' );
		}
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			define( 'DIGITALISIMO_ELEMENTS_FILE', WP_PLUGIN_DIR . '/digitalisimo-elements/pro-elements.php' );
		}
		static $registered = false;
		if ( ! $registered ) {
			$registered = true;
			spl_autoload_register( static function ( $class ) use ( $root ) {
				$prefix = 'Digitalisimo\\Elements\\Legacy\\';
				if ( 0 !== strpos( (string) $class, $prefix ) ) {
					return;
				}
				$parts = explode( '\\', substr( (string) $class, strlen( $prefix ) ) );
				$dirs  = array( 'Effects' => 'effects', 'Prime_Slider' => 'prime-slider', 'Platform' => 'platform' );
				if ( 2 !== count( $parts ) || ! isset( $dirs[ $parts[0] ] ) || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $parts[1] ) ) {
					return;
				}
				$file = $root . 'legacy/' . $dirs[ $parts[0] ] . '/class-' . str_replace( '_', '-', strtolower( $parts[1] ) ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			} );
		}
		foreach ( $files as $file ) {
			require_once $root . 'legacy/class-' . $file . '.php';
		}
	}
}

if ( ! isset( $GLOBALS['wp'] ) ) {
	f8_reset();
}
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	f8_wpdb();
}
