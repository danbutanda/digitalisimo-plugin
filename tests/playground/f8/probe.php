<?php
/**
 * Plugin Name: Sonda de f8.sh (sólo Playground)
 * Description: mu-plugin de tests/playground/f8.sh (F8, frente J). Nunca forma parte de un plugin.
 *
 * - **Sin salida a la red:** `pre_http_request` corta toda petición a otro host (y anota en `http-blocked.log` las que
 *   iban a un dominio de `vendors.json`, que el escenario hace fallar). Los correos se dan por enviados.
 * - **WP-Cron sólo a mano:** `DISABLE_WP_CRON`; el conductor pide `/wp-cron.php` de cada sitio cuando el escenario
 *   quiere que corra (P3: la barrera se libera con el WP-Cron del sitio principal) y `?digi_f8=due` adelanta un evento.
 * - En `wp_footer` 999 imprime `<!-- digi-f8-probe {…} -->` con lo que el escenario necesita de esa petición.
 * - `?digi_f8=<op>&token=…`: control del arnés. Exige el token aleatorio de `mode.json`.
 *
 * No usa ninguna clase de Migración ni de Elements sin comprobar antes que existe: P8 cambia sus versiones en caliente.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}
require_once WP_CONTENT_DIR . '/probe/lib.php';

/** Hosts del proveedor (vendors.json copiado a la carpeta de la sonda). */
function digi_f8_vendor_hosts() {
	static $hosts = null;
	if ( null === $hosts ) {
		$v     = json_decode( (string) @file_get_contents( DIGI_F8_DIR . '/vendors.json' ), true ); // phpcs:ignore
		$hosts = array_merge( (array) ( $v['hosts'] ?? array() ), (array) ( $v['future'] ?? array() ) );
	}
	return $hosts;
}

function digi_f8_vendor_host( $host ) {
	foreach ( digi_f8_vendor_hosts() as $vendor ) {
		if ( $host === $vendor || substr( $host, -strlen( '.' . $vendor ) ) === '.' . $vendor ) {
			return true;
		}
	}
	return false;
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		// Otro filtro (el escudo del proveedor de Elements, prioridad 1) ya respondió: la petición no sale.
		if ( false !== $pre ) {
			return $pre;
		}
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( '' === $host || $host === $home || '127.0.0.1' === $host || 'localhost' === $host ) {
			return $pre;
		}
		$line = gmdate( 'c' ) . ' ' . ( $_SERVER['REQUEST_URI'] ?? 'cli' ) . ' → ' . substr( (string) $url, 0, 300 ) . "\n"; // phpcs:ignore
		file_put_contents( DIGI_F8_DIR . '/' . ( digi_f8_vendor_host( $host ) ? 'http-blocked.log' : 'http-out.log' ), $line, FILE_APPEND ); // phpcs:ignore
		return new WP_Error( 'digi_f8_offline', 'f8.sh: sin salida a la red' );
	},
	PHP_INT_MAX,
	3
);
add_filter( 'pre_wp_mail', '__return_true' );
// Presupuesto de cada paso de «Migrar» (`digi_f8_step_seconds`, mínimo 5 s en Migración): más pasos, más sondeos.
add_filter(
	'digitalisimo_migration_step_seconds',
	static function ( $seconds ) {
		$own = (int) get_option( 'digi_f8_step_seconds', 0 );
		return $own > 0 ? $own : $seconds;
	}
);

/** Datos de la petición para el conductor, al final de cada página del front. */
add_action(
	'wp_footer',
	static function () {
		if ( is_admin() ) {
			return;
		}
		$state = get_option( 'digitalisimo_migration_tb_state', null );
		$data  = array(
			'blog'     => get_current_blog_id(),
			'ep'       => defined( 'BDTEP_VER' ),
			'ps'       => defined( 'BDTPS_CORE_VER' ),
			'queried'  => get_queried_object_id(),
			'tb'       => is_array( $state ) ? array( 'allow' => ! empty( $state['allow'] ), 'published' => ! empty( $state['published'] ) ) : null,
			'elements' => defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : '',
			'pro'      => class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module', false ),
		);
		echo "\n<!-- digi-f8-probe " . wp_json_encode( $data ) . " -->\n";
	},
	999
);

function digi_f8_send( $data, $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
	exit;
}

/** Opción leída directamente de la tabla (sin la caché de objetos ni la de opciones). */
function digi_f8_raw_option( $name ) {
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore
	return null === $raw ? null : maybe_unserialize( $raw );
}

/** Registro de Migración por tipo (`digitalisimo_migration_reg_<kind>`), leído de la tabla. */
function digi_f8_registry() {
	global $wpdb;
	$out = array();
	foreach ( (array) $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'digitalisimo\\_migration\\_reg\\_%'" ) as $name ) { // phpcs:ignore
		$out[ substr( $name, strlen( 'digitalisimo_migration_reg_' ) ) ] = digi_f8_raw_option( $name );
	}
	return $out;
}

