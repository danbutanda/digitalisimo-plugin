<?php
defined( 'ABSPATH' ) || exit;

/**
 * Costuras de seguridad del respaldo y pestaña «Seguridad». seal() cifra el ZIP ya verificado y open() prepara
 * cualquier archivo (ZIP o DGBK) para restaurarlo y decide su nivel de confianza: signed (firma válida con una clave
 * conocida o escrita), provenance (fila del historial creada aquí con el mismo SHA-256) o untrusted.
 */
final class Digitalisimo_Backups_Security {
	const OPTION = 'digitalisimo_backups_security';
	const ACTION_SAVE = 'digitalisimo_backups_save_security';
	const ACTION_REMOVE_KEY = 'digitalisimo_backups_retire_key';
	const STAGING = '.staging';
	const MARGIN = 268435456;

	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_' . self::ACTION_REMOVE_KEY, array( __CLASS__, 'remove_key' ) );
	}
	/** Con la constante en wp-config.php el cifrado es obligatorio: el respaldo lleva el propio wp-config.php. */
	public static function settings() {
		$saved = Digitalisimo_Backups_Keys::get( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array( 'encrypt' => ! empty( $saved['encrypt'] ) || 'none' !== Digitalisimo_Backups_Keys::constant_state(), 'require_signed' => ! empty( $saved['require_signed'] ) );
	}

	/** Clave y cifrado del respaldo que se va a crear. Falla cerrado: nunca sale en claro un respaldo que debía cifrarse. */
	public static function plan( $seal ) {
		$must = $seal && self::settings()['encrypt'];
		if ( $must && ! Digitalisimo_Backups_Sodium::available() ) throw new Exception( 'El cifrado está activo, pero este servidor no tiene las funciones de libsodium necesarias (' . implode( ', ', Digitalisimo_Backups_Sodium::missing() ) . '). No se creó un respaldo sin cifrar.' );
		try { $active = Digitalisimo_Backups_Keys::active(); }
		catch ( Exception $e ) {
			if ( $must ) throw new Exception( $e->getMessage() . ' No se creó un respaldo sin cifrar.', 0, $e );
			$active = null;
		}
		if ( $must && ! $active ) throw new Exception( 'El cifrado está activo, pero no hay clave: guarda una frase en Backups → Seguridad o define DIGITALISIMO_BACKUPS_KEY. No se creó un respaldo sin cifrar.' );
		return array( 'root' => $active ? $active[0] : null, 'descriptor' => $active ? $active[1] : null, 'encrypt' => $must );
	}

	/**
	 * Cifrado y huella de la clave que un trabajo de respaldo fija al encolarse (su especificación es inmutable). Falla
	 * cerrado como plan(): con el cifrado activo y sin clave no se encola nada. Nunca devuelve la clave.
	 */
	public static function job_settings( $inline ) {
		$plan = self::plan( ! $inline );
		$settings = array( 'encrypt' => (bool) $plan['encrypt'], 'key_id' => $plan['descriptor'] ? (string) $plan['descriptor']['id'] : '' );
		if ( $plan['root'] ) Digitalisimo_Backups_Sodium::memzero( $plan['root'] );
		return $settings;
	}
	/**
	 * Clave de un trabajo en cada fase: la de la huella que fijó al encolarse, aunque después se active otra. Si ya no
	 * se puede abrir, un trabajo cifrado falla cerrado (nunca sale en claro) y uno sin cifrar sale sin firma.
	 */
	public static function job_plan( array $spec ) {
		$security = is_array( $spec['security'] ?? null ) ? $spec['security'] : array();
		$encrypt = ! empty( $security['encrypt'] );
		$key_id = (string) ( $security['key_id'] ?? '' );
		$none = array( 'root' => null, 'descriptor' => null, 'encrypt' => false );
		if ( '' === $key_id ) {
			if ( $encrypt ) throw new Exception( 'El trabajo debía cifrarse y no fijó su clave. No se creó un respaldo sin cifrar; vuelve a iniciarlo.' );
			return $none;
		}
		if ( $encrypt && ! Digitalisimo_Backups_Sodium::available() ) throw new Exception( 'El cifrado está activo, pero este servidor no tiene las funciones de libsodium necesarias (' . implode( ', ', Digitalisimo_Backups_Sodium::missing() ) . '). No se creó un respaldo sin cifrar.' );
		$root = Digitalisimo_Backups_Keys::resolve_by_id( $key_id );
		$descriptor = false === $root ? null : Digitalisimo_Backups_Keys::descriptor_by_id( $key_id );
		if ( false === $root || ! $descriptor ) {
			if ( false !== $root ) Digitalisimo_Backups_Sodium::memzero( $root );
			if ( $encrypt ) throw new Exception( 'La clave ' . Digitalisimo_Backups_Keys::short( $key_id ) . ' con la que se inició este respaldo ya no se puede abrir (se quitó del llavero o cambiaron las claves de wp-config.php). No se creó un respaldo sin cifrar; vuelve a escribir la frase en Backups → Seguridad e inícialo otra vez.' );
			return $none;
		}
		return array( 'root' => $root, 'descriptor' => $descriptor, 'encrypt' => $encrypt );
	}

	/** Carpeta privada de trabajo dentro del almacén. */
	public static function new_staging( $storage ) {
		$root = rtrim( $storage, '/' ) . '/' . self::STAGING;
		if ( is_link( $root ) ) throw new Exception( 'La carpeta de trabajo de Backups es un enlace simbólico.' );
		if ( ! is_dir( $root ) && ! @mkdir( $root, 0700 ) && ! is_dir( $root ) ) throw new Exception( 'No se pudo crear la carpeta de trabajo de Backups.' );
		$path = $root . '/' . bin2hex( random_bytes( 12 ) );
		if ( ! @mkdir( $path, 0700 ) ) throw new Exception( 'No se pudo crear la carpeta de trabajo de Backups.' );
		return $path;
	}
	/** Con el bloqueo de operaciones tomado, todo lo de .staging/ y todo *.part del almacén es huérfano. */
	public static function clean_staging( $storage ) {
		$storage = rtrim( $storage, '/' );
		foreach ( glob( $storage . '/*.part' ) ?: array() as $part ) if ( is_file( $part ) || is_link( $part ) ) @unlink( $part );
		$root = $storage . '/' . self::STAGING;
		if ( is_link( $root ) ) { @unlink( $root ); return; }
		if ( is_dir( $root ) ) foreach ( (array) @scandir( $root ) as $name ) if ( '.' !== $name && '..' !== $name ) self::remove_tree( $root . '/' . $name );
	}
	public static function remove_tree( $path ) {
		if ( '' === (string) $path || ( ! file_exists( $path ) && ! is_link( $path ) ) ) return;
		if ( is_link( $path ) || ! is_dir( $path ) ) { @unlink( $path ); return; }
		foreach ( (array) @scandir( $path ) as $name ) if ( '.' !== $name && '..' !== $name ) self::remove_tree( $path . '/' . $name );
		@rmdir( $path );
	}

	/**
	 * Cifra el ZIP verificado a <nombre>.dgbk.part, lo descifra completo para comprobarlo y sólo entonces lo publica
	 * con rename(). Si algo falla no queda nada en el almacén salvo huérfanos que borra la siguiente operación.
	 */
	public static function seal( $zip_path, $target, $plan, $meta ) {
		$part = $target . '.part';
		try {
			if ( file_exists( $part ) || is_link( $part ) ) @unlink( $part );
			$size = max( 1, (int) @filesize( $zip_path ) );
			Digitalisimo_Backups::report_progress( 90, 'cifrado', 'Cifrando el respaldo…', true );
			Digitalisimo_Backups_Encrypted_File::encrypt( $zip_path, $part, $plan['root'], $plan['descriptor'], $meta, Digitalisimo_Backups_Encrypted_File::CHUNK, function( $done ) use ( $size ) { Digitalisimo_Backups::report_progress( 90 + (int) floor( 4 * $done / $size ), 'cifrado', 'Cifrando: ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $done ) . ' de ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $size ) ); } );
			Digitalisimo_Backups::report_progress( 94, 'cifrado', 'Comprobando el respaldo cifrado…', true );
			Digitalisimo_Backups_Encrypted_File::decrypt( $part, $plan['root'], null, null, function( $done ) use ( $size ) { Digitalisimo_Backups::report_progress( 94 + (int) floor( 4 * $done / $size ), 'cifrado', 'Comprobando el cifrado: ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $done ) . ' de ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $size ) ); } );
			if ( file_exists( $target ) || ! @rename( $part, $target ) ) throw new Exception( 'No se pudo publicar el respaldo cifrado verificado.' );
		} catch ( Throwable $e ) {
			@unlink( $part );
			throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e );
		}
		return $target;
	}

	/**
	 * Prepara un archivo para restaurarlo sin cambiar nada del sitio: comprueba el SHA-256 del historial, descifra un
	 * DGBK a .staging/ (con la clave conocida o el secreto escrito), verifica la firma y decide la confianza. El ZIP
	 * queda abierto en el informe: la inspección, el análisis del SQL y la importación usan el mismo.
	 */
	public static function open( $path, $item = array(), $secret = '' ) {
		if ( ! class_exists( 'ZipArchive' ) ) throw new Exception( 'El servidor no tiene ZipArchive.' );
		clearstatcache( true, $path );
		if ( is_link( $path ) || ! is_file( $path ) ) throw new Exception( 'El respaldo no es un archivo normal.' );
		$item = is_array( $item ) ? $item : array();
		if ( ! empty( $item['sha256'] ) ) {
			$checksum = @hash_file( 'sha256', $path );
			if ( false === $checksum || ! hash_equals( (string) $item['sha256'], $checksum ) ) throw new Exception( 'El SHA-256 del respaldo ya no coincide con el archivo registrado.' );
		}
		$provenance = ! empty( $item['sha256'] ) && 'imported' !== ( $item['origin'] ?? 'created' );
		$report = array( 'source' => $path, 'path' => $path, 'container' => 'zip', 'trust' => 'untrusted', 'auth' => 'unsigned', 'key' => null, 'via' => '', 'staging' => '', 'zip' => null, 'root' => null, 'header' => null );
		if ( Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) return self::open_encrypted( $path, (string) $secret, $report );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CHECKCONS ) ) throw new Exception( 'No se puede abrir el ZIP.' );
		try { $auth = Digitalisimo_Backups_Manifest_Auth::verify( $zip, (string) $secret ); }
		catch ( Throwable $e ) { $zip->close(); throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e ); }
		$short = isset( $auth['key']['id'] ) ? Digitalisimo_Backups_Keys::short( $auth['key']['id'] ) : '';
		if ( 'invalid' === $auth['state'] ) { $zip->close(); throw new Exception( 'El respaldo no supera la comprobación de su firma: ' . ( $auth['reason'] ?? 'fue modificado o está dañado.' ) . ' No se cambió nada del sitio.' ); }
		if ( 'wrong_key' === $auth['state'] ) { $zip->close(); throw new Exception( 'La frase o clave escrita no corresponde a la clave ' . $short . ' con la que se firmó este respaldo.' ); }
		$report['zip'] = $zip;
		$report['auth'] = $auth['state'];
		$report['key'] = $auth['key'] ?? null;
		if ( 'signed' === $auth['state'] ) { $report['trust'] = 'signed'; $report['root'] = $auth['root']; $report['via'] = $auth['via']; }
		elseif ( $provenance ) $report['trust'] = 'provenance';
		return $report;
	}
	private static function open_encrypted( $path, $secret, $report ) {
		$report['container'] = 'dgbk';
		if ( ! Digitalisimo_Backups_Sodium::available() ) throw new Exception( 'Este respaldo está cifrado y este servidor no tiene las funciones de libsodium para descifrarlo (' . implode( ', ', Digitalisimo_Backups_Sodium::missing() ) . ').' );
		$parsed = Digitalisimo_Backups_Encrypted_File::read_header( $path );
		$descriptor = $parsed['header']['key'];
		$short = Digitalisimo_Backups_Keys::short( $descriptor['id'] );
		$report['key'] = $descriptor;
		$report['header'] = $parsed['header'];
		$root = Digitalisimo_Backups_Keys::resolve_by_id( $descriptor['id'] );
		$report['via'] = 'keyring';
		if ( false === $root ) {
			if ( '' === $secret ) throw new Exception( 'Este respaldo está cifrado con la clave ' . $short . '. Escribe su frase o su clave; sin ella no se puede restaurar.' );
			$root = Digitalisimo_Backups_Keys::resolve_with_secret( $descriptor, $secret );
			$report['via'] = 'secret';
			if ( false === $root ) throw new Exception( 'La frase o clave escrita no corresponde a la clave ' . $short . ' de este respaldo.' );
		}
		$storage = dirname( $path );
		$free = Digitalisimo_Backups_Archive_Inspector::free_space( $storage );
		$needed = (int) ceil( $parsed['header']['inner_size'] * 1.05 ) + self::MARGIN;
		if ( null !== $free && $free < $needed ) throw new Exception( 'No hay espacio para descifrar el respaldo: se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed ) . ' libres en el almacén y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '.' );
		self::clean_staging( $storage );
		$staging = self::new_staging( $storage );
		$zip = null;
		try {
			$inner = $staging . '/inner.zip';
			Digitalisimo_Backups::report_progress( 5, 'descifrado', 'Descifrando el respaldo…', true );
			Digitalisimo_Backups_Encrypted_File::decrypt( $path, $root, $inner, $parsed );
			$zip = new ZipArchive();
			if ( true !== $zip->open( $inner, ZipArchive::CHECKCONS ) ) throw new Exception( 'El contenido descifrado no es un ZIP válido.' );
			$auth = Digitalisimo_Backups_Manifest_Auth::verify( $zip, '', $root );
			if ( 'signed' !== $auth['state'] || true !== $auth['encrypted'] || ( $auth['manifest']['backup_id'] ?? '' ) !== $parsed['header']['backup_id'] || ! hash_equals( $descriptor['id'], (string) ( $auth['key']['id'] ?? '' ) ) ) throw new Exception( 'El contenido descifrado no corresponde a su cabecera: el respaldo fue modificado o está dañado.' );
		} catch ( Throwable $e ) {
			self::close_zip( $zip );
			self::remove_tree( $staging );
			throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e );
		}
		return array_merge( $report, array( 'path' => $inner, 'zip' => $zip, 'staging' => $staging, 'trust' => 'signed', 'auth' => 'signed', 'root' => $root ) );
	}
	/** En PHP 8 cerrar un ZipArchive cerrado o sin abrir lanza ValueError; aquí sólo importa soltarlo. */
	private static function close_zip( $zip ) {
		if ( ! $zip instanceof ZipArchive ) return;
		try { @$zip->close(); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	public static function close( &$report ) {
		if ( ! is_array( $report ) ) return;
		self::close_zip( $report['zip'] ?? null );
		$report['zip'] = null;
		if ( ! empty( $report['staging'] ) ) self::remove_tree( $report['staging'] );
		$report['staging'] = '';
		if ( isset( $report['root'] ) ) Digitalisimo_Backups_Sodium::memzero( $report['root'] );
	}
	/** La palabra de confirmación depende de la confianza; un archivo sin firma pide aceptar expresamente su origen. */
	public static function require_confirmation( $report, $confirmation ) {
		$confirmation = strtoupper( trim( preg_replace( '/\s+/', ' ', (string) $confirmation ) ) );
		if ( 'untrusted' !== $report['trust'] ) {
			if ( ! in_array( $confirmation, array( 'RESTAURAR', 'RESTAURAR SIN FIRMA' ), true ) ) throw new Exception( 'Escribe RESTAURAR para confirmar una operación destructiva.' );
			return;
		}
		if ( self::settings()['require_signed'] ) throw new Exception( 'Este respaldo no tiene una firma válida y Backups → Seguridad sólo permite restaurar respaldos firmados. No se cambió nada del sitio.' );
		if ( 'RESTAURAR SIN FIRMA' !== $confirmation ) {
			$hint = 'needs_key' === $report['auth'] && ! empty( $report['key']['id'] ) ? ' Si conoces la frase o la clave ' . Digitalisimo_Backups_Keys::short( $report['key']['id'] ) . ' con la que se firmó, escríbela para comprobar su firma.' : '';
			throw new Exception( 'Este archivo no tiene una firma válida de este sitio ni un registro con su SHA-256: restaurarlo instala su código y sus datos. Revisa su origen y escribe RESTAURAR SIN FIRMA para continuar.' . $hint . ' No se cambió nada del sitio.' );
		}
	}
	/**
	 * Campos de seguridad de una fila nueva del historial. «signed» indica que el archivo lleva firma (verificada al
	 * crearlo); al restaurar se vuelve a comprobar. Las filas antiguas no los tienen y se leen como ZIP sin firma.
	 */
	public static function describe( $path ) {
		$info = array( 'container' => 'zip', 'key_id' => '', 'signed' => false, 'backup_id' => '' );
		try {
			if ( Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) {
				$header = Digitalisimo_Backups_Encrypted_File::read_header( $path )['header'];
				return array( 'container' => 'dgbk', 'key_id' => $header['key']['id'], 'signed' => true, 'backup_id' => $header['backup_id'] );
			}
			$zip = new ZipArchive();
			if ( true !== $zip->open( $path ) ) return $info;
			try {
				$data = self::manifest_summary( $zip );
				if ( is_array( $data ) && is_string( $data['backup_id'] ?? null ) ) $info['backup_id'] = $data['backup_id'];
				if ( is_array( $data['auth'] ?? null ) && Digitalisimo_Backups_Keys::valid_descriptor( $data['auth']['key'] ?? null ) && 0 === strpos( (string) $zip->getArchiveComment(), Digitalisimo_Backups_Manifest_Auth::PREFIX . ' ' . $data['auth']['key']['id'] . ' ' ) ) {
					$info['signed'] = true;
					$info['key_id'] = $data['auth']['key']['id'];
				}
			} finally {
				$zip->close();
			}
		} catch ( Throwable $e ) {
			return $info;
		}
		return $info;
	}
	/**
	 * `backup_id` y bloque `auth` del manifiesto. Uno de más de 1 MB (decenas de miles de entradas) no se decodifica
	 * entero, que con 64 MB no cabe (T2): se lee por partes y sólo se miran su principio (backup_id) y su final (auth, la
	 * última clave del manifiesto desde 1.0.59). Sólo describe la fila del historial: la restauración comprueba la firma.
	 */
	private static function manifest_summary( $zip ) {
		$stat = $zip->statName( 'manifest.json' );
		if ( ! $stat ) return null;
		if ( (int) $stat['size'] <= 1048576 ) return json_decode( (string) Digitalisimo_Backups_Manifest_Auth::read_manifest( $zip ), true, 64 );
		$stream = $zip->getStream( 'manifest.json' );
		if ( ! $stream ) return null;
		$head = '';
		$tail = '';
		try {
			while ( ! feof( $stream ) ) {
				$chunk = fread( $stream, 1048576 );
				if ( false === $chunk || '' === $chunk ) break;
				if ( strlen( $head ) < 65536 ) $head .= substr( $chunk, 0, 65536 - strlen( $head ) );
				$tail = substr( $tail . $chunk, -65536 );
			}
		} finally {
			fclose( $stream );
		}
		$summary = array();
		if ( preg_match( '/"backup_id"\s*:\s*"([0-9a-f-]{36})"/i', $head, $match ) ) $summary['backup_id'] = $match[1];
		$at = strrpos( $tail, '"auth":' );
		$auth = false === $at ? null : json_decode( '{' . substr( $tail, $at ), true, 64 );
		if ( is_array( $auth['auth'] ?? null ) ) $summary['auth'] = $auth['auth'];
		return $summary;
	}
	/** Aviso para un respaldo cifrado cuya clave este sitio no puede abrir sin que se escriba. */
	public static function missing_key_text( $key_id ) { return 'Este respaldo está cifrado con la clave ' . Digitalisimo_Backups_Keys::short( $key_id ) . '. Escribe su frase o su clave; sin ella no se puede restaurar.'; }
	/**
	 * Texto de un archivo de la lista de restauración: si está cifrado (por sus bytes, no por su extensión), con qué
	 * clave y si hay que escribirla. Sólo lee la cabecera pública del contenedor.
	 */
	public static function restore_label( $path, $item = array() ) {
		$key_id = (string) ( $item['key_id'] ?? '' );
		if ( 'dgbk' !== ( $item['container'] ?? '' ) ) {
			if ( ! Digitalisimo_Backups_Encrypted_File::is_encrypted( $path ) ) return '';
			try { $key_id = Digitalisimo_Backups_Encrypted_File::read_header( $path )['header']['key']['id']; }
			catch ( Exception $e ) { return ' · cifrado, cabecera dañada'; }
		}
		return ' · cifrado, clave ' . Digitalisimo_Backups_Keys::short( $key_id ) . ( Digitalisimo_Backups_Keys::can_open( $key_id ) ? '' : ' · escribe su frase o su clave' );
	}
	public static function trust_label( $trust ) { return array( 'signed' => 'firmado', 'provenance' => 'registrado en este sitio', 'untrusted' => 'sin firma' )[ $trust ] ?? 'sin firma'; }

	/** Etiqueta de la columna «Seguridad» del historial. Las filas antiguas no tienen estos campos. */
	public static function history_badge( $item ) {
		$key = ! empty( $item['key_id'] ) ? ' · ' . Digitalisimo_Backups_Keys::short( $item['key_id'] ) : '';
		if ( 'dgbk' === ( $item['container'] ?? '' ) ) {
			if ( Digitalisimo_Backups_Keys::can_open( (string) ( $item['key_id'] ?? '' ) ) ) return '<span class="digitalisimo-backups-badge is-encrypted">Cifrado' . esc_html( $key ) . '</span>';
			return '<span class="digitalisimo-backups-badge is-encrypted is-locked" title="' . esc_attr( self::missing_key_text( (string) ( $item['key_id'] ?? '' ) ) ) . '">Cifrado' . esc_html( $key ) . ' · falta la clave</span>';
		}
		if ( ! empty( $item['signed'] ) ) return '<span class="digitalisimo-backups-badge is-signed">Firmado' . esc_html( $key ) . '</span>';
		return '<span class="digitalisimo-backups-badge">Sin firma</span>';
	}

	private static function measure() {
		if ( Digitalisimo_Backups_Sodium::native() || ! Digitalisimo_Backups_Sodium::available() ) return null;
		$key = random_bytes( 32 );
		$data = random_bytes( 65536 );
		$start = microtime( true );
		list( $state ) = sodium_crypto_secretstream_xchacha20poly1305_init_push( $key );
		sodium_crypto_secretstream_xchacha20poly1305_push( $state, $data, '', Digitalisimo_Backups_Sodium::TAG_FINAL );
		$elapsed = max( 0.0001, microtime( true ) - $start );
		return 65536 / $elapsed;
	}
	/** Pestaña «Seguridad»: un formulario propio y ningún name repetido de otra pestaña. */
	public static function panel( $network, $history ) {
		if ( is_multisite() && ! $network && ! is_main_site() ) { echo '<section class="digitalisimo-backups-card"><h2>Seguridad de los respaldos</h2><p>La clave, el cifrado y la firma de los respaldos son de toda la red: se gestionan desde la administración de red o desde el sitio principal.</p></section>'; return; }
		$settings = self::settings();
		$ring = Digitalisimo_Backups_Keys::keyring();
		$constant = Digitalisimo_Backups_Keys::constant_state();
		$sodium = Digitalisimo_Backups_Sodium::available();
		$wrap = false !== Digitalisimo_Backups_Keys::wrap_key();
		if ( 'valid' === $constant && $sodium ) $source = 'wp-config.php · huella ' . Digitalisimo_Backups_Keys::short( Digitalisimo_Backups_Keys::key_id( Digitalisimo_Backups_Keys::parse_raw_key( constant( Digitalisimo_Backups_Keys::CONSTANT ) ) ) );
		elseif ( 'valid' === $constant ) $source = 'wp-config.php';
		elseif ( 'invalid' === $constant ) $source = 'wp-config.php · inválida: debe ser base64 de 32 bytes';
		elseif ( $ring['active'] ) $source = 'Frase guardada · huella ' . Digitalisimo_Backups_Keys::short( $ring['active'] ) . ( 'needs_passphrase' === Digitalisimo_Backups_Keys::entry_state( $ring['active'], $ring['keys'][ $ring['active'] ], $ring ) ? ' · vuelve a escribir la frase' : '' );
		else $source = 'Sin clave';
		$has_key = ( 'valid' === $constant || $ring['active'] ) && $sodium;
		$counts = array();
		foreach ( (array) $history as $item ) if ( ! empty( $item['key_id'] ) ) $counts[ $item['key_id'] ] = ( $counts[ $item['key_id'] ] ?? 0 ) + 1;
		echo '<section class="digitalisimo-backups-card"><h2>Seguridad de los respaldos</h2>';
		echo '<p>El cifrado protege las copias que salen del servidor (SFTP, Google Drive, OneDrive y descargas). No protege de quien ya controla este servidor: quien lee wp-config.php y la base de datos también lee la clave. La firma permite comprobar al restaurar que un respaldo no cambió.</p>';
		echo '<p><strong>Clave de los respaldos:</strong> ' . esc_html( $source ) . '</p>';
		echo '<p><strong>Cifrado disponible:</strong> ' . esc_html( $sodium ? ( Digitalisimo_Backups_Sodium::native() ? 'libsodium nativo' : 'sodium_compat de WordPress (PHP puro, más lento)' ) : 'no: faltan funciones de libsodium' ) . '</p>';
		$speed = self::measure();
		if ( $speed ) {
			$last = 0;
			foreach ( (array) $history as $item ) if ( in_array( $item['scope'] ?? '', array( 'network', 'installation' ), true ) ) { $last = (int) ( $item['size'] ?? 0 ); break; }
			echo '<p class="description">Velocidad medida en este servidor: ' . esc_html( number_format( $speed / 1048576, 2, ',', '.' ) ) . ' MB/s.' . ( $last ? ' Cifrar y comprobar un respaldo como el último (' . esc_html( size_format( $last ) ) . ') tardaría unos ' . esc_html( (string) max( 1, (int) ceil( 2 * $last / $speed / 60 ) ) ) . ' min.' : '' ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_SAVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '">';
		if ( $network ) echo '<input type="hidden" name="digitalisimo_backups_network" value="1">';
		echo '<table class="form-table">';
		if ( 'none' === $constant ) {
			$saved = $ring['active'] ? '<span class="digitalisimo-secret is-set">Frase guardada</span>' : '<span class="digitalisimo-secret is-empty">Sin frase</span>';
			echo '<tr><th><label for="digitalisimo-backups-passphrase">Frase de los respaldos</label></th><td><input class="regular-text" type="password" id="digitalisimo-backups-passphrase" name="digitalisimo_backups_security[passphrase]" value="" autocomplete="new-password"' . ( $ring['active'] ? ' placeholder="Guardada · escribe sólo para reemplazar"' : '' ) . '>' . $saved . '<p class="description">De ' . Digitalisimo_Backups_Keys::PASSPHRASE_MIN . ' a ' . Digitalisimo_Backups_Keys::PASSPHRASE_MAX . ' caracteres. La frase no se guarda: se guarda la clave que sale de ella, protegida con AUTH_KEY y SECURE_AUTH_KEY. Deja el campo vacío para conservar la actual.' . ( $wrap ? '' : ' <strong>Este sitio no puede guardar una frase:</strong> wp-config.php no define AUTH_KEY y SECURE_AUTH_KEY propias. Define allí DIGITALISIMO_BACKUPS_KEY.' ) . '</p></td></tr>';
			echo '<tr><th><label for="digitalisimo-backups-passphrase-repeat">Repite la frase</label></th><td><input class="regular-text" type="password" id="digitalisimo-backups-passphrase-repeat" name="digitalisimo_backups_security[passphrase_repeat]" value="" autocomplete="new-password"></td></tr>';
			echo '<tr><th>Copia de la frase</th><td><label><input type="checkbox" name="digitalisimo_backups_security[offsite]" value="1"> Guardé la frase fuera de este sitio</label><p class="description">Sin la frase (o la clave de wp-config.php) un respaldo cifrado no se puede restaurar: nadie puede recuperarlo, tampoco DIGITALÍSIMO.</p></td></tr>';
		} else {
			echo '<tr><th>Clave en wp-config.php</th><td><p class="description">DIGITALISIMO_BACKUPS_KEY firma y cifra los respaldos nuevos y el cifrado es obligatorio. Su valor nunca se muestra. Guárdala fuera de este sitio: sin ella los respaldos cifrados no se pueden restaurar.</p></td></tr>';
		}
		$encrypt_locked = 'none' !== $constant;
		echo '<tr><th>Cifrado</th><td><label><input type="checkbox" name="digitalisimo_backups_security[encrypt]" value="1"' . checked( $settings['encrypt'], true, false ) . disabled( $encrypt_locked || ( ! $has_key && ! $settings['encrypt'] ), true, false ) . '> Cifrar los respaldos nuevos</label><p class="description">Los respaldos nuevos se cifrarán. Sin la frase (o la clave de wp-config.php) no se pueden restaurar: nadie puede recuperarlos, tampoco DIGITALÍSIMO. Guarda la frase fuera de este sitio. Los respaldos anteriores no cambian y siguen sin cifrar. La copia previa a una restauración se queda en este servidor sin cifrar, pero firmada.</p></td></tr>';
		echo '<tr><th>Restauración</th><td><label><input type="checkbox" name="digitalisimo_backups_security[require_signed]" value="1"' . checked( $settings['require_signed'], true, false ) . '> Restaurar sólo respaldos firmados</label><p class="description">Con esta opción no se restaura un archivo sin firma válida aunque se escriba RESTAURAR SIN FIRMA (también UpdraftPlus). Los respaldos registrados en este sitio con su SHA-256 siguen restaurándose.</p></td></tr>';
		echo '</table>';
		submit_button( 'Guardar seguridad' );
		echo '</form></section>';
		echo '<section class="digitalisimo-backups-card"><h2>Llavero</h2>';
		if ( ! $ring['keys'] ) { echo '<p>El llavero está vacío.' . ( 'valid' === $constant ? ' Los respaldos nuevos usan la clave de wp-config.php.' : '' ) . '</p></section>'; return; }
		echo '<p>Las claves retiradas siguen sirviendo para restaurar y comprobar los respaldos que firmaron. Quitar una clave deja sin restaurar, salvo escribiendo su frase, los respaldos que dependen de ella.</p><table class="widefat striped"><thead><tr><th>Huella</th><th>Origen</th><th>Creada</th><th>Estado</th><th>Respaldos del historial</th><th>Acciones</th></tr></thead><tbody>';
		foreach ( $ring['keys'] as $key_id => $entry ) {
			$state = Digitalisimo_Backups_Keys::entry_state( $key_id, $entry, $ring );
			$label = array( 'active' => 'Activa', 'retired' => 'Retirada', 'needs_passphrase' => 'Vuelve a escribir la frase' )[ $state ];
			echo '<tr><td><code>' . esc_html( Digitalisimo_Backups_Keys::short( $key_id ) ) . '</code></td><td>Frase</td><td>' . esc_html( ! empty( $entry['created'] ) ? gmdate( 'Y-m-d H:i', (int) $entry['created'] ) . ' UTC' : '' ) . '</td><td>' . esc_html( $label ) . '</td><td>' . esc_html( (string) ( $counts[ $key_id ] ?? 0 ) ) . '</td><td>';
			if ( $key_id !== $ring['active'] ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( self::ACTION_REMOVE_KEY );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_REMOVE_KEY ) . '"><input type="hidden" name="digitalisimo_backups_key" value="' . esc_attr( $key_id ) . '">' . ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<input type="text" name="digitalisimo_backups_key_confirmation" placeholder="QUITAR" autocomplete="off" aria-label="Escribe QUITAR para confirmar"> <button class="button button-small">Quitar del llavero</button></form>';
			} else echo '—';
			echo '</td></tr>';
		}
		echo '</tbody></table></section>';
	}
	private static function guard_request( $action ) {
		if ( ! Digitalisimo_Backups::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( $action );
		if ( is_multisite() && ! is_main_site() ) Digitalisimo_Backups::redirect( false, 'La seguridad de los respaldos se gestiona desde la administración de red o el sitio principal.', null, 'security' );
	}
	public static function save() {
		self::guard_request( self::ACTION_SAVE );
		$input = isset( $_POST['digitalisimo_backups_security'] ) && is_array( $_POST['digitalisimo_backups_security'] ) ? wp_unslash( $_POST['digitalisimo_backups_security'] ) : array();
		$passphrase = is_string( $input['passphrase'] ?? null ) ? $input['passphrase'] : '';
		$repeat = is_string( $input['passphrase_repeat'] ?? null ) ? $input['passphrase_repeat'] : '';
		$message = array();
		try {
			if ( '' !== $passphrase || '' !== $repeat ) {
				if ( 'none' !== Digitalisimo_Backups_Keys::constant_state() ) throw new Exception( 'Con DIGITALISIMO_BACKUPS_KEY en wp-config.php no se usa una frase.' );
				if ( ! hash_equals( $passphrase, $repeat ) ) throw new Exception( 'Las dos frases no coinciden. No se cambió nada.' );
				if ( empty( $input['offsite'] ) ) throw new Exception( 'Confirma que guardaste la frase fuera de este sitio. No se cambió nada.' );
				if ( ! Digitalisimo_Backups_Sodium::available() ) throw new Exception( 'Este servidor no tiene las funciones de libsodium necesarias. No se cambió nada.' );
				$result = Digitalisimo_Backups_Keys::set_passphrase( $passphrase );
				$short = Digitalisimo_Backups_Keys::short( $result['key_id'] );
				$message[] = array( 'created' => 'Frase guardada (huella ' . $short . ').', 'rewrapped' => 'Frase guardada de nuevo para la clave ' . $short . '.', 'reactivated' => 'La clave ' . $short . ' vuelve a ser la activa.', 'unchanged' => 'La frase ya era la activa (huella ' . $short . ').' )[ $result['result'] ];
			}
			$settings = self::settings();
			$saved = Digitalisimo_Backups_Keys::get( self::OPTION, array() );
			$constant = 'none' !== Digitalisimo_Backups_Keys::constant_state();
			// Con la constante la casilla va deshabilitada y el cifrado es obligatorio; se conserva lo que había.
			$stored = $constant ? ! empty( $saved['encrypt'] ) : ! empty( $input['encrypt'] );
			$encrypt = $constant || $stored;
			if ( ! $constant && $stored && ! $settings['encrypt'] ) {
				$ring = Digitalisimo_Backups_Keys::keyring();
				if ( ! $ring['active'] || ! Digitalisimo_Backups_Sodium::available() ) throw new Exception( 'Para cifrar, guarda antes una frase o define DIGITALISIMO_BACKUPS_KEY en wp-config.php.' );
			}
			Digitalisimo_Backups_Keys::put( self::OPTION, array( 'encrypt' => $stored, 'require_signed' => ! empty( $input['require_signed'] ) ) );
			$message[] = $encrypt ? 'Los respaldos nuevos se cifrarán. Sin la frase (o la clave de wp-config.php) no se pueden restaurar: nadie puede recuperarlos, tampoco DIGITALÍSIMO. Guarda la frase fuera de este sitio.' : 'Seguridad guardada. Los respaldos nuevos no se cifran' . ( $settings['encrypt'] ? ': los anteriores cifrados siguen cifrados.' : '.' );
		} catch ( Exception $e ) {
			Digitalisimo_Backups::redirect( false, $e->getMessage(), null, 'security' );
		}
		Digitalisimo_Backups::redirect( true, implode( ' ', $message ), null, 'security' );
	}
	public static function remove_key() {
		self::guard_request( self::ACTION_REMOVE_KEY );
		$key_id = sanitize_key( wp_unslash( $_POST['digitalisimo_backups_key'] ?? '' ) );
		if ( 'QUITAR' !== strtoupper( trim( (string) wp_unslash( $_POST['digitalisimo_backups_key_confirmation'] ?? '' ) ) ) ) Digitalisimo_Backups::redirect( false, 'Escribe QUITAR para confirmar. No se cambió nada.', null, 'security' );
		try { Digitalisimo_Backups_Keys::remove( $key_id ); }
		catch ( Exception $e ) { Digitalisimo_Backups::redirect( false, $e->getMessage(), null, 'security' ); }
		Digitalisimo_Backups::redirect( true, 'Clave ' . Digitalisimo_Backups_Keys::short( $key_id ) . ' quitada del llavero. Sus respaldos sólo se pueden restaurar escribiendo su frase.', null, 'security' );
	}
}
