<?php
defined( 'ABSPATH' ) || exit;

/**
 * Retención en el destino remoto (B6) y «sólo en el remoto» (I4).
 *
 * - La retención que retira un respaldo (la de su automatización o la global de los manuales) borra también su copia
 *   remota, pero sólo la que **registró este sitio**: fila creada aquí por el motor de trabajos (`job`, origen distinto
 *   de `imported`), copia verificada (`transfer` = ok) con su recibo, y el SHA-256 del recibo igual al de la fila. Nunca
 *   lista el destino para borrar: sólo usa el recibo, que el destino confina a la carpeta de su conexión. Un respaldo
 *   bloqueado no se toca.
 * - Con el cerrojo del historial sólo se anota (`remote_prune` pendiente); el borrado remoto ocurre después, fuera de
 *   ese cerrojo, con esperas cortas, en el tick que terminó el respaldo, en los siguientes y en el vigilante horario. Un
 *   fallo pasajero se reintenta con esperas crecientes (5 min a 6 h); uno permanente (credenciales, conexión eliminada)
 *   queda a la vista en el historial y se reintenta con la siguiente retención de esa automatización.
 * - «Sólo en el remoto»: tras verificar la copia remota, el ZIP local se borra; la fila queda con «sólo la copia
 *   remota» y restaurar exige traerla antes al almacén («Traer al almacén», REST `fetch` o WP-CLI `fetch`).
 */
trait Digitalisimo_Backups_Remote_Retention {

	/* ------------------------------------------------------------------ Identidad de la instalación (revisión H3) */

	/**
	 * Identidad de esta instalación para las filas del historial: el SHA-256 de un identificador aleatorio que vive en el
	 * almacén privado (`.installation-id`, 0600, fuera de la raíz web y de la base de datos), la raíz de WordPress y la URL
	 * del sitio. Un clon de staging, una copia de la base de datos o el mismo sitio en otra carpeta o en otro dominio dan
	 * otra identidad, así que sus filas (que llegan con la base) no son «de este sitio» para la retención remota. array()
	 * si el almacén no está disponible (la fila se registra sin identidad y su copia remota no se borra sola).
	 */
	public static function installation_stamp() {
		$id = self::installation_id();
		if ( '' === $id ) return array();
		$home = function_exists( 'home_url' ) ? rtrim( (string) home_url(), '/' ) : '';
		return array( 'id' => hash( 'sha256', $id . "\n" . self::journal_owner() . "\n" . $home ), 'home' => $home );
	}
	private static function installation_id() {
		try {
			$path = rtrim( self::local_directory(), '/\\' ) . '/.installation-id';
		} catch ( Throwable $e ) {
			return '';
		}
		if ( is_link( $path ) ) return '';
		if ( ! file_exists( $path ) ) {
			// Creación exclusiva: dos peticiones a la vez nunca dejan dos identificadores.
			$handle = @fopen( $path, 'x' );
			if ( $handle ) {
				fwrite( $handle, bin2hex( random_bytes( 32 ) ) );
				fclose( $handle );
				@chmod( $path, 0600 );
			}
		}
		clearstatcache( true, $path );
		$id = is_file( $path ) && ! is_link( $path ) ? trim( (string) @file_get_contents( $path ) ) : '';
		return preg_match( '/^[a-f0-9]{64}$/', $id ) ? $id : '';
	}
	/**
	 * 'same' (de esta instalación), 'other' (de otra: clon o copia de la base) o 'unknown' (fila sin identidad). Además
	 * de la identidad, el nombre del archivo tiene que llevar el host de este sitio (backup_file_name()): una fila
	 * copiada de otro sitio con su identidad no basta.
	 */
	private static function installation_of( $item ) {
		$item = (array) $item;
		$stamp = is_array( $item['installation'] ?? null ) ? $item['installation'] : array();
		if ( empty( $stamp['id'] ) || ! is_string( $stamp['id'] ) ) return 'unknown';
		$here = self::installation_stamp();
		if ( empty( $here['id'] ) || ! hash_equals( (string) $here['id'], $stamp['id'] ) ) return 'other';
		$host = function_exists( 'home_url' ) && function_exists( 'sanitize_title' ) ? sanitize_title( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) : '';
		return '' !== $host && 0 === strpos( (string) ( $item['file'] ?? '' ), 'digitalisimo-backup-' . $host . '-' ) ? 'same' : 'other';
	}
	/**
	 * Campos de una fila que entra de fuera (la sincronización de un destino de R1, la importación de Backuply de R4, una
	 * subida): `origin` = `imported` y sin identidad de instalación. Para esas filas la retención nunca borra la copia
	 * remota (remote_owned()). Se pasan en el `$extra` de remember(), que respeta `origin` = `imported`.
	 */
	public static function imported_row_fields( array $extra = array() ) {
		unset( $extra['installation'] );
		return array( 'origin' => 'imported' ) + $extra;
	}

