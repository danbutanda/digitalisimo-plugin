<?php
defined( 'ABSPATH' ) || exit;

/**
 * Candados y límites del Asistente IA (docs/ASSISTANT.md, revisión A1 y M3).
 *
 * - Candado atómico: una fila con `INSERT IGNORE` en `options` (el patrón de `WP_Upgrader::create_lock()`), con
 *   caducidad en su valor; sólo lo suelta quien lo tomó (borrado con su valor exacto) y se suelta también al terminar el
 *   proceso (`register_shutdown_function`), aunque la respuesta en flujo acabe con `exit`. Nunca pasa por
 *   `get_option()`, así que la caché de opciones no interviene.
 * - Por conversación: una sola petición a la vez (confirmar dos veces, o confirmar y escribir a la vez, no se cruzan).
 * - Por usuario: N peticiones a la vez (1 por defecto), N mensajes por minuto y N llamadas al modelo por día, desde
 *   IA Tools → Avanzado (sitio o red) o el filtro `digitalisimo_ai_assistant_limits`. 0 en minuto o día = sin tope.
 */
final class Digitalisimo_AI_Assistant_Guard {
	const PREFIX   = 'digitalisimo_ai_asis_lock_';
	const LOCK_TTL = 300;

	/** @var array<string,string> candado => valor propio */
	private static $held = array();
	/** @var bool */
	private static $shutdown = false;

	/** Toma un candado; devuelve su clave o false si otro lo tiene (y no caducó). */
	public static function lock( $name, $ttl = self::LOCK_TTL ) {
		global $wpdb;
		$key   = self::PREFIX . $name;
		$value = strtolower( wp_generate_password( 20, false, false ) ) . '|' . ( time() + (int) $ttl );
		for ( $try = 0; $try < 2; $try++ ) {
			$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $key, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( 1 === (int) $inserted ) {
				self::$held[ $key ] = $value;
				if ( ! self::$shutdown ) {
					self::$shutdown = true;
					register_shutdown_function( array( __CLASS__, 'release_all' ) );
				}
				return $key;
			}
			$current = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$cut     = strrpos( $current, '|' );
			if ( '' !== $current && false !== $cut && (int) substr( $current, $cut + 1 ) >= time() ) {
				return false;
			}
			// Caducado (un proceso que murió sin soltarlo): se borra sólo esa fila exacta y se intenta otra vez.
			$wpdb->delete( $wpdb->options, array( 'option_name' => $key, 'option_value' => $current ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return false;
	}

	/**
	 * Renueva los candados propios mientras la petición sigue viva (cada paso del modelo y antes de cada herramienta):
	 * sólo se cambia la fila cuyo valor es el propio, así que nunca se roba uno ajeno.
	 */
	public static function renew_all( $ttl = self::LOCK_TTL ) {
		global $wpdb;
		foreach ( self::$held as $key => $value ) {
			$fresh = substr( $value, 0, (int) strrpos( $value, '|' ) ) . '|' . ( time() + (int) $ttl );
			if ( $wpdb->update( $wpdb->options, array( 'option_value' => $fresh ), array( 'option_name' => $key, 'option_value' => $value ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::$held[ $key ] = $fresh;
			}
		}
	}

	public static function unlock( $key ) {
		global $wpdb;
		if ( is_string( $key ) && isset( self::$held[ $key ] ) ) {
			$wpdb->delete( $wpdb->options, array( 'option_name' => $key, 'option_value' => self::$held[ $key ] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			unset( self::$held[ $key ] );
		}
	}

	public static function release_all() {
		foreach ( array_keys( self::$held ) as $key ) {
			self::unlock( $key );
		}
	}

	/** `per_minute`, `per_day` y `concurrent` efectivos. */
	public static function limits() {
		// Un campo vacío vale el valor por defecto, no «sin tope».
		$read   = function ( $key, $default ) {
			$value = Digitalisimo_AI::get( $key );
			return null === $value || '' === trim( (string) $value ) ? $default : (int) $value;
		};
		$limits = array(
			'per_minute' => max( 0, $read( 'assistant_rate_minute', 10 ) ),
			'per_day'    => max( 0, $read( 'assistant_daily_calls', 300 ) ),
			'concurrent' => max( 1, min( 10, $read( 'assistant_concurrency', 1 ) ) ),
		);
		$limits = (array) apply_filters( 'digitalisimo_ai_assistant_limits', $limits );
		return array( 'per_minute' => max( 0, (int) ( $limits['per_minute'] ?? 0 ) ), 'per_day' => max( 0, (int) ( $limits['per_day'] ?? 0 ) ), 'concurrent' => max( 1, (int) ( $limits['concurrent'] ?? 1 ) ) );
	}

	/** Un lugar libre entre las peticiones simultáneas del usuario: su candado o un error 429. */
	public static function user_slot( $user_id ) {
		$slots = self::limits()['concurrent'];
		for ( $i = 1; $i <= $slots; $i++ ) {
			$key = self::lock( 'u' . (int) $user_id . '_' . $i );
			if ( $key ) {
				return $key;
			}
		}
		return new WP_Error( 'digitalisimo_assistant_busy', 'Ya tienes una petición del Asistente en curso: espera a que termine (si se cortó, el aviso caduca en unos minutos).', array( 'status' => 429 ) );
	}

	/** Cuenta un mensaje del minuto actual; error 429 si ya se llegó al tope. */
	public static function rate( $user_id ) {
		$limit = self::limits()['per_minute'];
		if ( ! $limit ) {
			return true;
		}
		$key   = 'digitalisimo_ai_asis_rate_' . (int) $user_id . '_' . (int) floor( time() / 60 );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return new WP_Error( 'digitalisimo_assistant_rate', sprintf( 'Llegaste al límite de %d mensajes por minuto del Asistente: espera un momento.', $limit ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );
		return true;
	}

	/** Cuenta una llamada al modelo del día (UTC); false si ya no queda cupo. */
	public static function spend( $user_id ) {
		$limit = self::limits()['per_day'];
		if ( ! $limit ) {
			return true;
		}
		$key   = 'digitalisimo_ai_asis_day_' . (int) $user_id . '_' . gmdate( 'Ymd' );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, DAY_IN_SECONDS + HOUR_IN_SECONDS );
		return true;
	}
}
