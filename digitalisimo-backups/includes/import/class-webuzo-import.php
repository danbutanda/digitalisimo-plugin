<?php
defined( 'ABSPATH' ) || exit;

/**
 * Importar un respaldo de cuenta de Webuzo (el motor Backuply del panel: `tech: tar`, `backup_method: compressed`):
 * `<usuario>_backup_full[_auto]_remote_<fecha>.tar.gz`. De toda la cuenta se toma **un** WordPress (el que elige la
 * persona, o el único que hay) y se convierte, por tramos del motor de trabajos y como archivo no confiable, en un ZIP de
 * Backups (manifiesto v3 sin firma, `database.sql` y `wordpress/…`) que entra en la lista como «Importado» y se restaura
 * con la restauración por pasos (verificación, copia previa, reubicación de E1, H23, conservar el acceso y los
 * administradores). Ver «Importar respaldos de cuenta de Webuzo» en docs/BACKUPS.md.
 *
 * Formato (comprobado con respaldos reales del panel):
 * - tar GNU («ustar  », nombres largos `././@LongLink` tipo L, enlaces simbólicos tipo 2) en un solo miembro gzip, con
 *   dos tramos que empiezan cada uno por la carpeta `.`: primero `backuply-data/` (los datos de la cuenta: `info` y
 *   `domains` en JSON con el `homedir` y la carpeta de cada dominio, `mysql/<base>.sql.gz` con un mysqldump de MariaDB por
 *   base, y secretos: `ssl/` con llaves privadas, `shadow`, `grant_priv.json`, `ftp.json`…), después el home de la
 *   cuenta (`public_html/`, otros dominios, `.acme.sh/`, `ssl/`, `mail/`, `.softaculous/`, `tmp/`, respaldos anidados…).
 * - Cada WordPress se reconoce por su núcleo (`wp-load.php`, `wp-settings.php`, `wp-admin/index.php` y
 *   `wp-includes/version.php`) y su `wp-config.php` (`DB_NAME` y `$table_prefix`, leídos con token_get_all(), sin
 *   ejecutar nada); su base es `backuply-data/mysql/<DB_NAME>.sql.gz`, que puede faltar. Una base puede traer tablas de
 *   más de un WordPress: sólo se importan las del prefijo del elegido.
 *
 * Nada de la cuenta fuera de la carpeta del WordPress elegido entra al ZIP (ni `backuply-data`, ni `ssl`, ni `.acme.sh`,
 * ni el correo, ni otro sitio); dentro de ella tampoco `wp-config.php` (un marcador, como en Backuply), otros WordPress o
 * dominios anidados, respaldos anidados ni lo que Backups no respalda. Cada cabecera del tar se comprueba con el lector
 * de la importación de Backuply (suma, tipo, tamaño, rutas sin `..`, absolutas, NUL ni barra invertida), las entradas y
 * los tamaños quedan acotados y nada del archivo se ejecuta ni se deserializa.
 *
 * Fases del trabajo `import` (`format: webuzo`):
 * 1. `unpack`: se recorre el `.tar.gz` en flujo (sin descomprimirlo entero al disco). Sin WordPress elegido es el
 *    **análisis**: anota los WordPress y sus bases; con uno solo sigue solo, con varios el trabajo termina con la lista
 *    para elegir. Con uno elegido, la **extracción**: sólo sus archivos (a `files.bin` con su índice) y su `.sql.gz`.
 * 2. `scan`: la base `.sql.gz` se descomprime a `source.sql`.
 * 3. `database`: el traductor de Backuply (Digitalisimo_Backups_Backuply_Sql) con el dialecto de mysqldump, sin la línea
 *    sandbox, sin `CREATE DATABASE` ni `USE` y sólo con las tablas del prefijo; el guardián SQL en cada CREATE.
 * 4. `files`: el ZIP con el escritor de T2. 5. `finish`: manifiesto, publicación y fila «Importado».
 */
