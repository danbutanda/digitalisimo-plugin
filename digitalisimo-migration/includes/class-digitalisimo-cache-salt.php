<?php
/**
 * Sal propia de la caché de objetos, puesta por el plugin (reglas del propietario: «nadie edita wp-config.php a mano»;
 * «no se trata de bloquear el config, se trata de que uno al migrar no haga nada»; «no mezcles sitios, todo debe ser
 * perfecto»).
 *
 * Con una caché de objetos persistente compartida (Redis, Memcached, LSMCD) y sin sal ni prefijo propios, dos WordPress
 * del mismo servidor leen las mismas claves (incidente de plomerito). Qué separa las claves en cada drop-in (leído en su
 * código): Redis Object Cache usa `WP_REDIS_PREFIX` y, si falta, copia `WP_CACHE_KEY_SALT`; LiteSpeed Cache mete
 * `WP_CACHE_KEY_SALT`, `DB_NAME` y `$table_prefix` en `LSOC_PREFIX`; Memcached antepone `WP_CACHE_KEY_SALT`; W3 Total
 * Cache ignora la sal y separa por host y `W3TC_INSTANCE_ID`; Object Cache Pro usa `WP_REDIS_CONFIG['prefix']` (se
 * comprueba con una clave de prueba). Por eso el bloque define `WP_CACHE_KEY_SALT` y `WP_REDIS_PREFIX` con el mismo valor
 * y, con W3TC, `W3TC_INSTANCE_ID`; cada uno sólo si no está definido.
 *
 * La sal es `<host>:<8 hex de la ruta real, la base, su servidor y el prefijo>:` (`.<n>` al final cuando hubo que
 * cambiarla), estable entre peticiones: dos peticiones a la vez escriben lo mismo. Va en wp-config.php (el que carga
 * WordPress) antes de «That's all, stop editing» o del require de wp-settings.php, siempre como código de nivel superior;
 * si wp-config.php no se puede escribir, al principio de wp-content/object-cache.php (se carga antes de iniciar la
 * caché). Antes, una copia con fecha; la escritura es atómica (temporal y rename) si el temporal sale con el mismo dueño,
 * grupo y permisos, y si no, en su sitio sin vaciarlo (sólo desde el primer byte que cambia). Se valida con
 * token_get_all( TOKEN_PARSE ) y byte a byte; si algo falla, se repone el original. Si otro proceso cambió el archivo
 * entre la lectura y la escritura, no se escribe.
 *
 * En cada carga del escritorio (y en cada petición de Migrar):
 *  - sin sal: la escribe;
 *  - con la nuestra en vigor por primera vez: mira la clave de prueba (si el drop-in no la respeta, queda «ignored» y
 *    Migrar trabaja leyendo directo de la base: bypass());
 *  - con la nuestra pero de otro sitio (un clon: ruta, base o host distintos) o con la caché dando datos ajenos: la cambia;
 *  - si la nuestra estuvo en vigor y desapareció (un plugin de caché reescribió su drop-in, alguien quitó el bloque):
 *    primero invalida las claves de este sitio en la caché sin sal (quedaron viejas: capacidades, opciones) y después la
 *    vuelve a poner con otro valor (el espacio con la sal anterior también quedó viejo).
 * Tras escribir no se toca la caché compartida: lo que se borrara ahí lo volvería a llenar esta misma petición con los
 * datos de este sitio, a la vista de los vecinos sin sal. Sólo si nada de esto es viable, el aviso de Tools da la línea.
 *
 * Copia idéntica en Tools y Migración (tests/tools-cache-salt.php lo comprueba); depende sólo del candado
 * `Digitalisimo_Same_Site`.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Digitalisimo_Cache_Salt', false ) ) {
	final class Digitalisimo_Cache_Salt {
		/** Registro (opción sin carga automática; en una red, de la red). */
		const LOG  = 'digitalisimo_tools_cache_salt';
		const MARK = '/* DIGITALÍSIMO: sal propia de la caché de objetos */';
		const END  = '/* Fin de DIGITALÍSIMO: si se quita, hay que vaciar la caché de objetos de este sitio */';
		/** Sal propia: host, 8 hex de la instalación y, si se cambió, `.<n>`. */
		const FORMAT = '/^[a-z0-9.\-]+:[a-f0-9]{8}(?:\.([0-9]+))?:$/';
		/** Clave de prueba (grupo y nombre) para saber si el drop-in respeta la sal. */
		const GROUP = 'digitalisimo-salt';
		const PROBE = 'probe';
		/** Si la sal desaparece más de estas veces en un día, se deja de reponer (otro plugin la quita cada vez). */
		const LOST_MAX = 3;
		/** Segundos para invalidar lo viejo cuando la sal desaparece (sigue en la carga siguiente). */
		const BUDGET = 8;
		/** Segundos que se muestra el aviso de éxito. */
		const SHOW = 86400;

		/** @var array|null resultado de esta petición. */
		private static $done = null;
		private static $hooked = false;
		private static $bypassed = false;

		public static function init() {
			if ( self::$hooked ) return;
			self::$hooked = true;
			add_action( 'admin_init', array( __CLASS__, 'admin' ), 1 );
		}

		/** Escritorio: para quien gestiona el sitio o la red (nunca en AJAX ni en el cron). */
		public static function admin() {
			if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) return;
			if ( ! current_user_can( is_multisite() ? 'manage_network' : 'manage_options' ) ) return;
			if ( '' === self::need() ) return;
			self::ensure( 'admin' );
		}

		/**
		 * Qué hace falta: '' (nada), 'write' (no hay sal), 'probe' (la nuestra entra en vigor: comprobarla), 'rotate' (la
		 * nuestra es de otra instalación o la caché da datos ajenos), 'lost' (estuvo en vigor y desapareció) o 'pending'
		 * (escrita y aún sin efecto).
		 */
		public static function need() {
			if ( ! class_exists( 'Digitalisimo_Same_Site', false ) || ! Digitalisimo_Same_Site::external() ) return '';
			if ( self::others() ) return '';
			$salt = self::current();
			$last = self::last();
			if ( '' !== $salt ) {
				if ( ( $last['seen'] ?? '' ) !== $salt ) return 'probe';
				if ( ( $last['ignored'] ?? '' ) === $salt ) return '';
				if ( ! self::fits( $salt ) ) return 'rotate';
				$check = Digitalisimo_Same_Site::check( false );
				return empty( $check['ok'] ) ? 'rotate' : '';
			}
			if ( '' !== (string) ( $last['seen'] ?? '' ) ) return 'lost';
			if ( in_array( $last['state'] ?? '', array( 'written', 'pending' ), true ) && '' !== self::holder() ) return 'pending';
			return 'write';
		}

		/** ¿Tiene ya la caché una sal o un prefijo que no son nuestros? */
		public static function others() {
			$salt = defined( 'WP_CACHE_KEY_SALT' ) ? trim( (string) WP_CACHE_KEY_SALT ) : '';
			if ( '' !== $salt && ! preg_match( self::FORMAT, $salt ) ) return true;
			$redis = defined( 'WP_REDIS_PREFIX' ) ? trim( (string) WP_REDIS_PREFIX ) : '';
			if ( '' !== $redis && $redis !== $salt ) return true;
			return defined( 'WP_REDIS_CONFIG' ) && is_array( WP_REDIS_CONFIG ) && '' !== trim( (string) ( WP_REDIS_CONFIG['prefix'] ?? '' ) );
		}

		/** Nuestra sal en vigor ('' si no hay). */
		public static function current() {
			$salt = defined( 'WP_CACHE_KEY_SALT' ) ? trim( (string) WP_CACHE_KEY_SALT ) : '';
			return preg_match( self::FORMAT, $salt ) ? $salt : '';
		}

		/** ¿Aísla de verdad la caché de este sitio? (sal o prefijo ajenos, o la nuestra en vigor y respetada). */
		public static function isolated() {
			if ( ! class_exists( 'Digitalisimo_Same_Site', false ) || ! Digitalisimo_Same_Site::external() || self::others() ) return true;
			$salt = self::current();
			return '' !== $salt && ( self::last()['ignored'] ?? '' ) !== $salt;
		}

		/** Resultado de esta petición o el último registrado. */
		public static function result() {
			return null !== self::$done ? self::$done : self::last();
		}

		/**
		 * Hace lo que need() pide. Devuelve el registro con `state`: 'not_needed', 'active' (en vigor y comprobada),
		 * 'ignored' (en vigor, pero el drop-in no la respeta), 'written' (escrita: vale desde la petición siguiente),
		 * 'pending', 'cleaning' (invalidando lo viejo antes de reponerla), 'manual' (no se puede: la línea para copiar) o
		 * 'failed' (la escritura no pasó la comprobación). Un intento 'manual' o 'failed' no se repite mientras los archivos
		 * sigan igual, salvo con $retry.
		 */
		public static function ensure( $via = 'admin', $retry = false ) {
			if ( null !== self::$done && ! $retry && ! in_array( self::$done['state'], array( 'manual', 'failed' ), true ) ) return self::$done;
			$need = self::need();
			$last = self::last();
			if ( '' === $need ) {
				$cur   = self::current();
				$state = '' === $cur || ! self::external_unsalted() ? 'not_needed' : ( ( $last['ignored'] ?? '' ) === $cur ? 'ignored' : 'active' );
				return self::$done = array( 'state' => $state ) + $last + array( 'message' => '', 'line' => '' );
			}
			if ( 'pending' === $need ) {
				self::nudge( (string) ( $last['file'] ?? '' ) );
				return self::$done = array( 'state' => 'pending' ) + $last;
			}
			if ( 'probe' === $need ) return self::probe( $last );
			if ( ! $retry && in_array( $last['state'] ?? '', array( 'manual', 'failed' ), true ) && ( $last['sig'] ?? '' ) === self::signature() ) return self::$done = $last;
			$out = array( 'state' => 'manual', 'message' => '', 'line' => '', 'salt' => '', 'file' => '', 'target' => '', 'backup' => '', 'via' => (string) $via, 'at' => time(), 'kind' => self::kind() );
			if ( 'lost' === $need ) {
				// Antes de nada: lo que este sitio dejó en la caché sin sal está viejo (capacidades, opciones, entradas).
				$clean = is_array( $last['cleanup'] ?? null ) && $last['cleanup'] ? Digitalisimo_Same_Site::resume_invalidation( $last['cleanup'], self::BUDGET ) : Digitalisimo_Same_Site::invalidate_site( is_multisite() ? 'all' : null, null, self::BUDGET );
				if ( empty( $clean['done'] ) ) {
					$out['state']   = 'cleaning';
					$out['cleanup'] = (array) ( $clean['plan'] ?? array() );
					return self::finish( $out, 'la sal desapareció: se invalidan las claves viejas de este sitio antes de reponerla' );
				}
				$recent         = array_values( array_filter( (array) ( $last['lost_at'] ?? array() ), function ( $t ) { return (int) $t > time() - 86400; } ) );
				$recent[]       = time();
				$out['lost_at'] = $recent;
				$out['cleanup'] = array();
				$out['seen']    = '';
				if ( count( $recent ) > self::LOST_MAX ) {
					$out['salt'] = (string) ( $last['seen'] ?? '' );
					$out['line'] = self::line( $out['salt'] );
					$out['file'] = (string) ( $last['file'] ?? '' );
					return self::finish( $out, 'la sal desaparece una y otra vez de ' . ( '' !== $out['file'] ? basename( $out['file'] ) : 'wp-config.php' ) . ' (otro plugin la quita al reescribir el archivo)' );
				}
			}
			$prev = '' !== self::current() ? self::current() : (string) ( $last['seen'] ?? ( $last['salt'] ?? '' ) );
			$gen  = in_array( $need, array( 'rotate', 'lost' ), true ) && self::fits( $prev ) ? self::generation( $prev ) + 1 : 0;
			$salt = self::salt( $gen );
			$out['salt'] = $salt;
			$out['gen']  = $gen;
			$out['line'] = self::line( $salt );
			if ( ! self::file_mods_allowed() ) return self::finish( $out, 'DISALLOW_FILE_MODS prohíbe a los plugins modificar archivos en este sitio' );
			return self::write( $out, $need );
		}

		/** Sal de esta instalación (`$gen` > 0: cambiada tantas veces). */
		public static function salt( $gen = 0 ) {
			return self::base() . ( $gen > 0 ? '.' . (int) $gen : '' ) . ':';
		}

		/** `<host>:<8 hex>` de esta instalación. */
		private static function base() {
			global $wpdb;
			$host = preg_replace( '/[^a-z0-9.\-]/', '', Digitalisimo_Same_Site::host( self::main_home() ) );
			if ( '' === $host ) $host = 'sitio';
			$root = realpath( ABSPATH );
			$id   = hash( 'sha256', implode( "\n", array( false !== $root ? $root : ABSPATH, defined( 'DB_NAME' ) ? DB_NAME : '', defined( 'DB_HOST' ) ? DB_HOST : '', is_object( $wpdb ) && isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : '' ) ) );
			return $host . ':' . substr( $id, 0, 8 );
		}

		/** ¿Es $salt la de esta instalación (cualquier generación)? */
		private static function fits( $salt ) {
			$base = self::base();
			return $base . ':' === (string) $salt || 1 === preg_match( '/^' . preg_quote( $base, '/' ) . '\.[0-9]+:$/', (string) $salt );
		}

		private static function generation( $salt ) {
			return preg_match( self::FORMAT, (string) $salt, $m ) && isset( $m[1] ) ? (int) $m[1] : 0;
		}

		/** `home` del sitio (en una red, del principal) leído de la base, nunca de la caché (podría ser la de otro sitio). */
		private static function main_home() {
			global $wpdb;
			if ( ! is_multisite() || ! function_exists( 'get_main_site_id' ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_blog_prefix' ) ) return (string) Digitalisimo_Same_Site::db_option( 'home' );
			$table = $wpdb->get_blog_prefix( (int) get_main_site_id() ) . 'options';
			return (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1", 'home' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla del núcleo.
		}

		/** Línea para copiar a mano (sólo si nada más es viable). */
		private static function line( $salt ) {
			return "defined( 'WP_CACHE_KEY_SALT' ) || define( 'WP_CACHE_KEY_SALT', '" . $salt . "' );";
		}

		/** Bloque (sin el salto final). */
		private static function block( $salt, $eol, $kind ) {
			$lines = array( self::MARK, self::line( $salt ), "defined( 'WP_REDIS_PREFIX' ) || define( 'WP_REDIS_PREFIX', '" . $salt . "' );" );
			if ( 'w3tc' === $kind ) $lines[] = "defined( 'W3TC_INSTANCE_ID' ) || define( 'W3TC_INSTANCE_ID', " . ( hexdec( substr( hash( 'sha256', $salt ), 0, 7 ) ) + 1 ) . ' );';
			$lines[] = self::END;
			return implode( $eol, $lines );
		}

		/* ------------------------------------------------------------------ Escritura */

		private static function write( array $out, $need ) {
			$config = self::config_path();
			$dropin = self::dropin_path();
			$holder = self::holder();
			$plans  = array();
			$why    = array();
			if ( '' !== $holder ) {
				// El bloque ya está (cambiar la sal, o reponer una que no entró): se reemplaza ahí mismo.
				$plans[] = array( $holder, $holder === $dropin ? 'dropin' : 'config' );
			} else {
				$plans[] = array( $config, 'config' );
				if ( '' !== $dropin ) $plans[] = array( $dropin, 'dropin' );
			}
			foreach ( $plans as $plan ) {
				list( $path, $target ) = $plan;
				$name = '' !== $path ? basename( $path ) : 'wp-config.php';
				$bad  = self::unwritable( $path, $name );
				if ( '' !== $bad ) { $why[] = $bad; continue; }
				$original = (string) @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
				if ( '' === $original ) { $why[] = 'no se pudo leer ' . $name; continue; }
				$expected = self::compose( $original, $out['salt'], $target, $out['kind'], $bad );
				if ( '' === $expected ) { $why[] = $bad; if ( 'config' === $target && 0 === strpos( $bad, 'wp-config.php ya' ) ) break; continue; }
				$out['file']   = $path;
				$out['target'] = $target;
				$backup        = self::backup( $path, $original );
				if ( '' === $backup ) { $why[] = 'no se pudo guardar antes una copia de ' . $name; continue; }
				$out['backup'] = $backup;
				if ( function_exists( 'wp_cache_set' ) ) {
					// Clave de prueba en el espacio de ahora: si en la petición siguiente sigue visible, el drop-in no respeta la sal.
					$out['probe'] = bin2hex( random_bytes( 8 ) );
					wp_cache_set( self::PROBE, $out['probe'], self::GROUP, 3600 );
				}
				$put = self::put( $path, $original, $expected );
				if ( 'ok' === $put && self::valid( $path, $expected ) ) {
					$out['state'] = 'written';
					return self::finish( $out, '' );
				}
				$out['state'] = 'failed';
				if ( 'changed' === $put ) return self::finish( $out, $name . ' cambió mientras se escribía (otro proceso lo editó): no se tocó y se reintenta en la carga siguiente' );
				$restored = self::restore( $path, $original );
				return self::finish( $out, $restored ? 'la escritura de ' . $name . ' no pasó la comprobación y se repuso el original' : 'la escritura de ' . $name . ' no pasó la comprobación y no se pudo reponer el original: está en ' . $backup );
			}
			$out['file'] = $config;
			return self::finish( $out, implode( '; ', array_unique( array_filter( $why ) ) ) );
		}

		/** Contenido nuevo de $path con el bloque ('' y $why si no hay un sitio seguro). */
		private static function compose( $original, $salt, $target, $kind, &$why ) {
			$eol   = false !== strpos( $original, "\r\n" ) ? "\r\n" : "\n";
			$block = self::block( $salt, $eol, $kind );
			$start = strpos( $original, self::MARK );
			if ( false !== $start ) {
				$end = strpos( $original, self::END, $start );
				if ( false === $end ) { $why = 'el bloque de DIGITALÍSIMO está incompleto'; return ''; }
				$candidate = substr_replace( $original, $block, $start, $end + strlen( self::END ) - $start );
				if ( self::sound( $candidate, $original, 0 ) ) return $candidate;
				$why = 'no se pudo cambiar la sal dentro del bloque de DIGITALÍSIMO';
				return '';
			}
			if ( 'config' === $target ) {
				$named = array_intersect( array( 'WP_CACHE_KEY_SALT', 'WP_REDIS_PREFIX' ), self::code_names( $original ) );
				if ( $named ) { $why = 'wp-config.php ya define ' . implode( ' y ', $named ) . ' sin efecto (vacía o dentro de una condición): cámbiala por esta línea'; return ''; }
				foreach ( self::insertion_points( $original ) as $at ) {
					$candidate = substr_replace( $original, $block . $eol . $eol, $at, 0 );
					if ( self::top_level( $original, $at ) && self::sound( $candidate, $original, 2 ) ) return $candidate;
				}
				$why = 'no se encontró en wp-config.php un sitio seguro para la línea (antes de «That\'s all, stop editing» o del require de wp-settings.php, como código de nivel superior)';
				return '';
			}
			$at = self::dropin_point( $original );
			if ( null !== $at ) {
				$candidate = substr_replace( $original, ( $at[1] ? $eol : '' ) . $block . $eol, $at[0], 0 );
				if ( self::sound( $candidate, $original, 2 ) ) return $candidate;
			}
			$why = 'no se encontró al principio de object-cache.php un sitio seguro para la línea';
			return '';
		}

		/** Archivo que ya tiene el bloque ('' si ninguno). */
		private static function holder() {
			foreach ( array( self::config_path(), self::dropin_path() ) as $path ) {
				if ( '' !== $path && false !== strpos( (string) @file_get_contents( $path ), self::MARK ) ) return $path; // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			}
			return '';
		}

		/** wp-config.php que carga WordPress (wp-load.php): en ABSPATH o, si no, en la carpeta padre sin wp-settings.php. */
		public static function config_path() {
			$here = ABSPATH . 'wp-config.php';
			if ( file_exists( $here ) ) return $here;
			$up = dirname( ABSPATH ) . '/wp-config.php';
			if ( file_exists( $up ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) return $up;
			return '';
		}

		/** Drop-in de la caché de objetos ('' si no hay). */
		public static function dropin_path() {
			$path = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ) . '/object-cache.php';
			return is_file( $path ) ? $path : '';
		}

		/** Drop-in en uso: 'w3tc', 'ocp', 'litespeed', 'redis', 'memcached', 'other' o 'none'. */
		public static function kind() {
			$path = self::dropin_path();
			if ( '' === $path ) return 'none';
			$head = (string) @file_get_contents( $path, false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			foreach ( array( 'w3tc' => 'W3 Total Cache', 'ocp' => 'Object Cache Pro', 'litespeed' => 'LiteSpeed', 'redis' => 'Redis Object Cache', 'memcached' => 'Memcached' ) as $kind => $needle ) {
				if ( false !== stripos( $head, $needle ) ) return $kind;
			}
			return 'other';
		}

		/** Motivo por el que no se escribe $path ('' si se puede). */
		private static function unwritable( $path, $name ) {
			if ( '' === $path ) return 'no se encontró wp-config.php en la carpeta de WordPress ni en su carpeta padre';
			if ( is_link( $path ) ) return $name . ' es un enlace simbólico';
			clearstatcache( true, $path );
			if ( ! is_file( $path ) || ! is_readable( $path ) || ! is_writable( $path ) ) return 'PHP no puede escribir en ' . $name;
			return '';
		}

		private static function file_mods_allowed() {
			if ( function_exists( 'wp_is_file_mod_allowed' ) ) return (bool) wp_is_file_mod_allowed( 'digitalisimo_cache_salt' );
			return ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
		}

		/**
		 * Posiciones posibles en wp-config.php, en orden: inicio de la línea de «That's all, stop editing» si está antes del
		 * require de wp-settings.php (la constante tiene que existir antes de que cargue la caché) y la del require.
		 */
		private static function insertion_points( $source ) {
			$req  = preg_match( '/^[^\n]*\brequire(?:_once)?\s*\(?\s*ABSPATH\s*\.\s*[\'"]wp-settings\.php[\'"][^\n]*$/m', $source, $m, PREG_OFFSET_CAPTURE ) ? (int) $m[0][1] : null;
			$stop = preg_match( "/^[^\\n]*That's all, stop editing[^\\n]*$/mi", $source, $n, PREG_OFFSET_CAPTURE ) ? (int) $n[0][1] : null;
			$out  = array();
			if ( null !== $stop && ( null === $req || $stop < $req ) ) $out[] = $stop;
			if ( null !== $req ) $out[] = $req;
			return $out;
		}

		/**
		 * ¿Se ejecuta siempre un código puesto en $at? Fuera de llaves y de la sintaxis alternativa (if: … endif;), entre
		 * dos sentencias (no detrás de un `if (…)` sin llaves) y sin un return, exit o __halt_compiler antes.
		 */
		private static function top_level( $source, $at ) {
			try {
				$tokens = token_get_all( $source );
			} catch ( \Throwable $e ) {
				return false;
			}
			$pos   = 0;
			$depth = 0;
			$prev  = null;
			$alt   = array( 'T_ENDIF', 'T_ENDWHILE', 'T_ENDFOR', 'T_ENDFOREACH', 'T_ENDSWITCH', 'T_ENDDECLARE' );
			foreach ( $tokens as $token ) {
				$id = is_array( $token ) ? $token[0] : $token;
				if ( is_int( $id ) && in_array( token_name( $id ), $alt, true ) ) return false;
			}
			foreach ( $tokens as $token ) {
				$id   = is_array( $token ) ? $token[0] : $token;
				$text = is_array( $token ) ? $token[1] : $token;
				if ( $pos >= $at ) break;
				if ( $pos + strlen( $text ) > $at ) {
					if ( T_WHITESPACE !== $id ) return false; // Dentro de un comentario, una cadena o un heredoc.
					break;
				}
				if ( '{' === $id || T_CURLY_OPEN === $id || T_DOLLAR_OPEN_CURLY_BRACES === $id ) $depth++;
				if ( '}' === $id ) $depth--;
				if ( 0 === $depth && in_array( $id, array( T_RETURN, T_EXIT, T_HALT_COMPILER ), true ) ) return false;
				if ( ! in_array( $id, array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) $prev = $id;
				$pos += strlen( $text );
			}
			return 0 === $depth && in_array( $prev, array( ';', '}', T_OPEN_TAG ), true );
		}

		/** En object-cache.php: tras la etiqueta de apertura (o tras su declare(strict_types=1);). array( posición, ¿hace falta salto? ). */
		private static function dropin_point( $source ) {
			try {
				$tokens = token_get_all( $source );
			} catch ( \Throwable $e ) {
				return null;
			}
			if ( ! isset( $tokens[0] ) || ! is_array( $tokens[0] ) || T_OPEN_TAG !== $tokens[0][0] ) return null;
			$pos  = strlen( $tokens[0][1] );
			$at   = array( $pos, "\n" !== substr( $tokens[0][1], -1 ) );
			$seen = false;
			for ( $i = 1, $n = count( $tokens ); $i < $n; $i++ ) {
				$id   = is_array( $tokens[ $i ] ) ? $tokens[ $i ][0] : $tokens[ $i ];
				$text = is_array( $tokens[ $i ] ) ? $tokens[ $i ][1] : $tokens[ $i ];
				$pos += strlen( $text );
				if ( in_array( $id, array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
				if ( ! $seen && T_DECLARE !== $id ) return T_NAMESPACE === $id ? null : $at;
				$seen = true;
				if ( ';' === $id ) return array( $pos, true );
			}
			return $at;
		}

		/** ¿PHP válido y con exactamente $added apariciones más de WP_CACHE_KEY_SALT como código (no en comentarios)? */
		private static function sound( $candidate, $original, $added ) {
			try {
				token_get_all( $candidate, defined( 'TOKEN_PARSE' ) ? TOKEN_PARSE : 0 );
			} catch ( \Throwable $e ) {
				return false;
			}
			$count = function ( $source ) { return count( array_keys( self::code_names( $source ), 'WP_CACHE_KEY_SALT', true ) ); };
			return $count( $candidate ) - $count( $original ) === (int) $added && 1 === substr_count( $candidate, self::MARK );
		}

		/** Nombres (cadenas y constantes) que aparecen como código, no en comentarios. */
		private static function code_names( $source ) {
			$names = array();
			try {
				$tokens = token_get_all( $source );
			} catch ( \Throwable $e ) {
				return array( 'WP_CACHE_KEY_SALT' ); // Sin poder leerlo, no se toca.
			}
			foreach ( $tokens as $token ) {
				if ( is_array( $token ) && in_array( $token[0], array( T_STRING, T_CONSTANT_ENCAPSED_STRING ), true ) ) $names[] = trim( $token[1], '\'"' );
			}
			return $names;
		}

		/** Copia con fecha en el almacén privado de Backups (o junto al archivo), 0600. Devuelve la ruta o ''. */
		private static function backup( $path, $contents ) {
			$dir = '';
			if ( class_exists( '\\Digitalisimo_Backups', false ) && is_callable( array( '\\Digitalisimo_Backups', 'storage_directory' ) ) ) {
				try {
					$dir = (string) \Digitalisimo_Backups::storage_directory();
				} catch ( \Throwable $e ) {
					$dir = '';
				}
			}
			$dir = (string) apply_filters( 'digitalisimo_cache_salt_backup_dir', $dir );
			if ( '' === $dir || ! is_dir( $dir ) || ! is_writable( $dir ) ) $dir = dirname( $path ); // Como UpdraftPlus con su wp-config-pre-ud-restore-backup.php.
			$file = rtrim( $dir, '/\\' ) . '/' . basename( $path, '.php' ) . '-antes-de-la-sal-' . gmdate( 'Ymd-His' ) . '-' . substr( sha1( $contents ), 0, 6 ) . '.php';
			$ok   = false !== @file_put_contents( $file, $contents, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			@chmod( $file, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			$ok = $ok && (bool) apply_filters( 'digitalisimo_cache_salt_backup_ok', hash_equals( sha1( $contents ), (string) @sha1_file( $file ) ), $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( $ok ) return $file;
			if ( file_exists( $file ) ) @unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- una copia a medias (con credenciales) no se queda.
			return '';
		}

		/**
		 * Cambia $path de $original a $expected. 'ok', 'changed' (otro proceso lo cambió desde que se leyó: no se escribe) o
		 * 'error'. Temporal y rename si el temporal sale con el mismo dueño, grupo y permisos (si no, el rename le cambiaría
		 * el dueño); si no, en su sitio: sin vaciarlo, sólo desde el primer byte distinto.
		 */
		private static function put( $path, $original, $expected ) {
			clearstatcache( true, $path );
			$perms = (int) @fileperms( $path ) & 0777; // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$dir   = dirname( $path );
			$tmp   = $dir . '/.digitalisimo-sal-' . bin2hex( random_bytes( 6 ) ) . '.tmp';
			if ( is_writable( $dir ) && strlen( $expected ) === @file_put_contents( $tmp, $expected ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
				if ( $perms ) @chmod( $tmp, $perms ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
				clearstatcache( true, $tmp );
				$same = @fileowner( $tmp ) === @fileowner( $path ) && @filegroup( $tmp ) === @filegroup( $path ) && ( (int) @fileperms( $tmp ) & 0777 ) === $perms; // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( (bool) apply_filters( 'digitalisimo_cache_salt_same_owner', $same, $path ) ) {
					do_action( 'digitalisimo_cache_salt_before_write', $path );
					clearstatcache( true, $path );
					if ( ! hash_equals( sha1( $original ), (string) @sha1_file( $path ) ) ) { @unlink( $tmp ); return 'changed'; } // phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( @rename( $tmp, $path ) ) { self::nudge( $path ); return 'ok'; } // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
				}
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			$handle = @fopen( $path, 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			if ( ! $handle ) return 'error';
			if ( ! flock( $handle, LOCK_EX ) ) { fclose( $handle ); return 'error'; }
			do_action( 'digitalisimo_cache_salt_before_write', $path );
			rewind( $handle );
			if ( stream_get_contents( $handle ) !== $original ) { flock( $handle, LOCK_UN ); fclose( $handle ); return 'changed'; }
			$from = strspn( $original ^ $expected, "\0" );
			$tail = (string) substr( $expected, $from );
			$ok   = 0 === fseek( $handle, $from ) && strlen( $tail ) === fwrite( $handle, $tail ) && ( strlen( $expected ) >= strlen( $original ) || ftruncate( $handle, strlen( $expected ) ) ) && fflush( $handle );
			flock( $handle, LOCK_UN );
			fclose( $handle );
			self::nudge( $path );
			return $ok ? 'ok' : 'error';
		}

		/** Repone $original sólo si el archivo ya no es el original. */
		private static function restore( $path, $original ) {
			clearstatcache( true, $path );
			if ( hash_equals( sha1( $original ), (string) @sha1_file( $path ) ) ) return true; // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$now = (string) @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			self::put( $path, $now, $original );
			clearstatcache( true, $path );
			return hash_equals( sha1( $original ), (string) @sha1_file( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		/** Que PHP lea la versión nueva del archivo (opcache), también si la escribió otro proceso (WP-CLI). */
		private static function nudge( $path ) {
			if ( '' === (string) $path ) return;
			clearstatcache( true, $path );
			if ( function_exists( 'opcache_invalidate' ) ) @opcache_invalidate( $path, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		/** ¿Quedó exactamente lo esperado, con el bloque una sola vez, y es PHP válido? */
		private static function valid( $path, $expected ) {
			clearstatcache( true, $path );
			$now = (string) @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			$ok  = $now === $expected && 1 === substr_count( $now, self::MARK );
			if ( $ok ) {
				try {
					token_get_all( $now, defined( 'TOKEN_PARSE' ) ? TOKEN_PARSE : 0 );
				} catch ( \Throwable $e ) {
					$ok = false;
				}
			}
			return (bool) apply_filters( 'digitalisimo_cache_salt_valid', $ok, $now );
		}

		/* ------------------------------------------------------------------ Comprobación y lecturas directas */

		/** Primera petición con la sal en vigor: ¿cambió de verdad el espacio de claves? */
		private static function probe( array $last ) {
			$salt  = self::current();
			$token = (string) ( $last['probe'] ?? '' );
			$seen  = function_exists( 'wp_cache_get' ) ? wp_cache_get( self::PROBE, self::GROUP, true ) : false;
			$out   = $last + array( 'file' => '', 'via' => '', 'at' => time() );
			$out['seen']  = $salt;
			$out['probe'] = '';
			$out['salt']  = $salt;
			if ( '' !== $token && is_string( $seen ) && hash_equals( $token, $seen ) ) {
				// El drop-in no separa sus claves con la sal (ni con el prefijo): no hay forma de aislarlo desde aquí.
				if ( function_exists( 'wp_cache_delete' ) ) wp_cache_delete( self::PROBE, self::GROUP );
				$out['state']   = 'ignored';
				$out['ignored'] = $salt;
				return self::finish( $out, 'la caché de objetos (' . self::kind() . ') no separa sus claves con WP_CACHE_KEY_SALT ni con WP_REDIS_PREFIX' . ( defined( 'WP_REDIS_CONFIG' ) ? ": agrega 'prefix' => '" . $salt . "' a WP_REDIS_CONFIG" : '' ) );
			}
			$out['state']   = 'active';
			$out['ignored'] = '';
			if ( in_array( $last['state'] ?? '', array( 'written', 'pending' ), true ) ) $out['done_at'] = time();
			return self::finish( $out, (string) ( $last['message'] ?? '' ) );
		}

		/** ¿Caché externa sin una sal o prefijo ajenos? */
		private static function external_unsalted() {
			return class_exists( 'Digitalisimo_Same_Site', false ) && Digitalisimo_Same_Site::external() && ! self::others();
		}

		/**
		 * Último recurso cuando la caché no se puede aislar: en esta petición, las opciones, entradas, cuentas y demás
		 * grupos de un sitio se leen de la base y no se escriben en la caché compartida (grupos no persistentes, como
		 * los admite todo drop-in), sin vaciar la caché global. Lo que esta petición ya había leído de la caché se olvida.
		 */
		public static function bypass() {
			if ( self::$bypassed ) return true;
			if ( ! class_exists( 'Digitalisimo_Same_Site', false ) || ! Digitalisimo_Same_Site::external() || ! function_exists( 'wp_cache_add_non_persistent_groups' ) ) return false;
			wp_cache_add_non_persistent_groups( array_merge( Digitalisimo_Same_Site::SITE_GROUPS, Digitalisimo_Same_Site::PLUGIN_GROUPS ) );
			Digitalisimo_Same_Site::flush_runtime();
			if ( function_exists( 'wp_cache_delete' ) ) {
				foreach ( array( 'alloptions', 'notoptions', 'home', 'siteurl', 'active_plugins', 'template', 'stylesheet', 'cron' ) as $key ) wp_cache_delete( $key, 'options' );
				$network = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;
				foreach ( array( 'notoptions', 'active_sitewide_plugins', 'siteurl' ) as $key ) wp_cache_delete( $network . ':' . $key, 'site-options' );
			}
			return self::$bypassed = true;
		}

		/** ¿Está esta petición leyendo directo de la base? */
		public static function bypassed() {
			return self::$bypassed;
		}

		/* ------------------------------------------------------------------ Registro */

		/** Firma de los archivos para no repetir un intento fallido mientras sigan igual. */
		private static function signature() {
			$sig = array();
			foreach ( array( self::config_path(), self::dropin_path() ) as $path ) {
				if ( '' === $path || ! file_exists( $path ) ) { $sig[] = 'none'; continue; }
				clearstatcache( true, $path );
				$sig[] = $path . '|' . ( (int) @fileperms( $path ) ) . '|' . ( (int) @filemtime( $path ) ) . '|' . ( is_writable( $path ) ? 'w' : 'r' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			return implode( ';', $sig ) . ';' . ( self::file_mods_allowed() ? 'm' : '-' );
		}

		private static function finish( array $out, $why ) {
			$last = self::last();
			foreach ( array( 'seen', 'ignored', 'lost_at', 'cleanup', 'probe', 'done_at' ) as $keep ) {
				if ( ! array_key_exists( $keep, $out ) && array_key_exists( $keep, $last ) ) $out[ $keep ] = $last[ $keep ];
			}
			$out['message'] = (string) $why;
			$out['sig']     = self::signature();
			self::$done     = $out;
			self::store( $out );
			return $out;
		}

		/** Último registro, leído de la base (no de la caché: podría ser la de otro sitio). */
		public static function last() {
			$value = is_multisite() ? Digitalisimo_Same_Site::db_site_value( self::LOG, array() ) : Digitalisimo_Same_Site::db_value( self::LOG, array() );
			return is_array( $value ) ? $value : array();
		}

		/**
		 * Guarda el registro directamente en la base (sin leer y volver a guardar `alloptions`, que con la caché de otro
		 * sitio mezclaría los dos). No toca la caché: el registro sólo se lee de la base, y borrar `notoptions` en una caché
		 * compartida haría que esta petición la volviera a llenar con lo de este sitio, a la vista de los vecinos.
		 */
		private static function store( array $out ) {
			global $wpdb;
			$value = serialize( $out ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- formato de la tabla de opciones.
			if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) return;
			if ( is_multisite() && ! empty( $wpdb->sitemeta ) ) {
				$network = function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;
				$id      = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d LIMIT 1", self::LOG, $network ) );
				if ( null !== $id ) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->sitemeta} SET meta_value = %s WHERE meta_id = %d", $value, (int) $id ) );
				else $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) VALUES (%d, %s, %s)", $network, self::LOG, $value ) );
				return;
			}
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)", self::LOG, $value ) );
		}
	}
}