	/**
	 * Si la retención puede borrar la copia remota de esta fila (ver la cabecera): fila del motor de trabajos de **esta
	 * instalación** (H3), no importada, copia verificada con su recibo y los dos SHA-256 presentes e iguales (H4).
	 */
	private static function remote_owned( $item ) {
		$item = (array) $item;
		$copy = self::remote_copy( $item );
		if ( ! $copy || 'ok' !== $copy['state'] || empty( $copy['remote']['provider'] ) ) return false;
		if ( 'imported' === (string) ( $item['origin'] ?? 'created' ) || '' === (string) ( $item['job'] ?? '' ) ) return false;
		$row_sha = (string) ( $item['sha256'] ?? '' );
		$remote_sha = (string) ( $copy['remote']['sha256'] ?? '' );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $row_sha ) || ! hash_equals( $row_sha, $remote_sha ) ) return false;
		return 'same' === self::installation_of( $item );
	}
	/** Filas de la retención global (respaldos manuales y el programado anterior a las automatizaciones). */
	private static function global_retention_row( $item ) {
		return in_array( (string) ( ( (array) $item )['policy'] ?? 'manual' ), array( 'manual', 'legacy-full' ), true );
	}
	/**
	 * Con el cerrojo del historial: anota para borrar en el remoto las copias de $files (nombre => true) que la retención
	 * retira. Una anotada que falló de forma permanente vuelve a intentarse.
	 */
	private static function retire_remote_locked( array $files ) {
		if ( ! $files ) return;
		self::change_history( function( &$history ) use ( $files ) {
			$changed = false;
			foreach ( $history as &$row ) {
				if ( ! is_array( $row ) || ! isset( $files[ (string) ( $row['file'] ?? '' ) ] ) || ! empty( $row['locked'] ) ) continue;
				// De otra instalación (o sin identidad): nunca se borra su copia remota, y la fila lo dice (H3).
				$installation = self::installation_of( $row );
				if ( 'same' !== $installation && self::remote_exists( $row ) && ( $row['remote_foreign'] ?? '' ) !== $installation ) { $row['remote_foreign'] = $installation; $changed = true; }
				if ( ! self::remote_owned( $row ) ) continue;
				$prune = is_array( $row['remote_prune'] ?? null ) ? $row['remote_prune'] : array();
				if ( 'pending' === ( $prune['state'] ?? '' ) ) continue;
				$row['remote_prune'] = array( 'state' => 'pending', 'attempts' => (int) ( $prune['attempts'] ?? 0 ), 'next' => 0, 'error' => '', 'at' => time() );
				$changed = true;
			}
			unset( $row );
			return $changed;
		} );
	}
	/** Retención global de las copias remotas: las N filas manuales más recientes con copia remota la conservan. */
	private static function retire_remote_global_locked( $keep ) {
		$busy = self::busy_backup_files();
		if ( null === $busy ) return;
		$rows = array_values( array_filter( self::fresh_history(), function( $item ) { return is_array( $item ) && empty( $item['locked'] ) && self::global_retention_row( $item ) && self::remote_exists( $item ); } ) );
		$retire = array();
		foreach ( array_slice( $rows, max( 1, (int) $keep ) ) as $item ) if ( ! isset( $busy[ wp_basename( (string) $item['file'] ) ] ) ) $retire[ (string) $item['file'] ] = true;
		self::retire_remote_locked( $retire );
	}

	/**
	 * @internal Borra en el destino las copias que la retención anotó, con esperas de 20 s como máximo por petición. Nunca
	 * lanza. Devuelve cuántas intentó.
	 * - Con el cerrojo de operaciones (revisión H5): el tick y el vigilante ya lo tienen ($locked); si no, se intenta sin
	 *   esperar y, ocupado («Traer al almacén», una restauración, otro respaldo), lo hará el siguiente tick. Tampoco toca
	 *   un respaldo que use un trabajo activo (una restauración desde ese archivo).
	 * - Cada fila se vuelve a leer por separado. Primero se prepara la conexión (`prepare()`: token, sesión SFTP), fuera del
	 *   cerrojo del historial; después, con ese cerrojo tomado, se comprueba otra vez que no está bloqueada, que sigue
	 *   siendo propia y con el mismo recibo, y sólo entonces se borra y se anota (revisión H2): un «Bloquear» que llega
	 *   mientras tanto o se aplica antes (y no se borra) o espera a que termine ese único borrado.
	 */
	public static function job_remote_retention_pending( $limit = 5, $locked = false ) {
		$lock = null;
		if ( ! $locked ) {
			try { $lock = self::try_operation_lock(); } catch ( Throwable $e ) { return 0; }
			if ( ! $lock ) return 0;
		}
		try {
			return self::remote_retention_run( $limit );
		} finally {
			if ( $lock ) self::release_operation_lock( $lock );
		}
	}
	private static function remote_retention_run( $limit ) {
		try {
			$files = array();
			foreach ( self::fresh_history() as $row ) {
				$prune = is_array( $row ) && is_array( $row['remote_prune'] ?? null ) ? $row['remote_prune'] : array();
				if ( 'pending' === ( $prune['state'] ?? '' ) && (int) ( $prune['next'] ?? 0 ) <= time() ) $files[] = (string) ( $row['file'] ?? '' );
			}
		} catch ( Throwable $e ) {
			return 0;
		}
		$done = 0;
		foreach ( $files as $file ) {
			// Cada petición espera como mucho 20 s y nunca más de lo que le queda a esta petición (30 s en el hosting); con
			// menos de 5 s, el siguiente tick o el vigilante.
			$remaining = Digitalisimo_Backups_Step_Budget::request_remaining();
			if ( $done >= $limit || $remaining < 5 ) break;
			$timeout = (int) max( 5, min( self::ABANDON_TIMEOUT, $remaining ) );
			$row = self::history_item( $file );
			$prune = is_array( $row['remote_prune'] ?? null ) ? $row['remote_prune'] : array();
			if ( 'pending' !== ( $prune['state'] ?? '' ) ) continue;
			$busy = self::busy_backup_files();
			if ( null === $busy || isset( $busy[ wp_basename( $file ) ] ) ) continue;
			$done++;
			try {
				// Bloqueado después de anotarlo, o la fila ya no cumple: no se borra nada.
				if ( ! empty( $row['locked'] ) || ! self::remote_owned( $row ) ) { self::history_update( $file, array( 'remote_prune' => null ) ); continue; }
				$remote = (array) $row['remote'];
				if ( self::remote_connection_gone( $remote ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Su conexión de ' . Digitalisimo_Backups_Destinations::label( (string) ( $remote['provider'] ?? '' ) ) . ' se eliminó en «Servidores»: bórrala desde el servicio.' );
				$id = self::remote_connection( $remote );
				$problem = self::credentials_problem( $id );
				if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
				Digitalisimo_Backups_Http::$cap_timeout = $timeout;
				$destination = self::destination( $id, null, '', '', $timeout );
				$destination->prepare();
				$receipt = json_encode( $remote );
				self::change_history( function( &$history ) use ( $file, $receipt, $destination, $remote ) {
					foreach ( $history as &$item ) {
						if ( ! is_array( $item ) || ! hash_equals( (string) ( $item['file'] ?? '' ), $file ) ) continue;
						$same = 'pending' === ( $item['remote_prune']['state'] ?? '' ) && json_encode( (array) ( $item['remote'] ?? array() ) ) === $receipt;
						if ( ! empty( $item['locked'] ) || ! $same || ! self::remote_owned( $item ) ) {
							// Se bloqueó (o cambió) entretanto: no se borra.
							unset( $item['remote_prune'] );
							unset( $item );
							return true;
						}
						// Un archivo que ya no existe no es un error del contrato.
						$destination->delete( $remote );
						$item = array_merge( $item, array( 'transfer' => 'pruned', 'remote' => array(), 'transfer_error' => '', 'remote_pruned_at' => time() ) );
						unset( $item['remote_prune'] );
						unset( $item );
						return true;
					}
					unset( $item );
					return false;
				} );
			} catch ( Throwable $e ) {
				self::remote_retention_failed( $file, $prune, $e );
			} finally {
				Digitalisimo_Backups_Http::$cap_timeout = null;
			}
		}
		return $done;
	}
	private static function remote_retention_failed( $file, array $prune, Throwable $error ) {
		$attempts = (int) ( $prune['attempts'] ?? 0 ) + 1;
		$permanent = $error instanceof Digitalisimo_Backups_Permanent_Error;
		$message = Digitalisimo_Backups_Http::redact( $error->getMessage() );
		try {
			self::history_update( $file, array( 'remote_prune' => array( 'state' => $permanent ? 'failed' : 'pending', 'attempts' => $attempts, 'next' => time() + min( 21600, 300 * ( 1 << min( 6, $attempts - 1 ) ) ), 'error' => $message, 'at' => (int) ( $prune['at'] ?? time() ) ) ) );
		} catch ( Throwable $e ) {
			// Se reintentará con la siguiente retención.
		}
		if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: la retención no pudo borrar la copia remota de ' . $file . ': ' . $message );
	}
	/** Texto del historial para una copia remota que la retención va a borrar o no pudo borrar ('' si no es el caso). */
	private static function remote_retention_text( $item ) {
		$prune = is_array( ( (array) $item )['remote_prune'] ?? null ) ? $item['remote_prune'] : array();
		if ( 'pending' === ( $prune['state'] ?? '' ) ) return ' · la retención la borrará' . ( '' !== (string) ( $prune['error'] ?? '' ) ? ' (reintento ' . (int) $prune['attempts'] . ': ' . $prune['error'] . ')' : '' );
		if ( 'failed' === ( $prune['state'] ?? '' ) ) return ' · la retención no pudo borrarla: ' . (string) ( $prune['error'] ?? '' ) . ' Se reintentará con la siguiente retención; también puedes usar «Eliminar».';
		$foreign = (string) ( ( (array) $item )['remote_foreign'] ?? '' );
		if ( 'other' === $foreign ) return ' · la retención no borra esta copia remota: la fila es de otra instalación (un clon, una copia de la base de datos o el sitio en otra carpeta o dominio)';
		if ( 'unknown' === $foreign ) return ' · la retención no borra esta copia remota: la fila no lleva la identidad de esta instalación (es anterior a esa comprobación o vino de fuera); bórrala con «Eliminar» si ya no la necesitas';
		return '';
	}

	/**
	 * @internal Fase «prune» con «sólo en el remoto»: borra el ZIP local del respaldo de este trabajo si su copia remota
	 * está verificada (fila con `transfer` = ok y recibo con el mismo SHA-256). Idempotente; nunca toca otro archivo.
	 */
	public static function job_remote_only( array $spec ) {
		if ( empty( $spec['remote_only'] ) || 'local' === (string) ( $spec['destination'] ?? 'local' ) || ! empty( $spec['inline'] ) ) return '';
		$file = wp_basename( (string) ( $spec['file'] ?? '' ) );
		// Una restauración en curso desde este mismo ZIP lo necesita: se queda (la siguiente retención no lo borra).
		foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( Digitalisimo_Backups_Jobs::is_restore( $job ) && wp_basename( (string) ( $job['spec']['file'] ?? '' ) ) === $file ) return '';
		return (string) self::change_history( function( &$history ) use ( $file ) {
			foreach ( $history as &$row ) {
				if ( ! is_array( $row ) || ! hash_equals( (string) ( $row['file'] ?? '' ), $file ) ) continue;
				if ( ! self::remote_owned( $row ) ) return false;
				$path = self::local_copy( $row );
				if ( '' !== $path ) {
					if ( ! @unlink( $path ) && file_exists( $path ) ) return false;
				}
				$row['local'] = 'remote-only';
				unset( $row );
				return 'removed';
			}
			unset( $row );
			return false;
		} );
	}
	/** Casilla «Sólo en el remoto» con su aviso (automatizaciones y respaldo manual). */
	private static function remote_only_field( $checked, $name = 'digitalisimo_backup_automation[remote_only]' ) {
		return '<p><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . ( $checked ? ' checked' : '' ) . '> Sólo en el remoto: no conservar el ZIP en este servidor</label><br><span class="description">El ZIP local se borra en cuanto la copia remota se verifica. <strong>Restaurar exigirá descargarla antes</strong> («Traer al almacén» en «Restaurar», o <code>fetch</code> por REST y WP-CLI), y si el destino falla o se pierde no quedará copia aquí. Si la copia remota falla, el ZIP local se conserva. Con destino local no aplica. La retención («Conservar») borra también las copias remotas que registró este sitio.</span></p>';
	}
}
