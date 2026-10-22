<?php
/**
 * Plugin Name: Sonda de design.sh (sólo Playground)
 * Description: mu-plugin de tests/playground/design.sh (F7a, frente M). Nunca forma parte de un plugin.
 *
 * - En `wp_footer` 999 imprime `<!-- digi-design-probe {…} -->`: razones de la puerta de PRO, `DONOTCACHEPAGE`, las
 *   plantillas de cabecera y pie con el filtro de prioridad y sin él (lo que pinta 4.3.0.163), el contenido
 *   consultado, el tema y `is_rtl()`.
 * - Fuerza RTL en las páginas `rtl` de cases.json (también en su vista previa del editor), imprime un marcador en
 *   `digitalisimo_before_header`, registra `litespeed_purge_post`, aplica los interruptores de DS5 (tipo de
 *   consentimiento de la WP Consent API y el filtro de rastreo) y apaga WP-Cron desde las visitas.
 * - `?digi_design=<op>&token=…`: control del arnés (ver `digi_ds_route()`).
 *
 * Exige el token aleatorio de `mode.json`. No usa ninguna clase de F7a sin comprobarla antes: debe funcionar igual
 * con 4.3.0.163 instalado (DS13).
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}
require_once WP_CONTENT_DIR . '/probe/lib.php';

function digi_ds_flags() {
	$flags = get_option( 'digi_ds_flags', array() );
	return is_array( $flags ) ? $flags : array();
}

add_filter( 'pre_wp_mail', '__return_true' );

// DS5: WP Consent API simulada (flag `consent_api`). Sólo lo que Page_Visits usa: `wp_has_consent()` en PHP (su mera
// existencia) y, en el navegador, `window.wp_has_consent( categoría )` más el evento `wp_listen_for_consent_change`, que
// el arnés dispara al dar el consentimiento. El consentimiento es la cookie `digi_ds_consent_<categoría>=allow`.
if ( ! function_exists( 'wp_has_consent' ) && ! empty( digi_ds_flags()['consent_api'] ) ) {
	function wp_has_consent( $category ) {
		return isset( $_COOKIE[ 'digi_ds_consent_' . $category ] ) && 'allow' === $_COOKIE[ 'digi_ds_consent_' . $category ]; // phpcs:ignore
	}
	add_action(
		'wp_footer',
		static function () {
			echo '<script id="digi-ds-consent-api">window.wp_has_consent=function(c){return document.cookie.indexOf("digi_ds_consent_"+c+"=allow")>-1;};</script>' . "\n";
		},
		5
	);
}

// DS5: la WP Consent API con un gestor «optin» (sin consentimiento hasta que se da) y el filtro de rastreo.
add_filter(
	'wp_get_consent_type',
	static function ( $type ) {
		$flags = digi_ds_flags();
		return ! empty( $flags['consent_type'] ) ? (string) $flags['consent_type'] : $type;
	},
	99
);
add_filter(
	'digitalisimo_elements_page_visit_tracking',
	static function ( $on ) {
		$flags = digi_ds_flags();
		return ! empty( $flags['tracking_off'] ) ? false : $on;
	},
	99
);
// LiteSpeed simulado: cada purga queda anotada.
add_action(
	'litespeed_purge_post',
	static function ( $id ) {
		$log   = get_option( 'digi_ds_purged', array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = (int) $id;
		update_option( 'digi_ds_purged', array_slice( $log, -200 ), false );
	}
);
// DS7: un tercero que imprime en el gancho del tema.
add_action(
	'digitalisimo_before_header',
	static function () {
		echo '<div id="digi-ds-before-header" data-probe="1"></div>';
	}
);

/** ¿La petición es la página (o su vista previa) de un documento con `rtl` en cases.json? */
function digi_ds_rtl_request() {
	$ids = digi_ds_ids();
	foreach ( (array) ( digi_ds_cases()['docs'] ?? array() ) as $key => $doc ) {
		if ( empty( $doc['rtl'] ) || empty( $ids[ $key ] ) ) {
			continue;
		}
		$id = (int) $ids[ $key ];
		if ( isset( $_GET['elementor-preview'] ) && (int) $_GET['elementor-preview'] === $id ) { // phpcs:ignore
			return true;
		}
		if ( function_exists( 'get_queried_object_id' ) && (int) get_queried_object_id() === $id ) {
			return true;
		}
	}
	return false;
}
add_action(
	'wp',
	static function () {
		if ( digi_ds_rtl_request() && isset( $GLOBALS['wp_locale'] ) ) {
			$GLOBALS['wp_locale']->text_direction = 'rtl';
		}
	},
	1
);

