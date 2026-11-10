<?php
namespace Digitalisimo\Tools\Php_Snippets;
defined( 'ABSPATH' ) || exit;

/**
 * Guardián de los fragmentos (§6.2): atribuye un error fatal a un fragmento sólo con certeza y lo apaga con una sola fila
 * `INSERT IGNORE`, sin leer ni reescribir nada; el índice, el registro y el correo los concilia después Store::reconcile().
 *
 * Trabaja en dos sitios, porque cualquiera de los dos puede no llegar a correr:
 * - el filtro `wp_php_error_message` del manejador del núcleo (que se registró antes y puede acabar en un
 *   `wp_die_handler` ajeno que haga `exit`);
 * - un cierre propio, para cuando el filtro no corre (WP_SANDBOX_SCRAPING, mantenimiento, cabeceras enviadas fuera del
 *   escritorio o el drop-in `php-error.php`).
 * handle() es idempotente.
 *
 * Atribución, en este orden (una ruta de fragmento sólo cuenta si es del sitio o la red de origen y su `h12` es el de la
 * versión que corrió en esta petición):
 * 1. Guard::$running: el fragmento se estaba cargando (compilación, memoria y tiempo durante la carga).
 * 2. Redeclaración: Reflection sobre el símbolo que ya existía; nunca se lee la ubicación del mensaje.
 * 3. Memoria o tiempo fuera de la carga: sin culpable.
 * 4. `error['file']` es una ruta de fragmento.
 * 5. Throwable no atrapado lanzado en el núcleo: decide el primer marco que no es del núcleo.
 * 6. Cualquier otro caso: sin culpable.
 *
 * Modo prueba (lo arma el Runner con el contexto de Tester::claim): no escribe filas `off`; reúne avisos, redirección,
 * pie de página, fragmentos cargados, salida y errores, y al cerrar guarda el transitorio de resultado (§4.4).
 */
final class Guard {
	const FATAL     = array( E_ERROR, E_PARSE, E_USER_ERROR, E_COMPILE_ERROR, E_CORE_ERROR, E_RECOVERABLE_ERROR );
	const PATH      = '#^digitalisimo-php://(site|network)-(\d+)/([0-9a-f]{8})@([0-9a-f]{12})$#D';
	const REDECLARE = '/^Cannot (?:re)?declare (function |class |interface |trait |enum )?([A-Za-z_\x80-\xff\\\\][\w\x80-\xff\\\\]*)/';
	const NOTICES   = 20;

	/** Entrada que se está cargando ({ scope, id, title, hash, rev, candidate }, sin código), o null. */
	public static $running = null;

	private static $armed          = false;
	private static $handled        = false;
	private static $reserve        = '';
	private static $origin_blog    = 0;
	private static $origin_network = 0;
	/** Contexto de prueba, o null en producción. */
	private static $test = null;
	/** 'scope:id' → entrada cargada en esta petición. */
	private static $ran = array();
	/** Resultado de la prueba. */
	private static $result = array();
	private static $started = 0.0;
	private static $culprit = null;
	private static $previous_handler = null;

	/**
	 * Arma el guardián (sólo cuando hay algo que ejecutar): cierre propio, filtro del núcleo, reserva de 64 KB y el sitio y
	 * la red de origen. Con $test, entra en modo prueba y recoge las señales de la petición.
	 *
	 * @param array|null $test { token: sha256 del token (64 hex), kind: 'admin'|'front', store: 'site'|'network', user }
	 */
	public static function arm( $test = null ) {
		self::$origin_blog    = (int) get_current_blog_id();
		self::$origin_network = (int) get_current_network_id();
		self::$started        = microtime( true );
		self::$test           = ( is_array( $test ) && is_string( $test['token'] ?? null ) && preg_match( '/^[0-9a-f]{64}$/D', $test['token'] ) ) ? $test : null;
		if ( self::$armed ) return;
		self::$armed   = true;
		self::$reserve = str_repeat( ' ', 65536 );
		register_shutdown_function( array( __CLASS__, 'shutdown' ) );
		add_filter( 'wp_php_error_message', array( __CLASS__, 'message' ), 10, 2 );
		if ( self::$test ) self::collect();
	}

