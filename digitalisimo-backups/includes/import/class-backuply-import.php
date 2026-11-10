<?php
defined( 'ABSPATH' ) || exit;

/**
 * Importar los respaldos de Backuply que ya existen (I9): su `.tar.gz` con `softsql.sql` se convierte, por tramos del
 * motor de trabajos y como archivo no confiable, en un ZIP de Backups (manifiesto v3 sin firma, `wordpress/…` y
 * `database.sql`) que aparece en «Restaurar» como «sin registro» y se restaura con la restauración por pasos y la
 * reubicación de E1, escribiendo RESTAURAR SIN FIRMA.
 *
 * Formato de Backuply (1.6.0, `backup_ins.php` y `backuplytar.php`; ver «Importar respaldos de Backuply» en
 * docs/BACKUPS.md): un tar GNU («ustar  », nombres largos con `././@LongLink` tipo L, enlaces simbólicos tipo 2 sin
 * destino) comprimido con gzip, a veces en varios miembros porque cada tramo de Backuply lo abre para añadir. Las rutas
 * son relativas a ABSPATH; en la raíz van `softsql.sql` (primero), `softperms.txt`, `softver.txt` y `<nombre>.php` (al
 * final: `<?php exit();?>` y un JSON con `backup_site_url`, `backup_site_path`, `backup_db`, `backup_dir`, `btime`…).
 *
 * Nada del archivo se ejecuta ni se deserializa: el JSON de información se lee con json_decode() acotado y
 * `wp-config.php` con token_get_all() (sólo para el prefijo y las constantes de la red). Cada cabecera del tar se
 * comprueba (suma, tipo, tamaño, ruta sin `..`, absolutas, unidades, NUL ni barra invertida) y el número de entradas y
 * el tamaño quedan acotados. Ver Digitalisimo_Backups_Import_Job para las fases.
 */
