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

	/** Si la retención puede borrar la copia remota de esta fila (ver la cabecera). */
	private static function remote_owned( $item ) {
		$item = (array) $item;
		$copy = self::remote_copy( $item );
		if ( ! $copy || 'ok' !== $copy['state'] || empty( $copy['remote']['provider'] ) ) return false;
		if ( 'imported' === (string) ( $item['origin'] ?? 'created' ) || '' === (string) ( $item['job'] ?? '' ) ) return false;
		$sha256 = (string) ( $copy['remote']['sha256'] ?? '' );
		return '' === $sha256 || hash_equals( (string) ( $item['sha256'] ?? '' ), $sha256 );
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
				if ( ! is_array( $row ) || ! isset( $files[ (string) ( $row['file'] ?? '' ) ] ) || ! empty( $row['locked'] ) || ! self::remote_owned( $row ) ) continue;
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
	 * @internal Borra en el destino las copias que la retención anotó, fuera del cerrojo del historial y con esperas de
	 * 20 s como máximo por petición. Nunca lanza. Devuelve cuántas intentó.
	 */
	public static function job_remote_retention_pending( $limit = 5 ) {
		try {
			$history = self::fresh_history();
		} catch ( Throwable $e ) {
			return 0;
		}
		$done = 0;
		foreach ( $history as $row ) {
			$prune = is_array( $row ) && is_array( $row['remote_prune'] ?? null ) ? $row['remote_prune'] : array();
			if ( 'pending' !== ( $prune['state'] ?? '' ) || (int) ( $prune['next'] ?? 0 ) > time() ) continue;
			// Cada petición espera como mucho 20 s y nunca más de lo que le queda a esta petición (30 s en el hosting); con
			// menos de 5 s, el siguiente tick o el vigilante.
			$remaining = Digitalisimo_Backups_Step_Budget::request_remaining();
			if ( $done >= $limit || $remaining < 5 ) break;
			$timeout = (int) max( 5, min( self::ABANDON_TIMEOUT, $remaining ) );
			$done++;
			$file = (string) ( $row['file'] ?? '' );
			try {
				// Bloqueado después de anotarlo, o la fila ya no cumple: no se borra nada.
				if ( ! empty( $row['locked'] ) || ! self::remote_owned( $row ) ) { self::history_update( $file, array( 'remote_prune' => null ) ); continue; }
				$remote = (array) $row['remote'];
				if ( self::remote_connection_gone( $remote ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Su conexión de ' . Digitalisimo_Backups_Destinations::label( (string) ( $remote['provider'] ?? '' ) ) . ' se eliminó en «Servidores»: bórrala desde el servicio.' );
				$id = self::remote_connection( $remote );
				$problem = self::credentials_problem( $id );
				if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
				Digitalisimo_Backups_Http::$cap_timeout = $timeout;
				self::destination( $id, null, '', '', $timeout )->delete( $remote );
				// Confirmado por el destino: la fila ya no tiene copia remota.
				self::history_update( $file, array( 'transfer' => 'pruned', 'remote' => array(), 'remote_prune' => null, 'transfer_error' => '', 'remote_pruned_at' => time() ) );
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
