<?php
/**
 * Plugin Name: Sonda de la medición del front (sólo Playground)
 * Description: mu-plugin de tests/playground/front-budget.sh. Nunca forma parte de un plugin.
 *
 * - En `wp_footer` 99 imprime `<!-- digitalisimo-pro-gate {…} -->` con `Pro_Gate::reasons()` (el plugin no imprime
 *   nada de diagnóstico, §13 V3).
 * - `?digi_probe=state&token=…`: valor aplicado y efectivo, transición pendiente, eventos agendados
 *   (`wp_next_scheduled()`), análisis de iconos, filtros `default_option_` presentes, filas del núcleo y el respaldo de
 *   JavaScript de SEO.
 * - `?digi_probe=header&key=<caso>&token=…` (THEME=1): deja con `include/general` sólo la cabecera de ese caso.
 * - Fuerza `digitalisimo_elements_pro_frontend_needed` en la página `digi-fb-forced`, da por enviados los correos
 *   (`pre_wp_mail`) y apaga el disparo de WP-Cron desde las visitas: el arnés ejecuta `wp-cron.php` cuando toca, así la
 *   medición no depende de un lazo de red de Playground.
 *
 * Sólo existe dentro de Playground: la instala `front-budget/activate.php` en `wp-content/mu-plugins` y exige el
 * token aleatorio que el arnés deja en `mode.json`.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}

/** Modo de la corrida (lo escribe front-budget.sh; el blueprint no recibe variables de entorno). */
function digi_fb_probe_mode() {
	static $mode = null;
	if ( null === $mode ) {
		$raw  = @file_get_contents( WP_CONTENT_DIR . '/probe/mode.json' ); // phpcs:ignore
		$mode = is_string( $raw ) ? json_decode( $raw, true ) : null;
		$mode = is_array( $mode ) ? $mode : array();
	}
	return $mode;
}

add_filter( 'pre_wp_mail', '__return_true' );

add_filter(
	'digitalisimo_elements_pro_frontend_needed',
	static function ( $forced ) {
		return function_exists( 'is_page' ) && is_page( 'digi-fb-forced' ) ? true : $forced;
	}
);

add_action(
	'wp_footer',
	static function () {
		global $wpdb;
		$gate = 'Digitalisimo\\Elements\\Front\\Pro_Gate';
		$out  = array( 'gate' => class_exists( $gate ) );
		if ( $out['gate'] ) {
			$out['reasons'] = $gate::reasons();
			$out['needed']  = $gate::needed();
		}
		// Consultas de la petición hasta el pie (F7a, criterio 2): el arnés las compara con las de 4.3.0.163. Se toman
		// antes de construir el comentario, así que la sonda no suma ninguna propia.
		$out['queries'] = isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries : -1;
		echo "\n<!-- digitalisimo-pro-gate " . wp_json_encode( $out, JSON_UNESCAPED_SLASHES ) . " -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	99
);

// Consultas de la petición completa (hasta `shutdown`), por URL, en $OUT/queries.jsonl: lo que corre después del pie
// (cachés, transitorios) también cuenta. Sólo peticiones del front, sin la sonda ni wp-cron.
add_action(
	'shutdown',
	static function () {
		global $wpdb;
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || false !== strpos( $uri, 'digi_probe=' ) || 0 !== strpos( $uri, '/digi-fb-' ) ) {
			return;
		}
		@file_put_contents( WP_CONTENT_DIR . '/probe/queries.jsonl', wp_json_encode( array( 'uri' => $uri, 'queries' => (int) $wpdb->num_queries, 'logged_in' => is_user_logged_in() ) ) . "\n", FILE_APPEND ); // phpcs:ignore
	},
	PHP_INT_MAX
);

add_action( 'wp_loaded', 'digi_fb_probe_route', 99 );

