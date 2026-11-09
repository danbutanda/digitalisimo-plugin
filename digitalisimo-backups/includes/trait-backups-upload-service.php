<?php
defined( 'ABSPATH' ) || exit;

/**
 * Casos de uso de la subida de un respaldo por fragmentos (B3), compartidos por la pantalla (AJAX) y REST. Las
 * sesiones viven en Digitalisimo_Backups_Uploads; aquí se decide quién sube, cuánto y si el archivo terminado puede
 * entrar en el almacén: sólo después de pasar toda la validación de archivos no confiables de «Seguridad» (la misma de
 * `verify` y de la restauración, sin las condiciones de esta instalación, que se informan en `problems`).
 *
 * Una sesión es de la cuenta que la empezó (WP-CLI aparte); en una red se sube desde el sitio principal o la
 * administración de red, porque el almacén es de toda la instalación.
 */
trait Digitalisimo_Backups_Upload_Service {
	/** Sesión de una subida para la cuenta en curso, o el error que corresponde. */
	private static function upload_session( $id ) {
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
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && (int) ( $state['user_id'] ?? 0 ) !== (int) get_current_user_id() ) return self::service_error( 'upload_owner', 'Esta subida la empezó otra cuenta.', 403 );
		return $state;
	}
	/**
	 * Empieza una subida: nombre (.zip o .dgbk), tamaño y, si el cliente lo conoce, su SHA-256. Comprueba el espacio
	 * libre del almacén (con lo que les falta a las otras subidas) y no deja más de tres subidas sin terminar.
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
			$sessions = Digitalisimo_Backups_Uploads::sessions();
			if ( count( $sessions ) >= Digitalisimo_Backups_Uploads::MAX_SESSIONS ) return self::service_error( 'upload_limit', 'Ya hay ' . count( $sessions ) . ' subidas sin terminar: termina o cancela alguna antes de empezar otra (sin actividad, caducan a las 24 h).', 409 );
			$pending = 0;
			foreach ( $sessions as $session ) $pending += max( 0, (int) $session['size'] - Digitalisimo_Backups_Uploads::received( $session['id'] ) );
			$free = Digitalisimo_Backups_Archive_Inspector::free_space( $storage );
			$needed = $size + $pending + Digitalisimo_Backups_Security::MARGIN;
			if ( null !== $free && $free < $needed ) return self::service_error( 'upload_space', 'No hay espacio en el almacén privado para este respaldo: se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed ) . ' libres y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '.', 507 );
			$now = time();
			$state = Digitalisimo_Backups_Uploads::create( array( 'id' => Digitalisimo_Backups_Job_Store::new_id(), 'name' => $name, 'size' => $size, 'sha256' => $sha256, 'chunk_size' => Digitalisimo_Backups_Uploads::chunk_size(), 'user_id' => (int) get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) );
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		return Digitalisimo_Backups_Uploads::view( $state );
	}
	public static function service_upload_status( $id ) {
		$state = self::upload_session( $id );
		return is_wp_error( $state ) ? $state : Digitalisimo_Backups_Uploads::view( $state );
	}
	/**
	 * Anexa un fragmento en `offset`, que debe ser exactamente lo ya recibido (si no, 409 con lo recibido: el cliente
	 * sigue desde ahí). `$source` es `file` (el temporal de un formulario multipart, sin cargarlo en memoria) o `data`
	 * (el cuerpo de una petición REST). Nunca pasa del tamaño declarado ni del tamaño de fragmento de la sesión.
	 */
	public static function service_upload_chunk( $id, $offset, array $source ) {
		$state = self::upload_session( $id );
		if ( is_wp_error( $state ) ) return $state;
		$offset = is_numeric( $offset ) ? (int) $offset : -1;
		$lock = Digitalisimo_Backups_Uploads::lock( $state['id'] );
		if ( ! $lock ) return self::service_error( 'upload_busy', 'Otro fragmento de esta subida se está escribiendo; espera y vuelve a intentarlo.', 409, array( 'received' => Digitalisimo_Backups_Uploads::received( $state['id'] ) ) );
		$input = null;
		try {
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
	 * confiables dentro de su sesión y sólo entonces entra en el almacén como «sin registro». Un archivo que no la supera
	 * se descarta. Un `.dgbk` cifrado con una clave que este sitio no tiene se acepta tras comprobar su cabecera: sin su
	 * frase no se puede descifrar, y la restauración lo comprueba entero antes de tocar nada (`secret` permite hacerlo
	 * ya; una frase equivocada no descarta la subida). La frase nunca se guarda ni vuelve en la respuesta.
	 */
	public static function service_upload_finish( $id, array $args = array() ) {
		$state = self::upload_session( $id );
		if ( is_wp_error( $state ) ) return $state;
		$lock = Digitalisimo_Backups_Uploads::lock( $state['id'] );
		if ( ! $lock ) return self::service_error( 'upload_busy', 'La subida todavía está escribiendo un fragmento; espera y vuelve a intentarlo.', 409, array( 'received' => Digitalisimo_Backups_Uploads::received( $state['id'] ) ) );
		// La sesión se borra después de soltar su cerrojo: nunca se borra un archivo abierto y bloqueado (Windows y php-wasm).
		$discard = false;
		try {
			$received = Digitalisimo_Backups_Uploads::received( $state['id'] );
			if ( $received !== (int) $state['size'] ) return self::service_error( 'upload_incomplete', 'Faltan bytes: llegaron ' . $received . ' de ' . (int) $state['size'] . '.', 409, array( 'received' => $received ) );
			$path = Digitalisimo_Backups_Uploads::data_path( $state['id'] );
			if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );
			if ( '' !== (string) $state['sha256'] ) {
				$checksum = @hash_file( 'sha256', $path );
				if ( false === $checksum || ! hash_equals( (string) $state['sha256'], $checksum ) ) {
					$discard = true;
					return self::service_error( 'upload_checksum', 'El archivo recibido no coincide con el SHA-256 declarado y se descartó. Vuelve a subirlo.', 422 );
				}
			}
			$secret = is_string( $args['secret'] ?? null ) ? $args['secret'] : '';
			$report = self::upload_inspect( $path, $secret );
			if ( is_wp_error( $report ) ) {
				$discard = 'digitalisimo_backups_secret_wrong' !== $report->get_error_code();
				return $report;
			}
			$storage = trailingslashit( self::local_directory() );
			$name = (string) $state['name'];
			// Nombre y rename() con el cerrojo corto del almacén: nunca pisa un respaldo ni toma el nombre de una fila del historial.
			$file = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $path, $storage, $name, $report ) {
				$file = self::upload_final_name( $name, $report['container'] );
				if ( ! @rename( $path, $storage . $file ) ) throw new Exception( 'No se pudo mover el respaldo subido al almacén privado.' );
				@chmod( $storage . $file, 0600 );
				return $file;
			} );
			$discard = true;
			return array( 'file' => $file, 'size' => (int) $state['size'], 'registered' => false ) + $report;
		} catch ( Exception $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		} finally {
			Digitalisimo_Backups_Uploads::unlock( $lock );
			if ( $discard ) Digitalisimo_Backups_Uploads::remove( $state['id'] );
		}
	}
	public static function service_upload_cancel( $id ) {
		$state = self::upload_session( $id );
		if ( is_wp_error( $state ) ) return $state;
		$lock = Digitalisimo_Backups_Uploads::lock( $state['id'] );
		if ( ! $lock ) return self::service_error( 'upload_busy', 'La subida todavía está escribiendo; vuelve a intentarlo.', 409 );
		// Con el cerrojo tomado no hay ningún fragmento a medias; se suelta antes de borrar la sesión.
		Digitalisimo_Backups_Uploads::unlock( $lock );
		Digitalisimo_Backups_Uploads::remove( $state['id'] );
		return array( 'id' => (string) $state['id'], 'cancelled' => true );
	}
	/**
	 * Validación de archivo no confiable del respaldo subido, en su sesión: la de `verify` (firma o ninguna confianza,
	 * descifrado completo de un `.dgbk`, entradas, CRC y SHA-256 de cada una contra el manifiesto v3 estricto y el SQL
	 * en seco). Devuelve el informe o el error que lo rechaza.
	 */
	private static function upload_inspect( $path, $secret ) {
		$summary = array( 'container' => 'zip', 'verified' => false, 'needs_secret' => false, 'trust' => 'untrusted', 'signature' => 'unsigned', 'key' => '', 'confirmation' => 'RESTAURAR SIN FIRMA', 'compatible' => false, 'problems' => array() );
		if ( Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) {
			try {
				$header = Digitalisimo_Backups_Encrypted_File::read_header( $path )['header'];
			} catch ( Exception $e ) {
				return self::service_error( 'upload_invalid', 'El archivo no es un respaldo cifrado válido y se descartó: ' . $e->getMessage(), 422 );
			}
			$key_id = (string) $header['key']['id'];
			$summary = array_merge( $summary, array( 'container' => 'dgbk', 'trust' => 'signed', 'signature' => 'signed', 'key' => Digitalisimo_Backups_Keys::short( $key_id ), 'confirmation' => 'RESTAURAR' ) );
			if ( ! Digitalisimo_Backups_Keys::can_open( $key_id ) ) {
				if ( '' === $secret ) return array_merge( $summary, array( 'needs_secret' => true, 'compatible' => true, 'problems' => array( 'Está cifrado con la clave ' . Digitalisimo_Backups_Keys::short( $key_id ) . ', que este sitio no tiene: se comprobará por completo al restaurarlo escribiendo su frase o su clave.' ) ) );
				$root = Digitalisimo_Backups_Sodium::available() ? Digitalisimo_Backups_Keys::resolve_with_secret( $header['key'], $secret ) : false;
				if ( false !== $root ) Digitalisimo_Backups_Sodium::memzero( $root );
				if ( false === $root ) return self::service_error( 'secret_wrong', 'La frase o clave escrita no corresponde a la clave ' . Digitalisimo_Backups_Keys::short( $key_id ) . ' de este respaldo. La subida se conserva: vuelve a terminarla con la frase correcta o sin frase.', 400 );
			}
		} elseif ( "PK\x03\x04" !== (string) @file_get_contents( $path, false, null, 0, 4 ) ) {
			return self::service_error( 'upload_invalid', 'El archivo no es un ZIP ni un respaldo cifrado de DIGITALÍSIMO y se descartó.', 422 );
		}
		if ( ! class_exists( 'ZipArchive' ) ) return self::service_error( 'zip', 'El servidor necesita la extensión PHP ZipArchive para comprobar el respaldo.', 500 );
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
			return self::service_error( 'upload_invalid', 'El archivo no supera la validación de archivos no confiables y se descartó: ' . $e->getMessage(), 422 );
		} finally {
			Digitalisimo_Backups_Security::close( $report );
		}
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
