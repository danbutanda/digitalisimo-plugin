<?php
defined( 'ABSPATH' ) || exit;

/**
 * Restaurar (o verificar) un punto incremental: antes de tocar nada del sitio, comprueba su cadena entera y reconstruye
 * el respaldo completo de ese punto (docs/BACKUPS.md, «Incrementales»). Ese ZIP completo entra después en la
 * restauración de siempre, que lo vuelve a verificar entrada por entrada; la restauración no sabe que vino de una cadena.
 *
 * Tres pasos, para la verificación (en una petición) y para la restauración (por tramos reanudables, con el trabajo
 * Digitalisimo_Backups_Chain_Job):
 * 1. prepare(): la cadena se comprueba eslabón a eslabón, del punto hacia el completo, sin escribir nada fuera de su
 *    carpeta de trabajo: cada eslabón está en el almacén (con su fila del historial o, sin historial, por el nombre que le
 *    da el manifiesto del punto: recuperación ante desastre), se abre como un respaldo (SHA-256 registrado si tiene fila,
 *    descifrado con su clave, firma), el manifiesto del punto nombra a todos sus antecesores por el SHA-256 de su
 *    manifiesto y cada antecesor tiene ese manifiesto exacto, su identificador, su posición y la misma cadena; el
 *    inventario del punto es el que su manifiesto declara. Los eslabones cifrados quedan descifrados en esa carpeta (como
 *    un `.dgbk` que se restaura: la frase sólo vive en esa petición). Deja plan.json, inventory.jsonl y metadata.json.
 * 2. build_slice(): el ZIP del punto, entrada por entrada hasta el plazo del tramo: su base de datos y cada archivo del
 *    eslabón que dice el inventario, comprobado contra su tamaño y su SHA-256. Tras una muerte sigue desde su punto de
 *    control (el escritor de sólo añadir de T2).
 * 3. seal(): el manifiesto del punto como respaldo completo v3 (`type` full, `materialized_from`), firmado con la clave de
 *    la cadena si este sitio la tiene.
 * Una cadena rota se rechaza con un mensaje que dice qué eslabón falla y qué hacer; nada del sitio cambia.
 */
final class Digitalisimo_Backups_Chain_Restore {
	const MARGIN = 67108864;
	const PLAN = 'plan.json';

