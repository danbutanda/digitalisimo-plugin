<?php
/**
 * Pasos de theme-network.sh (red Multisite de Playground, un proceso PHP por paso). DIGI_STEP indica el paso y cada uno
 * deja en result.json sus problemas (lista vacía = pasa) y lo que observó. Contexto de cada paso:
 *   setup, n1-main, n6-*, n3f-check, n4*, n3-check · sitio principal
 *   n1-sub, n2-sub                                  · subsitio /sub/ (arranca su propio tema)
 *   n2-net, n5, n3f, n3                             · administración de la red
 * Nada de esto se publica.
 */

$digi_step = (string) getenv( 'DIGI_STEP' );
$digi_sub  = in_array( $digi_step, array( 'n1-sub', 'n2-sub' ), true );
$digi_net  = in_array( $digi_step, array( 'n2-net', 'n5', 'n3f', 'n3' ), true );
$digi_cron = 0 === strpos( $digi_step, 'n4' );
$_SERVER['HTTP_HOST']   = 'playground.test';
$_SERVER['REQUEST_URI'] = $digi_sub ? '/sub/wp-admin/index.php' : ( $digi_net ? '/wp-admin/network/index.php' : '/wp-admin/index.php' );
if ( 'setup' !== $digi_step && ! $digi_cron ) {
	define( 'WP_ADMIN', true );
}
if ( $digi_net ) {
	define( 'WP_NETWORK_ADMIN', true );
}
if ( $digi_cron ) {
	define( 'DOING_CRON', true );
}
register_shutdown_function(
	static function () use ( $digi_step ) {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( '/wordpress/wp-content/probe/fatal.log', $digi_step . ' ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line'] . "\n", FILE_APPEND );
		}
	}
);
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';
require_once ABSPATH . 'wp-admin/includes/update.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
if ( is_admin() ) {
	require_once ABSPATH . 'wp-admin/includes/admin.php'; // Lo mismo que carga admin-post.php antes de sus handlers.
}

const DIGI_DIR  = '/wordpress/wp-content/probe';
const DIGI_URI  = 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme';
const DIGI_COPY = 'digitalisimo-theme-1.0.0';

class Digi_Net_Redirect extends Exception {}
class Digi_Net_Die extends Exception {}

