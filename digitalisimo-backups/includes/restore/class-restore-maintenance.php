<?php
defined( 'ABSPATH' ) || exit;

/**
 * Modo de mantenimiento de una restauración por pasos (H2).
 *
 * WordPress incluye `ABSPATH/.maintenance` antes de cargar los plugins (y antes de la base de datos) y, mientras su
 * `$upgrading` tiene menos de 10 minutos, responde 503 a todo: portada, escritorio, AJAX, REST y WP-Cron. El archivo que
 * escribe aquí es una puerta que deja pasar sólo:
 *  - los ticks de la restauración: `wp-cron.php` (el cron del sistema o el loopback, sin testigo: sólo corre el evento
 *    de la restauración, ver Digitalisimo_Backups_Jobs::restore_cron_jobs()) y el tick por testigo de la pantalla
 *    (`admin-ajax.php?action=digitalisimo_backups_restore_tick`);
 *  - quien trae el testigo de la restauración (cookie `digitalisimo_backups_restore`, que recibe el navegador que la
 *    empezó, o el parámetro del mismo nombre: un cliente de REST); en el archivo sólo va su SHA-256;
 *  - WP-CLI (la vía para terminar o recuperar a mano).
 *
 * Los ticks (y, desde el cambio, todo lo que pasa) cargan WordPress «mínimo»: sólo este plugin, sin los demás, sin
 * mu-plugins y sin el tema (revisión, A3 y P1): a medio cambiar, el código de un plugin no se ejecuta contra la base de
 * datos o el núcleo del otro lado, y una actualización automática no corre a la vez que el cambio.
 *
 * Una petición que se queda fuera (un visitante, otro administrador) despierta la restauración como lo haría WP-Cron:
 * como mucho una vez por minuto lanza `wp-cron.php` sin esperar la respuesta (A1). Así, cerrar el navegador no deja el
 * sitio en mantenimiento: lo siguen las visitas, el cron del sistema o WP-CLI.
 *
 * Dos modos: hasta el cambio (base de datos e importación de archivos a su área de trabajo, con el sitio intacto) se
 * renueva en cada tick y caduca solo a los 10 minutos si la restauración se detiene, porque el sitio sigue entero;
 * desde el cambio (`$until_done`), y durante toda una vuelta a la copia previa, no caduca hasta que la restauración
 * termina, porque un sitio a medio cambiar no debe servirse. No es un `.htaccess`: no hace recargar el servidor web (H6).
 * Usa la misma marca que el modo de mantenimiento de la restauración en una sola petición, así que cualquiera de los dos
 * reconoce el del otro.
 */
final class Digitalisimo_Backups_Restore_Maintenance {
	const MARKER = 'DIGITALISIMO_BACKUPS_RESTORE';
	const COOKIE = 'digitalisimo_backups_restore';
	const PARAM = 'digitalisimo_backups_restore';
	/** Segundos entre dos despertares de la restauración desde una petición que se queda fuera. */
	const KICK_EVERY = 60;

