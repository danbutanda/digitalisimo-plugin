<?php
defined( 'ABSPATH' ) || exit;

/**
 * La configuración de Backups es de esta instalación, como el historial (H23 del ensayo E4).
 *
 * Restaurar el respaldo de otra instalación (traído de un destino remoto o subido) traía en su SQL la configuración de
 * Backups del origen: desaparecía sin aviso la conexión con nombre creada aquí y
 * este sitio heredaba las automatizaciones del origen hacia su mismo destino (los dos respaldaban en la misma carpeta).
 *
 * Ahora, al restaurar el respaldo de otra instalación (sin fila en el historial de aquí, o con otra identidad), si este
 * sitio tiene configuración propia de Backups (una conexión con nombre, un destino principal con conexión completa,
 * automatizaciones distintas de las de serie, avisos o el respaldo antes de actualizar), la restauración la conserva: los ajustes y destinos, las conexiones con nombre y sus credenciales cifradas (el almacén de S4 de este
 * servidor, con sus claves y atado a sus conexiones: nunca se mezcla con el del origen), el catálogo de servidores, las
 * automatizaciones con sus eventos de WP-Cron, los avisos, el respaldo antes de actualizar y el índice de los destinos.
 * Viaja en el diario de la restauración (`config_kept`) y vuelve tras el SQL, también si el proceso muere entre medias.
 *
 * Un sitio sin configuración propia (una instalación nueva donde se restaura el respaldo de un sitio perdido) recibe la
 * del respaldo, como hasta ahora (S4: las credenciales del origen se vuelven a cifrar con las claves de este servidor).
 * Un respaldo de esta misma instalación (su fila del historial lleva la identidad de aquí) tampoco cambia: devuelve la
 * configuración de su momento, como decidió S4 («todo viaja en el respaldo»).
 * El historial, el llavero, «Seguridad», el registro del almacén, la migración y lo traído de un destino tienen su
 * propio mecanismo.
 */
trait Digitalisimo_Backups_Keep_Config {

