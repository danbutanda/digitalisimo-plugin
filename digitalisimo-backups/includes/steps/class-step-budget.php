<?php
defined( 'ABSPATH' ) || exit;

/** El punto de control de un tramo ya no describe sus archivos: la fase vuelve a empezar desde cero, sin reintento. */
class Digitalisimo_Backups_Resume_Invalid extends Exception {}

/**
 * Presupuesto de cada tramo de la exportación, el empaquetado y la verificación (T2). Un tramo trabaja hasta el fin
 * del presupuesto del tick (o un mínimo para avanzar siempre), nunca más allá del límite real de la petición
 * (`max_execution_time` leído antes de que el motor llame a `set_time_limit(0)`, o el corte de un proxy) menos un
 * margen, y se detiene antes si la memoria se acerca a `memory_limit`. Tras un tramo que murió (tiempo agotado,
 * memoria, proceso matado) el siguiente trabaja la mitad; tras uno que terminó bien vuelve a crecer.
 */
final class Digitalisimo_Backups_Step_Budget {
	/** Cupo de memoria que un tramo no pasa (fracción de memory_limit). */
	const MEMORY_SHARE = 0.75;
	/** Escala mínima del tramo tras varias muertes seguidas. */
	const MIN_SCALE = 0.125;
	/** @var int|null max_execution_time de la petición al cargar el plugin. */
	private static $limit = null;

	/** Se llama al cargar el archivo: después, `set_time_limit(0)` del motor cambia lo que devuelve ini_get(). */
	public static function capture() {
		if ( null === self::$limit ) self::$limit = (int) ini_get( 'max_execution_time' );
	}
	/** Segundos que dura como mucho esta petición; 0 sin límite (WP-CLI o el cron del sistema por PHP CLI). */
	public static function request_limit() {
		self::capture();
		$limit = 'cli' === PHP_SAPI ? 0 : ( self::$limit > 0 ? self::$limit : 30 );
		// Detrás de un proxy (Cloudflare corta a los 100 s) o en pruebas se puede fijar otro valor.
		return max( 0, (int) self::filter( 'digitalisimo_backups_request_limit', $limit ) );
	}
	/** Segundos que quedan antes del límite real de la petición, ya descontado el margen; INF sin límite. */
	public static function request_remaining() {
		$limit = self::request_limit();
		if ( $limit <= 0 ) return INF;
		$start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );
		$margin = max( 3.0, 0.25 * $limit );
		return max( 0.0, $start + $limit - $margin - microtime( true ) );
	}

	/**
	 * Plazo absoluto (microtime) de un tramo: el del tick que lo ejecuta, al menos `digitalisimo_backups_step_min`
	 * segundos para avanzar siempre, como mucho `$cap` segundos si se pide, por la escala adaptativa y nunca más allá
	 * del límite real de la petición.
	 */
	public static function deadline( $scale = 1.0, $cap = null ) {
		$now = microtime( true );
		$min = max( 0.05, (float) self::filter( 'digitalisimo_backups_step_min', 1.0 ) );
		$tick = Digitalisimo_Backups_Jobs::step_deadline();
		$length = is_finite( $tick ) ? max( $min, $tick - $now ) : INF;
		if ( null !== $cap ) $length = min( $length, max( $min, (float) $cap ) );
		if ( is_finite( $length ) ) $length = max( min( $min, $length ), $length * max( self::MIN_SCALE, min( 1.0, (float) $scale ) ) );
		$remaining = self::request_remaining();
		if ( is_finite( $remaining ) ) $length = min( $length, max( 0.0, $remaining ) );
		return is_finite( $length ) ? $now + $length : INF;
	}
	/** Duración máxima de un tramo de empaquetado o verificación: su punto de control se guarda al menos con esta frecuencia. */
	public static function slice_cap() { return max( 0.5, (float) self::filter( 'digitalisimo_backups_step_slice', 5.0 ) ); }
	/** Tope de unidades (lotes, carpetas, archivos o entradas) por tramo; 0 sin tope. Sólo lo usan las pruebas. */
	public static function units() { return max( 0, (int) self::filter( 'digitalisimo_backups_step_units', 0 ) ); }

	/** memory_limit en bytes; 0 sin límite. */
	public static function memory_limit() {
		$raw = trim( (string) ini_get( 'memory_limit' ) );
		if ( '' === $raw || '-1' === $raw ) return 0;
		$value = (float) $raw;
		switch ( strtolower( substr( $raw, -1 ) ) ) {
			case 'g': $value *= 1024; // Sigue.
			case 'm': $value *= 1024; // Sigue.
			case 'k': $value *= 1024;
		}
		return max( 0, (int) $value );
	}
	/** Bytes que el tramo aún puede ocupar sin pasar su cupo de memoria; PHP_INT_MAX sin límite. */
	public static function memory_headroom() {
		$limit = self::memory_limit();
		if ( ! $limit ) return PHP_INT_MAX;
		return (int) max( 0, self::MEMORY_SHARE * $limit - memory_get_usage( true ) );
	}
	/** El tramo debe cerrar: venció su plazo, agotó sus unidades o la memoria se acerca al límite. */
	public static function exhausted( $deadline, $units = 0, $reserve = 0 ) {
		$cap = self::units();
		if ( $cap && $units >= $cap ) return true;
		if ( microtime( true ) >= $deadline ) return true;
		return self::memory_headroom() <= max( 0, (int) $reserve );
	}

	/**
	 * Escala del siguiente tramo. Si el anterior de esta fase falló sin guardar su avance (el motor contó un intento:
	 * tiempo agotado, memoria agotada o proceso muerto), trabaja la mitad; si no, recupera poco a poco hasta 1.
	 */
	public static function scale( array $job, $phase, array $cursor ) {
		$scale = isset( $cursor['scale'] ) ? (float) $cursor['scale'] : 1.0;
		if ( (int) ( $job['attempts'][ $phase ] ?? 0 ) > 0 ) return max( self::MIN_SCALE, $scale / 2 );
		return min( 1.0, $scale * 1.25 );
	}
	/** Medida de un tramo para su punto de control y el registro del trabajo. */
	public static function measure( $started, $units, $scale ) {
		return array( 'seconds' => round( microtime( true ) - $started, 3 ), 'peak_mb' => round( memory_get_peak_usage( true ) / 1048576, 1 ), 'units' => (int) $units, 'scale' => round( (float) $scale, 3 ) );
	}
	private static function filter( $hook, $value ) { return function_exists( 'apply_filters' ) ? apply_filters( $hook, $value ) : $value; }
}
Digitalisimo_Backups_Step_Budget::capture();
