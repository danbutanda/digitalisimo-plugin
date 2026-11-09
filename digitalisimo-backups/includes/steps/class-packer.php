<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fases «pack» y «verify» por tramos (T2), con el escritor de sólo añadir (`Digitalisimo_Backups_Zip_Writer`).
 *
 * «pack» tiene tres etapas, todas con su punto de control en el trabajo y sus listas en el área de trabajo:
 * 1. Inventario por carpetas: una cola de carpetas (`package.<tag>.dirs.jsonl`) que se recorre en anchura; cada carpeta
 *    se lee entera y añade sus subcarpetas a la cola y sus archivos a la lista (`package.<tag>.files.jsonl`).
 * 2. Escritura: `database.sql` y después cada archivo de la lista. Cada entrada terminada se anota con su posición, CRC,
 *    tamaños y SHA-256 (`package.<tag>.entries.jsonl`); una entrada grande puede quedar a medias entre tramos.
 * 3. Cierre: `manifest.json` (manifiesto v3, escrito por partes desde la lista de entradas, con su firma calculada a la
 *    vez), directorio central y fin de archivo con la firma en el comentario.
 * Tras una muerte, cada lista y el ZIP se truncan a su último punto de control: nada escrito antes se reescribe.
 *
 * «verify» comprueba el archivo terminado por tramos: primero su estructura con ZipArchive (CHECKCONS: cada cabecera
 * local contra el directorio central), el número de entradas y la firma; después cada entrada, leída con ZipArchive
 * (o, si es enorme, por partes desde el archivo) contra su CRC, sus tamaños y su SHA-256.
 */
final class Digitalisimo_Backups_Packer {
	const VERSION = 1;
	/** Valor de `packer` en el manifiesto. */
	const PACKER = 'digitalisimo-zip/1';
	const REQUIRED = array( 'wordpress/wp-config.php', 'wordpress/wp-load.php', 'wordpress/wp-settings.php', 'wordpress/wp-admin/index.php', 'wordpress/wp-includes/version.php' );
	/** Desde este tamaño una entrada se verifica por partes, retomable entre tramos (PHP 8). */
	const RAW_FROM = 268435456;
	/** Hasta este tamaño el manifiesto se decodifica entero en la verificación (con más, se comprueba por su SHA-256). */
	const DECODE_MANIFEST = 4194304;

	/* ------------------------------------------------------------------ pack */

