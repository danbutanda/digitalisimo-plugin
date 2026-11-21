<?php
/**
 * Sonda del escritorio (mu-plugin de admin-perf-all.sh). Con todos los plugins activos en la base, la cookie
 * `digi_ap_set` deja activo en cada petición sólo un conjunto (ninguno, uno, los propios de la rama o todos) y elige el
 * tema (DIGITALÍSIMO o el predeterminado), así que el mismo sitio se mide con y sin cada pieza sin reiniciar.
 *
 * Por petición anota en `metrics.jsonl`: tiempo (desde `$timestart` de WordPress), hitos de la carga, consultas, memoria
 * pico, peticiones HTTP salientes (con quién las hizo), avisos de PHP, scripts y hojas encolados de cada plugin y, con
 * `digi_ap_prof=1`, el tiempo de cada callback de los plugins y las consultas de cada uno. Las peticiones a GitHub y a
 * api.wordpress.org se responden con datos fijos (sin red) y cualquier otra salida se corta.
 */
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DIGI_AP_DIR' ) ) define( 'DIGI_AP_DIR', '/wordpress/wp-content/probe' );

/** Carpetas de los plugins y del tema medidos (carpeta en los sitios). */
function digi_ap_owned() {
	return array(
		'backups' => 'plugins/digitalisimo-backups/',
		'tools' => 'plugins/digitalisimo-tools/',
		'seo' => 'plugins/digitalisimo-seo/',
		'ia' => 'plugins/digitalisimo.chatbot/',
		'hosting' => 'plugins/digitalisimo-hosting/',
		'elements' => 'plugins/digitalisimo-elements/',
		'migration' => 'plugins/digitalisimo-migration/',
		'theme' => 'themes/digitalisimo-theme/',
	);
}
function digi_ap_plugin_files() {
	return array(
		'backups' => 'digitalisimo-backups/digitalisimo-backups.php',
		'tools' => 'digitalisimo-tools/digitalisimo-tools.php',
		'seo' => 'digitalisimo-seo/digitalisimo-integrations.php',
		'ia' => 'digitalisimo.chatbot/digitalisimo-chatbot.php',
		'hosting' => 'digitalisimo-hosting/digitalisimo-hosting.php',
		'elements' => 'digitalisimo-elements/pro-elements.php',
		'migration' => 'digitalisimo-migration/digitalisimo-migration.php',
	);
}
/** Conjuntos: nombre => piezas. Migración necesita Elements (Requires Plugins). */
function digi_ap_sets() {
	$all = array( 'backups', 'tools', 'seo', 'ia', 'hosting', 'elements', 'migration', 'theme' );
	return array(
		'none' => array(),
		'backups' => array( 'backups' ),
		'tools' => array( 'tools' ),
		'theme' => array( 'theme' ),
		'seo' => array( 'seo' ),
		'ia' => array( 'ia' ),
		'hosting' => array( 'hosting' ),
		'elements' => array( 'elements' ),
		'migration' => array( 'elements', 'migration' ),
		'mine' => array( 'backups', 'tools', 'theme' ),
		// Los siete plugins sin el tema (para separar lo de los plugins de lo del tema, fijado a su ZIP).
		'plugins' => array_values( array_diff( $all, array( 'theme' ) ) ),
		'all' => $all,
	);
}
function digi_ap_set() {
	static $set = null;
	if ( null !== $set ) return $set;
	$name = isset( $_COOKIE['digi_ap_set'] ) ? preg_replace( '/[^a-z]/', '', (string) $_COOKIE['digi_ap_set'] ) : '';
	if ( isset( $_SERVER['HTTP_X_DIGI_AP_SET'] ) ) $name = preg_replace( '/[^a-z]/', '', (string) $_SERVER['HTTP_X_DIGI_AP_SET'] );
	$sets = digi_ap_sets();
	$set = isset( $sets[ $name ] ) ? array( $name, $sets[ $name ] ) : array( '', null );
	return $set;
}

