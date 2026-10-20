<?php
/**
 * Plugin Name: DIGI red del tema (sólo pruebas)
 * Description: Índice de Releases falso para theme-network.sh: `plugins` con las versiones instaladas (ninguna se
 *              ofrece como nueva, P-5) y `themes.digitalisimo-theme` en la versión siguiente, cuyo ZIP es el que
 *              construyó el arnés. Respuestas vacías de wordpress.org, registro de toda petición (también a
 *              api.github.com), bloqueo del resto, actualizaciones automáticas sólo de temas, detección del modo de
 *              mantenimiento y un sistema de archivos cuyo move() falla (N3f) cuando la prueba lo pide. No se publica.
 */
defined( 'ABSPATH' ) || exit;

const DIGI_NET_DIR = '/wordpress/wp-content/probe';

if ( ! defined( 'FS_METHOD' ) ) {
	define( 'FS_METHOD', 'direct' );
}
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}

/** Estado que fija cada paso del arnés (state.json): `requires` del índice y `moveFail`. */
function digi_net_state() {
	$state = json_decode( (string) @file_get_contents( DIGI_NET_DIR . '/state.json' ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	return is_array( $state ) ? $state : array();
}

function digi_net_step() {
	return (string) getenv( 'DIGI_STEP' );
}

function digi_net_log( $file, array $row ) {
	$row['step'] = digi_net_step();
	file_put_contents( DIGI_NET_DIR . '/' . $file, wp_json_encode( $row ) . "\n", FILE_APPEND );
}

set_error_handler(
	static function ( $no, $message, $file, $line ) {
		$kinds = array( E_WARNING => 'Warning', E_USER_WARNING => 'Warning', E_NOTICE => 'Notice', E_USER_NOTICE => 'Notice', E_DEPRECATED => 'Deprecated', E_USER_DEPRECATED => 'Deprecated' );
		if ( isset( $kinds[ $no ] ) && 4437 !== error_reporting() ) {
			digi_net_log( 'php.jsonl', array( 'kind' => $kinds[ $no ], 'msg' => $message, 'file' => $file, 'line' => $line ) );
		}
		return false;
	}
);
foreach ( array( 'deprecated_function_run', 'deprecated_file_included', 'deprecated_argument_run', 'deprecated_hook_run', 'doing_it_wrong_run' ) as $digi_net_hook ) {
	add_action(
		$digi_net_hook,
		static function ( ...$args ) use ( $digi_net_hook ) {
			digi_net_log( 'php.jsonl', array( 'kind' => 'doing_it_wrong_run' === $digi_net_hook ? 'DoingItWrong' : 'Deprecated', 'msg' => $digi_net_hook . ': ' . implode( ' | ', array_map( static function ( $a ) { return is_scalar( $a ) ? (string) $a : gettype( $a ); }, $args ) ), 'file' => wp_debug_backtrace_summary( null, 3 ), 'line' => 0 ) );
		},
		10,
		4
	);
}

function digi_net_release() {
	$release = json_decode( (string) file_get_contents( DIGI_NET_DIR . '/release.json' ), true );
	return is_array( $release ) ? $release : array();
}

/** Índice de la Release falsa, con el mismo esquema que scripts/release-manifest.mjs (§4.2). */
function digi_net_manifest() {
	$release = digi_net_release();
	$state   = digi_net_state();
	$tag     = 'v2026.10.17.900';
	$prefix  = 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/' . $tag . '/';
	$plugins = array();
	foreach ( (array) ( $release['plugins'] ?? array() ) as $slug => $version ) {
		$plugins[ $slug ] = array( 'version' => $version, 'file' => "$slug-$version.zip", 'url' => $prefix . "$slug-$version.zip" );
	}
	$version = (string) ( $release['theme'] ?? '' );
	return array(
		'tag'         => $tag,
		'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/' . $tag,
		'generated'   => gmdate( 'c' ),
		'plugins'     => $plugins,
		'themes'      => array(
			'digitalisimo-theme' => array(
				'version'      => $version,
				'file'         => "digitalisimo-theme-$version.zip",
				'url'          => $prefix . "digitalisimo-theme-$version.zip",
				'requires'     => (string) ( $state['requires'] ?? '6.8' ),
				'requires_php' => '7.4',
			),
		),
	);
}

function digi_net_response( $body, $filename = null ) {
	return array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => $filename );
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		digi_net_log( 'http.jsonl', array( 'url' => (string) $url, 'host' => $host, 'caller' => wp_debug_backtrace_summary( null, 3 ) ) );
		if ( 'api.wordpress.org' === $host && false !== strpos( $path, '/themes/update-check/' ) ) {
			return digi_net_response( '{"themes":[],"no_update":[],"translations":[]}' );
		}
		if ( 'api.wordpress.org' === $host && false !== strpos( $path, '/plugins/update-check/' ) ) {
			return digi_net_response( '{"plugins":[],"no_update":[],"translations":[]}' );
		}
		if ( 'api.wordpress.org' === $host && false !== strpos( $path, '/core/version-check/' ) ) {
			return digi_net_response( '{"offers":[],"translations":[]}' );
		}
		if ( 'github.com' === $host && preg_match( '#^/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-plugins\.json$#', $path ) ) {
			return digi_net_response( wp_json_encode( digi_net_manifest() ) );
		}
		if ( 'github.com' === $host && preg_match( '#^/danbutanda/digitalisimo-plugin/releases/download/[^/]+/(digitalisimo-theme-[0-9.]+\.zip)$#', $path, $m ) ) {
			$zip = DIGI_NET_DIR . '/pkg/' . $m[1];
			if ( ! is_file( $zip ) ) {
				return new WP_Error( 'digi_net_zip', 'La prueba no tiene ' . $m[1] );
			}
			if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
				copy( $zip, $args['filename'] );
				return digi_net_response( '', $args['filename'] );
			}
			return digi_net_response( (string) file_get_contents( $zip ) );
		}
		return new WP_Error( 'digi_net_offline', 'Prueba de red sin salida: ' . $url );
	},
	PHP_INT_MAX,
	3
);

