<?php
/**
 * Sonda de seo-admin-perf.sh (mu-plugin que setup.php copia a mu-plugins/). Mide cada petición del escritorio sin
 * cambiar lo que hace:
 *   - conjunto de plugins de la petición: cabecera X-Digi-AP-Set o cookie digi_ap_set («none», «all» o archivos
 *     separados por comas); los plugins del entorno (WooCommerce) siguen siempre activos;
 *   - al terminar (último shutdown) escribe una línea en /digi-ap/log.jsonl con el id de la cabecera X-Digi-AP-Id:
 *     tiempo desde timer_start(), marcas de fases, consultas, memoria pico, peticiones HTTP salientes (y quién las hizo),
 *     avisos de PHP con su origen, scripts y hojas encolados de los plugins medidos y, con X-Digi-AP-Detail, cada
 *     consulta con su llamador (SAVEQUERIES lo activa wp-config sólo en esas peticiones);
 *   - ninguna petición sale a internet: GitHub responde con un índice falso con las versiones instaladas (como una
 *     Release sin novedades), WordPress.org como un sitio al día y el resto falla al instante (todas se registran, con
 *     su timeout pedido y quién las hizo);
 *   - ?digi_ap_state=1 con la cabecera X-Digi-AP-Token devuelve opciones con autoload, eventos de WP-Cron y plugins activos.
 */
defined( 'ABSPATH' ) || exit;

$GLOBALS['digi_ap'] = array( 'errors' => array(), 'http' => array(), 'marks' => array(), 'error_count' => 0 );

const DIGI_AP_DIRS = array( 'digitalisimo-seo', 'digitalisimo.chatbot', 'digitalisimo-hosting' );

/** Origen de una llamada: el primer marco de la pila dentro de un plugin medido (o de otro plugin / del núcleo). */
function digi_ap_origin( $skip = 2 ) {
	$fallback = '';
	foreach ( array_slice( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 ), $skip ) as $frame ) {
		$file = isset( $frame['file'] ) ? str_replace( '\\', '/', $frame['file'] ) : '';
		if ( '' === $file || false !== strpos( $file, '/mu-plugins/digi-ap-probe.php' ) ) continue;
		if ( preg_match( '#/wp-content/plugins/([^/]+)/(.+)$#', $file, $m ) ) {
			$where = $m[1] . '/' . $m[2] . ':' . ( $frame['line'] ?? 0 );
			if ( in_array( $m[1], DIGI_AP_DIRS, true ) ) return $where;
			if ( '' === $fallback ) $fallback = $where;
		}
	}
	return '' !== $fallback ? $fallback : 'core';
}

function digi_ap_plugin_of( $origin ) {
	$dir = strtok( (string) $origin, '/' );
	$map = array( 'digitalisimo-seo' => 'seo', 'digitalisimo.chatbot' => 'ia', 'digitalisimo-hosting' => 'hosting' );
	return $map[ $dir ] ?? ( 'core' === $origin ? 'core' : 'otro' );
}

// Conjunto de plugins activos en esta petición.
$digi_ap_set = '';
if ( ! empty( $_SERVER['HTTP_X_DIGI_AP_SET'] ) ) $digi_ap_set = (string) $_SERVER['HTTP_X_DIGI_AP_SET'];
elseif ( ! empty( $_COOKIE['digi_ap_set'] ) ) $digi_ap_set = (string) $_COOKIE['digi_ap_set'];
$GLOBALS['digi_ap']['set'] = $digi_ap_set;
if ( '' !== $digi_ap_set && 'all' !== $digi_ap_set ) {
	$GLOBALS['digi_ap_only'] = 'none' === $digi_ap_set ? array() : array_filter( explode( ',', $digi_ap_set ) );
	$keep = static function ( $file ) { return in_array( $file, $GLOBALS['digi_ap_only'], true ) || 0 === strpos( (string) $file, 'woocommerce/' ); };
	add_filter( 'option_active_plugins', static function ( $plugins ) use ( $keep ) { return array_values( array_filter( (array) $plugins, $keep ) ); }, 1 );
	add_filter( 'site_option_active_sitewide_plugins', static function ( $plugins ) use ( $keep ) { $out = array(); foreach ( (array) $plugins as $file => $time ) if ( $keep( $file ) ) $out[ $file ] = $time; return $out; }, 1 );
}

