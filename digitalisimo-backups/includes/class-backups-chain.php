<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cadenas de incrementales (docs/BACKUPS.md, «Incrementales»).
 *
 * Una cadena es un respaldo completo (la base, un ZIP v3 de siempre) y, detrás, incrementales que sólo guardan los
 * archivos nuevos o cambiados respecto a su padre, con la base de datos entera de su momento y el inventario completo del
 * punto (`inventory.jsonl`: cada archivo que existía con su tamaño, su SHA-256 y el eslabón que guarda sus bytes, más los
 * borrados desde el padre). Cada incremental nombra a todos sus antecesores por el SHA-256 de su manifiesto (firmado si el
 * sitio tiene clave), así que el manifiesto del punto fija la cadena entera. Un incremental es siempre un punto de
 * restauración completo: restaurarlo reconstruye antes un respaldo completo (Digitalisimo_Backups_Chain_Restore) y sólo
 * después entra en la restauración de siempre.
 *
 * Esta clase decide cuándo un trabajo continúa la cadena de su automatización o empieza una nueva con un completo
 * (consolidación: cada N incrementales o D días, o si algo de la cadena no está), guarda el índice lateral de cada punto
 * (`<almacén>/chains/<backup_id>.jsonl`, o `.dgbk` cifrado con la clave del trabajo si la cadena se cifra) con el que el
 * siguiente incremental compara sin abrir el ZIP anterior, y calcula qué eslabones no puede borrar la retención.
 */
final class Digitalisimo_Backups_Chain {
	const VERSION = 1;
	/** Incrementales seguidos antes de un completo nuevo: con una automatización diaria, un completo por semana. */
	const FULL_EVERY = 6;
	/** Días como máximo desde el completo de la cadena (aunque no se llegue a FULL_EVERY). */
	const FULL_DAYS = 7;
	const MAX_FULL_EVERY = 60;
	const MAX_FULL_DAYS = 90;
	/** Entrada del ZIP de un incremental con el inventario completo del punto. */
	const INVENTORY = 'inventory.jsonl';
	/** Carpeta de los índices laterales dentro del almacén privado. */
	const DIRECTORY = 'chains';
	/** Filas que el historial conserva como mínimo (Digitalisimo_Backups::history_cap()). */
	const HISTORY_ROWS = 100;
	/** Hasta este tamaño, un archivo del mismo tamaño que en el padre se compara por su SHA-256 antes de copiarlo. */
	const HASH_FIRST_MAX = 268435456;

	/* ------------------------------------------------------------------ Ajustes */