	public static function pack( array $job, $staging, $sql, array $database ) {
		$warnings = array();
		$cursor = self::resumable( $job, $staging );
		if ( ! $cursor ) {
			if ( ! empty( $job['checkpoint']['pack']['resume'] ) ) $warnings[] = 'El empaquetado no se pudo retomar desde su punto de control y empezó de nuevo.';
			$cursor = self::fresh( $staging );
		}
		$started = microtime( true );
		$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'pack', $cursor );
		$deadline = Digitalisimo_Backups_Step_Budget::deadline( $cursor['scale'], Digitalisimo_Backups_Step_Budget::slice_cap() );
		$units = 0;
		try {
			if ( 'inventory' === $cursor['stage'] ) self::inventory( $staging, $cursor, $deadline, $units );
			if ( 'write' === $cursor['stage'] && ! ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) ) self::write( $staging, $sql, $cursor, $deadline, $units, $warnings );
			if ( 'finish' === $cursor['stage'] && ! ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) ) {
				$final = self::finish( $staging, $job, $database, $cursor );
				$final['last'] = Digitalisimo_Backups_Step_Budget::measure( $started, $units + 1, $cursor['scale'] );
				return array( 'next' => 'verify', 'checkpoint' => $final, 'message' => 'ZIP escrito; verificando cada entrada…', 'warnings' => $warnings );
			}
		} catch ( Digitalisimo_Backups_Resume_Invalid $e ) {
			self::remove( $staging );
			return array( 'next' => 'pack', 'checkpoint' => array(), 'message' => 'Empaquetando otra vez desde el principio…', 'warnings' => array( $e->getMessage() . ' El empaquetado empieza de nuevo.' ) );
		}
		$cursor['last'] = Digitalisimo_Backups_Step_Budget::measure( $started, $units, $cursor['scale'] );
		$message = 'inventory' === $cursor['stage'] ? 'Inventariando archivos: ' . number_format( (int) $cursor['files'], 0, ',', '.' ) . ' archivos en las carpetas recorridas…' : 'Archivos incluidos: ' . number_format( (int) $cursor['done'], 0, ',', '.' ) . ' de ' . number_format( (int) $cursor['files'], 0, ',', '.' ) . '…';
		return array( 'next' => 'pack', 'checkpoint' => array( 'resume' => $cursor ), 'message' => $message, 'warnings' => $warnings );
	}

	private static function paths( $staging, $tag ) {
		$base = $staging . '/package.' . $tag;
		return array( 'zip' => $base . '.zip', 'dirs' => $base . '.dirs.jsonl', 'files' => $base . '.files.jsonl', 'entries' => $base . '.entries.jsonl' );
	}
	private static function resumable( array $job, $staging ) {
		$cursor = $job['checkpoint']['pack']['resume'] ?? null;
		if ( ! is_array( $cursor ) || self::VERSION !== (int) ( $cursor['v'] ?? 0 ) || ( $cursor['host'] ?? '' ) !== Digitalisimo_Backups_Jobs::host() ) return null;
		foreach ( array( 'tag', 'stage', 'dirs_read', 'dirs_size', 'files_size', 'files', 'bytes', 'config', 'files_read', 'done', 'done_bytes', 'zip_size', 'entries_size', 'entries', 'sql', 'partial', 'required', 'started_at', 'completed_at' ) as $key ) if ( ! array_key_exists( $key, $cursor ) ) return null;
		if ( ! is_string( $cursor['tag'] ) || ! preg_match( '/^[A-Za-z0-9]{8}$/D', $cursor['tag'] ) || ! in_array( $cursor['stage'], array( 'inventory', 'write', 'finish' ), true ) ) return null;
		$paths = self::paths( $staging, $cursor['tag'] );
		foreach ( array( 'dirs' => 'dirs_size', 'files' => 'files_size', 'entries' => 'entries_size', 'zip' => 'zip_size' ) as $file => $size ) {
			clearstatcache( true, $paths[ $file ] );
			if ( is_link( $paths[ $file ] ) || ! is_file( $paths[ $file ] ) || (int) filesize( $paths[ $file ] ) < (int) $cursor[ $size ] ) return null;
		}
		return $cursor;
	}
	private static function fresh( $staging ) {
		self::remove( $staging );
		Digitalisimo_Backups::job_files_context(); // wp-content dentro de ABSPATH y wp-config.php legible, antes de nada.
		$tag = Digitalisimo_Backups_Jobs::current_owner_tag();
		$paths = self::paths( $staging, $tag );
		$root = self::encode( '' ) . "\n";
		foreach ( array( 'dirs' => $root, 'files' => '', 'entries' => '', 'zip' => '' ) as $file => $contents ) {
			if ( false === @file_put_contents( $paths[ $file ], $contents ) ) throw new Exception( 'No se pudo preparar el empaquetado en el área de trabajo.' );
			if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $paths[ $file ], 0600 );
		}
		return array( 'v' => self::VERSION, 'host' => Digitalisimo_Backups_Jobs::host(), 'tag' => $tag, 'stage' => 'inventory', 'dirs_read' => 0, 'dirs_size' => strlen( $root ), 'files_size' => 0, 'files' => 0, 'bytes' => 0, 'config' => false, 'files_read' => 0, 'done' => 0, 'done_bytes' => 0, 'zip_size' => 0, 'entries_size' => 0, 'entries' => 0, 'sql' => false, 'partial' => null, 'required' => array(), 'started_at' => '', 'completed_at' => '', 'scale' => 1.0 );
	}
	private static function remove( $staging ) {
		foreach ( (array) @scandir( $staging ) as $name ) {
			if ( 0 !== strpos( (string) $name, 'package.' ) ) continue;
			$path = $staging . '/' . $name;
			if ( is_link( $path ) || is_file( $path ) ) @unlink( $path );
		}
	}

	/** Etapa 1: carpetas de la cola hasta vaciarla o hasta el plazo. Una carpeta es una unidad: se lee entera. */
	private static function inventory( $staging, array &$cursor, $deadline, &$units ) {
		$context = Digitalisimo_Backups::job_files_context();
		$paths = self::paths( $staging, $cursor['tag'] );
		$dirs = self::append_handle( $paths['dirs'], $cursor['dirs_size'] );
		$files = self::append_handle( $paths['files'], $cursor['files_size'] );
		$reader = @fopen( $paths['dirs'], 'rb' );
		try {
			if ( ! $reader ) throw new Exception( 'No se pudo leer el inventario del área de trabajo.' );
			while ( $cursor['dirs_read'] < $cursor['dirs_size'] ) {
				if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) break;
				if ( 0 !== fseek( $reader, (int) $cursor['dirs_read'] ) ) throw new Digitalisimo_Backups_Resume_Invalid( 'La cola de carpetas del inventario no coincide con su punto de control.' );
				$line = fgets( $reader );
				$relative = false === $line ? null : self::decode( $line );
				if ( null === $relative ) throw new Digitalisimo_Backups_Resume_Invalid( 'La cola de carpetas del inventario está dañada.' );
				$found = self::scan( $context, $relative, $dirs, $files );
				$cursor['dirs_read'] += strlen( $line );
				$cursor['dirs_size'] += $found['dirs_bytes'];
				$cursor['files_size'] += $found['files_bytes'];
				$cursor['files'] += $found['files'];
				$cursor['bytes'] += $found['bytes'];
				if ( $found['config'] ) $cursor['config'] = true;
				$units++;
				Digitalisimo_Backups_Jobs::progress( 40, 'Inventariando archivos: ' . $cursor['files'] . ' archivos · carpeta ' . ( '' === $relative ? '/' : $relative ) );
			}
			if ( $cursor['dirs_read'] >= $cursor['dirs_size'] ) {
				// wp-config.php fuera de ABSPATH o enlazado: se guarda su contenido y nunca se restaura encima.
				if ( ! $cursor['config'] ) {
					$line = self::encode( 'wp-config.php' ) . "\t" . self::encode( $context['config'] ) . "\n";
					self::put( $files, $line );
					$cursor['files_size'] += strlen( $line );
					$cursor['files']++;
					$cursor['bytes'] += (int) @filesize( $context['config'] );
				}
				$cursor['stage'] = 'write';
			}
			foreach ( array( $dirs, $files ) as $handle ) if ( ! fflush( $handle ) ) throw new Exception( 'No se pudo guardar el inventario en el área de trabajo.' );
		} finally {
			if ( $reader ) fclose( $reader );
			fclose( $dirs );
			fclose( $files );
		}
	}
	/** Lee una carpeta entera: subcarpetas a la cola, archivos a la lista. Mismas reglas que el empaquetado de un paso. */
	private static function scan( array $context, $relative, $dirs, $files ) {
		$root = $context['root'];
		$directory = '' === $relative ? rtrim( $root, '/' ) : $root . $relative;
		$prefix = '' === $relative ? '' : $relative . '/';
		$handle = @opendir( $directory );
		if ( ! $handle ) throw new Exception( 'No se pudo leer la carpeta ' . ( '' === $prefix ? '/' : $prefix ) . ' de WordPress.' );
		$found = array( 'dirs_bytes' => 0, 'files_bytes' => 0, 'files' => 0, 'bytes' => 0, 'config' => false );
		$dir_lines = '';
		$file_lines = '';
		try {
			while ( false !== ( $name = readdir( $handle ) ) ) {
				if ( '.' === $name || '..' === $name ) continue;
				$path = $directory . '/' . $name;
				$rel = $prefix . $name;
				if ( 0 === strpos( $path . '/', $context['backup'] ) ) continue;
				if ( isset( $context['sqlite'][ $path ] ) ) continue; // database.sql es la copia lógica consistente.
				if ( is_link( $path ) ) {
					// wp-config.php enlazado (archivos compartidos de un despliegue): se guarda su contenido al final del inventario.
					if ( $root . 'wp-config.php' === $path ) continue;
					throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo completo no puede omitir el enlace simbólico ' . $path . ' (apunta a ' . ( realpath( $path ) ?: (string) @readlink( $path ) ) . '). Sustitúyelo por el archivo o la carpeta real para respaldar la instalación completa.' );
				}
				$is_dir = is_dir( $path );
				if ( ! $is_dir && ! is_file( $path ) ) continue;
				if ( ! preg_match( '//u', $rel ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El nombre de ' . self::shown( $rel ) . ' no es UTF-8 válido y no puede figurar en el manifiesto; renómbralo para respaldar la instalación completa.' );
				if ( $is_dir ) {
					$dir_lines .= self::encode( $rel ) . "\n";
				} else {
					$file_lines .= self::encode( $rel ) . "\n";
					$found['files']++;
					$found['bytes'] += (int) @filesize( $path );
					if ( 'wp-config.php' === $rel ) $found['config'] = true;
				}
				if ( strlen( $dir_lines ) + strlen( $file_lines ) >= 1048576 ) self::flush_lines( $dirs, $files, $dir_lines, $file_lines, $found );
			}
		} finally {
			closedir( $handle );
		}
		self::flush_lines( $dirs, $files, $dir_lines, $file_lines, $found );
		return $found;
	}
	private static function flush_lines( $dirs, $files, &$dir_lines, &$file_lines, array &$found ) {
		if ( '' !== $dir_lines ) { self::put( $dirs, $dir_lines ); $found['dirs_bytes'] += strlen( $dir_lines ); }
		if ( '' !== $file_lines ) { self::put( $files, $file_lines ); $found['files_bytes'] += strlen( $file_lines ); }
		$dir_lines = '';
		$file_lines = '';
	}

	/** Etapa 2: `database.sql` y los archivos de la lista, con la entrada a medias que dejó el tramo anterior. */
	private static function write( $staging, $sql, array &$cursor, $deadline, &$units, array &$warnings ) {
		$context = Digitalisimo_Backups::job_files_context();
		$paths = self::paths( $staging, $cursor['tag'] );
		$partial = $cursor['partial'];
		$committed = (int) $cursor['zip_size'];
		$restart = false;
		if ( is_array( $partial ) ) {
			// Un archivo que cambió mientras su entrada estaba a medias se vuelve a escribir desde su cabecera.
			$restart = ! self::same_source( $partial );
			if ( $restart ) $committed = (int) $partial['o'];
		}
		$zip = new Digitalisimo_Backups_Zip_Writer( $paths['zip'], $committed );
		$entries = self::append_handle( $paths['entries'], $cursor['entries_size'] );
		$list = @fopen( $paths['files'], 'rb' );
		$chunks = 0;
		try {
			if ( ! $list ) throw new Exception( 'No se pudo leer la lista de archivos del área de trabajo.' );
			if ( is_array( $partial ) ) {
				$cursor['partial'] = null;
				if ( $restart ) {
					$item = array( $partial['n'], $partial['src'] );
				} else {
					$zip->resume( $partial );
					$item = null;
					if ( self::stream( $zip, $partial['src'], (int) $partial['read'], $partial['id'], $cursor, $deadline, $chunks ) ) self::entry_done( $entries, $zip->finish(), $cursor, $units );
				}
				if ( $restart ) self::add( $zip, $entries, $item[0], $item[1], $cursor, $deadline, $units, $chunks, $warnings );
			}
			if ( ! $cursor['sql'] && ! $cursor['partial'] ) {
				$cursor['sql'] = true;
				self::add( $zip, $entries, 'database.sql', $sql, $cursor, $deadline, $units, $chunks, $warnings );
			}
			while ( ! $cursor['partial'] && $cursor['files_read'] < $cursor['files_size'] ) {
				if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) break;
				if ( 0 !== fseek( $list, (int) $cursor['files_read'] ) ) throw new Digitalisimo_Backups_Resume_Invalid( 'La lista de archivos no coincide con su punto de control.' );
				$line = fgets( $list );
				if ( false === $line || "\n" !== substr( $line, -1 ) ) throw new Digitalisimo_Backups_Resume_Invalid( 'La lista de archivos del inventario está dañada.' );
				$cursor['files_read'] += strlen( $line );
				if ( '' === $cursor['started_at'] ) $cursor['started_at'] = gmdate( 'c' );
				$fields = explode( "\t", rtrim( $line, "\n" ) );
				$relative = self::decode( $fields[0] );
				$source = isset( $fields[1] ) ? self::decode( $fields[1] ) : ( null === $relative ? null : $context['root'] . $relative );
				if ( null === $relative || null === $source ) throw new Digitalisimo_Backups_Resume_Invalid( 'La lista de archivos del inventario está dañada.' );
				self::add( $zip, $entries, 'wordpress/' . $relative, $source, $cursor, $deadline, $units, $chunks, $warnings );
				Digitalisimo_Backups_Jobs::progress( 40 + (int) floor( 38 * min( 1, $cursor['done_bytes'] / max( 1, $cursor['bytes'] ) ) ), 'Archivos incluidos: ' . $cursor['done'] . ' de ' . $cursor['files'] . ' · último: ' . $relative );
			}
			if ( ! $cursor['partial'] && $cursor['files_read'] >= $cursor['files_size'] ) {
				foreach ( self::REQUIRED as $required ) if ( ! in_array( $required, (array) $cursor['required'], true ) ) throw new Exception( 'Falta el archivo obligatorio ' . $required . ' en el respaldo.' );
				$cursor['completed_at'] = gmdate( 'c' );
				$cursor['stage'] = 'finish';
			}
			if ( ! fflush( $entries ) ) throw new Exception( 'No se pudo guardar la lista de entradas del ZIP.' );
			$cursor['zip_size'] = $zip->commit();
			$position = ftell( $entries );
			if ( false === $position ) throw new Exception( 'No se pudo medir la lista de entradas del ZIP.' );
			$cursor['entries_size'] = (int) $position;
		} finally {
			if ( $list ) fclose( $list );
			fclose( $entries );
			$zip->close();
		}
	}
	/** Añade un archivo completo o lo deja a medias; uno que desapareció tras el inventario se omite con aviso. */
	private static function add( $zip, $entries, $name, $source, array &$cursor, $deadline, &$units, &$chunks, array &$warnings ) {
		clearstatcache( true, $source );
		if ( is_link( $source ) && ! ( 'wordpress/wp-config.php' === $name ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo completo no puede omitir el enlace simbólico ' . $source . ' (apunta a ' . ( realpath( $source ) ?: (string) @readlink( $source ) ) . '). Sustitúyelo por el archivo o la carpeta real para respaldar la instalación completa.' );
		if ( ! file_exists( $source ) && 'database.sql' !== $name ) {
			$warnings[] = 'El archivo ' . substr( $name, 10 ) . ' desapareció después del inventario y no está en el respaldo.';
			$units++;
			return;
		}
		$stat = @stat( $source );
		if ( ! is_array( $stat ) || ! is_file( $source ) || ! is_readable( $source ) ) throw new Exception( 'No se pudo agregar el archivo ' . $name . ' al respaldo.' );
		$zip->begin( $name, (int) $stat['mtime'], (int) $stat['mode'], (int) $stat['size'], Digitalisimo_Backups_Zip_Writer::method_for( $name, (int) $stat['size'] ) );
		$identity = array( 'size' => (int) $stat['size'], 'mtime' => (int) $stat['mtime'] );
		if ( self::stream( $zip, $source, 0, $identity, $cursor, $deadline, $chunks ) ) self::entry_done( $entries, $zip->finish(), $cursor, $units );
	}
	/**
	 * Copia el archivo a la entrada abierta desde `$offset`. Devuelve true al terminarlo; si vence el plazo antes, deja
	 * la entrada suspendida en el punto de control y devuelve false.
	 */
	private static function stream( $zip, $source, $offset, array $identity, array &$cursor, $deadline, &$chunks ) {
		$handle = @fopen( $source, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo agregar el archivo ' . basename( $source ) . ' al respaldo.' );
		$size = function_exists( 'apply_filters' ) ? (int) apply_filters( 'digitalisimo_backups_zip_chunk', Digitalisimo_Backups_Zip_Writer::CHUNK ) : Digitalisimo_Backups_Zip_Writer::CHUNK;
		$size = max( 1, $size );
		$cap = Digitalisimo_Backups_Step_Budget::units();
		try {
			if ( $offset > 0 && 0 !== fseek( $handle, $offset ) ) throw new Exception( 'No se pudo retomar la lectura de ' . basename( $source ) . '.' );
			while ( true ) {
				$chunk = fread( $handle, $size );
				if ( false === $chunk ) throw new Exception( 'No se pudo leer ' . basename( $source ) . ' para el respaldo.' );
				if ( '' === $chunk ) {
					if ( feof( $handle ) ) return true;
					throw new Exception( 'No se pudo leer ' . basename( $source ) . ' para el respaldo.' );
				}
				$zip->data( $chunk );
				$chunks++;
				$cursor['done_bytes'] += strlen( $chunk );
				if ( feof( $handle ) ) return true;
				if ( microtime( true ) >= $deadline || ( $cap && $chunks >= $cap ) || Digitalisimo_Backups_Step_Budget::memory_headroom() <= 0 ) {
					// Queda archivo: si lo que falta es sólo el final (EOF sin leer), termina igual.
					$probe = fread( $handle, 1 );
					if ( '' === $probe && feof( $handle ) ) return true;
					if ( false === $probe ) throw new Exception( 'No se pudo leer ' . basename( $source ) . ' para el respaldo.' );
					$cursor['done_bytes'] += 1;
					$state = $zip->suspend( $probe );
					$state['src'] = $source;
					$state['read'] = (int) $state['raw'];
					$state['id'] = $identity;
					$cursor['partial'] = $state;
					return false;
				}
				Digitalisimo_Backups_Jobs::progress( 40 + (int) floor( 38 * min( 1, $cursor['done_bytes'] / max( 1, $cursor['bytes'] ) ) ), 'Copiando ' . basename( $source ) . ': ' . self::bytes( (int) $zip->entry_read() ) );
			}
		} finally {
			fclose( $handle );
		}
	}
	private static function entry_done( $entries, array $record, array &$cursor, &$units ) {
		self::put( $entries, wp_json_encode( $record ) . "\n" );
		$cursor['entries']++;
		if ( 'database.sql' !== $record['n'] ) $cursor['done']++;
		if ( in_array( $record['n'], self::REQUIRED, true ) && ! in_array( $record['n'], (array) $cursor['required'], true ) ) $cursor['required'][] = $record['n'];
		$units++;
	}
	/** El archivo de una entrada a medias sigue siendo el mismo (tamaño y fecha) que cuando empezó. */
	private static function same_source( array $partial ) {
		$source = (string) ( $partial['src'] ?? '' );
		clearstatcache( true, $source );
		$stat = '' === $source || is_link( $source ) ? false : @stat( $source );
		return is_array( $stat ) && (int) $stat['size'] === (int) ( $partial['id']['size'] ?? -1 ) && (int) $stat['mtime'] === (int) ( $partial['id']['mtime'] ?? -1 );
	}

	/** Etapa 3: manifiesto, directorio central y fin de archivo. Si muere, se repite entera desde el final de las entradas. */
	private static function finish( $staging, array $job, array $database, array &$cursor ) {
		global $wpdb;
		$paths = self::paths( $staging, $cursor['tag'] );
		$plan = Digitalisimo_Backups::job_security_plan( (array) ( $job['spec'] ?? array() ) );
		$zip = null;
		try {
			$zip = new Digitalisimo_Backups_Zip_Writer( $paths['zip'], (int) $cursor['zip_size'] );
			$entries = self::append_handle( $paths['entries'], $cursor['entries_size'] );
			fclose( $entries );
			$totals = array( 'count' => 0, 'bytes' => 0, 'database' => 0 );
			foreach ( self::records( $paths['entries'], (int) $cursor['entries_size'] ) as $record ) {
				$totals['count']++;
				$totals['bytes'] += (int) $record['s'];
				if ( 'database.sql' === $record['n'] ) $totals['database'] = (int) $record['s'];
			}
			if ( $totals['count'] !== (int) $cursor['entries'] ) throw new Digitalisimo_Backups_Resume_Invalid( 'La lista de entradas del ZIP no coincide con su punto de control.' );
			$backup_id = wp_generate_uuid4();
			$head = array(
				'format' => 'digitalisimo-backup/v3',
				'backup_format_version' => 3,
				'backup_id' => $backup_id,
				'scope' => 'installation',
				'type' => 'full',
				'created_at' => (string) $database['at'],
				'completed_at' => gmdate( 'c' ),
				'home_url' => home_url(),
				'site_url' => site_url(),
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version' => PHP_VERSION,
				'database_version' => method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : '',
				'database_name' => DB_NAME,
				'database_size' => $totals['database'],
				'database_consistency' => (string) $database['mode'],
				'table_prefix' => $wpdb->base_prefix,
				'database_tables' => (array) $database['tables'],
				'multisite' => is_multisite(),
				'file_count' => $totals['count'] - 1,
				'uncompressed_size' => $totals['bytes'],
				'checksum_algorithm' => 'sha256',
				'plugin_version' => DIGITALISIMO_BACKUPS_VERSION,
				'excluded_secrets' => array( 'options:' . Digitalisimo_Backups_Keys::SECRET_PREFIX . '*', 'sitemeta:' . Digitalisimo_Backups_Keys::SECRET_PREFIX . '*' ),
			);
			$tail = array(
				'job_id' => (string) ( $job['id'] ?? '' ),
				'packer' => self::PACKER,
				'database_snapshot_at' => (string) $database['at'],
				'files_started_at' => (string) $cursor['started_at'],
				'files_completed_at' => (string) $cursor['completed_at'],
			);
			if ( $plan['root'] ) $tail['auth'] = Digitalisimo_Backups_Manifest_Auth::block( $plan['descriptor'], $plan['encrypt'] );
			$mac = null;
			$key_id = '';
			if ( $plan['root'] ) {
				$key_id = Digitalisimo_Backups_Keys::key_id( $plan['root'] );
				$mac = hash_init( 'sha512', HASH_HMAC, Digitalisimo_Backups_Keys::mac_key( $plan['root'] ) );
				hash_update( $mac, Digitalisimo_Backups_Manifest_Auth::PREFIX . "\n" . $key_id . "\n" );
			}
			Digitalisimo_Backups_Jobs::progress( 79, 'Escribiendo el manifiesto del ZIP…', true );
			$zip->begin( 'manifest.json', time(), 0644, 0, function_exists( 'deflate_init' ) ? 8 : 0 );
			foreach ( self::manifest_pieces( $head, $paths['entries'], (int) $cursor['entries_size'], $tail ) as $piece ) {
				$zip->data( $piece );
				if ( $mac ) hash_update( $mac, $piece );
			}
			$manifest = $zip->finish();
			$comment = $mac ? Digitalisimo_Backups_Manifest_Auth::PREFIX . ' ' . $key_id . ' ' . Digitalisimo_Backups_Keys::b64url_encode( substr( hash_final( $mac, true ), 0, 32 ) ) : '';
			$entries = self::append_handle( $paths['entries'], $cursor['entries_size'] );
			try {
				self::put( $entries, wp_json_encode( $manifest ) . "\n" );
				if ( ! fflush( $entries ) ) throw new Exception( 'No se pudo guardar la lista de entradas del ZIP.' );
				$entries_size = (int) ftell( $entries );
			} finally {
				fclose( $entries );
			}
			$count = $zip->close_archive( function() use ( $paths, $entries_size ) { return self::records( $paths['entries'], $entries_size ); }, $comment );
			$zip->commit();
			$zip->close();
			$zip = null;
			if ( $count !== $totals['count'] + 1 ) throw new Exception( 'El directorio central del ZIP no tiene todas las entradas.' );
			return array( 'file' => basename( $paths['zip'] ), 'fp' => Digitalisimo_Backups_Backup_Job::fingerprint( $paths['zip'] ), 'file_count' => $totals['count'] - 1, 'files_started_at' => (string) $cursor['started_at'], 'files_completed_at' => (string) $cursor['completed_at'], 'backup_id' => $backup_id, 'created_at' => (string) $database['at'], 'signed' => (bool) $plan['root'], 'packer' => self::PACKER, 'entries' => basename( $paths['entries'] ), 'entries_size' => $entries_size, 'count' => $count, 'manifest' => array( 'size' => (int) $manifest['s'], 'sha256' => (string) $manifest['h'] ) );
		} finally {
			if ( $zip ) $zip->close();
			if ( $plan['root'] ) Digitalisimo_Backups_Sodium::memzero( $plan['root'] );
		}
	}
	/**
	 * Bytes del manifiesto por partes: los mismos que `wp_json_encode( $manifiesto, JSON_PRETTY_PRINT )` del empaquetado
	 * de un paso, sin tener todas las entradas en memoria.
	 */
	public static function manifest_pieces( array $head, $entries_path, $entries_size, array $tail ) {
		$open = wp_json_encode( $head, JSON_PRETTY_PRINT );
		$close = wp_json_encode( $tail, JSON_PRETTY_PRINT );
		if ( ! is_string( $open ) || ! is_string( $close ) ) throw new Exception( 'No se pudo codificar el manifiesto del respaldo.' );
		yield substr( $open, 0, -2 ) . ",\n    \"entries\": {";
		$first = true;
		$buffer = '';
		foreach ( self::records( $entries_path, $entries_size ) as $record ) {
			$name = json_encode( (string) $record['n'] );
			if ( false === $name ) throw new Digitalisimo_Backups_Permanent_Error( 'El nombre de ' . self::shown( (string) $record['n'] ) . ' no es UTF-8 válido y no puede figurar en el manifiesto.' );
			$buffer .= ( $first ? "\n" : ",\n" ) . '        ' . $name . ": {\n            \"size\": " . (int) $record['s'] . ",\n            \"sha256\": \"" . $record['h'] . "\"\n        }";
			$first = false;
			if ( strlen( $buffer ) >= 262144 ) { yield $buffer; $buffer = ''; }
		}
		yield $buffer . "\n    },\n" . substr( $close, 2 );
	}
	/** Registros de la lista de entradas hasta `$size` bytes. */
	public static function records( $path, $size ) {
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo leer la lista de entradas del ZIP.' );
		try {
			$read = 0;
			while ( $read < $size ) {
				$line = fgets( $handle );
				if ( false === $line ) throw new Digitalisimo_Backups_Resume_Invalid( 'La lista de entradas del ZIP está truncada.' );
				$read += strlen( $line );
				$record = json_decode( $line, true );
				if ( ! is_array( $record ) || ! isset( $record['n'], $record['o'], $record['m'], $record['c'], $record['z'], $record['s'], $record['h'] ) ) throw new Digitalisimo_Backups_Resume_Invalid( 'La lista de entradas del ZIP está dañada.' );
				yield $record;
			}
		} finally {
			fclose( $handle );
		}
	}

	/* ------------------------------------------------------------------ verify */

	/**
	 * Un tramo de la verificación del ZIP de `pack`. Devuelve null si ya se comprobó todo (la fase sigue con el sellado
	 * y el registro) o el resultado de la fase para el tramo siguiente. Un fallo lanza una excepción: el motor vuelve a
	 * empaquetar. Un punto de control de otra versión (sin lista de entradas) se verifica entero como antes.
	 */
	public static function verify_slice( array $job, $path, array $pack ) {
		if ( empty( $pack['entries'] ) || empty( $pack['count'] ) ) {
			Digitalisimo_Backups::job_verify_package( $path, (array) $job['spec'] );
			return null;
		}
		$staging = dirname( $path );
		$list = $staging . '/' . basename( (string) $pack['entries'] );
		clearstatcache( true, $list );
		if ( is_link( $list ) || ! is_file( $list ) || (int) filesize( $list ) !== (int) $pack['entries_size'] ) throw new Exception( 'Falta la lista de entradas del ZIP o cambió.' );
		$cursor = $job['checkpoint']['verify']['resume'] ?? null;
		if ( ! is_array( $cursor ) || self::VERSION !== (int) ( $cursor['v'] ?? 0 ) || ! isset( $cursor['stage'], $cursor['index'], $cursor['offset'] ) || (int) $cursor['offset'] > (int) $pack['entries_size'] || (int) $cursor['index'] > (int) $pack['count'] ) {
			$cursor = array( 'v' => self::VERSION, 'stage' => 'structure', 'index' => 0, 'offset' => 0, 'partial' => null, 'required' => array(), 'scale' => 1.0 );
		}
		$started = microtime( true );
		$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'verify', $cursor );
		$deadline = Digitalisimo_Backups_Step_Budget::deadline( $cursor['scale'], Digitalisimo_Backups_Step_Budget::slice_cap() );
		$units = 0;
		if ( 'structure' === $cursor['stage'] ) {
			Digitalisimo_Backups_Jobs::progress( 81, 'Comprobando la estructura y la firma del ZIP…', true );
			self::structure( $path, $pack, (array) $job['spec'] );
			$cursor['stage'] = 'content';
			$units++;
		}
		$top = Digitalisimo_Backups::job_encrypts( (array) $job['spec'] ) ? 89 : 98;
		if ( self::content( $path, $list, $pack, $cursor, $deadline, $units, $top ) ) return null;
		$cursor['last'] = Digitalisimo_Backups_Step_Budget::measure( $started, $units, $cursor['scale'] );
		return array( 'next' => 'verify', 'checkpoint' => array( 'resume' => $cursor ), 'message' => 'Verificando el ZIP: entrada ' . min( (int) $pack['count'], (int) $cursor['index'] + 1 ) . ' de ' . (int) $pack['count'] . '…' );
	}
	/** CHECKCONS, número de entradas y firma (o su ausencia) con la clave que fijó el trabajo. */
	private static function structure( $path, array $pack, array $spec ) {
		$plan = Digitalisimo_Backups::job_security_plan( $spec );
		$zip = new ZipArchive();
		try {
			if ( true !== $zip->open( $path, ZipArchive::CHECKCONS ) ) throw new Exception( 'El ZIP generado no supera la comprobación estructural.' );
			try {
				if ( (int) $zip->numFiles !== (int) $pack['count'] ) throw new Exception( 'El ZIP no tiene el número de entradas que se escribió.' );
				$stat = $zip->statName( 'manifest.json' );
				if ( ! $stat || (int) $stat['size'] !== (int) $pack['manifest']['size'] || (int) $stat['index'] !== (int) $pack['count'] - 1 ) throw new Exception( 'El manifiesto del ZIP no es el que se escribió.' );
				$comment = (string) $zip->getArchiveComment();
				if ( $plan['root'] ) {
					$key_id = Digitalisimo_Backups_Keys::key_id( $plan['root'] );
					if ( ! preg_match( '/^DIGITALISIMO-AUTH\/1 ([0-9a-f]{32}) ([A-Za-z0-9_-]{43})$/D', $comment, $match ) || ! hash_equals( $key_id, $match[1] ) ) throw new Exception( 'El respaldo no supera la comprobación de su propia firma.' );
					$mac = hash_init( 'sha512', HASH_HMAC, Digitalisimo_Backups_Keys::mac_key( $plan['root'] ) );
					hash_update( $mac, Digitalisimo_Backups_Manifest_Auth::PREFIX . "\n" . $key_id . "\n" );
					$stream = $zip->getStream( 'manifest.json' );
					if ( ! $stream ) throw new Exception( 'No se pudo leer manifest.json del ZIP.' );
					while ( ! feof( $stream ) ) {
						$chunk = fread( $stream, 1048576 );
						if ( false === $chunk ) { fclose( $stream ); throw new Exception( 'No se pudo leer manifest.json del ZIP.' ); }
						hash_update( $mac, $chunk );
					}
					fclose( $stream );
					if ( ! hash_equals( Digitalisimo_Backups_Keys::b64url_encode( substr( hash_final( $mac, true ), 0, 32 ) ), $match[2] ) ) throw new Exception( 'El respaldo no supera la comprobación de su propia firma.' );
				} elseif ( 0 === strpos( $comment, Digitalisimo_Backups_Manifest_Auth::PREFIX ) || ! empty( $pack['signed'] ) ) {
					throw new Exception( 'El respaldo lleva una firma que este trabajo no puede comprobar.' );
				}
				// Si cabe con holgura en la memoria libre, el manifiesto se comprueba además como lo hace la restauración.
				// Decodificado ocupa unas 16 veces su tamaño: con 64 MB, uno de ~17 000 entradas no cabe (hallazgo H1 del
				// laboratorio) y basta lo anterior (su SHA-256, su tamaño y su firma, leídos por partes).
				if ( (int) $stat['size'] <= self::DECODE_MANIFEST && 16 * (int) $stat['size'] < Digitalisimo_Backups_Step_Budget::memory_headroom() ) {
					$auth = Digitalisimo_Backups_Manifest_Auth::verify( $zip, '', $plan['root'] );
					$state = $auth['state'];
					unset( $auth );
					if ( $plan['root'] && 'signed' !== $state ) throw new Exception( 'El respaldo no supera la comprobación de su propia firma.' );
					if ( ! $plan['root'] && 'unsigned' !== $state ) throw new Exception( 'El respaldo lleva una firma que este trabajo no puede comprobar.' );
					$manifest = Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
					if ( 'digitalisimo-backup/v3' !== ( $manifest['format'] ?? '' ) || 3 !== ( $manifest['backup_format_version'] ?? null ) || 'installation' !== ( $manifest['scope'] ?? '' ) || 'full' !== ( $manifest['type'] ?? '' ) || ! is_array( $manifest['entries'] ?? null ) || count( $manifest['entries'] ) !== (int) $pack['count'] - 1 || (int) $manifest['file_count'] !== (int) $pack['count'] - 2 ) throw new Exception( 'El manifiesto completo no coincide con el ZIP.' );
				}
			} finally {
				$zip->close();
			}
		} finally {
			if ( $plan['root'] ) Digitalisimo_Backups_Sodium::memzero( $plan['root'] );
		}
	}
	/**
	 * Entradas desde el punto de control: metadatos de ZipArchive contra la lista y contenido (CRC, tamaño y SHA-256).
	 * Devuelve true si comprobó la última.
	 */
	private static function content( $path, $list, array $pack, array &$cursor, $deadline, &$units, $top ) {
		$count = (int) $pack['count'];
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) throw new Exception( 'No se pudo abrir el ZIP para verificarlo.' );
		$handle = @fopen( $list, 'rb' );
		try {
			if ( ! $handle || 0 !== fseek( $handle, (int) $cursor['offset'] ) ) throw new Exception( 'No se pudo leer la lista de entradas del ZIP.' );
			while ( $cursor['index'] < $count ) {
				if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) return false;
				$line = fgets( $handle );
				$record = false === $line ? null : json_decode( $line, true );
				if ( ! is_array( $record ) || ! isset( $record['n'], $record['o'], $record['m'], $record['c'], $record['z'], $record['s'], $record['h'] ) ) throw new Exception( 'La lista de entradas del ZIP está dañada.' );
				$index = (int) $cursor['index'];
				if ( 0 === $index % 25 ) Digitalisimo_Backups_Jobs::progress( 81 + (int) floor( ( $top - 81 ) * $index / max( 1, $count ) ), 'Verificando archivo ' . ( $index + 1 ) . ' de ' . $count );
				$stat = $zip->statIndex( $index );
				if ( ! is_array( $stat ) || $stat['name'] !== $record['n'] || (int) $stat['size'] !== (int) $record['s'] || (int) $stat['comp_size'] !== (int) $record['z'] || (int) $stat['comp_method'] !== (int) $record['m'] || sprintf( '%08x', $stat['crc'] ) !== $record['c'] ) throw new Exception( 'La entrada ' . self::shown( $record['n'] ) . ' del ZIP no es la que se escribió.' );
				if ( ! self::entry( $zip, $path, $record, $cursor, $deadline ) ) return false;
				if ( ( 'database.sql' === $record['n'] || in_array( $record['n'], self::REQUIRED, true ) ) && ! in_array( $record['n'], $cursor['required'], true ) ) $cursor['required'][] = $record['n'];
				$cursor['index']++;
				$cursor['offset'] += strlen( $line );
				$units++;
			}
			foreach ( array_merge( array( 'database.sql' ), self::REQUIRED ) as $required ) if ( ! in_array( $required, $cursor['required'], true ) ) throw new Exception( 'Falta el archivo obligatorio ' . $required . ' en el respaldo completo.' );
			return true;
		} finally {
			if ( $handle ) fclose( $handle );
			$zip->close();
		}
	}
	/** Contenido de una entrada. Una enorme se lee por partes y puede quedar a medias entre tramos (PHP 8). */
	private static function entry( $zip, $path, array $record, array &$cursor, $deadline ) {
		$partial = is_array( $cursor['partial'] ) && (int) ( $cursor['partial']['i'] ?? -1 ) === (int) $cursor['index'] ? $cursor['partial'] : null;
		$cursor['partial'] = null;
		$by_parts = function_exists( 'apply_filters' ) ? (int) apply_filters( 'digitalisimo_backups_verify_parts_from', self::RAW_FROM ) : self::RAW_FROM;
		if ( $partial || ( (int) $record['s'] >= $by_parts && Digitalisimo_Backups_Zip_Writer::hash_state_supported() ) ) return self::entry_by_parts( $path, $record, $cursor, $deadline, $partial );
		$stream = $zip->getStream( $record['n'] );
		if ( ! $stream ) throw new Exception( 'No se pudo leer ' . self::shown( $record['n'] ) . ' del ZIP.' );
		$bytes = 0;
		$crc = hash_init( 'crc32b' );
		$sha = hash_init( 'sha256' );
		try {
			while ( ! feof( $stream ) ) {
				$chunk = @fread( $stream, 1048576 ); // libzip avisa de un CRC erróneo; la comparación de abajo da el motivo.
				if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) throw new Exception( 'La entrada ' . self::shown( $record['n'] ) . ' está truncada.' );
				$bytes += strlen( $chunk );
				hash_update( $crc, $chunk );
				hash_update( $sha, $chunk );
			}
		} finally {
			fclose( $stream );
		}
		if ( $bytes !== (int) $record['s'] || hash_final( $crc ) !== $record['c'] || ! hash_equals( (string) $record['h'], hash_final( $sha ) ) ) throw new Exception( 'Falló la integridad de ' . self::shown( $record['n'] ) . ' dentro del ZIP.' );
		return true;
	}
	private static function entry_by_parts( $path, array $record, array &$cursor, $deadline, $partial ) {
		$start = Digitalisimo_Backups_Zip_Writer::data_offset( $record );
		if ( ! $partial ) {
			$header = Digitalisimo_Backups_Zip_Writer::local_header( $record );
			$handle = @fopen( $path, 'rb' );
			$bytes = false;
			if ( $handle ) {
				if ( 0 === fseek( $handle, (int) $record['o'] ) ) $bytes = fread( $handle, strlen( $header ) );
				fclose( $handle );
			}
			if ( $bytes !== $header ) throw new Exception( 'La cabecera de ' . self::shown( $record['n'] ) . ' no es la que se escribió.' );
			$partial = array( 'i' => (int) $cursor['index'], 'from' => 0, 'raw' => 0, 'hc' => null, 'hs' => null );
			$contexts = array( hash_init( 'crc32b' ), hash_init( 'sha256' ) );
		} else {
			$contexts = array( @unserialize( (string) base64_decode( (string) $partial['hc'] ), array( 'allowed_classes' => array( 'HashContext' ) ) ), @unserialize( (string) base64_decode( (string) $partial['hs'] ), array( 'allowed_classes' => array( 'HashContext' ) ) ) );
			if ( ! $contexts[0] instanceof HashContext || ! $contexts[1] instanceof HashContext ) throw new Exception( 'No se pudo retomar la verificación de ' . self::shown( $record['n'] ) . '.' );
		}
		$total = (int) $record['z'];
		// Sin comprimir se puede parar en cualquier byte; con deflate, sólo en los límites de bloque del empaquetado.
		$stops = array();
		if ( (int) $record['m'] ) foreach ( (array) ( $record['b'] ?? array() ) as $bound ) $stops[] = (int) $bound[0];
		else for ( $stop = 67108864; $stop < $total; $stop += 67108864 ) $stops[] = $stop;
		$stops[] = $total;
		$stops = array_values( array_unique( array_filter( $stops, function( $stop ) use ( $partial ) { return $stop > (int) $partial['from']; } ) ) );
		sort( $stops );
		$cap = Digitalisimo_Backups_Step_Budget::units();
		foreach ( $stops as $position => $stop ) {
			if ( $position > 0 && ( microtime( true ) >= $deadline || ( $cap && $position >= $cap ) ) && $stop <= $total ) {
				$partial['hc'] = base64_encode( serialize( $contexts[0] ) );
				$partial['hs'] = base64_encode( serialize( $contexts[1] ) );
				$cursor['partial'] = $partial;
				return false;
			}
			list( $contexts[0], $contexts[1], $raw ) = Digitalisimo_Backups_Zip_Writer::hash_region( $path, $start, (int) $record['m'], (int) $partial['from'], $stop, $contexts );
			$partial['from'] = $stop;
			$partial['raw'] += $raw;
			Digitalisimo_Backups_Jobs::progress( 81, 'Verificando ' . self::shown( $record['n'] ) . ': ' . self::bytes( (int) $partial['raw'] ) );
		}
		if ( (int) $partial['raw'] !== (int) $record['s'] || hash_final( $contexts[0] ) !== $record['c'] || ! hash_equals( (string) $record['h'], hash_final( $contexts[1] ) ) ) throw new Exception( 'Falló la integridad de ' . self::shown( $record['n'] ) . ' dentro del ZIP.' );
		return true;
	}

	/* ------------------------------------------------------------------ utilidades */

	/** Una línea por ruta: «%», salto de línea, retorno y tabulador escapados (una ruta puede contenerlos). */
	private static function encode( $path ) { return str_replace( array( '%', "\n", "\r", "\t" ), array( '%25', '%0A', '%0D', '%09' ), (string) $path ); }
	private static function decode( $line ) {
		$line = rtrim( (string) $line, "\n" );
		if ( preg_match( '/%(?!25|0A|0D|09)/', $line ) ) return null;
		return strtr( $line, array( '%25' => '%', '%0A' => "\n", '%0D' => "\r", '%09' => "\t" ) );
	}
	private static function append_handle( $path, $size ) {
		$handle = @fopen( $path, 'c+b' );
		if ( ! $handle ) throw new Exception( 'No se pudo abrir ' . basename( $path ) . ' en el área de trabajo.' );
		if ( ! ftruncate( $handle, (int) $size ) || 0 !== fseek( $handle, (int) $size ) ) {
			fclose( $handle );
			throw new Exception( 'No se pudo preparar ' . basename( $path ) . ' en el área de trabajo.' );
		}
		return $handle;
	}
	private static function put( $handle, $bytes ) {
		$length = strlen( $bytes );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $handle, 0 === $offset ? $bytes : substr( $bytes, $offset ) );
			if ( false === $written || 0 === $written ) throw new Exception( 'No se pudo escribir en el área de trabajo; revisa el espacio libre del almacén privado.' );
			$offset += $written;
		}
	}
	private static function bytes( $bytes ) { return function_exists( 'size_format' ) ? (string) size_format( (int) $bytes ) : round( $bytes / 1048576, 1 ) . ' MB'; }
	private static function shown( $name ) {
		$name = preg_replace( '/[\x00-\x1F\x7F]/', '?', (string) $name );
		return strlen( $name ) > 120 ? substr( $name, 0, 117 ) . '…' : $name;
	}
}

