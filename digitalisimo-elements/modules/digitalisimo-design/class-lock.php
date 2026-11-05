<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Cerrojo compartido de DIGITALÍSIMO Elements: `INSERT IGNORE` con token propio en la tabla de opciones del sitio
 * actual, como `digitalisimo-theme/inc/switch.php` y Migración (regla de AGENTS.md: nunca `add_option` como cerrojo).
 *
 * - `acquire()` inserta `<time>|<token>` sin carga automática. Si la fila ya existe y tiene más de `$stale` segundos,
 *   la roba con un `UPDATE … WHERE option_value = <lo leído>`: sólo si nadie la cambió entre la lectura y el robo.
 * - Una petición nunca reentra en un cerrojo que ya tiene (devuelve `false`) y nunca suelta el de otra: `release()`
 *   borra la fila sólo si lleva su token.
 * - Los cerrojos que queden tomados se sueltan al terminar la petición (`register_shutdown_function`).
 * - En una red, cada sitio tiene su fila: el token se guarda por sitio y por nombre.
 *
 * Los nombres nuevos usan el prefijo `PREFIX` (`digitalisimo_elements_lock_presets`); los cerrojos que ya existían
 * conservan el suyo.
 */
final class Lock {
	const PREFIX = 'digitalisimo_elements_lock_';

	/** Segundos tras los que un cerrojo se considera abandonado y puede robarse. */
	const STALE = 300;

	/** @var array<string,string> Token propio por `<sitio>|<opción>`. */
	private static $tokens = array();

	/** @var bool */
	private static $shutdown = false;

	/**
	 * Toma el cerrojo `$option` en el sitio actual.
	 *
	 * @param string $option Nombre completo de la opción (`Lock::PREFIX . 'presets'`, o el de un cerrojo anterior).
	 * @param int    $stale  Segundos tras los que se roba.
	 */
	public static function acquire( string $option, int $stale = self::STALE ): bool {
		global $wpdb;
		$key = self::key( $option );
		if ( '' === $option || isset( self::$tokens[ $key ] ) || ! is_object( $wpdb ) ) {
			return false; // Ya lo tiene esta petición: no se reentra.
		}
		$token = function_exists( 'wp_generate_password' ) ? wp_generate_password( 20, false ) : bin2hex( random_bytes( 10 ) );
		$value = time() . '|' . $token;
		$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", $option, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( 1 !== $taken ) {
			$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( '' !== $held && time() - (int) $held < $stale ) {
				return false;
			}
			if ( 1 !== (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $option, $held ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return false;
			}
		}
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $option, 'options' );
		}
		if ( ! self::$shutdown ) {
			self::$shutdown = true;
			register_shutdown_function( array( __CLASS__, 'release_all' ) );
		}
		self::$tokens[ $key ] = $token;
		return true;
	}

	/**
	 * Suelta el cerrojo propio `$option` del sitio actual; nunca el de otra petición.
	 *
	 * @param string $option Nombre completo de la opción.
	 */
	public static function release( string $option ): void {
		global $wpdb;
		$key   = self::key( $option );
		$token = isset( self::$tokens[ $key ] ) ? self::$tokens[ $key ] : '';
		unset( self::$tokens[ $key ] );
		if ( '' === $token || ! is_object( $wpdb ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value LIKE %s", $option, '%|' . $wpdb->esc_like( $token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $option, 'options' );
		}
	}

	/**
	 * Esta petición tiene el cerrojo `$option` del sitio actual.
	 *
	 * @param string $option Nombre completo de la opción.
	 */
	public static function held( string $option ): bool {
		return isset( self::$tokens[ self::key( $option ) ] );
	}

	/**
	 * Ejecuta `$callback` bajo el cerrojo y lo suelta después, también si lanza una excepción.
	 *
	 * @param string   $option   Nombre completo de la opción.
	 * @param callable $callback Función sin argumentos.
	 * @param int      $stale    Segundos tras los que se roba.
	 * @param mixed    $busy     Lo que se devuelve si el cerrojo está tomado.
	 * @return mixed Lo que devuelva `$callback`, o `$busy`.
	 */
	public static function run( string $option, callable $callback, int $stale = self::STALE, $busy = false ) {
		if ( ! self::acquire( $option, $stale ) ) {
			return $busy;
		}
		try {
			return $callback();
		} finally {
			self::release( $option );
		}
	}

	/** Suelta los cerrojos propios que quedaran tomados (registrada al tomar el primero). */
	public static function release_all(): void {
		foreach ( array_keys( self::$tokens ) as $key ) {
			list( $blog, $option ) = explode( '|', $key, 2 );
			$switch = function_exists( 'is_multisite' ) && is_multisite() && (int) $blog && $blog !== self::blog() && function_exists( 'switch_to_blog' );
			if ( $switch ) {
				switch_to_blog( (int) $blog );
			}
			self::release( $option );
			if ( $switch ) {
				restore_current_blog();
			}
		}
	}

	private static function key( string $option ): string {
		return self::blog() . '|' . $option;
	}

	private static function blog(): string {
		return function_exists( 'get_current_blog_id' ) ? (string) get_current_blog_id() : '0';
	}
}