	/**
	 * Comprueba la cadena del punto `$item` (una fila del historial, o la fila sintética de un punto suelto) y deja su plan
	 * en `$dir`. `$local( fila )` da la ruta del archivo de una fila en el almacén ('' si no está). Con `$loose`, un eslabón
	 * sin fila se busca en el almacén por el nombre que le da el manifiesto del punto (sin historial: su confianza es la de
	 * su firma, o «sin firma»). Devuelve el plan; `$signer` recibe la clave de la cadena si estaba firmada (para sellar).
	 */
	public static function prepare( array $item, array $history, $secret, $storage, callable $local, $dir, $loose = false, &$signer = null ) {
		$signer = null;
		$chain = Digitalisimo_Backups_Chain::row_chain( $item );
		if ( ! $chain || $chain['sequence'] < 1 ) throw new Exception( 'Este respaldo no es un punto incremental.' );
		$point = self::point_label( $item );
		$rows = array();
		foreach ( $chain['links'] as $link ) {
			$row = Digitalisimo_Backups_Chain::link_row( $history, $link );
			if ( ! $row && $loose ) $row = array( 'file' => (string) $link['file'], 'loose' => true );
			if ( ! $row ) throw new Exception( self::broken( $point, (int) $link['sequence'], $chain['sequence'], 'no está en el historial (' . $link['file'] . ')', 'Ese punto no se puede restaurar; elige otro.' ) );
			$rows[] = $row;
		}
		$rows[] = $item;
		$paths = array();
		$missing = array();
		foreach ( $rows as $index => $row ) {
			$path = (string) call_user_func( $local, $row );
			if ( '' === $path ) $missing[] = (string) $row['file'];
			$paths[] = $path;
		}
		if ( $missing ) throw new Exception( 'Al punto ' . $point . ' le faltan en el almacén ' . count( $missing ) . ' de ' . count( $rows ) . ' archivos de su cadena (' . implode( ', ', array_slice( $missing, 0, 3 ) ) . ( count( $missing ) > 3 ? '…' : '' ) . '). ' . ( $loose ? 'Súbelos o tráelos de su destino («Respaldos en los destinos remotos», con ese nombre)' : 'Tráelos con «Traer al almacén» en su fila (trae la cadena entera)' ) . ' y vuelve a restaurar. No se cambió nada del sitio.' );
		$reports = array();
		$top = count( $rows ) - 1;
		try {
			// 1. El punto primero: su manifiesto fija la cadena, el tamaño del punto y su base de datos.
			$reports[ $top ] = self::open_link( $paths[ $top ], $rows[ $top ], $secret, $dir . '/link-' . $top . '.zip', $point, $top, $chain['sequence'] );
			$hashes = array( $top => self::manifest_sha( $reports[ $top ]['zip'] ) );
			$data = array( $top => self::manifest_of( $reports[ $top ]['zip'], $point, $top, $chain['sequence'] ) );
			$head = $data[ $top ];
			$block = Digitalisimo_Backups_Chain::manifest_chain( $head );
			if ( ! $block || $chain['id'] !== $block['id'] || $chain['sequence'] !== $block['sequence'] || $chain['backup_id'] !== ( $head['backup_id'] ?? null ) || ! hash_equals( $chain['manifest_sha256'], $hashes[ $top ] ) || ( ! $loose && $chain['links'] !== $block['links'] ) ) throw new Exception( self::broken( $point, $top, $chain['sequence'], 'no es el que registró el historial (su manifiesto o su cadena no coinciden)', 'Restaura otro punto o el completo de la cadena.' ) );
			$links = $block['links'];
			$declared = array();
			Digitalisimo_Backups_Manifest_Stream::read( $reports[ $top ]['zip'], function( $name, $entry ) use ( &$declared ) {
				if ( in_array( $name, array( 'database.sql', Digitalisimo_Backups_Chain::INVENTORY ), true ) ) $declared[ $name ] = $entry;
			} );
			foreach ( array( 'database.sql', Digitalisimo_Backups_Chain::INVENTORY ) as $name ) if ( ! isset( $declared[ $name ] ) ) throw new Exception( self::broken( $point, $top, $chain['sequence'], 'no declara ' . $name, 'Restaura otro punto o el completo de la cadena.' ) );
			$counts = is_array( $block['inventory'] ?? null ) ? $block['inventory'] : array();
			// Espacio: el punto sin comprimir (archivos y base de datos), lo descifrado de los eslabones cifrados y un margen.
			$needed = (int) ( $counts['bytes'] ?? 0 ) + (int) $declared['database.sql']['size'] + (int) $declared[ Digitalisimo_Backups_Chain::INVENTORY ]['size'] + self::MARGIN;
			foreach ( $paths as $index => $path ) if ( $index !== $top && Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) $needed += (int) @filesize( $path );
			$free = Digitalisimo_Backups_Archive_Inspector::free_space( $storage );
			if ( null !== $free && $free < $needed ) throw new Exception( 'No hay espacio para reconstruir el punto ' . $point . ': se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed ) . ' libres en el almacén privado y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '. No se cambió nada del sitio.' );
			// 2. Cada antecesor, con exactamente el manifiesto que el punto nombra.
			for ( $index = 0; $index < $top; $index++ ) {
				$link = $links[ $index ];
				if ( (string) $link['file'] !== (string) $rows[ $index ]['file'] ) throw new Exception( self::broken( $point, $index, $chain['sequence'], 'no es el archivo que nombra el punto', 'Restaura otro punto o el completo de la cadena.' ) );
				$reports[ $index ] = self::open_link( $paths[ $index ], $rows[ $index ], $secret, $dir . '/link-' . $index . '.zip', $point, $index, $chain['sequence'] );
				$hashes[ $index ] = self::manifest_sha( $reports[ $index ]['zip'] );
				$data[ $index ] = self::manifest_of( $reports[ $index ]['zip'], $point, $index, $chain['sequence'] );
				$problem = '';
				if ( ! hash_equals( (string) $link['manifest_sha256'], $hashes[ $index ] ) ) $problem = 'fue modificado: su manifiesto no es el que nombra el punto';
				elseif ( $link['backup_id'] !== ( $data[ $index ]['backup_id'] ?? null ) ) $problem = 'no es el respaldo que nombra el punto';
				elseif ( 0 === $index && 'full' !== ( $data[ $index ]['type'] ?? '' ) ) $problem = 'no es un respaldo completo';
				elseif ( $index > 0 ) {
					$inner = Digitalisimo_Backups_Chain::manifest_chain( $data[ $index ] );
					if ( ! $inner || $chain['id'] !== $inner['id'] || $index !== $inner['sequence'] || array_slice( $links, 0, $index ) !== $inner['links'] ) $problem = 'no ocupa ese lugar en la cadena (otro padre u otro orden)';
				}
				if ( '' !== $problem ) throw new Exception( self::broken( $point, $index, $chain['sequence'], $problem, 'Restaura otro punto o el completo de la cadena.' ) );
			}
			$trust = 'signed';
			$key = null;
			foreach ( $rows as $index => $row ) {
				if ( (bool) ( $data[ $index ]['multisite'] ?? false ) !== (bool) ( $head['multisite'] ?? false ) || (string) ( $data[ $index ]['table_prefix'] ?? '' ) !== (string) ( $head['table_prefix'] ?? '' ) ) throw new Exception( self::broken( $point, $index, $chain['sequence'], 'es de otra instalación', 'Restaura otro punto o el completo de la cadena.' ) );
				$level = (string) $reports[ $index ]['trust'];
				if ( 'untrusted' === $level ) $trust = 'untrusted';
				elseif ( 'provenance' === $level && 'signed' === $trust ) $trust = 'provenance';
				if ( 'signed' === $level ) {
					$id = (string) ( $reports[ $index ]['key']['id'] ?? '' );
					if ( $key && $id !== (string) $key['id'] ) throw new Exception( self::broken( $point, $index, $chain['sequence'], 'está firmado con otra clave que el resto de su cadena', 'Restaura otro punto o el completo de la cadena.' ) );
					$key = $reports[ $index ]['key'];
				}
			}
			// 3. El inventario del punto, copiado y comprobado contra su manifiesto; su primer nivel y el plan.
			self::extract_checked( $reports[ $top ]['zip'], Digitalisimo_Backups_Chain::INVENTORY, $declared[ Digitalisimo_Backups_Chain::INVENTORY ], $dir . '/inventory.jsonl', function() use ( $point, $top, $chain ) { return self::broken( $point, $top, $chain['sequence'], 'tiene un inventario dañado', 'Restaura otro punto o el completo de la cadena.' ); } );
			self::write_json( $dir . '/metadata.json', $head );
			$plan = array(
				'v' => 1,
				'point' => array( 'file' => (string) $item['file'], 'label' => $point, 'sequence' => (int) $chain['sequence'], 'chain' => (string) $chain['id'], 'manifest_sha256' => $hashes[ $top ] ),
				'links' => array(),
				'trust' => $trust,
				'key' => 'signed' === $trust ? $key : null,
				'via' => (string) ( $reports[ $top ]['via'] ?? '' ),
				'loose' => (bool) $loose,
				'files' => (int) ( $counts['files'] ?? -1 ),
				'bytes' => (int) ( $counts['bytes'] ?? -1 ),
				'database' => array( 'size' => (int) $declared['database.sql']['size'], 'sha256' => (string) $declared['database.sql']['sha256'] ),
				'inventory' => (int) $declared[ Digitalisimo_Backups_Chain::INVENTORY ]['size'],
			);
			foreach ( $rows as $index => $row ) {
				$source = 'dgbk' === $reports[ $index ]['container'] ? $dir . '/link-' . $index . '.zip' : $paths[ $index ];
				$plan['links'][ $index ] = array( 'file' => (string) $row['file'], 'path' => $source, 'fp' => Digitalisimo_Backups_Backup_Job::fingerprint( $source ) );
			}
			self::write_json( $dir . '/' . self::PLAN, $plan );
			// La clave del punto firma el reconstruido; close() no la borra (la borra quien la recibe).
			if ( 'signed' === $trust && ! empty( $reports[ $top ]['root'] ) ) { $signer = $reports[ $top ]['root']; $reports[ $top ]['root'] = null; }
		} finally {
			foreach ( $reports as $report ) {
				// Lo descifrado queda en la carpeta del plan; sólo se sueltan los ZIP y las claves.
				$report['staging'] = '';
				Digitalisimo_Backups_Security::close( $report );
			}
		}
		return $plan;
	}

