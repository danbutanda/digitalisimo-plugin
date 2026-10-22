<?php
/**
 * Pasos de front-network.sh (Playground con run-blueprint, un proceso PHP por paso, sin Chrome). DIGI_STEP nombra el
 * paso y DIGI_BLOG el sitio (1 principal, 2 en /sub/, 3 en /tarde/, creado después); cada paso deja en result.json lo
 * que observó y sus problemas. Tipos de paso:
 *   setup                      red con los sitios 1 y 2, Elements activo en la red, usuarios y el contador de vaciados
 *   visit-*                    una visita al front del sitio DIGI_BLOG (en `init` la transición agenda su evento)
 *   cron-*                     lo que haría wp-cron.php en el sitio DIGI_BLOG: ejecuta sus eventos vencidos
 *   post-<caso>                un envío a admin-post.php con usuario, nonce y datos del caso (redirección capturada)
 *   screen-site, screen-network  la pantalla del sitio 2 y la de la red (N5, N7)
 *   create-3                   crea el sitio 3 después del cambio de la red (N3)
 *   post-network-privacy       la red enciende `page_visits_no_consent` (F7a, «Privacidad»), sin tocar lo demás
 *   single-setup, single       sitio único (otro blueprint): la pantalla sin «Heredar de la red»
 * Nada de esto se publica.
 */