// Avisos de PHP (notice, warning, deprecated) con el plugin que los provoca.
$GLOBALS['digi_ap_prev_handler'] = set_error_handler( static function ( $no, $str, $file, $line ) {
	$GLOBALS['digi_ap']['error_count']++;
	if ( count( $GLOBALS['digi_ap']['errors'] ) < 40 ) {
		$path = str_replace( '\\', '/', (string) $file );
		$origin = preg_match( '#/wp-content/plugins/([^/]+)/(.+)$#', $path, $m ) ? $m[1] . '/' . $m[2] . ':' . $line : digi_ap_origin( 1 );
		$GLOBALS['digi_ap']['errors'][] = array( 'type' => $no, 'message' => substr( (string) $str, 0, 300 ), 'origin' => $origin, 'plugin' => digi_ap_plugin_of( $origin ) );
	}
	$prev = $GLOBALS['digi_ap_prev_handler'];
	return $prev ? (bool) call_user_func( $prev, $no, $str, $file, $line ) : false;
} );
foreach ( array( 'doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run', 'deprecated_file_included' ) as $digi_ap_hook ) {
	add_action( $digi_ap_hook, static function ( $what ) use ( $digi_ap_hook ) {
		if ( count( $GLOBALS['digi_ap']['errors'] ) >= 40 ) return;
		$origin = digi_ap_origin( 4 );
		$GLOBALS['digi_ap']['errors'][] = array( 'type' => $digi_ap_hook, 'message' => (string) $what, 'origin' => $origin, 'plugin' => digi_ap_plugin_of( $origin ) );
	}, 10, 1 );
}

