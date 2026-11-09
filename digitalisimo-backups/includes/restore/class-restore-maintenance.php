<?php
defined( 'ABSPATH' ) || exit;

/**
 * Modo de mantenimiento de una restauración por pasos (H2).
 *
 * WordPress incluye `ABSPATH/.maintenance` antes de cargar los plugins y, mientras su `$upgrading` tiene menos de 10
 * minutos, responde 503 a todo (portada, escritorio, AJAX, REST y WP-Cron). Una restauración por pasos necesita, en
 * cambio, que sus propios ticks lleguen: el archivo que escribe aquí deja pasar sólo las peticiones que traen el testigo
 * de la restauración (cookie `digitalisimo_backups_restore`, que recibe el navegador que la empezó, o el parámetro del
 * mismo nombre, que lleva el tick de WP-Cron y puede usar un cliente de REST); en el archivo sólo va su SHA-256.
 *
 * Dos modos: hasta el cambio (base de datos e importación de archivos a su área de trabajo, con el sitio intacto) se
 * renueva en cada tick y caduca solo a los 10 minutos si la restauración se detiene, porque el sitio sigue entero;
 * desde el cambio (`$until_done`) no caduca hasta que la restauración termina o vuelve a la copia previa, porque un sitio
 * a medio cambiar no debe servirse. No es un `.htaccess`: no hace recargar el servidor web (H6). Usa la misma marca que
 * el modo de mantenimiento de la restauración en una sola petición, así que cualquiera de los dos reconoce el del otro.
 */
final class Digitalisimo_Backups_Restore_Maintenance {
	const MARKER = 'DIGITALISIMO_BACKUPS_RESTORE';
	const COOKIE = 'digitalisimo_backups_restore';
	const PARAM = 'digitalisimo_backups_restore';

	public static function path() { return rtrim( ABSPATH, '/\\' ) . '/.maintenance'; }
	/** Un testigo nuevo (32 bytes en hexadecimal). */
	public static function new_token() { return bin2hex( random_bytes( 32 ) ); }
	public static function ours( $path = null ) {
		$path = null === $path ? self::path() : $path;
		return ! is_link( $path ) && is_file( $path ) && false !== strpos( (string) @file_get_contents( $path, false, null, 0, 1024 ), self::MARKER );
	}
	/**
	 * Escribe o renueva el mantenimiento. Un `.maintenance` ajeno (de una actualización en curso) o que es un enlace
	 * simbólico nunca se toca: la restauración sigue sin modo de mantenimiento y lo dice su registro. Devuelve si quedó activo.
	 */
	public static function hold( $token, $until_done, $job_id = '' ) {
		$path = self::path();
		if ( is_link( $path ) || ( is_file( $path ) && ! self::ours( $path ) ) ) return false;
		$hash = hash( 'sha256', (string) $token );
		$upgrading = $until_done ? time() + 315360000 : time();
		$content = '<?php // ' . self::MARKER . ': restauración de DIGITALÍSIMO Backups en curso' . ( '' !== $job_id ? ' (trabajo ' . preg_replace( '/[^0-9a-f-]/', '', (string) $job_id ) . ')' : '' ) . '. ' . ( $until_done ? 'El sitio está cambiando: sigue en mantenimiento hasta que termine o vuelva a la copia previa. Si se quedara así, revisa Backups (WP-CLI: wp digitalisimo-backups jobs run) antes de borrar este archivo.' : 'Si el proceso se detiene, WordPress sale del mantenimiento a los 10 minutos.' ) . "\n"
			. '$upgrading = ' . (int) $upgrading . ";\n"
			. '$digitalisimo_backups_restore = isset( $_COOKIE[\'' . self::COOKIE . '\'] ) ? $_COOKIE[\'' . self::COOKIE . '\'] : ( isset( $_GET[\'' . self::PARAM . '\'] ) ? $_GET[\'' . self::PARAM . '\'] : \'\' );' . "\n"
			. 'if ( is_string( $digitalisimo_backups_restore ) && \'\' !== $digitalisimo_backups_restore && hash_equals( \'' . $hash . '\', hash( \'sha256\', $digitalisimo_backups_restore ) ) ) $upgrading = 0;' . "\n"
			. 'unset( $digitalisimo_backups_restore );' . "\n";
		$temp = $path . '.tmp-' . bin2hex( random_bytes( 4 ) );
		if ( strlen( $content ) === @file_put_contents( $temp, $content ) && @rename( $temp, $path ) ) return true;
		@unlink( $temp );
		if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: no se pudo escribir .maintenance; la restauración sigue sin modo de mantenimiento.' );
		return false;
	}
	/** Retira el mantenimiento si es de una restauración (nunca el de una actualización de WordPress). */
	public static function release() {
		$path = self::path();
		if ( ! self::ours( $path ) ) return false;
		return @unlink( $path );
	}
	/** La petición trae el testigo de la restauración $token (cookie o parámetro). */
	public static function request_has( $token ) {
		if ( '' === (string) $token ) return false;
		foreach ( array( $_COOKIE[ self::COOKIE ] ?? null, $_GET[ self::PARAM ] ?? null, $_POST[ self::PARAM ] ?? null ) as $value ) {
			if ( is_string( $value ) && '' !== $value && hash_equals( hash( 'sha256', (string) $token ), hash( 'sha256', $value ) ) ) return true;
		}
		return false;
	}
	/** Cookie para el navegador que empezó la restauración: con ella ve la pantalla y la hace avanzar durante el mantenimiento. */
	public static function send_cookie( $token ) {
		if ( headers_sent() || ! function_exists( 'setcookie' ) ) return false;
		$secure = function_exists( 'is_ssl' ) && is_ssl();
		$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		if ( PHP_VERSION_ID >= 70300 ) return setcookie( self::COOKIE, (string) $token, array( 'expires' => time() + 2 * 86400, 'path' => $path, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax' ) );
		return setcookie( self::COOKIE, (string) $token, time() + 2 * 86400, $path . '; samesite=Lax', '', $secure, true );
	}
}
