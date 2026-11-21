<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

/**
 * Sal propia de la caché de objetos, sin que nadie edite nada (regla del propietario del 2026-11-21: «no debemos
 * modificar nada nosotros»).
 *
 * Con una caché de objetos persistente (Redis, Memcached o LSMCD) sin `WP_CACHE_KEY_SALT` ni `WP_REDIS_PREFIX`, dos
 * WordPress del mismo servidor leen las mismas claves y cada uno ve las opciones del otro en cada página (incidente de
 * plomerito). Saltarse la caché durante las operaciones propias no basta: el problema está en las visitas normales,
 * fuera de ellas. Tools agrega solo a wp-config.php un bloque marcado con
 * `define( 'WP_CACHE_KEY_SALT', '<host>:<8 hex de la instalación>:' );`:
 *  - sólo si wp-config.php es el que carga WordPress (en ABSPATH o en su carpeta padre), no es un enlace, se puede
 *    escribir y no trae ya una sal, un prefijo ni el bloque;
 *  - antes, una copia con fecha en el almacén privado de Backups (o, sin él, junto a wp-config.php, 0600);
 *  - escritura atómica (temporal y rename, con los mismos permisos; si el dueño del archivo no es el de PHP, se reescribe
 *    en su sitio para no cambiarle el dueño), validada con token_get_all( TOKEN_PARSE ) y comparada byte a byte con lo
 *    esperado; si algo falla, se repone el original;
 *  - el bloque nunca se duplica y la sal no depende de la petición (dos peticiones a la vez escriben lo mismo).
 * En una red, una sola sal con el host del sitio principal (wp-config.php es de toda la red). Corre en la siguiente
 * petición del escritorio de quien gestiona el sitio (manage_options; en una red, manage_network) y al pulsar Migrar,
 * antes del respaldo. Si no puede escribir, el aviso de Cache_Notice da la línea exacta: único caso en que se pide algo
 * a mano. El bloque se queda al desinstalar Tools (quitarlo a mano es seguro: la caché vuelve a no tener sal).
 */
final class Cache_Salt {
	/** Registro del último intento (opción sin carga automática; en una red, de la red). */
	const LOG = 'digitalisimo_tools_cache_salt';
	const MARK = '/* DIGITALÍSIMO: sal propia de la caché de objetos */';
	const END  = '/* Fin de DIGITALÍSIMO: se puede quitar a mano sin riesgo */';
	/** Opciones del sitio cuyas claves viejas (sin sal) se borran tras escribir: las que mezcló el incidente. */
	const OPTIONS = array( 'home', 'siteurl', 'blogname', 'blogdescription', 'active_plugins', 'site_icon', 'template', 'stylesheet', 'cron' );

