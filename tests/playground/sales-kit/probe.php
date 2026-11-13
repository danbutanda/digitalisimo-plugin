<?php
/**
 * Sonda del ensayo del kit de ventas (mu-plugin que copia setup.php).
 *
 * - Sin red hacia fuera (toda petición HTTP a otro host devuelve un error) y sin correo.
 * - Avisos de PHP, `_doing_it_wrong()` y obsolescencias del kit de ventas → /digi-probe/php-errors.log (el arnés falla
 *   con cualquier línea); los del resto de Elements → elements-notices.log (informativo); los fatales → fatal.log.
 * - `?digi_sk=<paso>&token=<token>` (el de /digi-probe/token.txt) prepara o lee el estado del sitio sin pasar por el MCP:
 *   `ping`, `container` (experimento de contenedores: active o inactive), `page` (estado, lienzo, marcas del
 *   constructor y widgets guardados de una página), `pages` (cuántas páginas hay por estado), `library` (plantillas de
 *   la biblioteca), `unfiltered` (la carga de archivos sin filtrar: 1 o 0), `login` (inicia sesión en el navegador con
 *   un usuario del ensayo) y `abilities` (abilities registradas).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DIGI_SK_DIR = '/digi-probe';

register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			file_put_contents( DIGI_SK_DIR . '/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
		}
	}
);
function digi_sk_ours( string $text ): bool {
	return 1 === preg_match( '#modules/digitalisimo-sales/|digitalisimo_sales|digitalisimo-sales/|Elements\\\\Sales\\\\#i', $text );
}
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		$text = ( $_SERVER['REQUEST_URI'] ?? '' ) . " · PHP $severity: $message en $file:$line\n";
		if ( digi_sk_ours( (string) $file ) || digi_sk_ours( (string) $message ) ) {
			file_put_contents( DIGI_SK_DIR . '/php-errors.log', $text, FILE_APPEND );
		} elseif ( false !== strpos( (string) $file, 'digitalisimo-elements' ) ) {
			file_put_contents( DIGI_SK_DIR . '/elements-notices.log', $text, FILE_APPEND );
		}
		return false;
	}
);
foreach ( array( 'doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run' ) as $digi_sk_hook ) {
	add_action(
		$digi_sk_hook,
		static function ( ...$args ) use ( $digi_sk_hook ) {
			$text  = $digi_sk_hook . ': ' . implode( ' · ', array_map( static function ( $a ) { return is_scalar( $a ) ? (string) $a : ''; }, $args ) );
			$trace = function_exists( 'wp_debug_backtrace_summary' ) ? (string) wp_debug_backtrace_summary( null, 0, true ) : '';
			if ( digi_sk_ours( $text ) || digi_sk_ours( $trace ) ) {
				file_put_contents( DIGI_SK_DIR . '/php-errors.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' · ' . $text . "\n", FILE_APPEND );
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
		return $host && $host !== $home && '127.0.0.1' !== $host && 'localhost' !== $host ? new WP_Error( 'digi_sk_offline', 'Sin red en el ensayo.' ) : $pre;
	},
	10,
	3
);
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'wp_is_application_passwords_available', '__return_true' );

function digi_sk_out( array $data, int $status = 200 ) {
	status_header( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Cache-Control: no-store' );
	echo wp_json_encode( $data );
	exit;
}

/** Widgets por tipo e IDs de un árbol del constructor. */
function digi_sk_walk( array $els, array &$widgets, array &$ids ) {
	foreach ( $els as $e ) {
		if ( ! is_array( $e ) ) {
			continue;
		}
		$ids[] = (string) ( $e['id'] ?? '' );
		if ( 'widget' === ( $e['elType'] ?? '' ) ) {
			$w             = (string) $e['widgetType'];
			$widgets[ $w ] = ( $widgets[ $w ] ?? 0 ) + 1;
		}
		digi_sk_walk( (array) ( $e['elements'] ?? array() ), $widgets, $ids );
	}
}