// N4 sólo puede actualizar temas: los plugins montados del arnés nunca se tocan (P-5).
add_filter( 'auto_update_plugin', '__return_false', PHP_INT_MAX );
add_filter( 'auto_update_core', '__return_false', PHP_INT_MAX );
add_filter( 'auto_update_translation', '__return_false', PHP_INT_MAX );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );

// N3: el modo de mantenimiento debe existir mientras se instala el paquete; N3f: move() falla a la fuerza.
add_filter(
	'upgrader_source_selection',
	static function ( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
		digi_net_log( 'upgrade.jsonl', array( 'source' => is_wp_error( $source ) ? 'WP_Error' : (string) $source, 'theme' => is_array( $hook_extra ) ? (string) ( $hook_extra['theme'] ?? '' ) : '', 'maintenance' => file_exists( ABSPATH . '.maintenance' ) ) );
		$state = digi_net_state();
		if ( ! empty( $state['moveFail'] ) && isset( $GLOBALS['wp_filesystem'] ) && $GLOBALS['wp_filesystem'] instanceof WP_Filesystem_Direct ) {
			require_once DIGI_NET_DIR . '/fs-fail.php';
			if ( ! $GLOBALS['wp_filesystem'] instanceof Digi_Net_Failing_Filesystem ) {
				$GLOBALS['wp_filesystem'] = new Digi_Net_Failing_Filesystem( null );
			}
		}
		return $source;
	},
	1,
	4
);

// N6: espías justo después del filtro del tema (30, registrado antes) y después del de Tools (31).
$GLOBALS['digi_net_spy'] = array();
function digi_net_spy( $transient, $where ) {
	$seen = array();
	foreach ( array( 'response', 'no_update' ) as $list ) {
		if ( is_object( $transient ) && isset( $transient->{$list} ) && is_array( $transient->{$list} ) ) {
			foreach ( $transient->{$list} as $slug => $item ) {
				if ( 0 === strpos( (string) $slug, 'digitalisimo-theme' ) ) {
					$seen[ $slug ] = array( 'list' => $list, 'item' => (array) $item );
				}
			}
		}
	}
	$GLOBALS['digi_net_spy'][] = array( 'where' => $where, 'filter' => current_filter(), 'seen' => $seen );
	return $transient;
}
add_action(
	'after_setup_theme',
	static function () {
		foreach ( array( 'pre_set_site_transient_update_themes', 'site_transient_update_themes' ) as $hook ) {
			add_filter( $hook, static function ( $t ) { return digi_net_spy( $t, 'tras-tema' ); }, 30 );
			add_filter( $hook, static function ( $t ) { return digi_net_spy( $t, 'tras-tools' ); }, 32 );
		}
	},
	PHP_INT_MAX
);
