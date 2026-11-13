<?php
defined( 'ABSPATH' ) || exit;

/**
 * Restaurar (o verificar) un punto incremental: antes de tocar nada del sitio, comprueba su cadena entera y reconstruye
 * el respaldo completo de ese punto (docs/BACKUPS.md, «Incrementales»). Ese ZIP completo entra después en la
 * restauración de siempre, que lo vuelve a verificar entrada por entrada; la restauración no sabe que vino de una cadena.
 *
 * La cadena se comprueba eslabón a eslabón, del punto hacia el completo:
 * - cada eslabón está en el historial y en el almacén, con el SHA-256 registrado (o descifrado con su clave) y su firma
 *   (Digitalisimo_Backups_Security::open(), la misma apertura que un respaldo completo);
 * - el manifiesto del punto nombra a todos sus antecesores por el SHA-256 de su manifiesto, y cada antecesor tiene ese
 *   manifiesto exacto, su identificador, su posición y la misma cadena (padres correctos y en orden);
 * - el inventario del punto es el que su manifiesto declara, y cada archivo sale del eslabón que el inventario dice con
 *   el tamaño y el SHA-256 del inventario; la base de datos es la del punto.
 * Una cadena rota se rechaza con un mensaje que dice qué eslabón falla y qué hacer; nada del sitio cambia.
 */
final class Digitalisimo_Backups_Chain_Restore {
	const MARGIN = 67108864;