final class Digitalisimo_Backups_Webuzo_Import {
	const FORMAT = 'webuzo';
	const PACKER = 'digitalisimo-webuzo-import/1';
	/** Carpeta del almacén privado para los `.tar.gz` subidos o traídos de un destino. */
	const STORE_DIRECTORY = 'webuzo';
	/** Nombre aceptable en el almacén (la misma lista blanca que Backuply: sin rutas). */
	const NAME = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}\.tar\.gz$/D';
	/** Un respaldo del panel en el home de la cuenta: `<usuario>_backup_<…>.tar.gz`. */
	const ACCOUNT = '/^([A-Za-z0-9][A-Za-z0-9_-]{0,31})_backup_[A-Za-z0-9._-]{1,150}\.tar\.gz$/D';
	/** Lo que se lee de un archivo pequeño (info, domains, wp-config.php, version.php). */
	const MAX_SMALL = 65536;
	/** Un archivo de los que se leen sólo se lee si mide hasta esto (más, y no es el de un WordPress). */
	const MAX_GRAB = 1048576;
	/** Una pieza que cambió hace menos que esto puede estar escribiéndola el panel. */
	const SETTLE = 60;
	const SPACE_EVERY = 16777216;
	const CONFIG_PLACEHOLDER = "<?php\n// DIGITALÍSIMO Backups: el wp-config.php del respaldo de Webuzo no se importa (lleva las credenciales de su servidor).\n// Marcador del ZIP convertido: la restauración nunca escribe wp-config.php.\n";
	/** El núcleo que todo WordPress trae (bits de `core`). */
	const CORE = array( 'wp-load.php' => 1, 'wp-settings.php' => 2, 'wp-admin/index.php' => 4, 'wp-includes/version.php' => 8 );
	/** Carpetas del home que nunca son un sitio: los datos de la cuenta, secretos, correo, temporales y respaldos. */
	const NOT_SITES = array( 'backuply-data', 'ssl', 'mail', 'etc', 'tmp', 'logs', 'softaculous_backups', 'access-logs', 'cache', 'perl5', 'php' );

	/* ------------------------------------------------------------------ Orígenes */

	public static function store_directory( $create = false ) {
		$dir = rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . self::STORE_DIRECTORY;
		if ( is_link( $dir ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta de respaldos de Webuzo del almacén no puede ser un enlace simbólico.' );
		if ( $create && ! is_dir( $dir ) ) {
			if ( ! @mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) throw new Exception( 'No se pudo crear la carpeta de respaldos de Webuzo en el almacén privado.' );
			@chmod( $dir, 0700 );
		}
		return $dir;
	}
	/**
	 * Carpetas donde el panel deja los respaldos de la cuenta: el home, que es la carpeta de arriba de este WordPress (o
	 * la del usuario de PHP, si este WordPress está dentro de ella). Sólo carpetas reales, sin enlaces, que contienen a
	 * ABSPATH; con open_basedir que no las deja leer, ninguna.
	 */
	public static function home_directories() {
		$abspath = realpath( ABSPATH );
		if ( false === $abspath ) return array();
		$abspath = rtrim( wp_normalize_path( $abspath ), '/' );
		$candidates = array( dirname( $abspath ) );
		if ( function_exists( 'posix_getpwuid' ) && function_exists( 'posix_geteuid' ) ) {
			$user = @posix_getpwuid( @posix_geteuid() );
			if ( is_array( $user ) && is_string( $user['dir'] ?? null ) && '' !== $user['dir'] ) $candidates[] = rtrim( wp_normalize_path( $user['dir'] ), '/' );
		}
		if ( function_exists( 'apply_filters' ) ) $candidates = (array) apply_filters( 'digitalisimo_backups_webuzo_home_directories', $candidates );
		$out = array();
		foreach ( $candidates as $dir ) {
			$dir = rtrim( (string) $dir, '/' );
			if ( '' === $dir || false !== strpos( $dir, "\0" ) || is_link( $dir ) || ! @is_dir( $dir ) ) continue;
			$real = realpath( $dir );
			if ( false === $real ) continue;
			$real = rtrim( wp_normalize_path( $real ), '/' );
			// Sólo una carpeta que contiene a este WordPress: nunca otra del servidor.
			if ( '' === $real || 0 !== strpos( $abspath . '/', $real . '/' ) || $real === $abspath ) continue;
			$out[ $real ] = true;
		}
		return array_keys( $out );
	}
	/** Ruta real de un origen: un `.tar.gz` regular del almacén (`store`) o del home de la cuenta (`home`). '' si no. */
	public static function source_path( $origin, $name ) {
		$name = (string) $name;
		if ( 'home' === $origin ) {
			if ( ! preg_match( self::ACCOUNT, $name ) ) return '';
			$dirs = self::home_directories();
		} elseif ( 'store' === $origin ) {
			if ( ! preg_match( self::NAME, $name ) ) return '';
			try {
				$dirs = array( self::store_directory() );
			} catch ( Exception $e ) {
				return '';
			}
		} else {
			return '';
		}
		foreach ( $dirs as $dir ) {
			$path = rtrim( $dir, '/\\' ) . '/' . $name;
			if ( is_file( $path ) && ! is_link( $path ) ) return $path;
		}
		return '';
	}
	/** Respaldos de Webuzo que se pueden importar (home y almacén), del más reciente al más antiguo. Sin rutas. */
	public static function sources() {
		self::clean_partials();
		self::adopt_backuply();
		$items = array();
		foreach ( self::home_directories() as $dir ) {
			foreach ( (array) glob( $dir . '/*_backup_*.tar.gz' ) as $path ) {
				$item = self::describe( 'home', (string) $path );
				if ( $item ) $items[] = $item;
			}
		}
		try {
			$store = self::store_directory();
			if ( is_dir( $store ) ) foreach ( (array) glob( $store . '/*.tar.gz' ) as $path ) {
				$item = self::describe( 'store', (string) $path );
				if ( $item ) $items[] = $item;
			}
		} catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		usort( $items, function( $a, $b ) { return array( $b['created'], $a['source'], $a['name'] ) <=> array( $a['created'], $b['source'], $b['name'] ); } );
		return $items;
	}
	/**
	 * Un respaldo de cuenta de Webuzo que se subió antes de esta versión quedó en `backuply/` del almacén (Backuply ahora lo
	 * rechaza): se muda a `webuzo/` con un nombre libre, salvo que un trabajo lo esté usando. Devuelve los mudados.
	 */
	public static function adopt_backuply() {
		$moved = array();
		try {
			$from = Digitalisimo_Backups_Backuply_Import::store_directory();
			if ( ! is_dir( $from ) ) return $moved;
			$busy = array();
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( Digitalisimo_Backups_Import_Job::is_import( $job ) ) $busy[ (string) ( $job['spec']['source']['name'] ?? '' ) ] = true;
			foreach ( (array) glob( $from . '/*.tar.gz' ) as $path ) {
				$name = basename( (string) $path );
				if ( isset( $busy[ $name ] ) || is_link( $path ) || ! is_file( $path ) || 'webuzo' !== self::sniff( $path ) ) continue;
				$store = self::store_directory( true );
				$moved[] = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $path, $store, $name ) {
					$file = $name;
					while ( file_exists( $store . '/' . $file ) || is_link( $store . '/' . $file ) ) $file = substr( $name, 0, -7 ) . '-' . bin2hex( random_bytes( 3 ) ) . '.tar.gz';
					if ( ! @rename( $path, $store . '/' . $file ) ) throw new Exception( 'No se pudo mover el respaldo de Webuzo al almacén de Webuzo.' );
					return $file;
				} );
			}
		} catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		return $moved;
	}
	/** Lo que la pantalla, REST y WP-CLI dicen de un respaldo, con su análisis si ya lo hay. */
	public static function describe( $origin, $path ) {
		$name = basename( (string) $path );
		if ( ! preg_match( 'home' === $origin ? self::ACCOUNT : self::NAME, $name ) || is_link( $path ) || ! is_file( $path ) ) return null;
		clearstatcache( true, $path );
		$size = (int) @filesize( $path );
		$mtime = (int) @filemtime( $path );
		$created = self::name_time( $name ) ?: $mtime;
		$side = self::side_info( $path );
		$analysis = self::analysis( $origin, $name, $size, $mtime );
		$busy = 'home' === $origin && $mtime > time() - self::SETTLE;
		return array(
			'source' => $origin,
			'name' => $name,
			'size' => $size,
			'created' => $created,
			'date' => gmdate( 'Y-m-d H:i', $created ),
			'account' => preg_match( self::ACCOUNT, $name, $m ) ? $m[1] : '',
			// Lo que dice el `.tar.gz.json` del panel, si está al lado (orientativo: el análisis lo comprueba dentro del tar).
			'contains' => $side['contains'],
			'analyzed' => is_array( $analysis ),
			'sites' => is_array( $analysis ) ? (array) $analysis['sites'] : array(),
			'importable' => ! $busy,
			'problem' => $busy ? 'El panel de Webuzo todavía está escribiendo este respaldo (cambió hace menos de un minuto): espera a que termine.' : '',
			'large' => $size > self::web_max(),
			'advice' => self::size_advice( $size ),
			'imported' => self::converted_names( $name ),
		);
	}
	/** `backup_contains` del `.tar.gz.json` que el panel deja junto al respaldo (sin rutas ni secretos). */
	private static function side_info( $path ) {
		$out = array( 'contains' => array() );
		$json = $path . '.json';
		if ( ! is_file( $json ) || is_link( $json ) || (int) @filesize( $json ) > self::MAX_SMALL ) return $out;
		$data = json_decode( (string) @file_get_contents( $json, false, null, 0, self::MAX_SMALL ), true, 8 );
		if ( ! is_array( $data ) || ! is_string( $data['backup_contains'] ?? null ) ) return $out;
		foreach ( array_slice( explode( ',', $data['backup_contains'] ), 0, 20 ) as $part ) if ( preg_match( '/^[a-z_]{1,32}$/', trim( $part ) ) ) $out['contains'][] = trim( $part );
		return $out;
	}
	/** Fecha del nombre (`…_AAAA-MM-DD_hh-mm-ss.tar.gz`), la hora del servidor del panel; 0 si no la lleva. */
	public static function name_time( $name ) {
		return preg_match( '/_([0-9]{4})-([0-9]{2})-([0-9]{2})_([0-9]{2})-([0-9]{2})-([0-9]{2})\.tar\.gz$/', (string) $name, $m ) ? (int) gmmktime( (int) $m[4], (int) $m[5], (int) $m[6], (int) $m[2], (int) $m[3], (int) $m[1] ) : 0;
	}
	/** Nombre de un `.tar.gz` subido, sin sanitize_file_name(): la lista blanca de Backuply. '' si no es aceptable. */
	public static function upload_name( $raw ) { return Digitalisimo_Backups_Backuply_Import::upload_name( $raw ); }
	/** Retira las descargas de `webuzo fetch` cortadas y los análisis de respaldos que ya no están (más de 6 horas). */
	public static function clean_partials() {
		try {
			$dir = self::store_directory();
		} catch ( Exception $e ) {
			return;
		}
		if ( ! is_dir( $dir ) ) return;
		foreach ( (array) glob( $dir . '/.incoming-*.part' ) as $path ) if ( is_file( $path ) && ! is_link( $path ) && time() - (int) @filemtime( $path ) > 21600 ) @unlink( $path );
		foreach ( (array) glob( $dir . '/.analysis-*.json' ) as $path ) if ( is_file( $path ) && ! is_link( $path ) && time() - (int) @filemtime( $path ) > 30 * 86400 ) @unlink( $path );
	}

	/* ------------------------------------------------------------------ Análisis guardado (para elegir el WordPress) */

	private static function analysis_path( $origin, $name, $size, $mtime ) {
		return self::store_directory( true ) . '/.analysis-' . substr( hash( 'sha256', $origin . "\n" . $name . "\n" . (int) $size . "\n" . (int) $mtime ), 0, 32 ) . '.json';
	}
	/** El análisis de un respaldo (los WordPress que trae), si se hizo con este mismo archivo (tamaño y fecha). */
	public static function analysis( $origin, $name, $size, $mtime ) {
		try {
			$path = self::analysis_path( $origin, $name, $size, $mtime );
		} catch ( Exception $e ) {
			return null;
		}
		if ( ! is_file( $path ) || is_link( $path ) ) return null;
		$data = json_decode( (string) @file_get_contents( $path, false, null, 0, 1048576 ), true, 16 );
		return is_array( $data ) && is_array( $data['sites'] ?? null ) ? $data : null;
	}
	private static function remember_analysis( array $job, array $sites, array $account ) {
		$source = (array) $job['spec']['source'];
		$path = self::analysis_path( (string) $source['origin'], (string) $source['name'], (int) $source['size'], (int) $source['mtime'] );
		if ( false === @file_put_contents( $path, wp_json_encode( array( 'sites' => $sites, 'account' => $account, 'at' => time() ) ), LOCK_EX ) ) throw new Exception( 'No se pudo guardar el análisis del respaldo de Webuzo en el almacén privado.' );
		@chmod( $path, 0600 );
	}
	/** Borra el análisis guardado de un respaldo (al borrarlo del almacén). */
	public static function forget_analysis( $origin, $name, $size, $mtime ) {
		try {
			$path = self::analysis_path( $origin, $name, $size, $mtime );
			if ( is_file( $path ) && ! is_link( $path ) ) @unlink( $path );
		} catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}

	/* ------------------------------------------------------------------ Reconocer el formato */

	/**
	 * Qué es un `.tar.gz` por sus primeras entradas (sin descomprimirlo entero): `webuzo` (la carpeta `.` y después
	 * `backuply-data`), `backuply` (un respaldo del plugin Backuply: `softsql.sql` o los archivos de WordPress en la raíz)
	 * o '' si no se sabe. Lee como mucho 64 KB comprimidos y 1 MB descomprimido.
	 */
	public static function sniff( $path ) {
		if ( ! function_exists( 'inflate_init' ) ) return '';
		$raw = (string) @file_get_contents( $path, false, null, 0, 65536 );
		if ( "\x1f\x8b" !== substr( $raw, 0, 2 ) ) return '';
		$context = @inflate_init( ZLIB_ENCODING_GZIP );
		$tar = '';
		foreach ( str_split( $raw, 4096 ) as $piece ) {
			$chunk = @inflate_add( $context, $piece, ZLIB_SYNC_FLUSH );
			if ( false === $chunk ) break;
			$tar .= $chunk;
			if ( strlen( $tar ) >= 1048576 || ZLIB_STREAM_END === inflate_get_status( $context ) ) break;
		}
		$names = array();
		$pos = 0;
		$long = null;
		try {
			while ( $pos + 512 <= strlen( $tar ) && count( $names ) < 4 ) {
				$block = substr( $tar, $pos, 512 );
				if ( str_repeat( "\0", 512 ) === $block ) break;
				$header = Digitalisimo_Backups_Backuply_Tar::header( $block );
				$data = $pos + 512;
				$pos = $data + Digitalisimo_Backups_Backuply_Tar::padded( $header['size'] );
				if ( 'L' === $header['type'] ) { $long = rtrim( (string) substr( $tar, $data, min( 4096, $header['size'] ) ), "\0" ); continue; }
				if ( in_array( $header['type'], array( 'x', 'g', 'K' ), true ) ) continue;
				$names[] = array( null !== $long ? $long : $header['name'], $header['type'] );
				$long = null;
			}
		} catch ( Exception $e ) {
			return '';
		}
		if ( ! $names ) return '';
		$first = rtrim( preg_replace( '#^(\./)+#', '', $names[0][0] . '/' ), '/' );
		if ( '' === $first && '5' === $names[0][1] ) {
			$second = isset( $names[1] ) ? rtrim( preg_replace( '#^(\./)+#', '', $names[1][0] ), '/' ) : '';
			return 'backuply-data' === $second || 0 === strpos( $second, 'backuply-data/' ) ? 'webuzo' : '';
		}
		foreach ( $names as $entry ) {
			$n = preg_replace( '#^(\./)+#', '', $entry[0] );
			if ( in_array( $n, array( 'softsql.sql', 'wp-load.php', 'wp-settings.php', 'wp-config.php', 'index.php', 'wp-admin', 'wp-admin/', 'wp-content', 'wp-content/', 'wp-includes', 'wp-includes/' ), true ) ) return 'backuply';
		}
		return '';
	}

	/* ------------------------------------------------------------------ Trabajo */

	/**
	 * Especificación de una importación (`kind = import`, `format = webuzo`). `$site`: la carpeta del WordPress elegido,
	 * relativa al home ('' = analizar primero y, si hay uno solo, seguir con él).
	 */
	public static function spec( $origin, $name, $site = '', array $args = array() ) {
		$path = self::source_path( $origin, $name );
		if ( '' === $path ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Webuzo solicitado no está disponible.' );
		$site = self::clean_site( (string) $site );
		if ( null === $site ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta del WordPress elegido no es válida.' );
		clearstatcache( true, $path );
		return array(
			'type' => 'import',
			'format' => self::FORMAT,
			'source' => array( 'origin' => (string) $origin, 'name' => (string) $name, 'size' => (int) filesize( $path ), 'mtime' => (int) filemtime( $path ) ),
			'site' => $site,
			'policy' => 'import:webuzo:' . $origin . ':' . $name,
			'destination' => 'local',
			'file' => self::target_name( $name, $site ),
			'scope' => is_multisite() ? 'network' : 'installation',
			'origin' => array(
				'via' => (string) ( $args['via'] ?? 'admin-post' ),
				'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'blog_id' => (int) get_current_blog_id(),
				'context' => ! empty( $args['network'] ) ? 'network' : 'site',
			),
			'engine' => array( 'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '', 'job_format' => Digitalisimo_Backups_Job_Store::JOB_FORMAT ),
		);
	}
	/** Carpeta de un WordPress dentro del home, como ruta relativa segura; null si no lo es ('' = ninguna). */
	public static function clean_site( $site ) {
		$site = (string) $site;
		if ( '' === $site ) return '';
		if ( '/' === $site[0] || false !== strpos( $site, '\\' ) || strlen( $site ) > 400 || preg_match( '/[\x00-\x1F\x7F]/', $site ) || ! preg_match( '//u', $site ) ) return null;
		$site = rtrim( $site, '/' );
		foreach ( explode( '/', $site ) as $segment ) if ( '' === $segment || '.' === $segment || '..' === $segment ) return null;
		return $site;
	}
	/** Nombre del ZIP convertido: sin puntos (sanitize_file_name() los cambiaría) y con la carpeta del WordPress. */
	public static function target_name( $name, $site = '' ) {
		$stem = trim( (string) preg_replace( '/[^A-Za-z0-9_-]+/', '-', substr( (string) $name, 0, -7 ) ), '-_' );
		$slug = trim( (string) preg_replace( '/[^A-Za-z0-9_-]+/', '-', (string) $site ), '-_' );
		return 'digitalisimo-webuzo-' . substr( $stem, 0, 120 ) . ( '' !== $slug ? '-' . substr( $slug, 0, 60 ) : '' ) . '.zip';
	}
	/** Los ZIP ya convertidos de un respaldo (uno por WordPress importado). */
	public static function converted_names( $name ) {
		try {
			$storage = trailingslashit( Digitalisimo_Backups::storage_directory() );
		} catch ( Exception $e ) {
			return array();
		}
		$stem = substr( self::target_name( $name ), 0, -4 );
		$out = array();
		foreach ( (array) glob( $storage . $stem . '*.zip' ) as $path ) if ( is_file( $path ) && ! is_link( $path ) ) $out[] = basename( (string) $path );
		sort( $out );
		return $out;
	}
	/** El origen sigue siendo el mismo archivo que se pidió importar (tamaño y fecha). */
	private static function source( array $job ) {
		$source = (array) ( $job['spec']['source'] ?? array() );
		$path = self::source_path( (string) ( $source['origin'] ?? '' ), (string) ( $source['name'] ?? '' ) );
		if ( '' === $path ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Webuzo que se estaba importando ya no está en su carpeta.' );
		clearstatcache( true, $path );
		if ( (int) filesize( $path ) !== (int) ( $source['size'] ?? -1 ) || (int) filemtime( $path ) !== (int) ( $source['mtime'] ?? -1 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Webuzo cambió durante la importación (otro proceso lo está escribiendo). Espera a que termine y vuelve a importarlo.' );
		return $path;
	}
	private static function paths( array $job ) {
		$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		return array( 'files' => $staging . '/files.bin', 'index' => $staging . '/index.jsonl', 'gz' => $staging . '/database.sql.gz', 'sql_in' => $staging . '/source.sql', 'sql' => $staging . '/database.sql', 'zip' => $staging . '/converted.zip', 'entries' => $staging . '/entries.jsonl', 'note' => $staging . '/publish.json' );
	}
	private static function deadline( array $job, $phase ) {
		$cursor = (array) ( $job['checkpoint'][ $phase ] ?? array() );
		return Digitalisimo_Backups_Step_Budget::deadline( Digitalisimo_Backups_Step_Budget::scale( $job, $phase, $cursor ), Digitalisimo_Backups_Step_Budget::slice_cap() );
	}
	private static function space( $dir, $needed, $what ) {
		$free = Digitalisimo_Backups_Archive_Inspector::free_space( $dir );
		if ( null !== $free && $free < $needed + Digitalisimo_Backups_Archive_Inspector::MARGIN ) throw new Digitalisimo_Backups_Permanent_Error( 'No hay espacio en el almacén privado para ' . $what . ': se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed + Digitalisimo_Backups_Archive_Inspector::MARGIN ) . ' libres y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '. Libera espacio y vuelve a importarlo.' );
	}
	/** Los mensajes del lector de tar de Backuply, con el nombre de este formato. */
	private static function webuzo_error( Exception $e ) {
		if ( ! $e instanceof Digitalisimo_Backups_Permanent_Error ) return $e;
		return new Digitalisimo_Backups_Permanent_Error( str_replace( array( 'respaldo de Backuply', 'tar de Backuply' ), array( 'respaldo de Webuzo', 'respaldo de cuenta de Webuzo' ), $e->getMessage() ) );
	}

	/**
	 * Descomprime `$source` (gzip de uno o varios miembros) por tramos y entrega cada trozo a `$sink`, desde `$cursor`
	 * (`offset`: lo ya entregado; `c` y `u`: dónde empieza el miembro en curso, en el comprimido y en lo descomprimido).
	 * Un tramo siguiente vuelve a descomprimir sin entregar lo ya hecho de su miembro (zlib no guarda su estado entre
	 * peticiones). `$safe` dice si el consumidor puede dejarlo ahí. Devuelve el cursor y si terminó. Tope de expansión.
	 */
	private static function inflate( array $job, $phase, $source, array $cursor, $cap, callable $sink, callable $safe, $what ) {
		$cursor += array( 'offset' => 0, 'c' => 0, 'u' => 0 );
		$unbounded = self::unbounded();
		// En la web, volver a descomprimir lo ya hecho de este miembro no puede comerse la petición: pasada la mitad de su
		// tiempo (filtro digitalisimo_backups_webuzo_skip_share), falla pronto y manda a WP-CLI.
		$window = Digitalisimo_Backups_Jobs::step_deadline() - microtime( true );
		$share = (float) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_webuzo_skip_share', 0.5 ) : 0.5 );
		$skip_until = $unbounded || ! is_finite( $window ) ? INF : microtime( true ) + max( 0.0, $share ) * max( 1.0, $window );
		$beat = 0;
		if ( ! function_exists( 'inflate_init' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El servidor necesita la extensión PHP zlib para leer un respaldo de Webuzo (.tar.gz).' );
		$in = @fopen( $source, 'rb' );
		if ( ! $in ) throw new Exception( 'No se pudo abrir ' . $what . '.' );
		$total = (int) @filesize( $source );
		$offset = (int) $cursor['offset'];
		$member = (int) $cursor['c'];
		$member_at = (int) $cursor['u'];
		$skip = $offset - $member_at;
		$units = 0;
		$deadline = null;
		$damaged = function( $where ) use ( $what ) { return new Digitalisimo_Backups_Permanent_Error( ucfirst( $what ) . ' está dañado o truncado: falló la descompresión gzip cerca del byte ' . $where . '.' ); };
		try {
			if ( $skip < 0 || 0 !== fseek( $in, $member ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La descompresión no coincide con su punto de control.', $phase );
			$context = inflate_init( ZLIB_ENCODING_GZIP );
			$fed = 0;
			while ( true ) {
				if ( ZLIB_STREAM_END === inflate_get_status( $context ) ) {
					$member += (int) inflate_get_read_len( $context );
					$member_at = $offset - $skip;
					if ( 0 !== fseek( $in, $member ) ) throw $damaged( $member );
					if ( "\x1f\x8b" !== (string) fread( $in, 2 ) ) break;
					fseek( $in, $member );
					$context = inflate_init( ZLIB_ENCODING_GZIP );
					$fed = 0;
				}
				$piece = fread( $in, 4096 );
				if ( false === $piece ) throw new Exception( 'No se pudo leer ' . $what . '.' );
				if ( '' === $piece ) throw $damaged( $member + $fed );
				$fed += strlen( $piece );
				$chunk = @inflate_add( $context, $piece, ZLIB_SYNC_FLUSH );
				if ( false === $chunk ) throw $damaged( $member + $fed );
				if ( $skip > 0 ) {
					$drop = min( $skip, strlen( $chunk ) );
					$chunk = (string) substr( $chunk, $drop );
					$skip -= $drop;
					if ( $skip > 0 && microtime( true ) > $skip_until ) throw new Digitalisimo_Backups_Permanent_Error( self::too_big_for_web( $job ) );
					if ( $skip > 0 ) continue;
				}
				// WP-CLI y el cron del sistema (sin plazo): una sola pasada, con su latido.
				if ( null === $deadline ) $deadline = $unbounded && ! Digitalisimo_Backups_Step_Budget::units() ? INF : self::deadline( $job, $phase );
				if ( $unbounded && ++$beat % 256 === 0 ) Digitalisimo_Backups_Jobs::progress( 'unpack' === $phase ? 10 : 22, 'Leyendo ' . $what . ': ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $offset ) . '…' );
				if ( '' === $chunk ) continue;
				if ( $offset + strlen( $chunk ) > $cap ) throw new Digitalisimo_Backups_Permanent_Error( Digitalisimo_Backups_Backuply_Import::max_tar() <= $cap ? ucfirst( $what ) . ' descomprimido supera ' . Digitalisimo_Backups_Archive_Inspector::megabytes( Digitalisimo_Backups_Backuply_Import::max_tar() ) . ', el máximo que se importa.' : ucfirst( $what ) . ' se descomprime a más de ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $cap ) . ', muchas más veces su tamaño (' . Digitalisimo_Backups_Archive_Inspector::megabytes( $total ) . ') de lo que da un sitio real: no se importa (puede ser una bomba de descompresión). Si es legítimo, sube el tope con el filtro digitalisimo_backups_import_max_ratio.' );
				$sink( $chunk );
				$offset += strlen( $chunk );
				$units++;
				if ( Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) && $safe() ) {
					return array( 'done' => false, 'cursor' => array( 'offset' => $offset, 'c' => $member, 'u' => $member_at ), 'read' => $member + $fed, 'total' => $total );
				}
			}
		} finally {
			fclose( $in );
		}
		return array( 'done' => true, 'cursor' => array( 'offset' => $offset, 'c' => $member, 'u' => $member_at ), 'read' => $total, 'total' => $total );
	}

	/** Sin plazo por petición (WP-CLI, el cron del sistema por PHP CLI): queda más de una hora. */
	private static function unbounded() {
		$left = Digitalisimo_Backups_Jobs::step_deadline() - microtime( true );
		return ! is_finite( $left ) || $left > 3600;
	}
	/** Un `.tar.gz` mayor que esto (comprimido) se avisa en la tarjeta y en REST: por la web puede no caber. */
	public static function web_max() { return (int) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_webuzo_web_max', 2147483648 ) : 2147483648 ); }
	private static function too_big_for_web( array $job ) {
		return 'Este respaldo de Webuzo es demasiado grande para importarlo por la web: es un solo bloque gzip y cada tramo vuelve a descomprimir lo ya leído, que ya no cabe en una petición. No se dejó nada a medias. Impórtalo con WP-CLI, que lo hace en una sola pasada y sin límite de tiempo: wp digitalisimo-backups webuzo import ' . (string) ( $job['spec']['source']['name'] ?? '<respaldo>' ) . ( '' !== (string) ( $job['spec']['site'] ?? '' ) ? ' --site=' . $job['spec']['site'] : '' ) . '.';
	}
	/** Aviso por tamaño para la tarjeta y REST ('' si no hace falta). */
	public static function size_advice( $size ) {
		return (int) $size > self::web_max() ? 'Mide ' . Digitalisimo_Backups_Archive_Inspector::megabytes( (int) $size ) . ': por la web puede no caber (cada tramo vuelve a descomprimir lo ya leído). Impórtalo con WP-CLI: wp digitalisimo-backups webuzo import <respaldo>.' : '';
	}

	/**
	 * Fase `unpack`: el `.tar.gz` en flujo, con el lector de Digitalisimo_Backups_Webuzo_Stream, en una o dos pasadas.
	 * La base (`backuply-data/mysql/…`) va en el primer tramo, antes que el `wp-config.php` que dice cuál es: para
	 * extraerla hay que saber ya qué base es. Por eso primero se **analiza** (salvo que ya haya un análisis de este mismo
	 * archivo): sin WordPress elegido, ninguno falla, uno solo que se puede importar sigue solo y varios terminan el
	 * trabajo con la lista para elegir. Después se **extrae** el elegido: sus archivos y su base.
	 */
	public static function unpack( array $job ) {
		$source = self::source( $job );
		$paths = self::paths( $job );
		$cursor = (array) ( $job['checkpoint']['unpack'] ?? array() );
		$spec_site = (string) ( $job['spec']['site'] ?? '' );
		$total = (int) $job['spec']['source']['size'];
		if ( ! isset( $cursor['pass'] ) ) {
			if ( "\x1f\x8b" !== (string) @file_get_contents( $source, false, null, 0, 2 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El archivo no es un respaldo de Webuzo: no está comprimido con gzip (.tar.gz).' );
			$cursor = array( 'pass' => 'analyze', 'site' => '', 'db' => '' );
			// Con un análisis de este mismo archivo, el elegido se extrae en una sola pasada.
			$known = '' !== $spec_site ? self::analysis( (string) $job['spec']['source']['origin'], (string) $job['spec']['source']['name'], (int) $job['spec']['source']['size'], (int) $job['spec']['source']['mtime'] ) : null;
			if ( is_array( $known ) ) foreach ( (array) $known['sites'] as $item ) if ( is_array( $item ) && ( $item['root'] ?? null ) === $spec_site && ! empty( $item['importable'] ) && is_string( $item['db'] ?? null ) ) $cursor = array( 'pass' => 'extract', 'site' => $spec_site, 'db' => $item['db'] );
		}
		$extract = 'extract' === $cursor['pass'];
		$site = $extract ? (string) $cursor['site'] : '';
		$outputs = $extract ? array( 'files' => $paths['files'], 'index' => $paths['index'], 'gz' => $paths['gz'] ) : array();
		$stream = new Digitalisimo_Backups_Webuzo_Stream( (array) ( $cursor['stream'] ?? array() ) + array( 'db_name' => $extract ? (string) $cursor['db'] : '' ), $site, $outputs );
		$written = $stream->written();
		try {
			$result = self::inflate( $job, 'unpack', $source, (array) ( $cursor['gzip'] ?? array() ), Digitalisimo_Backups_Backuply_Import::max_expansion( $total ), function( $chunk ) use ( $stream, $paths, &$written ) {
				$stream->feed( $chunk );
				if ( $stream->written() - $written >= self::SPACE_EVERY ) { self::space( dirname( $paths['files'] ), self::SPACE_EVERY, 'extraer el WordPress del respaldo de Webuzo' ); $written = $stream->written(); }
			}, function() use ( $stream ) { return $stream->safe(); }, 'el respaldo de Webuzo' );
			$state = $stream->close();
		} catch ( Exception $e ) {
			$stream->abandon();
			throw self::webuzo_error( $e );
		}
		if ( ! $result['done'] ) {
			$message = ( $extract ? 'Extrayendo el WordPress de ' . $site . ': ' : 'Analizando el respaldo de Webuzo: ' ) . (int) $state['entries'] . ' entradas…';
			Digitalisimo_Backups_Jobs::progress( ( $extract ? 10 : 1 ) + (int) min( $extract ? 10 : 9, floor( ( $extract ? 10 : 9 ) * $result['read'] / max( 1, $result['total'] ) ) ), $message, true );
			return array( 'next' => 'unpack', 'checkpoint' => array( 'gzip' => $result['cursor'], 'stream' => $state, 'scale' => Digitalisimo_Backups_Step_Budget::scale( $job, 'unpack', $cursor ) ) + $cursor, 'message' => $message );
		}
		if ( empty( $state['end'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Webuzo está incompleto: el tar no termina con su marca de fin (el panel no llegó a cerrarlo).' );
		if ( (int) $state['dots'] < 1 ) throw new Digitalisimo_Backups_Permanent_Error( 'El archivo no es un respaldo de cuenta de Webuzo: no empieza con los datos de la cuenta (backuply-data).' );
		$sites = Digitalisimo_Backups_Webuzo_Stream::sites( $state );
		$account = Digitalisimo_Backups_Webuzo_Stream::account( $state );
		$find = function( $root ) use ( $sites ) { foreach ( $sites as $item ) if ( $item['root'] === $root ) return $item; return null; };
		if ( ! $extract ) {
			self::remember_analysis( $job, $sites, $account );
			if ( ! $sites ) throw new Digitalisimo_Backups_Permanent_Error( (int) $state['dots'] < 2 ? 'Este respaldo de Webuzo no trae el home de la cuenta (sólo sus datos): no hay ningún WordPress que importar.' : 'Este respaldo de Webuzo no trae ningún WordPress completo (su núcleo: wp-load.php, wp-settings.php, wp-admin y wp-includes).' );
			$target = '' !== $spec_site ? $find( $spec_site ) : ( 1 === count( $sites ) ? $sites[0] : null );
			if ( '' !== $spec_site && ! $target ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Webuzo no trae un WordPress completo en ' . $spec_site . '.' );
			if ( ! $target ) {
				$list = array();
				foreach ( $sites as $item ) $list[] = $item['root'] . ( '' !== $item['domain'] ? ' (' . $item['domain'] . ')' : '' ) . ( $item['importable'] ? '' : ' — no se puede importar: ' . $item['problem'] );
				return array( 'next' => null, 'checkpoint' => array( 'analyzed' => count( $sites ) ), 'result' => array( 'webuzo' => array( 'choose' => true, 'source' => (string) $job['spec']['source']['name'], 'origin' => (string) $job['spec']['source']['origin'], 'sites' => $sites ) ), 'message' => 'Este respaldo de Webuzo trae ' . count( $sites ) . ' WordPress: elige cuál importar en «Respaldo de cuenta de Webuzo». ' . implode( '; ', $list ) . '.' );
			}
			if ( ! $target['importable'] ) throw new Digitalisimo_Backups_Permanent_Error( 'El WordPress de ' . $target['root'] . ' no se puede importar: ' . $target['problem'] );
			// La segunda pasada ya sabe qué base extraer (el análisis no guarda nada grande).
			return array( 'next' => 'unpack', 'checkpoint' => array( 'pass' => 'extract', 'site' => $target['root'], 'db' => $target['db'], 'auto' => '' === $spec_site ), 'message' => ( '' === $spec_site ? 'Un solo WordPress en el respaldo (' . $target['root'] . '): extrayéndolo…' : 'Extrayendo el WordPress de ' . $target['root'] . '…' ) );
		}
		$chosen = $find( $site );
		if ( ! $chosen ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Webuzo no trae un WordPress completo en ' . $site . '.' );
		if ( ! $chosen['importable'] ) throw new Digitalisimo_Backups_Permanent_Error( 'El WordPress de ' . $site . ' no se puede importar: ' . $chosen['problem'] );
		if ( $chosen['db'] !== (string) $cursor['db'] || empty( $state['db_done'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos ' . $chosen['db'] . ' del WordPress de ' . $site . ' no se extrajo del respaldo (el análisis guardado no coincide): vuelve a importarlo.' );
		$checkpoint = array( 'pass' => 'done', 'site' => $site, 'db' => $chosen['db'], 'auto' => ! empty( $cursor['auto'] ), 'chosen' => $chosen, 'account' => $account, 'nested' => Digitalisimo_Backups_Webuzo_Stream::nested( $state, $site ), 'foreign' => Digitalisimo_Backups_Webuzo_Stream::foreign( $state, $site ), 'out' => $state['out'], 'files' => (int) $state['x_files'], 'bytes' => (int) $state['x_bytes'], 'skipped' => (array) $state['x_skipped'], 'sites' => count( $sites ) );
		return array( 'next' => 'scan', 'checkpoint' => $checkpoint, 'message' => 'Descomprimiendo la base de datos ' . $chosen['db'] . '…' );
	}

	/** Fase `scan`: la base `.sql.gz` extraída se descomprime a `source.sql` por tramos (tope de expansión). */
	public static function scan( array $job ) {
		$paths = self::paths( $job );
		$unpack = (array) $job['checkpoint']['unpack'];
		$gz_size = (int) ( $unpack['out']['gz'] ?? 0 );
		self::fit( $paths['gz'], $gz_size, 'La base extraída no coincide con su punto de control.', 'unpack' );
		$cursor = (array) ( $job['checkpoint']['scan'] ?? array() );
		$offset = (int) ( $cursor['gzip']['offset'] ?? 0 );
		if ( 0 === $offset ) {
			if ( "\x1f\x8b" !== (string) @file_get_contents( $paths['gz'], false, null, 0, 2 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos del respaldo de Webuzo no está comprimida con gzip (.sql.gz).' );
			self::space( dirname( $paths['sql_in'] ), $gz_size * 4, 'descomprimir la base de datos del respaldo de Webuzo' );
		}
		$out = self::append( $paths['sql_in'], $offset );
		$checked = $offset;
		$prefix = (string) $unpack['chosen']['prefix'];
		$others = (array) ( $cursor['others'] ?? array() );
		$tail = (string) base64_decode( (string) ( $cursor['tail'] ?? '' ) );
		try {
			$result = self::inflate( $job, 'scan', $paths['gz'], (array) ( $cursor['gzip'] ?? array() ), Digitalisimo_Backups_Backuply_Import::max_expansion( $gz_size ), function( $chunk ) use ( $out, $paths, &$checked, $prefix, &$others, &$tail ) {
				// Otro WordPress de la misma base cuyo prefijo empieza por el del elegido (`wp_old_` de uno borrado junto a
				// `wp_`): su tabla `<prefijo>options` lo delata. Las tablas `<prefijo><n>_` son de los sitios de una red.
				$text = $tail . $chunk;
				if ( preg_match_all( '/CREATE TABLE `([A-Za-z0-9_$]{1,64})options` \(/', $text, $found ) ) foreach ( $found[1] as $other ) if ( $other !== $prefix && 0 === strpos( $other, $prefix ) && ! preg_match( '/^' . preg_quote( $prefix, '/' ) . '[0-9]+_$/D', $other ) && count( $others ) < 50 ) $others[ $other ] = $other;
				$tail = (string) substr( $text, -96 );
				self::put( $out, $chunk );
				if ( (int) ftell( $out ) - $checked >= self::SPACE_EVERY ) { self::space( dirname( $paths['sql_in'] ), self::SPACE_EVERY, 'descomprimir la base de datos del respaldo de Webuzo' ); $checked = (int) ftell( $out ); }
			}, function() { return true; }, 'la base de datos del respaldo de Webuzo' );
			self::sync( $out );
		} finally {
			fclose( $out );
		}
		if ( ! $result['done'] ) {
			Digitalisimo_Backups_Jobs::progress( 20 + (int) min( 5, floor( 5 * $result['read'] / max( 1, $result['total'] ) ) ), 'Descomprimiendo la base de datos: ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $result['cursor']['offset'] ) . '…', true );
			return array( 'next' => 'scan', 'checkpoint' => array( 'gzip' => $result['cursor'], 'others' => $others, 'tail' => base64_encode( $tail ), 'scale' => Digitalisimo_Backups_Step_Budget::scale( $job, 'scan', $cursor ) ), 'message' => 'Descomprimiendo la base de datos del respaldo de Webuzo…' );
		}
		if ( (int) $result['cursor']['offset'] < 16 ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos del respaldo de Webuzo está vacía o truncada.' );
		// Disco: la base comprimida ya no hace falta (si una fase posterior se repite, se vuelve a extraer).
		@unlink( $paths['gz'] );
		return array( 'next' => 'database', 'checkpoint' => array( 'sql_size' => (int) $result['cursor']['offset'], 'others' => array_values( $others ) ), 'message' => 'Convirtiendo la base de datos del respaldo de Webuzo…' );
	}

	/**
	 * Fase `database`: el mysqldump de MariaDB pasa al SQL del exportador con el traductor de Backuply: sin la línea
	 * sandbox, `CREATE DATABASE` ni `USE` (sólo de su base), sólo las tablas del prefijo del WordPress elegido (las de otro
	 * WordPress de la misma base se cuentan y se avisan) y con el guardián SQL en cada CREATE.
	 */
	public static function database( array $job ) {
		$paths = self::paths( $job );
		$unpack = (array) $job['checkpoint']['unpack'];
		$chosen = (array) $unpack['chosen'];
		$size = (int) ( $job['checkpoint']['scan']['sql_size'] ?? 0 );
		self::fit( $paths['sql_in'], $size, 'El SQL descomprimido no coincide con su punto de control.', 'scan' );
		$cursor = (array) ( $job['checkpoint']['database'] ?? array() );
		$deadline = self::deadline( $job, 'database' );
		$in = @fopen( $paths['sql_in'], 'rb' );
		$out = @fopen( $paths['sql'], 'c+b' );
		if ( ! $in || ! $out ) { if ( $in ) fclose( $in ); if ( $out ) fclose( $out ); throw new Exception( 'No se pudo leer el área de trabajo de la importación.' ); }
		@chmod( $paths['sql'], 0600 );
		try {
			if ( ! ftruncate( $out, (int) ( $cursor['out'] ?? 0 ) ) || 0 !== fseek( $out, (int) ( $cursor['out'] ?? 0 ) ) ) throw new Exception( 'No se pudo preparar el SQL convertido.' );
			$translator = new Digitalisimo_Backups_Backuply_Sql( $in, 0, $size, $out, $cursor, array( 'label' => 'Webuzo', 'cli' => 'wp digitalisimo-backups webuzo import', 'dialect' => 'mysqldump', 'database' => (string) $chosen['db'], 'prefix' => (string) $chosen['prefix'], 'exclude' => array_values( array_unique( array_merge( (array) $unpack['foreign'], (array) ( $job['checkpoint']['scan']['others'] ?? array() ) ) ) ), 'capture' => array( 'home', 'siteurl' ) ) );
			$done = $translator->run( $deadline );
			$state = $translator->state();
		} finally {
			fclose( $in );
			fclose( $out );
		}
		$state['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'database', $cursor );
		if ( ! $done ) {
			Digitalisimo_Backups_Jobs::progress( 25 + (int) floor( 25 * (int) $state['pos'] / max( 1, $size ) ), 'Convirtiendo la base de datos ' . $chosen['db'] . ': ' . count( (array) $state['tables'] ) . ' tablas, ' . (int) $state['rows'] . ' filas…', true );
			return array( 'next' => 'database', 'checkpoint' => $state, 'message' => 'Convirtiendo la base de datos del respaldo de Webuzo…' );
		}
		$state['tables'] = array();
		$prefix = (string) $chosen['prefix'];
		if ( ! in_array( $prefix . 'options', (array) $state['order'], true ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos ' . $chosen['db'] . ' no trae la tabla ' . $prefix . 'options del prefijo de su wp-config.php: no es la base completa de ese WordPress.' );
		$state['prefix'] = $prefix;
		$state['skip'] = array();
		// Disco: el SQL descomprimido ya está convertido.
		@unlink( $paths['sql_in'] );
		return array( 'next' => 'files', 'checkpoint' => $state, 'message' => 'Base de datos convertida; escribiendo el ZIP…' );
	}

	/**
	 * Fase `files`: el ZIP con el escritor de sólo añadir de T2: `database.sql`, el marcador de `wp-config.php` y cada
	 * archivo extraído como `wordpress/<ruta>` (menos los de otros WordPress anidados, que se descubren al final de la
	 * extracción). Una entrada grande puede quedar a medias entre tramos.
	 */
	public static function files( array $job ) {
		$paths = self::paths( $job );
		$unpack = (array) $job['checkpoint']['unpack'];
		$nested = (array) ( $unpack['nested'] ?? array() );
		$cursor = (array) ( $job['checkpoint']['files'] ?? array() ) + array( 'zip' => 0, 'entries' => 0, 'index' => 0, 'count' => 0, 'bytes' => 0, 'partial' => null, 'sql' => false, 'config' => false, 'nested' => 0 );
		self::fit( $paths['files'], (int) $unpack['out']['files'], 'Los archivos extraídos no coinciden con su punto de control.', 'unpack' );
		self::fit( $paths['index'], (int) $unpack['out']['index'], 'El índice de la extracción no coincide con su punto de control.', 'unpack' );
		if ( 0 === (int) $cursor['zip'] ) self::space( dirname( $paths['zip'] ), (int) $unpack['bytes'] + (int) @filesize( $paths['sql'] ), 'escribir el ZIP convertido' );
		$deadline = self::deadline( $job, 'files' );
		$zip = null;
		$entries = null;
		$units = 0;
		try {
			$zip = new Digitalisimo_Backups_Zip_Writer( $paths['zip'], (int) $cursor['zip'] );
			$entries = self::append( $paths['entries'], (int) $cursor['entries'] );
			$handles = array( 'files' => @fopen( $paths['files'], 'rb' ), 'sql' => @fopen( $paths['sql'], 'rb' ), 'index' => @fopen( $paths['index'], 'rb' ) );
			try {
				if ( ! $handles['files'] || ! $handles['sql'] || ! $handles['index'] ) throw new Exception( 'No se pudo leer el área de trabajo de la importación.' );
				if ( is_array( $cursor['partial'] ) ) {
					$state = $cursor['partial'];
					$cursor['partial'] = null;
					$zip->resume( $state['zip'] );
					$raw = (int) $state['zip']['raw'];
					if ( ! self::copy( $zip, $handles[ $state['src'] ], (int) $state['o'] + $raw, (int) $state['s'] - $raw, $cursor, $deadline, $state ) ) return self::files_slice( $job, $zip, $entries, $cursor );
					self::done( $zip, $entries, $cursor );
					if ( 'sql' === $state['src'] ) $cursor['sql'] = true;
					else $cursor['index'] = (int) $state['next'];
				}
				if ( ! $cursor['sql'] ) {
					$sql_size = (int) $job['checkpoint']['database']['out'];
					self::fit( $paths['sql'], $sql_size, 'El SQL convertido no coincide con su punto de control.', 'database' );
					$zip->begin( 'database.sql', time(), 0600, $sql_size, Digitalisimo_Backups_Zip_Writer::method_for( 'database.sql', $sql_size ) );
					if ( ! self::copy( $zip, $handles['sql'], 0, $sql_size, $cursor, $deadline, array( 'src' => 'sql', 'o' => 0, 's' => $sql_size, 'next' => 0 ) ) ) return self::files_slice( $job, $zip, $entries, $cursor );
					self::done( $zip, $entries, $cursor );
					$cursor['sql'] = true;
				}
				if ( ! $cursor['config'] ) {
					$zip->begin( 'wordpress/wp-config.php', time(), 0600, strlen( self::CONFIG_PLACEHOLDER ), 0 );
					$zip->data( self::CONFIG_PLACEHOLDER );
					self::done( $zip, $entries, $cursor );
					$cursor['config'] = true;
				}
				$limit = (int) $unpack['out']['index'];
				fseek( $handles['index'], (int) $cursor['index'] );
				while ( (int) $cursor['index'] < $limit ) {
					if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) return self::files_slice( $job, $zip, $entries, $cursor );
					$line = fgets( $handles['index'] );
					if ( false === $line ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El índice de la extracción está truncado.', 'unpack' );
					$next = (int) $cursor['index'] + strlen( $line );
					$entry = json_decode( $line, true );
					if ( ! is_array( $entry ) || ! isset( $entry['n'], $entry['o'], $entry['s'] ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El índice de la extracción está dañado.', 'unpack' );
					$units++;
					$inside = false;
					foreach ( $nested as $dir ) if ( 0 === strpos( (string) $entry['n'] . '/', $dir . '/' ) ) { $inside = true; break; }
					if ( $inside ) { $cursor['nested']++; $cursor['index'] = $next; continue; }
					$name = 'wordpress/' . $entry['n'];
					$zip->begin( $name, (int) $entry['t'], (int) $entry['m'], (int) $entry['s'], Digitalisimo_Backups_Zip_Writer::method_for( $name, (int) $entry['s'] ) );
					if ( ! self::copy( $zip, $handles['files'], (int) $entry['o'], (int) $entry['s'], $cursor, $deadline, array( 'src' => 'files', 'o' => (int) $entry['o'], 's' => (int) $entry['s'], 'next' => $next ) ) ) return self::files_slice( $job, $zip, $entries, $cursor );
					self::done( $zip, $entries, $cursor );
					$cursor['index'] = $next;
				}
			} finally {
				foreach ( $handles as $handle ) if ( $handle ) fclose( $handle );
			}
			$zip->commit();
			self::sync( $entries );
			$cursor['zip'] = $zip->size();
			$cursor['entries'] = (int) ftell( $entries );
		} finally {
			if ( $zip ) $zip->close();
			if ( $entries ) fclose( $entries );
		}
		return array( 'next' => 'finish', 'checkpoint' => $cursor, 'message' => 'Escribiendo el manifiesto del ZIP convertido…' );
	}
	private static function files_slice( array $job, $zip, $entries, array $cursor ) {
		$zip->commit();
		self::sync( $entries );
		$cursor['zip'] = is_array( $cursor['partial'] ) ? (int) $cursor['partial']['zip']['p'] + (int) $cursor['partial']['zip']['z'] : $zip->size();
		$cursor['entries'] = (int) ftell( $entries );
		$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'files', (array) ( $job['checkpoint']['files'] ?? array() ) );
		$total = max( 1, (int) ( $job['checkpoint']['unpack']['bytes'] ?? 1 ) );
		Digitalisimo_Backups_Jobs::progress( 50 + (int) floor( 44 * min( 1, (int) $cursor['bytes'] / $total ) ), 'Escribiendo el ZIP convertido: ' . (int) $cursor['count'] . ' archivos…', true );
		return array( 'next' => 'files', 'checkpoint' => $cursor, 'message' => 'Escribiendo el ZIP convertido…' );
	}
	/** Copia `$length` bytes desde `$from` a la entrada abierta; con el plazo vencido la deja suspendida y devuelve false. */
	private static function copy( $zip, $handle, $from, $length, array &$cursor, $deadline, array $state ) {
		if ( $length <= 0 ) return true;
		if ( 0 !== fseek( $handle, $from ) ) throw new Exception( 'No se pudo leer el área de trabajo de la importación.' );
		$left = $length;
		while ( $left > 0 ) {
			$chunk = fread( $handle, (int) min( Digitalisimo_Backups_Zip_Writer::CHUNK, $left ) );
			if ( false === $chunk || '' === $chunk ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'Un archivo extraído es más corto de lo que dice su índice.', 'unpack' );
			$left -= strlen( $chunk );
			$cursor['bytes'] += strlen( $chunk );
			if ( $left > 0 && is_finite( $deadline ) && Digitalisimo_Backups_Step_Budget::exhausted( $deadline ) ) {
				$state['zip'] = $zip->suspend( $chunk );
				$cursor['partial'] = $state;
				return false;
			}
			$zip->data( $chunk );
		}
		return true;
	}
	private static function done( $zip, $entries, array &$cursor ) {
		self::put( $entries, wp_json_encode( $zip->finish() ) . "\n" );
		$cursor['count']++;
	}

	/**
	 * Fase `finish`: manifiesto v3 sin firma, comprobación con el inspector de archivos no confiables, publicación con
	 * rename() y su fila «Importado» en la lista de respaldos.
	 */
	public static function finish( array $job ) {
		$paths = self::paths( $job );
		$spec = (array) $job['spec'];
		$storage = trailingslashit( Digitalisimo_Backups::storage_directory() );
		$noted = is_file( $paths['note'] ) ? json_decode( (string) @file_get_contents( $paths['note'], false, null, 0, 4096 ), true ) : null;
		if ( is_array( $noted ) && is_string( $noted['file'] ?? null ) && preg_match( '/^digitalisimo-webuzo-[A-Za-z0-9._-]+\.zip$/D', $noted['file'] ) && ! is_file( $paths['zip'] ) ) {
			clearstatcache( true, $storage . $noted['file'] );
			if ( is_file( $storage . $noted['file'] ) && (int) filesize( $storage . $noted['file'] ) === (int) ( $noted['size'] ?? -1 ) ) return self::published( $job, $noted['file'] );
		}
		$unpack = (array) $job['checkpoint']['unpack'];
		$database = (array) $job['checkpoint']['database'];
		$files = (array) $job['checkpoint']['files'];
		$meta = self::meta( $job );
		$zip = new Digitalisimo_Backups_Zip_Writer( $paths['zip'], (int) $files['zip'] );
		try {
			$totals = array( 'count' => 0, 'bytes' => 0 );
			foreach ( Digitalisimo_Backups_Packer::records( $paths['entries'], (int) $files['entries'] ) as $record ) { $totals['count']++; $totals['bytes'] += (int) $record['s']; }
			if ( $totals['count'] !== (int) $files['count'] ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La lista de entradas del ZIP convertido no coincide con su punto de control.', 'files' );
			$notices = self::notices( $job );
			$created = self::name_time( (string) $spec['source']['name'] ) ?: (int) $spec['source']['mtime'];
			$head = array(
				'format' => 'digitalisimo-backup/v3',
				'backup_format_version' => 3,
				'backup_id' => wp_generate_uuid4(),
				'scope' => 'installation',
				'type' => 'full',
				'created_at' => gmdate( 'c', $created ),
				'completed_at' => gmdate( 'c' ),
				'home_url' => $meta['home'],
				'site_url' => $meta['siteurl'],
				'wordpress_version' => (string) $unpack['chosen']['version'],
				'php_version' => '',
				'database_version' => '',
				'database_name' => '',
				'database_size' => (int) $database['out'],
				'database_consistency' => 'mysqldump',
				'table_prefix' => (string) $database['prefix'],
				'origin' => self::origin( $meta, (string) $database['prefix'] ),
				'database_tables' => array_values( (array) $database['order'] ),
				'multisite' => $meta['multisite'],
				'file_count' => $totals['count'] - 1,
				'uncompressed_size' => $totals['bytes'],
				'checksum_algorithm' => 'sha256',
				'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '',
				'imported_from' => array(
					'format' => self::FORMAT,
					'name' => (string) $spec['source']['name'],
					'file' => (string) $spec['source']['name'],
					'size' => (int) $spec['source']['size'],
					'account' => (string) ( $unpack['account']['user'] ?? '' ),
					'site' => (string) $unpack['site'],
					'domain' => (string) $unpack['chosen']['domain'],
					'database' => (string) $unpack['chosen']['db'],
					'site_url' => $meta['home'],
					'site_path' => $meta['abspath'],
					'trust' => 'untrusted',
				),
			);
			$tail = array( 'job_id' => (string) $job['id'], 'packer' => self::PACKER, 'database_snapshot_at' => gmdate( 'c', $created ) );
			if ( $notices ) $tail['notices'] = $notices;
			Digitalisimo_Backups_Jobs::progress( 95, 'Escribiendo el manifiesto del ZIP convertido…', true );
			$zip->begin( 'manifest.json', time(), 0644, 0, function_exists( 'deflate_init' ) ? 8 : 0 );
			foreach ( Digitalisimo_Backups_Packer::manifest_pieces( $head, $paths['entries'], (int) $files['entries'], $tail ) as $piece ) $zip->data( $piece );
			$manifest = $zip->finish();
			$entries = self::append( $paths['entries'], (int) $files['entries'] );
			try {
				self::put( $entries, wp_json_encode( $manifest ) . "\n" );
				self::sync( $entries );
				$size = (int) ftell( $entries );
			} finally {
				fclose( $entries );
			}
			$count = $zip->close_archive( function() use ( $paths, $size ) { return Digitalisimo_Backups_Packer::records( $paths['entries'], $size ); }, '' );
			$zip->commit();
		} finally {
			$zip->close();
		}
		if ( $count !== $totals['count'] + 1 ) throw new Exception( 'El directorio central del ZIP convertido no tiene todas las entradas.' );
		self::check_zip( $paths['zip'] );
		$note = $paths['note'];
		$file = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $paths, $storage, $spec, $note ) {
			$file = self::free_name( (string) $spec['file'] );
			clearstatcache( true, $paths['zip'] );
			if ( false === @file_put_contents( $note, wp_json_encode( array( 'file' => $file, 'size' => (int) filesize( $paths['zip'] ) ) ) ) ) throw new Exception( 'No se pudo anotar la publicación del ZIP convertido.' );
			if ( ! @rename( $paths['zip'], $storage . $file ) ) throw new Exception( 'No se pudo mover el ZIP convertido al almacén privado.' );
			@chmod( $storage . $file, 0600 );
			return $file;
		} );
		return self::published( $job, $file );
	}
	/** Direcciones, ruta y red del WordPress importado, de lo que guardan los puntos de control. */
	private static function meta( array $job ) {
		$unpack = (array) ( $job['checkpoint']['unpack'] ?? array() );
		$database = (array) ( $job['checkpoint']['database'] ?? array() );
		$chosen = (array) ( $unpack['chosen'] ?? array() );
		$captured = (array) ( $database['captured'] ?? array() );
		$url = function( $value ) { $value = rtrim( trim( (string) $value ), '/' ); return preg_match( '#^https?://[^\s"\'<>\\\\]{1,2000}$#iD', $value ) ? $value : ''; };
		$home = $url( $captured['home'] ?? '' );
		$siteurl = $url( $captured['siteurl'] ?? '' );
		// Sin las opciones en el volcado: el dominio del panel (con https si lo fuerza) y la subcarpeta.
		if ( '' === $home ) $home = '' !== $siteurl ? $siteurl : ( '' !== (string) ( $chosen['domain'] ?? '' ) ? $url( ( empty( $chosen['http'] ) ? 'https://' : 'http://' ) . $chosen['domain'] ) : '' );
		if ( '' === $home ) $home = 'http://webuzo.invalid';
		if ( '' === $siteurl ) $siteurl = $home;
		$homedir = (string) ( $unpack['account']['homedir'] ?? '' );
		$order = (array) ( $database['order'] ?? array() );
		$prefix = (string) ( $database['prefix'] ?? ( $chosen['prefix'] ?? '' ) );
		$multisite = ! empty( $chosen['multisite'] ) || ( in_array( $prefix . 'blogs', $order, true ) && in_array( $prefix . 'site', $order, true ) );
		return array( 'home' => $home, 'siteurl' => $siteurl, 'abspath' => '' !== $homedir ? $homedir . '/' . (string) ( $unpack['site'] ?? '' ) : '', 'multisite' => $multisite, 'subdomain' => ! empty( $chosen['subdomain'] ), 'network' => (array) ( $chosen['network'] ?? array() ) );
	}
	/** Bloque `origin` del manifiesto (el de Digitalisimo_Backups_Relocate::describe()). */
	private static function origin( array $meta, $prefix ) {
		$path = (string) $meta['abspath'];
		$out = array(
			'version' => Digitalisimo_Backups_Relocate::VERSION,
			'home' => (string) $meta['home'],
			'siteurl' => (string) $meta['siteurl'],
			'abspath' => $path,
			'abspath_real' => '',
			'content_dir' => '' === $path ? '' : $path . '/wp-content',
			'uploads_dir' => '' === $path ? '' : $path . '/wp-content/uploads',
			'prefix' => $prefix,
			'multisite' => (bool) $meta['multisite'],
			'subdomain' => (bool) $meta['subdomain'],
		);
		if ( $meta['multisite'] && ! empty( $meta['network']['domain'] ) ) $out['network'] = $meta['network'];
		return $out;
	}
	/** La fila «Importado» en la lista (si no estaba ya: tras una muerte, al volver) y el resultado del trabajo. */
	private static function published( array $job, $file ) {
		$unpack = (array) ( $job['checkpoint']['unpack'] ?? array() );
		$database = (array) ( $job['checkpoint']['database'] ?? array() );
		$meta = self::meta( $job );
		$notices = self::notices( $job );
		$created = self::name_time( (string) $job['spec']['source']['name'] ) ?: (int) $job['spec']['source']['mtime'];
		$row = Digitalisimo_Backups::remember_imported( trailingslashit( Digitalisimo_Backups::storage_directory() ) . $file, array(
			'format' => self::FORMAT,
			'source' => (string) $job['spec']['source']['name'] . ( '' !== (string) ( $unpack['site'] ?? '' ) ? ' → ' . $unpack['site'] : '' ),
			'origin' => (string) $job['spec']['source']['origin'],
			'home' => $meta['home'],
			'multisite' => $meta['multisite'],
			'created_at' => gmdate( 'c', $created ),
			'via' => (string) ( $job['spec']['origin']['via'] ?? '' ),
			'user_id' => (int) ( $job['spec']['origin']['user_id'] ?? 0 ),
			'notices' => $notices,
		) );
		$order = (array) ( $database['order'] ?? array() );
		$result = array( 'import' => array( 'file' => $file, 'source' => (string) $job['spec']['source']['name'], 'origin' => (string) $job['spec']['source']['origin'], 'format' => self::FORMAT, 'site' => (string) ( $unpack['site'] ?? '' ), 'domain' => (string) ( $unpack['chosen']['domain'] ?? '' ), 'files' => (int) ( $job['checkpoint']['files']['count'] ?? 0 ), 'tables' => count( $order ), 'rows' => (int) ( $database['rows'] ?? 0 ), 'prefix' => (string) ( $database['prefix'] ?? '' ), 'site_url' => $meta['home'], 'multisite' => $meta['multisite'], 'notices' => $notices, 'listed' => is_array( $row ), 'confirmation' => 'RESTAURAR SIN FIRMA' ) );
		return array( 'next' => null, 'checkpoint' => array( 'published' => $file ), 'result' => $result, 'message' => 'WordPress de ' . (string) ( $unpack['site'] ?? '' ) . ' importado del respaldo de Webuzo como ' . $file . ': está en la lista de respaldos («Importado»); para restaurarlo escribe RESTAURAR SIN FIRMA.' . ( $notices ? ' Avisos: ' . implode( ' ', $notices ) : '' ) );
	}
	/** Avisos de la importación (manifiesto, fila y tarjeta del trabajo). */
	public static function notices( array $job ) {
		$unpack = (array) ( $job['checkpoint']['unpack'] ?? array() );
		$database = (array) ( $job['checkpoint']['database'] ?? array() );
		$notes = (array) ( $database['notes'] ?? array() );
		$out = Digitalisimo_Backups_Backuply_Import::notices( $notes, array() );
		if ( ! empty( $notes['foreign'] ) ) $out[] = (int) $notes['foreign'] . ' tabla(s) de la base que no son del prefijo ' . (string) ( $unpack['chosen']['prefix'] ?? '' ) . ' (otro WordPress u otra aplicación en la misma base) no se importan.';
		$skipped = (array) ( $unpack['skipped'] ?? array() );
		if ( ! empty( $unpack['account']['meta_error'] ) ) $out[] = 'No se pudo leer ' . implode( ' ni ', (array) $unpack['account']['meta_error'] ) . ' de backuply-data: sin el home ni los dominios de la cuenta, no se excluyen las carpetas de otros dominios ni se reubican las rutas del servidor de origen.';
		if ( ! empty( $job['checkpoint']['scan']['others'] ) ) $out[] = 'La base trae tablas de otro WordPress con un prefijo que empieza por el de éste (' . implode( ', ', array_slice( (array) $job['checkpoint']['scan']['others'], 0, 5 ) ) . '): no se importan.';
		$out[] = 'Del respaldo de la cuenta sólo se importa el WordPress de ' . (string) ( $unpack['site'] ?? '' ) . ' y su base: nada del correo, los certificados, el DNS, el FTP ni el resto del home.';
		$out[] = 'No se importa el wp-config.php del respaldo (lleva las credenciales de su servidor): la restauración nunca escribe wp-config.php.';
		$nested = (int) ( $job['checkpoint']['files']['nested'] ?? 0 ) + (int) ( $skipped['domain'] ?? 0 );
		if ( $nested ) $out[] = $nested . ' archivo(s) de otros sitios dentro de su carpeta (otro WordPress o la carpeta de otro dominio de la cuenta) no se importan: impórtalos aparte.';
		if ( ! empty( $skipped['backup'] ) ) $out[] = (int) $skipped['backup'] . ' respaldo(s) anidado(s) dentro de su carpeta no se importan.';
		if ( ! empty( $skipped['config'] ) ) $out[] = (int) $skipped['config'] . ' wp-config.php de subcarpetas no se importan.';
		if ( ! empty( $skipped['excluded'] ) ) $out[] = (int) $skipped['excluded'] . ' archivo(s) de carpetas que Backups no respalda (otros plugins de respaldo, la caché, registros) no se importan.';
		if ( ! empty( $skipped['symlinks'] ) ) $out[] = (int) $skipped['symlinks'] . ' enlace(s) simbólico(s) de su carpeta no se importan (Backups no sigue ni crea enlaces).';
		return $out;
	}
	private static function free_name( $wanted ) {
		$storage = trailingslashit( Digitalisimo_Backups::storage_directory() );
		$known = array_flip( array_map( 'strval', array_column( (array) get_option( Digitalisimo_Backups::HISTORY, array() ), 'file' ) ) );
		$free = function( $candidate ) use ( $storage, $known ) { return ! file_exists( $storage . $candidate ) && ! is_link( $storage . $candidate ) && ! isset( $known[ $candidate ] ); };
		if ( $free( $wanted ) ) return $wanted;
		$stem = substr( $wanted, 0, -4 );
		do { $candidate = $stem . '-' . bin2hex( random_bytes( 3 ) ) . '.zip'; } while ( ! $free( $candidate ) );
		return $candidate;
	}
	private static function check_zip( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) return;
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) throw new Exception( 'El ZIP convertido no se puede abrir.' );
		try {
			Digitalisimo_Backups_Archive_Inspector::entries( $zip, false );
			Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
		} catch ( Exception $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Webuzo no se puede convertir: ' . $e->getMessage() );
		} finally {
			$zip->close();
		}
	}

	/* ------------------------------------------------------------------ Utilidades */

	private static function put( $handle, $bytes ) {
		$length = strlen( $bytes );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $handle, 0 === $offset ? $bytes : substr( $bytes, $offset ) );
			if ( false === $written || 0 === $written ) throw new Exception( 'No se pudo escribir en el área de trabajo de la importación; revisa el espacio libre del almacén privado.' );
			$offset += $written;
		}
	}
	private static function fit( $path, $size, $message, $phase ) {
		clearstatcache( true, $path );
		$actual = is_file( $path ) && ! is_link( $path ) ? (int) filesize( $path ) : -1;
		if ( $actual < (int) $size ) throw new Digitalisimo_Backups_Checkpoint_Invalid( $message, $phase );
		if ( $actual > (int) $size ) {
			$handle = @fopen( $path, 'r+b' );
			if ( ! $handle || ! ftruncate( $handle, (int) $size ) ) { if ( $handle ) fclose( $handle ); throw new Exception( 'No se pudo preparar el área de trabajo de la importación.' ); }
			fclose( $handle );
		}
	}
	private static function sync( $handle ) {
		if ( ! fflush( $handle ) ) throw new Exception( 'No se pudo guardar el avance de la importación en el área de trabajo.' );
		if ( function_exists( 'fsync' ) ) @fsync( $handle );
	}
	private static function append( $path, $size ) {
		$handle = @fopen( $path, 'c+b' );
		if ( ! $handle ) throw new Exception( 'No se pudo abrir ' . basename( $path ) . ' en el área de trabajo.' );
		@chmod( $path, 0600 );
		if ( ! ftruncate( $handle, (int) $size ) || 0 !== fseek( $handle, (int) $size ) ) {
			fclose( $handle );
			throw new Exception( 'No se pudo preparar ' . basename( $path ) . ' en el área de trabajo.' );
		}
		return $handle;
	}
}

/**
 * Lector en flujo del tar de una cuenta de Webuzo: recibe lo descomprimido a trozos, comprueba cada cabecera con el
 * lector de Backuply y anota lo que hace falta para elegir y extraer un WordPress. Su estado (`$s`) se guarda en el punto
 * de control; sólo se puede guardar en un punto «seguro» (safe()): nunca a mitad de una cabecera ni de un archivo que se
 * está leyendo para analizarlo (los dos son pequeños y se terminan en el tramo siguiente).
 */
final class Digitalisimo_Backups_Webuzo_Stream {
	public $s;
	private $site;
	private $buffer = '';
	private $grab = '';
	private $handles = array();
	private $paths;
	private $rules = null;

	/** Tope de entradas del recorrido de toda la cuenta: 20 veces el de un respaldo (filtro digitalisimo_backups_webuzo_max_walk). */
	public static function max_walk() {
		$max = 20 * Digitalisimo_Backups_Archive_Inspector::max_entries();
		return max( 1, (int) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_webuzo_max_walk', $max ) : $max ) );
	}
	public function __construct( array $state, $site, array $paths ) {
		$this->s = $state + array( 'pos' => 0, 'mode' => 'h', 'left' => 0, 'pad' => 0, 'act' => 'skip', 'cur' => array(), 'long' => null, 'pax_path' => null, 'pax_size' => null, 'chain' => 0, 'zeros' => 0, 'end' => false, 'dots' => 0, 'entries' => 0, 'symlinks' => 0, 'info' => array(), 'domains' => array(), 'dbs' => array(), 'wp' => array(), 'configs' => array(), 'sizes' => array(), 'out' => array( 'files' => 0, 'index' => 0, 'gz' => 0 ), 'x_files' => 0, 'x_bytes' => 0, 'x_skipped' => array(), 'db_done' => false, 'db_name' => '' );
		$this->site = (string) $site;
		$this->paths = $paths;
		foreach ( $paths as $key => $path ) {
			$handle = @fopen( $path, 'c+b' );
			if ( ! $handle ) throw new Exception( 'No se pudo escribir en el área de trabajo de la importación.' );
			@chmod( $path, 0600 );
			$size = (int) $this->s['out'][ $key ];
			if ( ! ftruncate( $handle, $size ) || 0 !== fseek( $handle, $size ) ) { fclose( $handle ); throw new Exception( 'No se pudo preparar el área de trabajo de la importación.' ); }
			$this->handles[ $key ] = $handle;
		}
	}
	/** Bytes escritos en el área de trabajo (para vigilar el espacio libre). */
	public function written() { return (int) $this->s['out']['files'] + (int) $this->s['out']['gz']; }
	/** El estado puede guardarse aquí: ninguna cabecera ni lectura a medias. */
	public function safe() {
		if ( 'h' === $this->s['mode'] ) return '' === $this->buffer && null === $this->s['long'] && null === $this->s['pax_path'] && null === $this->s['pax_size'];
		if ( 'p' === $this->s['mode'] ) return true;
		return in_array( $this->s['act'], array( 'skip', 'ext', 'db' ), true ) && empty( $this->s['cur']['grab'] );
	}
	/** Cierra las salidas (a salvo en el disco) y devuelve el estado para el punto de control. */
	public function close() {
		foreach ( $this->handles as $key => $handle ) {
			if ( ! fflush( $handle ) ) throw new Exception( 'No se pudo guardar el avance de la importación en el área de trabajo.' );
			if ( function_exists( 'fsync' ) ) @fsync( $handle );
			$this->s['out'][ $key ] = (int) ftell( $handle );
			fclose( $handle );
		}
		$this->handles = array();
		return $this->s;
	}
	public function abandon() {
		foreach ( $this->handles as $handle ) @fclose( $handle );
		$this->handles = array();
	}

	/** Un trozo más del tar. */
	public function feed( $chunk ) {
		$n = strlen( $chunk );
		$i = 0;
		while ( $i < $n ) {
			if ( $this->s['end'] ) { $i = $n; break; } // Tras la marca de fin, como tar: nada se lee.
			if ( 'h' === $this->s['mode'] ) {
				$take = min( 512 - strlen( $this->buffer ), $n - $i );
				$this->buffer .= substr( $chunk, $i, $take );
				$i += $take;
				if ( 512 === strlen( $this->buffer ) ) { $block = $this->buffer; $this->buffer = ''; $this->header( $block ); }
				continue;
			}
			if ( 'd' === $this->s['mode'] ) {
				$take = (int) min( $this->s['left'], $n - $i );
				if ( $take > 0 ) $this->data( 0 === $i && $take === $n ? $chunk : substr( $chunk, $i, $take ) );
				$i += $take;
				$this->s['left'] -= $take;
				if ( 0 === (int) $this->s['left'] ) $this->complete();
				continue;
			}
			$take = (int) min( $this->s['pad'], $n - $i );
			$i += $take;
			$this->s['pad'] -= $take;
			if ( 0 === (int) $this->s['pad'] ) $this->s['mode'] = 'h';
		}
		$this->s['pos'] += $n;
	}

	private function fail( $message ) { throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Webuzo ' . $message ); }

	private function header( $block ) {
		if ( str_repeat( "\0", 512 ) === $block ) {
			$this->s['zeros']++;
			if ( $this->s['zeros'] >= 2 ) $this->s['end'] = true;
			return;
		}
		$this->s['zeros'] = 0;
		$header = Digitalisimo_Backups_Backuply_Tar::header( $block );
		$type = $header['type'];
		$padded = Digitalisimo_Backups_Backuply_Tar::padded( $header['size'] );
		if ( in_array( $type, array( 'L', 'K', 'x', 'g' ), true ) ) {
			// Nombre largo GNU (L) o cabecera PAX: valen para la cabecera siguiente. Una cadena larga no la escribe ningún tar.
			if ( ++$this->s['chain'] > 8 ) $this->fail( 'trae una cadena de cabeceras extendidas (L, K, x o g) demasiado larga.' );
			if ( $header['size'] > ( 'L' === $type ? Digitalisimo_Backups_Archive_Inspector::MAX_NAME + 1 : Digitalisimo_Backups_Webuzo_Import::MAX_SMALL ) ) $this->fail( 'trae una cabecera extendida demasiado grande.' );
			return $this->begin( 'meta:' . $type, $header['size'], $padded, array( 'grab' => true ) );
		}
		if ( null !== $this->s['pax_size'] ) { $header['size'] = (int) $this->s['pax_size']; $padded = Digitalisimo_Backups_Backuply_Tar::padded( $header['size'] ); }
		$raw = null !== $this->s['pax_path'] ? (string) $this->s['pax_path'] : ( null !== $this->s['long'] ? (string) $this->s['long'] : $header['name'] );
		$this->s['long'] = null;
		$this->s['pax_path'] = null;
		$this->s['pax_size'] = null;
		$this->s['chain'] = 0;
		$this->s['entries']++;
		// El recorrido cuenta toda la cuenta (el correo de un buzón grande, otros sitios): su tope es otro, más alto.
		if ( $this->s['entries'] > Digitalisimo_Backups_Webuzo_Stream::max_walk() ) $this->fail( 'tiene más de ' . Digitalisimo_Backups_Webuzo_Stream::max_walk() . ' entradas en toda la cuenta, el máximo que se recorre (filtro digitalisimo_backups_webuzo_max_walk).' );
		// La carpeta `.` abre cada tramo: el primero, los datos de la cuenta; el segundo, su home.
		if ( preg_match( '#^\.(?:/\.)*/?$#D', $raw ) && ( '5' === $type || ( in_array( $type, array( '0', "\0" ), true ) && 0 === $header['size'] ) ) ) {
			$this->s['dots']++;
			return $this->begin( 'skip', 0, 0, array() );
		}
		if ( 0 === (int) $this->s['dots'] ) $this->fail( 'no tiene la forma de un respaldo de cuenta de Webuzo: no empieza por la carpeta «.» con los datos de la cuenta.' );
		$name = Digitalisimo_Backups_Backuply_Tar::path( $raw );
		// Una carpeta al estilo antiguo (v7): tipo de archivo regular, vacío y con «/» al final. Con datos se rechaza.
		if ( in_array( $type, array( '0', "\0", '7' ), true ) && '/' === substr( $raw, -1 ) ) {
			if ( 0 !== $header['size'] ) $this->fail( 'trae un archivo con datos cuyo nombre acaba en «/»: ' . Digitalisimo_Backups_Backuply_Tar::shown( $name ) . '.' );
			$type = '5';
		}
		$home = (int) $this->s['dots'] >= 2;
		$plain = ! preg_match( '/[\x00-\x1F\x7F]/', $name ) && (bool) preg_match( '//u', $name );
		if ( ! $home && 'backuply-data' !== $name && 0 !== strpos( $name, 'backuply-data/' ) ) $this->fail( 'no tiene la forma de un respaldo de cuenta de Webuzo: su primer tramo debe ser backuply-data/ (trae ' . Digitalisimo_Backups_Backuply_Tar::shown( $name ) . ').' );
		$inside = $home && '' !== $this->site && 0 === strpos( $name . '/', $this->site . '/' ) && $name !== $this->site;
		if ( $inside && ! $plain ) $this->fail( 'trae un nombre de archivo con caracteres de control o que no es UTF-8 válido dentro del WordPress elegido: ' . Digitalisimo_Backups_Backuply_Tar::shown( $name ) . '.' );
		if ( '5' === $type ) return $this->begin( 'skip', 0, 0, array() );
		if ( '2' === $type ) {
			$this->s['symlinks']++;
			if ( $inside ) $this->skipped( 'symlinks' );
			return $this->begin( 'skip', $header['size'], $padded, array() );
		}
		if ( '1' === $type ) {
			// Un enlace duro fuera del WordPress elegido no se lee; dentro, no se sabe qué datos tendría: se rechaza.
			if ( $inside ) $this->fail( 'trae un enlace duro dentro del WordPress elegido, que no se importa: ' . Digitalisimo_Backups_Backuply_Tar::shown( $name ) . '.' );
			return $this->begin( 'skip', $header['size'], $padded, array() );
		}
		if ( ! in_array( $type, array( '0', "\0", '7' ), true ) ) {
			if ( $inside ) $this->fail( 'trae una entrada de un tipo que no se importa (dispositivo, tubería u otro) dentro del WordPress elegido: ' . Digitalisimo_Backups_Backuply_Tar::shown( $name ) . '.' );
			return $this->begin( 'skip', in_array( $type, array( '3', '4', '6' ), true ) ? 0 : $header['size'], in_array( $type, array( '3', '4', '6' ), true ) ? 0 : $padded, array() );
		}
		$this->regular( $name, $header, $padded, $home, $inside, $plain );
	}

	/** Un archivo regular: qué se lee de él (datos de la cuenta, piezas de un WordPress) y si se extrae. */
	private function regular( $name, array $header, $padded, $home, $inside, $plain = true ) {
		$size = (int) $header['size'];
		$small = $size <= Digitalisimo_Backups_Webuzo_Import::MAX_GRAB;
		if ( ! $home ) {
			if ( 'backuply-data/info' === $name ) return $this->begin( 'skip', $size, $padded, $small ? array( 'grab' => true, 'k' => 'info', 'all' => true ) : array( 'k' => 'too_big_info' ) );
			if ( 'backuply-data/domains' === $name ) return $this->begin( 'skip', $size, $padded, $small ? array( 'grab' => true, 'k' => 'domains', 'all' => true ) : array( 'k' => 'too_big_domains' ) );
			if ( preg_match( '#^backuply-data/mysql/([A-Za-z0-9_$-]{1,64})\.sql\.gz$#D', $name, $m ) ) {
				if ( count( $this->s['dbs'] ) < 1000 ) $this->s['dbs'][ $m[1] ] = $size;
				if ( '' !== $this->site && '' !== $this->s['db_name'] && $m[1] === $this->s['db_name'] ) {
					if ( $this->s['db_done'] ) $this->fail( 'trae dos veces la base ' . $m[1] . '.' );
					return $this->begin( 'db', $size, $padded, array() );
				}
			}
			return $this->begin( 'skip', $size, $padded, array() );
		}
		// Una carpeta con caracteres de control o que no es UTF-8 nunca es un sitio (ni cuenta en los tamaños): su nombre no
		// cabe en el punto de control ni en un mensaje.
		if ( ! $plain ) return $this->begin( 'skip', $size, $padded, array() );
		$this->size( $name, $size );
		$base = basename( $name );
		$dir = dirname( $name );
		$dir = '.' === $dir ? '' : $dir;
		$options = array();
		if ( 'wp-config.php' === $base && $small ) $options = array( 'grab' => true, 'k' => 'config', 'r' => $dir );
		foreach ( Digitalisimo_Backups_Webuzo_Import::CORE as $file => $bit ) {
			if ( $name !== $file && substr( $name, -strlen( '/' . $file ) ) !== '/' . $file ) continue;
			$root = $name === $file ? '' : substr( $name, 0, -strlen( '/' . $file ) );
			$this->core( $root, $bit );
			if ( 'wp-includes/version.php' === $file && $small ) $options = array( 'grab' => true, 'k' => 'version', 'r' => $root );
		}
		if ( ! $inside ) return $this->begin( 'skip', $size, $padded, $options );
		// Dentro del WordPress elegido: lo que se extrae.
		$relative = substr( $name, strlen( $this->site ) + 1 );
		$why = $this->excluded( $relative, $base );
		if ( '' !== $why ) {
			$this->skipped( $why );
			return $this->begin( 'skip', $size, $padded, $options );
		}
		if ( ! preg_match( '//u', $relative ) ) $this->fail( 'trae un nombre de archivo que no es UTF-8 válido y no puede figurar en el manifiesto: ' . Digitalisimo_Backups_Backuply_Tar::shown( $relative ) . '.' );
		$this->put( 'index', wp_json_encode( array( 'n' => $relative, 'o' => (int) $this->s['out']['files'], 's' => $size, 'm' => (int) $header['mode'], 't' => (int) $header['mtime'] ) ) . "\n" );
		$this->s['x_files']++;
		if ( $this->s['x_files'] > Digitalisimo_Backups_Archive_Inspector::max_entries() ) $this->fail( 'trae más de ' . Digitalisimo_Backups_Archive_Inspector::max_entries() . ' archivos en el WordPress elegido, el máximo permitido (DIGITALISIMO_BACKUPS_MAX_ENTRIES).' );
		$this->s['x_bytes'] += $size;
		return $this->begin( 'ext', $size, $padded, $options );
	}
	/** Por qué no se extrae un archivo de la carpeta elegida ('' si se extrae). */
	private function excluded( $relative, $base ) {
		if ( 'wp-config.php' === $base ) return 'wp-config.php' === $relative ? 'root_config' : 'config';
		if ( preg_match( Digitalisimo_Backups_Webuzo_Import::ACCOUNT, $base ) || 0 === strpos( $relative . '/', 'softaculous_backups/' ) ) return 'backup';
		// La carpeta de otro dominio de la cuenta dentro de ésta: es otro sitio.
		foreach ( (array) $this->s['domains'] as $path ) {
			$path = (string) $path;
			if ( '' !== $path && $path !== $this->site && 0 === strpos( $path . '/', $this->site . '/' ) && 0 === strpos( $relative . '/', substr( $path, strlen( $this->site ) + 1 ) . '/' ) ) return 'domain';
		}
		if ( null === $this->rules ) {
			$default = array( 'folders' => Digitalisimo_Backups_Exclusions::FOLDERS, 'files' => Digitalisimo_Backups_Exclusions::FILES );
			$chosen = function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_default_exclusions', $default ) : $default;
			$chosen = is_array( $chosen ) ? $chosen : array();
			$this->rules = array( 'folders' => array(), 'files' => array_values( array_intersect( Digitalisimo_Backups_Exclusions::FILES, array_filter( (array) ( $chosen['files'] ?? array() ), 'is_string' ) ) ), 'code' => array( 'wp-admin', 'wp-includes', 'wp-content/plugins', 'wp-content/themes', 'wp-content/mu-plugins' ) );
			foreach ( array_intersect( Digitalisimo_Backups_Exclusions::FOLDERS, array_filter( (array) ( $chosen['folders'] ?? array() ), 'is_string' ) ) as $folder ) $this->rules['folders'][ 'wp-content/' . $folder ] = true;
		}
		if ( Digitalisimo_Backups_Exclusions::excluded( $relative, false, $this->rules ) ) return 'excluded';
		return '';
	}
	private function skipped( $why ) { $this->s['x_skipped'][ $why ] = (int) ( $this->s['x_skipped'][ $why ] ?? 0 ) + 1; }
	/** Tamaño aproximado de cada carpeta hasta tres niveles (para decir cuánto mide cada WordPress). */
	private function size( $name, $bytes ) {
		$parts = explode( '/', $name );
		array_pop( $parts );
		$path = '';
		foreach ( array_slice( $parts, 0, 3 ) as $part ) {
			$path = '' === $path ? $part : $path . '/' . $part;
			if ( ! isset( $this->s['sizes'][ $path ] ) && count( $this->s['sizes'] ) >= 5000 ) return;
			$this->s['sizes'][ $path ] = array( (int) ( $this->s['sizes'][ $path ][0] ?? 0 ) + 1, (int) ( $this->s['sizes'][ $path ][1] ?? 0 ) + $bytes );
		}
	}
	private function core( $root, $bit ) {
		if ( ! isset( $this->s['wp'][ $root ] ) ) {
			if ( count( $this->s['wp'] ) >= 500 ) return;
			$this->s['wp'][ $root ] = array( 'core' => 0, 'version' => '' );
		}
		$this->s['wp'][ $root ]['core'] |= $bit;
	}
	private function begin( $act, $size, $padded, array $cur ) {
		$this->s['act'] = $act;
		$this->s['cur'] = $cur;
		$this->s['left'] = (int) $size;
		$this->s['pad'] = (int) $padded - (int) $size;
		$this->s['mode'] = 'd';
		$this->grab = '';
		if ( 0 === (int) $size ) $this->complete();
	}
	private function data( $bytes ) {
		$limit = empty( $this->s['cur']['all'] ) ? Digitalisimo_Backups_Webuzo_Import::MAX_SMALL : Digitalisimo_Backups_Webuzo_Import::MAX_GRAB;
		if ( ! empty( $this->s['cur']['grab'] ) && strlen( $this->grab ) < $limit ) $this->grab .= substr( $bytes, 0, $limit - strlen( $this->grab ) );
		if ( 'ext' === $this->s['act'] ) { $this->put( 'files', $bytes ); $this->s['out']['files'] += strlen( $bytes ); }
		elseif ( 'db' === $this->s['act'] ) { $this->put( 'gz', $bytes ); $this->s['out']['gz'] += strlen( $bytes ); }
	}
	private function put( $key, $bytes ) {
		if ( ! isset( $this->handles[ $key ] ) ) return;
		$length = strlen( $bytes );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $this->handles[ $key ], 0 === $offset ? $bytes : substr( $bytes, $offset ) );
			if ( false === $written || 0 === $written ) throw new Exception( 'No se pudo escribir en el área de trabajo de la importación; revisa el espacio libre del almacén privado.' );
			$offset += $written;
		}
		if ( 'index' === $key ) $this->s['out']['index'] += $length;
	}
	/** Fin de una entrada: lo leído se interpreta (sin ejecutar ni deserializar nada) y se olvida. */
	private function complete() {
		$cur = (array) $this->s['cur'];
		$bytes = $this->grab;
		$this->grab = '';
		$act = $this->s['act'];
		$this->s['act'] = 'skip';
		$this->s['cur'] = array();
		$this->s['mode'] = (int) $this->s['pad'] > 0 ? 'p' : 'h';
		if ( 'db' === $act ) $this->s['db_done'] = true;
		if ( 0 === strpos( $act, 'meta:' ) ) {
			$type = substr( $act, 5 );
			if ( 'L' === $type ) $this->s['long'] = rtrim( $bytes, "\0" );
			if ( 'x' === $type ) {
				$pax = Digitalisimo_Backups_Backuply_Tar::pax( $bytes );
				if ( isset( $pax['path'] ) ) $this->s['pax_path'] = $pax['path'];
				if ( isset( $pax['size'] ) ) $this->s['pax_size'] = $pax['size'];
			}
			if ( 'g' === $type ) {
				$global = Digitalisimo_Backups_Backuply_Tar::pax( $bytes );
				if ( isset( $global['path'] ) || isset( $global['size'] ) ) $this->fail( 'trae una cabecera PAX global con path o size, que tar y Python leen distinto.' );
			}
			return;
		}
		$kind = (string) ( $cur['k'] ?? '' );
		// Sin poder leer info o domains, la cuenta queda sin su home ni sus dominios: se avisa.
		if ( in_array( $kind, array( 'too_big_info', 'too_big_domains' ), true ) || ( in_array( $kind, array( 'info', 'domains' ), true ) && ! is_array( json_decode( $bytes, true, 8 ) ) ) ) $this->s['meta_error'][ str_replace( 'too_big_', '', $kind ) ] = true;
		if ( 'info' === $kind ) $this->info( $bytes );
		elseif ( 'domains' === $kind ) $this->domains( json_decode( $bytes, true, 8 ) );
		elseif ( 'config' === $kind && count( $this->s['configs'] ) < 500 ) $this->s['configs'][ (string) $cur['r'] ] = Digitalisimo_Backups_Webuzo_Stream::config( $bytes );
		elseif ( 'version' === $kind && isset( $this->s['wp'][ (string) $cur['r'] ] ) && preg_match( '/\$wp_version\s*=\s*[\'"]([0-9A-Za-z.\-]{1,32})[\'"]/', $bytes, $m ) ) $this->s['wp'][ (string) $cur['r'] ]['version'] = $m[1];
	}
	private function info( $bytes ) {
		$data = json_decode( $bytes, true, 8 );
		if ( ! is_array( $data ) ) return;
		$user = is_string( $data['user'] ?? null ) && preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,31}$/D', $data['user'] ) ? $data['user'] : '';
		$homedir = is_string( $data['homedir'] ?? null ) ? rtrim( str_replace( '\\/', '/', $data['homedir'] ), '/' ) : '';
		if ( '' === $homedir || '/' !== $homedir[0] || false !== strpos( $homedir, "\0" ) || false !== strpos( $homedir, '..' ) || strlen( $homedir ) > 500 || ! preg_match( '#^/[A-Za-z0-9._/-]+$#D', $homedir ) ) $homedir = '';
		$this->s['info'] = array( 'user' => $user, 'homedir' => $homedir );
		if ( ! $this->s['domains'] && is_array( $data['domains'] ?? null ) ) $this->domains( $data['domains'] );
	}
	/** Dominio => carpeta relativa al home (de las rutas absolutas del panel); `http` si el panel no fuerza https. */
	private function domains( $data ) {
		if ( ! is_array( $data ) ) return;
		$homedir = (string) ( $this->s['info']['homedir'] ?? '' );
		$out = array();
		$http = array();
		foreach ( array_slice( $data, 0, 500, true ) as $domain => $entry ) {
			$domain = strtolower( (string) $domain );
			if ( ! preg_match( '/^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$/D', $domain ) || ! is_array( $entry ) || ! is_string( $entry['path'] ?? null ) ) continue;
			$path = rtrim( str_replace( '\\/', '/', $entry['path'] ), '/' );
			if ( '' === $homedir || ( 0 !== strpos( $path . '/', $homedir . '/' ) ) ) continue;
			$relative = trim( substr( $path, strlen( $homedir ) ), '/' );
			if ( null === Digitalisimo_Backups_Webuzo_Import::clean_site( $relative ) ) continue;
			$out[ $domain ] = $relative;
			if ( isset( $entry['force_https'] ) && 0 === (int) $entry['force_https'] ) $http[ $domain ] = true;
		}
		$this->s['domains'] = $out;
		$this->s['http'] = $http;
	}

	/**
	 * `$table_prefix`, las constantes de la red y `DB_NAME` de un wp-config.php, sin ejecutarlo (token_get_all(), como el
	 * de Backuply). Nada más se guarda: ni la contraseña, ni el usuario, ni las claves.
	 */
	public static function config( $php ) {
		$out = Digitalisimo_Backups_Backuply_Tar::config( $php );
		$code = (string) $php;
		if ( function_exists( 'token_get_all' ) ) {
			$clean = '';
			foreach ( @token_get_all( $code ) as $token ) {
				if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { $clean .= ' '; continue; }
				$clean .= is_array( $token ) ? $token[1] : $token;
			}
			$code = $clean;
		} else {
			$code = preg_replace( array( '#/\*.*?\*/#s', '#(?://|\#)[^\n]*#' ), ' ', $code );
		}
		$out['db'] = preg_match( '/\bdefine\s*\(\s*[\'"]DB_NAME[\'"]\s*,\s*[\'"]([A-Za-z0-9_$-]{1,64})[\'"]\s*\)/', $code, $m ) ? $m[1] : '';
		// Definidos más de una vez con valores distintos (o con una expresión): según el entorno, otra base u otro
		// prefijo. No se adivina cuál usa el sitio.
		$ambiguous = function( $all, $literal ) use ( $code ) {
			$total = preg_match_all( $all, $code );
			if ( $total < 2 ) return false;
			$values = preg_match_all( $literal, $code, $found ) ? array_unique( $found[1] ) : array();
			return 1 !== count( $values ) || count( $found[1] ) !== $total;
		};
		$out['conflict'] = array();
		if ( $ambiguous( '/\bdefine\s*\(\s*[\'"]DB_NAME[\'"]\s*,/', '/\bdefine\s*\(\s*[\'"]DB_NAME[\'"]\s*,\s*[\'"]([A-Za-z0-9_$-]{1,64})[\'"]\s*\)/' ) ) $out['conflict'][] = 'DB_NAME';
		if ( $ambiguous( '/\$table_prefix\s*=(?!=)/', '/\$table_prefix\s*=\s*[\'"]([A-Za-z0-9_]{1,64})[\'"]\s*;/' ) ) $out['conflict'][] = '$table_prefix';
		return $out;
	}

	/* ------------------------------------------------------------------ Lo que dice el recorrido completo */

	public static function account( array $s ) {
		return array( 'user' => (string) ( $s['info']['user'] ?? '' ), 'homedir' => (string) ( $s['info']['homedir'] ?? '' ), 'domains' => count( (array) $s['domains'] ), 'databases' => count( (array) $s['dbs'] ), 'meta_error' => array_keys( (array) ( $s['meta_error'] ?? array() ) ) );
	}
	/** Los WordPress completos del home (núcleo entero), fuera de las carpetas que nunca son un sitio. */
	private static function roots( array $s ) {
		$out = array();
		foreach ( (array) $s['wp'] as $root => $wp ) {
			$root = (string) $root;
			if ( '' === $root || 15 !== ( (int) $wp['core'] & 15 ) || preg_match( '/[\x00-\x1F\x7F]/', $root ) || ! preg_match( '//u', $root ) ) continue;
			$first = explode( '/', $root )[0];
			if ( in_array( $first, Digitalisimo_Backups_Webuzo_Import::NOT_SITES, true ) ) continue;
			$hidden = false;
			foreach ( explode( '/', $root ) as $segment ) if ( '.' === $segment[0] ) $hidden = true;
			if ( $hidden ) continue;
			$out[] = $root;
		}
		sort( $out, SORT_STRING );
		return $out;
	}
	/** El wp-config.php de un WordPress: el de su carpeta o, como admite WordPress, el de la de arriba (si no es otro). */
	private static function config_of( array $s, $root, array $roots ) {
		if ( isset( $s['configs'][ $root ] ) ) return (array) $s['configs'][ $root ];
		$up = dirname( $root );
		$up = '.' === $up ? '' : $up;
		if ( isset( $s['configs'][ $up ] ) && ! in_array( $up, $roots, true ) ) return (array) $s['configs'][ $up ];
		return null;
	}
	/** Los WordPress que trae el respaldo: carpeta, dominio, versión, prefijo, red, base y si se pueden importar. */
	public static function sites( array $s ) {
		$roots = self::roots( $s );
		$out = array();
		foreach ( $roots as $root ) {
			$config = self::config_of( $s, $root, $roots );
			$domain = '';
			$http = false;
			$best = -1;
			foreach ( (array) $s['domains'] as $name => $path ) {
				$path = (string) $path;
				if ( $path === $root ) { $domain = (string) $name; $http = ! empty( $s['http'][ $name ] ); break; }
				if ( ( '' === $path || 0 === strpos( $root . '/', $path . '/' ) ) && strlen( $path ) > $best ) { $best = strlen( $path ); $domain = $name . '/' . ( '' === $path ? $root : substr( $root, strlen( $path ) + 1 ) ); $http = ! empty( $s['http'][ $name ] ); }
			}
			$db = is_array( $config ) ? (string) $config['db'] : '';
			$prefix = is_array( $config ) ? (string) $config['prefix'] : '';
			$problem = '';
			if ( ! is_array( $config ) ) $problem = 'no trae su wp-config.php: no se sabe cuál es su base de datos ni su prefijo.';
			elseif ( ! empty( $config['conflict'] ) ) $problem = 'su wp-config.php define ' . implode( ' y ', (array) $config['conflict'] ) . ' más de una vez con valores distintos (por ejemplo, según el entorno): no se sabe qué base o qué prefijo usa este sitio, y no se adivina.';
			elseif ( '' === $db ) $problem = 'su wp-config.php no declara DB_NAME con un nombre literal: no se sabe cuál es su base de datos.';
			elseif ( '' === $prefix ) $problem = 'su wp-config.php no declara $table_prefix con un valor literal.';
			elseif ( ! isset( $s['dbs'][ $db ] ) ) $problem = 'su base de datos (' . $db . ') no viene en el respaldo (backuply-data/mysql/' . $db . '.sql.gz): un WordPress sin su base no se restaura. Haz en el panel un respaldo que incluya las bases de datos.';
			$sizes = (array) ( $s['sizes'][ $root ] ?? array( 0, 0 ) );
			$out[] = array(
				'root' => $root,
				'domain' => $domain,
				'http' => $http,
				'version' => (string) $s['wp'][ $root ]['version'],
				'prefix' => $prefix,
				'db' => $db,
				'multisite' => is_array( $config ) && ! empty( $config['multisite'] ),
				'subdomain' => is_array( $config ) && ! empty( $config['subdomain'] ),
				'network' => is_array( $config ) ? (array) $config['network'] : array(),
				'database' => '' !== $db && isset( $s['dbs'][ $db ] ),
				'database_size' => '' !== $db ? (int) ( $s['dbs'][ $db ] ?? 0 ) : 0,
				'files' => (int) ( $sizes[0] ?? 0 ),
				'size' => (int) ( $sizes[1] ?? 0 ),
				'importable' => '' === $problem,
				'problem' => $problem,
			);
		}
		return $out;
	}
	/** Otros WordPress dentro de la carpeta elegida (sus archivos no van al ZIP). */
	public static function nested( array $s, $site ) {
		$out = array();
		foreach ( self::roots( $s ) as $root ) if ( $root !== $site && 0 === strpos( $root . '/', $site . '/' ) ) $out[] = substr( $root, strlen( $site ) + 1 );
		return $out;
	}
	/**
	 * Prefijos de otros WordPress de la misma base que empiezan por el del elegido (`wp_` y `wp_tienda_`): sus tablas no
	 * son del elegido aunque empiecen igual.
	 */
	public static function foreign( array $s, $site ) {
		$roots = self::roots( $s );
		$mine = self::config_of( $s, $site, $roots );
		if ( ! is_array( $mine ) ) return array();
		$out = array();
		foreach ( $roots as $root ) {
			if ( $root === $site ) continue;
			$other = self::config_of( $s, $root, $roots );
			if ( is_array( $other ) && $other['db'] === $mine['db'] && $other['prefix'] !== $mine['prefix'] && 0 === strpos( (string) $other['prefix'], (string) $mine['prefix'] ) ) $out[] = (string) $other['prefix'];
		}
		return array_values( array_unique( $out ) );
	}
}
