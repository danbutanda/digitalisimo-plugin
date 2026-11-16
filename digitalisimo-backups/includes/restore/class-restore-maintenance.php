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
 *  - quien trae el testigo de la restauración (cookie `digitalisimo_backups_restore`, que recibe sólo el navegador que
 *    la empezó, o el parámetro del mismo nombre: un cliente de REST), y sólo a la pantalla de Backups y sus llamadas
 *    (las acciones de AJAX y admin-post que registra Backups, su REST) y a wp-login.php para entrar y salir: nunca el
 *    resto del escritorio ni el sitio (revisión N1, verificación final N9); en el archivo sólo va su SHA-256;
 *  - WP-CLI (la vía para terminar o recuperar a mano);
 *  - la lectura del estado de ese trabajo con su ficha de lectura (cabecera X-Digitalisimo-Backups-Job-Token, sólo
 *    `GET /wp-json/digitalisimo-backups/v1/jobs/<id>`; en el archivo sólo va su SHA-256): carga WordPress mínimo y el
 *    plugin sólo deja esa ruta (Digitalisimo_Backups_Rest::reader_only()); como una visita, despierta la restauración.
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
		return self::ours( $path ) && false !== strpos( (string) @file_get_contents( $path, false, null, 0, 65536 ), "'until_done' => true" );
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
	public static function hold( $token, $until_done, $job_id = '', array $readers = array() ) {
		$path = self::path();
		if ( is_link( $path ) || ( is_file( $path ) && ! self::ours( $path ) ) ) return false;
		$content = self::content( (string) $token, (bool) $until_done, (string) $job_id, $readers );
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
	private static function content( $token, $until_done, $job_id, array $readers = array() ) {
		$cron = function_exists( 'site_url' ) ? (string) site_url( 'wp-cron.php' ) : '';
		$config = array(
			'hash' => hash( 'sha256', $token ),
			// Las fichas de lectura del trabajo (sólo sus SHA-256): dejan pasar la consulta de su estado, nada más.
			'readers' => array_values( array_filter( array_map( 'strval', $readers ), function( $hash ) { return (bool) preg_match( '/^[0-9a-f]{64}$/D', $hash ); } ) ),
			// La ruta pública del sitio (vacía en la raíz, «/wp» en una carpeta): ancla la ruta REST que lee la ficha.
			'home_path' => rtrim( function_exists( 'home_url' ) ? (string) parse_url( (string) home_url(), PHP_URL_PATH ) : '', '/' ),
			'until_done' => $until_done,
			'plugin' => self::plugin(),
			'cron' => $cron,
			'every' => self::KICK_EVERY,
			// Las acciones de AJAX y admin-post que registra Backups: con el testigo, sólo ésas (verificación final).
			'actions' => self::screen_actions(),
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
		// Con el testigo, sólo la pantalla de Backups y sus llamadas: el resto del escritorio o del sitio guardaría en la base
		// de datos que el cambio de tablas va a reemplazar, o se serviría a medio cambiar (revisión N1).
		$known = $known && digitalisimo_backups_restore_screen( $script, $config );
		// Para entrar durante el mantenimiento basta wp-login.php: sin la confirmación periódica del correo del administrador,
		// que es otra acción (verificación final N9).
		if ( $known && 'wp-login.php' === $script && function_exists( 'add_filter' ) ) add_filter( 'admin_email_check_interval', '__return_zero', PHP_INT_MAX );
		$cli = defined( 'WP_CLI' ) && WP_CLI;
		$ours = $cli && ! empty( $GLOBALS['argv'] ) && is_array( $GLOBALS['argv'] ) && in_array( 'digitalisimo-backups', $GLOBALS['argv'], true );
		if ( $cron || ( $known && ( $tick || $config['until_done'] ) ) || ( $ours && $config['until_done'] ) ) digitalisimo_backups_restore_minimal( (string) $config['plugin'] );
		if ( $cli || $cron || $known ) return true;
		// La ficha de lectura del trabajo: sólo GET de su estado por la REST, con WordPress mínimo; también lo despierta.
		$reader = isset( $_SERVER['HTTP_X_DIGITALISIMO_BACKUPS_JOB_TOKEN'] ) && is_string( $_SERVER['HTTP_X_DIGITALISIMO_BACKUPS_JOB_TOKEN'] ) ? $_SERVER['HTTP_X_DIGITALISIMO_BACKUPS_JOB_TOKEN'] : '';
		if ( '' !== $reader && ! empty( $config['readers'] ) && in_array( hash( 'sha256', $reader ), (array) $config['readers'], true ) && digitalisimo_backups_restore_reading( isset( $config['home_path'] ) ? (string) $config['home_path'] : '' ) ) {
			digitalisimo_backups_restore_minimal( (string) $config['plugin'] );
			if ( ! defined( 'DIGITALISIMO_BACKUPS_RESTORE_READER' ) ) define( 'DIGITALISIMO_BACKUPS_RESTORE_READER', true );
			digitalisimo_backups_restore_kick( (string) $config['cron'], (int) $config['every'] );
			return true;
		}
		digitalisimo_backups_restore_kick( (string) $config['cron'], (int) $config['every'] );
		// La página de mantenimiento (503) de una restauración de Backups lo dice en una cabecera propia: la pantalla sólo
		// atribuye a una restauración el 503 que la trae (revisión N2).
		$GLOBALS['digitalisimo_backups_restore_blocked'] = true;
		if ( ! headers_sent() ) header( 'X-Digitalisimo-Backups-Restore: maintenance' );
		return false;
	}
	// La pantalla de Backups (admin.php?page=… del escritorio o de la red) y sus llamadas: admin-ajax.php y admin-post.php
	// con una de las acciones que registra Backups, su REST, y wp-login.php sólo para entrar y salir (sin la sesión, la
	// pantalla lleva ahí, y «Volver a la copia previa» la necesita; verificación final N9).
	function digitalisimo_backups_restore_screen( $script, $config ) {
		$home = isset( $config['home_path'] ) ? (string) $config['home_path'] : '';
		$path = isset( $_SERVER['SCRIPT_FILENAME'] ) ? realpath( dirname( (string) $_SERVER['SCRIPT_FILENAME'] ) ) : false;
		$admin = realpath( rtrim( ABSPATH, '/\\' ) . '/wp-admin' );
		$in_admin = false !== $path && false !== $admin && ( $path === $admin || ( dirname( $path ) === $admin && in_array( basename( $path ), array( 'network', 'user' ), true ) ) );
		$value = function( $source, $key ) { return isset( $source[ $key ] ) && is_string( $source[ $key ] ) ? $source[ $key ] : ''; };
		if ( 'admin-ajax.php' === $script || 'admin-post.php' === $script ) return $in_admin && in_array( $value( $_REQUEST, 'action' ), (array) ( isset( $config['actions'] ) ? $config['actions'] : array() ), true );
		if ( 'admin.php' === $script ) return $in_admin && in_array( $value( $_GET, 'page' ), array( 'digitalisimo-backups', 'digitalisimo-network-backups' ), true );
		if ( 'wp-login.php' === $script ) return false !== $path && realpath( ABSPATH ) === $path && in_array( $value( $_REQUEST, 'action' ), array( '', 'login', 'logout' ), true );
		return digitalisimo_backups_restore_rest( $home, '/digitalisimo-backups/v1(?:/.*)?' );
	}
	function digitalisimo_backups_restore_reading( $home ) {
		if ( 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : '' ) ) return false;
		return digitalisimo_backups_restore_rest( $home, '/digitalisimo-backups/v1/jobs/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/?' );
	}
	// Una ruta REST ($route, expresión regular) por el index.php de WordPress.
	function digitalisimo_backups_restore_rest( $home, $route ) {
		// Sólo el index.php de WordPress (el de su carpeta o, con WordPress en su propio directorio, el de una carpeta por
		// encima), sin PATH_INFO: ni wp-login.php/…, ni admin-ajax.php, ni wp-admin/index.php/… (revisión B2).
		$script = isset( $_SERVER['SCRIPT_FILENAME'] ) ? str_replace( '\\', '/', (string) $_SERVER['SCRIPT_FILENAME'] ) : '';
		if ( 'index.php' !== basename( $script ) || ! empty( $_SERVER['PATH_INFO'] ) || ! empty( $_SERVER['ORIG_PATH_INFO'] ) ) return false;
		$dir = realpath( dirname( $script ) );
		$root = realpath( ABSPATH );
		if ( false === $dir || false === $root ) return false;
		$dir = rtrim( str_replace( '\\', '/', $dir ), '/' ) . '/';
		$root = rtrim( str_replace( '\\', '/', $root ), '/' ) . '/';
		if ( 0 !== strpos( $root, $dir ) || false !== strpos( substr( $root, strlen( $dir ) ), 'wp-admin/' ) ) return false;
		$path = (string) parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH );
		$home = rtrim( (string) $home, '/' );
		// rest_route de la cadena de consulta manda sobre la ruta de /wp-json/ (WP::parse_request): si está, es la que
		// cuenta, y sólo en la portada del sitio (<home>/ o <home>/index.php).
		if ( isset( $_GET['rest_route'] ) ) return in_array( $path, array( $home . '/', $home . '/index.php', '' === $home ? '/' : $home ), true ) && is_string( $_GET['rest_route'] ) && (bool) preg_match( '#^' . $route . '$#D', $_GET['rest_route'] );
		return (bool) preg_match( '#^' . preg_quote( $home, '#' ) . '/wp-json' . $route . '$#D', $path );
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
		// Un hosting que necesita sus mu-plugins para cargar WordPress (o un ensayo) lo dice en wp-config.php.
		if ( ! defined( 'WPMU_PLUGIN_DIR' ) && ! ( defined( 'DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS' ) && DIGITALISIMO_BACKUPS_RESTORE_MU_PLUGINS ) ) define( 'WPMU_PLUGIN_DIR', $none );
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
	/**
	 * Las acciones de admin-ajax.php y admin-post.php que registra Backups (sus funciones son de una clase
	 * Digitalisimo_Backups…), además de las que registra siempre: la puerta las compara con la lista exacta, no por prefijo.
	 */
	public static function screen_actions() {
		$out = array();
		foreach ( array( Digitalisimo_Backups_Jobs::AJAX_TICK, Digitalisimo_Backups_Jobs::AJAX_STATUS, Digitalisimo_Backups_Jobs::ACTION_CANCEL, Digitalisimo_Backups_Jobs::AJAX_RESTORE_TICK, Digitalisimo_Backups::ACTION_RESTORE, Digitalisimo_Backups::ACTION_MANUAL_START, Digitalisimo_Backups::ACTION_MANUAL_WORK, Digitalisimo_Backups::ACTION_MANUAL_STATUS ) as $action ) $out[ $action ] = true;
		foreach ( (array) ( $GLOBALS['wp_filter'] ?? array() ) as $hook => $wp_hook ) {
			if ( ! preg_match( '/^(?:wp_ajax_(?:nopriv_)?|admin_post_(?:nopriv_)?)(digitalisimo_backups_[a-z0-9_]+)$/D', (string) $hook, $m ) || ! is_object( $wp_hook ) || ! isset( $wp_hook->callbacks ) ) continue;
			foreach ( (array) $wp_hook->callbacks as $callbacks ) foreach ( (array) $callbacks as $callback ) {
				$function = is_array( $callback ) ? ( $callback['function'] ?? null ) : null;
				$class = is_array( $function ) && isset( $function[0] ) ? ( is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0] ) : '';
				if ( 0 === strpos( $class, 'Digitalisimo_Backups' ) ) $out[ $m[1] ] = true;
			}
		}
		ksort( $out );
		return array_keys( $out );
	}
	/**
	 * El mantenimiento sigue puesto para el testigo $token aunque su restauración ya no esté en curso (un sitio a medio
	 * cambiar, tras una vuelta a la copia previa que también falló): quien lo trae lo necesita para llegar a Backups y
	 * volver a la copia previa.
	 */
	public static function held_for( $token ) {
		$path = self::path();
		if ( ! is_string( $token ) || '' === $token || ! self::ours( $path ) ) return false;
		return false !== strpos( (string) @file_get_contents( $path, false, null, 0, 65536 ), "'hash' => '" . hash( 'sha256', $token ) . "'" );
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
	// Sólo la cookie o el POST de la página (nunca la URL: el testigo del tick de WP-Cron queda en los registros de acceso).
	public static function request_has_page( $token ) {
		if ( '' === (string) $token ) return false;
		foreach ( array( $_COOKIE[ self::COOKIE ] ?? null, $_POST[ self::PARAM ] ?? null ) as $value ) {
			if ( is_string( $value ) && '' !== $value && hash_equals( hash( 'sha256', (string) $token ), hash( 'sha256', $value ) ) ) return true;
		}
		return false;
	}
	// Lo último que esta petición mandó en la cookie (el testigo, '' si la borró, null si nada) y hasta cuándo vale: lo
	// comprueban las pruebas.
	public static $sent = null;
	public static $sent_until = null;
	// Lo que dura la cookie (48 horas, docs/BACKUPS.md): cada tick de esa restauración la renueva; al terminar se borra.
	const COOKIE_TTL = 172800;
	/** Cookie para el navegador que empezó la restauración: con ella ve la pantalla y la hace avanzar durante el mantenimiento. */
	public static function send_cookie( $token, $expires = null ) {
		self::$sent = '' === (string) $token ? '' : (string) $token;
		$expires = null === $expires ? time() + self::COOKIE_TTL : (int) $expires;
		self::$sent_until = $expires;
		if ( headers_sent() || ! function_exists( 'setcookie' ) ) return false;
		$secure = function_exists( 'is_ssl' ) && is_ssl();
		$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		if ( PHP_VERSION_ID >= 70300 ) return setcookie( self::COOKIE, (string) $token, array( 'expires' => $expires, 'path' => $path, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax' ) );
		return setcookie( self::COOKIE, (string) $token, $expires, $path . '; samesite=Lax', '', $secure, true );
	}
	// La borra (caducada): al cerrar sesión, si la restauración no llegó a empezar o terminó y ya no hace falta.
	public static function clear_cookie() {
		if ( null === self::$sent && ! isset( $_COOKIE[ self::COOKIE ] ) ) return false;
		return self::send_cookie( '', time() - 3600 );
	}
	/** Línea para el cron del sistema de un hosting sin visitas: despierta la restauración cada minuto (sin testigo). */
	public static function system_cron_line() {
		$url = function_exists( 'site_url' ) ? (string) site_url( 'wp-cron.php' ) : 'https://example.com/wp-cron.php';
		return '* * * * * curl -s -o /dev/null ' . escapeshellarg( $url );
	}
}
