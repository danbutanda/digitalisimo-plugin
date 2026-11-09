<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fases de un respaldo completo y su punto de control. Cada fase es un paso: si el proceso muere, el trabajo vuelve
 * al punto de control de la fase y nunca repite lo que ya confirmó (una base de datos exportada no se exporta otra
 * vez si el empaquetado se interrumpe; un ZIP verificado no se reconstruye si falla la transferencia).
 *
 * Todo lo intermedio vive en `jobs/<id>/staging/`, que no coincide con los nombres de respaldo, no figura en el
 * historial y no se puede descargar. El nombre final sólo nace de un `rename()` después de verificar el ZIP. Con la
 * clave que el trabajo fijó al encolarse, `pack` firma el manifiesto, `verify` comprueba la firma y, si el trabajo
 * cifra, termina sellando el ZIP verificado en un DGBK que también se descifra completo antes de publicarse.
 *
 * Desde T2, «database», «pack» y «verify» avanzan por tramos dentro de una misma fase: cada tramo devuelve la misma
 * fase con su punto de control (`resume`) y, tras una muerte, el siguiente sigue desde ahí sin repetir lo guardado.
 */
final class Digitalisimo_Backups_Backup_Job {
	public static function run_phase( $phase, array $job ) {
		switch ( $phase ) {
			case 'preflight': return self::preflight( $job );
			case 'database': return self::database( $job );
			case 'pack': return self::pack( $job );
			case 'verify': return self::verify( $job );
			case 'publish': return self::publish( $job );
			case 'transfer': return self::transfer( $job );
			case 'prune': return self::prune( $job );
		}
		throw new Digitalisimo_Backups_Permanent_Error( 'El trabajo está en una fase desconocida.' );
	}