	/** @var array|null resultado de esta petición. */
	private static $done = null;

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'admin' ), 1 );
		add_filter( 'digitalisimo_tools_cache_salt', array( __CLASS__, 'filter' ), 10, 2 );
	}

	/** Para Migración (`apply_filters( 'digitalisimo_tools_cache_salt', null, 'migration' )`). */
	public static function filter( $result, $via = 'migration' ) {
		return self::ensure( is_string( $via ) ? $via : 'migration' );
	}

	/** Escritorio: una vez, para quien gestiona el sitio o la red (nunca en AJAX ni en el cron). */
	public static function admin() {
		if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) return;
		if ( ! current_user_can( is_multisite() ? 'manage_network' : 'manage_options' ) ) return;
		if ( ! self::needed() ) return;
		$last = self::last();
		// Un intento que no pudo escribir no se repite mientras wp-config.php siga igual (ruta, permisos y fecha).
		// Tampoco uno que encontró el bloque ya puesto ('pending'): no se reescribe el registro en cada carga.
		if ( in_array( $last['state'] ?? '', array( 'manual', 'failed', 'pending' ), true ) && ( $last['sig'] ?? '' ) === self::signature( self::config_path() ) ) {
			self::$done = $last;
			return;
		}
		self::ensure( 'admin' );
	}

	/** ¿Hay caché externa sin sal ni prefijo? */
	public static function needed() {
		return class_exists( '\\Digitalisimo_Same_Site', false ) && \Digitalisimo_Same_Site::external() && ! \Digitalisimo_Same_Site::salted();
	}

	/** Resultado de esta petición o el último registrado. */
	public static function result() {
		return null !== self::$done ? self::$done : self::last();
	}

	/**
	 * Escribe la sal si hace falta. Devuelve array( 'state' => …, 'message' => …, 'line' => …, … ) con state:
	 * 'not_needed' (sin caché externa o con sal), 'written' (escrita: vale desde la petición siguiente), 'pending' (el
	 * bloque ya está; la constante entra en la petición siguiente), 'manual' (no se puede escribir: hay que copiar la
	 * línea) o 'failed' (la escritura no pasó la validación y se repuso el original).
	 */
	public static function ensure( $via = 'admin' ) {
		if ( null !== self::$done && 'manual' !== self::$done['state'] && 'failed' !== self::$done['state'] ) return self::$done;
		if ( ! self::needed() ) return self::$done = array( 'state' => 'not_needed', 'message' => '', 'line' => '' );
		$salt = self::salt();
		$line = "define( 'WP_CACHE_KEY_SALT', '" . $salt . "' );";
		$path = self::config_path();
		$out  = array( 'state' => 'manual', 'message' => '', 'line' => $line, 'salt' => $salt, 'file' => $path, 'backup' => '', 'via' => (string) $via, 'at' => time() );
		$why  = self::unwritable( $path );
		if ( '' !== $why ) return self::finish( $out, $why );
		$original = (string) @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		if ( '' === $original ) return self::finish( $out, 'no se pudo leer wp-config.php' );
		if ( false !== strpos( $original, self::MARK ) ) {
			$out['state'] = 'pending';
			return self::finish( $out, 'wp-config.php ya trae la sal de DIGITALÍSIMO; vale desde la carga siguiente' );
		}
		if ( array_intersect( array( 'WP_CACHE_KEY_SALT', 'WP_REDIS_PREFIX', 'WP_REDIS_CONFIG' ), self::code_names( $original ) ) ) {
			return self::finish( $out, 'wp-config.php ya nombra una sal o un prefijo que no está en vigor (quizá dentro de una condición)' );
		}
		$eol      = false !== strpos( $original, "\r\n" ) ? "\r\n" : "\n";
		$block    = self::MARK . $eol . "defined( 'WP_CACHE_KEY_SALT' ) || " . $line . $eol . self::END . $eol . $eol;
		$expected = '';
		foreach ( self::insertion_points( $original ) as $at ) {
			$candidate = substr_replace( $original, $block, $at, 0 );
			if ( self::sound( $candidate ) ) { $expected = $candidate; break; }
		}
		if ( '' === $expected ) return self::finish( $out, 'no se encontró en wp-config.php dónde va la línea (antes de «That\'s all, stop editing» o de require_once ABSPATH . \'wp-settings.php\', fuera de un comentario)' );
		$backup   = self::backup( $path, $original );
		if ( '' === $backup ) return self::finish( $out, 'no se pudo guardar antes una copia de wp-config.php' );
		$out['backup'] = $backup;
		$perms         = (int) @fileperms( $path ) & 0777; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! self::put( $path, $expected, $perms ) || ! self::valid( $path, $expected, $line ) ) {
			$restored = self::put( $path, $original, $perms ) && hash_equals( sha1( $original ), (string) @sha1_file( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$out['state'] = 'failed';
			return self::finish( $out, $restored ? 'la escritura no pasó la comprobación y se repuso el wp-config.php original' : 'la escritura no pasó la comprobación y no se pudo reponer el original: está en ' . $backup );
		}
		$out['state'] = 'written';
		// Las claves viejas, sin sal, son las que comparte con los otros sitios: se retiran sólo las de este sitio (nunca la
		// caché global), para que un vecino sin sal tampoco siga leyendo los datos de aquí. Con la sal, este sitio ya no las lee.
		\Digitalisimo_Same_Site::invalidate( self::OPTIONS, is_multisite() ? array( 'siteurl', 'active_sitewide_plugins' ) : array() );
		return self::finish( $out, '' );
	}

	/** Sal: `<host del sitio (en una red, del principal)>:<8 hex de la instalación>:`, estable entre peticiones. */
	public static function salt() {
		$host = preg_replace( '/[^a-z0-9.\-]/', '', \Digitalisimo_Same_Site::host( self::main_home() ) );
		if ( '' === $host ) $host = 'sitio';
		return $host . ':' . self::installation() . ':';
	}

	/** `home` del sitio (en una red, del principal) leído de la base, nunca de la caché (podría ser la de otro sitio). */
	private static function main_home() {
		global $wpdb;
		if ( ! is_multisite() || ! function_exists( 'get_main_site_id' ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_blog_prefix' ) ) return (string) \Digitalisimo_Same_Site::db_option( 'home' );
		$table = $wpdb->get_blog_prefix( (int) get_main_site_id() ) . 'options';
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1", 'home' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nombre de tabla del núcleo.
	}

	/** 8 hex de la identidad de la instalación (la de Backups si está; si no, la raíz, la base y el prefijo). */
	private static function installation() {
		$id = '';
		if ( class_exists( '\\Digitalisimo_Backups', false ) && is_callable( array( '\\Digitalisimo_Backups', 'installation_owner' ) ) ) {
			try {
				$id = (string) \Digitalisimo_Backups::installation_owner();
			} catch ( \Throwable $e ) {
				$id = '';
			}
		}
		if ( ! preg_match( '/^[a-f0-9]{8}/', $id ) ) {
			global $wpdb;
			$root = realpath( ABSPATH );
			$id   = hash( 'sha256', ( false !== $root ? $root : ABSPATH ) . "\n" . ( defined( 'DB_NAME' ) ? DB_NAME : '' ) . "\n" . ( is_object( $wpdb ) && isset( $wpdb->base_prefix ) ? $wpdb->base_prefix : '' ) );
		}
		return substr( $id, 0, 8 );
	}

	/** wp-config.php que carga WordPress (wp-load.php): en ABSPATH o, si no, en la carpeta padre sin wp-settings.php. */
	public static function config_path() {
		$here = ABSPATH . 'wp-config.php';
		if ( file_exists( $here ) ) return $here;
		$up = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $up ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) return $up;
		return '';
	}

	/** Motivo por el que no se escribe ('' si se puede). */
	private static function unwritable( $path ) {
		if ( '' === $path ) return 'no se encontró wp-config.php en la carpeta de WordPress ni en su carpeta padre';
		if ( is_link( $path ) ) return 'wp-config.php es un enlace simbólico';
		clearstatcache( true, $path );
		if ( ! is_file( $path ) || ! is_readable( $path ) || ! is_writable( $path ) ) return 'PHP no puede escribir en wp-config.php';
		if ( self::same_owner( $path ) && ! is_writable( dirname( $path ) ) ) return 'PHP no puede crear archivos junto a wp-config.php';
		return '';
	}

	/** ¿Es PHP el dueño del archivo? (sin posix, se supone que sí, como en la mayoría de los alojamientos). */
	private static function same_owner( $path ) {
		if ( ! function_exists( 'posix_geteuid' ) || ! function_exists( 'fileowner' ) ) return true;
		$owner = @fileowner( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return false === $owner || (int) $owner === (int) posix_geteuid();
	}

	/**
	 * Posiciones posibles del bloque, en orden: inicio de la línea de «That's all, stop editing» si está antes del
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
	 * ¿Es PHP válido y la línea quedó como código (no dentro de un comentario o de una cadena)? La constante aparece dos
	 * veces como código: en defined() y en define().
	 */
	private static function sound( $source ) {
		try {
			token_get_all( $source, defined( 'TOKEN_PARSE' ) ? TOKEN_PARSE : 0 );
		} catch ( \Throwable $e ) {
			return false;
		}
		return 2 === count( array_keys( self::code_names( $source ), 'WP_CACHE_KEY_SALT', true ) );
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

	/** Copia de wp-config.php con fecha, en el almacén privado (0600). Devuelve la ruta o ''. */
	private static function backup( $path, $contents ) {
		$dir = '';
		if ( class_exists( '\\Digitalisimo_Backups', false ) && is_callable( array( '\\Digitalisimo_Backups', 'storage_directory' ) ) ) {
			try {
				$dir = (string) \Digitalisimo_Backups::storage_directory();
			} catch ( \Throwable $e ) {
				$dir = '';
			}
		}
		$dir = apply_filters( 'digitalisimo_tools_cache_salt_backup_dir', $dir );
		if ( '' === $dir || ! is_dir( $dir ) || ! is_writable( $dir ) ) $dir = dirname( $path ); // Como UpdraftPlus con su wp-config-pre-ud-restore-backup.php.
		$file = rtrim( (string) $dir, '/\\' ) . '/wp-config-antes-de-la-sal-' . gmdate( 'Ymd-His' ) . '-' . substr( sha1( $contents ), 0, 6 ) . '.php';
		if ( false === @file_put_contents( $file, $contents, LOCK_EX ) ) return ''; // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		@chmod( $file, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		return hash_equals( sha1( $contents ), (string) @sha1_file( $file ) ) ? $file : ''; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	/** Escribe $contents en $path conservando permisos (y dueño): temporal y rename o, con otro dueño, en su sitio. */
	private static function put( $path, $contents, $perms ) {
		if ( self::same_owner( $path ) ) {
			$tmp = dirname( $path ) . '/.wp-config.digitalisimo-' . bin2hex( random_bytes( 6 ) ) . '.tmp';
			if ( strlen( $contents ) !== @file_put_contents( $tmp, $contents, LOCK_EX ) ) { @unlink( $tmp ); return false; } // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			if ( $perms ) @chmod( $tmp, $perms ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			if ( ! @rename( $tmp, $path ) ) { @unlink( $tmp ); return false; } // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		} else {
			$handle = @fopen( $path, 'c+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
			if ( ! $handle ) return false;
			$ok = flock( $handle, LOCK_EX ) && ftruncate( $handle, 0 ) && 0 === fseek( $handle, 0 ) && strlen( $contents ) === fwrite( $handle, $contents ) && fflush( $handle );
			flock( $handle, LOCK_UN );
			fclose( $handle );
			if ( ! $ok ) return false;
		}
		clearstatcache( true, $path );
		if ( function_exists( 'opcache_invalidate' ) ) @opcache_invalidate( $path, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return true;
	}

	/** ¿Quedó exactamente lo esperado, con la línea una sola vez, y es PHP válido? */
	private static function valid( $path, $expected, $line ) {
		clearstatcache( true, $path );
		$now = (string) @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions,WordPress.PHP.NoSilencedErrors
		$ok  = $now === $expected && 1 === substr_count( $now, $line ) && 1 === substr_count( $now, self::MARK ) && self::sound( $now );
		return (bool) apply_filters( 'digitalisimo_tools_cache_salt_valid', $ok, $now );
	}

	/** Firma de wp-config.php para no repetir un intento fallido mientras siga igual. */
	private static function signature( $path ) {
		if ( '' === $path || ! file_exists( $path ) ) return 'none';
		clearstatcache( true, $path );
		return $path . '|' . ( (int) @fileperms( $path ) ) . '|' . ( (int) @filemtime( $path ) ) . '|' . ( is_writable( $path ) ? 'w' : 'r' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	private static function finish( array $out, $why ) {
		$out['message'] = (string) $why;
		$out['sig']     = self::signature( (string) $out['file'] );
		self::$done     = $out;
		self::store( $out );
		return $out;
	}

	/** Último registro, leído de la base (no de la caché: podría ser la de otro sitio). */
	public static function last() {
		$value = is_multisite() ? \Digitalisimo_Same_Site::db_site_value( self::LOG, array() ) : \Digitalisimo_Same_Site::db_value( self::LOG, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Guarda el registro directamente en la base (sin leer y volver a guardar `alloptions`, que con la caché de otro
	 * sitio mezclaría los dos) y retira su clave de la caché.
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
			if ( function_exists( 'wp_cache_delete' ) ) { wp_cache_delete( $network . ':' . self::LOG, 'site-options' ); wp_cache_delete( $network . ':notoptions', 'site-options' ); }
			return;
		}
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)", self::LOG, $value ) );
		if ( function_exists( 'wp_cache_delete' ) ) { wp_cache_delete( self::LOG, 'options' ); wp_cache_delete( 'notoptions', 'options' ); }
	}
}