	/** Si el guardián está en modo prueba. */
	public static function testing() {
		return null !== self::$test;
	}

	/** El fragmento $e empieza a cargarse desde $path. */
	public static function enter( array $e, $path ) {
		unset( $e['code'] );
		$e['path']                                 = (string) $path;
		self::$running                             = $e;
		self::$ran[ $e['scope'] . ':' . $e['id'] ] = $e;
	}

	/** Terminó la carga del fragmento en curso. */
	public static function leave() {
		self::$running = null;
	}

	/**
	 * Un Throwable al cargarse: en producción escribe la fila `off` en ese momento (contexto normal, no el cierre) y el
	 * Runner sigue con el siguiente; en la prueba sólo se anota. Los ganchos que alcanzó a registrar siguen vivos.
	 *
	 * El culpable es el fragmento en cuyo código se lanzó: si $e, al cargarse, llama a un callback de otro fragmento ya
	 * cargado en esta petición y ése falla, se culpa al otro (owner()).
	 */
	public static function caught( array $e, $t ) {
		if ( ! $t instanceof \Throwable ) return;
		$e    = self::owner( $t, $e );
		$line = self::line_in( $t, $e );
		if ( self::$test ) {
			self::$result['caught'][] = array( 'scope' => $e['scope'], 'id' => $e['id'], 'hash' => $e['hash'], 'class' => get_class( $t ), 'message' => Store::one_line( $t->getMessage(), 500 ), 'line' => $line, 'file' => Store::one_line( $t->getFile(), 200 ) );
			return;
		}
		self::disable( $e, array( 'type' => E_ERROR, 'line' => $line, 'message' => get_class( $t ) . ': ' . $t->getMessage() ) );
	}

	/**
	 * Fragmento dueño de un Throwable lanzado mientras $e se cargaba: decide el primer marco que no es del núcleo (donde se
	 * lanzó, y si no la traza). Si es otro fragmento cargado en esta petición, ése; en cualquier otro caso (el propio $e, un
	 * plugin o un tema al que $e llamó), $e.
	 */
	private static function owner( \Throwable $t, array $e ) {
		$frames = array( array( 'file' => (string) $t->getFile() ) );
		foreach ( $t->getTrace() as $frame ) if ( isset( $frame['file'] ) ) $frames[] = array( 'file' => (string) $frame['file'] );
		foreach ( $frames as $frame ) {
			if ( self::is_core( $frame['file'] ) ) continue;
			$entry = self::entry_for( $frame['file'] );
			return $entry ? $entry : $e;
		}
		return $e;
	}

	/** El fragmento imprimió $bytes al cargarse: en producción se descarta; en la prueba hace fallar. */
	public static function output( array $e, $bytes ) {
		if ( ! self::$test ) return;
		self::$result['output']       += (int) $bytes;
		self::$result['output_ids'][] = $e['id'];
	}

	/** El fragmento dejó o cerró búferes de salida: no se toca nada; la prueba lo avisa sin fallar. */
	public static function buffer_left( array $e ) {
		if ( self::$test ) self::$result['buffer_left'][] = $e['id'];
	}

	/** El fragmento terminó de cargarse: la prueba exige el hash de cada candidato en `loaded`. */
	public static function loaded( array $e ) {
		if ( self::$test ) self::$result['loaded'][] = array( 'scope' => $e['scope'], 'id' => $e['id'], 'hash' => $e['hash'] );
	}

	/** Resultado de la prueba en curso (lo que se guardará en el transitorio). */
	public static function result() {
		return self::$result;
	}

	/** Fragmento al que se atribuyó el fatal de esta petición: { entry, line, how }, o null. */
	public static function culprit() {
		return self::$culprit;
	}