	/** Requisitos: un fallo aquí no se arregla repitiendo. */
	private static function preflight( array $job ) {
		$spec = $job['spec'];
		if ( Digitalisimo_Backups_Job_Store::JOB_FORMAT !== (int) ( $spec['engine']['job_format'] ?? 0 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El trabajo se creó con otra versión del motor de respaldos; vuelve a iniciarlo.' );
		try {
			Digitalisimo_Backups::job_preflight( $spec );
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			throw $e;
		} catch ( Exception $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( $e->getMessage() );
		}
		Digitalisimo_Backups_Jobs::progress( 2, 'Requisitos comprobados.', true );
		return array( 'next' => 'database', 'checkpoint' => array( 'at' => time() ), 'message' => 'Exportando la base de datos…' );
	}

	/**
	 * Por tramos (T2): cada tramo exporta tablas enteras dentro de su instantánea hasta su plazo y guarda dónde empieza
	 * la siguiente; tras una muerte, la tabla a medias se repite entera. Ver Digitalisimo_Backups_Sql_Export.
	 */
	private static function database( array $job ) {
		// «Extraer como WordPress individual» (1.0.63) sigue en un paso: su exportador elige las tablas del sitio y deja el
		// informe que lee «pack» (ver Digitalisimo_Backups_Site_Extract).
		if ( Digitalisimo_Backups_Site_Extract::is_extract( (array) ( $job['spec'] ?? array() ) ) ) return self::database_in_one_step( $job );
		return Digitalisimo_Backups_Sql_Export::phase( $job );
	}
	/** La exportación en un solo paso (la de 1.0.63), para la extracción de un sitio de la red. */
	private static function database_in_one_step( array $job ) {
		$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		self::remove_matching( $staging, '/^database\./' );
		$tag = Digitalisimo_Backups_Jobs::current_owner_tag();
		$part = $staging . '/database.' . $tag . '.sql.part';
		$final = $staging . '/database.' . $tag . '.sql';
		Digitalisimo_Backups_Jobs::progress( 3, 'Exportando la base de datos…', true );
		try {
			$export = Digitalisimo_Backups::job_export_database( $part );
			if ( ! @rename( $part, $final ) ) throw new Exception( 'No se pudo cerrar el SQL exportado en el área de trabajo.' );
		} catch ( Throwable $e ) {
			@unlink( $part );
			throw $e;
		}
		$size = @filesize( $final );
		$hash = @hash_file( 'sha256', $final );
		if ( false === $size || false === $hash ) throw new Exception( 'No se pudo verificar el SQL exportado.' );
		return array(
			'next' => 'pack',
			'checkpoint' => array( 'file' => basename( $final ), 'size' => (int) $size, 'sha256' => $hash, 'tables' => $export['tables'], 'mode' => $export['mode'], 'at' => $export['at'], 'fp' => self::fingerprint( $final ) ),
			'message' => 'Base de datos exportada; empaquetando archivos…',
		);
	}

	/**
	 * Inventario por carpetas, SHA-256 y ZIP de los archivos con el SQL ya exportado, por tramos y dentro del área de
	 * trabajo (T2). Ver Digitalisimo_Backups_Packer.
	 */
	private static function pack( array $job ) {
		$database = $job['checkpoint']['database'] ?? null;
		$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		$sql = is_array( $database ) ? $staging . '/' . basename( (string) ( $database['file'] ?? '' ) ) : '';
		if ( ! $sql || ! is_file( $sql ) || ! self::same_fingerprint( self::fingerprint( $sql ), $database['fp'] ?? array() ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El SQL exportado ya no está íntegro en el área de trabajo; se exportará otra vez.', 'database' );
		if ( Digitalisimo_Backups_Site_Extract::is_extract( (array) ( $job['spec'] ?? array() ) ) ) return self::pack_in_one_step( $job, $staging, $sql, $database );
		return Digitalisimo_Backups_Packer::pack( $job, $staging, $sql, $database );
	}
	/** El empaquetado en un solo paso (el de 1.0.63), para la extracción de un sitio de la red; «verify» lo comprueba entero. */
	private static function pack_in_one_step( array $job, $staging, $sql, array $database ) {
		self::remove_matching( $staging, '/^package\./' );
		$target = $staging . '/package.' . Digitalisimo_Backups_Jobs::current_owner_tag() . '.zip';
		try {
			$build = Digitalisimo_Backups::job_build_package( $target, $sql, $database, array( 'job_id' => $job['id'], 'spec' => $job['spec'] ) );
		} catch ( Throwable $e ) {
			self::remove_matching( $staging, '/^package\./' );
			throw $e;
		}
		return array(
			'next' => 'verify',
			'checkpoint' => array( 'file' => basename( $target ), 'fp' => self::fingerprint( $target ), 'file_count' => (int) $build['file_count'], 'files_started_at' => $build['files_started_at'], 'files_completed_at' => $build['files_completed_at'], 'backup_id' => (string) ( $build['backup_id'] ?? '' ), 'created_at' => (string) ( $build['created_at'] ?? '' ), 'signed' => ! empty( $build['signed'] ) ),
			'message' => 'ZIP escrito; verificando cada entrada…',
		);
	}

	/**
	 * Relee cada entrada contra el manifiesto y comprueba la firma. Si falla, el ZIP se rehace (un archivo pudo cambiar
	 * mientras se leía). Un trabajo cifrado sella después el ZIP verificado: si el sellado falla, se repite esta fase.
	 * La lectura de las entradas va por tramos (T2): hasta comprobar la última, la fase devuelve su punto de control.
	 */
	private static function verify( array $job ) {
		$pack = $job['checkpoint']['pack'] ?? null;
		$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		$path = is_array( $pack ) ? $staging . '/' . basename( (string) ( $pack['file'] ?? '' ) ) : '';
		if ( ! $path || ! is_file( $path ) || ! self::same_fingerprint( self::fingerprint( $path ), $pack['fp'] ?? array() ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El ZIP del área de trabajo cambió o desapareció antes de verificarse; se empaquetará otra vez.', 'pack' );
		try {
			$more = Digitalisimo_Backups_Packer::verify_slice( $job, $path, $pack );
			if ( $more ) return $more;
		} catch ( Digitalisimo_Backups_Job_Cancelled $e ) {
			throw $e;
		} catch ( Digitalisimo_Backups_Job_Lease_Lost $e ) {
			throw $e;
		} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
			throw $e;
		} catch ( Exception $e ) {
			throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La verificación del ZIP falló: ' . $e->getMessage(), 'pack' );
		}
		$fingerprint = self::fingerprint( $path );
		if ( ! self::same_fingerprint( $fingerprint, $pack['fp'] ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El ZIP cambió mientras se verificaba; se empaquetará otra vez.', 'pack' );
		$notices = Digitalisimo_Backups_Packer::take_notices(); // T2: lo que la verificación no pudo probar, a la vista.
		if ( ! Digitalisimo_Backups::job_encrypts( $job['spec'] ) ) return array( 'next' => 'publish', 'checkpoint' => array( 'fp' => $fingerprint, 'at' => time() ), 'message' => 'ZIP verificado; registrándolo…', 'warnings' => $notices );
		$sealed = $staging . '/' . preg_replace( '/\.zip$/', '', basename( $path ) ) . '.dgbk';
		try {
			Digitalisimo_Backups::job_seal_package( $path, $sealed, $job['spec'], array( 'backup_id' => (string) ( $pack['backup_id'] ?? '' ), 'created_at' => (string) ( $pack['created_at'] ?? '' ) ) );
		} catch ( Throwable $e ) {
			@unlink( $sealed );
			throw $e;
		}
		if ( ! self::same_fingerprint( self::fingerprint( $path ), $pack['fp'] ) ) {
			@unlink( $sealed );
			throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El ZIP cambió mientras se cifraba; se empaquetará otra vez.', 'pack' );
		}
		return array( 'next' => 'publish', 'checkpoint' => array( 'file' => basename( $sealed ), 'fp' => self::fingerprint( $sealed ), 'zip_fp' => $fingerprint, 'at' => time() ), 'message' => 'Respaldo verificado y cifrado; registrándolo…', 'warnings' => $notices );
	}

	/**
	 * Idempotente: si el ZIP verificado ya se renombró y el proceso murió antes de registrarlo, comprueba que el final
	 * es el mismo archivo verificado y lo registra una sola vez.
	 */
	private static function publish( array $job ) {
		$spec = $job['spec'];
		$pack = (array) ( $job['checkpoint']['pack'] ?? array() );
		$verified = (array) ( $job['checkpoint']['verify']['fp'] ?? array() );
		if ( ! $verified ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'Falta la verificación del ZIP; se repetirá.', 'verify' );
		$final = rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . basename( (string) $spec['file'] );
		// Un trabajo cifrado publica el DGBK que selló la verificación; el ZIP en claro nunca sale del área de trabajo.
		$encrypted = Digitalisimo_Backups::job_encrypts( $spec );
		$staged_name = $encrypted ? (string) ( $job['checkpoint']['verify']['file'] ?? '' ) : (string) ( $pack['file'] ?? '' );
		if ( $encrypted && '.dgbk' !== substr( $staged_name, -5 ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'Falta el respaldo cifrado de la verificación; se repetirá.', 'verify' );
		$staged = Digitalisimo_Backups_Job_Store::directory( $job['id'] ) . '/' . Digitalisimo_Backups_Job_Store::STAGING . '/' . basename( $staged_name );
		$has_staged = '' !== $staged_name && is_file( $staged );
		$has_final = is_file( $final );
		if ( $has_staged && $has_final ) throw new Digitalisimo_Backups_Permanent_Error( 'Ya existe un archivo con el nombre final del respaldo; no se sobrescribe.' );
		if ( $has_staged ) {
			if ( ! self::same_fingerprint( self::fingerprint( $staged ), $verified ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El ZIP cambió después de verificarse; se empaquetará otra vez.', 'pack' );
			if ( ! @rename( $staged, $final ) ) throw new Exception( 'No se pudo publicar el ZIP verificado en el almacén privado.' );
		} elseif ( ! $has_final ) {
			throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El ZIP verificado ya no está en el área de trabajo; se empaquetará otra vez.', 'pack' );
		}
		if ( ! self::same_fingerprint( self::fingerprint( $final ), $verified ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El archivo con el nombre final no es el ZIP que se verificó; revisa el almacén privado.' );
		// El respaldo lleva wp-config.php y la base de datos: 0600 aunque venga de un área de trabajo anterior.
		Digitalisimo_Backups::private_file( $final );
		// También las listas del inventario (T2): llevan los nombres de los archivos en claro.
		if ( $encrypted ) self::remove_matching( Digitalisimo_Backups_Job_Store::staging( $job['id'] ), '/^package\./' );
		if ( ! empty( $spec['inline'] ) ) return array( 'next' => null, 'checkpoint' => array( 'renamed' => true ), 'result' => array( 'file' => basename( $final ), 'size' => (int) @filesize( $final ) ), 'message' => 'Copia previa creada y verificada.' );
		$row = Digitalisimo_Backups::job_register( $final, $spec, $job['id'] );
		Digitalisimo_Backups::job_record_notices( basename( $final ), $job ); // T2: avisos visibles y coherencia de la base.
		$remote = 'local' !== $spec['destination'];
		return array(
			'next' => $remote ? 'transfer' : 'prune',
			'checkpoint' => array( 'renamed' => true, 'sha256' => (string) ( $row['sha256'] ?? '' ) ),
			'result' => array( 'file' => basename( $final ), 'size' => (int) ( $row['size'] ?? 0 ), 'sha256' => (string) ( $row['sha256'] ?? '' ) ),
			'message' => $remote ? 'Respaldo verificado y registrado; copiándolo a ' . Digitalisimo_Backups::destination_name( $spec['destination'] ) . '…' : 'Respaldo verificado y registrado; aplicando la retención…',
		);
	}

	/**
	 * Copia al destino remoto y lo verifica allí; el registro local verificado se conserva pase lo que pase. La fila
	 * sigue siendo la del ZIP local (`destination` = local) y la copia remota se anota aparte: un respaldo copiado sigue
	 * restaurándose desde el almacén mientras su ZIP exista.
	 */
	private static function transfer( array $job ) {
		$spec = $job['spec'];
		$final = rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . basename( (string) $spec['file'] );
		if ( ! is_file( $final ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El ZIP local desapareció antes de copiarlo al destino remoto.' );
		Digitalisimo_Backups_Jobs::progress( 90, 'Copiando a ' . Digitalisimo_Backups::destination_name( $spec['destination'] ) . '…', true );
		// El estado reanudable de la subida (multipart, sesión, temporal) vive en el área de este trabajo.
		$remote = Digitalisimo_Backups::job_transfer( $final, $spec['destination'], $job['id'] );
		Digitalisimo_Backups::history_update( basename( $final ), array( 'transfer_destination' => $spec['destination'], 'remote' => (array) $remote, 'transfer' => 'ok', 'transfer_error' => '' ) );
		return array( 'next' => 'prune', 'checkpoint' => array( 'provider' => (string) ( $remote['provider'] ?? '' ), 'at' => time() ), 'message' => 'Copia remota verificada; aplicando la retención…' );
	}

	private static function prune( array $job ) {
		Digitalisimo_Backups::job_prune( $job['spec'] );
		return array( 'next' => null, 'checkpoint' => array( 'at' => time() ), 'message' => 'Respaldo completo creado y verificado: ' . basename( (string) $job['spec']['file'] ) );
	}

	/**
	 * Copia sincrónica con el mismo motor (la previa a una restauración, que ya tiene el cerrojo del almacén). Queda
	 * registrada como trabajo: si el proceso muere, el detector la da por fallida y borra su área de trabajo.
	 */
	public static function run_inline( $file ) {
		return Digitalisimo_Backups_Jobs::run_inline( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'pre-restore', 'destination' => 'local', 'retain' => 1, 'retention' => 'none', 'via' => 'restore', 'file' => $file, 'inline' => true ) ) );
	}

	/**
	 * Al reintentar o volver a una fase, retira lo que esa fase y las siguientes dejaron a medias. Al repetir la misma
	 * fase con un punto de control por tramos (T2), conserva sus archivos: el tramo siguiente los trunca a ese punto.
	 */
	public static function discard( array $job, $phase ) {
		$staging = Digitalisimo_Backups_Job_Store::directory( $job['id'] ) . '/' . Digitalisimo_Backups_Job_Store::STAGING;
		if ( is_link( $staging ) || ! is_dir( $staging ) ) return;
		$index = array_search( $phase, Digitalisimo_Backups_Jobs::PHASES, true );
		if ( false === $index ) return;
		$keep = array();
		$resume = $phase === ( $job['phase'] ?? '' ) ? ( $job['checkpoint'][ $phase ]['resume'] ?? null ) : null;
		// El SQL de trabajo y, si el proceso murió justo después de darle su nombre final, también ese nombre (T2).
		if ( is_array( $resume ) && 'database' === $phase ) array_push( $keep, (string) ( $resume['file'] ?? '' ), preg_replace( '/\.part$/', '', (string) ( $resume['file'] ?? '' ) ) );
		if ( is_array( $resume ) && 'pack' === $phase ) foreach ( array( '.zip', '.dirs.jsonl', '.files.jsonl', '.entries.jsonl', '.code.jsonl' ) as $suffix ) $keep[] = 'package.' . ( $resume['tag'] ?? '' ) . $suffix;
		if ( $index <= 1 ) self::remove_matching( $staging, '/^database\./', $keep );
		if ( $index <= 2 ) self::remove_matching( $staging, '/^package\./', $keep );
	}
	/** Al fallar o cancelar: sin área de trabajo; un ZIP ya registrado sigue siendo local y verificado. */
	public static function on_terminal( array $job, $status, $message ) {
		// Una transferencia que falla o se cancela retira del destino lo que dejó a medias (multipart, temporales).
		// Sólo aparta su estado: la llamada al destino ocurre después, fuera del cerrojo del registro (job_transfer_abandon_pending).
		if ( 'transfer' === ( $job['phase'] ?? '' ) && empty( $job['spec']['inline'] ) ) Digitalisimo_Backups::job_transfer_queue_abandon( $job );
		try {
			Digitalisimo_Backups_Job_Store::remove_staging( $job['id'] );
		} catch ( Throwable $e ) {
			// La poda del almacén volverá a intentarlo.
		}
		$spec = (array) ( $job['spec'] ?? array() );
		if ( empty( $spec['file'] ) || ! empty( $spec['inline'] ) || 'local' === ( $spec['destination'] ?? 'local' ) || 'transfer' !== ( $job['phase'] ?? '' ) ) return;
		try {
			Digitalisimo_Backups::history_update( basename( (string) $spec['file'] ), array( 'transfer' => 'cancelled' === $status ? 'cancelled' : 'failed', 'transfer_error' => (string) $message ) );
		} catch ( Throwable $e ) {
			// El historial conserva la fila local: los datos van primero.
		}
	}

	public static function fingerprint( $path ) {
		clearstatcache( true, $path );
		$stat = @stat( $path );
		return is_array( $stat ) ? array( 'size' => (int) $stat['size'], 'mtime' => (int) $stat['mtime'], 'ino' => (int) $stat['ino'] ) : array();
	}
	/** Tamaño y fecha de modificación: el inodo no sirve, cambia con `rename()` en php-wasm (Playground) y en algunos montajes de red. */
	public static function same_fingerprint( $a, $b ) {
		if ( ! is_array( $a ) || ! is_array( $b ) || ! $a || ! $b ) return false;
		foreach ( array( 'size', 'mtime' ) as $key ) if ( (int) ( $a[ $key ] ?? -1 ) !== (int) ( $b[ $key ] ?? -2 ) ) return false;
		return true;
	}
	private static function remove_matching( $directory, $pattern, array $keep = array() ) {
		foreach ( (array) @scandir( $directory ) as $name ) {
			if ( '.' === $name || '..' === $name || ! preg_match( $pattern, $name ) || in_array( $name, $keep, true ) ) continue;
			$path = $directory . '/' . $name;
			if ( is_link( $path ) || is_file( $path ) ) @unlink( $path );
		}
	}
}