/* -------------------------------------------------------------- Conjunto activo */
list( $digi_ap_name, $digi_ap_parts ) = digi_ap_set();
if ( null !== $digi_ap_parts ) {
	$digi_ap_files = array();
	foreach ( digi_ap_plugin_files() as $key => $file ) if ( in_array( $key, $digi_ap_parts, true ) ) $digi_ap_files[] = $file;
	add_filter( 'option_active_plugins', static function ( $plugins ) use ( $digi_ap_files ) {
		$others = array_filter( (array) $plugins, static function ( $p ) { return ! in_array( $p, digi_ap_plugin_files(), true ); } );
		return array_values( array_merge( $others, array_intersect( digi_ap_plugin_files(), $digi_ap_files ) ) );
	}, 1 );
	add_filter( 'site_option_active_sitewide_plugins', static function ( $plugins ) use ( $digi_ap_files ) {
		$out = array();
		foreach ( (array) $plugins as $file => $time ) if ( ! in_array( $file, digi_ap_plugin_files(), true ) || in_array( $file, $digi_ap_files, true ) ) $out[ $file ] = $time;
		return $out;
	}, 1 );
	if ( ! in_array( 'theme', $digi_ap_parts, true ) ) {
		$digi_ap_theme = static function ( $value ) { return 'digitalisimo-theme' === $value ? 'twentytwentyfive' : $value; };
		add_filter( 'option_template', $digi_ap_theme, 1 );
		add_filter( 'option_stylesheet', $digi_ap_theme, 1 );
	}
}
unset( $digi_ap_name, $digi_ap_parts, $digi_ap_files, $digi_ap_theme );

/* -------------------------------------------------------------- Recogida */
$GLOBALS['digi_ap'] = array(
	'marks' => array(),
	'http' => array(),
	'errors' => array(),
	'queries' => 0,
	'by_owner' => array(),
	'cb' => array(),
);
function digi_ap_now() { return round( ( microtime( true ) - ( isset( $GLOBALS['timestart'] ) ? $GLOBALS['timestart'] : $_SERVER['REQUEST_TIME_FLOAT'] ) ) * 1000, 2 ); }
function digi_ap_profiling() { return ! empty( $_COOKIE['digi_ap_prof'] ); }
/** Dueño de una ruta de archivo (clave de digi_ap_owned()), '' si no es de los medidos. */
function digi_ap_owner_of( $file ) {
	$file = str_replace( '\\', '/', (string) $file );
	foreach ( digi_ap_owned() as $key => $dir ) if ( false !== strpos( $file, '/wp-content/' . $dir ) ) return $key;
	if ( false !== strpos( $file, '/wp-content/plugins/' ) ) {
		$rest = substr( $file, strpos( $file, '/wp-content/plugins/' ) + 20 );
		return 'other:' . strtok( $rest, '/' );
	}
	if ( false !== strpos( $file, '/wp-content/themes/' ) ) return 'other-theme';
	return '';
}
/** Dueño de la llamada en curso: el marco más interno que sea de un plugin o tema (sin contar esta sonda). */
function digi_ap_caller() {
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
		if ( empty( $frame['file'] ) || false !== strpos( $frame['file'], '/mu-plugins/' ) || false !== strpos( $frame['file'], '/probe/' ) ) continue;
		$owner = digi_ap_owner_of( $frame['file'] );
		if ( '' !== $owner ) return $owner;
	}
	return 'core';
}
function digi_ap_mark( $name ) { if ( ! isset( $GLOBALS['digi_ap']['marks'][ $name ] ) ) $GLOBALS['digi_ap']['marks'][ $name ] = digi_ap_now(); }
foreach ( array( 'muplugins_loaded', 'plugins_loaded', 'setup_theme', 'after_setup_theme', 'init', 'wp_loaded', 'admin_init', 'current_screen', 'admin_enqueue_scripts', 'admin_head', 'in_admin_header', 'admin_notices', 'all_admin_notices', 'in_admin_footer', 'admin_print_footer_scripts', 'rest_api_init', 'parse_request', 'template_redirect' ) as $digi_ap_hook ) {
	add_action( $digi_ap_hook, static function () use ( $digi_ap_hook ) { digi_ap_mark( $digi_ap_hook ); }, PHP_INT_MIN );
	add_action( $digi_ap_hook, static function () use ( $digi_ap_hook ) { $GLOBALS['digi_ap']['marks'][ $digi_ap_hook . ':end' ] = digi_ap_now(); }, PHP_INT_MAX );
}
unset( $digi_ap_hook );
digi_ap_mark( 'probe' );