/** Plantillas de una ubicación: con el filtro de prioridad (4.3.0.164) y sin él (el orden de 4.3.0.163). */
function digi_ds_location( $location ) {
	if ( ! class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
		return null;
	}
	$manager  = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
	$with_map = (array) $manager->get_location_templates( $location );
	$filter   = 'digitalisimo_elements_theme_builder_priorities';
	$saved    = isset( $GLOBALS['wp_filter'][ $filter ] ) ? $GLOBALS['wp_filter'][ $filter ] : null;
	unset( $GLOBALS['wp_filter'][ $filter ] );
	$without = array_map( 'intval', array_keys( (array) $manager->get_location_templates( $location ) ) );
	if ( null !== $saved ) {
		$GLOBALS['wp_filter'][ $filter ] = $saved;
	}
	return array( 'with' => array_map( 'intval', array_keys( $with_map ) ), 'without' => $without, 'priorities' => $with_map );
}

add_action(
	'wp_footer',
	static function () {
		$out  = array(
			'donotcache' => defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE,
			'queried'    => (int) get_queried_object_id(),
			'rtl'        => is_rtl(),
			'theme'      => get_stylesheet(),
			'blog'       => (int) get_current_blog_id(),
			'header'     => digi_ds_location( 'header' ),
			'footer'     => digi_ds_location( 'footer' ),
		);
		$gate = 'Digitalisimo\\Elements\\Front\\Pro_Gate';
		if ( class_exists( $gate ) && method_exists( $gate, 'reasons' ) ) {
			$out['reasons'] = $gate::reasons();
		}
		echo "\n<!-- digi-design-probe " . wp_json_encode( $out, JSON_UNESCAPED_SLASHES ) . " -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	999
);

add_action( 'wp_loaded', 'digi_ds_route', 99 );

/** Respuesta JSON del control del arnés. */
function digi_ds_send( $data ) {
	nocache_headers();
	wp_send_json( $data );
}

/** Nombres de los controles `digitalisimo_*` y de F7a de un documento o un tipo de elemento. */
function digi_ds_control_names( $stack ) {
	$names = array();
	if ( is_object( $stack ) && method_exists( $stack, 'get_controls' ) ) {
		foreach ( array_keys( (array) $stack->get_controls() ) as $name ) {
			if ( 0 === strpos( $name, 'digitalisimo_' ) || false !== strpos( $name, '_digi_' ) ) {
				$names[] = $name;
			}
		}
	}
	return $names;
}

function digi_ds_route() {
	if ( ! isset( $_GET['digi_design'] ) ) { // phpcs:ignore
		return;
	}
	$mode = digi_ds_mode();
	if ( empty( $mode['token'] ) || ! isset( $_GET['token'] ) || ! hash_equals( (string) $mode['token'], (string) $_GET['token'] ) ) { // phpcs:ignore
		status_header( 403 );
		exit;
	}
	$op  = sanitize_key( wp_unslash( $_GET['digi_design'] ) ); // phpcs:ignore
	$arg = static function ( $key, $default = '' ) {
		return isset( $_GET[ $key ] ) ? wp_unslash( (string) $_GET[ $key ] ) : $default; // phpcs:ignore
	};
	$as_admin = static function () {
		wp_set_current_user( 1 );
	};
	$ids   = digi_ds_ids();
	$media = get_option( 'digi_ds_media', array() );
	$media = is_array( $media ) ? $media : array();

	switch ( $op ) {
		case 'ping':
			$design = 'Digitalisimo\\Elements\\Design\\Module';
			digi_ds_send(
				array(
					'ready'    => (int) get_option( 'digi_ds_ready', 0 ),
					'core'     => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
					'bundled'  => defined( 'ELEMENTOR_PATH' ) && false !== strpos( ELEMENTOR_PATH, '/digitalisimo-elements/elementor/' ),
					'elements' => defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : ( function_exists( 'get_plugin_data' ) ? '' : '' ),
					'design'   => class_exists( $design ),
					'missing'  => class_exists( $design ) && method_exists( $design, 'missing' ) ? $design::missing() : null,
					'theme'    => get_stylesheet(),
					'blog'     => (int) get_current_blog_id(),
					'errors'   => (string) @file_get_contents( DIGI_DS_DIR . '/setup-error.txt' ), // phpcs:ignore
				)
			);
			break;

		case 'ids':
			digi_ds_send( array( 'ids' => $ids, 'media' => $media, 'home' => home_url( '/' ), 'kit' => class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->kits_manager ) ? (int) \Elementor\Plugin::$instance->kits_manager->get_active_id() : 0 ) );
			break;

		case 'nonce':
			// Con la sesión de la petición (cookies del usuario): el nonce de elementor_ajax de ese usuario.
			digi_ds_send( array( 'user' => (int) get_current_user_id(), 'nonce' => is_user_logged_in() ? wp_create_nonce( 'elementor_ajax' ) : '' ) );
			break;

		case 'tree':
			digi_ds_send( digi_ds_doc_tree( sanitize_key( $arg( 'doc' ) ), $ids, $media ) );
			break;

		case 'data':
			$id = (int) $arg( 'id' );
			if ( ! $id && $arg( 'doc' ) ) {
				$id = (int) ( $ids[ sanitize_key( $arg( 'doc' ) ) ] ?? 0 );
			}
			$raw = get_post_meta( $id, '_elementor_data', true );
			digi_ds_send( array( 'id' => $id, 'status' => get_post_status( $id ), 'elements' => is_string( $raw ) ? json_decode( $raw, true ) : $raw, 'settings' => get_post_meta( $id, '_elementor_page_settings', true ), 'template_type' => get_post_meta( $id, '_elementor_template_type', true ), 'conditions' => get_post_meta( $id, '_elementor_conditions', true ) ) );
			break;

		case 'save':
			// Vuelve a guardar un documento de cases.json (o sólo sus ajustes) como administrador.
			$as_admin();
			$key      = sanitize_key( $arg( 'doc' ) );
			$document = isset( $ids[ $key ] ) && class_exists( '\\Elementor\\Plugin' ) ? \Elementor\Plugin::$instance->documents->get( (int) $ids[ $key ], false ) : null;
			if ( ! $document ) {
				digi_ds_send( array( 'ok' => false, 'error' => 'sin documento' ) );
			}
			$data = array();
			if ( '1' !== $arg( 'only_settings' ) ) {
				$tree             = digi_ds_doc_tree( $key, $ids, $media );
				$data['elements'] = $tree['elements'];
			}
			$settings = json_decode( $arg( 'settings', '{}' ), true );
			if ( is_array( $settings ) && $settings ) {
				$current          = get_post_meta( (int) $ids[ $key ], '_elementor_page_settings', true );
				$data['settings'] = array_merge( is_array( $current ) ? $current : array(), $settings );
				foreach ( $data['settings'] as $k => $v ) {
					if ( null === $v ) {
						unset( $data['settings'][ $k ] );
					}
				}
				// Document::save() no guarda unos ajustes vacíos; el editor siempre envía post_status (ajuste especial,
				// que no va a la meta): así quitar el último ajuste borra la meta como en el editor.
				$data['settings']['post_status'] = get_post_status( (int) $ids[ $key ] );
			}
			$ok = $document->save( $data );
			digi_ds_send( array( 'ok' => false !== $ok, 'settings' => get_post_meta( (int) $ids[ $key ], '_elementor_page_settings', true ) ) );
			break;

		case 'conditions':
			// Condiciones del Theme Builder de un documento (`list` = JSON de cadenas `include/…`), con la caché regenerada.
			$as_admin();
			$key = sanitize_key( $arg( 'doc' ) );
			if ( ! isset( $ids[ $key ] ) || ! class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
				digi_ds_send( array( 'ok' => false ) );
			}
			$rows = array();
			foreach ( (array) json_decode( $arg( 'list', '[]' ), true ) as $condition ) {
				$parts  = array_pad( explode( '/', (string) $condition ), 4, '' );
				$rows[] = array( 'type' => $parts[0], 'name' => $parts[1], 'sub_name' => $parts[2], 'sub_id' => $parts[3] );
			}
			$manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
			$ok      = $manager->save_conditions( (int) $ids[ $key ], $rows );
			$manager->get_cache()->regenerate();
			digi_ds_send( array( 'ok' => (bool) $ok, 'conditions' => get_post_meta( (int) $ids[ $key ], '_elementor_conditions', true ) ) );
			break;

		case 'controls':
			// Controles F7a de un documento (DS7) o de un tipo de elemento (DS11).
			$out = array();
			if ( $arg( 'doc' ) && isset( $ids[ sanitize_key( $arg( 'doc' ) ) ] ) && class_exists( '\\Elementor\\Plugin' ) ) {
				$out['document'] = digi_ds_control_names( \Elementor\Plugin::$instance->documents->get( (int) $ids[ sanitize_key( $arg( 'doc' ) ) ], false ) );
			}
			foreach ( array_filter( explode( ',', $arg( 'widgets' ) ) ) as $name ) {
				$type         = class_exists( '\\Elementor\\Plugin' ) ? \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $name ) : null;
				$out[ $name ] = $type ? digi_ds_control_names( $type ) : null;
			}
			foreach ( array_filter( explode( ',', $arg( 'elements' ) ) ) as $name ) {
				$type         = class_exists( '\\Elementor\\Plugin' ) ? \Elementor\Plugin::$instance->elements_manager->get_element_types( $name ) : null;
				$out[ $name ] = $type ? digi_ds_control_names( $type ) : null;
			}
			digi_ds_send( $out );
			break;

		case 'flag':
			$flags                         = digi_ds_flags();
			$flags[ sanitize_key( $arg( 'name' ) ) ] = $arg( 'value' );
			update_option( 'digi_ds_flags', $flags, true );
			digi_ds_send( array( 'flags' => $flags ) );
			break;

		case 'front':
			// Clave de «Rendimiento» (sitio o red) sin pasar por la pantalla: la paridad la prueba front-network.sh.
			$key   = sanitize_key( $arg( 'key' ) );
			$value = '1' === $arg( 'value' );
			if ( '1' === $arg( 'network' ) && is_multisite() ) {
				$net         = get_site_option( 'digitalisimo_elements_front_defaults', array() );
				$net         = is_array( $net ) ? $net : array();
				$net[ $key ] = $value;
				update_site_option( 'digitalisimo_elements_front_defaults', $net );
				digi_ds_send( array( 'network' => $net ) );
			}
			$site         = get_option( 'digitalisimo_elements_front', array() );
			$site         = is_array( $site ) ? $site : array();
			$site[ $key ] = $value;
			$site['own']  = array_values( array_unique( array_merge( isset( $site['own'] ) && is_array( $site['own'] ) ? $site['own'] : array(), array( $key ) ) ) );
			update_option( 'digitalisimo_elements_front', $site );
			digi_ds_send( array( 'site' => $site ) );
			break;

		case 'plugin':
			$as_admin();
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$plugin = $arg( 'file' );
			if ( '1' === $arg( 'on' ) ) {
				$result = activate_plugin( $plugin );
			} else {
				deactivate_plugins( $plugin, true );
				$result = null;
			}
			digi_ds_send( array( 'active' => is_plugin_active( $plugin ), 'error' => is_wp_error( $result ) ? $result->get_error_message() : '' ) );
			break;

		case 'theme':
			// DS7: cambia el tema activo sin `switch_theme()` (sin `after_switch_theme`). Volver desde Hello con la
			// activación nativa deja el tema DIGITALÍSIMO en paridad (`origin.family = hello`: hojas de Hello y plantillas
			// generadas), lo que cambiaría los escenarios siguientes; aquí sólo importa qué tema está activo.
			$as_admin();
			$to = 'hello' === $arg( 'to' ) ? 'hello-elementor' : 'digitalisimo-theme';
			if ( ! file_exists( get_theme_root() . '/' . $to . '/style.css' ) ) {
				digi_ds_send( array( 'theme' => get_stylesheet(), 'error' => 'tema no montado: ' . $to ) );
			}
			update_option( 'template', $to );
			update_option( 'stylesheet', $to );
			delete_option( 'theme_switched' );
			digi_ds_send( array( 'theme' => get_option( 'stylesheet' ) ) );
			break;

		case 'clearcss':
			if ( class_exists( '\\Elementor\\Plugin' ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
			digi_ds_send( array( 'ok' => true ) );
			break;

		case 'kitimported':
			$as_admin();
			do_action( 'digitalisimo_elements_kit_imported' );
			digi_ds_send( array( 'tracked' => get_option( 'digitalisimo_elements_tracked_pages', null ) ) );
			break;

		case 'file':
			$path = ltrim( $arg( 'path' ), '/' );
			$dir  = wp_get_upload_dir();
			$real = realpath( $dir['basedir'] . '/' . $path );
			$ok   = $real && 0 === strpos( $real, realpath( $dir['basedir'] ) . '/' ) && is_file( $real );
			digi_ds_send( array( 'exists' => (bool) $ok, 'sha256' => $ok ? hash_file( 'sha256', $real ) : '', 'bytes' => $ok ? filesize( $real ) : 0, 'head' => $ok ? substr( (string) file_get_contents( $real ), 0, 300 ) : '' ) );
			break;

		case 'state':
			global $wpdb;
			$alloptions = wp_load_alloptions();
			$revisions  = array();
			if ( isset( $ids['ds5-rev-src'] ) ) {
				foreach ( wp_get_post_revisions( (int) $ids['ds5-rev-src'] ) as $revision ) {
					$revisions[] = array( 'id' => (int) $revision->ID, 'mentions_target' => false !== strpos( (string) get_post_meta( $revision->ID, '_elementor_data', true ), '"id":' . (int) ( $ids['ds5-rev-target'] ?? -1 ) ) || false !== strpos( (string) get_post_meta( $revision->ID, '_elementor_data', true ), '\\"id\\":' . (int) ( $ids['ds5-rev-target'] ?? -1 ) ) );
				}
			}
			$autoload = $wpdb->get_results( "SELECT option_name, autoload FROM {$wpdb->options} WHERE option_name IN ('digitalisimo_elements_presets','digitalisimo_elements_tracked_pages','digitalisimo_elements_tb_priority')", ARRAY_A ); // phpcs:ignore
			$visits   = 'Digitalisimo\\Elements\\Conditions\\Page_Visits';
			digi_ds_send(
				array(
					'tracked'    => get_option( 'digitalisimo_elements_tracked_pages', null ),
					'priority'   => get_option( 'digitalisimo_elements_tb_priority', null ),
					'presets'    => get_option( 'digitalisimo_elements_presets', null ),
					'autoload'   => $autoload,
					'in_alloptions' => array( 'tracked' => array_key_exists( 'digitalisimo_elements_tracked_pages', $alloptions ), 'priority' => array_key_exists( 'digitalisimo_elements_tb_priority', $alloptions ), 'presets' => array_key_exists( 'digitalisimo_elements_presets', $alloptions ) ),
					'front'      => get_option( 'digitalisimo_elements_front', null ),
					'purged'     => get_option( 'digi_ds_purged', array() ),
					'flags'      => digi_ds_flags(),
					'cookie'     => class_exists( $visits ) ? array( 'name' => $visits::cookie_name(), 'path' => $visits::cookie_path() ) : null,
					'revisions'  => $revisions,
					'masks'      => glob( wp_get_upload_dir()['basedir'] . '/digitalisimo-elements/masks/*.svg' ),
					'blog'       => (int) get_current_blog_id(),
					'theme'      => get_stylesheet(),
					'core'       => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
					'consent'    => function_exists( 'wp_has_consent' ),
				)
			);
			break;

		case 'sitemeta':
			global $wpdb;
			$rows = is_multisite() ? $wpdb->get_results( "SELECT meta_key, meta_value FROM {$wpdb->sitemeta} ORDER BY meta_key", ARRAY_A ) : array(); // phpcs:ignore
			$out  = array();
			foreach ( $rows as $row ) {
				$out[ $row['meta_key'] ] = md5( (string) $row['meta_value'] );
			}
			digi_ds_send( array( 'rows' => $out ) );
			break;

		case 'blogopts':
			// DS12: las opciones de F7a de cada sitio de la red.
			$out = array();
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog ) {
				switch_to_blog( (int) $blog );
				$out[ (int) $blog ] = array(
					'presets'  => get_option( 'digitalisimo_elements_presets', null ),
					'tracked'  => get_option( 'digitalisimo_elements_tracked_pages', null ),
					'priority' => get_option( 'digitalisimo_elements_tb_priority', null ),
					'ids'      => get_option( 'digi_ds_ids', array() ),
				);
				restore_current_blog();
			}
			digi_ds_send( $out );
			break;

		default:
			digi_ds_send( array( 'error' => 'operación desconocida' ) );
	}
}