	/** Modo y consolidación de una automatización o de un respaldo manual: `mode` (full o incremental), `full_every`, `full_days`. */
	public static function settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$every = isset( $input['full_every'] ) && is_numeric( $input['full_every'] ) ? (int) $input['full_every'] : self::FULL_EVERY;
		$days = isset( $input['full_days'] ) && is_numeric( $input['full_days'] ) ? (int) $input['full_days'] : self::FULL_DAYS;
		return array(
			'mode' => 'incremental' === ( $input['mode'] ?? '' ) ? 'incremental' : 'full',
			'full_every' => max( 1, min( self::MAX_FULL_EVERY, $every ) ),
			'full_days' => max( 1, min( self::MAX_FULL_DAYS, $days ) ),
		);
	}
	/** Lo que fija la especificación de un trabajo: null si es completo. */
	public static function spec_value( $input ) {
		$settings = self::settings( $input );
		return 'incremental' === $settings['mode'] ? array( 'full_every' => $settings['full_every'], 'full_days' => $settings['full_days'] ) : null;
	}
	/** El trabajo es de una cadena: respaldo de la instalación o la red, ni copia previa, ni extracción, ni migración. */
	public static function job_enabled( array $spec ) {
		if ( ! is_array( $spec['incremental'] ?? null ) || ! empty( $spec['inline'] ) || 'full' !== (string) ( $spec['type'] ?? 'full' ) ) return false;
		if ( ! empty( $spec['extract'] ) || ! empty( $spec['migration'] ) || ! empty( $spec['kind'] ) ) return false;
		return in_array( (string) ( $spec['scope'] ?? '' ), array( 'installation', 'network' ), true );
	}
	/** Plan que fijó la fase «preflight» (base o incremental), o null si el trabajo no es de una cadena. */
	public static function job_plan( array $job ) {
		$plan = $job['checkpoint']['preflight']['chain'] ?? null;
		return is_array( $plan ) && self::VERSION === (int) ( $plan['v'] ?? 0 ) && in_array( $plan['mode'] ?? '', array( 'base', 'incremental' ), true ) ? $plan : null;
	}
	/** El plan de un incremental (el empaquetado y la verificación sólo cambian para él), o null. */
	public static function job_incremental( array $job ) {
		$plan = self::job_plan( $job );
		return $plan && 'incremental' === $plan['mode'] ? $plan : null;
	}

	/* ------------------------------------------------------------------ Filas del historial */

	public static function valid_id( $id ) { return is_string( $id ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $id ); }
	public static function valid_sha( $sha ) { return is_string( $sha ) && 1 === preg_match( '/^[0-9a-f]{64}$/D', $sha ); }
	public static function valid_file( $file ) { return is_string( $file ) && '' !== $file && basename( $file ) === $file && '.' !== $file[0] && ! preg_match( '#[\x00-\x1f/\\\\]#', $file ); }
	private static function valid_link( $link, $sequence ) {
		return is_array( $link ) && $sequence === ( $link['sequence'] ?? null ) && self::valid_file( $link['file'] ?? null ) && self::valid_id( $link['backup_id'] ?? null ) && self::valid_sha( $link['manifest_sha256'] ?? null );
	}
	/** Lista de eslabones (del completo al padre) bien formada. */
	public static function valid_links( $links, $count ) {
		if ( ! is_array( $links ) || array_values( $links ) !== $links || count( $links ) !== (int) $count ) return false;
		foreach ( $links as $index => $link ) if ( ! self::valid_link( $link, $index ) ) return false;
		return true;
	}
	/** El bloque `chain` de una fila del historial, si es un punto de una cadena de este formato; si no, null. */
	public static function row_chain( $row ) {
		$chain = is_array( $row ) && is_array( $row['chain'] ?? null ) ? $row['chain'] : null;
		if ( ! $chain || self::VERSION !== (int) ( $chain['v'] ?? 0 ) || ! is_int( $chain['sequence'] ?? null ) || $chain['sequence'] < 0 || $chain['sequence'] > self::MAX_FULL_EVERY ) return null;
		if ( ! self::valid_id( $chain['id'] ?? null ) || ! self::valid_id( $chain['backup_id'] ?? null ) || ! self::valid_sha( $chain['manifest_sha256'] ?? null ) ) return null;
		if ( ! self::valid_links( $chain['links'] ?? null, $chain['sequence'] ) || ! self::valid_file( $row['file'] ?? null ) ) return null;
		return $chain;
	}
	/**
	 * El bloque `chain` del manifiesto de un eslabón incremental (v3) bien formado, o null: el incremental heredado (v2)
	 * no tiene cadena.
	 */
	public static function manifest_chain( $data ) {
		if ( ! is_array( $data ) || 'digitalisimo-backup/v3' !== ( $data['format'] ?? '' ) || 'incremental' !== ( $data['type'] ?? '' ) ) return null;
		$chain = is_array( $data['chain'] ?? null ) ? $data['chain'] : null;
		// Ninguna cadena tiene más eslabones que el tope de «un completo cada N»: un manifiesto ajeno no hace buscar miles.
		if ( ! $chain || self::VERSION !== ( $chain['version'] ?? null ) || ! self::valid_id( $chain['id'] ?? null ) || ! is_int( $chain['sequence'] ?? null ) || $chain['sequence'] < 1 || $chain['sequence'] > self::MAX_FULL_EVERY || ! self::valid_links( $chain['links'] ?? null, $chain['sequence'] ) ) return null;
		return $chain;
	}
	/** Una fila que es un punto incremental (no la base). */
	public static function is_incremental( $row ) {
		$chain = self::row_chain( $row );
		return $chain && $chain['sequence'] > 0;
	}
	/** El eslabón que una fila es para sus sucesores. */
	public static function link_of( array $row ) {
		$chain = self::row_chain( $row );
		return $chain ? array( 'sequence' => $chain['sequence'], 'file' => (string) $row['file'], 'backup_id' => $chain['backup_id'], 'manifest_sha256' => $chain['manifest_sha256'] ) : null;
	}
	/** Archivos de la cadena de un punto, del completo al punto mismo; array() si la fila no es de una cadena. */
	public static function files( $row ) {
		$chain = self::row_chain( $row );
		if ( ! $chain ) return array();
		$files = array();
		foreach ( $chain['links'] as $link ) $files[] = (string) $link['file'];
		$files[] = (string) $row['file'];
		return $files;
	}
	/** Fila del historial de un eslabón: la del mismo archivo con el mismo respaldo y el mismo manifiesto. */
	public static function link_row( array $history, array $link ) {
		foreach ( $history as $row ) {
			if ( ! is_array( $row ) || (string) ( $row['file'] ?? '' ) !== (string) $link['file'] ) continue;
			$chain = self::row_chain( $row );
			if ( $chain && $chain['backup_id'] === $link['backup_id'] && hash_equals( $chain['manifest_sha256'], (string) $link['manifest_sha256'] ) && (int) $chain['sequence'] === (int) $link['sequence'] ) return $row;
			return null;
		}
		return null;
	}

	/* ------------------------------------------------------------------ Plan de un trabajo */

	/**
	 * Al empezar un trabajo de una cadena: ¿continúa la cadena de su automatización (o del respaldo manual) o empieza una
	 * nueva con un completo? Continúa sólo si el último respaldo de esa política es un punto de una cadena de esta
	 * instalación, con la misma clave y el mismo cifrado, con menos de N incrementales y una base de menos de D días, con
	 * todos sus eslabones en el historial y con alguna copia ($usable) y con su índice lateral íntegro ($index_ok). Si no,
	 * el trabajo es la base de una cadena nueva y `reason` dice por qué.
	 */
	public static function plan( array $spec, array $history, $now, callable $usable, callable $index_ok ) {
		$settings = self::settings( array( 'mode' => 'incremental' ) + (array) ( $spec['incremental'] ?? array() ) );
		$plan = array( 'v' => self::VERSION, 'mode' => 'base', 'sequence' => 0, 'id' => '', 'links' => array(), 'parent' => null, 'base_at' => (int) $now, 'full_every' => $settings['full_every'], 'full_days' => $settings['full_days'], 'reason' => '' );
		$head = null;
		foreach ( $history as $row ) {
			if ( ! is_array( $row ) || (string) ( $row['policy'] ?? '' ) !== (string) ( $spec['policy'] ?? '' ) || in_array( (string) ( $row['origin'] ?? 'created' ), array( 'imported', 'uploaded', 'added' ), true ) ) continue;
			$head = $row;
			break;
		}
		if ( ! $head ) return array( 'reason' => 'first' ) + $plan;
		$chain = self::row_chain( $head );
		$why = '';
		if ( ! $chain ) $why = 'not_chain';
		elseif ( (string) ( $head['scope'] ?? '' ) !== (string) ( $spec['scope'] ?? '' ) ) $why = 'scope';
		elseif ( $chain['sequence'] >= $settings['full_every'] ) $why = 'full_every';
		elseif ( (int) $now - (int) ( $chain['base_at'] ?? 0 ) >= $settings['full_days'] * 86400 ) $why = 'full_days';
		elseif ( (string) ( $head['key_id'] ?? '' ) !== (string) ( $spec['security']['key_id'] ?? '' ) || ( 'dgbk' === (string) ( $head['container'] ?? '' ) ) !== ! empty( $spec['security']['encrypt'] ) ) $why = 'security';
		if ( '' === $why ) {
			foreach ( $chain['links'] as $link ) {
				$row = self::link_row( $history, $link );
				if ( ! $row || ! $usable( $row ) ) { $why = 'missing_link'; break; }
			}
		}
		if ( '' === $why && ! $usable( $head ) ) $why = 'missing_link';
		if ( '' === $why && ! $index_ok( $head ) ) $why = 'index';
		if ( '' !== $why ) return array( 'reason' => $why ) + $plan;
		$links = $chain['links'];
		$links[] = self::link_of( $head );
		return array(
			'v' => self::VERSION,
			'mode' => 'incremental',
			'sequence' => $chain['sequence'] + 1,
			'id' => $chain['id'],
			'links' => $links,
			'parent' => array( 'file' => (string) $head['file'], 'backup_id' => $chain['backup_id'], 'manifest_sha256' => $chain['manifest_sha256'], 'sequence' => $chain['sequence'], 'index' => (array) ( $chain['index'] ?? array() ) ),
			'base_at' => (int) ( $chain['base_at'] ?? 0 ),
			'full_every' => $settings['full_every'],
			'full_days' => $settings['full_days'],
			'reason' => '',
		);
	}
	/** Por qué un trabajo de una cadena salió completo, para el registro del trabajo. */
	public static function reason_text( $reason ) {
		$texts = array(
			'first' => 'primer respaldo de la cadena',
			'not_chain' => 'el último respaldo de esta automatización no es de una cadena',
			'scope' => 'el último punto es de otro alcance',
			'full_every' => 'la cadena ya tiene todos sus incrementales: se consolida con un completo nuevo',
			'full_days' => 'el completo de la cadena superó sus días: se consolida con un completo nuevo',
			'security' => 'cambió la clave o el cifrado de los respaldos',
			'missing_link' => 'falta un eslabón de la cadena anterior (sin copias o fuera del historial)',
			'index' => 'falta o no coincide el índice del último punto',
		);
		return $texts[ (string) $reason ] ?? '';
	}

	/* ------------------------------------------------------------------ Índice lateral de cada punto */

	public static function directory( $storage ) {
		$directory = rtrim( (string) $storage, '/\\' ) . '/' . self::DIRECTORY;
		if ( is_link( $directory ) ) throw new Exception( 'La carpeta de índices de las cadenas es un enlace simbólico.' );
		if ( ! is_dir( $directory ) && ! @mkdir( $directory, 0700 ) && ! is_dir( $directory ) ) throw new Exception( 'No se pudo crear la carpeta de índices de las cadenas en el almacén privado.' );
		return $directory;
	}
	public static function index_name( $backup_id, $encrypted ) { return strtolower( (string) $backup_id ) . ( $encrypted ? '.dgbk' : '.jsonl' ); }
	/**
	 * Escribe el índice lateral de un punto desde `$lines` (cada uno: n, s, h, l). Con `$plan` cifrado (la clave del trabajo)
	 * queda en un DGBK: lleva nombres de archivos, que un respaldo cifrado no deja en claro. Devuelve file, sha256,
	 * encrypted y files. Idempotente: reescribe el del mismo respaldo.
	 */
	public static function write_index( $storage, array $header, $lines, array $plan, array $meta ) {
		$directory = self::directory( $storage );
		$encrypted = ! empty( $plan['encrypt'] ) && ! empty( $plan['root'] );
		$name = self::index_name( $header['backup_id'], $encrypted );
		$target = $directory . '/' . $name;
		$plain = $directory . '/.' . $name . '.' . bin2hex( random_bytes( 4 ) ) . '.part';
		$sealed = $plain . '.dgbk';
		$handle = @fopen( $plain, 'xb' );
		if ( ! $handle ) throw new Exception( 'No se pudo escribir el índice de la cadena en el almacén privado.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $plain, 0600 );
		$count = 0;
		try {
			$header = array( 'format' => 'digitalisimo-chain-index/1' ) + $header;
			self::write_all( $handle, json_encode( $header, JSON_UNESCAPED_SLASHES ) . "\n" );
			$buffer = '';
			foreach ( $lines as $line ) {
				$json = json_encode( array( 'n' => (string) $line['n'], 's' => (int) $line['s'], 'h' => (string) $line['h'], 'l' => (int) $line['l'] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				if ( ! is_string( $json ) ) throw new Exception( 'Un nombre de archivo del índice de la cadena no es UTF-8 válido.' );
				$buffer .= $json . "\n";
				$count++;
				if ( strlen( $buffer ) >= 1048576 ) { self::write_all( $handle, $buffer ); $buffer = ''; }
			}
			self::write_all( $handle, $buffer );
			if ( ! fflush( $handle ) ) throw new Exception( 'No se pudo escribir el índice de la cadena en el almacén privado.' );
			if ( function_exists( 'fsync' ) ) @fsync( $handle );
		} catch ( Throwable $e ) {
			fclose( $handle );
			@unlink( $plain );
			throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e );
		}
		fclose( $handle );
		if ( (int) ( $header['files'] ?? -1 ) !== $count ) { @unlink( $plain ); throw new Exception( 'El índice de la cadena no tiene todos los archivos del punto.' ); }
		try {
			if ( $encrypted ) {
				Digitalisimo_Backups_Encrypted_File::encrypt( $plain, $sealed, $plan['root'], $plan['descriptor'], $meta );
				@unlink( $plain );
				$from = $sealed;
			} else {
				$from = $plain;
			}
			if ( ( file_exists( $target ) || is_link( $target ) ) && ! @unlink( $target ) ) throw new Exception( 'No se pudo reemplazar el índice de la cadena.' );
			if ( ! @rename( $from, $target ) ) throw new Exception( 'No se pudo publicar el índice de la cadena en el almacén privado.' );
		} catch ( Throwable $e ) {
			@unlink( $plain );
			@unlink( $sealed );
			throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e );
		}
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $target, 0600 );
		$sha = @hash_file( 'sha256', $target );
		if ( false === $sha ) throw new Exception( 'No se pudo comprobar el índice de la cadena.' );
		return array( 'file' => $name, 'sha256' => $sha, 'encrypted' => $encrypted, 'files' => $count );
	}
	/** Ruta del índice lateral de un respaldo, si existe con el nombre de su respaldo y su SHA-256 registrado; si no, ''. */
	public static function index_file( $storage, $backup_id, array $index ) {
		$name = (string) ( $index['file'] ?? '' );
		if ( ! self::valid_id( $backup_id ) || ! self::valid_sha( $index['sha256'] ?? null ) || self::index_name( $backup_id, ! empty( $index['encrypted'] ) ) !== $name ) return '';
		$path = rtrim( (string) $storage, '/\\' ) . '/' . self::DIRECTORY . '/' . $name;
		clearstatcache( true, $path );
		if ( is_link( $path ) || ! is_file( $path ) ) return '';
		$sha = @hash_file( 'sha256', $path );
		return false !== $sha && hash_equals( (string) $index['sha256'], $sha ) ? $path : '';
	}
	/** Ruta del índice lateral de una fila del historial (ver index_file()). */
	public static function index_path( $storage, $row ) {
		$chain = self::row_chain( $row );
		return $chain ? self::index_file( $storage, $chain['backup_id'], is_array( $chain['index'] ?? null ) ? $chain['index'] : array() ) : '';
	}
	/**
	 * Deja en `$target` el índice en claro de `$path` (descifrado con la clave del trabajo si está cifrado) y comprueba su
	 * cabecera contra `$expect` (backup_id, manifest_sha256, chain, sequence). Devuelve la cabecera con `header_size`.
	 */
	public static function read_index( $path, array $index, array $expect, array $security, $target ) {
		if ( file_exists( $target ) || is_link( $target ) ) @unlink( $target );
		if ( ! empty( $index['encrypted'] ) ) {
			if ( empty( $security['root'] ) ) throw new Exception( 'El índice cifrado del punto anterior necesita la clave de este trabajo.' );
			Digitalisimo_Backups_Encrypted_File::decrypt( $path, $security['root'], $target );
		} elseif ( ! @copy( $path, $target ) ) {
			throw new Exception( 'No se pudo copiar el índice del punto anterior al área de trabajo.' );
		}
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $target, 0600 );
		$handle = @fopen( $target, 'rb' );
		$line = $handle ? fgets( $handle ) : false;
		if ( $handle ) fclose( $handle );
		$header = false === $line ? null : json_decode( $line, true );
		if ( ! is_array( $header ) || 'digitalisimo-chain-index/1' !== ( $header['format'] ?? '' ) || ( $header['backup_id'] ?? '' ) !== $expect['backup_id'] || ! hash_equals( (string) $expect['manifest_sha256'], (string) ( $header['manifest_sha256'] ?? '' ) ) || ( $header['chain'] ?? '' ) !== $expect['chain'] || (int) ( $header['sequence'] ?? -1 ) !== (int) $expect['sequence'] || ! is_int( $header['files'] ?? null ) || $header['files'] < 0 ) {
			@unlink( $target );
			throw new Exception( 'El índice del punto anterior no corresponde a su respaldo.' );
		}
		return $header + array( 'header_size' => strlen( $line ) );
	}
	/**
	 * El índice del padre de un plan, en claro en `$target` y comprobado (su SHA-256 registrado y su cabecera). Si no
	 * puede, lanza: el trabajo vuelve a «preflight», que empieza una cadena nueva.
	 */
	public static function open_index( $storage, array $plan, array $security, $target ) {
		$parent = (array) ( $plan['parent'] ?? array() );
		$index = (array) ( $parent['index'] ?? array() );
		$path = self::index_file( $storage, (string) ( $parent['backup_id'] ?? '' ), $index );
		if ( '' === $path ) throw new Exception( 'El índice del punto anterior de la cadena ya no está o cambió.' );
		return self::read_index( $path, $index, array( 'backup_id' => (string) $parent['backup_id'], 'manifest_sha256' => (string) ( $parent['manifest_sha256'] ?? '' ), 'chain' => (string) $plan['id'], 'sequence' => (int) ( $parent['sequence'] ?? -1 ) ), $security, $target );
	}
	/** Retira los índices laterales que ya no son de ninguna fila del historial (nunca otro archivo). */
	public static function sweep( $storage, array $history ) {
		$directory = rtrim( (string) $storage, '/\\' ) . '/' . self::DIRECTORY;
		if ( is_link( $directory ) || ! is_dir( $directory ) ) return 0;
		$keep = array();
		foreach ( $history as $row ) {
			$chain = self::row_chain( $row );
			if ( $chain && ! empty( $chain['index']['file'] ) ) $keep[ (string) $chain['index']['file'] ] = true;
		}
		$removed = 0;
		foreach ( (array) @scandir( $directory ) as $name ) {
			if ( ! preg_match( '/^\.?[0-9a-f-]{36}\.(?:jsonl|dgbk)(?:\.[0-9a-f]{8}\.part(?:\.dgbk)?)?$/D', (string) $name ) || isset( $keep[ $name ] ) ) continue;
			$path = $directory . '/' . $name;
			// Sólo con el cerrojo de operaciones (la retención de un trabajo): ningún otro trabajo publica a la vez. Uno publicado
			// sin fila es de un punto retirado; uno a medio escribir (.part, de más de una hora), de un trabajo que murió.
			if ( ( is_file( $path ) || is_link( $path ) ) && ( '.' !== $name[0] || time() - (int) @filemtime( $path ) > 3600 ) && @unlink( $path ) ) $removed++;
		}
		return $removed;
	}

	/** Retira el índice lateral de una fila (al borrarla a mano). */
	public static function remove_index( $storage, $row ) {
		$chain = self::row_chain( $row );
		$name = $chain ? (string) ( $chain['index']['file'] ?? '' ) : '';
		if ( '' === $name || self::index_name( $chain['backup_id'], ! empty( $chain['index']['encrypted'] ) ) !== $name ) return;
		$path = rtrim( (string) $storage, '/\\' ) . '/' . self::DIRECTORY . '/' . $name;
		if ( is_file( $path ) || is_link( $path ) ) @unlink( $path );
	}

	/* ------------------------------------------------------------------ «Conservar»: respaldos o cadenas */

	/**
	 * Segundos entre dos ejecuciones de cada frecuencia de una automatización (WP-Cron; «monthly» son 30 días); sin
	 * programación, 0. La estimación de assets/retention.js usa la misma tabla (tests/backups-retain-chains-ui.mjs).
	 */
	const PERIODS = array( 'hourly' => 3600, 'digitalisimo_every_6_hours' => 21600, 'digitalisimo_every_12_hours' => 43200, 'daily' => 86400, 'weekly' => 604800, 'monthly' => 2592000 );

	/**
	 * Qué cuenta «Conservar» de una automatización: `chains` (cadenas enteras) sólo en una incremental que lo eligió; si
	 * no, `points` (cada respaldo, como siempre). Una automatización anterior, sin `retain_unit`, cuenta respaldos.
	 */
	public static function retain_unit( $policy ) {
		$policy = is_array( $policy ) ? $policy : array();
		return 'incremental' === ( $policy['mode'] ?? '' ) && 'chains' === ( $policy['retain_unit'] ?? '' ) ? 'chains' : 'points';
	}
	/**
	 * Tope de «Conservar» con un completo cada `$full_every` incrementales, para que lo conservado y la cadena de la que
	 * depende quepan en las 100 filas del historial: N respaldos más una cadena (N + M ≤ 100) o N cadenas enteras
	 * (N × (M + 1) ≤ 100).
	 */
	public static function retain_cap( $unit, $full_every ) {
		$every = max( 1, min( self::MAX_FULL_EVERY, (int) $full_every ) );
		return 'chains' === $unit ? max( 1, intdiv( self::HISTORY_ROWS, $every + 1 ) ) : max( 1, self::HISTORY_ROWS - $every );
	}
	/**
	 * Respaldos de una cadena entera: su completo y `$full_every` incrementales, salvo que la frecuencia la corte antes por
	 * días (plan(): un completo nuevo cuando la base tiene `$full_days` días). Con un respaldo cada `$period` segundos caben
	 * ⌈D × 86 400 / período⌉; sin programación (0), sólo cuenta el número.
	 */
	public static function chain_length( $full_every, $full_days, $period ) {
		$length = (int) $full_every + 1;
		if ( (int) $period > 0 ) $length = min( $length, max( 1, intdiv( (int) $full_days * 86400 + (int) $period - 1, (int) $period ) ) );
		return $length;
	}
	/**
	 * Cuántos respaldos guarda una automatización (de su `retain`, `retain_unit`, `mode`, `full_every`, `full_days`,
	 * `schedule` y `destination`) una vez que su historial se llena, y cuántas cadenas: lo que dicen la pantalla al
	 * pintarse, REST y WP-CLI. assets/retention.js hace la misma cuenta al editar (las dos dan lo mismo,
	 * tests/backups-retain-chains-ui.mjs). Con L respaldos por cadena:
	 * - «Conservar N respaldos»: los N más recientes y la cadena de la que dependen: entre N y N + L − 1, en
	 *   1 + ⌊(N − 1) / L⌋ o 1 + ⌈(N − 1) / L⌉ cadenas;
	 * - «Conservar N cadenas»: la actual (aunque esté a medias) y las N − 1 anteriores enteras: entre (N − 1) × L + 1 y
	 *   N × L respaldos;
	 * - «Completo cada vez»: N completos (la unidad no aplica).
	 * Todas las cadenas menos la actual están enteras (la retención nunca rompe una), así que `previous_min` (las
	 * anteriores completas que siempre hay) es el mínimo de cadenas menos una; con 0, el texto lo advierte.
	 */
	public static function estimate( $policy ) {
		$policy = is_array( $policy ) ? $policy : array();
		$retain = isset( $policy['retain'] ) && is_numeric( $policy['retain'] ) ? (int) $policy['retain'] : 4;
		if ( 'incremental' !== ( $policy['mode'] ?? '' ) ) {
			$n = max( 1, min( self::HISTORY_ROWS, $retain ) );
			return array( 'unit' => 'points', 'retain' => $n, 'cap' => self::HISTORY_ROWS, 'min' => $n, 'max' => $n, 'chains_min' => $n, 'chains_max' => $n, 'previous_min' => $n - 1, 'chain_length' => 1, 'text' => 'Guardarás ' . $n . ( 1 === $n ? ' respaldo completo.' : ' respaldos completos.' ) );
		}
		$settings = self::settings( $policy );
		$unit = self::retain_unit( $policy );
		$period = self::PERIODS[ (string) ( $policy['schedule'] ?? '' ) ] ?? 0;
		$length = self::chain_length( $settings['full_every'], $settings['full_days'], $period );
		$cap = self::retain_cap( $unit, $settings['full_every'] );
		$n = max( 1, min( $cap, $retain ) );
		if ( 'chains' === $unit ) {
			$min = ( $n - 1 ) * $length + 1;
			$max = $n * $length;
			$low = $n;
			$high = $n;
		} else {
			$min = $n;
			$max = $n + $length - 1;
			$low = 1 + intdiv( $n - 1, $length );
			$high = 1 + intdiv( $n + $length - 2, $length );
		}
		$days = 'cada ' . $settings['full_days'] . ( 1 === $settings['full_days'] ? ' día' : ' días' );
		$text = 'Guardarás ' . ( $min === $max ? $min . ( 1 === $min ? ' respaldo' : ' respaldos' ) : 'entre ' . $min . ' y ' . $max . ' respaldos' ) . ' en ' . ( $low === $high ? $low . ( 1 === $low ? ' cadena' : ' cadenas' ) : $low . ' o ' . $high . ' cadenas' ) . '.';
		if ( $length < $settings['full_every'] + 1 ) $text .= 1 === $length ? ' Con esta frecuencia cada respaldo sale completo (un completo ' . $days . ').' : ' Con esta frecuencia la cadena se corta por días (un completo ' . $days . '): ' . $length . ' respaldos.';
		if ( $low > 1 ) {
			$text .= 2 === $low ? ' Siempre queda al menos una cadena anterior completa.' : ' Siempre quedan al menos ' . ( $low - 1 ) . ' cadenas anteriores completas.';
		} else {
			$when = 1 === $high ? ' Una sola cadena' : ( $period > 0 && $period < 86400 ? ' Hay horas' : ' Hay días' ) . ' con una sola cadena';
			$text .= $when . ': si su base se daña, ' . ( 'local' === (string) ( $policy['destination'] ?? 'local' ) ? 'no hay otra copia.' : 'sólo queda la copia remota.' );
		}
		if ( $retain > $cap ) $text .= ' «Conservar» quedará en ' . $cap . '.';
		return array( 'unit' => $unit, 'retain' => $n, 'cap' => $cap, 'min' => $min, 'max' => $max, 'chains_min' => $low, 'chains_max' => $high, 'previous_min' => $low - 1, 'chain_length' => $length, 'text' => $text );
	}
	/**
	 * Retención de una automatización por cadenas: de sus filas (de la más reciente a la más antigua, sin las bloqueadas
	 * ni las subidas), las que quedan fuera de sus `$keep` cadenas más recientes, enteras (nombre => true). La cadena en
	 * curso, aunque esté a medias, cuenta como una; un completo que no es de una cadena (uno anterior a ser incremental)
	 * es una cadena él solo.
	 */
	public static function outside_chains( array $rows, $keep ) {
		$keep = max( 1, (int) $keep );
		$order = array();
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || '' === (string) ( $row['file'] ?? '' ) ) continue;
			$chain = self::row_chain( $row );
			$key = $chain ? 'chain:' . $chain['id'] : 'file:' . $row['file'];
			if ( ! isset( $order[ $key ] ) ) $order[ $key ] = count( $order );
			if ( $order[ $key ] >= $keep ) $out[ (string) $row['file'] ] = true;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ Retención */

	/**
	 * Archivos que la retención no puede borrar: los eslabones de los que depende alguna fila que se queda (`$remaining`:
	 * nombre => true, las que esta pasada no retira, también las bloqueadas y las de otras automatizaciones). Cada fila
	 * nombra a todos sus antecesores, así que basta una pasada.
	 */
	public static function pinned( array $history, array $remaining ) {
		$pins = array();
		foreach ( $history as $row ) {
			if ( ! is_array( $row ) || ! isset( $remaining[ (string) ( $row['file'] ?? '' ) ] ) ) continue;
			foreach ( self::anchored_links( $row, $history ) as $file ) $pins[ $file ] = true;
		}
		return $pins;
	}
	/**
	 * Archivos de su cadena que una fila fija (retención y tope del historial) y de los que depende para «Borrar». Los de
	 * una fila hecha aquí, o subida con la firma de una clave de este sitio: todos. Los de un punto subido o agregado sin
	 * esa firma: su manifiesto no es confiable, así que sólo fija los eslabones que son exactamente el que nombra
	 * (exact_link_row(): `backup_id`, SHA-256 de su manifiesto y posición en la cadena, N1 y N2 de la verificación) y los
	 * que no tienen fila. Nombrar un respaldo por su nombre no basta para fijarlo ni bloquear su «Borrar» (M1).
	 */
	public static function anchored_links( array $row, array $history ) {
		$chain = self::row_chain( $row );
		if ( ! $chain ) return array();
		$files = array();
		foreach ( $chain['links'] as $link ) $files[] = (string) $link['file'];
		if ( ! self::untrusted_upload( $row ) ) return $files;
		$named = array();
		foreach ( $history as $other ) if ( is_array( $other ) ) $named[ (string) ( $other['file'] ?? '' ) ] = true;
		$out = array();
		foreach ( $chain['links'] as $link ) if ( ! isset( $named[ (string) $link['file'] ] ) || null !== self::exact_link_row( $history, $link ) ) $out[] = (string) $link['file'];
		return $out;
	}
	/**
	 * La fila del historial que es exactamente el eslabón `$link`, o null: la que link_row() reconoce por su propio bloque
	 * `chain` (`backup_id`, SHA-256 de su manifiesto y posición: la escribió este motor o la validación de la subida) o,
	 * para la base de una cadena subida o agregada (una fila sin bloque `chain`: un completo), la de su mismo `backup_id`
	 * y el SHA-256 de su manifiesto anotado al validarla (`upload.manifest_sha256`), en la posición 0. Esa identidad no
	 * se adivina sin tener ya el respaldo.
	 */
	public static function exact_link_row( array $history, array $link ) {
		$row = self::link_row( $history, $link );
		if ( $row ) return $row;
		foreach ( $history as $named ) {
			if ( ! is_array( $named ) || (string) ( $named['file'] ?? '' ) !== (string) $link['file'] ) continue;
			if ( ! in_array( (string) ( $named['origin'] ?? '' ), array( 'uploaded', 'added' ), true ) || self::row_chain( $named ) ) return null;
			$sha = (string) ( $named['upload']['manifest_sha256'] ?? '' );
			$base = 0 === (int) $link['sequence'] && 'incremental' !== (string) ( $named['backup_type'] ?? 'full' );
			return $base && '' !== $sha && hash_equals( $sha, (string) $link['manifest_sha256'] ) && (string) ( $named['backup_id'] ?? '' ) === (string) $link['backup_id'] ? $named : null;
		}
		return null;
	}
	/** Una fila subida o agregada sin la firma de una clave de este sitio (`upload.site_key`). */
	public static function untrusted_upload( $row ) {
		return is_array( $row ) && in_array( (string) ( $row['origin'] ?? '' ), array( 'uploaded', 'added' ), true ) && empty( $row['upload']['site_key'] );
	}
	/**
	 * Quita de `$retire` (nombre => true) lo que una fila que se queda necesita. Repite hasta estabilizarse: lo que deja
	 * de retirarse también se queda y protege a sus antecesores.
	 */
	public static function keep_dependencies( array $history, array $retire ) {
		for ( $round = 0; $round < 100; $round++ ) {
			$remaining = array();
			foreach ( $history as $row ) if ( is_array( $row ) && '' !== (string) ( $row['file'] ?? '' ) && ! isset( $retire[ (string) $row['file'] ] ) ) $remaining[ (string) $row['file'] ] = true;
			$pins = self::pinned( $history, $remaining );
			$kept = array_intersect_key( $retire, $pins );
			if ( ! $kept ) return $retire;
			$retire = array_diff_key( $retire, $kept );
		}
		return $retire;
	}
	/** Filas del historial que dependen de `$file` (incrementales posteriores de su cadena). */
	public static function dependents( array $history, $file ) {
		$out = array();
		foreach ( $history as $row ) if ( is_array( $row ) && in_array( (string) $file, self::anchored_links( $row, $history ), true ) ) $out[] = (string) $row['file'];
		return $out;
	}

	/* ------------------------------------------------------------------ Utilidades */

	public static function write_all( $handle, $bytes ) {
		$length = strlen( (string) $bytes );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $handle, 0 === $offset ? $bytes : substr( $bytes, $offset ) );
			if ( false === $written || 0 === $written ) throw new Exception( 'No se pudo escribir en el almacén privado; revisa su espacio libre.' );
			$offset += $written;
		}
	}
	/** Líneas JSON de un archivo desde `$offset` hasta `$limit` bytes: array( número desde 0, registro, posición, posición siguiente ). */
	public static function records( $path, $offset = 0, $limit = PHP_INT_MAX ) {
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo leer el inventario de la cadena.' );
		try {
			if ( $offset > 0 && 0 !== fseek( $handle, (int) $offset ) ) throw new Exception( 'No se pudo leer el inventario de la cadena.' );
			$read = (int) $offset;
			$number = 0;
			while ( $read < $limit && false !== ( $line = fgets( $handle ) ) ) {
				$at = $read;
				$read += strlen( $line );
				$record = json_decode( $line, true );
				if ( ! is_array( $record ) || "\n" !== substr( $line, -1 ) ) throw new Exception( 'El inventario de la cadena está dañado.' );
				yield array( $number++, $record, $at, $read );
			}
		} finally {
			fclose( $handle );
		}
	}
	/** Una línea del inventario de un punto: archivo con tamaño, SHA-256 y eslabón, o un borrado (`x`). */
	public static function valid_inventory_line( $record, $sequence ) {
		if ( ! is_array( $record ) || ! is_string( $record['n'] ?? null ) || 0 !== strpos( $record['n'], 'wordpress/' ) || strlen( $record['n'] ) <= 10 ) return false;
		if ( ! empty( $record['x'] ) ) return 1 === count( $record ) - 1;
		return is_int( $record['s'] ?? null ) && $record['s'] >= 0 && self::valid_sha( $record['h'] ?? null ) && is_int( $record['l'] ?? null ) && $record['l'] >= 0 && $record['l'] <= (int) $sequence;
	}
}

