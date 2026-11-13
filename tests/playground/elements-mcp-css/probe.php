<?php
/**
 * Sonda del ensayo del CSS tras las escrituras del MCP de DIGITALÍSIMO Elements (mu-plugin que copia setup.php).
 *
 * - Sin red hacia fuera: toda petición HTTP a otro host devuelve un error y el correo no sale.
 * - Anota en la opción `digi_mc_abilog` cada `wp_before_execute_ability` y `wp_after_execute_ability` (prueba que la
 *   Abilities API de este WordPress los dispara alrededor de cada herramienta del MCP).
 * - `?digi_mc=<paso>&token=<token>` (el token de /digi-probe/token.txt): `ping`, `users` (administradora con contraseña
 *   de aplicación en /digi-probe/secrets.json), `login` (cookies de sesión de la administradora, para ver borradores),
 *   `state` (estado de una página: estado, autoguardado, estilos locales guardados, nodos de la caché de estilos
 *   atómicos y archivos CSS en uploads), `v3page` (crea una página clásica publicada con Document::save()), `abilog`.
 * Los avisos de PHP del módulo propio (modules/digitalisimo-mcp/) van a /digi-probe/php-errors.log (el arnés falla con
 * cualquier línea).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIGI_MC_DIR = '/digi-probe';

register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( DIGI_MC_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
		}
	}
);

function digi_mc_ours( string $text ): bool {
	return 1 === preg_match( '#modules/digitalisimo-mcp/|Digitalisimo\\\\Elements\\\\Mcp\\\\#i', $text );
}
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		if ( digi_mc_ours( (string) $file ) || digi_mc_ours( (string) $message ) ) {
			file_put_contents( DIGI_MC_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . " · PHP $severity: $message en $file:$line\n", FILE_APPEND );
		}
		return false;
	}
);
foreach ( array( 'doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run' ) as $digi_mc_hook ) {
	add_action(
		$digi_mc_hook,
		static function ( ...$args ) use ( $digi_mc_hook ) {
			$text  = $digi_mc_hook . ': ' . implode( ' · ', array_map( static function ( $a ) { return is_scalar( $a ) ? (string) $a : ''; }, $args ) );
			$trace = function_exists( 'wp_debug_backtrace_summary' ) ? (array) wp_debug_backtrace_summary( null, 0, false ) : array();
			if ( digi_mc_ours( $text ) || digi_mc_ours( implode( ',', $trace ) ) ) {
				file_put_contents( DIGI_MC_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' · ' . $text . "\n", FILE_APPEND );
			}
		},
		10,
		3
	);
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host && $host !== $home && '127.0.0.1' !== $host && 'localhost' !== $host ? new WP_Error( 'digi_mc_offline', 'Sin red en el ensayo.' ) : $pre;
	},
	10,
	3
);
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'wp_is_application_passwords_available', '__return_true' );

foreach ( array( 'wp_before_execute_ability' => 'before', 'wp_after_execute_ability' => 'after' ) as $digi_mc_hook => $digi_mc_tag ) {
	add_action(
		$digi_mc_hook,
		static function ( $name ) use ( $digi_mc_tag ) {
			$log   = get_option( 'digi_mc_abilog', array() );
			$log   = is_array( $log ) ? $log : array();
			$log[] = $digi_mc_tag . ':' . $name;
			update_option( 'digi_mc_abilog', array_slice( $log, -200 ), false );
		},
		1
	);
}

// Diagnóstico de los enlaces firmados: qué entrada, qué datos y qué hoja del documento ve esa petición.
add_action(
	'wp_head',
	static function () {
		if ( empty( $_GET['elementor_preview_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$id    = (int) get_the_ID();
		$css   = get_post_meta( $id, '_elementor_css', true );
		$class = '\\Elementor\\Modules\\Mcp\\Preview\\Preview_Token';
		$claims = class_exists( $class ) ? $class::decode( sanitize_text_field( wp_unslash( $_GET['elementor_preview_token'] ) ), $class::secret() ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rev    = is_array( $claims ) ? get_post( (int) $claims['revision_id'] ) : null;
		$data = json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
		file_put_contents(
			DIGI_MC_DIR . '/preview-debug.log',
			wp_json_encode(
				array(
					'id'        => $id,
					'singular'  => is_singular(),
					'post_css'  => wp_style_is( 'elementor-post-' . $id, 'enqueued' ),
					'css_meta'  => is_array( $css ) ? ( $css['status'] ?? null ) : $css,
					'edit_mode' => get_post_meta( $id, '_elementor_edit_mode', true ),
					'local_ids' => digi_mc_local_ids( $data ),
					'revision'  => $rev ? array( 'id' => (int) $rev->ID, 'name' => $rev->post_name, 'autosave' => (bool) wp_is_post_autosave( $rev ) ) : null,
					'post_css_file' => is_array( $css ) ? ( $css['time'] ?? null ) : null,
					'theme_tpl' => get_page_template_slug( $id ),
				)
			) . "\n",
			FILE_APPEND
		);
	},
	999
);

function digi_mc_out( array $data, int $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $data );
	exit;
}

function digi_mc_secret( string $key, $value ) {
	$file = DIGI_MC_DIR . '/secrets.json';
	$all  = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
	$all  = is_array( $all ) ? $all : array();
	$all[ $key ] = $value;
	file_put_contents( $file, wp_json_encode( $all ) );
}

/** Nodo de la caché de estilos atómicos (`elementor_atomic_cache_validity__<raíz>`) de una entrada. */
function digi_mc_validity( string $root, int $id ) {
	$data = get_option( 'elementor_atomic_cache_validity__' . $root, null );
	return is_array( $data ) && isset( $data['children'][ $id ] ) ? $data['children'][ $id ] : null;
}

