<?php
/**
 * Candado «mismo sitio» de los plugins DIGITALÍSIMO.
 *
 * Una caché de objetos persistente (Redis, Memcached, LSMCD) compartida entre cuentas de un servidor y sin sal propia
 * por sitio (`WP_CACHE_KEY_SALT`) hace que dos WordPress lean las mismas claves: `alloptions` de un sitio aparece en el
 * otro, con su `home`, su `siteurl` y sus `active_plugins`. Leer y volver a guardar en ese estado escribe en la base de
 * este sitio datos del otro. Antes de escribir algo importante (Migrar, respaldar, restaurar, importar) se compara lo
 * que da la caché con la lectura directa de la base y, en una petición web, con el host pedido; si no coinciden, no se
 * escribe nada y se avisa.
 *
 * Tampoco se vacía nunca la caché global (`wp_cache_flush()`): borraría las claves de los otros sitios que la comparten.
 * invalidate() e invalidate_site() borran sólo lo de este sitio.
 *
 * Copia idéntica en Backups, Migración y Tools (tests/same-site-guard.php lo comprueba): cualquiera funciona solo.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Digitalisimo_Same_Site', false ) ) {
	final class Digitalisimo_Same_Site {
		/** Grupos de la caché de objetos que guardan datos de un sitio (o de la red de esta instalación). */
		const SITE_GROUPS = array( 'options', 'site-options', 'site-transient', 'transient', 'posts', 'post_meta', 'post_parent', 'post-queries', 'terms', 'term_meta', 'term-queries', 'term_relationships', 'category_relationships', 'post_tag_relationships', 'post_format_relationships', 'nav_menu_relationships', 'comment', 'comment_meta', 'comment-queries', 'counts', 'users', 'user_meta', 'userlogins', 'useremail', 'userslugs', 'usernames', 'user-queries', 'networks', 'sites', 'site-details', 'blog-details', 'blog-id-cache', 'blog-lookup', 'blog_meta', 'site-queries', 'network-queries', 'themes', 'theme_json', 'plugins', 'bookmark', 'calendar', 'timeinfo', 'rss' );
		/** Grupos con `last_changed`: subirlo invalida las consultas guardadas aunque el borrado por grupo no exista. */
		const QUERY_GROUPS = array( 'posts', 'terms', 'comment', 'users', 'sites', 'networks', 'site-options', 'options' );
		/**
		 * Grupos persistentes de plugins frecuentes (WooCommerce, ACF, Elementor): con borrado por grupo también se vacían
		 * (sólo existen si el plugin los usa). Otros se añaden con el filtro `digitalisimo_same_site_groups`.
		 */
		const PLUGIN_GROUPS = array( 'woocommerce', 'products', 'orders', 'order-items', 'wc_session_id', 'store_api_rate_limit', 'taxonomy_attributes', 'acf', 'elementor' );
		/** Tope de filas que el respaldo sin borrado por grupo enumera por tabla. */
		const ENUMERATE_MAX = 200000;
		/**
		 * Sin borrado por grupo, los objetos que existían antes de restaurar y el respaldo no trae no están en la base para
		 * enumerarlos: se borran también los identificadores siguientes al mayor restaurado (una cuenta, entrada o
		 * comentario creados después del respaldo tienen identificadores mayores).
		 */
		const ID_TAIL = 20000;

		/** @var array|null resultado de check() en esta petición. */
		private static $last = null;

		/** Valor de una opción leído directamente de la tabla de opciones del sitio en curso (null si no existe). */
		public static function db_option( $name ) {
			global $wpdb;
			if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->options ) ) return null;
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", (string) $name ) );
			return null === $value ? null : (string) $value;
		}

		/** ¿Hay una caché de objetos externa (persistente)? */
		public static function external() {
			return function_exists( 'wp_using_ext_object_cache' ) && (bool) wp_using_ext_object_cache();
		}

		/** ¿Tiene esta instalación una sal o un prefijo propio para la caché de objetos? */
		public static function salted() {
			foreach ( array( 'WP_CACHE_KEY_SALT', 'WP_REDIS_PREFIX' ) as $constant ) {
				if ( defined( $constant ) && '' !== trim( (string) constant( $constant ) ) ) return true;
			}
			if ( defined( 'WP_REDIS_CONFIG' ) && is_array( WP_REDIS_CONFIG ) && '' !== trim( (string) ( WP_REDIS_CONFIG['prefix'] ?? '' ) ) ) return true;
			return false;
		}

		/** Host normalizado de una URL o de un host (sin esquema, puerto, `www.` ni mayúsculas). */
		public static function host( $url ) {
			$url  = trim( (string) $url );
			if ( '' === $url ) return '';
			$host = false === strpos( $url, '//' ) ? $url : (string) parse_url( $url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- sin dependencias.
			$host = strtolower( preg_replace( '/:\d+$/', '', trim( $host, "[] \t\n\r\0\x0B" ) ) );
			return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
		}

		/** URL comparable: host normalizado más la ruta sin barra final. */
		private static function comparable( $url ) {
			$url = trim( (string) $url );
			if ( '' === $url ) return '';
			$path = (string) parse_url( $url, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			return self::host( $url ) . rtrim( $path, '/' );
		}

		/**
		 * El valor crudo (sin los filtros `option_home`/`option_siteurl`, ni WP_HOME en una red, ni los de un plugin de
		 * dominios) que la caché de objetos tiene para $name: `alloptions` o su clave propia leídos de la caché compartida
		 * ($force) y, si ésta no responde así, la copia que esta petición cargó (wp_load_alloptions()). null si no lo tiene.
		 */
		private static function cached_raw( $name ) {
			if ( function_exists( 'wp_cache_get' ) ) {
				$all = wp_cache_get( 'alloptions', 'options', true );
				if ( is_array( $all ) && array_key_exists( $name, $all ) && is_scalar( $all[ $name ] ) ) return (string) $all[ $name ];
				$one = wp_cache_get( $name, 'options', true );
				if ( false !== $one && null !== $one && is_scalar( $one ) ) return (string) $one;
			}
			if ( function_exists( 'wp_load_alloptions' ) ) {
				$all = wp_load_alloptions();
				if ( is_array( $all ) && array_key_exists( $name, $all ) && is_scalar( $all[ $name ] ) ) return (string) $all[ $name ];
			}
			return null;
		}

		/**
		 * Comprueba que este WordPress lee sus propios datos. Devuelve array( 'ok' => bool, 'foreign' => host ajeno o '',
		 * 'cached' => …, 'db' => …, 'request' => host pedido, 'message' => texto para el informe ). Sin caché persistente no
		 * hay nada que comparar (lo de la petición sale de la base) y no consulta nada. Con ella compara el valor crudo de la
		 * caché con la lectura directa de la tabla de opciones y, en una petición web cuyo host es el de la base, con ese
		 * host. $force repite la comprobación aunque ya se hiciera en esta petición (por defecto: la caché puede cambiar
		 * entre dos etapas); check( false ) la reutiliza (avisos y salidas en cada página).
		 */
		public static function check( $force = true ) {
			if ( ! $force && null !== self::$last ) return self::$last;
			$result = array( 'ok' => true, 'foreign' => '', 'cached' => '', 'db' => '', 'request' => '', 'message' => '' );
			if ( ! self::external() ) {
				self::$last = $result;
				return $result;
			}
			$db       = array( 'home' => self::db_option( 'home' ), 'siteurl' => self::db_option( 'siteurl' ) );
			$cached   = array( 'home' => self::cached_raw( 'home' ), 'siteurl' => self::cached_raw( 'siteurl' ) );
			$foreign  = '';
			$result['db']     = (string) $db['home'];
			$result['cached'] = (string) $cached['home'];
			foreach ( array( 'home', 'siteurl' ) as $name ) {
				if ( null !== $db[ $name ] && null !== $cached[ $name ] && self::comparable( $cached[ $name ] ) !== self::comparable( $db[ $name ] ) ) { $foreign = $cached[ $name ]; break; }
			}
			// En una petición web: el host pedido es el de la base, pero la caché da otro (la petición es de este sitio).
			$request = self::request_host();
			$result['request'] = $request;
			if ( '' === $foreign && '' !== $request && null !== $db['home'] && null !== $cached['home'] ) {
				$mine = array_filter( array( self::host( $db['home'] ), self::host( (string) $db['siteurl'] ) ) );
				foreach ( array( 'WP_HOME', 'WP_SITEURL' ) as $constant ) if ( defined( $constant ) ) $mine[] = self::host( (string) constant( $constant ) );
				if ( in_array( $request, $mine, true ) && ! in_array( self::host( $cached['home'] ), $mine, true ) ) $foreign = $cached['home'];
			}
			if ( '' !== $foreign ) {
				$result['ok']      = false;
				$result['foreign'] = self::host( $foreign );
				$result['message'] = sprintf( 'la caché de objetos de este servidor está devolviendo datos de otro sitio (%s); define WP_CACHE_KEY_SALT por sitio o usa una base de caché distinta', '' !== $result['foreign'] ? $result['foreign'] : $foreign );
			}
			$result = function_exists( 'apply_filters' ) ? (array) apply_filters( 'digitalisimo_same_site_check', $result ) : $result;
			self::$last = $result;
			return $result;
		}

		/** ¿Lee este WordPress sus propios datos? (atajo de check()). */
		public static function ok() {
			$check = self::check();
			return ! empty( $check['ok'] );
		}

		/**
		 * Mensaje para quien pulsó $action («Migrar», «Respaldar»…), o '' si el sitio lee sus datos: «La caché de objetos
		 * de este servidor está devolviendo datos de otro sitio (X); define WP_CACHE_KEY_SALT por sitio o usa una base de
		 * caché distinta, y vuelve a pulsar Migrar.». Quien llama añade lo que hizo (o no) antes de detenerse.
		 */
		public static function refusal( $action ) {
			$check = self::check();
			if ( ! empty( $check['ok'] ) ) return '';
			return 'L' . substr( (string) $check['message'], 1 ) . ', y vuelve a pulsar ' . $action . '.';
		}

		/** Host de la petición web en curso ('' en WP-CLI, sin petición o en el cron del sistema). */
		public static function request_host() {
			if ( 'cli' === PHP_SAPI || ( defined( 'WP_CLI' ) && WP_CLI ) ) return '';
			$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sólo se compara.
			return preg_match( '/^[A-Za-z0-9.\-:\[\]]{1,255}$/', $host ) ? self::host( $host ) : '';
		}

		/** ¿El host de $url es el de este sitio según la base? (para no lanzar peticiones a otro sitio). */
		public static function own_url( $url ) {
			$host = self::host( $url );
			if ( '' === $host ) return false;
			$mine = array( self::host( (string) self::db_option( 'home' ) ), self::host( (string) self::db_option( 'siteurl' ) ) );
			foreach ( array( 'WP_HOME', 'WP_SITEURL' ) as $constant ) if ( defined( $constant ) ) $mine[] = self::host( (string) constant( $constant ) );
			return in_array( $host, array_filter( $mine ), true );
		}

		/* ------------------------------------------------------------------ Invalidación sin vaciar la caché global */

		/** ¿Admite la caché la operación $feature (`flush_group`, `flush_runtime`)? Sin wp_cache_supports(), no. */
		private static function supports( $feature ) {
			return function_exists( 'wp_cache_supports' ) && wp_cache_supports( $feature );
		}

		/** Vacía la memoria de esta petición (nunca la caché compartida). */
		public static function flush_runtime() {
			if ( self::supports( 'flush_runtime' ) && function_exists( 'wp_cache_flush_runtime' ) ) return (bool) wp_cache_flush_runtime();
			if ( ! self::external() && isset( $GLOBALS['wp_object_cache'] ) && is_object( $GLOBALS['wp_object_cache'] ) && method_exists( $GLOBALS['wp_object_cache'], 'flush' ) ) return (bool) $GLOBALS['wp_object_cache']->flush();
			return false;
		}

		/**
		 * Borra de la caché sólo las opciones nombradas de este sitio ($options) y de su red ($network_options), además de
		 * `alloptions` y `notoptions` del sitio. $blog_ids: null (el sitio en curso) o los sitios de la red donde hacerlo.
		 */
		public static function invalidate( array $options = array(), array $network_options = array(), $blog_ids = null ) {
			if ( ! function_exists( 'wp_cache_delete' ) ) return;
			$each = function() use ( $options ) {
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				foreach ( $options as $name ) wp_cache_delete( (string) $name, 'options' );
			};
			self::on_sites( $blog_ids, $each );
			if ( $network_options ) {
				$network = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;
				wp_cache_delete( $network . ':notoptions', 'site-options' );
				foreach ( $network_options as $name ) {
					wp_cache_delete( $network . ':' . $name, 'site-options' );
					wp_cache_delete( (string) $name, 'site-options' );
				}
			}
			self::flush_runtime();
		}

		/**
		 * Tras cambiar las tablas por debajo de WordPress (una restauración, una reubicación): invalida lo de este sitio sin
		 * vaciar la caché global. Con borrado por grupo (wp_cache_flush_group()), los grupos de un sitio, las relaciones de
		 * cada taxonomía y los de plugins frecuentes (con una sal propia, sólo los de esta instalación; sin ella, esos
		 * grupos también de los demás sitios, que los vuelven a leer de su base: nunca se mezclan datos). Si no, cada
		 * opción, entrada, término, comentario y usuario que la base tiene ahora, los ID_TAIL identificadores siguientes al
		 * mayor y `last_changed`. Límite declarado sin borrado por grupo: una opción no autocargada creada después del
		 * respaldo puede seguir en la caché hasta que caduque. $blog_ids: null (el sitio en curso), 'all' (todos los sitios
		 * de la red) o una lista.
		 */
		public static function invalidate_site( $blog_ids = null ) {
			if ( ! function_exists( 'wp_cache_delete' ) ) return;
			self::flush_runtime();
			// Sin caché persistente basta la memoria de la petición (lo que hacía wp_cache_flush()).
			if ( ! self::external() ) return;
			if ( self::supports( 'flush_group' ) && function_exists( 'wp_cache_flush_group' ) ) {
				foreach ( self::groups() as $group ) wp_cache_flush_group( $group );
				return;
			}
			global $wpdb;
			$users = false;
			self::on_sites( $blog_ids, function() use ( $wpdb, &$users ) {
				wp_cache_delete( 'alloptions', 'options' );
				wp_cache_delete( 'notoptions', 'options' );
				foreach ( self::column( "SELECT option_name FROM {$wpdb->options}" ) as $name ) wp_cache_delete( $name, 'options' );
				$relations = array_map( function( $taxonomy ) { return $taxonomy . '_relationships'; }, self::taxonomies() );
				foreach ( self::ids( "SELECT ID FROM {$wpdb->posts}" ) as $id ) {
					foreach ( array( 'posts', 'post_meta', 'post_parent' ) as $group ) wp_cache_delete( $id, $group );
					foreach ( $relations as $group ) wp_cache_delete( $id, $group );
				}
				foreach ( self::ids( "SELECT term_id FROM {$wpdb->terms}" ) as $id ) { wp_cache_delete( $id, 'terms' ); wp_cache_delete( $id, 'term_meta' ); }
				foreach ( self::ids( "SELECT comment_ID FROM {$wpdb->comments}" ) as $id ) { wp_cache_delete( $id, 'comment' ); wp_cache_delete( $id, 'comment_meta' ); }
				foreach ( self::QUERY_GROUPS as $group ) wp_cache_delete( 'last_changed', $group );
				if ( ! $users ) {
					$users = true;
					$top = 0;
					foreach ( $wpdb->get_results( "SELECT ID, user_login, user_email, user_nicename FROM {$wpdb->users} LIMIT " . (int) self::ENUMERATE_MAX ) ?: array() as $row ) {
						$top = max( $top, (int) $row->ID );
						wp_cache_delete( (int) $row->ID, 'users' );
						wp_cache_delete( (int) $row->ID, 'user_meta' );
						wp_cache_delete( (string) $row->user_login, 'userlogins' );
						wp_cache_delete( (string) $row->user_email, 'useremail' );
						wp_cache_delete( (string) $row->user_nicename, 'userslugs' );
					}
					// Una cuenta creada después del respaldo (p. ej. la de un intruso): sin su objeto ni sus metas en la caché,
					// su búsqueda por nombre llega a la base, que ya no la tiene.
					for ( $id = $top + 1; $id <= $top + self::ID_TAIL; $id++ ) { wp_cache_delete( $id, 'users' ); wp_cache_delete( $id, 'user_meta' ); }
				}
			} );
			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				$network = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;
				wp_cache_delete( $network . ':notoptions', 'site-options' );
				if ( ! empty( $wpdb->sitemeta ) ) foreach ( self::column( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE site_id = %d", $network ) ) as $name ) wp_cache_delete( $network . ':' . $name, 'site-options' );
				if ( ! empty( $wpdb->blogs ) ) foreach ( self::column( "SELECT blog_id FROM {$wpdb->blogs}" ) as $id ) { wp_cache_delete( $id, 'sites' ); wp_cache_delete( $id, 'site-details' ); wp_cache_delete( $id, 'blog-details' ); wp_cache_delete( $id . 'short', 'blog-details' ); }
			}
		}

		/** Grupos que invalida el borrado por grupo: los de un sitio, las relaciones de cada taxonomía y los de plugins. */
		private static function groups() {
			$groups = array_merge( self::SITE_GROUPS, self::PLUGIN_GROUPS, array_map( function( $taxonomy ) { return $taxonomy . '_relationships'; }, self::taxonomies() ) );
			$groups = function_exists( 'apply_filters' ) ? (array) apply_filters( 'digitalisimo_same_site_groups', $groups ) : $groups;
			return array_values( array_unique( array_map( 'strval', $groups ) ) );
		}

		/** Taxonomías registradas (más las del núcleo). */
		private static function taxonomies() {
			$names = function_exists( 'get_taxonomies' ) ? (array) get_taxonomies() : array();
			return array_values( array_unique( array_merge( array( 'category', 'post_tag', 'post_format', 'nav_menu', 'link_category' ), array_map( 'strval', $names ) ) ) );
		}

		/** Identificadores de una consulta y los ID_TAIL siguientes al mayor (lo creado después del respaldo). */
		private static function ids( $sql ) {
			$ids = array_map( 'intval', self::column( $sql ) );
			$top = $ids ? max( $ids ) : 0;
			return array_merge( $ids, range( $top + 1, $top + self::ID_TAIL ) );
		}

		/** Una columna de una consulta (como cadenas), con tope; un error da array(). */
		private static function column( $sql ) {
			global $wpdb;
			$values = $wpdb->get_col( $sql . ' LIMIT ' . (int) self::ENUMERATE_MAX ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- consultas internas sin datos de fuera.
			return is_array( $values ) ? array_map( 'strval', $values ) : array();
		}

		/** Ejecuta $callback en el sitio en curso o en los indicados ('all': todos los de la red). */
		private static function on_sites( $blog_ids, callable $callback ) {
			if ( ! function_exists( 'is_multisite' ) || ! is_multisite() || null === $blog_ids || ! function_exists( 'switch_to_blog' ) ) {
				$callback();
				return;
			}
			if ( 'all' === $blog_ids ) {
				global $wpdb;
				$blog_ids = empty( $wpdb->blogs ) ? array( get_current_blog_id() ) : self::column( "SELECT blog_id FROM {$wpdb->blogs}" );
			}
			foreach ( array_unique( array_map( 'intval', (array) $blog_ids ) ) as $id ) {
				if ( $id <= 0 ) continue;
				$switch = $id !== (int) get_current_blog_id();
				if ( $switch ) switch_to_blog( $id );
				try {
					$callback();
				} finally {
					if ( $switch ) restore_current_blog();
				}
			}
		}
	}
}