	/* ------------------------------------------------------------------ *
	 * Señales de la prueba
	 * ------------------------------------------------------------------ */

	private static function collect() {
		self::$result = array( 'kind' => (string) ( self::$test['kind'] ?? '' ), 'status' => 0, 'fatal' => null, 'caught' => array(), 'output' => 0, 'output_ids' => array(), 'buffer_left' => array(), 'footer' => '', 'loaded' => array(), 'user' => 0, 'notices' => array(), 'redirect' => '', 'redirect_status' => 0, 'redirect_login' => false, 'ms' => 0 );
		self::$previous_handler = set_error_handler( array( __CLASS__, 'notice' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		add_filter( 'wp_redirect', array( __CLASS__, 'redirect' ), PHP_INT_MAX, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ), PHP_INT_MAX );
		add_action( 'admin_footer', array( __CLASS__, 'footer' ), PHP_INT_MAX );
	}

	/** Manejador encadenado: anota hasta 20 avisos de archivos `digitalisimo-php://` y deja seguir al anterior o al de PHP. */
	public static function notice( $type, $message, $file = '', $line = 0 ) {
		if ( is_string( $file ) && 0 === strpos( $file, Stream::SCHEME . '://' ) && ( error_reporting() & $type ) && count( self::$result['notices'] ?? array() ) < self::NOTICES ) {
			self::$result['notices'][] = array( 'type' => (int) $type, 'message' => Store::one_line( $message, 300 ), 'file' => $file, 'line' => (int) $line );
		}
		return self::$previous_handler ? call_user_func( self::$previous_handler, $type, $message, $file, $line ) : false;
	}

	public static function redirect( $location, $status = 302 ) {
		if ( self::$test ) {
			self::$result['redirect']        = Store::one_line( (string) $location, 500 );
			self::$result['redirect_status'] = (int) $status;
			self::$result['redirect_login']  = self::is_login( (string) $location );
		}
		return $location;
	}

	/** Si $location es el acceso del sitio: wp-login.php o la ruta de wp_login_url() (la ruta privada de Ajustes del sitio). */
	private static function is_login( $location ) {
		$path = (string) parse_url( $location, PHP_URL_PATH );
		if ( '' === $path ) return false;
		if ( 'wp-login.php' === basename( $path ) ) return true;
		$login = function_exists( 'wp_login_url' ) ? (string) parse_url( (string) wp_login_url(), PHP_URL_PATH ) : '';
		return '' !== $login && rtrim( $login, '/' ) === rtrim( $path, '/' );
	}

	public static function footer() {
		if ( self::$test ) self::$result['footer'] = function_exists( 'current_filter' ) ? (string) current_filter() : 'footer';
	}

	/* ------------------------------------------------------------------ *
	 * Cierre y filtro del núcleo
	 * ------------------------------------------------------------------ */

	public static function shutdown() {
		$error = error_get_last();
		if ( is_array( $error ) && in_array( (int) ( $error['type'] ?? 0 ), self::FATAL, true ) ) self::handle( $error );
		if ( self::$test ) self::write_result();
	}

	/**
	 * Filtro `wp_php_error_message`: atiende el error antes que un `wp_die_handler` ajeno y, si hay sesión con can_stop,
	 * dice qué fragmento lo provocó. En los endpoints protegidos sustituye la promesa de un correo del núcleo, que aquí no
	 * llegaría. Los visitantes ven el mensaje genérico.
	 */
	public static function message( $message, $error = array() ) {
		try {
			$found = self::handle( is_array( $error ) ? $error : array() );
			if ( ! $found || self::$test || ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() || ! Access::can_stop() ) return $message;
			$title = (string) ( $found['entry']['title'] ?? '' );
			$text  = 'network' === $found['entry']['scope']
				? sprintf( 'Un fragmento PHP de DIGITALÍSIMO Tools («%s») provocó el error y quedó pausado en este sitio. Recarga la página.', $title )
				: sprintf( 'Un fragmento PHP de DIGITALÍSIMO Tools («%s») provocó el error y quedó desactivado. Recarga la página.', $title );
			$html  = '<p>' . esc_html( $text ) . '</p>';
			return ( function_exists( 'is_protected_endpoint' ) && is_protected_endpoint() ) ? $html : $message . $html;
		} catch ( \Throwable $t ) {
			return $message;
		}
	}

	/**
	 * Atiende un error fatal una sola vez: libera la reserva, sube la memoria si se agotó, vuelve al sitio de origen,
	 * atribuye y, en producción, escribe la fila `off` del culpable.
	 *
	 * @return array|null { entry, line, how } del culpable.
	 */
	public static function handle( $error ) {
		if ( ! self::$armed || self::$handled || ! is_array( $error ) || ! in_array( (int) ( $error['type'] ?? 0 ), self::FATAL, true ) ) return self::$culprit;
		self::$handled = true;
		self::$reserve = '';
		$message = (string) ( $error['message'] ?? '' );
		if ( self::is_memory( $message ) ) self::raise_memory();
		self::restore_origin();
		$found         = self::attribute( $error );
		self::$culprit = $found;
		if ( self::$test ) {
			self::$result['fatal'] = array(
				'attributed' => (bool) $found,
				'scope'      => $found ? $found['entry']['scope'] : '',
				'id'         => $found ? $found['entry']['id'] : '',
				'type'       => (int) $error['type'],
				'message'    => Store::one_line( self::first_line( $message ), 500 ),
				'line'       => $found ? (int) $found['line'] : (int) ( $error['line'] ?? 0 ),
				'file'       => Store::one_line( (string) ( $error['file'] ?? '' ), 200 ),
			);
			return $found;
		}
		if ( $found ) self::disable( $found['entry'], array( 'type' => (int) $error['type'], 'line' => (int) $found['line'], 'message' => $message ) );
		return $found;
	}

	/* ------------------------------------------------------------------ *
	 * Atribución
	 * ------------------------------------------------------------------ */

	/**
	 * Culpable de un error con la forma de error_get_last(), o null si no hay certeza. No escribe nada.
	 *
	 * @return array|null { entry, line, how: running|redeclare|file|trace }
	 */
	public static function attribute( array $error ) {
		$type    = (int) ( $error['type'] ?? 0 );
		$message = (string) ( $error['message'] ?? '' );
		$file    = (string) ( $error['file'] ?? '' );
		$line    = (int) ( $error['line'] ?? 0 );
		if ( self::$running ) return array( 'entry' => self::$running, 'line' => $file === self::$running['path'] ? $line : 0, 'how' => 'running' );
		if ( ( E_COMPILE_ERROR === $type || E_ERROR === $type ) && preg_match( self::REDECLARE, $message, $m ) && '::' !== substr( $message, strlen( $m[0] ), 2 ) ) {
			$found = self::declared_in( $m[2], trim( $m[1] ) );
			if ( $found ) return $found;
		}
		if ( self::is_memory( $message ) || 0 === strpos( $message, 'Maximum execution time' ) ) return null;
		if ( $entry = self::entry_for( $file ) ) return array( 'entry' => $entry, 'line' => $line, 'how' => 'file' );
		if ( 0 === strpos( $message, 'Uncaught ' ) && self::is_core( $file ) ) {
			foreach ( self::frames( $error ) as $frame ) {
				if ( self::is_core( $frame['file'] ) ) continue;
				$entry = self::entry_for( $frame['file'] );
				return $entry ? array( 'entry' => $entry, 'line' => $frame['line'], 'how' => 'trace' ) : null;
			}
		}
		return null;
	}

	/** Pregunta a Reflection dónde se declaró el símbolo que ya existía; sólo cuenta si es una ruta de fragmento válida. */
	private static function declared_in( $name, $kind ) {
		$name = ltrim( (string) $name, '\\' );
		try {
			$class = static function () use ( $name ) {
				return ( class_exists( $name, false ) || interface_exists( $name, false ) || trait_exists( $name, false ) ) ? new \ReflectionClass( $name ) : null;
			};
			$function = static function () use ( $name ) {
				return function_exists( $name ) ? new \ReflectionFunction( $name ) : null;
			};
			$reflection = 'function' === $kind ? $function() : ( '' === $kind ? ( $function() ?: $class() ) : $class() );
			if ( ! $reflection ) return null;
			$entry = self::entry_for( (string) $reflection->getFileName() );
			return $entry ? array( 'entry' => $entry, 'line' => (int) $reflection->getStartLine(), 'how' => 'redeclare' ) : null;
		} catch ( \Throwable $t ) {
			return null;
		}
	}

	/** Entrada cargada en esta petición a la que corresponde $path, o null (ruta ajena, de otro sitio o red, o `h12` viejo). */
	public static function entry_for( $path ) {
		if ( ! is_string( $path ) || ! preg_match( self::PATH, $path, $m ) ) return null;
		if ( (int) $m[2] !== ( 'site' === $m[1] ? self::$origin_blog : self::$origin_network ) ) return null;
		$key = $m[1] . ':' . $m[3];
		if ( ! isset( self::$ran[ $key ] ) || Store::h12( self::$ran[ $key ]['hash'] ) !== $m[4] ) return null;
		return self::$ran[ $key ];
	}

	/**
	 * Marcos de la traza: de `error['trace']` (PHP ≥ 8.5) o del texto que sigue al último "\nStack trace:\n". Un mensaje con
	 * un «Stack trace» falso queda antes del bloque real, y los argumentos se recortan, así que no se puede fabricar un marco.
	 *
	 * @return array [ { file, line } ], del más interno al más externo.
	 */
	public static function frames( array $error ) {
		$frames = array();
		if ( isset( $error['trace'] ) && is_array( $error['trace'] ) && $error['trace'] ) {
			foreach ( $error['trace'] as $frame ) if ( is_array( $frame ) && isset( $frame['file'] ) ) $frames[] = array( 'file' => (string) $frame['file'], 'line' => (int) ( $frame['line'] ?? 0 ) );
			return $frames;
		}
		$message = (string) ( $error['message'] ?? '' );
		// Antes de PHP 8, error_get_last() recorta el mensaje a log_errors_max_len: si llegó recortado, el bloque real de la
		// traza puede faltar y el último «Stack trace» sería texto del mensaje (de un visitante). Sin traza fiable, sin culpable.
		if ( PHP_VERSION_ID < 80000 ) {
			$max = (int) ini_get( 'log_errors_max_len' );
			if ( $max > 0 && strlen( $message ) >= $max - 1 ) return $frames;
		}
		$pos = strrpos( $message, "\nStack trace:\n" );
		if ( false === $pos ) return $frames;
		foreach ( explode( "\n", substr( $message, $pos + 14 ) ) as $row ) {
			if ( ! preg_match( '/^#\d+ /', $row ) ) break;
			if ( preg_match( '/^#\d+ \[internal function\]: /', $row ) || ! preg_match( '/^#\d+ (.+?)\((\d+)\): /', $row, $m ) ) continue;
			$frames[] = array( 'file' => $m[1], 'line' => (int) $m[2] );
		}
		return $frames;
	}

	/** Archivo del núcleo: wp-includes, wp-admin o un archivo de la raíz de WordPress. */
	public static function is_core( $file ) {
		$file = (string) $file;
		if ( '' === $file || ! defined( 'ABSPATH' ) || 0 !== strpos( $file, ABSPATH ) ) return false;
		$inc = defined( 'WPINC' ) ? WPINC : 'wp-includes';
		return 0 === strpos( $file, ABSPATH . $inc . '/' ) || 0 === strpos( $file, ABSPATH . 'wp-admin/' ) || false === strpos( substr( $file, strlen( ABSPATH ) ), '/' );
	}

	/** Línea del fragmento en un Throwable: la suya si se lanzó ahí, o la del primer marco que pase por él. */
	private static function line_in( \Throwable $t, array $e ) {
		$path = Stream::path( $e['scope'], $e['id'], $e['hash'] );
		if ( $t->getFile() === $path ) return (int) $t->getLine();
		foreach ( $t->getTrace() as $frame ) if ( ( $frame['file'] ?? '' ) === $path ) return (int) ( $frame['line'] ?? 0 );
		return 0;
	}

	/* ------------------------------------------------------------------ *
	 * Escritura
	 * ------------------------------------------------------------------ */

	/** Una sola fila `INSERT IGNORE` en la tabla del sitio de origen: `off_s_<id>_<h12>` o `off_n_<id>_<h12>_<rev>` (D3). */
	private static function disable( array $e, array $info ) {
		$value = array( 't' => time(), 'type' => (int) $info['type'], 'line' => (int) $info['line'], 'msg' => Store::one_line( self::first_line( $info['message'] ), 500 ), 'path' => self::request_path() );
		Store::insert_row( self::$origin_blog, Store::off_name( $e['scope'], $e['id'], $e['hash'], (int) ( $e['rev'] ?? 0 ) ), $value, true );
	}

	private static function write_result() {
		try {
			self::restore_origin();
			$result           = self::$result;
			$result['status'] = function_exists( 'http_response_code' ) ? (int) http_response_code() : 0;
			$result['ms']     = (int) round( ( microtime( true ) - self::$started ) * 1000 );
			$result['user']   = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
			$name             = Store::TRANSIENT . 'result_' . self::$test['token'];
			if ( 'network' === ( self::$test['store'] ?? 'site' ) && is_multisite() ) set_site_transient( $name, $result, 300 );
			else set_transient( $name, $result, 300 );
		} catch ( \Throwable $t ) {
			unset( $t ); // Sin resultado la prueba falla («no terminó»): nunca se activa a ciegas.
		}
	}

	/** Ruta de la URL sin la consulta, sin caracteres de control y con 200 caracteres como mucho. */
	public static function request_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( '' === $uri ) return ( defined( 'WP_CLI' ) && WP_CLI ) ? 'wp-cli' : ( ( defined( 'DOING_CRON' ) && DOING_CRON ) ? 'cron' : '' );
		$path = explode( '?', explode( '#', $uri, 2 )[0], 2 )[0];
		return substr( (string) preg_replace( '/[\x00-\x1F\x7F]+/', '', $path ), 0, 200 );
	}