/** IDs de los estilos locales guardados en un árbol de elementos. */
function digi_mc_local_ids( $elements ): array {
	$ids = array();
	foreach ( is_array( $elements ) ? $elements : array() as $element ) {
		if ( ! empty( $element['styles'] ) && is_array( $element['styles'] ) ) {
			$ids = array_merge( $ids, array_keys( $element['styles'] ) );
		}
		$ids = array_merge( $ids, digi_mc_local_ids( $element['elements'] ?? array() ) );
	}
	return $ids;
}

add_action(
	'init',
	static function () {
		if ( ! isset( $_GET['digi_mc'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = trim( (string) @file_get_contents( DIGI_MC_DIR . '/token.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( '' === $token || ! isset( $_GET['token'] ) || ! hash_equals( $token, (string) $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
			digi_mc_out( array( 'error' => 'token' ), 403 );
		}
		$step  = sanitize_key( wp_unslash( $_GET['digi_mc'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$arg   = isset( $_GET['value'] ) ? sanitize_text_field( wp_unslash( $_GET['value'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$admin = get_user_by( 'login', 'admin' );
		switch ( $step ) {
			case 'ping':
				digi_mc_out(
					array(
						'ready'      => true,
						'wp'         => $GLOBALS['wp_version'],
						'php'        => PHP_VERSION,
						'abilities'  => function_exists( 'wp_register_ability' ),
						'atomic'     => class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_atomic_elements' ),
						'fix_loaded' => class_exists( '\Digitalisimo\Elements\Mcp\Css_Sync', false ),
						'kit'        => class_exists( '\Elementor\Plugin' ) ? (int) \Elementor\Plugin::$instance->kits_manager->get_active_id() : 0,
					)
				);
				break;
			case 'users':
				require_once ABSPATH . 'wp-admin/includes/user.php';
				$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-mc' ) );
				if ( is_wp_error( $created ) ) {
					digi_mc_out( array( 'error' => $created->get_error_message() ), 500 );
				}
				digi_mc_secret( 'password:admin', $created[0] );
				digi_mc_out( array( 'users' => array( 'admin' => $admin->ID ) ) );
				break;
			case 'login':
				wp_set_auth_cookie( $admin->ID, false, false );
				digi_mc_out( array( 'login' => true ) );
				break;
			case 'state':
				$id       = absint( $arg );
				$autosave = wp_get_post_autosave( $id, $admin->ID );
				$files    = array();
				foreach ( (array) glob( wp_upload_dir()['basedir'] . '/elementor/css/*' ) as $file ) {
					if ( preg_match( '/(^|-)' . $id . '(-|\.)/', basename( $file ) ) ) {
						$files[ basename( $file ) ] = array( 'mtime' => filemtime( $file ), 'size' => filesize( $file ) );
					}
				}
				$data = json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
				$auto = $autosave ? json_decode( (string) get_metadata( 'post', $autosave->ID, '_elementor_data', true ), true ) : array();
				digi_mc_out(
					array(
						'status'    => get_post_status( $id ),
						'autosave'  => $autosave ? (int) $autosave->ID : 0,
						'autosave_ids' => digi_mc_local_ids( $auto ),
						'local_ids' => digi_mc_local_ids( $data ),
						'local'     => digi_mc_validity( 'local', $id ),
						'global'    => digi_mc_validity( 'global', $id ),
						'files'     => $files,
					)
				);
				break;
			case 'v3page':
				wp_set_current_user( $admin->ID );
				$id = wp_insert_post( array( 'post_title' => 'Página clásica', 'post_status' => 'publish', 'post_type' => 'page' ) );
				$document = \Elementor\Plugin::$instance->documents->get( $id );
				$document->set_is_built_with_elementor( true );
				$document->save(
					array(
						'settings' => array( 'post_status' => 'publish' ),
						'elements' => array(
							array(
								'id'       => 'c3a1b2d',
								'elType'   => 'container',
								'settings' => array( 'background_background' => 'classic', 'background_color' => '#1E90FF' ),
								'elements' => array(
									array(
										'id'         => 'h3e4f5a',
										'elType'     => 'widget',
										'widgetType' => 'heading',
										'settings'   => array( 'title' => 'Página clásica', 'title_color' => '#FF6600' ),
										'elements'   => array(),
									),
								),
							),
						),
					)
				);
				digi_mc_out( array( 'id' => (int) $id ) );
				break;
			case 'abilog':
				digi_mc_out( array( 'log' => get_option( 'digi_mc_abilog', array() ) ) );
				break;
		}
		digi_mc_out( array( 'error' => 'paso desconocido' ), 400 );
	},
	99
);