// Consultas: total y, con el perfil, por dueño (el marco más interno de un plugin) con su texto normalizado.
add_filter( 'query', static function ( $query ) {
	$GLOBALS['digi_ap']['queries']++;
	if ( digi_ap_profiling() ) {
		$owner = digi_ap_caller();
		if ( 'core' !== $owner ) {
			// Se conservan los nombres cortos (opciones, metas) para saber qué se consulta; los números y los textos largos, no.
			$key = preg_replace( array( '/\s+/', "/'[^']{41,}'/", '/\b\d+\b/' ), array( ' ', "'…'", 'N' ), substr( (string) $query, 0, 260 ) );
			if ( ! isset( $GLOBALS['digi_ap']['by_owner'][ $owner ] ) ) $GLOBALS['digi_ap']['by_owner'][ $owner ] = array( 'n' => 0, 'q' => array() );
			$GLOBALS['digi_ap']['by_owner'][ $owner ]['n']++;
			$q = &$GLOBALS['digi_ap']['by_owner'][ $owner ]['q'];
			$q[ $key ] = ( isset( $q[ $key ] ) ? $q[ $key ] : 0 ) + 1;
		}
	}
	return $query;
}, PHP_INT_MAX );

// Avisos de PHP (también los de _doing_it_wrong y _deprecated_*, que con WP_DEBUG pasan por trigger_error).
set_error_handler( static function ( $type, $message, $file = '', $line = 0 ) {
	if ( ! ( error_reporting() & $type ) && ! in_array( $type, array( E_DEPRECATED, E_USER_DEPRECATED ), true ) ) return false;
	$owner = digi_ap_owner_of( $file );
	if ( '' === $owner ) $owner = digi_ap_caller();
	$key = $type . '|' . $owner . '|' . substr( (string) $message, 0, 300 ) . '|' . basename( (string) $file ) . ':' . $line;
	$GLOBALS['digi_ap']['errors'][ $key ] = ( isset( $GLOBALS['digi_ap']['errors'][ $key ] ) ? $GLOBALS['digi_ap']['errors'][ $key ] : 0 ) + 1;
	return false;
} );