/** Plugins activos en el sitio y en la red. */
function digi_f8_plugins() {
	return array(
		'site'    => array_values( (array) digi_f8_raw_option( 'active_plugins' ) ),
		'network' => is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array(),
	);
}

/** Resumen de un documento: tipo, estado, metas de Migración y widgets. */
function digi_f8_doc_info( $id, $full = false ) {
	$post = get_post( $id );
	if ( ! $post ) {
		return null;
	}
	$raw = (string) get_post_meta( $id, '_elementor_data', true );
	preg_match_all( '/"widgetType":"([a-z0-9_-]+)"/', $raw, $m );
	$metas = array();
	foreach ( (array) get_post_meta( $id ) as $key => $values ) {
		if ( 0 === strpos( $key, '_digitalisimo' ) || 0 === strpos( $key, '_elementor_conditions' ) || 0 === strpos( $key, '_elementor_template_type' ) ) {
			$metas[ $key ] = $full ? maybe_unserialize( $values[0] ) : strlen( (string) $values[0] );
		}
	}
	$out = array(
		'id'      => (int) $id,
		'type'    => $post->post_type,
		'status'  => $post->post_status,
		'title'   => $post->post_title,
		'slug'    => $post->post_name,
		'widgets' => array_count_values( $m[1] ),
		'legacy'  => (bool) preg_match( '/"widgetType":"(bdt-|prime-slider-)|"(ep_|element_pack_|bdt_)[a-z0-9_]*"/', $raw ),
		'markers' => substr_count( $raw, '"widgetType":"digitalisimo-unconverted"' ),
		'md5'     => md5( $raw ),
		'metas'   => $metas,
	);
	if ( $full ) {
		$out['data'] = json_decode( $raw, true );
		$out['page'] = get_post_meta( $id, '_elementor_page_settings', true );
	}
	return $out;
}