function digi_fb_probe_route() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['digi_probe'] ) ) {
		return;
	}
	$mode  = digi_fb_probe_mode();
	$token = isset( $_GET['token'] ) ? (string) wp_unslash( $_GET['token'] ) : '';
	if ( empty( $mode['token'] ) || ! hash_equals( (string) $mode['token'], $token ) ) {
		wp_send_json( array( 'error' => 'token' ), 403 );
	}
	$what = (string) wp_unslash( $_GET['digi_probe'] );
	if ( 'state' === $what ) {
		wp_send_json( digi_fb_probe_state() );
	}
	if ( 'fontguard' === $what ) {
		wp_send_json( digi_fb_probe_fontguard() );
	}
	if ( 'header' === $what ) {
		wp_send_json( digi_fb_probe_header( isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '' ) );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	wp_send_json( array( 'error' => 'desconocido' ), 404 );
}

/** ¿Existe la fila de una opción? (consulta directa: los filtros `default_option_` y `pre_option_` no cuentan). */
function digi_fb_probe_row( $option ) {
	global $wpdb;
	return null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $option ) ); // phpcs:ignore
}

function digi_fb_probe_state() {
	$ns    = 'Digitalisimo\\Elements\\Front\\';
	$state = array(
		'blog'         => get_current_blog_id(),
		'multisite'    => is_multisite(),
		'elementor'    => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
		'elements'     => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '',
		'bundled'      => class_exists( 'Digitalisimo_Elements_Core' ) && Digitalisimo_Elements_Core::bundled(),
		'module'       => class_exists( $ns . 'Module' ),
		'gate'         => class_exists( $ns . 'Pro_Gate' ),
		'filters'      => array(),
		'rows'         => array(),
		'options'      => array(),
	);
	foreach ( array( 'elementor_font_display', 'elementor_element_cache_ttl', 'elementor_optimized_image_loading' ) as $option ) {
		$state['filters'][ $option ] = false !== has_filter( 'default_option_' . $option );
		$state['rows'][ $option ]    = digi_fb_probe_row( $option );
		$state['options'][ $option ] = get_option( $option );
	}
	foreach ( array( 'elementor_experiment-e_font_icon_svg', 'elementor_local_google_fonts', 'elementor_install_history', 'elementor_icon_manager_needs_update', 'digitalisimo_performance_js_fallback', 'digitalisimo_elements_front', 'digitalisimo_elements_front_snippets' ) as $option ) {
		$state['options'][ $option ] = get_option( $option, null );
	}
	// F7a: opciones nuevas (presets sin carga automática; índices que existen siempre, con carga automática).
	$alloptions = wp_load_alloptions();
	foreach ( array( 'digitalisimo_elements_presets', 'digitalisimo_elements_tracked_pages', 'digitalisimo_elements_tb_priority' ) as $option ) {
		$value                       = get_option( $option, null );
		$state['f7a'][ $option ]     = array(
			'row'      => digi_fb_probe_row( $option ),
			'autoload' => array_key_exists( $option, $alloptions ),
			'value'    => 'digitalisimo_elements_presets' === $option && is_array( $value ) ? array( 'slugs' => array_keys( (array) ( $value['presets'] ?? array() ) ) ) : $value,
		);
	}
	$state['rows']['elementor_experiment-e_font_icon_svg'] = digi_fb_probe_row( 'elementor_experiment-e_font_icon_svg' );
	if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->experiments ) ) {
		$state['svg_active'] = (bool) \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_font_icon_svg' );
		$state['v4_active']  = (bool) \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_atomic_elements' );
	}
	if ( class_exists( $ns . 'Transition' ) ) {
		$transition         = $ns . 'Transition';
		$state['applied']   = $transition::applied();
		$state['effective'] = $transition::effective();
		$state['pending']   = $transition::pending();
		$state['scheduled'] = array( 'transition' => wp_next_scheduled( $transition::HOOK ) );
	}
	if ( class_exists( $ns . 'Settings' ) ) {
		$settings                 = $ns . 'Settings';
		$state['settings']        = $settings::stored();
		$state['network_stored']  = $settings::network_stored();
		$state['inherits_svg']    = $settings::inherits_network( 'svg_icons' );
	}
	if ( class_exists( $ns . 'Icon_Scan' ) ) {
		$scan                       = $ns . 'Icon_Scan';
		$state['scan']              = $scan::state();
		$state['scan']['verdict']   = $scan::verdict();
		$state['scheduled']['scan'] = defined( $scan . '::HOOK' ) ? wp_next_scheduled( constant( $scan . '::HOOK' ) ) : false;
	}
	if ( class_exists( $ns . 'Core_Defaults' ) ) {
		$state['core_defaults'] = ( $ns . 'Core_Defaults' )::active();
	}
	if ( class_exists( $ns . 'Lazy_Libs' ) ) {
		$state['lazy_registered'] = wp_script_is( ( $ns . 'Lazy_Libs' )::HANDLE, 'registered' );
	}
	$cron = _get_cron_array();
	$due  = array();
	foreach ( (array) $cron as $time => $hooks ) {
		foreach ( array_keys( (array) $hooks ) as $hook ) {
			if ( 0 === strpos( (string) $hook, 'digitalisimo' ) ) {
				$due[] = $hook . '@' . ( (int) $time - time() );
			}
		}
	}
	$state['cron']       = $due;
	$state['doing_cron'] = get_transient( 'doing_cron' );
	return $state;
}