	/**
	 * Un tramo de la reconstrucción hasta `$deadline` (INF: entera). `$cursor` es el punto de control (vacío al empezar).
	 * Devuelve true si escribió la última entrada (el siguiente paso es seal()).
	 */
	public static function build_slice( array $plan, $dir, array &$cursor, $deadline = INF ) {
		$cursor += array( 'stage' => 'sql', 'offset' => 0, 'files' => 0, 'bytes' => 0, 'zip' => 0, 'entries' => 0, 'database' => 0 );
		if ( 'done' === $cursor['stage'] ) return true;
		$point = (string) $plan['point']['label'];
		$sequence = (int) $plan['point']['sequence'];
		$top = count( $plan['links'] ) - 1;
		foreach ( $plan['links'] as $index => $link ) {
			if ( ! Digitalisimo_Backups_Backup_Job::same_fingerprint( Digitalisimo_Backups_Backup_Job::fingerprint( (string) $link['path'] ), (array) $link['fp'] ) ) throw new Digitalisimo_Backups_Permanent_Error( self::broken( $point, (int) $index, $sequence, 'cambió o desapareció del almacén mientras se reconstruía el punto', 'Vuelve a restaurar.' ) );
		}
		$zips = array();
		$entries = self::append_handle( $dir . '/point.entries.jsonl', (int) $cursor['entries'] );
		$writer = null;
		$units = 0;
		try {
			$writer = new Digitalisimo_Backups_Zip_Writer( $dir . '/point.zip', (int) $cursor['zip'] );
			$open = function( $index ) use ( &$zips, $plan ) {
				if ( ! isset( $zips[ $index ] ) ) {
					$zip = new ZipArchive();
					if ( true !== $zip->open( (string) $plan['links'][ $index ]['path'] ) ) throw new Exception( 'No se pudo abrir el eslabón ' . ( (int) $index + 1 ) . ' de la cadena.' );
					$zips[ $index ] = $zip;
				}
				return $zips[ $index ];
			};
			if ( 'sql' === $cursor['stage'] ) {
				$record = self::copy_entry( $writer, $open( $top ), 'database.sql', (int) $plan['database']['size'], 0644, (string) $plan['database']['sha256'] );
				if ( null === $record ) throw new Exception( self::broken( $point, $top, $sequence, 'trae una base de datos que no es la de su manifiesto', 'Restaura otro punto o el completo de la cadena.' ) );
				Digitalisimo_Backups_Chain::write_all( $entries, json_encode( $record ) . "\n" );
				$cursor['database'] = (int) $record['s'];
				$cursor['stage'] = 'files';
				$units++;
			}
			$inventory = $dir . '/inventory.jsonl';
			$size = (int) $plan['inventory'];
			if ( 'files' === $cursor['stage'] && (int) $cursor['offset'] < $size ) {
				foreach ( Digitalisimo_Backups_Chain::records( $inventory, (int) $cursor['offset'], $size ) as $row ) {
					if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) break;
					list( , $line, , $next ) = $row;
					if ( ! Digitalisimo_Backups_Chain::valid_inventory_line( $line, $sequence ) ) throw new Exception( self::broken( $point, $top, $sequence, 'tiene un inventario mal formado', 'Restaura otro punto o el completo de la cadena.' ) );
					if ( empty( $line['x'] ) ) {
						$link = (int) $line['l'];
						$source = $open( $link );
						$stat = $source->statName( $line['n'] );
						$mode = 0644;
						if ( is_array( $stat ) && method_exists( $source, 'getExternalAttributesIndex' ) && $source->getExternalAttributesIndex( (int) $stat['index'], $opsys, $attributes ) && ZipArchive::OPSYS_UNIX === $opsys && ( $attributes >> 16 ) & 0777 ) $mode = ( $attributes >> 16 ) & 0777;
						$record = is_array( $stat ) && (int) $stat['size'] === (int) $line['s'] ? self::copy_entry( $writer, $source, $line['n'], (int) $line['s'], $mode, $line['h'], (int) ( $stat['mtime'] ?? time() ) ) : null;
						if ( null === $record ) throw new Exception( self::broken( $point, $link, $sequence, 'no trae ' . substr( $line['n'], 10 ) . ' con el contenido que el punto exige', 'Restaura otro punto o el completo de la cadena.' ) );
						Digitalisimo_Backups_Chain::write_all( $entries, json_encode( $record ) . "\n" );
						$cursor['files']++;
						$cursor['bytes'] += (int) $record['s'];
					}
					$cursor['offset'] = $next;
					$units++;
				}
			}
			if ( 'files' === $cursor['stage'] && (int) $cursor['offset'] >= $size ) {
				if ( (int) $cursor['files'] !== (int) $plan['files'] || (int) $cursor['bytes'] !== (int) $plan['bytes'] ) throw new Exception( self::broken( $point, $top, $sequence, 'tiene un inventario que no suma lo que declara su manifiesto', 'Restaura otro punto o el completo de la cadena.' ) );
				$cursor['stage'] = 'done';
			}
			if ( ! fflush( $entries ) ) throw new Exception( 'No se pudo guardar el avance del punto en el almacén privado.' );
			$cursor['zip'] = $writer->commit();
			$cursor['entries'] = (int) ftell( $entries );
		} finally {
			if ( $writer ) $writer->close();
			fclose( $entries );
			foreach ( $zips as $zip ) $zip->close();
		}
		Digitalisimo_Backups_Jobs::progress( 10 + (int) floor( 80 * (int) $cursor['offset'] / max( 1, $size ) ), 'Reconstruyendo el punto: ' . (int) $cursor['files'] . ' de ' . (int) $plan['files'] . ' archivos…' );
		return 'done' === $cursor['stage'];
	}

	/**
	 * Cierra el ZIP del punto con su manifiesto de respaldo completo v3, firmado con `$root` (la clave de la cadena) si la
	 * hay. Idempotente: tras una muerte vuelve a escribirlo desde el final de las entradas. Devuelve su ruta.
	 */
	public static function seal( array $plan, $dir, array $cursor, $root = null ) {
		if ( 'done' !== ( $cursor['stage'] ?? '' ) ) throw new Exception( 'El punto aún no está reconstruido.' );
		$head = json_decode( (string) @file_get_contents( $dir . '/metadata.json' ), true );
		if ( ! is_array( $head ) ) throw new Exception( 'Falta el manifiesto del punto en su carpeta de trabajo.' );
		$data = $head;
		foreach ( array( 'entries_count', 'chain', 'auth', 'notices', 'job_id', 'packer', 'database_snapshot_at', 'files_started_at', 'files_completed_at', 'excluded' ) as $key ) unset( $head[ $key ] );
		$head['type'] = 'full';
		$head['file_count'] = (int) $cursor['files'];
		$head['uncompressed_size'] = (int) $cursor['bytes'] + (int) $cursor['database'];
		$head['database_size'] = (int) $cursor['database'];
		$tail = array();
		foreach ( array( 'job_id', 'packer', 'database_snapshot_at', 'files_started_at', 'files_completed_at', 'notices', 'excluded' ) as $key ) if ( array_key_exists( $key, $data ) ) $tail[ $key ] = $data[ $key ];
		$tail['materialized_from'] = array( 'chain' => (string) $plan['point']['chain'], 'sequence' => (int) $plan['point']['sequence'], 'links' => count( $plan['links'] ), 'manifest_sha256' => (string) $plan['point']['manifest_sha256'] );
		$target = $dir . '/point.zip';
		$entries_path = $dir . '/point.entries.jsonl';
		$listed = (int) $cursor['entries'];
		$mac = null;
		$key_id = '';
		$key = is_array( $plan['key'] ?? null ) ? $plan['key'] : null;
		if ( $root && $key && Digitalisimo_Backups_Keys::valid_descriptor( $key ) ) {
			$key_id = Digitalisimo_Backups_Keys::key_id( $root );
			if ( ! hash_equals( (string) $key['id'], $key_id ) ) throw new Exception( 'La clave del punto no es la de su firma.' );
			$tail['auth'] = Digitalisimo_Backups_Manifest_Auth::block( $key, false );
			$mac = hash_init( 'sha512', HASH_HMAC, Digitalisimo_Backups_Keys::mac_key( $root ) );
			hash_update( $mac, Digitalisimo_Backups_Manifest_Auth::PREFIX . "\n" . $key_id . "\n" );
		}
		$writer = new Digitalisimo_Backups_Zip_Writer( $target, (int) $cursor['zip'] );
		$entries = self::append_handle( $entries_path, $listed );
		try {
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
			if ( $count !== (int) $cursor['files'] + 2 ) throw new Exception( 'El respaldo completo reconstruido no tiene todas sus entradas.' );
		} finally {
			$writer->close();
			fclose( $entries );
		}
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $target, 0600 );
		return $target;
	}

	/**
	 * Comprobar y reconstruir en esta petición (verificar un punto, y las pruebas): prepare() + build_slice() + seal() en una
	 * carpeta nueva de `.staging/` del almacén. Devuelve path (el ZIP), staging (su carpeta; se borra al cerrar el informe),
	 * item (la fila con la que se abre: su confianza), secret, trust, links, files, bytes, sha256 y summary.
	 */
	public static function materialize( array $item, array $history, $secret, $storage, callable $local, $loose = false ) {
		$staging = Digitalisimo_Backups_Security::new_staging( $storage );
		$signer = null;
		try {
			$plan = self::prepare( $item, $history, $secret, $storage, $local, $staging, $loose, $signer );
			$cursor = array();
			self::build_slice( $plan, $staging, $cursor, INF );
			$target = self::seal( $plan, $staging, $cursor, $signer );
		} catch ( Throwable $e ) {
			Digitalisimo_Backups_Security::remove_tree( $staging );
			throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e );
		} finally {
			if ( $signer ) Digitalisimo_Backups_Sodium::memzero( $signer );
		}
		self::tidy( $staging );
		$sha = @hash_file( 'sha256', $target );
		if ( false === $sha ) { Digitalisimo_Backups_Security::remove_tree( $staging ); throw new Exception( 'No se pudo comprobar el respaldo completo reconstruido del punto ' . $plan['point']['label'] . '.' ); }
		return self::opened( $plan, $target, $staging, $sha, $secret, $cursor );
	}
	/** Lo que se pasa a Security::open() para abrir el punto reconstruido con la confianza de su cadena. */
	public static function opened( array $plan, $target, $staging, $sha, $secret, array $cursor ) {
		$trust = (string) $plan['trust'];
		return array(
			'path' => $target,
			'staging' => $staging,
			// Firmado con la clave de la cadena (si este sitio la tiene o se escribió su frase), registrado aquí o sin firma.
			'item' => 'untrusted' === $trust ? array() : array( 'sha256' => $sha, 'origin' => 'created' ),
			'secret' => 'signed' === $trust && 'secret' === $plan['via'] ? (string) $secret : '',
			'trust' => $trust,
			'links' => count( $plan['links'] ),
			'files' => (int) $cursor['files'],
			'bytes' => (int) $cursor['bytes'],
			'sha256' => $sha,
			'summary' => self::summary( $plan, (int) $cursor['files'] ),
		);
	}
	public static function summary( array $plan, $files ) {
		return 'Punto incremental ' . (int) $plan['point']['sequence'] . ' de su cadena reconstruido entero desde ' . count( $plan['links'] ) . ' archivos comprobados (el completo y sus incrementales' . ( ! empty( $plan['loose'] ) ? ', sin historial: por sus manifiestos' : '' ) . '): ' . (int) $files . ' archivos y la base de datos de ese momento.';
	}
	/** Deja en la carpeta sólo el ZIP del punto (los eslabones descifrados y las listas ya no hacen falta). */
	public static function tidy( $dir ) {
		foreach ( (array) @scandir( $dir ) as $name ) if ( '.' !== $name && '..' !== $name && 'point.zip' !== $name ) @unlink( $dir . '/' . $name );
	}

	/**
	 * La fila sintética de un punto suelto (sin fila en el historial: un servidor nuevo, una base de datos perdida, un
	 * archivo subido o traído de un destino) a partir de su manifiesto, o null si no es un eslabón incremental v3.
	 */
	public static function loose_item( $zip, $file, $date = '' ) {
		$data = Digitalisimo_Backups_Manifest_Stream::read( $zip )['data'];
		$block = Digitalisimo_Backups_Chain::manifest_chain( $data );
		if ( ! $block || ! Digitalisimo_Backups_Chain::valid_id( $data['backup_id'] ?? null ) ) return null;
		$counts = is_array( $block['inventory'] ?? null ) ? $block['inventory'] : array();
		return array(
			'file' => (string) $file,
			'date' => '' !== (string) $date ? (string) $date : str_replace( 'T', ' ', substr( (string) ( $data['created_at'] ?? '' ), 0, 19 ) ),
			'loose' => true,
			'chain' => array( 'v' => Digitalisimo_Backups_Chain::VERSION, 'id' => (string) $block['id'], 'sequence' => (int) $block['sequence'], 'backup_id' => (string) $data['backup_id'], 'manifest_sha256' => self::manifest_sha( $zip ), 'links' => $block['links'], 'files' => (int) ( $counts['files'] ?? 0 ), 'bytes' => (int) ( $counts['bytes'] ?? 0 ) ),
		);
	}

	/* ------------------------------------------------------------------ utilidades */

	/** Un eslabón abierto como un respaldo: un ZIP con Security::open(), un DGBK descifrado en `$inner` (open_sealed()). */
	private static function open_link( $path, array $row, $secret, $inner, $point, $index, $sequence ) {
		$row = ! empty( $row['loose'] ) ? array() : $row;
		try {
			return Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ? self::open_sealed( $path, $row, (string) $secret, $inner ) : Digitalisimo_Backups_Security::open( $path, $row, (string) $secret );
		} catch ( Exception $e ) {
			throw new Exception( self::broken( $point, $index, $sequence, 'no supera su comprobación: ' . rtrim( $e->getMessage(), '.' ), 'Restaura otro punto o el completo de la cadena.' ) );
		}
	}
	private static function manifest_of( $zip, $point, $index, $sequence ) {
		try {
			return Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
		} catch ( Exception $e ) {
			throw new Exception( self::broken( $point, $index, $sequence, 'tiene un manifiesto ilegible: ' . rtrim( $e->getMessage(), '.' ), 'Restaura otro punto o el completo de la cadena.' ) );
		}
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
			if ( file_exists( $inner ) ) @unlink( $inner );
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
		if ( file_exists( $target ) ) @unlink( $target );
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
	private static function append_handle( $path, $size ) {
		$handle = @fopen( $path, 'c+b' );
		if ( ! $handle || ! ftruncate( $handle, (int) $size ) || 0 !== fseek( $handle, (int) $size ) ) { if ( $handle ) fclose( $handle ); throw new Exception( 'No se pudo preparar el punto en el almacén privado.' ); }
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
		return $handle;
	}
	private static function write_json( $path, $data ) {
		$json = wp_json_encode( $data );
		if ( ! is_string( $json ) || strlen( $json ) !== @file_put_contents( $path . '.tmp', $json ) || ! @rename( $path . '.tmp', $path ) ) throw new Exception( 'No se pudo guardar la comprobación de la cadena en el almacén privado.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
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
