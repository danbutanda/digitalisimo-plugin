<?php
/**
 * Candado «mismo sitio» de los plugins DIGITALÍSIMO.
 *
 * Una caché de objetos persistente (Redis, Memcached, LSMCD) compartida entre cuentas de un servidor y sin sal propia
 * por sitio (`WP_CACHE_KEY_SALT`) hace que dos WordPress lean las mismas claves: `alloptions` de un sitio aparece en el
 * otro, con su `home`, su `siteurl` y sus `active_plugins`. Leer y volver a guardar en ese estado escribe en la base de
 * este sitio datos del otro. Antes de escribir algo importante (Migrar, respaldar, restaurar, importar) se compara lo
 * que da la caché (el valor crudo de `home` y `siteurl`) con la lectura directa de la base; si no coinciden, no se
 * escribe nada y se avisa.
 *
 * Tampoco se vacía la caché global (`wp_cache_flush()`), que borraría las claves de los otros sitios que la comparten:
 * invalidate() e invalidate_site() borran sólo lo de este sitio. Única excepción: un vaciado selectivo de verdad
 * (Redis Object Cache con WP_REDIS_SELECTIVE_FLUSH y una sal o un prefijo propios), que sólo toca esta instalación.
 *
 * Copia idéntica en Backups, Migración y Tools (tests/same-site-guard.php lo comprueba): cualquiera funciona solo.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Digitalisimo_Same_Site', false ) ) {
	final class Digitalisimo_Same_Site {
		/** Grupos de la caché de objetos que guardan datos de un sitio (o de la red de esta instalación). */
		const SITE_GROUPS = array( 'options', 'site-options', 'site-transient', 'transient', 'posts', 'post_meta', 'post_parent', 'post-queries', 'terms', 'term_meta', 'term-queries', 'term_relationships', 'category_relationships', 'post_tag_relationships', 'post_format_relationships', 'nav_menu_relationships', 'comment', 'comment_meta', 'comment-queries', 'counts', 'users', 'user_meta', 'userlogins', 'useremail', 'userslugs', 'usernames', 'user-queries', 'networks', 'sites', 'site-details', 'blog-details', 'blog-id-cache', 'blog-lookup', 'blog_meta', 'site-queries', 'network-queries', 'themes', 'theme_json', 'plugins', 'bookmark', 'calendar', 'timeinfo', 'rss' );
		/** Grupos con `last_changed`: borrarlo invalida las consultas guardadas aunque el borrado por grupo no exista. */
		const QUERY_GROUPS = array( 'posts', 'terms', 'comment', 'users', 'sites', 'networks', 'site-options', 'options' );
		/**
		 * Grupos persistentes de plugins frecuentes (WooCommerce, ACF, Elementor): con borrado por grupo también se vacían
		 * (sólo existen si el plugin los usa). Otros se añaden con el filtro `digitalisimo_same_site_groups`.
		 */
		const PLUGIN_GROUPS = array( 'woocommerce', 'products', 'orders', 'order-items', 'wc_session_id', 'store_api_rate_limit', 'taxonomy_attributes', 'acf', 'elementor' );
		/** Transitorios del núcleo que viven sólo en la caché externa (sin borrado por grupo no se pueden enumerar los demás). */
		const CORE_TRANSIENTS = array( 'doing_cron', 'update_plugins', 'update_themes', 'update_core', 'plugin_slugs', 'wp_core_block_css_files', 'dirsize_cache', 'health-check-site-status-result', 'wp_theme_files_patterns' );
		/** Filas por consulta y claves por borrado múltiple al recorrer una tabla o un rango. */
		const BATCH = 1000;
		/** Segundos, por defecto, que puede tardar invalidate_site() sin borrado por grupo. */
		const BUDGET = 20;

		/** @var array|null resultado de check() en esta petición. */
		private static $last = null;

		/** Valor de una opción leído directamente de la tabla de opciones del sitio en curso (null si no existe). */
		public static function db_option( $name ) {
			global $wpdb;
			if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->options ) ) return null;
			$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", (string) $name ) );
			return null === $value ? null : (string) $value;
		}

		/** ¿Se puede leer la base directamente? (un $wpdb con tabla de opciones). */
		private static function db_ready() {
			global $wpdb;
			return isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->options ) && method_exists( $wpdb, 'get_var' ) && method_exists( $wpdb, 'prepare' );
		}

		/**
		 * Valor de una opción de este sitio leído de la base, sin la caché de objetos (deserializado), o $default si no
		 * existe. Sin caché persistente (get_option() ya sale de la base) o sin acceso directo a la base, get_option(). Para
		 * las instantáneas que se reponen después de restaurar.
		 */
		public static function db_value( $name, $default = false ) {
			if ( ! self::external() || ! self::db_ready() ) return function_exists( 'get_option' ) ? get_option( $name, $default ) : $default;
			$raw = self::db_option( $name );
			return null === $raw ? $default : self::unserial( $raw );
		}

		/** maybe_unserialize() de WordPress (o lo mismo sin él). */
		private static function unserial( $raw ) {
			if ( function_exists( 'maybe_unserialize' ) ) return maybe_unserialize( $raw );
			if ( ! is_string( $raw ) || 1 !== preg_match( '/^(?:[aOs]:\d+:|[ibd]:[^;]*;|N;)/', trim( $raw ) ) ) return $raw;
			$value = @unserialize( trim( $raw ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors -- igual que maybe_unserialize().
			return false === $value && 'b:0;' !== trim( $raw ) ? $raw : $value;
		}

		/** Lo mismo con una opción de la red (sitemeta); fuera de una red, la del sitio. */
		public static function db_site_value( $name, $default = false ) {
			global $wpdb;
			if ( ! self::external() || ! self::db_ready() ) return function_exists( 'get_site_option' ) ? get_site_option( $name, $default ) : $default;
			if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) return self::db_value( $name, $default );
			if ( empty( $wpdb->sitemeta ) ) return function_exists( 'get_site_option' ) ? get_site_option( $name, $default ) : $default;
			$network = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;
			$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d LIMIT 1", (string) $name, $network ) );
			return null === $raw ? $default : self::unserial( $raw );
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

		/**
		 * ¿Está la caché de este sitio aislada de las de otros? Sólo con sal o prefijo propio (una base de Redis distinta de
		 * 0 no basta: varios sitios pueden elegir la misma). Sólo decide el aviso de Tools: el candado compara siempre.
		 */
		public static function isolated() {
			return self::salted();
		}

		/**
		 * ¿Vacía wp_cache_flush() sólo las claves de esta instalación? Únicamente Redis Object Cache con
		 * WP_REDIS_SELECTIVE_FLUSH y una sal o un prefijo propios; los demás drop-ins vacían la base entera (FLUSHDB,
		 * flush_all), también con sal.
		 */
		public static function selective_flush() {
			return self::salted() && defined( 'WP_REDIS_SELECTIVE_FLUSH' ) && (bool) WP_REDIS_SELECTIVE_FLUSH;
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
		 * 'cached' => …, 'db' => …, 'message' => texto para el informe ). Sin caché persistente no hay nada que comparar (lo
		 * de la petición sale de la base) y no consulta nada. Con ella compara el valor crudo de la caché con la lectura
		 * directa de la tabla de opciones. $force repite la comprobación aunque ya se hiciera en esta petición (por defecto: la caché puede cambiar
		 * entre dos etapas); check( false ) la reutiliza (avisos y salidas en cada página).
		 */
		public static function check( $force = true ) {
			if ( ! $force && null !== self::$last ) return self::$last;
			$result = array( 'ok' => true, 'foreign' => '', 'cached' => '', 'db' => '', 'message' => '' );
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
			return 'L' . substr( (string) $check['message'], 1 ) . ', y vuelve a pulsar ' . $action . '. Si este sitio acaba de cambiar de dirección, puede ser su propia caché vieja: vacía la caché de objetos de este sitio y vuelve a intentarlo.';
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
		 * Antes de reemplazar las tablas: lo que la caché puede guardar de la base de ahora y el respaldo quizá no trae. En
		 * memoria sólo los identificadores mayores de entradas, términos, comentarios y cuentas de cada sitio; los nombres
		 * de las opciones y el login, el correo y el slug de cada cuenta van por lotes al archivo $file (una línea JSON por
		 * clave), sin tope de memoria. invalidate_site() lo recorre después del cambio. Vacío si no hace falta (sin caché
		 * externa, con borrado por grupo o con vaciado selectivo).
		 */
		public static function snapshot_site( $blog_ids = null, $file = '' ) {
			global $wpdb;
			$out = array( 'v' => 2, 'sites' => array(), 'users_max' => 0, 'file' => '' );
			if ( ! self::external() || self::supports( 'flush_group' ) || self::selective_flush() || ! self::db_ready() ) return $out;
			$handle = '' !== (string) $file ? @fopen( (string) $file, 'wb' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			$write  = function( array $line ) use ( $handle ) { if ( $handle ) fwrite( $handle, json_encode( $line ) . "\n" ); }; // phpcs:ignore WordPress.WP.AlternativeFunctions
			// Primero las cuentas, de la más nueva a la más vieja: si la invalidación se corta, la de un intruso (creada después
			// del respaldo, con el identificador mayor) ya salió de la caché. Después las opciones de cada sitio.
			$out['users_max'] = self::max_id( $wpdb->users, 'ID' );
			self::each_row( $wpdb->users, 'ID', 'ID, user_login, user_email, user_nicename', function( $row ) use ( $write ) { $write( array( 'u', (string) $row->user_login, (string) $row->user_email, (string) $row->user_nicename ) ); }, true );
			self::on_sites( $blog_ids, function() use ( $wpdb, &$out, $write ) {
				$blog = (int) ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 );
				$out['sites'][ $blog ] = array( 'posts' => self::max_id( $wpdb->posts, 'ID' ), 'terms' => self::max_id( $wpdb->terms, 'term_id' ), 'comments' => self::max_id( $wpdb->comments, 'comment_ID' ) );
				self::each_row( $wpdb->options, 'option_name', 'option_name', function( $row ) use ( $blog, $write ) { $write( array( 'o', $blog, (string) $row->option_name ) ); } );
			} );
			if ( function_exists( 'is_multisite' ) && is_multisite() && ! empty( $wpdb->sitemeta ) ) self::each_row( $wpdb->sitemeta, 'meta_id', 'meta_id, meta_key', function( $row ) use ( $write ) { $write( array( 'n', (string) $row->meta_key ) ); } );
			if ( $handle ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$out['file'] = (string) $file;
			}
			return $out;
		}

		/**
		 * Tras cambiar las tablas por debajo de WordPress (una restauración, una reubicación): invalida lo de este sitio.
		 *  - Con borrado por grupo (wp_cache_flush_group()): los grupos de un sitio, las relaciones de cada taxonomía, los
		 *    transitorios y los de plugins frecuentes.
		 *  - Con vaciado selectivo de verdad (selective_flush()): wp_cache_flush(), que sólo toca esta instalación.
		 *  - Si no (una caché compartida): clave por clave con el plan de plan_invalidation(), primero las claves exactas
		 *    (logins, correos y slugs de las cuentas de antes y de ahora, y las opciones), después los rangos de
		 *    identificadores de mayor a menor (lo creado después del respaldo tiene los mayores), con presupuesto de tiempo.
		 * Devuelve array( 'done' => bool, 'plan' => lo que falta ). Si no terminó, quien llama guarda `plan` y lo sigue con
		 * resume_invalidation() hasta terminar. $blog_ids: null (el sitio en curso), 'all' o una lista.
		 */
		public static function invalidate_site( $blog_ids = null, $before = null, $budget = null ) {
			if ( ! function_exists( 'wp_cache_delete' ) ) return array( 'done' => true, 'plan' => array() );
			self::flush_runtime();
			// Sin caché persistente basta la memoria de la petición (lo que hacía wp_cache_flush()).
			if ( ! self::external() ) return array( 'done' => true, 'plan' => array() );
			if ( self::supports( 'flush_group' ) && function_exists( 'wp_cache_flush_group' ) ) {
				foreach ( self::groups() as $group ) wp_cache_flush_group( $group );
				return array( 'done' => true, 'plan' => array() );
			}
			if ( self::selective_flush() && function_exists( 'wp_cache_flush' ) ) {
				// Sólo las claves con la sal o el prefijo de esta instalación.
				wp_cache_flush();
				return array( 'done' => true, 'plan' => array() );
			}
			$plan = self::plan_invalidation( $blog_ids, is_array( $before ) ? $before : array() );
			return self::resume_invalidation( $plan, $budget );
		}

		/** Plan de la invalidación clave por clave (pequeño: tareas con su cursor, sin listas de claves). */
		public static function plan_invalidation( $blog_ids, array $before ) {
			global $wpdb;
			$blogs = array();
			self::on_sites( $blog_ids, function() use ( &$blogs ) { $blogs[] = (int) ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 ); } );
			$tasks = array();
			foreach ( $blogs as $blog ) $tasks[] = array( 'do' => 'alloptions', 'blog' => $blog );
			if ( '' !== (string) ( $before['file'] ?? '' ) ) $tasks[] = array( 'do' => 'file', 'file' => (string) $before['file'], 'at' => 0 );
			$tasks[] = array( 'do' => 'users', 'after' => '' );
			foreach ( $blogs as $blog ) $tasks[] = array( 'do' => 'options', 'blog' => $blog, 'after' => '' );
			if ( function_exists( 'is_multisite' ) && is_multisite() && ! empty( $wpdb->sitemeta ) ) $tasks[] = array( 'do' => 'network', 'after' => '' );
			$tasks[] = array( 'do' => 'transients' );
			foreach ( $blogs as $blog ) $tasks[] = array( 'do' => 'counts', 'blog' => $blog );
			$tasks[] = array( 'do' => 'range', 'blog' => 0, 'groups' => array( 'users', 'user_meta' ), 'next' => max( (int) ( $before['users_max'] ?? 0 ), self::max_id( $wpdb->users, 'ID' ) ) );
			$relations = array_map( function( $taxonomy ) { return $taxonomy . '_relationships'; }, self::taxonomies() );
			foreach ( $blogs as $blog ) {
				$then = (array) ( $before['sites'][ $blog ] ?? array() );
				self::on_sites( array( $blog ), function() use ( $wpdb, $blog, $then, $relations, &$tasks ) {
					$tasks[] = array( 'do' => 'range', 'blog' => $blog, 'groups' => array_merge( array( 'posts', 'post_meta', 'post_parent' ), $relations ), 'next' => max( (int) ( $then['posts'] ?? 0 ), self::max_id( $wpdb->posts, 'ID' ) ) );
					$tasks[] = array( 'do' => 'range', 'blog' => $blog, 'groups' => array( 'terms', 'term_meta' ), 'next' => max( (int) ( $then['terms'] ?? 0 ), self::max_id( $wpdb->terms, 'term_id' ) ) );
					$tasks[] = array( 'do' => 'range', 'blog' => $blog, 'groups' => array( 'comment', 'comment_meta' ), 'next' => max( (int) ( $then['comments'] ?? 0 ), self::max_id( $wpdb->comments, 'comment_ID' ) ) );
				} );
			}
			return array( 'v' => 1, 'tasks' => $tasks );
		}

		/**
		 * Sigue un plan de plan_invalidation() hasta agotar $budget segundos. Devuelve array( 'done' => bool, 'plan' => lo
		 * que falta, con sus cursores ). Al terminar borra el archivo de la instantánea.
		 */
		public static function resume_invalidation( array $plan, $budget = null ) {
			global $wpdb;
			$deadline = microtime( true ) + ( null === $budget ? self::BUDGET : (float) $budget );
			$tasks    = array_values( (array) ( $plan['tasks'] ?? array() ) );
			while ( $tasks ) {
				$task = $tasks[0];
				$done = true;
				$run  = function() use ( &$task, &$done, $deadline, $wpdb ) {
					switch ( (string) ( $task['do'] ?? '' ) ) {
						case 'alloptions':
							wp_cache_delete( 'alloptions', 'options' );
							wp_cache_delete( 'notoptions', 'options' );
							foreach ( array_merge( self::QUERY_GROUPS, array( 'counts' ) ) as $group ) wp_cache_delete( 'last_changed', $group );
							break;
						case 'file':
							$done = self::run_file( $task, $deadline );
							break;
						case 'users':
							$done = self::run_rows( $task, $deadline, $wpdb->users, 'ID', 'ID, user_login, user_email, user_nicename', function( $row ) {
								return array( array( (string) $row->user_login, 'userlogins' ), array( (string) $row->user_email, 'useremail' ), array( (string) $row->user_nicename, 'userslugs' ) );
							} );
							break;
						case 'options':
							$done = self::run_rows( $task, $deadline, $wpdb->options, 'option_name', 'option_name', function( $row ) {
								return array( array( (string) $row->option_name, 'options' ) );
							} );
							break;
						case 'network':
							$network = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;
							wp_cache_delete( $network . ':notoptions', 'site-options' );
							$done = self::run_rows( $task, $deadline, $wpdb->sitemeta, 'meta_id', 'meta_id, meta_key', function( $row ) use ( $network ) {
								return array( array( $network . ':' . (string) $row->meta_key, 'site-options' ) );
							} );
							if ( $done && ! empty( $wpdb->blogs ) ) {
								// Cada sitio por su número y por su dirección (get_blog_details() e get_blog_id_from_url() buscan por
								// md5( dominio . ruta )).
								self::each_row( $wpdb->blogs, 'blog_id', 'blog_id, domain, path', function( $row ) {
									$id = (int) $row->blog_id;
									foreach ( array( 'sites', 'site-details', 'blog-details' ) as $group ) wp_cache_delete( $id, $group );
									wp_cache_delete( $id . 'short', 'blog-details' );
									$where = md5( (string) $row->domain . (string) $row->path );
									wp_cache_delete( $where, 'blog-lookup' );
									wp_cache_delete( $where, 'blog-id-cache' );
								} );
							}
							break;
						case 'counts':
							// Los recuentos de wp_count_posts(), wp_count_comments() y wp_count_attachments() (grupo `counts`, sin
							// last_changed): uno por tipo de contenido de este sitio.
							$types = method_exists( $wpdb, 'get_col' ) && ! empty( $wpdb->posts ) ? (array) $wpdb->get_col( "SELECT DISTINCT post_type FROM {$wpdb->posts}" ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla interna.
							foreach ( array_unique( array_merge( array( 'post', 'page', 'attachment' ), array_map( 'strval', $types ) ) ) as $type ) {
								wp_cache_delete( 'posts-' . $type, 'counts' );
								wp_cache_delete( 'posts-' . $type . '_readable', 'counts' );
							}
							foreach ( array( 'comments-0', 'attachments' ) as $key ) wp_cache_delete( $key, 'counts' );
							break;
						case 'transients':
							foreach ( self::CORE_TRANSIENTS as $name ) { wp_cache_delete( $name, 'transient' ); wp_cache_delete( $name, 'site-transient' ); wp_cache_delete( $name, 'options' ); }
							break;
						case 'range':
							// De mayor a menor: lo creado después del respaldo (una cuenta de un intruso) sale primero.
							while ( (int) $task['next'] >= 1 ) {
								if ( microtime( true ) > $deadline ) { $done = false; break; }
								$from = max( 1, (int) $task['next'] - self::BATCH + 1 );
								self::delete_now( range( (int) $task['next'], $from ), (array) $task['groups'] );
								$task['next'] = $from - 1;
							}
							break;
					}
				};
				$blog = (int) ( $task['blog'] ?? 0 );
				if ( $blog > 0 ) self::on_sites( array( $blog ), $run );
				else $run();
				if ( ! $done ) {
					$tasks[0] = $task;
					return array( 'done' => false, 'plan' => array( 'v' => 1, 'tasks' => $tasks ) );
				}
				array_shift( $tasks );
				if ( $tasks && microtime( true ) > $deadline ) return array( 'done' => false, 'plan' => array( 'v' => 1, 'tasks' => $tasks ) );
			}
			return array( 'done' => true, 'plan' => array() );
		}

		/**
		 * Junta dos planes (el que quedaba pendiente y uno nuevo) sin perder nada: primero todas las tareas de claves exactas
		 * de los dos, en su orden, y después todos los rangos.
		 */
		public static function merge_plans( array $first, array $second ) {
			$tasks = array_merge( array_values( (array) ( $first['tasks'] ?? array() ) ), array_values( (array) ( $second['tasks'] ?? array() ) ) );
			$exact = array_values( array_filter( $tasks, function( $task ) { return 'range' !== ( $task['do'] ?? '' ); } ) );
			$range = array_values( array_filter( $tasks, function( $task ) { return 'range' === ( $task['do'] ?? '' ); } ) );
			return array( 'v' => 1, 'tasks' => array_merge( $exact, $range ) );
		}

		/** Recorre el archivo de la instantánea desde su cursor (`at`, en bytes) y borra sus claves exactas. */
		private static function run_file( array &$task, $deadline ) {
			$file = (string) $task['file'];
			$handle = is_file( $file ) ? @fopen( $file, 'rb' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			if ( ! $handle ) return true;
			fseek( $handle, (int) $task['at'] );
			$network = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;
			$done = true;
			while ( true ) {
				if ( microtime( true ) > $deadline ) { $done = false; break; }
				$batch = array();
				for ( $i = 0; $i < self::BATCH && false !== ( $line = fgets( $handle ) ); $i++ ) $batch[] = json_decode( $line, true );
				$task['at'] = ftell( $handle );
				if ( ! $batch ) break;
				$by = array();
				foreach ( $batch as $row ) {
					if ( ! is_array( $row ) ) continue;
					if ( 'u' === ( $row[0] ?? '' ) ) { $by['0|userlogins'][] = (string) $row[1]; $by['0|useremail'][] = (string) $row[2]; $by['0|userslugs'][] = (string) $row[3]; }
					elseif ( 'o' === ( $row[0] ?? '' ) ) $by[ (int) $row[1] . '|options' ][] = (string) $row[2];
					elseif ( 'n' === ( $row[0] ?? '' ) ) $by['0|site-options'][] = $network . ':' . (string) $row[1];
				}
				foreach ( $by as $where => $keys ) {
					list( $blog, $group ) = explode( '|', $where, 2 );
					$delete = function() use ( $keys, $group ) { self::delete_now( array_values( array_unique( array_filter( $keys, 'strlen' ) ) ), array( $group ) ); };
					if ( (int) $blog > 0 ) self::on_sites( array( (int) $blog ), $delete );
					else $delete();
				}
			}
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( $done ) @unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			return $done;
		}

		/** Recorre una tabla por lotes desde el cursor de la tarea (`after`) y borra las claves que da $keys( $row ). */
		private static function run_rows( array &$task, $deadline, $table, $key, $columns, callable $keys ) {
			global $wpdb;
			if ( '' === (string) $table || ! method_exists( $wpdb, 'get_results' ) ) return true;
			while ( true ) {
				if ( microtime( true ) > $deadline ) return false;
				$after = (string) $task['after'];
				$where = '' === $after || ! method_exists( $wpdb, 'prepare' ) ? '' : $wpdb->prepare( " WHERE `{$key}` > %s", $after );
				$rows  = $wpdb->get_results( "SELECT {$columns} FROM {$table}{$where} ORDER BY `{$key}` LIMIT " . self::BATCH ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla y columnas internas.
				$rows  = is_array( $rows ) ? $rows : array();
				$by    = array();
				foreach ( $rows as $row ) {
					$row = (object) $row;
					foreach ( $keys( $row ) as $pair ) $by[ $pair[1] ][] = $pair[0];
					$task['after'] = (string) $row->$key;
				}
				foreach ( $by as $group => $list ) self::delete_now( array_values( array_unique( array_filter( $list, 'strlen' ) ) ), array( $group ) );
				if ( count( $rows ) < self::BATCH ) return true;
			}
		}

		/** Borra $keys en cada grupo (wp_cache_delete_multiple() si existe). */
		private static function delete_now( array $keys, array $groups ) {
			foreach ( $groups as $group ) {
				if ( function_exists( 'wp_cache_delete_multiple' ) ) wp_cache_delete_multiple( $keys, $group );
				else foreach ( $keys as $one ) wp_cache_delete( $one, $group );
			}
		}

		/**
		 * Lo que una restauración reescribe por SQL en unas cuentas: su objeto, sus metas y sus búsquedas por login, correo
		 * y slug, de antes y de ahora (lo que diga la base de cada ID). Con cualquier caché persistente.
		 */
		public static function forget_users( array $ids, array $names = array() ) {
			global $wpdb;
			if ( ! function_exists( 'wp_cache_delete' ) ) return;
			$ids   = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
			$by    = array( 'userlogins' => array(), 'useremail' => array(), 'userslugs' => array() );
			foreach ( $names as $name ) foreach ( array_keys( $by ) as $group ) $by[ $group ][] = (string) $name;
			foreach ( $ids as $id ) {
				wp_cache_delete( $id, 'users' );
				wp_cache_delete( $id, 'user_meta' );
				if ( isset( $wpdb ) && is_object( $wpdb ) && ! empty( $wpdb->users ) && method_exists( $wpdb, 'get_row' ) && method_exists( $wpdb, 'prepare' ) ) {
					$row = $wpdb->get_row( $wpdb->prepare( "SELECT user_login, user_email, user_nicename FROM {$wpdb->users} WHERE ID = %d", $id ) );
					if ( $row ) {
						$row = (object) $row;
						$by['userlogins'][] = (string) ( $row->user_login ?? '' );
						$by['useremail'][]  = (string) ( $row->user_email ?? '' );
						$by['userslugs'][]  = (string) ( $row->user_nicename ?? '' );
					}
				}
			}
			foreach ( $by as $group => $keys ) foreach ( array_unique( array_filter( $keys, 'strlen' ) ) as $key ) wp_cache_delete( $key, $group );
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

		/** El identificador mayor de una tabla (0 si está vacía o no se puede leer). */
		private static function max_id( $table, $column ) {
			global $wpdb;
			if ( '' === (string) $table || ! method_exists( $wpdb, 'get_var' ) ) return 0;
			return (int) $wpdb->get_var( "SELECT MAX(`{$column}`) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla y columna internas.
		}

		/** Todos los valores de una columna clave, por lotes ordenados (sin tope). */
		private static function keys( $table, $column ) {
			$out = array();
			self::each_row( $table, $column, $column, function( $row ) use ( $column, &$out ) { $out[] = (string) $row->$column; } );
			return $out;
		}

		/** Recorre una tabla por lotes de su clave ($key, ordenada), con las columnas $columns. */
		private static function each_row( $table, $key, $columns, callable $callback, $desc = false ) {
			global $wpdb;
			if ( '' === (string) $table || ! method_exists( $wpdb, 'get_results' ) ) return;
			$after = null;
			$op    = $desc ? '<' : '>';
			$order = $desc ? ' DESC' : '';
			do {
				$where = null === $after ? '' : ( method_exists( $wpdb, 'prepare' ) ? $wpdb->prepare( " WHERE `{$key}` {$op} %s", $after ) : '' );
				if ( null !== $after && '' === $where ) return;
				$rows = $wpdb->get_results( "SELECT {$columns} FROM {$table}{$where} ORDER BY `{$key}`{$order} LIMIT " . self::BATCH ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla y columnas internas.
				$rows = is_array( $rows ) ? $rows : array();
				foreach ( $rows as $row ) {
					$row = (object) $row;
					$callback( $row );
					$after = (string) $row->$key;
				}
			} while ( count( $rows ) === self::BATCH );
		}

		/** Ejecuta $callback en el sitio en curso o en los indicados ('all': todos los de la red). */
		private static function on_sites( $blog_ids, callable $callback ) {
			if ( ! function_exists( 'is_multisite' ) || ! is_multisite() || null === $blog_ids || ! function_exists( 'switch_to_blog' ) ) {
				$callback();
				return;
			}
			if ( 'all' === $blog_ids ) {
				global $wpdb;
				$blog_ids = empty( $wpdb->blogs ) ? array( get_current_blog_id() ) : self::keys( $wpdb->blogs, 'blog_id' );
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