add_action(
	'wp_loaded',
	static function () {
		if ( ! isset( $_GET['digi_sk'] ) ) {
			return;
		}
		$token = trim( (string) @file_get_contents( DIGI_SK_DIR . '/token.txt' ) );
		if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) {
			digi_sk_out( array( 'error' => 'token' ), 403 );
		}
		$step  = (string) $_GET['digi_sk'];
		$value = (string) ( $_GET['value'] ?? '' );
		switch ( $step ) {
			case 'ping':
				$exp = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance->experiments : null;
				digi_sk_out(
					array(
						'ready'     => did_action( 'elementor/loaded' ) > 0,
						'wp'        => $GLOBALS['wp_version'],
						'php'       => PHP_VERSION,
						'core'      => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
						'container' => $exp ? (bool) $exp->is_feature_active( 'container' ) : null,
						'mcp'       => (bool) get_option( 'elementor_mcp_enabled' ),
						'sales'     => class_exists( '\Digitalisimo\Elements\Sales\Module' ),
					)
				);
				break;
			case 'container':
				update_option( 'elementor_experiment-container', 'active' === $value ? 'active' : 'inactive' );
				digi_sk_out( array( 'ok' => true ) );
				break;
			case 'page':
				$id   = (int) $value;
				$post = get_post( $id );
				if ( ! $post ) {
					digi_sk_out( array( 'exists' => false ) );
				}
				$data    = json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
				$widgets = array();
				$ids     = array();
				digi_sk_walk( is_array( $data ) ? $data : array(), $widgets, $ids );
				$upload = wp_upload_dir();
				digi_sk_out(
					array(
						'exists'        => true,
						'status'        => $post->post_status,
						'type'          => $post->post_type,
						'title'         => $post->post_title,
						'author'        => (int) $post->post_author,
						'page_template' => get_post_meta( $id, '_wp_page_template', true ),
						'edit_mode'     => get_post_meta( $id, '_elementor_edit_mode', true ),
						'template_type' => get_post_meta( $id, '_elementor_template_type', true ),
						'version'       => get_post_meta( $id, '_elementor_version', true ),
						'first_type'    => is_array( $data ) && isset( $data[0]['elType'] ) ? $data[0]['elType'] : null,
						'top'           => is_array( $data ) ? count( $data ) : 0,
						'widgets'       => $widgets,
						'ids'           => $ids,
						'css_file'      => file_exists( $upload['basedir'] . '/elementor/css/post-' . $id . '.css' ),
					)
				);
				break;
			case 'pages':
				$counts = wp_count_posts( 'page' );
				digi_sk_out( array( 'publish' => (int) $counts->publish, 'draft' => (int) $counts->draft, 'all' => (int) $counts->publish + (int) $counts->draft + (int) $counts->pending + (int) $counts->private + (int) $counts->future ) );
				break;
			case 'library':
				$counts = wp_count_posts( 'elementor_library' );
				digi_sk_out( array( 'count' => (int) $counts->publish + (int) $counts->draft + (int) $counts->private ) );
				break;
			case 'unfiltered':
				if ( '1' === $value ) {
					update_option( 'elementor_unfiltered_files_upload', 1 );
				} else {
					delete_option( 'elementor_unfiltered_files_upload' );
				}
				digi_sk_out( array( 'ok' => true ) );
				break;
			case 'login':
				$user = get_user_by( 'login', $value );
				if ( ! $user ) {
					digi_sk_out( array( 'ok' => false ), 404 );
				}
				wp_set_current_user( $user->ID );
				wp_set_auth_cookie( $user->ID, true );
				digi_sk_out( array( 'ok' => true ) );
				break;
			case 'abilities':
				$ids = function_exists( 'wp_get_abilities' ) ? array_map( static function ( $a ) { return $a->get_name(); }, wp_get_abilities() ) : array();
				digi_sk_out( array( 'abilities' => array_values( $ids ) ) );
				break;
		}
		digi_sk_out( array( 'error' => 'paso desconocido' ), 400 );
	},
	1
);