/**
 * SEO=guard: lo mismo que hace «Optimizar fuentes» de DIGITALÍSIMO SEO (análisis de familias, modo `auto`, copias y
 * aprobación), para que el sitio parta con el blindaje sirviendo sus copias antes de la transición.
 */
function digi_fb_probe_fontguard() {
	if ( ! class_exists( 'Digitalisimo_Integrations_Performance_Fonts' ) || ! class_exists( 'Digitalisimo_Integrations_Performance_Font_Guard' ) ) {
		return array( 'error' => 'sin DIGITALÍSIMO SEO' );
	}
	$report = Digitalisimo_Integrations_Performance_Fonts::scan();
	Digitalisimo_Integrations_Performance_Font_Guard::forget();
	$result = Digitalisimo_Integrations_Performance_Font_Guard::build( true, $report );
	if ( class_exists( 'Digitalisimo_Integrations_Performance_Preloads' ) && method_exists( 'Digitalisimo_Integrations_Performance_Preloads', 'rebuild' ) ) {
		Digitalisimo_Integrations_Performance_Preloads::rebuild( $report );
	}
	if ( class_exists( 'Digitalisimo_Integrations_Performance_Font_Inline' ) && method_exists( 'Digitalisimo_Integrations_Performance_Font_Inline', 'rebuild' ) ) {
		Digitalisimo_Integrations_Performance_Font_Inline::rebuild();
	}
	$manifest = Digitalisimo_Integrations_Performance_Font_Guard::manifest();
	return array( 'families' => array_values( (array) ( $report['families'] ?? array() ) ), 'faces' => count( (array) ( $report['faces'] ?? array() ) ), 'complete' => ! empty( $report['complete'] ), 'result' => $result, 'rows' => array_map( static function ( $row ) { return array( 'url' => $row['url'] ?? '', 'valid' => $row['valid'] ?? null, 'removed' => $row['removed'] ?? 0 ); }, (array) ( $manifest['rows'] ?? array() ) ) );
}

/** THEME=1: sólo la cabecera del caso pedido queda con condiciones. */
function digi_fb_probe_header( $key ) {
	if ( ! class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
		return array( 'error' => 'sin Theme Builder' );
	}
	$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
	$done    = array();
	foreach ( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $post ) {
		if ( ! preg_match( '/^digi-fb-(.+)-x-header$/', $post->post_name, $m ) ) {
			continue;
		}
		$on = $m[1] === $key;
		$manager->save_conditions( $post->ID, $on ? array( array( 'type' => 'include', 'name' => 'general', 'sub_name' => '', 'sub_id' => '' ) ) : array() );
		$done[ $m[1] ] = $on;
	}
	return array( 'headers' => $done );
}