	public static function path() { return rtrim( ABSPATH, '/\\' ) . '/.maintenance'; }
	/** Un testigo nuevo (32 bytes en hexadecimal). */
	public static function new_token() { return bin2hex( random_bytes( 32 ) ); }
	public static function ours( $path = null ) {
		$path = null === $path ? self::path() : $path;
		return ! is_link( $path ) && is_file( $path ) && false !== strpos( (string) @file_get_contents( $path, false, null, 0, 1024 ), self::MARKER );
	}
	/** El mantenimiento de una restauración que no caduca (desde el cambio, o la vuelta a la copia previa). */
	public static function until_done( $path = null ) {
		$path = null === $path ? self::path() : $path;
		return self::ours( $path ) && false !== strpos( (string) @file_get_contents( $path, false, null, 0, 4096 ), "'until_done' => true" );
	}
	/** Plugin de este archivo (`carpeta/archivo.php`), el único que cargan los ticks durante el mantenimiento. */
	public static function plugin() {
		if ( defined( 'DIGITALISIMO_BACKUPS_FILE' ) && function_exists( 'plugin_basename' ) ) return plugin_basename( DIGITALISIMO_BACKUPS_FILE );
		return basename( dirname( __DIR__, 2 ) ) . '/digitalisimo-backups.php';
	}
	/**
	 * Escribe o renueva el mantenimiento. Un `.maintenance` ajeno (de una actualización en curso) o que es un enlace
	 * simbólico nunca se toca: la restauración sigue sin modo de mantenimiento y lo dice su registro. Devuelve si quedó activo.
	 */
	public static function hold( $token, $until_done, $job_id = '' ) {
		$path = self::path();
		if ( is_link( $path ) || ( is_file( $path ) && ! self::ours( $path ) ) ) return false;
		$content = self::content( (string) $token, (bool) $until_done, (string) $job_id );
		$temp = $path . '.tmp-' . bin2hex( random_bytes( 4 ) );
		if ( strlen( $content ) === @file_put_contents( $temp, $content ) && @rename( $temp, $path ) ) return true;
		@unlink( $temp );
		if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: no se pudo escribir .maintenance; la restauración sigue sin modo de mantenimiento.' );
		return false;
	}
	/**
	 * El PHP del `.maintenance`. Lo incluye wp_is_maintenance_mode() (con `global $upgrading`) antes de cargar la base de
	 * datos y los plugins, donde ya existe add_filter(). Sin rutas ni secretos: el testigo va como SHA-256 y la dirección
	 * de WP-Cron es pública. Puede incluirse más de una vez en la misma petición.
	 */
	private static function content( $token, $until_done, $job_id ) {
		$cron = function_exists( 'site_url' ) ? (string) site_url( 'wp-cron.php' ) : '';
		$config = array(
			'hash' => hash( 'sha256', $token ),
			'until_done' => $until_done,
			'plugin' => self::plugin(),
			'cron' => $cron,
			'every' => self::KICK_EVERY,
		);
		$what = $until_done
			? 'El sitio está cambiando: sigue en mantenimiento hasta que termine o vuelva a la copia previa; las visitas, WP-Cron, el cron del sistema y WP-CLI (wp digitalisimo-backups jobs run) la hacen avanzar. Si se quedara así, revisa Backups antes de borrar este archivo.'
			: 'Si el proceso se detiene, WordPress sale del mantenimiento a los 10 minutos.';
		return '<?php // ' . self::MARKER . ': restauración de DIGITALÍSIMO Backups en curso' . ( '' !== $job_id ? ' (trabajo ' . preg_replace( '/[^0-9a-f-]/', '', $job_id ) . ')' : '' ) . '. ' . $what . "\n"
			. '$upgrading = ' . (int) ( $until_done ? time() + 315360000 : time() ) . ";\n"
			. 'if ( ! function_exists( \'digitalisimo_backups_restore_gate\' ) ) {' . "\n"
			. self::gate_source()
			. "}\n"
			. 'if ( digitalisimo_backups_restore_gate( ' . var_export( $config, true ) . ', $upgrading ) ) $upgrading = 0;' . "\n";
	}
	/** La puerta: decide quién pasa, carga WordPress mínimo para los ticks y despierta la restauración (ver la clase). */
	private static function gate_source() {
		return <<<'PHP'
	function digitalisimo_backups_restore_gate( $config, $upgrading ) {
		// Caducado (antes del cambio, sin ticks en 10 minutos): WordPress ya no está en mantenimiento y nada cambia.
		if ( empty( $config['until_done'] ) && time() - (int) $upgrading >= 600 ) return false;
		$read = function( $source, $key ) { return isset( $source[ $key ] ) && is_string( $source[ $key ] ) ? $source[ $key ] : ''; };
		$token = $read( $_COOKIE, 'digitalisimo_backups_restore' );
		if ( '' === $token ) $token = $read( $_GET, 'digitalisimo_backups_restore' );
		if ( '' === $token ) $token = $read( $_POST, 'digitalisimo_backups_restore' );
		$known = '' !== $token && hash_equals( (string) $config['hash'], hash( 'sha256', $token ) );
		$script = isset( $_SERVER['SCRIPT_FILENAME'] ) ? basename( (string) $_SERVER['SCRIPT_FILENAME'] ) : '';
		$cron = 'wp-cron.php' === $script;
		$tick = 'admin-ajax.php' === $script && 'digitalisimo_backups_restore_tick' === $read( $_REQUEST, 'action' );
		$cli = defined( 'WP_CLI' ) && WP_CLI;
		$ours = $cli && ! empty( $GLOBALS['argv'] ) && is_array( $GLOBALS['argv'] ) && in_array( 'digitalisimo-backups', $GLOBALS['argv'], true );
		if ( $cron || ( $known && ( $tick || $config['until_done'] ) ) || ( $ours && $config['until_done'] ) ) digitalisimo_backups_restore_minimal( (string) $config['plugin'] );
		if ( $cli || $cron || $known ) return true;
		digitalisimo_backups_restore_kick( (string) $config['cron'], (int) $config['every'] );
		return false;
	}
	function digitalisimo_backups_restore_minimal( $plugin ) {
		if ( defined( 'DIGITALISIMO_BACKUPS_RESTORE_MINIMAL' ) || ! function_exists( 'add_filter' ) ) return;
		define( 'DIGITALISIMO_BACKUPS_RESTORE_MINIMAL', true );
		// Las carpetas de verdad (el plugin las necesita para saber qué cambiar) antes de esconderlas a WordPress.
		if ( defined( 'WP_CONTENT_DIR' ) && ! defined( 'DIGITALISIMO_BACKUPS_MU_PLUGIN_DIR' ) ) define( 'DIGITALISIMO_BACKUPS_MU_PLUGIN_DIR', defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins' );
		$none = rtrim( ABSPATH, '/\\' ) . '/.digitalisimo-restore-minimal';
		add_filter( 'option_active_plugins', function( $list ) use ( $plugin ) { return in_array( $plugin, (array) $list, true ) ? array( $plugin ) : array(); }, PHP_INT_MAX );
		add_filter( 'site_option_active_sitewide_plugins', function( $list ) use ( $plugin ) { $list = (array) $list; return isset( $list[ $plugin ] ) ? array( $plugin => $list[ $plugin ] ) : array(); }, PHP_INT_MAX );
		add_filter( 'template_directory', function() use ( $none ) { return $none; }, PHP_INT_MAX );
		add_filter( 'stylesheet_directory', function() use ( $none ) { return $none; }, PHP_INT_MAX );
		if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) define( 'WPMU_PLUGIN_DIR', $none );
	}
	function digitalisimo_backups_restore_kick( $url, $every ) {
		$self = rtrim( ABSPATH, '/\\' ) . '/.maintenance';
		$last = @filemtime( $self );
		if ( '' === $url || ( false !== $last && time() - $last < $every ) || ! @touch( $self ) ) return;
		$parts = parse_url( $url );
		if ( empty( $parts['host'] ) || ! function_exists( 'stream_socket_client' ) ) return;
		$https = 'https' === strtolower( isset( $parts['scheme'] ) ? $parts['scheme'] : 'http' );
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : ( $https ? 443 : 80 );
		$context = stream_context_create( array( 'ssl' => array( 'verify_peer' => false, 'verify_peer_name' => false ) ) );
		$socket = @stream_socket_client( ( $https ? 'ssl://' : 'tcp://' ) . $parts['host'] . ':' . $port, $errno, $error, 1, STREAM_CLIENT_CONNECT, $context );
		if ( ! $socket ) return;
		$path = ( isset( $parts['path'] ) ? $parts['path'] : '/wp-cron.php' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		@fwrite( $socket, 'GET ' . $path . " HTTP/1.1\r\nHost: " . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $port : '' ) . "\r\nUser-Agent: DIGITALISIMO Backups (restauración en curso)\r\nConnection: close\r\n\r\n" );
		@fclose( $socket );
	}

PHP;
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
	/** Línea para el cron del sistema de un hosting sin visitas: despierta la restauración cada minuto (sin testigo). */
	public static function system_cron_line() {
		$url = function_exists( 'site_url' ) ? (string) site_url( 'wp-cron.php' ) : 'https://example.com/wp-cron.php';
		return '* * * * * curl -s -o /dev/null ' . escapeshellarg( $url );
	}
}
