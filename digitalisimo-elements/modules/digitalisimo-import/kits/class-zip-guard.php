<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Guardián del ZIP de un kit (§7.2, D13, CR-3, CR-19, CV-22).
 *
 * `inspect()` decide si un ZIP es un kit admisible **sin extraer nada a disco** (salvo la copia temporal de un ZIP
 * anidado, que hay que abrir). Rechaza con un mensaje en español:
 * - rutas con `..`, absolutas, con `\`, con NUL, repetidas sin distinguir mayúsculas o enlaces simbólicos;
 * - ejecutables de servidor y `.htaccess` en cualquier lugar del ZIP;
 * - dentro del kit, cualquier extensión que no sea `json` o `xml`, los archivos propios de `digitalisimo/` con las
 *   formas de §5.2 y las capturas `jpg`, `png` y `webp`;
 * - más entradas, más bytes o más compresión que los límites, y un tamaño falso en el directorio central;
 * - JSON demasiado grandes, demasiado profundos o con un esquema mínimo incorrecto;
 * - XML demasiado grandes o con `<!DOCTYPE` o `<!ENTITY`;
 * - más de un nivel de ZIP anidado.
 * Lo que queda fuera del kit (la documentación de un ZIP de marketplace) se ignora, `__MACOSX/` y los ocultos también.
 */
final class Zip_Guard {
	/** Límites de D13. Se cambian con el filtro `digitalisimo_elements_kit_guard_limits`. */
	const LIMITS = array(
		'unzipped'         => 536870912, // 512 MB descomprimidos.
		'entries'          => 5000,
		'ratio'            => 100,       // Relación de compresión máxima…
		'ratio_min'        => 1048576,   // …en entradas de más de 1 MB.
		'json'             => 33554432,  // 32 MB.
		'json_depth'       => 256,
		'xml'              => 67108864,  // 64 MB.
		'nesting'          => 1,
		'session_download' => 536870912, // 512 MB de descargas por sesión.
		'download_timeout' => 20,
		'redirects'        => 3,
		'request_network'  => 20,        // Segundos de red acumulados por petición (CV-23).
		'request_media'    => 15,        // Segundos de la petición tras los que no se descarga otro medio (A13).
		'file'             => 20971520,  // 20 MB por archivo descargado.
	);

	/** Ejecutables de servidor: rechazan el kit entero en cualquier lugar del ZIP. */
	const FORBIDDEN = array( 'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps', 'pht', 'pgif', 'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp' );

	/** Capturas que se admiten dentro del kit (en la raíz o en `screenshots/`) y nunca se extraen. */
	const SCREENSHOTS = array( 'jpg', 'jpeg', 'png', 'webp' );

	const READ_CHUNK = 1048576;

	/** Límites vigentes. */
	public static function limits(): array {
		$limits = self::LIMITS;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( Module::FILTER_GUARD_LIMITS, $limits );
			if ( is_array( $filtered ) ) {
				foreach ( $limits as $key => $value ) {
					if ( isset( $filtered[ $key ] ) && is_numeric( $filtered[ $key ] ) && $filtered[ $key ] > 0 ) {
						$limits[ $key ] = (int) $filtered[ $key ];
					}
				}
			}
		}
		return $limits;
	}

	/**
	 * Inspecciona un ZIP.
	 *
	 * @param string     $zip    Ruta del ZIP subido.
	 * @param array|null $limits Límites; por defecto, `limits()`.
	 *
	 * @throws \RuntimeException Con el motivo, en español, si el ZIP no es un kit admisible o falta `ZipArchive`.
	 */
	public static function inspect( string $zip, ?array $limits = null ): Inspection {
		if ( ! class_exists( '\ZipArchive' ) ) {
			throw new \RuntimeException( 'Este servidor no tiene la extensión ZipArchive de PHP: no se pueden importar kits hasta activarla.' );
		}
		$limits = null === $limits ? self::limits() : array_replace( self::LIMITS, $limits );
		return self::inspect_archive( $zip, $limits, 0, '' );
	}

	/**
	 * Decodifica un JSON del kit con el límite de profundidad (D13).
	 *
	 * @throws \RuntimeException Si no es JSON válido o no es un objeto o una lista.
	 */
	public static function decode( string $json, string $path, int $depth = 0 ): array {
		$depth = $depth > 0 ? $depth : self::LIMITS['json_depth'];
		try {
			$data = json_decode( $json, true, $depth, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING );
		} catch ( \JsonException $error ) {
			throw new \RuntimeException( sprintf( 'El archivo %s del kit no es un JSON válido o es demasiado profundo.', $path ) );
		}
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( sprintf( 'El archivo %s del kit debe contener un objeto o una lista.', $path ) );
		}
		return $data;
	}

	/** @throws \RuntimeException */
	private static function inspect_archive( string $file, array $limits, int $depth, string $temporary ): Inspection {
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			self::fail( 'El archivo no es un ZIP válido.', $temporary );
		}
		try {
			$count = (int) $zip->numFiles; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( $count > $limits['entries'] ) {
				self::fail( sprintf( 'El ZIP tiene %1$d entradas; el máximo es %2$d.', $count, $limits['entries'] ), $temporary );
			}

			$all   = array();
			$names = array();
			$bytes = 0;
			$raw   = self::raw_names( $file );
			for ( $i = 0; $i < $count; $i++ ) {
				$stat = $zip->statIndex( $i );
				if ( false === $stat || ! isset( $stat['name'] ) ) {
					self::fail( 'El directorio del ZIP está dañado.', $temporary );
				}
				$name = (string) $stat['name'];
				if ( null !== $raw && isset( $raw[ $i ] ) && false !== strpos( $raw[ $i ], "\0" ) ) {
					// libzip cambia el NUL por un espacio: sólo el directorio central crudo lo deja ver.
					self::fail( sprintf( 'El ZIP contiene una ruta no admitida: %s.', self::printable( $raw[ $i ] ) ), $temporary );
				}
				self::check_name( $name, $temporary );
				$lower = strtolower( $name );
				if ( isset( $names[ $lower ] ) ) {
					self::fail( sprintf( 'El ZIP repite la entrada %s.', $name ), $temporary );
				}
				$names[ $lower ] = true;
				if ( self::is_symlink( $zip, $i ) ) {
					self::fail( sprintf( 'El ZIP contiene un enlace simbólico: %s.', $name ), $temporary );
				}
				$base = strtolower( basename( rtrim( $name, '/' ) ) );
				$ext  = strtolower( (string) pathinfo( $base, PATHINFO_EXTENSION ) );
				if ( '.htaccess' === $base || '.user.ini' === $base || in_array( $ext, self::FORBIDDEN, true ) ) {
					self::fail( sprintf( 'El ZIP contiene un archivo ejecutable o de configuración del servidor: %s.', $name ), $temporary );
				}
				if ( '/' === substr( $name, -1 ) ) {
					continue;
				}
				$size       = (int) $stat['size'];
				$compressed = (int) $stat['comp_size'];
				$bytes     += $size;
				if ( $bytes > $limits['unzipped'] ) {
					self::fail( sprintf( 'El kit ocupa más de %d MB descomprimido.', (int) ( $limits['unzipped'] / 1048576 ) ), $temporary );
				}
				if ( $size > $limits['ratio_min'] && ( $compressed <= 0 || $size > $compressed * $limits['ratio'] ) ) {
					self::fail( sprintf( 'La entrada %s está comprimida de forma anómala.', $name ), $temporary );
				}
				if ( self::ignored( $name ) ) {
					continue;
				}
				$all[ $name ] = array(
					'name'  => $name,
					'index' => $i,
					'size'  => $size,
					'ext'   => $ext,
				);
			}

			$prefix = self::root( $all );
			if ( null === $prefix ) {
				$inner = self::single_zip( $all );
				if ( null === $inner ) {
					self::fail( 'El ZIP no es un kit: no tiene manifest.json en la raíz ni en una sola subcarpeta.', $temporary );
				}
				if ( $depth >= $limits['nesting'] ) {
					self::fail( 'El ZIP tiene más de un nivel de ZIP anidado.', $temporary );
				}
				$copy = self::temporary_file();
				$data = $zip->getFromIndex( $inner['index'], $inner['size'] + 1 );
				if ( false === $data || strlen( $data ) !== $inner['size'] || false === file_put_contents( $copy, $data ) ) {
					@unlink( $copy ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
					self::fail( 'No se pudo leer el ZIP interior del kit.', $temporary );
				}
				unset( $data );
				$zip->close();
				$zip = null;
				self::drop( $temporary );
				return self::inspect_archive( $copy, $limits, $depth + 1, $copy );
			}

			$entries = array();
			$ignored = 0;
			$total   = 0;
			foreach ( $all as $name => $entry ) {
				if ( '' !== $prefix && 0 !== strpos( $name, $prefix ) ) {
					++$ignored;
					continue;
				}
				$relative = self::relative( substr( $name, strlen( $prefix ) ) );
				$kind     = self::kind( $relative, $entry['ext'] );
				if ( 'reject' === $kind ) {
					self::fail( sprintf( 'El kit contiene un archivo no admitido: %s.', $relative ), $temporary );
				}
				if ( 'skip' === $kind ) {
					++$ignored;
					continue;
				}
				if ( isset( $entries[ $relative ] ) ) {
					self::fail( sprintf( 'El kit repite la entrada %s.', $relative ), $temporary );
				}
				$entry['kind']        = $kind;
				$entries[ $relative ] = $entry;
				$total               += $entry['size'];
			}
			if ( ! isset( $entries['manifest.json'] ) ) {
				self::fail( 'El kit no tiene manifest.json.', $temporary );
			}

			$inspection = new Inspection( $file, $prefix, $entries, $ignored, $total, $temporary );
			$temporary  = '';
			foreach ( $entries as $relative => $entry ) {
				if ( 'json' === $entry['kind'] ) {
					self::check_json( $zip, $inspection, $relative, $entry, $limits );
				} elseif ( 'xml' === $entry['kind'] ) {
					self::check_xml( $zip, $relative, $entry, $limits );
				}
			}
			return $inspection;
		} catch ( \RuntimeException $error ) {
			if ( isset( $inspection ) ) {
				$inspection->cleanup();
			}
			self::drop( $temporary );
			throw $error;
		} finally {
			if ( $zip instanceof \ZipArchive ) {
				@$zip->close(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- puede estar cerrado ya.
			}
		}
	}

	/** Comprueba el nombre de una entrada. */
	private static function check_name( string $name, string $temporary ): void {
		if ( '' === $name || false !== strpos( $name, "\0" ) || false !== strpos( $name, '\\' ) || '/' === $name[0] || preg_match( '#^[A-Za-z]:#', $name ) ) {
			self::fail( sprintf( 'El ZIP contiene una ruta no admitida: %s.', self::printable( $name ) ), $temporary );
		}
		foreach ( explode( '/', rtrim( $name, '/' ) ) as $segment ) {
			if ( '..' === $segment || '.' === $segment || '' === $segment ) {
				self::fail( sprintf( 'El ZIP contiene una ruta no admitida: %s.', self::printable( $name ) ), $temporary );
			}
		}
	}

	/** ¿Es un enlace simbólico (atributos externos de Unix)? */
	private static function is_symlink( \ZipArchive $zip, int $index ): bool {
		$opsys = 0;
		$attr  = 0;
		if ( ! $zip->getExternalAttributesIndex( $index, $opsys, $attr ) ) {
			return false;
		}
		return \ZipArchive::OPSYS_UNIX === $opsys && 0120000 === ( ( $attr >> 16 ) & 0170000 );
	}

	/** `__MACOSX/` y los archivos o carpetas ocultos se ignoran. */
	private static function ignored( string $name ): bool {
		foreach ( explode( '/', $name ) as $segment ) {
			if ( '__MACOSX' === $segment || ( '' !== $segment && '.' === $segment[0] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Subcarpeta raíz del kit: '' si `manifest.json` está en la raíz, `<carpeta>/` si está en una sola subcarpeta, o
	 * null si no hay ninguna.
	 *
	 * @throws \RuntimeException Si hay más de una subcarpeta con manifiesto.
	 */
	private static function root( array $entries ): ?string {
		$found = array();
		foreach ( array_keys( $entries ) as $name ) {
			$lower = strtolower( (string) $name );
			if ( 'manifest.json' === $lower ) {
				return '';
			}
			if ( preg_match( '#^([^/]+/)manifest\.json$#', $lower ) ) {
				$found[] = substr( (string) $name, 0, strpos( (string) $name, '/' ) + 1 );
			}
		}
		if ( count( $found ) > 1 ) {
			throw new \RuntimeException( 'El ZIP contiene más de un kit: deja uno solo.' );
		}
		return $found ? $found[0] : null;
	}

	/** El único `.zip` del ZIP, o null. */
	private static function single_zip( array $entries ): ?array {
		$zips = array_values(
			array_filter(
				$entries,
				function ( $entry ) {
					return 'zip' === $entry['ext'];
				}
			)
		);
		return 1 === count( $zips ) ? $zips[0] : null;
	}

	/**
	 * Ruta relativa al kit con la carpeta de primer nivel conocida y la extensión en minúsculas: un kit con
	 * `TEMPLATES/A.JSON` se lee como `templates/A.json`.
	 */
	private static function relative( string $relative ): string {
		$segments = explode( '/', $relative );
		$known    = array( 'templates', 'content', 'taxonomies', 'wp-content', 'digitalisimo', 'screenshots', 'manifest.json', 'site-settings.json' );
		if ( in_array( strtolower( $segments[0] ), $known, true ) ) {
			$segments[0] = strtolower( $segments[0] );
		}
		$last = count( $segments ) - 1;
		$ext  = pathinfo( $segments[ $last ], PATHINFO_EXTENSION );
		if ( '' !== $ext ) {
			$segments[ $last ] = substr( $segments[ $last ], 0, -strlen( $ext ) ) . strtolower( $ext );
		}
		return implode( '/', $segments );
	}

	/** Qué es una entrada del kit: `json`, `xml`, `file` (de `digitalisimo/`), `skip` o `reject`. */
	private static function kind( string $relative, string $ext ): string {
		if ( 0 === strpos( $relative, Module::KIT_FOLDER ) ) {
			$inner = substr( $relative, strlen( Module::KIT_FOLDER ) );
			if ( 0 === strpos( $inner, 'media/' ) || 0 === strpos( $inner, 'files/' ) ) {
				return Module::is_kit_file( $inner ) ? 'file' : 'reject';
			}
			return preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*\.json$/', $inner ) ? 'json' : 'reject';
		}
		if ( 'json' === $ext ) {
			return 'json';
		}
		if ( 'xml' === $ext ) {
			return 'xml';
		}
		if ( in_array( $ext, self::SCREENSHOTS, true ) && ( false === strpos( $relative, '/' ) || 0 === strpos( $relative, 'screenshots/' ) ) ) {
			return 'skip';
		}
		return 'reject';
	}

	/** @throws \RuntimeException */
	private static function check_json( \ZipArchive $zip, Inspection $inspection, string $relative, array $entry, array $limits ): void {
		if ( $entry['size'] > $limits['json'] ) {
			throw new \RuntimeException( sprintf( 'El archivo %1$s ocupa más de %2$d MB.', $relative, (int) ( $limits['json'] / 1048576 ) ) );
		}
		$json = $zip->getFromIndex( $entry['index'], $entry['size'] + 1 );
		if ( false === $json || strlen( $json ) !== $entry['size'] ) {
			throw new \RuntimeException( sprintf( 'El tamaño real de %s no coincide con el que declara el ZIP.', $relative ) );
		}
		$data = self::decode( $json, $relative, $limits['json_depth'] );
		unset( $json );
		self::schema( $relative, $data );
		$inspection->remember( $relative, $data );
	}

	/** @throws \RuntimeException */
	private static function check_xml( \ZipArchive $zip, string $relative, array $entry, array $limits ): void {
		if ( $entry['size'] > $limits['xml'] ) {
			throw new \RuntimeException( sprintf( 'El archivo %1$s ocupa más de %2$d MB.', $relative, (int) ( $limits['xml'] / 1048576 ) ) );
		}
		$stream = $zip->getStream( $entry['name'] );
		if ( ! is_resource( $stream ) ) {
			throw new \RuntimeException( sprintf( 'No se pudo leer %s.', $relative ) );
		}
		$read = 0;
		$tail = '';
		while ( ! feof( $stream ) ) {
			$chunk = fread( $stream, self::READ_CHUNK );
			if ( false === $chunk || '' === $chunk ) {
				break;
			}
			$read += strlen( $chunk );
			if ( $read > $entry['size'] ) {
				break;
			}
			if ( preg_match( '/<!\s*(?:DOCTYPE|ENTITY)/i', $tail . $chunk ) ) {
				fclose( $stream );
				throw new \RuntimeException( sprintf( 'El archivo %s declara un DOCTYPE o entidades: no se admite.', $relative ) );
			}
			$tail = substr( $chunk, -16 );
		}
		fclose( $stream );
		if ( $read !== $entry['size'] ) {
			throw new \RuntimeException( sprintf( 'El tamaño real de %s no coincide con el que declara el ZIP.', $relative ) );
		}
	}

	/**
	 * Esquema mínimo por archivo: un tipo erróneo lanzaría «Invalid data» en la petición final del núcleo (CV-22).
	 *
	 * @throws \RuntimeException
	 */
	public static function schema( string $relative, array $data ): void {
		$fail = function ( $what ) use ( $relative ) {
			throw new \RuntimeException( sprintf( 'El archivo %1$s del kit no tiene el formato esperado: %2$s.', $relative, $what ) );
		};
		if ( 'manifest.json' === $relative ) {
			if ( Part_Context::is_list( $data ) && array() !== $data ) {
				$fail( 'debe ser un objeto' );
			}
			foreach ( array( 'templates', 'content', 'wp-content', 'taxonomies', 'site-settings', 'plugins', Module::MANIFEST_KEY ) as $key ) {
				if ( isset( $data[ $key ] ) && ! is_array( $data[ $key ] ) ) {
					$fail( $key . ' debe ser un objeto' );
				}
			}
			foreach ( isset( $data['templates'] ) && is_array( $data['templates'] ) ? $data['templates'] : array() as $template ) {
				if ( ! is_array( $template ) || ( isset( $template['doc_type'] ) && ! is_string( $template['doc_type'] ) ) ) {
					$fail( 'cada plantilla debe ser un objeto con doc_type' );
				}
			}
			foreach ( isset( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : array() as $posts ) {
				if ( ! is_array( $posts ) ) {
					$fail( 'content debe agrupar objetos por tipo' );
				}
				foreach ( $posts as $post ) {
					if ( ! is_array( $post ) ) {
						$fail( 'cada entrada de content debe ser un objeto' );
					}
				}
			}
			return;
		}
		if ( 'site-settings.json' === $relative ) {
			if ( isset( $data['settings'] ) && ! is_array( $data['settings'] ) ) {
				$fail( 'settings debe ser un objeto' );
			}
			return;
		}
		if ( preg_match( '#^(?:templates/[^/]+|content/[^/]+/[^/]+)\.json$#', $relative ) ) {
			if ( isset( $data['content'] ) ) {
				if ( ! is_array( $data['content'] ) || ! Part_Context::is_list( $data['content'] ) ) {
					$fail( 'content debe ser una lista de elementos' );
				}
				foreach ( $data['content'] as $element ) {
					self::element( $element, $fail, 0 );
				}
			}
			foreach ( array( 'settings', 'metadata', 'page_settings' ) as $key ) {
				if ( isset( $data[ $key ] ) && ! is_array( $data[ $key ] ) ) {
					$fail( $key . ' debe ser un objeto' );
				}
			}
			return;
		}
		if ( 0 === strpos( $relative, 'taxonomies/' ) ) {
			foreach ( $data as $term ) {
				if ( ! is_array( $term ) ) {
					$fail( 'cada término debe ser un objeto' );
				}
			}
			return;
		}
		if ( Module::KIT_FOLDER . 'media.json' === $relative ) {
			foreach ( $data as $url => $media ) {
				if ( ! is_string( $url ) || ! is_array( $media ) || ! isset( $media['file'] ) || ! is_string( $media['file'] ) || 1 !== preg_match( Module::MEDIA_FILE_PATTERN, $media['file'] ) || empty( $media['sha256'] ) || ! is_string( $media['sha256'] ) || 1 !== preg_match( '/^[0-9a-f]{64}$/', $media['sha256'] ) ) {
					$fail( 'media.json tiene una entrada inválida' );
				}
			}
			return;
		}
		$patterns = array(
			Module::KIT_FOLDER . 'files.json'         => Module::FILES_KEY_PATTERN,
			Module::KIT_FOLDER . 'urls.json'          => Module::URLS_KEY_PATTERN,
			Module::KIT_FOLDER . 'theme-builder.json' => Module::THEME_BUILDER_KEY_PATTERN,
		);
		if ( isset( $patterns[ $relative ] ) ) {
			foreach ( array_keys( $data ) as $key ) {
				if ( 1 !== preg_match( $patterns[ $relative ], (string) $key ) ) {
					$fail( 'clave inválida ' . self::printable( (string) $key ) );
				}
			}
		}
	}

	/** Un elemento del contenido: un objeto con `elType` y, si tiene, `elements` como lista. */
	private static function element( $element, callable $fail, int $depth ): void {
		if ( ! is_array( $element ) || ! isset( $element['elType'] ) || ! is_string( $element['elType'] ) || '' === $element['elType'] ) {
			$fail( 'cada elemento debe tener elType' );
		}
		foreach ( array( 'settings' ) as $key ) {
			if ( isset( $element[ $key ] ) && ! is_array( $element[ $key ] ) ) {
				$fail( 'settings de un elemento debe ser un objeto' );
			}
		}
		if ( isset( $element['elements'] ) ) {
			if ( ! is_array( $element['elements'] ) || ! Part_Context::is_list( $element['elements'] ) ) {
				$fail( 'elements debe ser una lista' );
			}
			foreach ( $element['elements'] as $child ) {
				self::element( $child, $fail, $depth + 1 );
			}
		}
	}

	/**
	 * Nombres tal como están en el directorio central, sin la limpieza de libzip, o null si no se puede leer (ZIP64
	 * o un directorio raro: entonces sólo cuentan los nombres de libzip).
	 *
	 * @return string[]|null
	 */
	private static function raw_names( string $file ): ?array {
		$size = (int) filesize( $file );
		if ( $size < 22 ) {
			return null;
		}
		$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- lectura del ZIP subido.
		if ( false === $handle ) {
			return null;
		}
		$length = min( $size, 65557 );
		fseek( $handle, $size - $length );
		$tail = (string) fread( $handle, $length ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$at   = strrpos( $tail, "PK\x05\x06" );
		if ( false === $at || strlen( $tail ) - $at < 22 ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return null;
		}
		$end = unpack( 'Vsignature/vdisk/vstart/vlocal/ventries/Vlength/Voffset', substr( $tail, $at, 20 ) );
		if ( 0xFFFF === $end['entries'] || 0xFFFFFFFF === $end['offset'] || $end['length'] > 16777216 || $end['offset'] + $end['length'] > $size ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return null;
		}
		fseek( $handle, $end['offset'] );
		$directory = $end['length'] > 0 ? (string) fread( $handle, $end['length'] ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$names    = array();
		$position = 0;
		for ( $i = 0; $i < $end['entries']; $i++ ) {
			if ( "PK\x01\x02" !== substr( $directory, $position, 4 ) || strlen( $directory ) < $position + 46 ) {
				return null;
			}
			$lengths   = unpack( 'vname/vextra/vcomment', substr( $directory, $position + 28, 6 ) );
			$names[]   = substr( $directory, $position + 46, $lengths['name'] );
			$position += 46 + $lengths['name'] + $lengths['extra'] + $lengths['comment'];
		}
		return $names;
	}

	private static function temporary_file(): string {
		$dir  = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
		$file = tempnam( $dir, 'digikit' );
		if ( false === $file ) {
			throw new \RuntimeException( 'No se pudo crear un archivo temporal para el ZIP interior.' );
		}
		return $file;
	}

	private static function drop( string $temporary ): void {
		if ( '' !== $temporary && is_file( $temporary ) ) {
			@unlink( $temporary ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/** @throws \RuntimeException */
	private static function fail( string $message, string $temporary ): void {
		self::drop( $temporary );
		throw new \RuntimeException( $message );
	}

	private static function printable( string $name ): string {
		return substr( (string) preg_replace( '/[^\x20-\x7E]/', '?', $name ), 0, 120 );
	}
}