function digi_save( $key, array $problems, array $data = array() ) {
	$file   = DIGI_DIR . '/result.json';
	$result = is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	$result[ $key ] = array( 'problems' => array_values( $problems ), 'data' => $data );
	file_put_contents( $file, wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

function digi_state( array $state ) {
	file_put_contents( DIGI_DIR . '/state.json', wp_json_encode( $state ) );
}

function digi_release() {
	return (array) json_decode( (string) file_get_contents( DIGI_DIR . '/release.json' ), true );
}

/** Cachés del índice, del tema y de Tools, y la caché de actualizaciones de WordPress. */
function digi_clear_caches() {
	foreach ( array( 'digitalisimo_plugins_manifest', 'digitalisimo_theme_release', 'digitalisimo_tools_theme_release', 'update_themes' ) as $transient ) {
		delete_site_transient( $transient );
	}
	wp_clean_themes_cache();
}

function digi_version( $stylesheet ) {
	$file = get_theme_root() . '/' . $stylesheet . '/style.css';
	if ( ! is_file( $file ) ) {
		return null;
	}
	$data = get_file_data( $file, array( 'Version' => 'Version', 'UpdateURI' => 'Update URI' ) );
	return $data['Version'];
}

function digi_api_calls( $step ) {
	$count = 0;
	foreach ( file_exists( DIGI_DIR . '/http.jsonl' ) ? file( DIGI_DIR . '/http.jsonl' ) : array() as $line ) {
		$row = json_decode( $line, true );
		if ( $row && $step === $row['step'] && 'api.github.com' === $row['host'] ) {
			++$count;
		}
	}
	return $count;
}

/** Comprueba una entrada de update_themes: array, con paquete, sin `autoupdate` y con el id del tema. */
function digi_entry_problems( $transient, $stylesheet, $list = 'response' ) {
	$problems = array();
	$entry    = is_object( $transient ) && isset( $transient->{$list}[ $stylesheet ] ) ? $transient->{$list}[ $stylesheet ] : null;
	if ( null === $entry ) {
		return array( "update_themes->{$list}['$stylesheet'] no existe" );
	}
	if ( ! is_array( $entry ) ) {
		$problems[] = "update_themes->{$list}['$stylesheet'] no es un array (" . gettype( $entry ) . ')';
		$entry      = (array) $entry;
	}
	if ( 'response' === $list && empty( $entry['package'] ) ) {
		$problems[] = "$stylesheet: entrada sin package";
	}
	if ( array_key_exists( 'autoupdate', $entry ) ) {
		$problems[] = "$stylesheet: la entrada lleva autoupdate";
	}
	if ( DIGI_URI !== ( $entry['id'] ?? '' ) ) {
		$problems[] = "$stylesheet: id " . ( $entry['id'] ?? '(vacío)' );
	}
	if ( $stylesheet !== ( $entry['theme'] ?? '' ) ) {
		$problems[] = "$stylesheet: theme " . ( $entry['theme'] ?? '(vacío)' );
	}
	return $problems;
}

/** Ejecuta un handler de admin-post como lo haría admin-post.php y devuelve cómo terminó. */
/** admin_init una sola vez por petición, como admin-post.php. */
function digi_admin_init() {
	static $done = false;
	if ( ! $done ) {
		$done = true;
		do_action( 'admin_init' );
	}
}

function digi_handler( $action, array $query ) {
	$_GET     = $query + array( 'action' => $action );
	$_REQUEST = $_GET;
	add_filter( 'wp_redirect', static function ( $location ) { throw new Digi_Net_Redirect( (string) $location ); }, PHP_INT_MAX );
	add_filter( 'wp_die_handler', static function () { return static function ( $message ) { throw new Digi_Net_Die( is_wp_error( $message ) ? $message->get_error_message() : wp_strip_all_tags( (string) $message ) ); }; }, PHP_INT_MAX );
	ob_start();
	try {
		digi_admin_init();
		do_action( 'admin_post_' . $action );
		$out = array( 'end' => 'sin salida', 'detail' => '' );
	} catch ( Digi_Net_Redirect $e ) {
		$out = array( 'end' => 'redirect', 'detail' => $e->getMessage() );
	} catch ( Digi_Net_Die $e ) {
		$out = array( 'end' => 'wp_die', 'detail' => $e->getMessage() );
	} catch ( Throwable $e ) {
		$out = array( 'end' => 'error', 'detail' => get_class( $e ) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() );
	}
	$out['output'] = substr( (string) ob_get_clean(), 0, 2000 );
	return $out;
}

function digi_catalog( $context ) {
	ob_start();
	do_action( 'digitalisimo_plugin_catalog_after', $context );
	return (string) ob_get_clean();
}

function digi_rows( $html ) {
	if ( ! preg_match( '#<table[^>]*digitalisimo-theme-catalog[^>]*>.*?<tbody>(.*?)</tbody>#s', $html, $m ) ) {
		return array();
	}
	preg_match_all( '#<tr>(.*?)</tr>#s', $m[1], $rows );
	return $rows[1];
}

function digi_mods_md5( $blog, $stylesheet ) {
	$mods = get_blog_option( $blog, 'theme_mods_' . $stylesheet, null );
	if ( is_array( $mods ) ) {
		unset( $mods['sidebars_widgets'] );
		ksort( $mods );
	}
	return null === $mods ? null : md5( serialize( $mods ) );
}

$problems = array();
$data     = array( 'blog' => get_current_blog_id(), 'template' => get_template(), 'themeUpdater' => class_exists( 'Digitalisimo_Theme_Updater', false ), 'tools' => class_exists( '\Digitalisimo\Tools\Theme_Updates' ) );
if ( 'setup' !== $digi_step ) {
	wp_set_current_user( $digi_cron ? 0 : 1 );
	if ( ! $data['tools'] ) {
		$problems[] = 'Tools no cargó Digitalisimo\Tools\Theme_Updates (contrato §4.1)';
	}
}
$release = digi_release();
$next    = (string) ( $release['theme'] ?? '' );
$current = (string) ( $release['installed'] ?? '' );

switch ( $digi_step ) {
	case 'setup':
		// Subsitio, dos copias del tema (la segunda en una carpeta con punto, RL-8) y Tools activo en la red.
		if ( ! get_site( 2 ) ) {
			wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'sub/', 'Subsitio', 1 );
		}
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			mkdir( WPMU_PLUGIN_DIR, 0777, true );
		}
		copy( DIGI_DIR . '/mu-net.php', WPMU_PLUGIN_DIR . '/digi-net.php' );
		WP_Filesystem();
		foreach ( array( 'digitalisimo-theme', DIGI_COPY ) as $folder ) {
			wp_mkdir_p( get_theme_root() . '/' . $folder );
			$copied = copy_dir( DIGI_DIR . '/themes/digitalisimo-theme', get_theme_root() . '/' . $folder );
			if ( is_wp_error( $copied ) ) {
				$problems[] = "No se pudo copiar $folder: " . $copied->get_error_message();
			}
		}
		search_theme_directories( true );
		wp_clean_themes_cache();
		$activated = activate_plugin( 'digitalisimo-tools/digitalisimo-tools.php', '', true );
		if ( is_wp_error( $activated ) ) {
			$problems[] = 'Tools: ' . $activated->get_error_message();
		}
		switch_to_blog( 2 );
		update_option( 'allowedthemes', array( DIGI_COPY => true ) );
		switch_theme( DIGI_COPY );
		set_theme_mod( 'digi_net_mod', 'valor de prueba' );
		restore_current_blog();
		update_site_option( 'auto_update_themes', array() );
		digi_clear_caches();
		$data['themes'] = array_keys( wp_get_themes() );
		$data['sub']    = get_blog_option( 2, 'stylesheet' );
		break;

	case 'n1-main':
		// N1: el tema no está cargado en el sitio principal; Tools cubre las dos copias (D1).
		if ( $data['themeUpdater'] ) {
			$problems[] = 'el actualizador del tema está cargado en el sitio principal, que usa el tema por defecto';
		}
		digi_clear_caches();
		wp_update_themes();
		$raw = get_site_option( '_site_transient_update_themes' );
		foreach ( array( DIGI_COPY, 'digitalisimo-theme' ) as $stylesheet ) {
			$problems = array_merge( $problems, digi_entry_problems( $raw, $stylesheet ) );
		}
		if ( digi_api_calls( $digi_step ) ) {
			$problems[] = digi_api_calls( $digi_step ) . ' consultas a api.github.com con el índice disponible';
		}
		$data['response'] = is_object( $raw ) ? array_keys( (array) $raw->response ) : null;
		break;

	case 'n1-sub':
		// RL-12: un wp_update_themes() en el contexto del subsitio (con el tema activo) rehace el inventario de la red.
		if ( ! $data['themeUpdater'] ) {
			$problems[] = 'el actualizador del tema no se cargó en el subsitio que lo tiene activo';
		}
		digi_clear_caches();
		wp_update_themes();
		$raw = get_site_option( '_site_transient_update_themes' );
		foreach ( array( DIGI_COPY, 'digitalisimo-theme' ) as $stylesheet ) {
			$problems = array_merge( $problems, digi_entry_problems( $raw, $stylesheet ) );
		}
		if ( digi_api_calls( $digi_step ) ) {
			$problems[] = digi_api_calls( $digi_step ) . ' consultas a api.github.com con el índice disponible';
		}
		$data['response'] = is_object( $raw ) ? array_keys( (array) $raw->response ) : null;
		break;

	case 'n2-net':
		// N2 (red): una fila por copia, «Actualización disponible» y los botones; un nonce de otra copia termina en wp_die.
		$html = digi_catalog( 'network' );
		file_put_contents( DIGI_DIR . '/n2-network.html', $html );
		$rows = digi_rows( $html );
		$data['rows'] = count( $rows );
		if ( 2 !== count( $rows ) ) {
			$problems[] = 'la sección Tema de la red tiene ' . count( $rows ) . ' filas (se esperaban 2, una por copia)';
		}
		foreach ( array( DIGI_COPY, 'digitalisimo-theme' ) as $stylesheet ) {
			$row = '';
			foreach ( $rows as $candidate ) {
				if ( preg_match( '#theme=' . preg_quote( rawurlencode( $stylesheet ), '#' ) . '(&|"|\')#', $candidate ) ) {
					$row = $candidate;
				}
			}
			if ( '' === $row ) {
				$problems[] = "no hay fila con acciones para $stylesheet";
				continue;
			}
			if ( false === stripos( $row, 'Actualización disponible' ) ) {
				$problems[] = "$stylesheet: el estado no dice «Actualización disponible»";
			}
			if ( false === strpos( $row, 'digitalisimo_tools_theme_update' ) ) {
				$problems[] = "$stylesheet: falta el botón Actualizar";
			}
			if ( false === strpos( $row, 'digitalisimo_tools_theme_check' ) ) {
				$problems[] = "$stylesheet: falta «Buscar actualizaciones»";
			}
		}
		if ( preg_match( '#>\s*(Activar|Habilitar)\s*<#u', $html ) ) {
			$problems[] = 'la sección Tema ofrece activar o habilitar el tema';
		}
		$other = digi_handler( 'digitalisimo_tools_theme_update', array( 'theme' => DIGI_COPY, 'digitalisimo_context' => 'network', '_wpnonce' => wp_create_nonce( 'digitalisimo_tools_theme_update_digitalisimo-theme' ) ) );
		$data['otherNonce'] = $other;
		if ( 'wp_die' !== $other['end'] ) {
			$problems[] = 'un nonce de otra copia no terminó en wp_die: ' . $other['end'] . ' ' . $other['detail'];
		}
		break;

	case 'n2-sub':
		// N2 (subsitio): sólo el estado; una URL de actualizar fabricada en el contexto del sitio termina en wp_die.
		$html = digi_catalog( 'site' );
		file_put_contents( DIGI_DIR . '/n2-site.html', $html );
		if ( preg_match( '#digitalisimo_tools_theme_(update|install|check)#', $html, $m ) ) {
			$problems[] = 'el subsitio ofrece la acción ' . $m[0];
		}
		if ( false === stripos( $html, 'administrador de la red' ) ) {
			$problems[] = 'el subsitio no dice que la instala y actualiza el administrador de la red';
		}
		$data['rows'] = count( digi_rows( $html ) );
		$forged       = digi_handler( 'digitalisimo_tools_theme_update', array( 'theme' => DIGI_COPY, 'digitalisimo_context' => 'site', '_wpnonce' => wp_create_nonce( 'digitalisimo_tools_theme_update_' . DIGI_COPY ) ) );
		$data['forged'] = $forged;
		if ( 'wp_die' !== $forged['end'] ) {
			$problems[] = 'una URL de actualizar fabricada en el subsitio no terminó en wp_die: ' . $forged['end'] . ' ' . $forged['detail'];
		}
		break;

	case 'n5':
		// N5: la ficha de «Ver detalles» (iframe de Temas). El handler termina con exit: la salida se guarda al apagar.
		$_GET     = array( 'action' => 'digitalisimo_theme_details', 'theme' => DIGI_COPY );
		$_REQUEST = $_GET;
		$GLOBALS['digi_details_done'] = false;
		register_shutdown_function(
			static function () {
				$html = '';
				while ( ob_get_level() > 0 ) {
					$html = (string) ob_get_clean() . $html;
				}
				file_put_contents( DIGI_DIR . '/n5-details.html', $html );
				$problems = array();
				if ( ! preg_match( '#<body[^>]*class="[^"]*digitalisimo-admin#', $html ) ) {
					$problems[] = 'la ficha no lleva digitalisimo-admin en body';
				}
				if ( preg_match( '#(Warning|Deprecated|Notice|Fatal error)\s*:#', $html, $m ) ) {
					$problems[] = 'la ficha imprime un aviso de PHP: ' . $m[0];
				}
				if ( ! preg_match( '#rel="noopener noreferrer"#', $html ) ) {
					$problems[] = 'el enlace a la Release no lleva rel="noopener noreferrer"';
				}
				$code = http_response_code();
				if ( $code && 200 !== $code ) {
					$problems[] = "la ficha respondió $code";
				}
				if ( ! empty( $GLOBALS['digi_details_died'] ) ) {
					$problems[] = 'la ficha terminó en wp_die: ' . $GLOBALS['digi_details_died'];
				}
				digi_save( 'n5', $problems, array( 'status' => $code, 'bytes' => strlen( $html ) ) );
			}
		);
		add_filter( 'wp_die_handler', static function () { return static function ( $message ) { $GLOBALS['digi_details_died'] = is_wp_error( $message ) ? $message->get_error_message() : wp_strip_all_tags( (string) $message ); exit; }; }, PHP_INT_MAX );
		remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 ); // La salida la recoge la función de apagado de arriba.
		ob_start();
		digi_admin_init();
		do_action( 'admin_post_digitalisimo_theme_details' );
		exit; // Por si el handler no termina solo.

	case 'n6-switch':
		switch_theme( 'digitalisimo-theme' );
		$data['stylesheet'] = get_option( 'stylesheet' );
		break;

	case 'n6-check':
		// N6: con el tema activo en el sitio principal manda su actualizador y Tools no toca su entrada; con el filtro
		// del tema retirado (actualizador roto), Tools escribe la entrada de respaldo (RL-3).
		if ( ! $data['themeUpdater'] ) {
			$problems[] = 'el actualizador del tema no se cargó con el tema activo en el sitio principal';
		}
		digi_clear_caches();
		$GLOBALS['digi_net_spy'] = array();
		wp_update_themes();
		$raw = get_site_option( '_site_transient_update_themes' );
		$problems = array_merge( $problems, digi_entry_problems( $raw, 'digitalisimo-theme' ) );
		$theme_view = null;
		$tools_view = null;
		foreach ( $GLOBALS['digi_net_spy'] as $spy ) {
			if ( 'pre_set_site_transient_update_themes' !== $spy['filter'] ) {
				continue;
			}
			if ( 'tras-tema' === $spy['where'] ) {
				$theme_view = $spy['seen']['digitalisimo-theme'] ?? null;
			} else {
				$tools_view = $spy['seen']['digitalisimo-theme'] ?? null;
			}
		}
		if ( ! $theme_view ) {
			$problems[] = 'tras el filtro del tema (30) no hay entrada para la copia cargada';
		} elseif ( $theme_view !== $tools_view ) {
			$problems[] = 'Tools (31) cambió la entrada que escribió el tema: ' . wp_json_encode( array( 'tema' => $theme_view, 'tools' => $tools_view ) );
		}
		// Actualizador roto: sin los filtros del tema, la entrada la escribe Tools.
		if ( class_exists( 'Digitalisimo_Theme_Updater', false ) ) {
			foreach ( array( 'pre_set_site_transient_update_themes', 'site_transient_update_themes' ) as $hook ) {
				remove_filter( $hook, array( 'Digitalisimo_Theme_Updater', 'check' ), 30 );
			}
			remove_filter( 'update_themes_github.com', array( 'Digitalisimo_Theme_Updater', 'uri_update' ), 30 );
		}
		digi_clear_caches();
		wp_update_themes();
		$raw = get_site_option( '_site_transient_update_themes' );
		$broken = digi_entry_problems( $raw, 'digitalisimo-theme' );
		foreach ( $broken as $p ) {
			$problems[] = 'sin el actualizador del tema: ' . $p;
		}
		$data['spies'] = count( $GLOBALS['digi_net_spy'] );
		break;

	case 'n6-restore':
		switch_theme( WP_DEFAULT_THEME );
		digi_clear_caches();
		$data['stylesheet'] = get_option( 'stylesheet' );
		break;

	case 'n3f':
		// N3f: move() falla al dejar el paquete en la carpeta con otro nombre: WP_Error y nada cambia (RL-1).
		digi_state( array( 'moveFail' => true ) );
		$data['before'] = array( 'copy' => digi_version( DIGI_COPY ), 'main' => digi_version( 'digitalisimo-theme' ), 'sub' => get_blog_option( 2, 'stylesheet' ) );
		$run = digi_handler( 'digitalisimo_tools_theme_update', array( 'theme' => DIGI_COPY, 'digitalisimo_context' => 'network', '_wpnonce' => wp_create_nonce( 'digitalisimo_tools_theme_update_' . DIGI_COPY ) ) );
		digi_state( array() );
		$data['run'] = $run;
		if ( 'redirect' !== $run['end'] || false === strpos( $run['detail'], 'digitalisimo-theme-notice=update_failed' ) ) {
			$problems[] = 'con move() fallido, el handler debía volver con update_failed: ' . $run['end'] . ' ' . $run['detail'];
		}
		break;

	case 'n3f-check':
		$moves = is_file( DIGI_DIR . '/movefail.log' ) ? trim( (string) file_get_contents( DIGI_DIR . '/movefail.log' ) ) : '';
		$data['moves'] = $moves;
		if ( '' === $moves ) {
			$problems[] = 'la prueba no llegó a simular el fallo de move() (¿keep_folder no usa $wp_filesystem->move()?)';
		}
		foreach ( array( DIGI_COPY, 'digitalisimo-theme' ) as $folder ) {
			if ( digi_version( $folder ) !== $current ) {
				$problems[] = "$folder quedó en " . var_export( digi_version( $folder ), true ) . " (se esperaba $current intacta)";
			}
		}
		if ( DIGI_COPY !== get_blog_option( 2, 'stylesheet' ) ) {
			$problems[] = 'el subsitio cambió de tema: ' . get_blog_option( 2, 'stylesheet' );
		}
		break;

	case 'n4a':
		// N4: la actualización automática sólo actúa si el tema está en auto_update_themes.
		$data['updater'] = array( 'disabled' => ( new WP_Automatic_Updater() )->is_disabled(), 'constant' => defined( 'AUTOMATIC_UPDATER_DISABLED' ) ? AUTOMATIC_UPDATER_DISABLED : null, 'fileMods' => wp_is_file_mod_allowed( 'automatic_updater' ), 'vcs' => ( new WP_Automatic_Updater() )->is_vcs_checkout( get_theme_root() ) );
		if ( $data['updater']['disabled'] ) {
			$problems[] = 'WordPress tiene desactivada la actualización automática en este sitio: N4 no se puede comprobar';
		}
		update_site_option( 'auto_update_themes', array() );
		digi_clear_caches();
		wp_maybe_auto_update();
		$data['after'] = array( 'main' => digi_version( 'digitalisimo-theme' ), 'copy' => digi_version( DIGI_COPY ) );
		if ( $data['after']['main'] !== $current || $data['after']['copy'] !== $current ) {
			$problems[] = 'se actualizó solo sin estar en auto_update_themes: ' . wp_json_encode( $data['after'] );
		}
		break;

	case 'n4c':
		// N4 con requires 99.0: la entrada lleva disable_autoupdate y no se instala sola (RL-9).
		digi_state( array( 'requires' => '99.0' ) );
		update_site_option( 'auto_update_themes', array( 'digitalisimo-theme' ) );
		digi_clear_caches();
		wp_update_themes();
		$raw   = get_site_option( '_site_transient_update_themes' );
		$entry = is_object( $raw ) && isset( $raw->response['digitalisimo-theme'] ) ? (array) $raw->response['digitalisimo-theme'] : null;
		$data['entry'] = $entry;
		if ( ! $entry || empty( $entry['disable_autoupdate'] ) ) {
			$problems[] = 'con requires 99.0 la entrada no lleva disable_autoupdate: ' . wp_json_encode( $entry );
		}
		wp_maybe_auto_update();
		$data['after'] = digi_version( 'digitalisimo-theme' );
		if ( $data['after'] !== $current ) {
			$problems[] = "se actualizó sola una versión incompatible (quedó en {$data['after']})";
		}
		digi_state( array() );
		break;

	case 'n4b':
		update_site_option( 'auto_update_themes', array( 'digitalisimo-theme' ) );
		digi_clear_caches();
		wp_maybe_auto_update();
		$data['after'] = array( 'main' => digi_version( 'digitalisimo-theme' ), 'copy' => digi_version( DIGI_COPY ) );
		if ( $data['after']['main'] !== $next ) {
			$problems[] = "en auto_update_themes, digitalisimo-theme no pasó a $next: " . wp_json_encode( $data['after'] );
		}
		if ( $data['after']['copy'] !== $current ) {
			$problems[] = DIGI_COPY . ' se actualizó sin estar en auto_update_themes';
		}
		$strays = array_values( array_diff( array_map( 'basename', glob( get_theme_root() . '/digitalisimo-theme*', GLOB_ONLYDIR ) ), array( 'digitalisimo-theme', DIGI_COPY ) ) );
		if ( $strays ) {
			$problems[] = 'carpetas sobrantes: ' . implode( ', ', $strays );
		}
		update_site_option( 'auto_update_themes', array() );
		break;

	case 'n3':
		// N3: «Actualizar» de Tools en la red sobre la copia con otro nombre de carpeta, activa en el subsitio.
		digi_clear_caches();
		$baseline = array(
			'sub'          => get_blog_option( 2, 'stylesheet' ),
			'mods'         => digi_mods_md5( 2, DIGI_COPY ),
			'allowed'      => md5( serialize( get_site_option( 'allowedthemes' ) ) ),
			'allowedBlog'  => md5( serialize( get_blog_option( 2, 'allowedthemes' ) ) ),
		);
		file_put_contents( DIGI_DIR . '/n3-baseline.json', wp_json_encode( $baseline ) );
		$run = digi_handler( 'digitalisimo_tools_theme_update', array( 'theme' => DIGI_COPY, 'digitalisimo_context' => 'network', '_wpnonce' => wp_create_nonce( 'digitalisimo_tools_theme_update_' . DIGI_COPY ) ) );
		$data['run'] = $run;
		if ( 'redirect' !== $run['end'] || false === strpos( $run['detail'], 'digitalisimo-theme-notice=updated' ) ) {
			$problems[] = 'el handler de Tools no volvió con updated: ' . $run['end'] . ' ' . $run['detail'];
		}
		break;

	case 'n3-check':
		$baseline = (array) json_decode( (string) file_get_contents( DIGI_DIR . '/n3-baseline.json' ), true );
		$after    = array(
			'sub'         => get_blog_option( 2, 'stylesheet' ),
			'mods'        => digi_mods_md5( 2, DIGI_COPY ),
			'allowed'     => md5( serialize( get_site_option( 'allowedthemes' ) ) ),
			'allowedBlog' => md5( serialize( get_blog_option( 2, 'allowedthemes' ) ) ),
		);
		$data['baseline'] = $baseline;
		$data['after']    = $after;
		if ( digi_version( DIGI_COPY ) !== $next ) {
			$problems[] = DIGI_COPY . ' quedó en ' . var_export( digi_version( DIGI_COPY ), true ) . " (se esperaba $next en la misma carpeta)";
		}
		foreach ( array( 'sub' => 'stylesheet del subsitio', 'mods' => 'md5 de theme_mods_' . DIGI_COPY, 'allowed' => 'allowedthemes de la red', 'allowedBlog' => 'allowedthemes del subsitio' ) as $key => $label ) {
			if ( ( $baseline[ $key ] ?? null ) !== $after[ $key ] ) {
				$problems[] = "$label cambió";
			}
		}
		$maintenance = false;
		foreach ( file_exists( DIGI_DIR . '/upgrade.jsonl' ) ? file( DIGI_DIR . '/upgrade.jsonl' ) : array() as $line ) {
			$row = json_decode( $line, true );
			if ( $row && 'n3' === $row['step'] && DIGI_COPY === $row['theme'] ) {
				$maintenance = $maintenance || ! empty( $row['maintenance'] );
			}
		}
		if ( ! $maintenance ) {
			$problems[] = '.maintenance no existía durante la actualización (bulk_upgrade en red)';
		}
		if ( file_exists( ABSPATH . '.maintenance' ) ) {
			$problems[] = '.maintenance quedó después de la actualización';
		}
		$strays = array_values( array_diff( array_map( 'basename', glob( get_theme_root() . '/digitalisimo-theme*', GLOB_ONLYDIR ) ), array( 'digitalisimo-theme', DIGI_COPY ) ) );
		if ( $strays ) {
			$problems[] = 'carpetas sobrantes: ' . implode( ', ', $strays );
		}
		break;

	default:
		$problems[] = "paso desconocido: $digi_step";
}

digi_save( $digi_step, $problems, $data );