	/**
	 * Reconstruye el respaldo completo del punto `$item` en una carpeta nueva de `.staging/` del almacén. `$local` es un
	 * callable( fila ) que devuelve la ruta del archivo de la fila en el almacén ('' si no está). Devuelve path (el ZIP),
	 * staging (su carpeta; se borra al cerrar el informe), item (la fila con la que se abre: su confianza), secret (la
	 * frase con la que se firmó, si hizo falta), trust (la de la cadena), y links, files, bytes y summary para el informe.
	 */
	public static function materialize( array $item, array $history, $secret, $storage, callable $local ) {
		$chain = Digitalisimo_Backups_Chain::row_chain( $item );
		if ( ! $chain || $chain['sequence'] < 1 ) throw new Exception( 'Este respaldo no es un punto incremental.' );
		$point = self::point_label( $item );
		$rows = array();
		foreach ( $chain['links'] as $link ) {
			$row = Digitalisimo_Backups_Chain::link_row( $history, $link );
			if ( ! $row ) throw new Exception( self::broken( $point, (int) $link['sequence'], $chain['sequence'], 'no está en el historial (' . $link['file'] . ')', 'Ese punto no se puede restaurar; elige otro.' ) );
			$rows[] = $row;
		}
		$rows[] = $item;
		$paths = array();
		$missing = array();
		foreach ( $rows as $index => $row ) {
			$path = (string) call_user_func( $local, $row );
			if ( '' === $path ) $missing[] = $index;
			$paths[] = $path;
		}
		if ( $missing ) throw new Exception( 'Al punto ' . $point . ' le faltan en el almacén ' . count( $missing ) . ' de ' . count( $rows ) . ' archivos de su cadena (' . implode( ', ', array_map( function( $index ) use ( $rows ) { return (string) $rows[ $index ]['file']; }, array_slice( $missing, 0, 3 ) ) ) . ( count( $missing ) > 3 ? '…' : '' ) . '). Tráelos con «Traer al almacén» en su fila (trae la cadena entera) y vuelve a restaurar. No se cambió nada del sitio.' );
		// Espacio: lo descifrado de los eslabones cifrados y el ZIP del punto (sin comprimir) más un margen.
		$needed = (int) ( $chain['bytes'] ?? 0 ) + (int) ( $item['size'] ?? 0 ) + self::MARGIN;
		foreach ( $paths as $path ) if ( Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) $needed += (int) @filesize( $path );
		$free = Digitalisimo_Backups_Archive_Inspector::free_space( $storage );
		if ( null !== $free && $free < $needed ) throw new Exception( 'No hay espacio para reconstruir el punto ' . $point . ': se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed ) . ' libres en el almacén privado y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '. No se cambió nada del sitio.' );
		$reports = array();
		$staging = '';
		try {
			$staging = Digitalisimo_Backups_Security::new_staging( $storage );
			// 1. Abrir cada eslabón como se abre un respaldo: SHA-256 registrado, descifrado y firma.
			foreach ( $rows as $index => $row ) {
				try {
					$reports[ $index ] = Digitalisimo_Backups_Encrypted_File::is_encrypted( $paths[ $index ] ) ? self::open_sealed( $paths[ $index ], $row, (string) $secret, $staging . '/link-' . $index . '.zip' ) : Digitalisimo_Backups_Security::open( $paths[ $index ], $row, (string) $secret );
				} catch ( Exception $e ) {
					throw new Exception( self::broken( $point, $index, $chain['sequence'], 'no supera su comprobación: ' . rtrim( $e->getMessage(), '.' ), 'Restaura otro punto o el completo de la cadena.' ) );
				}
			}
			// 2. Manifiestos: el del punto fija la cadena; cada antecesor tiene exactamente el manifiesto que nombra.
			$top = count( $rows ) - 1;
			$data = array();
			$hashes = array();
			foreach ( $rows as $index => $row ) {
				$zip = $reports[ $index ]['zip'];
				try {
					$hashes[ $index ] = self::manifest_sha( $zip );
					$data[ $index ] = Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
				} catch ( Exception $e ) {
					throw new Exception( self::broken( $point, $index, $chain['sequence'], 'tiene un manifiesto ilegible: ' . rtrim( $e->getMessage(), '.' ), 'Restaura otro punto o el completo de la cadena.' ) );
				}
			}
			$head = $data[ $top ];
			$block = is_array( $head['chain'] ?? null ) ? $head['chain'] : array();
			if ( 'incremental' !== ( $head['type'] ?? '' ) || Digitalisimo_Backups_Chain::VERSION !== ( $block['version'] ?? null ) || $chain['id'] !== ( $block['id'] ?? null ) || $chain['sequence'] !== ( $block['sequence'] ?? null ) || ! Digitalisimo_Backups_Chain::valid_links( $block['links'] ?? null, $chain['sequence'] ) || $chain['backup_id'] !== ( $head['backup_id'] ?? null ) || ! hash_equals( $chain['manifest_sha256'], $hashes[ $top ] ) ) throw new Exception( self::broken( $point, $top, $chain['sequence'], 'no es el que registró el historial (su manifiesto o su cadena no coinciden)', 'Restaura otro punto o el completo de la cadena.' ) );
			$links = $block['links'];
			$trust = 'signed';
			$key = '';
			foreach ( $rows as $index => $row ) {
				$problem = '';
				if ( $index < $top ) {
					$link = $links[ $index ];
					if ( (string) $link['file'] !== (string) $row['file'] ) $problem = 'no es el archivo que nombra el punto';
					elseif ( ! hash_equals( (string) $link['manifest_sha256'], $hashes[ $index ] ) ) $problem = 'fue modificado: su manifiesto no es el que nombra el punto';
					elseif ( $link['backup_id'] !== ( $data[ $index ]['backup_id'] ?? null ) ) $problem = 'no es el respaldo que nombra el punto';
					elseif ( 0 === $index && 'full' !== ( $data[ $index ]['type'] ?? '' ) ) $problem = 'no es un respaldo completo';
					elseif ( $index > 0 ) {
						$inner = is_array( $data[ $index ]['chain'] ?? null ) ? $data[ $index ]['chain'] : array();
						if ( 'incremental' !== ( $data[ $index ]['type'] ?? '' ) || $chain['id'] !== ( $inner['id'] ?? null ) || $index !== ( $inner['sequence'] ?? null ) || array_slice( $links, 0, $index ) !== ( $inner['links'] ?? null ) ) $problem = 'no ocupa ese lugar en la cadena (otro padre u otro orden)';
					}
				}
				if ( '' === $problem && ( (bool) ( $data[ $index ]['multisite'] ?? false ) !== (bool) ( $head['multisite'] ?? false ) || (string) ( $data[ $index ]['table_prefix'] ?? '' ) !== (string) ( $head['table_prefix'] ?? '' ) ) ) $problem = 'es de otra instalación';
				if ( '' !== $problem ) throw new Exception( self::broken( $point, $index, $chain['sequence'], $problem, 'Restaura otro punto o el completo de la cadena.' ) );
				$level = (string) $reports[ $index ]['trust'];
				if ( 'untrusted' === $level ) $trust = 'untrusted';
				elseif ( 'provenance' === $level && 'signed' === $trust ) $trust = 'provenance';
				if ( 'signed' === $level ) {
					$id = (string) ( $reports[ $index ]['key']['id'] ?? '' );
					if ( '' !== $key && $id !== $key ) throw new Exception( self::broken( $point, $index, $chain['sequence'], 'está firmado con otra clave que el resto de su cadena', 'Restaura otro punto o el completo de la cadena.' ) );
					$key = $id;
				}
			}
			// 3. El respaldo completo del punto, entrada por entrada, con el contenido que el inventario firmado exige.
			$target = $staging . '/point.zip';
			$built = self::build( $reports, $data, $hashes, $top, $chain, $point, $staging, $target, 'signed' === $trust ? $reports[ $top ] : null );
		} catch ( Throwable $e ) {
			foreach ( $reports as $report ) Digitalisimo_Backups_Security::close( $report );
			if ( '' !== $staging ) Digitalisimo_Backups_Security::remove_tree( $staging );
			throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e );
		}
		$via = (string) ( $reports[ $top ]['via'] ?? '' );
		foreach ( $reports as $report ) Digitalisimo_Backups_Security::close( $report );
		// Los eslabones descifrados ya no hacen falta: en el área sólo queda el ZIP completo del punto.
		foreach ( (array) @scandir( $staging ) as $name ) if ( '.' !== $name && '..' !== $name && 'point.zip' !== $name ) @unlink( $staging . '/' . $name );
		$sha = @hash_file( 'sha256', $target );
		if ( false === $sha ) { Digitalisimo_Backups_Security::remove_tree( $staging ); throw new Exception( 'No se pudo comprobar el respaldo completo reconstruido del punto ' . $point . '.' ); }
		// La confianza del reconstruido es la de su cadena: firmado (con la clave del punto), registrado aquí o sin firma.
		$open_as = 'untrusted' === $trust ? array() : array( 'sha256' => $sha, 'origin' => 'created' );
		return array(
			'path' => $target,
			'staging' => $staging,
			'item' => $open_as,
			'secret' => 'signed' === $trust && 'secret' === $via ? (string) $secret : '',
			'trust' => $trust,
			'links' => count( $rows ),
			'files' => (int) $built['files'],
			'bytes' => (int) $built['bytes'],
			'sha256' => $sha,
			'summary' => 'Punto incremental ' . $chain['sequence'] . ' de su cadena reconstruido entero desde ' . count( $rows ) . ' archivos comprobados (el completo y sus incrementales): ' . (int) $built['files'] . ' archivos y la base de datos de ese momento.',
		);
	}

	/**
	 * Un eslabón cifrado (DGBK), descifrado en la carpeta de esta reconstrucción con las mismas comprobaciones que
	 * Digitalisimo_Backups_Security::open() (que vaciaría `.staging/`, donde ya están los otros eslabones): SHA-256
	 * registrado, clave conocida o frase escrita, descifrado completo, firma, cifrado declarado e identidad de la cabecera.
	 */
	private static function open_sealed( $path, array $row, $secret, $inner ) {
		if ( ! empty( $row['sha256'] ) ) {
			$checksum = @hash_file( 'sha256', $path );
			if ( false === $checksum || ! hash_equals( (string) $row['sha256'], $checksum ) ) throw new Exception( 'El SHA-256 del respaldo ya no coincide con el archivo registrado.' );
		}
		if ( ! Digitalisimo_Backups_Sodium::available() ) throw new Exception( 'Este respaldo está cifrado y este servidor no tiene las funciones de libsodium para descifrarlo.' );
		$parsed = Digitalisimo_Backups_Encrypted_File::read_header( $path );
		$descriptor = $parsed['header']['key'];
		$via = 'keyring';
		$root = Digitalisimo_Backups_Keys::resolve_by_id( $descriptor['id'] );
		if ( false === $root ) {
			if ( '' === $secret ) throw new Exception( Digitalisimo_Backups_Security::missing_key_text( $descriptor['id'] ) );
			$root = Digitalisimo_Backups_Keys::resolve_with_secret( $descriptor, $secret );
			$via = 'secret';
			if ( false === $root ) throw new Exception( 'La frase o clave escrita no corresponde a la clave ' . Digitalisimo_Backups_Keys::short( $descriptor['id'] ) . ' de este respaldo.' );
		}
		$zip = null;
		try {
			Digitalisimo_Backups_Encrypted_File::decrypt( $path, $root, $inner, $parsed );
			$zip = new ZipArchive();
			if ( true !== $zip->open( $inner, ZipArchive::CHECKCONS ) ) throw new Exception( 'El contenido descifrado no es un ZIP válido.' );
			$auth = Digitalisimo_Backups_Manifest_Auth::verify( $zip, '', $root );
			if ( 'signed' !== $auth['state'] || true !== $auth['encrypted'] || ( $auth['manifest']['backup_id'] ?? '' ) !== $parsed['header']['backup_id'] || ! hash_equals( $descriptor['id'], (string) ( $auth['key']['id'] ?? '' ) ) ) throw new Exception( 'El contenido descifrado no corresponde a su cabecera: el respaldo fue modificado o está dañado.' );
		} catch ( Throwable $e ) {
			if ( $zip instanceof ZipArchive ) { try { @$zip->close(); } catch ( Throwable $ignored ) {} } // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			@unlink( $inner );
			Digitalisimo_Backups_Sodium::memzero( $root );
			throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e );
		}
		return array( 'source' => $path, 'path' => $inner, 'container' => 'dgbk', 'trust' => 'signed', 'auth' => 'signed', 'key' => $descriptor, 'via' => $via, 'staging' => '', 'zip' => $zip, 'root' => $root, 'header' => $parsed['header'] );
	}
	/** Escribe el ZIP completo del punto y lo sella como un respaldo v3 de siempre. Devuelve files y bytes. */
	private static function build( array $reports, array $data, array $hashes, $top, array $chain, $point, $staging, $target, $signer ) {
		$zip_top = $reports[ $top ]['zip'];
		// Entradas del manifiesto del punto que hacen falta: su base de datos y su inventario.
		$declared = array();
		Digitalisimo_Backups_Manifest_Stream::read( $zip_top, function( $name, $entry ) use ( &$declared ) {
			if ( in_array( $name, array( 'database.sql', Digitalisimo_Backups_Chain::INVENTORY ), true ) ) $declared[ $name ] = $entry;
		} );
		foreach ( array( 'database.sql', Digitalisimo_Backups_Chain::INVENTORY ) as $name ) if ( ! isset( $declared[ $name ] ) ) throw new Exception( self::broken( $point, $top, $chain['sequence'], 'no declara ' . $name, 'Restaura otro punto o el completo de la cadena.' ) );
		// El inventario, copiado al área y comprobado contra el manifiesto firmado del punto.
		$inventory = $staging . '/inventory.jsonl';
		self::extract_checked( $zip_top, Digitalisimo_Backups_Chain::INVENTORY, $declared[ Digitalisimo_Backups_Chain::INVENTORY ], $inventory, function() use ( $point, $top, $chain ) { return self::broken( $point, $top, $chain['sequence'], 'tiene un inventario dañado', 'Restaura otro punto o el completo de la cadena.' ); } );
		$entries_path = $staging . '/point.entries.jsonl';
		$entries = @fopen( $entries_path, 'xb' );
		if ( ! $entries ) throw new Exception( 'No se pudo preparar el respaldo completo del punto en el almacén privado.' );
		$writer = null;
		$files = 0;
		$bytes = 0;
		try {
			$writer = new Digitalisimo_Backups_Zip_Writer( $target, 0 );
			// La base de datos del punto.
			$record = self::copy_entry( $writer, $zip_top, 'database.sql', (int) $declared['database.sql']['size'], 0644, $declared['database.sql']['sha256'] );
			if ( null === $record ) throw new Exception( self::broken( $point, $top, $chain['sequence'], 'trae una base de datos que no es la de su manifiesto', 'Restaura otro punto o el completo de la cadena.' ) );
			Digitalisimo_Backups_Chain::write_all( $entries, json_encode( $record ) . "\n" );
			$database = (int) $record['s'];
			// Cada archivo del punto, del eslabón que guarda sus bytes.
			foreach ( Digitalisimo_Backups_Chain::records( $inventory ) as $row ) {
				$line = $row[1];
				if ( ! Digitalisimo_Backups_Chain::valid_inventory_line( $line, $chain['sequence'] ) ) throw new Exception( self::broken( $point, $top, $chain['sequence'], 'tiene un inventario mal formado', 'Restaura otro punto o el completo de la cadena.' ) );
				if ( ! empty( $line['x'] ) ) continue;
				$link = (int) $line['l'];
				$source = $reports[ $link ]['zip'];
				$stat = $source->statName( $line['n'] );
				$mode = 0644;
				if ( is_array( $stat ) && method_exists( $source, 'getExternalAttributesIndex' ) && $source->getExternalAttributesIndex( (int) $stat['index'], $opsys, $attributes ) && ZipArchive::OPSYS_UNIX === $opsys && ( $attributes >> 16 ) & 0777 ) $mode = ( $attributes >> 16 ) & 0777;
				$record = is_array( $stat ) && (int) $stat['size'] === (int) $line['s'] ? self::copy_entry( $writer, $source, $line['n'], (int) $line['s'], $mode, $line['h'], (int) ( $stat['mtime'] ?? time() ) ) : null;
				if ( null === $record ) throw new Exception( self::broken( $point, $link, $chain['sequence'], 'no trae ' . substr( $line['n'], 10 ) . ' con el contenido que el punto exige', 'Restaura otro punto o el completo de la cadena.' ) );
				Digitalisimo_Backups_Chain::write_all( $entries, json_encode( $record ) . "\n" );
				$files++;
				$bytes += (int) $record['s'];
				if ( 0 === $files % 200 ) Digitalisimo_Backups::report_progress( 8, 'cadena', 'Reconstruyendo el punto: ' . $files . ' archivos…' );
			}
			$counts = is_array( $data[ $top ]['chain']['inventory'] ?? null ) ? $data[ $top ]['chain']['inventory'] : array();
			if ( $files !== (int) ( $counts['files'] ?? -1 ) || $bytes !== (int) ( $counts['bytes'] ?? -1 ) ) throw new Exception( self::broken( $point, $top, $chain['sequence'], 'tiene un inventario que no suma lo que declara su manifiesto', 'Restaura otro punto o el completo de la cadena.' ) );
			if ( ! fflush( $entries ) ) throw new Exception( 'No se pudo preparar el respaldo completo del punto en el almacén privado.' );
			$listed = (int) ftell( $entries );
			// El manifiesto: el del punto como respaldo completo (sus direcciones, rutas, tablas y exclusiones), sin su cadena.
			$head = $data[ $top ];
			$auth = $head['auth'] ?? null;
			foreach ( array( 'entries_count', 'chain', 'auth', 'notices', 'job_id', 'packer', 'database_snapshot_at', 'files_started_at', 'files_completed_at', 'excluded' ) as $key ) unset( $head[ $key ] );
			$head['type'] = 'full';
			$head['file_count'] = $files;
			$head['uncompressed_size'] = $bytes + $database;
			$head['database_size'] = $database;
			$tail = array();
			foreach ( array( 'job_id', 'packer', 'database_snapshot_at', 'files_started_at', 'files_completed_at', 'notices', 'excluded' ) as $key ) if ( array_key_exists( $key, $data[ $top ] ) ) $tail[ $key ] = $data[ $top ][ $key ];
			$tail['materialized_from'] = array( 'chain' => $chain['id'], 'sequence' => $chain['sequence'], 'links' => count( $reports ), 'manifest_sha256' => $hashes[ $top ] );
			$mac = null;
			$key_id = '';
			if ( $signer && ! empty( $signer['root'] ) && is_array( $auth ) && Digitalisimo_Backups_Keys::valid_descriptor( $auth['key'] ?? null ) ) {
				$key_id = Digitalisimo_Backups_Keys::key_id( $signer['root'] );
				if ( ! hash_equals( (string) $auth['key']['id'], $key_id ) ) throw new Exception( 'La clave del punto no es la de su firma.' );
				$tail['auth'] = Digitalisimo_Backups_Manifest_Auth::block( $auth['key'], false );
				$mac = hash_init( 'sha512', HASH_HMAC, Digitalisimo_Backups_Keys::mac_key( $signer['root'] ) );
				hash_update( $mac, Digitalisimo_Backups_Manifest_Auth::PREFIX . "\n" . $key_id . "\n" );
			}
			$writer->begin( 'manifest.json', time(), 0644, 0, function_exists( 'deflate_init' ) ? 8 : 0 );
			foreach ( Digitalisimo_Backups_Packer::manifest_pieces( $head, $entries_path, $listed, $tail ) as $piece ) {
				$writer->data( $piece );
				if ( $mac ) hash_update( $mac, $piece );
			}
			$manifest = $writer->finish();
			Digitalisimo_Backups_Chain::write_all( $entries, json_encode( $manifest ) . "\n" );
			if ( ! fflush( $entries ) ) throw new Exception( 'No se pudo preparar el respaldo completo del punto en el almacén privado.' );
			$size = (int) ftell( $entries );
			$comment = $mac ? Digitalisimo_Backups_Manifest_Auth::PREFIX . ' ' . $key_id . ' ' . Digitalisimo_Backups_Keys::b64url_encode( substr( hash_final( $mac, true ), 0, 32 ) ) : '';
			$count = $writer->close_archive( function() use ( $entries_path, $size ) { return Digitalisimo_Backups_Packer::records( $entries_path, $size ); }, $comment );
			$writer->commit();
			if ( $count !== $files + 2 ) throw new Exception( 'El respaldo completo reconstruido no tiene todas sus entradas.' );
		} finally {
			if ( $writer ) $writer->close();
			fclose( $entries );
		}
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $target, 0600 );
		return array( 'files' => $files, 'bytes' => $bytes );
	}
	/** Copia una entrada de `$zip` al ZIP nuevo sin comprimir, comprobando tamaño y SHA-256; null si no coinciden. */
	private static function copy_entry( $writer, $zip, $name, $size, $mode, $sha256, $mtime = null ) {
		$stream = Digitalisimo_Backups::zip_entry_reader( $zip, $name, $size );
		if ( ! $stream ) return null;
		$writer->begin( $name, null === $mtime ? time() : $mtime, $mode, $size, 0 );
		try {
			while ( ! feof( $stream ) ) {
				$chunk = @fread( $stream, 1048576 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) break;
				$writer->data( $chunk );
			}
		} finally {
			fclose( $stream );
		}
		$record = $writer->finish();
		if ( (int) $record['s'] !== (int) $size || ! hash_equals( (string) $sha256, (string) $record['h'] ) ) {
			$writer->drop( $record );
			return null;
		}
		return $record;
	}
	/** Copia una entrada a un archivo y comprueba su tamaño y su SHA-256 contra el manifiesto. */
	private static function extract_checked( $zip, $name, array $entry, $target, callable $error ) {
		$stream = Digitalisimo_Backups::zip_entry_reader( $zip, $name, (int) $entry['size'] );
		$out = @fopen( $target, 'xb' );
		if ( ! $stream || ! $out ) { if ( $stream ) fclose( $stream ); if ( $out ) fclose( $out ); throw new Exception( call_user_func( $error ) ); }
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $target, 0600 );
		$hash = hash_init( 'sha256' );
		$bytes = 0;
		try {
			while ( ! feof( $stream ) ) {
				$chunk = @fread( $stream, 1048576 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) throw new Exception( call_user_func( $error ) );
				$bytes += strlen( $chunk );
				hash_update( $hash, $chunk );
				Digitalisimo_Backups_Chain::write_all( $out, $chunk );
			}
		} finally {
			fclose( $stream );
			fclose( $out );
		}
		if ( $bytes !== (int) $entry['size'] || ! hash_equals( (string) $entry['sha256'], hash_final( $hash ) ) ) throw new Exception( call_user_func( $error ) );
	}
	/** SHA-256 de los bytes exactos de manifest.json, leídos por partes. */
	public static function manifest_sha( $zip ) {
		$stat = $zip->statName( 'manifest.json' );
		$stream = $stat ? $zip->getStream( 'manifest.json' ) : false;
		if ( ! $stream ) throw new Exception( 'Falta el manifiesto del respaldo.' );
		$hash = hash_init( 'sha256' );
		$bytes = 0;
		try {
			while ( ! feof( $stream ) ) {
				$chunk = fread( $stream, 1048576 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) throw new Exception( 'No se pudo leer el manifiesto del respaldo.' );
				$bytes += strlen( $chunk );
				hash_update( $hash, $chunk );
			}
		} finally {
			fclose( $stream );
		}
		if ( $bytes !== (int) $stat['size'] ) throw new Exception( 'No se pudo leer el manifiesto del respaldo.' );
		return hash_final( $hash );
	}
	/** Texto de una cadena rota: qué punto, qué eslabón, por qué y qué hacer; siempre «No se cambió nada del sitio». */
	public static function broken( $point, $index, $sequence, $why, $what ) {
		$which = 0 === (int) $index ? 'el completo de la cadena' : ( (int) $index === (int) $sequence ? 'el propio punto' : 'el incremental ' . (int) $index );
		return 'La cadena del punto ' . $point . ' está rota: ' . $which . ' (eslabón ' . ( (int) $index + 1 ) . ' de ' . ( (int) $sequence + 1 ) . ') ' . $why . '. ' . $what . ' No se cambió nada del sitio.';
	}
	private static function point_label( array $item ) {
		$date = (string) ( $item['date'] ?? '' );
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $date ) ? substr( $date, 0, 16 ) : (string) ( $item['file'] ?? '' );
	}
}