$digi_step  = (string) getenv( 'DIGI_STEP' );
$digi_blog  = (int) getenv( 'DIGI_BLOG' );
$digi_paths = array( 1 => '/', 2 => '/sub/', 3 => '/tarde/' );
$digi_path  = $digi_paths[ $digi_blog ] ?? '/';
$digi_admin = 0 === strpos( $digi_step, 'post-' ) || 0 === strpos( $digi_step, 'screen-' ) || 'single' === $digi_step;
$digi_cron  = 0 === strpos( $digi_step, 'cron-' );
$_SERVER['HTTP_HOST'] = 'playground.test';
if ( $digi_admin ) {
	define( 'WP_ADMIN', true );
	if ( 'screen-network' === $digi_step ) {
		define( 'WP_NETWORK_ADMIN', true );
	}
	$_SERVER['REQUEST_URI'] = 'screen-network' === $digi_step ? '/wp-admin/network/admin.php' : $digi_path . ( 0 === strpos( $digi_step, 'post-' ) ? 'wp-admin/admin-post.php' : 'wp-admin/admin.php' );
} elseif ( $digi_cron ) {
	define( 'DOING_CRON', true );
	$_SERVER['REQUEST_URI'] = $digi_path . 'wp-cron.php';
} else {
	$_SERVER['REQUEST_URI'] = $digi_path;
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
if ( is_admin() ) {
	require_once ABSPATH . 'wp-admin/includes/admin.php';
}

const DIGI_DIR = '/wordpress/wp-content/probe';

class Digi_Front_Redirect extends Exception {}
class Digi_Front_Die extends Exception {}

function digi_save( $key, array $problems, array $data = array() ) {
	$file   = DIGI_DIR . '/result.json';
	$result = is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	$result[ $key ] = array( 'problems' => array_values( $problems ), 'data' => $data );
	file_put_contents( $file, wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

function digi_state_file() {
	$file = DIGI_DIR . '/state.json';
	return is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
}

function digi_state_put( array $state ) {
	file_put_contents( DIGI_DIR . '/state.json', wp_json_encode( $state ) );
}

/** Vaciados de archivos del constructor por sitio, contados por el mu-plugin desde el principio. */
function digi_clears() {
	$file   = DIGI_DIR . '/clear-cache.log';
	$counts = array();
	foreach ( is_file( $file ) ? file( $file, FILE_IGNORE_NEW_LINES ) : array() as $line ) {
		$blog            = (int) $line;
		$counts[ $blog ] = ( $counts[ $blog ] ?? 0 ) + 1;
	}
	return $counts;
}

/** Lo aplicado y lo agendado en un sitio (sin cambiar el sitio en curso al terminar). */
function digi_site_state( $blog ) {
	$switched = get_current_blog_id() !== $blog;
	if ( $switched ) {
		switch_to_blog( $blog );
	}
	$transition = '\\Digitalisimo\\Elements\\Front\\Transition';
	$settings   = '\\Digitalisimo\\Elements\\Front\\Settings';
	$out        = array(
		'applied'   => class_exists( $transition ) ? $transition::applied() : null,
		'scheduled' => class_exists( $transition ) ? wp_next_scheduled( $transition::HOOK ) : null,
		'stored'    => get_option( 'digitalisimo_elements_front', null ),
		'inherits'  => class_exists( $settings ) ? $settings::inherits_network( 'pro_conditional' ) : null,
		'wanted'    => class_exists( $settings ) ? $settings::get( 'pro_conditional' ) : null,
		// F7a: «Registrar visitas sin gestor de consentimiento» (sección «Privacidad»; no transita).
		'visits'          => class_exists( $settings ) ? $settings::get( 'page_visits_no_consent' ) : null,
		'visits_inherits' => class_exists( $settings ) ? $settings::inherits_network( 'page_visits_no_consent' ) : null,
	);
	if ( $switched ) {
		restore_current_blog();
	}
	return $out;
}

/** Envía un formulario a admin-post.php como el navegador: usuario, POST, nonce; captura redirección o wp_die. */
function digi_post( $user_login, array $post, $nonce_action ) {
	$user = get_user_by( 'login', $user_login );
	wp_set_current_user( $user ? $user->ID : 0 );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	if ( null !== $nonce_action ) {
		$post['_wpnonce'] = is_string( $nonce_action ) && 0 === strpos( $nonce_action, 'raw:' ) ? substr( $nonce_action, 4 ) : wp_create_nonce( $nonce_action );
	}
	$_POST    = wp_slash( $post );
	$_REQUEST = $_POST;
	add_filter( 'wp_redirect', static function ( $location ) { throw new Digi_Front_Redirect( $location ); }, PHP_INT_MAX );
	add_filter( 'wp_die_handler', static function () { return static function ( $message, $title = '', $args = array() ) { throw new Digi_Front_Die( wp_json_encode( array( 'message' => is_string( $message ) ? $message : '', 'status' => is_array( $args ) && isset( $args['response'] ) ? (int) $args['response'] : 500 ) ) ); }; } );
	$out = array( 'redirect' => '', 'die' => null );
	try {
		require ABSPATH . 'wp-admin/admin-post.php';
	} catch ( Digi_Front_Redirect $redirect ) {
		$out['redirect'] = $redirect->getMessage();
	} catch ( Digi_Front_Die $die ) {
		$out['die'] = json_decode( $die->getMessage(), true );
	}
	return $out;
}

/** Texto visible y enlaces de una pantalla, para N5 y N7. */
function digi_screen( callable $render ) {
	add_filter( 'wp_die_handler', static function () { return static function ( $message ) { throw new Digi_Front_Die( is_string( $message ) ? $message : 'wp_die' ); }; } );
	ob_start();
	try {
		$render();
	} catch ( Throwable $e ) {
		echo 'EXCEPCIÓN ' . esc_html( $e->getMessage() );
	}
	return (string) ob_get_clean();
}

function digi_screen_problems( $label, $html, $want_inherit ) {
	$problems = array();
	$inherit  = false !== stripos( $html, 'Heredar de la red' );
	if ( $inherit !== $want_inherit ) {
		$problems[] = "$label: «Heredar de la red» " . ( $want_inherit ? 'falta' : 'no debe aparecer' );
	}
	$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
	if ( preg_match( '/\bElementor\b/u', $text . ' ' . $html, $m ) ) {
		$problems[] = "$label: la pantalla nombra «Elementor»";
	}
	if ( preg_match_all( '/\b(?:href|action|src)=["\']([^"\']+)["\']/i', $html, $links ) ) {
		$home = wp_parse_url( network_home_url(), PHP_URL_HOST );
		foreach ( $links[1] as $link ) {
			$host = wp_parse_url( html_entity_decode( $link ), PHP_URL_HOST );
			if ( $host && $host !== $home ) {
				$problems[] = "$label: enlace externo $link";
			}
		}
	}
	$vendors = json_decode( (string) @file_get_contents( DIGI_DIR . '/vendors.json' ), true ); // phpcs:ignore
	foreach ( array_merge( (array) ( $vendors['hosts'] ?? array() ), (array) ( $vendors['future'] ?? array() ) ) as $domain ) {
		if ( is_string( $domain ) && '' !== $domain && false !== stripos( $html, $domain ) ) {
			$problems[] = "$label: nombra el dominio $domain";
		}
	}
	if ( false !== strpos( $html, 'EXCEPCIÓN' ) ) {
		$problems[] = "$label: la pantalla lanzó una excepción";
	}
	return $problems;
}

/**
 * F7a: la sección «Privacidad» con la casilla `page_visits_no_consent` en la opción del formulario, y su «Heredar de la
 * red» sólo donde se pide (el sitio de una red).
 */
function digi_privacy_problems( $label, $html, $option, $want_inherit ) {
	$problems = array();
	if ( false === strpos( $html, 'id="digitalisimo-front-privacy"' ) ) {
		$problems[] = "$label: falta la sección «Privacidad»";
	}
	if ( false === strpos( $html, 'name="' . $option . '[page_visits_no_consent]"' ) ) {
		$problems[] = "$label: falta la casilla {$option}[page_visits_no_consent]";
	}
	if ( false === strpos( $html, 'Registrar visitas sin gestor de consentimiento' ) ) {
		$problems[] = "$label: falta el texto «Registrar visitas sin gestor de consentimiento»";
	}
	$inherit = false !== strpos( $html, 'name="digitalisimo_elements_front[inherit][page_visits_no_consent]"' );
	if ( $inherit !== $want_inherit ) {
		$problems[] = "$label: «Heredar de la red» de page_visits_no_consent " . ( $want_inherit ? 'falta' : 'no debe aparecer' );
	}
	return $problems;
}

$problems = array();
$data     = array();
$ns       = '\\Digitalisimo\\Elements\\Front\\';

switch ( true ) {
	case 'setup' === $digi_step:
		if ( ! get_site( 2 ) ) {
			wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'sub/', 'Subsitio', 1 );
		}
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			mkdir( WPMU_PLUGIN_DIR, 0777, true );
		}
		// Contador de vaciados por sitio (lo que escuchan las cachés de página y SEO).
		file_put_contents( WPMU_PLUGIN_DIR . '/digi-front-clears.php', '<?php add_action( "elementor/core/files/clear_cache", static function () { file_put_contents( "/wordpress/wp-content/probe/clear-cache.log", get_current_blog_id() . "\n", FILE_APPEND ); } );' );
		$result = activate_plugin( 'digitalisimo-elements/pro-elements.php', '', true );
		if ( is_wp_error( $result ) ) {
			$problems[] = 'No se pudo activar Elements en la red: ' . $result->get_error_message();
		}
		foreach ( array( 'editor2' => 'editor', 'siteadmin2' => 'administrator' ) as $login => $role ) {
			$id = username_exists( $login ) ?: wp_create_user( $login, wp_generate_password(), $login . '@example.com' );
			add_user_to_blog( 2, (int) $id, $role );
		}
		$data = array( 'sites' => array_map( static function ( $site ) { return $site->path; }, get_sites() ) );
		break;

	case 0 === strpos( $digi_step, 'visit-' ):
		$data = array( 'blog' => get_current_blog_id(), 'state' => digi_site_state( get_current_blog_id() ) );
		if ( get_current_blog_id() !== $digi_blog ) {
			$problems[] = "la visita debía resolverse en el sitio $digi_blog y fue en " . get_current_blog_id();
		}
		break;

	case $digi_cron:
		$ran = array();
		foreach ( (array) wp_get_ready_cron_jobs() as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $events ) {
				foreach ( $events as $event ) {
					wp_unschedule_event( $timestamp, $hook, $event['args'] );
					do_action_ref_array( $hook, $event['args'] );
					$ran[] = $hook;
				}
			}
		}
		$data = array( 'blog' => get_current_blog_id(), 'ran' => $ran, 'state' => digi_site_state( get_current_blog_id() ) );
		break;

	case 0 === strpos( $digi_step, 'post-' ):
		$page   = $ns . 'Settings_Page';
		$option = 'digitalisimo_elements_front';
		$all    = array( 'pro_conditional', 'font_swap', 'svg_icons', 'lazy_libs', 'element_cache_off', 'image_loading_off' );
		$cases = array(
			// N4: un editor no guarda los ajustes del sitio.
			'post-editor'         => array( 'editor2', array( 'action' => 'digitalisimo_elements_front_save', $option => array( 'pro_conditional' => '0' ) ), 'digitalisimo_elements_front_save|2', 'die', 403 ),
			// N4: un administrador del sitio no guarda la red.
			'post-siteadmin-net'  => array( 'siteadmin2', array( 'action' => 'digitalisimo_elements_front_save_network', 'digitalisimo_elements_front_defaults' => array( 'pro_conditional' => '0' ) ), 'digitalisimo_elements_front_save_network|network', 'die', 403 ),
			// N4: un nonce de otro sitio o de otra acción no vale.
			'post-bad-nonce'      => array( 'admin', array( 'action' => 'digitalisimo_elements_front_save', $option => array( 'pro_conditional' => '0' ) ), 'digitalisimo_elements_front_save|1', 'die', 403 ),
			// N2 y N4: el sitio 1 personaliza pro_conditional y, apagada, page_visits_no_consent (F7a), y los conserva
			// tras cambiar la red.
			'post-site1-own'      => array( 'admin', array( 'action' => 'digitalisimo_elements_front_save', $option => array( 'pro_conditional' => '1', 'font_swap' => '1', 'svg_icons' => '0', 'lazy_libs' => '0', 'element_cache_off' => '0', 'image_loading_off' => '0', 'page_visits_no_consent' => '0', 'inherit' => array( 'pro_conditional' => '0', 'font_swap' => '1', 'svg_icons' => '1', 'lazy_libs' => '1', 'element_cache_off' => '1', 'image_loading_off' => '1', 'page_visits_no_consent' => '0' ) ) ), 'digitalisimo_elements_front_save|1', 'redirect', 'wp-admin/admin.php?page=digitalisimo-elements-front' ),
			// N1 y N4: la red apaga pro_conditional (la red sólo escribe su opción).
			'post-network-off'    => array( 'admin', array( 'action' => 'digitalisimo_elements_front_save_network', 'digitalisimo_elements_front_defaults' => array( 'pro_conditional' => '0', 'font_swap' => '1' ) ), 'digitalisimo_elements_front_save_network|network', 'redirect', 'wp-admin/network/admin.php?page=digitalisimo-elements-front' ),
			// F7a (N1, N3, N6): la red enciende «Registrar visitas sin gestor de consentimiento» con los mismos valores en
			// lo demás; los sitios que heredan la toman sin transición ni vaciado.
			'post-network-privacy' => array( 'admin', array( 'action' => 'digitalisimo_elements_front_save_network', 'digitalisimo_elements_front_defaults' => array( 'pro_conditional' => '0', 'font_swap' => '1', 'page_visits_no_consent' => '1' ) ), 'digitalisimo_elements_front_save_network|network', 'redirect', 'wp-admin/network/admin.php?page=digitalisimo-elements-front' ),
		);
		if ( ! isset( $cases[ $digi_step ] ) ) {
			$problems[] = "caso desconocido $digi_step";
			break;
		}
		list( $login, $body, $nonce, $want, $detail ) = $cases[ $digi_step ];
		$before = digi_clears();
		$result = digi_post( $login, $body, $nonce );
		$data   = array( 'result' => $result, 'blog' => get_current_blog_id(), 'clears_before' => $before, 'clears_after' => digi_clears(), 'site1' => digi_site_state( 1 ), 'network' => get_site_option( 'digitalisimo_elements_front_defaults', null ) );
		if ( 'die' === $want ) {
			if ( empty( $result['die'] ) || (int) $result['die']['status'] !== $detail ) {
				$problems[] = "$digi_step: se esperaba un rechazo $detail y hubo " . wp_json_encode( $result );
			}
		} else {
			$expected = 0 === strpos( $digi_step, 'post-network-' ) ? network_admin_url( 'admin.php?page=digitalisimo-elements-front' ) : get_admin_url( $digi_blog, 'admin.php?page=digitalisimo-elements-front' );
			if ( 0 !== strpos( $result['redirect'], $expected ) ) {
				$problems[] = "$digi_step: redirección a «{$result['redirect']}», se esperaba la misma superficie «{$expected}…»";
			}
		}
		if ( 0 === strpos( $digi_step, 'post-network-' ) && $before !== digi_clears() ) {
			$problems[] = "$digi_step: guardar la red no debe vaciar ningún sitio en esa petición";
		}
		$data['sites'] = array( 1 => digi_site_state( 1 ), 2 => digi_site_state( 2 ) ) + ( get_site( 3 ) ? array( 3 => digi_site_state( 3 ) ) : array() );
		break;

	case 'create-3' === $digi_step:
		if ( ! get_site( 3 ) ) {
			wpmu_create_blog( DOMAIN_CURRENT_SITE, PATH_CURRENT_SITE . 'tarde/', 'Sitio tardío', 1 );
		}
		$data = array( 'sites' => array_map( static function ( $site ) { return $site->path; }, get_sites() ) );
		break;

	case 'screen-site' === $digi_step:
		wp_set_current_user( 1 );
		set_current_screen( 'dashboard' );
		$html = digi_screen( array( $ns . 'Settings_Page', 'render' ) );
		file_put_contents( DIGI_DIR . '/screen-site-' . get_current_blog_id() . '.html', $html );
		$problems = array_merge( digi_screen_problems( 'pantalla del sitio ' . get_current_blog_id(), $html, true ), digi_privacy_problems( 'pantalla del sitio ' . get_current_blog_id(), $html, 'digitalisimo_elements_front', true ) );
		$data     = array( 'blog' => get_current_blog_id(), 'bytes' => strlen( $html ) );
		break;

	case 'screen-network' === $digi_step:
		wp_set_current_user( 1 );
		set_current_screen( 'dashboard-network' );
		$fields   = digi_screen( array( $ns . 'Settings', 'network_fields' ) );
		$html     = digi_screen( array( $ns . 'Settings_Page', 'render_network' ) );
		file_put_contents( DIGI_DIR . '/screen-network.html', $html );
		$problems = array_merge( digi_screen_problems( 'campos de la red (network_fields)', $fields, false ), digi_screen_problems( 'pantalla de la red', $html, false ) );
		if ( false === strpos( $html, 'digitalisimo_elements_front_defaults[pro_conditional]' ) ) {
			$problems[] = 'pantalla de la red: faltan los interruptores de red';
		}
		$problems = array_merge( $problems, digi_privacy_problems( 'pantalla de la red', $html, 'digitalisimo_elements_front_defaults', false ) );
		// Tras post-network-privacy la casilla de la red sale marcada (lo guardado, no el predeterminado).
		if ( ! preg_match( '/<input[^>]*id="digitalisimo-front-network-page-visits-no-consent"[^>]*checked="checked"/', $html ) ) {
			$problems[] = 'pantalla de la red: la casilla de privacidad no refleja el valor guardado (encendido)';
		}
		$data = array( 'network_admin' => is_network_admin(), 'bytes' => strlen( $html ) );
		break;

	case 'single-setup' === $digi_step:
		$result = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
		if ( is_wp_error( $result ) ) {
			$problems[] = 'No se pudo activar Elements: ' . $result->get_error_message();
		}
		break;

	case 'single' === $digi_step:
		$page = $ns . 'Settings_Page';
		wp_set_current_user( 1 );
		set_current_screen( 'dashboard' );
		$html = digi_screen( array( $page, 'render' ) );
		file_put_contents( DIGI_DIR . '/screen-single.html', $html );
		$problems = array_merge( digi_screen_problems( 'pantalla del sitio único', $html, false ), digi_privacy_problems( 'pantalla del sitio único', $html, 'digitalisimo_elements_front', false ) );
		if ( is_multisite() ) {
			$problems[] = 'el paso single debe correr en un sitio único';
		}
		$data = array( 'bytes' => strlen( $html ) );
		break;

	default:
		$problems[] = "paso desconocido $digi_step";
}

digi_save( $digi_step, $problems, $data + array( 'clears' => digi_clears() ) );