/**
 * Índice en disco del inventario del padre (memoria constante: un sitio con un millón de archivos no cabe en un array con
 * 64 MB). Tabla de dispersión con sondeo lineal: cada hueco son 20 bytes (8 del MD5 del nombre, 8 de la posición de su
 * línea + 1 y 4 del número de registro). Además, un byte por registro marca los archivos que siguen en el sitio: los que
 * quedan sin marcar son los borrados desde el padre. Se construye por tramos.
 */
final class Digitalisimo_Backups_Chain_Lookup {
	const SLOT = 20;
	private $lines;
	private $table;
	private $seen;
	private $slots;

	public static function slots_for( $count ) {
		$slots = 1;
		while ( $slots < 2 * max( 1, (int) $count ) ) $slots <<= 1;
		return $slots;
	}
	/** Prepara la tabla vacía y el mapa de vistos (los dos a cero). */
	public static function create( $table, $seen, $count ) {
		foreach ( array( $table => self::slots_for( $count ) * self::SLOT, $seen => max( 0, (int) $count ) ) as $path => $size ) {
			$handle = @fopen( $path, 'c+b' );
			if ( ! $handle || ! ftruncate( $handle, 0 ) || ! ftruncate( $handle, $size ) ) { if ( $handle ) fclose( $handle ); throw new Exception( 'No se pudo preparar el índice del punto anterior en el área de trabajo.' ); }
			fclose( $handle );
			if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
		}
	}
	public function __construct( $lines, $table, $seen, $count ) {
		$this->lines = @fopen( $lines, 'rb' );
		$this->table = @fopen( $table, 'r+b' );
		$this->seen = @fopen( $seen, 'r+b' );
		$this->slots = self::slots_for( $count );
		if ( ! $this->lines || ! $this->table || ! $this->seen ) { $this->close(); throw new Digitalisimo_Backups_Resume_Invalid( 'Falta el índice del punto anterior en el área de trabajo.' ); }
		clearstatcache();
		if ( (int) fstat( $this->table )['size'] !== $this->slots * self::SLOT || (int) fstat( $this->seen )['size'] !== max( 0, (int) $count ) ) { $this->close(); throw new Digitalisimo_Backups_Resume_Invalid( 'El índice del punto anterior no tiene su tamaño.' ); }
	}
	public function close() {
		foreach ( array( 'lines', 'table', 'seen' ) as $handle ) if ( is_resource( $this->$handle ) ) fclose( $this->$handle );
		$this->lines = $this->table = $this->seen = null;
	}
	private static function key( $name ) { return substr( md5( (string) $name, true ), 0, 8 ); }
	private function start( $key ) { return unpack( 'N', substr( $key, 0, 4 ) )[1] & ( $this->slots - 1 ); }
	/** Anota un registro; un nombre repetido es un índice dañado. Idempotente para el mismo registro (tramo repetido). */
	public function insert( $name, $offset, $number ) {
		$key = self::key( $name );
		$slot = $this->start( $key );
		for ( $probe = 0; $probe < $this->slots; $probe++ ) {
			fseek( $this->table, $slot * self::SLOT );
			$bytes = (string) fread( $this->table, self::SLOT );
			$stored = self::SLOT === strlen( $bytes ) ? unpack( 'J', substr( $bytes, 8, 8 ) )[1] : 0;
			if ( 0 === $stored ) {
				fseek( $this->table, $slot * self::SLOT );
				if ( self::SLOT !== fwrite( $this->table, $key . pack( 'J', (int) $offset + 1 ) . pack( 'N', (int) $number ) ) ) throw new Exception( 'No se pudo escribir el índice del punto anterior.' );
				return;
			}
			if ( substr( $bytes, 0, 8 ) === $key ) {
				if ( (int) $stored === (int) $offset + 1 ) return;
				$other = $this->line( (int) $stored - 1 );
				if ( is_array( $other ) && (string) $other['n'] === (string) $name ) throw new Exception( 'El índice del punto anterior repite el archivo ' . $name . '.' );
			}
			$slot = ( $slot + 1 ) & ( $this->slots - 1 );
		}
		throw new Exception( 'El índice del punto anterior está lleno.' );
	}
	private function line( $offset ) {
		if ( 0 !== fseek( $this->lines, (int) $offset ) ) return null;
		$line = fgets( $this->lines );
		$record = false === $line ? null : json_decode( $line, true );
		return is_array( $record ) && is_string( $record['n'] ?? null ) ? $record : null;
	}
	/** El registro del padre de `$name` (n, s, h, l y su número `r`), o null. */
	public function find( $name ) {
		$key = self::key( $name );
		$slot = $this->start( $key );
		for ( $probe = 0; $probe < $this->slots; $probe++ ) {
			fseek( $this->table, $slot * self::SLOT );
			$bytes = (string) fread( $this->table, self::SLOT );
			if ( self::SLOT !== strlen( $bytes ) ) return null;
			$stored = unpack( 'J', substr( $bytes, 8, 8 ) )[1];
			if ( 0 === $stored ) return null;
			if ( substr( $bytes, 0, 8 ) === $key ) {
				$record = $this->line( (int) $stored - 1 );
				if ( is_array( $record ) && (string) $record['n'] === (string) $name ) return $record + array( 'r' => unpack( 'N', substr( $bytes, 16, 4 ) )[1] );
			}
			$slot = ( $slot + 1 ) & ( $this->slots - 1 );
		}
		return null;
	}
	public function seen( $number ) {
		if ( 0 !== fseek( $this->seen, (int) $number ) || 1 !== fwrite( $this->seen, "\1" ) ) throw new Exception( 'No se pudo anotar un archivo en el índice del punto anterior.' );
	}
	/** Olvida todas las marcas (se vuelven a poner desde el inventario confirmado del punto). */
	public function reset_seen( $count ) {
		if ( ! ftruncate( $this->seen, 0 ) || ! ftruncate( $this->seen, max( 0, (int) $count ) ) ) throw new Exception( 'No se pudo preparar el recuento de borrados del punto.' );
	}
	public function was_seen( $number ) {
		if ( 0 !== fseek( $this->seen, (int) $number ) ) return false;
		return "\1" === fread( $this->seen, 1 );
	}
	public function sync() {
		foreach ( array( $this->table, $this->seen ) as $handle ) if ( is_resource( $handle ) ) { fflush( $handle ); if ( function_exists( 'fsync' ) ) @fsync( $handle ); }
	}
}
