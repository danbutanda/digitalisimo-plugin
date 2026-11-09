<?php
defined( 'ABSPATH' ) || exit;

/**
 * Casos de uso de la subida de un respaldo por fragmentos (B3), compartidos por la pantalla (AJAX), REST y WP-CLI. Las
 * sesiones viven en Digitalisimo_Backups_Uploads; aquí se decide quién sube, cuánto y si el archivo terminado puede
 * entrar en el almacén: sólo después de pasar toda la validación de archivos no confiables de «Seguridad» (la misma de
 * `verify` y de la restauración, sin las condiciones de esta instalación, que se informan en `problems`).
 *
 * Continuar o cerrar una subida es de la cuenta que la empezó (WP-CLI aparte); verla y descartarla, de cualquier
 * cuenta con la capability. En una red se sube desde el sitio principal o la administración de red, porque el almacén
 * es de toda la instalación.
 *
 * Al cerrar, sólo se descarta un archivo que no es un respaldo válido (formato, firma, contenido contra el manifiesto,
 * SQL, SHA-256 declarado). Lo que impide comprobarlo —falta de espacio para descifrarlo, una frase equivocada, un
 * error del servidor— conserva la subida con el motivo, para reintentar o descartarla a mano.
 */
trait Digitalisimo_Backups_Upload_Service {
	/**
	 * Sesión de una subida, o el error que corresponde. Con $owner, sólo la de la cuenta en curso (continuar y cerrar);
	 * sin él, cualquiera (verla y descartarla).
	 */
	private static function upload_session( $id, $owner = true ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$id = (string) $id;
		if ( ! Digitalisimo_Backups_Uploads::valid_id( $id ) ) return self::service_error( 'upload_invalid', 'El identificador de la subida no es válido.', 400 );
		try {
			$state = Digitalisimo_Backups_Uploads::read( $id );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		if ( ! $state || time() - (int) ( $state['updated_at'] ?? 0 ) > Digitalisimo_Backups_Uploads::EXPIRES_AFTER ) return self::service_error( 'upload_missing', 'La subida no existe o caducó (24 h sin actividad); vuelve a empezarla.', 404 );
		if ( $owner && ! ( defined( 'WP_CLI' ) && WP_CLI ) && (int) ( $state['user_id'] ?? 0 ) !== (int) get_current_user_id() ) return self::service_error( 'upload_owner', 'Esta subida la empezó otra cuenta: sólo esa cuenta puede continuarla (cualquiera puede descartarla).', 403 );
		return $state;
	}
	/**
	 * Empieza una subida: nombre (.zip o .dgbk), tamaño y, si el cliente lo conoce, su SHA-256. Con el cerrojo corto del
	 * almacén (dos inicios a la vez no superan los límites): tres subidas sin terminar por cuenta y diez en total, y
	 * espacio libre para lo que les falta a todas, más descifrarlas si son `.dgbk`.
	 */
	public static function service_upload_start( array $args ) {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$name = sanitize_file_name( (string) ( $args['name'] ?? '' ) );
		if ( '' === $name || strlen( $name ) > 200 || ! preg_match( '/\.(?:zip|dgbk)$/i', $name ) ) return self::service_error( 'upload_name', 'Elige un respaldo de DIGITALÍSIMO: un archivo .zip o .dgbk.', 400 );
		$size = is_numeric( $args['size'] ?? null ) ? (int) $args['size'] : 0;
		if ( $size <= 0 ) return self::service_error( 'upload_size', 'El tamaño del respaldo no es válido.', 400 );
		if ( $size > Digitalisimo_Backups_Uploads::MAX_SIZE ) return self::service_error( 'upload_size', 'El respaldo supera el tamaño máximo de una subida (64 GB).', 413 );
		$sha256 = strtolower( trim( (string) ( $args['sha256'] ?? '' ) ) );
		if ( '' !== $sha256 && ! preg_match( '/^[0-9a-f]{64}$/', $sha256 ) ) return self::service_error( 'upload_checksum', 'El SHA-256 declarado no es válido: son 64 caracteres hexadecimales.', 400 );
		try {
			$storage = self::local_directory();
			return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $storage, $name, $size, $sha256 ) {
				$open = array_values( array_filter( Digitalisimo_Backups_Uploads::sessions(), array( 'Digitalisimo_Backups_Uploads', 'open' ) ) );
				$user = (int) get_current_user_id();
				$mine = array_filter( $open, function( $state ) use ( $user ) { return (int) ( $state['user_id'] ?? 0 ) === $user; } );
				if ( count( $mine ) >= Digitalisimo_Backups_Uploads::MAX_SESSIONS ) return self::service_error( 'upload_limit', 'Ya tienes ' . count( $mine ) . ' subidas sin terminar: termina o descarta alguna en «Subir un respaldo» antes de empezar otra (sin actividad, caducan a las 24 h).', 409 );
				if ( count( $open ) >= Digitalisimo_Backups_Uploads::MAX_TOTAL ) return self::service_error( 'upload_limit', 'Hay ' . count( $open ) . ' subidas sin terminar en este sitio: descarta alguna en «Subir un respaldo» antes de empezar otra.', 409 );
				$pending = 0;
				foreach ( $open as $state ) $pending += Digitalisimo_Backups_Uploads::reserved( $state );
				$free = Digitalisimo_Backups_Archive_Inspector::free_space( $storage );
				$needed = $size + ( Digitalisimo_Backups_Uploads::encrypted_name( $name ) ? (int) ceil( $size * 1.05 ) : 0 ) + $pending + Digitalisimo_Backups_Security::MARGIN;
				if ( null !== $free && $free < $needed ) return self::service_error( 'upload_space', 'No hay espacio en el almacén privado para este respaldo' . ( Digitalisimo_Backups_Uploads::encrypted_name( $name ) ? ' (un .dgbk necesita además sitio para descifrarlo al comprobarlo)' : '' ) . ': se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed ) . ' libres y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '.', 507 );
				$now = time();
				$state = Digitalisimo_Backups_Uploads::create( array( 'id' => Digitalisimo_Backups_Job_Store::new_id(), 'name' => $name, 'size' => $size, 'sha256' => $sha256, 'chunk_size' => Digitalisimo_Backups_Uploads::chunk_size(), 'user_id' => $user, 'phase' => 'uploading', 'attempts' => 0, 'created_at' => $now, 'updated_at' => $now ) );
				return Digitalisimo_Backups_Uploads::view( $state );
			} );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
	}
	/** Estado de una subida (cualquier cuenta con la capability): fase, lo recibido y, si terminó, su resultado. */
	public static function service_upload_status( $id ) {
		$state = self::upload_session( $id, false );
		return is_wp_error( $state ) ? $state : Digitalisimo_Backups_Uploads::view( $state );
	}
	/** Subidas sin terminar y las cerradas de las últimas 24 h, de todas las cuentas (para verlas y descartarlas). */
	public static function service_upload_list() {
		$allowed = self::service_scope( false, false );
		if ( is_wp_error( $allowed ) ) return $allowed;
		try {
			return array_map( array( 'Digitalisimo_Backups_Uploads', 'view' ), Digitalisimo_Backups_Uploads::sessions() );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
	}
	/**
	 * Anexa un fragmento en `offset`, que debe ser exactamente lo ya recibido (si no, 409 con lo recibido: el cliente
	 * sigue desde ahí). `$source` es `file` (el temporal de un formulario multipart, sin cargarlo en memoria) o `data`
	 * (el cuerpo de una petición REST). Nunca pasa del tamaño declarado ni del tamaño de fragmento de la sesión, ni
	 * escribe en una subida que ya se está comprobando, cerrada o descartada.
	 */
	public static function service_upload_chunk( $id, $offset, array $source ) {
		$state = self::upload_session( $id );
		if ( is_wp_error( $state ) ) return $state;
		$offset = is_numeric( $offset ) ? (int) $offset : -1;
		$lock = Digitalisimo_Backups_Uploads::lock( $state['id'] );
		if ( ! $lock ) return self::service_error( 'upload_busy', 'Otro fragmento de esta subida se está escribiendo o se está comprobando; espera y vuelve a intentarlo.', 409, array( 'received' => Digitalisimo_Backups_Uploads::received( $state['id'] ) ) );
		$input = null;
		try {
			// Con el cerrojo: si entretanto se cerró o se descartó, no se escribe nada.
			$state = Digitalisimo_Backups_Uploads::read( $state['id'] );
			if ( ! $state || 'uploading' !== Digitalisimo_Backups_Uploads::phase( $state ) ) return self::service_error( 'upload_closed', 'Esta subida ya no admite fragmentos (terminó, se descartó o se está comprobando).', 409, array( 'received' => $state ? Digitalisimo_Backups_Uploads::received( $state['id'] ) : 0 ) );
			$received = Digitalisimo_Backups_Uploads::received( $state['id'] );
			if ( $offset !== $received ) return self::service_error( 'upload_offset', 'La subida va en el byte ' . $received . ': continúa desde ahí.', 409, array( 'received' => $received ) );
			if ( isset( $source['file'] ) && is_string( $source['file'] ) && '' !== $source['file'] ) {
				clearstatcache( true, $source['file'] );
				$length = is_file( $source['file'] ) && ! is_link( $source['file'] ) ? @filesize( $source['file'] ) : false;
				$input = false === $length ? false : @fopen( $source['file'], 'rb' );
			} elseif ( isset( $source['data'] ) && is_string( $source['data'] ) ) {
				$length = strlen( $source['data'] );
				$input = @fopen( 'php://temp', 'w+b' );
				if ( $input ) { fwrite( $input, $source['data'] ); rewind( $input ); }
			} else {
				$length = false;
				$input = false;
			}
			if ( ! $input || ! $length ) return self::service_error( 'upload_chunk', 'No llegó el fragmento del respaldo.', 400 );
			if ( $length > (int) $state['chunk_size'] ) return self::service_error( 'upload_chunk_size', 'El fragmento supera el tamaño de fragmento de esta subida (' . Digitalisimo_Backups_Archive_Inspector::megabytes( (int) $state['chunk_size'] ) . ').', 413 );
			if ( $received + $length > (int) $state['size'] ) return self::service_error( 'upload_overflow', 'El fragmento pasa del tamaño declarado del respaldo.', 400, array( 'received' => $received ) );
			$path = Digitalisimo_Backups_Uploads::data_path( $state['id'] );
			if ( is_link( $path ) || ! is_file( $path ) ) return self::service_error( 'upload_missing', 'El archivo de la subida ya no está en el almacén privado; vuelve a empezarla.', 404 );
			$output = @fopen( $path, 'ab' );
			$written = $output ? stream_copy_to_stream( $input, $output, $length ) : false;
			$flushed = $output && fflush( $output );
			if ( $output ) fclose( $output );
			if ( $written !== $length || ! $flushed ) {
				// Una escritura a medias (disco lleno) no deja bytes de más: el archivo vuelve a lo que tenía.
				$handle = @fopen( $path, 'r+b' );
				if ( $handle ) { ftruncate( $handle, $received ); fclose( $handle ); }
				return self::service_error( 'upload_write', 'No se pudo escribir el fragmento en el almacén privado; revisa el espacio libre y vuelve a intentarlo.', 500, array( 'received' => $received ) );
			}
			$state['updated_at'] = time();
			unset( $state['error'] );
			Digitalisimo_Backups_Uploads::write( $state );
			return Digitalisimo_Backups_Uploads::view( $state );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		} finally {
			if ( is_resource( $input ) ) fclose( $input );
			Digitalisimo_Backups_Uploads::unlock( $lock );
		}
	}
	/**
	 * Cierra la subida: con todos los bytes (y el SHA-256 declarado, si lo hubo) pasa la validación de archivos no
	 * confiables dentro de su sesión y sólo entonces entra en el almacén como «sin registro».
	 *
	 * Idempotente y a prueba de cortes: una subida ya cerrada devuelve su resultado (o su rechazo); mientras otro
	 * proceso la comprueba responde 202 `upload_validating` (el cliente consulta el estado); la comprobación sigue
	 * aunque el proxy corte la petición (ignore_user_abort). Si el proceso muere comprobándola tres veces (el tiempo
	 * límite del servidor), responde 504 y pide cerrarla con WP-CLI, que no tiene límite de tiempo; nunca obliga a subirla
	 * otra vez. Sólo se descarta un archivo que no es un respaldo válido; espacio, frase o un error del servidor la
	 * conservan con el motivo. Un `.dgbk` cifrado con una clave que este sitio no tiene se acepta tras comprobar su
	 * cabecera (se comprueba entero al restaurarlo con su frase, o ya, con `secret`). La frase nunca se guarda.
	 */
	public static function service_upload_finish( $id, array $args = array() ) {
		$state = self::upload_session( $id );
		if ( is_wp_error( $state ) ) return $state;
		$closed = self::upload_closed( $state );
		if ( $closed ) return $closed;
		$lock = Digitalisimo_Backups_Uploads::lock( $state['id'] );
		if ( ! $lock ) return self::upload_validating( $state );
		$discard = false;
		try {
			$state = Digitalisimo_Backups_Uploads::read( $state['id'] );
			if ( ! $state ) return self::service_error( 'upload_missing', 'La subida ya no existe; vuelve a empezarla.', 404 );
			$closed = self::upload_closed( $state );
			if ( $closed ) return $closed;
			$received = Digitalisimo_Backups_Uploads::received( $state['id'] );
			if ( $received !== (int) $state['size'] ) return self::service_error( 'upload_incomplete', 'Faltan bytes: llegaron ' . $received . ' de ' . (int) $state['size'] . '.', 409, array( 'received' => $received ) );
			$cli = defined( 'WP_CLI' ) && WP_CLI;
			if ( ! $cli && 'validating' === Digitalisimo_Backups_Uploads::phase( $state ) && (int) ( $state['attempts'] ?? 0 ) >= Digitalisimo_Backups_Uploads::MAX_ATTEMPTS ) {
				return self::service_error( 'upload_timeout', 'La comprobación de esta subida se cortó ' . (int) $state['attempts'] . ' veces (el tiempo límite del servidor o del proxy). Se conserva: termínala con WP-CLI (wp digitalisimo-backups upload finish ' . $state['id'] . '), que no tiene límite de tiempo, o descártala.', 504, array( 'received' => $received ) );
			}
			$state['phase'] = 'validating';
			$state['attempts'] = (int) ( $state['attempts'] ?? 0 ) + 1;
			$state['updated_at'] = time();
			unset( $state['error'] );
			Digitalisimo_Backups_Uploads::write( $state );
			// La comprobación sigue aunque el proxy corte la petición: el cliente consulta el estado y no vuelve a subirla.
			if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
			if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );
			$path = Digitalisimo_Backups_Uploads::data_path( $state['id'] );
			$report = null;
			if ( '' !== (string) $state['sha256'] ) {
				$checksum = @hash_file( 'sha256', $path );
				if ( false === $checksum ) $report = self::service_error( 'upload_error', 'No se pudo leer el archivo subido para calcular su SHA-256; se conserva para reintentar.', 500 );
				elseif ( ! hash_equals( (string) $state['sha256'], $checksum ) ) $report = self::service_error( 'upload_checksum', 'El archivo recibido no coincide con el SHA-256 declarado y se descartó. Vuelve a subirlo.', 422 );
			}
			if ( null === $report ) $report = self::upload_inspect( $path, is_string( $args['secret'] ?? null ) ? $args['secret'] : '' );
			if ( is_wp_error( $report ) ) {
				$code = $report->get_error_code();
				$discard = in_array( $code, array( 'digitalisimo_backups_upload_invalid', 'digitalisimo_backups_upload_checksum' ), true );
				$state['phase'] = $discard ? 'rejected' : 'uploading';
				// Un rechazo por espacio, frase o el servidor no es un corte: no gasta intentos.
				if ( ! $discard ) $state['attempts'] = 0;
				$state['error'] = array( 'code' => $code, 'message' => $report->get_error_message() );
				$state['updated_at'] = time();
				Digitalisimo_Backups_Uploads::write( $state );
				if ( $discard ) @unlink( $path );
				return $report;
			}
			$storage = trailingslashit( self::local_directory() );
			$name = (string) $state['name'];
			// Nombre y rename() con el cerrojo corto del almacén: nunca pisa un respaldo ni toma el nombre de una fila del historial.
			$file = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $path, $storage, $name, $report ) {
				$file = self::upload_final_name( $name, $report['container'] );
				if ( ! @rename( $path, $storage . $file ) ) throw new Exception( 'No se pudo mover el respaldo subido al almacén privado.' );
				self::private_file( $storage . $file );
				return $file;
			} );
			$result = array( 'file' => $file, 'size' => (int) $state['size'], 'registered' => false ) + $report;
			$state['phase'] = 'done';
			$state['result'] = $result;
			$state['updated_at'] = time();
			Digitalisimo_Backups_Uploads::write( $state );
			return $result;
		} catch ( Throwable $e ) {
			// El servidor no pudo terminar (almacén, permisos): la subida se conserva para reintentar.
			try {
				if ( is_array( $state ) && Digitalisimo_Backups_Uploads::read( $state['id'] ) ) {
					$state['phase'] = 'uploading';
					$state['attempts'] = 0;
					$state['error'] = array( 'code' => 'digitalisimo_backups_upload_error', 'message' => $e->getMessage() );
					$state['updated_at'] = time();
					Digitalisimo_Backups_Uploads::write( $state );
				}
			} catch ( Throwable $ignored ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			return self::service_error( 'upload_error', 'No se pudo terminar la subida; se conserva para reintentar o descartarla: ' . $e->getMessage(), 500 );
		} finally {
			Digitalisimo_Backups_Uploads::unlock( $lock );
		}
	}
	/** Una subida ya cerrada responde siempre lo mismo: su resultado o su rechazo. */
	private static function upload_closed( array $state ) {
		$phase = Digitalisimo_Backups_Uploads::phase( $state );
		if ( 'done' === $phase && is_array( $state['result'] ?? null ) ) return $state['result'];
		if ( 'rejected' === $phase ) return self::service_error( 'upload_invalid', (string) ( $state['error']['message'] ?? 'El archivo no era un respaldo válido y se descartó.' ), 422 );
		return null;
	}
	/** Otro proceso la está comprobando: 202 con la vista; el cliente consulta el estado hasta que termine. */
	private static function upload_validating( array $state ) {
		return self::service_error( 'upload_validating', 'La subida se está comprobando; consulta su estado en unos segundos.', 202, array( 'received' => Digitalisimo_Backups_Uploads::received( $state['id'] ), 'upload' => Digitalisimo_Backups_Uploads::view( $state ) ) );
	}
	/** Descarta una subida sin cerrar, de cualquier cuenta con la capability, y borra lo recibido. */
	public static function service_upload_cancel( $id ) {
		$state = self::upload_session( $id, false );
		if ( is_wp_error( $state ) ) return $state;
		$lock = Digitalisimo_Backups_Uploads::lock( $state['id'] );
		if ( ! $lock ) return self::service_error( 'upload_busy', 'La subida se está escribiendo o comprobando; vuelve a intentarlo en unos segundos.', 409 );
		// Con el cerrojo se marca descartada (un fragmento que llegue después no escribe); se suelta antes de borrar.
		try {
			$state['phase'] = 'cancelled';
			$state['updated_at'] = time();
			Digitalisimo_Backups_Uploads::write( $state );
		} catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		Digitalisimo_Backups_Uploads::unlock( $lock );
		Digitalisimo_Backups_Uploads::remove( $state['id'] );
		return array( 'id' => (string) $state['id'], 'cancelled' => true );
	}
	/**
	 * Validación de archivo no confiable del respaldo subido, en su sesión: la de `verify` (firma o ninguna confianza,
	 * descifrado completo de un `.dgbk`, entradas, CRC y SHA-256 de cada una contra el manifiesto v3 estricto y el SQL
	 * en seco). Devuelve el informe o el error: `upload_invalid` (no es un respaldo válido: se descarta),
	 * `secret_wrong`, `upload_space` o `upload_error` (no se pudo comprobar: se conserva).
	 */
	private static function upload_inspect( $path, $secret ) {
		$summary = array( 'container' => 'zip', 'verified' => false, 'needs_secret' => false, 'trust' => 'untrusted', 'signature' => 'unsigned', 'key' => '', 'confirmation' => 'RESTAURAR SIN FIRMA', 'compatible' => false, 'problems' => array() );
		if ( ! class_exists( 'ZipArchive' ) ) return self::service_error( 'upload_error', 'El servidor necesita la extensión PHP ZipArchive para comprobar el respaldo; la subida se conserva.', 500 );
		if ( Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) {
			try {
				$parsed = Digitalisimo_Backups_Encrypted_File::read_header( $path );
				$header = $parsed['header'];
			} catch ( Exception $e ) {
				return self::service_error( 'upload_invalid', 'El archivo no es un respaldo cifrado válido y se descartó: ' . $e->getMessage(), 422 );
			}
			$key_id = (string) $header['key']['id'];
			$summary = array_merge( $summary, array( 'container' => 'dgbk', 'trust' => 'signed', 'signature' => 'signed', 'key' => Digitalisimo_Backups_Keys::short( $key_id ), 'confirmation' => 'RESTAURAR' ) );
			if ( ! Digitalisimo_Backups_Keys::can_open( $key_id ) ) {
				if ( '' === $secret ) return array_merge( $summary, array( 'needs_secret' => true, 'compatible' => true, 'problems' => array( 'Está cifrado con la clave ' . Digitalisimo_Backups_Keys::short( $key_id ) . ', que este sitio no tiene: se comprobará por completo al restaurarlo escribiendo su frase o su clave.' ) ) );
				$root = Digitalisimo_Backups_Sodium::available() ? Digitalisimo_Backups_Keys::resolve_with_secret( $header['key'], $secret ) : false;
				if ( false !== $root ) Digitalisimo_Backups_Sodium::memzero( $root );
				if ( false === $root ) return self::upload_wrong_secret( $key_id );
			}
			// Descifrarlo para comprobarlo necesita sitio además del archivo subido: si no lo hay, se conserva.
			$free = Digitalisimo_Backups_Archive_Inspector::free_space( dirname( $path ) );
			$needed = (int) ceil( (int) $header['inner_size'] * 1.05 ) + Digitalisimo_Backups_Security::MARGIN;
			if ( null !== $free && $free < $needed ) return self::service_error( 'upload_space', 'No hay espacio para descifrar el respaldo y comprobarlo: se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed ) . ' libres en el almacén y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '. La subida se conserva: libera espacio y vuelve a terminarla, o descártala.', 507 );
		} elseif ( "PK\x03\x04" !== (string) @file_get_contents( $path, false, null, 0, 4 ) ) {
			return self::service_error( 'upload_invalid', 'El archivo no es un ZIP ni un respaldo cifrado de DIGITALÍSIMO y se descartó.', 422 );
		} elseif ( '' !== $secret ) {
			// Un ZIP firmado con una clave que este sitio no tiene: una frase equivocada no es un archivo inválido.
			$zip = new ZipArchive();
			if ( true === $zip->open( $path ) ) {
				try {
					$auth = Digitalisimo_Backups_Manifest_Auth::verify( $zip, $secret );
				} catch ( Throwable $e ) {
					$auth = array();
				}
				@$zip->close();
				if ( 'wrong_key' === ( $auth['state'] ?? '' ) ) return self::upload_wrong_secret( (string) ( $auth['key']['id'] ?? '' ) );
			}
		}
		$report = null;
		try {
			$report = Digitalisimo_Backups_Security::open( $path, array(), $secret );
			$source = array();
			$data = self::inspect_package( $report['zip'], 'untrusted' !== $report['trust'], false, $source );
			$problems = self::package_installation_problems( $data );
			// «Origen → este sitio», como `verify`: qué cambiaría al restaurarlo aquí (o por qué no se puede).
			$relocation = null;
			if ( ! $problems ) {
				$plan = self::relocation_plan( $data, $source, array( 'addresses' => 'current' ) );
				$problems = $plan->problems();
				$relocation = $plan->summary();
			}
			if ( 'untrusted' === $report['trust'] && Digitalisimo_Backups_Security::settings()['require_signed'] ) $problems[] = 'Este sitio sólo restaura respaldos firmados (Backups → Seguridad) y este archivo no tiene una firma válida de una clave de este sitio.';
			return array_merge( $summary, array(
				'container' => (string) $report['container'],
				'verified' => true,
				'trust' => (string) $report['trust'],
				'signature' => (string) $report['auth'],
				'key' => isset( $report['key']['id'] ) ? Digitalisimo_Backups_Keys::short( $report['key']['id'] ) : '',
				'confirmation' => 'untrusted' === $report['trust'] ? 'RESTAURAR SIN FIRMA' : 'RESTAURAR',
				'compatible' => ! $problems,
				'problems' => $problems,
				'entries' => (int) $report['zip']->numFiles,
				'format' => (string) ( $data['format'] ?? '' ),
				'scope' => (string) ( $data['scope'] ?? '' ),
				'multisite' => ! empty( $data['multisite'] ),
				'created_at' => (string) ( $data['created_at'] ?? '' ),
				'plugin_version' => (string) ( $data['plugin_version'] ?? '' ),
				'relocation' => $relocation,
				'wp_config' => self::config_report( $report['zip'] ),
			) );
		} catch ( Exception $e ) {
			return self::upload_failure( $e->getMessage() );
		} catch ( Throwable $e ) {
			return self::service_error( 'upload_error', 'No se pudo comprobar el respaldo por un error del servidor; la subida se conserva para reintentar: ' . $e->getMessage(), 500 );
		} finally {
			Digitalisimo_Backups_Security::close( $report );
		}
	}
	private static function upload_wrong_secret( $key_id ) {
		return self::service_error( 'secret_wrong', 'La frase o clave escrita no corresponde a la clave ' . Digitalisimo_Backups_Keys::short( $key_id ) . ' de este respaldo. La subida se conserva: vuelve a terminarla con la frase correcta o sin frase.', 400 );
	}
	/**
	 * Qué significa un fallo de la comprobación: lo que dice del servidor (espacio, libsodium, la carpeta de trabajo)
	 * conserva la subida; lo demás es que el archivo no es un respaldo válido, y se descarta. Una frase equivocada no
	 * llega aquí: upload_inspect() la reconoce antes por la cabecera del .dgbk o por la firma del manifiesto.
	 */
	private static function upload_failure( $message ) {
		if ( false !== strpos( $message, 'No hay espacio' ) ) return self::service_error( 'upload_space', $message . ' La subida se conserva: libera espacio y vuelve a terminarla, o descártala.', 507 );
		if ( false !== strpos( $message, 'este servidor no tiene' ) || false !== strpos( $message, 'No se pudo crear la carpeta de trabajo' ) || false !== strpos( $message, 'ZipArchive' ) ) return self::service_error( 'upload_error', 'No se pudo comprobar el respaldo en este servidor; la subida se conserva para reintentar: ' . $message, 500 );
		return self::service_error( 'upload_invalid', 'El archivo no supera la validación de archivos no confiables y se descartó: ' . $message, 422 );
	}
	/**
	 * Nombre en el almacén: el original si ya es el de un respaldo de DIGITALÍSIMO (con la extensión de su contenido) y
	 * no lo usa ningún archivo ni ninguna fila del historial; si no, `digitalisimo-upload-<fecha>-<aleatorio>`. Así
	 * siempre aparece en «Restaurar» como «sin registro» y nunca hereda la confianza de una fila.
	 */
	private static function upload_final_name( $name, $container ) {
		$storage = trailingslashit( self::local_directory() );
		$extension = 'dgbk' === $container ? '.dgbk' : '.zip';
		$stem = (string) preg_replace( '/\.(?:zip|dgbk)$/i', '', sanitize_file_name( (string) $name ) );
		$known = array_flip( array_map( 'strval', array_column( (array) self::fresh_history(), 'file' ) ) );
		$free = function( $candidate ) use ( $storage, $known ) { return ! file_exists( $storage . $candidate ) && ! is_link( $storage . $candidate ) && ! isset( $known[ $candidate ] ); };
		$listed = preg_match( '/^digitalisimo-[A-Za-z0-9._-]+$/', $stem ) || ( '.zip' === $extension && preg_match( '/^safety-before-restore-[A-Za-z0-9._-]+$/', $stem ) );
		if ( $listed && $free( $stem . $extension ) ) return $stem . $extension;
		do {
			$candidate = 'digitalisimo-upload-' . gmdate( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 4 ) ) . $extension;
		} while ( ! $free( $candidate ) );
		return $candidate;
	}
}
