<?php
defined( 'ABSPATH' ) || exit;

/**
 * Modo de mantenimiento de WordPress durante una restauración (I8).
 *
 * Mientras corren el SQL y la extracción de archivos (también la recuperación desde la copia previa si algo falla),
 * `ABSPATH/.maintenance` hace que el sitio —en una red, todos los sitios— responda «en mantenimiento» (503) en vez de
 * servir una base de datos o unos archivos a medias. Se retira siempre al terminar, falle o no, y la operación
 * siguiente lo retira si el proceso murió (con el diario de la restauración).
 *
 * WordPress sólo respeta `.maintenance` durante 10 minutos desde su `$upgrading`: aquí se renueva cada minuto mientras
 * la restauración avanza, así que una restauración larga sigue protegida y, si el proceso muere, el sitio vuelve solo
 * como mucho 10 minutos después. No es un `.htaccess`: no hace recargar el servidor web (ver H6 en docs/BACKUPS.md).
 */
final class Digitalisimo_Backups_Maintenance {
	const MARKER = 'DIGITALISIMO_BACKUPS_RESTORE';
	const REFRESH = 60;
	private static $active = false;
	private static $written = 0;

	public static function path() { return rtrim( ABSPATH, '/\\' ) . '/.maintenance'; }
	public static function active() { return self::$active; }
	/** Activa el mantenimiento. Un `.maintenance` que sea un enlace simbólico nunca se sigue: la restauración avisa y sigue sin él. */
	public static function start() {
		self::$active = true;
		self::$written = 0;
		self::write();
	}
	/** Lo llaman la importación del SQL y la escritura de cada archivo: renueva `$upgrading` como mucho una vez por minuto. */
	public static function heartbeat() {
		if ( self::$active && time() - self::$written >= self::REFRESH ) self::write();
	}
	/** Retira el mantenimiento; sólo borra el `.maintenance` que escribió esta restauración. */
	public static function stop() {
		if ( ! self::$active ) return;
		self::$active = false;
		self::clear_stale();
	}
	/** Un `.maintenance` de una restauración que murió (lo reconoce por su marca): se retira. Devuelve si lo había. */
	public static function clear_stale() {
		$path = self::path();
		if ( is_link( $path ) || ! is_file( $path ) || ! self::ours( $path ) ) return false;
		return @unlink( $path );
	}
	public static function ours( $path = null ) {
		$path = null === $path ? self::path() : $path;
		return is_file( $path ) && false !== strpos( (string) @file_get_contents( $path, false, null, 0, 1024 ), self::MARKER );
	}
	private static function write() {
		$path = self::path();
		if ( is_link( $path ) ) {
			if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: .maintenance es un enlace simbólico; la restauración sigue sin modo de mantenimiento.' );
			return;
		}
		// Sólo un entero y un comentario: WordPress incluye este archivo para leer $upgrading.
		$content = '<?php $upgrading = ' . (int) time() . '; // ' . self::MARKER . ': restauración de DIGITALÍSIMO Backups en curso. Si el proceso se detiene, WordPress sale del mantenimiento a los 10 minutos.' . "\n";
		$temp = $path . '.tmp-' . bin2hex( random_bytes( 4 ) );
		if ( strlen( $content ) === @file_put_contents( $temp, $content ) && @rename( $temp, $path ) ) {
			self::$written = time();
			return;
		}
		@unlink( $temp );
		if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: no se pudo escribir .maintenance; la restauración sigue sin modo de mantenimiento.' );
	}
}