// Peticiones HTTP salientes: se registran todas y ninguna sale.
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$origin = digi_ap_origin( 3 );
	$parts  = wp_parse_url( (string) $url );
	$GLOBALS['digi_ap']['http'][] = array(
		'url'      => ( $parts['scheme'] ?? '' ) . '://' . ( $parts['host'] ?? '' ) . ( $parts['path'] ?? '' ), // sin query: Namecheap lleva la clave ahí
		'method'   => (string) ( $args['method'] ?? 'GET' ),
		'blocking' => ! isset( $args['blocking'] ) || (bool) $args['blocking'],
		'timeout'  => (float) ( $args['timeout'] ?? 5 ),
		'origin'   => $origin,
		'plugin'   => digi_ap_plugin_of( $origin ),
		'hook'     => current_action() ?: current_filter(),
	);
	if ( false !== $pre ) return $pre;
	$host = (string) ( $parts['host'] ?? '' );
	$path = (string) ( $parts['path'] ?? '' );
	if ( 'github.com' === $host && preg_match( '#/releases/latest/download/digitalisimo-plugins\.json$#', $path ) ) {
		$plugins = array();
		foreach ( array( 'digitalisimo-seo' => 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo-ia-tools' => 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-hosting' => 'digitalisimo-hosting/digitalisimo-hosting.php' ) as $slug => $file ) {
			$data = is_file( WP_PLUGIN_DIR . '/' . $file ) ? get_file_data( WP_PLUGIN_DIR . '/' . $file, array( 'Version' => 'Version' ) ) : array();
			if ( empty( $data['Version'] ) ) continue;
			$plugins[ $slug ] = array( 'version' => $data['Version'], 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v-ap/' . $slug . '-' . $data['Version'] . '.zip' );
		}
		$body = wp_json_encode( array( 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v-ap', 'plugins' => $plugins, 'themes' => array() ) );
		return array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	}
	if ( 'api.github.com' === $host ) return array( 'headers' => array(), 'body' => '[]', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	// WordPress.org responde como un sitio al día: así el núcleo guarda sus cachés (update_plugins con «checked», la
	// versión del navegador y la de PHP) igual que en un sitio real y no repite la consulta en cada carga.
	if ( 'api.wordpress.org' === $host ) {
		$ver = get_bloginfo( 'version' );
		if ( false !== strpos( $path, '/plugins/update-check/' ) ) $body = array( 'plugins' => new stdClass(), 'translations' => array(), 'no_update' => new stdClass() );
		elseif ( false !== strpos( $path, '/themes/update-check/' ) ) $body = array( 'themes' => new stdClass(), 'translations' => array(), 'no_update' => new stdClass() );
		elseif ( false !== strpos( $path, '/core/version-check/' ) ) $body = array( 'offers' => array( array( 'response' => 'latest', 'download' => '', 'locale' => get_locale(), 'packages' => new stdClass(), 'current' => $ver, 'version' => $ver, 'php_version' => '7.4', 'mysql_version' => '5.5.5', 'new_bundled' => '', 'partial_version' => false ) ), 'translations' => array() );
		elseif ( false !== strpos( $path, '/core/browse-happy/' ) ) $body = array( 'name' => 'Chrome', 'version' => '140', 'platform' => 'Linux', 'update_url' => 'https://www.google.com/chrome', 'img_src' => '', 'img_src_ssl' => '', 'current_version' => '140', 'upgrade' => false, 'insecure' => false, 'mobile' => false );
		elseif ( false !== strpos( $path, '/core/serve-happy/' ) ) $body = array( 'recommended_version' => '8.3', 'minimum_version' => '7.4', 'is_supported' => true, 'is_secure' => true, 'is_acceptable' => true );
		else $body = array( 'translations' => array() );
		return array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	}
	return new WP_Error( 'digi_ap_offline', 'seo-admin-perf: sin red' );
}, PHP_INT_MAX, 3 );

// Marcas de fases (segundos desde timer_start()).
foreach ( array( 'muplugins_loaded', 'plugins_loaded', 'init', 'wp_loaded', 'admin_init', 'current_screen', 'admin_head', 'in_admin_header', 'admin_footer', 'rest_api_init' ) as $digi_ap_hook ) {
	add_action( $digi_ap_hook, static function () use ( $digi_ap_hook ) {
		if ( ! isset( $GLOBALS['digi_ap']['marks'][ $digi_ap_hook ] ) ) $GLOBALS['digi_ap']['marks'][ $digi_ap_hook ] = round( ( microtime( true ) - $GLOBALS['timestart'] ) * 1000, 2 );
	}, PHP_INT_MAX );
}

// Tiempo de incluir cada plugin (de un plugin_loaded al siguiente): el costo fijo de cargar su código en cada petición.
$GLOBALS['digi_ap']['load'] = array(); $GLOBALS['digi_ap']['load_last'] = null;
add_action( 'muplugins_loaded', static function () { $GLOBALS['digi_ap']['load_last'] = microtime( true ); }, PHP_INT_MAX );
add_action( 'plugin_loaded', static function ( $file ) {
	$now = microtime( true );
	if ( null !== $GLOBALS['digi_ap']['load_last'] ) $GLOBALS['digi_ap']['load'][ basename( dirname( $file ) ) ] = round( ( $now - $GLOBALS['digi_ap']['load_last'] ) * 1000, 2 );
	$GLOBALS['digi_ap']['load_last'] = $now;
}, PHP_INT_MAX );
add_action( 'plugins_loaded', static function () { $GLOBALS['digi_ap']['load_last'] = null; }, 0 );

// Scripts y hojas de los plugins medidos que llegaron a imprimirse.
function digi_ap_assets() {
	$out = array();
	foreach ( array( 'script' => function_exists( 'wp_scripts' ) ? wp_scripts() : null, 'style' => function_exists( 'wp_styles' ) ? wp_styles() : null ) as $kind => $deps ) {
		if ( ! $deps ) continue;
		foreach ( (array) $deps->done as $handle ) {
			$item = $deps->registered[ $handle ] ?? null;
			$src  = $item && is_string( $item->src ) ? $item->src : '';
			$mine = '' !== $src && preg_match( '#/wp-content/plugins/(' . implode( '|', array_map( 'preg_quote', DIGI_AP_DIRS ) ) . ')/#', $src, $m );
			$inline = $item && ! empty( $item->extra['after'] ) && false !== stripos( implode( "\n", array_filter( (array) $item->extra['after'], 'is_string' ) ), 'digitalisimo' );
			if ( $mine || 0 === strpos( $handle, 'digitalisimo' ) || $inline ) {
				$out[] = array( 'kind' => $kind, 'handle' => $handle, 'plugin' => $mine ? digi_ap_plugin_of( $m[1] . '/' ) : ( $inline ? 'inline' : 'digitalisimo' ), 'src' => preg_replace( '#^https?://[^/]+#', '', $src ) );
			}
		}
	}
	return $out;
}

function digi_ap_write() {
	global $wpdb;
	$id = isset( $_SERVER['HTTP_X_DIGI_AP_ID'] ) ? preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $_SERVER['HTTP_X_DIGI_AP_ID'] ) : '';
	if ( '' === $id && empty( $_COOKIE['digi_ap_log'] ) ) return;
	$line = array(
		'id'      => $id,
		't'       => microtime( true ),
		'uri'     => (string) ( $_SERVER['REQUEST_URI'] ?? '' ),
		'method'  => (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ),
		'set'     => $GLOBALS['digi_ap']['set'],
		'blog'    => get_current_blog_id(),
		'ms'      => round( ( microtime( true ) - $GLOBALS['timestart'] ) * 1000, 2 ),
		'queries' => (int) $wpdb->num_queries,
		'peak_kb' => (int) round( memory_get_peak_usage() / 1024 ),
		'marks'   => $GLOBALS['digi_ap']['marks'],
		'load'    => $GLOBALS['digi_ap']['load'],
		'opcache' => function_exists( 'opcache_get_status' ) && is_array( $st = @opcache_get_status( false ) ) && ! empty( $st['opcache_enabled'] ),
		'http'    => $GLOBALS['digi_ap']['http'],
		'errors'  => $GLOBALS['digi_ap']['errors'],
		'error_count' => $GLOBALS['digi_ap']['error_count'],
		'assets'  => digi_ap_assets(),
		'active'  => array_values( array_intersect( array( 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-hosting/digitalisimo-hosting.php' ), array_merge( (array) get_option( 'active_plugins', array() ), is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array() ) ) ),
	);
	if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && ! empty( $wpdb->queries ) ) {
		$by = array();
		foreach ( $wpdb->queries as $q ) {
			$caller = (string) ( $q[2] ?? '' );
			$plugin = 'core';
			if ( preg_match( '/Digitalisimo_(AI|Content_Automation|MCP_Articles)/', $caller ) ) $plugin = 'ia';
			elseif ( preg_match( '/Digitalisimo_Hosting/', $caller ) ) $plugin = 'hosting';
			elseif ( preg_match( '/Digitalisimo_(Integrations|SEO_|Tools_Update_Bridge|Media_Field|Admin_UI)/', $caller ) ) $plugin = 'seo';
			elseif ( preg_match( '/Digitalisimo_(Core|Updater|Plugin_Catalog)|digitalisimo_ui/', $caller ) ) $plugin = 'compartido';
			$by[ $plugin ][] = array( 'sql' => substr( preg_replace( '/\s+/', ' ', (string) $q[0] ), 0, 400 ), 'ms' => round( (float) $q[1] * 1000, 3 ), 'caller' => substr( $caller, -400 ) );
		}
		$line['detail'] = $by;
	}
	@file_put_contents( '/digi-ap/log.jsonl', wp_json_encode( $line ) . "\n", FILE_APPEND | LOCK_EX );
}
add_action( 'shutdown', static function () { register_shutdown_function( 'digi_ap_write' ); }, PHP_INT_MAX );

// Estado: opciones con autoload, eventos de WP-Cron y plugins activos (sin el filtro del conjunto).
add_action( 'init', static function () {
	if ( empty( $_GET['digi_ap_state'] ) ) return;
	$token = is_file( '/digi-ap/token.txt' ) ? trim( (string) file_get_contents( '/digi-ap/token.txt' ) ) : '';
	if ( '' === $token || ! hash_equals( $token, (string) ( $_SERVER['HTTP_X_DIGI_AP_TOKEN'] ?? '' ) ) ) return;
	nocache_headers();
	if ( 'expire' === $_GET['digi_ap_state'] ) {
		// Cachés remotas vencidas: los transitorios propios (de sitio y de red), como a los 10 minutos en un sitio real.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%digitalisimo%'" );
		if ( is_multisite() ) $wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%site_transient%digitalisimo%'" );
		wp_cache_flush();
		wp_send_json( array( 'expired' => true ) );
	}
	if ( 'cron' === $_GET['digi_ap_state'] ) {
		// Un evento de WP-Cron de los plugins, ejecutado una vez con sus argumentos (la sonda mide la petición).
		$hook = sanitize_text_field( wp_unslash( $_GET['hook'] ?? '' ) );
		$args = array();
		foreach ( (array) _get_cron_array() as $hooks ) if ( isset( $hooks[ $hook ] ) ) { $event = reset( $hooks[ $hook ] ); $args = (array) ( $event['args'] ?? array() ); break; }
		define( 'DOING_CRON', true );
		do_action_ref_array( $hook, $args );
		wp_send_json( array( 'ran' => $hook ) );
	}
	wp_send_json( digi_ap_state() );
}, 0 );

function digi_ap_state() {
	global $wpdb;
	$autoload = array();
	$values = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : array( 'yes' );
	$in = "'" . implode( "','", array_map( 'esc_sql', $values ) ) . "'";
	foreach ( (array) $wpdb->get_results( "SELECT option_name, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE autoload IN ($in)", ARRAY_A ) as $row ) $autoload[ $row['option_name'] ] = (int) $row['bytes'];
	$options = array();
	foreach ( (array) $wpdb->get_results( "SELECT option_name, autoload, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE option_name LIKE '%digitalisimo%'", ARRAY_A ) as $row ) $options[ $row['option_name'] ] = array( 'autoload' => $row['autoload'], 'bytes' => (int) $row['bytes'] );
	$cron = array();
	foreach ( (array) _get_cron_array() as $time => $hooks ) foreach ( (array) $hooks as $hook => $events ) foreach ( (array) $events as $event ) $cron[] = array( 'hook' => $hook, 'in_s' => (int) $time - time(), 'schedule' => $event['schedule'] ?? false, 'interval' => $event['interval'] ?? null, 'args' => count( (array) ( $event['args'] ?? array() ) ) );
	$raw_active = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'active_plugins'" );
	$sitemeta = array();
	if ( is_multisite() ) foreach ( (array) $wpdb->get_results( "SELECT meta_key, LENGTH(meta_value) AS bytes FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%digitalisimo%'", ARRAY_A ) as $row ) $sitemeta[ $row['meta_key'] ] = (int) $row['bytes'];
	return array( 'blog' => get_current_blog_id(), 'autoload' => $autoload, 'options' => $options, 'sitemeta' => $sitemeta, 'cron' => $cron, 'active_raw' => maybe_unserialize( $raw_active ), 'sitewide_raw' => is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array(), 'postmeta_rows' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" ), 'plugin_postmeta' => (array) $wpdb->get_results( "SELECT meta_key, COUNT(*) AS n FROM {$wpdb->postmeta} WHERE meta_key LIKE '%digitalisimo%' GROUP BY meta_key", ARRAY_A ) );
}