	/** Opciones de la configuración propia de Backups (en una red, cada una en su ámbito: sitio principal o red). */
	private static function keep_config_options() {
		return array(
			self::OPTION,
			self::POLICIES,
			self::SERVERS,
			self::REMOTE_INDEX,
			Digitalisimo_Backups_Destinations::CONNECTIONS,
			Digitalisimo_Backups_Credentials::OPTION,
			Digitalisimo_Backups_Credentials::OPTION_MORE,
			Digitalisimo_Backups_Notify::OPTION,
			Digitalisimo_Backups_Pre_Update::OPTION,
			Digitalisimo_Backups_Pre_Update::STATE,
		);
	}
	/**
	 * El respaldo $file es de esta misma instalación: tiene fila en el historial y su identidad (`installation`) es la de
	 * aquí (una fila anterior a la identidad, sin ella, también: se registró aquí). Entonces su configuración es la de este
	 * sitio en otro momento y viaja como siempre (S4). Un archivo sin fila (traído, subido) o de otra identidad, no.
	 */
	private static function keep_config_same_installation( $file ) {
		$row = '' !== (string) $file ? self::history_item( wp_basename( (string) $file ) ) : array();
		if ( ! $row ) return false;
		$stamp = (array) ( $row['installation'] ?? array() );
		if ( empty( $stamp['id'] ) ) return true;
		$here = self::installation_stamp();
		return ! empty( $here['id'] ) && hash_equals( (string) $here['id'], (string) $stamp['id'] );
	}
	/** Este sitio tiene configuración de Backups propia (no la de una instalación recién hecha). */
	private static function keep_config_wanted() {
		try {
			if ( Digitalisimo_Backups_Destinations::connections() ) return true;
			foreach ( array_keys( self::manual_destinations( self::settings() ) ) as $id ) if ( 'local' !== (string) $id ) return true;
			$policies = get_option( self::POLICIES, false );
			if ( false !== $policies && $policies != self::default_policies() ) return true;
			foreach ( array( Digitalisimo_Backups_Notify::OPTION, Digitalisimo_Backups_Pre_Update::OPTION ) as $name ) if ( Digitalisimo_Backups_Keys::get( $name, array() ) ) return true;
		} catch ( Throwable $e ) {
			// Sin poder decidirlo, se conserva lo de aquí: perder una configuración propia es peor que no heredar la del origen.
			return true;
		}
		return false;
	}
	/** Foto de la configuración: valor de cada opción en el sitio y, en una red, en la red (`absent` si no existe). */
	private static function keep_config_snapshot() {
		$absent = '__digitalisimo_backups_absent__';
		$out = array( 'format' => 1, 'site' => array(), 'network' => null );
		foreach ( self::keep_config_options() as $name ) {
			$value = get_option( $name, $absent );
			$out['site'][ $name ] = $absent === $value ? array( 'absent' => true ) : array( 'value' => $value );
		}
		if ( is_multisite() ) {
			$out['network'] = array();
			foreach ( self::keep_config_options() as $name ) {
				$value = get_site_option( $name, $absent );
				$out['network'][ $name ] = $absent === $value ? array( 'absent' => true ) : array( 'value' => $value );
			}
		}
		return $out;
	}
	/**
	 * Tras el SQL (y tras volver a cifrar las credenciales que trajo): la configuración de este sitio vuelve tal cual, la
	 * del origen se retira y las automatizaciones recuperan sus eventos (los del origen se quitan de `cron`).
	 */
	private static function keep_config_reapply( $snapshot ) {
		if ( ! is_array( $snapshot ) || 1 !== ( $snapshot['format'] ?? null ) || ! is_array( $snapshot['site'] ?? null ) ) return;
		$allowed = array_flip( self::keep_config_options() );
		foreach ( $snapshot['site'] as $name => $entry ) {
			if ( ! isset( $allowed[ $name ] ) || ! is_array( $entry ) ) continue;
			if ( ! empty( $entry['absent'] ) ) delete_option( $name );
			elseif ( array_key_exists( 'value', $entry ) ) update_option( $name, $entry['value'], false );
		}
		if ( is_multisite() && is_array( $snapshot['network'] ?? null ) ) {
			foreach ( $snapshot['network'] as $name => $entry ) {
				if ( ! isset( $allowed[ $name ] ) || ! is_array( $entry ) ) continue;
				if ( ! empty( $entry['absent'] ) ) delete_site_option( $name );
				elseif ( array_key_exists( 'value', $entry ) ) update_site_option( $name, $entry['value'] );
			}
		}
		if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
		self::keep_config_events();
	}
	/** Eventos de WP-Cron de las automatizaciones: fuera los de las que no son de este sitio; las suyas, a su hora. */
	private static function keep_config_events() {
		try {
			$ids = array();
			foreach ( self::policies() as $policy ) $ids[ (string) ( $policy['id'] ?? '' ) ] = true;
			if ( function_exists( '_get_cron_array' ) && function_exists( 'wp_unschedule_event' ) ) {
				foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
					foreach ( (array) ( $hooks['digitalisimo_backups_policy'] ?? array() ) as $event ) {
						$args = (array) ( $event['args'] ?? array() );
						if ( ! isset( $ids[ (string) ( $args[0] ?? '' ) ] ) ) wp_unschedule_event( (int) $timestamp, 'digitalisimo_backups_policy', $args );
					}
				}
			}
			if ( function_exists( 'wp_schedule_event' ) && ( ! is_multisite() || is_main_site() ) ) self::sync_policy_schedules();
		} catch ( Throwable $e ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: automatizaciones tras restaurar: ' . $e->getMessage() );
		}
	}
	/** La foto que anotó el diario de esta restauración (o null). */
	private static function keep_config_from_journal() {
		$path = trailingslashit( self::local_directory() ) . self::RESTORE_JOURNAL;
		$data = is_file( $path ) && ! is_link( $path ) ? json_decode( (string) @file_get_contents( $path ), true ) : null;
		return is_array( $data ) && is_array( $data['config_kept'] ?? null ) ? $data['config_kept'] : null;
	}
}