function digi_f8_route() {
	if ( ! isset( $_GET['digi_f8'] ) ) { // phpcs:ignore
		return;
	}
	$mode = digi_f8_mode();
	if ( empty( $mode['token'] ) || ! isset( $_GET['token'] ) || ! hash_equals( (string) $mode['token'], (string) $_GET['token'] ) ) { // phpcs:ignore
		status_header( 403 );
		exit;
	}
	$op  = sanitize_key( wp_unslash( $_GET['digi_f8'] ) ); // phpcs:ignore
	$arg = static function ( $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- sonda de Playground protegida por token.
		$source = isset( $_POST[ $key ] ) ? $_POST : $_GET;
		return isset( $source[ $key ] ) ? wp_unslash( (string) $source[ $key ] ) : $default;
	};
	if ( 'ping' !== $op ) {
		wp_set_current_user( 1 );
	}
	switch ( $op ) {
		case 'ping':
			digi_f8_send(
				array(
					'ready'     => (int) get_option( 'digi_f8_ready', 0 ),
					'blog'      => get_current_blog_id(),
					'core'      => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
					'bundled'   => defined( 'ELEMENTOR_PATH' ) && false !== strpos( ELEMENTOR_PATH, '/digitalisimo-elements/elementor/' ),
					'elements'  => defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : '',
					'migration' => defined( 'DIGITALISIMO_MIGRATION_VERSION' ) ? DIGITALISIMO_MIGRATION_VERSION : '',
					'ep'        => defined( 'BDTEP_VER' ) ? BDTEP_VER : '',
					'ps'        => defined( 'BDTPS_CORE_VER' ) ? BDTPS_CORE_VER : '',
					'pro'       => class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module', false ),
					'conflict'  => function_exists( 'digitalisimo_elements_conflicting_plugin_active' ) ? digitalisimo_elements_conflicting_plugin_active() : null,
					'theme'     => get_stylesheet(),
					'woo'       => defined( 'WC_VERSION' ) ? WC_VERSION : null,
					'plugins'   => digi_f8_plugins(),
					'errors'    => (string) @file_get_contents( DIGI_F8_DIR . '/setup-error.txt' ), // phpcs:ignore
					'warnings'  => (string) @file_get_contents( DIGI_F8_DIR . '/setup-warnings.txt' ), // phpcs:ignore
				)
			);
			break;
		case 'ids':
			$ids  = digi_f8_ids();
			$urls = array();
			foreach ( $ids as $key => $id ) {
				$link = get_permalink( $id );
				if ( $link ) {
					$urls[ $key ] = $link;
				}
			}
			digi_f8_send( array( 'ids' => $ids, 'urls' => $urls, 'home' => home_url( '/' ) ) );
			break;
		case 'state':
			$job      = digi_f8_raw_option( 'digitalisimo_migration_job' );
			$report   = digi_f8_raw_option( 'digitalisimo_migration_report' );
			$f8       = digi_f8_raw_option( 'digitalisimo_migration_report_f8' );
			$crons    = array();
			foreach ( (array) _get_cron_array() as $time => $hooks ) {
				foreach ( (array) $hooks as $hook => $events ) {
					if ( 0 === strpos( (string) $hook, 'digitalisimo_migration' ) ) {
						$crons[] = array( 'hook' => $hook, 'at' => (int) $time, 'in' => (int) $time - time() );
					}
				}
			}
			digi_f8_send(
				array(
					'blog'        => get_current_blog_id(),
					'job'         => $job,
					'report'      => $report,
					'report_f8'   => $f8,
					'tb_state'    => digi_f8_raw_option( 'digitalisimo_migration_tb_state' ),
					'registry'    => '1' === $arg( 'registry' ) ? digi_f8_registry() : null,
					'network_job' => is_multisite() ? get_site_option( 'digitalisimo_migration_network_job', null ) : null,
					'net_lock'    => is_multisite() ? digi_f8_raw_option( 'digitalisimo_migration_network_lock' ) : null,
					'lock'        => digi_f8_raw_option( 'digitalisimo_migration_lock' ),
					'plugins'     => digi_f8_plugins(),
					'ep'          => defined( 'BDTEP_VER' ),
					'ps'          => defined( 'BDTPS_CORE_VER' ),
					'pro'         => class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module', false ),
					'crons'       => $crons,
					'alias'       => digi_f8_raw_option( 'digitalisimo_migration_icon_alias' ),
				)
			);
			break;
		case 'doc':
			$id = (int) $arg( 'id' );
			if ( ! $id ) {
				$ids = digi_f8_ids();
				$id  = (int) ( $ids[ $arg( 'key' ) ] ?? 0 );
			}
			digi_f8_send( array( 'doc' => digi_f8_doc_info( $id, '1' === $arg( 'full' ) ) ) );
			break;
		case 'posts':
			$types = array_filter( explode( ',', $arg( 'types', 'page' ) ) );
			$out   = array();
			foreach ( get_posts( array( 'post_type' => $types, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) ) as $post ) {
				$out[] = digi_f8_doc_info( $post->ID, '1' === $arg( 'full' ) );
			}
			digi_f8_send( array( 'posts' => $out ) );
			break;
		case 'option':
			$name = $arg( 'name' );
			if ( '' === $name || 0 !== strpos( $name, 'digi' ) && ! in_array( $name, (array) ( $mode['writable'] ?? array() ), true ) && '' !== $arg( 'value', '' ) ) {
				digi_f8_send( array( 'error' => 'opción no permitida' ), 400 );
			}
			if ( '' !== $arg( 'value', '' ) ) {
				$value = json_decode( $arg( 'value' ), true );
				null === $value ? delete_option( $name ) : update_option( $name, $value, false );
			}
			digi_f8_send( array( 'name' => $name, 'value' => digi_f8_raw_option( $name ) ) );
			break;
		case 'due':
			// Adelanta (como si pasara el tiempo) los eventos de WP-Cron de un gancho de Migración para que el próximo
			// /wp-cron.php los ejecute. Sólo ganchos de Migración.
			$hook = $arg( 'hook' );
			if ( 0 !== strpos( $hook, 'digitalisimo_migration' ) ) {
				digi_f8_send( array( 'error' => 'gancho no permitido' ), 400 );
			}
			$moved = 0;
			$crons = (array) _get_cron_array();
			foreach ( $crons as $time => $hooks ) {
				if ( isset( $hooks[ $hook ] ) ) {
					foreach ( $hooks[ $hook ] as $event ) {
						wp_unschedule_event( $time, $hook, $event['args'] );
						wp_schedule_single_event( time() - 1, $hook, $event['args'] );
						++$moved;
					}
				}
			}
			delete_transient( 'doing_cron' );
			digi_f8_send( array( 'moved' => $moved ) );
			break;
		case 'clearcss':
			if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
			digi_f8_send( array( 'ok' => true ) );
			break;
	}
	if ( function_exists( 'digi_f8_ops' ) ) {
		$ops = digi_f8_ops();
		if ( isset( $ops[ $op ] ) ) {
			digi_f8_send( call_user_func( $ops[ $op ], $arg ) );
		}
	}
	digi_f8_send( array( 'error' => 'operación desconocida: ' . $op ), 404 );
}
add_action( 'init', 'digi_f8_route', 99 );

// Operaciones propias del escenario (sus funciones `digi_f8_ops()`), si el archivo existe.
$digi_f8_mode = digi_f8_mode();
if ( ! empty( $digi_f8_mode['scenario_file'] ) && is_file( DIGI_F8_DIR . '/' . basename( $digi_f8_mode['scenario_file'] ) ) ) {
	require_once DIGI_F8_DIR . '/' . basename( $digi_f8_mode['scenario_file'] );
}