	/** La parte del mensaje antes de la traza. */
	private static function first_line( $message ) {
		$message = (string) $message;
		$pos     = strpos( $message, "\nStack trace:" );
		return false === $pos ? $message : substr( $message, 0, $pos );
	}

	private static function is_memory( $message ) {
		return 0 === strpos( $message, 'Allowed memory size' ) || 0 === strpos( $message, 'Out of memory' );
	}

	/** Sube memory_limit 64 MB para poder terminar la escritura tras agotar la memoria. */
	private static function raise_memory() {
		$limit = (string) ini_get( 'memory_limit' );
		if ( '-1' === $limit ) return;
		$bytes = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( $limit ) : (int) $limit;
		@ini_set( 'memory_limit', (string) ( max( $bytes, memory_get_usage( true ) ) + 67108864 ) ); // phpcs:ignore
	}

	/** Si el fatal ocurrió dentro de un switch_to_blog(), vuelve al sitio de origen: nunca se escribe en otro sitio. */
	private static function restore_origin() {
		if ( ! function_exists( 'get_current_blog_id' ) ) return;
		$guard = 100;
		while ( function_exists( 'ms_is_switched' ) && ms_is_switched() && (int) get_current_blog_id() !== self::$origin_blog && $guard-- > 0 ) restore_current_blog();
		if ( (int) get_current_blog_id() !== self::$origin_blog && function_exists( 'switch_to_blog' ) && is_multisite() ) switch_to_blog( self::$origin_blog );
	}
}