// HTTP saliente: se anota y se responde sin red (GitHub y api.wordpress.org con datos fijos; lo demás, cortado).
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$started = microtime( true );
	$host = (string) parse_url( $url, PHP_URL_HOST );
	$path = (string) parse_url( $url, PHP_URL_PATH );
	$entry = array( 'url' => $host . $path, 'blocking' => ! isset( $args['blocking'] ) || $args['blocking'], 'by' => digi_ap_caller(), 'at' => digi_ap_now() );
	$gh = isset( $_COOKIE['digi_ap_gh'] ) ? preg_replace( '/[^a-z]/', '', (string) $_COOKIE['digi_ap_gh'] ) : 'ok';
	$delay = isset( $_COOKIE['digi_ap_delay'] ) ? min( 15000, max( 0, (int) $_COOKIE['digi_ap_delay'] ) ) : 0;
	$response = null;
	if ( 'github.com' === $host || 'api.github.com' === $host || 'objects.githubusercontent.com' === $host ) {
		if ( $delay ) usleep( $delay * 1000 );
		if ( 'fail' === $gh ) {
			$response = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out (simulado)' );
		} elseif ( 'github.com' === $host && false !== strpos( $path, 'digitalisimo-plugins.json' ) && is_file( DIGI_AP_DIR . '/manifest.json' ) ) {
			$response = array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => (string) file_get_contents( DIGI_AP_DIR . '/manifest.json' ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
		} elseif ( 'api.github.com' === $host ) {
			$response = array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => '[]', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
		} else {
			$response = array( 'headers' => array(), 'body' => 'Not Found', 'response' => array( 'code' => 404, 'message' => 'Not Found' ), 'cookies' => array(), 'filename' => null );
		}
	} elseif ( 'api.wordpress.org' === $host && preg_match( '#/(plugins|themes)/update-check/#', $path, $m ) ) {
		$response = array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( array( $m[1] => new stdClass(), 'translations' => array(), 'no_update' => new stdClass() ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	} elseif ( 'api.wordpress.org' === $host && false !== strpos( $path, '/core/version-check/' ) ) {
		$response = array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( array( 'offers' => array(), 'translations' => array() ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	} elseif ( false !== strpos( $path, '/digi-ap-dummy' ) && ! empty( $args['filename'] ) && is_file( DIGI_AP_DIR . '/digi-ap-dummy.zip' ) ) {
		copy( DIGI_AP_DIR . '/digi-ap-dummy.zip', $args['filename'] );
		$response = array( 'headers' => array( 'content-type' => 'application/zip' ), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => $args['filename'] );
	} else {
		$response = new WP_Error( 'http_request_failed', 'Sin red en el ensayo (admin-perf)' );
	}
	$entry['ms'] = round( ( microtime( true ) - $started ) * 1000, 1 );
	$entry['result'] = is_wp_error( $response ) ? 'error' : (int) $response['response']['code'];
	$GLOBALS['digi_ap']['http'][] = $entry;
	return $response;
}, PHP_INT_MAX, 3 );

/* -------------------------------------------------------------- Perfil de callbacks */
/** Envuelve (una vez) cada callback de un plugin o del tema medidos para sumar su tiempo por gancho. */
function digi_ap_wrap_all() {
	if ( ! digi_ap_profiling() ) return;
	global $wp_filter;
	// Sólo ganchos de la carga del escritorio que pasan sus argumentos por valor (envolver todos rompía la carga: 500).
	static $hooks = array( 'plugins_loaded', 'after_setup_theme', 'init', 'wp_loaded', 'admin_init', 'current_screen', 'admin_menu', 'network_admin_menu', 'admin_enqueue_scripts', 'admin_notices', 'network_admin_notices', 'all_admin_notices', 'admin_head', 'admin_footer', 'admin_print_footer_scripts', 'in_admin_header', 'admin_bar_menu', 'site_transient_update_plugins', 'site_transient_update_themes', 'rest_api_init', 'heartbeat_received', 'wp_dashboard_setup', 'shutdown' );
	foreach ( $hooks as $hook ) {
		$object = isset( $wp_filter[ $hook ] ) ? $wp_filter[ $hook ] : null;
		if ( ! $object instanceof WP_Hook ) continue;
		foreach ( $object->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $idx => $cb ) {
				if ( ! empty( $cb['digi_ap'] ) ) continue;
				$fn = $cb['function'];
				$file = '';
				$label = '';
				try {
					if ( is_string( $fn ) && false === strpos( $fn, '::' ) ) {
						if ( ! function_exists( $fn ) ) continue;
						$r = new ReflectionFunction( $fn ); $file = $r->getFileName(); $label = $fn;
					} elseif ( is_string( $fn ) ) {
						list( $c, $m ) = explode( '::', $fn ); $r = new ReflectionMethod( $c, $m ); $file = $r->getFileName(); $label = $fn;
					} elseif ( is_array( $fn ) && 2 === count( $fn ) ) {
						$c = is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0];
						if ( ! method_exists( $fn[0], $fn[1] ) ) continue;
						$r = new ReflectionMethod( $c, $fn[1] ); $file = $r->getFileName(); $label = $c . '::' . $fn[1];
					} elseif ( $fn instanceof Closure ) {
						$r = new ReflectionFunction( $fn ); $file = $r->getFileName(); $label = 'closure@' . basename( (string) $file ) . ':' . $r->getStartLine();
					} else {
						continue;
					}
				} catch ( ReflectionException $e ) {
					continue;
				}
				$owner = digi_ap_owner_of( $file );
				if ( '' === $owner || 0 === strpos( $owner, 'other' ) ) continue;
				$key = $owner . '|' . $hook . '|' . $label;
				$object->callbacks[ $priority ][ $idx ]['function'] = static function ( ...$args ) use ( $fn, $key ) {
					$t = microtime( true );
					$q = $GLOBALS['digi_ap']['queries'];
					try {
						return call_user_func_array( $fn, $args );
					} finally {
						if ( ! isset( $GLOBALS['digi_ap']['cb'][ $key ] ) ) $GLOBALS['digi_ap']['cb'][ $key ] = array( 0, 0, 0 );
						$GLOBALS['digi_ap']['cb'][ $key ][0] += ( microtime( true ) - $t ) * 1000;
						$GLOBALS['digi_ap']['cb'][ $key ][1]++;
						$GLOBALS['digi_ap']['cb'][ $key ][2] += $GLOBALS['digi_ap']['queries'] - $q;
					}
				};
				$object->callbacks[ $priority ][ $idx ]['digi_ap'] = true;
			}
		}
	}
}
foreach ( array( 'plugins_loaded', 'after_setup_theme', 'init', 'wp_loaded', 'admin_init', 'current_screen', 'admin_enqueue_scripts', 'admin_head', 'rest_api_init', 'wp' ) as $digi_ap_hook ) add_action( $digi_ap_hook, 'digi_ap_wrap_all', PHP_INT_MIN + 1 );
unset( $digi_ap_hook );

/* -------------------------------------------------------------- Recursos encolados */
function digi_ap_assets() {
	$out = array();
	foreach ( array( 'scripts' => function_exists( 'wp_scripts' ) ? wp_scripts() : null, 'styles' => function_exists( 'wp_styles' ) ? wp_styles() : null ) as $kind => $deps ) {
		if ( ! $deps ) continue;
		foreach ( array_unique( array_merge( (array) $deps->done, (array) $deps->queue ) ) as $handle ) {
			$item = isset( $deps->registered[ $handle ] ) ? $deps->registered[ $handle ] : null;
			$src = $item && is_string( $item->src ) ? $item->src : '';
			$owner = '' !== $src ? digi_ap_owner_of( (string) parse_url( $src, PHP_URL_PATH ) ) : '';
			if ( '' === $owner && 0 === strpos( $handle, 'digitalisimo' ) ) $owner = 'digitalisimo?';
			$inline = '';
			if ( $item && ! empty( $item->extra ) ) foreach ( array( 'before', 'after', 'data' ) as $k ) if ( ! empty( $item->extra[ $k ] ) && false !== stripos( wp_json_encode( $item->extra[ $k ] ), 'digitalisimo' ) ) $inline = $k;
			if ( '' === $owner && '' === $inline ) continue;
			$out[] = array( 'kind' => $kind, 'handle' => $handle, 'owner' => $owner, 'inline' => $inline, 'src' => (string) parse_url( $src, PHP_URL_PATH ) );
		}
	}
	return $out;
}

/* -------------------------------------------------------------- Escritura */
register_shutdown_function( static function () {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	if ( false !== strpos( $uri, 'digi_ap=' ) && false === strpos( $uri, 'digi_ap=measure' ) ) return;
	if ( preg_match( '#\.(css|js|png|jpe?g|gif|svg|woff2?|ico|map)(\?|$)#', $uri ) ) return;
	global $wpdb;
	$d = $GLOBALS['digi_ap'];
	$cb = array();
	foreach ( $d['cb'] as $key => $v ) if ( $v[0] >= 0.05 || $v[2] ) $cb[ $key ] = array( round( $v[0], 2 ), $v[1], $v[2] );
	arsort( $cb );
	$error = error_get_last();
	$row = array(
		't' => microtime( true ),
		'req' => isset( $_COOKIE['digi_ap_req'] ) ? substr( preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $_COOKIE['digi_ap_req'] ), 0, 80 ) : '',
		'set' => digi_ap_set()[0],
		'prof' => digi_ap_profiling(),
		'method' => isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '',
		'uri' => $uri,
		'action' => isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? substr( $_REQUEST['action'], 0, 80 ) : '',
		'status' => function_exists( 'http_response_code' ) ? http_response_code() : 0,
		'ms' => digi_ap_now(),
		'php' => PHP_VERSION,
		'queries' => $d['queries'],
		'num_queries' => isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries : 0,
		'mem_peak_kb' => (int) round( memory_get_peak_usage() / 1024 ),
		'mem_peak_real_kb' => (int) round( memory_get_peak_usage( true ) / 1024 ),
		'marks' => $d['marks'],
		'http' => $d['http'],
		'errors' => $d['errors'],
		'fatal' => $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ? $error['message'] . ' @ ' . $error['file'] . ':' . $error['line'] : '',
		'assets' => did_action( 'admin_enqueue_scripts' ) || did_action( 'wp_enqueue_scripts' ) ? digi_ap_assets() : array(),
		'by_owner' => $d['by_owner'],
		'cb' => array_slice( $cb, 0, 60, true ),
		'included' => count( get_included_files() ),
	);
	file_put_contents( DIGI_AP_DIR . '/metrics.jsonl', wp_json_encode( $row ) . "\n", FILE_APPEND | LOCK_EX );
} );

/* -------------------------------------------------------------- Órdenes del ensayo */
// ?digi_ap=<orden> (sólo administradores): estado del sitio (autoload, cron) y escenarios de los actualizadores.
add_action( 'init', static function () {
	if ( empty( $_GET['digi_ap'] ) || ! current_user_can( 'manage_options' ) ) return;
	$cmd = sanitize_key( wp_unslash( $_GET['digi_ap'] ) );
	if ( 'measure' === $cmd ) return;
	require_once DIGI_AP_DIR . '/commands.php';
	wp_send_json( digi_ap_command( $cmd ) );
}, 1 );