final class Digitalisimo_Backups_Backuply_Import {
	const FORMAT = 'backuply';
	const PACKER = 'digitalisimo-backuply-import/1';
	/** Carpeta del almacén privado para los `.tar.gz` subidos o traídos de un destino. */
	const STORE_DIRECTORY = 'backuply';
	const NAME = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}\.tar\.gz$/D';
	const CHUNK = 1048576;
	/** Tamaño máximo del tar descomprimido (como una subida): más allá se rechaza. */
	const MAX_TAR = 68719476736;
	const MAX_INFO = 65536;
	const MAX_CONFIG = 1048576;
	const REQUIRED = array( 'wp-config.php', 'wp-load.php', 'wp-settings.php', 'wp-admin/index.php', 'wp-includes/version.php' );
	/** Carpetas que no se importan: los respaldos y el estado de Backuply (Backuply ya las excluye). */
	const SKIP = '#^wp-content/backuply(?:/|$)#';
	const METADATA = array( 'softsql.sql', 'softperms.txt', 'softver.txt' );

	/* ------------------------------------------------------------------ Detección y orígenes */

	/** Backuply en este sitio: su plugin, su carpeta o sus ajustes. */
	public static function detected() {
		$plugin = defined( 'WP_PLUGIN_DIR' ) && is_file( WP_PLUGIN_DIR . '/backuply/backuply.php' );
		$folder = defined( 'WP_CONTENT_DIR' ) && is_dir( WP_CONTENT_DIR . '/backuply' ) && ! is_link( WP_CONTENT_DIR . '/backuply' );
		$active = false;
		if ( function_exists( 'get_option' ) ) {
			$active = in_array( 'backuply/backuply.php', (array) get_option( 'active_plugins', array() ), true );
			if ( ! $active && function_exists( 'is_multisite' ) && is_multisite() ) $active = isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )['backuply/backuply.php'] );
		}
		return array( 'installed' => $plugin, 'active' => $active, 'folder' => $folder );
	}
	/** Carpetas `backups-*` de Backuply (sólo carpetas reales dentro de `wp-content/backuply`). */
	private static function backuply_folders( $pattern ) {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) return array();
		$root = WP_CONTENT_DIR . '/backuply';
		if ( ! is_dir( $root ) || is_link( $root ) ) return array();
		$real = realpath( $root );
		$out = array();
		foreach ( (array) glob( $root . '/' . $pattern, GLOB_ONLYDIR ) as $dir ) {
			if ( is_link( $dir ) ) continue;
			$resolved = realpath( $dir );
			if ( false === $resolved || false === $real || 0 !== strpos( $resolved, $real . DIRECTORY_SEPARATOR ) ) continue;
			$out[] = $resolved;
		}
		return $out;
	}
	public static function store_directory( $create = false ) {
		$dir = rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . self::STORE_DIRECTORY;
		if ( is_link( $dir ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta de respaldos de Backuply del almacén no puede ser un enlace simbólico.' );
		if ( $create && ! is_dir( $dir ) ) {
			if ( ! @mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) throw new Exception( 'No se pudo crear la carpeta de respaldos de Backuply en el almacén privado.' );
			@chmod( $dir, 0700 );
		}
		return $dir;
	}

	/**
	 * Respaldos de Backuply que se pueden importar: los de su carpeta en este sitio (`backuply`) y los del almacén privado
	 * (`store`: subidos o traídos de un destino). Sin rutas: el origen y el nombre bastan para pedir la importación.
	 */
	public static function sources() {
		$items = array();
		foreach ( self::backuply_folders( 'backups-*' ) as $dir ) {
			foreach ( (array) glob( $dir . '/*.tar.gz' ) as $path ) {
				$item = self::describe( 'backuply', $path );
				if ( $item ) $items[] = $item;
			}
		}
		try {
			$store = self::store_directory();
			if ( is_dir( $store ) ) foreach ( (array) glob( $store . '/*.tar.gz' ) as $path ) {
				$item = self::describe( 'store', $path );
				if ( $item ) $items[] = $item;
			}
		} catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		usort( $items, function( $a, $b ) { return $b['created'] <=> $a['created']; } );
		return $items;
	}
	private static function describe( $origin, $path ) {
		$name = basename( $path );
		if ( ! preg_match( self::NAME, $name ) || is_link( $path ) || ! is_file( $path ) ) return null;
		$size = (int) @filesize( $path );
		$info = 'backuply' === $origin ? self::side_info( $name ) : null;
		$created = (int) ( $info['btime'] ?? 0 ) ?: (int) @filemtime( $path );
		return array(
			'source' => $origin,
			'name' => $name,
			'size' => $size,
			'created' => $created,
			'date' => gmdate( 'Y-m-d H:i', $created ),
			// Lo que dice el archivo de información de Backuply (sólo orientativo: la importación lo comprueba dentro del tar).
			'database' => null === $info ? null : ! empty( $info['backup_db'] ),
			'files' => null === $info ? null : ! empty( $info['backup_dir'] ),
			'site_url' => is_string( $info['backup_site_url'] ?? null ) && preg_match( '#^https?://#i', $info['backup_site_url'] ) ? substr( (string) $info['backup_site_url'], 0, 200 ) : '',
			'imported' => self::converted_name( $name ),
		);
	}
	/** El archivo de información que Backuply deja junto a sus respaldos (`backups_info-…/<nombre>.php`), sin ejecutarlo. */
	private static function side_info( $name ) {
		$stem = substr( $name, 0, -7 );
		foreach ( self::backuply_folders( 'backups_info-*' ) as $dir ) {
			$path = $dir . '/' . $stem . '.php';
			if ( ! is_file( $path ) || is_link( $path ) || (int) @filesize( $path ) > self::MAX_INFO ) continue;
			$info = self::parse_info( (string) @file_get_contents( $path, false, null, 0, self::MAX_INFO ) );
			if ( $info ) return $info;
		}
		return null;
	}
	/** `<?php exit();?>` y un objeto JSON (sin ejecutar ni deserializar nada); null si no lo es. */
	public static function parse_info( $raw ) {
		$raw = (string) $raw;
		if ( ! preg_match( '/^<\?php\s+exit\s*\(\s*\)\s*;\s*\?>\s*/', $raw, $m ) ) return null;
		$data = json_decode( substr( $raw, strlen( $m[0] ) ), true, 4 );
		return is_array( $data ) ? $data : null;
	}
	/** Ruta real de un origen: sólo un `.tar.gz` regular dentro de las carpetas de Backuply o del almacén. */
	public static function source_path( $origin, $name ) {
		$name = (string) $name;
		if ( ! preg_match( self::NAME, $name ) ) return '';
		$dirs = 'store' === $origin ? array( self::store_directory() ) : ( 'backuply' === $origin ? self::backuply_folders( 'backups-*' ) : array() );
		foreach ( $dirs as $dir ) {
			$path = rtrim( $dir, '/\\' ) . '/' . $name;
			if ( is_file( $path ) && ! is_link( $path ) ) return $path;
		}
		return '';
	}
	/** Nombre del ZIP convertido de un respaldo de Backuply, si ya está en el almacén ('' si no). */
	public static function converted_name( $name ) {
		$file = self::target_name( $name );
		try {
			return is_file( trailingslashit( Digitalisimo_Backups::storage_directory() ) . $file ) ? $file : '';
		} catch ( Exception $e ) {
			return '';
		}
	}
	public static function target_name( $name ) {
		$stem = preg_replace( '/[^A-Za-z0-9._-]+/', '-', substr( (string) $name, 0, -7 ) );
		return 'digitalisimo-backuply-' . trim( (string) $stem, '.-_' ) . '.zip';
	}

	/* ------------------------------------------------------------------ Trabajo */

	/** Especificación de una importación (va al motor de trabajos como `kind = import`). */
	public static function spec( $origin, $name, array $args = array() ) {
		$path = self::source_path( $origin, $name );
		if ( '' === $path ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply solicitado no está disponible.' );
		clearstatcache( true, $path );
		return array(
			'type' => 'import',
			'format' => self::FORMAT,
			'source' => array( 'origin' => (string) $origin, 'name' => (string) $name, 'size' => (int) filesize( $path ), 'mtime' => (int) filemtime( $path ) ),
			'policy' => 'import:' . $origin . ':' . $name,
			'destination' => 'local',
			'file' => self::target_name( $name ),
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
	/** El origen sigue siendo el mismo archivo que se pidió importar (tamaño y fecha). */
	private static function source( array $job ) {
		$source = (array) ( $job['spec']['source'] ?? array() );
		$path = self::source_path( (string) ( $source['origin'] ?? '' ), (string) ( $source['name'] ?? '' ) );
		if ( '' === $path ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply que se estaba importando ya no está en su carpeta.' );
		clearstatcache( true, $path );
		if ( (int) filesize( $path ) !== (int) ( $source['size'] ?? -1 ) || (int) filemtime( $path ) !== (int) ( $source['mtime'] ?? -1 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply cambió durante la importación (otro proceso lo está escribiendo). Espera a que Backuply termine y vuelve a importarlo.' );
		return $path;
	}
	private static function paths( $job ) {
		$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		return array( 'tar' => $staging . '/source.tar', 'index' => $staging . '/index.jsonl', 'sql' => $staging . '/database.sql', 'zip' => $staging . '/converted.zip', 'entries' => $staging . '/entries.jsonl' );
	}
	private static function deadline( array $job, $phase ) {
		$cursor = (array) ( $job['checkpoint'][ $phase ] ?? array() );
		return Digitalisimo_Backups_Step_Budget::deadline( Digitalisimo_Backups_Step_Budget::scale( $job, $phase, $cursor ), Digitalisimo_Backups_Step_Budget::slice_cap() );
	}
	private static function space( $dir, $needed, $what ) {
		$free = Digitalisimo_Backups_Archive_Inspector::free_space( $dir );
		if ( null !== $free && $free < $needed + Digitalisimo_Backups_Archive_Inspector::MARGIN ) throw new Digitalisimo_Backups_Permanent_Error( 'No hay espacio en el almacén privado para ' . $what . ': se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed + Digitalisimo_Backups_Archive_Inspector::MARGIN ) . ' libres y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '. Libera espacio y vuelve a importarlo.' );
	}

	/**
	 * Fase `unpack`: descomprime el `.tar.gz` (también con varios miembros gzip) a `staging/source.tar`, por tramos. Tras
	 * una muerte, la salida se trunca a su punto de control y la lectura vuelve ahí (gzseek descomprime de nuevo lo
	 * anterior sin escribirlo).
	 */
	public static function unpack( array $job ) {
		$source = self::source( $job );
		$paths = self::paths( $job );
		$cursor = (array) ( $job['checkpoint']['unpack'] ?? array() ) + array( 'offset' => 0 );
		$deadline = self::deadline( $job, 'unpack' );
		if ( 0 === (int) $cursor['offset'] ) {
			$magic = (string) @file_get_contents( $source, false, null, 0, 2 );
			if ( "\x1f\x8b" !== $magic ) throw new Digitalisimo_Backups_Permanent_Error( 'El archivo no es un respaldo de Backuply: no está comprimido con gzip (.tar.gz).' );
			self::space( dirname( $paths['tar'] ), (int) $job['spec']['source']['size'], 'descomprimir el respaldo de Backuply' );
		}
		if ( ! function_exists( 'gzopen' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El servidor necesita la extensión PHP zlib para leer un respaldo de Backuply (.tar.gz).' );
		$gz = @gzopen( $source, 'rb' );
		if ( ! $gz ) throw new Exception( 'No se pudo abrir el respaldo de Backuply.' );
		$out = @fopen( $paths['tar'], 'c+b' );
		if ( ! $out ) { gzclose( $gz ); throw new Exception( 'No se pudo escribir en el área de trabajo de la importación.' ); }
		@chmod( $paths['tar'], 0600 );
		$offset = (int) $cursor['offset'];
		$units = 0;
		try {
			if ( ! ftruncate( $out, $offset ) || 0 !== fseek( $out, $offset ) ) throw new Exception( 'No se pudo preparar el área de trabajo de la importación.' );
			if ( $offset > 0 && 0 !== gzseek( $gz, $offset ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está dañado: no se pudo volver al punto de la descompresión.' );
			while ( true ) {
				$chunk = gzread( $gz, self::CHUNK );
				if ( false === $chunk ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está dañado: falló la descompresión gzip cerca del byte ' . $offset . '.' );
				if ( '' === $chunk ) {
					if ( ! gzeof( $gz ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está dañado: falló la descompresión gzip cerca del byte ' . $offset . '.' );
					break;
				}
				if ( $offset + strlen( $chunk ) > self::max_tar() ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply descomprimido supera ' . Digitalisimo_Backups_Archive_Inspector::megabytes( self::max_tar() ) . ', el máximo que se importa.' );
				self::put( $out, $chunk );
				$offset += strlen( $chunk );
				$units++;
				if ( 0 === $units % 64 ) self::space( dirname( $paths['tar'] ), 64 * self::CHUNK, 'descomprimir el respaldo de Backuply' );
				if ( Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) {
					self::sync( $out );
					Digitalisimo_Backups_Jobs::progress( 2 + (int) min( 18, floor( 18 * $offset / max( 1, 3 * (int) $job['spec']['source']['size'] ) ) ), 'Descomprimiendo el respaldo de Backuply: ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $offset ) . '…', true );
					return array( 'next' => 'unpack', 'checkpoint' => array( 'offset' => $offset, 'scale' => Digitalisimo_Backups_Step_Budget::scale( $job, 'unpack', $cursor ) ), 'message' => 'Descomprimiendo el respaldo de Backuply…' );
				}
			}
			self::sync( $out );
		} finally {
			fclose( $out );
			gzclose( $gz );
		}
		if ( $offset < 1024 || 0 !== $offset % 512 ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está truncado o dañado: descomprimido mide ' . $offset . ' bytes, que no es un tar completo.' );
		return array( 'next' => 'scan', 'checkpoint' => array( 'offset' => $offset, 'tar_size' => $offset ), 'message' => 'Comprobando el contenido del respaldo de Backuply…' );
	}
	public static function max_tar() { return (int) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_import_max_size', self::MAX_TAR ) : self::MAX_TAR ); }

	/**
	 * Fase `scan`: recorre las cabeceras del tar (sin leer los datos), valida cada una y anota en `index.jsonl` los
	 * archivos que irán al ZIP. Reconoce los archivos de Backuply de la raíz y comprueba que es un respaldo completo.
	 */
	public static function scan( array $job ) {
		$paths = self::paths( $job );
		$size = (int) ( $job['checkpoint']['unpack']['tar_size'] ?? 0 );
		self::fit( $paths['tar'], $size, 'El tar descomprimido no coincide con su punto de control.', 'unpack' );
		$cursor = (array) ( $job['checkpoint']['scan'] ?? array() ) + array( 'pos' => 0, 'index' => 0, 'files' => 0, 'bytes' => 0, 'entries' => 0, 'symlinks' => 0, 'dirs' => 0, 'skipped' => 0, 'special' => array(), 'last' => '', 'zeros' => 0 );
		$deadline = self::deadline( $job, 'scan' );
		$tar = @fopen( $paths['tar'], 'rb' );
		$index = @fopen( $paths['index'], 'c+b' );
		if ( ! $tar || ! $index ) throw new Exception( 'No se pudo leer el área de trabajo de la importación.' );
		@chmod( $paths['index'], 0600 );
		$max = Digitalisimo_Backups_Archive_Inspector::max_entries();
		$units = 0;
		try {
			if ( ! ftruncate( $index, (int) $cursor['index'] ) || 0 !== fseek( $index, (int) $cursor['index'] ) ) throw new Exception( 'No se pudo preparar el índice de la importación.' );
			$pos = (int) $cursor['pos'];
			while ( $pos < $size ) {
				if ( 0 !== fseek( $tar, $pos ) ) throw new Exception( 'No se pudo leer el tar descomprimido.' );
				$block = (string) fread( $tar, 512 );
				if ( 512 !== strlen( $block ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está truncado.' );
				if ( str_repeat( "\0", 512 ) === $block ) { $pos += 512; $cursor['zeros']++; continue; }
				$cursor['zeros'] = 0;
				$long = null;
				$header = Digitalisimo_Backups_Backuply_Tar::header( $block );
				// Nombre largo GNU (L) o cabecera PAX (x): valen para la cabecera siguiente.
				while ( in_array( $header['type'], array( 'L', 'K', 'x', 'g' ), true ) ) {
					$data = $pos + 512;
					$padded = Digitalisimo_Backups_Backuply_Tar::padded( $header['size'] );
					if ( $data + $padded > $size ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está truncado.' );
					if ( 'L' === $header['type'] || 'x' === $header['type'] ) {
						if ( $header['size'] > ( 'L' === $header['type'] ? Digitalisimo_Backups_Archive_Inspector::MAX_NAME + 1 : self::MAX_INFO ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply trae una cabecera de nombre demasiado grande.' );
						fseek( $tar, $data );
						$raw = $header['size'] ? (string) fread( $tar, $header['size'] ) : '';
						if ( strlen( $raw ) !== $header['size'] ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está truncado.' );
						$long = 'L' === $header['type'] ? rtrim( $raw, "\0" ) : Digitalisimo_Backups_Backuply_Tar::pax_path( $raw, $long );
					}
					$pos = $data + $padded;
					fseek( $tar, $pos );
					$block = (string) fread( $tar, 512 );
					if ( 512 !== strlen( $block ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está truncado.' );
					$header = Digitalisimo_Backups_Backuply_Tar::header( $block );
				}
				$data = $pos + 512;
				$padded = Digitalisimo_Backups_Backuply_Tar::padded( $header['size'] );
				if ( $data + $padded > $size ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está truncado (falta el contenido de ' . Digitalisimo_Backups_Backuply_Tar::shown( $header['name'] ) . ').' );
				$name = Digitalisimo_Backups_Backuply_Tar::path( null !== $long ? $long : $header['name'] );
				$cursor['entries']++;
				if ( $cursor['entries'] > $max ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply tiene más de ' . $max . ' entradas, el máximo permitido (DIGITALISIMO_BACKUPS_MAX_ENTRIES).' );
				$type = $header['type'];
				if ( '5' === $type ) {
					$cursor['dirs']++;
				} elseif ( '2' === $type ) {
					// Backuply los guarda sin destino (lo anota en softperms.txt) y los recrea al restaurar; Backups no.
					$cursor['symlinks']++;
				} elseif ( in_array( $type, array( '0', "\0", '7' ), true ) ) {
					self::classify( $name, $data, $header, $cursor, $index );
				} else {
					throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply trae una entrada de un tipo que no se importa (enlace duro, dispositivo o tubería): ' . Digitalisimo_Backups_Backuply_Tar::shown( $name ) . '.' );
				}
				$cursor['last'] = $name;
				$pos = $data + $padded;
				$units++;
				if ( $pos < $size && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) {
					self::sync( $index );
					$cursor['pos'] = $pos;
					$cursor['index'] = (int) ftell( $index );
					$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'scan', $cursor );
					Digitalisimo_Backups_Jobs::progress( 20 + (int) floor( 5 * $pos / max( 1, $size ) ), 'Comprobando el contenido del respaldo de Backuply: ' . $cursor['entries'] . ' entradas…', true );
					return array( 'next' => 'scan', 'checkpoint' => $cursor, 'message' => 'Comprobando el contenido del respaldo de Backuply…' );
				}
			}
			self::sync( $index );
			$cursor['pos'] = $size;
			$cursor['index'] = (int) ftell( $index );
		} finally {
			fclose( $tar );
			fclose( $index );
		}
		if ( $cursor['zeros'] < 2 ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está incompleto: el tar no termina con su marca de fin (Backuply no llegó a cerrarlo).' );
		$special = (array) $cursor['special'];
		if ( empty( $special['softsql.sql'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Este respaldo de Backuply no incluye la base de datos (es «sólo archivos»): Backups sólo restaura respaldos completos. Importa uno hecho con la base de datos y los archivos.' );
		$missing = array_values( array_diff( self::REQUIRED, array_keys( (array) ( $special['required'] ?? array() ) ) ) );
		if ( $missing ) throw new Digitalisimo_Backups_Permanent_Error( 'Este respaldo de Backuply no incluye los archivos de WordPress (' . implode( ', ', $missing ) . '): es «sólo base de datos» o un respaldo parcial, y Backups sólo restaura respaldos completos.' );
		$cursor['meta'] = self::metadata( $paths['tar'], $special, $job );
		$cursor['special']['required'] = array();
		return array( 'next' => 'database', 'checkpoint' => $cursor, 'message' => 'Convirtiendo la base de datos de Backuply…' );
	}
	/** Clasifica un archivo regular: metadatos de Backuply en la raíz, carpetas que no se importan o una entrada del ZIP. */
	private static function classify( $name, $data, array $header, array &$cursor, $index ) {
		$root = false === strpos( $name, '/' );
		if ( $root && in_array( $name, self::METADATA, true ) ) {
			$cursor['special'][ $name ] = array( 'o' => $data, 's' => $header['size'] );
			return;
		}
		// El archivo de información va justo después de softver.txt, en la raíz y empieza por «<?php exit();» (se lee como texto).
		if ( $root && 'softver.txt' === $cursor['last'] && '.php' === substr( $name, -4 ) && $header['size'] <= self::MAX_INFO ) {
			$cursor['special']['info'] = array( 'o' => $data, 's' => $header['size'], 'n' => $name );
			return;
		}
		if ( preg_match( self::SKIP, $name ) || ( $root && '.maintenance' === $name ) ) { $cursor['skipped']++; return; }
		if ( ! preg_match( '//u', $name ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply trae un nombre de archivo que no es UTF-8 válido y no puede figurar en el manifiesto: ' . Digitalisimo_Backups_Backuply_Tar::shown( $name ) . '.' );
		if ( in_array( $name, self::REQUIRED, true ) ) {
			$cursor['special']['required'][ $name ] = true;
			if ( in_array( $name, array( 'wp-config.php', 'wp-includes/version.php' ), true ) ) $cursor['special'][ $name ] = array( 'o' => $data, 's' => $header['size'] );
		}
		self::put( $index, wp_json_encode( array( 'n' => $name, 'o' => $data, 's' => $header['size'], 'm' => $header['mode'], 't' => $header['mtime'] ) ) . "\n" );
		$cursor['files']++;
		$cursor['bytes'] += $header['size'];
	}
	/**
	 * Lo que el manifiesto necesita del respaldo, sin ejecutar nada: el JSON de información de Backuply, la versión de
	 * Backuply, `$table_prefix` y las constantes de la red de `wp-config.php` y `$wp_version`.
	 */
	private static function metadata( $tar, array $special, array $job ) {
		$read = function( $what, $max ) use ( $tar, $special ) {
			if ( empty( $special[ $what ] ) || (int) $special[ $what ]['s'] > $max ) return '';
			$handle = @fopen( $tar, 'rb' );
			if ( ! $handle ) return '';
			fseek( $handle, (int) $special[ $what ]['o'] );
			$raw = (int) $special[ $what ]['s'] > 0 ? (string) fread( $handle, (int) $special[ $what ]['s'] ) : '';
			fclose( $handle );
			return $raw;
		};
		$info = self::parse_info( $read( 'info', self::MAX_INFO ) ) ?: array();
		$config = Digitalisimo_Backups_Backuply_Tar::config( $read( 'wp-config.php', self::MAX_CONFIG ) );
		$version = preg_match( '/\$wp_version\s*=\s*[\'"]([0-9A-Za-z.\-]{1,32})[\'"]/', $read( 'wp-includes/version.php', self::MAX_CONFIG ), $m ) ? $m[1] : '';
		$url = is_string( $info['backup_site_url'] ?? null ) && preg_match( '#^https?://[^\s"\'<>]+$#i', $info['backup_site_url'] ) ? rtrim( $info['backup_site_url'], '/' ) : '';
		$path = is_string( $info['backup_site_path'] ?? null ) && '/' === substr( $info['backup_site_path'], 0, 1 ) && false === strpos( $info['backup_site_path'], "\0" ) && false === strpos( $info['backup_site_path'], '..' ) ? rtrim( $info['backup_site_path'], '/' ) : '';
		$softver = trim( substr( $read( 'softver.txt', 64 ), 0, 32 ) );
		return array(
			'site_url' => $url,
			'site_path' => $path,
			'created' => (int) ( $info['btime'] ?? 0 ) ?: (int) ( $job['spec']['source']['mtime'] ?? time() ),
			'name' => is_string( $info['name'] ?? null ) ? substr( preg_replace( '/[^A-Za-z0-9._-]/', '', $info['name'] ), 0, 120 ) : '',
			'backuply_version' => preg_match( '/^[0-9A-Za-z.\-]+$/', $softver ) ? $softver : '',
			'prefix' => $config['prefix'],
			'multisite' => $config['multisite'],
			'subdomain' => $config['subdomain'],
			'network' => $config['network'],
			'wordpress_version' => $version,
		);
	}

	/** Fase `database`: el `softsql.sql` del tar pasa a `database.sql` con el formato del exportador (por tramos). */
	public static function database( array $job ) {
		$paths = self::paths( $job );
		$scan = (array) ( $job['checkpoint']['scan'] ?? array() );
		$sql = (array) ( $scan['special']['softsql.sql'] ?? array() );
		$cursor = (array) ( $job['checkpoint']['database'] ?? array() );
		$deadline = self::deadline( $job, 'database' );
		$in = @fopen( $paths['tar'], 'rb' );
		$out = @fopen( $paths['sql'], 'c+b' );
		if ( ! $in || ! $out ) throw new Exception( 'No se pudo leer el área de trabajo de la importación.' );
		@chmod( $paths['sql'], 0600 );
		try {
			if ( ! ftruncate( $out, (int) ( $cursor['out'] ?? 0 ) ) || 0 !== fseek( $out, (int) ( $cursor['out'] ?? 0 ) ) ) throw new Exception( 'No se pudo preparar el SQL convertido.' );
			$translator = new Digitalisimo_Backups_Backuply_Sql( $in, (int) $sql['o'], (int) $sql['s'], $out, $cursor );
			$done = $translator->run( $deadline );
			$state = $translator->state();
		} finally {
			fclose( $in );
			fclose( $out );
		}
		$state['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'database', $cursor );
		if ( ! $done ) {
			Digitalisimo_Backups_Jobs::progress( 25 + (int) floor( 25 * (int) $state['pos'] / max( 1, (int) $sql['s'] ) ), 'Convirtiendo la base de datos de Backuply: ' . count( (array) $state['tables'] ) . ' tablas, ' . (int) $state['rows'] . ' filas…', true );
			return array( 'next' => 'database', 'checkpoint' => $state, 'message' => 'Convirtiendo la base de datos de Backuply…' );
		}
		// Las columnas ya no hacen falta: sólo la lista de tablas, en el orden del volcado.
		$state['tables'] = array();
		$prefix = (string) ( $scan['meta']['prefix'] ?? '' );
		if ( '' === $prefix || ! preg_match( '/^[A-Za-z0-9_]+$/D', $prefix ) ) $prefix = self::guess_prefix( (array) $state['order'] );
		if ( '' === $prefix ) throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo saber el prefijo de tablas del respaldo de Backuply: su wp-config.php no declara $table_prefix y ninguna tabla termina en «options».' );
		if ( ! in_array( $prefix . 'options', (array) $state['order'], true ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El SQL del respaldo de Backuply no trae la tabla ' . $prefix . 'options del prefijo de su wp-config.php: no es un respaldo completo de ese WordPress.' );
		$state['prefix'] = $prefix;
		return array( 'next' => 'files', 'checkpoint' => $state, 'message' => 'Base de datos convertida; escribiendo el ZIP…', 'warnings' => self::sql_warnings( (array) $state['notes'] ) );
	}
	private static function guess_prefix( array $tables ) {
		$best = '';
		foreach ( $tables as $table ) if ( preg_match( '/^([A-Za-z0-9_]*_)options$/D', $table, $m ) && ( '' === $best || strlen( $m[1] ) < strlen( $best ) ) ) $best = $m[1];
		return $best;
	}
	/** Avisos de lo que no se importa (van al manifiesto y a la tarjeta del trabajo). */
	public static function notices( array $notes, array $scan ) {
		$out = array();
		if ( ! empty( $notes['views'] ) ) $out[] = (int) $notes['views'] . ' vista(s) de la base de datos no se importaron (Backups no restaura vistas).';
		if ( ! empty( $notes['routines'] ) ) $out[] = (int) $notes['routines'] . ' disparador(es), evento(s) o rutina(s) de la base de datos no se importaron.';
		if ( ! empty( $notes['constraints'] ) ) $out[] = (int) $notes['constraints'] . ' grupo(s) de claves foráneas no se importaron: las tablas quedan sin esas restricciones.';
		if ( ! empty( $scan['symlinks'] ) ) $out[] = (int) $scan['symlinks'] . ' enlace(s) simbólico(s) del respaldo no se importaron (Backuply los guarda sin su destino).';
		return $out;
	}
	private static function sql_warnings( array $notes ) {
		$out = array();
		foreach ( self::notices( $notes, array() ) as $text ) $out[] = array( 'notice' => 'import-' . md5( $text ), 'text' => $text );
		return $out;
	}

	/**
	 * Fase `files`: el ZIP con el escritor de sólo añadir de T2. Primero `database.sql`, después cada archivo del índice
	 * como `wordpress/<ruta>`; una entrada grande puede quedar a medias entre tramos (como en `pack`).
	 */
	public static function files( array $job ) {
		$paths = self::paths( $job );
		$scan = (array) ( $job['checkpoint']['scan'] ?? array() );
		$cursor = (array) ( $job['checkpoint']['files'] ?? array() ) + array( 'zip' => 0, 'entries' => 0, 'index' => 0, 'count' => 0, 'bytes' => 0, 'partial' => null, 'sql' => false );
		if ( 0 === (int) $cursor['zip'] ) self::space( dirname( $paths['zip'] ), (int) $scan['bytes'] + (int) @filesize( $paths['sql'] ), 'escribir el ZIP convertido' );
		$deadline = self::deadline( $job, 'files' );
		$zip = null;
		$entries = null;
		$index = null;
		$units = 0;
		try {
			$zip = new Digitalisimo_Backups_Zip_Writer( $paths['zip'], (int) $cursor['zip'] );
			$entries = self::append( $paths['entries'], (int) $cursor['entries'] );
			$handles = array( 'tar' => @fopen( $paths['tar'], 'rb' ), 'sql' => @fopen( $paths['sql'], 'rb' ) );
			if ( ! $handles['tar'] || ! $handles['sql'] ) throw new Exception( 'No se pudo leer el área de trabajo de la importación.' );
			try {
				$index = @fopen( $paths['index'], 'rb' );
				if ( ! $index ) throw new Exception( 'No se pudo leer el índice de la importación.' );
				$limit = (int) $scan['index'];
				// Una entrada que quedó a medias en el tramo anterior (también database.sql) sigue donde se quedó.
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
				fseek( $index, (int) $cursor['index'] );
				while ( (int) $cursor['index'] < $limit ) {
					if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) return self::files_slice( $job, $zip, $entries, $cursor );
					$line = fgets( $index );
					if ( false === $line ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El índice de la importación está truncado.', 'scan' );
					$next = (int) $cursor['index'] + strlen( $line );
					$entry = json_decode( $line, true );
					if ( ! is_array( $entry ) || ! isset( $entry['n'], $entry['o'], $entry['s'] ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El índice de la importación está dañado.', 'scan' );
					$name = 'wordpress/' . $entry['n'];
					$zip->begin( $name, (int) $entry['t'], (int) $entry['m'], (int) $entry['s'], Digitalisimo_Backups_Zip_Writer::method_for( $name, (int) $entry['s'] ) );
					$units++;
					if ( ! self::copy( $zip, $handles['tar'], (int) $entry['o'], (int) $entry['s'], $cursor, $deadline, array( 'src' => 'tar', 'o' => (int) $entry['o'], 's' => (int) $entry['s'], 'next' => $next ) ) ) return self::files_slice( $job, $zip, $entries, $cursor );
					self::done( $zip, $entries, $cursor );
					$cursor['index'] = $next;
				}
			} finally {
				foreach ( $handles as $handle ) if ( $handle ) fclose( $handle );
				if ( $index ) fclose( $index );
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
	/** Punto de control de un tramo de `files`: lo escrito del ZIP y de la lista de entradas, a salvo en el disco. */
	private static function files_slice( array $job, $zip, $entries, array $cursor ) {
		$zip->commit();
		self::sync( $entries );
		$cursor['zip'] = is_array( $cursor['partial'] ) ? (int) $cursor['partial']['zip']['p'] + (int) $cursor['partial']['zip']['z'] : $zip->size();
		$cursor['entries'] = (int) ftell( $entries );
		$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'files', (array) ( $job['checkpoint']['files'] ?? array() ) );
		$total = max( 1, (int) ( $job['checkpoint']['scan']['bytes'] ?? 1 ) );
		Digitalisimo_Backups_Jobs::progress( 50 + (int) floor( 44 * min( 1, (int) $cursor['bytes'] / $total ) ), 'Escribiendo el ZIP convertido: ' . (int) $cursor['count'] . ' archivos…', true );
		return array( 'next' => 'files', 'checkpoint' => $cursor, 'message' => 'Escribiendo el ZIP convertido…' );
	}
	/**
	 * Copia `$length` bytes desde `$from` a la entrada abierta. true al terminar; con el plazo vencido deja la entrada
	 * suspendida en `$cursor['partial']` y devuelve false.
	 */
	private static function copy( $zip, $handle, $from, $length, array &$cursor, $deadline, array $state = array() ) {
		if ( $length <= 0 ) return true;
		if ( 0 !== fseek( $handle, $from ) ) throw new Exception( 'No se pudo leer el tar descomprimido.' );
		$left = $length;
		while ( $left > 0 ) {
			$chunk = fread( $handle, (int) min( Digitalisimo_Backups_Zip_Writer::CHUNK, $left ) );
			if ( false === $chunk || '' === $chunk ) throw new Exception( 'No se pudo leer el tar descomprimido.' );
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
		$record = $zip->finish();
		self::put( $entries, wp_json_encode( $record ) . "\n" );
		$cursor['count']++;
	}

	/**
	 * Fase `finish`: manifiesto v3 sin firma (por partes, como `pack`), directorio central, comprobación del ZIP con el
	 * inspector de archivos no confiables y publicación en el almacén con `rename()`.
	 */
	public static function finish( array $job ) {
		$paths = self::paths( $job );
		$spec = (array) $job['spec'];
		$storage = trailingslashit( Digitalisimo_Backups::storage_directory() );
		$done = (array) ( $job['checkpoint']['finish'] ?? array() );
		// Murió después de publicar: el ZIP ya está en el almacén con su huella.
		if ( ! empty( $done['published'] ) && is_file( $storage . $done['published'] ) ) return self::published( $job, (string) $done['published'] );
		$scan = (array) $job['checkpoint']['scan'];
		$database = (array) $job['checkpoint']['database'];
		$files = (array) $job['checkpoint']['files'];
		$meta = (array) ( $scan['meta'] ?? array() );
		$zip = new Digitalisimo_Backups_Zip_Writer( $paths['zip'], (int) $files['zip'] );
		try {
			$totals = array( 'count' => 0, 'bytes' => 0 );
			foreach ( Digitalisimo_Backups_Packer::records( $paths['entries'], (int) $files['entries'] ) as $record ) { $totals['count']++; $totals['bytes'] += (int) $record['s']; }
			if ( $totals['count'] !== (int) $files['count'] ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La lista de entradas del ZIP convertido no coincide con su punto de control.', 'files' );
			$notices = self::notices( (array) $database['notes'], $scan );
			$head = array(
				'format' => 'digitalisimo-backup/v3',
				'backup_format_version' => 3,
				'backup_id' => wp_generate_uuid4(),
				'scope' => 'installation',
				'type' => 'full',
				'created_at' => gmdate( 'c', (int) $meta['created'] ),
				'completed_at' => gmdate( 'c' ),
				'home_url' => '' !== $meta['site_url'] ? $meta['site_url'] : 'http://backuply.invalid',
				'site_url' => '' !== $meta['site_url'] ? $meta['site_url'] : 'http://backuply.invalid',
				'wordpress_version' => (string) $meta['wordpress_version'],
				'php_version' => '',
				'database_version' => '',
				'database_name' => '',
				'database_size' => (int) $database['out'],
				'database_consistency' => 'backuply',
				'table_prefix' => (string) $database['prefix'],
				'origin' => self::origin( $meta, (string) $database['prefix'] ),
				'database_tables' => array_values( (array) $database['order'] ),
				'multisite' => (bool) $meta['multisite'],
				'file_count' => $totals['count'] - 1,
				'uncompressed_size' => $totals['bytes'],
				'checksum_algorithm' => 'sha256',
				'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '',
				'imported_from' => array( 'format' => self::FORMAT, 'backuply_version' => (string) $meta['backuply_version'], 'name' => (string) $meta['name'], 'file' => (string) $spec['source']['name'], 'size' => (int) $spec['source']['size'], 'site_url' => (string) $meta['site_url'], 'site_path' => (string) $meta['site_path'], 'trust' => 'untrusted' ),
			);
			$tail = array( 'job_id' => (string) $job['id'], 'packer' => self::PACKER, 'database_snapshot_at' => gmdate( 'c', (int) $meta['created'] ) );
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
			// Sin firma: el comentario del ZIP va vacío. Es un archivo de fuera y así lo trata la restauración.
			$count = $zip->close_archive( function() use ( $paths, $size ) { return Digitalisimo_Backups_Packer::records( $paths['entries'], $size ); }, '' );
			$zip->commit();
		} finally {
			$zip->close();
		}
		if ( $count !== $totals['count'] + 1 ) throw new Exception( 'El directorio central del ZIP convertido no tiene todas las entradas.' );
		self::check_zip( $paths['zip'] );
		$file = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $paths, $storage, $spec ) {
			$file = self::free_name( (string) $spec['file'] );
			if ( ! @rename( $paths['zip'], $storage . $file ) ) throw new Exception( 'No se pudo mover el ZIP convertido al almacén privado.' );
			@chmod( $storage . $file, 0600 );
			return $file;
		} );
		return self::published( $job, $file, $notices, $head );
	}
	private static function published( array $job, $file, array $notices = array(), array $head = array() ) {
		$scan = (array) ( $job['checkpoint']['scan'] ?? array() );
		$database = (array) ( $job['checkpoint']['database'] ?? array() );
		$result = array( 'import' => array( 'file' => $file, 'source' => (string) $job['spec']['source']['name'], 'origin' => (string) $job['spec']['source']['origin'], 'files' => (int) ( $scan['files'] ?? 0 ), 'tables' => count( (array) ( $database['order'] ?? array() ) ), 'rows' => (int) ( $database['rows'] ?? 0 ), 'prefix' => (string) ( $database['prefix'] ?? '' ), 'site_url' => (string) ( $head['home_url'] ?? '' ), 'multisite' => ! empty( $head['multisite'] ), 'notices' => $notices, 'confirmation' => 'RESTAURAR SIN FIRMA' ) );
		return array( 'next' => null, 'checkpoint' => array( 'published' => $file ), 'result' => $result, 'message' => 'Respaldo de Backuply importado como ' . $file . ': aparece en «Restaurar respaldo local» como archivo sin firma (escribe RESTAURAR SIN FIRMA para restaurarlo).' . ( $notices ? ' Avisos: ' . implode( ' ', $notices ) : '' ) );
	}
	/** Nombre libre en el almacén: ni un archivo ni una fila del historial con ese nombre. */
	private static function free_name( $wanted ) {
		$storage = trailingslashit( Digitalisimo_Backups::storage_directory() );
		$known = array_flip( array_map( 'strval', array_column( (array) get_option( Digitalisimo_Backups::HISTORY, array() ), 'file' ) ) );
		$free = function( $candidate ) use ( $storage, $known ) { return ! file_exists( $storage . $candidate ) && ! is_link( $storage . $candidate ) && ! isset( $known[ $candidate ] ); };
		if ( $free( $wanted ) ) return $wanted;
		$stem = substr( $wanted, 0, -4 );
		do { $candidate = $stem . '-' . bin2hex( random_bytes( 3 ) ) . '.zip'; } while ( ! $free( $candidate ) );
		return $candidate;
	}
	/**
	 * Comprobación del ZIP convertido con lo mismo que lo leerá la restauración: entradas (rutas, duplicados, mayúsculas,
	 * tipos y métodos) y el manifiesto v3 estricto, por partes. El contenido entero (CRC y SHA-256) lo comprueba la
	 * verificación de la restauración, por tramos.
	 */
	private static function check_zip( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) return;
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) throw new Exception( 'El ZIP convertido no se puede abrir.' );
		try {
			Digitalisimo_Backups_Archive_Inspector::entries( $zip, false );
			Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
		} catch ( Exception $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply no se puede convertir: ' . $e->getMessage() );
		} finally {
			$zip->close();
		}
	}
	/** Bloque `origin` del manifiesto (el de Digitalisimo_Backups_Relocate::describe()) con lo que dice el respaldo. */
	private static function origin( array $meta, $prefix ) {
		$path = (string) $meta['site_path'];
		$out = array(
			'version' => Digitalisimo_Backups_Relocate::VERSION,
			'home' => (string) $meta['site_url'],
			'siteurl' => (string) $meta['site_url'],
			'abspath' => $path,
			'abspath_real' => '',
			'content_dir' => '' === $path ? '' : $path . '/wp-content',
			'uploads_dir' => '' === $path ? '' : $path . '/wp-content/uploads',
			'prefix' => $prefix,
			'multisite' => (bool) $meta['multisite'],
			'subdomain' => (bool) $meta['subdomain'],
		);
		if ( $meta['multisite'] && is_array( $meta['network'] ) && ! empty( $meta['network']['domain'] ) ) $out['network'] = $meta['network'];
		return $out;
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
	/**
	 * Un archivo del área de trabajo que ya terminó su fase: lo que pase de su tamaño confirmado lo escribió un proceso que
	 * murió después de su punto de control y se recorta; si es más corto, la fase que lo escribe se repite.
	 */
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

/** Cabeceras del tar de Backuply (GNU «ustar  » y POSIX «ustar\0»), sin leer nunca más de lo declarado. */
final class Digitalisimo_Backups_Backuply_Tar {
	/** Cabecera de 512 bytes comprobada: nombre, modo, tamaño, fecha y tipo. */
	public static function header( $block ) {
		$sum = 0;
		for ( $i = 0; $i < 512; $i++ ) $sum += ( $i >= 148 && $i < 156 ) ? 32 : ord( $block[ $i ] );
		$declared = self::octal( substr( $block, 148, 8 ) );
		if ( null === $declared || $declared !== $sum ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está dañado: una cabecera del tar no supera su suma de comprobación.' );
		$size = self::number( substr( $block, 124, 12 ) );
		if ( null === $size || $size < 0 ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply está dañado: una cabecera del tar declara un tamaño no válido.' );
		$name = self::field( substr( $block, 0, 100 ) );
		$magic = substr( $block, 257, 6 );
		$prefix = "ustar\0" === $magic ? self::field( substr( $block, 345, 155 ) ) : '';
		if ( '' !== $prefix ) $name = $prefix . '/' . $name;
		$type = $block[156];
		return array( 'name' => $name, 'mode' => (int) ( self::octal( substr( $block, 100, 8 ) ) ?? 0644 ) & 0777, 'size' => $size, 'mtime' => (int) ( self::number( substr( $block, 136, 12 ) ) ?? 0 ), 'type' => $type );
	}
	public static function padded( $size ) { return (int) ( ceil( $size / 512 ) * 512 ); }
	private static function field( $raw ) { $end = strpos( $raw, "\0" ); return false === $end ? $raw : substr( $raw, 0, $end ); }
	private static function octal( $raw ) {
		$text = trim( str_replace( "\0", ' ', $raw ) );
		if ( '' === $text ) return 0;
		return preg_match( '/^[0-7]+$/D', $text ) ? (int) octdec( $text ) : null;
	}
	/** Octal o, con el bit alto, binario GNU (archivos de 8 GiB o más). */
	private static function number( $raw ) {
		if ( '' !== $raw && ( ord( $raw[0] ) & 0x80 ) ) {
			if ( ord( $raw[0] ) & 0x40 ) return null; // Negativo.
			$value = ord( $raw[0] ) & 0x3f;
			for ( $i = 1; $i < strlen( $raw ); $i++ ) {
				if ( $value > ( PHP_INT_MAX >> 8 ) ) return null;
				$value = ( $value << 8 ) | ord( $raw[ $i ] );
			}
			return $value;
		}
		return self::octal( $raw );
	}
	/** `path` de una cabecera PAX («longitud clave=valor\n»); lo demás se ignora. */
	public static function pax_path( $raw, $fallback ) {
		$i = 0;
		$n = strlen( $raw );
		$path = $fallback;
		while ( $i < $n ) {
			if ( ! preg_match( '/\G([0-9]{1,6}) /', $raw, $m, 0, $i ) ) break;
			$length = (int) $m[1];
			if ( $length <= strlen( $m[0] ) || $i + $length > $n ) break;
			$record = substr( $raw, $i + strlen( $m[0] ), $length - strlen( $m[0] ) - 1 );
			if ( 0 === strpos( $record, 'path=' ) ) $path = substr( $record, 5 );
			$i += $length;
		}
		return $path;
	}
	/**
	 * Ruta relativa segura de una entrada (sin `./` delante ni barra final), o excepción: nada absoluto, ni unidades, ni
	 * `..`, ni NUL, ni barra invertida, ni segmentos o nombres demasiado largos.
	 */
	public static function path( $raw ) {
		$name = (string) $raw;
		while ( 0 === strpos( $name, './' ) ) $name = substr( $name, 2 );
		$name = rtrim( $name, '/' );
		$bad = '' === $name || strlen( $name ) > Digitalisimo_Backups_Archive_Inspector::MAX_NAME || false !== strpos( $name, "\0" ) || false !== strpos( $name, '\\' ) || '/' === $name[0] || preg_match( '/^[A-Za-z]:/', $name );
		if ( ! $bad ) foreach ( explode( '/', $name ) as $segment ) if ( '' === $segment || '.' === $segment || '..' === $segment || strlen( $segment ) > Digitalisimo_Backups_Archive_Inspector::MAX_SEGMENT ) $bad = true;
		if ( $bad ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de Backuply contiene una ruta no permitida: ' . self::shown( $raw ) );
		return $name;
	}
	public static function shown( $name ) {
		$name = preg_replace( '/[\x00-\x1F\x7F]/', '?', (string) $name );
		return strlen( $name ) > 120 ? substr( $name, 0, 117 ) . '…' : $name;
	}
	/**
	 * `$table_prefix` y las constantes de la red de un wp-config.php, sin ejecutarlo: los comentarios fuera
	 * (token_get_all, si está; si no, se quitan con expresiones) y expresiones sobre lo que queda.
	 */
	public static function config( $php ) {
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
		$define = function( $name ) use ( $code ) {
			if ( ! preg_match( '/\bdefine\s*\(\s*[\'"]' . $name . '[\'"]\s*,\s*(true|false|[0-9]+|\'[^\'\\\\]*\'|"[^"\\\\$]*")\s*\)/i', $code, $m ) ) return null;
			$value = strtolower( $m[1] );
			if ( 'true' === $value ) return true;
			if ( 'false' === $value ) return false;
			if ( is_numeric( $m[1] ) ) return (int) $m[1];
			return substr( $m[1], 1, -1 );
		};
		$prefix = preg_match( '/\$table_prefix\s*=\s*[\'"]([A-Za-z0-9_]{1,64})[\'"]\s*;/', $code, $m ) ? $m[1] : '';
		$multisite = true === $define( 'MULTISITE' ) || 1 === $define( 'MULTISITE' );
		$domain = $define( 'DOMAIN_CURRENT_SITE' );
		$path = $define( 'PATH_CURRENT_SITE' );
		$network = array();
		if ( $multisite && is_string( $domain ) && preg_match( '/^[A-Za-z0-9.\-]+(?::[0-9]+)?$/D', $domain ) ) $network = array( 'domain' => strtolower( $domain ), 'path' => is_string( $path ) && '' !== $path ? '/' . trim( $path, '/' ) . ( '/' === $path || '' === trim( $path, '/' ) ? '' : '/' ) : '/' );
		if ( isset( $network['path'] ) && '//' === $network['path'] ) $network['path'] = '/';
		$subdomain = $define( 'SUBDOMAIN_INSTALL' );
		return array( 'prefix' => $prefix, 'multisite' => $multisite, 'subdomain' => true === $subdomain || 1 === $subdomain, 'network' => $network );
	}
}

/**
 * Importación como trabajo del motor de trabajos (`kind = import`): cola, fases con punto de control, arrendamiento,
 * latido y reanudación, igual que un respaldo. Fases:
 *
 * 1. `unpack`: el `.tar.gz` se descomprime a un tar en el área de trabajo, por tramos.
 * 2. `scan`: cada cabecera del tar se comprueba y se anotan los archivos (índice en el área de trabajo).
 * 3. `database`: `softsql.sql` pasa al SQL del exportador (Digitalisimo_Backups_Backuply_Sql), por tramos.
 * 4. `files`: el ZIP con el escritor de T2 (`database.sql` y `wordpress/…`), por tramos.
 * 5. `finish`: manifiesto v3 sin firma, comprobación con el inspector de archivos no confiables y publicación.
 *
 * Nada toca el sitio: el resultado es un archivo del almacén. Se puede cancelar en cualquier fase menos la última; una
 * cancelación o un fallo retiran el área de trabajo y nunca dejan un ZIP a medias en el almacén.
 */
final class Digitalisimo_Backups_Import_Job {
	const PHASES = array( 'unpack', 'scan', 'database', 'files', 'finish' );
	const LABELS = array( 'unpack' => 'Descompresión', 'scan' => 'Comprobación del archivo', 'files' => 'ZIP convertido', 'finish' => 'Manifiesto y publicación' );
	const FLOORS = array( 'unpack' => 2, 'scan' => 20, 'database' => 25, 'files' => 50, 'finish' => 94 );

	public static function is_import( $job ) { return is_array( $job ) && 'import' === ( $job['kind'] ?? '' ); }
	public static function run_phase( $phase, array $job ) {
		if ( Digitalisimo_Backups_Job_Store::JOB_FORMAT !== (int) ( $job['spec']['engine']['job_format'] ?? 0 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La importación se creó con otra versión del motor; vuelve a iniciarla.' );
		switch ( $phase ) {
			case 'unpack': return Digitalisimo_Backups_Backuply_Import::unpack( $job );
			case 'scan': return Digitalisimo_Backups_Backuply_Import::scan( $job );
			case 'database': return Digitalisimo_Backups_Backuply_Import::database( $job );
			case 'files': return Digitalisimo_Backups_Backuply_Import::files( $job );
			case 'finish': return Digitalisimo_Backups_Backuply_Import::finish( $job );
		}
		throw new Digitalisimo_Backups_Permanent_Error( 'La importación está en una fase desconocida.' );
	}
	/** Al terminar sin éxito, el área de trabajo se retira (el motor sólo la retira al terminar bien). */
	public static function on_terminal( array $job, $status, $message ) {
		Digitalisimo_Backups_Job_Store::remove_staging( $job['id'] );
		return 'cancelled' === $status ? 'Importación cancelada: no se dejó nada en el almacén.' : (string) $message;
	}
	public static function discard( array $job, $phase ) {} // Cada fase empieza truncando a su punto de control.
	public static function cancellable( $phase ) { return 'finish' !== $phase; }
}
